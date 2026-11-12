<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class desktop_capability_user_orgscope
{
    private $saveOrganizationPermission;
    private $saveDealerShopPermission;
    private $saveOperationPermission;

    public function __construct(
        ?callable $saveOrganizationPermission = null,
        ?callable $saveDealerShopPermission = null,
        ?callable $saveOperationPermission = null
    ) {
        $this->saveOrganizationPermission = $saveOrganizationPermission ?? static function (int $userId, array $orgIds): bool {
            if (!app::get('organization')->is_installed()) {
                return true;
            }
            return (bool) app::get('organization')->model('organization_ops')->saveUserOrganizations($userId, $orgIds);
        };
        $this->saveDealerShopPermission = $saveDealerShopPermission ?? static function (array $payload): bool {
            return (bool) kernel::single('organization_cos')->save_operation_permission($payload);
        };
        $this->saveOperationPermission = $saveOperationPermission ?? static function (array $payload): bool {
            $model = app::get('ome')->model('operation_ops');
            $userId = (int) ($payload['user_id'] ?? 0);
            if ($userId < 1) {
                return false;
            }
            $model->delete(['op_id' => $userId]);
            foreach ((array) ($payload['org_id'] ?? []) as $orgId) {
                $orgId = (int) $orgId;
                if ($orgId < 1) {
                    continue;
                }
                $row = ['op_id' => $userId, 'org_id' => $orgId];
                $model->save($row);
            }
            return true;
        };
    }

    public function update(int $userId, array $request): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('用户ID不能为空');
        }

        $orgIds = [];
        if (array_key_exists('store_org_conf', $request)) {
            $orgValues = (array) (($request['store_org_conf']['orgIds'] ?? []));
            if (count($orgValues) === 1 && strpos((string) $orgValues[0], ',') !== false) {
                $orgIds = array_values(array_filter(explode(',', (string) $orgValues[0])));
            } else {
                $orgIds = array_values(array_filter(array_map('strval', $orgValues)));
            }

            if (!(($this->saveOrganizationPermission)($userId, $orgIds))) {
                throw new RuntimeException('保存组织权限失败');
            }
        }

        if (array_key_exists('org_id', $request)) {
            $payload = [
                'user_id' => $userId,
                'org_id' => array_values(array_filter(array_map('intval', (array) ($request['org_id'] ?? []))))
            ];
            if (!(($this->saveOperationPermission)($payload))) {
                throw new RuntimeException('保存运营组织权限失败');
            }
        }

        if (array_key_exists('dealer_shop_conf', $request)) {
            $payload = [
                'user_id' => $userId,
                'dealer_shop_conf' => [
                    'cosIds' => (array) (($request['dealer_shop_conf']['cosIds'] ?? []))
                ]
            ];
            if (!(($this->saveDealerShopPermission)($payload))) {
                throw new RuntimeException('保存经销商店铺权限失败');
            }
        }

        return ['success' => true];
    }
}
