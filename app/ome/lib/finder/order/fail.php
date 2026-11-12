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

class ome_finder_order_fail
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "abnormal_status";
    
    var $detail_fail = '失败商品修正';
    function detail_fail($order_id){
        $render = app::get('ome')->render();
        $oOrder = app::get('ome')->model('orders');

        $orderInfo = $oOrder->dump($order_id , '*' , array('order_objects' => array('*',array('order_items' => array('*')))));
        //echo "<pre>";
        //var_dump($orderInfo['order_objects']);
        foreach($orderInfo['order_objects'] as $ko => &$obj){
            if($obj['goods_id'] <=0){
                //销售物料找不到的
                $obj['obj_fail'] = 1;
                continue;
            }

            if(!isset($obj['order_items'])){
                $obj['obj_item_fail'] = 1;
            }
        }

        $shopex_shop_list = ome_shop_type::shopex_shop_type();
        $shops = app::get('ome')->model('shop')->dump(array('shop_id'=>$orderInfo['shop_id']),'node_type');
        $render->pagedata['shop_type'] = in_array($shops['node_type'],$shopex_shop_list) ? 'shopex' : 'c2c';
        $render->pagedata['orderInfo'] = $orderInfo;
        $render->pagedata['object_alias'] = $oOrder->getOrderObjectAlias($order_id);
        return $render->fetch('admin/order/detail_fail.html');
    }
    
    /**
     * 订单操作记录
     *
     * @param int $order_id
     * @var string
     */
    var $detail_history = '订单操作记录';
    /**
     * detail_history
     * @param mixed $order_id ID
     * @return mixed 返回值
     */
    public function detail_history($order_id)
    {
        //订单信息
        $orderType = app::get('ome')->model('orders')->dump(array('order_id'=>$order_id), 'order_type');
        
        //加载模板
        return $this->__normal_log_history($order_id);
    }
    
    var $column_abnormal_mark = '异常标识';
    /**
     * column_abnormal_mark
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_abnormal_mark($row)
    {
        $abnormal_status = $row[$this->col_prefix.'abnormal_status'];
        if(empty($abnormal_status)){
            return '';
        }elseif($abnormal_status & ome_preprocess_const::__ORDER_LUCKY_FAIL ){
            //福袋订单异常
            return kernel::single('ome_preprocess_const')->getBoolTypeIdentifier($abnormal_status, $row[$this->col_prefix.'shop_type']);
        }
        
        return '';
    }
    
    var $column_abnormal_msg = '失败原因';
    var $column_abnormal_msg_order = 10;
    var $column_abnormal_msg_width = 350;
    /**
     * column_abnormal_msg
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_abnormal_msg($row, $list)
    {
        $order_id = $row['order_id'];
        
        $abnormalMsgInfo = $this->_getAbnormalMsgList($order_id, $list);
        
        return "<a href='javascript:void(0);' title='". $abnormalMsgInfo['abnormal_msg'] ."' style='text-decoration:none;'>". $abnormalMsgInfo['abnormal_msg'] ."</a>";
    }
    
    private function _getAbnormalMsgList($order_id, $list)
    {
        static $abnormalMsgList;
        
        //check
        if(isset($abnormalMsgList)) {
            return $abnormalMsgList[$order_id];
        }
        
        $orderIds = array();
        foreach($list as $val)
        {
            $orderIds[] = $val['order_id'];
        }
        
        //订单商品失败异常类型
        $abnormal_type = 'object_fail';
        
        //list
        $dataList = app::get('ome')->model('order_abnormal')->getList('aid,order_id,abnormal_msg', array('order_id'=>$orderIds, 'abnormal_type'=>$abnormal_type));
        $dataList = array_column($dataList, null, 'order_id');
        
        foreach ($orderIds as $orderKey => $id)
        {
            if(isset($dataList[$id])){
                $abnormalMsgList[$id] = $dataList[$id];
            }else{
                $abnormalMsgList[$id] = array();
            }
            
        }
        
        return $abnormalMsgList[$order_id];
    }
    
    /**
     * [普通]订单操作记录
     * 
     * @param int $order_id
     * @return string
     */
    private function __normal_log_history($order_id)
    {
        $render = app::get('ome')->render();
        $orderObj = app::get('ome')->model('orders');
        $logObj = app::get('ome')->model('operation_log');
        $deliveryObj = app::get('ome')->model('delivery');
        $ooObj = app::get('ome')->model('operations_order');
        
        /* 本订单日志 */
        $history = $logObj->read_log(array('obj_id'=>$order_id,'obj_type'=>'orders@ome'),0,-1);
        foreach($history as $k=>$v){
            $data = $ooObj->getList('operation_id',array('log_id'=>$v['log_id']));
            if(!empty($data)){
                $history[$k]['flag'] ='true';
            }else{
                $history[$k]['flag'] ='false';
            }
            $history[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            
            // 为长文本准备数据，HTML由模板处理
            $memo = $history[$k]['memo'];
            $memoLength = mb_strlen($memo);
            
            if ($memoLength > 400) {
                $history[$k]['short_memo'] = mb_substr($memo, 0, 400);
                $history[$k]['is_long'] = true;
            } else {
                $history[$k]['is_long'] = false;
            }
        }
        
        /* 发货单日志 */
        $delivery_ids = $deliveryObj->getDeliverIdByOrderId($order_id);
        if ($delivery_ids) {
            $deliverylog = $logObj->read_log(array('obj_id'=>$delivery_ids,'obj_type'=>'delivery@ome'), 0, -1);
        }
        
        //[拆单]多个发货单 格式化分开显示
        $dly_log_list   = array();
        foreach((array) $deliverylog as $k=>$v)
        {
            $deliverylog[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            
            $obj_id     = $v['obj_id'];
            $dly_log_list[$obj_id]['obj_name']  = $v['obj_name'];
            $dly_log_list[$obj_id]['list'][]    = $deliverylog[$k];
        }
        $render->pagedata['dly_log_list'] = $dly_log_list;
        
        /* “失败”、“取消”、“打回”发货单日志 */
        $history_ids = $deliveryObj->getHistoryIdByOrderId($order_id);
        $deliveryHistorylog = array();
        foreach($history_ids as $v){
            $delivery = $deliveryObj->dump($v,'delivery_id,delivery_bn,status');
            $deliveryHistorylog[$delivery['delivery_bn']] = $logObj->read_log(array('obj_id'=>$v,'obj_type'=>'delivery@ome'), 0, -1);
            
            
            foreach($deliveryHistorylog[$delivery['delivery_bn']] as $k=>$v){
                $deliveryHistorylog[$delivery['delivery_bn']][$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
                $deliveryHistorylog[$delivery['delivery_bn']][$k]['status'] =$delivery['status'];
            }
        }
        
        /* 同批处理的订单日志 */
        $order_ids = $deliveryObj->getOrderIdByDeliveryId($delivery_ids);
        $orderLogs = array();
        foreach($order_ids as $v){
            if($v != $order_id){
                $order = $orderObj->dump($v,'order_id,order_bn');
                $orderLogs[$order['order_bn']] = $logObj->read_log(array('obj_id'=>$v,'obj_type'=>'orders@ome'), 0, -1);
                foreach($orderLogs[$order['order_bn']] as $k=>$v){
                    if($v)
                        $orderLogs[$order['order_bn']][$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
                }
            }
        }
        
        $render->pagedata['history'] = $history;
        $render->pagedata['deliverylog'] = $deliverylog;
        $render->pagedata['deliveryHistorylog'] = $deliveryHistorylog;
        $render->pagedata['orderLogs'] = $orderLogs;
        $render->pagedata['order_id'] = $order_id;
        
        return $render->fetch('admin/order/detail_history.html');
    }
}