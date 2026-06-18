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
 the Free Software Foundation; either version 2 of the License, or
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

use CommonDBTM;
use DBConnection;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Migration;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class NotificationState
 */
class NotificationState extends CommonDBTM
{
    public static $rightname = "config";
    /**
     * @return array
     */
    public function findStates()
    {

        $state = new self();
        $states = $state->find();
        $data = [];
        foreach ($states as $dataChilds) {
            $data[] = $dataChilds["plugin_accounts_accountstates_id"];
        }

        return $data;
    }

    /**
     * @param $plugin_accounts_accountstates_id
     */
    public function addNotificationState($plugin_accounts_accountstates_id)
    {

        if ($this->getFromDBbyCrit(['plugin_accounts_accountstates_id' => $plugin_accounts_accountstates_id])) {
            $this->update([
                'id' => $this->fields['id'],
                'plugin_accounts_accountstates_id' => $plugin_accounts_accountstates_id]);
        } else {
            $this->add([
                'plugin_accounts_accountstates_id' => $plugin_accounts_accountstates_id]);
        }
    }

    /**
     * @param $target
     */
    public function showNotificationForm($target)
    {

        $states = $this->find([], ["plugin_accounts_accountstates_id ASC"]);

        $used = $entries = [];

        $canedit = $this->canEdit($this->getID());

        foreach ($states as $value) {
            $used[] = $value['plugin_accounts_accountstates_id'];


            $entries[] = [
                'itemtype' => self::class,
                'id' => $value['id'],
                'name' => Dropdown::getDropdownName(
                    "glpi_plugin_accounts_accountstates",
                    $value["plugin_accounts_accountstates_id"]
                ),
            ];
        }


        $columns = [
            'name' => __('Name'),
        ];
        $formatters = [
            'name' => 'raw_html',
        ];
        $footers = [];

        $rand = mt_rand();

        TemplateRenderer::getInstance()->display(
            '@accounts/status_cron.html.twig',
            [
                'id'                => 1,
                'item'              => $this,
                'config'            => $this->fields,
                'action'            => $target,
                'used'            => $used,
                'can_edit' => $canedit,
                'datatable_params' => [
                    'is_tab' => true,
                    'nofilter' => true,
                    'nosort' => true,
                    'columns' => $columns,
                    'formatters' => $formatters,
                    'entries' => $entries,
                    'footers' => $footers,
                    'total_number' => count($entries),
                    'filtered_number' => count($entries),
                    'showmassiveactions' => $canedit,
                    'massiveactionparams' => [
                        'container' => 'massiveactioncontainer' . $rand,
                        'itemtype'  => self::class,
                    ],
                ],
            ],
        );
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        $forbidden[] = 'delete';
        $forbidden[] = 'restore';
        return $forbidden;
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
                        `plugin_accounts_accountstates_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accountstates (id)',
                         PRIMARY KEY  (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
