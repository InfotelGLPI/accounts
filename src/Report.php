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
use Glpi\Application\View\TemplateRenderer;
use Search;
use Session;

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
            // report_accounts_list.html.twig no longer carry one, so this is the branch they land in.
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

        // Holding the entity key does not lift the per-user/per-group visibility: the key is
        // shared by every user of the entity, so the same rule as the search engine, the
        // dropdowns and canViewItem() must filter this list (and its CSV/PDF export).
        $visibility = Account::getVisibilityCriteria(true);
        if ($visibility !== []) {
            $criteria['WHERE'][] = $visibility;
        }

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
        // and public/scripts/report.js is its only consumer. The CSV and PDF renderings export the
        // cryptograms as they stand, so they neither receive nor need it.
        $aeskey = (string) ($values["aeskey"] ?? '');

        $Hash = self::loadReachableHash($ID);

        $default_values["start"]  = $start = 0;
        $default_values["id"]     = $id = 0;
        $default_values["export"] = $export = false;

        foreach ($default_values as $key => $val) {
            if (isset($values[$key])) {
                $$key = $values[$key];
            }
        }
        $itemtype     = Account::class;
        if (!Session::haveRight(Profile::RIGHT_SEE_ALL_USERS, 1)) {
            return false;
        }
        // Set display type for export if define
        $output_type = $values["display_type"] ?? Search::HTML_OUTPUT;
        $output = SearchEngine::getOutputForLegacyKey($output_type);
        $is_html_output = $output instanceof HTMLSearchOutput;

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

        if ($is_html_output) {
            $columns = ['name' => __('Name')];
            if (Session::isMultiEntitiesMode()) {
                $columns['entities_id'] = __('Entity');
            }
            $columns['type']     = __('Type');
            $columns['login']    = __('Login');
            $columns['password'] = __('Decrypted password', 'accounts');

            $entries = [];
            foreach ($list as $account) {
                $IDc  = (int) $account['id'];
                $name = htmlescape((string) $account['name']);
                if ($_SESSION["glpiis_ids_visible"]) {
                    $name .= " (" . $IDc . ")";
                }
                $entries[] = [
                    'name'        => '<a href="' . htmlescape(PLUGIN_ACCOUNTS_WEBDIR . '/front/account.form.php?id=' . $IDc) . '">' . $name . '</a>',
                    'entities_id' => (string) ($account['entities_id'] ?? ''),
                    'type'        => (string) ($account['type'] ?? ''),
                    'login'       => (string) ($account['login'] ?? ''),
                    // Filled by public/scripts/report.js. No hidden field for the decrypted value,
                    // and nothing inside the export form: the plaintext must never leave the
                    // browser (same reasoning as #hidden_password in templates/account.html.twig).
                    'password'    => '<span data-accounts-encrypted="' . htmlescape((string) $account['password']) . '"></span>',
                ];
            }

            TemplateRenderer::getInstance()->display('@accounts/report_accounts_list.html.twig', [
                // Only the fingerprint travels in the export form: the export is gated on the
                // marker rememberVerification() left in the session when the key was checked,
                // so it still takes having known the key -- without the key itself being
                // re-posted on every click, or held anywhere afterwards.
                'show_export'    => !empty($list) && Session::getCurrentInterface() == "central",
                'export_url'     => PLUGIN_ACCOUNTS_WEBDIR . '/front/report.dynamic.php',
                'hash_id'        => $ID,
                'export_formats' => [
                    '-' . Search::PDF_OUTPUT_LANDSCAPE => __('All pages in landscape PDF'),
                    '-' . Search::PDF_OUTPUT_PORTRAIT  => __('All pages in portrait PDF'),
                    '-' . Search::CSV_OUTPUT           => __('All pages in CSV'),
                ],
                // Only the HTML rendering has a key to work with: it is the request that carries
                // one, and the decryption script is its only consumer.
                'aeskey'           => $aeskey,
                'verifier'         => (string) $Hash->fields['hash'],
                'datatable_params' => [
                    'is_tab'          => true,
                    'nofilter'        => true,
                    'nosort'          => true,
                    'super_header'    => __('Linked accounts list', 'accounts'),
                    'columns'         => $columns,
                    'formatters'      => [
                        'name'     => 'raw_html',
                        'password' => 'raw_html',
                    ],
                    'entries'         => $entries,
                    'total_number'    => count($entries),
                    'filtered_number' => count($entries),
                ],
            ]);

            return;
        }

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

        $row_num = 0;
        for ($i = $start; ($i < $numrows) && ($i < $end_display); $i++) {
            $row_num++;
            $current_row = [];
            $colnum = 0;
            $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["name"]];
            if (Session::isMultiEntitiesMode()) {
                $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]['entities_id']];
            }
            $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["type"] ?? ""];
            $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["login"] ?? ""];
            $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $list[$i]["password"] ?? ""];
            $rows[$row_num] = $current_row;
        }

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
