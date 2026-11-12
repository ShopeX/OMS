<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_order_type
{
    private omeauto_capability_order_type $capability;

    public function __construct(?omeauto_capability_order_type $capability = null)
    {
        $this->capability = $capability ?? new omeauto_capability_order_type();
    }

    #[RequirePermission('order_type')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        unset($context);
        return $this->list();
    }

    #[RequirePermission('order_type')]
    public function list(): mcpapi_http_response
    {
        return $this->jsonRows($this->capability->list()['rows'] ?? []);
    }

    #[RequirePermission('order_type')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $row = $this->capability->detail((int) ($request['tid'] ?? 0));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('order_type')]
    public function getOrganizations(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return $this->jsonRows($this->capability->organizations()['rows'] ?? []);
    }

    #[RequirePermission('order_type')]
    public function getBranches(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return $this->jsonRows($this->capability->branches()['rows'] ?? []);
    }

    #[RequirePermission('order_type')]
    public function getAutoconfirmRules(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return $this->jsonRows($this->capability->autoconfirmRules()['rows'] ?? []);
    }

    #[RequirePermission('order_type')]
    public function getAutodispatchRules(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return $this->jsonRows($this->capability->autodispatchRules()['rows'] ?? []);
    }

    #[RequirePermission('order_type')]
    public function options(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $role = trim((string) ($request['role'] ?? ''));
            $orgId = (int) ($request['org_id'] ?? 0);
            $options = $role !== '' ? $this->capability->conditionOptions($role, $orgId) : [];
            return mcpapi_http_response::fromJson(200, [
                'role' => $role,
                'options' => $options,
                'count' => count($options),
            ]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('order_type')]
    public function create(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->create($request, $this->organizationPermissions($context)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('order_type')]
    public function update(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->update((int) ($request['tid'] ?? 0), $request, $this->organizationPermissions($context)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found', 'message' => $exception->getMessage()]);
        }
    }

    private function organizationPermissions(mcpapi_auth_context $context): ?array
    {
        if ($context->isSuper()) {
            return null;
        }

        $rows = app::get('ome')->model('operation_ops')->getList('org_id', ['op_id' => $context->omsSubjectId()], 0, -1) ?: [];
        $orgIds = array_values(array_unique(array_map('intval', array_column($rows, 'org_id'))));
        return $orgIds;
    }

    private function jsonRows(array $rows): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson(200, ['rows' => $rows, 'pager' => ['total' => count($rows), 'page' => 1, 'pageSize' => count($rows), 'totalPages' => $rows ? 1 : 0]]);
    }
}
