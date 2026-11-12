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
 * 自动登出根因①：CSRF cookie 生命周期与 access TTL 解耦。
 *
 * 修复前：CSRF cookie 的 expires 默认绑 access TTL（JWT_TTL，≤3600s），
 *   access 1h 到期即让 oms-csrf-token 失效 ⇒ legacy 请求在登出前持续 403。
 * 修复后：默认绑 refresh TTL（JWT_REFRESH_TTL，24h），access 到期不再连带
 *   让 CSRF 失效。
 *
 * 本测试守护两个不变式（无需 config/DB，纯单元测试）：
 *   1. configuredRefreshTtl() 必须显著大于 configuredAccessTtl()（解耦前提）；
 *   2. transport::cookieOptions() 必须原样透传 expires 时间戳（下发链路）。
 */
#[RunTestsInSeparateProcesses]
final class CsrfCookieExpiryDecouplingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(__DIR__ . '/../../..');
        $_SERVER = ['HTTP_HOST' => 'qa3-oms.tiffany.cn', 'HTTPS' => 'on'];
        $_GET = [];
        $_COOKIE = [];
        $_SESSION = [];
    }

    public function testRefreshTtlExceedsAccessTtl(): void
    {
        require_once 'app/desktop/lib/auth/jwt.php';

        $access = desktop_auth_jwt::configuredAccessTtl();
        $refresh = desktop_auth_jwt::configuredRefreshTtl();

        $this->assertGreaterThan(0, $access, 'access TTL 必须为正数');
        $this->assertGreaterThan(0, $refresh, 'refresh TTL 必须为正数');
        $this->assertGreaterThan(
            $access,
            $refresh,
            'refresh TTL 必须大于 access TTL：CSRF cookie 绑定 refresh TTL 才能活过 access 到期'
        );
    }

    public function testCookieOptionsCarriesExpiryTimestampThroughTransport(): void
    {
        // 桩掉 kernel（避免 class_exists('kernel') 触发 autoload 需要 APP_DIR/config 常量）
        if (!class_exists('kernel', false)) {
            eval('class kernel {}');
        }

        require_once 'app/desktop/lib/auth/jwt.php';
        require_once 'app/desktop/lib/auth/jwt/transport.php';

        $ts = time() + 86400;
        $opts = desktop_auth_jwt_transport::cookieOptions($ts);

        $this->assertSame(
            $ts,
            $opts['expires'],
            'cookieOptions 必须原样透传 expires，setCsrfCookie 依赖它下发 cookie 寿命'
        );
    }
}
