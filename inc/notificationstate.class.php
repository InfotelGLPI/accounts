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

class PluginAccountsNotificationState extends CommonDBTM {

   public function getFromDBbyState($plugin_accounts_accountstates_id) {
      global $DB;

      $query = "SELECT * FROM `".$this->getTable()."` " .
               "WHERE `plugin_accounts_accountstates_id` = '" . $plugin_accounts_accountstates_id . "' ";
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

   public function findStates() {
      global $DB;

      $queryBranch='';
      // Recherche les enfants

      $queryChilds= "SELECT `plugin_accounts_accountstates_id`
               FROM `".$this->getTable()."`";
      if ($resultChilds = $DB->query($queryChilds)) {
         while ($dataChilds = $DB->fetch_array($resultChilds)) {
            $child=$dataChilds["plugin_accounts_accountstates_id"];
            $queryBranch .= ",$child";
         }
      }

      return $queryBranch;
   }

   public function addNotificationState($plugin_accounts_accountstates_id) {

      if ($this->getFromDBbyState($plugin_accounts_accountstates_id)) {

         $this->update(array(
                  'id'=>$this->fields['id'],
                  'plugin_accounts_accountstates_id'=>$plugin_accounts_accountstates_id));
      } else {

         $this->add(array(
                  'plugin_accounts_accountstates_id'=>$plugin_accounts_accountstates_id));
      }
   }

   public function showAddForm($target) {
      global $DB;
      
      $used = array();
      $query = "SELECT *
      FROM `".$this->getTable()."`
      ORDER BY `plugin_accounts_accountstates_id` ASC ";
      if ($result = $DB->query($query)) {
         $number = $DB->numrows($result);
         if ($number != 0) {
             while($ligne= $DB->fetch_array($result)) {
               $used[]=$ligne["plugin_accounts_accountstates_id"];
            }
         }
      }
      
      echo "<div align='center'><form method='post'  action=\"$target\">";
      echo "<table class='tab_cadre_fixe' cellpadding='5'><tr ><th colspan='2'>";
      echo __('Add a unused status for expiration mailing', 'accounts')."</th></tr>";
      echo "<tr class='tab_bg_1'><td>";
      Dropdown::show('PluginAccountsAccountState', array('name' => "plugin_accounts_accountstates_id",
                                                         'used' => $used));
      echo "</td>";
      echo "<td>";
      echo "<div align='center'>";
      echo "<input type='submit' name='add' value=\""._sx('button','Add')."\" class='submit' >";
      echo "</div></td></tr>";
      echo "</table>";
      Html::closeForm();
      echo "</div>";
   }

   public function showForm($target) {
      global $DB;

      $rand=mt_rand();

      $query = "SELECT *
               FROM `".$this->getTable()."`
                        ORDER BY `plugin_accounts_accountstates_id` ASC ";
      if ($result = $DB->query($query)) {
         $number = $DB->numrows($result);
         if ($number != 0) {

            echo "<div align='center'>";
            echo "<form method='post' name='massiveaction_form$rand' id='massiveaction_form$rand' action=\"$target\">";
            echo "<table class='tab_cadre_fixe' cellpadding='5'>";
            echo "<tr>";
            echo "<th></th><th>".__('Unused status for expiration mailing', 'accounts')."</th>";
            echo "</tr>";
            while($ligne= $DB->fetch_array($result)) {
               $ID=$ligne["id"];
               echo "<tr class='tab_bg_1'>";
               echo "<td class='center' width='10'>";
               echo "<input type='hidden' name='id' value='$ID'>";
               echo "<input type='checkbox' name='item[$ID]' value='1'>";
               echo "</td>";
               echo "<td>";
               echo Dropdown::getDropdownName("glpi_plugin_accounts_accountstates",
                        $ligne["plugin_accounts_accountstates_id"]);
               echo "</td>";
               echo "</tr>";
            }

            Html::openArrowMassives("massiveaction_form$rand", true);
            Html::closeArrowMassives(array('delete' => __('Delete permanently')));

            echo "</table>";
            Html::closeForm();
            echo "</div>";
         }
      }
   }
}

?>