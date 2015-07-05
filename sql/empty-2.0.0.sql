DROP TABLE IF EXISTS `glpi_plugin_accounts_accounts`;
CREATE TABLE `glpi_plugin_accounts_accounts` (
   `id` int(11) NOT NULL auto_increment,
   `entities_id` int(11) NOT NULL default '0',
   `is_recursive` tinyint(1) NOT NULL default '0',
   `name` varchar(255) collate utf8_unicode_ci default NULL,
   `login` varchar(255) collate utf8_unicode_ci default NULL,
   `encrypted_password` varchar(255) collate utf8_unicode_ci default NULL,
   `others` varchar(255) collate utf8_unicode_ci default NULL,
   `plugin_accounts_accounttypes_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accounttypes (id)',
   `plugin_accounts_accountstates_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accountstates (id)',
   `date_creation` date default NULL,
   `date_expiration` date default NULL,
   `users_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_users (id)',
   `groups_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_groups (id)',
   `users_id_tech` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_users (id)',
   `groups_id_tech` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_groups (id)',
   `locations_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_locations (id)',
   `is_helpdesk_visible` int(11) NOT NULL default '1',
   `date_mod` datetime default NULL,
   `comment` text collate utf8_unicode_ci,
   `is_deleted` tinyint(1) NOT NULL default '0',
   PRIMARY KEY  (`id`),
   KEY `name` (`name`),
      KEY `entities_id` (`entities_id`),
      KEY `plugin_accounts_accounttypes_id` (`plugin_accounts_accounttypes_id`),
      KEY `plugin_accounts_accountstates_id` (`plugin_accounts_accountstates_id`),
      KEY `users_id` (`users_id`),
      KEY `groups_id` (`groups_id`),
      KEY `users_id_tech` (`users_id_tech`),
      KEY `groups_id_tech` (`groups_id_tech`),
      KEY `date_mod` (`date_mod`),
      KEY `is_helpdesk_visible` (`is_helpdesk_visible`),
      KEY `is_deleted` (`is_deleted`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_accounts_accounttypes`;
CREATE TABLE `glpi_plugin_accounts_accounttypes` (
   `id` int(11) NOT NULL auto_increment,
   `entities_id` int(11) NOT NULL default '0',
   `is_recursive` tinyint(1) NOT NULL default '0',
   `name` varchar(255) collate utf8_unicode_ci default NULL,
   `comment` text collate utf8_unicode_ci,
   PRIMARY KEY  (`id`),
   KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_accounts_accountstates`;
CREATE TABLE `glpi_plugin_accounts_accountstates` (
      `id` int(11) NOT NULL auto_increment,
      `name` varchar(255) collate utf8_unicode_ci default NULL,
   `comment` text collate utf8_unicode_ci,
   PRIMARY KEY  (`id`),
   KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_accounts_hashes`;
CREATE TABLE `glpi_plugin_accounts_hashes` (
      `id` int(11) NOT NULL auto_increment,
      `name` varchar(255) collate utf8_unicode_ci default NULL,
      `entities_id` int(11) NOT NULL default '0',
      `is_recursive` tinyint(1) NOT NULL default '0',
      `hash` varchar(255) collate utf8_unicode_ci default NULL,
      `comment` text collate utf8_unicode_ci,
      `date_mod` datetime default NULL,
      PRIMARY KEY  (`id`),
      KEY `entities_id` (`entities_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_accounts_aeskeys`;
CREATE TABLE `glpi_plugin_accounts_aeskeys` (
      `id` int(11) NOT NULL auto_increment,
      `name` varchar(255) collate utf8_unicode_ci default NULL,
      `plugin_accounts_hashes_id` int(11) NOT NULL default '0',
      PRIMARY KEY  (`id`),
      KEY `plugin_accounts_hashes_id` (`plugin_accounts_hashes_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_accounts_accounts_items`;
CREATE TABLE `glpi_plugin_accounts_accounts_items` (
   `id` int(11) NOT NULL auto_increment,
   `plugin_accounts_accounts_id` int(11) NOT NULL default '0',
   `items_id` int(11) NOT NULL default '0' COMMENT 'RELATION to various tables, according to itemtype (id)',
   `itemtype` varchar(100) collate utf8_unicode_ci NOT NULL COMMENT 'see .class.php file',
   PRIMARY KEY  (`id`),
   UNIQUE KEY `unicity` (`plugin_accounts_accounts_id`,`itemtype`,`items_id`),
   KEY `FK_device` (`items_id`,`itemtype`),
   KEY `item` (`itemtype`,`items_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_accounts_configs`;
CREATE TABLE `glpi_plugin_accounts_configs` (
      `id` int(11) NOT NULL auto_increment,
   `delay_expired` varchar(50) collate utf8_unicode_ci NOT NULL default '30',
   `delay_whichexpire` varchar(50) collate utf8_unicode_ci NOT NULL default '30',
   PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `glpi_plugin_accounts_configs` ( `id` , `delay_expired` , `delay_whichexpire`) VALUES (1, '30', '30');

DROP TABLE IF EXISTS `glpi_plugin_accounts_notificationstates`;
CREATE TABLE `glpi_plugin_accounts_notificationstates` (
   `id` int(11) NOT NULL auto_increment,
   `plugin_accounts_accountstates_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accountstates (id)',
   PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_compte_mailing`;

INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsAccount','2','3','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsAccount','3','1','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsAccount','4','2','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsAccount','5','4','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsAccount','6','5','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsAccount','7','6','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsHelpdesk','2','3','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsHelpdesk','3','1','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsHelpdesk','4','2','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsHelpdesk','5','4','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsHelpdesk','6','5','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsHelpdesk','7','6','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsGroup','2','3','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsGroup','3','1','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsGroup','4','2','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsGroup','5','4','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsGroup','6','5','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsGroup','7','6','0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginAccountsGroup','8','7','0');

INSERT INTO `glpi_notificationtemplates` VALUES(NULL, 'New Accounts', 'PluginAccountsAccount', '2010-02-17 22:36:46','',NULL);
INSERT INTO `glpi_notificationtemplates` VALUES(NULL, 'Alert Accounts', 'PluginAccountsAccount', '2010-02-23 11:37:46','',NULL);
