<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Kdf\Pbkdf2Sha256;
use PHPUnit\Framework\TestCase;

final class Pbkdf2Sha256Test extends TestCase
{
    private const ITERATIONS = 1_000;

    public function testMultipleOutputLengths(): void
    {
        $kdf = new Pbkdf2Sha256(self::ITERATIONS);
        foreach ([1, 16, 32, 64, 128] as $len) {
            self::assertSame(
                $len,
                strlen($kdf->deriveFromPassword('secret', random_bytes(16), $len)),
                "PBKDF2 length {$len}"
            );
        }
    }

    public function testDeterministic(): void
    {
        $kdf = new Pbkdf2Sha256(self::ITERATIONS);
        self::assertSame(
            $kdf->deriveFromPassword('secret', 'saltsalt', 32),
            $kdf->deriveFromPassword('secret', 'saltsalt', 32)
        );
        self::assertNotSame(
            $kdf->deriveFromPassword('secret', 'saltsalt', 32),
            $kdf->deriveFromPassword('secret', 'other-salt', 32)
        );
    }

    public function testMatchesNativeHashPbkdf2(): void
    {
        $kdf = new Pbkdf2Sha256(self::ITERATIONS);
        $salt = random_bytes(16);
        self::assertSame(
            hash_pbkdf2('sha256', 'secret', $salt, self::ITERATIONS, 40, true),
            $kdf->deriveFromPassword('secret', $salt, 40)
        );
    }

    public function testNegativeLengthRejected(): void
    {
        $kdf = new Pbkdf2Sha256(self::ITERATIONS);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('PBKDF2 output length must be positive');
        $kdf->deriveFromPassword('secret', random_bytes(16), -1);
    }

    public function testNonPositiveIterationsRejected(): void
    {
        foreach ([0, -100] as $iterations) {
            try {
                new Pbkdf2Sha256($iterations);
                self::fail("iterations={$iterations} should have thrown");
            } catch (EncryptionException $e) {
                self::assertSame('PBKDF2 iterations must be positive.', $e->getMessage());
            }
        }
    }

    public function testCustomIdentifier(): void
    {
        self::assertSame('custom-pbkdf2', (new Pbkdf2Sha256(self::ITERATIONS, 'custom-pbkdf2'))->getIdentifier());
    }
}
