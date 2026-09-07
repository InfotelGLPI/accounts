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

use GlpiPlugin\Accounts\Hash;

header("Content-Type: text/plain; charset=UTF-8");
Html::header_nocache();

Session::checkRight("plugin_accounts_hash", READ);

if (isset($_POST["plugin_accounts_hashes_id"])) {
    $hashKey = new Hash();
    $hash_id = (int) $_POST["plugin_accounts_hashes_id"];
    // The recursive flag has to travel with the entity, otherwise a fingerprint shared down
    // the tree is refused when the form is opened from a child entity. Same call shape as
    // Report::loadReachableHash().
    if ($hashKey->getFromDB($hash_id)
        && Session::haveAccessToEntity(
            $hashKey->fields['entities_id'] ?? 0,
            (bool) ($hashKey->fields['is_recursive'] ?? false),
        )) {
        // Handing out the verifier is what the zero-knowledge design is built on: the browser
        // needs it to tell a mistyped key from a wrong one before attempting a decryption.
        // It is offline attack material all the same, which is why the salted PBKDF2 format
        // replaced the unsalted double SHA-256 -- see the legacy marker on the Hash form.
        echo $hashKey->fields['hash'];
    }
}
