<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_shop
{
    private object $shopModel;
    private object $orgModel;
    private object $shopService;
    private object $authConfig;
    private object $shopPropsModel;

    public function __construct(
        ?object $shopModel = null,
        ?object $orgModel = null,
        ?object $shopService = null,
        ?object $authConfig = null,
        ?object $shopPropsModel = null
    ) {
        $this->shopModel = $shopModel ?? app::get('ome')->model('shop');
        $this->orgModel = $orgModel ?? app::get('ome')->model('operation_organization');
        $this->shopService = $shopService ?? kernel::single('ome_shop');
        $this->authConfig = $authConfig ?? kernel::single('ome_auth_config');
        $this->shopPropsModel = $shopPropsModel ?? app::get('ome')->model('shop_props');
    }

    public function list(?array $organizationIds = null): array
    {
        if ($organizationIds !== null && $organizationIds === []) {
            return ['rows' => []];
        }

        $filter = [
            's_type' => 1,
            'delivery_mode|notin' => 'shopyjdf',
        ];
        if ($organizationIds !== null) {
            $filter['org_id|in'] = array_values(array_unique(array_map('strval', $organizationIds)));
        }
        $cols = 'shop_id,shop_bn,name,shop_type,node_id,s_status,delivery_mode,org_id,last_download_time,aoxiang_signed';
        $rows = $this->shopModel->getList($cols, $filter, 0, -1, 'shop_id DESC');
        return ['rows' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows)];
    }

    public function detail(int $shopId, ?array $organizationIds = null): ?array
    {
        if ($shopId < 1) {
            throw new InvalidArgumentException('店铺ID不能为空');
        }

        $row = $this->shopModel->dump($shopId);
        if (!is_array($row) || $row === []) {
            return null;
        }
        if (!$this->canAccessShopRow($row, $organizationIds)) {
            return null;
        }

        return $row;
    }

    public function addForm(): array
    {
        return $this->buildFormConfig(null);
    }

    public function editForm(int $shopId, ?array $organizationIds = null): ?array
    {
        if ($shopId < 1) {
            throw new InvalidArgumentException('店铺ID不能为空');
        }

        $shop = $this->detail($shopId, $organizationIds);
        if (!is_array($shop) || $shop === []) {
            return null;
        }

        return $this->buildFormConfig($shopId);
    }

    private function canAccessShopRow(array $row, ?array $organizationIds): bool
    {
        if ($organizationIds === null) {
            return true;
        }
        if ($organizationIds === []) {
            return false;
        }

        $allowedOrganizationIds = array_values(array_unique(array_map('strval', $organizationIds)));
        $rowOrganizationId = (string) ($row['org_id'] ?? '');

        return $rowOrganizationId !== '' && in_array($rowOrganizationId, $allowedOrganizationIds, true);
    }

    private function buildFormConfig(?int $shopId): array
    {
        $shop = $this->buildDefaultShop();
        $props = [];

        if ($shopId !== null) {
            $shop = array_merge($shop, $this->shopModel->dump($shopId) ?: []);
            $config = @unserialize($shop['config'] ?? '');
            if (!is_array($config)) {
                $config = [];
            }
            if (!empty($config['password']) && method_exists($this->shopModel, 'aes_decode')) {
                $config['password'] = $this->shopModel->aes_decode($config['password']);
            }
            if (!isset($config['adapter']) && !empty($shop['node_id'])) {
                $config['adapter'] = 'matrixonline';
            }
            $shop['config'] = $config;

            foreach ($this->shopPropsModel->getlist('*', ['shop_id' => $shopId]) as $item) {
                $props[$item['props_col']] = $item['props_value'];
            }
        }

        $customcols = $this->shopService->getcols();
        foreach ($customcols as &$customcol) {
            $key = $customcol['col_key'] ?? '';
            $customcol['col_value'] = $props[$key] ?? '';
        }
        unset($customcol);

        return [
            'shop' => $shop,
            'props' => $props,
            'customcols' => $customcols,
            'options' => [
                'shopTypes' => $this->formatAssocOptions(ome_shop_type::get_shop_type()),
                'orgs' => $this->formatOrgOptions($this->orgModel->getList('*', [], 0, -1)),
                'adapters' => $this->authConfig->getAdapterList(),
                'deliveryModes' => [
                    ['value' => 'self', 'label' => '自发'],
                    ['value' => 'jingxiao', 'label' => '经销'],
                ],
                'businessCategories' => [
                    ['value' => 'B2C', 'label' => 'B2C'],
                    ['value' => 'B2B', 'label' => 'B2B'],
                ],
            ],
        ];
    }

    private function buildDefaultShop(): array
    {
        return [
            'shop_id' => '',
            'shop_bn' => '',
            'old_shop_bn' => '',
            'name' => '',
            'shop_type' => '',
            'org_id' => '',
            'area' => '',
            'addr' => '',
            'zip' => '',
            'default_sender' => '',
            'tel' => '',
            'mobile' => '',
            'delivery_mode' => 'self',
            'business_category' => 'B2C',
            'node_id' => '',
            'shop_type_alias' => '',
            'config' => [
                'url' => '',
                'account' => '',
                'password' => '',
                'adapter' => 'matrixonline',
            ],
        ];
    }

    private function formatAssocOptions(array $items): array
    {
        $options = [];
        foreach ($items as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => (string) $label];
        }
        return $options;
    }

    private function formatOrgOptions(array $items): array
    {
        $options = [];
        foreach ($items as $item) {
            $options[] = ['value' => (string) $item['org_id'], 'label' => (string) $item['name']];
        }
        return $options;
    }

    private function normalizeListRow(array $row): array
    {
        return [
            'shop_id' => (int) ($row['shop_id'] ?? 0),
            'shop_bn' => (string) ($row['shop_bn'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'shop_type' => (string) ($row['shop_type'] ?? ''),
            'node_id' => (string) ($row['node_id'] ?? ''),
            's_status' => (string) ($row['s_status'] ?? ''),
            'delivery_mode' => (string) ($row['delivery_mode'] ?? ''),
            'org_id' => (int) ($row['org_id'] ?? 0),
            'last_download_time' => $row['last_download_time'] ?? null,
            'aoxiang_signed' => $row['aoxiang_signed'] ?? null,
        ];
    }
}
