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

$plugin = new Plugin();
if ($plugin->isActivated("accounts")) {
    
   Session::checkRight("config", UPDATE);
   $PluginAccountsHash=new PluginAccountsHash();
   $PluginAccountsHash->getFromDB(1);
   $hash=$PluginAccountsHash->fields["hash"];

   $update=0;
   if (countElementsInTable("glpi_plugin_accounts_accounts")>0) {
      $update=1;
   }

   if (empty($hash)) {

      if ($plugin->isActivated("environment"))
         Html::header(PluginAccountsAccount::getTypeName(2),'',"assets","pluginenvironmentdisplay","accounts");
      else
         Html::header(PluginAccountsAccount::getTypeName(2),'',"assets","pluginaccountsmenu", "account");

      if ($_SESSION['glpiactive_entity'] == 0) {
         if ($update==1) {
            echo "<div class='center b'>".__('Upgrade')."</div><br><br>";
            echo "<div class='center b'>".__('1. Define the encryption key and create hash', 'accounts')."</div><br><br>";
            $options = array("update" => true, "upgrade" => 1);
            $PluginAccountsHash->showForm(1,$options);
         }
      } else {
         echo "<div class='center red'>".__('Go to Root Entity', 'accounts')."</div>";
      }
      Html::footer();

   }
} else {
   Html::header(__('Setup'),'',"config", "plugins");
   echo "<div align='center'><br><br>";
   echo "<img src=\"".$CFG_GLPI["root_doc"]."/pics/warning.png\" alt=\"warning\"><br><br>";
   echo "<b>__('Please activate the plugin', 'accounts')</b></div>";
   Html::footer();
}

?>