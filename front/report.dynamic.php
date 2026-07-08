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

use GlpiPlugin\Accounts\Report;

Session::checkCentralAccess();

Session::checkRight("plugin_accounts", READ);

if (isset($_POST["display_type"])) {
    if ($_POST["display_type"] < 0) {
        $_POST["display_type"] = -$_POST["display_type"];
        $_POST["export_all"] = 1;
    }

    // Re-query from the database using the hash ID and AES key already present
    // in the pager form. The previous approach passed all account data through
    // per-row hidden inputs, hitting PHP's max_input_vars=1000 limit and
    // silently truncating the list at ~142 rows in multi-entities mode.
    $parm = [
        "display_type" => $_POST["display_type"],
        "id"           => $_POST["hash_id"],
        "aeskey"       => $_POST["aeskey"],
        "itemtype"     => $_POST["itemtype"],
    ];

    $reindexed = Report::queryAccountsList($parm);

    Report::showAccountsList($parm, $reindexed);
}
