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
 * Class PluginAccountsHash
 */
class PluginAccountsHash extends CommonDBTM {

   static $rightname = "plugin_accounts_hash";

   public $dohistory = true;

   /**
    * @param int $nb
    *
    * @return translated
    */
   public static function getTypeName($nb = 0) {

      return _n('Encryption key', 'Encryption keys', $nb, 'accounts');
   }

   static function getIcon() {
      return "ti ti-lock-open";
   }

   /**
    * @return bool
    */
   public static function canCreate() {
      return Session::haveRight(static::$rightname, UPDATE);
   }

   /**
    * @return bool
    */
   public static function canView() {
      return Session::haveRight(static::$rightname, READ);
   }

   /**
    * @param CommonGLPI $item
    * @param int        $withtemplate
    *
    * @return array|string
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if (!$withtemplate) {
         switch ($item->getType()) {
            case __CLASS__ :
               $ong    = [];
               $ong[2] = __('Linked accounts list', 'accounts');
               $ong[3] = __('Modification of the encryption key for all password', 'accounts');
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
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == __CLASS__) {

         $key = PluginAccountsAesKey::checkIfAesKeyExists($item->getID());
         switch ($tabnum) {
            case 2 :
               if (!$key) {
                  self::showSelectAccountsList($item->getID());
               } else {
                  $parm     = ["id" => $item->getID(),
                               "aeskey" => $key];
                  $accounts = PluginAccountsReport::queryAccountsList($parm);
                  PluginAccountsReport::showAccountsList($parm, $accounts);
               }
               break;
            case 3 :
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
   public function rawSearchOptions() {
      $tab[] = [
         'id'   => 'common',
         'name' => self::getTypeName(2)
      ];

      $tab[] = [
         'id'            => '1',
         'table'         => $this->getTable(),
         'field'         => 'name',
         'name'          => __('Name'),
         'datatype'      => 'itemlink',
         'itemlink_type' => 'PluginAccountsHash',
         'massiveaction' => false
      ];

      $tab[] = [
         'id'            => '2',
         'table'         => $this->getTable(),
         'field'         => 'hash',
         'name'          => __('Hash'),
         'massiveaction' => false
      ];

      $tab[] = [
         'id'       => '7',
         'table'    => $this->getTable(),
         'field'    => 'comment',
         'name'     => __('Comments'),
         'datatype' => 'text'
      ];

      $tab[] = [
         'id'       => '11',
         'table'    => $this->getTable(),
         'field'    => 'is_recursive',
         'name'     => __('Child entities'),
         'datatype' => 'bool'
      ];

      $tab[] = [
         'id'            => '14',
         'table'         => $this->getTable(),
         'field'         => 'date_mod',
         'name'          => __('Last update'),
         'massiveaction' => false,
         'datatype'      => 'datetime'
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
    * @param array $options
    *
    * @return array
    */
   public function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('PluginAccountsAesKey', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   /**
    * @param       $ID
    * @param array $options
    *
    * @return bool
    */
   public function showForm($ID, $options = []) {

      if (!$this->canView()) {
         return false;
      }
      $dbu      = new DbUtils();
      $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_accounts_hashes",
                                                    '', '', $this->maybeRecursive());

      if ($ID < 1
          && $dbu->countElementsInTable("glpi_plugin_accounts_hashes", $restrict) > 0) {
         echo "<div class='alert alert-important alert-warning d-flex'>" .
              __('WARNING : a encryption key already exist for this entity', 'accounts') . "</div></br>";
      }
      /*
            if ($ID > 0) {
               $this->check($ID, READ);
            } else {
               // Create item
               $this->check(-1, READ);
               $this->getEmpty();
            }
      */
      $options['colspan'] = 1;

      if ($options['update'] == 1) {
         echo "<div class='alert alert-important alert-warning d-flex'>"
              . __('Warning : if you change used hash, the old accounts will use the old encryption key', 'accounts') .
              "</font><br><br>";
      }

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Name') . "</td>";
      echo "<td>";
      echo Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);
      echo "</td>";
      echo "</tr>";

