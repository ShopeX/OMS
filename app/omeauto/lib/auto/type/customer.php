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
 * 按客户分类
 */
class omeauto_auto_type_customer extends omeauto_auto_type_abstract implements omeauto_auto_type_interface
{
    /**
     * 商品类型
     * 
     * @param object $tpl
     * @return void
     */
    public function _prepareUI(&$tpl)
    {
        $classifyMdl = app::get('material')->model('customer_classify');
        
        //客户分类
        $classList = $classifyMdl->getList('class_id,class_name', array('disabled'=>'false'), 0, -1);
        if($classList){
            $classList = array_column($classList, null, 'class_id');
        }
        
        $tpl->pagedata['class_list'] = $classList;
    }
    
    //检查输入的参数
    public function checkParams($params)
    {
        if (empty($params['class_id'])) {
            return "你还没有选择客户分类\n\n请勾选以后再试！！";
        }
        
        return true;
    }
    
    /**
     * 生成规则字串
     *
     * @param Array $params
     * @return String
     */
    public function roleToString($params)
    {
        $classifyMdl = app::get('material')->model('customer_classify');
        
        //客户分类
        $caption = '';
        $classList = $classifyMdl->getList('class_id,class_name', array('disabled'=>'false'), 0, -1);
        if($classList){
            $classList = array_column($classList, null, 'class_id');
            
            foreach ($classList as $key => $val)
            {
                if($key == $params['class_id']){
                    $caption = sprintf('订单明细中包含 [%s] 客户分类', $val['class_name']);
                }
            }
        }
        
        //role
        $role = array('role'=>'customer', 'caption'=>$caption, 'content'=>array('class_id'=>$params['class_id']));
        
        return json_encode($role);
    }

    /**
     * 检查订单数据是否符合要求
     * 
     * @param omeauto_auto_group_item $item
     * @return boolean
     */
    public function vaild($item)
    {
        if(empty($this->content)) {
            return false;
        }
        
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        //客户分类
        $class_id = intval($this->content['class_id']);
        
        //获取订单明细中的基础物料
        $goodsIds = array();
        foreach ($item->getOrders() as $order)
        {
            foreach ($order['objects'] as $objKey => $objVal)
            {
                $goods_id = $objVal['goods_id'];
                
                // goods ids
                $goodsIds[$goods_id] = $goods_id;
            }
        }
        
        //获取销售物料
        $tempList = $salesMaterialObj->getList('sm_id,sales_material_bn,sales_material_type,class_id', array('sm_id'=>$goodsIds));
        if(empty($tempList)){
            return false;
        }
        
        //获取规则中的基础物料
        $virtualBns = array();
        foreach ($tempList as $key => $val)
        {
            $sm_id = $val['sm_id'];
            
            if($val['class_id'] == $class_id){
                $virtualBns[$sm_id] = $val['sales_material_bn'];
            }
        }
        
        if(empty($virtualBns)){
            return false;
        }
        
        return true;
    }
}