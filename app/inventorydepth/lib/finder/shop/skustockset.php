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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2021/8/10 18:10:54
 * @describe: 类
 * ============================
 */
class inventorydepth_finder_shop_skustockset {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    public $addon_cols = 'skus_id,shop_product_bn,branch_id';

    public $column_shopname='店铺名称';
    public $column_shopname_width = "120";
    public $column_shopname_order = 5;
    public function column_shopname($row, $list){
        $shopSkus = $this->_getShopSkus($row, $list);
        return $shopSkus['shop_name'];
    }

    public $column_shopskuid='平台SKUID';
    public $column_shopskuid_width = "120";
    public $column_shopskuid_order = 55;
    public function column_shopskuid($row, $list){
        $shopSkus = $this->_getShopSkus($row, $list);
        return $shopSkus['shop_sku_id'];
    }

    public $column_shopiid='平台商品ID';
    public $column_shopiid_width = "120";
    public $column_shopiid_order = 65;
    public function column_shopiid($row, $list){
        $shopSkus = $this->_getShopSkus($row, $list);
        return $shopSkus['shop_iid'];
    }

    public $column_shoptitle='平台商品名称';
    public $column_shoptitle_width = "120";
    public $column_shoptitle_order = 75;
    public function column_shoptitle($row, $list){
        $shopSkus = $this->_getShopSkus($row, $list);
        return $shopSkus['shop_title'];
    }

    public $column_salesname='OMS商品名称';
    public $column_salesname_width = "120";
    public $column_salesname_order = 15;
    public function column_salesname($row, $list){
        $sm = $this->_getSaleMaterial($row, $list);
        return $sm['sales_material_name'];
    }

    public $column_branchname='系统仓库名称';
    public $column_branchname_width = "120";
    public $column_branchname_order = 17;
    public function column_branchname($row, $list){
        $branch = $this->_getBranchInfo($row, $list);
        return $branch['name'];
    }

/*    public $column_store='库存';
    public $column_store_width = "120";
    public $column_store_order = 85;
    public function column_store($row, $list){
        if ($this->is_export_data) {
            return '';
        }
        $jsHtml = <<<EOF
<script type="text/javascript">
    new Request.JSON({
            url:"{$url}",
            data:{
                branch_code:"{$branch_code}",
                bn:"{$strBn}"
            },
            onComplete: function(rsp) {
                if(rsp.data) {
                    Object.each(rsp.data, function(item, key) {
                        if($(key)) {
                            $(key).setHTML(item);
                            if(item < 0) {
                                $(key).getParent('TR').addClass('list-warning');
                            }
                        }
                    });
                }
            }
        }).send();
</script>
EOF;
        return $branch['name'];
    }

    public $column_storefreeze='冻结库存';
    public $column_storefreeze_width = "120";
    public $column_storefreeze_order = 95;
    public function column_storefreeze($row, $list){
        $branch = $this->_getBranchInfo($row, $list);
        return $branch['name'];
    }*/

    private $shopSkus;
    private function _getShopSkus($row, $list) {
        $skus_id = $row[$this->col_prefix.'skus_id'];
        if($this->shopSkus) {
            return $this->shopSkus[$skus_id];
        }
        $skusIds = [];
        foreach ($list as $v) {
            $skusIds[] = $v[$this->col_prefix.'skus_id'];
        }
        $fields = 'id,shop_name,shop_title,shop_iid,shop_sku_id';
        $rows = app::get('inventorydepth')->model('shop_skus')->getList($fields, ['id'=>$skusIds]);
        $this->shopSkus = array_column($rows, null, 'id');
        return $this->shopSkus[$skus_id];
    }

    private $salesMaterial;
    private function _getSaleMaterial($row, $list) {
        $shop_product_bn = $row[$this->col_prefix.'shop_product_bn'];
        if($this->salesMaterial) {
            return $this->salesMaterial[$shop_product_bn];
        }
        $bn = [];
        foreach ($list as $v) {
            $bn[] = $v[$this->col_prefix.'shop_product_bn'];
        }
        $fields = 'sm_id,sales_material_bn,sales_material_name';
        $rows = app::get('material')->model('sales_material')->getList($fields, ['sales_material_bn'=>$bn]);
        $this->salesMaterial = array_column($rows, null, 'sales_material_bn');
        return $this->salesMaterial[$shop_product_bn];
    }

    private $branchInfo;
    private function _getBranchInfo($row, $list) {
        $branch_id = $row[$this->col_prefix.'branch_id'];
        if($this->branchInfo) {
            return $this->branchInfo[$branch_id];
        }
        $id = [];
        foreach ($list as $v) {
            $id[] = $v[$this->col_prefix.'branch_id'];
        }
        $fields = 'branch_id,name';
        $rows = app::get('ome')->model('branch')->getList($fields, ['branch_id'=>$id, 'check_permission'=>'false']);
        $this->branchInfo = array_column($rows, null, 'branch_id');
        return $this->branchInfo[$branch_id];
    }
}