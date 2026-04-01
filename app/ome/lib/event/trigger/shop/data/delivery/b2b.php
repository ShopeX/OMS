<?php
class ome_event_trigger_shop_data_delivery_b2b extends ome_event_trigger_shop_data_delivery_common
{

    public function get_sdf($delivery_id)
    {
        $this->__sdf = parent::get_sdf($delivery_id);
    
        if (!$this->__sdf) {
            return [];
        }
    
        $this->_get_order_objects_sdf($delivery_id);
    
        $this->_get_delivery_items_sdf($delivery_id);
    
        // 订单拆单判断
        $order    = $this->__sdf['orderinfo'];
        $is_split = $this->_is_split_order($delivery_id);
        if ($is_split) {
            // 判断第一单还是最后一单
            $this->_nonsupport_mode_request($delivery_id);
            $delivery = $this->__deliverys[$delivery_id];
        
            $this->__sdf['is_last_delivery'] = false;
            if ((in_array($delivery['delivery_id'], $this->lastDeliveryId) && $order['ship_status'] == '1') || ($delivery['parent_id'] > 0 && in_array($delivery['parent_id'], $this->lastDeliveryId))) {
                $this->__sdf['is_last_delivery'] = true;
            }
        
            //拆单 最后一单发货时回写
            if (!$this->__sdf['is_last_delivery']) {
                return [];
            }
        }
    
        $this->__sdf['is_split']      = $is_split;
        $this->__sdf['order_objects'] = $order['order_objects'];
        return $this->__sdf;
    }
} 