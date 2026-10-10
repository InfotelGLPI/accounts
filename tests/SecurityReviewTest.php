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

namespace GlpiPlugin\Accounts\Tests;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\AccountCrypto;
use GlpiPlugin\Accounts\AesKey;
use GlpiPlugin\Accounts\Hash;
use MassiveAction;

/**
 * Regressions of the 3.3.0 security review:
 * - MEDIUM: the transfer re-encrypts the secrets from one stored key to another, only for a
 *   caller entitled to the stored keys (plugin_accounts_hash UPDATE);
 * - LOW: a fingerprint with a salted verifier only takes v4 records, the records still in a
 *   former format are counted and migrated by a rotation to the same key.
 */
class SecurityReviewTest extends DbTestCase
{
    private const SOURCE_KEY = 'source-entity-key';
    private const DEST_KEY   = 'destination-entity-key';

    /** Fingerprint of an entity, its key stored server side */
    private function createVault(int $entities_id, string $key): Hash
    {
        /** @var Hash $hash */
        $hash = $this->createItem(Hash::class, [
            'name'         => 'Vault ' . $this->getUniqueString(),
            'hash'         => AccountCrypto::makeVerifier($key),
            'entities_id'  => $entities_id,
            'is_recursive' => 0,
        ]);
        $this->createItem(AesKey::class, [
            'name'                      => $key,
            'plugin_accounts_hashes_id' => $hash->getID(),
        ], ['name']);

        return $hash;
    }

