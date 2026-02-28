ALTER TABLE `glpi_plugin_accounts_aeskeys`
   CHANGE `aeskey` `name` varchar(255) collate utf8_unicode_ci default NULL,
   ADD `plugin_accounts_hashes_id` int(11) NOT NULL default '0',
   ADD INDEX (`plugin_accounts_hashes_id`);
   
ALTER TABLE `glpi_plugin_accounts_hashes`
   ADD `name` varchar(255) collate utf8_unicode_ci default NULL,
   ADD `entities_id` int(11) NOT NULL default '0',
   ADD `is_recursive` tinyint(1) NOT NULL default '0',
   ADD `date_mod` datetime default NULL,
   ADD `comment` text collate utf8_unicode_ci,
   ADD INDEX (`entities_id`);