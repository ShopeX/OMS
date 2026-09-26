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
 * 订单归档处理类
 * 
 * archive_time 参数说明：
 * 1. 支持相对时间参数：'1','2','3','6','9','12' (分别表示1,2,3,6,9,12个月前)
 * 2. 支持时间戳：传入具体的时间戳，如 1640995200 或 1640995200000
 * 3. 支持日期格式：'2024-06-30' 或 '2024-06-30 23:59:59'
 * 4. 如果不传或为空，默认使用12个月前的数据
 * 
 * 使用示例：
 * $orderfilter = array(
 *     'status' => array('fail', 'unpayed'),
 *     'archive_time' => '2024-06-30 23:59:59',  // 日期格式
 * );
 * 
 * // 或者使用时间戳
 * $orderfilter = array(
 *     'status' => array('fail', 'unpayed'),
 *     'archive_time' => 1640995200,  // 时间戳
 * );
 * 
 * // 或者使用相对时间
 * $orderfilter = array(
 *     'status' => array('fail', 'unpayed'),
 *     'archive_time' => '6',  // 6个月前
 * );
 */

class archive_order{
    protected $db;
    /**
     * __construct
     * @return mixed 返回值
     */

    public function __construct(){
        
        $this->db = kernel::database();
        
    }
    
   
    function archivetimeFilter($archive_time)
    {
        $create_time = '';
        
        // 判断是否为日期格式（YYYY-MM-DD 或 YYYY-MM-DD HH:MM:SS）
        if (preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$/', $archive_time)) {
            return strtotime($archive_time);
        }
        
        // 如果不是时间戳或日期格式，则按原来的switch逻辑处理相对时间
        switch($archive_time){
            case '1':
                $create_time =  strtotime("-1 month");
            break;
            case '2':
                $create_time =  strtotime("-2 month");
            break;
             case '3':
                $create_time =  strtotime("-3 month");
            break;
            case '6':
                $create_time =  strtotime("-6 month");
            break;
            case '9':
                $create_time =  strtotime("-9 month");
            break;
            default:
                $create_time =  strtotime("-12 month");
                break;
        }
        return $create_time;
    }
    /**
     * 创建订单信息
     * @param   
     * @return  
     * @access  public
     * @author sunjng@shopex.cn
     */
    function _create_order($order_list)
    {
   
        $orderIds = array();
       
        foreach ($order_list as $order ) {
            $orderIds[] = $order['order_id'];
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        // 优化订单主表插入SQL
        $order_sql = "INSERT INTO sdb_archive_orders(
            `order_id`, `order_bn`, `member_id`, `status`, `pay_status`, `ship_status`, 
            `pay_bn`, `payment`, `itemnum`, `createtime`, `download_time`, `last_modified`, 
            `shop_id`, `shop_type`, `ship_name`, `ship_area`, `ship_addr`, `ship_zip`, 
            `ship_tel`, `ship_email`, `ship_time`, `ship_mobile`, `consigner_name`, 
            `consigner_area`, `consigner_addr`, `consigner_zip`, `consigner_email`, 
            `consigner_mobile`, `consigner_tel`, `cost_item`, `is_tax`, `cost_tax`, 
            `tax_company`, `cost_freight`, `is_protect`, `cost_protect`, `is_cod`, 
            `is_fail`, `discount`, `pmt_goods`, `pmt_order`, `total_amount`, 
            `final_amount`, `payed`, `custom_mark`, `mark_text`, `tax_no`, `source`, 
            `order_type`, `order_combine_idx`, `order_combine_hash`, `paytime`, 
            `modifytime`, `order_source`, `relate_order_bn`, `createway`, 
            `process_status`, `archive_time`, `org_id`,
            `platform_order_bn`,`api_version`,`logi_id`,`logi_no`,`end_time`,`betc_id`,`cos_id`
        ) SELECT 
            `order_id`, `order_bn`, `member_id`, `status`, `pay_status`, `ship_status`, 
            `pay_bn`, `payment`, `itemnum`, `createtime`, `download_time`, `last_modified`, 
            `shop_id`, `shop_type`, `ship_name`, `ship_area`, `ship_addr`, `ship_zip`, 
            `ship_tel`, `ship_email`, `ship_time`, `ship_mobile`, `consigner_name`, 
            `consigner_area`, `consigner_addr`, `consigner_zip`, `consigner_email`, 
            `consigner_mobile`, `consigner_tel`, `cost_item`, `is_tax`, `cost_tax`, 
            `tax_company`, `cost_freight`, `is_protect`, `cost_protect`, `is_cod`, 
            `is_fail`, `discount`, `pmt_goods`, `pmt_order`, `total_amount`, 
            `final_amount`, `payed`, `custom_mark`, `mark_text`, `tax_no`, `source`, 
            `order_type`, `order_combine_idx`, `order_combine_hash`, `paytime`, 
            `modifytime`, `order_source`, `relate_order_bn`, `createway`, 
            `process_status`, ".time()." AS archive_time, `org_id`,
            `platform_order_bn`,`api_version`,`logi_id`,`logi_no`,`end_time`,`betc_id`,`cos_id`
        FROM sdb_ome_orders 
        WHERE `order_id` IN (".$orderIdstr.") 
        AND `order_id` NOT IN (SELECT `order_id` FROM sdb_archive_orders)";

        $order_result = $this->db->exec($order_sql);
        if (!$order_result) {
            throw new Exception("Failed to create order archive");
        }
        
        // 优化订单对象表插入SQL
        $order_objsql = "INSERT INTO sdb_archive_order_objects(
            `obj_id`, `order_id`, `obj_type`, `goods_id`, `bn`, `name`, `price`, 
            `amount`, `quantity`, `pmt_price`, `sale_price`, `oid`, 
            `divide_order_fee`, `part_mjz_discount`
        ) SELECT 
            `obj_id`, `order_id`, `obj_type`, `goods_id`, `bn`, `name`, `price`, 
            `amount`, `quantity`, `pmt_price`, `sale_price`, `oid`, 
            `divide_order_fee`, `part_mjz_discount` 
        FROM sdb_ome_order_objects 
        WHERE `order_id` IN (".$orderIdstr.") 
        AND `obj_id` NOT IN (SELECT `obj_id` FROM sdb_archive_order_objects)";
       
        $obj_result = $this->db->exec($order_objsql);
        if (!$obj_result) {
            throw new Exception("Failed to create order objects archive");
        }
        
