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

class ome_shop_type{

    /**
     * 节点类型
     * @access public
     * @return Array
     */
    static function get_shop_type(){
        $shop_type = array(
            'taobao'            => '淘宝',
            'tmall'             => '天猫',
            'alibaba4ascp'      => '淘工厂',
            '360buy'            => '京东POP',
            'jd'                => '京东厂直',
            'luban'             => '抖音',
            'pinduoduo'         => '拼多多',
            'alibaba'           => '阿里巴巴',
            'meituan4medicine'  => '美团医药',
            'meituan4bulkpurchasing'  => '美团电商',
            'meituan4sg'        => '美团闪购',
            'kuaishou'          => '快手',
            'dewu'              => '得物',
            'xhs'               => '新小红书',
            'wx'                => '微信小店',
            'youzan'            => '有赞',
            'vop'               => '唯品会',
            'weimob'            => '微盟旺铺',
            'weimobv'           => '微盟微商城',
            'weimobr'           => '微盟零售',
            'shopex_b2c'        => '48体系网店',
            'shopex_b2b'        => '分销王',
            'ecos.b2c'          => 'ECSTORE',
            'ecshop_b2c'        => 'ECSHOP',
            'amazon'            => '亚马逊',
            'suning'            => '苏宁',
            'suning4zy'         => '苏宁自营',
            'icbc'              => '工行',
            'bbc'               => 'BBC商城',
            'mgj'               => '新蘑菇街',
            'shopex_fy'         => '全民分销',
            'shopex_penkrwd'    => '朋客',
            'ecos.b2b2c.stdsrc' => '新BBC商城',
            'renrendian'        => '人人店',
            'haoshiqi'          => '好食期',
            'weidian'           => '微店',
            'yunji'             => '云集',
            'shunguang'         => '顺逛',
            'congminggou'       => '聪明购',
            'gegejia'           => '格家网络',
            'xiaodian'          => '小店',
            'aikucun'           => '爱库存',
            'yutang'            => '鱼塘',
            'mingrong'          => '名融',
            'xiaomi'            => '小米有品',
            'yangsc'            => '洋葱',
            'ecos.ecshopx'      => '源源客',
            'yunji4fx'          => '云集分销',
            'yunji4pop'         => '云集POP',
            'huawei'            => '华为商城',
            'weixinshop'        => '微信小商店',
            // 'website'        => '官网V1',
            'website_d1m'       => '第一秒小程序',
            'wxshipin'          => '微信视频号',
            'website_v2'        => '官网',
            'b2b'              => 'B2B',
        );
        return $shop_type;
    }

    /**
     * 获取节点类型名称
     * @param string $shop_type 店铺类型
     * @return 店铺类型名称
     */
    static function shop_name($shop_type=''){
        $types = self::get_shop_type();
        return $types[$shop_type];
    }

    /**
     * C2C前端店铺列表
     * @return array
     */
    static function shop_list(){
        $shop = array(
                'taobao','paipai','youa','360buy','jd','yihaodian','qq_buy','dangdang',
                'amazon','vjia','alibaba','suning','mogujie',
                'wx','meilishuo','feiniu','youzan','juanpi','mia','beibei',
                'vop','mengdian','weimob','mgj',
                'pinduoduo','renrendian','haoshiqi','weidian','eyee','yunji',
                'xiaohongshu','shunguang','congminggou','gegejia','xiaodian',
                'aikucun','yutang','suning4zy','mingrong',
                'luban','xiaomi','yangsc','kuaishou','weimobr',
                'dewu','yunji4fx','alibaba4ascp','xhs','meituan4medicine','yunji4pop','huawei','weixinshop','website','wxshipin','website_d1m','website','meituan4sg',
                'weimobv','meituan4bulkpurchasing','website_v2'
        );
        
        return $shop;
    }

