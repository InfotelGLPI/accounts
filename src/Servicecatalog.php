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

use CommonGLPI;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


class Servicecatalog extends CommonGLPI
{
    public static $rightname = 'plugin_accounts';

    public $dohistory = false;

    public static function canUse()
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * @return string
     */
    public static function getMenuLink()
    {

        return PLUGIN_ACCOUNTS_WEBDIR . "/front/account.php";
    }

    /**
     * @return string
     */
    public static function getNavBarLink()
    {

        return PLUGIN_ACCOUNTS_WEBDIR . "/front/account.php";
    }

    public static function getMenuLogo()
    {

        return Account::getIcon();
    }

    /**
     * @return string
     * @throws \GlpitestSQLError
     */
    public static function getMenuLogoCss()
    {

        $addstyle = "font-size: 4.5em;";
        return $addstyle;
    }

    public static function getMenuTitle()
    {

        return __s('See your accounts', 'accounts');
    }


    public static function getMenuComment()
    {

        return __s('See your accounts', 'accounts');
    }

    public static function getLinkList()
    {
        return "";
    }

    public static function getList()
    {
        return "";
    }
}
