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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2021/5/12 15:49:16
 * @describe: 小红书
 * ============================
 */
class wms_event_trigger_logistics_data_electron_xhs extends wms_event_trigger_logistics_data_electron_common
{

    /**
     * orderChannelsType
     * @param mixed $shop_type shop_type
     * @return mixed 返回值
     */

    public function orderChannelsType($shop_type = 'others')
    {
        $list = [
            'xhs'         => ['code' => 'XIAO_HONG_SHU', 'name' => '小红书'],
            'xiaohongshu' => ['code' => 'XIAO_HONG_SHU', 'name' => '小红书'],
            'taobao'      => ['code' => 'TB', 'name' => '淘宝'],
            'tmall'       => ['code' => 'TM', 'name' => '天猫'],
            'luban'       => ['code' => 'DOU_YIN', 'name' => '抖音'],
            'kuaishou'    => ['code' => 'KUAI_SHOU', 'name' => '快手'],
            '360buy'      => ['code' => 'JD', 'name' => '京东'],
            'paipai'      => ['code' => 'PP', 'name' => '拍拍'],
            'dangdang'    => ['code' => 'DD', 'name' => '当当'],
            'amazon'      => ['code' => 'AMAZON', 'name' => '亚马逊'],
            'qq_buy'      => ['code' => 'QQ', 'name' => 'QQ'],
            'suning'      => ['code' => 'SN', 'name' => '苏宁'],
            'suning4zy'   => ['code' => 'SN', 'name' => '苏宁'],
            'gome'        => ['code' => 'GM', 'name' => '国美'],
            'vop'         => ['code' => 'WPH', 'name' => '唯品会'],
            'mogujie'     => ['code' => 'MGJ', 'name' => '蘑菇街'],
            'mgj'         => ['code' => 'MGJ', 'name' => '蘑菇街'],
            'yintai'      => ['code' => 'YT', 'name' => '银泰'],
            'yihaodian'   => ['code' => 'YHD', 'name' => '1号店'],
            'vjia'        => ['code' => 'VANCL', 'name' => '凡客'],
            'youzan'      => ['code' => 'YOU_ZAN', 'name' => '有赞'],
            'pinduoduo'   => ['code' => 'PIN_DUO_DUO', 'name' => '拼多多'],
            'zhe800'      => ['code' => 'ZHE_800', 'name' => '折800'],
            'juanpi'      => ['code' => 'JUAN_PI', 'name' => '卷皮'],
            'beibei'      => ['code' => 'BEI_BEI', 'name' => '贝贝'],
            'weidian'     => ['code' => 'WEI_DIAN', 'name' => '微店'],
            'meilishuo'   => ['code' => 'MEI_LI_SHUO', 'name' => '美丽说'],
            'mengdian'    => ['code' => 'MENG_DIAN', 'name' => '萌店'],
            'weimob'      => ['code' => 'WEI_MENG', 'name' => '微盟'],
            'weimobv'     => ['code' => 'WEI_MENG', 'name' => '微盟'],
            'weimobr'     => ['code' => 'WEI_MENG', 'name' => '微盟'],
            'kaola'       => ['code' => 'KAO_LA', 'name' => '考拉'],
            'kaola4zy'    => ['code' => 'KAO_LA', 'name' => '考拉'],
            'mia'         => ['code' => 'MI_YA', 'name' => '蜜芽'],
            'yunji'       => ['code' => 'YUN_JI', 'name' => '云集'],
            'huawei'      => ['code' => 'HUAWEI', 'name' => '华为'],
            'dewu'        => ['code' => 'DU', 'name' => '得物'],
            'xiaomi'      => ['code' => 'YouPin', 'name' => '小米有品'],
            'weixinshop'  => ['code' => 'WXXD', 'name' => '微信小店'],
            'others'      => ['code' => 'OTHERS', 'name' => '其他'],
            // ''    => ['code' => 'YX', 'name' => '易迅'],
            // ''    => ['code' => 'EBAY', 'name' => 'EBAY'],
            // ''    => ['code' => 'JM', 'name' => '聚美'],
            // ''    => ['code' => 'LF', 'name' => '乐蜂'],
            // ''    => ['code' => 'JS', 'name' => '聚尚'],
            // ''    => ['code' => 'PX', 'name' => '拍鞋'],
            // ''    => ['code' => 'YL', 'name' => '邮乐'],
            // ''    => ['code' => 'YG', 'name' => '优购'],
            // ''    => ['code' => '1688', 'name' => '1688'],
            // ''    => ['code' => 'CHU_CHU_JIE', 'name' => '楚楚街'],
            // ''    => ['code' => 'QIAN_MI', 'name' => '千米'],
            // ''    => ['code' => 'FAN_LI', 'name' => '返利'],
            // ''    => ['code' => 'YAN_XUAN', 'name' => '网易严选'],
            // ''    => ['code' => 'WEI_SHANG', 'name' => '微商'],
            // ''    => ['code' => 'XIAO_MI', 'name' => '小米'],
            // ''    => ['code' => 'BEI_DIAN', 'name' => '贝店'],
            // ''    => ['code' => 'XIAN_YU', 'name' => '闲鱼'],
            // ''    => ['code' => 'WAN_WU_DE_ZHI', 'name' => '玩物得志'],
            // ''    => ['code' => 'YANG_MA_TOU', 'name' => '洋码头'],
            // ''    => ['code' => 'WO_MAI', 'name' => '我买'],
            // ''    => ['code' => 'JIU_XIAN_WANG', 'name' => '酒仙网'],
            // ''    => ['code' => 'BEN_LAI_SHENG_HUO', 'name' => '本来生活'],
            // ''    => ['code' => 'MTShanGou', 'name' => '美团闪购'],
            // ''    => ['code' => 'ELE', 'name' => '饿了么'],
            // ''    => ['code' => 'DOU_CANG', 'name' => '抖仓'],
        ];

        !$list[$shop_type] && $shop_type = 'others';

        return $list[$shop_type]['code'];
    }

