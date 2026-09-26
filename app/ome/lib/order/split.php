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
 * 订单拆单处理逻辑Lib类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: split.php 2015-12-10 15:00
 */
class ome_order_split
{
    /**
     * [拆单配置]获取拆单后回写发货单方式
     * 注：电子面单线 与 协同版获取拆单配置不一样；
     * 
     * @return array
     */

    public function get_delivery_seting()
    {
        $split = app::get('ome')->getConf('ome.order.split');
        $split_type = app::get('ome')->getConf('ome.order.split_type');
        $split_type = intval($split_type);
        
        if(empty($split) || empty($split_type))
        {
            return '';
        }
        
        //回写方式
        $split_seting    = array('split_model'=>$split_type);
        
        return $split_seting;
    }
    
    /**
     * * 已弃用!!!
     * 
     * 判断"拆单方式"配置是否变更
     * 1.未开启拆单则返回空
     * 2.已开启拆单，并且订单对应的拆单方式与上次不一样，则会返回上次拆单方式
     * 
     * @param intval $order_id
     * @return array
     */
    public function get_split_setup_change($order_id)
    {
        $sql    = "SELECT syn_id, sync, split_model, split_type FROM sdb_ome_delivery_sync WHERE order_id = '".intval($order_id)."' AND sync='succ' ORDER BY dateline DESC";
        $row    = kernel::database()->selectrow($sql);
        
        if(empty($row) || $row['split_model'] == 0)
        {
            return '';//上次未开启拆单或无发货记录
        }
        
        #拆单配置
        $split_seting    = $this->get_delivery_seting();
        
        if($row['split_model'] != $split_seting['split_model'])
        {
            $split_seting['old_split_model']    = $row['split_model'];
            
            return $split_seting;
        }
        
        return '';
    }
    
    /**
     * 根据发货单统计订单商品重量
     * 
     * @param   Intval $order_id
     * @param   Array  $order_items
     * @return  Number
     */
    public function getDeliveryWeight($order_id, $order_items = array(), $delivery_id = 0)
    {
        $orderItemObj  = app::get('ome')->model('order_items');
        $objectsObj    = app::get('ome')->model('order_objects');

        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');

        $weight        = 0;
        
        if(empty($order_items) && !empty($delivery_id)) 
        {
            $didObj = app::get('ome')->model('delivery_items_detail');
            $dly_itemlist   = $didObj->getList('delivery_id, order_item_id, product_id, number', array('delivery_id'=>$delivery_id, 'order_id'=>$order_id));
            foreach ($dly_itemlist as $key => $val)
            {
                $order_items[$key]  = array('item_id'=>$val['order_item_id'], 'product_id'=>$val['product_id'], 'number'=>$val['number']);
            }
            unset($dly_itemlist);
        }
        elseif(empty($order_items))
        {
            $orderObj    = app::get('ome')->model('orders');
            
            $weight   = $orderObj->getOrderWeight($order_id);
            return $weight;
        }
        
        //[部分拆分]订单计算本次发货商品重量
        $item_list   = $item_ids = array();
        foreach ($order_items as $key => $val) 
        {
            $item_id     = $val['item_id'];
            $product_id  = $val['product_id'];
            
            $item_list[$item_id]    = $val;            
            $item_ids[]             = $item_id;
        }
        
        #获取本次发货单关联的订单明细
        $obj_list = array();
        $flag     = true;
        
        $filter     = array('item_id'=>$item_ids, '`delete`'=>'false');        
        $item_data  = $orderItemObj->getList('item_id, obj_id, product_id, bn, item_type, nums', $filter);
        foreach ($item_data as $key => $val) 
        {
            $item_type   = $val['item_type'];
            $item_id     = $val['item_id'];
            $obj_id      = $val['obj_id'];
            $product_id  = $val['product_id'];
            $bn          = $val['bn'];
            
            $val['send_num']   = $item_list[$item_id]['number'];//发货数量
            
            if($item_type == 'pkg') 
            {
                $obj_list[$obj_id]['items'][$item_id]  = $val;
                
                //[捆绑商品]货号bn
                if(empty($obj_list[$obj_id]['bn'])) 
                {
                    $obj_item     = $objectsObj->getList('obj_id,goods_id, bn', array('obj_id'=>$obj_id), 0, 1);
                    $obj_list[$obj_id]['bn']  = $obj_item[0]['bn'];
                    
                    //[捆绑商品]重量
                    $pkg_goods    = $salesMaterialExtObj->dump(array('sm_id'=>$obj_item[0]['goods_id']),'sm_id, weight');
                    $obj_list[$obj_id]['net_weight']  = floatval($pkg_goods['weight']);
                    
                    //[捆绑商品]发货数量
                    $pkg_product   = $salesBasicMaterialObj->dump(array('sm_id'=>$pkg_goods['sm_id'], 'bm_id'=>$product_id), 'number');
                    
                    // 兼容订单明细：PKG捆绑商品被批量替换成促销销售物料不包含的基础物料
                    if(empty($pkg_product)){
                        $obj_list[$obj_id]['send_num'] = intval($val['send_num']);
                    }else{
                        $obj_list[$obj_id]['send_num']    = intval($val['send_num'] / $pkg_product['number']);
                    }
                    
                    $obj_list[$obj_id]['weight']  = 0;
                    if($obj_list[$obj_id]['net_weight'] > 0)
                    {
                        $obj_list[$obj_id]['weight']     = ($obj_list[$obj_id]['net_weight'] * $obj_list[$obj_id]['send_num']);
                    }
                }
            }
            else 
            {
                //普通商品直接计算重量
                $products = $basicMaterialExtObj->dump(array('bm_id'=>$product_id),'weight');
                if($products['weight'] >= 0)
                {//不走下面的else 其他商品下的基础物料 有无重量都加
                  $weight += ($products['weight'] * $val['send_num']);
                }
                else 
                {
                    $weight    = 0;//有一个商品重量为0,就返回
                    $flag      = false;
                    break;
                }
            }
        }
        
        #捆绑商品无重量的重新计算
        if(!empty($obj_list) && $flag)
        {
            foreach ($obj_list as $obj_id => $obj_item) 
            {
                if($obj_item['weight'] > 0) 
                {
                    $weight += $obj_item['weight'];
                }
                else 
                {
                    foreach ($obj_item['items'] as $item_id => $item)
                    {
                        $products = $basicMaterialExtObj->dump(array('bm_id'=>$item['product_id']),'weight');
                        if($products['weight'] >= 0)
                        {//不走下面的else 促销商品下的基础物料 有无重量都加
                            $weight += ($products['weight'] * $item['send_num']);
                        }
                        else 
                        {
                            $weight    = 0;
                            break 2;
                        }
                    }
                }
            }
        }
        
        return $weight;
    }
    
