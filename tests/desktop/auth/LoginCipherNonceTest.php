<?php

/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * SCRUM-7779：登录密文口径（复用 legacy login_cipher）与防重放（修法 2）
 *
 * - 密文：AES-128-CBC + ZeroPadding + base64；key = 服务端日期 + "eDu189"；iv = OPENSSL_CPIHER_IV[:16]
 * - 防重放：前端生成 nonce（32 位 hex）+ 密文内时间戳；后端只记「已用 nonce」并原子占位
 */
#[RunTestsInSeparateProcesses]
final class LoginCipherNonceTest extends TestCase
{
    private const NONCE = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        // 注意：config.php 自身会定义 ROOT_DIR/APP_DIR，预先定义会触发 "already defined" 警告
        $root = dirname(__DIR__, 3);
        chdir($root);
        include_once $root . '/config/config.php';
        include_once $root . '/app/base/defined.php';
        include_once $root . '/app/base/kernel.php';

        include_once $root . '/app/desktop/lib/auth/login/cipher.php';
        include_once $root . '/app/desktop/lib/auth/login/nonce.php';
    }

    /** 与前端同口径造密文（AES-128-CBC + ZeroPadding + base64） */
    private function encrypt(array $payload, ?string $date = null): string
    {
        $key = ($date ?? date('Y-m-d')) . 'eDu189';
        $iv = substr(OPENSSL_CPIHER_IV, 0, 16);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $pad = (16 - (strlen($json) % 16)) % 16;

        return openssl_encrypt($json . str_repeat("\x00", $pad), 'aes-128-cbc', $key, 0, $iv);
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    public function testDecryptsFrontendStyleCipher(): void
    {
        $payload = [
            'uname' => 'admin',
            'password' => 'p@ss中文!',
            'terminal' => 'PC',
            'nonce' => self::NONCE,
            'ts' => $this->nowMs(),
        ];
        $decoded = desktop_auth_login_cipher::decrypt($this->encrypt($payload));

        $this->assertIsArray($decoded);
        $this->assertSame('admin', $decoded['uname']);
        $this->assertSame('p@ss中文!', $decoded['password']);
        $this->assertSame(self::NONCE, $decoded['nonce']);
    }

    public function testDecryptToleratesAdjacentDays(): void
    {
        $yesterday = date('Y-m-d', time() - 86400);
        $decoded = desktop_auth_login_cipher::decrypt($this->encrypt(['uname' => 'a', 'password' => 'b'], $yesterday));

        $this->assertIsArray($decoded);
        $this->assertSame('a', $decoded['uname']);
    }

    public function testRejectsTamperedOrGarbageCipher(): void
    {
        $good = $this->encrypt(['uname' => 'admin', 'password' => 'x', 'nonce' => self::NONCE]);

        $this->assertNull(desktop_auth_login_cipher::decrypt('AAAA' . substr($good, 4)));
        $this->assertNull(desktop_auth_login_cipher::decrypt('not-a-cipher'));
        $this->assertNull(desktop_auth_login_cipher::decrypt(''));
    }

    /**
     * nonce 只能消费一次：第 2、3 次必须被拒（不能出现「DEL 后未回写 ⇒ 第 3 次放过」的漏洞）
     */
    public function testClaimIsSingleUse(): void
    {
        $nonce = bin2hex(random_bytes(16));

        $this->assertTrue(desktop_auth_login_nonce::claim($nonce));
        $this->assertFalse(desktop_auth_login_nonce::claim($nonce));
        $this->assertFalse(desktop_auth_login_nonce::claim($nonce));
    }

    /**
     * 本地/降级环境未接 Redis（kvstore 落到 base_kvstore_filesystem）时不能让登录 500：
     * 该后端没有 increment()，claim() 必须降级为「读-写」占位，且依旧只放行一次。
     */
    public function testClaimWorksOnNonAtomicBackend(): void
    {
        $root = dirname(__DIR__, 3);
        define('FORCE_KVSTORE_STORAGE', 'base_kvstore_filesystem');
        include_once $root . '/app/base/lib/interface/kvstore/base.php';
        include_once $root . '/app/base/lib/interface/kvstore/extension.php';
        include_once $root . '/app/base/lib/static/utils.php';
        include_once $root . '/app/base/lib/kvstore/abstract.php';
        include_once $root . '/app/base/lib/kvstore/filesystem.php';
        include_once $root . '/app/base/lib/kvstore.php';
        base_kvstore::config_persistent(false);   // 单测不落 sdb_base_kvstore 持久层

        $nonce = bin2hex(random_bytes(16));

        $this->assertTrue(desktop_auth_login_nonce::claim($nonce));
        $this->assertFalse(desktop_auth_login_nonce::claim($nonce));
        $this->assertFalse(desktop_auth_login_nonce::claim($nonce));
    }

    public function testClaimRejectsMalformedNonce(): void
    {
        $this->assertFalse(desktop_auth_login_nonce::claim(''));
        $this->assertFalse(desktop_auth_login_nonce::claim('xyz'));
        $this->assertFalse(desktop_auth_login_nonce::claim('0123456789abcdef'));   // 长度不足
    }

    /**
     * 时间戳窗口：过期/超前都拒；窗口内通过（保证「录走的密文过期即失效」）
     */
    public function testFreshTimestampWindow(): void
    {
        $now = $this->nowMs();

        $this->assertTrue(desktop_auth_login_nonce::freshTimestamp($now));
        $this->assertTrue(desktop_auth_login_nonce::freshTimestamp($now - 60_000));
        $this->assertTrue(desktop_auth_login_nonce::freshTimestamp($now + 60_000));

        $this->assertFalse(desktop_auth_login_nonce::freshTimestamp($now - (desktop_auth_login_nonce::TS_MAX_AGE + 10) * 1000));
        $this->assertFalse(desktop_auth_login_nonce::freshTimestamp($now + (desktop_auth_login_nonce::TS_MAX_SKEW + 10) * 1000));
        $this->assertFalse(desktop_auth_login_nonce::freshTimestamp(0));
    }

    /**
     * 缓存 TTL 必须大于时间戳窗口，否则「缓存过期但时间戳仍新鲜」会出现重放窗口
     */
    public function testCacheTtlCoversTimestampWindow(): void
    {
        $this->assertGreaterThan(desktop_auth_login_nonce::TS_MAX_AGE, desktop_auth_login_nonce::CACHE_TTL);
    }

    /**
     * 账号解析（SCRUM-7779）：带密文时只认密文里的 uname，顶层明文 username 一概忽略
     * —— 否则「前端置空 username」被绕过后仍可用明文账号请求。
     */
    public function testResolveUsernamePrefersCipherOverPlaintext(): void
    {
        $cipher = $this->encrypt(['uname' => 'real_user', 'password' => '', 'nonce' => self::NONCE]);

        $this->assertSame('real_user', desktop_auth_login_cipher::resolveUsername([
            'username' => '',
            'login_cipher' => $cipher,
        ]));

        $this->assertSame('real_user', desktop_auth_login_cipher::resolveUsername([
            'username' => 'attacker_supplied',
            'login_cipher' => $cipher,
        ]));
    }

    /** 密文损坏 ⇒ 返回 null（调用方按「登录信息异常」拒绝），不得回退明文 username */
    public function testResolveUsernameRejectsTamperedCipherWithoutPlaintextFallback(): void
    {
        $this->assertNull(desktop_auth_login_cipher::resolveUsername([
            'username' => 'admin',
            'login_cipher' => 'not-a-cipher',
        ]));

        $good = $this->encrypt(['uname' => 'admin', 'password' => 'x', 'nonce' => self::NONCE]);
        $this->assertNull(desktop_auth_login_cipher::resolveUsername([
            'username' => 'admin',
            'login_cipher' => 'AAAA' . substr($good, 4),
        ]));
    }

    /** 无密文（老客户端 / legacy GET 直连）时回退明文 username，再回退 query 兜底 */
    public function testResolveUsernameFallsBackToPlaintextWhenNoCipher(): void
    {
        $this->assertSame('admin', desktop_auth_login_cipher::resolveUsername(['username' => ' admin ']));
        $this->assertSame('legacy_user', desktop_auth_login_cipher::resolveUsername([], 'legacy_user'));
        $this->assertSame('', desktop_auth_login_cipher::resolveUsername([], ''));
    }

    /** 登录前接口的入参必须走 resolveUsername（禁止回退顶层明文 username 的读法） */
    public function testLoginFrontApisResolveUsernameThroughCipher(): void
    {
        $root = dirname(__DIR__, 3);
        $jwt = (string)file_get_contents($root . '/app/desktop/controller/api/jwt.php');
        $flow = (string)file_get_contents($root . '/app/desktop/lib/auth/jwt/flow.php');

        // jwt.php：genIdaasCaptcha + sendSecondFactorCode（login 走 flow 的密文+nonce 路径）
        $this->assertSame(2, substr_count($jwt, 'desktop_auth_login_cipher::resolveUsername('));
        $this->assertStringNotContainsString("\$payload['username']", $jwt);
        // flow.php：verifySecondFactorBrowser（loginBrowser 内另有密文覆盖写法）
        $this->assertStringContainsString(
            "desktop_auth_login_cipher::resolveUsername(\$payload)",
            $flow
        );
    }
}