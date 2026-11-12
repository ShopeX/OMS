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
 * 订单接口处理
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_response_qianniu extends erpapi_shop_response_abstract
{
    /**
     * ERP订单
     * 
     * @var string
     * */

    public $_order_detail= array();

    /**
     * 订单接收格式
     * 
     * @var string
     * */
    public $_qnordersdf = array();
    
    /**
     * 改地址消息
     * @var array
     */
    public $_notifysdf = array();

    /**
     * 是否接收订单
     * 
     * @return void
     * @author
     * */
    protected function _canModify()
    {
        $orderModel = app::get('ome')->model('orders');
        $filter = array('order_bn'=>$this->_qnordersdf['bizOrderId'],'shop_id'=>$this->__channelObj->channel['shop_id']);

        $this->_order_detail = $orderModel->dump($filter,'*',array("order_objects"=>array("*",array("order_items"=>array('*')))));
        if (!$this->_order_detail){
            $shopId = $this->__channelObj->channel['shop_id'];
            $orderRsp = kernel::single('erpapi_router_request')->set('shop',$shopId)->order_get_order_detial($this->_qnordersdf['bizOrderId']);
            if ($orderRsp['rsp'] == 'succ') {
                $msg = '';
                $rs = kernel::single('ome_syncorder')->get_order_log($orderRsp['data']['trade'],$shopId,$msg);
                if ($rs) {
                    $this->_order_detail = $orderModel->dump($filter,'*',array("order_objects"=>array("*",array("order_items"=>array('*')))));
                }
            }
            if(!$this->_order_detail) {
                return true;
            }
        }
        //只针对未发货  部分发货也不可以修改
        if (!in_array($this->_order_detail['status'],array('active')) || !in_array($this->_order_detail['ship_status'],array('0')) || !in_array($this->_order_detail['process_status'],array('unconfirmed','confirmed','splitting','splited'))){
            $this->__apilog['result']['msg'] = '对应状态不可以 编辑订单';
            $this->__apilog['result']['msg_code'] = '200006';
            return false;
        }

        return true;
    }

    protected function _formatSdf(){}

        /**
     * 添加ress_modify
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function address_modify($sdf)
    {

        $this->_qnordersdf = $sdf;
        $this->__apilog['result']['data'] = array('tid'=>$this->_qnordersdf['bizOrderId']);
        $this->__apilog['original_bn']    = $this->_qnordersdf['bizOrderId'];
        $this->__apilog['title']          = '千牛/平台修改订单地址['.$this->_qnordersdf['bizOrderId'].']';

        $this->_order_detail=array();

        $accept = $this->_canModify();

        //本地作配置开启判断
        if ($accept === false) {
            return array();
        }
        $this->_formatSdf();
        if (!$this->_qnordersdf['consignee']){
            $this->__apilog['result']['msg'] = '地区格式有误';
            $this->__apilog['result']['msg_code'] = '100002';
            return array();
        }
        
        //地址是否发生变化

        $oldconsignee = array(
            'name'      =>  $this->_order_detail['consignee']['name'],
            'area'      =>  $this->_order_detail['consignee']['area'],
            'addr'      =>  $this->_order_detail['consignee']['addr'],
            'zip'       =>  $this->_order_detail['consignee']['zip'],
            'mobile'    =>  $this->_order_detail['consignee']['mobile'],
        );
        $newconsignee = $this->_qnordersdf['consignee'];


        $diff_consignee = array_diff_assoc($newconsignee,$oldconsignee);
        if (!$diff_consignee){
            $this->__apilog['result']['msg'] = '地址没有变化';
           return array();
        }
        $new_order = array();
        $new_order['order_id']      = $this->_order_detail['order_id'];
        $new_order['consignee']     = $newconsignee;
        //暂停成功
        $new_order['confirm']        = 'N';
        $new_order['process_status'] = 'unconfirmed';
        $new_order['pause']          = 'false';
        $convert_order = array(
            'new_order'     =>  $new_order,
            'order_detail'  =>  $this->_order_detail,
        );
        return $convert_order;
    }

    protected function _formatModifysku($sdf) {
        $params = [
            'order_bn' => $sdf['bizOrderId'],
            'old_sale_bn' => $sdf['oldOuterId'] ? : $sdf['outer_sku_id_from'],
            'sale_bn' => $sdf['outerId'] ? : $sdf['outer_sku_id_to'],
        ];
        return $params;
    }
    /**
     * 换货 sku
     * @param $sdf
     */
    public function modifysku($sdf)
    {
        // params
        $sdf = $this->_formatModifysku($sdf);
        
        // shop_type
        $sdf['shop_type'] = $this->__channelObj->channel['shop_type'];
        $sdf['shop_id'] = $this->__channelObj->channel['shop_id'];
        
        // apilog
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title'] = '更换sku校验';
        
        // 淘宝自助改sku需要在1S之内返回是否允许修改SKU
        if(in_array($sdf['shop_type'], ['taobao'])){
            // 记录开始执行时间（微秒）
            $sdf['_modifysku_start_time'] = microtime(true);
            
            // 直接返回相关参数
            return $sdf;
        }
        
        // 其它平台走以下逻辑
        $orders_obj = app::get('ome')->model('orders');
        $order_objects_obj = app::get('ome')->model('order_objects');
        $delivery_order_obj = app::get('ome')->model('delivery_order');
        $delivery_obj = app::get('ome')->model('delivery');
        $shop_obj = app::get('ome')->model('shop');
        $ome_branch_product = app::get('ome')->model('branch_product');
        $basic_material_obj = app::get('material')->model('basic_material');
        $sales_material_obj = app::get('material')->model('sales_material');
        $material_sales_basic_material_obj = app::get('material')->model('sales_basic_material');
        $basicMStockFreezeLib   = kernel::single('material_basic_material_stock_freeze');
        
        // 订单信息
        $order_filter = array('order_bn'=>$sdf['order_bn'],'shop_id'=>$this->__channelObj->channel['shop_id']);
        $orders_info = $orders_obj->dump($order_filter);
        if (!$orders_info) {
            //如果没有找到，先去拉取订单
            $shopId = $this->__channelObj->channel['shop_id'];
            $orderRsp = kernel::single('erpapi_router_request')->set('shop', $shopId)->order_get_order_detial($sdf['order_bn']);
            if ($orderRsp['rsp'] == 'succ') {
                $msg = '';
                $rs = kernel::single('ome_syncorder')->get_order_log($orderRsp['data']['trade'], $shopId, $msg);
                if ($rs) {
                    $orders_info = $orders_obj->dump($order_filter, '*');
                }
            }

            if (!$orders_info) {
                $this->__apilog['result']['msg'] = '订单不存在';
                $this->__apilog['result']['msg_code'] = '2001';
                return false;
            }
        }
        
        if($orders_info['is_modify'] == 'true') {
            $this->__apilog['result']['msg'] = '订单已被修改';
            $this->__apilog['result']['msg_code'] = '3003';
            return false;
        }
        
        //获得店铺对应的仓库的库存
        $shop_info = $shop_obj->dump(['shop_id' => $orders_info['shop_id']]);
        $shop_bn = $shop_info['shop_bn'];
        $branches = app::get('ome')->getConf('shop.branch.relationship');
        $shop_branch = $branches[$shop_bn];
        $skuId = $sdf['sale_bn'];
        $sales_material_info = $sales_material_obj->dump(['sales_material_bn'=>$skuId],'sm_id');
        if(!$sales_material_info) {
            $this->__apilog['result']['msg'] = '没有找到对应销售物料！';
            $this->__apilog['result']['msg_code'] = '3003';
            return false;
        }
        $sm_id = $sales_material_info['sm_id'];

        $sales_basic_material_info = $material_sales_basic_material_obj->getList('*', ['sm_id'=>$sm_id]);
        if(!$sales_basic_material_info){
            $this->__apilog['result']['msg'] = '没有找到对应基础物料！';
            $this->__apilog['result']['msg_code'] = '3003';
            return false;
        }
        $bm_id = array_column($sales_basic_material_info, 'bm_id');
        $shop_branch_ids = array_keys($shop_branch);
        $branch_product = $ome_branch_product->getList('product_id,store_freeze,store', ['product_id' => $bm_id, 'branch_id' => $shop_branch_ids]);
        $sum_store = [];
        foreach($branch_product as $bp) {
            $sum_store[$bp['product_id']] += $bp['store'] - $bp['store_freeze'];
        }
        $salesBasic = array_column($sales_basic_material_info, null, 'bm_id');
        foreach($sum_store as $bmId => $num) {
            $sum_store[$bmId] = bcdiv($num, $salesBasic[$bmId]['number'], 1);
        }
        $minStore = min($sum_store);

        //订单商品表里购买的数量
        $order_objects_info = $order_objects_obj->dump(['bn' => $sdf['old_sale_bn'], 'order_id'=>$orders_info['order_id'], 'shop_goods_id|noequal'=>'-1'], 'goods_id,quantity');
        if (!$order_objects_info) {
            $this->__apilog['result']['msg'] = '订单对应的销售物料不存在';
            $this->__apilog['result']['msg_code'] = '2001';
            return false;
        }
        if ($minStore < $order_objects_info['quantity']) {
            $this->__apilog['result']['msg'] = '库存不足';
            $this->__apilog['result']['msg_code'] = '1030';
            return false;
        }

        if($orders_info['ship_status'] != 0) {
            $this->__apilog['result']['msg'] = '订单已发货或者已退货无法修改sku';
            $this->__apilog['result']['msg_code'] = '1015';
            return false;
        }

        if(in_array($orders_info['pay_status'],[4,5,6,7])) {
            $this->__apilog['result']['msg'] = '退款或者退款中无法修改sku';
            $this->__apilog['result']['msg_code'] = '1015';
            return false;
        }

        //查看发货单状态
        $delivery_ids = $delivery_obj->getDeliverIdByOrderId($orders_info['order_id'], true);

        //如有有发货单
        foreach ($delivery_ids as $delivery_id) {
            $delivery_info = $delivery_obj->dump(['delivery_id' => $delivery_id]);

            //取消发货单
            if (in_array($delivery_info['status'], ['ready', 'progress']) && (
                $delivery_info['is_bind'] == 'true' || $delivery_info['parent_id'] == 0
            )) {
                $memo = 'sku换货取消发货单';
                $result = $delivery_obj->rebackDelivery($delivery_id, $memo);
                if (!$result) {
                    $this->__apilog['result']['msg'] = '撤销发货单失败';
                    $this->__apilog['result']['msg_code'] = '3003';
                    return false;
                }

                if ($delivery_info['is_bind'] == 'true') {
                    $child_ids = $delivery_obj->getItemsByParentId($delivery_id, 'array');
                    foreach($child_ids as $id){
                        $delivery_obj->rebackDelivery($id, '合并发货单叫回');
                    }
                }
            }
        }
        
        $params = [
            'order_id' => $orders_info['order_id'],
            'old_sale_bn' => $sdf['old_sale_bn'],
            'sale_bn' => $sdf['sale_bn'],
        ];

        return $params;
    }
    
    /**
     * order_addr_modify
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function order_addr_modify($sdf)
    {
        $this->_notifysdf                 = $sdf;
        $this->__apilog['result']['data'] = array('tid' => $this->_notifysdf['orderId']);
        $this->__apilog['original_bn']    = $this->_notifysdf['orderId'];
        $this->__apilog['title']          = '平台修改订单地址[' . $this->_notifysdf['orderId'] . ']';
        $convert_order                    = array(
            'order_bn' => $this->_notifysdf['orderId'],
            'shop_id'  => $this->__channelObj->channel['shop_id'],
        );
        return $convert_order;
    }

}
