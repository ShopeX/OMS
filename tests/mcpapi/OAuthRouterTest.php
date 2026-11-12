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
 * OAuth 路由接入：endpoint 解析、认证分类、bearer 模式分派、HTML/302 响应输出。
 */
final class OAuthRouterTest extends TestCase
{
    private string $cwdBeforeTest;
    /** @var array<string, array> */
    private array $clients = [];
    /** @var array<string, array> */
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

    public function testWellKnownRouteResolvesToPublicController(): void
    {
        $router = $this->router();
        $response = $router->dispatch('GET', '/mcp-api/.well-known/oauth-authorization-server', '', 'req-1', [], 'application/json');
        $this->assertSame(200, $response->status());
        $this->assertArrayHasKey('authorization_endpoint', $response->body());
    }

    public function testJwksRouteIsPublic(): void
    {
        // jwks 控制器默认构造走生产 DB；注入 askeys 服务隔离
        $askeys = new mcpapi_auth_oauth_askeys(
            fn(array $filter): array => array_values($this->asKeys),
            function (array $row): void {
                $this->asKeys[$row['kid']] = $row;
            },
            $this->now
        );
        $router = new mcpapi_router(
            $this->guard(),
            null,
            null,
            null,
            null,
            new mcpapi_ctl_oauth_jwks($askeys)
        );
        $response = $router->dispatch('GET', '/mcp-api/oauth/jwks', '', 'req-2', [], 'application/json');
        $this->assertSame(200, $response->status());
        $this->assertArrayHasKey('keys', $response->body());
        // AS key 已惰性生成并可发布
        $this->assertNotEmpty($response->body()['keys']);
    }

    public function testAuthorizeAnonymousRedirectsToSpaLogin(): void
    {
        // 现行设计（授权码全流程联调验证）：路由层对 oauth_authorize 豁免未登录请求，
        // 放行到控制器；控制器校验参数通过后 302 到 SPA 登录页（next 回跳），而非路由层 401
        desktop_auth_context::clear();
        $this->clients['mcp_anon_client'] = $this->publicClientRow('mcp_anon_client');
        $router = new mcpapi_router($this->guard(), $this->authorizeController());
        $response = $router->dispatch(
            'GET',
            '/mcp-api/oauth/authorize',
            '',
            'req-3',
            [
                'response_type' => 'code',
                'client_id' => 'mcp_anon_client',
                'redirect_uri' => 'workbuddy://cb',
                'code_challenge' => str_repeat('a', 43),
                'code_challenge_method' => 'S256',
            ],
            'application/json'
        );
        $this->assertSame(302, $response->status());
        $location = (string) $response->headers()['Location'];
        $this->assertStringContainsString('/oauth-login', $location);
        // next 参数承载回跳（base64(urlencode(authorize 完整 URL))）
        $this->assertStringContainsString('next=', $location);
    }

    public function testAuthorizeAnonymousUnknownClientRejectedBeforeRedirect(): void
    {
        // RFC 6749 §4.1.2.1：client 未知不得 302 重定向；参数校验先于登录跳转
        desktop_auth_context::clear();
        $router = new mcpapi_router($this->guard(), $this->authorizeClientUnknown());
        $response = $router->dispatch(
            'GET',
            '/mcp-api/oauth/authorize',
            '',
            'req-4',
            ['response_type' => 'code', 'client_id' => 'ghost_client', 'redirect_uri' => 'workbuddy://cb'],
            'application/json'
        );
        $this->assertSame(400, $response->status());
        $this->assertSame('invalid_client', $response->body()['error']);
    }

    public function testTokenRouteIsPublicAndRejectsMalformedGrant(): void
    {
        $router = $this->router();
        $response = $router->dispatch('POST', '/mcp-api/oauth/token', '', 'req-5', ['grant_type' => 'password'], 'application/json');
        $this->assertSame(400, $response->status());
        $this->assertSame('unsupported_grant_type', $response->body()['error']);
    }

