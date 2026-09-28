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

/* global generic_check_hash, decrypt_cryptogram */

/**
 * Decrypt the passwords of the linked accounts report (Report::showAccountsList).
 *
 * The list is rendered by a tab or loaded by ajax/viewaccountslist.php, so it may appear at any
 * time: try now, then on every DOM change. The plaintext only ever lands in textContent, and the
 * key attribute is removed once used so it does not linger in the DOM.
 */
function decryptReport(report) {
    const aeskey = report.getAttribute('data-accounts-aeskey') || '';
    const verifier = report.getAttribute('data-accounts-verifier') || '';
    report.removeAttribute('data-accounts-aeskey');

    // generic_check_hash (crypt.js) handles both the salted PBKDF2 format and legacy double SHA-256.
    const valid = generic_check_hash(verifier, aeskey);
    report.querySelectorAll('[data-accounts-encrypted]').forEach((cell) => {
        // decrypt_cryptogram dispatches on the version prefix; never pick the version here.
        cell.textContent = valid
            ? decrypt_cryptogram(cell.getAttribute('data-accounts-encrypted'), aeskey)
            : report.getAttribute('data-accounts-wrong-key');
    });
}

function decryptPendingReports() {
    document.querySelectorAll('[data-accounts-report][data-accounts-aeskey]').forEach(decryptReport);
}

decryptPendingReports();
new MutationObserver(decryptPendingReports).observe(document.documentElement, {
    childList: true,
    subtree: true,
});
