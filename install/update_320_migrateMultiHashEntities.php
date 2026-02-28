<?php

/*
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2015 by the accounts Development Team.
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

use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\Hash;

/**
 *
 * @return bool for success (will die for most error)
 * */
function update_320_migrateMultiHashEntities()
{
    $entity = new Entity();
    $hashes = new Hash();
    $account = new Account();

    $entities = $entity->find();
    foreach ($entities as $e) {
        $restrict = getEntitiesRestrictCriteria(
            "glpi_plugin_accounts_hashes",
            '',
            $e['id'],
            $hashes->maybeRecursive()
        );
        $hashesList = getAllDataFromTable("glpi_plugin_accounts_hashes", $restrict);
        $idHash = 0;

        if (count($hashesList) > 0) {
            foreach ($hashesList as $hashItem) {
                $idHash = $hashItem['id'];
            }

            $accounts = $account->find(['entities_id' => $e['id']]);
            foreach ($accounts as $a) {
                $input = [];
                $input['id'] = $a['id'];
                $input['plugin_accounts_hashes_id'] = $idHash;
                $account->update($input);
            }
        }
    }
    return true;
}
