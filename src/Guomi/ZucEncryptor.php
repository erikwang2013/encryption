<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Guomi;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Internal\ZucEngine;
use Erikwang2013\Encryption\Internal\EncryptThenMacBlob;

/**
 * ZUC-128 流密码 + HMAC-SHA256（encrypt-then-mac）：载荷格式 v1 | IV(16) | MAC(32) | 密文（与密钥流 XOR）。
 * 密钥长度 16 字节；IV 每次加密随机生成并随密文携带。
 */
final class ZucEncryptor implements EncryptorInterface
{
    use EncryptThenMacBlob;

    public const KEY_LEN = 16;
    public const IV_LEN = 16;
    public const MAC_LEN = 32;
    private const PREFIX = 'v1';

    public function __construct(
        private readonly string $key,
        private readonly string $identifier = 'zuc-128',
    ) {
        if (strlen($this->key) !== 16) {
            throw new EncryptionException('ZUC key must be exactly 16 bytes.');
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LEN);

        return $this->packWithMac($iv, $this->xorKeystream($this->key, $iv, $plaintext));
    }

    public function decrypt(string $ciphertext): string
    {
        [$iv, $mac, $ct] = $this->unpackAndVerify($ciphertext, self::PREFIX);

        return $this->xorKeystream($this->key, $iv, $ct);
    }

    protected function label(): string
    {
        return 'ZUC';
    }

    private function xorKeystream(string $key, string $iv, string $data): string
    {
        $engine = new ZucEngine($key, $iv);
        $len = strlen($data);
        // 批量生成与明文等长的密钥流，尾部不足 16 字节用 substr 截断补齐（密钥流与明文长度必须一致，
        // 否则 PHP 字符串 XOR 会静默截断到短者，丢失尾部数据）
        $ks = '';
        $full = intdiv($len, 16);
        for ($j = 0; $j < $full; $j++) {
            $ks .= pack('N4', $engine->nextKey(), $engine->nextKey(), $engine->nextKey(), $engine->nextKey());
        }
        for ($j = $full * 16; $j < $len; $j += 4) {
            $ks .= substr(pack('N', $engine->nextKey()), 0, min(4, $len - $j));
        }

        return $data ^ $ks;
    }
}
