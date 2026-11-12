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
 * 物流包裹明细列表Finder类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_finder_delivery_package
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_delivery = '物流包裹明细列表';
    
    var $addon_cols = 'delivery_id,logi_no,bn,product_id';
    
    public $_deliveryObj = null;

    static $_orderList = null;
    static $_refundList = null;
    static $_reshipList = null;
    static $_deliveryPackageList = null;
    static $_deliveryList = null;
    static $_orderItemList = null;
    static $_orderObjectsList = null;
    static $_channelChannelList = null;
    static $_basicMaterial = null;
    
    public $pay_status_list = null;
    public $refund_status_list = null;
    public $refund_refer_list = null;
    public $ship_status_list = null;
    
    function __construct()
    {
        $this->_deliveryObj = app::get('ome')->model('delivery');
        
        $this->pay_status_list = array (
                0 => '未支付',
                1 => '已支付',
                2 => '处理中',
                3 => '部分付款',
                4 => '部分退款',
                5 => '全额退款',
                6 => '退款申请中',
                7 => '退款中',
                8 => '支付中',
        );
        
        $this->refund_status_list = array(
                0 => '未审核',
                1 => '审核中',
                2 => '已接受申请',
                3 => '已拒绝',
                4 => '已退款',
                5 => '退款中',
                6 => '退款失败',
        );
        
        $this->refund_refer_list = array(
                0 => '普通退款',
                1 => '售后退款',
        );

        $this->ship_status_list = array(
                0 => '未发货',
                1 => '已发货',
                2 => '部分发货',
                3 => '部分退货',
                4 => '已退货',
        );

        //平台售后状态
        $this->platform_status_list = array(
            '6' => '待商家处理',
            '7' => '待买家退货',
            '11' => '待商家收货',
            '12' => '商家同意退款',
            '27' => '拒绝售后申请',
            '28' => '售后关闭',
            '29' => '退货后商家拒绝',
        );

        //物流跟踪状态
        $this->logi_status_list = array(
            '0' => '无',
            '1' => '已揽收',
            '2' => '在途中',
            '3' => '已签收',
            '4' => '退件/问题件',
            '5' => '待取件',
            '6' => '待派件',
        );
        
    }

    /**
     * 暂时未考虑性能，后需需要优化
     * @param $list
     */

    public function _getDeliveryList($list)
    {
        if(empty(self::$_orderList)){
            $delivery_ids = array();
            $cancel_dly_ids = array();
            $product_ids = array();
            
            //list
            foreach ($list as $key => $val)
            {
                //delivery_id
                $delivery_id = $val[$this->col_prefix.'delivery_id'];
                $delivery_ids[$delivery_id] = $delivery_id;
                
                //product_id
                $product_id = $val[$this->col_prefix.'product_id'];
                $product_ids[$product_id] = $product_id;
                
                //取消状态
                if($val['status'] == 'cancel'){
                    $cancel_dly_ids[$delivery_id] = $delivery_id;
                }
            }

            //关联订单
            $order_ids = array();
            $sql = "SELECT a.delivery_id, b.order_id,b.order_bn,b.pay_status,b.ship_status,b.paytime,b.source_status,b.ship_status,b.createtime FROM sdb_ome_delivery_order AS a 
                    LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.delivery_id IN(". implode(',', $delivery_ids) .")";
            $tempList = $this->_deliveryObj->db->select($sql);
            if($tempList){
                foreach ($tempList as $key => $val)
                {
                    $delivery_id = $val['delivery_id'];
                    $order_id = $val['order_id'];

                    self::$_orderList[$delivery_id] = $val;

                    $order_ids[$order_id] = $order_id;
                }
            }

            //订单相关信息
            if($order_ids){
                //退款申请单类型
                $sql = "SELECT apply_id,order_id,status,refund_refer,refunded FROM sdb_ome_refund_apply WHERE order_id IN(". implode(',', $order_ids) .")";
                $tempList = $this->_deliveryObj->db->select($sql);
                if($tempList){
                    foreach ($tempList as $key => $val)
                    {
                        $order_id = $val['order_id'];

                        self::$_refundList[$order_id] = $val;
                    }
                }
                
                //订单关联退换货单，取平台售后原始状态
                $sql = "SELECT order_id,platform_status FROM sdb_ome_reship WHERE order_id IN(". implode(',', $order_ids) .")";
                $tempList = $this->_deliveryObj->db->select($sql);
                if($tempList){
                    foreach ($tempList as $key => $val)
                    {
                        $order_id = $val['order_id'];
                        
                        self::$_reshipList[$order_id] = $val;
                    }
                }
                
                //发货单明细关联订单明细，商品实付价格
                $sql = "SELECT order_id,amount,bn,divide_order_fee,price FROM sdb_ome_order_items WHERE order_id IN(". implode(',', $order_ids) .")";
                $tempList = $this->_deliveryObj->db->select($sql);
                if($tempList){
                    foreach($tempList as $key => $val){
                        $order_id = $val['order_id'];
                        $bn = $val['bn'];
                
                        self::$_orderItemList[$order_id.'_'.$bn] = $val;
                    }
                }
                
                //发货单明细关联订单对象表，达人ID，达人名称
                $sql = "SELECT author_id,author_name,order_id FROM sdb_ome_order_objects WHERE order_id IN(". implode(',', $order_ids) .")";
                $tempList = $this->_deliveryObj->db->select($sql);
                if($tempList){
                    foreach($tempList as $key => $val){
                        $order_id = $val['order_id'];
                        
                        //防止手工编辑订单删除和添加新商品多了空值
                        if(empty($val['author_id'])){
                            continue;
                        }
                        
                        self::$_orderObjectsList[$order_id] = $val;
                    }
                }
                
            }
            
            //发货单信息
            $branch_ids = array();
            if($delivery_ids){
                //发货单列表关联明细表，渠道ID，基础物料名称，商品采购价格
                $sql = "SELECT d.delivery_id,d.wms_channel_id,di.bn,di.product_name,di.purchase_price,d.logi_id,d.logi_name,d.delivery_time,d.logi_status,di.number,d.branch_id 
                        FROM sdb_ome_delivery_items di LEFT JOIN sdb_ome_delivery AS d ON d.delivery_id = di.delivery_id WHERE di.delivery_id IN(". implode(',', $delivery_ids) .")";
                $tempList = $this->_deliveryObj->db->select($sql);
                if($tempList){
                    foreach ($tempList as $key => $val)
                    {
                        $delivery_id = $val['delivery_id'];
                        $bn = $val['bn'];
                        
                        self::$_deliveryList[$delivery_id.'_'.$bn] = $val;
                        
                        $branch_id = $val['branch_id'];
                        $branch_ids[$branch_id] = $branch_id;
                    }
                }
                
                //获取发货单明细为子单号的数据
                $sql = "SELECT d.delivery_id,d.delivery_bn,cde.original_delivery_bn FROM sdb_ome_delivery d 
                        LEFT JOIN sdb_console_delivery_extension AS cde ON d.delivery_bn = cde.delivery_bn 
                        WHERE  d.delivery_id IN(". implode(',', $delivery_ids) .")";
                $tempList = $this->_deliveryObj->db->select($sql);
                if ($tempList) {
                    foreach ($tempList as $key => $val) {
                        self::$_deliveryPackageList[$val['delivery_id']] = $val;
                    }
                }
            }

            //发货单关联仓库关联商家账号
            if($branch_ids){
                $sql = "SELECT b.branch_id,cc.crop_config FROM sdb_ome_branch b 
                        LEFT JOIN sdb_channel_channel AS cc ON b.wms_id = cc.channel_id
                        WHERE b.branch_id IN(". implode(',', $branch_ids) .")
                        ";
                $tempList = $this->_deliveryObj->db->select($sql);
                if($tempList){
                    foreach($tempList as $key => $val){
                        $branch_id = $val['branch_id'];
                        self::$_channelChannelList[$branch_id] = $val;
                    }
                }
            }

            //基础物料包装单位
            if($product_ids){
                $materialExtObj = app::get('material')->model('basic_material_ext');
                
                //扩展信息
                $tempList = $materialExtObj->getList('bm_id,unit', array('bm_id'=>$product_ids));
                
                self::$_basicMaterial = array_column($tempList, null, 'bm_id');
            }
            unset($delivery_ids, $tempList, $order_ids, $material_bns);
        }
    }


    var $column_order_bn = '平台订单号';
    var $column_order_bn_width = 180;
    var $column_order_bn_order = 25;
    function column_order_bn($row, $list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $orderInfo = self::$_orderList[$delivery_id];
        
        return $orderInfo['order_bn'];
    }
    
    var $column_delivery_bn = 'OMS发货单号';
    var $column_delivery_bn_width = 150;
    var $column_delivery_bn_order = 21;
    function column_delivery_bn($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $deliveryInfo = $this->_deliveryObj->dump(array('delivery_id'=>$delivery_id), 'delivery_bn');
        
        return $deliveryInfo['delivery_bn'];
    }
    
    var $column_pay_status = '平台订单付款状态';
    var $column_pay_status_width = 150;
    var $column_pay_status_order = 26;
    function column_pay_status($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        
        $orderInfo = self::$_orderList[$delivery_id];
        
        return $this->pay_status_list[$orderInfo['pay_status']];
    }
    
    var $column_refund_status = '平台订单退款状态';
    var $column_refund_status_width = 150;
    var $column_refund_status_order = 27;
    function column_refund_status($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        
        $order_id = self::$_orderList[$delivery_id]['order_id'];
        if($order_id){
            if(self::$_refundList[$order_id]){
                $status = self::$_refundList[$order_id]['status'];
                return $this->refund_status_list[$status];
            }
        }
        
        return '';
    }
    
    var $column_refund_type = '订退款类型';
    var $column_refund_type_width = 120;
    var $column_refund_type_order = 28;
    function column_refund_type($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        
        $order_id = self::$_orderList[$delivery_id]['order_id'];
        if($order_id){
            if(self::$_refundList[$order_id]){
                $refund_refer = self::$_refundList[$order_id]['refund_refer'];
                return $this->refund_refer_list[$refund_refer];
            }
        }
        return '';
    }

    var $column_paytime = '平台订单支付时间';
    var $column_paytime_width = 150;
    var $column_paytime_order = 30;
    function column_paytime($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $orderInfo = self::$_orderList[$delivery_id];
        return isset($orderInfo['paytime']) ? date('Y-m-d H:i:s',$orderInfo['paytime']) : '';
    }
    
    var $column_createtime = '订单下单时间';
    var $column_createtime_width = 130;
    var $column_createtime_order = 32;
    function column_createtime($row, $list)
    {
        $this->_getDeliveryList($list);
        
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $orderInfo = self::$_orderList[$delivery_id];
        
        return isset($orderInfo['createtime']) ? date('Y-m-d H:i:s', $orderInfo['createtime']) : '';
    }

    var $column_source_status = '平台订单原始状态';
    var $column_source_status_width = 150;
    var $column_source_status_order = 30;
    function column_source_status($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];

        $orderInfo = self::$_orderList[$delivery_id];
        return kernel::single('ome_order_func')->get_source_status($orderInfo['source_status'],'');
    }

    var $column_ship_status = '平台订单发货状态';
    var $column_ship_status_width = 150;
    var $column_ship_status_order = 30;
    function column_ship_status($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $orderInfo = self::$_orderList[$delivery_id];
        return $this->ship_status_list[$orderInfo['ship_status']];
    }

    var $column_platform_status = '平台售后原始状态';
    var $column_platform_status_width = 150;
    var $column_platform_status_order = 30;
    function column_platform_status($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $order_id = self::$_orderList[$delivery_id]['order_id'];
        if($order_id){
            if(self::$_reshipList[$order_id]){
                $platform_status = self::$_reshipList[$order_id]['platform_status'];
                return $this->platform_status_list[$platform_status];
            }
        }
        return '';
    }

    var $column_wms_channel_id = '渠道ID';
    var $column_wms_channel_id_width = 50;
    var $column_wms_channel_id_order = 30;
    function column_wms_channel_id($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($delivery_id){
            if(self::$_deliveryList[$delivery_id.'_'.$bn]){
                return self::$_deliveryList[$delivery_id.'_'.$bn]['wms_channel_id'];
            }
        }
        return '';
    }

    var $column_product_name = '基础物料名称';
    var $column_product_name_width = 150;
    var $column_product_name_order = 30;
    function column_product_name($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($delivery_id){
            if(self::$_deliveryList[$delivery_id.'_'.$bn]){
                return self::$_deliveryList[$delivery_id.'_'.$bn]['product_name'];
            }
        }
        return '';
    }

    var $column_amount = '商品实付价格';
    var $column_amount_width = 150;
    var $column_amount_order = 30;
    function column_amount($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $order_id = self::$_orderList[$delivery_id]['order_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($order_id){
            if(self::$_orderItemList[$order_id.'_'.$bn]){
                if($row['is_wms_gift'] == 'true') return '0.00';
                return self::$_orderItemList[$order_id.'_'.$bn]['divide_order_fee'];
            }
        }
        return '';
    }

    var $column_purchase_price = '商品采购价格';
    var $column_purchase_price_width = 150;
    var $column_purchase_price_order = 30;
    function column_purchase_price($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($delivery_id){
            if(self::$_deliveryList[$delivery_id.'_'.$bn]){
                if($row['is_wms_gift'] == 'true') return '0.00';
                return self::$_deliveryList[$delivery_id.'_'.$bn]['purchase_price'];
            }
        }
        return '';
    }

    var $column_sum_purchase_price = 'WMS采购小计';
    var $column_sum_purchase_price_width = 150;
    var $column_sum_purchase_price_order = 30;
    function column_sum_purchase_price($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($delivery_id){
            if(self::$_deliveryList[$delivery_id.'_'.$bn]){
                $purchase_price = self::$_deliveryList[$delivery_id.'_'.$bn]['purchase_price'];
                $number = self::$_deliveryList[$delivery_id.'_'.$bn]['number'];
                return bcmul($purchase_price,$number,2);
            }
        }
        return '';
    }

    var $column_author_id = '达人ID';
    var $column_author_id_width = 50;
    var $column_author_id_order = 30;
    function column_author_id($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $order_id = self::$_orderList[$delivery_id]['order_id'];
        if($order_id){
            return self::$_orderObjectsList[$order_id]['author_id'];
        }
    }

    var $column_author_name = '达人名称';
    var $column_author_name_width = 50;
    var $column_author_name_order = 30;
    function column_author_name($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $order_id = self::$_orderList[$delivery_id]['order_id'];
        if($order_id){
            return self::$_orderObjectsList[$order_id]['author_name'];
        }
    }

    var $column_father_package_bn = '京东父单号';
    var $column_father_package_bn_width = 120;
    var $column_father_package_bn_order = 30;
    function column_father_package_bn($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        if (isset(self::$_deliveryPackageList[$delivery_id])) {
            if (self::$_deliveryPackageList[$delivery_id]['original_delivery_bn'] != $row['package_bn']) {
                return self::$_deliveryPackageList[$delivery_id]['original_delivery_bn'];
            }
        }
        return ' - ';
    }

    var $column_crop_config = '店铺下单账号';
    var $column_crop_config_width = 120;
    var $column_crop_config_order = 30;
    function column_crop_config($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        if($delivery_id){
            $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
            $branch_id = self::$_deliveryList[$delivery_id.'_'.$bn]['branch_id'];
            if(self::$_channelChannelList[$branch_id]){
                $crop_config = unserialize(self::$_channelChannelList[$branch_id]['crop_config']);
                if($crop_config){
                    return $crop_config['pin'];
                }
            }
        }
        return '';
    }

    /* var $column_delivery_time = 'WMS包裹发货时间';
    var $column_delivery_time_width = 150;
    var $column_delivery_time_order = 30;
    function column_delivery_time($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($delivery_id){
            if(self::$_deliveryList[$delivery_id.'_'.$bn]){
                $delivery_time = self::$_deliveryList[$delivery_id.'_'.$bn]['delivery_time'];
                if($delivery_time){
                    return date('Y-m-d H:i:s',$delivery_time);
                }
            }
        }
        return '';
    } */

    var $column_logi_name = '物流公司';
    var $column_logi_name_width = 100;
    var $column_logi_name_order = 30;
    function column_logi_name($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($delivery_id && $row[$this->col_prefix.'logi_no']){
            if(self::$_deliveryList[$delivery_id.'_'.$bn]){
                return self::$_deliveryList[$delivery_id.'_'.$bn]['logi_name'];
            }
        }
        return '';
    }

    var $column_logi_status = 'WMS包裹配送状态';
    var $column_logi_status_width = 150;
    var $column_logi_status_order = 30;
    function column_logi_status($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($delivery_id){
            if(self::$_deliveryList[$delivery_id.'_'.$bn]){
                $logi_status = self::$_deliveryList[$delivery_id.'_'.$bn]['logi_status'];
                return $this->logi_status_list[$logi_status];
            }
        }
        return '';
    }

    var $column_unit = '计价单位';
    var $column_unit_width = 150;
    var $column_unit_order = 100;
    function column_unit($row,$list)
    {
        $this->_getDeliveryList($list);
        $product_id = $row[$this->col_prefix.'product_id'];
        if($product_id){
            if(self::$_basicMaterial[$product_id]){
                return self::$_basicMaterial[$product_id]['unit'];
            }
        }
        return '';
    }

    var $column_price = '商品单价';
    var $column_price_width = 150;
    var $column_price_order = 30;
    function column_price($row,$list)
    {
        $this->_getDeliveryList($list);
        $delivery_id = $row[$this->col_prefix.'delivery_id'];
        $order_id = self::$_orderList[$delivery_id]['order_id'];
        $bn = ($row['bn'] ? $row['bn'] : $row[$this->col_prefix.'bn']);
        if($order_id){
            if(self::$_orderItemList[$order_id.'_'.$bn]){
                return self::$_orderItemList[$order_id.'_'.$bn]['price'];
            }
        }
        return '';
    }
}
?>