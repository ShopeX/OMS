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
 * 订单处理
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_tmall_request_order extends erpapi_shop_request_order
{
    private $__status = array(
        // 收单
        'build' => [
            'event' => [
                ['key' => 'QIMEN_ERP_TRANSFER', 'value' => '已转单'],
            ],
        ],
        // 审单
        'check' => [
            'event' => [
                ['key' => 'QIMEN_ERP_CHECK', 'value' => '已客审'],
            ],
        ],
        // 推仓
        'to_wms' => [
            'event' => [
                ['key' => 'QIMEN_CP_NOTIFY', 'value' => '已通知配货'],
            ],
        ],
        // // 打印拣货单
        // 'print_stock' => [
        //     'event' => [
        //         ['key' => 'X_SORT_PRINTED', 'value' => '已打拣货单'],
        //     ],
        // ],
        // // 打印发货单
        // 'print_deliv' => [
        //     'event' => [
        //         ['key' => 'X_SEND_PRINTED', 'value' => '已打发货单'],
        //     ],
        // ],
        // // 打印物流单
        // 'print_expre' => [
        //     'event' => [
        //         ['key' => 'X_LOGISTICS_PRINTED', 'value' => '已打物流单'],
        //     ],
        // ],
        // // 拣货
        // 'picking' => [
        //     'event' => [
        //         ['key' => 'X_SORTED', 'value' => '已拣货'],
        //         ['key' => 'X_EXAMINED', 'value' => '已验货'],
        //     ],
        // ],
        // 出库
        'dispatch' => [
            'event' => [
                ['key' => 'QIMEN_CP_OUT', 'value' => '已出库'],
            ],
        ],
    );

    /**
     * 淘宝全链路
     *
     * @return void
     * @author 
     **/

    public function message_produce($sdf_arr,$queue=false)
    {
        foreach ($sdf_arr as $sk => $sdf) {
            if (!isset($this->__status[$sdf['message_produce_status']])) {
                unset($sdf_arr[$sk]);
                continue;
            }
        }
        if (!$sdf_arr) {
            return $this->succ();
        }

        $args = func_get_args();array_pop($args);
        $_in_mq = $this->__caller->caller_into_mq('order_message_produce','shop',$this->__channelObj->channel['shop_id'],$args,$queue);
        if ($_in_mq) {
            return $this->succ('成功放入队列');
        }

        foreach ($sdf_arr as $sk => $sdf) {

            $status_list = $this->__status[$sdf['message_produce_status']]['event'];
            foreach ($status_list as $status_info) {
                $status = $status_info['key'];

                // 整理参数格式
                $title = sprintf('天猫全链路%s[%s]',$status,$sdf['order_bn']); 


                $remark = $sdf['remark'] ? $sdf['remark'] : $status_info['value'];

                $order_ids = array();
                foreach ((array) $sdf['order_objects'] as $key => $value) {
                    if ($value['oid']) $order_ids[] = $value['oid'];
                }

                $params = array(
                    'topic'       => 'taobao_jds_TradeTrace', 
                    'tid'         => $sdf['order_bn'],
                    'order_ids'   => implode(',',$order_ids),
                    'status'      => $status,
                    'action_time' => date("Y-m-d H:i:s"),
                    'remark'      => $remark,
                );

                $callback = array(
                   'class' => get_class($this),
                   'method' => 'callback',
                    'params' => array(
                        'obj_bn' => $sdf['order_bn'],
                    ),
                );

                $res = $this->__caller->call(SHOP_TMC_MESSAGE_PRODUCE, $params, $callback, $title,10,$sdf['order_bn'],true);
            }
        }
        return $this->succ();
    }

    protected function __formatUpdateOrderShippingInfo($order) {
        $consignee_area = $order['consignee']['area'];
        if(strpos($consignee_area,":")){
            $t_area            = explode(":",$consignee_area);
            $t_area_1          = explode("/",$t_area[1]);
            $receiver_state    = $t_area_1[0];
            $receiver_city     = $t_area_1[1];
            $receiver_district = $t_area_1[2];
        }
        $params = array();
        $params['tid']               = $order['order_bn'];
        $params['receiver_name']     = $order['consignee']['name']?$order['consignee']['name']:'';
        $params['receiver_phone']    = $order['consignee']['telephone']?$order['consignee']['telephone']:'';
        $params['receiver_mobile']   = $order['consignee']['mobile']?$order['consignee']['mobile']:'';
        $params['receiver_state']    = $receiver_state ? $receiver_state : '';
        $params['receiver_city']     = $receiver_city ? $receiver_city : '';
        $params['receiver_district'] = $receiver_district ? $receiver_district : '';
        $params['receiver_address']  = $order['consignee']['addr']?$order['consignee']['addr']:'';
        $params['receiver_zip']      = $order['consignee']['zip']?$order['consignee']['zip']:'';
        return $params;
    }
    
    /**
     * [淘宝700虚拟号]隐私号G组更新：报备外呼主叫号码组
     *
     * @param array $params
     * @return array
     */
    public function bindSecretMobiles($params)
    {
        $title = '隐私号G组更新';
        
        $original_bn = $params['order_bn'];
        
        // check
        if(empty($original_bn) || empty($params['oaid']) || empty($params['mobile_list'])){
            return $this->error('请检查order_bn、oaid、mobile_list是否为空');
        }
        
//        // 请求的数据
//        $requestData = [
//            'mobile_list' => $params['mobile_list'], // 手机号列表(json)
//            'operate_type' => 'ADD_GXB_GROUP', // 操作类型（DELETE_GXB_GROUP/ADD_GXB_GROUP）
//            'oaid' => $params['oaid'], // 收件人ID (Open Addressee ID)，长度在128个字符之内
//        ];
//
//        $requestParams= [
//            'secret_order_g_group_update_external_request' => $requestData
//        ];
//
//        // params
//        $params = array(
//            'api' => 'taobao.top.secret.group.update',
//            'data' => json_encode($requestParams, JSON_UNESCAPED_UNICODE), // Json格式化
//        );
//
//        // callback
//        $callback = array();
//
//        // 使用矩阵透传接口请求淘宝
//        $result = $this->__caller->call(TAOBAO_COMMON_TOP_SEND, $params, $callback, $title, 10, $original_bn);
        
        // mobile_list
        if(is_array($params['mobile_list'])){
            $json_mobile = json_encode($params['mobile_list'], JSON_UNESCAPED_UNICODE);
        }else{
            $json_mobile = $params['mobile_list'];
        }
        
        // params
        $params = array(
            'operate_type' => 'ADD_GXB_GROUP', // ADD_GXB_GROUP: 添加，DELETE_GXB_GROUP: 删除
            'tid' => $params['order_bn'], //订单号
            'mobile_list' => $json_mobile, // JSON格式
            'virtual_id' => $params['oaid'], // 收件人ID (Open Addressee ID)，长度在128个字符之内
        );
        
        // callback
        $callback = array();
        
        // 使用矩阵透传接口请求淘宝
        $result = $this->__caller->call(STORE_VIRTUAL_NUMBER_GROUP_UPDATE, $params, $callback, $title, 10, $original_bn);
        
        return $result;
    }
    
    /**
     * [淘宝700虚拟号]隐私号G组查询：查询报备外呼主叫号码组
     *
     * @param array $params
     * @return array
     */
    public function querySecretMobiles($params)
    {
        $title = '隐私号G组查询';
        
        $original_bn = $params['order_bn'];
        
        // check
        if(empty($params['oaid'])){
            return $this->error('没有提供oaid进行查询');
        }
        
        // 请求的数据
        $requestData = [
            'oaid' => $params['oaid'], // 收件人ID (Open Addressee ID)，长度在128个字符之内
        ];
        
        $requestParams= [
            'secret_order_g_group_query_external_request' => $requestData
        ];
        
        // params
        $params = array(
            'api' => 'taobao.top.secret.group.query',
            'data' => json_encode($requestParams, JSON_UNESCAPED_UNICODE), // Json格式化
        );
        
        // callback
        $callback = array();
        
        // 使用矩阵透传接口请求淘宝
        $result = $this->__caller->call(TAOBAO_COMMON_TOP_SEND, $params, $callback, $title, 10, $original_bn);
        
        return $result;
    }
}