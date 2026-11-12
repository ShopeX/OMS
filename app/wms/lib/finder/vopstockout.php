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

class wms_finder_vopstockout{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols    ='status,confirm_status,o_status,dly_mode,carrier_code';
    var $_status       = array();
    var $_confirm_status       = array();
    
    function __construct()
    {
        $this->_status      = array(1=>'新建', '取消', '完成');
        $this->_confirm_status      = array(1=>'未审核', '已审核');
    }
    
    var $column_status = '单据状态';
    var $column_status_width = '100';
    var $column_status_order = 12;
    function column_status($row)
    {
        $stockLib    = kernel::single('purchase_purchase_stockout');
        $status    = $row[$this->col_prefix .'status'];
        
        return $stockLib->getBillStatus($status);
    }
    
    var $column_confirm_status = '审核状态';
    var $column_confirm_status_width = '100';
    var $column_confirm_status_order = 11;
    function column_confirm_status($row)
    {
        return $this->_confirm_status[$row[$this->col_prefix .'confirm_status']];
    }
    
    var $column_o_status = '出库状态';
    var $column_o_status_width = '100';
    var $column_o_status_order = 12;
    function column_o_status($row)
    {
        $stockLib    = kernel::single('purchase_purchase_stockout');
        $o_status    = $row[$this->col_prefix .'o_status'];
        
        return $stockLib->getStockoutStatus($o_status);
    }
    
    var $column_dly_mode = '配送方式';
    var $column_dly_mode_width = '100';
    var $column_dly_mode_order = 12;
    function column_dly_mode($row)
    {
        if(empty($row[$this->col_prefix .'dly_mode']))
        {
            return '';
        }
        
        $stockLib    = kernel::single('purchase_purchase_stockout');
        $dly_mode    = $row[$this->col_prefix .'dly_mode'];
        
        return $stockLib->getDlyMode($dly_mode);
    }
    
    var $column_carrier_code = '承运商';
    var $column_carrier_code_width = '100';
    var $column_carrier_code_order = 12;
    function column_carrier_code($row)
    {
        if(empty($row[$this->col_prefix .'carrier_code']))
        {
            return '';
        }
        
        $stockLib        = kernel::single('purchase_purchase_stockout');
        $carrier_code    = $row[$this->col_prefix .'carrier_code'];
        
        return $stockLib->getCarrierCode('', $carrier_code);
    }
    
    /***
     * 
    var $column_pick_no = '拣货单号';
    var $column_pick_no_width = 130;
    var $column_pick_no_order = 5;
    function column_pick_no($row)
    {
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        
        $stockout_id    = $row['stockout_id'];
        
        $sql    = "SELECT b.pick_no FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b 
                   ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
        $pickInfo    = $stockoutObj->db->selectrow($sql);
        
        return $pickInfo['pick_no'];
    }
    ***/
    
    var $column_branch_bn = '入库仓';
    var $column_branch_bn_width = '100';
    var $column_branch_bn_order = 21;
    function column_branch_bn($row)
    {
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        
        $stockout_id    = $row['stockout_id'];
        
        $sql    = "SELECT b.to_branch_bn FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b
                   ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
        $pickInfo    = $stockoutObj->db->selectrow($sql);
        
        if($pickInfo['to_branch_bn'])
        {
            $purchaseLib    = kernel::single('purchase_purchase_order');
            $branchInfo     = $purchaseLib->getWarehouse($pickInfo['to_branch_bn']);
            
            $pickInfo['to_branch_bn']    = $branchInfo['branch_name'];
        }
        
        return $pickInfo['to_branch_bn'];
    }
    
    var $column_edit  = '操作';
    var $column_edit_order = 2;
    var $column_edit_width = 100;
    function column_edit($row)
    {
        $stockout_id = $row['stockout_id'];
        $finder_id   = $_GET['_finder']['finder_id'];
        
        if($row[$this->col_prefix .'o_status'] != 1)
        {
            return '';
        }
        
        $button = <<<EOF
            <a class="lnk" href="index.php?app=wms&ctl=admin_vopstockout&act=confirm&p[0]=$stockout_id&_finder[finder_id]=$finder_id&finder_id=$finder_id" target="_blank">出库确认</a>
EOF;
        return '<span class="c-gray">'. $button .'</span>';
    }
    
