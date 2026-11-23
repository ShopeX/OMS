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

class console_vopreturn
{
    
    public static $bill_type = array(
        'vop'          => 'vopjitrk',
        '360buy'       => 'jdlreturn',
       
    );
    /**
     * 保存拆分数量
     * @param $return
     * @param $items
     * @return array
     * @date 2024-11-05 6:27 下午
     */
    public function formatItems($return, $items)
    {
        $vopreturnItemsMdl = app::get('console')->model('vopreturn_items');
        $vopItems          = $vopreturnItemsMdl->getList('*', ['return_id' => $return['id']]);
        if (!$vopItems) {
            return [false, '缺少退供单明细'];
        }
        $vopItems   = array_column($vopItems, null, 'id');
        $products = [];
        foreach ($items as $item_id => $splitNum) {
            if ($splitNum == 0) {
                continue;
            }
            $itemInfo = $vopItems[$item_id];
            $num      = $itemInfo['qty'] - $itemInfo['split_num'] - $splitNum;
            if ($num < 0) {
                return [false, sprintf('条码 [%s] 确认数量 [%s] + 已拆分数量 [%s] > 实退数量 [%s]', $itemInfo['barcode'], $splitNum, $itemInfo['split_num'], $itemInfo['qty'])];
            }
            $splitItem                  = [
                'return_id'          => $itemInfo['return_id'],
                'vopreturn_items_id' => $itemInfo['id'],
                'barcode'            => $itemInfo['barcode'],
                'num'                => $splitNum,
                'box_no'             => $itemInfo['box_no'],
                'po_no'              => $itemInfo['po_no'],
            ];
            
            $products[$itemInfo['barcode']]['barcode'] = $itemInfo['barcode'];
            $products[$itemInfo['barcode']]['material_bn'] = $itemInfo['material_bn'];
            $products[$itemInfo['barcode']]['material_name'] = $itemInfo['product_name'];
            $products[$itemInfo['barcode']]['bm_id'] = $itemInfo['bm_id'];
            $products[$itemInfo['barcode']]['price'] = $itemInfo['price'];
            $products[$itemInfo['barcode']]['qty'] += $splitNum;

    
            $products[$itemInfo['barcode']]['items_detail'][] = $splitItem;
            $rs = $vopreturnItemsMdl->updateSplitNum($splitItem['vopreturn_items_id'], $splitItem['num']);
            if ($rs == 0) {
                return [false, sprintf('[%s]更新[%s]拆分数量失败', $return['return_sn'], $splitItem['barcode'])];
            }
            
        }
        
        if (!$products) {
            return [false, '没有可确认的商品明细'];
        }

        return [true, $products];
    }
    
