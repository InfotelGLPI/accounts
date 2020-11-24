ALTER TABLE `glpi_plugin_accounts_accounts` 
   ADD `groups_id_tech` int(11) NOT NULL DEFAULT '0',
   ADD `users_id_tech` int(11) NOT NULL DEFAULT '0';