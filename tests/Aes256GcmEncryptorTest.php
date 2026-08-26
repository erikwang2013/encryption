<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

final class Aes256GcmEncryptorTest extends TestCase
{
    public function testCiphertextDecryptableByNativeOpenssl(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $e = new Aes256GcmEncryptor($key);
        $blob = substr($e->encrypt('interop'), strlen('v1'));
        $iv = substr($blob, 0, Aes256GcmEncryptor::IV_LEN);
        $tag = substr($blob, Aes256GcmEncryptor::IV_LEN, Aes256GcmEncryptor::TAG_LEN);
        $ct = substr($blob, Aes256GcmEncryptor::IV_LEN + Aes256GcmEncryptor::TAG_LEN);
        self::assertSame(12, strlen($iv), 'IV must be 12 bytes');
        self::assertSame(16, strlen($tag), 'Tag must be 16 bytes');
        self::assertSame('interop', openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag));
    }

    public function testNativeOpensslCiphertextDecryptableByClass(): void
    {
        $key = random_bytes(Aes256GcmEncryptor::KEY_LEN);
        $e = new Aes256GcmEncryptor($key);
        $iv = random_bytes(Aes256GcmEncryptor::IV_LEN);
        $plain = 'native payload 世界';
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        self::assertSame($plain, $e->decrypt('v1' . $iv . $tag . $ct));
    }

    public function testWrongKeyFails(): void
    {
        $e = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN));
        $ct = $e->encrypt('secret');
        $other = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN));
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('decryption failed');
        $other->decrypt($ct);
    }

    public function testEmptyPlaintextRoundTrip(): void
    {
        $e = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN));
        self::assertSame('', $e->decrypt($e->encrypt('')));
    }

    public function testCustomIdentifier(): void
    {
        $e = new Aes256GcmEncryptor(random_bytes(Aes256GcmEncryptor::KEY_LEN), 'custom-gcm');
        self::assertSame('custom-gcm', $e->getIdentifier());
    }
}
