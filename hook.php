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

use Glpi\Search\SearchOption;
use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\Account_Item;
use GlpiPlugin\Accounts\AccountInjection;
use GlpiPlugin\Accounts\AccountState;
use GlpiPlugin\Accounts\AccountType;
use GlpiPlugin\Accounts\Profile;

/**
 * @return bool
 */
function plugin_accounts_install()
{
    global $DB;

    $install   = false;
    $update78  = false;
    $update171 = false;
    $update85 = false;
    if (!$DB->tableExists("glpi_plugin_compte")
        && !$DB->tableExists("glpi_plugin_comptes")
        && !$DB->tableExists("glpi_comptes")
        && !$DB->tableExists("glpi_plugin_accounts_accounts")) {
        $install = true;
        $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/empty-3.1.0.sql");
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
        $update85 = true;
    }

    $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-3.1.0.sql");

    $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-3.1.3.sql");

    $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-3.1.4.sql");

    $DB->runFile(PLUGIN_ACCOUNTS_DIR . "/sql/update-3.1.6.sql");
    //DisplayPreferences Migration
    $classes = ['PluginAccountsAccount' => Account::class];

    foreach ($classes as $old => $new) {
        $displayusers = $DB->request([
            'SELECT' => [
                'users_id'
            ],
            'DISTINCT' => true,
            'FROM' => 'glpi_displaypreferences',
            'WHERE' => [
                'itemtype' => $old,
            ],
        ]);

        if (count($displayusers) > 0) {
            foreach ($displayusers as $displayuser) {
                $iterator = $DB->request([
                    'SELECT' => [
                        'num',
                        'id'
                    ],
                    'FROM' => 'glpi_displaypreferences',
                    'WHERE' => [
                        'itemtype' => $old,
                        'users_id' => $displayuser['users_id'],
                        'interface' => 'central'
                    ],
                ]);

                if (count($iterator) > 0) {
                    foreach ($iterator as $data) {
                        $iterator2 = $DB->request([
                            'SELECT' => [
                                'id'
                            ],
                            'FROM' => 'glpi_displaypreferences',
                            'WHERE' => [
                                'itemtype' => $new,
                                'users_id' => $displayuser['users_id'],
                                'num' => $data['num'],
                                'interface' => 'central'
                            ],
                        ]);
                        if (count($iterator2) > 0) {
                            foreach ($iterator2 as $dataid) {
                                $query = $DB->buildDelete(
                                    'glpi_displaypreferences',
                                    [
                                        'id' => $dataid['id'],
                                    ]
                                );
                                $DB->doQuery($query);
                            }
                        } else {
                            $query = $DB->buildUpdate(
                                'glpi_displaypreferences',
                                [
                                    'itemtype' => $new,
                                ],
                                [
                                    'id' => $data['id'],
                                ]
                            );
                            $DB->doQuery($query);
                        }
                    }
                }
            }
        }
    }

    if ($install || $update78) {
        install_notifications_accounts();
    }
    if ($update78) {
        //Do One time on 0.78
        $iterator = $DB->request([
            'SELECT' => [
                'id',
            ],
            'FROM' => 'glpi_plugin_accounts_profiles',
        ]);
        if (count($iterator) > 0) {
            foreach ($iterator as $data) {
                $query = "UPDATE `glpi_plugin_accounts_profiles`
                     SET `profiles_id` = '" . $data["id"] . "'
                              WHERE `id` = '" . $data["id"] . "';";
                $DB->doQuery($query);
            }
        }

        $query = "ALTER TABLE `glpi_plugin_accounts_profiles`
               DROP `name` ;";
        $DB->doQuery($query);
    }

    if ($update171) {
        $query = "UPDATE `glpi_plugin_accounts_hashes`
               SET `is_recursive` = '1'
               WHERE `id` = '1';";
        $DB->doQuery($query);

        $query = "UPDATE `glpi_plugin_accounts_aeskeys`
               SET `plugin_accounts_hashes_id` = '1'
               WHERE `id` = '1';";
        $DB->doQuery($query);
    }
    if ($update85) {
        $notepad_tables = ['glpi_plugin_accounts_accounts'];
        $dbu = new DbUtils();

        foreach ($notepad_tables as $t) {
            // Migrate data
            $iterator = $DB->request([
                'SELECT' => [
                    'notepad',
                    'id',
                ],
                'FROM' => $t,
                'WHERE' => [
                    'NOT' => ['notepad' => null],
                    'notepad' => ['<>', ''],
                ],
            ]);
            if (count($iterator) > 0) {
                foreach ($iterator as $data) {
                    $iq = "INSERT INTO `glpi_notepads`
                          (`itemtype`, `items_id`, `content`, `date`, `date_mod`)
                   VALUES ('" . $dbu->getItemTypeForTable($t) . "', '" . $data['id'] . "',
                           '" . addslashes($data['notepad']) . "', NOW(), NOW())";
                    $DB->doQuery($iq, "0.85 migrate notepad data");
                }
            }
            $query = "ALTER TABLE `glpi_plugin_accounts_accounts` DROP COLUMN `notepad`;";
            $DB->doQuery($query);
        }
    }
    CronTask::Register(Account::class, 'AccountsAlert', DAY_TIMESTAMP);

    Profile::initProfile();
    Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
    $migration = new Migration("2.2.0");
    $migration->dropTable('glpi_plugin_accounts_profiles');
    return true;
}


