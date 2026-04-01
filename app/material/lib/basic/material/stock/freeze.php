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
 * 基础物料库存冻结类
 * @author xiayuanjun@shopex.cn
 * @version 1.0
 */
class material_basic_material_stock_freeze{

    //---对象类型字段(obj_type)的常量定义--begin
    //订单类型
    const __ORDER = 1;

    //仓库类型
    const __BRANCH = 2;

    //售后类型
    //**换出货品库存不足,冻到商品上(类似订单类型),因订单bill_type有多个值且冻结释放都不传bill_type，顾新开类型
    const __AFTERSALE = 3;
    //---对象类型字段(obj_type)的常量定义--end

    //---配额ID字段(bmsq_id)的常量定义--begin
    //非配额即共享库存
    const __SHARE_STORE = -1;

    //门店确认库存
    const __STORE_CONFIRM = -2;
    //---配额ID字段(bmsq_id)的常量定义--end
    
    //OMS订单业务(默认)
    const __OMS_ORDER = 0;
    
    //经销商订单业务(一件代发)
    const __DEALER_ORDER = 2;
    
    //---业务类型字段(bill_type)的常量定义--begin
    //发货业务
    const __DELIVERY = 1;
    
    //售后业务
    const __RESHIP = 2;
    
    //采购退货业务
    const __RETURNED = 3;
    
    //调拨出库业务
    const __STOCKOUT = 4;
    
    //库内转储业务
    const __STOCKDUMP = 5;
    
    //唯品会出库业务
    const __VOPSTOCKOUT = 6;
    
    //人工库存预占业务
    const __ARTIFICIALFREEZE = 7;

    //库存调整单出库业务
    const __ADJUSTOUT = 8;

    //差异单出库业务
    const __DIFFERENCEOUT = 9;

    //加工单出库业务
    const __MATERIALPACKAGEOUT = 10;

    // 唯品会拣货单
    const __VOPICKBILLS = 11;

    // 售后申请单
    const __RETURN = 12;

    // 订单缺货
    const __ORDER_QUE = 13;

    // 订单仓库预占
    const __ORDER_YOU = 14;
    //---业务类型字段(bill_type)的常量定义--end
    
    //[库存预占类型ID]唯品会销售订单
    const __VOP_INVENTORY_ORDER = 15;
    
    private $_stockFreezeObj;
    private $_libBranchProduct;
    function __construct(){
        $this->_stockFreezeObj = app::get('material')->model('basic_material_stock_freeze');
        $this->_libBranchProduct = kernel::single('ome_branch_product');
    }

    public function deleteOrderBranchFreeze($arrOrderId) {
        $orderFreeze = $this->_stockFreezeObj->getList('*', array('obj_type'=>1, 'obj_id'=>$arrOrderId));
        $trans = kernel::database()->beginTransaction();
        $batchList = [];
        foreach($orderFreeze as $v) {
            if($v['bill_type'] == self::__ORDER_YOU) {
                $rs = $this->_stockFreezeObj->update(['bill_type'=>0, 'branch_id'=>0], ['bmsf_id'=>$v['bmsf_id'], 'bill_type'=>self::__ORDER_YOU]);
                if(!is_bool($rs)) {
                    if ($v['num']>0) {
                        $bn = app::get('material')->model('basic_material')->db_dump(['bm_id'=>$v['bm_id']], 'material_bn')['material_bn'];
                        $batchList[] = [
                            'branch_id'     =>  $v['branch_id'],
                            'product_id'    =>  $v['bm_id'],
                            'quantity'      =>  $v['num'],
                            'bn'            =>  $bn,
                            'obj_type'      =>  1,
                            'bill_type'     =>  0,
                            'obj_id'        =>  $v['obj_id'],
                            'obj_bn'        =>  $v['obj_bn'],
                        ];
                    }
                }
            } elseif($v['bill_type'] == self::__ORDER_QUE) {
                $this->_stockFreezeObj->update(['bill_type'=>0, 'branch_id'=>0], ['bmsf_id'=>$v['bmsf_id'], 'bill_type'=>self::__ORDER_QUE]);
            }
        }
        if(empty($batchList)) {
            kernel::database()->commit($trans);
            return [true, ['msg'=>'无需释放']];
        }
        $rs = ome_branch_product::freezeInRedis($batchList, '-', __CLASS__.'::'.__FUNCTION__);
        if ($rs[0] == false) {
            $memo = '预选仓预占释放失败：'.$rs[1];
            kernel::database()->rollBack();
            return [false, ['msg'=>$memo]];
        }
        kernel::database()->commit($trans);
        $memo = '预选仓预占释放成功：'.implode(', ', array_column($batchList, 'bn'));
        return [true, ['msg'=>$memo]];
    }

