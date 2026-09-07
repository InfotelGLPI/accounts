<?php

/**
 * -------------------------------------------------------------------------
 * accounts plugin for GLPI
 * Copyright (C) 2015-2026 by the accounts Development Team.
 *
 * https://github.com/InfotelGLPI/accounts
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of accounts.
 *
 * accounts is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * accounts is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with accounts. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

use GlpiPlugin\Accounts\Account;

if (strpos($_SERVER['PHP_SELF'], "dropdownTypeAccounts.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

global $DB;

Session::checkCentralAccess();

Session::checkRight("plugin_accounts", READ);

// Make a select box
if (isset($_POST["accounttype"])) {
    $used = [];

    // Clean used array
    if (isset($_POST['used'])
       && is_array($_POST['used'])
       && (count($_POST['used']) > 0)) {
        $iterator = $DB->request([
            'SELECT'    => [
                'id',
            ],
            'FROM'      => 'glpi_plugin_accounts_accounts',
            'WHERE'     => [
                ['id' => $_POST['used'],
                    'plugin_accounts_accounttypes_id' => $_POST["accounttype"],
                ],
            ],
        ]);

        if (count($iterator) > 0) {
            foreach ($iterator as $data) {
                $used[$data['id']] = $data['id'];
            }
        }
    }

    // 'entity' comes straight from the request: the caller only ever sends the entity of the
    // form it renders, but nothing stops a client from sending another one, and Dropdown::show()
    // takes it as the authoritative restriction. Intersecting it with the active entities turns
    // it back into a narrowing hint over what the session already grants.
    $entity_restrict = Session::getMatchingActiveEntities($_POST['entity'] ?? []);

    // Same rule as the account list itself: the entity says which accounts exist here, the
    // visibility criteria say which of them this profile is allowed to read. Only the first half
    // was applied, so the dropdown listed every account of the entity by name whatever the
    // 'see all users' / 'my groups' rights of the caller.
    $condition = [
        'glpi_plugin_accounts_accounts.plugin_accounts_accounttypes_id' => $_POST["accounttype"],
    ];
    $visibility = Account::getVisibilityCriteria(true);
    if ($visibility !== []) {
        $condition[] = $visibility;
    }

    Dropdown::show(
        Account::class,
        ['name' => $_POST['myname'],
            'used' => $used,
            'width' => '50%',
            'entity' => $entity_restrict,
            'rand' => $_POST['rand'],
            'condition' => $condition],
    );
}