    /**
     * 退供单确认
     * @param $returnId
     * @param $branchId
     * @param array $returnItems
     * @return array
     * @date 2024-11-05 6:27 下午
     */
    public function doCheck($returnId, $branchId, $returnItems = [])
    {
        $vopreturnMdl = app::get('console')->model('vopreturn');
        $itemsMdl     = app::get('console')->model('vopreturn_items');
        $materialMdl  = app::get('material')->model('basic_material');
        kernel::database()->beginTransaction();

        $vopreturnMdl->update(['status' => '4','in_branch_id' => $branchId], ['id' => $returnId, 'status' => ['0', '4']]);
        $oldRow = $vopreturnMdl->db_dump($returnId, 'id,return_sn,status,return_address_no');
        if ($oldRow['status'] != '4') {
            kernel::database()->rollBack();
            return [false, '不可审核'];
        }
        
        $splitItems = [];
        if (!$returnItems) {
            $items = $itemsMdl->db->select("SELECT sum(qty) as qty,barcode,material_bn,product_name AS material_name,bm_id,po_no,price FROM `sdb_console_vopreturn_items` WHERE `return_id` =" . $returnId . " group by barcode");
        } else {
            list($res, $items) = $this->formatItems($oldRow, $returnItems);
            if (!$res) {
                kernel::database()->rollBack();
                return [false, $items];
            }
        }
        if (empty($items)) {
            kernel::database()->rollBack();
            return [false, '缺少明细'];
        }
    
        
    
        $materialBns  = array_column($items, 'material_bn');
        $materialList = $materialMdl->getList('bm_id,material_bn,material_name', ['material_bn' => $materialBns]);
        $materialList = array_column($materialList, null, 'material_bn');
    
        foreach ($items as $v) {
            if (isset($v['material_bn']) && !$v['material_bn']) {
                kernel::database()->rollBack();
                return [false, sprintf('商品条码[%s]，货号为空！', $v['barcode'])];
            }
            if (!isset($materialList[$v['material_bn']])) {
                return [false, sprintf('商品货号[%s]不存在！', $v['material_bn'])];
            }
        }
        
        $logMsg = '部分确认';
        if (!$itemsMdl->db_dump(['return_id' => $returnId, 'filter_sql' => 'split_num < qty'], 'id')) {
            $rs = $vopreturnMdl->update(['status' => '1'], ['id' => $returnId, 'status' => ['0', '4']]);
            if (is_bool($rs)) {
                kernel::database()->rollBack();
                return [false, sprintf('[%s]确认完成失败', $oldRow['return_sn'])];
            }
            $logMsg = '确认完成';
        }
        app::get('ome')->model('operation_log')->write_log('vopreturn@console', $returnId, $logMsg);
        
        $iso_id = $this->createStockin($returnId, $branchId, $items,$err_msg);
        if (!$iso_id) {

            kernel::database()->rollBack();
            return [false, '有货号不存在'];
        }
        
        kernel::database()->commit();
        //增加入库单创建日志
        app::get('ome')->model('operation_log')->write_log('create_iostock@taoguaniostockorder', $iso_id, '退供入库单新建成功');
        app::get('ome')->model('operation_log')->write_log('vopreturn@console', $returnId, '生成退供入库单成功');
        
        
        $isoObj = app::get('taoguaniostockorder')->model('iso');
        $iso    = $isoObj->db_dump($iso_id, 'check_status,branch_id,iso_bn,type_id,appropriation_no');
        if (!$iso) {
            return [false, '入库单不存在'];
        }
        
        //审核并推送wms
        $io = kernel::single('ome_iostock')->getIoByType($iso['type_id']);
        list($rs, $rsData) = kernel::single('console_iostockorder')->doCkeck($iso_id, $io);
        if (!$rs) {
            $msg = $rsData['msg'];
            return [true, $msg];
        }
        app::get('ome')->model('operation_log')->write_log('vopreturn@console', $returnId, '入库单成功推送WMS');
        return [true, '操作完成'];
    }
    
    /**
     * VOP退供创建入库单
     * @param $id
     * @param $branch_id
     * @param $items 按条码汇总明细
     * @param array $splitItems 拆分确认明细
     * @return bool|mixed|string
     * @date 2024-11-06 11:38 上午
     */
    public function createStockin($id, $branch_id, $items, &$msg = '')
    {
        $vopreturnObj = app::get('console')->model('vopreturn');
        $vopreturns   = $vopreturnObj->db_dump($id, 'shop_type,id,return_sn,status,return_address_no');
        $products = array();
        $shop_type = $vopreturns['shop_type'];
        foreach ($items as $v) {
            $products[$v['bm_id']] = array(
                'product_id' => $v['bm_id'],
                'product_bn' => $v['material_bn'],
                'name'       => $v['material_name'],
                'bn'         => $v['material_bn'],
                'price'      => $v['price'],
                'nums'       => $v['qty'],
            );
            if(isset($v['items_detail'])){
                foreach ($v['items_detail'] as $detail) {
                    $iso_items_detail                        = [
                        'product_id'   => $v['bm_id'],
                        'name'         => $v['material_name'],
                        'bn'           => $v['material_bn'],
                        'price'        => $v['price'],
                        'nums'         => $detail['num'],
                        'batch_code'   => '',
                        'product_date' => '',
                        'expire_date'  => '',
                        'sn'           => '',
                        'original_id'  => $detail['vopreturn_items_id'] ?? 0,
                        'box_no'       => $detail['box_no'] ?? '',
                        'extendpro'    => serialize(['po_no' => $detail['po_no']]),
                    ];
                    $products[$v['bm_id']]['items_detail'][] = $iso_items_detail;
                }
            }
            
        }
        
        $op_name          = kernel::single('desktop_user')->get_name();
        $iostock_instance = kernel::single('console_iostockorder');
        $shift_data       = array(
            'iostockorder_name' => date('Ymd') . 'VOP退供入库单',
            'branch'            => $branch_id,
            'type_id'           => 70,//退供入库
            'bill_type'         => self::$bill_type[$shop_type],
            'iso_price'         => 0,
            'memo'              => '退供入库',
            'operator'          => $op_name,
            'products'          => $products,
            'original_bn'       => $vopreturns['return_sn'],
            'original_id'       => $vopreturns['id'],
            'confirm'           => 'N',
        );

        $iso_id = $iostock_instance->save_iostockorder($shift_data,$msg);
        //增加入库单创建日志
        $log_msg = sprintf('退供入库单新建%s', $iso_id ? '成功' : $msg);
        app::get('ome')->model('operation_log')->write_log('create_iostock@taoguaniostockorder', $iso_id, $log_msg);
        return $iso_id;
    }
    
