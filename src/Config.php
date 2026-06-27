<?php

/*
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2015-2026 by the accounts Development Team.

 https://github.com/InfotelGLPI/accounts
 -------------------------------------------------------------------------

 LICENSE

 This file is part of accounts.

 accounts is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
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
use CommonGLPI;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Config
 */
class Config extends CommonDBTM
{
    public static $rightname = "config";
    /**
     * @param CommonGLPI $item
     * @param int $withtemplate
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if ($item->getType() == 'CronTask'
            && $item->getField('name') == "AccountsAlert") {
            return self::createTabEntry(__s('Plugin Setup', 'accounts'));
        }
        return '';
    }

    public static function getIcon()
    {
        return "ti ti-lock";
    }

    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == 'CronTask') {
            $target = PLUGIN_ACCOUNTS_WEBDIR . "/front/notification.state.php";
            Account::configCron($target);
        }
        return true;
    }

    /**
     * @param $target
     * @param $ID
     */
    public function showConfigForm($target)
    {

        if (!$this->canCreate()) {
            return false;
        }

        $canedit = true;

        if ($canedit) {
            $ID = 1;
            $this->getFromDB($ID);
            $delay_expired = $this->fields["delay_expired"];
            $delay_whichexpire = $this->fields["delay_whichexpire"];
            $delay_stamp_first = mktime(0, 0, 0, date("m"), date("d") - $delay_expired, date("y"));
            $delay_stamp_next = mktime(0, 0, 0, date("m"), date("d") + $delay_whichexpire, date("y"));
            $date_first = date("Y-m-d", $delay_stamp_first);
            $date_next = date("Y-m-d", $delay_stamp_next);

            TemplateRenderer::getInstance()->display(
                '@accounts/config.html.twig',
                [
                    'id'                => 1,
                    'item'              => $this,
                    'config'            => $this->fields,
                    'action'            => $target,
                    'date_first'            => Html::convDate($date_first),
                    'date_next'            => Html::convDate($date_next),
                ],
            );
        }
        return true;
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL auto_increment,
                        `delay_expired` varchar(50) collate utf8mb4_unicode_ci NOT NULL default '30',
                        `delay_whichexpire` varchar(50) collate utf8mb4_unicode_ci NOT NULL default '30',
                        PRIMARY KEY  (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $DB->insert(
                $table,
                ['id' => 1,
                    'delay_expired' => 30,
                    'delay_whichexpire' => 30]
            );
        }
    }
}
