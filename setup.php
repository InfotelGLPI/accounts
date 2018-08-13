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

// Init the hooks of the plugins -Needed
function plugin_init_accounts() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['accounts'] = true;
   $PLUGIN_HOOKS['assign_to_ticket']['accounts'] = true;
   $PLUGIN_HOOKS['change_profile']['accounts'] = ['PluginAccountsProfile', 'initProfile'];

   if (Session::getLoginUserID()) {

      // Params : plugin name - string type - number - attributes
      Plugin::registerClass('PluginAccountsAccount',
         ['linkgroup_types' => true,
            'linkuser_types' => true,
            'linkgroup_tech_types' => true,
            'linkuser_tech_types' => true,
            'document_types' => true,
            'ticket_types' => true,
            'helpdesk_visible_types' => true,
            'notificationtemplates_types' => true,
            'header_types' => true
         ]
      );

      Plugin::registerClass('PluginAccountsConfig',
         ['addtabon' => 'CronTask']);

      Plugin::registerClass('PluginAccountsProfile',
         ['addtabon' => 'Profile']);

      $plugin = new Plugin();
      if (!$plugin->isActivated('environment')
         && Session::haveRight("plugin_accounts", READ)
      ) {

         $PLUGIN_HOOKS["menu_toadd"]['accounts'] = ['admin' => 'PluginAccountsMenu'];
         $PLUGIN_HOOKS['helpdesk_menu_entry']['accounts'] = '/front/account.php';
      }
      if ($plugin->isActivated('environment')
         && Session::haveRight("plugin_accounts", READ)
      ) {
         $PLUGIN_HOOKS['helpdesk_menu_entry']['accounts'] = '/front/account.php';
      }

      if (Session::haveRight("plugin_accounts", UPDATE)) {
         $PLUGIN_HOOKS['use_massive_action']['accounts'] = 1;
      }

      $PLUGIN_HOOKS['redirect_page']['accounts'] = "front/account.form.php";

      //Clean Plugin on Profile delete
      if (class_exists('PluginAccountsAccount_Item')) { // only if plugin activated
         $PLUGIN_HOOKS['plugin_datainjection_populate']['accounts']
            = 'plugin_datainjection_populate_accounts';
      }

      // Add specific files to add to the header : javascript or css
      $PLUGIN_HOOKS['add_javascript']['accounts'][] = "scripts/getparameter.js";
      $PLUGIN_HOOKS['add_javascript']['accounts'][] = "scripts/crypt.js";
      if (strpos($_SERVER['REQUEST_URI'], "front/account.form.php") !== false) {
         $PLUGIN_HOOKS['add_javascript']['accounts'][] = "scripts/account.form.js";
      }
      $PLUGIN_HOOKS['add_javascript']['accounts'][] = "lib/lightcrypt.js";

      $PLUGIN_HOOKS['migratetypes']['accounts'] = 'plugin_datainjection_migratetypes_accounts';

      // End init, when all types are registered
      $PLUGIN_HOOKS['post_init']['accounts'] = 'plugin_accounts_postinit';

   }
}

// Get the name and the version of the plugin - Needed
/**
 * @return array
 */
function plugin_version_accounts() {

   return [
      'name' => _n('Account', 'Accounts', 2, 'accounts'),
      'version' => '2.3.3',
      'oldname' => 'compte',
      'license' => 'GPLv2+',
      'author' => "<a href='http://infotel.com/services/expertise-technique/glpi/'>Infotel</a>, Franck Waechter",
      'homepage' => 'https://github.com/InfotelGLPI/accounts',
      'minGlpiVersion' => '9.2',
   ];

}

// Optional : check prerequisites before install : may print errors or add to message after redirect
/**
 * @return bool
 */
function plugin_accounts_check_prerequisites() {
   global $DB;

   $dbu = new DbUtils();

   if (version_compare(GLPI_VERSION, '9.2', 'lt') || version_compare(GLPI_VERSION, '9.3', 'ge')) {
      echo __('This plugin requires GLPI >= 9.2', 'accounts');
      return false;
   } else {
      if ($DB->tableExists("glpi_comptes")) {//1.0
         if ($dbu->countElementsInTable("glpi_comptes") > 0 && function_exists("mcrypt_encrypt")) {
            return true;
         } else {
            echo __('phpX-mcrypt must be installed', 'accounts');
         }
      } else if ($DB->tableExists("glpi_plugin_comptes")) {//1.1
         if ($dbu->countElementsInTable("glpi_plugin_comptes") > 0 && function_exists("mcrypt_encrypt")) {
            return true;
         } else {
            echo __('phpX-mcrypt must be installed', 'accounts');
         }
      } else if (!$DB->tableExists("glpi_plugin_compte_mailing")
         && $DB->tableExists("glpi_plugin_comptes")
      ) {//1.3
         if ($dbu->countElementsInTable("glpi_plugin_comptes") > 0 && function_exists("mcrypt_encrypt")) {
            return true;
         } else {
            echo __('phpX-mcrypt must be installed', 'accounts');
         }
      } else if ($DB->tableExists("glpi_plugin_compte")
         && $DB->fieldExists("glpi_plugin_compte_profiles", "interface")
      ) {//1.4
         if ($dbu->countElementsInTable("glpi_plugin_compte") > 0 && function_exists("mcrypt_encrypt")) {
            return true;
         } else {
            echo __('phpX-mcrypt must be installed', 'accounts');
         }
      } else {
         return true;
      }
   }
}

// Uninstall process for plugin : need to return true if succeeded
//may display messages or add to message after redirect
/**
 * @return bool
 */
function plugin_accounts_check_config() {
   return true;
}

/**
 * @param $types
 * @return mixed
 */
function plugin_datainjection_migratetypes_accounts($types) {
   $types[1900] = 'PluginAccountsAccount';
   return $types;
}