    /**
     * 判断订单是否进行了拆单操作
     * 
     * @param Number $delivery_id 发货单id
     * @return Boolean
     */
    public function check_order_split($delivery_id)
    {
        $deliveryObj    = app::get('ome')->model('delivery');
        
        #获取订单order_id
        $order_ids     = $deliveryObj->getOrderIdByDeliveryId($delivery_id);
        foreach ($order_ids as $key => $val)
        {
            $order_id    = $val;
        }
        
        #获取关联的发货单id
        $temp_ids       = $deliveryObj->getDeliverIdByOrderId($order_id);
        
        #获取订单是否有未生成的发货单的商品
        $sql   = "SELECT item_id FROM sdb_ome_order_items WHERE order_id = '".$order_id."' AND nums != sendnum AND `delete` = 'false'";
        $row   = kernel::database()->selectrow($sql);
        
        if(count($temp_ids) > 1 || !empty($row))
        {
            return true;
        }
        
        return false;
    }
    
    /**
     * 已弃用!!!
     * 
     * 获取关联的成功发货或未发货的发货单
     * 
     * @param   Number    $delivery_id  发货单id
     * @param   Flag      $status       all全部、true已发货、false未发货
     * @return array
     */
    public function get_delivery_process($delivery_id, $status='all')
    {
        $deliveryObj    = app::get('ome')->model('delivery');
        
        $result     = array();
        $order_id   = 0;
        
        #根据发货单delivery_id获取对应的订单order_id
        $order_ids     = $deliveryObj->getOrderIdByDeliveryId($delivery_id);
        foreach ($order_ids as $key => $val)
        {
            $order_id    = $val;
        }
        
        #对比上一次发货操作时的"拆单方式"配置是否变更
        $change_split   = $this->get_split_setup_change($order_id);
        if(!empty($change_split))
        {
            return '';//配置变更，直接回写
        }
        
        #根据订单order_id获取对应的所有发货单delivery_id
        $temp_ids       = $deliveryObj->getDeliverIdByOrderId($order_id);
        if(!empty($temp_ids))
        {
            $delivery_ids     = array();
            foreach ($temp_ids as $key => $val)
            {
                //去除本次发货的delivery_id发货单
                if($val == $delivery_id)
                {
                    continue;
                }
                
                $delivery_ids[]  = $val;
            }
            
            if(!empty($delivery_ids))
            {
                $cols       = 'delivery_id, delivery_bn, is_cod, logi_id, logi_no, status, branch_id, 
                                 stock_status, deliv_status, expre_status, verify, process, type';
                
                $filter     = array('delivery_id'=>$delivery_ids);
                if($status == 'true')
                {
                    $filter['process'] = 'true';//只查询已发货的发货单
                }
                elseif($status == 'false')
                {
                    $filter['process'] = 'false';//只查询未发货的发货单
                }
                
                $result['delivery']     = $deliveryObj->getList($cols, $filter, 0, -1);
            }
        }
        
        #获取订单商品明细表中未全部发货的记录
        if($status == 'false')
        {
            $sql   = "SELECT item_id, order_id, nums, sendnum FROM sdb_ome_order_items WHERE order_id = '".$order_id."' AND nums != sendnum AND `delete` = 'false'";
            $row   = kernel::database()->selectrow($sql);
            $result['order_items'] = $row;
        }
        
        return $result;
    }
    
    /**
     * 保存_淘宝平台_的原始属性值[bn、oid、quantity]
     * 
     * @param $sdf       订单数据
     * @return Boolean
     */
    public function hold_order_delivery($sdf)
    {
        $data                = array();
        $data['order_bn']    = $sdf['order_bn'];
        
        #现只保存_淘宝平台
        if($sdf['shop_type'] != 'taobao' || empty($sdf['order_objects']))
        {
            return false;
        }
        
        foreach ($sdf['order_objects'] as $key => $obj_val)
        {
            if(empty($obj_val['order_items']))
            {
                continue;
            }
            
            foreach ($obj_val['order_items'] as $key_j => $item)
            {
                $data['oid'][]   = $obj_val['oid'];
                $data['bn'][]              = $item['bn'];
                $data['quantity'][]        = $item['quantity'];
            }
        }
        
        if(empty($data['oid']))
        {
            return false;
        }
        
        $save_data   = array();
        $save_data['order_bn']   = $data['order_bn'];
        $save_data['oid']            = implode(',', $data['oid']);
        $save_data['quantity']       = implode(',', $data['quantity']);
        
        $save_data['bn']         = serialize($data['bn']);//序列化存储防止有,逗号
        $save_data['dateline']   = time();
        
        $mdl_orddly  = app::get('ome')->model('order_delivery');
        $mdl_orddly->save($save_data);
        
        return true;
    }
    
