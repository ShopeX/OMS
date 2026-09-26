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
 * 唯品会JIT出库单Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 0.1
 */
class purchase_purchase_stockout
{
    const _io_type    = 'VOPSTOCKOUT';//出库类型

    function __construct()
    {
        $this->_stockObj        = app::get('purchase')->model('pick_stockout_bills');
        $this->_stockItemObj    = app::get('purchase')->model('pick_stockout_bill_items');

        $this->_logObj          = app::get('ome')->model('operation_log');
    }

    /**
     * 生成出库单号
     * $type 类型code:900
     */
    function get_iostockorder_bn($type=900, $num = 0)
    {
        $iostock_instance  = kernel::single('siso_receipt_iostock');

        $kt                = $iostock_instance->iostock_rules($type);
        $iostockorder_type = 'stockoutbills-'. $type;

        if($num >= 1){
            $num++;
        }else{
            $sql = "SELECT id FROM sdb_ome_concurrent WHERE `type`='". $iostockorder_type ."' 
                    AND `current_time`>'". strtotime(date('Y-m-d')) ."' AND `current_time`<=". time() ." order by id desc limit 0,1";
            $arr = $this->_stockObj->db->select($sql);
            $id = $arr[0]['id'];
            if($id)
            {
                $num = substr($id,-6);
                $num = intval($num)+1;
            }else{
                $num = 1;
            }
        }

        $po_num            = str_pad($num, 6, '0', STR_PAD_LEFT);
        $iostockorder_bn   = $kt . date('Ymd') . $po_num;

        $conObj    = app::get('ome')->model('concurrent');
        if($conObj->is_pass($iostockorder_bn, $iostockorder_type))
        {
            return $iostockorder_bn;
        } else {
            if($num > 999999){
                return false;
            }else{
                return $this->get_iostockorder_bn($type, $num);
            }
        }
    }

    /**
     * 创建出库单
     */
    function create_stockout($sdf)
    {
        $reStockObj    = app::get('purchase')->model('pick_stockout');

        $product_list    = $sdf['detail'];
        $bill_ids        = $sdf['bill_ids'];
        unset($sdf['bill_ids']);

        //是否自动
        $log_str    = ($sdf['is_auto'] ? '出库单自动创建成功' : '出库单创建成功');
        $err = '';

        //唯品会出库类型
        $iostock_instance  = kernel::single('siso_receipt_iostock');
        $vop_type          = $iostock_instance::VOP_STOCKOUT;

        //出库单号
        $sdf['stockout_no']    = $this->get_iostockorder_bn($vop_type);

        $sdf['create_time']    = time();
        $sdf['last_modified']  = time();
        unset($sdf['detail'], $sdf['is_auto']);

        //开启事务
        $this->_stockObj->db->beginTransaction();

        if(!$this->_stockObj->save($sdf))
        {
            //事务回滚
            $this->_stockObj->db->rollBack();
            return false;
        }

        $labelLib  = kernel::single('ome_bill_label');

        $billItemIdArr = array_column($product_list, 'bill_item_id');
        $labelList     = $labelLib->getLabelFromOrder($billItemIdArr, 'pick_bill_item');

        //保存明细
        foreach ($product_list as $key => $item)
        {
            $item['stockout_id']    = $sdf['stockout_id'];

            if(!$this->_stockItemObj->save($item))
            {
                //事务回滚
                $this->_stockObj->db->rollBack();
                return false;
            }
            // 检测拣货单是否有标签，如果有，给出库单也打上对应标签
            if ($labelList[$item['bill_item_id']]) {
                if (in_array('quality_check', array_column($labelList[$item['bill_item_id']], 'label_code'))) {
                    kernel::single('ome_bill_label')->markBillLabel($item['stockout_item_id'], '', 'quality_check', 'pick_stockout_bill_item', $err);
                }
                if (in_array('priority_delivery', array_column($labelList[$item['bill_item_id']], 'label_code'))) {
                    kernel::single('ome_bill_label')->markBillLabel($item['stockout_item_id'], '', 'priority_delivery', 'pick_stockout_bill_item', $err);
                }
            }
        }

        $labelList = $labelLib->getLabelFromOrder($bill_ids, 'pick_bill');
        //拣货出库单关联
        foreach ($bill_ids as $key => $bill_id)
        {
            $data    = array('bill_id'=>$bill_id, 'stockout_id'=>$sdf['stockout_id']);

            if(!$reStockObj->insert($data))
            {
                //事务回滚
                $this->_stockObj->db->rollBack();
                return false;
            }

            // 检测拣货单是否有标签，如果有，给出库单也打上对应标签
            if ($labelList[$bill_id]) {
                if (in_array('quality_check', array_column($labelList[$bill_id], 'label_code'))) {
                    $labelLib->markBillLabel($sdf['stockout_id'], '', 'quality_check', 'pick_stockout_bill', $err);
                }
                if (in_array('priority_delivery', array_column($labelList[$bill_id], 'label_code'))) {
                    $labelLib->markBillLabel($sdf['stockout_id'], '', 'priority_delivery', 'pick_stockout_bill', $err);
                }
            }
        }

        //事务确认
        $this->_stockObj->db->commit();

        //增加出库单创建日志
        $this->_logObj->write_log('create_stockout_bills@ome', $sdf['stockout_id'], $log_str);

        return $sdf['stockout_no'];
    }

