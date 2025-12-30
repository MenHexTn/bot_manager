🚀 BotManager Pro - Complete Discord Bot Hosting Platform
📌 One-Line Description
"Self-hosted Discord bot management panel with marketplace, local execution, and one-click deployment"

🎯 Project Description
BotManager Pro is a comprehensive, self-hosted web platform that allows Discord server owners and developers to create, manage, and deploy multiple Discord bots through an intuitive dashboard. Think "cPanel for Discord bots" - all running on your own server with full control and privacy.

🌟 Key Features:
🎨 User-Friendly Dashboard - Manage all your bots from a single interface

🛒 Bot Marketplace - Browse and install pre-built bot templates (Moderation, Music, Welcome, etc.)

⚡ Local Bot Execution - Run bots directly on your server (no external dependencies)

🚀 One-Click Deployment - Deploy bots to Replit/Glitch with built-in guides

📁 File Manager - Built-in code editor for customizing bot scripts

📊 Real-time Logs - Monitor bot activity with live console output

👥 Multi-User Support - Team management with role-based permissions

🔒 Complete Privacy - Your bots, your tokens, your server - no third parties

🎮 What Problems Does It Solve?
For Discord Server Owners:
❌ "I need 5 different bots but managing them is confusing"
✅ Solution: One dashboard to rule them all - manage moderation, music, welcome, and utility bots in one place.

For Bot Developers:
❌ "I waste time setting up Node.js and managing processes for each bot"
✅ Solution: Automated bot creation, dependency installation, and process management.

For Non-Technical Users:
❌ "I want a music bot but I don't know how to code"
✅ Solution: One-click install pre-built bots from the marketplace.

For Privacy-Conscious Users:
❌ "I don't trust third-party bot hosting with my server data"
✅ Solution: 100% self-hosted - your data never leaves your server.

# Copyright (c) 2025 HexMen. All rights reserved.

