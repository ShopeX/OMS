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
 * 第三方仓储管理（wmsmgr / admin_wms）共享能力
 *
 * 单一实现，两条入口共用同一份逻辑：
 *  - Vue JSON 入口：wmsmgr_ctl_api_wms::*（act=save/deleteChannel/saveDetail* / bind* / doSyncBase）
 *  - legacy 入口：wmsmgr_ctl_admin_wms::saveWms()/deleteChannel()/doInitializeLogi()
 *                与 wmsmgr_finder_wms 的 detail_* $_POST 分支（事务由 desktop begin()/end() 托管，传 false）
 *
 * 底座：sdb_channel_channel（模型 wmsmgr_mdl_wms，channel_type='wms'），
 * 关联表 express_relation / branch_relation / supplier_relation，配置写 finance/wmsmgr getConf/setConf。
 *
 * 校验 / 归一化 / 持久化 / 事务只此一份；失败抛异常（controller 捕获转 fail(...,422)）。
 */
class wmsmgr_capability_wms
{
    private const CHANNEL_TYPE = 'wms';

    private object $wmsModel;

    private object $adapterModel;

    private object $database;

    public function __construct(?object $wmsModel = null, ?object $adapterModel = null, ?object $database = null)
    {
        // kernel::single('wmsmgr_capability_wms') 会按类名首段把 app::get('wmsmgr') 注入首参
        // （app/base/kernel.php::single），此处必须识别丢弃，否则 app 对象会被当 model 用
        if ($wmsModel instanceof app) {
            $wmsModel = null;
        }
        $this->wmsModel     = $wmsModel ?? app::get('wmsmgr')->model('wms');
        $this->adapterModel = $adapterModel ?? app::get('channel')->model('adapter');
        $this->database     = $database ?? kernel::database();
    }

