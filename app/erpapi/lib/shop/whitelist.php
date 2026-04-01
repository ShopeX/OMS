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
 * @author ykm 2016-04-15
 * @describe 平台接口白名单
 */
class erpapi_shop_whitelist {
    private $whiteList;

    /**
     * __construct
     * @return mixed 返回值
     */

    public function __construct(){
        $this->whiteList = array(
            'shopex_b2c'        => $this->shopexb2c,
            'shopex_b2b'        => $this->shopexb2b,
            'ecos.b2c'          => $this->ecosb2c,
            'ecos.dzg'          => $this->ecosdzg,
            'taobao'            => $this->taobao,
            'ecshop_b2c'        => $this->ecshop,
            'youa'              => $this->youa,
            'paipai'            => $this->paipai,
            '360buy'            => $this->jingdong,
            'jd'                => $this->jd,
            'yihaodian'         => $this->yihaodian,
            'qq_buy'            => $this->qqbuy,
            'dangdang'          => $this->dangdang,
            'amazon'            => $this->amazon,
            'vjia'              => $this->vjia,
            'alibaba'           => $this->alibaba,
            'suning'            => $this->suning,
            'yintai'            => $this->yintai,
            'icbc'              => $this->icbc,
            'mogujie'           => $this->mogujie,
            'gome'              => $this->gome,
            'wx'                => $this->wx,
            'ccb'               => $this->ccb,#建设银行
            'meilishuo'         => $this->meilishuo,
            'feiniu'            => $this->feiniu,
            'youzan'            => $this->youzan,
            'juanpi'            => $this->juanpi,
            'mia'               => $this->mia,
            'bbc'               => $this->bbc,
            'beibei'            => $this->beibei,
            'wdwd'              => $this->wdwd,
            'vop'               => $this->vop,
            'mengdian'          => $this->mengdian,
            'zhe800'            => $this->zhe800,
            'weimob'            => $this->weimob,
            'public_b2c'        => $this->public_b2c,
            'mls'               => $this->mls,
            'mgj'               => $this->mgj,
            'kaola'             => $this->kaola,
            'shopex_fy'         => $this->shopex_fy,
            'pinduoduo'         => $this->pinduoduo,
            'shopex_penkrwd'    => $this->shopex_penkrwd,
            'ecos.b2b2c.stdsrc' => $this->bbc,
            'cmb'               => $this->cmb,
            'renrendian'        => $this->renrendian,
            'haoshiqi'          => $this->haoshiqi,
            'weidian'           => $this->weidian,
            'eyee'              => $this->eyee,
            'yunji'             => $this->yunji,
            'xiaohongshu'       => $this->xiaohongshu,
            'xhs'               => $this->xhs,
            'shunguang'         => $this->shunguang,
            'congminggou'       => $this->congminggou,
            'gegejia'           => $this->gegejia,
            'xiaodian'          => $this->xiaodian,
            'aikucun'           => $this->aikucun,
            'weimobv'           => $this->weimobv,
            'weimobr'           => $this->weimobr,
            'gs'                => $this->gs,
            'kaola4zy'          => $this->kaola4zy,
            'yutang'            => $this->yutang,
            'suning4zy'         => $this->suning4zy,
            'mingrong'          => $this->mingrong,
            'hupu'              => $this->hupu,
            'yitiao'            => $this->yitiao,
            'luban'             => $this->luban,
            'yangsc'            => $this->yangsc,
            'ecos.ecshopx'      => $this->ecos_ecshopx,
            'kuaishou'          => $this->kuaishou,
            'dewu'              => $this->dewu,
            'yunji4fx'          => $this->yunji4fx,
            'alibaba4ascp'      => $this->alibaba4ascp,
            'yunmall'           => $this->yunmall,
            'meituan4medicine'  => $this->meituan4medicine,
            'meituan4bulkpurchasing'  => $this->meituan4bulkpurchasing,
            'meituan4sg'        => $this->meituan4sg,
            'yunji4pop'         => $this->yunji4pop,
            'huawei'            => $this->huawei,
            'weixinshop'        => $this->weixinshop,
            'website'           => $this->website,
            'pekon'             => $this->pos,
            'pos'               => $this->pos,
            'wxshipin'          => $this->wxshipin,
            'zkh'               => $this->zkh,
            'website_v2'        => $this->website,
            'xiaomi'            => $this->xiaomi,
        );
    }

    /**
     * 获取WhiteList
     * @param mixed $nodeType nodeType
     * @return mixed 返回结果
     */
    public function getWhiteList($nodeType) {
        return $this->whiteList[$nodeType] ? array_merge($this->whiteList[$nodeType], $this->public_api) : $this->public_api;
    }

    #平台共有接口
    private $public_api = array(
        SHOP_LOGISTICS_PUB,
        SHOP_LOGISTICS_BIND,
        SHOP_TRADE_FULLINFO_RPC,
        SHOP_FENXIAO_TRADE_FULLINFO_RPC,
        SHOP_IFRAME_TRADE_EDIT_RPC,
        SHOP_GET_TRADES_SOLD_RPC,
        SHOP_LOGISTICS_SUBSCRIBE,
        SHOP_INVENTORY_CACHE_QUERY, //商品缓存查询
        STORE_LOGISTICS_COMPANIES_GET,
        STORE_WAYBILL_SEARCH,
        STORE_WAYBILL_SERVICE_SEARCH,
    );

