<?php

class ome_mdl_order_reservation extends dbeav_model
{
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = '';
        $is_assign_time = true;
        
        //多订单号查询
        $orderBns = array();
        if($filter['order_bn'] && is_string($filter['order_bn']) && strpos($filter['order_bn'], "\n") !== false){
            $orderBns = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));
            
            unset($filter['order_bn']);
        }elseif($filter['order_bn']){
            $orderBns = array($filter['order_bn']);
            
            unset($filter['order_bn']);
        }
        
        if($orderBns){
            $orderIds = array();
            
            //订单列表
            $orderObj = app::get('ome')->model('orders');
            $tempList = $orderObj->getList('order_id', array('order_bn'=>$orderBns), 0, 500);
            foreach((array)$tempList as $row){
                $orderIds[] = $row['order_id'];
            }
            
            //[兼容]归档订单
            if(empty($orderIds)){
                $ordersObj = app::get('archive')->model('orders');
                $tempList = $ordersObj->getList('order_id', array('order_bn'=>$orderBns), 0, 500);
                foreach((array)$tempList as $row) {
                    $orderIds[] = $row['order_id'];
                }
            }
            
            if(empty($orderIds)){
                $orderIds[] = 0;
            }
            
            // where
            $where .= ' AND order_id IN ('. implode(',', $orderIds) .')';
            
            // flag
            $is_assign_time = false;
            
            // unset
            unset($orderIds, $tempList);
        }
        
        //多订单号查询
        $erpOrderBns = array();
        if($filter['erp_order_bn'] && is_string($filter['erp_order_bn']) && strpos($filter['erp_order_bn'], "\n") !== false){
            $erpOrderBns = array_unique(array_map('trim', array_filter(explode("\n", $filter['erp_order_bn']))));
            
            // unset
            unset($filter['erp_order_bn']);
        }elseif($filter['erp_order_bn']){
            $erpOrderBns = array($filter['erp_order_bn']);
            
            // flag
            $is_assign_time = false;
            
            // unset
            unset($filter['erp_order_bn']);
        }
        
        if($erpOrderBns){
            $filter['erp_order_bn'] = $erpOrderBns;
            
            // unset
            unset($erpOrderBns);
        }
        
        // 默认指定：只搜索三个月之内的订单
        if($is_assign_time){
            if(empty($filter['at_time']) && empty($filter['up_time'])){
                $start_date = date('Y-m-d H:i:s', strtotime('-3 month'));
                $end_date = date('Y-m-d H:i:s', time());
                
                $filter['at_time|betweenstr'] = [$start_date, $end_date];
            }
        }
        
        return parent::_filter($filter,$tableAlias,$baseWhere) . $where;
    }
    
    /**
     * 收货人省、市、区
     *
     * @param $row
     * @return string
     */
    public function modifier_custom_reserved_area($row)
    {
        $area = explode(':', $row);
        
        return $area[1];
    }
    
    /**
     * 收货人省、市、区
     *
     * @param $row
     * @return string
     */
    public function modifier_custom_reserved_time($row)
    {
        $date = ($row ? date('Y-m-d', $row) : '');
        
        return $date;
    }
}
