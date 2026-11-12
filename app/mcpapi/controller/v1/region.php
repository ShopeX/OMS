<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_region
{
    private eccommon_capability_region $capability;

    public function __construct(?eccommon_capability_region $capability = null)
    {
        $this->capability = $capability ?? new eccommon_capability_region();
    }

    #[RequirePermission('regions')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        return $this->list($context, []);
    }

    #[RequirePermission('regions')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $result = $this->capability->search($request);
            return mcpapi_http_response::fromJson(200, [
                'list' => $result['list'],
                'total' => $result['total'],
            ]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }
}
