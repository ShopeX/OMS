<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_autoconfirm
{
    private omeauto_capability_autoconfirm $capability;

    public function __construct(?omeauto_capability_autoconfirm $capability = null)
    {
        $this->capability = $capability ?? new omeauto_capability_autoconfirm();
    }

    #[RequirePermission('auto_confirm')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        return $this->list($context, []);
    }

    #[RequirePermission('auto_confirm')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($request);
        return $this->jsonRows($this->capability->list($this->organizationPermissions($context)));
    }

    #[RequirePermission('auto_confirm')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $row = $this->capability->detail((int) ($request['oid'] ?? 0), $this->organizationPermissions($context));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('auto_confirm')]
    public function orderTypes(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($request);
        return $this->jsonRows($this->capability->orderTypes($this->organizationPermissions($context)));
    }

    #[RequirePermission('auto_confirm')]
    public function create(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->create($request, $this->organizationPermissions($context)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found']);
        }
    }

    #[RequirePermission('auto_confirm')]
    public function update(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->update((int) ($request['oid'] ?? 0), $request, $this->organizationPermissions($context)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found']);
        }
    }

    #[RequirePermission('auto_confirm')]
    public function setStatus(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->setStatus((int) ($request['oid'] ?? 0), (string) ($request['status'] ?? ''), $this->organizationPermissions($context)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found']);
        }
    }

    #[RequirePermission('auto_confirm')]
    public function setDefault(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->setDefault((int) ($request['oid'] ?? 0), $this->organizationPermissions($context)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found']);
        }
    }

    #[RequirePermission('auto_confirm')]
    public function removeDefault(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->removeDefault((int) ($request['oid'] ?? 0), $this->organizationPermissions($context)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found']);
        }
    }

    private function jsonRows(array $rows): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson(200, ['rows' => $rows, 'pager' => ['total' => count($rows), 'page' => 1, 'pageSize' => count($rows), 'totalPages' => $rows ? 1 : 0]]);
    }

    private function allowed(mcpapi_auth_context $context): bool
    {
        return $context->hasPermission('auto_confirm');
    }

    private function organizationPermissions(mcpapi_auth_context $context): ?array
    {
        if ($context->isSuper()) {
            return null;
        }
        try {
            if (class_exists('desktop_auth_context')) {
                desktop_auth_context::setUserId($context->omsSubjectId());
            }
            $user = kernel::single('desktop_user');
            $user->setVirtualLogin($context->omsSubjectId());
            return array_values($user->get_organization_permission());
        } catch (Throwable) {
            return [];
        }
    }
}
