<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Encryptor\SodiumXChaCha20Encryptor;
use Erikwang2013\Encryption\Exception\EncryptionException;

final class SodiumXChaCha20EncryptorTest extends TestCase
{
    public function testTamperedCiphertextFailsAuthentication(): void
    {
        $this->skipWithoutSodium();
        $e = new SodiumXChaCha20Encryptor(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        $ct = $e->encrypt('authenticated payload');
        // 翻转 nonce 之后第一个密文字节，认证必须失败。
        $idx = strlen('v1') + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $tampered = substr($ct, 0, $idx) . chr(ord($ct[$idx]) ^ 0x01) . substr($ct, $idx + 1);
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('decryption failed');
        $e->decrypt($tampered);
    }

    public function testCiphertextDecryptableByNativeSodium(): void
    {
        $this->skipWithoutSodium();
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $e = new SodiumXChaCha20Encryptor($key);
        $parts = $this->splitBlob($e->encrypt('interop'), SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES, 0);
        self::assertSame('interop', sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($parts['ct'], '', $parts['iv'], $key));
    }

    public function testNativeSodiumCiphertextDecryptableByClass(): void
    {
        $this->skipWithoutSodium();
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $e = new SodiumXChaCha20Encryptor($key);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $plain = 'native payload 世界';
        $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, '', $nonce, $key);
        self::assertSame($plain, $e->decrypt('v1' . $nonce . $ct));
    }

    public function testWrongKeyFails(): void
    {
        $this->skipWithoutSodium();
        $e = new SodiumXChaCha20Encryptor(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        $ct = $e->encrypt('secret');
        $other = new SodiumXChaCha20Encryptor(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        $this->expectException(EncryptionException::class);
        $this->expectExceptionMessage('decryption failed');
        $other->decrypt($ct);
    }

    public function testEmptyPlaintextRoundTrip(): void
    {
        $this->skipWithoutSodium();
        $e = new SodiumXChaCha20Encryptor(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        self::assertSame('', $e->decrypt($e->encrypt('')));
    }
}
