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
    $bot_id = $_POST['bot_id'];
    
    // Check if user is admin or owns the bot
    if ($_SESSION['user_type'] == 'admin') {
        $query = "DELETE FROM bots WHERE id = :bot_id";
    } else {
        $query = "DELETE FROM bots WHERE id = :bot_id AND user_id = :user_id";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':bot_id', $bot_id);
    
    if ($_SESSION['user_type'] != 'admin') {
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
    }
    
    if ($stmt->execute()) {
        if ($_SESSION['user_type'] == 'admin') {
            header('Location: admin_panel.php');
        } else {
            header('Location: user_panel.php');
        }
        exit();
    }
}

// If deletion fails or invalid request, redirect back
if ($_SESSION['user_type'] == 'admin') {
    header('Location: admin_panel.php');
} else {
    header('Location: user_panel.php');
}
exit();
?>