<?php

namespace GlpiPlugin\Accounts\Tests;

use Computer;
use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\AccountCrypto;
use GlpiPlugin\Accounts\AesKey;
use GlpiPlugin\Accounts\Hash;
use Glpi\Tests\DbTestCase;

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

        $this->createItem(AesKey::class, [
            'plugin_accounts_hashes_id' => $hash->getID(),
            'name'                      => $fingerprint,
        ]);

        $plaintext = 'my-plain-password';
        $v1        = \GlpiPlugin\Accounts\AesCtr::encrypt($plaintext, $hash_value, 256);

        $account        = new Account();
        $account->fields = ['plugin_accounts_hashes_id' => $hash->getID()];
        $result         = $account->prepareInputForUpdate([
            'id'                 => 1,
            'encrypted_password' => $v1,
        ]);

        $this->assertStringStartsWith(AccountCrypto::V2_PREFIX, $result['encrypted_password']);

        $decrypted = AccountCrypto::decrypt($result['encrypted_password'], $fingerprint);
        $this->assertSame($plaintext, $decrypted);
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

        $account_item = new \GlpiPlugin\Accounts\Account_Item();
        $account_item->add([
            'plugin_accounts_accounts_id' => $account->getID(),
            'items_id'                    => $computer->getID(),
            'itemtype'                    => Computer::class,
        ]);

        $account->delete(['id' => $account->getID()], true);

        $remaining = countElementsInTable(
            'glpi_plugin_accounts_accounts_items',
            ['plugin_accounts_accounts_id' => $account->getID()]
        );
        $this->assertSame(0, $remaining);
    }
}
