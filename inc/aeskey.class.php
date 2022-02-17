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
 * Class PluginAccountsAesKey
 */
class PluginAccountsAesKey extends CommonDBTM
{

   static $rightname = "plugin_accounts";

   /**
    * @var hash
    */
   private $h;

   /**
    * PluginAccountsAesKey constructor.
    */
   public function __construct() {
      $this->h = new PluginAccountsHash();
   }

   static function getIcon() {
      return "ti ti-lock-open";
   }

   /**
    * @param int $nb
    * @return translated
    */
   public static function getTypeName($nb = 0) {
      return _n('Encryption key', 'Encryption key', $nb, 'accounts');
   }

   /**
    * @param CommonGLPI $item
    * @param int $withtemplate
    * @return string|translated
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

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

   /**
    * @param CommonGLPI $item
    * @param int $tabnum
    * @param int $withtemplate
    * @return bool
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      $self = new self();

      switch ($item->getType()) {
         case 'PluginAccountsHash':
            $key = self::checkIfAesKeyExists($item->getID());
            if ($key) {
               $self->showAesKey($item->getID());
            }
            if (!$key) {
               $self->showForm("", ['plugin_accounts_hashes_id' => $item->getID()]);
            }
            break;
         case __CLASS__ :
            $item->showForm($item->getID());
      }
      return true;
   }

   /**
    * @param $plugin_accounts_hashes_id
    * @return bool
    */
   public function getFromDBByHash($plugin_accounts_hashes_id) {
      global $DB;

      $query = "SELECT * FROM `" . $this->getTable() . "`
               WHERE `plugin_accounts_hashes_id` = '" . $plugin_accounts_hashes_id . "' ";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result) != 1) {
            return false;
         }
         $this->fields = $DB->fetchAssoc($result);
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         } else {
            return false;
         }
      }
      return false;
   }

   /**
    * @param $plugin_accounts_hashes_id
    * @return bool
    */
   public static function checkIfAesKeyExists($plugin_accounts_hashes_id) {

      $aeskey = false;
      if ($plugin_accounts_hashes_id) {
         $dbu = new DbUtils();
         $devices = $dbu->getAllDataFromTable("glpi_plugin_accounts_aeskeys",
            ["plugin_accounts_hashes_id" => $plugin_accounts_hashes_id]);
         if (!empty($devices)) {
            foreach ($devices as $device) {
               $aeskey = $device["name"];
               return $aeskey;
            }
         } else {
            return $aeskey;
         }
      }
   }

   /**
    * @param array $options
    * @return array
    */
   public function defineTabs($options = []) {

      $ong = [];
      $this->addStandardTab(__CLASS__, $ong, $options);
      return $ong;
   }

   /**
    * @param $ID
    * @param array $options
    */
   public function showForm($ID, $options = []) {
      $dbu = new DbUtils();
      $restrict = $dbu->getEntitiesRestrictCriteria("glpi_plugin_accounts_hashes", '', '', $this->h->maybeRecursive());
      if ($dbu->countElementsInTable("glpi_plugin_accounts_hashes", $restrict) == 0) {
         echo "<div class='center red'>" . __('Encryption key modified', 'accounts') . "</div></br>";
      }

      $plugin_accounts_hashes_id = -1;
      if (isset($options['plugin_accounts_hashes_id'])) {
         $plugin_accounts_hashes_id = $options['plugin_accounts_hashes_id'];
      }

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<div class='alert alert-important alert-warning d-flex'>" . __('Warning : saving the encryption key is a security hole', 'accounts') . "</div></br>";

      $options['colspan'] = 2;
      $this->h->getFromDB($plugin_accounts_hashes_id);
      echo Html::hidden('plugin_accounts_hashes_id', ['value' => $plugin_accounts_hashes_id]);

      echo "<tr class='tab_bg_2'><td colspan='2'>";
      echo __('Encryption key', 'accounts');
      echo "</td><td colspan='2'>";
      echo Html::input('name', ['value' => $this->fields["name"], 'type' => 'password', 'size' => 40, 'autocomplete' => 'off']);
      echo "</td>";
      echo "</tr>";
      $options['candel'] = false;
      $this->showFormButtons($options);
   }

   /**
    * @param datas $input
    * @return bool|datas
    */
   public function prepareInputForAdd($input) {
      // Not attached to hash -> not added
      if (!isset($input['plugin_accounts_hashes_id']) || $input['plugin_accounts_hashes_id'] <= 0) {
         return false;
      }
      return $input;
   }

   /**
    * @param $ID
    */
   public function showAesKey($ID) {
      global $DB;

      $this->h->getFromDB($ID);

      Session::initNavigateListItems("PluginAccountsAesKey", __('Hash', 'accounts') . " = " . $this->h->fields["name"]);

      $candelete = Session::haveRight(self::$rightname, DELETE);
      $query     = "SELECT *
                     FROM `glpi_plugin_accounts_aeskeys`
                     WHERE `plugin_accounts_hashes_id` = '$ID' ";
      $result    = $DB->query($query);
      $numrows   = $DB->numrows($result);

      $rand = mt_rand();
      echo "<div class='left'>";

      echo Html::hidden('plugin_accounts_hashes_id', ['value' => $ID]);

      if ($candelete && $numrows) {
         Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
         $massiveactionparams = ['item' => __CLASS__, 'container' => 'mass' . __CLASS__ . $rand];
         Html::showMassiveActions($massiveactionparams);
      }

      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th colspan='" . ($candelete ? 2 : 1) . "'>" . __('Encryption key', 'accounts') . "</th></tr>";
      echo "<tr>";
      if ($candelete && $numrows) {
         echo "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand) . "</th>";
      }
      echo "<th class='left'>" . __('Name') . "</th>";
      echo "</tr>";

      if ($DB->numrows($result) > 0) {

         while ($data = $DB->fetchArray($result)) {
            Session::addToNavigateListItems("PluginAccountsAesKey", $data['id']);
            $name = "item[" . $data["id"] . "]";
            echo Html::hidden($name, ['value' => $ID]);
            echo "<tr class='tab_bg_1 center'>";
            if ($candelete) {
               echo "<td width='10'>";
               Html::showMassiveActionCheckBox(__CLASS__, $data["id"]);
               echo "</td>";
            }
            $link = Toolbox::getItemTypeFormURL("PluginAccountsAesKey");
            echo "<td class='left'><a href='" . $link . "?id=" . $data["id"] . "&plugin_accounts_hashes_id=" . $ID . "'>";
            echo __('Encryption key', 'accounts') . "</a></td>";
            echo "</tr>";
         }

         echo "<tr>";
         if ($candelete && $numrows) {
            echo "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand) . "</th>";
         }
         echo "<th class='left'>" . __('Name') . "</th>";
         echo "</tr>";
         echo "</table>";

         if ($candelete) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
         }
      } else {
         echo "</table>";
      }
      echo "</div>";
   }

   /**
    * @return an|array
    */
   function getForbiddenStandardMassiveAction() {

      $forbidden   = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'update';
      return $forbidden;
   }

}
