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
 * OMS翱象商品finder
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 2023.03.08
 */
class dchain_finder_aoxiang_product
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'product_id';

    var $_url = 'index.php?app=dchain&ctl=admin_aoxiang_product';

    public $column_edit = '操作';
    public $column_edit_width = 130;
    public $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $shop_id = trim($_GET['shop_id']);
        $view = intval($_GET['view']);
        $id = $row['pid'];
        $button = '';
        
        //edit
        if(empty($shop_id)){
            return '';
        }
        
        //组合商品
        if($row['product_type'] == 'combine'){
            $this->_url = 'index.php?app=dchain&ctl=admin_aoxiang_pkgproduct';
        }
        
        //sync
        if(in_array($row['sync_status'], array('none', 'fail', 'running'))){
            if($row['product_type'] == 'combine'){
                $button = '<a href="'. $this->_url .'&act=single_sync&id='. $id .'&view='. $view .'&shop_id='. $shop_id .'&finder_id='. $finder_id .'">组合同步</a>';
            }else{
                $button = '<a href="'. $this->_url .'&act=single_sync&id='. $id .'&view='. $view .'&shop_id='. $shop_id .'&finder_id='. $finder_id .'">同步</a>';
            }
        }elseif(in_array($row['sync_status'], array('succ')) && !in_array($row['mapping_status'], array('succ'))){
            if($row['product_type'] == 'combine'){
                $button = '<a href="'. $this->_url .'&act=single_mapping_sync&id='. $id .'&view='. $view .'&shop_id='. $shop_id .'&finder_id='. $finder_id .'">组合关系同步</a>';
            }else{
                $button = '<a href="'. $this->_url .'&act=single_mapping_sync&id='. $id .'&view='. $view .'&shop_id='. $shop_id .'&finder_id='. $finder_id .'">关系同步</a>';
            }
        }
        return $button;
    }

    var $detail_basic = '商品明细信息';
    function detail_basic($pid)
    {
        $render = app::get('dchain')->render();

        $axSkuMdl = app::get('dchain')->model('aoxiang_skus');

        $itemList = $axSkuMdl->getList('*', array('pid'=>$pid), 0, -1, 'shop_iid ASC');

        $render->pagedata['items'] = $itemList;

        return $render->fetch('aoxiang/product_item.html');
    }
    
    var $detail_product = '销售物料详情';
    function detail_product($pid)
    {
        $render = app::get('dchain')->render();
        
        $axProductMdl = app::get('dchain')->model('aoxiang_product');
        $saleMaterialMdl = app::get('material')->model('sales_material');
        
        //setting
        $html = 'aoxiang/combine_item.html';
        
        //销售物料类型
        $sales_material_types = material_sales_material::$sales_material_type;
        
        //info
        $axProductInfo = $axProductMdl->dump(array('pid'=>$pid), '*');
        if(empty($axProductInfo)){
            return $render->fetch($html);
        }
        
        //销售物料信息
        $sm_ids = array($axProductInfo['product_id']);
        $saleMaterialInfo = $saleMaterialMdl->dump(array('sm_id'=>$sm_ids));
        $sales_material_type = $saleMaterialInfo['sales_material_type'];
        $saleMaterialInfo['type_name'] = $sales_material_types[$sales_material_type]['name'];
        
        //[组合]销售物料关联的子商品
        $sql = "SELECT a.*, b.material_bn,b.material_name FROM sdb_material_sales_basic_material AS a LEFT JOIN sdb_material_basic_material AS b ON a.bm_id=b.bm_id ";
        $sql .= "WHERE a.sm_id IN(". implode(',', $sm_ids) .")";
        $materialList = $saleMaterialMdl->db->select($sql);
        
        $render->pagedata['axProductInfo'] = $axProductInfo;
        $render->pagedata['saleMaterialInfo'] = $saleMaterialInfo;
        $render->pagedata['materialList'] = $materialList;
        
        return $render->fetch($html);
    }
}
