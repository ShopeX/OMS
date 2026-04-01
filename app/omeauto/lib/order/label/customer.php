<?php
/**
 * 按客户分类给订单打标签
 */
class omeauto_order_label_customer extends omeauto_order_label_abstract implements omeauto_order_label_interface
{
    /**
     * 检查订单数据是否符合要求
     *
     * @param array $orderInfo
     * @param string $error_msg
     * @return bool
     */
    public function vaild($orderInfo, &$error_msg=null)
    {
        if(empty($this->content)){
            $error_msg = '没有设置客户分类规则';
            return false;
        }
        
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        // 客户分类ID
        $class_id = intval($this->content['class_id']);
        
        //获取订单明细中的基础物料
        $goodsIds = [];
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            $goods_id = $objVal['goods_id'];
            if($goods_id){
                $goodsIds[$goods_id] = $goods_id;
            }
        }
        
        // 没有销售物料
        if(empty($goodsIds)){
            $error_msg = '订单商品明细';
            return false;
        }
        
        // 销售物料列表
        $saleMaterialList = $salesMaterialObj->getList('sm_id,sales_material_bn,class_id', array('sm_id'=>$goodsIds));
        if(empty($saleMaterialList)){
            $error_msg = '订单明细商品未创建销售物料';
            return false;
        }
        
        // 获取指定的客户分类
        $findGoods = [];
        foreach ($saleMaterialList as $key => $val)
        {
            $sm_id = $val['sm_id'];
            
            //check
            if($val['class_id'] == $class_id){
                $findGoods[$sm_id] = $val['sales_material_bn'];
            }
        }
        
        if(empty($findGoods)){
            $error_msg = '订单商品没有符合的客户分类';
            return false;
        }
        
        return true;
    }
}