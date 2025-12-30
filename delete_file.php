<?php
// delete_file.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['bot_id']) || !isset($_GET['file'])) {
    header('Location: user_panel.php');
    exit();
}

$bot_id = intval($_GET['bot_id']);
$filename = basename($_GET['file']);

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

// Don't delete critical files
$protected_files = ['index.js', 'package.json'];
if (in_array($filename, $protected_files)) {
    header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=Cannot+delete+critical+files');
    exit();
}

// Delete file
$bot_dir = __DIR__ . '/bots/bot_' . $bot_id;
$file_path = $bot_dir . '/' . $filename;

if (file_exists($file_path) && unlink($file_path)) {
    header('Location: bot_manage.php?bot_id=' . $bot_id . '&success=File+deleted');
} else {
    header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=Failed+to+delete+file');
}
exit();
?>