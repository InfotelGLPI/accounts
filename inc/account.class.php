<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2009-2016 by the accounts Development Team.

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
class PluginAccountsAccount extends CommonDBTM {

   static $rightname = "plugin_accounts";

   static $types = ['Computer', 'Monitor', 'NetworkEquipment', 'Peripheral',
      'Phone', 'Printer', 'Software', 'SoftwareLicense', 'Entity', 'Contract'];

   public $dohistory = true;
   protected $usenotepad = true;

   /**
    * Return the localized name of the current Type
    *
    * @param int $nb
    *
    * @return string
    */
   public static function getTypeName($nb = 0) {
      return _n('Account', 'Accounts', $nb, 'accounts');
   }

   /**
    * Actions done when item is deleted from the database
    */
   public function cleanDBonPurge() {
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
   public function rawSearchOptions() {

      $tab[] = [
         'id' => 'common',
         'name' => self::getTypeName(2)
      ];

      $tab[] = [
         'id' => '1',
         'table' => $this->getTable(),
         'field' => 'name',
         'name' => __('Name'),
         'datatype' => 'itemlink',
         'itemlink_type' => 'PluginAccountsAccount',
         'massiveaction' => false
      ];
      if (Session::getCurrentInterface() != 'central') {
         $tab[1]['searchtype'] = 'contains';
      }

      $tab[] = [
         'id' => '2',
         'table' => 'glpi_plugin_accounts_accounttypes',
         'field' => 'name',
         'name' => __('Type'),
         'datatype' => 'dropdown'
      ];
      if (Session::getCurrentInterface() != 'central') {
         $tab[2]['searchtype'] = 'contains';
      }

      $tab[] = [
         'id' => '16',
         'table' => 'glpi_users',
         'field' => 'name',
         'name' => __('Affected User', 'accounts'),
      ];
      if (Session::getCurrentInterface() != 'central') {
         $tab[16]['searchtype'] = 'contains';
      }

      $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

      $tab[] = [
         'id' => '4',
         'table' => $this->getTable(),
         'field' => 'login',
         'name' => __('Login')
      ];

      $tab[] = [
         'id' => '5',
         'table' => $this->getTable(),
         'field' => 'date_creation',
         'name' => __('Creation date'),
         'datatype' => 'date'
      ];

      $tab[] = [
         'id' => '6',
         'table' => $this->getTable(),
         'field' => 'date_expiration',
         'name' => __('Expiration date')
      ];

      $tab[] = [
         'id' => '7',
         'table' => $this->getTable(),
         'field' => 'comment',
         'name' => __('Comments'),
         'datatype' => 'text'
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
            'joinparams' => ['jointype' => 'child']
         ];
      }

      $tab[] = [
         'id' => '9',
         'table' => $this->getTable(),
         'field' => 'others',
         'name' => __('Others')
      ];

      $tab[] = [
         'id' => '10',
         'table' => 'glpi_plugin_accounts_accountstates',
         'field' => 'name',
         'name' => __('Status'),
      ];
      if (Session::getCurrentInterface() != 'central') {
         $tab[10]['searchtype'] = 'contains';
      }

      if (Session::getCurrentInterface() == 'central') {
         $tab[] = [
            'id' => 11,
            'table' => $this->getTable(),
            'field' => 'is_recursive',
            'name' => __('Child entities'),
            'datatype' => 'bool'
         ];
      }

      $tab[] = [
         'id' => '12',
         'table' => 'glpi_groups',
         'field' => 'completename',
         'name' => __('Group'),
         'datatype' => 'dropdown',
         'condition' => ['`is_itemgroup`' => 1],
      ];
      if (Session::getCurrentInterface() != 'central') {
         $tab[12]['searchtype'] = 'contains';
      }

      if (Session::getCurrentInterface() == 'central') {
         $tab[] = [
            'id' => 13,
            'table' => $this->getTable(),
            'field' => 'is_helpdesk_visible',
            'name' => __('Associable to a ticket'),
            'datatype' => 'bool',
         ];
      }

      $tab[] = [
         'id' => '14',
         'table' => $this->getTable(),
         'field' => 'date_mod',
         'name' => __('Last update'),
         'massiveaction' => false,
         'datatype' => 'datetime'
      ];

      $tab[] = [
         'id' => '17',
         'table' => 'glpi_users',
         'field' => 'name',
         'linkfield' => 'users_id_tech',
         'name' => __('Technician in charge of the hardware'),
         'datatype' => 'dropdown',
         'right' => 'interface'
      ];

      $tab[] = [
         'id' => '18',
         'table' => 'glpi_groups',
         'field' => 'completename',
         'linkfield' => 'groups_id_tech',
         'name' => __('Group in charge of the hardware'),
         'condition' => ['`is_assign`' => 1],
         'datatype' => 'dropdown'
      ];

      $tab[] = [
         'id' => '30',
         'table' => $this->getTable(),
         'field' => 'id',
         'name' => __('ID'),
         'datatype' => 'number'
      ];

      $tab[] = [
         'id' => '81',
         'table' => 'glpi_entities',
         'field' => 'entities_id',
         'name' => __('Entity-ID')
      ];

      $tab[] = [
         'id' => '80',
         'table' => 'glpi_entities',
         'field' => 'completename',
         'name' => __('Entity'),
         'datatype' => 'dropdown'
      ];

      $tab[] = [
         'id' => '86',
         'table' => $this->getTable(),
         'field' => 'is_recursive',
         'name' => __('Child entities'),
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
   public function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
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
   public function prepareInputForAdd($input) {

      if (isset($input['date_creation']) && empty($input['date_creation'])) {
         $input['date_creation'] = 'NULL';
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
   public function post_addItem() {
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
   public function prepareInputForUpdate($input) {

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
   public function getSelectLinkedItem() {
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
   public function showForm($ID, $options = []) {
      global $CFG_GLPI;
      if (!$this->canView()) {
         return false;
      }

      $hashclass = new PluginAccountsHash();
      $dbu = new DbUtils();

      $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_accounts_hashes", '', '', $hashclass->maybeRecursive());
      if ($ID < 1 && $dbu->countElementsInTable("glpi_plugin_accounts_hashes", $restrict) == 0) {
         echo "<div class='center'>" . __('There is no encryption key for this entity', 'accounts') . "<br><br>";
         echo "<a href='" . Toolbox::getItemTypeSearchURL('PluginAccountsAccount') . "'>";
         echo __('Back');
         echo "</a></div>";
         return false;
      }
      if ($ID != 0) {
         // Create item
         $this->check(-1, UPDATE);
         $this->getEmpty();
      }
      $options["formoptions"] = "id = 'account_form'";
      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Name') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "name");
      echo "</td>";

      echo "<td>" . __('Status') . "</td><td>";
      Dropdown::show('PluginAccountsAccountState',
         ['value' => $this->fields["plugin_accounts_accountstates_id"]]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Login') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "login");
      echo "</td>";

      echo "<td>" . __('Type') . "</td><td>";
      Dropdown::show('PluginAccountsAccountType',
         ['value' => $this->fields["plugin_accounts_accounttypes_id"]]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      //hash
      $hash = 0;
      $hash_id = 0;
      $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_accounts_hashes", '',
         $this->getEntityID(), $hashclass->maybeRecursive());
      $hashes = $dbu->getAllDataFromTable("glpi_plugin_accounts_hashes", $restrict);
      if (!empty($hashes)) {
         foreach ($hashes as $hashe) {
            $hash = $hashe["hash"];
            $hash_id = $hashe["id"];
         }
         $alert = '';
      } else {
         $alert = __('There is no encryption key for this entity', 'accounts');
      }

      $aeskey = new PluginAccountsAesKey();

      //aeskey non enregistre
      if ($hash) {
         if (!$aeskey->getFromDBByHash($hash_id) || !$aeskey->fields["name"]) {
            echo "<td>" . __('Encryption key', 'accounts') . "</div></td><td>";
            echo "<input type='password' autocomplete='off' name='aeskey' id='aeskey'>";

            echo Html::hidden('encrypted_password', ['value' => $this->fields["encrypted_password"],
               'id' => 'encrypted_password']);
            echo Html::hidden('good_hash', ['value' => $hash,
               'id' => 'good_hash']);
            echo Html::hidden('wrong_key_locale', ['value' => __('Wrong encryption key', 'accounts'),
               'id' => 'wrong_key_locale']);
            if (!empty($ID) || $ID > 0) {
               echo "&nbsp;<input type='button' id='decrypte_link' name='decrypte' value='" . __s('Uncrypt', 'accounts') . "'
                        class='submit'>";
            }
            echo Html::scriptBlock("$('#aeskey').keypress(function(e) {
                 switch(e.keyCode) { 
                     case 13:
                        if (!check_hash()) {
                           var value = document.getElementById('wrong_key_locale').value;
                           document.getElementById('wrong_key_locale_div').innerHTML = value;
                        } else {
                           document.getElementById('wrong_key_locale_div').innerHTML = '';
                           decrypt_password();
                        }
                      return false;
                      break;
                 }
               });");
            echo "<div id='wrong_key_locale_div' style='color:red'></div>";
            echo "</td>";
         } else {
            echo "<td></td><td>";
            echo "</td>";
         }
      } else {
         echo "<td>" . __('Encryption key', 'accounts') . "</div></td><td><div class='red'>";
         echo $alert;
         echo "</div></td>";
      }
      if (Session::getCurrentInterface() == 'central') {
         echo "<td>" . __('Affected User', 'accounts') . "</td><td>";
         if ($this->canCreate()) {
            User::dropdown(['value' => $this->fields["users_id"],
               'entity' => $this->fields["entities_id"],
               'right' => 'all']);
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
      if (isset($hash_id) && $aeskey->getFromDBByHash($hash_id) && $aeskey->fields["name"]) {
         echo Html::hidden('good_hash', ['value' => $hash,
            'id' => 'good_hash']);
         echo Html::hidden('aeskey', ['value' => $aeskey->fields["name"],
            'id' => 'aeskey',
            'autocomplete' => 'off']);
         echo Html::hidden('encrypted_password', ['value' => $this->fields["encrypted_password"],
            'id' => 'encrypted_password']);
         echo Html::hidden('wrong_key_locale', ['value' => __('Wrong encryption key', 'accounts'),
            'id' => 'wrong_key_locale']);
         echo Html::scriptBlock("auto_decrypt();");
      }
      echo "<input type='text' name='hidden_password' id='hidden_password' size='30' >";

      echo "</td>";

      if (Session::getCurrentInterface() == 'central') {
         echo "<td>" . __('Affected Group', 'accounts') . "</td><td>";
         if (self::canCreate()) {
            Dropdown::show('Group', ['value' => $this->fields["groups_id"],
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
      User::dropdown(['name' => "users_id_tech",
         'value' => $this->fields["users_id_tech"],
         'entity' => $this->fields["entities_id"],
         'right' => 'interface']);
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
      Group::dropdown(['name' => 'groups_id_tech',
         'value' => $this->fields['groups_id_tech'],
         'condition' => ['is_assign' => 1]]);
      echo "</td>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Others') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "others");
      echo "</td>";

      echo "<td>" . __('Location') . "</td><td>";
      Location::dropdown(['value' => $this->fields["locations_id"],
         'entity' => $this->fields["entities_id"]]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td colspan = '4'>";
      echo "<table cellpadding='2' cellspacing='2' border='0'><tr><td>";
      echo __('Comments') . "</td></tr>";
      echo "<tr><td class='center'>";
      echo "<textarea cols='125' rows='3' name='comment'>" . $this->fields["comment"] . "</textarea>";
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

      if (self::canCreate()) {
         if (empty($ID) || $ID < 0) {

            echo "<tr>";
            echo "<td class='tab_bg_2 top' colspan='4'>";
            echo "<div align='center'>";
            echo "<input type='submit' name='add' id='account_add' value='" . _sx('button', 'Add') . "' class='submit'>";
            echo "</div>";
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
            echo "</td>";
            echo "</tr>";

         } else {

            echo "<tr>";
            echo "<td class='tab_bg_2'  colspan='4 top'><div align='center'>";
            echo Html::hidden('id', ['value' => $ID]);
            echo "<input type='submit' name='update' id='account_update' value=\"" . _sx('button', 'Save') . "\" class='submit' >";
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

            if ($this->fields["is_deleted"] == '0') {
               echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='delete' value=\"" . _sx('button', 'Put in trashbin') . "\" class='submit'></div>";
            } else {
               echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='restore' value=\"" . _sx('button', 'Restore') . "\" class='submit'>";
               echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='purge' value=\"" . _sx('button', 'Delete permanently') . "\" class='submit'></div>";
            }

            echo "</td>";
            echo "</tr>";

         }
      }
      $options['canedit'] = false;

      $options['candel']  = false;

      if (empty($ID)) {
         echo "<table class='tab_cadre'>
               <tbody>
               <tr class='tab_bg_1 center'><th colspan ='2'>" . __s('Generate password', 'accounts') . "</th></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-0\" /></td><td><label for=\"char-0\"> ".__("Numbers","accounts")." <small>(0123456789)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-1\" /></td><td><label for=\"char-1\"> ".__("Lowercase","accounts")." <small>(abcdefghijklmnopqrstuvwxyz)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" checked id=\"char-2\" /></td><td><label for=\"char-2\"> ".__("Uppercase","accounts")." <small>(ABCDEFGHIJKLMNOPQRSTUVWXYZ)</small></label></td></tr>
               <tr class='tab_bg_1'><td><input type=\"checkbox\" id=\"char-3\" /></td><td><label for=\"char-3\"> ".__("Special characters","accounts")." <small>(!\"#$%&amp;'()*+,-./:;&lt;=&gt;?@[\]^_`{|}~)</small></label></td></tr>
               <tr class='tab_bg_1'>
                        <td><label for='length'>".__("Length","accounts")."</label></td>
                        <td><input type='number' min='1' value='8' step='1' id='length' style='width:4em'  /> ".__(" characters","accounts")."</td>
                     </tr>
               <tr id='fakeupdate'></tr>
               <tr class='tab_bg_2 center'><td colspan='2'>&nbsp;<input type='button' id='generatePass' name='generatePass' class='submit' style='background-color: #fec95c;color: #4b2f03;border: 2px solid #4b2f03;padding: 5px;' value='" . __s('Generate', 'accounts') . "'
                     class='submit'></td></tr>
               </tbody>
               </table>";
         Ajax::updateItemOnEvent("generatePass","fakeupdate",$CFG_GLPI["root_doc"]."/plugins/accounts/ajax/generatepassword.php",["password"=>1],["click"]);
      }


      $this->showFormButtons($options);
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
   static function dropdownAccount($options = []) {
      global $DB, $CFG_GLPI;

      $p['name'] = 'plugin_accounts_accounts_id';
      $p['entity'] = '';
      $p['used'] = [];
      $p['display'] = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }
      $dbu = new DbUtils();
      $where = " WHERE `glpi_plugin_accounts_accounts`.`is_deleted` = '0' " .
         $dbu->getEntitiesRestrictRequest("AND", "glpi_plugin_accounts_accounts", '', $p['entity'], true);

      if (count($p['used'])) {
         $where .= " AND `id` NOT IN (0, " . implode(",", array_filter($p['used'])) . ")";
      }

      $query = "SELECT *
                FROM `glpi_plugin_accounts_accounttypes`
                WHERE `id` IN (SELECT DISTINCT `plugin_accounts_accounttypes_id`
                               FROM `glpi_plugin_accounts_accounts`
                             $where)
                ORDER BY `name`";
      $result = $DB->query($query);

      $values = [0 => Dropdown::EMPTY_VALUE];

      while ($data = $DB->fetch_assoc($result)) {
         $values[$data['id']] = $data['name'];
      }
      $rand = mt_rand();
      $out = Dropdown::showFromArray('_accounttype', $values, ['width' => '30%',
         'rand' => $rand,
         'display' => false]);
      $field_id = Html::cleanId("dropdown__accounttype$rand");

      $params = ['accounttype' => '__VALUE__',
         'entity' => $p['entity'],
         'rand' => $rand,
         'myname' => $p['name'],
         'used' => $p['used']];

      $out .= Ajax::updateItemOnSelectEvent($field_id, "show_" . $p['name'] . $rand,
         $CFG_GLPI["root_doc"] . "/plugins/accounts/ajax/dropdownTypeAccounts.php",
         $params, false);
      $out .= "<span id='show_" . $p['name'] . "$rand'>";
      $out .= "</span>\n";

      $params['accounttype'] = 0;
      $out .= Ajax::updateItem("show_" . $p['name'] . $rand,
         $CFG_GLPI["root_doc"] . "/plugins/accounts/ajax/dropdownTypeAccounts.php",
         $params, false);
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
   public function getSpecificMassiveActions($checkitem = null) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if (Session::getCurrentInterface() == 'central') {
         if ($isadmin) {
            $actions['PluginAccountsAccount' . MassiveAction::CLASS_ACTION_SEPARATOR . 'install'] = _x('button', 'Associate');
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
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case 'add_item':
            self::dropdownAccount([]);
            echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
            return true;
         case "install" :
            Dropdown::showSelectItemFromItemtypes(['items_id_name' => 'item_item',
               'itemtype_name' => 'typeitem',
               'itemtypes' => self::getTypes(true),
               'checkright'
               => true,
            ]);
            echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
            return true;
            break;
         case "uninstall" :
            Dropdown::showSelectItemFromItemtypes(['items_id_name' => 'item_item',
               'itemtype_name' => 'typeitem',
               'itemtypes' => self::getTypes(true),
               'checkright'
               => true,
            ]);
            echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
            return true;
            break;
         case "transfer" :
            Dropdown::show('Entity');
            echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
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
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {

      $account_item = new PluginAccountsAccount_Item();
      $dbu = new DbUtils();

      switch ($ma->getAction()) {
         case "add_item":
            $input = $ma->getInput();
            foreach ($ma->items as $itemtype => $myitem) {
               foreach ($myitem as $key => $value) {
                  if (!$dbu->countElementsInTable('glpi_plugin_accounts_accounts_items',
                     ["itemtype" => $itemtype,
                        "items_id" => $key,
                        "plugin_accounts_accounts_id" => $input['plugin_accounts_accounts_id']])) {
                     $myvalue['plugin_accounts_accounts_id'] = $input['plugin_accounts_accounts_id'];
                     $myvalue['itemtype'] = $itemtype;
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
            }
            break;

         case "transfer" :
            $input = $ma->getInput();
            if ($item->getType() == 'PluginAccountsAccount') {
               foreach ($ids as $key) {
                  $item->getFromDB($key);
                  $type = PluginAccountsAccountType::transfer($item->fields["plugin_accounts_accounttypes_id"], $input['entities_id']);
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

         case 'install' :
            $input = $ma->getInput();

            foreach ($ids as $key) {
               if ($item->can($key, UPDATE)) {
                  $values = ['plugin_accounts_accounts_id' => $key,
                     'items_id' => $input["item_item"],
                     'itemtype' => $input['typeitem']];
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
   public function getForbiddenStandardMassiveAction() {
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
   public static function cronInfo($name) {

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
   private static function queryExpiredAccounts() {

      $config = new PluginAccountsConfig();
      $notif = new PluginAccountsNotificationState();

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
   private static function queryAccountsWhichExpire() {

      $config = new PluginAccountsConfig();
      $notif = new PluginAccountsNotificationState();

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
   public static function cronAccountsAlert($task = null) {
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
         foreach ($DB->request($query) as $data) {
            $entity = $data['entities_id'];
            $message = $data["name"] . ": " .
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

            if (NotificationEvent::raiseEvent(($type == Alert::NOTICE ? "AccountsWhichExpire" : "ExpiredAccounts"),
               new PluginAccountsAccount(),
               ['entities_id' => $entity,
                  'accounts' => $accounts])) {
               $message = $account_messages[$type][$entity];
               $cron_status = 1;
               if ($task) {
                  $task->log(Dropdown::getDropdownName("glpi_entities",
                        $entity) . ":  $message\n");
                  $task->addVolume(1);
               } else {
                  Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",
                        $entity) . ":  $message");
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
   public static function configCron($target) {

      $notif = new PluginAccountsNotificationState();
      $config = new PluginAccountsConfig();

      $config->showForm($target, 1);
      $notif->showForm($target);
      $notif->showAddForm($target);

   }

   /**
    * Display types of used accounts
    *
    * @param $target
    */
   public static function showSelector($target) {
      global $CFG_GLPI;

      $rand = mt_rand();
      Plugin::loadLang('accounts');
      echo "<div class='center' ><span class='b'>" . __('Select the wanted account type', 'accounts') . "</span><br>";
      echo "<a style='font-size:14px;' href='" . $target . "?reset=reset' title=\"" .
         __s('Show all') . "\">" . str_replace(" ", "&nbsp;", __('Show all')) . "</a></div>";

      $js = "   $(function() {
                  $.getScript('{$CFG_GLPI["root_doc"]}/lib/jqueryplugins/jstree/jstree.min.js', function(data, textStatus, jqxhr) {
                     $('#tree_projectcategory$rand').jstree({
                        // the `plugins` array allows you to configure the active plugins on this instance
                        'plugins' : ['search', 'qload'],
                        'search': {
                           'case_insensitive': true,
                           'show_only_matches': true,
                           'ajax': {
                              'type': 'POST',
                              'url': '" . $CFG_GLPI["root_doc"] . "/plugins/accounts/ajax/accounttreetypes.php'
                           }
                        },
                        'qload': {
                           'prevLimit': 50,
                           'nextLimit': 30,
                           'moreText': '" . __s('Load more...') . "'
                        },
                        'core': {
                           'themes': {
                              'name': 'glpi'
                           },
                           'animation': 0,
                           'data': {
                              'url': function(node) {
                                 return node.id === '#' ?
                                    '" . $CFG_GLPI["root_doc"] . "/plugins/accounts/ajax/accounttreetypes.php?node=-1' :
                                    '" . $CFG_GLPI["root_doc"] . "/plugins/accounts/ajax/accounttreetypes.php?node='+node.id;
                              }
                           }
                        }
                     });
                  });
               });";

      echo Html::scriptBlock($js);
      echo "<div class='left' style='width:100%'>";
      echo "<div id='tree_projectcategory$rand'></div>";
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
   public static function registerType($type) {
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
   public static function getTypes($all = false) {

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
   public static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'date_expiration' :
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
   function getRights($interface = 'central') {

      $values = parent::getRights();

      if ($interface == 'helpdesk') {
         unset($values[CREATE], $values[DELETE], $values[PURGE]);
      }
      return $values;
   }
}
