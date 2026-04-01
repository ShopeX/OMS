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

class ome_mdl_delivery_items_detail extends dbeav_model{

    /**
     * 创建大发货单对应的发货单订单商品详情
     *
     * @param bigint $parent_id
     * @param array() $items
     * 
     * @return boolean
     */
    function insertParentItemDetailByItemsDetail($parent_id, $items){
        if (!is_array($items)) return false;
        $ids = implode(',', $items);
        $sql = "SELECT *,SUM(number) AS 'total_num',SUM(amount) AS 'total_amount' FROM sdb_ome_delivery_items_detail 
                                        WHERE delivery_id in ($ids) 
                                        GROUP BY order_id,order_obj_id,order_item_id,product_id";
        //echo $sql;
        $rows = $this->db->select($sql);
        if ($rows){
            $diObj = $this->app->model('delivery_items');
            foreach ($rows as $oi){
                $di = $diObj->dump(array('delivery_id'=>$parent_id,'product_id'=>$oi['product_id']));
                $did = array(
                    'delivery_id'       => $parent_id,
                    'delivery_item_id'  => $di['item_id'],
                    'order_id'          => $oi['order_id'],
                    'order_item_id'     => $oi['order_item_id'],
                    'order_obj_id'      => $oi['order_obj_id'],
                    'item_type'         => $oi['item_type'],
                    'product_id'        => $oi['product_id'],
                    'bn'                => $oi['bn'],
                    'number'            => $oi['total_num'],
                    'price'             => $oi['price'],
                    'amount'            => $oi['total_num']*$oi['price'],
                    'actually_amount'   => $oi['actually_amount'], // 客户实付
                );
                $this->save($did);
            }
            return true;
        }
        return false;
    }


    function getOrderobjQuantity($item_id,$delivery_id,$order_obj_bn){
        $order_obj = $this->db->selectrow('SELECT sum(o.quantity) as quantity,sum(o.price) FROM sdb_ome_order_objects as o LEFT JOIN sdb_ome_delivery_items_detail as d on o.obj_id=d.order_obj_id WHERE d.delivery_item_id='.$item_id.' AND d.delivery_id='.$delivery_id.' AND o.bn="'.$order_obj_bn.'"');
        return $order_obj;
    }
    
}
?>
