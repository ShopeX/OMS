<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_automation_rule
{
    private omeauto_capability_rule_explain $capability;

    public function __construct(?omeauto_capability_rule_explain $capability = null)
    {
        $this->capability = $capability ?? new omeauto_capability_rule_explain();
    }

    #[RequirePermission('order_view')]
    public function explain(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            return mcpapi_http_response::fromJson(200, $this->capability->explain($request, $this->organizationPermissions($context)));
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'validation_failed', 'message' => $exception->getMessage()]);
        } catch (OutOfBoundsException $exception) {
            return mcpapi_http_response::fromJson(404, ['error' => 'not_found', 'message' => $exception->getMessage()]);
        }
    }

    private function organizationPermissions(mcpapi_auth_context $context): ?array
    {
        if ($context->isSuper()) {
            return null;
        }

        $rows = app::get('ome')->model('operation_ops')->getList('org_id', ['op_id' => $context->omsSubjectId()], 0, -1) ?: [];
        return array_values(array_unique(array_map('intval', array_column($rows, 'org_id'))));
    }
}
