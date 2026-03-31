<?php

declare(strict_types=1);

namespace Erikwang2013\Encryption\Guomi;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Internal\ZucEngine;

/**
 * ZUC-128 流密码：载荷格式 v1 | IV(16) | 密文（与密钥流 XOR）。
 * 密钥长度 16 字节；IV 每次加密随机生成并随密文携带。
 */
final class ZucEncryptor implements EncryptorInterface
{
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
        $iv = random_bytes(16);

        return self::PREFIX . $iv . $this->xorKeystream($this->key, $iv, $plaintext);
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new EncryptionException('Invalid ZUC ciphertext prefix.');
        }
        $blob = substr($ciphertext, strlen(self::PREFIX));
        if (strlen($blob) < 16) {
            throw new EncryptionException('ZUC ciphertext too short.');
        }
        $iv = substr($blob, 0, 16);
        $ct = substr($blob, 16);

        return $this->xorKeystream($this->key, $iv, $ct);
    }

    private function xorKeystream(string $key, string $iv, string $data): string
    {
        $engine = new ZucEngine($key, $iv);
        $out = '';
        $len = strlen($data);
        $i = 0;
        while ($i < $len) {
            $word = $engine->nextKey();
            for ($j = 0; $j < 4 && $i < $len; $j++, $i++) {
                $byte = ($word >> (8 * (3 - $j))) & 0xff;
                $out .= chr(ord($data[$i]) ^ $byte);
            }
        }

        return $out;
    }
}
