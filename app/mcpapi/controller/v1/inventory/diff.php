<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_inventory_diff
{
    private console_capability_inventory_diff $capability;

    public function __construct(?console_capability_inventory_diff $capability = null)
    {
        $this->capability = $capability ?? new console_capability_inventory_diff();
    }

    #[RequirePermission('inventory')]
    public function overview(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->overview($request, $context));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('inventory')]
    public function detail(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $row = $this->capability->detail($request, $context);
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }
}
