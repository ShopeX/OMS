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

final class mcpapi_router
{
    private const ACTIONS = ['all', 'approve', 'create', 'delete', 'disable', 'expiration', 'register', 'handle', 'index', 'list', 'options', 'pending', 'regmode', 'revoke', 'rotate', 'show', 'update'];

    private mcpapi_auth_guard $guard;
    private array $controllerOverrides = [];
    private ?mcpapi_front_controller $frontController = null;
    private ?mcpapi_api_log_writer_contract $apiLogWriter = null;
    private ?mcpapi_agent_log_writer $agentLogWriter = null;

    public function __construct($guardOrApp, ...$controllers)
    {
        if ($guardOrApp instanceof app) {
            $this->frontController = new mcpapi_front_controller();
            return;
        }
        if (!$guardOrApp instanceof mcpapi_auth_guard) {
            throw new InvalidArgumentException('MCPAPI router requires an auth guard or app');
        }
        $this->guard = $guardOrApp;
        foreach ($controllers as $controller) {
            if ($controller instanceof mcpapi_api_log_writer_contract) {
                $this->apiLogWriter = $controller;
                continue;
            }
            if (is_object($controller)) {
                $class = get_class($controller);
                if (str_starts_with($class, 'mcpapi_ctl_')) {
                    $this->controllerOverrides[substr($class, 11)] = $controller;
                }
            }
        }
    }

    public function dispatch(
        string $methodOrPath,
        ?string $path = null,
        string $authorization = '',
        string $requestId = '',
        array $request = [],
        ?string $contentType = null,
        array $headers = []
    ): mcpapi_http_response {
        if ($this->frontController !== null) {
            return $this->dispatchFromApplicationRouter($methodOrPath);
        }
        $method = $methodOrPath;
        if ($path === null) {
            throw new InvalidArgumentException('MCPAPI dispatch requires a path');
        }
        $path = $this->normalizePath($path);
        if (!$this->validMcpContentType($contentType)) {
            return mcpapi_http_response::fromJson(415, ['error' => 'unsupported_media_type']);
        }

        if (!str_starts_with($path, '/mcp-api/')) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found']);
        }

