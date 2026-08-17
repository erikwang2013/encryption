<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Guomi;

use CryptoSm\Exception\CryptoException;
use CryptoSm\Exception\InvalidKeyException;
use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;
use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Internal\EncryptThenMacBlob;

/**
 * 国密 SM4-CBC（PKCS#5/7 填充）+ HMAC-SHA256（encrypt-then-mac），依赖 OpenSSL 的 SM4-CBC 与 pohoc/crypto-sm 封装。
 * 载荷：v1 | IV(16) | MAC(32) | 密文（hex 解码后的二进制）。
 */
final class Sm4CbcEncryptor implements EncryptorInterface
{
    use EncryptThenMacBlob;

    public const KEY_LEN = 16;
    public const IV_LEN = 16;
    public const MAC_LEN = 32;
    private const PREFIX = 'v1';

    public function __construct(
        private readonly string $key,
        private readonly string $identifier = 'sm4-cbc',
    ) {
        if (strlen($this->key) !== 16) {
            throw new EncryptionException('SM4 key must be exactly 16 bytes.');
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LEN);
        $options = (new Sm4Options())
            ->setMode(Sm4::MODE_CBC)
            ->setIv(bin2hex($iv))
            ->setPadding('pkcs5');
        try {
            $hex = Sm4::encrypt($plaintext, bin2hex($this->key), $options);
        } catch (InvalidKeyException | CryptoException $e) {
            throw new EncryptionException($e->getMessage(), (int) $e->getCode(), $e);
        }
        $ct = hex2bin($hex);
        if ($ct === false) {
            throw new EncryptionException('SM4 encryption failed.');
        }

        return $this->packWithMac($iv, $ct);
    }

    public function decrypt(string $ciphertext): string
    {
        [$iv, $mac, $ct] = $this->unpackAndVerify($ciphertext, self::PREFIX);
        $options = (new Sm4Options())
            ->setMode(Sm4::MODE_CBC)
            ->setIv(bin2hex($iv))
            ->setPadding('pkcs5');
        try {
            return Sm4::decrypt(bin2hex($ct), bin2hex($this->key), $options);
        } catch (InvalidKeyException | CryptoException $e) {
            throw new EncryptionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    protected function label(): string
    {
        return 'SM4';
    }
}
