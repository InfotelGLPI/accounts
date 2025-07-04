<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2009-2022 by the accounts Development Team.

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

$AJAX_INCLUDE = 1;

include('../../../inc/includes.php');

header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

use Glpi\DBAL\QuerySubQuery;

Session::checkLoginUser();

global $DB;

if (isset($_REQUEST['node'])) {
    /* if ($_SESSION['glpiactiveprofile']['interface']=='helpdesk') {
     $target="helpdesk.public.php";
    } else {*/
    $target = "account.php";
    //}

    $nodes = [];
    // Root node
    if ($_REQUEST['node'] == -1) {
        $criteria = [
            'SELECT' => ['name', 'id'],
            'FROM' => 'glpi_plugin_accounts_accounttypes',
            'WHERE' => [
                'glpi_plugin_accounts_accounttypes.id' => new QuerySubQuery(
                    [
                        'SELECT' => 'plugin_accounts_accounttypes_id',
                        'DISTINCT' => true,
                        'FROM' => 'glpi_plugin_accounts_accounts',
                        'WHERE' => ['is_deleted' => 0,
                            getEntitiesRestrictCriteria('glpi_plugin_accounts_accounts')],
                        'GROUPBY' => 'plugin_accounts_accounttypes_id',
                    ]
                ),
            ],
            'ORDERBY' => 'name',
        ];

        $iterator = $DB->request($criteria);
        if (count($iterator) > 0) {
            $pos = 0;
            foreach ($iterator as $row) {
                $value = Dropdown::getDropdownName("glpi_plugin_accounts_accounttypes", $row['id']);
                $path = [
                    'id' => $row['id'],
                    'text' => $value,
                    'a_attr' => [
                        "onclick" => 'window.open("' . PLUGIN_ACCOUNTS_WEBDIR . '/front/' . $target .
                            '?criteria[0][field]=2&criteria[0][searchtype]=contains&criteria[0][value]=^' .
                            rawurlencode($value) . '&itemtype=PluginAccountsAccount&start=0")'
                    ]
                ];
                $nodes[] = $path;
            }
        }
    }
    echo json_encode($nodes);
}
