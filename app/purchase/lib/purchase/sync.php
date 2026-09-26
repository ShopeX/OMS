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
 * 回传唯品会平台Lib类
 * 
 * @author wangbiao@shopex.cn
 * @version 0.1
 */
class purchase_purchase_sync
{
    private $_stockoutObj = null;
    private $_stockItemObj = null;
    
    private $_isoInfo = array();
    private $_pickList = array();
    private $_items = array();
    private $_box_items = array();
    
    private $_shop_id = '';
    
    function __construct()
    {
        $this->_stockObj        = app::get('purchase')->model('pick_stockout_bills');
        $this->_stockItemObj    = app::get('purchase')->model('pick_stockout_bill_items');
        
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
            
            $this->_logObj->write_log('update_stockout_bills@ome', $stockout_id, $error_msg);
            
            return false;
        }
        
        // branch_id
        $iso['branch_id'] = intval($iso['branch_id']);
        
        // 获取仓库信息
        $branchInfo = kernel::single('ome_branch')->getBranchInfo($iso['branch_id'], 'branch_bn');
        
        // 获取仓库关联的供应商发货仓库
        $relBranchInfo = app::get('ome')->model('branch_relation')->dump(array('branch_id'=>$iso['branch_id'], 'type'=>'vopjitx'), '*');
        if(empty($relBranchInfo['relation_branch_bn'])){
            $error_msg = '创建JIT出库单时，没有配置【平台仓库编码配置-->唯品会JITX编码】，请检查配置。';
            
            $this->_logObj->write_log('check_stockout_bills@ome', $iso['stockout_id'], $error_msg);
            
            return false;
        }
        
        // 仓库编码
        $iso['branch_bn'] = $branchInfo['branch_bn'];
        
        // 供应商发货仓库编码
        $iso['relation_branch_bn'] = $relBranchInfo['relation_branch_bn'];
        
        $this->_isoInfo = $iso;
        
        //关联拣货单(注意：多个拣货单可合并成一个出库单)
        $sql         = "SELECT b.bill_id, b.pick_no, b.po_id, b.po_bn, b.to_branch_bn FROM sdb_purchase_pick_stockout AS a 
                        LEFT JOIN sdb_purchase_pick_bills AS b ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
        $tempList    = $this->_stockObj->db->select($sql);
        if(empty($tempList))
        {
            $error_msg    = '拣货单不存在';
            
            $this->_logObj->write_log('update_stockout_bills@ome', $stockout_id, $error_msg);
            
            return false;
        }
        
        $po_id       = 0;
        $pickList    = array();
        foreach ($tempList as $key => $val)
        {
            $pickList[$val['bill_id']]    = $val;
            
            $po_id    = $val['po_id'];
            
            //入库仓
            $this->_isoInfo['to_branch_bn']    = $val['to_branch_bn'];
        }
        $this->_pickList    = $pickList;
        
        //获取店铺shop_id
        $sql     = "SELECT shop_id FROM sdb_purchase_order WHERE po_id=". $po_id;
        $poInfo  = $this->_stockObj->db->selectrow($sql);
        if(empty($poInfo))
        {
            $error_msg    = 'PO采购单不存在';
            
            $this->_logObj->write_log('update_stockout_bills@ome', $stockout_id, $error_msg);
            
            return false;
        }
        $this->_shop_id    = $poInfo['shop_id'];
        
        //出仓产品列表
        $sql       = "SELECT a.*, b.bn, b.barcode FROM sdb_purchase_pick_stockout_bill_item_boxs AS a 
                      LEFT JOIN sdb_purchase_pick_stockout_bill_items AS b ON a.stockout_item_id=b.stockout_item_id 
                      WHERE a.stockout_id=". $stockout_id;
        $tempList  = $this->_stockObj->db->select($sql);
        if(empty($tempList))
        {
            $error_msg    = '没有装箱信息';
            
            $this->_logObj->write_log('update_stockout_bills@ome', $stockout_id, $error_msg);
            
            return false;
        }
        
        $box_items    = array();
        foreach ($tempList as $key => $val)
        {
            $bill_id    = $val['bill_id'];
            $po_bn      = $this->_pickList[$bill_id]['po_bn'];
            $pick_no    = $this->_pickList[$bill_id]['pick_no'];
            
            $box_items[]    = array(
                    'vendor_type'=>'COMMON',//供应商类型： 只可传：COMMON或3PL
                    'barcode'=>$val['barcode'],//条形码
                    'box_no'=>$val['box_no'],//供应商箱号
                    'amount'=>$val['num'],//商品数量
                    'pick_no'=>$pick_no,//拣货单号
                    'po_no'=>$po_bn,//po单编码
            );
        }
        $this->_box_items    = $box_items;
        
        unset($iso, $poInfo, $tempList, $box_items);
        
