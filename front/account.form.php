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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Accounts\Account_Item;
use GlpiPlugin\Servicecatalog\Main;

if (!isset($_GET["id"])) {
    $_GET["id"] = 0;
}
if (!isset($_GET["withtemplate"])) {
    $_GET["withtemplate"] = "";
}

$account      = new Account();
$account_item = new Account_Item();

if (isset($_POST["add"])) {
    $account->check(-1, CREATE, $_POST);
    $newID = $account->add($_POST);
    if ($_SESSION['glpibackcreated']) {
        Html::redirect($account->getFormURL() . "?id=" . $newID);
    }
    Html::back();
} elseif (isset($_POST["update"])) {
    $account->check($_POST['id'], UPDATE);
    $account->update($_POST);
    Html::back();
} elseif (isset($_POST["delete"])) {
    $account->check($_POST['id'], DELETE);
    $account->delete($_POST);
    $account->redirectToList();
} elseif (isset($_POST["restore"])) {
    $account->check($_POST['id'], PURGE);
    $account->restore($_POST);
    $account->redirectToList();
} elseif (isset($_POST["purge"])) {
    $account->check($_POST['id'], PURGE);
    $account->delete($_POST, 1);
    $account->redirectToList();
} elseif (isset($_POST["additem"])) {
    if (!empty($_POST['itemtype'])
        && $_POST['items_id'] > 0
        && $_POST['plugin_accounts_accounts_id'] > 0) {
        // Object-level check: the caller must be able to READ the target account
        // before linking it to an item. Without this, a forged POST could attach
        // an account outside the caller's visibility (group/user scoping) — same
        // guard as ajax/log_decrypt.php.
        if (!$account->can((int) $_POST['plugin_accounts_accounts_id'], READ)) {
            throw new AccessDeniedHttpException();
        }
        $account_item->check(-1, CREATE, $_POST);
        $account_item->addItem($_POST);
    }
    Html::back();
} elseif (isset($_POST["deleteitem"])) {
    // A form submitted with no box ticked posts "deleteitem" and nothing else, and PHP 8
    // turns the resulting foreach into a TypeError -- fatal under GLPI_STRICT_ENV. The rights
    // are checked per row below; this only decides whether there is a row at all.
    foreach ((array) ($_POST["item"] ?? []) as $key => $val) {
        $input = ['id' => $key];
        if ($val == 1) {
            $account_item->check($key, UPDATE);
            $account_item->delete($input);
        }
    }

    Html::back();

    //from items ?
} elseif (isset($_POST["deleteaccounts"])) {
    $input = ['id' => $_POST["id"]];
    $account_item->check($_POST["id"], UPDATE);
    $account_item->delete($input);
    Html::back();
} else {
    $account->checkGlobal(READ);

    if (Session::getCurrentInterface() == 'central') {
        Html::header(Account::getTypeName(2), '', "admin", Account::class);
    } else {
        if (Plugin::isPluginActive('servicecatalog')) {
            Main::showDefaultHeaderHelpdesk(Account::getTypeName(2), true);
        } else {
            Html::helpHeader(Account::getTypeName(2));
        }
    }

    // The ownership rule is enforced by Account::canViewItem(), replayed by check() here as
    // well as by every other entry point (tabs, PDF export, massive actions).
    $account->check($_GET['id'], READ);
    $account->display(['id' => $_GET['id']]);

    if (Session::getCurrentInterface() != 'central'
        && Plugin::isPluginActive('servicecatalog')) {
        Main::showNavBarFooter('accounts');
    }

    if (Session::getCurrentInterface() == 'central') {
        Html::footer();
    } else {
        Html::helpFooter();
    }
}
