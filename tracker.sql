-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 02, 2026 at 10:29 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `changelog`
--

CREATE TABLE `changelog` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `action` varchar(20) NOT NULL,
  `entity` varchar(20) NOT NULL,
  `entity_name` varchar(150) NOT NULL DEFAULT '',
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`user_id`, `game_id`, `created_at`) VALUES
(9, 9, '2026-11-02 09:27:58');

-- --------------------------------------------------------

--
-- Table structure for table `friendships`
--

CREATE TABLE `friendships` (
  `id` int(10) UNSIGNED NOT NULL,
  `requester_id` int(10) UNSIGNED NOT NULL,
  `addressee_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `image_back` varchar(500) DEFAULT NULL,
  `image_front` varchar(500) DEFAULT NULL,
  `image_size` varchar(50) NOT NULL DEFAULT 'cover',
  `image_position` varchar(50) NOT NULL DEFAULT 'center',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`id`, `name`, `genre`, `image_back`, `image_front`, `image_size`, `image_position`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Overwatch', 'Team based shooter', 'https://blz-contentstack-images.akamaized.net/v3/assets/blta8f9a8e092360c6c/bltdf9dd58b1d2893d5/6ac5243232e6024d7dc0e46c/blizz_ow_nologo.png?format=webply&quality=80&auto=webp', 'https://blz-contentstack-images.akamaized.net/v3/assets/blta8f9a8e092360c6c/blt356e8885177e1b70/69839b1b9fc491040b9a15f9/logo_ow_enUS.png?format=webply&quality=80&auto=webp', 'cover', 'center', 'Team-based hero shooter where two teams fight over objectives. Pick a hero from the tank, damage or support roles and combine abilities with your team to win each match.', '2026-10-27 07:52:04', '2026-10-28 07:17:57'),
(2, 'Marvel Rivals', 'Third person shooter', 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/2767030/library_600x900_2x.jpg', NULL, 'cover', 'center', 'Fast 6v6 hero shooter featuring Marvel characters. Each hero has unique abilities and team-up powers, and the maps can be partly destroyed during the fight.', '2026-10-27 07:52:04', '2026-10-28 07:20:57'),
(3, 'Apex Legends', 'First person shooter', 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/1172470/library_600x900.jpg', NULL, '120%', 'center', 'Free-to-play battle royale hero shooter from Respawn Entertainment. Squads drop onto the map, pick a Legend with unique abilities, loot weapons and fight to be the last squad standing.', '2026-10-27 08:35:53', '2026-10-28 07:20:48'),
(4, 'Tom Clancy\'s Rainbow Six Siege', 'First person shooter', 'https://cdn.akamai.steamstatic.com/steam/apps/359550/library_600x900.jpg', NULL, 'cover', 'center', 'aksjclpsadmc[ozjv]oasc', '2026-10-27 10:38:19', '2026-10-27 12:47:26'),
(6, 'Call of Duty Warzone', 'First person shooter', 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/1962663/library_600x900_2x.jpg', NULL, 'cover', 'center', NULL, '2026-10-27 13:01:00', '2026-10-28 07:13:40'),
(7, 'Deadlock', 'Third person shooter', 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/1422450/logo.png', NULL, 'contain', 'center', NULL, '2026-10-28 07:23:15', '2026-10-28 07:25:54'),
(8, 'Team Fortress 2', 'First person shooter', 'https://shared.cloudflare.steamstatic.com/store_item_assets/steam/apps/440/library_600x900_2x.jpg', NULL, '100%', 'center', NULL, '2026-10-28 07:24:06', '2026-10-28 07:26:10'),
(9, 'Valorant', 'First person shooter', 'https://www.riotgames.com/darkroom/900/82a04f6bf37d1258c3242713d926c50b:a9101749edc9242665d310a120d3f710/v26-a6-website-productcardvisual-840x1413.jpg', NULL, 'cover', 'center', NULL, '2026-10-28 07:24:17', '2026-10-28 07:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `game_images`
--

CREATE TABLE `game_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED NOT NULL,
  `image` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_media`
--

CREATE TABLE `game_media` (
  `id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('screenshot','clip') NOT NULL,
  `file` varchar(255) DEFAULT NULL,
  `video_id` varchar(20) DEFAULT NULL,
  `caption` varchar(150) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_tags`
--

CREATE TABLE `game_tags` (
  `game_id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `game_tags`
--

INSERT INTO `game_tags` (`game_id`, `tag_id`) VALUES
(1, 3),
(2, 2),
(3, 1),
(4, 1),
(6, 1),
(7, 2),
(8, 1),
(9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `media_comments`
--

CREATE TABLE `media_comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `media_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media_likes`
--

CREATE TABLE `media_likes` (
  `media_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `body` varchar(300) NOT NULL DEFAULT '',
  `image` varchar(500) DEFAULT NULL,
  `game_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `title`, `body`, `image`, `created_at`, `updated_at`) VALUES
(1, 'Apex Legends will be great again', 'EPIC NEWS!!!!\r\nNext season lifeline is getting shield revive again', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQCdI299kjsJd3wXRHLt15-KsuSQzI8RLX5ODiHEYVaJA&s=10', '2026-11-02 09:21:41', '2026-11-02 09:21:41'),
(2, 'New deadlock update now has halloween', 'yup', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTxrMkjc1jDduAhdmMC0tT2H__ihReHSmx_xjfSjfIQyQ&s=10', '2026-11-02 09:22:17', '2026-11-02 09:22:17');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `from_user_id` int(10) UNSIGNED NOT NULL,
  `media_id` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`) VALUES
(3, 4, 'e02d5044f4db474d5c772d521f7af4726bbc17c60cfb033d9a5f18bd6e5844c9', '2026-10-28 10:27:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL CHECK (`rating` between 1 and 5),
  `comment` varchar(1000) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`user_id`, `game_id`, `rating`, `comment`, `created_at`, `updated_at`) VALUES
(7, 3, 2, 'The game has potential to be great again, but as we know EA, they will only ruin it more', '2026-11-02 09:18:21', '2026-11-02 09:18:21'),
(7, 9, 1, 'Cancer-causing game!!!!!\r\nI really don\'t reccommend this game, my son was playing this game and got leucemia because of this brain-rotting piece of sh*t. He was playing it 110 hours per week, it really hurt his hair structure because of the headphones', '2026-11-02 09:26:13', '2026-11-02 09:26:13'),
(9, 3, 4, 'I only appreciate gambling mechanics', '2026-11-02 09:19:46', '2026-11-02 09:19:46'),
(9, 9, 5, 'I LOVE THIS GAME IS MY FAVORITE IM GOLD 2, I LOVE EKITTENS UwU\r\n-posp', '2026-11-02 09:27:18', '2026-11-02 09:27:18');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `token`, `ip_address`, `created_at`, `expires_at`) VALUES
(20, 9, 'e1ab5dfbb4503b7aca4721ab5d6c776776d939d79752fe5d5d58e13bfc933993', '::1', '2026-11-02 09:26:33', '2026-11-09 10:26:33');

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`) VALUES
(4, 'Battle Royale'),
(1, 'First person shooter'),
(3, 'Team based shooter'),
(2, 'Third person shooter');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','editor','admin') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `last_login`) VALUES
(4, 'admin', 'lubkobernhauser2@gmail.com', '$2y$10$yPuTAK9E6jnTxn8xUkrmDugrcuvkLfu.loFt1IXV.5HcJBec4GWUe', 'admin', 1, '2026-10-28 07:53:27', '2026-11-02 09:20:07'),
(7, 'user', 'amaha1337@centrum.sk', '$2y$10$A5joDj0fPJqnblYlu7FIXO2OiRMmV91l0FEhaqAtGBj0ggGrhI4i6', 'user', 1, '2026-11-02 09:05:58', '2026-11-02 09:23:28'),
(9, 'user2', 'luboslav.berny@gmail.com', '$2y$10$i1DuE9ddxfzIEjpnsaySdu74XzYSLxq21Sa5e7lPIEsHiR3NFAHUC', 'user', 1, '2026-11-02 09:19:08', '2026-11-02 09:26:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `changelog`
--
ALTER TABLE `changelog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_changelog_created` (`created_at`),
  ADD KEY `fk_changelog_user` (`user_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`user_id`,`game_id`),
  ADD KEY `idx_favorites_game` (`game_id`);

