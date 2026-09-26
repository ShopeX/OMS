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

class ome_security_youzan extends ome_security_hash
{
    // 订单加密字段
    protected $_order_encrypt = array(
       'ship_name'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/name',
        ),
       'ship_tel'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/telephone',
        ),
        'ship_mobile' => array(
            'type' => 'simple',
            'sdf'  => 'consignee/mobile',
        ),
        'ship_addr'   => array(
            'type' => 'simple',
            'sdf'  => 'consignee/addr',
        ),
        'uname' => array (
            'type' => 'simple',
            'jdkey' => 'uname',
        ),
    );

    // 会员加密字段
    protected $_member_encrypt = array(
        'uname'  => array(
            'type' => 'simple',
            'sdf'  => 'account/uname',
        ),
        'name'   => array(
            'type' => 'simple',
            'sdf'  => 'contact/name',
            'jdkey' => 'uname',
        ),
        'mobile' => array(
            'type' => 'simple',
            'sdf'  => 'contact/phone/mobile',
            'jdkey' => 'ship_mobile',
        ),
        'tel' => array(
            'type' => 'simple',
            'sdf'  => 'contact/phone/telephone',
            'jdkey' => 'ship_tel',
        ),
    );

    // 发货单加密字段
    protected $_delivery_encrypt = array(
        'ship_name'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/name',
        ),
        'ship_tel'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/telephone',
        ),
        'ship_mobile' => array(
            'type' => 'simple',
            'sdf'  => 'consignee/mobile',
        ),
        'ship_addr'   => array(
            'type' => 'simple',
            'sdf'  => 'consignee/addr',
        ),
        'uname' => array (
            'type' => 'simple',
            'jdkey' => 'uname',
        ),
    );

    protected $_sales_delivery_encrypt = array (
        'ship_name'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/name',
        ),
        'ship_tel'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/telephone',
        ),
        'ship_mobile' => array(
            'type' => 'simple',
            'sdf'  => 'consignee/mobile',
        ),
        'ship_addr'   => array(
            'type' => 'simple',
            'sdf'  => 'consignee/addr',
        ),
        'uname' => array (
            'type' => 'simple',
            'jdkey' => 'uname',
        ),
    );

    // 开票加密字段
    protected $_invoice_encrypt = array (
        'ship_tel' => array(
            'type' => 'simple',
        ),
        'ship_name'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/name',
        ),
        'ship_addr' => array(
            'type' => 'simple',
        ),
    );

    // 跨境加密字段
    protected $_customs_encrypt = array (
        'member_uname' => array(
            'type' => 'simple',
            'jdkey' => 'uname',
        ),
        'member_name' => array(
            'type' => 'simple',
            'jdkey' => 'uname',
        ),
        'member_mobile' => array(
            'type' => 'simple',
            'jdkey' => 'ship_mobile',
        ),
    );

    // 订单加密字段
    protected $_reship_encrypt = array(
        'ship_name'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/telephone',
        ),
        'ship_tel'    => array(
            'type' => 'simple',
            'sdf'  => 'consignee/telephone',
        ),
        'ship_mobile' => array(
            'type' => 'simple',
            'sdf'  => 'consignee/mobile',
        ),
        'ship_addr'   => array(
            'type' => 'simple',
            'sdf'  => 'consignee/addr',
        ),
        'uname' => array (
            'type' => 'simple',
            'jdkey' => 'uname',
        ),
    );

    // 订单加密字段
    protected $_aftersale_encrypt = array(
        'ship_mobile' => array(
            'type' => 'simple',
        ),
        'member_uname' => array (
            'type' => 'simple',
            'jdkey' => 'uname',
        ),
    );
    /**
     * 获取加密请求BODY
     * @param  [type] $order_id    [description]
     * @param  [type] $shop_id [description]
     * @param  string $type    [description]
     * @return [type]          [description]
     */
    public function get_encrypt_body($data, $type = 'order')
    {
        $shop = app::get('ome')->model('shop')->db_dump($data['shop_id'], 'node_id,node_type');

        if (!$shop['node_id']) {
            return array();
        }
        $order_bn = explode(',', $data['order_bn']);
        $order_bn = $order_bn[0];
        $body = array(
            'tids'         => $order_bn,
            'method'       => 'shopex.youzan.open.decrypt',
            'to_node_id'   => $shop['node_id'],
            'from_node_id' => base_shopnode::node_id('ome'),
            'node_type'    => $shop['node_type'],
            'url'          => $this->get_url(),
        );

        // 判断哪些字段已经非可逆加密了
        $tmpEncryptData = [];
        foreach ((array) $this->get_encrypt_fields($type) as $c => $r) {
            if ($this->get_code() == substr($data[$c], -5)) {
                $body['fields'][$c] = $index = $r['jdkey'] ? $r['jdkey'] : $c;
                $tmpEncryptData[$index] = substr($data[$c], 0, -5);
            }
        }

        // 无非可逆字段
        if (!$body['fields']) {
            return array();
        }

        $body['fields'] = json_encode($body['fields']);
        $body['data'] = json_encode([$order_bn=>$tmpEncryptData]);
        $body['sign']   = base_certificate::gen_sign($body);

        $this->_log($body);

        return $body;
    }


    public function get_url()
    {
        return OMS_DES_URL;
    }

    /**
     * 有赞自提信息解密（o2o_info.self_fetch_info_encrypted）
     *
     * @param string $shop_id
     * @param string $order_bn
     * @param string $encrypted
     * @return array 解密后的 self_fetch_info 数组
     */
    public function decryptSelfFetchInfo($shop_id, $order_bn, $encrypted)
    {
        if (!$shop_id || !$order_bn || !$encrypted) {
            return array();
        }

        $shop = app::get('ome')->model('shop')->db_dump($shop_id, 'node_id,node_type');
        if (!$shop['node_id']) {
            return array();
        }

        $order_bn = explode(',', $order_bn);
        $order_bn = $order_bn[0];

        $body = array(
            'tids'         => $order_bn,
            'method'       => 'shopex.youzan.open.decrypt',
            'to_node_id'   => $shop['node_id'],
            'from_node_id' => base_shopnode::node_id('ome'),
            'node_type'    => $shop['node_type'],
            'url'          => $this->get_url(),
            'fields'       => json_encode(array('self_fetch_info' => 'self_fetch_info')),
            'data'         => json_encode(array($order_bn => array('self_fetch_info' => $encrypted))),
        );
        $body['sign'] = base_certificate::gen_sign($body);

        $this->_log($body);

        $res = $this->_requestSelfFetchDecrypt($body, $order_bn);
        if (!$res || $res['rsp'] != 'succ' || empty($res['data'][$order_bn]['self_fetch_info'])) {
            return array();
        }

        $info = json_decode($res['data'][$order_bn]['self_fetch_info'], true);

        return is_array($info) ? $info : array();
    }

    /**
     * 请求有赞自提解密（支持测试脚本通过 GLOBALS 注入 mock 报文）
     *
     * @param array  $body
     * @param string $order_bn
     * @return array
     */
    protected function _requestSelfFetchDecrypt($body, $order_bn)
    {
        if (!empty($GLOBALS['ome_security_youzan_mock_self_fetch_decrypt'])) {
            $mock = $GLOBALS['ome_security_youzan_mock_self_fetch_decrypt'];
            if (is_string($mock)) {
                $mock = @json_decode($mock, true);
            }
            if (is_array($mock)) {
                if (!empty($mock['data'][$order_bn]['self_fetch_info'])) {
                    return $mock;
                }
                if (!empty($mock['self_fetch_info'])) {
                    return array(
                        'rsp'  => 'succ',
                        'data' => array(
                            $order_bn => array(
                                'self_fetch_info' => is_array($mock['self_fetch_info'])
                                    ? json_encode($mock['self_fetch_info'], JSON_UNESCAPED_UNICODE)
                                    : $mock['self_fetch_info'],
                            ),
                        ),
                    );
                }
            }
        }

        $res = kernel::single('base_httpclient')->set_timeout(10)->post($body['url'], $body);

        return @json_decode($res, true);
    }

}