    /**
     * 编辑回填（legacy `_edit($wms_id)`）：wms 权威行 + 适配器清单。
     *
     * @return array{wms:array,adapterList:array}
     */
    public function loadWms(int $channelId): array
    {
        if ($channelId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $row = $this->wmsModel->dump(
            ['channel_id' => $channelId, 'channel_type' => self::CHANNEL_TYPE],
            '*'
        );
        if (empty($row)) {
            throw new InvalidArgumentException('第三方仓储不存在');
        }

        $config = @unserialize((string) ($row['config'] ?? ''));
        if (!is_array($config)) {
            $config = [];
        }

        $adapter = kernel::single('wmsmgr_func')->getAdapterByChannelId($channelId);

        return [
            'wms' => [
                'channel_id'   => (int) $row['channel_id'],
                'channel_name' => (string) ($row['channel_name'] ?? ''),
                'channel_bn'   => (string) ($row['channel_bn'] ?? ''),
                'channel_type' => (string) ($row['channel_type'] ?? self::CHANNEL_TYPE),
                'node_id'      => (string) ($row['node_id'] ?? ''),
                'node_type'    => (string) ($row['node_type'] ?? ''),
                'config'       => $config,
                'adapter'      => (string) ($adapter ?? ''),
                'api_url'      => (string) app::get('wmsmgr')->getConf('api_url' . ($row['node_id'] ?? '')),
                'shipper'      => (string) ($row['shipper'] ?? ''),
            ],
            'adapterList' => kernel::single('wmsmgr_auth_config')->getAdapterList(),
        ];
    }

    /**
     * 新建 / 编辑保存（逐字对齐 legacy `saveWms()` 的校验/落库语义）。
     *
     * @param array $input          wms{channel_id?,channel_bn,channel_name,adapter} / config / shipper / adapter(原值) / divisionCode
     * @param bool  $ownTransaction legacy 入口事务由 desktop begin()/end() 托管，传 false
     *
     * @return array{id:int,message:string}
     */
    public function saveWms(array $input, bool $ownTransaction = true): array
    {
        $wmsInput = is_array($input['wms'] ?? null) ? $input['wms'] : [];
        $config   = is_array($input['config'] ?? null) ? $input['config'] : [];

        $adapter     = trim((string) ($wmsInput['adapter'] ?? ''));
        $channelId   = (int) ($wmsInput['channel_id'] ?? 0);
        $channelBn   = trim((string) ($wmsInput['channel_bn'] ?? ''));
        $channelName = trim((string) ($wmsInput['channel_name'] ?? ''));

        $data = [
            'channel_name' => $channelName,
            'channel_type' => self::CHANNEL_TYPE,
        ];
        if ($channelId > 0) {
            $data['channel_id'] = $channelId;
        }
        if ($channelBn !== '') {
            $data['channel_bn'] = $channelBn;
        }

        $channelInfo = $channelId > 0 ? $this->wmsModel->dump(['channel_id' => $channelId], '*') : [];
        if (!is_array($channelInfo)) {
            $channelInfo = [];
        }

        // 绑定关系已存在时不可切换仓储适配器（legacy 同款守卫）
        if ($channelId > 0 && !empty($channelInfo['node_id'])) {
            $currentAdapter = trim((string) kernel::single('wmsmgr_func')->getAdapterByChannelId($channelId));
            if ($adapter !== $currentAdapter) {
                throw new InvalidArgumentException('绑定关系已存在情况下不可切换仓储适配器!');
            }
        }

        switch ($adapter) {
            case 'selfwms':
                $data['node_id']   = 'selfwms';
                $data['node_type'] = 'selfwms';
                break;
            case 'openapiwms':
                $data['node_type'] = trim((string) ($config['node_type'] ?? ''));
                $data['node_id']   = sprintf('o%u', crc32(utils::array_md5($config) . kernel::base_url()));

                if ($data['node_type'] === '') {
                    throw new InvalidArgumentException('请选择仓储平台');
                }

                // 验证 node_id 是否重复
                $valid = true;
                if ($channelId > 0) {
                    if ($this->wmsModel->dump(['channel_id|noequal' => $channelId, 'node_id' => $data['node_id']])) {
                        $valid = false;
                    }
                } else {
                    if ($this->wmsModel->dump(['node_id' => $data['node_id']])) {
                        $valid = false;
                    }
                }
                if ($valid === false) {
                    throw new InvalidArgumentException('node_id重复，请更换秘钥，同时通知仓储更改秘钥');
                }

                // 判断参数是否都填了
                $params = kernel::single('wmsmgr_auth_config')->getPlatformParam($data['node_type']);
                if ($params) {
                    foreach ($params as $key => $label) {
                        if (empty($config[$key])) {
                            throw new InvalidArgumentException($label . '不能为空');
                        }
                    }
                }
                break;
            case 'ilcwms':
                $data['node_id'] = trim((string) ($config['node_id'] ?? ''));
                break;
            case 'mixturewms':
                $data['shipper'] = (string) ($input['shipper'] ?? '');
                break;
            default:
                break;
        }

        // 第三方仓储编码重复
        $resolvedBn = (string) ($data['channel_bn'] ?? ($channelInfo['channel_bn'] ?? ''));
        if ($resolvedBn !== '') {
            $dupFilter = ['channel_bn' => $resolvedBn];
            if ($channelId > 0) {
                $dupFilter['channel_id|noequal'] = $channelId;
            }
            if ($this->wmsModel->dump($dupFilter, '*')) {
                throw new InvalidArgumentException('第三方仓储编码重复');
            }
        }

        $configSerialized = serialize($config);
        $data['config']   = $configSerialized;
        if (!empty($channelInfo['channel_id'])) {
            $data['channel_id'] = (int) $channelInfo['channel_id'];
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $saved = $this->wmsModel->save($data);
            if (!$saved) {
                throw new RuntimeException('保存失败');
            }

            $newChannelId = (int) ($data['channel_id'] ?? 0);

            $adapterRow = [
                'channel_id' => $newChannelId,
                'adapter'    => $adapter,
                'config'     => $configSerialized,
            ];
            $this->adapterModel->save($adapterRow);

            if (!empty($input['divisionCode'])) {
                app::get('wms')->setConf('wms_storage_division_code_' . $newChannelId, $input['divisionCode']);
            }

            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return [
            'id'      => $newChannelId,
            'message' => '保存成功',
        ];
    }

    /**
     * 删除仓储（保留 legacy `deleteChannel()` 的三道守卫与全部文案）。
     *
     * @param array $input channel_id(数组) 或 selection_mode='_ALL_' + filters
     *
     * @return array{message:string}
     */
    public function deleteChannel(array $input, bool $ownTransaction = true): array
    {
        $objChannel = kernel::single('channel_channel');
        $objBranch  = kernel::single('ome_branch');

        $filter = [];
        if (!empty($input['channel_id']) && is_array($input['channel_id'])) {
            $filter['channel_id'] = $input['channel_id'];
        } elseif (($input['selection_mode'] ?? '') === '_ALL_') {
            $filter = is_array($input['filters'] ?? null) ? $input['filters'] : [];
            unset($filter['app'], $filter['ctl'], $filter['act'], $filter['flt'], $filter['_finder']);
        }

        if (empty($filter)) {
            throw new InvalidArgumentException('请选择要删除的仓储!');
        }

        // 获取所有已经绑定的应用 channel_id
        $bindInfo        = $objChannel->getList($filter, '*');
        $branchBindWms   = $objBranch->getBindWmsBranchList();
        $bindChannelId   = [];
        $channelName     = [];
        $channelIdList   = [];

        if (!empty($bindInfo)) {
            foreach ($bindInfo as $v) {
                $channelName[$v['channel_id']] = $v['channel_name'];
                $channelIdList[] = $v['channel_id'];

                // 把已经存在的绑定节点存起来
                if (strlen((string) $v['node_id']) > 0 && !in_array($v['node_type'], ['selfwms', 'mobile', 'webpos'], true)) {
                    $bindChannelId[] = $v['channel_id'];
                }

                if (in_array($v['node_type'], ['mobile', 'webpos'], true)) {
                    throw new RuntimeException('虚拟门店仓储类型不允许被删除!');
                }
            }
        }

        if (!empty($branchBindWms)) {
            foreach ($branchBindWms as $v) {
                if (in_array($v['wms_id'], $channelIdList, true)) {
                    throw new RuntimeException('仓储:' . $channelName[$v['wms_id']] . '，已经绑定了仓库，请先解除绑定!');
                }
            }
        }

        // 验证即将被删除的这条 channel_id 是否已经解除绑定
        foreach ($channelIdList as $id) {
            if (array_search($id, $bindChannelId) !== false) {
                throw new RuntimeException('删除前，请解除绑定!');
            }
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $result = $objChannel->delete(['channel_id' => $channelIdList]);
            if (!$result) {
                throw new RuntimeException('删除失败');
            }
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '删除成功'];
    }

    /**
     * 物流公司编码配置写（legacy finder `detail_company` 的 $_POST 分支）。
     *
     * @return array{message:string}
     */
    public function saveDetailCompany(array $input, bool $ownTransaction = true): array
    {
        $wmsId         = (int) ($input['wms_id'] ?? 0);
        $wmsExpressBn  = is_array($input['wms_express_bn'] ?? null) ? $input['wms_express_bn'] : [];
        $sysExpressBn  = is_array($input['sys_express_bn'] ?? null) ? $input['sys_express_bn'] : [];

        if ($wmsId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $model = app::get('wmsmgr')->model('express_relation');
            $model->delete(['wms_id' => $wmsId]);
            foreach ($wmsExpressBn as $k => $v) {
                if ($k && $v) {
                    $sdata = [
                        'wms_id'         => $wmsId,
                        'logi_id'        => $k,
                        'sys_express_bn' => trim((string) ($sysExpressBn[$k] ?? '')),
                        'wms_express_bn' => trim((string) $v),
                    ];
                    $model->save($sdata);
                }
            }
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '保存成功'];
    }

    /**
     * 店铺售达方编号写（legacy finder `detail_shop` 的 $_POST 分支）。
     *
     * @return array{message:string}
     */
    public function saveDetailShop(array $input, bool $ownTransaction = true): array
    {
        $wmsId      = (int) ($input['wms_id'] ?? 0);
        $shopConfig = is_array($input['shop_config'] ?? null) ? $input['shop_config'] : [];

        if ($wmsId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            app::get('finance')->setConf('shop_config_' . $wmsId, $shopConfig);
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '保存成功'];
    }

    /**
     * 仓库编码配置写（legacy finder `detail_branch` 的 $_POST 分支）。
     *
     * @return array{message:string}
     */
    public function saveDetailBranch(array $input, bool $ownTransaction = true): array
    {
        $wmsId       = (int) ($input['wms_id'] ?? 0);
        $wmsBranchBn = is_array($input['wms_branch_bn'] ?? null) ? $input['wms_branch_bn'] : [];
        $positive    = is_array($input['positive'] ?? null) ? $input['positive'] : [];
        $negative    = is_array($input['negative'] ?? null) ? $input['negative'] : [];

        if ($wmsId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $model = app::get('wmsmgr')->model('branch_relation');
            $model->delete(['wms_id' => $wmsId]);
            foreach ($wmsBranchBn as $k => $v) {
                $sdata = [
                    'wms_id'         => $wmsId,
                    'sys_branch_bn'  => $k,
                    'wms_branch_bn'  => $v,
                    'positive'       => !empty($positive[$k]) ? '1' : '2',
                    'negative'       => (int) ($negative[$k] ?? 0),
                ];
                $model->insert($sdata);
            }
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '保存成功'];
    }

    /**
     * 月结号等配置写（legacy finder `detail_monthaccount` 的 $_POST 分支）。
     *
     * @return array{message:string}
     */
    public function saveDetailMonthaccount(array $input, bool $ownTransaction = true): array
    {
        $wmsId = (int) ($input['wms_id'] ?? 0);
        if ($wmsId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $monthaccount = $input['monthaccount'] ?? '';
        $departmentNo = trim((string) ($input['department_no'] ?? ''));
        $isvSource    = trim((string) ($input['isv_source'] ?? ''));
        $ownerUserId  = trim((string) ($input['owner_user_id'] ?? ''));

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            app::get('wmsmgr')->setConf('monthaccount_' . $wmsId, $monthaccount);
            app::get('wmsmgr')->setConf('department_no_' . $wmsId, $departmentNo);
            app::get('wmsmgr')->setConf('isv_source_' . $wmsId, $isvSource);
            app::get('wmsmgr')->setConf('owner_user_id_' . $wmsId, $ownerUserId);
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '保存成功'];
    }

    /**
     * 基础配置写（legacy finder `detail_basic` 的 $_POST 分支）：
     * `$wms['crop_config'] = array_merge((array)$wms['crop_config'], $_POST); $wmsMdl->save($wms);`
     *
     * @return array{message:string}
     */
    public function saveDetailBasic(array $input, bool $ownTransaction = true): array
    {
        $wmsId = (int) ($input['wms_id'] ?? 0);
        if ($wmsId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $wms = $this->wmsModel->dump($wmsId, 'channel_id,crop_config,node_type');
        if (empty($wms)) {
            throw new InvalidArgumentException('第三方仓储不存在');
        }

        $cropConfig = is_array($wms['crop_config'] ?? null) ? $wms['crop_config'] : [];
        $fields     = is_array($input['crop_config'] ?? null)
            ? $input['crop_config']
            : array_diff_key($input, ['wms_id' => true]);

        $wms['channel_id']  = $wmsId;
        $wms['crop_config'] = array_merge($cropConfig, $fields);

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $saved = $this->wmsModel->save($wms);
            if (!$saved) {
                throw new RuntimeException('保存失败');
            }
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '保存成功'];
    }

    /**
     * 供应商编码配置写（legacy finder `detail_supplier` 的 $_POST 分支）。
     *
     * @return array{message:string}
     */
    public function saveDetailSupplier(array $input, bool $ownTransaction = true): array
    {
        $wmsId = (int) ($input['wms_id'] ?? 0);
        $wms   = is_array($input['wms'] ?? null) ? $input['wms'] : [];

        if ($wmsId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $model = app::get('wmsmgr')->model('supplier_relation');
            foreach ($wms as $k => $v) {
                $sdata = [
                    'wms_id'          => $wmsId,
                    'supplier_id'     => $k,
                    'wms_supplier_bn' => trim((string) (is_array($v) ? ($v['supplier_bn'] ?? '') : $v)),
                ];
                $model->db_save($sdata);
            }
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '保存成功'];
    }

    /**
     * 仓储绑定店铺（legacy `bind_shop()`）。
     *
     * @return array{message:string}
     */
    public function bindShop(array $input, bool $ownTransaction = true): array
    {
        $channelId = (int) ($input['channel_id'] ?? 0);
        $shopId    = (int) ($input['shop_id'] ?? 0);

        $wms = $this->wmsModel->db_dump(['channel_id' => $channelId, 'channel_type' => self::CHANNEL_TYPE]);
        if (!empty($wms)) {
            $wms['config'] = @unserialize((string) $wms['config']);
        }

        if (empty($wms)) {
            throw new InvalidArgumentException('仓储不存在');
        }
        if (!empty($wms['node_id'])) {
            throw new InvalidArgumentException('仓储已经绑定');
        }
        if (empty($wms['config']['node_type']) || $wms['config']['node_type'] === 'other') {
            throw new InvalidArgumentException('仓储不支持绑定店铺');
        }

        $shop = app::get('ome')->model('shop')->dump($shopId, 'node_id,node_type');
        if (empty($shop['node_id'])) {
            throw new InvalidArgumentException('店铺未绑定');
        }

        $addon  = ['node_id' => $shop['node_id']];
        $nodeId = sprintf('o%u', crc32(utils::array_md5($wms['config']) . kernel::base_url()));

        return $this->updateBindTransaction(
            ['node_id' => $nodeId, 'node_type' => $wms['config']['node_type'], 'addon' => $addon],
            ['channel_id' => $channelId],
            $ownTransaction
        );
    }

    /**
     * 填写 node_id 绑定（legacy `setNodeId()`）。
     *
     * @return array{message:string}
     */
    public function setNodeId(array $input, bool $ownTransaction = true): array
    {
        $channelId = (int) trim((string) ($input['channel_id'] ?? ''));
        $nodeId    = trim((string) ($input['node_id'] ?? ''));

        $wms = $this->wmsModel->db_dump(['channel_id' => $channelId, 'channel_type' => self::CHANNEL_TYPE]);
        if (!empty($wms)) {
            $wms['config'] = @unserialize((string) $wms['config']);
        }

        if (empty($wms)) {
            throw new InvalidArgumentException('仓储不存在');
        }
        if (!empty($wms['node_id'])) {
            throw new InvalidArgumentException('仓储已经绑定');
        }
        if (empty($wms['config']['node_type']) || $wms['config']['node_type'] === 'other') {
            throw new InvalidArgumentException('仓储不支持填写node_id');
        }

        return $this->updateBindTransaction(
            ['node_id' => $nodeId, 'node_type' => $wms['config']['node_type']],
            ['channel_id' => $channelId],
            $ownTransaction
        );
    }

    /**
     * 仓储解绑店铺（legacy `unbind_shop()`）。
     *
     * @return array{message:string}
     */
    public function unbindShop(array $input, bool $ownTransaction = true): array
    {
        $channelId = (int) ($input['channel_id'] ?? 0);

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $affectRow = $this->wmsModel->update(
                ['node_id' => null, 'node_type' => null],
                ['channel_id' => $channelId, 'channel_type' => self::CHANNEL_TYPE]
            );
            if (!($affectRow && is_numeric($affectRow))) {
                throw new RuntimeException('解绑失败');
            }
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '解绑成功'];
    }

    /**
     * 最终解除绑定（legacy `finish_unbind($channel_id)`）。
     *
     * @return array{message:string}
     */
    public function finishUnbind(array $input, bool $ownTransaction = true): array
    {
        $channelId = (int) ($input['channel_id'] ?? 0);

        $wmsInfo = $this->wmsModel->db_dump(['channel_id' => $channelId, 'channel_type' => self::CHANNEL_TYPE], '*');
        if (empty($wmsInfo)) {
            throw new InvalidArgumentException('WMS仓储信息不存在');
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $affectRow = $this->wmsModel->update(
                ['node_id' => null, 'node_type' => null],
                ['channel_id' => $channelId, 'channel_type' => self::CHANNEL_TYPE]
            );
            if (!($affectRow && is_numeric($affectRow))) {
                throw new RuntimeException('解除绑定失败');
            }
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '解除绑定成功'];
    }

    /**
     * 同步基础数据（legacy `doSyncBase()`）：外部同步，只做参数校验与分派。
     *
     * @return array{message:string}
     */
    public function doSyncBase(array $input, bool $ownTransaction = true): array
    {
        $type = trim((string) ($input['type'] ?? ''));
        if (!in_array($type, ['shop', 'corp', 'supplier'], true)) {
            throw new InvalidArgumentException('没有该类型');
        }

        return ['message' => 'ok.'];
    }

    /**
     * 申请绑定 / 查看绑定关系 iframe URL（legacy `apply_bindrelation()` / `view_bindrelation()`）。
     */
    public function relationUrl(string $mode, array $input = []): string
    {
        if ($mode === 'view') {
            return $this->buildViewRelationUrl();
        }

        return $this->buildApplyRelationUrl(
            (string) ($input['app_id'] ?? 'ome'),
            (string) ($input['callback'] ?? ''),
            (string) ($input['api_url'] ?? '')
        );
    }

    /**
     * 申请绑定关系 iframe URL（legacy `apply_bindrelation()`）。
     */
    public function buildApplyRelationUrl(string $appId = 'ome', string $callback = '', string $apiUrl = ''): string
    {
        $certiId = (string) base_certificate::get('certificate_id');
        $token   = (string) base_certificate::get('token');
        $nodeId  = (string) base_shopnode::node_id($appId);
        $sessId  = (string) kernel::single('base_session')->sess_id();

        $apply = [
            'certi_id' => $certiId,
            'sess_id'  => $sessId,
        ];
        if ($nodeId !== '') {
            $apply['node_id'] = $nodeId;
        }

        ksort($apply);
        $str = '';
        foreach ($apply as $value) {
            $str .= $value;
        }
        $apply['certi_ac'] = md5($str . $token);

        $params = [
            'source'    => 'apply',
            'certi_id'  => $apply['certi_id'],
            'node_id'   => $apply['node_id'] ?? '',
            'sess_id'   => $apply['sess_id'],
            'certi_ac'  => $apply['certi_ac'],
            'callback'  => $callback,
            'api_url'   => $apiUrl,
            'show_type' => 'wms',
        ];

        return MATRIX_RELATION_URL . '?' . http_build_query($params);
    }

    /**
     * 查看绑定关系 iframe URL（legacy `view_bindrelation()`）。
     */
    public function buildViewRelationUrl(): string
    {
        $certiId = (string) base_certificate::get('certificate_id');
        $token   = (string) base_certificate::get('token');
        $nodeId  = (string) base_shopnode::node_id('ome');
        $sessId  = (string) kernel::single('base_session')->sess_id();

        $apply = [
            'certi_id' => $certiId,
            'node_id'  => $nodeId,
            'sess_id'  => $sessId,
        ];
        ksort($apply);
        $str = '';
        foreach ($apply as $value) {
            $str .= $value;
        }
        $apply['certi_ac'] = md5($str . $token);

        $callback = urlencode((string) kernel::openapi_url('openapi.channel', 'bindCallback'));
        $apiUrl   = urlencode(kernel::base_url(true) . kernel::url_prefix() . '/api');
        $opId     = (string) kernel::single('desktop_user')->get_login_name();
        $opUser   = (string) kernel::single('desktop_user')->get_name();

        $params = [
            'op_id'     => $opId,
            'op_user'   => $opUser,
            'source'    => 'accept',
            'certi_id'  => $apply['certi_id'],
            'node_id'   => $apply['node_id'],
            'sess_id'   => $apply['sess_id'],
            'certi_ac'  => $apply['certi_ac'],
            'callback'  => $callback,
            'api_url'   => $apiUrl,
            'show_type' => 'wms',
        ];

        return MATRIX_RELATION_URL . '?' . http_build_query($params);
    }

    /**
     * 生成私钥（legacy `gen_private_key()`：echo md5(uniqid())）。
     */
    public function genPrivateKey(): string
    {
        return md5(uniqid());
    }

    /**
     * 同步基础数据选项（legacy `syncBase()` 的 base_data）。
     *
     * @return list<array{id:string,type:string,name:string}>
     */
    public function syncBaseOptions(int $wmsId): array
    {
        $baseData = [];

        $shop = app::get('ome')->model('shop')->getList('shop_id,name', []);
        foreach ($shop as $val) {
            $baseData[] = [
                'id'   => $val['shop_id'],
                'type' => 'shop',
                'name' => '店铺-' . $val['name'],
            ];
        }
        $baseData[] = ['id' => '-1', 'type' => 'shop', 'name' => '没有店铺-noshopcode'];

        $corp = app::get('ome')->model('dly_corp')->getList('corp_id,name', []);
        foreach ($corp as $val) {
            $baseData[] = [
                'id'   => $val['corp_id'],
                'type' => 'corp',
                'name' => '物流-' . $val['name'],
            ];
        }
        $baseData[] = ['id' => '-1', 'type' => 'corp', 'name' => '未选物流-other'];

        $supplier = app::get('purchase')->model('supplier')->getList('supplier_id,name', []);
        foreach ($supplier as $val) {
            $baseData[] = [
                'id'   => $val['supplier_id'],
                'type' => 'supplier',
                'name' => '供应商-' . $val['name'],
            ];
        }
        $baseData[] = ['id' => '-1', 'type' => 'supplier', 'name' => '没有供应商-nosuppliercode'];

        return $baseData;
    }

    /**
     * 初始化京东物流公司映射关系（legacy `doInitializeLogi()`）。
     *
     * @return array{message:string}
     */
    public function doInitializeLogi(array $input, bool $ownTransaction = true): array
    {
        $channelId = (int) ($input['channel_id'] ?? 0);
        if (empty($channelId)) {
            throw new InvalidArgumentException('没有获取到channel_id');
        }

        $wmsInfo = $this->wmsModel->dump($channelId);
        if (empty($wmsInfo)) {
            throw new InvalidArgumentException('没有获取到WMS仓储信息');
        }

        $logiList = kernel::single('wmsmgr_func')->getKeplerLogi();
        if (empty($logiList)) {
            throw new InvalidArgumentException('没有获取到物流公司列表');
        }

        $dlyCorpModel = app::get('ome')->model('dly_corp');
        $relationModel = app::get('wmsmgr')->model('express_relation');

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            // 创建 OMS 物流公司
            foreach ($logiList as $item) {
                if (empty($item['logi_code'])) {
                    continue;
                }

                $corpInfo = [
                    'branch_mode'    => 'multi',
                    'tmpl_type'      => 'normal',
                    'name'           => trim($item['logi_name']),
                    'type'           => trim($item['logi_code']),
                    'website'        => '',
                    'request_url'    => '',
                    'setting'        => '1',
                    'firstunit'      => '1',
                    'continueunit'   => '1000',
                    'firstprice'     => '1',
                    'continueprice'  => '1',
                    'dt_expressions' => '',
                    'dt_useexp'      => '0',
                    'disabled'       => 'false',
                    'cainiao_tmpl_id' => '',
                    'pdd_tmpl_id'    => '',
                    'jd_tmpl_id'     => '',
                    'douyin_tmpl_id' => '',
                    'weight'         => '0',
                    'prt_tmpl_id'    => '1',
                    'shop_id'        => '',
                    'channel_id'     => 0,
                    'area_fee_conf'  => [],
                    'protect'        => 'false',
                ];

                if (empty($corpInfo['type'])) {
                    continue;
                }

                $sql = "SELECT * FROM sdb_ome_dly_corp WHERE type='" . $corpInfo['type'] . "' AND name='" . $corpInfo['name'] . "'";
                $checkInfo = $dlyCorpModel->db->selectrow($sql);
                if ($checkInfo) {
                    continue;
                }

                $isSave = $dlyCorpModel->save($corpInfo);
                if (!$isSave) {
                    throw new RuntimeException('创建物流公司：' . $corpInfo['name'] . '失败');
                }
            }

            // 创建京东云交易仓储映射物流关系
            foreach ($logiList as $item) {
                $jdLogiCode = trim($item['logi_code']);
                $jdLogiId   = trim($item['logi_id']);

                if (empty($jdLogiId)) {
                    continue;
                }

                $sql = "SELECT * FROM sdb_ome_dly_corp WHERE type='" . $jdLogiCode . "'";
                $logiInfo = $dlyCorpModel->db->selectrow($sql);
                if (empty($logiInfo)) {
                    continue;
                }

                $omsLogiId = $logiInfo['corp_id'];

                $checkInfo = $relationModel->dump(['wms_id' => $channelId, 'logi_id' => $omsLogiId], '*');
                if ($checkInfo && !empty($checkInfo['wms_express_bn'])) {
                    continue;
                }

                $sdf = [
                    'wms_id'         => $channelId,
                    'logi_id'        => $omsLogiId,
                    'sys_express_bn' => $jdLogiCode,
                    'wms_express_bn' => $jdLogiId,
                ];
                $isSave = $relationModel->save($sdf);
                if (!$isSave) {
                    throw new RuntimeException('创建仓储映射物流关系：' . $jdLogiCode . '失败');
                }
            }

            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '同步WMS异常错误码成功'];
    }

    /**
     * 绑定/解绑共用的单行更新（显式事务）。
     *
     * @return array{message:string}
     */
    private function updateBindTransaction(array $data, array $filter, bool $ownTransaction): array
    {
        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $this->wmsModel->update($data, $filter);
            if ($started) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($started) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return ['message' => '绑定成功'];
    }
}
