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

// Get bot files
$files_query = "SELECT * FROM bot_files WHERE bot_id = :bot_id ORDER BY filepath";
$files_stmt = $db->prepare($files_query);
$files_stmt->bindParam(':bot_id', $bot_id);
$files_stmt->execute();
$files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle file operations
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['upload_file'])) {
        $filename = basename($_FILES['file']['name']);
        $filepath = isset($_POST['filepath']) ? trim($_POST['filepath']) : '/';
        
        if ($filename) {
            $content = file_get_contents($_FILES['file']['tmp_name']);
            $file_type = pathinfo($filename, PATHINFO_EXTENSION);
            $size = $_FILES['file']['size'];
            
            $full_path = $filepath . ($filepath == '/' ? '' : '/') . $filename;
            
            // Check if it's an archive file
            $is_archive = in_array(strtolower($file_type), ['zip', 'rar', '7z', 'tar', 'gz']);
            
            if ($is_archive) {
                // Handle archive extraction
                $extract_result = extractArchive($_FILES['file']['tmp_name'], $bot_id, $filepath, $file_type);
                if ($extract_result['success']) {
                    $success = "Archive extracted successfully! " . $extract_result['message'];
                } else {
                    $error = "Failed to extract archive: " . $extract_result['error'];
                }
            } else {
                // Regular file upload
                $query = "INSERT INTO bot_files (bot_id, filename, filepath, content, file_type, size) 
                          VALUES (:bot_id, :filename, :filepath, :content, :file_type, :size)
                          ON DUPLICATE KEY UPDATE content = VALUES(content), size = VALUES(size), updated_at = NOW()";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':bot_id', $bot_id);
                $stmt->bindParam(':filename', $filename);
                $stmt->bindParam(':filepath', $full_path);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':file_type', $file_type);
                $stmt->bindParam(':size', $size);
                
                if ($stmt->execute()) {
                    $success = "File uploaded successfully!";
                } else {
                    $error = "Failed to upload file.";
                }
            }
        }
    } elseif (isset($_POST['create_file'])) {
        $filename = trim($_POST['new_filename']);
        $filepath = trim($_POST['new_filepath']);
        $content = $_POST['file_content'];
        
        if ($filename) {
            $full_path = $filepath . ($filepath == '/' ? '' : '/') . $filename;
            $file_type = pathinfo($filename, PATHINFO_EXTENSION);
            
            $query = "INSERT INTO bot_files (bot_id, filename, filepath, content, file_type) 
                      VALUES (:bot_id, :filename, :filepath, :content, :file_type)
                      ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':bot_id', $bot_id);
            $stmt->bindParam(':filename', $filename);
            $stmt->bindParam(':filepath', $full_path);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':file_type', $file_type);
            
            if ($stmt->execute()) {
                $success = "File created successfully!";
            } else {
                $error = "Failed to create file.";
            }
        }
    } elseif (isset($_POST['delete_file'])) {
        $file_id = intval($_POST['file_id']);
        
        $query = "DELETE FROM bot_files WHERE id = :file_id AND bot_id = :bot_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':file_id', $file_id);
        $stmt->bindParam(':bot_id', $bot_id);
        
        if ($stmt->execute()) {
            $success = "File deleted successfully!";
        }
    } elseif (isset($_POST['create_folder'])) {
        $folder_name = trim($_POST['folder_name']);
        $folder_path = trim($_POST['folder_path']);
        
        if ($folder_name) {
            $full_path = $folder_path . ($folder_path == '/' ? '' : '/') . $folder_name . '/';
            
            // Create a placeholder file to represent the folder
            $query = "INSERT INTO bot_files (bot_id, filename, filepath, content, file_type) 
                      VALUES (:bot_id, :filename, :filepath, :content, :file_type)";
            
            $filename = '.folder';
            $content = 'This is a folder placeholder';
            $file_type = 'folder';
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':bot_id', $bot_id);
            $stmt->bindParam(':filename', $filename);
            $stmt->bindParam(':filepath', $full_path);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':file_type', $file_type);
            
            if ($stmt->execute()) {
                $success = "Folder created successfully!";
            } else {
                $error = "Failed to create folder.";
            }
        }
    } elseif (isset($_POST['extract_archive'])) {
        $archive_id = intval($_POST['archive_id']);
        $extract_path = trim($_POST['extract_path']);
        
        // Get archive file
        $query = "SELECT * FROM bot_files WHERE id = :file_id AND bot_id = :bot_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':file_id', $archive_id);
        $stmt->bindParam(':bot_id', $bot_id);
        $stmt->execute();
        $archive_file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($archive_file) {
            // Save archive to temp file
            $temp_file = tempnam(sys_get_temp_dir(), 'archive_');
            file_put_contents($temp_file, $archive_file['content']);
            
            $file_type = strtolower($archive_file['file_type']);
            $result = extractArchive($temp_file, $bot_id, $extract_path, $file_type);
            
            unlink($temp_file);
            
            if ($result['success']) {
                $success = "Archive extracted successfully! " . $result['message'];
            } else {
                $error = "Failed to extract archive: " . $result['error'];
            }
        }
    }
    
    // Refresh files list
    $files_stmt->execute();
    $files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to extract archives