    /**
     * 提前选仓的库存预占处理方法
     *
     * @param array $orderFreeze 订单冻结
     * @param array $itemBranchStore 商品在仓库可用
     * @return array
     */
    public function addOrderBranchFreeze($orderFreeze, $itemBranchStore) {
        if(empty($orderFreeze) || empty($itemBranchStore)) {
            return [true, ['msg'=>'无需预占']];
        }
        $memo = '提前选仓结果：<br/>';
        $batchList = [];
        $order_id = current($orderFreeze)['obj_id'];
        foreach($orderFreeze as $v) {
            if($v['bill_type'] == self::__ORDER_YOU) {
                continue;
            }
            if(empty($itemBranchStore[$v['bm_id']])) {
                $this->_stockFreezeObj->update(['bill_type' => self::__ORDER_QUE], ['bmsf_id'=>$v['bmsf_id'], 'bill_type|noequal'=>self::__ORDER_YOU]);
                continue;
            }
            $branchNum = $itemBranchStore[$v['bm_id']]['branch'];
            if(count($branchNum) > 1) {
                $memo .= $itemBranchStore[$v['bm_id']]['bn'].':多个选仓无法预占<br/>';
                continue;
            }
            foreach($branchNum as $branch_id => $num) {
                if($v['num'] > $num) {
                    $this->_stockFreezeObj->update(['bill_type' => self::__ORDER_QUE], ['bmsf_id'=>$v['bmsf_id'], 'bill_type|noequal'=>self::__ORDER_YOU]);
                } else {
                    $batchList[] = [
                        'branch_id'     =>  $branch_id,
                        'product_id'    =>  $v['bm_id'],
                        'quantity'      =>  $v['num'],
                        'bn'            =>  $itemBranchStore[$v['bm_id']]['bn'],
                        'obj_type'      =>  1,
                        'bill_type'     =>  self::__ORDER_YOU,
                        'obj_id'        =>  $v['obj_id'],
                        'obj_bn'        =>  $v['obj_bn'],
                        'bmsf_id'       =>  $v['bmsf_id'],
                    ];
                }
            }
        }
        $trans = kernel::database()->beginTransaction();
        foreach ($batchList as $key => $value) {
            $rs = $this->_stockFreezeObj->update(['bill_type' => self::__ORDER_YOU, 'branch_id'=>$value['branch_id']], ['bmsf_id'=>$value['bmsf_id'], 'bill_type|noequal'=>self::__ORDER_YOU]);
            if(is_bool($rs)) {
                unset($batchList[$key]);
            } elseif ($value['quantity'] == 0) {
                unset($batchList[$key]);
            }
        }
        if(empty($batchList)) {
            $memo .= '没有商品进行仓预占';
            kernel::database()->commit($trans);
            app::get('ome')->model('operation_log')->write_log('order_edit@ome', $order_id, $memo);
            return [true, ['msg'=>$memo]];
        }
        $rs = ome_branch_product::freezeInRedis($batchList, '+', __CLASS__.'::'.__FUNCTION__);
        if ($rs[0] == false) {
            $memo .= '商品进行仓预占失败：'.$rs[1];
            kernel::database()->rollBack();
            app::get('ome')->model('operation_log')->write_log('order_edit@ome', $order_id, $memo);
            return [false, ['msg'=>$memo]];
        }
        kernel::database()->commit($trans);
        $memo .= '商品进行仓预占成功：'.implode(', ', array_column($batchList, 'bn'));
        app::get('ome')->model('operation_log')->write_log('order_edit@ome', $order_id, $memo);
        return [true, ['msg'=>$memo]];
    }