        // 优化订单明细表插入SQL
        $order_itemsql = "INSERT INTO sdb_archive_order_items(
            `item_id`, `order_id`, `obj_id`, `product_id`, `bn`, `name`, `cost`, 
            `price`, `pmt_price`, `sale_price`, `amount`, `nums`, `sendnum`, 
            `item_type`, `weight`, `addon`, `return_num`, `divide_order_fee`, 
            `part_mjz_discount`
        ) SELECT 
            I.`item_id`, I.`order_id`, I.`obj_id`, I.`product_id`, I.`bn`, I.`name`, 
            I.`cost`, I.`price`, I.`pmt_price`, I.`sale_price`, I.`amount`, I.`nums`, 
            I.`sendnum`, I.`item_type`, I.`weight`, I.`addon`, I.`return_num`, 
            I.`divide_order_fee`, I.`part_mjz_discount` 
        FROM sdb_ome_order_items AS I 
        WHERE I.`order_id` IN (".$orderIdstr.") 
        AND I.`delete` = 'false' 
        AND I.`item_id` NOT IN (SELECT `item_id` FROM sdb_archive_order_items)";
      
        $item_result = $this->db->exec($order_itemsql);
        if (!$item_result) {
            throw new Exception("Failed to create order items archive");
        }
        
        return true;
    }

    /**
     * 删除原始数据
     * @param array $order_list 订单列表
     * @param array $delivery_list 发货单列表
     * @param array $wmsdeliveryIds WMS发货单ID列表
     * @return void
     * @access private
     * @author system
     */
    private function _delete_original_data($order_list, $delivery_list, $wmsdeliveryIds)
    {
        try {
            // 更新关联单据为已归档
            $this->archive_bill($order_list);
            
            // 删除订单相关数据
            $this->_delete_order($order_list);
            $this->_delete_delivery($delivery_list);
            
            // 删除订单标记
            $this->_delete_bill_label($order_list, 'order');
            
            $this->_delete_order_coupon($order_list);
            $this->_delete_order_invoice($order_list);
            $this->_delete_order_luckybag($order_list);
            $this->_delete_order_objects_check_items($order_list);
            $this->_delete_order_objects_coupon($order_list);
            $this->_delete_order_objects_extend($order_list);
            $this->_delete_order_pmt($order_list);
            $this->_delete_order_receiver($order_list);
            $this->_delete_order_selling_agent($order_list);
            $this->_delete_order_service($order_list);
            
            // 删除WMS发货单数据
            if (!empty($wmsdeliveryIds)) {
                $this->deleteWmsdelivery($wmsdeliveryIds);
            }
            
        } catch (Exception $e) {
            throw new Exception("Failed to delete original data: " . $e->getMessage());
        }
    }

    function _get_deliveryList($order_list){
        $orderIds = array();
       
        foreach ($order_list as $order ) {
            $orderIds[] = $order['order_id'];
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        $delivery_sql = "SELECT D.`delivery_id`,D.`parent_id`,D.`process`,D.`status`,D.`delivery_bn` FROM sdb_ome_delivery_order  as O LEFT JOIN sdb_ome_delivery as D on O.`delivery_id`=D.`delivery_id` WHERE O.`order_id` in(".$orderIdstr.")";
        $deliveryList = $this->db->select($delivery_sql);
        $delivery_ids = array();
        foreach($deliveryList as $delivery){
            $delivery_ids[$delivery['delivery_id']] = $delivery;
        }
        return $delivery_ids;
        
    }

    function _get_wmsdeliveryList($deliveryList){
        $outdeliverybn = array();
        foreach ($deliveryList as $delivery ) {
            
            if ($delivery['parent_id']==0 && $delivery['status']=='succ' && $delivery['process'] == 'true') {
               
                $outdeliverybn[$delivery['delivery_id']] = $delivery['delivery_bn'];
            }
            
        }
       
        $wmsdeliveryIds = $this->getdeliveryidByOutbn($outdeliverybn);
        return $wmsdeliveryIds;

        
    }
    /**
     * 发货单组建信息
     * @param  
     * @return  
     * @access  public
     * @author sunjng@shopex.cn
     */
    function _create_delivery($deliveryList)
    {
        $deliveryIds = array();
        $outdeliverybn = array();
        foreach ($deliveryList as $delivery ) {
            
            if ($delivery['parent_id']==0 && $delivery['status']=='succ' && $delivery['process'] == 'true') {
                $deliveryIds[] = $delivery['delivery_id'];
                $outdeliverybn[$delivery['delivery_id']] = $delivery['delivery_bn'];
            }
            
        }
        if ($deliveryIds) {
            $deliveryIdstr = "'".implode("','",$deliveryIds)."'";
             $copy_delivery_sql = "INSERT  INTO sdb_archive_delivery(`delivery_id`,`idx_split`,`skuNum`,`itemNum`,`delivery_bn`,`bnsContent`,`member_id`,`is_protect`,`cost_protect`,`is_cod`,`delivery`,`logi_id`,`logi_name`,`logi_no`,`logi_number`,`delivery_logi_number`,`ship_name`,`ship_area`,`ship_province`,`ship_city`,`ship_district`,`ship_addr`,`ship_zip`,`ship_tel`,`ship_mobile`,`ship_email`,`create_time`,`status`,`memo`,`branch_id`,`last_modified`,`delivery_time`,`ship_time`,`op_id`,`op_name`,`shop_id`,`org_id`,`shop_type`,`betc_id`,`cos_id`) SELECT `delivery_id`,`idx_split`,`skuNum`,`itemNum`,`delivery_bn`,`bnsContent`,`member_id`,`is_protect`,`cost_protect`,`is_cod`,`delivery`,`logi_id`,`logi_name`,`logi_no`,`logi_number`,`delivery_logi_number`,`ship_name`,`ship_area`,`ship_province`,`ship_city`,`ship_district`,`ship_addr`,`ship_zip`,`ship_tel`,`ship_mobile`,`ship_email`,`create_time`,`status`,`memo`,`branch_id`,`last_modified`,`delivery_time`,`ship_time`,`op_id`,`op_name`,`shop_id`,`org_id`,`shop_type`,`betc_id`,`cos_id` FROM sdb_ome_delivery WHERE `delivery_id` in(".$deliveryIdstr.") AND `delivery_id` NOT IN(SELECT `delivery_id` FROM sdb_archive_delivery)";
         
            $delivery_result = $this->db->exec($copy_delivery_sql);
            if (!$delivery_result) {
                throw new Exception("Failed to create delivery archive");
            }
            
            $copy_items_sql = "INSERT  INTO sdb_archive_delivery_items(`item_id`,`delivery_id`,`product_id`,`bn`,`product_name`,`number`) SELECT `item_id`,`delivery_id`,`product_id`,`bn`,`product_name`,`number` FROM sdb_ome_delivery_items WHERE `delivery_id` in(".$deliveryIdstr.") AND `item_id` NOT IN(SELECT `item_id` FROM sdb_archive_delivery_items)";
           
            $item_result = $this->db->exec($copy_items_sql);
            if (!$item_result) {
                throw new Exception("Failed to create delivery items archive");
            }
            
            $deliveryorder_sql = "INSERT  INTO sdb_archive_delivery_order(`order_id`,`delivery_id`) SELECT `order_id`,`delivery_id` FROM sdb_ome_delivery_order WHERE `delivery_id` in(".$deliveryIdstr.")";
           
            $deliveryorder_result = $this->db->exec($deliveryorder_sql);
            if (!$deliveryorder_result) {
                throw new Exception("Failed to create delivery order archive");
            }
         }
        
        return true;
    }



     /**
      * 冻结库存
      * @param  
      * @return  
      * @access  public
      * @author sunjng@shopex.cn
      */

     function _storefreeze_order($order_list)
     {

        $orderIds = array();

        foreach ($order_list as $order ) {
            $orderIds[] = $order['order_id'];
        }
        $oProduct = app::get('ome')->model("products");
        $orderIdstr = "'".implode("','",$orderIds)."'";
         //释放冻结

        $items = $this->db->select("SELECT `product_id`,`nums` FROM sdb_ome_order_items WHERE `order_id` in (".$orderIdstr.")");
        foreach($items as $v){
            $num = $v['nums'];
            $oProduct->chg_product_store_freeze($v['product_id'],$num,"-");
        }
        echo "冻结库存释放完成\n";
     }

     
     /**
      * 删除订单相关信息
      * @param  
      * @return 
      * @access  public
      * @author sunjing@shopex.cn
      */
     function _delete_order($order_list)
     {
        $orderIds = array();
       
        foreach ($order_list as $order ) {
            $orderIds[] = $order['order_id'];
        }
        if ($orderIds) {
            $orderIdstr = "'".implode("','",$orderIds)."'";
            $ordersql = "DELETE FROM sdb_ome_orders WHERE `order_id` in(".$orderIdstr.")";
           
            $this->db->exec($ordersql);
            $orderobjectsql = "DELETE FROM sdb_ome_order_objects WHERE `order_id` in(".$orderIdstr.")";
            
            $this->db->exec($orderobjectsql);
            $orderitemsql = "DELETE FROM sdb_ome_order_items WHERE `order_id` in(".$orderIdstr.")";
            $this->db->exec($orderitemsql);
            
            $orderextendsql = "DELETE FROM sdb_ome_order_extend WHERE `order_id` in(".$orderIdstr.")";
            $this->db->exec($orderextendsql);
            
            $orderabnormalsql = "DELETE FROM sdb_ome_order_abnormal WHERE `order_id` in(".$orderIdstr.")";
            $this->db->exec($orderabnormalsql);
            
            $orderoutstoragesql = "DELETE FROM sdb_ome_order_outstorage WHERE `order_id` in(".$orderIdstr.")";
            $this->db->exec($orderoutstoragesql);
            
            $orderplatformsplitsql = "DELETE FROM sdb_ome_order_platformsplit WHERE `order_id` in(".$orderIdstr.")";
            $this->db->exec($orderplatformsplitsql);
            
            $orderpreprocesssql = "DELETE FROM sdb_ome_order_preprocess WHERE `preprocess_order_id` in(".$orderIdstr.")";
            $this->db->exec($orderpreprocesssql);
            
            $orderretrialsql = "DELETE FROM sdb_ome_order_retrial WHERE `order_id` in(".$orderIdstr.")";
            $this->db->exec($orderretrialsql);
            
            $orderretrialsnapshotsql = "DELETE FROM sdb_ome_order_retrial_snapshot WHERE `order_id` in(".$orderIdstr.")";
            $this->db->exec($orderretrialsnapshotsql);
            
            $orderretrialstorefreezesql = "DELETE FROM sdb_ome_order_retrial_store_freeze WHERE `order_id` in(".$orderIdstr.")";
            $this->db->exec($orderretrialstorefreezesql);
           
            //异常备注不删除，LMZ20150120401。2015.2.11 liuzecheng
            //$this->db->exec("DELETE FROM sdb_ome_abnormal WHERE order_id in(".$orderIdstr.")");
            //$this->_operation_log('orders@ome',$orderIdstr);
            
            //删除订单关联的标签 - 已移至 _delete_bill_label 方法处理
            
            echo "订单删除完成\n";
        }
     }
    
     /**
      * 删除订单相关信息
      * @param  
      * @return 
      * @access  public
      * @author sunjing@shopex.cn
      */
     function _delete_delivery($delivery_list)
     {
        $deliveryIds = array_keys($delivery_list);
        if ($deliveryIds){
            $deliveryIdstr = "'".implode("','",$deliveryIds)."'";
            $deliverysql = "DELETE FROM sdb_ome_delivery WHERE `delivery_id` in(".$deliveryIdstr.")";
     
            $this->db->exec($deliverysql);
            $deliveryordsql = "DELETE FROM sdb_ome_delivery_order WHERE `delivery_id` in(".$deliveryIdstr.")";
           
            $this->db->exec($deliveryordsql);
            $deliverybillsql = "DELETE FROM sdb_ome_delivery_bill WHERE `delivery_id` in(".$deliveryIdstr.")";
            
            $this->db->exec($deliverybillsql);
            $itemsql = "DELETE FROM sdb_ome_delivery_items WHERE `delivery_id` in(".$deliveryIdstr.")";
           
            $this->db->exec($itemsql);
            $detailsql = "DELETE FROM sdb_ome_delivery_items_detail WHERE `delivery_id` in(".$deliveryIdstr.")";
          
            $this->db->exec($detailsql);
            //删除订单关联的标签
            $deleteSql = "DELETE FROM sdb_ome_bill_label WHERE `bill_id` IN(". $deliveryIdstr .") AND `bill_type`='ome_delivery'";
            $this->db->exec($deleteSql);
        
        }
        
 
     }

    
   
  

    /**
     * 订单操作日志
     * @param   
     * @return  
     * @access  public
     * @author  sunjing@shopex.cn
     */
    function _operation_log($obj_type,$orderIdstr)
    {
        $sql = "DELETE FROM sdb_ome_operation_log WHERE `obj_id` in(".$orderIdstr.") AND `obj_type` in('".$obj_type."')";

         $this->db->exec($sql);
    }

    
    /**
     * 更新关联单据为已归档
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function archive_bill($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order ) {
            $orderIds[] = $order['order_id'];
        }
        if ($orderIds) {
            $orderIdstr = "'".implode("','",$orderIds)."'";
            $apply_sql = "UPDATE sdb_ome_refund_apply SET `archive`='1' WHERE `order_id` in(".$orderIdstr.")";
           
            $this->db->exec($apply_sql);
            $return_sql = "UPDATE sdb_ome_return_product SET `archive`='1' WHERE `order_id` in(".$orderIdstr.")";
         
            $this->db->exec($return_sql);
            $reship_sql = "UPDATE sdb_ome_reship SET `archive`='1' WHERE `order_id` in(".$orderIdstr.")";
           
            $this->db->exec($reship_sql);
            $sales_sql = "UPDATE sdb_ome_sales SET `archive`='1' WHERE `order_id` in(".$orderIdstr.")";
          
            $this->db->exec($sales_sql);
            $aftersale_sql = "UPDATE sdb_sales_aftersale SET `archive`='1' WHERE `order_id` in(".$orderIdstr.")";
           
            $this->db->exec($aftersale_sql);

            $payments_sql = "UPDATE sdb_ome_payments SET `archive`='1' WHERE `order_id` in(".$orderIdstr.")";
           
            $this->db->exec($payments_sql);
            $refunds_sql = "UPDATE sdb_ome_refunds SET `archive`='1' WHERE `order_id` in(".$orderIdstr.")";
        
            
            $this->db->exec($refunds_sql);

        }
    }
    
   
   
    
    /**
     * 判断是否归档类型.
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function is_archive($source)
    {
        $result = false;
        if (($source && in_array($source,array('archive'))) || $source=='1') {
            $result = true;
        }
        return $result;
    }

    
    /**
     * 最新归档时间.
     * @param
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_archive_time()
    {
        $archive_log = $this->db->selectrow("SELECT `archive_time` FROM sdb_archive_operation_log WHERE `archive_time`>0 ORDER BY `archive_time` DESC");
        return $archive_log['archive_time'];
    }

    function optimize($table)
    {
        $sql = 'OPTIMIZE TABLE '.$table;
        $this->db->exec($sql);

    }

    
    
    function getdeliveryidByOutbn($delivery_bns)
    {
        $wmsdeliveryIds = array();
        if ($delivery_bns){
            $delivery_bns = "'".implode("','",$delivery_bns)."'";
            $deliverys = $this->db->select("SELECT `delivery_id` FROM sdb_wms_delivery WHERE `outer_delivery_bn` in(".$delivery_bns.")");
            if ($deliverys) {
                foreach ($deliverys as $delivery) {
                    $wmsdeliveryIds[] = $delivery['delivery_id'];
                }
            }
        }
        return $wmsdeliveryIds;
    }

    
    
    function deleteWmsdelivery($delivery_ids)
    {
        if ($delivery_ids){
            $delivery_ids = "'".implode("','",$delivery_ids)."'";
            $this->db->exec("DELETE FROM sdb_wms_delivery WHERE `delivery_id` in(".$delivery_ids.")");
            $this->db->exec("DELETE FROM sdb_wms_delivery_bill WHERE `delivery_id` in(".$delivery_ids.")");
            $this->db->exec("DELETE FROM sdb_wms_delivery_items WHERE `delivery_id` in(".$delivery_ids.")");
        }
    }

    
    
    function copyWmsdelivery($delivery_ids)
    {
        if ($delivery_ids){
            $delivery_ids = "'".implode("','",$delivery_ids)."'";
            $keys = '`delivery_id`,`idx_split`,`skuNum`,`itemNum`,`bnsContent`,`delivery_bn`,`member_id`,`is_protect`,`cost_protect`,`is_cod`,`logi_id`,`logi_name`,`logi_number`,`delivery_logi_number`,`ship_name`,`ship_area`,`ship_province`,`ship_city`,`ship_district`,`ship_addr`,`ship_zip`,`ship_tel`,`ship_mobile`,`ship_email`,`create_time`,`STATUS`,`print_status`,`process_status`,`memo`,`disabled`,`branch_id`,`last_modified`,`delivery_time`,`delivery_cost_expect`,`delivery_cost_actual`,`bind_key`,`type`,`shop_id`,`order_createtime`,`ship_time`,`op_id`,`op_name`,`outer_delivery_bn`,`shop_type`';
            $deliverysql = "INSERT INTO sdb_archive_wmsdelivery(".$keys.") SELECT ".$keys." FROM sdb_wms_delivery WHERE `delivery_id` in (".$delivery_ids.")";
            $itemkeys = '`item_id`,`delivery_id`,`product_id`,`bn`,`product_name`,`number`,`price`,`sale_price`,`pmt_price`';
            $itemsql = "INSERT INTO sdb_archive_wmsdelivery_items(".$itemkeys.") SELECT ".$itemkeys." FROM sdb_wms_delivery_items WHERE `delivery_id` in (".$delivery_ids.")" ;
            $billkeys = '`b_id`,`delivery_id`,`logi_no`,`type`,`print_status`,`status`,`net_weight`,`weight`,`create_time`,`delivery_time`';
            $billsql = "INSERT INTO sdb_archive_wmsdelivery_bill(".$billkeys.") SELECT ".$billkeys." FROM sdb_wms_delivery_bill WHERE `delivery_id` in (".$delivery_ids.")";

            $delivery_result = $this->db->exec($deliverysql);
            if (!$delivery_result) {
                throw new Exception("Failed to copy WMS delivery");
            }
            
            $item_result = $this->db->exec($itemsql);
            if (!$item_result) {
                throw new Exception("Failed to copy WMS delivery items");
            }
            
            $bill_result = $this->db->exec($billsql);
            if (!$bill_result) {
                throw new Exception("Failed to copy WMS delivery bill");
            }
        }
        
        return true;
    }

    function copyTables($keys,$datarow)
    {
        $order_key = explode(',',$keys);
        $values = array();
        foreach ($order_key as $ordervalue ) {
            $ordervalue = str_replace('`','',$ordervalue);
             $datavalue = $datarow[$ordervalue];
            if (is_string($datavalue)) {
                $datavalue = addslashes($datavalue);
            }
            $values[] = "'".$datavalue."'";
        }
        $values = "(".implode(',',$values).")";
        return $values;
    }

    
    
    function get_total($orderfilter)
    {
        $sqlstr = "WHERE (`archive`='1' OR `status` in('dead') ";
        
        if (isset($orderfilter['archive_time'])) {
            $archive_time = $this->archivetimeFilter($orderfilter['archive_time']);
        } else {
            // 默认使用12个月前的数据
            $archive_time = $this->archivetimeFilter('12');
        }
        
        $status = $orderfilter['status'];
        
        if ($status) {
            if (in_array('fail',$status)) {
                
                $sqlstr.= " OR (pay_status='0' AND process_status in ('unconfirmed'))";
            }
            if (in_array('unpayed',$status)) {
                
                $sqlstr.= " OR (is_fail='true' AND pay_status in ('0','1'))";
            }
        }
        $sqlstr.=') AND createtime<'.$archive_time;
        $order_total = $this->db->selectrow("SELECT count(`order_id`) as _count FROM sdb_ome_orders ".$sqlstr);
    
        $total = $order_total['_count'];
        return $total;
    }

    
    /**
     * 处理操作归档
     * @param $orderfilter
     * @return array 处理结果[true/false,错误信息]
     * @date 2025-07-22 下午6:54
     */
    function process($orderfilter)
    {
        $sqlstr = "WHERE (`archive`='1' OR `status` in('dead') ";
        
        if (isset($orderfilter['archive_time'])) {
            $archive_time = $this->archivetimeFilter($orderfilter['archive_time']);
        } else {
            // 默认使用12个月前的数据
            $archive_time = $this->archivetimeFilter('12');
        }
        
        $status = $orderfilter['status'];
        
        if ($status) {
            if (in_array('fail',$status)) {
                $sqlstr.= " OR (pay_status='0' AND process_status in ('unconfirmed'))";
            }
            if (in_array('unpayed',$status)) {
                $sqlstr.= " OR (is_fail='true' AND pay_status in ('0','1'))";
            }
        }
        $sqlstr.=') AND createtime<'.$archive_time;
        
        // 添加 order_id 范围条件
        if (isset($orderfilter['order_id_start']) && $orderfilter['order_id_start'] > 0) {
            $sqlstr .= " AND order_id > " . intval($orderfilter['order_id_start']);
        }
        
        if (isset($orderfilter['order_id_end']) && $orderfilter['order_id_end'] > 0) {
            $sqlstr .= " AND order_id <= " . intval($orderfilter['order_id_end']);
        }
    
        $archiveDocuments = $orderfilter['archive_documents'];
        //是否归档销售单、售后单
        $archiveSalesAftersale = false;
        if($archiveDocuments && in_array('sales_aftersale',$archiveDocuments)){
            $archiveSalesAftersale = true;
        }
        
        $order_list = $this->db->select("SELECT `order_id` FROM sdb_ome_orders ".$sqlstr." ORDER BY order_id ASC LIMIT 0,500");
      
        if ($order_list) {
            $this->db->beginTransaction();
            
            try {
                // 1. 创建归档数据
                $this->_create_order($order_list);
                
                $delivery_list = $this->_get_deliveryList($order_list);
                $this->_create_delivery($delivery_list);
                
                // 处理订单标记
                $this->_create_bill_label($order_list, 'order');
                
                // 处理发货单标记
                if (!empty($delivery_list)) {
                    $this->_create_bill_label($delivery_list, 'ome_delivery');
                }
                
                $this->_create_order_coupon($order_list);
                $this->_create_order_invoice($order_list);
                $this->_create_order_luckybag($order_list);
                $this->_create_order_objects_check_items($order_list);
                $this->_create_order_objects_coupon($order_list);
                $this->_create_order_objects_extend($order_list);
                $this->_create_order_pmt($order_list);
                $this->_create_order_receiver($order_list);
                $this->_create_order_selling_agent($order_list);
                $this->_create_order_service($order_list);
                $this->_create_return_product($order_list);
                $this->_create_refund_apply($order_list);
                $this->_create_reship($order_list);
                
                // 2. 处理WMS发货单
                $wmsdeliveryIds = $this->_get_wmsdeliveryList($delivery_list);
                $this->copyWmsdelivery($wmsdeliveryIds);
    
                // 增加销售单和售后单归档
                if ($archiveSalesAftersale) {
                    $this->_create_sales($order_list);
                    $this->_create_aftersale($order_list);
                }

                // 增加发货销售单归档
                $this->_create_sales_delivery_order($delivery_list);
                $this->_create_sales_delivery_order_item($delivery_list);

                // 3. 提交事务
                $this->db->commit();
                
                // 4. 删除原数据（事务外执行，避免长时间锁定）
                $this->_delete_original_data($order_list, $delivery_list, $wmsdeliveryIds);
                
            } catch (Exception $e) {
                $this->db->rollBack();
                $errMsg = "Archive process failed: " . $e->getMessage()."\n";
                return [false,$errMsg];
            }
        }
        
        unset($order_list);
        return [true,'归档成功'];
    }


    /**
     * _create_refund_apply
     * @param mixed $order_list order_list
     * @return mixed 返回值
     */
    public function _create_refund_apply($order_list){
        $orderIds = array();

        foreach ($order_list as $order ) {
            $orderIds[] = $order['order_id'];
        }

        $orderIdstr = "'".implode("','",$orderIds)."'";

        // 仅终态进归档表；未完结留在 sdb_ome_refund_apply，
        // 随后 _delete_original_data→archive_bill 会打 archive=1（不删行，避免漏数）。
        $applyList = $this->db->select("select `apply_id` FROM sdb_ome_refund_apply WHERE `order_id` in(".$orderIdstr.") AND `status` in('3','4')");

        if($applyList){
            $apply_ids =  array_column($applyList, 'apply_id');

            $applyIdstr = "'".implode("','",$apply_ids)."'";
            $keys = '`apply_id`,`refund_apply_bn`,`order_id`,`pay_type`,`account`,`bank`,`pay_account`,`money`,`refunded`,`memo`,`create_time`,`last_modified`,`status`,`shop_id`,`return_id`,`reship_id`,`addon`,`refund_refer`,`bcmoney`,`product_data`,`source`,`shop_type`,`mark_text`,`outer_lastmodify`,`oid`,`bn`';

            $refund_sql="INSERT INTO sdb_archive_refund_apply(".$keys.")SELECT ".$keys." FROM sdb_ome_refund_apply WHERE `apply_id` in(".$applyIdstr.") ";

            $refund_result = $this->db->exec($refund_sql);
            if (!$refund_result) {
                throw new Exception("Failed to create refund apply archive");
            }

            if($refund_result){
                $delete_result = $this->db->exec("DELETE FROM sdb_ome_refund_apply WHERE `apply_id` in(".$applyIdstr.")");
                if (!$delete_result) {
                    throw new Exception("Failed to delete original refund apply data");
                }
            }
        }
        
        return true;
    }


    /**
     * _create_return_product
     * @param mixed $order_list order_list
     * @return mixed 返回值
     */
    public function _create_return_product($order_list){
        $return_ids = $this->_get_returnList($order_list);
       
        if($return_ids){

            $returnIdstr = "'".implode("','",$return_ids)."'";
            $keys = '`return_id`,`return_bn`,`order_id`,`title`,`content`,`product_data`,`add_time`,`shop_id`,`member_id`,`process_data`,`memo`,`money`,`op_id`,`refundmoney`,`delivery_id`,`status`,`last_modified`,`tmoney`,`bmoney`,`source`,`shop_type`,`outer_lastmodify`,`return_type`,`kinds`';

            $return_sql="INSERT INTO sdb_archive_return_product(".$keys.")SELECT ".$keys." FROM sdb_ome_return_product WHERE `return_id` in(".$returnIdstr.")";

            $return_result = $this->db->exec($return_sql);
            if (!$return_result) {
                throw new Exception("Failed to create return product archive");
            }

            if($return_result){
                $itemkeys = '`item_id`,`return_id`,`product_id`,`bn`,`name`,`branch_id`,`num`,`price`';
                $item_sql = "INSERT INTO sdb_archive_return_product_items(".$itemkeys.")SELECT ".$itemkeys." FROM sdb_ome_return_product_items WHERE `return_id` in(".$returnIdstr.")";
            
                $item_result = $this->db->exec($item_sql);
                if (!$item_result) {
                    throw new Exception("Failed to create return product items archive");
                }

                if($item_result){
                    $delete_return_result = $this->db->exec("DELETE FROM sdb_ome_return_product WHERE `return_id` in(".$returnIdstr.")");
                    if (!$delete_return_result) {
                        throw new Exception("Failed to delete original return product data");
                    }

                    $delete_item_result = $this->db->exec("DELETE FROM sdb_ome_return_product_items WHERE `return_id` in(".$returnIdstr.")");
                    if (!$delete_item_result) {
                        throw new Exception("Failed to delete original return product items data");
                    }
                }
            }
        }
        
        return true;
    }


    /**
     * _create_reship
     * @param mixed $order_list order_list
     * @return mixed 返回值
     */
    public function _create_reship($order_list){
        $reship_ids = $this->_get_reshipList($order_list);
      
        if($reship_ids){
            $reshipIdstr = "'".implode("','",$reship_ids)."'";
            $keys = '`reship_id`,`reship_bn`,`order_id`,`money`,`delivery`,`return_logi_name`,`return_logi_no`,`ship_name`,`ship_area`,`ship_addr`,`ship_zip`,`ship_tel`,`ship_mobile`,`t_begin`,`t_end`,`op_id`,`status`,`is_check`,`memo`,`tmoney`,`bmoney`,`totalmoney`,`return_id`,`reason`,`return_type`,`change_status`,`shop_id`,`change_amount`,`cost_freight_money`,`branch_id`,`source`,`outer_lastmodify`,`out_iso_bn`,`check_time`,`changebranch_id`,`change_order_id`,`shop_type`';

            $reship_sql = "INSERT INTO sdb_archive_reship(".$keys.") SELECT ".$keys." FROM sdb_ome_reship WHERE `reship_id` in(".$reshipIdstr.")";

            $reship_result = $this->db->exec($reship_sql);
            if (!$reship_result) {
                throw new Exception("Failed to create reship archive");
            }

            if($reship_result){
                $itemkeys = '`item_id`,`reship_id`,`obj_id`,`bn`,`product_name`,`product_id`,`num`,`price`,`branch_id`,`op_id`,`return_type`,`defective_num`,`normal_num`';
                $item_sql = "INSERT INTO sdb_archive_reship_items(".$itemkeys.") SELECT ".$itemkeys." FROM sdb_ome_reship_items WHERE `reship_id` in(".$reshipIdstr.") AND `is_del`='false'";
               
                $item_result = $this->db->exec($item_sql);
                if (!$item_result) {
                    throw new Exception("Failed to create reship items archive");
                }

                if($item_result){
                    $delete_reship_result = $this->db->exec("DELETE FROM sdb_ome_reship WHERE `reship_id` in(".$reshipIdstr.")");
                    if (!$delete_reship_result) {
                        throw new Exception("Failed to delete original reship data");
                    }

                    $delete_item_result = $this->db->exec("DELETE FROM sdb_ome_reship_items WHERE `reship_id` in(".$reshipIdstr.")");
                    if (!$delete_item_result) {
                        throw new Exception("Failed to delete original reship items data");
                    }

                    $delete_process_result = $this->db->exec("DELETE FROM sdb_ome_return_process WHERE `reship_id` in(".$reshipIdstr.")");
                    if (!$delete_process_result) {
                        throw new Exception("Failed to delete original return process data");
                    }

                    $delete_process_items_result = $this->db->exec("DELETE FROM sdb_ome_return_process_items WHERE `reship_id` in(".$reshipIdstr.")");
                    if (!$delete_process_items_result) {
                        throw new Exception("Failed to delete original return process items data");
                    }
                }
            }
        }
        
        return true;
    }

    /**
     * _get_reshipList
     * @param mixed $order_list order_list
     * @return mixed 返回值
     */
    public function _get_reshipList($order_list){
        $orderIds = array();

        foreach ($order_list as $order ) {
            $orderIds[] = $order['order_id'];
        }

        $orderIdstr = "'".implode("','",$orderIds)."'";
        $reship_sql = "SELECT reship_id FROM sdb_ome_reship WHERE order_id in(".$orderIdstr.") AND is_check in('5','7')";
        $reshipList = $this->db->select($reship_sql);
        $reship_ids =  array_column($reshipList, 'reship_id');
        
        return $reship_ids;
    }


    /**
     * _get_returnList
     * @param mixed $order_list order_list
     * @return mixed 返回值
     */
    public function _get_returnList($order_list){
        $orderIds = array();

        foreach ($order_list as $order ) {
            $orderIds[] = $order['order_id'];
        }

        $orderIdstr = "'".implode("','",$orderIds)."'";
        $return_sql = "SELECT return_id FROM sdb_ome_return_product WHERE order_id in(".$orderIdstr.") AND `status` in('4','5')";
        $returnList = $this->db->select($return_sql);
        $return_ids =  array_column($returnList, 'return_id');
        
        return $return_ids;
    }

    /**
     * historyArchiveList
     * @return mixed 返回值
     */
    public function historyArchiveList(){
        $archivesql = "select order_id from sdb_archive_orders";

        $order_list = $this->db->select($archivesql);

        $this->_create_return_product($order_list);

        $this->_create_refund_apply($order_list);
        $this->_create_reship($order_list);

    }

    /**
     * 删除Processlist
     * @return mixed 返回值
     */
    public function deleteProcesslist(){

        $sql = "SELECT count(reship_id) as _count FROM sdb_archive_reship";

        $reships = $this->db->selectrow($sql);

        $total = $reships['_count'];

        $pagelimit = 500;
        $page = ceil($total/$pagelimit);
       
        for($i=1;$i<=$page;$i++){
           
            $offset = $pagelimit*($i-1);
            $offset = max($offset,0);

            $querysql = "SELECT reship_id FROM sdb_archive_reship ORDER BY reship_id ASC LIMIT $offset,$pagelimit";

            $reshipList = $this->db->select($querysql);

            $reship_ids = array_column($reshipList,'reship_id');

            if($reship_ids){
                $reshipIdstr = "'".implode("','",$reship_ids)."'";

                $this->db->exec("DELETE FROM sdb_ome_return_process WHERE reship_id in(".$reshipIdstr.")");
                $this->db->exec("DELETE FROM sdb_ome_return_process_items WHERE reship_id in(".$reshipIdstr.")");

            }
            
        }

    }

    /**
     * 创建单据标记归档
     * @param array $data_list 数据列表（订单列表或发货单列表）
     * @param string $bill_type 单据类型（'order' 或 'ome_delivery'）
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_bill_label($data_list, $bill_type = 'order')
    {
        if (empty($data_list)) {
            return true;
        }
        
        // 根据单据类型决定提取哪个字段
        $id_field = ($bill_type == 'order') ? 'order_id' : 'delivery_id';
        
        $ids = array();
        foreach ($data_list as $data) {
            $ids[] = $data[$id_field];
        }
        
        // 如果提取的ID为空，则不处理
        if (empty($ids)) {
            return true;
        }
        
        $idstr = "'".implode("','",$ids)."'";
        
        // 获取单据相关的标记数据，根据单据类型进行特殊处理
        $keys = '`id`,`bill_type`,`bill_id`,`label_id`,`label_name`,`label_code`,`label_value`,`extend_info`,`create_time`';
        $bill_label_sql = "INSERT INTO sdb_archive_bill_label(".$keys.",archive_time) 
                           SELECT ".$keys.",".time()." AS archive_time 
                           FROM sdb_ome_bill_label 
                           WHERE bill_id IN(".$idstr.") 
                           AND bill_type='".$bill_type."' 
                           AND `id` NOT IN (SELECT `id` FROM sdb_archive_bill_label)";
        
        $bill_label_result = $this->db->exec($bill_label_sql);
        if (!$bill_label_result) {
            throw new Exception("Failed to create bill label archive for type: " . $bill_type);
        }
        
        return true;
    }

    /**
     * 删除单据标记数据
     * @param array $data_list 数据列表（订单列表或发货单列表）
     * @param string $bill_type 单据类型（'order' 或 'ome_delivery'）
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_bill_label($data_list, $bill_type = 'order')
    {
        if (empty($data_list)) {
            return true;
        }
        
        // 根据单据类型决定提取哪个字段
        $id_field = ($bill_type == 'order') ? 'order_id' : 'delivery_id';
        
        $ids = array();
        foreach ($data_list as $data) {
            $ids[] = $data[$id_field];
        }
        
        // 如果提取的ID为空，则不处理
        if (empty($ids)) {
            return true;
        }
        
        $idstr = "'".implode("','",$ids)."'";
        
        // 删除单据相关的标记数据，根据单据类型进行特殊处理
        $deleteSql = "DELETE FROM sdb_ome_bill_label WHERE `bill_id` IN(".$idstr.") AND bill_type='".$bill_type."'";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original bill label data for type: " . $bill_type);
        }
        
        return $delete_result;
    }

    /**
     * 创建订单优惠券归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_coupon($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        // 获取订单相关的优惠券数据
        $keys = '`id`,`order_id`,`type`,`type_name`,`coupon_type`,`num`,`material_name`,`material_bn`,`amount`,`total_amount`,`oid`,`pay_time`,`create_time`,`shop_type`,`extend`,`source`';
        $order_coupon_sql = "INSERT INTO sdb_archive_order_coupon(".$keys.",archive_time) 
                             SELECT ".$keys.",".time()." AS archive_time 
                             FROM sdb_ome_order_coupon 
                             WHERE order_id IN(".$orderIdstr.") 
                             AND `id` NOT IN (SELECT `id` FROM sdb_archive_order_coupon)";
        
        $coupon_result = $this->db->exec($order_coupon_sql);
        if (!$coupon_result) {
            throw new Exception("Failed to create order coupon archive");
        }
        
        return true;
    }

    /**
     * 删除订单优惠券数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_coupon($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        // 删除订单相关的优惠券数据
        $deleteSql = "DELETE FROM sdb_ome_order_coupon WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order coupon data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单发票归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_invoice($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`id`,`order_id`,`tax_title`,`tax_no`,`register_no`,`invoice_kind`,`title_type`,`create_time`';
        $order_invoice_sql = "INSERT INTO sdb_archive_order_invoice(".$keys.",archive_time) 
                              SELECT ".$keys.",".time()." AS archive_time 
                              FROM sdb_ome_order_invoice 
                              WHERE order_id IN(".$orderIdstr.") 
                              AND `id` NOT IN (SELECT `id` FROM sdb_archive_order_invoice)";
        
        $invoice_result = $this->db->exec($order_invoice_sql);
        if (!$invoice_result) {
            throw new Exception("Failed to create order invoice archive");
        }
        
        return true;
    }

    /**
     * 删除订单发票数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_invoice($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_invoice WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order invoice data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单福袋归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_luckybag($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`lid`,`order_id`,`obj_id`,`item_id`,`combine_id`,`combine_bn`,`bm_id`,`selected_number`,`include_number`,`real_ratio`,`price_rate`,`at_time`,`up_time`';
        $order_luckybag_sql = "INSERT INTO sdb_archive_order_luckybag(".$keys.",archive_time) 
                               SELECT ".$keys.",".time()." AS archive_time 
                               FROM sdb_ome_order_luckybag 
                               WHERE order_id IN(".$orderIdstr.") 
                               AND `lid` NOT IN (SELECT `lid` FROM sdb_archive_order_luckybag)";
        
        $luckybag_result = $this->db->exec($order_luckybag_sql);
        if (!$luckybag_result) {
            throw new Exception("Failed to create order luckybag archive");
        }
        
        return true;
    }

    /**
     * 删除订单福袋数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_luckybag($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_luckybag WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order luckybag data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单对象检测项归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_objects_check_items($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`check_item_id`,`order_id`,`object_comp_key`,`obj_type`,`shop_goods_id`,`bn`,`channel`,`problem_desc`,`order_label`,`image_fileid_list`,`image_list`,`video_fileid_list`,`video_list`,`delivery_warehouse`,`order_sn`,`first_classification`,`second_classification`,`third_classification`,`at_time`,`up_time`';
        $order_check_items_sql = "INSERT INTO sdb_archive_order_objects_check_items(".$keys.",archive_time) 
                                  SELECT ".$keys.",".time()." AS archive_time 
                                  FROM sdb_ome_order_objects_check_items 
                                  WHERE order_id IN(".$orderIdstr.") 
                                  AND `check_item_id` NOT IN (SELECT `check_item_id` FROM sdb_archive_order_objects_check_items)";
        
        $check_items_result = $this->db->exec($order_check_items_sql);
        if (!$check_items_result) {
            throw new Exception("Failed to create order objects check items archive");
        }
        
        return true;
    }

    /**
     * 删除订单对象检测项数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_objects_check_items($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_objects_check_items WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order objects check items data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单对象优惠券归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_objects_coupon($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`id`,`order_id`,`order_bn`,`num`,`material_name`,`material_bn`,`oid`,`create_time`,`shop_id`,`shop_type`,`addon`,`source`,`org_id`';
        $order_objects_coupon_sql = "INSERT INTO sdb_archive_order_objects_coupon(".$keys.",archive_time) 
                                     SELECT ".$keys.",".time()." AS archive_time 
                                     FROM sdb_ome_order_objects_coupon 
                                     WHERE order_id IN(".$orderIdstr.") 
                                     AND `id` NOT IN (SELECT `id` FROM sdb_archive_order_objects_coupon)";
        
        $objects_coupon_result = $this->db->exec($order_objects_coupon_sql);
        if (!$objects_coupon_result) {
            throw new Exception("Failed to create order objects coupon archive");
        }
        
        return true;
    }

    /**
     * 删除订单对象优惠券数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_objects_coupon($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_objects_coupon WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order objects coupon data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单对象扩展归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_objects_extend($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`obj_id`,`order_id`,`store_dly_type`,`store_bn`,`customization`,`at_time`,`up_time`';
        $order_objects_extend_sql = "INSERT INTO sdb_archive_order_objects_extend(".$keys.",archive_time) 
                                     SELECT ".$keys.",".time()." AS archive_time 
                                     FROM sdb_ome_order_objects_extend 
                                     WHERE order_id IN(".$orderIdstr.") 
                                     AND `obj_id` NOT IN (SELECT `obj_id` FROM sdb_archive_order_objects_extend)";
        
        $objects_extend_result = $this->db->exec($order_objects_extend_sql);
        if (!$objects_extend_result) {
            throw new Exception("Failed to create order objects extend archive");
        }
        
        return true;
    }

    /**
     * 删除订单对象扩展数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_objects_extend($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_objects_extend WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order objects extend data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单促销归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_pmt($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`id`,`order_id`,`pmt_amount`,`pmt_memo`,`pmt_describe`,`coupon_id`,`up_time`';
        $order_pmt_sql = "INSERT INTO sdb_archive_order_pmt(".$keys.",archive_time) 
                          SELECT ".$keys.",".time()." AS archive_time 
                          FROM sdb_ome_order_pmt 
                          WHERE order_id IN(".$orderIdstr.") 
                          AND `id` NOT IN (SELECT `id` FROM sdb_archive_order_pmt)";
        
        $pmt_result = $this->db->exec($order_pmt_sql);
        if (!$pmt_result) {
            throw new Exception("Failed to create order pmt archive");
        }
        
        return true;
    }

    /**
     * 删除订单促销数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_pmt($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_pmt WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order pmt data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单收货人归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_receiver($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`order_id`,`encrypt_source_data`,`platform_country_id`,`platform_province_id`,`platform_city_id`,`platform_district_id`,`platform_town_id`,`ship_province`,`ship_city`,`ship_district`,`ship_town`,`ship_village`';
        $order_receiver_sql = "INSERT INTO sdb_archive_order_receiver(".$keys.",archive_time) 
                               SELECT ".$keys.",".time()." AS archive_time 
                               FROM sdb_ome_order_receiver 
                               WHERE order_id IN(".$orderIdstr.") 
                               AND `order_id` NOT IN (SELECT `order_id` FROM sdb_archive_order_receiver)";
        
        $receiver_result = $this->db->exec($order_receiver_sql);
        if (!$receiver_result) {
            throw new Exception("Failed to create order receiver archive");
        }
        
        return true;
    }

    /**
     * 删除订单收货人数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_receiver($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_receiver WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order receiver data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单代销归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_selling_agent($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`selling_agent_id`,`order_id`,`uname`,`level`,`name`,`birthday`,`sex`,`email`,`area`,`addr`,`zip`,`mobile`,`tel`,`qq`,`website_name`,`website_domain`,`website_logo`,`addon`,`seller_name`,`seller_mobile`,`seller_phone`,`seller_zip`,`seller_area`,`seller_address`,`print_status`';
        $order_selling_agent_sql = "INSERT INTO sdb_archive_order_selling_agent(".$keys.",archive_time) 
                                    SELECT ".$keys.",".time()." AS archive_time 
                                    FROM sdb_ome_order_selling_agent 
                                    WHERE order_id IN(".$orderIdstr.") 
                                    AND `selling_agent_id` NOT IN (SELECT `selling_agent_id` FROM sdb_archive_order_selling_agent)";
        
        $selling_agent_result = $this->db->exec($order_selling_agent_sql);
        if (!$selling_agent_result) {
            throw new Exception("Failed to create order selling agent archive");
        }
        
        return true;
    }

    /**
     * 删除订单代销数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_selling_agent($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_selling_agent WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order selling agent data");
        }
        
        return $delete_result;
    }

    /**
     * 创建订单服务归档
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _create_order_service($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $keys = '`id`,`order_id`,`item_oid`,`tmser_spu_code`,`sale_price`,`num`,`total_fee`,`type`,`type_alias`,`title`,`service_id`,`refund_id`';
        $order_service_sql = "INSERT INTO sdb_archive_order_service(".$keys.",archive_time) 
                              SELECT ".$keys.",".time()." AS archive_time 
                              FROM sdb_ome_order_service 
                              WHERE order_id IN(".$orderIdstr.") 
                              AND `id` NOT IN (SELECT `id` FROM sdb_archive_order_service)";
        
        $service_result = $this->db->exec($order_service_sql);
        if (!$service_result) {
            throw new Exception("Failed to create order service archive");
        }
        
        return true;
    }

    /**
     * 删除订单服务数据
     * @param array $order_list 订单列表
     * @return bool 处理结果
     * @access public
     * @author system
     */
    public function _delete_order_service($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        $deleteSql = "DELETE FROM sdb_ome_order_service WHERE order_id IN(".$orderIdstr.")";
        $delete_result = $this->db->exec($deleteSql);
        
        if (!$delete_result) {
            throw new Exception("Failed to delete original order service data");
        }
        
        return $delete_result;
    }

    /**
     * 获取订单数据
     * @param $orderfilter
     * @return array 订单数据列表
     * @date 2025-01-22
     */
    function get_data($orderfilter)
    {
        $sqlstr = "WHERE (`archive`='1' OR `status` in('dead') ";
        
        if (isset($orderfilter['archive_time'])) {
            $archive_time = $this->archivetimeFilter($orderfilter['archive_time']);
        } else {
            // 默认使用12个月前的数据
            $archive_time = $this->archivetimeFilter('12');
        }
        
        $status = $orderfilter['status'];
        
        if ($status) {
            if (in_array('fail',$status)) {
                $sqlstr.= " OR (pay_status='0' AND process_status in ('unconfirmed'))";
            }
            if (in_array('unpayed',$status)) {
                $sqlstr.= " OR (is_fail='true' AND pay_status in ('0','1'))";
            }
        }
        $sqlstr.=') AND createtime<'.$archive_time;
        
        // 添加 order_id 范围条件
        if (isset($orderfilter['order_id_start']) && $orderfilter['order_id_start'] > 0) {
            $sqlstr .= " AND order_id > " . intval($orderfilter['order_id_start']);
        }
        
        if (isset($orderfilter['order_id_end']) && $orderfilter['order_id_end'] > 0) {
            $sqlstr .= " AND order_id <= " . intval($orderfilter['order_id_end']);
        }
        
        // 设置限制条数
        $limit = isset($orderfilter['limit']) ? intval($orderfilter['limit']) : 500;
        
        $order_list = $this->db->select("SELECT `order_id`, `createtime` FROM sdb_ome_orders ".$sqlstr." ORDER BY order_id ASC LIMIT 0,".$limit);
        
        return $order_list;
    }
    
    /**
     * 创建销售单归档
     * @param array $order_list 订单列表
     * @return boolean
     * @access  public
     * @author system
     */
    function _create_sales($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        // 先查询出相关的销售单ID
        $sales_sql = "SELECT sale_id FROM sdb_ome_sales WHERE order_id in(".$orderIdstr.")";
        $salesList = $this->db->select($sales_sql);
        
        if (empty($salesList)) {
            return true;
        }
        
        $saleIds = array();
        foreach ($salesList as $sale) {
            $saleIds[] = $sale['sale_id'];
        }
        $saleIdstr = "'".implode("','",$saleIds)."'";
        
        // 优化销售单主表插入SQL
        $copy_sales_sql = "INSERT INTO sdb_archive_sales(
            `sale_id`, `sale_bn`, `order_id`, `order_bn`, `iostock_bn`, `order_type`, 
            `sale_time`, `total_amount`, `cost_freight`, `sale_amount`, `payment`, 
            `delivery_cost`, `additional_costs`, `deposit`, `discount`, `operator`, 
            `member_id`, `delivery_cost_actual`, `delivery_id`, `is_tax`, `shop_id`, 
            `shopping_guide`, `cost`, `memo`, `branch_id`, `pay_status`, `logi_id`, 
            `logi_name`, `logi_no`, `order_check_id`, `order_check_time`, 
            `order_create_time`, `paytime`, `ship_time`, `archive`, `selling_agent_id`, 
            `service_price`, `refund_money`, `shop_type`, `org_id`, `check`, `check_msg`, 
            `settlement_amount`, `actually_amount`, `platform_amount`, `platform_pay_amount`, 
            `platform_order_bn`, `order_bool_type`, `extra_info`, `up_time`, `order_source`, 
            `addon`, `betc_id`, `cos_id`, `archive_time`
        ) SELECT 
            `sale_id`, `sale_bn`, `order_id`, `order_bn`, `iostock_bn`, `order_type`, 
            `sale_time`, `total_amount`, `cost_freight`, `sale_amount`, `payment`, 
            `delivery_cost`, `additional_costs`, `deposit`, `discount`, `operator`, 
            `member_id`, `delivery_cost_actual`, `delivery_id`, `is_tax`, `shop_id`, 
            `shopping_guide`, `cost`, `memo`, `branch_id`, `pay_status`, `logi_id`, 
            `logi_name`, `logi_no`, `order_check_id`, `order_check_time`, 
            `order_create_time`, `paytime`, `ship_time`, `archive`, `selling_agent_id`, 
            `service_price`, `refund_money`, `shop_type`, `org_id`, `check`, `check_msg`, 
            `settlement_amount`, `actually_amount`, `platform_amount`, `platform_pay_amount`, 
            `platform_order_bn`, `order_bool_type`, `extra_info`, `up_time`, `order_source`, 
            `addon`, `betc_id`, `cos_id`, ".time()." AS archive_time
        FROM sdb_ome_sales 
        WHERE sale_id in(".$saleIdstr.") 
        AND sale_id NOT IN (SELECT sale_id FROM sdb_archive_sales)";
        
        $sales_result = $this->db->exec($copy_sales_sql);
        if (!$sales_result) {
            throw new Exception("Failed to create sales archive");
        }
        
        // 优化销售单明细表插入SQL
        $copy_sales_items_sql = "INSERT INTO sdb_archive_sales_items(
            `item_id`, `sale_id`, `product_id`, `bn`, `name`, `pmt_price`, 
            `orginal_price`, `price`, `spec_name`, `nums`, `sales_amount`, 
            `sale_price`, `apportion_pmt`, `refund_money`, `cost`, `cost_amount`, 
            `gross_sales`, `gross_sales_rate`, `cost_tax`, `branch_id`, `iostock_id`, 
            `sales_material_bn`, `obj_type`, `s_type`, `addon`, `oid`, `order_item_id`, 
            `obj_id`, `platform_amount`, `settlement_amount`, `actually_amount`, 
            `platform_pay_amount`, `sell_code`, `archive_time`
        ) SELECT 
            `item_id`, `sale_id`, `product_id`, `bn`, `name`, `pmt_price`, 
            `orginal_price`, `price`, `spec_name`, `nums`, `sales_amount`, 
            `sale_price`, `apportion_pmt`, `refund_money`, `cost`, `cost_amount`, 
            `gross_sales`, `gross_sales_rate`, `cost_tax`, `branch_id`, `iostock_id`, 
            `sales_material_bn`, `obj_type`, `s_type`, `addon`, `oid`, `order_item_id`, 
            `obj_id`, `platform_amount`, `settlement_amount`, `actually_amount`, 
            `platform_pay_amount`, `sell_code`, ".time()." AS archive_time
        FROM sdb_ome_sales_items 
        WHERE sale_id in(".$saleIdstr.") 
        AND item_id NOT IN (SELECT item_id FROM sdb_archive_sales_items)";
        
        $sales_items_result = $this->db->exec($copy_sales_items_sql);
        if (!$sales_items_result) {
            throw new Exception("Failed to create sales items archive");
        }
        
        // 优化销售单对象表插入SQL
        $copy_sales_objects_sql = "INSERT INTO sdb_archive_sales_objects(
            `obj_id`, `sale_id`, `order_id`, `order_obj_id`, `obj_type`, `goods_id`, 
            `goods_bn`, `goods_name`, `quantity`, `price`, `sale_price`, `pmt_price`, 
            `apportion_pmt`, `sales_amount`, `refund_money`, `cost`, `cost_amount`, 
            `cost_tax`, `iostock_id`, `oid`, `settlement_amount`, `actually_amount`, 
            `platform_amount`, `platform_pay_amount`, `archive_time`
        ) SELECT 
            `obj_id`, `sale_id`, `order_id`, `order_obj_id`, `obj_type`, `goods_id`, 
            `goods_bn`, `goods_name`, `quantity`, `price`, `sale_price`, `pmt_price`, 
            `apportion_pmt`, `sales_amount`, `refund_money`, `cost`, `cost_amount`, 
            `cost_tax`, `iostock_id`, `oid`, `settlement_amount`, `actually_amount`, 
            `platform_amount`, `platform_pay_amount`, ".time()." AS archive_time
        FROM sdb_ome_sales_objects 
        WHERE sale_id in(".$saleIdstr.") 
        AND obj_id NOT IN (SELECT obj_id FROM sdb_archive_sales_objects)";
        
        $sales_objects_result = $this->db->exec($copy_sales_objects_sql);
        if (!$sales_objects_result) {
            throw new Exception("Failed to create sales objects archive");
        }
        
        // 删除原始销售单数据
        $delete_sales_objects_sql = "DELETE FROM sdb_ome_sales_objects WHERE sale_id in(".$saleIdstr.")";
        $delete_sales_objects_result = $this->db->exec($delete_sales_objects_sql);
        if (!$delete_sales_objects_result) {
            throw new Exception("Failed to delete original sales objects data");
        }
        
        $delete_sales_items_sql = "DELETE FROM sdb_ome_sales_items WHERE sale_id in(".$saleIdstr.")";
        $delete_sales_items_result = $this->db->exec($delete_sales_items_sql);
        if (!$delete_sales_items_result) {
            throw new Exception("Failed to delete original sales items data");
        }
        
        $delete_sales_sql = "DELETE FROM sdb_ome_sales WHERE sale_id in(".$saleIdstr.")";
        $delete_sales_result = $this->db->exec($delete_sales_sql);
        if (!$delete_sales_result) {
            throw new Exception("Failed to delete original sales data");
        }
        
        return true;
    }
    
    /**
     * 创建售后单归档
     * @param array $order_list 订单列表
     * @return boolean
     * @access  public
     * @author system
     */
    function _create_aftersale($order_list)
    {
        $orderIds = array();
        foreach ($order_list as $order) {
            $orderIds[] = $order['order_id'];
        }
        
        if (empty($orderIds)) {
            return true;
        }
        
        $orderIdstr = "'".implode("','",$orderIds)."'";
        
        // 先查询出相关的售后单ID
        $aftersale_sql = "SELECT aftersale_id FROM sdb_sales_aftersale WHERE order_id in(".$orderIdstr.")";
        $aftersaleList = $this->db->select($aftersale_sql);
        
        if (empty($aftersaleList)) {
            return true;
        }
        
        $aftersaleIds = array();
        foreach ($aftersaleList as $aftersale) {
            $aftersaleIds[] = $aftersale['aftersale_id'];
        }
        $aftersaleIdstr = "'".implode("','",$aftersaleIds)."'";
        
        // 优化售后单主表插入SQL
        $copy_aftersale_sql = "INSERT INTO sdb_archive_aftersale(
            `aftersale_id`, `aftersale_bn`, `shop_id`, `shop_bn`, `shop_name`, `order_id`, 
            `order_bn`, `return_id`, `return_bn`, `reship_id`, `reship_bn`, `delivery_id`, 
            `delivery_bn`, `return_apply_id`, `return_apply_bn`, `return_type`, 
            `refund_apply_money`, `refundmoney`, `real_refund_amount`, `paymethod`, 
            `member_id`, `member_uname`, `ship_mobile`, `add_time`, `check_time`, 
            `acttime`, `refundtime`, `check_op_id`, `check_op_name`, `op_id`, `op_name`, 
            `refund_op_id`, `refund_op_name`, `aftersale_time`, `receiving_status`, 
            `trigger_event`, `diff_order_bn`, `change_order_bn`, `pay_type`, `account`, 
            `bank`, `pay_account`, `refund_apply_time`, `problem_name`, `archive`, 
            `org_id`, `platform_order_bn`, `settlement_amount`, `platform_amount`, 
            `actually_amount`, `platform_pay_amount`, `ship_time`, `need_refundmoney`, 
            `order_bool_type`, `extra_info`, `at_time`, `up_time`, `betc_id`, `cos_id`, 
            `archive_time`
        ) SELECT 
            `aftersale_id`, `aftersale_bn`, `shop_id`, `shop_bn`, `shop_name`, `order_id`, 
            `order_bn`, `return_id`, `return_bn`, `reship_id`, `reship_bn`, `delivery_id`, 
            `delivery_bn`, `return_apply_id`, `return_apply_bn`, `return_type`, 
            `refund_apply_money`, `refundmoney`, `real_refund_amount`, `paymethod`, 
            `member_id`, `member_uname`, `ship_mobile`, `add_time`, `check_time`, 
            `acttime`, `refundtime`, `check_op_id`, `check_op_name`, `op_id`, `op_name`, 
            `refund_op_id`, `refund_op_name`, `aftersale_time`, `receiving_status`, 
            `trigger_event`, `diff_order_bn`, `change_order_bn`, `pay_type`, `account`, 
            `bank`, `pay_account`, `refund_apply_time`, `problem_name`, `archive`, 
            `org_id`, `platform_order_bn`, `settlement_amount`, `platform_amount`, 
            `actually_amount`, `platform_pay_amount`, `ship_time`, `need_refundmoney`, 
            `order_bool_type`, `extra_info`, `at_time`, `up_time`, `betc_id`, `cos_id`, 
            ".time()." AS archive_time
        FROM sdb_sales_aftersale 
        WHERE aftersale_id in(".$aftersaleIdstr.") 
        AND aftersale_id NOT IN (SELECT aftersale_id FROM sdb_archive_aftersale)";
        
        $aftersale_result = $this->db->exec($copy_aftersale_sql);
        if (!$aftersale_result) {
            throw new Exception("Failed to create aftersale archive");
        }
        
        // 优化售后单明细表插入SQL
        $copy_aftersale_items_sql = "INSERT INTO sdb_archive_aftersale_items(
            `item_id`, `aftersale_id`, `obj_item_id`, `bn`, `product_name`, `product_id`, 
            `apply_num`, `num`, `defective_num`, `normal_num`, `price`, `saleprice`, 
            `branch_id`, `branch_name`, `return_type`, `pay_type`, `account`, `bank`, 
            `pay_account`, `money`, `refunded`, `payment`, `create_time`, `last_modified`, 
            `cost`, `cost_amount`, `order_item_id`, `item_type`, `sales_material_bn`, 
            `addon`, `settlement_amount`, `platform_amount`, `actually_amount`, 
            `platform_pay_amount`, `oid`, `archive_time`
        ) SELECT 
            `item_id`, `aftersale_id`, `obj_item_id`, `bn`, `product_name`, `product_id`, 
            `apply_num`, `num`, `defective_num`, `normal_num`, `price`, `saleprice`, 
            `branch_id`, `branch_name`, `return_type`, `pay_type`, `account`, `bank`, 
            `pay_account`, `money`, `refunded`, `payment`, `create_time`, `last_modified`, 
            `cost`, `cost_amount`, `order_item_id`, `item_type`, `sales_material_bn`, 
            `addon`, `settlement_amount`, `platform_amount`, `actually_amount`, 
            `platform_pay_amount`, `oid`, ".time()." AS archive_time
        FROM sdb_sales_aftersale_items 
        WHERE aftersale_id in(".$aftersaleIdstr.") 
        AND item_id NOT IN (SELECT item_id FROM sdb_archive_aftersale_items)";
        
        $aftersale_items_result = $this->db->exec($copy_aftersale_items_sql);
        if (!$aftersale_items_result) {
            throw new Exception("Failed to create aftersale items archive");
        }
        
        // 删除原始售后单数据
        $delete_aftersale_items_sql = "DELETE FROM sdb_sales_aftersale_items WHERE aftersale_id in(".$aftersaleIdstr.")";
        $delete_aftersale_items_result = $this->db->exec($delete_aftersale_items_sql);
        if (!$delete_aftersale_items_result) {
            throw new Exception("Failed to delete original aftersale items data");
        }
        
        $delete_aftersale_sql = "DELETE FROM sdb_sales_aftersale WHERE aftersale_id in(".$aftersaleIdstr.")";
        $delete_aftersale_result = $this->db->exec($delete_aftersale_sql);
        if (!$delete_aftersale_result) {
            throw new Exception("Failed to delete original aftersale data");
        }
        
        return true;
    }
    
    /**
     * 创建发货销售单归档
     * @param array $delivery_list 发货单列表
     * @return boolean
     * @access  public
     * @author system
     */
    function _create_sales_delivery_order($delivery_list)
    {
        $deliveryIds = array();
        foreach ($delivery_list as $delivery) {
            $deliveryIds[] = $delivery['delivery_id'];
        }
        
        if (empty($deliveryIds)) {
            return true;
        }
        
        $deliveryIdstr = "'".implode("','",$deliveryIds)."'";
        
        // 优化发货销售单主表插入SQL
        $copy_delivery_sql = "INSERT INTO sdb_archive_sales_delivery_order(
            `delivery_id`, `delivery_bn`, `shop_id`, `shop_type`, `member_id`,
            `delivery_time`, `sale_time`, `org_id`, `logi_id`, `logi_name`, `logi_no`,
            `ship_name`, `ship_area`, `ship_province`, `ship_city`, `ship_district`,
            `ship_addr`, `ship_zip`, `ship_tel`, `ship_mobile`, `ship_email`,
            `branch_id`, `addon`, `archive_time`
        ) SELECT
            `delivery_id`, `delivery_bn`, `shop_id`, `shop_type`, `member_id`,
            `delivery_time`, `sale_time`, `org_id`, `logi_id`, `logi_name`, `logi_no`,
            `ship_name`, `ship_area`, `ship_province`, `ship_city`, `ship_district`,
            `ship_addr`, `ship_zip`, `ship_tel`, `ship_mobile`, `ship_email`,
            `branch_id`, `addon`, ".time()." AS archive_time
        FROM sdb_sales_delivery_order
        WHERE delivery_id in(".$deliveryIdstr.")
        AND delivery_id NOT IN (SELECT delivery_id FROM sdb_archive_sales_delivery_order)";
        
        $delivery_result = $this->db->exec($copy_delivery_sql);
        if (!$delivery_result) {
            throw new Exception("Failed to create sales delivery order archive");
        }
        
        // 删除原始发货销售单数据
        $delete_delivery_sql = "DELETE FROM sdb_sales_delivery_order WHERE delivery_id in(".$deliveryIdstr.")";
        $delete_delivery_result = $this->db->exec($delete_delivery_sql);
        if (!$delete_delivery_result) {
            throw new Exception("Failed to delete original sales delivery order data");
        }
        
        return true;
    }
    
    /**
     * 创建发货销售单明细归档
     * @param array $delivery_list 发货单列表
     * @return boolean
     * @access  public
     * @author system
     */
    function _create_sales_delivery_order_item($delivery_list)
    {
        $deliveryIds = array();
        foreach ($delivery_list as $delivery) {
            $deliveryIds[] = $delivery['delivery_id'];
        }
        
        if (empty($deliveryIds)) {
            return true;
        }
        
        $deliveryIdstr = "'".implode("','",$deliveryIds)."'";
        
        // 优化发货销售单明细表插入SQL
        $copy_delivery_item_sql = "INSERT INTO sdb_archive_sales_delivery_order_item(
            `id`, `shop_id`, `shop_bn`, `shop_type`, `branch_id`, `branch_bn`,
            `delivery_id`, `delivery_item_id`, `order_id`, `order_bn`, `delivery_bn`,
            `order_obj_id`, `order_item_id`, `obj_type`, `product_id`, `sales_material_bn`,
            `bn`, `name`, `price`, `nums`, `pmt_price`, `sale_price`, `apportion_pmt`,
            `sales_amount`, `platform_amount`, `settlement_amount`, `actually_amount`,
            `platform_pay_amount`, `return_num`, `return_amount`, `delivery_time`,
            `order_create_time`, `order_pay_time`, `sale_time`, `s_type`, `org_id`,
            `addon`, `at_time`, `up_time`, `oid`, `archive_time`
        ) SELECT
            `id`, `shop_id`, `shop_bn`, `shop_type`, `branch_id`, `branch_bn`,
            `delivery_id`, `delivery_item_id`, `order_id`, `order_bn`, `delivery_bn`,
            `order_obj_id`, `order_item_id`, `obj_type`, `product_id`, `sales_material_bn`,
            `bn`, `name`, `price`, `nums`, `pmt_price`, `sale_price`, `apportion_pmt`,
            `sales_amount`, `platform_amount`, `settlement_amount`, `actually_amount`,
            `platform_pay_amount`, `return_num`, `return_amount`, `delivery_time`,
            `order_create_time`, `order_pay_time`, `sale_time`, `s_type`, `org_id`,
            `addon`, `at_time`, `up_time`, `oid`, ".time()." AS archive_time
        FROM sdb_sales_delivery_order_item
        WHERE delivery_id in(".$deliveryIdstr.")
        AND id NOT IN (SELECT id FROM sdb_archive_sales_delivery_order_item)";
        
        $delivery_item_result = $this->db->exec($copy_delivery_item_sql);
        if (!$delivery_item_result) {
            throw new Exception("Failed to create sales delivery order item archive");
        }
        
        // 删除原始发货销售单明细数据
        $delete_delivery_item_sql = "DELETE FROM sdb_sales_delivery_order_item WHERE delivery_id in(".$deliveryIdstr.")";
        $delete_delivery_item_result = $this->db->exec($delete_delivery_item_sql);
        if (!$delete_delivery_item_result) {
            throw new Exception("Failed to delete original sales delivery order item data");
        }
        
        return true;
    }
    
}

?>
