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

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class BrowserCookieContractTest extends TestCase
{
    private array $savedCookie;
    private array $savedServer;

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
        putenv('JWT_SECRET=test-browser-cookie-contract');
        require_once 'vendor/autoload.php';
        require_once 'config/config.php';
        require_once 'app/base/defined.php';
        require_once 'app/base/kernel.php';

        require_once 'app/desktop/lib/auth/exception.php';
        require_once 'app/desktop/lib/auth/jwt/client.php';

        $this->savedCookie = $_COOKIE;
        $this->savedServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->savedCookie;
        $_SERVER = $this->savedServer;
        parent::tearDown();
    }

    public function testBrowserAccessUsesOmsCookieWhenLegacyJwtAlsoExists(): void
    {
        $_COOKIE['jwt'] = 'legacy-token';
        $_COOKIE['oms-token-key'] = 'canonical-token';
        $_SERVER['HTTP_X_AUTH_TOKEN'] = 'header-token';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer auth-header-token';

        $this->assertSame(
            'canonical-token',
            desktop_auth_jwt::extractBrowserAccessToken()
        );
    }

    public function testBrowserAccessDoesNotUseLegacyJwtCookie(): void
    {
        $_COOKIE['jwt'] = 'legacy-token';
        unset($_COOKIE['oms-token-key']);
        $_SERVER = [];

        $this->assertNull(desktop_auth_jwt::extractBrowserAccessToken());
    }

    public function testBrowserAccessDoesNotUseHeaderTokens(): void
    {
        $_COOKIE['oms-token-key'] = 'canonical-token';
        $_SERVER['HTTP_X_AUTH_TOKEN'] = 'header-token';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer auth-header-token';

        $token = desktop_auth_jwt::extractBrowserAccessToken();
        $this->assertNotSame('header-token', $token);
        $this->assertNotSame('auth-header-token', $token);
        $this->assertSame('canonical-token', $token);
    }

    public function testHeaderAccessReadsHeaderTokensOnly(): void
    {
        $_COOKIE['jwt'] = 'legacy-token';
        $_COOKIE['oms-token-key'] = 'canonical-token';
        $_SERVER['HTTP_X_AUTH_TOKEN'] = 'header-token';

        $this->assertSame('header-token', desktop_auth_jwt::extractHeaderAccessToken());
    }

    public function testWapAccessReadsOnlyWapCookie(): void
    {
        $_COOKIE['wap-token-key'] = 'wap-token';
        $_COOKIE['oms-token-key'] = 'canonical-token';
        $_SERVER = [];

        $this->assertSame('wap-token', desktop_auth_jwt::extractWapAccessToken());
    }

    public function testWapRefreshReadsOnlyWapRefreshCookie(): void
    {
        $_COOKIE['wap-refresh-token-key'] = 'wap-refresh';
        $_COOKIE['oms-refresh-token-key'] = 'desktop-refresh';
        $_SERVER = [];

        $this->assertSame('wap-refresh', desktop_auth_jwt::extractWapRefreshToken());
    }

    public function testApiGuardRejectsBrowserCookies(): void
    {
        $_COOKIE['oms-token-key'] = 'canonical-token';
        $_COOKIE['oms-refresh-token-key'] = 'refresh-token';

        $client = $this->buildApiClient();
        $mode = $client->tokenSourceMode();

        $this->assertSame(desktop_auth_jwt_client::MODE_HEADER_BODY, $mode);
        $this->assertNull(desktop_auth_jwt::extractHeaderAccessToken());
    }

    public function testLegacyDesktopCookieGroupIncludesOnlyJwt(): void
    {
        $this->assertSame(['jwt'], desktop_auth_jwt_transport::LEGACY_DESKTOP_AUTH_COOKIES);
    }

    public function testDesktopCookieGroupIncludesCanonicalAndLegacy(): void
    {
        $names = desktop_auth_jwt_transport::DESKTOP_AUTH_COOKIES;

        $this->assertContains('jwt', $names);
        $this->assertContains('oms-token-key', $names);
        $this->assertContains('oms-refresh-token-key', $names);
        $this->assertContains('oms-csrf-token', $names);
        $this->assertNotContains('wap-token-key', $names);
        $this->assertNotContains('wap-refresh-token-key', $names);
    }

    public function testWapCookieGroupIsSeparateFromDesktop(): void
    {
        $this->assertSame('wap-token-key', desktop_auth_jwt_transport::WAP_ACCESS_COOKIE);
        $this->assertSame('wap-refresh-token-key', desktop_auth_jwt_transport::WAP_REFRESH_COOKIE);
        $this->assertNotSame(
            desktop_auth_jwt_transport::WAP_ACCESS_COOKIE,
            desktop_auth_jwt_transport::DESKTOP_ACCESS_COOKIE
        );
    }

    private function buildApiClient(): desktop_auth_jwt_client
    {
        $_SERVER['HTTP_X_OMS_CLIENT_TYPE'] = 'api';

        return desktop_auth_jwt_client::fromRequest();
    }
}
