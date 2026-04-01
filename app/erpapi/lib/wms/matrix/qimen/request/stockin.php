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
 * 入库单推送
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_matrix_qimen_request_stockin extends erpapi_wms_request_stockin
{
    protected function _format_stockin_cancel_params($sdf)
    {
        $params = parent::_format_stockin_cancel_params($sdf);
        $params['warehouse_code'] = $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']);
        $params['order_id'] = $sdf['out_iso_bn'];
        if (isset($sdf['owner_code'])) {
            $params['ownerCode'] = $sdf['owner_code'];
        }
        
        //采购入库
        if($sdf['bill_type'] && $sdf['bill_type'] == 'asn'){
            $params['order_type'] = $this->transfer_stockin_type('PURCHASE');
        }

        return $params;
    } 

    protected function _format_stockin_create_params($sdf)
    {
        $params = parent::_format_stockin_create_params($sdf);
        $params['warehouse_code'] = $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']);
        if (isset($sdf['owner_code'])) {
            $params['ownerCode'] = $sdf['owner_code'];
        }

        $params['logistics_no'] = $sdf['logi_no'];
        
        //采购入库
        if($sdf['bill_type'] && $sdf['bill_type'] == 'asn'){
            $params['order_type'] = $this->transfer_stockin_type('PURCHASE');
        }

        // 唯品会退供
        if($sdf['bill_type'] && $sdf['bill_type'] == 'vopjitrk'){
            $params['purchase_code'] = $sdf['original_bn'];
        }
        // jdl
        if($sdf['bill_type'] && $sdf['bill_type'] == 'jdlreturn'){
            $params['purchase_code'] = $sdf['business_bn'];
        }
        $appkey = $this->__channelObj->wms['addon']['wms_appkey'];
        $items = array('item'=>array());
        if ($sdf['items']){
            foreach ((array) $sdf['items'] as $k => $v){
                $material = kernel::single('material_basic_material')->getBasicMaterialBybn($v['bn']);
                $foreignsku = app::get('console')->model('foreign_sku')->dump(array('wms_id'=>$this->__channelObj->wms['channel_id'],'inner_sku'=>$v['bn']));

                $tmpitem = array(
                    'item_code'       => $foreignsku['oms_sku'] ? $foreignsku['oms_sku'] : $v['bn'],
                    'item_name'       => $v['name'],
                    'item_quantity'   => (int)$v['num'],
                    'item_price'      => $v['price'] ? (float)$v['price'] : 0,// TODO: 商品价格
                    'item_line_num'   => $v['item_line_num'] ?: ($k + 1),// TODO: 订单商品列表中商品的行项目编号，即第n行或第n个商品
                    'trade_code'      => isset($v['batch_code']) ? $v['batch_code'] : '',//批次号 //可选(若是淘宝交易订单，并且不是赠品，必须要传订单来源编号)
                    'item_id'         => $foreignsku['outer_sku'] ? $foreignsku['outer_sku'] : $v['bn'],// 商品ID
                    'is_gift'         => '0',// TODO: 判断是否为赠品0:不是1:是
                    'item_remark'     => '',// TODO: 商品备注
                    'inventory_type'  => $sdf['branch_type'] == 'damaged' ? '101' : '1',// TODO: 库存类型1可销售库存101类型用来定义残次品201冻结类型库存301在途库存
                    'item_sale_price' => $material['retail_price'] ? (float)$material['retail_price'] : 0,
                    'ownerCode'       => $sdf['owner_code'],
                    'product_date'    => isset($v['product_date']) ? $v['product_date'] : '',//生产日期
                    'expire_date'     => isset($v['expire_date']) ? $v['expire_date'] : '',//过期日期
                );
                if(in_array($appkey,array('31417025'))){
                    $tmpitem['sku_property'] = $v['box_no'];
                }

                // 行明细扩展信息
                if ($extendpro = @unserialize($v['extendpro'])) {
                    // 唯品会退供采购PO单号
                    $extendpro['po_no'] && $tmpitem['extendProps']['po_no'] = $extendpro['po_no'];
                }

                // 获取外部商品sku
                $items['item'][] = $tmpitem;
            }
        }

        $params['items'] = json_encode($items);

        if ($sdf['receiver_state']) $params['receiver_state']       = $sdf['receiver_state'];
        if ($sdf['receiver_city']) $params['receiver_city']         = $sdf['receiver_city'];
        if ($sdf['receiver_district']) $params['receiver_district'] = $sdf['receiver_district'];
        if ($sdf['receiver_name']) $params['receiver_name']         = $sdf['receiver_name'];
        if ($sdf['receiver_address']) $params['receiver_address']   = $sdf['receiver_address'];
        if ($sdf['receiver_phone']) $params['receiver_phone']       = $sdf['receiver_phone'];
        if ($sdf['receiver_mobile']) $params['receiver_mobile']     = $sdf['receiver_mobile'];

        // 调拨出库收货人：仓库
        if ($sdf['io_type'] == 'ALLCOATE') {
            if($sdf['appropriation_no']) {
                $params['related_orders'] = json_encode(['related_order'=>[['order_type'=>'DB','order_code'=>$sdf['appropriation_no']]]]);
            }
            if ($sdf['branch_id']) {
                $branch = app::get('ome')->model('branch')->dump($sdf['branch_id']);
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

            if ($sdf['extrabranch_id']) {
                $branch = app::get('ome')->model('branch')->dump($sdf['extrabranch_id']);
                $area = $branch['area'];

                if ($area) {
                    kernel::single('eccommon_regions')->split_area($area);
                    $params['shipper_state']    = $area[0];
                    if($params['shipper_state']){
                        $params['shipper_state'] = $this->_formate_receiver_citye($params['shipper_state']);
                    }
                    $params['shipper_city']     = $area[1];
                    $params['shipper_district'] = $area[2];
                }

                $params['shipper_zip']     = $branch['zip'];
                $params['shipper_name']    = $branch['uname'];
                $params['shipper_address'] = $branch['address'];
                $params['shipper_phone']   = $branch['phone'];
                $params['shipper_mobile']  = $branch['mobile'];
            }
        }
        
        return $params;
    }
}
