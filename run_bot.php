<?php
// run_bot.php - SIMPLE WORKING VERSION
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Accept GET parameter
if (!isset($_GET['bot_id'])) {
    header('Location: user_panel.php?error=Invalid+request');
    exit();
}

$bot_id = intval($_GET['bot_id']);

$database = new Database();
$db = $database->getConnection();

try {
    // Verify bot ownership
    $query = "SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':bot_id', $bot_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bot) {
        header('Location: user_panel.php?error=Access+denied');
        exit();
    }
    
    // Create bot directory
    $bot_dir = __DIR__ . '/bots/bot_' . $bot_id;
    if (!file_exists($bot_dir)) {
        mkdir($bot_dir, 0777, true);
    }
    
    // Create logs directory
    $logs_dir = __DIR__ . '/bots/logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0777, true);
    }
    
    // Create simple bot code
    $bot_code = 'const { Client, GatewayIntentBits } = require(\'discord.js\');
const token = \'' . addslashes($bot['bot_token']) . '\';

console.log(\'🚀 Starting Discord bot...\');
console.log(\'📁 Directory: \' + process.cwd());

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
    ]
});

client.once(\'ready\', () => {
    console.log(\'✅ Bot is online as \' + client.user.tag);
    console.log(\'👥 Serving \' + client.guilds.cache.size + \' servers\');
});

client.on(\'messageCreate\', message => {
    if (message.author.bot) return;
    
    if (message.content === \'!ping\') {
        message.reply(\'🏓 Pong!\');
    }
    
    if (message.content === \'!status\') {
        const uptime = process.uptime();
        const hours = Math.floor(uptime / 3600);
        const minutes = Math.floor((uptime % 3600) / 60);
        const seconds = Math.floor(uptime % 60);
        
        message.reply(`Bot Status:\n🟢 Online\n⏰ Uptime: ${hours}h ${minutes}m ${seconds}s\n👥 Servers: ${client.guilds.cache.size}`);
    }
});

client.login(token).catch(error => {
    console.error(\'❌ Login failed:\', error.message);
    process.exit(1);
});';
    
    // Save bot file
    file_put_contents($bot_dir . '/index.js', $bot_code);
    
    // Create package.json if not exists
    if (!file_exists($bot_dir . '/package.json')) {
        $package_json = [
            'name' => 'bot_' . $bot_id,
            'version' => '1.0.0',
            'dependencies' => [
                'discord.js' => '^14.14.1'
            ],
            'scripts' => [
                'start' => 'node index.js'
            ]
        ];
        file_put_contents($bot_dir . '/package.json', json_encode($package_json, JSON_PRETTY_PRINT));
    }
    
    // Install dependencies in background
    chdir($bot_dir);
    if (!file_exists($bot_dir . '/node_modules')) {
        shell_exec('npm install --silent 2>&1');
    }
    
    // Kill existing process
    if ($bot['process_id']) {
        exec("taskkill /F /PID " . $bot['process_id'] . " 2>nul");
    }
    
    // Start bot process
    $log_file = $logs_dir . '/bot_' . $bot_id . '.log';
    
    // Clear old log
    if (file_exists($log_file)) {
        unlink($log_file);
    }
    
    // Start bot (Windows method)
    $batch_content = '@echo off' . PHP_EOL;
    $batch_content .= 'chcp 65001 > nul' . PHP_EOL;
    $batch_content .= 'title bot_' . $bot_id . PHP_EOL;
    $batch_content .= 'cd /d "' . $bot_dir . '"' . PHP_EOL;
    $batch_content .= 'node index.js > "' . $log_file . '" 2>&1' . PHP_EOL;
    
    $batch_file = $bot_dir . '/start.bat';
    file_put_contents($batch_file, $batch_content);
    
    // Execute
    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
    );
    
    $process = proc_open('start /B "" "' . $batch_file . '"', $descriptorspec, $pipes);
    
    if (is_resource($process)) {
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
    
    // Wait for bot to start
    sleep(2);
    
    // Try to find PID
    $pid = 0;
    $cmd = 'wmic process where "name=\'node.exe\'" get processid,commandline';
    $processes = shell_exec($cmd);
    
    if ($processes) {
        $lines = explode("\n", trim($processes));
        foreach ($lines as $line) {
            if (strpos($line, 'bot_' . $bot_id) !== false || strpos($line, 'index.js') !== false) {
                $parts = preg_split('/\s+/', trim($line));
                $pid = end($parts);
                break;
            }
        }
    }
    
    // Check if bot started successfully
    $started = false;
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        
        if (strpos($log_content, 'Starting Discord bot') !== false || 
            strpos($log_content, 'Bot is online') !== false) {
            $started = true;
        }
    }
    
    if ($started) {
        // Update database
        $update_query = "UPDATE bots SET status = 'online', process_id = :pid, last_seen = NOW() 
                         WHERE id = :bot_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':pid', $pid);
        $update_stmt->bindParam(':bot_id', $bot_id);
        $update_stmt->execute();
        
        // Redirect back to bot management page
        header('Location: bot_manage.php?bot_id=' . $bot_id . '&success=Bot+started+successfully');
        exit();
    } else {
        // Bot didn't start
        $error_msg = 'Bot failed to start. ';
        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            $error_msg .= 'Check console logs.';
        }
        header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=' . urlencode($error_msg));
        exit();
    }
    
} catch (Exception $e) {
    header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=' . urlencode('Error: ' . $e->getMessage()));
    exit();
}

// If we get here, something went wrong
header('Location: user_panel.php?error=Something+went+wrong');
exit();
?>