    /**
     * 更新拣货单
     */
    function update_stockout($sdf)
    {
        //开启事务
        $tran = $this->_stockObj->db->beginTransaction();

        //更新类型(is_auto为系统自动)
        $log_str     = ($sdf['is_auto'] ? '出库单自动编辑成功' : '出库单编辑成功');
        $log_type    = 'edit_stockout_bills@ome';
        $err = '';

        if($sdf['action'] == 'is_check')
        {
            $log_type    = 'check_stockout_bills@ome';
            $log_str     = ($sdf['is_auto'] ? '出库单自动审核成功' : '出库单审核成功');
            $log_str     .= ',获取入库单号：'. $sdf['storage_no'];

            // 处理拣货单明细的详单，并释放拣货单冻结
            if ($sdf['confirm_status'] == '2') {
                $err = [];
                $rs = kernel::single('purchase_purchase_inventory')->process($sdf['stockout_id'], $err);
                if (!$rs) {
                    $this->_stockObj->db->rollBack();
                    return false;
                }
            }
        }
        elseif($sdf['action'] == 'is_update')
        {
            $log_type    = 'update_stockout_bills@ome';
            $log_str     = ($sdf['is_auto'] ? '出库单自动更新成功' : '出库单更新成功');
        }
        
        $sdf['check_time']  = time();
        $sdf['last_modified']  = time();
        unset($sdf['detail'], $sdf['is_auto'], $sdf['action']);

        if(!$this->_stockObj->save($sdf))
        {
            //事务回滚
            $this->_stockObj->db->rollBack();
            return false;
        }
        else
        {
            //事务确认
            $this->_stockObj->db->commit($tran);
        }

        //增加出库单更新日志
        $this->_logObj->write_log($log_type, $sdf['stockout_id'], $log_str);

        return true;
    }

    /**
     * 单据状态
     */
    function getBillStatus($val = '')
    {
        $status    = array(1=>'新建', '取消', '完成');

        if($val)
        {
            return $status[$val];
        }

        return $status;
    }

    /**
     * 出库状态
     * @param intval $val
     * @return
     */
    function getStockoutStatus($val = '')
    {
        $status    = array(1=>'未出库', '部分出库', '全部出库');

        if($val)
        {
            return $status[$val];
        }

        return $status;
    }

    /**
     * 配送方式
     * @param intval $val 1:汽运,2:空运
     * @return
     */
    function getDlyMode($val='')
    {
        $status    = array(1=>'汽运', 2=>'空运');

        if($val)
        {
            return $status[$val];
        }

        return $status;
    }

    /**
     * 承运商
     */
    function getCarrierCode($shop_id=null, $carrier_code=null)
    {
        $carrierObj    = app::get('purchase')->model('carrier');

        //filter
        $filter    = array('carrier_isvalid'=>1, 'disabled'=>'false');
        
        // shop_id
        if($shop_id)
        {
            $filter['shop_id']    = $shop_id;
        }
        
        // carrier_code
        if($carrier_code)
        {
            $filter['carrier_code']    = $carrier_code;
        }

        //getList
        $tempData      = $carrierObj->getList('*', $filter, 0, -1, 'carrier_code ASC');

        $carrier_list    = array();
        if($tempData)
        {
            foreach ($tempData as $key => $val)
            {
                $code    = $val['carrier_code'];

                $carrier_list[$code]    = $val['carrier_name'];
            }
        }

        //指定输出
        if($carrier_code)
        {
            return $carrier_list[$carrier_code];
        }

        return $carrier_list;
    }

