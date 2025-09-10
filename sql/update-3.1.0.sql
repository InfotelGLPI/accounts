UPDATE `glpi_displaypreferences` SET `interface` = 'helpdesk' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginAccountsHelpdesk';
DELETE  FROM `glpi_displaypreferences` WHERE `itemtype` LIKE '%PluginAccountsGroup%' AND users_id = 0;
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginAccountsHelpdesk';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_notificationtemplates` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_notifications` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_impactrelations` SET `itemtype_source` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype_source` = 'PluginAccountsAccount';
UPDATE `glpi_impactrelations` SET `itemtype_impacted` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype_impacted` = 'PluginAccountsAccount';

UPDATE `glpi_documents_items` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_savedsearches` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_items_tickets` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_dropdowntranslations` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_savedsearches_users` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_notepads` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
