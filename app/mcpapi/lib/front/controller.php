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

final class mcpapi_front_controller
{
    private ?object $classificationRouter = null;
    private $dispatcher;
    private $authBootstrap;
    private $csrfVerifier;
    private $browserRouteChecker;
    private $browserMutationChecker;

    public function __construct(
        ?callable $dispatcher = null,
        ?callable $authBootstrap = null,
        ?callable $csrfVerifier = null,
        ?callable $browserRouteChecker = null,
        ?callable $browserMutationChecker = null
    )
    {
        $usesProductionDispatcher = $dispatcher === null;
        $this->dispatcher = $dispatcher ?? $this->productionDispatcher();
        $this->authBootstrap = $authBootstrap ?? static function (): void {
            desktop_auth_request::bootstrap();
        };
        $this->csrfVerifier = $csrfVerifier ?? static function (): bool {
            return kernel::single('desktop_csrf', app::get('desktop'))->verify();
        };
        if ($browserRouteChecker !== null) {
            $this->browserRouteChecker = $browserRouteChecker;
        } elseif (!$usesProductionDispatcher) {
            $this->browserRouteChecker = [$this, 'isBrowserClientRequest'];
        }
        if ($browserMutationChecker !== null) {
            $this->browserMutationChecker = $browserMutationChecker;
        } elseif (!$usesProductionDispatcher) {
            $this->browserMutationChecker = [$this, 'isBrowserClientMutation'];
        }
    }

    public static function isRequest(?array $server = null): bool
    {
        $server = $server ?? $_SERVER;
        $path = self::requestPath($server);
        return is_string($path) && str_starts_with($path, '/mcp-api/');
    }

    public static function run(): void
    {
        $response = (new self())->handle($_SERVER, file_get_contents('php://input') ?: '');
        http_response_code($response->status());
        foreach ($response->headers() as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $response->isHtml() ? $response->html() : $response->json();
    }

    public function handle(array $server, string $rawBody): mcpapi_http_response
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $path = self::requestPath($server);
        if (!is_string($path) || !self::isRequest($server)) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found']);
        }

        $contentType = $server['CONTENT_TYPE'] ?? null;
        $mediaType = strtolower(trim(explode(';', (string) $contentType, 2)[0]));
        // OAuth authorize GET：浏览器地址栏直达（无 Content-Type），按 query 参数解析
        $browserEntryPoint = $this->isOauthAuthorizeGet($method, $path, $contentType);
        // OAuth 端点 POST（RFC 6749 §4.1.3 token / authorize 授权确认表单）：
        // 内容类型固定 application/x-www-form-urlencoded，不属于 MCP API 的 JSON 调用面。
        $oauthFormPost = $method === 'POST'
            && $mediaType === 'application/x-www-form-urlencoded'
            && ($path === '/mcp-api/oauth/authorize' || $path === '/mcp-api/oauth/token'
                || $path === '/mcp-api/oauth/register' || $path === '/mcp-api/oauth/login');
        if (!$browserEntryPoint && !$oauthFormPost
            && ($contentType === null || !in_array($mediaType, ['application/json', 'multipart/form-data'], true))) {
            return mcpapi_http_response::fromJson(415, ['error' => 'unsupported_media_type']);
        }
        $request = $browserEntryPoint
            ? (is_array($_GET ?? null) ? $_GET : [])
            : ($oauthFormPost
                ? (is_array($_POST ?? null) ? $_POST : [])
                : ($mediaType === 'multipart/form-data'
                    ? $this->parseMultipartBody()
                    : $this->parseBody($rawBody, $contentType)));
        if ($request === null) {
            return mcpapi_http_response::fromJson(400, ['error' => 'invalid_request']);
        }

