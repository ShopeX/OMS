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
* alibaba(阿里巴巴平台)订单处理 抽象类
*
* @author chenping<chenping@shopex.cn>
* @version $Id: abstract.php 2013-3-12 17:23Z
*/
class erpapi_shop_matrix_alibaba_response_order extends erpapi_shop_response_order
{

    /**
     * business_flow
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function business_flow($sdf)
    {
        if ($sdf['t_type'] == 'fenxiao' || $sdf['order_source'] == 'taofenxiao') {
            $order_type = 'b2b';
        } else {
            $order_type = 'b2c';
        }

        return 'erpapi_shop_matrix_alibaba_response_order_'.$order_type;
    }

    protected function _analysis()
    {
        parent::_analysis();

        if( !$this->_ordersdf['payinfo']['pay_name'] ) $this->_ordersdf['payinfo']['pay_name'] = '支付宝担保交易';

        if($this->_ordersdf['shipping']['is_cod'] == 'true'){
            $this->_ordersdf['pay_bn']              = 'online';
            $this->_ordersdf['payinfo']['pay_name'] = '货到付款';
            $this->_ordersdf['pay_status']          = '0';
            $this->_ordersdf['payments']            = array();
            $this->_ordersdf['payment_detail']      = array();
        }

        // 获取货号
        foreach ($this->_ordersdf['order_objects'] as $objkey => $object) {
            if($object['sub_order_bn']){
                $this->_ordersdf['order_objects'][$objkey]['oid'] = $object['sub_order_bn'];
            } else{
                $this->_ordersdf['order_objects'][$objkey]['oid'] = $object['oid'];
            }
            
            $goods = $this->item_get($object['bn']);
            if ($goods){
                $this->_ordersdf['order_objects'][$objkey]['shop_goods_id'] = $object['bn'];
                $this->_ordersdf['order_objects'][$objkey]['bn']            = $goods['bn'];
                foreach ($object['order_items'] as $itemkey => $item) {
                    #普通商品类型
                    if($object['obj_type'] == 'goods'){
                        $this->_ordersdf['order_objects'][$objkey]['bn'] = isset($goods['skus']) ? $goods['skus'][$item['specId']]['bn'] : $object['bn'];#重新整理objects这层的货号
                    }
                    $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['shop_goods_id'] = $object['bn'];
                    $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['shop_product_id'] = $item['specId'];
                    $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['bn'] = isset($goods['skus']) ? $goods['skus'][$item['specId']]['bn'] : $goods['bn'];
                    $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['product_attr'] = isset($goods['skus']) ? $goods['skus'][$item['specId']]['product_attr'] : '';

                    $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['pmt_price'] = bcsub($item['amount'], $item['sale_price'],3);
                }
            }else{
                foreach ($object['order_items'] as $itemkey => $item) {
                    $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['pmt_price'] = bcsub($item['amount'], $item['sale_price'],3);
                }
            }
            
        }
        
        // 官方提货物流
        if ($this->_ordersdf['officialPickUp']) {
            $this->_ordersdf['official_pickup'] = 'true';
            $this->_ordersdf['shipping']['shipping_name'] = 'pickup';
        }

        // 判断是否有退款
        if ($this->_ordersdf['payed'] > $this->_ordersdf['total_amount']) {
            $this->_ordersdf['pay_status'] = '6';
            $this->_ordersdf['pause']      = 'true';
        }
    }

    /**
     * 获取货品
     *
     * @param String $num_iid 商品ID
     * @return void
     * @author 
     **/
    protected function item_get($num_iid)
    {
        static $goods;

        if ($goods[$num_iid]) return $goods[$num_iid];

        $rs = kernel::single('erpapi_router_request')->set('shop',$this->__channelObj->channel['shop_id'])->product_item_get($num_iid);

        if ($rs['rsp'] == 'fail' || !$rs['data'] ){
            return array();
        }
        
        $item = $rs['data']['toReturn'][0]; unset($item['details']);

        $feature = array();
        foreach ((array) $item['productFeatureList'] as $value) {
            if ($value['name'] == '货号') {
                $goods[$num_iid]['bn'] = $value['value'];
            }
            $feature[$value['fid']] = $value;
        }

        if ($item['isSkuOffer'] == true) {
            foreach ((array) $item['skuArray'] as $v1) {
                if ($v1['children']) {
                    foreach ((array) $v1['children'] as $v2) {
                        $goods[$num_iid]['skus'][$v2['specId']]['bn'] = $v2['cargoNumber'];
                        $goods[$num_iid]['skus'][$v2['specId']]['product_attr'] = array(
                            0 => array('value' => $v1['value'], 'label' => $feature[$v1['fid']]['name']),
                            1 => array('value' => $v2['value'], 'label' => $feature[$v2['fid']]['name'] ),
                        );
                    }
                } else {
                    $goods[$num_iid]['skus'][$v1['specId']]['bn'] = $v1['cargoNumber'];
                    $goods[$num_iid]['skus'][$v1['specId']]['product_attr'] = array(
                        0 => array( 'value' => $v1['value'], 'label' => $feature[$v1['fid']]['name']),
                    );
                }
            }
        }
        
        return $goods[$num_iid];
    }

    /**
     * 订单组件
     *
     * @return void
     * @author 
     **/
    protected function get_update_components()
    {
        $components = array('markmemo','custommemo','marktype','consignee');

        return $components;
    }

    /**
     * 创建订单的插件
     *
     * @return void
     * @author 
     **/
    protected function get_create_plugins()
    {
        $plugins = parent::get_create_plugins();

        $plugins[] = 'orderextend';

        return $plugins;
    }

    /**
     * 更新订单插件
     *
     * @return void
     * @author 
     **/
    protected function get_update_plugins()
    {
        $plugins = parent::get_update_plugins();

        $plugins[] = 'promotion';
        $plugins[] = 'refundapply';

        return $plugins;
    }
}
