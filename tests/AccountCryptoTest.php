<?php

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
