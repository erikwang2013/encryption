<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\EncryptorRegistry;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

final class AbstractRegistryTest extends TestCase
{
    public function testRegisterReturnsFluentSelf(): void
    {
        $registry = new EncryptorRegistry();
        $e = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN));
        self::assertSame($registry, $registry->register($e));
    }

    public function testConstructorAcceptsVariadicItems(): void
    {
        $a = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN), 'a');
        $b = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN), 'b');
        $registry = new EncryptorRegistry($a, $b);
        self::assertSame(['a', 'b'], $registry->identifiers());
    }

    public function testHasAndGet(): void
    {
        $e = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN));
        $registry = new EncryptorRegistry($e);
        self::assertTrue($registry->has('aes-256-gcm'));
        self::assertFalse($registry->has('missing'));
        self::assertSame($e, $registry->get('aes-256-gcm'));
    }

    public function testGetUnknownThrows(): void
    {
        $registry = new EncryptorRegistry();
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown encryptor: nope');
        $registry->get('nope');
    }

    public function testIdentifiersPreserveRegistrationOrder(): void
    {
        $registry = new EncryptorRegistry(
            new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN), 'first'),
            new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN), 'second'),
        );
        self::assertSame(['first', 'second'], $registry->identifiers());
    }

    public function testEmptyRegistryHasNoIdentifiers(): void
    {
        $registry = new EncryptorRegistry();
        self::assertSame([], $registry->identifiers());
    }
}
