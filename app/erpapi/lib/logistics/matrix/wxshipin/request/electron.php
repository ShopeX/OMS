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

class erpapi_logistics_matrix_wxshipin_request_electron extends erpapi_logistics_request_electron
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
     * @author mxc 2023-10-09 16:38
     * @describe 电子面单预取号
     */
    public function directRequestPre($sdf = '', &$params)
    {
        // $sdf['order'][0]['order_bn'] = '3714984178963580928';
        $this->title     = '微信视频号-' . $this->__channelObj->channel['logistics_code'] . '电子面单预取号';
        $this->timeOut   = 20;
        $this->primaryBn = $sdf['primary_bn'];

        $sender = [
            'name'     => $sdf['shop']['default_sender'], // 人名
            'mobile'   => $sdf['shop']['mobile'], // 联系电话
            'province' => $sdf['shop']['province'], // 省
            'city'     => $sdf['shop']['city'], // 市
            'county'   => $sdf['shop']['area'], // 区
            'street'   => $sdf['shop']['street'], // 街道
            'address'  => $sdf['shop']['address_detail'], // 详细地址
        ];
        $receiver = [
            'name'     => $sdf['delivery']['ship_name'], // 人名
            'mobile'   => $sdf['delivery']['ship_mobile'], // 联系电话
            'province' => $sdf['delivery']['ship_province'], // 省
            'city'     => $sdf['delivery']['ship_city'], // 市
            'county'   => $sdf['delivery']['ship_district'], // 区
            'street'   => $sdf['delivery']['ship_town'], // 街道
            'address'  => $sdf['delivery']['ship_addr'], // 详细地址
        ];
        $goods_list = [];
        foreach ($sdf['delivery_item'] as $k => $v) {
            $goods_info = [
                'good_name'  => $v['product_name'], // 商品名
                'good_count' => (int) $v['number'], // 商品个数
                'product_id' => (int) $v['shop_goods_id'], // 商品product id
                'sku_id'     => (int) $v['shop_product_id'], // 商品sku id
            ];
            if (!$v['shop_goods_id'] || $v['shop_goods_id'] == '-1') {
                //本地商品或本地赠品不传product_id和sku_id
                unset($goods_info['product_id'], $goods_info['sku_id']);
            }
            $goods_list[] = $goods_info;
        }
        $ec_order_list = [[
            'ec_order_id' => (int) $sdf['order'][0]['order_bn'],
            'goods_list'  => $goods_list,
        ]];

        // 代发模式
        if ($sdf['delivery']['shop']['addon']['unikey'] && $sdf['delivery']['shop']['shop_id'] != $this->__channelObj->channel['shop_id']) {
            $original = app::get('ome')->model('order_receiver')->db_dump(['order_id' => $sdf['order'][0]['order_id']], 'encrypt_source_data');
            if ($original) {
                $encrypt_source_data = json_decode($original['encrypt_source_data'], 1);
                if ($encrypt_source_data['ewaybill_order_code']) {            
                    $ec_order_list = [[
                        'goods_list'           => $goods_list,
                        'ewaybill_order_code'  => $encrypt_source_data['ewaybill_order_code'],
                        'ewaybill_order_appid' => $sdf['delivery']['shop']['addon']['unikey'],
                    ]];
                }
            }
        }

        $remark = [];
        foreach ($sdf['order'] as $ok => $ov) {
            if ($ov['custom_mark']) {
                $tmp      = unserialize($ov['custom_mark']);
                $remark[] = str_replace(["\t", "\r\n", "\r", "\n", "'", "\"", "\\"], '', $tmp['op_content']);
            }
            if ($ov['mark_text']) {
                $tmp      = unserialize($ov['mark_text']);
                $remark[] = str_replace(["\t", "\r\n", "\r", "\n", "'", "\"", "\\"], '', $tmp['op_content']);
            }
        }

        $params = [
            'order_bn'         => json_encode(array_column($sdf['order'], 'order_bn')), // oms日志记录的时候存int类型有问题,所以单独存一个字段
            'delivery_id'      => $sdf['dly_corp']['type'], // 快递公司id
            'site_code'        => $sdf['shop']['site_code'], // 网点编码
            'ewaybill_acct_id' => $sdf['shop']['acct_id'], // 电子面单账号id
            'sender'           => json_encode($sender), // 寄件人,传明文
            'receiver'         => json_encode($receiver), // 收件人,传小店订单内获取到的用户信息即可
            'ec_order_list'    => json_encode($ec_order_list), // 订单信息
            'remark'           => $remark ? implode('; ', $remark) : '', // 备注
            'shop_id'          => $sdf['shop']['shop_id'], // 店铺id（从查询开通账号信息接口获取）
        ];

        $jst     = [];
        $gateway = '';
        $result  = $this->requestCall(STORE_WAYBILL_PRE_GET, $params, array(), $jst, $gateway);
        $waybill = empty($result['data']) ? array() : json_decode($result['data'], true);

        if ($result['rsp'] == 'succ' && $waybill['ewaybill_order_id']) {
            // 取号成功以后，会保存在sdb_logisticsmanager_waybill_extend的print_config里
            $params['ewaybill_order_id'] = $waybill['ewaybill_order_id'];
        }
        return $result;
    }

    /**
     * directRequest
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function directRequest($sdf)
    {
        // 电子面单预取号
        $preRes = $this->directRequestPre($sdf, $preParams);
        if (!$preParams['ewaybill_order_id']) {
            $returnResult = $this->backToResult($preRes, $sdf['delivery']);
            return $returnResult;
        }

        $this->title     = '微信视频号-' . $this->__channelObj->channel['logistics_code'] . '获取电子面单';
        $this->timeOut   = 20;
        $this->primaryBn = $sdf['primary_bn'];

        $preParams['template_id'] = $sdf['template']['out_template_id'];

        // // 退货地址
        // $preParams['return_address'] = json_encode([
        //     'name'     => $sdf['shop']['default_sender'], // 人名
        //     'mobile'   => $sdf['shop']['mobile'], // 联系电话
        //     'province' => $sdf['shop']['province'], // 省
        //     'city'     => $sdf['shop']['city'], // 市
        //     'county'   => $sdf['shop']['area'], // 区
        //     'street'   => $sdf['shop']['street'], // 街道
        //     'address'  => $sdf['shop']['address_detail'], // 详细地址
        // ]);

        $result = $this->requestCall(STORE_WAYBILL_GET, $preParams, array());

        $returnResult = $this->backToResult($result, $sdf['delivery']);
        return $returnResult;
    }

    private function backToResult($ret, $delivery)
    {
        $waybill = empty($ret['data']) ? array() : json_decode($ret['data'], true);
        if (empty($waybill) || $ret['rsp'] == 'fail') {
            return $ret['msg'] ? $ret['msg'] : false;
        }
        $result   = array();
        $result[] = array(
            'succ'           => $waybill['waybill_id'] ? true : false,
            'msg'            => '',
            'delivery_id'    => $delivery['delivery_id'],
            'delivery_bn'    => $delivery['delivery_bn'],
            'logi_no'        => $waybill['waybill_id'],
            'mailno_barcode' => '',
            'qrcode'         => '',
            'position'       => '',
            'position_no'    => '',
            'package_wdjc'   => '',
            'package_wd'     => '',
            'print_config'   => json_encode(['ewaybill_order_id' => $waybill['ewaybill_order_id']]),
            'json_packet'    => is_array($waybill) ? json_encode($waybill) : $waybill,
        );
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

        $this->title     = '微信视频号_' . $this->__channelObj->channel['logistics_code'] . '取消电子面单';
        $this->primaryBn = $waybillNumber;

        $ewaybill_order_id = $this->getEwaybillOrderIdFromLogNo($waybillNumber);

        $params = array(
            'ewaybill_order_id' => $ewaybill_order_id, // 电子面单订单id，全局唯一id
            'delivery_id'       => $this->__channelObj->channel['logistics_code'], // 快递公司id
            'logistics_no'      => $waybillNumber, // 快递单号

        );
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
        $ewaybill_order_id = $this->getEwaybillOrderIdFromLogNo($sdf['logi_no']);

        $deliveryInfo = app::get('wms')->model('delivery')->db_dump(['delivery_bn' => $sdf['delivery_bn']]);
        $dlyCorpInfo  = app::get('ome')->model('dly_corp')->db_dump(['corp_id' => $deliveryInfo['logi_id']]);
        app::get('ome')->model('dly_corp_channel')->getChannel($dlyCorpInfo, array($deliveryInfo));
        $prt_tmpl_id  = $dlyCorpInfo['prt_tmpl_id'];
        $templateMdl  = app::get('logisticsmanager')->model('express_template');
        $templateInfo = $templateMdl->db_dump(['template_id' => $prt_tmpl_id]);

        if (strpos($templateInfo['out_template_id'], 'single') !== false) {
            $template_id = 'single';
        } else {
            $template_id = $templateInfo['out_template_id'];
        }

        $params = array(
            'ewaybill_order_id' => $ewaybill_order_id, // 电子面单订单id，全局唯一id（从预取号接口获取或者自定义）
            'template_id'       => $template_id, // 模板id, 如无需使用后台模板，可直接传递template_type做为默认模板， 如‘single’
        );

        $title = '获取打印数据';

        $result = $this->__caller->call(STORE_WAYBILL_PRINTDATA, $params, array(), $title, 10, $sdf['logi_no']);
        if ($result['rsp'] == 'succ' && $result['data']) {
            $data = json_decode($result['data'], 1);

            $result['data'] = $data['print_info'];
            // $result['data']['params'] = $data['extend_field'];
        } else {
            $result['msg'] = $result['err_msg'];
        }

        return $result;
    }

    public function getWaybillISearch($sdf = array())
    {
        $params = [
            // 'need_balance' => true, // 必填, 因为矩阵默认是true，所以不传
            'limit'       => 50, // 必填
            'delivery_id' => $this->__channelObj->channel['logistics_code'],
            'status'      => 3, // 1 绑定审核中;2 取消绑定审核中;3 已绑定;4 已解除绑定;5 绑定未通过;6 取消绑定未通过
        ];

        $title = '查询开通的网点账号信息';

        $result = $this->__caller->call(STORE_WAYBILL_ADRESS, $params, array(), $title, 6, $this->__channelObj->channel['logistics_code']);

        if ($result['rsp'] == 'succ' && $result['data']) {

            $data           = json_decode($result['data'], 1);
            $result['data'] = $data;
            // $this->_getWISCallback($result['data']);

        } else {
            $result['msg'] = $result['err_msg'];
        }
        $result['request_logistics_code'] = $this->__channelObj->channel['logistics_code'];
        $result['channel_type']           = $this->__channelObj->channel['channel_type'];

        $_tmp = [];
        foreach ($result['data']['account_list'] as $k => $v) {
            $_tmp[] = [
                'available'      => $v['available'],
                'status'         => $v['status'],
                'delivery_id'    => $v['delivery_id'],
                'acct_id'        => $v['acct_id'],
                'recycled'       => $v['recycled'],
                'shop_id'        => $v['shop_id'],
                'cancel'         => $v['cancel'],
                'allocated'      => $v['allocated'],
                'site_name'      => $v['site_info']['site_name'],
                'site_status'    => $v['site_info']['site_status'],
                'site_code'      => $v['site_info']['site_code'],
                'site_fullname'  => $v['site_info']['site_fullname'],
                'mobile'         => $v['site_info']['contact']['mobile'],
                'phone'          => $v['site_info']['contact']['phone'],
                'name'           => $v['site_info']['contact']['name'],
                'province_code'  => $v['site_info']['address']['province_code'],
                'street_name'    => $v['site_info']['address']['street_name'],
                'street_code'    => $v['site_info']['address']['street_code'],
                'city_name'      => $v['site_info']['address']['city_name'],
                'country_code'   => $v['site_info']['address']['country_code'],
                'district_code'  => $v['site_info']['address']['district_code'],
                'detail_address' => $v['site_info']['address']['detail_address'],
                'district_name'  => $v['site_info']['address']['district_name'],
                'city_code'      => $v['site_info']['address']['city_code'],
                'province_name'  => $v['site_info']['address']['province_name'],
            ];
        }
        $result['data']['account_list'] = $_tmp;

        return $result;
        // return array('rsp' => $result['rsp'], 'msg' => $result['rsp'] == 'succ' ? '获取成功' : '获取失败');
    }

    /**
     * 获取EwaybillOrderIdFromLogNo
     * @param mixed $logi_no logi_no
     * @return mixed 返回结果
     */
    public function getEwaybillOrderIdFromLogNo($logi_no)
    {
        $waybillModel       = app::get('logisticsmanager')->model('waybill');
        $waybillExtendModel = app::get('logisticsmanager')->model('waybill_extend');

        $waybillInfo = $waybillModel->db_dump([
            'waybill_number' => $logi_no,
            'channel_id'     => $this->__channelObj->channel['channel_id'],
        ]);

        $extendInfo = $waybillExtendModel->db_dump(['waybill_id' => $waybillInfo['id']]);

        $extendInfo['print_config'] && $extendInfo['print_config'] = json_decode($extendInfo['print_config'], 1);
        return $extendInfo['print_config']['ewaybill_order_id'] ?: '';
    }

    /**
     * @author mxc 2023-10-09 16:38
     * @describe 电子面单打单成功通知
     */
    public function delivery($sdf)
    {
        $delivery = $sdf['delivery'];

        $this->title     = '微信视频号电子面单打单成功通知';
        $this->primaryBn = $delivery['logi_no'];

        $logData = array(
            'logi_no'      => $delivery['logi_no'],
            'delivery_id'  => $delivery['delivery_id'],
            'channel_type' => 'wxshipin',
        );

        $ewaybill_order_id = $this->getEwaybillOrderIdFromLogNo($delivery['logi_no']);
        $params            = [
            'ewaybill_order_id' => $ewaybill_order_id, // 电子面单订单id，全局唯一id
            'delivery_id'       => $sdf['dly_corp']['type'], // 快递公司id
            'waybill_id'        => $delivery['logi_no'], // 快递单号
            're_print'          => '0', // 1 补打单成功 0 首次打单成功
        ];
        $isAsync = false;
        $gateway = '';
        $res     = $this->deliveryCall(STORE_ETMS_WAYBILL_SEND, $logData, $params, $gateway, $isAsync);

        return $res;
    }

    // private function _getWISCallback($data)
    // {
    //     if (!$data) {
    //         return;
    //     }

    //     $extendObj = app::get('logisticsmanager')->model('channel_extend');

    //     // 取有面单号的
    //     $process_params = array();
    //     foreach ($data['netsites'] as $info) {
    //         if ($info['amount'] < $process_params['allocated_quantity']) {
    //             continue;
    //         }
    //         $process_params = array(
    //             'cancel_quantity'    => $info['cancelled_quantity'] > 0 ? $info['cancelled_quantity'] : 0,
    //             'allocated_quantity' => $info['amount'],
    //             'province'           => $info['sender_address'][0]['province_name'],
    //             'city'               => $info['sender_address'][0]['city_name'],
    //             'area'               => $info['sender_address'][0]['district_name'],
    //             'street'             => $info['sender_address'][0]['street_name'],
    //             'address_detail'     => $info['sender_address'][0]['detail_address'],
    //             'channel_id'         => $this->__channelObj->channel['channel_id'],
    //             'print_quantity'     => $info['allocated_quantity'] - $info['recycled_quantity'],
    //         );
    //         if ($process_params['print_quantity'] < 0) {
    //             $process_params['print_quantity'] = 0;
    //         }
    //     }

    //     if (!$process_params) {
    //         return;
    //     }

    //     $extend = $extendObj->dump(array('channel_id' => $this->__channelObj->channel['channel_id']), 'id');
    //     if ($extend) {
    //         $process_params['id'] = $extend['id'];
    //     }

    //     $extendObj->save($process_params);
    // }

}
