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

class PluginAccountsReport extends CommonDBTM {

   public static function queryAccountsList($values) {
      global $DB;

      $ID = $values["id"];
      $aeskey = $values["aeskey"];

      $PluginAccountsHash = new PluginAccountsHash();
      $PluginAccountsHash->getFromDB($ID);
      $hash = $PluginAccountsHash->fields["hash"];

      if ($PluginAccountsHash->isRecursive()) {
         $entities = getSonsOf('glpi_entities',$PluginAccountsHash->getEntityID());
      } else {
         $entities = $PluginAccountsHash->getEntityID();
      }
      if ($aeskey) {
         $query = "SELECT `glpi_plugin_accounts_accounts`.*,
                  `glpi_plugin_accounts_accounttypes`.`name` AS type
                  FROM `glpi_plugin_accounts_accounts`
                  LEFT JOIN `glpi_plugin_accounts_accounttypes`
                  ON (`glpi_plugin_accounts_accounts`.`plugin_accounts_accounttypes_id` = `glpi_plugin_accounts_accounttypes`.`id`)
                  WHERE `is_deleted`= '0'";
         $query.=getEntitiesRestrictRequest(" AND ","glpi_plugin_accounts_accounts",'',$entities,$PluginAccountsHash->maybeRecursive());
         $query.= " ORDER BY `type`,`name`";

         foreach ($DB->request($query) as $data) {
            $accounts[] = $data;
         }

         $list = array();
         if (!empty($accounts)) {

            foreach ($accounts as $account) {

               $ID=$account["id"];
               $list[$ID]["id"] = $account["id"];
               $list[$ID]["name"] = $account["name"];
               if (Session::isMultiEntitiesMode()) {
                  $list[$ID]["entities_id"] = Dropdown::getDropdownName("glpi_entities",$account["entities_id"]);
               }
               $list[$ID]["type"] = $account["type"];
               $list[$ID]["login"] = $account["login"];
               $list[$ID]["password"] = $account["encrypted_password"];
            }
         }
      }
      return $list;
   }

