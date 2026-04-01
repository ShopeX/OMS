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

class erpapi_logistics_matrix_xhs_request_electron extends erpapi_logistics_request_electron
{
    protected $directNum = 1;

    /**
     * bufferRequest
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function bufferRequest($sdf)
    {
        return $this->directNum;
    }

    /**
     * directRequest
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function directRequest($sdf)
    {
        $this->title     = '小红书-' . $this->__channelObj->channel['logistics_code'] . '获取电子面单';
        $this->timeOut   = 20;
        $this->primaryBn = $sdf['primary_bn'];

        $sdf['order_bns'] = array_column($sdf['order'], 'order_bn');

        $prt_tmpl_id = $sdf['dly_corp']['prt_tmpl_id'];

        $templateMdl  = app::get('logisticsmanager')->model('express_template');
        $templateInfo = $templateMdl->db_dump(['template_id' => $prt_tmpl_id]);

        $custom_mark = $mark_text = [];
        foreach ($sdf['order'] as $k => $v) {
            if ($v['custom_mark']) {
                $tmp           = unserialize($v['custom_mark']);
                $custom_mark[] = str_replace(["\t", "\r\n", "\r", "\n", "'", "\"", "\\"], '', $tmp['op_content']);
            }
            if ($v['mark_text']) {
                $tmp         = unserialize($v['mark_text']);
                $mark_text[] = str_replace(["\t", "\r\n", "\r", "\n", "'", "\"", "\\"], '', $tmp['op_content']);
            }
        }

        $params           = [];
        $params['cpCode'] = $this->__channelObj->channel['logistics_code']; // 快递公司编码
        if (in_array($params['cpCode'], ['SF', 'FOP'])) {
            $params['cpCode'] = 'shunfeng';
        }
        $params['sender'] = json_encode([
            'address' => [
                'city'     => $sdf['shop']['city'],
                'detail'   => $sdf['shop']['address_detail'],
                'district' => $sdf['shop']['area'],
                'province' => $sdf['shop']['province'],
                'town'     => $sdf['shop']['street'],
            ],
            'mobile'  => $sdf['shop']['mobile'], // 手机号码，明文
            'name'    => $sdf['shop']['default_sender'], // 姓名，明文
            'phone'   => $sdf['shop']['tel'],
        ]); // 寄件人地址必须和订购关系的地址保持一致

        // 保价计算
        $corp = $sdf['dly_corp'];
        if ($corp['protect'] == 'true') {
            $protectValue = max($sdf['delivery']['total_amount'] * $corp['protect_rate'], $corp['minprice']);
        }

        $serviceCode = json_decode($this->__channelObj->channel['service_code'], 1);

        $logisticsServices = $product_type = $customer_code = $branch_code = [];
        $payMethod = ''; // 付款方式；仅新版电子面单支持；顺丰，1:寄方付 2:收方付 3:第三方付；邮政：1寄付，2-到付
        foreach ($serviceCode as $k => $v) {
            if ($k == 'PRODUCT-TYPE') {
                $product_type = $v['value'];
                continue;
            }
            if ($k == 'customerCode') {
                $customer_code = $v['value'];
                continue;
            }
            if ($k == 'branchCode') {
                $branch_code = $v['value'];
                continue;
            }
            if ($k == 'payMethod') {
                $payMethod = $v['value'];
                continue;
            }
            if (($k == 'SVC-INSURE' || $k == 'INSURE' || $k == 'VALUE_INSURED') && $v['value'] == '1' && $corp['protect'] == 'true') {
                $logisticsServices[$k] = ['value' => sprintf('%.2f', $protectValue)];
            } else if ($k == 'insurance' && $v['value'] == '1' && $corp['protect'] == 'true') {
                $logisticsServices[$k] = ['insuranceValue' => sprintf('%.2f', $protectValue)];
            } else if ($k == 'SVC-COD' && $v['value'] == '1' && $sdf['delivery']['is_cod'] == 'true') {
                // 代收货款
                $logisticsServices[$k] = ['value' => sprintf('%.2f', $sdf['delivery']['total_amount'])];
            } else if ($v['value'] == '1') {
                $logisticsServices[$k] = new stdClass;
            }
        }

        if ($this->__channelObj->channel['ver'] == '2') {
            $params['billVersion'] = '2';
            if ($payMethod) {
                $params['payMethod'] = $payMethod;
            }
            if ($params['cpCode'] == 'shentong' && $logisticsServices['SVC-FRESH']) {
                unset($logisticsServices['SVC-FRESH']); // 生鲜件(仅旧版本支持)
            }
        } else {
            $params['billVersion'] = '1';
            if ($params['cpCode'] == 'shentong' && $logisticsServices['SVC-AXPS']) {
                unset($logisticsServices['SVC-AXPS']); // 申咚咚(仅新版本支持)
            }
            if ($params['cpCode'] == 'debangwuliu' && $logisticsServices['PRODUCT-TYPE']) {
                unset($logisticsServices['PRODUCT-TYPE']); // 产品类型(仅新版本支持)
            }
        }

        $orderInfo = [
            'orderChannelsType' => kernel::single('wms_event_trigger_logistics_data_electron_xhs')->orderChannelsType($sdf['shop']['shop_type']),
            'tradeOrderList'    => is_array($sdf['order_bns']) ? $sdf['order_bns'] : [$sdf['order_bns']],
        ];
        $custom_mark && $orderInfo['buyerMemo'] = $custom_mark;
        $mark_text && $orderInfo['sellerMemo']  = $mark_text;

        $params['tradeOrderInfoList']    = [];
        $params['tradeOrderInfoList'][0] = [
            'objectId'    => $sdf['primary_bn'], // 请求ID，保证一次批量请求不重复，返回结果基于该值取到对应的快递单号
            'orderInfo'   => $orderInfo,
            'packageInfo' => [
                'id'                   => $sdf['delivery']['delivery_id'], // 包裹ID
                'items'                => [], // 默认空，下面会赋值
                'volume'               => 0, // 体积, 单位 ml
                'weight'               => 0, // 重量,单位 g
                'length'               => 0, // 包裹长，单位厘米
                'width'                => 0, // 包裹宽，单位厘米
                'height'               => 0, // 包裹高，单位厘米
                'totalPackagesCount'   => 0, // 子母件包裹数
                'packagingDescription' => '', // 大件快运的包装方式描述
                'goodsDescription'     => '', // 大件快运的货品描述,顺丰要求必传长度不能超过20,且不能和商品名称相同
                'goodValue'            => 0, // 物流价值，单位元
            ],
            'templateId'  => $templateInfo['out_template_id'], // 电子面单模板ID
        ]; // 请求面单列表（上限10个）

        $logisticsServices && $params['tradeOrderInfoList'][0]['logisticsServices'] = json_encode($logisticsServices); // 物流服务

        $recipient = [
            'address'       => [
                'city'     => $sdf['delivery']['ship_city'],
                'detail'   => $sdf['delivery']['ship_addr'],
                'district' => $sdf['delivery']['ship_district'],
                'province' => $sdf['delivery']['ship_province'],
                'town'     => '',
            ],
            'mobile'        => $sdf['delivery']['ship_mobile'],
            'name'          => $sdf['delivery']['ship_name'],
            'phone'         => $sdf['delivery']['ship_tel'],
            'openAddressId' => $sdf['order'][0]['order_extend']['extend_field']['openAddressId'] ?: '',
        ];
        $is_encrypt = kernel::single('ome_security_router', $sdf['delivery']['shop_type'])->is_encrypt($sdf['delivery'], 'delivery');
        if ($is_encrypt) {
            $original = app::get('ome')->model('order_receiver')->db_dump(['order_id' => $sdf['delivery']['delivery_order'][0]['order_id']], 'encrypt_source_data');
            if ($original) {
                $encrypt_source_data = json_decode($original['encrypt_source_data'], 1);
                if ($encrypt_source_data) {
                    $recipient = [
                        'address'       => [
                            'city'     => $sdf['delivery']['ship_city'],
                            'detail'   => explode('>>', $sdf['delivery']['ship_addr'])[0],
                            'district' => $sdf['delivery']['ship_district'],
                            'province' => $sdf['delivery']['ship_province'],
                            'town'     => '',
                        ],
                        'mobile'        => $encrypt_source_data['buyer_mobile_index_origin'],
                        'name'          => explode('>>', $sdf['delivery']['ship_name'])[0],
                        'phone'         => $encrypt_source_data['buyer_phone_index_origin'],
                        'openAddressId' => $sdf['order'][0]['order_extend']['extend_field']['openAddressId'] ?: '',
                    ];
                }
            }
        }

        // 和小红书沟通，如果有openAddressId，就不传detail，因为传了小红书有可能会返回收货地址无效
        if ($recipient['openAddressId']) {
            $recipient['address']['detail'] = '';
        }
    
        if ($recipient['mobile'] == $recipient['openAddressId']){
            $recipient['mobile'] = '';
        }
        if ($recipient['name'] == $recipient['openAddressId']){
            $recipient['name'] = '';
        }
        if ($recipient['phone'] == $recipient['openAddressId']){
            $recipient['phone'] = '';
        }

        $params['tradeOrderInfoList'][0]['recipient'] = $recipient;

        foreach ($sdf['delivery_item'] as $k => $v) {
            $params['tradeOrderInfoList'][0]['packageInfo']['items'][] = [
                'count' => $v['number'],
                'name'  => $v['product_name'],
                // 'specification' => '',
            ];
        }
        $params['tradeOrderInfoList'] = json_encode($params['tradeOrderInfoList']);

        // $params['extraInfo']          = ''; // 拓展信息，预留字段，目前没有使用
        if ($customer_code) {
            $params['customerCode'] = $customer_code; // 月结卡号，直营快递公司一般要求必填
        }
        if (in_array($this->__channelObj->channel['logistics_code'], ['SF', 'FOP'])) {
            $params['brandCode'] = $this->__channelObj->channel['logistics_code']; // 品牌编码，顺丰要求必填
        }
        if ($product_type) {
            $params['productCode'] = $product_type; // 产品编码，京东要求必填，仅部分快递公司支持传入
        }
        $params['callDoorPickUp'] = 'false'; // 是否预约上门，仅部分快递公司支持传入
        // $params['doorPickUpTime']    = ''; // 预约上门取件时间，'yyyy-MM-dd HH:mm:ss'，仅部分快递公司支持传入
        // $params['doorPickUpEndTime'] = ''; // 预约上门取件截止时间，'yyyy-MM-dd HH:mm:ss'，仅部分快递公司支持传入
        $params['sellerName'] = $sdf['shop']['shop_name']; // 店铺名称，对参数内容没有限制
        if ($branch_code) {
            $params['branchCode'] = $branch_code; // 网点编码，加盟型快递公司一般要求必填
        }

        $result = $this->requestCall(STORE_WAYBILL_GET, $params, array());

        $result['billVersion'] = $params['billVersion']; // 版本存到json_packet中，printer.js需要
        $returnResult = $this->backToResult($result, $sdf['delivery']);

        return $returnResult;
    }

    private function backToResult($ret, $delivery)
    {
        $waybill = empty($ret['data']) ? array() : json_decode($ret['data'], true);
        if (empty($waybill) || $ret['rsp'] == 'fail') {
            return $ret['msg'] ? $ret['msg'] : false;
        }
        $result = array();
        foreach ($waybill['data']['wayBillList'] as $val) {
            $deliveryBn = trim($val['objectId']);
            $val['billVersion']  = $ret['billVersion']; // 版本存到json_packet中，printer.js需要

            $result[] = array(
                'succ'           => $val['waybillCode'] ? true : false,
                'msg'            => '',
                'delivery_id'    => $delivery['delivery_id'],
                'delivery_bn'    => $deliveryBn,
                'logi_no'        => $val['waybillCode'],
                'mailno_barcode' => '',
                'qrcode'         => '',
                'position'       => '',
                'position_no'    => '',
                'package_wdjc'   => '',
                'package_wd'     => '',
                'print_config'   => '',
                'json_packet'    => is_array($val) ? json_encode($val) : $val,
            );
        }
        if ($ret['err_msg'] || $ret['rsp'] == 'fail') {
            $result[] = array(
                'succ'        => false,
                'msg'         => $ret['err_msg'],
                'delivery_id' => $delivery['delivery_id'],
                'delivery_bn' => $delivery['delivery_bn'],
            );
        }
        $this->directDataProcess($result);
        return $result;
    }

    /**
     * recycleWaybill
     * @param mixed $waybillNumber waybillNumber
     * @param mixed $delivery_bn delivery_bn
     * @return mixed 返回值
     */
    public function recycleWaybill($waybillNumber, $delivery_bn = '')
    {
        app::get('logisticsmanager')->model('waybill')->update(array('status' => 2, 'create_time' => time()), array('waybill_number' => $waybillNumber));

        $this->title     = '小红书_' . $this->__channelObj->channel['logistics_code'] . '取消电子面单';
        $this->primaryBn = $waybillNumber;

        $cpCode = $this->__channelObj->channel['logistics_code'];
        if (in_array($cpCode, ['SF', 'FOP'])) {
            $cpCode = 'shunfeng';
        }
        $params = array(
            'waybillCode' => $waybillNumber,
            'cpCode'      => $cpCode,
        );

        if ($this->__channelObj->channel['ver'] == '2') {
            $params['billVersion'] = '2';
        }

        $callback = array(
            'class'  => get_class($this),
            'method' => 'callback',
        );
        $this->requestCall(STORE_WAYBILL_CANCEL, $params, $callback);
    }

