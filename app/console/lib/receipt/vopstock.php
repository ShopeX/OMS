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
* 出入库单据相关处理
*/
class console_receipt_vopstock
{
    private $_stockoutObj = null;

    private $_stockItemObj = null;

    private $_isoInfo = array();

    private $_items = array();
    
    private static $iso_status =array(
        'PARTIN'=>2 ,
        'FINISH'=>3,
        'CLOSE'=>4,
        'CANCEL'=>4,
        'FAILED'=>4,
    );

    function __construct()
    {
        $this->_stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $this->_stockItemObj   = app::get('purchase')->model('pick_stockout_bill_items');
        $this->_pickObj        = app::get('purchase')->model('pick_bills');
    }
    
    /**
     * 出入库据保存(io为0出库,1入库)
     * 
     * @param array $data
     * @param int $io
     * @param string $msg
     * 
     * @return boolean
     */
    public function do_save($data, $io, &$msg)
    {
        $thx = kernel::database()->beginTransaction();

        try {

            $this->_processStockout($data, $io);
            
            kernel::database()->commit($thx);

        } catch (\Exception $e) {
            $msg = $e->getMessage();

            kernel::database()->rollBack();

            return false;
        }

        return true;
    }

    /**
     * 唯品会出库
     *
     * @param array $data
     * @param int $io
     * @return bool
     * @throws Exception 如果业务处理失败，抛出错误信息
     **/
    private function _processStockout($data, $io)
    {
        $obj_stockout    = app::get('purchase')->model('pick_stockout_bills');

        $filter = array('stockout_no'=>$data['io_bn']);
        $stockout_info = $obj_stockout->db_dump($filter,'stockout_id,stockout_no,branch_id,o_status');

        if (!$stockout_info) {
            throw new \Exception("JIT出库单【{$data['io_bn']}】不存在");
        }

        $stockout_id = $stockout_info['stockout_id'];

        // 更新运单号
        $upData = [];
        if($data['logi_no']) {
            $upData['delivery_no'] = $data['logi_no'];
        }
        if(isset($iso['weight'])) {
            $upData['weight'] = $data['weight'];
        }

        if($upData && !$obj_stockout->update($upData,array('stockout_id'=>$stockout_id)) ){
            throw new \Exception('更新运单号/重量失败');
        }

        if (!$data['packages']) {
            throw new \Exception('出库失败，没有包裹明细');
        }

        $pick_bill_packages = array(); $bn_packages = array();
        foreach ($data['packages'] as $value) {
            $bn = strtoupper($value['bn']);

            // 指定拣货单
            if ($value['bill_id']) {
                $pick_bill_packages[$value['bill_id']][$bn] = $value;
            } else {
                // 第三方仓可能货号重复
                $bn_packages[$bn]['bn']               = $value['bn'];
                $bn_packages[$bn]['package_code']     = $value['package_code'];
                $bn_packages[$bn]['entry_normal_num'] += $value['entry_normal_num'];
            }
        }

        $box_data = array();
        $stockoutItemModel = app::get('purchase')->model('pick_stockout_bill_items');

        // 出库单明细
        foreach ($stockoutItemModel->getList('*', array('stockout_id'=>$stockout_id, 'is_del'=>'false')) as $value) {

            // 过滤已经出库明细
            if ($value['actual_num'] >= $value['item_num']) continue;

            $bn = strtoupper($value['bn']);

            // 指定拣货单
            if ($pick_bill_packages[$value['bill_id']][$bn]) {
                $box_data[] = array(
                    'bill_id'          => $value['bill_id'],         // 拣货单ID
                    'po_id'            => $value['po_id'],         // 采购单ID
                    'bn'               => $value['bn'],         // 货号
                    'box_num'          => $pick_bill_packages[$value['bill_id']][$bn]['entry_normal_num'],         // 数量
                    'box_no'           => $pick_bill_packages[$value['bill_id']][$bn]['package_code'],         // 箱号
                    'stockout_item_id' => $value['stockout_item_id'],         // 出库单明细ID
                    'price'            => $value['price'],
                    'product_id'       => $value['product_id'],
                );
            } elseif ($bn_packages[$bn] && $bn_packages[$bn]['entry_normal_num'] > 0) {
                $box_num = min($value['item_num']-$value['actual_num'], $bn_packages[$bn]['entry_normal_num']);

                $box_data[]  = array(
                    'bill_id'          => $value['bill_id'],                    // 拣货单ID
                    'po_id'            => $value['po_id'],                      // 采购单ID
                    'bn'               => $value['bn'],                         // 货号
                    'box_num'          => $box_num,                             // 数量
                    'box_no'           => $bn_packages[$bn]['package_code'],    // 箱号
                    'stockout_item_id' => $value['stockout_item_id'],           // 出库单明细ID
                    'price'            => $value['price'],
                    'product_id'       => $value['product_id'],
                );

                $bn_packages[$bn]['entry_normal_num'] -= $box_num;

                if ($bn_packages[$bn]['entry_normal_num'] <= 0) {
                    unset($bn_packages[$bn]);
                }
            }
        }

        if ($bn_packages){
            $msg = '';
            foreach ($bn_packages as $bn => $pack) {
                $msg .= sprintf('【%s】多发数量%s；', $bn, $pack['entry_normal_num']);
            }

            throw new \Exception($msg);
        }

        // 确认出库后，更新单据相关状态和数量
        list($result,$msg) = kernel::single('vop_pick_stockout_bills')->do_stockout($stockout_info,$box_data);
        if ( !$result ) {
            throw new \Exception('唯品会JIT出库失败：'.$msg);
        }
        
        // 生成出入库明细
        if ($box_data) {
            $stockLib    = kernel::single('siso_receipt_iostock_vopstockout');
        
            $iostock_instance  = kernel::single('siso_receipt_iostock');
            $stockLib->_typeId = $iostock_instance::VOP_STOCKOUT;
            
            $iostockData = [
                'iso_id' => $stockout_id,
                'items' => [],
            ];
            
            foreach ($box_data as $value) {
                $iostockData['items'][] = [
                    'iso_items_id' => $value['stockout_item_id'],
                    'bn' => $value['bn'],
                    'price' => $value['price'],
                    'nums' => $value['box_num'],

                ];
            }

            $result = $stockLib->create($iostockData, $tmp,  $msg);
            if (!$result) {
                throw new \Exception($msg);
            }

            //释放冻结
            $storeManageLib    = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id'=>$stockout_info['branch_id']));
        
            $params                 = [];
            $params['node_type']    = 'finishVopstockout';
            $params['params']       = array('stockout_id'=>$stockout_info['stockout_id'], 'branch_id'=>$stockout_info['branch_id']);
        
            //装箱出库明细
            foreach ($box_data as $value) {
                $params['params']['items'][] = [
                    'product_id' => $value['product_id'],
                    'num' => $value['box_num'],
                    'bn' => $value['bn'],
                ];
            }
        
            $processResult    = $storeManageLib->processBranchStore($params, $err_msg);
            if (!$processResult){
                throw new \Exception($err_msg);
            }
        }

        // 生成销售单
        list($rs, $msg) = $this->_createSales($stockout_info['stockout_id']);
        if (!$rs) {
            throw new \Exception('生成JIT销售单失败：'.$msg);
        }

        return true;
    }

