<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\Exception\EncryptionException;
use PHPUnit\Framework\TestCase;

final class OpenSslAes256CbcEncryptorTest extends TestCase
{
    public function testCiphertextDecryptableByNativeOpenssl(): void
    {
        $key = random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN);
        $e = new OpenSslAes256CbcEncryptor($key);
        $blob = substr($e->encrypt('interop'), strlen('v1'));
        $iv = substr($blob, 0, OpenSslAes256CbcEncryptor::IV_LEN);
        $mac = substr($blob, OpenSslAes256CbcEncryptor::IV_LEN, OpenSslAes256CbcEncryptor::MAC_LEN);
        $ct = substr($blob, OpenSslAes256CbcEncryptor::IV_LEN + OpenSslAes256CbcEncryptor::MAC_LEN);

        $macKey = hash_hmac('sha256', $key, 'dgn:enc:hmac', true);
        self::assertTrue(hash_equals($mac, hash_hmac('sha256', $iv . $ct, $macKey, true)));
        self::assertSame('interop', openssl_decrypt($ct, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv));
    }

    public function testNativeOpensslCiphertextDecryptableByClass(): void
    {
        $key = random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN);
        $e = new OpenSslAes256CbcEncryptor($key);
        $iv = random_bytes(OpenSslAes256CbcEncryptor::IV_LEN);
        $plain = 'native payload 世界';
        $ct = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $macKey = hash_hmac('sha256', $key, 'dgn:enc:hmac', true);
        $mac = hash_hmac('sha256', $iv . $ct, $macKey, true);
        self::assertSame($plain, $e->decrypt('v1' . $iv . $mac . $ct));
    }

    public function testWrongKeyFailsMacVerification(): void
    {
        $e = new OpenSslAes256CbcEncryptor(random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN));
        $ct = $e->encrypt('secret');
        $other = new OpenSslAes256CbcEncryptor(random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN));
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('MAC verification failed');
        $other->decrypt($ct);
    }

    public function testEmptyPlaintextRoundTrip(): void
    {
        $e = new OpenSslAes256CbcEncryptor(random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN));
        self::assertSame('', $e->decrypt($e->encrypt('')));
    }

    public function testCustomIdentifier(): void
    {
        $e = new OpenSslAes256CbcEncryptor(random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN), 'custom-cbc');
        self::assertSame('custom-cbc', $e->getIdentifier());
    }
}
