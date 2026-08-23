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