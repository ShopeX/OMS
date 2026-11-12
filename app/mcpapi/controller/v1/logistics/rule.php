<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_logistics_rule
{
    private logistics_capability_rule $capability;

    public function __construct(?logistics_capability_rule $capability = null)
    {
        $this->capability = $capability ?? new logistics_capability_rule();
    }

    #[RequirePermission('logistics_rule')]
    public function index(mcpapi_auth_context $context): mcpapi_http_response
    {
        unset($context);
        return $this->list();
    }

    #[RequirePermission('logistics_rule')]
    public function list(): mcpapi_http_response
    {
        return $this->jsonRows($this->capability->list()['rows'] ?? []);
    }

    #[RequirePermission('logistics_rule')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $row = $this->capability->detail((int) ($request['rule_id'] ?? $request['id'] ?? 0));
            return $row === null
                ? mcpapi_http_response::fromJson(404, ['error' => 'not_found'])
                : mcpapi_http_response::fromJson(200, ['row' => $row]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('logistics_rule')]
    public function getBranches(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        return $this->jsonRows($this->capability->branches()['rows'] ?? []);
    }

    #[RequirePermission('logistics_rule')]
    public function getCorpList(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            $branchId = (int) ($request['branch_id'] ?? 0);
            return $this->jsonRows($this->capability->corpList($branchId)['rows'] ?? []);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('logistics_rule')]
    public function getShops(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        return $this->jsonRows($this->capability->shops((int) ($request['branch_id'] ?? 0))['rows'] ?? []);
    }

    #[RequirePermission('logistics_rule')]
    public function checkRuleName(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->checkRuleName((string) ($request['rule_name'] ?? ''), (int) ($request['branch_id'] ?? 0)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('logistics_rule')]
    public function create(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context);
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->save($request));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        }
    }

    #[RequirePermission('logistics_rule')]
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