        return true;
    }
    
    /**
     * Api1.修改出仓单信息
     */
    public function editDelivery(&$error_msg)
    {
        set_time_limit(0);
        
        $warehouse = $this->_isoInfo['to_branch_bn'];
        
        //送货批次和要求到货时间
        $delivery_time    = $this->_isoInfo['delivery_time'] .':00';//加入秒
        $arrival_time     = $this->_isoInfo['arrival_time'] .':00';//加入秒
        
        // 供应商发货仓库编码
        $delivery_warehouse = $this->_isoInfo['relation_branch_bn'];
        
        //修改出仓单信息
        $param    = array(
                'stockout_no'=>$this->_isoInfo['stockout_no'],//出库单号
                'storage_no'=>$this->_isoInfo['storage_no'],//入库单号
                'delivery_no'=>$this->_isoInfo['delivery_no'],//运单号
                'warehouse'=>$warehouse,//送货仓库
                'delivery_time'=>$delivery_time,//送货时间
                'arrival_time'=>$arrival_time,//要求到货时间
                'race_time'=>$arrival_time,//预计收货时间
                'delivery_method'=>$this->_isoInfo['dly_mode'],//配送方式
                'carrier_code'=>$this->_isoInfo['carrier_code'],//承运商编码
                'is_air_embargo' => $this->_isoInfo['is_air_embargo'],
                'delivery_warehouse' => $delivery_warehouse, // 供应商发货仓库(如果是承运商=唯品会，则必填)
        );
        
        //超时请求3次
        $requestCount = 0;
        do {
            $rsp    = kernel::single('erpapi_router_request')->set('shop', $this->_shop_id)->purchase_editDelivery($param);
            
            //判断是否请求超时
            if ($rsp['rsp'] != 'fail' || ($rsp['res_ltype'] != 1 && $rsp['res_ltype'] != 2))
            {
                break;
            }
            
            $requestCount++;
        } while ($requestCount<3);
        
        //打标接口失败
        if($rsp['rsp'] == 'fail')
        {
            $error_msg    = '修改出仓单信息失败';
            if($rsp['err_msg'])
            {
                $rsp['err_msg']    = json_decode($rsp['err_msg'], true);
                $error_msg         = $rsp['err_msg']['returnMessage'];
            }
            
            $this->_stockObj->update(array('rsp_code'=>1), array('stockout_id'=>$this->_isoInfo['stockout_id']));
            
            $this->_logObj->write_log('update_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Api2.将出仓明细信息导入到出仓单中
     */
    public function importDeliveryDetail(&$error_msg)
    {
        set_time_limit(0);
        
        $po_nos    = array();
        foreach ($this->_pickList as $key => $val)
        {
            $po_nos[$val['po_bn']]    = $val['po_bn'];
        }
        $po_no    = implode(',', $po_nos);
        
        //组织数据
        $param    = array(
                'stockout_no'=>$this->_isoInfo['stockout_no'],//出库单号
                'po_no'=>$po_no,//po单编号
                'storage_no'=>$this->_isoInfo['storage_no'],//入库单号
        );
        $param['delivery_list']    = $this->_box_items;
        
        //超时请求3次
        $requestCount = 0;
        do {
            $rsp    = kernel::single('erpapi_router_request')->set('shop', $this->_shop_id)->purchase_importDeliveryDetail($param);
            
            //判断是否请求超时
            if ($rsp['rsp'] != 'fail' || ($rsp['res_ltype'] != 1 && $rsp['res_ltype'] != 2))
            {
                break;
            }
            
            $requestCount++;
        } while ($requestCount<3);
        
        //打标接口失败
        if($rsp['rsp'] == 'fail')
        {
            $error_msg    = '导入出仓明细信息失败';
            if($rsp['err_msg'])
            {
                $rsp['err_msg']    = json_decode($rsp['err_msg'], true);
                $error_msg         = $rsp['err_msg']['returnMessage'];
            }
            
            $this->_stockObj->update(array('rsp_code'=>3), array('stockout_id'=>$this->_isoInfo['stockout_id']));
            
            $this->_logObj->write_log('update_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Api3.确认某一条出仓单
     */
    public function confirmDelivery(&$error_msg)
    {
        set_time_limit(0);
        
        $param    = array(
                'stockout_no'=>$this->_isoInfo['stockout_no'],//出库单号
                'storage_no'=>$this->_isoInfo['storage_no'],//入库单号
        );
        
        //超时请求3次
        $requestCount = 0;
        do {
            $rsp    = kernel::single('erpapi_router_request')->set('shop', $this->_shop_id)->purchase_confirmDelivery($param);
            
            //判断是否请求超时
            if ($rsp['rsp'] != 'fail' || ($rsp['res_ltype'] != 1 && $rsp['res_ltype'] != 2))
            {
                break;
            }
            
            $requestCount++;
        } while ($requestCount<3);
        
        //打标接口失败
        if($rsp['rsp'] == 'fail')
        {
            $error_msg    = '确认出仓单失败';
            if($rsp['err_msg'])
            {
                $rsp['err_msg']    = json_decode($rsp['err_msg'], true);
                $error_msg         = $rsp['err_msg']['returnMessage'];
            }
            
            $this->_stockObj->update(array('rsp_code'=>7), array('stockout_id'=>$this->_isoInfo['stockout_id']));
            
            $this->_logObj->write_log('update_stockout_bills@ome', $this->_isoInfo['stockout_id'], $error_msg);
            
            return false;
        }
        
        return true;
    }
}
?>