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

class logisticsmanager_waybill_taobao {
    /**
     * 云栈订单来源列表
     * @var Array $channelsTypeList
     */
    public static $channelsTypeList = array(
        'C' => 'TB',//淘宝
        'B' => 'TM',//天猫
        'OTHER' => 'OTHERS',//其它

        '360BUY'       => 'JD', // 京东
        'PAIPAI'       => 'PP', // 拍拍
        'DANGDANG'     => 'DD', // 当当
        'AMAZON'       => 'AMAZON', // 亚马逊
        'QQ_BUY'       => 'QQ', // QQ
        'SUNING'       => 'SN', // 苏宁
        'SUNING4ZY'    => 'SN', // 苏宁
        'GOME'         => 'GM', // 国美
        'VOP'          => 'WPH', // 唯品会
        'MOGUJIE'      => 'MGJ', // 蘑菇街
        'MGJ'          => 'MGJ', // 蘑菇街
        'YINTAI'       => 'YT', // 银泰
        'YIHAODIAN'    => 'YHD', // 1号店
        'VJIA'         => 'VANCL', // 凡客
        'ALIBABA'      => '1688', // 1688
        'ALIBABA4ASCP' => '1688', // 1688
        'YOUZAN'       => 'YOU_ZAN', // 有赞
        'PINDUODUO'    => 'PIN_DUO_DUO', // 拼多多
        'ZHE800'       => 'ZHE_800', // 折800
        'JUANPI'       => 'JUAN_PI', // 卷皮
        'BEIBEI'       => 'BEI_BEI', // 贝贝
        'WEIDIAN'      => 'WEI_DIAN', // 微店
        'MEILISHUO'    => 'MEI_LI_SHUO', // 美丽说
        'MENGDIAN'     => 'MENG_DIAN', // 萌店
        'WEIMOB'       => 'WEI_MENG', // 微盟
        'WEIMOBV'      => 'WEI_MENG', // 微盟
        'WEIMOBR'      => 'WEI_MENG', // 微盟
        'KAOLA'        => 'KAO_LA', // 考拉
        'KAOLA4ZY'     => 'KAO_LA', // 考拉
        'MIA'          => 'MI_YA', // 蜜芽
        'YUNJI'        => 'YUN_JI', // 云集
        'LUBAN'        => 'DOU_YIN', // 抖音
        'KUAISHOU'     => 'KUAI_SHOU', // 快手
        'XHS'          => 'XIAO_HONG_SHU', // 小红书
        'HUAWEI'       => 'HUAWEI', // 华为
        'DEWU'         => 'DU', // 毒
        // ''             => 'YX', // 易讯
        // ''             => 'EBAY', // EBAY
        // ''             => 'JM', // 聚美
        // ''             => 'LF', // 乐蜂
        // ''             => 'JS', // 聚尚
        // ''             => 'PX', // 拍鞋
        // ''             => 'YL', // 邮乐
        // ''             => 'YG', // 优购
        // ''             => 'CHU_CHU_JIE', // 楚楚街
        // ''             => 'QIAN_MI', // 千米
        // ''             => 'FAN_LI', // 返利
        // ''             => 'YAN_XUAN', // 网易严选
        // ''             => 'WEI_SHANG', // 微商
        // ''             => 'XIAO_MI', // 小米
        // ''             => 'BEI_DIAN', // 贝店
        // ''             => 'XIAN_YU', // 闲鱼
        // ''             => 'WAN_WU_DE_ZHI', // 玩物得志
        // ''             => 'YANG_MA_TOU', // 洋码头
        // ''             => 'WO_MAI', // 我买
        // ''             => 'JIU_XIAN_WANG', // 酒仙网
        // ''             => 'BEN_LAI_SHENG_HUO', // 本来生活
    );
    /**
     * 默认订单来源类型
     * @var String 默认来源
     */
    public static $defaultChannelsType = 'OTHER';
    
