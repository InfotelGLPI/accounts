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


include('../../../inc/includes.php');

if (!isset($_GET["id"])) {
    $_GET["id"] = 0;
}
if (!isset($_GET["withtemplate"])) {
    $_GET["withtemplate"] = "";
}

$account      = new PluginAccountsAccount();
$account_item = new PluginAccountsAccount_Item();

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
    if (!empty($_POST['itemtype']) && $_POST['items_id'] > 0) {
        $account_item->check(-1, UPDATE, $_POST);
        $account_item->addItem($_POST);
    }
    Html::back();
} elseif (isset($_POST["deleteitem"])) {
    foreach ($_POST["item"] as $key => $val) {
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
        if (Plugin::isPluginActive("environment")) {
            Html::header(PluginAccountsAccount::getTypeName(2), '', "assets", "pluginenvironmentdisplay", "accounts");
        } else {
            Html::header(PluginAccountsAccount::getTypeName(2), '', "admin", "pluginaccountsaccount");
        }
    } else {
        Html::helpHeader(PluginAccountsAccount::getTypeName(2));
    }

    $account->check($_GET['id'], READ);
    if (isset($_GET['id'])
        && $_GET['id'] != 0
        && !Session::haveRight("plugin_accounts_see_all_users", 1)) {
        $access = 0;

        if (Session::haveRight("plugin_accounts_my_groups", 1)) {
            if ($account->fields["groups_id"]) {
                if (count($_SESSION['glpigroups'])
                    && in_array($account->fields["groups_id"], $_SESSION['glpigroups'])) {
                    $access = 1;
                }
            }
            if ($account->fields["users_id"]) {
                if ($account->fields["users_id"] == Session::getLoginUserID()) {
                    $access = 1;
                }
            }
        }
        if (!Session::haveRight("plugin_accounts_my_groups", 1)
            && $account->fields["users_id"] == Session::getLoginUserID()) {
            $access = 1;
        }

        if ($access != 1) {
            Html::displayRightError();
        } else {
            $account->display(['id' => $_GET['id']]);
        }
    } else {
        $account->display(['id' => $_GET['id']]);
    }

    if (Session::getCurrentInterface() == 'central') {
        Html::footer();
    } else {
        Html::helpFooter();
    }
}
