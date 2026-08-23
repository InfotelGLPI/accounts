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

ALTER TABLE `glpi_plugin_comptes` ADD `recursive` tinyint(1) NOT NULL default '0' AFTER `FK_entities`;
ALTER TABLE `glpi_plugin_compte_profiles` ADD `my_groups` char(1) default NULL AFTER `all_users`;
ALTER TABLE `glpi_plugin_comptes` ADD `FK_groups` int(11) NOT NULL default '0' AFTER `requester`;
ALTER TABLE `glpi_plugin_comptes` CHANGE `login` `login` varchar(50) collate utf8_unicode_ci NOT NULL default '';
ALTER TABLE `glpi_plugin_comptes` CHANGE `mdp` `mdp` varchar(255) collate utf8_unicode_ci NOT NULL default '';
INSERT INTO `glpi_displaypreferences` VALUES (NULL , '1902', '2', '3', '0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL , '1902', '3', '1', '0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL , '1902', '4', '2', '0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL , '1902', '5', '4', '0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL , '1902', '6', '5', '0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL , '1902', '7', '6', '0');
INSERT INTO `glpi_displaypreferences` VALUES (NULL , '1902', '8', '7', '0');
RENAME TABLE `glpi_plugin_comptes`  TO `glpi_plugin_compte` ;