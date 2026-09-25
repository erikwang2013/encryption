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
 * 国密 SM4-CBC（PKCS#5/7 填充）+ HMAC-SHA256（encrypt-then-mac）。
 *
 * 优先走 OpenSSL 原生 sm4-cbc（约 3 倍于 vendor 的 hex 往返实现），老 OpenSSL 不含该
 * 算法时回退 pohoc/crypto-sm。两条路径密文逐字节一致（同为 PKCS#7 填充），
 * 载荷格式 v1 | IV(16) | MAC(32) | 密文 不变，历史数据可继续解密（见 GuomiSm4Test 固化向量）。
 */
final class Sm4CbcEncryptor implements EncryptorInterface
{
    use EncryptThenMacBlob;

    public const KEY_LEN = 16;
    public const IV_LEN = 16;
    public const MAC_LEN = 32;
    private const PREFIX = 'v1';

    /** OpenSSL 是否提供 sm4-cbc；进程内只需探测一次 */
    private static ?bool $nativeAvailable = null;

    public function __construct(
        private string $key,
        private string $identifier = 'sm4-cbc',
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

        return $this->packWithMac($iv, $this->cbcEncrypt($plaintext, $iv));
    }

    public function decrypt(string $ciphertext): string
    {
        [$iv, $mac, $ct] = $this->unpackAndVerify($ciphertext, self::PREFIX);

        return $this->cbcDecrypt($ct, $iv);
    }

    /**
     * OpenSSL 是否提供 sm4-cbc；进程内只需探测一次。
     */
    public static function nativeAvailable(): bool
    {
        return self::$nativeAvailable ??= in_array(
            'sm4-cbc',
            array_map('strtolower', openssl_get_cipher_methods()),
            true
        );
    }

    protected function label(): string
    {
        return 'SM4';
    }

    /**
     * 优先走 OpenSSL 原生 SM4-CBC（约 67 MB/s，免去 vendor 的 hex 往返）；
     * 老 OpenSSL 无 sm4-cbc 时回退 pohoc/crypto-sm。
     * 两条路径的密文逐字节一致：都用 PKCS#7 填充，密文格式 v1 | IV | MAC | 密文 不变。
     */
    private function cbcEncrypt(string $plaintext, string $iv): string
    {
        if (self::nativeAvailable()) {
            $ct = openssl_encrypt($plaintext, 'sm4-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
            if ($ct === false) {
                throw new EncryptionException('SM4-CBC encryption failed.');
            }

            return $ct;
        }

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

        return $ct;
    }

    private function cbcDecrypt(string $ct, string $iv): string
    {
        if (self::nativeAvailable()) {
            // MAC 已在 unpackAndVerify() 校验过，这里的填充错误不构成 oracle。
            $pt = openssl_decrypt($ct, 'sm4-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
            if ($pt === false) {
                throw new EncryptionException('SM4-CBC decryption failed.');
            }

            return $pt;
        }

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
}
