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
 * 代发销售商品（dealer/admin_material_sales）共享写能力
 *
 * 单一写实现，两条入口共用同一份逻辑：
 *  - Vue JSON 入口：dealer_ctl_api_material_sales::save()（act=save，JSON body）
 *  - legacy 入口：dealer_ctl_admin_material_sales::toAdd() / toEdit()（表单 AJAX，仅做传输适配）
 *
 * 语义与 legacy 原实现等价（app/dealer/controller/admin/material/sales.php:177-275 toAdd、
 * :406-517 toEdit）：
 *  - 校验**复用 legacy 同一套规则** dealer_sales_check::checkParams()（新增：编码正则 + (bn,shop_id)
 *    唯一；编辑：sm_id 必填 + 排除自身唯一）
 *  - cos_id 取所属店铺的 cos_id（编辑时店铺必须有 cos_id，否则「店铺企业组织架构ID无效」）
 *  - 关联基础商品按类型：1 普通(bm_id，number=1) / 2 组合促销(at[bm_id]=number + pr[bm_id]=rate，
 *    且必须全部有效) / 3 赠品(at[bm_id]=number)
 *  - is_bind：新增有关联置 1（无关联保持默认）；编辑有关联置 1、**无关联置 2**（legacy 原语义）
 *  - 操作日志：新增 dealer_sm_add@dealer「销售商品添加」/ 编辑 dealer_sm_edit@dealer「更新销售商品」，
 *    编辑同时落 operation_log_snapshoot 快照（改前主表行 + 关联行）
 */
final class dealer_capability_material_sales
{
    private object $salesMaterialModel;

    private object $salesBasicMaterialModel;

    private object $basicMaterialModel;

    private object $shopModel;

    private object $database;

    public function __construct(
        ?object $salesMaterialModel = null,
        ?object $salesBasicMaterialModel = null,
        ?object $basicMaterialModel = null,
        ?object $shopModel = null,
        ?object $database = null
    ) {
        $this->salesMaterialModel      = $salesMaterialModel ?? app::get('dealer')->model('sales_material');
        $this->salesBasicMaterialModel = $salesBasicMaterialModel ?? app::get('dealer')->model('sales_basic_material');
        $this->basicMaterialModel      = $basicMaterialModel ?? app::get('material')->model('basic_material');
        $this->shopModel               = $shopModel ?? app::get('ome')->model('shop');
        $this->database                = $database ?? kernel::database();
    }

