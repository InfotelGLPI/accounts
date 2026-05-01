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
        if (getItemForItemtype($values['itemtype']) === false) {
            return false;
        }

        return $this->add(['plugin_accounts_accounts_id' => $values['plugin_accounts_accounts_id'],
            'items_id'                    => (int) $values['items_id'],
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
        global $DB;

        $instID = $account->getID();

        if (!$account->can($instID, READ)) {
            return false;
        }
        $canedit = $account->canEdit($instID);
        $rand    = mt_rand();

        // Une seule requête pour tous les liens — remplace getDistinctTypes + JOIN par type
        $link_where = [self::getTable() . '.' . static::$items_id_1 => $instID];
        if ($DB->fieldExists(self::getTable(), 'is_deleted')) {
            $link_where[self::getTable() . '.is_deleted'] = 0;
        }

        $links_by_type = [];
        foreach ($DB->request(['SELECT' => ['itemtype', 'items_id', 'id AS linkid'], 'FROM' => self::getTable(), 'WHERE' => $link_where]) as $link) {
            $links_by_type[$link['itemtype']][$link['items_id']] = $link['linkid'];
        }

        $totalnb = 0;
        $entity_names_cache = [];
        $entries = [];
        $used = [];

        foreach ($links_by_type as $itemtype => $id_to_linkid) {

            $item = getItemForItemtype($itemtype);

            if (!$item || !$item::canView()) {
                continue;
            }

            $itemtype_name = $item::getTypeName(1);
            $nameField     = $item::getNameField();

            // Requête par type avec IN — plus de JOIN vers la table de lien
            $type_criteria = [
                'SELECT' => [$item->getTable() . '.*'],
                'FROM'   => $item->getTable(),
                'WHERE'  => [$item->getTable() . '.id' => array_keys($id_to_linkid)],
                'ORDER'  => $item->getTable() . '.' . $nameField,
            ];

            if ($item->maybeTemplate()) {
                $type_criteria['WHERE'][$item->getTable() . '.is_template'] = 0;
            }

            if ($item->isEntityAssign() && $itemtype !== Entity::getType()) {
                $type_criteria['SELECT'][]                     = 'glpi_entities.id AS entity';
                $type_criteria['LEFT JOIN']['glpi_entities']   = [
                    'FKEY' => [$item->getTable() => 'entities_id', 'glpi_entities' => 'id'],
                ];
                $type_criteria['WHERE'] += getEntitiesRestrictCriteria($item->getTable(), '', '', $item->maybeRecursive());
                $type_criteria['ORDER']  = ['glpi_entities.completename', $type_criteria['ORDER']];
            }

            $nb = 0;
            foreach ($DB->request($type_criteria) as $data) {
                $nb++;
                $data['linkid'] = $id_to_linkid[$data['id']];

                $name = $data[$nameField];
                if ($_SESSION["glpiis_ids_visible"] || empty($name)) {
                    $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
                }
                $namelink = "<a href=\"" . htmlescape($item::getFormURLWithID($data['id'])) . "\">" . htmlescape($name) . "</a>";

                $entityId = $data['entity'] ?? 0;
                if (!isset($entity_names_cache[$entityId])) {
                    $entity_names_cache[$entityId] = Dropdown::getDropdownName("glpi_entities", $entityId);
                }

                $entries[] = [
                    'itemtype'    => self::class,
                    'id'          => $data['linkid'],
                    'row_class'   => (isset($data['is_deleted']) && $data['is_deleted']) ? 'table-deleted' : '',
                    'type'        => $itemtype_name,
                    'name'        => $namelink,
                    'entity'      => $entity_names_cache[$entityId],
                    'serial'      => $data["serial"] ?? '-',
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


    public static function showForAsset(CommonDBTM $item): bool
    {
        global $DB;

        $used = $entries = [];

        $criteria = [
            'SELECT' => [
                'glpi_plugin_accounts_accounts_items.id AS assocID',
                'glpi_entities.id AS entity',
                'glpi_plugin_accounts_accounts.name AS assocName',
                'glpi_plugin_accounts_accounts.*',
                'glpi_plugin_accounts_hashes.hash AS hash_value',
                'glpi_plugin_accounts_aeskeys.name AS aeskey_name',
            ],
            'FROM' => 'glpi_plugin_accounts_accounts_items',
            'LEFT JOIN' => [
                'glpi_plugin_accounts_accounts' => [
                    'ON' => [
                        'glpi_plugin_accounts_accounts_items' => 'plugin_accounts_accounts_id',
                        'glpi_plugin_accounts_accounts'       => 'id',
                    ],
                ],
                'glpi_entities' => [
                    'ON' => [
                        'glpi_plugin_accounts_accounts' => 'entities_id',
                        'glpi_entities'                 => 'id',
                    ],
                ],
                'glpi_plugin_accounts_hashes' => [
                    'ON' => [
                        'glpi_plugin_accounts_accounts' => 'plugin_accounts_hashes_id',
                        'glpi_plugin_accounts_hashes'   => 'id',
                    ],
                ],
                'glpi_plugin_accounts_aeskeys' => [
                    'ON' => [
                        'glpi_plugin_accounts_aeskeys'  => 'plugin_accounts_hashes_id',
                        'glpi_plugin_accounts_accounts' => 'plugin_accounts_hashes_id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_plugin_accounts_accounts_items.items_id' => $item->getID(),
                'glpi_plugin_accounts_accounts_items.itemtype' => $item->getType(),
            ],
            'ORDERBY' => 'assocName',
        ];

        $visibility = Account::getVisibilityCriteria();
        if (!empty($visibility)) {
            $criteria['WHERE'] = array_merge($criteria['WHERE'], $visibility);
        }

        $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
            'glpi_plugin_accounts_accounts',
            '',
            '',
            true
        );

        $iterator_list = $DB->request($criteria);
        $mtrand = mt_rand();
        foreach ($iterator_list as $value) {
            $used[] = $value['id'];

            $account = new Account();
            $account->fields = $value; //hydrated from the JOIN — avoids getFromDB()
            $accountID = $value['id'];

            if (!$account->can($accountID, READ)) {
                continue;
            }

            $entries[] = [
                'itemtype' => self::class,
                'id' => $value['assocID'],
                'name' => $account->getLink(),
                'entities_id' => Dropdown::getDropdownName("glpi_entities", $value['entities_id']),
                'login' => $value['login'],
                'decrypt_password' => [
                    'content'        => __s('Decrypt', 'accounts'),
                    'button-id'      => "decrypt_link$accountID",
                    'button-name'    => 'decrypte',
                    'good_hash'      => $value['hash_value'] ?? '',
                    'rand'           => $accountID,
                    'accountID'      => $accountID,
                    'items_id'       => $item->getID(),
                    'itemtype'       => $item->getType(),
                    'button-onclick' => "decryptCheckbtn$accountID()",
                    'hidden-value'   => $value['encrypted_password'],
                    'hidden-id'      => "encrypted_password",
                    'hidden-name'    => "encrypted_password",
                    'aeskey_uncrypted' => $value['aeskey_name'] ?? false,
                ],
                'users_id' => getUserName($value['users_id']),
                'plugin_accounts_accounttypes_id' => Dropdown::getDropdownName(
                    "glpi_plugin_accounts_accounttypes",
                    $value["plugin_accounts_accounttypes_id"]
                ),
                'date_creation'  => $value['date_creation'],
                'date_expiration' => $value['date_expiration'],
            ];
        }

        $cols = [
            'columns' => [
                "name" => __('Name'),
                "entities_id" => __s('Entity'),
                "login" => __s('Login'),
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
                'decrypt_password' => 'button',
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
                    'container' => 'massiveactioncontainer' . $mtrand,
                    'itemtype'  => self::class,
                ],
            ],
        ]);

        return true;
    }
}