    static function shop_refund_list() {
        $shop = array('congminggou','pinduoduo','luban','xhs','meituan4medicine', 'website','ecos.ecshopx','website_v2','b2b');
        return $shop;
    }
    /**
     * B2B前端店铺列表
     * @return array
     */
    static function b2b_shop_list(){
        $shop = array('shopex_b2b');
        return $shop;
    }
    /**
     * 库存回写是否增加本店铺冻结库存
     * @access public
     * @return Array
     */
    static function get_store_config(){
        $store_config = array (
            'shopex_b2b' => 'off',
            'shopex_b2c' => 'off',
            'ecos.b2c' => 'off',
            'ecos.dzg' => 'off',
            'ecshop_b2c' => 'off',
            'taobao' => 'off',
            'paipai' => 'off',
            'qq_buy' => 'off',
            '360buy' => 'on',
            'jd' => 'on',
            'yihaodian' => 'on',
            'dangdang' => 'off',
            'amazon' => 'off',
            //'yintai' => 'off',
            'vjia' => 'off',
            'alibaba' => 'off',
            'suning' => 'off',
            'beibei' => 'off',
            'vop' => 'off',
            //'wdwd' => 'off',
            'mengdian' => 'off',
            //'zhe800'=>'off',
            'weimob'=>'off',
            'weimobv'  => 'off',
            'weimobr'  => 'off',
            //'yunmall'  => 'off',
            'yutang'=>'on',
            'mingrong'=>'on',
            //'gs'=>'on',
            //'hupu'=>'off',
            'luban'=>'off',
            'kuaishou'=>'off',
            'ecos.ecshopx' => 'off',
            'dewu' => 'off',
            'yunji4fx' => 'off',
            'alibaba4ascp' => 'off',
            'website' => 'off',
            'b2b' => 'off',
        );
        return $store_config;
    }

    /**
     * 京东类型
     * 京东类型
     * @return array
     */
    static function jingdong_type(){
        $shop = array('360buy','jd');
        return $shop;
    }

    /**
     * shopex前端店铺列表
     * @author yangminsheng
     * @return array
     * */
    static function shopex_shop_type(){
        $shop = array('shopex_b2b','shopex_b2c','ecos.b2c','ecshop_b2c','ecos.dzg','bbc','ecos.b2b2c.stdsrc','shopex_fy','shopex_penkrwd','ecos.ecshopx');
        return $shop;
    }

    /**
     * (已弃用) chenping 2014-1-9
     * 前端店铺是否需要发货明细
     * @params on 需要发货明细 off 不需要
     * @return void
     * @author
     * */
    static function is_shop_deliveryitem($shop_type = null)
    {
        $shop_list = array(
            'shopex_b2c' => 'on',
            'shopex_b2b' => 'on',
            'ecos.b2c' => 'on',
            'ecos.dzg' => 'on',
            'taobao' => 'off',
            'paipai' => 'off',
            '360buy' => 'off',
            'ecshop_b2c' => 'off',
            'yihaodian' => 'off',
            'qq_buy' => 'off',
            'dangdang' => 'on',
            'amazon' => 'on',
            //'yintai' => 'off',
            'vjia' => 'off',
            'alibaba' => 'off',
        );

        return $shop_list[$shop_type];
    }

    /**
     * 是否允许开启单独获取店铺订单配置
     * @param shop_type
     * @return void
     * @author
     * */
    static function get_shoporder_config($shop_type = null)
    {
        $shop_config = array(
            'shopex_b2b' => 'on',
            'shopex_b2c' => 'on',
            'ecos.b2c' => 'on',
            'ecos.dzg' => 'on',
            'taobao' => 'on',
            'paipai' => 'on',
            'qq_buy' => 'on',
            '360buy' => 'on',
            'jd' => 'on',
            'yihaodian' => 'on',
            'dangdang' => 'off',
            'amazon' => 'off',
            //'yintai' => 'off',
            'vjia' => 'off',
            'alibaba' => 'on',
            'suning' => 'on',
            'suning4zy' => 'on',
            //'icbc' => 'on',
            'beibei' => 'on',
            //'wdwd' => 'on',
            'vop' => 'off',
            'youzan' => 'on',
            'meilishuo' => 'on',
            //'mls' => 'on',
            //'ccb' => 'on',
            'mia' => 'on',
            'feiniu' => 'on',
            //'gome' => 'on',
            'juanpi' => 'on',
            'mogujie' => 'on',
            'wx' => 'on',
            'ecshop_b2c' => 'on',
            'bbc' => 'on',
            'mengdian' => 'on',
            //'zhe800' => 'on',
            'weimob'=>'on',
            //'mls' => 'on',
            'mgj' => 'on',
            //'kaola'=>'on',
            //'kaola4zy'=>'off',
            'pinduoduo'=>'on',
            'ecos.b2b2c.stdsrc'=> 'on',
            'renrendian'=>'on',
            'haoshiqi' => 'on',
            'weidian' => 'on',
            'eyee' => 'on',
            'yunji' => 'on',
            'yunji4pop' => 'on',
            'xiaohongshu' => 'on',
            'xhs' => 'on',
            'shunguang' => 'on',
            'congminggou' => 'off',
            'gegejia' => 'off',
            'xiaodian' => 'on',
            //'gs'=>'on',
            'yutang'=>'on',
            'mingrong'=>'on',
            //'hupu'=>'off',
            //'yitiao'=>'off',
            'luban'=>'on',
            'xiaomi'=>'on',
            'weimobv'=>'on',
            'weimobr'=>'on',
            //'yunmall'=>'on',
            'yangsc'=>'off',
            'ecos.ecshopx' => 'on',
            'kuaishou' => 'on',
            'dewu' => 'on',
            'yunji4fx' => 'on',
            'alibaba4ascp' => 'on',
            'yunji4pop' => 'on',
            'huawei' => 'on',
            'website' => 'on',
            'b2b' => 'on',
            'meituan4bulkpurchasing' => 'on',
        );

        if(!empty($shop_type)){
            return $shop_config[$shop_type];
        }

        return $shop_config;
    }

