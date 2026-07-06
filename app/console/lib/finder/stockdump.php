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

class console_finder_stockdump{
    function __construct($app)
    {
        $this->app = $app;
        
        if($_GET['app']!='console'){
            
            unset($this->column_operation);
        }
       
    }
    var $detail_basic = "详情";
    function detail_item($appr_id)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $branchObj = app::get('ome')->model('branch');
        $render = app::get('console')->render();
        
        $appObj = app::get('console')->model('stockdump');
        $itemObj = app::get('console')->model('stockdump_items');
        $items = $itemObj->getList('*',array('stockdump_id'=>$appr_id),0,-1);
        
        if ($items)
        foreach ($items as $key => $item)
        {
            //将商品的显示名称改为后台的显示名称
            $bm_ids          = $basicMaterialObj->dump(array('material_bn'=>$items[$key]['bn']), 'bm_id');
            $product_name    = $basicMaterialLib->getBasicMaterialExt($bm_ids['bm_id']);
            
            $items[$key]['product_name'] = $product_name['material_name'];
            $items[$key]['spec_info'] = $product_name['spec_info'];
            $items[$key]['unit'] = $product_name['unit'];
        }
        //采购价选择判断展示
        $showPurchasePrice = true;
        if (!kernel::single('desktop_user')->has_permission('purchase_price')) {
            $showPurchasePrice = false;
        }
        $render->pagedata['show_purchase_price'] = $showPurchasePrice;

