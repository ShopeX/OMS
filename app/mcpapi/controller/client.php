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

final class mcpapi_ctl_client
{
    private mcpapi_auth_client_manager $manager;
    private mcpapi_auth_owner $ownerResolver;
    private $permissionChecker;

    public function __construct(
        $managerOrApp = null,
        ?callable $permissionChecker = null,
        ?mcpapi_auth_owner $ownerResolver = null
    )
    {
        // 兼容两种实例化：desktop 框架传 app 对象；mcp-api/测试传 manager
        $manager = $managerOrApp instanceof mcpapi_auth_client_manager ? $managerOrApp : null;
        $this->manager = $manager ?? new mcpapi_auth_client_manager();
        $this->ownerResolver = $ownerResolver ?? new mcpapi_auth_owner();
        $this->permissionChecker = $permissionChecker ?? static function (string $permission): bool {
            return (bool) kernel::single('desktop_user')->has_permission($permission);
        };
    }

    public function index(): mcpapi_http_response
    {
        return $this->runAction(function (int $ownerId): mcpapi_http_response {
            $this->authorize('mcpapi.client.list');
            return mcpapi_http_response::fromJson(200, ['clients' => $this->manager->listForOwner($ownerId)]);
        });
    }

    public function create(array $request = []): mcpapi_http_response
    {
        return $this->runAction(function (int $ownerId) use ($request): mcpapi_http_response {
            $this->authorize('mcpapi.client.add');
            $this->authorizeOwnerMcp($ownerId);
            $result = $this->manager->createForOwner(
                $ownerId,
                (string) ($request['name'] ?? ''),
                $this->expirationValue($request)
            );
            return mcpapi_http_response::fromJson(201, $this->secretResponse($result), ['Cache-Control' => 'no-store']);
        });
    }

    public function rotate(array $request = []): mcpapi_http_response
    {
        unset($request);
        return $this->runAction(function (int $ownerId): mcpapi_http_response {
            $this->authorize('mcpapi.client.rotate');
            $this->authorizeOwnerMcp($ownerId);
            $result = $this->manager->rotateForOwner($ownerId, '');
            return mcpapi_http_response::fromJson(200, $this->secretResponse($result), ['Cache-Control' => 'no-store']);
        });
    }

    public function revoke(array $request = []): mcpapi_http_response
    {
        return $this->runAction(function (int $ownerId) use ($request): mcpapi_http_response {
            $this->authorize('mcpapi.client.revoke');
            $result = $this->manager->revokeForOwner($ownerId, '', (string) ($request['reason'] ?? ''));
            return mcpapi_http_response::fromJson(200, ['client' => $result]);
        });
    }

    public function expiration(array $request = []): mcpapi_http_response
    {
        return $this->runAction(function (int $ownerId) use ($request): mcpapi_http_response {
            $this->authorize('mcpapi.client.expiration');
            $result = $this->manager->updateExpirationForOwner($ownerId, '', $this->expirationValue($request));
            return mcpapi_http_response::fromJson(200, ['client' => $result]);
        });
    }

    /**
     * 管理端静态注册机密客户端（性质分流，权限点 mcpapi.client.add）。
     * kind=operator：回调必填，owner=0；kind=agent：绑定/顺带创建服务账号，回调恒为空。
     * 响应含一次性明文密钥（复用 secretResponse 交接形态）。
     */
    public function register(array $request = []): mcpapi_http_response
    {
        return $this->runAction(function (int $ownerId) use ($request): mcpapi_http_response {
            $this->authorize('mcpapi.client.add');
            $this->authorizeOwnerMcp($ownerId);
            $operator = (string) (desktop_auth_context::loginName() ?: ('user#' . $ownerId));

            $kind = (string) ($request['kind'] ?? 'operator');
            $name = (string) ($request['name'] ?? '');
            $redirectUris = is_array($request['redirect_uris'] ?? null)
                ? array_values(array_filter($request['redirect_uris'], 'is_string'))
                : [];
            $scopes = is_array($request['scopes'] ?? null)
                ? array_values(array_filter($request['scopes'], 'is_string'))
                : [];
            $serviceUserId = (int) ($request['service_user_id'] ?? 0) ?: null;
            $newSvc = is_array($request['new_service_account'] ?? null) ? $request['new_service_account'] : null;
            $expiresAt = isset($request['expires_at']) && (int) $request['expires_at'] > 0 ? (int) $request['expires_at'] : null;

            $result = $this->manager->registerConfidential(
                $kind,
                $name,
                $redirectUris,
                $scopes,
                $operator,
                $serviceUserId,
                $newSvc,
                $expiresAt
            );

            $payload = [
                'client' => $result['client'],
                'client_secret' => $result['client_secret'], // 一次性交接
            ];
            if (isset($result['service_account'])) {
                $payload['service_account'] = $result['service_account'];
            }
            return mcpapi_http_response::fromJson(201, $payload, ['Cache-Control' => 'no-store']);
        });
    }

