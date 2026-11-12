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

final class ProductionIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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

    public function testProductionAdapterPreservesFullRequestEnvelopeForAllMcpRoutes(): void
    {
        $calls = [];
        $adapter = new mcpapi_front_controller(static function (...$arguments) use (&$calls): mcpapi_http_response {
            $calls[] = $arguments;
            return mcpapi_http_response::fromJson(200, ['status' => 'ok']);
        });

        foreach ([
            ['GET', '/mcp-api/client', ''],
            ['GET', '/mcp-api/v1/jwks', ''],
            ['GET', '/mcp-api/v1/health', ''],
            ['POST', '/mcp-api/v1/internal/exchange', '{"assertion":"client"}'],
        ] as [$method, $path, $body]) {
            $adapter->handle([
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $path . '?trace=1',
                'HTTP_AUTHORIZATION' => 'Bearer token',
                'HTTP_X_REQUEST_ID' => 'request-123',
                'HTTP_X_FORWARDED_HOST' => 'oms.example.test',
            'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CUSTOM_HEADER' => 'preserve-me',
            ], $body);
        }

        $this->assertCount(4, $calls);
        $this->assertSame('/mcp-api/client', $calls[0][1]);
        $this->assertSame('/mcp-api/v1/jwks', $calls[1][1]);
        $this->assertSame('/mcp-api/v1/health', $calls[2][1]);
        $this->assertSame('/mcp-api/v1/internal/exchange', $calls[3][1]);
        $this->assertSame('POST', $calls[3][0]);
        $this->assertSame(['assertion' => 'client'], $calls[3][4]);
        $this->assertSame('preserve-me', $calls[0][6]['HTTP_X_CUSTOM_HEADER']);
    }

    public function testBrowserClientMutationRequiresCsrfAndPrivateKeyResponsesAreNotCacheable(): void
    {
        desktop_auth_context::setUserId(7);
        $manager = new mcpapi_auth_client_manager(
            static fn(): array => [],
            static fn(): array => [],
            static function (): void {},
            static function (): void {},
            static function (): void {},
            static function (): void {},
            static fn(): string => 'Alice',
            1720000000
        );
        $controller = new mcpapi_ctl_client($manager, static fn(): bool => true);
        $adapter = new mcpapi_front_controller(
            static function (string $method, string $path, string $authorization, string $requestId, array $request, ?string $contentType) use ($controller): mcpapi_http_response {
                return $controller->create($request);
            },
            static fn(): bool => true,
            static fn(): bool => false
        );

        $csrfFailure = $adapter->handle([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/mcp-api/client',
            'CONTENT_TYPE' => 'application/json',
        ], '{"name":"agent"}');

        $this->assertSame(403, $csrfFailure->status());
        $this->assertSame(['error' => 'csrf_failed'], $csrfFailure->body());
    }

    public function testIndexPhpMcpApiPathIsNormalizedBeforeDispatch(): void
    {
        $calls = [];
        $adapter = new mcpapi_front_controller(static function (...$arguments) use (&$calls): mcpapi_http_response {
            $calls[] = $arguments;
            return mcpapi_http_response::fromJson(200, ['status' => 'ok']);
        });

        $response = $adapter->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/index.php/mcp-api/client?trace=1',
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $this->assertSame(200, $response->status());
        $this->assertSame('/mcp-api/client', $calls[0][1]);
    }

    public function testApplicationRouterDispatchesThroughTheMcpapiFrontController(): void
    {
        $serverBefore = $_SERVER;
        $outputBefore = ob_get_level();
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/index.php/mcp-api/v1/health',
            'SCRIPT_NAME' => '/index.php',
            'CONTENT_TYPE' => 'application/json',
        ];

        try {
            $router = new mcpapi_router(new app('mcpapi'));
            ob_start();
            $response = $router->dispatch('v1/health');
            $output = ob_get_clean();

            $this->assertSame(401, $response->status());
            $this->assertSame($response->json(), $output);
        } finally {
            while (ob_get_level() > $outputBefore) {
                ob_end_clean();
            }
            $_SERVER = $serverBefore;
        }
    }
}
