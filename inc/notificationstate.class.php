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
 * Class PluginAccountsNotificationState
 */
class PluginAccountsNotificationState extends CommonDBTM
{

   /**
    * @param $plugin_accounts_accountstates_id
    * @return bool
    */
   public function getFromDBbyState($plugin_accounts_accountstates_id) {
      global $DB;

      $query = "SELECT * FROM `" . $this->getTable() . "` " .
         "WHERE `plugin_accounts_accountstates_id` = '" . $plugin_accounts_accountstates_id . "' ";
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
    * @return string
    */
   public function findStates() {
      global $DB;

      $queryBranch = '';
      // Recherche les enfants

      $queryChilds = "SELECT `plugin_accounts_accountstates_id`
               FROM `" . $this->getTable() . "`";
      if ($resultChilds = $DB->query($queryChilds)) {
         while ($dataChilds = $DB->fetchArray($resultChilds)) {
            $child = $dataChilds["plugin_accounts_accountstates_id"];
            $queryBranch .= ",$child";
         }
      }

      return $queryBranch;
   }

   /**
    * @param $plugin_accounts_accountstates_id
    */
   public function addNotificationState($plugin_accounts_accountstates_id) {

      if ($this->getFromDBbyState($plugin_accounts_accountstates_id)) {

         $this->update([
            'id' => $this->fields['id'],
            'plugin_accounts_accountstates_id' => $plugin_accounts_accountstates_id]);
      } else {

         $this->add([
            'plugin_accounts_accountstates_id' => $plugin_accounts_accountstates_id]);
      }
   }

   /**
    * @param $target
    */
   public function showAddForm($target) {
      global $DB;

      $used = [];
      $query = "SELECT *
      FROM `" . $this->getTable() . "`
      ORDER BY `plugin_accounts_accountstates_id` ASC ";
      if ($result = $DB->query($query)) {
         $number = $DB->numrows($result);
         if ($number != 0) {
            while ($ligne = $DB->fetchArray($result)) {
               $used[] = $ligne["plugin_accounts_accountstates_id"];
            }
         }
      }

      echo "<div align='center'><form method='post'  action=\"$target\">";
      echo "<table class='tab_cadre_fixe' cellpadding='5'><tr ><th colspan='2'>";
      echo __('Add a unused status for expiration mailing', 'accounts') . "</th></tr>";
      echo "<tr class='tab_bg_1'><td>";
      Dropdown::show('PluginAccountsAccountState', ['name' => "plugin_accounts_accountstates_id",
         'used' => $used]);
      echo "</td>";
      echo "<td>";
      echo "<div align='center'>";
      echo Html::submit(_sx('button', 'Add'), ['name' => 'add', 'update' => 'btn btn-primary']);
      echo "</div></td></tr>";
      echo "</table>";
      Html::closeForm();
      echo "</div>";
   }

   /**
    * @param $target
    */
   public function showNotificationForm($target) {
      global $DB;

      $rand = mt_rand();

      $query = "SELECT *
               FROM `" . $this->getTable() . "`
                        ORDER BY `plugin_accounts_accountstates_id` ASC ";
      if ($result = $DB->query($query)) {
         $number = $DB->numrows($result);
         if ($number != 0) {

            echo "<div align='center'>";

            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams = ['item' => __CLASS__, 'container' => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);

            echo "<table class='tab_cadre_fixe' cellpadding='5'>";
            echo "<tr>";
            echo "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand) . "</th>";
            echo "<th>" . __('Unused status for expiration mailing', 'accounts') . "</th>";
            echo "</tr>";
            while ($ligne = $DB->fetchArray($result)) {
               $ID = $ligne["id"];
               echo "<tr class='tab_bg_1'>";
               echo "<td class='center' width='10'>";
               Html::showMassiveActionCheckBox(__CLASS__, $ID);
               echo "</td>";
               echo "<td>";
               echo Dropdown::getDropdownName("glpi_plugin_accounts_accountstates",
                  $ligne["plugin_accounts_accountstates_id"]);
               echo "</td>";
               echo "</tr>";
            }

            echo "<tr>";
            echo "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand) . "</th>";
            echo "<th></th>";
            echo "</tr>";

            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();

            echo "</table>";
            Html::closeForm();
            echo "</div>";
         }
      }
   }

   /**
    * Get the specific massive actions
    *
    * @since version 0.84
    *
    * @param $checkitem link item to check right   (default NULL)
    *
    * @return an array of massive actions
    * */
   function getSpecificMassiveActions($checkitem = null) {
      $actions = parent::getSpecificMassiveActions($checkitem);

      $actions['PluginAccountsNotificationState' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Delete'] = __('Delete');
      return $actions;
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    *
    * @param MassiveAction $ma
    * @param CommonDBTM    $item
    * @param array         $ids
    *
    * @return nothing|void
    */
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {

      switch ($ma->getAction()) {
         case 'Delete':
            $notif = new PluginAccountsNotificationState();
            foreach ($ids as $id) {
               if ($notif->delete(['id' => $id])) {
                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
               } else {
                  $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
               }

            }

            return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }
}