    /**
     * 审批激活待审批的动态注册客户端（权限点 mcpapi.client.approve + 审批人自身 mcp_enabled）。
     * 审批操作员写入 updated_by，可追溯。
     */
    public function approve(array $request = []): mcpapi_http_response
    {
        $ownerId = desktop_auth_context::userId();
        if ($ownerId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }
        try {
            $this->authorize('mcpapi.client.approve');
            $clientId = (string) ($request['client_id'] ?? '');
            if ($clientId === '') {
                throw new InvalidArgumentException('client_id required', 422);
            }
            $operator = (string) (desktop_auth_context::loginName() ?: ('user#' . $ownerId));
            $client = $this->manager->approvePendingClient($clientId, $operator);
            return mcpapi_http_response::fromJson(200, ['client' => $client]);
        } catch (mcpapi_auth_exception $exception) {
            return mcpapi_http_response::fromJson($exception->getCode(), [
                'error' => $exception->getCode() === 403 ? 'forbidden' : 'not_found',
                'error_description' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 全部客户端列表（管理员视角，含动态注册的外部应用）。
     */
    public function all(): mcpapi_http_response
    {
        $ownerId = desktop_auth_context::userId();
        if ($ownerId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }
        try {
            $this->authorize('mcpapi.client.list');
            return mcpapi_http_response::fromJson(200, ['clients' => $this->manager->listAllClients()]);
        } catch (mcpapi_auth_exception $exception) {
            return mcpapi_http_response::fromJson($exception->getCode(), ['error' => 'forbidden']);
        }
    }

    /**
     * 管理员吊销任意客户端（权限点 mcpapi.client.revoke）。
     */
    public function disable(array $request = []): mcpapi_http_response
    {
        $ownerId = desktop_auth_context::userId();
        if ($ownerId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }
        try {
            $this->authorize('mcpapi.client.revoke');
            $clientId = (string) ($request['client_id'] ?? '');
            if ($clientId === '') {
                throw new InvalidArgumentException('client_id required', 422);
            }
            $operator = (string) (desktop_auth_context::loginName() ?: ('user#' . $ownerId));
            $client = $this->manager->revokeById($clientId, $operator, (string) ($request['reason'] ?? ''));
            return mcpapi_http_response::fromJson(200, ['client' => $client]);
        } catch (mcpapi_auth_exception $exception) {
            return mcpapi_http_response::fromJson($exception->getCode(), [
                'error' => $exception->getCode() === 403 ? 'forbidden' : 'not_found',
                'error_description' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 待审批的动态注册客户端列表（管理员视角，全局）。
     */
    public function pending(): mcpapi_http_response
    {
        $ownerId = desktop_auth_context::userId();
        if ($ownerId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }
        try {
            $this->authorize('mcpapi.client.approve');
            return mcpapi_http_response::fromJson(200, ['clients' => $this->manager->listPendingClients()]);
        } catch (mcpapi_auth_exception $exception) {
            return mcpapi_http_response::fromJson($exception->getCode(), ['error' => 'forbidden']);
        }
    }

    /**
     * 拒绝待审批客户端（吊销语义，记录审批操作员）。
     */
    public function reject(array $request = []): mcpapi_http_response
    {
        $ownerId = desktop_auth_context::userId();
        if ($ownerId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }
        try {
            $this->authorize('mcpapi.client.approve');
            $clientId = (string) ($request['client_id'] ?? '');
            if ($clientId === '') {
                throw new InvalidArgumentException('client_id required', 422);
            }
            $operator = (string) (desktop_auth_context::loginName() ?: ('user#' . $ownerId));
            $this->manager->rejectPendingClient($clientId, $operator, (string) ($request['reason'] ?? ''));
            return mcpapi_http_response::fromJson(200, ['ok' => true, 'client_id' => $clientId]);
        } catch (mcpapi_auth_exception $exception) {
            return mcpapi_http_response::fromJson($exception->getCode(), [
                'error' => $exception->getCode() === 403 ? 'forbidden' : 'not_found',
                'error_description' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 动态注册治理模式：GET 查看 / POST 设置（open|approval|closed）。
     */
    public function regmode(array $request = []): mcpapi_http_response
    {
        $ownerId = desktop_auth_context::userId();
        if ($ownerId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }
        try {
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'POST') {
                $this->authorize('mcpapi.client.approve');
                $mode = (string) ($request['mode'] ?? '');
                mcpapi_auth_oauth_registration::setMode($mode);
            } else {
                $this->authorize('mcpapi.client.list');
            }
            return mcpapi_http_response::fromJson(200, ['mode' => mcpapi_auth_oauth_registration::currentMode()]);
        } catch (mcpapi_auth_exception $exception) {
            return mcpapi_http_response::fromJson($exception->getCode(), ['error' => 'forbidden']);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'invalid_request']);
        }
    }

    private function runAction(callable $action): mcpapi_http_response
    {
        $ownerId = desktop_auth_context::userId();
        if ($ownerId < 1) {
            return mcpapi_http_response::fromJson(401, ['error' => 'unauthorized']);
        }

        try {
            return $action($ownerId);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson($exception->getCode() ?: 422, ['error' => 'invalid_request']);
        } catch (mcpapi_auth_exception $exception) {
            return mcpapi_http_response::fromJson($exception->getCode(), ['error' => $exception->getCode() === 403 ? 'forbidden' : 'not_found']);
        } catch (RuntimeException $exception) {
            return mcpapi_http_response::fromJson($exception->getCode() ?: 500, ['error' => 'server_error']);
        }
    }

    private function authorize(string $permission): void
    {
        if (!(bool) ($this->permissionChecker)($permission)) {
            throw new mcpapi_auth_exception('Forbidden', 403);
        }
    }

    private function authorizeOwnerMcp(int $ownerId): void
    {
        $this->ownerResolver->resolve($ownerId);
    }

    private function expirationValue(array $request): ?int
    {
        if (!array_key_exists('expires_at', $request) || $request['expires_at'] === null || $request['expires_at'] === '') {
            return null;
        }

        if (is_int($request['expires_at'])) {
            return $request['expires_at'];
        }
        if (is_string($request['expires_at']) && ctype_digit($request['expires_at'])) {
            return (int) $request['expires_at'];
        }

        throw new InvalidArgumentException('Invalid expiration', 422);
    }

    private function secretResponse(array $result): array
    {
        $clientSecret = $result['client_secret'];
        unset($result['client_secret']);
        return ['client' => $result, 'client_secret' => $clientSecret];
    }
}
