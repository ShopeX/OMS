<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_material_sales
{
    private material_capability_sales_material $capability;

    public function __construct(?material_capability_sales_material $capability = null)
    {
        $this->capability = $capability ?? new material_capability_sales_material();
    }

    #[RequirePermission('sales_material')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        return mcpapi_http_response::fromJson(200, $this->capability->list($request));
    }

    #[RequirePermission('sales_material')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $row = $this->capability->detail((int) ($request['sm_id'] ?? 0));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('sales_material_import')]
    public function importTemplate(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return mcpapi_http_response::fromJson(200, $this->capability->importTemplate());
    }
}
