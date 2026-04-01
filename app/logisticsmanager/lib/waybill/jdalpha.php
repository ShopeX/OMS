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

class logisticsmanager_waybill_jdalpha {
     public function template_cfg() {
        $arr = array(
            'template_name' => '京东',
            'shop_name' => '京东',
            'print_url' => 'http://prod-oms-app-cprt.jdwl.com/OpenCloudPrint/setup.exe',
            'template_url' => 'https://template-design.jd.com/?o=1#/home/templates',
            'shop_type' => ['360buy','jd'],
            'control_type' => 'jd',
            'request_again' => true
        );
        return $arr;
    }
    /**
     * 获取物流公司编码
     * @param Sring $logistics_code 物流代码
     */
    public function logistics($logistics_code = '') {
        #'mode'=>'direct'直营，'mode'=>'join'加盟
        $logistics = array(
            'ZTO'        => array('code' => 'ZTO', 'name' => '中通速递','jdalpha_code'=>'ZTO','mode'=>'join'),
            'YUNDA'      => array('code' => 'YUNDA', 'name' => '韵达快递','jdalpha_code'=>'YUNDA','mode'=>'join'),
            'STO'        => array('code' => 'STO', 'name' => '申通快递','jdalpha_code'=>'STO','mode'=>'join'),
            'UC'         => array('code' => 'UC', 'name'=>'优速快递','jdalpha_code'=>'UC','mode'=>'join'),
            'QFKD'       => array('code' => 'QFKD', 'name'=>'全峰快递','jdalpha_code'=>'QFKD','mode'=>'join'),
            'GTO'        => array('code' => 'GTO', 'name' => '国通快递','jdalpha_code'=>'GTO','mode'=>'join'),
            'UAPEX'      =>  array('code' => 'UAPEX', 'name'=>'全一快递','jdalpha_code'=>'QY','mode'=>'join'),            #京东alpha获取面单时，要换为：QY
            'SURE'       => array('code'=>'SURE','name'=>'速尔快递','jdalpha_code'=>'SE','mode'=>'join'),                 #京东alpha获取面单时，要换为：SE
            'FAST'       => array('code' => 'FAST', 'name'=>'快捷快递','jdalpha_code'=>'KJKD','mode'=>'join'),            #京东alpha获取面单时，要换为：KJKD
            'EMS'        => array('code'=>'EMS','name'=>'邮政EMS经济快递','jdalpha_code'=>'EMS','mode'=>'direct'),
            'EMSBZ'      => array('code'=>'EMSBZ','name'=>'邮政EMS标准快递','jdalpha_code'=>'EMSBZ','mode'=>'direct'),
            'ZJS'        => array('code' => 'ZJS', 'name'=>'宅急送','jdalpha_code'=>'ZJS','mode'=>'direct'),
            'POSTB'      => array('code' => 'POSTB', 'name' => '邮政国内小包','jdalpha_code'=>'POSTB','mode'=>'direct'),   #京东alpha获取面单时，要换为：ZGYZZHD
            'ky-express' => array('code' => 'ky-express', 'name' => '跨越速运','jdalpha_code'=>'KYSD','mode'=>'direct'), #京东alpha获取面单时，要换为：KYSD
            'SF'         => array('code'=>'SF','name'=>'顺丰快递','jdalpha_code'=>'SF','mode'=>'direct'),
            'ANWL'       => array('code' => 'ANWL', 'name' => '安能物流','jdalpha_code'=>'ANWL','mode'=>'join'),
            'YTO'        => array('code' => 'YTO','name'=>'圆通快递','jdalpha_code'=>'YTO','mode'=>'join'),
            'DBKD'       => array('code' => 'DBKD','name'=>'德邦快递','jdalpha_code'=>'DBKD','mode'=>'direct'),
            'ZYKD'       => array('code' => 'ZYKD','name'=>'众邮快递','jdalpha_code'=>'ZYKD','mode'=>'join'),
            'FENGWANG' => array('code'=>'FENGWANG', 'name'=>'丰网速运', 'jdalpha_code'=>'FENGWANG', 'mode'=>'join'),
            'SXJD' => array('code'=>'SXJD', 'name'=>'顺心捷达', 'jdalpha_code'=>'SXJD', 'mode'=>'join'),
            'JTSD' => array('code'=>'JTSD', 'name'=>'极兔速递', 'jdalpha_code'=>'JTSD', 'mode'=>'join'),
            'DSBK'     => array('code'=>'DSBK', 'name'=>'邮政电商标快', 'jdalpha_code'=>'DSBK', 'mode'=>'direct'),
            'JDDJ' => array('code'=>'JDDJ', 'name'=>'京东大件纯配', 'jdalpha_code'=>'JDDJ', 'mode'=>'self'),
            'DDKY' => array('code' => 'DDKY','name'=>'德邦快运','jdalpha_code'=>'DDKY','mode'=>'direct'),
        );
        if (!empty($logistics_code)) {
            return $logistics[$logistics_code];
        }
        return $logistics;
    }
    function  get_ExpType($type){
        $logistics = array(
            'SF'=>array(
                '1'   => '顺丰次日',
                '2'   => '顺丰隔日',
                '14'  => '冷运到家',
                '15'  => '生鲜速配',
                '28'  => '电商专配',
                '111' => '顺丰干配',
                '112' => '顺丰空配',
                '13'  => '物流普运',
                '200' => '冷运速配',
                '201' => '冷运特惠',
                '202' => '航空微小件',
                '204' => '陆运微小件',
                '18'  => '重货快运',
                '208' => '特惠专配',
                '16'  => '大闸蟹专递',
                '154' => '重货包裹',
                '231' => '陆运包裹',
                '242' => '丰网速运',
                '234' => '商务标快',
                '247' => '电商标快',
                '238' => '纯重特配',
                '283' => '填舱标快',
            ),
        );
        if($logistics){
            return $logistics[$type];
        }else{
            return '';
        }
    }
    public function pay_method($method = '') {
        $payMethod = array(
            '1' => array('code' => '1', 'name' => '寄方付'),
            '2' => array('code' => '2', 'name' => '收方付'),
            '3' => array('code' => '3', 'name' => '第三方支付'),
        );
        if (!empty($method)) {
            return $payMethod[$method];
        }
        return $payMethod;
    }

