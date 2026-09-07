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

use Computer;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\Account_Item;
use GlpiPlugin\Accounts\AccountCrypto;
use GlpiPlugin\Accounts\AesCtr;
use GlpiPlugin\Accounts\AesKey;
use GlpiPlugin\Accounts\Hash;
use GlpiPlugin\Accounts\Report;

class AccountTest extends DbTestCase
{
    public function testPrepareInputForAddSetsDateCreationWhenEmpty(): void
    {
        $this->login();

        $_SESSION['glpi_currenttime'] = '2024-01-15 10:00:00';

        $account = new Account();
        $result  = $account->prepareInputForAdd([
            'name'          => 'test',
            'date_creation' => '',
        ]);

        $this->assertSame('2024-01-15 10:00:00', $result['date_creation']);
    }

    public function testPrepareInputForAddSetsDateExpirationToNullWhenEmpty(): void
    {
        $this->login();

        $account = new Account();
        $result  = $account->prepareInputForAdd([
            'name'            => 'test',
            'date_expiration' => '',
        ]);

        $this->assertSame('NULL', $result['date_expiration']);
    }

    public function testPrepareInputForAddPreservesNonEmptyDates(): void
    {
        $this->login();

        $account = new Account();
        $result  = $account->prepareInputForAdd([
            'name'            => 'test',
            'date_creation'   => '2024-06-01',
            'date_expiration' => '2025-06-01',
        ]);

        $this->assertSame('2024-06-01', $result['date_creation']);
        $this->assertSame('2025-06-01', $result['date_expiration']);
    }

    public function testPrepareInputForUpdateBlanksPasswordWhenFlagSet(): void
    {
        $this->login();

        $account = new Account();
        $result  = $account->prepareInputForUpdate([
            'id'                     => 1,
            '_blank_account_passwd'  => true,
            'encrypted_password'     => 'something',
        ]);

        $this->assertSame('', $result['encrypted_password']);
    }

    public function testPrepareInputForUpdateKeepsPasswordWhenFlagAbsent(): void
    {
        $this->login();

        $account = new Account();
        $result  = $account->prepareInputForUpdate([
            'id'                 => 1,
            'encrypted_password' => 'kept-value',
        ]);

        $this->assertSame('kept-value', $result['encrypted_password']);
    }

    public function testPrepareInputForUpdateBlanksTotpSecretWhenFlagSet(): void
    {
        $this->login();

        $account = new Account();
        $result  = $account->prepareInputForUpdate([
            'id'                     => 1,
            '_blank_totp_secret'     => true,
            'encrypted_totp_secret'  => 'something',
        ]);

        $this->assertSame('', $result['encrypted_totp_secret']);
    }

    /**
     * The TOTP seed is encrypted by the browser like the password, so the cryptogram is under
     * the control of whoever posts the form. Replaying it with the MAC segment stripped would
     * turn an authenticated record back into a malleable AES-CTR blob.
     */
    public function testPrepareInputForUpdateRejectsUnauthenticatedTotpSecretDowngrade(): void
    {
        $this->login();

        $fingerprint = 'totp-downgrade-key';
        $verifier    = hash('sha256', hash('sha256', $fingerprint));

        $authenticated = AccountCrypto::encrypt('JBSWY3DPEHPK3PXP', $fingerprint, $verifier);
        $this->assertTrue(AccountCrypto::isAuthenticated($authenticated));

        $account         = new Account();
        $account->fields = ['encrypted_totp_secret' => $authenticated];

        $result = $account->prepareInputForUpdate([
            'id'                    => 1,
            'encrypted_totp_secret' => AesCtr::encrypt('JBSWY3DPEHPK3PXP', $fingerprint, 256),
        ]);

        $this->assertArrayNotHasKey('encrypted_totp_secret', $result);
        $this->hasSessionMessages(ERROR, ['The submitted TOTP secret is not integrity protected and was ignored']);
    }

