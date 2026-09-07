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

// Read the verifier in force on the current form: the one of the encryption key being
// selected when the user is changing it, the one stored on the account otherwise. Shared by
// check_hash() and encrypt_password() so a password is always encrypted under the very
// verifier the typed key was checked against, and so both sides derive the same keys.
function get_active_verifier(suffix) {

    suffix = suffix || "";

    var on_change_hash = $("#change_good_hash" + suffix).text();

    if (on_change_hash != '') {
        return on_change_hash;
    }

    return $("#good_hash" + suffix).val();
}

var check_hash = function (suffix) {

    suffix = suffix || "";

    var aeskey = $("#aeskey" + suffix).val();

    return generic_check_hash(get_active_verifier(suffix), aeskey);
};

// Iteration count for newly generated PBKDF2 verifiers. Kept moderate because CryptoJS runs
// PBKDF2 in pure JS; the embedded iteration count lets each verifier be checked at its own
// cost, so this can be raised later without invalidating existing verifiers. Must stay in
// sync with AccountCrypto::VERIFIER_ITERATIONS (PHP side) for verifiers it generates.
var ACCOUNTS_PBKDF2_ITERATIONS = 100000;

// Memoize verification results: check_hash() is called once per decrypted field, so without
// this a list of N accounts would run the (deliberately slow) PBKDF2 N times and freeze the UI.
// Keyed on the exact (verifier, key) pair, so a changed key is always re-checked.
var _accounts_hash_cache = {};

// Build a salted PBKDF2 verifier ($pbkdf2$<iterations>$<salt_b64>$<hex>) for a key.
// Mirror of AccountCrypto::makeVerifier() (PHP) — keep both in sync.
function generate_hash_verifier(aeskey) {
    var salt = CryptoJS.lib.WordArray.random(16);
    var derived = CryptoJS.PBKDF2(aeskey, salt, {
        keySize: 256 / 32,
        iterations: ACCOUNTS_PBKDF2_ITERATIONS,
        hasher: CryptoJS.algo.SHA256
    }).toString();
    return '$pbkdf2$' + ACCOUNTS_PBKDF2_ITERATIONS + '$'
        + CryptoJS.enc.Base64.stringify(salt) + '$'
        + derived;
}

var generic_check_hash = function (good_hash, aeskey) {
    if (!aeskey) { // covers both '' and undefined
        return false;
    }

    var cache_key = good_hash + '\0' + aeskey;
    if (Object.prototype.hasOwnProperty.call(_accounts_hash_cache, cache_key)) {
        return _accounts_hash_cache[cache_key];
    }

    var result = _compute_check_hash(good_hash, aeskey);
    _accounts_hash_cache[cache_key] = result;
    return result;
};

function _compute_check_hash(good_hash, aeskey) {
    // New salted, slow verifier: $pbkdf2$<iterations>$<salt_b64>$<hex(derived)>
    if (typeof good_hash === 'string' && good_hash.indexOf('$pbkdf2$') === 0) {
        var parts = good_hash.split('$'); // ['', 'pbkdf2', iterations, salt_b64, hex]
        var iterations = parseInt(parts[2], 10);
        var salt = CryptoJS.enc.Base64.parse(parts[3]);
        var derived = CryptoJS.PBKDF2(aeskey, salt, {
            keySize: 256 / 32,
            iterations: iterations,
            hasher: CryptoJS.algo.SHA256
        }).toString();
        return derived === parts[4];
    }

    // Legacy verifiers (bare double SHA-256), still accepted so existing keys keep working.
    // First form: computed with CryptoJS.
    var hashCryptoJS = CryptoJS.SHA256(CryptoJS.SHA256(aeskey)).toString();
    if (hashCryptoJS === good_hash) {
        return true;
    }

    // Second form: computed with the older standalone SHA256() of lightcrypt.
    if (typeof SHA256 === 'function') {
        var hashOld = SHA256(SHA256(aeskey));
        if (hashOld === good_hash) {
            return true;
        }
    }

    // No form matches.
    return false;
}

// var generic_check_hash = function (good_hash, aeskey) {
//
//     if (aeskey == '' || aeskey == undefined) {
//         return false;
//     }
//
//     var hash = SHA256(SHA256(aeskey));
//     //Prepare Hash migration - for drop lightcrypt
//     // var hash = CryptoJS.SHA256(
//     //     CryptoJS.SHA256(aeskey)
//     // ).toString();
//
//     if (hash != good_hash) {
//         return false;
//     }
//     return true;
// };

