# 单元测试报告（全模块）

- 日期：2026-08-27
- 项目：/home/wwwroot/erikwang2013/encryption（composer 包，PHPUnit 10.5.63）
- 执行人：测试团队负责人（test-lead 汇总）

## 一、测试环境

```
PHP 8.3.7 (cli) (built: Jul 9 2025 10:04:19) (NTS)
Zend Engine v4.3.7, ionCube PHP Loader v14.4.1
```

| 扩展 | 状态 | 说明 |
|------|------|------|
| openssl | 可用 | 但 PHP 8.3 的 openssl_digest 不支持 sm3（互操作测试因此跳过） |
| sodium | 可用 | XChaCha20 全链路实测通过 |
| gmp | **缺失** | SM2 全部功能测试跳过（最大覆盖盲点，见第四节） |

运行命令：`vendor/bin/phpunit`（工作区根目录）。

## 二、测试覆盖矩阵（最终结果：通过）

**总体：225 个测试，4469 个断言，通过 215，失败 0，跳过 10，错误 0。**

### 2.1 国密模块（SM2/SM3/SM4/ZUC）— 70 测试

| 测试文件 | 测试数 | 断言 | 跳过 | 结果 |
|---|---|---|---|---|
| tests/GuomiAlgorithmsTest.php | 21 | 43 | 1（SM2，gmp） | 通过 |
| tests/GuomiSm2Test.php | 7 | 8 | 3（gmp） | 通过 |
| tests/GuomiSm3Test.php | 9 | 23 | 1（openssl 无 sm3 digest） | 通过 |
| tests/GuomiSm4Test.php | 9 | 25 | 0 | 通过 |
| tests/GuomiZucTest.php | 15 | 4062 | 0 | 通过 |
| tests/GuomiUnavailableTest.php | 5 | 9 | 0 | 通过 |
| tests/Sm2AsymmetricCipherTest.php | 4 | 5 | 1（gmp） | 通过 |

要点：SM3 官方 KAT 向量、SM4 GB/T 官方 ECB KAT 向量、ZUC 全零/全一密钥 KAT（4062 断言）均通过；SM4/ZUC 的 CBC 往返、篡改检测（MAC）、截断、错误密钥长度、边界长度（0/1/15/16/17/31/32）覆盖完整。

### 2.2 对称加密模块（AES-256-CBC/GCM、XChaCha20、HKDF/PBKDF2、SHA-256）— 52 测试

| 测试文件 | 测试数 | 断言 | 跳过 | 结果 |
|---|---|---|---|---|
| tests/Aes256GcmEncryptorTest.php | 5 | 8 | 0 | 通过 |
| tests/OpenSslAes256CbcEncryptorTest.php | 5 | 7 | 0 | 通过 |
| tests/SodiumXChaCha20EncryptorTest.php | 5 | 7 | 0 | 通过 |
| tests/HkdfSha256Test.php | 7 | 14 | 0 | 通过 |
| tests/Pbkdf2Sha256Test.php | 6 | 13 | 0 | 通过 |
| tests/Sha256HasherTest.php | 5 | 13 | 0 | 通过 |
| tests/CryptoPrimitivesTest.php（历史综合） | 19 | 34 | 2（SM2，gmp） | 通过 |

要点：GCM 篡改/截断/坏前缀、CBC 篡改 MAC、XChaCha20 与原生 sodium 双向互操作、HKDF/PBKDF2 空盐与零长度参数边界均覆盖；SHA-256 与哈希管理器/注册表行为验证。

### 2.3 核心模块（EncryptionManager/Factory/Registries/EncryptThenMacBlob/异常）— 103 测试

| 测试文件 | 测试数 | 断言 | 跳过 | 结果 |
|---|---|---|---|---|
| tests/EncryptionManagerTest.php | 8 | 13 | 0 | 通过 |
| tests/EncryptionManagerFactoryTest.php | 8 | 22 | 0 | 通过 |
| tests/EncryptorRegistryTest.php | 3 | 6 | 0 | 通过 |
| tests/AbstractRegistryTest.php | 6 | 9 | 0 | 通过 |
| tests/AsymmetricCipherRegistryTest.php | 4 | 7 | 0 | 通过 |
| tests/AsymmetricCryptoManagerTest.php | 6 | 6 | 2（gmp） | 通过 |
| tests/HasherRegistryTest.php | 4 | 9 | 0 | 通过 |
| tests/HashingManagerTest.php | 6 | 10 | 0 | 通过 |
| tests/KeyDerivationManagerTest.php | 6 | 10 | 0 | 通过 |
| tests/KeyDerivationRegistryTest.php | 4 | 8 | 0 | 通过 |
| tests/PasswordBasedKdfManagerTest.php | 6 | 10 | 0 | 通过 |
| tests/PasswordBasedKdfRegistryTest.php | 4 | 7 | 0 | 通过 |
| tests/EncryptThenMacBlobTest.php | 7 | 17 | 0 | 通过 |
| tests/EncryptionExceptionTest.php | 4 | 8 | 0 | 通过 |
| tests/EncryptorRoundTripTest.php（历史综合） | 27 | 56 | 0 | 通过 |

