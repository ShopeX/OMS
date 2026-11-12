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
 * sunjing@shopex.cn
 * 天猫换货
 *
 */
class erpapi_shop_matrix_tmall_response_exchange extends erpapi_shop_response_exchange {

    protected function _formatAddParams($params) {
        $sdf = $params;
        $tmallSdf = array(
            'oid'                   =>  $sdf['tid'],
            'return_bn'             =>  $sdf['dispute_id'],
            'status'                =>  $sdf['status'],
            'reason'                =>  $sdf['reason'],
            'comment'               =>  $sdf['desc'],
            'modified'              =>  $sdf['modified'] ? kernel::single('ome_func')->date2time($sdf['modified']) : '',
            'created'               =>  $sdf['createtime'] ? kernel::single('ome_func')->date2time($sdf['createtime']) : time(),
            'refund_phase'          =>  $sdf['refund_phase'],
            'advance_status'        =>  $sdf['advance_status'],
            'cs_status'             =>  $sdf['cs_status'],
            'good_status'           =>  $sdf['good_status'],
            'alipay_no'             =>  $sdf['alipay_no'],
            'buyer_nick'            =>  $sdf['buyer_nick'],
            'desc'                  =>  $sdf['desc'],
            'logistics_no'          =>  $sdf['buyer_logistic_no'] ? $sdf['buyer_logistic_no'] : '',
            'buyer_address'         =>  $sdf['buyer_address'] ? $sdf['buyer_address'] : '',
            'logistics_company'      => $sdf['buyer_logistic_name'] ? $sdf['buyer_logistic_name'] : '',
            'buyer_phone'           =>  $sdf['buyer_phone'] ? $sdf['buyer_phone'] : '',
            'seller_address'        =>  $sdf['address'] ? $sdf['address'] : '',
            'seller_logistic_no'    =>  $sdf['seller_logistic_no'] ? $sdf['seller_logistic_no'] : '',
            'seller_logistic_name'  =>  $sdf['seller_logistic_name'] ? $sdf['seller_logistic_name'] : '',
            'bought_bn'             =>  $sdf['bought_bn'],
            'title'                 =>  $sdf['title'],
            'num'                   =>  $sdf['num'],
            'price'                 =>  $sdf['price'],
            'exchange_bn'           =>  $sdf['exchange_bn'],
            'time_out'              =>  $sdf['time_out'],
            'operation_contraint'   =>  $sdf['operation_contraint'],
            'refund_version'        =>  $sdf['refund_version'],
            'speed_exchange'        =>  kernel::single('ome_order_platform_taobao_aftersale')->extractSpeedExchange($sdf), // 极速换货(1：代表是)，兼容 extend_field
            'shop_type'             =>  'tmall',
            'shop_id'               =>  $this->__channelObj->channel['shop_id'],
            'org_oid'               =>  $sdf['tid'],   
        );
        
        $formatSdf = parent::_formatAddParams($params);
        $tmallSdf = array_merge($formatSdf, $tmallSdf);
        if($params['status']=='EXCHANGE_SUCCESS' && $params['cs_status']=='6'){
            $tmallSdf['status']= 'EXCHANGE_CLOSE';
        }
        
        //平台订单信息
        $orders_detail = $this->getOrderByoid($this->__channelObj->channel['shop_id'],$sdf);
        if (!$orders_detail){
            $this->__apilog['result']['msg'] = '订单不存在';
            return array();
        }
        
        //平台订单号：platform_order_bn
        //@todo：淘宝平台给的tid是订单的oid子订单号,不是order_bn订单号;
        if($orders_detail['platform_order_bn']){
            $tmallSdf['platform_order_bn'] = $orders_detail['platform_order_bn'];
        }else{
            //注销oid子订单号
            unset($tmallSdf['platform_order_bn']);
        }
        
        $tmallSdf['order'] = array(//o.order_id,o.status,o.process_status,o.ship_status,o.pay_status
            'order_id'  =>  $orders_detail['order_id'],
            'status'    =>  $orders_detail['status'],
            'process_status'    =>  $orders_detail['process_status'],
            'ship_status'   =>$orders_detail['ship_status'],
            'pay_status'    =>  $orders_detail['pay_status'],
            'order_bn'      =>  $orders_detail['order_bn'],
        );
        
        if ($orders_detail['tran_type'] == 'archive'){
            $tmallSdf['order']['tran_type'] = 'archive';
        }
        
        $return_items = array();
        foreach($orders_detail['item_list'] as $o_v){
            $price = round($o_v['divide_order_fee']/$o_v['nums'],2);
            $radio = $sdf['num']/$o_v['quantity'];
            $return_items[] =array(
                'bn'        =>  $o_v['item_bn'],
                'name'      =>  $o_v['name'],
                'product_id'=>  $o_v['product_id'],
                'num'       =>  $o_v['obj_type'] == 'pkg' ? (int)($radio * $o_v['nums']) : $sdf['num'],
                'price'     =>  $price,//换货目前价格就为0
                'sendNum'   =>  $o_v['sendnum'],
                'order_item_id'=>$o_v['item_id'],
            );
        }

        $change_items = array();
        if ($params['exchange_bn']){
            $change_items[] = array(
                'bn'    =>  $params['exchange_bn'],
                'num'   =>  $params['num'],
                'price' =>  floatval($params['price']),
            );
        }
        $tmallSdf['change_items'] = $change_items;
        $tmallSdf['return_items'] = $return_items;
        
        return $tmallSdf;
    }

