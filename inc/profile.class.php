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

class PluginAccountsProfile extends Profile {
   
   static $rightname = "profile";
    
   public function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType()=='Profile') {
         return PluginAccountsAccount::getTypeName(2);
      }
      return '';
   }


   public static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI;

      if ($item->getType()=='Profile') {
         $ID = $item->getID();
         $prof = new self();

         self::addDefaultProfileInfos($ID, 
                                    array('plugin_accounts'               => 0,
                                          'plugin_accounts_my_groups'     => 0,
                                          'plugin_accounts_open_ticket'   => 0,
                                          'plugin_accounts_see_all_users' => 0));
         $prof->showForm($ID);
      }
      return true;
   }
   
   static function createFirstAccess($ID) {
      //85
      self::addDefaultProfileInfos($ID,
                                    array('plugin_accounts'               => 127,
                                          'plugin_accounts_my_groups'     => 1,
                                          'plugin_accounts_open_ticket'   => 1,
                                          'plugin_accounts_see_all_users' => 1), true);
   }

   /**
    * Show profile form
    *
    * @param $items_id integer id of the profile
    * @param $target value url of target
    *
    * @return nothing
    **/
   function showForm($profiles_id=0, $openform=TRUE, $closeform=TRUE) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         $profile = new Profile();
         echo "<form method='post' action='".$profile->getFormURL()."'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);
      
      $rights = $this->getHelpdeskRights();
      if ($profile->getField('interface') == 'central') {
         $rights = $this->getAllRights();
      }
      $profile->displayRightsChoiceMatrix($rights, array('canedit'       => $canedit,
                                                      'default_class' => 'tab_bg_2',
                                                      'title'         => __('General')));
      
      echo "<table class='tab_cadre_fixehov'>";
      $effective_rights = ProfileRight::getProfileRights($profiles_id, array('plugin_accounts_see_all_users',
                                                                              'plugin_accounts_my_groups'));
      
      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('See accounts of my groups', 'accounts')."</td>";
      echo "<td colspan='5'>";
      Html::showCheckbox(array('name'    => '_plugin_accounts_my_groups',
                               'checked' => $effective_rights['plugin_accounts_my_groups']));
      echo "</td></tr>\n";
      
      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('See all accounts', 'accounts')."</td>";
      echo "<td colspan='5'>";
      Html::showCheckbox(array('name'    => '_plugin_accounts_see_all_users',
                               'checked' => $effective_rights['plugin_accounts_see_all_users']));
      echo "</td></tr>\n";
      echo "</table>";
      
      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr class='tab_bg_1'><th colspan='4'>".__('Helpdesk')."</th></tr>\n";

      $effective_rights = ProfileRight::getProfileRights($profiles_id, array('plugin_accounts_open_ticket'));
      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('Associable items to a ticket')."</td>";
      echo "<td colspan='5'>";
      Html::showCheckbox(array('name'    => '_plugin_accounts_open_ticket',
                               'checked' => $effective_rights['plugin_accounts_open_ticket']));
      echo "</td></tr>\n";
      echo "</table>";
      
      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', array('value' => $profiles_id));
         echo Html::submit(_sx('button', 'Save'), array('name' => 'update'));
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }
   
   static function getHelpdeskRights($all = false) {
      
      
      $rights = array(
          array('rights'  => Profile::getRightsFor('PluginAccountsAccount', 'helpdesk'),
                'label'     => _n('Account', 'Accounts', 2, 'accounts'),
                'field'     => 'plugin_accounts'
          ),
      );

      if ($all) {
         $rights[] = array('itemtype' => 'PluginAccountsAccount',
                           'label'    =>  __('See accounts of my groups', 'accounts'),
                           'field'    => 'plugin_accounts_my_groups');
                           
         $rights[] = array('itemtype' => 'PluginAccountsAccount',
                           'label'    =>  __('See all accounts', 'accounts'),
                           'field'    => 'plugin_accounts_see_all_users');
                           
         $rights[] = array('itemtype' => 'PluginAccountsAccount',
                           'label'    =>  __('Associable items to a ticket'),
                           'field'    => 'plugin_accounts_open_ticket');
      }
      
      return $rights;
   }
   
   static function getAllRights($all = false) {
      
      
      $rights = array(
          array('rights'  => Profile::getRightsFor('PluginAccountsAccount', 'central'),
                'label'     => _n('Account', 'Accounts', 2, 'accounts'),
                'field'     => 'plugin_accounts'
          ),
      );

      if ($all) {
         $rights[] = array('itemtype' => 'PluginAccountsAccount',
                           'label'    =>  __('See accounts of my groups', 'accounts'),
                           'field'    => 'plugin_accounts_my_groups');
                           
         $rights[] = array('itemtype' => 'PluginAccountsAccount',
                           'label'    =>  __('See all accounts', 'accounts'),
                           'field'    => 'plugin_accounts_see_all_users');
                           
         $rights[] = array('itemtype' => 'PluginAccountsAccount',
                           'label'    =>  __('Associable items to a ticket'),
                           'field'    => 'plugin_accounts_open_ticket');
      }
      
      return $rights;
   }

   /**
    * Init profiles
    *
    **/
    
   static function translateARight($old_right) {
      switch ($old_right) {
         case '': 
            return 0;
         case 'r' :
            return READ;
         case 'w':
            return ALLSTANDARDRIGHT + READNOTE + UPDATENOTE;
         case '0':
         case '1':
            return $old_right;
            
         default :
            return 0;
      }
   }
   
   /**
   * @since 0.85
   * Migration rights from old system to the new one for one profile
   * @param $profiles_id the profile ID
   */
   static function migrateOneProfile($profiles_id) {
      global $DB;
      //Cannot launch migration if there's nothing to migrate...
      if (!TableExists('glpi_plugin_accounts_profiles')) {
      return true;
      }
      
      foreach ($DB->request('glpi_plugin_accounts_profiles', 
                            "`profiles_id`='$profiles_id'") as $profile_data) {

         $matching = array('accounts'    => 'plugin_accounts', 
                           'all_users'   => 'plugin_accounts_see_all_users',
                           'my_groups'   => 'plugin_accounts_my_groups',
                           'open_ticket' => 'plugin_accounts_open_ticket');
         $current_rights = ProfileRight::getProfileRights($profiles_id, array_values($matching));
         foreach ($matching as $old => $new) {
            if (!isset($current_rights[$old])) {
               $query = "UPDATE `glpi_profilerights` 
                         SET `rights`='".self::translateARight($profile_data[$old])."' 
                         WHERE `name`='$new' AND `profiles_id`='$profiles_id'";
               $DB->query($query);
            }
         }
      }
   }
   
   /**
   * Initialize profiles, and migrate it necessary
   */
   static function initProfile() {
      global $DB;
      $profile = new self();

      //Add new rights in glpi_profilerights table
      foreach ($profile->getAllRights(true) as $data) {
         if (countElementsInTable("glpi_profilerights", 
                                  "`name` = '".$data['field']."'") == 0) {
            ProfileRight::addProfileRights(array($data['field']));
         }
      }
      
      //Migration old rights in new ones
      foreach ($DB->request("SELECT `id` FROM `glpi_profiles`") as $prof) {
         self::migrateOneProfile($prof['id']);
      }
      foreach ($DB->request("SELECT *
                           FROM `glpi_profilerights` 
                           WHERE `profiles_id`='".$_SESSION['glpiactiveprofile']['id']."' 
                              AND `name` LIKE '%plugin_accounts%'") as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights']; 
      }
   }


   static function removeRightsFromSession() {
      foreach (self::getAllRights(true) as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }

   /**
    * @param $profile
   **/
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {
      global $DB;
      
      $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if (countElementsInTable('glpi_profilerights',
                                   "`profiles_id`='$profiles_id' AND `name`='$right'") && $drop_existing) {
            $profileRight->deleteByCriteria(array('profiles_id' => $profiles_id, 'name' => $right));
         }
         if (!countElementsInTable('glpi_profilerights',
                                   "`profiles_id`='$profiles_id' AND `name`='$right'")) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }
}

?>