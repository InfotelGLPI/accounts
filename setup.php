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

global $CFG_GLPI;

use Glpi\Plugin\Hooks;

define('PLUGIN_ACCOUNTS_VERSION', '3.0.4');

if (!defined("PLUGIN_ACCOUNTS_DIR")) {
    define("PLUGIN_ACCOUNTS_DIR", Plugin::getPhpDir("accounts"));
    define("PLUGIN_ACCOUNTS_DIR_NOFULL", Plugin::getPhpDir("accounts", false));
    $root = $CFG_GLPI['root_doc'] . '/plugins/accounts';
    define("PLUGIN_ACCOUNTS_WEBDIR", $root);
}

// Init the hooks of the plugins -Needed
function plugin_init_accounts()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['accounts']   = true;
    $PLUGIN_HOOKS['assign_to_ticket']['accounts'] = true;
    $PLUGIN_HOOKS['change_profile']['accounts']   = ['PluginAccountsProfile', 'initProfile'];

    if (Session::getLoginUserID() && Plugin::isPluginActive('accounts')) {
        // Params : plugin name - string type - number - attributes
        Plugin::registerClass(
            'PluginAccountsAccount',
            ['assignable_types'         => true,
             'document_types'              => true,
             'ticket_types'                => true,
             'helpdesk_visible_types'      => true,
             'notificationtemplates_types' => true,
            ]
        );

        $CFG_GLPI['impact_asset_types']['PluginAccountsAccount'] = PLUGIN_ACCOUNTS_DIR_NOFULL . "/accounts.png";

        Plugin::registerClass(
            'PluginAccountsConfig',
            ['addtabon' => 'CronTask']
        );

        Plugin::registerClass(
            'PluginAccountsProfile',
            ['addtabon' => 'Profile']
        );

        PluginAccountsAccount::registerType('Appliance');
        PluginAccountsAccount::registerType('DatabaseInstance');

        if (Session::haveRight("plugin_accounts", READ)) {
            $PLUGIN_HOOKS["menu_toadd"]['accounts'] = ['admin' => 'PluginAccountsAccount'];
        }

        if (Session::haveRight("plugin_accounts", READ)
            && !Plugin::isPluginActive('servicecatalog')
        ) {
            $PLUGIN_HOOKS['helpdesk_menu_entry']['accounts']      = PLUGIN_ACCOUNTS_DIR_NOFULL . '/front/account.php';
            $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['accounts'] = PluginAccountsAccount::getIcon();
        }

        if (Plugin::isPluginActive('servicecatalog')) {
            $PLUGIN_HOOKS['servicecatalog']['accounts'] = ['PluginAccountsServicecatalog'];
        }

        if (Plugin::isPluginActive('fields')
            && Session::haveRight("plugin_accounts", READ)
        ) {
            $PLUGIN_HOOKS['plugin_fields']['accounts'] = 'PluginAccountsAccount';
        }

        if (Session::haveRight("plugin_accounts", UPDATE)) {
            $PLUGIN_HOOKS['use_massive_action']['accounts'] = 1;
        }

        $PLUGIN_HOOKS['redirect_page']['accounts'] = PLUGIN_ACCOUNTS_DIR_NOFULL . '/front/account.form.php';

      //Clean Plugin on Profile delete
        if (class_exists('PluginAccountsAccount_Item')) { // only if plugin activated
            $PLUGIN_HOOKS['plugin_datainjection_populate']['accounts']
               = 'plugin_datainjection_populate_accounts';
        }

        // Add specific files to add to the header : javascript or css
        $PLUGIN_HOOKS[Hooks::ADD_CSS]['accounts'] = ['accounts.css'];
         $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['accounts'][] = "getparameter.js";
         $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['accounts'][] = "crypt.js";
        if (strpos($_SERVER['REQUEST_URI'], "front/account.form.php") !== false) {
             $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['accounts'][] = "account.form.js.php";
             $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['accounts'][] = "clipboard.js";
        }
         $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['accounts'][] = "lib/lightcrypt.js";

        $PLUGIN_HOOKS['migratetypes']['accounts'] = 'plugin_datainjection_migratetypes_accounts';

        // End init, when all types are registered
        $PLUGIN_HOOKS['post_init']['accounts'] = 'plugin_accounts_postinit';
    }
}

// Get the name and the version of the plugin - Needed
/**
 * @return array
 */
function plugin_version_accounts()
{
    return [
       'name'         => _n('Account', 'Accounts', 2, 'accounts'),
       'version'      => PLUGIN_ACCOUNTS_VERSION,
       'oldname'      => 'compte',
       'license'      => 'GPLv2+',
       'author'       => "<a href='https://blogglpi.infotel.com'>Infotel</a>, Franck Waechter",
       'homepage'     => 'https://github.com/InfotelGLPI/accounts',
       'requirements' => [
          'glpi' => [
             'min' => '11.0',
             'max' => '12.0',
             'dev' => false
          ]
       ],
    ];
}

/**
 * @param $types
 *
 * @return mixed
 */
function plugin_datainjection_migratetypes_accounts($types)
{
    $types[1900] = 'PluginAccountsAccount';
    return $types;
}