    /**
     * 编辑回填：主表 + 已关联基础商品（Vue 弹窗 / legacy act=edit 同数据）
     *
     * @return array{sm_id:int,sales_material_bn:string,sales_material_name:string,
     *               sales_material_type:int,shop_id:string,is_bind:int,bindings:array}
     */
    public function loadMaterial(int $smId): array
    {
        if ($smId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        // 注意：框架 dump(数组条件) 返回「行集」（list 或 PK 键），传标量主键才返回单行 ⇒ 三种形态都兼容
        $rows = $this->salesMaterialModel->dump(['sm_id' => $smId], '*');
        $row  = null;
        if (is_array($rows)) {
            if (isset($rows['sm_id'])) {
                $row = $rows;
            } else {
                foreach ($rows as $candidate) {
                    if (is_array($candidate) && (int) ($candidate['sm_id'] ?? 0) === $smId) {
                        $row = $candidate;
                        break;
                    }
                }
            }
        }
        if (empty($row)) {
            throw new InvalidArgumentException('销售商品不存在');
        }

        return [
            'sm_id'               => (int) $row['sm_id'],
            'sales_material_bn'   => (string) $row['sales_material_bn'],
            'sales_material_name' => (string) $row['sales_material_name'],
            'sales_material_type' => (int) $row['sales_material_type'],
            'shop_id'             => (string) $row['shop_id'],
            // 编辑回填用：店铺下拉已改 remote（无预置候选），必须带出店铺名，否则前端只显示裸 shop_id
            'shop_name'           => (string) ((($shopRow = $this->shopModel->db_dump(['shop_id' => (string) $row['shop_id']])) && isset($shopRow['name'])) ? $shopRow['name'] : ''),
            'is_bind'             => (int) ($row['is_bind'] ?? 0),
            'bindings'            => $this->loadBindings($smId),
        ];
    }

    /**
     * 店铺候选
     * 大表保护：硬 LIMIT（禁 getList(...,0,-1) 全量返回整表）；传了关键词则前缀匹配。
     */
    public function loadShops(string $keyword = '', array $cosIds = [], int $limit = 50): array
    {
        $keyword = trim($keyword);
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }

        $where = "delivery_mode = 'shopyjdf'";
        if ($cosIds) {
            $where .= ' AND cos_id IN (' . implode(',', array_map('intval', $cosIds)) . ')';
        }
        if ($keyword !== '') {
            $where .= ' AND name LIKE ' . $this->database->quote(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%');
        }

        $sql   = 'SELECT shop_id, name FROM sdb_ome_shop WHERE ' . $where . ' ORDER BY shop_id DESC LIMIT ' . (int) $limit;
        $shops = [];
        foreach ((array) $this->database->select($sql) as $row) {
            $shops[] = ['shop_id' => (string) $row['shop_id'], 'name' => (string) $row['name']];
        }

        return $shops;
    }

    /**
     * 新建 / 编辑保存
     *
     * @param array $input sm_id(编辑时必填) / sales_material_bn / sales_material_name /
     *                     sales_material_type / shop_id / bm_id(类型1) / at(类型2、3) / pr(类型2)
     * @param bool  $ownTransaction legacy 入口事务由 desktop_controller::begin() 托管，传 false
     *
     * @return array{sm_id:int,message:string}
     */
    public function saveMaterial(array $input, bool $ownTransaction = true): array
    {
        $smId   = (int) ($input['sm_id'] ?? 0);
        $isEdit = $smId > 0;
        $type   = (int) ($input['sales_material_type'] ?? 0);

        // ① 校验：直接复用 legacy 校验器，两个入口共用同一套规则与文案
        $params         = $input;
        $params['edit'] = $isEdit; // legacy 用 edit 标识区分新增/编辑分支
        $errMsg         = '';
        $checkLib       = kernel::single('dealer_sales_check');
        if (!$checkLib->checkParams($params, $errMsg)) {
            throw new InvalidArgumentException($errMsg !== '' ? $errMsg : '参数校验失败');
        }

        // ② 归一关联基础商品（含类型 2 的有效性校验，与 legacy 等价）
        $bindings = $this->normalizeBindings($input, $type);

        // ③ cos_id 取所属店铺（编辑时必须有 cos_id）
        $shopId   = (string) ($input['shop_id'] ?? '');
        $shopInfo = $this->shopModel->db_dump(['shop_id' => $shopId]);
        if ($isEdit && (empty($shopInfo) || empty($shopInfo['cos_id']))) {
            throw new InvalidArgumentException('店铺企业组织架构ID无效');
        }
        $cosId  = $shopInfo['cos_id'] ?? 0;
        $opInfo = kernel::single('ome_func')->getDesktopUser();

        $transaction = $ownTransaction ? $this->database->beginTransaction() : null;
        try {
            $snapshoot = [];
            if ($isEdit) {
                $snapshoot = [
                    'sdb_dealer_sales_material'       => $this->salesMaterialModel->db_dump(['sm_id' => $smId]),
                    'sdb_dealer_sales_basic_material' => $this->salesBasicMaterialModel->getList('*', ['sm_id' => $smId]),
                ];

                $updated = $this->salesMaterialModel->update([
                    'sales_material_name' => (string) ($input['sales_material_name'] ?? ''),
                    'sales_material_type' => $type,
                    'shop_id'             => $shopId,
                    'cos_id'              => $cosId,
                    'last_modified'       => time(),
                ], ['sm_id' => $smId]);
                if ($updated === false) {
                    throw new RuntimeException('保存失败');
                }

                // 重做关联：先删后插（legacy 原语义）
                $this->salesBasicMaterialModel->delete(['sm_id' => $smId]);
                $this->applyBindings($smId, $bindings);
                // legacy：编辑时有关联 is_bind=1，无关联 is_bind=2
                $this->salesMaterialModel->update(['is_bind' => $bindings ? 1 : 2], ['sm_id' => $smId]);

                $operation = 'dealer_sm_edit@dealer';
                $logInfo   = '更新销售商品';
            } else {
                $data = [
                    'sales_material_bn'   => (string) ($input['sales_material_bn'] ?? ''),
                    'sales_material_name' => (string) ($input['sales_material_name'] ?? ''),
                    'sales_material_type' => $type,
                    'shop_id'             => $shopId,
                    'cos_id'              => $cosId,
                    'op_name'             => (string) ($opInfo['op_name'] ?? ''),
                ];
                $isSave = $this->salesMaterialModel->save($data);
                if (!$isSave) {
                    throw new RuntimeException('保存失败');
                }
                $smId = (int) ($data['sm_id'] ?? 0);
                if ($smId <= 0) {
                    $created = $this->salesMaterialModel->getList(
                        'sm_id',
                        ['sales_material_bn' => $data['sales_material_bn'], 'shop_id' => $shopId],
                        0,
                        1
                    );
                    $smId = (int) ($created[0]['sm_id'] ?? 0);
                }
                if ($smId <= 0) {
                    throw new RuntimeException('保存失败');
                }

                $this->applyBindings($smId, $bindings);
                if ($bindings) {
                    $this->salesMaterialModel->update(['is_bind' => 1], ['sm_id' => $smId]);
                }

                $operation = 'dealer_sm_add@dealer';
                $logInfo   = '销售商品添加';
            }

            // 操作日志 +（编辑）改前快照
            $logModel = app::get('ome')->model('operation_log');
            $logId    = $logModel->write_log($operation, $smId, $logInfo);
            if ($logId && $snapshoot) {
                $shootMdl = app::get('ome')->model('operation_log_snapshoot');
                // 框架 insert(&$data) 按引用传参：必须先用变量（PHP 8 传字面量直接致命）
                $shootData = [
                    'log_id'    => $logId,
                    'snapshoot' => json_encode($snapshoot, JSON_UNESCAPED_UNICODE),
                ];
                $shootMdl->insert($shootData);
            }

            if ($ownTransaction) {
                $this->database->commit($transaction);
            }
        } catch (Throwable $exception) {
            if ($ownTransaction) {
                $this->database->rollBack($transaction);
            }
            throw $exception;
        }

        return [
            'sm_id'   => $smId,
            'message' => $isEdit ? '保存成功' : '操作成功',
        ];
    }

    /**
     * 已关联基础商品（弹窗回填 + 详情 tab 均可复用）
     */
    private function loadBindings(int $smId): array
    {
        $rows  = (array) $this->salesBasicMaterialModel->getList('*', ['sm_id' => $smId]);
        $bmIds = array_values(array_unique(array_column($rows, 'bm_id')));

        // 编辑回填必须带出编码/名称，否则前端只能显示裸 bm_id（客户不友好）
        $bmMap = [];
        if ($bmIds) {
            $bmMap = $this->basicMaterialModel->getList('bm_id, material_bn, material_name', ['bm_id|in' => $bmIds]);
            $bmMap = array_column((array) $bmMap, null, 'bm_id');
        }

        $items = [];
        foreach ($rows as $row) {
            $bm = $bmMap[$row['bm_id']] ?? [];

            $items[] = [
                'bm_id'       => (int) $row['bm_id'],
                'material_bn' => (string) ($bm['material_bn'] ?? ''),
                // 与选择器下发的行结构保持一致（前端读 row.name）
                'name'        => (string) ($bm['material_name'] ?? ''),
                'number'      => (int) $row['number'],
                'rate'        => $row['rate'] !== null ? (string) $row['rate'] : '',
            ];
        }

        return $items;
    }

    /**
     * 关联基础商品归一（等价 legacy toAdd/toEdit 的三分支）
     *
     * @return array<int,array{number:int,rate:float|string|null}> bm_id => 关联行
     */
    private function normalizeBindings(array $input, int $type): array
    {
        $items = [];

        if ($type === 1 && !empty($input['bm_id'])) {
            $bmId = (int) $input['bm_id'];
            if (!$this->basicMaterialModel->dump(['bm_id' => $bmId], '*')) {
                throw new InvalidArgumentException('基础商品无效');
            }
            $items[$bmId] = ['number' => 1, 'rate' => null];
        } elseif ($type === 2 && !empty($input['at'])) {
            $at = (array) $input['at'];
            $pr = (array) ($input['pr'] ?? []);
            $found = $this->basicMaterialModel->getList('bm_id', ['bm_id|in' => array_keys($at)]);
            if (!$found || count((array) $found) !== count(array_keys($at))) {
                throw new InvalidArgumentException('基础商品无效');
            }
            foreach ($at as $bmId => $number) {
                $items[(int) $bmId] = [
                    'number' => (int) $number,
                    'rate'   => $pr[$bmId] ?? 0,
                ];
            }
        } elseif ($type === 3 && !empty($input['at'])) {
            foreach ((array) $input['at'] as $bmId => $number) {
                $items[(int) $bmId] = ['number' => (int) $number, 'rate' => null];
            }
        }

        return $items;
    }

    /**
     * 落关联行（rate 为 null 时不写该列，等价 legacy 类型 1/3 只写 number）
     */
    private function applyBindings(int $smId, array $bindings): void
    {
        foreach ($bindings as $bmId => $item) {
            $data = [
                'sm_id'  => $smId,
                'bm_id'  => (int) $bmId,
                'number' => (int) $item['number'],
            ];
            if ($item['rate'] !== null) {
                $data['rate'] = $item['rate'];
            }
            $this->salesBasicMaterialModel->insert($data);
        }
    }
}
