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

namespace GlpiPlugin\Accounts\Tests;

use GlpiPlugin\Accounts\AccountCrypto;
use GlpiPlugin\Accounts\AesCtr;
use PHPUnit\Framework\TestCase;

class AccountCryptoTest extends TestCase
{
    /**
     * With no verifier to borrow a salt and an iteration count from, encrypt() falls back to
     * the v3 layout rather than drawing a salt of its own — see the class doc.
     */
    public function testEncryptWithoutVerifierFallsBackToV3(): void
    {
        $ciphertext = AccountCrypto::encrypt('secret', 'my-fingerprint', '');

        $this->assertStringStartsWith(AccountCrypto::V3_PREFIX, $ciphertext);
    }

    public function testEncryptWithPbkdf2VerifierProducesV4(): void
    {
        $fingerprint = 'my-fingerprint';
        $verifier    = AccountCrypto::makeVerifier($fingerprint);

        $ciphertext = AccountCrypto::encrypt('secret', $fingerprint, $verifier);

        $this->assertStringStartsWith(AccountCrypto::V4_PREFIX, $ciphertext);
        $this->assertSame('secret', AccountCrypto::decrypt($ciphertext, $fingerprint));
    }

    public function testEncryptWithLegacyVerifierStillProducesV3(): void
    {
        $fingerprint = 'my-fingerprint';
        $legacy      = hash('sha256', hash('sha256', $fingerprint));

        $ciphertext = AccountCrypto::encrypt('secret', $fingerprint, $legacy);

        $this->assertStringStartsWith(AccountCrypto::V3_PREFIX, $ciphertext);
    }

    public function testV4CarriesTheVerifierSaltAndIterations(): void
    {
        $fingerprint = 'my-fingerprint';
        $verifier    = AccountCrypto::makeVerifier($fingerprint);
        // ['pbkdf2', iterations, salt_b64, hex]
        $vparts = explode('$', ltrim($verifier, '$'));

        $ciphertext = AccountCrypto::encrypt('secret', $fingerprint, $verifier);
        // ['v4', salt_b64, iterations, iv_b64, ct_b64, mac_b64]
        $cparts = explode('$', ltrim($ciphertext, '$'));

        $this->assertCount(6, $cparts);
        $this->assertSame($vparts[2], $cparts[1]);
        $this->assertSame($vparts[1], $cparts[2]);
    }

    public function testV4WithTamperedCiphertextReturnsEmpty(): void
    {
        $fingerprint = 'my-fingerprint';
        $verifier    = AccountCrypto::makeVerifier($fingerprint);
        $parts       = explode('$', ltrim(AccountCrypto::encrypt('secret', $fingerprint, $verifier), '$'));

        // AES-CTR is malleable: flipping a ciphertext bit flips the matching plaintext bit.
        // Only the MAC stands in the way, so this must read back as unreadable, not as garbage.
        $raw       = base64_decode($parts[4], true);
        $raw[0]    = chr(ord($raw[0]) ^ 0x01);
        $parts[4]  = base64_encode($raw);

        $tampered = AccountCrypto::V4_PREFIX . implode('$', array_slice($parts, 1));

        $this->assertSame('', AccountCrypto::decrypt($tampered, $fingerprint));
    }

    public function testV4WithoutMacSegmentReturnsEmpty(): void
    {
        $fingerprint = 'my-fingerprint';
        $verifier    = AccountCrypto::makeVerifier($fingerprint);
        $parts       = explode('$', ltrim(AccountCrypto::encrypt('secret', $fingerprint, $verifier), '$'));

        // v4 is never emitted without a MAC, so dropping the segment is a downgrade attempt.
        $stripped = AccountCrypto::V4_PREFIX . $parts[1] . '$' . $parts[2] . '$'
            . $parts[3] . '$' . $parts[4];

        $this->assertSame('', AccountCrypto::decrypt($stripped, $fingerprint));
        $this->assertFalse(AccountCrypto::isAuthenticated($stripped));
    }

    public function testV4IsAuthenticatedAndNotLegacy(): void
    {
        $fingerprint = 'my-fingerprint';
        $ciphertext  = AccountCrypto::encrypt('secret', $fingerprint, AccountCrypto::makeVerifier($fingerprint));

        $this->assertTrue(AccountCrypto::isAuthenticated($ciphertext));
        $this->assertFalse(AccountCrypto::isLegacyFormat($ciphertext));
    }

    /**
     * PBKDF2 truncates: deriving 64 bytes from the same (password, salt) pair as the verifier
     * would make the first 32 of them equal the verifier itself — a value that is public, since
     * it is shipped to the browser to check the typed key. The '|key' salt suffix is what keeps
     * the two apart, and this test pins that property down.
     */
    public function testV4KeysAreSeparatedFromThePublicVerifier(): void
    {
        $fingerprint = 'separation-key';
        $verifier    = AccountCrypto::makeVerifier($fingerprint);

        $vparts     = explode('$', ltrim($verifier, '$'));
        $iterations = (int) $vparts[1];
        $salt       = base64_decode($vparts[2], true);

        $naive     = hash_pbkdf2('sha256', $fingerprint, $salt, $iterations, 64, true);
        $separated = hash_pbkdf2('sha256', $fingerprint, $salt . '|key', $iterations, 64, true);

        // The hazard is real: the naive derivation does start with the public verifier.
        $this->assertSame($vparts[3], bin2hex(substr($naive, 0, 32)));
        $this->assertNotSame(substr($naive, 0, 32), substr($separated, 0, 32));

        // And the format really uses the separated derivation.
        $cparts = explode('$', ltrim(AccountCrypto::encrypt('secret', $fingerprint, $verifier), '$'));
        $iv     = base64_decode($cparts[3], true);
        $ct     = base64_decode($cparts[4], true);

        $this->assertSame(
            'secret',
            openssl_decrypt($ct, 'AES-256-CTR', substr($separated, 0, 32), OPENSSL_RAW_DATA, $iv),
        );
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $fingerprint = 'test-fingerprint-key';
        $plaintext   = 'my-secret-password';

        $ciphertext = AccountCrypto::encrypt($plaintext, $fingerprint, '');
        $result     = AccountCrypto::decrypt($ciphertext, $fingerprint);

        $this->assertSame($plaintext, $result);
    }

