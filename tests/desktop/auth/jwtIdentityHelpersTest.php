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
final class jwtIdentityHelpersTest extends TestCase
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
        putenv('JWT_SECRET=test-jwt-identity-helpers');
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';

        desktop_auth_context::clear();
        $_COOKIE = [];
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        desktop_auth_context::clear();
        parent::tearDown();
    }

    public function testCurrentUserIdPrefersAuthContextOverMissingCookie(): void
    {
        desktop_auth_context::setUserId(42);
        desktop_auth_context::setLoginName('ctx-user');

        $this->assertSame(42, desktop_auth_jwt::currentUserId());
        $this->assertSame(42, desktop_auth_user::currentUserId());
    }

    public function testClaimsFallBackToContextWhenNoTokenPresent(): void
    {
        desktop_auth_context::setUserId(7);
        desktop_auth_context::setLoginName('fallback');
        desktop_auth_context::setProfile([
            'login_name' => 'fallback',
            'is_super' => true,
            'roles' => ['admin'],
        ]);

        $claims = desktop_auth_jwt::claims(true);
        $this->assertSame(7, (int)$claims['user_id']);
        $this->assertSame('fallback', $claims['login_name']);
        $this->assertTrue((bool)$claims['super']);
        $this->assertSame(['admin'], $claims['roles']);
    }

    public function testExtractWapRefreshTokenReadsOnlyWapCookie(): void
    {
        $_COOKIE['oms-refresh-token-key'] = 'desktop-refresh';
        $_COOKIE['wap-refresh-token-key'] = 'wap-refresh';

        $this->assertSame('wap-refresh', desktop_auth_jwt::extractWapRefreshToken());
    }
}