    /**
     * 生成销售单
     *
     * @param int $stockout_id
     * @return array
     **/
    private function _createSales($stockout_id)
    {
        $stockoutBillItems = app::get('purchase')->model('pick_stockout_bill_items')->getList('*', [
            'stockout_id' => $stockout_id,
            'is_del' => 'false',
        ]);
        
        // 采购单
        $poList = app::get('purchase')->model('order')->getList('po_id,po_bn', [
            'po_id' => array_column($stockoutBillItems, 'po_id')
        ]);
        $poList = array_column($poList, null, 'po_id');
        
        // 拣货单
        $billIds = array_column($stockoutBillItems, 'bill_id');
        $pickBills = app::get('purchase')->model('pick_bills')->getList('bill_id,pick_no,shop_id', [
            'bill_id' => $billIds
        ]);
        $pickBills = array_column($pickBills, null, 'bill_id');

        // 出库单
        $stockoutBill = app::get('purchase')->model('pick_stockout_bills')->db_dump($stockout_id);

        // 仓库信息
        $branch = app::get('ome')->model('branch')->db_dump([
            'check_permission' => 'false', 
            'branch_id' => $stockoutBill['branch_id']
        ], 'branch_id,branch_bn,name');
    
        $shopIds  = array_unique(array_column($pickBills, 'shop_id'));
        $shopList = app::get('ome')->model('shop')->getList('shop_id,shop_bn,name', ['shop_id' => $shopIds]);
        $shopList = array_column($shopList, null, 'shop_id');
    
        $bmIds           = array_unique(array_column($stockoutBillItems, 'product_id'));
        $materialExtList = app::get('material')->model('basic_material_ext')->getList('bm_id,retail_price', ['bm_id' => $bmIds]);
        $materialExtList = array_column($materialExtList, null, 'bm_id');
        
        // 按bill_id分组
        $salesList = [];
        foreach ($stockoutBillItems as $item) {

            if ($item['actual_num'] == 0) {
                continue;
            }

            // 销售单数据主结构
            $shop_id = $pickBills[$item['bill_id']]['shop_id'];
            $salesList[$item['bill_id']]['bill_bn'] = $pickBills[$item['bill_id']]['pick_no'];
            $salesList[$item['bill_id']]['bill_type'] = 'JIT_STOCKOUT';
            $salesList[$item['bill_id']]['bill_id'] = $pickBills[$item['bill_id']]['bill_id'];
            $salesList[$item['bill_id']]['shop_id'] = $shop_id;
            $salesList[$item['bill_id']]['shop_bn'] = $shopList[$shop_id]['shop_bn'] ?? '';
            $salesList[$item['bill_id']]['shop_name'] = $shopList[$shop_id]['name'] ?? '';
            $salesList[$item['bill_id']]['sale_time'] = $stockoutBill['ship_time'];
            $salesList[$item['bill_id']]['ship_time'] = $stockoutBill['ship_time'];
            $salesList[$item['bill_id']]['original_bn'] = $stockoutBill['stockout_no'];
            $salesList[$item['bill_id']]['original_id'] = $stockoutBill['stockout_id'];
            $salesList[$item['bill_id']]['branch_id'] = $branch['branch_id'];
            $salesList[$item['bill_id']]['branch_bn'] = $branch['branch_bn'];
            $salesList[$item['bill_id']]['branch_name'] = $branch['name'];
            $salesList[$item['bill_id']]['logi_code'] = $stockoutBill['carrier_code'];
            $salesList[$item['bill_id']]['logi_no'] = $stockoutBill['delivery_no'];
            $salesList[$item['bill_id']]['po_bn'] = $poList[$item['po_id']]['po_bn'];
            $salesList[$item['bill_id']]['order_bn'] = $poList[$item['po_id']]['po_bn'];
    
            $retail_price = $materialExtList[$item['product_id']]['retail_price'] ?? 0;
            $amount       = $retail_price * $item['actual_num'];
            $sale_price   = $settlement_amount = $item['price'] * $item['actual_num'];
            

            $salesList[$item['bill_id']]['total_amount'] += $amount;
            $salesList[$item['bill_id']]['total_sale_price'] += $sale_price;
            $salesList[$item['bill_id']]['settlement_amount'] += $settlement_amount;

            // 销售单明细结构
            $salesList[$item['bill_id']]['items'][] = [
                'material_bn' => $item['bn'],
                'barcode' => $item['barcode'],
                'material_name' => $item['product_name'],
                'bm_id' => $item['product_id'],
                'nums' => $item['actual_num'],
                'original_item_id' => $item['stockout_item_id'],
                'price' => $retail_price,
                'amount' => $amount,
                'settlement_amount' => $settlement_amount,
                'sale_price' => $sale_price,
            ];
        }

        if (!$salesList) {
            return [false, '未查到JIT出库单已出库明细'];
        }


        foreach ($salesList as $sales) {
            // 获取bill信息
            list($rs) = $result = app::get('billcenter')->model('sales')->create_sales($sales);

            if ($rs == false){
                return $result;
            }
        }

        return [true];
    }
}
