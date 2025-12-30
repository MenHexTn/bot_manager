<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (isset($_GET['bot_id'])) {
    $bot_id = intval($_GET['bot_id']);
    
    try {
        // Verify bot ownership
        if ($_SESSION['user_type'] != 'admin') {
            $check_query = "SELECT id FROM bots WHERE id = :bot_id AND user_id = :user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':bot_id', $bot_id);
            $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                echo json_encode(['error' => 'Access denied']);
                exit();
            }
        }
        
        // Get bot info
        $bot_query = "SELECT process_id, status FROM bots WHERE id = :bot_id";
        $bot_stmt = $db->prepare($bot_query);
        $bot_stmt->bindParam(':bot_id', $bot_id);
        $bot_stmt->execute();
        $bot = $bot_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bot) {
            echo json_encode(['error' => 'Bot not found']);
            exit();
        }
        
        // Check if process is running (Windows)
        $is_running = false;
        if (!empty($bot['process_id'])) {
            $cmd = 'tasklist /FI "PID eq ' . $bot['process_id'] . '" /FO CSV /NH';
            $output = shell_exec($cmd);
            $is_running = strpos($output, 'node.exe') !== false;
        }
        
        // If no PID or process not found, check by title
        if (!$is_running) {
            $cmd = 'tasklist /FI "WINDOWTITLE eq bot_' . $bot_id . '" /FO CSV /NH';
            $output = shell_exec($cmd);
            $is_running = strpos($output, 'node.exe') !== false;
        }
        
        // Determine status
        $status = $is_running ? 'online' : 'offline';
        
        // Update database if status changed
        if ($bot['status'] != $status) {
            $update_query = "UPDATE bots SET status = :status WHERE id = :bot_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $status);
            $update_stmt->bindParam(':bot_id', $bot_id);
            $update_stmt->execute();
        }
        
        echo json_encode([
            'status' => $status,
            'is_running' => $is_running,
            'process_id' => $bot['process_id']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Bot ID required']);
}
?>