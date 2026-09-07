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

namespace GlpiPlugin\Accounts;

/**
Cryptographic helpers for the Accounts plugin.

Format v4 (current default), authenticated (encrypt-then-MAC), keys derived by PBKDF2:
$v4$<base64(salt)>$<iterations>$<base64(iv)>$<base64(ciphertext)>$<base64(mac)>
Where:
salt        = the salt of the hash record verifier, so every record of one encryption key
              shares it and the (deliberately slow) derivation is computed once per key
iterations  = the iteration count of that same verifier, carried along so the record stays
              readable if the count is raised later
dk          = PBKDF2-SHA256(fingerprint, salt . '|key', iterations, 64 bytes)
enc_key     = dk[0..31], mac_key = dk[32..63]
ciphertext  = openssl_encrypt(plaintext, 'AES-256-CTR', enc_key, OPENSSL_RAW_DATA, iv)
mac         = HMAC-SHA256(mac_key, iv . ciphertext)

The '|key' suffix on the salt is what keeps the derivation apart from the verifier: PBKDF2
truncates, so deriving with the bare salt would make the first 32 bytes of dk equal to the
verifier itself — a value that is public, since it is shipped to the browser to check the
typed key. Never derive both from the same (password, salt) pair.

Format v3 (read-only), same authenticated layout but with keys derived by a single unsalted
SHA-256 of the fingerprint:
$v3$<base64(iv)>$<base64(ciphertext)>$<base64(mac)>
That derivation makes an offline dictionary attack on a database dump orders of magnitude
cheaper than going through the verifier, which is why v3 is no longer emitted whenever the
hash record carries a PBKDF2 verifier to derive from.

Format v2 (read-only), v3 layout with an optional MAC segment. The MAC was added after the
format shipped, so a v2 ciphertext without it stays readable. That leniency is precisely why
v2 is no longer emitted: since the field travels through the browser, an authenticated
ciphertext could be downgraded to an unauthenticated one by dropping its MAC, and AES-CTR is
malleable. v3 and v4 have no such branch, and Account::prepareInputForUpdate() refuses any
transition from an authenticated ciphertext to an unauthenticated one.

Records are re-encrypted to the best format available the next time they are saved with a
verified fingerprint.

Legacy format (v1, read-only):

base64-encoded string without version prefix — handled by AesCtr::decrypt()
 */
class AccountCrypto
{
    private const CIPHER    = 'AES-256-CTR';
    /** Read-only legacy format, kept for existing vaults (MAC segment optional). */
    public const V2_PREFIX = '$v2$';
    /** Read-only format: mandatory MAC, but keys derived by a single unsalted SHA-256. */
    public const V3_PREFIX = '$v3$';
    /** Current format: mandatory MAC and keys derived by PBKDF2, salt and count carried along. */
    public const V4_PREFIX = '$v4$';
    // Domain-separation suffix used to derive the v3 MAC key from the fingerprint.
    // Must stay in sync with the JavaScript implementation (public/crypt.js).
    private const MAC_KEY_SUFFIX = '|mac';
    // Domain-separation suffix appended to the verifier salt before deriving the v4 keys,
    // so that the derivation never coincides with the (public) verifier. See the class doc.
    private const KEY_SALT_SUFFIX = '|key';

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
     * Shortest encryption key the plugin agrees to take.
     *
     * Client-side encryption means the browser has to be handed what it needs to decrypt: the
     * verifier and the cryptograms are served to every user allowed to read an account of the
     * entity. That is the design, not a defect, but it does put the master key within reach of
     * an offline attack by anyone holding the read right. PBKDF2 over 100 000 iterations makes
     * each guess expensive; it cannot make a short key take many guesses. This is the other
     * half of that defence, and the only one that scales.
     *
     * Enforced server-side on rotation (front/hash.form.php), which is the one path the key
     * travels on. At creation the browser derives the verifier and posts only that, so the
     * check there is necessarily client-side (templates/hash.html.twig) -- a usability guard
     * for the honest operator rather than a boundary. Keep the two in step.
     */
    public const MIN_KEY_LENGTH = 12;