function install_notifications_accounts()
{

    global $DB;

    $migration = new Migration(1.0);

    // Notification
    // Request
    $options_notif        = ['itemtype' => Account::class,
        'name' => 'New Accounts'];
    $DB->insert(
        "glpi_notificationtemplates",
        $options_notif
    );

    foreach ($DB->request([
        'FROM' => 'glpi_notificationtemplates',
        'WHERE' => $options_notif]) as $data) {
        $templates_id = $data['id'];

        if ($templates_id) {

            $DB->insert(
                "glpi_notificationtemplatetranslations",
                [
                    'notificationtemplates_id' => $templates_id,
                    'subject' => '##lang.account.title##',
                    'content_text' => '##lang.account.url## : ##account.url##\r\n\r\n
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
                    'content_html' => '&lt;p&gt;&lt;strong&gt;##lang.account.url##&lt;/strong&gt; : &lt;a href=\"##account.url##\"&gt;##account.url##&lt;/a&gt;&lt;/p&gt;
                        &lt;p&gt;&lt;strong&gt;##lang.account.entity##&lt;/strong&gt; : ##account.entity##&lt;br /&gt; ##IFaccount.name##&lt;strong&gt;##lang.account.name##&lt;/strong&gt; : ##account.name##&lt;br /&gt;##ENDIFaccount.name##  ##IFaccount.type##&lt;strong&gt;##lang.account.type##&lt;/strong&gt; : ##account.type##&lt;br /&gt;##ENDIFaccount.type##  ##IFaccount.state##&lt;strong&gt;##lang.account.state##&lt;/strong&gt; : ##account.state##&lt;br /&gt;##ENDIFaccount.state##  ##IFaccount.login##&lt;strong&gt;##lang.account.login##&lt;/strong&gt; : ##account.login##&lt;br /&gt;##ENDIFaccount.login##  ##IFaccount.users##&lt;strong&gt;##lang.account.users##&lt;/strong&gt; : ##account.users##&lt;br /&gt;##ENDIFaccount.users##  ##IFaccount.groups##&lt;strong&gt;##lang.account.groups##&lt;/strong&gt; : ##account.groups##&lt;br /&gt;##ENDIFaccount.groups##  ##IFaccount.others##&lt;strong&gt;##lang.account.others##&lt;/strong&gt; : ##account.others##&lt;br /&gt;##ENDIFaccount.others##  ##IFaccount.datecreation##&lt;strong&gt;##lang.account.datecreation##&lt;/strong&gt; : ##account.datecreation##&lt;br /&gt;##ENDIFaccount.datecreation##  ##IFaccount.dateexpiration##&lt;strong&gt;##lang.account.dateexpiration##&lt;/strong&gt; : ##account.dateexpiration##&lt;br /&gt;##ENDIFaccount.dateexpiration##  ##IFaccount.comment##&lt;strong&gt;##lang.account.comment##&lt;/strong&gt; : ##account.comment####ENDIFaccount.comment##&lt;/p&gt;',
                ]
            );

            $DB->insert(
                "glpi_notifications",
                [
                    'name' => 'New Accounts',
                    'entities_id' => 0,
                    'itemtype' => Account::class,
                    'event' => 'new',
                    'is_recursive' => 1,
                ]
            );

            $options_notif        = ['itemtype' => Account::class,
                'name' => 'New Accounts',
                'event' => 'new'];

            foreach ($DB->request([
                'FROM' => 'glpi_notifications',
                'WHERE' => $options_notif]) as $data_notif) {
                $notification = $data_notif['id'];
                if ($notification) {
                    $DB->insert(
                        "glpi_notifications_notificationtemplates",
                        [
                            'notifications_id' => $notification,
                            'mode' => 'mailing',
                            'notificationtemplates_id' => $templates_id,
                        ]
                    );
                }
            }
        }
    }

    // Alert Expired
    $options_notif        = ['itemtype' => Account::class,
        'name' => 'Alert Accounts'];
    // Request
    $DB->insert(
        "glpi_notificationtemplates",
        $options_notif
    );

    foreach ($DB->request([
        'FROM' => 'glpi_notificationtemplates',
        'WHERE' => $options_notif]) as $data) {
        $templates_id = $data['id'];

        if ($templates_id) {

            $DB->insert(
                "glpi_notificationtemplatetranslations",
                [
                    'notificationtemplates_id' => $templates_id,
                    'subject' => '##account.action## : ##account.entity##',
                    'content_text' => '##lang.account.entity## :##account.entity##
                        ##FOREACHaccounts##
                        ##lang.account.name## : ##account.name## - ##lang.account.dateexpiration## : ##account.dateexpiration##
                        ##ENDFOREACHaccounts##',
                    'content_html' => '&lt;p&gt;##lang.account.entity## :##account.entity##&lt;br /&gt; &lt;br /&gt;
                        ##FOREACHaccounts##&lt;br /&gt;
                        ##lang.account.name##  : ##account.name## - ##lang.account.dateexpiration## :  ##account.dateexpiration##&lt;br /&gt;
                        ##ENDFOREACHaccounts##&lt;/p&gt;',
                ]
            );

            $DB->insert(
                "glpi_notifications",
                [
                    'name' => 'Alert Expired Accounts',
                    'entities_id' => 0,
                    'itemtype' => Account::class,
                    'event' => 'ExpiredAccounts',
                    'is_recursive' => 1,
                ]
            );

            $options_notif        = ['itemtype' => Account::class,
                'name' => 'Alert Expired Accounts',
                'event' => 'ExpiredAccounts'];

            foreach ($DB->request([
                'FROM' => 'glpi_notifications',
                'WHERE' => $options_notif]) as $data_notif) {
                $notification = $data_notif['id'];
                if ($notification) {
                    $DB->insert(
                        "glpi_notifications_notificationtemplates",
                        [
                            'notifications_id' => $notification,
                            'mode' => 'mailing',
                            'notificationtemplates_id' => $templates_id,
                        ]
                    );
                }
            }

            $DB->insert(
                "glpi_notifications",
                [
                    'name' => 'Alert Accounts Which Expire',
                    'entities_id' => 0,
                    'itemtype' => Account::class,
                    'event' => 'AccountsWhichExpire',
                    'is_recursive' => 1,
                ]
            );

            $options_notif        = ['itemtype' => Account::class,
                'name' => 'Alert Accounts Which Expire',
                'event' => 'AccountsWhichExpire'];

            foreach ($DB->request([
                'FROM' => 'glpi_notifications',
                'WHERE' => $options_notif]) as $data_notif) {
                $notification = $data_notif['id'];
                if ($notification) {
                    $DB->insert(
                        "glpi_notifications_notificationtemplates",
                        [
                            'notifications_id' => $notification,
                            'mode' => 'mailing',
                            'notificationtemplates_id' => $templates_id,
                        ]
                    );
                }
            }
        }
    }

    $migration->executeMigration();
    return true;
}


