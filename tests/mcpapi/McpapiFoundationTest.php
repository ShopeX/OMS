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

final class McpapiFoundationTest extends TestCase
{
    private string $cwdBeforeTest;

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
        chdir($this->cwdBeforeTest);
        $this->assertSame($this->cwdBeforeTest, getcwd());
        parent::tearDown();
    }

    public function testMcpapiClassesResolveThroughOmsAutoload(): void
    {
        $this->assertTrue(class_exists('mcpapi_auth_context'));
        $this->assertTrue(class_exists('mcpapi_auth_guard'));
        $this->assertTrue(class_exists('mcpapi_http_response'));
        $this->assertTrue(class_exists('mcpapi_ctl_v1_health'));
        $this->assertTrue(class_exists('mcpapi_router'));
        $this->assertTrue(class_exists('mcpapi_ctl_v1_internal_exchange'));
    }

    public function testMcpapiIsRegisteredAsAnApplicationRoute(): void
    {
        include ROOT_DIR . '/config/mapper.php';

        $this->assertSame('mcpapi', $urlmap['/mcp-api']['app']);
    }

    public function testMcpapiProductionFilesDeferSiblingLoadingToOmsAutoload(): void
    {
        foreach ([
            'app/mcpapi/controller/v1/health.php',
            'app/mcpapi/lib/router.php',
            'app/mcpapi/lib/auth/guard.php',
        ] as $path) {
            $this->assertStringNotContainsString('require_once', file_get_contents($path));
        }
    }

    public function testAuthExceptionUsesOmsAutoloadCompatibleFile(): void
    {
        $this->assertFileExists('app/mcpapi/lib/auth/exception.php');
        $this->assertStringNotContainsString(
            'class mcpapi_auth_exception',
            file_get_contents('app/mcpapi/lib/auth/guard.php')
        );
        $this->assertTrue(class_exists('mcpapi_auth_exception'));
    }

    public function testRouterAcceptsValidBasicCredentials(): void
    {
        $router = new mcpapi_router($this->basicGuard());

        $response = $router->dispatch('GET', '/mcp-api/v1/health', $this->basicAuthorization(), 'request-123');

        $this->assertSame(200, $response->status());
    }

    public function testCwdRestorationFixtureChangesWorkingDirectory(): void
    {
        chdir(sys_get_temp_dir());
        $this->addToAssertionCount(1);
    }

    public function testContextExposesImmutableVerifiedIdentity(): void
    {
        $context = new mcpapi_auth_context(
            'request-123',
            'client-123',
            'subject-123',
            'tenant-123',
            'https://oms.example.internal',
            42,
            [7],
            ['health.read'],
            ['health:read'],
            1893456000
        );

        $scopes = $context->scopes();
        $scopes[] = 'admin';

        $this->assertSame('request-123', $context->requestId());
        $this->assertSame('client-123', $context->clientId());
        $this->assertSame('subject-123', $context->subject());
        $this->assertSame('tenant-123', $context->tenantId());
        $this->assertSame(['health:read'], $context->scopes());
        $this->assertSame(1893456000, $context->expiresAt());
    }

    public function testGuardAcceptsMcpAudienceAndUsesVerifiedTenantClaim(): void
    {
        $guard = new mcpapi_auth_guard(static function (string $token): array {
            unset($token);
            return [
                'aud' => 'mcp-api',
                'client_id' => 'client-123',
                'external_subject' => 'subject-123',
                'tenant_id' => 'tenant-from-verified-claims',
                'oms_base_url' => 'https://oms.example.internal',
                'oms_subject_id' => 42,
                'oms_role_ids' => [7],
                'oms_permissions' => ['health.read'],
                'scope' => 'health:read',
                'exp' => 1893456000,
            ];
        });

        $context = $guard->authenticate('Bearer test-token', 'request-123');

        $this->assertSame('tenant-from-verified-claims', $context->tenantId());
        $this->assertSame('subject-123', $context->subject());
    }

    public function testGuardRejectsBrowserAudience(): void
    {
        $guard = new mcpapi_auth_guard(static function (string $token): array {
            unset($token);
            return [
                'aud' => 'browser',
                'client_id' => 'client-123',
                'sub' => 'subject-123',
                'tenant_id' => 'tenant-123',
                'exp' => 1893456000,
            ];
        });

        $this->expectException(mcpapi_auth_exception::class);
        $this->expectExceptionCode(401);
        $guard->authenticate('Bearer browser-token', 'request-123');
    }

    public function testGuardAuthenticatesOwnerWithBasicClientCredentials(): void
    {
        $guard = new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static function (string $clientId, string $clientSecret): ?mcpapi_auth_client_record {
                return $clientId === 'client-123' && $clientSecret === 'secret-123'
                    ? new mcpapi_auth_client_record(
                        'client-123',
                        'tenant-123',
                        'https://oms.example.internal',
                        42,
                        [7],
                        ['health.read'],
                        ['health:read'],
                        true,
                        null,
                        1893456000,
                        '',
                        '',
                        '',
                        '',
                        null,
                        42
                    )
                    : null;
            }
        );

        $context = $guard->authenticateBasic('Basic ' . base64_encode('client-123:secret-123'), 'request-123');

        $this->assertSame('client-123', $context->clientId());
        $this->assertSame(42, $context->omsSubjectId());
    }

    public function testRouterMatchesOnlyExplicitHealthRoute(): void
    {
        $verified = static function (string $token): array {
            unset($token);
            return [
                'aud' => 'mcp-api',
                'client_id' => 'client-123',
                'external_subject' => 'subject-123',
                'tenant_id' => 'tenant-123',
                'oms_base_url' => 'https://oms.example.internal',
                'oms_subject_id' => 42,
                'oms_role_ids' => [7],
                'oms_permissions' => ['health.read'],
                'scope' => 'health:read',
                'exp' => 1893456000,
            ];
        };
        $router = new mcpapi_router($this->basicGuard());

        $health = $router->dispatch('GET', '/mcp-api/v1/health', $this->basicAuthorization(), 'request-123');
        $unknownMethod = $router->dispatch('POST', '/mcp-api/v1/health', $this->basicAuthorization(), 'request-123');
        $unknownPath = $router->dispatch('GET', '/mcp-api/v1/health/extra', $this->basicAuthorization(), 'request-123');
        $legacyPath = $router->dispatch('GET', '/v1/health', $this->basicAuthorization(), 'request-123');

        $this->assertSame(200, $health->status());
        $this->assertSame(['status' => 'ok'], $health->body());
        $this->assertSame(404, $unknownMethod->status());
        $this->assertSame(['error' => 'not_found'], $unknownMethod->body());
        $this->assertSame(404, $unknownPath->status());
        $this->assertSame(['error' => 'not_found'], $unknownPath->body());
        $this->assertSame(404, $legacyPath->status());
        $this->assertSame(['error' => 'not_found'], $legacyPath->body());
    }

    public function testRouterUsesBasicAuthForMcpBusinessRoutes(): void
    {
        $record = new mcpapi_auth_client_record(
            'client-123',
            'tenant-123',
            'https://oms.example.internal',
            42,
            [7],
            ['health.read'],
            ['health:read'],
            true,
            null,
            1893456000,
            '',
            '',
            '',
            '',
            null,
            42
        );
        $router = new mcpapi_router(new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static fn(string $clientId, string $clientSecret): ?mcpapi_auth_client_record
                => $clientId === 'client-123' && $clientSecret === 'secret-123' ? $record : null
        ));

        $response = $router->dispatch(
            'GET',
            '/mcp-api/v1/health',
            'Basic ' . base64_encode('client-123:secret-123'),
            'request-123'
        );

        $this->assertSame(200, $response->status());
    }

    public function testRouterRequiresEveryPermissionDeclaredByAllAnnotation(): void
    {
        require_once ROOT_DIR . '/app/desktop/lib/annotation/RequirePermission.php';

        $router = new mcpapi_router(
            $this->permissionGuardWithPermissions(['perm.a']),
            new mcpapi_ctl_v1_permission_probe()
        );

        $missingPermission = $router->dispatch(
            'POST',
            '/mcp-api/v1/permission/probe/list',
            'Basic ' . base64_encode('permission-client:permission-secret'),
            'request-123',
            []
        );

        $this->assertSame(403, $missingPermission->status());

        $router = new mcpapi_router(
            $this->permissionGuardWithPermissions(['perm.a', 'perm.b']),
            new mcpapi_ctl_v1_permission_probe()
        );

        $authorized = $router->dispatch(
            'POST',
            '/mcp-api/v1/permission/probe/list',
            'Basic ' . base64_encode('permission-client:permission-secret'),
            'request-123',
            []
        );

        $this->assertSame(200, $authorized->status());
        $this->assertSame(['ok' => true], $authorized->body());
    }

    public function testRouterDeniesMalformedPermissionAnnotation(): void
    {
        require_once ROOT_DIR . '/app/desktop/lib/annotation/RequirePermission.php';

        $router = new mcpapi_router(
            $this->permissionGuard('perm.a'),
            new mcpapi_ctl_v1_malformed_permission()
        );

        $response = $router->dispatch(
            'GET',
            '/mcp-api/v1/malformed_permission',
            'Basic ' . base64_encode('permission-client:permission-secret'),
            'request-123'
        );

        $this->assertSame(403, $response->status());
        $this->assertSame(['error' => 'permission_denied'], $response->body());
    }

    public function testRouterNormalizesIndexPhpMcpApiPrefix(): void
    {
        $router = new mcpapi_router($this->basicGuard());

        $response = $router->dispatch(
            'GET',
            '/index.php/mcp-api/v1/health',
            $this->basicAuthorization(),
            'request-123',
            [],
            'application/json'
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(['status' => 'ok'], $response->body());
    }

    public function testPrivateExchangeRouteRequiresPostAndRejectsUserBody(): void
    {
        $router = $this->routerWithExchange();

        $get = $router->dispatch('GET', '/mcp-api/v1/internal/exchange', 'Bearer internal-token', 'request-123');
        $body = $router->dispatch(
            'POST',
            '/mcp-api/v1/internal/exchange',
            'Bearer client-token',
            'request-123',
            ['client_id' => 'attacker', 'tenant_id' => 'attacker', 'scope' => ['admin']]
        );

        $this->assertSame(404, $get->status());
        $this->assertSame(['error' => 'not_found'], $get->body());
        $this->assertSame(404, $body->status());
        $this->assertSame(['error' => 'not_found'], $body->body());
    }

    public function testRemovedExchangeAndJwksRoutesReturnNotFound(): void
    {
        $router = new mcpapi_router(new mcpapi_auth_guard(static fn(string $token): array => []));

        $exchange = $router->dispatch('POST', '/mcp-api/v1/internal/exchange', '', 'request-123', [], 'application/json');
        $jwks = $router->dispatch('GET', '/mcp-api/v1/jwks', '', 'request-123', [], 'application/json');

        $this->assertSame(404, $exchange->status());
        $this->assertSame(404, $jwks->status());
    }

    public function testRemovedExchangeDoesNotIssueAccessToken(): void
    {
        $response = $this->routerWithExchange()->dispatch(
            'POST',
            '/mcp-api/v1/internal/exchange',
            'Bearer client-token',
            'request-123',
            []
        );

        $this->assertSame(404, $response->status());
        $this->assertSame(['error' => 'not_found'], $response->body());
    }

    public function testInternalAudienceCannotAccessHealthAndMcpAudienceCannotExchange(): void
    {
        $router = $this->routerWithExchange();

        $internalOnHealth = $router->dispatch('GET', '/mcp-api/v1/health', 'Bearer internal-token', 'request-123');
        $mcpOnExchange = $router->dispatch('POST', '/mcp-api/v1/internal/exchange', 'Bearer mcp-token', 'request-123');

        $this->assertSame(401, $internalOnHealth->status());
        $this->assertSame(['error' => 'unauthorized'], $internalOnHealth->body());
        $this->assertSame(404, $mcpOnExchange->status());
        $this->assertSame(['error' => 'not_found'], $mcpOnExchange->body());
    }

    public function testSameBasicCredentialCanBeAcceptedRepeatedly(): void
    {
        $router = new mcpapi_router($this->basicGuard());

        $first = $router->dispatch('GET', '/mcp-api/v1/health', $this->basicAuthorization(), 'request-1');
        $second = $router->dispatch('GET', '/mcp-api/v1/health', $this->basicAuthorization(), 'request-2');

        $this->assertSame(200, $first->status());
        $this->assertSame(200, $second->status());
    }

    public function testGuardReturnsEquivalentContextsForRepeatedInternalAccessTokenAuthentication(): void
    {
        $issuer = new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60);
        $verifier = new mcpapi_auth_token_verifier(static fn(): string => 'test-only-key', 'test-issuer');
        $now = time();
        $exchange = new mcpapi_auth_exchange(
            new class implements mcpapi_auth_client_registry {
                public function find(string $clientId): ?mcpapi_auth_client_record
                {
                    return $clientId === 'client-123'
                        ? new mcpapi_auth_client_record('client-123', 'tenant-123', 'https://oms.example.internal', 42, [7], ['orders.read'], ['orders:read'], true, null, time() + 1000)
                        : null;
                }
            },
            $issuer
        );
        $token = $exchange->issue(new mcpapi_gateway_identity('client-123', 'gateway-subject'), $now);
        $guard = new mcpapi_auth_guard(static fn(string $value): array => $verifier->verify($value, $now));

        $first = $guard->authenticate('Bearer ' . $token, 'request-123');
        $second = $guard->authenticate('Bearer ' . $token, 'request-123');

        $this->assertEquals($first, $second);
    }

    private function routerWithExchange(): mcpapi_router
    {
        $verifier = static function (string $token): array {
            return match ($token) {
                'internal-token' => [
                    'aud' => 'mcp-api-internal',
                    'client_id' => 'client-123',
                    'sub' => 'gateway-subject',
                    'exp' => 1893456000,
                ],
                'mcp-token' => [
                    'aud' => 'mcp-api',
                    'client_id' => 'client-123',
                    'external_subject' => 'subject-123',
                    'tenant_id' => 'tenant-123',
                    'oms_base_url' => 'https://oms.example.internal',
                    'oms_subject_id' => 42,
                    'oms_role_ids' => [7],
                    'oms_permissions' => ['health.read'],
                    'scope' => ['health:read'],
                    'exp' => 1893456000,
                ],
                default => throw new mcpapi_auth_exception(),
            };
        };
        $clientVerifier = static function (string $token): array {
            if ($token !== 'client-token') {
                throw new mcpapi_auth_exception();
            }
            return [
                'iss' => 'oms-mcp-client',
                'aud' => 'oms-mcp-gateway',
                'iat' => 1893455900,
                'exp' => 1893456000,
                'jti' => 'assertion-123',
                'client_id' => 'client-123',
                'sub' => 'gateway-subject',
            ];
        };
        $registry = new class implements mcpapi_auth_client_registry {
            public function find(string $clientId): ?mcpapi_auth_client_record
            {
                return $clientId === 'client-123'
                    ? new mcpapi_auth_client_record('client-123', 'tenant-123', 'https://oms.example.internal', 42, [7], ['orders.read'], ['orders:read'], true, null, 1893456000)
                    : null;
            }
        };
        $exchange = new mcpapi_auth_exchange(
            $registry,
            new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60)
        );

        return new mcpapi_router(
            new mcpapi_auth_guard($verifier, $clientVerifier),
            null,
            new mcpapi_ctl_v1_internal_exchange($exchange)
        );
    }

    public function testHealthResponseDoesNotExposeClaimsOrSecrets(): void
    {
        $context = new mcpapi_auth_context(
            'request-123',
            'client-secret-value',
            'subject-secret-value',
            'tenant-secret-value',
            'https://oms.example.internal',
            42,
            [7],
            ['health.read'],
            ['health:read'],
            1893456000
        );
        $response = (new mcpapi_ctl_v1_health())->handle($context);

        $this->assertSame(200, $response->status());
        $this->assertSame(['status' => 'ok'], $response->body());
        $this->assertStringNotContainsString('secret', strtolower($response->json()));
        $this->assertStringNotContainsString('tenant', strtolower($response->json()));
        $this->assertStringNotContainsString('subject', strtolower($response->json()));
    }

    public function testConventionRouterDispatchesControllerWithoutRouteDeclaration(): void
    {
        $router = new mcpapi_router(
            $this->basicGuard(),
            new mcpapi_ctl_v1_convention()
        );

        $default = $router->dispatch('GET', '/mcp-api/v1/convention', $this->basicAuthorization(), 'request-123');
        $action = $router->dispatch(
            'POST',
            '/mcp-api/v1/convention/rotate',
            $this->basicAuthorization(),
            'request-123',
            ['name' => 'convention']
        );
        $unknown = $router->dispatch('GET', '/mcp-api/v1/convention/unknown', $this->basicAuthorization(), 'request-123');
        $helper = $router->dispatch('POST', '/mcp-api/v1/convention/helper', $this->basicAuthorization(), 'request-123');
        $wrongMethod = $router->dispatch('GET', '/mcp-api/v1/convention/rotate', $this->basicAuthorization(), 'request-123');

        $this->assertSame(['action' => 'handle'], $default->body());
        $this->assertSame(['action' => 'rotate', 'request' => ['name' => 'convention']], $action->body());
        $this->assertSame(404, $unknown->status());
        $this->assertSame(404, $helper->status());
        $this->assertSame(404, $wrongMethod->status());
    }

    public function testRouterRejectsMissingMethodPermissionBeforeInvocation(): void
    {
        $controller = new mcpapi_ctl_v1_permission_test();
        $router = new mcpapi_router($this->basicGuard(), $controller);

        $response = $router->dispatch(
            'GET',
            '/mcp-api/v1/permission_test',
            $this->basicAuthorization(),
            'request-permission-denied'
        );

        $this->assertSame(403, $response->status());
        $this->assertSame(['error' => 'permission_denied'], $response->body());
        $this->assertSame(0, $controller->invocations());
    }

    public function testRouterInvokesMethodWithRequiredPermission(): void
    {
        $controller = new mcpapi_ctl_v1_permission_test();
        $router = new mcpapi_router($this->permissionGuard('test.permission'), $controller);

        $response = $router->dispatch(
            'GET',
            '/mcp-api/v1/permission_test',
            'Basic ' . base64_encode('permission-client:permission-secret'),
            'request-permission-allowed'
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(['status' => 'invoked'], $response->body());
        $this->assertSame(1, $controller->invocations());
    }

    public function testRouterAllowsAnnotatedPermissionForLiveSuperOwner(): void
    {
        $controller = new mcpapi_ctl_v1_permission_test();
        $router = new mcpapi_router($this->superPermissionGuard(), $controller);

        $response = $router->dispatch(
            'GET',
            '/mcp-api/v1/permission_test',
            'Basic ' . base64_encode('super-permission-client:super-permission-secret'),
            'request-super-permission'
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(['status' => 'invoked'], $response->body());
        $this->assertSame(1, $controller->invocations());
    }

    public function testRouterAllowsExplicitAutoconfirmDomainActions(): void
    {
        $router = new mcpapi_router($this->permissionGuard('auto_confirm'));
        $reflection = new ReflectionMethod(mcpapi_router::class, 'validAction');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($router, 'v1_autoconfirm', 'setStatus', 'POST', true));
        $this->assertTrue($reflection->invoke($router, 'v1_autoconfirm', 'orderTypes', 'POST', true));
        $this->assertFalse($reflection->invoke($router, 'v1_autoconfirm', 'unknown', 'POST', true));
    }

    public function testRouterAllowsExplicitAutodispatchDomainActions(): void
    {
        $router = new mcpapi_router($this->permissionGuard('auto_dispatch'));
        $reflection = new ReflectionMethod(mcpapi_router::class, 'validAction');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($router, 'v1_autodispatch', 'setStatus', 'POST', true));
        $this->assertTrue($reflection->invoke($router, 'v1_autodispatch', 'orderTypeDetail', 'POST', true));
        $this->assertFalse($reflection->invoke($router, 'v1_autodispatch', 'unknown', 'POST', true));
    }

    public function testRouterAllowsExplicitMaterialCatSortAction(): void
    {
        $router = new mcpapi_router($this->permissionGuard('material_cat'));
        $reflection = new ReflectionMethod(mcpapi_router::class, 'validAction');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($router, 'v1_material_cat', 'sort', 'POST', true));
        $this->assertFalse($reflection->invoke($router, 'v1_material_cat', 'unknown', 'POST', true));
    }

    public function testRouterMapsSalesDetailStatisticsExplicitPostIndex(): void
    {
        $capability = new class extends omeanalysts_capability_sales_detail_statistics {
            public function __construct() {}

            public function execute(array $request, array $allowedOrganizationIds = []): array
            {
                unset($request, $allowedOrganizationIds);
                return [
                    'detail' => ['list' => [], 'total' => 0, 'page' => 1, 'page_size' => 20],
                    'summary' => [],
                    'aggregations' => ['by_product' => [], 'by_shop' => []],
                ];
            }
        };
        $desktopUser = new class extends desktop_user {
            public function __construct() {}

            public function is_super()
            {
                return true;
            }

            public function get_organization_permission()
            {
                return [];
            }
        };
        $controller = new mcpapi_ctl_v1_sales_detail_statistics($capability, $desktopUser);

        $router = new mcpapi_router($this->permissionGuard('analysis_products'), $controller);
        $response = $router->dispatch(
            'POST',
            '/mcp-api/v1/sales/detail/statistics/index',
            'Basic ' . base64_encode('permission-client:permission-secret'),
            'request-sales-detail-statistics',
            ['material_type' => 'basic_material'],
            'application/json'
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(['detail', 'summary', 'aggregations'], array_keys($response->body()));

        $notFound = $router->dispatch(
            'POST',
            '/mcp-api/v1/sales/detail/statistics',
            'Basic ' . base64_encode('permission-client:permission-secret'),
            'request-sales-detail-statistics-implicit',
            ['material_type' => 'basic_material'],
            'application/json'
        );

        $this->assertSame(404, $notFound->status());
    }

    public function testSuperOwnerPassesAutodispatchPermissionCheck(): void
    {
        $controller = new mcpapi_ctl_v1_autodispatch();

        $response = $controller->orderTypes($this->businessContext('auto_dispatch', true), []);

        $this->assertSame(200, $response->status());
        $this->assertArrayHasKey('rows', $response->body());
    }

    public function testAutoconfirmRejectsMutationWithoutVisibleTarget(): void
    {
        $controller = new mcpapi_ctl_v1_autoconfirm();
        $context = $this->businessContext('auto_confirm');

        $status = $controller->setStatus($context, ['oid' => 0, 'status' => 'true']);
        $update = $controller->update($context, ['oid' => 0]);

        $this->assertSame(404, $status->status());
        $this->assertSame(['error' => 'not_found'], $status->body());
        $this->assertSame(404, $update->status());
        $this->assertSame(['error' => 'not_found'], $update->body());
    }

    private function basicGuard(): mcpapi_auth_guard
    {
        $record = new mcpapi_auth_client_record(
            'client-123',
            'tenant-123',
            'https://oms.example.internal',
            42,
            [7],
            ['health.read'],
            ['health:read'],
            true,
            null,
            1893456000,
            '',
            '',
            '',
            '',
            null,
            42
        );
        return new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static fn(string $clientId, string $clientSecret): ?mcpapi_auth_client_record
                => $clientId === 'client-123' && $clientSecret === 'secret-123' ? $record : null
        );
    }

    private function basicAuthorization(): string
    {
        return 'Basic ' . base64_encode('client-123:secret-123');
    }

    private function permissionGuard(string $permission): mcpapi_auth_guard
    {
        return $this->permissionGuardWithPermissions([$permission]);
    }

    private function permissionGuardWithPermissions(array $permissions): mcpapi_auth_guard
    {
        $record = new mcpapi_auth_client_record(
            'permission-client',
            'tenant-123',
            'https://oms.example.internal',
            42,
            [7],
            $permissions,
            $permissions,
            true,
            null,
            1893456000,
            '',
            '',
            '',
            '',
            null,
            0
        );
        return new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static fn(string $clientId, string $clientSecret): ?mcpapi_auth_client_record
                => $clientId === 'permission-client' && $clientSecret === 'permission-secret' ? $record : null
        );
    }

    private function superPermissionGuard(): mcpapi_auth_guard
    {
        $record = new mcpapi_auth_client_record(
            'super-permission-client',
            'tenant-123',
            'https://oms.example.internal',
            42,
            [],
            [],
            ['test:permission'],
            true,
            null,
            1893456000,
            '',
            '',
            '',
            '',
            null,
            42
        );
        $owner = new mcpapi_auth_owner(
            static fn(int $userId): array => ['user_id' => $userId, 'status' => '1', 'super' => '1'],
            static fn(int $userId): array => []
        );
        return new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static fn(string $clientId, string $clientSecret): ?mcpapi_auth_client_record
                => $clientId === 'super-permission-client' && $clientSecret === 'super-permission-secret' ? $record : null,
            $owner
        );
    }

    private function businessContext(string $permission, bool $isSuper = false): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-business',
            'client-business',
            'client-business',
            'tenant-business',
            'https://oms.example.internal',
            42,
            [7],
            [$permission],
            [$permission],
            1893456000,
            $isSuper
        );
    }
}

