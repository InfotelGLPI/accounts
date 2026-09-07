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

use Ajax;
use Alert;
use Change_Item;
use CommonDBTM;
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;
use Glpi\DBAL\QuerySubQuery;
use Glpi\Features\Clonable;
use Html;
use Item_Problem;
use Item_Project;
use Location;
use MassiveAction;
use Migration;
use NotificationEvent;
use Plugin;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Account
 */
class Account extends CommonDBTM
{
    /** @use Clonable<static> */
    use Clonable;

    public static $rightname = "plugin_accounts";

    /**
     * Verifiers read from glpi_plugin_accounts_hashes, memoized per request.
     *
     * @var array<int, string>
     */
    private static array $verifier_cache = [];

    public static $types = [
        'Computer',
        'Monitor',
        'NetworkEquipment',
        'Peripheral',
        'Phone',
        'Printer',
        'Software',
        'SoftwareLicense',
        'Entity',
        'Contract',
        'Supplier',
        'Certificate',
        'Cluster',
    ];

    public $dohistory = true;

    /**
     * Log::constructHistory() records the former and the new value of every changed field
     * that owns a search option, and encrypted_totp_secret owns option 31 -- its nosearch
     * and nodisplay flags only concern the search engine, not the history. Every cryptogram
     * ever written would therefore survive in glpi_logs under the key it was produced with,
     * which Hash::updateHash() cannot rewrite: rotating the master key would stop revoking
     * access to the TOTP seeds. encrypted_password is listed too, so that adding a search
     * option to it later cannot open the same hole by accident.
     */
    public $history_blacklist = ['encrypted_totp_secret', 'encrypted_password'];
    protected $usenotepad = true;

