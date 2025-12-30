<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all bots and users
$bots_query = "SELECT b.*, u.username FROM bots b JOIN users u ON b.user_id = u.id";
$bots_stmt = $db->prepare($bots_query);
$bots_stmt->execute();
$all_bots = $bots_stmt->fetchAll(PDO::FETCH_ASSOC);

$users_query = "SELECT * FROM users";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statistics
$online_bots = 0;
$offline_bots = 0;
foreach ($all_bots as $bot) {
    if ($bot['status'] == 'online') {
        $online_bots++;
    } else {
        $offline_bots++;
    }
}

// Handle user permissions update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_permissions'])) {
    $user_id = $_POST['user_id'];
    $max_bots = $_POST['max_bots'];
    
    $update_query = "UPDATE users SET max_bots = :max_bots WHERE id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':max_bots', $max_bots);
    $update_stmt->bindParam(':user_id', $user_id);
    
    if ($update_stmt->execute()) {
        $success = "Permissions updated successfully!";
    } else {
        $error = "Failed to update permissions.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - TN-Nodes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f5f7fa;
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
        }

        .icon-users {
            color: #4CAF50;
        }

        .icon-online {
            color: #2196F3;
        }

        .icon-offline {
            color: #F44336;
        }

        .icon-total {
            color: #9C27B0;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-container h2 {
            margin-bottom: 20px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        tr:hover {
            background: #f8f9fa;
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

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background: #e0a800;
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
            padding: 40px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .modal-header h2 {
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

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">TN-Nodes</div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($_SESSION['username']); ?></h3>
            <p>Admin Panel</p>
        </div>
        <ul class="nav-links">
            <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="#"><i class="fas fa-robot"></i> All Bots</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="dashboard-cards">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Total Users</div>
                    <div class="card-icon icon-users"><i class="fas fa-users"></i></div>
                </div>
                <div class="card-value"><?php echo count($all_users); ?></div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Online Bots</div>
                    <div class="card-icon icon-online"><i class="fas fa-signal"></i></div>
                </div>
                <div class="card-value"><?php echo $online_bots; ?></div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Offline Bots</div>
                    <div class="card-icon icon-offline"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="card-value"><?php echo $offline_bots; ?></div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Total Bots</div>
                    <div class="card-icon icon-total"><i class="fas fa-robot"></i></div>
                </div>
                <div class="card-value"><?php echo count($all_bots); ?></div>
            </div>
        </div>

        <div class="table-container">
            <h2>All Bots</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bot Name</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Last Seen</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_bots as $bot): ?>
                    <tr>
                        <td><?php echo $bot['id']; ?></td>
                        <td><?php echo htmlspecialchars($bot['bot_name']); ?></td>
                        <td><?php echo htmlspecialchars($bot['username']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $bot['status']; ?>">
                                <?php echo ucfirst($bot['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $bot['last_seen'] ? date('Y-m-d H:i:s', strtotime($bot['last_seen'])) : 'Never'; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($bot['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-primary" onclick="viewBot(<?php echo $bot['id']; ?>)">View</button>
                            <form action="delete_bot.php" method="POST" style="display: inline;">
                                <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <h2>All Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Max Bots</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $user['user_type'] == 'admin' ? 'online' : 'offline'; ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                        </td>
                        <td><?php echo $user['max_bots']; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($user['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-primary" onclick="editUser(<?php echo $user['id']; ?>)">Edit Permissions</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Edit User Modal -->
        <div id="editUserModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit User Permissions</h2>
                    <button class="close-modal" onclick="closeEditUserModal()">&times;</button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="form-group">
                        <label for="max_bots">Maximum Bots Allowed</label>
                        <input type="number" id="max_bots" name="max_bots" min="1" max="999" required>
                    </div>
                    <button type="submit" name="update_permissions" class="btn btn-primary">Update Permissions</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions for user permissions
        function editUser(userId) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('editUserModal').style.display = 'flex';
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('editUserModal');
            if (event.target == modal) {
                modal