-- Spusti raz v phpMyAdmin (databáza tracker).
-- Email je vo formulári voliteľný, preto musí povoliť NULL.
-- (UNIQUE index dovoľuje viac NULL hodnôt.)
ALTER TABLE `users` MODIFY `email` VARCHAR(255) NULL;