    /**
     * 获取EncryptPrintData
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getEncryptPrintData($sdf)
    {
        $cpCode = $this->__channelObj->channel['logistics_code'];
        if (in_array($cpCode, ['SF', 'FOP'])) {
            $cpCode = 'shunfeng';
        }

        $params = array(
            'waybillCode' => $sdf['logi_no'],
            'cpCode'      => $cpCode,
        );

        if ($this->__channelObj->channel['ver'] == '2') {
            $params['billVersion'] = '2';
        }

        $title = '获取打印数据';

        $result = $this->__caller->call(STORE_WAYBILL_PRINTDATA, $params, array(), $title, 10, $sdf['logi_no']);
        if ($result['rsp'] == 'succ' && $result['data']) {
            $data = json_decode($result['data'], 1);

            $result['data'] = $data;
            // $result['data']['params'] = $data['extend_field'];
        } else {
            $result['msg'] = $result['err_msg'];
        }

        return $result;
    }

    public function getWaybillISearch($sdf = array())
    {
        $params = [
            'cpCode' => $this->__channelObj->channel['logistics_code'],
        ];
        if (in_array($this->__channelObj->channel['logistics_code'], ['SF','FOP'])) {
            $params = [
                'cpCode'    => 'shunfeng',
                'brandCode' => $this->__channelObj->channel['logistics_code'],
            ];
        }
        if ($this->__channelObj->channel['ver'] == '2') {
            $params['billVersion'] = '2';
        }

        $title = '查询开通的网点账号信息';

        $result = $this->__caller->call(STORE_STANDARD_XHS_SEARCH, $params, array(), $title, 6, $this->__channelObj->channel['logistics_code']);

        if ($result['rsp'] == 'succ' && $result['data']) {

            $data           = json_decode($result['data'], 1);
            $result['data'] = $data;

        } else {
            $result['msg'] = $result['err_msg'];
        }
        $result['request_logistics_code'] = $this->__channelObj->channel['logistics_code'];
        $result['channel_type']           = $this->__channelObj->channel['channel_type'];

        $_tmp = [];
        if (isset($result['data']['data']['subscribeList'])) {
            foreach ($result['data']['data']['subscribeList'] as $k => $v) {
                $_tmp[] = [
                    'acct_id'        => $result['data']['data']['accountId'],
                    'delivery_id'    => $v['cpCode'] == 'shunfeng'?$v['brandCode']:$v['cpCode'],
                    'site_code'      => $v['cpCode'],
                    'site_name'      => $v['cpName'],
                    'mobile'         => $v['senderAddressList'][0]['mobile'],
                    'phone'          => $v['senderAddressList'][0]['phone'],
                    'name'           => $v['senderAddressList'][0]['name'],
                    'province_name'  => $v['senderAddressList'][0]['address']['province'],
                    'city_name'      => $v['senderAddressList'][0]['address']['city'],
                    'district_name'  => $v['senderAddressList'][0]['address']['district'],
                    'street_name'    => $v['senderAddressList'][0]['address']['town'],
                    'detail_address' => $v['senderAddressList'][0]['address']['detail'],
                    // 一个物流品牌多个签约地的时候，只有一个accountId，控制层拿到数据是以acct_id为key组数据，需要加一个唯一key
                    '_unique_acct_id'=> $result['data']['data']['accountId'].'-'.$v['branchCode'],
                ];
            }
            $result['data']['account_list'] = $_tmp;
        } else {
            $result['data'] = $_tmp;
        }

        return $result;
        // return array('rsp' => $result['rsp'], 'msg' => $result['rsp'] == 'succ' ? '获取成功' : '获取失败');
    }

}