    /**
     * Encrypt plaintext using AES-256-CTR with a random IV.
     *
     * Emits v4 ($v4$<salt_b64>$<iterations>$<iv_b64>$<ct_b64>$<mac_b64>) when the hash record
     * carries a PBKDF2 verifier to borrow the salt and iteration count from, v3 otherwise.
     * Falling back rather than drawing a fresh salt is deliberate: the browser decrypts whole
     * lists of accounts, and a salt of its own per record would mean one slow derivation per
     * row instead of one per encryption key. Vaults still on a legacy verifier upgrade to v4
     * as soon as their verifier does — see Account::resolveFingerprint().
     *
     * The verifier has no default on purpose. Omitting it silently downgrades the record to v3,
     * and that mistake is invisible in review — pass '' explicitly to say no verifier is
     * available, so the fallback is always a decision rather than an oversight.
     *
     * @param string $plaintext   The password to encrypt
     * @param string $fingerprint The raw fingerprint key
     * @param string $verifier    The verifier stored on the hash record, '' when there is none
     * @return string             Versioned ciphertext
     */
    public static function encrypt(string $plaintext, string $fingerprint, string $verifier): string
    {
        $params = self::parseVerifier($verifier);
        $iv     = random_bytes(16); // cryptographically secure IV

        if ($params !== null) {
            [$enc_key, $mac_key] = self::deriveKeys($fingerprint, $params['salt'], $params['iterations']);
        } else {
            $enc_key = hash('sha256', $fingerprint, true);                          // 32 raw bytes
            $mac_key = hash('sha256', $fingerprint . self::MAC_KEY_SUFFIX, true);   // distinct key
        }

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $enc_key,
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('AccountCrypto: openssl_encrypt failed: ' . openssl_error_string());
        }

        // Encrypt-then-MAC: authenticate IV + ciphertext with a distinct MAC key.
        $mac = hash_hmac('sha256', $iv . $ciphertext, $mac_key, true);

        if ($params !== null) {
            return self::V4_PREFIX
                . base64_encode($params['salt']) . '$'
                . $params['iterations'] . '$'
                . base64_encode($iv) . '$'
                . base64_encode($ciphertext) . '$'
                . base64_encode($mac);
        }

