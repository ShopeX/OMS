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

class omeanalysts_finder_ome_delivery_order_item
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public function __construct()
    {}

    public $addon_cols = 'order_create_time,delivery_time,order_pay_time,order_item_id,product_id,return_num,return_amount';

    public $column_sale_bn       = '销售单号';
    public $column_sale_bn_order = "3";
    public $column_sale_bn_width = "120";
    /**
     * column_sale_bn
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_sale_bn($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeSalesList = self::getOmeSalesFromOrderId($orderIdArr);
        $sale_bn      = '';
        foreach ($omeSalesList as $info) {
            if ($info['order_id'] == $orderId) {
                $sale_bn = $info['sale_bn'];
                break;
            }
        }
        return $sale_bn;
    }

    public $column_org_order_bn       = '原订单号';
    public $column_org_order_bn_order = "7";
    public $column_org_order_bn_width = "120";
    /**
     * column_org_order_bn
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_org_order_bn($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeSalesList = self::getOrderFromOrderId($orderIdArr);
        $org_order_bn = '';
        foreach ($omeSalesList as $info) {
            if ($info['order_id'] == $orderId) {
                $org_order_bn = $info['relate_order_bn'];
                break;
            }
        }
        return $org_order_bn;
    }

    public $column_order_type       = '订单类型';
    public $column_order_type_order = "8";
    public $column_order_type_width = "80";
    /**
     * column_order_type
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_order_type($row, $list)
    {
        if ($row[$this->col_prefix . 'return_amount'] > 0 || $row[$this->col_prefix . 'return_num'] > 0) {
            return '非残退货';
        } else {
            return '正常销售';
        }
    }

    public $column_order_create_time       = '订单日期';
    public $column_order_create_time_order = "9";
    public $column_order_create_time_width = "80";
    /**
     * column_order_create_time
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_order_create_time($row, $list)
    {
        return date('Y-m-d', $row[$this->col_prefix . 'order_create_time']);
    }

    public $column_sales_material_name       = '销售物料名称';
    public $column_sales_material_name_order = "13";
    public $column_sales_material_name_width = "120";
    /**
     * column_sales_material_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_sales_material_name($row, $list)
    {
        static $smList;
        if (isset($smList)) {
            return $smList[$row['sales_material_bn']]['sales_material_name'];
        }

        $smBnArr = array_column($list, 'sales_material_bn');

        $smMdl  = app::get('material')->model('sales_material');
        $smList = $smMdl->getList('sales_material_bn,sales_material_name', ['sales_material_bn|in' => $smBnArr]);
        $smList = array_column($smList, null, 'sales_material_bn');
        if (!$smList) {
            return '';
        }
        return $smList[$row['sales_material_bn']]['sales_material_name'];
    }

    public $column_goods_type_name       = '分组';
    public $column_goods_type_name_order = "15";
    public $column_goods_type_name_width = "90";
    /**
     * column_goods_type_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_goods_type_name($row, $list)
    {
        $bmIdArr = array_column($list, $this->col_prefix . 'product_id');
        $bmId    = $row[$this->col_prefix . 'product_id'];
        $bmList  = self::getMaterialByBmId($bmIdArr);

        $goods_type_name = '';
        foreach ($bmList as $info) {
            if ($info['bm_id'] == $bmId) {
                $goods_type_name = $info['goods_type_name'];
                break;
            }
        }
        return $goods_type_name;
    }

    public $column_color       = '颜色';
    public $column_color_order = "17";
    public $column_color_width = "50";
    /**
     * column_color
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_color($row, $list)
    {
        $bmIdArr = array_column($list, $this->col_prefix . 'product_id');
        $bmId    = $row[$this->col_prefix . 'product_id'];
        $bmList  = self::getMaterialByBmId($bmIdArr);

        $color = '';
        foreach ($bmList as $info) {
            if ($info['bm_id'] == $bmId) {
                $color = $info['color'];
                break;
            }
        }
        return $color;
    }

    public $column_size       = '尺码';
    public $column_size_order = "19";
    public $column_size_width = "50";
    /**
     * column_size
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_size($row, $list)
    {
        $bmIdArr = array_column($list, $this->col_prefix . 'product_id');
        $bmId    = $row[$this->col_prefix . 'product_id'];
        $bmList  = self::getMaterialByBmId($bmIdArr);

        $size = '';
        foreach ($bmList as $info) {
            if ($info['bm_id'] == $bmId) {
                $size = $info['size'];
                break;
            }
        }
        return $size;
    }

    public $column_discount       = '销售折扣';
    public $column_discount_order = "27";
    public $column_discount_width = "80";
    /**
     * column_discount
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_discount($row, $list)
    {
        return '1';
    }

    public $column_freight       = '运费';
    public $column_freight_order = "31";
    public $column_freight_width = "50";
    /**
     * column_freight
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_freight($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeOrdersList = self::getOrderFromOrderId($orderIdArr);
        $cost_freight  = '';
        foreach ($omeOrdersList as $info) {
            if ($info['order_id'] == $orderId) {
                $cost_freight = $info['cost_freight'];
                break;
            }
        }
        return $cost_freight;
    }

    public $column_pmt_describe       = '优惠原因';
    public $column_pmt_describe_order = "37";
    public $column_pmt_describe_width = "120";
    /**
     * column_pmt_describe
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_pmt_describe($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeOrdersList = self::getOrderFromOrderId($orderIdArr);
        $pmt_describe  = '';
        foreach ($omeOrdersList as $info) {
            if ($info['order_id'] == $orderId) {
                if ($info['pmt_detail']) {
                    $tmp          = array_column($info['pmt_detail'], 'pmt_describe');
                    $pmt_describe = implode(' ', $tmp);
                }
                break;
            }
        }
        return $pmt_describe;
    }

    public $column_delivery_time       = '发货日期';
    public $column_delivery_time_order = "39";
    public $column_delivery_time_width = "80";
    /**
     * column_delivery_time
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_delivery_time($row, $list)
    {
        return date('Y-m-d', $row[$this->col_prefix . 'delivery_time']);
    }

    public $column_reship_signfor_time       = '收到退货日期';
    public $column_reship_signfor_time_order = "41";
    public $column_reship_signfor_time_width = "100";
    /**
     * column_reship_signfor_time
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_reship_signfor_time($row, $list)
    {
        static $reshipList;
        if (isset($reshipList)) {
            if (isset($reshipList[$row['order_item_id']])) {
                return $reshipList[$row['order_item_id']]['outer_lastmodify'];
            } else {
                return '';
            }
        }

        $orderItemIdArr = array_column($list, $this->col_prefix . 'order_item_id');

        $reshipItemMdl  = app::get('ome')->model('reship_items');
        $reshipItemList = $reshipItemMdl->getList('reship_id,order_item_id', ['order_item_id|in' => $orderItemIdArr]);

        if ($reshipItemList) {
            $reshipList  = [];
            $reshipIdArr = array_unique(array_column($reshipItemList, 'reship_id'));

            $reshipMdl   = app::get('ome')->model('reship');
            $_reshipList = $reshipMdl->getList('reship_id,outer_lastmodify', ['reship_id|in' => $reshipIdArr]);
            $_reshipList = array_column($_reshipList, null, 'reship_id');

            foreach ($reshipItemList as $v) {
                if (isset($_reshipList[$v['reship_id']]) && $_reshipList[$v['reship_id']]['outer_lastmodify'] > 0) {
                    $reshipList[$v['order_item_id']]['outer_lastmodify'] = $_reshipList[$v['reship_id']]['outer_lastmodify'];
                }
            }
        } else {
            $reshipList = [];
        }

        if (isset($reshipList[$row['order_item_id']])) {
            return date('Y-m-d', $reshipList[$row['order_item_id']]['outer_lastmodify']);
        } else {
            return '';
        }
    }

    public $column_order_pay_time       = '收款日期';
    public $column_order_pay_time_order = "43";
    public $column_order_pay_time_width = "80";
    /**
     * column_order_pay_time
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_order_pay_time($row, $list)
    {
        return date('Y-m-d', $row[$this->col_prefix . 'order_pay_time']);
    }

    public $column_payment       = '支付方式';
    public $column_payment_order = "45";
    public $column_payment_width = "80";
    /**
     * column_payment
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_payment($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeSalesList = self::getOrderFromOrderId($orderIdArr);
        $payment      = '';
        foreach ($omeSalesList as $info) {
            if ($info['order_id'] == $orderId) {
                $payment = $info['payment'];
                break;
            }
        }
        return $payment;
    }

    // public $column_refund_sent_time       = '退款日期';
    // public $column_refund_sent_time_order = "47";
    // public $column_refund_sent_time_width = "80";
    // public function column_refund_sent_time($row, $list)
    // {
    //     return '';
    // }

    public $column_email       = '客户邮箱';
    public $column_email_order = "51";
    public $column_email_width = "100";
    /**
     * column_email
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_email($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeSalesList = self::getMemberByOrderId($orderIdArr);
        $email        = '';
        foreach ($omeSalesList as $info) {
            if ($info['order_id'] == $orderId) {
                $email = $info['email'];
                break;
            }
        }
        return $email;
    }

    public $column_member_name       = '客户姓名';
    public $column_member_name_order = "53";
    public $column_member_name_width = "120";
    /**
     * column_member_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_member_name($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeSalesList = self::getMemberByOrderId($orderIdArr);
        $member_name  = '';
        foreach ($omeSalesList as $info) {
            if ($info['order_id'] == $orderId) {
                $member_name = $info['name'];
                $is_encrypt  = kernel::single('ome_security_router', $info['shop_type'])->is_encrypt($info, 'member');
                if ($is_encrypt) {
                    $member_name = explode('>>', $info['name'])[0];
                }
                break;
            }
        }
        return $member_name;
    }

    public $column_mobile       = '客户电话';
    public $column_mobile_order = "55";
    public $column_mobile_width = "80";
    /**
     * column_mobile
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_mobile($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeSalesList = self::getMemberByOrderId($orderIdArr);
        $mobile       = '';
        foreach ($omeSalesList as $info) {
            if ($info['order_id'] == $orderId) {
                $mobile     = $info['mobile'];
                $is_encrypt = kernel::single('ome_security_router', $info['shop_type'])->is_encrypt($info, 'member');
                if ($is_encrypt) {
                    $mobile = explode('>>', $info['mobile'])[0];
                }
                break;
            }
        }
        return $mobile;
    }

    public $column_province       = '省';
    public $column_province_order = "57";
    public $column_province_width = "60";
    /**
     * column_province
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_province($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeOrdersList = self::getOrderFromOrderId($orderIdArr);
        $province      = '';
        foreach ($omeOrdersList as $info) {
            if ($info['order_id'] == $orderId) {
                list(, $mainland) = explode(':', $info['ship_area']);
                list($province)   = explode('/', $mainland);
                break;
            }
        }
        return $province;
    }

    public $column_city       = '市';
    public $column_city_order = "58";
    public $column_city_width = "80";
    /**
     * column_city
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_city($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeOrdersList = self::getOrderFromOrderId($orderIdArr);
        $city          = '';
        foreach ($omeOrdersList as $info) {
            if ($info['order_id'] == $orderId) {
                list(, $mainland) = explode(':', $info['ship_area']);
                list(, $city)     = explode('/', $mainland);
                break;
            }
        }
        return $city;
    }

    public $column_district       = '地区';
    public $column_district_order = "59";
    public $column_district_width = "60";
    /**
     * column_district
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_district($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeOrdersList = self::getOrderFromOrderId($orderIdArr);
        $district      = '';
        foreach ($omeOrdersList as $info) {
            if ($info['order_id'] == $orderId) {
                list(, $mainland)   = explode(':', $info['ship_area']);
                list(, , $district) = explode('/', $mainland);
                break;
            }
        }
        return $district;
    }

    public $column_addr       = '地址';
    public $column_addr_order = "60";
    public $column_addr_width = "120";
    /**
     * column_addr
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_addr($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeSalesList = self::getOrderFromOrderId($orderIdArr);
        $ship_addr    = '';
        foreach ($omeSalesList as $info) {
            if ($info['order_id'] == $orderId) {
                $ship_addr  = $info['ship_addr'];
                $is_encrypt = kernel::single('ome_security_router', $info['shop_type'])->is_encrypt($info, 'order');
                if ($is_encrypt) {
                    $ship_addr = explode('>>', $info['ship_addr'])[0];
                }
                break;
            }
        }
        return $ship_addr;
    }

    public $column_predict_delivery_time       = '预计发货周期';
    public $column_predict_delivery_time_order = "60";
    public $column_predict_delivery_time_width = "100";
    /**
     * column_predict_delivery_time
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_predict_delivery_time($row, $list)
    {
        // 正常销售是2，非残退货是空白
        if ($row[$this->col_prefix . 'return_amount'] > 0 || $row[$this->col_prefix . 'return_num'] > 0) {
            return '';
        } else {
            return '2';
        }
    }

    public $column_uname       = '会员ID';
    public $column_uname_order = "61";
    public $column_uname_width = "120";
    /**
     * column_uname
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_uname($row, $list)
    {
        $orderIdArr = array_column($list, 'order_id');
        $orderId    = $row['order_id'];

        $omeSalesList = self::getMemberByOrderId($orderIdArr);
        $uname        = '';
        foreach ($omeSalesList as $info) {
            if ($info['order_id'] == $orderId) {
                $uname      = $info['uname'];
                $is_encrypt = kernel::single('ome_security_router', $info['shop_type'])->is_encrypt($info, 'member');
                if ($is_encrypt) {
                    $uname = explode('>>', $info['uname'])[0];
                }
                break;
            }
        }
        return $uname;
    }

    private static function getOmeSalesFromOrderId($orderIdArr = [])
    {
        static $omeSalesList;
        if (isset($omeSalesList)) {
            return $omeSalesList;
        }

        $salesMdl     = app::get('ome')->model('sales');
        $omeSalesList = $salesMdl->getList('*', ['order_id|in' => $orderIdArr]);

        return $omeSalesList;
    }

    private static function getMaterialByBmId($bmIdArr = [])
    {
        static $bmList;
        if (isset($bmList)) {
            return $bmList;
        }

        $bmMdl  = app::get('material')->model('basic_material');
        $bmList = $bmMdl->getList('*', ['bm_id|in' => $bmIdArr]);
        $bmList = array_column($bmList, null, 'bm_id');

        $bmExtMdl = app::get('material')->model('basic_material_ext');
        $extList  = $bmExtMdl->getList('*', ['bm_id|in' => $bmIdArr]);
        $extList  = array_column($extList, null, 'bm_id');

        $goodsTypeMdl  = app::get('ome')->model('goods_type');
        $gooddTypeList = $goodsTypeMdl->getList('*');
        $gooddTypeList = array_column($gooddTypeList, null, 'type_id');

        foreach ($bmList as $bm_id => $info) {
            $tmp = [
                'goods_type_id'   => $extList[$bm_id]['cat_id'],
                'goods_type_name' => $gooddTypeList[$extList[$bm_id]['cat_id']]['name'],
                'color'           => $extList[$bm_id]['color'],
                'size'            => $extList[$bm_id]['size'],
            ];
            $bmList[$bm_id] = array_merge($bmList[$bm_id], $tmp);
        }

        return $bmList;
    }

    private static function getOrderFromOrderId($orderIdArr = [])
    {
        static $ordersList;
        if (isset($ordersList)) {
            return $ordersList;
        }

        $ordersMdl  = app::get('ome')->model('orders');
        $ordersList = $ordersMdl->getList('*', ['order_id|in' => $orderIdArr]);
        $ordersList = array_column($ordersList, null, 'order_id');

        $pmtList  = [];
        $pmtMdl   = app::get('ome')->model('order_pmt');
        $_pmtList = $pmtMdl->getList('*', ['order_id|in' => $orderIdArr]);
        foreach ($_pmtList as $_v) {
            if (!isset($pmtList[$_v['order_id']])) {
                $pmtList[$_v['order_id']] = [];
            }
            $pmtList[$_v['order_id']][] = $_v;
        }

        foreach ($ordersList as $orderId => $info) {
            $tmp = [
                'pmt_detail' => $pmtList[$orderId] ? $pmtList[$orderId] : [],
            ];
            $ordersList[$orderId] = array_merge($ordersList[$orderId], $tmp);
        }

        return $ordersList;
    }

    private static function getMemberByOrderId($orderIdArr = [])
    {
        static $memberList;
        if (isset($memberList)) {
            return $memberList;
        }

        $ordersList  = self::getOrderFromOrderId($orderIdArr);
        $memberIdArr = array_column($ordersList, 'member_id');

        $membersMdl  = app::get('ome')->model('members');
        $_memberList = $membersMdl->getList('*', ['member_id|in' => $memberIdArr]);
        $_memberList = array_column($_memberList, null, 'member_id');

        $memberList = [];
        foreach ($ordersList as $orderId => $info) {
            if (isset($_memberList[$info['member_id']])) {
                $memberList[$orderId] = $_memberList[$info['member_id']];

                $memberList[$orderId]['order_id']  = $orderId;
                $memberList[$orderId]['shop_type'] = $info['shop_type'];
            }
        }
        return $memberList;
    }

}