    /**
     * 增加基础物料仓冻结
     * 
     * @param Int $items[].bm_id 基础物料ID
     * @param Int $items[].bn 基础物料bn
     * @param Int $items[].obj_type 1 订单预占 2 仓库预占 3 售后预占
     * @param Int $items[].bill_type 业务类型  默认为0
     * @param Int $items[].obj_id 关联对象ID
     * @param String $items[].shop_id 店铺ID
     * @param String $items[].branch_id 仓库ID
     * @param Int $items[].bmsq_id 配额ID  -1代表非配额货品 -2代表门店确认库存的货品 -3代表门店非确认库存的货品
     * @param Int $items[].num 预占数
     * @param string $items[].store_code 抖音平台指定仓  default ''
     * @param string $items[].obj_bn 单据编号  default ''
     * @param string $items[].sub_bill_type 业务子分类  default ''
     * @param Boolean $items[].sync_sku 是否执行增加物料冻结, default true
     * @param Boolean $items[].log_type 日志类型, default '',如果是=negative_store为允许冻结大于库存
     * @param string $source 方法调用来源，一般入参__CLASS__.'::'.__FUNCTION__
     * @param string $error_msg 报错信息
     * @return Boolean
     */
    public function freezeBatch($items, $source='', &$error_msg = '')
    {
        // redis库存高可用，迭代掉本类的freeze方法
        // ****** $items里可能同时存在仓和商品预占，比如checkChangeReship方法(换货单审核)
        $batchList = $skuBatchList = [];
        foreach ($items as $freezeData) {

            $sm_id = $freezeData['sm_id'];
            $bm_id = $freezeData['bm_id'];
            $bn = $freezeData['bn'];
            $obj_type = $freezeData['obj_type'];
            $bill_type = $freezeData['bill_type'] ? $freezeData['bill_type'] : 0;
            $obj_id = $freezeData['obj_id'];
            $shop_id = $freezeData['shop_id'];
            $branch_id = $freezeData['branch_id'];
            $bmsq_id = $freezeData['bmsq_id'];
            $num = $freezeData['num'];
            $store_code = $freezeData['store_code'] ? : '';
            $obj_bn = $freezeData['obj_bn'] ? : '';
            $sub_bill_type = $freezeData['sub_bill_type'] ? : '';
            $log_type = $freezeData['log_type'] ? $freezeData['log_type'] : '';
            
            // check
            if(empty($bm_id) || empty($obj_type) || empty($bmsq_id)){
                $error_msg = '冻结基础数据缺失';
                return false;
            }
            
            // 基础物料不管控库存，则跳过
            if (isset($freezeData['is_product_ctrl_store']) && $freezeData['is_product_ctrl_store'] === false) {
                continue;
            }
            
            $num = intval($num);

            // 是否增加基础物料的冻结
            $sync_sku = true;
            if (isset($freezeData['sync_sku']) && !$freezeData['sync_sku']) {
                $sync_sku = false;
            }
            if ($sync_sku) {
                $skuBatchList[] = [
                    'bm_id' =>  $bm_id,
                    'sm_id' =>  $sm_id,
                    'num'   =>  $num,
                    'branch_id'     =>  $branch_id,
                    'obj_type'      =>  $obj_type,
                    'bill_type'     =>  $bill_type,
                    'obj_id'        =>  $obj_id,
                    'obj_bn'        =>  $obj_bn,
                    // 'obj_item_id'   =>  $obj_item_id,
                ];
            }
            switch($obj_type){
                //订单预占
                case 1:
                    $filter = array('bm_id'=>$bm_id, 'obj_type'=>$obj_type, 'obj_id'=>$obj_id, 'bmsq_id'=>$bmsq_id);
                    $insertExtData = array('shop_id'=>$shop_id, 'branch_id'=>$branch_id);
                    if ($bill_type == self::__VOPICKBILLS) {
                        $filter['bill_type'] = $bill_type;
                    }
                    
                    //抖音平台指定仓
                    if($store_code){
                        $insertExtData['store_code'] = $store_code;
                    }
                    
                    break;
                //售后预占
                case 3:
                    $filter = array('bm_id'=>$bm_id, 'obj_type'=>$obj_type, 'obj_id'=>$obj_id, 'bmsq_id'=>$bmsq_id, 'bill_type'=>$bill_type);
                    $insertExtData = array('shop_id'=>$shop_id, 'branch_id'=>$branch_id);
                    
                    //抖音平台指定仓
                    if($store_code){
                        $insertExtData['store_code'] = $store_code;
                    }
                    
                    break;
                //电商仓/门店仓预占
                case 2:
                    $filter = array('bm_id'=>$bm_id, 'obj_type'=>$obj_type, 'obj_id'=>$obj_id, 'bmsq_id'=>$bmsq_id, 'bill_type'=>$bill_type);
                    $insertExtData = array('shop_id'=>$shop_id, 'branch_id'=>$branch_id);
                    break;
                //唯品会销售订单
                case 15:
                    $filter = array('bm_id'=>$bm_id, 'obj_type'=>$obj_type, 'obj_id'=>$obj_id, 'bmsq_id'=>$bmsq_id, 'bill_type'=>$bill_type);
                    $insertExtData = array('shop_id'=>$shop_id, 'branch_id'=>$branch_id);
                    break;
            }

            //仓库类型
            if($obj_type == 2){
                if($bmsq_id == -1 && $num!=0){
                    //仓库冻结库存
                    /*
                    $rs    = $this->_libBranchProduct->chg_product_store_freeze($branch_id, $bm_id, $num, '+', $log_type);
                    if($rs == false){
                        return false;
                    }
                    */
                    $batchList[] = [
                        'branch_id'     =>  $branch_id,
                        'product_id'    =>  $bm_id,
                        'quantity'      =>  $num,
                        'bn'            =>  $bn,
                        'obj_type'      =>  2,
                        'bill_type'     =>  $bill_type,
                        'obj_id'        =>  $obj_id,
                        'obj_bn'        =>  $obj_bn,
                        'log_type'      =>  $log_type,
                    ];

                }
            }
            
            $freezeRow = $this->_stockFreezeObj->getList('bmsf_id,bill_type,branch_id', $filter, 0, 1);
            if($freezeRow){
                if($obj_type == 1 && $freezeRow[0]['bill_type'] == self::__ORDER_YOU && $num!=0) {
                    $batchList[] = [
                        'branch_id'     =>  $freezeRow[0]['branch_id'],
                        'product_id'    =>  $bm_id,
                        'quantity'      =>  $num,
                        'bn'            =>  $bn,
                        'obj_type'      =>  1,
                        'bill_type'     =>  $freezeRow[0]['bill_type'],
                        'obj_id'        =>  $obj_id,
                        'obj_bn'        =>  $obj_bn,
                        'log_type'      =>  $log_type,
                    ];
                }
                $sql = "UPDATE sdb_material_basic_material_stock_freeze SET num=num+".$num.", last_modified=". time() ." WHERE bmsf_id=".$freezeRow[0]['bmsf_id'];
                if($this->_stockFreezeObj->db->exec($sql)){
                    $rs = $this->_stockFreezeObj->db->affect_row();
                    if(is_numeric($rs) && $rs > 0){
                        continue;
                        // return true;
                    }else{
                        $error_msg = '更新冻结流水无效';
                        return false;
                    }
                }else{
                    $error_msg = '更新冻结流水失败';
                    return false;
                }
            }else{
                $insertData = $filter;
                $insertData['sm_id'] = $sm_id;
                $insertData['obj_bn'] = (string)$obj_bn;
                $insertData['sub_bill_type'] = (string)$sub_bill_type;
                $insertData['num'] = $num;
                $insertData['create_time'] = time();
                $insertData['last_modified'] = time();
                $insertData['source'] = $source;
                
                if($insertExtData){
                    $insertData = array_merge($insertData, $insertExtData);
                }
                $rs = $this->_stockFreezeObj->insert($insertData);
                if ($rs) {
                    continue;
                } else {
                    $error_msg = '添加冻结流水失败';
                    return false;
                }
            }
        }
        if ($batchList) {
            $rs = ome_branch_product::freezeInRedis($batchList, '+', $source);
            if ($rs[0] == false) {
                $error_msg = $rs[1];
                return false;
            }
        }
        // 商品冻结
        if ($skuBatchList) {
            $basicMStockLib = kernel::single('material_basic_material_stock');
            $rs = $basicMStockLib->freezeBatch($skuBatchList, $source);
            if (!$rs[0]) {
                $error_msg = '货品冻结预占失败'.$rs[1];
                return false;
            }
        }
        return true;
    }
    
    
    /**
     * 释放基础物料仓冻结
     * 
     * @param int $items[].bm_id 基础物料ID
     * @param int $items[].obj_type 1 订单预占 2 仓库预占 3 售后预占
     * @param int $items[].bill_type 业务类型  默认为0
     * @param int $items[].obj_id 关联对象ID
     * @param string $items[].branch_id 仓库ID
     * @param int $items[].bmsq_id 配额ID  -1代表非配额货品 -2代表门店确认库存的货品 -3代表门店非确认库存的货品
     * @param int $items[].num 预占数
     * @param string $items[].bm_bn 基础物料bn
     * @param Boolean $items[].sync_sku 是否执行释放物料冻结, default true
     * @param string $source 方法调用来源，一般入参__CLASS__.'::'.__FUNCTION__
     * @param string $error_msg 报错信息
     * 
     * @return Boolean
     */
    public function unfreezeBatch($items, $source='', &$error_msg = '')
    {
        // redis库存高可用，迭代掉本类的unfreeze方法
        // ****** $items里可能同时存在仓和商品预占，比如checkChangeReship方法(换货单审核)冻结，然后新建换货订单释放预占refuseChangeReship
        $batchList = $skuBatchList = [];
        $obj_id = $obj_type = $bill_type = '';
        $obj_type_arr = $obj_id_arr = [];
        foreach ($items as $item) {
            
            // 基础物料不管控库存，则跳过
            if (isset($item['is_product_ctrl_store']) && $item['is_product_ctrl_store'] === false) {
                continue;
            }
            
            $bm_id      = $item['bm_id'];
            $obj_type   = $item['obj_type'];
            $bill_type  = $item['bill_type'] ? $item['bill_type'] : 0; 
            $obj_id     = $item['obj_id'];
            $branch_id  = $item['branch_id']; 
            $bmsq_id    = $item['bmsq_id']; 
            $num        = $item['num'];
            $bm_bn      = $item['bm_bn'] ? $item['bm_bn'] : '';

            $obj_type_arr[$obj_type] = $obj_type;
            $obj_id_arr[$obj_id] = $obj_id; // vop出库单对应的拣货单会有多个拣货单id

            if(empty($bm_id) || empty($obj_type) || empty($bmsq_id)){
                $error_msg = '释放冻结基础数据缺失';
                return false;
            }

            $num = intval($num);

            // 是否释放基础物料的冻结
            $sync_sku = true;
            if (isset($item['sync_sku']) && !$item['sync_sku']) {
                $sync_sku = false;
            }
            if ($sync_sku && $num!=0) {
                $skuBatchList[] = [
                    'bm_id' =>  $bm_id,
                    'num'   =>  $num,
                    'branch_id'     =>  $branch_id,
                    'obj_type'      =>  $obj_type,
                    'bill_type'     =>  $bill_type,
                    'obj_id'        =>  $obj_id,
                    'obj_bn'        =>  '',
                    // 'obj_item_id'   =>  $obj_item_id,
                ];
            }

            switch($obj_type){
                case 1:
                    $sql_where = "WHERE  bm_id =".$bm_id." and obj_type =".$obj_type." and obj_id =".$obj_id." and bmsq_id =".$bmsq_id;
                    if ($bill_type == self::__VOPICKBILLS) {
                        $sql_where .= " and bill_type='" . self::__VOPICKBILLS . "'";
                    }
                    $list = $this->_stockFreezeObj->db->select('select bmsf_id,bill_type,branch_id from sdb_material_basic_material_stock_freeze '.$sql_where);
                    $sql_where = "WHERE bmsf_id in('" . implode("','", array_column($list, 'bmsf_id')) . "')";
                    foreach($list as $v) {
                        if($v['bill_type'] == self::__ORDER_YOU && $num!=0) {
                            $batchList[] = [
                                'branch_id'     =>  $v['branch_id'],
                                'product_id'    =>  $bm_id,
                                'quantity'      =>  $num,
                                'bn'            =>  $bm_bn,
                                'obj_type'      =>  1,
                                'bill_type'     =>  $v['bill_type'],
                                'obj_id'        =>  $obj_id,
                                'obj_bn'        =>  '',
                            ];
                        }
                    }
                    break;
                case 3:
                    $sql_where = "WHERE  bm_id =".$bm_id." and obj_type =".$obj_type." and bill_type=". $bill_type ." and obj_id =".$obj_id." and bmsq_id =".$bmsq_id;
                    $list = $this->_stockFreezeObj->db->select('select bmsf_id,bill_type,branch_id from sdb_material_basic_material_stock_freeze '.$sql_where);
                    $sql_where = "WHERE bmsf_id in('" . implode("','", array_column($list, 'bmsf_id')) . "')";
                    break;
                case 2:
                    $sql_where = "WHERE  bm_id =".$bm_id." and obj_type =".$obj_type." and bill_type=". $bill_type ." and obj_id =".$obj_id." and bmsq_id =".$bmsq_id;
                    $list = $this->_stockFreezeObj->db->select('select bmsf_id from sdb_material_basic_material_stock_freeze '.$sql_where);
                    $sql_where = "WHERE bmsf_id in('" . implode("','", array_column($list, 'bmsf_id')) . "')";
                    break;
                case 15:
                    //唯品会销售订单
                    $sql_where = "WHERE bm_id =". $bm_id ." AND obj_type =". $obj_type ." AND bill_type=". $bill_type ." AND obj_id =". $obj_id ." AND bmsq_id =". $bmsq_id;
                    $list = $this->_stockFreezeObj->db->select('select bmsf_id from sdb_material_basic_material_stock_freeze '.$sql_where);
                    $sql_where = "WHERE bmsf_id in('" . implode("','", array_column($list, 'bmsf_id')) . "')";
                    break;
                default:
                    $error_msg = 'obj_type无效';
                    return false;
                    break;
            }

            //仓库类型
            if($obj_type == 2)
            {
                if($bmsq_id == -1 && $num!=0)
                {
                    //仓库冻结库存
                    /*
                    $rs    = $this->_libBranchProduct->chg_product_store_freeze($branch_id, $bm_id, $num, '-', $log_type);
                    if($rs == false){
                        return false;
                    }
                    */
                    $batchList[] = [
                        'branch_id'     =>  $branch_id,
                        'product_id'    =>  $bm_id,
                        'quantity'      =>  $num,
                        'bn'            =>  $bm_bn,
                        'obj_type'      =>  2,
                        'bill_type'     =>  $bill_type,
                        'obj_id'        =>  $obj_id,
                        'obj_bn'        =>  '',
                    ];
                }
            }
            
            // $sql = "UPDATE sdb_material_basic_material_stock_freeze SET num=IF((CAST(num AS SIGNED)-$num)>0,num-$num,0), last_modified=". time() ." ".$sql_where;
            $sql = "UPDATE sdb_material_basic_material_stock_freeze SET num=num-$num, last_modified=". time() ." ".$sql_where;
            if($this->_stockFreezeObj->db->exec($sql)){
                $rs = $this->_stockFreezeObj->db->affect_row();
                if(is_numeric($rs) && $rs > 0){
                    continue;
                    // return true;
                }else{
                    $error_msg = '释放冻结流水无效';
                    return false;
                }
            }else{
                $error_msg = '释放冻结流水失败';
                return false;
            }
        }
        if ($batchList) {
            $rs = ome_branch_product::freezeInRedis($batchList, '-', $source);
            if ($rs[0] == false) {
                $error_msg = $rs[1];
                return false;
            }
        }
        // 商品释放冻结
        if ($skuBatchList) {
            $basicMStockLib = kernel::single('material_basic_material_stock');
            $rs = $basicMStockLib->unfreezeBatch($skuBatchList, $source);
            if (!$rs[0]) {
                $error_msg = '货品冻结释放失败'.$rs[1];
                return false;
            }
        }
        // 释放冻结成功以后，再去删除冻结流水，并回收业务层删除流水方法的调用
        if ($obj_id && $obj_type) {
            $filter = array(
                'obj_id'    => array_keys($obj_id_arr),
                'obj_type'  => array_keys($obj_type_arr),
                'bill_type' => $bill_type,
                'num'       => 0
            );
            $this->_stockFreezeObj->delete($filter);
        }
        return true;
    }