    /**
     * 判断订单生成的发货单是否已全部发货
     * 
     * @param   String      $order_id        订单号ID
     * @param   String      $delivery_id     发货单ID
     * @param   bool        $is_create_sales 生成销售单单独判断 
     * @return  boolean
     */
    public function check_order_all_delivery($order_id, $delivery_id, $is_create_sales=false)
    {
        #订单"部分拆分"不生成销售单
        $orderObj    = app::get('ome')->model('orders');
        $row         = $orderObj->dump(array('order_id'=>$order_id), 'process_status');
        
        if($row['process_status'] == 'splitting')
        {
            return true;
        }
        
        #判断——订单所属发货单是否全部发货 process!='true'
        $sql    = "SELECT dord.delivery_id, d.delivery_bn, d.process, d.status FROM sdb_ome_delivery_order AS dord
                        LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                        WHERE dord.order_id='".$order_id."' AND d.delivery_id!='".$delivery_id."' AND d.process!='true'
                        AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false'";
        
        #生成销售单时,去除return_back追加发货单状态
        if($is_create_sales)
        {
            $sql    .= " AND d.status NOT IN('failed','cancel','back')";
        }
        else 
        {
            $sql    .= " AND d.status NOT IN('failed','cancel','back','return_back')";
        }
        
        $row    = kernel::database()->selectrow($sql);
        if(!empty($row))
        {
            return true;
        }
        
        return false;
    }
    
    /**
     * 余单撤消后_生成销售单[已弃用]
     * 
     * @param   Array     $data     订单号ID
     * @param   Intval    $io       默认0出库
     * @return  boolean
     */
    public function add_to_sales($data, $io=0, $type=null)
    {
        $allow_commit       = false;
        $iostock_instance   = kernel::service('ome.iostock');
        $sales_instance     = kernel::service('ome.sales');
        
        if (method_exists($iostock_instance, 'set') == false){
            return false;
        }
        
        //存储出入库记录
        $iostock_data   = $data['iostock'];
        if(!$type){
             eval('$type='.get_class($iostock_instance).'::LIBRARY_SOLD;');
        }
        
        $iostock_bn     = $iostock_instance->get_iostock_bn($type);
    
        $sales_msg = '';
        if ( method_exists($sales_instance, 'set') ){
            if ($data['sales']['sales_items']){
                $get_order_id       = intval($data['sales']['order_id']);
                $get_delivery_id    = intval($data['sales']['delivery_id']);
                
                //[拆单]获取订单对应所有iostock出入库单
                $order_delivery_iostock_data    = $this->get_delivery_iostock_data($iostock_data);
                
                #多个发货单累加物流成本
                $delivery_cost_actual           = $this->count_delivery_cost_actual($get_order_id);
                if($delivery_cost_actual){
                    $sales_data['delivery_cost_actual']  = $delivery_cost_actual;
                }
                
                //存储销售记录
                $branch_id = '';
                if ($data['sales']['sales_items']){
                    foreach ($data['sales']['sales_items'] as $k=>$v)
                    {
                        //[拆单]多个发货单时_iostock_id为NULL重新获取
                        if(!empty($iostock_data[$v['item_detail_id']]['iostock_id'])){
                            $v['iostock_id'] = $iostock_data[$v['item_detail_id']]['iostock_id'];
                        }else{
                            $v['iostock_id']   = $order_delivery_iostock_data[$v['item_detail_id']]['iostock_id'];
                        }
                        
                        $data['sales']['sales_items'][$k] = $v;
                    }
                }
                $data['sales']['iostock_bn'] = $iostock_bn;
                $sales_data = $data['sales'];
                $sale_bn = $sales_instance->get_salse_bn();
                $sales_data['sale_bn'] = $sale_bn;
                if ( $sales_instance->set($sales_data, $sales_msg) ){
                    $allow_commit = true;
                }
            }else{
                foreach($data['sales'] as $k=>$v)
                {
                    $get_order_id       = intval($v['order_id']);
                    $get_delivery_id    = intval($v['delivery_id']);
                    
                    //获取订单对应所有iostock出入库单
                    $order_delivery_iostock_data    = $this->get_delivery_iostock_data($iostock_data);
                    
                    //多个发货单累加物流成本
                    $delivery_cost_actual           = $this->count_delivery_cost_actual($get_order_id);
                    if($delivery_cost_actual)
                    {
                        $data['sales'][$k]['delivery_cost_actual']  = $delivery_cost_actual;
                    }
                    
                    //存储销售记录
                    $branch_id = '';
                    if ($data['sales'][$k]['sales_items']){
                        foreach ($data['sales'][$k]['sales_items'] as $kk=>$vv)
                        {
                            //[拆单]多个发货单时_iostock_id为NULL重新获取
                            if(!empty($iostock_data[$vv['item_detail_id']]['iostock_id'])){
                                $vv['iostock_id']   = $iostock_data[$vv['item_detail_id']]['iostock_id'];
                            }else {
                                $vv['iostock_id']   = $order_delivery_iostock_data[$vv['item_detail_id']]['iostock_id'];
                            }
                            
                            $data['sales'][$k]['sales_items'][$kk] = $vv;
                        }
                    }
                    $data['sales'][$k]['iostock_bn'] = $iostock_bn;
                    $sale_bn = $sales_instance->get_salse_bn();
                    $data['sales'][$k]['sale_bn'] = $sale_bn;
                    $data['sales'][$k]['operator'] = $data['sales'][$k]['operator'] ? $data['sales'][$k]['operator'] : 'system';
                    if ( $sales_instance->set($data['sales'][$k], $sales_msg) ){
                        $allow_commit = true;
                    }
                }
            }
            
            //更新销售单上的成本单价和成本金额等字段
            kernel::single('tgstockcost_instance_router')->set_sales_iostock_cost($io,$iostock_data);
        }
        
        return $allow_commit;
    }
    
