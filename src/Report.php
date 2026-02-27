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

use CommonDBTM;
use DbUtils;
use Dropdown;
use Glpi\Search\Output\HTMLSearchOutput;
use Glpi\Search\SearchEngine;
use Html;
use Search;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Report
 */
class Report extends CommonDBTM
{
    /**
     * @param $values
     *
     * @return array
     */
    public static function queryAccountsList($values)
    {
        global $DB;

        $ID     = $values["id"];
        $aeskey = $values["aeskey"];

        $Hash = new Hash();
        $Hash->getFromDB($ID);
        $dbu = new DbUtils();

        if ($Hash->isRecursive()) {
            $entities = $dbu->getSonsOf('glpi_entities', $Hash->getEntityID());
        } else {
            $entities = [$Hash->getEntityID()];
        }

        $entities = array_intersect($entities, $_SESSION["glpiactiveentities"]);
        $list     = [];
        if ($aeskey) {
            $criteria = [
                'SELECT'    => [
                    'glpi_plugin_accounts_accounts.*',
                    'glpi_plugin_accounts_accounttypes.name AS typename',
                ],
                'FROM'      => 'glpi_plugin_accounts_accounts',
                'LEFT JOIN'       => [
                    'glpi_plugin_accounts_accounttypes' => [
                        'ON' => [
                            'glpi_plugin_accounts_accounts' => 'plugin_accounts_accounttypes_id',
                            'glpi_plugin_accounts_accounttypes'          => 'id',
                        ],
                    ],
                ],
                'WHERE'     => [
                    'is_deleted'  => 0,
                ],
                'ORDERBY'   => 'typename, name',
            ];

            $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
                'glpi_plugin_accounts_accounts',
                $field = '',
                $entities,
                $Hash->maybeRecursive(),
            );

            $iterator = $DB->request($criteria);

            if (count($iterator) > 0) {
                foreach ($iterator as $data) {
                    $accounts[] = $data;
                }
            }

            if (!empty($accounts)) {
                $i = 0;
                foreach ($accounts as $account) {
                    $list[$i]["id"]   = $account["id"];
                    $list[$i]["name"] = $account["name"];
                    if (Session::isMultiEntitiesMode()) {
                        $list[$i]["entities_id"] = Dropdown::getDropdownName("glpi_entities", $account["entities_id"]);
                    }
                    $list[$i]["type"]     = $account["typename"];
                    $list[$i]["login"]    = $account["login"];
                    $list[$i]["password"] = $account["encrypted_password"];
                    $i++;
                }
            }
        }

