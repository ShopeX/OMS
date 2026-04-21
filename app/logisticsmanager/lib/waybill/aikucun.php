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
class logisticsmanager_waybill_aikucun {

    public function service_code($param)
    {
        $cpCode  = $param['logistics'];
        $service = array(
            'zhongtong'      => array(
            ),
            'jtexpress'      => array(
            ),
            'shunfeng'       => array(
                'customerCode' => array(
                    'text'       => '月结卡号',
                    'code'       => 'customerCode',
                    'input_type' => 'input',
                    'require'    => 'true',
                ),
                'productType'  => array(
                    'text'       => '产品类型',
                    'code'       => 'productType',
                    'input_type' => 'select',
                    'options'    => array(
                        ''    => '',
                        '1'   => '顺丰特快',
                        '2'   => '顺丰标快',
                        '6'   => '顺丰即日',
                        '10'  => '国际小包',
                        '23'  => '顺丰国际特惠(文件)',
                        '24'  => '顺丰国际特惠(包裹)',
                        '26'  => '国际大件',
                        '29'  => '国际电商专递-标准',
                        '30'  => '三号便利箱(特快)',
                        '31'  => '便利封/袋(特快)',
                        '32'  => '二号便利箱(特快)',
                        '33'  => '岛内件(80CM)',
                        '35'  => '物资配送',
                        '39'  => '岛内件(110CM)',
                        '40'  => '岛内件(140CM)',
                        '41'  => '岛内件(170CM)',
                        '42'  => '岛内件(210CM)',
                        '43'  => '台湾岛内件-批(80CM)',
                        '44'  => '台湾岛内件-批(110CM)',
                        '45'  => '台湾岛内件-批(140CM)',
                        '46'  => '台湾岛内件-批(170CM)',
                        '47'  => '台湾岛内件-批(210CM)',
                        '48'  => '台湾岛内件店取(80CM)',
                        '49'  => '台湾岛内件店取(110CM)',
                        '50'  => '千点取60',
                        '51'  => '千点取80',
                        '52'  => '千点取100',
                        '53'  => '电商盒子F1',
                        '54'  => '电商盒子F2',
                        '55'  => '电商盒子F3',
                        '56'  => '电商盒子F4',
                        '57'  => '电商盒子F5',
                        '58'  => '电商盒子F6',
                        '59'  => 'E顺递',
                        '60'  => '顺丰特快（文件）',
                        '61'  => 'C1类包裹',
                        '62'  => 'C2类包裹',
                        '63'  => 'C3类包裹',
                        '64'  => 'C4类包裹',
                        '65'  => 'C5类包裹',
                        '66'  => '特快D类',
                        '73'  => 'F5超值箱',
                        '99'  => '顺丰国际标快(文件)',
                        '100' => '顺丰国际标快(包裹)',
                        '104' => '岛内件(80CM,1kg以内)',
                        '106' => '国际重货-门到门',
                        '111' => '顺丰干配',
                        '113' => '便利封/袋(标快)',
                        '114' => '二号便利箱(标快)',
                        '115' => '三号便利箱(标快)',
                        '116' => '国际标快-BD2',
                        '117' => '国际标快-BD3',
                        '118' => '国际标快-BD4',
                        '119' => '国际标快-BD5',
                        '120' => '国际标快-BD6',
                        '121' => '国际标快-BDE',
                        '126' => '掌柜-大格',
                        '127' => '掌柜-中格',
                        '128' => '掌柜-小格',
                        '129' => '掌柜-柜到柜(单程)',
                        '130' => '掌柜-柜到柜(双程)',
                        '132' => '顺丰国际特惠(FBA)',
                        '136' => '国际集运',
                        '144' => '当日配-门(80CM/1KG以内)',
                        '145' => '当日配-门(80CM)',
                        '146' => '当日配-门(110CM)',
                        '147' => '当日配-门(140CM)',
                        '148' => '当日配-门(170CM)',
                        '149' => '当日配-门(210CM)',
                        '150' => '标快D类',
                        '153' => '整车直达',
                        '160' => '国际重货-港到港',
                        '178' => '一号便利箱(特快)',
                        '179' => '一号便利箱(标快)',
                        '180' => '岛內件-专车普运',
                        '184' => '顺丰国际标快+（文件）',
                        '186' => '顺丰国际标快+（包裹）',
                        '201' => '冷运标快',
                        '202' => '顺丰微小件',
                        '207' => '限时寄递',
                        '215' => '大票直送',
                        '218' => '国际电商专递-CD',
                        '221' => '香港冷运到家(≤60厘米)',
                        '222' => '香港冷运到家(61-80厘米)',
                        '223' => '香港冷运到家(81-100厘米)',
                        '224' => '香港冷运到家(101-120厘米)',
                        '225' => '香港冷运到家(121-150厘米)',
                        '231' => '陆运包裹',
                        '235' => '预售当天达',
                        '236' => '电商退货',
                        '241' => '国际电商专递-快速',
                        '244' => '店到店',
                        '245' => '店到门',
                        '246' => '门到店',
                        '247' => '电商标快',
                        '249' => '丰礼遇',
                        '252' => '前置小时达',
                        '253' => '前置当天达',
                        '255' => '顺丰卡航',
                        '256' => '顺丰卡航（D类）',
                        '257' => '医药温控配送',
                        '258' => '退换自寄',
                        '259' => '极速配',
                        '261' => 'O2O店配',
                        '262' => '前置标快',
                        '263' => '同城半日达',
                        '265' => '预售电标',
                        '266' => '顺丰空配（新）',
                        '267' => '行李送递-上门',
                        '268' => '行李送递',
                        '269' => '酒类配送',
                        '270' => '行李托运-上门',
                        '271' => '行李托运',
                        '272' => '行李送递-上门 (九龙)',
                        '273' => '温控配送自取',
                        '274' => '温控配送上门',
                        '275' => '酒类温控自取',
                        '276' => '酒类温控上门',
                        '277' => '跨境FBA空运',
                        '278' => '跨境FBA海运',
                        '283' => '填舱标快',
                        '285' => '填舱电标',
                        '288' => '冷运大件到港',
                        '289' => '跨城急件',
                        '293' => '特快包裹（新）',
                        '297' => '样本安心递',
                        '299' => '标快零担',
                        '303' => '专享急件',
                        '308' => '国际特快（文件）',
                        '310' => '国际特快（包裹）',
                        '316' => '前置次日达',
                        '318' => '航空港到港',
                        '323' => '电商微小件',
                        '325' => '温控包裹',
                        '329' => '填舱大件',
                    ),
                ),
            ),
            'yunda'          => array(
            ),
            'yuantong'       => array(
            ),
            'shentong'       => array(
            ),
            'youzhengguonei' => array(
                'customerCode' => array(
                    'text'       => '月结卡号',
                    'code'       => 'customerCode',
                    'input_type' => 'input',
                    'require'    => 'true',
                ),
            ),
            'ems'            => array(
                'customerCode' => array(
                    'text'       => '月结卡号',
                    'code'       => 'customerCode',
                    'input_type' => 'input',
                    'require'    => 'true',
                ),
            ),
            'yzdsbk'         => array(
                'customerCode' => array(
                    'text'       => '月结卡号',
                    'code'       => 'customerCode',
                    'input_type' => 'input',
                    'require'    => 'true',
                ),
            ),
            'jd'             => array(
                'customerCode' => array(
                    'text'       => '月结卡号',
                    'code'       => 'customerCode',
                    'input_type' => 'input',
                    'require'    => 'true',
                ),
                'productType'  => array(
                    'text'       => '产品类型',
                    'code'       => 'productType',
                    'input_type' => 'select',
                    'options'    => array(
                        ''            => '',
                        'ed-m-0001'   => '京东标快',
                        'ed-m-0002'   => '京东特快',
                        'LL-HD-M'     => '生鲜标快',
                        'LL-SD-M'     => '生鲜特快',
                        'ed-m-0017'   => '函速达',
                        'ed-m-0012'   => '特惠包裹',
                        'ed-m-0059'   => '电商标快',
                        'ed-m-0019'   => '电商特惠',
                        'ed-m-0005'   => '同城急送',
                        'fr-m-0004'   => '特快重货',
                        'fr-m-0007'   => '航空重货',
                        'fr-m-0017'   => '特惠专配',
                        'fr-m-0018'   => '爱宝速递',
                        'ed-m-00000'  => '智能分单',
                    ),
                ),
            ),
            'debangwuliu'    => array(
                'customerCode' => array(
                    'text'       => '月结卡号',
                    'code'       => 'customerCode',
                    'input_type' => 'input',
                    'require'    => 'true',
                ),
                'productType'  => array(
                    'text'       => '产品类型',
                    'code'       => 'productType',
                    'input_type' => 'select',
                    'options'    => array(
                        ''       => '',
                        'RCP'    => '大件快递3.60',
                        'PACKAGE'=> '标准快递',
                        'TZKJC'  => '特快专递',
                        'HKDJG'  => '航空大件隔日达',
                        'HKDJC'  => '航空大件次日达',
                        'NZBRH'  => '重包入户',
                        'NFLF'   => '精准卡航(新)',
                        'NJZZH'  => '精准重货(新)',
                        'NLRF'   => '精准汽运(新)',
                    ),
                ),
            ),
        );
        return isset($service[$cpCode]) ? $service[$cpCode] : [];
    }

