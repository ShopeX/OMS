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
class wms_finder_basic_material
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_stock = "库存详情";
    var $order_store_freeze = 0;
    var $branch_store_freeze = 0;
    
    function __construct()
    {
        if($_GET['ctl'] != 'admin_stock'){
            unset($this->column_edit_stock);
            unset($this->detail_stock);
        }
        
        $this->basicMStorageLifeLib = kernel::single('material_storagelife');
        $this->_basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
    }
    
    function detail_stock($bm_id)
    {
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
            
            $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=999 WHERE bm_id IN
                (
                    SELECT product_id FROM sdb_ome_branch_product 
                    WHERE product_id='.$product_ids[$k-1].' AND safe_store>(store - store_freeze + arrive_store)
                )';
            kernel::database()->exec($sql);
        }
        $render = app::get('ome')->render();
        
        $receiptMaterial    = kernel::single('wms_receipt_material');
        $pro_detail = $receiptMaterial->products_detail($bm_id);
        
        # [开启]保质期监控
        $get_material_conf       = $this->basicMStorageLifeLib->checkStorageLifeById($bm_id);
        $pro_detail['use_expire']    = ($get_material_conf ? 1 : 0);
        
        if($get_material_conf && $pro_detail['branch_product'])
        {
            $render     = app::get('wms')->render();#加载模板
            
            $branch_id_list    = array();
            foreach ($pro_detail['branch_product'] as $key => $val)
            {
                $branch_id_list[]    = $val['branch_id'];
            }
            
            #计算保质期批次_入库数理&&剩余数量
            $branch_in_num        = array();
            $branch_balance_num   = array();
            $branch_warn_num      = array();
            
            $get_storage_life    = $this->basicMStorageLifeLib->getStorageLifeBatchList($bm_id, $branch_id_list);
            if($get_storage_life)
            {
                foreach ($get_storage_life as $key => $val)
                {
                    $branch_in_num[$val['branch_id']]         += $val['in_num'];
                    $branch_balance_num[$val['branch_id']]    += $val['balance_num'];
                    
                    #预警库存
                    if($val['warn_date'] <= time())
                    {
                        $branch_warn_num[$val['branch_id']]    += $val['balance_num'];
                    }
                }
            }
            
            #赋值
            foreach ($pro_detail['branch_product'] as $key => $val)
            {
                $pro_detail['branch_product'][$key]['in_num']         = $branch_in_num[$val['branch_id']];
                $pro_detail['branch_product'][$key]['balance_num']    = intval($branch_balance_num[$val['branch_id']]);
                
                $pro_detail['branch_product'][$key]['warn_num']    = intval($branch_warn_num[$val['branch_id']]);
            }
        }
        
        $render->pagedata['pro_detail']    = $pro_detail;
        
        return $render->fetch('admin/stock/detail_stock.html');
    }
    
    var $addon_cols='alert_store';
    
    #总库存
    var $column_store = '总库存';
    var $column_store_width = 80;
    var $column_store_order = 80;
    function column_store($row)
    {
        $productObj = kernel::single('wms_receipt_material');
        $num = $productObj->countBranchProduct($row['bm_id'],'store');
        return (int)$num;
    }
    
    #总冻结库存
    var $column_store_freeze = '总冻结库存';
    var $column_store_freeze_width = 80;
    var $column_store_freeze_order = 90;
    function column_store_freeze($row)
    {
        //获取操作员管辖仓库
        $is_super   = kernel::single('desktop_user')->is_super();
        $branch_ids = kernel::single('wms_branch')->getBranchwmsByUser($is_super);
        
        //订单冻结库存
        $this->order_store_freeze = $this->_basicMStockFreezeLib->getShopFreezeByBmid($row['bm_id']);
        
        //仓库冻结库存(只获取操作员对应有权限的仓库)
        $this->branch_store_freeze = $this->_basicMStockFreezeLib->getBranchFreezeByBmid($row['bm_id'], $branch_ids);
        
        //总冻结库存 = 订单冻结库存 + 仓库冻结库存
        $store_freeze = $this->order_store_freeze + $this->branch_store_freeze;
        
        return $store_freeze;
    }
    
    #订单冻结库存
    var $column_order_store_freeze = '订单冻结库存';
    var $column_order_store_freeze_width = 90;
    var $column_order_store_freeze_order = 91;
    function column_order_store_freeze($row)
    {
        //$store_freeze    = $this->_basicMStockFreezeLib->getShopFreezeByBmid($row['bm_id']);
        
        return $this->order_store_freeze;
    }
    
    #仓库冻结库存
    var $column_branch_store_freeze = '仓库冻结库存';
    var $column_branch_store_freeze_width = 90;
    var $column_branch_store_freeze_order = 92;
    function column_branch_store_freeze($row)
    {
        return $this->branch_store_freeze;
    }
    
    #在途库存
    var $column_arrive_store='在途库存';
    var $column_arrive_store_width='60';
    var $column_arrive_store_order = 100;//排在列尾
    function column_arrive_store($row)
    {
        $productObj = kernel::single('wms_receipt_material');
        $num = $productObj->countBranchProduct($row['bm_id'],'arrive_store');
        return (int)$num;
    }
    
    #临期预警天数
    var $column_warn_day='临期预警天数';
    var $column_warn_day_width='100';
    var $column_warn_day_order = 100;//排在列尾
    function column_warn_day($row)
    {
        $basicInfo    = $this->basicMStorageLifeLib->getStorageLifeInfoById($row['bm_id']);
        
        return $basicInfo['warn_day'];
    }
    
    #临期库存
    var $column_balance_num='临期库存';
    var $column_balance_num_width='80';
    var $column_balance_num_order = 100;//排在列尾
    function column_balance_num($row)
    {
        $sql    = "SELECT sum(balance_num) as nums FROM sdb_material_basic_material_storage_life WHERE bm_id='". $row['bm_id'] ."'";
        $balance_num    = kernel::database()->selectrow($sql);
        
        return $balance_num['nums'];
    }
    
    #预警总数
    var $column_warn_num='预警总数';
    var $column_warn_num_width='80';
    var $column_warn_num_order = 100;//排在列尾
    function column_warn_num($row)
    {
        $sql    = "SELECT sum(balance_num) as nums FROM sdb_material_basic_material_storage_life WHERE bm_id='". $row['bm_id'] ."' AND warn_date<=" . time();
        $warn_num    = kernel::database()->selectrow($sql);
        
        return $warn_num['nums'];
    }
    
    #自动退出库存天数
    var $column_quit_day='自动退出库存天数';
    var $column_quit_day_width='100';
    var $column_quit_day_order = 100;//排在列尾
    function column_quit_day($row)
    {
        $basicInfo    = $this->basicMStorageLifeLib->getStorageLifeInfoById($row['bm_id']);
        
        return $basicInfo['quit_day'];
    }
    
    /*------------------------------------------------------ */
    //-- 显示行样式[粗体：highlight-row]
    //-- [加绿：list-even 加黄：selected 加红：list-warning]
    /*------------------------------------------------------ */
    public function row_style($row)
    {
        $has = app::get('ome')->model('branch_product')->dump(array('product_id'=>$row['bm_id'], 'filter_sql'=>'(safe_store+store_freeze)>(store+arrive_store)'), 'product_id');
        if(!empty($has)){
            return 'list-warning';
        }
        if($row[$this->col_prefix.'alert_store']==999){
            return 'list-warning';
        }
    }
}