    /**
     * 订单全部发货后，调用该方法删除订单预占记录
     * 
     * @param Int $order_id 订单ID
     * @param Int $bill_type 业务类型，0为默认、2为经销商订单
     * @return Boolean
     * **仅限平台自发订单发货完成后使用**
     * **仅限平台自发订单发货完成后使用**
     * **仅限平台自发订单发货完成后使用**
     */
    public function delOrderFreeze($order_id, $bill_type=0)
    {

        // // 删除动作移到了unfreezeBatch方法最下面，
        // // 确保商品和仓的冻结释放成功以后再删除
        // return true; 

        if(empty($order_id)){
            return false;
        }
        
        $filter = array(
            'obj_id' => $order_id,
            'obj_type' => 1,
            // 'num' => 0
        );
        
        //按业务类型,进行删除
        if($bill_type){
            $filter['bill_type'] = $bill_type;
        }
        
        return $this->_stockFreezeObj->delete($filter);
    }

    /**
     * 订单全部发货后，调用该方法删除订单预占记录
     * 
     * @param Int $bill_id vop拣货单ID
     * @return Boolean
     */
    public function delVopickOrderFreeze($bill_id){

        // 删除动作移到了unfreezeBatch方法最下面，
        // 确保商品和仓的冻结释放成功以后再删除
        return true; 

        if(empty($bill_id)){
            return false;
        }

        $filter = array(
            'obj_id' => $bill_id,
            'obj_type' => 1,
            'bill_type' => self::__VOPICKBILLS,
            'num' => 0
        );
        return $this->_stockFreezeObj->delete($filter);
    }