    public static $businessType = array(
        'EMS' => 1,
        'EYB' => 2,
        'SF' => 3,
        'ZJS' => 4,
        'ZTO' => 5,
        'HTKY' => 6,
        'UC' => 7,
        'YTO' => 8,
        'STO' => 9,
        'TTKDEX' => 10,
        'DBKD'=>11,
        '100004928'=>12,
        'CN7000001003751'=>13,
        '2608021499_235'=>14,
        '2460304407_385'=>15,
        '2383545689_32'=>16,
    );

    /**
     * 获取物流公司编码
     * @param Sring $logistics_code 物流代码
     */
    public function logistics($logistics_code = '') {
        $logistics = array(
            'EMS'             => array('code' => 'EMS', 'name' => '普通EMS'),
            'EYB'             => array('code' => 'EYB', 'name' => '邮政电商标快'),
            'SF'              => array('code' => 'SF', 'name' => '顺丰'),
            'ZJS'             => array('code' => 'ZJS', 'name' => '宅急送'),
            'ZTO'             => array('code' => 'ZTO', 'name' => '中通'),
            'HTKY'            => array('code' => 'HTKY', 'name' => '极兔速递'),
            'UC'              => array('code' => 'UC', 'name' => '优速'),
            'YTO'             => array('code' => 'YTO', 'name' => '圆通'),
            'STO'             => array('code' => 'STO', 'name' => '申通'),
            'TTKDEX'          => array('code' => 'TTKDEX', 'name' => '天天'),
            'QFKD'            => array('code' => 'QFKD', 'name' => '全峰'),
            'FAST'            => array('code' => 'FAST', 'name' => '快捷'),
            'POSTB'           => array('code' => 'POSTB', 'name' => '邮政小包'),
            '5000000007756'   => array('code' => '5000000007756', 'name' => '邮政国内标快'),
            '100005492'       => array('code' => '100005492', 'name' => '日日顺'),
            'SNWL'            => array('code' => 'SNWL', 'name' => '苏宁快递'),
            'CP570969'        => array('code' => 'CP570969', 'name' => '丹鸟'),
            'CP468398'        => array('code' => 'CP468398', 'name' => '圆通承诺达'),
            '100007887'       => array('code' => '100007887', 'name' => '山东递速'),
            'XFWL'            => array('code' => 'XFWL', 'name' => '信丰物流'),
            'CP443514'        => array('code' => 'CP443514', 'name' => '百世云配 '),
            'GTO'             => array('code' => 'GTO', 'name' => '国通'),
            'YUNDA'           => array('code' => 'YUNDA', 'name' => '韵达'),
            'DBKD'            => array('code' => 'DBKD', 'name' => '德邦快递'),
            '100004928'       => array('code' => '100004928', 'name' => '如风达'),
            '2608021499_235'  => array('code' => '2608021499_235', 'name' => '安能快递'),
            'CN7000001003751' => array('code' => 'CN7000001003751', 'name' => '跨越'),
            '2460304407_385'  => array('code' => '2460304407_385', 'name' => '远成快运'),
            '2383545689_32'   => array('code' => '2383545689_32', 'name' => '九曳供应链'),
            'CP449455'        => array('code' => 'CP449455', 'name' => '京广速递'),
            'CN7000001009020' => array('code' => 'CN7000001009020', 'name' => '德邦快运', 'parent_waybill' => true),
            'BESTQJT'         => array('code' => 'BESTQJT', 'name' => '百世快运', 'parent_waybill' => true),
            'CN7000001000869' => array('code' => 'CN7000001000869', 'name' => '安能快运', 'parent_waybill' => true),
            '2744832184_543'  => array('code' => '2744832184_543', 'name' => '壹米滴答', 'parent_waybill' => true),
            'CN7000001021040' => array('code' => 'CN7000001021040', 'name' => '韵达快运', 'parent_waybill' => true),
            '3108002701_1011' => array('code' => '3108002701_1011', 'name' => '中通快运', 'parent_waybill' => true),
            'SURE'            => array('code' => 'SURE', 'name' => '速尔快运', 'parent_waybill' => true),
            'CP446169'        => array('code' => 'CP446169', 'name' => '加运美', 'parent_waybill' => true),
            'HOAU'            => array('code' => 'HOAU', 'name' => '天地华宇', 'parent_waybill' => true),
            'CN7000001017817' => array('code' => 'CN7000001017817', 'name' => '申通快运', 'parent_waybill' => true),
            'FEDEX'           => array('code' => 'FEDEX', 'name' => '联邦'),
            'LE10576340'      => array('code' => 'LE10576340', 'name' => '菜鸟裹裹商家寄件'),
            'CP471906'        => array('code' => 'CP471906', 'name' => '顺心捷达', 'parent_waybill' => true),
            'LE10032270'      => array('code' => 'LE10032270', 'name' => '韵达同城'),
            'CP446881'        => array('code' => 'CP446881', 'name' => '平安达腾飞'),
            'LE09252050'      => array('code' => 'LE09252050', 'name' => '丰网'),
            'CN7000001028572' => array('code' => 'CN7000001028572', 'name' => '速腾快递','parent_waybill' => true),
            'LE14066700'      => array('code' => 'LE14066700', 'name' => '中通冷链','parent_waybill' => true),
            'LE04284890'      => array('code' => 'LE04284890', 'name' => '京东快递'),
            'LE38288910'      => array('code' => 'LE38288910', 'name' => '京东快运','parent_waybill' => true),
        );

        if (!empty($logistics_code)) {
            return $logistics[$logistics_code];
        }
        return $logistics;
    }

