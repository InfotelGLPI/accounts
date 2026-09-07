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

namespace GlpiPlugin\Accounts;

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Html;
use Migration;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Hash
 */
class Hash extends CommonDBTM
{
    public static $rightname = "plugin_accounts_hash";

    public $dohistory = true;

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {

        return _n('Fingerprint', 'Fingerprints', $nb, 'accounts');
    }

    public static function getIcon()
    {
        return "ti ti-fingerprint";
    }

    /**
     * @return bool
     */
    public static function canCreate(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    /**
     * @return bool
     */
    public static function canView(): bool
    {
        return Session::haveRight(static::$rightname, READ);
    }

    /**
     * @param CommonGLPI $item
     * @param int        $withtemplate
     *
     * @return array|string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if (!$withtemplate) {
            switch ($item->getType()) {
                case __CLASS__:
                    $ong    = [];

                    $ong[2] = self::createTabEntry(__s('Linked accounts list', 'accounts'));
                    if (Session::haveRight(static::$rightname, UPDATE)) {
                        $ong[3] = self::createTabEntry(__s('Modification of the encryption key for all passwords', 'accounts'));
                    }

                    return $ong;
            }
        }
        return '';
    }

    /**
     * @param CommonGLPI $item
     * @param int        $tabnum
     * @param int        $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == __CLASS__) {
            $key = AesKey::checkIfAesKeyExists($item->getID());
            switch ($tabnum) {
                case 2:
                    // Serve the stored master key (auto-decrypt of every password) only to users
                    // allowed to manage the encryption key (plugin_accounts_hash UPDATE), mirroring
                    // Account::showForm. A plain READ user must type the key manually via
                    // showSelectAccountsList: otherwise they could read the master key from the page
                    // source and decrypt every account of the entity offline.
                    if (!$key || !Session::haveRight(static::$rightname, UPDATE)) {
                        self::showSelectAccountsList($item->getID());
                    } else {
                        $parm     = ["id" => $item->getID(),
                            "aeskey" => $key];
                        $accounts = Report::queryAccountsList($parm);
                        Report::showAccountsList($parm, $accounts);
                    }
                    break;
                case 3:
                    // The tab is only listed to users holding UPDATE, but a tab is no security
                    // boundary: ajax/common.tabs.php only checks can($id, READ), then hands the
                    // requested tab number straight to this method without ever checking it
                    // against getTabNameForItem. The guard has to be replayed here, before the
                    // form decrypts the master key of the entity.
                    if (!Session::haveRight(static::$rightname, UPDATE)) {
                        throw new AccessDeniedHttpException();
                    }
                    self::showHashChangeForm($item->getID());
                    break;
            }
        }
        return true;
    }

    /**
     * Provides search options configuration. Do not rely directly
     * on this, @return array a *not indexed* array of search options
     *
     * @since 9.3
     *
     * This should be overloaded in Class
     *
     * @see CommonDBTM::searchOptions instead.
     *
     * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
     **/
    public function rawSearchOptions()
    {
        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(2),
        ];

        $tab[] = [
            'id'            => '1',
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => __s('Name'),
            'datatype'      => 'itemlink',
            'itemlink_type' => Hash::class,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => '2',
            'table'         => $this->getTable(),
            'field'         => 'hash',
            'name'          => _n('Fingerprint', 'Fingerprints', 1, 'accounts'),
            'massiveaction' => false,
            // The fingerprint is a secret key verifier: never expose it as a
            // displayable/searchable column, otherwise it would leak through
            // the search engine and CSV/PDF exports.
            'nodisplay'     => true,
            'nosearch'      => true,
        ];

        $tab[] = [
            'id'       => '7',
            'table'    => $this->getTable(),
            'field'    => 'comment',
            'name'     => __s('Comments'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'       => '11',
            'table'    => $this->getTable(),
            'field'    => 'is_recursive',
            'name'     => __s('Child entities'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'            => '14',
            'table'         => $this->getTable(),
            'field'         => 'date_mod',
            'name'          => __s('Last update'),
            'massiveaction' => false,
            'datatype'      => 'datetime',
        ];

        $tab[] = [
            'id'       => '80',
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => __s('Entity'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id'       => '86',
            'table'    => $this->getTable(),
            'field'    => 'is_recursive',
            'name'     => __s('Child entities'),
            'datatype' => 'bool',
        ];

        return $tab;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab(AesKey::class, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    public function showForm($ID, $options = [])
    {

        if (!$this->canView()) {
            return false;
        }

        $restrict = getEntitiesRestrictCriteria(
            "glpi_plugin_accounts_hashes",
            '',
            '',
            $this->maybeRecursive(),
        );
        $nbhashes = countElementsInTable("glpi_plugin_accounts_hashes", $restrict);
        $alert = __s('Please do not use special characters like / \ apostrophe ampersand in encryption keys, or you cannot change it after.', 'accounts');

        // A verifier still in the pre-v4 format is an unsalted, non-iterated double SHA-256,
        // and it is handed to every holder of the read right by
        // ajax/getHashOnSelectEncryptionKey.php. upgradeHashVerifier() migrates one on the
        // first successful decryption, but nothing said which fingerprints were still
        // waiting, so the migration had no way of being finished.
        $stored_verifier   = (string) ($this->fields['hash'] ?? '');
        $is_legacy_verifier = $stored_verifier !== ''
            && !str_starts_with($stored_verifier, AccountCrypto::VERIFIER_PREFIX);

        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('@accounts/hash.html.twig', [
            'item' => $this,
            'nbhashes' => $nbhashes,
            'alertmsg' => $alert,
            'is_legacy_verifier' => $is_legacy_verifier,
            'params' => $options,
        ]);
        return true;
    }

    /**
     * Raised for the duration of the single write that is entitled to replace the verifier.
     *
     * The stored fingerprint IS the verifier: AccountCrypto::verify() interrogates it to decide
     * whether a typed key is the right one. The generic update branch of front/hash.form.php
     * used to hand it straight from the POST to update(), so a holder of the
     * plugin_accounts_hash UPDATE right -- a right meant for managing fingerprints, not for
     * holding the secrets -- could substitute the verifier of a key of their own without ever
     * knowing the one in force. The accounts stay encrypted under the former key, so the
     * legitimate holders are locked out of the whole vault of the entity, and everything
     * created afterwards resolves under the substituted key.
     *
     * @var bool
     */
    private bool $verifier_write_allowed = false;

    /**
     * Prepare input data for updating the item
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|false
     */
    public function prepareInputForUpdate($input)
    {
        if (
            array_key_exists('hash', $input)
            && !$this->verifier_write_allowed
            // An empty verifier is a fingerprint that never received one -- an interrupted
            // creation. There is nothing to protect, and no rotation could ever repair it
            // since updateHash() needs a verifier to check the former key against.
            && !empty($this->fields['hash'])
            // The form posts the field back unchanged (it is rendered read-only); refusing an
            // identical value would turn every ordinary save into a silent no-op.
            && $input['hash'] !== $this->fields['hash']
        ) {
            unset($input['hash']);
        }

        return $input;
    }

    /**
     * Prepare input data for adding the item
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {

        if (isset($input['hash']) && empty($input['hash'])) {
            $message = __s('You must generate the fingerprint for your encryption key', 'accounts');
            Session::addMessageAfterRedirect($message, false, ERROR);
            return false;
        }

        return $input;
    }

    /**
     * @param $ID
     */
    public static function showSelectAccountsList($ID)
    {

        $rand = mt_rand();
        TemplateRenderer::getInstance()->display('@accounts/hash_select_accounts.html.twig', [
            'hash_id'           => $ID,
            'rand'              => $rand,
            'root_accounts_doc' => PLUGIN_ACCOUNTS_WEBDIR,
        ]);

    }

    /**
     * @param $hash_id
     */
    public static function showHashChangeForm($hash_id)
    {
        // Defense in depth: this public static method hands out the master key of the entity in
        // clear text, so it guards itself rather than trusting each one of its call sites.
        if (!Session::haveRight(static::$rightname, UPDATE)) {
            throw new AccessDeniedHttpException();
        }

        $aesKey      = new AesKey();
        $current_key = '';

        if ($aesKey->getFromDBByCrit(['plugin_accounts_hashes_id' => $hash_id])
            && isset($aesKey->fields['name'])) {
            $current_key = $aesKey->getDecryptedName();
        }

        TemplateRenderer::getInstance()->display('@accounts/hash_change_key.html.twig', [
            'hash_id'        => $hash_id,
            'current_aeskey' => $current_key,
            'root_accounts_doc' => PLUGIN_ACCOUNTS_WEBDIR,
        ]);
    }

    /**
     * Rotate the master key of a fingerprint, all or nothing.
     *
     * Rotating is the revocation mechanism of the plugin: the new verifier and the new stored
     * key are what every future decryption is measured against. Writing them while some
     * records could not be rewritten used to leave those records permanently unreadable --
     * their cryptograms still belong to the former key, which nothing remembers any more.
     * The whole operation therefore runs inside a transaction and is rolled back as soon as
     * one record resists.
     *
     * @param string $oldaeskey Key currently in force
     * @param string $newaeskey Key to rotate to
     * @param int    $hash_id   Fingerprint being rotated
     * @return bool             True when the rotation was committed
     */
    public static function updateHash($oldaeskey, $newaeskey, $hash_id): bool
    {
        global $DB;

        $Hash = new self();
        $Hash->getFromDB($hash_id);

        $account = new Account();
        $aeskey  = new AesKey();

        // Salted, slow verifier for the new key (mirror of crypt.js). Replaces the former
        // fast double SHA-256, which was brute-forceable offline once disclosed to a user.
        $newhashstore = AccountCrypto::makeVerifier($newaeskey);
        // uncrypt passwords for update

        $criteria = [
            'SELECT' => '*',
            'FROM' => 'glpi_plugin_accounts_accounts',
            'WHERE' => ['plugin_accounts_hashes_id' => $hash_id],
        ];

        $iterator = $DB->request($criteria);

        $rotated_ids = [];
        $blocking    = [];

        // beginTransaction() opens a savepoint when one is already underway, so this stays
        // correct if a caller ever wraps the rotation in a transaction of its own.
        $DB->beginTransaction();

        if (count($iterator) > 0) {
            foreach ($iterator as $data) {
                $rotated_ids[] = (int) $data['id'];
                $oldpassword = AccountCrypto::decrypt($data['encrypted_password'], $oldaeskey);

                // decrypt() returns an empty string on failure (invalid MAC, damaged cryptogram,
                // record encrypted under a key other than the one attached to this hash). Feeding
                // that to encrypt() would produce a perfectly valid cryptogram of an empty string
                // and overwrite the original: the password would be destroyed for good. The record
                // is left alone and the rotation as a whole is abandoned below -- keeping the old
                // key in force is the only thing that keeps this cryptogram readable.
                if ($oldpassword === '' && !empty($data['encrypted_password'])) {
                    $blocking[] = $data['name'];
                    continue;
                }

                // The verifier must travel with the key: without it encrypt() falls back to v3,
                // whose encryption key is a bare unsalted SHA-256 of the fingerprint. Omitting it
                // here would rewrite every v4 record of the hash in the weaker format.
                $update = [
                    'id'                 => $data["id"],
                    'encrypted_password' => AccountCrypto::encrypt($oldpassword, $newaeskey, $newhashstore),
                ];
                // Re-encrypt TOTP secret if present
                if (!empty($data['encrypted_totp_secret'])) {
                    $oldtotp = AccountCrypto::decrypt($data['encrypted_totp_secret'], $oldaeskey);
                    if ($oldtotp === '') {
                        // Same reasoning as the password above. This branch used to drop the already
                        // re-encrypted $update['encrypted_password'] on the floor; there is nothing
                        // left to drop now that the rotation is abandoned as a whole.
                        $blocking[] = $data['name'];
                        continue;
                    }
                    $update['encrypted_totp_secret'] = AccountCrypto::encrypt($oldtotp, $newaeskey, $newhashstore);
                }
                $account->update($update);
            }
        }
        if ($blocking !== []) {
            $DB->rollBack();
            Session::addMessageAfterRedirect(
                sprintf(
                    __s(
                        'The encryption key was not modified: the following accounts could not be read with the former key: %1$s',
                        'accounts',
                    ),
                    htmlescape(implode(', ', array_unique($blocking))),
                ),
                false,
                ERROR,
            );

            return false;
        }

        // Rotating the key is the revocation mechanism of the plugin, and it only revokes
        // what it can rewrite. Before $history_blacklist was set, every cryptogram of the TOTP
        // secret was copied into glpi_logs (search option 31) under the key in force at the
        // time, where the former holder of the key can still read it. Those rows are the one
        // place the old key keeps working, so the rotation has to clear them.
        if ($rotated_ids !== []) {
            $DB->delete('glpi_logs', [
                'itemtype'         => Account::class,
                'items_id'         => $rotated_ids,
                'id_search_option' => 31,
            ]);
        }

        // The one path entitled to move the verifier: it only runs once front/hash.form.php has
        // confirmed the former key against the stored one, and every account has just been
        // re-encrypted under the new one inside this transaction.
        $Hash->verifier_write_allowed = true;
        $Hash->update(['id' => $hash_id, 'hash' => $newhashstore]);
        $Hash->verifier_write_allowed = false;

        if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id'  => $hash_id]) && isset($aeskey->fields["name"])) {
            $values["id"]   = $aeskey->fields["id"];
            $values["name"] = $newaeskey;
            $aeskey->update($values);
        }

        $DB->commit();

        return true;
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
                        `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                        `entities_id` int unsigned NOT NULL default '0',
                        `is_recursive` tinyint NOT NULL default '0',
                        `hash` varchar(255) collate utf8mb4_unicode_ci default NULL,
                        `comment` text collate utf8mb4_unicode_ci,
                        `date_mod` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY  (`id`),
                        KEY `entities_id` (`entities_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
