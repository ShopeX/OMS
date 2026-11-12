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

declare(strict_types=1);

/**
 * 费用项设置（financebase / shop_settlement_feetype）写能力层 —— 唯一认可的写实现。
 *
 * 链路：
 *   Vue `ctl=api_shop_settlement_fee_type&act=save|saveWhiteList`（JSON body）
 *     → financebase_ctl_api_shop_settlement_fee_type → 本 capability（事务归属自动判定）
 *
 * legacy 控制器自带内联实现（迁移前形态，本层不承接、不改动 legacy）。
 *
 * 校验 / 归一化 / 持久化 / 事务全部收敛在此层；**权限判定在控制器层**（api 控制器 `#[RequirePermission]`），
 * 本层只做「校验 + 归一化 + 持久化 + 事务」。
 * 行为逐字对齐 legacy save():71-104 / setWhiteList():108-114 / saveWhiteList():117-131 /
 * add():42-53（表单静态数据）。
 */
class financebase_capability_shop_settlement_fee_type
{
    /** @var object */
    private $feeTypeModel;

    /** @var object */
    private $shopModel;

    /** @var object */
    private $ruleModel;

    /** @var object */
    private $database;

    public function __construct($feeTypeModel = null, $shopModel = null, $ruleModel = null, $db = null)
    {
        $this->feeTypeModel = $feeTypeModel ?: app::get('financebase')->model('bill_fee_type');
        $this->shopModel    = $shopModel ?: app::get('ome')->model('shop');
        $this->ruleModel    = $ruleModel ?: app::get('financebase')->model('bill_category_rules');
        $this->database     = $db ?: kernel::database();
    }

    /**
     * 新增表单静态数据（legacy add():42-53 —— $shop_list 下拉 + `bill_category_rules@financebase`
     * object 输入的候选（filter need_ar_verify=1）+ 资金流向 `bill_type` 两个固定项）。
     *
     * @return array{shop_list:list<array>,rule_list:list<array>,bill_type_options:list<array>}
     */
    public function formData(): array
    {
        $shopList = financebase_func::getShopList(financebase_func::getShopType());

        $shops = [];
        foreach ((array) $shopList as $shop) {
            $shopId   = (string) ($shop['shop_id'] ?? '');
            $shopType = (string) ($shop['shop_type'] ?? '');
            $shops[] = [
                // legacy 模板 option value="<shop_id>_<shop_type>"（fee_type.html:26）⇒ save 时 explode('_') 取 [0]
                'value'     => $shopId . '_' . $shopType,
                'shop_id'   => $shopId,
                'shop_type' => $shopType,
                'label'     => (string) ($shop['name'] ?? ''),
            ];
        }

        // legacy fee_type.html:35 <{input type="object" object="bill_category_rules@financebase" filter="need_ar_verify=1" ...}>
        $ruleList = $this->ruleModel->getList('rule_id,bill_category', ['need_ar_verify' => 1]);
        $rules = [];
        foreach ((array) $ruleList as $rule) {
            $rules[] = [
                'rule_id' => (int) ($rule['rule_id'] ?? 0),
                'label'   => (string) ($rule['bill_category'] ?? ''),
            ];
        }

        return [
            'shop_list'         => $shops,
            'rule_list'         => $rules,
            // legacy fee_type.html:43-46 <select name="bill_type"><option value="0">收入</option><option value="1">支出</option>
            'bill_type_options' => [
                ['label' => '收入', 'value' => 0],
                ['label' => '支出', 'value' => 1],
            ],
        ];
    }

    /**
     * 白名单表单回填（legacy setWhiteList():108-114）。
     *
     * @return array{data:array}
     */
    public function whiteListData(int $feeTypeId): array
    {
        $info = $this->feeTypeModel->getList('fee_type_id,whitelist', ['fee_type_id' => $feeTypeId], 0, 1);
        $row  = is_array($info) && isset($info[0]) ? $info[0] : [];

        return [
            'data' => [
                'fee_type_id' => $feeTypeId,
                'whitelist'   => (string) ($row['whitelist'] ?? ''),
            ],
        ];
    }

    /**
     * 新增费用项（legacy save():71-104）。
     *
     * @param array<string,mixed> $input 与 legacy $_POST 同形：shop_id("<shop_id>_<shop_type>")/rule_id/bill_type/fee_type
     *
     * @return array{message:string,id:int}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息 = legacy 文案）
     */
    public function save(array $input): array
    {
        $shopArray = explode('_', (string) ($input['shop_id'] ?? ''));
        $shop      = $this->shopModel->db_dump(['shop_id' => $shopArray[0]], 'node_type');

        $shopTypeMap = ome_shop_type::get_shop_type();

        $data = [];
        $data['shop_id']       = $shopArray[0];
        $data['fee_type']      = (string) ($input['fee_type'] ?? '');
        $data['rule_id']       = !empty($input['rule_id']) ? (int) $input['rule_id'] : null;
        $data['platform_type'] = (string) ($shopTypeMap[$shop['node_type'] ?? ''] ?? '');
        $data['bill_type']     = (int) ($input['bill_type'] ?? 0);
        $data['createtime']    = time();

        // legacy:86-88 具体类别必填
        if (empty($data['rule_id'])) {
            throw new \RuntimeException('具体类别需填写');
        }

        // legacy:89-93 同一 shop_id + rule_id 唯一
        $filter = ['shop_id' => $data['shop_id'], 'rule_id' => $data['rule_id']];
        if ($this->feeTypeModel->getList('fee_type_id', $filter)) {
            throw new \RuntimeException('费用项已存在');
        }

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            if (!$this->feeTypeModel->save($data)) {
                throw new \RuntimeException('保存失败');
            }
            // dbeav_model::save(&$data) 按引用回填自增主键
            $feeTypeId = (int) $data['fee_type_id'];
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            $this->commit();
        }

        return ['message' => '保存成功', 'id' => $feeTypeId];
    }

    /**
     * 保存白名单（legacy saveWhiteList():117-131）。
     *
     * @param array<string,mixed> $input 与 legacy $_POST 同形：fee_type_id/whitelist
     *
     * @return array{message:string}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息 = legacy 文案）
     */
    public function saveWhiteList(array $input): array
    {
        $feeTypeId = (int) ($input['fee_type_id'] ?? 0);
        $whitelist = (string) ($input['whitelist'] ?? '');

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            if (!$this->feeTypeModel->update(['whitelist' => $whitelist], ['fee_type_id' => $feeTypeId])) {
                throw new \RuntimeException('保存失败');
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            $this->commit();
        }

        return ['message' => '保存成功'];
    }

    /**
     * 事务归属：按连接状态自动判定（外层已开事务则由外层托管，如 desktop_controller::begin()/end()）。
     *
     * `connections::beginTransaction()` 不抛异常：已在事务中时直接 `return false`，
     * 故必须检查返回值；失败即复位并抛错。
     *
     * @return bool 本方法是否拥有事务（true ⇒ 调用方负责 commit/rollBack）
     */
    private function beginTransactionIfOwned(): bool
    {
        if ($this->database->isInTransaction()) {
            return false;
        }

        if (false === $this->database->beginTransaction()) {
            $this->database->rollBack();
            throw new \RuntimeException('开启事务失败');
        }

        return true;
    }

    private function commit(): void
    {
        $this->database->commit();
    }

    private function rollBack(): void
    {
        $this->database->rollBack();
    }
}