    private $public_b2c = array(
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
    );

    /**
     * EC-STORE RPC服务接口名列表
     * @access private
     */
    private $ecosb2c = array(
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
     * 店掌柜 RPC服务接口名列表
     * @access private
     */
    private $ecosdzg = array(
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
    );

    /**
     * SHOPEX485 RPC服务接口名列表
     * @access private
     */
    private $shopexb2c = array(
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
        //SHOP_UPDATE_RESHIP_STATUS_RPC,
        SHOP_UPDATE_TRADE_RPC,
        SHOP_UPDATE_TRADE_STATUS_RPC,
        SHOP_UPDATE_TRADE_TAX_RPC,
        SHOP_UPDATE_TRADE_SHIP_STATUS_RPC,
        SHOP_UPDATE_TRADE_MEMO_RPC,
        SHOP_ADD_TRADE_MEMO_RPC,
        SHOP_ADD_TRADE_BUYER_MESSAGE_RPC,
        SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
    );

    /**
     * 当当 RPC服务接口名列表
     * @access private
     */
    private $dangdang = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_GET_DANGDANG_SHOP_CATEGORYLIST,
        SHOP_UPDATE_DANGDANG_QUANTITY_LIST_RPC,
    );

    /**
     * 一号店 RPC服务接口名列表
     * @access private
     */
    private $yihaodian = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_CHECK_REFUND_GOOD,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_REFUSE_RETURN_GOOD,
        SHOP_GET_TRADE_INVOICE_RPC,
    );

    /**
     * 淘宝 RPC服务接口名列表
     * @access private
     */
    private $taobao = array(
        SHOP_LOGISTICS_ONLINE_SEND, #在线下单
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_WLB_ORDER_JZPARTNER_QUERY,
        SHOP_WLB_ORDER_JZWITHINS_CONSIGN,
        SHOP_TMC_MESSAGE_PRODUCE,
        SHOP_LOGISTICS_DUMMY_SEND,
        SHOP_LOGISTICS_ADDRESS_SEARCH,
        SHOP_BILL_BOOK_BILL_GET,
        SHOP_BILL_BILL_GET,
        SHOP_USER_TRADE_SEARCH,
        SHOP_TOPATS_RESULT_GET,
        SHOP_TOPATS_USER_ACCOUNTREPORT_GET,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_UPDATE_FENXIAO_ITEMS_QUANTITY_LIST_RPC,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_REFUSE_REFUND,
        SHOP_AGREE_RETURN_I_GOOD_TMALL,
        SHOP_REFUSE_RETURN_I_GOOD_TMALL,
        SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
        SHOP_GET_ITEMS_ALL_RPC,
        SHOP_GET_ITEMS_LIST_RPC,
        SHOP_GET_FENXIAO_PRODUCTS,
        SHOP_ITEM_GET,
        SHOP_UPDATE_FENXIAO_PRODUCT,
        SHOP_ITEM_SKU_GET,
        SHOP_UPDATE_ITEM_APPROVE_STATUS_RPC,
        SHOP_UPDATE_ITEM_APPROVE_STATUS_LIST_RPC,
        SHOP_GET_REFUND_MESSAGE,
        SHOP_GET_REFUND_I_MESSAGE_TMALL,
        SHOP_ADD_REFUND_MESSAGE,
        SHOP_REFUND_GOOD_RETURN_CHECK,
        SHOP_GET_TRADE_REFUND_RPC,
        SHOP_GET_TRADE_REFUND_I_RPC,
        SHOP_REFUNSE_REFUND_I_TMALL,
        SHOP_AGREE_REFUND_I_TMALL,
        SHOP_GET_CLOUD_STACK_PRINT_TAG,
        SHOP_WLB_ORDER_JZ_QUERY,
        SHOP_WLB_ORDER_JZ_CONSIGN,
        //SHOP_GET_ACCOUNTREPORT,
        STORE_AG_SENDGOODS_CANCEL,
        STORE_AG_LOGISTICS_WAREHOUSE_UPDATE,
        STORE_CN_RULE,
        //STORE_CN_SMARTDELIVERY,
        SHOP_WLB_THREEPL_OFFLINE_SEND,
        SHOP_WLB_THREEPL_RESOUCE_GET,
        SHOP_EXCHANGE_RETURNGOODS_AGREE,
        SHOP_EXCHANGE_RETURNGOODS_REFUSE,
        SHOP_EXCHANGE_REFUSEREASON_GET,
        SHOP_EXCHANGE_MESSAGE_GET,
        SHOP_EXCHANGE_MESSAGE_ADD,
        SHOP_EXCHANGE_CONSIGNGOODS,
        SHOP_REFUSE_CHANGE_I_GOOD_TMALL,
        SHOP_AGREE_CHANGE_I_GOOD_TMALL,
        SHOP_EXCHANGE_GET,
        SHOP_RDC_ORDERMSG_UPDATE,
        STORE_CN_WAYBILL_II_SEARCH,
        LOGISTICS_SERVICE_AREAS_ALL_GET,
        STORE_MODIFYSKU_OPEN,
        TAOBAO_COMMON_TOP_SEND,
        SHOP_GET_SUPPLIER_PRODUCTS,
        SHOP_SUPPLIER_ORDER_CONFIRM,
        SHOP_SUPPLIER_ORDER_LACK_APPLY,
        SHOP_SUPPLIER_ORDER_REJECT_APPLY,
        SHOP_SUPPLIER_ORDER_CANCEL_BACK,
        SHOP_SUPPLIER_RETURN_GOOD_CONFIRM,
        SHOP_SUPPLIER_RETURN_GOOD_INSTORAGE_RESULT,
        SHOP_REFUND_DETAIL_GET,
        SHOP_REFUND_INTERCEPT,
        SHOP_REFUND_NEGOTIATERETURN_RENDER,
        SHOP_REFUND_NEGOTIATERETURN,
        SHOP_REFUND_NOTIFY_GET,
        SHOP_REFUND_STATUS_GET,
        SHOP_LOGISTICS_SELLER_SEND,
        SHOP_LOGISTICS_SELLER_WRITEOFF,
        SHOP_LOGISTICS_SELLER_RESEND,
        SHOP_LOGISTICS_SELLER_TRACE,
        SHOP_LOGISTICS_SELLER_ORDERS,
        SHOP_AOXIANG_WAREHOUSE_CREATE, //翱象仓库对接
        SHOP_AOXIANG_LOGISTICS_CREATE, //翱象物流公司对接
        SHOP_AOXIANG_GOODS_CREATE_ASYNC, //翱象销售物料对接
        SHOP_AOXIANG_GOODS_COMBINE_CREATE_ASYNC, //翱象组合货品接口
        SHOP_AOXIANG_GOODS_DELETE_ASYNC, //翱象货品删除接口
        SHOP_AOXIANG_GOODS_DELETE_MAPPING, //翱象货品删除商品关系接口
        SHOP_AOXIANG_GOODS_MAPPING_ASYNC, //翱象货品关联关系接口
        SHOP_AOXIANG_INVENTORY_SYNC, //翱象实仓库存同步接口
        SHOP_AOXIANG_WAREHOUSE_REPORT, //翱象仓作业信息同步接口
        SHOP_AOXIANG_LOGISTICS_QUERY, //翱象黑白名单快递接口
        SHOP_EXCHANGE_CONFIRMCONSIGN, //卖家确认收货&&卖家发货
        SHOP_FX_JZ_LOGISTICS_OFFLINE_SEND, //喵住发货回写
        SHOP_GET_TRADE_INVOICE_SUMMARY,
        STORE_LOGISTICS_PACKAGE_EXCEPTION_QUERY, //物流包裹异常查询接口
        STORE_LOGISTICS_PACKAGE_EXCEPTION_CONFIG_QUERY, //物流包裹异常配置查询接口
        SHOP_REFUND_NEGOTIATION_GET, // 获取协商退货退款渲染数据
        SHOP_REFUND_NEGOTIATE_CANAPPLY_GET, // 查询是否可发起协商
        SHOP_REFUND_NEGOTIATION_CREATE, //协商退货退款接口
        SHOP_RESHIPPING_GET, //查询补寄详情
        SHOP_RESHIPPING_MESSAGES_GET, //查询补寄留言列表
        SHOP_RESHIPPING_MESSAGE_ADD, //创建补寄留言
        SHOP_RESHIPPING_AGREE, //同意补寄申请
        SHOP_RESHIPPING_REFUSE, //拒绝补寄申请
        SHOP_RESHIPPING_CONSIGNGOODS, //补寄发货
        SHOP_RESHIPPING_REFUSEREASON_GET, //查询拒绝原因列表
        STORE_VIRTUAL_NUMBER_GROUP_UPDATE,
    );

    /**
     * 拍拍 RPC服务接口名列表
     * @access private
     */
    private $paipai = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_GET_ITEMS_ALL_RPC,
        SHOP_ITEM_GET,
        SHOP_ITEM_SKU_GET,
        SHOP_UPDATE_ITEM_APPROVE_STATUS_RPC,
        SHOP_UPDATE_ITEM_APPROVE_STATUS_LIST_RPC,
    );

    /**
     * qq网购 RPC服务接口名列表
     * @access private
     */
    private $qqbuy = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
    );

    /**
     * SHOPEX B2B RPC服务接口名列表
     * @access private
     */
    private $shopexb2b = array(
        SHOP_TRADE_SHIPPING_ADD,
        SHOP_PAYMETHOD_RPC,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_ADD_PAYMENT_RPC,
        SHOP_ADD_AFTERSALE_RPC,
        SHOP_UPDATE_AFTERSALE_STATUS_RPC,
        SHOP_ADD_REFUND_RPC,
        SHOP_ADD_RESHIP_RPC,
        SHOP_UPDATE_RESHIP_STATUS_RPC,
        SHOP_UPDATE_TRADE_RPC,
        SHOP_UPDATE_TRADE_STATUS_RPC,
        SHOP_UPDATE_TRADE_TAX_RPC,
        SHOP_UPDATE_TRADE_MEMO_RPC,
        SHOP_ADD_TRADE_MEMO_RPC,
        SHOP_ADD_TRADE_BUYER_MESSAGE_RPC,
        SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
    );


    /**
     * 京东 RPC服务接口名列表
     * @access private
     */
    private $jingdong = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_CHECK_REFUND_GOOD,
        SHOP_GET_ITEMS_ALL_RPC,
        SHOP_GET_ITEMS_LIST_RPC,
        SHOP_ITEM_GET,
        SHOP_ITEM_SKU_GET,
        SHOP_UPDATE_ITEM_APPROVE_STATUS_RPC,
        SHOP_UPDATE_ITEM_APPROVE_STATUS_LIST_RPC,
        SHOP_GET_ITEMS_VALID_RPC,
        SHOP_ITEM_I_GET,
        SHOP_ITEM_SKU_I_GET,
        SHOP_LOGISTICS_ADDRESS_SEARCH,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_REFUSE_RETURN_GOOD,
        SHOP_ASC_AUDIT_REASON_GET,
        STORE_AG_SENDGOODS_CANCEL,
        STORE_AG_LOGISTICS_WAREHOUSE_UPDATE,
        STORE_TRADE_COUPONDETAIL_GET,
        JD_COMMON_TOP_SEND,
        SHOP_ORDER_SPLIT,
        SHOP_REFUND_NEGOTIATION_GET,
        STORE_JD_NPS_TOKEN,
         SHOP_JDLVMI_GET_ITEMS_ALL_RPC,
        SHOP_JDLVMI_ITEMS_PRODUCT_GET,
        SHOP_JDVMI_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_WMS_OUTORDER_NOTIFY,
        SHOP_WMI_LOGISTICS_OFFLINE_SEND,
        SHOP_RETURN_GOOD_CONFIRM,
        SHOP_VMI_RETURN_GOOD_CONFIRM,
        SHOP_COMPENSATE_REFUND_GET,
        SHOP_INVENTORY_QUERY,
        EINVOICE_DETAIL_UPLOAD,
        SHOP_SERIALNUMBER_UPDATE,
        SHOP_BILL_PAYABLE_SETTLEMENT_QUERY,
        SHOP_LOGISTICS_CONSIGN_RESEND,
        STORE_VIRTUAL_NUMBER_GROUP_UPDATE,
    );

    /**
     * 京东供应商平台 RPC服务接口名列表
     * @access private
     */
    private $jd = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_REFUND_CHECK,
        SHOP_OUT_BRANCH,
        JD_COMMON_TOP_SEND,
        SHOP_JDGXD_LOGISTICS_FULFILLMENT_INFO,
        SHOP_JDGXD_CHOICE_LOGISTICS,
        STORE_THIRDPDF_FORVENDER_GET,
    );
    
    /**
     * 亚马逊 RPC服务接口名列表
     * @access private
     */
    private $amazon = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );

    /**
     * 凡客 RPC服务接口名列表
     * @access private
     */
    private $vjia = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_TRADE_OUTSTORAGE,
        SHOP_LOGISTICS_CONSIGN_RESEND,
        SHOP_LOGISTICS_RESEND_CONFIRM,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_GET_TRADE_INVOICE_RPC,
    );

    /**
     * 阿里巴巴 RPC服务接口名列表
     * @access private
     */
    private $alibaba = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_ITEM_GET,
        //SHOP_NEW_ITEM_GET,
        SHOP_ITEM_I_GET,
        SHOP_GET_ITEMS_ALL_RPC,
        //SHOP_ITEM_SKU_GET, //单拉商品SKU(矩阵不支持)
        SHOP_LOGISTICS_OFFICIAL_PICKUP,
    );

    /**
     * 苏宁 RPC服务接口名列表
     * @access private
     */
    private $suning = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_ITEM_GET,
        SHOP_GET_ITEMS_CUSTOM,
        STORE_LOGISTICS_DUMMY_SEND,
    );
    /**
     * 银泰 RPC服务接口列表
     * @access private
     */
    private $yintai = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_GET_ITEMS_CUSTOM,
    );
    #工行RPC服务接口
    private $icbc = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );
    #蘑菇街RPC服务接口
    private $mogujie = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );
    #国美RPC服务接口
    private $gome= array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );
    #微信RPC服务接口
    private $wx= array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_ITEM_GET,
    );
    #建设银行RPC服务接口
    private $ccb = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );
    #meilishuoRPC服务接口
    private $meilishuo = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_MEILISHUO_REFUND_GOOD_RETURN_AGREE,
    );
    #飞牛RPC服务接口
    private $feiniu = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );
    #有赞RPC服务接口
    private $youzan = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_AGREE_REFUNDGOODS,
        SHOP_REFUSE_REFUNDGOODS,
        SHOP_EXCHANGE_RETURNGOODS_REFUSE,
        SHOP_AGREE_CHANGE_I_GOOD_TMALL,
        SHOP_REFUSE_CHANGE_I_GOOD_TMALL,
        SHOP_EXCHANGE_CONFIRMCONSIGN,
    );
    #卷皮RPC服务接口
    private $juanpi = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
    );
    #蜜芽宝贝RPC服务接口
    private $mia = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );
    /**
     * 店掌柜 RPC服务接口名列表
     * @access private
     */
    private $bbc = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_REFUSE_REFUND,
        SHOP_ADD_REFUND_RPC,
        SHOP_UPDATE_TRADE_STATUS_RPC,
        SHOP_UPDATE_TRADE_TAX_RPC,
        SHOP_GET_ITEMS_ALL_RPC,
        SHOP_GET_ITEMS_LIST_RPC,
        SHOP_ITEM_GET,
        SHOP_ITEM_SKU_GET,
        SHOP_UPDATE_AFTERSALE_STATUS_RPC,
        SHOP_TRADE_SHIPPING_STATUS_UPDATE,
    );
    private $beibei = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC
    );
    private $wdwd = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC
    );
    #唯品会RPC服务接口
    private $vop = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_COMMONS_VOP_JIT,
        SHOP_GET_ORDER_STATUS,
        SHOP_PRINT_THIRD_BILL,
        SHOP_GET_DLY_INFO,
        SHOP_BRANCH_FEEDBACK,
        SHOP_BILL_DETAIL_GET,
        SHOP_BILL_LIST_GET,
        SHOP_COMMONS_VOP_BILL,
        SHOP_JITX_WAREHOUSES_GET,
        SHOP_GET_COOPERATIONNOLIST,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_ITEM_SKU_LIST,
        SHOP_VOP_INVENTORY,
        SHOP_VOP_FEEFBACK,
        SHOP_VOP_DOWNLOAD,
        SHOP_BILL_BOOK_BILL_GET,
        SHOP_RETURNORDER_DIFF_DETAIL_GET,
        SHOP_RETURNORDER_DIFF_LIST_GET,
    );
    private $mengdian = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_LOGISTICS_DUMMY_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC
    );
    #ecshop
    private $ecshop = array(
        SHOP_UPDATE_TRADE_MEMO_RPC,
        SHOP_ADD_TRADE_BUYER_MESSAGE_RPC,
        SHOP_ADD_RESHIP_RPC,
        SHOP_ADD_REFUND_RPC,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_UPDATE_TRADE_RPC,
        SHOP_ADD_PAYMENT_RPC,
        SHOP_PAYMETHOD_RPC,
        SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
        SHOP_UPDATE_TRADE_STATUS_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND
    );
    private $zhe800 = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
    );

    private $kaola = array(
            SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
            SHOP_LOGISTICS_OFFLINE_SEND,
            SHOP_AGREE_REFUND,
            SHOP_REFUSE_REFUND,
            SHOP_AGREE_REFUNDGOODS,
            SHOP_REFUSE_REFUNDGOODS,
    );

    private $pinduoduo = array(
            SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
            SHOP_LOGISTICS_OFFLINE_SEND,
            // SHOP_GET_ORDER_STATUS,
            SHOP_AG_SEND_CANCEL,
            STORE_HQEPAY_ORDERSERVICE,
            SHOP_LOGISTICS_RECOMMEND,
            SHOP_EXCHANGE_CONSIGNGOODS,
            SHOP_GET_ITEMS_ALL_RPC,
            SHOP_GET_ITEMS_LIST_RPC,
            SHOP_ITEM_GET,
            SHOP_ITEM_SKU_GET,
            SHOP_GET_ITEMS_CUSTOM,
            STORE_AG_LOGISTICS_WAREHOUSE_UPDATE,
            STORE_AG_SENDGOODS_CANCEL,
            SHOP_TRADE_OPERATION_IN_WAREHOUSE,
            SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
            SHOP_GET_ADDRESS_PROVINCE,
            STORE_ADDRESS_GETBY_PROVINCE,
            SHOP_ISV_PAGE_CODE,
    );

   private $weimob = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
    );

    /**
     * mls (美丽说2)RPC服务接口
     * @var array
     */
    private $mls = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );

    private $mgj = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );

    private $shopex_fy = array(
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
    );

    private $shopex_penkrwd = array(
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
    );

    private $cmb = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
    );

    private $renrendian = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );

    #好食期
    private $haoshiqi = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
    );

    #微店服务接口
    private $weidian = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_LOGISTICS_DUMMY_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );

    //蜂潮服务接口
    private $eyee = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_LOGISTICS_DUMMY_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );

    //云集
    private $yunji = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
    );
    
    //小红书
    private $xiaohongshu = array(
            SHOP_LOGISTICS_OFFLINE_SEND, //发货结果回传
            SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
            SHOP_AGREE_REFUND, //同意退款
            SHOP_REFUSE_REFUND, //拒绝退款单
            SHOP_AGREE_REFUNDGOODS, //同意退货
            SHOP_REFUSE_REFUNDGOODS, //拒绝退货
    );
    //新小红书
    private $xhs = array(
        SHOP_LOGISTICS_OFFLINE_SEND, //发货结果回传
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
        SHOP_AGREE_REFUND, //同意退款
        SHOP_REFUSE_REFUND, //拒绝退款单
        SHOP_AGREE_REFUNDGOODS, //同意退货
        SHOP_REFUSE_REFUNDGOODS, //拒绝退货
        SHOP_AGREE_REFUNDGOODS,
        SHOP_REFUSE_REFUNDGOODS,
        SHOP_RETURN_GOOD_CONFIRM,
        SHOP_EXCHANGE_CONSIGNGOODS,
        SHOP_EXCHANGE_CONFIRMCONSIGN,
        STORE_STANDARD_XHS_TEMPLATE,
        STORE_STANDARD_XHS_SEARCH,
    );
    
    //顺逛
    private $shunguang = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_ADD_REFUND_RPC,
    );

    //聪明购
    private $congminggou = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_ADD_REFUND_RPC,
    );

    //格家网络
    private $gegejia = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
    );

    //小店
    private $xiaodian = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );

    //爱库存
    private $aikucun = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_ITEM_STOCK_GET, //库存查询
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
        //SHOP_GET_ITEMS_ALL_RPC,// 获取前端商品
        //SHOP_GET_ITEMS_LIST_RPC, //通过IID获取多个前端商品
        //SHOP_ITEM_GET, //通过IID获取单个商品
    );

    //微盟微商城
    private $weimobv = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_REFUSE_RETURN_GOOD,
        SHOP_RETURN_GOOD_CONFIRM,
        SHOP_EXCHANGE_CONSIGNGOODS,
        SHOP_CHECK_REFUND_GOOD,
        SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
        SHOP_GET_ADDRESS_PROVINCE,
        STORE_ADDRESS_GETBY_PROVINCE,
    );

    //微盟零售
    private $weimobr = array(
        SHOP_TRADE_FULLINFO_RPC, // 获取订单详情
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, // 覆盖/增量更新库存
        SHOP_LOGISTICS_OFFLINE_SEND, // 商家配送发货(线下物流)
        SHOP_LOGISTICS_CONSIGN_RESEND, // 修改配送信息
        SHOP_AGREE_RETURN_GOOD, // 同意售后单
        SHOP_REFUSE_RETURN_GOOD,
        SHOP_TRADE_REFUND_GET, // 查询售后单详情
        SHOP_RETURN_GOOD_CONFIRM,
        SHOP_GET_ITEMS_ALL_RPC, // 获取所有商品
        SHOP_ITEM_GET, // 查询在售商品
        SHOP_GET_INVENTORY_QUERY, // 批量查询商品库存
        STORE_O2O_STORES_GET, // 获取门店信息
        STORE_KS_SUB_RETURNINFO, // 根据售后单号填写退货物流
    );
    
    // 环球捕手 by mxc
    private $gs = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );
    
    private $kaola4zy = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_RDC_ORDERMSG_UPDATE
    );

    //鱼塘
    private $yutang = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND
    );

    /**
     * 苏宁自营 RPC服务接口名列表
     * @access private
     */
    private $suning4zy = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_ITEM_GET,
        SHOP_GET_ITEMS_CUSTOM,
        SHOP_LOGISTICS_ADDRESS_SEARCH,
        SHOP_REFUSE_REFUND,
        SHOP_AGREE_REFUND,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_REFUSE_RETURN_GOOD
    );
    
    private $mingrong = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_EXCHANGE_GET,
        SHOP_EXCHANGE_CONSIGNGOODS,
        SHOP_AGREE_REFUND,
        SHOP_REFUSE_REFUND,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_AGREE_CHANGE_I_GOOD_TMALL,
        SHOP_REFUSE_CHANGE_I_GOOD_TMALL,
        SHOP_REFUSE_RETURN_GOOD
    );

    // 虎扑
    private $hupu = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_GET_ORDER_STATUS,
    );
    
    //得物
    private $dewu = array(
            SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
            SHOP_LOGISTICS_OFFLINE_SEND,
            //SHOP_GET_ORDER_STATUS, //获取订单状态
            SHOP_GET_BIDDING_ALL,
            SHOP_UPDATE_BIDDING_QUANTITY,
            SHOP_GET_BIDDING_BRAND_ALL,
            SHOP_GET_BIDDING_NORMAL_SKUS,
            SHOP_GET_BIDDING_BRAND_SKUS,
            SHOP_GET_BIDDING_NORMAL_DETAIL,
            SHOP_GET_BIDDING_BRAND_DETAIL,
            SHOP_RESHIP_AUDIT,
            SHOP_RETURN_GOOD_SIGN,
            SHOP_RETURN_GOOD_CHECK,
            STORE_ORDER_BRAND_DELIVER_QUERY_BUYER_ADDRESS,
            STORE_ORDER_BRAND_DELIVER_ACCEPT_ORDER,
            STORE_ORDER_BRAND_DELIVER_QUERY_SELLER_ADDRESS,
            STORE_ORDER_BRAND_DELIVER_CHANGE_DELIVERY_WAREHOUSE,
            STORE_ORDER_BRAND_DELIVER_LOGISTIC_NO,
            STORE_ORDER_BRAND_DELIVER_EXPRESS_SHEET,
            STORE_ORDER_BRAND_DELIVER_DELIVERY,
            SHOP_GET_BIDDING_BRAND_DELI_ALL,
            SHOP_GET_INVENTORY_QUERY,
            SHOP_GET_SKU_PRICE_ALL,
    );
    
    //抖音
    private $luban = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //回写库存
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_REFUSE_RETURN_GOOD,
        SHOP_AGREE_REFUND,
        SHOP_REFUSE_REFUND,
        SHOP_GET_ITEMS_VALID_RPC, //同步平台货品
        SHOP_CONFIRM_ADDRESS_MODIFY,
        SHOP_AFTERSALE_EXCHANGE_AGREE, //同意退货
        SHOP_AFTERSALE_EXCHANGE_REFUSE, //拒绝退货
        SHOP_EXCHANGE_RETURNGOODS_AGREE, //[换货]卖家确认收货
        SHOP_EXCHANGE_RETURNGOODS_REFUSE, //[换货]卖家拒绝收货
        SHOP_GET_ADDRESS_PROVINCE,
        STORE_ADDRESS_GETBY_PROVINCE,
        SHOP_BIND_WAREHOUSE_ADDR,
        SHOP_UNBIND_WAREHOUSE_ADDR,
        SHOP_CREATE_WAREHOUSE,
        SHOP_EDIT_WAREHOUSE,
        SHOP_SET_WAREHOUSE_PRIORITY,
        SHOP_GET_ITEMS_LIST_RPC,
        SHOP_ITEM_GET,
        SHOP_ORDER_SETTLE_GET,
        SHOP_ITEM_STOCK_GET,
        SHOP_ITEM_SKU_GET,
        SHOP_AGREE_AFTERSALE_REFUND,
        SHOP_REFUSE_AFTERSALE_REFUND,
        SHOP_REFUND_LIST_SEARCH,
        SHOP_SKU_SET_SELL_TYPE, //设置SKU区域库存发货时效
        SHOP_GET_ADDRESS_LIST, //拉取商家退货地址列表
        SHOP_AFTERSALE_RETURN_REMARK, //同步京东审核意见给到抖音平台
        SHOP_LOGISTICS_RECOMMENDED_DELIVERY,
        SHOP_SERIALNUMBER_UPDATE,
        STORE_AG_LOGISTICS_WAREHOUSE_UPDATE,
        STORE_AG_SENDGOODS_CANCEL,
        SHOP_INVOICE_QUERY, //获取发票信息
        SHOP_UPLOAD_ORDER_RECORD, // 订单链路监控接口
        SHOP_INVOICE_STATUS_UPDATE, //发票上传
        SHOP_RESHIPPING_GET, //查询补寄详情
        SHOP_RESHIPPING_MESSAGES_GET, //查询补寄留言列表
        SHOP_RESHIPPING_MESSAGE_ADD, //创建补寄留言
        SHOP_RESHIPPING_AGREE, //同意补寄申请
        SHOP_RESHIPPING_REFUSE, //拒绝补寄申请
        SHOP_RESHIPPING_CONSIGNGOODS, //补寄发货
        SHOP_RESHIPPING_REFUSEREASON_GET, //查询拒绝原因列表
    );
    
    private $yangsc = array(
        //SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
        
    );
    
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
            EINVOICE_DETAIL_UPLOAD
    );

    private $kuaishou = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_REFUSE_RETURN_GOOD,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_RETURN_GOOD_CONFIRM,
        SHOP_GET_ITEMS_LIST_RPC,
        SHOP_ITEM_SKU_LIST,
        SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
        SHOP_GET_ADDRESS_PROVINCE,
        STORE_ADDRESS_GETBY_PROVINCE,
        SHOP_EXCHANGE_CONSIGNGOODS,
        SHOP_AGREE_CHANGE_I_GOOD_TMALL,
        SHOP_REFUSE_CHANGE_I_GOOD_TMALL,
        STORE_KS_SUB_RETURNINFO,
        SHOP_CONFIRM_ADDRESS_MODIFY,
    );

    private $yunji4fx = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_LOGISTICS_OFFLINE_SEND,
    );

    //淘工厂
    private $alibaba4ascp = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //回写库存
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_REFUSE_RETURN_GOOD,
        SHOP_AGREE_REFUND,
        SHOP_REFUSE_REFUND,
        SHOP_GET_ITEMS_VALID_RPC, //同步平台货品
        SHOP_CONFIRM_ADDRESS_MODIFY,
    );
    
    //一条商城
    private $yitiao = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );

    //一条商城
    private $yunmall = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        SHOP_AGREE_RETURN_GOOD,
        SHOP_CHECK_REFUND_GOOD,
        SHOP_REFUSE_RETURN_GOOD,
    );
    
    
    //美团医药
    private $meituan4medicine = array(
        SHOP_LOGISTICS_OFFLINE_SEND, //发货结果回传
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
        SHOP_AGREE_RETURN_GOOD, //同意退货
        SHOP_REFUSE_RETURN_GOOD, //拒绝退货
        SHOP_CHECK_REFUND_GOOD,//确认退货
        SHOP_AGREE_REFUND, //同意退款
        SHOP_REFUSE_REFUND, //拒绝退款单
    );
    //美团电商
    private $meituan4bulkpurchasing = array(
        SHOP_LOGISTICS_OFFLINE_SEND, //发货结果回传
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
        SHOP_AGREE_RETURN_GOOD, //同意退货
        SHOP_REFUSE_RETURN_GOOD, //拒绝退货
        SHOP_CHECK_REFUND_GOOD,//确认退货
        SHOP_AGREE_REFUND, //同意退款
        SHOP_REFUSE_REFUND, //拒绝退款单
        SHOP_GET_ITEMS_LIST_RPC,
    );
    
    //美团闪购
    private $meituan4sg = array(
        SHOP_LOGISTICS_OFFLINE_SEND, //发货结果回传
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
        SHOP_AGREE_RETURN_GOOD, //同意退货
        SHOP_REFUSE_RETURN_GOOD, //拒绝退货
        SHOP_CHECK_REFUND_GOOD,//确认退货
        SHOP_AGREE_REFUND, //同意退款
        SHOP_REFUSE_REFUND, //拒绝退款单
        SHOP_GET_ITEMS_LIST_RPC,
        STORE_TRADE_CONFIRM, //订单确认接口
        STORE_TRADE_PICKUP_CONFIRM, //拣货确认接口（小时达平台配送）
    );
    
    //云集pop
    private $yunji4pop = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
    );
    
    //华为商城
    private $huawei = array(
        SHOP_LOGISTICS_OFFLINE_SEND, //发货结果回传
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
        SHOP_AGREE_RETURN_GOOD, //同意退货
        SHOP_REFUSE_RETURN_GOOD, //拒绝退货
        SHOP_CHECK_REFUND_GOOD,//确认退货
        SHOP_AGREE_REFUND, //同意退款
        SHOP_REFUSE_REFUND, //拒绝退款单
        SHOP_GET_ITEMS_ALL_RPC,// 获取前端商品
        SHOP_GET_ITEMS_LIST_RPC, //通过IID获取多个前端商品
        SHOP_ITEM_GET, //通过IID获取单个商品
    );
    
    //微信小商店
    private $weixinshop = array(
        SHOP_LOGISTICS_OFFLINE_SEND, //发货结果回传
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
        SHOP_AGREE_RETURN_GOOD, //同意退货
        SHOP_REFUSE_RETURN_GOOD, //拒绝退货
        SHOP_CHECK_REFUND_GOOD,//确认退货
        SHOP_AGREE_REFUND, //同意退款
        SHOP_REFUSE_REFUND, //拒绝退款单
    );

    //wesite
    private $website = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        SHOP_ADD_REFUND_RPC,
        SHOP_REFUND_CHECK,
        SHOP_ADD_RESHIP_RPC,
        SHOP_UPDATE_RESHIP_STATUS_RPC,
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC,
        EINVOICE_DETAIL_UPLOAD,
        SHOP_AGREE_REFUND,
        SHOP_AGREE_REFUNDGOODS,
        SHOP_REFUSE_REFUNDGOODS,
        SHOP_RETURN_GOOD_CONFIRM,
        SHOP_ADD_AFTERSALE_RPC,
        SHOP_REFUSE_REFUND,
        SHOP_EXCHANGE_CONSIGNGOODS,
        'b2c.reship.create',
        SHOP_EXCHANGE_NOTIFY,
        SHOP_UPDATE_AFTERSALE_STATUS_RPC
    );

    private $pos = array(
       
        SHOP_LOGISTICS_OFFLINE_SEND,
        'orderDeliveryUpdate',
        'refundOrderAudit',
        'refundOrderConfirm',
        'UpdateSalesOrderInvoiceInfo',
        'CreateRefundOrder',
    );
    
    //微信视频号
    private $wxshipin = array(
        SHOP_LOGISTICS_OFFLINE_SEND, //发货结果回传
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC, //更新库存
        SHOP_AGREE_RETURN_GOOD, //同意退货
        SHOP_REFUSE_RETURN_GOOD, //拒绝退货
        SHOP_CHECK_REFUND_GOOD,//确认退货
        SHOP_AGREE_REFUND, //同意退款
        SHOP_REFUSE_REFUND, //拒绝退款单
        SHOP_REFUSE_CHANGE_I_GOOD_TMALL,
        SHOP_EXCHANGE_CONFIRMCONSIGN,
        SHOP_AGREE_CHANGE_I_GOOD_TMALL,
    );
    
    //震坤行 zkh
    private $zkh = array(
//        SHOP_LOGISTICS_OFFLINE_SEND,//更新物流信息
//        ZKH_OPEN_DELIVERY_CREATE_POST,//添加发货单
        SHOP_UPDATE_BIDDING_QUANTITY,
        SHOP_TRADE_FULLINFO_RPC,
        SHOP_GET_ITEMS_LIST_RPC,
        ZKH_OPEN_DELIVERY_CONFIRM_POST,//采购单发货确认
        ZKH_OPEN_GET_DELIVERY_POST,
        ZKH_OPEN_GET_DELIVERY_DETAIL,
    );

    // 小米有品
    private $xiaomi = array(
        SHOP_LOGISTICS_OFFLINE_SEND,
        //SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,
    );
}
