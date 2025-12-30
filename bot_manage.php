<?php
// bot_manage.php - COMPLETE WITH WORKING FILE MANAGER AND AUTO-INSTALLATION
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['bot_id'])) {
    header('Location: user_panel.php');
    exit();
}

$bot_id = intval($_GET['bot_id']);

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get bot information
try {
    $query = "SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':bot_id', $bot_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bot) {
        header('Location: user_panel.php?error=Bot not found');
        exit();
    }
} catch(Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle installation request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['install_bot'])) {
    try {
        // Get the selected project type
        $project_type = $_POST['project_type'];
        $bot_token = trim($_POST['bot_token']);
        
        // Validate bot token
        if (empty($bot_token)) {
            header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=Bot token is required');
            exit();
        }
        
        // Bot directory
        $bot_dir = __DIR__ . '/bots/bot_' . $bot_id;
        
        // Create bot directory if it doesn't exist
        if (!file_exists($bot_dir)) {
            mkdir($bot_dir, 0777, true);
        }
        
        // Install files based on project type
        switch ($project_type) {
            case 'discord.js':
                installDiscordJS($bot_dir, $bot_token);
                $entry_file = 'index.js';
                break;
                
            case 'discord.py':
                installDiscordPy($bot_dir, $bot_token);
                $entry_file = 'main.py';
                break;
                
            case 'discord.php':
                installDiscordPHP($bot_dir, $bot_token);
                $entry_file = 'index.php';
                break;
                
            case 'custom':
                // User will upload their own files
                $entry_file = $_POST['entry_file'] ?? 'index.js';
                break;
                
            default:
                header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=Invalid project type');
                exit();
        }
        
        // Update bot information in database
        $update_query = "UPDATE bots SET 
                        bot_token = :bot_token,
                        bot_type = :bot_type,
                        entry_file = :entry_file,
                        status = 'stopped',
                        updated_at = NOW()
                        WHERE id = :bot_id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':bot_token', $bot_token);
        $update_stmt->bindParam(':bot_type', $project_type);
        $update_stmt->bindParam(':entry_file', $entry_file);
        $update_stmt->bindParam(':bot_id', $bot_id);
        $update_stmt->execute();
        
        // Refresh bot data
        $query = "SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':bot_id', $bot_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $bot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        header('Location: bot_manage.php?bot_id=' . $bot_id . '&success=Bot+installed+successfully&tab=files');
        exit();
        
    } catch (Exception $e) {
        header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=' . urlencode($e->getMessage()));
        exit();
    }
}