/**
 * @return bool
 */
function plugin_accounts_uninstall()
{
    global $DB;

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (Profile::getAllRights() as $right) {
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
        $DB->dropTable($table, true);
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
        $DB->dropTable($table, true);
    }

    $notif          = new Notification();
    $notif_template = new Notification_NotificationTemplate();

    $options = ['itemtype' => Account::class];
    foreach ($DB->request([
        'FROM' => 'glpi_notifications',
        'WHERE' => $options]) as $data) {
        $notif->delete($data);
    }

    //templates
    $template    = new NotificationTemplate();
    $translation = new NotificationTemplateTranslation();
    $options     = ['itemtype' => Account::class];
    foreach ($DB->request([
        'FROM' => 'glpi_notificationtemplates',
        'WHERE' => $options]) as $data) {
        $options_template = [
            'notificationtemplates_id' => $data['id'],
        ];

        foreach ($DB->request([
            'FROM' => 'glpi_notificationtemplatetranslations',
            'WHERE' => $options_template]) as $data_template) {
            $translation->delete($data_template);
        }
        $template->delete($data);

        foreach ($DB->request([
            'FROM' => 'glpi_notifications_notificationtemplates',
            'WHERE' => $options_template]) as $data_template) {
            $notif_template->delete($data_template);
        }
    }

    $tables_glpi = ["glpi_displaypreferences",
        "glpi_documents_items",
        "glpi_savedsearches",
        "glpi_notepads",
        "glpi_alerts",
        "glpi_links_itemtypes",
        "glpi_items_tickets",
        "glpi_dropdowntranslations",
        "glpi_impactitems"];

    foreach ($tables_glpi as $table_glpi) {
        $DB->delete($table_glpi, ['itemtype' => Account::class]);
    }

    $DB->delete('glpi_impactrelations', [
        'OR' => [
            [
                'itemtype_source' => [Account::class],
            ],
            [
                'itemtype_impacted' => [Account::class],
            ],
        ],
    ]);

    if (class_exists('PluginDatainjectionModel')) {
        PluginDatainjectionModel::clean(['itemtype' => Account::class]);
    }

    Profile::removeRightsFromSession();

    Account::removeRightsFromSession();

    return true;
}

