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

abstract class omeanalysts_vue_abstract
{
    protected $_params = [];
    protected $app = null;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * API 入口 - 返回报表数据
     */
    public function getData(): array
    {
        $params = $this->parseParams();

        return [
            'summary' => $this->getSummary($params),
            'list' => $this->getList($params),
            'filterOptions' => $this->getFilterOptions(),
            'timeShortcuts' => $this->getTimeShortcuts(),
            'timeExplain' => $params['time_explain'] ?? '',
            'total' => $this->getTotal($params)
        ];
    }

    // ==================== 必须实现的方法 ====================

    abstract protected function getSummary(array $params): array;

    abstract protected function getList(array $params): array;

    abstract public function getFilterOptions(): array;

    abstract public function getTotal(array $params): int;

    // ==================== 可覆盖的方法 ====================

    protected function parseParams(): array
    {
        $params = $_GET;

        // 分页参数
        $params['page'] = intval($params['page'] ?? 1);
        $params['page_size'] = intval($params['page_size'] ?? 20);
        $params['offset'] = ($params['page'] - 1) * $params['page_size'];
        $params['order_by'] = $params['order_by'] ?? null;
        $params['order_type'] = strtoupper($params['order_type'] ?? 'DESC');

        // 组织权限过滤
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $params['org_id'] = $organization_permissions;
        }

        // order_status 决定用哪个时间字段查询
        $config = app::get('eccommon')->getConf('analysis_config');
        $params['order_status'] = $config['filter']['order_status'] ?? 'ship';
        $params['time_explain'] = $this->getTimeExplain($params['order_status']);

        return $params;
    }

    protected function getTimeExplain(string $orderStatus): string
    {
        $map = [
            'createorder' => '创建时间',
            'confirmed' => '确认时间',
            'pay' => '付款时间',
            'ship' => '发货时间'
        ];
        return $map[$orderStatus] ?? '发货时间';
    }

    protected function getTimeShortcuts(): array
    {
        return [
            ['key' => 'yesterday', 'label' => '昨日'],
            ['key' => 'last7days', 'label' => '最近7天'],
            ['key' => 'thisMonth', 'label' => '本月'],
            ['key' => 'thisWeek', 'label' => '本周']
        ];
    }

    // ==================== 工具方法 ====================

    protected function formatMoney($value): string
    {
        return '￥' . number_format($value ?? 0, 2, '.', ',');
    }

    protected function formatPercent($value): string
    {
        return round(($value ?? 0) * 100, 2) . '%';
    }

    protected function formatNumber($value, int $decimals = 2): string
    {
        return round($value ?? 0, $decimals);
    }
}