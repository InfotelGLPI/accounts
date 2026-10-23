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

use GlpiPlugin\Accounts\Account;

Html::header_nocache();

Session::checkRight("plugin_accounts", READ);

global $CFG_GLPI;

// $_GET['target'] is reflected into an HTML attribute by showSelector(). Only accept an
// internal same-origin path (prefixed by the GLPI web root, no character able to break out
// of the attribute) and fall back to the default otherwise, to prevent a reflected XSS /
// open redirect through an attacker-supplied target.
if (
    !isset($_GET['target'])
    || !str_starts_with((string) $_GET['target'], $CFG_GLPI['root_doc'] . '/')
    || preg_match('/[\s"\'<>]/', (string) $_GET['target'])
) {
    $_GET['target'] = Toolbox::getItemTypeSearchURL(Account::class);
}

// The page is loaded in the iframe of a modal: emit the standard modal document so the tree
// inherits the whole GLPI stylesheet (Tabler) and the core bundles carrying fancytree,
// instead of rendering a bare fragment in quirks mode.
Html::popHeader(__('Type view', 'accounts'), '', true);

Account::showSelector($_GET['target']);

Html::popFooter();
