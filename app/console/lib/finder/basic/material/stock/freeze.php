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
 * 仓库预占流水记录
 */

class console_finder_basic_material_stock_freeze
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'bm_id,obj_type,bmsq_id,obj_id,obj_bn,branch_id,shop_id,bill_type';//调用字段
    
    var $_type_list   = array();
    var $_obj_type    = array();
    function __construct()
    {
        $this->_basicMaterialObj = app::get('material')->model('basic_material');
        $this->_shopObj      = app::get('ome')->model('shop');
        $this->_branchObj    = app::get('ome')->model('branch');
        
        $this->_obj_type   = array(1=>'订单', 2=>'仓库', 3=>'售后', 15=>'唯品会销售订单');
        
        $stockFreezeObj    = app::get('console')->model('basic_material_stock_freeze');
        $this->_type_list  = $stockFreezeObj->get_type();
    }

    var $column_edit  = '操作';
    var $column_edit_order = 1;
    var $column_edit_width = '60';
    function column_edit($row, $list){
        $bmsf_id = $row["bmsf_id"];
        $finder_id = $_GET['_finder']['finder_id'];
        $obj_bn = $row[$this->col_prefix.'obj_bn'];
        $bmBn = $this->_get_material_bn($row, $list);

        $unfreezeButton = sprintf('<a href="javascript:if (confirm(\'确定要释放%s的商品【%s】预占吗？\')){W.page(\'index.php?app=console&ctl=admin_stock_freeze&act=single_unfreeze&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">释放</a>', $obj_bn, $bmBn['material_bn'], $bmsf_id, $finder_id);

        // 整单释放按钮（仅对订单类型显示）
        $orderUnfreezeButton = sprintf('<a href="javascript:if (confirm(\'确定要释放%s所有的商品预占吗？\')){W.page(\'index.php?app=console&ctl=admin_stock_freeze&act=single_order_unfreeze&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">整单释放</a>', $obj_bn, $row[$this->col_prefix.'obj_id'], $finder_id);

        static $allow_unfreeze;
        static $allow_order_unfreeze;
        if(isset($allow_unfreeze)){
            $buttons = [];
            // 如果订单允许整单释放，显示整单释放按钮
            if (isset($allow_order_unfreeze[$row[$this->col_prefix.'obj_id']])) {
                $buttons[] = $orderUnfreezeButton;
            }
            // 对于允许释放的记录，显示单个释放按钮
            if ($allow_unfreeze[$bmsf_id]) {
                $buttons[] = $unfreezeButton;
            }
            return implode('&nbsp;&nbsp;', $buttons);
        }
        $allow_unfreeze = [];
        $allow_order_unfreeze = [];
        $need_select = [];

        $shopList = kernel::single('omeanalysts_shop')->getShopList();
        $shopIds = $shopList['360buy'] ?? [];

        foreach ($list as $v) {
            // 京东店铺的售后单和售后申请单
            if (in_array($v[$this->col_prefix.'bill_type'], [material_basic_material_stock_freeze::__RESHIP,material_basic_material_stock_freeze::__RETURN]) && $v['num']>0) {

                if (in_array($v[$this->col_prefix.'shop_id'], $shopIds)) {

                    $allow_unfreeze[$v['bmsf_id']] = $v;

                } elseif (!$v[$this->col_prefix.'shop_id']) {
                    // shop_id为0，需要查询原始单据，判断是否为京东店铺
                    $need_select[$v[$this->col_prefix.'bill_type']][] = $v;
                }
            } elseif ($v[$this->col_prefix.'bill_type'] == 0 && $v[$this->col_prefix.'obj_type'] == 1 && $v['num']>0) {
                // 收集需要查询的订单ID (bill_type=0 表示订单冻结，此类型未在material_basic_material_stock_freeze中定义常量)
                $need_select[0][] = $v;
            }
        }

        // 批量查询已取消或已作废的订单
        if ($need_select[0]) {
            $orderObj = app::get('ome')->model('orders');
            $orderIds = array_column($need_select[0], $this->col_prefix.'obj_id');
            $orderList = $orderObj->getList('order_id,process_status,status,order_bn', ['order_id|in' => $orderIds]);
            $orderList = array_column($orderList, null, 'order_id');
            
            foreach ($need_select[0] as $v) {
                $orderId = $v[$this->col_prefix.'obj_id'];
                if (isset($orderList[$orderId]) 
                    && ($orderList[$orderId]['process_status'] == 'cancel' || $orderList[$orderId]['status'] == 'dead')
                    && $orderList[$orderId]['order_bn'] == $v[$this->col_prefix.'obj_bn']
                ) {
                    // 记录可以整单释放的订单ID
                    $allow_order_unfreeze[$orderId] = true;
                }
            }
        }
        // 售后业务 sdb_ome_reship
        if ($shopIds && $need_select[material_basic_material_stock_freeze::__RESHIP]) {
            $reshipMdl = app::get('ome')->model('reship');
            $reshipIds = array_column($need_select[material_basic_material_stock_freeze::__RESHIP], $this->col_prefix.'obj_id');
            $reshipList = $reshipMdl->getList('reship_id,shop_id,shop_type', ['reship_id|in' => $reshipIds]);
            $reshipList = array_column($reshipList, null, 'reship_id');
            foreach ($need_select[material_basic_material_stock_freeze::__RESHIP] as $v) {
                if ($reshipList[$v[$this->col_prefix.'obj_id']]['shop_type'] == '360buy' || in_array($reshipList[$v[$this->col_prefix.'obj_id']]['shop_id'], $shopIds)) {
                    $allow_unfreeze[$v['bmsf_id']] = $v;
                }
            }
        }
        // 售后申请单 sdb_ome_return_product
        if ($shopIds && $need_select[material_basic_material_stock_freeze::__RETURN]) {
            $returnProductMdl = app::get('ome')->model('return_product');
            $returnIds = array_column($need_select[material_basic_material_stock_freeze::__RETURN], $this->col_prefix.'obj_id');
            $returnProductList = $returnProductMdl->getList('return_id,shop_id,shop_type', ['return_id'=>$returnIds]);
            $returnProductList = array_column($returnProductList, null,'return_id');
            foreach ($need_select[material_basic_material_stock_freeze::__RETURN] as $v) {
                if ($returnProductList[$v[$this->col_prefix.'obj_id']]['shop_type'] == '360buy' || in_array($reshipList[$v[$this->col_prefix.'obj_id']]['shop_id'], $shopIds)) {
                    $allow_unfreeze[$v['bmsf_id']] = $v;
                }
            }
        }
        if ($allow_unfreeze[$bmsf_id]) {
            return $unfreezeButton;
        }
        if ($allow_order_unfreeze[$row[$this->col_prefix.'obj_id']]) {
            return $orderUnfreezeButton;
        }
        return '';
    }
    
    var $column_bm_id = '基础物料编号';
    var $column_bm_id_width = 150;
    var $column_bm_id_order = 5;
    function column_bm_id($row, $list)
    {
        $bmBn = $this->_get_material_bn($row, $list);
        return $bmBn['material_bn'];
    }

    private function _get_material_bn($row, $list){
        static $bmBnList;
        if (isset($bmBnList)) {
            return $bmBnList[$row[$this->col_prefix.'bm_id']];
        }
        $bmBnList = [];
        $bmIds = array_column($list, $this->col_prefix.'bm_id');
        $bmBnList = $this->_basicMaterialObj->getList('bm_id,material_bn', ['bm_id|in' => $bmIds]);
        $bmBnList = array_column($bmBnList, null, 'bm_id');
        return $bmBnList[$row[$this->col_prefix.'bm_id']];
    }
    
    var $column_obj_type = '对象类型';
    var $column_obj_type_width = 120;
    var $column_obj_type_order = 10;
    function column_obj_type($row)
    {
        $obj_type    = $row[$this->col_prefix .'obj_type'];
        
        return $this->_obj_type[$obj_type];
    }
    
    var $column_bill_type = '业务类型';
    var $column_bill_type_width = 120;
    var $column_bill_type_order = 20;
    function column_bill_type($row)
    {
        $obj_type   = $row[$this->col_prefix .'obj_type'];
        $bill_type    = abs($row[$this->col_prefix.'bill_type']);
        
        return $this->_type_list[$obj_type][$bill_type];
    }
    
    var $column_obj_id = '单据号';
    var $column_obj_id_width = 180;
    var $column_obj_id_order = 40;
    function column_obj_id($row)
    {
        $obj_id    = $row[$this->col_prefix .'obj_id'];
        $obj_bn    = $row[$this->col_prefix .'obj_bn'];
        if($obj_bn) {
            return $obj_bn;
        }
        
        //订单类型
        if($row[$this->col_prefix .'obj_type'] == 1)
        {
            $orderObj     = app::get('ome')->model('orders');
            $orderInfo    = $orderObj->dump(array('order_id'=>$obj_id), 'order_bn');
            $obj_bn       = $orderInfo['order_bn'];
        }
        //仓库类型
        elseif($row[$this->col_prefix .'obj_type'] == 2)
        {
            switch ($row[$this->col_prefix .'bill_type'])
            {
                //发货单
                case 1:
                    $deliveryObj    = app::get('ome')->model('delivery');
                    $deliveryInfo   = $deliveryObj->dump(array('delivery_id'=>$obj_id), 'delivery_bn');
                    $obj_bn         = $deliveryInfo['delivery_bn'];
                    break;
                //售后单
                case 2:
                    $oReship      = app::get('ome')->model('reship');
                    $reshipInfo   = $oReship->dump(array('reship_id'=>$obj_id), 'reship_bn');
                    $obj_bn       = $reshipInfo['reship_bn'];
                    break;
                //采购退货
                case 3:
                    $returnObj    = app::get('purchase')->model('returned_purchase');
                    $returnInfo   = $returnObj->dump(array('rp_id'=>$obj_id), 'rp_bn');
                    $obj_bn       = $returnInfo['rp_bn'];
                    break;
                //调拨出库
                case 4:
                    $isoObj    = app::get('taoguaniostockorder')->model("iso");
                    $isoInfo   = $isoObj->dump(array('iso_id'=>$obj_id), 'iso_bn');
                    $obj_bn    = $isoInfo['iso_bn'];
                    break;
                //库内转储
                case 5:
                    $oAppro    = app::get('console')->model('stockdump');
                    $tempInfo  = $oAppro->dump(array('stockdump_id'=>$obj_id), 'stockdump_bn');
                    $obj_bn    = $tempInfo['stockdump_bn'];
                    break;
                //唯品会出库
                case 6:
                    $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
                    $tempInfo       = $stockoutObj->dump(array('stockout_id'=>$obj_id), 'stockout_no');
                    $obj_bn         = $tempInfo['stockout_no'];
                    break;
                //人工库存预占
                case 7:
                    $artFreezeObj = app::get('material')->model('basic_material_stock_artificial_freeze');
                    $artFreezeInfo = $artFreezeObj->dump(array('bmsaf_id'=>$obj_id), 'bmsaf_id,original_bn');
                    
                    $obj_bn = ($artFreezeInfo['original_bn'] ? $artFreezeInfo['original_bn'] : $obj_id);
                    break;
            }
        }
        
        return $obj_bn;
    }
    
    var $column_branch_id = '仓库';
    var $column_branch_id_width = 120;
    var $column_branch_id_order = 50;
    function column_branch_id($row)
    {
        $branch_name    = '';
        
        if($row[$this->col_prefix .'branch_id'])
        {
            $tempData    = $this->_branchObj->dump(array('branch_id'=>$row[$this->col_prefix .'branch_id']), 'name');
            $branch_name = $tempData['name'];
        }
        
        return $branch_name;
    }
    
    var $column_shop_id = '店铺';
    var $column_shop_id_width = 200;
    var $column_shop_id_order = 60;
    function column_shop_id($row)
    {
        $shop_name    = '';
        
        if($row[$this->col_prefix .'shop_id'])
        {
            $tempData    = $this->_shopObj->dump(array('shop_id'=>$row[$this->col_prefix .'shop_id']), 'name');
            $shop_name   = $tempData['name'];
        }
        
        return $shop_name;
    }
}
