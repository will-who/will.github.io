-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hostiteľ: 127.0.0.1
-- Čas generovania: Út 27.Okt 2026, 11:32
-- Verzia serveru: 10.4.32-MariaDB
-- Verzia PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáza: `tracker`
--

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `games`
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sťahujem dáta pre tabuľku `games`
--

INSERT INTO `games` (`id`, `name`, `genre`, `image_back`, `image_front`, `image_size`, `image_position`, `description`, `created_at`) VALUES
(1, 'Overwatch', 'First person shooter', 'https://blz-contentstack-images.akamaized.net/v3/assets/blta8f9a8e092360c6c/bltdf9dd58b1d2893d5/6ac5243232e6024d7dc0e46c/blizz_ow_nologo.png?format=webply&quality=80&auto=webp', 'https://blz-contentstack-images.akamaized.net/v3/assets/blta8f9a8e092360c6c/blt356e8885177e1b70/69839b1b9fc491040b9a15f9/logo_ow_enUS.png?format=webply&quality=80&auto=webp', 'cover', 'center', 'Team-based hero shooter where two teams fight over objectives. Pick a hero from the tank, damage or support roles and combine abilities with your team to win each match.', '2026-10-27 07:52:04'),
(2, 'Marvel Rivals', 'Third person shooter', 'https://r.res.easebar.com/pic/20260713/16639216-d99c-4b43-a1b5-4ed8ed298e44.jpeg', NULL, 'cover', 'center', 'Fast 6v6 hero shooter featuring Marvel characters. Each hero has unique abilities and team-up powers, and the maps can be partly destroyed during the fight.', '2026-10-27 07:52:04'),
(3, 'Apex Legends', 'First person shooter', 'https://drop-assets.ea.com/images/6cyQPnGdgdYyJfkxfXEZc7/1980ba3f54aa5da589f0f864ce05cab3/Apex_S30_Primary_SeasonLogo_2160x2160.jpeg', NULL, 'contain', 'center', 'Free-to-play battle royale hero shooter from Respawn Entertainment. Squads drop onto the map, pick a Legend with unique abilities, loot weapons and fight to be the last squad standing.', '2026-10-27 08:35:53');

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `sessions`
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
-- Sťahujem dáta pre tabuľku `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `token`, `ip_address`, `created_at`, `expires_at`) VALUES
(3, 2, 'c8619f9f4c61c61172bffcc8435188853e6ef8ff5cb7596602c89147be4ba0a5', '::1', '2026-10-27 10:30:17', '2026-11-03 11:30:17');

-- --------------------------------------------------------

--
-- Štruktúra tabuľky pre tabuľku `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sťahujem dáta pre tabuľku `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `last_login`) VALUES
(2, 'admin', 'luboslav.berny@gmail.com', '$2y$10$qf32sXFmHAtjP/O9FgvcMOfqad/0jgp3ZzBo9nIDppsD6Oyqp4Z9.', 'admin', 1, '2026-10-27 10:30:17', '2026-10-27 10:30:17');

--
-- Kľúče pre exportované tabuľky
--

--
-- Indexy pre tabuľku `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_games_name` (`name`);

--
-- Indexy pre tabuľku `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_resets_token` (`token`),
  ADD KEY `fk_resets_user` (`user_id`);

--
-- Indexy pre tabuľku `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sessions_token` (`token`),
  ADD KEY `idx_sessions_user` (`user_id`);

--
-- Indexy pre tabuľku `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT pre exportované tabuľky
--

--
-- AUTO_INCREMENT pre tabuľku `games`
--
ALTER TABLE `games`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pre tabuľku `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pre tabuľku `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pre tabuľku `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Obmedzenie pre exportované tabuľky
--

--
-- Obmedzenie pre tabuľku `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Obmedzenie pre tabuľku `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
