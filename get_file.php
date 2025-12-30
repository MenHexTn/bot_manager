<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;

// Verify file access
$query = "SELECT bf.*, b.user_id FROM bot_files bf 
          JOIN bots b ON bf.bot_id = b.id 
          WHERE bf.id = :file_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':file_id', $file_id);
$stmt->execute();

$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit();
}

// Check if user owns the bot or is admin
if ($_SESSION['user_type'] != 'admin' && $file['user_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

echo json_encode([
    'success' => true,
    'filename' => $file['filename'],
    'content' => $file['content'],
    'file_type' => $file['file_type']
]);
?>