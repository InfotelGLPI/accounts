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

class PluginAccountsAccount extends CommonDBTM {

   static $rightname = "plugin_accounts";
   
   static $types = array('Computer', 'Monitor', 'NetworkEquipment', 'Peripheral',
            'Phone', 'Printer', 'Software', 'SoftwareLicense', 'Entity', 'Contract');

   public $dohistory = true;
   protected $usenotepad = true;
   
   /**
    * Return the localized name of the current Type
    *
    * @return string
    **/
   public static function getTypeName($nb = 0) {
      return _n('Account', 'Accounts', $nb, 'accounts');
   }
   
   /**
    * Actions done when item is deleted from the database
    *
    * @return nothing
    **/
   public function cleanDBonPurge() {
      $temp = new PluginAccountsAccount_Item();
      $temp->deleteByCriteria(array('plugin_accounts_accounts_id' => $this->fields['id']));
      
      $ip = new Item_Problem();
      $ip->cleanDBonItemDelete(__CLASS__, $this->fields['id']);

      $ci = new Change_Item();
      $ci->cleanDBonItemDelete(__CLASS__, $this->fields['id']);

      $ip = new Item_Project();
      $ip->cleanDBonItemDelete(__CLASS__, $this->fields['id']);
      
   }
   
   /**
    * Get the Search options for the given Type
    *
    * @return an array of search options
    * More information on https://forge.indepnet.net/wiki/glpi/SearchEngine
    **/
   public function getSearchOptions() {

      $tab                       = array();

      $tab['common']             = self::getTypeName(2);

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[1]['searchtype']   = 'contains';
      }

      $tab[2]['table']           = 'glpi_plugin_accounts_accounttypes';
      $tab[2]['field']           = 'name';
      $tab[2]['name']            = __('Type');
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
         $tab[2]['searchtype']   = 'contains';
      $tab[2]['datatype']        = 'dropdown';

