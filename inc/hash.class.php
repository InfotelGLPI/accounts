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

class PluginAccountsHash extends CommonDBTM {

   static $rightname = "config";
   
   public $dohistory=true;

   public static function getTypeName($nb=0) {

      return _n('Encryption key', 'Encryption keys', $nb, 'accounts');
   }

   public static function canCreate() {
      return Session::haveRight(static::$rightname, UPDATE);
   }

   public static function canView() {
      return Session::haveRight(static::$rightname, READ);
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (!$withtemplate) {
         switch ($item->getType()) {
            case __CLASS__ :
               $ong = array();
               $ong[2] = __('Linked accounts list', 'accounts');
               $ong[3] = __('Modification of the encryption key for all password', 'accounts');
               return $ong;
         }
      }
      return '';
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType() == __CLASS__) {

         $key = PluginAccountsAesKey::checkIfAesKeyExists($item->getID());
         switch ($tabnum) {
            case 2 :
               if (!$key) {
                  self::showSelectAccountsList($item->getID());
               } else {
                  $parm = array("id" => $item->getID(), "aeskey" => $key);
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

   public function getSearchOptions() {

      $tab                       = array();

      $tab['common']             = self::getTypeName(2);

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();

      $tab[2]['table']           = $this->getTable();
      $tab[2]['field']           = 'hash';
      $tab[2]['name']            = __('Hash', 'accounts');
      $tab[2]['massiveaction']   = false;

      $tab[7]['table']           = $this->getTable();
      $tab[7]['field']           = 'comment';
      $tab[7]['name']            = __('Comments');
      $tab[7]['datatype']        = 'text';

      $tab[11]['table']          = $this->getTable();
      $tab[11]['field']          = 'is_recursive';
      $tab[11]['name']           = __('Child entities');
      $tab[11]['datatype']       = 'bool';

      $tab[14]['table']          = $this->getTable();
      $tab[14]['field']          = 'date_mod';
      $tab[14]['name']           = __('Last update');
      $tab[14]['massiveaction']  = false;
      $tab[14]['datatype']       = 'datetime';

      $tab[80]['table']          = 'glpi_entities';
      $tab[80]['field']          = 'completename';
      $tab[80]['name']           = __('Entity');
      $tab[80]['datatype']       = 'dropdown';

      return $tab;
   }

   public function defineTabs($options=array()) {

      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('PluginAccountsAesKey', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   public function showForm ($ID, $options=array()) {

      if (!$this->canView()) return false;

      $restrict = getEntitiesRestrictRequest(" ", 
                                             "glpi_plugin_accounts_hashes",
                                             '','',$this->maybeRecursive());

      if($ID < 1 
           && countElementsInTable("glpi_plugin_accounts_hashes",$restrict) > 0) {
         echo "<div class='center red'>".
            __('WARNING : a encryption key already exist for this entity', 'accounts')."</div></br>";
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

      if (!$options['upgrade'] && $options['update']==1) {
         echo "<div class='center red'>"
            .__('Warning : if you change used hash, the old accounts will use the old encryption key', 'accounts').
         "</font><br><br>";
      }

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";

      echo "<td>".__('Name')."</td>";
      echo "<td>";
      Html::autocompletionTextField($this,"name");
      echo "</td>";
      echo "</tr>";

      if ($ID < 1 || ($ID == 1 && $options['update']==1)) {
         echo "<tr class='tab_bg_1'>";

         echo "<td>".__('Encryption key', 'accounts')."</td>";
         echo "<td>";
         echo "<input type='text' name='aeskey' id='aeskey' value='' class='' autocomplete='off'>";
         $message=__('The hash to insert into the next field for create crypt is : ', 'accounts');
         echo "&nbsp;<input type='button' id='generate_hash'".
              "value='".__s('Generate hash with this encryption key', 'accounts').
              "' class='submit'>";
         echo Html::scriptBlock("$(document).on('click', '#generate_hash', function(event) {
            if ($('#aeskey').val() == '') {
               alert('".__('Please fill the encryption key', 'accounts')."');
               $('#hash').val('');
            } else {
               $('#hash').val(SHA256(SHA256($('#aeskey').val())));
            }
         });");
         echo "</td>";
         echo "</tr>";
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Hash', 'accounts')."</td>";
      echo "<td>";
      echo "<input type='text' readonly='readonly' size='100' id='hash' name='hash' value='".$this->fields["hash"]."' autocomplete='off'>";
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td valign='top'>".__('Comments')."</td>";
      echo "<td>";
      echo "<textarea cols='75' rows='3' name='comment'>".$this->fields["comment"]."</textarea>";
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2'>";
      printf(__('Last update on %s'), Html::convDateTime($this->fields["date_mod"]));
      echo "</td>";
      echo "</tr>";

      if ($ID < 1) {
         echo "<tr class='tab_bg_1 '>";
         echo "<td class='center red' colspan='2'>";
         echo __('Please do not use special characters like / \ \' " & in encryption keys, or you cannot change it after.', 'accounts')."</td>";
         echo "</tr>";
      }

      if ($options['upgrade']) {
         echo "<tr class='tab_bg_1'>";
         echo "<td class='center' colspan='2'>";
         echo "<input type='hidden' name='id' value='1'>";
         echo "<input type='submit' name='upgrade' value=\""._sx('button','Upgrade')."\" class='submit' >";
         echo "</td></tr>";
      }

      if (!$options['update']==1) {
         $this->showFormButtons($options);
      } else {
         echo "</table>";
         Html::closeForm();
         echo "</div>";
      }
      return true;
      //$this->addDivForTabs();

   }

   public static function showSelectAccountsList($ID) {
      global $CFG_GLPI;

      $rand = mt_rand();

      echo "<div align='center'>";
      echo "<table class='tab_cadre_fixe' cellpadding='5'>";
      echo "<tr><th colspan='2'>";
      echo __('Linked accounts list', 'accounts')."</th></tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>";
      echo __('Please fill the encryption key', 'accounts')."</td>";
      echo "<td class='center'>";
      echo "<input type='password' autocomplete='off' name='key' id='key'>&nbsp;";
      echo "<input type='submit' name='select' value=\"".__s('Display report')."\"
               class='submit' id='showAccountsList$rand'>";
      echo "</td>";
      echo "</tr>";
      echo "</table></div>";

      $url = $CFG_GLPI["root_doc"]."/plugins/accounts/ajax/viewaccountslist.php";
      echo "<div id='viewaccountslist$rand'></div>";
      echo Html::scriptBlock("$(document).on('click', '#showAccountsList$rand', function(){
         var key = $('#key').val();
         if (key == '') {
            alert('".__('Please fill the encryption key', 'accounts')."');
         } else {
            $('#viewaccountslist$rand').load('$url', {'id': $ID, 'key': key});
         }
      });");


   }

   public static function showHashChangeForm ($hash_id) {

      echo "<div class='center red'>";
      echo "<b>".__('Warning : if you make a mistake in entering the old or the new key, you could no longer decrypt your passwords. It is STRONGLY recommended that you make a backup of the database before.', 'accounts')."</b></div><br>";
      echo "<form method='post' action='./hash.form.php'>";
      echo "<table class='tab_cadre_fixe' cellpadding='5'><tr><th colspan='2'>";
      echo __('Old encryption key', 'accounts')."</th></tr>";
      echo "<tr class='tab_bg_1 center'><td>";
      $aesKey=new PluginAccountsAesKey();
      $key = "";
      if ($aesKey->getFromDBByHash($hash_id) && isset($aesKey->fields["name"]))
         $key = "value='".$aesKey->fields["name"]."' ";
      echo "<input type='password' autocomplete='off' name='aeskey' id= 'aeskey' $key >";
      echo "</td></tr>";
      echo "<tr><th>";
      echo __('New encryption key', 'accounts')."</th></tr>";
      echo "<tr class='tab_bg_1 center'><td>";
      echo "<input type='password' autocomplete='off' name='aeskeynew' id= 'aeskeynew'>";
      echo "</td></tr>";
      echo "<tr class='tab_bg_1 center'><td>";
      $message=__('You want to change the key : ', 'accounts');
      $message2=__(' by the key : ', 'accounts');
      echo "<input type='hidden' name='ID' value='".$hash_id."'>";
      echo "<input type='submit' name='updatehash' value=\""._sx('button','Update')."\" class='submit'
      onclick='return (confirm(\"$message\" +  document.getElementById(\"aeskey\").value + \"$message2\" + document.getElementById(\"aeskeynew\").value)) '>";
      echo "</td></tr>";
      echo "</table>";
      Html::closeForm();
      echo "</div>";
   }

   public static function updateHash($oldaeskey, $newaeskey, $hash_id) {
      global $DB;

      $self=new self();
      $self->getFromDB($hash_id);

      $account = new PluginAccountsAccount();
      $aeskey  = new PluginAccountsAesKey();

      $oldhash      = hash ( "sha256" ,$oldaeskey);
      $newhash      = hash ( "sha256" ,$newaeskey);
      $newhashstore = hash ( "sha256" ,$newhash);
      // uncrypt passwords for update
      $query_= "SELECT *
                FROM `glpi_plugin_accounts_accounts`
                WHERE ";
      $query_.= getEntitiesRestrictRequest(" ","glpi_plugin_accounts_accounts",'',$self->fields['entities_id'], $self->fields['is_recursive']);

      $result_=$DB->query($query_);
      if ($DB->numrows($result_)>0){

         while ($data=$DB->fetch_array($result_)){
             
            $oldpassword=addslashes(plugin_accounts_AESDecryptCtr($data['encrypted_password'], $oldhash, 256));
            $newpassword=addslashes(plugin_accounts_AESEncryptCtr($oldpassword, $newhash, 256));

            $account->update(array(
                     'id'=>$data["id"],
                     'encrypted_password'=>$newpassword));
         }
         $self->update(array('id'=>$hash_id,'hash'=>$newhashstore));

         if ($aeskey->getFromDBByHash($hash_id) && isset($aeskey->fields["name"])) {
            $values["id"]   = $aeskey->fields["id"];
            $values["name"] = $newaeskey;
            $aeskey->update($values);
         }
      }
   }
}

?>