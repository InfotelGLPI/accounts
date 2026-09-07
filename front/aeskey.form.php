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
use GlpiPlugin\Accounts\AesKey;
use GlpiPlugin\Accounts\Hash;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}
if (!isset($_GET["plugin_accounts_hashes_id"])) {
    $_GET["plugin_accounts_hashes_id"] = "";
}

Session::checkRight("plugin_accounts_hash", UPDATE);

$aeskey = new AesKey();

// AesKey rows carry no entities_id of their own: they are scoped through the parent Hash
// (plugin_accounts_hashes_id). The plugin_accounts_hash UPDATE right can be recursive/global,
// so relying only on Session::checkRight()/canCreate() lets a user in entity A target an AesKey
// id (or a hash id) belonging to entity B by tampering with the POST (IDOR). Every write must be
// pinned to the entity of the parent hash — same guard as front/hash.form.php:69 and
// ajax/getHashOnSelectEncryptionKey.php.
// The two predicates now live on AesKey so that the massive actions and the Hash tab go through
// the same rule; these closures only turn a false into the HTTP answer this front expects.
$assertHashEntityAccess = static function (int $hashes_id): void {
    if (!AesKey::isHashReachable($hashes_id)) {
        throw new AccessDeniedHttpException();
    }
};
$assertAesKeyEntityAccess = static function (int $aeskeys_id): void {
    if (!AesKey::isReachable($aeskeys_id)) {
        throw new AccessDeniedHttpException();
    }
};

Html::header(Account::getTypeName(2), '', "admin", Account::class, "hash");

if (isset($_POST["add"])) {
    if ($aeskey->canCreate()) {
        // Bind the new key to an entity the caller can reach (target hash's entity).
        $assertHashEntityAccess((int) ($_POST["plugin_accounts_hashes_id"] ?? 0));
        $newID = $aeskey->add($_POST);
        unset($_SESSION['MESSAGE_AFTER_REDIRECT']);
        Session::addMessageAfterRedirect(__s('Encryption key saved', 'accounts'), true);
    }
    if ($_SESSION['glpibackcreated']) {
        Html::redirect($aeskey->getFormURL() . "?id=" . $newID);
    }
    Html::back();
} elseif (isset($_POST["update"])) {
    if ($aeskey->canCreate()) {
        // Existing row must belong to a reachable entity...
        $assertAesKeyEntityAccess((int) ($_POST["id"] ?? 0));
        // ...and so must the target hash if the update tries to move the key to another hash.
        if (isset($_POST["plugin_accounts_hashes_id"])) {
            $assertHashEntityAccess((int) $_POST["plugin_accounts_hashes_id"]);
        }
        $aeskey->update($_POST);
    }
    Html::back();
} elseif (isset($_POST["delete"])) {
    if ($aeskey->canCreate()) {
        foreach ($_POST["check"] as $ID => $value) {
            $assertAesKeyEntityAccess((int) $ID);
            $aeskey->delete(["id" => (int) $ID], 1);
        }
    }
    Html::back();
} elseif (isset($_POST["purge"])) {
    if ($aeskey->canCreate()) {
        $assertAesKeyEntityAccess((int) ($_POST["id"] ?? 0));
        $aeskey->delete(["id" => (int) $_POST["id"]], 1);
    }
    $aeskey->redirectToList();
} else {
    // display() runs a can($id, READ), but AesKey carries no entities_id, so the checkEntity()
    // behind it is a no-op and only the global plugin_accounts_hash right is left. Without the
    // same closure the four write branches use, a user of entity A could open the form of a key
    // of entity B and learn which fingerprint it belongs to -- the key itself stays hidden, the
    // template renders the field empty on purpose.
    $id                        = (int) $_GET['id'];
    $plugin_accounts_hashes_id = (int) $_GET['plugin_accounts_hashes_id'];
    if ($id > 0) {
        $assertAesKeyEntityAccess($id);
    } elseif ($plugin_accounts_hashes_id > 0) {
        $assertHashEntityAccess($plugin_accounts_hashes_id);
    }
    $aeskey->display(['id' => $id,
        'plugin_accounts_hashes_id' => $plugin_accounts_hashes_id]);
}

Html::footer();
