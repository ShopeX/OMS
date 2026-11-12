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
 * WITHOUT WARRANTIES OF ANY CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

/**
 * 虚拟仓 callback 配置（omevirtualwms / admin_wms#callback_config）读写 capability
 *
 * 单一实现：legacy 控制器 `omevirtualwms_ctl_admin_wms::callback_config()`
 * （app/omevirtualwms/controller/admin/wms.php:1052-1081）与 Vue JSON api
 * `omevirtualwms_ctl_api_wms` 共用本类，避免两处漂移。
 *
 * 语义严格对齐 legacy：
 *  - kvstore 前缀 `omevirtualwms`、键 `callback` 存整个 $callback 数组；
 *    业务消费者 `omevirtualwms_callback::call()`（app/omevirtualwms/lib/callback.php:41）
 *    读取 `async.rsp` / `sync.rsp` / `res` / `err_msg`（`status` 仅本页与 network 同步用）。
 *  - 保存成功后按 `status` 同步 `sdb_base_network.node_url`（pkey `node_id`）：
 *      '0' 内网 → http://rpc.ex-sandbox.com                     （node_id=1）
 *      '2' 外网 → MATRIX_URL                                    （node_id=1）
 *      else 模拟 → {base_url}/index.php/openapi/virtualwms/call （link_status=active）
 *
 * ⚠️ legacy 对取值不做任何校验（枚举由表单 select/radio 约束）⇒ 本 capability
 *    只做**结构归一化**（按键名收拢并转字符串），不新增比 legacy 更严的值域校验
 *    —— 迁移是等价重建，不是功能改版。
 * ⚠️ kvstore 与 conf 均无事务能力 ⇒ 保存前快照、异常时回滚 kvstore 与 network 整组写。
 */
class omevirtualwms_capability_callback
{
    /** 单据状态可选值（对齐 legacy view/callback_config.html 的 select） */
    public const RES_OPTIONS = [
        ''       => '请选择',
        'w03101' => '单据号不存在',
        'w03102' => '单据已发货',
        'w03103' => '单据已取消',
        'w03104' => '单据已关闭',
        'w03105' => '单据号重复',
        'w03106' => '单据已收货',
        'w03107' => '退货单原始订单号不对',
        'w03108' => '单据不能撤销',
        'w03020' => '第三方仓储无此接口',
    ];

    /** 异步接口状态（对齐 legacy select） */
    public const ASYNC_OPTIONS = ['running' => '运行中', 'fail' => '失败'];

    /** 同步接口状态（对齐 legacy select） */
    public const SYNC_OPTIONS = ['succ' => '成功', 'fail' => '失败'];

    /** CALLBACK模拟（legacy 模板仅渲染 value=2 外网；0/1 已注释，但控制器仍保留分支） */
    public const STATUS_OPTIONS = ['2' => '外网'];

    private const KVSTORE_PREFIX = 'omevirtualwms';
    private const KVSTORE_KEY    = 'callback';

    /**
     * 读取配置（legacy 渲染与 api 读 act 共用）
     *
     * @return array{callback:array,statusOptions:array,asyncOptions:array,syncOptions:array,resOptions:array}
     */
    public function load(): array
    {
        return [
            'callback'      => $this->fetchCallback(),
            'statusOptions' => self::STATUS_OPTIONS,
            'asyncOptions'  => self::ASYNC_OPTIONS,
            'syncOptions'   => self::SYNC_OPTIONS,
            'resOptions'    => self::RES_OPTIONS,
        ];
    }

    /**
     * 保存配置（legacy POST 与 api save act 共用）
     *
     * @param array $callback 结构同 legacy $_POST['callback']
     *
     * @return array 归一化后的配置
     *
     * @throws InvalidArgumentException 入参为空
     * @throws Throwable                持久化失败（kvstore 与 network 已回滚）
     */
    public function save(array $callback): array
    {
        if (empty($callback)) {
            throw new InvalidArgumentException('参数错误：缺少配置数据');
        }

        $normalized = $this->normalize($callback);
        $status     = (string) $normalized['status'];

        // 1) 快照（kvstore 与 DB 更新无共同事务 ⇒ 异常回滚整组写）
        $oldRaw       = $this->fetchRaw();
        $networkModel = app::get('base')->model('network');
        $networkRows  = $this->snapshotNetworkRows($networkModel, $status);

        // 2) 持久化
        try {
            $this->storeRaw($normalized);
            $this->syncNetworkNodeUrl($networkModel, $status);
        } catch (Throwable $exception) {
            $this->storeRaw($oldRaw);
            foreach ($networkRows as $row) {
                $networkModel->update(['node_url' => $row['node_url']], ['node_id' => $row['node_id']]);
            }
            throw $exception;
        }

        return $normalized;
    }

    /**
     * 结构归一化（只收拢 legacy 模板会提交的 5 个键，值一律转字符串）
     */
    private function normalize(array $callback): array
    {
        return [
            'status'  => (string) ($callback['status'] ?? ''),
            'async'   => ['rsp' => (string) ($callback['async']['rsp'] ?? '')],
            'sync'    => ['rsp' => (string) ($callback['sync']['rsp'] ?? '')],
            'res'     => (string) ($callback['res'] ?? ''),
            'err_msg' => (string) ($callback['err_msg'] ?? ''),
        ];
    }

    /**
     * 读取原始值（未命中时为 null）
     *
     * @return mixed
     */
    private function fetchRaw()
    {
        $raw = null;
        base_kvstore::instance(self::KVSTORE_PREFIX)->fetch(self::KVSTORE_KEY, $raw);

        return $raw;
    }

    /**
     * 读取配置数组（非数组一律回空数组，对齐 legacy 的容错）
     */
    private function fetchCallback(): array
    {
        $raw = $this->fetchRaw();

        return is_array($raw) ? $raw : [];
    }

    /**
     * 写 kvstore（null 时删除键，保证回滚到「未设置」态）
     *
     * @param mixed $value
     */
    private function storeRaw($value): void
    {
        $kvstore = base_kvstore::instance(self::KVSTORE_PREFIX);
        if ($value === null) {
            $kvstore->delete(self::KVSTORE_KEY);

            return;
        }

        $kvstore->store(self::KVSTORE_KEY, $value);
    }

    /**
     * 记录将被本次保存改动的 network 行（按 legacy 同款 where 条件）
     *
     * @return array<int,array{node_id:string,node_url:string}>
     */
    private function snapshotNetworkRows(object $networkModel, string $status): array
    {
        $filter = ($status === '0' || $status === '2') ? ['node_id' => '1'] : ['link_status' => 'active'];
        $rows   = $networkModel->getList('node_id,node_url', $filter);

        return is_array($rows) ? $rows : [];
    }

    /**
     * 按 legacy 语义同步异步回调地址
     */
    private function syncNetworkNodeUrl(object $networkModel, string $status): void
    {
        if ($status === '0') {
            // 内网（异步）
            $networkModel->update(['node_url' => 'http://rpc.ex-sandbox.com'], ['node_id' => '1']);

            return;
        }

        if ($status === '2') {
            // 外网（异步）
            $networkModel->update(['node_url' => MATRIX_URL], ['node_id' => '1']);

            return;
        }

        // 模拟
        $networkModel->update(
            ['node_url' => kernel::base_url(1) . '/index.php/openapi/virtualwms/call'],
            ['link_status' => 'active']
        );
    }
}
