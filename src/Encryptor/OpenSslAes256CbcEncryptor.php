<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Encryptor;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Internal\EncryptThenMacBlob;

/**
 * AES-256-CBC + HMAC-SHA256（encrypt-then-mac）；载荷格式：v1 | IV(16) | MAC(32) | Ciphertext。
 * 兼容旧环境；新系统优先用 AES-GCM 或 Sodium。
 */
final class OpenSslAes256CbcEncryptor implements EncryptorInterface
{
    use EncryptThenMacBlob;

    public const IV_LEN = 16;
    public const MAC_LEN = 32;
    public const KEY_LEN = 32;
    private const PREFIX = 'v1';

    public function __construct(
        private readonly string $key,
        private readonly string $identifier = 'aes-256-cbc-hmac',
    ) {
        if (strlen($this->key) !== self::KEY_LEN) {
            throw new EncryptionException(sprintf('AES-256-CBC key must be exactly %d bytes.', self::KEY_LEN));
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LEN);
        $ct = openssl_encrypt($plaintext, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
        if ($ct === false) {
            throw new EncryptionException('AES-256-CBC encryption failed.');
        }

        return $this->packWithMac($iv, $ct);
    }

    public function decrypt(string $ciphertext): string
    {
        [$iv, $mac, $ct] = $this->unpackAndVerify($ciphertext, self::PREFIX);
        $plain = openssl_decrypt($ct, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new EncryptionException('AES-256-CBC decryption failed.');
        }

        return $plain;
    }

    protected function label(): string
    {
        return 'AES-256-CBC';
    }

    protected function prefixError(): string
    {
        return 'Invalid ciphertext prefix for AES-256-CBC.';
    }

    protected function tooShortError(): string
    {
        return 'Ciphertext too short.';
    }

    protected function macError(): string
    {
        return 'MAC verification failed.';
    }

    protected function macGenerationError(): string
    {
        return 'HMAC generation failed.';
    }
}
