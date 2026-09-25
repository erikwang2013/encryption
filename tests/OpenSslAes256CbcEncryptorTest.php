<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\Exception\EncryptionException;

final class OpenSslAes256CbcEncryptorTest extends TestCase
{
    public function testCiphertextDecryptableByNativeOpenssl(): void
    {
        $key = random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN);
        $e = new OpenSslAes256CbcEncryptor($key);
        $parts = $this->splitBlob($e->encrypt('interop'));

        $macKey = hash_hmac('sha256', $key, 'dgn:enc:hmac', true);
        self::assertTrue(hash_equals($parts['mac'], hash_hmac('sha256', $parts['iv'] . $parts['ct'], $macKey, true)));
        self::assertSame('interop', openssl_decrypt($parts['ct'], 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $parts['iv']));
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

    public function testV2MacDerivationRoundTripsAndUsesCorrectedMacKey(): void
    {
        $key = random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN);
        $v2 = new OpenSslAes256CbcEncryptor($key, macDerivation: 'v2');
        $parts = $this->splitBlob($v2->encrypt('v2 payload'));
        // 载荷前缀是密文格式版本，与派生方案无关：v2 派生方案下仍是 'v1'。
        self::assertSame('v1', $parts['prefix']);
        // v2 的 MAC 密钥用密文密钥作 HMAC key、标签作 message（默认 v1 相反）。
        $macKey = hash_hmac('sha256', 'dgn:enc:hmac', $key, true);
        self::assertTrue(hash_equals($parts['mac'], hash_hmac('sha256', $parts['iv'] . $parts['ct'], $macKey, true)));
        self::assertSame('v2 payload', $v2->decrypt($v2->encrypt('v2 payload')));
    }

    public function testV1CiphertextStillDecryptsAndV2CiphertextIsNotReadableByV1(): void
    {
        $key = random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN);
        $v1 = new OpenSslAes256CbcEncryptor($key); // 默认 v1，历史构造调用不变
        self::assertSame('legacy', $v1->decrypt($v1->encrypt('legacy')));
        try {
            $v1->decrypt((new OpenSslAes256CbcEncryptor($key, macDerivation: 'v2'))->encrypt('payload'));
            self::fail('A v1 encryptor must not decrypt v2 ciphertext: the MAC keys differ.');
        } catch (EncryptionException $e) {
            self::assertSame('MAC verification failed.', $e->getMessage());
        }
    }

    public function testUnknownMacKeySchemeThrows(): void
    {
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('Unknown AES-256-CBC MAC key derivation scheme "v3" (expected "v1" or "v2").');
        new OpenSslAes256CbcEncryptor(random_bytes(OpenSslAes256CbcEncryptor::KEY_LEN), macDerivation: 'v3');
    }
}
