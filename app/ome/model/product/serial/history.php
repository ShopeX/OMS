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

class ome_mdl_product_serial_history extends dbeav_model{

    //是否有导出配置
    var $has_export_cnf = true;
    var $export_name = '唯一码历史';
    
    function modifier_act_type($row){
        if($row == '1'){
            return "出库";
        }elseif($row == '2'){
            return '退入';
        }elseif($row == '3'){
            return '导入';
        }else{
            return '未知';
        }
    }

    function modifier_bill_type($row){
        if($row == '1'){
            return "发货单";
        }elseif($row == '2'){
            return '退货单';
        }elseif($row == '3'){
            return '订单';
        }else{
            return '未知';
        }
    }
    
    function export_extra_cols(){
        return array(
                'column_ship_name' => array('label'=>'收货人','width'=>'100','func_suffix'=>'ship_name'),
                'column_ship_area' => array('label'=>'收货区域','width'=>'100','func_suffix'=>'ship_area'),
                'column_ship_addr' => array('label'=>'收货地址','width'=>'100','func_suffix'=>'ship_addr'),
                'column_ship_tel' => array('label'=>'电话','width'=>'100','func_suffix'=>'ship_tel'),
                'column_ship_mobile' => array('label'=>'手机','width'=>'100','func_suffix'=>'ship_mobile'),
        );
    }
    
    //获取扩展字段 收货人
    function export_extra_ship_name($rows){
        return kernel::single('ome_exportextracolumn_product_serial_history_shipname')->process($rows);
    }
    
    //获取扩展字段 收货区域
    function export_extra_ship_area($rows){
        return kernel::single('ome_exportextracolumn_product_serial_history_shiparea')->process($rows);
    }
    
    //获取扩展字段 收货地址
    function export_extra_ship_addr($rows){
        return kernel::single('ome_exportextracolumn_product_serial_history_shipaddr')->process($rows);
    }
    //获取扩展字段 电话
    function export_extra_ship_tel($rows){
        return kernel::single('ome_exportextracolumn_product_serial_history_shiptel')->process($rows);
    }
    //获取扩展字段 手机
    function export_extra_ship_mobile($rows){
        return kernel::single('ome_exportextracolumn_product_serial_history_shipmobile')->process($rows);
    }

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
           
