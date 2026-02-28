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

namespace GlpiPlugin\Accounts;

use DbUtils;
use Dropdown;
use Html;
use NotificationTarget;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class NotificationTargetAccount
 */
class NotificationTargetAccount extends NotificationTarget
{

    const ACCOUNT_USER      = 1900;
    const ACCOUNT_GROUP     = 1901;
    const ACCOUNT_TECHUSER  = 1902;
    const ACCOUNT_TECHGROUP = 1903;

   /**
    * @return array
    */
    public function getEvents()
    {
        return ['new'                 => __s('New account', 'accounts'),
                   'ExpiredAccounts'     => __s('Accounts expired', 'accounts'),
                   'AccountsWhichExpire' => __s('Accounts which expires', 'accounts')];
    }

   /**
    * Get additionnals targets for Tickets
    *
    * @param string $event
    */
    public function addAdditionalTargets($event = '')
    {
        $this->addTarget(self::ACCOUNT_USER, __s('Affected User', 'accounts'));
        $this->addTarget(self::ACCOUNT_GROUP, __s('Affected Group', 'accounts'));
        $this->addTarget(self::ACCOUNT_TECHUSER, __s('Technician in charge'));
        $this->addTarget(self::ACCOUNT_TECHGROUP, __s('Group in charge'));
    }

   /**
    * @param $data
    * @param $options
    */
    public function addSpecificTargets($data, $options)
    {

       //Look for all targets whose type is Notification::ITEM_USER
        switch ($data['items_id']) {
            case self::ACCOUNT_USER:
                $this->addUserByField("users_id");
                break;
            case self::ACCOUNT_GROUP:
                $this->getGroupAddress();
                break;
            case self::ACCOUNT_TECHUSER:
                $this->addUserByField("users_id_tech");
                break;
            case self::ACCOUNT_TECHGROUP:
                $this->getGroupTechAddress();
                break;
        }
    }


    public function getGroupAddress()
    {
        global $DB;

        $group_field = "groups_id";

        if (isset($this->obj->fields[$group_field])
          && $this->obj->fields[$group_field] > 0) {
            $criteria = $this->getDistinctUserCriteria() + $this->getProfileJoinCriteria();
            $criteria['FROM'] = User::getTable();
            $criteria['LEFT JOIN'] = ['glpi_groups_users' => ['ON' => ['glpi_groups_users' => 'users_id',
                                                                    'glpi_users'        => 'id']]];
            $criteria['WHERE']['glpi_groups_users.groups_id'] = $this->obj->fields[$group_field];
            $iterator = $DB->request($criteria);

            foreach ($iterator as $data) {
               //Add the user email and language in the notified users list
                $this->addToRecipientsList($data);
            }
        }
    }


    public function getGroupTechAddress()
    {
        global $DB;

        $group_field = "groups_id_tech";

        if (isset($this->obj->fields[$group_field])
          && $this->obj->fields[$group_field] > 0) {
            $criteria = $this->getDistinctUserCriteria() + $this->getProfileJoinCriteria();
            $criteria['FROM'] = User::getTable();
            $criteria['LEFT JOIN'] = ['glpi_groups_users' => ['ON' => ['glpi_groups_users' => 'users_id',
                                                                    'glpi_users'        => 'id']]];
            $criteria['WHERE']['glpi_groups_users.groups_id'] = $this->obj->fields[$group_field];
            $iterator = $DB->request($criteria);

            foreach ($iterator as $data) {
               //Add the user email and language in the notified users list
                $this->addToRecipientsList($data);
            }
        }
    }