function extractArchive($archive_path, $bot_id, $base_path = '/', $archive_type = 'zip') {
    global $db;
    
    $result = ['success' => false, 'message' => '', 'error' => ''];
    $base_path = rtrim($base_path, '/') . '/';
    
    try {
        $extracted_files = [];
        
        if ($archive_type == 'zip') {
            // Extract ZIP archive
            $zip = new ZipArchive;
            if ($zip->open($archive_path) === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $file_content = $zip->getFromIndex($i);
                    
                    if ($file_content !== false) {
                        $file_path = $base_path . $filename;
                        $file_type = pathinfo($filename, PATHINFO_EXTENSION);
                        
                        // Skip directory entries
                        if (substr($filename, -1) == '/') {
                            continue;
                        }
                        
                        // Save to database
                        $query = "INSERT INTO bot_files (bot_id, filename, filepath, content, file_type, size) 
                                  VALUES (:bot_id, :filename, :filepath, :content, :file_type, :size)
                                  ON DUPLICATE KEY UPDATE content = VALUES(content), size = VALUES(size), updated_at = NOW()";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':bot_id', $bot_id);
                        $stmt->bindParam(':filename', basename($filename));
                        $stmt->bindParam(':filepath', $file_path);
                        $stmt->bindParam(':content', $file_content);
                        $stmt->bindParam(':file_type', $file_type);
                        $stmt->bindValue(':size', strlen($file_content));
                        
                        if ($stmt->execute()) {
                            $extracted_files[] = $filename;
                        }
                    }
                }
                $zip->close();
                $result['success'] = true;
                $result['message'] = count($extracted_files) . " files extracted.";
            } else {
                $result['error'] = "Failed to open ZIP archive.";
            }
        } elseif ($archive_type == 'rar') {
            // Extract RAR archive (requires rar extension or external command)
            if (class_exists('RarArchive')) {
                // PHP RAR extension
                $rar = RarArchive::open($archive_path);
                if ($rar !== FALSE) {
                    $entries = $rar->getEntries();
                    foreach ($entries as $entry) {
                        if (!$entry->isDirectory()) {
                            $stream = $entry->getStream();
                            if ($stream) {
                                $file_content = stream_get_contents($stream);
                                fclose($stream);
                                
                                $file_path = $base_path . $entry->getName();
                                $file_type = pathinfo($entry->getName(), PATHINFO_EXTENSION);
                                
                                // Save to database
                                $query = "INSERT INTO bot_files (bot_id, filename, filepath, content, file_type, size) 
                                          VALUES (:bot_id, :filename, :filepath, :content, :file_type, :size)
                                          ON DUPLICATE KEY UPDATE content = VALUES(content), size = VALUES(size), updated_at = NOW()";
                                
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':bot_id', $bot_id);
                                $stmt->bindParam(':filename', basename($entry->getName()));
                                $stmt->bindParam(':filepath', $file_path);
                                $stmt->bindParam(':content', $file_content);
                                $stmt->bindParam(':file_type', $file_type);
                                $stmt->bindValue(':size', $entry->getUnpackedSize());
                                
                                if ($stmt->execute()) {
                                    $extracted_files[] = $entry->getName();
                                }
                            }
                        }
                    }
                    $rar->close();
                    $result['success'] = true;
                    $result['message'] = count($extracted_files) . " files extracted.";
                } else {
                    $result['error'] = "Failed to open RAR archive.";
                }
            } elseif (exec('which unrar') || exec('where unrar')) {
                // Use external unrar command
                $temp_dir = sys_get_temp_dir() . '/extract_' . uniqid();
                mkdir($temp_dir, 0777, true);
                
                $command = escapeshellcmd("unrar x -y " . escapeshellarg($archive_path) . " " . escapeshellarg($temp_dir));
                exec($command, $output, $return_var);
                
                if ($return_var === 0) {
                    $extracted_files = extractDirectory($temp_dir, $bot_id, $base_path);
                    $result['success'] = true;
                    $result['message'] = count($extracted_files) . " files extracted using unrar.";
                } else {
                    $result['error'] = "unrar command failed.";
                }
                
                // Cleanup
                deleteDirectory($temp_dir);
            } else {
                $result['error'] = "RAR extraction requires PHP RAR extension or unrar command.";
            }
        } else {
            $result['error'] = "Unsupported archive type: " . $archive_type;
        }
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Helper function to extract files from directory
function extractDirectory($source_dir, $bot_id, $base_path) {
    global $db;
    
    $extracted_files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $relative_path = str_replace($source_dir, '', $item->getPathname());
            $relative_path = ltrim($relative_path, '/\\');
            
            $file_path = $base_path . $relative_path;
            $file_content = file_get_contents($item->getRealPath());
            $file_type = pathinfo($item->getFilename(), PATHINFO_EXTENSION);
            
            // Save to database
            $query = "INSERT INTO bot_files (bot_id, filename, filepath, content, file_type, size) 
                      VALUES (:bot_id, :filename, :filepath, :content, :file_type, :size)
                      ON DUPLICATE KEY UPDATE content = VALUES(content), size = VALUES(size), updated_at = NOW()";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':bot_id', $bot_id);
            $stmt->bindParam(':filename', $item->getFilename());
            $stmt->bindParam(':filepath', $file_path);
            $stmt->bindParam(':content', $file_content);
            $stmt->bindParam(':file_type', $file_type);
            $stmt->bindValue(':size', $item->getSize());
            
            if ($stmt->execute()) {
                $extracted_files[] = $relative_path;
            }
        }
    }
    
    return $extracted_files;
}