    public function testPrepareInputForUpdateReEncryptsLegacyPasswordWhenAesKeyAvailable(): void
    {
        $this->login();

        $fingerprint = 'migration-key-abc';
        $hash_value  = hash('sha256', $fingerprint);

        $hash = $this->createItem(Hash::class, [
            'name'        => 'migration-hash',
            'hash'        => hash('sha256', $hash_value),
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);

        // AesKey stores its master key ('name') encrypted at rest via GLPIKey, so the
        // persisted value never equals the plaintext fingerprint: skip it in the check.
        $this->createItem(AesKey::class, [
            'plugin_accounts_hashes_id' => $hash->getID(),
            'name'                      => $fingerprint,
        ], ['name']);

        $plaintext = 'my-plain-password';
        $v1        = AesCtr::encrypt($plaintext, $hash_value, 256);

        $account        = new Account();
        $account->fields = ['plugin_accounts_hashes_id' => $hash->getID()];
        $result         = $account->prepareInputForUpdate([
            'id'                 => 1,
            'encrypted_password' => $v1,
        ]);

        // The hash was created with a legacy double SHA-256 verifier. Verifying the key against
        // it upgrades the verifier to PBKDF2 (rehash-on-login), and the very same save then has
        // the parameters to emit v4 rather than v3.
        $this->assertStringStartsWith(AccountCrypto::V4_PREFIX, $result['encrypted_password']);

        $decrypted = AccountCrypto::decrypt($result['encrypted_password'], $fingerprint);
        $this->assertSame($plaintext, $decrypted);

        $reloaded = new Hash();
        $reloaded->getFromDB($hash->getID());
        $this->assertStringStartsWith(AccountCrypto::VERIFIER_PREFIX, $reloaded->fields['hash']);
        $this->assertTrue(AccountCrypto::verify($fingerprint, $reloaded->fields['hash']));
    }

    /**
     * The report used to build its list on "the key parameter is not empty". On the CSV and PDF
     * paths that list is every cryptogram of the entity in a single download, so the announced
     * "you have to know the key" gate has to be a real comparison against the verifier.
     */
    public function testQueryAccountsListRefusesAWrongKey(): void
    {
        $this->login();

        $key = 'report-list-key';
        // queryAccountsList() intersects the sons of the fingerprint entity with the active
        // entities of the session, so the fixture has to live in one the session really holds:
        // anchored at the root it would be filtered out before the key is ever looked at, and
        // the empty list would then say nothing about the guard under test.
        $entity = (int) reset($_SESSION['glpiactiveentities']);
        $hash   = $this->createItem(Hash::class, [
            'name'         => 'report-list-hash',
            'hash'         => AccountCrypto::makeVerifier($key),
            'entities_id'  => $entity,
            'is_recursive' => 1,
        ]);

        $this->createItem(Account::class, [
            'name'                      => 'Listed Account',
            'entities_id'               => $entity,
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);

        $this->assertSame([], Report::queryAccountsList([
            'id'     => $hash->getID(),
            'aeskey' => 'not-the-key',
        ]));
        $this->hasSessionMessages(ERROR, ['Wrong encryption key']);

        $with_key = Report::queryAccountsList([
            'id'     => $hash->getID(),
            'aeskey' => $key,
        ]);
        $this->assertCount(1, $with_key);
        $this->assertSame('Listed Account', $with_key[0]['name']);
    }

    /**
     * A key rotation re-encrypts every account of the fingerprint. encrypt() only emits v4 when
     * the verifier travels with the key, so an omission there would silently rewrite the whole
     * vault with unsalted SHA-256 keys — a downgrade no error surfaces.
     */
    public function testUpdateHashKeepsRecordsInV4(): void
    {
        $this->login();

        $old_key = 'rotation-old-key';
        $new_key = 'rotation-new-key';

        $hash = $this->createItem(Hash::class, [
            'name'         => 'rotation-hash',
            'hash'         => AccountCrypto::makeVerifier($old_key),
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $account = $this->createItem(Account::class, [
            'name'                      => 'Rotation Test',
            'entities_id'               => 0,
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);
        // Written straight to the column: prepareInputForUpdate() would re-encrypt it itself.
        $this->updateItem(Account::class, $account->getID(), [
            'encrypted_password' => AccountCrypto::encrypt('rotate-me', $old_key, AccountCrypto::makeVerifier($old_key)),
        ], ['encrypted_password']);

        Hash::updateHash($old_key, $new_key, $hash->getID());

        $reloaded = new Account();
        $reloaded->getFromDB($account->getID());

        $this->assertStringStartsWith(AccountCrypto::V4_PREFIX, $reloaded->fields['encrypted_password']);
        $this->assertSame('rotate-me', AccountCrypto::decrypt($reloaded->fields['encrypted_password'], $new_key));
    }

    /**
     * A record that cannot be read with the former key must be left alone: encrypt('') would
     * produce a valid cryptogram of an empty string and destroy the password for good. And
     * because the former key is the only thing that still opens it, the rotation as a whole is
     * abandoned rather than committed around it.
     */
    public function testUpdateHashAbortsWhenARecordCannotBeDecrypted(): void
    {
        $this->login();

        $hash = $this->createItem(Hash::class, [
            'name'         => 'rotation-guard-hash',
            'hash'         => AccountCrypto::makeVerifier('guard-old-key'),
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // Encrypted under a key unrelated to the one the rotation will present.
        $foreign = AccountCrypto::encrypt('unreachable', 'some-other-key', '');

        $account = $this->createItem(Account::class, [
            'name'                      => 'Desynchronised',
            'entities_id'               => 0,
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);
        $this->updateItem(Account::class, $account->getID(), [
            'encrypted_password' => $foreign,
        ], ['encrypted_password']);

        $former_verifier = $hash->fields['hash'];

        $this->assertFalse(Hash::updateHash('guard-old-key', 'guard-new-key', $hash->getID()));

        $reloaded = new Account();
        $reloaded->getFromDB($account->getID());
        $this->assertSame($foreign, $reloaded->fields['encrypted_password']);

        // The verifier is what every future decryption is measured against: had it been written,
        // the accounts that did rotate would be fine and this one would be lost for good.
        $reloaded_hash = new Hash();
        $reloaded_hash->getFromDB($hash->getID());
        $this->assertSame($former_verifier, $reloaded_hash->fields['hash']);

        $this->hasSessionMessages(ERROR, [
            'The encryption key was not modified: the following accounts could not be read with the former key: Desynchronised',
        ]);
    }
    /**
     * The stored fingerprint is the verifier every key check is made against. The generic update
     * branch of front/hash.form.php hands the whole POST to update(), so nothing but
     * prepareInputForUpdate() stands between a holder of the UPDATE right and the substitution of
     * a verifier they know the key of -- which locks every legitimate holder out of the vault.
     */
    public function testVerifierCannotBeReplacedByAPlainUpdate(): void
    {
        $this->login();

        $key      = 'fingerprint-key';
        $verifier = AccountCrypto::makeVerifier($key);
        $hash     = $this->createItem(Hash::class, [
            'name'        => 'protected-verifier',
            'hash'        => $verifier,
            'entities_id' => 0,
        ]);

        $forged = AccountCrypto::makeVerifier('a-key-of-my-own');
        $hash->update(['id' => $hash->getID(), 'name' => 'renamed', 'hash' => $forged]);

        $reloaded = new Hash();
        $this->assertTrue($reloaded->getFromDB($hash->getID()));
        $this->assertSame($verifier, $reloaded->fields['hash']);
        // The rest of the update still goes through: only the verifier is pinned.
        $this->assertSame('renamed', $reloaded->fields['name']);
        $this->assertTrue(AccountCrypto::verify($key, $reloaded->fields['hash']));
    }

    /**
     * ... but the rotation, which only runs once the former key has been checked, must still be
     * able to move it. A pin that also blocks the legitimate writer would freeze every vault.
     */
    public function testRotationStillMovesTheVerifier(): void
    {
        $this->login();

        $hash = $this->createItem(Hash::class, [
            'name'        => 'rotating-verifier',
            'hash'        => AccountCrypto::makeVerifier('old-key'),
            'entities_id' => 0,
        ]);

        $this->assertTrue(Hash::updateHash('old-key', 'new-key', $hash->getID()));

        $reloaded = new Hash();
        $this->assertTrue($reloaded->getFromDB($hash->getID()));
        $this->assertTrue(AccountCrypto::verify('new-key', $reloaded->fields['hash']));
        $this->assertFalse(AccountCrypto::verify('old-key', $reloaded->fields['hash']));
    }

    /**
     * The pager and the export form carry no key at all. What queryAccountsList() leaves behind
     * once it has checked one is a marker saying it did -- and the export is gated on that.
     */
    public function testReportExportIsGatedOnAMarkerAndNotOnTheStoredKey(): void
    {
        $this->login();

        $key      = 'a-long-enough-key';
        $verifier = AccountCrypto::makeVerifier($key);
        $entity   = (int) reset($_SESSION['glpiactiveentities']);
        $hash     = $this->createItem(Hash::class, [
            'name'         => 'recall-hash',
            'hash'         => $verifier,
            'entities_id'  => $entity,
            'is_recursive' => 1,
        ]);
        $this->createItem(Account::class, [
            'name'                      => 'Exported Account',
            'entities_id'               => $entity,
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);

        // Nothing has been checked yet, so the export path is closed.
        $this->assertFalse(Report::hasVerifiedKey($hash->getID(), $verifier));
        $this->assertSame([], Report::queryAccountsList(['id' => $hash->getID()]));
        $this->hasSessionMessages(ERROR, ['The encryption key is no longer available, please display the list again before exporting it']);

        // Checking the key opens it...
        $this->assertCount(1, Report::queryAccountsList(['id' => $hash->getID(), 'aeskey' => $key]));
        $this->assertTrue(Report::hasVerifiedKey($hash->getID(), $verifier));
        // ... for a request that carries no key of its own, which is what the export form posts.
        $this->assertCount(1, Report::queryAccountsList(['id' => $hash->getID()]));

        // The point of the whole thing: the key is not what was kept.
        $kept = $_SESSION['plugin_accounts']['report_key'][$hash->getID()];
        $this->assertNotContains($key, $kept);
        $this->assertArrayNotHasKey('key', $kept);

        // A wrong key leaves no marker.
        $other = $this->createItem(Hash::class, [
            'name'         => 'recall-hash-other',
            'hash'         => AccountCrypto::makeVerifier('another-long-key'),
            'entities_id'  => $entity,
            'is_recursive' => 1,
        ]);
        Report::queryAccountsList(['id' => $other->getID(), 'aeskey' => 'not-the-key']);
        $this->hasSessionMessages(ERROR, ['Wrong encryption key']);
        $this->assertFalse(
            Report::hasVerifiedKey($other->getID(), (string) $other->fields['hash']),
        );

        // A rotation invalidates a marker left by a check against the former verifier.
        $this->assertFalse(
            Report::hasVerifiedKey($hash->getID(), AccountCrypto::makeVerifier('rotated-key')),
        );

        // And so does the elapsing of the window -- the marker is dropped, not merely refused.
        Report::queryAccountsList(['id' => $hash->getID(), 'aeskey' => $key]);
        $_SESSION['plugin_accounts']['report_key'][$hash->getID()]['verified_until'] = time() - 1;
        $this->assertFalse(Report::hasVerifiedKey($hash->getID(), $verifier));
        $this->assertArrayNotHasKey(
            $hash->getID(),
            $_SESSION['plugin_accounts']['report_key'],
        );
    }

    /**
     * The "accounts without fingerprint" banner used to count across the whole entity tree. The
     * query now carries the entity restriction, so it has to stay a valid query.
     */
    public function testShowAccountsWithoutHashIsScopedToTheEntity(): void
    {
        $this->login();

        $entity = (int) reset($_SESSION['glpiactiveentities']);
        $this->createItem(Account::class, [
            'name'                      => 'Orphan Account',
            'entities_id'               => $entity,
            'plugin_accounts_hashes_id' => 0,
        ]);

        ob_start();
        Account::showAccountsWithoutHash();
        $shown = (string) ob_get_clean();

        $this->assertStringContainsString('alert-warning', $shown);
    }

    public function testRegisterTypeAddsNewType(): void
    {
        $original_types = Account::$types;

        Account::registerType('MyCustomItemtype');

        $this->assertContains('MyCustomItemtype', Account::$types);

        Account::$types = $original_types;
    }

    public function testRegisterTypeDuplicateIsIgnored(): void
    {
        $original_types = Account::$types;

        Account::registerType('Computer');
        Account::registerType('Computer');

        $count = array_count_values(Account::$types)['Computer'] ?? 0;
        $this->assertSame(1, $count);

        Account::$types = $original_types;
    }

    public function testGetVisibilityCriteriaReturnsEmptyArrayForSeeAllRight(): void
    {
        $this->login('glpi', 'glpi');

        $criteria = Account::getVisibilityCriteria();

        $this->assertSame([], $criteria);
    }

    public function testAccountCanBeCreatedAndRetrieved(): void
    {
        $this->login();

        $hash = $this->createItem(Hash::class, [
            'name'         => 'crud-hash',
            'hash'         => hash('sha256', hash('sha256', 'crud-fingerprint')),
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $account = $this->createItem(Account::class, [
            'name'                      => 'CRUD Test Account',
            'entities_id'               => 0,
            'login'                     => 'testlogin',
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);

        $this->assertGreaterThan(0, $account->getID());
        $this->assertSame('CRUD Test Account', $account->getField('name'));
        $this->assertSame('testlogin', $account->getField('login'));
    }

    public function testCleanDBonPurgeRemovesAccountItems(): void
    {
        $this->login();

        $hash = $this->createItem(Hash::class, [
            'name'         => 'purge-hash',
            'hash'         => hash('sha256', hash('sha256', 'purge-fp')),
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $account = $this->createItem(Account::class, [
            'name'                      => 'Purge Test',
            'entities_id'               => 0,
            'plugin_accounts_hashes_id' => $hash->getID(),
        ]);

        $computer = $this->createItem(Computer::class, [
            'name'        => 'test-computer',
            'entities_id' => 0,
        ]);

        $account_item = new Account_Item();
        $account_item->add([
            'plugin_accounts_accounts_id' => $account->getID(),
            'items_id'                    => $computer->getID(),
            'itemtype'                    => Computer::class,
        ]);

        $account->delete(['id' => $account->getID()], true);

        $remaining = countElementsInTable(
            'glpi_plugin_accounts_accounts_items',
            ['plugin_accounts_accounts_id' => $account->getID()],
        );
        $this->assertSame(0, $remaining);
    }
}