    /**
     * 发货单发货后，调用该方法删除仓库预占记录
     * 
     * @param Int $delivery_id 发货单ID
     * @return Boolean
     */
    public function delDeliveryFreeze($delivery_id){

        // 删除动作移到了unfreezeBatch方法最下面，
        // 确保商品和仓的冻结释放成功以后再删除
        return true; 

        if(empty($delivery_id)){
            return false;
        }

        $filter = array(
            'obj_id' => $delivery_id,
            'obj_type' => 2,
            'bill_type' => self::__DELIVERY,
            'num' => 0
        );
        $sfIds = $this->_stockFreezeObj->getList('bmsf_id', $filter);
        if($sfIds){
            return $this->_stockFreezeObj->delete(['bmsf_id'=>array_column($sfIds, 'bmsf_id')]);
        }
        return true;
    }

    /**
     * 根据订单号查询是否有该订单的预占
     * 
     * @param Int $order_id 订单ID
     * @return Boolean
     */
    public function hasOrderFreeze($order_id){

        if(empty($order_id)){
            return false;
        }

        $result = $this->_stockFreezeObj->getList('bmsf_id', array('obj_type'=>1,'obj_id'=>$order_id,'num|than'=>0), 0, 1);
        if($result){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 根据店铺ID、基础物料ID获取该物料店铺级的预占
     * 
     * @param Int $bm_id 基础物料ID
     * @param Int $shop_id 店铺ID
     * @param string $store_code 抖音平台指定仓
     * @return number
     */
    public function getShopFreeze($bm_id, $shop_id, $store_code=''){

        if(empty($bm_id) || empty($shop_id)){
            return false;
        }
        $sql = "SELECT sum(num) as total FROM sdb_material_basic_material_stock_freeze WHERE bm_id=".$bm_id." AND obj_type in (1,3) AND shop_id='".$shop_id."'";

        if($store_code){
            $sql .= " AND (store_code='". $store_code ."' OR store_code='')";
        } else {
            $sql .= " AND branch_id=0";
        }
        
        $result = $this->_stockFreezeObj->db->selectrow($sql);
        if($result){
            return $result['total'];
        }else{
            return 0;
        }
    }

    /**
     * 根据仓库ID、基础物料ID获取该物料店铺级的预占
     * 
     * @param Int $bm_id 基础物料ID
     * @param Int $branch_id 仓库ID
     * @return number
     */
    public function getOrderBranchFreeze($bm_id, $branch_id){

        if(empty($bm_id) || empty($branch_id)){
            return false;
        }
        $sql = "SELECT sum(num) as total FROM sdb_material_basic_material_stock_freeze WHERE bm_id=".$bm_id." AND obj_type=1 AND branch_id='".$branch_id."'";
        
        $result = $this->_stockFreezeObj->db->selectrow($sql);
        if($result){
            return $result['total'];
        }else{
            return 0;
        }
    }

    /**
     * 根据仓库ID、基础物料ID获取该物料仓库级的预占
     * 
     * @param Int $bm_id 基础物料ID
     * @param Int $branch_id 仓库ID
     * @return int
     */
    public function getBranchFreeze($bm_id, $branch_id){

        if(empty($bm_id) || empty($branch_id)){
            return false;
        }
        $result = ome_branch_product::storeFromRedis(['branch_id' => $branch_id, 'product_id' => $bm_id]);
        //冻结库存
        // $result = $this->_stockFreezeObj->db->selectrow("SELECT store_freeze FROM sdb_ome_branch_product WHERE branch_id=".$branch_id." AND product_id=". $bm_id);
        
        if($result[0]){
            return intval($result[2]['store_freeze']);
        }else{
            return 0;
        }
    }
    
    /**
     * 根据基础物料ID获取关联仓库的冻结数量之和
     * 
     * @param Int $bm_id 基础物料ID
     * @return number
     */
    public function getBranchProductFreeze($bm_id){
        
        if(empty($bm_id)){
            return false;
        }
        
        $result = $this->_stockFreezeObj->db->selectrow("SELECT sum(store_freeze) AS total FROM sdb_ome_branch_product WHERE product_id=". $bm_id);
        if($result){
            return intval($result['total']);
        }else{
            return 0;
        }
    }
    
    /**
     * 删除仓库预占流水记录(除发货业务之外)
     * 
     * @param Int $obj_id 记录ID
     * @param Int $bill_type 业务类型
     * @return Boolean
     */
    public function delOtherFreeze($obj_id, $bill_type){
        
        // 删除动作移到了unfreezeBatch方法最下面，
        // 确保商品和仓的冻结释放成功以后再删除
        return true; 

        if(empty($obj_id) || empty($bill_type)){
            return false;
        }
        
        $filter = array(
                'obj_id' => $obj_id,
                'obj_type' => 2,
                'bill_type' => $bill_type,
                'num' => 0
        );
        return $this->_stockFreezeObj->delete($filter);
    }
    
    /**
     * 根据基础物料ID获取对应的冻结库存
     * 
     * @param Int $bm_id 基础物料ID
     * @return number
     */
    public function getMaterialStockFreeze($bm_id){
        
        if(empty($bm_id)){
            return false;
        }
        
        //冻结库存
        $result = $this->_stockFreezeObj->db->selectrow("SELECT store_freeze FROM sdb_material_basic_material_stock WHERE bm_id=".$bm_id);
        
        if($result){
            return intval($result['store_freeze']);
        }else{
            return 0;
        }
    }
    
    /**
     * 根据门店仓库ID、基础物料ID获取该物料门店仓库级的预占
     *
     * @param Int $bm_id 基础物料ID
     * @param Int $branch_id 仓库ID
     * @return int
     */
    public function getO2oBranchFreeze($bm_id, $branch_id){
        
        if(empty($bm_id) || empty($branch_id)){
            return false;
        }
        $result = ome_branch_product::storeFromRedis(['branch_id' => $branch_id, 'product_id' => $bm_id]);
        //冻结库存
        // $result = $this->_stockFreezeObj->db->selectrow("SELECT store_freeze FROM sdb_ome_branch_product WHERE branch_id=".$branch_id." AND product_id=". $bm_id);
        
        if($result[0]){
            return intval($result[2]['store_freeze']);
        }else{
            return 0;
        }
    }
    
    /*
     * 删除人工库存预占流水记录
     * $obj_ids 记录主键数组
     */
    public function delArtificialFreeze($obj_ids){

        // 删除动作移到了unfreezeBatch方法最下面，
        // 确保商品和仓的冻结释放成功以后再删除
        return true; 

        if(empty($obj_ids)){
            return false;
        }
        foreach($obj_ids as $var_obj_id){
            $filter = array(
                    "obj_id" => $var_obj_id,
                    "bill_type" => "7",
                    "num" => 0
            );
            $this->_stockFreezeObj->delete($filter);
        }
    }
    
    /**
     * 根据基础物料bm_id获取该物料店铺级的预占
     *
     * @param Int $bm_id 基础物料ID
     * @return number
     */
    public function getShopFreezeByBmid($bm_id){
        
        if(empty($bm_id)){
            return false;
        }
        
        $result = $this->_stockFreezeObj->db->selectrow("SELECT sum(num) as total FROM sdb_material_basic_material_stock_freeze WHERE bm_id=".$bm_id." AND obj_type=1 AND bill_type<>".self::__ORDER_YOU);
        if($result){
            return intval($result['total']);
        }else{
            return 0;
        }
    }
    
    /**
     * 根据基础物料bm_id获取该物料仓库级的预占
     *
     * @param Int $bm_id 基础物料ID
     * @param Array $branch_ids 仓库
     * @return number
     */
    public function getBranchFreezeByBmid($bm_id, $branch_ids=''){
        
        if(empty($bm_id)){
            return false;
        }
        
        $sql = "SELECT sum(num) as total FROM sdb_material_basic_material_stock_freeze WHERE bm_id=".$bm_id." AND (obj_type=2 || (obj_type=1 AND bill_type=".self::__ORDER_YOU."))";
        
        //仓库条件
        if($branch_ids && is_array($branch_ids)){
            $sql .= " AND branch_id IN(". implode(',', $branch_ids) .")";
        }
        
        //仓库冻结总数
        $result = $this->_stockFreezeObj->db->selectrow($sql);
        
        return intval($result['total']);
    }
    
    //根据基础物料bm_id获取在途库存
    public function getMaterialArriveStore($bm_id){
        if(empty($bm_id)){
            return false;
        }
        $sql = "SELECT SUM(arrive_store) AS 'total' FROM ".DB_PREFIX."ome_branch_product WHERE product_id=".$bm_id;
        $count = kernel::database()->selectrow($sql);
        if($count["total"]){
            return $count["total"];
        }else{
            return 0;
        }
    }
    
    //根据基础物料bm_id获取良品库存(除去残损仓)
    public function getMaterialGoodStore($bm_id){
        if(empty($bm_id)){
            return false;
        }
        $filter_str = "product_id=".$bm_id;
        $mdl_ome_branch = app::get('ome')->model('branch');
        $branchList = $mdl_ome_branch->db->select('SELECT branch_id FROM sdb_ome_branch WHERE type=\'damaged\'');
        if(!empty($branchList)){
            $damaged_branch_ids = array();
            foreach($branchList as $var_branch){
                $damaged_branch_ids[] = $var_branch["branch_id"];
            }
            $filter_str.= " and branch_id not in(".implode(",", $damaged_branch_ids).")";
        }
        $sql = "SELECT SUM(store) AS 'total' FROM ".DB_PREFIX."ome_branch_product WHERE ".$filter_str;
        $count = kernel::database()->selectrow($sql);
        if($count["total"]){
            return $count["total"];
        }else{
            return 0;
        }
    }
    

    //根据基础物料bm_id获取良品库存(除去残损仓)
    public function getMaterialWarehouseStore($bm_id){
        if(empty($bm_id)){
            return false;
        }
        $filter_str = "product_id=".$bm_id." and store_id=0";
        $mdl_ome_branch = app::get('ome')->model('branch');
        $branchList = $mdl_ome_branch->db->select("SELECT branch_id FROM sdb_ome_branch WHERE (type='damaged' or (type='main' and online='false') ) ");
        if(!empty($branchList)){
            $damaged_branch_ids = array();
            foreach($branchList as $var_branch){
                $damaged_branch_ids[] = $var_branch["branch_id"];
            }
            $filter_str.= " and branch_id not in(".implode(",", $damaged_branch_ids).")";
        }
        /*$warehousebranch = $mdl_ome_branch->db->select('SELECT branch_id FROM sdb_ome_branch WHERE attr=\'true\' and b_type=1');

        $branch_ids = array_column($warehousebranch,'branch_id');

        if($branch_ids){
            $filter_str.= " and branch_id  in(".implode(",", $branch_ids).")";
        }*/
        $sql = "SELECT SUM(store) AS 'total' FROM ".DB_PREFIX."ome_branch_product WHERE ".$filter_str;
        $count = kernel::database()->selectrow($sql);
        if($count["total"]){
            return $count["total"];
        }else{
            return 0;
        }
    }

    //根据基础物料bm_id获取良品库存(除去残损仓)
    public function getMaterialO2oStore($bm_id){
        if(empty($bm_id)){
            return false;
        }
        $filter_str = "product_id=".$bm_id." and store_id>0 ";
     
        $sql = "SELECT SUM(store) AS 'total' FROM ".DB_PREFIX."ome_branch_product WHERE ".$filter_str;

        $count = kernel::database()->selectrow($sql);
        if($count["total"]){
            return $count["total"];
        }else{
            return 0;
        }
    }


    /**
     * 根据基础物料bm_id获取该物料仓库级的预占
     *
     * @param Int $bm_id 基础物料ID
     * @param Array $branch_ids 仓库
     * @return number
     */
    public function getWareBranchFreezeByBmid($bm_id){
        
        if(empty($bm_id)){
            return false;
        }
        
        $sql = "SELECT sum(num) as total FROM sdb_material_basic_material_stock_freeze WHERE bm_id=".$bm_id." AND (obj_type=2 || (obj_type=1 AND bill_type=".self::__ORDER_YOU."))";
        
        $branch_ids = $this->getWarehouseBranch();
        //仓库条件
        if($branch_ids && is_array($branch_ids)){
            $sql .= " AND branch_id IN(". implode(',', $branch_ids) .")";
        }
        
        //仓库冻结总数
        $result = $this->_stockFreezeObj->db->selectrow($sql);
        
        return intval($result['total']);
    }

    /*
     * 取大仓
     */
    public function getWarehouseBranch(){
        $mdl_ome_branch = app::get('ome')->model('branch');
        $warehousebranch = $mdl_ome_branch->db->select('SELECT branch_id FROM sdb_ome_branch WHERE b_type=1');

        $branch_ids = array_column($warehousebranch,'branch_id');

        return $branch_ids;
    }

    /**
     * 根据基础物料bm_id获取该物料仓库级的预占
     *
     * @param Int $bm_id 基础物料ID
     * @param Array $branch_ids 仓库
     * @return number
     */
    public function getStoreBranchFreezeByBmid($bm_id){
        
        if(empty($bm_id)){
            return false;
        }
        
        $sql = "SELECT sum(num) as total FROM sdb_material_basic_material_stock_freeze WHERE bm_id=".$bm_id." AND obj_type=2";
        
        $branch_ids = $this->getWarehouseBranch();
        //仓库条件
        if($branch_ids && is_array($branch_ids)){
            $sql .= " AND branch_id not IN(". implode(',', $branch_ids) .")";
        }
        
        //仓库冻结总数
        $result = $this->_stockFreezeObj->db->selectrow($sql);
        
        return intval($result['total']);
    }


    /**
     * 根据业务类型和单据id（或者单据bn）获取该单据的预占
     *
     * @param Int $bm_id 基础物料ID
     * @param Array $branch_ids 仓库
     * @return number
     */
    public function getStockFreezeByObj($obj_id = '', $obj_bn = '', $bill_type = '')
    {
        if (!$bill_type || (!$obj_id && !$obj_bn)) {
            return [];
        }
        $where = ' bill_type='.$bill_type;
        if ($obj_id) {
            $where .= ' AND obj_id="'.$obj_id.'"';
        } else {
            $where .= ' AND obj_bn="'.$obj_bn.'"';
        }
        $sql = "SELECT * FROM sdb_material_basic_material_stock_freeze WHERE " . $where;

        $res = $this->_stockFreezeObj->db->select($sql);
        return $res;
    }

}
