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

namespace GlpiPlugin\Accounts\Tests;

use GlpiPlugin\Accounts\AccountCrypto;
use GlpiPlugin\Accounts\AesCtr;
use PHPUnit\Framework\TestCase;

class AccountCryptoTest extends TestCase
{
    public function testEncryptProducesV2Prefix(): void
    {
        $ciphertext = AccountCrypto::encrypt('secret', 'my-fingerprint');

        $this->assertStringStartsWith(AccountCrypto::V2_PREFIX, $ciphertext);
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $fingerprint = 'test-fingerprint-key';
        $plaintext   = 'my-secret-password';

        $ciphertext = AccountCrypto::encrypt($plaintext, $fingerprint);
        $result     = AccountCrypto::decrypt($ciphertext, $fingerprint);

        $this->assertSame($plaintext, $result);
    }

    public function testDecryptWithWrongFingerprintReturnsEmpty(): void
    {
        $ciphertext = AccountCrypto::encrypt('secret', 'correct-key');

        $result = AccountCrypto::decrypt($ciphertext, 'wrong-key');

        $this->assertNotSame('secret', $result);
    }

    public function testIsLegacyFormatReturnsTrueForV1(): void
    {
        $v1 = base64_encode('some-legacy-ciphertext');

        $this->assertTrue(AccountCrypto::isLegacyFormat($v1));
    }

    public function testIsLegacyFormatReturnsFalseForV2(): void
    {
        $v2 = AccountCrypto::encrypt('pass', 'key');

        $this->assertFalse(AccountCrypto::isLegacyFormat($v2));
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
        $a = AccountCrypto::encrypt('same', 'key');
        $b = AccountCrypto::encrypt('same', 'key');

        $this->assertNotSame($a, $b);
    }

    public function testDecryptTruncatedCiphertextReturnsEmpty(): void
    {
        $result = AccountCrypto::decrypt(AccountCrypto::V2_PREFIX . 'bad', 'key');

        $this->assertSame('', $result);
    }
}