      $tab[3]['table']           = 'glpi_users';
      $tab[3]['field']           = 'name';
      $tab[3]['name']            = __('Affected User', 'accounts');
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[3]['searchtype']   = 'contains';
      }
      
      $tab[4]['table']           = $this->getTable();
      $tab[4]['field']           = 'login';
      $tab[4]['name']            = __('Login');

      $tab[5]['table']           = $this->getTable();
      $tab[5]['field']           = 'date_creation';
      $tab[5]['name']            = __('Creation date');
      $tab[5]['datatype']        = 'date';

      $tab[6]['table']           = $this->getTable();
      $tab[6]['field']           = 'date_expiration';
      $tab[6]['name']            = __('Expiration date');

      $tab[7]['table']           = $this->getTable();
      $tab[7]['field']           = 'comment';
      $tab[7]['name']            = __('Comments');
      $tab[7]['datatype']        = 'text';

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $tab[8]['table']        = 'glpi_plugin_accounts_accounts_items';
         $tab[8]['field']        = 'items_id';
         $tab[8]['nosearch']     = true;
         $tab[8]['name']         = _n('Associated item' , 'Associated items', 2);
         $tab[8]['forcegroupby'] = true;
         $tab[8]['massiveaction']= false;
         $tab[8]['joinparams']   = array('jointype' => 'child');
      }

      $tab[9]['table']           = $this->getTable();
      $tab[9]['field']           = 'others';
      $tab[9]['name']            = __('Others');

      $tab[10]['table']          = 'glpi_plugin_accounts_accountstates';
      $tab[10]['field']          = 'name';
      $tab[10]['name']           = __('Status');
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[10]['searchtype']  = 'contains';
      }

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $tab[11]['table']       = $this->getTable();
         $tab[11]['field']       = 'is_recursive';
         $tab[11]['name']        = __('Child entities');
         $tab[11]['datatype']    = 'bool';
      }

      $tab[12]['table']          = 'glpi_groups';
      $tab[12]['field']          = 'completename';
      $tab[12]['name']           = __('Group');
      $tab[12]['datatype']       = 'dropdown';
      $tab[12]['condition']      = '`is_itemgroup`';
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[12]['searchtype']  = 'contains';
      }

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $tab[13]['table']       = $this->getTable();
         $tab[13]['field']       = 'is_helpdesk_visible';
         $tab[13]['name']        = __('Associable to a ticket');
         $tab[13]['datatype']    = 'bool';
      }

      $tab[14]['table']          = $this->getTable();
      $tab[14]['field']          = 'date_mod';
      $tab[14]['name']           = __('Last update');
      $tab[14]['massiveaction']  = false;
      $tab[14]['datatype']       = 'datetime';

      /*$tab[15]['table']= $this->getTable();
       $tab[15]['field']='encrypted_password';
      $tab[15]['name']=__('Password');
      $tab[15]['datatype']='password';
      $tab[15]['nosearch']=true;
      $tab[15]['massiveaction'] = false;
      */

      $tab[16]['table']          = 'glpi_locations';
      $tab[16]['field']          = 'completename';
      $tab[16]['name']           = __('Location');
      $tab[16]['datatype']       = 'dropdown';
      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $tab[16]['searchtype']  = 'contains';
      }
         
      $tab[17]['table']          = 'glpi_users';
      $tab[17]['field']          = 'name';
      $tab[17]['linkfield']      = 'users_id_tech';
      $tab[17]['name']           = __('Technician in charge of the hardware');
      $tab[17]['datatype']       = 'dropdown';
      $tab[17]['right']          = 'interface';

      $tab[18]['table']          = 'glpi_groups';
      $tab[18]['field']          = 'completename';
      $tab[18]['linkfield']      = 'groups_id_tech';
      $tab[18]['name']           = __('Group in charge of the hardware');
      $tab[18]['condition']      = '`is_assign`';
      $tab[18]['datatype']       = 'dropdown';

      $tab[30]['table']          = $this->getTable();
      $tab[30]['field']          = 'id';
      $tab[30]['name']           = __('ID');
      $tab[30]['datatype']       = 'number';

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         $tab[80]['table']       = 'glpi_entities';
         $tab[80]['field']       = 'completename';
         $tab[80]['name']        = __('Entity');
         $tab[80]['datatype']    = 'dropdown';

      }
      
      $tab[81]['table']       = 'glpi_entities';
      $tab[81]['field']       = 'entities_id';
      $tab[81]['name']        = __('Entity')."-".__('ID');

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
   public function defineTabs($options = array()) {

      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('PluginAccountsAccount_Item', $ong, $options);
      $this->addStandardTab('Ticket', $ong, $options);
      $this->addStandardTab('Item_Problem', $ong, $options);
      //$this->addStandardTab('Change_Item', $ong, $options);
      $this->addStandardTab('Item_Project', $ong, $options);
      $this->addStandardTab('Document_Item', $ong, $options);
      $this->addStandardTab('Notepad', $ong, $options);
      if ($_SESSION['glpiactiveprofile']['interface'] == 'central')
         $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   /**
    * Prepare input datas for adding the item
    *
    * @param $input datas used to add the item
    *
    * @return the modified $input array
    **/
   public function prepareInputForAdd($input) {

      if (isset($input['date_creation']) && empty($input['date_creation']))
         $input['date_creation'] = 'NULL';
      if (isset($input['date_expiration']) && empty($input['date_expiration']))
         $input['date_expiration'] = 'NULL';

      return $input;
   }

   /**
    * Actions done after the ADD of the item in the database
    *
    * @return nothing
    **/
   public function post_addItem() {
      global $CFG_GLPI;

      if ($CFG_GLPI["use_mailing"]) {
         NotificationEvent::raiseEvent("new", $this);
      }
   }

   /**
    * Prepare input datas for updating the item
    *
    * @param $input datas used to update the item
    *
    * @return the modified $input array
    **/
   public function prepareInputForUpdate($input) {

      if (isset($input['date_creation']) && empty($input['date_creation']))
         $input['date_creation'] = 'NULL';
      if (isset($input['date_expiration']) && empty($input['date_expiration']))
         $input['date_expiration'] = 'NULL';

      return $input;
   }

   /**
    * Return the SQL command to retrieve linked object
   *
   * @return a SQL command which return a set of (itemtype, items_id)
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
    * @return Nothing (display)
    **/
   public function showForm($ID, $options = array()) {

      if (!$this->canView()) return false;

      $hashclass = new PluginAccountsHash();

      $restrict = getEntitiesRestrictRequest(" ", "glpi_plugin_accounts_hashes", '', '', $hashclass->maybeRecursive());
      if ($ID < 1 && countElementsInTable("glpi_plugin_accounts_hashes", $restrict) == 0) {
         echo "<div class='center'>" . __('There is no encryption key for this entity', 'accounts') . "<br><br>";
         echo "<a href='" . Toolbox::getItemTypeSearchURL('PluginAccountsAccount') . "'>";
         _e('Back');
         echo "</a></div>";
         return false;
      }
/*
      if ($ID > 0) {
         $this->check($ID, READ);
         if (!Session::haveRight("plugin_accounts_see_all_users", 1)) {
            $access = 0;
            if (Session::haveRight("plugin_accounts_my_groups", 1)) {
               if ($this->fields["groups_id"]) {
                  if (count($_SESSION['glpigroups'])
                           && in_array($this->fields["groups_id"], $_SESSION['glpigroups'])
                  ) {
                     $access = 1;
                  }
               }
               if ($this->fields["users_id"]) {
                  if ($this->fields["users_id"] == Session::getLoginUserID())
                     $access = 1;
               }
            }
            if (!Session::haveRight("plugin_accounts_my_groups", 1)
                     && $this->fields["users_id"] == Session::getLoginUserID()
            )
               $access = 1;

            if ($access != 1)
               return false;
         }
      } else {
         // Create item
         $this->check(-1, UPDATE);
         $this->getEmpty();
      }
*/
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
      array('value' => $this->fields["plugin_accounts_accountstates_id"]));
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Login') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "login");
      echo "</td>";

      echo "<td>" . __('Type') . "</td><td>";
      Dropdown::show('PluginAccountsAccountType',
      array('value' => $this->fields["plugin_accounts_accounttypes_id"]));
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      //hash
      $hash = 0;

      $restrict = getEntitiesRestrictRequest(" ", "glpi_plugin_accounts_hashes", '', $this->getEntityID(), $hashclass->maybeRecursive());
      $hashes = getAllDatasFromTable("glpi_plugin_accounts_hashes", $restrict);
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

            echo Html::hidden('encrypted_password', array('value' => $this->fields["encrypted_password"],
                                                          'id'    => 'encrypted_password'));
            echo Html::hidden('good_hash', array('value' => $hash,
                                                 'id'    => 'good_hash'));
            echo Html::hidden('wrong_key_locale', array('value' => __('Wrong encryption key', 'accounts'),
                                                        'id'    => 'wrong_key_locale'));
            if (!empty($ID) || $ID > 0) {
               $url = $this->getFormURL();
               echo "&nbsp;<input type='button' id='decrypte_link' name='decrypte' value='" . __s('Uncrypt', 'accounts') . "'
                        class='submit'>";
            }

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
      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         echo "<td>" . __('Affected User', 'accounts') . "</td><td>";
         if ($this->canCreate()) {
            User::dropdown(array('value' => $this->fields["users_id"],
            'entity' => $this->fields["entities_id"],
            'right' => 'all'));
         } else {
            echo getUserName($this->fields["users_id"]);
         }
         echo "</td>";
      } else {
         echo "<td>" . __('Affected User', 'accounts') . "</td><td>";
         echo getUserName($this->fields["users_id"]);
         echo "</td>";
      }

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Password') . "</td>";

      echo "<td>";
      //aeskey enregistre
      if (isset($hash_id) && $aeskey->getFromDBByHash($hash_id) && $aeskey->fields["name"]) {
         echo Html::hidden('good_hash',          array('value'        => $hash, 
                                                       'id'           => 'good_hash'));
         echo Html::hidden('aeskey',             array('value'        => $aeskey->fields["name"], 
                                                       'id'           => 'aeskey', 
                                                       'autocomplete' => 'off'));
         echo Html::hidden('encrypted_password', array('value'        => $this->fields["encrypted_password"], 
                                                       'id'           => 'encrypted_password'));
         echo Html::hidden('wrong_key_locale',   array('value'        => __('Wrong encryption key', 'accounts'),
                                                       'id'           => 'wrong_key_locale'));
         echo Html::scriptBlock("auto_decrypt();");
      } 
      echo "<input type='text' name='hidden_password' id='hidden_password' size='30' >";

      echo "</td>";

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         echo "<td>" . __('Affected Group', 'accounts') . "</td><td>";
         if (self::canCreate()) {
            Dropdown::show('Group', array('value' => $this->fields["groups_id"],
            'condition' => '`is_itemgroup`'));
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
      Html::showDateFormItem("date_creation", $this->fields["date_creation"], true, true);
      echo "</td>";

      echo "<td>" . __('Technician in charge of the hardware') . "</td>";
      echo "<td>";
      User::dropdown(array('name' => "users_id_tech",
      'value' => $this->fields["users_id_tech"],
      'entity' => $this->fields["entities_id"],
      'right' => 'interface'));
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Expiration date') . "&nbsp;";
      Html::showToolTip(nl2br(__('Empty for infinite', 'accounts')));
      echo "</td>";
      echo "<td>";
      Html::showDateFormItem("date_expiration", $this->fields["date_expiration"], true, true);
      echo "</td>";

      echo "<td>" . __('Group in charge of the hardware') . "</td><td>";
      Group::dropdown(array('name' => 'groups_id_tech',
      'value' => $this->fields['groups_id_tech'],
      'condition' => '`is_assign`'));
      echo "</td>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Others') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "others");
      echo "</td>";

      echo "<td>" . __('Location') . "</td><td>";
      Location::dropdown(array('value' => $this->fields["locations_id"],
      'entity' => $this->fields["entities_id"]));
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
            echo "<input type='submit' name='add' id='account_add' value='"._sx('button', 'Add')."' class='submit'>";
            echo "</div>";
            echo Html::scriptBlock("$('#account_form').submit(function(event){
               if ($('#hidden_password').val() == '' || $('#aeskey').val() == '') {
                  alert('".__('You have not filled the password and encryption key', 'accounts')."');
                  return false;
               };
               if (!check_hash()) {
                  alert('".__('Wrong encryption key', 'accounts')."');
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
            echo "<input type='hidden' name='id' value=\"$ID\">";
            echo "<input type='submit' name='update' id='account_update' value=\""._sx('button', 'Save')."\" class='submit' >";
            echo Html::scriptBlock("$('#account_form').submit(function(event){
               if ($('#hidden_password').val() == '' || $('#aeskey').val() == '') {
                  alert('".__('Password will not be modified', 'accounts')."');
               } else if (!check_hash()) {
                  alert('".__('Wrong encryption key', 'accounts')."');
                  return false;
               } else {
                  encrypt_password();
               }
            });");

            if ($this->fields["is_deleted"] == '0')
               echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='delete' value=\"" . _sx('button', 'Put in dustbin') . "\" class='submit'></div>";
            else {
               echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='restore' value=\"" . _sx('button', 'Restore') . "\" class='submit'>";
               echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='purge' value=\"" . _sx('button', 'Delete permanently') . "\" class='submit'></div>";
            }

            echo "</td>";
            echo "</tr>";

         }
      }
      $options['canedit'] = false;
      $options['candel']  = false;
      $this->showFormButtons($options);
      Html::closeForm();

      return true;
   }

   /**
    * Print the list of accounts to be upgraded
    *
    * @param $hash varchar hash used for uncrypt
    *
    * @return Nothing (display)
    **/
   public function showAccountsUpgrade($hash) {

      echo "<div align='center'><b>" . __('2. Migrate accounts', 'accounts') . "</b><br><br>";

      $rand = mt_rand();

      $restrict = " 1=1 ORDER BY `name` ";
      $accounts = getAllDatasFromTable($this->getTable(), $restrict);

      if (!empty($accounts)) {
         echo "<form method='post' name='massiveaction_form$rand' id='massiveaction_form$rand'  action=\"./account.upgrade.php\">";
         echo "<table class='tab_cadre' cellpadding='5'>";
         echo "<tr><th></th><th>" . __('Account names', 'accounts') . "</th><th>" . __('Uncrypted password', 'accounts') . "</th><th>" . __('Encryption key', 'accounts') . "</th></tr>";
         echo "</tr>";
         foreach ($accounts as $account) {
            $ID = $account["id"];
            if (!in_array($ID, $_SESSION['plugin_accounts']['upgrade'])) {
               echo "<tr class='tab_bg_1'>";
               echo "<td class='center'>";
               echo "<input type='hidden' name='update_encrypted_password' value='1'>";
               echo "<input type='checkbox' checked name='item[$ID]' value='1'>";
               echo "</td>";
               echo "<td>";
               echo $account["name"] . "</td>";
               echo "<td><input type='hidden' name='encrypted_password$$ID' value=''><input type='text' name='hidden_password$$ID' value=\"" . $account["encrypted_password"] . "\"></td>";
               echo "<td><input type='text' name='aescrypted_key' id= 'aescrypted_key' value='" . $_SESSION['plugin_accounts']['aescrypted_key'] . "' class='' autocomplete='off'>";
               $js = "var good_hash=\"$hash\";
               var hash=SHA256(SHA256(document.getElementById(\"aescrypted_key\").value));
               document.getElementsByName(\"encrypted_password$$ID\").item(0).value=AESEncryptCtr(document.getElementsByName(\"hidden_password$$ID\").item(0).value,SHA256(document.getElementById(\"aescrypted_key\").value), 256)"; 
               Html::scriptBlock($js);
               
               echo "</td>";
               echo "</td></tr>";
            }
         }

         echo "<tr class='tab_bg_2'><td colspan='4' class='center'>";
         echo "<input type='submit' name='upgrade_accounts[" . $ID . "]' value=\"" . _sx('button', 'Update') . "\" class='submit' >";
         echo "</td></tr>";
         echo "</table>";
         Html::closeForm();
      }
      echo "<br><br><div align='center'><b>" . 
         __('3. If all accounts are migrated, the upgrade is finished', 'accounts') . "</b></div><br><br>";
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
   static function dropdownAccount($options=array()) {
      global $DB, $CFG_GLPI;


      $p['name']    = 'plugin_accounts_accounts_id';
      $p['entity']  = '';
      $p['used']    = array();
      $p['display'] = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $where = " WHERE `glpi_plugin_accounts_accounts`.`is_deleted` = '0' ".
                       getEntitiesRestrictRequest("AND", "glpi_plugin_accounts_accounts", '', $p['entity'], true);

      if (count($p['used'])) {
         $where .= " AND `id` NOT IN (0, ".implode(",",$p['used']).")";
      }

      $query = "SELECT *
                FROM `glpi_plugin_accounts_accounttypes`
                WHERE `id` IN (SELECT DISTINCT `plugin_accounts_accounttypes_id`
                               FROM `glpi_plugin_accounts_accounts`
                             $where)
                ORDER BY `name`";
      $result = $DB->query($query);

      $values = array(0 => Dropdown::EMPTY_VALUE);

      while ($data = $DB->fetch_assoc($result)) {
         $values[$data['id']] = $data['name'];
      }
      $rand = mt_rand();
      $out  = Dropdown::showFromArray('_accounttype', $values, array('width'   => '30%',
                                                                'rand'    => $rand,
                                                                'display' => false));
      $field_id = Html::cleanId("dropdown__accounttype$rand");

      $params   = array('accounttype' => '__VALUE__',
                        'entity' => $p['entity'],
                        'rand'   => $rand,
                        'myname' => $p['name'],
                        'used'   => $p['used']);

      $out .= Ajax::updateItemOnSelectEvent($field_id,"show_".$p['name'].$rand,
                                            $CFG_GLPI["root_doc"]."/plugins/accounts/ajax/dropdownTypeAccounts.php",
                                            $params, false);
      $out .= "<span id='show_".$p['name']."$rand'>";
      $out .= "</span>\n";

      $params['accounttype'] = 0;
      $out .= Ajax::updateItem("show_".$p['name'].$rand,
                               $CFG_GLPI["root_doc"]. "/plugins/accounts/ajax/dropdownTypeAccounts.php",
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
    * @since version 0.84
    * @param $checkitem link item to check right   (default NULL)
    * 
    * @return an array of massive actions
    **/
   public function getSpecificMassiveActions($checkitem = NULL) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         if ($isadmin) {
            $actions['PluginAccountsAccount'.MassiveAction::CLASS_ACTION_SEPARATOR.'install']    = _x('button', 'Associate');
            $actions['PluginAccountsAccount'.MassiveAction::CLASS_ACTION_SEPARATOR.'uninstall'] = _x('button', 'Dissociate');

            if (Session::haveRight('transfer', READ)
                     && Session::isMultiEntitiesMode()
            ) {
               $actions['PluginAccountsAccount'.MassiveAction::CLASS_ACTION_SEPARATOR.'transfer'] = __('Transfer');
            }
         }
      }
      return $actions;
   }

   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case 'add_item':
            self::dropdownAccount(array());
            echo Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
            return true;
         case "install" :
            Dropdown::showAllItems("item_item", 0, 0, -1, self::getTypes(true), 
                                   false, false, 'typeitem');
            echo Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
            return true;
            break;
         case "uninstall" :
            Dropdown::showAllItems("item_item", 0, 0, -1, self::getTypes(true), 
                                   false, false, 'typeitem');
            echo Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
            return true;
            break;
         case "transfer" :
            Dropdown::show('Entity');
            echo Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
            return true;
            break;
      }
      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {

      $account_item = new PluginAccountsAccount_Item();

      switch ($ma->getAction()) {
          case "add_item":
            $input = $ma->getInput();
            foreach ($ma->items as $itemtype => $myitem) {
               foreach ($myitem as $key => $value) {
                  if (!countElementsInTable('glpi_plugin_accounts_accounts_items', 
                                          "itemtype='$itemtype' 
                                             AND items_id='$key' 
                                             AND plugin_accounts_accounts_id='".$input['plugin_accounts_accounts_id']."'")) {
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
                  $values = array('plugin_accounts_accounts_id' => $key,
                                 'items_id'      => $input["item_item"],
                                 'itemtype'      => $input['typeitem']);
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
               if ($val == 1) {
                  if ($account_item->deleteItemByAccountsAndItem($key,$input['item_item'],$input['typeitem'])) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                      $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                  }
               }
            }
            break;
      }
   }

   /**
    * Get the standard massive actions which are forbidden
    *
    * @since version 0.84
    *
    * @return an array of massive actions
    **/
   public function getForbiddenStandardMassiveAction() {
      $forbidden = parent::getForbiddenStandardMassiveAction();
      if (isset ($_SESSION['glpiactiveprofile']['interface'])
               && $_SESSION['glpiactiveprofile']['interface'] != 'central') {
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
            return array(
            'description' => __('Accounts expired or accounts which expires', 'accounts')); // Optional
            break;
      }
      return array();
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
   public static function cronAccountsAlert($task = NULL) {
      global $DB, $CFG_GLPI;

      if (!$CFG_GLPI["use_mailing"]) {
         return 0;
      }

      $cron_status = 0;

      $query_expired = self::queryExpiredAccounts();
      $query_whichexpire = self::queryAccountsWhichExpire();

      $querys = array(Alert::NOTICE => $query_whichexpire, Alert::END => $query_expired);

      $account_infos = array();
      $account_messages = array();

      foreach ($querys as $type => $query) {
         $account_infos[$type] = array();
         foreach ($DB->request($query) as $data) {
            $entity = $data['entities_id'];
            $message = $data["name"] . ": " .
                     Html::convdate($data["date_expiration"]) . "<br>\n";
            $account_infos[$type][$entity][] = $data;

            if (!isset($accounts_infos[$type][$entity])) {
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
                     array('entities_id' => $entity,
                              'accounts' => $accounts))
            ) {
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
    * @param $target target for type change action
    * 
    * @return nothing
    */
   public static function showSelector($target) {
      global $CFG_GLPI;

      $rand = mt_rand();
      Plugin::loadLang('accounts');
      echo "<div class='center' ><span class='b'>" . __('Select the wanted account type', 'accounts') . "</span><br>";
      echo "<a style='font-size:14px;' href='" . $target . "?reset=reset' title=\"" .
               __s('Show all') . "\">" . str_replace(" ", "&nbsp;", __('Show all')) . "</a></div>";

      $js = "$('#tree_projectcategory$rand').jstree({
         'plugins' : ['themes', 'json_data', 'search'],
         'core' : {'load_open': true,
                 'html_titles': true,
                 'animation' : 0},
         'themes' : {
            'theme' : 'classic',
            'url'   : '".$CFG_GLPI["root_doc"]."/css/jstree/style.css'
         },
         'search' : {
            'case_insensitive' : true,
            'show_only_matches' : true,
            'ajax' : {
               'type': 'POST',
               'url' : '".$CFG_GLPI["root_doc"]."/plugins/accounts/ajax/accounttreetypes.php'
            }
         },
         'json_data' : {
            'ajax' : {
               'type': 'POST',
               'url': function (node) {
                  var nodeId = '';
                  var url = '';
                  if (node == -1) {
                     url = '".$CFG_GLPI["root_doc"]."/plugins/accounts/ajax/accounttreetypes.php?node=-1';
                  } else {
                     nodeId = node.attr('id');
                     url = '".$CFG_GLPI["root_doc"]."/plugins/accounts/ajax/accounttreetypes.php?node='+nodeId;
                  }
                  return url;
               },
               'success': function (new_data) {
                  return new_data;
               },
               'progressive_render' : true
            }
         }
      });";

      echo Html::scriptBlock($js);
      echo "<div class='left' style='width:100%'>";
      echo "<div id='tree_projectcategory$rand'></div>";
      echo "</div>";
   }

   /**
    * For other plugins, add a type to the linkable types
    *
    * @since version 1.8.0
    *
    * @param $type string class name
    * 
    * @return nothing
    **/
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
    * @since version 0.83
    *
    * @param $field     String         name of the field
    * @param $values    String/Array   with the value to display or a Single value
    * @param $options   Array          of options
    *
    * @return return the string to display
    **/
   public static function getSpecificValueToDisplay($field, $values, array $options = array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      switch ($field) {
         case 'date_expiration' :
            if (empty($values[$field]))
               return __('Don\'t expire', 'accounts');
            else
               return Html::convdate($values[$field]);
            break;
      }
      return '';
   }
   
   function getRights($interface='central') {

      $values = parent::getRights();

      if ($interface == 'helpdesk') {
         unset($values[CREATE], $values[DELETE], $values[PURGE]);
      }
      return $values;
   }
}

?>