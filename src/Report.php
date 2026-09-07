<?php

/**
 * -------------------------------------------------------------------------
 * accounts plugin for GLPI
 * Copyright (C) 2015-2026 by the accounts Development Team.
 *
 * https://github.com/InfotelGLPI/accounts
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of accounts.
 *
 * accounts is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * accounts is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with accounts. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Accounts;

use CommonDBTM;
use DbUtils;
use Dropdown;
use Glpi\Exception\Http\AccessDeniedHttpException;
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
     * Load the fingerprint the report is asked about, refusing one outside the caller's
     * entities.
     *
     * The ID comes straight from the request (ajax/viewaccountslist.php reads $_POST['id'],
     * front/report.dynamic.php $_POST['id'] as well) and the only right involved,
     * plugin_accounts_see_all_users, is global. The account list further down is properly
     * bounded by getEntitiesRestrictCriteria(), but the record itself is not: showAccountsList()
     * publishes its 'hash' column -- the PBKDF2 verifier of the master key -- into the page for
     * the browser to check the typed key against. Handing that out for another entity gives an
     * offline oracle on a human-chosen passphrase, which is exactly what an attacker needs to
     * confirm a dictionary hit without ever touching the application again. Same guard as
     * front/hash.form.php and ajax/getHashOnSelectEncryptionKey.php.
     *
     * @param int $ID The requested fingerprint ID
     * @return Hash   The loaded record
     */
    private static function loadReachableHash(int $ID): Hash
    {
        $hash = new Hash();
        if (
            $ID <= 0
            || !$hash->getFromDB($ID)
            || !Session::haveAccessToEntity(
                $hash->fields['entities_id'],
                (bool) $hash->fields['is_recursive'],
            )
        ) {
            throw new AccessDeniedHttpException();
        }

        return $hash;
    }

    /**
     * @param $values
     *
     * @return array
     */
    public static function queryAccountsList($values)
    {
        global $DB;

        $ID     = (int) ($values["id"] ?? 0);
        $aeskey = (string) ($values["aeskey"] ?? '');

        $Hash = self::loadReachableHash($ID);
        $dbu  = new DbUtils();

        if ($Hash->isRecursive()) {
            $entities = $dbu->getSonsOf('glpi_entities', $Hash->getEntityID());
        } else {
            $entities = [$Hash->getEntityID()];
        }

        $entities = array_intersect($entities, $_SESSION["glpiactiveentities"]);
        $list     = [];
        $verifier = (string) $Hash->fields['hash'];

        if ($aeskey !== '') {
            // The gate used to be "$aeskey is not empty": the string "x" opened the list, and on
            // the CSV/PDF path that list is every cryptogram of the entity in one download.
            // Confront the key with the stored verifier, exactly like front/hash.form.php does
            // before a rotation.
            if (!AccountCrypto::verify($aeskey, $verifier)) {
                Session::addMessageAfterRedirect(
                    __s('Wrong encryption key', 'accounts'),
                    false,
                    ERROR,
                );

                return $list;
            }

            self::rememberVerification($ID, $verifier);
        } elseif (!self::hasVerifiedKey($ID, $verifier)) {
            // No key in this request, and none checked recently enough: the export forms built by
            // printPager() no longer carry one, so this is the branch they land in.
            Session::addMessageAfterRedirect(
                __s('The encryption key is no longer available, please display the list again before exporting it', 'accounts'),
                false,
                ERROR,
            );

            return $list;
        }

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
                'glpi_plugin_accounts_hashes' => [
                    'ON' => [
                        'glpi_plugin_accounts_accounts' => 'plugin_accounts_hashes_id',
                        'glpi_plugin_accounts_hashes'          => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_plugin_accounts_accounts.is_deleted'  => 0,
                'glpi_plugin_accounts_hashes.id'  => $ID,
            ],
            'ORDERBY'   => 'glpi_plugin_accounts_accounts.name',
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

        return $list;
    }

    /**
     * How long a checked key stays accepted by the pager and the export form, in seconds.
     */
    private const KEY_TTL = 900;

    /**
     * Record that a key has just been confronted with the stored verifier -- the fact of it,
     * never the key.
     *
     * Every other screen of the plugin goes out of its way to keep the master key inside the
     * browser: templates/account.html.twig deliberately leaves #aeskey without a name attribute
     * so it cannot be posted at all. The report path could not do quite the same, because
     * queryAccountsList() has to confront the key with the verifier before building a list of
     * cryptograms; but it used to re-post it on every page turn and every export, which is one
     * occasion per click for a logging proxy, an APM probe or a 500-page dump to record the one
     * secret that opens every account of the entity.
     *
     * The obvious repair -- send it once, keep it server-side -- traded a transport exposure for
     * a storage one: the master key would then sit in cleartext in whatever backs the session, a
     * file or a Redis instance that outlives the request and lands in backups. It is not needed
     * there. The server never decrypts anything: the CSV and PDF exports carry the same
     * cryptograms as the screen does, and the browser is what turns them into passwords. All the
     * export path has to establish is that the key was produced at some point during this
     * session, so that is all that is kept.
     *
     * @param int    $hash_id  Fingerprint the key was checked against
     * @param string $verifier Verifier it was checked against, to notice a rotation
     */
    private static function rememberVerification(int $hash_id, string $verifier): void
    {
        $_SESSION['plugin_accounts']['report_key'][$hash_id] = [
            // Deliberately not the key. See the method doc.
            'verified_until' => time() + self::KEY_TTL,
            'verifier'       => $verifier,
        ];
    }

    /**
     * Whether the key of a fingerprint was checked recently enough to still open an export.
     *
     * An expired marker is dropped rather than merely ignored, and so is one left by a check
     * against a verifier that has since been replaced -- a rotation means the key that was typed
     * is no longer the key of the vault.
     *
     * @param int    $hash_id  Fingerprint concerned
     * @param string $verifier Verifier currently stored for it
     */
    public static function hasVerifiedKey(int $hash_id, string $verifier): bool
    {
        $kept = $_SESSION['plugin_accounts']['report_key'][$hash_id] ?? null;
        if (!is_array($kept)) {
            return false;
        }

        if (
            (int) ($kept['verified_until'] ?? 0) < time()
            || (string) ($kept['verifier'] ?? '') !== $verifier
        ) {
            unset($_SESSION['plugin_accounts']['report_key'][$hash_id]);

            return false;
        }

        return true;
    }

    /**
     * @param $values
     * @param $list
     */
    public static function showAccountsList($values, $list)
    {
        $ID = (int) ($values["id"] ?? 0);
        // Only the HTML rendering has a key to work with: it is the request that carries one,
        // and its script block is the only consumer. The CSV and PDF renderings export the
        // cryptograms as they stand, so they neither receive nor need it.
        $aeskey = (string) ($values["aeskey"] ?? '');

        $Hash      = self::loadReachableHash($ID);
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
        //        $end_display = $start + $_SESSION['glpilist_limit'];
        $end_display = $numrows;
        if (isset($_GET['export_all'])) {
            $start       = 0;
            $end_display = $numrows;
        }

        $nbcols     = 4;
        if (!$is_html_output) {
            $nbcols--;
        }

        // printPager() turns this into the hidden fields of the export form. Only the fingerprint
        // travels: the export is gated on the marker rememberVerification() left in the session
        // when the key was checked, so it still takes having known the key -- without the key
        // itself being re-posted on every click, or held anywhere afterwards.
        $parameters = "id=" . $ID;

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
            // CSV and PDF cannot run the decryption script, so what lands in those files is
            // the cryptogram. Labelling it "Decrypted password" invited treating a vault dump
            // as a harmless export.
            $headers[] = __s('Encrypted password', 'accounts');
        } else {
            $header_num    = 1;
            $html_output .= $output::showNewLine();
            $html_output .= $output::showHeaderItem(__s('Name'), $header_num);
            if (Session::isMultiEntitiesMode()) {
                $html_output .= $output::showHeaderItem(__s('Entity'), $header_num);
            }
            $html_output .= $output::showHeaderItem(__s('Type'), $header_num);
            $html_output .= $output::showHeaderItem(__s('Login'), $header_num);
            $html_output .= $output::showHeaderItem(__s('Decrypted password', 'accounts'), $header_num);
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

                // Values come from the database and are stored raw since GLPI 10+: escape before HTML output (stored XSS)
                $name = "<a href='" . PLUGIN_ACCOUNTS_WEBDIR . "/front/account.form.php?id=" . (int) $IDc . "'>"
                    . htmlspecialchars((string) $list[$i]["name"], ENT_QUOTES, 'UTF-8');
                if ($_SESSION["glpiis_ids_visible"]) {
                    $name .= " (" . (int) $IDc . ")";
                }
                $name .= "</a>";
                if ($is_html_output) {
                    $html_output .= $output::showItem($name, $item_num, $row_num);
                } else {
                    $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $name];
                }
                if (Session::isMultiEntitiesMode()) {
                    if ($is_html_output) {
                        $html_output .= $output::showItem($list[$i]['entities_id'], $item_num, $row_num);
                    } else {
                        $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]['entities_id']];
                    }
                }
                if ($is_html_output) {
                    // Escape DB-stored value before HTML output (stored XSS)
                    $html_output .= $output::showItem(htmlspecialchars((string) ($list[$i]["type"] ?? ""), ENT_QUOTES, 'UTF-8'), $item_num, $row_num);
                } else {
                    $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["type"] ?? ""];
                }

                if ($is_html_output) {
                    // Escape DB-stored value before HTML output (stored XSS)
                    $html_output .= $output::showItem(htmlspecialchars((string) ($list[$i]["login"] ?? ""), ENT_QUOTES, 'UTF-8'), $item_num, $row_num);
                } else {
                    $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["login"] ?? ""];
                }

                if ($is_html_output) {
                    $encrypted = $list[$i]["password"];
                    // No hidden field for the decrypted value. It used to be emitted here and
                    // filled by the script below, inside the export form opened by printPager():
                    // one click on Export then posted every password of the vault in cleartext to
                    // front/report.dynamic.php, which never reads them -- it re-queries the
                    // database. Same reasoning as templates/account.html.twig, where
                    // #hidden_password deliberately carries no name attribute.
                    $pass = "<p name='show_password' id='show_password$$IDc'></p>";
                    // Encode all dynamic values as JS literals to prevent script injection
                    $js_aeskey    = json_encode($aeskey);
                    $js_encrypted = json_encode($encrypted);
                    $js_hashvalue = json_encode($hashvalue);
                    $js_wrongkey  = json_encode(__('Wrong encryption key', 'accounts'));
                    $pass .= Html::scriptBlock("
                                var good_hash = $js_hashvalue;
                                var aeskey = $js_aeskey;
                                var encrypted = $js_encrypted;

                                // Verify the typed key against the stored verifier. generic_check_hash
                                // (crypt.js) handles both the salted PBKDF2 format and legacy double SHA-256.
                                if (generic_check_hash(good_hash, aeskey)) {
                                    // decrypt_cryptogram dispatches on the version prefix (v3 with a
                                    // mandatory MAC, v2 with an optional one) and falls back to the
                                    // legacy AES-CTR format. Never pick the version here.
                                    pass = decrypt_cryptogram(encrypted, aeskey);
                                } else {
                                    pass = $js_wrongkey;
                                }

                                // Display cell only: the plaintext must never leave the browser.
                                document.getElementById(\"show_password$$IDc\").textContent = pass;

                                ");

                    $html_output .= $output::showItem($pass, $item_num, $row_num);
                } else {
                    $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["password"] ?? ""];
                }

                $rows[$row_num] = $current_row;
                if ($is_html_output) {
                    $html_output .= $output::showEndLine(false);
                }
            }
        }

        if ($is_html_output) {
            // Everything else on this path is accumulated into $html_output and echoed in one
            // go below. Html::closeForm() prints straight away unless told otherwise, so the
            // </form> used to be emitted before the table it closes; and showFooter() returns
            // its fragment in GLPI 11 instead of printing it, so the end of the table and of
            // the containers opened by showHeader() was computed and then dropped. The browser
            // was left to guess, and swallowed whatever followed on the page.
            $html_output .= Html::closeForm(false);
            $html_output .= $output::showFooter(__s('Linked accounts list', 'accounts'), $numrows);
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