        $render->pagedata['items'] = $items;
        return $render->fetch('admin/stockdump/stockdump_detail_item.html');
    }


    var $column_operation = '操作';
    var $column_operation_width = 90;
    public function column_operation($row){
        $return = '';
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $info = $appObj->dump(array('stockdump_id'=>$stockdump_id),'stockdump_bn,in_status,self_status,confirm_type');

        if($info['in_status'] == 0 && $info['self_status'] == 1 && $info['confirm_type']!='2'){
           
            $return = sprintf('<a href="index.php?app=console&ctl=admin_stockdump&act=cancel&p[0]=%s&p[1]=%s&p[2]=stockdump" target="dialog::{width:500,height:200,title:\'转储单\'}">取消</a>',$stockdump_id,$info['stockdump_bn']);

        }elseif($info['self_status'] == 1 && $info['in_status'] == 1 && $info['confirm_type'] == 1){
            //确认
            $return = sprintf('<a href="javascript:if (confirm(\'入库数量有差异，是否按实际数量确认转储单？\')){W.page(\'index.php?app=console&ctl=admin_stockdump&act=do_save_confirm_type&stockdump_bn=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">确认</a>',$info['stockdump_bn'],$_GET['_finder']['finder_id']);
            $items = $appObj->db->select("SELECT * from sdb_console_stockdump_items where stockdump_id=".intval($stockdump_id)." and (`in_nums`!=`num` or `defective_num`!=0)");
            if($items)$return.=" <a target='_blank' href='index.php?app=console&ctl=admin_stockdump&act=difference&p[0]={$stockdump_id}'>查看差异</a>";
            
        }
        elseif($info['self_status'] == 1 && $info['in_status'] == 1){
                $items = $appObj->db->select("SELECT * from sdb_console_stockdump_items where stockdump_id=".intval($stockdump_id)." and (`in_nums`!=`num` or `defective_num`!=0)");
                if($items)$return.=" <a target='_blank' href='index.php?app=console&ctl=admin_stockdump&act=difference&p[0]={$stockdump_id}'>查看差异</a>";
        }
        
        return $return;
    }

    var $column_confirm_type = '确认状态';
    var $column_confirm_type_width = 80;
    public function column_confirm_type($row){
        $return = '';
        $stockdump_id = $row['stockdump_id'];
        
        $appObj = app::get('console')->model('stockdump');
        $info = $appObj->dump(array('stockdump_id'=>$stockdump_id),'stockdump_bn,in_status,self_status,confirm_type');
        
        if($info['confirm_type'] == 1 ){
            $return = '未确认';
        }elseif($info['confirm_type'] == 2){
            $return = '已确认';
        }else{
            $return = '无需确认';
        }

        return $return;
    }

    var $column_confirm_name = '确认人';
    var $column_confirm_name_width = 80;
    public function column_confirm_name($row){
        $return = '';
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $info = $appObj->dump(array('stockdump_id'=>$stockdump_id),'confirm_name');
        $return = $info['confirm_name'];
        return $return;
    }

    var $column_confirm_time = '确认日期';
    var $column_confirm_time_width = 140;
    public function column_confirm_time($row){
        $return = '';
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $info = $appObj->dump(array('stockdump_id'=>$stockdump_id),'confirm_time');
        if( empty($info['confirm_time']) ){
            $return = '';
        }else{
            $return = date('Y-m-d H:i:s',$info['confirm_time']);
        }
        return $return;
    }

    var $column_op_time = ' 处理时长';
    var $column_op_time_width = 80;
    public function column_op_time($row){
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $approData = $appObj->getList("in_status,self_status,create_time,response_time",array('stockdump_id'=>$stockdump_id));
        $ome_func = kernel::single("ome_func");
        //如果单据是全部入库 or 接单失败 or 取消 or 关闭 时间计算停止
        if($approData[0]['in_status'] ==9 || $approData[0]['in_status'] ==10 || $approData[0]['in_status'] ==11 || $approData[0]['self_status'] ==0 ||  $approData[0]['self_status'] ==2){
            if($approData[0]['response_time']){
                $end_time = $approData[0]['response_time'];
                $timeData = $ome_func->toTimeDiff($end_time,$approData[0]['create_time']);
                return $timeData['d']."天".$timeData['h']."小时".$timeData['m']."分";
            }
        }else{
            $end_time = time();
            $timeData = $ome_func->toTimeDiff($end_time,$approData[0]['create_time']);
            return $timeData['d']."天".$timeData['h']."小时".$timeData['m']."分";
        }
    }
    
    var $column_time_remind = ' 超时提醒';
    var $column_time_remind_width = 80;
    public function column_time_remind($row){
        $stockdump_remind_setting_days = app::get("omestorage")->getConf("stockdump_remind_setting_days");
        if($stockdump_remind_setting_days == 'nosetting')
            return ;
        elseif(empty($stockdump_remind_setting_days))
            $stockdump_remind_setting_days = 3;
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $approData = $appObj->getList("in_status,self_status,create_time,response_time",array('stockdump_id'=>$stockdump_id));
        $ome_func = kernel::single("ome_func");
        //如果单据不是已入库 or 接单失败 or 取消 or 关闭 判断是否超时
        if($approData[0]['in_status'] !=9 && $approData[0]['in_status'] !=10 && $approData[0]['in_status'] !=11 && $approData[0]['self_status'] !=0 &&  $approData[0]['self_status'] !=2){
            if(time()-$approData[0]['create_time']>$stockdump_remind_setting_days*24*3600) return '<img src="'.app::get("ome")->res_url.'/warn.png" class="x-barcode" width="20pt" height="20px" />';
        }
    }

    var $column_sync_status = ' 同步状态';
    var $column_sync_status_width = 80;
    public function column_sync_status($row){
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $approData = $appObj->getList("sync_status",array('stockdump_id'=>$stockdump_id));
        switch($approData[0]['sync_status']){
            case "nosync":
                return "-";
                break;
            case "running":
                
                return '运行中';
                break;
            case "fail":
                
                return '失败';
                break;
            case "success":
                return "<font style='color:green'>成功</font>";
                break;
        }
    }

    var $column_type = '单据状态';
    var $column_type_width = 80;
    public function column_type($row){
        $return = '';
        $stockdump_id = $row['stockdump_id'];
        
        $in_status = array (
            0 => '未入库',
            1 => '已入库',
            2 => '失败',
           
        );

        $appObj = app::get('console')->model('stockdump');
        $info = $appObj->dump(array('stockdump_id'=>$stockdump_id),'in_status,self_status');
        
        if($info['self_status'] == 0){
            $return = '已取消';
        }elseif($info['self_status'] == 2){
            $return = '已关闭';
        }else{
            $return = $in_status[$info['in_status']];
        }

        return $return;
    }

    

    var $column_to_branch_id = '调入仓库';
    var $column_to_branch_id_width = 120;
    public function column_to_branch_id($row){
        $return = '';
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $branchObj = app::get('ome')->model('branch');
        $appro_data = $appObj->dump(array('stockdump_id'=>$stockdump_id),'stockdump_bn,to_branch_id');
        $branch_data = $branchObj->dump(array('branch_id'=>$appro_data['to_branch_id']),'name');
        $return = $branch_data['name'];

        return $return;
    }

    var $column_from_branch_name = '调出仓库';
    var $column_from_branch_name_width = 120;
    public function column_from_branch_name($row){
        $return = '';
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $branchObj = app::get('ome')->model('branch');
        $appro_data = $appObj->dump(array('stockdump_id'=>$stockdump_id),'stockdump_bn,from_branch_id');
        $branch_data = $branchObj->dump(array('branch_id'=>$appro_data['from_branch_id']),'name');
        $return = $branch_data['name'];

        return $return;
    }

    var $column_memo = '备注';
    var $column_memo_width = 200;
    public function column_memo($row){
        $return = '';
        $stockdump_id = $row['stockdump_id'];
        $appObj = app::get('console')->model('stockdump');
        $appro_data = $appObj->dump(array('stockdump_id'=>$stockdump_id),'memo');
        $return = $appro_data['memo'];

        return $return;
    }

}
?>