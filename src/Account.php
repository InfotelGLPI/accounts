<?php

/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2009-2022 by the accounts Development Team.

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

namespace GlpiPlugin\Accounts;

use Ajax;
use Alert;
use Change_Item;
use CommonDBTM;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;
use Glpi\Features\Clonable;
use Html;
use Item_Problem;
use Item_Project;
use Location;
use MassiveAction;
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
                    'accounts'
                ),
                false,
                WARNING
            );
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
        if (isset($input["plugin_accounts_hashes_id"])
            && !Session::haveRight('plugin_accounts_hash', UPDATE)) {
            unset($input['plugin_accounts_hashes_id']);
        }
        // Transparent v1 → v2 re-encryption on save
        // Only possible if the AES key is available (stored in AesKeys table)
        if (isset($input['encrypted_password']) && !empty($input['encrypted_password'])
            && AccountCrypto::isLegacyFormat($input['encrypted_password'])
        ) {
            $hash_id = $this->fields['plugin_accounts_hashes_id']
                ?? ($input['plugin_accounts_hashes_id'] ?? 0);

            $aeskey = new AesKey();
            if ($hash_id) {
                if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $hash_id])
                    && !empty($aeskey->fields['name'])) {
                    $fingerprint = $aeskey->fields['name'];
                    $old_hash = hash('sha256', $fingerprint);
                    // Decrypt v1
                    $plaintext = AesCtr::decrypt($input['encrypted_password'], $old_hash, 256);
                    if (!empty($plaintext)) {
                        // Re-encrypt as v2
                        $input['encrypted_password'] = AccountCrypto::encrypt($plaintext, $fingerprint);
                    }
                } else {
                    $fingerprint = $input['aeskey'];
                    $old_hash = hash('sha256', $fingerprint);
                    // Decrypt v1
                    $plaintext = AesCtr::decrypt($input['encrypted_password'], $old_hash, 256);
                    if (!empty($plaintext)) {
                        // Re-encrypt as v2
                        $input['encrypted_password'] = AccountCrypto::encrypt($plaintext, $fingerprint);
                    }
                }
            }
        }


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
            $hashclass->maybeRecursive()
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
            $hashclass->maybeRecursive()
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

            $hashclass->getFromDBByCrit(['id' => $this->fields["plugin_accounts_hashes_id"]]);
            if (count($hashclass->fields) > 0) {
                $hash = $hashclass->fields["hash"];
            } else {
                $alerthash = __(
                    'There is no encryption key associated to this account, please select one above',
                    'accounts'
                );
            }
        } else {
            $alert = __s('There is no encryption key for this entity', 'accounts');
            echo "<div class='alert alert-warning d-flex'>";
            echo $alert;
            echo "</div>";
            return false;
        }

        $aeskey = new AesKey();
        if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $this->fields["plugin_accounts_hashes_id"]])
            && $aeskey->fields["name"]) {
            $aeskey_uncrypted = $aeskey->fields["name"];
        }

        $canupdateHash = Session::haveRight('plugin_accounts_hash', UPDATE);

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
                true
            );

        if (count($p['used'])) {
            $subquery['WHERE'] = $subquery['WHERE'] + ['id' => ['NOT IN', array_filter($p['used'])]];;
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
            false
        );
        $out .= "<span id='show_" . $p['name'] . "$rand'>";
        $out .= "</span>\n";

        $params['accounttype'] = 0;
        $out .= Ajax::updateItem(
            "show_" . $p['name'] . $rand,
            PLUGIN_ACCOUNTS_WEBDIR . "/ajax/dropdownTypeAccounts.php",
            $params,
            false
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
                    'Associate'
                );
                $actions[Account::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'uninstall'] = _x(
                    'button',
                    'Dissociate'
                );

                if (Session::haveRight('transfer', READ)
                    && Session::isMultiEntitiesMode()
                ) {
                    $actions[Account::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfer'] = __s(
                        'Transfer'
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
                foreach ($ids as $key) {
                    if (!$dbu->countElementsInTable(
                        'glpi_plugin_accounts_accounts_items',
                        [
                            "itemtype" => $item->getType(),
                            "items_id" => $key,
                            "plugin_accounts_accounts_id" => $input['plugin_accounts_accounts_id'],
                        ]
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
                            $input['entities_id']
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
                                $src_aes_key_value = $src_aeskey->fields['name'];
                                $src_hash_value = hash('sha256', $src_aes_key_value);

                                // Decrypt with source key
                                $plaintext = AesCtr::decrypt(
                                    $item->fields['encrypted_password'],
                                    $src_hash_value,
                                    256
                                );

                                // Find destination entity's fingerprint
                                $dest_hash = new Hash();
                                $restrict = getEntitiesRestrictCriteria(
                                    'glpi_plugin_accounts_hashes',
                                    '',
                                    $input['entities_id'],
                                    $dest_hash->maybeRecursive()
                                );
                                $dest_hashes = getAllDataFromTable('glpi_plugin_accounts_hashes', $restrict);

                                if (count($dest_hashes) > 0) {
                                    // Use first available fingerprint in destination entity
                                    $dest_hash_row = reset($dest_hashes);
                                    $new_hash_id = $dest_hash_row['id'];

                                    $dest_aeskey = new AesKey();
                                    if ($dest_aeskey->getFromDBByCrit(['plugin_accounts_hashes_id' => $new_hash_id])
                                        && !empty($dest_aeskey->fields['name'])) {
                                        $dest_aes_key_value = $dest_aeskey->fields['name'];
                                        $dest_hash_value = hash('sha256', $dest_aes_key_value);

                                        // Re-encrypt with destination key
                                        $reencrypted_password = addslashes(
                                            AesCtr::encrypt($plaintext, $dest_hash_value, 256)
                                        );
                                    }
                                }

                                // If no destination fingerprint found — warn and skip re-encryption
                                if ($reencrypted_password === null) {
                                    Session::addMessageAfterRedirect(
                                        sprintf(
                                            __s(
                                                'Account "%s" transferred but no fingerprint found in destination entity. Password was cleared for security.',
                                                'accounts'
                                            ),
                                            $item->fields['name']
                                        ),
                                        false,
                                        WARNING
                                    );
                                    // Clear password rather than leave it encrypted with wrong key
                                    $reencrypted_password = '';
                                }
                            }
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
                    'description' => __s('Accounts expired or accounts which expires', 'accounts'),
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
                                'Accounts expired or accounts which expires',
                                'accounts'
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
                    ]
                )) {
                    $message = $account_messages[$type][$entity];
                    $cron_status = 1;
                    if ($task) {
                        $task->log(
                            Dropdown::getDropdownName(
                                "glpi_entities",
                                $entity
                            ) . ":  $message\n"
                        );
                        $task->addVolume(1);
                    } else {
                        Session::addMessageAfterRedirect(
                            Dropdown::getDropdownName(
                                "glpi_entities",
                                $entity
                            ) . ":  $message"
                        );
                    }
                } else {
                    if ($task) {
                        $task->log(
                            Dropdown::getDropdownName("glpi_entities", $entity)
                            . ":  Send accounts alert failed\n"
                        );
                    } else {
                        Session::addMessageAfterRedirect(
                            Dropdown::getDropdownName("glpi_entities", $entity)
                            . ":  Send accounts alert failed",
                            false,
                            ERROR
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
     * Display types of used accounts
     *
     * @param $target
     */
    public static function showSelector($target)
    {
        $rand = mt_rand();
        Plugin::loadLang('accounts');
        echo Html::css("/lib/base.css");
        echo Html::script("/lib/base.js");
        echo Html::css(PLUGIN_ACCOUNTS_WEBDIR . "/lib/jstree/themes/default/style.min.css");
        echo Html::css(PLUGIN_ACCOUNTS_WEBDIR . "/lib/jstree/jstree-glpi.css");
        echo "<div class='alert alert-info d-flex'>" . __s(
                'Select the wanted account type',
                'accounts'
            ) . "</div><br>";
        echo "<a href='" . $target . "?reset=reset' target='_blank' title=\""
            . __s('Show all') . "\">" . str_replace(" ", "&nbsp;", __s('Show all')) . "</a>";
        $root = PLUGIN_ACCOUNTS_WEBDIR;
        $js = "   $(function() {
                  $.getScript('{$root}/lib/jstree/jstree.min.js', function(data, textStatus, jqxhr) {
                     $('#tree_accounttypes$rand').jstree({
                        // the `plugins` array allows you to configure the active plugins on this instance
                        'plugins' : ['search', 'qload'],
                        'search': {
                           'case_insensitive': true,
                           'show_only_matches': true,
                           'ajax': {
                              'type': 'POST',
                              'url': '" . PLUGIN_ACCOUNTS_WEBDIR . "/ajax/accounttreetypes.php'
                           }
                        },
                        'qload': {
                           'prevLimit': 50,
                           'nextLimit': 30,
                           'moreText': '" . __s('Load more...') . "'
                        },
                        'core': {
//                           'themes': {
//                              'name': 'default'
//                           },
                           'animation': 0,
                           'data': {
                              'url': function(node) {
                                 return node.id === '#' ?
                                    '" . PLUGIN_ACCOUNTS_WEBDIR . "/ajax/accounttreetypes.php?node=-1' :
                                    '" . PLUGIN_ACCOUNTS_WEBDIR . "/ajax/accounttreetypes.php?node='+node.id;
                              }
                           }
                        }
                     });
                  });
               });";

        echo Html::scriptBlock($js);
        echo "<div class='left' style='width:100%'>";
        echo "<div id='tree_accounttypes$rand'></div>";
        echo "</div>";
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
                if (empty($values[$field])) {
                    return __s('Don\'t expire', 'accounts');
                } else {
                    return Html::convDate($values[$field]);
                }
                break;
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
            printf(__s('Created from the template %s'), $this->fields['template_name']);
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
                'accounts'
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
                'COUNT' => 'id AS cpt'
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
                        'accounts'
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
        // Super-admin: see everything
        if (Session::haveRight('plugin_accounts_see_all_users', READ)) {
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
}
