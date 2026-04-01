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
 * 退货单推送
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_matrix_qimen_request_reship extends erpapi_wms_request_reship
{
    public function reship_cancel($sdf){
        $title = $this->__channelObj->wms['channel_name'].'退货单取消';
        
        //单据类型
        //$order_type = 'IN_SALE_RETURN';
        
        $oReship = app::get('ome')->model('reship');
        $reship_data = $oReship->db_dump(array('reship_id'=>$sdf['reship_id']),'return_type');
        
        //单据类型
        if ($reship_data['return_type'] == 'change') {
            $order_type = 'IN_EXCHANGE'; //换货入库
        }else{
            $order_type = 'IN_RETURN'; //退货入库
        }
        
        //params
        $params = array(
            'order_type'     => $order_type,
            'out_order_code' => $sdf['reship_bn'],
            'warehouse_code' => $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']),
        );
        if (isset($sdf['owner_code'])) {
            $params['ownerCode'] = $sdf['owner_code'];
        }

        return $this->__caller->call(WMS_ORDER_CANCEL, $params, null, $title, 10, $sdf['reship_bn']);
    }

    protected function _format_reship_create_params($sdf)
    {
        $params = parent::_format_reship_create_params($sdf);
        $params['warehouse_code'] = $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']); 

        switch ($sdf['return_type']) {
            case 'return':
                $params['order_type'] = 'THRK'; // 退货入库
                break;
            case 'change':
                $params['order_type'] = 'HHRK'; // 换货入库 
                break;
            default:
                $params['order_type'] = '';
                break;
        }
        
        $items = array('item'=>array());
        if ($sdf['items']){


            sort($sdf['items']);
            foreach ((array) $sdf['items'] as $k => $v){
                $foreignsku = app::get('console')->model('foreign_sku')->dump(array('wms_id'=>$this->__channelObj->wms['channel_id'],'inner_sku'=>$v['bn']));
                // 获取外部商品sku
                $items['item'][] = array(
                    'item_code'      => $foreignsku['oms_sku'] ? $foreignsku['oms_sku'] : $v['bn'],
                    'item_name'      => $v['name'],
                    'item_quantity'  => (int)$v['num'],
                    'item_price'     => $v['price'] ? (float)$v['price'] : 0,// TODO: 商品价格
                    'item_line_num'  => ($k + 1),// TODO: 订单商品列表中商品的行项目编号，即第n行或第n个商品
                    'trade_code'     => $sdf['order_bn'],//可选(若是淘宝交易订单，并且不是赠品，必须要传订单来源编号) 
                    'item_id'        => $foreignsku['outer_sku'] ? $foreignsku['outer_sku'] : $v['bn'],// 商品ID
                    'is_gift'        => '0',// TODO: 判断是否为赠品0:不是1:是
                    'item_remark'    => '',// TODO: 商品备注
                    'inventory_type' => $sdf['branch_type'] == 'damaged' ? '101' : '1',// TODO: 库存类型1可销售库存101类型用来定义残次品201冻结类型库存301在途库存
                    'ownerCode'      => $sdf['owner_code'],
                    'sn'             => $v['sn'] ? ['sn' => $v['sn']] : '',
                );
            }
        }

        $params['items'] = json_encode($items);

        $params['wms_order_code'] = $sdf['original_delivery_bn'];
        $params['orig_order_code'] = $sdf['delivery_bn'];
        
        // 施华洛世奇历史订单导入退货原出库单传订单
        if (in_array(base_shopnode::node_id('ome'), ['1798140238', '1695100739']) && !$params['orig_order_code']) {
            $params['orig_order_code'] = $sdf['order_bn'];
        }
    
        $extend_props = [];
        //仓库自定义字段-活动号
        if (isset($sdf['activity_no'])) {
            $extend_props['activity_no'] = $sdf['activity_no'];
        }
        if ($extend_props) {
            $params['extendProps'] = json_encode($extend_props);
        }

        return $params;
    }
}
