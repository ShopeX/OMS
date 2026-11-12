<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class omeauto_capability_autobranchset
{
    private object $orderTypeModel;
    private object $branchModel;
    private object $autobranchModel;
    private object $autobranchgetModel;
    private object $regionModel;
    private object $database;
    private object $conditionEncoder;

    public function __construct(
        ?object $orderTypeModel = null,
        ?object $branchModel = null,
        ?object $database = null,
        ?object $autobranchModel = null,
        ?object $regionModel = null,
        ?object $autobranchgetModel = null,
        ?object $conditionEncoder = null
    ) {
        $this->orderTypeModel = $orderTypeModel ?? app::get('omeauto')->model('order_type');
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
        $this->database = $database ?? kernel::database();
        $this->autobranchModel = $autobranchModel ?? app::get('omeauto')->model('autobranch');
        $this->regionModel = $regionModel ?? app::get('eccommon')->model('regions');
        $this->autobranchgetModel = $autobranchgetModel ?? app::get('omeauto')->model('autobranchget');
        $this->conditionEncoder = $conditionEncoder ?? new omeauto_capability_order_type();
    }

    public function list(): array
    {
        return ['rows' => $this->database->select(
            "SELECT ot.tid,ot.oid,ot.did,ot.config,ot.name,ot.memo,ot.weight,ot.delivery_group,ot.group_type,ot.disabled,bt.bid,bt.is_default
             FROM sdb_omeauto_order_type AS ot
             LEFT JOIN sdb_omeauto_autobranch AS bt ON ot.tid=bt.tid
             WHERE ot.group_type='branch'
             ORDER BY bt.is_default DESC,ot.weight DESC,bt.weight DESC,ot.tid ASC"
        ) ?: []];
    }

    public function detail(int $tid): ?array
    {
        if ($tid < 1) {
            throw new InvalidArgumentException('仓库分组规则ID无效');
        }

        $row = $this->orderTypeModel->dump(['tid' => $tid], '*');
        if (!is_array($row) || ($row['group_type'] ?? '') !== 'branch') {
            return null;
        }

        $row['branch_bindings'] = $this->database->select("SELECT tid,bid,weight,is_default FROM sdb_omeauto_autobranch WHERE tid={$tid} ORDER BY is_default DESC, weight DESC, bid ASC") ?: [];
        $row['branch_rules'] = $this->database->select("SELECT tid,classify,weight FROM sdb_omeauto_autobranchget WHERE tid={$tid} ORDER BY weight DESC") ?: [];
        return $row;
    }

    public function branches(): array
    {
        $rows = $this->branchModel->getList('branch_id,name,branch_bn', ['disabled' => 'false', 'is_deliv_branch' => 'true'], 0, -1, 'name ASC') ?: [];
        $rows[] = ['branch_id' => -1, 'name' => '参加O2O的门店', 'branch_bn' => ''];
        return ['rows' => $rows];
    }

    public function save(array $data): array
    {
        $tid = (int) ($data['tid'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('分组名称不能为空');
        }

        $orgId = (int) ($data['org_id'] ?? 0);
        $existingOrgId = 0;
        if ($tid > 0 && $orgId < 1) {
            $existing = $this->orderTypeModel->dump(['tid' => $tid], 'org_id,group_type');
            if (is_array($existing)) {
                $existingOrgId = (int) ($existing['org_id'] ?? 0);
            }
        }
        if ($orgId < 1 && $existingOrgId > 0) {
            $orgId = $existingOrgId;
        }

        $conditions = $this->conditionEncoder->normalizeGroupConditions(
            $data['conditions'] ?? [],
            $orgId > 0 ? $orgId : 0
        );
        $bindings = $this->normalizeBindings($data['branch_bindings'] ?? []);
        $branchRules = $this->normalizeBranchRules($data['branch_rules'] ?? []);

        $row = [
            'tid' => $tid > 0 ? $tid : null,
            'name' => $name,
            'memo' => (string) ($data['memo'] ?? ''),
            'weight' => (int) ($data['weight'] ?? 0),
            'group_type' => 'branch',
        ];
        if ($tid < 1) {
            $row['org_id'] = $orgId > 0 ? $orgId : 1;
        } elseif (array_key_exists('org_id', $data)) {
            $row['org_id'] = $orgId > 0 ? $orgId : 1;
        }
        if ($conditions !== []) {
            $row['config'] = $conditions;
        }

        $transaction = $this->database->beginTransaction();
        try {
            if ($this->orderTypeModel->save($row) === false) {
                throw new RuntimeException('保存失败');
            }

            $savedTid = (int) ($row['tid'] ?? 0);
            if ($savedTid < 1) {
                throw new RuntimeException('保存后未返回规则ID');
            }

            if ($bindings !== []) {
                $this->replaceBindings($savedTid, $bindings);
            }
            if ($branchRules !== []) {
                $this->replaceBranchRules($savedTid, $branchRules);
            }

            $this->database->commit($transaction);
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }

        $saved = $this->orderTypeModel->dump(['tid' => $savedTid], '*');
        if (!is_array($saved)) {
            throw new RuntimeException('仓库规则回读失败');
        }
        $saved['branch_bindings'] = $this->database->select("SELECT tid,bid,weight,is_default FROM sdb_omeauto_autobranch WHERE tid={$savedTid} ORDER BY is_default DESC, weight DESC, bid ASC") ?: [];
        $saved['branch_rules'] = $this->database->select("SELECT tid,classify,weight FROM sdb_omeauto_autobranchget WHERE tid={$savedTid} ORDER BY weight DESC") ?: [];

        return ['tid' => $savedTid, 'row' => $saved];
    }

    private function normalizeBranchRules(mixed $branchRules): array
    {
        if (!is_array($branchRules) || $branchRules === []) {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($branchRules as $rule) {
            if (!is_array($rule)) {
                throw new InvalidArgumentException('仓库选择策略格式无效');
            }

            $classify = strtolower(trim((string) ($rule['classify'] ?? '')));
            if (!in_array($classify, ['router', 'cost'], true)) {
                throw new InvalidArgumentException('暂不支持该仓库选择策略：' . ($classify !== '' ? $classify : 'empty'));
            }
            if (isset($seen[$classify])) {
                throw new InvalidArgumentException('仓库选择策略不能重复：' . $classify);
            }

            $weight = (int) ($rule['weight'] ?? 0);
            if ($weight < 0 || $weight > 255) {
                throw new InvalidArgumentException('仓库选择策略权重需要在 0-255 之间');
            }

            $seen[$classify] = true;
            $normalized[] = [
                'classify' => $classify,
                'weight' => $weight,
            ];
        }

        return $normalized;
    }

    private function normalizeBindings(mixed $bindings): array
    {
        if (!is_array($bindings) || $bindings === []) {
            return [];
        }

        $normalized = [];
        $defaultCount = 0;
        foreach ($bindings as $binding) {
            if (!is_array($binding)) {
                throw new InvalidArgumentException('仓库绑定格式无效');
            }

            $branchId = (int) ($binding['branch_id'] ?? 0);
            if ($branchId === 0 || $branchId < -1) {
                throw new InvalidArgumentException('仓库绑定ID无效');
            }
            $isDefault = !empty($binding['is_default']);
            if ($isDefault) {
                $defaultCount++;
            }
            $normalized[] = [
                'bid' => $branchId,
                'weight' => max(0, (int) ($binding['weight'] ?? 0)),
                'is_default' => $isDefault,
            ];
        }

        if ($defaultCount !== 1) {
            throw new InvalidArgumentException('仓库规则必须且只能设置一个默认仓库');
        }

        $branchIds = array_values(array_unique(array_filter(array_column($normalized, 'bid'), static fn(int $id): bool => $id > 0)));
        $knownBranches = [];
        if ($branchIds !== []) {
            $rows = $this->branchModel->getList('branch_id,is_ctrl_store', ['branch_id' => $branchIds], 0, -1) ?: [];
            $knownBranches = array_column($rows, 'is_ctrl_store', 'branch_id');
            $missing = array_values(array_filter($branchIds, static fn(int $id): bool => !array_key_exists($id, $knownBranches)));
            if ($missing !== []) {
                throw new InvalidArgumentException('绑定的仓库不存在：' . implode(',', $missing));
            }

            $storeModes = array_values(array_unique(array_map('strval', $knownBranches)));
            if (count($storeModes) > 1) {
                throw new InvalidArgumentException('绑定的仓库不能同时包含管控库存和不管控库存');
            }
        }

        return $normalized;
    }

    private function replaceBindings(int $tid, array $bindings): void
    {
        $this->database->exec("DELETE FROM sdb_omeauto_autobranch WHERE tid={$tid}");

        $defaultBid = 0;
        foreach ($bindings as $binding) {
            $bindingRow = [
                'tid' => $tid,
                'bid' => $binding['bid'],
                'weight' => $binding['weight'],
                'is_default' => $binding['is_default'] ? '1' : '0',
            ];
            if ($this->autobranchModel->save($bindingRow) === false) {
                throw new RuntimeException('仓库绑定保存失败');
            }
            if ($binding['is_default']) {
                $defaultBid = $binding['bid'];
            }
        }

        $update = ['bid' => $defaultBid];
        if ($this->orderTypeModel->update($update, ['tid' => $tid]) === false) {
            throw new RuntimeException('默认仓库保存失败');
        }
    }

    private function replaceBranchRules(int $tid, array $rules): void
    {
        $this->database->exec("DELETE FROM sdb_omeauto_autobranchget WHERE tid={$tid}");

        foreach ($rules as $rule) {
            $ruleRow = [
                'tid' => $tid,
                'classify' => $rule['classify'],
                'weight' => $rule['weight'],
            ];
            if ($this->autobranchgetModel->insert($ruleRow) === false) {
                throw new RuntimeException('仓库选择策略保存失败');
            }
        }
    }
}
