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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginAccountsNotificationTargetAccount
 */
class PluginAccountsNotificationTargetAccount extends NotificationTarget {

   const ACCOUNT_USER      = 1900;
   const ACCOUNT_GROUP     = 1901;
   const ACCOUNT_TECHUSER  = 1902;
   const ACCOUNT_TECHGROUP = 1903;

   /**
    * @return array
    */
   public function getEvents() {
      return array('new'                 => __('New account', 'accounts'),
                   'ExpiredAccounts'     => __('Accounts expired', 'accounts'),
                   'AccountsWhichExpire' => __('Accounts which expires', 'accounts'));
   }

   /**
    * Get additionnals targets for Tickets
    *
    * @param string $event
    */
   public function addAdditionalTargets($event = '') {
      $this->addTarget(self::ACCOUNT_USER, __('Affected User', 'accounts'));
      $this->addTarget(self::ACCOUNT_GROUP, __('Affected Group', 'accounts'));
      $this->addTarget(self::ACCOUNT_TECHUSER, __('Technician in charge of the hardware'));
      $this->addTarget(self::ACCOUNT_TECHGROUP, __('Group in charge of the hardware'));
   }

   /**
    * @param $data
    * @param $options
    */
   public function addSpecificTargets($data, $options) {

      //Look for all targets whose type is Notification::ITEM_USER
      switch ($data['items_id']) {

         case self::ACCOUNT_USER :
            $this->getUserAddress();
            break;
         case self::ACCOUNT_GROUP :
            $this->getGroupAddress();
            break;
         case self::ACCOUNT_TECHUSER :
            $this->getUserTechAddress();
            break;
         case self::ACCOUNT_TECHGROUP :
            $this->getGroupTechAddress();
            break;
      }
   }

   //Get receipient
   public function getUserAddress() {
      return $this->addUserByField("users_id");
   }

   public function getGroupAddress() {
      global $DB;

      $group_field = "groups_id";

      if (isset($this->obj->fields[$group_field])
          && $this->obj->fields[$group_field] > 0) {

         $query = $this->getDistinctUserSql() .
                  " FROM `glpi_users`
                  LEFT JOIN `glpi_groups_users` ON (`glpi_groups_users`.`users_id` = `glpi_users`.`id`)" .
                  $this->getProfileJoinSql() . "
                           WHERE `glpi_groups_users`.`groups_id` = '" . $this->obj->fields[$group_field] . "'";

         foreach ($DB->request($query) as $data) {
            $this->addToRecipientsList($data);
         }
      }
   }

   //Get receipient
   function getUserTechAddress() {
      return $this->addUserByField("users_id_tech");
   }

   public function getGroupTechAddress() {
      global $DB;

      $group_field = "groups_id_tech";

      if (isset($this->obj->fields[$group_field])
          && $this->obj->fields[$group_field] > 0) {

         $query = $this->getDistinctUserSql() .
                  " FROM `glpi_users`
                  LEFT JOIN `glpi_groups_users` ON (`glpi_groups_users`.`users_id` = `glpi_users`.`id`)" .
                  $this->getProfileJoinSql() . "
                           WHERE `glpi_groups_users`.`groups_id` = '" . $this->obj->fields[$group_field] . "'";

         foreach ($DB->request($query) as $data) {
            $this->addToRecipientsList($data);
         }
      }
   }

