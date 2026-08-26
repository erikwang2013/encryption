<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Kdf\HkdfSha256;
use Erikwang2013\Encryption\KeyDerivationRegistry;
use PHPUnit\Framework\TestCase;

final class KeyDerivationRegistryTest extends TestCase
{
    public function testDuplicateRegistrationThrows(): void
    {
        $registry = new KeyDerivationRegistry(new HkdfSha256());
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('KDF "hkdf-sha256" is already registered.');
        $registry->register(new HkdfSha256());
    }

    public function testUnknownIdentifierMessageKeepsUppercase(): void
    {
        $registry = new KeyDerivationRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown KDF: nope');
        $registry->get('nope');
    }

    public function testEmptyIdentifierThrows(): void
    {
        $registry = new KeyDerivationRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('KDF identifier must not be empty.');
        $registry->register(new HkdfSha256(''));
    }

    public function testCustomIdentifierResolution(): void
    {
        $kdf = new HkdfSha256('kdf-v2');
        $registry = new KeyDerivationRegistry($kdf);
        self::assertSame($kdf, $registry->get('kdf-v2'));
        self::assertSame(['kdf-v2'], $registry->identifiers());
    }
}
