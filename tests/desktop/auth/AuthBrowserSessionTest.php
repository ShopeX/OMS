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

use PHPUnit\Framework\TestCase;

final class AuthBrowserSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__, 3));
        }
        if (!defined('APP_DIR')) {
            define('APP_DIR', ROOT_DIR . '/app');
        }
        chdir(ROOT_DIR);
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';

        $_SESSION = [];
    }

    public function testBackendDoesNotTrackBrowserIdleActivity(): void
    {
        $request = file_get_contents('app/desktop/lib/auth/request.php');
        $guard = file_get_contents('app/desktop/lib/auth/jwt/guard.php');
        $flow = file_get_contents('app/desktop/lib/auth/jwt/flow.php');

        $this->assertStringNotContainsString('SESSION_LAST_ACTIVITY_AT', $request);
        $this->assertStringNotContainsString('touchBrowserActivity', $request);
        $this->assertStringNotContainsString('isBrowserSessionIdle', $guard);
        $this->assertStringNotContainsString('isBrowserSessionIdle', $flow);
        $this->assertStringNotContainsString('clearBrowserActivity', $flow);
    }

    public function testJwtControllerDoesNotExposeHeartbeat(): void
    {
        $controller = file_get_contents('app/desktop/controller/api/jwt.php');

        $this->assertStringNotContainsString('public function heartbeat()', $controller);
    }

    public function testDesktopJwtDoesNotExposeLegacyMcpAuthentication(): void
    {
        $controller = file_get_contents('app/desktop/controller/api/jwt.php');
        $flow = file_get_contents('app/desktop/lib/auth/jwt/flow.php');
        $transport = file_get_contents('app/desktop/lib/auth/jwt/transport.php');

        foreach (['loginForMcp', 'refreshForMcp', 'logoutForMcp'] as $action) {
            $this->assertStringNotContainsString("public function {$action}()", $controller);
            $this->assertStringNotContainsString("'{$action}'", $controller);
        }
        $this->assertStringNotContainsString('public static function loginForMcp(', $flow);
        $this->assertStringNotContainsString('public static function refreshForMcp(', $flow);
        $this->assertStringNotContainsString('mcpTokenPayload', $transport);
        $this->assertStringContainsString('public static function logout(?string $token)', $flow);
        $this->assertStringNotContainsString('extractRefreshToken($payload)', $flow);
    }

    public function testBrowserLoginDoesNotRequireAChangedBaseSessionApi(): void
    {
        $flow = file_get_contents('app/desktop/lib/auth/jwt/flow.php');

        $this->assertStringNotContainsString("kernel::single('base_session')->regenerate_id()", $flow);
        $this->assertFalse(method_exists(base_session::class, 'regenerate_id'));
    }

    public function testBrowserRefreshReadsOnlyTheHttpOnlyCookie(): void
    {
        $_COOKIE['oms-refresh-token-key'] = 'cookie-refresh-token';

        $this->assertSame('cookie-refresh-token', desktop_auth_jwt::extractBrowserRefreshToken());
        $flow = file_get_contents('app/desktop/lib/auth/jwt/flow.php');
        $refreshStart = strpos($flow, 'public static function refresh(');
        $refreshEnd = strpos($flow, 'public static function logout(', $refreshStart);
        $browserRefresh = substr($flow, $refreshStart, $refreshEnd - $refreshStart);

        $this->assertStringContainsString('desktop_auth_jwt::extractBrowserRefreshToken()', $browserRefresh);
        $this->assertStringNotContainsString('desktop_auth_jwt::extractRefreshToken($payload)', $browserRefresh);
    }
}
