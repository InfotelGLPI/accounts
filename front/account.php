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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Accounts\Account;
use GlpiPlugin\Servicecatalog\Main;

if (Session::getCurrentInterface() == 'central') {

    Html::header(
        Account::getTypeName(2),
        $_SERVER["PHP_SELF"],
        "admin",
        Account::class,
        "accounts",
    );
} else {
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Account::getTypeName(2), true);
    } else {
        Html::helpHeader(Account::getTypeName(2));
    }
}

$account = new Account();
$account->checkGlobal(READ);

if ($account->canView()) {
    if (Session::haveRight("plugin_accounts_see_all_users", 1)) {
        // The modal markup and the script that opens it come from the core helper: ask for
        // the string, so the template stays in charge of the layout.
        $modal = (string) Ajax::createIframeModalWindow(
            'seetypemodal',
            PLUGIN_ACCOUNTS_WEBDIR . "/ajax/accounttree.php",
            [
                'title' => __('Type view', 'accounts'),
                'display' => false,
                // createIframeModalWindow() accepts width/height but never emits them: the
                // iframe is hardcoded to height="400". Only the dialog class reaches the
                // markup, the rest of the sizing is done in accounts.css.
                'dialog_class' => 'modal-xl modal-dialog-centered plugin_accounts_tree_dialog',
            ],
        );

        TemplateRenderer::getInstance()->display('@accounts/account_tree_button.html.twig', [
            'label' => __('Type view', 'accounts'),
            'modal' => $modal,
        ]);
    }

    Account::showAccountsWithoutHash();

    Search::show(Account::class);
} else {
    throw new AccessDeniedHttpException();
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {

    Main::showNavBarFooter('accounts');
}

if (Session::getCurrentInterface() == 'central') {
    Html::footer();
} else {
    Html::helpFooter();
}