    /**
     * 获取订单来源类型
     * @param String $type 类型
     * @param String $node_type 节点类型
     */
    public static function get_order_channels_type($type = '', $node_type) {
        $type = strtoupper($type);
        $channelsType = self::$channelsTypeList[self::$defaultChannelsType];
        if ($node_type == 'taobao') {
          if (in_array($type, array_keys(self::$channelsTypeList))) {
              $channelsType = self::$channelsTypeList[$type];
          }
        } else {
          $node_type = strtoupper($node_type);
          if (in_array($node_type, array_keys(self::$channelsTypeList))) {
              $channelsType = self::$channelsTypeList[$node_type];
          }
        }
        return $channelsType;
    }

    public static function getBusinessType($type) {
        $type = strtoupper($type);
        return self::$businessType[$type];
    }

    public function service_code($param) {
        $sdf = array('cp_code'=>$param['logistics']);
        $rs = kernel::single('erpapi_router_request')->set('shop', $param['shop_id'])->logistics_getCorpServiceCode($sdf);
        if($rs['rsp'] == 'fail' || empty($rs['data'])) {
            return array();
        }
        $data = json_decode($rs['data'], 1);
        $service = array();
        if($data['waybill_apply_subscription_info']) {
//            $objWaybillType = $this->_getObject($sdf['cp_code']);
//            $service = $objWaybillType->getServiceCode($data['waybill_product_type']);
            $obj = kernel::single('logisticsmanager_waybill_taobao_common');
            $service = $obj->getServiceCode($data);
        }
        if($param['logistics'] == 'SF' && !$service['PAYMENT-TYPE']) {
            $service['SF-PAY-METHOD'] = array(
                'text'       => '付款方式',
                'code'       => 'SF-PAY-METHOD',
                'options'    => [''=>'','1'=>'寄方付','2'=>'收方付','3'=>'第三方付'],
                'input_type' => 'select',
            );
        }
        return $service;
    }