    public function template_cfg()
    {
        $arr = array(
            'template_name' => '爱库存',
            'shop_name'     => '爱库存',
            'print_url'     => 'https://mx-lews-web.aikucun.com/merchant/xiazai',
            'shop_type'     => 'aikucun',
            'control_type'  => 'aikucun',
            'request_again' => true,
        );
        return $arr;
    }

    public function logistics($logistics_code = '') {
        $logistics = array(
            'zhongtong'      => array('code' => 'zhongtong',      'name' => '中通速递',     'mode' => 'join'),
            'jtexpress'      => array('code' => 'jtexpress',      'name' => '极兔速递',     'mode' => 'join'),
            'shunfeng'       => array('code' => 'shunfeng',       'name' => '顺丰速运',     'mode' => 'direct'),
            'yunda'          => array('code' => 'yunda',          'name' => '韵达快递',     'mode' => 'join'),
            'yuantong'       => array('code' => 'yuantong',       'name' => '圆通快递',     'mode' => 'join'),
            'shentong'       => array('code' => 'shentong',       'name' => '申通速递',     'mode' => 'join'),
            'youzhengguonei' => array('code' => 'youzhengguonei', 'name' => '中国邮政',     'mode' => 'direct'),
            'ems'            => array('code' => 'ems',            'name' => 'EMS',          'mode' => 'direct'),
            'yzdsbk'         => array('code' => 'yzdsbk',        'name' => '邮政电商标快', 'mode' => 'direct'),
            'jd'             => array('code' => 'jd',            'name' => '京东快递',     'mode' => 'direct'),
            'debangwuliu'    => array('code' => 'debangwuliu',    'name' => '德邦快递',     'mode' => 'direct'),
        );

        if (!empty($logistics_code)) {
            return $logistics[$logistics_code];
        }

        return $logistics;
    }

}
