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

class erpapi_logistics_matrix_pdd_request_electron extends erpapi_logistics_request_electron
{
    protected $directNum = 1;

    /**
     * bufferRequest
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function bufferRequest($sdf){
        return $this->directNum;
    }

    /**
     * directRequest
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function directRequest($sdf) {
        // 模板地址
        $corp = app::get('ome')->model('dly_corp')->getList('prt_tmpl_id, protect, protect_rate, minprice', array('corp_id'=>$sdf['delivery'][0]['logi_id']), 0, 1);

        if($corp['channel_id'] != $this->__channelObj->channel['channel_id']) {
            $prtTmpl = app::get('ome')->model('dly_corp_channel')->db_dump(
                array('channel_id'=>$this->__channelObj->channel['channel_id'], 'corp_id'=>$sdf['delivery'][0]['logi_id']), 'prt_tmpl_id');
            if($prtTmpl) {
                $corp[0]['prt_tmpl_id'] = $prtTmpl['prt_tmpl_id'];
            }
        }

        $templateObj = app::get("logisticsmanager")->model('express_template');
        $printTpl = $templateObj->getList('*', array('template_id'=>$corp[0]['prt_tmpl_id']), 0, 1);
        $template = $printTpl[0];
        $templateUrl = $this->_getTemplateUrl($template);

        $this->title     = '拼多多-' . $this->__channelObj->channel['logistics_code'] . '获取电子面单';
        $this->timeOut   = 20;
        $this->primaryBn = $sdf['primary_bn'];

        // 发货地址
        $seller = array(
            'city'     => $sdf['shop']['city'],
            'detail'   => $sdf['shop']['address_detail'],
            'district' => $sdf['shop']['area'],
            'province' => $sdf['shop']['province'],
            'street'   => $sdf['shop']['street'],
            'mobile' => $sdf['shop']['mobile'],
            'name'   => $sdf['shop']['default_sender'],
            'phone'  => $sdf['shop']['tel'],
        );

        $arrDelivery = $sdf['delivery'];
        $deliveryBnKey = array();
        $TradeOrderInfoDto = array();

        $is_encrypt = false; $jst = array ('order_bns' => array ());
        foreach($arrDelivery as $delivery) {
            //分销一件代发订单
            if($delivery['cos_id'] > 0){
                //替换使用平台订单号
                $getPlatformOrder = $this->_formatPlatformOrderBn($delivery);
                
                //order_bns
                if($getPlatformOrder['order_bns']){
                    $delivery['order_bns'] = $getPlatformOrder['order_bns'];
                }
                
                //order_bn
                if($getPlatformOrder['order_bn']){
                    $delivery['order_bn'] = $getPlatformOrder['order_bn'];
                }
            }
            
            // 是否加密
            if (!$is_encrypt) {
                $is_encrypt = kernel::single('ome_security_router',$delivery['shop_type'])->is_encrypt($delivery,'delivery');

                // 如果是拼多多订单，则需要密文取号
                if ($is_encrypt && $delivery['shop_type'] == 'pinduoduo') {
                    $encryptData = [
                        'shop_id'       => $delivery['shop']['shop_id'],
                        'order_bn'      => reset($delivery['order_bns']),
                        'ship_name'     => $delivery['ship_name'],
                        'ship_tel'      => $delivery['ship_tel'],
                        'ship_mobile'   => $delivery['ship_mobile'],
                        'ship_addr'     => $delivery['ship_addr'],
                    ];
                    $originalEncrypt = kernel::single('ome_security_pinduoduo')->get_encrypt_origin($encryptData, 'delivery');
                    foreach ($originalEncrypt as $dk => $dv) {
                        $delivery[$dk] = $dv;
                        $is_encrypt = false;
                    }
                }
            }
            $jst['order_bns'] = array_merge($jst['order_bns'], $delivery['order_bns']);

            $deliveryBnKey[$delivery['delivery_bn']] = $delivery;

            $arrItem = array();
            foreach($delivery['package_items'] as $pVal) {
                $arrItem[] = array(
                    'count' => $pVal['count'],
                    'name'  => $this->charFilter($pVal['item_name'])
                );
            }
            $tmp = array(
                'object_id'           => $delivery['delivery_bn'],
                'order_platform' => $delivery['order_channels_type'] ? $delivery['order_channels_type'] : 'OTHER',
                'tid_list'    => empty($delivery['order_bn'])
                    ? array($delivery['delivery_bn'])
                    : array_values(array_unique($delivery['order_bn'])),
                'package_id'                   => $delivery['delivery_bn'],
                'items'                => $arrItem,
                'package_volume'               => 0,
                'package_weight'               => (int)$delivery['net_weight'],
                'package_total_count' => intval($delivery['logi_number']),
                'recipient' => array(
                    'city'     => $delivery['ship_city'],
                    'detail'   => $delivery['ship_addr'],
                    'district' => $delivery['ship_district']?$delivery['ship_district']:'区',
                    'province' => $delivery['ship_province'],
                    'mobile' => $delivery['ship_mobile'],
                    'name'   => $delivery['ship_name'],
                    'phone'  => $delivery['ship_tel']
                ),
                'template_url' => $templateUrl,

                // 新增解密字段
                'order_bns'    => implode(',', $delivery['order_bns']),

            );

            // 四级地区(街道)
            if ($delivery['ship_town']) {
                $tmp['recipient']['street'] = $delivery['ship_town'];
            }

            $TradeOrderInfoDto[] = $tmp;
        }

        $params = array(
            'sender'             => json_encode($seller),
            'trade_order_info_list' => json_encode($TradeOrderInfoDto),
        );

        // 加密请求虎符
        $gateway = '';
        if ($is_encrypt) {
            $params['s_node_id']    = $delivery['shop']['node_id'];
            $params['s_node_type']  = $delivery['shop_type'];

            $gateway = $delivery['shop_type'];
        }

        $result = $this->requestCall(STORE_WAYBILL_PRINT, $params,  array(), $jst, $gateway);

        $returnResult = $this->backToResult($result, $deliveryBnKey);

        return $returnResult;
    }

    private function _getTemplateUrl($template) {
        if (!strpos($template['template_name'], '自定义')) {
            $url = explode(':', $template['template_data'], 2);
        } else {
            $filter = array(
                'template_type' => 'pdd_standard',
                'cp_code'       => $this->__channelObj->channel['logistics_code'],
            );
            $templateObj = app::get("logisticsmanager")->model('express_template');
            $printTpl    = $templateObj->getList('*', $filter, 0, 1);
            if ($printTpl) {
                $url = explode(':', $printTpl[0]['template_data'], 2);
            } else {
                $url[1] = 'url:http://pinduoduoimg.yangkeduo.com/print_template/2019-03-18/8d8176570314b2b7484195634f654f7c.xml';
            }
        }
        return $url[1];
    }

    private function backToResult($ret, $deliveryBnKey){
        $waybill = empty($ret['data']) ? array() : json_decode($ret['data'], true);
        if(empty($waybill)) {
            return $ret['msg'] ? $ret['msg'] : false;
        }
        $result = array();
        foreach ($waybill as $val) {
            $deliveryBn = trim($val['object_id']);
            $delivery = $deliveryBnKey[$deliveryBn];
            $printData = json_decode($val['print_data'], true);
            $position = $printData['routingInfo']['bigShotName'];
            $position_no = $printData['routingInfo']['threeSegmentCode'];
            $packageWdjc = $printData['routingInfo']['endBranchName'];
            $packageWd = $printData['routingInfo']['endBranchCode'];

            $result[] = array(
                'succ' => $val['waybill_code'] ? true : false,
                'msg' => '',
                'delivery_id' => $delivery['delivery_id'],
                'delivery_bn' => $delivery['delivery_bn'],
                'logi_no' => $val['waybill_code'],
                'mailno_barcode' => '',
                'qrcode' => '',
                'position' => trim($position),
                'position_no' => $position_no,
                'package_wdjc' => (string) $packageWdjc,
                'package_wd' => (string) $packageWd,
                'print_config' => '',
                'json_packet' => $val['print_data'],
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
    public function recycleWaybill($waybillNumber,$delivery_bn = '') {
        app::get('logisticsmanager')->model('waybill')->update(array('status'=>2,'create_time'=>time()),array('waybill_number'=>$waybillNumber));
        $this->title = '拼多多_' . $this->__channelObj->channel['logistics_code'] . '取消电子面单';
        $this->primaryBn = $waybillNumber;
        $params = array(
            'waybill_code' => $waybillNumber
        );
        $callback = array(
            'class' => get_class($this),
            'method' => 'callback'
        );
        $this->requestCall(STORE_WAYBILL_CANCEL, $params, $callback);
    }

    public function getWaybillISearch($sdf = array())
    {
        $params = array();

        $title = '拼多多订购地址获取';

        $result = $this->__caller->call(STORE_HQEPAY_ORDERSERVICE,$params,array(),$title, 6, $params['cp_code']);

        if ($result['rsp'] == 'succ' && $result['data']) {
            $this->_getWISCallback($result['data']);
        }

        return array('rsp'=>$result['rsp'],'msg'=>$result['rsp']=='succ'?'获取成功':'获取失败');
    }

    private function _getWISCallback($data)
    {
        $data = json_decode($data,true);

        if (!$data) return ;

        $extendObj = app::get('logisticsmanager')->model('channel_extend');

        // 取有面单号的
        $process_params = array();
        foreach ($data[0]['branch_account_cols'] as $info) {
            if($info['quantity'] < $process_params['allocated_quantity']) {
                continue;
            }
            $process_params = array(
                'cancel_quantity'    => $info['cancel_quantity'],
                'allocated_quantity' => $info['quantity'],
                'province'           => $info['shipp_address_cols'][0]['province'],
                'city'               => $info['shipp_address_cols'][0]['city'],
                'area'               => $info['shipp_address_cols'][0]['district'],
                'address_detail'     => $info['shipp_address_cols'][0]['detail'],
                'channel_id'         => $this->__channelObj->channel['channel_id'],
                'print_quantity'     => $info['allocated_quantity'],
            );
        }

        if (!$process_params) return ;

        $extend = $extendObj->dump(array('channel_id'=>$this->__channelObj->channel['channel_id']),'id');
        if ($extend) $process_params['id'] = $extend['id'];

        $extendObj->save($process_params);
    }
}
