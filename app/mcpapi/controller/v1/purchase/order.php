<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_purchase_order
{
    private console_capability_purchase $capability;

    public function __construct(?console_capability_purchase $capability = null)
    {
        $this->capability = $capability ?? new console_capability_purchase();
    }

    #[RequirePermission('console_purchase_po')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        return $this->list($context, []);
    }

    #[RequirePermission('console_purchase_po')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson(200, $this->capability->list($request, $context));
    }

    #[RequirePermission('console_purchase_po')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $row = $this->capability->detail((int) ($request['po_id'] ?? 0), $context);
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('console_purchase_po')]
    public function importTemplate(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return mcpapi_http_response::fromJson(200, $this->capability->importTemplate());
    }

    #[RequirePermission('console_purchase_po')]
    public function import(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(202, $this->capability->import($request, $context));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }
}