    public function service_code($param) {
        $cpCode  = $param['logistics'];
        $service = array(
            'JDDJ' => [
                'onDoorPickUp' => array(
                    'text'       => '是否上门揽件',
                    'code'       => 'onDoorPickUp',
                    'options'    => ['1'=>'是上门揽件', '2'=>'非上门揽件'],
                    'value'      => '1',
                    'input_type' => 'select',
                ),
                // 'isGuarantee' => array(
                //     'text'       => '是否保价',
                //     'code'       => 'isGuarantee',
                //     'options'    => ['1'=>'需要保价', '2'=>'不需保价'],
                //     'value'      => '1',
                //     'input_type' => 'select',
                // ),
                // 'productType' => array(
                //     'text'       => '产品类型',
                //     'code'       => 'productType',
                //     'options'    => ['0'=>'宅配(2C业务)','1'=>'零担','2'=>'整车'],
                //     'value'      => '0',
                //     'input_type' => 'select',
                // ),
                'sameCityDelivery' => array(
                    'text'       => '是否纯配同城',
                    'code'       => 'sameCityDelivery',
                    'value'      => '1',
                    'input_type' => 'checkbox',
                ),
                'lasDischarge' => array(
                    'text'       => '是否卸货到店',
                    'code'       => 'lasDischarge',
                    'value'      => '1',
                    'input_type' => 'checkbox',
                ),
                'thirdPayment' => array(
                    'text'       => '运费结算方式',
                    'code'       => 'thirdPayment',
                    'options'    => ['0'=>'月结','2'=>'到付'],
                    'value'      => '0',
                    'input_type' => 'select',
                ),
                'upstairsFlag' => array(
                    'text'       => '是否重货上楼',
                    'code'       => 'upstairsFlag',
                    'value'      => '1',
                    'input_type' => 'checkbox',
                ),
                'getOldService' => array(
                    'text'       => '是否取旧服务',
                    'code'       => 'getOldService',
                    'value'      => '1',
                    'input_type' => 'checkbox',
                ),
                'openBoxService' => array(
                    'text'       => '开箱服务',
                    'code'       => 'openBoxService',
                    'options'    => ['0'=>'否','1'=>'开箱通电','2'=>'开箱验机','3'=>'禁止开箱'],
                    'value'      => '0',
                    'input_type' => 'select',
                ),
                'deliveryInstallService' => array(
                    'text'       => '是否送装一体服务',
                    'code'       => 'deliveryInstallService',
                    'value'      => '1',
                    'input_type' => 'checkbox',
                ),
            ],
        );
        return isset($service[$cpCode]) ? $service[$cpCode] : [];
    }

}