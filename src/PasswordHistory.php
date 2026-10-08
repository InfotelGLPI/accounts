<?php

/**
 * Password history for the Accounts plugin for GLPI.
 * Copyright (C) 2015-2026 by the accounts Development Team.
 * https://github.com/InfotelGLPI/accounts
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace GlpiPlugin\Accounts;

use DBConnection;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Migration;
use Session;

/**
 * Ciphertexts belong to an account and carry a snapshot of their key verifier.
 * Deliberately not a CommonDBTM: there is no independent CRUD/API entry point.
 */
final class PasswordHistory
{
    public const TABLE = 'glpi_plugin_accounts_passwordhistories';
    public const LIMIT = 5;

    public static function install(Migration $migration): void
    {
        global $DB;

        if ($DB->tableExists(self::TABLE)) {
            return;
        }

        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $key_sign = DBConnection::getDefaultPrimaryKeySignOption();
        $table = self::TABLE;
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int {$key_sign} NOT NULL AUTO_INCREMENT,
            `plugin_accounts_accounts_id` int {$key_sign} NOT NULL,
            `plugin_accounts_hashes_id` int {$key_sign} NOT NULL DEFAULT '0',
            `hash_name` varchar(255) NOT NULL DEFAULT '',
            `hash` varchar(255) NOT NULL DEFAULT '',
            `encrypted_password` text NOT NULL,
            `date_change` datetime NOT NULL,
            `users_id` int {$key_sign} NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `account_history` (`plugin_accounts_accounts_id`, `id`),
            KEY `plugin_accounts_hashes_id` (`plugin_accounts_hashes_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
        $DB->clearSchemaCache();
    }

    /** Return ciphertexts only after rechecking the parent's current access rules. */
    public static function getForAccount(Account $account): array
    {
        global $DB;

        // CommonDBTM::can() reuses fields on an already-loaded object. A fresh
        // object also catches ownership/entity changes made since it was loaded.
        $current = new Account();
        if ($account->getID() <= 0 || !$current->can($account->getID(), READ)) {
            throw new AccessDeniedHttpException();
        }

        return iterator_to_array($DB->request([
            'FROM' => self::TABLE,
            'WHERE' => ['plugin_accounts_accounts_id' => $account->getID()],
            'ORDER' => ['id DESC'],
            'LIMIT' => self::LIMIT,
        ]), false);
    }

    /** Called inside Account::updateInDB()'s transaction, using the locked DB row. */
    public static function record(array $previous): void
    {
        global $DB;

        if (empty($previous['encrypted_password'])) {
            return;
        }

        $hash = $DB->request([
            'FROM' => Hash::getTable(),
            'WHERE' => ['id' => (int) $previous['plugin_accounts_hashes_id']],
        ])->current();

        if (!$DB->insert(self::TABLE, [
            'plugin_accounts_accounts_id' => (int) $previous['id'],
            'plugin_accounts_hashes_id' => (int) $previous['plugin_accounts_hashes_id'],
            'hash_name' => (string) ($hash['name'] ?? ''),
            'hash' => (string) ($hash['hash'] ?? ''),
            'encrypted_password' => $previous['encrypted_password'],
            'date_change' => $_SESSION['glpi_currenttime'],
            'users_id' => (int) Session::getLoginUserID(),
        ])) {
            throw new \RuntimeException('Unable to store the password history.');
        }

        $ids = [];
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM' => self::TABLE,
            'WHERE' => ['plugin_accounts_accounts_id' => (int) $previous['id']],
            'ORDER' => ['id DESC'],
        ]) as $row) {
            $ids[] = (int) $row['id'];
        }
        $expired = array_slice($ids, self::LIMIT);
        if ($expired !== [] && !$DB->delete(self::TABLE, ['id' => $expired])) {
            throw new \RuntimeException('Unable to prune the password history.');
        }
    }

    /**
     * Revoke the former key for history as well as current secrets. Includes entries
     * of accounts that have since switched to another fingerprint. The caller owns
     * the transaction and must roll back on false, preserving all original values.
     */
    public static function rotate(int $hash_id, string $old_key, string $new_key, string $new_verifier): bool
    {
        global $DB;

        foreach ($DB->request([
            'FROM' => self::TABLE,
            'WHERE' => ['plugin_accounts_hashes_id' => $hash_id],
            'ORDER' => ['plugin_accounts_accounts_id', 'id'],
        ]) as $row) {
            // v1 has no MAC, so verify the key before accepting its plaintext.
            if (!AccountCrypto::verify($old_key, (string) $row['hash'])) {
                return false;
            }
            $plain = AccountCrypto::decrypt($row['encrypted_password'], $old_key);
            if ($plain === '') {
                return false;
            }
            if (!$DB->update(self::TABLE, [
                'encrypted_password' => AccountCrypto::encrypt($plain, $new_key, $new_verifier),
                'hash' => $new_verifier,
            ], ['id' => $row['id']])) {
                return false;
            }
        }

        return true;
    }
}