   public static function showAccountsList($values, $list) {
      global $CFG_GLPI;

      $ID = $values["id"];
      $aeskey = $values["aeskey"];

      $PluginAccountsHash = new PluginAccountsHash();
      $PluginAccountsHash->getFromDB($ID);
      $hash = $PluginAccountsHash->fields["hash"];

      $default_values["start"]  = $start  = 0;
      $default_values["id"]     = $id     = 0;
      $default_values["export"] = $export = false;

      foreach ($default_values as $key => $val) {
         if (isset($values[$key])) {
            $$key=$values[$key];
         }
      }

      // Set display type for export if define
      $output_type= Search::HTML_OUTPUT;

      if (isset($values["display_type"]))
         $output_type=$values["display_type"];
       
      $header_num=1;
      $nbcols=4;
      $row_num=1;
      $numrows = 1;

      $parameters = "id=".$ID."&amp;aeskey=".$aeskey;
      if ($output_type==Search::HTML_OUTPUT && !empty($list)) {
         self::printPager($start,$numrows,$_SERVER['PHP_SELF'],$parameters,"PluginAccountsReport");
      }

      echo Search::showHeader($output_type,1,$nbcols,1);

      echo Search::showNewLine($output_type);
      echo Search::showHeaderItem($output_type,__('Name'),$header_num);
      if (Session::isMultiEntitiesMode())
         echo Search::showHeaderItem($output_type,__('Entity'),$header_num);
      echo Search::showHeaderItem($output_type, __('Type'), $header_num);
      echo Search::showHeaderItem($output_type,__('Login'),$header_num);
      echo Search::showHeaderItem($output_type,__('Uncrypted password', 'accounts'),$header_num);
      echo Search::showEndLine($output_type);

      if (!empty($list)) {

         foreach ($list as $user => $field) {
            $row_num++;
            $item_num=1;
            echo Search::showNewLine($output_type);

            $IDc=$field["id"];
            if ($output_type==Search::HTML_OUTPUT) {
               echo "<input type='hidden' name='hash_id' value='".$ID."'>";
               echo "<input type='hidden' name='id[$IDc]' value='".$IDc."'>";
            }
            
            $name = "<a href='".$CFG_GLPI["root_doc"]."/plugins/accounts/front/account.form.php?id=".$IDc."'>".$field["name"];
            if ($_SESSION["glpiis_ids_visible"]) $name .= " (".$IDc.")";
            $name .= "</a>";
            echo Search::showItem($output_type,$name,$item_num,$row_num);
            if ($output_type==Search::HTML_OUTPUT) {
               echo "<input type='hidden' name='name[$IDc]' value='".$field["name"]."'>";
            }
            if (Session::isMultiEntitiesMode()) {
               echo Search::showItem($output_type,$field['entities_id'],$item_num,$row_num);
               if ($output_type==Search::HTML_OUTPUT) {
                  echo "<input type='hidden' name='entities_id[$IDc]' value='".$field["entities_id"]."'>";
               }
            }
            echo Search::showItem($output_type, $field["type"], $item_num, $row_num);
            if ($output_type==Search::HTML_OUTPUT) {
               echo "<input type='hidden' name='type[$IDc]' value='".$field["type"]."'>";
            }
            echo Search::showItem($output_type,$field["login"],$item_num,$row_num);
            if ($output_type==Search::HTML_OUTPUT) {
               echo "<input type='hidden' name='login[$IDc]' value='".$field["login"]."'>";
            }
            if ($output_type==Search::HTML_OUTPUT) {
               $encrypted = $field["password"];
               echo "<input type='hidden' name='password[$IDc]'>";
               $pass= "<p name='show_password' id='show_password$$IDc'></p>";
               $pass.= "<script language='javascript'>
               var good_hash=\"$hash\";
               var hash=SHA256(SHA256(\"$aeskey\"));
               if (hash != good_hash) {
               pass = \"".__('Wrong encryption key', 'accounts')."\";
            } else {
            pass = AESDecryptCtr(\"$encrypted\",SHA256(\"$aeskey\"), 256);
            };

            document.getElementsByName(\"password[$IDc]\").item(0).value = pass;

            document.getElementById(\"show_password$$IDc\").innerHTML = pass;
            </script>";

               echo Search::showItem($output_type,$pass,$item_num,$row_num);
            } else {
               echo Search::showItem($output_type,$field["password"],$item_num,$row_num);
            }
            echo Search::showEndLine($output_type);
         }
      }

      if ($output_type==Search::HTML_OUTPUT) {
         Html::closeForm();
      }
      // Display footer
      echo Search::showFooter($output_type,__('Linked accounts list', 'accounts'));
   }

   public static function printPager($start,$numrows,$target,$parameters,$item_type_output=0,$item_type_output_param=0) {
      global $CFG_GLPI;

      $list_limit=$_SESSION['glpilist_limit'];
      // Forward is the next step forward
      $forward = $start+$list_limit;

      // This is the end, my friend
      $end = $numrows-$list_limit;

      // Human readable count starts here
      $current_start=$start+1;

      // And the human is viewing from start to end
      $current_end = $current_start+$list_limit-1;
      if ($current_end>$numrows) {
         $current_end = $numrows;
      }

      // Backward browsing
      if ($current_start-$list_limit<=0) {
         $back=0;
      } else {
         $back=$start-$list_limit;
      }

      // Print it

      echo "<form method='POST' action=\"".$CFG_GLPI["root_doc"].
      "/plugins/accounts/front/report.dynamic.php\" target='_blank'>\n";
       
      echo "<table class='tab_cadre_pager'>\n";
      echo "<tr>\n";

      if (isset($_SESSION["glpiactiveprofile"])
               &&$_SESSION["glpiactiveprofile"]["interface"]=="central") {
         echo "<td class='tab_bg_2' width='30%'>" ;

         echo "<input type='hidden' name='item_type' value='PluginAccountsReport'>";
         if ($item_type_output_param!=0)
            echo "<input type='hidden' name='item_type_param' value='".
            serialize($item_type_output_param)."'>";
         $explode=explode("&amp;",$parameters);
         for ($i=0;$i<count($explode);$i++) {
            $pos=strpos($explode[$i],'=');
            echo "<input type='hidden' name=\"".substr($explode[$i],0,$pos)."\" value=\"".
                     substr($explode[$i],$pos+1)."\">";
         }
         self::showOutputFormat();

         echo "</td>" ;
      }

      // End pager
      echo "</tr>\n";
      echo "</table><br>\n";
   }
   
   static function showOutputFormat() {
      global $CFG_GLPI;

      $values['-'.Search::PDF_OUTPUT_LANDSCAPE] = __('All pages in landscape PDF');
      $values['-'.Search::PDF_OUTPUT_PORTRAIT]  = __('All pages in portrait PDF');
      $values['-'.Search::SYLK_OUTPUT]          = __('All pages in SLK');
      $values['-'.Search::CSV_OUTPUT]           = __('All pages in CSV');

      Dropdown::showFromArray('display_type', $values);
      if (GLPI_USE_CSRF_CHECK) {
         echo "<input type='hidden' id='report_csrf' name='_glpi_csrf_token' value='".Session::getNewCSRFToken()."'>";
      }
         
      echo "<input type='image' name='export' class='pointer' src='".$CFG_GLPI["root_doc"]."/pics/export.png'
             title=\""._sx('button', 'Export')."\" value=\""._sx('button', 'Export')."\">";
   }
}

?>