<?php

/**
 * Accounts password history regression tests — GPL-3.0-or-later.
 */

namespace GlpiPlugin\Accounts\Tests;

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\AccountCrypto;
use GlpiPlugin\Accounts\AesCtr;
use GlpiPlugin\Accounts\Hash;
use GlpiPlugin\Accounts\PasswordHistory;
use Migration;
use Session;

class PasswordHistoryTest extends DbTestCase
{
    private const KEY = 'history-original-key';
    private string $verifier;
    private Hash $hash;
    private Account $account;
    private int $entity;

    public function setUp(): void
    {
        parent::setUp();
        $this->login();
        $this->entity = (int) reset($_SESSION['glpiactiveentities']);
        $_SESSION['glpiactiveprofile']['plugin_accounts'] = ALLSTANDARDRIGHT;
        $_SESSION['glpiactiveprofile']['plugin_accounts_hash'] = ALLSTANDARDRIGHT;
        $_SESSION['glpiactiveprofile']['plugin_accounts_see_all_users'] = READ;
        PasswordHistory::install(new Migration('test'));

        $this->verifier = AccountCrypto::makeVerifier(self::KEY);
        $this->hash = $this->createItem(Hash::class, [
            'name' => 'History key',
            'hash' => $this->verifier,
            'entities_id' => $this->entity,
            'is_recursive' => 1,
        ]);
        $this->account = $this->createItem(Account::class, [
            'name' => 'History account',
            'entities_id' => $this->entity,
            'users_id' => Session::getLoginUserID(),
            'plugin_accounts_hashes_id' => $this->hash->getID(),
            'encrypted_password' => $this->encrypt('initial'),
        ], ['encrypted_password']);
    }

    private function encrypt(string $plain): string
    {
        return AccountCrypto::encrypt($plain, self::KEY, $this->verifier);
    }

    private function replace(string $plain): void
    {
        $this->assertTrue($this->account->update([
            'id' => $this->account->getID(),
            'encrypted_password' => $this->encrypt($plain),
        ]));
    }

    private function entries(): array
    {
        return PasswordHistory::getForAccount($this->account);
    }

    public function testOnlyTheFiveMostRecentReplacedPasswordsAreKept(): void
    {
        $this->assertSame([], $this->entries());
        for ($i = 1; $i <= 8; $i++) {
            $this->replace('password-' . $i);
        }
        $entries = $this->entries();
        $this->assertCount(5, $entries);
        $this->assertSame(
            ['password-7', 'password-6', 'password-5', 'password-4', 'password-3'],
            array_map(static fn($entry) => AccountCrypto::decrypt($entry['encrypted_password'], self::KEY), $entries),
        );
        $this->assertSame($_SESSION['glpi_currenttime'], $entries[0]['date_change']);
        $this->assertSame((int) Session::getLoginUserID(), (int) $entries[0]['users_id']);
    }

    public function testAnUnchangedPasswordAndMetadataUpdatesDoNotCreateHistory(): void
    {
        $this->assertTrue($this->account->update([
            'id' => $this->account->getID(),
            'name' => 'Renamed account',
            'encrypted_password' => $this->account->fields['encrypted_password'],
        ]));
        $this->assertSame([], $this->entries());
    }

    public function testClearingArchivesThePreviousPasswordAndRefillingDoesNotArchiveEmptyValues(): void
    {
        $this->assertTrue($this->account->update([
            'id' => $this->account->getID(), '_blank_account_passwd' => 1,
        ]));
        $this->assertSame('', $this->account->fields['encrypted_password']);
        $this->replace('replacement');
        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $this->assertSame('initial', AccountCrypto::decrypt($entries[0]['encrypted_password'], self::KEY));
    }

    public function testPostedFlagsCannotDisablePasswordHistory(): void
    {
        $this->assertTrue($this->account->update([
            'id' => $this->account->getID(),
            'encrypted_password' => $this->encrypt('new'),
            '_no_history' => 1, '_password_reencryption' => 1,
            'password_reencryption' => true, 'password_format_upgrade' => true,
        ], false));
        $this->assertCount(1, $this->entries());
    }