    public function getServiceCodeValue($cpCode, $serviceCode) {
        if(empty($serviceCode)) {
            return array();
        }
//        $objWaybillType = $this->_getObject($cpCode);
//        return $objWaybillType->getServiceCodeValue($serviceCode);
        $obj = kernel::single('logisticsmanager_waybill_taobao_common');
        return $obj->getServiceCodeValue($serviceCode);
    }

//    protected function _getObject($cpCode) {
//        $obj = kernel::single('logisticsmanager_waybill_taobao_common');
//        $className = 'logisticsmanager_waybill_taobao_' . strtolower($cpCode);
//        try{
//            if(class_exists($className)) {
//                $obj = kernel::single($className);
//            }
//        }catch (Exception $e) {}
//
//        return $obj;
//    }
//    
    public function template_cfg() {
        $arr = array(
            'template_name' => '菜鸟',
            'shop_name' => '淘宝',
            'print_url' => 'https://page.cainiao.com/waybill/cloud_printing/home.html',
            'template_url' => 'https://cloudprint.cainiao.com',
            'shop_type' => 'taobao',
            'control_type' => 'cainiao',
            'template_type'=>array('cainiao','cainiao_standard','cainiao_user'),
        );
        return $arr;
    }

    function  get_ExpType($type){
       $logistics = array( 
           'SF'=>array(
                1 => '顺丰特快',
                2 => '顺丰标快',
                6 => '顺丰即日',
                10 => '国际小包',
                23 => '顺丰国际特惠(文件)',
                24 => '顺丰国际特惠(包裹)',
                26 => '国际大件',
                29 => '国际电商专递-标准',
                30 => '三号便利箱(特快)',
                31 => '便利封/袋(特快)',
                32 => '二号便利箱(特快)',
                33 => '岛内件(80CM)',
                35 => '物资配送',
                39 => '岛内件(110CM)',
                40 => '岛内件(140CM)',
                41 => '岛内件(170CM)',
                42 => '岛内件(210CM)',
                43 => '台湾岛内件-批(80CM)',
                44 => '台湾岛内件-批(110CM)',
                45 => '台湾岛内件-批(140CM)',
                46 => '台湾岛内件-批(170CM)',
                47 => '台湾岛内件-批(210CM)',
                48 => '台湾岛内件店取(80CM)',
                49 => '台湾岛内件店取(110CM)',
                50 => '千点取60',
                51 => '千点取80',
                52 => '千点取100',
                53 => '电商盒子F1',
                54 => '电商盒子F2',
                55 => '电商盒子F3',
                56 => '电商盒子F4',
                57 => '电商盒子F5',
                58 => '电商盒子F6',
                59 => 'E顺递',
                60 => '顺丰特快（文件）',
                61 => 'C1类包裹',
                62 => 'C2类包裹',
                63 => 'C3类包裹',
                64 => 'C4类包裹',
                65 => 'C5类包裹',
                66 => '特快D类',
                73 => 'F5超值箱',
                99 => '顺丰国际标快(文件)',
                100 => '顺丰国际标快(包裹)',
                104 => '岛内件(80CM,1kg以内)',
                106 => '国际重货-门到门',
                111 => '顺丰干配',
                112 => '顺丰空配',
                113 => '便利封/袋(标快)',
                114 => '二号便利箱(标快)',
                115 => '三号便利箱(标快)',
                116 => '国际标快-BD2',
                117 => '国际标快-BD3',
                118 => '国际标快-BD4',
                119 => '国际标快-BD5',
                120 => '国际标快-BD6',
                121 => '国际标快-BDE',
                126 => '掌柜-大格',
                127 => '掌柜-中格',
                128 => '掌柜-小格',
                129 => '掌柜-柜到柜(单程)',
                130 => '掌柜-柜到柜(双程)',
                132 => '顺丰国际特惠(FBA)',
                136 => '集货转运',
                144 => '当日配-门(80CM/1KG以内)',
                145 => '当日配-门(80CM)',
                146 => '当日配-门(110CM)',
                147 => '当日配-门(140CM)',
                148 => '当日配-门(170CM)',
                149 => '当日配-门(210CM)',
                150 => '标快D类',
                153 => '整车直达',
                160 => '国际重货-港到港',
                178 => '一号便利箱(特快)',
                179 => '一号便利箱(标快)',
                180 => '岛內件-专车普运',
                184 => '顺丰国际标快+（文件）',
                186 => '顺丰国际标快+（包裹）',
                199 => '特快包裹',
                201 => '冷运标快',
                202 => '顺丰微小件',
                207 => '限时寄递',
                209 => '高铁专送',
                215 => '大票直送',
                218 => '国际电商专递-CD',
                221 => '香港冷运到家(≤60厘米)',
                222 => '香港冷运到家(61-80厘米)',
                223 => '香港冷运到家(81-100厘米)',
                224 => '香港冷运到家(101-120厘米)',
                225 => '香港冷运到家(121-150厘米)',
                229 => '精温专递',
                231 => '陆运包裹',
                235 => '预售当天达',
                236 => '电商退货',
                238 => '纯重特配',
                241 => '国际电商专递-快速',
                242 => '丰网速运',
                244 => '店到店',
                245 => '店到门',
                246 => '门到店',
                247 => '电商标快',
                248 => '自贸区特配',
                249 => '丰礼遇',
                250 => '极置店配',
                251 => '极置店配（专线）',
                252 => '前置小时达',
                253 => '前置当天达',
                255 => '顺丰卡航',
                256 => '顺丰卡航（D类）',
                257 => '医药温控配送',
                258 => '退换自寄',
                259 => '极速配',
                260 => '入仓电标',
                261 => 'O2O店配',
                262 => '前置标快',
                263 => '同城半日达',
                264 => '同城次日达',
                265 => '预售电标',
                266 => '顺丰空配（新）',
                267 => '行李送递-上门',
                268 => '行李送递',
                269 => '酒类配送',
                270 => '行李托运-上门',
                271 => '行李托运',
                272 => '行李送递-上门 (九龙)',
                273 => '温控配送自取',
                274 => '温控配送上门',
                275 => '酒类温控自取',
                276 => '酒类温控上门',
                277 => '跨境FBA空运',
                278 => '跨境FBA海运',
                283 => '填舱标快',
                285 => '填舱电标',
                287 => '冷运大件标快',
                288 => '冷运大件到港',
                289 => '跨城急件',
                293 => '特快包裹（新）',
                297 => '样本安心递',
                299 => '标快零担',
           ),
           'LE04284890' => array(
                'ed-m-0001' => '京东标快',
                'ed-m-0002' => '京东特快',
                'LL-HD-M_usual' => '生鲜标快常温',
                'LL-HD-M_alive' => '生鲜标快鲜活',
                'LL-HD-M_control' => '生鲜标快控温',
                'LL-HD-M_cold' => '生鲜标快冷藏',
                'LL-HD-M_freezing' => '生鲜标快冷冻',
                'LL-HD-M_deepCool' => '生鲜标快深冷',
                'LL-SD-M_usual' => '生鲜特快常温',
                'LL-SD-M_alive' => '生鲜特快鲜活',
                'LL-SD-M_control' => '生鲜特快控温',
                'LL-SD-M_cold' => '生鲜特快冷藏',
                'LL-SD-M_freezing' => '生鲜特快冷冻',
                'LL-SD-M_deepCool' => '生鲜特快深冷',
                'll-m-0015_usual' => '冷链专送常温',
                'll-m-0015_alive' => '冷链专送鲜活',
                'll-m-0015_control' => '冷链专送控温',
                'll-m-0015_cold' => '冷链专送冷藏',
                'll-m-0015_freezing' => '冷链专送冷冻',
                'll-m-0015_deepCool' => '冷链专送深冷',
                'ed-m-0059' => '电商特惠',  
                'ed-m-0012' => '特惠包裹',
                'ed-m-0019' => '京东特惠',
                'ed-m-0020' => '特快包裹',
            ),
            'LE38288910' => array(
                    'fr-m-0001' => '特快零担',
                    'fr-m-0004' => '特快重货',
                    'fr-m-0006' => '快运零担',
                    'lq-m-0025' => '大件宅配',
                    'lq-m-0011' => '大件零担',
                    'lq-m-0005' => '大件商务仓',
            ),
       );
       if($logistics){
          return $logistics[$type];
       }else{
          return '';
       }
    }
}
