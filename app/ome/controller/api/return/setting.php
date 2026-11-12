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

use app\desktop\lib\annotation\RequirePermission;

class ome_ctl_api_return_setting extends desktop_ctl_api_base
{
    /**
     * 退换货设置 - 读取
     * GET ?app=ome&ctl=api_return_setting&act=index
     */
    #[RequirePermission('aftersale_return_setting')]
    public function index(): void
    {
        $omeApp = app::get('ome');

        $setData = [
            'return_auto_approve'         => $omeApp->getConf('return.auto_approve'),
            'aftersale_auto_approve'      => $omeApp->getConf('aftersale.auto_approve'),
            'aftersale_gift_auto_approve' => $omeApp->getConf('aftersale.gift_auto_approve'),
            'return_auto_confirm'         => $omeApp->getConf('return.auto_confirm'),
            'return_logi_auto_approve'    => $omeApp->getConf('return.logi_auto_approve'),
        ];

        $this->succ($setData);
    }

    /**
     * 退换货设置 - 保存
     * POST ?app=ome&ctl=api_return_setting&act=save
     * Body: { set: { 'return.auto_approve': 1, ... } }
     */
    #[RequirePermission('aftersale_return_setting')]
    public function save(): void
    {
        $input = $this->getJsonBody();
        $settings = $input['set'] ?? [];

        if (empty($settings) || !is_array($settings)) {
            $this->fail('参数错误：缺少设置数据');
            return;
        }

        $omeApp = app::get('ome');

        foreach ($settings as $key => $value) {
            if ($omeApp->getConf($key) != $value) {
                $omeApp->setConf($key, $value);
            }
        }

        // 前后端分离契约：直接返回 JSON（勿用 legacy begin()/end()，其 redirect 会让 SPA 跳去 legacy 页）
        $this->succ(['message' => '保存成功']);
    }
}