    /**
     * 出库库存检查
     *
     * @param intval $stockout_id 出库单ID
     * @param intval $branch_id 仓库ID
     * @param string $error_msg
     * @param bool $is_check  是否检查盘点
     *
     * @return bool
     */
    function checkBranchStock($stockout_id, $branch_id, &$error_msg, $is_check=false)
    {
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');

        $bProductObj    = app::get('ome')->model('branch_product');
        $inventoryLib   = kernel::single('taoguaninventory_inventorylist');
        $is_install     = app::get('taoguaninventory')->is_installed();

        if(empty($stockout_id) || empty($branch_id))
        {
            $error_msg    = '无效操作,请检查';
            return false;
        }

        //出库单明细
        $sql    = "SELECT a.stockout_item_id, a.bn, a.num, b.bm_id AS product_id FROM sdb_purchase_pick_stockout_bill_items AS a 
                   LEFT JOIN sdb_material_basic_material AS b ON a.bn=b.material_bn WHERE a.stockout_id=". $stockout_id ." AND a.is_del='false'";
        $itemList = $bProductObj->db->select($sql);

        $temp_bns     = array();
        $temp_nums    = array();
        foreach ($itemList as $key => $val)
        {
            if(empty($val['product_id']))
            {
                $error_msg    = '货号：'. $val['bn'] .' 系统中不存在,请先添加!';
                return false;
            }

            $temp_bns[$val['product_id']]    = $val['bn'];

            //累加同货号的商品数量
            $temp_nums[$val['product_id']]    += $val['num'];
        }

        //库存检查
        foreach ($temp_nums as $product_id => $num)
        {
            $storeInfo    = $bProductObj->dump(array('product_id'=>$product_id, 'branch_id'=>$branch_id), 'store,store_freeze');

            //根据仓库ID、基础物料ID获取该物料仓库级的预占
            $storeInfo['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($product_id, $branch_id);

            if(($storeInfo['store'] - $storeInfo['store_freeze']) < $num)
            {
                $error_msg    = '货号：'. $temp_bns[$product_id] .' 库存不足';
                return false;
            }

            //盘点商品检查
            if($is_check && $is_install)
            {
                $check_inventory    = $inventoryLib->checkproductoper($product_id, $branch_id);
                if(!$check_inventory)
                {
                    $error_msg    = '货品：'. $temp_bns[$product_id] .' 正在盘点中,不可以出入库操作!';
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 出库冻结库存
     *
     * @param intval $stockout_id 出库单ID
     * @param intval $branch_id 仓库ID
     * @param string $error_msg
     *
     * @return bool
     */
    function freeze($stockout_id, $branch_id, &$error_msg)
    {
        if(empty($stockout_id) || empty($branch_id))
        {
            $error_msg    = '无效操作,请检查';
            return false;
        }

        //$basicMaterialStock  = kernel::single('material_basic_material_stock');
        //$libBranchProduct    = kernel::single('ome_branch_product');

        //出库单明细
        $sql    = "SELECT a.stockout_item_id, a.bn, a.num, b.bm_id AS product_id FROM sdb_purchase_pick_stockout_bill_items AS a
                   LEFT JOIN sdb_material_basic_material AS b ON a.bn=b.material_bn WHERE a.stockout_id=". $stockout_id ." AND a.is_del='false'";
        $itemList = $this->_stockObj->db->select($sql);

        //库存管控处理
        $storeManageLib    = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id'=>$branch_id));

        $params    = array();
        $params['node_type'] = 'checkVopstockout';
        $params['params']    = array('stockout_id'=>$stockout_id, 'branch_id'=>$branch_id);
        $params['params']['items'] = $itemList;
        
        $err_msg = '';
        $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
        if(!$processResult)
        {
            $error_msg    = $err_msg;
            return false;
        }

        return true;
    }

    /**
     * 组织入库详情和明细
     * @param intval $stockout_id 出库单ID
     * @return Array
     */
    function get_iostockData($stockout_id, &$error_msg)
    {
        if(empty($stockout_id))
        {
            $error_msg    = '无效操作,请检查';
            return false;
        }

        $pickObj    = app::get('purchase')->model('pick_bills');
        $shopObj = app::get('ome')->model('shop');

        //出库单
        $row    = $this->_stockObj->dump(array('stockout_id'=>$stockout_id), '*');

        //关联拣货单
        $sql            = "SELECT b.pick_no, b.po_id, b.po_bn, b.to_branch_bn FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b
                           ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
        $pickInfo    = $this->_stockObj->db->selectrow($sql);

        //承运商名称
        $carrier_name    = $this->getCarrierCode('', $row['carrier_code']);

        //OMS出库仓库
        $branchObj    = app::get('ome')->model('branch');
        $branchInfo   = $branchObj->dump(array('branch_id'=>$row['branch_id']), 'branch_bn,storage_code,owner_code');

        //组织数据
        $data = array(
                'io_bn' =>$row['stockout_no'],//出库单号
                'storage_no'=>$row['storage_no'],//入库单号
                'dly_mode'=>$row['dly_mode'],//配送方式 1空运 2汽运
                'carrier_code'=>$row['carrier_code'],//承运商编码
                'carrier_name'=>$carrier_name,//承运商名称
                'arrival_time'=>$row['arrival_time'],//要求到货时间
                'to_branch_no'=>$pickInfo['to_branch_bn'],//唯品会入库仓编码
                'memo'=>'',//备注
                'create_time'=>$row['create_time'],//单据创建时间
                'branch_id'=>$row['branch_id'],
                'branch_bn'=>$branchInfo['branch_bn'],//仓库编号
                'owner_code'=>$branchInfo['owner_code'],//货主编码
                'storage_code'=>$branchInfo['storage_code'],//仓库编号
                'io_type'=>self::_io_type,//出库类型
        );

        if ($this->is_vopcp($row['carrier_code'])) {
            $data['delivery_no'] = $row['delivery_no'];
        }

        
        //新增来源店铺编码
        $sql = "SELECT po_id, shop_id FROM sdb_purchase_order WHERE po_id=". $pickInfo['po_id'];
        $poInfo = $this->_stockObj->db->selectrow($sql);
        if($poInfo){
            $shopInfo = $shopObj->dump($poInfo['shop_id'], 'shop_bn,name');
            $data['shop_code'] = $shopInfo['shop_bn'];
        }

        //仓库信息
        $warehouseObj    = app::get('purchase')->model('warehouse');
        $branchInfo      = $warehouseObj->dump(array('branch_bn'=>$pickInfo['to_branch_bn']), '*');

        $area = $branchInfo['area'];
        $area = explode(':', $area);
        $area = explode('/', $area[1]);

        $data['receiver_name']       = $branchInfo['uname'];
        $data['receiver_phone']      = $branchInfo['phone'];
        $data['receiver_mobile']     = $branchInfo['mobile'];
        $data['receiver_email']      = $branchInfo['email'];
        $data['receiver_zip']        = $branchInfo['zip'];// TODO: 收货人邮政编码

        $data['receiver_country']    = '中国';
        $data['receiver_state']      = $area[0];// TODO: 所在省
        $data['receiver_city']       = $area[1];// TODO: 所在市
        $data['receiver_district']   = $area[2];// TODO: 所在县（区）
        $data['receiver_address']    = $branchInfo['address'];// TODO: 收货地址

        //出库单明细
        $iso_items      = array();
        $item_pick_num  = 0;

        $_check_items = [];
        $itemList   = $this->_stockItemObj->getList('*', array('stockout_id'=>$stockout_id, 'is_del'=>'false'), 0, -1);
        foreach ($itemList as $key => $val)
        {
            //拣货单号和PO单号
            $bill_id     = $val['bill_id'];
            $pickInfo    = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');

            $iso_items[]    = array(
                            'bill_id'=>$bill_id,
                            'po_bn'=>$pickInfo['po_bn'],//PO单号
                            'pick_bn'=>$pickInfo['pick_no'],//拣货单号
                            'bn'=>$val['bn'],//货品编码
                            'barcode'=>$val['barcode'],//条形码
                            'name'=>$val['product_name'],//货品名称
                            'size'=>$val['size'],//尺寸
                            'num'=>$val['num'],//数量
                            'unit_price'=>$val['price'],//成本价
                            'market_price'=>$val['market_price'],//市场价
            );

            $item_pick_num += $val['num'];

            $_check_items[$bill_id]['pick_bn'] = $pickInfo['pick_no'];
            $_check_items[$bill_id]['barcode_list'][] = $val['barcode'];
        }
        $data['items']    = $iso_items;

        // 唯品会重点检查
        $checkMdl = app::get('purchase')->model('pick_bill_check_items');
        $billItemMdl = app::get('purchase')->model('pick_bill_items');
        foreach ($_check_items as $ik => $iv) {
            $check_params = [
                'bill_id'        =>  $ik,
                'barcode_list'   =>  $iv['barcode_list'],
            ];
            $check_res = $checkMdl->getCheckList($check_params);
            if ($check_res) {
                $data['quality_check'][$iv['pick_bn']] = $check_res;
            }

            // 优先发货
            $bill_item_id_arr = $billItemMdl->getList('bill_item_id', ['bill_id'=>$ik, 'barcode|in'=>$iv['barcode_list']]);
            $bill_item_id_arr = array_column($bill_item_id_arr, 'bill_item_id');
            if ($bill_item_id_arr) {
                $lable_arr = kernel::single('ome_bill_label')->getLabelFromOrder($bill_item_id_arr, 'pick_bill_item');
                $bill_item_id_arr = array_keys($lable_arr);
                $_tmp_item_bn = $billItemMdl->getList('barcode', ['bill_id'=>$ik, 'bill_item_id|in'=>$bill_item_id_arr]);
                if ($_tmp_item_bn) {
                    $data['action_list']['priorityDelivery'] = array_column($_tmp_item_bn, 'barcode');
                }
            }
        }

        //拣货货品的种类数量(整单维护统计)
        $data['sku_pick_num']    = count($iso_items);

        //总拣货数量(整单维护统计)
        $data['item_pick_num']    = $item_pick_num;

        return $data;
    }

    /**
     * 检查装箱商品库存
     *
     * @param Array $product_info 商品列表
     * @param intval $branch_id 仓库ID
     * @param string $error_msg
     * @param bool $is_check  是否检查盘点
     *
     * @return bool
     */
    function checkBoxStock($product_info, $branch_id, &$error_msg, $is_check=false)
    {
        //$basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $bProductObj      = app::get('ome')->model('branch_product');
        $inventoryLib     = kernel::single('taoguaninventory_inventorylist');

        //是否安装taoguaninventory
        $is_install     = app::get('taoguaninventory')->is_installed();

        if(empty($product_info) || empty($branch_id))
        {
            $error_msg    = '无效操作,请检查';
            return false;
        }

        //库存检查
        foreach ($product_info as $bn => $num)
        {
            $product_info    = $basicMaterialObj->dump(array('material_bn'=>$bn), 'bm_id');
            if(empty($product_info))
            {
                $error_msg    = '货号：'. $bn .'系统中不存在,请先添加!';
                return false;
            }

            $product_id   = $product_info['bm_id'];
            $storeInfo    = $bProductObj->dump(array('product_id'=>$product_id, 'branch_id'=>$branch_id), 'store,store_freeze');

            //根据仓库ID、基础物料ID获取该物料仓库级的预占
            //$storeInfo['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($product_id, $branch_id);

            if($storeInfo['store'] < $num)
            {
                $error_msg    = '货号：'. $bn .' 出库数量不可大于库存数量!';
                return false;
            }

            //盘点商品检查
            if($is_check && $is_install)
            {
                $check_inventory    = $inventoryLib->checkproductoper($product_id, $branch_id);
                if(!$check_inventory)
                {
                    $error_msg    = '货品：'. $bn .' 正在盘点中,不可以出入库操作!';
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 保存同步的承运商数据
     */
    function saveCarrier($data)
    {
        $carrierObj    = app::get('purchase')->model('carrier');

        $data['carrier_isvalid']    = intval($data['carrier_isvalid']);
        $carrier_code    = $data['carrier_code'];
        $shop_id         = $data['shop_id'];
        $carrier_name    = $data['carrier_name'];
        
        // 检查承运商的平台状态
        if($carrier_name){
            $carrierList = $this->getVopCarrierList();
            if(isset($carrierList[$carrier_name]) && $carrierList[$carrier_name]){
                $carrierInfo = $carrierList[$carrier_name];
                
                // 平台状态
                $data['platform_status'] = $carrierInfo['platform_status'];
                $data['memo'] = $carrierInfo['memo'];
                if($carrierInfo['platform_status'] == 'abandon'){
                    $data['disabled'] = 'true';
                }else{
                    $data['disabled'] = 'false';
                }
            }
        }
        
        // save
        $row    = $carrierObj->dump(array('carrier_code'=>$carrier_code, 'shop_id'=>$shop_id), '*');
        if($row){
            $carrierObj->update($data, array('cid'=>$row['cid']));
        }else{
            $rs = $carrierObj->insert($data);
            if(!$rs){
                return false;
            }
        }

        return true;
    }

    /**
     * 发货批次
     *
     * @param intval $dly_mode 配送方式 1:汽运,2:空运
     * @return Array
     */
    function getDeliveryTime($dly_mode=null)
    {
        $data       = array();
        $data[1]    = array(1=>'04:00', '12:00', '15:00', '20:00', '22:00');//汽运
        $data[2]    = array(1=>'04:00', '12:00', '15:00', '16:00', '20:00', '22:00');//空运
        
        if(isset($dly_mode) && $dly_mode){
            $dly_mode = intval($dly_mode);
            
            return $data[$dly_mode];
        }
        
        return $data;
    }

    /**
     * 推算出要求到货时间
     *
     * @param intval $dly_mode 配送方式 1:汽运,2:空运
     * @param string $delivery_date 送货批次:年月日
     * @param string $delivery_hour 送货批次:小时时间点
     * @return Array
     */
    function reckonArrivalTime($dly_mode, $delivery_date, $delivery_hour)
    {
        $data       = array();

        //汽运
        $data[1]    = array(
                    1=>array(
                            0=>array('day'=>0, 'hour'=>'10:00'),
                    ),
                    2=>array(
                            0=>array('day'=>0, 'hour'=>'16:00'),
                            1=>array('day'=>0, 'hour'=>'20:00'),
                    ),
                    3=>array(
                            0=>array('day'=>0, 'hour'=>'22:00'),
                    ),
                    4=>array(
                            0=>array('day'=>0, 'hour'=>'23:59'),
                            1=>array('day'=>1, 'hour'=>'09:00'),
                    ),
                    5=>array(
                            0=>array('day'=>1, 'hour'=>'09:00'),
                    ),
        );

        //空运
        $data[2]    = array(
                1=>array(
                        0=>array('day'=>0, 'hour'=>'20:00'),
                ),
                2=>array(
                        0=>array('day'=>0, 'hour'=>'23:59'),
                ),
                3=>array(
                        0=>array('day'=>1, 'hour'=>'09:00'),
                ),
                4=>array(
                        0=>array('day'=>1, 'hour'=>'09:00'),
                ),
                5=>array(
                        0=>array('day'=>1, 'hour'=>'16:00'),
                ),
                6=>array(
                        0=>array('day'=>1, 'hour'=>'18:00'),
                ),
        );

        //发货批次
        $deliveryTime    = $this->getDeliveryTime($dly_mode);

        $delivery_key    = 0;
        foreach ($deliveryTime as $key => $val)
        {
            if($val == $delivery_hour)
            {
                $delivery_key    = $key;
                break;
            }
        }

        //计算到货时间
        $temp = array();
        if(isset($dly_mode) && $dly_mode){
            $dly_mode = intval($dly_mode);
            
            $temp = $data[$dly_mode][$delivery_key];
        }
        
        $dly_date_list = array();
        foreach ($temp as $key => $val){

            $hour    = $val['hour'];
            $day     = $val['day'];

            $arrival_time    = $delivery_date;
            if($day)
            {
                $arrival_time    = date('Y-m-d', strtotime('+'. $day .' day', strtotime($delivery_date)));
            }

            $dly_date_list[]    = $arrival_time .' '. $hour;
        }

        return $dly_date_list;
    }

    /**
     * 根据当前小时和配送方式(推算出送货批次和要求到货时间)
     */
    function reckonTiem($dly_mode)
    {
        $now_hour    = date('H', time());

        $delivery_date    = date('Y-m-d', time());
        $delivery_hour    = '';
        $add_day          = 0;

        $timeData    = $this->getDeliveryTime($dly_mode);
        foreach ($timeData as $key => $val)
        {
            $tempHour    = explode(':', $val);
            $hour        = intval($tempHour[0]);

            if($now_hour <= $hour)
            {
                $delivery_hour    = $val;
                break;
            }
        }

        //未匹配到
        if(empty($delivery_hour))
        {
            $add_day          = 1;
            $delivery_hour    = $timeData[1];
        }

        //加一天
        if($add_day)
        {
            $delivery_date    = date('Y-m-d', strtotime("+1 day"));
        }

        $delivery_time    = $delivery_date .' '. $delivery_hour;//送货批次

        //到货时间
        $arrival_time    = $this->reckonArrivalTime($dly_mode, $delivery_date, $delivery_hour);

        //程序自动选择时,默认选择第一条
        $arrival_time    = $arrival_time[0];

        return array('delivery_time'=>$delivery_time, 'arrival_time'=>$arrival_time);
    }

    /**
     * 检查Post编辑后的出库单明细
     *
     * @param Array   $data POST数据
     * @param String  $error_msg 错误信息
     * @return Array
     */
    function check_edit_items($data, &$error_msg)
    {
        $stockoutItemsObj    = app::get('purchase')->model('pick_stockout_bill_items');

        $stockout_id    = $data['stockout_id'];
        $item_data      = $data['item_num'];//post编辑提交的出库单明细
        $snap_data      = array();
        $flag           = false;

        //出库单明细
        $result      = array();
        $dataList    = $stockoutItemsObj->getList('stockout_item_id, bn, num, item_num, is_del', array('stockout_id'=>$stockout_id));
        foreach ($dataList as $key => $val)
        {
            $item_id    = $val['stockout_item_id'];

            if(isset($item_data[$item_id]))
            {
                $num    = intval($item_data[$item_id]);
                if(empty($num))
                {
                    $error_msg    = '货号：'. $val['bn'] .' 申请数量填写错误!';
                    return false;
                }
                elseif($num > $val['item_num'])
                {
                    $error_msg    = '货号：'. $val['bn'] .' 申请数量不能大于可出库数量!';
                    return false;
                }

                $flag = true;
                $result[$item_id]    = array('is_del'=>false, 'num'=>$num);

                //修改日志
                if($val['item_num'] != $num)
                {
                    $snap_data[]    = '货号'. $val['bn'] .'修改数量('. $val['item_num'] .'->'. $num .')';
                }
            }
            else
            {
                //删除状态
                $result[$item_id]    = array('is_del'=>true, 'num'=>$val['num']);

                //修改日志
                $snap_data[]    = '货号'. $val['bn'] .'被删除';
            }
        }

        if(!$flag)
        {
            $error_msg    = '出库单明细不能全部删除!';
            return false;
        }

        //增加修改日志
        if($snap_data)
        {
            $log_str    = implode('；', $snap_data);
            $this->_logObj->write_log('update_stockout_bills@ome', $stockout_id, $log_str);
        }

        return $result;
    }

    /**
     * 保存post编辑后的出库单明细
     *
     * @param Array   $item_data 出库单明细
     * @param String  $error_msg 错误信息
     * @return Array
     */
    function update_edit_items($item_data, &$error_msg)
    {
        $stockoutItemsObj    = app::get('purchase')->model('pick_stockout_bill_items');

        if(empty($item_data))
        {
            $error_msg    = '出库单明细不存在!';
            return false;
        }

        foreach ($item_data as $item_id => $val)
        {
            $data    = array();
            $data['num']    = $val['num'];
            $data['is_del'] = ($val['is_del'] ? 'true' : 'false');

            $stockoutItemsObj->update($data, array('stockout_item_id'=>$item_id));
        }

        return  true;
    }

    /**
     * 根据货号、仓库branch_id获取对应的仓库可用库存
     *
     * @param varchar $bn 货号
     * @param intval $branch_id 仓库ID
     * @return Array
     */
    function getBranchStoreByBn($bn, $branch_id)
    {
        $basicMaterialObj      = app::get('material')->model('basic_material');
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');

        //基础物料ID
        $row   = $basicMaterialObj->dump(array('material_bn'=>$bn), 'bm_id');
        if(empty($row))
        {
            return array();
        }

        //库存
        $result    = array();
        $sql       = "SELECT * FROM sdb_ome_branch_product WHERE product_id=". $row['bm_id'] ." AND branch_id=". $branch_id;
        $branch_product    = $basicMaterialObj->db->selectrow($sql);

        //根据仓库ID、基础物料ID获取该物料仓库级的预占
        $branch_product['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($row['bm_id'], $branch_id);

        $store    = max(0, $branch_product['store'] - $branch_product['store_freeze']);

        return $store;
    }

    /**
     * 判断是否唯品会专配
     *
     * @return void
     * @author 
     **/
    public function is_vopcp($carrier_code)
    {
        return $carrier_code == '120001552' ? true : false;
    }
    
    /**
     * 获取唯品会物流承运商，平台状态
     *
     * @return array[]
     */
    public function getVopCarrierList()
    {
        $carrierList = array (
            '唯品专配' => array (
                'carrier_code' => '120001552',
                'carrier_isvalid' => 1,
                'carrier_name' => '唯品专配',
                'carrier_shortname' => '唯品专配',
                'tms_carrier_id' => '9409',
                'platform_status' => 'allow',
                'memo' => '',
            ),
            '顺丰(入库)' => array (
                'carrier_code' => '1200000540',
                'carrier_isvalid' => 1,
                'carrier_name' => '顺丰(入库)',
                'carrier_shortname' => '顺丰(入库)',
                'tms_carrier_id' => '9411',
                'platform_status' => 'allow',
                'memo' => '',
            ),
            '跨越(入库)' => array (
                'carrier_code' => '1200000538',
                'carrier_isvalid' => 1,
                'carrier_name' => '跨越(入库)',
                'carrier_shortname' => '跨越(入库)',
                'tms_carrier_id' => '9410',
                'platform_status' => 'allow',
                'memo' => '',
            ),
            '唯品标配' => array (
                'carrier_code' => '1200000672',
                'carrier_isvalid' => 1,
                'carrier_name' => '唯品标配',
                'carrier_shortname' => '唯品标配',
                'tms_carrier_id' => '',
                'platform_status' => 'allow',
                'memo' => '仅预调拨场景下开放，常规订单无需展示',
            ),
            '其他' => array (
                'carrier_code' => '2',
                'carrier_isvalid' => 1,
                'carrier_name' => '其他',
                'carrier_shortname' => '其他',
                'tms_carrier_id' => '',
                'platform_status' => 'allow',
                'memo' => '',
            ),
            '品骏航空(入库)' => array (
                'carrier_code' => '120000831',
                'carrier_isvalid' => 1,
                'carrier_name' => '品骏航空(入库)',
                'carrier_shortname' => '品骏航空(入库)',
                'tms_carrier_id' => '8a4db4c7540a86bd015428554eed107d',
                'platform_status' => 'abandon',
                'memo' => '平台已废弃此承运商',
            ),
            '自有车辆送货' => array (
                'carrier_code' => '1',
                'carrier_isvalid' => 1,
                'carrier_name' => '自有车辆送货',
                'carrier_shortname' => '自有车辆送货',
                'tms_carrier_id' => '',
                'platform_status' => 'abandon',
                'memo' => '平台已废弃此承运商',
            ),
            '虚拟承运' => array (
                'carrier_code' => '3',
                'carrier_isvalid' => 1,
                'carrier_name' => '虚拟承运',
                'carrier_shortname' => '虚拟承运',
                'tms_carrier_id' => '',
                'platform_status' => 'abandon',
                'memo' => '平台已废弃此承运商',
            ),
            '大鸿(入库)' => array (
                'carrier_code' => '120000846',
                'carrier_isvalid' => 1,
                'carrier_name' => '大鸿(入库)' ,
                'carrier_shortname' => '大鸿(入库)',
                'tms_carrier_id' => '8a4db4cb545c924201545fb57b6b1aed',
                'platform_status' => 'abandon',
                'memo' => '平台已废弃此承运商',
            ),
        );
        
        return $carrierList;
    }
    
}
?>
