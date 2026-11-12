<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_user
{
    private object $capability;
    private ?object $mutation;
    private $loginExists;
    private $targetVisibility;

    public function __construct(
        ?object $capability = null,
        ?object $desktopUser = null,
        ?object $mutation = null,
        ?callable $loginExists = null,
        ?callable $targetVisibility = null
    )
    {
        $this->capability = $capability ?? new desktop_capability_user_read();
        $this->mutation = $mutation;
        $this->loginExists = $loginExists ?? static function (string $loginName): bool {
            $accountType = pam_account::get_account_type('desktop');
            $rows = app::get('pam')->model('account')->getList(
                'account_id',
                ['login_name' => $loginName, 'account_type' => $accountType],
                0,
                1
            );
            return $rows !== [] && (int) ($rows[0]['account_id'] ?? 0) > 0;
        };
        $this->targetVisibility = $targetVisibility ?? static function (int $userId, bool $isSuper, array $scope): ?bool {
            $user = app::get('desktop')->model('users')->dump($userId, 'user_id,super');
            if (!is_array($user) || $user === []) {
                return null;
            }
            if ($isSuper) {
                return true;
            }
            $rows = app::get('ome')->model('operation_ops')->getList('org_id', ['op_id' => $userId], 0, -1);
            $targetScope = array_values(array_unique(array_filter(array_map(
                'intval',
                array_column((array) $rows, 'org_id')
            ), static fn(int $id): bool => $id > 0)));
            return array_intersect($targetScope, $scope) !== [];
        };
    }

    #[RequirePermission('users')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, [
                'data' => $this->capability->list($request, $context->isSuper(), $this->organizationScope($context)),
            ]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'internal_error']);
        }
    }

    #[RequirePermission('operator_edit')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $user = $this->capability->show(
                (int) ($request['user_id'] ?? 0),
                $context->isSuper(),
                $this->organizationScope($context)
            );
            return $user === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['data' => ['user' => $user]]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'internal_error']);
        }
    }

    #[RequirePermission('operator_add')]
    public function create(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $authority = $this->establishMutationSubject($context);
            $payload = $this->normalizeCreateRequest($request);
            if (($this->loginExists)($payload['pam_account']['login_name'])) {
                return mcpapi_http_response::fromJson(409, ['error' => 'DUPLICATE_LOGIN']);
            }
            if (array_key_exists('org_id', $payload)) {
                $this->assertScope((array) $payload['org_id'], $authority);
            }
            return $this->mutationResponse($this->mutationCapability()->create($payload, $authority));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, [
                'error' => 'VALIDATION_FAILED',
                'message' => $exception->getMessage(),
            ]);
        } catch (RuntimeException $exception) {
            return $this->runtimeErrorResponse($exception);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR']);
        }
    }

    #[RequirePermission('operator_edit')]
    public function update(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $authority = $this->establishMutationSubject($context);
            $userId = (int) ($request['user_id'] ?? 0);
            if ($userId < 1 || !isset($request['expected_state_fingerprint']) || !is_string($request['expected_state_fingerprint'])) {
                throw new InvalidArgumentException('user_id 和 expected_state_fingerprint 为必填项');
            }
            $visibility = ($this->targetVisibility)($userId, $context->isSuper(), $authority['scope_org_ids']);
            if ($visibility === null) {
                return mcpapi_http_response::fromJson(404, ['error' => 'NOT_FOUND']);
            }
            if ($visibility === false) {
                return mcpapi_http_response::fromJson(403, ['error' => 'FORBIDDEN']);
            }
            $current = $this->capability->show($userId, $context->isSuper(), $authority['scope_org_ids']);
            if ($current === null) {
                return mcpapi_http_response::fromJson(404, ['error' => 'NOT_FOUND']);
            }
            if (!hash_equals((string) ($current['state_fingerprint'] ?? ''), $request['expected_state_fingerprint'])) {
                return mcpapi_http_response::fromJson(409, ['error' => 'STALE_STATE']);
            }
            $payload = $this->normalizeUpdateRequest($request);
            if (array_key_exists('org_id', $payload)) {
                $this->assertScope($payload['org_id'], $authority);
            }
            return $this->mutationResponse($this->mutationCapability()->update($userId, $payload, $authority));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, [
                'error' => 'VALIDATION_FAILED',
                'message' => $exception->getMessage(),
            ]);
        } catch (RuntimeException $exception) {
            return $this->runtimeErrorResponse($exception);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR']);
        }
    }

    #[RequirePermission('operator_edit')]
    public function changePassword(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $result = (new desktop_capability_user_password())->change($request, $context->omsSubjectId());
            return mcpapi_http_response::fromJson(200, ['data' => $result]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'VALIDATION_FAILED', 'message' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR', 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR']);
        }
    }

    private function organizationScope(mcpapi_auth_context $context): array
    {
        if ($context->isSuper()) {
            return [];
        }
        $subjectId = $context->omsSubjectId();
        if ($subjectId < 1) {
            return [];
        }
        $rows = app::get('ome')->model('operation_ops')->getList('org_id', ['op_id' => $subjectId], 0, -1);
        return array_values(array_unique(array_filter(array_map(
            'intval',
            array_column((array) $rows, 'org_id')
        ), static fn(int $id): bool => $id > 0)));
    }

    private function establishMutationSubject(mcpapi_auth_context $context): array
    {
        return [
            'subject_id' => $context->omsSubjectId(),
            'is_super' => $context->isSuper(),
            'permissions' => array_values(array_filter($context->omsPermissions(), 'is_string')),
            'scope_org_ids' => $this->organizationScope($context),
        ];
    }

    private function normalizeCreateRequest(array $request): array
    {
        $this->assertAllowedKeys($request, [
            'login_name', 'password', 'name', 'mobile', 'email', 'op_no', 'memo', 'role_ids',
            'org_id', 'branch', 'confirm_group', 'store_org_ids', 'dealer_shop_ids',
            'store_org_conf', 'dealer_shop_conf',
        ]);
        $loginName = trim((string) ($request['login_name'] ?? ''));
        $password = (string) ($request['password'] ?? '');
        if ($loginName === '' || $password === '') {
            throw new InvalidArgumentException('login_name 和 password 为必填项');
        }
        $payload = $request;
        unset($payload['login_name'], $payload['password']);
        $payload['role'] = $payload['role_ids'] ?? [];
        unset($payload['role_ids']);
        $this->mapRelationAliases($payload);
        $payload['pam_account'] = ['login_name' => $loginName, 'login_password' => $password];
        return $payload;
    }

    private function normalizeUpdateRequest(array $request): array
    {
        $this->assertAllowedKeys($request, [
            'user_id', 'expected_state_fingerprint', 'name', 'mobile', 'email', 'op_no', 'memo',
            'status', 'mcp_enabled', 'role_ids', 'org_id', 'branch', 'confirm_group',
            'store_org_ids', 'dealer_shop_ids', 'store_org_conf', 'dealer_shop_conf',
        ]);
        $payload = $request;
        unset($payload['user_id'], $payload['expected_state_fingerprint']);
        if (array_key_exists('role_ids', $payload)) {
            $payload['role'] = $payload['role_ids'];
            unset($payload['role_ids']);
        }
        $this->mapRelationAliases($payload);
        return $payload;
    }

    private function mapRelationAliases(array &$payload): void
    {
        if (array_key_exists('store_org_ids', $payload)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $payload['store_org_ids']), static fn(int $id): bool => $id > 0)));
            $payload['store_org_conf'] = ['orgIds' => $ids];
            unset($payload['store_org_ids']);
        }
        if (array_key_exists('dealer_shop_ids', $payload)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $payload['dealer_shop_ids']), static fn(int $id): bool => $id > 0)));
            $payload['dealer_shop_conf'] = ['cosIds' => $ids];
            unset($payload['dealer_shop_ids']);
        }
        if (array_key_exists('org_id', $payload)) {
            $payload['org_id'] = array_values(array_unique(array_filter(array_map('intval', (array) $payload['org_id']), static fn(int $id): bool => $id > 0)));
            if ($payload['org_id'] === []) {
                unset($payload['org_id']);
            }
        }
    }

    private function assertAllowedKeys(array $request, array $allowed): void
    {
        $unknown = array_diff(array_keys($request), $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException('请求包含不支持的字段');
        }
    }

    private function assertScope(array $orgIds, array $authority): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orgIds), static fn(int $id): bool => $id > 0)));
        if (($authority['is_super'] ?? false) === true) {
            return;
        }
        if ($ids === [] || array_diff($ids, (array) ($authority['scope_org_ids'] ?? [])) !== []) {
            throw new RuntimeException('scope denied');
        }
    }

    private function mutationResponse(array $result): mcpapi_http_response
    {
        $status = (string) ($result['status'] ?? 'rejected');
        if ($status === 'committed') {
            return mcpapi_http_response::fromJson(200, ['data' => $result]);
        }
        if ($status === 'partial') {
            return mcpapi_http_response::fromJson(409, ['error' => 'PARTIAL_WRITE', 'data' => $result]);
        }
        if (in_array($status, ['unknown', 'ambiguous'], true)) {
            return mcpapi_http_response::fromJson(503, ['error' => 'WRITE_AMBIGUOUS']);
        }
        return mcpapi_http_response::fromJson(409, ['error' => 'WRITE_REJECTED']);
    }

    private function mutationCapability(): object
    {
        return $this->mutation ??= new desktop_capability_user_mutation();
    }

    private function runtimeErrorResponse(RuntimeException $exception): mcpapi_http_response
    {
        $message = $exception->getMessage();
        if (str_contains($message, '权限') || str_contains($message, 'scope') || str_contains($message, 'permission')
            || str_contains($message, 'protected') || str_contains($message, '受保护') || str_contains($message, '当前登录')) {
            return mcpapi_http_response::fromJson(403, ['error' => 'FORBIDDEN']);
        }
        if (str_contains($message, '不存在')) {
            return mcpapi_http_response::fromJson(404, ['error' => 'NOT_FOUND']);
        }
        return mcpapi_http_response::fromJson(409, ['error' => 'WRITE_REJECTED']);
    }
}
