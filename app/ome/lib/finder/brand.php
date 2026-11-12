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

class ome_finder_brand{

    var $column_edit = '品牌操作';
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public function column_edit($row){

        $finder_id = $_GET['_finder']['finder_id'];
        return '<a href="index.php?app=ome&ctl=admin_brand&act=edit&brand_id='.$row ['brand_id'].'&finder_id='.$finder_id.'" target="dialog::{width:600,height:300,title:\'编辑物料品牌\'}">编辑</a>';
    }


    var $detail_info = '品牌信息';
    function detail_info($brand_id){

        $render =  app::get('ome')->render();
        $render->path[] = array('text'=>app::get('base')->_('商品品牌编辑'));
        $objBrand = &$render->app->model('brand');
        $render->pagedata['brandInfo'] = $objBrand->dump($brand_id);

        if(empty($render->pagedata['brandInfo']['brand_url'])) $render->pagedata['brandInfo']['brand_url'] = 'http://';

        $brand_type_id = $objBrand->getBrandTypes($brand_id);
        foreach($brand_type_id as $row){
            $aType[$row['type_id']] = $row;
        }

        $render->pagedata['seo']=$seo_info;
        $render->pagedata['brandInfo']['type'] = $aType;
        $render->pagedata['type'] = $objBrand->getDefinedType();//所有的商品类型
        $objGtype = &$render->app->model('goods_type');
        $render->pagedata['gtype']['status'] = $objGtype->checkDefined();

        if ($this->vue_mode) {
            return [
                'items' => $render->pagedata['brandInfo'],
                'type' => $aType,
                'defined_type' => $render->pagedata['type'],
            ];
        }

        return $render->fetch('admin/goods/brand/detail.html');
    }

}
