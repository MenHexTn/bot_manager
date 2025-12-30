<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

echo "<h2>Bot Status Check</h2>";

// Get user's bots
$query = "SELECT * FROM bots WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$bots = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($bots as $bot) {
    echo "<h3>" . htmlspecialchars($bot['bot_name']) . " (ID: " . $bot['id'] . ")</h3>";
    echo "Database Status: " . $bot['status'] . "<br>";
    echo "Process ID: " . ($bot['process_id'] ?: 'None') . "<br>";
    
    // Check if process is actually running
    if ($bot['process_id']) {
        $cmd = 'tasklist /FI "PID eq ' . $bot['process_id'] . '" 2>nul';
        $output = shell_exec($cmd);
        
        if (strpos($output, 'node.exe') !== false) {
            echo "✅ Process is running<br>";
            
            // Update status if not online
            if ($bot['status'] != 'online') {
                $update = $db->prepare("UPDATE bots SET status = 'online' WHERE id = ?");
                $update->execute([$bot['id']]);
                echo "Updated database status to online<br>";
            }
        } else {
            echo "❌ Process NOT running (PID " . $bot['process_id'] . ")<br>";
            
            // Update status if not stopped
            if ($bot['status'] != 'stopped' && $bot['status'] != 'offline') {
                $update = $db->prepare("UPDATE bots SET status = 'stopped', process_id = NULL WHERE id = ?");
                $update->execute([$bot['id']]);
                echo "Updated database status to stopped<br>";
            }
        }
    } else {
        echo "No process ID in database<br>";
        
        // Check if any node process is running for this bot
        $bot_dir = __DIR__ . '/bots/bot_' . $bot['id'];
        $cmd = 'wmic process where "commandline like \'%' . str_replace('\\', '\\\\', $bot_dir) . '%\'" get processid 2>nul';
        $output = shell_exec($cmd);
        
        if ($output && preg_match('/\d+/', $output, $matches)) {
            echo "⚠️ Found running process (PID: " . $matches[0] . ") but not in database<br>";
            
            // Update database with found PID
            $update = $db->prepare("UPDATE bots SET status = 'online', process_id = ? WHERE id = ?");
            $update->execute([$matches[0], $bot['id']]);
            echo "Updated database with PID " . $matches[0] . "<br>";
        }
    }
    
    echo "<hr>";
}

echo '<br><a href="user_panel.php">← Back to User Panel</a>';
?>