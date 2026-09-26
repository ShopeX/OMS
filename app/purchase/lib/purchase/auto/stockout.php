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
 * 唯品会JIT出库单自动审核Lib类
 * 
 * @author wangbiao@shopex.cn
 * @version 0.1
 */
class purchase_purchase_auto_stockout
{
    private $_stockoutObj = null;
    private $_stockItemObj = null;
    private $_logObj = null;
    private $_stockLib = null;
    
    private $_isoInfo = array();
    private $_items = array();
    private $_config = '';
    
    private $_shop_id = '';
    private $_branch_id = 0;
    
    function __construct()
    {
        $this->_stockObj        = app::get('purchase')->model('pick_stockout_bills');
        $this->_stockItemObj    = app::get('purchase')->model('pick_stockout_bill_items');
        $this->_stockLib        = kernel::single('purchase_purchase_stockout');
        
        $this->_logObj          = app::get('ome')->model('operation_log');
    }
    
    /**
     * 初始化出库单信息
     *
     * @param array $params 传入参数
     * @param string $error_msg 错误信息
     */
    public function _initStockoutIfo($stockout_id, &$error_msg)
    {
        //出库单
        $iso    = $this->_stockObj->dump(array('stockout_id'=>$stockout_id), '*');
        if(empty($iso))
        {
            $error_msg    = '出库单不存在';
            return false;
        }
        
        //关联拣货单
        $sql         = "SELECT b.pick_no, b.po_id, b.po_bn, b.to_branch_bn, b.create_time AS pick_create_time FROM sdb_purchase_pick_stockout AS a 
                        LEFT JOIN sdb_purchase_pick_bills AS b ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
        $pickInfo    = $this->_stockObj->db->selectrow($sql);
        if(empty($pickInfo))
        {
            $error_msg    = '拣货单不存在';
            return false;
        }
        
        $iso               = array_merge($iso, $pickInfo);
        $this->_isoInfo    = $iso;
        $this->_branch_id  = intval($iso['branch_id']);
        
        //获取店铺shop_id
        $sql     = "SELECT shop_id FROM sdb_purchase_order WHERE po_id=". $this->_isoInfo['po_id'];
        $poInfo  = $this->_stockObj->db->selectrow($sql);
        if(empty($poInfo))
        {
            $error_msg    = 'PO采购单不存在';
            return false;
        }
        $this->_shop_id    = $poInfo['shop_id'];
        
        //出库单明细
        $sql    = "SELECT a.stockout_item_id, a.bn, a.num, b.bm_id AS product_id FROM sdb_purchase_pick_stockout_bill_items AS a 
                   LEFT JOIN sdb_material_basic_material AS b ON a.bn=b.material_bn WHERE a.stockout_id=". $stockout_id;
        $itemList     = $this->_stockItemObj->db->select($sql);
        if(empty($itemList))
        {
            $error_msg    = '没有出库单明细';
            return false;
        }
        $this->_items = $itemList;
        
        //应用的店铺
        $setShopObj     = app::get('purchase')->model('setting_shop');
        $setShopInfo    = $setShopObj->dump(array('shop_id'=>$this->_shop_id), 'sid');
        
        //[读取]自动审核配置
        $setObj    = app::get('purchase')->model('setting');
        $config    = $setObj->dump(array('sid'=>$setShopInfo['sid']), '*');
        $this->_config    = $config;
        
        //自动更新承运商、配送方式
        $carrier_code    = $this->_config['carrier_code'];
        $dly_mode        = $this->_config['dly_mode'];
        
        if(empty($carrier_code))
        {
            $error_msg    = '没有配置承运商';
            return false;
        }
        
        if(empty($dly_mode))
        {
            $error_msg    = '没有配置配送方式';
            return false;
        }
        
        //送货批次和要求到货时间
        $dataTime    = $this->_stockLib->reckonTiem($dly_mode);
        
        //更新出库单
        $data    = array(
                'stockout_id' => $this->_isoInfo['stockout_id'],
                'dly_mode' => $dly_mode,
                'carrier_code' => $carrier_code,
                'delivery_time' => $dataTime['delivery_time'],//送货批次
                'arrival_time' => $dataTime['arrival_time'],//要求到货时间
        );
        $result    = $this->_stockLib->update_stockout($data);
        if(!$result)
        {
            $error_msg    = '更新出库单信息失败';
            return false;
        }
        
        //承运商名称
        $data['carrier_name']    = $this->_stockLib->getCarrierCode('', $data['carrier_code']);
        
        $this->_isoInfo    = array_merge($this->_isoInfo, $data);
        
        unset($iso, $poInfo, $itemList, $config);
        
        return true;
    }
    
