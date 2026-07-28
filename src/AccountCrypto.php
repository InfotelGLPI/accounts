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

namespace GlpiPlugin\Accounts;

/**
Cryptographic helpers for the Accounts plugin.
Format v2 (new default), authenticated (encrypt-then-MAC):
$v2$<base64(iv)>$<base64(ciphertext)>$<base64(mac)>
Where:
iv          = 16 random bytes (random_bytes)
enc_key     = SHA-256 of the user-supplied fingerprint key (32 bytes)
ciphertext  = openssl_encrypt(plaintext, 'AES-256-CTR', enc_key, OPENSSL_RAW_DATA, iv)
mac_key     = SHA-256 of (fingerprint . '|mac') (32 bytes, distinct from enc_key)
mac         = HMAC-SHA256(mac_key, iv . ciphertext) — detects tampering of the ciphertext
Older v2 ciphertexts without the trailing MAC segment are still accepted for reading
(they are re-encrypted with a MAC the next time the record is saved).
Legacy format (v1, read-only):

base64-encoded string without '$v2$' prefix — handled by AesCtr::decrypt()
 */
class AccountCrypto
{
    private const CIPHER    = 'AES-256-CTR';
    public const V2_PREFIX = '$v2$';
    // Domain-separation suffix used to derive the MAC key from the fingerprint.
    // Must stay in sync with the JavaScript implementation (public/crypt.js).
    private const MAC_KEY_SUFFIX = '|mac';

    // Fingerprint verifier hardening. The stored verifier (glpi_plugin_accounts_hashes.hash)
    // used to be a bare double SHA-256 of the encryption key, which is trivially brute-forced
    // offline once disclosed to a user (it is shipped to the browser to check the typed key).
    // New verifiers use a salted, slow PBKDF2 in this self-describing format:
    //   $pbkdf2$<iterations>$<base64(salt)>$<hex(derived)>
    // Legacy 64-hex verifiers keep working (see crypt.js generic_check_hash). This mirrors the
    // JavaScript implementation exactly (public/crypt.js) — keep both sides in sync.
    public const VERIFIER_PREFIX = '$pbkdf2$';
    private const VERIFIER_ITERATIONS = 100000;
    private const VERIFIER_SALT_BYTES = 16;

    /**
     * Encrypt plaintext using AES-256-CTR with a random IV.
     * Returns a v2-format string: $v2$<iv_b64>$<ct_b64>
     *
     * @param string $plaintext   The password to encrypt
     * @param string $fingerprint The raw fingerprint key (will be SHA-256 hashed)
     * @return string             Versioned ciphertext
     */
    public static function encrypt(string $plaintext, string $fingerprint): string
    {
        $key = hash('sha256', $fingerprint, true);  // 32 raw bytes
        $iv  = random_bytes(16);                    // cryptographically secure IV

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('AccountCrypto: openssl_encrypt failed: ' . openssl_error_string());
        }

        // Encrypt-then-MAC: authenticate IV + ciphertext with a distinct MAC key.
        $mac_key = hash('sha256', $fingerprint . self::MAC_KEY_SUFFIX, true);
        $mac     = hash_hmac('sha256', $iv . $ciphertext, $mac_key, true);

        return self::V2_PREFIX
            . base64_encode($iv) . '$'
            . base64_encode($ciphertext) . '$'
            . base64_encode($mac);
    }

    /**
     * Decrypt a ciphertext. Supports both v2 (new) and v1 (legacy AesCtr) formats.
     *
     * @param string $ciphertext  The stored encrypted value
     * @param string $fingerprint The raw fingerprint key
     * @return string             Decrypted plaintext, or empty string on failure
     */
    public static function decrypt(string $ciphertext, string $fingerprint): string
    {
        if (str_starts_with($ciphertext, self::V2_PREFIX)) {
            return self::decryptV2($ciphertext, $fingerprint);
        }

        // Legacy v1 format — delegate to old implementation
        $hash = hash('sha256', $fingerprint);
        return AesCtr::decrypt($ciphertext, $hash, 256);
    }

    /**
     * Build a salted, slow verifier for an encryption key (fingerprint).
     * Stored in glpi_plugin_accounts_hashes.hash and used only to check that a typed key
     * is the right one — it is NOT the encryption key. Format:
     *   $pbkdf2$<iterations>$<base64(salt)>$<hex(derived)>
     * Must produce a value verifiable by crypt.js generic_check_hash().
     *
     * @param string $key The raw encryption key (fingerprint)
     * @return string      Self-describing PBKDF2 verifier
     */
    public static function makeVerifier(string $key): string
    {
        $salt    = random_bytes(self::VERIFIER_SALT_BYTES);
        $derived = hash_pbkdf2('sha256', $key, $salt, self::VERIFIER_ITERATIONS, 0, false);

        return self::VERIFIER_PREFIX
            . self::VERIFIER_ITERATIONS . '$'
            . base64_encode($salt) . '$'
            . $derived;
    }

    /**
     * Check if a stored ciphertext uses the legacy (v1) format.
     * Useful for migration scripts and re-encryption on save.
     */
    public static function isLegacyFormat(string $ciphertext): bool
    {
        return !str_starts_with($ciphertext, self::V2_PREFIX);
    }

    /**
     * Tell whether a stored ciphertext should be transparently re-encrypted on save.
     * True for legacy v1 records (no versioning) and for early v2 records that still
     * lack the trailing HMAC segment, so they gain integrity protection over time.
     */
    public static function needsReencryption(string $ciphertext): bool
    {
        if (self::isLegacyFormat($ciphertext)) {
            return true;
        }

        // v2 without a (non-empty) MAC segment: upgrade to authenticated v2.
        $parts = explode('$', ltrim($ciphertext, '$'));
        return !isset($parts[3]) || $parts[3] === '';
    }

    // -----------------------------------------------------------------------

    private static function decryptV2(string $ciphertext, string $fingerprint): string
    {
        // Format: $v2$<iv_b64>$<ct_b64>
        $parts = explode('$', ltrim($ciphertext, '$'));
        // parts[0] = 'v2', parts[1] = iv_b64, parts[2] = ct_b64
        if (count($parts) < 3) {
            return '';
        }

        $iv         = base64_decode($parts[1]);
        $ct         = base64_decode($parts[2]);
        $key        = hash('sha256', $fingerprint, true);

        // Verify the MAC when present (encrypt-then-MAC). Older v2 ciphertexts have no
        // MAC segment and are still readable so existing vaults keep working; they gain
        // a MAC on the next save. A present-but-invalid MAC means tampering: reject.
        if (isset($parts[3]) && $parts[3] !== '') {
            $mac_key  = hash('sha256', $fingerprint . self::MAC_KEY_SUFFIX, true);
            $expected = hash_hmac('sha256', $iv . $ct, $mac_key, true);
            $given    = base64_decode($parts[3], true);
            if ($given === false || !hash_equals($expected, $given)) {
                return '';
            }
        }

        $plaintext = openssl_decrypt(
            $ct,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plaintext === false ? '' : $plaintext;
    }
}