    public function testRegisterRouteIsPublic(): void
    {
        $router = $this->router();
        // 无效 redirect_uris → 400（验证路由可达且公开可调，无需触发 DB 写入）
        $response = $router->dispatch('POST', '/mcp-api/oauth/register', '', 'req-6', [
            'redirect_uris' => ['http://not-loopback.example/cb'],
        ], 'application/json');
        $this->assertSame(400, $response->status());
        $this->assertSame('invalid_redirect_uri', $response->body()['error']);
    }

    public function testV1RoutesSupportBearerMode(): void
    {
        // 用真实 AS 密钥签发 token，走 mcp 路由 bearer 分派
        $this->clients['mcp_router_client'] = [
            'client_id' => 'mcp_router_client',
            'owner_user_id' => 0,
            'name' => 'Router client',
            'tenant_id' => 'default',
            'oms_base_url' => 'https://oms.example',
            'oms_subject_id' => 0,
            'oms_role_ids' => [],
            'oms_permissions' => [],
            'scopes' => ['oms:read'],
            'client_type' => 'public',
            'redirect_uris' => ['workbuddy://cb'],
            'registration_origin' => 'dynamic',
            'token_endpoint_auth' => 'none',
            'enabled' => true,
            'revoked_at' => null,
            'expires_at' => $this->now + 86400,
        ];

        $askeys = new mcpapi_auth_oauth_askeys(
            fn(array $filter): array => array_values($this->asKeys),
            function (array $row): void {
                $this->asKeys[$row['kid']] = $row;
            },
            $this->now
        );
        $tokens = new mcpapi_auth_oauth_tokens(
            $askeys,
            null,
            'https://oms.example/mcp-api/oauth',
            3600,
            static fn(int $userId): int => 1
        );
        $issued = $tokens->issueAccessToken('mcp_router_client', 7, ['oms:read'], $this->now);

        $guard = new mcpapi_auth_guard(
            static fn(): array => throw new mcpapi_auth_exception(),
            null,
            null,
            null,
            new mcpapi_auth_owner(
                static fn(int $userId): array => ['user_id' => $userId, 'status' => '1', 'super' => '0', 'mcp_enabled' => '1', 'token_version' => 1],
                static fn(): array => []
            ),
            null,
            fn(string $clientId): ?array => $this->clients[$clientId] ?? null,
            fn(string $token): array => $askeys->verify($token),
            'https://oms.example/mcp-api/oauth'
        );
        $router = new mcpapi_router($guard);

        // health 无 RequirePermission 注解豁免 → 200
        $ok = $router->dispatch(
            'GET',
            '/mcp-api/v1/health',
            'Bearer ' . $issued['access_token'],
            'req-7',
            [],
            'application/json',
            ['X-Shopex-OMS-Client-Mode' => 'bearer']
        );
        $this->assertSame(200, $ok->status());
        $this->assertSame(['status' => 'ok'], $ok->body());

        // 无 bearer 头 → 401
        $noToken = $router->dispatch(
            'GET',
            '/mcp-api/v1/health',
            '',
            'req-8',
            [],
            'application/json',
            ['X-Shopex-OMS-Client-Mode' => 'bearer']
        );
        $this->assertSame(401, $noToken->status());

        // 伪造 token → 401
        $bad = $router->dispatch(
            'GET',
            '/mcp-api/v1/health',
            'Bearer not.a.jwt',
            'req-9',
            [],
            'application/json',
            ['X-Shopex-OMS-Client-Mode' => 'bearer']
        );
        $this->assertSame(401, $bad->status());
    }

