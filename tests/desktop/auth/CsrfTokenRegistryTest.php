<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * SCRUM-7767: CSRF 双提交校验口径 —— 由「header 必须严格等于 cookie」
 * 放宽为「header 必须命中服务端滚动注册表（TOKEN_MAX 窗口）」。
 *
 * 线上回归场景（403 csrf_failed 的根因）：并发写请求与 JWT refresh 都会轮换
 * oms-csrf-token cookie，客户端在「读到 token」与「请求真正发出」之间被轮换
 * ⇒ header(旧) != cookie(新)。旧实现直接判失败（403）；新实现必须接受仍在
 * 注册表窗口内的旧 token。
 */
#[RunTestsInSeparateProcesses]
final class CsrfTokenRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(__DIR__ . '/../../..');
        $_SERVER = [];
        $_GET = [];
        $_COOKIE = [];
        $_SESSION = [];
        $GLOBALS['__test_cookie_writes'] = [];
        $this->loadCsrf();
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_COOKIE = [];
        $_SESSION = [];
        $GLOBALS['__test_cookie_writes'] = [];
        parent::tearDown();
    }

    public function testRotatedTokenWithMatchingRegisteredHeaderStillVerifies(): void
    {
        // 注册表窗口里有 T1；cookie 已被并发请求轮换成 T2；在飞请求带的是它读到的 T1
        $_SESSION['_oms_csrf_key_'] = ['T2', 'T1'];

        $csrf = new desktop_csrf(null);

        $this->assertTrue(
            $csrf->verify('T1', 'T2'),
            '窗口内的旧 token 必须仍被接受，否则在飞请求会被打成 403 csrf_failed'
        );
    }

    public function testUnregisteredTokenIsRejected(): void
    {
        $_SESSION['_oms_csrf_key_'] = ['T1'];

        $csrf = new desktop_csrf(null);

        $this->assertFalse($csrf->verify('FORGED-TOKEN', 'T1'), '未登记的 header 必须拒绝');
        // header/cookie 自洽但注册表被清 ⇒ 走既有补种逻辑放行（保留旧行为，见 testSelfConsistentTokenIsReseededWhenRegistryCleared）
        $this->assertTrue($csrf->verify('FORGED-TOKEN', 'FORGED-TOKEN'));
    }

    public function testRegistryHitIgnoresCookieValue(): void
    {
        // 新口径下 cookie 仅作载体：header 命中注册表即通过（cookie 被轮换成什么都不影响）
        $_SESSION['_oms_csrf_key_'] = ['T1'];

        $csrf = new desktop_csrf(null);

        $this->assertTrue($csrf->verify('T1', 'ROTATED-AWAY'));
    }

    public function testSelfConsistentTokenIsReseededWhenRegistryCleared(): void
    {
        $_SESSION['_oms_csrf_key_'] = [];

        $csrf = new desktop_csrf(null);

        $this->assertTrue($csrf->verify('T1', 'T1'), '注册表被清但 header/cookie 自洽 ⇒ 补种放行，免整站重登');
        $this->assertContains('T1', $_SESSION['_oms_csrf_key_']);
    }

    public function testMissingHeaderOrCookieIsRejected(): void
    {
        $_SESSION['_oms_csrf_key_'] = ['T1'];

        $csrf = new desktop_csrf(null);

        $this->assertFalse($csrf->verify('', 'T1'), '缺 header 必须拒绝');
        $this->assertFalse($csrf->verify('T1', ''), '缺 cookie 必须拒绝');
    }

    public function testSuccessfulVerifyRotatesToken(): void
    {
        $_SESSION['_oms_csrf_key_'] = ['T1'];

        $csrf = new desktop_csrf(null);

        $this->assertTrue($csrf->verify('T1', 'T1'));

        $writes = $GLOBALS['__test_cookie_writes'] ?? [];
        $this->assertNotEmpty($writes, '校验成功后必须轮换 CSRF cookie');
        $last = $writes[count($writes) - 1]['token'];
        $this->assertNotSame('T1', $last, '轮换必须写入新 token');
        $this->assertContains($last, $_SESSION['_oms_csrf_key_'], '新 token 必须进入注册表窗口');
    }

    /**
     * 桩：cachecore 关闭 ⇒ token 注册表走 $_SESSION；transport 只记录 cookie 写入。
     */
    private function loadCsrf(): void
    {
        if (!class_exists('cachecore', false)) {
            eval('class cachecore { public static function enable() { return false; } public static function fetch($k) { return null; } public static function store($k, $v, $ttl = 0) { return true; } }');
        }
        if (!class_exists('desktop_auth_jwt_transport', false)) {
            eval('class desktop_auth_jwt_transport { public static function setCsrfCookie($token, $expiresAt = null) { $GLOBALS["__test_cookie_writes"][] = ["token" => $token, "expiresAt" => $expiresAt]; } }');
        }
        require_once 'app/desktop/lib/csrf.php';
    }
}
