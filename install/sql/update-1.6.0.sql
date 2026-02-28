ALTER TABLE `glpi_plugin_compte` RENAME `glpi_plugin_accounts_accounts`;
ALTER TABLE `glpi_dropdown_plugin_compte_type` RENAME `glpi_plugin_accounts_accounttypes`;
ALTER TABLE `glpi_dropdown_plugin_compte_status` RENAME `glpi_plugin_accounts_accountstates`;
ALTER TABLE `glpi_plugin_compte_hash` RENAME `glpi_plugin_accounts_hashs`;
ALTER TABLE `glpi_plugin_compte_aeskey` RENAME `glpi_plugin_accounts_aeskeys`;
ALTER TABLE `glpi_plugin_compte_device` RENAME `glpi_plugin_accounts_accounts_items`;
ALTER TABLE `glpi_plugin_compte_profiles` RENAME `glpi_plugin_accounts_profiles`;
ALTER TABLE `glpi_plugin_compte_config` RENAME `glpi_plugin_accounts_configs`;
ALTER TABLE `glpi_plugin_compte_default` RENAME `glpi_plugin_accounts_notificationstates`;
DROP TABLE IF EXISTS `glpi_plugin_compte_mailing`;

ALTER TABLE `glpi_plugin_accounts_accounts`
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   CHANGE `FK_entities` `entities_id` int(11) NOT NULL default '0',
   CHANGE `recursive` `is_recursive` tinyint(1) NOT NULL default '0',
   CHANGE `name` `name` varchar(255) collate utf8_unicode_ci default NULL,
   CHANGE `login` `login` varchar(255) collate utf8_unicode_ci default NULL,
   CHANGE `mdp` `encrypted_password` varchar(255) collate utf8_unicode_ci default NULL,
   CHANGE `others` `others` varchar(255) collate utf8_unicode_ci default NULL,
   CHANGE `type` `plugin_accounts_accounttypes_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accounttypes (id)',
   CHANGE `status` `plugin_accounts_accountstates_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accountstates (id)',
   CHANGE `creation_date` `date_creation` date default NULL,
   CHANGE `expiration_date` `date_expiration` date default NULL,
   CHANGE `FK_users` `users_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_users (id)',
   CHANGE `FK_groups` `groups_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_groups (id)',
   CHANGE `helpdesk_visible` `is_helpdesk_visible` int(11) NOT NULL default '1',
   CHANGE `notes` `notepad` longtext collate utf8_unicode_ci,
   CHANGE `comments` `comment` text collate utf8_unicode_ci,
   CHANGE `deleted` `is_deleted` tinyint(1) NOT NULL default '0',
   ADD INDEX (`name`),
   ADD INDEX (`entities_id`),
   ADD INDEX (`plugin_accounts_accounttypes_id`),
   ADD INDEX (`plugin_accounts_accountstates_id`),
   ADD INDEX (`users_id`),
   ADD INDEX (`groups_id`),
   ADD INDEX (`date_mod`),
   ADD INDEX (`is_helpdesk_visible`),
   ADD INDEX (`is_deleted`);

ALTER TABLE `glpi_plugin_accounts_accounttypes`
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   CHANGE `FK_entities` `entities_id` int(11) NOT NULL default '0',
   CHANGE `name` `name` varchar(255) collate utf8_unicode_ci default NULL,
   CHANGE `comments` `comment` text collate utf8_unicode_ci;

ALTER TABLE `glpi_plugin_accounts_accountstates`
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   CHANGE `name` `name` varchar(255) collate utf8_unicode_ci default NULL,
   CHANGE `comments` `comment` text collate utf8_unicode_ci;

ALTER TABLE `glpi_plugin_accounts_hashs`
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   CHANGE `hash` `hash` varchar(255) collate utf8_unicode_ci default NULL;

ALTER TABLE `glpi_plugin_accounts_aeskeys`
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   CHANGE `aeskey` `aeskey` varchar(255) collate utf8_unicode_ci default NULL;

ALTER TABLE `glpi_plugin_accounts_accounts_items`
   DROP INDEX `FK_compte`,
   DROP INDEX `FK_compte_2`,
   DROP INDEX `FK_device`,
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   CHANGE `FK_compte` `plugin_accounts_accounts_id` int(11) NOT NULL default '0',
   CHANGE `FK_device` `items_id` int(11) NOT NULL default '0' COMMENT 'RELATION to various tables, according to itemtype (id)',
   CHANGE `device_type` `itemtype` varchar(100) collate utf8_unicode_ci NOT NULL COMMENT 'see .class.php file',
   ADD UNIQUE `unicity` (`plugin_accounts_accounts_id`,`itemtype`,`items_id`),
   ADD INDEX `FK_device` (`items_id`,`itemtype`),
   ADD INDEX `item` (`itemtype`,`items_id`);

ALTER TABLE `glpi_plugin_accounts_profiles`
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   ADD `profiles_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_profiles (id)',
   CHANGE `compte` `accounts` char(1) collate utf8_unicode_ci default NULL,
   CHANGE `all_users` `all_users` char(1) collate utf8_unicode_ci default NULL,
   CHANGE `my_groups` `my_groups` char(1) collate utf8_unicode_ci default NULL,
   CHANGE `open_ticket` `open_ticket` char(1) collate utf8_unicode_ci default NULL,
   ADD INDEX (`profiles_id`);

ALTER TABLE `glpi_plugin_accounts_notificationstates`
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   CHANGE `status` `plugin_accounts_accountstates_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accountstates (id)';

ALTER TABLE `glpi_plugin_accounts_configs` 
   CHANGE `ID` `id` int(11) NOT NULL auto_increment,
   CHANGE `delay` `delay_expired` varchar(50) collate utf8_unicode_ci NOT NULL default '30',
   ADD `delay_whichexpire` varchar(50) collate utf8_unicode_ci NOT NULL default '30';

INSERT INTO `glpi_notificationtemplates` VALUES(NULL, 'New Accounts', 'PluginAccountsAccount', '2010-02-17 22:36:46','',NULL, '2010-02-17 22:36:46');
INSERT INTO `glpi_notificationtemplates` VALUES(NULL, 'Alert Accounts', 'PluginAccountsAccount', '2010-02-23 11:37:46','',NULL, '2010-02-17 22:36:46');