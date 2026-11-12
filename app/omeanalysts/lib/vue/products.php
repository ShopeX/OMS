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

class omeanalysts_vue_products extends omeanalysts_vue_abstract
{
    /**
     * 重写 getData 以支持 material_type 参数
     */
    public function getData(string $materialType = 'basic_material'): array
    {
        $params = $this->parseParams();
        $params['material_type'] = $materialType;

        return [
            'summary' => $this->getSummary($params),
            'list' => $this->getList($params),
            'filterOptions' => $this->getFilterOptions(),
            'timeShortcuts' => $this->getTimeShortcuts(),
            'timeExplain' => $params['time_explain'] ?? '',
            'total' => $this->getTotal($params)
        ];
    }

    protected function getSummary(array $params): array
    {
        $model = $this->getModel($params);
        $data = $model->get_products($params);

        return [
            ['key' => 'sale_num', 'label' => '销售量', 'value' => $this->formatNumber($data['salenums'] ?? 0, 0)],
            ['key' => 'sale_amount', 'label' => '销售额', 'value' => $this->formatMoney($data['sale_amount'] ?? 0)],
            ['key' => 'day_amounts', 'label' => '日均销售额', 'value' => $this->formatMoney($data['day_amounts'] ?? 0)],
            ['key' => 'day_nums', 'label' => '日均销售量', 'value' => $this->formatNumber($data['day_nums'] ?? 0)],
            ['key' => 'gross_sales', 'label' => '销售毛利', 'value' => $this->formatMoney($data['gross_sales'] ?? 0)],
            ['key' => 'gross_sales_rate', 'label' => '销售毛利率', 'value' => $this->formatPercent($data['gross_sales_rate'] ?? 0)],
            ['key' => 'reship_nums', 'label' => '退货量', 'value' => $this->formatNumber($data['reship_nums'] ?? 0, 0)],
            ['key' => 'reship_amounts', 'label' => '退货总额', 'value' => $this->formatMoney($data['reship_amounts'] ?? 0)]
        ];
    }

    protected function getList(array $params): array
    {
        $model = $this->getModel($params);
        $offset = $params['offset'] ?? 0;
        $limit = $params['page_size'] ?? 20;
        $orderType = $params['order_by']
            ? ($params['order_by'] . ' ' . $params['order_type'])
            : null;

        $list = $model->getList('*', $params, $offset, $limit, $orderType);

        return array_map(function ($row) {
            return [
                'bn' => $row['bn'] ?? '',
                'name' => $row['name'] ?? '',
                'shop_name' => $row['shop_name'] ?? '',
                'org_name' => $row['org_name'] ?? '',
                'sale_amount' => $this->formatMoney($row['sale_amount'] ?? 0),
                'sale_num' => $this->formatNumber($row['sale_num'] ?? 0, 0),
                'day_amount' => $this->formatMoney($row['day_amount'] ?? 0),
                'type_id' => $row['type_id'] ?? '',
                'cat_name' => $row['cat_id'] ?? '',
                'cost_amount' => $this->formatMoney($row['cost_amount'] ?? 0),
                'gross_sales' => $this->formatMoney($row['gross_sales'] ?? 0),
                'gross_sales_rate' => $this->formatPercent($row['gross_sales_rate'] ?? 0),
                'reship_num' => $this->formatNumber($row['reship_num'] ?? 0, 0),
                'reship_ratio' => $this->formatPercent($row['reship_ratio'] ?? 0),
                'sale_price' => $this->formatMoney($row['sale_price'] ?? 0)
            ];
        }, $list);
    }

    public function getFilterOptions(): array
    {
        $typeObj = $this->app->model('ome_type');

        $shops = $typeObj->get_shop();
        $shopTypes = $typeObj->getShopType();
        $organizations = $typeObj->getOrg();

        return [
            'shops' => $this->formatOptions($shops, 'type_id', 'name'),
            'shopTypes' => $this->formatOptions($shopTypes),
            'organizations' => $this->formatOptions($organizations, 'org_id', 'name')
        ];
    }

    public function getTotal(array $params): int
    {
        return $this->getModel($params)->count($params);
    }

    /**
     * 根据 material_type 获取对应的 Model
     */
    private function getModel(array $params): dbeav_model
    {
        $type = $params['material_type'] ?? 'basic_material';
        switch ($type) {
            case 'sales_material':
                return kernel::single('omeanalysts_mdl_sales_goods');  // MODEL class
            case 'sales_and_basic_material':
                return kernel::single('omeanalysts_mdl_sales_products');  // MODEL class
            case 'basic_material':
            default:
                return kernel::single('omeanalysts_mdl_ome_products');
        }
    }

    private function formatOptions(array $data, string $valueKey = 'type_id', string $labelKey = 'name'): array
    {
        if (empty($data)) {
            return [];
        }
        return array_map(function ($item) use ($valueKey, $labelKey) {
            return [
                'value' => $item[$valueKey] ?? '',
                'label' => $item[$labelKey] ?? ''
            ];
        }, $data);
    }
}