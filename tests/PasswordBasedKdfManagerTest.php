<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\AbstractRegistry;
use Erikwang2013\Encryption\Kdf\Pbkdf2Sha256;
use Erikwang2013\Encryption\PasswordBasedKdfManager;
use Erikwang2013\Encryption\PasswordBasedKdfRegistry;

final class PasswordBasedKdfManagerTest extends AbstractManagerTestCase
{
    protected function makeItem(): object
    {
        return new Pbkdf2Sha256(1000);
    }

    protected function makeRegistry(object ...$items): AbstractRegistry
    {
        return new PasswordBasedKdfRegistry(...$items);
    }

    protected function makeManager(object $registry, string $defaultIdentifier): object
    {
        return new PasswordBasedKdfManager($registry, $defaultIdentifier);
    }

    protected function defaultIdentifier(): string
    {
        return 'pbkdf2-sha256';
    }

    protected function itemName(): string
    {
        return 'Password KDF';
    }

    protected function boundDefault(object $manager): object
    {
        return $manager->defaultKdf();
    }

    protected function dispatchToUnknown(object $manager): void
    {
        $manager->deriveFromPassword('secret', random_bytes(16), 16, 'nope');
    }

    public function testDeriveWithDefaultAndExplicitIdentifier(): void
    {
        $weak = new Pbkdf2Sha256(1000);
        $strong = new Pbkdf2Sha256(5000, 'pbkdf2-strong');
        $mgr = new PasswordBasedKdfManager(new PasswordBasedKdfRegistry($weak, $strong), 'pbkdf2-sha256');
        $salt = random_bytes(16);
        self::assertSame(32, strlen($mgr->deriveFromPassword('secret', $salt, 32)));
        // 显式标识走不同迭代次数，输出不同，证明路由生效。
        self::assertSame(
            $strong->deriveFromPassword('secret', $salt, 32),
            $mgr->deriveFromPassword('secret', $salt, 32, 'pbkdf2-strong')
        );
    }
}
