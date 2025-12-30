<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect based on user type
if ($_SESSION['user_type'] == 'admin') {
    header('Location: admin_panel.php');
} else {
    header('Location: user_panel.php');
}
exit();
?>