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
 * 渠道管理列表（wmsmgr / admin_channel）写/读契约的唯一实现。
 *
 * capability 拥有校验、归一化、持久化、事务（本页为单表单行写，不需要显式多写事务）。
 * 两个入口共用同一实现，消除「双写出口」：
 *   - Vue：  ctl=api_channel&act=save|delChannel        （JSON body）
 *   - legacy：ctl=admin_channel&act=saveChannel|delChannel（表单，桌面框架托管事务）
 *
 * 表：sdb_wmsmgr_channel（模型 wmsmgr_mdl_channel，由 dbschema/channel.php 生成）。
 */
class wmsmgr_capability_channel
{
    /** 授权服务键（legacy saveChannel 内写死） */
    private const SERVICE_KEY = 'kepler_channel';

    private function model()
    {
        return app::get('wmsmgr')->model('channel');
    }

    private function materialChannelModel()
    {
        return app::get('material')->model('basic_material_channel');
    }

    /**
     * 是否允许添加新渠道（legacy index() 的工具栏门控 + saveChannel 的前置校验共用）。
     */
    public function canAddChannel(): bool
    {
        return (bool) kernel::single('wmsmgr_channel')->checkAddChannel();
    }

    /**
     * 关联第三方仓储下拉（legacy `_edit()` 的 `$wms`）。
     * 过滤条件逐字照抄 legacy：node_type='yjdf' 且 node_id 非空。
     */
    public function listWmsOptions(): array
    {
        $rows = app::get('wmsmgr')->model('wms')->getList('*', array('node_type' => 'yjdf', 'node_id|noequal' => ''));

        return array_map(function ($row) {
            return array(
                'value' => (string) ($row['channel_id'] ?? ''),
                'label' => (string) ($row['channel_name'] ?? ''),
            );
        }, is_array($rows) ? $rows : array());
    }

    /**
     * 编辑回填：取库中权威行（legacy `_edit($id)` 的 `$channel`）。
     *
     * @throws Exception 行不存在
     */
    public function loadChannel(int $id): array
    {
        if ($id <= 0) {
            throw new Exception('参数错误：渠道记录不存在');
        }

        $row = $this->model()->dump($id);
        if (empty($row)) {
            throw new Exception('渠道记录不存在');
        }

        return array(
            'channel' => array(
                'id'           => (int) ($row['id'] ?? 0),
                'channel_id'   => (string) ($row['channel_id'] ?? ''),
                'channel_name' => (string) ($row['channel_name'] ?? ''),
                'wms_id'       => (string) ($row['wms_id'] ?? ''),
            ),
        );
    }

    /**
     * 新建渠道（legacy saveChannel 的语义等价实现）。
     *
     * @throws Exception 校验失败 / 重复 / 落库失败
     */
    public function saveChannel(array $input): array
    {
        // FE 载荷：{channel:{channel_id,channel_name,wms_id}}；legacy 表单同为 channel[...]
        $channel = isset($input['channel']) && is_array($input['channel']) ? $input['channel'] : $input;

        $channelId   = trim((string) ($channel['channel_id'] ?? ''));
        $channelName = trim((string) ($channel['channel_name'] ?? ''));
        $wmsId       = $channel['wms_id'] ?? '';

        // legacy：先判授权门控
        if (!$this->canAddChannel()) {
            throw new Exception('请先购买绑定多渠道的授权服务!');
        }

        if ($channelId === '') {
            throw new Exception('渠道ID不能为空');
        }
        if ($channelName === '') {
            throw new Exception('渠道名称不能为空');
        }

        $adInfo = kernel::single('ome_func')->getDesktopUser();

        $data = array(
            'channel_id'   => $channelId,
            'channel_name' => $channelName,
            'createtime'   => time(),
            'op_id'        => $adInfo['op_id'],
            'op_name'      => $adInfo['op_name'],
        );
        if ($wmsId !== '' && $wmsId !== null) {
            $data['wms_id'] = $wmsId;
        }

        $mdl = $this->model();
        if ($mdl->dump(array('channel_id' => $channelId))) {
            throw new Exception(app::get('base')->_('渠道ID重复，保存失败'));
        }

        $rt = $mdl->save($data);
        if (!$rt) {
            throw new Exception(app::get('base')->_('保存失败'));
        }

        $saved = $mdl->dump(array('channel_id' => $channelId));

        // 更新授权服务已使用次数（Monitor 每次固定 +1 授权次数）—— legacy 同款
        kernel::single('wmsmgr_channel')->updateServiceAuthNums(self::SERVICE_KEY, 1);

        return array(
            'message' => app::get('base')->_('保存成功'),
            'id'      => (int) ($saved['id'] ?? 0),
            'channel_id' => $channelId,
        );
    }

    /**
     * 删除渠道（有关联商品则禁止）。
     *
     * @throws Exception 无效参数 / 有关联商品 / 删除失败
     */
    public function deleteChannel(string $channelId): array
    {
        $channelId = trim($channelId);

        if ($channelId === '') {
            throw new Exception('操作出错：无效的操作。');
        }

        // 渠道商品总数
        $countNum = $this->materialChannelModel()->count(array('channel_id' => $channelId));
        if ($countNum) {
            throw new Exception('此渠道已经有关联商品,禁止删除。');
        }

        $result = $this->model()->delete(array('channel_id' => $channelId));
        if (!$result) {
            throw new Exception('删除渠道失败');
        }

        return array('message' => '删除渠道成功');
    }
}