function plugin_accounts_postinit()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['item_purge']['accounts'] = [];

    foreach (Account::getTypes(true) as $type) {
        $PLUGIN_HOOKS['item_purge']['accounts'][$type]
           = [Account_Item::class, 'cleanForItem'];

        CommonGLPI::registerStandardTab($type, Account_Item::class);
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
        $types[Account::class] = Account::getTypeName(2);
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
                "glpi_plugin_accounts_accounts" => "plugin_accounts_accounttypes_id",
            ],
            //           "glpi_plugin_accounts_accountstates" => [
            //              "glpi_plugin_accounts_accounts"      => "plugin_accounts_accountstates_id",
            //              "glpi_plugin_accounts_notificationstates" => "plugin_accounts_accountstates_id"
            //           ],
            "glpi_plugin_accounts_accounts"      => [
                "glpi_plugin_accounts_accounts_items" => "plugin_accounts_accounts_id",
            ],
            "glpi_entities"                      => [
                "glpi_plugin_accounts_accounts"     => "entities_id",
                "glpi_plugin_accounts_accounttypes" => "entities_id",
            ],
            "glpi_users"                         => [
                "glpi_plugin_accounts_accounts" => "users_id",
                "glpi_plugin_accounts_accounts" => "users_id_tech",
            ],
            "glpi_groups"                        => [
                "glpi_plugin_accounts_accounts" => "groups_id",
                "glpi_plugin_accounts_accounts" => "groups_id_tech",
            ],
            "glpi_locations"                     => [
                "glpi_plugin_accounts_accounts" => "locations_id",
            ],
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
            AccountType::class  => AccountType::getTypeName(2),
            AccountState::class => AccountState::getTypeName(2),
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

    if (in_array($itemtype, Account::getTypes(true))) {
        if (Session::haveRight("plugin_accounts", READ)) {
            $sopt[1900]['table']         = 'glpi_plugin_accounts_accounts';
            $sopt[1900]['field']         = 'name';
            $sopt[1900]['name']          = Account::getTypeName(2) . " - " . __s('Name');
            $sopt[1900]['forcegroupby']  = true;
            $sopt[1900]['datatype']      = 'itemlink';
            $sopt[1900]['massiveaction'] = false;
            $sopt[1900]['itemlink_type'] = Account::class;
            if ($itemtype != 'User') {
                $sopt[1900]['joinparams'] = ['beforejoin' => ['table'      => 'glpi_plugin_accounts_accounts_items',
                    'joinparams' => ['jointype' => 'itemtype_item']]];
            }
            $sopt[1901]['table']         = 'glpi_plugin_accounts_accounttypes';
            $sopt[1901]['field']         = 'name';
            $sopt[1901]['name']          = Account::getTypeName(2) . " - " . __s('Type');
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

//function plugin_accounts_addLeftJoin($type, $ref_table, $new_table, $linkfield) {
//// Example of standard LEFT JOIN  clause but use it ONLY for specific LEFT JOIN
//// No need of the function if you do not have specific cases
//    switch ($new_table) {
//        case "glpi_users": // From items
//            $out['LEFT JOIN'] = [
//                'glpi_plugin_accounts_accounts' => [
//                    'ON' => [
//                        'glpi_plugin_accounts_accounts'   => 'users_id',
//                        'glpi_users'                  => 'id'
//                    ],
//                ],
//            ];
//            return $out;
//    }
//    return "";
//}

/**
 * @param $type
 *
 * @return string
 */
function plugin_accounts_addDefaultWhere($type)
{
    switch ($type) {
        case Account::class:
            $who = Session::getLoginUserID();
            if (!Session::haveRight("plugin_accounts_see_all_users", 1)) {
                if (count($_SESSION["glpigroups"])
                    && Session::haveRight("plugin_accounts_my_groups", 1)) {

                    $criteria = [
                        'OR' => [
                            ['glpi_plugin_accounts_accounts.groups_id' => $_SESSION['glpigroups']],
                            ['glpi_plugin_accounts_accounts.users_id' => $who],
                            ]
                    ];

                    return $criteria;

                } else { // Only personal ones
//                    return " `glpi_plugin_accounts_accounts`.`users_id` = '$who' ";
                    $criteria = ['glpi_plugin_accounts_accounts.users_id' => $who];

                    return $criteria;
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
        case Account::class:
            return true;
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
    $searchopt  = SearchOption::getOptionsForItemtype($type);
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

    $searchopt  = SearchOption::getOptionsForItemtype($type);
    $table     = $searchopt[$ID]["table"];
    $field     = $searchopt[$ID]["field"];

    switch ($type) {
        case Account::class:
            switch ($table . '.' . $field) {
                case "glpi_plugin_accounts_accounts_items.items_id":
                    $query_device  = "SELECT DISTINCT `itemtype`
                        FROM `glpi_plugin_accounts_accounts_items`
                        WHERE `plugin_accounts_accounts_id` = '" . $data['id'] . "'
                                 ORDER BY `itemtype`
                                 LIMIT " . count(Account::getTypes(true));
                    $result_device = $DB->doQuery($query_device);
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

                                if ($result_linked = $DB->doQuery($query)) {
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
        if (in_array($type, Account::getTypes(true))) {
            $icon = "<i class='".Account::getIcon()."'></i>";
            return [
                Account::class . MassiveAction::CLASS_ACTION_SEPARATOR . "add_item" =>
                    $icon." ".__s('Associate to account', 'accounts'),
            ];
        }
    }
    return [];
}

/*
function plugin_accounts_MassiveActionsProcess($data) {

   $account_item = new Account_Item();

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

if ($parm["item_type"]=='Report'
         && isset($parm["id"])
         && isset($parm["display_type"])) {

$accounts = Report::queryAccountsList($parm);

Report::showAccountsList($parm, $accounts);
return true;
}

// Return false if no specific display is done, then use standard display
return false;
}*/

function plugin_datainjection_populate_accounts()
{
    global $INJECTABLE_TYPES;
    $INJECTABLE_TYPES[AccountInjection::class] = 'accounts';
}