    protected function _returnProductAdditional($sdf) {
        $ret = array(
            'model' => 'return_product_tmall',
            'data' => array(
                'shop_id'               =>  $sdf['shop_id'],
                'refund_phase'          =>  $sdf['refund_phase'],
                'advance_status'        =>  $sdf['advance_status'],
                'cs_status'             =>  $sdf['cs_status'],
                'good_status'           =>  $sdf['good_status'],
                'alipay_no'             =>  $sdf['alipay_no'],
                'buyer_nick'            =>  $sdf['buyer_nick'],
                'buyer_logistic_no'     =>  $sdf['logistics_no'],
                'buyer_address'         =>  $sdf['buyer_address'],
                'buyer_logistic_name'   =>  $sdf['logistics_company'],
                'buyer_phone'           =>  $sdf['buyer_phone'],
                'seller_address'        =>  $sdf['address'],
                'seller_logistic_no'    =>  $sdf['seller_logistic_no'],
                'seller_logistic_name'  =>  $sdf['seller_logistic_name'],
                'exchange_sku'          =>  $sdf['exchange_bn'],
                'current_phase_timeout' =>  $sdf['time_out'] ? strtotime($sdf['time_out']) : '',
                'operation_contraint'   =>  $sdf['operation_contraint'],
                'refund_version'        =>  $sdf['refund_version'],
                'refund_type'           =>  'change',
                'exchange_num'          =>  $sdf['num'],
                'exchange_price'        =>  floatval($sdf['price']),
                'oid'                   =>  $sdf['oid'],
                'org_oid'               =>  $sdf['oid'],   
            )
        );
        return $ret;
    }
    