This software and its source code are proprietary and confidential.
Unauthorized copying, modification, distribution, or use of this software,
via any medium, is strictly prohibited without the express written
permission of HexMen.
#SQL
```
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 28, 2025 at 10:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bot_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `bots`
--

CREATE TABLE `bots` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bot_name` varchar(100) NOT NULL,
  `bot_token` varchar(255) NOT NULL,
  `bot_type` enum('nodejs','python','php') DEFAULT 'nodejs',
  `status` enum('online','offline','error','stopped') DEFAULT 'offline',
  `entry_file` varchar(255) DEFAULT 'index.js',
  `process_id` int(11) DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bots`
--

INSERT INTO `bots` (`id`, `user_id`, `bot_name`, `bot_token`, `bot_type`, `status`, `entry_file`, `process_id`, `last_seen`, `created_at`, `updated_at`) VALUES
(1, 2, 'test', 'MTI2OTYxODQzMTkxODA4MDA0MQ.GK8q5f.4slpsB886TA125c1wxLZX8VbSbTt3ouhtNVCM0', 'nodejs', 'stopped', 'index.js', NULL, '2025-12-28 19:32:05', '2025-12-28 16:44:43', '2025-12-28 19:32:05'),
(2, 2, 'test-project', 'MTQ1NDg3OTk0MDkzOTA4Nzg5Mg.G-xl8p.4sdbljXFFo7uBd-6usOXsmlBA0exxcKchmWnPQ', 'nodejs', 'stopped', 'index.js', NULL, '2025-12-28 19:37:58', '2025-12-28 16:54:26', '2025-12-28 19:37:58'),
(3, 3, 'test-project', 'MTQ1NDg3OTk0MDkzOTA4Nzg5Mg.G-xl8p.4sdbljXFFo7uBd-6usOXsmlBA0exxcKchmWnPQ', 'nodejs', 'offline', 'index.js', NULL, NULL, '2025-12-28 20:28:34', '2025-12-28 20:28:34');

-- --------------------------------------------------------

--
-- Table structure for table `bot_files`
--

CREATE TABLE `bot_files` (
  `id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `content` longtext DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `size` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bot_files`
--

INSERT INTO `bot_files` (`id`, `bot_id`, `filename`, `filepath`, `content`, `file_type`, `size`, `created_at`, `updated_at`) VALUES
(3, 1, 'index.js', '/index.js', 'const { Client, GatewayIntentBits } = require(\'discord.js\');\r\n\r\n// Get bot token from environment or config\r\nconst token = process.env.DISCORD_TOKEN || require(\'./config.json\').token;\r\n\r\n// Create a new client instance\r\nconst client = new Client({\r\n    intents: [\r\n        GatewayIntentBits.Guilds,\r\n        GatewayIntentBits.GuildMessages,\r\n        GatewayIntentBits.MessageContent,\r\n        GatewayIntentBits.GuildMembers,\r\n    ]\r\n});\r\n\r\n// When the client is ready, run this code (only once)\r\nclient.once(\'ready\', () => {\r\n    console.log(`✅ Logged in as ${client.user.tag}!`);\r\n    console.log(`🆔 Bot ID: ${client.user.id}`);\r\n    console.log(`👥 Serving ${client.guilds.cache.size} guilds`);\r\n    console.log(`💬 ${client.channels.cache.size} channels`);\r\n    console.log(`👤 ${client.users.cache.size} users`);\r\n    \r\n    // Set bot status\r\n    client.user.setPresence({\r\n        activities: [{\r\n            name: \'with BotManager\',\r\n            type: 0 // PLAYING\r\n        }],\r\n        status: \'online\'\r\n    });\r\n});\r\n\r\n// Log messages\r\nclient.on(\'messageCreate\', async message => {\r\n    // Don\'t respond to bots\r\n    if (message.author.bot) return;\r\n    \r\n    // Simple ping command\r\n    if (message.content === \'!ping\') {\r\n        await message.reply(\'Pong! 🏓\');\r\n    }\r\n    \r\n    // Help command\r\n    if (message.content === \'!help\') {\r\n        const helpEmbed = {\r\n            color: 0x0099ff,\r\n            title: \'🤖 Bot Help\',\r\n            description: \'Available commands:\',\r\n            fields: [\r\n                {\r\n                    name: \'!ping\',\r\n                    value: \'Check if bot is alive\',\r\n                    inline: true\r\n                },\r\n                {\r\n                    name: \'!status\',\r\n                    value: \'Check bot status\',\r\n                    inline: true\r\n                },\r\n                {\r\n                    name: \'!info\',\r\n                    value: \'Get bot information\',\r\n                    inline: true\r\n                }\r\n            ],\r\n            timestamp: new Date(),\r\n        };\r\n        \r\n        await message.reply({ embeds: [helpEmbed] });\r\n    }\r\n    \r\n    // Status command\r\n    if (message.content === \'!status\') {\r\n        const uptime = process.uptime();\r\n        const hours = Math.floor(uptime / 3600);\r\n        const minutes = Math.floor((uptime % 3600) / 60);\r\n        const seconds = Math.floor(uptime % 60);\r\n        \r\n        const statusEmbed = {\r\n            color: 0x00ff00,\r\n            title: \'📊 Bot Status\',\r\n            fields: [\r\n                {\r\n                    name: \'🟢 Status\',\r\n                    value: \'Online\',\r\n                    inline: true\r\n                },\r\n                {\r\n                    name: \'⏰ Uptime\',\r\n                    value: `${hours}h ${minutes}m ${seconds}s`,\r\n                    inline: true\r\n                },\r\n                {\r\n                    name: \'👥 Guilds\',\r\n                    value: client.guilds.cache.size.toString(),\r\n                    inline: true\r\n                },\r\n                {\r\n                    name: \'💬 Channels\',\r\n                    value: client.channels.cache.size.toString(),\r\n                    inline: true\r\n                },\r\n                {\r\n                    name: \'👤 Users\',\r\n                    value: client.users.cache.size.toString(),\r\n                    inline: true\r\n                },\r\n                {\r\n                    name: \'🏓 Ping\',\r\n                    value: `${client.ws.ping}ms`,\r\n                    inline: true\r\n                }\r\n            ],\r\n            timestamp: new Date(),\r\n        };\r\n        \r\n        await message.reply({ embeds: [statusEmbed] });\r\n    }\r\n});\r\n\r\n// Handle errors\r\nclient.on(\'error\', error => {\r\n    console.error(\'❌ Discord client error:\', error);\r\n});\r\n\r\nclient.on(\'warn\', info => {\r\n    console.warn(\'⚠️ Discord client warning:\', info);\r\n});\r\n\r\n// Login to Discord\r\nclient.login(token).catch(error => {\r\n    console.error(\'❌ Failed to login:\', error);\r\n    process.exit(1);\r\n});\r\n\r\n// Handle graceful shutdown\r\nprocess.on(\'SIGINT\', () => {\r\n    console.log(\'🛑 Shutting down bot...\');\r\n    client.destroy();\r\n    process.exit(0);\r\n});\r\n\r\n// Export for testing\r\nmodule.exports = { client };', 'js', 0, '2025-12-28 15:48:45', '2025-12-28 15:48:45'),
(6, 2, 'index.js', '/index.js', 'const { Client, GatewayIntentBits } = require(\'discord.js\');\r\n\r\nconsole.log(\'🚀 Starting Discord bot...\');\r\nconsole.log(\'📁 Directory: \' + process.cwd());\r\nconsole.log(\'🔑 Token: \' + token.substring(0, 10) + \'...\');\r\n\r\nconst client = new Client({\r\n    intents: [\r\n        GatewayIntentBits.Guilds,\r\n        GatewayIntentBits.GuildMessages,\r\n        GatewayIntentBits.MessageContent,\r\n    ]\r\n});\r\n\r\nclient.once(\'ready\', () => {\r\n    console.log(\'✅ Bot is online as \' + client.user.tag);\r\n    console.log(\'👥 Serving \' + client.guilds.cache.size + \' servers\');\r\n    \r\n    client.user.setPresence({\r\n        activities: [{ name: \'BotManager\', type: 0 }],\r\n        status: \'online\'\r\n    });\r\n});\r\n\r\nclient.on(\'messageCreate\', message => {\r\n    if (message.author.bot) return;\r\n    \r\n    if (message.content === \'!ping\') {\r\n        message.reply(\'🏓 Pong!\');\r\n    }\r\n    \r\n    if (message.content === \'!status\') {\r\n        const uptime = process.uptime();\r\n        const hours = Math.floor(uptime / 3600);\r\n        const minutes = Math.floor((uptime % 3600) / 60);\r\n        const seconds = Math.floor(uptime % 60);\r\n        \r\n        message.reply(\\`Bot Status:\\n🟢 Online\\n⏰ Uptime: ${hours}h ${minutes}m ${seconds}s\\n👥 Servers: ${client.guilds.cache.size}\\`);\r\n    }\r\n});\r\n\r\nclient.login(token).catch(error => {\r\n    console.error(\'❌ Login failed:\', error.message);\r\n    process.exit(1);\r\n});\r\n\r\n// Keep alive\r\nsetInterval(() => {\r\n    if (client.isReady()) {\r\n        console.log(\'💗 Bot heartbeat - \' + new Date().toLocaleTimeString());\r\n    }\r\n}, 30000);', 'js', 0, '2025-12-28 17:09:58', '2025-12-28 17:09:58');

-- --------------------------------------------------------

--
-- Table structure for table `bot_logs`
--

CREATE TABLE `bot_logs` (
  `id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `log_type` enum('info','error','warning','success') DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bot_logs`
--

INSERT INTO `bot_logs` (`id`, `bot_id`, `log_type`, `message`, `created_at`) VALUES
(1, 1, 'success', 'Bot started successfully. PID: $!', '2025-12-28 15:45:35'),
(2, 1, 'info', 'Bot stopped by user', '2025-12-28 15:46:27'),
(3, 1, 'success', 'Bot started successfully. PID: $!', '2025-12-28 15:46:42'),
(4, 1, 'info', 'Bot stopped by user', '2025-12-28 15:49:07'),
(5, 1, 'info', 'Bot stopped by user', '2025-12-28 16:33:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','user') DEFAULT 'user',
  `max_bots` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `user_type`, `max_bots`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@botmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 999, '2025-12-28 15:26:47', '2025-12-28 15:26:47'),
(2, 'kaydou', 'kaydou@gmail.com', '$2y$10$BfjRkoYPJt4ccehsBzLSl.UL.e76WsDqEIwnHEaR3aYnWbmV226YG', 'user', 2, '2025-12-28 15:31:15', '2025-12-28 16:52:24'),
(3, 'menhex', 'menhex@gmail.com', '$2y$10$lMyfYQ8SeCiS7jK7WGwYGe7VyjHizlS4UdRMBzE5zgcmpBu5DMpyq', 'user', 1, '2025-12-28 20:27:56', '2025-12-28 20:27:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bots`
--
ALTER TABLE `bots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bot_files`
--
ALTER TABLE `bot_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bot_file` (`bot_id`,`filepath`);

--
-- Indexes for table `bot_logs`
--
ALTER TABLE `bot_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bot_logs` (`bot_id`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bots`
--
ALTER TABLE `bots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bot_files`
--
ALTER TABLE `bot_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `bot_logs`
--
ALTER TABLE `bot_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bot_files`
--
ALTER TABLE `bot_files`
  ADD CONSTRAINT `bot_files_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bot_logs`
--
ALTER TABLE `bot_logs`
  ADD CONSTRAINT `bot_logs_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
```

