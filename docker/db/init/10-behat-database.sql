-- Creates the dedicated database used by the Behat suite (APP_ENV=behat,
-- dbname_suffix "_behat" in config/packages/doctrine.yaml) and grants the
-- application user full access to it, so the automated tests never touch
-- development data.
--
-- MySQL runs files from /docker-entrypoint-initdb.d/ only when the data
-- volume is first initialised. On an existing volume, apply it manually:
--   docker exec -i takt-database-1 mysql -uroot -p<root-password> < docker/db/init/10-behat-database.sql
CREATE DATABASE IF NOT EXISTS `app_behat`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `app_behat`.* TO 'app'@'%';
FLUSH PRIVILEGES;