final class mcpapi_ctl_v1_convention
{
    #[\app\desktop\lib\annotation\RequirePermission('health.read')]
    public function handle(mcpapi_auth_context $context): mcpapi_http_response
    {
        unset($context);
        return mcpapi_http_response::fromJson(200, ['action' => 'handle']);
    }

    #[\app\desktop\lib\annotation\RequirePermission('health.read')]
    public function rotate(mcpapi_auth_context $context, array $request = []): mcpapi_http_response
    {
        unset($context);
        return mcpapi_http_response::fromJson(200, ['action' => 'rotate', 'request' => $request]);
    }

    public function helper(mcpapi_auth_context $context): mcpapi_http_response
    {
        unset($context);
        return mcpapi_http_response::fromJson(200, ['action' => 'helper']);
    }
}

final class mcpapi_ctl_v1_permission_test
{
    private int $invocations = 0;

    #[\app\desktop\lib\annotation\RequirePermission('test.permission')]
    public function handle(mcpapi_auth_context $context): mcpapi_http_response
    {
        unset($context);
        $this->invocations++;
        return mcpapi_http_response::fromJson(200, ['status' => 'invoked']);
    }

    public function invocations(): int
    {
        return $this->invocations;
    }
}

final class mcpapi_ctl_v1_permission_probe
{
    #[\app\desktop\lib\annotation\RequirePermission(all: ['perm.a', 'perm.b'])]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return mcpapi_http_response::fromJson(200, ['ok' => true]);
    }
}

final class mcpapi_ctl_v1_malformed_permission
{
    #[\app\desktop\lib\annotation\RequirePermission(all: [''])]
    public function handle(mcpapi_auth_context $context): mcpapi_http_response
    {
        unset($context);
        return mcpapi_http_response::fromJson(200, ['ok' => true]);
    }
}
