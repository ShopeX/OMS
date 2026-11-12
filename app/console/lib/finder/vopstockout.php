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

class console_finder_vopstockout{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols    ='status,confirm_status,o_status,dly_mode,carrier_code,receive_status';
    var $_status       = array();
    var $_confirm_status       = array();
    
    function __construct()
    {
        $this->_confirm_status      = array(1=>'未审核', '已审核', '取消');
        
        if($_REQUEST['action'] == 'exportcnf' || $_REQUEST['action'] == 'to_export'){
            unset($this->column_edit);
        }
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
    var $column_o_status_order = 13;
    function column_o_status($row)
    {
        $stockLib    = kernel::single('purchase_purchase_stockout');
        $o_status    = $row[$this->col_prefix .'o_status'];
        
        return $stockLib->getStockoutStatus($o_status);
    }
    
    var $column_dly_mode = '配送方式';
    var $column_dly_mode_width = '100';
    var $column_dly_mode_order = 20;
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
    var $column_carrier_code_order = 21;
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
    
    var $column_branch_bn = '入库仓';
    var $column_branch_bn_width = '100';
    var $column_branch_bn_order = 19;
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
    var $column_edit_width = 150;
    function column_edit($row)
    {
        $stockout_id = $row['stockout_id'];
        $finder_id   = $_GET['_finder']['finder_id'];
        
        //部分出库&&单据未完成需要人工确认出库
        if(($row[$this->col_prefix .'receive_status'] & console_const::_FINISH_CODE) && $row[$this->col_prefix .'confirm_status'] == 2)
        {
            $button    = '';
            
            //差异查看
            $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
            $sql            = "SELECT stockout_item_id FROM sdb_purchase_pick_stockout_bill_items 
                               WHERE stockout_id=". $stockout_id ." AND (actual_num != num)";
            $items          = $stockoutObj->db->select($sql);
            if($items)
            {
                $button    .= '<a class="lnk" href="index.php?app=console&ctl=admin_vopstockout&act=difference&p[0]='. $stockout_id .'&finder_id='. $finder_id .'" target="_blank">差异查看</a> | ';
            }
            
            $button    .= '<a href="javascript:if(confirm(\'出库单号：'. $row['stockout_no'] .'确认出库？\'))';
            $button    .= '{W.page(\'index.php?app=console&ctl=admin_vopstockout&act=confirm&p[0]='. $stockout_id .'&finder_id='. $finder_id .'\', ';
            $button    .= '$extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">确认出库</a>';
            
            return '<span class="c-gray">'. $button .'</span>';
        }
        elseif($row[$this->col_prefix .'o_status'] == 2)
        {
            $button    = '';
            
            //差异查看
            $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
            $sql            = "SELECT stockout_item_id FROM sdb_purchase_pick_stockout_bill_items
                               WHERE stockout_id=". $stockout_id ." AND (actual_num != num)";
            $items          = $stockoutObj->db->select($sql);
            if($items)
            {
                $button    .= '<a class="lnk" href="index.php?app=console&ctl=admin_vopstockout&act=difference&p[0]='. $stockout_id .'&finder_id='. $finder_id .'" target="_blank">差异查看</a>';
            }
            
            return '<span class="c-gray">'. $button .'</span>';
        }
        elseif($row[$this->col_prefix .'confirm_status'] != 1)
        {
            return '';
        }
        
        $button_1 = <<<EOF
            <a class="lnk" href="index.php?app=console&ctl=admin_vopstockout&act=edit&p[0]=$stockout_id&_finder[finder_id]=$finder_id&finder_id=$finder_id" target="_blank">编辑</a>
EOF;
        $button_2 = <<<EOF
            <a class="lnk" href="index.php?app=console&ctl=admin_vopstockout&act=check&p[0]=$stockout_id&_finder[finder_id]=$finder_id&finder_id=$finder_id" target="_blank">审核</a>
EOF;
        
        $button_list    = array($button_1, $button_2);
        
        return '<span class="c-gray">'.implode(' |', $button_list).'</span>';
    }
    
    var $detail_base = '基础信息';
    /**
     * detail_base
     * @param mixed $stockout_id ID
     * @return mixed 返回值
     */
    public function detail_base($stockout_id)
    {
        $render = app::get('console')->render();
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
        $render = app::get('console')->render();
        
        if($stockout_id){

            $stockoutItemsObj    = app::get('purchase')->model('pick_stockout_bill_items');
            $pickObj             = app::get('purchase')->model('pick_bills');
            //出库单明细
            $dataList    = $stockoutItemsObj->getList('*', array('stockout_id'=>$stockout_id));

            $stockoutItemIdArr = array_column($dataList, 'stockout_item_id');
            $labelList         = kernel::single('ome_bill_label')->getLabelFromOrder($stockoutItemIdArr, 'pick_stockout_bill_item');
            foreach ($dataList as $key => $val)
            {
                $bill_id     = $val['bill_id'];
                $pickInfo    = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');
                
                $val['pick_no']    = $pickInfo['pick_no'];
                $val['po_bn']      = $pickInfo['po_bn'];
                
                //是否删除状态
                if($val['is_del'] == 'true')
                {
                    $val['item_del']    = 'item_del';
                }

                if ($labelList[$val['stockout_item_id']]) {
                    $val['order_label'] = '';
                    foreach ($labelList[$val['stockout_item_id']] as $lk => $lv) {
                        $val['order_label'] .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#FFF;'>%s</span>", $lv['label_name'], $lv['label_color'], $lv['label_name']);
                    }
                }
                
                $dataList[$key]    = $val;
            }
            $render->pagedata['dataList']    = $dataList;

            $pickItemObj      = app::get('purchase')->model('pick_bill_check_items');

            $billIdArr      = array_unique(array_column($dataList, 'bill_id'));
            $barcodeList    = array_unique(array_column($dataList, 'barcode'));
            $checkDataList  = $pickItemObj->getList('*', array('bill_id|in'=>$billIdArr));
            foreach ($checkDataList as $k => $v) {
                if (!in_array($v['barcode'], $barcodeList)) {
                    unset($checkDataList[$k]);
                    continue;
                }
                if ($pickItemObj->order_label[$v['order_label']]) {
                    $checkDataList[$k]['order_label'] = $pickItemObj->order_label[$v['order_label']];
                }
            }
            $render->pagedata['checkDataList']    = $checkDataList;
        }

        $render->pagedata['stockout_id']    = $stockout_id;
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
        $render = app::get('console')->render();
        
        $render->pagedata['stockout_id']    = $stockout_id;
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
        $render = app::get('console')->render();
        
        $logObj = app::get('ome')->model('operation_log');
        
        $logs    = $logObj->read_log(array('obj_id'=>$stockout_id, 'obj_type'=>'pick_stockout_bills@purchase'), 0, -1);
        foreach($logs as $k=>$v)
        {
            $logs[$k]['operate_time']    = date('Y-m-d H:i:s', $v['operate_time']);
        }
        
        $render->pagedata['logs']    = $logs;
        if ($this->vue_mode) { return []; }
        return $render->fetch('admin/vop/logs.html');
    }

    /**
     * 订单标记
     */
    public $column_order_label = '标记';
    public $column_order_label_width = 110;
    public $column_order_label_order = 30;
    /**
     * column_order_label
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_order_label($row, $list)
    {
        $stockout_id = $row['stockout_id'];
        
        //获取订单标记列表
        $labelList = $this->__getOrderLabel($list);
        $dataList = $labelList[$stockout_id];
        if(empty($dataList)){
            return '';
        }
        
        //默认只显示三条记录
        $str = [];
        $color_i = 0;
        foreach ($dataList as $key => $val)
        {
            $color_i++;
            
            // if($color_i > 3){
            //     continue;
            // }
            
            $str[] = sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#FFF;'>%s</span>", $val['label_name'], $val['label_color'], $val['label_name']);
        }
        $str = implode("<br>", $str);
        return $str;
    }

    /**
     * 订单标记列表
     * 
     * @param array $list
     * @return null
     */
    private function __getOrderLabel($list)
    {
        static $arrOrderLabel;
        
        if(isset($arrOrderLabel)){
            return $arrOrderLabel;
        }
        
        $billIds = array();
        foreach($list as $val) {
            $billIds[] = $val['stockout_id'];
        }

        //获取订单标记列表
        $orderLabelObj = app::get('ome')->model('bill_label');
        $labelData = $orderLabelObj->getBIllLabelList($billIds, 'pick_stockout_bill');
        foreach($labelData as $val)
        {
            $stockout_id = $val['bill_id'];
            
            $arrOrderLabel[$stockout_id][] = array(
                    'label_id' => $val['label_id'],
                    'label_name' => $val['label_name'],
                    'label_color' => $val['label_color'],
            );
        }
        
        unset($billIds, $labelData);
        
        return $arrOrderLabel;
    }
}