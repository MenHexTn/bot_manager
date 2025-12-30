<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Discord Token</title>
</head>
<body>
    <h2>Test Discord Bot Token</h2>
    <form method="POST">
        <textarea name="token" rows="3" cols="50" placeholder="Paste your Discord bot token here"></textarea><br>
        <button type="submit">Test Token</button>
    </form>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['token'])) {
        $token = trim($_POST['token']);
        
        echo "<h3>Testing token: " . substr($token, 0, 10) . "...</h3>";
        
        // Test 1: Validate format
        if (preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token)) {
            echo "✅ Token format is valid<br>";
        } else {
            echo "❌ Invalid token format<br>";
        }
        
        // Test 2: Extract bot ID
        $parts = explode('.', $token);
        $bot_id = $parts[0] ?? '';
        echo "Bot ID from token: " . htmlspecialchars($bot_id) . "<br>";
        
        // Test 3: Try to get bot info from Discord API
        $url = "https://discord.com/api/v10/users/" . $bot_id;
        $headers = [
            'Authorization: Bot ' . $token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            echo "✅ Discord API connection successful!<br>";
            echo "🤖 Bot username: " . htmlspecialchars($data['username'] . '#' . $data['discriminator']) . "<br>";
            echo "🆔 Bot ID: " . htmlspecialchars($data['id']) . "<br>";
        } elseif ($http_code === 401) {
            echo "❌ Invalid token (Unauthorized)<br>";
        } else {
            echo "⚠️ API returned code: " . $http_code . "<br>";
            echo "Response: " . htmlspecialchars($response) . "<br>";
        }
        
        curl_close($ch);
        
        // Test 4: Create a simple test bot file
        $test_dir = __DIR__ . '\\test_bot';
        if (!file_exists($test_dir)) {
            mkdir($test_dir, 0777, true);
        }
        
        $test_code = <<<JS
const { Client } = require('discord.js');
const token = '$token';

console.log('Testing token...');

const client = new Client({ intents: [] });

client.login(token)
    .then(() => {
        console.log('✅ Login successful!');
        console.log('Bot: ' + client.user.tag);
        client.destroy();
        process.exit(0);
    })
    .catch(err => {
        console.error('❌ Login failed:', err.message);
        process.exit(1);
    });
JS;
        
        file_put_contents($test_dir . '\\test.js', $test_code);
        
        // Create package.json
        $package = ['dependencies' => ['discord.js' => '^14.14.1']];
        file_put_contents($test_dir . '\\package.json', json_encode($package));
        
        echo "<br><h4>Running quick Node.js test...</h4>";
        
        chdir($test_dir);
        $output = shell_exec('node test.js 2>&1');
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    ?>
</body>
</html>