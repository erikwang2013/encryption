<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Hash\Sha256Hasher;
use PHPUnit\Framework\TestCase;

final class Sha256HasherTest extends TestCase
{
    public function testDigestMatchesNativeHash(): void
    {
        $h = new Sha256Hasher();
        foreach (['abc', '', 'hello 世界', random_bytes(1000)] as $data) {
            self::assertSame(hash('sha256', $data, true), $h->digest($data), 'binary digest');
            self::assertSame(hash('sha256', $data, false), $h->digestHex($data), 'hex digest');
        }
    }

    public function testKnownVector(): void
    {
        $h = new Sha256Hasher();
        self::assertSame(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            $h->digestHex('abc')
        );
    }

    public function testBinaryDigestEqualsDecodedHex(): void
    {
        $h = new Sha256Hasher();
        self::assertSame($h->digest('x'), hex2bin($h->digestHex('x')));
    }

    public function testEmptyInput(): void
    {
        $h = new Sha256Hasher();
        self::assertSame(32, strlen($h->digest('')));
        self::assertSame('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $h->digestHex(''));
    }

    public function testCustomIdentifier(): void
    {
        self::assertSame('custom-sha256', (new Sha256Hasher('custom-sha256'))->getIdentifier());
    }
}