    /**
     * Return the localized name of the current Type
     *
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Account', 'Accounts', $nb, 'accounts');
    }

    public static function getIcon()
    {
        return "ti ti-lock";
    }

    public function getCloneRelations(): array
    {
        return [];
    }

    /**
     * Actions done when item is deleted from the database
     */
    public function cleanDBonPurge()
    {
        $temp = new Account_Item();
        $temp->deleteByCriteria(['plugin_accounts_accounts_id' => $this->fields['id']]);

        $ip = new Item_Problem();
        $ip->cleanDBonItemDelete(__CLASS__, $this->fields['id']);

        $ci = new Change_Item();
        $ci->cleanDBonItemDelete(__CLASS__, $this->fields['id']);

        $ip = new Item_Project();
        $ip->cleanDBonItemDelete(__CLASS__, $this->fields['id']);
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
            'id' => 'common',
            'name' => self::getTypeName(2),
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
                'id' => '1',
                'table' => $this->getTable(),
                'field' => 'name',
                'name' => __s('Name'),
                'datatype' => 'itemlink',
                'itemlink_type' => Account::class,
                'massiveaction' => false,
                'searchtype' => 'contains',
            ];
        } else {
            $tab[] = [
                'id' => '1',
                'table' => $this->getTable(),
                'field' => 'name',
                'name' => __s('Name'),
                'datatype' => 'itemlink',
                'itemlink_type' => Account::class,
                'massiveaction' => false,
            ];
        }

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
                'id' => '2',
                'table' => 'glpi_plugin_accounts_accounttypes',
                'field' => 'name',
                'name' => __s('Type'),
                'datatype' => 'dropdown',
                'searchtype' => 'contains',
            ];
        } else {
            $tab[] = [
                'id' => '2',
                'table' => 'glpi_plugin_accounts_accounttypes',
                'field' => 'name',
                'name' => __s('Type'),
                'datatype' => 'dropdown',
            ];
        }

        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab[] = [
            'id' => '4',
            'table' => $this->getTable(),
            'field' => 'login',
            'name' => __s('Login'),
        ];

        $tab[] = [
            'id' => '5',
            'table' => $this->getTable(),
            'field' => 'date_creation',
            'name' => __s('Creation date'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '6',
            'table' => $this->getTable(),
            'field' => 'date_expiration',
            'name' => __('Expiration date'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '7',
            'table' => $this->getTable(),
            'field' => 'comment',
            'name' => __s('Comments'),
            'datatype' => 'text',
        ];

        if (Session::getCurrentInterface() == 'central') {
            $tab[] = [
                'id' => 8,
                'table' => 'glpi_plugin_accounts_accounts_items',
                'field' => 'items_id',
                'nosearch' => true,
                'name' => _n('Associated item', 'Associated items', 2),
                'forcegroupby' => true,
                'massiveaction' => false,
                'joinparams' => ['jointype' => 'child'],
            ];
        }

        $tab[] = [
            'id' => '9',
            'table' => $this->getTable(),
            'field' => 'others',
            'name' => __s('Others'),
        ];

        $tab[] = [
            'id'              => '31',
            'table'           => $this->getTable(),
            'field'           => 'encrypted_totp_secret',
            'name'            => __s('TOTP Secret', 'accounts'),
            'massiveaction'   => false,
            'nosearch'        => true,
            'nodisplay'       => true,
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
                'id' => '10',
                'table' => 'glpi_plugin_accounts_accountstates',
                'field' => 'name',
                'name' => __s('Status'),
                'searchtype' => 'contains',
            ];
        } else {
            $tab[] = [
                'id' => '10',
                'table' => 'glpi_plugin_accounts_accountstates',
                'field' => 'name',
                'name' => __s('Status'),
                'datatype' => 'dropdown',
            ];
        }

        if (Session::getCurrentInterface() == 'central') {
            $tab[] = [
                'id' => 11,
                'table' => $this->getTable(),
                'field' => 'is_recursive',
                'name' => __s('Child entities'),
                'datatype' => 'bool',
            ];
        }

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
                'id' => '12',
                'table' => 'glpi_groups',
                'field' => 'completename',
                'name' => __s('Group'),
                'datatype' => 'dropdown',
                'condition' => ['`is_itemgroup`' => 1],
                'searchtype' => 'contains',
            ];
        } else {
            $tab[] = [
                'id' => '12',
                'table' => 'glpi_groups',
                'field' => 'completename',
                'name' => __s('Group'),
                'datatype' => 'dropdown',
                'condition' => ['`is_itemgroup`' => 1],
            ];
        }

        if (Session::getCurrentInterface() == 'central') {
            $tab[] = [
                'id' => 13,
                'table' => $this->getTable(),
                'field' => 'is_helpdesk_visible',
                'name' => __s('Associable to a ticket'),
                'datatype' => 'bool',
            ];
        }

        $tab[] = [
            'id' => '14',
            'table' => $this->getTable(),
            'field' => 'date_mod',
            'name' => __s('Last update'),
            'massiveaction' => false,
            'datatype' => 'datetime',
        ];

        if (Session::getCurrentInterface() == 'central') {
            $tab[] = [
                'id' => '15',
                'table' => 'glpi_plugin_accounts_hashes',
                'field' => 'name',
                'name' => _n('Fingerprint', 'Fingerprints', 1, 'accounts'),
                'datatype' => 'dropdown',
            ];
        }
        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
                'id' => '16',
                'table' => 'glpi_users',
                'field' => 'name',
                'name' => __s('Affected User', 'accounts'),
                'searchtype' => 'contains',
            ];
        } else {
            $tab[] = [
                'id' => '16',
                'table' => 'glpi_users',
                'field' => 'name',
                'name' => __s('Affected User', 'accounts'),
            ];
        }

        $tab[] = [
            'id' => '17',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_tech',
            'name' => __s('Technician in charge'),
            'datatype' => 'dropdown',
            'right' => 'interface',
        ];

        $tab[] = [
            'id' => '18',
            'table' => 'glpi_groups',
            'field' => 'completename',
            'linkfield' => 'groups_id_tech',
            'name' => __s('Group in charge'),
            'condition' => ['`is_assign`' => 1],
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '30',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __s('ID'),
            'datatype' => 'number',
        ];
        if (Session::getCurrentInterface() == 'central') {
            $tab[] = [
                'id' => '50',
                'table' => 'glpi_plugin_accounts_accounts_items',
                'field' => 'itemtype',
                'name'               => __('Item type'),
                'massiveaction'      => false,
                'datatype'           => 'itemtypename',
                'types'              => Account::getTypes(true),
            ];
        }

        $tab[] = [
            'id' => '81',
            'table' => 'glpi_entities',
            'field' => 'entities_id',
            'name' => __s('Entity-ID'),
        ];

        $tab[] = [
            'id' => '80',
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __s('Entity'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '86',
            'table' => $this->getTable(),
            'field' => 'is_recursive',
            'name' => __s('Child entities'),
            'datatype' => 'bool',
        ];

        return $tab;
    }

    /**
     * Define tabs to display
     *
     * NB : Only called for existing object
     *
     * @param $options array
     *     - withtemplate is a template view ?
     *
     * @return array containing the tabs
     **/
    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);

        $this->addImpactTab($ong, $options);
        if (Session::getCurrentInterface() == 'central') {
            $this->addStandardTab(Account_Item::class, $ong, $options);
            $this->addStandardTab('Item_Ticket', $ong, $options);
            $this->addStandardTab('Item_Problem', $ong, $options);
            //$this->addStandardTab('Change_Item', $ong, $options);
            $this->addStandardTab('Item_Project', $ong, $options);
            $this->addStandardTab('Document_Item', $ong, $options);
            $this->addStandardTab('Notepad', $ong, $options);
            $this->addStandardTab('Log', $ong, $options);
        }

        return $ong;
    }

    /**
     * Prepare input datas for adding the item
     *
     * @param  $input
     *
     * @return $input
     */
    public function prepareInputForAdd($input)
    {
        if (isset($input['date_creation']) && empty($input['date_creation'])) {
            $input['date_creation'] = $_SESSION["glpi_currenttime"];
        }
        if (isset($input['date_expiration']) && empty($input['date_expiration'])) {
            $input['date_expiration'] = 'NULL';
        }

        // Guard: warn if plaintext password is suspiciously long before encryption
        // The JS client encrypts the password, but this checks the stored ciphertext length
        if (isset($input['encrypted_password']) && strlen($input['encrypted_password']) > 4000) {
            Session::addMessageAfterRedirect(
                __s(
                    'Warning: the encrypted password is very long and may indicate an issue with the encryption key.',
                    'accounts',
                ),
                false,
                WARNING,
            );
        }

        // Unlike the update path this does not require plugin_accounts_hash UPDATE: a user
        // with CREATE alone has to be able to pick the fingerprint of the new account, and
        // stripping the value here would create an account whose password can never be read.
        // The entity boundary is what is missing, and refusing beats silently unsetting: a
        // hash-less account is a broken one.
        if (isset($input['plugin_accounts_hashes_id'])
            && (int) $input['plugin_accounts_hashes_id'] > 0
            && !self::isHashReachable((int) $input['plugin_accounts_hashes_id'])) {
            Session::addMessageAfterRedirect(
                __('The selected fingerprint does not belong to an entity you can access', 'accounts'),
                false,
                ERROR,
            );
            return false;
        }

        return $input;
    }

    /**
     * Actions done after the ADD of the item in the database
     *
     **/
    public function post_addItem()
    {
        global $CFG_GLPI;

        if ($CFG_GLPI["notifications_mailing"]) {
            NotificationEvent::raiseEvent("new", $this);
        }
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function prepareInputForUpdate($input)
    {
        if (isset($input['date_creation']) && empty($input['date_creation'])) {
            $input['date_creation'] = 'NULL';
        }
        if (isset($input['date_expiration']) && empty($input['date_expiration'])) {
            $input['date_expiration'] = 'NULL';
        }

        if (isset($input["_blank_account_passwd"]) && $input["_blank_account_passwd"]) {
            $input['encrypted_password'] = '';
        }

        // Clear TOTP secret if requested
        if (isset($input["_blank_totp_secret"]) && $input["_blank_totp_secret"]) {
            $input['encrypted_totp_secret'] = '';
        }

        if (isset($input["plugin_accounts_hashes_id"])
            && !Session::haveRight('plugin_accounts_hash', UPDATE)) {
            unset($input['plugin_accounts_hashes_id']);
        }
        // The right alone says nothing about the entity: it can be granted recursively or
        // globally, so a holder of it could still move an account onto the fingerprint of an
        // entity they cannot reach. Dropping the value leaves the account on its current one.
        if (isset($input["plugin_accounts_hashes_id"])
            && (int) $input['plugin_accounts_hashes_id'] > 0
            && !self::isHashReachable((int) $input['plugin_accounts_hashes_id'])) {
            Session::addMessageAfterRedirect(
                __('The selected fingerprint does not belong to an entity you can access', 'accounts'),
                false,
                ERROR,
            );
            unset($input['plugin_accounts_hashes_id']);
        }
        // Downgrade guard: the ciphertext is built by the browser and posted as-is, so an
        // authenticated record could be replaced by an unauthenticated one carrying the same
        // key (MAC segment simply dropped). AES-CTR being malleable, that would silently
        // give up integrity on the stored password. Never accept that transition.
        if (isset($input['encrypted_password']) && !empty($input['encrypted_password'])
            && !AccountCrypto::isAuthenticated($input['encrypted_password'])
            && AccountCrypto::isAuthenticated((string) ($this->fields['encrypted_password'] ?? ''))
        ) {
            Session::addMessageAfterRedirect(
                __('The submitted password is not integrity protected and was ignored', 'accounts'),
                false,
                ERROR,
            );
            unset($input['encrypted_password']);
        }

        // Same guard for the TOTP seed, which is now encrypted by the browser as well and
        // posted as an opaque cryptogram. Without it, replaying the form with the MAC segment
        // stripped would turn an authenticated seed back into a malleable AES-CTR blob.
        if (isset($input['encrypted_totp_secret']) && !empty($input['encrypted_totp_secret'])
            && !AccountCrypto::isAuthenticated($input['encrypted_totp_secret'])
            && AccountCrypto::isAuthenticated((string) ($this->fields['encrypted_totp_secret'] ?? ''))
        ) {
            Session::addMessageAfterRedirect(
                __('The submitted TOTP secret is not integrity protected and was ignored', 'accounts'),
                false,
                ERROR,
            );
            unset($input['encrypted_totp_secret']);
        }

        // Transparent re-encryption on save: upgrade older records to the best format the hash
        // record allows (v4 with PBKDF2-derived keys, v3 otherwise). Only possible when the
        // fingerprint is available (AesKey table for the entity, or POSTed key).
        $reencrypt_hash_id = (int) ($this->fields['plugin_accounts_hashes_id']
            ?? ($input['plugin_accounts_hashes_id'] ?? 0));

        if (isset($input['encrypted_password']) && !empty($input['encrypted_password'])
            && AccountCrypto::needsReencryption(
                $input['encrypted_password'],
                self::getHashVerifier($reencrypt_hash_id),
            )
        ) {
            // Resolve the fingerprint via the shared helper, which validates the key against
            // the stored verifier whichever side it comes from. Never re-encrypt under an
            // unverified key: legacy v1 (AesCtr, no MAC) can decrypt to non-empty garbage with
            // a wrong key, which would then be written back as an authenticated record and
            // silently destroy the original password.
            $fingerprint = self::resolveFingerprint($reencrypt_hash_id, $input['aeskey'] ?? null);

            if ($fingerprint !== null) {
                // decrypt() transparently reads v1, v2 (with/without MAC), v3 and v4.
                $plaintext = AccountCrypto::decrypt($input['encrypted_password'], $fingerprint);
                // Never rewrite a secret that could not be read back: an empty plaintext means
                // the key did not fit or the record is damaged, and rewriting it would turn a
                // recoverable record into a lost one.
                if ($plaintext !== '') {
                    $input['encrypted_password'] = AccountCrypto::encrypt(
                        $plaintext,
                        $fingerprint,
                        self::getHashVerifier($reencrypt_hash_id),
                    );
                }
            }
        }

        return $input;
    }

    /**
     * Tell whether the caller may attach an account to a given fingerprint.
     *
     * plugin_accounts_hashes_id is a plain foreign key: nothing in CommonDBTM confronts it
     * with the entity of the caller. A forged post can therefore bind an account -- and,
     * through resolveFingerprint(), the master key of another entity -- to a fingerprint the
     * caller cannot reach. front/aeskey.form.php and front/hash.form.php already rebuild that
     * boundary by hand for the same reason; this is the account side of it.
     *
     * @param int $hash_id The posted fingerprint ID
     * @return bool        True when the fingerprint exists in a reachable entity
     */
    private static function isHashReachable(int $hash_id): bool
    {
        $hash = new Hash();

        return $hash_id > 0
            && $hash->getFromDB($hash_id)
            && Session::haveAccessToEntity(
                $hash->fields['entities_id'],
                (bool) $hash->fields['is_recursive'],
            );
    }

    /**
     * Resolve the encryption fingerprint (AES key) for a given hash record.
     *
     * Prefers the AesKey stored in DB for that hash, then falls back to a key posted with the
     * form. Whichever it comes from, the key is returned ONLY after being verified against the
     * verifier stored on the hash record. The key read from the AesKey table is no more
     * trustworthy than the posted one: it may have been typed wrong when the key was saved,
     * or have been damaged, and nothing else ever confronts it with the verifier. A wrong key
     * matters because it is used to re-encrypt: legacy v1 (AesCtr, no MAC) decrypts to
     * non-empty garbage under a wrong key, and that garbage would then be written back as an
     * authenticated v4 record, destroying the original password for good.
     *
     * Returns null when no verified fingerprint is available, which every caller must read as
     * "leave the stored secret alone".
     *
     * @param int         $hash_id The hash (fingerprint) ID
     * @param string|null $posted  The key posted with the form ($input['aeskey']), if any
     * @return string|null The validated fingerprint, or null
     */
    private static function resolveFingerprint(int $hash_id, ?string $posted): ?string
    {
        // A hash record can never be created without a verifier (Hash::prepareInputForAdd
        // refuses an empty one), so an empty value here means a damaged record: fail closed.
        $verifier = self::getHashVerifier($hash_id);
        if ($verifier === '') {
            return null;
        }

        // AccountCrypto::verify handles both the salted PBKDF2 verifier and the legacy
        // double SHA-256 one.
        $aeskey = new AesKey();
        if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $hash_id])
            && !empty($aeskey->fields['name'])) {
            $stored = (string) $aeskey->getDecryptedName();
            if ($stored !== '' && AccountCrypto::verify($stored, $verifier)) {
                self::upgradeHashVerifier($hash_id, $stored, $verifier);
                return $stored;
            }
        }

        if (!empty($posted) && AccountCrypto::verify($posted, $verifier)) {
            self::upgradeHashVerifier($hash_id, $posted, $verifier);
            return $posted;
        }

        return null;
    }

    /**
     * Read the verifier stored on a hash record.
     *
     * Memoized: it is needed several times per save (re-encryption decision, re-encryption
     * itself, TOTP secret) and never changes within a request.
     *
     * @param int $hash_id The hash (fingerprint) ID
     * @return string      The stored verifier, or an empty string when there is none
     */
    private static function getHashVerifier(int $hash_id): string
    {
        if (!$hash_id) {
            return '';
        }

        if (!isset(self::$verifier_cache[$hash_id])) {
            $hashRecord = new Hash();
            self::$verifier_cache[$hash_id] = $hashRecord->getFromDB($hash_id)
                ? (string) ($hashRecord->fields['hash'] ?? '')
                : '';
        }

        return self::$verifier_cache[$hash_id];
    }

    /**
     * Rewrite a legacy verifier as a salted PBKDF2 one, once the key it describes has just
     * been verified against it.
     *
     * Same idea as rehashing a password on a successful login: the plaintext key is only ever
     * available at that moment. It also unlocks the v4 ciphertext format, which borrows the
     * salt and the iteration count from the verifier — as long as a hash record carries a bare
     * double SHA-256 verifier, its accounts cannot be stored with PBKDF2-derived keys.
     *
     * The column is written directly rather than through Hash::update(): this is an internal
     * representation change, the key itself is unchanged, and it happens while another item is
     * being saved — it has no place in that item's history.
     *
     * @param int    $hash_id  The hash (fingerprint) ID
     * @param string $key      The plaintext key, already verified against $verifier
     * @param string $verifier The verifier currently stored
     */
    private static function upgradeHashVerifier(int $hash_id, string $key, string $verifier): void
    {
        global $DB;

        if (str_starts_with($verifier, AccountCrypto::VERIFIER_PREFIX)) {
            return;
        }

        $upgraded = AccountCrypto::makeVerifier($key);
        $DB->update(
            'glpi_plugin_accounts_hashes',
            ['hash' => $upgraded],
            ['id' => $hash_id],
        );

        // Replace the memoized value, otherwise the very save that upgraded the verifier
        // would still encrypt with the old format.
        self::$verifier_cache[$hash_id] = $upgraded;
    }

    /**
     * Print the acccount form
     *
     * @param $ID        integer ID of the item
     * @param $options   array
     *     - target for the Form
     *     - withtemplate template or basic computer
     *
     *
     * @return bool
     */
    public function showForm($ID, $options = [])
    {
        // Defense in depth: canView() is the global right only. Every caller is supposed to
        // have run check($ID, READ) beforehand, but this method renders the encrypted password
        // and the fingerprint of the entity, so it re-runs the item level check itself.
        if (!$this->can($ID, READ)) {
            return false;
        }

        $hashclass = new Hash();

        $restrict = getEntitiesRestrictCriteria(
            "glpi_plugin_accounts_hashes",
            '',
            '',
            $hashclass->maybeRecursive(),
        );

        $nbhashes = countElementsInTable("glpi_plugin_accounts_hashes", $restrict);

        if ($ID < 1 && $nbhashes == 0) {
            echo "<div class='alert alert-warning d-flex'>";
            echo __s('There is no encryption key for this entity', 'accounts');
            echo "</div>";
            return false;
        }

        $options["form_id"] = "account_form";

        //hash
        $restrict = getEntitiesRestrictCriteria(
            "glpi_plugin_accounts_hashes",
            '',
            $this->getEntityID(),
            $hashclass->maybeRecursive(),
        );
        $hashes = getAllDataFromTable("glpi_plugin_accounts_hashes", $restrict);
        $hash             = "";
        $alerthash        = "";
        $aeskey_uncrypted = false;
        // Distinct name: $hash is the scalar verifier handed to the template further down, and the
        // loop used to leave it holding the last row of $hashes. When getFromDBByCrit() below
        // failed -- an account pointing at a deleted fingerprint -- that array reached Twig in
        // place of a string, hiding the very warning meant to explain the situation.
        if (!empty($hashes)) {
            foreach ($hashes as $hash_row) {
                if (empty($hash_row['hash'])) {
                    $alert = __s('Your encryption key is malformed, please regenerate the fingerprint', 'accounts');
                    echo "<div class='alert alert-warning d-flex'>";
                    echo $alert;
                    echo "</div>";
                    return false;
                }
            }

            // Auto-select the only available hash for new items, so users
            // don't have to manually pick the fingerprint + encryption key
            // every time they create an account (regression from 3.0.x).
            $selected_hash_id = $this->fields["plugin_accounts_hashes_id"];
            if (empty($selected_hash_id) && count($hashes) === 1) {
                $only_hash = reset($hashes);
                $selected_hash_id = $only_hash['id'];
                $this->fields["plugin_accounts_hashes_id"] = $selected_hash_id;
            }

            $hashclass->getFromDBByCrit(['id' => $selected_hash_id]);
            if (count($hashclass->fields) > 0) {
                $hash = (string) $hashclass->fields["hash"];
            } else {
                $alerthash = __(
                    'There is no encryption key associated to this account, please select one above',
                    'accounts',
                );
            }
        } else {
            $alert = __s('There is no encryption key for this entity', 'accounts');
            echo "<div class='alert alert-warning d-flex'>";
            echo $alert;
            echo "</div>";
            return false;
        }

        $canupdateHash = Session::haveRight('plugin_accounts_hash', UPDATE);

        // Serve the remembered master key in cleartext (and trigger auto-decrypt) only to users
        // allowed to manage the encryption key (plugin_accounts_hash UPDATE). A plain READ user
        // must enter the key manually, preserving the zero-knowledge model for lower-privileged
        // readers: otherwise anyone with READ could read the key from the page source and decrypt
        // every account of the entity offline.
        if ($canupdateHash) {
            $aeskey = new AesKey();
            if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $selected_hash_id])
                && $aeskey->fields["name"]) {
                $aeskey_uncrypted = $aeskey->getDecryptedName();
            }
        }

        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('@accounts/account.html.twig', [
            'item' => $this,
            'nbhashes' => $nbhashes,
            'hash' => $hash,
            'canupdateHash' => $canupdateHash,
            'alerthash' => $alerthash,
            'aeskey_uncrypted' => $aeskey_uncrypted,
            'root_accounts_doc' => PLUGIN_ACCOUNTS_WEBDIR,
            'params' => $options,
            'has_totp' => !empty($this->fields['encrypted_totp_secret']),
            'show_password_generator' => empty($ID) ? true : false,
        ]);

        return true;
    }

    /**
     * Make a select box for link accounts
     *
     * Parameters which could be used in options array :
     *    - name : string / name of the select (default is documents_id)
     *    - entity : integer or array / restrict to a defined entity or array of entities
     *                   (default -1 : no restriction)
     *    - used : array / Already used items ID: not to display in dropdown (default empty)
     *
     * @param $options array of possible options
     *
     * @return nothing (print out an HTML select box)
     **/
    public static function dropdownAccount($options = [])
    {
        global $DB;

        $p['name'] = 'plugin_accounts_accounts_id';
        $p['entity'] = '';
        $p['used'] = [];
        $p['display'] = true;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $subquery = [
            'SELECT' => 'plugin_accounts_accounttypes_id',
            'DISTINCT' => true,
            'FROM' => 'glpi_plugin_accounts_accounts',
            'WHERE' => ['glpi_plugin_accounts_accounts.is_deleted' => 0],
        ];
        $subquery['WHERE'] = $subquery['WHERE'] + getEntitiesRestrictCriteria(
            'glpi_plugin_accounts_accounts',
            '',
            $p['entity'],
            true,
        );

        if (count($p['used'])) {
            $subquery['WHERE'] = $subquery['WHERE'] + ['id' => ['NOT IN', array_filter($p['used'])]];
            ;
        }

        $criteria = [
            'FROM' => 'glpi_plugin_accounts_accounttypes',
            'WHERE' => [
                'id' => new QuerySubQuery($subquery),
            ],
            'GROUPBY' => 'name',
        ];

        $iterator = $DB->request($criteria);

        $values = [0 => Dropdown::EMPTY_VALUE];

        foreach ($iterator as $data) {
            $values[$data['id']] = $data['name'];
        }
        $rand = mt_rand();
        $out = Dropdown::showFromArray('_accounttype', $values, [
            'width' => '30%',
            'rand' => $rand,
            'display' => false,
        ]);
        $field_id = Html::cleanId("dropdown__accounttype$rand");

        $params = [
            'accounttype' => '__VALUE__',
            'entity' => $p['entity'],
            'rand' => $rand,
            'myname' => $p['name'],
            'used' => $p['used'],
        ];

        $out .= Ajax::updateItemOnSelectEvent(
            $field_id,
            "show_" . $p['name'] . $rand,
            PLUGIN_ACCOUNTS_WEBDIR . "/ajax/dropdownTypeAccounts.php",
            $params,
            false,
        );
        $out .= "<span id='show_" . $p['name'] . "$rand'>";
        $out .= "</span>\n";

        $params['accounttype'] = 0;
        $out .= Ajax::updateItem(
            "show_" . $p['name'] . $rand,
            PLUGIN_ACCOUNTS_WEBDIR . "/ajax/dropdownTypeAccounts.php",
            $params,
            false,
        );
        if ($p['display']) {
            echo $out;
            return $rand;
        }
        return $out;
    }

    /**
     * Get the specific massive actions
     *
     * @param $checkitem link item to check right   (default NULL)
     *
     * @return an $array of massive actions
     * @since version 0.84
     *
     */
    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if (Session::getCurrentInterface() == 'central') {
            if ($isadmin) {
                $actions[Account::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'install'] = _x(
                    'button',
                    'Associate',
                );
                $actions[Account::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'uninstall'] = _x(
                    'button',
                    'Dissociate',
                );

                if (Session::haveRight('transfer', READ)
                    && Session::isMultiEntitiesMode()
                ) {
                    $actions[Account::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfer'] = __s(
                        'Transfer',
                    );
                }
            }
        }
        return $actions;
    }

    /**
     * @param MassiveAction $ma
     *
     * @return bool|false
     */
    /**
     * @param MassiveAction $ma
     *
     * @return bool|false
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'add_item':
                self::dropdownAccount([]);
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
                return true;
            case "uninstall":
            case "install":
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name' => 'item_item',
                    'itemtype_name' => 'typeitem',
                    'itemtypes' => self::getTypes(true),
                    'checkright'
                    => true,
                ]);
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
                return true;
                break;
            case "transfer":
                Dropdown::show('Entity');
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
                return true;
                break;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @param MassiveAction $ma
     * @param CommonDBTM $item
     * @param array $ids
     *
     * @return nothing|void
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     *
     */
    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        $account_item = new Account_Item();
        $dbu = new DbUtils();

        switch ($ma->getAction()) {
            case "add_item":
                $input = $ma->getInput();
                // Object-level check: the caller must be able to READ the
                // destination account before linking items to it. add() runs no
                // guard on its own (Account_Item has no prepareInputForAdd), so
                // without this a forged mass action could attach items to an
                // account outside the caller's visibility — same guard as
                // front/account.form.php.
                $account = new self();
                if (
                    empty($input['plugin_accounts_accounts_id'])
                    || !$account->can((int) $input['plugin_accounts_accounts_id'], READ)
                ) {
                    foreach ($ids as $key) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                    break;
                }
                foreach ($ids as $key) {
                    // Linking an account to an asset writes on that asset: the identifiers come
                    // straight from the POST and the core never rechecks them once a plugin
                    // implements processMassiveActionsForOneItemtype(). Same per item guard as
                    // the transfer, install and uninstall branches below.
                    if (!$item->can($key, UPDATE)) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::NO_ACTION);
                        continue;
                    }
                    if (!$dbu->countElementsInTable(
                        'glpi_plugin_accounts_accounts_items',
                        [
                            "itemtype" => $item->getType(),
                            "items_id" => $key,
                            "plugin_accounts_accounts_id" => $input['plugin_accounts_accounts_id'],
                        ],
                    )) {
                        $myvalue['plugin_accounts_accounts_id'] = $input['plugin_accounts_accounts_id'];
                        $myvalue['itemtype'] = $item->getType();
                        $myvalue['items_id'] = $key;
                        if ($account_item->add($myvalue)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                }

                break;

            case "transfer":
                $input = $ma->getInput();
                if ($item->getType() == Account::class) {
                    // The destination entity comes from the POST and is never checked by the core:
                    // revalidate the posted value, otherwise an account could be moved into an
                    // entity the caller has no access to (and re-encrypted with its key).
                    $target_entity = isset($input['entities_id']) ? (int) $input['entities_id'] : -1;
                    if ($target_entity < 0 || !Session::haveAccessToEntity($target_entity)) {
                        foreach ($ids as $key) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        }
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        break;
                    }

                    foreach ($ids as $key) {
                        // The ids also come from the POST: MassiveAction filters them neither by
                        // right nor by entity, so each account is checked here. Without it the
                        // handler would decrypt an account of another entity and re-encrypt it
                        // with the key of the caller's own entity.
                        if (!$item->can($key, UPDATE)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                            continue;
                        }
                        $item->getFromDB($key);
                        // --- Step 1: Resolve account type in destination entity ---
                        $type = AccountType::transfer(
                            $item->fields["plugin_accounts_accounttypes_id"],
                            $target_entity,
                        );

                        // --- Step 2: Re-encrypt password with destination fingerprint ---
                        $reencrypted_password = null;
                        $new_hash_id = 0;
                        // These three are only assigned inside the "has a password" block below, so
                        // without an explicit reset they survive into the next iteration. An account
                        // with a TOTP secret but no password would then have its seed decrypted with
                        // the key of a previously processed account -- a different fingerprint, very
                        // possibly a different entity -- and the empty result written back over it.
                        $src_aes_key_value  = null;
                        $dest_aes_key_value = null;
                        $dest_verifier      = '';

                        if (!empty($item->fields['encrypted_password'])) {
                            // Get the AES key for the SOURCE fingerprint
                            $src_aeskey = new AesKey();
                            $src_hash_id = $item->fields['plugin_accounts_hashes_id'];

                            if ($src_aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $src_hash_id])
                                && !empty($src_aeskey->fields['name'])) {
                                $src_aes_key_value = $src_aeskey->getDecryptedName();

                                // Decrypt with source key. AccountCrypto hashes the fingerprint
                                // internally, so pass the raw AES key (as resolveFingerprint does),
                                // never a pre-hashed value: double hashing breaks decryption and
                                // would silently clear the transferred secret.
                                $plaintext = AccountCrypto::decrypt(
                                    $item->fields['encrypted_password'],
                                    $src_aes_key_value,
                                );

                                // Find destination entity's fingerprint
                                $dest_hash = new Hash();
                                $restrict = getEntitiesRestrictCriteria(
                                    'glpi_plugin_accounts_hashes',
                                    '',
                                    $target_entity,
                                    $dest_hash->maybeRecursive(),
                                );
                                $dest_hashes = getAllDataFromTable('glpi_plugin_accounts_hashes', $restrict);

                                if (count($dest_hashes) > 0) {
                                    // Use first available fingerprint in destination entity
                                    $dest_hash_row = reset($dest_hashes);
                                    $new_hash_id = $dest_hash_row['id'];
                                    // The destination verifier carries the PBKDF2 salt and
                                    // iteration count encrypt() needs to emit v4. Without it the
                                    // record would be rewritten as v3, whose key is a bare
                                    // unsalted SHA-256 — a silent downgrade of the KDF.
                                    $dest_verifier = (string) ($dest_hash_row['hash'] ?? '');

                                    $dest_aeskey = new AesKey();
                                    if ($dest_aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $new_hash_id])
                                        && !empty($dest_aeskey->fields['name'])) {
                                        $dest_aes_key_value = $dest_aeskey->getDecryptedName();

                                        // Re-encrypt with destination key (raw AES key as fingerprint).
                                        $reencrypted_password = AccountCrypto::encrypt(
                                            $plaintext,
                                            $dest_aes_key_value,
                                            $dest_verifier,
                                        );
                                    }
                                }

                                // If no destination fingerprint found — warn and skip re-encryption
                                if ($reencrypted_password === null) {
                                    Session::addMessageAfterRedirect(
                                        sprintf(
                                            __s(
                                                'Account "%s" transferred but no fingerprint found in destination entity. Password was cleared for security.',
                                                'accounts',
                                            ),
                                            $item->fields['name'],
                                        ),
                                        false,
                                        WARNING,
                                    );
                                    // Clear password rather than leave it encrypted with wrong key
                                    $reencrypted_password = '';
                                }
                            }
                        }

                        // --- Step 2b: Re-encrypt TOTP secret with destination fingerprint ---
                        $reencrypted_totp = null;
                        if (!empty($item->fields['encrypted_totp_secret'])
                            && $src_aes_key_value !== null && $dest_aes_key_value !== null) {
                            $plain_totp = AccountCrypto::decrypt(
                                $item->fields['encrypted_totp_secret'],
                                $src_aes_key_value,
                            );
                            // Same rule as Hash::updateHash(): decrypt() answers an empty string when
                            // the MAC does not check out, and re-encrypting that would store a perfectly
                            // valid cryptogram of nothing in place of the seed. Skip the whole record
                            // rather than move it half-transferred, and say which one it was.
                            if ($plain_totp === '') {
                                Session::addMessageAfterRedirect(
                                    sprintf(
                                        __s(
                                            'The TOTP secret of account "%s" could not be decrypted with the source key: the account was not transferred.',
                                            'accounts',
                                        ),
                                        htmlescape($item->fields['name']),
                                    ),
                                    false,
                                    ERROR,
                                );
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                                continue;
                            }
                            $reencrypted_totp = AccountCrypto::encrypt(
                                $plain_totp,
                                $dest_aes_key_value,
                                $dest_verifier,
                            );
                        } elseif (!empty($item->fields['encrypted_totp_secret']) && $reencrypted_password === null) {
                            // No usable destination fingerprint: the account keeps the one it had, so the
                            // seed would stay readable under a key of the source entity from inside the
                            // destination one. It is cleared for that reason -- but never silently.
                            Session::addMessageAfterRedirect(
                                sprintf(
                                    __s(
                                        'Account "%s" transferred but no fingerprint found in destination entity. TOTP secret was cleared for security.',
                                        'accounts',
                                    ),
                                    htmlescape($item->fields['name']),
                                ),
                                false,
                                WARNING,
                            );
                            $reencrypted_totp = '';
                        }

                        // --- Step 3: Build update values ---
                        $values = ['id' => $key, 'entities_id' => $target_entity];

                        if ($type > 0) {
                            $values['plugin_accounts_accounttypes_id'] = $type;
                        }
                        if ($reencrypted_password !== null) {
                            $values['encrypted_password'] = $reencrypted_password;
                            $values['plugin_accounts_hashes_id'] = $new_hash_id;
                        }

                        if ($reencrypted_totp !== null) {
                            $values['encrypted_totp_secret'] = $reencrypted_totp;
                        }

                        if ($item->update($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                break;
            case 'install':
                $input = $ma->getInput();

                // Same posted itemtype/id as the uninstall branch: validate the asset once,
                // before linking any account to it.
                $target = self::getMassiveActionTargetItem($input);
                if ($target === null) {
                    foreach ($ids as $key) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                    }
                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    break;
                }

                foreach ($ids as $key) {
                    if ($item->can($key, UPDATE)) {
                        $values = [
                            'plugin_accounts_accounts_id' => $key,
                            'items_id' => $input["item_item"],
                            'itemtype' => $input['typeitem'],
                        ];
                        if ($account_item->add($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                break;
            case 'uninstall':
                $input = $ma->getInput();
                // Both the itemtype and the id of the asset come from the POST: resolve them
                // against the linkable types and check UPDATE on the asset before touching any
                // link, otherwise a forged mass action would break links on arbitrary items.
                $target = self::getMassiveActionTargetItem($input);
                if ($target === null) {
                    foreach ($ids as $key) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                    }
                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    break;
                }

                foreach ($ids as $key) {
                    // The account side is checked per item, as the install branch does.
                    if (!$item->can($key, UPDATE)) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        continue;
                    }
                    if ($account_item->deleteItemByAccountsAndItem($key, $input['item_item'], $input['typeitem'])) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                }
                break;
        }
    }

    /**
     * Resolves the asset a link mass action (install/uninstall) targets.
     *
     * Both `typeitem` and `item_item` are posted values MassiveAction hands over untouched, and
     * `new $itemtype` is a sink: the itemtype is checked against the linkable types before it is
     * instantiated, then UPDATE is checked on the asset itself.
     *
     * @param array $input input of the mass action
     *
     * @return CommonDBTM|null the asset, or null when the caller may not link anything to it
     */
    private static function getMassiveActionTargetItem(array $input): ?CommonDBTM
    {
        $itemtype = (string) ($input['typeitem'] ?? '');
        $items_id = (int) ($input['item_item'] ?? 0);

        if ($items_id <= 0 || !in_array($itemtype, self::getTypes(true), true)) {
            return null;
        }

        $target = getItemForItemtype($itemtype);
        if (!($target instanceof CommonDBTM) || !$target->can($items_id, UPDATE)) {
            return null;
        }

        return $target;
    }

    /**
     * Get the standard massive actions which are forbidden
     *
     * @return an|array $array of massive actions
     * @since version 0.84
     */
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden = parent::getForbiddenStandardMassiveAction();
        if (Session::getCurrentInterface() != 'central') {
            $forbidden[] = 'update';
            $forbidden[] = 'delete';
            $forbidden[] = 'purge';
            $forbidden[] = 'restore';
        }
        return $forbidden;
    }

    /**
     * Cron Info
     *
     * @param $name of the cron task
     *
     * @return array
     **/
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'AccountsAlert':
                return [
                    'description' => __s('Accounts expired or accounts which expire', 'accounts'),
                ]; // Optional
                break;
        }
        return [];
    }

    /**
     * Query used for check expired accounts
     *
     * @return array
     **/
    private static function queryExpiredAccounts()
    {
        global $DB;

        $config = new Config();
        $notif = new NotificationState();

        $config->getFromDB('1');
        // The column is a varchar and the configuration form stores it unfiltered: cast and
        // quote it, it is interpolated into a raw SQL expression right below.
        $delay = (int) $config->fields["delay_expired"];

        if ($delay) {
            $criteria = [
                'SELECT' => '*',
                'FROM' => self::getTable(),
                'WHERE' => [
                    'NOT' => [
                        'date_expiration' => null,
                    ],
                    'is_deleted' => 0,
                    new QueryExpression("DATEDIFF(CURDATE(), " . $DB->quoteName('date_expiration') . ") > " . $DB::quoteValue($delay)),
                    new QueryExpression("DATEDIFF(CURDATE(), " . $DB->quoteName('date_expiration') . ") > 0"),
                ],
            ];

            if (count($notif->findStates()) > 0) {
                $criteria['WHERE'] = $criteria['WHERE'] + ['plugin_accounts_accountstates_id' => $notif->findStates()];
            }
            return $criteria;
        }
        return [];
    }

    /**
     * Query used for check accounts which expire
     *
     * @return array
     **/
    private static function queryAccountsWhichExpire()
    {
        global $DB;

        $config = new Config();
        $notif = new NotificationState();

        $config->getFromDB('1');
        // Same as queryExpiredAccounts(): the raw configuration value never reaches the SQL.
        $delay = (int) $config->fields["delay_whichexpire"];

        if ($delay) {
            $criteria = [
                'SELECT' => '*',
                'FROM' => self::getTable(),
                'WHERE' => [
                    'NOT' => ['date_expiration' => null],
                    'is_deleted' => 0,
                    new QueryExpression("DATEDIFF(CURDATE(), " . $DB->quoteName('date_expiration') . ") > " . $DB::quoteValue(-$delay)),
                    new QueryExpression("DATEDIFF(CURDATE(), " . $DB->quoteName('date_expiration') . ") < 0"),
                ],
            ];

            if (count($notif->findStates()) > 0) {
                $criteria['WHERE'] = $criteria['WHERE'] + ['plugin_accounts_accountstates_id' => $notif->findStates()];
            }
            return $criteria;
        }
        return [];
    }

    /**
     * Cron action on accounts : ExpiredAccounts or AccountsWhichExpire
     *
     * @param $task for log, if NULL display
     *
     * @return int
     **/
    public static function cronAccountsAlert($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $cron_status = 0;

        $query_expired = self::queryExpiredAccounts();
        $query_whichexpire = self::queryAccountsWhichExpire();

        $querys = [Alert::NOTICE => $query_whichexpire, Alert::END => $query_expired];

        $account_infos = [];
        $account_messages = [];

        foreach ($querys as $type => $query) {
            $account_infos[$type] = [];
            if (!empty($query)) {
                foreach ($DB->request($query) as $data) {
                    $entity = $data['entities_id'];
                    $message = $data["name"] . ": "
                        . Html::convDate($data["date_expiration"]) . "<br>\n";
                    $account_infos[$type][$entity][] = $data;

                    if (!isset($account_messages[$type][$entity])) {
                        $account_messages[$type][$entity] = __s(
                            'Accounts expired or accounts which expire',
                            'accounts',
                        ) . "<br />";
                    }
                    $account_messages[$type][$entity] .= $message;
                }
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($account_infos[$type] as $entity => $accounts) {
                Plugin::loadLang('accounts');

                if (NotificationEvent::raiseEvent(
                    ($type == Alert::NOTICE ? "AccountsWhichExpire" : "ExpiredAccounts"),
                    new Account(),
                    [
                        'entities_id' => $entity,
                        'accounts' => $accounts,
                    ],
                )) {
                    $message = $account_messages[$type][$entity];
                    $cron_status = 1;
                    if ($task) {
                        $task->log(
                            Dropdown::getDropdownName(
                                "glpi_entities",
                                $entity,
                            ) . ":  $message\n",
                        );
                        $task->addVolume(1);
                    } else {
                        Session::addMessageAfterRedirect(
                            Dropdown::getDropdownName(
                                "glpi_entities",
                                $entity,
                            ) . ":  $message",
                        );
                    }
                } else {
                    if ($task) {
                        $task->log(
                            Dropdown::getDropdownName("glpi_entities", $entity)
                            . ":  Send accounts alert failed\n",
                        );
                    } else {
                        Session::addMessageAfterRedirect(
                            Dropdown::getDropdownName("glpi_entities", $entity)
                            . ":  Send accounts alert failed",
                            false,
                            ERROR,
                        );
                    }
                }
            }
        }

        return $cron_status;
    }

    /**
     * Cron task configuration
     *
     * @param $target
     *
     * @return
     **/
    public static function configCron($target)
    {
        $notif = new NotificationState();
        $config = new Config();

        $config->showConfigForm($target);
        $notif->showNotificationForm($target);
    }

    /**
     * Maximum number of accounts listed under an account type node.
     *
     * The tree has no paging: past that count, the "Show all" node of the type links to
     * the filtered search list, which is the way to reach the whole set.
     */
    public const TREE_CHILDREN_LIMIT = 50;

    /**
     * Search criteria restricting the account type tree to what the user may see.
     *
     * Adapter over getVisibilityCriteria(), which is the single definition of "accounts
     * visible to the current user" and the one plugin_accounts_addDefaultWhere() feeds the
     * search engine with. Only what is specific to the tree is added here: the entity scope
     * and the is_deleted filter. Spelling the ownership rule out a second time is what let
     * the two drift apart -- the tree used to ignore users_id_tech and groups_id_tech, so a
     * technician saw in the list accounts the tree hid from them. The tree is opened from a
     * page gated on plugin_accounts_see_all_users, but its endpoint is reachable on its own.
     *
     * @return array
     */
    private static function getTreeVisibilityCriteria()
    {
        $table = self::getTable();

        // Kept as an AND list rather than merged: getEntitiesRestrictCriteria() returns
        // an 'OR' key of its own when the entity is recursive, which would collide with
        // the ownership clause below.
        $criteria = [
            'AND' => [
                ["$table.is_deleted" => 0],
                getEntitiesRestrictCriteria($table, '', '', true),
            ],
        ];

        // Qualified with the table name: the tree queries join glpi_plugin_accounts_accounttypes.
        $visibility = self::getVisibilityCriteria(true);
        if ($visibility !== []) {
            $criteria['AND'][] = $visibility;
        }

        return $criteria;
    }

    /**
     * URL of the account list filtered on an account type.
     *
     * Search option 2 is a dropdown on glpi_plugin_accounts_accounttypes, so the "equals"
     * search type matches on the identifier: no name is interpolated into the URL any
     * more, which also makes the link exact instead of a "starts with" on the name.
     *
     * @param int $accounttypes_id
     *
     * @return string
     */
    private static function getTreeSearchUrl($accounttypes_id)
    {
        return PLUGIN_ACCOUNTS_WEBDIR . '/front/account.php?' . http_build_query([
            'criteria' => [
                [
                    'field'      => 2,
                    'searchtype' => 'equals',
                    'value'      => (int) $accounttypes_id,
                ],
            ],
            'start' => 0,
        ]);
    }

    /**
     * Build the fancytree nodes of the account type browser.
     *
     * The root level lists the account types holding at least one visible account;
     * expanding one lazy-loads its accounts. Leaf nodes carry their target URL in
     * data.url, which the script opens on activation, so no event handler is built
     * server side any more.
     *
     * @param string $node fancytree key of the node being expanded, '-1' for the root
     *
     * @return array
     */
    public static function getTreeNodes($node)
    {
        if ((string) $node === '-1') {
            return self::getAccountTypeTreeNodes();
        }

        // Child keys are built below as "accounttype-<id>"; nothing else has children.
        if (preg_match('/^accounttype-(\d+)$/', (string) $node, $matches) !== 1) {
            return [];
        }

        return self::getAccountTreeNodes((int) $matches[1]);
    }

    /**
     * Root level of the tree: the account types holding at least one visible account.
     *
     * Account types carry no URL on purpose: activating one only unfolds it. The filtered
     * list is reachable from the "Show all" child node built below.
     *
     * @return array
     */
    private static function getAccountTypeTreeNodes()
    {
        global $DB;

        $table = self::getTable();
        $types = AccountType::getTable();

        $iterator = $DB->request([
            'SELECT'     => [
                "$types.id AS id",
                "$types.name AS name",
                QueryFunction::count("$table.id", false, 'nb'),
            ],
            'FROM'       => $types,
            'INNER JOIN' => [
                $table => [
                    'FKEY' => [
                        $types => 'id',
                        $table => 'plugin_accounts_accounttypes_id',
                    ],
                ],
            ],
            'WHERE'      => self::getTreeVisibilityCriteria(),
            'GROUPBY'    => ["$types.id", "$types.name"],
            'ORDER'      => "$types.name",
        ]);

        $nodes = [];
        foreach ($iterator as $type) {
            $nodes[] = [
                'key'     => 'accounttype-' . $type['id'],
                'title'   => sprintf(__('%1$s (%2$s)'), $type['name'], $type['nb']),
                'folder'  => true,
                'lazy'    => true,
                'tooltip' => AccountType::getTypeName(1) . ' - ' . $type['name'],
            ];
        }

        return $nodes;
    }

    /**
     * Second level of the tree: the accounts of an account type.
     *
     * The listing is capped by TREE_CHILDREN_LIMIT; the leading "Show all" node links to
     * the filtered search list, which is both the way to reach the whole set and the way
     * to reach the accounts the cap left out.
     *
     * @param int $accounttypes_id
     *
     * @return array
     */
    private static function getAccountTreeNodes($accounttypes_id)
    {
        global $DB;

        $table = self::getTable();

        $criteria = self::getTreeVisibilityCriteria();
        $criteria['AND'][] = ["$table.plugin_accounts_accounttypes_id" => $accounttypes_id];

        $total = countElementsInTable($table, $criteria);

        $nodes = [
            [
                'key'   => 'accounttype-' . $accounttypes_id . '-all',
                'title' => sprintf(__('%1$s (%2$s)'), __('Show all'), $total),
                'icon'  => 'ti ti-list-search',
                'data'  => ['url' => self::getTreeSearchUrl($accounttypes_id)],
            ],
        ];

        $iterator = $DB->request([
            'SELECT' => ["$table.id AS id", "$table.name AS name", "$table.login AS login"],
            'FROM'   => $table,
            'WHERE'  => $criteria,
            'ORDER'  => ["$table.name", "$table.login"],
            'LIMIT'  => self::TREE_CHILDREN_LIMIT,
        ]);

        foreach ($iterator as $account) {
            $title = (string) $account['name'];
            if (!empty($account['login'])) {
                $title = sprintf(__('%1$s (%2$s)'), $title, $account['login']);
            }

            $nodes[] = [
                'key'   => 'account-' . $account['id'],
                'title' => $title,
                'icon'  => 'ti ti-key',
                'data'  => [
                    'url' => PLUGIN_ACCOUNTS_WEBDIR . '/front/account.form.php?id=' . $account['id'],
                ],
            ];
        }

        return $nodes;
    }

    /**
     * Show the account type tree used to filter the account list.
     *
     * Rendered inside the iframe of a modal, in a page emitted by Html::popHeader(): the
     * GLPI stylesheet and the core bundles are already there, only the tree own assets are
     * pulled here.
     *
     * @param string $target front page the "Show all" link points back to
     *
     * @return void
     */
    public static function showSelector($target)
    {
        Plugin::loadLang('accounts');

        // The page goes through Html::popHeader(), which already brings the whole GLPI
        // stylesheet and the core bundles carrying fancytree: only the tree own assets are
        // left to pull.
        $assets = Html::css(PLUGIN_ACCOUNTS_WEBDIR . "/css/accounttree.css", [], false)
            . Html::script(PLUGIN_ACCOUNTS_WEBDIR . "/scripts/accounttree.js", ['type' => 'module'], false);

        TemplateRenderer::getInstance()->display('@accounts/account_tree.html.twig', [
            'assets'       => $assets,
            'rand'         => mt_rand(),
            'target'       => $target,
            'root_doc'     => PLUGIN_ACCOUNTS_WEBDIR,
            'no_data_text' => __('No item found'),
            'search_label' => __('Search'),
        ]);
    }

    /**
     * For other plugins, add a type to the linkable types
     *
     * @param $type string class name
     *
     **@since version 1.8.0
     *
     */
    public static function registerType($type)
    {
        if (!in_array($type, self::$types)) {
            self::$types[] = $type;
        }
    }

    /**
     * Type than could be linked to a Rack
     *
     * @param $all boolean, all type, or only allowed ones
     *
     * @return array of types
     **/
    public static function getTypes($all = false)
    {
        global $CFG_GLPI;

        if ($all) {
            return array_merge(self::$types, $CFG_GLPI['asset_types'], ['Database']);
        }

        // Only allowed types
        $types = array_merge(self::$types, $CFG_GLPI['asset_types'], ['Database']);

        foreach ($types as $key => $type) {
            if (!class_exists($type)) {
                continue;
            }

            $item = new $type();
            if (!$item->canView()) {
                unset($types[$key]);
            }
        }
        return $types;
    }

    /**
     * display a specific field value
     *
     * @param $field     String         name of the field
     * @param $values    String/Array   with the value to display or a Single value
     * @param $options   Array          of options
     *
     * @return date|return|string|translated
     * @since version 0.83
     *
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
            case 'date_expiration':
            case 'date_creation':
                if (!empty($values[$field]) && $values[$field] !== 'NULL') {
                    return Html::convDate($values[$field]);
                }
                return __s('Don\'t expire', 'accounts');
        }
        return '';
    }

    /**
     * @param string $interface
     *
     * @return array
     */
    /**
     * @param string $interface
     *
     * @return array
     */
    public function getRights($interface = 'central')
    {
        global $DB;

        if (!$DB->tableExists('glpi_plugin_accounts_accounts')) {
            return true;
        }

        $values = parent::getRights();

        if ($interface == 'helpdesk') {
            unset($values[CREATE], $values[DELETE], $values[PURGE]);
        }
        return $values;
    }

    /**
     * @param array $options Options
     *
     * @return bool
     **@since 9.1
     *
     */
    public function showDates($options = [])
    {
        $isNewID = ((isset($options['withtemplate']) && ($options['withtemplate'] == 2))
            || $this->isNewID($this->getID()));

        if ($isNewID) {
            return true;
        }

        $date_creation_exists = ($this->getField('date_creation') != NOT_AVAILABLE);
        $date_mod_exists = ($this->getField('date_mod') != NOT_AVAILABLE);

        $colspan = $options['colspan'];
        if ((!isset($options['withtemplate']) || ($options['withtemplate'] == 0))
            && !empty($this->fields['template_name'])) {
            $colspan = 1;
        }

        echo "<tr class='tab_bg_1 footerRow'>";
        //Display when it's not a new asset being created
        if ($date_creation_exists
            && $this->getID() > 0
            && (!isset($options['withtemplate']) || $options['withtemplate'] == 0 || $options['withtemplate'] == null)) {
            echo "<th colspan='$colspan'>";
            printf(__s('Created on %s'), Html::convDateTime($this->fields["date_creation"]));
            echo "</th>";
        } elseif (!isset($options['withtemplate']) || $options['withtemplate'] == 0 || !$date_creation_exists) {
            echo "<th colspan='$colspan'>";
            echo "</th>";
        }

        if (isset($options['withtemplate']) && $options['withtemplate']) {
            echo "<th colspan='$colspan'>";
            //TRANS: %s is the datetime of insertion
            printf(__s('Created on %s'), Html::convDateTime($_SESSION["glpi_currenttime"]));
            echo "</th>";
        }

        if ($date_mod_exists) {
            echo "<th colspan='$colspan'>";
            //TRANS: %s is the datetime of update
            printf(__s('Last update on %s'), Html::convDateTime($this->fields["date_mod"]));
            echo "</th>";
        } else {
            echo "<th colspan='$colspan'>";
            echo "</th>";
        }

        if ((!isset($options['withtemplate']) || ($options['withtemplate'] == 0))
            && !empty($this->fields['template_name'])) {
            echo "<th colspan='" . ($colspan * 2) . "'>";
            // Stored raw since GLPI 10+: escape before output like the other columns (name/login/type).
            printf(__s('Created from the template %s'), htmlspecialchars((string) $this->fields['template_name'], ENT_QUOTES, 'UTF-8'));
            echo "</th>";
        }

        echo "</tr>";
    }

    /**
     * @return array
     */
    public static function getMenuContent()
    {
        $image = "<i class='ti ti-lock-open' title='" . _n(
            'Encryption key',
            'Encryption keys',
            2,
            'accounts',
        ) . "'></i>" . _n('Encryption key', 'Encryption keys', 2, 'accounts');

        $menu = [];
        $menu['title'] = self::getMenuName();
        $menu['page'] = self::getSearchURL(false);
        $menu['links']['search'] = self::getSearchURL(false);
        $menu['links']['lists'] = "";
        if (Hash::canView()) {
            $menu['links'][$image] = Hash::getSearchURL(false);
        }
        if (self::canCreate()) {
            $menu['links']['add'] = self::getFormURL(false);
        }

        $menu['options']['account']['title'] = self::getTypeName(2);
        $menu['options']['account']['page'] = self::getSearchURL(false);
        $menu['options']['account']['links']['search'] = Account::getSearchURL(false);
        if (Hash::canView()) {
            $menu['options']['account']['links'][$image] = Hash::getSearchURL(false);
        }
        if (Account::canCreate()) {
            $menu['options']['account']['links']['add'] = self::getFormURL(false);
        }

        if (Hash::canView()) {
            $menu['options']['hash']['title'] = Hash::getTypeName(2);
            $menu['options']['hash']['page'] = Hash::getSearchURL(false);
            $menu['options']['hash']['links']['search'] = Hash::getSearchURL(false);
            $menu['options']['hash']['links'][$image] = Hash::getSearchURL(false);
        }
        if (Hash::canCreate()) {
            $menu['options']['hash']['links']['add'] = Hash::getFormURL(false);
        }

        $menu['icon'] = self::getIcon();

        return $menu;
    }

    public static function removeRightsFromSession()
    {
        global $DB;

        if (!$DB->tableExists('glpi_plugin_accounts_accounts')) {
            return true;
        }

        if (isset($_SESSION['glpimenu']['admin']['types'][Account::class])) {
            unset($_SESSION['glpimenu']['admin']['types'][Account::class]);
        }
        if (isset($_SESSION['glpimenu']['admin']['content'][Account::class])) {
            unset($_SESSION['glpimenu']['admin']['content'][Account::class]);
        }
    }

    public static function supportHelpdeskDisplayPreferences(): bool
    {
        return true;
    }

    public static function showAccountsWithoutHash()
    {
        global $DB;

        $criteria = [
            'SELECT' => [
                'COUNT' => 'id AS cpt',
            ],
            'FROM' => 'glpi_plugin_accounts_accounts',
            'WHERE' => [
                'plugin_accounts_hashes_id' => 0,
                'is_deleted' => 0,
                // Without this the banner counted the whole tree, so a technician of one entity
                // was told to go and fix accounts of entities they cannot even see -- and was
                // told nothing when their own entity was clean. Same restriction as everywhere
                // else in the plugin.
                getEntitiesRestrictCriteria('glpi_plugin_accounts_accounts', '', '', true),
            ],
        ];

        // getVisibilityCriteria() answers an empty array for a holder of the "see all" right,
        // and an empty array is not a criterion the query builder can render.
        $visibility = self::getVisibilityCriteria(true);
        if ($visibility !== []) {
            $criteria['WHERE'][] = $visibility;
        }

        $iterator = $DB->request($criteria);

        if (count($iterator) > 0) {
            foreach ($iterator as $data) {
                $cpt = $data['cpt'];
                if ($cpt > 0) {
                    echo "<div class='alert alert-warning d-flex'>";
                    echo __s(
                        'You have accounts without linked fingerprint, please add it with massive action or into forms',
                        'accounts',
                    );
                    echo "</div>";
                }
            }
        }
    }

    /**
     * Build WHERE criteria for account visibility based on current user rights.
     *
     * - plugin_accounts_see_all_users = 1 → no restriction (admin)
     * - plugin_accounts_my_groups = 1     → own groups + own user
     * - default                           → own user only
     *
     * @param bool $qualified Prefix the column names with the table name, as needed by the
     *                        search engine where the query joins tables sharing those names.
     *
     * @return array GLPI DBUtils criteria array, empty if no restriction needed
     */
    public static function getVisibilityCriteria(bool $qualified = false): array
    {
        // Only the explicit "see all" right lifts the restriction: being allowed to
        // administrate GLPI ('config') does not imply being allowed to read every password,
        // and the default super-admin profile is granted 'see all' at install time anyway.
        if (Session::haveRight('plugin_accounts_see_all_users', READ)) {
            return [];
        }
        $who    = Session::getLoginUserID();
        $prefix = $qualified ? self::getTable() . '.' : '';

        // Group-based visibility
        if (Session::haveRight('plugin_accounts_my_groups', READ)
            && !empty($_SESSION['glpigroups'])) {
            $or = [
                $prefix . 'users_id' => $who,
                $prefix . 'groups_id' => $_SESSION['glpigroups'],
            ];
            if (Session::haveRight('plugin_accounts_my_tech_groups', READ)) {
                $or[$prefix . 'users_id_tech']  = $who;
                $or[$prefix . 'groups_id_tech'] = $_SESSION['glpigroups'];
            }
            return ['OR' => $or];
        }

        // Personal only
        return [
            'OR' => [
                $prefix . 'users_id' => $who,
                $prefix . 'users_id_tech' => $who,
            ],
        ];
    }

    /**
     * Replay the visibility rule of getVisibilityCriteria() on the loaded item.
     *
     * The criteria only filter the lists: every direct access (form, tabs, PDF export,
     * massive actions) reaches the item without ever going through the search engine, so the
     * rule has to be enforced by the model itself, the single place all those paths share.
     */
    private function isVisibleToCurrentUser(): bool
    {
        if ($this->isNewItem()) {
            return true;
        }

        $criteria = self::getVisibilityCriteria();
        if ($criteria === []) {
            return true;
        }

        foreach ($criteria['OR'] as $field => $expected) {
            $value = (int) ($this->fields[$field] ?? 0);
            if ($value === 0) {
                // An unset owner never matches, whatever the expected value is.
                continue;
            }
            foreach ((array) $expected as $candidate) {
                if ($value === (int) $candidate) {
                    return true;
                }
            }
        }

        return false;
    }

    public function canViewItem(): bool
    {
        // parent::canViewItem() holds the entity boundary (checkEntity), it must stay first.
        return parent::canViewItem() && $this->isVisibleToCurrentUser();
    }

    public function canUpdateItem(): bool
    {
        return parent::canUpdateItem() && $this->isVisibleToCurrentUser();
    }

    /**
     * Override to inject group-based visibility filtering into the search engine.
     * This affects front/account.php list and all search-based views.
     */
    public static function getDefaultWhere(): string
    {
        global $DB;
        $criteria = self::getVisibilityCriteria(true);
        if (empty($criteria)) {
            return '';
        }

        // Convert the criteria array to a SQL WHERE clause fragment
        $iterator = new \DBmysqlIterator($DB);
        $where = $iterator->analyseCrit($criteria);

        if (empty($where)) {
            return '';
        }

        return " AND ($where)";
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
                        `entities_id` int unsigned NOT NULL default '0',
                        `is_recursive` tinyint NOT NULL default '0',
                        `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                        `login` varchar(255) collate utf8mb4_unicode_ci default NULL,
                        `encrypted_password` text collate utf8mb4_unicode_ci default NULL,
                        `encrypted_totp_secret` text collate utf8mb4_unicode_ci DEFAULT NULL,
                        `plugin_accounts_hashes_id` int unsigned NOT NULL default '0',
                        `others` varchar(255) collate utf8mb4_unicode_ci default NULL,
                        `plugin_accounts_accounttypes_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accounttypes (id)',
                        `plugin_accounts_accountstates_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_accounts_accountstates (id)',
                        `date_creation` timestamp NULL DEFAULT NULL,
                        `date_expiration` timestamp NULL DEFAULT NULL,
                        `users_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_users (id)',
                        `groups_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_groups (id)',
                        `users_id_tech` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_users (id)',
                        `groups_id_tech` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_groups (id)',
                        `locations_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_locations (id)',
                        `is_helpdesk_visible` int unsigned NOT NULL default '1',
                        `date_mod` timestamp NULL DEFAULT NULL,
                        `comment` text collate utf8mb4_unicode_ci,
                        `is_deleted` tinyint NOT NULL default '0',
                        PRIMARY KEY  (`id`),
                        KEY `name` (`name`),
                           KEY `entities_id` (`entities_id`),
                           KEY `plugin_accounts_accounttypes_id` (`plugin_accounts_accounttypes_id`),
                           KEY `plugin_accounts_accountstates_id` (`plugin_accounts_accountstates_id`),
                           KEY `users_id` (`users_id`),
                           KEY `groups_id` (`groups_id`),
                           KEY `users_id_tech` (`users_id_tech`),
                           KEY `groups_id_tech` (`groups_id_tech`),
                           KEY `date_mod` (`date_mod`),
                           KEY `is_helpdesk_visible` (`is_helpdesk_visible`),
                           KEY `is_deleted` (`is_deleted`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            //Displayprefs
            $prefs = [
                2 => 3,
                3 => 1,
                4 => 2,
                5 => 4,
                6 => 5,
                7 => 6,
            ];
            foreach ($prefs as $num => $rank) {
                if (!countElementsInTable(
                    "glpi_displaypreferences",
                    ['itemtype' => self::class,
                        'num' => $num,
                        'users_id' => 0,
                        'interface' => 'central',
                    ],
                )
                ) {
                    $DB->insert(
                        'glpi_displaypreferences',
                        ['itemtype' => self::class,
                            'num' => $num,
                            'rank' => $rank,
                            'users_id' => 0,
                            'interface' => 'central'],
                    );
                }
            }

            //Displayprefs
            $prefs = [
                2 => 3,
                3 => 1,
                4 => 2,
                5 => 4,
                6 => 5,
                7 => 6,
            ];
            foreach ($prefs as $num => $rank) {
                if (!countElementsInTable(
                    "glpi_displaypreferences",
                    ['itemtype' => self::class,
                        'num' => $num,
                        'users_id' => 0,
                        'interface' => 'helpdesk',
                    ],
                )
                ) {
                    $DB->insert(
                        'glpi_displaypreferences',
                        ['itemtype' => self::class,
                            'num' => $num,
                            'rank' => $rank,
                            'users_id' => 0,
                            'interface' => 'helpdesk'],
                    );
                }
            }
        }

        // Notification
        // Request
        $options_notif        = ['itemtype' => self::class,
            'name' => 'New Accounts'];
        $DB->insert(
            "glpi_notificationtemplates",
            $options_notif,
        );

        foreach ($DB->request([
            'FROM' => 'glpi_notificationtemplates',
            'WHERE' => $options_notif]) as $data) {
            $templates_id = $data['id'];

            if ($templates_id) {

                $DB->insert(
                    "glpi_notificationtemplatetranslations",
                    [
                        'notificationtemplates_id' => $templates_id,
                        'subject' => '##lang.account.title##',
                        'content_text' => '##lang.account.url## : ##account.url##\r\n\r\n
                        ##lang.account.entity## : ##account.entity##\r\n
                        ##IFaccount.name####lang.account.name## : ##account.name##\r\n##ENDIFaccount.name##
                        ##IFaccount.type####lang.account.type## : ##account.type##\r\n##ENDIFaccount.type##
                        ##IFaccount.state####lang.account.state## : ##account.state##\r\n##ENDIFaccount.state##
                        ##IFaccount.login####lang.account.login## : ##account.login##\r\n##ENDIFaccount.login##
                        ##IFaccount.users_id####lang.account.users_id## : ##account.users_id##\r\n##ENDIFaccount.users_id##
                        ##IFaccount.groups_id####lang.account.groups_id## : ##account.groups_id##\r\n##ENDIFaccount.groups_id##
                        ##IFaccount.others####lang.account.others## : ##account.others##\r\n##ENDIFaccount.others##
                        ##IFaccount.datecreation####lang.account.datecreation## : ##account.datecreation##\r\n##ENDIFaccount.datecreation##
                        ##IFaccount.dateexpiration####lang.account.dateexpiration## : ##account.dateexpiration##\r\n##ENDIFaccount.dateexpiration##
                        ##IFaccount.comment####lang.account.comment## : ##account.comment##\r\n##ENDIFaccount.comment##',
                        'content_html' => '&lt;p&gt;&lt;strong&gt;##lang.account.url##&lt;/strong&gt; : &lt;a href=\"##account.url##\"&gt;##account.url##&lt;/a&gt;&lt;/p&gt;
                        &lt;p&gt;&lt;strong&gt;##lang.account.entity##&lt;/strong&gt; : ##account.entity##&lt;br /&gt; ##IFaccount.name##&lt;strong&gt;##lang.account.name##&lt;/strong&gt; : ##account.name##&lt;br /&gt;##ENDIFaccount.name##  ##IFaccount.type##&lt;strong&gt;##lang.account.type##&lt;/strong&gt; : ##account.type##&lt;br /&gt;##ENDIFaccount.type##  ##IFaccount.state##&lt;strong&gt;##lang.account.state##&lt;/strong&gt; : ##account.state##&lt;br /&gt;##ENDIFaccount.state##  ##IFaccount.login##&lt;strong&gt;##lang.account.login##&lt;/strong&gt; : ##account.login##&lt;br /&gt;##ENDIFaccount.login##  ##IFaccount.users##&lt;strong&gt;##lang.account.users##&lt;/strong&gt; : ##account.users##&lt;br /&gt;##ENDIFaccount.users##  ##IFaccount.groups##&lt;strong&gt;##lang.account.groups##&lt;/strong&gt; : ##account.groups##&lt;br /&gt;##ENDIFaccount.groups##  ##IFaccount.others##&lt;strong&gt;##lang.account.others##&lt;/strong&gt; : ##account.others##&lt;br /&gt;##ENDIFaccount.others##  ##IFaccount.datecreation##&lt;strong&gt;##lang.account.datecreation##&lt;/strong&gt; : ##account.datecreation##&lt;br /&gt;##ENDIFaccount.datecreation##  ##IFaccount.dateexpiration##&lt;strong&gt;##lang.account.dateexpiration##&lt;/strong&gt; : ##account.dateexpiration##&lt;br /&gt;##ENDIFaccount.dateexpiration##  ##IFaccount.comment##&lt;strong&gt;##lang.account.comment##&lt;/strong&gt; : ##account.comment####ENDIFaccount.comment##&lt;/p&gt;',
                    ],
                );

                $DB->insert(
                    "glpi_notifications",
                    [
                        'name' => 'New Accounts',
                        'entities_id' => 0,
                        'itemtype' => self::class,
                        'event' => 'new',
                        'is_recursive' => 1,
                    ],
                );

                $options_notif        = ['itemtype' => self::class,
                    'name' => 'New Accounts',
                    'event' => 'new'];

                foreach ($DB->request([
                    'FROM' => 'glpi_notifications',
                    'WHERE' => $options_notif]) as $data_notif) {
                    $notification = $data_notif['id'];
                    if ($notification) {
                        $DB->insert(
                            "glpi_notifications_notificationtemplates",
                            [
                                'notifications_id' => $notification,
                                'mode' => 'mailing',
                                'notificationtemplates_id' => $templates_id,
                            ],
                        );
                    }
                }
            }
        }

        // Alert Expired
        $options_notif        = ['itemtype' => self::class,
            'name' => 'Alert Accounts'];
        // Request
        $DB->insert(
            "glpi_notificationtemplates",
            $options_notif,
        );

        foreach ($DB->request([
            'FROM' => 'glpi_notificationtemplates',
            'WHERE' => $options_notif]) as $data) {
            $templates_id = $data['id'];

            if ($templates_id) {

                $DB->insert(
                    "glpi_notificationtemplatetranslations",
                    [
                        'notificationtemplates_id' => $templates_id,
                        'subject' => '##account.action## : ##account.entity##',
                        'content_text' => '##lang.account.entity## :##account.entity##
                        ##FOREACHaccounts##
                        ##lang.account.name## : ##account.name## - ##lang.account.dateexpiration## : ##account.dateexpiration##
                        ##ENDFOREACHaccounts##',
                        'content_html' => '&lt;p&gt;##lang.account.entity## :##account.entity##&lt;br /&gt; &lt;br /&gt;
                        ##FOREACHaccounts##&lt;br /&gt;
                        ##lang.account.name##  : ##account.name## - ##lang.account.dateexpiration## :  ##account.dateexpiration##&lt;br /&gt;
                        ##ENDFOREACHaccounts##&lt;/p&gt;',
                    ],
                );

                $DB->insert(
                    "glpi_notifications",
                    [
                        'name' => 'Alert Expired Accounts',
                        'entities_id' => 0,
                        'itemtype' => self::class,
                        'event' => 'ExpiredAccounts',
                        'is_recursive' => 1,
                    ],
                );

                $options_notif        = ['itemtype' => self::class,
                    'name' => 'Alert Expired Accounts',
                    'event' => 'ExpiredAccounts'];

                foreach ($DB->request([
                    'FROM' => 'glpi_notifications',
                    'WHERE' => $options_notif]) as $data_notif) {
                    $notification = $data_notif['id'];
                    if ($notification) {
                        $DB->insert(
                            "glpi_notifications_notificationtemplates",
                            [
                                'notifications_id' => $notification,
                                'mode' => 'mailing',
                                'notificationtemplates_id' => $templates_id,
                            ],
                        );
                    }
                }

                $DB->insert(
                    "glpi_notifications",
                    [
                        'name' => 'Alert Accounts Which Expire',
                        'entities_id' => 0,
                        'itemtype' => self::class,
                        'event' => 'AccountsWhichExpire',
                        'is_recursive' => 1,
                    ],
                );

                $options_notif        = ['itemtype' => Account::class,
                    'name' => 'Alert Accounts Which Expire',
                    'event' => 'AccountsWhichExpire'];

                foreach ($DB->request([
                    'FROM' => 'glpi_notifications',
                    'WHERE' => $options_notif]) as $data_notif) {
                    $notification = $data_notif['id'];
                    if ($notification) {
                        $DB->insert(
                            "glpi_notifications_notificationtemplates",
                            [
                                'notifications_id' => $notification,
                                'mode' => 'mailing',
                                'notificationtemplates_id' => $templates_id,
                            ],
                        );
                    }
                }
            }
        }
    }
}
