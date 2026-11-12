<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class omeauto_capability_order_type
{
    private object $orderTypeModel;
    private object $organizationModel;
    private object $branchModel;
    private object $autoConfirmModel;
    private object $autoDispatchModel;
    private object $regionModel;
    private object $shopModel;
    private object $orderLabelModel;
    private object $materialCatModel;
    private object $customerClassModel;
    private object $salesMaterialModel;

    public function __construct(
        ?object $orderTypeModel = null,
        ?object $organizationModel = null,
        ?object $branchModel = null,
        ?object $autoConfirmModel = null,
        ?object $autoDispatchModel = null,
        ?object $regionModel = null,
        ?object $shopModel = null,
        ?object $orderLabelModel = null,
        ?object $materialCatModel = null,
        ?object $customerClassModel = null,
        ?object $salesMaterialModel = null,
    ) {
        $this->orderTypeModel = $orderTypeModel ?? app::get('omeauto')->model('order_type');
        $this->organizationModel = $organizationModel ?? app::get('ome')->model('operation_organization');
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
        $this->autoConfirmModel = $autoConfirmModel ?? app::get('omeauto')->model('autoconfirm');
        $this->autoDispatchModel = $autoDispatchModel ?? app::get('omeauto')->model('autodispatch');
        $this->regionModel = $regionModel ?? app::get('eccommon')->model('regions');
        $this->shopModel = $shopModel ?? app::get('ome')->model('shop');
        $this->orderLabelModel = $orderLabelModel ?? app::get('omeauto')->model('order_labels');
        $this->materialCatModel = $materialCatModel ?? app::get('material')->model('basic_material_cat');
        $this->customerClassModel = $customerClassModel ?? app::get('material')->model('customer_classify');
        $this->salesMaterialModel = $salesMaterialModel ?? app::get('material')->model('sales_material');
    }

    public function normalizeGroupConditions(mixed $conditions, int $orgId = 0): array
    {
        return $this->normalizeConditions($conditions, $orgId);
    }

    public function list(): array
    {
        return ['rows' => $this->orderTypeModel->getList('*', ['group_type' => 'order'], 0, -1, 'weight DESC, tid ASC') ?: []];
    }

    public function detail(int $tid): ?array
    {
        if ($tid < 1) {
            throw new InvalidArgumentException('订单分组规则ID无效');
        }

        $row = $this->orderTypeModel->dump(['tid' => $tid], '*');
        if (!is_array($row) || ($row['group_type'] ?? '') !== 'order') {
            return null;
        }

        return $row;
    }

    public function organizations(): array
    {
        return ['rows' => $this->organizationModel->getList('org_id,code,name', [], 0, -1, 'name ASC') ?: []];
    }

    public function branches(): array
    {
        $rows = $this->branchModel->getList('branch_id,name,branch_bn', ['is_deliv_branch' => 'true'], 0, -1, 'name ASC') ?: [];
        $rows[] = ['branch_id' => -1, 'name' => '参加O2O的门店', 'branch_bn' => ''];
        return ['rows' => $rows];
    }

    public function autoconfirmRules(): array
    {
        return ['rows' => $this->autoConfirmModel->getList('oid,name,disabled', [], 0, -1, 'oid DESC') ?: []];
    }

    public function autodispatchRules(): array
    {
        return ['rows' => $this->autoDispatchModel->getList('oid,name,disabled', [], 0, -1, 'oid DESC') ?: []];
    }

    public function conditionOptions(string $role, int $orgId = 0): array
    {
        $role = strtolower($role);
        if (!in_array($role, omeauto_auto_type::$TYPE_LIST, true)) {
            throw new InvalidArgumentException('不支持该分组条件类型：' . $role);
        }

        return match ($role) {
            'ordertype' => $this->orderTypeOptions(),
            'cod' => [
                ['value' => 'true', 'label' => '货到付款'],
                ['value' => 'false', 'label' => '款到发货'],
            ],
            'shopmode' => [
                ['value' => 'shop_self', 'label' => '直销店铺(默认)'],
                ['value' => 'shop_yjdf', 'label' => '一件代发店铺'],
            ],
            'shop' => $this->shopOptions($orgId),
            'platform' => $this->platformOptions(),
            'materialtype' => $this->materialTypeOptions(),
            'materialcat' => $this->materialCatOptions(),
            'customer' => $this->customerClassOptions(),
            'orderlabel' => $this->orderLabelOptions(),
            default => [],
        };
    }

    private function orderTypeOptions(): array
    {
        $orderFunc = kernel::single('ome_order_func');
        $labels = array_merge(array_map('strval', $orderFunc->get_order_type()), [
            'presale' => '预售订单',
            'gift' => '赠品订单',
            'integral' => '积原子单',
            'staff' => '员工订单',
            'custom' => '定制订单',
        ]);
        $options = [];
        foreach ($orderFunc->get_normal_order_type() as $code) {
            $options[] = ['value' => $code, 'label' => $labels[$code] ?? $code];
        }
        return $options;
    }

    private function shopOptions(int $orgId): array
    {
        $filter = ['s_type' => 1, 'disabled' => 'false'];
        if ($orgId > 0) {
            $filter['org_id'] = $orgId;
        }
        $rows = $this->shopModel->getList('shop_id,name', $filter, 0, -1, 'name ASC') ?: [];
        return array_map(static fn(array $row): array => ['value' => (string) $row['shop_id'], 'label' => (string) $row['name']], $rows);
    }

    private function platformOptions(): array
    {
        $labels = array_merge(
            array_map('strval', ome_shop_type::get_shop_type()),
            ['haoshiqi_taobao' => '好食期-淘宝', 'haoshiqi_douyin' => '好食期-抖音', 'haoshiqi_kuaishou' => '好食期-快手']
        );
        $seen = [];
        $options = [];
        foreach ($labels as $value => $label) {
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    private function materialTypeOptions(): array
    {
        $labels = kernel::single('material_basic_material')->get_material_types();
        $options = [];
        foreach ($labels as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => (string) $label];
        }
        return $options;
    }

    private function materialCatOptions(): array
    {
        $rows = $this->materialCatModel->getList('cat_id,parent_id,cat_name,cat_code', ['disabled' => 'false'], 0, -1, 'cat_id ASC') ?: [];
        return array_map(static fn(array $row): array => [
            'value' => (string) $row['cat_id'],
            'label' => (string) $row['cat_name'],
            'parent_id' => isset($row['parent_id']) ? (string) $row['parent_id'] : '0',
        ], $rows);
    }

    private function customerClassOptions(): array
    {
        $rows = $this->customerClassModel->getList('class_id,class_name', ['disabled' => 'false'], 0, -1, 'class_id ASC') ?: [];
        return array_map(static fn(array $row): array => ['value' => (string) $row['class_id'], 'label' => (string) $row['class_name']], $rows);
    }

    private function orderLabelOptions(): array
    {
        $rows = $this->orderLabelModel->getList('label_id,label_name', [], 0, 200, 'label_id ASC') ?: [];
        return array_map(static fn(array $row): array => ['value' => (string) $row['label_id'], 'label' => (string) $row['label_name']], $rows);
    }

    public function create(array $request, ?array $allowedOrganizationIds): array
    {
        $saved = $this->save($request, $allowedOrganizationIds);
        return ['tid' => $saved['tid'], 'row' => $saved['row']];
    }

    public function update(int $tid, array $request, ?array $allowedOrganizationIds): array
    {
        $current = $this->detail($tid);
        if ($current === null || ($current['group_type'] ?? '') !== 'order') {
            throw new OutOfBoundsException('分组规则不存在或没有组织权限');
        }

        $request['tid'] = $tid;
        $saved = $this->save($request, $allowedOrganizationIds);
        return ['tid' => $saved['tid'], 'row' => $saved['row']];
    }

    private function save(array $request, ?array $allowedOrganizationIds): array
    {
        $tid = (int) ($request['tid'] ?? 0);
        $name = trim((string) ($request['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('分组规则名称不能为空');
        }

        $orgId = (int) ($request['org_id'] ?? 0);
        if ($orgId < 1) {
            $orgId = 1;
        }
        if ($allowedOrganizationIds !== null && !in_array($orgId, $allowedOrganizationIds, true)) {
            throw new OutOfBoundsException('没有该组织的数据权限');
        }

        $conditions = $this->normalizeConditions($request['conditions'] ?? [], $orgId);
        if ($conditions === []) {
            throw new InvalidArgumentException('请至少添加一个分组条件');
        }

        $groupType = (string) ($request['group_type'] ?? 'order');
        if ($groupType === '') {
            $groupType = 'order';
        }

        $row = [
            'name' => $name,
            'memo' => trim((string) ($request['memo'] ?? '')),
            'weight' => max(0, (int) ($request['weight'] ?? 0)),
            'config' => $conditions,
            'group_type' => $groupType,
            'org_id' => $orgId,
        ];
        if ($tid > 0) {
            $row['tid'] = $tid;
        }

        $transaction = kernel::database()->beginTransaction();
        try {
            if ($this->orderTypeModel->save($row) === false) {
                throw new RuntimeException('分组规则保存失败');
            }

            $savedTid = (int) ($row['tid'] ?? 0);
            if ($savedTid < 1) {
                throw new RuntimeException('保存后未返回分组规则ID');
            }

            kernel::database()->commit($transaction);
        } catch (Throwable $exception) {
            kernel::database()->rollBack($transaction);
            throw $exception;
        }

        $savedRow = $this->orderTypeModel->dump(['tid' => $savedTid], '*');
        if (!is_array($savedRow)) {
            throw new RuntimeException('分组规则回读失败');
        }

        return ['tid' => $savedTid, 'row' => $savedRow];
    }

    private function normalizeConditions(mixed $conditions, int $orgId): array
    {
        if (!is_array($conditions) || $conditions === []) {
            return [];
        }

        $normalized = [];
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                throw new InvalidArgumentException('分组条件格式无效');
            }

            $role = strtolower((string) ($condition['role'] ?? ''));
            if (!in_array($role, omeauto_auto_type::$TYPE_LIST, true)) {
                throw new InvalidArgumentException('暂不支持该分组条件类型：' . ($role !== '' ? $role : 'empty'));
            }

            $content = $condition['content'] ?? null;
            $builder = sprintf('normalize%sCondition', $this->camelize($role));
            if (!method_exists($this, $builder)) {
                throw new InvalidArgumentException('分组条件实现缺失：' . $role);
            }

            $normalized[] = $this->{$builder}($content, $orgId);
        }

        return $normalized;
    }

    private function camelize(string $value): string
    {
        return str_replace('_', '', ucwords($value, '_'));
    }

    private function normalizeOrdertypeCondition(mixed $content, int $orgId): string
    {
        $values = array_values(array_unique(array_filter((array) ($content['order_type'] ?? []), static fn(mixed $v): bool => is_string($v) && $v !== '')));
        if ($values === []) {
            throw new InvalidArgumentException('订单类型条件至少需要选择一个订单类型');
        }

        $orderFunc = kernel::single('ome_order_func');
        $allowed = array_map('strval', $orderFunc->get_normal_order_type());
        $labels = array_merge(array_map('strval', $orderFunc->get_order_type()), [
            'presale' => '预售订单',
            'gift' => '赠品订单',
            'integral' => '积原子单',
            'staff' => '员工订单',
            'custom' => '定制订单',
        ]);
        $invalid = array_values(array_filter($values, static fn(string $value): bool => !in_array($value, $allowed, true)));
        if ($invalid !== []) {
            throw new InvalidArgumentException('不支持该订单类型：' . implode(',', $invalid));
        }

        $caption = implode(', ', array_map(static fn(string $value): string => $labels[$value] ?? $value, $values));
        return $this->encodeRole('ordertype', '订单类型为 ' . $caption, ['order_type' => $values]);
    }

    private function normalizeCodCondition(mixed $content, int $orgId): string
    {
        $value = $content === true ? 'true' : ($content === false ? 'false' : (string) $content);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException('货到付款条件只能是 true 或 false');
        }
        return $this->encodeRole('cod', $value === 'true' ? '货到付款订单' : '款到发货订单', $value);
    }

    private function normalizeShopmodeCondition(mixed $content, int $orgId): string
    {
        $mode = strtolower((string) ($content['type'] ?? ''));
        $labels = ['shop_self' => '直销店铺(默认)', 'shop_yjdf' => '一件代发店铺'];
        if (!isset($labels[$mode])) {
            throw new InvalidArgumentException('店铺发货模式无效，仅支持 shop_self/shop_yjdf');
        }
        return $this->encodeRole('shopmode', '店铺模式：' . $labels[$mode], ['type' => $mode]);
    }

    private function normalizeRangeCondition(mixed $content, int $orgId, string $role, array $labels, bool $moneyOnly = false): string
    {
        $type = (int) ($content['type'] ?? 0);
        $allowedTypes = $moneyOnly ? [1, 2, 3] : [1, 2, 3, 4];
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException($labels['type'] . '范围类型无效');
        }

        $min = (string) ($content['min'] ?? '');
        $max = (string) ($content['max'] ?? '');
        if ($min !== '' && !is_numeric($min)) {
            throw new InvalidArgumentException($labels['type'] . '最小值必须是数字');
        }
        if ($max !== '' && !is_numeric($max)) {
            throw new InvalidArgumentException($labels['type'] . '最大值必须是数字');
        }

        switch ($type) {
            case 1:
                $caption = sprintf($labels['less'], $max);
                break;
            case 2:
                $caption = sprintf($labels['greater'], $min);
                break;
            case 3:
                $caption = sprintf($labels['between'], $min, $max);
                break;
            default:
                $caption = sprintf($labels['equal'], $min);
        }

        return $this->encodeRole($role, $caption, ['min' => $min, 'max' => $max, 'type' => $type]);
    }

    private function normalizeWeightCondition(mixed $content, int $orgId): string
    {
        return $this->normalizeRangeCondition($content, $orgId, 'weight', [
            'type' => '订单商品总重量',
            'less' => '订单商品总重量小于 %s 克',
            'greater' => '订单商品总重量大于等于 %s 克',
            'between' => '订单商品总重量位于 %s 克(包含) - %s 克(不包含) 之间',
            'equal' => '订单商品总重量等于 %s 克',
        ]);
    }

    private function normalizeMoneyCondition(mixed $content, int $orgId): string
    {
        return $this->normalizeRangeCondition($content, $orgId, 'money', [
            'type' => '订单金额',
            'less' => '订单金额小于 %s 元',
            'greater' => '订单金额大于等于 %s 元',
            'between' => '订单金额位于 %s 元(包含) - %s 元(不包含) 之间',
            'equal' => '订单金额等于 %s 元',
        ], true);
    }

    private function normalizeItemnumCondition(mixed $content, int $orgId): string
    {
        return $this->normalizeRangeCondition($content, $orgId, 'itemnum', [
            'type' => '订单商品数',
            'less' => '订单商品数小于 %s 个',
            'greater' => '订单商品数大于等于 %s 个',
            'between' => '订单商品数位于 %s 个(包含) - %s 个(不包含) 之间',
            'equal' => '订单商品数等于 %s 个',
        ]);
    }

    private function normalizeSkunumCondition(mixed $content, int $orgId): string
    {
        return $this->normalizeRangeCondition($content, $orgId, 'skunum', [
            'type' => '订单商品种类数',
            'less' => '订单商品种类数小于 %s 个',
            'greater' => '订单商品种类数大于等于 %s 个',
            'between' => '订单商品种类数位于 %s 个(包含) - %s 个(不包含) 之间',
            'equal' => '订单商品种类数等于 %s 个',
        ]);
    }

    private function normalizeAddressCondition(mixed $content, int $orgId): string
    {
        $regionIds = array_values(array_unique(array_filter(
            array_map(static fn(mixed $id): int => (int) $id, (array) $content),
            static fn(int $id): bool => $id > 0
        )));
        if ($regionIds === []) {
            throw new InvalidArgumentException('收货地区条件至少需要选择一个地区');
        }

        $regions = $this->regionModel->getList('region_id,local_name', ['region_id' => $regionIds], 0, -1) ?: [];
        $byId = [];
        foreach ($regions as $region) {
            $byId[(int) $region['region_id']] = (string) $region['local_name'];
        }
        $missing = array_values(array_filter($regionIds, static fn(int $id): bool => !isset($byId[$id])));
        if ($missing !== []) {
            throw new InvalidArgumentException('收货地区不存在：' . implode(',', $missing));
        }

        $caption = implode(', ', array_map(static fn(int $id): string => $byId[$id], $regionIds));
        return $this->encodeRole('address', '收货区域在 ' . $caption, array_map('strval', $regionIds));
    }

    private function normalizeShopCondition(mixed $content, int $orgId): string
    {
        $shopIds = array_values(array_unique(array_filter(array_map('strval', (array) $content), static fn(string $id): bool => $id !== '')));
        if ($shopIds === []) {
            throw new InvalidArgumentException('来源店铺条件至少需要选择一个店铺');
        }

        $filter = ['s_type' => 1, 'shop_id' => $shopIds];
        if ($orgId > 0) {
            $filter['org_id'] = $orgId;
        }
        $rows = $this->shopModel->getList('shop_id,name', $filter, 0, -1) ?: [];
        $byId = [];
        foreach ($rows as $row) {
            $byId[(string) $row['shop_id']] = (string) $row['name'];
        }
        $missing = array_values(array_filter($shopIds, static fn(string $id): bool => !isset($byId[$id])));
        if ($missing !== []) {
            throw new InvalidArgumentException('来源店铺不存在或不属于该组织：' . implode(',', $missing));
        }

        $caption = implode(', ', array_map(static fn(string $id): string => $byId[$id], $shopIds));
        return $this->encodeRole('shop', '来自店铺 ' . $caption, $shopIds);
    }

    private function normalizePlatformCondition(mixed $content, int $orgId): string
    {
        $type = (string) ($content['type'] ?? '');
        $labels = array_merge(
            array_map('strval', ome_shop_type::get_shop_type()),
            ['haoshiqi_taobao' => '好食期-淘宝', 'haoshiqi_douyin' => '好食期-抖音', 'haoshiqi_kuaishou' => '好食期-快手']
        );
        if ($type === '' || (!isset($labels[$type]) && !$this->platformExists($type))) {
            throw new InvalidArgumentException('来源平台无效或当前环境不存在该平台类型');
        }

        $caption = '来自 ' . ($labels[$type] ?? $type) . ' 平台下的订单';
        return $this->encodeRole('platform', $caption, ['type' => $type]);
    }

    private function normalizeMemoCondition(mixed $content, int $orgId): string
    {
        $types = array_values(array_unique((array) ($content['memo_type'] ?? [])));
        $scope = (string) ($content['memo_scope'] ?? '');
        $text = trim((string) ($content['memo_text'] ?? ''));
        $validTypes = ['custom', 'shop'];
        if ($types === [] || array_diff($types, $validTypes) !== []) {
            throw new InvalidArgumentException('备注条件类型无效');
        }
        if (!in_array($scope, ['part', 'all'], true)) {
            throw new InvalidArgumentException('备注筛选范围无效');
        }
        if ($text === '') {
            throw new InvalidArgumentException('备注关键字不能为空');
        }

        $caption = '';
        if (in_array('custom', $types, true)) {
            $caption .= '买家备注';
        }
        if (in_array('shop', $types, true)) {
            $caption .= ($caption !== '' ? ',' : '') . '客服备注';
        }
        $caption .= $scope === 'part' ? '包含部分关键字' : '包含全部关键字';
        $caption .= '(' . $text . ')';
        return $this->encodeRole('memo', $caption, [
            'memo_type' => $types,
            'memo_scope' => $scope,
            'memo_text' => $text,
        ]);
    }

    private function normalizeSkuCondition(mixed $content, int $orgId): string
    {
        $skuText = trim((string) ($content['sku'] ?? ''));
        $skuRange = (string) ($content['sku_range'] ?? 'false');
        $start = (string) ($content['start'] ?? '');
        $end = (string) ($content['end'] ?? '');
        if ($skuText === '') {
            throw new InvalidArgumentException('SKU 条件至少需要一个商品编码');
        }
        if (!in_array($skuRange, ['true', 'false'], true)) {
            throw new InvalidArgumentException('SKU 匹配模式无效');
        }
        foreach (explode(',', $skuText) as $bn) {
            $singleBn = trim($bn);
            if ($singleBn === '' || preg_match('/^[0-9a-zA-Z_\/-]+$/', $singleBn) !== 1) {
                throw new InvalidArgumentException('物料编码是由数字英文下划线及横杠组成');
            }
        }
        $start = $start === '' || $start === '0' ? '' : $start;
        $end = $end === '' || $end === '0' ? '' : $end;
        $rangeCaption = $skuRange === 'false' ? '包含商品' : '仅有商品';
        $caption = $rangeCaption . ' ' . $skuText;
        if ($start !== '' || $end !== '') {
            $caption = ($start !== '' ? '从 ' . $start . ' 开始' : '') . ($end !== '' ? '到 ' . $end . ' 为止' : '') . ' ' . $rangeCaption . ' ' . $skuText;
        }
        return $this->encodeRole('sku', $caption, [
            'sku' => $skuText,
            'sku_range' => $skuRange,
            'start' => $start,
            'end' => $end,
        ]);
    }

    private function normalizeOrderhostCondition(mixed $content, int $orgId): string
    {
        $type = (string) ($content['type'] ?? '');
        $author = trim((string) ($content['author'] ?? ''));
        $room = trim((string) ($content['room'] ?? ''));
        if (!in_array($type, ['all', 'assign'], true)) {
            throw new InvalidArgumentException('达人条件范围无效');
        }
        if ($type === 'assign' && $author === '') {
            throw new InvalidArgumentException('指定达人订单时达人ID不能为空');
        }
        $caption = $type === 'all' ? '所有达人订单' : '指定达人订单 达人ID：' . $author . ($room !== '' ? ' 直播间ID：' . $room : '');
        return $this->encodeRole('orderhost', $caption, [
            'author' => $author,
            'room' => $room,
            'type' => $type,
        ]);
    }

    private function normalizeMaterialtypeCondition(mixed $content, int $orgId): string
    {
        $typeId = (string) ($content['material_type'] ?? '');
        $labels = kernel::single('material_basic_material')->get_material_types();
        if ($typeId === '' || !isset($labels[$typeId])) {
            throw new InvalidArgumentException('基础物料类型无效或不存在');
        }
        return $this->encodeRole('materialtype', '订单明细中包含 [' . $labels[$typeId] . '] 类型的基础物料', ['material_type' => $typeId]);
    }

    private function normalizeMaterialcatCondition(mixed $content, int $orgId): string
    {
        $catId = (int) ($content['cat_id'] ?? 0);
        $findType = (string) ($content['find_type'] ?? '');
        if ($catId < 1) {
            throw new InvalidArgumentException('基础物料分类不能为空');
        }
        if (!in_array($findType, ['include', 'not_include'], true)) {
            throw new InvalidArgumentException('基础物料分类筛选范围无效');
        }
        $row = $this->materialCatModel->dump(['cat_id' => $catId, 'disabled' => 'false'], 'cat_name');
        if (!is_array($row) || !isset($row['cat_name'])) {
            throw new InvalidArgumentException('基础物料分类不存在或已停用');
        }
        $caption = '订单明细中 [' . ($findType === 'include' ? '包含' : '不包含') . '] [' . $row['cat_name'] . '] 基础物料分类';
        return $this->encodeRole('materialcat', $caption, ['cat_id' => $catId, 'find_type' => $findType]);
    }

    private function normalizeCustomerCondition(mixed $content, int $orgId): string
    {
        $classId = (int) ($content['class_id'] ?? 0);
        if ($classId < 1) {
            throw new InvalidArgumentException('客户分类不能为空');
        }
        $row = $this->customerClassModel->dump(['class_id' => $classId, 'disabled' => 'false'], 'class_name');
        if (!is_array($row) || !isset($row['class_name'])) {
            throw new InvalidArgumentException('客户分类不存在或已停用');
        }
        return $this->encodeRole('customer', '订单明细中包含 [' . $row['class_name'] . '] 客户分类', ['class_id' => $classId]);
    }

    private function normalizeOrderlabelCondition(mixed $content, int $orgId): string
    {
        $labelIds = array_values(array_unique(array_filter(array_map('strval', is_array($content['label_id'] ?? null) ? $content['label_id'] : [$content['label_id'] ?? '']), static fn(string $id): bool => $id !== '')));
        if ($labelIds === []) {
            throw new InvalidArgumentException('订单标记条件至少需要选择一个标记');
        }
        $rows = $this->orderLabelModel->getList('label_id,label_name', ['label_id' => $labelIds], 0, -1) ?: [];
        $byId = [];
        foreach ($rows as $row) {
            $byId[(string) $row['label_id']] = (string) $row['label_name'];
        }
        $missing = array_values(array_filter($labelIds, static fn(string $id): bool => !isset($byId[$id])));
        if ($missing !== []) {
            throw new InvalidArgumentException('订单标记不存在：' . implode(',', $missing));
        }
        return $this->encodeRole('orderlabel', '订单标记 ' . implode('、', $byId), ['label_id' => $labelIds]);
    }

    private function platformExists(string $type): bool
    {
        $rows = kernel::database()->select("SELECT DISTINCT shop_type FROM sdb_ome_shop WHERE shop_type<>'' LIMIT 500") ?: [];
        foreach ($rows as $row) {
            if ((string) $row['shop_type'] === $type) {
                return true;
            }
        }
        return false;
    }

    private function encodeRole(string $role, string $caption, mixed $content): string
    {
        return json_encode([
            'role' => $role,
            'caption' => $caption,
            'content' => $content,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
