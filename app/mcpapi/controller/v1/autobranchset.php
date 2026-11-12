<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_autobranchset
{
    private omeauto_capability_autobranchset $capability;

    public function __construct(?omeauto_capability_autobranchset $capability = null)
    {
        $this->capability = $capability ?? new omeauto_capability_autobranchset();
    }

    #[RequirePermission('auto_branch')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        unset($context);
        return $this->list();
    }

    #[RequirePermission('auto_branch')]
    public function list(): mcpapi_http_response
    {
        return $this->jsonRows($this->capability->list()['rows'] ?? []);
    }

    #[RequirePermission('auto_branch')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $row = $this->capability->detail((int) ($request['tid'] ?? 0));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('auto_branch')]
    public function getBranches(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return $this->jsonRows($this->capability->branches()['rows'] ?? []);
    }

    #[RequirePermission('auto_branch')]
    public function create(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->save($request));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('auto_branch')]
    public function update(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->save($request));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    private function jsonRows(array $rows): mcpapi_http_response
    {
        return mcpapi_http_response::fromJson(200, ['rows' => $rows, 'pager' => ['total' => count($rows), 'page' => 1, 'pageSize' => count($rows), 'totalPages' => $rows ? 1 : 0]]);
    }
}
