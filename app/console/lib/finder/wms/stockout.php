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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2022/10/20 17:58:06
 * @describe: 第三方出库单
 * ============================
 */
class console_finder_wms_stockout {
    public $addon_cols = 'wms_status,iso_status';

    /*public $column_edit = "操作";
    public $column_edit_width = "80";
    public $column_edit_order = "-1";
    public function column_edit($row) {
        $btn = [];
        return implode('|', $btn);
    }*/

    public $detail_item = "基础物料详情";
    public function detail_item($id){
        $render = app::get('console')->render();
        $items = app::get('console')->model('wms_stockout_items')->getList('*', ['wso_id'=>$id]);
        foreach ($items as $key => $value) {
            if($value['sn_list']) {
                $sn_list = json_decode($value['sn_list'],1);
                $items[$key]['sn_list'] = '唯一码：'.(is_array($sn_list['sn']) ? implode(', ', $sn_list['sn']) : $sn_list['sn']);
            }
            if($value['batch']) {
                $batch = json_decode($value['batch'], 1);
                $batch = isset($batch['batch'][0]) ? $batch['batch'] : [$batch['batch']];
                $title = array_keys($batch[0]);
                array_unshift($batch, $title);
                $items[$key]['batch'] = $batch;
            }
        }
        $render->pagedata['items'] = $items;
        return $render->fetch('admin/wms/stockout_items.html');
    }

    public $detail_oplog = "操作记录";
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'wms_stockout@console'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('admin/oplog.html');
    }
}