    /**
     * 获取订单对应所有iostock出入库单
     * 
     * @param   Array   $iostock_data     出入库单
     * @param   bool    $is_create_sales 生成销售单单独判断
     * @return  Array
     */
    public function get_delivery_iostock_data($iostock_data, $is_create_sales=false)
    {
        $order_ids  = $delivery_ids = array();
        foreach ($iostock_data as $key => $val)
        {
            $order_ids[$val['order_id']]    = $val['order_id'];
        }
        $in_order_id    = implode(',', $order_ids);
        
        #获取订单对应所有发货单delivery_id
        $sql            = "SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            WHERE dord.order_id in(".$in_order_id.") AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false'";
        
        #生成销售单时,去除return_back追加发货单状态
        if($is_create_sales)
        {
            $sql    .= " AND d.status NOT IN('failed','cancel','back')";
        }
        else 
        {
            $sql    .= " AND d.status NOT IN('failed','cancel','back','return_back')";
        }
        
        $temp_data      = kernel::database()->select($sql);
        foreach ($temp_data as $key => $val)
        {
            $delivery_ids[]     = $val['delivery_id'];
        }
        
        #读取出库记录
        $result     = array();
        $ioObj      = app::get('ome')->model('iostock');
        $field      = 'iostock_id, iostock_bn, type_id, branch_id, original_bn, original_id, original_item_id, bn';
        $temp_data  = $ioObj->getList($field, array('original_id'=>$delivery_ids, 'type_id'=>'3'));
        
        foreach ($temp_data as $key => $val)
        {
            $result[$val['original_item_id']]   = $val;
        }
        
        return $result;
    }
    
    /**
     * 多个发货单累加物流成本
     * 
     * @param   Array   $iostock_data   出入库单
     * @param   bool    $is_create_sales 生成销售单单独判断
     * @return  Array
     */
    public function count_delivery_cost_actual($order_id, $is_create_sales=false)
    {
        $oDelivery      = app::get('ome')->model('delivery');
        $delivery_ids   = $temp_data = array();
        
        #获取订单对应所有发货单delivery_id
        $sql            = "SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            WHERE dord.order_id='".$order_id."' AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false'";
        
        #生成销售单时,去除return_back追加发货单状态
        if($is_create_sales){
            $sql    .= " AND d.status NOT IN('failed','cancel','back')";
        }else{
            $sql    .= " AND d.status NOT IN('failed','cancel','back','return_back')";
        }
        
        $temp_data      = kernel::database()->select($sql);
        
        //[无拆单]订单只有一个发货单,直接返回false
        if(count($temp_data) < 2){
            return false;
        }
        
        foreach ($temp_data as $key => $val)
        {
            $delivery_ids[]     = $val['delivery_id'];
        }
        
        //累加物流成本
        $dly_data               = $oDelivery->getList('delivery_id, delivery_cost_actual, parent_id, is_bind', array('delivery_id'=>$delivery_ids));
        $delivery_cost_actual   = 0;
        foreach ($dly_data as $key => $val)
        {
            //[合并发货单]重新计算物流运费
            if($val['is_bind'] == 'true'){
                $val['delivery_cost_actual']    = $this->compute_delivery_cost_actual($order_id, $val['delivery_id'], $val['delivery_cost_actual']);
            }
            $delivery_cost_actual += floatval($val['delivery_cost_actual']);
        }
        
        return $delivery_cost_actual;
    }
    
    /**
     * 合并发货单_平摊预估物流运费
     * 
     * @param $order_id
     * @param $delivery_id
     * @param  $delivery_cost_actual
     * @return  Array
     */
    public function compute_delivery_cost_actual($order_id, $delivery_id, $delivery_cost_actual)
    {
        $oOrders    = app::get('ome')->model('orders');
        $oDelivery  = app::get('ome')->model('delivery');
        
        $orderIds   = $oDelivery->getOrderIdsByDeliveryIds(array($delivery_id));
        
        $sales_data = $temp_data  = array();
        $temp_data  = $oOrders->getList('order_id, payed', array('order_id'=>$orderIds));
        foreach ($temp_data as $key => $val)
        {
            $val['delivery_cost_actual']    = $delivery_cost_actual;
            $sales_data[$val['order_id']]   = $val;
        }
        
        //平摊预估物流运费，主要处理订单合并发货以及多包裹单的运费问题
        $ome_sales_logistics_feeLib = kernel::single('ome_sales_logistics_fee');
        $ome_sales_logistics_feeLib->calculate($orderIds,$sales_data);
        
        return $sales_data[$order_id]['delivery_cost_actual'];//返回所查订单的平摊物流费用
    }    
    
    /**
     * 获取订单中货品已发货数量
     * 
     * @param Intval $order_id
     * @param Intval $item_id
     * @param Intval $product_id
     * @return Number
     */
    public function get_item_product_num($order_id, $item_id, $product_id)
    {
        if(empty($order_id) || empty($item_id) || empty($product_id))
        {
            return 0;
        }
        
        $sql    = "SELECT SUM(did.number) AS num FROM `sdb_ome_delivery_items_detail` did
                                JOIN `sdb_ome_delivery` d ON d.delivery_id=did.delivery_id
                                WHERE did.order_id='".$order_id."'
                                AND did.order_item_id='".$item_id."'
                                AND did.product_id='".$product_id."'
                                AND d.status != 'back' AND d.status != 'cancel' AND d.status != 'return_back' AND d.is_bind = 'false'";
        $oi    = kernel::database()->selectrow($sql);
        
        return intval($oi['num']);
    }

