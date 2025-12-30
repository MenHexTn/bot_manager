<?php
// check_environment.php
echo "<h1>Environment Check</h1>";

echo "<h2>PHP Info</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PHP User: " . get_current_user() . "<br>";
echo "PHP Safe Mode: " . (ini_get('safe_mode') ? 'On' : 'Off') . "<br>";

echo "<h2>Server Info</h2>";
echo "Server OS: " . PHP_OS . "<br>";
echo "Web Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

echo "<h2>Node.js Check</h2>";
$node = shell_exec('node --version 2>&1');
$npm = shell_exec('npm --version 2>&1');
echo "Node.js: " . ($node ? $node : "NOT FOUND") . "<br>";
echo "NPM: " . ($npm ? $npm : "NOT FOUND") . "<br>";

echo "<h2>Permissions Check</h2>";
$bots_dir = __DIR__ . '/bots';
echo "Bots directory: " . $bots_dir . "<br>";
echo "Exists: " . (file_exists($bots_dir) ? 'Yes' : 'No') . "<br>";
echo "Writable: " . (is_writable($bots_dir) ? 'Yes' : 'No') . "<br>";

echo "<h2>Test Bot Creation</h2>";
$test_dir = $bots_dir . '/test_bot';
if (!file_exists($test_dir)) {
    mkdir($test_dir, 0777, true);
}

$test_file = $test_dir . '/test.js';
file_put_contents($test_file, 'console.log("Test successful!"); process.exit(0);');

$test_output = shell_exec('node "' . $test_file . '" 2>&1');
echo "Test output: <pre>" . htmlspecialchars($test_output) . "</pre>";

echo "<h2>Discord.js Test</h2>";
$package_json = $test_dir . '/package.json';
file_put_contents($package_json, json_encode([
    'name' => 'test_bot',
    'dependencies' => ['discord.js' => '^14.14.1']
], JSON_PRETTY_PRINT));

chdir($test_dir);
$install = shell_exec('npm install --silent 2>&1');
echo "NPM Install output: <pre>" . htmlspecialchars($install) . "</pre>";

$module_test = $test_dir . '/module_test.js';
file_put_contents($module_test, 'try { console.log("Discord.js test"); const djs = require("discord.js"); console.log("Success! Version: " + djs.version); } catch(e) { console.log("Error: " + e.message); }');

$module_output = shell_exec('node "' . $module_test . '" 2>&1');
echo "Module test: <pre>" . htmlspecialchars($module_output) . "</pre>";
?>