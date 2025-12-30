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

$error = '';
$success = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_settings'])) {
        $bot_name = trim($_POST['bot_name']);
        $bot_token = trim($_POST['bot_token']);
        $bot_type = $_POST['bot_type'];
        $entry_file = trim($_POST['entry_file']);
        
        // Validate entry file
        if ($bot_type == 'nodejs' && !preg_match('/\.(js|ts)$/i', $entry_file)) {
            $error = "Node.js bots must have .js or .ts entry file";
        } elseif ($bot_type == 'python' && !preg_match('/\.py$/i', $entry_file)) {
            $error = "Python bots must have .py entry file";
        } elseif ($bot_type == 'php' && !preg_match('/\.php$/i', $entry_file)) {
            $error = "PHP bots must have .php entry file";
        } else {
            // Check if updated_at column exists
            $check_column = $db->query("SHOW COLUMNS FROM bots LIKE 'updated_at'");
            $has_updated_at = $check_column->rowCount() > 0;
            
            if ($has_updated_at) {
                $update_query = "UPDATE bots SET bot_name = :bot_name, bot_token = :bot_token, 
                                bot_type = :bot_type, entry_file = :entry_file, updated_at = NOW() 
                                WHERE id = :bot_id";
            } else {
                $update_query = "UPDATE bots SET bot_name = :bot_name, bot_token = :bot_token, 
                                bot_type = :bot_type, entry_file = :entry_file 
                                WHERE id = :bot_id";
            }
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':bot_name', $bot_name);
            $update_stmt->bindParam(':bot_token', $bot_token);
            $update_stmt->bindParam(':bot_type', $bot_type);
            $update_stmt->bindParam(':entry_file', $entry_file);
            $update_stmt->bindParam(':bot_id', $bot_id);
            
            if ($update_stmt->execute()) {
                $success = "Settings updated successfully!";
                // Refresh bot info
                $bot_stmt->execute();
                $bot = $bot_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Failed to update settings.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Settings - <?php echo htmlspecialchars($bot['bot_name']); ?></title>
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
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
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

        .settings-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 16px;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
            padding-right: 40px;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
        }

        .bot-info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #333;
            font-weight: 600;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .online {
            background: #d4edda;
            color: #155724;
        }

        .offline {
            background: #f8d7da;
            color: #721c24;
        }

        .danger-zone {
            background: #fff5f5;
            border: 2px solid #f8d7da;
            border-radius: 10px;
            padding: 25px;
            margin-top: 40px;
        }

        .danger-zone h3 {
            color: #721c24;
            margin-bottom: 15px;
        }

        .danger-zone p {
            color: #856404;
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bot Settings: <span><?php echo htmlspecialchars($bot['bot_name']); ?></span></h1>
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

        <div class="settings-container">
            <div class="bot-info-card">
                <h3 style="margin-bottom: 15px; color: #333;">Current Bot Information</h3>
                <div class="info-row">
                    <span class="info-label">Current Status:</span>
                    <span class="status-badge <?php echo $bot['status']; ?>">
                        <?php echo ucfirst($bot['status']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Bot ID:</span>
                    <span class="info-value"><?php echo $bot['id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created:</span>
                    <span class="info-value"><?php echo date('Y-m-d H:i:s', strtotime($bot['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Updated:</span>
                    <span class="info-value">
                        <?php 
                        if (isset($bot['updated_at']) && $bot['updated_at'] != '0000-00-00 00:00:00' && !empty($bot['updated_at'])) {
                            echo date('Y-m-d H:i:s', strtotime($bot['updated_at']));
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Seen:</span>
                    <span class="info-value">
                        <?php 
                        if (isset($bot['last_seen']) && $bot['last_seen'] != '0000-00-00 00:00:00' && !empty($bot['last_seen'])) {
                            echo date('Y-m-d H:i:s', strtotime($bot['last_seen']));
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </span>
                </div>
                <?php if(isset($bot['process_id']) && !empty($bot['process_id'])): ?>
                    <div class="info-row">
                        <span class="info-label">Process ID:</span>
                        <span class="info-value"><?php echo $bot['process_id']; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Bot Name</label>
                        <input type="text" class="form-control" name="bot_name" 
                               value="<?php echo htmlspecialchars($bot['bot_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Bot Token</label>
                        <input type="text" class="form-control" name="bot_token" 
                               value="<?php echo htmlspecialchars($bot['bot_token']); ?>" required>
                        <small style="color: #666; margin-top: 5px; display: block;">
                            Get token from <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a>
                        </small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Bot Type</label>
                        <select class="form-select" name="bot_type" required>
                            <option value="nodejs" <?php echo $bot['bot_type'] == 'nodejs' ? 'selected' : ''; ?>>Node.js</option>
                            <option value="python" <?php echo $bot['bot_type'] == 'python' ? 'selected' : ''; ?>>Python</option>
                            <option value="php" <?php echo $bot['bot_type'] == 'php' ? 'selected' : ''; ?>>PHP</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Entry File</label>
                        <input type="text" class="form-control" name="entry_file" 
                               value="<?php echo htmlspecialchars($bot['entry_file']); ?>" required>
                        <small style="color: #666; margin-top: 5px; display: block;">
                            Main file to run (e.g., index.js, bot.py, index.php)
                        </small>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    
                    <a href="user_panel.php" class="btn" style="background: #6c757d; color: white; text-decoration: none;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>

            <div class="danger-zone">
                <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                <p>These actions are irreversible. Please proceed with caution.</p>
                
                <div style="display: flex; gap: 15px;">
                    <form method="POST" action="delete_bot.php" style="display: inline;">
                        <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                        <button type="submit" class="btn btn-danger" 
                                onclick="return confirm('Are you sure you want to delete this bot? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete Bot
                        </button>
                    </form>
                    
                    <button type="button" class="btn" style="background: #ffc107; color: #333;"
                            onclick="if(confirm('Reset bot to initial state? This will clear all files and settings.')) { 
                                window.location.href='reset_bot.php?bot_id=<?php echo $bot['id']; ?>';
                            }">
                        <i class="fas fa-redo"></i> Reset Bot
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update entry file placeholder based on bot type
        document.querySelector('select[name="bot_type"]').addEventListener('change', function() {
            const type = this.value;
            const entryFile = document.querySelector('input[name="entry_file"]');
            
            switch(type) {
                case 'nodejs':
                    entryFile.value = 'index.js';
                    break;
                case 'python':
                    entryFile.value = 'bot.py';
                    break;
                case 'php':
                    entryFile.value = 'index.php';
                    break;
                default:
                    entryFile.value = 'index.js';
            }
        });
    </script>
</body>
</html>