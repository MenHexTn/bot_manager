<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$bot_id = intval($_POST['bot_id'] ?? 0);

// Get bot info
$stmt = $db->prepare("SELECT * FROM bots WHERE id = ?");
$stmt->execute([$bot_id]);
$bot = $stmt->fetch();

if (!$bot) {
    exit('Bot not found');
}

// Create bot directory
$bot_dir = __DIR__ . '\\bots\\bot_' . $bot_id;
if (!file_exists($bot_dir)) {
    mkdir($bot_dir, 0777, true);
}

// Create simple bot file
$bot_code = <<<'JS'
const { Client, GatewayIntentBits } = require('discord.js');

const token = '{$bot['bot_token']}';

console.log('Starting Discord bot with token:', token.substring(0, 10) + '...');

const client = new Client({
    intents: [GatewayIntentBits.Guilds, GatewayIntentBits.GuildMessages]
});

client.once('ready', () => {
    console.log('✅ Bot ready as', client.user.tag);
});

client.login(token).catch(err => {
    console.error('Login failed:', err.message);
    process.exit(1);
});

// Keep process alive
setInterval(() => {}, 60000);
JS;

// Replace token placeholder
$bot_code = str_replace('{$bot[\'bot_token\']}', $bot['bot_token'], $bot_code);
file_put_contents($bot_dir . '\\index.js', $bot_code);

// Create package.json
$package = [
    'name' => 'bot_' . $bot_id,
    'version' => '1.0.0',
    'dependencies' => ['discord.js' => '^14.14.1']
];
file_put_contents($bot_dir . '\\package.json', json_encode($package));

// Install dependencies
chdir($bot_dir);
echo "Installing dependencies...<br>";
shell_exec('npm install --silent 2>&1');

// Start bot
echo "Starting bot...<br>";
$log_file = __DIR__ . '\\bots\\logs\\bot_' . $bot_id . '.log';

// Create a batch file
$batch = '@echo off' . PHP_EOL;
$batch .= 'cd /d "' . $bot_dir . '"' . PHP_EOL;
$batch .= 'node index.js > "' . $log_file . '" 2>&1' . PHP_EOL;

$batch_file = $bot_dir . '\\run.bat';
file_put_contents($batch_file, $batch);

// Execute
pclose(popen("start /B \"\" \"$batch_file\"", "r"));

echo "✅ Bot started! Check logs: " . htmlspecialchars($log_file) . "<br>";

// Update status
$db->prepare("UPDATE bots SET status = 'online', last_seen = NOW() WHERE id = ?")
   ->execute([$bot_id]);
?>