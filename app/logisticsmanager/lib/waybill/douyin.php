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

class logisticsmanager_waybill_douyin
{
     /**
      * 打印组件三个接口
      * 获取打印数据 /logistics/waybillApply
      * 获取标准模板 /logistics/templateList
      * 获取自定义模板 /logistics/customTemplateList
      * @return array [description]
      */
     public function template_cfg() {
        $arr = array(
            'template_name' => '抖音',
            'shop_name' => '抖音',
            'print_url' => 'https://logistics.douyinec.com/davinci/CloudPrintClient',
            'template_url' => 'https://cloudprint.douyinec.com/login',
            'shop_type' => 'luban',
            'control_type' => 'douyin',
            'request_again' => true
        );
        return $arr;
    }
    /**
     * 获取物流公司编码
     * @param Sring $logistics_code 物流代码
     * @return array
     */
    public function logistics($logistics_code = '') {
        $logistics = array(
            'jtexpress'   => array('code'=>'jtexpress','name'=>'极兔快递'),
            'shunfeng'    => array('code'=>'shunfeng','name'=>'顺丰速递'),
            'zhongtong'   => array('code'=>'zhongtong','name'=>'中通速递'),
            'yunda'       => array('code'=>'yunda','name'=>'韵达快递'),
            'yuantong'    => array('code'=>'yuantong','name'=>'圆通快递'),
            'youzhengguonei' => array('code'=>'youzhengguonei','name'=>'邮政快递包裹'),
            'ems'         => array('code'=>'ems','name'=>'EMS'),
            'huitongkuaidi' => array('code'=>'huitongkuaidi','name'=>'百世快递'),
            'jd'          => array('code'=>'jd','name'=>'京东快递'),
            'shentong'    => array('code'=>'shentong','name'=>'申通快递'),
            'zhongyouex'  => array('code'=>'zhongyouex','name'=>'众邮快递'),
            'debangwuliu' => array('code'=>'debangwuliu', 'name'=>'德邦快递'),
            'fengwang' => array('code'=>'fengwang', 'name'=>'丰网'),
            'shunfengkuaiyun' => array('code'=>'shunfengkuaiyun', 'name'=>'顺丰快运'),
            'baishiwuliu' => array('code'=>'baishiwuliu', 'name'=>'百世快运'),
            'danniao' => array('code'=>'danniao', 'name'=>'丹鸟'),
            'sxjdfreight' => array('code'=>'sxjdfreight', 'name'=>'顺心捷达'),
            'zhongtongkuaiyun' => array('code'=>'zhongtongkuaiyun', 'name'=>'中通快运'),
            'zhaijisong' => array('code'=>'zhaijisong', 'name'=>'宅急送'),
            'annengwuliu' => array('code'=>'annengwuliu', 'name'=>'安能物流'),
            'youshuwuliu'          => ['code' => 'youshuwuliu', 'name' => '优速物流'],
            'jiuyescm'             => ['code' => 'jiuyescm', 'name' => '九曳供应链'],
            'suning'               => ['code' => 'suning', 'name' => '苏宁物流'],
            'dsukuaidi'            => ['code' => 'dsukuaidi', 'name' => 'D速物流'],
            'xlair'                => ['code' => 'xlair', 'name' => '快弟来了'],
            'NZSY'                 => ['code' => 'NZSY', 'name' => '哪吒速运'],
            'ztocc'                => ['code' => 'ztocc', 'name' => '中通冷链'],
            'kuayue'               => ['code' => 'kuayue', 'name' => '跨越速运'],
            'zhongtongguoji'       => ['code' => 'zhongtongguoji', 'name' => '中通国际'],
            'yundakuaiyun'         => ['code' => 'yundakuaiyun', 'name' => '韵达快运'],
            'yimidida'             => ['code' => 'yimidida', 'name' => '壹米滴答'],
            'jingdongdajian'       => ['code' => 'jingdongdajian', 'name' => '京东大件'],
            'jingdongkuaiyun'      => ['code' => 'jingdongkuaiyun', 'name' => '京东快运'],
            'debangkuaiyun'        => ['code' => 'debangkuaiyun', 'name' => '德邦快运'],
            'yilongex'             => ['code' => 'yilongex', 'name' => '亿隆速运'],
            'annto'                => ['code' => 'annto', 'name' => '安得物流'],
            'savor'                => ['code' => 'savor', 'name' => '海信物流'],
            'yzdsbk'               => ['code' => 'yzdsbk', 'name' => '邮政电商标快'],
            'jinguangsudikuaijian' => ['code' => 'jinguangsudikuaijian', 'name' => '京广速递'],
            'pingandatengfei'      => ['code' => 'pingandatengfei', 'name' => '平安达腾飞快递'],
            'lntjs'                => ['code' => 'lntjs', 'name' => '特急送'],
        );

        if (!empty($logistics_code)) {
            return $logistics[$logistics_code];
        }
        return $logistics;
    }

