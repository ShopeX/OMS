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
class console_finder_inventory_apply{
   
    var $detail_item = "详情";

   
    
    function detail_item($apply_id){
        $render = app::get('console')->render();
        $inv_aiObj = app::get('console')->model('inventory_apply_items');
        
        $count = $inv_aiObj->count(array('inventory_apply_id'=>$apply_id));
        if ($count > 20){
            $render->pagedata['many'] = 'true';
            $rows = $inv_aiObj->getList('*', array('inventory_apply_id'=>$apply_id), 0, 20);
        }else {
            $rows = $inv_aiObj->getList('*', array('inventory_apply_id'=>$apply_id), 0, -1);
        }
        $render->pagedata['apply_id'] = $apply_id;
        $render->pagedata['rows'] = $rows;
        return $render->fetch("admin/inventory/apply/item.html");
    }
    
    public $detail_oplog = "操作记录";
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'inventory_apply@console'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('admin/oplog.html');
    }

    var $column_operation = '操作';
    var $column_operation_width = 70;
    public function column_operation($row){
        $apply_id = $row['inventory_apply_id'];
        $inv_aObj = app::get('console')->model('inventory_apply');
        $info = $inv_aObj->dump($apply_id);
        if (empty($info)) {
            return '';
        }
        $id = $info['inventory_apply_id'];
        $fid = isset($_GET['_finder']['finder_id']) ? $_GET['_finder']['finder_id'] : '';
        $return = '';
        if ($info['status'] == 'unconfirmed') {
            $return = ' <a href="index.php?app=console&ctl=admin_inventory_apply&act=do_confirm&p[0]='.$id.'&finder_id='.$fid.'" target="_blank">确认</a>';
            $return .= ' | '.sprintf('<a href="javascript:if (confirm(\'确认要关闭吗？\')){W.page(\'index.php?app=console&ctl=admin_inventory_apply&act=do_close&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">关闭</a>',$id,$fid);
        } elseif ($info['status'] == 'confirming') {
            $return = ' '.sprintf('<a href="javascript:if (confirm(\'确认要关闭吗？\')){W.page(\'index.php?app=console&ctl=admin_inventory_apply&act=do_close&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">关闭</a>',$id,$fid);
        }

        return $return;
    }


    public $detail_useful = "有效期列表";
    public function detail_useful($apply_id){
        $render = app::get('console')->render();
        $inv_aiObj = app::get('console')->model('inventory_apply_items');
        $rows = $inv_aiObj->getList('batch,material_bn', array('inventory_apply_id'=>$apply_id), 0, -1);

      
        foreach($rows as &$v){

            $v['batch'] = json_decode($v['batch'],true);
            
        }


        $render->pagedata['batchs'] = $rows;

        return $render->fetch("admin/useful/item.html");

    }
    
}
?>
