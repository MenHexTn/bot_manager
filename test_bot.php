<?php
// test_bot.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

echo "<h1>Bot Debug Tool</h1>";

if (isset($_GET['bot_id'])) {
    $bot_id = intval($_GET['bot_id']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get bot info
    $query = "SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':bot_id', $bot_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bot) {
        echo "<h2>Testing Bot: " . htmlspecialchars($bot['bot_name']) . "</h2>";
        
        // Check directories
        $bot_dir = __DIR__ . '/bots/bot_' . $bot_id;
        $logs_dir = __DIR__ . '/bots/logs';
        
        echo "<h3>Directory Check:</h3>";
        echo "Bot Directory: " . $bot_dir . "<br>";
        echo "Exists: " . (file_exists($bot_dir) ? "✅ Yes" : "❌ No") . "<br>";
        echo "Logs Directory: " . $logs_dir . "<br>";
        echo "Exists: " . (file_exists($logs_dir) ? "✅ Yes" : "❌ No") . "<br>";
        
        // Check if index.js exists
        $index_file = $bot_dir . '/index.js';
        echo "<h3>Bot Files:</h3>";
        echo "index.js: " . (file_exists($index_file) ? "✅ Exists" : "❌ Missing") . "<br>";
        
        if (file_exists($index_file)) {
            echo "<pre>" . htmlspecialchars(file_get_contents($index_file)) . "</pre>";
        }
        
        // Check if Node.js is installed
        echo "<h3>Node.js Check:</h3>";
        $node_version = shell_exec('node --version 2>&1');
        echo "Node.js: " . ($node_version ? "✅ " . $node_version : "❌ Not found") . "<br>";
        
        // Try to run the bot manually
        echo "<h3>Manual Test:</h3>";
        if (file_exists($index_file)) {
            chdir($bot_dir);
            $output = shell_exec('node index.js 2>&1');
            echo "<pre>Output: " . htmlspecialchars($output) . "</pre>";
        }
        
        // Check logs
        $log_file = $logs_dir . '/bot_' . $bot_id . '.log';
        echo "<h3>Log File:</h3>";
        if (file_exists($log_file)) {
            echo "<pre>" . htmlspecialchars(file_get_contents($log_file)) . "</pre>";
        } else {
            echo "No log file found.<br>";
        }
        
    } else {
        echo "Bot not found.";
    }
} else {
    echo "<form method='GET'>
        <label>Enter Bot ID: </label>
        <input type='number' name='bot_id' required>
        <button type='submit'>Test Bot</button>
    </form>";
}
?>