    /**
     * WMS入库回传更新退供单
     * @param $isoId
     * @return array
     * @date 2024-11-05 6:30 下午
     */
    public function finishStockin($isoId)
    {
        $isoMdl            = app::get('taoguaniostockorder')->model('iso');
        $isoItemsMdl       = app::get('taoguaniostockorder')->model('iso_items');
        $isoItemsDetailMdl = app::get('taoguaniostockorder')->model('iso_items_detail');
        $iso               = $isoMdl->db_dump(array('iso_id' => $isoId, 'type_id' => '70','bill_type'=>'vopjitrk'), 'iso_id,original_id,iso_status,bill_type');
        if (empty($iso) || empty($iso['original_id'])) {
            return [false, '没有vop退供单入库单'];
        }
        
        if ($iso['iso_status'] != 3) {
            return [false, 'vop退供单未全部入库'];
        }
        
        $items = $isoItemsMdl->getList('product_id,product_name,bn,nums,normal_num,defective_num', ['iso_id' => $isoId]);
        if (empty($items)) {
            return [false, '没有vop退供单明细'];
        }
        $itemObj = app::get('console')->model('vopreturn_items');
        kernel::database()->beginTransaction();
        
        $items_detail        = $isoItemsDetailMdl->getlist('product_id,product_name,bn,nums,original_id,normal_num,defective_num', array('iso_id' => $isoId), 0, -1, 'nums desc');
        foreach ($items_detail as $detail) {
            $vopreturn_items_id = $detail['original_id'];
            $nums = $detail['normal_num'] + $detail['defective_num'];
            if ($nums == 0) {
                continue;
            }
            $rs = $itemObj->updateReturnNum($vopreturn_items_id, $nums);
            if ($rs == 0) {
                kernel::database()->rollBack();
                return [false, sprintf('更新[%s]入库数量失败', $detail['bn'])];
            }
        }

        $returnId = $iso['original_id'];
        $logMsg   = '仓储退供单完成';
        $vopreturnObj = app::get('console')->model('vopreturn');
        
        $vopData     = ['in_status' => '2'];//部分入库
        $itemList = $itemObj->db_dump(['return_id' => $returnId, 'filter_sql' => 'num < qty'], 'id');
        if (!$itemList) {
            $vopData['status']    = '2';//已完成
            $vopData['in_status'] = '3';//全部入库
            $vopData['iostock_time'] = time();//入库时间
            $logMsg            = '仓储退供单全部完成';
        }
        $rsUp = $vopreturnObj->update($vopData, ['id' => $returnId, 'status' => ['1', '4']]);
        if (is_bool($rsUp)) {
            kernel::database()->rollBack();
            return [false, '确认完成失败'];
        }
        
        app::get('ome')->model('operation_log')->write_log('create_iostock@taoguaniostockorder', $isoId, '退供入库单入库完成');
        app::get('ome')->model('operation_log')->write_log('vopreturn@console', $returnId, $logMsg);
        
        // 生成售后单
        list($rs, $msg) = $this->_createAftersales($isoId);
        if (!$rs) {
            kernel::database()->rollBack();
            return [false,'生成JIT售后单失败：'.$msg];
        }
        
        kernel::database()->commit();
        return [true, '操作完成'];
    }
    