        return $list;
    }

    /**
     * @param $values
     * @param $list
     */
    public static function showAccountsList($values, $list)
    {
        $ID     = $values["id"];
        $aeskey = $values["aeskey"];

        $Hash = new Hash();
        $Hash->getFromDB($ID);
        $hashvalue = $Hash->fields["hash"];

        $default_values["start"]  = $start = 0;
        $default_values["id"]     = $id = 0;
        $default_values["export"] = $export = false;

        foreach ($default_values as $key => $val) {
            if (isset($values[$key])) {
                $$key = $values[$key];
            }
        }
        $itemtype     = Account::class;
        if (!Session::haveRight("plugin_accounts_see_all_users", 1)) {
            return false;
        }
        // Set display type for export if define
        $output_type = $values["display_type"] ?? Search::HTML_OUTPUT;
        $output = SearchEngine::getOutputForLegacyKey($output_type);
        $is_html_output = $output instanceof HTMLSearchOutput;
        $html_output = '';

        if (isset($values["display_type"])) {
            $output_type = $values["display_type"];
        }

        $headers = [];
        $rows = [];
        $numrows = count($list);
        $end_display = $start + $_SESSION['glpilist_limit'];
        if (isset($_GET['export_all'])) {
            $start       = 0;
            $end_display = $numrows;
        }

        $nbcols     = 4;
        if (!$is_html_output) {
            $nbcols--;
        }

        $parameters = "id=" . $ID . "&amp;aeskey=" . $aeskey;

        if ($is_html_output && !empty($list)) {
            self::printPager($start, $numrows, $_SERVER['PHP_SELF'], $parameters, "Report");
        }
        if ($is_html_output) {
            $html_output .= $output::showHeader($end_display - $start + 1, $nbcols);
        }
        if (!$is_html_output) {
            $headers[] = __s('Name');
            if (Session::isMultiEntitiesMode()) {
                $headers[] = __s('Entity');
            }
            $headers[] = __s('Type');
            $headers[] = __s('Login');
            $headers[] = __s('Uncrypted password', 'accounts');
        } else {
            $header_num    = 1;
            $html_output .= $output::showNewLine();
            $html_output .= $output::showHeaderItem(__s('Name'), $header_num);
            if (Session::isMultiEntitiesMode()) {
                $html_output .= $output::showHeaderItem(__s('Entity'), $header_num);
            }
            $html_output .= $output::showHeaderItem(__s('Type'), $header_num);
            $html_output .= $output::showHeaderItem(__s('Login'), $header_num);
            $html_output .= $output::showHeaderItem(__s('Uncrypted password', 'accounts'), $header_num);
            $html_output .= $output::showEndLine($output_type);
        }
        $row_num = 0;
        if (!empty($list)) {
            for ($i = $start; ($i < $numrows) && ($i < $end_display); $i++) {
                $row_num++;
                $current_row = [];
                $item_num = 1;
                $colnum = 0;
                if ($is_html_output) {
                    $html_output .= $output::showNewLine($i % 2 === 1);
                }
                $IDc = $list[$i]["id"];
                if ($is_html_output) {
                    echo Html::hidden('hash_id', ['value' => $ID]);
                    echo Html::hidden("id[$IDc]", ['value' => $IDc]);
                }

                $name = "<a href='" . PLUGIN_ACCOUNTS_WEBDIR . "/front/account.form.php?id=" . $IDc . "'>" . $list[$i]["name"];
                if ($_SESSION["glpiis_ids_visible"]) {
                    $name .= " (" . $IDc . ")";
                }
                $name .= "</a>";
                if ($is_html_output) {
                    $html_output .= $output::showItem($name, $item_num, $row_num);
                    echo Html::hidden("name[$IDc]", ['value' => $list[$i]["name"]]);
                } else {
                    $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $name];
                }
                if (Session::isMultiEntitiesMode()) {
                    if ($is_html_output) {
                        $html_output .= $output::showItem($list[$i]['entities_id'], $item_num, $row_num);
                        echo Html::hidden("entities_id[$IDc]", ['value' => $list[$i]["entities_id"]]);
                    } else {
                        $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]['entities_id']];
                    }
                }
                if ($is_html_output) {
                    $html_output .= $output::showItem($list[$i]["type"] ?? "", $item_num, $row_num);
                    echo Html::hidden("type[$IDc]", ['value' => $list[$i]["type"]]);
                } else {
                    $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["type"]];
                }

                if ($is_html_output) {
                    $html_output .= $output::showItem($list[$i]["login"] ?? "", $item_num, $row_num);
                    echo Html::hidden("login[$IDc]", ['value' => $list[$i]["login"]]);
                } else {
                    $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["login"]];
                }

                if ($is_html_output) {
                    $encrypted = $list[$i]["password"];
                    echo Html::hidden("password[$IDc]");
                    $pass = "<p name='show_password' id='show_password$$IDc'></p>";
                    $pass .= Html::scriptBlock("
                                var good_hash=\"$hashvalue\";
                                var hash=SHA256(SHA256(\"$aeskey\"));
                                if (hash != good_hash) {
                                    pass = \"" . __s('Wrong encryption key', 'accounts') . "\";
                                } else {
                                    pass = AESDecryptCtr(\"$encrypted\",SHA256(\"$aeskey\"), 256);
                                }

                                document.getElementsByName(\"password[$IDc]\").item(0).value = pass;
                                document.getElementById(\"show_password$$IDc\").innerHTML = pass;
                                ");

                    $html_output .= $output::showItem($pass, $item_num, $row_num);
                } else {
                    $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["password"]];
                }

                $rows[$row_num] = $current_row;
                if ($is_html_output) {
                    $html_output .= $output::showEndLine(false);
                }
            }
        }

        if ($is_html_output) {
            Html::closeForm();
            $output::showFooter(__s('Linked accounts list', 'accounts'), $numrows);
        }

        if ($is_html_output) {
            echo $html_output;
        } else {
            $params = [
                'start' => 0,
                'is_deleted' => 0,
                'as_map' => 0,
                'browse' => 0,
                'unpublished' => 1,
                'criteria' => [],
                'metacriteria' => [],
                'display_type' => 0,
                'hide_controls' => true,
            ];

            $accounts_data = SearchEngine::prepareDataForSearch($itemtype, $params);
            $accounts_data = array_merge($accounts_data, [
                'itemtype' => $itemtype,
                'data' => [
                    'totalcount' => $numrows,
                    'count' => $numrows,
                    'search' => '',
                    'cols' => [],
                    'rows' => $rows,
                ],
            ]);

            $colid = 0;
            foreach ($headers as $header) {
                $accounts_data['data']['cols'][] = [
                    'name' => $header,
                    'itemtype' => $itemtype,
                    'id' => ++$colid,
                ];
            }

            $output->displayData($accounts_data, []);
        }
    }

    /**
     * @param     $start
     * @param     $numrows
     * @param     $target
     * @param     $parameters
     * @param int $item_type_output
     * @param int $item_type_output_param
     */
    public static function printPager($start, $numrows, $target, $parameters, $item_type_output = 0, $item_type_output_param = 0)
    {
        global $CFG_GLPI;

        // Print it

        echo "<form method='POST' action=\"" . PLUGIN_ACCOUNTS_WEBDIR
             . "/front/report.dynamic.php\" target='_blank'>\n";

        echo "<table class='tab_cadre_pager'>\n";
        echo "<tr>\n";

        if (Session::getCurrentInterface() == "central") {
            echo "<td class='tab_bg_2' width='30%'>";

            echo Html::hidden('itemtype', ['value' => Report::class]);
            if ($item_type_output_param != 0) {
                echo Html::hidden('item_type_param', ['value' => serialize($item_type_output_param)]);
            }
            $explode = explode("&amp;", $parameters);
            for ($i = 0; $i < count($explode); $i++) {
                $pos = strpos($explode[$i], '=');
                $name = substr($explode[$i], 0, $pos);
                echo Html::hidden($name, ['value' => substr($explode[$i], $pos + 1)]);
            }
            self::showOutputFormat();

            echo "</td>";
        }

        // End pager
        echo "</tr>\n";
        echo "</table><br>\n";
    }

    public static function showOutputFormat()
    {
        $values['-' . Search::PDF_OUTPUT_LANDSCAPE] = __s('All pages in landscape PDF');
        $values['-' . Search::PDF_OUTPUT_PORTRAIT]  = __s('All pages in portrait PDF');
        $values['-' . Search::CSV_OUTPUT]           = __s('All pages in CSV');

        Dropdown::showFromArray('display_type', $values);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo Html::submit(_sx('button', 'Export'), ['name' => 'export', 'class' => 'btn btn-primary']);
    }
}
