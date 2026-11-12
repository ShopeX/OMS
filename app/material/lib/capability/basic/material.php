<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class material_capability_basic_material
{
    private object $basicMaterialModel;
    private object $basicMaterialExtModel;
    private object $basicMaterialConfModel;
    private object $basicMaterialConfSpecialModel;
    private object $barcodeModel;
    private object $basicMaterialCatModel;
    private object $propsModel;
    private object $featureGroupModel;
    private object $combinationItemsModel;

    public function __construct(
        ?object $basicMaterialModel = null,
        ?object $basicMaterialExtModel = null,
        ?object $basicMaterialConfModel = null,
        ?object $basicMaterialConfSpecialModel = null,
        ?object $barcodeModel = null,
        ?object $basicMaterialCatModel = null,
        ?object $propsModel = null,
        ?object $featureGroupModel = null,
        ?object $combinationItemsModel = null
    ) {
        $this->basicMaterialModel = $basicMaterialModel ?? app::get('material')->model('basic_material');
        $this->basicMaterialExtModel = $basicMaterialExtModel ?? app::get('material')->model('basic_material_ext');
        $this->basicMaterialConfModel = $basicMaterialConfModel ?? app::get('material')->model('basic_material_conf');
        $this->basicMaterialConfSpecialModel = $basicMaterialConfSpecialModel ?? app::get('material')->model('basic_material_conf_special');
        $this->barcodeModel = $barcodeModel ?? app::get('material')->model('codebase');
        $this->basicMaterialCatModel = $basicMaterialCatModel ?? app::get('material')->model('basic_material_cat');
        $this->propsModel = $propsModel ?? app::get('material')->model('basic_material_props');
        $this->featureGroupModel = $featureGroupModel ?? app::get('material')->model('basic_material_feature_group');
        $this->combinationItemsModel = $combinationItemsModel ?? app::get('material')->model('basic_material_combination_items');
    }

    public function list(array $request): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $filter = is_array($request['filters'] ?? null) ? $request['filters'] : [];
        $orderBy = (string) ($request['orderBy'] ?? 'bm_id');
        $orderType = strtoupper((string) ($request['orderType'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $rows = $this->basicMaterialModel->getList('*', $filter, $offset, $pageSize, $orderBy . ' ' . $orderType);
        $total = method_exists($this->basicMaterialModel, 'count')
            ? (int) $this->basicMaterialModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(int $bmId): ?array
    {
        if ($bmId < 1) {
            throw new InvalidArgumentException('缺少基础物料ID');
        }

        $basicInfo = $this->basicMaterialModel->dump($bmId);
        if (!is_array($basicInfo) || $basicInfo === []) {
            return null;
        }

        $extInfo = $this->basicMaterialExtModel->dump($bmId);
        $confInfo = $this->basicMaterialConfModel->dump($bmId);
        $specialConf = $this->basicMaterialConfSpecialModel->dump($bmId);
        $barcodeInfo = $this->barcodeModel->dump(['bm_id' => $bmId, 'type' => material_codebase::getBarcodeType()], 'code');
        $propsList = $this->propsModel->getList('*', ['bm_id' => $bmId]);
        $featureGroupInfo = $this->featureGroupModel->getList('*', ['bm_id' => $bmId], 0, 1);

        $props = [];
        foreach ($propsList as $item) {
            $props[$item['props_col']] = $item['props_value'];
        }

        $combinationItems = [];
        if (in_array((string) ($basicInfo['type'] ?? ''), ['1', '4'], true)) {
            $items = $this->combinationItemsModel->getList('*', ['pbm_id' => $bmId]);
            foreach ($items as $item) {
                $combinationItems[] = [
                    'bm_id' => (int) ($item['bm_id'] ?? 0),
                    'material_name' => (string) ($item['material_name'] ?? ''),
                    'material_bn' => (string) ($item['material_bn'] ?? ''),
                    'material_num' => (string) ($item['material_num'] ?? ''),
                ];
            }
        }

        $catInfo = [];
        if (!empty($basicInfo['cat_id'])) {
            $catInfo = (array) $this->basicMaterialCatModel->dump($basicInfo['cat_id'], 'cat_id,cat_path,min_price');
        }

        return [
            'bm_id' => $bmId,
            'type' => (string) ($basicInfo['type'] ?? '1'),
            'material_name' => (string) ($basicInfo['material_name'] ?? ''),
            'material_bn' => (string) ($basicInfo['material_bn'] ?? ''),
            'material_spu' => (string) ($basicInfo['material_spu'] ?? ''),
            'cat_id' => $basicInfo['cat_id'] ?? '',
            'cat_path' => (string) ($basicInfo['cat_path'] ?? ''),
            'brand' => $extInfo['brand_id'] ?? '',
            'goods_type' => $extInfo['cat_id'] ?? '',
            'material_code' => (string) ($barcodeInfo['code'] ?? ''),
            'material_specification' => (string) ($extInfo['specifications'] ?? ''),
            'special_setting' => $specialConf['openscan'] ?? '',
            'first_num' => $specialConf['fromposition'] ?? '',
            'last_num' => $specialConf['toposition'] ?? '',
            'serial_number' => $basicInfo['serial_number'] ?? 'false',
            'visibled' => $basicInfo['visibled'] ?? 1,
            'omnichannel' => $basicInfo['omnichannel'] ?? 2,
            'is_o2o_sales' => $basicInfo['is_o2o_sales'] ?? 0,
            'is_ctrl_store' => $basicInfo['is_ctrl_store'] ?? 1,
            'bbu_info' => (!empty($basicInfo['bbu_id']) && !empty($basicInfo['cos_id']))
                ? $basicInfo['bbu_id'] . '|' . $basicInfo['cos_id']
                : '',
            'tax_rate' => $basicInfo['tax_rate'] ?? '',
            'tax_name' => $basicInfo['tax_name'] ?? '',
            'tax_code' => $basicInfo['tax_code'] ?? '',
            'color' => $basicInfo['color'] ?? '',
            'size' => $basicInfo['size'] ?? '',
            'cost' => $extInfo['cost'] ?? '',
            'retail_price' => $extInfo['retail_price'] ?? '',
            'weight' => $extInfo['weight'] ?? '',
            'length' => $extInfo['length'] ?? '',
            'width' => $extInfo['width'] ?? '',
            'high' => $extInfo['high'] ?? '',
            'unit' => $extInfo['unit'] ?? '',
            'purchasing_price' => $extInfo['purchasing_price'] ?? '',
            'volume' => $extInfo['volume'] ?? '',
            'box_spec' => $extInfo['box_spec'] ?? '',
            'net_weight' => $extInfo['net_weight'] ?? '',
            'use_expire' => $confInfo['use_expire'] ?? 2,
            'warn_day' => $confInfo['warn_day'] ?? '',
            'quit_day' => $confInfo['quit_day'] ?? '',
            'shelf_life' => $confInfo['shelf_life'] ?? '',
            'reject_life_cycle' => $confInfo['reject_life_cycle'] ?? '',
            'lockup_life_cycle' => $confInfo['lockup_life_cycle'] ?? '',
            'advent_life_cycle' => $confInfo['advent_life_cycle'] ?? '',
            'ftgp_id' => !empty($featureGroupInfo[0]) ? $featureGroupInfo[0]['feature_group_id'] : '',
            'props' => $props,
            'combination_items' => $combinationItems,
            'cat_info' => $catInfo,
        ];
    }

    public function importTemplate(): array
    {
        $columns = $this->basicMaterialModel->templateColumn();
        return [
            'columns' => $columns,
            'headers' => array_keys($columns),
            'csv_header' => implode(',', array_keys($columns)),
        ];
    }

    private function normalizeListRow(array $row): array
    {
        if (isset($row['bm_id'])) {
            $row['bm_id'] = (int) $row['bm_id'];
        }
        if (isset($row['cat_id']) && $row['cat_id'] !== '') {
            $row['cat_id'] = (int) $row['cat_id'];
        }

        return $row;
    }
}
