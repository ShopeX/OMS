<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

/**
 * OAuth scope 白名单与归一化。
 *
 * scope 为粗粒度工具组；细粒度权限点由 owner 实时收敛（authorizeMcpMethod 的
 * RequirePermission 注解校验）负责。授权码签发时把请求 scope 收敛到白名单内。
 */
final class mcpapi_auth_oauth_scopes
{
    /** @var array<string, string> scope => 中文说明（同意页展示用） */
    public const SUPPORTED = [
        'oms:read' => '订单、售后、库存等业务数据只读',
        'oms:statistics' => '销售与售后统计报表',
        'oms:fulfillment' => '发货、面单、物流履约操作',
        'oms:rules' => '订单类型与自动化规则配置',
        'oms:supply' => '采购、供应商与物料数据',
        'oms:admin' => '用户、组织、店铺等基础配置',
    ];

    /**
     * scope → RequirePermission 权限点映射（技术设计文档 V3.1 §2.3，36 控制器 / 25 权限点实测盘点）。
     *
     * 映射只影响 token 携带的权限面：业务路由的 RequirePermission 注解校验语义不变，
     * 权限点仍然实时解析；本映射用于把用户实时权限收敛到「该应用已获授权的 scope」内。
     * 重叠权限点（invoice_delivery / setting_api_log）在权限点层面天然幂等。
     */
    public const SCOPE_PERMISSIONS = [
        'oms:read' => [
            'order_view',
            'invoice_delivery',
            'aftersale_return_refund_apply',
            'aftersale_return_apply',
            'ome.action.return_reshipping.view',
            'inventory',
            'setting_api_log',
            'regions',
        ],
        'oms:statistics' => [
            'analysis_products',
            'analysis_aftersale',
        ],
        'oms:fulfillment' => [
            'invoice_delivery',
            'setting_api_log',
        ],
        'oms:rules' => [
            'order_type',
            'auto_dispatch',
            'auto_confirm',
            'auto_branch',
            'logistics_rule',
        ],
        'oms:supply' => [
            'console_purchase_po',
            'purchase_supplier',
            'sales_material',
            'sales_material_import',
            'basic_material',
            'basic_material_import',
            'material_cat',
        ],
        'oms:admin' => [
            'users',
            'operator_add',
            'operator_edit',
            'user_roles_add',
            'user_roles_edit',
            'operation_org',
            'branch',
            'setting_shop',
            'goods_type',
            'goods_brand',
            'delivery_mode',
        ],
    ];

    /**
     * 解析空格分隔的 scope 字符串。
     *
     * @return string[]
     */
    public function parse(?string $scope): array
    {
        if ($scope === null || trim($scope) === '') {
            return [];
        }
        $values = preg_split('/\s+/', trim($scope), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($values === false ? [] : $values));
    }

    /**
     * 请求 scope 收敛到白名单内（未知 scope 直接丢弃，不报错——公共客户端可能携带扩展 scope）。
     *
     * @param string[] $scopes
     * @return string[]
     */
    public function sanitize(array $scopes): array
    {
        $allowed = array_fill_keys(array_keys(self::SUPPORTED), true);
        return array_values(array_unique(array_filter(
            $scopes,
            static fn ($scope): bool => is_string($scope) && isset($allowed[$scope])
        )));
    }

    /**
     * scope 集合展开为权限点集合（并集、去重）。
     *
     * @param string[] $scopes
     * @return string[]
     */
    public function permissionsFor(array $scopes): array
    {
        $permissions = [];
        foreach ($scopes as $scope) {
            foreach (self::SCOPE_PERMISSIONS[$scope] ?? [] as $permission) {
                $permissions[$permission] = true;
            }
        }
        return array_keys($permissions);
    }

    /**
     * 权限点收敛：实时角色权限 ∩ 已授权 scope 展开的权限点。
     *
     * @param string[] $scopes
     * @param string[] $ownerPermissions
     * @return string[]
     */
    public function convergePermissions(array $scopes, array $ownerPermissions): array
    {
        $allowed = array_fill_keys($this->permissionsFor($scopes), true);
        return array_values(array_filter(
            $ownerPermissions,
            static fn ($permission): bool => is_string($permission) && isset($allowed[$permission])
        ));
    }

    public function isSupported(string $scope): bool
    {
        return isset(self::SUPPORTED[$scope]);
    }

    /**
     * 默认 scope（请求未携带或全部非法时授予最小集）。
     *
     * @return string[]
     */
    public function defaults(): array
    {
        return ['oms:read'];
    }

    public function describe(array $scopes): array
    {
        $descriptions = [];
        foreach ($scopes as $scope) {
            if (isset(self::SUPPORTED[$scope])) {
                $descriptions[] = ['scope' => $scope, 'description' => self::SUPPORTED[$scope]];
            }
        }
        return $descriptions;
    }
}