    /**
     * 生成售后单
     * @param int $iso_id
     * @return array
     **/
    public function _createAftersales($iso_id)
    {
        
        //入库单
        $isoInfo = app::get('taoguaniostockorder')->model('iso')->db_dump(['iso_id' => $iso_id, 'iso_status' => '3'], 'iso_id,iso_bn,original_id,original_bn,branch_id,bill_type,complete_time');
        if (!$isoInfo) {
            return [false, '退供单未入库'];
        }
        
        $isoItems = app::get('taoguaniostockorder')->model('iso_items_detail')->getList('*', [
            'iso_id' => $iso_id,
        ]);
        
        //退供单
        $vopreturnInfo = app::get('console')->model('vopreturn')->db_dump(['id' => $isoInfo['original_id']], 'id,return_sn,shop_id,create_time,shop_type,iostock_time');
        if (!$vopreturnInfo) {
            return [false, '退供单不存在'];
        }
        
        $aftersaleInfo = app::get('billcenter')->model('aftersales')->db_dump(['original_bn' => $isoInfo['iso_bn'], 'bill_type' => 'JIT_STOCKIN'], 'aftersale_bn');
        if ($aftersaleInfo) {
            return [false, 'VOP售后单已生成'];
        }
        
        $vopreturnItems = app::get('console')->model('vopreturn_items')->getList('id,return_id,barcode,product_name,po_no,box_no,split_num,originsaleordid', ['return_id' => $isoInfo['original_id']]);
        $vopreturnItems = array_column($vopreturnItems,null,'id');
        
        $newIsoItems = [];
        foreach($isoItems as $value){
            $poNoInfo = $vopreturnItems[$value['original_id']];
            $newIsoItems[$poNoInfo['po_no']][] = $value;
        }
    
        // 仓库信息
        $branch = app::get('ome')->model('branch')->db_dump([
            'check_permission' => 'false',
            'branch_id'        => $isoInfo['branch_id']
        ], 'branch_id,branch_bn,name');
        
        $shop = app::get('ome')->model('shop')->db_dump([
            'shop_id'        => $vopreturnInfo['shop_id']
        ], 'shop_id,shop_bn,name');
        
        $bmIds           = array_unique(array_column($isoItems, 'product_id'));
        $materialExtList = app::get('material')->model('basic_material_ext')->getList('bm_id,retail_price', ['bm_id' => $bmIds]);
        $materialExtList = array_column($materialExtList, null, 'bm_id');
        
        foreach ($newIsoItems as $po_no => $poVal) {
            $aftersales = [];
            foreach($poVal as $item){
                $returnItemVal = $vopreturnItems[$item['original_id']] ?? [];
                $nums = $item['normal_num'] + $item['defective_num'];

                if (!$item || $nums == 0) {
                    continue;
                }
                
                // 售后单数据主结构
                $aftersales['bill_bn']        = $vopreturnInfo['return_sn'];
                $aftersales['bill_type']      = 'JIT_STOCKIN';//唯品退供
                $aftersales['bill_id']        = $isoInfo['original_id'];
                $aftersales['shop_id']        = $vopreturnInfo['shop_id'];
                $aftersales['shop_bn']        = $shop['shop_bn'];
                $aftersales['shop_name']      = $shop['name'];
                $aftersales['aftersale_time'] = $isoInfo['complete_time'];//入库单完成时间
                $aftersales['original_bn']    = $isoInfo['iso_bn'];
                $aftersales['original_id']    = $isoInfo['iso_id'];
                $aftersales['branch_id']      = $branch['branch_id'];
                $aftersales['branch_bn']      = $branch['branch_bn'];
                $aftersales['branch_name']    = $branch['name'];
                $aftersales['logi_code']      = '';
                $aftersales['logi_no']        = '';
                $aftersales['po_bn']          = $returnItemVal['po_no'];
                $aftersales['order_bn']       = $returnItemVal['po_no'];
                
                //兼容唯品会多次退换 导致采购单发生变化，顾取原采购单
                if($vopreturnInfo['shop_type'] == 'vop' && !empty($returnItemVal['originsaleordid'])){
                    $aftersales['order_bn'] = $returnItemVal['originsaleordid'];
                }
                $sale_price   = $settlement_amount = $item['price'] * $nums;
                $retail_price = $materialExtList[$item['product_id']]['retail_price'] ?? 0;
                $amount       = $retail_price * $nums;
                
                $aftersales['total_amount']      += $amount;
                $aftersales['settlement_amount'] += $settlement_amount;
                $aftersales['total_sale_price']  += $sale_price;
                
                // 销售单明细结构
                $aftersales['items'][] = [
                    'material_bn'       => $item['bn'],
                    'barcode'           => $returnItemVal['barcode'] ?: '',
                    'material_name'     => $item['product_name'],
                    'bm_id'             => $item['product_id'],
                    'nums'              => $nums,
                    'price'             => $retail_price,
                    'amount'            => $amount,
                    'settlement_amount' => $settlement_amount,
                    'sale_price'        => $sale_price,
                    'box_no'            => $returnItemVal['box_no'] ?: '',
                    'original_item_id'  => $item['id'],
                ];
            }
            if (!$aftersales) {
                continue;
            }
    
            // 获取bill信息
            list($result, $err_msg) = app::get('billcenter')->model('aftersales')->create_aftersales($aftersales);
            
            if ($result == false) {
                return [false, $err_msg];
            }
            
        }
        
        return [true];
    }
    
