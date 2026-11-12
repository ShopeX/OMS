<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class console_capability_inventory_movement
{
    private object $iostockModel;
    private object $branchModel;
    private object $branchOpsModel;

    public function __construct(?object $iostockModel = null, ?object $branchModel = null, ?object $branchOpsModel = null)
    {
        $this->iostockModel = $iostockModel ?? app::get('ome')->model('iostock');
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
        $this->branchOpsModel = $branchOpsModel ?? app::get('ome')->model('branch_ops');
    }

    public function list(array $request, ?mcpapi_auth_context $context = null): array
    {
        $branchId = (int) ($request['branch_id'] ?? 0);
        $timeFrom = (int) ($request['time_from'] ?? 0);
        $timeTo = (int) ($request['time_to'] ?? 0);
        $bn = trim((string) ($request['bn'] ?? ''));
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['page_size'] ?? 100)));

        if ($branchId < 1) {
            throw new InvalidArgumentException('branch_id 必须为正整数');
        }
        if ($timeFrom < 1 || $timeTo < $timeFrom) {
            throw new InvalidArgumentException('time_from/time_to 必须为有效的 Unix epoch 秒范围');
        }
        if (strlen($bn) > 200) {
            throw new InvalidArgumentException('bn 长度不能超过 200 个字符');
        }

        $scope = $this->applyBranchScope(['branch_id' => $branchId], $context);
        if (($scope['branch_id'] ?? null) === 'false') {
            throw new InvalidArgumentException('当前 MCP 凭证无权操作该仓库');
        }
        if (is_array($scope['branch_id'] ?? null) && !in_array($branchId, array_map('intval', $scope['branch_id']), true)) {
            throw new InvalidArgumentException('当前 MCP 凭证无权操作该仓库');
        }

        $branch = $this->branchModel->dump($branchId, 'branch_id,branch_bn');
        if (!is_array($branch) || (int) ($branch['branch_id'] ?? 0) !== $branchId) {
            throw new InvalidArgumentException('请求仓库不存在');
        }

        $filter = [
            'branch_id' => $branchId,
            'create_time|between' => [$timeFrom, $timeTo],
        ];
        if ($bn !== '') {
            $filter['bn'] = $bn;
        }
        $offset = ($page - 1) * $pageSize;
        $rows = $this->iostockModel->getList('*', $filter, $offset, $pageSize, 'create_time asc, iostock_id asc');
        $total = method_exists($this->iostockModel, 'count') ? (int) $this->iostockModel->count($filter) : count($rows);

        $list = array_map(function (array $row) use ($branchId, $branch): array {
            $typeId = (int) ($row['type_id'] ?? 0);
            $direction = $this->resolveDirection($typeId);
            $quantity = (float) ($row['nums'] ?? 0);
            if (floor($quantity) === $quantity) {
                $quantity = (int) $quantity;
            }
            $signedQuantity = $direction === 'in' ? $quantity : -$quantity;

            return [
                'iostock_id' => (int) ($row['iostock_id'] ?? 0),
                'iostock_bn' => (string) ($row['iostock_bn'] ?? ''),
                'original_bn' => (string) ($row['original_bn'] ?? ''),
                'branch_id' => $branchId,
                'branch_bn' => (string) ($branch['branch_bn'] ?? ''),
                'material_bn' => (string) ($row['bn'] ?? ''),
                'type_id' => $typeId,
                'direction' => $direction,
                'quantity' => $quantity,
                'signed_quantity' => $signedQuantity,
                'create_time' => (int) ($row['create_time'] ?? 0),
            ];
        }, $rows);

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    private function resolveDirection(int $typeId): string
    {
        $types = null;
        if (property_exists($this->iostockModel, '_iostock_types')) {
            $types = $this->iostockModel->_iostock_types;
        }
        if (!is_array($types)) {
            $service = kernel::single('siso_receipt_iostock');
            $types = property_exists($service, '_iostock_types') ? $service->_iostock_types : null;
        }
        $type = is_array($types) ? ($types[(string) $typeId] ?? null) : null;
        if (!is_array($type) || !array_key_exists('io', $type)) {
            throw new InvalidArgumentException('OMS 出入库类型无效: ' . $typeId);
        }
        return (int) $type['io'] === 1 ? 'in' : 'out';
    }

    private function applyBranchScope(array $filter, ?mcpapi_auth_context $context): array
    {
        if ($context === null || $context->isSuper()) {
            return $filter;
        }
        $branchOps = $this->branchOpsModel->getList('branch_id', ['op_id' => $context->omsSubjectId()], 0, -1);
        $branchIds = array_values(array_unique(array_map('intval', array_column($branchOps, 'branch_id'))));
        if ($branchIds === []) {
            $filter['branch_id'] = 'false';
            return $filter;
        }
        $filter['branch_id'] = in_array((int) $filter['branch_id'], $branchIds, true) ? (int) $filter['branch_id'] : 'false';
        return $filter;
    }
}