    public function testEachEntryKeepsTheVerifierOfItsOwnKeyEvenAfterFingerprintDeletion(): void
    {
        global $DB;

        $other_key = 'history-other-key';
        $other_verifier = AccountCrypto::makeVerifier($other_key);
        $other_hash = $this->createItem(Hash::class, [
            'name' => 'Other history key', 'hash' => $other_verifier,
            'entities_id' => $this->entity, 'is_recursive' => 1,
        ]);
        $this->assertTrue($this->account->update([
            'id' => $this->account->getID(),
            'plugin_accounts_hashes_id' => $other_hash->getID(),
            'encrypted_password' => AccountCrypto::encrypt('other-password', $other_key, $other_verifier),
        ]));
        $this->assertTrue($DB->delete(Hash::getTable(), ['id' => $this->hash->getID()]));
        $entry = $this->entries()[0];
        $this->assertSame($this->verifier, $entry['hash']);
        $this->assertSame('History key', $entry['hash_name']);
        $this->assertSame('initial', AccountCrypto::decrypt($entry['encrypted_password'], self::KEY));
        $this->assertSame('', AccountCrypto::decrypt($entry['encrypted_password'], $other_key));
    }

    public function testRotationReencryptsHistoryWithoutCreatingExtraEntries(): void
    {
        $this->replace('current');
        $this->assertTrue(Hash::updateHash(self::KEY, 'history-rotated-key', $this->hash->getID()));
        $entry = $this->entries()[0];
        $this->assertCount(1, $this->entries());
        $this->assertTrue(AccountCrypto::verify('history-rotated-key', $entry['hash']));
        $this->assertSame('initial', AccountCrypto::decrypt($entry['encrypted_password'], 'history-rotated-key'));
        $this->assertSame('', AccountCrypto::decrypt($entry['encrypted_password'], self::KEY));
        $this->account->getFromDB($this->account->getID());
        $this->assertSame('current', AccountCrypto::decrypt($this->account->fields['encrypted_password'], 'history-rotated-key'));
    }

    public function testRotationAlsoFindsHistoryOfAccountsNowUsingAnotherFingerprint(): void
    {
        $other_key = 'history-other-key';
        $other_verifier = AccountCrypto::makeVerifier($other_key);
        $other_hash = $this->createItem(Hash::class, [
            'name' => 'Destination', 'hash' => $other_verifier,
            'entities_id' => $this->entity, 'is_recursive' => 1,
        ]);
        $this->assertTrue($this->account->update([
            'id' => $this->account->getID(), 'plugin_accounts_hashes_id' => $other_hash->getID(),
            'encrypted_password' => AccountCrypto::encrypt('new', $other_key, $other_verifier),
        ]));
        $this->assertTrue(Hash::updateHash(self::KEY, 'history-rotated-key', $this->hash->getID()));
        $this->assertSame('initial', AccountCrypto::decrypt($this->entries()[0]['encrypted_password'], 'history-rotated-key'));
        $this->assertSame('new', AccountCrypto::decrypt($this->account->fields['encrypted_password'], $other_key));
    }

    public function testADamagedHistoryEntryRollsBackTheWholeRotation(): void
    {
        global $DB;

        $this->replace('second');
        $this->replace('current');
        $entries = $this->entries();
        $DB->update(PasswordHistory::TABLE, [
            'encrypted_password' => AccountCrypto::encrypt('foreign', 'wrong-history-key', ''),
        ], ['id' => $entries[0]['id']]);
        $before = $this->entries();
        $current = $this->account->fields['encrypted_password'];
        $this->assertFalse(Hash::updateHash(self::KEY, 'history-rotated-key', $this->hash->getID()));
        $this->hasSessionMessages(ERROR, [__('The encryption key was not modified: a password history entry could not be decrypted.', 'accounts')]);
        $this->assertSame($before, $this->entries());
        $this->hash->getFromDB($this->hash->getID());
        $this->assertSame($this->verifier, $this->hash->fields['hash']);
        $this->account->getFromDB($this->account->getID());
        $this->assertSame($current, $this->account->fields['encrypted_password']);
    }

    public function testPasswordAndHistoryShareTheCallersTransaction(): void
    {
        global $DB;

        $DB->beginTransaction();
        try {
            $this->replace('temporary');
            $this->assertCount(1, $this->entries());
        } finally {
            $DB->rollBack();
        }
        $this->assertSame([], $this->entries());
        $this->account->getFromDB($this->account->getID());
        $this->assertSame('initial', AccountCrypto::decrypt($this->account->fields['encrypted_password'], self::KEY));
    }

