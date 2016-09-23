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

class PluginAccountsAccount_Item extends CommonDBRelation {

   static $rightname = "plugin_accounts";
   
   // From CommonDBRelation
   static public $itemtype_1 = "PluginAccountsAccount";
   static public $items_id_1 = 'plugin_accounts_accounts_id';
   static public $take_entity_1 = false ;

   static public $itemtype_2 = 'itemtype';
   static public $items_id_2 = 'items_id';
   static public $take_entity_2 = true ;
   

   /**
    * Get the standard massive actions which are forbidden
    *
    * @since version 0.84
    *
    * @return an array of massive actions
    **/
   public function getForbiddenStandardMassiveAction() {

      $forbidden   = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'update';
      return $forbidden;
   }

   /**
    * Clean table when item is purged
    *
    * @param $item Object to use
    *
    * @return nothing
    **/
   public static function cleanForItem(CommonDBTM $item) {

      $temp = new self();
      $temp->deleteByCriteria(
               array('itemtype' => $item->getType(),
                        'items_id' => $item->getField('id'))
      );
   }
   
   /**
    * Get Tab Name used for itemtype
    *
    * NB : Only called for existing object
    *      Must check right on what will be displayed + template
    *
    * @since version 0.83
    *
    * @param $item                     CommonDBTM object for which the tab need to be displayed
    * @param $withtemplate    boolean  is a template object ? (default 0)
    *
    *  @return string tab name
    **/
   public function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (!$withtemplate) {
         if ($item->getType()=='PluginAccountsAccount'
                  && count(PluginAccountsAccount::getTypes(false))) {
            if ($_SESSION['glpishow_count_on_tabs']) {
               return self::createTabEntry(_n('Associated item', 'Associated items', 2) ,
                        self::countForAccount($item));
            }
            return _n('Associated item', 'Associated items', 2);

         } else if (in_array($item->getType(), PluginAccountsAccount::getTypes(true))
                  && Session::haveRight("plugin_accounts", READ)) {
            if ($_SESSION['glpishow_count_on_tabs']) {
               return self::createTabEntry(PluginAccountsAccount::getTypeName(2),
                        self::countForItem($item));
            }
            return PluginAccountsAccount::getTypeName(2);
         }
      }
      return '';
   }

   /**
    * show Tab content
    *
    * @since version 0.83
    *
    * @param $item                  CommonGLPI object for which the tab need to be displayed
    * @param $tabnum       integer  tab number (default 1)
    * @param $withtemplate boolean  is a template object ? (default 0)
    *
    * @return true
    **/
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType()=='PluginAccountsAccount') {

         self::showForAccount($item);

      } else if (in_array($item->getType(), PluginAccountsAccount::getTypes(true))) {

         self::showForItem($item);

      }
      return true;
   }
   
   
   private static function countForAccount(PluginAccountsAccount $item) {

      $types = implode("','", $item->getTypes());
      if (empty($types)) {
         return 0;
      }
      return countElementsInTable('glpi_plugin_accounts_accounts_items',
               "`itemtype` IN ('$types')
               AND `plugin_accounts_accounts_id` = '".$item->getID()."'");
   }


   private static function countForItem(CommonDBTM $item) {

      return countElementsInTable('glpi_plugin_accounts_accounts_items',
               "`itemtype`='".$item->getType()."'
               AND `items_id` = '".$item->getID()."'");
   }

   public function getFromDBbyAccountsAndItem($plugin_accounts_accounts_id,$items_id,$itemtype) {
      global $DB;

      $query = "SELECT * FROM `".$this->getTable()."` " .
               "WHERE `plugin_accounts_accounts_id` = '" . $plugin_accounts_accounts_id . "'
                        AND `itemtype` = '" . $itemtype . "'
                                 AND `items_id` = '" . $items_id . "'";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result) != 1) {
            return false;
         }
         $this->fields = $DB->fetch_assoc($result);
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         } else {
            return false;
         }
      }
      return false;
   }

   public function addItem($values) {

      $this->add(array('plugin_accounts_accounts_id'=>$values['plugin_accounts_accounts_id'],
               'items_id'=>$values['items_id'],
               'itemtype'=>$values['itemtype']));

   }

   public function deleteItemByAccountsAndItem($plugin_accounts_accounts_id,$items_id,$itemtype) {

      if ($this->getFromDBbyAccountsAndItem($plugin_accounts_accounts_id,$items_id,$itemtype)) {
         $this->delete(array('id'=>$this->fields["id"]));
         return true;
      }
      return false;
   }


   /**
    * Show items links to a account
    *
    * @since version 0.84
    *
    * @param $PluginAccountsAccount PluginAccountsAccount object
    *
    * @return nothing (HTML display)
    **/
   public static function showForAccount(PluginAccountsAccount $account) {
      global $DB;

      $instID = $account->fields['id'];
      if (!$account->can($instID, READ)) {
         return false;
      }
      $canedit = $account->can($instID, UPDATE);

      $query = "SELECT DISTINCT `itemtype`
                FROM `glpi_plugin_accounts_accounts_items`
                WHERE `plugin_accounts_accounts_id` = '$instID'
                ORDER BY `itemtype`
                LIMIT ".count(PluginAccountsAccount::getTypes(true));

      $result = $DB->query($query);
      $number = $DB->numrows($result);
      $rand   = mt_rand();

      if ($canedit) {
         echo "<div class='firstbloc'>";
         echo "<form name='accountitem_form$rand' id='accountitem_form$rand' method='post'
         action='".Toolbox::getItemTypeFormURL("PluginAccountsAccount")."'>";

         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_2'><th colspan='2'>".__('Add an item')."</th></tr>";

         echo "<tr class='tab_bg_1'><td class='right'>";
         Dropdown::showAllItems("items_id", 0, 0,
         ($account->fields['is_recursive']?-1:$account->fields['entities_id']),
         PluginAccountsAccount::getTypes(), false, true);
         echo "</td><td class='center'>";
         echo "<input type='submit' name='additem' value=\""._sx('button', 'Add')."\" class='submit'>";
         echo "<input type='hidden' name='plugin_accounts_accounts_id' value='$instID'>";
         echo "</td></tr>";
         echo "</table>";
         Html::closeForm();
         echo "</div>";
      }

      echo "<div class='spaced'>";
      if ($canedit && $number) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams['item'] = $account;
         Html::showMassiveActions($massiveactionparams);
      }
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";

      if ($canedit && $number) {
         echo "<th width='10'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand)."</th>";
      }

      echo "<th>".__('Type')."</th>";
      echo "<th>".__('Name')."</th>";
      echo "<th>".__('Entity')."</th>";
      echo "<th>".__('Serial number')."</th>";
      echo "<th>".__('Inventory number')."</th>";
      echo "</tr>";

      for ($i=0 ; $i < $number ; $i++) {
         $itemtype=$DB->result($result, $i, "itemtype");
         if (!($item = getItemForItemtype($itemtype))) {
            continue;
         }

         if ($item->canView()) {
            $column = "name";
            if ($itemtype == 'Ticket') {
               $column = "id";
            }

            $itemtable = getTableForItemType($itemtype);
            $query     = "SELECT `$itemtable`.*,
            `glpi_plugin_accounts_accounts_items`.`id` AS IDD, ";

            if ($itemtype == 'KnowbaseItem') {
               $query .= "-1 AS entity
               FROM `glpi_plugin_accounts_accounts_items`, `$itemtable`
               ".KnowbaseItem::addVisibilityJoins()."
               WHERE `$itemtable`.`id` = `glpi_plugin_accounts_accounts_items`.`items_id`
               AND ";
            } else {
               $query .= "`glpi_entities`.`id` AS entity
               FROM `glpi_plugin_accounts_accounts_items`, `$itemtable` ";
               
               if ($itemtype !='Entity') {
                  $query .= "LEFT JOIN `glpi_entities`
                              ON (`glpi_entities`.`id` = `$itemtable`.`entities_id`) ";
               }
               $query .= "WHERE `$itemtable`.`id` = `glpi_plugin_accounts_accounts_items`.`items_id`
               AND ";
            }
            $query .= "`glpi_plugin_accounts_accounts_items`.`itemtype` = '$itemtype'
            AND `glpi_plugin_accounts_accounts_items`.`plugin_accounts_accounts_id` = '$instID' ";

            if ($itemtype =='KnowbaseItem') {
               if (Session::getLoginUserID()) {
                  $query = "AND ".KnowbaseItem::addVisibilityRestrict();
               } else {
                  // Anonymous access
                  if (Session::isMultiEntitiesMode()) {
                     $query = " AND (`glpi_entities_knowbaseitems`.`entities_id` = '0'
                              AND `glpi_entities_knowbaseitems`.`is_recursive` = '1')";
                  }
               }
            } else {
               $query .= getEntitiesRestrictRequest(" AND ", $itemtable, '', '',
                        $item->maybeRecursive());
            }

            if ($item->maybeTemplate()) {
               $query .= " AND `$itemtable`.`is_template` = '0'";
            }

            if ($itemtype == 'KnowbaseItem') {
               $query .= " ORDER BY `$itemtable`.`$column`";
            } else {
               $query .= " ORDER BY `glpi_entities`.`completename`, `$itemtable`.`$column`";
            }

            if ($itemtype == 'SoftwareLicense') {
               $soft = new Software();
            }

            if ($result_linked = $DB->query($query)) {
               if ($DB->numrows($result_linked)) {

                  while ($data = $DB->fetch_assoc($result_linked)) {

                     if ($itemtype == 'Ticket') {
                        $data["name"] = sprintf(__('%1$s: %2$s'), __('Ticket'), $data["id"]);
                     }

                     if ($itemtype == 'SoftwareLicense') {
                        $soft->getFromDB($data['softwares_id']);
                        $data["name"] = sprintf(__('%1$s - %2$s'), $data["name"],
                                 $soft->fields['name']);
                     }
                     $linkname = $data["name"];
                     if ($_SESSION["glpiis_ids_visible"]
                              || empty($data["name"])) {
                        $linkname = sprintf(__('%1$s (%2$s)'), $linkname, $data["id"]);
                     }

                     $link = Toolbox::getItemTypeFormURL($itemtype);
                     $name = "<a href=\"".$link."?id=".$data["id"]."\">".$linkname."</a>";

                     echo "<tr class='tab_bg_1'>";

                     if ($canedit) {
                        echo "<td width='10'>";
                        Html::showMassiveActionCheckBox(__CLASS__, $data["IDD"]);
                        echo "</td>";
                     }
                     echo "<td class='center'>".$item->getTypeName(1)."</td>";
                     echo "<td ".
                              (isset($data['is_deleted']) && $data['is_deleted']?"class='tab_bg_2_2'":"").
                              ">".$name."</td>";
                     echo "<td class='center'>".Dropdown::getDropdownName("glpi_entities",
                              $data['entity']);
                     echo "</td>";
                     echo "<td class='center'>".
                              (isset($data["serial"])? "".$data["serial"]."" :"-")."</td>";
                     echo "<td class='center'>".
                              (isset($data["otherserial"])? "".$data["otherserial"]."" :"-")."</td>";
                     echo "</tr>";
                  }
               }
            }
         }
      }
      echo "</table>";
      if ($canedit && $number) {
         $paramsma['ontop'] = false;
         $paramsma['item']  = $account;
         Html::showMassiveActions($paramsma);
         Html::closeForm();
      }
      echo "</div>";

   }
   
    /**
    * Show accounts associated to an item
    *
    * @since version 0.84
    *
    * @param $item            CommonDBTM object for which associated accounts must be displayed
    * @param $withtemplate    (default '')
   **/
   static function showForItem(CommonDBTM $item, $withtemplate='') {
      global $DB, $CFG_GLPI;

      $ID = $item->getField('id');

      if ($item->isNewID($ID)) {
         return false;
      }
      if (!Session::haveRight("plugin_accounts", READ)) {
         return false;
      }

      if (!$item->can($item->fields['id'], READ)) {
         return false;
      }

      if (empty($withtemplate)) {
         $withtemplate = 0;
      }

      $canedit       =  $item->canadditem('PluginAccountsAccount');
      $rand          = mt_rand();
      $is_recursive  = $item->isRecursive();
      $who           = Session::getLoginUserID();
      if (count($_SESSION["glpigroups"]) 
            && Session::haveRight("plugin_accounts_my_groups",1)) {
         $first_groups=true;
         $groups="";
         foreach ($_SESSION['glpigroups'] as $val) {
            if (!$first_groups) $groups.=",";
            else $first_groups=false;
            $groups.="'".$val."'";
         }
         $ASSIGN="( `groups_id` IN ($groups) OR `users_id` = '$who') ";
      } else { // Only personal ones
         $ASSIGN=" `users_id` = '$who' ";
      }
      
      $query = "SELECT `glpi_plugin_accounts_accounts_items`.`id` AS assocID,
                       `glpi_entities`.`id` AS entity,
                       `glpi_plugin_accounts_accounts`.`name` AS assocName,
                       `glpi_plugin_accounts_accounts`.*
                FROM `glpi_plugin_accounts_accounts_items`
                LEFT JOIN `glpi_plugin_accounts_accounts`
                 ON (`glpi_plugin_accounts_accounts_items`.`plugin_accounts_accounts_id`=`glpi_plugin_accounts_accounts`.`id`)
                LEFT JOIN `glpi_entities` ON (`glpi_plugin_accounts_accounts`.`entities_id`=`glpi_entities`.`id`)
                WHERE `glpi_plugin_accounts_accounts_items`.`items_id` = '$ID'
                      AND `glpi_plugin_accounts_accounts_items`.`itemtype` = '".$item->getType()."' ";

      $query .= getEntitiesRestrictRequest(" AND","glpi_plugin_accounts_accounts",'','',true);
      
      if (!Session::haveRight("plugin_accounts_see_all_users", 1)) {
         $query.= " AND $ASSIGN ";
      }  
      $query .= " ORDER BY `assocName`";

      $result = $DB->query($query);
      $number = $DB->numrows($result);
      $i      = 0;

      $accounts      = array();
      $account       = new PluginAccountsAccount();
      $used      = array();
      if ($numrows = $DB->numrows($result)) {
         while ($data = $DB->fetch_assoc($result)) {
            $accounts[$data['assocID']] = $data;
            $used[$data['id']] = $data['id'];
         }
      }

      if ($canedit && $withtemplate < 2) {
         // Restrict entity for knowbase
         $entities = "";
         $entity   = $_SESSION["glpiactive_entity"];

         if ($item->isEntityAssign()) {
            /// Case of personal items : entity = -1 : create on active entity (Reminder case))
            if ($item->getEntityID() >=0 ) {
               $entity = $item->getEntityID();
            }

            if ($item->isRecursive()) {
               $entities = getSonsOf('glpi_entities',$entity);
            } else {
               $entities = $entity;
            }
         }
         $limit = getEntitiesRestrictRequest(" AND ","glpi_plugin_accounts_accounts",'',$entities,true);
         $q = "SELECT COUNT(*)
               FROM `glpi_plugin_accounts_accounts`
               WHERE `is_deleted` = '0'
               $limit";

         $result = $DB->query($q);
         $nb     = $DB->result($result,0,0);

         echo "<div class='firstbloc'>";
         
         
         if (Session::haveRight('plugin_accounts', READ)
             && ($nb > count($used))) {
            echo "<form name='account_form$rand' id='account_form$rand' method='post'
                   action='".Toolbox::getItemTypeFormURL('PluginAccountsAccount')."'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'>";
            echo "<td colspan='4' class='center'>";
            echo "<input type='hidden' name='entities_id' value='$entity'>";
            echo "<input type='hidden' name='is_recursive' value='$is_recursive'>";
            echo "<input type='hidden' name='itemtype' value='".$item->getType()."'>";
            echo "<input type='hidden' name='items_id' value='$ID'>";
            if ($item->getType() == 'Ticket') {
               echo "<input type='hidden' name='tickets_id' value='$ID'>";
            }

            PluginAccountsAccount::dropdownAccount(array('entity' => $entities ,
                                                  'used'   => $used));
            echo "</td><td class='center' width='20%'>";
            echo "<input type='submit' name='additem' value=\"".
                     _sx('button', 'Associate a account', 'accounts')."\" class='submit'>";
            echo "</td>";
            echo "</tr>";
            echo "</table>";
            Html::closeForm();
         }

         echo "</div>";
      }

      echo "<div class='spaced'>";
      if ($canedit && $number && ($withtemplate < 2)) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams = array('num_displayed'  => $number);
         Html::showMassiveActions($massiveactionparams);
      }
      echo "<table class='tab_cadre_fixe'>";
      
      if (Session::isMultiEntitiesMode()) {
         $colsup=1;
      } else {
         $colsup=0;
      }
      
      //hash
      $hashclass = new PluginAccountsHash();
      $hash = 0;
      $restrict = getEntitiesRestrictRequest(" ",
               "glpi_plugin_accounts_hashes",
               '',
               $item->getEntityID(),
               $hashclass->maybeRecursive());
      $hashes = getAllDatasFromTable("glpi_plugin_accounts_hashes",$restrict);
      if (!empty($hashes)) {
         foreach ($hashes as $hashe) {
            $hash = $hashe["hash"];
            $hash_id = $hashe["id"];
         }
         $alert = '';
      } else {
         $alert = __('There is no encryption key for this entity', 'accounts');
      }

      $aeskey=new PluginAccountsAesKey();
      echo "<tr><th colspan='".(8+$colsup)."'>";
      if ($hash) {
         if (!$aeskey->getFromDBByHash($hash_id) || !$aeskey->fields["name"]) {
            _e('Encryption key', 'accounts');
            echo "&nbsp;<input type='password' name='aeskey' id='aeskey' autocomplete='off'>";
         } else {
            echo Html::hidden('aeskey', array('value'        => $aeskey->fields["name"], 
                                              'id'           => 'aeskey', 
                                              'autocomplete' => 'off'));
         }
      } else {
         echo __('Encryption key', 'accounts');
         echo "<div class='red'>";
         echo $alert;
         echo "</div>";
      }
      
      
      echo "<tr>";
      if ($canedit && $number && ($withtemplate < 2)) {
         echo "<th width='10'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand)."</th>";
      }
      echo "<th>".__('Name')."</th>";
      if (Session::isMultiEntitiesMode()) {
         echo "<th>".__('Entity')."</th>";
      }
      echo "<th>".__('Login')."</th>";
      echo "<th>".__('Password')."</th>";
      echo "<th>".__('Affected User', 'accounts')."</th>";
      echo "<th>".__('Type')."</th>";
      echo "<th>".__('Creation date')."</th>";
      echo "<th>".__('Expiration date')."</th>";
      echo "</tr>";
      $used = array();

      if ($number) {

         Session::initNavigateListItems('PluginAccountsAccount',
                           //TRANS : %1$s is the itemtype name,
                           //        %2$s is the name of the item (used for headings of a list)
                                        sprintf(__('%1$s = %2$s'),
                                                $item->getTypeName(1), $item->getName()));

         
         foreach  ($accounts as $data) {
            $accountID        = $data["id"];
            $link         = NOT_AVAILABLE;

            if ($account->getFromDB($accountID)) {
               $link         = $account->getLink();
            }

            Session::addToNavigateListItems('PluginAccountsAccount', $accountID);
            
            $used[$accountID] = $accountID;
            $assocID      = $data["assocID"];

            echo "<tr class='tab_bg_1".($data["is_deleted"]?"_2":"")."'>";
            if ($canedit && ($withtemplate < 2)) {
               echo "<td width='10'>";
               Html::showMassiveActionCheckBox(__CLASS__, $data["assocID"]);
               echo "</td>";
            }
            echo "<td class='center'>$link</td>";
            if (Session::isMultiEntitiesMode()) {
               echo "<td class='center'>".Dropdown::getDropdownName("glpi_entities", $data['entities_id']).
                    "</td>";
            }
            echo "<td class='center'>".$data["login"]."</td>";
            echo "<td class='center'>";
            //hash
            if (isset($hash_id) && $aeskey->getFromDBByHash($hash_id) && $aeskey->fields["name"]) {
               echo Html::hidden("encrypted_password$accountID", array('value'        => $data["encrypted_password"], 
                                                                        'id'           => "encrypted_password$accountID",
                                                                        'autocomplete' => 'off'));
               echo "<input type='text' id='hidden_password$accountID' value='' size='30' >";

               echo Html::scriptBlock("
                  if (!check_hash()) {
                     $('#hidden_password$accountID')
                        .after('".__('Wrong encryption key', 'accounts')."')
                        .remove();
                  } else {
                     decrypt_password('$accountID');
                  }
               "); 
            } else {

               $url = $CFG_GLPI["root_doc"]."/plugins/accounts/front/account.form.php";
               echo "&nbsp;<input type='button' id='decrypt_link$accountID' name='decrypte' value='".__s('Uncrypt', 'accounts')."'
                        class='submit'>";
               echo Html::hidden("encrypted_password$accountID", array('value'        => $data["encrypted_password"], 
                                                                        'id'           => "encrypted_password$accountID",
                                                                        'autocomplete' => 'off'));

               echo Html::scriptBlock("$(document).on('click', '#decrypt_link$accountID', function(event) {
                  if (!check_hash()) {
                     alert('".__('Wrong encryption key', 'accounts')."');
                  } else {
                     var decrypted_password = decrypt_password('$accountID');
                     $('#decrypt_link$accountID')
                        .after(decrypted_password)
                        .remove();
                  }
               });");      
            }
            echo "</td>";
            echo "<td class='center'>";
            echo getUsername($data["users_id"]);
            echo "</td>";
            echo "<td class='center'>";
            echo Dropdown::getDropdownName("glpi_plugin_accounts_accounttypes",
                     $data["plugin_accounts_accounttypes_id"]);
            echo "</td>";
            echo "<td class='center'>".Html::convdate($data["date_creation"])."</td>";
            if ($data["date_expiration"] <= date('Y-m-d') && !empty($data["date_expiration"])) {
               echo "<td class='center'>";
               echo "<div class='deleted'>".Html::convdate($data["date_expiration"])."</div>";
               echo "</td>";
            } else if (empty($data["date_expiration"])) {
               echo "<td class='center'>".__('Don\'t expire', 'accounts')."</td>";
            } else {
               echo "<td class='center'>".Html::convdate($data["date_expiration"])."</td>";
            }
            echo "</tr>";
            $i++;
         }
      }


      echo "</table>";
      echo Html::hidden('good_hash', array('value' => $hash, 
                                           'id'    => 'good_hash'));
      
      if ($canedit && $number && ($withtemplate < 2)) {
         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);
         Html::closeForm();
      }
      echo "</div>";
   }
}

?>