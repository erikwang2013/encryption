<?php

declare(strict_types=1);

namespace Erikwang2013\Encryption\Guomi;

use CryptoSm\SM4\Sm4;
use CryptoSm\SM4\Sm4Options;
use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 国密 SM4-CBC（PKCS#5/7 填充），依赖 OpenSSL 的 SM4-CBC 与 pohoc/crypto-sm 封装。
 * 载荷：v1 | IV(16) | 密文（hex 解码后的二进制）。
 */
final class Sm4CbcEncryptor implements EncryptorInterface
{
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
        $iv = random_bytes(16);
        $keyHex = bin2hex($this->key);
        $ivHex = bin2hex($iv);
        $options = (new Sm4Options())
            ->setMode(Sm4::MODE_CBC)
            ->setIv($ivHex)
            ->setPadding('pkcs5');
        $hex = Sm4::encrypt($plaintext, $keyHex, $options);
        $ct = hex2bin($hex);
        if ($ct === false) {
            throw new EncryptionException('SM4 encryption failed.');
        }

        return self::PREFIX . $iv . $ct;
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new EncryptionException('Invalid SM4 ciphertext prefix.');
        }
        $blob = substr($ciphertext, strlen(self::PREFIX));
        if (strlen($blob) < 16) {
            throw new EncryptionException('SM4 ciphertext too short.');
        }
        $iv = substr($blob, 0, 16);
        $ct = substr($blob, 16);
        $keyHex = bin2hex($this->key);
        $ivHex = bin2hex($iv);
        $options = (new Sm4Options())
            ->setMode(Sm4::MODE_CBC)
            ->setIv($ivHex)
            ->setPadding('pkcs5');

        return Sm4::decrypt(bin2hex($ct), $keyHex, $options);
    }
}
