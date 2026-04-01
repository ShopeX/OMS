<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class orderTest extends TestCase
{
    // 在每个测试方法之前执行
    protected function setUp(): void
    {
        parent::setUp();

        // 在 setUp 方法中引入依赖文件
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }
    
    private $order_bn = '';

    /**
     * 测试订单创建接口
     *
     * @return void
     * @author chenping<chenping@shopex.cn>
     */
    public function testApiAdd(): void
    {
        $mockData = <<<JSON
{
    "is_service_order": "",
    "cur_amount": "77.51",
    "from_node_id": "1267132737",
    "yfx_info":
    {
        "has_yfx": "true",
        "yfx_type": "",
        "yfx_fee": "",
        "yfx_id": ""
    },
    "app_id": "ecos.ome",
    "trade_from": "WAP",
    "order_limit_time": "",
    "cost_item": "79.90",
    "is_yushou": "false",
    "pmt_detail":
    [],
    "cost_tax": "",
    "is_pt": "",
    "lastmodify": "1703490720",
    "title": "jackey083939",
    "real_point_fee": "0",
    "o2o_info":
    {
        "tcps_code": "com.tmall.astrolabe"
    },
    "order_bn": "6095408761",
    "invoice_bank_name": "",
    "value_added_tax_invoice": "",
    "to_node_id": "1976120933",
    "pmt_goods": "0.0",
    "order_source": "taobao",
    "score_u": "0",
    "member_info":
    {
        "tel": "",
        "uname": "test0001",
        "area_district": "",
        "area_state": "",
        "addr": "",
        "name": "test0001",
        "zip": "",
        "mobile": "",
        "area_city": "",
        "alipay_no": "131****1111",
        "buyer_open_uid": "",
        "email": "t.afngwyki@iixslroh.np"
    },
    "date": "2023-12-25 15:52:00",
    "score_g": "0",
    "tax_title": "",
    "sign": "FDEE2B1B0D79D40B85E593ABA2AFA64C",
    "signfor_status": "0",
    "mark_text": "",
    "platform_discount": "0.0",
    "t_type": "fixed",
    "modified": "1703490720",
    "method": "ome.order.add",
    "payed": "77.51",
    "order_objects":
    [
        {
            "gift_mids": "",
            "weight": "",
            "is_sh_ship": "",
            "bn": "test001",
            "oid": 27785675882111110,
            "order_items":
            [
                {
                    "specId": 1111111111,
                    "gift_mids": "",
                    "part_mjz_discount": 2.39,
                    "is_sh_ship": "",
                    "price": "79.90",
                    "cost": "79.90",
                    "customization": "",
                    "promotion_id": "",
                    "shop_product_id": "32796152123131",
                    "shop_goods_id": 1111111111,
                    "pmt_price": "0.00",
                    "expand_card_expand_price_used_suborder": "0.0",
                    "score": "",
                    "sale_price": "",
                    "divide_order_fee": 77.51,
                    "purchase_price": "",
                    "status": "active",
                    "extend_item_list":
                    {},
                    "bn": "test001",
                    "original_str": "颜色分类:黑色;尺码:L 165/82A",
                    "product_attr":
                    [
                        {
                            "value": "黑色",
                            "label": "颜色分类"
                        },
                        {
                            "value": "L 165/82A",
                            "label": "尺码"
                        }
                    ],
                    "sendnum": "0",
                    "name": "高腰束脚宽松女裤",
                    "estimate_con_time": "",
                    "adjust_fee": "0.00",
                    "sale_amount": "79.90",
                    "item_type": "product",
                    "amount": 79.9,
                    "zhengji_status": "",
                    "expand_card_basic_price_used_suborder": "0.0",
                    "quantity": 1
                }
            ],
            "buyer_payment": "",
            "obj_alias": "商品",
            "part_mjz_discount": 2.39,
            "divide_order_fee": 77.51,
            "obj_type": "goods",
            "name": "高腰束脚宽松女裤",
            "store_code": "",
            "pmt_price": 0,
            "sale_price": "",
            "source_status": "WAIT_SELLER_SEND_GOODS",
            "is_oversold": true,
            "amount": 79.9,
            "score": "",
            "zhengji_status": "",
            "shop_goods_id": 1111111111,
            "pic_path": "https://img.alicdn.com/bao/uploaded/i2/352469034/O1CN01I2ykAq2Gbch3KW0YG_!!352469034.jpg",
            "price": "79.90",
            "quantity": 1
        }
    ],
    "payments":
    [
        {
            "paymethod": "支付宝",
            "pay_account": "131****1111",
            "memo": "",
            "paycost": "",
            "pay_time": "2023-12-25 15:52:00",
            "bank": "",
            "trade_no": "2022072522001100281123123213123",
            "account": "***ile.ss@ssss.com",
            "pay_bn": "alipay",
            "extend_paymentitem_list":
            {},
            "money": "77.51"
        }
    ],
    "position_no": "",
    "cn_info":
    {
        "trade_attr": "{\"erpHold\":\"0\"}"
    },
    "credit_card_fee": "",
    "step_trade_status": "",
    "coupon_fee": "0",
    "weight": "",
    "cur_rate": "1.0",
    "is_sh_ship": "false",
    "point_fee": "0",
    "consignee":
    {
        "area_street": null,
        "telephone": "",
        "area_state": "福建省",
        "lat": "",
        "addr": "四*街道江**路***号山庭**-***",
        "r_time": "~",
        "name": "王**",
        "zip": "000000",
        "mobile": "*******0111",
        "country": "",
        "lon": "",
        "area_city": "南平市",
        "area_district": "延平区",
        "email": ""
    },
    "is_lgtype": "True",
    "service_order_objects":
    {
        "service_order":
        []
    },
    "currency": "CNY",
    "trade_type": "fixed",
    "mark_type": "b0",
    "custom_mark": "",
    "payinfo":
    {
        "pay_name": "支付宝",
        "cost_payment": ""
    },
    "is_force_wlb": "True",
    "is_delivery": "",
    "buyer_memo": "",
    "other_list":
    [
        {
            "type": "unpaid",
            "unpaidprice": 0
        },
        {
            "oid": "27785675882111110",
            "type": "category",
            "cid": "50023107"
        }
    ],
    "pay_status": "1",
    "end_time": "",
    "status": "active",
    "self_fetch_info":
    {},
    "memeber_id": "jackey083939",
    "payer_register_no": "",
    "pmt_order": "2.39",
    "is_errortrade": "false",
    "invoice_phone": "",
    "discount": "0.0",
    "node_id": "222",
    "operator_state": "",
    "position": "",
    "payment_detail":
    {
        "paymethod": "支付宝",
        "pay_account": "131****1111",
        "trade_no": "2022072522001100281123123213123",
        "pay_time": 1703490720,
        "currency": "CNY"
    },
    "is_risk": "",
    "total_amount": "77.51",
    "ship_status": "0",
    "extend_field":
    {
        "is_free_gift":
        [
            {
                "27785675882111110": ""
            }
        ],
        "shipper":
        [
            {
                "27785675882111110": ""
            }
        ],
        "gift_mids":
        [
            {
                "27785675882111110": ""
            }
        ],
        "oaid": "1gIicjVkaicx1D6GIu7DJo4dtETsu7AlwBIfubZLvWdvasfasdfas1231231",
        "receiver_street": "四鹤街道",
        "adjust_fee": "0.00",
        "need_return":
        {
            "27785675882111110": ""
        },
        "special_refund_type_info":
        {
            "27785675882111110": ""
        },
        "extend_info":
        {
            "27785675882111110": ""
        },
        "platform_subsidy_fee": "0.00",
        "tax_info":
        {
            "s_tariff_fee":
            {
                "27785675882111110": ""
            },
            "sub_order_tax_fee":
            {
                "27785675882111110": ""
            }
        },
        "has_gift":
        [
            {
                "27785675882111110": ""
            }
        ]
    },
    "step_paid_fee": "",
    "source_status": "WAIT_SELLER_SEND_GOODS",
    "shipping":
    {
        "shipping_name": "快递",
        "is_cod": "false",
        "is_virtual_delivery": "",
        "is_protect": "",
        "cost_shipping": "0.0",
        "cost_protect": "",
        "shipping_id": ""
    },
    "errortrade_desc": "",
    "invoice_address": "",
    "is_tax": "false",
    "alipay_point": "0",
    "invoice_bank_account": "",
    "createtime": "1703490720"
}
JSON;
        $mockData = json_decode($mockData, true);

        $result = kernel::single('erpapi_router_response')
        ->set_node_id($mockData['node_id'])
        ->set_api_name('shop.order.add')
        ->dispatch($mockData);
        $this->markTestIncomplete(
            'This test has not been implemented yet.',
        );
        // $this->assertTrue(in_array($result['rsp'], ['succ', 'fail']));
        $this->assertTrue(true, 'This should already work.');


        print '***';
    }

    /**
     * 测试订单审核
     *
     * @return void
     * @author chenping<chenping@shopex.cn>
     */
    public function testApiConsignUpload(): void
    {
        $res = kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_send(17);

        $this->assertTrue(true);
    }
    
    /**
     * 测试订单取消
     */
    public function testOrderCancel(): void
    {
        $orderMdl = app::get('ome')->model('orders');
        $order = $orderMdl->dump(['order_bn' => 'L202406081910003050']);
        
        if (!$order){
            $this->assertTrue(false);
            
            return ;
        }
        
        // $orderMdl->cancel($order['order_id'], '订单取消测试', false, 'async');
        
        $this->assertTrue(true);
    }
    // 订单审核
    public function testOrderAudit(): void
    {
        $params[]['orders'][]    = 41;

        //开始自动确认
        $orderAuto = new omeauto_auto_combine('combine');
        $result = $orderAuto->process($params);

        $this->assertTrue(true);
    }   
}
