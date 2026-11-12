<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_confirm_group
{
    private object $groupsModel;

    public function __construct(?object $groupsModel = null)
    {
        $this->groupsModel = $groupsModel ?? app::get('ome')->model('groups');
    }

    /**
     * POST /mcp-api/v1/confirm/group/list
     * List order confirmation groups for user assignment candidates.
     */
    #[RequirePermission('users')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        unset($context, $request);
        try {
            $rows = $this->groupsModel->getList('group_id,name', ['g_type' => 'confirm'], 0, -1, 'group_id ASC');
            $items = [];
            foreach ((array) $rows as $row) {
                $groupId = (int) ($row['group_id'] ?? 0);
                if ($groupId < 1) {
                    continue;
                }
                $items[] = [
                    'group_id' => $groupId,
                    'group_name' => (string) ($row['name'] ?? ''),
                ];
            }

            return mcpapi_http_response::fromJson(200, [
                'data' => [
                    'items' => $items,
                    'total' => count($items),
                ],
            ]);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR']);
        }
    }
}
