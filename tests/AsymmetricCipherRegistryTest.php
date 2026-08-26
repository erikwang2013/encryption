<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Asymmetric\Sm2AsymmetricCipher;
use Erikwang2013\Encryption\AsymmetricCipherRegistry;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

final class AsymmetricCipherRegistryTest extends TestCase
{
    public function testDuplicateRegistrationThrows(): void
    {
        $registry = new AsymmetricCipherRegistry(new Sm2AsymmetricCipher());
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Asymmetric cipher "sm2" is already registered.');
        $registry->register(new Sm2AsymmetricCipher());
    }

    public function testUnknownIdentifierMessage(): void
    {
        $registry = new AsymmetricCipherRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown asymmetric cipher: nope');
        $registry->get('nope');
    }

    public function testEmptyIdentifierThrows(): void
    {
        $registry = new AsymmetricCipherRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Asymmetric cipher identifier must not be empty.');
        $registry->register(new Sm2AsymmetricCipher(null, ''));
    }

    public function testCustomIdentifierResolution(): void
    {
        $cipher = new Sm2AsymmetricCipher(null, 'sm2-custom');
        $registry = new AsymmetricCipherRegistry($cipher);
        self::assertSame($cipher, $registry->get('sm2-custom'));
    }
}
