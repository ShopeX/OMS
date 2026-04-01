<?php
/**
 * 按基础物料分类
 */
class omeauto_auto_type_materialcat extends omeauto_auto_type_abstract implements omeauto_auto_type_interface
{
    /**
     * 商品类型
     * 
     * @param object $tpl
     * @return void
     */
    public function _prepareUI(&$tpl)
    {
        $catMdl = app::get('material')->model('basic_material_cat');
        
        //客户分类
        $catList = $catMdl->getList('cat_id,parent_id,is_leaf,type_id,cat_name,cat_code', array('disabled'=>'false'), 0, -1);
        if($catList){
            $catList = array_column($catList, null, 'cat_id');
        }
        
        $tpl->pagedata['cat_list'] = $catList;
    }
    
    //检查输入的参数
    public function checkParams($params)
    {
        if (empty($params['cat_id'])) {
            return "你还没有选择相应的基础物料分类\n\n请勾选以后再试！！";
        }
        
        if (empty($params['find_type'])) {
            return "请先选择筛选范围！";
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
        $catMdl = app::get('material')->model('basic_material_cat');
        
        //客户分类
        $caption = '';
        $catList = $catMdl->getList('cat_id,parent_id,is_leaf,type_id,cat_name,cat_code', array('disabled'=>'false'), 0, -1);
        if($catList){
            $catList = array_column($catList, null, 'cat_id');
            
            foreach ($catList as $key => $val)
            {
                if($key == $params['cat_id']){
                    if($params['find_type'] == 'not_include'){
                        $caption = sprintf('订单明细中 [不包含] [%s] 基础物料分类', $val['cat_name']);
                    }else{
                        $caption = sprintf('订单明细中 [包含] [%s] 基础物料分类', $val['cat_name']);
                    }
                }
            }
        }
        
        //role
        $role = array('role'=>'materialcat', 'caption'=>$caption, 'content'=>array('cat_id'=>$params['cat_id'], 'find_type'=>$params['find_type']));
        
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
        
        //基础物料分类
        $cat_id = intval($this->content['cat_id']);
        $find_type = $this->content['find_type'];
        
        //获取订单明细中的基础物料
        $arrProductId = array();
        foreach ($item->getOrders() as $order)
        {
            foreach ($order['objects'] as $objKey => $objVal)
            {
                foreach ($objVal['items'] as $itemKey => $itemVal)
                {
                    $product_id = $itemVal['product_id'];
                    $arrProductId[$product_id] = $product_id;
                }
            }
        }
        
        //获取基础物料
        $basicMaterialObj = app::get('material')->model('basic_material');
        $tempList = $basicMaterialObj->getList('bm_id,material_bn,material_name,cat_id', array('bm_id'=>$arrProductId));
        
        //获取规则中的基础物料
        $virtualBns = array();
        foreach ($tempList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            
            if($val['cat_id'] == $cat_id){
                $virtualBns[$bm_id] = $val['material_bn'];
            }
        }
        
        //场景一：[不包含]指定基础物料分类
        if($find_type == 'not_include'){
            if($virtualBns){
                return false;
            }
        }else{
            //场景二：[包含]指定基础物料分类
            if(empty($virtualBns)){
                return false;
            }
        }
        
        return true;
    }
}