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

/**
 * @return bool
 */
function plugin_accounts_install()
{
    global $DB, $CFG_GLPI;

    include_once(PLUGIN_ACCOUNTS_DIR . "/inc/profile.class.php");

    $install   = false;
    $update78  = false;
    $update171 = false;
    if (!$DB->tableExists("glpi_plugin_compte")
        && !$DB->tableExists("glpi_plugin_comptes")
        && !$DB->tableExists("glpi_comptes")
        && !$DB->tableExists("glpi_plugin_accounts_accounts")) {
        $install = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/empty-3.0.0.sql");
    } elseif ($DB->tableExists("glpi_comptes")
               && !$DB->fieldExists("glpi_comptes", "notes")) {
        $update78 = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.1.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.3.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.1.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.3.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.6.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
        $_SESSION['plugin_acounts_upgrading'] = 1;
    } elseif ($DB->tableExists("glpi_plugin_comptes")
               && !$DB->fieldExists("glpi_plugin_comptes", "all_users")) {
        $update78 = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.3.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.1.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.3.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.6.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
        $_SESSION['plugin_acounts_upgrading'] = 1;
    } elseif ($DB->tableExists("glpi_plugin_compte_profiles")
               && !$DB->fieldExists("glpi_plugin_compte_profiles", "my_groups")) {
        $update78 = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.1.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.3.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.6.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
        $_SESSION['plugin_acounts_upgrading'] = 1;
    } elseif ($DB->tableExists("glpi_plugin_compte_profiles")
               && $DB->fieldExists("glpi_plugin_compte_profiles", "interface")) {
        $update78 = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.1.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.3.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.6.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
        $_SESSION['plugin_acounts_upgrading'] = 1;
    } elseif ($DB->tableExists("glpi_plugin_compte")
               && !$DB->fieldExists("glpi_plugin_compte", "date_mod")) {
        $update78 = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.1.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.3.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.6.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
    } elseif ($DB->tableExists("glpi_plugin_compte")
               && !$DB->tableExists("glpi_plugin_compte_aeskey")) {
        $update78 = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.5.3.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.6.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
    } elseif ($DB->tableExists("glpi_plugin_compte")
               && !$DB->tableExists("glpi_plugin_accounts_accounts")) {
        $update78 = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.6.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
    } elseif ($DB->tableExists("glpi_plugin_accounts_accounts")
               && !$DB->fieldExists("glpi_plugin_accounts_accounts", "locations_id")) {
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.0.sql");
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
    } elseif ($DB->tableExists("glpi_plugin_accounts_hashes")
               && !$DB->fieldExists("glpi_plugin_accounts_hashes", "entities_id")) {
        $update171 = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.7.1.sql");
    }

    //from 1.6 version
    if ($DB->tableExists("glpi_plugin_accounts_accounts")
        && !$DB->fieldExists("glpi_plugin_accounts_accounts", "users_id_tech")) {
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.8.0.sql");
    }

    //from 1.9 version
    if ($DB->tableExists("glpi_plugin_accounts_accounttypes")
        && !$DB->fieldExists("glpi_plugin_accounts_accounttypes", "is_recursive")) {
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-1.9.0.sql");
    }

    if ($install || $update78) {
        /***** Begin Notif New account *****/
        //Do One time on 0.78
        $query_id = "SELECT `id` FROM `glpi_notificationtemplates`
               WHERE `itemtype`='PluginAccountsAccount'
               AND `name` = 'New Accounts'";
        $result = $DB->query($query_id) or die($DB->error());
        $itemtype = $DB->result($result, 0, 'id');

        $query = "INSERT INTO `glpi_notificationtemplatetranslations`
               VALUES(NULL, " . $itemtype . ", '','##lang.account.title##',
                        '##lang.account.url## : ##account.url##\r\n\r\n
                        ##lang.account.entity## : ##account.entity##\r\n
                        ##IFaccount.name####lang.account.name## : ##account.name##\r\n##ENDIFaccount.name##
                        ##IFaccount.type####lang.account.type## : ##account.type##\r\n##ENDIFaccount.type##
                        ##IFaccount.state####lang.account.state## : ##account.state##\r\n##ENDIFaccount.state##
                        ##IFaccount.login####lang.account.login## : ##account.login##\r\n##ENDIFaccount.login##
                        ##IFaccount.users_id####lang.account.users_id## : ##account.users_id##\r\n##ENDIFaccount.users_id##
                        ##IFaccount.groups_id####lang.account.groups_id## : ##account.groups_id##\r\n##ENDIFaccount.groups_id##
                        ##IFaccount.others####lang.account.others## : ##account.others##\r\n##ENDIFaccount.others##
                        ##IFaccount.datecreation####lang.account.datecreation## : ##account.datecreation##\r\n##ENDIFaccount.datecreation##
                        ##IFaccount.dateexpiration####lang.account.dateexpiration## : ##account.dateexpiration##\r\n##ENDIFaccount.dateexpiration##
                        ##IFaccount.comment####lang.account.comment## : ##account.comment##\r\n##ENDIFaccount.comment##',
                        '&lt;p&gt;&lt;strong&gt;##lang.account.url##&lt;/strong&gt; : &lt;a href=\"##account.url##\"&gt;##account.url##&lt;/a&gt;&lt;/p&gt;
                        &lt;p&gt;&lt;strong&gt;##lang.account.entity##&lt;/strong&gt; : ##account.entity##&lt;br /&gt; ##IFaccount.name##&lt;strong&gt;##lang.account.name##&lt;/strong&gt; : ##account.name##&lt;br /&gt;##ENDIFaccount.name##  ##IFaccount.type##&lt;strong&gt;##lang.account.type##&lt;/strong&gt; : ##account.type##&lt;br /&gt;##ENDIFaccount.type##  ##IFaccount.state##&lt;strong&gt;##lang.account.state##&lt;/strong&gt; : ##account.state##&lt;br /&gt;##ENDIFaccount.state##  ##IFaccount.login##&lt;strong&gt;##lang.account.login##&lt;/strong&gt; : ##account.login##&lt;br /&gt;##ENDIFaccount.login##  ##IFaccount.users##&lt;strong&gt;##lang.account.users##&lt;/strong&gt; : ##account.users##&lt;br /&gt;##ENDIFaccount.users##  ##IFaccount.groups##&lt;strong&gt;##lang.account.groups##&lt;/strong&gt; : ##account.groups##&lt;br /&gt;##ENDIFaccount.groups##  ##IFaccount.others##&lt;strong&gt;##lang.account.others##&lt;/strong&gt; : ##account.others##&lt;br /&gt;##ENDIFaccount.others##  ##IFaccount.datecreation##&lt;strong&gt;##lang.account.datecreation##&lt;/strong&gt; : ##account.datecreation##&lt;br /&gt;##ENDIFaccount.datecreation##  ##IFaccount.dateexpiration##&lt;strong&gt;##lang.account.dateexpiration##&lt;/strong&gt; : ##account.dateexpiration##&lt;br /&gt;##ENDIFaccount.dateexpiration##  ##IFaccount.comment##&lt;strong&gt;##lang.account.comment##&lt;/strong&gt; : ##account.comment####ENDIFaccount.comment##&lt;/p&gt;');";
        $DB->query($query);

        $query = "INSERT INTO `glpi_notifications` 
                (`name`, `entities_id`, `itemtype`, `event`, `is_recursive`, `is_active`) 
               VALUES ('New Accounts', 0, 'PluginAccountsAccount', 'new', 1, 1);";
        $DB->query($query);

        $query_id = "SELECT `id` FROM `glpi_notifications`
               WHERE `name` = 'New Accounts' AND `itemtype` = 'PluginAccountsAccount' AND `event` = 'new'";
        $result = $DB->query($query_id) or die($DB->error());
        $notification = $DB->result($result, 0, 'id');

        $query = "INSERT INTO `glpi_notifications_notificationtemplates` (`notifications_id`, `mode`, `notificationtemplates_id`) 
               VALUES (" . $notification . ", 'mailing', " . $itemtype . ");";
        $DB->query($query);

        /***** End Notif New account *****/

        /***** Begin Notif Alert Expired *****/
        $query_id = "SELECT `id` FROM `glpi_notificationtemplates`
               WHERE `itemtype`='PluginAccountsAccount'
               AND `name` = 'Alert Accounts'";
        $result = $DB->query($query_id) or die($DB->error());
        $itemtype = $DB->result($result, 0, 'id');

        $query = "INSERT INTO `glpi_notificationtemplatetranslations`
               VALUES(NULL, " . $itemtype . ", '','##account.action## : ##account.entity##',
                        '##lang.account.entity## :##account.entity##
                        ##FOREACHaccounts##
                        ##lang.account.name## : ##account.name## - ##lang.account.dateexpiration## : ##account.dateexpiration##
                        ##ENDFOREACHaccounts##',
                        '&lt;p&gt;##lang.account.entity## :##account.entity##&lt;br /&gt; &lt;br /&gt;
                        ##FOREACHaccounts##&lt;br /&gt;
                        ##lang.account.name##  : ##account.name## - ##lang.account.dateexpiration## :  ##account.dateexpiration##&lt;br /&gt;
                        ##ENDFOREACHaccounts##&lt;/p&gt;');";
        $DB->query($query);

        $query = "INSERT INTO `glpi_notifications` 
              (`name`, `entities_id`, `itemtype`, `event`, `is_recursive`, `is_active`) 
               VALUES ('Alert Expired Accounts', 0, 'PluginAccountsAccount', 'ExpiredAccounts', 1, 1);";
        $DB->query($query);

        $query_id = "SELECT `id` FROM `glpi_notifications`
               WHERE `name` = 'Alert Expired Accounts' AND `itemtype` = 'PluginAccountsAccount' AND `event` = 'ExpiredAccounts'";
        $result = $DB->query($query_id) or die($DB->error());
        $notification = $DB->result($result, 0, 'id');

        $query = "INSERT INTO `glpi_notifications_notificationtemplates` (`notifications_id`, `mode`, `notificationtemplates_id`) 
               VALUES (" . $notification . ", 'mailing', " . $itemtype . ");";
        $DB->query($query);

        $query = "INSERT INTO `glpi_notifications`
                (`name`, `entities_id`, `itemtype`, `event`, `is_recursive`, `is_active`) 
               VALUES ('Alert Accounts Which Expire', 0, 'PluginAccountsAccount', 'AccountsWhichExpire', 1, 1);";
        $DB->query($query);

        $query_id = "SELECT `id` FROM `glpi_notifications`
               WHERE `name` = 'Alert Accounts Which Expire' AND `itemtype` = 'PluginAccountsAccount' AND `event` = 'AccountsWhichExpire'";
        $result = $DB->query($query_id) or die($DB->error());
        $notification = $DB->result($result, 0, 'id');

        $query = "INSERT INTO `glpi_notifications_notificationtemplates` (`notifications_id`, `mode`, `notificationtemplates_id`) 
               VALUES (" . $notification . ", 'mailing', " . $itemtype . ");";
        $DB->query($query);

        /***** End Notif Alert Expired *****/
    }
    if ($update78) {
        //Do One time on 0.78
        $query_  = "SELECT *
               FROM `glpi_plugin_accounts_profiles` ";
        $result_ = $DB->query($query_);
        if ($DB->numrows($result_) > 0) {
            while ($data = $DB->fetchArray($result_)) {
                $query = "UPDATE `glpi_plugin_accounts_profiles`
                     SET `profiles_id` = '" . $data["id"] . "'
                              WHERE `id` = '" . $data["id"] . "';";
                $DB->query($query);
            }
        }

        $query = "ALTER TABLE `glpi_plugin_accounts_profiles`
               DROP `name` ;";
        $DB->query($query);

        Plugin::migrateItemType(
            [1900 => 'PluginAccountsAccount',
             1901 => 'PluginAccountsHelpdesk',
             1902 => 'PluginAccountsGroup'],
            ["glpi_savedsearches", "glpi_savedsearches_users", "glpi_displaypreferences",
             "glpi_documents_items", "glpi_infocoms", "glpi_logs", "glpi_items_tickets"],
            ["glpi_plugin_accounts_accounts_items"]
        );

        Plugin::migrateItemType(
            [1200 => "PluginAppliancesAppliance",
             1300 => "PluginWebapplicationsWebapplication",
             1700 => "PluginCertificatesCertificate",
             4400 => "PluginDomainsDomain",
             2400 => "PluginDatabasesDatabase"],
            ["glpi_plugin_accounts_accounts_items"]
        );
    }

    if ($update171) {
        $query = "UPDATE `glpi_plugin_accounts_hashes`
               SET `is_recursive` = '1'
               WHERE `id` = '1';";
        $DB->query($query);

        $query = "UPDATE `glpi_plugin_accounts_aeskeys`
               SET `plugin_accounts_hashes_id` = '1'
               WHERE `id` = '1';";
        $DB->query($query);
    }

    $notepad_tables = ['glpi_plugin_accounts_accounts'];
    $dbu            = new DbUtils();

    foreach ($notepad_tables as $t) {
        // Migrate data
        if ($DB->fieldExists($t, 'notepad')) {
            $query = "SELECT id, notepad
                   FROM `$t`
                   WHERE notepad IS NOT NULL
                         AND notepad <>'';";
            foreach ($DB->request($query) as $data) {
                $iq = "INSERT INTO `glpi_notepads`
                          (`itemtype`, `items_id`, `content`, `date`, `date_mod`)
                   VALUES ('" . $dbu->getItemTypeForTable($t) . "', '" . $data['id'] . "',
                           '" . addslashes($data['notepad']) . "', NOW(), NOW())";
                $DB->queryOrDie($iq, "0.85 migrate notepad data");
            }
            $query = "ALTER TABLE `glpi_plugin_accounts_accounts` DROP COLUMN `notepad`;";
            $DB->query($query);
        }
    }

    CronTask::Register('PluginAccountsAccount', 'AccountsAlert', DAY_TIMESTAMP);

    PluginAccountsProfile::initProfile();
    PluginAccountsProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
    $migration = new Migration("2.2.0");
    $migration->dropTable('glpi_plugin_accounts_profiles');
    return true;
}

/**
 * @return bool
 */
function plugin_accounts_uninstall()
{
    global $DB;

    include_once(PLUGIN_ACCOUNTS_DIR . "/inc/profile.class.php");

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (PluginAccountsProfile::getAllRights(true) as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }

    $tables = ["glpi_plugin_accounts_accounts",
               "glpi_plugin_accounts_accounts_items",
               "glpi_plugin_accounts_accounttypes",
               "glpi_plugin_accounts_accountstates",
               "glpi_plugin_accounts_configs",
               "glpi_plugin_accounts_hashs",
               "glpi_plugin_accounts_hashes",
               "glpi_plugin_accounts_aeskeys",
               "glpi_plugin_accounts_notificationstates"];

    foreach ($tables as $table) {
        $DB->query("DROP TABLE IF EXISTS `$table`;");
    }

    //old versions
    $tables = ["glpi_plugin_comptes",
               "glpi_plugin_compte_device",
               "glpi_dropdown_plugin_compte_type",
               "glpi_dropdown_plugin_compte_status",
               "glpi_plugin_compte_profiles",
               "glpi_plugin_compte_config",
               "glpi_plugin_compte_default",
               "glpi_plugin_compte_mailing",
               "glpi_plugin_compte",
               "glpi_plugin_compte_hash",
               "glpi_plugin_compte_aeskey",
               "glpi_plugin_accounts_profiles"];

    foreach ($tables as $table) {
        $DB->query("DROP TABLE IF EXISTS `$table`;");
    }

    $notif          = new Notification();
    $notif_template = new Notification_NotificationTemplate();

    $options = ['itemtype' => 'PluginAccountsAccount',
                'event'    => 'new',
                'FIELDS'   => 'id'];
    foreach ($DB->request('glpi_notifications', $options) as $data) {
        $notif->delete($data);
    }
    $options = ['itemtype' => 'PluginAccountsAccount',
                'event'    => 'ExpiredAccounts',
                'FIELDS'   => 'id'];
    foreach ($DB->request('glpi_notifications', $options) as $data) {
        $notif->delete($data);
    }
    $options = ['itemtype' => 'PluginAccountsAccount',
                'event'    => 'AccountsWhichExpire',
                'FIELDS'   => 'id'];
    foreach ($DB->request('glpi_notifications', $options) as $data) {
        $notif->delete($data);
    }

    //templates
    $template    = new NotificationTemplate();
    $translation = new NotificationTemplateTranslation();
    $options     = ['itemtype' => 'PluginAccountsAccount',
                    'FIELDS'   => 'id'];
    foreach ($DB->request('glpi_notificationtemplates', $options) as $data) {
        $options_template = ['notificationtemplates_id' => $data['id'],
                             'FIELDS'                   => 'id'];

        foreach ($DB->request('glpi_notificationtemplatetranslations', $options_template)
                 as $data_template) {
            $translation->delete($data_template);
        }
        $template->delete($data);

        foreach ($DB->request('glpi_notifications_notificationtemplates', $options_template) as $data_template) {
            $notif_template->delete($data_template);
        }
    }

    $tables_glpi = ["glpi_displaypreferences",
                    "glpi_documents_items",
                    "glpi_savedsearches",
                    "glpi_logs",
                    "glpi_items_tickets",
                    "glpi_dropdowntranslations",
                    "glpi_impactitems"];

    foreach ($tables_glpi as $table_glpi) {
        $DB->query("DELETE FROM `$table_glpi`
               WHERE `itemtype` = 'PluginAccountsAccount'
               OR `itemtype` = 'PluginAccountsHelpdesk'
               OR `itemtype` = 'PluginAccountsGroup'
               OR `itemtype` = 'PluginAccountsAccountState'
               OR `itemtype` = 'PluginAccountsAccountType' ;");
    }

    $DB->query("DELETE
                  FROM `glpi_impactrelations`
                  WHERE `itemtype_source` IN ('PluginAccountsAccount')
                    OR `itemtype_impacted` IN ('PluginAccountsAccount')");

    if (class_exists('PluginDatainjectionModel')) {
        PluginDatainjectionModel::clean(['itemtype' => 'PluginAccountsAccount']);
    }

    PluginAccountsProfile::removeRightsFromSession();

    PluginAccountsAccount::removeRightsFromSession();

    return true;
}

function plugin_accounts_postinit()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['item_purge']['accounts'] = [];

    foreach (PluginAccountsAccount::getTypes(true) as $type) {
        $PLUGIN_HOOKS['item_purge']['accounts'][$type]
           = ['PluginAccountsAccount_Item', 'cleanForItem'];

        CommonGLPI::registerStandardTab($type, 'PluginAccountsAccount_Item');
    }
}

/**
 * @param $types
 *
 * @return mixed
 */
function plugin_accounts_AssignToTicket($types)
{
    if (Session::haveRight("plugin_accounts_open_ticket", "1")) {
        $types['PluginAccountsAccount'] = PluginAccountsAccount::getTypeName(2);
    }

    return $types;
}

// Define dropdown relations
/**
 * @return array
 */
function plugin_accounts_getDatabaseRelations()
{
    if (Plugin::isPluginActive("accounts")) {
        return [
           "glpi_plugin_accounts_accounttypes"  => [
              "glpi_plugin_accounts_accounts" => "plugin_accounts_accounttypes_id"
           ],
           "glpi_plugin_accounts_accountstates" => [
              "glpi_plugin_accounts_accounts"      => "plugin_accounts_accountstates_id",
              "glpi_plugin_accounts_mailingstates" => "plugin_accounts_accountstates_id"
           ],
           "glpi_plugin_accounts_accounts"      => [
              "glpi_plugin_accounts_accounts_items" => "plugin_accounts_accounts_id"
           ],
           "glpi_entities"                      => [
              "glpi_plugin_accounts_accounts"     => "entities_id",
              "glpi_plugin_accounts_accounttypes" => "entities_id"
           ],
           "glpi_users"                         => [
              "glpi_plugin_accounts_accounts" => "users_id",
              "glpi_plugin_accounts_accounts" => "users_id_tech"
           ],
           "glpi_groups"                        => [
              "glpi_plugin_accounts_accounts" => "groups_id",
              "glpi_plugin_accounts_accounts" => "groups_id_tech"
           ],
           "glpi_locations"                     => [
              "glpi_plugin_accounts_accounts" => "locations_id"
           ]
        ];
    } else {
        return [];
    }
}

// Define Dropdown tables to be manage in GLPI :
/**
 * @return array
 */
function plugin_accounts_getDropdown()
{
    if (Plugin::isPluginActive("accounts")) {
        return [
           "PluginAccountsAccountType"  => PluginAccountsAccountType::getTypeName(2),
           "PluginAccountsAccountState" => PluginAccountsAccountState::getTypeName(2)
        ];
    } else {
        return [];
    }
}

/**
 * @param $itemtype
 *
 * @return array
 */
function plugin_accounts_getAddSearchOptions($itemtype)
{
    $sopt = [];

    if (in_array($itemtype, PluginAccountsAccount::getTypes(true))) {
        if (Session::haveRight("plugin_accounts", READ)) {
            $sopt[1900]['table']         = 'glpi_plugin_accounts_accounts';
            $sopt[1900]['field']         = 'name';
            $sopt[1900]['name']          = PluginAccountsAccount::getTypeName(2) . " - " . __('Name');
            $sopt[1900]['forcegroupby']  = true;
            $sopt[1900]['datatype']      = 'itemlink';
            $sopt[1900]['massiveaction'] = false;
            $sopt[1900]['itemlink_type'] = 'PluginAccountsAccount';
            if ($itemtype != 'User') {
                $sopt[1900]['joinparams'] = ['beforejoin' => ['table'      => 'glpi_plugin_accounts_accounts_items',
                                                              'joinparams' => ['jointype' => 'itemtype_item']]];
            }
            $sopt[1901]['table']         = 'glpi_plugin_accounts_accounttypes';
            $sopt[1901]['field']         = 'name';
            $sopt[1901]['name']          = PluginAccountsAccount::getTypeName(2) . " - " . __('Type');
            $sopt[1901]['forcegroupby']  = true;
            $sopt[1901]['joinparams']    = ['beforejoin' => [['table'      => 'glpi_plugin_accounts_accounts',
                                                              'joinparams' => $sopt[1900]['joinparams']]]];
            $sopt[1901]['datatype']      = 'dropdown';
            $sopt[1901]['massiveaction'] = false;
        }
    }
    return $sopt;
}

/**
 * @param $type
 * @param $ref_table
 * @param $new_table
 * @param $linkfield
 * @param $already_link_tables
 *
 * @return string
 */
function plugin_accounts_addLeftJoin($type, $ref_table, $new_table, $linkfield, &$already_link_tables)
{
    switch ($ref_table) {
        case "glpi_users": // From items
            $out = " LEFT JOIN `glpi_plugin_accounts_accounts`
                  ON (`glpi_plugin_accounts_accounts`.`users_id` = `glpi_users`.`id` ) ";
            return $out;
            break;
    }

    return "";
}

/**
 * @param $type
 *
 * @return string
 */
function plugin_accounts_addDefaultWhere($type)
{
    switch ($type) {
        case "PluginAccountsAccount":
            $who = Session::getLoginUserID();
            if (!Session::haveRight("plugin_accounts_see_all_users", 1)) {
                if (count($_SESSION["glpigroups"]) && Session::haveRight("plugin_accounts_my_groups", 1)) {
                    $first_groups = true;
                    $groups       = "";
                    foreach ($_SESSION['glpigroups'] as $val) {
                        if (!$first_groups) {
                            $groups .= ",";
                        } else {
                            $first_groups = false;
                        }
                        $groups .= "'" . $val . "'";
                    }
                    return " (`glpi_plugin_accounts_accounts`.`groups_id` IN (
               SELECT DISTINCT `groups_id`
               FROM `glpi_groups_users`
               WHERE `groups_id` IN ($groups)
               )
               OR `glpi_plugin_accounts_accounts`.`users_id` = '$who') ";
                } else { // Only personal ones
                    return " `glpi_plugin_accounts_accounts`.`users_id` = '$who' ";
                }
            }
    }
    return "";
}

/**
 * @param $type
 *
 * @return bool
 */
function plugin_accounts_forceGroupBy($type)
{
    return true;
    switch ($type) {
        case 'PluginAccountsAccount':
            return true;
            break;
        case 'PluginAccountsHelpdesk':
            return true;
            break;
    }
    return false;
}

/**
 * @param $type
 * @param $ID
 * @param $data
 * @param $num
 *
 * @return string
 */
function plugin_accounts_displayConfigItem($type, $ID, $data, $num)
{
    $searchopt =& Search::getOptions($type);
    $table     = $searchopt[$ID]["table"];
    $field     = $searchopt[$ID]["field"];

    switch ($table . '.' . $field) {
        case "glpi_plugin_accounts_accounts.date_expiration":
            if ($data[$num] <= date('Y-m-d') && !empty($data[$num])) {
                return " class=\"deleted\" ";
            }
            break;
    }
    return "";
}

/**
 * @param $type
 * @param $ID
 * @param $data
 * @param $num
 *
 * @return string
 */
function plugin_accounts_giveItem($type, $ID, $data, $num)
{
    global $DB;

    $dbu = new DbUtils();

    $searchopt =& Search::getOptions($type);
    $table     = $searchopt[$ID]["table"];
    $field     = $searchopt[$ID]["field"];

    switch ($type) {
        case 'PluginAccountsAccount':
            switch ($table . '.' . $field) {
                case "glpi_plugin_accounts_accounts_items.items_id":
                    $query_device  = "SELECT DISTINCT `itemtype`
                        FROM `glpi_plugin_accounts_accounts_items`
                        WHERE `plugin_accounts_accounts_id` = '" . $data['id'] . "'
                                 ORDER BY `itemtype`
                                 LIMIT " . count(PluginAccountsAccount::getTypes(true));
                    $result_device = $DB->query($query_device);
                    $number_device = $DB->numrows($result_device);
                    $out           = '';
                    $accounts      = $data['id'];
                    if ($number_device > 0) {
                        for ($i = 0; $i < $number_device; $i++) {
                            $column   = "name";
                            $itemtype = $DB->result($result_device, $i, "itemtype");

                            if (!class_exists($itemtype)) {
                                continue;
                            }
                            $item = new $itemtype();
                            if ($item->canView()) {
                                $table_item = $dbu->getTableForItemType($itemtype);
                                if ($itemtype != 'Entity') {
                                    $query = "SELECT `" . $table_item . "`.*,
                                    `glpi_plugin_accounts_accounts_items`.`id` AS items_id,
                                    `glpi_entities`.`id` AS entity "
                                             . " FROM `glpi_plugin_accounts_accounts_items`, `" . $table_item
                                             . "` LEFT JOIN `glpi_entities` ON (`glpi_entities`.`id` = `" . $table_item . "`.`entities_id`) "
                                             . " WHERE `" . $table_item . "`.`id` = `glpi_plugin_accounts_accounts_items`.`items_id`
                                             AND `glpi_plugin_accounts_accounts_items`.`itemtype` = '$itemtype'
                                             AND `glpi_plugin_accounts_accounts_items`.`plugin_accounts_accounts_id` = '" . $accounts . "' ";

                                    $query .= $dbu->getEntitiesRestrictRequest(" AND ", $table_item, '', '', $item->maybeRecursive());

                                    if ($item->maybeTemplate()) {
                                        $query .= " AND " . $table_item . ".is_template='0'";
                                    }
                                    $query .= " ORDER BY `glpi_entities`.`completename`, `" . $table_item . "`.`$column` ";
                                } else {
                                    $query = "SELECT `" . $table_item . "`.*,
                                    `glpi_plugin_accounts_accounts_items`.`id` AS items_id,
                                    `glpi_entities`.`id` AS entity "
                                             . " FROM `glpi_plugin_accounts_accounts_items`, `" . $table_item
                                             . "` WHERE `" . $table_item . "`.`id` = `glpi_plugin_accounts_accounts_items`.`items_id`
                                    AND `glpi_plugin_accounts_accounts_items`.`itemtype` = '$itemtype'
                                    AND `glpi_plugin_accounts_accounts_items`.`plugin_accounts_accounts_id` = '" . $accounts . "' "
                                             . $dbu->getEntitiesRestrictRequest(" AND ", $table_item, '', '', $item->maybeRecursive());

                                    if ($item->maybeTemplate()) {
                                        $query .= " AND " . $table_item . ".is_template='0'";
                                    }
                                    $query .= " ORDER BY `glpi_entities`.`completename`, `" . $table_item . "`.`$column` ";
                                }

                                if ($result_linked = $DB->query($query)) {
                                    if ($DB->numrows($result_linked)) {
                                        $item = new $itemtype();
                                        while ($data = $DB->fetchAssoc($result_linked)) {
                                            if ($item->getFromDB($data['id'])) {
                                                $out .= $item::getTypeName() . " - " . $item->getLink() . "<br>";
                                            }
                                        }
                                    } else {
                                        $out .= ' ';
                                    }
                                }
                            } else {
                                $out .= ' ';
                            }
                        }
                    }
                    return $out;
                    break;
            }
            break;
    }
    return "";
}

////// SPECIFIC MODIF MASSIVE FUNCTIONS ///////

/**
 * @param $type
 *
 * @return array
 */
function plugin_accounts_MassiveActions($type)
{
    if (Plugin::isPluginActive('accounts')) {
        if (in_array($type, PluginAccountsAccount::getTypes(true))) {
            return [
               'PluginAccountsAccount' . MassiveAction::CLASS_ACTION_SEPARATOR . "add_item" => __('Associate to account', 'accounts')
            ];
        }
    }
    return [];
}

/*
function plugin_accounts_MassiveActionsProcess($data) {

   $account_item = new PluginAccountsAccount_Item();

   $res = array('ok' => 0,
            'ko' => 0,
            'noright' => 0);

   switch ($data['action']) {

      case "plugin_accounts_add_item" :
         foreach ($data["item"] as $key => $val) {
            if ($val == 1) {
               $input = array('plugin_accounts_accounts_id' => $data['plugin_accounts_accounts_id'],
                        'items_id'      => $key,
                        'itemtype'      => $data['itemtype']);
               if ($account_item->can(-1,'w',$input)) {
                  if ($account_item->add($input)){
                     $res['ok']++;
                  } else {
                     $res['ko']++;
                  }
               } else {
                  $res['noright']++;
               }
            }
         }
         break;
   }
   return $res;
}*/

//////////////////////////////

// Do special actions for dynamic report
/*
 function plugin_accounts_dynamicReport($parm) {

if ($parm["item_type"]=='PluginAccountsReport'
         && isset($parm["id"])
         && isset($parm["display_type"])) {

$accounts = PluginAccountsReport::queryAccountsList($parm);

PluginAccountsReport::showAccountsList($parm, $accounts);
return true;
}

// Return false if no specific display is done, then use standard display
return false;
}*/

function plugin_datainjection_populate_accounts()
{
    global $INJECTABLE_TYPES;
    $INJECTABLE_TYPES['PluginAccountsAccountInjection'] = 'accounts';
}
