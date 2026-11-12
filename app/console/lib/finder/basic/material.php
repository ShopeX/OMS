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
 * 基础物料查询数据finder
 *
 * @version 1.0
 */
class console_finder_basic_material
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_stock = "库存详情";
    private $bmStock;
    private $bmwarehousestock;
    private $bmo2ostock;
    private $_isPower = false;
    
    function __construct()
    {
        if($_GET['ctl'] != 'admin_stock'){
            unset($this->column_edit_stock);
            unset($this->detail_stock);
        }

        $this->_basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //重置冻结库存权限
        $this->_isPower = kernel::single('desktop_user')->has_permission('console_reset_freeze');
    }

    function detail_stock($product_id)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');

        // 保存安全库存数量
        if($_POST)
        {
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
                )';
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

        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
            $oBranch = app::get('ome')->model('branch');
            $branch_ids = $oBranch->getBranchByUser(true);
            $render->pagedata['branch_ids'] = $branch_ids;
        }
        $render->pagedata['pro_detail'] = $basicMaterialSelect->products_detail($product_id);
        return $render->fetch('admin/stock/detail_stock.html');
    }

    /*------------------------------------------------------ */
    //-- 格式化字段
    /*------------------------------------------------------ */
    #总库存
    var $column_store = '总库存';
    var $column_store_width = 80;
    var $column_store_order = 80;
    function column_store($row,$list)
    {
        $bmstocklist = $this->getbmStocklist($list);
        $bm_id = $row['bm_id'];

        $this->bmStock[$bm_id]['store'] = (int) $bmstocklist[$bm_id]['store'];
        $this->bmStock[$row['bm_id']]['store_freeze'] = (int)$bmstocklist[$bm_id]['store_freeze'];

        return $this->bmStock[$row['bm_id']]['store'];
    }
    
    #良品库存
    /*
    var $column_good_store = '良品库存';
    var $column_good_store_width = 80;
    var $column_good_store_order = 85;
    function column_good_store($row){
        $good_store = $this->_basicMStockFreezeLib->getMaterialGoodStore($row['bm_id']);
        return $good_store;
    }*/
     #良品库存
    var $column_good_store = '大仓良品库存';
    var $column_good_store_width = 95;
    var $column_good_store_order = 85;
    function column_good_store($row,$list){
        $bm_id = $row['bm_id'];
        $good_stores = $this->getMaterialWarehouseStorelist($list);
       
        return $good_stores[$bm_id]['total']? $good_stores[$bm_id]['total'] : 0;
    }

    //仓库冻结库存
    var $column_branch_store_freeze = '大仓冻结';
    var $column_branch_store_freeze_width = 70;
    var $column_branch_store_freeze_order = 5;
    function column_branch_store_freeze($row,$list)
    {
        $bm_id = $row['bm_id'];

        $store_freezes    = $this->getWareBranchFreezelist($list);
        
        return $store_freezes[$bm_id]['total']? $store_freezes[$bm_id]['total'] :0;
    }
    var $column_valid_store = '大仓可售库存';//公式：大仓良品库存 - 大仓冻结-店铺冻结
    var $column_valid_store_width = 95;
    var $column_valid_store_order = 7;
    function column_valid_store($row,$list){
        $bm_id = $row['bm_id'];
        $good_stores = $this->getMaterialWarehouseStorelist($list);

        $store_freezes    = $this->getWareBranchFreezelist($list);
        $shop_freezes    = $this->getShopFreezelist($list);
        $warehouse_store    = $good_stores[$bm_id]['total'];
        $branch_freeze    = $store_freezes[$bm_id]['total'];

        $shop_freeze = $shop_freezes[$bm_id]['total'];
        return $warehouse_store - $branch_freeze-$shop_freeze;
    }
     #良品库存
    var $column_o2o_store = '门店库存';
    var $column_o2o_store_width = 70;
    var $column_o2o_store_order = 9;
    function column_o2o_store($row,$list){
        $bm_id = $row['bm_id'];
        $good_stores = $this->getMaterialO2oStorelist($list);
        $good_store = $good_stores[$bm_id]['total'];
        return $good_store ? $good_store : 0;
    }
   
    
     //仓库冻结库存
    var $column_o2o_store_freeze = '门店冻结';
    var $column_o2o_store_freeze_width = 70;
    var $column_o2o_store_freeze_order = 11;
    function column_o2o_store_freeze($row,$list)
    {
        $shop_freezes    = $this->getStoreBranchFreezelist($list);
        $bm_id = $row['bm_id'];
        $store_freeze    = $shop_freezes[$bm_id]['total'];
        
        return $store_freeze ? $store_freeze : 0;
    }

    //总冻结库存
    var $column_store_freeze = '总冻结';
    var $column_store_freeze_width = 60;
    var $column_store_freeze_order = 90;
    function column_store_freeze($row,$list)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        
        //重置冻结库存
        $linkUrl = $this->bmStock[$row['bm_id']]['store_freeze'];
        if($this->_isPower){
            $linkUrl = "<a href='index.php?app=console&ctl=admin_stock_freeze&act=show_store_freeze_list&product_id=".$row['bm_id']."&finder_id=". $finder_id ."' target=\"_blank\"\"><span>". $this->bmStock[$row['bm_id']]['store_freeze'] ."</span></a>";
        }
        
        return $linkUrl;
    }

    //订单冻结库存
    var $column_order_store_freeze = '订单冻结';
    var $column_order_store_freeze_width = 70;
    var $column_order_store_freeze_order = 12;
    function column_order_store_freeze($row,$list)
    {
        $bm_id = $row['bm_id'];
        $shop_freezes    = $this->getShopFreezelist($list);
        $store_freeze    = $shop_freezes[$bm_id]['total'];
        
        return $store_freeze ? $store_freeze : 0;
    }
    
    
   
    #在途库存
    var $column_arrive_store='在途库存';
    var $column_arrive_store_width='60';
    var $column_arrive_store_order = 100;//排在列尾
    function column_arrive_store($row,$list)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $bm_id = $row['bm_id'];
        $arrive_storelist = $this->getMaterialArriveStorelist($list);
        $arrive_store = $arrive_storelist[$bm_id]['total'];
        
        //重置在途库存
        $linkUrl = $arrive_store;
        if($this->_isPower){
            $linkUrl = "<a href='index.php?app=console&ctl=admin_stock&act=show_arrive_store_list&arrive_store=".$arrive_store."&product_id=".$row['bm_id']."&finder_id=". $finder_id ."' target=\"_blank\"\"><span>".$arrive_store."</span></a>";
        }
        
        return $linkUrl;
    }

    /*------------------------------------------------------ */
    //-- 显示行样式[粗体：highlight-row]
    //-- [加绿：list-even 加黄：selected 加红：list-warning]
    /*------------------------------------------------------ */
    /**
     * row_style
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function row_style($row)
    {
        $has = app::get('ome')->model('branch_product')->dump(array('product_id'=>$row['bm_id'], 'filter_sql'=>'(safe_store+store_freeze)>(store+CAST(arrive_store AS SIGNED))'), 'product_id');
        if(!empty($has)){
            return 'list-warning';
        }
        if($row[$this->col_prefix.'alert_store']==999){
            return 'list-warning';
        }
    }

    /**
     * 获取bmStocklist
     * @param mixed $list list
     * @return mixed 返回结果
     */
    public function getbmStocklist($list){
        static $bmstocklist;
        if(isset($bmstocklist)) {
            return $bmstocklist;
        }
        $stockMdl = app::get('material')->model('basic_material_stock');
        $bm_ids = array_column($list,'bm_id');
        if($bm_ids){
            $stocks = $stockMdl->getlist('store,store_freeze,bm_id',array('bm_id'=>$bm_ids));
            $bmstocklist = $stocks ? array_column($stocks,null,'bm_id') : array();
            return $bmstocklist;
        }
        
    }

    /**
     * 获取MaterialWarehouseStorelist
     * @param mixed $list list
     * @return mixed 返回结果
     */
    public function getMaterialWarehouseStorelist($list){
        static $warehousestorelist;
        if(isset($warehousestorelist)){
            return $warehousestorelist;
        }
        $bm_ids =array_column($list,'bm_id');
        if(empty($bm_ids)){
            return false;
        }
        $filter_str = "product_id in (".implode(",", $bm_ids).") and store_id=0";
        $mdl_ome_branch = app::get('ome')->model('branch');
        $branchList = $mdl_ome_branch->db->select("SELECT branch_id FROM sdb_ome_branch WHERE (type='damaged' or (type='main' and online='false') ) ");
        if(!empty($branchList)){
            $damaged_branch_ids = array();
            foreach($branchList as $var_branch){
                $damaged_branch_ids[] = $var_branch["branch_id"];
            }
            $filter_str.= " and branch_id not in(".implode(",", $damaged_branch_ids).")";
        }
        
        $sql = "SELECT SUM(store) AS 'total',product_id FROM ".DB_PREFIX."ome_branch_product WHERE ".$filter_str.' group by product_id';

        $products = kernel::database()->select($sql);
        
        $warehousestorelist = $products ? array_column($products,null,'product_id') : array();
        return $warehousestorelist;
    }

    /**
     * 根据基础物料bm_id获取该物料仓库级的预占
     * 
     * @param Int $bm_id 基础物料ID
     * @param Array $branch_ids 仓库
     * @return number
     */
    public function getWareBranchFreezelist($list){
        static $warebranchfreezelist;
        if(isset($warebranchfreezelist)){
            return $warebranchfreezelist;
        }
        $bm_ids =array_column($list,'bm_id');
        if(empty($bm_ids)){
            return false;
        }
        $bill_type = $this->_basicMStockFreezeLib::__ORDER_YOU;
        $sql = "SELECT sum(num) as total,bm_id FROM sdb_material_basic_material_stock_freeze WHERE bm_id in (".implode(",", $bm_ids).") AND (obj_type=2 || (obj_type=1 AND bill_type=".$bill_type."))";
        
        $branch_ids = $this->_basicMStockFreezeLib->getWarehouseBranch();
        //仓库条件
        if($branch_ids && is_array($branch_ids)){
            $sql .= " AND branch_id IN(". implode(',', $branch_ids) .")";
        }
        $sql.=" group by bm_id";
        //仓库冻结总数
        $freezes = kernel::database()->select($sql);

        $warebranchfreezelist = $freezes ? array_column($freezes,null,'bm_id') : array();
        
        return $warebranchfreezelist;
    }

    //店铺冻结
    /**
     * 获取ShopFreezelist
     * @param mixed $list list
     * @return mixed 返回结果
     */
    public function getShopFreezelist($list){
        
        static $shopfreezelist;
        if(isset($shopfreezelist)){
            return $shopfreezelist;
        }
        $bm_ids =array_column($list,'bm_id');
        if(empty($bm_ids)){
            return false;
        }
        $bill_type = $this->_basicMStockFreezeLib::__ORDER_YOU;
        $result =kernel::database()->select("SELECT sum(num) as total,bm_id FROM sdb_material_basic_material_stock_freeze WHERE bm_id in (".implode(",", $bm_ids).") AND obj_type=1 AND bill_type<>".$bill_type." group by bm_id");
        $shopfreezelist = $result ? array_column($result,null,'bm_id') : array();
        return $shopfreezelist;
    }

    //o2o仓库列表
    /**
     * 获取MaterialO2oStorelist
     * @param mixed $list list
     * @return mixed 返回结果
     */
    public function getMaterialO2oStorelist($list){
        static $o2ostores;
        if(isset($o2ostores)){
            return $o2ostores;
        }
        $bm_ids =array_column($list,'bm_id');
        if(empty($bm_ids)){
            return false;
        }
        $filter_str = "product_id in(".implode(",", $bm_ids).") and store_id>0 ";
     
        $sql = "SELECT SUM(store) AS 'total',product_id FROM sdb_ome_branch_product WHERE ".$filter_str." group by product_id";

        $result = kernel::database()->select($sql);
        $o2ostores = $result ? array_column($result,null,'product_id') : array();
        return $o2ostores;
    }

    /**
     * 在途库存
     * 
     */
   
    public function getMaterialArriveStorelist($list){
        static $arrivestores;
        if(isset($arrivestores)){
            return $arrivestores;
        }
        $bm_ids =array_column($list,'bm_id');
        if(empty($bm_ids)){
            return false;
        }

        $sql = "SELECT SUM(arrive_store) AS 'total',product_id FROM sdb_ome_branch_product WHERE product_id in (".implode(",", $bm_ids).") group by product_id";
        $result = kernel::database()->select($sql);

        $arrivestores = $result ? array_column($result,null,'product_id'):array();

        return $arrivestores;
    }

    /**
     * 门店冻结
     * 
     */
    public function getStoreBranchFreezelist($list){
        
        static $storebranchfreezes;

        if(isset($storebranchfreezes)){
            return $storebranchfreezes;
        }
        $bm_ids =array_column($list,'bm_id');
        if(empty($bm_ids)){
            return false;
        }
        $sql = "SELECT sum(num) as total,bm_id FROM sdb_material_basic_material_stock_freeze WHERE bm_id in(".implode(",", $bm_ids).") AND obj_type=2";
        
        $branch_ids = $this->_basicMStockFreezeLib->getWarehouseBranch();
        //仓库条件
        if($branch_ids && is_array($branch_ids)){
            $sql .= " AND branch_id not IN(". implode(',', $branch_ids) .")";
        }
        $sql.=" group by bm_id";
        //仓库冻结总数
        $result = kernel::database()->select($sql);
        $storebranchfreezes = $result ? array_column($result,null,'bm_id'):array();
        return $storebranchfreezes;
    }

}
