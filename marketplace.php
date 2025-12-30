<?php
// marketplace.php - Add to your existing project
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all bot templates
$templates = [];
try {
    $query = "SELECT * FROM bot_templates WHERE status = 'active' ORDER BY downloads DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    error_log("Marketplace error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bot Marketplace - BotManager</title>
    <!-- Use your existing CSS -->
    <style>
        .marketplace-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .marketplace-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .bot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .bot-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .bot-card:hover {
            transform: translateY(-5px);
        }
        
        .bot-icon {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .bot-category {
            display: inline-block;
            padding: 5px 12px;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 10px;
        }
        
        .bot-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
            margin-top: 15px;
        }
        
        .install-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            margin-top: 15px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Use your existing sidebar/header -->
    <div class="sidebar">
        <!-- Your existing sidebar code -->
    </div>
    
    <div class="main-content">
        <div class="marketplace-container">
            <div class="marketplace-header">
                <h1><i class="fas fa-store"></i> Bot Marketplace</h1>
                <p>Browse and install pre-made Discord bots with one click!</p>
            </div>
            
            <!-- Categories Filter -->
            <div style="margin-bottom: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="category-btn active">All Bots</button>
                <button class="category-btn">Moderation</button>
                <button class="category-btn">Music</button>
                <button class="category-btn">Fun</button>
                <button class="category-btn">Utility</button>
            </div>
            
            <!-- Bot Grid -->
            <div class="bot-grid">
                <?php if(empty($templates)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        <i class="fas fa-robot" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3>No bots available yet</h3>
                        <p>Check back soon for bot templates!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($templates as $bot): ?>
                    <div class="bot-card">
                        <div class="bot-icon">
                            <i class="fas fa-<?php echo $bot['icon'] ?? 'robot'; ?>"></i>
                        </div>
                        
                        <span class="bot-category"><?php echo ucfirst($bot['category']); ?></span>
                        
                        <h3><?php echo htmlspecialchars($bot['name']); ?></h3>
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                            <?php echo htmlspecialchars(substr($bot['description'], 0, 100)); ?>...
                        </p>
                        
                        <div class="bot-stats">
                            <span><i class="fas fa-download"></i> <?php echo $bot['downloads']; ?> installs</span>
                            <span><i class="fas fa-star"></i> <?php echo $bot['rating']; ?>/5</span>
                            <span>v<?php echo $bot['version']; ?></span>
                        </div>
                        
                        <a href="install_bot.php?template_id=<?php echo $bot['id']; ?>" class="install-btn">
                            <i class="fas fa-plus"></i> Install Bot
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>