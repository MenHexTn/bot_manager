<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Check - BotManager</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🛠️ System Check</h1>
    
    <?php
    echo "<h3>PHP Information:</h3>";
    echo "PHP Version: " . phpversion() . "<br>";
    echo "Safe Mode: " . (ini_get('safe_mode') ? 'On' : 'Off') . "<br>";
    echo "Disabled Functions: " . (ini_get('disable_functions') ?: 'None') . "<br>";
    
    echo "<h3>Server Information:</h3>";
    echo "Server OS: " . php_uname() . "<br>";
    echo "Web Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
    
    echo "<h3>Command Execution Test:</h3>";
    
    // Test 1: Simple command
    $test1 = shell_exec('echo "Hello World"');
    echo "Test 1 (echo): " . ($test1 ? '<span class="success">✓ Works</span>' : '<span class="error">✗ Failed</span>') . "<br>";
    
    // Test 2: Node.js check
    $test2 = shell_exec('node --version 2>&1');
    echo "Test 2 (Node.js): ";
    if (strpos($test2, 'v') === 0) {
        echo '<span class="success">✓ ' . trim($test2) . '</span>';
    } else {
        echo '<span class="error">✗ Not found: ' . htmlspecialchars($test2) . '</span>';
    }
    echo "<br>";
    
    // Test 3: NPM check
    $test3 = shell_exec('npm --version 2>&1');
    echo "Test 3 (NPM): ";
    if (is_numeric(trim($test3))) {
        echo '<span class="success">✓ Version ' . trim($test3) . '</span>';
    } else {
        echo '<span class="error">✗ Not found: ' . htmlspecialchars($test3) . '</span>';
    }
    echo "<br>";
    
    // Test 4: Directory permissions
    $test_dir = __DIR__ . '/bots';
    echo "Test 4 (Directory permissions for /bots): ";
    if (!file_exists($test_dir)) {
        if (mkdir($test_dir, 0777, true)) {
            echo '<span class="success">✓ Created successfully</span>';
        } else {
            echo '<span class="error">✗ Cannot create directory</span>';
        }
    } else {
        if (is_writable($test_dir)) {
            echo '<span class="success">✓ Writable</span>';
        } else {
            echo '<span class="error">✗ Not writable</span>';
        }
    }
    echo "<br>";
    
    // Test 5: Process execution
    echo "Test 5 (Background process): ";
    $test_file = tempnam(sys_get_temp_dir(), 'test_');
    $cmd = 'echo "Test" > ' . escapeshellarg($test_file) . ' & echo $!';
    $pid = trim(shell_exec($cmd));
    
    sleep(1);
    if (file_exists($test_file) && filesize($test_file) > 0) {
        echo '<span class="success">✓ Works (PID: ' . $pid . ')</span>';
        unlink($test_file);
    } else {
        echo '<span class="error">✗ Failed</span>';
    }
    echo "<br>";
    
    // Test 6: Check if shell_exec is disabled
    echo "Test 6 (shell_exec enabled): ";
    if (function_exists('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec')) {
        echo '<span class="success">✓ Enabled</span>';
    } else {
        echo '<span class="error">✗ Disabled in php.ini</span>';
    }
    echo "<br>";
    
    echo "<h3>Suggested Fixes:</h3>";
    echo "<ol>";
    echo "<li>Make sure Node.js is installed: <code>node --version</code> should show version</li>";
    echo "<li>Make sure /bots directory exists and is writable</li>";
    echo "<li>Check php.ini: <code>disable_functions</code> should not include shell_exec</li>";
    echo "<li>On Windows XAMPP, you might need to add Node.js to PATH</li>";
    echo "<li>Try running as administrator if on Windows</li>";
    echo "</ol>";
    ?>
    
    <h3>Quick Test Bot:</h3>
    <form method="POST" action="test_bot.php">
        <button type="submit">Test Simple Bot</button>
    </form>
</body>
</html>