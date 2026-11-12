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
 * 报价系统授权（wmsmgr / admin_smart）共享能力
 *
 * 单一实现，两条入口共用同一份逻辑：
 *  - Vue JSON 入口：wmsmgr_ctl_api_smart::save()（act=save，JSON body）
 *  - legacy 入口：wmsmgr_ctl_admin_smart::savesmart()（表单，事务由 begin()/end() 托管）
 *
 * 读：编辑回填（legacy `_edit()` 的 `$wms` 字段）/ 适配器·平台清单（wmsmgr_smart_config）/
 *     绑定关系 iframe URL（legacy `apply_bindrelation()` / `view_bindrelation()`）。
 * 写：`savesmart` 逐字等价（校验 / node_id 计算 / 唯一性 / adapter 落库）。
 *
 * 底座模型 `wmsmgr_mdl_smart`（table `sdb_channel_channel`，channel_type='smart'），
 * 与 legacy 的 `$this->app->model('wms')` 同表（`wmsmgr_mdl_wms` 亦指向 `sdb_channel_channel`）。
 */
final class wmsmgr_capability_smart
{
    private const CHANNEL_TYPE = 'smart';

    private object $smartModel;

    private object $adapterModel;

    private object $database;

    public function __construct(?object $smartModel = null, ?object $adapterModel = null, ?object $database = null)
    {
        $this->smartModel   = $smartModel ?? app::get('wmsmgr')->model('smart');
        $this->adapterModel = $adapterModel ?? app::get('channel')->model('adapter');
        $this->database     = $database ?? kernel::database();
    }

    /**
     * 编辑回填（legacy `_edit($wms_id)` 取 `$wms` 与 `adapter` / `api_url`）
     *
     * @return array{data:array}
     */
    public function loadSmart(int $channelId): array
    {
        if ($channelId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $row = $this->smartModel->dump(
            ['channel_id' => $channelId, 'channel_type' => self::CHANNEL_TYPE],
            '*'
        );
        if (empty($row)) {
            throw new InvalidArgumentException('报价系统授权不存在');
        }

        $config = @unserialize((string) ($row['config'] ?? ''));
        if (!is_array($config)) {
            $config = [];
        }

        $adapter = kernel::single('wmsmgr_func')->getAdapterByChannelId($channelId);

        return [
            'data' => [
                'channel_id'   => (int) $row['channel_id'],
                'channel_bn'   => (string) ($row['channel_bn'] ?? ''),
                'channel_name' => (string) ($row['channel_name'] ?? ''),
                'adapter'      => (string) ($adapter ?? ''),
                'node_type'    => (string) ($row['node_type'] ?? ''),
                'config'       => $config,
                'node_id'      => (string) ($row['node_id'] ?? ''),
                'api_url'      => (string) app::get('wmsmgr')->getConf('api_url' . ($row['node_id'] ?? '')),
            ],
        ];
    }

    /**
     * 新建 / 编辑保存（逐字对齐 legacy `savesmart()`）
     *
     * @param array $input          wms{channel_id?,channel_bn,channel_name,adapter} / config{node_type,...}
     * @param bool  $ownTransaction legacy 入口事务由 desktop_controller::begin() 托管，传 false
     *
     * @return array{id:int,message:string}
     */
    public function saveSmart(array $input, bool $ownTransaction = true): array
    {
        $wms    = is_array($input['wms'] ?? null) ? $input['wms'] : [];
        $config = is_array($input['config'] ?? null) ? $input['config'] : [];

        $postData = [
            'channel_type' => self::CHANNEL_TYPE,
            'channel_bn'   => trim((string) ($wms['channel_bn'] ?? '')),
            'channel_name' => trim((string) ($wms['channel_name'] ?? '')),
            'adapter'      => trim((string) ($wms['adapter'] ?? '')),
            'node_type'    => trim((string) ($config['node_type'] ?? '')),
        ];

        if ($postData['channel_bn'] === '') {
            throw new InvalidArgumentException('请填写系统编码!');
        }
        if ($postData['channel_name'] === '') {
            throw new InvalidArgumentException('请填写系统名称!');
        }
        if ($postData['adapter'] === '') {
            throw new InvalidArgumentException('请选择系统适配器!');
        }
        if ($postData['node_type'] === '') {
            throw new InvalidArgumentException('请选择授权平台!');
        }

        $channelId = (int) ($wms['channel_id'] ?? 0);

        // add OR update
        $channelInfo = [];
        $filter      = ['channel_bn' => $postData['channel_bn']];
        if ($channelId > 0) {
            $channelInfo = $this->smartModel->dump(['channel_id' => $channelId], '*');
            if ($channelInfo) {
                $filter['channel_id|noequal'] = (int) $channelInfo['channel_id'];
            }
        }

        // 判断数据是否已经存在
        if ($this->smartModel->dump($filter, '*')) {
            throw new InvalidArgumentException('系统编码已经存在,不能重复!');
        }

        // 绑定关系已存在时不可切换适配器
        if (!empty($channelInfo['node_id']) && $postData['adapter'] !== (string) ($channelInfo['adapter'] ?? '')) {
            // 兼容 legacy：adapter 通过 channel/adapter 表取（见下方 getAdapterByChannelId）
            $currentAdapter = (string) kernel::single('wmsmgr_func')->getAdapterByChannelId((int) $channelInfo['channel_id']);
            if ($postData['adapter'] !== $currentAdapter) {
                throw new InvalidArgumentException('绑定关系已存在情况下：不可切换仓储适配器!');
            }
        }

        if ($postData['adapter'] === 'openapiwms') {
            $postData['node_id'] = sprintf('o%u', crc32(utils::array_md5($config) . kernel::base_url()));

            $nodeFilter = ['node_id' => $postData['node_id']];
            if ($channelInfo) {
                $nodeFilter['channel_id|noequal'] = (int) $channelInfo['channel_id'];
            }
            if ($this->smartModel->dump($nodeFilter, '*')) {
                throw new InvalidArgumentException('node_id重复：请更换秘钥,同时通知报价系统更改秘钥!');
            }

            $params = kernel::single('wmsmgr_smart_config')->getPlatformParam($postData['node_type']);
            if ($params) {
                foreach ($params as $key => $label) {
                    if (empty($config[$key])) {
                        throw new InvalidArgumentException($label . '：不能为空');
                    }
                }
            }
        }

        $postData['config'] = serialize($config);
        if ($channelInfo) {
            $postData['channel_id'] = (int) $channelInfo['channel_id'];
        }

        $started = false;
        if ($ownTransaction) {
            $this->database->beginTransaction();
            $started = true;
        }

        try {
            $isSave = $this->smartModel->save($postData);
            if (!$isSave) {
                throw new RuntimeException('保存数据失败!');
            }

            $newChannelId = (int) ($postData['channel_id'] ?? 0);

            $adapterRow = [
                'channel_id' => $newChannelId,
                'adapter'    => $postData['adapter'],
                'config'     => $postData['config'],
            ];
            $this->adapterModel->save($adapterRow);

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
     * 申请绑定关系 iframe URL（legacy `apply_bindrelation()`）
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
     * 查看绑定关系 iframe URL（legacy `view_bindrelation()`）
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
}