    /**
     * 获取OrderItemsForDelivery
     * @param mixed $orders orders
     * @param mixed $items items
     * @param mixed $splitting_product splitting_product
     * @return mixed 返回结果
     */
    public function getOrderItemsForDelivery($orders, $items, $splitting_product) {
        $order_id = array();
        foreach ($orders as $value) {
            $order_id[] = $value['order_id'];
        }

        if (count($orders) > 1 || empty($splitting_product)) {
            foreach ($items as $item){
                $nums = $item['nums'] - $item['sendnum'] - $item['split_num'];
                if ($nums <= 0) continue;
                $item['original_num'] = $item['nums'];
                $item['nums'] = $nums;
                $orders[$item['order_id']]['items'][$item['item_id']] = $item;
            }
            return $orders;
        }

        $proSplit = array(); $pkgSplit = array();
        foreach ($splitting_product as $item_type => $item) {
            if (!in_array($item_type, kernel::single('ome_order_func')->get_pkg_type())) {
                foreach ($item as $product_id => $quantity) {
                    $proSplit[$product_id] += $quantity;
                }
            } else {
                foreach ($item as $product_id => $im) {
                    foreach ($im as $item_id => $quantity) {
                        $pkgSplit[$product_id] += $quantity;
                    }
                }
            }
        }

        // 处理拆单,
        $pkgSplitItemProduct = array();
        foreach ($items as $key => $item) {
            $leftNum = $item['nums'] - $item['split_num'];

            // 无可审核数量
            if ($leftNum <= 0) continue;

            $nums = in_array($item['item_type'], kernel::single('ome_order_func')->get_pkg_type()) ? (int) $splitting_product[$item['item_type']][$item['product_id']][$item['item_id']]/*$pkgSplit[$item['product_id']]*/ : (int)$proSplit[$item['product_id']];//不使用$pkgSplit处理捆绑商品不同子商品相同的问题

            // 如果传入的数量比剩余数量多，取剩余数量
            if ($nums > $leftNum) {
                $nums = $leftNum;
            }

            if ($nums <= 0) {
                $pkgSplitItemProduct[$item['product_id']][$item['item_id']] = $item;
                continue;
            }

            if (in_array($item['item_type'], kernel::single('ome_order_func')->get_pkg_type())) {
                $pkgSplit[$item['product_id']] -= $nums;
            } else {
                $proSplit[$item['product_id']] -= $nums;
            }
            $item['original_num'] = $item['nums'];
            $item['nums'] = $nums;
            $orders[$item['order_id']]['items'][$item['item_id']] = $item;
        }
        //处理捆绑商品相同的问题
        foreach ($pkgSplit as $productId => $dlyNum) {
            if($dlyNum <= 0) {
                continue;
            }
            foreach ($pkgSplitItemProduct[$productId] as $item) {
                if($dlyNum <= 0) {break;}
                $leftNum = $item['nums'] - $item['sendnum'] - $item['split_num'];
                if ($leftNum <= 0) continue;
                if ($dlyNum > $leftNum) {
                    $nums = $leftNum;
                } else {
                    $nums = $dlyNum;
                }
                $dlyNum -= $nums;
                $item['nums'] = $nums;
                $orders[$item['order_id']]['items'][$item['item_id']] = $item;
            }
        }
        return $orders;
    }
    /**
     * 格式化拆分的订单明细
     * 
     * @param Array $orders 订单数组
     * @param Array $objects 订单object对象层数据
     * @param Array $items 订单明细
     * @param Array $splitting_product 拆分的商品数量
     * @return $Array
     */
    public function format_mkDelivery($orders, $objects, $items, $splitting_product)
    {
        if(empty($orders) || empty($items) || empty($objects))
        {
            return array();
        }
        $old_items = array();
        foreach ($items as $item)
        {
            //暂存原来总的货品需要发数，后面计算捆绑的obj层的数量
            $old_items[$item['item_id']] = $item['nums'];
        }
        $orders = $this->getOrderItemsForDelivery($orders, $items, $splitting_product);
        //根据最终每个item货品实际需发数量计算obj对象要释放多少冻结
        $tmp_orders = array();
        foreach($orders as $order_id => $order){
            if(!isset($tmp_orders[$order_id])){
                $tmp_orderInfo = $order;
                unset($tmp_orderInfo['items']);
                $tmp_orders[$order_id] = $tmp_orderInfo;
            }

            foreach($order['items'] as $item_id => $item){
                if(!isset($tmp_orders[$order_id]['objects'][$item['obj_id']])){
                    $tmp_orders[$order_id]['objects'][$item['obj_id']] = $objects[$item['obj_id']];
                }

                $tmp_orders[$order_id]['objects'][$item['obj_id']]['items'][$item['item_id']] = $item;

                if(in_array($item['item_type'], kernel::single('ome_order_func')->get_pkg_type())){
                    $tmp_orders[$order_id]['objects'][$item['obj_id']]['quantity'] = $objects[$item['obj_id']]['quantity']/$old_items[$item['item_id']]*$item['nums'];
                }else{
                    $tmp_orders[$order_id]['objects'][$item['obj_id']]['quantity'] = $item['nums'];
                }
            }
        }

        return $tmp_orders;
    }    
    
