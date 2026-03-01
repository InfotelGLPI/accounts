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

use CommonDBRelation;
use CommonDBTM;
use CommonGLPI;
use DbUtils;
use Dropdown;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Html;
use KnowbaseItem;
use Session;

final class Account_Item extends CommonDBRelation
{
    public static $rightname = "plugin_accounts";

    // From CommonDBRelation
    public static $itemtype_1    = Account::class;
    public static $items_id_1    = 'plugin_accounts_accounts_id';
    public static $take_entity_1 = false;
    public static $itemtype_2    = 'itemtype';
    public static $items_id_2    = 'items_id';
    public static $take_entity_2 = true;


    /**
     * Get the standard massive actions which are forbidden
     *
     * @return array of massive actions
     **@since version 0.84
     *
     */
    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        $forbidden[] = 'add_note';
        return $forbidden;
    }

    public static function getIcon()
    {
        return "ti ti-lock";
    }

    /**
     * Clean table when item is purged
     *
     * @param CommonDBTM|object $item Object to use
     *
     * @return void
     */
    public static function cleanForItem(CommonDBTM $item)
    {

        $temp = new self();
        $temp->deleteByCriteria(
            ['itemtype' => $item->getType(),
                'items_id' => $item->getField('id')]
        );
    }

    /**
     * Get Tab Name used for itemtype
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @param CommonDBTM|CommonGLPI $item CommonDBTM object for which the tab need to be displayed
     * @param bool|int              $withtemplate boolean  is a template object ? (default 0)
     *
     * @return string tab name
     * @since version 0.83
     *
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if (!$withtemplate) {
            if ($item->getType() == Account::class
                && count(Account::getTypes(false))
            ) {
                if ($_SESSION['glpishow_count_on_tabs']) {
                    return self::createTabEntry(
                        _n('Associated item', 'Associated items', 2),
                        self::countForAccount($item)
                    );
                }
                return _n('Associated item', 'Associated items', 2);

            } elseif (in_array($item->getType(), Account::getTypes(true))
                       && Session::haveRight("plugin_accounts", READ)
            ) {
                if ($_SESSION['glpishow_count_on_tabs']) {
                    return self::createTabEntry(
                        Account::getTypeName(2),
                        self::countForItem($item)
                    );
                }
                return Account::getTypeName(2);
            }
        }
        return '';
    }

    /**
     * show Tab content
     *
     * @param          $item                  CommonGLPI object for which the tab need to be displayed
     * @param          $tabnum       integer  tab number (default 1)
     * @param bool|int $withtemplate boolean  is a template object ? (default 0)
     *
     * @return true
     * @since version 0.83
     *
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if (!$item instanceof CommonDBTM) {
            return false;
        }

        if ($item instanceof Account) {
            return self::showForAccount($item, $withtemplate);

        }
        if (Account::canView()
            && in_array($item->getType(), Account::getTypes(true))) {

            //            self::showForItem($item);
            return self::showForAsset($item);

        }
        return false;
    }


    /**
     * @param Account $item
     *
     * @return int
     */
    private static function countForAccount(Account $item)
    {

        if (count($item->getTypes()) <= 0) {
            return 0;
        }
        $dbu = new DbUtils();
        return $dbu->countElementsInTable(
            'glpi_plugin_accounts_accounts_items',
            ["plugin_accounts_accounts_id" => $item->getID(),
                "itemtype"                    => $item->getTypes(),
            ]
        );
    }


    /**
     * @param CommonDBTM $item
     *
     * @return int
     */
    public static function countForItem(CommonDBTM $item)
    {
        $dbu = new DbUtils();
        return $dbu->countElementsInTable(
            'glpi_plugin_accounts_accounts_items',
            ["itemtype" => $item->getType(),
                "items_id" => $item->getID()]
        );
    }


    /**
     * @param $values
     */
    public function addItem($values)
    {

        $this->add(['plugin_accounts_accounts_id' => $values['plugin_accounts_accounts_id'],
            'items_id'                    => $values['items_id'],
            'itemtype'                    => $values['itemtype']]);

    }

    /**
     * @param $plugin_accounts_accounts_id
     * @param $items_id
     * @param $itemtype
     *
     * @return bool
     */
    public function deleteItemByAccountsAndItem($plugin_accounts_accounts_id, $items_id, $itemtype)
    {


        if ($this->getFromDBByCrit(['plugin_accounts_accounts_id' => $plugin_accounts_accounts_id,
            'items_id' => $items_id,
            'itemtype' => $itemtype])) {
            $this->delete(['id' => $this->fields["id"]]);
            return true;
        }
        return false;
    }


    /**
     * Print the HTML array for Items linked to a account
     *
     * @param Account $account
     * @param int $withtemplate
     *
     * @return bool
     **/
    public static function showForAccount(Account $account, int $withtemplate = 0): bool
    {
        $instID = $account->getID();

        if (!$account->can($instID, READ)) {
            return false;
        }
        $canedit = $account->canEdit($instID);
        $rand    = mt_rand();

        $types_iterator = self::getDistinctTypes($instID);

        $totalnb = 0;
        $entity_names_cache = [];
        $entries = [];
        $used = [];

        foreach ($types_iterator as $row) {
            $itemtype = $row['itemtype'];
            if (!($item = getItemForItemtype($itemtype)) || !$item::canView()) {
                continue;
            }

            $itemtype_name = $item::getTypeName(1);
            $iterator = self::getTypeItems($instID, $itemtype);
            $nb = count($iterator);

            foreach ($iterator as $data) {
                $name = $data[$itemtype::getNameField()];
                if (
                    $_SESSION["glpiis_ids_visible"]
                    || empty($data[$itemtype::getNameField()])
                ) {
                    $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
                }
                $link     = $item::getFormURLWithID($data['id']);
                $namelink = "<a href=\"" . htmlescape($link) . "\">" . htmlescape($name) . "</a>";

                if (!isset($entity_names_cache[$data['entity']])) {
                    $entity_names_cache[$data['entity']] = Dropdown::getDropdownName("glpi_entities", $data['entity']);
                }

                $entries[] = [
                    'itemtype' => self::class,
                    'id' => $data['linkid'],
                    'row_class' => (isset($data['is_deleted']) && $data['is_deleted']) ? 'table-deleted' : '',
                    'type' => $itemtype_name,
                    'name' => $namelink,
                    'entity' => $entity_names_cache[$data['entity']],
                    'serial' => $data["serial"] ?? '-',
                    'otherserial' => $data["otherserial"] ?? '-',
                ];
                $used[$itemtype][$data['id']] = $data['id'];
            }
            $totalnb += $nb;
        }

        $columns = [
            'type' => _n('Type', 'Types', 1),
        ];
        if (Session::isMultiEntitiesMode()) {
            $columns['entity'] = Entity::getTypeName(1);
        }
        $columns += [
            'name' => __('Name'),
            'serial' => __('Serial number'),
            'otherserial' => __('Inventory number'),
        ];
        $formatters = [
            'name' => 'raw_html',
        ];
        $footers = [];
        if ($totalnb > 0) {
            $footers = [
                [sprintf(__('%1$s = %2$s'), __('Total'), $totalnb)],
            ];
        }

        TemplateRenderer::getInstance()->display('@accounts/item_account.html.twig', [
            'item' => $account,
            'can_edit' => $canedit && $withtemplate != 2,
            'withtemplate' => $withtemplate,
            'used' => $used,
            'types' => Account::getTypes(true),
            'datatable_params' => [
                'is_tab' => true,
                'nofilter' => true,
                'nosort' => true,
                'columns' => $columns,
                'formatters' => $formatters,
                'entries' => $entries,
                'footers' => $footers,
                'total_number' => count($entries),
                'filtered_number' => count($entries),
                'showmassiveactions' => $canedit,
                'massiveactionparams' => [
                    'container' => 'massiveactioncontainer' . $rand,
                    'itemtype'  => self::class,
                ],
            ],
        ]);

        return true;
    }


    private static function showForAsset(CommonDBTM $item): bool
    {
        global $DB;

        $used = $entries = [];

        $who          = Session::getLoginUserID();
        if (count($_SESSION["glpigroups"])
            && Session::haveRight("plugin_accounts_my_groups", 1)
        ) {
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

            $ASSIGN = ['OR' => [
                'groups_id'  => $groups,
                'users_id'    => $who,
            ],
            ];

        } else { // Only personal ones
            $ASSIGN = ['users_id' => $who];
        }

        $criteria = [
            'SELECT' => ['glpi_plugin_accounts_accounts_items.id AS assocID',
                'glpi_entities.id AS entity',
                'glpi_plugin_accounts_accounts.name AS assocName',
                'glpi_plugin_accounts_accounts.*'],
            'FROM' => 'glpi_plugin_accounts_accounts_items',
            'LEFT JOIN'       => [
                'glpi_plugin_accounts_accounts' => [
                    'ON' => [
                        'glpi_plugin_accounts_accounts_items' => 'plugin_accounts_accounts_id',
                        'glpi_plugin_accounts_accounts'          => 'id',
                    ],
                ],
                'glpi_entities' => [
                    'ON' => [
                        'glpi_plugin_accounts_accounts' => 'entities_id',
                        'glpi_entities'          => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_plugin_accounts_accounts_items.items_id' => $item->getID(),
                'glpi_plugin_accounts_accounts_items.itemtype' => $item->getType(),
            ],
            'ORDERBY'   => 'assocName',
        ];
        $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
            'glpi_plugin_accounts_accounts',
            '',
            '',
            true
        );

        if (!Session::haveRight("plugin_accounts_see_all_users", 1)) {
            $criteria['WHERE'] = $criteria['WHERE'] + $ASSIGN;
        }
        $iterator_list = $DB->request($criteria);


        //hash
//        $hashclass = new Hash();
//        $hash_id   = 0;
//        $hash      = 0;
//        $restrict  = getEntitiesRestrictCriteria(
//            "glpi_plugin_accounts_hashes",
//            '',
//            $item->getEntityID(),
//            $hashclass->maybeRecursive()
//        );
//
//        $hashes    = getAllDataFromTable("glpi_plugin_accounts_hashes", $restrict);
//        if (!empty($hashes)) {
//            foreach ($hashes as $hashe) {
//                $hash    = $hashe["hash"];
//                $hash_id = $hashe["id"];
//            }
//        } else {
//            $alert = __s('There is no encryption key for this entity', 'accounts');
//            echo "<div class='alert alert-warning d-flex'>";
//            echo $alert;
//            echo "</div>";
//            return false;
//        }

//        else {
//            $alert = __s('There is no encryption key for this entity', 'accounts');
//            echo "<div class='alert alert-warning d-flex'>";
//            echo $alert;
//            echo "</div>";
//            return false;
//        }

        foreach ($iterator_list as $value) {
            $used[] = $value['id'];
            $account = new Account();

            $accountID = $value['id'];
            $result = $account->getFromDB($value['id']);

            if ($result === false || !$account->can($account->getID(), READ)) {
                continue;
            }
            $hash_id = $account->fields['plugin_accounts_hashes_id'];
            $aeskey = new AesKey();
            if ($hash_id) {
                $aeskey_uncrypted = false;
                if ($aeskey->getFromDBByCrit(['plugin_accounts_hashes_id'  => $hash_id])
                    && $aeskey->fields["name"]) {
                    $aeskey_uncrypted = $aeskey->fields["name"];
                }
            }
            $hash = "";
            $hashclass= new Hash();
            if ($hashclass->getFromDB($hash_id)) {
                $hash = $hashclass->fields['hash'];
            }

            $rand = mt_rand();
            $entries[] = [
                'itemtype' => self::class,
                'id' => $value['assocID'],
                'name' => $account->getLink(),
                'entities_id' => Dropdown::getDropdownName("glpi_entities", $account->fields['entities_id']),
                'login' => $account->fields['login'],
                'decrypt_password' => [
                    'content' => __s('Uncrypt', 'accounts'),
                    'button-id' => "decrypt_link$accountID$rand",
                    'button-name' => 'decrypte',
                    'good_hash' => $hash,
                    'rand' => $rand,
                    'accountID' => $accountID,
                    'button-onclick' => "decryptCheckbtn$rand()",
                    'hidden-value' => $account->fields["encrypted_password"],
                    'hidden-id' => "encrypted_password",
                    'hidden-name' => "encrypted_password",
                    'aeskey_uncrypted' => $aeskey_uncrypted,
                ],
                'users_id' => getUserName($account->fields['users_id']),
                'plugin_accounts_accounttypes_id' => Dropdown::getDropdownName(
                    "glpi_plugin_accounts_accounttypes",
                    $account->fields["plugin_accounts_accounttypes_id"]
                ),
                'date_creation' => $account->fields['date_creation'],
                'date_expiration' => $account->fields['date_expiration'],
            ];
        }

        $cols = [
            'columns' => [
                "name" => __('Name'),
                "entities_id" => __s('Entity'),
                "login" => __s('Login'),
//                'aeskey' => __s('Encryption key', 'accounts'),
//                "good_hash" => __s(''),
//                "encrypted_password" => __s(''),
                "decrypt_password" => __s('Password'),
                "users_id" => __s('Affected User', 'accounts'),
                "plugin_accounts_accounttypes_id" => __s('Type'),
                "date_creation" => __s('Creation date'),
                "date_expiration" => __('Expiration date'),
            ],
            'formatters' => [
                'name' => 'raw_html',
                'entities_id' => 'raw_html',
                'login' => 'raw_html',
//                'aeskey' => 'password',
//                'good_hash' => 'hidden',
                'decrypt_password' => 'button',
//                'encrypted_password' => 'hidden',
                'users_id' => 'raw_html',
                'plugin_accounts_accounttypes_id' => 'raw_html',
                'date_creation' => 'date',
                'date_expiration' => 'date',
            ],
        ];


        $footers = [];

        TemplateRenderer::getInstance()->display('@accounts/item_account.html.twig', [
            'item' => $item,
            'can_edit' => $item->canEdit($item->getID()),
            'used' => $used,
            'datatable_params' => [
                'is_tab' => true,
                'nofilter' => true,
                'nosort' => true,
                'columns' => $cols['columns'],
                'formatters' => $cols['formatters'],
                'entries' => $entries,
                'footers' => $footers,
                'total_number' => count($entries),
                'filtered_number' => count($entries),
                'alert_encryption' => __s('Wrong encryption key', 'accounts'),
                'showmassiveactions' => $item->canEdit($item->getID()),
                'massiveactionparams' => [
                    'container' => 'massiveactioncontainer' . $rand,
                    'itemtype'  => self::class,
                ],
            ],
        ]);

        return true;
    }
    /**
     * Return visibility SQL restriction to add
     *
     * @return string restrict to add
     **/
    public static function addVisibilityRestrict()
    {
        //not deprecated because used in Search

        //get and clean criteria
        $criteria = KnowbaseItem::getVisibilityCriteria();
        unset($criteria['LEFT JOIN']);
        $criteria['FROM'] = self::getTable();

        $it = new \DBmysqlIterator(null);
        $it->buildQuery($criteria);
        $sql = $it->getSql();
        $sql = preg_replace('/.*WHERE /', '', $sql);

        return $sql;
    }

    /**
     * Return visibility joins to add to SQL
     *
     * @param $forceall force all joins (false by default)
     *
     * @return string joins to add
     **/
    public static function addVisibilityJoins($forceall = false)
    {
        //not deprecated because used in Search
        /** @var \DBmysql $DB */
        global $DB;

        //get and clean criteria
        $criteria = self::getVisibilityCriteria();
        unset($criteria['WHERE']);
        $criteria['FROM'] = self::getTable();

        $it = new \DBmysqlIterator(null);
        $it->buildQuery($criteria);
        $sql = $it->getSql();
        $sql = trim(str_replace(
            'SELECT * FROM ' . $DB->quoteName(self::getTable()),
            '',
            $sql
        ));
        return $sql;
    }
}
