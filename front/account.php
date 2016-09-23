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

$plugin = new plugin();

if ($_SESSION['glpiactiveprofile']['interface'] == 'central'){
   if ($plugin->isActivated("environment"))
      Html::header(PluginAccountsAccount::getTypeName(2),'', "assets", "pluginenvironmentdisplay", "accounts");
   else
      Html::header(PluginAccountsAccount::getTypeName(2),'', "admin","pluginaccountsmenu");
} else {
   Html::helpHeader(PluginAccountsAccount::getTypeName(2));
}

$account=new PluginAccountsAccount();
$account->checkGlobal(READ);

if ($account->canView()) {

   if (Session::haveRight("plugin_accounts_see_all_users", 1)) {
      echo "<div align='center'>";
      echo "<a onclick='add_file_modal.dialog(\"open\");' href='#modal_account_content' title='".
               __s('Type view')."'>".__('Type view', 'accounts')."</a>";
      echo "</div>";

      Ajax::createModalWindow('add_file_modal', 
                              $CFG_GLPI['root_doc']."/plugins/accounts/ajax/accounttree.php",
                              array('title'        => __('Type view', 'accounts'),
                                    'width'        => 800,
                                    'height'       => 400));
   }

   Search::show("PluginAccountsAccount");

} else {
   Html::displayRightError();
}

if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}

?>