    /**
     * 自动审核出库单
     */
    public function combine(&$error_msg)
    {
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $bProductObj    = app::get('ome')->model('branch_product');
        $inventoryLib   = kernel::single('taoguaninventory_inventorylist');
        $is_install     = app::get('taoguaninventory')->is_installed();
        $is_check       = false;//是否检查盘点
        
        // 获取仓库关联的供应商发货仓库
        $relBranchInfo = app::get('ome')->model('branch_relation')->dump(array('branch_id'=>$this->_branch_id, 'type'=>'vopjitx'), '*');
        if(empty($relBranchInfo['relation_branch_bn'])){
            $error_msg = '创建JIT出库单时，没有配置【平台仓库编码配置-->唯品会JITX编码】，请检查!';
            
            $this->_logObj->write_log('check_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
            
            return false;
        }
        
        // 供应商发货仓库编码
        $delivery_warehouse = $relBranchInfo['relation_branch_bn'];
        
        //检查货品对应出库仓库存
        foreach ($this->_items as $key => $val)
        {
            if(empty($val['product_id']))
            {
                $error_msg    = '货号：'. $val['bn'] .'系统中不存在,请先添加!';
                
                $this->_logObj->write_log('check_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
                
                return false;
            }
            
            $storeInfo    = $bProductObj->dump(array('product_id'=>$val['product_id'], 'branch_id'=>$this->_branch_id), 'store,store_freeze');
            
            //根据仓库ID、基础物料ID获取该物料仓库级的预占
            $storeInfo['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($val['product_id'], $this->_branch_id);
            
            if(($storeInfo['store'] - $storeInfo['store_freeze']) < $val['num'])
            {
                $error_msg    = '货号：'. $val['bn'] .'库存不足';
                
                $this->_logObj->write_log('check_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
                
                return false;
            }
            
            //盘点商品检查
            if($is_check && $is_install)
            {
                $check_inventory    = $inventoryLib->checkproductoper($val['product_id'], $this->_branch_id);
                if(!$check_inventory)
                {
                    $error_msg    = '货品：'. $val['bn'] .' 正在盘点中,不可以出入库操作!';
                    
                    $this->_logObj->write_log('check_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
                    
                    return false;
                }
            }
        }
        
        //请求vop创建出库单
        $storage_no    = $this->_isoInfo['storage_no'];
        if(empty($storage_no))
        {
            //格式化时间
            $this->_isoInfo['delivery_time']    = $this->_isoInfo['delivery_time'] .':00';
            $this->_isoInfo['arrival_time']     = $this->_isoInfo['arrival_time'] .':00';
            
            //API创建出库单
            $param    = array(
                    'po_no'=>$this->_isoInfo['po_bn'],//po号
                    'delivery_no'=>$this->_isoInfo['stockout_no'],//运单号
                    'warehouse'=>$this->_isoInfo['to_branch_bn'],//送货仓库
                    'delivery_method'=>$this->_isoInfo['dly_mode'],//配送方式
                    'carrier_name'=>$this->_isoInfo['carrier_name'],//承运商名称
                    'carrier_code'=>$this->_isoInfo['carrier_code'],//承运商编码
                    'delivery_time'=>$this->_isoInfo['delivery_time'],//送货批次
                    'arrival_time'=>$this->_isoInfo['arrival_time'],//要求到货时间
                    'delivery_warehouse' => $delivery_warehouse, // 供应商发货仓库(如果是承运商=唯品会，则必填)
            );
            
            //超时请求3次
            $requestCount = 0;
            do {
                $rsp    = kernel::single('erpapi_router_request')->set('shop', $this->_shop_id)->purchase_createDelivery($param);
                
                //判断是否请求超时
                if ($rsp['rsp'] != 'fail' || ($rsp['res_ltype'] != 1 && $rsp['res_ltype'] != 2))
                {
                    break;
                }
                
                $requestCount++;
            } while ($requestCount<3);
            
            if($rsp['rsp'] == 'fail')
            {
                $rsp['err_msg']    = json_decode($rsp['err_msg'], true);
                $error_msg         = $rsp['err_msg']['returnMessage'];
                $error_msg         = ($error_msg ? $error_msg : '创建出仓单失败');
                
                $this->_logObj->write_log('check_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
                
                return false;
            }
            
            //防止出库单号没有创建成功
            $storage_no    = $rsp['delivery']['storage_no'];
            if($rsp['rsp'] == 'succ' && empty($storage_no))
            {
                $error_msg    = '没有创建出仓单号';
                
                $this->_logObj->write_log('check_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
                
                return false;
            }
        }
        
        //更新为已审核
        $data    = array('action'=>'is_check', 'stockout_id'=>$this->_isoInfo['stockout_id'], 'storage_no'=>$storage_no, 'confirm_status'=>2);
        $result    = $this->_stockLib->update_stockout($data);
        if(!$result)
        {
            $error_msg    = '更新出库单失败';
            
            $this->_logObj->write_log('check_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
            
            return false;
        }
        
        //库存冻结
        $result      = $this->_stockLib->freeze($this->_isoInfo['stockout_id'], $this->_branch_id, $error_msg);
        if(!$result)
        {
            $this->_logObj->write_log('check_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
            
            return false;
        }
        
        //推送出库信息给仓库
        kernel::single('console_event_trigger_vopstockout')->create(array('iso_id'=>$this->_isoInfo['stockout_id']), false);

        // 推送信息给唯品会时效订单结果反馈
        kernel::single('console_event_trigger_vopstockout')->occupied_order_feedback(['stockout_id'=>$this->_isoInfo['stockout_id']], false);
        
        return true;
    }
}
?>