    /**
     * service_code
     * @param mixed $param param
     * @return mixed 返回值
     */
    public function service_code($param)
    {
        $cpCode  = $param['logistics'];
        $service = array(
            'jtexpress'   => array(
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                )
            ),
            'shunfeng'    => array(
                'PRODUCT-TYPE' => array(
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => array(
                        '' => '',
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
                        // '112' => '顺丰空配',
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
                        // '154' => '重货包裹',
                        // '155' => '标准零担',
                        '160' => '国际重货-港到港',
                        // '174' => '重货包裹B',
                        '178' => '一号便利箱(特快)',
                        '179' => '一号便利箱(标快)',
                        '180' => '岛內件-专车普运',
                        '184' => '顺丰国际标快+（文件）',
                        '186' => '顺丰国际标快+（包裹）',
                        // '199' => '特快包裹',
                        // '200' => '冷运速配',
                        // '201' => '冷运特惠',
                        '201' => '冷运标快',
                        // '202' => '航空微小件',
                        '202' => '顺丰微小件',
                        // '204' => '陆运微小件',
                        '207' => '限时寄递',
                        // '208' => '特惠专配',
                        // '209' => '高铁专送',
                        '215' => '大票直送',
                        '218' => '国际电商专递-CD',
                        '221' => '香港冷运到家(≤60厘米)',
                        '222' => '香港冷运到家(61-80厘米)',
                        '223' => '香港冷运到家(81-100厘米)',
                        '224' => '香港冷运到家(101-120厘米)',
                        '225' => '香港冷运到家(121-150厘米)',
                        // '229' => '精温专递',
                        '231' => '陆运包裹',
                        // '233' => '精温专递（样本陆）',
                        // '235' => '极效前置-预售',
                        '235' => '预售当天达',
                        '236' => '电商退货',
                        // '238' => '纯重特配',
                        '241' => '国际电商专递-快速',
                        // '242' => '丰网速运',
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
                        // '287' => '冷运大件标快',
                        '288' => '冷运大件到港',
                        '289' => '跨城急件',
                        '293' => '特快包裹（新）',
                        '297' => '样本安心递',
                        '299' => '标快零担',
                        '303' => '专享急件',
                        // '304' => '特早达',
                        // '306' => '专享急件（海外）',
                        '308' => '国际特快（文件）',
                        '310' => '国际特快（包裹）',
                        '316' => '前置次日达',
                        '318' => '航空港到港',
                        '323' => '电商微小件',
                        '325' => '温控包裹',
                        '329' => '填舱大件',
                    )
                ),
                'COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'COD',
                    'input_type' => 'checkbox',
                ),
                'INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'INSURE',
                    'input_type' => 'checkbox',
                )
            ),
            'zhongtong'   => array(
                'SVC-WBHOMEDELIVERY' => [
                    'text'          => '音尊达服务',
                    'code'          => 'SVC-WBHOMEDELIVERY',
                    'input_type'    => 'checkbox',
                ]
            ),
            'yunda'       => array(
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ),
                'SVC-WBHOMEDELIVERY' => [
                    'text'          => '音尊达服务',
                    'code'          => 'SVC-WBHOMEDELIVERY',
                    'input_type'    => 'checkbox',
                ]
            ),
            'yuantong'    => array(
                'SVC-WBHOMEDELIVERY' => [
                    'text'          => '音尊达服务',
                    'code'          => 'SVC-WBHOMEDELIVERY',
                    'input_type'    => 'checkbox',
                ]
            ),
            'youzhengguonei' => array(
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                )
            ),
            'ems'         => array(
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                )
            ),
            'huitongkuaidi' => array(
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ),
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                )
            ),
            'jd'          => array(
                'PRODUCT-TYPE' => array(
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => array(
                        '' => '',
                        'ed-m-0001'=>'特惠送',
                        'ed-m-0002'=>'特快送',
                        'LL-HD-M'=>'生鲜特惠',
                        'LL-SD-M'=>'生鲜特快',
                        'ed-m-0012' => '特惠包裹',
                        'ed-m-0019' => '特惠小件',
                        'ed-m-0017' => '函速达',
                    )
                ),
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ),
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ),
                'SVC-TEM'=>array(
                    'text'       => '温层',
                    'code'       => 'SVC-TEM',
                    'input_type' => 'select',
                    'options' => array(
                        '' => '',
                        '1' => '普通',
                        '2' => '生鲜常温',
                        '5' => '鲜活',
                        '6' => '控温',
                        '7' => '冷藏',
                        '8' => '冷冻',
                        '9' => '深冷',
                    )
                ),
            ),
            'shentong'    => array(
                'PRODUCT-TYPE' => array(
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => array(
                        '' => '',
                        'FRESH_EXPRESS_TYPE'=>'生鲜件',
                    )
                ),
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ),
            ),
            'zhongyouex'  => array(
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ),
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ),
            ),
            'shunfengkuaiyun' => array (
                'PRODUCT-TYPE' => array(
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => array(
                        '' => '',
                        'SE0100' => '重货包裹',
                        'SE0101' => '标准零担',
                        'SE0114' => '大票直送',
                        'SE0020' => '整车直达',
                        'S1' => '顺丰特快',
                        'S2' => '顺丰标快',
                        'SE0122' => '特惠专配',
                        'SE0091' => '专线普运',
                        'SE0130' => '特惠件',
                        'SE010101' => '纯重特配',
                    )
                ),
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ),
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ),
                // 'SVC-PKFEE' => array(

                // ),
            ),
            'debangwuliu' => array (
                'PRODUCT-TYPE' => array(
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => array(
                        'PACKAGE' => '标准快递',
                        'DEAP' => '特准快件',
                        'TZKJC' => '特快专递',
                        'RCP' => '大件快递360',
                        'NZBRH' => '重包入户',
                        'ZBTH' => '重包特惠',
                        'WXJTH' => '微小件特惠',
                    )
                ),
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ),
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ),
            ),
            'annengwuliu' => array (
                'PRODUCT-TYPE' => array(
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => array(
                        '' => '',
                        '546' => '安心达',
                        '23' => '定时达',
                        '270' => '普惠达',
                        '524' => 'MiNi电商小件',
                        '95' => 'MiNi电商大件',
                        '24' => '标准快运',
                    )
                ),
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ),
            ),
            'zhongtongkuaiyun' => array (
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ),
            ),
            'sxjdfreight' => array (
                'SVC-RECEIVE-TYPE'=>array(
                    'text'       => '接货方式',
                    'code'       => 'SVC-RECEIVE-TYPE',
                    'input_type' => 'select',
                    'options' => array(
                        'SEND' => '上门接货',
                        'SELF' => '客户自送',
                    )
                ),
                'SVC-DELIVERY-TYPE' => array(
                    'text'       => '送货方式',
                    'code'       => 'SVC-DELIVERY-TYPE',
                    'input_type' => 'select',
                    'options' => array(
                        'SEND_NO_UPSTAIRS' => '送货（不含上楼）',
                        'SEND_HAS_ELEVATOR' => '送货上楼（有电梯）',
                        'SEND_NO_ELEVATOR' => '送货上楼（无电梯）',
                        'SELF' => '自提',
                    )
                ),
                'SVC-INSURE'=>array(
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ),
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ),
            ),
            'youshuwuliu' => [
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
            ],
            'suning' => [
                'PRODUCT-TYPE' => [
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => [
                        '01'                  => '大件配送',
                        'dstributeAndInstall' => '送装一体',
                    ]
                ],
            ],
            'dsukuaidi' => [
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
                'SVC-COD'=>array(
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ),
            ],
            'xlair' => [
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
            ],
            'NZSY' => [
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
                'PRODUCT-TYPE' => [
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => [
                        '默认'          => '标准快递',
                        'FRESH_EXPRESS' => '生鲜件',
                    ]
                ],
            ],
            'ztocc' => [
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
                'SVC-TEM' => [
                    'text'       => '温层',
                    'code'       => 'SVC-TEM',
                    'input_type' => 'select',
                    'options' => [
                        '7' => '冷藏',
                        '8' => '冷冻',
                    ],
                ],
                'SVC-DELIVERY-TYPE' => [
                    'text'       => '送货方式',
                    'code'       => 'SVC-DELIVERY-TYPE',
                    'input_type' => 'select',
                    'options' => [
                        'SELF' => '自提',
                        'SEND' => '派送',
                    ],
                ],
                'SVC-SIGN-TYPE' => [
                    'text'       => '签单方式',
                    'code'       => 'SVC-SIGN-TYPE',
                    'input_type' => 'select',
                    'options' => [
                        'PAPER_PAPER'           => '纸质/纸质',
                        'PAPER_ELECTRONIC'      => '纸质/电子',
                        'ELECTRONIC_PAPER'      => '电子/纸质',
                        'ELECTRONIC_ELECTRONIC' => '电子/电子',
                    ],
                ],
                'PRODUCT-TYPE' => [
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => [
                        'SINGLE'    => '单件',
                        'LCL'       => '零担',
                    ]
                ],
            ],
            'kuayue' => [
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
                'SVC-COD' => [
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ],
                // // 因为service_value是多值的类型，暂不支持
                // 'SVC-SIGN-TYPE' => [
                //     'text'       => '签回单',
                //     'code'       => 'SVC-SIGN-TYPE',
                //     'input_type' => 'textlist',
                //     'options' => [
                //         'value'     => '1/2', // 1:回单原件 2:回单照片
                //         'value1'     => '份数',
                //     ],
                // ],
                'SVC-PKFEE' => [
                    'text' => '包装方式',
                    'code' => 'SVC-PKFEE',
                    'input_type' => 'select',
                    'options' => [
                        '1'     => '打卡板',
                        '2'     => '打木架',
                        '3'     => '打木箱',
                    ]
                ],
                'PRODUCT-TYPE' => [
                    'text' => '产品类型',
                    'code' => 'PRODUCT-TYPE',
                    'input_type' => 'select',
                    'options' => [
                        '10'    =>  '当日达',
                        '20'    =>  '次日达',
                        '30'    =>  '隔日达',
                        '40'    =>  '陆运件',
                        '210'   =>  '空运',
                        '220'   =>  '专运',
                        '160'   =>  '省内日次',
                        '170'   =>  '省内即日',
                        '50'    =>  '同城次日',
                        '70'    =>  '同城即日',
                    ]
                ],
            ],
            'yundakuaiyun' => [
                'SVC-DELIVERY-TYPE' => [
                    'text'       => '送货方式',
                    'code'       => 'SVC-DELIVERY-TYPE',
                    'input_type' => 'select',
                    'options' => [
                        '1'     => '派送(默认)',
                        '2'     => '送货上楼',
                        '3'     => '自提',
                    ],
                ],
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
                'SVC-BUSINESS' => [
                    'text'       => '业务类型',
                    'code'       => 'SVC-BUSINESS',
                    'input_type' => 'select',
                    'options' => [
                        '0'     =>  '无(默认)',
                        '1'     =>  '韵准达',
                        '2'     =>  '粤准达',
                        '3'     =>  '168大件',
                        '4'     =>  '京津冀电商',
                    ],
                ],
                'SVC-ITEM' => [
                    'text'       => '货物类型',
                    'code'       => 'SVC-ITEM',
                    'input_type' => 'select',
                    'options'    => [
                        '1'     =>  '正常货物',
                        '2'     =>  '易损品',
                        '3'     =>  '药品类',
                    ],
                ],
                'SVC-SIGN-TYPE' => [
                    'text'       => '签回单',
                    'code'       => 'SVC-SIGN-TYPE',
                    'input_type' => 'select',
                    'options' => [
                        '0'     => '否(不打印)',
                        '1'     => '是',
                    ],
                ],
                'PRODUCT-TYPE' => [
                    'text'          => '产品类型',
                    'code'          => 'PRODUCT-TYPE',
                    'input_type'    => 'select',
                    'options'       => [
                        '1'    =>  '标准快运(默认)',
                        '2'    =>  '电商件',
                    ]
                ],
            ],
            'jingdongdajian' => [
                'SVC-COD' => [
                    'text'       => '代收货款',
                    'code'       => 'SVC-COD',
                    'input_type' => 'checkbox',
                ],
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
            ],
            'jingdongkuaiyun' => [
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
                'SVC-PKFEE' => [
                    'text'          => '包装方式',
                    'code'          => 'SVC-PKFEE',
                    'input_type'    => 'checkbox',
                ],
                'SVC-DELIVERY-TYPE' => [
                    'text'       => '上楼',
                    'code'       => 'SVC-DELIVERY-TYPE',
                    'input_type' => 'checkbox',
                ],
                // // 因为service_value是多值的类型，暂不支持
                // 'SVC-fr-a-0008' => [
                //     'text'          =>  '进仓',
                //     'code'          =>  'SVC-fr-a-0008',
                //     'input_type'    =>  'textlist',
                //     'options'       => [
                //         'value'     => '进仓预约号',
                //         'value1'    => '进仓备注',
                //         'value2'    => '进仓时间',
                //     ],
                // ],
                // // 因为service_value是多值的类型，暂不支持
                // 'SVC-SIGN-TYPE' => [
                //     'text'       => '签单',
                //     'code'       => 'SVC-SIGN-TYPE',
                //     'input_type' => 'select',
                //     'options' => [
                //         'value1'     => '纸质签单',
                //         'value2'     => '电子签单',
                //         'value3'     => '纸质签单+电子签单',
                //     ],
                // ],
                'PRODUCT-TYPE' => [
                    'text'          => '产品类型',
                    'code'          => 'PRODUCT-TYPE',
                    'input_type'    => 'select',
                    'options'       => [
                        'fr-m-0004'    =>  '特快重货',
                        'fr-m-0002'    =>  '特惠重货',
                        'fr-m-0001'    =>  '特快零担',
                    ]
                ],
            ],
            'debangkuaiyun' => [
                'SVC-INSURE' => [
                    'text'       => '保价服务',
                    'code'       => 'SVC-INSURE',
                    'input_type' => 'checkbox',
                ],
                'PRODUCT-TYPE' => [
                    'text'          => '产品类型',
                    'code'          => 'PRODUCT-TYPE',
                    'input_type'    => 'select',
                    'options'       => [
                        'JZKH'      =>  '快车',
                        'JZQY_LONG' =>  '慢车',
                        'TZKJC'     =>  '空运',
                    ]
                ],
            ],
            'yzdsbk' => [
                'PRODUCT-TYPE' => [
                    'text'          => '产品类型',
                    'code'          => 'PRODUCT-TYPE',
                    'input_type'    => 'select',
                    'options'       => [
                        '11510' =>  '电商标快',
                    ]
                ],
            ],
            'jinguangsudikuaijian' => [
                'SVC-SIGN-TYPE' => [
                    'text'       => '签单',
                    'code'       => 'SVC-SIGN-TYPE',
                    'input_type' => 'text',
                    'value'      => '回单号(KH+10位纯数字)',
                ],
            ],
        );

        return isset($service[$cpCode]) ? $service[$cpCode] : array();
    }
}