    public function testLegacyFormatUpgradeDoesNotArchiveAnUnchangedPassword(): void
    {
        global $DB;

        $legacy = AesCtr::encrypt('initial', hash('sha256', self::KEY), 256);
        $DB->update(Account::getTable(), ['encrypted_password' => $legacy], ['id' => $this->account->getID()]);
        $this->assertTrue($this->account->update([
            'id' => $this->account->getID(), 'encrypted_password' => $legacy, 'aeskey' => self::KEY,
        ]));
        $this->assertStringStartsWith(AccountCrypto::V4_PREFIX, $this->account->fields['encrypted_password']);
        $this->assertSame([], $this->entries());
    }

    public function testHistoryIsNotCopiedIntoGeneralGLPILogs(): void
    {
        global $DB;

        $previous = $this->account->fields['encrypted_password'];
        $this->replace('current');
        foreach ($DB->request([
            'FROM' => 'glpi_logs',
            'WHERE' => ['itemtype' => Account::class, 'items_id' => $this->account->getID()],
        ]) as $row) {
            $this->assertStringNotContainsString($previous, (string) $row['old_value']);
            $this->assertStringNotContainsString($previous, (string) $row['new_value']);
        }
    }

    public function testAReaderCannotRetrieveTheHistoryOfAnotherOwnersAccount(): void
    {
        global $DB;

        $this->replace('current');
        $DB->update(Account::getTable(), ['users_id' => 0, 'users_id_tech' => 0], ['id' => $this->account->getID()]);
        $_SESSION['glpiactiveprofile']['plugin_accounts_see_all_users'] = 0;
        $_SESSION['glpiactiveprofile']['plugin_accounts_my_groups'] = 0;
        $this->expectException(AccessDeniedHttpException::class);
        $this->entries();
    }

    public function testReadRightIsRequiredEvenWhenTheEncryptionKeyIsKnown(): void
    {
        $this->replace('current');
        $_SESSION['glpiactiveprofile']['plugin_accounts'] = 0;
        $this->expectException(AccessDeniedHttpException::class);
        $this->entries();
    }

    public function testEntityRestrictionsAlsoApplyToHistory(): void
    {
        $this->replace('current');
        $_SESSION['glpiactiveentities'] = [];
        $this->expectException(AccessDeniedHttpException::class);
        $this->entries();
    }

    public function testRejectedCiphertextDoesNotCreateHistory(): void
    {
        $this->assertTrue($this->account->update([
            'id' => $this->account->getID(),
            'name' => 'Renamed account', 'encrypted_password' => 'unauthenticated-ciphertext',
        ]));
        $this->hasSessionMessages(ERROR, [__('The submitted password is not integrity protected and was ignored', 'accounts')]);
        $this->assertSame([], $this->entries());
        $this->assertSame('initial', AccountCrypto::decrypt($this->account->fields['encrypted_password'], self::KEY));
    }

    public function testInstallationIsIdempotentAndPreservesExistingHistory(): void
    {
        $this->replace('current');
        $before = $this->entries();
        PasswordHistory::install(new Migration('test'));
        $this->assertSame($before, $this->entries());
    }

    public function testHistoryPanelRendersMaskedFieldsWithoutPostingKeysOrPlaintext(): void
    {
        $this->replace('current');
        $entry = $this->entries()[0];
        ob_start();
        try {
            $this->assertTrue($this->account->showForm($this->account->getID()));
        } finally {
            $html = ob_get_clean();
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query('//details[@data-history-id]');
        $this->assertCount(1, $rows);
        $this->assertSame($entry['encrypted_password'], $rows->item(0)->getAttribute('data-ciphertext'));
        $this->assertSame($entry['hash'], $rows->item(0)->getAttribute('data-verifier'));
        $this->assertCount(0, $xpath->query('//input[contains(@class,"history-key") or contains(@class,"history-password")][@name]'));
        $this->assertCount(1, $xpath->query('//input[contains(@class,"history-password")][@type="password"][@readonly]'));
        $this->assertCount(1, $xpath->query('//input[@id="hidden_password"]/ancestor::div[contains(@class,"field-container")][1]//details[@data-account-id]'));
        $this->assertStringNotContainsString('value="initial"', $html);
    }

    public function testTrashAndRestorePreserveHistoryAndPurgeDeletesIt(): void
    {
        global $DB;

        $this->replace('current');
        $id = $this->account->getID();
        $this->assertTrue($this->account->delete(['id' => $id]));
        $this->assertTrue($this->account->restore(['id' => $id]));
        $this->assertCount(1, $this->entries());
        $this->assertTrue($this->account->delete(['id' => $id], true));
        $this->assertCount(0, $DB->request([
            'FROM' => PasswordHistory::TABLE, 'WHERE' => ['plugin_accounts_accounts_id' => $id],
        ]));
    }
}
