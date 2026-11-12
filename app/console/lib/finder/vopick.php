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

class console_finder_vopick{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols    ='status,delivery_status,to_branch_bn,pick_no';
    
    var $column_to_branch_bn = '入库仓库';
    var $column_to_branch_bn_width = '100';
    var $column_to_branch_bn_order = 14;
    function column_to_branch_bn($row)
    {
        if($row[$this->col_prefix .'to_branch_bn'])
        {
            $purchaseLib    = kernel::single('purchase_purchase_order');
            $branchInfo     = $purchaseLib->getWarehouse($row[$this->col_prefix .'to_branch_bn']);
            
            $row[$this->col_prefix .'to_branch_bn']    = $branchInfo['branch_name'];
        }
        
        return $row[$this->col_prefix .'to_branch_bn'];
    }
    
    var $column_status = '审核状态';
    var $column_status_width = '100';
    var $column_status_order = 14;
    function column_status($row)
    {
        if ($row[$this->col_prefix .'status'] == '3') {
            return '已取消';
        }

        return ($row[$this->col_prefix .'status'] == 2 ? '已审核' : '未审核');
    }
    
    var $column_delivery_status = '发贷状态';
    var $column_delivery_status_width = '100';
    var $column_delivery_status_order = 14;
    function column_delivery_status($row)
    {
        return ($row[$this->col_prefix .'delivery_status'] == 2 ? '已发货' : '未发货');
    }
    
    var $column_edit  = '操作';
    var $column_edit_order = 2;
    var $column_edit_width = '150';
    function column_edit($row)
    {
        $bill_id     = $row['bill_id'];
        $finder_id   = $_GET['_finder']['finder_id'];
        $pick_no = $row[$this->col_prefix.'pick_no'];
        
        $button    = <<<EOF
            <a class="lnk" href="index.php?app=console&ctl=admin_vopick&act=recheck&p[0]=$bill_id&_finder[finder_id]=$finder_id&finder_id=$finder_id" target="_blank">补发</a>
EOF;
        
        if($row[$this->col_prefix .'status'] == '2')
        {
            if($row[$this->col_prefix .'delivery_status']==2 && ($row['pick_num'] > $row['branch_send_num']))
            {
                //检查是否存在未完成的出库单
                $stockoutObj = app::get('purchase')->model('pick_stockout_bills');
                
                $sql        = "SELECT a.stockout_id FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_stockout_bills AS b
                               ON a.stockout_id=b.stockout_id WHERE a.bill_id=". $bill_id ." AND (b.status=1 OR b.o_status=1)";
                $stockRow   = $stockoutObj->db->selectrow($sql);
                if(empty($stockRow))
                {
                    return '<span class="c-gray">'. $button .'</span>';
                }
            }
            
            return '';
        }
        
        $button = [];
        if ($row[$this->col_prefix .'status'] == '1'){
            $button[]    = <<<EOF
            <a class="lnk" href="index.php?app=console&ctl=admin_vopick&act=check&p[0]=$bill_id&_finder[finder_id]=$finder_id&finder_id=$finder_id" target="_blank">审核</a>
EOF;
            // 取消按钮
            $button[]    = <<<EOF
            <a class="c-red" href="javascript:if(confirm('确认取消单据【{$pick_no}】？')){W.page('index.php?app=console&ctl=admin_vopick&act=cancel&p[0]={$bill_id}&finder_id={$finder_id}');}">取消</a>
EOF;
        }
        
        return '<span>'. implode('&nbsp;&nbsp', $button) .'</span>';
    }
    
    var $detail_base = '拣货单详情';
    /**
     * detail_base
     * @param mixed $bill_id ID
     * @return mixed 返回值
     */
    public function detail_base($bill_id)
    {
        $render = app::get('console')->render();
        
        //详情
        $pickObj    = app::get('purchase')->model('pick_bills');
        $row        = $pickObj->dump(array('bill_id'=>$bill_id), '*');
        
        $row['status']           = $row['status'] == '2' ? '已审核' : '未审核';
        $row['delivery_status']  = $row['delivery_status'] == '2' ? '已发货' : '未发货';
        $row['create_time']      = date('Y-m-d H:i:s', $row['create_time']);
        
        if($row['to_branch_bn'])
        {
            $purchaseLib    = kernel::single('purchase_purchase_order');
            $branchInfo     = $purchaseLib->getWarehouse($row['to_branch_bn']);
            
            $row['branch_name']    = $branchInfo['branch_name'];
        }
        
        $render->pagedata['data']    = $row;
        return $render->fetch('admin/vop/vopick_detail.html');
    }
    