    /**
     * 判断订单是否进行了拆单操作
     * 
     * @param   Number    $delivery_id 发货单id
     * @return  Boolean
     */
    public function check_order_is_split($delivery, $chk_oid=false)
    {
        #获取订单order_id
        $order_id    = intval($delivery['order']['order_id']);
        if(empty($order_id))
        {
            return false;
        }
        
        #获取订单关联的所有发货单id
        $deliveryObj    = app::get('ome')->model('delivery');
        $dly_ids        = $deliveryObj->getDeliverIdByOrderId($order_id);
        
        if(count($dly_ids) > 1)
        {
            return true;
        }
        
        #获取订单是否有未生成的发货单的商品
        $sql   = "SELECT item_id FROM sdb_ome_order_items WHERE order_id = '".$order_id."' AND nums != sendnum AND `delete` = 'false'";
        $row   = kernel::database()->selectrow($sql);
        
        if(!empty($row))
        {
            return true;
        }
        
        #拆单后_余单撤消
        $result     = $this->order_remain_cancel($order_id);
        if($result)
        {
            return true;
        }
        
        return false;
    }    
    
    /**
     * [余单撤消]根据拆单方式进行回写
     * 
     * @param intval $order_id
     * @return boolean
     */
    public function order_remain_cancel($order_id)
    {
        $orderObj    = app::get('ome')->model('orders');
        $row         = $orderObj->dump(array('order_id'=>$order_id), 'process_status');
        
        return ($row['process_status'] == 'remain_cancel' ? true : false);
    }
    
    /**
     * [发货单]获取成功发货的记录
     * 
     * @param $order_id   订单ID
     * @param $out_delivery_id    排除的发货单ID
     * @return array
     */
    public function get_delivery_succ($order_id, $out_delivery_id = 0)
    {
        $sql    = "SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d 
                    ON(dord.delivery_id=d.delivery_id) WHERE dord.order_id='".intval($order_id)."' AND d.status='succ' AND d.process='true'";
        
        if($out_delivery_id)
        {
            $sql    .= " AND d.delivery_id != '".intval($out_delivery_id)."'";
        }
        
        $data   = kernel::database()->select($sql);
        
        return $data;
    }
    
    /**
     * 获取财务退款确认中申请的商品明细(淘宝、天猫)
     * 
     * @param $order_id
     * @param $bn_data
     * @param $oid_data
     * @return array
     */
    public function getRefundBnList($order_id, $delivery_id, $bn_data, $oid_data)
    {
        if(empty($order_id) || empty($delivery_id) || empty($bn_data) || empty($oid_data))
        {
            return false;
        }
        
        $refund_list          = array();
        $refund_applyObj    = app::get('ome')->model('refund_apply');
        
        #默认只查询15条有商品明细的退款记录
        $refund_array    = $refund_applyObj->getList('product_data', array('order_id'=>$order_id, 'status'=>'4', 'product_data|noequal'=>''), 0, 15);
        
        if(!empty($refund_array))
        {
            foreach ($refund_array as $key => $val)
            {
                $product_data    = unserialize($val['product_data']);
                if(!is_array($product_data))
                {
                    continue;
                }
                
                foreach ($product_data as $item)
                {
                    $refund_list[]    = $item['bn'];
                }
            }
        }
        
        if(!empty($refund_list))
        {
            //发货单明细
            $deliItemModel = app::get('ome')->model('delivery_items');
            $develiy_items = $deliItemModel->getList('product_id, bn, number', array('delivery_id'=>$delivery_id));
            
            //获取购买商品的bn
            $goods_bn     = array();
            foreach($develiy_items as $key => $item)
            {
                $goods_bn[]    = $item['bn'];
            }
            
            #退款上商品明细与发货单商品明细相同时直接返回false_防止回写全部到前端店铺
            if(array_diff($refund_list, $goods_bn))
            {
                return false;
            }
            
            $orderItemModel    = app::get('ome')->model('order_items');
            $orderObjModel     = app::get('ome')->model('order_objects');
            
            $item_list         = $orderItemModel->getList('obj_id', array('order_id'=>$order_id, 'bn'=>$refund_list), 0, -1);
            if(empty($item_list))
            {
                return false;
            }
            $obj_ids    = array();
            foreach ($item_list as $key => $val)
            {
                $obj_ids[]    = $val['obj_id'];
            }
            
            $obj_list        = $orderObjModel->getList('bn', array('order_id'=>$order_id, 'obj_id'=>$obj_ids), 0, -1);
            if(empty($obj_list))
            {
                return false;
            }
            
            $refund_list    = array();
            foreach ($obj_list as $key => $val)
            {
                $refund_list[]    = $val['bn'];
            }
            
            #过滤掉退款的商品明细
            foreach ($refund_list as $refund_bn)
            {
                foreach ($bn_data as $key => $oid_bn)
                {
                    if($refund_bn == $oid_bn)
                    {
                        unset($bn_data[$key], $oid_data[$key]);#删除前端店铺的oid
                    }
                }
            }
        }
        unset($order_id, $refund_array, $refund_list, $product_data, $item_list, $obj_list, $obj_ids);
        
        return array('bn_data'=>$bn_data, 'oid_data'=>$oid_data);
    }    
    
