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

if (Session::getCurrentInterface() == 'central') {
   if (Plugin::isPluginActive("environment")) {
      Html::header(PluginAccountsAccount::getTypeName(2), '', "assets", "pluginenvironmentdisplay", "accounts");
   } else {
      Html::header(PluginAccountsAccount::getTypeName(2), '', "admin", "pluginaccountsaccount");
   }
} else {
   Html::helpHeader(PluginAccountsAccount::getTypeName(2));
}

$account = new PluginAccountsAccount();
$account->checkGlobal(READ);

if ($account->canView()) {

   if (Session::haveRight("plugin_accounts_see_all_users", 1)) {
      echo "<div align='center'>";
      echo "<a href='#' data-bs-toggle='modal' data-bs-target='#seetypemodal' class='submit btn btn-primary' title='" . __('Type view', 'accounts') . "' >";
      echo __('Type view', 'accounts');
      echo "</a>";
      echo "</div><br>";
      echo Ajax::createIframeModalWindow('seetypemodal',
                                         PLUGIN_ACCOUNTS_WEBDIR . "/ajax/accounttree.php",
                                         ['title'   => __('Type view', 'accounts'),
                                          'display'       => false,
                                           'width'         => 600,
                                           'height'        => 500]);
   }

   Search::show("PluginAccountsAccount");

} else {
   Html::displayRightError();
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
