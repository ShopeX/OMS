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

class console_ctl_admin_branch_product_extend extends desktop_controller
{
    var $name = "仓库库存扩展列表";
    var $workground = "console_center";
    
    function index()
    {
        //商品可视状态
        if (!isset($_POST['visibility'])) {
            $base_filter['visibility'] = 'true';
        }elseif(empty($_POST['visibility'])){
            unset($_POST['visibility']);
        }
        
        //列表新增仓库搜索
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            
            $panel->setId('ome_branch_finder_top');
            $panel->setTmpl('admin/finder/finder_branch_panel_filter.html');
            
            $panel->show('ome_mdl_branch_product', $params);
        }
        
        //获取操作员管辖仓库
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
            if (!isset($_POST['branch_id']) || empty($_POST['branch_id']) ) {
                $branch_ids = $oBranch->getBranchByUser(true);
                if ($branch_ids){
                    $base_filter['branch_id'] = $branch_ids;
                }else{
                    $base_filter['branch_id'] = 'false';
                }
            }else{
                $base_filter['branch_id'] = $_POST['branch_id'];
            }
        }
        
        //action
        $actions = array();
        $actions[] = array(
            'label' => '设置仓库发货时效',
            'submit' => 'index.php?app=console&ctl=admin_branch_product_extend&act=setStoreMode&p[0]='. $_GET['view'],
            'target' => "dialog::{width:700,height:500,title:'设置仓库发货时效'}",
        );
        
        //params
        $params = array(
            'title' => '仓库库存扩展列表',
            'base_filter' => $base_filter,
            'actions' => $actions,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'actions' => $actions,
            'use_buildin_filter' => true,
            'object_method' => array('count'=>'countlist', 'getlist'=>'getlists')
        );
        
        $this->finder('console_mdl_branch_product_extend', $params);
    }
    
    /**
     * 设置仓库发货时效
     */
    public function setStoreMode()
    {
        if (empty($_POST['eid']) || $_POST['isSelectedAll']=='_ALL_'){
            die('暂不支持全选');
        }
        
        //post
        $ids = $_POST['eid'];
        
        //dbschema
        $schema = app::get('ome')->model('branch_product_extend')->get_schema();
        
        //库存模式
        $sellTypes = $schema['columns']['store_sell_type']['type'];
        unset($sellTypes['normal']);
        
        //发货时效
        $sellDelays = $schema['columns']['sell_delay']['type'];
        unset($sellDelays['normal']);
        
        //抖音平台店铺
        $shopObj = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', array('shop_type'=>'luban'));
        
        //默认值
        $data = array(
                'sell_time_hour' => '0',
                'sell_time_minitue' => '0',
                'sell_time_second' => '0',
        );
        
        $this->pagedata['shopList'] = $shopList;
        $this->pagedata['data'] = $data;
        $this->pagedata['sellTypes'] = $sellTypes;
        $this->pagedata['sellDelays'] = $sellDelays;
        
        $this->pagedata['conf_hours'] = array_merge(array('00','01','02','03','04','05','06','07','08','09'), range(10,23));
        $this->pagedata['conf_minitue'] = array_merge(array('00','01','02','03','04','05','06','07','08','09'), range(10,59));
        $this->pagedata['conf_second'] = array_merge(array('00','01','02','03','04','05','06','07','08','09'), range(10,59));
        
        $this->pagedata['GroupList']   = json_encode($ids);
        $this->pagedata['custom_html'] = $this->fetch('admin/branch/product/set_store_mode.html');
        $this->pagedata['request_url'] = 'index.php?app=console&ctl=admin_branch_product_extend&act=saveStoreMode';
        
        //调用desktop公用进度条
        parent::dialog_batch();
    }
    
    /**
     * 保存StoreMode
     * @return mixed 返回操作结果
     */
    public function saveStoreMode()
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $braProExtObj = app::get('ome')->model('branch_product_extend');
        $shopObj = app::get('ome')->model('shop');
        $operLogObj = app::get('ome')->model('operation_log');
        $shopSkuObj = app::get('inventorydepth')->model('shop_skus');
        
        $branchLib = kernel::single('ome_branch');
        
        //post
        $ids = array_map('intval', explode(',', $_POST['primary_id']));
        $ids = array_filter($ids);
        if(empty($ids)){
            echo 'Error: 请先选择操作的单据;';
            exit;
        }
        
        $shop_id = trim($_POST['shop_id']);
        if(empty($shop_id)){
            echo 'Error: 请先选择店铺;';
            exit;
        }
        
        $shopInfo = $shopObj->dump(array('shop_id'=>$shop_id), 'shop_id,shop_bn,node_id,shop_type,name');
        if(empty($shopInfo)){
            echo 'Error: 选择的店铺信息不存在;';
            exit;
        }
        
        $node_id = $shopInfo['node_id'];
        
        $store_sell_type = $_POST['store_sell_type']; //库存销售模式
        $sell_delay = $_POST['sell_delay']; //发货时效
        
        $sell_time_date = $_POST['sell_time_date']; //全款预售截止时间
        $sell_time_hour = $_POST['sell_time_hour']; //小时
        $sell_time_minitue = $_POST['sell_time_minitue']; //分钟
        $sell_time_second = $_POST['sell_time_second']; //秒
        
        //check
        if(empty($store_sell_type)){
            echo 'Error: 请先选择库存销售模式;';
            exit;
        }
        
        if(empty($sell_delay) && $sell_delay !== '0'){
            echo 'Error: 请先选择库存发货时效;';
            exit;
        }
        
        //全款预售模式
        $sell_end_time = 0;
        if($store_sell_type == 'presell'){
            if(empty($sell_time_date)){
                echo 'Error: 请先选择全款预售截止时间;';
                exit;
            }
            
            if($sell_time_hour === '' || $sell_time_minitue === '' || $sell_time_second === ''){
                echo 'Error: 请先选择全额预售时间的小时、分钟、秒;';
                exit;
            }
            
            $sell_end_time = strtotime($sell_time_date . ' ' . $sell_time_hour .':'. $sell_time_minitue .':'. $sell_time_second);
            if(empty($sell_end_time)){
                echo 'Error: 全款预售截止时间格式错误';
                exit;
            }
            
            if($sell_end_time < time()){
                echo 'Error: 全款预售截止时间必须大于当前时间';
                exit;
            }
        }
        
        //result
        $retArr = array(
                'itotal'  => count($ids),
                'isucc'   => 0,
                'ifail'   => 0,
                'err_msg' => array(),
        );
        
        //list
        $dataList = array();
        $branch_ids = array();
        $product_ids = array();
        foreach ($ids as $key => $val)
        {
            $tempData = explode('_', $val);
            $branch_id = $tempData[0];
            $product_id = $tempData[1];
            $extend_id = 0;
            if($tempData[0] && empty($tempData[1])){
                $extend_id = $tempData[0];
            }
            
            //[兼容]已经存在扩展信息
            $extendInfo = array();
            if($extend_id){
                $extendInfo = $braProExtObj->dump(array('eid'=>$extend_id), 'eid,branch_id,product_id');
                
                $branch_id = $extendInfo['branch_id'];
                $product_id = $extendInfo['product_id'];
            }
            
            //check
            if(empty($branch_id) || empty($product_id)){
                continue;
            }
            
            //sdf
            $sdf = array(
                    'branch_id' => $branch_id,
                    'product_id' => $product_id,
                    'store_sell_type' => $store_sell_type,
                    'sell_delay' => $sell_delay,
                    'last_modify' => time(),
            );
            
            //全款预售模式
            if($store_sell_type == 'presell'){
                $sdf['sell_end_time'] = $sell_end_time;
            }
            
            //data
            $branch_ids[$branch_id] = $branch_id;
            $product_ids[$product_id] = $product_id;
            
            //save
            if($extendInfo){
                unset($sdf['branch_id'], $sdf['product_id']);
                
                $eid = $extendInfo['eid'];
                $braProExtObj->update($sdf, array('eid'=>$eid));
            }else{
                $braProExtObj->insert($sdf);
                $eid = $sdf['eid'];
            }
            
            $dataList[$branch_id][$product_id] = $eid;
        }
        
        //普通模式,保存后直接返回
        if($store_sell_type == 'normal'){
            //成功条数
            $retArr['isucc'] = count($ids);
            
            echo json_encode($retArr),'ok.';
            exit;
        }
        
        //获取已绑定抖音平台的区域仓
        $shop_type = 'luban';
        $warehouseList = $branchLib->getLogisticWarehouseIds($shop_type);
        if(empty($warehouseList)){
            echo 'Error: 未获取到抖音平台的区域仓;';
            exit;
        }
        
        //获取基础物料
        //todo：只支持基础物料 与 销售物料货号相同,不支持捆绑商品.
        $material_bns = array();
        $tempList = $basicMaterialObj->getList('bm_id,material_bn', array('bm_id'=>$product_ids));
        foreach ($tempList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            
            $material_bns[$bm_id] = $val['material_bn'];
        }
        
        //获取平台sku_id
        $skuList = array();
        $tempList = $shopSkuObj->getList('id,shop_sku_id,shop_product_bn', array('shop_product_bn'=>$material_bns));
        foreach ($tempList as $key => $val)
        {
            $shop_product_bn = $val['shop_product_bn'];
            
            $skuList[$shop_product_bn] = $val['shop_sku_id'];
        }
        
        //库存销售模式
        $pre_sell_type = 0;
        if($store_sell_type == 'presell'){
            $pre_sell_type = 1;
        }
        
        //发货延迟时间
        $delay_day = intval($sell_delay);
        
        //request
        foreach ($dataList as $branch_id => $items)
        {
            //外部仓库ID
            $out_warehouse_id = $warehouseList[$branch_id]['branch_bn'];
            $branch_name = $warehouseList[$branch_id]['name'];
            
            if(empty($out_warehouse_id)){
                //仓库信息
                $sql = "SELECT name FROM sdb_ome_branch WHERE branch_id=". $branch_id;
                $branchInfo = $braProExtObj->db->selectrow($sql);
                
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('仓库[%s] %s', $branchInfo['name'], '没有关联区域仓');
                
                continue;
            }
            
            foreach ($items as $product_id => $eid)
            {
                //material_bn
                $material_bn = $material_bns[$product_id];
                if(empty($material_bn)){
                    $retArr['ifail']++;
                    $retArr['err_msg'][] = sprintf('货号[%s] %S', $material_bn, '没有获取到基础物料信息');
                    
                    continue;
                }
                
                $sku_id = $skuList[$material_bn];
                if(empty($sku_id)){
                    $retArr['ifail']++;
                    $retArr['err_msg'][] = sprintf('货号[%s] %S', $material_bn, '没有获取到平台sku_id');
                    
                    continue;
                }
                
                //params
                $params = array(
                        'sku_id' => $sku_id, //skuid
                        'shop_product_bn' => $material_bn, //平台商品号
                        'out_warehouse_id' => $out_warehouse_id, //外部仓库id
                        'pre_sell_type' => $pre_sell_type, //0表示现货模式，1表示全款预售模式
                        'delay_day' => $delay_day, //发货延迟时间：0表示当天发货，1表示24小时发货
                );
                
                if($pre_sell_type == 1){
                    $params['pre_sell_end_time'] = $sell_end_time; //全款预售截止时间
                }else{
                    $params['pre_sell_end_time'] = time(); //当前时间
                }
                
                //request
                $saveData = array();
                $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->product_setSkuShipTime($params);
                if($result['rsp'] == 'succ'){
                    $retArr['isucc']++;
                    
                    if($pre_sell_type == 1){
                        $logMsg = sprintf('同步成功：现货模式[%s],发货延迟时间[%s],全款预售截止时间[%s]', $pre_sell_type, $delay_day, $sell_end_time);
                    }else{
                        $logMsg = sprintf('同步成功：现货模式[%s],发货延迟时间[%s]', $pre_sell_type, $delay_day);
                    }
                    
                    $saveData['sync_status'] = 'succ';
                }else{
                    $retArr['ifail']++;
                    $retArr['err_msg'][] = sprintf('仓库['. $branch_name .'] 同步失败：货号[%s]%S', $sku_id, $result['err_msg']);
                    
                    $logMsg = sprintf('同步失败：%s', $result['err_msg']);
                    
                    $saveData['sync_status'] = 'fail';
                }
                
                //update
                $braProExtObj->update($saveData, array('eid'=>$eid));
                
                //log
                $operLogObj->write_log('branch_product_extend@ome', $eid, $logMsg);
                
            }
        }
        
        echo json_encode($retArr),'ok.';
        exit;
    }
    
    /**
     * ajax加载发货时效
     * 
     * @return Json
     */
    public function ajax_get_sell_delay()
    {
        $sell_type = $_POST['sell_type'];
        if(empty($sell_type)){
            echo json_encode(array('res'=>'error'));
            exit;
        }
        
        //dbschema
        $schema = app::get('ome')->model('branch_product_extend')->get_schema();
        
        //库存模式
        $sellTypes = $schema['columns']['store_sell_type']['type'];
        unset($sellTypes['normal']);
        
        //发货时效
        $sellDelays = $schema['columns']['sell_delay']['type'];
        unset($sellDelays['normal']);
        
        //加载发货时效
        if($sell_type == 'presell'){
            $sell_delays = array(
                    0 => array('key'=>'2', 'value'=>'2天'),
                    1 => array('key'=>'3', 'value'=>'3天'),
                    2 => array('key'=>'5', 'value'=>'5天'),
                    3 => array('key'=>'7', 'value'=>'7天'),
                    4 => array('key'=>'10', 'value'=>'10天'),
                    5 => array('key'=>'15', 'value'=>'15天'),
            );
        }else{
            $sell_delays = array(
                    0 => array('key'=>'0', 'value'=>'当天发货'),
                    1 => array('key'=>'1', 'value'=>'24小时发货'),
            );
        }
        
        echo json_encode(array('res'=>'succ', 'sell_delays'=>$sell_delays));
        exit;
    }
}
?>