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

use PHPUnit\Framework\TestCase;

/**
 * OAuth 一期全链路：动态注册 → authorize（PKCE/同意）→ token（授权码/刷新）→ Bearer 认证。
 */
final class OAuthFlowTest extends TestCase
{
    private string $cwdBeforeTest;

    /** @var array<string, array> client_id => row */
    private array $clients = [];
    /** @var array<string, array> code_hash => row */
    private array $codes = [];
    /** @var array<string, array> user|client => row */
    private array $consents = [];
    /** @var array<string, array> token_hash => row */
    private array $refreshTokens = [];
    /** @var array<string, array> kid => row */
    private array $asKeys = [];
    private int $now = 1800000000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBeforeTest = getcwd();
        chdir(__DIR__ . '/../..');
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', getcwd());
        }
        if (!defined('APP_DIR')) {
            define('APP_DIR', ROOT_DIR . '/app');
        }
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
        kernel::register_autoload();
    }

    protected function tearDown(): void
    {
        desktop_auth_context::clear();
        chdir($this->cwdBeforeTest);
        parent::tearDown();
    }

    public function testDynamicRegistrationRfc7591(): void
    {
        $controller = new mcpapi_ctl_oauth_register($this->registrationService());

        $response = $controller->create([
            'client_name' => 'WorkBuddy 连接器',
            'redirect_uris' => ['workbuddy://oauth/callback', 'https://client.example/cb'],
            'scope' => 'oms:read oms:statistics unknown:scope',
        ]);

        $this->assertSame(201, $response->status());
        $body = $response->body();
        $this->assertSame('none', $body['token_endpoint_auth_method']);
        $this->assertSame(['workbuddy://oauth/callback', 'https://client.example/cb'], $body['redirect_uris']);
        // 未知 scope 被过滤
        $this->assertSame('oms:read oms:statistics', $body['scope']);
        $this->assertSame('WorkBuddy 连接器', $body['client_name']);
        $this->assertStringStartsWith('mcp_', $body['client_id']);

        // 库中行：公共客户端、动态注册、30 天 TTL（活跃授权续期）
        $row = $this->clients[$body['client_id']];
        $this->assertSame('public', $row['client_type']);
        $this->assertSame('dynamic', $row['registration_origin']);
        $this->assertSame('none', $row['token_endpoint_auth']);
        $this->assertSame($this->now + 2592000, $row['expires_at']);
    }

    public function testRegistrationRateLimitedPerIp(): void
    {
        $registration = new mcpapi_auth_oauth_registration(
            function (array $row): void {
                $this->clients[$row['client_id']] = $row;
            },
            fn(string $tenantId): int => 0,
            $this->scopeService(),
            'default',
            'https://oms.example',
            $this->now,
            static fn(string $ip): bool => false
        );
        $controller = new mcpapi_ctl_oauth_register($registration);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $response = $controller->create(['redirect_uris' => ['workbuddy://oauth/callback']]);
        unset($_SERVER['REMOTE_ADDR']);
        $this->assertSame(429, $response->status());
        $this->assertSame('invalid_client_metadata', $response->body()['error']);
    }

    public function testRegistrationRejectsInvalidRedirectUris(): void
    {
        $controller = new mcpapi_ctl_oauth_register($this->registrationService());

        // 全部非法（http 非回环）
        $response = $controller->create(['redirect_uris' => ['http://evil.example/cb']]);
        $this->assertSame(400, $response->status());
        $this->assertSame('invalid_redirect_uri', $response->body()['error']);
    }

    public function testRegistrationCapacityLimit(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->clients['mcp_seed_' . $i] = $this->publicClientRow('mcp_seed_' . $i);
        }
        $controller = new mcpapi_ctl_oauth_register($this->registrationService());

        $response = $controller->create(['redirect_uris' => ['workbuddy://oauth/callback']]);
        $this->assertSame(403, $response->status());
        $this->assertSame('invalid_client_metadata', $response->body()['error']);
    }

    public function testAuthorizeRejectsScopeBeyondClientCapability(): void
    {
        // 换票关：scope 超出 client 能力上限 → invalid_scope（不允许请求即所得）
        $this->clients['mcp_cap_client'] = $this->publicClientRow('mcp_cap_client');

        desktop_auth_context::setUserId(7);
        $authorize = $this->authorizeController();
        $response = $authorize->index([
            'response_type' => 'code',
            'client_id' => 'mcp_cap_client',
            'redirect_uri' => 'workbuddy://oauth/cb',
            'scope' => 'oms:read oms:admin',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]);
        $this->assertSame(400, $response->status());
        $this->assertSame('invalid_scope', $response->body()['error']);
    }

    public function testAuthorizeRejectsDisabledUser(): void
    {
        // EARS：用户禁用或 mcp_enabled 关闭 → 拒绝签发新 token
        $this->clients['mcp_disabled_user_client'] = $this->publicClientRow('mcp_disabled_user_client');

        desktop_auth_context::setUserId(7);
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService(static fn(): array => throw new mcpapi_auth_exception('Forbidden', 403))
        );
        $response = $authorize->index([
            'response_type' => 'code',
            'client_id' => 'mcp_disabled_user_client',
            'redirect_uri' => 'workbuddy://oauth/cb',
            'scope' => 'oms:read',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]);
        $this->assertSame(403, $response->status());
        $this->assertSame('access_denied', $response->body()['error']);
    }

    public function testAuthorizeGrantsFullScopesForSuperUser(): void
    {
        // 超管授权：scope 不按 client 能力上限收敛 → 授予全部 SUPPORTED scope
        $this->clients['mcp_super_client'] = $this->publicClientRow('mcp_super_client');

        desktop_auth_context::setUserId(7);
        $verifier = 'super-verifier-string-with-enough-length-43-chars-minimum';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService(static fn(int $userId): array => [
                'user_id' => $userId, 'status' => '1', 'super' => '1', 'mcp_enabled' => '1', 'token_version' => 1,
            ])
        );
        $page = $authorize->index([
            'response_type' => 'code',
            'client_id' => 'mcp_super_client',
            'redirect_uri' => 'workbuddy://oauth/cb',
            'scope' => 'oms:read',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        // 同意页展示全部 scope
        foreach (['oms:read', 'oms:statistics', 'oms:fulfillment', 'oms:rules', 'oms:supply', 'oms:admin'] as $scope) {
            $this->assertStringContainsString($scope, $page->html());
        }

        $approved = $authorize->create([
            'action' => 'approve',
            'response_type' => 'code',
            'client_id' => 'mcp_super_client',
            'redirect_uri' => 'workbuddy://oauth/cb',
            'scope' => 'oms:read',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        $code = $this->extractCode($approved);
        $this->assertNotSame('', $code);

        $token = new mcpapi_ctl_oauth_token(
            $this->clientFinder(),
            $this->codeService(),
            $this->refreshService(),
            $this->tokenService(),
            $this->consentService()
        );
        $issued = $token->create([
            'grant_type' => 'authorization_code',
            'client_id' => 'mcp_super_client',
            'code' => $code,
            'redirect_uri' => 'workbuddy://oauth/cb',
            'code_verifier' => $verifier,
        ]);
        $this->assertSame(200, $issued->status(), (string) json_encode($issued->body()));
        $this->assertSame(
            'oms:read oms:statistics oms:fulfillment oms:rules oms:supply oms:admin',
            $issued->body()['scope']
        );
    }

    public function testConsentPageEmbedsCsrfFormField(): void
    {
        $this->clients['mcp_csrf_client'] = $this->publicClientRow('mcp_csrf_client');
        $_COOKIE['oms-csrf-token'] = 'csrf-cookie-value';

        desktop_auth_context::setUserId(7);
        $authorize = $this->authorizeController();
        $page = $authorize->index([
            'response_type' => 'code',
            'client_id' => 'mcp_csrf_client',
            'redirect_uri' => 'workbuddy://oauth/cb',
            'scope' => 'oms:read',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]);
        unset($_COOKIE['oms-csrf-token']);

        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('name="_csrf"', $page->html());
        $this->assertStringContainsString('value="csrf-cookie-value"', $page->html());
    }

    public function testAuthorizeFullFlowWithPkce(): void
    {
        // 1. 动态注册
        $register = new mcpapi_ctl_oauth_register($this->registrationService());
        $registered = $register->create([
            'client_name' => 'Flow client',
            'redirect_uris' => ['workbuddy://oauth/callback'],
            'scope' => 'oms:read oms:statistics',
        ])->body();
        $clientId = $registered['client_id'];

        // 2. PKCE 材料
        $verifier = 'test-verifier-string-with-enough-length-43-chars-minimum-ok';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        // 3. 未登录 → 302 登录页
        desktop_auth_context::clear();
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService()
        );
        $loginRedirect = $authorize->index([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'workbuddy://oauth/callback',
            'scope' => 'oms:read oms:statistics',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        $this->assertSame(302, $loginRedirect->status());
        $this->assertStringContainsString('/oauth-login', (string) $loginRedirect->headers()['Location']);

        // 4. 已登录未同意 → 同意页 HTML
        desktop_auth_context::setUserId(7);
        $consentPage = $authorize->index([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'workbuddy://oauth/callback',
            'scope' => 'oms:read oms:statistics',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        $this->assertSame(200, $consentPage->status());
        $this->assertTrue($consentPage->isHtml());
        $this->assertStringContainsString('oms:read', $consentPage->html());

        // 5. 同意 → 302 + code + state
        $approved = $authorize->create([
            'action' => 'approve',
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'workbuddy://oauth/callback',
            'scope' => 'oms:read oms:statistics',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        $this->assertSame(200, $approved->status());
        $this->assertTrue($approved->isHtml());
        $location = (string) ($approved->headers()['Location'] ?? '');
        if ($location === '') {
            preg_match('/location\\.replace\\("([^"]*)"/', $approved->html(), $lm);
            $location = $lm[1] ?? '';
        }
        $this->assertStringStartsWith('workbuddy://oauth/callback?code=', $location);
        $this->assertStringContainsString('state=xyz', $location);
        preg_match('/code=([^&]+)/', $location, $m);
        $code = rawurldecode($m[1]);

        // 同意记录已建立 → 再次 GET 直接签发
        $second = $authorize->index([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'workbuddy://oauth/callback',
            'scope' => 'oms:read',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        $this->assertTrue(in_array($second->status(), [200, 302], true));
        $this->assertNotSame('', $this->extractCode($second));

        // 6. 拒绝 → access_denied
        $denied = $authorize->create([
            'action' => 'deny',
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'workbuddy://oauth/callback',
            'scope' => 'oms:read',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        $deniedLocation = (string) ($denied->headers()['Location'] ?? '');
        if ($deniedLocation === '' && $denied->isHtml()) {
            preg_match('/location\.replace\("([^"]*)"/', $denied->html(), $dm);
            $deniedLocation = $dm[1] ?? '';
        }
        $this->assertNotSame('', $deniedLocation);
        $this->assertStringContainsString('error=access_denied', $deniedLocation);

        // 7. 授权码换 token（正确 PKCE verifier）
        $token = new mcpapi_ctl_oauth_token(
            $this->clientFinder(),
            $this->codeService(),
            $this->refreshService(),
            $this->tokenService(),
            $this->consentService()
        );
        $tokenResponse = $token->create([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => 'workbuddy://oauth/callback',
            'code_verifier' => $verifier,
        ]);
        $this->assertSame(200, $tokenResponse->status());
        $body = $tokenResponse->body();
        $this->assertSame('Bearer', $body['token_type']);
        $this->assertSame('oms:read oms:statistics', $body['scope']);
        $this->assertNotSame('', $body['access_token']);
        $this->assertNotSame('', $body['refresh_token']);
        $this->assertSame(1800, $body['expires_in']);

        // 8. 授权码不可重放
        $replay = $token->create([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => 'workbuddy://oauth/callback',
            'code_verifier' => $verifier,
        ]);
        $this->assertSame(400, $replay->status());
        $this->assertSame('invalid_grant', $replay->body()['error']);

        // 9. access token 可验签，claims 正确
        $claims = $this->asKeyService()->verify($body['access_token']);
        $this->assertSame('mcp-api', $claims['aud']);
        $this->assertSame('7', $claims['sub']);
        $this->assertSame($clientId, $claims['client_id']);
        $this->assertSame('access', $claims['type']);
        $this->assertSame(1, $claims['ver']);

        // 10. refresh 轮换
        $refreshed = $token->create([
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $body['refresh_token'],
        ]);
        $this->assertSame(200, $refreshed->status());
        $this->assertNotSame($body['refresh_token'], $refreshed->body()['refresh_token']);

        // 11. 旧 refresh 重放 → 链吊销
        $replayRefresh = $token->create([
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $body['refresh_token'],
        ]);
        $this->assertSame(400, $replayRefresh->status());

        // 新 token 也被链式吊销
        $deadRefresh = $token->create([
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $refreshed->body()['refresh_token'],
        ]);
        $this->assertSame(400, $deadRefresh->status());
    }

    public function testPkceVerifierMismatchRejected(): void
    {
        $register = new mcpapi_ctl_oauth_register($this->registrationService());
        $clientId = $register->create(['redirect_uris' => ['workbuddy://oauth/cb']])->body()['client_id'];

        desktop_auth_context::setUserId(7);
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService()
        );
        $approved = $authorize->create([
            'action' => 'approve',
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'workbuddy://oauth/cb',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'correct-verifier', true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
        $code = $this->extractCode($approved);

        $token = new mcpapi_ctl_oauth_token(
            $this->clientFinder(),
            $this->codeService(),
            $this->refreshService(),
            $this->tokenService(),
            $this->consentService()
        );
        $wrong = $token->create([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => 'workbuddy://oauth/cb',
            'code_verifier' => 'wrong-verifier-value-1234567890123456789012345678',
        ]);
        $this->assertSame(400, $wrong->status());
        $this->assertSame('invalid_grant', $wrong->body()['error']);
    }

    public function testAuthorizeRejectsUnregisteredRedirectUri(): void
    {
        $register = new mcpapi_ctl_oauth_register($this->registrationService());
        $clientId = $register->create(['redirect_uris' => ['workbuddy://oauth/cb']])->body()['client_id'];

        desktop_auth_context::setUserId(7);
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService()
        );
        $response = $authorize->index([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'https://evil.example/cb',
        ]);
        $this->assertSame(400, $response->status());
        $this->assertSame('invalid_request', $response->body()['error']);
    }

    public function testAuthorizeRequiresPkceForPublicClients(): void
    {
        $register = new mcpapi_ctl_oauth_register($this->registrationService());
        $clientId = $register->create(['redirect_uris' => ['workbuddy://oauth/cb']])->body()['client_id'];

        desktop_auth_context::setUserId(7);
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService()
        );
        $response = $authorize->index([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'workbuddy://oauth/cb',
        ]);
        $this->assertSame(400, $response->status());
        $this->assertSame('invalid_request', $response->body()['error']);
    }

    public function testBearerAuthenticationBuildsContext(): void
    {
        $this->clients['mcp_bearer_client'] = array_merge(
            $this->publicClientRow('mcp_bearer_client'),
            ['scopes' => ['oms:read', 'oms:admin']]
        );

        // 签发一个 access token，然后走 guard authenticateBearer
        $tokenService = $this->tokenService();
        $issued = $tokenService->issueAccessToken('mcp_bearer_client', 7, ['oms:read', 'oms:admin']);

        $guard = new mcpapi_auth_guard(
            static fn(): array => throw new mcpapi_auth_exception(),
            null,
            null,
            null,
            new mcpapi_auth_owner(
                static fn(int $userId): array => ['user_id' => $userId, 'status' => '1', 'super' => '1', 'mcp_enabled' => '1', 'token_version' => 1],
                static fn(): array => []
            ),
            null,
            fn(string $clientId): ?array => $this->clients[$clientId] ?? null,
            fn(string $token): array => $this->asKeyService()->verify($token),
            'https://oms.example/index.php/mcp-api/oauth'
        );

        $context = $guard->authenticateBearer('Bearer ' . $issued['access_token'], 'req-1');
        $this->assertSame('mcp_bearer_client', $context->clientId());
        $this->assertTrue($context->isSuper());
        // super 用户 scope 不收敛
        $this->assertContains('oms:admin', $context->scopes());
    }

    public function testBearerConvergesPermissionsByScopeForNonSuperUser(): void
    {
        // 非 super：实时角色权限 ∩ 已授权 scope 展开的权限点
        $this->clients['mcp_scope_client'] = $this->publicClientRow('mcp_scope_client');
        $claims = [
            'iss' => 'https://oms.example/index.php/mcp-api/oauth',
            'aud' => 'mcp-api',
            'type' => 'access',
            'exp' => time() + 300,
            'ver' => 1,
            'client_id' => 'mcp_scope_client',
            'sub' => '7',
            'scope' => 'oms:read',
        ];

        $guard = new mcpapi_auth_guard(
            static fn(): array => throw new mcpapi_auth_exception(),
            null,
            null,
            null,
            $this->ownerService(), // 权限点 order_view + users；scope 只有 oms:read
            null,
            fn(string $clientId): ?array => $this->clients[$clientId] ?? null,
            static fn(string $token): array => $claims,
            'https://oms.example/index.php/mcp-api/oauth'
        );

        $context = $guard->authenticateBearer('Bearer fake.token.value', 'req-1');
        // users 权限点属于 oms:admin，未授权该 scope → 被收敛掉
        $this->assertSame(['order_view'], $context->omsPermissions());
        $this->assertSame(['oms:read'], $context->scopes());
    }

    public function testBearerRejectsStaleTokenVersion(): void
    {
        // 改密/吊销会话提升 token_version 后，存量 access 立即失效
        $this->clients['mcp_stale_client'] = $this->publicClientRow('mcp_stale_client');
        $claims = [
            'iss' => 'https://oms.example/index.php/mcp-api/oauth',
            'aud' => 'mcp-api',
            'type' => 'access',
            'exp' => time() + 300,
            'ver' => 1,
            'client_id' => 'mcp_stale_client',
            'sub' => '7',
            'scope' => 'oms:read',
        ];

        $guard = new mcpapi_auth_guard(
            static fn(): array => throw new mcpapi_auth_exception(),
            null,
            null,
            null,
            $this->ownerService(static fn(int $userId): array => [
                'user_id' => $userId, 'status' => '1', 'super' => '0', 'mcp_enabled' => '1', 'token_version' => 5,
            ]),
            null,
            fn(string $clientId): ?array => $this->clients[$clientId] ?? null,
            static fn(string $token): array => $claims,
            'https://oms.example/index.php/mcp-api/oauth'
        );

        $this->expectException(mcpapi_auth_exception::class);
        $guard->authenticateBearer('Bearer fake.token.value', 'req-1');
    }

    public function testBearerRejectsForeignIssuer(): void
    {
        $this->clients['mcp_iss_client'] = $this->publicClientRow('mcp_iss_client');
        $claims = [
            'iss' => 'https://evil.example/mcp-api/oauth',
            'aud' => 'mcp-api',
            'type' => 'access',
            'exp' => time() + 300,
            'ver' => 1,
            'client_id' => 'mcp_iss_client',
            'sub' => '7',
            'scope' => 'oms:read',
        ];

        $guard = new mcpapi_auth_guard(
            static fn(): array => throw new mcpapi_auth_exception(),
            null,
            null,
            null,
            $this->ownerService(),
            null,
            fn(string $clientId): ?array => $this->clients[$clientId] ?? null,
            static fn(string $token): array => $claims,
            'https://oms.example/index.php/mcp-api/oauth'
        );

        $this->expectException(mcpapi_auth_exception::class);
        $guard->authenticateBearer('Bearer fake.token.value', 'req-1');
    }

    public function testRefreshReplayRevokesAllUserClientTokens(): void
    {
        // 重放检测：rotated refresh 再用 → 吊销 (user_id, client_id) 全部 refresh + 安全事件钩子
        $this->clients['mcp_replay_client'] = $this->publicClientRow('mcp_replay_client');
        $replayHookCalls = [];
        $refresh = new mcpapi_auth_oauth_refreshtokens(
            fn(array $filter): array => isset($this->refreshTokens[$filter['token_hash'] ?? ''])
                ? [$this->refreshTokens[$filter['token_hash']]] : [],
            function (array $row): void {
                $this->refreshTokens[$row['token_hash']] = $row;
            },
            function (array $data, array $filter): void {
                if (isset($this->refreshTokens[$filter['token_hash'] ?? ''])) {
                    $hash = $filter['token_hash'];
                    $this->refreshTokens[$hash] = array_merge($this->refreshTokens[$hash], $data);
                    return;
                }
                foreach ($this->refreshTokens as $hash => $row) {
                    if (isset($filter['user_id']) && (int) $row['user_id'] !== (int) $filter['user_id']) continue;
                    if (isset($filter['client_id']) && (string) $row['client_id'] !== (string) $filter['client_id']) continue;
                    if (isset($filter['status']) && (string) $row['status'] !== (string) $filter['status']) continue;
                    $this->refreshTokens[$hash] = array_merge($row, $data);
                }
            },
            $this->now,
            static fn(int $userId): int => 1,
            function (int $userId, string $clientId) use (&$replayHookCalls): void {
                $replayHookCalls[] = [$userId, $clientId];
            }
        );

        // 同一 user+client 两条独立链
        $first = $refresh->issue(7, 'mcp_replay_client', ['oms:read']);
        $second = $refresh->issue(7, 'mcp_replay_client', ['oms:read']);

        // 链 1 正常轮换 → rotated
        $refresh->rotate($first['refresh_token'], 'mcp_replay_client');

        // 重放链 1 旧 token → 链 2 也被吊销
        try {
            $refresh->rotate($first['refresh_token'], 'mcp_replay_client');
            $this->fail('Expected replay to be rejected');
        } catch (mcpapi_auth_oauth_error $e) {
            $this->assertSame('invalid_grant', $e->errorCode());
        }

        $this->assertSame([[7, 'mcp_replay_client']], $replayHookCalls);
        try {
            $refresh->rotate($second['refresh_token'], 'mcp_replay_client');
            $this->fail('Expected sibling chain to be revoked');
        } catch (mcpapi_auth_oauth_error $e) {
            $this->assertSame('invalid_grant', $e->errorCode());
            $this->assertStringContainsString('revoked', $e->getMessage());
        }
    }

    public function testRefreshRejectsStaleTokenVersion(): void
    {
        // 签发后用户改密（token_version 提升）→ refresh 换票被拒且全链吊销
        $version = 1;
        $refresh = new mcpapi_auth_oauth_refreshtokens(
            fn(array $filter): array => isset($this->refreshTokens[$filter['token_hash'] ?? ''])
                ? [$this->refreshTokens[$filter['token_hash']]] : [],
            function (array $row): void {
                $this->refreshTokens[$row['token_hash']] = $row;
            },
            function (array $data, array $filter): void {
                if (isset($this->refreshTokens[$filter['token_hash'] ?? ''])) {
                    $hash = $filter['token_hash'];
                    $this->refreshTokens[$hash] = array_merge($this->refreshTokens[$hash], $data);
                    return;
                }
                foreach ($this->refreshTokens as $hash => $row) {
                    if (isset($filter['user_id']) && (int) $row['user_id'] !== (int) $filter['user_id']) continue;
                    if (isset($filter['client_id']) && (string) $row['client_id'] !== (string) $filter['client_id']) continue;
                    if (isset($filter['status']) && (string) $row['status'] !== (string) $filter['status']) continue;
                    $this->refreshTokens[$hash] = array_merge($row, $data);
                }
            },
            $this->now,
            static function (int $userId) use (&$version): int {
                return $version;
            }
        );

        $issued = $refresh->issue(7, 'mcp_ver_client', ['oms:read']);
        $version = 2; // 用户改密 → 版本提升

        try {
            $refresh->rotate($issued['refresh_token'], 'mcp_ver_client');
            $this->fail('Expected stale token_version refresh to be rejected');
        } catch (mcpapi_auth_oauth_error $e) {
            $this->assertSame('invalid_grant', $e->errorCode());
            $this->assertStringContainsString('session has been revoked', $e->getMessage());
        }
    }

    public function testConsentRevokeEndpointRevokesConsentAndRefreshTokens(): void
    {
        $register = new mcpapi_ctl_oauth_register($this->registrationService());
        $clientId = $register->create(['redirect_uris' => ['workbuddy://oauth/cb']])->body()['client_id'];

        desktop_auth_context::setUserId(7);
        $this->consentService()->grant(7, $clientId, ['oms:read']);

        $controller = new mcpapi_ctl_oauth_consents_revoke($this->consentService(), $this->refreshService());
        $response = $controller->create(['client_id' => $clientId]);

        $this->assertSame(200, $response->status());
        $this->assertTrue($response->body()['ok']);
        // 同意已撤销 → 静默授权失效
        $this->assertFalse($this->consentService()->hasValidConsent(7, $clientId, ['oms:read']));
        // 用户端点：缺少 client_id → 400
        $response = $controller->create([]);
        $this->assertSame(400, $response->status());
    }

    public function testBearerRejectsTamperedToken(): void
    {
        $tokenService = $this->tokenService();
        $issued = $tokenService->issueAccessToken('mcp_bearer_client', 7, ['oms:read']);
        $parts = explode('.', $issued['access_token']);
        $tampered = $parts[0] . '.' . $this->b64(json_encode(array_merge(
            json_decode($this->unb64($parts[1]), true),
            ['sub' => '99']
        ))) . '.' . $parts[2];

        $guard = new mcpapi_auth_guard(
            static fn(): array => throw new mcpapi_auth_exception(),
            null,
            null,
            null,
            null,
            null,
            null,
            fn(string $token): array => $this->asKeyService()->verify($token)
        );

        $this->expectException(mcpapi_auth_exception::class);
        $guard->authenticateBearer('Bearer ' . $tampered, 'req-1');
    }

    public function testAsKeyServiceGeneratesAndPublishesJwks(): void
    {
        $service = $this->asKeyService();
        $key = $service->ensureActiveKey();
        $this->assertStringStartsWith('as_', $key['kid']);
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $key['private_key']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $key['public_key']);

        $jwks = $service->jwks();
        $this->assertCount(1, $jwks['keys']);
        $this->assertSame('RSA', $jwks['keys'][0]['kty']);
        $this->assertSame($key['kid'], $jwks['keys'][0]['kid']);
        $this->assertSame('RS256', $jwks['keys'][0]['alg']);

        // 幂等：不重复生成
        $this->assertCount(1, $service->activeKids());
    }

    public function testWellKnownMetadata(): void
    {
        $controller = new mcpapi_ctl_oauth_authorization_server($this->tokenService());
        $response = $controller->index();
        $this->assertSame(200, $response->status());
        $body = $response->body();
        $this->assertSame(['code'], $body['response_types_supported']);
        $this->assertContains('authorization_code', $body['grant_types_supported']);
        $this->assertContains('refresh_token', $body['grant_types_supported']);
        $this->assertSame(['S256'], $body['code_challenge_methods_supported']);
        $this->assertContains('none', $body['token_endpoint_auth_methods_supported']);
        $this->assertStringContainsString('/mcp-api/oauth/authorize', $body['authorization_endpoint']);
        $this->assertStringContainsString('/mcp-api/oauth/token', $body['token_endpoint']);
        $this->assertStringContainsString('/mcp-api/oauth/register', $body['registration_endpoint']);
        $this->assertStringContainsString('/mcp-api/oauth/jwks', $body['jwks_uri']);
        $this->assertContains('oms:read', $body['scopes_supported']);
    }

    public function testConfidentialClientTokenWithSecret(): void
    {
        // 机密客户端：admin 静态登记形态
        $secret = 'confidential-secret-value';
        $this->clients['mcp_conf_client'] = array_merge($this->publicClientRow('mcp_conf_client'), [
            'client_type' => 'confidential',
            'token_endpoint_auth' => 'client_secret_basic',
            'client_secret_hash' => password_hash($secret, PASSWORD_DEFAULT),
            'redirect_uris' => ['https://conf.example/cb'],
        ]);
        $clientId = 'mcp_conf_client';

        desktop_auth_context::setUserId(9);
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService()
        );
        $approved = $authorize->create([
            'action' => 'approve',
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'https://conf.example/cb',
            'scope' => 'oms:read',
        ]);
        $this->assertSame(302, $approved->status());
        $code = $this->extractCode($approved);

        $token = new mcpapi_ctl_oauth_token(
            $this->clientFinder(),
            $this->codeService(),
            $this->refreshService(),
            $this->tokenService(),
            $this->consentService()
        );

        // 正确 Basic 认证
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($clientId . ':' . $secret);
        $ok = $token->create([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => 'https://conf.example/cb',
        ]);
        $this->assertSame(200, $ok->status());
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testPublicClientRejectsSecret(): void
    {
        $register = new mcpapi_ctl_oauth_register($this->registrationService());
        $clientId = $register->create(['redirect_uris' => ['workbuddy://oauth/cb']])->body()['client_id'];

        $token = new mcpapi_ctl_oauth_token(
            $this->clientFinder(),
            $this->codeService(),
            $this->refreshService(),
            $this->tokenService(),
            $this->consentService()
        );
        $response = $token->create([
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => 'should-not-be-here',
            'refresh_token' => 'nonexistent',
        ]);
        // 公共客户端携带 secret → 直接 401，不进入 grant 处理
        $this->assertSame(401, $response->status());
        $this->assertSame('invalid_client', $response->body()['error']);
    }

    public function testConsentRevocationKillsRefreshChain(): void
    {
        $register = new mcpapi_ctl_oauth_register($this->registrationService());
        $clientId = $register->create(['redirect_uris' => ['workbuddy://oauth/cb']])->body()['client_id'];

        desktop_auth_context::setUserId(7);
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService()
        );
        $approved = $authorize->create([
            'action' => 'approve',
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => 'workbuddy://oauth/cb',
            'scope' => 'oms:read',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'consent-revocation-verifier-43-chars-minimum-xxx', true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
        $code = $this->extractCode($approved);

        $token = new mcpapi_ctl_oauth_token(
            $this->clientFinder(),
            $this->codeService(),
            $this->refreshService(),
            $this->tokenService(),
            $this->consentService()
        );
        $issued = $token->create([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => 'workbuddy://oauth/cb',
            'code_verifier' => 'consent-revocation-verifier-43-chars-minimum-xxx',
        ]);
        $this->assertSame(200, $issued->status());

        // 撤销同意
        $this->consentService()->revoke(7, $clientId);

        // refresh 失败：同意已撤销
        $refresh = $token->create([
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $issued->body()['refresh_token'],
        ]);
        $this->assertSame(400, $refresh->status());
        $this->assertSame('invalid_grant', $refresh->body()['error']);
        $this->assertStringContainsString('Consent has been revoked', $refresh->body()['error_description']);
    }

    // --------------------------------------------------------------- 工厂方法

    public function testDynamicRegistrationClosedModeRejects(): void
    {
        $registration = new mcpapi_auth_oauth_registration(
            function (array $row): void { $this->clients[$row['client_id']] = $row; },
            fn(string $tenantId): int => 0,
            $this->scopeService(), 'default', 'https://oms.example', $this->now, null, 'closed'
        );
        $response = (new mcpapi_ctl_oauth_register($registration))->create(['redirect_uris' => ['workbuddy://cb']]);
        $this->assertSame(403, $response->status());
        $this->assertSame('access_denied', $response->body()['error']);
        $this->assertStringContainsString('static registration', $response->body()['error_description']);
    }

    public function testDynamicRegistrationApprovalModeCreatesPendingClient(): void
    {
        $registration = new mcpapi_auth_oauth_registration(
            function (array $row): void { $this->clients[$row['client_id']] = $row; },
            fn(string $tenantId): int => 0,
            $this->scopeService(), 'default', 'https://oms.example', $this->now, null, 'approval'
        );
        $response = (new mcpapi_ctl_oauth_register($registration))->create(['redirect_uris' => ['workbuddy://cb']]);
        $this->assertSame(201, $response->status());
        $this->assertTrue($response->body()['x_pending_approval']);
        $row = $this->clients[$response->body()['client_id']];
        $this->assertSame('false', $row['enabled']);
    }

    public function testAuthorizeRejectsPendingClientWithApprovalHint(): void
    {
        $row = $this->publicClientRow('mcp_pending_client');
        $row['enabled'] = 'false';
        $this->clients['mcp_pending_client'] = $row;

        desktop_auth_context::setUserId(7);
        $clients = $this->clients;
        $authorize = new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService(),
            static fn(string $clientId): ?array => $clients[$clientId] ?? null
        );
        $response = $authorize->index([
            'response_type' => 'code',
            'client_id' => 'mcp_pending_client',
            'redirect_uri' => 'workbuddy://oauth/cb',
            'scope' => 'oms:read',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]);
        $this->assertSame(403, $response->status());
        $this->assertTrue($response->isHtml());
        $this->assertStringContainsString('应用等待审批', $response->html());
    }

    public function testClientApproveActivatesPendingClient(): void
    {
        $row = $this->publicClientRow('mcp_approve_me');
        $row['enabled'] = 'false';
        $manager = new class extends mcpapi_auth_client_manager {
            public array $store = [];
            public function findClientById(string $clientId): ?array { return $this->store[$clientId] ?? null; }
            public function approvePendingClient(string $clientId, string $operator): array {
                $this->store[$clientId]['enabled'] = 'true';
                $this->store[$clientId]['updated_by'] = 'approve:' . $operator;
                return $this->store[$clientId];
            }
        };
        $manager->store = ['mcp_approve_me' => $row];

        desktop_auth_context::setUserId(7);
        $controller = new mcpapi_ctl_client($manager, static fn(): bool => true, $this->ownerService());
        $response = $controller->approve(['client_id' => 'mcp_approve_me']);
        $this->assertSame(200, $response->status());
        $this->assertSame('true', $response->body()['client']['enabled']);
        $this->assertStringContainsString('approve:', (string) $response->body()['client']['updated_by']);
    }

    public function testClientApproveRequiresPermission(): void
    {
        $manager = new class extends mcpapi_auth_client_manager {
            public array $store = [];
            public function findClientById(string $clientId): ?array { return $this->store[$clientId] ?? null; }
            public function approvePendingClient(string $clientId, string $operator): array {
                $this->store[$clientId]['enabled'] = 'true';
                return $this->store[$clientId];
            }
        };
        $manager->store = ['x' => $this->publicClientRow('x')];
        desktop_auth_context::setUserId(7);

        $denied = new mcpapi_ctl_client($manager, static fn(): bool => false, $this->ownerService());
        $this->assertSame(403, $denied->approve(['client_id' => 'x'])->status());

        $allowed = new mcpapi_ctl_client($manager, static fn(): bool => true, $this->ownerService());
        $this->assertSame(200, $allowed->approve(['client_id' => 'x'])->status());
    }

    public function testPendingListAndRejectFlow(): void
    {
        $row = $this->publicClientRow('mcp_reject_me');
        $row['enabled'] = 'false';
        $manager = new class extends mcpapi_auth_client_manager {
            public array $store = [];
            public function findClientById(string $clientId): ?array { return $this->store[$clientId] ?? null; }
            public function listPendingClients(): array {
                return array_values(array_filter($this->store, static fn(array $r): bool =>
                    $r['enabled'] === 'false' && (int) ($r['revoked_at'] ?? 0) === 0));
            }
            public function rejectPendingClient(string $clientId, string $operator, string $reason = ''): array {
                $r = $this->store[$clientId] ?? null;
                if ($r === null) {
                    throw new mcpapi_auth_exception('Client not found', 404);
                }
                if ($r['enabled'] !== 'false' || (int) ($r['revoked_at'] ?? 0) > 0) {
                    throw new mcpapi_auth_exception('Client is not pending approval', 409);
                }
                unset($this->store[$clientId]); // 拒绝 = 直接作废删除
                return [];
            }
        };
        $manager->store = ['mcp_reject_me' => $row];

        desktop_auth_context::setUserId(7);
        $controller = new mcpapi_ctl_client($manager, static fn(): bool => true, $this->ownerService());

        $list = $controller->pending();
        $this->assertSame(200, $list->status());
        $this->assertCount(1, $list->body()['clients']);

        $rejected = $controller->reject(['client_id' => 'mcp_reject_me', 'reason' => 'test']);
        $this->assertSame(200, $rejected->status());
        $this->assertTrue($rejected->body()['ok']);

        $this->assertCount(0, $controller->pending()->body()['clients']);
        // 拒绝后记录已删除 → 再拒返回 404
        $this->assertSame(404, $controller->reject(['client_id' => 'mcp_reject_me'])->status());
    }

    public function testRegmodeReadAndInvalidModeRejected(): void
    {
        desktop_auth_context::setUserId(7);
        $controller = new mcpapi_ctl_client(null, static fn(): bool => true, $this->ownerService());
        $read = $controller->regmode([]);
        $this->assertSame(200, $read->status());
        $this->assertContains($read->body()['mode'], ['open', 'approval', 'closed']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $bad = $controller->regmode(['mode' => 'yolo']);
        unset($_SERVER['REQUEST_METHOD']);
        $this->assertSame(422, $bad->status());
    }

    /**
     * approve/静默发码响应提取授权码：兼容 302 Location 与 200 结果页（location.replace）两种形态
     */
    private function extractCode(mcpapi_http_response $response): string
    {
        $location = (string) ($response->headers()['Location'] ?? '');
        if ($location !== '' && preg_match('/code=([^&]+)/', $location, $m)) {
            return rawurldecode($m[1]);
        }
        if ($response->isHtml() && preg_match('/location\.replace\("([^"]*)"/', $response->html(), $m)) {
            if (preg_match('/[?&]code=([^&]+)/', $m[1], $cm)) {
                return rawurldecode($cm[1]);
            }
        }
        return '';
    }

    private function asKeyService(): mcpapi_auth_oauth_askeys
    {
        return new mcpapi_auth_oauth_askeys(
            fn(array $filter): array => array_values(array_filter(
                $this->asKeys,
                static fn(array $row): bool => ($filter['status|in'] ?? null) === null
                    || in_array($row['status'], (array) $filter['status|in'], true)
            )),
            function (array $row): void {
                $this->asKeys[$row['kid']] = $row;
            },
            $this->now
        );
    }

    private function scopeService(): mcpapi_auth_oauth_scopes
    {
        return new mcpapi_auth_oauth_scopes();
    }

    /** 默认 owner：用户 7 可用非 super，权限含 order_view/users 两个权限点 */
    private function ownerService(?callable $userLoader = null): mcpapi_auth_owner
    {
        return new mcpapi_auth_owner(
            $userLoader ?? static fn(int $userId): array => [
                'user_id' => $userId,
                'status' => '1',
                'super' => '0',
                'mcp_enabled' => '1',
                'token_version' => 1,
            ],
            static fn(int $userId): array => [
                ['role_id' => 1, 'workground' => serialize(['order_view', 'users']), 'data_authority' => null],
            ]
        );
    }

    /** authorize 控制器（内存服务，含默认可用 owner） */
    private function authorizeController(): mcpapi_ctl_oauth_authorize
    {
        return new mcpapi_ctl_oauth_authorize(
            $this->clientFinder(),
            $this->scopeService(),
            $this->consentService(),
            $this->codeService(),
            $this->ownerService()
        );
    }

    private function codeService(): mcpapi_auth_oauth_codes
    {
        return new mcpapi_auth_oauth_codes(
            fn(array $filter): array => isset($this->codes[$filter['code_hash'] ?? ''])
                ? [$this->codes[$filter['code_hash']]] : [],
            function (array $row): void {
                $this->codes[$row['code_hash']] = $row;
            },
            function (array $data, array $filter): void {
                if (isset($this->codes[$filter['code_hash'] ?? ''])) {
                    $this->codes[$filter['code_hash']] = array_merge($this->codes[$filter['code_hash']], $data);
                }
            },
            $this->now
        );
    }

    private function consentService(): mcpapi_auth_oauth_consents
    {
        return new mcpapi_auth_oauth_consents(
            function (array $filter): array {
                $userId = (int) ($filter['user_id'] ?? 0);
                $clientId = (string) ($filter['client_id'] ?? '');
                $key = $userId . '|' . $clientId;
                return isset($this->consents[$key]) ? [$this->consents[$key]] : [];
            },
            function (array $row): void {
                $this->consents[$row['user_id'] . '|' . $row['client_id']] = $row;
            },
            function (array $data, array $filter): void {
                $key = (int) ($filter['user_id'] ?? 0) . '|' . (string) ($filter['client_id'] ?? '');
                if (isset($this->consents[$key])) {
                    $this->consents[$key] = array_merge($this->consents[$key], $data);
                }
            },
            $this->now
        );
    }

    private function refreshService(): mcpapi_auth_oauth_refreshtokens
    {
        return new mcpapi_auth_oauth_refreshtokens(
            fn(array $filter): array => isset($this->refreshTokens[$filter['token_hash'] ?? ''])
                ? [$this->refreshTokens[$filter['token_hash']]] : [],
            function (array $row): void {
                $this->refreshTokens[$row['token_hash']] = $row;
            },
            function (array $data, array $filter): void {
                if (isset($this->refreshTokens[$filter['token_hash'] ?? ''])) {
                    $hash = $filter['token_hash'];
                    $this->refreshTokens[$hash] = array_merge($this->refreshTokens[$hash], $data);
                    return;
                }
                // revokeAll / 条件更新：按 user_id + client_id + status 过滤
                foreach ($this->refreshTokens as $hash => $row) {
                    if (isset($filter['user_id']) && (int) $row['user_id'] !== (int) $filter['user_id']) {
                        continue;
                    }
                    if (isset($filter['client_id']) && (string) $row['client_id'] !== (string) $filter['client_id']) {
                        continue;
                    }
                    if (isset($filter['status']) && (string) $row['status'] !== (string) $filter['status']) {
                        continue;
                    }
                    $this->refreshTokens[$hash] = array_merge($row, $data);
                }
            },
            $this->now,
            static fn(int $userId): int => 1
        );
    }

    private function tokenService(): mcpapi_auth_oauth_tokens
    {
        return new mcpapi_auth_oauth_tokens(
            $this->asKeyService(),
            null,
            'https://oms.example/index.php/mcp-api/oauth',
            1800,
            static fn(int $userId): int => 1
        );
    }

    private function registrationService(): mcpapi_auth_oauth_registration
    {
        return new mcpapi_auth_oauth_registration(
            function (array $row): void {
                $this->clients[$row['client_id']] = $row;
            },
            fn(string $tenantId): int => count(array_filter(
                $this->clients,
                static fn(array $row): bool => ($row['registration_origin'] ?? '') === 'dynamic'
                    && ($row['tenant_id'] ?? '') === $tenantId
            )),
            $this->scopeService(),
            'default',
            'https://oms.example',
            $this->now
        );
    }

    private function clientFinder(): callable
    {
        return function (string $clientId): ?array {
            $row = $this->clients[$clientId] ?? null;
            if (!is_array($row)) {
                return null;
            }
            $enabled = $row['enabled'] ?? false;
            $revokedAt = $row['revoked_at'] ?? null;
            $expiresAt = $row['expires_at'] ?? null;
            $active = ($enabled === true || $enabled === 1 || $enabled === '1' || $enabled === 'true')
                && ($revokedAt === null || (int) $revokedAt === 0)
                && ($expiresAt === null || (int) $expiresAt > $this->now);
            return $active ? $row : null;
        };
    }

    private function publicClientRow(string $clientId): array
    {
        return [
            'client_id' => $clientId,
            'client_secret_hash' => null,
            'owner_user_id' => 0,
            'name' => 'Public client ' . $clientId,
            'tenant_id' => 'default',
            'oms_base_url' => 'https://oms.example',
            'oms_subject_id' => 0,
            'oms_role_ids' => [],
            'oms_permissions' => [],
            'scopes' => ['oms:read'],
            'client_type' => 'public',
            'redirect_uris' => ['workbuddy://oauth/cb'],
            'registration_origin' => 'dynamic',
            'token_endpoint_auth' => 'none',
            'registered_at' => $this->now,
            'last_seen_at' => $this->now,
            'enabled' => true,
            'revoked_at' => null,
            'expires_at' => $this->now + 2592000,
            'created_at' => $this->now,
            'updated_at' => $this->now,
            'created_by' => 'test',
            'updated_by' => 'test',
            'revoked_reason' => null,
            'last_used_at' => null,
        ];
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function unb64(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
