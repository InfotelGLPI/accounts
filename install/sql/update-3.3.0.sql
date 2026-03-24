ALTER TABLE `glpi_plugin_accounts_accounts`
    ADD COLUMN `encrypted_totp_secret` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL
    AFTER `encrypted_password`;
