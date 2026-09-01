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

        // Encrypt TOTP secret server-side if provided
        $input = self::encryptTotpSecret($input, $input['plugin_accounts_hashes_id'] ?? 0);

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
     * @param datas $input
     *
     * @return datas
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
        // Transparent re-encryption on save: upgrade legacy v1 records to v2, and early
        // v2 records that still lack the HMAC segment to authenticated v2. Only possible
        // when the fingerprint is available (AesKey table for the entity, or POSTed key).
        if (isset($input['encrypted_password']) && !empty($input['encrypted_password'])
            && AccountCrypto::needsReencryption($input['encrypted_password'])
        ) {
            $hash_id = (int) ($this->fields['plugin_accounts_hashes_id']
                ?? ($input['plugin_accounts_hashes_id'] ?? 0));

            // Resolve the fingerprint via the shared helper, which validates a POSTed key
            // against the stored hash. Never re-encrypt under an unverified key: legacy v1
            // (AesCtr, no MAC) can decrypt to non-empty garbage with a wrong key, which
            // would then be re-encrypted as v2 and silently corrupt the original password.
            $fingerprint = self::resolveFingerprint($hash_id, $input['aeskey'] ?? null);

            if ($fingerprint !== null) {
                // decrypt() transparently reads both v1 and v2 (with/without MAC).
                $plaintext = AccountCrypto::decrypt($input['encrypted_password'], $fingerprint);
                if ($plaintext !== '') {
                    // Re-encrypt as authenticated v2 (encrypt-then-MAC).
                    $input['encrypted_password'] = AccountCrypto::encrypt($plaintext, $fingerprint);
                }
            }
        }

        // Encrypt TOTP secret server-side if a new one was provided
        $hash_id = $input['plugin_accounts_hashes_id']
            ?? ($this->fields['plugin_accounts_hashes_id'] ?? 0);
        $input = self::encryptTotpSecret($input, $hash_id);

        return $input;
    }

    /**
     * Resolve the encryption fingerprint (AES key) for a given hash record.
     *
     * Prefers the AesKey stored in DB for that hash. Otherwise falls back to a key
     * posted with the form, but ONLY after verifying it against the stored hash
     * (double SHA-256) with hash_equals(). This prevents a wrong key from being used
     * to (re-)encrypt a secret: legacy v1 (AesCtr, no MAC) can decrypt to non-empty
     * garbage under a wrong key, so the plaintext non-emptiness check alone is not a
     * safe guard. Returns null when no valid fingerprint is available.
     *
     * @param int         $hash_id The hash (fingerprint) ID
     * @param string|null $posted  The key posted with the form ($input['aeskey']), if any
     * @return string|null The validated fingerprint, or null
     */
    private static function resolveFingerprint(int $hash_id, ?string $posted): ?string
    {
        if (!$hash_id) {
            return null;
        }

        $aeskey = new AesKey();
        if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $hash_id])
            && !empty($aeskey->fields['name'])) {
            return $aeskey->getDecryptedName();
        }

        // Key not stored in DB: accept the posted key only if it matches the stored
        // verifier. AccountCrypto::verify handles both the new salted PBKDF2 format and
        // legacy double SHA-256.
        if (!empty($posted)) {
            $hashRecord = new Hash();
            if ($hashRecord->getFromDB($hash_id)
                && AccountCrypto::verify($posted, (string) ($hashRecord->fields['hash'] ?? ''))) {
                return $posted;
            }
        }

        return null;
    }

    /**
     * Encrypt the TOTP secret (submitted as plaintext via form POST) using
     * the same fingerprint as the password. The plaintext field
     * 'totp_secret_plain' is removed and replaced with 'encrypted_totp_secret'.
     *
     * @param array $input   Form input
     * @param int   $hash_id The hash (fingerprint) ID to use for encryption
     * @return array Modified input
     */
    private static function encryptTotpSecret(array $input, int $hash_id): array
    {
        if (!isset($input['totp_secret_plain']) || $input['totp_secret_plain'] === '') {
            unset($input['totp_secret_plain']);
            return $input;
        }

        $fingerprint = self::resolveFingerprint($hash_id, $input['aeskey'] ?? null);

        if ($fingerprint !== null) {
            $input['encrypted_totp_secret'] = addslashes(AccountCrypto::encrypt(
                $input['totp_secret_plain'],
                $fingerprint,
            ));
        } else {
            Session::addMessageAfterRedirect(
                __('TOTP secret not saved: no encryption key available. Please configure an encryption key first.', 'accounts'),
                false,
                ERROR,
            );
        }
        unset($input['totp_secret_plain']);
        return $input;
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
        if (!$this->canView()) {
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
        $alerthash = "";
        $aeskey_uncrypted = false;
        if (!empty($hashes)) {
            foreach ($hashes as $hash) {
                if (empty($hash['hash'])) {
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
                $hash = $hashclass->fields["hash"];
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
                    foreach ($ids as $key) {
                        $item->getFromDB($key);
                        // --- Step 1: Resolve account type in destination entity ---
                        $type = AccountType::transfer(
                            $item->fields["plugin_accounts_accounttypes_id"],
                            $input['entities_id'],
                        );

                        // --- Step 2: Re-encrypt password with destination fingerprint ---
                        $reencrypted_password = null;
                        $new_hash_id = 0;

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
                                    $input['entities_id'],
                                    $dest_hash->maybeRecursive(),
                                );
                                $dest_hashes = getAllDataFromTable('glpi_plugin_accounts_hashes', $restrict);

                                if (count($dest_hashes) > 0) {
                                    // Use first available fingerprint in destination entity
                                    $dest_hash_row = reset($dest_hashes);
                                    $new_hash_id = $dest_hash_row['id'];

                                    $dest_aeskey = new AesKey();
                                    if ($dest_aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $new_hash_id])
                                        && !empty($dest_aeskey->fields['name'])) {
                                        $dest_aes_key_value = $dest_aeskey->getDecryptedName();

                                        // Re-encrypt with destination key (raw AES key as fingerprint).
                                        $reencrypted_password = addslashes(
                                            AccountCrypto::encrypt($plaintext, $dest_aes_key_value),
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
                            && isset($src_aes_key_value, $dest_aes_key_value)) {
                            $plain_totp = AccountCrypto::decrypt(
                                $item->fields['encrypted_totp_secret'],
                                $src_aes_key_value,
                            );
                            $reencrypted_totp = addslashes(
                                AccountCrypto::encrypt($plain_totp, $dest_aes_key_value),
                            );
                        } elseif (!empty($item->fields['encrypted_totp_secret']) && $reencrypted_password === null) {
                            $reencrypted_totp = '';
                        }

                        // --- Step 3: Build update values ---
                        $values = ['id' => $key, 'entities_id' => $input['entities_id']];

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
                foreach ($ids as $key) {
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
        $delay = $config->fields["delay_expired"];

        if ($delay) {
            $criteria = [
                'SELECT' => '*',
                'FROM' => self::getTable(),
                'WHERE' => [
                    'NOT' => [
                        'date_expiration' => null,
                    ],
                    'is_deleted' => 0,
                    new QueryExpression("DATEDIFF(CURDATE(), " . $DB->quoteName('date_expiration') . ") > $delay"),
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
        $delay = $config->fields["delay_whichexpire"];

        if ($delay) {
            $criteria = [
                'SELECT' => '*',
                'FROM' => self::getTable(),
                'WHERE' => [
                    'NOT' => ['date_expiration' => null],
                    'is_deleted' => 0,
                    new QueryExpression("DATEDIFF(CURDATE(), " . $DB->quoteName('date_expiration') . ") > -$delay"),
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
     * Mirrors plugin_accounts_addDefaultWhere() (hook.php) so the tree exposes exactly
     * what the account list would: entity scope, plus the "own accounts only" restriction
     * when the profile lacks the plugin_accounts_see_all_users right. The tree is opened
     * from a page gated on that right, but its endpoint is reachable on its own.
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

        if (!Session::haveRight('plugin_accounts_see_all_users', 1)) {
            $who = Session::getLoginUserID();

            if (
                count($_SESSION['glpigroups'] ?? [])
                && Session::haveRight('plugin_accounts_my_groups', 1)
            ) {
                $criteria['AND'][] = [
                    'OR' => [
                        "$table.groups_id" => $_SESSION['glpigroups'],
                        "$table.users_id"  => $who,
                    ],
                ];
            } else {
                $criteria['AND'][] = ["$table.users_id" => $who];
            }
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
            ],
        ];

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
     * @return array GLPI DBUtils criteria array, empty if no restriction needed
     */
    public static function getVisibilityCriteria(): array
    {
        // Super-admin (config right) or explicit "see all" right: no restriction
        if (Session::haveRight('plugin_accounts_see_all_users', READ)
            || Session::haveRight('config', READ)) {
            return [];
        }
        $who = Session::getLoginUserID();

        // Group-based visibility
        if (Session::haveRight('plugin_accounts_my_groups', READ)
            && !empty($_SESSION['glpigroups'])) {
            $or = [
                'users_id' => $who,
                'groups_id' => $_SESSION['glpigroups'],
            ];
            if (Session::haveRight('plugin_accounts_my_tech_groups', READ)) {
                $or['users_id_tech']  = $who;
                $or['groups_id_tech'] = $_SESSION['glpigroups'];
            }
            return ['OR' => $or];
        }

        // Personal only
        return [
            'OR' => [
                'users_id' => $who,
                'users_id_tech' => $who,
            ],
        ];
    }

    /**
     * Override to inject group-based visibility filtering into the search engine.
     * This affects front/account.php list and all search-based views.
     */
    public static function getDefaultWhere(): string
    {
        global $DB;
        $criteria = self::getVisibilityCriteria();
        if (empty($criteria)) {
            return '';
        }

        // Convert the criteria array to a SQL WHERE clause fragment
        $iterator = new \DBmysqlIterator($DB);
        $where = $iterator->analyseCrit($criteria);

        if (empty($where)) {
            return '';
        }

        $table = self::getTable();
        // Prefix unqualified column references with the table name
        $where = preg_replace('/\b(users_id|users_id_tech|groups_id|groups_id_tech)\b/', "`$table`.`$1`", $where);

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