    /**
     * 采购单同步店铺
     */
    static function get_shop_purchase_sync($shop_type = null)
    {
        $shop_list = array('vop'=>'on');
        
        return ($shop_list[$shop_type]=='on' ? true : false);
    }

    /**
     * ecos.ecshopx源源客
     * @access private
     */
    private $ecos_ecshopx = array(
            SHOP_TRADE_SHIPPING_ADD,
            SHOP_TRADE_SHIPPING_STATUS_UPDATE,
            SHOP_TRADE_SHIPPING_UPDATE,
            SHOP_PAYMETHOD_RPC,
            SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
            SHOP_ADD_PAYMENT_RPC,
            SHOP_ADD_AFTERSALE_RPC,
            SHOP_UPDATE_AFTERSALE_STATUS_RPC,
            SHOP_ADD_REFUND_RPC,
            SHOP_ADD_RESHIP_RPC,
            SHOP_UPDATE_RESHIP_STATUS_RPC,
            SHOP_UPDATE_PAYMENT_STATUS_RPC,
            SHOP_UPDATE_REFUND_STATUS_RPC,
            SHOP_UPDATE_TRADE_RPC,
            SHOP_UPDATE_TRADE_STATUS_RPC,
            SHOP_UPDATE_TRADE_TAX_RPC,
            SHOP_UPDATE_TRADE_SHIP_STATUS_RPC,
            SHOP_UPDATE_TRADE_PAY_STATUS_RPC,
            SHOP_UPDATE_TRADE_MEMO_RPC,
            SHOP_ADD_TRADE_MEMO_RPC,
            SHOP_ADD_TRADE_BUYER_MESSAGE_RPC,
            SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
            SHOP_UPDATE_TRADE_ITEM_FREEZSTORE_RPC,
            SHOP_GET_ITEMS_ALL_RPC,// 获取前端商品
            SHOP_GET_ITEMS_LIST_RPC,// 通过IID获取多个前端商品
            //SHOP_UPDATE_ITEM_APPROVE_STATUS_RPC,// 单个商品上下架更新
            //SHOP_UPDATE_ITEM_APPROVE_STATUS_LIST_RPC,批量上下架
            SHOP_ITEM_SKU_GET,// 单拉商品SKU
            SHOP_ITEM_GET,// 通过IID获取单个商品
            SHOP_LOGISTICS_OFFLINE_SEND,
            SHOP_REFUSE_REFUND,
    );
    
    /**
     * 允许虚拟发货的店铺类型
     * 
     * @return array
     */
    static function virtual_delivery()
    {
        return array('taobao','mengdian','weidian','public_b2c');
    }
    
    /**
     * 多包裹回写配置
     * @return string[]
     * @author db
     * @date 2024-03-22 5:20 下午
     */
    static function many_split_type()
    {
        return array('pinduoduo');
    }
}
