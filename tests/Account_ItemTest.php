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

namespace GlpiPlugin\Accounts\Tests;

use Computer;
use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\Account_Item;
use GlpiPlugin\Accounts\Hash;
use Glpi\Tests\DbTestCase;

class Account_ItemTest extends DbTestCase
{
    private function createTestAccount(): Account
    {
        $hash = $this->createItem(Hash::class, [
            'name'         => 'item-test-hash',
            'hash'         => hash('sha256', hash('sha256', 'item-test-fp')),
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        return $this->createItem(Account::class, [
            'name'                      => 'Item Test Account',
            'entities_id'               => 0,
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);
    }

    public function testAddItemCreatesAssociation(): void
    {
        $this->login();

        $account  = $this->createTestAccount();
        $computer = $this->createItem(Computer::class, [
            'name'        => 'assoc-computer',
            'entities_id' => 0,
        ]);

        $account_item = new Account_Item();
        $id = $account_item->add([
            'plugin_accounts_accounts_id' => $account->getID(),
            'items_id'                    => $computer->getID(),
            'itemtype'                    => Computer::class,
        ]);

        $this->assertGreaterThan(0, $id);
    }

    public function testDeleteItemByAccountsAndItemRemovesAssociation(): void
    {
        $this->login();

        $account  = $this->createTestAccount();
        $computer = $this->createItem(Computer::class, [
            'name'        => 'delete-computer',
            'entities_id' => 0,
        ]);

        $account_item = new Account_Item();
        $account_item->add([
            'plugin_accounts_accounts_id' => $account->getID(),
            'items_id'                    => $computer->getID(),
            'itemtype'                    => Computer::class,
        ]);

        $result = $account_item->deleteItemByAccountsAndItem(
            $account->getID(),
            $computer->getID(),
            Computer::class
        );

        $this->assertTrue($result);

        $remaining = countElementsInTable(
            'glpi_plugin_accounts_accounts_items',
            [
                'plugin_accounts_accounts_id' => $account->getID(),
                'items_id'                    => $computer->getID(),
                'itemtype'                    => Computer::class,
            ]
        );
        $this->assertSame(0, $remaining);
    }

    public function testDeleteItemByAccountsAndItemReturnsFalseWhenNotFound(): void
    {
        $this->login();

        $account_item = new Account_Item();

        $result = $account_item->deleteItemByAccountsAndItem(99999, 99999, Computer::class);

        $this->assertFalse($result);
    }

    public function testCountForItemReturnsCorrectCount(): void
    {
        $this->login();

        $account  = $this->createTestAccount();
        $computer = $this->createItem(Computer::class, [
            'name'        => 'count-computer',
            'entities_id' => 0,
        ]);

        $account_item = new Account_Item();
        $account_item->add([
            'plugin_accounts_accounts_id' => $account->getID(),
            'items_id'                    => $computer->getID(),
            'itemtype'                    => Computer::class,
        ]);

        $count = Account_Item::countForItem($computer);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testAddItemRejectsUnknownItemtype(): void
    {
        $this->login();

        $account      = $this->createTestAccount();
        $account_item = new Account_Item();

        $result = $account_item->addItem([
            'plugin_accounts_accounts_id' => $account->getID(),
            'items_id'                    => 1,
            'itemtype'                    => 'NonExistentItemtype',
        ]);

        $this->assertFalse($result);
    }

    public function testCleanForItemPurgesAssociations(): void
    {
        $this->login();

        $account  = $this->createTestAccount();
        $computer = $this->createItem(Computer::class, [
            'name'        => 'clean-computer',
            'entities_id' => 0,
        ]);

        $account_item = new Account_Item();
        $account_item->add([
            'plugin_accounts_accounts_id' => $account->getID(),
            'items_id'                    => $computer->getID(),
            'itemtype'                    => Computer::class,
        ]);

        Account_Item::cleanForItem($computer);

        $remaining = countElementsInTable(
            'glpi_plugin_accounts_accounts_items',
            [
                'items_id' => $computer->getID(),
                'itemtype' => Computer::class,
            ]
        );
        $this->assertSame(0, $remaining);
    }
}
