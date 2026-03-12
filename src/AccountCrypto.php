<?php

/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2009-2022 by the accounts Development Team.

 https://github.com/InfotelGLPI/accounts
 -------------------------------------------------------------------------

 LICENSE

 This file is part of accounts.

 accounts is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
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
Format v2 (new default):
$v2$<base64(iv)>$<base64(ciphertext)>
Where:
iv          = 16 random bytes (openssl_random_pseudo_bytes)
key         = SHA-256 of the user-supplied fingerprint key (32 bytes)
ciphertext  = openssl_encrypt(plaintext, 'AES-256-CTR', key, OPENSSL_RAW_DATA, iv)
Legacy format (v1, read-only):

base64-encoded string without '$v2$' prefix — handled by AesCtr::decrypt()
 */
class AccountCrypto
{
    private const CIPHER    = 'AES-256-CTR';
    private const V2_PREFIX = '$v2$';

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

        return self::V2_PREFIX . base64_encode($iv) . '$' . base64_encode($ciphertext);
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
     * Check if a stored ciphertext uses the legacy (v1) format.
     * Useful for migration scripts and re-encryption on save.
     */
    public static function isLegacyFormat(string $ciphertext): bool
    {
        return !str_starts_with($ciphertext, self::V2_PREFIX);
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
