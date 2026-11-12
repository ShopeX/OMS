<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class omeauto_capability_autoconfirm
{
    private object $model;
    private object $orderTypeModel;
    private object $database;

    public function __construct(?object $model = null, ?object $orderTypeModel = null, ?object $database = null)
    {
        $this->model = $model ?? app::get('omeauto')->model('autoconfirm');
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
            throw new InvalidArgumentException('规则ID或状态无效');
        }

        $transaction = $this->database->beginTransaction();
        try {
            $disabled = $status === 'true' ? 'false' : 'true';
            $this->executeQuery("update sdb_omeauto_autoconfirm set disabled='{$disabled}' where oid={$oid}");
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

            $this->executeQuery("update sdb_omeauto_autoconfirm set defaulted='false'");
            $this->executeQuery("update sdb_omeauto_order_type set oid=0 where oid={$oid}");
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
            $this->executeQuery("update sdb_omeauto_autoconfirm set defaulted='false' where oid={$oid}");
            $this->database->commit($transaction);

            return ['affected_rule_oids' => [$oid], 'affected_order_type_tids' => []];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    private function persist(array $data, ?array $allowedOrganizationIds): array
    {
        $error = '';
        if (!$this->formatConfirmData($data, $error)) {
            throw new InvalidArgumentException($error);
        }

        $oid = (int) ($data['oid'] ?? 0);
        $transaction = $this->database->beginTransaction();
        try {
            if ($oid > 0) {
                if ($this->detail($oid, $allowedOrganizationIds) === null) {
                    throw new OutOfBoundsException('规则不存在或没有组织权限');
                }
                $this->executeQuery("update sdb_omeauto_order_type set oid=0 where oid={$oid}");
            }

            if ($this->model->save($data) === false) {
                throw new RuntimeException('保存失败');
            }
            $oid = (int) ($data['oid'] ?? 0);
            if ($oid < 1) {
                throw new RuntimeException('保存后未返回规则ID');
            }

            if ($oid > 0 && empty($data['config']['autoOrders'])) {
                $this->executeQuery("update sdb_omeauto_order_type set oid=0 where oid={$oid}");
            }

            $linked = [];
            foreach (array_values(array_unique(array_map('intval', (array) ($data['config']['autoOrders'] ?? [])))) as $tid) {
                if ($tid < 1) {
                    continue;
                }
                $this->executeQuery("update sdb_omeauto_order_type set oid={$oid} where tid={$tid}");
                $linked[] = $tid;
            }

            $this->database->commit($transaction);
            return ['oid' => $oid, 'linked_order_type_tids' => $linked];
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
            static fn(array $row): bool => !array_key_exists('org_id', $row) || in_array((string) $row['org_id'], $permissions, true)
        ));
    }

    private function formatConfirmData(array &$data, string &$error): bool
    {
        if (empty($data['confirmStartTime']) || empty($data['confirmEndTime'])) {
            $error = '请设置生效时间范围';
            return false;
        }

        $confirmStartTime = strtotime(
            $data['confirmStartTime'] . ' ' . ($data['_DTIME_']['H']['confirmStartTime'] ?? '') . ':' . ($data['_DTIME_']['M']['confirmStartTime'] ?? '')
        );
        $confirmEndTime = strtotime(
            $data['confirmEndTime'] . ' ' . ($data['_DTIME_']['H']['confirmEndTime'] ?? '') . ':' . ($data['_DTIME_']['M']['confirmEndTime'] ?? '')
        );

        if (empty($confirmStartTime) || empty($confirmEndTime)) {
            $error = '生效时间范围设置错误,请检查';
            return false;
        }

        $excludeStartTime = 0;
        $excludeEndTime = 0;
        if (!empty($data['excludeStartTime']) && !empty($data['excludeEndTime'])) {
            $excludeStartTime = strtotime(
                $data['excludeStartTime'] . ' ' . ($data['_DTIME_']['H']['excludeStartTime'] ?? '') . ':' . ($data['_DTIME_']['M']['excludeStartTime'] ?? '')
            );
            $excludeEndTime = strtotime(
                $data['excludeEndTime'] . ' ' . ($data['_DTIME_']['H']['excludeEndTime'] ?? '') . ':' . ($data['_DTIME_']['M']['excludeEndTime'] ?? '')
            );
        }

        if (empty($excludeStartTime) || empty($excludeEndTime)) {
            $excludeStartTime = 0;
            $excludeEndTime = 0;
        }

        if ($confirmStartTime > $confirmEndTime) {
            $error = '(生效时间范围)开始时间不能大于结束时间';
            return false;
        }
        if (($confirmEndTime - $confirmStartTime) < 3600) {
            $error = '(生效时间范围)时间范围不能小于1个小时';
            return false;
        }

        if ($excludeStartTime && $excludeEndTime) {
            if ($excludeStartTime > $excludeEndTime) {
                $error = '(排除时间范围)开始时间不能大于结束时间';
                return false;
            }
            if (($excludeEndTime - $excludeStartTime) < 3600) {
                $error = '(排除时间范围)时间范围不能小于1个小时';
                return false;
            }
            if ($excludeStartTime <= $confirmStartTime) {
                $error = '排除时间范围不得早于生效时间范围';
                return false;
            }
            if ($excludeEndTime >= $confirmEndTime) {
                $error = '排除时间范围不得晚于生效时间范围';
                return false;
            }
        }

        $data['config']['confirmStartTime'] = $confirmStartTime;
        $data['config']['confirmEndTime'] = $confirmEndTime;
        if ($excludeStartTime && $excludeEndTime) {
            $data['config']['excludeStartTime'] = $excludeStartTime;
            $data['config']['excludeEndTime'] = $excludeEndTime;
        } else {
            unset($data['config']['excludeStartTime'], $data['config']['excludeEndTime']);
        }

        unset($data['confirmStartTime'], $data['confirmEndTime'], $data['excludeStartTime'], $data['excludeEndTime'], $data['_DTIME_'], $data['_DTYPE_TIME']);

        return true;
    }

    private function executeQuery(string $sql): void
    {
        if ($this->database->query($sql) === false) {
            throw new RuntimeException('数据库写入失败');
        }
    }
}