      if ($ID < 1 || ($ID == 1 && $options['update'] == 1)) {
         echo "<tr class='tab_bg_1'>";

         echo "<td>" . __('Encryption key', 'accounts') . "</td>";
         echo "<td>";
         echo Html::input('aeskey', ['id' => 'aeskey', 'size' => 40, 'autocomplete' => 'off']);
         //         echo "<input type='text' name='aeskey' id='aeskey' value='' class='' autocomplete='off'>";
         echo "&nbsp;<input type='button' id='generate_hash'" .
              "value='" . __s('Generate hash with this encryption key', 'accounts') .
              "' class='submit btn btn-primary'>";
         echo Html::scriptBlock("$(document).on('click', '#generate_hash', function(event) {
            if ($('#aeskey').val() == '') {
               alert('" . __('Please fill the encryption key', 'accounts') . "');
               $('#hash').val('');
            } else {
               $('#hash').val(SHA256(SHA256($('#aeskey').val())));
            }
         });");
         echo "</td>";
         echo "</tr>";
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Hash', 'accounts') . "</td>";
      echo "<td>";
      echo Html::input('hash', ['id' => 'hash', 'value' => $this->fields["hash"], 'readonly' => 'readonly', 'size' => 40, 'autocomplete' => 'off']);
      //      echo "<input type='text' readonly='readonly' size='100' id='hash' name='hash' value='" . $this->fields["hash"] . "' autocomplete='off'>";
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td valign='top'>" . __('Comments') . "</td>";
      echo "<td>";
      Html::textarea(['name'            => 'comment',
                      'value'           => $this->fields["comment"],
                      'cols'            => 75,
                      'rows'            => 3,
                      'enable_richtext' => false]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2'>";
      printf(__('Last update on %s'), Html::convDateTime($this->fields["date_mod"]));
      echo "</td>";
      echo "</tr>";

      if ($ID < 1) {
         echo "<tr class='tab_bg_1 '>";
         echo "<td class='alert alert-important alert-warning d-flex' colspan='2'>";
         echo __('Please do not use special characters like / \ \' " & in encryption keys, or you cannot change it after.', 'accounts') . "</td>";
         echo "</tr>";
      }

      if (!$options['update'] == 1) {
         if ($ID < 1
             && $dbu->countElementsInTable("glpi_plugin_accounts_hashes", $restrict) > 0) {
            echo "</table>";
            Html::closeForm();

         } else {
            $this->showFormButtons($options);
         }

      } else {
         echo "</table>";
         Html::closeForm();
         echo "</div>";
      }
      return true;

   }

   /**
    * Prepare input datas for adding the item
    *
    * @param datas $input
    *
    * @return \datas $input
    */
   public function prepareInputForAdd($input) {

      if (isset($input['hash']) && empty($input['hash'])) {
         $message = __('You must generate the hash for your encryption key', 'accounts');
         Session::addMessageAfterRedirect($message, false, ERROR);
         return false;
      }

      return $input;
   }

