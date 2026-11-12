<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_return_product
{
    private ome_capability_return_product $capability;

    public function __construct(?ome_capability_return_product $capability = null)
    {
        $this->capability = $capability ?? new ome_capability_return_product();
    }

    #[RequirePermission('aftersale_return_apply')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        return $this->list($context, []);
    }

    #[RequirePermission('aftersale_return_apply')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson(200, $this->capability->list($request, $context));
    }

    #[RequirePermission('aftersale_return_apply')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $row = $this->capability->detail((int) ($request['return_id'] ?? 0), $context);
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }
}