var CRYPTOGRAM_V2_PREFIX = '$v2$';
var CRYPTOGRAM_V3_PREFIX = '$v3$';
var CRYPTOGRAM_V4_PREFIX = '$v4$';

// Domain-separation suffix appended to the verifier salt before deriving the v4 keys. PBKDF2
// truncates, so deriving with the bare salt would make the encryption key start with the
// verifier itself — a value that is public, since it is shipped here to check the typed key.
// Must stay in sync with AccountCrypto::KEY_SALT_SUFFIX (PHP side).
var ACCOUNTS_KEY_SALT_SUFFIX = '|key';

// Memoize the v4 derivations, for the same reason as _accounts_hash_cache above: every record
// of one encryption key shares the salt, and decrypting a list would otherwise run the
// (deliberately slow) PBKDF2 once per row and freeze the UI.
var _accounts_key_cache = {};

// Derive the v4 encryption and MAC keys from the fingerprint: a single 64-byte PBKDF2 output
// split in two halves, so both keys cost one derivation. Mirror of AccountCrypto::deriveKeys().
// salt_b64 and iterations are carried by the cryptogram itself, so a record stays readable
// after the iteration count is raised.
function deriveKeys(fingerprint, salt_b64, iterations) {
    var cache_key = fingerprint + '\0' + salt_b64 + '\0' + iterations;
    if (Object.prototype.hasOwnProperty.call(_accounts_key_cache, cache_key)) {
        return _accounts_key_cache[cache_key];
    }

    // concat() mutates its receiver, hence the clone: the parsed salt must stay intact.
    var salt = CryptoJS.enc.Base64.parse(salt_b64).clone()
        .concat(CryptoJS.enc.Utf8.parse(ACCOUNTS_KEY_SALT_SUFFIX));

    var derived = CryptoJS.PBKDF2(fingerprint, salt, {
        keySize: 512 / 32, // 64 bytes = 16 words
        iterations: iterations,
        hasher: CryptoJS.algo.SHA256
    });

    var keys = {
        enc: CryptoJS.lib.WordArray.create(derived.words.slice(0, 8), 32),
        mac: CryptoJS.lib.WordArray.create(derived.words.slice(8, 16), 32)
    };

    _accounts_key_cache[cache_key] = keys;
    return keys;
}

// Decrypt a v4 cryptogram: $v4$<salt_b64>$<iterations>$<iv_b64>$<ct_b64>$<mac_b64>.
// The MAC is mandatory — v4 is never emitted without one, so a missing or invalid MAC means
// the record was tampered with. Mirror of AccountCrypto::decryptV4().
function decryptV4(ciphertext, fingerprint) {
    // parts[0] = version, [1] = salt_b64, [2] = iterations, [3] = iv_b64, [4] = ct_b64,
    // [5] = mac_b64
    var parts = ciphertext.replace(/^\$/, '').split('$');
    if (parts.length < 6 || parts[5] === '') {
        return '';
    }

    var iterations = parseInt(parts[2], 10);
    if (!iterations || iterations <= 0) {
        return '';
    }

    var keys = deriveKeys(fingerprint, parts[1], iterations);
    var iv = CryptoJS.enc.Base64.parse(parts[3]);
    var ct = CryptoJS.enc.Base64.parse(parts[4]);

    var msg = iv.clone().concat(ct);
    var expectedMac = CryptoJS.enc.Base64.stringify(CryptoJS.HmacSHA256(msg, keys.mac));
    if (expectedMac !== parts[5]) {
        return '';
    }

    var decrypted = CryptoJS.AES.decrypt(
        { ciphertext: ct },
        keys.enc,
        {
            iv: iv,
            mode: CryptoJS.mode.CTR,
            padding: CryptoJS.pad.NoPadding
        }
    );

    try {
        return decrypted.toString(CryptoJS.enc.Utf8);
    } catch (e) {
        return '';
    }
}