        return self::V3_PREFIX
            . base64_encode($iv) . '$'
            . base64_encode($ciphertext) . '$'
            . base64_encode($mac);
    }

    /**
     * Decrypt a ciphertext. Supports v4, v3, v2 and v1 (legacy AesCtr) formats.
     *
     * @param string $ciphertext  The stored encrypted value
     * @param string $fingerprint The raw fingerprint key
     * @return string             Decrypted plaintext, or empty string on failure
     */
    public static function decrypt(string $ciphertext, string $fingerprint): string
    {
        if (str_starts_with($ciphertext, self::V4_PREFIX)) {
            return self::decryptV4($ciphertext, $fingerprint);
        }

        if (str_starts_with($ciphertext, self::V3_PREFIX)) {
            // v3 is only ever emitted with a MAC: a missing or invalid one is tampering.
            return self::decryptVersioned($ciphertext, $fingerprint, true);
        }

        if (str_starts_with($ciphertext, self::V2_PREFIX)) {
            return self::decryptVersioned($ciphertext, $fingerprint, false);
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
     * Check a typed encryption key against a stored verifier.
     * PHP mirror of crypt.js generic_check_hash(): accepts both the new salted PBKDF2
     * verifier ($pbkdf2$<iterations>$<base64(salt)>$<hex(derived)>) and legacy bare
     * double SHA-256 verifiers, so existing keys keep working during the transition.
     * Comparisons use hash_equals() to stay constant-time.
     *
     * @param string $key    The raw encryption key (fingerprint) typed by the user
     * @param string $stored The stored verifier (glpi_plugin_accounts_hashes.hash)
     * @return bool           True when the key matches the verifier
     */
    public static function verify(string $key, string $stored): bool
    {
        if ($key === '' || $stored === '') {
            return false;
        }

        // New salted, slow verifier.
        if (str_starts_with($stored, self::VERIFIER_PREFIX)) {
            // ltrim the leading '$' then split: ['pbkdf2', iterations, salt_b64, hex]
            $parts = explode('$', ltrim($stored, '$'));
            if (count($parts) < 4) {
                return false;
            }
            $iterations = (int) $parts[1];
            $salt       = base64_decode($parts[2], true);
            if ($iterations <= 0 || $salt === false) {
                return false;
            }
            $derived = hash_pbkdf2('sha256', $key, $salt, $iterations, 0, false);
            return hash_equals($parts[3], $derived);
        }

        // Legacy verifier: bare double SHA-256.
        return hash_equals($stored, hash('sha256', hash('sha256', $key)));
    }

    /**
     * Check if a stored ciphertext uses the legacy (v1) format.
     * Useful for migration scripts and re-encryption on save.
     */
    public static function isLegacyFormat(string $ciphertext): bool
    {
        return !str_starts_with($ciphertext, self::V2_PREFIX)
            && !str_starts_with($ciphertext, self::V3_PREFIX)
            && !str_starts_with($ciphertext, self::V4_PREFIX);
    }

    /**
     * Tell whether a ciphertext carries integrity protection, i.e. whether reading it back
     * actually verifies its MAC. Used to refuse a downgrade on save.
     */
    public static function isAuthenticated(string $ciphertext): bool
    {
        if (self::isLegacyFormat($ciphertext)) {
            return false;
        }

        // The MAC is the last segment, and v4 carries two extra ones (salt, iterations).
        $parts = explode('$', ltrim($ciphertext, '$'));
        $index = str_starts_with($ciphertext, self::V4_PREFIX) ? 5 : 3;

        return isset($parts[$index]) && $parts[$index] !== '';
    }

    /**
     * Tell whether a stored ciphertext should be transparently re-encrypted on save.
     *
     * The target format depends on what the hash record can offer: v4 when its verifier
     * provides a salt and an iteration count to derive from, v3 otherwise. Anything below
     * that target has to be rewritten — legacy v1 records, v2 records with or without their
     * optional HMAC segment, and v3 records once v4 becomes reachable.
     *
     * @param string $ciphertext The stored value
     * @param string $verifier   The verifier stored on the hash record, when available
     */
    public static function needsReencryption(string $ciphertext, string $verifier = ''): bool
    {
        // Never ask to rewrite a record that is already above the target: without a verifier
        // the target computes to v3, and a v4 record would then be pushed back down to weaker
        // keys. Re-encryption only ever moves forward.
        if (str_starts_with($ciphertext, self::V4_PREFIX)) {
            return !self::isAuthenticated($ciphertext);
        }

        $target = self::parseVerifier($verifier) !== null ? self::V4_PREFIX : self::V3_PREFIX;

        return !str_starts_with($ciphertext, $target)
            || !self::isAuthenticated($ciphertext);
    }

    // -----------------------------------------------------------------------

    /**
     * Extract the derivation parameters of a PBKDF2 verifier.
     *
     * @param string $verifier A verifier as stored in glpi_plugin_accounts_hashes.hash
     * @return array{salt: string, iterations: int}|null Null for a legacy or malformed verifier
     */
    private static function parseVerifier(string $verifier): ?array
    {
        if (!str_starts_with($verifier, self::VERIFIER_PREFIX)) {
            return null;
        }

        // ltrim the leading '$' then split: ['pbkdf2', iterations, salt_b64, hex]
        $parts = explode('$', ltrim($verifier, '$'));
        if (count($parts) < 4) {
            return null;
        }

        $iterations = (int) $parts[1];
        $salt       = base64_decode($parts[2], true);
        if ($iterations <= 0 || $salt === false || $salt === '') {
            return null;
        }

        return ['salt' => $salt, 'iterations' => $iterations];
    }

    /**
     * Derive the v4 encryption and MAC keys from the fingerprint.
     *
     * A single 64-byte PBKDF2 output split in two halves, so both keys cost one derivation.
     * Results are memoized: the same key is used for every record of a hash, and the whole
     * point of PBKDF2 is to be slow. Mirror of deriveKeys() in public/crypt.js.
     *
     * @return array{0: string, 1: string} [enc_key, mac_key], 32 raw bytes each
     */
    private static function deriveKeys(string $fingerprint, string $salt, int $iterations): array
    {
        static $cache = [];

        $cache_key = hash('sha256', $fingerprint . "\0" . $salt . "\0" . $iterations);
        if (!isset($cache[$cache_key])) {
            $derived = hash_pbkdf2(
                'sha256',
                $fingerprint,
                $salt . self::KEY_SALT_SUFFIX,
                $iterations,
                64,
                true,
            );
            $cache[$cache_key] = [substr($derived, 0, 32), substr($derived, 32, 32)];
        }

        return $cache[$cache_key];
    }

    /**
     * Decrypt a v4 ciphertext: $v4$<salt_b64>$<iterations>$<iv_b64>$<ct_b64>$<mac_b64>.
     * The MAC is mandatory — v4 is never emitted without one, so a missing or invalid MAC
     * means the record was tampered with.
     */
    private static function decryptV4(string $ciphertext, string $fingerprint): string
    {
        // parts[0] = version, [1] = salt_b64, [2] = iterations, [3] = iv_b64, [4] = ct_b64,
        // [5] = mac_b64
        $parts = explode('$', ltrim($ciphertext, '$'));
        if (count($parts) < 6 || $parts[5] === '') {
            return '';
        }

        $salt       = base64_decode($parts[1], true);
        $iterations = (int) $parts[2];
        $iv         = base64_decode($parts[3], true);
        $ct         = base64_decode($parts[4], true);
        $given      = base64_decode($parts[5], true);

        if ($salt === false || $salt === '' || $iterations <= 0
            || $iv === false || $ct === false || $given === false) {
            return '';
        }

        [$enc_key, $mac_key] = self::deriveKeys($fingerprint, $salt, $iterations);

        $expected = hash_hmac('sha256', $iv . $ct, $mac_key, true);
        if (!hash_equals($expected, $given)) {
            return '';
        }

        $plaintext = openssl_decrypt($ct, self::CIPHER, $enc_key, OPENSSL_RAW_DATA, $iv);

        return $plaintext === false ? '' : $plaintext;
    }

    /**
     * @param bool $require_mac Reject the ciphertext when it carries no MAC segment. True for
     *                          v3, which is never emitted without one; false for v2, whose MAC
     *                          was added after the format shipped.
     */
    private static function decryptVersioned(
        string $ciphertext,
        string $fingerprint,
        bool $require_mac,
    ): string {
        // Format: $v<n>$<iv_b64>$<ct_b64>[$<mac_b64>]
        $parts = explode('$', ltrim($ciphertext, '$'));
        // parts[0] = version, parts[1] = iv_b64, parts[2] = ct_b64, parts[3] = mac_b64
        if (count($parts) < 3) {
            return '';
        }

        $has_mac = isset($parts[3]) && $parts[3] !== '';
        if ($require_mac && !$has_mac) {
            return '';
        }

        $iv         = base64_decode($parts[1]);
        $ct         = base64_decode($parts[2]);
        $key        = hash('sha256', $fingerprint, true);

        // Verify the MAC when present (encrypt-then-MAC). Older v2 ciphertexts have no
        // MAC segment and are still readable so existing vaults keep working; they gain
        // a MAC on the next save. A present-but-invalid MAC means tampering: reject.
        if ($has_mac) {
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
            $iv,
        );

        return $plaintext === false ? '' : $plaintext;
    }
}
