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

class PluginAccountsAesKey extends CommonDBTM {

   static $rightname = "plugin_accounts";

   /**
    * @var hash
    */
   private $h;
   
   public function __construct(){
      $this->h = new PluginAccountsHash();
   }
   
   public static function getTypeName($nb=0) {
      return _n('Encryption key','Encryption key', $nb, 'accounts');
   }

   public function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (!$withtemplate) {
         switch ($item->getType()) {
            case 'PluginAccountsHash':
               return __('Save the encryption key', 'accounts');
            case __CLASS__ :
               return self::getTypeName();
         }
      }
      return '';
   }

   public static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
       
      $self = new self();

      switch ($item->getType()) {
         case 'PluginAccountsHash':
            $key = self::checkIfAesKeyExists($item->getID());
            if ($key) {
               $self->showAesKey($item->getID());
            }
            if (!$key) {
               $self->showForm("", array('plugin_accounts_hashes_id' => $item->getID()));
            }
            break;
         case __CLASS__ :
            $item->showForm($item->getID(), $item->fields);
      }
      return true;
   }

   public function getFromDBByHash($plugin_accounts_hashes_id) {
      global $DB;

      $query = "SELECT * FROM `".$this->getTable()."`
               WHERE `plugin_accounts_hashes_id` = '" . $plugin_accounts_hashes_id . "' ";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result) != 1) {
            return false;
         }
         $this->fields = $DB->fetch_assoc($result);
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         } else {
            return false;
         }
      }
      return false;
   }

   public static function checkIfAesKeyExists($plugin_accounts_hashes_id) {

      $aeskey = false;
      if ($plugin_accounts_hashes_id) {
         $devices = getAllDatasFromTable("glpi_plugin_accounts_aeskeys",
                  "`plugin_accounts_hashes_id` = '$plugin_accounts_hashes_id' ");
         if (!empty($devices)) {
            foreach ($devices as $device) {
               $aeskey = $device["name"];
               return $aeskey;
            }
         } else
            return $aeskey;
      }
   }

   public function defineTabs($options=array()) {

      $ong = array();
      $this->addStandardTab(__CLASS__, $ong, $options);
      return $ong;
   }

   public function showForm($ID, $options=array()) {
      $restrict = getEntitiesRestrictRequest(" ","glpi_plugin_accounts_hashes",'','',$this->h->maybeRecursive());
      if(countElementsInTable("glpi_plugin_accounts_hashes",$restrict) == 0) {
         echo "<div class='center red'>".__('Encryption key modified', 'accounts')."</div></br>";
      }

      $plugin_accounts_hashes_id= -1;
      if (isset($options['plugin_accounts_hashes_id'])) {
         $plugin_accounts_hashes_id = $options['plugin_accounts_hashes_id'];
      }

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<div class='center red b'>".__('Warning : saving the encryption key is a security hole', 'accounts')."</div></br>";

      $options['colspan'] = 2;
      $this->h->getFromDB($plugin_accounts_hashes_id);
      echo "<input type='hidden' name='plugin_accounts_hashes_id' value='$plugin_accounts_hashes_id'>";

      echo "<tr class='tab_bg_2'><td colspan='2'>";
      _e('Encryption key', 'accounts');
      echo "</td><td colspan='2'>";
      echo "<input type='password' autocomplete='off' name='name' value='".$this->fields["name"]."'>";
      echo "</td>";
      echo "</tr>";
      $options['candel'] = false;
      $this->showFormButtons($options);
   }

   public function prepareInputForAdd($input) {
      // Not attached to hash -> not added
      if (!isset($input['plugin_accounts_hashes_id']) || $input['plugin_accounts_hashes_id'] <= 0) {
         return false;
      }
      return $input;
   }

   public function showAesKey($ID) {
      global $DB;

      $this->h->getFromDB($ID);

      Session::initNavigateListItems("PluginAccountsAesKey",__('Hash', 'accounts')." = ".$this->h->fields["name"]);

      $candelete =$this->h->can($ID, DELETE);
      $query = "SELECT *
      FROM `glpi_plugin_accounts_aeskeys`
      WHERE `plugin_accounts_hashes_id` = '$ID' ";
      $result = $DB->query($query);
      $rand=mt_rand();
      echo "<div class='center'>";
      echo "<form method='post' name='show_aeskey$rand' id='show_aeskey$rand' action=\"./aeskey.form.php\">";
      echo "<input type='hidden' name='plugin_accounts_hashes_id' value='" . $ID . "'>";
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th colspan='5'>".__('Encryption key', 'accounts')."</th></tr>";
      echo "<tr><th>&nbsp;</th>";
      echo "<th class='left'>" . __('Name') . "</th>";
      echo "</tr>";

      if ($DB->numrows($result) > 0) {

         while ($data = $DB->fetch_array($result)) {
            Session::addToNavigateListItems("PluginAccountsAesKey",$data['id']);
            echo "<input type='hidden' name='item[" . $data["id"] . "]' value='" . $ID . "'>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<td width='10'>";
            if ($candelete) {
               echo "<input type='checkbox' name='check[" . $data["id"] . "]'";
               if (isset($_POST['check']) && $_POST['check'] == 'all')
                  echo " checked ";
               echo ">";
            }
            echo "</td>";
            $link=Toolbox::getItemTypeFormURL("PluginAccountsAesKey");
            echo "<td class='left'><a href='".$link."?id=".$data["id"]."&plugin_accounts_hashes_id=".$ID."'>";
            echo __('Encryption key', 'accounts') . "</a></td>";
            echo "</tr>";
         }
         echo "</table>";

         if ($candelete) {
            Html::openArrowMassives("show_aeskey$rand", true);
            Html::closeArrowMassives(array('delete' => __('Delete permanently')));
         }
      } else {
         echo "</table>";
      }
      Html::closeForm();
      echo "</div>";
   }

}

?>