// Decrypt a versioned cryptogram, mirror of AccountCrypto::decryptVersioned().
// requireMac is true for $v3$, which is never emitted without a MAC, and false for $v2$,
// whose MAC segment was added after the format shipped.
function decryptVersioned(ciphertext, fingerprint, requireMac) {
    var parts = ciphertext.replace(/^\$/, '').split('$');
    if (parts.length < 3) return '';

    var hasMac = parts.length >= 4 && parts[3] !== '';
    if (requireMac && !hasMac) return '';

    var iv = atob(parts[1]);
    var ct = atob(parts[2]);

    // Verify the MAC when present (encrypt-then-MAC, mirror of AccountCrypto.php).
    // Older v2 ciphertexts have no MAC segment and stay readable; a present-but-invalid
    // MAC means the ciphertext was tampered with, so reject it.
    if (hasMac) {
        var macKey = CryptoJS.enc.Hex.parse(CryptoJS.SHA256(fingerprint + '|mac').toString());
        var msg = CryptoJS.enc.Latin1.parse(iv + ct);
        var expectedMac = CryptoJS.enc.Base64.stringify(CryptoJS.HmacSHA256(msg, macKey));
        if (expectedMac !== parts[3]) {
            return '';
        }
    }

    // Unsalted SHA-256 of the fingerprint, same derivation as the PHP side for v2/v3.
    var key = CryptoJS.enc.Hex.parse(CryptoJS.SHA256(fingerprint).toString());

    var decrypted = CryptoJS.AES.decrypt(
        { ciphertext: CryptoJS.enc.Latin1.parse(ct) },
        key,
        {
            iv: CryptoJS.enc.Latin1.parse(iv),
            mode: CryptoJS.mode.CTR,
            padding: CryptoJS.pad.NoPadding
        }
    );

    try {
        return decrypted.toString(CryptoJS.enc.Utf8);
    } catch (e) {
        // Fallback for passwords encrypted with the older Latin1 encoding.
        return decrypted.toString(CryptoJS.enc.Latin1);
    }
}

// Single entry point: dispatches on the version prefix and falls back to the legacy v1
// AES-CTR format. Callers must never pick the version themselves, otherwise a $v3$ value
// could be read through the lenient v2 path.
function decrypt_cryptogram(ciphertext, fingerprint) {
    if (ciphertext.startsWith(CRYPTOGRAM_V4_PREFIX)) {
        return decryptV4(ciphertext, fingerprint);
    }
    if (ciphertext.startsWith(CRYPTOGRAM_V3_PREFIX)) {
        return decryptVersioned(ciphertext, fingerprint, true);
    }
    if (ciphertext.startsWith(CRYPTOGRAM_V2_PREFIX)) {
        return decryptVersioned(ciphertext, fingerprint, false);
    }
    return AESDecryptCtr(ciphertext, SHA256(fingerprint), 256);
}


function decrypt_password(root_accounts_doc, suffix) {
    suffix = suffix || "";

    var aeskey = $("#aeskey" + suffix).val();
    var encrypted_password = $("#encrypted_password" + suffix).val();
    var accounts_id = $("#accounts_id" + suffix).val();
    var items_id = $("#items_id" + suffix).val();
    var itemtype = $("#itemtype" + suffix).val();

    var decrypted_password = '';

    decrypted_password = decrypt_cryptogram(encrypted_password, aeskey);

    if ($("#hidden_password" + suffix).length) {
        $("#hidden_password" + suffix).val(decrypted_password);
    }

    var url = root_accounts_doc + '/ajax/log_decrypt.php';

    var idcrypt, from;
    if (document.location.pathname.indexOf('accounts') > 0) {
        idcrypt = $('form#account_form input[name=id]').val();
        from = 'account';
    } else {
        idcrypt = accounts_id;
        from = 'item';
    }

    $.ajax({
        url: url,
        type: 'POST',
        data: { idcrypt, from, items_id, itemtype }
    });

    return decrypted_password;
}

// Extract the derivation parameters of a PBKDF2 verifier, or null for a legacy or malformed
// one. Mirror of AccountCrypto::parseVerifier().
function parse_verifier(verifier) {
    if (typeof verifier !== 'string' || verifier.indexOf('$pbkdf2$') !== 0) {
        return null;
    }

    var parts = verifier.split('$'); // ['', 'pbkdf2', iterations, salt_b64, hex]
    if (parts.length < 5) {
        return null;
    }

    var iterations = parseInt(parts[2], 10);
    if (!iterations || iterations <= 0 || parts[3] === '') {
        return null;
    }

    return { salt_b64: parts[3], iterations: iterations };
}

