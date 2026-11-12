<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class omeauto_capability_autodispatch
{
    private object $model;
    private object $orderTypeModel;
    private object $database;

    public function __construct(?object $model = null, ?object $orderTypeModel = null, ?object $database = null)
    {
        $this->model = $model ?? app::get('omeauto')->model('autodispatch');
        $this->orderTypeModel = $orderTypeModel ?? app::get('omeauto')->model('order_type');
        $this->database = $database ?? kernel::database();
    }

    public function list(?array $allowedOrganizationIds): array
    {
        return $this->visibleRows($this->model->getList('*'), $allowedOrganizationIds);
    }

    public function detail(int $oid, ?array $allowedOrganizationIds): ?array
    {
        if ($oid < 1) {
            throw new InvalidArgumentException('规则ID无效');
        }

        $row = $this->model->dump(['oid' => $oid], '*');
        if (!is_array($row) || $this->visibleRows([$row], $allowedOrganizationIds) === []) {
            return null;
        }

        return $row;
    }

    public function orderTypes(?array $allowedOrganizationIds): array
    {
        return $this->visibleRows($this->orderTypeModel->getList('*'), $allowedOrganizationIds);
    }

    public function orderTypeDetail(int $tid, ?array $allowedOrganizationIds): ?array
    {
        if ($tid < 1) {
            throw new InvalidArgumentException('订单类型ID无效');
        }

        $row = $this->orderTypeModel->dump(['tid' => $tid], '*');
        if (!is_array($row) || $this->visibleRows([$row], $allowedOrganizationIds) === []) {
            return null;
        }

        return $row;
    }

    public function create(array $data, ?array $allowedOrganizationIds): array
    {
        return $this->persist($data, $allowedOrganizationIds);
    }

    public function update(int $oid, array $data, ?array $allowedOrganizationIds): array
    {
        if ($oid < 1) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }
        if ($this->detail($oid, $allowedOrganizationIds) === null) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }

        $data['oid'] = $oid;
        return $this->persist($data, $allowedOrganizationIds);
    }

    public function setStatus(int $oid, string $status, ?array $allowedOrganizationIds): array
    {
        if ($oid < 1) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }
        if ($this->detail($oid, $allowedOrganizationIds) === null) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }
        if (!in_array($status, ['true', 'false'], true)) {
            throw new InvalidArgumentException('状态无效');
        }

        $transaction = $this->database->beginTransaction();
        try {
            $disabled = $status === 'true' ? 'false' : 'true';
            $this->executeQuery("update sdb_omeauto_autodispatch set disabled='{$disabled}' where oid={$oid}");
            $this->database->commit($transaction);

            return ['affected_rule_oids' => [$oid], 'affected_order_type_tids' => []];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    public function setDefault(int $oid, ?array $allowedOrganizationIds): array
    {
        if ($oid < 1) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }
        $current = $this->detail($oid, $allowedOrganizationIds);
        if ($current === null) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }

        $transaction = $this->database->beginTransaction();
        try {
            $config = is_array($current['config'] ?? null) ? $current['config'] : [];
            unset($config['autoOrders']);

            $this->executeQuery("update sdb_omeauto_autodispatch set defaulted='false'");
            $this->executeQuery("update sdb_omeauto_order_type set did=0 where did={$oid}");
            if ($this->model->update(['defaulted' => 'true', 'config' => $config], ['oid' => $oid]) === false) {
                throw new RuntimeException('规则默认状态保存失败');
            }
            $this->database->commit($transaction);

            return ['affected_rule_oids' => [$oid], 'affected_order_type_tids' => []];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    public function removeDefault(int $oid, ?array $allowedOrganizationIds): array
    {
        if ($oid < 1) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }
        if ($this->detail($oid, $allowedOrganizationIds) === null) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }

        $transaction = $this->database->beginTransaction();
        try {
            $this->executeQuery("update sdb_omeauto_autodispatch set defaulted='false' where oid={$oid}");
            $this->database->commit($transaction);

            return ['affected_rule_oids' => [$oid], 'affected_order_type_tids' => []];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    private function persist(array $data, ?array $allowedOrganizationIds): array
    {
        $oid = (int) ($data['oid'] ?? 0);
        $orgId = (int) ($data['org_id'] ?? 0);
        if ($allowedOrganizationIds !== null && $orgId > 0 && !in_array($orgId, $allowedOrganizationIds, true)) {
            throw new OutOfBoundsException('没有该组织的数据权限');
        }

        if ($oid > 0 && $this->detail($oid, $allowedOrganizationIds) === null) {
            throw new OutOfBoundsException('规则不存在或没有组织权限');
        }

        $transaction = $this->database->beginTransaction();
        try {
            if ($oid > 0) {
                $this->executeQuery("update sdb_omeauto_order_type set did=0 where did={$oid}");
            }

            $data['config'] = is_array($data['config'] ?? null) ? $data['config'] : [];
            $data['op_id'] = $data['op_id'] ?? null;
            $data['org_id'] = $orgId > 0 ? $orgId : 1;
            if ($this->model->save($data) === false) {
                throw new RuntimeException('保存失败');
            }

            $oid = (int) ($data['oid'] ?? 0);
            if ($oid < 1) {
                throw new RuntimeException('保存后未返回规则ID');
            }

            if ($oid > 0 && empty($data['config']['autoOrders'])) {
                $this->executeQuery("update sdb_omeauto_order_type set did=0 where did={$oid}");
            }

            $linked = [];
            foreach (array_values(array_unique(array_map('intval', (array) ($data['config']['autoOrders'] ?? [])))) as $tid) {
                if ($tid < 1) {
                    continue;
                }
                $this->executeQuery("update sdb_omeauto_order_type set did={$oid} where tid={$tid}");
                $linked[] = $tid;
            }

            $this->database->commit($transaction);
            return ['oid' => $oid, 'affected_rule_oids' => [$oid], 'affected_order_type_tids' => $linked];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    private function visibleRows(array $rows, ?array $allowedOrganizationIds): array
    {
        if ($allowedOrganizationIds === null) {
            return $rows;
        }

        $permissions = array_map('strval', $allowedOrganizationIds);
        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => in_array((string) ($row['org_id'] ?? ''), $permissions, true)
        ));
    }

    private function executeQuery(string $sql): void
    {
        if ($this->database->query($sql) === false) {
            throw new RuntimeException('数据库写入失败');
        }
    }
}
