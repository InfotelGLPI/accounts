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


include('../../../inc/includes.php');

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}
if (!isset($_GET["plugin_accounts_hashes_id"])) {
   $_GET["plugin_accounts_hashes_id"] = "";
}

Session::checkRight("config", UPDATE);

$aeskey = new PluginAccountsAesKey();

if (Plugin::isPluginActive("environment")) {
   Html::header(PluginAccountsAccount::getTypeName(2), '', "assets", "pluginenvironmentdisplay", "accounts");
} else {
   Html::header(PluginAccountsAccount::getTypeName(2), '', "admin", "pluginaccountsaccount", "hash");
}

if (isset($_POST["add"])) {
   if ($aeskey->canCreate()) {
      $newID = $aeskey->add($_POST);
   }
   if ($_SESSION['glpibackcreated']) {
      Html::redirect($aeskey->getFormURL() . "?id=" . $newID);
   }
   Html::back();

} else if (isset($_POST["update"])) {

   if ($aeskey->canCreate()) {
      $aeskey->update($_POST);
   }
   Html::back();

} else if (isset($_POST["delete"])) {
   if ($aeskey->canCreate()) {
      foreach ($_POST["check"] as $ID => $value) {
         $aeskey->delete(["id" => $ID], 1);
      }
   }
   Html::back();

} else {
   $aeskey->display(['id' => $_GET['id'],
      'plugin_accounts_hashes_id' => $_GET["plugin_accounts_hashes_id"]]);
}

Html::footer();
