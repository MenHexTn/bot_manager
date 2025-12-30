<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bot_id'])) {
    $bot_id = intval($_POST['bot_id']);
    
    try {
        // Verify bot ownership
        if ($_SESSION['user_type'] != 'admin') {
            $check_query = "SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':bot_id', $bot_id);
            $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $check_stmt->execute();
            
            $bot = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bot) {
                header('Location: user_panel.php?error=Access+denied');
                exit();
            }
        } else {
            $check_query = "SELECT * FROM bots WHERE id = :bot_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':bot_id', $bot_id);
            $check_stmt->execute();
            $bot = $check_stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Kill the process
        if (!empty($bot['process_id'])) {
            exec("taskkill /F /PID " . $bot['process_id'] . " 2>nul");
        }
        
        // Kill any node process running this bot
        $bot_dir = __DIR__ . '/bots/bot_' . $bot_id;
        exec('wmic process where "commandline like \'%' . str_replace('\\', '\\\\', $bot_dir) . '%\'" delete 2>nul');
        
        // Kill by window title
        exec("taskkill /F /IM node.exe /FI \"WINDOWTITLE eq bot_" . $bot_id . "\" 2>nul");
        
        // Update database
        $update_query = "UPDATE bots SET status = 'stopped', process_id = NULL WHERE id = :bot_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':bot_id', $bot_id);
        
        if ($update_stmt->execute()) {
            header('Location: user_panel.php?success=Bot+stopped+successfully');
            exit();
        } else {
            header('Location: user_panel.php?error=Failed+to+update+database');
            exit();
        }
        
    } catch (Exception $e) {
        header('Location: user_panel.php?error=' . urlencode('Error: ' . $e->getMessage()));
        exit();
    }
} else {
    header('Location: user_panel.php?error=Invalid+request');
    exit();
}
?>