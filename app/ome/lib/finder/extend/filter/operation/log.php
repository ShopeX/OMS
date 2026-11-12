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
 * 操作日志dbschema扩展
 *
 */
class ome_finder_extend_filter_operation_log {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $usersModel = app::get('desktop')->model('users');
        $result = $usersModel->getList('user_id,name');
        $userList = array();
        foreach ($result as $v) {
            $userList[$v['user_id']] = $v['name'];
        }
        $operationType = ome_operation_log::getType();
        $db['operation_log'] = array(
            'columns' => array(
                'user' => array(
                    'type' => $userList,
                    'editable' => false,
                    'label' => '操作者',
                    'width' => 110,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'ome_operation_log_finder_top',
                ),
                'operation_type' => array(
                    'type' => $operationType,
                    'editable' => false,
                    'label' => '操作类型',
                    'width' => 110,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'ome_operation_log_finder_top',
                ),
                'st_time' => array(
                    'type' => 'date',
                    'label' => '起始日期',
                    'required' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filterdefault' => true,
                    // Vue 模式(desktop_finder_vue_context:526-529 会先 setVueMode(true))返回 date:
                    // 前端渲染单日期选择器并以裸键 st_time 直传,由 ome_mdl_operation_log::_filter()
                    // 翻译为 operate_time|bthan;legacy 面板保持 time 口径不变
                    'filtertype' => $this->vue_mode ? 'date' : 'time',
                    'width' => 140,
                    'panel_id' => 'ome_operation_log_finder_top',
                ),
                'et_time' => array(
                    'type' => 'date',
                    'label' => '结束日期',
                    'required' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filterdefault' => true,
                    // 同 st_time:Vue 裸键 et_time 直传,模型 _filter() +1 天实现含当天;legacy 保持 time
                    'filtertype' => $this->vue_mode ? 'date' : 'time',
                    'width' => 140,
                    'panel_id' => 'ome_operation_log_finder_top',
                ),
            )
        );
        return $db;
     }
}