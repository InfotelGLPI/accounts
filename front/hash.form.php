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


include ('../../../inc/includes.php');

if (!isset($_GET["id"])) $_GET["id"] = "";

$account=new PluginAccountsAccount();
$account->checkGlobal(UPDATE);

$hashClass=new PluginAccountsHash();

$update=0;
if (countElementsInTable("glpi_plugin_accounts_accounts")>0) {
   $update=1;
}

if (isset($_POST["add"])) {

   $hashClass->check(-1, CREATE, $_POST);
   $newID=$hashClass->add($_POST);
   $hashClass->redirectToList();

} else if (isset($_POST["upgrade"])) {
   if ($_POST["hash"]) {
      include_once (GLPI_ROOT."/plugins/accounts/hook.php");
      $_SESSION['plugin_accounts']['aescrypted_key']=$_POST["aeskey"];
      $hashClass->update($_POST);

      plugin_accounts_configure15();
      $_SESSION['plugin_accounts']['upgrade']=array();
      Html::redirect("./account.upgrade.php");
   } else {
      Html::back();
   }

} else if (isset($_POST["update"]) && $_POST["hash"]) {
    
   $hashClass->check($_POST['id'],UPDATE);
   $hashClass->update($_POST);
   Html::back();

} else if (isset($_POST["delete"])) {
    
   $hashClass->check($_POST['id'],DELETE);
   $hashClass->delete($_POST);
   Html::back();

} else if (isset($_POST['updatehash']) ) {

   if (isset($_POST["aeskeynew"]) && isset($_POST["aeskey"])){

      require_once(GLPI_ROOT .'/plugins/accounts/inc/aes.function.php');

      $hash = 0;
      $restrict = "`entities_id` = '".$_SESSION['glpiactive_entity']."'";
      $hashes = getAllDatasFromTable("glpi_plugin_accounts_hashes",$restrict);
      if (!empty($hashes)) {
         foreach ($hashes as $hashe) {
            $hash_id = $hashe["id"];
            $hash = $hashe["hash"];
         }
      }
       
      if (!empty ($_POST["aeskeynew"]) && !empty($_POST["aeskey"]) && !empty($hash)) {
         if ($hash <> hash ( "sha256" ,hash ( "sha256" ,$_POST["aeskey"]))) {
            Session::addMessageAfterRedirect(__('Wrong encryption key', 'accounts'),true,ERROR);
            Html::back();
         } else {
            PluginAccountsHash::updateHash($_POST["aeskey"], $_POST["aeskeynew"],$hash_id);
            Session::addMessageAfterRedirect(__('Encryption key modified', 'accounts'),true);
            Html::back();
         }
      } else {
         Session::addMessageAfterRedirect(__('The old or the new encryption key can not be empty', 'accounts'),true,ERROR);
         Html::back();
      }
   }
} else {

   if ($plugin->isActivated("environment"))
      Html::header(PluginAccountsAccount::getTypeName(2),'',"assets","pluginenvironmentdisplay","hash");
   else
      Html::header(PluginAccountsAccount::getTypeName(2),'',"admin","pluginaccountsmenu","hash");
    
   $options = array("id" => $_GET['id'], "update" => false, "upgrade" => 0);
   $hashClass->display($options);
   Html::footer();

}

?>