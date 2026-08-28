<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Hash\Sha256Hasher;
use Erikwang2013\Encryption\HashingManager;
use Erikwang2013\Encryption\HasherRegistry;

final class HashingManagerTest extends AbstractManagerTestCase
{
    protected function makeItem(): object
    {
        return new Sha256Hasher();
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new HasherRegistry(...$items);
    }

    protected function makeManager(object $registry, string $defaultIdentifier): object
    {
        return new HashingManager($registry, $defaultIdentifier);
    }

    protected function defaultIdentifier(): string
    {
        return 'sha256';
    }

    protected function itemName(): string
    {
        return 'Hasher';
    }

    protected function boundDefault(object $manager): object
    {
        return $manager->defaultHasher();
    }

    protected function dispatchToUnknown(object $manager): void
    {
        $manager->digest('data', 'nope');
    }

    public function testDigestRoutesByIdentifier(): void
    {
        $registry = new HasherRegistry(new Sha256Hasher(), new Sm3Hasher());
        $mgr = new HashingManager($registry, 'sha256');
        $data = 'routing-check';
        self::assertSame((new Sha256Hasher())->digestHex($data), $mgr->digestHex($data));
        self::assertSame((new Sm3Hasher())->digestHex($data), $mgr->digestHex($data, 'sm3'));
    }
}