    /**
     * 取消退供入库单
     * @param $isoId
     * @return array
     * @date 2025-04-08 3:14 下午
     */
    public function cancelStockin($isoId)
    {
        $isoInfo = app::get('taoguaniostockorder')->model('iso')->db_dump(['iso_id' => $isoId, 'iso_status' => '4'], 'iso_id,iso_bn,original_id,original_bn,branch_id,bill_type,iso_status');
        if (!$isoInfo) {
            return [false, '未查到可以取消的入库单'];
        }
        
        $vopreturnMdl      = app::get('console')->model('vopreturn');
        $vopreturnItemsMdl = app::get('console')->model('vopreturn_items');
        
        $vopreturn = $vopreturnMdl->db_dump(['return_sn' => $isoInfo['original_bn'], 'shop_type' => 'vop'], 'id,return_sn');
        if (!$vopreturn) {
            return [false, '未查到可以取消的唯品退供单'];
        }
        
        $itemDetailList = app::get('taoguaniostockorder')->model('iso_items_detail')->getList('id,product_id,bn,nums,original_id', ['iso_id' => $isoId]);
        foreach ($itemDetailList as $val) {
            $rs = $vopreturnItemsMdl->updateSplitNum($val['original_id'], $val['nums'], '-');
            if ($rs == 0) {
                return [false, sprintf('[%s]更新[%s]拆分数量失败', $vopreturn['return_sn'], $val['bn'])];
            }
        }
        $logMsg = '待确认';
        $status = 0;
        if ($vopreturnItemsMdl->db_dump(['return_id' => $vopreturn['id'], 'filter_sql' => 'split_num > 0'], 'id')) {
            $status = 4;
            $logMsg = '部分取消';
        }
        $rs = $vopreturnMdl->update(['status' => $status], ['id' => $vopreturn['id'], 'status' => ['1', '4']]);
        if (is_bool($rs)) {
            return [false, sprintf('[%s]部分取消失败', $vopreturn['return_sn'])];
        }
        app::get('ome')->model('operation_log')->write_log('vopreturn@console', $vopreturn['id'], $logMsg);
    
        return [true, '操作完成'];
    }
    