// Emits $v4$ when the hash record carries a PBKDF2 verifier to borrow the salt and iteration
// count from, $v3$ otherwise. Both mandate a MAC on read. Mirror of AccountCrypto::encrypt().
// $v2$ is never emitted any more: its MAC being optional, an authenticated cryptogram could be
// downgraded by simply dropping the segment.
//
// Borrowing the verifier salt rather than drawing a fresh one per record is deliberate: this
// page decrypts whole lists of accounts, and a salt of its own per record would mean one slow
// derivation per row instead of one per encryption key.
function encrypt_cryptogram(plaintext, fingerprint, verifier) {
    var params = parse_verifier(verifier);

    // Random 16-byte IV.
    var iv = CryptoJS.lib.WordArray.random(16);

    var encKey, macKey;
    if (params !== null) {
        var keys = deriveKeys(fingerprint, params.salt_b64, params.iterations);
        encKey = keys.enc;
        macKey = keys.mac;
    } else {
        encKey = CryptoJS.enc.Hex.parse(CryptoJS.SHA256(fingerprint).toString());
        macKey = CryptoJS.enc.Hex.parse(CryptoJS.SHA256(fingerprint + '|mac').toString());
    }

    // AES-256-CTR encryption.
    var encrypted = CryptoJS.AES.encrypt(
        CryptoJS.enc.Utf8.parse(plaintext),
        encKey,
        {
            iv: iv,
            mode: CryptoJS.mode.CTR,
            padding: CryptoJS.pad.NoPadding
        }
    );

    var iv_b64 = CryptoJS.enc.Base64.stringify(iv);
    var ct_b64 = CryptoJS.enc.Base64.stringify(encrypted.ciphertext);

    // Encrypt-then-MAC over IV + ciphertext with a distinct MAC key (mirror of AccountCrypto.php)
    var msg = iv.clone().concat(encrypted.ciphertext);
    var mac_b64 = CryptoJS.enc.Base64.stringify(CryptoJS.HmacSHA256(msg, macKey));

    if (params !== null) {
        // Format $v4$<salt_b64>$<iterations>$<iv_b64>$<ct_b64>$<mac_b64>
        return CRYPTOGRAM_V4_PREFIX + params.salt_b64 + '$' + params.iterations + '$'
            + iv_b64 + '$' + ct_b64 + '$' + mac_b64;
    }

    // Format $v3$<iv_b64>$<ct_b64>$<mac_b64>
    return CRYPTOGRAM_V3_PREFIX + iv_b64 + '$' + ct_b64 + '$' + mac_b64;
}

// var encrypt_password = function (suffix) {
//     suffix = suffix || "";
//     var aeskey = $("#aeskey").val();
//     // var checkAeskey           = $("#checkaeskey").val();
//     var select_encryption_key = false;
//     var encrypted_password = '';
//
//     // if (checkAeskey != '') {
//     //     select_encryption_key = true;
//     // }
//
//     // if (select_encryption_key) {
//     //     encrypted_password = AESEncryptCtr($('#hidden_password' + suffix).val(),
//     //         SHA256(checkAeskey),
//     //         256);
//     // } else {
//     encrypted_password = AESEncryptCtr($('#hidden_password' + suffix).val(),
//         SHA256(aeskey),
//         256);
//     // }
//
//     $('#encrypted_password').val(encrypted_password);
//     $('#account_form').submit();
// };

var encrypt_password = function(suffix) {

    suffix = suffix || "";

    var aeskey = $("#aeskey" + suffix).val();
    var plaintext = $("#hidden_password" + suffix).val();

    // Pass the verifier so the cryptogram is derived from the same salt the key was checked
    // against; without it the record falls back to v3 and loses the PBKDF2 derivation.
    var encrypted_password = encrypt_cryptogram(plaintext, aeskey, get_active_verifier(suffix));

    $("#encrypted_password" + suffix).val(encrypted_password);
};

/**
 * Mirror of encrypt_password() for the TOTP seed.
 *
 * The seed used to be posted in cleartext as totp_secret_plain and encrypted server side,
 * while the password had deliberately been kept inside the browser. Both are secrets of the
 * same vault and both belong on the same side of the wire, so the seed is encrypted here too
 * and only the cryptogram is posted.
 *
 * An empty field means "leave the stored secret alone": the hidden input then keeps the value
 * the server rendered, so the record is rewritten with what it already held.
 */
var encrypt_totp_secret = function(suffix) {

    suffix = suffix || "";

    var aeskey = $("#aeskey" + suffix).val();
    var plaintext = $("#totp_secret_field" + suffix).val();

    if (!plaintext || !aeskey) {
        return;
    }

    $("#encrypted_totp_secret" + suffix).val(
        encrypt_cryptogram(plaintext, aeskey, get_active_verifier(suffix))
    );

    // The field carries no name any more, but emptying it also keeps the seed out of the
    // browser's form restore and out of any autofill history.
    $("#totp_secret_field" + suffix).val('');
};
