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

/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_api_pagecols_setting extends desktop_ctl_api_base
{
    /**
     * GET - 获取页面配置数据
     * ?app=desktop&ctl=api_pagecols_setting&act=index
     */
    #[RequirePermission('page_config_setting')]
    public function index(): void
    {
        try {
            $model = $this->app->model('pagecols_setting');
            
            // 获取所有配置数据
            $configs = $model->getList('*', array(), 0, -1, 'id ASC');
            
            // 按场景分组数据
            $grouped_configs = array();
            foreach ($configs as $config) {
                $scene = $config['tbl_name'];
                if (!isset($grouped_configs[$scene])) {
                    $grouped_configs[$scene] = array();
                }
                $grouped_configs[$scene][$config['col_key']] = array(
                    'required' => $config['is_required'] == '1',
                    'default' => $config['default_value']
                );
            }
            
            // 预定义的场景和元素
            $list = kernel::servicelist('set_pagecols_setting');
            $predefined_scenes = array();
            foreach ($list as $k => $obj) {
                if (method_exists($obj, 'get_pagecols_setting')) {
                    $scenes = $obj->get_pagecols_setting();
                    if (is_array($scenes)) {
                        $predefined_scenes = array_merge($predefined_scenes, $scenes);
                    }
                }
            }
            
            // Transform predefined_scenes from object to array format for Vue
            $predefined_scenes_array = [];
            foreach ($predefined_scenes as $scene_key => $scene_data) {
                $cols = [];
                if (isset($scene_data['elements']) && is_array($scene_data['elements'])) {
                    foreach ($scene_data['elements'] as $col_key => $col_data) {
                        $isString   = !is_array($col_data);
                        $hasOptions = is_array($col_data) && !empty($col_data['options']);
                        // legacy 三态：有 options ⇒ select；字符串 / 带 options 键 / 带 name 键 ⇒ no-default（无需设置）；其余 ⇒ input
                        $isNoDefault = !$hasOptions && ($isString
                            || (is_array($col_data) && (array_key_exists('options', $col_data) || !empty($col_data['name']))));
                        $cols[] = [
                            'key'     => $col_key,
                            'label'   => $isString ? (string) $col_data : ($col_data['name'] ?? $col_key),
                            'type'    => $hasOptions ? 'select' : ($isNoDefault ? 'no-default' : 'input'),
                            'options' => $hasOptions ? $col_data['options'] : [],
                        ];
                    }
                }
                $predefined_scenes_array[] = [
                    'scene'  => $scene_key,
                    'label'  => $scene_data['name'] ?? $scene_key,
                    'cols'   => $cols,
                ];
            }
            
            $this->succ([
                'grouped_configs'    => $grouped_configs,
                'predefined_scenes' => $predefined_scenes_array,
            ]);
        } catch (Throwable $e) {
            error_log('[pagecols_setting/index] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->fail('加载数据失败: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST - 保存配置数据
     * ?app=desktop&ctl=api_pagecols_setting&act=save
     */
    #[RequirePermission('page_config_setting')]
    public function save(): void
    {
        try {
            $input = $this->getRequestInput();
            $scene_data = $input['scene_data'] ?? null;
            
            if (!$scene_data) {
                $this->fail('提交数据不能为空');
                return;
            }
            
            $model = $this->app->model('pagecols_setting');
            
            // 先删除所有现有配置
            $model->delete(array());
            
            // 保存新的配置数据
            $success = true;
            foreach ($scene_data as $scene => $elements) {
                if (!is_array($elements)) {
                    continue;
                }
                foreach ($elements as $element_key => $element_data) {
                    if (!is_array($element_data)) {
                        continue;
                    }
                    $data = array(
                        'tbl_name' => $scene,
                        'col_key' => $element_key,
                        'is_required' => !empty($element_data['required']) ? '1' : '0',
                        'default_value' => $element_data['default'] ?? '',
                    );
                    
                    $result = $model->insert($data);
                    if (!$result) {
                        $success = false;
                        break 2;
                    }
                }
            }
            
            if ($success) {
                $this->succ([
                    'message' => '保存成功',
                ]);
            } else {
                $this->fail('保存失败');
            }
        } catch (Throwable $e) {
            error_log('[pagecols_setting/save] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->fail('保存失败: ' . $e->getMessage(), 500);
        }
    }
}