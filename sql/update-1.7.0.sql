ALTER TABLE `glpi_plugin_accounts_accounts`
   ADD `locations_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_locations (id)',
   ADD INDEX (`locations_id`);

RENAME TABLE `glpi_plugin_accounts_hashs` TO `glpi_plugin_accounts_hashes` ;