--
-- Indexes for table `friendships`
--
ALTER TABLE `friendships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_friend_pair` (`requester_id`,`addressee_id`),
  ADD KEY `idx_friend_addressee` (`addressee_id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_games_name` (`name`);

--
-- Indexes for table `game_images`
--
ALTER TABLE `game_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_game_images_game` (`game_id`);

--
-- Indexes for table `game_media`
--
ALTER TABLE `game_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_media_game` (`game_id`),
  ADD KEY `idx_media_user` (`user_id`);

--
-- Indexes for table `game_tags`
--
ALTER TABLE `game_tags`
  ADD PRIMARY KEY (`game_id`,`tag_id`),
  ADD KEY `idx_game_tags_tag` (`tag_id`);

--
-- Indexes for table `media_comments`
--
ALTER TABLE `media_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mcomments_media` (`media_id`,`created_at`),
  ADD KEY `idx_mcomments_user` (`user_id`);

--
-- Indexes for table `media_likes`
--
ALTER TABLE `media_likes`
  ADD PRIMARY KEY (`media_id`,`user_id`),
  ADD KEY `idx_mlikes_user` (`user_id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_news_game` (`game_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`,`is_read`),
  ADD KEY `fk_notif_from` (`from_user_id`),
  ADD KEY `idx_notif_media` (`media_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_resets_token` (`token`),
  ADD KEY `fk_resets_user` (`user_id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`user_id`,`game_id`),
  ADD KEY `idx_ratings_game` (`game_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sessions_token` (`token`),
  ADD KEY `idx_sessions_user` (`user_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tags_name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `changelog`
--
ALTER TABLE `changelog`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_images`
--
ALTER TABLE `game_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friendships`
--
ALTER TABLE `friendships`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_media`
--
ALTER TABLE `game_media`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `media_comments`
--
ALTER TABLE `media_comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `changelog`
--
ALTER TABLE `changelog`
  ADD CONSTRAINT `fk_changelog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `fk_favorites_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friendships`
--
ALTER TABLE `friendships`
  ADD CONSTRAINT `fk_friend_addressee` FOREIGN KEY (`addressee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_friend_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `game_images`
--
ALTER TABLE `game_images`
  ADD CONSTRAINT `fk_game_images_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `game_media`
--
ALTER TABLE `game_media`
  ADD CONSTRAINT `fk_media_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_media_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `game_tags`
--
ALTER TABLE `game_tags`
  ADD CONSTRAINT `fk_game_tags_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_game_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media_comments`
--
ALTER TABLE `media_comments`
  ADD CONSTRAINT `fk_mcomments_media` FOREIGN KEY (`media_id`) REFERENCES `game_media` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mcomments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media_likes`
--
ALTER TABLE `media_likes`
  ADD CONSTRAINT `fk_mlikes_media` FOREIGN KEY (`media_id`) REFERENCES `game_media` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mlikes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `fk_news_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_media` FOREIGN KEY (`media_id`) REFERENCES `game_media` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `fk_ratings_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;