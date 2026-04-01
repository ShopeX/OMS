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
 * 按基础物料分类
 */
class omeauto_order_label_materialcat extends omeauto_order_label_abstract implements omeauto_order_label_interface
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
            $error_msg = '没有设置基础物料类型规则';
            return false;
        }
        
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        //基础物料分类
        $cat_id = intval($this->content['cat_id']);
        $find_type = $this->content['find_type'];
        
        //check
        if(empty($cat_id) || empty($find_type)){
            $error_msg = '基础物料分类或者筛选范围条件，不能为空!';
            return false;
        }
        
        //获取订单明细中的基础物料
        $arrProductId = array();
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                //check
                if($itemVal['delete'] == 'true'){
                    continue;
                }
                
                $product_id = $itemVal['product_id'];
                $arrProductId[$product_id] = $product_id;
            }
        }
        
        //check没有item明细
        if(empty($arrProductId)){
            $error_msg = '订单没有基础物料明细';
            return false;
        }
        
        //获取虚拟商品
        $virtualBns = array();
        $tempList = $basicMaterialObj->getList('bm_id,material_bn,type,cat_id', array('bm_id'=>$arrProductId));
        foreach ($tempList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            
            //check
            if($val['cat_id'] == $cat_id){
                $virtualBns[$bm_id] = $val['material_bn'];
            }
        }
        
        //场景一：[不包含]指定基础物料分类
        if($find_type == 'not_include'){
            if($virtualBns){
                $error_msg = '基础物料编码：'. implode('、', $virtualBns).'包含了指定基础物料分类';
                return false;
            }
        }else{
            //场景二：[包含]指定基础物料分类
            if(empty($virtualBns)){
                $error_msg = '订单明细没有包含指定基础物料分类';
                return false;
            }
        }
        
        return true;
    }
}