要点：Factory 错误主密钥长度、未知默认算法、SM4/ZUC 注册与往返；Manager 默认标识符切换与未知标识符；各注册表重复注册/未知标识符/空标识符；EncryptThenMacBlob 加解密/篡改/截断（此前无直接单测，本次补齐）。

### 跳过明细（10 个，全部为环境所致，非代码缺陷）

| 测试 | 原因 |
|---|---|
| GuomiSm2Test（3）、Sm2AsymmetricCipherTest（1）、GuomiAlgorithmsTest（1）、CryptoPrimitivesTest（2）、AsymmetricCryptoManagerTest（2） | ext-gmp not loaded（共 9 个 SM2 用例） |
| GuomiSm3Test::testDigestHexMatchesOpenSslWhenAvailable | ext-openssl 不支持 sm3 digest（PHP 8.3 限制） |

## 三、发现的 bug 汇总

**实现层（src/）：未发现缺陷。** 本轮全部失败均为测试代码问题，且均已在测试编写过程中被修复，未进入最终结果：

1. **tests/SodiumXChaCha20EncryptorTest.php**（过程中）：调用未定义的 `makeEncryptor()`，PHP Fatal Error——测试模板残留，tester 已自行修复。
2. **异常消息大小写期望错误（共 7 处，会话过程中发现并已修复）**：新测试期望 `'Unknown Encryptor/Hasher/Password KDF/Asymmetric cipher: …'`（首字母大写），而实现约定为小写标签——`src/AbstractRegistry.php:50` 使用 `sprintf('Unknown %s: %s', $this->itemNameLower(), $identifier)`。涉及 tests/AbstractRegistryTest.php、EncryptionManagerTest.php、HasherRegistryTest.php、HashingManagerTest.php、PasswordBasedKdfRegistryTest.php、PasswordBasedKdfManagerTest.php、AsymmetricCipherRegistryTest.php。与既有测试 `testKeyDerivationRegistryUnknownMessagePreservesCase`（保留标识符大小写）约定一致，故判定为测试期望错误而非实现 bug。

## 四、覆盖率盲点与后续建议

1. **SM2 全链路未验证（最大盲点）**：本机无 ext-gmp，密钥生成/加解密往返/错误密钥/管理器路由共 9 个用例全部跳过。建议在安装 gmp 的 CI 或开发机复跑 `GuomiSm2Test`、`Sm2AsymmetricCipherTest`、`AsymmetricCryptoManagerTest`。
2. **SM3 与 OpenSSL 互操作未验证**：PHP 8.3 的 openssl 扩展无 sm3 digest。已有官方 KAT 向量兜底（testSm3KatVector），可接受；升级到支持 sm3 digest 的运行时后可补跑。
3. **加密原语性能/基准**：ZUC 提速 2.6x 的回归风险建议用基准测试而非单测覆盖（如 1MB 数据吞吐）。
4. **时序安全**：错误密钥/篡改密文的解密耗时无明显分支差异，目前无任何定时断言，建议后续以性能测试补充。
5. **EncryptThenMacBlob 的组合场景**：已补 7 个用例（往返/篡改/截断/坏前缀），但 CBC+MAC 的边界（超长密文、空明文）仍可扩展。
6. **随机性**：密钥/IV 随机源未做重复性断言（CSPRNG 保证依赖 PHP 层，属合理盲区）。

## 五、结论

- **状态：通过（GREEN）**。225 测试 / 4469 断言 / 0 失败 / 0 错误，10 个跳过全部为环境（ext-gmp、openssl sm3）所致。
- 三个模块（国密 70、对称 52、核心 103）均无实现缺陷；官方向量（SM3 KAT、SM4 GB/T ECB KAT、ZUC KAT）与原生 sodium 互操作测试为本次最高价值覆盖。
- **放行建议**：本轮可合并；但 SM2 相关 9 个用例必须在具备 ext-gmp 的环境补跑通过后才能声明国密全链路可用。
