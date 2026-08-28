<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Hash\Sha256Hasher;
use Erikwang2013\Encryption\HasherRegistry;

final class HasherRegistryTest extends AbstractRegistryTestCase
{
    protected function makeItem(?string $identifier = null): object
    {
        return new Sha256Hasher($identifier ?? 'sha256');
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new HasherRegistry(...$items);
    }

    protected function itemName(): string
    {
        return 'Hasher';
    }

    protected function customIdentifier(): string
    {
        return 'custom';
    }

    public function testMultipleHashersCoexist(): void
    {
        $sha = new Sha256Hasher();
        $sm3 = new Sm3Hasher();
        $registry = new HasherRegistry($sha, $sm3);
        self::assertSame(['sha256', 'sm3'], $registry->identifiers());
        self::assertSame($sm3, $registry->get('sm3'));
        self::assertSame($sha, $registry->get('sha256'));
    }
}