    var $detail_base = '基础信息';
    /**
     * detail_base
     * @param mixed $stockout_id ID
     * @return mixed 返回值
     */
    public function detail_base($stockout_id)
    {
        $render = app::get('wms')->render();
        $stockLib    = kernel::single('purchase_purchase_stockout');
        
        //详情
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $row            = $stockoutObj->dump(array('stockout_id'=>$stockout_id), '*');
        
        $row['status']        = $stockLib->getBillStatus($row['status']);
        $row['create_time']   = date('Y-m-d H:i:s', $row['create_time']);
        
        //出库仓
        $branchObj    = app::get('ome')->model('branch');
        $branchInfo   = $branchObj->dump(array('branch_id'=>$row['branch_id']), 'name');
        $row['branch_name']    = $branchInfo['name'];
        
        //关联拣货单
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $sql            = "SELECT b.pick_no, b.to_branch_bn FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b
                           ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
        $pickList    = $stockoutObj->db->select($sql);
        
        $pickInfo    = array();
        foreach ($pickList as $key => $val)
        {
            $pickInfo['pick_no'][]    = $val['pick_no'];
            $pickInfo['to_branch_bn'] = $val['to_branch_bn'];
        }
        
        //格式化入库仓
        if($pickInfo['to_branch_bn'])
        {
            $purchaseLib    = kernel::single('purchase_purchase_order');
            $branchInfo     = $purchaseLib->getWarehouse($pickInfo['to_branch_bn']);
            
            $pickInfo['branch_name']    = $branchInfo['branch_name'];
        }
        $pickInfo['pick_no']    = implode(',', $pickInfo['pick_no']);
        
        $render->pagedata['pickInfo']    = $pickInfo;
        
        //出库状态
        $row['o_status']      = $stockLib->getStockoutStatus($row['o_status']);
        
        //配送方式
        if($row['dly_mode'])
        {
            $row['dly_mode']    = $stockLib->getDlyMode($row['dly_mode']);
        }
        
        //承运商
        if($row['carrier_code'])
        {
            $row['carrier_code']    = $stockLib->getCarrierCode('', $row['carrier_code']);
        }
        
        $render->pagedata['data']    = $row;
        return $render->fetch('admin/vop/stockout_detail.html');
    }
    
    var $detail_items = '出库单明细';
    /**
     * detail_items
     * @param mixed $stockout_id ID
     * @return mixed 返回值
     */
    public function detail_items($stockout_id)
    {
        $render = app::get('wms')->render();
        
        $pickObj    = app::get('purchase')->model('pick_bills');
        
        //详情
        $stockoutItemsObj    = app::get('purchase')->model('pick_stockout_bill_items');
        $dataList            = $stockoutItemsObj->getList('*', array('stockout_id'=>$stockout_id, 'is_del'=>'false'));
        
        foreach ($dataList as $key => $val)
        {
            $bill_id     = $val['bill_id'];
            $pickInfo    = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');
            
            $val['pick_no']    = $pickInfo['pick_no'];
            $val['po_bn']      = $pickInfo['po_bn'];
            
            $dataList[$key]    = $val;
        }
        
        $render->pagedata['dataList']    = $dataList;
        return $render->fetch('admin/vop/stockout_item.html');
    }
    
    var $detail_pack = '装箱信息';
    /**
     * detail_pack
     * @param mixed $stockout_id ID
     * @return mixed 返回值
     */
    public function detail_pack($stockout_id)
    {
        $render = app::get('wms')->render();
        
        $pickObj    = app::get('purchase')->model('pick_bills');
        
        //详情
        $stockItemObj = app::get('purchase')->model('pick_stockout_bill_items');
        
        $sql       = "SELECT a.stockout_item_id, a.bn, a.barcode, a.product_name, a.bill_id, b.box_id, b.box_no, b.num FROM sdb_purchase_pick_stockout_bill_items AS a 
                      LEFT JOIN sdb_purchase_pick_stockout_bill_item_boxs AS b ON a.stockout_item_id=b.stockout_item_id WHERE a.stockout_id=". $stockout_id;
        $dataList  = $stockItemObj->db->select($sql);
        
        foreach ($dataList as $key => $val)
        {
            $bill_id     = $val['bill_id'];
            $pickInfo    = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');
            
            $val['pick_no']    = $pickInfo['pick_no'];
            $val['po_bn']      = $pickInfo['po_bn'];
            
            $dataList[$key]    = $val;
        }
        
        $render->pagedata['dataList']    = $dataList;
        return $render->fetch('admin/vop/stockout_pack.html');
    }
    
    var $detail_logs = '操作日志';
    /**
     * detail_logs
     * @param mixed $stockout_id ID
     * @return mixed 返回值
     */
    public function detail_logs($stockout_id)
    {
        $render = app::get('wms')->render();
        
        $logObj = app::get('ome')->model('operation_log');
        
        $logs    = $logObj->read_log(array('obj_id'=>$stockout_id, 'obj_type'=>'pick_stockout_bills@purchase'), 0, -1);
        foreach($logs as $k=>$v)
        {
            $logs[$k]['operate_time']    = date('Y-m-d H:i:s', $v['operate_time']);
        }
        
        $render->pagedata['logs']    = $logs;
        return $render->fetch('admin/vop/logs.html');
    }
}