    public function testFrontControllerAllowsAuthorizeGetWithoutContentType(): void
    {
        $front = new mcpapi_front_controller(
            fn(string $method, string $path, string $auth, string $rid, array $req, ?string $ct, array $h = []) => mcpapi_http_response::fromJson(200, ['reached' => true, 'method' => $method, 'request' => $req]),
            null,
            null,
            null,
            null
        );

        $_GET = ['response_type' => 'code', 'client_id' => 'mcp_x', 'redirect_uri' => 'workbuddy://cb'];
        $response = $front->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/mcp-api/oauth/authorize?response_type=code&client_id=mcp_x&redirect_uri=workbuddy%3A%2F%2Fcb',
            'SCRIPT_NAME' => '/index.php',
        ], '');
        unset($_GET);

        $this->assertSame(200, $response->status());
        $this->assertTrue($response->body()['reached']);
        $this->assertSame('code', $response->body()['request']['response_type']);
    }

    public function testFrontControllerStillRejectsOtherContentTypelessGet(): void
    {
        $front = new mcpapi_front_controller(
            fn(string $method, string $path, string $auth, string $rid, array $req, ?string $ct, array $h = []) => mcpapi_http_response::fromJson(200, ['reached' => true])
        );

        $response = $front->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/mcp-api/v1/order',
            'SCRIPT_NAME' => '/index.php',
        ], '');

        $this->assertSame(415, $response->status());
    }

    private function router(): mcpapi_router
    {
        return new mcpapi_router($this->guard());
    }

    private function publicClientRow(string $clientId): array
    {
        return [
            'client_id' => $clientId,
            'owner_user_id' => 0,
            'name' => 'Anonymous test client ' . $clientId,
            'tenant_id' => 'default',
            'oms_base_url' => 'https://oms.example',
            'oms_subject_id' => 0,
            'oms_role_ids' => [],
            'oms_permissions' => [],
            'scopes' => ['oms:read'],
            'client_type' => 'public',
            'redirect_uris' => ['workbuddy://cb'],
            'registration_origin' => 'dynamic',
            'token_endpoint_auth' => 'none',
            'enabled' => true,
            'revoked_at' => null,
            'expires_at' => $this->now + 86400,
        ];
    }

    /** 匿名 authorize 用内存服务构造的控制器（不触碰 DB） */
    private function authorizeController(): mcpapi_ctl_oauth_authorize
    {
        $clients = $this->clients;
        return new mcpapi_ctl_oauth_authorize(
            function (string $clientId) use ($clients): ?array {
                $row = $clients[$clientId] ?? null;
                if (!is_array($row)) {
                    return null;
                }
                return $row; // 测试行 enabled=true / revoked_at=null / expires_at=now+86400
            },
            new mcpapi_auth_oauth_scopes(),
            new mcpapi_auth_oauth_consents(
                static fn(array $filter): array => [],
                static function (array $row): void {},
                static function (array $data, array $filter): void {},
                $this->now
            ),
            new mcpapi_auth_oauth_codes(
                static fn(array $filter): array => [],
                static function (array $row): void {},
                static function (array $data, array $filter): void {},
                $this->now
            )
        );
    }

    /** client 未知场景：clientFinder 恒返回 null */
    private function authorizeClientUnknown(): mcpapi_ctl_oauth_authorize
    {
        return new mcpapi_ctl_oauth_authorize(
            static fn(string $clientId): ?array => null,
            new mcpapi_auth_oauth_scopes(),
            new mcpapi_auth_oauth_consents(
                static fn(array $filter): array => [],
                static function (array $row): void {},
                static function (array $data, array $filter): void {},
                $this->now
            ),
            new mcpapi_auth_oauth_codes(
                static fn(array $filter): array => [],
                static function (array $row): void {},
                static function (array $data, array $filter): void {},
                $this->now
            )
        );
    }

    private function guard(): mcpapi_auth_guard
    {
        return new mcpapi_auth_guard(
            static fn(): array => throw new mcpapi_auth_exception(),
            null,
            null,
            null,
            null,
            null,
            fn(string $clientId): ?array => $this->clients[$clientId] ?? null,
            fn(string $token): array => (new mcpapi_auth_oauth_askeys(
                fn(array $filter): array => array_values($this->asKeys),
                function (array $row): void {
                    $this->asKeys[$row['kid']] = $row;
                },
                $this->now
            ))->verify($token)
        );
    }
}
