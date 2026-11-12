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

class console_finder_foreign_sku{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function __construct(){
        if($_GET['wms_id'] != '0'){
            unset($this->column_wms);
        }     
    }
    var $addon_cols = 'inner_sku,wms_id,outer_sku,sync_status,inner_type,inner_product_id';

    var $column_inner_name = '基础物料名称';
    function column_inner_name($row)
    {


        $product_id = $row[$this->col_prefix.'inner_product_id'];

        $inner_type = $row[$this->col_prefix.'inner_type'];


        return $this->get_material_detail($inner_type,$product_id);
    }

    var $column_inner_brand = '商品品牌';
    function column_inner_brand($row)
    {
        $product_id = $row[$this->col_prefix.'inner_product_id'];

        return '';
    }

    var $column_eidt = '操作';
    var $column_edit_order = COLUMN_IN_HEAD;//排在列头
    function column_eidt($row){
        $wms_id = intval($_GET['wms_id']);

        $node_type = kernel::single('channel_func')->getWmsNodeTypeById($_GET['wms_id']);
        if ($node_type == '360buy') {
            $channel = app::get('channel')->model('channel')->dump($_GET['wms_id'],'addon');
            if($channel['addon']['business_type'] == 'yjdf') {
                return '-';
            }
        }
        $product_id = $row[$this->col_prefix.'inner_product_id'];
        $finder_id = $_GET['_finder']['finder_id'];
        $inner_type = $row[$this->col_prefix.'inner_type'];
        $view = intval($_GET['view']);
        //同步成功的不出现同步按钮
        if($row['sync_status'] != '3'){

            if ($node_type == 'qimen') {
                $html = "<a href='index.php?app=console&ctl=admin_goodssync&act=batchSyncDialog&p[0]=".$wms_id."&inner_type=".$inner_type."&finder_id=".$finder_id."' target=dialog::{width:690,height:300,title:'同步单个',ajaxoptions:{method:'POST',data:{view:".$view.",inner_product_id:".$product_id.",fsid:".$row['fsid'].",inner_type:".$inner_type."}}} >同步</a>";
            } else {
                $html = "<a href='index.php?app=console&ctl=admin_goodssync&act=sync&wms_id=".$wms_id."&view=".$view."&inner_type=".$inner_type."&inner_product_id=".$product_id."&fsid=".$row['fsid']."&finder_id=".$finder_id."'>同步</a>";
            }


            return $html;
        }
    }

    var $column_wms = '分派到第三方仓库名称';
    var $column_wms_width = '100';

    function column_wms($row){
        $wms_id = $row[$this->col_prefix.'wms_id'];
        return kernel::single('channel_func')->getChannelNameById($wms_id);
    }


    /**
     * 获取_material_detail
     * @param mixed $inner_type inner_type
     * @param mixed $bm_id ID
     * @return mixed 返回结果
     */
    public function get_material_detail($inner_type,$bm_id){
        static $material_detail;
        if ($material_detail[$inner_type][$bm_id]) return $material_detail[$inner_type][$bm_id];
        if ($inner_type == '0'){
            $basicObj = app::get('material')->model('basic_material');
            $basic = $basicObj->dump(array('bm_id'=>$bm_id),'material_name');

            $material_detail[$inner_type][$bm_id] = $basic['material_name'];

        }else{
            $salesObj = app::get('material')->model('sales_material');
            $sales = $salesObj->dump(array('sm_id'=>$bm_id),'sales_material_name');
            $material_detail[$inner_type][$bm_id] = $sales['sales_material_name'];

        }

        return $material_detail[$inner_type][$bm_id];

    }
}