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

class PluginAccountsMenu extends CommonGLPI {
   static $rightname = 'plugin_accounts';

   static function getMenuName() {
      return _n('Account', 'Accounts', 2, 'accounts');
   }

   static function getMenuContent() {
      global $CFG_GLPI;

      $image = "<img src='".
            $CFG_GLPI["root_doc"]."/plugins/accounts/pics/cadenas.png' title='".
            _n('Encryption key', 'Encryption keys', 2, 'accounts')."' alt='".
            _n('Encryption key', 'Encryption keys', 2, 'accounts')."'>";

      $menu                                           = array();
      $menu['title']                                  = self::getMenuName();
      $menu['page']                                   = "/plugins/accounts/front/account.php";
      $menu['page']                                   = "/plugins/accounts/front/account.php";
      $menu['links']['search']                        = PluginAccountsAccount::getSearchURL(false);
      $menu['links'][$image]                          = PluginAccountsHash::getSearchURL(false);
      if (PluginAccountsAccount::canCreate()) {
         $menu['links']['add']                        = PluginAccountsAccount::getFormURL(false);
      }
      
      $menu['options']['account']['title']            = PluginAccountsAccount::getTypeName(2);
      $menu['options']['account']['page']             = PluginAccountsAccount::getSearchURL(false);
      $menu['options']['account']['links']['search']  = PluginAccountsAccount::getSearchURL(false);
      $menu['options']['account']['links'][$image] = PluginAccountsHash::getSearchURL(false);
      if (PluginAccountsAccount::canCreate()) {
         $menu['options']['account']['links']['add']  = PluginAccountsAccount::getFormURL(false);
      }

      $menu['options']['hash']['title']               = PluginAccountsHash::getTypeName(2);
      $menu['options']['hash']['page']                = PluginAccountsHash::getSearchURL(false);
      $menu['options']['hash']['links']['search']     = PluginAccountsHash::getSearchURL(false);
      $menu['options']['hash']['links'][$image]       = PluginAccountsHash::getSearchURL(false);;

      if (PluginAccountsHash::canCreate()) {
         $menu['options']['hash']['links']['add']     = PluginAccountsHash::getFormURL(false);
      }

      return $menu;
   }

   static function removeRightsFromSession() {
      if (isset($_SESSION['glpimenu']['admin']['types']['PluginAccountsMenu'])) {
         unset($_SESSION['glpimenu']['admin']['types']['PluginAccountsMenu']); 
      }
      if (isset($_SESSION['glpimenu']['admin']['content']['pluginaccountsmenu'])) {
         unset($_SESSION['glpimenu']['admin']['content']['pluginaccountsmenu']); 
      }
   }
}