            'order_bn'=>app::get('base')->_('订单号'),
           
        );
        return array_merge($childOptions,$parentOptions);
    }

    function _filter($filter,$tableAlias=null,$baseWhere=null){
        $where = 1;
        if(isset($filter['order_bn'])){

            $delivery_bns = $this->get_delivery_list($filter['order_bn']);
            $reship_bns = $this->get_reship_list($filter['order_bn']);
            $bill_list = array();
            $bill_list[] = $filter['order_bn'];
            if ($delivery_bns){
                $bill_list = array_merge($bill_list,$delivery_bns);
            }
            if ($reship_bns){
                $bill_list = array_merge($bill_list,$reship_bns);
            }
            if ($bill_list){
                $where.=" AND bill_no in ('".implode('\',\'',$bill_list)."')";
            }

            unset($filter['order_bn']);
        }


        return $where ." AND ".parent::_filter($filter,$tableAlias,$baseWhere);
    }

    /**
     * 获取_delivery_list
     * @param mixed $order_bn order_bn
     * @return mixed 返回结果
     */
    public function get_delivery_list($order_bn){

        static $delivery_list ;
        if ($delivery_list[$order_bn]) return $delivery_list[$order_bn];
        $order_id = $this->get_order_detail($order_bn);
        $sql = "SELECT d.delivery_bn
                FROM sdb_ome_delivery_order as deo
                LEFT JOIN sdb_ome_delivery AS d ON deo.delivery_id = d.delivery_id
                WHERE deo.order_id={$order_id}
                AND (d.parent_id=0 OR d.is_bind='true')";
        $delivery = kernel::database()->select($sql);
        if ($delivery){
            $delivery_list[$order_bn] = array_map('current', $delivery);
            return $delivery_list[$order_bn];
        }
    }


    /**
     * 获取_reship_list
     * @param mixed $order_bn order_bn
     * @return mixed 返回结果
     */
    public function get_reship_list($order_bn){
        static $reship_list;
        if ($reship_list[$order_bn]) return $reship_list[$order_bn];
        $order_id = $this->get_order_detail($order_bn);

        $sql = "SELECT reship_bn FROM sdb_ome_reship WHERE order_id=".$order_id."";
        $reship = $this->db->select($sql);
        if ($reship){
            $reship_list[$order_bn] = array_map('current',$reship);
            return $reship_list[$order_bn];
        }


    }

    /**
     * 获取_order_detail
     * @param mixed $order_bn order_bn
     * @return mixed 返回结果
     */
    public function get_order_detail($order_bn){
        static $order_detail;
        if ($order_detail[$order_bn]) return $order_detail[$order_bn];

        $order_info = app::get('ome')->model('orders')->select()->columns('order_id')->where('order_bn=?',$order_bn)->instance()->fetch_row();
        if ($order_info){
            $order_detail[$order_bn] = $order_info['order_id'];
        } else {
            $order_detail[$order_bn] = -1;
        }
        return $order_detail[$order_bn];
    }

    /**
     * 获取_ordersBydeliverybn
     * @param mixed $delivery_bn delivery_bn
     * @return mixed 返回结果
     */
    public function get_ordersBydeliverybn($delivery_bn){
        $sql = "SELECT DO.order_id FROM `sdb_ome_delivery_order` as DO  LEFT JOIN sdb_ome_delivery as d on DO.delivery_id=d.delivery_id WHERE d.delivery_bn ='".$delivery_bn."' AND d.parent_id=0";

        $delivery = $this->db->selectrow($sql);
        $order_id = $delivery['order_id'];
        return $this->get_orders($order_id);
    }

    /**
     * 获取_ordersByreshipbn
     * @param mixed $reship_bn reship_bn
     * @return mixed 返回结果
     */
    public function get_ordersByreshipbn($reship_bn){
        $sql = "SELECT o.order_id FROM sdb_ome_reship WHERE reship_bn='".$reship_bn."'";
        $reship_detail = $this->db->selectrow($sql);
        $order_id = $reship_detail['order_id'];
        return $this->get_orders($order_id);
    }

    /**
     * 获取_orders
     * @param mixed $order_id ID
     * @return mixed 返回结果
     */
    public function get_orders($order_id){
        static $orders;
        if ($orders[$order_id]) return $orders[$order_id];
        $orders_detail = $this->db->selectrow("SELECT order_bn FROM sdb_ome_orders where order_id='".$order_id."'");

        $orders[$order_id] = $orders_detail['order_bn'];

        return $orders[$order_id];
    }

    private $templateColumn = array(
        '订单号' => 'order_bn',
        '店铺编码' => 'shop_bn',
        '基础物料编码' => 'material_bn',
        '唯一码' => 'serial_number',
    );

    /**
     * 获取TemplateColumn
     * @return mixed 返回结果
     */
    public function getTemplateColumn() {
        return array_keys($this->templateColumn);
    }
    /**
     * prepared_import_csv
     * @return mixed 返回值
     */
    public function prepared_import_csv(){
        $this->import_data =[];
        $this->shop = [];
        $this->order = [];
        $this->material = [];
        $this->branch = [];
        $this->ioObj->cacheTime = time();
    }

    /**
     * prepared_import_csv_row
     * @param mixed $row row
     * @param mixed $title title
     * @param mixed $tmpl tmpl
     * @param mixed $mark mark
     * @param mixed $newObjFlag newObjFlag
     * @param mixed $msg msg
     * @return mixed 返回值
     */
    public function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        if(empty($row) || empty(array_filter($row))) return false;
        if( $row[0] == '订单号' ){
            $this->nums = 1;
            $title = array_flip($row);
            foreach($this->templateColumn as $k => $val) {
                if(!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return false;
        }
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        if(isset($this->nums)){
            $this->nums++;
            if($this->nums > 10000){
                $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
                return false;
            }
        }
        $arrRequired = ['order_bn','shop_bn','material_bn','serial_number'];
        $arrData = array();
        foreach($this->templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
            if(in_array($value, $arrRequired) && empty($arrData[$val])) {
                $msg['warning'][] = 'Line '.$this->nums.'：'.$k.'不能为空！';
                return false;
            }
        }
        if(!isset($this->shop[$arrData['shop_bn']])) {
            $this->shop[$arrData['shop_bn']] = app::get('ome')->model('shop')->db_dump(['shop_bn'=>$arrData['shop_bn']], 'shop_id, shop_bn, node_type');
        }
        $shop = $this->shop[$arrData['shop_bn']];
        if(empty($shop)) {
            $msg['warning'][] = 'Line '.$this->nums.'：'.$arrData['shop_bn'].'店铺不存在';
            return false;
        }
        if($shop['node_type'] != 'luban') {
            $msg['warning'][] = 'Line '.$this->nums.'：'.$arrData['shop_bn'].'不是抖音店铺';
            return false;
        }
        if(!isset($this->order[$arrData['order_bn']])) {
            $this->order[$arrData['order_bn']] = app::get('ome')->model('orders')->db_dump(['shop_id'=>$shop['shop_id'],'order_bn'=>$arrData['order_bn']], 'order_id, order_bn, ship_status');
        }
        $order = $this->order[$arrData['order_bn']];
        if(empty($order)) {
            $msg['warning'][] = 'Line '.$this->nums.'：'.$arrData['order_bn'].'订单不存在';
            return false;
        }
        if($order['ship_status'] != '1') {
            $msg['warning'][] = 'Line '.$this->nums.'：'.$arrData['order_bn'].'订单未发货完成';
            return false;
        }
        if(!isset($this->material[$arrData['material_bn']])) {
            $this->material[$arrData['material_bn']] = app::get('material')->model('basic_material')->db_dump(['material_bn'=>$arrData['material_bn']], 'bm_id, material_bn, material_name');
        }
        $material = $this->material[$arrData['material_bn']];
        if(empty($material)) {
            $msg['warning'][] = 'Line '.$this->nums.'：'.$arrData['material_bn'].'基础物料不存在';
            return false;
        }
        $op = kernel::single('ome_func')->getDesktopUser();
        $item = [
            'bn' => $material['material_bn'],
            'product_name' => $material['material_name'],
            'act_type' => '3',
            'act_time' => time(),
            'act_owner' => $op['op_id'],
            'bill_type' => '3',
            'bill_id' => $order['order_id'],
            'bill_no' => $order['order_bn'],
            'serial_number' => $arrData['serial_number']
        ];
        $this->import_data[$order['order_id']][] = $item;
        $mark = 'contents';
        return true;
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        if(empty($this->import_data)) {
            return null;
        }
        return null;
    }


    /**
     * finish_import_csv
     * @return mixed 返回值
     */
    public function finish_import_csv(){
        if(empty($this->import_data)) {
            return null;
        }
        $data = $this->import_data;
        foreach($data as $bill_id => $v){
            $this->delete(['bill_id'=>$bill_id, 'bill_type'=>'3']);
            foreach ($v as $vv) {
                $this->insert($vv);
            }
            $queueData = array(
                'queue_title'=>$vv['bill_no'] . '订单唯一码导入',
                'start_time'=>time(),
                'params'=>array(
                    'sdfdata'=>$bill_id,
                ),
                'worker'=>'ome_mdl_product_serial_history.sync',
            );
            app::get('base')->model('queue')->save($queueData);
        }
        app::get('base')->model('queue')->flush();
        return null;
    }

    /**
     * run
     * @param mixed $cursor_id ID
     * @param mixed $params 参数
     * @param mixed $errmsg errmsg
     * @return mixed 返回值
     */
    public function run(&$cursor_id,$params,&$errmsg){
        $billId = $params['sdfdata'];
        kernel::single('ome_event_trigger_shop_order')->order_serial_sync($billId);
        return false;
    }
}