        $route = $this->resolveRoute($method, $path);
        if ($route === null) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found']);
        }
        $startedAt = microtime(true);
        try {
            $invocation = $this->invoke($route, $authorization, $requestId, $request, $headers);
            $response = $invocation['response'];
            $context = $invocation['context'];
            $this->writeApiLog($route, $method, $path, $requestId, $request, $context, $response, $startedAt, $headers);
            return $response;
        } catch (mcpapi_auth_exception $exception) {
            $response = mcpapi_http_response::fromJson($exception->getCode() ?: 401, ['error' => 'unauthorized']);
            $this->writeApiLog($route, $method, $path, $requestId, $request, null, $response, $startedAt, $headers);
            return $response;
        } catch (\Throwable $exception) {
            $response = mcpapi_http_response::fromJson(500, ['error' => 'internal_error', 'message' => $exception->getMessage()]);
            $this->writeApiLog($route, $method, $path, $requestId, $request, null, $response, $startedAt, $headers);
            throw $exception;
        }
    }

    private function dispatchFromApplicationRouter(string $path): mcpapi_http_response
    {
        $server = $_SERVER;
        $server['REQUEST_URI'] = '/mcp-api/' . ltrim($path, '/');
        $response = $this->frontController->handle($server, file_get_contents('php://input') ?: '');
        http_response_code($response->status());
        foreach ($response->headers() as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $response->isHtml() ? $response->html() : $response->json();
        return $response;
    }

    public function requiresBrowserAuth(string $method, string $path): bool
    {
        $route = $this->resolveRoute($method, $path);
        return is_array($route) && $route['auth'] === 'browser';
    }

    public function requiresBrowserCsrf(string $method, string $path): bool
    {
        return strtoupper($method) === 'POST' && $this->requiresBrowserAuth($method, $path);
    }

    private function resolveRoute(string $method, string $path): ?array
    {
        $path = $this->normalizePath($path);
        $relativePath = substr($path, strlen('/mcp-api/'));
        if ($relativePath === '' || preg_match('/%2f|%5c/i', $relativePath) === 1 || str_contains($relativePath, '\\')) {
            return null;
        }

        $segments = explode('/', $relativePath);
        if (in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            return null;
        }

        $fullKey = implode('_', $segments);
        if ($fullKey === 'v1_jwks' || $fullKey === 'v1_internal_exchange') {
            return null;
        }
        // RFC 8414 元数据路径：.well-known/oauth-authorization-server → oauth/authorization/server 控制器
        if (count($segments) === 2 && $segments[0] === '.well-known') {
            if ($segments[1] === 'oauth-authorization-server') {
                $segments = ['oauth', 'authorization', 'server'];
                $fullKey = 'oauth_authorization_server';
            } else {
                return null;
            }
        }
        $controller = $this->resolveController($fullKey);
        $action = null;
        if ($controller === null && count($segments) > 1) {
            $action = array_pop($segments);
            $controller = $this->resolveController(implode('_', $segments));
        }
        if ($controller === null) {
            return null;
        }

        $controllerKey = implode('_', $segments);
        if ($action === null && !$this->defaultMethodAllowed($controllerKey, $method)) {
            return null;
        }
        $callable = $action ?? $this->defaultAction($controller, $method);
        if (!$this->validAction($controllerKey, $callable, $method, $action !== null)) {
            return null;
        }
        if ($callable === '' || !method_exists($controller, $callable)) {
            return null;
        }
        $reflection = new ReflectionMethod($controller, $callable);
        if (!$reflection->isPublic() || $reflection->isStatic()) {
            return null;
        }
        $auth = $this->authForController($controllerKey);
        if ($auth === null || !$this->supportsParameters($reflection, $auth)) {
            return null;
        }

        return [
            'controller' => $controller,
            'controllerKey' => $controllerKey,
            'action' => $callable,
            'auth' => $auth,
        ];
    }

    /**
     * 供 front controller 做 GET 准入分类：返回路由认证类型（browser/public/mcp/internal）或 null
     */
    public function routeAuth(string $method, string $path): ?string
    {
        $route = $this->resolveRoute($method, $path);
        return is_array($route) ? ($route['auth'] ?? null) : null;
    }

    private function resolveController(string $key): ?object
    {
        if (!preg_match('/^[a-z0-9_]+$/', $key)) {
            return null;
        }
        if (isset($this->controllerOverrides[$key])) {
            return $this->controllerOverrides[$key];
        }

        $class = 'mcpapi_ctl_' . $key;
        $file = ROOT_DIR . '/app/mcpapi/controller/' . str_replace('_', '/', $key) . '.php';
        if (!file_exists($file)) {
            return null;
        }
        if (!class_exists($class)) {
            return null;
        }

        return new $class();
    }

    private function invoke(array $route, string $authorization, string $requestId, array $request, array $headers = []): array
    {
        $controller = $route['controller'];
        $arguments = [];
        $context = null;
        if ($route['auth'] === 'mcp') {
            // Client-Mode 决定鉴权方案：bearer → OAuth access token；jwt → 内嵌助手 JWT；secret/缺省 → Basic
            $clientMode = strtolower($this->headerValue($headers, 'X-Shopex-OMS-Client-Mode', 'HTTP_X_SHOPEX_OMS_CLIENT_MODE'));
            if ($clientMode === 'bearer') {
                if (!preg_match('/^Bearer\s+\S+/i', $authorization)) {
                    throw new mcpapi_auth_exception('Bearer mode requires Authorization Bearer token', 401);
                }
                $context = $this->guard->authenticateBearer($authorization, $requestId);
            } elseif ($clientMode === 'jwt') {
                $clientId = $this->headerValue($headers, 'X-Shopex-OMS-Client-Id', 'HTTP_X_SHOPEX_OMS_CLIENT_ID');
                $clientSecretHeader = $this->headerValue($headers, 'X-Shopex-OMS-Client-Secret', 'HTTP_X_SHOPEX_OMS_CLIENT_SECRET');
                $bearer = '';
                if (preg_match('/^Bearer\s+\S+/i', $authorization)) {
                    $bearer = $authorization;
                } elseif ($clientSecretHeader !== '') {
                    $bearer = preg_match('/^Bearer\s+/i', $clientSecretHeader)
                        ? $clientSecretHeader
                        : ('Bearer ' . $clientSecretHeader);
                }
                if ($clientId === '' || $bearer === '') {
                    throw new mcpapi_auth_exception('JWT mode requires Client-Id and Bearer token', 401);
                }
                $context = $this->guard->authenticateBrowserJwt($clientId, $bearer, $requestId);
            } else {
                // secret/缺省：优先 Authorization Basic；否则用 Client-Id + Client-Secret 组装 Basic
                $basicAuthorization = $authorization;
                if (!preg_match('/^Basic\s+\S+/i', $basicAuthorization)) {
                    $clientId = $this->headerValue($headers, 'X-Shopex-OMS-Client-Id', 'HTTP_X_SHOPEX_OMS_CLIENT_ID');
                    $clientSecret = $this->headerValue($headers, 'X-Shopex-OMS-Client-Secret', 'HTTP_X_SHOPEX_OMS_CLIENT_SECRET');
                    if ($clientId !== '' && $clientSecret !== '') {
                        $basicAuthorization = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);
                    }
                }
                $context = $this->guard->authenticateBasic($basicAuthorization, $requestId);
            }
        } elseif ($route['auth'] === 'internal') {
            $context = $this->guard->authenticateInternal($authorization, $requestId);
        } elseif ($route['auth'] === 'browser'
            && $route['controllerKey'] !== 'oauth_authorize'
            && (!class_exists('desktop_auth_context') || !desktop_auth_context::isAuthenticated())) {
            // oauth_authorize 例外：未登录时放行到控制器，由其 302 到 SPA 登录页（next 回跳）
            throw new mcpapi_auth_exception('Browser authentication required', 401);
        }

        $reflection = new ReflectionMethod($controller, $route['action']);
        if ($route['auth'] === 'mcp' && $context instanceof mcpapi_auth_context) {
            $permissionResponse = $this->authorizeMcpMethod($route['controllerKey'], $reflection, $context);
            if ($permissionResponse !== null) {
                return ['response' => $permissionResponse, 'context' => $context];
            }
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
            if ($context !== null && $typeName === get_class($context)) {
                $arguments[] = $context;
            } elseif ($typeName === 'array') {
                $arguments[] = $request;
            } elseif (!$parameter->isOptional()) {
                throw new RuntimeException('Unsupported controller argument', 500);
            }
        }

        return ['response' => $controller->{$route['action']}(...$arguments), 'context' => $context];
    }

    private function headerValue(array $headers, string $name, string $serverKey = ''): string
    {
        if (isset($headers[$name]) && is_string($headers[$name])) {
            return trim($headers[$name]);
        }
        $lower = strtolower($name);
        foreach ($headers as $key => $value) {
            if (is_string($key) && strtolower($key) === $lower && is_string($value)) {
                return trim($value);
            }
        }
        if ($serverKey !== '' && isset($headers[$serverKey]) && is_string($headers[$serverKey])) {
            return trim($headers[$serverKey]);
        }
        return '';
    }

    private function writeApiLog(
        array $route,
        string $method,
        string $path,
        string $requestId,
        array $request,
        ?mcpapi_auth_context $context,
        mcpapi_http_response $response,
        float $startedAt,
        array $headers = []
    ): void {
        if ($route['auth'] !== 'mcp' || $route['controllerKey'] === 'v1_health') {
            return;
        }

        $writer = $this->apiLogWriter ?? new mcpapi_api_log_writer();
        if (!$writer instanceof mcpapi_api_log_writer_contract) {
            return;
        }

        $writer->write([
            'request_id' => $requestId,
            'path' => $path,
            'method' => strtoupper($method),
            'controller' => $route['controllerKey'],
            'action' => $route['action'],
            'client_id' => $context?->clientId() ?? '',
            'subject_id' => $context?->subject() ?? '',
            'request' => $request,
            'response_status' => $response->status(),
            'response_body' => $response->body(),
            'duration' => microtime(true) - $startedAt,
            'message' => (string) ($response->body()['message'] ?? $response->body()['msg'] ?? $response->body()['error'] ?? ''),
            'status' => $response->status() >= 200 && $response->status() < 300 ? 'success' : 'fail',
        ]);

        // Agent 审计日志（专表）：agent/模型/工具/操作员/参数（脱敏）。失败不阻断业务。
        try {
            $agentWriter = $this->agentLogWriter ?? new mcpapi_agent_log_writer();
            $headerValue = static function (string $name) use ($headers): string {
                $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
                return isset($headers[$key]) && is_string($headers[$key]) ? trim($headers[$key]) : '';
            };
            $agentWriter->write([
                'request_id' => $requestId,
                'session_id' => $headerValue('Mcp-Session-Id'),
                'client_id' => $context?->clientId() ?? '',
                'client_mode' => $headerValue('X-Shopex-OMS-Client-Mode'),
                'agent_name' => $headerValue('X-MCP-Agent-Name'),
                'agent_version' => $headerValue('X-MCP-Agent-Version'),
                'model' => $headerValue('X-MCP-Model'),
                'user_id' => $context?->omsSubjectId() ?? 0,
                'tenant_id' => $context?->tenantId() ?? '',
                'tool_name' => $headerValue('X-MCP-Tool-Name'),
                'trace_id' => $headerValue('X-MCP-Trace-Id'),
                'confirm_used' => $headerValue('X-MCP-Confirm-Used') !== '',
                'path' => $path,
                'method' => strtoupper($method),
                'write_flag' => strtoupper($method) === 'POST',
                'success' => $response->status() >= 200 && $response->status() < 300,
                'error_code' => (string) ($response->body()['error'] ?? ''),
                'http_status' => $response->status(),
                'params' => $request,
                'response_digest' => $response->body(),
                'spendtime_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        } catch (Throwable $e) {
            // 审计写失败不阻断
        }
    }

    private function authorizeMcpMethod(
        string $controllerKey,
        ReflectionMethod $reflection,
        mcpapi_auth_context $context
    ): ?mcpapi_http_response {
        if ($controllerKey === 'v1_health') {
            return null;
        }

        $attributes = $reflection->getAttributes(\app\desktop\lib\annotation\RequirePermission::class);
        if (count($attributes) !== 1) {
            return mcpapi_http_response::fromJson(403, ['error' => 'permission_denied']);
        }

        $requiredPermissions = $this->requiredPermissionsFromAttribute($attributes[0]);
        if ($requiredPermissions === []) {
            return mcpapi_http_response::fromJson(403, ['error' => 'permission_denied']);
        }

        if (in_array(null, $requiredPermissions, true)) {
            return mcpapi_http_response::fromJson(403, ['error' => 'permission_denied']);
        }

        foreach ($requiredPermissions as $permission) {
            if (!$context->hasPermission($permission)) {
                return mcpapi_http_response::fromJson(403, ['error' => 'permission_denied']);
            }
        }

        return null;
    }

    private function requiredPermissionsFromAttribute(ReflectionAttribute $attribute): array
    {
        try {
            $arguments = $attribute->getArguments();
        } catch (\Throwable) {
            return [];
        }

        $permission = null;
        $all = [];

        if (array_key_exists(0, $arguments)) {
            $permission = $arguments[0];
        }
        if (array_key_exists('permission', $arguments)) {
            $permission = $arguments['permission'];
        }
        if (array_key_exists(1, $arguments) && is_array($arguments[1])) {
            $all = $arguments[1];
        }
        if (array_key_exists('all', $arguments) && is_array($arguments['all'])) {
            $all = $arguments['all'];
        }

        if ($permission !== null && !is_string($permission)) {
            return [];
        }
        if ($permission === '') {
            return [];
        }
        if ($all !== []) {
            if ($permission !== null) {
                return [];
            }
            foreach ($all as $entry) {
                if (!is_string($entry) || $entry === '') {
                    return [];
                }
            }
            return array_values($all);
        }

        return $permission === null ? [] : [$permission];
    }

    private function defaultAction(object $controller, string $method): string
    {
        if (method_exists($controller, 'handle')) {
            return 'handle';
        }
        return $method === 'GET' ? 'index' : ($method === 'POST' ? 'create' : '');
    }

    private function defaultMethodAllowed(string $key, string $method): bool
    {
        if ($key === 'client_approve' || $key === 'client_disable' || $key === 'client_register') {
            return $method === 'POST';
        }
        if ($key === 'client_all' || $key === 'client_pending') {
            return $method === 'GET';
        }
        if ($key === 'client_reject') {
            return $method === 'POST';
        }
        if ($key === 'client_regmode') {
            return in_array($method, ['GET', 'POST'], true);
        }
        if ($key === 'client') {
            return in_array($method, ['GET', 'POST'], true);
        }
        if ($key === 'v1_internal' || str_starts_with($key, 'v1_internal_')) {
            return $method === 'POST';
        }
        if ($key === 'oauth_authorize') {
            // GET = 同意页/直接签发；POST = 同意/拒绝表单
            return in_array($method, ['GET', 'POST'], true);
        }
        if ($key === 'oauth_login') {
            // GET = 登录页；POST = 登录表单提交
            return in_array($method, ['GET', 'POST'], true);
        }
        if ($key === 'oauth_token' || $key === 'oauth_register') {
            return $method === 'POST';
        }
        if ($key === 'oauth_jwks' || $key === 'oauth_authorization_server') {
            return $method === 'GET';
        }
        if ($key === 'oauth_consents') {
            return $method === 'GET';
        }
        if ($key === 'oauth_consents_revoke') {
            return $method === 'POST';
        }
        if ($key === 'v1_aftersale_statistics') {
            return false;
        }
        if ($key === 'v1' || str_starts_with($key, 'v1_')) {
            if ($key === 'v1_health') {
                return $method === 'GET';
            }
            return in_array($method, ['GET', 'POST'], true);
        }
        return $method === 'GET';
    }

    private function validAction(string $controllerKey, string $action, string $method, bool $explicit): bool
    {
        if ($explicit && $this->isSupportedDomainAction($controllerKey, $action, $method)) {
            return true;
        }
        if ($action === '' || !in_array($action, self::ACTIONS, true)) {
            return false;
        }
        if ($method === 'GET') {
            return in_array($action, $explicit ? ['all', 'list', 'show', 'pending', 'regmode'] : ['handle', 'index'], true);
        }
        return $method === 'POST'
            && in_array($action, $explicit ? ['approve', 'create', 'delete', 'disable', 'expiration', 'list', 'regmode', 'register', 'revoke', 'rotate', 'show', 'update'] : ['create', 'handle'], true);
    }

    private function isSupportedDomainAction(string $controllerKey, string $action, string $method): bool
    {
        if ($method !== 'POST') {
            return false;
        }

        $allowed = match ($controllerKey) {
            'v1_order_type' => ['getOrganizations', 'getBranches', 'getAutoconfirmRules', 'getAutodispatchRules', 'options'],
            'v1_autobranchset' => ['getBranches'],
            'v1_logistics_rule' => ['getBranches', 'getCorpList', 'getShops', 'checkRuleName'],
            'v1_autoconfirm' => ['orderTypes', 'setStatus', 'setDefault', 'removeDefault'],
            'v1_autodispatch' => ['orderTypes', 'orderTypeDetail', 'setStatus', 'setDefault', 'removeDefault'],
            'v1_material_cat' => ['sort'],
            'v1_material_basic' => ['importTemplate'],
            'v1_material_sales' => ['importTemplate'],
            'v1_purchase_order' => ['importTemplate', 'import'],
            'v1_inventory_diff' => ['overview', 'detail', 'trace', 'export'],
            'v1_branch' => ['getWmsList', 'getBranchTypes', 'getShopList', 'getCorpList'],
            'v1_sales_detail_statistics' => ['index'],
            'v1_aftersale_statistics' => ['index'],
            'v1_purchase_replenishment' => ['recommend'],
            'v1_automation_rule' => ['explain'],
            default => [],
        };

        return in_array($action, $allowed, true);
    }

    private function authForController(string $key): ?string
    {
        if ($key === 'client' || str_starts_with($key, 'client_')) {
            return 'browser';
        }
        if ($key === 'agentlog') {
            return 'browser';
        }
        if ($key === 'v1_internal' || str_starts_with($key, 'v1_internal_')) {
            return 'internal';
        }
        if ($key === 'v1_jwks') {
            return 'public';
        }
        // OAuth 端点：authorize/login/consents 走 browser（desktop 会话 + CSRF），token/register/jwks/metadata/login 公开
        if ($key === 'oauth_authorize' || $key === 'oauth_consents' || $key === 'oauth_consents_revoke') {
            return 'browser';
        }
        if ($key === 'oauth_token' || $key === 'oauth_register' || $key === 'oauth_jwks'
            || $key === 'oauth_authorization_server' || $key === 'oauth_login') {
            return 'public';
        }
        if ($key === 'v1' || str_starts_with($key, 'v1_')) {
            return 'mcp';
        }
        return null;
    }

    private function supportsParameters(ReflectionMethod $reflection, string $auth): bool
    {
        $contextType = $auth === 'mcp'
            ? mcpapi_auth_context::class
            : ($auth === 'internal' ? mcpapi_gateway_identity::class : null);
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType
                || !in_array($type->getName(), array_filter([$contextType, 'array']), true)) {
                return false;
            }
        }
        return true;
    }

    private function validMcpContentType(?string $contentType): bool
    {
        return $contentType === null
            || in_array(strtolower(trim(explode(';', $contentType, 2)[0])), ['application/json', 'multipart/form-data'], true)
            // OAuth authorize 授权确认表单（browser 原生表单提交）
            || strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/x-www-form-urlencoded';
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/index.php/mcp-api/')) {
            return substr($path, strlen('/index.php'));
        }
        return $path;
    }
}
