<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check user type
if ($_SESSION['user_type'] == 'admin') {
    header('Location: admin_panel.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Initialize variables
$error = '';
$success = '';
$message = '';

// Check for messages from GET parameters
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

// Get user's bots
$bots = [];
try {
    $query = "SELECT * FROM bots WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle bot creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_bot'])) {
    $bot_name = trim($_POST['bot_name'] ?? '');
    $bot_token = trim($_POST['bot_token'] ?? '');
    $bot_type = $_POST['bot_type'] ?? 'nodejs';
    $entry_file = trim($_POST['entry_file'] ?? 'index.js');
    
    // Validate inputs
    if (empty($bot_name) || empty($bot_token) || empty($bot_type) || empty($entry_file)) {
        $error = "All fields are required!";
    } else {
        // Check if user has reached bot limit
        if (count($bots) >= ($_SESSION['max_bots'] ?? 1)) {
            $error = "You have reached your bot limit. Max allowed: " . ($_SESSION['max_bots'] ?? 1);
        } else {
            try {
                $query = "INSERT INTO bots (user_id, bot_name, bot_token, bot_type, entry_file, status) 
                          VALUES (:user_id, :bot_name, :bot_token, :bot_type, :entry_file, 'offline')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':bot_name', $bot_name);
                $stmt->bindParam(':bot_token', $bot_token);
                $stmt->bindParam(':bot_type', $bot_type);
                $stmt->bindParam(':entry_file', $entry_file);
                
                if ($stmt->execute()) {
                    $success = "Bot created successfully! You can now manage it.";
                    // Refresh bot list
                    $stmt = $db->prepare("SELECT * FROM bots WHERE user_id = :user_id ORDER BY created_at DESC");
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->execute();
                    $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to create bot. Please try again.";
                }
            } catch(Exception $e) {
                $error = "Database error: " . $e->getMessage();
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
    <title>User Dashboard - TN-Nodes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 30px 20px;
            color: white;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 40px;
            text-align: center;
        }

        .user-info {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-info h3 {
            margin-bottom: 5px;
            color: white;
        }

        .user-info p {
            font-size: 14px;
            opacity: 0.8;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            margin-bottom: 15px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover, .nav-links a.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-links i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .logout-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            color: #666;
        }

        .card-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .card-icon {
            font-size: 40px;
            color: #667eea;
        }

        /* Bot Grid Cards */
        .bots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .bot-card-square {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 2px solid transparent;
        }

        .bot-card-square:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }

        .bot-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .bot-card-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bot-type-icon {
            font-size: 20px;
        }

        .nodejs-icon {
            color: #68a063;
        }

        .python-icon {
            color: #3572A5;
        }

        .php-icon {
            color: #777BB4;
        }

        .bot-status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-online {
            background: #d4edda;
            color: #155724;
        }

        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }

        .status-running {
            background: #d1ecf1;
            color: #0c5460;
        }

        .bot-card-info {
            flex-grow: 1;
            margin-bottom: 20px;
        }

        .bot-card-info p {
            margin-bottom: 8px;
            color: #666;
            font-size: 14px;
        }

        .bot-card-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .bot-action-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-manage {
            background: #667eea;
            color: white;
        }

        .btn-manage:hover {
            background: #5a6fd8;
        }

        .btn-run-card {
            background: #28a745;
            color: white;
        }

        .btn-run-card:hover {
            background: #218838;
        }

        .btn-stop-card {
            background: #dc3545;
            color: white;
        }

        .btn-stop-card:hover {
            background: #c82333;
        }

        /* Add Bot Form */
        .bot-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .bot-form h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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

        .form-control, .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            background-color: white;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
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

        .section-title {
            margin: 30px 0 20px 0;
            color: #333;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #667eea;
            opacity: 0.5;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                margin-bottom: 20px;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .bots-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">TN-Nodes</div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></h3>
            <p>User Panel</p>
        </div>
        <ul class="nav-links">
            <li><a href="user_panel.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#"><i class="fas fa-robot"></i> My Bots</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Dashboard</h1>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="dashboard-cards">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Total Bots</div>
                    <div class="card-icon"><i class="fas fa-robot"></i></div>
                </div>
                <div class="card-value"><?php echo count($bots); ?></div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Bot Limit</div>
                    <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                </div>
                <div class="card-value"><?php echo $_SESSION['max_bots'] ?? 1; ?></div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Active Bots</div>
                    <div class="card-icon"><i class="fas fa-play-circle"></i></div>
                </div>
                <div class="card-value">
                    <?php 
                    $active_count = 0;
                    foreach($bots as $bot) {
                        if($bot['status'] == 'online') $active_count++;
                    }
                    echo $active_count;
                    ?>
                </div>
            </div>
        </div>

        <!-- Add Bot Form -->
        <div class="bot-form">
            <h2>Create New Bot</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Bot Name</label>
                        <input type="text" class="form-control" name="bot_name" required 
                               placeholder="My Awesome Bot">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Bot Token</label>
                        <input type="text" class="form-control" name="bot_token" required 
                               placeholder="MTI2OTYxODQzMTI6xODA4...">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Bot Type</label>
                        <select class="form-select" name="bot_type" id="botTypeSelect" required>
                            <option value="">Select bot type</option>
                            <option value="nodejs">Node.js</option>
                            <option value="python">Python</option>
                            <option value="php">PHP</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Entry File</label>
                        <input type="text" class="form-control" name="entry_file" id="entryFile" required 
                               placeholder="index.js" value="index.js">
                    </div>
                </div>
                
                <button type="submit" name="add_bot" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Bot
                </button>
            </form>
        </div>

        <h2 class="section-title">My Bots</h2>

        <?php if(empty($bots)): ?>
            <div class="empty-state">
                <i class="fas fa-robot"></i>
                <h3>No bots yet</h3>
                <p>Create your first bot using the form above!</p>
            </div>
        <?php else: ?>
            <div class="bots-grid">
                <?php foreach($bots as $bot): ?>
                    <div class="bot-card-square">
                        <div class="bot-card-header">
                            <div class="bot-card-name">
                                <?php if($bot['bot_type'] == 'nodejs'): ?>
                                    <i class="fab fa-node-js nodejs-icon bot-type-icon"></i>
                                <?php elseif($bot['bot_type'] == 'python'): ?>
                                    <i class="fab fa-python python-icon bot-type-icon"></i>
                                <?php else: ?>
                                    <i class="fab fa-php php-icon bot-type-icon"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($bot['bot_name']); ?>
                            </div>
                            <div class="bot-status-badge status-<?php echo $bot['status']; ?>">
                                <?php echo strtoupper($bot['status']); ?>
                            </div>
                        </div>
                        
                        <div class="bot-card-info">
                            <p><strong>Type:</strong> <?php echo strtoupper($bot['bot_type']); ?></p>
                            <p><strong>Token:</strong> <?php echo substr($bot['bot_token'], 0, 20) . '...'; ?></p>
                            <p><strong>Last Seen:</strong> <?php echo $bot['last_seen'] ? date('Y-m-d H:i:s', strtotime($bot['last_seen'])) : 'Never'; ?></p>
                            <p><strong>Created:</strong> <?php echo date('Y-m-d H:i:s', strtotime($bot['created_at'])); ?></p>
                            <?php if($bot['process_id']): ?>
                                <p><strong>PID:</strong> <?php echo $bot['process_id']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="bot-card-actions">
                            <!-- Manage Button - Opens bot_manage.php -->
                            <a href="bot_manage.php?bot_id=<?php echo $bot['id']; ?>" class="bot-action-btn btn-manage">
                                <i class="fas fa-cog"></i> Manage
                            </a>
                            
                            <!-- Run Button -->
                            <form method="POST" action="run_bot.php" style="display: inline; width: 100%;" 
                                  onsubmit="return confirm('Start this bot?')">
                                <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                <button type="submit" class="bot-action-btn btn-run-card" id="run-btn-<?php echo $bot['id']; ?>">
                                    <i class="fas fa-play"></i> Run
                                </button>
                            </form>
                            
                            <!-- Stop Button -->
                            <form method="POST" action="stop_bot.php" style="display: inline; width: 100%;" 
                                  onsubmit="return confirm('Stop this bot?')">
                                <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                <button type="submit" class="bot-action-btn btn-stop-card" id="stop-btn-<?php echo $bot['id']; ?>">
                                    <i class="fas fa-stop"></i> Stop
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Update entry file based on bot type
        document.getElementById('botTypeSelect').addEventListener('change', function() {
            const type = this.value;
            const entryFile = document.getElementById('entryFile');
            
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

        // Add loading indicators to forms
        document.addEventListener('submit', function(e) {
            if (e.target.matches('form[action*="run_bot.php"], form[action*="stop_bot.php"]')) {
                const button = e.target.querySelector('button[type="submit"]');
                const originalText = button.innerHTML;
                button.innerHTML = '<span class="spinner"></span> Processing...';
                button.disabled = true;
                
                // Re-enable button after 5 seconds if still disabled (in case of error)
                setTimeout(() => {
                    if (button.disabled) {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>