<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_inventory
{
    private console_capability_inventory $capability;

    public function __construct(?console_capability_inventory $capability = null)
    {
        $this->capability = $capability ?? new console_capability_inventory();
    }

    #[RequirePermission('inventory')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        return mcpapi_http_response::fromJson(200, $this->capability->list($request));
    }

    #[RequirePermission('inventory')]
    public function getByMaterialWarehouse(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $row = $this->capability->getByMaterialWarehouse((int) ($request['branch_id'] ?? 0), (int) ($request['bm_id'] ?? 0));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('inventory')]
    public function diffCheck(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->diffCheck((array) ($request['pairs'] ?? [])));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }
}