    /**
     * 获取订单未发货的发货单 和 未拆分的订单商品
     * 
     * @param   Number    $delivery_id  发货单id
     * @param   Number    $parent_id    合并发货单中的父发货单
     * @return array
     */
    public function split_order_delivery_process($delivery_id, $parent_id=0)
    {
        $result         = array();
        $deliveryObj    = app::get('ome')->model('delivery');
        
        //获取订单order_id
        $order_ids     = $deliveryObj->getOrderIdByDeliveryId($delivery_id);
        $order_id      = $order_ids[0];
        
        //根据订单order_id获取未发货的发货单
        $temp_ids       = $deliveryObj->getDeliverIdByOrderId($order_id);
        if(!empty($temp_ids)){
            //去除现操作的delivery_id发货单
            $delivery_ids     = array();
            foreach ($temp_ids as $key => $val)
            {
                if($val == $delivery_id)  continue;
                
                //过滤合并发货单中的父发货单
                if($parent_id && $val == $parent_id)
                {
                    continue;
                }
                
                $delivery_ids[]  = $val;
            }
            
            if(!empty($delivery_ids)){
                $result['delivery']    = $deliveryObj->getList('delivery_id, delivery_bn', array('delivery_id'=>$delivery_ids, 'process'=>'false'), 0, 1);
            }
        }
        
        //[节省资源]有未发货的发货单则直接返回
        if($result['delivery'])
        {
            return $result;
        }
        
        //获取订单是否有未生成的发货单
        $sql   = "SELECT item_id FROM sdb_ome_order_items WHERE order_id = '".$order_id."' AND nums != sendnum AND `delete` = 'false'";
        $row   = kernel::database()->selectrow($sql);
        $result['order_items'] = $row;
        
        return $result;
    }
    
    /**
     * 开启拆单,审单流程处理
     * 
     * @param Array $order
     * @param Array $combineOrders
     * @param String $error_msg
     * 
     * @return Array
     */
    public function checkOrderConfirm($order, &$combineOrders, &$error_msg)
    {
        $order_id  = $order['order_id'];
        $result    = array('rsp'=>'fail');
        
        //订单上重复货品处理
        $flag               = false;
        $chk_repeat_list    = array();
        $fail_pkg           = array();
        
        if($combineOrders[$order_id]['items'])
        {
            #捆绑商品中有单个货品是删除状态
            foreach ($combineOrders[$order_id]['items'] as $items_type => $item_row)
            {
                foreach ($item_row as $obj_id => $obj_item)
                {
                    foreach ($obj_item['order_items'] as $item_id => $order_item)
                    {
                        if($items_type == 'pkg' || $items_type == 'lkb')
                        {
                            $fail_pkg['obj_bn'][$obj_id]              = $obj_item['bn'];
                            $fail_pkg['delete'][$obj_id][$item_id]    = $order_item['delete'];
                        }
                    }
                }
            }
            
            foreach ($combineOrders[$order_id]['items'] as $items_type => $item_row)
            {
                foreach ($item_row as $obj_id => $obj_item) 
                {
                    foreach ($obj_item['order_items'] as $item_id => $order_item) 
                    {
                        //剔除无效的商品
                        if($order_item['delete'] == 'true' && ($items_type == 'pkg' || $items_type == 'giftpackage' || $items_type == 'lkb')) 
                        {
                            unset($combineOrders[$order_id]['items'][$items_type][$obj_id]);
                            break;
                        }
                        elseif ($order_item['delete'] == 'true')
                        {
                            unset($combineOrders[$order_id]['items'][$items_type][$obj_id][$item_id]);
                            break;
                        }
                        
                        //标记重复的商品
                        $chk_repeat_list[$order_item['product_id']]['num']++;
                        if($chk_repeat_list[$order_item['product_id']]['num'] > 1)
                        {
                            $flag   = true;
                        }
                    }
                }
            }
        }
        
        #捆绑商品中有单个货品是删除状态
        foreach ($fail_pkg['delete'] as $chk_obj_id => $chk_item)
        {
            if(in_array('false', $chk_item) && in_array('true', $chk_item))
            {
                $fail_pkg['bn'][]    = $fail_pkg['obj_bn'][$chk_obj_id];
            }
        }
        
        //捆绑商品中有删除的货品,直接跳出
        if($fail_pkg['bn'])
        {
            $error_msg    = "捆绑商品：". implode(',', $fail_pkg['bn']) ." 存在删除状态的货品,请修正后再审核";
            
            return $result;
        }
        unset($fail_pkg);
        
        #订单部分发货或部分拆分
        if($order['process_status'] == 'splitting' || $order['ship_status'] == '2') 
        {
            $flag   = false;
        }
        
        $result    = array('rsp'=>'succ');
        return $result;
    }
    
    /**
     * 获取订单已拆分的发货单信息
     * 
     * @param Int $order_id
     * @param Array $branch_list
     * 
     * @return Array
     */
    public function getDeliveryByOrderId($order_id, $branch_list)
    {
        $dlyObj    = app::get('ome')->model('delivery');
        $dly_ids   = $dlyObj->getDeliverIdByOrderId($order_id);
        $order_dlylist = array();
        
        if($dly_ids)
        {
            //仓库
            $branch_data   = array();
            foreach ($branch_list as $key => $val)
            {
                $temp_id   = $val['branch_id'];
                $branch_data[$temp_id]    = $val['name'];
            }
            
            $status_text = array ('succ' => '已发货','failed' => '发货失败','cancel' => '已取消','progress' => '等待配货', 
                                  'timeout' => '超时','ready' => '等待配货','stop' => '暂停','back' => '打回');
            
            //发货单
            $in_ids    = implode(',', $dly_ids);
            $sql       = "SELECT i.*, d.delivery_bn, d.branch_id, d.logi_id, d.logi_name, d.status, d.delivery_cost_expect, d.is_bind FROM sdb_ome_delivery_items AS i 
                          LEFT JOIN sdb_ome_delivery AS d ON i.delivery_id=d.delivery_id WHERE i.delivery_id in(".$in_ids.")";
            $temp_data  = $dlyObj->db->select($sql);
            
            $order_dlylist   = array();
            foreach ($temp_data as $key => $val)
            {
                $val_dlyid      = $val['delivery_id'];
                $val_status     = $val['status'];
                $val_branch_id  = $val['branch_id'];
                
                $val['status']      = $status_text[$val_status];//发货状态
                $val['branch_name'] = $branch_data[$val_branch_id];//仓库
                
                $order_dlylist[$val_dlyid]['list'][]    = $val;
                $order_dlylist[$val_dlyid]['count']     = count($order_dlylist[$val_dlyid]['list']);
            }
        }
        
        return $order_dlylist;
    }
    
