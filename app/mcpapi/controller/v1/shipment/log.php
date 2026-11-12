<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_shipment_log
{
    private ome_capability_shipment_log $capability;

    public function __construct(?ome_capability_shipment_log $capability = null)
    {
        $this->capability = $capability ?? new ome_capability_shipment_log();
    }

    #[RequirePermission('setting_api_log')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        return $this->list($context, []);
    }

    #[RequirePermission('setting_api_log')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson(200, $this->capability->list($request));
    }

    #[RequirePermission('setting_api_log')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $row = $this->capability->detail((string) ($request['log_id'] ?? ''));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }
}