    var $detail_items = '拣货单明细';
    /**
     * detail_items
     * @param mixed $bill_id ID
     * @return mixed 返回值
     */
    public function detail_items($bill_id)
    {
        $render = app::get('console')->render();
        
        //详情
        $pickObj        = app::get('purchase')->model('pick_bills');
        $pickItemObj    = app::get('purchase')->model('pick_bill_items');
        
        if($bill_id){
            //拣货单信息
            $row = $pickObj->dump(array('bill_id'=>$bill_id), 'po_id, bill_id, status, delivery_status');
            //拣货单明细
            $dataList    = $pickItemObj->getList('*', array('bill_id'=>$bill_id));

            $billItemIdArr = array_column($dataList, 'bill_item_id');
            $labelList     = kernel::single('ome_bill_label')->getLabelFromOrder($billItemIdArr, 'pick_bill_item');
            foreach ($dataList as $k => $v){
                if ($labelList[$v['bill_item_id']]) {
                    $dataList[$k]['order_label'] = '';
                    foreach ($labelList[$v['bill_item_id']] as $lk => $lv) {
                        $dataList[$k]['order_label'] .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#FFF;'>%s</span>", $lv['label_name'], $lv['label_color'], $lv['label_name']);
                    }
                }
            }
            
            //计算已出库数量
            if($row['status'] == 2){
                $sql    = "SELECT a.stockout_id FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_stockout_bills AS b
                           ON a.stockout_id=b.stockout_id WHERE a.bill_id=". $bill_id ." AND b.confirm_status=2 AND b.o_status in(2,3)";
                $stockoutInfo    = $pickObj->db->select($sql);
                if($stockoutInfo){
                    $stockout_ids    = array();
                    foreach ($stockoutInfo as $key => $val) {
                        $stockout_ids[]    = $val['stockout_id'];
                    }
                    
                    foreach ($dataList as $key => $val) {
                        $sql    = "SELECT stockout_item_id FROM sdb_purchase_pick_stockout_bill_items WHERE stockout_id in(". implode(',', $stockout_ids) .")
                                   AND po_id=". $row['po_id'] ." AND bill_id=". $row['bill_id'] . " AND barcode='". $val['barcode'] ."'";
                        
                        $item_ids     = array();
                        $stockItem    = $pickObj->db->select($sql);
                        foreach ($stockItem as $key_i => $val) {
                            $item_ids[]    = $val['stockout_item_id'];
                        }
                        
                        if($item_ids){
                            $sql    = "SELECT sum(num) AS send_num FROM sdb_purchase_pick_stockout_bill_item_boxs 
                                       WHERE stockout_item_id in(". implode(',', $item_ids) .")";
                            $boxItem    = $pickObj->db->selectrow($sql);
                            
                            $dataList[$key]['send_num'] = $boxItem['send_num'];
                        }
                    }
                }
            }
            $render->pagedata['dataList'] = $dataList;

            $pickItemObj    = app::get('purchase')->model('pick_bill_check_items');
            $checkDataList  = $pickItemObj->getList('*', array('bill_id'=>$bill_id));
            foreach ($checkDataList as $k => $v) {
                if ($pickItemObj->order_label[$v['order_label']]) {
                    $checkDataList[$k]['order_label'] = $pickItemObj->order_label[$v['order_label']];
                }
            }
            $render->pagedata['checkDataList'] = $checkDataList;
        }

        $render->pagedata['is_stockout']  = ($row['status'] == 2 ? true : false);
        $render->pagedata['pick_info']    = $row;
        return $render->fetch('admin/vop/vopick_item.html');
    }
    
    var $detail_logs = '操作日志';
    /**
     * detail_logs
     * @param mixed $bill_id ID
     * @return mixed 返回值
     */
    public function detail_logs($bill_id)
    {
        $render = app::get('console')->render();
        
        $logObj = app::get('ome')->model('operation_log');
        
        $logs    = $logObj->read_log(array('obj_id'=>$bill_id, 'obj_type'=>'pick_bills@purchase'), 0, -1);
        foreach($logs as $k=>$v)
        {
            $logs[$k]['operate_time']    = date('Y-m-d H:i:s', $v['operate_time']);
        }
        
        $render->pagedata['logs']    = $logs;
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
        $bill_id = $row['bill_id'];
        
        //获取订单标记列表
        $labelList = $this->__getOrderLabel($list);
        $dataList = $labelList[$bill_id];
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
            $billIds[] = $val['bill_id'];
        }
        
        //获取订单标记列表
        $orderLabelObj = app::get('ome')->model('bill_label');
        $labelData = $orderLabelObj->getBIllLabelList($billIds, 'pick_bill');
        foreach($labelData as $val)
        {
            $bill_id = $val['bill_id'];
            
            $arrOrderLabel[$bill_id][] = array(
                    'label_id' => $val['label_id'],
                    'label_name' => $val['label_name'],
                    'label_color' => $val['label_color'],
            );
        }
        
        unset($billIds, $labelData);
        
        return $arrOrderLabel;
    }

}