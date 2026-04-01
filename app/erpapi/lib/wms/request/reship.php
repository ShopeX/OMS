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
 * 退货单推送
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_request_reship extends erpapi_wms_request_abstract
{

    /**
     * 退货单创建
     *
     * @return void
     * @author
     **/
    public function reship_create($sdf)
    {
        $reship_bn = $sdf['reship_bn'];

        // 判断是否已被删除
        $iscancel = kernel::single('console_service_commonstock')->iscancel($reship_bn);
        if ($iscancel) {
            $this->succ('退货单已取消,终止同步');
        }
        $this->_dealEncryptData($sdf);
        $title = $this->__channelObj->wms['channel_name'] . '退货单添加';

        $params = $this->_format_reship_create_params($sdf);
        
        // 调用 service 进行参数扩展或修改
        if ($service = kernel::servicelist('erpapi.service.wms.reship.params.format')) {
            foreach ($service as $object => $instance) {
                if (method_exists($instance, 'format_reship_create_params')) {
                    $params = $instance->format_reship_create_params($sdf, $params, $this->__channelObj);
                }
            }
        }
        
        if (!$params) {
            return $this->error('参数为空,终止同步');
        }

        $callback = array(
            'class'  => get_class($this),
            'method' => 'reship_create_callback',
            'params' => array('reship_bn' => $reship_bn, 'obj_bn' => $reship_bn, 'obj_type' => 'reship'),
        );

        $retry = array(
            'obj_bn'        => $reship_bn,
            'obj_type'      => 'reship',
            'channel'       => 'wms',
            'channel_id'    => $this->__channelObj->wms['channel_id'],
            'method'        => 'reship_create',
            'args'          => func_get_args(),
            'next_obj_type' => $this->_getNextObjType()
        );
        $apiFailId = app::get('erpapi')->model('api_fail')->saveRunning($retry);
        if($apiFailId) {
            $callback['params']['api_fail_id'] = $apiFailId;
        }

        // 判断是否加密
        $gateway = '';
        $sdf['shop_type'] = $sdf['shop_type'] == 'tmall' ? 'taobao' : $sdf['shop_type'];
        if (kernel::single('ome_security_router', $sdf['shop_type'])->is_encrypt($sdf, 'reship')) {
            $params['s_node_type'] = $sdf['shop_type'];
            $params['s_node_id']   = $sdf['node_id'];
            $params['order_bns']   = $sdf['order_bn'];

            $need_encrypt_list = $this->__channelObj->wms['config']['need_encrypt'];
            if ($need_encrypt_list && $need_encrypt_list[$sdf['shop_type']]) {
                $params['need_encrypt'] = 'true';
            }

            $gateway = $sdf['shop_type'];
        }

        return $this->__caller->call(WMS_RETURNORDER_CREATE, $params, $callback, $title, 10, $reship_bn, true, $gateway);
    }

    protected function _dealEncryptData(&$sdf) {
        $shop_type = $sdf['shop_type'] == 'tmall' ? 'taobao' : $sdf['shop_type'];
        if (kernel::single('ome_security_router', $shop_type)->is_encrypt($sdf, 'reship')) {
            foreach ($sdf as $key => $string) {
                if(is_string($string)) {
                    $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($string);
                    if ($is_encrypt) {
                        if($index = strpos($string, '>>')) {
                            $sdf[$key] = substr($string, 0, $index);
                            continue;
                        }

                        if($index = strpos($string, '@hash')) {
                            $sdf[$key] = substr($string, 0, $index);
                            continue;
                        }

                        if($index = strpos($string, '&gt;&gt;')) {
                            $sdf[$key] = substr($string, 0, $index);
                            continue;
                        }
                    }

                }
            }
        }
    }

    public function reship_create_callback($response, $callback_params)
    {
        // 更新外部编码
        $rsp     = $response['rsp'];
        $err_msg = $response['err_msg'];
        $data    = @json_decode($response['data'], true);
        $data = is_array($data) ? $data : [];
        $msg_id  = $response['msg_id'];
        $res     = $response['res'];

        $reship_bn = $callback_params['reship_bn'];
        $reshipObj = app::get('ome')->model('reship');
        if ($data['wms_order_code'] && $reship_bn) {
            $reshipObj->db->exec("UPDATE sdb_ome_reship SET out_iso_bn='" . $data['wms_order_code'] . "' WHERE reship_bn='" . $reship_bn . "'");
        }
        $reshipObj->update(['sync_status'=>($rsp == 'succ' ? '3' : '2'),'sync_msg'=>$err_msg], ['reship_bn'=>$reship_bn]);
        /***
         * @todo：(已咨询同事)注销此段代码;
         * $this->_getNextObjType()方法不存在,导致回调报500错误;
         * 
        if ($rsp == 'succ' && $this->_getNextObjType()) {
            //把单号加进队列
            $failApiModel = app::get('erpapi')->model('api_fail');
            $api_data     = array(
                'obj_type' => $this->_getNextObjType(),
                'obj_bn'   => $reship_bn,
                'obj_id'   => '',
                'params'   => $callback_params['params'],

            );
            $failApiModel->publish_api_fail(WMS_INORDER_GET, $api_data, array('rsp' => 'fail'));

        }
        ***/
        
        $callback_params['obj_bn']   = $reship_bn;
        $callback_params['obj_type'] = 'reship';
        return $this->callback($response, $callback_params);
    }

    protected function _format_reship_create_params($sdf)
    {
        $sdf['item_total_num'] = $sdf['line_total_count'] = count($sdf['items']);

        $items = array('item' => array());
        if ($sdf['items']) {
            sort($sdf['items']);
            foreach ((array) $sdf['items'] as $k => $v) {
                // 获取外部商品sku
                $items['item'][] = array(
                    'item_code'      => $v['bn'],
                    'item_name'      => $v['name'],
                    'item_quantity'  => (int) $v['num'],
                    'item_price'     => $v['price'] ? (float) $v['price'] : 0, // TODO: 商品价格
                    'item_line_num'  => ($k + 1), // TODO: 订单商品列表中商品的行项目编号，即第n行或第n个商品
                    'trade_code'     => '', //可选(若是淘宝交易订单，并且不是赠品，必须要传订单来源编号)
                    'item_id'        => $v['bn'], // 商品ID
                    'is_gift'        => '0', // TODO: 判断是否为赠品0:不是1:是
                    'item_remark'    => '', // TODO: 商品备注
                    'inventory_type' => $sdf['branch_type'] == 'damaged' ? '101' : '1', // TODO: 库存类型1可销售库存101类型用来定义残次品201冻结类型库存301在途库存
                    'sn'             => $v['sn'] ? ['sn' => $v['sn']] : '',
                );
            }
        }

        $create_time = preg_match('/-|\//', $sdf['create_time']) ? $sdf['create_time'] : date("Y-m-d H:i:s", $sdf['create_time']);
        $reship_bn   = $sdf['reship_bn'];

        $shop_code      = kernel::single('wmsmgr_func')->getWmsShopCode($this->__channelObj->wms['channel_id'], $sdf['shop_bn']);

        $params = array(
            'uniqid'              => self::uniqid(),
            'source'              => (string)$shop_code,
            'wms_supplier'        => '', //    TODO: 服务提供商编号
            'out_order_code'      => $reship_bn,
            'warehouse_code'      => $sdf['branch_bn'],
            'orig_order_code'     => $sdf['original_delivery_bn'],
            'created'             => $create_time,
            'logistics_no'        => $sdf['logi_no'],
            'logistics_code'      => $this->get_logistics_code($sdf['logi_name']),
            'logistics_name'      => $sdf['logi_name'], //物流公司名称
            'remark'              => $sdf['memo'],
            'platform_order_code' => $sdf['order_bn'], //订单号
            'wms_order_code'      => $reship_bn,
            'is_finished'         => 'true',
            'current_page'        => '1', // 当前批次,用于分批同步
            'total_page'          => '1', // 总批次,用于分批同步
            'receiver_name'       => $sdf['receiver_name'],
            'receiver_zip'        => $sdf['receiver_zip'],
            'receiver_state'      => $sdf['receiver_state'],
            'receiver_city'       => $sdf['receiver_city'],
            'receiver_district'   => $sdf['receiver_district'],
            'receiver_address'    => $sdf['receiver_addr'],
            'receiver_phone'      => $sdf['receiver_tel'],
            'receiver_mobile'     => $sdf['receiver_mobile'],
            'receiver_email'      => $sdf['receiver_email'],
            'sign_code'           => '', // TODO: 节点标识，请求唯一标识
            'dest_plan'           => '', // TODO: 目的计划点
            'line_total_count'    => $sdf['line_total_count'], // TODO: 订单行项目数量
            'storage_code'        => $sdf['storage_code'], // 库内存放点编号
            'items'               => json_encode($items),
            'shop_nick'           => $this->_getShopCode(array('shop_bn' => $sdf['shop_bn'])),
            'return_reason'       => $sdf['return_reason'],  
        );

        return $params;
    }

    public function reship_cancel($sdf)
    {
        return $this->error('接口方法不存在', 'w402');
    }

    /**
     * 退货单创建取消
     *
     * @return void
     * @author
     **/
    /*
    public function reship_cancel($sdf){
    $reship_bn = $sdf['reship_bn'];

    $title = $this->__channelObj->wms['channel_name'].'退货单取消'.$reship_bn;

    $params = $this->_format_reship_cancel_params($sdf);

    return $this->__caller->call(WMS_RETURNORDER_CANCEL, $params, null, $title, 10, $reship_bn);
    }*/

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    protected function _format_reship_cancel_params($sdf)
    {
        $params = array(
            'out_order_code' => $sdf['reship_bn'],
            'warehouse_code' => $sdf['branch_bn'],
        );

        return $params;
    }

    protected function _get_search_api() {
        return '';
    }
    /**
     * 退货单查询
     *
     * @return void
     * @author
     **/
    public function reship_search($sdf)
    {
        $title = $this->__channelObj->wms['channel_name'] . '退货单查询';

        $params = $this->_format_search_params($sdf);

        $apiName = $this->_get_search_api();
        if(empty($apiName)) {
            return;
        }
        $rs        = $this->__caller->call($apiName, $params, null, $title, 10, $sdf['stockin_bn']);
        $failModel = app::get('erpapi')->model('api_fail');
        if ($rs['rsp'] == 'succ') {
            $result = $this->_deal_search_result($rs);
            if ($result['data']) {
                $rs = kernel::single('erpapi_router_response')->set_node_id($this->__channelObj->wms['node_id'])->set_api_name('wms.reship.status_update')->dispatch($result['data']);
                if ($rs['rsp'] == 'succ' && $result['data']['status'] != 'PARTIN' && $result['io_status'] != 'PARTIN') {
                    $failModel->delete(array('id' => $sdf['obj_id']));
                }
            }
        } else {
            $failModel->db->exec("UPDATE sdb_erpapi_api_fail SET fail_times=fail_times+1,msg_id='" . $rs['msg_id'] . "' WHERE id=" . $sdf['obj_id']);
        }
    }

    protected function _format_search_params($sdf)
    {
        $params = array(
            'out_order_code' => $sdf['out_order_code'],
        );
        return $params;
    }

    protected function _deal_search_result($rs)
    {
        return $rs;
    }
    
    //同步WMS仓储的售后原因
    public function reship_resaon($sdf)
    {
        return $this->error('接口方法不存在', 'w402');
    }
    
    //同步第三方仓储WMS异常错误码
    public function reship_errorcode($sdf)
    {
        return $this->error('接口方法不存在', 'w402');
    }
}
