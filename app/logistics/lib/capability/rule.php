<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class logistics_capability_rule
{
    private object $ruleModel;
    private object $branchModel;
    private object $shopModel;
    private object $dlyCorpModel;
    private object $branchRuleModel;
    private object $ruleObjModel;
    private object $database;

    public function __construct(
        ?object $ruleModel = null,
        ?object $branchModel = null,
        ?object $shopModel = null,
        ?object $dlyCorpModel = null,
        ?object $branchRuleModel = null,
        ?object $ruleObjModel = null,
        ?object $database = null,
    ) {
        $this->ruleModel = $ruleModel ?? app::get('logistics')->model('rule');
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
        $this->shopModel = $shopModel ?? app::get('ome')->model('shop');
        $this->dlyCorpModel = $dlyCorpModel ?? app::get('ome')->model('dly_corp');
        $this->branchRuleModel = $branchRuleModel ?? app::get('logistics')->model('branch_rule');
        $this->ruleObjModel = $ruleObjModel ?? app::get('logistics')->model('rule_obj');
        $this->database = $database ?? kernel::database();
    }

    public function list(): array
    {
        return ['rows' => $this->ruleModel->getList('*', [], 0, -1, 'rule_id DESC') ?: []];
    }

    public function detail(int $ruleId): ?array
    {
        if ($ruleId < 1) {
            throw new InvalidArgumentException('规则ID不能为空');
        }

        $rule = $this->ruleModel->detailRule($ruleId, 1);
        return is_array($rule) && $rule !== [] ? $rule : null;
    }

    public function branches(): array
    {
        return ['rows' => $this->branchModel->getList('branch_id,name,branch_bn', [], 0, -1, 'name ASC') ?: []];
    }

    public function corpList(int $branchId): array
    {
        if ($branchId < 1) {
            throw new InvalidArgumentException('仓库ID不能为空');
        }

        $branchInfo = $this->branchModel->db_dump(['branch_id' => $branchId, 'check_permission' => 'false'], 'b_type');
        if ($branchInfo && (string) $branchInfo['b_type'] === '2') {
            $rows = $this->dlyCorpModel->getList('corp_id,name,type,weight,tmpl_type,channel_id,d_type,corp_model', ['disabled' => 'false']) ?: [];
        } else {
            $rows = $this->branchModel->get_corp($branchId, '') ?: [];
        }

        $rows[] = ['corp_id' => '-1', 'name' => '人工审单'];
        return ['rows' => $rows];
    }

    public function shops(int $branchId = 0): array
    {
        $shops = $this->shopModel->getList('name,shop_id', [], 0, -1, 'name ASC') ?: [];
        if ($branchId > 0) {
            $existing = $this->branchRuleModel->getList('*', ['branch_id' => $branchId]) ?: [];
            $hasShopIds = array_values(array_map('intval', array_column($existing, 'shop_id')));
            if ($hasShopIds) {
                foreach ($shops as &$shop) {
                    if (in_array((int) ($shop['shop_id'] ?? 0), $hasShopIds, true)) {
                        $shop['disabled'] = true;
                    }
                }
                unset($shop);
            }
        }

        return ['rows' => $shops];
    }

    public function checkRuleName(string $ruleName, int $branchId): array
    {
        if (trim($ruleName) === '' || $branchId < 1) {
            throw new InvalidArgumentException('参数不完整');
        }

        $exists = $this->ruleModel->getList('rule_id', ['rule_name' => trim($ruleName), 'branch_id' => $branchId]) ?: [];
        return ['exists' => $exists !== [], 'message' => $exists !== [] ? '规则名称已存在' : ''];
    }

    public function save(array $data): array
    {
        $transaction = $this->database->beginTransaction();
        try {
            $ruleId = (int) ($data['rule_id'] ?? 0);
            if ($ruleId > 0) {
                $this->ruleModel->editRule($data);
            } else {
                $ruleId = (int) $this->ruleModel->createRule($data);
            }

            if ($ruleId < 1) {
                throw new RuntimeException('保存后未返回规则ID');
            }

            $this->database->commit($transaction);
            return ['rule_id' => $ruleId, 'row' => $this->ruleModel->detailRule($ruleId, 1)];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }
}
