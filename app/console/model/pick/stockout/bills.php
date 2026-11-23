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
 * 唯品会JIT出库单mdl类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: vopurchase.php 2017-03-06 13:00
 */
class console_mdl_pick_stockout_bills extends dbeav_model{
    var $defaultOrder = array('stockout_id',' DESC');
    
    //是否有导出配置
    var $has_export_cnf = true;
    var $export_name = '唯品会JIT出库单';
    var $export_flag = false;
    
    public function table_name($real = false)
    {
        if($real){
           $table_name = 'sdb_purchase_pick_stockout_bills';
        }else{
           $table_name = 'pick_stockout_bills';
        }
        
        return $table_name;
    }
    
    public function get_schema(){
        return app::get('purchase')->model('pick_stockout_bills')->get_schema();
    }
    
    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'stockout_no'=>app::get('base')->_('出库单号'),
            'po_bn'=>app::get('base')->_('采购单号'),
            'pick_no'=>app::get('base')->_('拣货单号'),
        );
        return array_merge($childOptions,$parentOptions);
    }
    
    /**
     * 扩展搜索条件
     */
    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $where    = '';
        
        //搜索拣货单号和入库仓
        if($filter['pick_no'] || $filter['to_branch_bn'] || $filter['po_bn']){
            $pickObj    = app::get('purchase')->model('pick_bills');
            
            $pick_filter = array();
            if($filter['pick_no']){
                $pick_filter['pick_no'] = $filter['pick_no'];
            }
    
            if($filter['po_bn']){
                $pick_filter['po_bn'] = $filter['po_bn'];
            }
            
            if($filter['to_branch_bn']){
                $pick_filter['to_branch_bn'] = $filter['to_branch_bn'];
            }
    
            $stock_ids    = array(0);
    
            $pickInfo   = $pickObj->getList( 'bill_id',$pick_filter);
            if($pickInfo)
            {
                $str_bill_ids = '"' . implode('","', array_column($pickInfo,'bill_id')) . '"';
                $sql    = "SELECT a.stockout_id FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b 
                           ON a.bill_id=b.bill_id WHERE a.bill_id in ($str_bill_ids)";
                $temp_data    = $pickObj->db->select($sql);
                if($temp_data)
                {
                    foreach ($temp_data as $key => $val)
                    {
                        $stock_ids[]    = $val['stockout_id'];
                    }
                    
                }
            }
            $where    .= " AND stockout_id IN(". implode(',', $stock_ids) .")";
    
            unset($pickObj,$pickInfo,$sql,$pick_filter,$filter['pick_no'],$filter['to_branch_bn'],$temp_data,$filter['po_bn']);
        }
        
        //单据状态
        if(isset($filter['status'])){
            $filter['status'] = intval($filter['status']);
        }
        
        //审核状态
        if(isset($filter['confirm_status'])){
            $filter['confirm_status'] = intval($filter['confirm_status']);
        }
        
        //出库状态
        if(isset($filter['o_status'])){
            $filter['o_status'] = intval($filter['o_status']);
        }

        //订单标记
        if($filter['order_label']){
            $ordLabelObj = app::get('ome')->model('bill_label');
            $tempData = $ordLabelObj->getList('bill_id', array('label_id'=>$filter['order_label'], 'bill_type'=>'pick_stockout_bill'));
            if($tempData){
                $orderId = array();
                foreach ($tempData as $tempKey => $tempVal)
                {
                    $temp_order_id = $tempVal['bill_id'];
                    
                    $orderId[$temp_order_id] = $temp_order_id;
                }
                
                $where .= ' AND stockout_id IN ('. implode(',', $orderId) .')';
            }else{
                $where .= ' AND stockout_id=-1';
            }
            
            unset($filter['order_label'], $tempData);
        }
        
        return parent::_filter($filter,$tableAlias,$baseWhere) . $where;
    }
    
    /**
     * 新增导出列表扩展字段
     */
    function export_extra_cols(){
        return array(
                'column_pick_no' => array('label'=>'拣货单号','width'=>'100','func_suffix'=>'pick_no'),
        );
    }
    
    /**
     * 出库单关联的拣货单号
     */
    function export_extra_pick_no($rows){
        return kernel::single('ome_exportextracolumn_console_pickno')->process($rows);
    }
    
    /**
     * 删除导出时不需要的字段
     */
    public function disabled_export_cols(&$cols){
        unset($cols['column_edit']);
    }
    
    function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }
    
    function io_title($filter=null, $ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['bills'] = array(
                        '*:出库单号' => 'stockout_no',
                        '*:出库仓' => 'branch_name',
                        '*:入库仓' => 'warehouse',
                        '*:拣货数量' => 'pick_num',
                        '*:单据状态' => 'status',
                        '*:审核状态' => 'confirm_status',
                        '*:出库状态' => 'o_status',
                        '*:承运商' => 'carrier_code',
                        '*:运单号' => 'delivery_no',
                        '*:入库单号' => 'storage_no',
                        '*:送货批次时间' => 'delivery_time',
                        '*:要求到货时间' => 'arrival_time',
                        '*:配送方式' => 'dly_mode',
                        '*:创建时间' => 'create_time',
                        '*:最后更新时间' => 'last_modified',
                );
                
                $this->oSchema['csv']['box'] = array(
                        '*:出库单号' => 'stockout_no',
                        '*:采购单号' => 'po_bn',
                        '*:拣货单号' => 'pick_no',
                        '*:箱号' => 'box_no',
                        '*:货号' => 'bn',
                        '*:条形码' => 'barcode',
                        '*:货品名称' => 'product_name',
                        '*:申请数量' => 'num',
                        '*:装箱数量' =>'actual_num',//实际出库数量
                );
            break;
        }
        
        $this->ioTitle[$ioType]['bills'] = array_keys( $this->oSchema[$ioType]['bills'] );
        $this->ioTitle[$ioType]['box'] = array_keys( $this->oSchema[$ioType]['box'] );
        
        return $this->ioTitle[$ioType][$filter];
    }
    
    /*
     * 本地导出模式
     */
    function fgetlist_csv(&$data,$filter,$offset,$exportType = 1)
    {
        @ini_set('memory_limit','1024M'); set_time_limit(0);
        
        $branchObj    = app::get('ome')->model('branch');
        $pickObj      = app::get('purchase')->model('pick_bills');
        $stockLib       = kernel::single('purchase_purchase_stockout');
        $purchaseLib    = kernel::single('purchase_purchase_order');
        
        //标题
        if(!$data['title']['bills']){
            $title = array();
            foreach( $this->io_title('bills') as $k => $v ){
                $title[] = $v;
            }
            $data['title']['bills'] = '"'.implode('","',$title).'"';
        }
        
        if(!$data['title']['box']){
            $title = array();
            foreach( $this->io_title('box') as $k => $v ){
                $title[] = $v;
            }
            $data['title']['box'] = '"'.implode('","',$title).'"';
        }
        
        //数据
        $limit = 100;
        if( !$dataList = $this->getList('*', array('stockout_id'=>$filter['stockout_id']), $offset*$limit, $limit) )return false;
        if($dataList)
        {
            foreach ($dataList as $key => $ordVal)
            {
                $stockout_id    = $ordVal['stockout_id'];
                
                //出库仓
                $branchInfo   = $branchObj->dump(array('branch_id'=>$ordVal['branch_id']), 'name');
                $ordVal['branch_name']    = $branchInfo['name'];
                
                //单据状态
                $ordVal['status']    = $stockLib->getBillStatus($ordVal['status']);
                
                //审核状态
                $ordVal['confirm_status']    = ($ordVal['confirm_status'] == '2' ? '已审核' : '未审核');
                
                //出库状态
                $ordVal['o_status']    = $stockLib->getStockoutStatus($ordVal['o_status']);
                
                //承运商
                $ordVal['carrier_code']    = $stockLib->getCarrierCode('', $ordVal['carrier_code']);
                
                //配送方式
                $ordVal['dly_mode']    = $stockLib->getDlyMode($ordVal['dly_mode']);
                
                //入库仓
                $sql    = "SELECT b.to_branch_bn FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b
                           ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
                $pickInfo    = $this->db->selectrow($sql);
                if($pickInfo['to_branch_bn'])
                {
                    $branchInfo     = $purchaseLib->getWarehouse($pickInfo['to_branch_bn']);
                    $ordVal['warehouse']    = $branchInfo['branch_name'];
                }
                
                //创建时间
                $ordVal['create_time']    = date('Y-m-d H:i:s', $ordVal['create_time']);
                
                //最后更新时间
                $ordVal['last_modified']    = date('Y-m-d H:i:s', $ordVal['last_modified']);
                
                //装箱明细
                $sql    = "SELECT a.*, b.bill_id, b.bn, b.barcode, b.product_name FROM sdb_purchase_pick_stockout_bill_item_boxs AS a
                           LEFT JOIN sdb_purchase_pick_stockout_bill_items AS b ON a.stockout_item_id=b.stockout_item_id WHERE a.stockout_id=". $stockout_id;
                $boxList    = $this->db->select($sql);
                if($boxList){
                    foreach ($boxList as $boxKey => $boxVal)
                    {
                        //出库单号
                        $boxVal['stockout_no']    = $ordVal['stockout_no'];
                        
                        //拣货单信息
                        $pickInfo    = $pickObj->dump(array('bill_id'=>$boxVal['bill_id']), 'pick_no, po_bn');
                        $boxVal['po_bn']    = $pickInfo['po_bn'];
                        $boxVal['pick_no']  = $pickInfo['pick_no'];
                        
                        $boxObjRow    = array();
                        foreach( $this->oSchema['csv']['box'] as $k => $v ){
                            $boxObjRow[$k] = utils::apath($boxVal, explode('/',$v));
                        }
                        $data['content']['box'][] = '"'.implode('","', $boxObjRow).'"';
                    }
                }
                
                //订单信息
                $orderRow = array();
                foreach( $this->oSchema['csv']['bills'] as $k => $v ){
                    $orderRow[$k] = utils::apath($ordVal, explode('/',$v));
                }
                $data['content']['bills'][] = '"'.implode('","',$orderRow).'"';
            }
        }
        
        return true;
    }
    
    /*
     * 导出csv数据
     */
    function export_csv($data,$exportType = 1 ){
        $output = array();
        
        foreach( $data['title'] as $k => $val ){
            $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
        }
        
        return implode("\n",$output);
    }
    
    /**
     * 导出出库单装箱信息
     * 
     * @return array
     */
    public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false)
    {
        $pickObj    = app::get('purchase')->model('pick_bills');
        
        //装箱标题
        $boxTitle    = $this->io_title('box');
        
        //出库单
        $stockData   = array();
        $tempData    = $this->getList('stockout_id,stockout_no', array('stockout_id'=>$filter['stockout_id']));
        if($tempData)
        {
            foreach ($tempData as $key => $val)
            {
                $stockData[$val['stockout_id']]    = $val['stockout_no'];
            }
        }
        
        //装箱信息
        $data    = array();
        $row_num = 1;
        foreach($filter['stockout_id'] as $stockout_id)
        {
            $sql    = "SELECT b.bill_id, b.bn, b.barcode, b.product_name ,b.stockout_item_id,b.stockout_id,b.bill_id,b.actual_num,b.num FROM  sdb_purchase_pick_stockout_bill_items AS b  WHERE b.stockout_id=". $stockout_id;
            $boxList    = $this->db->select($sql);
            if($boxList){
                foreach ($boxList as $boxKey => $boxVal)
                {
                    //出库单号
                    $boxVal['stockout_no']    = $stockData[$boxVal['stockout_id']];
                    
                    //拣货单信息
                    $pickInfo    = $pickObj->dump(array('bill_id'=>$boxVal['bill_id']), 'pick_no, po_bn');
                    $boxVal['po_bn']    = $pickInfo['po_bn'];
                    $boxVal['pick_no']  = $pickInfo['pick_no'];
                    
                    $boxObjRow    = array();
                    foreach( $this->oSchema['csv']['box'] as $k => $v ){
                        $boxObjRow[$k] = mb_convert_encoding(utils::apath($boxVal, explode('/',$v)), 'GBK', 'UTF-8');
                    }
                    
                    $data[$row_num]    = implode(',', $boxObjRow);
                    $row_num++;
                }
            }
        }
        
        //装箱标题处理
        if($data && $has_title){
            foreach ($boxTitle as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }
            
            $data[0] = implode(',', $title);
        }
        ksort($data);
        
        return $data;
    }
}
?>
