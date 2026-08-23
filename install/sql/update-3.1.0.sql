--
-- -------------------------------------------------------------------------
-- accounts plugin for GLPI
-- Copyright (C) 2015-2026 by the accounts Development Team.
--
-- https://github.com/InfotelGLPI/accounts
-- -------------------------------------------------------------------------
--
-- LICENSE
--
-- This file is part of accounts.
--
-- accounts is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- accounts is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with accounts. If not, see <http://www.gnu.org/licenses/>.
-- --------------------------------------------------------------------------
--

UPDATE `glpi_displaypreferences` SET `interface` = 'helpdesk' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginAccountsHelpdesk';
DELETE  FROM `glpi_displaypreferences` WHERE `itemtype` LIKE '%PluginAccountsGroup%' AND users_id = 0;
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginAccountsHelpdesk';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_notificationtemplates` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_notifications` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_notificationtemplates` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_impactrelations` SET `itemtype_source` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype_source` = 'PluginAccountsAccount';
UPDATE `glpi_impactrelations` SET `itemtype_impacted` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype_impacted` = 'PluginAccountsAccount';

UPDATE `glpi_documents_items` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_savedsearches` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_items_tickets` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_dropdowntranslations` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_savedsearches_users` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
UPDATE `glpi_notepads` SET `itemtype` = 'GlpiPlugin\\Accounts\\Account' WHERE `itemtype` = 'PluginAccountsAccount';
