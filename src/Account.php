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
use Group;
use Html;
use Item_Problem;
use Item_Project;
use Location;
use MassiveAction;
use NotificationEvent;
use Plugin;
use Session;
use Twig\TwigFilter;
use User;

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
        $this->addStandardTab(Account_Item::class, $ong, $options);
        $this->addStandardTab('Item_Ticket', $ong, $options);
        $this->addStandardTab('Item_Problem', $ong, $options);
        //$this->addStandardTab('Change_Item', $ong, $options);
        $this->addStandardTab('Item_Project', $ong, $options);
        $this->addStandardTab('Document_Item', $ong, $options);
        $this->addStandardTab('Notepad', $ong, $options);
        if (Session::getCurrentInterface() == 'central') {
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
        $hash = 0;
        $hash_id = 0;
        $restrict = getEntitiesRestrictCriteria(
            "glpi_plugin_accounts_hashes",
            '',
            $this->getEntityID(),
            $hashclass->maybeRecursive()
        );
        $hashes = getAllDataFromTable("glpi_plugin_accounts_hashes", $restrict);

        if (!empty($hashes)) {
            if (count($hashes) > 1) {
                echo "<div class='alert alert-warning d-flex'>";
                echo __s(
                    'WARNING : there are multiple encryption keys for this entity. The encryption key of this entity will be used',
                    'accounts'
                );
                echo "</div>";

                foreach ($hashes as $hashe) {
                    if ($hashe["entities_id"] == $this->getEntityID()) {
                        $hash = $hashe["hash"];
                        $hash_id = $hashe["id"];
                    }
                }
            } else {
                foreach ($hashes as $hashe) {
                    $hash = $hashe["hash"];
                    $hash_id = $hashe["id"];
                }
            }

            if (empty($hash)) {
                $alert = __s('Your encryption key is malformed, please generate the hash', 'accounts');
                echo "<div class='alert alert-warning d-flex'>";
                echo $alert;
                echo "</div>";
                return false;
            }
        } else {
            $alert = __s('There is no encryption key for this entity', 'accounts');
            echo "<div class='alert alert-warning d-flex'>";
            echo $alert;
            echo "</div>";
            return false;
        }

        $aeskey = new AesKey();
        if ($hash) {
            $aeskey_uncrypted = false;
            if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id'  => $hash_id])
                && $aeskey->fields["name"]) {
                $aeskey_uncrypted = $aeskey->fields["name"];
            }
        }

        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('@accounts/account.html.twig', [
            'item' => $this,
            'nbhashes' => $nbhashes,
            'hash' => $hash,
            'aeskey_uncrypted' => $aeskey_uncrypted,
            'root_accounts_doc' => PLUGIN_ACCOUNTS_WEBDIR,
            'params' => $options,
        ]);

        if (empty($ID)) {
            echo "<br><table class='tab_cadre'>
               <tbody>
               <tr class='tab_bg_1 center'><th colspan ='2'>" . __s('Generate password', 'accounts') . "</th></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-0\" /></td><td><label for=\"char-0\"> " . __s(
                "Numbers",
                "accounts"
            ) . " <small>(0123456789)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-1\" /></td><td><label for=\"char-1\"> " . __s(
                "Lowercase",
                "accounts"
            ) . " <small>(abcdefghijklmnopqrstuvwxyz)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-2\" /></td><td><label for=\"char-2\"> " . __s(
                "Uppercase",
                "accounts"
            ) . " <small>(ABCDEFGHIJKLMNOPQRSTUVWXYZ)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" id=\"char-3\" /></td><td><label for=\"char-3\"> " . __s(
                "Special characters",
                "accounts"
            ) . " <small>(!\"#$%&amp;'()*+,-./:;&lt;=&gt;?@[\]^_`{|}~)</small></label></td></tr>
               <tr class='tab_bg_1'>
                        <td><label for='length'>" . __s("Length", "accounts") . "</label></td>
                        <td><input type='number' min='1' value='8' step='1' id='length' style='width:4em'  /> " . __s(
                " characters",
                "accounts"
            ) . "</td>
                     </tr>
               <tr id='fakeupdate'></tr>
               <tr class='tab_bg_2 center'><td colspan='2'>&nbsp
               <button type='button' id='generatePass' name='generatePass' class='submit btn btn-primary' value='" . __s(
                'Generate',
                'accounts'
            ) . "'>" . __s('Generate', 'accounts') . "</button></td></tr>
               </tbody>
               </table>";
            Ajax::updateItemOnEvent(
                "generatePass",
                "fakeupdate",
                PLUGIN_ACCOUNTS_WEBDIR . "/ajax/generatepassword.php",
                ["password" => 1],
                ["click"]
            );
        }

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
            'DISTINCT'        => true,
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
            $subquery['WHERE'] = $subquery['WHERE'] + ['id' => ['NOT IN',  array_filter($p['used'])]];
            ;
        }

        $criteria = [
            'FROM'      => 'glpi_plugin_accounts_accounttypes',
            'WHERE'     => [
                'id'  => new QuerySubQuery($subquery),
            ],
            'GROUPBY'   => 'name',
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
                        $type = AccountType::transfer(
                            $item->fields["plugin_accounts_accounttypes_id"],
                            $input['entities_id']
                        );
                        if ($type > 0) {
                            $values["id"] = $key;
                            $values["plugin_accounts_accounttypes_id"] = $type;
                            $item->update($values);
                        }

                        unset($values);
                        $values["id"] = $key;
                        $values["entities_id"] = $input['entities_id'];

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
                'FROM'   => self::getTable(),
                'WHERE'  => [
                    'NOT' => ['date_expiration' => null,
                    ],
                    'is_deleted'   => 0,
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
                'FROM'   => self::getTable(),
                'WHERE'  => [
                    'NOT' => ['date_expiration' => null],
                    'is_deleted'   => 0,
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
        if ($all) {
            return self::$types;
        }

        // Only allowed types
        $types = self::$types;

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
        $menu['links'][$image] = Hash::getSearchURL(false);
        if (self::canCreate()) {
            $menu['links']['add'] = self::getFormURL(false);
        }

        $menu['options']['account']['title'] = self::getTypeName(2);
        $menu['options']['account']['page'] = self::getSearchURL(false);
        $menu['options']['account']['links']['search'] = Account::getSearchURL(false);
        $menu['options']['account']['links'][$image] = Hash::getSearchURL(false);
        if (Account::canCreate()) {
            $menu['options']['account']['links']['add'] = self::getFormURL(false);
        }

        $menu['options']['hash']['title'] = Hash::getTypeName(2);
        $menu['options']['hash']['page'] = Hash::getSearchURL(false);
        $menu['options']['hash']['links']['search'] = Hash::getSearchURL(false);
        $menu['options']['hash']['links'][$image] = Hash::getSearchURL(false);

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
}
