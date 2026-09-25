<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Guomi;

use CryptoSm\SM3\Sm3;
use Erikwang2013\Encryption\Contract\HasherInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * 国密 SM3 杂凑。
 *
 * 优先走 OpenSSL 原生摘要（OpenSSL 3.x 提供 sm3，实测约 90 MB/s、内存平坦）；
 * 老版本 OpenSSL 不含 sm3 时回退 pohoc/crypto-sm 的纯 PHP 实现——后者对大输入
 * 是二次方内存开销（1 MiB 约需 490 MB、耗时 85 s），故只作兜底。
 * 两条路径输出逐字节一致（含空串与 55/56/64 字节分组边界，见 Sm3HasherTest）。
 */
final class Sm3Hasher implements HasherInterface
{
    /** OpenSSL 是否提供 sm3 摘要；进程内只需探测一次 */
    private static ?bool $nativeAvailable = null;

    public function __construct(
        private string $identifier = 'sm3',
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function digest(string $data): string
    {
        if (self::nativeAvailable()) {
            $bin = openssl_digest($data, 'sm3', true);
            if ($bin !== false) {
                return $bin;
            }
        }

        $bin = hex2bin(Sm3::hash($data));
        if ($bin === false) {
            throw new EncryptionException('SM3 hex2bin conversion failed.');
        }

        return $bin;
    }

    public function digestHex(string $data): string
    {
        return bin2hex($this->digest($data));
    }

    /**
     * 原生路径是否可用（不做缓存之外的副作用，便于测试直接断言）。
     */
    public static function nativeAvailable(): bool
    {
        return self::$nativeAvailable ??= in_array(
            'sm3',
            array_map('strtolower', openssl_get_md_methods()),
            true
        );
    }
}