        if (($this->browserRouteChecker)($method, $path)) {
            ($this->authBootstrap)();
            // oauth authorize 同意表单（服务端渲染）无 JS 环境，_csrf 表单字段映射为 header 后走双提交校验
            if ($path === '/mcp-api/oauth/authorize' && $method === 'POST'
                && (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') === ''
                && is_string($_POST['_csrf'] ?? null) && $_POST['_csrf'] !== '') {
                $_SERVER['HTTP_X_CSRF_TOKEN'] = $_POST['_csrf'];
            }
            if (($this->browserMutationChecker)($method, $path)
                && desktop_auth_context::isAuthenticated()
                && !(($this->csrfVerifier)())) {
                return mcpapi_http_response::fromJson(403, ['error' => 'csrf_failed']);
            }
        }

        $headers = $server;
        $authorization = (string) ($server['HTTP_AUTHORIZATION'] ?? '');
        $requestId = (string) ($server['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(16)));

        return ($this->dispatcher)($method, $path, $authorization, $requestId, $request, $contentType, $headers);
    }

    /**
     * OAuth authorize GET：无 Content-Type 的浏览器直达请求。
     * 同样放行 well-known 元数据与 jwks 的裸 GET（部分客户端不带 Accept/Content-Type）。
     */
    private function isOauthAuthorizeGet(string $method, string $path, ?string $contentType): bool
    {
        if ($method !== 'GET') {
            return false;
        }
        if ($contentType !== null && strtolower(trim(explode(';', (string) $contentType, 2)[0])) !== '') {
            return false;
        }
        // browser/public 路由的 GET（授权页、发现端点、管理查询等）一律放行；
        // mcp/internal 路由仍维持原有 415 门槛
        $auth = $this->classificationRouter?->routeAuth($method, $path);
        if ($auth !== null) {
            return in_array($auth, ['browser', 'public'], true);
        }
        // 自定义分发器（测试）回退：白名单
        if (str_starts_with($path, '/mcp-api/.well-known/')) {
            return true;
        }
        return in_array($path, [
            '/mcp-api/oauth/authorize',
            '/mcp-api/oauth/jwks',
            '/mcp-api/oauth/login',
            '/mcp-api/client/regmode',
            '/mcp-api/client/pending',
        ], true);
    }

    private function parseBody(string $rawBody, ?string $contentType): ?array
    {
        if ($rawBody === '') {
            return [];
        }
        $mediaType = strtolower(trim(explode(';', (string) $contentType, 2)[0]));
        if ($mediaType === 'application/json' || $mediaType === '') {
            $request = json_decode($rawBody, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($request) ? $request : null;
        }
        return [];
    }

    private function parseMultipartBody(): ?array
    {
        $request = is_array($_POST ?? null) ? $_POST : [];
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || is_array($file['tmp_name'] ?? null)) {
            return null;
        }
        $request['file'] = [
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'name' => (string) ($file['name'] ?? ''),
            'type' => (string) ($file['type'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
            'error' => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
        ];
        return $request;
    }

    private static function requestPath(array $server): ?string
    {
        $requestUri = (string) ($server['REQUEST_URI'] ?? '');
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }
        $scriptName = (string) ($server['SCRIPT_NAME'] ?? '/index.php');
        if ($scriptName !== '' && str_starts_with($path, rtrim($scriptName, '/') . '/')) {
            $path = substr($path, strlen(rtrim($scriptName, '/')));
        }
        return $path === '' ? '/' : $path;
    }

    private function isBrowserClientMutation(string $method, string $path): bool
    {
        return $method === 'POST' && (
            $path === '/mcp-api/client'
            || in_array($path, ['/mcp-api/client/rotate', '/mcp-api/client/revoke'], true)
        );
    }

    private function isBrowserClientRequest(string $method, string $path): bool
    {
        return $method === 'GET' && $path === '/mcp-api/client'
            || $this->isBrowserClientMutation($method, $path);
    }

    private function productionDispatcher(): callable
    {
        $guard = new mcpapi_auth_guard(static function (string $token): array {
            return (new mcpapi_auth_token_verifier())->verify($token);
        });
        $router = new mcpapi_router($guard);
        $this->classificationRouter = $router;
        $this->browserRouteChecker = [$router, 'requiresBrowserAuth'];
        $this->browserMutationChecker = [$router, 'requiresBrowserCsrf'];
        return static function (string $method, string $path, string $authorization, string $requestId, array $request, ?string $contentType, array $headers = []) use ($router): mcpapi_http_response {
            return $router->dispatch($method, $path, $authorization, $requestId, $request, $contentType, $headers);
        };
    }
}
