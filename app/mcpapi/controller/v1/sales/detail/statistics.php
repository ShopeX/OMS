<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_sales_detail_statistics
{
    private omeanalysts_capability_sales_detail_statistics $capability;
    private desktop_user $desktopUser;

    public function __construct(?omeanalysts_capability_sales_detail_statistics $capability = null, ?desktop_user $desktopUser = null)
    {
        $this->capability = $capability ?? new omeanalysts_capability_sales_detail_statistics();
        $this->desktopUser = $desktopUser ?? kernel::single('desktop_user');
    }

    #[RequirePermission('analysis_products')]
    public function index(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        return $this->respond($request, $this->organizationPermissions($context));
    }

    private function respond(array $request, array $allowedOrganizationIds): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->execute($request, $allowedOrganizationIds));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, [
                'error' => 'validation_failed',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function organizationPermissions(mcpapi_auth_context $context): array
    {
        if ($context->isSuper()) {
            return [];
        }

        try {
            if (class_exists('desktop_auth_context')) {
                desktop_auth_context::setUserId($context->omsSubjectId());
            }
            if (method_exists($this->desktopUser, 'setVirtualLogin')) {
                $this->desktopUser->setVirtualLogin($context->omsSubjectId());
            }
        } catch (Throwable) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn($organizationId): string => (string) $organizationId,
            $this->desktopUser->get_organization_permission()
        ), static fn(string $organizationId): bool => $organizationId !== '')));
    }
}
