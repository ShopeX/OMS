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
 * 出库单推送
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_matrix_qimen_request_stockout extends erpapi_wms_request_stockout
{
    public function stockout_cancel($sdf){
        $stockout_bn = $sdf['io_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '出库单取消';

        $params = array(
            'order_type'     => $this->transfer_stockout_type($sdf['io_type']),
            'out_order_code' => $stockout_bn,
            'warehouse_code' => $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']),
            'order_id'       => $sdf['out_iso_bn'],
        );
        if (isset($sdf['owner_code'])) {
            $params['ownerCode'] = $sdf['owner_code'];
        }

        return $this->__caller->call(WMS_ORDER_CANCEL, $params, null, $title, 10, $stockout_bn);
    }

    protected function _vop_format_stockout_create_params($sdf){
        $params = $this->_format_stockout_create_params($sdf);
        $params['shipping_type']  = $sdf['dly_mode'] ? kernel::single('purchase_purchase_stockout')->getDlyMode($sdf['dly_mode']) : '';
        $params['logistics_code'] = $sdf['carrier_code'];
        $params['logistics_name'] = $sdf['carrier_name'];
        $params['target_entry_order_code'] = $sdf['storage_no'];
        $params['receiver_warehouse_code'] = $sdf['to_branch_no'];
        $params['receiving_time'] = $sdf['arrival_time'];
        $params['remark']         = 'JIT单据'; #唯品会JIT出库

        $bnToBarcode = array_column($sdf['items'], 'barcode', 'bn');

        $params['items'] = json_decode($params['items'],1);
        // 查询是否有重点检查、优先发货等标签
        if ($sdf['quality_check']) {
            $params['extendProps']['quality_check'] = true;

            // 提取重点检测的barcode合集
            $quality_check_barcode = [];
            foreach ($sdf['quality_check'] as $pick_bn => $items) {
                foreach ($items as $key => $v) {
                    $quality_check_barcode[$v['barcode']] = $v['barcode'];
                }
            }

            // 给重点检测的明细打标
            foreach ($params['items']['item'] as $ik => $iv) {
                if (in_array($bnToBarcode[$iv['item_code']], $quality_check_barcode)) {
                    $params['items']['item'][$ik]['extendProps']['quality_check_itemcode'] = true;
                }
            }
        }
        // 优先发货
        if ($sdf['action_list']) {
            $params['extendProps']['priority_delivery'] = true;

            // 提取优先发货的barcode合集
            $priority_delivery_barcode = $sdf['action_list']['priorityDelivery'];

            // 给优先发货的明细打标
            foreach ($params['items']['item'] as $ik => $iv) {
                if (in_array($bnToBarcode[$iv['item_code']], $priority_delivery_barcode)) {
                    $params['items']['item'][$ik]['extendProps']['priority_delivery_itemcode'] = true;
                }
            }
        }
        
        if (isset($params['extendProps'])) {
            $params['extendProps'] = json_encode($params['extendProps']);
        }
        $params['items'] = json_encode($params['items']);

        return $params;
    }

    protected function _format_stockout_create_params($sdf)
    {
        $params = parent::_format_stockout_create_params($sdf);
        $params['warehouse_code'] = $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']);

        $items = array('item'=>array());
        if ($sdf['items']){
            foreach ($sdf['items'] as $k => $v){
                $foreignsku = app::get('console')->model('foreign_sku')->dump(array('wms_id'=>$this->__channelObj->wms['channel_id'],'inner_sku'=>$v['bn']));

                $items['item'][] = array(
                    'item_code'      => $foreignsku['oms_sku'] ? $foreignsku['oms_sku'] : $v['bn'],
                    'item_name'      => $v['name'],
                    'item_quantity'  => (int)$v['num'],
                    'item_price'     => $v['price'] ? (float)$v['price'] : 0,// TODO: 商品价格
                    'item_line_num'  => ($k + 1),// TODO: 订单商品列表中商品的行项目编号，即第n行或第n个商品
                    'trade_code'     => isset($v['batch_code']) ? $v['batch_code'] : '',//批次号 //可选(若是淘宝交易订单，并且不是赠品，必须要传订单来源编号)
                    'item_id'        => $foreignsku['outer_sku'] ? $foreignsku['outer_sku'] : $v['bn'],// 商品ID
                    'is_gift'        => '0',// TODO: 判断是否为赠品0:不是1:是
                    'item_remark'    => '',// TODO: 商品备注
                    'inventory_type' => $sdf['branch_type'] == 'damaged' ? '101' : '1',// TODO: 库存类型1可销售库存101类型用来定义残次品201冻结类型库存301在途库存
                    'ownerCode'       => $sdf['owner_code'],
                    'product_date'    => isset($v['product_date']) ? $v['product_date'] : '',//生产日期
                    'expire_date'     => isset($v['expire_date']) ? $v['expire_date'] : '',//过期日期
                );
            }
        }

        $params['items'] = json_encode($items);


        // 采购退货收货人：供应商
        if ($sdf['supplier_id'] && $sdf['io_type'] == 'PURCHASE_RETURN') {
            $supplier = app::get('purchase')->model('supplier')->dump($sdf['supplier_id']);

            $area = $supplier['area'];
            if ($area) {
                kernel::single('eccommon_regions')->split_area($area);
                $params['receiver_state']    = $area[0];
                $params['receiver_city']     = $area[1];
                $params['receiver_district'] = $area[2];
            }

            $params['receiver_zip']     = $supplier['zip'];
            $params['receiver_name']    = $supplier['name'];
            $params['receiver_address'] = $supplier['addr'];
            $params['receiver_phone']   = $supplier['telphone'];
            $params['receiver_mobile']  = $supplier['telphone'];
        }

        // 调拨出库收货人：仓库
        if ($sdf['io_type'] == 'ALLCOATE') {
            if($sdf['appropriation_no']) {
                $params['related_orders'] = json_encode(['related_order'=>[['order_type'=>'DB','order_code'=>$sdf['appropriation_no']]]]);
            }
            if ($sdf['extrabranch_id'] && $sdf['bill_type']!='o2oprepayed') {
                $branch = app::get('ome')->model('branch')->dump($sdf['extrabranch_id']);
                $area = $branch['area'];

                if ($area) {
                    kernel::single('eccommon_regions')->split_area($area);
                    $params['receiver_state']    = $area[0];
                    $params['receiver_city']     = $area[1];
                    $params['receiver_district'] = $area[2];
                }

                $params['receiver_zip']     = $branch['zip'];
                $params['receiver_name']    = $branch['uname'];
                $params['receiver_address'] = $branch['address'];
                $params['receiver_phone']   = $branch['phone'];
                $params['receiver_mobile']  = $branch['mobile'];
            }

            if ($sdf['branch_id']) {
                $branch = app::get('ome')->model('branch')->dump($sdf['branch_id']);
                $area = $branch['area'];

                if ($area) {
                    kernel::single('eccommon_regions')->split_area($area);
                    $params['shipper_state']    = $area[0];
                    $params['shipper_city']     = $area[1];
                    $params['shipper_district'] = $area[2];
                }

                $params['shipper_zip']     = $branch['zip'];
                $params['shipper_name']    = $branch['uname'];
                $params['shipper_address'] = $branch['address'];
                $params['shipper_phone']   = $branch['phone'];
                $params['shipper_mobile']  = $branch['mobile'];
            }

        // } elseif ($sdf['io_type'] == 'JITCK') {
        //     $wms_branch_bn    = $this->get_wms_branch_bn($this->__channelObj->wms['channel_id'], $sdf['branch_bn']);
        //     $extendProps = array(
        //         'VpPackageNO'       =>  $sdf['po_bn'],//唯品会PO单号
        //         'VpStockInOrderNO'  =>  $sdf['storage_no'],//唯品会入库单号
        //         'VpShippingInfo'    =>  $wms_branch_bn.'|'.$sdf['arrival_time'].'|'.$sdf['dly_mode'],//唯品会送货仓库|要求到货时间|送货模式
        //         'VpWH'              =>  $sdf['to_branch_no'],//站点（唯品会仓库名称）|仓库编码
        //         'VpLogisticsCode'   =>  $sdf['carrier_code'],//承运商编码
        //         'VpExpressCode'     =>  $sdf['delivery_no'],//运单号
        //         'VpBrand'           =>  '',//品牌信息
        //         'VpPickNo'          =>  implode(',',$sdf['pick_no_list']),//拣货单号
        //     );

        //     $params['extendProps'] = $extendProps;

        }
        // 仓库自定义字段-活动号
        $extend_props = [];
        if (isset($sdf['activity_no'])) {
            $extend_props['activity_no'] = $sdf['activity_no'];
        }
        if ($extend_props) {
            $params['extendProps'] = json_encode($extend_props);
        }

        return $params;
    }
}