    /**
     * 获取DirectSdf
     * @param mixed $arrDelivery arrDelivery
     * @param mixed $arrBill arrBill
     * @param mixed $shop shop
     * @return mixed 返回结果
     */
    public function getDirectSdf($arrDelivery, $arrBill, $shop)
    {
        $delivery = $arrDelivery[0];

        if (empty($arrBill)) {
            $this->needRequestId[] = $delivery['delivery_id'];
        } else {
            $this->needRequestId[]   = $arrBill[0]['b_id'];
            $delivery['delivery_bn'] = $this->setChildRqOrdNo($delivery['delivery_bn'], $arrBill[0]['b_id']);
        }

        $deliveryItems = $this->getDeliveryItems($delivery['delivery_id']);

        if (empty($shop)) {
            $shop   = [];
            $branch = app::get('ome')->model('branch')->db_dump($delivery['branch_id']);

            list(, $mainland)             = explode(':', $branch['area']);
            list($province, $city, $area) = explode('/', $mainland);

            $shop['shop_name']      = $branch['name'];
            $shop['province']       = $province;
            $shop['city']           = $city;
            $shop['area']           = $area;
            $shop['street']         = '';
            $shop['address_detail'] = $branch['address'];
            $shop['default_sender'] = $branch['uname'];
            $shop['mobile']         = $branch['mobile'];
            $shop['tel']            = $branch['phone'];
            $shop['zip']            = $branch['zip'];
        }

        $orders = app::get('ome')->model('orders')->getList('total_amount,shop_type,order_bn,custom_mark,mark_text,order_id', array('order_bn|in' => $delivery['order_bns']));

        $orderIdArr  = array_column($orders, 'order_id');
        $orderExtend = app::get('ome')->model('order_extend')->getList('*', ['order_id|in' => $orderIdArr]);
        $orderExtend = array_column($orderExtend, null, 'order_id');

        $total_amount = 0;
        foreach ($orders as $k => $order) {
            $total_amount += $order['total_amount'];
            $shop['shop_type'] = $order['shop_type'];

            if ($orderExtend[$order['order_id']]) {
                $orders[$k]['order_extend'] = [
                    'extend_field' => json_decode($orderExtend[$order['order_id']]['extend_field'], 1),
                ];
            }
        }

        $dlyCorp = app::get('ome')->model('dly_corp')->dump(array('corp_id' => $delivery['logi_id']));
        app::get('ome')->model('dly_corp_channel')->getChannel($dlyCorp, array($delivery));

        $sdf                  = parent::getDirectSdf($arrDelivery, $arrBill, $shop);
        $sdf['primary_bn']    = $delivery['delivery_bn'];
        $sdf['delivery']      = $delivery;
        $sdf['delivery_item'] = $deliveryItems;
        $sdf['shop']          = $shop;
        $sdf['dly_corp']      = $dlyCorp;
        $sdf['total_amount']  = $total_amount;
        $sdf['order']         = $orders;

        return $sdf;
    }

}