// Helper function to delete directory
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

// Group files by directory
$directories = [];
foreach ($files as $file) {
    $dir = dirname($file['filepath']);
    if (!isset($directories[$dir])) {
        $directories[$dir] = [];
    }
    $directories[$dir][] = $file;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - <?php echo htmlspecialchars($bot['bot_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #333;
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

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
        }

        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .bot-info {
            margin-bottom: 30px;
        }

        .bot-info h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .bot-info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .bot-info-label {
            color: #666;
            font-weight: 500;
        }

        .bot-info-value {
            color: #333;
        }

        .bot-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-online {
            background: #d4edda;
            color: #155724;
        }

        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateX(5px);
        }

        .main-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .tab {
            padding: 10px 25px;
            background: #f8f9fa;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .file-browser {
            margin-bottom: 30px;
        }

        .file-browser-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .directory {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .directory-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }

        .directory-name {
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .file-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .file-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }

        .file-icon {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .file-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
            word-break: break-all;
        }

        .file-size {
            color: #666;
            font-size: 12px;
        }

        .file-archive-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffc107;
            color: #333;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }

        .file-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }

        .file-action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            color: #333;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        textarea.form-control {
            min-height: 200px;
            font-family: monospace;
            resize: vertical;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-block {
            width: 100%;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .upload-area i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .upload-text {
            color: #666;
            margin-bottom: 10px;
        }

        .upload-hint {
            color: #999;
            font-size: 14px;
        }

        .archive-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .archive-info h4 {
            color: #856404;
            margin-bottom: 10px;
        }

        .archive-info p {
            color: #856404;
            font-size: 14px;
        }

        .extract-options {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            .files-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>File Manager: <span><?php echo htmlspecialchars($bot['bot_name']); ?></span></h1>
            <a href="user_panel.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="dashboard">
            <div class="sidebar">
                <div class="bot-info">
                    <h3>Bot Information</h3>
                    <div class="bot-info-item">
                        <span class="bot-info-label">Status:</span>
                        <span class="bot-status <?php echo $bot['status']; ?>">
                            <?php echo ucfirst($bot['status']); ?>
                        </span>
                    </div>
                    <div class="bot-info-item">
                        <span class="bot-info-label">Type:</span>
                        <span class="bot-info-value"><?php echo strtoupper($bot['bot_type']); ?></span>
                    </div>
                    <div class="bot-info-item">
                        <span class="bot-info-label">Entry File:</span>
                        <span class="bot-info-value"><?php echo htmlspecialchars($bot['entry_file']); ?></span>
                    </div>
                    <div class="bot-info-item">
                        <span class="bot-info-label">Files Count:</span>
                        <span class="bot-info-value"><?php echo count($files); ?></span>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="bot_settings.php?bot_id=<?php echo $bot_id; ?>" class="action-btn">
                        <i class="fas fa-cog"></i> Bot Settings
                    </a>
                    <a href="bot_console.php?bot_id=<?php echo $bot_id; ?>" class="action-btn">
                        <i class="fas fa-terminal"></i> Bot Console
                    </a>
                    <button onclick="runBot(<?php echo $bot_id; ?>)" class="action-btn" style="text-align: left; border: none;">
                        <i class="fas fa-play"></i> Run Bot
                    </button>
                    <button onclick="stopBot(<?php echo $bot_id; ?>)" class="action-btn" style="text-align: left; border: none;">
                        <i class="fas fa-stop"></i> Stop Bot
                    </button>
                </div>
            </div>

            <div class="main-content">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('files')">Files</button>
                    <button class="tab" onclick="switchTab('upload')">Upload</button>
                    <button class="tab" onclick="switchTab('create')">Create</button>
                    <button class="tab" onclick="switchTab('folder')">New Folder</button>
                </div>

                <div id="files-tab" class="tab-content active">
                    <div class="file-browser">
                        <div class="file-browser-header">
                            <h3>Project Files</h3>
                            <button onclick="refreshFiles()" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>

                        <?php foreach($directories as $dir => $dir_files): ?>
                            <div class="directory">
                                <div class="directory-header">
                                    <div class="directory-name">
                                        <i class="fas fa-folder"></i>
                                        <?php echo htmlspecialchars($dir == '.' ? '/' : $dir); ?>
                                    </div>
                                </div>
                                <div class="files-grid">
                                    <?php foreach($dir_files as $file): 
                                        $is_archive = in_array(strtolower($file['file_type']), ['zip', 'rar', '7z', 'tar', 'gz']);
                                    ?>
                                        <div class="file-item" onclick="fileClick(<?php echo $file['id']; ?>, '<?php echo $file['file_type']; ?>')">
                                            <?php if($is_archive): ?>
                                                <div class="file-archive-badge"><?php echo strtoupper($file['file_type']); ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="file-icon">
                                                <?php 
                                                $ext = strtolower($file['file_type']);
                                                if ($ext == 'folder') {
                                                    echo '<i class="fas fa-folder"></i>';
                                                } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                                                    echo '<i class="fas fa-file-archive"></i>';
                                                } elseif (in_array($ext, ['js', 'ts'])) {
                                                    echo '<i class="fab fa-js"></i>';
                                                } elseif ($ext == 'py') {
                                                    echo '<i class="fab fa-python"></i>';
                                                } elseif (in_array($ext, ['php', 'html', 'css'])) {
                                                    echo '<i class="fab fa-php"></i>';
                                                } elseif (in_array($ext, ['json', 'xml', 'yml', 'yaml'])) {
                                                    echo '<i class="fas fa-code"></i>';
                                                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg'])) {
                                                    echo '<i class="fas fa-image"></i>';
                                                } else {
                                                    echo '<i class="fas fa-file"></i>';
                                                }
                                                ?>
                                            </div>
                                            <div class="file-name"><?php echo htmlspecialchars($file['filename']); ?></div>
                                            <div class="file-size">
                                                <?php 
                                                $size = $file['size'];
                                                if ($size < 1024) {
                                                    echo $size . ' bytes';
                                                } elseif ($size < 1048576) {
                                                    echo round($size / 1024, 2) . ' KB';
                                                } else {
                                                    echo round($size / 1048576, 2) . ' MB';
                                                }
                                                ?>
                                            </div>
                                            <div class="file-actions">
                                                <?php if($is_archive): ?>
                                                    <button onclick="event.stopPropagation(); extractArchiveModal(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['filename']); ?>')" 
                                                            class="file-action-btn btn-success">
                                                        Extract
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="event.stopPropagation(); editFile(<?php echo $file['id']; ?>)" 
                                                            class="file-action-btn btn-primary">
                                                        Edit
                                                    </button>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Delete this file?');">
                                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                    <button type="submit" name="delete_file" 
                                                            class="file-action-btn btn-danger"
                                                            onclick="event.stopPropagation();">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="upload-tab" class="tab-content">
                    <div class="archive-info">
                        <h4><i class="fas fa-info-circle"></i> Archive Support</h4>
                        <p>You can upload ZIP and RAR files. They will be automatically extracted to the specified path.</p>
                        <p><strong>Supported formats:</strong> ZIP, RAR, 7Z, TAR, GZ</p>
                        <?php if(!class_exists('ZipArchive')): ?>
                            <p style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> ZIP extraction requires PHP Zip extension.</p>
                        <?php endif; ?>
                        <?php if(!class_exists('RarArchive') && !(exec('which unrar') || exec('where unrar'))): ?>
                            <p style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> RAR extraction requires PHP RAR extension or unrar command.</p>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Select File to Upload</label>
                            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div class="upload-text">Click to select file or drag and drop</div>
                                <div class="upload-hint">Maximum file size: 50MB</div>
                                <input type="file" id="fileInput" name="file" style="display: none;" 
                                       onchange="document.getElementById('fileName').textContent = this.files[0].name">
                            </div>
                            <div id="fileName" style="margin-top: 10px; color: #666;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Upload Path</label>
                            <input type="text" class="form-control" name="filepath" value="/" 
                                   placeholder="e.g., /src or /config">
                        </div>
                        
                        <button type="submit" name="upload_file" class="btn btn-primary btn-block">
                            <i class="fas fa-upload"></i> Upload File
                        </button>
                    </form>
                </div>

                <div id="create-tab" class="tab-content">
                    <form method="POST" id="createFileForm">
                        <div class="form-group">
                            <label class="form-label">File Path</label>
                            <input type="text" class="form-control" name="new_filepath" value="/" 
                                   placeholder="e.g., /src or /config">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">File Name</label>
                            <input type="text" class="form-control" name="new_filename" required 
                                   placeholder="e.g., index.js, bot.py, config.json">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">File Content</label>
                            <textarea class="form-control" name="file_content" rows="10" 
                                      placeholder="Enter file content here..."></textarea>
                        </div>
                        
                        <button type="submit" name="create_file" class="btn btn-primary btn-block">
                            <i class="fas fa-plus"></i> Create File
                        </button>
                    </form>
                </div>

                <div id="folder-tab" class="tab-content">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Folder Path</label>
                            <input type="text" class="form-control" name="folder_path" value="/" 
                                   placeholder="e.g., /src or /config">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Folder Name</label>
                            <input type="text" class="form-control" name="folder_name" required 
                                   placeholder="e.g., utils, models, views">
                        </div>
                        
                        <button type="submit" name="create_folder" class="btn btn-primary btn-block">
                            <i class="fas fa-folder-plus"></i> Create Folder
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit File Modal -->
    <div id="editFileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit File</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editFileForm" method="POST">
                    <input type="hidden" id="edit_file_id" name="file_id">
                    <div class="form-group">
                        <label class="form-label">File Content</label>
                        <textarea id="fileEditor" name="file_content" class="form-control" rows="20"></textarea>
                    </div>
                    <button type="submit" name="save_file" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Extract Archive Modal -->
    <div id="extractArchiveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Extract Archive</h3>
                <button class="close-modal" onclick="closeExtractModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" id="extract_archive_id" name="archive_id">
                    <div class="form-group">
                        <label class="form-label">Extract to Path</label>
                        <input type="text" class="form-control" name="extract_path" value="/" 
                               placeholder="e.g., /src or /extracted">
                    </div>
                    
                    <div class="extract-options">
                        <h4><i class="fas fa-info-circle"></i> Extraction Options</h4>
                        <p>Files will be extracted to the specified path. Existing files will be overwritten.</p>
                    </div>
                    
                    <button type="submit" name="extract_archive" class="btn btn-success btn-block">
                        <i class="fas fa-file-archive"></i> Extract Archive
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Activate clicked button
            event.target.classList.add('active');
        }
        
        let currentFileId = null;
        
        function fileClick(fileId, fileType) {
            if (fileType === 'folder') {
                // Handle folder click (you can implement navigation)
                alert('Folder clicked. Implement navigation if needed.');
            } else {
                editFile(fileId);
            }
        }
        
        function editFile(fileId) {
            currentFileId = fileId;
            
            // Fetch file content via AJAX
            fetch('get_file.php?file_id=' + fileId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const editor = document.getElementById('fileEditor');
                        editor.value = data.content;
                        
                        // Show modal
                        document.getElementById('editFileModal').style.display = 'flex';
                    } else {
                        alert('Error loading file: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading file.');
                });
        }
        
        function extractArchiveModal(fileId, filename) {
            document.getElementById('extract_archive_id').value = fileId;
            document.getElementById('extractArchiveModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editFileModal').style.display = 'none';
        }
        
        function closeExtractModal() {
            document.getElementById('extractArchiveModal').style.display = 'none';
        }
        
        function runBot(botId) {
            if (confirm('Start this bot?')) {
                fetch('run_bot.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'bot_id=' + botId
                })
                .then(response => response.text())
                .then(data => {
                    alert(data);
                    location.reload();
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
        
        function stopBot(botId) {
            if (confirm('Stop this bot?')) {
                fetch('stop_bot.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'bot_id=' + botId
                })
                .then(response => response.text())
                .then(data => {
                    alert(data);
                    location.reload();
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
        
        function refreshFiles() {
            location.reload();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editFileModal');
            const extractModal = document.getElementById('extractArchiveModal');
            
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == extractModal) {
                extractModal.style.display = 'none';
            }
        };
    </script>
</body>
</html>