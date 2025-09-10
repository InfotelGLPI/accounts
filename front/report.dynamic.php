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

use GlpiPlugin\Accounts\Report;

Session::checkCentralAccess();

if (isset($_POST["display_type"])) {
    if ($_POST["display_type"] < 0) {
        $_POST["display_type"] = -$_POST["display_type"];
        $_POST["export_all"] = 1;
    }

    $post = $_POST;

    $parm["display_type"] = $post["display_type"];
    $parm["id"] = $post["hash_id"];
    $parm["aeskey"] = $post["aeskey"];
    $parm["item_type"] = $post["item_type"];

    $accounts = [];
    foreach ($post["id"] as $k => $v) {
        $accounts[$k]["id"] = $v;
    }
    $accounts[$k]["name"] = [];
    if (isset($post["name"]) && is_array($post["name"])) {
        foreach ($post["name"] as $k => $v) {
            $accounts[$k]["name"] = $v;
        }
    }
    $accounts[$k]["entities_id"] = [];
    if (isset($post["entities_id"]) && is_array($post["entities_id"])) {
        foreach ($post["entities_id"] as $k => $v) {
            $accounts[$k]["entities_id"] = $v ?? 0;
        }
    }
    $accounts[$k]["type"] = [];
    if (isset($post["type"]) && is_array($post["type"])) {
        foreach ($post["type"] as $k => $v) {
            $accounts[$k]["type"] = $v;
        }
    }
    $accounts[$k]["login"] = [];
    if (isset($post["login"]) && is_array($post["login"])) {
        foreach ($post["login"] as $k => $v) {
            $accounts[$k]["login"] = $v;
        }
    }
    $accounts[$k]["password"] = [];
    if (isset($post["password"]) && is_array($post["password"])) {
        foreach ($post["password"] as $k => $v) {
            $accounts[$k]["password"] = $v;
        }
    }

    Report::showAccountsList($parm, $accounts);
}
