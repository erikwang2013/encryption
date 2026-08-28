<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\EncryptorRegistry;

final class EncryptorRegistryTest extends AbstractRegistryTestCase
{
    protected function makeItem(?string $identifier = null): object
    {
        return new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN), $identifier ?? 'aes-256-gcm');
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new EncryptorRegistry(...$items);
    }

    protected function itemName(): string
    {
        return 'Encryptor';
    }

    protected function customIdentifier(): string
    {
        return 'my-aes-gcm';
    }

    protected function assertCustomIdentifierResolved(AbstractRegistry $registry, object $item): void
    {
        self::assertSame($item, $registry->get($this->customIdentifier()));
        self::assertTrue($registry->has($this->customIdentifier()));
    }

    public function testRegisterReturnsFluentSelf(): void
    {
        $registry = $this->makeRegistry();
        $e = $this->makeItem();
        self::assertSame($registry, $registry->register($e));
    }

    public function testConstructorAcceptsVariadicItems(): void
    {
        $a = $this->makeItem('a');
        $b = $this->makeItem('b');
        $registry = $this->makeRegistry($a, $b);
        self::assertSame(['a', 'b'], $registry->identifiers());
    }

    public function testHasAndGet(): void
    {
        $e = $this->makeItem();
        $registry = $this->makeRegistry($e);
        self::assertTrue($registry->has('aes-256-gcm'));
        self::assertFalse($registry->has('missing'));
        self::assertSame($e, $registry->get('aes-256-gcm'));
    }

    public function testIdentifiersPreserveRegistrationOrder(): void
    {
        $registry = $this->makeRegistry($this->makeItem('first'), $this->makeItem('second'));
        self::assertSame(['first', 'second'], $registry->identifiers());
    }

    public function testEmptyRegistryHasNoIdentifiers(): void
    {
        $registry = $this->makeRegistry();
        self::assertSame([], $registry->identifiers());
    }
}