    /**
     * 编辑明细
     * @param $data
     * @return array
     * @date 2025-05-12 上午11:28
     */
    public function editVopreturn($data)
    {
        $itemMdl              = app::get('console')->model('vopreturn_items');
        $basicMaterialObj     = app::get('material')->model('basic_material');
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');
        $atList               = $data['at'] ?? [];
        $upList               = $data['up'] ?? [];
        
        $return_id   = $data['return_id'];
        $product_ids = $data['product_id'] ?? [];
        $item_ids    = $data['item_id'] ?? [];
        
        $basicMInfoList = $basicMaterialObj->getList('bm_id,material_bn,material_name', array('bm_id' => $product_ids));
        $basicMInfoList = array_column($basicMInfoList, null, 'bm_id');
        
        $vopreturnItems    = $itemMdl->getList('*', ['return_id' => $return_id]);
        $vopreturnItems    = array_column($vopreturnItems, null, 'id');
        $newVopreturnItems = [];
        $localItemIds      = [];
        foreach ($vopreturnItems as $val) {
            $newVopreturnItems[$val['po_no'] . '_' . $val['box_no'] . '_' . $val['bm_id']] = $val;
            if ($val['source'] == 'local') {
                $localItemIds[] = $val['id'];
            }
        }
        
        //开启事务
        kernel::database()->beginTransaction();
        $errMsg = [];
        
        //删除
        $diffItemIds = array_diff($localItemIds,$item_ids);
        if ($diffItemIds) {
            $itemMdl->delete(['id' => $diffItemIds, 'source' => 'local']);
        }
        
        if ($item_ids) {
            foreach ($item_ids as $item_id) {
                //检测是否已存在
                $po_no      = $upList['po_no'][$item_id];
                $box_no     = $upList['box_no'][$item_id];
                $storage_box_no = $upList['storage_box_no'][$item_id];
                $num        = (int)$upList['num'][$item_id];
                
                $price      = $upList['price'][$item_id];
                $info       = $vopreturnItems[$item_id];
                if($num == 0){
                    $errMsg[] = sprintf('采购单内【%s】条码【%s】数量【%s】不能为0！', $po_no, $info['barcode'], $num);
                    continue;
                }
                $count      = $itemMdl->count(['po_no' => $po_no, 'box_no' => $box_no, 'barcode' => $info['barcode']]);
                if ($count > 1) {
                    $errMsg[] = sprintf('采购单内【%s】基础物料编码【%s】箱号【%s】不能相同！', $po_no, $info['barcode'], $box_no);
                    continue;
                }
                $up_item['price']      = $price;
                $up_item['po_no']      = $po_no;
                $up_item['box_no']     = $box_no;
                $up_item['storage_box_no'] = $storage_box_no;
                $up_item['qty']        = $num;
    
                $itemMdl->update($up_item, ['id' => $item_id]);
            }
        }
        
        $insertData = [];
        foreach ($product_ids as $product_id) {
            //检测是否已存在
            $po_no      = $atList['po_no'][$product_id];
            $box_no     = $atList['box_no'][$product_id];
            $storage_box_no = $atList['storage_box_no'][$product_id];
            $price      = $atList['price'][$product_id];
            $num      = (int)$atList['num'][$product_id];
            if($num == 0){
                continue;
            }
            
            $itemInfo   = $newVopreturnItems[$po_no . '_' . $box_no . '_' . $product_id] ?? [];
            if ($itemInfo) {
                $errMsg[] = sprintf('采购单内【%s】条码【%s】箱号【%s】不能相同！', $po_no, $itemInfo['barcode'], $box_no);
                continue;
            }
            $item['return_id']    = $return_id;
            $item['bm_id']        = $product_id;
            $item['material_bn']  = $basicMInfoList[$product_id]['material_bn'];
            $item['product_name'] = $basicMInfoList[$product_id]['material_name'];
            $item['barcode']      = $basicMaterialBarcode->getBarcodeById($product_id);
            $item['price']        = $price;
            $item['po_no']        = $po_no;
            $item['box_no']       = $box_no;
            $item['storage_box_no']   = $storage_box_no;
            $item['qty']          = $num;
            $item['source']       = 'local';
            $insertData[] = $item;
        }
        if ($errMsg) {
            kernel::database()->rollBack();
            return [false, implode('；', $errMsg)];
        }
        
        if ($insertData) {
            $sql = kernel::single('ome_func')->get_insert_sql($itemMdl, $insertData);
            $re  = $itemMdl->db->exec($sql);
            if (!$re) {
                kernel::database()->rollBack();
                return [false, '明细写入失败！'];
            }
        }
    
        kernel::database()->commit();
        app::get('ome')->model('operation_log')->write_log('vopreturn@console', $return_id, '明细编辑成功');
        return [true, '成功'];
        
    }
}
