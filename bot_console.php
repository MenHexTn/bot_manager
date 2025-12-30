<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$bot_id = isset($_GET['bot_id']) ? intval($_GET['bot_id']) : 0;

// Verify bot ownership
if ($_SESSION['user_type'] != 'admin') {
    $check_query = "SELECT id FROM bots WHERE id = :bot_id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':bot_id', $bot_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        die("Access denied.");
    }
}

// Get bot info
$bot_query = "SELECT * FROM bots WHERE id = :bot_id";
$bot_stmt = $db->prepare($bot_query);
$bot_stmt->bindParam(':bot_id', $bot_id);
$bot_stmt->execute();
$bot = $bot_stmt->fetch(PDO::FETCH_ASSOC);

if (!$bot) {
    die("Bot not found.");
}

// Get bot logs
$logs_query = "SELECT * FROM bot_logs WHERE bot_id = :bot_id ORDER BY created_at DESC LIMIT 100";
$logs_stmt = $db->prepare($logs_query);
$logs_stmt->bindParam(':bot_id', $bot_id);
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Console - <?php echo htmlspecialchars($bot['bot_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #1e1e1e;
            color: #fff;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #2d2d2d;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .header h1 {
            color: #fff;
            font-size: 24px;
        }

        .header h1 span {
            color: #667eea;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .console-container {
            background: #252526;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .console-header {
            background: #3e3e42;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .console-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bot-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .online {
            background: #4caf50;
            color: white;
        }

        .offline {
            background: #f44336;
            color: white;
        }

        .console-controls {
            display: flex;
            gap: 10px;
        }

        .console-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-refresh {
            background: #2196f3;
            color: white;
        }

        .btn-clear {
            background: #ff9800;
            color: white;
        }

        .console-body {
            padding: 20px;
            height: 600px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
        }

        .log-entry {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            background: #1e1e1e;
            border-left: 4px solid #2196f3;
        }

        .log-timestamp {
            color: #888;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .log-message {
            color: #fff;
            word-break: break-all;
        }

        .log-info {
            border-left-color: #2196f3;
        }

        .log-error {
            border-left-color: #f44336;
        }

        .log-warning {
            border-left-color: #ff9800;
        }

        .log-success {
            border-left-color: #4caf50;
        }

        .console-input {
            background: #3e3e42;
            padding: 15px 20px;
            border-top: 1px solid #555;
        }

        .input-group {
            display: flex;
            gap: 10px;
        }

        .cmd-input {
            flex: 1;
            background: #1e1e1e;
            border: 1px solid #555;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;