// Helper functions for installation
function installDiscordJS($bot_dir, $bot_token) {
    // Create package.json
    $package_json = json_encode([
        'name' => 'discord-bot',
        'version' => '1.0.0',
        'description' => 'Discord.js bot',
        'main' => 'index.js',
        'scripts' => [
            'start' => 'node index.js'
        ],
        'dependencies' => [
            'discord.js' => '^14.14.1',
            'dotenv' => '^16.3.1'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    file_put_contents($bot_dir . '/package.json', $package_json);
    
    // Create index.js with bot token
    $index_js = <<<JS
const { Client, GatewayIntentBits, Events } = require('discord.js');
const client = new Client({ 
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.GuildMembers
    ]
});

// Bot token from configuration
const BOT_TOKEN = '{$bot_token}';

client.once(Events.ClientReady, (c) => {
    console.log(\`✅ Logged in as \${c.user.tag}\`);
    console.log(\`👥 Serving \${client.guilds.cache.size} servers\`);
});

client.on(Events.MessageCreate, async (message) => {
    // Ignore messages from bots
    if (message.author.bot) return;

    // Basic ping command
    if (message.content.toLowerCase() === '!ping') {
        await message.reply('🏓 Pong!');
    }

    // Hello command
    if (message.content.toLowerCase() === '!hello') {
        await message.reply(\`Hello \${message.author.username}! 👋\`);
    }

    // Help command
    if (message.content.toLowerCase() === '!help') {
        const helpEmbed = {
            color: 0x0099ff,
            title: '🤖 Bot Commands',
            fields: [
                { name: '!ping', value: 'Check if bot is alive', inline: true },
                { name: '!hello', value: 'Say hello to the bot', inline: true },
                { name: '!help', value: 'Show this help message', inline: true }
            ],
            timestamp: new Date(),
            footer: {
                text: 'Bot Manager System'
            }
        };
        await message.channel.send({ embeds: [helpEmbed] });
    }
});

// Error handling
client.on(Events.Error, (error) => {
    console.error('Discord.js error:', error);
});

process.on('unhandledRejection', (error) => {
    console.error('Unhandled promise rejection:', error);
});

// Login to Discord
client.login(BOT_TOKEN)
    .then(() => {
        console.log('🔗 Connecting to Discord...');
    })
    .catch((error) => {
        console.error('❌ Login failed:', error);
        process.exit(1);
    });
JS;
    
    file_put_contents($bot_dir . '/index.js', $index_js);
    
    // Create .env file
    $env_content = "DISCORD_TOKEN={$bot_token}\n";
    file_put_contents($bot_dir . '/.env', $env_content);
    
    // Create README.md
    $readme = <<<MD
# Discord Bot

This is a Discord bot created using Discord.js v14.

## Installation

1. Make sure you have Node.js installed (v16.9.0 or higher)
2. Install dependencies:
   \`\`\`bash
   npm install
   \`\`\`
3. Start the bot:
   \`\`\`bash
   npm start
   \`\`\`

## Features

- Ready event logging
- Ping command (!ping)
- Hello command (!hello)
- Help command (!help)

## Configuration

The bot token is configured in the Bot Manager system. You can update it in the settings tab.
MD;
    
    file_put_contents($bot_dir . '/README.md', $readme);
}

function installDiscordPy($bot_dir, $bot_token) {
    // Create requirements.txt
    $requirements = "discord.py>=2.3.0\npython-dotenv>=1.0.0\n";
    file_put_contents($bot_dir . '/requirements.txt', $requirements);
    
    // Create main.py
    $main_py = <<<PY
import discord
from discord.ext import commands
import os
import asyncio

intents = discord.Intents.default()
intents.message_content = True
intents.members = True

# Bot token from configuration
BOT_TOKEN = '{$bot_token}'

bot = commands.Bot(command_prefix='!', intents=intents)

@bot.event
async def on_ready():
    print(f'✅ Logged in as {bot.user.name}')
    print(f'👥 Serving {len(bot.guilds)} servers')
    try:
        synced = await bot.tree.sync()
        print(f"✅ Synced {len(synced)} command(s)")
    except Exception as e:
        print(f"❌ Failed to sync commands: {e}")

@bot.event
async def on_message(message):
    # Ignore messages from bots
    if message.author.bot:
        return
    
    # Basic ping command
    if message.content.lower() == '!ping':
        await message.reply('🏓 Pong!')
    
    # Hello command
    elif message.content.lower() == '!hello':
        await message.reply(f'Hello {message.author.name}! 👋')
    
    # Help command
    elif message.content.lower() == '!help':
        embed = discord.Embed(
            title="🤖 Bot Commands",
            description="Here are the available commands:",
            color=discord.Color.blue()
        )
        embed.add_field(name="!ping", value="Check if bot is alive", inline=True)
        embed.add_field(name="!hello", value="Say hello to the bot", inline=True)
        embed.add_field(name="!help", value="Show this help message", inline=True)
        embed.set_footer(text="Bot Manager System")
        
        await message.channel.send(embed=embed)
    
    # Process commands
    await bot.process_commands(message)

@bot.tree.command(name="ping", description="Check bot latency")
async def ping(interaction: discord.Interaction):
    await interaction.response.send_message(f'🏓 Pong! {round(bot.latency * 1000)}ms')

@bot.tree.command(name="hello", description="Say hello to the bot")
async def hello(interaction: discord.Interaction):
    await interaction.response.send_message(f'Hello {interaction.user.name}! 👋')

# Run the bot
if __name__ == "__main__":
    try:
        print('🔗 Connecting to Discord...')
        bot.run(BOT_TOKEN)
    except Exception as e:
        print(f'❌ Failed to start bot: {e}')
PY;
    
    file_put_contents($bot_dir . '/main.py', $main_py);
    
    // Create .env file
    $env_content = "DISCORD_TOKEN={$bot_token}\n";
    file_put_contents($bot_dir . '/.env', $env_content);
    
    // Create README.md
    $readme = <<<MD
# Discord Bot (Python)

This is a Discord bot created using discord.py.

## Installation

1. Make sure you have Python 3.8 or higher installed
2. Install dependencies:
   \`\`\`bash
   pip install -r requirements.txt
   \`\`\`
3. Start the bot:
   \`\`\`bash
   python main.py
   \`\`\`

## Features

- Slash commands support
- Ping command
- Hello command
- Help command

## Configuration

The bot token is configured in the Bot Manager system.
MD;
    
    file_put_contents($bot_dir . '/README.md', $readme);
}

function installDiscordPHP($bot_dir, $bot_token) {
    // Create composer.json
    $composer_json = json_encode([
        'name' => 'discord/php-bot',
        'description' => 'Discord PHP bot',
        'type' => 'project',
        'require' => [
            'discord-php/discord-php' => '^7.1.0'
        ],
        'autoload' => [
            'psr-4' => [
                'App\\' => 'src/'
            ]
        ],
        'minimum-stability' => 'stable'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    file_put_contents($bot_dir . '/composer.json', $composer_json);
    
    // Create config.php
    $config_php = <<<PHP
<?php
// Bot Configuration
return [
    'token' => '{$bot_token}',
    'prefix' => '!',
    'owner_id' => null,
    'intents' => [
        'guilds',
        'guild_messages',
        'message_content',
        'guild_members'
    ]
];
PHP;
    
    file_put_contents($bot_dir . '/config.php', $config_php);
    
    // Create index.php
    $index_php = <<<PHP
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Discord\\Discord;
use Discord\\Parts\\Channel\\Message;
use Discord\\WebSockets\\Event;
use Discord\\WebSockets\\Intents;

// Load configuration
\$config = require_once __DIR__ . '/config.php';

// Create Discord instance
\$discord = new Discord([
    'token' => \$config['token'],
    'intents' => Intents::getDefaultIntents() | Intents::MESSAGE_CONTENT,
]);

\$discord->on('ready', function (Discord \$discord) {
    echo "✅ Bot is ready! Logged in as " . \$discord->user->username . "\\n";
    echo "👥 Serving " . count(\$discord->guilds) . " servers\\n";
});

\$discord->on(Event::MESSAGE_CREATE, function (Message \$message, Discord \$discord) {
    // Ignore messages from bots
    if (\$message->author->bot) {
        return;
    }

    \$content = strtolower(\$message->content);
    \$prefix = '!';

    // Ping command
    if (\$content === '!ping') {
        \$message->reply('🏓 Pong!');
    }

    // Hello command
    if (\$content === '!hello') {
        \$message->reply("Hello {\$message->author->username}! 👋");
    }

    // Help command
    if (\$content === '!help') {
        \$embed = new \\Discord\\Parts\\Embed\\Embed(\$discord);
        \$embed->setTitle('🤖 Bot Commands');
        \$embed->setColor(0x0099ff);
        \$embed->addField([
            'name' => '!ping',
            'value' => 'Check if bot is alive',
            'inline' => true
        ]);
        \$embed->addField([
            'name' => '!hello',
            'value' => 'Say hello to the bot',
            'inline' => true
        ]);
        \$embed->addField([
            'name' => '!help',
            'value' => 'Show this help message',
            'inline' => true
        ]);
        \$embed->setFooter('Bot Manager System');
        \$embed->setTimestamp();

        \$message->channel->sendEmbed(\$embed);
    }
});

// Error handling
\$discord->on('error', function (\$error, \$discord) {
    echo "❌ Error: " . \$error . "\\n";
});

echo "🔗 Connecting to Discord...\\n";
\$discord->run();
PHP;
    
    file_put_contents($bot_dir . '/index.php', $index_php);
    
    // Create README.md
    $readme = <<<MD
# Discord Bot (PHP)

This is a Discord bot created using DiscordPHP.

## Installation

1. Make sure you have PHP 7.4 or higher and Composer installed
2. Install dependencies:
   \`\`\`bash
   composer install
   \`\`\`
3. Start the bot:
   \`\`\`bash
   php index.php
   \`\`\`

## Features

- Event-based architecture
- Ping command
- Hello command
- Help command with embed

## Configuration

Update the bot token in config.php if needed.
MD;
    
    file_put_contents($bot_dir . '/README.md', $readme);
}

// Get console logs
$console_logs = '';
$log_file = __DIR__ . '/bots/logs/bot_' . $bot_id . '.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    if ($log_content !== false) {
        $console_logs = htmlspecialchars($log_content);
    } else {
        $console_logs = "Unable to read log file.";
    }
} else {
    $console_logs = "No logs available for this bot. Logs will appear here when you run the bot.";
}

// Handle Stop action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'stop') {
    try {
        // Kill the process
        if (!empty($bot['process_id'])) {
            exec("taskkill /F /PID " . $bot['process_id'] . " 2>nul");
        }
        
        // Update database
        $update_query = "UPDATE bots SET status = 'stopped', process_id = NULL, last_seen = NOW() WHERE id = :bot_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':bot_id', $bot_id);
        $update_stmt->execute();
        
        // Refresh page
        header('Location: bot_manage.php?bot_id=' . $bot_id . '&success=Bot+stopped+successfully');
        exit();
        
    } catch (Exception $e) {
        header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=' . urlencode($e->getMessage()));
        exit();
    }
}

// Handle file save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_file'])) {
    try {
        $file_path = __DIR__ . '/bots/bot_' . $bot_id . '/' . basename($_POST['filename']);
        $content = $_POST['file_content'];
        
        if (file_put_contents($file_path, $content)) {
            header('Location: bot_manage.php?bot_id=' . $bot_id . '&success=File+saved+successfully&action=edit&file=' . urlencode($_POST['filename']));
        } else {
            header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=Failed+to+save+file&action=edit&file=' . urlencode($_POST['filename']));
        }
        exit();
    } catch (Exception $e) {
        header('Location: bot_manage.php?bot_id=' . $bot_id . '&error=' . urlencode($e->getMessage()));
        exit();
    }
}

// Handle messages
if (isset($_GET['success'])) {
    $action_message = '<div class="alert success">✅ ' . htmlspecialchars($_GET['success']) . '</div>';
} elseif (isset($_GET['error'])) {
    $action_message = '<div class="alert error">❌ ' . htmlspecialchars($_GET['error']) . '</div>';
} else {
    $action_message = '';
}

// Check if bot needs installation
$bot_dir = __DIR__ . '/bots/bot_' . $bot_id;
$needs_installation = false;
if (!file_exists($bot_dir) || count(scandir($bot_dir)) <= 2) {
    $needs_installation = true;
}

// Helper function for file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

// Get file content for editing
$file_content = '';
$editing_file = '';
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['file'])) {
    $editing_file = basename($_GET['file']);
    $file_path = $bot_dir . '/' . $editing_file;
    if (file_exists($file_path)) {
        $file_content = file_get_contents($file_path);
    }
}

// Determine active tab
$active_tab = 'console';
if (isset($_GET['tab'])) {
    $active_tab = $_GET['tab'];
} elseif ($needs_installation) {
    $active_tab = 'install';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bot - <?php echo htmlspecialchars($bot['bot_name']); ?></title>
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
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eaeaea;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #5a6268;
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
            font-size: 14px;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .bot-info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .bot-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .bot-name-large {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .bot-type-icon {
            font-size: 28px;
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

        .bot-status-large {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-online {
            background: #d4edda;
            color: #155724;
        }

        .status-stopped, .status-offline {
            background: #f8d7da;
            color: #721c24;
        }

        .bot-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .bot-info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .bot-info-item label {
            display: block;
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .bot-info-item p {
            color: #333;
            font-size: 16px;
            word-break: break-all;
        }

        .bot-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            flex-wrap: wrap;
        }

        .bot-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 15px;
            min-width: 120px;
        }

        .btn-run {
            background: #28a745;
            color: white;
        }

        .btn-run:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-stop {
            background: #dc3545;
            color: white;
        }

        .btn-stop:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-refresh {
            background: #17a2b8;
            color: white;
        }

        .btn-refresh:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        /* Management Tabs */
        .management-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 25px;
            background: none;
            border: none;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
            font-size: 15px;
        }

        .tab-btn:hover {
            background: #f0f0f0;
            color: #333;
        }

        .tab-btn.active {
            background: #667eea;
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .tab-content.active {
            display: block;
        }

        /* Console Tab */
        .console-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .console-title {
            font-size: 20px;
            color: #333;
            font-weight: 600;
        }

        .console-display {
            background: #1e1e1e;
            color: #f8f8f8;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
        }

        .console-controls {
            display: flex;
            gap: 10px;
        }

        /* Installation Tab */
        .installation-wizard {
            max-width: 800px;
            margin: 0 auto;
        }

        .installation-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .installation-steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 0;
            right: 0;
            height: 2px;
            background: #eaeaea;
            z-index: 1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #eaeaea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
            border: 4px solid white;
        }

        .step.active .step-number {
            background: #667eea;
            color: white;
        }

        .step.completed .step-number {
            background: #28a745;
            color: white;
        }

        .step-label {
            font-size: 14px;
            color: #666;
            text-align: center;
        }

        .step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }

        .project-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .project-option {
            border: 2px solid #eaeaea;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .project-option:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .project-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        }

        .project-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .project-option:nth-child(1) .project-icon {
            color: #68a063;
        }

        .project-option:nth-child(2) .project-icon {
            color: #3572A5;
        }

        .project-option:nth-child(3) .project-icon {
            color: #777BB4;
        }

        .project-option:nth-child(4) .project-icon {
            color: #f0ad4e;
        }

        .project-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .project-desc {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .installation-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            color: #555;
            font-weight: 500;
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-secondary:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }

        /* File Manager Tab */
        .file-browser {
            display: flex;
            gap: 30px;
        }

        .file-list-container {
            width: 300px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            height: 500px;
            overflow-y: auto;
        }

        .file-list-container h4 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .file-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s ease;
        }

        .file-item:hover {
            background: #e9ecef;
        }

        .file-item a {
            color: #333;
            text-decoration: none;
            flex: 1;
        }

        .file-item a:hover {
            text-decoration: underline;
        }

        .btn-edit-small {
            background: #667eea;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            transition: background 0.3s ease;
        }

        .btn-edit-small:hover {
            background: #5a6fd8;
            text-decoration: none;
        }

        .btn-delete-small {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            transition: background 0.3s ease;
            margin-left: 5px;
        }

        .btn-delete-small:hover {
            background: #c82333;
            text-decoration: none;
        }

        .file-editor {
            flex: 1;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }

        .file-editor h4 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        }

        .code-editor {
            width: 100%;
            height: 400px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f8f9fa;
            resize: vertical;
        }

        .editor-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .radio-hidden {
            display: none;
        }

        @media (max-width: 992px) {
            .file-browser {
                flex-direction: column;
            }
            
            .file-list-container {
                width: 100%;
                height: 300px;
            }
            
            .project-options {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .header-actions {
                display: flex;
                gap: 10px;
                width: 100%;
            }
            
            .back-btn, .logout-btn {
                flex: 1;
                text-align: center;
            }
            
            .management-tabs {
                flex-wrap: wrap;
            }
            
            .bot-actions {
                flex-wrap: wrap;
            }
            
            .bot-action-btn {
                flex: 1;
                min-width: auto;
            }
            
            .installation-steps {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .installation-steps::before {
                display: none;
            }
            
            .step {
                flex-direction: row;
                gap: 15px;
                width: 100%;
            }
            
            .step-number {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .project-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <?php if($bot['bot_type'] == 'nodejs'): ?>
                    <i class="fab fa-node-js nodejs-icon bot-type-icon"></i>
                <?php elseif($bot['bot_type'] == 'python'): ?>
                    <i class="fab fa-python python-icon bot-type-icon"></i>
                <?php else: ?>
                    <i class="fab fa-php php-icon bot-type-icon"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($bot['bot_name']); ?>
            </h1>
            <div class="header-actions">
                <a href="user_panel.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <form action="logout.php" method="POST" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>

        <?php echo $action_message; ?>

        <?php if($needs_installation): ?>
            <div class="alert info">
                <i class="fas fa-info-circle"></i> This bot needs to be configured. Please complete the installation wizard below.
            </div>
        <?php endif; ?>

        <div class="bot-info-card">
            <div class="bot-info-header">
                <div class="bot-name-large">
                    <?php if($bot['bot_type'] == 'nodejs'): ?>
                        <i class="fab fa-node-js nodejs-icon bot-type-icon"></i>
                    <?php elseif($bot['bot_type'] == 'python'): ?>
                        <i class="fab fa-python python-icon bot-type-icon"></i>
                    <?php else: ?>
                        <i class="fab fa-php php-icon bot-type-icon"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($bot['bot_name']); ?>
                </div>
                <div class="bot-status-large status-<?php echo $bot['status']; ?>">
                    <?php echo strtoupper($bot['status']); ?>
                </div>
            </div>
            
            <div class="bot-info-grid">
                <div class="bot-info-item">
                    <label>Bot Token</label>
                    <p><?php echo htmlspecialchars(substr($bot['bot_token'], 0, 30) . '...'); ?></p>
                </div>
                <div class="bot-info-item">
                    <label>Bot Type</label>
                    <p><?php echo strtoupper($bot['bot_type']); ?></p>
                </div>
                <div class="bot-info-item">
                    <label>Entry File</label>
                    <p><?php echo htmlspecialchars($bot['entry_file']); ?></p>
                </div>
                <div class="bot-info-item">
                    <label>Last Seen</label>
                    <p><?php echo $bot['last_seen'] ? date('Y-m-d H:i:s', strtotime($bot['last_seen'])) : 'Never'; ?></p>
                </div>
                <div class="bot-info-item">
                    <label>Created At</label>
                    <p><?php echo date('Y-m-d H:i:s', strtotime($bot['created_at'])); ?></p>
                </div>
                <?php if(!empty($bot['process_id'])): ?>
                <div class="bot-info-item">
                    <label>Process ID</label>
                    <p><?php echo $bot['process_id']; ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if(!$needs_installation): ?>
            <div class="bot-actions">
                <!-- Run Button - goes to run_bot.php -->
                <a href="run_bot.php?bot_id=<?php echo $bot_id; ?>" class="bot-action-btn btn-run" onclick="showLoading(this)">
                    <i class="fas fa-play"></i> Run Bot
                </a>
                
                <!-- Stop Button - posts to this page -->
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="stop">
                    <button type="submit" class="bot-action-btn btn-stop" onclick="return confirm('Are you sure you want to stop this bot?')">
                        <i class="fas fa-stop"></i> Stop Bot
                    </button>
                </form>
                
                <button type="button" class="bot-action-btn btn-refresh" onclick="refreshConsole()">
                    <i class="fas fa-sync-alt"></i> Refresh Logs
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Management Tabs -->
        <!-- Management Tabs - FIXED VERSION -->
<div class="management-tabs" style="display: flex; gap: 10px; padding: 20px; background: #f8f9fa; border-radius: 10px; margin-bottom: 20px;">
    <?php if($needs_installation): ?>
    <button class="tab-btn <?php echo $active_tab == 'install' ? 'active' : ''; ?>" 
            onclick="showTab('install')"
            style="padding: 12px 25px; border: none; border-radius: 5px; background: <?php echo $active_tab == 'install' ? '#667eea' : '#eaeaea'; ?>; color: <?php echo $active_tab == 'install' ? 'white' : '#666'; ?>; cursor: pointer;">
        <i class="fas fa-download"></i> Installation
    </button>
    <?php endif; ?>
    
    <button class="tab-btn <?php echo $active_tab == 'console' ? 'active' : ''; ?>" 
            onclick="showTab('console')"
            style="padding: 12px 25px; border: none; border-radius: 5px; background: <?php echo $active_tab == 'console' ? '#667eea' : '#eaeaea'; ?>; color: <?php echo $active_tab == 'console' ? 'white' : '#666'; ?>; cursor: pointer;">
        <i class="fas fa-terminal"></i> Console Logs
    </button>
    
    <button class="tab-btn <?php echo $active_tab == 'files' ? 'active' : ''; ?>" 
            onclick="showTab('files')"
            style="padding: 12px 25px; border: none; border-radius: 5px; background: <?php echo $active_tab == 'files' ? '#667eea' : '#eaeaea'; ?>; color: <?php echo $active_tab == 'files' ? 'white' : '#666'; ?>; cursor: pointer;">
        <i class="fas fa-folder"></i> File Manager
    </button>
    
    <button class="tab-btn <?php echo $active_tab == 'settings' ? 'active' : ''; ?>" 
            onclick="showTab('settings')"
            style="padding: 12px 25px; border: none; border-radius: 5px; background: <?php echo $active_tab == 'settings' ? '#667eea' : '#eaeaea'; ?>; color: <?php echo $active_tab == 'settings' ? 'white' : '#666'; ?>; cursor: pointer;">
        <i class="fas fa-cog"></i> Settings
    </button>
</div>

        <!-- Installation Tab (Only shows when needed) -->
        <?php if($needs_installation): ?>
        <div id="install-tab" class="tab-content <?php echo $active_tab == 'install' ? 'active' : ''; ?>">
            <div class="installation-wizard">
                <h3 style="margin-bottom: 30px; color: #333;">Install Bot Template</h3>
                
                <div class="installation-steps">
                    <div class="step active">
                        <div class="step-number">1</div>
                        <div class="step-label">Choose Template</div>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-label">Configure</div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-label">Install</div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="install_bot" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Select Project Template</label>
                        <div class="project-options">
                            <label class="project-option" onclick="selectProject('discord.js')">
                                <input type="radio" name="project_type" value="discord.js" class="radio-hidden">
                                <div class="project-icon">
                                    <i class="fab fa-node-js"></i>
                                </div>
                                <div class="project-title">Discord.js v14</div>
                                <div class="project-desc">
                                    JavaScript Discord bot using Discord.js library. Includes basic commands and event handlers.
                                </div>
                            </label>
                            
                            <label class="project-option" onclick="selectProject('discord.py')">
                                <input type="radio" name="project_type" value="discord.py" class="radio-hidden">
                                <div class="project-icon">
                                    <i class="fab fa-python"></i>
                                </div>
                                <div class="project-title">Discord.py</div>
                                <div class="project-desc">
                                    Python Discord bot with slash commands support. Modern discord.py library.
                                </div>
                            </label>
                            
                            <label class="project-option" onclick="selectProject('discord.php')">
                                <input type="radio" name="project_type" value="discord.php" class="radio-hidden">
                                <div class="project-icon">
                                    <i class="fab fa-php"></i>
                                </div>
                                <div class="project-title">DiscordPHP</div>
                                <div class="project-desc">
                                    PHP Discord bot using DiscordPHP library. Event-based architecture.
                                </div>
                            </label>
                            
                            <label class="project-option" onclick="selectProject('custom')">
                                <input type="radio" name="project_type" value="custom" class="radio-hidden">
                                <div class="project-icon">
                                    <i class="fas fa-code"></i>
                                </div>
                                <div class="project-title">Custom Project</div>
                                <div class="project-desc">
                                    Upload your own bot files. You'll need to manage dependencies manually.
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="installation-form">
                        <div class="form-group">
                            <label class="form-label">Discord Bot Token</label>
                            <input type="text" class="form-control" name="bot_token" 
                                   placeholder="MTQ1NDg3OTiOMJ0kzOTA4Nsg5Mg.G-x..." required>
                            <small style="color: #666; margin-top: 5px; display: block;">
                                Get your bot token from: <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a>
                            </small>
                        </div>
                        
                        <div id="custom-fields" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Entry File</label>
                                <input type="text" class="form-control" name="entry_file" 
                                       placeholder="index.js or main.py or bot.php">
                                <small style="color: #666; margin-top: 5px; display: block;">
                                    The main file that will be executed when running the bot
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-primary" style="width: 100%;">
                                <i class="fas fa-download"></i> Install Bot Template
                            </button>
                            <small style="color: #666; margin-top: 10px; display: block; text-align: center;">
                                This will automatically install all necessary files and configure your bot.
                            </small>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Console Tab -->
        <div id="console-tab" class="tab-content <?php echo $active_tab == 'console' ? 'active' : ''; ?>">
            <div class="console-header">
                <div class="console-title">Console Logs</div>
                <div class="console-controls">
                    <button class="bot-action-btn btn-refresh" onclick="refreshConsole()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="console-display" id="console-display">
                <?php echo $console_logs; ?>
            </div>
        </div>

        <!-- File Manager Tab -->
        <div id="files-tab" class="tab-content <?php echo $active_tab == 'files' ? 'active' : ''; ?>">
            <div class="file-manager-container">
                <?php if($needs_installation): ?>
                    <div style="text-align: center; padding: 50px; color: #666;">
                        <i class="fas fa-download" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3>No Files Found</h3>
                        <p>Please complete the installation wizard first to generate bot files.</p>
                        <a href="?bot_id=<?php echo $bot_id; ?>&tab=install" class="btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-arrow-right"></i> Go to Installation
                        </a>
                    </div>
                <?php elseif($editing_file): ?>
                    <!-- File Editor -->
                    <div class="file-editor">
                        <h4>Editing: <?php echo htmlspecialchars($editing_file); ?></h4>
                        <form method="POST" action="">
                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($editing_file); ?>">
                            <textarea name="file_content" class="code-editor" id="fileContent"><?php echo htmlspecialchars($file_content); ?></textarea>
                            <div class="editor-actions">
                                <button type="submit" name="save_file" class="btn-primary">
                                    <i class="fas fa-save"></i> Save File
                                </button>
                                <a href="?bot_id=<?php echo $bot_id; ?>&tab=files" class="btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- File List -->
                    <div class="file-browser">
                        <div class="file-list-container">
                            <h4>Bot Files (<?php echo $bot_dir; ?>)</h4>
                            <?php
                            if (file_exists($bot_dir)) {
                                $files = scandir($bot_dir);
                                foreach ($files as $file) {
                                    if ($file == '.' || $file == '..') continue;
                                    
                                    $file_path = $bot_dir . '/' . $file;
                                    $is_dir = is_dir($file_path);
                                    $file_size = $is_dir ? '' : ' (' . formatFileSize(filesize($file_path)) . ')';
                                    
                                    echo '<div class="file-item">';
                                    echo '<i class="fas ' . ($is_dir ? 'fa-folder' : 'fa-file-code') . '"></i>';
                                    echo '<a href="?bot_id=' . $bot_id . '&tab=files&action=edit&file=' . urlencode($file) . '">';
                                    echo '<span>' . htmlspecialchars($file) . $file_size . '</span>';
                                    echo '</a>';
                                    
                                    // Edit button for files (not folders)
                                    if (!$is_dir) {
                                        echo '<a href="?bot_id=' . $bot_id . '&tab=files&action=edit&file=' . urlencode($file) . '" class="btn-edit-small">Edit</a>';
                                    }
                                    
                                    // Delete button (don't show for main files)
                                    $protected_files = ['index.js', 'main.py', 'index.php', 'package.json', 'requirements.txt', 'composer.json'];
                                    if (!$is_dir && !in_array($file, $protected_files)) {
                                        echo '<a href="delete_file.php?bot_id=' . $bot_id . '&file=' . urlencode($file) . '" 
                                              class="btn-delete-small" 
                                              onclick="return confirm(\'Are you sure you want to delete ' . htmlspecialchars($file) . '?\')">
                                              <i class="fas fa-trash"></i>
                                              </a>';
                                    }
                                    echo '</div>';
                                }
                            } else {
                                echo '<p>Bot directory not found.</p>';
                            }
                            ?>
                        </div>
                        
                        <div class="file-editor" style="background: transparent; border: 2px dashed #ddd; display: flex; align-items: center; justify-content: center;">
                            <div style="text-align: center; color: #666;">
                                <i class="fas fa-file-edit" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                <h4>Select a file to edit</h4>
                                <p>Click "Edit" on any file to edit its contents</p>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Form -->
                    <div class="upload-form" style="margin-top: 20px;">
                        <h5>Create New File</h5>
                        <form method="POST" action="create_file.php">
                            <input type="hidden" name="bot_id" value="<?php echo $bot_id; ?>">
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; color: #666;">File name:</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" name="filename" placeholder="filename.js" required 
                                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-plus"></i> Create
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content <?php echo $active_tab == 'settings' ? 'active' : ''; ?>">
            <div class="settings-form">
                <h3 style="margin-bottom: 20px; color: #333;">Bot Settings</h3>
                <form method="POST" action="update_bot.php">
                    <input type="hidden" name="bot_id" value="<?php echo $bot_id; ?>">
                    
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
                            Get your bot token from: <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Entry File</label>
                        <input type="text" class="form-control" name="entry_file" 
                               value="<?php echo htmlspecialchars($bot['entry_file']); ?>" required>
                    </div>
                    
                    <?php if($needs_installation): ?>
                    <div class="form-group">
                        <div class="alert info">
                            <i class="fas fa-exclamation-triangle"></i> Complete installation first before you can run the bot.
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn-primary" name="update_bot">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Update URL without reloading
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            history.pushState({}, '', url);
        }

        // Show loading on Run button click
        function showLoading(button) {
            const originalHTML = button.innerHTML;
            button.innerHTML = '<span class="spinner"></span> Starting...';
            button.style.pointerEvents = 'none';
            
            // Allow navigation to proceed
            return true;
        }
        
        // Refresh console logs
        function refreshConsole() {
            const button = event.target.closest('button');
            if (button) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<span class="spinner"></span> Refreshing...';
                button.disabled = true;
            }
            
            // Simple page reload
            location.reload();
        }

        // Project selection
        function selectProject(projectType) {
            // Remove selected class from all options
            document.querySelectorAll('.project-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.target.closest('.project-option').classList.add('selected');
            
            // Check the radio button
            const radio = event.target.closest('.project-option').querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Show/hide custom fields
            const customFields = document.getElementById('custom-fields');
            if (projectType === 'custom') {
                customFields.style.display = 'block';
            } else {
                customFields.style.display = 'none';
            }
        }

        // Auto-scroll console to bottom
        window.onload = function() {
            const consoleDisplay = document.getElementById('console-display');
            if (consoleDisplay) {
                consoleDisplay.scrollTop = consoleDisplay.scrollHeight;
            }
            
            // If editing a file, auto-focus the editor
            const fileEditor = document.getElementById('fileContent');
            if (fileEditor) {
                fileEditor.focus();
            }
            
            // Check for project type selection
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'install') {
                const projectType = document.querySelector('input[name="project_type"]:checked');
                if (projectType) {
                    selectProject(projectType.value);
                }
            }
        };

        // Auto-refresh console every 10 seconds if logs exist and console tab is active
        setInterval(() => {
            const consoleTab = document.getElementById('console-tab');
            if (consoleTab && consoleTab.classList.contains('active')) {
                const consoleDisplay = document.getElementById('console-display');
                if (consoleDisplay && consoleDisplay.textContent.trim() !== '' && 
                    consoleDisplay.textContent !== "No logs available for this bot. Logs will appear here when you run the bot.") {
                    refreshConsole();
                }
            }
        }, 10000);

        // Add loading indicator to Save Changes button
        document.querySelector('form[action="update_bot.php"]')?.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<span class="spinner"></span> Saving...';
                button.disabled = true;
                
                setTimeout(() => {
                    if (button.disabled) {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                }, 5000);
            }
        });

        // Add loading indicator to Save File button
        document.querySelector('form[action=""]')?.addEventListener('submit', function(e) {
            if (this.querySelector('textarea[name="file_content"]')) {
                const button = this.querySelector('button[name="save_file"]');
                if (button) {
                    const originalText = button.innerHTML;
                    button.innerHTML = '<span class="spinner"></span> Saving...';
                    button.disabled = true;
                }
            }
        });

        // Add loading indicator to Install button
        document.querySelector('form[action=""]')?.addEventListener('submit', function(e) {
            if (this.querySelector('input[name="install_bot"]')) {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    const originalText = button.innerHTML;
                    button.innerHTML = '<span class="spinner"></span> Installing...';
                    button.disabled = true;
                }
            }
        });
        
    </script>
    
</body>
</html>