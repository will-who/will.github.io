-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 28, 2026 at 12:54 PM
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
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED NOT NULL,
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
(2, 3, 'b04eced8b1edea991cf64fe74c9bdc53c1492469df138a6b8f7ebf31efd38142', '2026-10-28 10:12:30', 0),
(3, 4, 'e02d5044f4db474d5c772d521f7af4726bbc17c60cfb033d9a5f18bd6e5844c9', '2026-10-28 10:27:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 'editor', 'luboslav.berny@gmail.com', '$2y$10$qf32sXFmHAtjP/O9FgvcMOfqad/0jgp3ZzBo9nIDppsD6Oyqp4Z9.', 'editor', 1, '2026-10-27 10:30:17', '2026-10-27 12:59:09'),
(3, 'user', 'amaha1337@centrum.sk', '$2y$10$zQ0pmSI4bAO8z5EX4rhOPOJUua/k.7ORxFEhQ7LMLulPOpFGruMoS', 'user', 1, '2026-10-27 12:54:13', '2026-10-27 12:54:13'),
(4, 'admin', 'lubkobernhauser2@gmail.com', '$2y$10$yPuTAK9E6jnTxn8xUkrmDugrcuvkLfu.loFt1IXV.5HcJBec4GWUe', 'admin', 1, '2026-10-28 07:53:27', '2026-10-28 08:09:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`user_id`,`game_id`),
  ADD KEY `idx_favorites_game` (`game_id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_games_name` (`name`);

--
-- Indexes for table `game_tags`
--
ALTER TABLE `game_tags`
  ADD PRIMARY KEY (`game_id`,`tag_id`),
  ADD KEY `idx_game_tags_tag` (`tag_id`);

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
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `fk_favorites_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `game_tags`
--
ALTER TABLE `game_tags`
  ADD CONSTRAINT `fk_game_tags_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_game_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

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
