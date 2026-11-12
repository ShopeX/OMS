<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_aftersales
{
    private ome_capability_aftersales_unified $capability;

    public function __construct(?ome_capability_aftersales_unified $capability = null)
    {
        $this->capability = $capability ?? new ome_capability_aftersales_unified();
    }

    #[RequirePermission(all: ['aftersale_return_refund_apply', 'aftersale_return_apply', 'ome.action.return_reshipping.view'])]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $this->validateRequest($request);
            return mcpapi_http_response::fromJson(200, $this->capability->list($request, $context));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    private function validateRequest(array $request): void
    {
        if (trim((string) ($request['query'] ?? '')) === '') {
            throw new InvalidArgumentException('query must not be empty');
        }

        foreach (['page' => [1, null], 'page_size' => [1, 100], 'pageSize' => [1, 100]] as $field => [$minimum, $maximum]) {
            if (!array_key_exists($field, $request)) {
                continue;
            }
            if (!is_int($request[$field]) || $request[$field] < $minimum || ($maximum !== null && $request[$field] > $maximum)) {
                throw new InvalidArgumentException($field . ' is invalid');
            }
        }
    }
}
