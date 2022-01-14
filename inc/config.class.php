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
 * Class PluginAccountsConfig
 */
class PluginAccountsConfig extends CommonDBTM
{

   /**
    * @param CommonGLPI $item
    * @param int $withtemplate
    * @return string|translated
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'CronTask' && $item->getField('name') == "AccountsAlert") {
         return __('Plugin Setup', 'accounts');
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

      if ($item->getType() == 'CronTask') {

         PluginAccountsAccount::configCron(1);
      }
      return true;
   }

   /**
    * @param $target
    * @param $ID
    */
   public function showConfigForm($target, $ID) {

      $this->getFromDB($ID);
      $delay_expired = $this->fields["delay_expired"];
      $delay_whichexpire = $this->fields["delay_whichexpire"];
      echo "<div align='center'>";
      $target = PLUGIN_ACCOUNTS_WEBDIR . "/front/notification.state.php";
      echo "<form method='post' action=\"$target\">";
      echo "<table class='tab_cadre_fixe' cellpadding='5'><tr><th>";
      echo __('Time of checking of of expiration of accounts', 'accounts') . "</th></tr>";
      echo "<tr class='tab_bg_1'><td><div align='center'>";

      $delay_stamp_first = mktime(0, 0, 0, date("m"), date("d") - $delay_expired, date("y"));
      $delay_stamp_next = mktime(0, 0, 0, date("m"), date("d") + $delay_whichexpire, date("y"));
      $date_first = date("Y-m-d", $delay_stamp_first);
      $date_next = date("Y-m-d", $delay_stamp_next);

      echo "<tr class='tab_bg_1'><td><div align='left'>";
      echo __('Accounts expired for more than', 'accounts');
      echo "&nbsp;";
      echo Html::input('delay_expired', ['value' => $delay_expired, 'size' => 5]);
      echo "&nbsp;";
      echo _n('Day', 'Days', 2) . " ( >" . Html::convDate($date_first) . ")<br>";
      echo __('Accounts expiring in less than', 'accounts');
      echo "&nbsp;";
      echo Html::input('delay_whichexpire', ['value' => $delay_whichexpire, 'size' => 5]);
      echo "&nbsp;";
      echo _n('Day', 'Days', 2) . " ( <" . Html::convDate($date_next) . ")";

      echo "</td>";
      echo "</tr>";

      echo "<tr><th>";
      echo Html::hidden('id', ['value' => $ID]);
      echo "<div align='center'>";
      echo Html::submit(_sx('button', 'Save'), ['name' => 'xxx', 'update' => 'btn btn-primary']);
      echo "</div></th></tr>";
      echo "</table>";
      Html::closeForm();
      echo "</div>";
   }
}