    public function testDecryptWithWrongFingerprintReturnsEmpty(): void
    {
        $ciphertext = AccountCrypto::encrypt('secret', 'correct-key', '');

        $result = AccountCrypto::decrypt($ciphertext, 'wrong-key');

        $this->assertNotSame('secret', $result);
    }

    public function testIsLegacyFormatReturnsTrueForV1(): void
    {
        $v1 = base64_encode('some-legacy-ciphertext');

        $this->assertTrue(AccountCrypto::isLegacyFormat($v1));
    }

    public function testIsLegacyFormatReturnsFalseForVersionedFormats(): void
    {
        $v3 = AccountCrypto::encrypt('pass', 'key', '');
        $v2 = AccountCrypto::V2_PREFIX . substr($v3, strlen(AccountCrypto::V3_PREFIX));

        $this->assertFalse(AccountCrypto::isLegacyFormat($v3));
        $this->assertFalse(AccountCrypto::isLegacyFormat($v2));
    }

    public function testV2WithoutMacStaysReadable(): void
    {
        $fingerprint = 'key';
        $v3          = AccountCrypto::encrypt('pass', $fingerprint, '');
        $parts       = explode('$', ltrim($v3, '$'));
        // A pre-MAC v2 record: same layout, no fourth segment.
        $v2 = AccountCrypto::V2_PREFIX . $parts[1] . '$' . $parts[2];

        $this->assertSame('pass', AccountCrypto::decrypt($v2, $fingerprint));
    }

    public function testV3WithoutMacIsRejected(): void
    {
        $fingerprint = 'key';
        $v3          = AccountCrypto::encrypt('pass', $fingerprint, '');
        $parts       = explode('$', ltrim($v3, '$'));
        // Stripping the MAC of an authenticated record must not turn it into a readable one.
        $stripped = AccountCrypto::V3_PREFIX . $parts[1] . '$' . $parts[2];

        $this->assertSame('', AccountCrypto::decrypt($stripped, $fingerprint));
    }

    public function testIsAuthenticatedTracksTheMacSegment(): void
    {
        $v3    = AccountCrypto::encrypt('pass', 'key', '');
        $parts = explode('$', ltrim($v3, '$'));

        $this->assertTrue(AccountCrypto::isAuthenticated($v3));
        $this->assertFalse(
            AccountCrypto::isAuthenticated(AccountCrypto::V2_PREFIX . $parts[1] . '$' . $parts[2]),
        );
        $this->assertFalse(AccountCrypto::isAuthenticated(base64_encode('legacy')));
    }

    public function testNeedsReencryptionForEverythingButAuthenticatedV3(): void
    {
        $v3    = AccountCrypto::encrypt('pass', 'key', '');
        $parts = explode('$', ltrim($v3, '$'));

        $this->assertFalse(AccountCrypto::needsReencryption($v3));
        $this->assertTrue(AccountCrypto::needsReencryption(base64_encode('legacy')));
        $this->assertTrue(AccountCrypto::needsReencryption(
            AccountCrypto::V2_PREFIX . $parts[1] . '$' . $parts[2] . '$' . $parts[3],
        ));
    }

    /**
     * The re-encryption target follows what the hash record can offer, so a v3 record that was
     * fine yesterday has to be rewritten once its verifier gains PBKDF2 parameters — and a v4
     * record must not be rewritten on every single save.
     */
    public function testNeedsReencryptionTargetsV4WhenTheVerifierAllowsIt(): void
    {
        $fingerprint = 'key';
        $verifier    = AccountCrypto::makeVerifier($fingerprint);

        $v3 = AccountCrypto::encrypt('pass', $fingerprint, '');
        $v4 = AccountCrypto::encrypt('pass', $fingerprint, $verifier);

        $this->assertTrue(AccountCrypto::needsReencryption($v3, $verifier));
        $this->assertFalse(AccountCrypto::needsReencryption($v4, $verifier));

        // Without the parameters the target drops back to v3, but a v4 record is already above
        // it and must never be pushed back down to weaker keys.
        $this->assertFalse(AccountCrypto::needsReencryption($v3));
        $this->assertFalse(AccountCrypto::needsReencryption($v4));
    }

    public function testDecryptV1LegacyFormatViaAesCtr(): void
    {
        $fingerprint = 'legacy-fingerprint';
        $hash        = hash('sha256', $fingerprint);
        $v1          = AesCtr::encrypt('legacy-pass', $hash, 256);

        $result = AccountCrypto::decrypt($v1, $fingerprint);

        $this->assertSame('legacy-pass', $result);
    }

    public function testEncryptProducesDifferentCiphertextsForSamePlaintext(): void
    {
        $a = AccountCrypto::encrypt('same', 'key', '');
        $b = AccountCrypto::encrypt('same', 'key', '');

        $this->assertNotSame($a, $b);
    }

    public function testDecryptTruncatedCiphertextReturnsEmpty(): void
    {
        $result = AccountCrypto::decrypt(AccountCrypto::V3_PREFIX . 'bad', 'key');

        $this->assertSame('', $result);
    }
}
