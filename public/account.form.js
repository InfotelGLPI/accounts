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

var root_accounts_doc = CFG_GLPI.root_doc + '/plugins/accounts';

var auto_decrypt = function (suffix) {
    suffix = suffix || "";
    if (!check_hash()) {
        $("#hidden_password" + suffix).val($("#wrong_key_locale").val());
    } else {
        decrypt_password(root_accounts_doc, suffix);
        decrypt_totp_secret(suffix);
    }
};

var uncryptpassword = async function (suffix) {
    if (!check_hash()) {
        var value = document.getElementById('wrong_key_locale').value;
        document.getElementById('wrong_key_locale_div').textContent = value;
    } else {
        document.getElementById('wrong_key_locale_div').textContent = '';
        await decrypt_password(root_accounts_doc, suffix);
        decrypt_totp_secret(suffix);
    }
};

var decrypt_totp_secret = function (suffix) {
    suffix = suffix || "";
    var aeskey = $("#aeskey" + suffix).val();
    var encrypted = $("#encrypted_totp_secret" + suffix).val();
    if (!encrypted || !aeskey) return;

    var decrypted = '';
    if (encrypted.startsWith('$v2$')) {
        decrypted = decryptV2(encrypted, aeskey);
    } else if (typeof AESDecryptCtr === 'function') {
        decrypted = AESDecryptCtr(encrypted, SHA256(aeskey), 256);
    } else {
        decrypted = encrypted;
    }

    if ($("#hidden_totp_secret" + suffix).length) {
        $("#hidden_totp_secret" + suffix).val(decrypted);
    }
};

var checkInputIfNewEncryptionKey = function (newValue, currentValue) {
    if (currentValue > 0 && newValue != currentValue) {
        $('#alertfootprint').show();
    }
};
