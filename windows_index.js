// Windows-compatible Discord bot
const { Client, GatewayIntentBits } = require('discord.js');
require('dotenv').config();

console.log('========================================');
console.log('🤖 Windows Discord Bot Starting...');
console.log('📅 ' + new Date().toLocaleString());
console.log('🖥️ Platform: ' + process.platform);
console.log('📁 Working directory: ' + process.cwd());
console.log('========================================');

const token = process.env.DISCORD_TOKEN;

if (!token) {
    console.error('❌ ERROR: DISCORD_TOKEN is not set in .env file!');
    console.error('Please add: DISCORD_TOKEN=your_bot_token_here');
    process.exit(1);
}

console.log('✅ Token found (first 10 chars): ' + token.substring(0, 10) + '...');

// Create Discord client
const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.GuildMembers,
    ]
});

// When bot is ready
client.once('ready', () => {
    console.log('========================================');
    console.log(`✅ SUCCESS: Logged in as ${client.user.tag}`);
    console.log(`🆔 Bot ID: ${client.user.id}`);
    console.log(`👥 Servers: ${client.guilds.cache.size}`);
    console.log(`👤 Total Users: ${client.users.cache.size}`);
    console.log('========================================');
    
    // Set bot status
    client.user.setPresence({
        activities: [{ 
            name: 'BotManager on Windows',
            type: 0 // PLAYING
        }],
        status: 'online'
    });
    
    console.log('✅ Bot presence set to online');
});

// Message handler
client.on('messageCreate', async (message) => {
    // Ignore bot messages
    if (message.author.bot) return;
    
    // Simple commands
    if (message.content === '!ping') {
        await message.reply(`🏓 Pong! Latency: ${client.ws.ping}ms`);
    }
    
    if (message.content === '!help') {
        const helpMessage = `
**🤖 Bot Commands:**
\`!ping\` - Check bot latency
\`!status\` - Bot status
\`!server\` - Server info
\`!hello\` - Say hello
        
**Bot Manager:** https://github.com/your-repo
        `;
        await message.reply(helpMessage);
    }
    
    if (message.content === '!status') {
        const uptime = process.uptime();
        const hours = Math.floor(uptime / 3600);
        const minutes = Math.floor((uptime % 3600) / 60);
        const seconds = Math.floor(uptime % 60);
        
        const statusMessage = `
**📊 Bot Status:**
🟢 **Status:** Online
⏰ **Uptime:** ${hours}h ${minutes}m ${seconds}s
👥 **Servers:** ${client.guilds.cache.size}
👤 **Users:** ${client.users.cache.size}
💬 **Channels:** ${client.channels.cache.size}
🏓 **Ping:** ${client.ws.ping}ms
🖥️ **Platform:** Windows
        `;
        await message.reply(statusMessage);
    }
});

// Error handling
client.on('error', (error) => {
    console.error('❌ Discord.js Error:', error);
});

client.on('warn', (warning) => {
    console.warn('⚠️ Discord.js Warning:', warning);
});

process.on('unhandledRejection', (error) => {
    console.error('❌ Unhandled Promise Rejection:', error);
});

// Login to Discord
console.log('🔑 Attempting to login to Discord...');
client.login(token).then(() => {
    console.log('✅ Login successful!');
}).catch((error) => {
    console.error('❌ Failed to login:', error.message);
    console.error('Possible causes:');
    console.error('1. Invalid bot token');
    console.error('2. Bot not added to server');
    console.error('3. Internet connection issues');
    console.error('4. Discord API downtime');
    process.exit(1);
});

// Keep-alive heartbeat
setInterval(() => {
    if (client.isReady()) {
        console.log('💗 Heartbeat - Bot is alive and connected');
    }
}, 60000); // Every minute

// Handle shutdown
process.on('SIGINT', () => {
    console.log('🛑 Received SIGINT - Shutting down gracefully...');
    if (client.isReady()) {
        client.destroy();
    }
    console.log('✅ Bot shutdown complete');
    process.exit(0);
});

// Windows-specific: Handle window close
if (process.platform === 'win32') {
    const rl = require('readline').createInterface({
        input: process.stdin,
        output: process.stdout
    });

    rl.on('SIGINT', () => {
        process.emit('SIGINT');
    });
}

console.log('✅ Bot initialization complete, waiting for Discord connection...');