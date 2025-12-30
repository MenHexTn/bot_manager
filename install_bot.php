<?php
// install_bot.php - Add to your existing project
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$template_id = intval($_GET['template_id'] ?? 0);
$database = new Database();
$db = $database->getConnection();

// Get template details
$template = [];
try {
    $query = "SELECT * FROM bot_templates WHERE id = :id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $template_id);
    $stmt->execute();
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    error_log("Template error: " . $e->getMessage());
}

if (!$template) {
    header('Location: marketplace.php?error=Template+not+found');
    exit();
}

// Handle installation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bot_name = trim($_POST['bot_name']);
    $bot_token = trim($_POST['bot_token']);
    
    try {
        // 1. Create bot in your existing bots table
        $query = "INSERT INTO bots (user_id, bot_name, bot_token, bot_type, status, entry_file) 
                  VALUES (:user_id, :bot_name, :bot_token, 'nodejs', 'offline', 'index.js')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':bot_name', $bot_name);
        $stmt->bindParam(':bot_token', $bot_token);
        $stmt->execute();
        
        $bot_id = $db->lastInsertId();
        
        // 2. Save template installation record
        $query = "INSERT INTO installed_templates (user_id, template_id, bot_id, custom_name) 
                  VALUES (:user_id, :template_id, :bot_id, :custom_name)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':template_id', $template_id);
        $stmt->bindParam(':bot_id', $bot_id);
        $stmt->bindParam(':custom_name', $bot_name);
        $stmt->execute();
        
        // 3. Update template download count
        $query = "UPDATE bot_templates SET downloads = downloads + 1 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $template_id);
        $stmt->execute();
        
        // 4. Create bot files using template
        $bot_dir = __DIR__ . '/bots/bot_' . $bot_id;
        if (!file_exists($bot_dir)) {
            mkdir($bot_dir, 0777, true);
        }
        
        // Save bot code
        file_put_contents($bot_dir . '/index.js', $template['code_template']);
        
        // Save package.json
        file_put_contents($bot_dir . '/package.json', $template['package_json']);
        
        // 5. Redirect to deployment page
        header('Location: deploy_replit.php?bot_id=' . $bot_id . '&from_template=' . $template_id);
        exit();
        
    } catch(Exception $e) {
        $error = "Installation failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Install <?php echo htmlspecialchars($template['name']); ?> - BotManager</title>
    <style>
        .install-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .template-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .template-icon {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .btn-install {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .features-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Use your existing sidebar -->
    <div class="sidebar">
        <!-- Your existing sidebar code -->
    </div>
    
    <div class="main-content">
        <div class="install-container">
            <div class="template-header">
                <div class="template-icon">
                    <i class="fas fa-<?php echo $template['icon']; ?>"></i>
                </div>
                <h1>Install <?php echo htmlspecialchars($template['name']); ?></h1>
                <p>Version <?php echo $template['version']; ?> • <?php echo $template['downloads']; ?> installs</p>
            </div>
            
            <div class="features-list">
                <h3><i class="fas fa-check-circle"></i> Features Included:</h3>
                <p><?php echo htmlspecialchars($template['description']); ?></p>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Bot Display Name</label>
                    <input type="text" name="bot_name" class="form-control" 
                           value="<?php echo htmlspecialchars($template['name']); ?> Bot" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Discord Bot Token</label>
                    <input type="text" name="bot_token" class="form-control" 
                           placeholder="Paste your bot token here" required>
                    <small style="color: #666;">Get token from: 
                        <a href="https://discord.com/developers/applications" target="_blank">
                            Discord Developer Portal
                        </a>
                    </small>
                </div>
                
                <?php if(isset($error)): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-install">
                    <i class="fas fa-download"></i> Install & Configure
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="marketplace.php" style="color: #667eea;">
                    <i class="fas fa-arrow-left"></i> Back to Marketplace
                </a>
            </div>
        </div>
    </div>
</body>
</html>