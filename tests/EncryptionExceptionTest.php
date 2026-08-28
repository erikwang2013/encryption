<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Exception\UnsupportedNationalAlgorithmException;
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
}
