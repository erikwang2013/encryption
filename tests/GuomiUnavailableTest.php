<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Exception\UnsupportedNationalAlgorithmException;
use Erikwang2013\Encryption\Guomi\UnavailableNationalAlgorithms;
use PHPUnit\Framework\TestCase;

final class GuomiUnavailableTest extends TestCase
{
    public function testSm1ThrowsUnsupportedException(): void
    {
        $this->expectException(UnsupportedNationalAlgorithmException::class);
        UnavailableNationalAlgorithms::sm1();
    }

    public function testSm7ThrowsUnsupportedException(): void
    {
        $this->expectException(UnsupportedNationalAlgorithmException::class);
        UnavailableNationalAlgorithms::sm7();
    }

    public function testSm9ThrowsUnsupportedException(): void
    {
        $this->expectException(UnsupportedNationalAlgorithmException::class);
        UnavailableNationalAlgorithms::sm9();
    }

    public function testAllAreEncryptionExceptions(): void
    {
        foreach (['sm1', 'sm7', 'sm9'] as $method) {
            try {
                UnavailableNationalAlgorithms::$method();
                self::fail("{$method} should have thrown");
            } catch (EncryptionException $e) {
                self::assertInstanceOf(UnsupportedNationalAlgorithmException::class, $e);
            }
        }
    }

    public function testMessagesMentionAlgorithmName(): void
    {
        foreach (['sm1' => 'SM1', 'sm7' => 'SM7', 'sm9' => 'SM9'] as $method => $name) {
            try {
                UnavailableNationalAlgorithms::$method();
                self::fail("{$method} should have thrown");
            } catch (UnsupportedNationalAlgorithmException $e) {
                self::assertStringContainsString($name, $e->getMessage(), "{$method} message should mention {$name}");
            }
        }
    }
}
