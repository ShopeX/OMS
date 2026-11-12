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
class ome_finder_product_serial{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = '基本信息';
    
    /**
     * Short description.
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    function detail_basic($item_id)
    {
        $render = app::get('ome')->render();
        $userObj = app::get('desktop')->model('users');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $branchObj = app::get('ome')->model('branch');
        $serialObj = app::get('ome')->model('product_serial');
        $serialLogObj = app::get('ome')->model('product_serial_log');
        $data = $serialObj->dump($item_id);
        if($data && $data['item_id']>0){
            $product = $basicMaterialObj->dump($data['product_id'],'material_name');
            $branch = $branchObj->dump($data['branch_id'],'name');
            $data['product_name'] = $product['material_name'];
            $data['branch_name'] = $branch['name'];
           
            switch($data['status']){
                case 0:
                    $data['status'] = '已入库';
                    break;
                case 1:
                    $data['status'] = '已出库';
                    break;
                case 2:
                    $result['serial_status'] = '无效';
                    break;
            }

            $logData = $serialLogObj->getList('*',array('item_id'=>$data['item_id']),0,-1,'act_time DESC');
            foreach($logData as $key=>$val){
                $logStatus = array();
                $logStatus = $this->log_status($val);
                $logData[$key]['act_type'] = $logStatus['act_type'];
                $logData[$key]['bill_type'] = $logStatus['bill_type'];
                $logData[$key]['bill_no'] = $logStatus['bill_no'];
                $logData[$key]['orderBn'] = $logStatus['orderBn'];
                $logData[$key]['serial_status'] = $logStatus['serial_status'];

                if($val['act_owner'] == 16777215){
                    $logData[$key]['act_owner'] = 'system';
                }else{
                    $user = $userObj->dump($val['act_owner'],'name');
                    $logData[$key]['act_owner'] = $user['name'];
                }
            }
        }

        $render->pagedata['serial_number'] = $_POST['serial_number'];

        $render->pagedata['data'] = $data;
        $render->pagedata['tag'] = true;
        $render->pagedata['logData'] = $logData;
        return $render->fetch('admin/serial/detail.html');
    }

    function log_status($data){
        if($data['act_type']>=0){
            switch($data['act_type']){
                case 0:
                    $result['act_type'] = '出库效验';
                    break;
                case 1:
                    $result['act_type'] = '入库效验';
                    break;
            }
        }

        if($data['bill_type']>=0){
            $orderObj = app::get('ome')->model('orders');
            switch($data['bill_type']){
                case 0:
                    $result['bill_type'] = '发货单';
                    if($data['bill_no'] && $data['bill_no'] != ''){
                        $deliveryObj = app::get('ome')->model('delivery');
                        $delivery = $deliveryObj->dump($data['bill_no'],'delivery_bn,process');
                        $result['bill_no'] = $delivery['delivery_bn'];
                        if($delivery['process']=='true'){
                            $orderIds = $deliveryObj->getOrderIdByDeliveryId($data['bill_no']);
                            //$orders = $orderObj->getList('order_id,order_bn',array('order_id'=>$orderIds));
                            //货品存在于此单中才显示订单号
                            $SQL = "SELECT o.order_bn,o.order_id FROM sdb_ome_order_items as i LEFT JOIN sdb_ome_orders as o ON i.order_id=o.order_id LEFT JOIN sdb_ome_product_serial ser ON ser.product_id=i.product_id WHERE o.order_id in (".implode(',',$orderIds).")  AND ser.item_id=".$data['item_id'];

                            $orders = $orderObj->db->select($SQL);
                            foreach($orders as $key=>$val){
                                $orderBn[$val['order_id']] = $val['order_bn'];
                            }
                            $result['orderBn'] = $orderBn;
                        }
                    }
                    break;
                case 1:
                    $result['bill_type'] = '售后申请单';
                    if($data['bill_no'] && $data['bill_no'] != ''){
                        $processObj = app::get('ome')->model('reship');
                        $process = $processObj->dump($data['bill_no'],'order_id,reship_bn');
                        $order = $orderObj->dump($process['order_id'],'order_bn');
                        $result['bill_no'] = $process['reship_bn'];
                        $orderBn[$process['order_id']] = $order['order_bn'];
                        $result['orderBn'] = $orderBn;
                    }
                    break;
            }
        }

        if($data['serial_status']>=0){
            switch($data['serial_status']){
                case 0:
                    $result['serial_status'] = '入库';
                    break;
                case 1:
                    $result['serial_status'] = '出库';
                    break;
                case 2:
                    $result['serial_status'] = '无效';
                    break;
            }
        }

        return $result;
    }
    var $column_delivery_bn='单据号';
    var $column_delivery_bn_width='单据号';
    function column_delivery_bn($row){
        $serialLogObj = app::get('ome')->model('product_serial_log');
        $deliveryObj = app::get('ome')->model('delivery');
        $item_id = $row['item_id'];
        $seriallog = $serialLogObj->dump(array('item_id'=>$item_id),'*');
        $data = $this->log_status($seriallog);
        return $data['bill_no'];
        
    }

    var $addon_cols = "product_id";
     var $column_product_name='货品名称';
    var $column_product_name_width = "100";
    function column_product_name($row){
        $product_id = $row[$this->col_prefix . 'product_id'];
        $basicMaterialObj = app::get('material')->model('basic_material');
        $product = $basicMaterialObj->dump($product_id,'material_name');
        return $product['material_name'];
    }


    /*
    var $column_product_type='商品类型';
    var $column_product_type_width = "100";
    function column_product_type($row)
    {
        $product_id = $row[$this->col_prefix . 'product_id'];
        $oSerial = app::get('ome')->model('product_serial');
        $serial_log = $oSerial->getProductById($product_id);
        return $serial_log['goods_type_name'];
    }
    var $column_product_brand='商品品牌';
    var $column_product_brand_width = "100";
    function column_product_brand($row)
    {
        $product_id = $row[$this->col_prefix . 'product_id'];
        $oSerial = app::get('ome')->model('product_serial');
        $serial_log = $oSerial->getProductById($product_id);
        return $serial_log['brand_name'];
    }
    var $column_goods_bn='商品编码';
    var $column_goods_bn_width = "100";
    function column_goods_bn($row)
    {
        $product_id = $row[$this->col_prefix . 'product_id'];
        $oSerial = app::get('ome')->model('product_serial');
        $serial_log = $oSerial->getProductById($product_id);
        return $serial_log['goods_bn'];
    }

    var $column_product_spec='商品规格';
    var $column_product_spec_width = "100";
    function column_product_spec($row)
    {
        $product_id = $row[$this->col_prefix . 'product_id'];
        $oSerial = app::get('ome')->model('product_serial');
        $serial_log = $oSerial->getProductById($product_id);
        return $serial_log['spec_info'];
    }
    */
    
}

?>
