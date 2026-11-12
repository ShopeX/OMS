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
 * @author ykm 2015-11-16
 * @describe 特殊订单条件列表
 */
class brush_finder_farm{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $column_edit = '操作';
    var $column_edit_width = 120;
    var $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row){
        $farm_id = $row['farm_id'];
        $finder_id = $_GET['_finder']['finder_id'];
        return  '<a href="index.php?app=brush&ctl=admin_condition&act=edit&farm_id='.$row['farm_id'].'&finder_id='.$finder_id.'" target="_blank">编辑</a>';
    }

    public $detail_log = '操作日志';
    /**
     * detail_log
     * @param mixed $farmId ID
     * @return mixed 返回值
     */
    public function detail_log($farmId) {
        $render = app::get('brush')->render();
        $logObj = app::get('ome')->model('operation_log');
        $log = $logObj->read_log(array('obj_id'=>$farmId,'obj_type'=>'farm@brush'), 0, -1);
        foreach($log as $k=>$v){
            $log[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['arrLog'] = $log;
        return $render->fetch('admin/farm/detail/log.html');
    }
}