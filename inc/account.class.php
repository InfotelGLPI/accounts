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


if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginAccountsAccount
 */
class PluginAccountsAccount extends CommonDBTM
{
    public static $rightname = "plugin_accounts";

    public static $types = ['Computer', 'Monitor', 'NetworkEquipment', 'Peripheral',
                     'Phone', 'Printer', 'Software', 'SoftwareLicense', 'Entity',
                     'Contract', 'Supplier', 'Certificate', 'Cluster'];

    public $dohistory  = true;
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

    /**
     * Actions done when item is deleted from the database
     */
    public function cleanDBonPurge()
    {
        $temp = new PluginAccountsAccount_Item();
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
           'id'   => 'common',
           'name' => self::getTypeName(2)
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
               'id'            => '1',
               'table'         => $this->getTable(),
               'field'         => 'name',
               'name'          => __('Name'),
               'datatype'      => 'itemlink',
               'itemlink_type' => 'PluginAccountsAccount',
               'massiveaction' => false,
               'searchtype'    => 'contains'
            ];
        } else {
            $tab[] = [
               'id'            => '1',
               'table'         => $this->getTable(),
               'field'         => 'name',
               'name'          => __('Name'),
               'datatype'      => 'itemlink',
               'itemlink_type' => 'PluginAccountsAccount',
               'massiveaction' => false
            ];
        }

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
               'id'         => '2',
               'table'      => 'glpi_plugin_accounts_accounttypes',
               'field'      => 'name',
               'name'       => __('Type'),
               'datatype'   => 'dropdown',
               'searchtype' => 'contains'
            ];
        } else {
            $tab[] = [
               'id'       => '2',
               'table'    => 'glpi_plugin_accounts_accounttypes',
               'field'    => 'name',
               'name'     => __('Type'),
               'datatype' => 'dropdown'
            ];
        }

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
               'id'         => '16',
               'table'      => 'glpi_users',
               'field'      => 'name',
               'name'       => __('Affected User', 'accounts'),
               'searchtype' => 'contains'
            ];
        } else {
            $tab[] = [
               'id'    => '16',
               'table' => 'glpi_users',
               'field' => 'name',
               'name'  => __('Affected User', 'accounts'),
            ];
        }

        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab[] = [
           'id'    => '4',
           'table' => $this->getTable(),
           'field' => 'login',
           'name'  => __('Login')
        ];

        $tab[] = [
           'id'       => '5',
           'table'    => $this->getTable(),
           'field'    => 'date_creation',
           'name'     => __('Creation date'),
           'datatype' => 'date'
        ];

        $tab[] = [
           'id'       => '6',
           'table'    => $this->getTable(),
           'field'    => 'date_expiration',
           'name'     => __('Expiration date'),
           'datatype' => 'date'
        ];

        $tab[] = [
           'id'       => '7',
           'table'    => $this->getTable(),
           'field'    => 'comment',
           'name'     => __('Comments'),
           'datatype' => 'text'
        ];

        if (Session::getCurrentInterface() == 'central') {
            $tab[] = [
               'id'            => 8,
               'table'         => 'glpi_plugin_accounts_accounts_items',
               'field'         => 'items_id',
               'nosearch'      => true,
               'name'          => _n('Associated item', 'Associated items', 2),
               'forcegroupby'  => true,
               'massiveaction' => false,
               'joinparams'    => ['jointype' => 'child']
            ];
        }

        $tab[] = [
           'id'    => '9',
           'table' => $this->getTable(),
           'field' => 'others',
           'name'  => __('Others')
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
               'id'         => '10',
               'table'      => 'glpi_plugin_accounts_accountstates',
               'field'      => 'name',
               'name'       => __('Status'),
               'searchtype' => 'contains'
            ];
        } else {
            $tab[] = [
               'id'       => '10',
               'table'    => 'glpi_plugin_accounts_accountstates',
               'field'    => 'name',
               'name'     => __('Status'),
               'datatype' => 'dropdown'
            ];
        }


        if (Session::getCurrentInterface() == 'central') {
            $tab[] = [
               'id'       => 11,
               'table'    => $this->getTable(),
               'field'    => 'is_recursive',
               'name'     => __('Child entities'),
               'datatype' => 'bool'
            ];
        }

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
               'id'         => '12',
               'table'      => 'glpi_groups',
               'field'      => 'completename',
               'name'       => __('Group'),
               'datatype'   => 'dropdown',
               'condition'  => ['`is_itemgroup`' => 1],
               'searchtype' => 'contains'
            ];
        } else {
            $tab[] = [
               'id'        => '12',
               'table'     => 'glpi_groups',
               'field'     => 'completename',
               'name'      => __('Group'),
               'datatype'  => 'dropdown',
               'condition' => ['`is_itemgroup`' => 1]
            ];
        }


        if (Session::getCurrentInterface() == 'central') {
            $tab[] = [
               'id'       => 13,
               'table'    => $this->getTable(),
               'field'    => 'is_helpdesk_visible',
               'name'     => __('Associable to a ticket'),
               'datatype' => 'bool',
            ];
        }

        $tab[] = [
           'id'            => '14',
           'table'         => $this->getTable(),
           'field'         => 'date_mod',
           'name'          => __('Last update'),
           'massiveaction' => false,
           'datatype'      => 'datetime'
        ];

        $tab[] = [
           'id'        => '17',
           'table'     => 'glpi_users',
           'field'     => 'name',
           'linkfield' => 'users_id_tech',
           'name'      => __('Technician in charge of the hardware'),
           'datatype'  => 'dropdown',
           'right'     => 'interface'
        ];

        $tab[] = [
           'id'        => '18',
           'table'     => 'glpi_groups',
           'field'     => 'completename',
           'linkfield' => 'groups_id_tech',
           'name'      => __('Group in charge of the hardware'),
           'condition' => ['`is_assign`' => 1],
           'datatype'  => 'dropdown'
        ];

        $tab[] = [
           'id'       => '30',
           'table'    => $this->getTable(),
           'field'    => 'id',
           'name'     => __('ID'),
           'datatype' => 'number'
        ];

        $tab[] = [
           'id'    => '81',
           'table' => 'glpi_entities',
           'field' => 'entities_id',
           'name'  => __('Entity-ID')
        ];

        $tab[] = [
           'id'       => '80',
           'table'    => 'glpi_entities',
           'field'    => 'completename',
           'name'     => __('Entity'),
           'datatype' => 'dropdown'
        ];

        $tab[] = [
           'id'       => '86',
           'table'    => $this->getTable(),
           'field'    => 'is_recursive',
           'name'     => __('Child entities'),
           'datatype' => 'bool'
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
        $this->addStandardTab('PluginAccountsAccount_Item', $ong, $options);
        $this->addStandardTab('Ticket', $ong, $options);
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
     * @param datas $input
     *
     * @return \datas $input
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
     * Return the SQL command to retrieve linked object
     *
     * @return SQL command which return a set of (itemtype, items_id)
     */
    public function getSelectLinkedItem()
    {
        return "SELECT `itemtype`, `items_id`
               FROM `glpi_plugin_accounts_accounts_items`
               WHERE `plugin_accounts_accounts_id`='" . $this->fields['id'] . "'";
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
        global $CFG_GLPI;
        if (!$this->canView()) {
            return false;
        }

        $hashclass = new PluginAccountsHash();
        $dbu       = new DbUtils();

        $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_accounts_hashes", '', '', $hashclass->maybeRecursive());
        if ($ID < 1 && $dbu->countElementsInTable("glpi_plugin_accounts_hashes", $restrict) == 0) {
            echo "<div class='alert alert-important alert-warning d-flex'>";
            echo __('There is no encryption key for this entity', 'accounts');
            echo "</div>";
            return false;
        }
      //      if ($ID != 0) {
      //         // Create item
      //         $this->check($ID, UPDATE);
      //         $this->getEmpty();
      //      }
        $options["formoptions"] = "id = 'account_form'";
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";

        echo "<td>" . __('Name') . "</td>";
        echo "<td>";
        echo Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);
        echo "</td>";

        echo "<td>" . __('Status') . "</td><td>";
        Dropdown::show(
            'PluginAccountsAccountState',
            ['value' => $this->fields["plugin_accounts_accountstates_id"]]
        );
        echo "</td>";

        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        echo "<td>" . __('Login') . "</td>";
        echo "<td>";
        echo Html::input('login', ['value' => $this->fields['login'], 'size' => 40]);
        echo "</td>";

        echo "<td>" . __('Type') . "</td><td>";
        Dropdown::show(
            'PluginAccountsAccountType',
            ['value' => $this->fields["plugin_accounts_accounttypes_id"]]
        );
        echo "</td>";

        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        //hash
        $hash     = 0;
        $hash_id  = 0;
        $restrict = $dbu->getEntitiesRestrictCriteria(
            "glpi_plugin_accounts_hashes",
            '',
            $this->getEntityID(),
            $hashclass->maybeRecursive()
        );
        $hashes   = $dbu->getAllDataFromTable("glpi_plugin_accounts_hashes", $restrict);

        if (!empty($hashes)) {
            if (count($hashes) > 1) {
                echo "<div class='alert alert-important alert-warning d-flex'>";
                echo __('Warning : there are multiple encryption keys for this entity. The encryption key of this entity will be used', 'accounts');
                echo "</div>";

                foreach ($hashes as $hashe) {
                    if ($hashe["entities_id"] == $this->getEntityID()) {
                        $hash    = $hashe["hash"];
                        $hash_id = $hashe["id"];
                    }
                }
            } else {
                foreach ($hashes as $hashe) {
                    $hash    = $hashe["hash"];
                    $hash_id = $hashe["id"];
                }
            }

            $alert = '';
            if (empty($hash)) {
                $alert = __('Your encryption key is malformed, please generate the hash', 'accounts');
            }
        } else {
            $alert = __('There is no encryption key for this entity', 'accounts');
        }

        $aeskey = new PluginAccountsAesKey();

        //aeskey non enregistre
        if ($hash) {
            if (!$aeskey->getFromDBByHash($hash_id) || !$aeskey->fields["name"]) {
                echo "<td>" . __('Encryption key', 'accounts') . "</div></td><td>";
                echo Html::input('aeskey', ['id' => 'aeskey', 'type' => 'password', 'size' => 40, 'autocomplete' => 'off']);
            //            echo "<input type='password' class='form-control' autocomplete='off' size='20' name='aeskey' id='aeskey'
            //            style='width: 50%;display: inline;'>";

                echo Html::hidden('encrypted_password', ['value' => $this->fields["encrypted_password"],
                                                         'id'    => 'encrypted_password']);
                echo Html::hidden('good_hash', ['value' => $hash,
                                                'id'    => 'good_hash']);
                echo Html::hidden('wrong_key_locale', ['value' => __('Wrong encryption key', 'accounts'),
                                                       'id'    => 'wrong_key_locale']);
                if (!empty($ID) || $ID > 0) {
                    echo Html::submit("<i class='ti ti-eye'></i>&nbsp;" . __s('Uncrypt & copy', 'accounts'), ['name'  => 'decrypte',
                                                                                                              'id'    => 'decrypte_link',
                                                                                                              'form'  => '',
                                                                                                              'class' => 'btn btn-primary']);

               //               echo "&nbsp;<button type='submit' id='decrypte_link' name='decrypte' value='" . __s('Uncrypt & copy', 'accounts') . "'
               //                        class='btn btn-primary'>";
               //               echo "<i class='ti ti-eye'></i>&nbsp;".__s('Uncrypt & copy', 'accounts');
                }
                echo Html::scriptBlock("$('#aeskey').keypress(function(e) {
                 var root_accounts_doc = '" . PLUGIN_ACCOUNTS_WEBDIR . "';
                 switch(e.keyCode) { 
                     case 13:
                        if (!check_hash()) {
                           var value = document.getElementById('wrong_key_locale').value;
                           document.getElementById('wrong_key_locale_div').innerHTML = value;
                        } else {
                           document.getElementById('wrong_key_locale_div').innerHTML = '';
                           decrypt_password(root_accounts_doc);
                        }
                      return false;
                      break;
                 }
               });");
                echo "</button>";
                echo "<div id='wrong_key_locale_div' style='color:red'></div>";
                echo "</td>";
            } else {
                echo "<td></td><td>";
                echo "</td>";
            }
        } else {
            echo "<td>" . __('Encryption key', 'accounts') . "</td>";
            echo "<td><div class='alert alert-important alert-warning d-flex'>";
            echo $alert;
            echo "</div></td>";
        }
        if (Session::getCurrentInterface() == 'central') {
            echo "<td>" . __('Affected User', 'accounts') . "</td><td>";
            if ($this->canCreate()) {
                User::dropdown(['value'  => $this->fields["users_id"],
                                'entity' => $this->fields["entities_id"],
                                'right'  => 'all']);
            } else {
                echo $dbu->getUserName($this->fields["users_id"]);
            }
            echo "</td>";
        } else {
            echo "<td>" . __('Affected User', 'accounts') . "</td><td>";
            echo $dbu->getUserName($this->fields["users_id"]);
            echo "</td>";
        }

        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        echo "<td>" . __('Password') . "</td>";

        echo "<td>";
        //aeskey enregistre
        if (isset($hash_id)
            && $aeskey->getFromDBByHash($hash_id)
            && $aeskey->fields["name"]) {
            echo Html::hidden('good_hash', ['value' => $hash,
                                            'id'    => 'good_hash']);
            echo Html::hidden('aeskey', ['value'        => $aeskey->fields["name"],
                                         'id'           => 'aeskey',
                                         'autocomplete' => 'off']);
            echo Html::hidden('encrypted_password', ['value' => $this->fields["encrypted_password"],
                                                     'id'    => 'encrypted_password']);
            echo Html::hidden('wrong_key_locale', ['value' => __('Wrong encryption key', 'accounts'),
                                                   'id'    => 'wrong_key_locale']);
         //         echo Html::scriptBlock("");
            echo '<script type="text/javascript">
               $(document).ready(function () {
                  auto_decrypt();
               });</script>';
        }
        if (!empty($ID) || $ID > 0) {
            echo "<span class='account_to_clipboard_wrapper pointer'>";
        }
      //      echo Html::input('hidden_password', ['id' => 'hidden_password', 'type' => 'password', 'size' => 40, 'autocomplete' => 'off']);
        echo "<input type='password' name='hidden_password' id='hidden_password' size='30' >";
        if (!empty($ID) || $ID > 0) {
            echo "</span>";
        }
        echo "<span toggle='#hidden_password' class='fa-fw ti ti-eye field-icon toggle-password pointer'></span>";
        echo "</td>";

        echo Html::scriptBlock('$(".toggle-password").click(function() {
                                 $(".toggle-password").toggleClass("ti-eye ti-eye-off");
                                 var input = $($(this).attr("toggle"));
                                 if (input.attr("type") == "password") {
                                     input.attr("type", "text");
                                 } else {
                                     input.attr("type", "password");
                                 }
                             });');
        if (Session::getCurrentInterface() == 'central') {
            echo "<td>" . __('Affected Group', 'accounts') . "</td><td>";
            if (self::canCreate()) {
                Dropdown::show('Group', ['value'     => $this->fields["groups_id"],
                                         'condition' => ['is_itemgroup' => 1]]);
            } else {
                echo Dropdown::getDropdownName("glpi_groups", $this->fields["groups_id"]);
            }
            echo "</td>";
        } else {
            echo "<td>" . __('Affected Group', 'accounts') . ":	</td><td>";
            echo Dropdown::getDropdownName("glpi_groups", $this->fields["groups_id"]);
            echo "</td>";
        }

        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        echo "<td>" . __('Creation date') . "</td>";
        echo "<td>";
        Html::showDateField("date_creation", ['value' => $this->fields["date_creation"]]);
        echo "</td>";

        echo "<td>" . __('Technician in charge of the hardware') . "</td>";
        echo "<td>";
        User::dropdown(['name'   => "users_id_tech",
                        'value'  => $this->fields["users_id_tech"],
                        'entity' => $this->fields["entities_id"],
                        'right'  => 'interface']);
        echo "</td>";

        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        echo "<td>" . __('Expiration date') . "&nbsp;";
        Html::showToolTip(nl2br(__('Empty for infinite', 'accounts')));
        echo "</td>";
        echo "<td>";
        Html::showDateField("date_expiration", ['value' => $this->fields["date_expiration"]]);
        echo "</td>";

        echo "<td>" . __('Group in charge of the hardware') . "</td><td>";
        Group::dropdown(['name'      => 'groups_id_tech',
                         'value'     => $this->fields['groups_id_tech'],
                         'condition' => ['is_assign' => 1]]);
        echo "</td>";

        echo "<tr class='tab_bg_1'>";

        echo "<td>" . __('Others') . "</td>";
        echo "<td>";
        echo Html::input('others', ['value' => $this->fields['others'], 'size' => 40]);
        echo "</td>";

        echo "<td>" . __('Location') . "</td><td>";
        Location::dropdown(['value'  => $this->fields["locations_id"],
                            'entity' => $this->fields["entities_id"]]);
        echo "</td>";

        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        echo "<td colspan = '4'>";
        echo "<table cellpadding='2' cellspacing='2' border='0'><tr><td>";
        echo __('Comments') . "</td></tr>";
        echo "<tr><td class='center'>";
        Html::textarea(['name'            => 'comment',
                        'value'           => $this->fields["comment"],
                        'cols'            => 125,
                        'rows'            => 3,
                        'enable_richtext' => false]);
        echo "</td></tr></table>";
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Associable to a ticket') . "</td><td>";
        Dropdown::showYesNo('is_helpdesk_visible', $this->fields['is_helpdesk_visible']);
        echo "</td>";
        echo "<td colspan='2'>";
        echo "</td>";

        echo "</tr>";

        Plugin::doHook("post_item_form", ['item' => $this, 'options' => &$params]);

        $options['colspan'] = 2;
        $this->showDates($options);

        if (self::canCreate() || self::canUpdate()) {
            if ((empty($ID) || $ID < 0) && self::canCreate()) {
                echo "<tr class='tab_bg_2'>";
                echo "<td class='center' colspan='4'>";
                echo Html::submit("<i class='fas fa-plus'></i>&nbsp;" . _sx('button', 'Add'), ['name' => 'add', 'id' => 'account_add', 'class' => 'btn btn-primary']);

                echo Html::scriptBlock("$('#account_form').submit(function(event){
               if ($('#hidden_password').val() == '' || $('#aeskey').val() == '') {
                  alert('" . __('You have not filled the password and encryption key', 'accounts') . "');
                  return false;
               };
               if (!check_hash()) {
                  alert('" . __('Wrong encryption key', 'accounts') . "');
                  return false;
               } else {
                  encrypt_password();
               }
            });");
            //            echo "</button>";
                echo "</td>";
                echo "</tr>";
            } elseif ($ID > 0) {
                echo "<tr class='tab_bg_2'>";
                echo "<td class='center' colspan='4'>";
                echo Html::hidden('id', ['value' => $ID]);

                echo Html::submit("<i class='far fa-save'></i>&nbsp;" . _sx('button', 'Save'), ['name' => 'update', 'id' => 'account_update', 'class' => 'btn btn-primary']);
            //
            //            echo "<button type='submit' name='update' id='account_update' value=\"" . _sx('button', 'Save') . "\" class='btn btn-primary' >";
            //            echo "<i class='fas fa-save'></i>&nbsp;"._sx('button', 'Save');

                echo Html::scriptBlock("$('#account_form').submit(function(event){
               if ($('#hidden_password').val() == '' || $('#aeskey').val() == '') {
                  alert('" . __('Password will not be modified', 'accounts') . "');
               } else if (!check_hash()) {
                  alert('" . __('Wrong encryption key', 'accounts') . "');
                  return false;
               } else {
                  encrypt_password();
               }
            });");
            //            echo "</button>";
                echo "</td>";
                echo "</tr>";
                echo "<tr class='tab_bg_2'>";
                echo "<td class='right' colspan='4'>";
                if ($this->fields["is_deleted"] == '0') {
                    echo Html::submit("<i class='ti ti-trash'></i>&nbsp;" . _sx('button', 'Put in trashbin'), ['name' => 'delete', 'class' => 'btn btn-outline-warning me-2']);
               //               echo "<button type='submit' name='delete' value=\"" . _sx('button', 'Put in trashbin') . "\" class='btn btn-primary'>";
               //               echo "<i class='fas fa-trash-alt'></i>&nbsp;"._sx('button', 'Put in trashbin');
               //               echo "</button>";
                } else {
                    echo Html::submit("<i class='ti ti-trash-off'></i>&nbsp;" . _sx('button', 'Restore'), ['name' => 'restore', 'class' => 'btn btn-outline-secondary me-2']);
               //               echo "<button type='submit' name='restore' value=\"" . _sx('button', 'Restore') . "\" class='btn btn-primary'>";
               //               echo "<i class='fas fa-trash-restore'></i>&nbsp;"._sx('button', 'Restore');
               //               echo "</button>";
                    echo Html::submit("<i class='ti ti-trash'></i>&nbsp;" . _sx('button', 'Delete permanently'), ['name' => 'purge', 'class' => 'btn btn-outline-danger me-2']);
               //               echo "&nbsp;&nbsp;<button type='submit' name='purge' value=\"" . _sx('button', 'Delete permanently') . "\" class='btn btn-primary'>";
               //               echo "<i class='fas fa-trash-alt'></i>&nbsp;"._sx('button', 'Delete permanently');
               //               echo "</button>";
                }

                echo "</td>";
                echo "</tr>";
            }
        }

        if (empty($ID)) {
            echo "<br><table class='tab_cadre'>
               <tbody>
               <tr class='tab_bg_1 center'><th colspan ='2'>" . __s('Generate password', 'accounts') . "</th></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-0\" /></td><td><label for=\"char-0\"> " . __("Numbers", "accounts") . " <small>(0123456789)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-1\" /></td><td><label for=\"char-1\"> " . __("Lowercase", "accounts") . " <small>(abcdefghijklmnopqrstuvwxyz)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-2\" /></td><td><label for=\"char-2\"> " . __("Uppercase", "accounts") . " <small>(ABCDEFGHIJKLMNOPQRSTUVWXYZ)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" id=\"char-3\" /></td><td><label for=\"char-3\"> " . __("Special characters", "accounts") . " <small>(!\"#$%&amp;'()*+,-./:;&lt;=&gt;?@[\]^_`{|}~)</small></label></td></tr>
               <tr class='tab_bg_1'>
                        <td><label for='length'>" . __("Length", "accounts") . "</label></td>
                        <td><input type='number' min='1' value='8' step='1' id='length' style='width:4em'  /> " . __(" characters", "accounts") . "</td>
                     </tr>
               <tr id='fakeupdate'></tr>
               <tr class='tab_bg_2 center'><td colspan='2'>&nbsp
               <button type='button' id='generatePass' name='generatePass' class='submit btn btn-primary' value='" . __s('Generate', 'accounts') . "'>" . __s('Generate', 'accounts') . "</button></td></tr>
               </tbody>
               </table>";
            Ajax::updateItemOnEvent("generatePass", "fakeupdate", PLUGIN_ACCOUNTS_WEBDIR . "/ajax/generatepassword.php", ["password" => 1], ["click"]);
        }

        Html::closeForm();

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
        global $DB, $CFG_GLPI;

        $p['name']    = 'plugin_accounts_accounts_id';
        $p['entity']  = '';
        $p['used']    = [];
        $p['display'] = true;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }
        $dbu   = new DbUtils();
        $where = " WHERE `glpi_plugin_accounts_accounts`.`is_deleted` = '0' " .
                 $dbu->getEntitiesRestrictRequest("AND", "glpi_plugin_accounts_accounts", '', $p['entity'], true);

        if (count($p['used'])) {
            $where .= " AND `id` NOT IN (0, " . implode(",", array_filter($p['used'])) . ")";
        }

        $query  = "SELECT *
                FROM `glpi_plugin_accounts_accounttypes`
                WHERE `id` IN (SELECT DISTINCT `plugin_accounts_accounttypes_id`
                               FROM `glpi_plugin_accounts_accounts`
                             $where)
                ORDER BY `name`";
        $result = $DB->query($query);

        $values = [0 => Dropdown::EMPTY_VALUE];

        while ($data = $DB->fetchAssoc($result)) {
            $values[$data['id']] = $data['name'];
        }
        $rand     = mt_rand();
        $out      = Dropdown::showFromArray('_accounttype', $values, ['width'   => '30%',
                                                                      'rand'    => $rand,
                                                                      'display' => false]);
        $field_id = Html::cleanId("dropdown__accounttype$rand");

        $params = ['accounttype' => '__VALUE__',
                   'entity'      => $p['entity'],
                   'rand'        => $rand,
                   'myname'      => $p['name'],
                   'used'        => $p['used']];

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
        $out                   .= Ajax::updateItem(
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
                $actions['PluginAccountsAccount' . MassiveAction::CLASS_ACTION_SEPARATOR . 'install']   = _x('button', 'Associate');
                $actions['PluginAccountsAccount' . MassiveAction::CLASS_ACTION_SEPARATOR . 'uninstall'] = _x('button', 'Dissociate');

                if (Session::haveRight('transfer', READ)
                    && Session::isMultiEntitiesMode()
                ) {
                    $actions['PluginAccountsAccount' . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfer'] = __('Transfer');
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
                Dropdown::showSelectItemFromItemtypes(['items_id_name' => 'item_item',
                                                       'itemtype_name' => 'typeitem',
                                                       'itemtypes'     => self::getTypes(true),
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
     * @param CommonDBTM    $item
     * @param array         $ids
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
        array         $ids
    )
    {
        $account_item = new PluginAccountsAccount_Item();
        $dbu          = new DbUtils();

        switch ($ma->getAction()) {
            case "add_item":
                $input = $ma->getInput();
                foreach ($ma->items as $itemtype => $myitem) {
                    foreach ($myitem as $key => $value) {
                        if (!$dbu->countElementsInTable(
                            'glpi_plugin_accounts_accounts_items',
                            ["itemtype"                    => $itemtype,
                             "items_id"                    => $key,
                             "plugin_accounts_accounts_id" => $input['plugin_accounts_accounts_id']]
                        )) {
                            $myvalue['plugin_accounts_accounts_id'] = $input['plugin_accounts_accounts_id'];
                            $myvalue['itemtype']                    = $itemtype;
                            $myvalue['items_id']                    = $key;
                            if ($account_item->add($myvalue)) {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                break;

            case "transfer":
                $input = $ma->getInput();
                if ($item->getType() == 'PluginAccountsAccount') {
                    foreach ($ids as $key) {
                        $item->getFromDB($key);
                        $type = PluginAccountsAccountType::transfer($item->fields["plugin_accounts_accounttypes_id"], $input['entities_id']);
                        if ($type > 0) {
                            $values["id"]                              = $key;
                            $values["plugin_accounts_accounttypes_id"] = $type;
                            $item->update($values);
                        }

                        unset($values);
                        $values["id"]          = $key;
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
                        $values = ['plugin_accounts_accounts_id' => $key,
                                   'items_id'                    => $input["item_item"],
                                   'itemtype'                    => $input['typeitem']];
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
                   'description' => __('Accounts expired or accounts which expires', 'accounts')]; // Optional
                break;
        }
        return [];
    }

    /**
     * Query used for check expired accounts
     *
     * @return query
     **/
    private static function queryExpiredAccounts()
    {
        $config = new PluginAccountsConfig();
        $notif  = new PluginAccountsNotificationState();

        $config->getFromDB('1');
        $delay = $config->fields["delay_expired"];

        $query = "SELECT *
      FROM `glpi_plugin_accounts_accounts`
      WHERE `date_expiration` IS NOT NULL
      AND `is_deleted` = '0'
      AND DATEDIFF(CURDATE(),`date_expiration`) > $delay
      AND DATEDIFF(CURDATE(),`date_expiration`) > 0 ";
        $query .= "AND `plugin_accounts_accountstates_id` NOT IN (999999";
        $query .= $notif->findStates();
        $query .= ") ";

        return $query;
    }

    /**
     * Query used for check accounts which expire
     *
     * @return query
     **/
    private static function queryAccountsWhichExpire()
    {
        $config = new PluginAccountsConfig();
        $notif  = new PluginAccountsNotificationState();

        $config->getFromDB('1');
        $delay = $config->fields["delay_whichexpire"];

        $query = "SELECT *
      FROM `glpi_plugin_accounts_accounts`
      WHERE `date_expiration` IS NOT NULL
      AND `is_deleted` = '0'
      AND DATEDIFF(CURDATE(),`date_expiration`) > -$delay
      AND DATEDIFF(CURDATE(),`date_expiration`) < 0 ";
        $query .= "AND `plugin_accounts_accountstates_id` NOT IN (999999";
        $query .= $notif->findStates();
        $query .= ") ";

        return $query;
    }

    /**
     * Cron action on accounts : ExpiredAccounts or AccountsWhichExpire
     *
     * @param $task for log, if NULL display
     *
     * @return cron_status
     **/
    public static function cronAccountsAlert($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $cron_status = 0;

        $query_expired     = self::queryExpiredAccounts();
        $query_whichexpire = self::queryAccountsWhichExpire();

        $querys = [Alert::NOTICE => $query_whichexpire, Alert::END => $query_expired];

        $account_infos    = [];
        $account_messages = [];

        foreach ($querys as $type => $query) {
            $account_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                $entity                          = $data['entities_id'];
                $message                         = $data["name"] . ": " .
                                                   Html::convDate($data["date_expiration"]) . "<br>\n";
                $account_infos[$type][$entity][] = $data;

                if (!isset($account_messages[$type][$entity])) {
                    $account_messages[$type][$entity] = __('Accounts expired or accounts which expires', 'accounts') . "<br />";
                }
                $account_messages[$type][$entity] .= $message;
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($account_infos[$type] as $entity => $accounts) {
                Plugin::loadLang('accounts');

                if (NotificationEvent::raiseEvent(
                    ($type == Alert::NOTICE ? "AccountsWhichExpire" : "ExpiredAccounts"),
                    new PluginAccountsAccount(),
                    ['entities_id' => $entity,
                     'accounts'    => $accounts]
                )) {
                    $message     = $account_messages[$type][$entity];
                    $cron_status = 1;
                    if ($task) {
                        $task->log(Dropdown::getDropdownName(
                            "glpi_entities",
                            $entity
                        ) . ":  $message\n");
                        $task->addVolume(1);
                    } else {
                        Session::addMessageAfterRedirect(Dropdown::getDropdownName(
                            "glpi_entities",
                            $entity
                        ) . ":  $message");
                    }
                } else {
                    if ($task) {
                        $task->log(Dropdown::getDropdownName("glpi_entities", $entity) .
                                   ":  Send accounts alert failed\n");
                    } else {
                        Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities", $entity) .
                                                         ":  Send accounts alert failed", false, ERROR);
                    }
                }
            }
        }

        return $cron_status;
    }

    /**
     * Cron task configuration
     *
     * @param $target url
     *
     * @return nothing (display)
     **/
    public static function configCron($target)
    {
        $notif  = new PluginAccountsNotificationState();
        $config = new PluginAccountsConfig();

        $config->showConfigForm($target, 1);
        $notif->showNotificationForm($target);
        $notif->showAddForm($target);
    }

    /**
     * Display types of used accounts
     *
     * @param $target
     */
    public static function showSelector($target)
    {
        global $CFG_GLPI;

        $rand = mt_rand();
        Plugin::loadLang('accounts');
        echo Html::css("/public/lib/base.css");
        echo Html::script("public/lib/base.js");
        echo Html::css(PLUGIN_ACCOUNTS_DIR_NOFULL . "/lib/jstree/themes/default/style.min.css");

        echo "<div class='alert alert-important alert-info d-flex'>" . __('Select the wanted account type', 'accounts') . "</div><br>";
        echo "<a href='" . $target . "?reset=reset' target='_blank' title=\"" .
             __s('Show all') . "\">" . str_replace(" ", "&nbsp;", __('Show all')) . "</a>";
        $root = PLUGIN_ACCOUNTS_WEBDIR;
        $js   = "   $(function() {
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
                    return __('Don\'t expire', 'accounts');
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
        $values = parent::getRights();

        if ($interface == 'helpdesk') {
            unset($values[CREATE], $values[DELETE], $values[PURGE]);
        }
        return $values;
    }

    /**
     * @param array $options Options
     *
     * @return boolean
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
        $date_mod_exists      = ($this->getField('date_mod') != NOT_AVAILABLE);

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
            printf(__('Created on %s'), Html::convDateTime($this->fields["date_creation"]));
            echo "</th>";
        } elseif (!isset($options['withtemplate']) || $options['withtemplate'] == 0 || !$date_creation_exists) {
            echo "<th colspan='$colspan'>";
            echo "</th>";
        }

        if (isset($options['withtemplate']) && $options['withtemplate']) {
            echo "<th colspan='$colspan'>";
            //TRANS: %s is the datetime of insertion
            printf(__('Created on %s'), Html::convDateTime($_SESSION["glpi_currenttime"]));
            echo "</th>";
        }

        if ($date_mod_exists) {
            echo "<th colspan='$colspan'>";
            //TRANS: %s is the datetime of update
            printf(__('Last update on %s'), Html::convDateTime($this->fields["date_mod"]));
            echo "</th>";
        } else {
            echo "<th colspan='$colspan'>";
            echo "</th>";
        }

        if ((!isset($options['withtemplate']) || ($options['withtemplate'] == 0))
            && !empty($this->fields['template_name'])) {
            echo "<th colspan='" . ($colspan * 2) . "'>";
            printf(__('Created from the template %s'), $this->fields['template_name']);
            echo "</th>";
        }

        echo "</tr>";
    }

    /**
     * @return array
     */
    public static function getMenuContent()
    {
        $image = "<i class='ti ti-lock-open' title='" . _n('Encryption key', 'Encryption keys', 2, 'accounts') . "'></i>" . _n('Encryption key', 'Encryption keys', 2, 'accounts');

        $menu                    = [];
        $menu['title']           = self::getMenuName();
        $menu['page']            = self::getSearchURL(false);
        $menu['links']['search'] = self::getSearchURL(false);
        $menu['links']['lists']  = "";
        $menu['links'][$image]   = PluginAccountsHash::getSearchURL(false);
        if (self::canCreate()) {
            $menu['links']['add'] = self::getFormURL(false);
        }

        $menu['options']['account']['title']           = self::getTypeName(2);
        $menu['options']['account']['page']            = self::getSearchURL(false);
        $menu['options']['account']['links']['search'] = PluginAccountsAccount::getSearchURL(false);
        $menu['options']['account']['links'][$image]   = PluginAccountsHash::getSearchURL(false);
        if (PluginAccountsAccount::canCreate()) {
            $menu['options']['account']['links']['add'] = self::getFormURL(false);
        }

        $menu['options']['hash']['title']           = PluginAccountsHash::getTypeName(2);
        $menu['options']['hash']['page']            = PluginAccountsHash::getSearchURL(false);
        $menu['options']['hash']['links']['search'] = PluginAccountsHash::getSearchURL(false);
        $menu['options']['hash']['links'][$image]   = PluginAccountsHash::getSearchURL(false);

        if (PluginAccountsHash::canCreate()) {
            $menu['options']['hash']['links']['add'] = PluginAccountsHash::getFormURL(false);
        }

        $menu['icon'] = self::getIcon();

        return $menu;
    }

    public static function removeRightsFromSession()
    {
        if (isset($_SESSION['glpimenu']['admin']['types']['PluginAccountsAccount'])) {
            unset($_SESSION['glpimenu']['admin']['types']['PluginAccountsAccount']);
        }
        if (isset($_SESSION['glpimenu']['admin']['content']['pluginaccountsaccounts'])) {
            unset($_SESSION['glpimenu']['admin']['content']['pluginaccountsaccounts']);
        }
    }
}
