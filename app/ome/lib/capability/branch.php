<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_branch
{
    private object $branchModel;
    private object $branchCorpModel;
    private object $shopModel;
    private object $branchService;
    private object $dlyCorpModel;
    private array $shopBranchRelationship;

    public function __construct(
        ?object $branchModel = null,
        ?object $branchCorpModel = null,
        ?object $shopModel = null,
        ?object $branchService = null,
        ?object $dlyCorpModel = null,
        ?array $shopBranchRelationship = null
    ) {
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
        $this->branchCorpModel = $branchCorpModel ?? app::get('ome')->model('branch_corp');
        $this->shopModel = $shopModel ?? app::get('ome')->model('shop');
        $this->branchService = $branchService ?? kernel::single('ome_branch');
        $this->dlyCorpModel = $dlyCorpModel ?? app::get('ome')->model('dly_corp');
        $this->shopBranchRelationship = $shopBranchRelationship ?? (array) app::get('ome')->getConf('shop.branch.relationship');
    }

    public function list(): array
    {
        $rows = $this->branchModel->getList('*', ['b_type' => 1], 0, -1, 'wms_id asc');
        return ['rows' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows)];
    }

    public function detail(int $branchId): ?array
    {
        if ($branchId < 1) {
            throw new InvalidArgumentException('缺少仓库ID');
        }

        $branch = $this->branchModel->dump($branchId, '*');
        if (!is_array($branch) || $branch === []) {
            return null;
        }

        if (!empty($branch['area_conf']) && is_string($branch['area_conf'])) {
            $areaConf = @unserialize($branch['area_conf']);
            $branch['area_conf'] = $areaConf === false ? $branch['area_conf'] : $areaConf;
        }

        $branch['cutoff_time_hour'] = $branch['cutoff_time'] ? intval(substr((string) $branch['cutoff_time'], 0, 2)) : 0;
        $branch['cutoff_time_minute'] = $branch['cutoff_time'] ? intval(substr((string) $branch['cutoff_time'], 2, 2)) : 0;
        $branch['latest_delivery_time_hour'] = $branch['latest_delivery_time'] ? intval(substr((string) $branch['latest_delivery_time'], 0, 2)) : 0;
        $branch['latest_delivery_time_minute'] = $branch['latest_delivery_time'] ? intval(substr((string) $branch['latest_delivery_time'], 2, 2)) : 0;

        $selectedCorps = $this->branchCorpModel->getList('corp_id', ['branch_id' => $branchId]);
        $branch['selected_corp_ids'] = array_map('intval', array_column($selectedCorps, 'corp_id'));
        $branch['shop_bns'] = $this->shopBnsForBranch((string) ($branch['branch_bn'] ?? ''));
        $branch['corps'] = $this->dlyCorpModel->getList('*', ['d_type' => 1, 'disabled' => 'false']);

        return $branch;
    }

    public function wmsList(): array
    {
        $list = array_values(array_filter(
            $this->branchService->getWmsChannelList(),
            static fn(array $item): bool => !empty($item['node_id'])
        ));
        return ['list' => $list];
    }

    public function branchTypes(): array
    {
        return ['list' => $this->branchService->getBranchTypes()];
    }

    public function shopList(): array
    {
        return ['list' => $this->shopModel->getList('*', ['s_type' => 1, 'delivery_mode' => 'self'], 0, -1)];
    }

    public function corpList(): array
    {
        return ['list' => $this->dlyCorpModel->getList('*', ['disabled' => 'false']) ?: []];
    }

    private function normalizeListRow(array $row): array
    {
        return [
            'branch_id' => (int) ($row['branch_id'] ?? 0),
            'branch_bn' => (string) ($row['branch_bn'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'wms_id' => (int) ($row['wms_id'] ?? 0),
            'type' => (string) ($row['type'] ?? ''),
            'is_deliv_branch' => (string) ($row['is_deliv_branch'] ?? ''),
        ];
    }

    private function shopBnsForBranch(string $branchBn): array
    {
        if ($branchBn === '') {
            return [];
        }

        $shopBns = [];
        foreach ($this->shopBranchRelationship as $shop => $branches) {
            if (is_array($branches) && in_array($branchBn, $branches, true)) {
                $shopBns[] = (string) $shop;
            }
        }
        return $shopBns;
    }
}