    public function getOrderByoid($shop_id, $sdf)
    {
        $oid = $sdf['tid'];
        $orderModel = app::get('ome')->model('orders');

        if ($sdf['bought_bn']){
            $order_detail = $orderModel->db->selectrow("SELECT ob.order_id,ob.obj_id FROM  sdb_ome_order_objects as ob left join sdb_ome_order_items as i on ob.obj_id=i.obj_id WHERE ob.oid='".$oid."' AND ob.bn='".$sdf['bought_bn']."' and i.`delete`='false'");
            if(!$order_detail && $sdf['org_oid']){
                $oid = $sdf['org_oid'];
                $order_detail = $orderModel->db->selectrow("SELECT ob.order_id,ob.obj_id FROM  sdb_ome_order_objects as ob left join sdb_ome_order_items as i on ob.obj_id=i.obj_id WHERE ob.oid='".$oid."' AND ob.bn='".$sdf['bought_bn']."' and i.`delete`='false'");
            }
            if(!$order_detail){
                $order_detail = $orderModel->db->selectrow("SELECT ob.order_id,ob.obj_id FROM  sdb_ome_order_objects as ob left join sdb_ome_order_items as i on ob.obj_id=i.obj_id WHERE ob.oid='".$oid."' and i.`delete`='false'");
            }
        }else{
            $order_detail = $orderModel->db->selectrow("SELECT ob.order_id,ob.obj_id FROM  sdb_ome_order_objects as ob WHERE ob.oid='".$oid."' and ob.`delete`='false' ");
        }

        // 兼容换了又换
        if ($sdf['is_change_more']){
            $order_detail = $orderModel->db->selectrow("SELECT ob.order_id,ob.obj_id FROM  sdb_ome_order_objects as ob left join sdb_ome_order_items as i on ob.obj_id=i.obj_id WHERE ob.order_id='".$sdf['order']['order_id']."' AND ob.oid='".$sdf['oid']."' AND i.`delete`='false'");
            if(!$order_detail){
                $order_detail = $orderModel->db->selectrow("SELECT ob.order_id,ob.obj_id FROM  sdb_ome_order_objects as ob left join sdb_ome_order_items as i on ob.obj_id=i.obj_id WHERE ob.order_id='".$sdf['order']['order_id']."'  AND i.`delete`='false'");
            }
        }
        
        if(!$order_detail){
            $archive_detail = $orderModel->db->selectrow("SELECT o.order_bn,o.order_id,o.status,o.process_status,o.ship_status,o.pay_status FROM sdb_archive_orders as o  WHERE  o.order_bn='".$sdf['alipay_no']."' AND o.shop_id='".$shop_id."' ");
            if ($archive_detail){
                //归档里取
                // $items_list =   $orderModel->db->select("SELECT i.bn as item_bn,i.name,i.product_id,i.nums,i.sale_price as divide_order_fee,i.sendnum,i.item_id FROM  sdb_archive_order_items as i WHERE i.order_id=".$archive_detail['order_id']." AND i.bn='".$sdf['bought_bn']."' AND i.delete='false'");

                $items_list =   $orderModel->db->select("SELECT ob.bn,ob.obj_type,ob.obj_id,ob.quantity,i.bn as item_bn,i.name,i.product_id,i.nums,i.sale_price as divide_order_fee,i.sendnum,i.item_id FROM  sdb_archive_order_items as i left join sdb_archive_order_objects as ob on ob.obj_id=i.obj_id WHERE i.order_id=".$archive_detail['order_id']." AND i.bn='".$sdf['bought_bn']."' AND i.delete='false'");

                if ($items_list){
                    $archive_detail['tran_type'] = 'archive';
                    $archive_detail['item_list'] = $items_list;
                    return $archive_detail;
                }
                
            }
            
            return false;
        }
        
        //平台订单信息
        $orders = $orderModel->db->selectrow("SELECT o.platform_order_bn,o.order_bn,o.order_id,o.status,o.process_status,o.ship_status,o.pay_status FROM sdb_ome_orders as o  WHERE  o.order_id=".$order_detail['order_id']." AND o.shop_id='".$shop_id."' ");

        if (!$orders){
            return false;
        }
        $items_list =   $orderModel->db->select("SELECT ob.bn,ob.obj_type,ob.obj_id,ob.quantity,i.bn as item_bn,i.name,i.product_id,i.nums,i.sale_price,i.sendnum,i.divide_order_fee,i.item_id FROM sdb_ome_order_objects as ob  left join sdb_ome_order_items as i on ob.obj_id=i.obj_id  WHERE ob.order_id=".$order_detail['order_id']." AND ob.obj_id=".$order_detail['obj_id']." AND i.delete='false'");
        if(!$items_list){
            return false;
        }
        
        $orders['item_list'] = $items_list;

        return $orders;
    }
    
    /**
     * 判断是否已产生换货后退货订单
     *
     * @param $sdf
     * @return false|mixed|void
     */
    protected function _tranChange($sdf){
        $order = $sdf['order'];
        $order_id = $order['order_id'];
        $tid = $order['order_bn'];
       
        $oid = $sdf['org_oid'];
        if(empty($oid)) return false;
        $org_order_bn = $sdf['org_order_bn'];
        $db = kernel::database();
        $sql = "SELECT r.change_order_id FROM  sdb_ome_return_product_tmall as t LEFT JOIN sdb_ome_reship as r ON t.return_bn=r.reship_bn WHERE  r.is_check not in('5','9') AND r.return_type='change' AND  t.org_oid='".$oid."' limit 5";
        $reship_detail = $db->select($sql);
        if($reship_detail){
            $change_order_ids = array_column($reship_detail,'change_order_id');

            $sql = "SELECT o.order_id as change_order_id FROM sdb_ome_orders as o  WHERE  o.order_id in (".implode(',',$change_order_ids).") AND o.ship_status in('1')";
            $order_detail = $db->selectrow($sql);

            return $order_detail;
        }
    }
}
