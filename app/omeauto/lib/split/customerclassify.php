<?php
/**
 * 按客户分类拆Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.09.23
 */
class omeauto_split_customerclassify extends omeauto_split_abstract
{
    /**
     * 拆单规则配置获取数据
     *
     * @return array
     */
    public function getSpecial() {
        return array();
    }
    
    /**
     * 拆单规则配置保存
     *
     * @param $sdf
     * @return array
     */
    public function preSaveSdf(&$sdf)
    {
        if(empty($sdf['split_config']['class_id'])) {
            return array(false, '[客户分类]未选择，请检查!');
        }
        
        return array(true, '保存成功');
    }
    
    /**
     * 开始拆单处理
     *
     * @param $group 订单组
     * @param $splitConfig 拆单规则配置信息
     * @return array|true[]
     */
    public function splitOrder(&$group, $splitConfig)
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        // orders
        $arrOrder = $group->getOrders();
        
        // 指定拆单的客户分类
        $class_id = isset($splitConfig['class_id']) ? $splitConfig['class_id'] : 0;
        if(empty($class_id)){
            return array(false, '按客户分类拆单失败：没有指定客户分类');
        }
        
        // format
        $goodsIds = [];
        foreach ($arrOrder as $orderKey => $orderVal)
        {
            // order_objects
            foreach ($orderVal['objects'] as $objectKey => $objectVal)
            {
                $goods_id = $objectVal['goods_id'];
                
                // order items
                foreach ($objectVal['items'] as $itemKey => $itemVal)
                {
                    // 过滤没有可拆分的子商品
                    $itemVal['nums'] = $itemVal['nums'] - $itemVal['split_num'];
                    if($itemVal['nums'] < 1) {
                        continue 2;
                    }
                }
                
                $goodsIds[$goods_id] = $goods_id;
            }
        }
        
        // check
        if(empty($goodsIds)){
            return array(false, '按客户分类拆单失败：没有可审核的SKU货品');
        }
        
        // 销售物料列表
        $saleMaterialList = $salesMaterialObj->getList('sm_id,sales_material_bn,class_id', array('sm_id'=>$goodsIds));
        if(empty($saleMaterialList)){
            return array(false, '按客户分类拆单失败：没有销售物料');
        }
        $saleMaterialList = array_column($saleMaterialList, null, 'sm_id');
        
        // split
        $arrOrderId = array();
        $splitOrder = array();
        foreach ($arrOrder as $orderKey => $orderVal)
        {
            $order_id = $orderVal['order_id'];
            
            // order_objects
            foreach ($orderVal['objects'] as $objectKey => $objectVal)
            {
                $goods_id = $objectVal['goods_id'];
                
                // 订单商品是：指定客户分类，则进行拆分
                if(isset($saleMaterialList[$goods_id]) && $saleMaterialList[$goods_id]['class_id'] == $class_id){
                    // 初始化订单信息
                    if(empty($splitOrder[$orderKey])) {
                        $tempOrderInfo = $orderVal;
                        
                        // unset objects
                        unset($tempOrderInfo['objects']);
                        
                        $splitOrder[$orderKey] = $tempOrderInfo;
                    }
                    
                    $splitOrder[$orderKey]['objects'][$objectKey] = $objectVal;
                    
                    // unset
                    unset($arrOrder[$orderKey]['objects'][$objectKey]);
                }else{
                    $arrOrderId[$order_id] = $order_id;
                }
            }
        }
        
        // 剩余需要审单的order_id
        if ($arrOrderId) {
            $group->setSplitOrderId($arrOrderId);
        }
        
        // 本次需要拆单的货品明细
        $group->updateOrderInfo($splitOrder);
        if (empty($splitOrder)) {
            return array(false, '按客户分类拆单失败：无法拆单');
        }
        
        return array(true);
    }
}