    private function createAccount(Hash $hash, string $password): Account
    {
        /** @var Account $account */
        $account = $this->createItem(Account::class, [
            'name'                      => 'Transferred ' . $this->getUniqueString(),
            'entities_id'               => $hash->fields['entities_id'],
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);
        $this->updateItem(Account::class, $account->getID(), [
            'encrypted_password' => AccountCrypto::encrypt($password, self::SOURCE_KEY, $hash->fields['hash']),
        ], ['encrypted_password']);
        $account->getFromDB($account->getID());

        return $account;
    }

    /**
     * Runs the transfer massive action
     *
     * @return array<int, int> status of each account (MassiveAction::ACTION_*)
     */
    private function transfer(Account $account, int $entities_id): array
    {
        $done = [];
        $ma   = self::createStub(MassiveAction::class);
        $ma->method('getAction')->willReturn('transfer');
        $ma->method('getInput')->willReturn(['entities_id' => $entities_id]);
        $ma->method('itemDone')->willReturnCallback(
            static function ($itemtype, $id, $status) use (&$done): void {
                $done[(int) $id] = $status;
            },
        );

        Account::processMassiveActionsForOneItemtype($ma, new Account(), [$account->getID()]);

        return $done;
    }

    public function testATransferWithoutTheKeyRightIsRefusedForAnAccountHoldingASecret(): void
    {
        $this->login();
        $source  = $this->createVault(getItemByTypeName(\Entity::class, '_test_child_1', true), self::SOURCE_KEY);
        $dest_id = getItemByTypeName(\Entity::class, '_test_child_2', true);
        $this->createVault($dest_id, self::DEST_KEY);
        $account = $this->createAccount($source, 'secret-of-entity-1');
        $before  = $account->fields['encrypted_password'];
        $_SESSION['glpiactiveprofile'][Account::$rightname] = ALLSTANDARDRIGHT;
        $_SESSION['glpiactiveprofile'][Hash::$rightname]    = READ;

        $done = $this->transfer($account, $dest_id);

        $this->assertSame(MassiveAction::ACTION_NORIGHT, $done[$account->getID()]);
        $account->getFromDB($account->getID());
        // Not moved, not re-encrypted under a key the caller holds
        $this->assertSame((int) $source->fields['entities_id'], (int) $account->fields['entities_id']);
        $this->assertSame($before, $account->fields['encrypted_password']);
        $this->assertSame('', AccountCrypto::decrypt($account->fields['encrypted_password'], self::DEST_KEY));
    }

    public function testATransferByAKeyManagerReEncryptsAndRebinds(): void
    {
        $this->login();
        $source  = $this->createVault(getItemByTypeName(\Entity::class, '_test_child_1', true), self::SOURCE_KEY);
        $dest_id = getItemByTypeName(\Entity::class, '_test_child_2', true);
        $dest    = $this->createVault($dest_id, self::DEST_KEY);
        $account = $this->createAccount($source, 'secret-of-entity-1');
        $_SESSION['glpiactiveprofile'][Account::$rightname] = ALLSTANDARDRIGHT;
        $_SESSION['glpiactiveprofile'][Hash::$rightname]    = ALLSTANDARDRIGHT;

        $done = $this->transfer($account, $dest_id);

        $this->assertSame(MassiveAction::ACTION_OK, $done[$account->getID()]);
        $account->getFromDB($account->getID());
        $this->assertSame($dest_id, (int) $account->fields['entities_id']);
        // Bound to the destination fingerprint: its key opens it
        $this->assertSame($dest->getID(), (int) $account->fields['plugin_accounts_hashes_id']);
        $this->assertSame('secret-of-entity-1', AccountCrypto::decrypt($account->fields['encrypted_password'], self::DEST_KEY));
    }

    public function testAnAccountWithoutSecretIsStillTransferred(): void
    {
        $this->login();
        $source  = $this->createVault(getItemByTypeName(\Entity::class, '_test_child_1', true), self::SOURCE_KEY);
        $dest_id = getItemByTypeName(\Entity::class, '_test_child_2', true);
        /** @var Account $account */
        $account = $this->createItem(Account::class, [
            'name'                      => 'No secret',
            'entities_id'               => $source->fields['entities_id'],
            'plugin_accounts_hashes_id' => $source->getID(),
        ]);
        $_SESSION['glpiactiveprofile'][Account::$rightname] = ALLSTANDARDRIGHT;
        $_SESSION['glpiactiveprofile'][Hash::$rightname]    = READ;

        $done = $this->transfer($account, $dest_id);

        $this->assertSame(MassiveAction::ACTION_OK, $done[$account->getID()]);
        $account->getFromDB($account->getID());
        $this->assertSame($dest_id, (int) $account->fields['entities_id']);
    }

    // -------------------------------------------------------------------------
    // Former formats
    // -------------------------------------------------------------------------

    public function testAPbkdf2FingerprintOnlyAcceptsV4Records(): void
    {
        $verifier = AccountCrypto::makeVerifier(self::SOURCE_KEY);
        $legacy   = hash('sha256', hash('sha256', self::SOURCE_KEY));

        $v4 = AccountCrypto::encrypt('x', self::SOURCE_KEY, $verifier);
        $v3 = AccountCrypto::encrypt('x', self::SOURCE_KEY, '');

        $this->assertTrue(AccountCrypto::isAcceptedFor($v4, $verifier));
        $this->assertFalse(AccountCrypto::isAcceptedFor($v3, $verifier));
        // A fingerprint still on the former verifier keeps its records readable and writable
        $this->assertTrue(AccountCrypto::isAcceptedFor($v3, $legacy));
        // Clearing a secret is always allowed
        $this->assertTrue(AccountCrypto::isAcceptedFor('', $verifier));
    }

    public function testAPostedV3RecordIsRefusedOnAPbkdf2Fingerprint(): void
    {
        $this->login();
        // Key not stored server side: nothing can re-encrypt the posted record to v4
        /** @var Hash $hash */
        $hash = $this->createItem(Hash::class, [
            'name'         => 'Unstored key',
            'hash'         => AccountCrypto::makeVerifier(self::SOURCE_KEY),
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 0,
        ]);
        /** @var Account $account */
        $account = $this->createItem(Account::class, [
            'name'                      => 'Downgrade',
            'entities_id'               => $this->getTestRootEntity(true),
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);

        $input = $account->prepareInputForUpdate([
            'id'                 => $account->getID(),
            'encrypted_password' => AccountCrypto::encrypt('weak', self::SOURCE_KEY, ''),
        ]);

        $this->assertArrayNotHasKey('encrypted_password', $input);
        $this->hasSessionMessages(ERROR, ['The submitted secret uses a former encryption format and was ignored']);
    }

    public function testAPostedV3RecordIsUpgradedWhenTheKeyIsStored(): void
    {
        $this->login();
        $hash    = $this->createVault($this->getTestRootEntity(true), self::SOURCE_KEY);
        /** @var Account $account */
        $account = $this->createItem(Account::class, [
            'name'                      => 'Upgrade',
            'entities_id'               => $this->getTestRootEntity(true),
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);

        $input = $account->prepareInputForUpdate([
            'id'                 => $account->getID(),
            'encrypted_password' => AccountCrypto::encrypt('upgraded', self::SOURCE_KEY, ''),
        ]);

        $this->assertStringStartsWith(AccountCrypto::V4_PREFIX, $input['encrypted_password']);
        $this->assertSame('upgraded', AccountCrypto::decrypt($input['encrypted_password'], self::SOURCE_KEY));
    }

    public function testFormerRecordsAreCountedThenMigratedByARotationToTheSameKey(): void
    {
        $this->login();
        $hash    = $this->createVault($this->getTestRootEntity(true), self::SOURCE_KEY);
        /** @var Account $account */
        $account = $this->createItem(Account::class, [
            'name'                      => 'Former record',
            'entities_id'               => $this->getTestRootEntity(true),
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);
        // A record written before v4: straight into the table, update() would refuse it now
        global $DB;
        $DB->update(Account::getTable(), [
            'encrypted_password' => AccountCrypto::encrypt('kept', self::SOURCE_KEY, ''),
        ], ['id' => $account->getID()]);

        $this->assertSame(1, Hash::countLegacyRecords($hash->getID()));

        $this->assertTrue(Hash::updateHash(self::SOURCE_KEY, self::SOURCE_KEY, $hash->getID()));

        $this->assertSame(0, Hash::countLegacyRecords($hash->getID()));
        $account->getFromDB($account->getID());
        $this->assertStringStartsWith(AccountCrypto::V4_PREFIX, $account->fields['encrypted_password']);
        $this->assertSame('kept', AccountCrypto::decrypt($account->fields['encrypted_password'], self::SOURCE_KEY));
    }
}
