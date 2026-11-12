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

final class AuthObservabilityRedactionTest extends TestCase
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
        require_once ROOT_DIR . '/app/desktop/lib/auth/observability.php';
    }

    public function testContextKeepsAllowlistKeys(): void
    {
        $context = desktop_auth_observability::context([
            'event' => 'refresh_success',
            'client_mode' => 'browser_cookie',
            'endpoint' => '/index.php?app=desktop&ctl=api_jwt&act=refresh',
            'user_id' => 42,
            'reason' => 'scheduled',
            'token_type' => 'access',
            'token_version' => 12,
            'db_token_version' => 12,
            'cookie_names_present' => [
                'oms-token-key' => true,
                'oms-refresh-token-key' => true,
            ],
        ]);

        $this->assertSame('refresh_success', $context['event']);
        $this->assertSame('browser_cookie', $context['client_mode']);
        $this->assertSame(42, $context['user_id']);
        $this->assertSame('scheduled', $context['reason']);
        $this->assertTrue($context['cookie_names_present']['oms-token-key']);
    }

    public function testContextNeverContainsCredentialValues(): void
    {
        $access = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.signature';
        $refresh = 'eyJhbGciOiJIUzI1NiJ9.eyJ0eXBlIjoicmVmcmVzaCJ9.refreshsig';

        $context = desktop_auth_observability::context([
            'event' => 'refresh_failed',
            'cookie_names_present' => [
                'oms-token-key' => true,
                'oms-refresh-token-key' => true,
            ],
            'access_token' => $access,
            'refresh_token' => $refresh,
            'cookie_header' => 'oms-token-key=' . $access . '; oms-refresh-token-key=' . $refresh,
            'authorization_header' => 'Bearer ' . $access,
            'password' => 'plain-password',
        ]);

        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($encoded);

        $this->assertStringNotContainsString($access, $encoded);
        $this->assertStringNotContainsString($refresh, $encoded);
        $this->assertStringNotContainsString('access_token', $encoded);
        $this->assertStringNotContainsString('refresh_token', $encoded);
        $this->assertStringNotContainsString('cookie_header', $encoded);
        $this->assertStringNotContainsString('authorization_header', $encoded);
        $this->assertStringNotContainsString('password', $encoded);
        $this->assertArrayNotHasKey('access_token', $context);
        $this->assertArrayNotHasKey('refresh_token', $context);
        $this->assertSame(true, $context['cookie_names_present']['oms-token-key']);
    }

    public function testNestedArraysAreScrubbed(): void
    {
        $context = desktop_auth_observability::context([
            'event' => 'auth_revoked',
            'reason' => [
                'cause' => 'password_changed',
                'access_token' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.sig',
                'cookie_header' => 'oms-token-key=eyJ...',
            ],
        ]);

        $this->assertSame('auth_revoked', $context['event']);
        $this->assertSame('password_changed', $context['reason']['cause']);
        $this->assertArrayNotHasKey('access_token', $context['reason']);
        $this->assertArrayNotHasKey('cookie_header', $context['reason']);
    }

    public function testContextDropsUnknownKeys(): void
    {
        $context = desktop_auth_observability::context([
            'event' => 'auth_success',
            'secret_field' => 'must-not-appear',
            'csrf_token' => 'fake-csrf',
        ]);

        $this->assertSame(['event' => 'auth_success'], $context);
    }

    public function testLongStringsAreTruncated(): void
    {
        $long = str_repeat('a', 1024);
        $context = desktop_auth_observability::context([
            'event' => $long,
        ]);

        $this->assertArrayHasKey('event', $context);
        $this->assertLessThanOrEqual(201, mb_strlen($context['event']));
    }

    public function testRefreshFailureLoggingIsNotPartOfTheAuthFlow(): void
    {
        $flow = file_get_contents(ROOT_DIR . '/app/desktop/lib/auth/jwt/flow.php');

        $this->assertFalse(method_exists(desktop_auth_observability::class, 'refreshFailureContext'));
        $this->assertStringNotContainsString('logRefreshFailure', $flow);
        $this->assertStringNotContainsString('[auth.refresh]', $flow);
    }
}
