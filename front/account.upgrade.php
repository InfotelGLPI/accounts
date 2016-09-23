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

Session::checkRight("config","w");

$plugin=new plugin;

if ($plugin->isActivated("environment"))
   Html::header(PluginAccountsAccount::getTypeName(2),'',"assets","pluginenvironmentdisplay","accounts");
else
   Html::header(PluginAccountsAccount::getTypeName(2),'', "admin","pluginaccountsmenu", "account");

$PluginAccountsHash=new PluginAccountsHash();
$account=new PluginAccountsAccount();

if (isset($_POST["update_encrypted_password"]) && isset($_POST["item"])) {

   foreach ($_POST["upgrade_accounts"] as $key => $val) {
      foreach ($_POST["item"] as $key2 => $val2) {
         $encrypted_password = "encrypted_password$".$key2;
         $_POST[$encrypted_password]=addslashes($_POST[$encrypted_password]);
         if (isset($_POST[$encrypted_password]) && $_POST[$encrypted_password]) {
            $query="UPDATE `glpi_plugin_accounts_accounts`
                     SET `encrypted_password` ='".$_POST[$encrypted_password]."'
                              WHERE `id` ='".$key2."' ;";
            $result=$DB->query($query);
            $_SESSION['plugin_accounts']['upgrade'][]=$key2;
         }
      }
   }

   Html::back();

} else {

   $PluginAccountsHash->getFromDB(1);
   $hash=$PluginAccountsHash->fields["hash"];
   $account->showAccountsUpgrade($hash);
}

Html::footer();

?>