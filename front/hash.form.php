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

$account = new PluginAccountsAccount();
$account->checkGlobal(UPDATE);

$hashClass = new PluginAccountsHash();
$dbu       = new DbUtils();

$update = 0;
if ($dbu->countElementsInTable("glpi_plugin_accounts_accounts") > 0) {
   $update = 1;
}

if (isset($_POST["add"])) {

   $hashClass->check(-1, CREATE, $_POST);
   $newID = $hashClass->add($_POST);
   $hashClass->redirectToList();

} else if (isset($_POST["update"]) && $_POST["hash"]) {

   $hashClass->check($_POST['id'], UPDATE);
   $hashClass->update($_POST);
   Html::back();

} else if (isset($_POST["purge"])) {

   $hashClass->check($_POST['id'], DELETE);
   $hashClass->delete($_POST);
   $hashClass->redirectToList();

} else if (isset($_POST['updatehash'])) {

   if (isset($_POST["aeskeynew"]) && isset($_POST["aeskey"])) {

      require_once(PLUGIN_ACCOUNTS_DIR . '/inc/aes.function.php');

      $hash = 0;
      $hash_id = 0;
      $restrict = ["entities_id" => $_SESSION['glpiactive_entity']];
      $hashes = $dbu->getAllDataFromTable("glpi_plugin_accounts_hashes", $restrict);
      if (!empty($hashes)) {
         foreach ($hashes as $hashe) {
            $hash_id = $hashe["id"];
            $hash = $hashe["hash"];
         }
      }

      if (!empty ($_POST["aeskeynew"]) && !empty($_POST["aeskey"]) && !empty($hash)) {
         if ($hash <> hash("sha256", hash("sha256", $_POST["aeskey"]))) {
            Session::addMessageAfterRedirect(__('Wrong encryption key', 'accounts'), true, ERROR);
            Html::back();
         } else {
            PluginAccountsHash::updateHash($_POST["aeskey"], $_POST["aeskeynew"], $hash_id);
            Session::addMessageAfterRedirect(__('Encryption key modified', 'accounts'), true);
            Html::back();
         }
      } else {
         Session::addMessageAfterRedirect(__('The old or the new encryption key can not be empty', 'accounts'), true, ERROR);
         Html::back();
      }
   }
} else {

   if (Plugin::isPluginActive("environment")) {
      Html::header(PluginAccountsAccount::getTypeName(2), '', "assets", "pluginenvironmentdisplay", "hash");
   } else {
      Html::header(PluginAccountsAccount::getTypeName(2), '', "admin", "pluginaccountsaccount", "hash");
   }

   $options = ["id" => $_GET['id'], "update" => false];
   $hashClass->display($options);
   Html::footer();

}
