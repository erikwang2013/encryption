<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Base test case providing shared helpers for the suite: extension guards and blob layout splitting.
 */
abstract class TestCase extends PhpUnitTestCase
{
    protected function skipWithoutGmp(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded.');
        }
    }

    protected function skipIfGmpLoaded(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp loaded, cannot test missing-gmp path.');
        }
    }

    protected function skipWithoutSodium(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not loaded');
        }
    }

    protected function splitBlob(string $blob, int $ivLen = 16, int $macLen = 32): array
    {
        return [
            'prefix' => substr($blob, 0, 2),
            'iv' => substr($blob, 2, $ivLen),
            'mac' => substr($blob, 2 + $ivLen, $macLen),
            'ct' => substr($blob, 2 + $ivLen + $macLen),
        ];
    }
}
