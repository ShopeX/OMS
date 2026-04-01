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
 * 获取数据(以下处理数据都已非合单为单位)
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class ome_event_trigger_shop_data_delivery_common
{
    protected $__deliverys = array();

    protected $__delivery_orders = array();

    protected $__sdf = array();
    
    protected $firstDeliveryId = 0;//第一张
    
    protected $lastDeliveryId = [];//最后一张
    
    //强制按照oid纬度回写平台发货状态
    protected $_forced_sync_oid = false;
    
    //是否经销商订单
    protected $_is_dealer_order = false;
    
    final public function init($deliverys,$delivery_orders)
    {
        // 发货单
        foreach ($deliverys as $key => $value) {
            $this->__deliverys[$value['delivery_id']] = $value;
        }

        // 发货单对应订单
        $this->__delivery_orders = $delivery_orders;

        $this->__sdf = array();

        return $this;
    }


    /**
     * 不支持拆单回写
     *
     * @return void
     * @author
     **/
    protected function _nonsupport_mode_request($delivery_id)
    {
        $delivery = $this->__deliverys[$delivery_id];
        $order    = $this->__delivery_orders[$delivery_id];

        // 订单拆单判断
        $is_split = $this->_is_split_order($delivery_id);

        if ($is_split) {
            $order_delivery = $this->_get_order_delivery_ids($delivery_id);

                $check_delivery_id = $delivery['parent_id'] > 0 ? $delivery['parent_id'] : $delivery_id;

                // 如果只有一张发货成功的单子
                $tmp_delivery = array();
                foreach ($order_delivery as $key => $value) {
                    if ($value['status'] == 'succ') $tmp_delivery[strval($value['delivery_id'])] = $value['delivery_time'];
                }
                asort($tmp_delivery);
                reset($tmp_delivery);
                $firstDeliveryId = key($tmp_delivery);
                $last_deliverytime = end($tmp_delivery);
                $lastDeliveryId = array_keys($tmp_delivery,$last_deliverytime);
    
                $this->firstDeliveryId = $firstDeliveryId;
                $this->lastDeliveryId  = $lastDeliveryId;

            $split_type = app::get('ome')->getConf('ome.order.split_type');
            if ($split_type == '1' && $check_delivery_id != $firstDeliveryId) { // 以第一张回写
                return false;
            }

            if ($split_type  == '2' && ($order['ship_status']!='1' || !in_array($check_delivery_id, $lastDeliveryId) || $order['sync'] == 'succ' )) { // 以最后回写
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $delivery_id 如果是合并发货单，取的是子单
     *
     * @return array
     * @author
     **/
    public function get_sdf($delivery_id)
    {
        $delivery = $this->__deliverys[$delivery_id];
        $order    = $this->__delivery_orders[$delivery_id];
        
        //check
        if (empty($delivery) || empty($order)) {
            return array();
        }
        
        //[天猫]补发订单--进行补发单回传平台
        if($order['order_type'] == 'bufa' && $order['relate_order_bn'] && in_array($order['shop_type'], array('taobao','tmall'))){
            //淘宝订单履约-补发场景-回传补发单号（taobao.reissue.consign）
            
        }elseif($order['createway'] != 'matrix'){
            //不是平台获取
            return array();
        }
        
        //经销商订单强制按照oid纬度回写平台发货状态
        if($order['platform_order_bn'] && $order['betc_id']){
            //强制标记
            $this->_forced_sync_oid = true;
            
            //经销商订单标记
            $this->_is_dealer_order = true;
        }
        
        //回写方式的平台,例如：回写第一张、回写最后一张
        $syncShopTypes = array(
                'yunji','weidian','ecos.b2c','ecos.dzg','taobao','shopex_b2b','shopex_fy','wdwd',
                'aikucun','weimobv','yutang','mingrong','beibei','360buy','yangsc','yunji4fx',
                'luban','xiaohongshu','xhs','weimobr','yunmall','meituan4medicine','yunji4pop',
                'ecos.ecshopx','weixinshop','website','website_d1m','wxshipin','zkh','kuaishou','pinduoduo',
                'meituan4bulkpurchasing','meituan4sg','website_v2','xiaomi',
        );
        
        // 开启拆单
        $switch = $this->_is_split_switch($delivery_id);
        if ($switch != '0' && !in_array($order['shop_type'], $syncShopTypes) ){
            $isRequest = $this->_nonsupport_mode_request($delivery_id);

            if ($isRequest === false) return array();
        }

        // 如果只有OMS赠品不再回写平台
        $this->_get_delivery_items_sdf($delivery_id);
        $shop_goods_id = array_unique(array_column((array)$this->__sdf['delivery_items'], 'shop_goods_id'));
        $item_type     = array_unique(array_column((array)$this->__sdf['delivery_items'], 'item_type'));
        if (1 == count((array)$shop_goods_id) 
            && 1 == count((array)$item_type) 
            && 'gift' == $item_type[0] 
            && '-1' == $shop_goods_id[0]){
                return [];
        }
        
        // 获取发货单信息
        $this->_get_delivery_sdf($delivery_id);

        return $this->__sdf;
    }

    /**
     * 获取添加发货单SDF
     *
     * @return void
     * @author
     **/
    public function get_add_delivery_sdf($delivery_id){return array();}

    /**
     * 获取发货单
     *
     * @return void
     * @author
     **/
    final protected function _get_delivery_sdf($delivery_id)
    {
        $delivery = $this->__deliverys[$delivery_id];
        $order    = $this->__delivery_orders[$delivery_id];

        $corp = $this->_get_corp($delivery['logi_id']);

        $this->__sdf['delivery_id']          = $delivery['delivery_id'];
        $this->__sdf['delivery_bn']          = $delivery['delivery_bn'];
        $this->__sdf['parent_id']            = $delivery['parent_id'];
        $this->__sdf['parent_bn']            = $this->_get_parent_bn($delivery['parent_id']);
        $this->__sdf['branch_id']            = $delivery['branch_id'];
        $this->__sdf['status']               = $delivery['status'];
        $this->__sdf['logi_id']              = $delivery['logi_id'];
        $this->__sdf['logi_no']              = $delivery['logi_no'];
        $this->__sdf['crossborder_res_id']   = $corp['crossborder_res_id'];#跨境配送资源id
        $this->__sdf['corp_type']            = $corp['corp_type'];#类型(本地、跨境)
        $this->__sdf['crossborder_region_id']  = $corp['crossborder_region_id'];#发货地区域id
        $this->__sdf['logi_name']            = $corp['name'];
        $this->__sdf['logi_type']            = $corp['type'];
        
        $this->__sdf['logi_model'] = $corp['corp_model']; //物流模式(normal:普通快递,instatnt:同城配送,seller:商家配送)
        
        $this->__sdf['is_cod']               = $delivery['is_cod'];
        $this->__sdf['itemNum']              = $delivery['itemNum'];
        $this->__sdf['delivery_time']        = $delivery['delivery_time'];
        $this->__sdf['last_modified']        = $delivery['last_modified'];
        $this->__sdf['delivery_cost_actual'] = $delivery['delivery_cost_actual'];
        $this->__sdf['create_time']          = $delivery['create_time'];
        $this->__sdf['is_protect']           = $delivery['is_protect'];
        $this->__sdf['delivery']             = $delivery['delivery'];
        $this->__sdf['memo']                 = $delivery['memo'];
        
        $this->__sdf['delivery_type'] = $delivery['delivery']; //配送方式
        
        $this->__sdf['consignee'] = array(
            'name'      => $delivery['ship_name'],
            'area'      => $delivery['ship_area'],
            'addr'      => $delivery['ship_addr'],
            'zip'       => $delivery['ship_zip'],
            'email'     => $delivery['ship_email'],
            'mobile'    => $delivery['ship_mobile'],
            'telephone' => $delivery['ship_tel'],
        );

        $this->__sdf['orderinfo']['order_id']      = $order['order_id'];
        $this->__sdf['orderinfo']['order_bn']      = $order['order_bn'];
        $this->__sdf['orderinfo']['ship_status']   = $order['ship_status'];
        $this->__sdf['orderinfo']['createway']     = $order['createway'];
        $this->__sdf['orderinfo']['sync']          = $order['sync'];
        $this->__sdf['orderinfo']['is_cod']        = $order['is_cod'];
        $this->__sdf['orderinfo']['self_delivery'] = $order['self_delivery'];
        $this->__sdf['orderinfo']['order_bool_type'] = $order['order_bool_type'];
        $this->__sdf['orderinfo']['tostr'] = $order['tostr'];
        $this->__sdf['orderinfo']['is_modify'] = $order['is_modify']; //是否手工编辑过订单
        
        $this->__sdf['orderinfo']['order_type'] = $order['order_type'];
        $this->__sdf['orderinfo']['relate_order_bn'] = $order['relate_order_bn'];
        
        if ($delivery['type'] == 'reject') {
            $this->__sdf['logi_no']   = $order['order_bn'];
            $this->__sdf['logi_name'] = '其他物流公司';
            $this->__sdf['logi_type'] = 'OTHER';
        }

        //来源渠道
        if ($order['order_source']) {
            $this->__sdf['orderinfo']['order_source'] = $order['order_source'];
        }
        
        //平台订单号
        if($order['platform_order_bn']){
            $this->__sdf['orderinfo']['platform_order_bn'] = $order['platform_order_bn'];
        }
        
        //经销商订单标记
        if($this->_is_dealer_order){
            $this->__sdf['is_dealer_order'] = true;
        }
    }

   
    final protected function _get_parent_bn($parent_id = 0)
    {
        $info = [];
        if ($parent_id > 0) {
            $info = app::get('ome')->model('delivery')->db_dump(['delivery_id'=>$parent_id],'delivery_bn');
        }
        if (!$parent_id || !$info) {
            return 0;
        } else {
            return $info['delivery_bn'];
        }
    }
    
    
    
    /**
     * 获取发货的商品明细
     *
     * @param $delivery_id 发货单ID
     * @param $allDelivery 获取明细方式(false:指定发货单明细, true:订单对应所有发货单的明细)
     * @param $check_ship_status 检查订单发货状态
     * @return void
     */
    protected function _get_delivery_items_sdf($delivery_id, $allDelivery = false, $check_ship_status=true)
    {
        $delivery_items = array();

        
        if($allDelivery) {
            $delivery_items_detail = $this->_get_delivery_items_detail_order($delivery_id, $check_ship_status);
        } else {
            $delivery_items_detail = $this->_get_delivery_items_detail($delivery_id);
        }
        $order_objects         = $this->_get_order_objects($delivery_id);

        foreach ($delivery_items_detail as $key => $value) {
            $order_item = $order_objects[$value['order_obj_id']]['order_items'][$value['order_item_id']];
            
            // 订单基础物料已经发货数量
            $item_sendnum = intval($order_item['sendnum']);
            
            if ($value['item_type'] == 'pkg') {
                $number = $order_objects[$value['order_obj_id']]['quantity']*$value['number']/$order_item['nums'];
                
                // 公式：子订单已发货数量 = (子订单数量 * 基础物料已发货数量) / 基础物料数量
                // 先乘后除可以减少精度损失，使用 floor() 确保向下取整为整数
                if ($order_item['nums'] > 0 && isset($order_objects[$value['order_obj_id']]['quantity'])) {
                    $obj_sendnum = intval($order_objects[$value['order_obj_id']]['quantity'] / $order_item['nums'] * $item_sendnum);
                } else {
                    $obj_sendnum = 0;
                }
                
                $delivery_items['obj_'.$value['order_obj_id'] . '_' . $value['delivery_id']] = array(
                    'name'          => trim($order_objects[$value['order_obj_id']]['name']),
                    'bn'            => trim($order_objects[$value['order_obj_id']]['bn']),
                    'number'        => $number,
                    'sendnum'       => $obj_sendnum,
                    'item_type'     => $value['item_type'],
                    'shop_goods_id' => $order_objects[$value['order_obj_id']]['shop_goods_id'],
                    'shop_product_id' => $order_item['shop_product_id'],
                    'oid'           => $order_objects[$value['order_obj_id']]['oid'],
                    'main_oid'      => $order_objects[$value['order_obj_id']]['main_oid'],
                    'logi_no'       => $value['logi_no'] ? $value['logi_no'] : $this->__sdf['logi_no'],
                    'logi_type'     => $value['logi_type'] ? $value['logi_type'] : $this->__sdf['logi_type'],
                    'logi_name'     => $value['logi_name'] ? $value['logi_name'] : $this->__sdf['logi_name'],
                    'delivery_id'   =>$value['delivery_id'],
                    'nums' => $order_objects[$value['order_obj_id']]['quantity'], //购物数量
                    'order_obj_id' => $value['order_obj_id'], //obj_id
                    'product_bn' => trim($value['bn']), //货号
                    'sku_uuid'      =>$order_objects[$value['order_obj_id']]['sku_uuid'],

                );

            } else {
                $delivery_items['item_'.$value['order_item_id'] . '_' . $value['delivery_id']] = array(
                    'name'          => trim($order_objects[$value['order_obj_id']]['name']),
                    'bn'            => trim($order_objects[$value['order_obj_id']]['bn']),
                    'number'        => $value['number'],
                    'item_type'     => $value['item_type'],
                    'bm_id'         => $order_item['product_id'],
                    'shop_goods_id' => $order_item['shop_goods_id'],
                    'shop_product_id' => $order_item['shop_product_id'],
                    'promotion_id'  => $order_item['promotion_id'],
                    'oid'           => $order_objects[$value['order_obj_id']]['oid'],
                    'main_oid'      => $order_objects[$value['order_obj_id']]['main_oid'],
                    'nums'          => $order_item['nums'],
                    'sendnum'       => $order_item['sendnum'],
                    'logi_no'         => $value['logi_no'] ? $value['logi_no'] : $this->__sdf['logi_no'],
                    'logi_type'       => $value['logi_type'] ? $value['logi_type'] : $this->__sdf['logi_type'],
                    'logi_name'     => $value['logi_name'] ? $value['logi_name'] : $this->__sdf['logi_name'],
                    'delivery_id'   =>$value['delivery_id'],
                    'order_obj_id' => $value['order_obj_id'], //obj_id
                    'product_bn' => trim($value['bn']), //货号
                    'sku_uuid'      =>$order_objects[$value['order_obj_id']]['sku_uuid'],

                );
            }
        }

        $this->__sdf['delivery_items'] = $delivery_items;
    }

    final protected function _get_order_objects_sdf($delivery_id)
    {
        $order_objects = $this->_get_order_objects($delivery_id);

        $order_objects_sdf = array();
        foreach ($order_objects as $object) {

            $order_items_sdf = array();
            foreach ($object['order_items'] as $item) {
                $order_items_sdf[$item['item_id']]['product_id']    = $item['product_id'];
                $order_items_sdf[$item['item_id']]['bn']            = $item['bn'];
                $order_items_sdf[$item['item_id']]['shop_goods_id'] = $item['shop_goods_id'];
                $order_items_sdf[$item['item_id']]['sendnum']       = $item['sendnum'];
                $order_items_sdf[$item['item_id']]['name']          = $item['name'];
                $order_items_sdf[$item['item_id']]['promotion_id']  = $item['promotion_id'];
                $order_items_sdf[$item['item_id']]['item_type']     = $item['item_type'];
                $order_items_sdf[$item['item_id']]['nums']          = $item['nums'];
            }

            $order_objects_sdf[$object['obj_id']]['bn']            = $object['bn'];
            $order_objects_sdf[$object['obj_id']]['oid']           = $object['oid'];
            $order_objects_sdf[$object['obj_id']]['shop_goods_id'] = $object['shop_goods_id'];
            $order_objects_sdf[$object['obj_id']]['quantity']      = $object['quantity'];
            $order_objects_sdf[$object['obj_id']]['name']          = $object['name'];
            $order_objects_sdf[$object['obj_id']]['obj_type']      = $object['obj_type'];
            $order_objects_sdf[$object['obj_id']]['order_items']   = $order_items_sdf;
            $order_objects_sdf[$object['obj_id']]['store_code']    = $object['store_code'];

            $order_objects_sdf[$object['obj_id']]['sku_uuid']      = $object['sku_uuid'];

        }

        $this->__sdf['orderinfo']['order_objects'] = $order_objects_sdf;
    }
    #获取所有订单明细(包括已删除商品)
    final protected function _get_order_all_objects_sdf($delivery_id){
       $order_objects = $this->_get_all_order_objects($delivery_id);

       $order_objects_sdf = array();
       foreach ($order_objects as $object) {
          $order_items_sdf = array();
          foreach ($object['order_items'] as $item) {
             $order_items_sdf[$item['item_id']]['bn']            = $item['bn'];
             $order_items_sdf[$item['item_id']]['shop_goods_id'] = $item['shop_goods_id'];
             $order_items_sdf[$item['item_id']]['sendnum']       = $item['sendnum'];
             $order_items_sdf[$item['item_id']]['name']          = $item['name'];
             $order_items_sdf[$item['item_id']]['promotion_id']  = $item['promotion_id'];
             $order_items_sdf[$item['item_id']]['item_type']     = $item['item_type'];
             $order_items_sdf[$item['item_id']]['nums']          = $item['nums'];
          }
          $order_objects_sdf[$object['obj_id']]['bn']            = $object['bn'];
          $order_objects_sdf[$object['obj_id']]['oid']           = $object['oid'];
          $order_objects_sdf[$object['obj_id']]['shop_goods_id'] = $object['shop_goods_id'];
          $order_objects_sdf[$object['obj_id']]['quantity']      = $object['quantity'];
          $order_objects_sdf[$object['obj_id']]['name']          = $object['name'];
          $order_objects_sdf[$object['obj_id']]['obj_type']      = $object['obj_type'];
          $order_objects_sdf[$object['obj_id']]['order_items']   = $order_items_sdf;
       }
       $this->__sdf['orderinfo']['order_objects'] = $order_objects_sdf;
    }
    final protected function _get_all_order_objects($delivery_id){
       static $order_all_objects;
       $order = $this->__delivery_orders[$delivery_id];

       if (isset($order_all_objects[$order['order_id']])) return $order_all_objects[$order['order_id']];

       $orderIds = array();
       foreach ($this->__delivery_orders as $key => $value) {
          $orderIds[] = $value['order_id'];
          $order_all_objects[$value['order_id']] = array();
       }

       $orderItemModel = app::get('ome')->model('order_items');
       $rows = $orderItemModel->getList('*',array('order_id'=>$orderIds));
       $order_items = array();
       foreach ($rows as $row) {
          $order_items[$row['obj_id']][$row['item_id']] = $row;
       }

       $orderObjModel = app::get('ome')->model('order_objects');
       $rows = $orderObjModel->getList('*',array('obj_id'=>array_keys($order_items)));
       foreach ($rows as $row) {
          $row['order_items'] = $order_items[$row['obj_id']];
          $order_all_objects[$row['order_id']][$row['obj_id']] = $row;
       }
       return $order_all_objects[$order['order_id']];
    }

    final protected function _get_members_sdf($delivery_id)
    {
        $member = $this->_get_members($delivery_id);

        $this->__sdf['memberinfo']['uname'] = $member['uname'];
    }

    final protected function _get_product_serial_sdf($delivery_id)
    {
        $serial = $this->_get_product_serial($delivery_id);

        if (!$serial) return;

        $feature = array();

        $delivery = $this->__deliverys[$delivery_id];
        if ($delivery['parent_id'] > 0) {
            // 获取父发货单对应的所有订单
            $deliveryItemDetailModel = app::get('ome')->model('delivery_items_detail');
            $deliveryItemDetailList = $deliveryItemDetailModel->getList('*',array('delivery_id'=>$delivery['parent_id']));

            $product_serial = array();
            foreach ($deliveryItemDetailList as $key => $value) {
                if (!$serial[$value['product_id']]) continue;

                $product_serial[$value['order_item_id']] = array_splice($serial[$value['product_id']], 0, $value['number']);
            }


        } else {
            $deliveryItemDetailList = $this->_get_delivery_items_detail($delivery_id);
            $product_serial = array();
            foreach ($deliveryItemDetailList as $key => $value) {
                if (!$serial[$value['product_id']]) continue;

                $product_serial[$value['order_item_id']] = array_splice($serial[$value['product_id']], 0, $value['number']);

            }
        }

        $order_objects = $this->_get_order_objects($delivery_id);
        foreach ($order_objects as $object) {
            $obj_product_serial = array();
            foreach ($object['order_items'] as $item) {
                if ($product_serial[$item['item_id']])
                    $obj_product_serial[] = implode(',',$product_serial[$item['item_id']]);
            }

            if ($object['oid'] && $obj_product_serial) {
                $feature[] = $object['oid'].':'.implode('|',$obj_product_serial);
            }
        }

        if ($feature) {
            if ($this->__sdf['feature']) {
                $this->__sdf['feature'] .= ';identCode='.implode('|',$feature);
            } else {
                $this->__sdf['feature'] = 'identCode='.implode('|',$feature);
            }
        }
    }

    /**
     * 拆单
     *
     * @return void
     * @author
     **/
    final protected function _get_split_sdf($delivery_id)
    {
        $delivery = $this->__deliverys[$delivery_id];
        $order    = $this->__delivery_orders[$delivery_id];

        if ($delivery['type'] != 'normal') return;
    
        //未开启强制oid纬度标记,则进行拆单判断
        if(!$this->_forced_sync_oid) {
            // 开启了拆单
            $switch = $this->_is_split_switch($delivery_id);
            if ($switch == '0') return;
            
            // 判断订单是否拆单
            $is_split = $this->_is_split_order($delivery_id);
            if (!$is_split) return;
        }
        
        $delivery_items_detail = $this->_get_delivery_items_detail($delivery_id);
        $order_objects = $this->_get_order_objects($delivery_id);
        
        // 如果有删除明细，全量回写(oid不存在，肯定有删除)
        $delete_exist = false;
        foreach ($order_objects as $object) {
            if (!$object['oid'] && $object['shop_goods_id'] != '-1') {
                $delete_exist = true; break;
            }
        }

        if ($delete_exist) return;

        $this->__sdf['is_split'] = 1;
        // 子单号
        $order_oid = array();
        foreach ($order_objects as $object) {
            $order_oid[$object['obj_id']] = $object['oid'];
        }


        // 如果有退款,默认只查询15条有商品明细的退款记录
        $refund_bn = array();
        $refundApplyModel = app::get('ome')->model('refund_apply');
        $refundApplyList = $refundApplyModel->getList('product_data', array('order_id'=>$order['order_id'], 'status'=>'4', 'product_data|noequal'=>''), 0, 15);
        if ($refundApplyList) {
            foreach ($refundApplyList as $key => $value) {
                $product_data = unserialize($value['product_data']);
                foreach ($product_data as $k => $v) {
                    $refund_bn[]    = $v['bn'];
                }
            }
        }

        // 如果有余单撤销,把其他未返回的一并回传了

        // 正常回写
        $oid_list = array();
        $oid_numlist = array();
        foreach ($delivery_items_detail as $key => $value) {
            if (in_array($value['bn'], $refund_bn)) continue;

            $oid_list[] = $order_oid[$value['order_obj_id']];
            $oid_numlist[] = array('oid'=>$order_oid[$value['order_obj_id']],'nums'=>$value['number']);
        }
        $this->__sdf['oid_numlist'] = $oid_numlist;
        $this->__sdf['oid_list'] = array_unique(array_filter($oid_list));
        return ;
    }

    /**
     * 获取分销王格式的拆分数据
     *
     * @return void
     * @author
     **/
    final protected function _get_fxwfat_split_sdf($delivery_id)
    {
        $delivery = $this->__deliverys[$delivery_id];
        $order    = $this->__delivery_orders[$delivery_id];

        if ($delivery['type'] != 'normal' || !in_array($order['shop_type'], array('shopex_b2b','ecshop'))) return;

        // 开启了拆单
        $switch = $this->_is_split_switch($delivery_id);
        if ($switch == '0') return ;

        // 判断订单是否拆单
        $is_split = $this->_is_split_order($delivery_id);
        if (!$is_split) return ;


        $delivery_items_detail = $this->_get_delivery_items_detail($delivery_id);
        $order_objects = $this->_get_order_objects($delivery_id);

        $this->__sdf['is_split'] = 1;

        // 正常回写
        $delivery_items = array();
        foreach ($delivery_items_detail as $key => $value) {
            if ($order_objects[$value['order_obj_id']]['order_items'][$value['order_item_id']]['nums'] == $order_objects[$value['order_obj_id']]['order_items'][$value['order_item_id']]['sendnum'] || $order_objects[$value['order_obj_id']]['order_items'][$value['order_item_id']]['nums'] == $value['number']){

                if ($value['item_type'] == 'pkg') {
                    $number = $order_objects[$value['order_obj_id']]['quantity'];

                    $delivery_items['obj_'.$value['order_obj_id']] = array(
                        'name'          => trim($order_objects[$value['order_obj_id']]['name']),
                        'bn'            => trim($order_objects[$value['order_obj_id']]['bn']),
                        'number'        => $number,
                        'item_type'     => $value['item_type'],
                        'shop_goods_id' => $order_objects[$value['order_obj_id']]['shop_goods_id'],
                    );

                } else {
                    $order_item = $order_objects[$value['order_obj_id']]['order_items'][$value['order_item_id']];
                    $delivery_items['item_'.$value['order_item_id']] = array(
                        'name'          => trim($order_objects[$value['order_obj_id']]['name']),
                        'bn'            => trim($value['bn']),
                        'number'        => $order_objects[$value['order_obj_id']]['order_items'][$value['order_item_id']]['nums'],
                        'item_type'     => $value['item_type'],
                        'shop_goods_id' => $order_item['shop_goods_id'],
                        'promotion_id'  => $order_item['promotion_id'],
                    );
                }
            }
        }

        $this->__sdf['delivery_items'] = $delivery_items;

        return ;
    }


    /**
     * 是否开启拆单功能
     *
     * @return void
     * @author
     **/
    final protected function _is_split_switch($delivery_id)
    {

        if (app::get('ome')->getConf('ome.order.split') != '1') {
            return '0';
        }

        // 用发货单和订单购买数量匹配是否拆单
        $delivery_items_detail = $this->_get_delivery_items_detail($delivery_id);
        $order_objects = $this->_get_order_objects($delivery_id);

        $oid = true;
        foreach ($delivery_items_detail as $detail) {
            if ($detail['number'] != $order_objects[$detail['order_obj_id']]['order_items'][$detail['order_item_id']]['nums']) {
                $oid = false;break;
            }
        }

        return $oid===true ? '1' : '2';
    }

    /**
     * 判断是否拆单
     *
     * @return bool
     * @author
     **/
    final protected function _is_split_order($delivery_id)
    {
        $order = $this->__delivery_orders[$delivery_id];

        // 如果存在未发货订单
        if ($order['ship_status'] == '2' || $order['process_status'] == 'remain_cancel' || $order['process_status'] == 'splitting') {
            return true;
        }

        $order_delivery = $this->_get_order_delivery_ids($delivery_id);
        if (count($order_delivery) > 1) {
            return true;
        }

        return false;
    }

    /**
     * 获取订单的所有发货单ID
     *
     * @return array
     * @author
     **/
    final protected function _get_order_delivery_ids($delivery_id)
    {
        static $order_delivery;

        $order = $this->__delivery_orders[$delivery_id];

        if (isset($order_delivery[$order['order_id']])) return $order_delivery[$order['order_id']];

        // 订单的发货单数
        $sql = "SELECT d.delivery_id,d.status,d.delivery_time
                FROM sdb_ome_delivery_order AS do
                LEFT JOIN sdb_ome_delivery AS d
                ON(do.delivery_id=d.delivery_id)
                WHERE do.order_id='".$order['order_id']."' AND d.parent_id='0' AND d.disabled='false' AND d.status IN('succ','progress','ready')";
        $rows = kernel::database()->select($sql);

        $order_delivery[$order['order_id']] = array();

        if ($rows){
            foreach ($rows as $row) {
                $order_delivery[$order['order_id']][$row['delivery_id']] = $row;
            }
        }

        return $order_delivery[$order['order_id']];
    }

    protected function _get_order_deliverys_items_detail($delivery_id)
    {
        static $detail;

        if (isset($detail[$delivery_id])) return $detail[$delivery_id];

        $detail[$delivery_id] = array();

        $delivery_ids = array();
        foreach ((array) $this->_get_order_delivery_ids($delivery_id) as $value) {
            $delivery_ids[] = array_keys($value);
        }

        if ($delivery_ids) {
            $deliveryItemDetailModel = app::get('ome')->model('delivery_items_detail');
            $deliveryItemDetailList = $deliveryItemDetailModel->getList('*',array('delivery_id'=>$delivery_ids));

            foreach ($deliveryItemDetailList as $key => $value) {
                $detail[$value['delivery_id']][] = $value;
            }
        }

        return $detail[$delivery_id];
    }

    final protected function _get_corp($corp_id)
    {
        static $corps;

        if (isset($corps[$corp_id])) return $corps[$corp_id];

        $corpIds = array();
        foreach ($this->__deliverys as $delivery) {
            $corpIds[] = $delivery['logi_id'];

            $corps[$delivery['logi_id']] = array();
        }

        $corpModel = app::get('ome')->model('dly_corp');
        $rows = $corpModel->getList('corp_id,type,corp_type,name,crossborder_region_id,crossborder_res_id,corp_model',array('corp_id'=>$corpIds));
        foreach ($rows as $row) {
            $corps[$row['corp_id']] = $row;
        }

        return $corps[$corp_id] ? $corps[$corp_id] : array();
    }

    final protected function _get_order_extend($delivery_id)
    {
        static $order_extends;

        $order = $this->__delivery_orders[$delivery_id];

        if (isset($order_extends[$order['order_id']])) return $order_extends[$order['order_id']];

        $orderIds = array();
        foreach ($this->__delivery_orders as $key => $value) {
            $orderIds[] = $value['order_id'];

            $order_extends[$value['order_id']] = array();
        }

        $orderExtendModel = app::get('ome')->model('order_extend');
        $rows = $orderExtendModel->getList('*',array('order_id'=>$orderIds));
        foreach ($rows as $row) {
            $order_extends[$row['order_id']] = $row;
        }

        return $order_extends[$order['order_id']];
    }

    final protected function _get_order_objects($delivery_id)
    {
        static $order_objects;

        $order = $this->__delivery_orders[$delivery_id];

        if (isset($order_objects[$order['order_id']])) return $order_objects[$order['order_id']];

        $orderIds = array();
        foreach ($this->__delivery_orders as $key => $value) {
            $orderIds[] = $value['order_id'];

            $order_objects[$value['order_id']] = array();
        }

        $orderItemModel = app::get('ome')->model('order_items');
        $rows = $orderItemModel->getList('*',array('order_id'=>$orderIds,'delete'=>'false'));
        $order_items = array();
        foreach ($rows as $row) {
            $order_items[$row['obj_id']][$row['item_id']] = $row;
        }

        $orderObjModel = app::get('ome')->model('order_objects');
        $rows = $orderObjModel->getList('*',array('obj_id'=>array_keys($order_items)));
        foreach ($rows as $row) {
            $row['order_items'] = $order_items[$row['obj_id']];
            $order_objects[$row['order_id']][$row['obj_id']] = $row;
        }

        return $order_objects[$order['order_id']];
    }

    final protected function _order_is_all_virtual($delivery_id) {
        $order_objects = $this->_get_order_objects($delivery_id);
        $productId = [];
        foreach ($order_objects as $objId => $obj) {
            foreach ($obj['order_items'] as $itemId => $item) {
                $productId[$item['product_id']] = $item['product_id'];
            }
        }
        $bm = app::get('material')->model('basic_material')->getList('bm_id', ['bm_id'=>$productId,'type|noequal'=>'5']);
        if($bm) {
            return false;
        }
        return true;
    }
    
    final protected function _get_delivery_bill_detail($delivery_id)
    {
        static $delivery_bill_detail;
        
        if (isset($delivery_bill_detail[$delivery_id])) return $delivery_bill_detail[$delivery_id];
        
        $deliveryIds = array();
        foreach ($this->__deliverys as $delivery) {
            $deliveryIds[] = $delivery['delivery_id'];
            
            $delivery_bill_detail[$delivery['delivery_id']] = array();
        }
        
        $deliveryBillModel = app::get('ome')->model('delivery_bill');
        $deliveryBillList = $deliveryBillModel->getList('logi_no,delivery_id,courier_name,courier_phone,phone_type,privacy_num_validity_seconds',array('delivery_id'=>$deliveryIds));
        
        foreach ($deliveryBillList as $key => $value) {
            $delivery_bill_detail[$value['delivery_id']][] = $value;
        }
        
        return $delivery_bill_detail[$delivery_id];
    }

    final protected function _get_delivery_items_detail($delivery_id)
    {
        static $delivery_items_detail;

        if (isset($delivery_items_detail[$delivery_id])) return $delivery_items_detail[$delivery_id];

        $deliveryIds = array();
        foreach ($this->__deliverys as $delivery) {
            $deliveryIds[] = $delivery['delivery_id'];

            $delivery_items_detail[$delivery['delivery_id']] = array();
        }

        $deliveryItemDetailModel = app::get('ome')->model('delivery_items_detail');
        $deliveryItemDetailList = $deliveryItemDetailModel->getList('*',array('delivery_id'=>$deliveryIds));

        foreach ($deliveryItemDetailList as $key => $value) {
            $delivery_items_detail[$value['delivery_id']][] = $value;
        }

        return $delivery_items_detail[$delivery_id];
    }

    protected $__product_serial;
    final protected function _get_product_serial($delivery_id)
    {
        
        if (isset($this->__product_serial)) {
            return $this->__product_serial[$delivery_id] ? : [];
        }
        $this->__product_serial = [];
        //获取所有的父发货单ID
        $parentIdList = [];
        $deliveryList = [];
        foreach ($this->__deliverys as $delivery) {
            $parentIdList[$delivery['delivery_id']] = $delivery['parent_id'] == 0 ? $delivery['delivery_id'] : $delivery['parent_id'];
            $deliveryList[$delivery['delivery_id']] = $this->_get_delivery_items_detail($delivery['delivery_id']);
            
        }

        $dlyItemsSerialObj    = app::get('ome')->model('product_serial_history');
        $rows = $dlyItemsSerialObj->getList('bn,serial_number', array('bill_type' => '1','bill_id'=>$parentIdList), 0, -1);
        $product_serial = [];
        foreach ($rows as $row) {
            $product_serial[$row['bn']][$row['serial_number']] = $row['serial_number'];
        }
        foreach($deliveryList as $d){
            foreach($d as $v) {
                if($product_serial[$v['bn']]) {
                    $i = $v['number'];
                    if($i < 1) {
                        continue;
                    }
                    foreach($product_serial[$v['bn']] as $pk => $pv) {
                        unset($product_serial[$v['bn']][$pk]);
                        $this->__product_serial[$v['delivery_id']][$v['product_id']][] = $pv;
                        $i--;
                        if($i < 1) {
                            break;
                        }
                    }
                }
            }
        }

        return $this->__product_serial[$v['delivery_id']] ? : [];
    }

    protected $__product_serial_sn_imei;
    final protected function _get_product_serial_sn_imei($delivery_id)
    {
        
        if (isset($this->__product_serial_sn_imei)) {
            return $this->__product_serial_sn_imei[$delivery_id] ? : [];
        }
        $this->__product_serial_sn_imei = [];
        //获取所有的父发货单ID
        $parentIdList = [];
        $deliveryList = [];
        foreach ($this->__deliverys as $delivery) {
            $parentIdList[$delivery['delivery_id']] = $delivery['parent_id'] == 0 ? $delivery['delivery_id'] : $delivery['parent_id'];
            $deliveryList[$delivery['delivery_id']] = $this->_get_delivery_items_detail($delivery['delivery_id']);
            
        }

        $dlyItemsSerialObj    = app::get('ome')->model('product_serial_history');
        $rows = $dlyItemsSerialObj->getList('bn,serial_number,imei_number', array('bill_type' => '1','bill_id'=>$parentIdList), 0, -1);
        $product_serial = [];
        foreach ($rows as $row) {
            $product_serial[$row['bn']]['sn'][$row['serial_number']] = $row['serial_number'];
            $product_serial[$row['bn']]['imei'][$row['serial_number']] = $row['imei_number'];
        }
        foreach($deliveryList as $d){
            foreach($d as $v) {
                if($product_serial[$v['bn']]) {
                    $i = $v['number'];
                    if($i < 1) {
                        continue;
                    }
                    foreach($product_serial[$v['bn']]['sn'] as $pk => $pv) {
                        unset($product_serial[$v['bn']]['sn'][$pk]);
                        $this->__product_serial_sn_imei[$v['delivery_id']][$v['product_id']]['sn'][] = $pv;
                        $this->__product_serial_sn_imei[$v['delivery_id']][$v['product_id']]['imei'][$pk] = $product_serial[$v['bn']]['imei'][$pk];
                        unset($product_serial[$v['bn']]['imei'][$pk]);
                        $i--;
                        if($i < 1) {
                            break;
                        }
                    }
                }
            }
        }

        return $this->__product_serial_sn_imei[$v['delivery_id']] ? : [];
    }

    final protected function _get_product_serial_sn_imei_sdf($delivery_id)
    {
        $serial = $this->_get_product_serial_sn_imei($delivery_id);

        if (!$serial) return;

        $feature = array();

        $delivery = $this->__deliverys[$delivery_id];
        if ($delivery['parent_id'] > 0) {
            // 获取父发货单对应的所有订单
            $deliveryItemDetailModel = app::get('ome')->model('delivery_items_detail');
            $deliveryItemDetailList = $deliveryItemDetailModel->getList('*',array('delivery_id'=>$delivery['parent_id']));

            $product_serial = array();
            foreach ($deliveryItemDetailList as $key => $value) {
                if (!$serial[$value['product_id']]) continue;

                $product_serial[$value['order_item_id']]['sn'] = array_splice($serial[$value['product_id']]['sn'], 0, $value['number']);
                $product_serial[$value['order_item_id']]['imei'] = array_splice($serial[$value['product_id']]['imei'], 0, $value['number']);
            }


        } else {
            $deliveryItemDetailList = $this->_get_delivery_items_detail($delivery_id);
            $product_serial = array();
            foreach ($deliveryItemDetailList as $key => $value) {
                if (!$serial[$value['product_id']]) continue;

                $product_serial[$value['order_item_id']]['sn'] = array_splice($serial[$value['product_id']]['sn'], 0, $value['number']);
                $product_serial[$value['order_item_id']]['imei'] = array_splice($serial[$value['product_id']]['imei'], 0, $value['number']);

            }
        }

        $order_objects = $this->_get_order_objects($delivery_id);
        foreach ($order_objects as $object) {
            foreach ($object['order_items'] as $item) {
                if ($product_serial[$item['item_id']] && $object['oid']){
                    foreach ($product_serial[$item['item_id']]['sn'] as $_sk => $_sn) {
                        $_imei = $product_serial[$item['item_id']]['imei'][$_sk] ?? '';
                        $feature[] = $object['oid'].':'.$_sn.':'.$_imei;
                    }
                }
            }
        }

        if ($feature) {
            if ($this->__sdf['feature']) {
                $this->__sdf['feature'] .= ';sn='.implode('|',$feature);
            } else {
                $this->__sdf['feature'] = 'sn='.implode('|',$feature);
            }
        }
    }

    final protected function _get_members($delivery_id)
    {
        static $members;

        $order = $this->__delivery_orders[$delivery_id];

        if (isset($members[$order['member_id']])) return $members[$order['member_id']];

        $memberIds = array();
        foreach ($this->__delivery_orders as $key => $value) {
            $memberIds[] = $value['member_id'];

            $members[$value['member_id']] = array();
        }

        if (!$members) return array();

        $memberModel = app::get('ome')->model('members');
        $rows = $memberModel->getList('*',array('member_id'=>$memberIds));
        foreach ($rows as $row) {
            $members[$row['member_id']] = $row;
        }

        return $members[$order['member_id']];
    }

    final protected function _get_branch($branch_id)
    {
      static $branches;

      if (isset($branches[$branch_id])) return $branches[$branch_id];

      $branchIds = array();
      foreach ($this->__deliverys as $delivery) {
          $branchIds[] = $delivery['branch_id'];

          $branches[$delivery['branch_id']] = array();
      }

      $branchModel = app::get('ome')->model('branch');
      $rows = $branchModel->getList('branch_id,branch_bn,wms_id,area,address,mobile,uname,latitude,longitude',array('branch_id'=>$branchIds));
      foreach ($rows as $row) {
          $branches[$row['branch_id']] = $row;
      }

      return $branches[$branch_id] ? $branches[$branch_id] : array();
    }

    final protected function _get_delivery_items_detail_order($delivery_id, $check_ship_status=true)
    {
        static $delivery_items_detail_order;
        $order = $this->__delivery_orders[$delivery_id];
        if($order['ship_status'] != 1 && $check_ship_status) {
            return array();
        }
        if (isset($delivery_items_detail_order[$order['order_id']])) return $delivery_items_detail_order[$order['order_id']];

        $deliveryItemDetailModel = app::get('ome')->model('delivery_items_detail');
        $deliveryItemDetailList = $deliveryItemDetailModel->getList('*',array('order_id'=>$order['order_id']));
        $arrDlyId = array();
        foreach ($deliveryItemDetailList as $key => $value) {
            $arrDlyId[] = $value['delivery_id'];
        }
        $deliveryModel = app::get('ome')->model('delivery');
        $deliveryList = $deliveryModel->getList('delivery_id,logi_no,delivery_time,logi_id',array('delivery_id'=>$arrDlyId,'process'=>'true','parent_id'=>'0'));
        $arrDelivery  = array ();
        $lastDelivery = array ();
        $corp_id      = array ('0');
        foreach ($deliveryList as $value) {
            if($lastDelivery) {
                if(($lastDelivery['delivery_time'] == $value['delivery_time']
                        && $lastDelivery['delivery_id'] < $value['delivery_id'])
                    || $lastDelivery['delivery_time'] < $value['delivery_time']) {
                    $lastDelivery = $value;
                }
            } else {
                $lastDelivery = $value;
            }

            $corp_id[] = $value['logi_id'];

            $arrDelivery[$value['delivery_id']] = $value;
            $arrDelivery[$value['delivery_id']]['logi_type'] = &$corps[$value['logi_id']];
            $arrDelivery[$value['delivery_id']]['logi_name'] = &$logi_name[$value['logi_id']];
        }

        $corpMdl = app::get('ome')->model('dly_corp');
        foreach ($corpMdl->getList('corp_id,type,name', array ('corp_id' => $corp_id)) as $value) {
          $corps[$value['corp_id']] = $value['type'];
          $logi_name[$value['corp_id']] = $value['name'];
        }


        $this->__sdf['logi_no'] = $lastDelivery['logi_no'];
        foreach ($deliveryItemDetailList as $key => $value) {
            if($arrDelivery[$value['delivery_id']]) {
                $value['logi_no']   = $arrDelivery[$value['delivery_id']]['logi_no'];
                $value['logi_type'] = $arrDelivery[$value['delivery_id']]['logi_type'];
                $value['logi_name'] = $arrDelivery[$value['delivery_id']]['logi_name'];
                $delivery_items_detail_order[$value['order_id']][] = $value;
            }
        }

        return $delivery_items_detail_order[$order['order_id']];
    }
    
    /**
     * [兼容]订单全部发货后&&是被编辑过,删除了平台上的oid前端平台商品
     * @todo：[场景一]编辑订单删除所有平台oid商品后,又添加了新商品;这时回写给平台失败,因为没有oid商品明细;
     * @todo：[场景二]编辑订单删除部分平台oid商品后,发货回写给平台,只回写了部分oid商品,导致平台显示"部分发货";
     * @todo：现仅针对订单全部发货后,并且是阿里巴巴平台有oid商品;
     */
    public function _compatible_order_sync()
    {
        if(empty($this->__sdf) || empty($this->__sdf['delivery_items'])){
            return false;
        }
        
        $orderObj = app::get('ome')->model('orders');
        
        //订单信息
        $order_id = intval($this->__sdf['orderinfo']['order_id']);
        $orderInfo = $orderObj->dump(array('order_id'=>$order_id), 'order_bn,process_status,status,pay_status,ship_status,is_modify');
        if(empty($orderInfo)){
            return false;
        }
        
        //check
        if($orderInfo['is_modify'] != 'true'){
            return false; //订单没有被编辑,跳过
        }
        
        if($orderInfo['ship_status'] != '1'){
            return false; //订单不是[全部发货]状态,跳过
        }
        
        if($orderInfo['pay_status'] != '1'){
            return false; //订单不是[已支付]状态,跳过
        }
        
        //oid列表
        $oidList = array_column($this->__sdf['delivery_items'], 'oid');
        
        //增加被删除的oid商品
        foreach ($this->__sdf['orderinfo']['order_objects'] as $obj_id => $objInfo)
        {
            $is_del = false;
            foreach ($objInfo['order_items'] as $item_id => $itemInfo)
            {
                $is_del = ($itemInfo['delete'] == 'true' ? true : false);
            }
            
            if(!$is_del){
                continue; //不是删除状态,跳过
            }
            
            if(empty($objInfo['oid'])){
                continue; //不是oid商品,跳过
            }
            
            //子订单已经存在,则跳过
            if(in_array($objInfo['oid'], $oidList)){
                continue;
            }
            
            //nums
            $nums = ($objInfo['quantity'] ? $objInfo['quantity'] : $objInfo['nums']);
            
            $this->__sdf['delivery_items'][$obj_id] = array(
                    'name' => trim($objInfo['name']),
                    'bn' => trim($objInfo['bn']),
                    'number' => intval($nums),
                    'item_type' => $objInfo['obj_type'],
                    'shop_goods_id' => $objInfo['shop_goods_id'],
                    'oid' => $objInfo['oid'],
            );
        }
        
        return true;
    }
    
    /**
     * [兼容]阿里巴巴不支持按数量拆单回写
     */
    public function _format_confirm_oid()
    {
        if(empty($this->__sdf['delivery_items'])){
            return false;
        }
        
        //发货明细
        $is_flag = false;
        foreach ($this->__sdf['delivery_items'] as $key => $val)
        {
            $val['nums'] = intval($val['nums']);
            
            //[按数量拆分]发货数量与购物数量不一致
            if($val['number'] != $val['nums']){
                $is_flag = true;
                break;
            }
        }
        
        //不需要格式化,直接退出
        if(!$is_flag){
            return false;
        }
        
        $orderObj = app::get('ome')->model('orders');
        
        //需要格式化oid数量
        foreach ($this->__sdf['delivery_items'] as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $order_obj_id = $val['order_obj_id'];
            $product_bn = $val['product_bn'];
            
            $val['nums'] = intval($val['nums']);
            
            if($val['number'] != $val['nums']){
                //回写全部数量
                $sql = "SELECT a.delivery_id FROM sdb_ome_delivery_items_detail AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
                $sql .= " WHERE a.order_obj_id=". $order_obj_id ." AND a.bn='". $product_bn ."' AND b.status='succ' AND a.delivery_id != ". $delivery_id;
                
                $tempData = $orderObj->db->selectrow($sql);
                if($tempData){
                    //已经有发货成功记录,删除此oid明细
                    unset($this->__sdf['delivery_items'][$key]);
                }else{
                    //第一次发货时,回写全部数量
                    $val['number'] = ($val['nums'] ? $val['nums'] : $val['number']);
                    
                    $this->__sdf['delivery_items'][$key] = $val;
                }
            }
        }
        
        return true;
    }

    /**
     * 获取发货单包裹明细
     * @param  String $delivery_id 发货单ID 有子单时为子发货单id，否则为主发货单id
     * @return array|mixed
     */
    protected $__package = [];
    
    final protected function _get_delivery_package($delivery_id)
    {
        $data = $this->__package;
        
        if (isset($data[$delivery_id])) {
            return $data[$delivery_id] ? : [];
        }
        
        //获取所有的父发货单ID
        $parentIdList = [];
        $deliveryList = [];
        foreach ($this->__deliverys as $delivery) {
            $parentIdList[$delivery['delivery_id']] = $delivery['parent_id'] == 0 ? $delivery['delivery_id'] : $delivery['parent_id'];
            $deliveryList[$delivery['delivery_id']] = $this->_get_delivery_items_detail($delivery['delivery_id']);
            
        }
        
        //查所有的包裹信息
        $packages = app::get('ome')->model('delivery_package')->getList('*', array(
            'delivery_id' => $parentIdList
        ));
      
        $newPackages = [];
        foreach ($packages as $k => $v) {
            $v['number']                                        = (int)$v['number'];
            $newPackages[$v['delivery_id']][$v['product_id']][] = $v;
        }
        foreach ($deliveryList as $key => $items) {
            foreach ($items as $k_i => $val) {
                $p_id     = $parentIdList[$val['delivery_id']];
                $leaveNum = $val['number'];
                foreach ($newPackages[$p_id][$val['product_id']] as $k => $package) {
                    if ($package['number'] == 0) {
                        continue;
                    }
                    $minNum        = min($leaveNum, $package['number']);
                    $pkg           = $package;
                    $pkg['number'] = $minNum;
                
                    $this->__package[$val['delivery_id']][] = $pkg;
                
                    $newPackages[$p_id][$val['product_id']][$k]['number'] -= $minNum;
                    if ($newPackages[$p_id][$val['product_id']][$k]['number'] <= 0) {
                        unset($newPackages[$p_id][$val['product_id']][$k]);
                    }
                
                    $leaveNum -= $minNum;
                    if ($leaveNum <= 0) {
                        continue 2;
                    }
                }
            }
        }
        
        return $this->__package[$delivery_id] ? : [];
        
    }
    
    /**
     * 过滤已经退款的oid子订单(并且按oid子订单方式回写平台)，并且会过滤OMS赠送的赠品
     * @todo：抖音平台售前退款商品,只能使用oid拆单方式回写发货,否则平台会返回报错.
     *
     * @param $delivery_id
     * @return void
     */
    final protected function _refund_split_sdf($delivery_id)
    {
        $orderObjMdl = app::get('ome')->model('order_objects');
        $refundApplyObj = app::get('ome')->model('refund_apply');
        
        //orderInfo
        $orderInfo = $this->__delivery_orders[$delivery_id];
        $order_id = $orderInfo['order_id'];
        
        //获取退款列表(包含：售前退款、售后退款)
        $refundList = $refundApplyObj->getList('apply_id,refund_apply_bn,money,refunded,oid,bn', array('order_id'=>$order_id, 'status'=>'4'));
        if(empty($refundList)){
            return false;
        }
        
        //按SKU商品累加退款金额
        $refundOidList = array();
        foreach ($refundList as $key => $val)
        {
            $oid = $val['oid'];
            
            //check
            if(empty($oid)){
                continue;
            }
            
            //PKG组合商品退款时：会保存多个oid以逗号分隔
            $tempOids = explode(',', $oid);
            
            //取第一个oid
            $oid = current($tempOids);
            
            //merge
            if($refundOidList[$oid]){
                $refundOidList[$oid] += $val['refunded'];
            }else{
                $refundOidList[$oid] = $val['refunded'];
            }
        }
        
        //获取删除的平台商品
        $deleteOidList = array();
        $objectList = $orderObjMdl->getList('obj_id,obj_type,bn,oid,divide_order_fee,pay_status,`delete`,main_oid', array('order_id'=>$order_id));
        foreach ($objectList as $key => $val)
        {
            $oid = $val['oid'];
            
            //check
            if(empty($oid)){
                continue;
            }
            
            //已删除的商品
            //@todo：只过滤退款的商品,不用过滤已删除的商品;如果是手工编辑替换过的平台商品,后面会有方法再加上;
//            if($val['delete'] == 'true'){
//                $deleteOidList[$oid] = $val['bn'];
//                continue;
//            }
            
            //已退款的商品
            if(in_array($val['pay_status'], array('4','5'))){
                $deleteOidList[$oid] = $val['bn'];
                continue;
            }
            
            // main_oid
            $main_oid = $val['main_oid'];
            if($main_oid){
                $main_oid = explode(',',$main_oid);

                if(array_intersect($main_oid,array_keys($refundOidList))){
                    $deleteOidList[$oid] = $val['bn'];
                }
            }
            //退款商品金额大于等于商品支持的金额
            if($refundOidList[$oid] && $refundOidList[$oid] >= $val['divide_order_fee']){
                $deleteOidList[$oid] = $val['bn'];
                continue;
            }
        }
        
        //check没有需要过滤回传的oid子订单
        if(empty($deleteOidList)){
            return false;
        }
        
        //check
        if(empty($this->__sdf['delivery_items'])){
            //设置为空值,则不会同步给平台
            $this->__sdf = array();
            
            return false;
        }
        
        //[拆单]按oid子订单回传标记
        $this->__sdf['is_split'] = 1;
        
        //items
        foreach ($this->__sdf['delivery_items'] as $itemKey => $itemVal)
        {
            $oid = $itemVal['oid'];
            
            //check
            if(empty($oid)){
                // [过滤没有oid的商品]删除OMS系统添加的商品(赠品、普通商品)
                unset($this->__sdf['delivery_items'][$itemKey]);
                
                continue;
            }
            
            //删除已经退款的oid子订单
            if($deleteOidList[$oid]){
                unset($this->__sdf['delivery_items'][$itemKey]);
            }
        }
        
        //是否禁止同步平台状态
        if(empty($this->__sdf['delivery_items'])){
            //$this->__sdf['forbid_sync'] = 'true';
            
            //设置为空值,则不会同步给平台
            $this->__sdf = array();
        }
        
        return true;
    }

    final protected function _get_bill_label($delivery_id)
    {
        static $bill_labels;

        if (isset($bill_labels[$delivery_id])) return $bill_labels[$delivery_id];

        $orderIds = array();
        foreach ($this->__delivery_orders as $key => $value) {
            $orderIds[] = $value['order_id'];
            $bill_labels[$value['order_id']] = array();
        }

        $billLabelModel = app::get('ome')->model('bill_label');
        $rows = $billLabelModel->getList('*', array('bill_type' => 'order', 'bill_id' => $orderIds));
        
        foreach ($rows as $row) {
            $row['extend_info'] = $row['extend_info'] ? @json_decode($row['extend_info'], true) : [];
            $bill_labels[$row['bill_id']][] = $row;
        }

        $order = $this->__delivery_orders[$delivery_id];
        return $bill_labels[$order['order_id']] ? $bill_labels[$order['order_id']] : array();
    }
}
