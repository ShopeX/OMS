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

class console_finder_products{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_stock = "库存详情";

    function __construct(){
        if($_GET['ctl'] != 'admin_stock'){
            unset($this->column_edit_stock);
            unset($this->detail_stock);
        }

        $this->_basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
    }

    /**
     * row_style
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function row_style($row){
        if($row[$this->col_prefix.'alert_store']==999){
            return 'list-warning';
        }
    }

    function detail_stock($product_id)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');

        // 保存安全库存数量
        if($_POST) {
            $oBranchPro = app::get('ome')->model('branch_product');
            $branch_id = $_POST['branch_id'];
            $product_ids = $_POST['product_id'];
            $safe_store = $_POST['safe_store'];
            $is_locked = $_POST['is_locked'];
            for($k=0;$k<sizeof($branch_id);$k++) {
                $oBranchPro -> update(
                    array('safe_store'=>$safe_store[$k],'is_locked'=>$is_locked[$k]),
                    array(
                        'product_id'=>$product_ids[$k],
                        'branch_id'=>$branch_id[$k]
                    )
                );
            }

            $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=0 WHERE bm_id='.$product_ids[$k-1];
            kernel::database()->exec($sql);

            /***
            $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=999 WHERE bm_id IN
                (
                    SELECT product_id FROM sdb_ome_branch_product
                    WHERE product_id='.$product_ids[$k-1].' AND safe_store>(store - store_freeze + arrive_store)
                )
            ';
            kernel::database()->exec($sql);
            ***/

            //冻结库存
            $sql         = "SELECT product_id, safe_store, store, store_freeze, arrive_store FROM sdb_ome_branch_product WHERE product_id=". $product_ids[$k-1];
            $tempData    = $oBranchPro->db->select($sql);
            if($tempData)
            {
                $bm_ids    = array();
                foreach ($tempData as $key => $val)
                {
                    //根据基础物料ID获取对应的冻结库存
                    $val['store_freeze']  = $this->_basicMStockFreezeLib->getMaterialStockFreeze($val['product_id']);
                    $safe_store    = $val['store'] - $val['store_freeze'] + $val['arrive_store'];

                    if($val['safe_store'] > $safe_store)
                    {
                        $bm_ids[]    = $val['product_id'];
                    }
                }

                if($bm_ids)
                {
                    $sql = "UPDATE sdb_material_basic_material_stock SET alert_store=999 WHERE bm_id IN(". implode(',', $bm_ids) .")";
                    kernel::database()->exec($sql);
                }
            }
        }
        $render = app::get('ome')->render();

        $render->pagedata['pro_detail'] = $basicMaterialSelect->products_detail($product_id);//基础物料_暂无安全库存
        return $render->fetch('admin/stock/detail_stock.html');
    }

    var $addon_cols='product_id,store_freeze,alert_store';
    var $column_store_freeze = '冻结库存';
    var $column_store_freeze_width='60';
    var $column_store_freeze_order = COLUMN_IN_TAIL;//排在列尾
    function column_store_freeze($row){

        //获取冻结库存
        $store_freeze    = $this->_basicMStockFreezeLib->getMaterialStockFreeze($row[$this->col_prefix.'product_id']);

        return $store_freeze;
    }

    /*var $column_edit_stock='操作';
    var $column_edit_stock_width='60';
    var $column_edit_stock_order = COLUMN_IN_HEAD;//排在列头
    function column_edit_stock($row){
        return '<a href="index.php?app=ome&ctl=admin_stock&act=edit&p[0]='.$row[$this->col_prefix.'product_id'].'">货位关联</a>';
    }*/

    var $column_arrive_store='在途库存';
    var $column_arrive_store_width='60';
    var $column_arrive_store_order = COLUMN_IN_TAIL;//排在列尾
    function column_arrive_store($row)
    {
        $libBranchProduct    = kernel::single('ome_branch_product');

        $product_id = $row[$this->col_prefix.'product_id'];

        $num = $libBranchProduct->countBranchProduct($product_id, 'arrive_store');

        return "<a href='index.php?app=console&ctl=admin_stock&act=show_arrive_store_list&arrive_store=".$num."&product_id=".$row['product_id']."'target=\"_blank\"\"><span>".(int)$num."</span></a>";
    }


}
