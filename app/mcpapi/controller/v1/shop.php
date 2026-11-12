<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_shop
{
    private ome_capability_shop $capability;

    public function __construct(?ome_capability_shop $capability = null)
    {
        $this->capability = $capability ?? new ome_capability_shop();
    }

    #[RequirePermission('setting_shop')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        return $this->list($context, []);
    }

    #[RequirePermission('setting_shop')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($request);
        return mcpapi_http_response::fromJson(200, $this->capability->list($this->organizationPermissions($context)));
    }

    #[RequirePermission('setting_shop')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $row = $this->capability->detail((int) ($request['shop_id'] ?? 0), $this->organizationPermissions($context));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('setting_shop')]
    public function addForm(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return mcpapi_http_response::fromJson(200, $this->capability->addForm());
    }

    #[RequirePermission('setting_shop')]
    public function editForm(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $data = $this->capability->editForm((int) ($request['shop_id'] ?? 0), $this->organizationPermissions($context));
            return $data === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, $data);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
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