   /**
    * @param       $event
    * @param array $options
    */
   public function addDataForTemplate($event, $options = array()) {
      global $CFG_GLPI;

      if ($event == 'new') {

         $this->data['##lang.account.title##'] = __('An account has been created', 'accounts');

         $this->data['##lang.account.entity##'] = __('Entity');
         $this->data['##account.entity##']      =
            Dropdown::getDropdownName('glpi_entities',
                                      $this->obj->getField('entities_id'));
         $this->data['##lang.account.id##']     = __('ID');
         $this->data['##account.id##']          = sprintf("%07d", $this->obj->getField("id"));

         $this->data['##lang.account.name##'] = __('Name');
         $this->data['##account.name##']      = $this->obj->getField("name");

         $this->data['##lang.account.type##'] = __('Type');
         $this->data['##account.type##']      = Dropdown::getDropdownName('glpi_plugin_accounts_accounttypes',
                                                                           $this->obj->getField('plugin_accounts_accounttypes_id'));


         $this->data['##lang.account.state##'] = __('Status');
         $this->data['##account.state##']      = Dropdown::getDropdownName('glpi_plugin_accounts_accountstates',
                                                                            $this->obj->getField('plugin_accounts_accountstates_id'));

         $this->data['##lang.account.login##'] = __('Login');
         $this->data['##account.login##']      = $this->obj->getField("login");

         $this->data['##lang.account.users##'] = __('Affected User', 'accounts');
         $this->data['##account.users##']      = Html::clean(getUserName($this->obj->getField("users_id")));

         $this->data['##lang.account.groups##'] = __('Affected Group', 'accounts');
         $this->data['##account.groups##']      = Dropdown::getDropdownName('glpi_groups',
                                                                             $this->obj->getField('groups_id'));

         $this->data['##lang.account.userstech##'] = __('Technician in charge of the hardware');
         $this->data['##account.userstech##']      = Html::clean(getUserName($this->obj->getField("users_id_tech")));

         $this->data['##lang.account.groupstech##'] = __('Group in charge of the hardware');
         $this->data['##account.groupstech##']      = Dropdown::getDropdownName('glpi_groups',
                                                                                 $this->obj->getField('groups_id_tech'));

         $this->data['##lang.account.location##'] = __('Location');
         $this->data['##account.location##']      = Dropdown::getDropdownName('glpi_locations',
                                                                               $this->obj->getField('locations_id'));

         $this->data['##lang.account.others##'] = __('Others');
         $this->data['##account.others##']      = $this->obj->getField("others");

         $this->data['##lang.account.datecreation##'] = __('Creation date');
         $this->data['##account.datecreation##']      = Html::convDate($this->obj->getField('date_creation'));

         $this->data['##lang.account.dateexpiration##'] = __('Expiration date');
         $this->data['##account.dateexpiration##']      = Html::convDate($this->obj->getField('date_expiration'));

         $this->data['##lang.account.comment##'] = __('Comments');
         $this->data['##account.comment##']      = $this->obj->getField("comment");

         $this->data['##lang.account.url##'] = __('Direct link to created account', 'accounts');
         $this->data['##account.url##']      = urldecode($CFG_GLPI["url_base"] . "/index.php?redirect=PluginAccountsAccount_" .
                                                          $this->obj->getField("id"));

      } else {

         $this->data['##account.entity##']      =
            Dropdown::getDropdownName('glpi_entities',
                                      $options['entities_id']);
         $this->data['##lang.account.entity##'] = __('Entity');
         $this->data['##lang.account.action##'] = __('Action');
         $this->data['##account.action##']      = ($event == "ExpiredAccounts" ? __('Accounts expired', 'accounts') :
            __('Accounts which expires', 'accounts'));

         $this->data['##lang.account.name##']           = __('Name');
         $this->data['##lang.account.dateexpiration##'] = __('Expiration date');
         $this->data['##lang.account.type##']           = __('Type');
         $this->data['##lang.account.state##']          = __('Status');
         $this->data['##lang.account.login##']          = __('Login');
         $this->data['##lang.account.users##']          = __('Affected User', 'accounts');
         $this->data['##lang.account.groups##']         = __('Affected Group', 'accounts');
         $this->data['##lang.account.userstech##']      = __('Technician in charge of the hardware');
         $this->data['##lang.account.groupstech##']     = __('Group in charge of the hardware');
         $this->data['##lang.account.location##']       = __('Location');
         $this->data['##lang.account.others##']         = __('Others');
         $this->data['##lang.account.datecreation##']   = __('Creation date');
         $this->data['##lang.account.dateexpiration##'] = __('Expiration date');
         $this->data['##lang.account.comment##']        = __('Comments');

         foreach ($options['accounts'] as $id => $account) {
            $tmp = array();

            $tmp['##account.name##']           = $account['name'];
            $tmp['##account.type##']           = Dropdown::getDropdownName('glpi_plugin_accounts_accounttypes',
                                                                           $account['plugin_accounts_accounttypes_id']);
            $tmp['##account.state##']          = Dropdown::getDropdownName('glpi_plugin_accounts_accountstates',
                                                                           $account['plugin_accounts_accountstates_id']);
            $tmp['##account.login##']          = $account['login'];
            $tmp['##account.users##']          = Html::clean(getUserName($account['users_id']));
            $tmp['##account.groups##']         = Dropdown::getDropdownName('glpi_groups',
                                                                           $account['groups_id']);
            $tmp['##account.userstech##']      = Html::clean(getUserName($account['users_id_tech']));
            $tmp['##account.groupstech##']     = Dropdown::getDropdownName('glpi_groups',
                                                                           $account['groups_id_tech']);
            $tmp['##account.location##']       = Dropdown::getDropdownName('glpi_locations',
                                                                           $account['locations_id']);
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
   function getTags() {

      $tags = array('account.action'         => __('Action'),
                    'account.entity'         => __('Entity'),
                    'account.id'             => __('ID'),
                    'account.url'            => __('Direct link to created account', 'accounts'),
                    'account.name'           => __('Name'),
                    'account.type'           => __('Type'),
                    'account.state'          => __('Status'),
                    'account.login'          => __('Login'),
                    'account.users'          => __('Affected User', 'accounts'),
                    'account.groups'         => __('Affected Group', 'accounts'),
                    'account.userstech'      => __('Technician in charge of the hardware'),
                    'account.groupstech'     => __('Group in charge of the hardware'),
                    'account.location'       => __('Location'),
                    'account.others'         => __('Others'),
                    'account.datecreation'   => __('Creation date'),
                    'account.dateexpiration' => __('Expiration date'),
                    'account.comment'        => __('Comments'));
      foreach ($tags as $tag => $label) {
         $this->addTagToList(array('tag'   => $tag, 'label' => $label,
                                   'value' => true));
      }

      $this->addTagToList(array('tag'   => '##lang.account.title##',
                                'value' => false,
                                'label' => __('An account has been created', 'accounts')));

      $this->addTagToList(array('tag'     => 'accounts',
                                'label'   => __('Accounts expired or accounts which expires', 'accounts'),
                                'value'   => false,
                                'foreach' => true,
                                'events'  => array('AccountsWhichExpire', 'ExpiredAccounts')));

      asort($this->tag_descriptions);
   }
}