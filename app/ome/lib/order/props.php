<?php
/**
 * 订单扩展属性Lib方法
 *
 * @author wangbiao@shopex.cn
 * @version 2025.11.07
 */
class ome_order_props
{
    /**
     * 保存订单扩展属性
     *
     * @param $orderIds 订单ID（支持多个或单个）
     * @param $data 订单属性值（key：value）
     * @param $error_msg
     * @return false|void
     */
    public function saveOrderProps($orderIds, $data, &$error_msg=null)
    {
        $orderPropsMdl = app::get('ome')->model('order_props');
        
        // check
        if(empty($orderIds) || empty($data)){
            $error_msg = '订单ID、订单属性值不能为空';
            return false;
        }
        
        if(empty($data['props_col']) || empty($data['props_value'])){
            $error_msg = '订单属性值无效';
            return false;
        }
        
        // format
        $orderIds = is_array($orderIds) ? $orderIds : [$orderIds];
        
        // 更新已经存在的订单属性
        $propList = $orderPropsMdl->getList('ps_id,order_id,props_col', array('order_id'=>$orderIds, 'props_col'=>$data['props_col']));
        if($propList){
            foreach ($propList as $prop)
            {
                $order_id = $prop['order_id'];
                $ps_id = $prop['ps_id'];
                
                // update
                if($prop['props_col'] == $data['props_col']){
                    $updateData = [
                        'props_value' => $data['props_value'],
                    ];
                    $orderPropsMdl->update($updateData, ['ps_id'=>$ps_id]);
                    
                    // remove order_id
                    $search_key = array_search($order_id, $orderIds);
                    if ($search_key !== false) {
                        unset($orderIds[$search_key]);
                    }
                    
                }
            }
        }
        
        // 保存订单属性
        if($orderIds){
            foreach ($orderIds as $order_id){
                $saveData = [
                    'order_id' => $order_id,
                    'props_col' => $data['props_col'],
                    'props_value' => $data['props_value'],
                ];
                $orderPropsMdl->insert($saveData);
            }
        }
        
        return true;
    }
    
    /**
     * 批量查询订单扩展属性
     *
     * @param $orderIds 订单ID（一次查询多个）
     * @param $order_props_col 属性key
     * @return void
     */
    public function getOrderProps($orderIds, $order_props_col)
    {
        $orderPropsMdl = app::get('ome')->model('order_props');
        
        // check
        if(empty($orderIds) || empty($order_props_col)){
            return [];
        }
        
        // 订单属性列表
        $tempList = $orderPropsMdl->getList('ps_id,order_id,props_col,props_value', array('order_id'=>$orderIds, 'props_col'=>$order_props_col));
        if(empty($tempList)){
            return [];
        }
        
        // format
        $propList = [];
        $propsValues = [];
        foreach ($tempList as $prop)
        {
            $order_id = $prop['order_id'];
            $props_col = $prop['props_col'];
            $props_value = $prop['props_value'];
            
            $propList[$order_id][$props_col] = [
                'props_col' => $props_col, // 属性值，例如：销售人员编码
                'props_value' => $props_value, // 属性值，例如：销售人员编码
                'props_name' => $props_value, // (备用)属性名称，例如：销售人员名称
            ];
            
            $propsValues[$props_value] = $props_value;
        }
        
        // 销售人员编码
        if($order_props_col == 'seller_code'){
            $sellerMdl = app::get('material')->model('seller');
            
            // 销售人员列表
            $sellerList = $sellerMdl->getList('id,seller_code,seller_name', array('seller_code'=>$propsValues));
            if($sellerList){
                $sellerList = array_column($sellerList, null, 'seller_code');
                
                // format
                foreach ($propList as $order_id => $props)
                {
                    $propInfo = $props['seller_code'];
                    $seller_code = $propInfo['props_value'];
                    
                    if(isset($sellerList[$seller_code])){
                        $propList[$order_id]['seller_code']['props_name'] = $sellerList[$seller_code]['seller_name'];
                    }
                }
            }
        }
        
        return $propList;
    }
}