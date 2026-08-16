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
 * 发货单处理
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_request_delivery extends erpapi_shop_request_abstract
{
    protected $_delivery_errcode = array(
        'W90010'=>'已经出库',
        'W90011'=>'参数错误',
        'W90012'=>'已经出库',
        'W90013'=>'参数错误',
        'W90014'=>'参数错误',
    );

    /**
     * 发货确认
     *
     * @return void
     * @author
     * @param array $sdf = array(
     *                  'delivery_id' => #发货单ID#
     *                  'delivery_bn' => #发货单编号#
     *                  'status'      => #发货单状态#
     *                  'logi_id' => #物流公司ID#
     *                  'logi_name' => #物流公司名称#
     *                  'logi_no' => #运单号#
     *                  'logi_type' => #物流公司类型#
     *                  'feature' => #唯一码#
     *                  'is_split' => #是否拆单#
     *                  'split_model' => #拆单模式#
     *                  'is_cod' => #货到付款#
     *                  'oid_list'=> #子单#
     *                  'itemNum' => #包裹数#
     *                  'delivery_time' => #发货时间#
     *                  'last_modified' => #最后更新时间#
     *                  'delivery_cost_actual' => #物流费#
     *                  'create_time' => #创建时间#
     *                  'is_protect' => #是否保价#
     *                  'delivery' => #配送方式#
     *                  'memo' => #备注#
     *                  'is_virtual' => #是否为虚拟化发货#
     *                  'delivery_items' => array(
     *                      'bn' => #货号#
     *                      'number' => #数量#
     *                      'name' => #名称#
     *                      'item_type' => #明细类型#
     *                      'shop_goods_id' => #前端商品#
     *                      'promotion_id' => #优惠ID#
     *                  ),
     *                  'consignee' => array(
     *                      'name' => #收货人姓名#
     *                      'area' => #收货地区#
     *                      'addr' => #收货人地址#
     *                      'zip'  => #收货人邮编#
     *                      'email' => #收货人邮箱#
     *                      'mobile' => #收货人手机#
     *                      'telephone' => #收货人电话#
     *                  ),
     *                  'memberinfo' => array(
     *                      'uname' => #会员名#
     *                  ),
     *                  'orderinfo' => array(
     *                         'order_id' => #订单ID#
     *                         'order_bn' => #订单编号#
     *                         'ship_status' => #发货状态#
     *                         'createway' => #订单生成方式#
     *                         'sync'  => #回写状态#
     *                         'sellermemberid' => #买家ID#
     *                         'is_cod' => #到付#
     *                         'self_delivery' => #自发货#
     *                         'order_objects' => array(
     *                              'bn' => #货号#
     *                              'oid' => #子单编号#
     *                              'shop_goods_id' => #平台商品ID#
     *                              'quantity' => #数量#
     *                              'name' => #商品名称#
     *                              'obj_type' => #类型#
     *                              'order_items' => array(
     *                                  'bn' => #货号#
     *                                  'shop_goods_id' => #平台商品ID#
     *                                  'sendnum' => #发货数量#
     *                                  'product_name' => #商品名称#
     *                                  'promotion_id' => #优惠ID#
     *                                  'item_type' => #类型#
     *                                  'nums' => #购买数量#
     *                               ),
     *                          ),
     *                  ),
     *              )
     **/
    public function confirm($sdf,$queue=false)
    {
        $orderModel = app::get('ome')->model('orders');
        
        //[经销商订单]使用平台订单号
        $original_bn = $sdf['orderinfo']['order_bn'];
        if($sdf['is_dealer_order'] && $sdf['orderinfo']['platform_order_bn']){
            $original_bn = $sdf['orderinfo']['platform_order_bn'];
        }
        
        //title
        $title = sprintf('发货状态回写[%s]-%s', $sdf['delivery_bn'], $original_bn);
        
        // 只处理已发货与部分发货状态
        if ($sdf['status'] != 'succ' && !in_array($sdf['orderinfo']['ship_status'], array('1','2'))) {
            $rb = app::get('ome')->getConf('ome.delivery.back_node');
            if (!in_array($rb, array('check','print'))) {
                return $this->succ('发货单未发货');
            }
        }
        $args[0] = $sdf;
        $_in_mq = $this->__caller->caller_into_mq('delivery_confirm','shop',$this->__channelObj->channel['shop_id'],$args,$queue);
        if ($_in_mq) {
            return $this->succ('成功放入队列');
        }

        $this->format_confirm_sdf($sdf);
        
        // 发货记录
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $log = array(
            'shopId'           => $this->__channelObj->channel['shop_id'],
            'ownerId'          => $opInfo['op_id'],
            'orderBn'          => $sdf['orderinfo']['order_bn'],
            'deliveryCode'     => $sdf['logi_no'],
            'deliveryCropCode' => $sdf['logi_type'],
            'deliveryCropName' => $sdf['logi_name'],
            'receiveTime'      => time(),
            'status'           => 'send',
            'updateTime'       => '0',
            'oid_list'         => $sdf['oid_list'] ? implode(',', $sdf['oid_list']) : '',
            'message'          => '',
            'log_id'           => uniqid('',true),
        );

        $shipmentLogModel = app::get('ome')->model('shipment_log');
        
        // 更新订单状态
        $orderModel->update(array('sync'=>'run'),array('order_id'=>$sdf['orderinfo']['order_id']));
        
        $params = $this->get_confirm_params($sdf);
        
        $callback = array(
           'class' => get_class($this),
           'method' => 'confirm_callback',
           'params' => array(
                'shipment_log_id' => $log['log_id'],
                'order_id'        => $sdf['orderinfo']['order_id'],
                'logi_no'         => $sdf['logi_no'],
                'obj_bn' => $sdf['orderinfo']['order_bn'],
                'company_code'=>$sdf['logi_type'],
                'delivery_bn'   => $sdf['delivery_bn'],
            ),
        );

        // 判断发货单是否有合单
        if ($params['merged_order_sns']) {
            $callback['params']['merged_order_sns'] = $params['merged_order_sns'];
            $callback['params']['tid'] = $params['tid'];
        }
        
        //请求接口名
        $api_method = $this->get_delivery_apiname($sdf);
        $api_msg = '';
        
        //请求失败记录
        $apiFailMdl = app::get('erpapi')->model('api_fail');

        $parent_bn = $sdf['delivery_bn'];
        $sdf['parent_bn'] && $parent_bn = $sdf['parent_bn'];

        if ($params['is_single_item_send'] && $params['packages_list']) {
            // 多包裹同步请求
            foreach ($params['packages_list'] as $package_param) {
                if (!$package_param) {
                    continue;
                }
                // 插入日志
                $log['log_id'] = uniqid('',true);
                $log['deliveryCode'] = $package_param['logistics_no'];
                $callback['params']['shipment_log_id'] = $log['log_id'];
                $shipmentLogModel->insert($log);

                // 失败重试
                $apiFailId  = $apiFailMdl->saveTriggerRequest(
                    // $sdf['delivery_bn'], 
                    $parent_bn,
                    'deliveryBack', 
                    $api_method, 
                    $api_msg,
                    md5(json_encode($package_param))
                );
                if($apiFailId) {
                    $callback['params']['api_fail_id'] = $apiFailId;
                }

                //判断是否使用同步请求
                $result = $this->__caller->call($api_method, $package_param, [], $title,10,$package_param['tid']);

                $result = $this->confirm_callback($result,$callback['params']);
            }

        }else{
            // 插入日志
            $shipmentLogModel->insert($log);

            // 失败重试
            $apiFailId  = $apiFailMdl->saveTriggerRequest(
                // $sdf['delivery_bn'], 
                $parent_bn, 
                'deliveryBack', 
                $api_method, 
                $api_msg
            );
            if($apiFailId) {
                $callback['params']['api_fail_id'] = $apiFailId;
            }

            $result = $this->__caller->call($api_method, $params, $callback, $title,10,$sdf['orderinfo']['order_bn']);
            if ($result['rsp'] == 'fail') {
                $shipmentLogModel->update(array('status'=>'fail','message'=>$result['err_msg'],'updateTime'=>time()),array('log_id'=>$log['log_id']));
            }
        }

        return $result;
    }

    /**
     * 发货请求参数（以淘宝做为标准）
     *
     * @return array
     * @author 
     **/
    protected function get_confirm_params($sdf)
    {
        $param = array(
            'tid'          => $sdf['orderinfo']['order_bn'], // 订单号
            'company_code' => $sdf['logi_type'], // 物流编号
            'company_name' => $sdf['logi_name'], // 物流公司
            'logistics_no' => $sdf['logi_no'], // 运单号
        );
        
        //[经销商订单]更换为平台订单号
        if($sdf['is_dealer_order'] && $sdf['orderinfo']['platform_order_bn']){
            //平台订单号
            $param['tid'] = $sdf['orderinfo']['platform_order_bn'];
            
            //经销商订单标记
            $param['is_dealer_order'] = 'true';
        }
        
        return $param;
    }

    /**
     * 数据处理
     *
     * @return void
     * @author 
     **/
    protected function format_confirm_sdf(&$sdf)
    {
        // 物流发货单去BOM头
        $pattrn           = chr(239).chr(187).chr(191);
        $sdf['logi_no']   = trim(str_replace($pattrn, '', $sdf['logi_no']));
        $sdf['logi_type'] = trim($sdf['logi_type']);
        $sdf['logi_name'] = strval($sdf['logi_name']);
        $sdf['logi_no']   = strval($sdf['logi_no']);
    }

    /**
     * 发货回调
     *
     * @return array
     * @author 
     **/
    public function confirm_callback($response, $callback_params)
    {
        $rsp             = $response['rsp'];
        $msg_id          = $response['msg_id'];
        $res             = trim($response['res']);
        $err_msg         = $response['err_msg'];
        $order_id        = $callback_params['order_id'];
        $shipment_log_id = $callback_params['shipment_log_id'];
        $logi_no         = $callback_params['logi_no'];
        
        //订单信息
        $orderModel = app::get('ome')->model('orders');
        $orders = $orderModel->getList('order_id,order_bn,sync,shop_type,shop_id',array('order_id'=>$order_id),0,1);

        // 支持合单的回写
        $orderBnArr = [];
        if ($callback_params['merged_order_sns']) {
            $orderBnArr = json_decode($callback_params['merged_order_sns'], 1);
            if ($orderBnArr && is_array($orderBnArr)) {
                // 去除掉已作废的订单
                $orders = $orderModel->getList('order_id,order_bn,sync,shop_type,shop_id',array('order_bn|in'=>$orderBnArr, 'status|noequal'=>'dead'));
            }
        }

        // 改成循环，支持合单的回写
        foreach ($orders as $k => $order) {
            $order_id = $order['order_id'];
            // 已经回写成功，不需要再改
            if ($order['sync'] == 'succ') $rsp = 'succ';

            // 出现需要重新的，重置
            if ($order['shop_type'] == 'taobao' && false !== strstr($err_msg,'error_response')) {
                $errmsg = @json_decode($err_msg,true);
                if (is_array($errmsg)){
                    $err_msg = $errmsg['error_response']['sub_msg'];

                    if ($errmsg['error_response']['sub_code'] == 'B150') $rsp = 'fail';
                }
            }
            $rsp=='success' ? 'succ' : $rsp;
            $status = 'succ'; $sync_fail_type = 'none'; $message = '';
            // ERP没有发起成功且请求失败
            if ($rsp != 'succ' ) {
                $status = 'fail';

                // 错误信息
                $message = $err_msg.'('.$msg_id.')';

                // 失败类型
                if ('已经出库' == $this->_delivery_errcode[$res]) {
                    $status = 'succ';
                    $message = '已经出库'.$res.'('.$msg_id.')';

                    $sync_fail_type = 'shipped';
                }elseif (in_array($res,array('W90011','W90013','W90014'))) {
                    $sync_fail_type = 'params';
                }
            }

            // 更新订单状态
            if ($order_id) {
                $updateOrderData = array(
                    'sync'           => $status,
                    'up_time'        => time(),
                    'sync_fail_type' => $sync_fail_type,
                );

                $orderModel->update($updateOrderData,array('order_id'=>$order_id,'sync|noequal'=>'succ'));
            }
        }
        /*

        // 已经回写成功，不需要再改
        if ($order[0]['sync'] == 'succ') $rsp = 'succ';

        // 出现需要重新的，重置
        if ($order[0]['shop_type'] == 'taobao' && false !== strstr($err_msg,'error_response')) {
            $errmsg = @json_decode($err_msg,true);
            if (is_array($errmsg)){
                $err_msg = $errmsg['error_response']['sub_msg'];

                if ($errmsg['error_response']['sub_code'] == 'B150') $rsp = 'fail';
            }
        }
        
        $rsp == 'success' ? 'succ' : $rsp;
        $status = 'succ';
        $sync_fail_type = 'none';
        $message = '';
        
        // ERP没有发起成功且请求失败
        if ($rsp != 'succ' ) {
            $status = 'fail';
            
            // 错误信息
            $message = $err_msg.'('.$msg_id.')';
            
            // 失败类型
            if ('已经出库' == $this->_delivery_errcode[$res] || preg_match('/等待买家收货|已发货|已出库/', $err_msg) ) {
                $status = 'succ';
                $message = '已经出库'.$res.'('.$msg_id.')';

                $sync_fail_type = 'shipped';
            }elseif (in_array($res,array('W90011','W90013','W90014'))) {
                $sync_fail_type = 'params';
            }else{
                //物流公司或物流单号不符合规则(抖音平台返回报错 logisitcs_id 单词字母写错了)
                $findKeywords = array('运单号', '快递公司', '物流公司', 'logistics', 'logisitcs', '物流单号', '不符合单号规则');
                foreach ($findKeywords as $findKey => $findVal)
                {
                    if(strpos($err_msg, $findVal) !== false){
                        //运单号不符合规则
                        $sync_fail_type = 'logistics';
                        break;
                    }
                }
                
                //前端已发货
                $findKeywords = array('没有能发货', '已发货', '订单已完结');
                foreach ($findKeywords as $findKey => $findVal)
                {
                    if(strpos($err_msg, $findVal) !== false){
                        $sync_fail_type = 'shipped';
                        
                        //标记为回传平台成功
                        $status = 'succ';
                        
                        break;
                    }
                }
            }
        }

        // 更新订单状态
        if ($order_id) {
            $updateOrderData = array(
                'sync'           => $status,
                'up_time'        => time(),
                'sync_fail_type' => $sync_fail_type,
            );

            $orderModel->update($updateOrderData,array('order_id'=>$order_id,'sync|noequal'=>'succ'));
        }
        */

        $shipmentModel = app::get('ome')->model('shipment_log');

        // 更新发货日志状态
        if ($shipment_log_id) {
            $updateShipmentData = array(
                'status'     => $status,
                'updateTime' => time(),
                'message'    => $message,
            );

            $logFileter = array('log_id'=>$shipment_log_id, 'status' => array('send','succ'));
            // 合单的回写,merge_order一起更新状态，更新成succ以后不再更新
            $shipmentModel->update($updateShipmentData,$logFileter);
            if($status != 'succ'){
                // 获取预警需要的变量
                $delivery_bn = isset($callback_params['delivery_bn']) ? $callback_params['delivery_bn'] : '';
                $shop_name = '';
                $platform = '';
                
                // 获取店铺信息和平台类型
                if (!empty($orders)) {
                    $firstOrder = $orders[0];
                    
                    // 获取店铺信息和平台类型
                    if (!empty($firstOrder['shop_id'])) {
                        $shopModel = app::get('ome')->model('shop');
                        $shopInfo = $shopModel->dump(array('shop_id' => $firstOrder['shop_id']), 'name,node_type');
                        if ($shopInfo) {
                            $shop_name = $shopInfo['name'] ? $shopInfo['name'] : '';
                            // 优先使用店铺的 node_type，如果没有则使用订单的 shop_type
                            $shop_type_code = $shopInfo['node_type'] ? $shopInfo['node_type'] : ($firstOrder['shop_type'] ? $firstOrder['shop_type'] : '');
                        } else {
                            // 如果店铺信息获取失败，使用订单的 shop_type
                            $shop_type_code = $firstOrder['shop_type'] ? $firstOrder['shop_type'] : '';
                        }
                    } else {
                        // 如果没有 shop_id，使用订单的 shop_type
                        $shop_type_code = $firstOrder['shop_type'] ? $firstOrder['shop_type'] : '';
                    }

                    
                    // 将 shop_type 转换为中文
                    if ($shop_type_code) {
                        $platform = ome_shop_type::shop_name($shop_type_code);
                        if (empty($platform)) {
                            $platform = $shop_type_code; // 如果转换失败，使用原始值
                        }
                    }
                }

                
                kernel::single('monitor_event_notify')->addNotify('order_delivery_platform_sync_error', [
                    'logi_no' => $logi_no,
                    'order_bn' => implode(',', array_column($orders, 'order_bn')),
                    'delivery_bn' => $delivery_bn,
                    'platform' => $platform,
                    'shop_name' => $shop_name,
                    'errmsg' => $message,
                ]);
            }
            // 合单的回写,merged_order_sns一起更新状态，更新成succ以后不再更新
            if ($orderBnArr && is_array($orderBnArr) && $status == 'succ' && $callback_params['tid']) {

                // 排除主订单号
                $orderBnArr = array_diff($orderBnArr, [$callback_params['tid']]);

                $logFileter = [
                    'orderBn|in'    =>  $orderBnArr,
                    'deliveryCode'  =>  $logi_no,
                    'status|in'     =>  ['send', 'fail'],
                ];

                $upOther = [];
                // 筛选出合单订单里每个订单最新的一条前端回写日志
                $logList = $shipmentModel->getList('log_id,orderBn,updateTime', $logFileter);
                foreach ($logList as $lk => $lv) {
                    if (!isset($upOther[$lv['orderBn']])) {
                        $upOther[$lv['orderBn']] = $lv;
                    } elseif ($lv['updateTime'] && $lv['updateTime']>$upOther[$lv['orderBn']]['updateTime']) {
                        $upOther[$lv['orderBn']] = $lv;
                    }
                }
                if ($upOther) {
                    $upOther = array_column($upOther, 'log_id');
                    $logFileter['log_id|in'] = $upOther;
                    $shipmentModel->update($updateShipmentData,$logFileter);
                }

            }
        }
        $rsp == 'succ' && $response['rsp'] = $rsp;

        return $this->callback($response, $callback_params);
    }

    /**
     * 家装服务商
     *
     * @return void
     * @author 
     **/
    public function jzpartner_query($sdf)
    {
        $title = sprintf('家装服务商查询[%s]',$sdf['orderinfo']['order_bn']);
        
        $params = array(
            'tid'=>$sdf['orderinfo']['order_bn'],
        );

        $result=$this->__caller->call(SHOP_WLB_ORDER_JZ_QUERY, $params, null, $title,10,$sdf['delivery_bn']);
        $jzdata = array();
        if ($result['rsp'] == 'succ') {
            $data=json_decode($result['data'],true);
            $data = $data['result'];
            foreach($data['expresses']['expresses'] as $lgcps){
                $code = $lgcps['code'];

                // 返回第一个
                if ($sdf['logi_type'] == $code) {
                    $jzdata['lg_tp_dto'] = $lgcps; break;
                }
            }
            if(empty($jzdata['lg_tp_dto'])){
                $jzdata['lg_tp_dto'] = $data['expresses']['expresses'][0];
            }

            if($data['support_install'] == '1'){
                $jzdata['ins_tp_dto'] = $data['ins_tps']['instps'][0];
            
            }

        }

        return $jzdata;
    }

    /**
     * 获取发货接口(默认线下发货)
     *
     * @return string
     * @author 
     **/
    protected function get_delivery_apiname($sdf)
    {
        return SHOP_LOGISTICS_OFFLINE_SEND;
    }

    /**
     * 添加发货单
     *
     * @return void
     * @author 
     **/
    public function add($sdf){}

    /**
     * 更新发货单流水状态
     *
     * @return void
     * @author 
     **/
    public function process_update($sdf){}

    /**
     * 更新物流公司
     *
     * @return void
     * @author 
     **/
    public function logistics_update($sdf){}
    
    /**
     * 发货单查询核销订单ID
     * 
     * @param array $params
     * @return array
     */
    public function seller_lporderid($params)
    {
        $wmsDeliveryObj = app::get('wms')->model('delivery');
        
        $title = sprintf('发货单查询核销订单ID[%s]-%s', $params['delivery_bn'], $params['order_bn']);
        
        $order_bn = $params['order_bn'];
        $delivery_bn = $params['delivery_bn'];
        $wms_delivery_bn = $params['wms_delivery_bn'];
        $receive_code = $params['receive_code'];
        
        //params
        $requestParams = array(
            'tid' => $order_bn,
            'receive_code' => $receive_code,
        );
        
        //request
        $result = $this->__caller->call(SHOP_LOGISTICS_SELLER_ORDERS, $requestParams, null, $title, 10, $order_bn);
        if ($result['rsp'] != 'succ') {
            //update
            $wmsDeliveryObj->update(array('writeoff_status'=>3), array('delivery_bn'=>$wms_delivery_bn));
            
            $result['error_msg'] = '发货单查询核销订单ID失败';
            
            return $result;
        }
        
        $data = json_decode($result['data'],true);
        
        //data
        $lpOrderList = array();
        $writeoff_data = $data['writeoff_order_list']['write_off_order_d_t_o'];
        if($writeoff_data){
            foreach ($writeoff_data as $key => $val)
            {
                $trade_id = $val['trade_id'];
                $lp_order_id = $val['lp_order_id'];
                
                if(empty($trade_id) || empty($trade_id)){
                    continue;
                }
                
                $lpOrderList[$trade_id] = $lp_order_id;
            }
        }
        
        $lp_order_id = $lpOrderList[$order_bn];
        if($lp_order_id){
            //update
            $wmsDeliveryObj->update(array('writeoff_status'=>2, 'lp_order_id'=>$lp_order_id, 'receive_code'=>$receive_code), array('delivery_bn'=>$wms_delivery_bn));
        }else{
            //update
            $wmsDeliveryObj->update(array('writeoff_status'=>3), array('delivery_bn'=>$wms_delivery_bn));
            
            $result['rsp'] = 'fail';
            $result['error_msg'] = '查询核销订单ID为空,请查看同步日志';
            
            return $result;
        }
        
        return $result;
    }
    
    /**
     * 发货单签收核销
     *
     * @param array $params
     * @return array
     */
    public function seller_writeoff($params)
    {
        $wmsDeliveryObj = app::get('wms')->model('delivery');
        $operationMdl = app::get('ome')->model('operation_log');
        
        $title = sprintf('发货单确认核销[%s]-%s', $params['delivery_bn'], $params['order_bn']);
        
        $order_bn = $params['order_bn'];
        $delivery_bn = $params['delivery_bn'];
        $wms_delivery_bn = $params['wms_delivery_bn'];
        $wms_delivery_id = $params['wms_delivery_id'];
        
        //params
        $requestParams = array(
            'tid' => $order_bn,
            'lp_order_id' => $params['lp_order_id'],
            'receive_code' => $params['receive_code'],
        );
        
        //request
        $result = $this->__caller->call(SHOP_LOGISTICS_SELLER_WRITEOFF, $requestParams, null, $title, 10, $order_bn);
        if ($result['rsp'] != 'succ') {
            //update
            $wmsDeliveryObj->update(array('writeoff_status'=>4), array('delivery_bn'=>$wms_delivery_bn));
            
            $result['error_msg'] = '发货单签收核销失败';
            
            return $result;
        }
        
        $data = json_decode($result['data'],true);
        $request_id = $data['request_id'];
        $orgResult = $data['result'];
        if($orgResult['success'] == 'true'){
            //成功
        }else{
            //请求成功,但结果是失败的
        }
        
        //update
        $wmsDeliveryObj->update(array('writeoff_status'=>1), array('delivery_bn'=>$wms_delivery_bn));
        
        //logs
        if($wms_delivery_id){
            $operationMdl->write_log('delivery_logi@wms', $wms_delivery_id, '确认核销成功，收货码：'. $params['receive_code'] .'，核销时间：'. date('Y-m-d H:i:s', time()));
        }
        
        return $result;
    }
    
    /**
     * 物流轨迹查询
     *
     * @param array $params
     * @return array
     */
    public function logistics_track($params)
    {
        $title = sprintf('同城配物流轨迹[%s]-%s', $params['delivery_bn'], $params['order_bn']);
        
        $order_bn = $params['order_bn'];
        $delivery_bn = $params['delivery_bn'];
        
        //params
        $requestParams = array(
            'tid' => $order_bn,
            'logi_code' => $params['logi_code'], //矩阵不需要,为了查日志方便
            'logi_no' => $params['logi_no'], //矩阵不需要,为了查日志方便
        );
        
        //request
        $result = $this->__caller->call(SHOP_LOGISTICS_SELLER_TRACE, $requestParams, null, $title, 10, $order_bn);
        if ($result['rsp'] != 'succ') {
            $result['error_msg'] = '物流轨迹查询失败';
            
            return $result;
        }
        
        //data
        if(is_string($result['data'])){
            $data = json_decode($result['data'],true);
        }else{
            $data = $result['data'];
        }
        
        //data
        $trackList = array();
        $mail_list = $data['mail_list']['top_logistics_mail_d_t_o'];
        if($mail_list){
            foreach ($mail_list as $key => $val)
            {
                $tid = $val['tid'];
                $out_sid = $val['out_sid'];
                $status = $val['status'];
                
                if(empty($tid)){
                    continue;
                }
                
                $trace_list = $val['trace_list']['top_logistics_node_d_t_o'];
                if($trace_list){
                    $trackList[$tid] = $trace_list;
                }
            }
        }
        
        //check
        if(empty($trackList[$order_bn])){
            $result['rsp'] = 'fail';
            $result['error_msg'] = '没有获取到物流轨迹信息';
            
            return $result;
        }
        
        $result['data'] = $trackList[$order_bn];
        
        return $result;
    }
    
    /**
     * 修改物流公司信息
     *
     * @param array $params
     * @return array
     */
    public function logistics_resend($params)
    {
        $title = sprintf('修改同城配物流信息[%s]-%s', $params['delivery_bn'], $params['order_bn']);
        
        $order_bn = $params['order_bn'];
        $delivery_bn = $params['delivery_bn'];
        
        //params
        $requestParams = array(
            'tid' => $order_bn,
            'company_code' => $params['logi_code'],
            'out_sid' => $params['logi_no'],
        );
        
        //[拆单]子订单列表
        if($params['sub_tids']){
            $requestParams['sub_tids'] = $params['sub_tids'];
        }
        
        //request
        $result = $this->__caller->call(SHOP_LOGISTICS_SELLER_RESEND, $requestParams, null, $title, 10, $order_bn);
        if ($result['rsp'] != 'succ') {
            
            $result['error_msg'] = '修改同城配物流信息失败';
            
            return $result;
        }
        
        return $result;
    }
    
    /**
     * [翱象系统]同步仓库作业信息
     *
     * @param array $sdf
     * @return array
     */
    public function aoxiangReport($sdf)
    {
        $original_bn = $sdf['delivery_bn'];
        $order_bn = ($sdf['order_bn'] ? $sdf['order_bn'] : $sdf['orderinfo']['order_bn']);
        $title = sprintf('翱象仓库作业同步[%s]', $order_bn);
        
        //params
        $requestParams = array();
        if($sdf['process_status'] == 'confirm'){
            //确认出库
            $requestParams = $this->_formatAoxiangConfirm($sdf);
        }else{
            //仓库接单
            $requestParams = $this->_formatAoxiangCreate($sdf);
        }
        
        //callback
        $callback = array();
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_WAREHOUSE_REPORT, $requestParams, $callback, $title, 10, $original_bn);
        if ($result['rsp'] != 'succ') {
            $error_msg = ($result['message'] ? $result['message'] : $result['err_msg']);
            $result['error_msg'] = '同步发货单失败：'. $error_msg;
            
            return $result;
        }
        
        return $result;
    }
    
    /**
     * [翱象系统]仓库接单--格式化请求数据
     *
     * @param array $sdf
     * @return array
     */
    public function _formatAoxiangCreate($sdf)
    {
        $nowTime = time();
        $create_time = ($sdf['create_time'] ? $sdf['create_time'] : time());
        
        $order_bn = $sdf['order_bn'];
        
        //订单类型
        $order_type = 'JYCK';
        $pre_order_code = '';
        if($sdf['orderinfo']['createway'] == 'after'){
            $order_type = 'HHCK';
            
            //换货订单关联的原平台订单号
            $orderInfo = app::get('ome')->model('orders')->dump(array('order_bn'=>$order_bn), 'order_id,order_bn,relate_order_bn');
            $pre_order_code = $orderInfo['relate_order_bn'];
        }
        
        //是否拆单(多批次发货)
        $is_split = $sdf['is_split'];
        
        //单据信息
        $orderData = array (
            'order_code' => $order_bn, //订单号
            'order_id' => $sdf['delivery_bn'], //发货单号
            'erp_warehouse_code' => $sdf['branch_bn'], //仓库编码
            'create_time' => ($create_time * 1000), //创建时间(时间戳-毫秒)
            'order_type' => $order_type, //单据类型(JYCK:一般交易出库,HHCK:换货出库,BFCK:补发出库,QTCK:其他出库单)
            'pre_order_code' => $pre_order_code, //原ERP发货单，条件必填（换货出库/补发出库）
        );
        
        //单据作业信息
        $processData = array (
            'process_status' => 'ACCEPT', //作业状态
            //'confirm_type' => 0, //多批次出库(0表示发货单最终状态确认;1表示发货单中间状态确认)
            'logistics_type' => '2', //物流方式(2:自己联系物流,3:无需物流),默认2
            'operate_time' => ($nowTime * 1000), //操作时间(时间戳-毫秒)
        );
        
        //发货明细
        $line_i = 0;
        $deliveryItems = array();
        foreach ($sdf['delivery_items'] as $itemKey => $itemVal)
        {
            $line_i++;
            
            //check
            //if(empty($itemVal['oid'])){
            //    continue;
            //}
            
            //items
            $deliveryItems[] = array (
                'order_line_no' => $line_i, //行号
                'source_order_code' => $itemVal['order_bn'], //交易主单号(OMS订单号)
                'sub_source_order_code' => $itemVal['oid'], //交易子单号(oid子单号)
                'sc_item_id' => $itemVal['bn'], //货品编码id
                'plan_qty' => $itemVal['nums'], //发货数量
            );
        }
        
        //包裹明细
        $packageData = array();
        
        //params
        $params = array(
            'order' => json_encode($orderData), //单据信息
            'process' => json_encode($processData), //单据作业信息
            'order_lines' => json_encode($deliveryItems), //订单明细(创建发货单必填)
            //'confirm_packages' => json_encode($packageData), //包裹明细
            'order_flag' => 'COD', //订单标记
        );
        
        return $params;
    }
    
    /**
     * [翱象系统]确认出库--格式化请求数据
     *
     * @param array $sdf
     * @return array
     */
    public function _formatAoxiangConfirm($sdf)
    {
        $nowTime = time();
        $create_time = ($sdf['delivery_time'] ? $sdf['delivery_time'] : time());
        
        $order_bn = $sdf['order_bn'];
        
        //订单类型
        $order_type = 'JYCK';
        $pre_order_code = '';
        if($sdf['createway'] == 'after'){
            $order_type = 'HHCK';
            
            //换货订单关联的原平台订单号
            $orderInfo = app::get('ome')->model('orders')->dump(array('order_bn'=>$order_bn), 'order_id,order_bn,relate_order_bn');
            $pre_order_code = $orderInfo['relate_order_bn'];
        }
        
        //是否拆单(多批次发货)
        $is_split = $sdf['is_split'];
        
        //单据信息
        $orderData = array (
            'order_code' => $order_bn, //订单号
            'order_id' => $sdf['delivery_bn'], //发货单号
            'erp_warehouse_code' => $sdf['branch_bn'], //仓库编码
            'create_time' => ($create_time * 1000), //创建时间(时间戳-毫秒)
            'order_type' => $order_type, //单据类型(JYCK:一般交易出库,HHCK:换货出库,BFCK:补发出库,QTCK:其他出库单)
            'pre_order_code' => $pre_order_code, //原ERP发货单，条件必填（换货出库/补发出库）
        );
        
        //单据作业信息
        $processData = array (
            'process_status' => 'CONFIRM', //作业状态
            //'confirm_type' => 0, //多批次出库(0表示发货单最终状态确认;1表示发货单中间状态确认)
            'logistics_type' => '2', //物流方式(2:自己联系物流,3:无需物流),默认2
            'operate_time' => ($nowTime * 1000), //操作时间(时间戳-毫秒)
        );
        
        //发货明细
        $orderItems = array();
        $scItems = array();
        $line_i = 0;
        foreach ($sdf['delivery_items'] as $itemKey => $itemVal)
        {
            $line_i++;
            
            $orderItems[] = array (
                'order_line_no' => $line_i, //行号
                'source_order_code' => $itemVal['order_bn'], //交易主单号
                'sub_source_order_code' => $itemVal['oid'], //交易子单号
                'sc_item_id' => $itemVal['bn'], //货品编码id
                'actual_qty' => $itemVal['nums'], //发货数量
            );
            
            //sc_items
            $scItems[] = array (
                'order_line_no' => $line_i, //行号
                //'sub_express_code' => '', //子件运单号,如果有子母件可填
                'sc_item_id' => $itemVal['bn'], //货品编码id
                'quantity' => $itemVal['nums'], //发货数量
            );
        }
        
        //包裹明细
        $packageData = array();
        $packageData[0] = array(
            'logistics_code' => $sdf['logi_no'],
            'express_code' => $sdf['logi_type'],
            'sc_items' => $scItems,
        );
        
        //params
        $params = array(
            'order' => json_encode($orderData), //单据信息
            'process' => json_encode($processData), //单据作业信息
            'confirm_order_lines' => json_encode($orderItems), //出库订单明细(仓库出库必接)
            'confirm_packages' => json_encode($packageData), //出库包裹明细
            'order_flag' => 'COD', //订单标记
        );
        
        return $params;
    }
    public function getPrintDelivery($sdf){}

    public function operationInWarehouse($sdf){}
    
    /**
     * 获取订单发货oid子订单列表
     *
     * @param $sdf
     * @return array
     */
    public function _getDeliveryOids($sdf)
    {
        //check
        if(empty($sdf['delivery_items'])){
            return array();
        }
        
        //组织包裹列表
        $oidList = array();
        foreach ($sdf['delivery_items'] as $key => $val)
        {
            $oid = $val['oid'];
            
            //check
            if(empty($oid)){
                continue;
            }
            
            //子订单信息
            $oidList[$oid] = $oid;
        }
        
        return $oidList;
    }
    
    /**
     * 按发货子订单明细
     *
     * @param $sdf
     * @param $shop_type 店铺类型(taobao、tmall)
     * @return array
     */
    public function _getDeliveryItems($sdf, $shop_type=null)
    {
        $logi_type = $sdf['logi_type']; //物流公司编码
        $logi_no = $sdf['logi_no']; //物流单号
        $logi_name = $sdf['logi_name']; //物流公司名称
        
        //check
        if(empty($sdf['delivery_items'])){
            return array();
        }
        
        //order_objects
        $objectList = array();
        foreach ($sdf['order_objects'] as $key => $val)
        {
            $oid = $val['oid'];
            
            $objectList[$oid] = array('quantity'=>intval($val['quantity']));
        }
        
        //初始化包裹信息(只有一个包裹)
        $packageList[$logi_no] = array(
            'company_code' => $logi_type,
            'out_sid' => $logi_no,
        );
        
        //组织包裹列表
        $oidList = array();
        $consignStatus = array();
        foreach ($sdf['delivery_items'] as $key => $val)
        {
            $oid = $val['oid'];
            $item_number = intval($val['number']);
            
            //check
            if(empty($oid)){
                continue;
            }
            
            //包裹发货数量
            //@todo：防止wms回传发货数量大于订单上商品购买数量；
            if($objectList[$oid]['quantity'] && $item_number > $objectList[$oid]['quantity']){
                $item_number = $objectList[$oid]['quantity'];
            }
            
            //子订单信息
            $oidList[$oid] = array(
                'sub_tid' => $oid, //子订单id
                'num' => $item_number, //发货数量
            );
            
            //子订单发货情况
            $is_part_consign = false;
            if($val['sendnum'] < $val['nums']){
                $is_part_consign = true;
            } else {
                if(app::get('ome')->model('order_items')->db_dump(['obj_id'=>$val['order_obj_id'], 'delete'=>'false', 'filter_sql'=>'nums <> sendnum'], 'item_id')){
                    $is_part_consign = true;
                }
            }
            
            $consignStatus[$oid] = array(
                'sub_tid' => $oid, //子订单id
                'is_part_consign' => $is_part_consign, //子订单是否部分发货,true:部分发货,false:全部发货;
            );
            if(in_array($shop_type, array('tmall', 'taobao'))){
                //去除数量字段
                unset($oidList[$oid]['num']);
            }
        }
        
        //包裹子订单信息(去除下标)
        $packageList[$logi_no]['goods'] = array_values($oidList);
        
        //去除数组下标
        if($packageList){
            $packageList = array_values($packageList);
        }
        
        //去除数组下标
        if($consignStatus){
            $consignStatus = array_values($consignStatus);
        }
        
        return array('packageList'=>$packageList, 'consignStatus'=>$consignStatus);
    }
    
    /**
     * 按多包裹发货
     *
     * @param $sdf
     * @param $shop_type 店铺类型(taobao、tmall)
     * @return array
     */
    public function _getDeliveryPackages($sdf, $shop_type=null)
    {
        //check
        if(empty($sdf['delivery_items']) || empty($sdf['delivery_package'])){
            return array();
        }
        
        //order_objects
        $objectList = array();
        foreach ($sdf['order_objects'] as $key => $val)
        {
            $oid = $val['oid'];
            
            $objectList[$oid] = array('quantity'=>intval($val['quantity']));
        }
        
        //多包裹
        $dlyPackages = array();
        foreach ($sdf['delivery_package'] as $key => $val)
        {
            $product_bn = $val['bn'];
            $logi_no = $val['logi_no'];
            
            //check
            if(empty($product_bn) || empty($logi_no)){
                continue;
            }
            
            //按[货号+物流单号]纬度
            //@todo：天猫平台一个订单有2行SKU一模一样（买一赠一商品有金额多数量）并且有多个不同物流单号的场景；
            if(isset($dlyPackages[$product_bn][$logi_no])){
                $dlyPackages[$product_bn][$logi_no]['number'] += $val['number'];
            }else{
                $dlyPackages[$product_bn][$logi_no] = array(
                    'package_key' => $key,
                    'number' => $val['number'],
                );
            }
        }
        
        //按发货单明细获取包裹信息
        $packageList = array();
        $oidList = array();
        foreach ($sdf['delivery_items'] as $itemKey => $itemVal)
        {
            $product_bn = $itemVal['product_bn'];
            $item_number = $itemVal['number'];
            $oid = $itemVal['oid'];
            
            //check
            if(empty($oid)){
                continue;
            }
            
            if(empty($dlyPackages[$product_bn])){
                continue;
            }
            
            //初始化打包数量
            $sdf['delivery_items'][$itemKey]['pack_nums'] = 0;
            
            //oid信息
            $oidList[$oid] = array('nums'=>$itemVal['nums'], 'sendnum'=>$itemVal['sendnum']);
            
            //一个货号有多个物流包裹的场景
            foreach ($dlyPackages[$product_bn] as $logi_no => $packVal)
            {
                $package_key = $packVal['package_key'];
                $packageInfo = $sdf['delivery_package'][$package_key];
                
                //check
                if($packVal['number'] < 1){
                    continue;
                }
                
                if(empty($packageInfo)){
                    continue;
                }
                
                //检查已经打包的数量(PKG组合商品没有sendnum字段)
                if(isset($sdf['delivery_items'][$itemKey]['sendnum'])){
                    if($sdf['delivery_items'][$itemKey]['pack_nums'] >= $sdf['delivery_items'][$itemKey]['sendnum']){
                        continue;
                    }
                }
                
                //包裹发货数量
                if($packVal['number'] >= $item_number){
                    $package_num = $item_number;
                    
                    $dlyPackages[$product_bn][$logi_no]['number'] -= $item_number;
                }else{
                    $package_num = $packVal['number'];
                    
                    $dlyPackages[$product_bn][$logi_no]['number'] = 0;
                }
                
                //已经打包的数量
                $sdf['delivery_items'][$itemKey]['pack_nums'] += $package_num;
                //data（含 order_obj_id 供 is_part_consign 场景2 使用）
                $packageList[$logi_no][$oid] = array(
                    'oid' => $oid,
                    'order_obj_id' => isset($itemVal['order_obj_id']) ? $itemVal['order_obj_id'] : null,
                    'item_type' => $itemVal['item_type'],
                    'goods_bn' => $itemVal['bn'],
                    'product_bn' => $product_bn,
                    'num' => $package_num,
                    'logi_type' => $packageInfo['logi_bn'], //物流公司编码
                    'logi_no' => $packageInfo['logi_no'], //物流单号
                    'package_bn' => $packageInfo['package_bn'],
                    'product_id' => $packageInfo['product_id'],
                );
            }
        }
        
        //check
        if(empty($packageList)){
            return array();
        }
        
        //组织包裹列表
        $packages = array();
        $consignStatus = array();
        foreach ($packageList as $pack_logi_no => $packageVal)
        {
            //items
            foreach ($packageVal as $oidKey => $oidVal)
            {
                $logi_no = $oidVal['logi_no'];
                $oid = $oidVal['oid'];
                
                //包裹主信息
                if(empty($packages[$logi_no])){
                    $packages[$logi_no] = array(
                        'out_sid' => $logi_no, //物流单号
                        'company_code' => $oidVal['logi_type'], //物流公司编码
                    );
                }
                
                //包裹发货数量
                //@todo：防止wms回传发货数量大于订单上商品购买数量；
                $package_num = intval($oidVal['num']);
                if($objectList[$oid]['quantity'] && $package_num > $objectList[$oid]['quantity']){
                    $package_num = $objectList[$oid]['quantity'];
                }
                
                //包裹中商品信息
                $packages[$logi_no]['goods'][$oid] = array(
                    'sub_tid' => $oid, //子订单id
                    'num' => $package_num, //发货数量
                );
                //子订单发货情况（is_part_consign=true 仅在这两种部分发货场景出现，见下方注释）
                $is_part_consign = false;
                if($oidList[$oid]['sendnum'] < $oidList[$oid]['nums']){
                    // 场景1：该子订单当前已发数量 < 订购数量（本次或历史为部分发货）
                    $is_part_consign = true;
                } else {
                    // 场景2：同一子订单(obj_id)下存在其他 order_item 未发完(nums<>sendnum)
                    $obj_id = isset($oidVal['order_obj_id']) ? $oidVal['order_obj_id'] : null;
                    if ($obj_id && app::get('ome')->model('order_items')->db_dump(['obj_id'=>$obj_id, 'filter_sql'=>'nums <> sendnum'], 'item_id')){
                        $is_part_consign = true;
                    }
                }
                
                $consignStatus[$oid] = array(
                    'sub_tid' => $oid, //子订单id
                    'is_part_consign' => $is_part_consign, //子订单是否部分发货,true:部分发货,false:全部发货;
                );
                if(in_array($shop_type, array('tmall', 'taobao'))){
                    //去除数量字段
                    unset($packages[$logi_no]['goods'][$oid]['num']);
                }
            }
        }
        
        //去除数组下标
        if($packages){
            foreach ($packages as $logi_no => $packageVal)
            {
                $packages[$logi_no]['goods'] = array_values($packageVal['goods']);
            }
            
            $packages = array_values($packages);
        }
        
        //去除数组下标
        if($consignStatus){
            $consignStatus = array_values($consignStatus);
        }
        
        return array('packages'=>$packages, 'consignStatus'=>$consignStatus);
    }
}
