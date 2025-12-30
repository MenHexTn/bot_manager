<?php
// create_file.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_POST['bot_id']) || !isset($_POST['filename'])) {
    header('Location: user_panel.php');
    exit();
}

$bot_id = intval($_POST['bot_id']);
$filename = basename($_POST['filename']);

// Verify bot ownership
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id FROM bots WHERE id = :bot_id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':bot_id', $bot_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header('Location: user_panel.php?error=Access+denied');
    exit();
}

// Create file
$bot_dir = __DIR__ . '/bots/bot_' . $bot_id;
if (!file_exists($bot_dir)) {
    mkdir($bot_dir, 0777, true);
}

$file_path = $bot_dir . '/' . $filename;

if (file_exists($file_path)) {
    header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=File+already+exists');
    exit();
}

// Create empty file
if (file_put_contents($file_path, '')) {
    header('Location: bot_manage.php?bot_id=' . $bot_id . '&action=edit&file=' . urlencode($filename) . '&success=File+created');
} else {
    header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=Failed+to+create+file');
}
exit();
?>