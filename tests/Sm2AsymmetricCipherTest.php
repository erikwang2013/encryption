<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Asymmetric\Sm2AsymmetricCipher;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;
use PHPUnit\Framework\TestCase;

final class Sm2AsymmetricCipherTest extends TestCase
{
    public function testDefaultIdentifier(): void
    {
        self::assertSame('sm2', (new Sm2AsymmetricCipher())->getIdentifier());
    }

    public function testCustomIdentifier(): void
    {
        self::assertSame('sm2-custom', (new Sm2AsymmetricCipher(null, 'sm2-custom'))->getIdentifier());
    }

    public function testDecryptRejectsInvalidPrivateKeyWithoutGmp(): void
    {
        $cipher = new Sm2AsymmetricCipher();
        foreach (['', 'short', 'zz' . str_repeat('0', 62)] as $badKey) {
            try {
                $cipher->decrypt('v1ciphertext', $badKey);
                self::fail("decrypt() should have rejected key '{$badKey}'.");
            } catch (EncryptionException $e) {
                self::assertSame('Invalid SM2 private key.', $e->getMessage());
            }
        }
    }

    public function testRoundTripWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded');
        }
        $pair = Sm2EncryptionService::generateKeyPairHex();
        $cipher = new Sm2AsymmetricCipher();
        $plain = 'sm2-cipher';
        self::assertSame($plain, $cipher->decrypt($cipher->encrypt($plain, $pair->getPublicKey()), $pair->getPrivateKey()));
    }
}
