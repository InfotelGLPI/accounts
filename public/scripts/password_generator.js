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


/**
 * Password generator of the account form (#generatePass in templates/account.html.twig).
 *
 * Runs fully in the browser: the generated password only lands in #hidden_password, which the
 * form encrypts before submit, so it never travels in clear.
 */
const CHARSETS = [
    '0123456789',
    'abcdefghijklmnopqrstuvwxyz',
    'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    '!"#$%&\'()*+,-./:;<=>?@[]^_`{|}~' + String.fromCharCode(92),
];

// Cryptographically secure, unbiased index in [0, n) via rejection sampling.
function randomInt(n) {
    const limit = Math.floor(0x100000000 / n) * n;
    const buf = new Uint32Array(1);
    do {
        window.crypto.getRandomValues(buf);
    } while (buf[0] >= limit);
    return buf[0] % n;
}

function generatePassword(button) {
    const chars = [...new Set(CHARSETS
        .filter((charset, i) => document.getElementById(`char-${i}`)?.checked)
        .join(''))];
    if (chars.length === 0) {
        window.alert(button.getAttribute('data-accounts-empty-message'));
        return;
    }

    const length = parseInt(document.getElementById('length')?.value, 10) || 0;
    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars[randomInt(chars.length)];
    }

    const target = document.getElementById('hidden_password');
    if (target !== null) {
        target.value = result;
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('#generatePass');
    if (button !== null) {
        generatePassword(button);
    }
});
