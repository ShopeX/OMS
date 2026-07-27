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

class ome_mdl_delivery_order extends dbeav_model{
    function insertParentOrderByItems($parent_id, $items){
        if (!is_array($items)) return false;
        
        $filter['delivery_id'] = $items;
        $rows = $this->getList('order_id', $filter, 0, -1);
        if ($rows){
            foreach ($rows as $item){
                $new_order['delivery_id'] = $parent_id;
                $new_order['order_id']    = $item['order_id'];
                                
                $this->save($new_order);
                $new_order = NULL;
            }
            return true;
        }
        return false;
    }
    #根据物流单号，获取会员备注与订单备注
    public function getMarkInfo($dly_id,$_column=null){

        if(!empty($_column)){
            $column = 'orders.'.$_column;
            $sql = "
                select
                	$column from sdb_ome_orders orders
                join sdb_ome_delivery_order  on orders.order_id=sdb_ome_delivery_order.order_id
                and sdb_ome_delivery_order.delivery_id=".$dly_id;
        }else{
            $sql = '
            select
            	orders.order_bn,orders.custom_mark,orders.mark_text from sdb_ome_orders orders
            join sdb_ome_delivery_order  on orders.order_id=sdb_ome_delivery_order.order_id
            and sdb_ome_delivery_order.delivery_id='.$dly_id;
        }
        return $this->db->select($sql);
    }
    public function getOrderInfo($filed = null,$delivery_id = array()){
       $sql = '
               select '
               .$filed.' 
                from sdb_ome_orders
                left join sdb_ome_delivery_order 
                on sdb_ome_delivery_order.order_id=sdb_ome_orders.order_id
                where sdb_ome_delivery_order.delivery_id in ('.$delivery_id.')';
      return $this->db->select($sql);
    }
}
?>
