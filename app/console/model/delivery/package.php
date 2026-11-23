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
 * 物流包裹明细列表Model类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_mdl_delivery_package extends dbeav_model
{
    var $defaultOrder = array('package_id',' DESC');
    
    //是否支持导出字段定义
    var $has_export_cnf = true;
    
    //导出的文件名
    var $export_name = '物流包裹明细';
    
    var $ioTitle = array();
    var $export_flag = false;
    
    public function table_name($real = false)
    {
        if($real){
            $table_name = 'sdb_ome_delivery_package';
        }else{
            $table_name = 'delivery_package';
        }
        
        return $table_name;
    }
    
    public function get_schema(){
        return app::get('ome')->model('delivery_package')->get_schema();
    }
    
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = '';

        // 多包裹号查询
        if($filter['package_bn'] && is_string($filter['package_bn']) && strpos($filter['package_bn'], "\n") !== false){
            $filter['package_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['package_bn']))));
        }

        // 界面高级筛选多订单号查询
        if($filter['order_bn'] && is_string($filter['order_bn']) && strpos($filter['order_bn'], "\n") !== false){
            $order_bns = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));

            $orderMdl = app::get('ome')->model('orders');
            $deliveryOrderMdl = app::get('ome')->model('delivery_order');

            $order_list = $orderMdl->getList('order_id', ['order_bn' => $order_bns]);
            $f_order_id = array_column($order_list, 'order_id');

            $f_delivery_id = [0];
            if ($f_order_id) {
                $delivery_order_list = $deliveryOrderMdl->getList('delivery_id' , ['order_id' => $f_order_id]);

                $f_delivery_id = array_column($delivery_order_list, 'delivery_id');
            }

            $where .= '  AND delivery_id IN ('.implode(',', $f_delivery_id).')';

            unset($filter['order_bn']);
        }elseif($filter['order_bn']){
            //按单个订单号查询
            $orderObj = app::get('ome')->model('orders');
            $rows = $orderObj->getList('order_id', array('order_bn'=>$filter['order_bn']));
            $orderId[] = 0;
            foreach($rows as $row){
                $orderId[] = $row['order_id'];
            }
            
            $dlyOrderObj = app::get('ome')->model('delivery_order');
            $rows = $dlyOrderObj->getList('delivery_id', array('order_id'=>$orderId));
            
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            
            $where .= '  AND delivery_id IN ('.implode(',', $deliveryId).')';
            unset($filter['order_bn']);
        }
        
        //按发货单号查询
        if($filter['delivery_bn']){
            $deliveryObj = app::get('ome')->model('delivery');
            $rows = $deliveryObj->getList('delivery_id', array('delivery_bn'=>$filter['delivery_bn']));
            
            $deliveryId = array(0);
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            
            $where .= '  AND delivery_id IN ('.implode(',', $deliveryId).')';
            unset($filter['delivery_bn']);
        }
        
        /****
        //订单付款状态和发货状态
        $order_filter = array();
        if($filter['pay_status'] && $filter['ship_status']){
            $order_filter = array();
            
            $order_filter['pay_status'] = $filter['pay_status'];
            $order_filter['ship_status'] = $filter['ship_status'];
            
            unset($filter['pay_status'], $filter['ship_status']);
        }elseif($filter['pay_status']){
            $order_filter = array();
            
            $order_filter['pay_status'] = $filter['pay_status'];
            
            unset($filter['pay_status']);
        }elseif($filter['ship_status']){
            $order_filter = array();
            
            $order_filter['ship_status'] = $filter['ship_status'];
            
            unset($filter['ship_status']);
        }
        
        if($order_filter){
            $orderIds = array();
            
            $orderObj = app::get('ome')->model('orders');
            $rows = $orderObj->getList('order_id', $order_filter);
            foreach($rows as $row){
                $orderIds[] = $row['order_id'];
            }
            
            if(empty($orderIds)){
                $orderIds[] = 0;
            }
            
            $dlyOrderObj = app::get('ome')->model('delivery_order');
            $rows = $dlyOrderObj->getList('delivery_id', array('order_id'=>$orderIds));
            
            $deliveryId[] = 0;
            foreach($rows as $row){
                $deliveryId[] = $row['delivery_id'];
            }
            
            $where .= '  AND delivery_id IN ('.implode(',', $deliveryId).')';
        }
        ****/
        
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }
    
    /**
     * 添加搜索项
     */
    function searchOptions()
    {
        $parentOptions = parent::searchOptions();
        
        $childOptions = array(
                'package_bn' => app::get('ome')->_('京东订单号'),
                'order_bn' => app::get('ome')->_('订单号'),
                'delivery_bn' => app::get('ome')->_('发货单号'),
        );
        
        return array_merge($parentOptions, $childOptions);
    }
    
    /**
     * 格式化包裹状态
     */
    function modifier_status($package_status)
    {
        $deliveryLib = kernel::single('console_delivery');
        
        $status = '';
        if($package_status){
            $status = $deliveryLib->getPackageStatus($package_status);
        }
        
        return $status;
    }
    
    /**
     * 格式化包裹配送状态
     */
    function modifier_shipping_status($shipping_status)
    {
        $deliveryLib = kernel::single('console_delivery');
        
        $status = '';
        if($shipping_status){
            $status = $deliveryLib->getShippingStatus($shipping_status);
        }
        
        return $status;
    }
    
    /**
     * 导入导出的标题
     *
     * @param Null
     * @return Array
     */
    function io_title($filter, $ioType='csv')
    {
        switch($filter)
        {
            case 'package':
                $this->oSchema['csv'][$filter] = array(
                    '*:包裹号' => 'package_bn',
                    '*:订单号' => 'order_bn',
                    '*:发货单号' => 'delivery_bn',
                    '*:基础物料号' => 'bn',
                    '*:外部sku' => 'outer_sku',
                    '*:包裹状态' => 'status',
                    '*:配送状态' => 'shipping_status',
                    '*:数量' => 'number',
                    '*:物流公司编码' => 'logi_bn',
                    '*:物流单号' => 'logi_no',
                    '*:创建时间' => 'create_time',
                    '*:付款状态' => 'pay_status',
                    '*:发货状态' => 'ship_status',
                );
                break;
            default:
                $this->oSchema['csv'][$filter] = array();
        }
        
        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema[$ioType][$filter]);
        
        return $this->ioTitle[$ioType][$filter];
    }
    
    /**
     * 导出模板的标题
     *
     * @param Null
     * @return array
     */
    function exportTemplate($filter)
    {
        foreach ($this->io_title($filter) as $v)
        {
            $title[] = $v;
        }
        
        return $title;
    }
    
    /**
     * 整理导出数据
     **/
    function fgetlist_csv(&$data, $filter, $offset, $exportType=1)
    {
        unset($filter['_io_type']);
        
        @ini_set('memory_limit','1024M');
        set_time_limit(0);
        
        $deliveryLib = kernel::single('console_delivery');
        
        $this->export_flag = true;
        $limit = 100;
        
        //限制导出的最大页码数(最多一次导出1w条记录)
        $max_offset = 100;
        if ($offset>$max_offset){
            return false;
        }
        
        //标题
        if(empty($data['title'])){
            $title = array();
            foreach($this->io_title('package') as $key => $val){
                $title[] = $val;
            }
            $data['title'][] = '"'. implode('","', $title) .'"';
        }
        
        //列表
        $dataList = $this->getList('*', $filter, $offset*$limit, $limit);
        if(empty($dataList)){
            return false;
        }
        
        $delivery_ids = array();
        foreach($dataList as $key => $val)
        {
            $temp_id = $val['delivery_id'];
            
            $delivery_ids[$temp_id] = $temp_id;
        }
        
        //订单号
        $sql = "SELECT a.delivery_id, b.order_id,b.order_bn,b.pay_status,b.ship_status FROM sdb_ome_delivery_order AS a 
                LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.delivery_id IN(". implode(',', $delivery_ids) .")";
        $tempList = $this->db->select($sql);
        
        $orderList = array();
        if($tempList){
            foreach ($tempList as $key => $val)
            {
                $delivery_id = $val['delivery_id'];
                
                $orderList[$delivery_id] = $val;
            }
        }
        
        //发货单号
        $sql = "SELECT a.delivery_id, b.delivery_bn FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id 
                WHERE a.delivery_id IN(". implode(',', $delivery_ids) .")";
        $tempList = $this->db->select($sql);
        
        $deliveryList = array();
        if($tempList){
            foreach ($tempList as $key => $val)
            {
                $delivery_id = $val['delivery_id'];
                
                $deliveryList[$delivery_id] = $val['delivery_bn'];
            }
        }
        
        $pay_status_list = array (
                0 => '未支付',
                1 => '已支付',
                2 => '处理中',
                3 => '部分付款',
                4 => '部分退款',
                5 => '全额退款',
                6 => '退款申请中',
                7 => '退款中',
                8 => '支付中',
        );
        
        $ship_status_list = array (
                0 => '未发货',
                1 => '已发货',
                2 => '部分发货',
                3 => '部分退货',
                4 => '已退货',
        );
        
        //格式化数据
        foreach($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $orderInfo = $orderList[$delivery_id];
            
            $delivery_bn = $deliveryList[$delivery_id];
            
            //格式化包裹配送状态
            if($val['status']){
                $package_status = $deliveryLib->getPackageStatus($val['status']);
            }
            
            //格式化包裹配送状态
            if($val['shipping_status']){
                $shipping_status = $deliveryLib->getShippingStatus($val['shipping_status']);
            }
            
            //付款状态
            $orderInfo['pay_status'] = $pay_status_list[$orderInfo['pay_status']];
            
            //发货状态
            $orderInfo['ship_status'] = $ship_status_list[$orderInfo['ship_status']];
            
            //export
            $exportData = array(
                    '*:包裹号' => $val['package_bn'],
                    '*:订单号' => $orderInfo['order_bn'],
                    '*:发货单号' => $delivery_bn,
                    '*:基础物料号' => $val['bn'],
                    '*:外部sku' => $val['outer_sku'],
                    '*:包裹状态' => $package_status,
                    '*:配送状态' => $shipping_status,
                    '*:数量' => $val['number'],
                    '*:物流公司编码' => $val['logi_bn'],
                    '*:物流单号' => $val['logi_no'],
                    '*:创建时间' => ($val['create_time'] ? date('Y-m-d H:i:s', $val['create_time']) : ''),
                    '*:付款状态' => $orderInfo['pay_status'],
                    '*:发货状态' => $orderInfo['ship_status'],
            );
            
            $data['contents'][] = '"'. implode('","', $exportData) .'"';
        }
        
        unset($dataList, $tempList, $orderList, $deliveryList);
        
        //不能return true，否则导出死循环
        return false;
    }
    
    function export_csv($data, $exportType=1)
    {
        $output = array();
        foreach($data['title'] as $k => $val){
            $output[] = kernel::single('base_charset')->utf2local($val);
        }
        
        foreach($data['contents'] as $k => $val){
            $output[] = kernel::single('base_charset')->utf2local($val);
        }
        
        echo implode("\n", $output);
    }
}