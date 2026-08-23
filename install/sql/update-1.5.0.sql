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

ALTER TABLE `glpi_plugin_compte` CHANGE `creation_date` `creation_date` DATE NULL default NULL;
UPDATE `glpi_plugin_compte` SET `creation_date` = NULL WHERE `creation_date` ='0000-00-00';
ALTER TABLE `glpi_plugin_compte` CHANGE `expiration_date` `expiration_date` DATE NULL default NULL;
UPDATE `glpi_plugin_compte` SET `expiration_date` = NULL WHERE `expiration_date` ='0000-00-00';

ALTER TABLE `glpi_plugin_compte_profiles` DROP COLUMN `interface` , DROP COLUMN `is_default`;

ALTER TABLE `glpi_plugin_compte` CHANGE `requester` `FK_users` int(4);
ALTER TABLE `glpi_plugin_compte_profiles` ADD `open_ticket` char(1) default NULL;

CREATE TABLE `glpi_plugin_compte_hash` (
  `ID` int(11) NOT NULL auto_increment,
  `hash` varchar(255) collate utf8_unicode_ci NOT NULL default '',
  PRIMARY KEY  (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `glpi_plugin_compte_hash` ( `ID` , `hash` ) VALUES (1, '');