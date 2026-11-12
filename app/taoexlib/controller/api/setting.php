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

class taoexlib_ctl_api_setting extends desktop_ctl_api_base
{
    /**
     * 短信设置 - 获取当前配置
     * GET ?app=taoexlib&ctl=api_setting&act=index
     */
    #[RequirePermission('rolescfg')]
    public function index(): void
    {
        $setting = kernel::single('taoexlib_setting');
        $settings = $setting->all_settings();

        $setData = [];
        foreach ($settings as $set) {
            $key = str_replace('.', '_', $set);
            $setData[$key] = app::get('taoexlib')->getConf($set);
        }

        $this->succ([
            'settings' => $setData,
            'configKeys' => $settings,
        ]);
    }

    /**
     * 短信设置 - 保存配置
     * POST ?app=taoexlib&ctl=api_setting&act=save
     * Body: { settings: { 'taoexlib.message.switch': 'on', ... } }
     */
    #[RequirePermission('rolescfg')]
    public function save(): void
    {
        $input = $this->getJsonBody();
        $settings = $input['settings'] ?? [];

        if (empty($settings)) {
            $this->fail('没有要保存的设置');
            return;
        }

        foreach ($settings as $key => $value) {
            $curSet = app::get('taoexlib')->getConf($key);
            if ($curSet != $value) {
                app::get('taoexlib')->setConf($key, $value);
            }
        }

        // 前后端分离契约：直接返回 JSON（勿用 legacy begin()/end()，其 redirect 会让 SPA 跳去 legacy 页）
        $this->succ(['message' => '保存成功']);
    }

    /**
     * 免打扰设置 - 加载/保存黑名单手机号
     */
    #[RequirePermission('rolescfg')]
    public function blacklist(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->doSave();
            return;
        }

        $this->doLoad();
    }

    /**
     * 加载黑名单配置
     */
    protected function doLoad(): void
    {
        $blacklist = app::get('taoexlib')->getConf('taoexlib.message.blacklist') ?? '';
        
        $this->succ([
            'blacklist' => $blacklist,
        ]);
    }

    /**
     * 保存黑名单配置
     */
    protected function doSave(): void
    {
        // ⚠️ PHP 会把 POST 键名里的 `.` 统一转成 `_`
        // （`set[taoexlib.message.blacklist]` 到达 $_POST 时已是 `set[taoexlib_message_blacklist]`）
        // ⇒ 两种键形态都要兜住，否则永远读到空值、把配置写成空（实测 2026-09-26）
        $blacklist = $_POST['set']['taoexlib.message.blacklist']
            ?? $_POST['set']['taoexlib_message_blacklist']
            ?? '';

        // 验证：格式为 ## 分隔的手机号
        if ($blacklist !== '') {
            $phones = explode('##', $blacklist);
            foreach ($phones as $phone) {
                $phone = trim($phone);
                if ($phone !== '' && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
                    $this->fail('手机号格式不正确：' . $phone);
                    return;
                }
            }
        }

        $current = app::get('taoexlib')->getConf('taoexlib.message.blacklist');
        if ($current !== $blacklist) {
            app::get('taoexlib')->setConf('taoexlib.message.blacklist', $blacklist);
        }

        $this->succ(['message' => '保存成功']);
    }
}