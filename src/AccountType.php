<?php

/*
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2015-2026 by the accounts Development Team.

 https://github.com/InfotelGLPI/accounts
 -------------------------------------------------------------------------

 LICENSE

 This file is part of accounts.

 accounts is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.

 accounts is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with accounts. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Accounts;

use CommonDropdown;
use DBConnection;
use Migration;

// Class for a Dropdown
/**
 * Class AccountType
 */
class AccountType extends CommonDropdown
{

    static $rightname = "dropdown";
    var $can_be_translated = true;

   /**
    * @param int $nb
    * @return string
    */
    public static function getTypeName($nb = 0)
    {
        return _n('Type of account', 'Types of account', $nb, 'accounts');
    }

   /**
    * @param $ID
    * @param $entity
    * @return $newID
    */
    public static function transfer($ID, $entity)
    {
        global $DB;

        if ($ID > 0) {
            $table = self::getTable();
            $iterator = $DB->request([
               'FROM'   => $table,
               'WHERE'  => ['id' => $ID]
            ]);

            foreach ($iterator as $data) {
                $input['name']        = $data['name'];
                $input['entities_id'] = $entity;
                $temp                 = new self();
                $newID                = $temp->getID();
                if ($newID < 0) {
                    $newID = $temp->import($input);
                }

                return $newID;
            }
        }
        return 0;
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL auto_increment,
                        `entities_id` int unsigned NOT NULL default '0',
                        `is_recursive` tinyint NOT NULL default '0',
                        `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                        `comment` text collate utf8mb4_unicode_ci,
                        PRIMARY KEY  (`id`),
                        KEY `name` (`name`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
