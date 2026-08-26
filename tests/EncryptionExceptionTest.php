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

final class EncryptionExceptionTest extends TestCase
{
    public function testEncryptionExceptionExtendsRuntimeException(): void
    {
        $e = new EncryptionException('boom');
        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame('boom', $e->getMessage());
    }

    public function testUnsupportedNationalAlgorithmExceptionIsEncryptionException(): void
    {
        $e = new UnsupportedNationalAlgorithmException('unsupported');
        self::assertInstanceOf(EncryptionException::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testUnavailableNationalAlgorithmsThrowUnsupported(): void
    {
        foreach (['sm1', 'sm7', 'sm9'] as $method) {
            try {
                UnavailableNationalAlgorithms::$method();
                self::fail("{$method}() should have thrown.");
            } catch (UnsupportedNationalAlgorithmException $e) {
                self::assertNotEmpty($e->getMessage(), "{$method}() exception message must not be empty");
            }
        }
    }

    public function testThrownRegistryErrorsAreEncryptionException(): void
    {
        // 抽查一条真实抛出路径，验证异常类型在整个库中一致。
        try {
            UnavailableNationalAlgorithms::sm1();
            self::fail('sm1() should have thrown.');
        } catch (EncryptionException $e) {
            self::assertInstanceOf(UnsupportedNationalAlgorithmException::class, $e);
        }
    }
}