   /**
    * @param       $event
    * @param array $options
    */
    public function addDataForTemplate($event, $options = [])
    {
        global $CFG_GLPI;

        $dbu = new DbUtils();

        if ($event == 'new') {
            $this->data['##lang.account.title##'] = __s('An account has been created', 'accounts');

            $this->data['##lang.account.entity##'] = __s('Entity');
            $this->data['##account.entity##']      =
            Dropdown::getDropdownName(
                'glpi_entities',
                $this->obj->getField('entities_id')
            );
            $this->data['##lang.account.id##']     = __s('ID');
            $this->data['##account.id##']          = sprintf("%07d", $this->obj->getField("id"));

            $this->data['##lang.account.name##'] = __s('Name');
            $this->data['##account.name##']      = $this->obj->getField("name");

            $this->data['##lang.account.type##'] = __s('Type');
            $this->data['##account.type##']      = Dropdown::getDropdownName(
                'glpi_plugin_accounts_accounttypes',
                $this->obj->getField('plugin_accounts_accounttypes_id')
            );

            $this->data['##lang.account.state##'] = __s('Status');
            $this->data['##account.state##']      = Dropdown::getDropdownName(
                'glpi_plugin_accounts_accountstates',
                $this->obj->getField('plugin_accounts_accountstates_id')
            );

            $this->data['##lang.account.login##'] = __s('Login');
            $this->data['##account.login##']      = $this->obj->getField("login");

            $this->data['##lang.account.users##'] = __s('Affected User', 'accounts');
            $this->data['##account.users##']      = getUserName($this->obj->getField("users_id"));

            $this->data['##lang.account.groups##'] = __s('Affected Group', 'accounts');
            $this->data['##account.groups##']      = Dropdown::getDropdownName(
                'glpi_groups',
                $this->obj->getField('groups_id')
            );

            $this->data['##lang.account.userstech##'] = __s('Technician in charge');
            $this->data['##account.userstech##']      = $dbu->getUserName($this->obj->getField("users_id_tech"));

            $this->data['##lang.account.groupstech##'] = __s('Group in charge');
            $this->data['##account.groupstech##']      = Dropdown::getDropdownName(
                'glpi_groups',
                $this->obj->getField('groups_id_tech')
            );

            $this->data['##lang.account.location##'] = __s('Location');
            $this->data['##account.location##']      = Dropdown::getDropdownName(
                'glpi_locations',
                $this->obj->getField('locations_id')
            );

            $this->data['##lang.account.others##'] = __s('Others');
            $this->data['##account.others##']      = $this->obj->getField("others");

            $this->data['##lang.account.datecreation##'] = __s('Creation date');
            $this->data['##account.datecreation##']      = Html::convDate($this->obj->getField('date_creation'));

            $this->data['##lang.account.dateexpiration##'] = __s('Expiration date');
            $this->data['##account.dateexpiration##']      = Html::convDate($this->obj->getField('date_expiration'));

            $this->data['##lang.account.comment##'] = __s('Comments');
            $this->data['##account.comment##']      = $this->obj->getField("comment");

            $this->data['##lang.account.url##'] = __s('Direct link to created account', 'accounts');
            $this->data['##account.url##']      = urldecode($CFG_GLPI["url_base"] . "/index.php?redirect=".Account::class."_" .
                                                          $this->obj->getField("id"));
        } else {
            $this->data['##account.entity##']      =
            Dropdown::getDropdownName(
                'glpi_entities',
                $options['entities_id']
            );
            $this->data['##lang.account.entity##'] = __s('Entity');
            $this->data['##lang.account.action##'] = __s('Action');
            $this->data['##account.action##']      = ($event == "ExpiredAccounts" ? __s('Accounts expired', 'accounts') :
            __s('Accounts which expires', 'accounts'));

            $this->data['##lang.account.name##']           = __s('Name');
            $this->data['##lang.account.type##']           = __s('Type');
            $this->data['##lang.account.state##']          = __s('Status');
            $this->data['##lang.account.login##']          = __s('Login');
            $this->data['##lang.account.users##']          = __s('Affected User', 'accounts');
            $this->data['##lang.account.groups##']         = __s('Affected Group', 'accounts');
            $this->data['##lang.account.userstech##']      = __s('Technician in charge');
            $this->data['##lang.account.groupstech##']     = __s('Group in charge');
            $this->data['##lang.account.location##']       = __s('Location');
            $this->data['##lang.account.others##']         = __s('Others');
            $this->data['##lang.account.datecreation##']   = __s('Creation date');
            $this->data['##lang.account.dateexpiration##'] = __s('Expiration date');
            $this->data['##lang.account.comment##']        = __s('Comments');

            foreach ($options['accounts'] as $id => $account) {
                $tmp = [];

                $tmp['##account.name##']           = $account['name'];
                $tmp['##account.type##']           = Dropdown::getDropdownName(
                    'glpi_plugin_accounts_accounttypes',
                    $account['plugin_accounts_accounttypes_id']
                );
                $tmp['##account.state##']          = Dropdown::getDropdownName(
                    'glpi_plugin_accounts_accountstates',
                    $account['plugin_accounts_accountstates_id']
                );
                $tmp['##account.login##']          = $account['login'];
                $tmp['##account.users##']          = $dbu->getUserName($account['users_id']);
                $tmp['##account.groups##']         = Dropdown::getDropdownName(
                    'glpi_groups',
                    $account['groups_id']
                );
                $tmp['##account.userstech##']      = getUserName($account['users_id_tech']);
                $tmp['##account.groupstech##']     = Dropdown::getDropdownName(
                    'glpi_groups',
                    $account['groups_id_tech']
                );
                $tmp['##account.location##']       = Dropdown::getDropdownName(
                    'glpi_locations',
                    $account['locations_id']
                );
                $tmp['##account.others##']         = $account['others'];
                $tmp['##account.datecreation##']   = Html::convDate($account['date_creation']);
                $tmp['##account.dateexpiration##'] = Html::convDate($account['date_expiration']);
                $tmp['##account.comment##']        = $account['comment'];

                $this->data['accounts'][] = $tmp;
            }
        }
    }

   /**
    *
    */
    function getTags()
    {

        $tags = ['account.action'         => __s('Action'),
                    'account.entity'         => __s('Entity'),
                    'account.id'             => __s('ID'),
                    'account.url'            => __s('Direct link to created account', 'accounts'),
                    'account.name'           => __s('Name'),
                    'account.type'           => __s('Type'),
                    'account.state'          => __s('Status'),
                    'account.login'          => __s('Login'),
                    'account.users'          => __s('Affected User', 'accounts'),
                    'account.groups'         => __s('Affected Group', 'accounts'),
                    'account.userstech'      => __s('Technician in charge'),
                    'account.groupstech'     => __s('Group in charge'),
                    'account.location'       => __s('Location'),
                    'account.others'         => __s('Others'),
                    'account.datecreation'   => __s('Creation date'),
                    'account.dateexpiration' => __s('Expiration date'),
                    'account.comment'        => __s('Comments')];
        foreach ($tags as $tag => $label) {
            $this->addTagToList(['tag'   => $tag, 'label' => $label,
                                   'value' => true]);
        }

        $this->addTagToList(['tag'   => '##lang.account.title##',
                                'value' => false,
                                'label' => __s('An account has been created', 'accounts')]);

        $this->addTagToList(['tag'     => 'accounts',
                                'label'   => __s('Accounts expired or accounts which expires', 'accounts'),
                                'value'   => false,
                                'foreach' => true,
                                'events'  => ['AccountsWhichExpire', 'ExpiredAccounts']]);

        asort($this->tag_descriptions);
    }
}