   /**
    * @param $ID
    */
   public static function showSelectAccountsList($ID) {
      global $CFG_GLPI;

      $rand = mt_rand();

      echo "<div align='center'>";
      echo "<table class='tab_cadre_fixe' cellpadding='5'>";
      echo "<tr><th colspan='2'>";
      echo __('Linked accounts list', 'accounts') . "</th></tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __('Please fill the encryption key', 'accounts') . "</td>";
      echo "<td class='center'>";
      echo Html::input('key', ['id' => 'key', 'type' => 'password', 'size' => 40, 'autocomplete' => 'off']);
      //      echo "<input type='password' class='form-control' autocomplete='off' name='key' id='key'>";
      echo "&nbsp;";
      //      echo "<input type='submit' name='select' value=\"" . __s('Display report') . "\"
      //               class='btn btn-primary' id='showAccountsList$rand'>";
      $id = "showAccountsList$rand";
      echo Html::submit(__s('Display report'), ['name' => 'select', 'form' => '', 'id' => $id, 'class' => 'btn btn-primary']);
      echo "</td>";
      echo "</tr>";
      echo "</table></div>";

      $url = PLUGIN_ACCOUNTS_WEBDIR . "/ajax/viewaccountslist.php";
      echo "<div id='viewaccountslist$rand'></div>";
      echo Html::scriptBlock("$(document).on('click', '#showAccountsList$rand', function(){
         var key = $('#key').val();
         if (key == '') {
            alert('" . __('Please fill the encryption key', 'accounts') . "');
         } else {
            $('#viewaccountslist$rand').load('$url', {'id': $ID, 'key': key});
         }
      });");

   }

   /**
    * @param $hash_id
    */
   public static function showHashChangeForm($hash_id) {

      echo "<div class='alert alert-important alert-warning d-flex'>";
      echo "<b>" . __('Warning : if you make a mistake in entering the old or the new key, you could no longer decrypt your passwords. It is STRONGLY recommended that you make a backup of the database before.', 'accounts') . "</b></div><br>";
      echo "<form method='post' action='./hash.form.php'>";
      echo "<table class='tab_cadre_fixe' cellpadding='5'><tr><th colspan='2'>";
      echo __('Old encryption key', 'accounts') . "</th></tr>";
      echo "<tr class='tab_bg_1 center'><td>";
      $aesKey = new PluginAccountsAesKey();
      $key    = "";
      if ($aesKey->getFromDBByHash($hash_id) && isset($aesKey->fields["name"])) {
         $key = $aesKey->fields["name"];
      }
      echo Html::input('aeskey', ['id' => 'aeskey', 'type' => 'password', 'size' => 40, 'autocomplete' => 'off', 'value' => $key]);
      //      echo "<input type='password' class='form-control' autocomplete='off' name='aeskey' id= 'aeskey' $key >";
      echo "</td></tr>";
      echo "<tr><th>";
      echo __('New encryption key', 'accounts') . "</th></tr>";
      echo "<tr class='tab_bg_1 center'><td>";
      echo Html::input('aeskeynew', ['id' => 'aeskeynew', 'type' => 'password', 'size' => 40, 'autocomplete' => 'off']);
      //      echo "<input type='password' class='form-control' autocomplete='off' name='aeskeynew' id= 'aeskeynew'>";
      echo "</td></tr>";
      echo "<tr class='tab_bg_1 center'><td>";
      $message  = __('You want to change the key : ', 'accounts');
      $message2 = __(' by the key : ', 'accounts');
      echo Html::hidden('ID', ['value' => $hash_id]);
      echo Html::submit(_sx('button', 'Update'), ['name' => 'updatehash',  'class' => 'btn btn-primary']);
      //
      //      echo "<input type='submit' name='updatehash' value=\"" . _sx('button', 'Update') . "\" class='btn btn-primary'
      //      onclick=' '>";
      echo "</td></tr>";
      echo "</table>";
      Html::closeForm();
      echo "</div>";
   }

   /**
    * @param $oldaeskey
    * @param $newaeskey
    * @param $hash_id
    */
   public static function updateHash($oldaeskey, $newaeskey, $hash_id) {
      global $DB;

      $PluginAccountsHash = new self();
      $PluginAccountsHash->getFromDB($hash_id);
      $dbu = new DbUtils();

      if ($PluginAccountsHash->isRecursive()) {
         $entities = $dbu->getSonsOf('glpi_entities', $PluginAccountsHash->getEntityID());
      } else {
         $entities = $PluginAccountsHash->getEntityID();
      }

      $account = new PluginAccountsAccount();
      $aeskey  = new PluginAccountsAesKey();

      $oldhash      = hash("sha256", $oldaeskey);
      $newhash      = hash("sha256", $newaeskey);
      $newhashstore = hash("sha256", $newhash);
      // uncrypt passwords for update
      $query_ = "SELECT *
                FROM `glpi_plugin_accounts_accounts`
                WHERE 1 ";
      $query_ .= $dbu->getEntitiesRestrictRequest("AND", "glpi_plugin_accounts_accounts", '',
                                                  $entities, $PluginAccountsHash->maybeRecursive());

      $result_ = $DB->query($query_);
      if ($DB->numrows($result_) > 0) {

         while ($data = $DB->fetchArray($result_)) {

            $oldpassword = addslashes(plugin_accounts_AESDecryptCtr($data['encrypted_password'], $oldhash, 256));
            $newpassword = addslashes(plugin_accounts_AESEncryptCtr($oldpassword, $newhash, 256));

            $account->update([
                                'id'                 => $data["id"],
                                'encrypted_password' => $newpassword]);
         }
         $PluginAccountsHash->update(['id' => $hash_id, 'hash' => $newhashstore]);

         if ($aeskey->getFromDBByHash($hash_id) && isset($aeskey->fields["name"])) {
            $values["id"]   = $aeskey->fields["id"];
            $values["name"] = $newaeskey;
            $aeskey->update($values);
         }
      }
   }
}
