<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_material_cat
{
    private material_capability_cat $capability;

    public function __construct(?material_capability_cat $capability = null)
    {
        $this->capability = $capability ?? new material_capability_cat();
    }

    #[RequirePermission('material_cat')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        return $this->list($context, []);
    }

    #[RequirePermission('material_cat')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        $rows = $this->capability->tree();
        return mcpapi_http_response::fromJson(200, ['rows' => $rows, 'total' => count($rows)]);
    }

    #[RequirePermission('material_cat')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $row = $this->capability->detail((int) ($request['cat_id'] ?? 0));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('material_cat')]
    public function create(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->create($request));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('material_cat')]
    public function update(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->update((int) ($request['cat_id'] ?? 0), $request));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('material_cat')]
    public function delete(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, ['success' => $this->capability->delete((int) ($request['cat_id'] ?? 0))]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('material_cat')]
    public function sort(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $count = $this->capability->sort((array) ($request['p_order'] ?? []));
            return mcpapi_http_response::fromJson(200, ['success' => true, 'updated' => $count]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }
}
