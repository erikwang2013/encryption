<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\EncryptorRegistry;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

final class EncryptorRegistryTest extends TestCase
{
    public function testDuplicateRegistrationThrows(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $registry = new EncryptorRegistry(new Aes256GcmEncryptor($key));
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Encryptor "aes-256-gcm" is already registered.');
        $registry->register(new Aes256GcmEncryptor($key));
    }

    public function testEmptyIdentifierThrows(): void
    {
        $registry = new EncryptorRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Encryptor identifier must not be empty.');
        $registry->register(new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN), ''));
    }

    public function testCustomIdentifierAliasResolution(): void
    {
        $e = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN), 'my-aes-gcm');
        $registry = new EncryptorRegistry($e);
        self::assertSame($e, $registry->get('my-aes-gcm'));
        self::assertTrue($registry->has('my-aes-gcm'));
    }
}