    /**
     * 获取订单的退款、退换货记录
     * 
     * @param Int $order_id
     * 
     * @return Array
     */
    public function getReshipByOrderId($order_id)
    {
        $orderItemObj   = app::get('ome')->model('order_items');
        $oReship        = app::get('ome')->model('reship');
        $oRefund_apply  = app::get('ome')->model('refund_apply');
        $result = array();
        
        //售后单状态
        $status_text    = $oReship->is_check;
        
        //退换货记录
        $sql            = "SELECT r.reship_bn, r.status, r.is_check, r.tmoney, r.return_id, i.* 
                           FROM sdb_ome_reship as r left join sdb_ome_reship_items as i on r.reship_id=i.reship_id 
                           WHERE r.order_id='".$order_id."' AND r.return_type in('return', 'change') AND r.is_check!='5'";
        $reship_list    = $oReship->db->select($sql);
        if($reship_list)
        {
            $temp_bn  = array();
            foreach ($reship_list as $key => $val)
            {
                $val['return_type_name']    = ($val['return_type'] == 'return' ? '退货' : '换货');
                $val['type_name']           = $status_text[$val['is_check']];
                $val['addon']               = '-';//规格
                
                //存储货号查询规格
                $temp_bn[]        = $val['product_id'];
                
                $reship_list[$key]  = $val;
            }
            
            $temp_items = array();
            $temp_addon = $orderItemObj->getList('product_id, addon', array('order_id'=>$order_id, 'product_id'=>$temp_bn));
            foreach ($temp_addon as $key => $val)
            {
                if($val['addon'])
                {
                    $temp_items[$val['product_id']] = ome_order_func::format_order_items_addon($val['addon']);;
                }
            }
            
            if($temp_addon)
            {
                foreach ($reship_list as $key => $val)
                {
                    $product_id = $val['product_id'];
                    
                    if($temp_items[$product_id])
                    {
                        $val['addon']       = $temp_items[$product_id];
                    }
                    $reship_list[$key]      = $val;
                }
            }
            
            unset($temp_bn, $temp_addon, $temp_items);
            
            //返回退换货记录
            $result['reship_list']    = $reship_list;
        }
        
        //退款记录
        $field    = 'apply_id, refund_apply_bn, money, refunded, create_time, last_modified, status, return_id';
        $refund_apply    = $oRefund_apply->getList($field, array('order_id'=>$order_id, 'disabled'=>'false'));
        if($refund_apply){
            foreach($refund_apply as $k=>$v){
                $refund_apply[$k]['status_text'] = ome_refund_func::refund_apply_status_name($v['status']);
            }
            
            //返回退款记录
            $result['refund_apply']    = $refund_apply;
        }
        
        return $result;
    }
    
    /**
     * 检查多个重复商品的库存
     * 场景：两个不同的销售物料上绑定了相同的基础物料,但库存只满足一个销售物料!
     * 
     * @param   Array  $dlyItems 发货的商品
     * @param   Number $branch_id 发货的仓库
     * @return bool
     */
    public function check_branch_stoce($dlyItems, $branch_id, &$error_msg)
    {
        $is_repeat = false;
        $itemList = array();
        foreach ($dlyItems as $item_type => $items){
            if($item_type == 'pkg' || $item_type == 'lkb'){
                foreach ($items as $product_id => $temVal){
                    foreach ($temVal as $num){
                        if($itemList[$product_id]){
                            $is_repeat = true;
                        }
                        $itemList[$product_id] += intval($num);
                    }
                }
            }
            else
            {
                foreach ($items as $product_id => $num){
                    if($itemList[$product_id]){
                        $is_repeat = true;
                    }
                    $itemList[$product_id] += intval($num);
                }
            }
        }
        
        //有重复的商品,检查库存
        if($is_repeat){
            $libBranchProduct = kernel::single('ome_branch_product');
            $basicMaterialObj = app::get('material')->model('basic_material');
            
            $product_ids = array_keys($itemList);
            $usable_store = $libBranchProduct->getAvailableStore($branch_id, $product_ids);
            
            foreach ($itemList as $product_id => $num){
                if ($num > $usable_store[$product_id]) {
                    $productInfo = $basicMaterialObj->dump(array('bm_id'=>$product_id), 'material_bn');
                    $error_msg = '重复的货号：'.$productInfo['material_bn'].' 可用库存不足';
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * [拆单]获取发货单包含的oid
     * 
     * @param int $delivery_id
     * @return array
     */
    public function getDeliveryOids($delivery_id)
    {
        $dlyDetailMdl = app::get('ome')->model('delivery_items_detail');
        
        //list
        $itemList = $dlyDetailMdl->getList('item_detail_id,delivery_id,oid', array('delivery_id'=>$delivery_id));
        if(empty($itemList)){
            return array();
        }
        
        $oidList = array();
        foreach($itemList as $key => $val)
        {
            $oid = $val['oid'];
            if(empty($oid)){
                continue;
            }
            
            $oidList[$oid] = $oid;
        }
        
        return $oidList;
    }
}
