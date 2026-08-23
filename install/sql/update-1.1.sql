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

ALTER TABLE `glpi_comptes` ADD `notes` LONGTEXT NOT NULL;

ALTER TABLE `glpi_comptes` RENAME `glpi_plugin_comptes`;

ALTER TABLE `glpi_compte_device` RENAME `glpi_plugin_compte_device`;

ALTER TABLE `glpi_dropdown_compte_type` RENAME `glpi_dropdown_plugin_compte_type`;

CREATE TABLE `glpi_plugin_compte_documents` (
	`ID` int(11) NOT NULL auto_increment,
	`FK_documents` int(11) NOT NULL default '0',
	`FK_compte` int(11) NOT NULL default '0',
	PRIMARY KEY  (`ID`),
	UNIQUE KEY `FK_documents` (`FK_documents`,`FK_compte`),
	KEY `FK_documents_2` (`FK_documents`),
	KEY `FK_compte` (`FK_compte`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_compte_profiles` (
	`ID` int(11) NOT NULL auto_increment,
	`name` varchar(255) default NULL,
	`interface` varchar(50) NOT NULL default 'compte',
	`is_default` enum('0','1') NOT NULL default '0',
	`compte` char(1) default NULL,
	`statistics` char(1) default NULL,
	`create_compte` char(1) default NULL,
	`update_compte` char(1) default NULL,
	`delete_compte` char(1) default NULL,
	`all_users` char(1) default NULL,
	PRIMARY KEY  (`ID`),
	KEY `interface` (`interface`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `glpi_plugin_compte_profiles` ( `ID`, `name` , `interface`, `is_default`, `compte`,`statistics`,`create_compte`,`update_compte`,`delete_compte`,`all_users`) VALUES ('1', 'post-only','compte','1',NULL,NULL,NULL,NULL,NULL,NULL);

INSERT INTO `glpi_plugin_compte_profiles` ( `ID`, `name` , `interface`, `is_default`, `compte`,`statistics`,`create_compte`,`update_compte`,`delete_compte`,`all_users`) VALUES ('2', 'normal','compte','0','r','r',NULL,NULL,NULL,NULL);

INSERT INTO `glpi_plugin_compte_profiles` ( `ID`, `name` , `interface`, `is_default`, `compte`,`statistics`,`create_compte`,`update_compte`,`delete_compte`,`all_users`) VALUES ('3', 'admin','compte','0','w','r','1','1',NULL,NULL);

INSERT INTO `glpi_plugin_compte_profiles` ( `ID`, `name` , `interface`, `is_default`, `compte`,`statistics`,`create_compte`,`update_compte`,`delete_compte`,`all_users`) VALUES ('4', 'super-admin','compte','0','w','r','1','1','1','1');

ALTER TABLE `glpi_plugin_comptes` CHANGE `notes` `notes` LONGTEXT ;

ALTER TABLE `glpi_plugin_comptes` CHANGE `comments` `comments` TEXT ;