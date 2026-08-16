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
 * 发货单推送
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_matrix_qimen_request_delivery extends erpapi_wms_request_delivery
{
    private $_shop_type_mapping = array(
        'taobao'    => 'TB',
        'paipai'    => 'PP',
        '360buy'    => 'JD',
        'yihaodian' => 'YHD',
        'qq_buy'    => 'QQ',
        'dangdang'  => 'DD',
        'amazon'    => 'AMAZON',
        'yintai'    => 'YT',
        'vjia'      => 'FK',
        'alibaba'   => '1688',
        'suning'    => 'SN',
        'gome'      => 'GM',
        'mogujie'   => 'MGJ',
        'luban' => 'DY',
        'kuaishou' => 'KS',
        'alibaba4ascp' => 'TM',
        'pinduoduo' => 'PDD',
        'tmall'     => 'TM',
        'guomei'    => 'GM',
        'vop'       => 'WPH',
        'youzan'    => 'YZ',
        'haoshiqi'  => 'HSQ',
        'gegejia'   => 'GGJ',
        'xhs'       => 'XHS',
        'weimobv'   => 'WM',
        'weimobr'   => 'WM',
        'xiaomi'    => 'XMYP',
        'dewu'      => 'DW',
        'wxshipin'  => 'WXSPHXD',
        'aikucun'   => 'AKC',
    );

    public function delivery_cancel($sdf){
        $delivery_bn = $sdf['outer_delivery_bn'];
        $oDelivery_extension = app::get('console')->model('delivery_extension');
        $dextend = $oDelivery_extension->dump(array('delivery_bn'=>$delivery_bn)); 


        $title = $this->__channelObj->wms['channel_name'].'发货单取消';

        $params = array(
            'order_type'     => 'OUT_SALEORDER',
            'out_order_code' => $delivery_bn,
            'warehouse_code' => $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']),
            'order_id'       => $dextend['original_delivery_bn'],
        );
        if (isset($sdf['owner_code'])) {
            $params['ownerCode'] = $sdf['owner_code'];
        }

        return $this->__caller->call(WMS_ORDER_CANCEL, $params, null, $title,10,$delivery_bn);
    }

    protected function _needEncryptOriginData($sdf) {
        $wmsAppKey = $this->__channelObj->channel['addon']['wms_appkey'];
        if (in_array($wmsAppKey, ['23087164','23012571','23178000','23013186','23019120','23797486','30155871','qimen','24974606','25076223','23370043','27714490','25934812','23212523','29303656','21363512']) && $sdf['shop_type'] == 'luban') {
            return true;
        }
        return parent::_needEncryptOriginData($sdf);
    }
    
    public function delivery_create($sdf){
        // 平台收货人信息是否密文
        //@todo：qimen路由下面会先过滤掉收货人信息中@hash，导致父类方法中无法判断为密文;
        if(kernel::single('ome_security_router',$sdf['shop_type'])->is_encrypt($sdf,'delivery')) {
            $this->is_platform_encrypt = true;
        }

        //oaid
        if(in_array($sdf['shop_type'], array('taobao','360buy', 'jd', 'wxshipin')) && $sdf['encrypt_source_data']['oaid']) {
            $sdf['oaid'] = $sdf['encrypt_source_data']['oaid'];

            // consignee
            foreach ($sdf['consignee'] as $dk => $dv) {
                if(is_string($dv) && $index = strpos($dv , '>>')) {
                    $sdf['consignee'][$dk] = substr($dv , 0, $index);
                }
            }
        }

        // alibaba解密用origin_caid_field，可参考ome_security_alibaba->get_encrypt_body
        if(in_array($sdf['shop_type'], array('alibaba')) && $sdf['encrypt_source_data']['origin_caid_field']) {
            $sdf['oaid'] = $sdf['encrypt_source_data']['origin_caid_field'];

            // consignee
            foreach ($sdf['consignee'] as $dk => $dv) {
                if(is_string($dv) && $index = strpos($dv , '>>')) {
                    $sdf['consignee'][$dk] = substr($dv , 0, $index);
                }
            }
        }

        if( in_array($sdf['shop_type'], array('taobao')) ) {
            foreach ($sdf['consignee'] as $dk => $dv) {
                if(is_string($dv) && $index = strpos($dv , '>>')) {
                    $sdf['consignee'][$dk] = substr($dv , 0, $index);
                    
                    //获取订单收货人信息上的oaid
                    if ( !$sdf['oaid'] ) {
                        $sdf['oaid'] = substr($dv, $index+2, -5);
                    }
                }
            }
        }


        if (in_array($sdf['shop_type'], array('xhs')) && $sdf['extend_field']['openAddressId']) {


            $sdf['oaid'] = $sdf['extend_field']['openAddressId'];
        }
        //  有些WMS还不支持oaid取号，所以也需要把密文推给他们
        if(in_array($sdf['shop_type'], array('xhs')) && $sdf['encrypt_source_data'] && $sdf['encrypt_source_data']['openAddressId']){
            $sdf['oaid'] = $sdf['encrypt_source_data']['openAddressId'];
        }
        return parent::delivery_create($sdf);
    }

    protected function _format_delivery_create_params($sdf)
    {
        $appkey = $this->__channelObj->wms['addon']['wms_appkey'];

        $oaid = $sdf['oaid'];
        $params = parent::_format_delivery_create_params($sdf);
        $params['warehouse_code'] = $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']);
        $shopObj    =   app::get('ome')->model('shop');
        // 发货人信息
        $shop = $shopObj->dump($sdf['shop_id']);
        $area = explode(':',$shop['area']);
        list($shipper_state,$shipper_city,$shipper_area) = explode('/',$area[1]);

        $params['shipper_name']    = $shop['default_sender'];
        $params['shipper_mobile']  = $shop['mobile'];
        $params['shipper_state']   = $shipper_state;
        $params['shipper_city']    = $shipper_city;
        $params['shipper_district'] = $shipper_area;
        $params['shipper_address'] = $shop['addr'];
        $params['shipper_zip'] = $shop['zip'];
        $params['order_flag']      = '';

        //泉峰的单独处理
        if (base_shopnode::node_id('ome') == '1081190530') {
            //订单来源平台
            $order_source = $this->_shop_type_mapping[$sdf['shop_type']];
            $params['order_source'] = ($order_source ? $order_source : $params['order_source']);
        } elseif (in_array($sdf['shop_type'], ['website', 'website_d1m', 'website_v2'])) {
            $shopInfo               = $shopObj->db_dump(array('shop_id' => $sdf['shop_id']), 'shop_id,shop_type_alias,shop_type');
            $params['order_source'] = $shopInfo['shop_type_alias'] ? $shopInfo['shop_type_alias'] : 'OTHER';
        } else {
            $order_source = $this->_shop_type_mapping[$sdf['shop_type']];
            $params['order_source'] = $order_source && $sdf['createway'] == 'matrix' ?  $order_source : 'OTHER';

            // 补发订单，如果关联的原订单是平台订单，则使用平台标识
            if($sdf['order_type'] == 'bufa' && $sdf['relate_order_bn']){
                // 获取关联的原订单信息
                $relateOrderInfo = app::get('ome')->model('orders')->dump(array('order_bn'=>$sdf['relate_order_bn']), 'order_id,shop_type,createway');
                if($relateOrderInfo && $relateOrderInfo['createway'] == 'matrix'){
                    $relate_order_source = $this->_shop_type_mapping[$relateOrderInfo['shop_type']];

                    // order_source
                    $params['order_source'] = $relate_order_source ?  $relate_order_source : $params['order_source'];
                }
            }

            //订单来源标识
            if (($sdf['shop_type'] == 'taobao' && $sdf['shop_id'] && $sdf['createway'] == 'matrix') || ($sdf['order_source'] == 'platformexchange' && $order_source)) {
                //矩阵下发的淘宝订单 或 平台换货生成的新订单
                $params['order_source'] = $order_source;
                //判断是不是天猫
                $shopInfo = $shopObj->dump(array('shop_id'=>$sdf['shop_id'],'tbbusiness_type'=>'B'));
                if ($shopInfo) {
                    $params['order_source'] = 'TM';
                }
            }elseif ($sdf['platform_encrypt'] == true && $order_source){
                //平台加密订单
                $params['order_source'] = $order_source;
            }
        }

        if($sdf['order_source'] == 'platformexchange' && $sdf['shop_type']=='wxshipin'){
            $params['order_source'] = 'OTHER';
        }
        // 得物品牌直发需要处理一些参数，因为品牌直发不支持合单，所以只需要判断订单数组的第一组就好
        $shopType = $shop['shop_type'];
        if($shopType == 'dewu') {
            if(isset($sdf['order_bool_type']) && kernel::single('ome_order_bool_type')->isDWBrand($sdf['order_bool_type'])) {
                $params['order_source']      = 'DWZF';
                // $params['order_source_name'] = '得物直发';
            }
        }

        $order_flag = array();
        // 到付订单
        if ($params['is_cod'] == 'true') {
            $order_flag[] = 'COD';
        }
        $extend_props = [
            'businessCategory' => $sdf['business_category'],
        ];
        //预打包
        if($sdf['prepackage']=='true'){
            $order_flag[] = 'PRESELL';
        }

        // 保价
        if($sdf['logi_protect'] == 'true'){
            $order_flag[] = 'INSURE';
        }

        //
        $node_id = base_shopnode::node_id('ome');

        if(in_array($node_id,array('1901943233','1892160738'))){
            if($sdf['order_type'] == 'presale' && !in_array('PRESELL',$order_flag)){

               $order_flag[] = 'PRESELL';
            }
        }
        // 天猫物流升级
        if (kernel::single('ome_delivery_bool_type')->isCPUP($sdf['bool_type'])) {
            // 最晚发货时间
            if ($sdf['delivery_time']) {
                $params['latest_delivery_time'] = date('Y-m-d H:i:s', $sdf['delivery_time']);
            }
        
            // 最晚送达时间
            if (strstr($sdf['ship_time'],'-')){
                list($scheduleDay, $scheduleEndTime) = explode(' ', $sdf['ship_time']);
            
                $delivery_requirements = array (
                    'schedule_day' => $scheduleDay,
                    'schedule_end_time' => $scheduleEndTime,
                );
            
                $params['delivery_requirements'] = json_encode(array($delivery_requirements));
            }
        
            $order_flag[] = 'LIMIT';
        
            // 上门订单
            if (in_array('201', explode(',', $sdf['cpup_service']))) {
                $order_flag[] = 'VISIT';
            }
            if ($sdf['promised_collect_time']) {
                $extend_props['promised_collect_time'] = date('Y-m-d H:i:s', $sdf['promised_collect_time']);
            }
            if ($sdf['promised_sign_time']) {
                $extend_props['promised_sign_time'] = date('Y-m-d H:i:s', $sdf['promised_sign_time']);
            }
            if ($sdf['cpup_addon']) {
                $cpup_addon = unserialize($sdf['cpup_addon']);
                if ($cpup_addon['collect_time']) {
                    $extend_props['collect_time'] = date('Y-m-d H:i:s', $cpup_addon['collect_time']);
                }
            }
            // 音尊达
            if (in_array('sug_home_deliver', explode(',', $sdf['cpup_service']))) {
                $order_flag[] = 'SUG_HOME_DELIVER';
            }
        }
        if ($sdf['bool_type'] & ome_delivery_bool_type::__SHSM_CODE) {
            $extend_props['jdService'] = 'JDWJ_DELIVERY_TO_DOOR';
            $order_flag[] = 'VISIT';
        }

        //仓库自定义字段-活动号
        if(isset($sdf['activity_no'])){
            $extend_props['activity_no'] = $sdf['activity_no'];
        }

        //抖音自选物流发货
        $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($sdf['delivery_id'], 'ome_delivery');
        $labelCode = array_column($labelCode, 'label_code');
        if (in_array(kernel::single('ome_bill_label')->isExpressMust(), (array)$labelCode)) {
            $order_flag[] = 'MODIFYTRANSPORT';
        }

        // 国补订单(京东平台国补拍照信息：gov_photo_check)
        if(in_array($sdf['shop_type'], ['360buy', 'jd'])){
            $guoBuLabelInfo = [];
            $tempOrderBns = explode('|', $sdf['order_bn']);
            $order_bn = $tempOrderBns[0];
            $orderInfo = app::get('ome')->model('orders')->dump(array('order_bn'=>$order_bn), 'order_id,order_bn');
            if($orderInfo){
                $guoBuLabelInfo = kernel::single('ome_bill_label')->getBillLabelInfo($orderInfo['order_id'], 'order', 'SOMS_GB');
            }

            // 国补订单拍照信息
            if($guoBuLabelInfo && $guoBuLabelInfo['label_value'] & 0x0400){
                $extend_props['gov_photo_check'] = '国补拍照';

                // flag
                $params['remark'] = '[国补拍照]'. $params['remark'];
            }
        }

        if ($this->isUrgentDelivery($sdf)) {
            $extend_props['urgent_delivery'] = true;
        }
        //是否是国补订单进行标识推送wms
        $extend_props['isGuobuOrder'] = $sdf['is_guobu_order'] ?: 'false';

        $params['extendProps'] = json_encode($extend_props);
        $params['order_flag'] = implode(',', $order_flag);

        // 如果三级区不存在，直接使用二级市
        $params['receiver_state']   = $this->_formate_receiver_province($params['receiver_state'],$params['receiver_district']);

        // oaid
        if($oaid) {
            // 是否密文标记
            $params['is_platform_encrypt'] = $this->is_platform_encrypt;

            //[伊藤忠WMS]订单手工编辑收货人信息是明文时,不用传oaid
            if(in_array($appkey, array('31417025')) && $sdf['extend_status'] == 'consignee_modified' && !$this->is_platform_encrypt){
                $params['receiver_oaid'] = '';
            }elseif(!$this->is_platform_encrypt){
                // 订单收货人信息是明文,不用传oaid给WMS仓库
                $params['receiver_oaid'] = '';
            }else{
                $params['receiver_oaid'] = $oaid;
            }

            // tid
            if($sdf['extend_field']['oaidSourceCode']) {
                $params['tid'] = $sdf['extend_field']['oaidSourceCode'];
                $extendProps = $params['extendProps'] ? json_decode($params['extendProps'], 1) : [];
                $extendProps["supplierId"] = $sdf['extend_field']['supplierId'];
                $extendProps["businessModel"] = $sdf['extend_field']['businessModel'];
                $params['extendProps'] = json_encode($extendProps);
            }elseif($sdf['order_source'] == 'platformexchange' && $sdf['platform_order_bn'] && in_array($sdf['shop_type'], array('xhs'))){
                //平台订单号(平台换货生成新订单的场景)
                //@todo：天猫平台换货,需要传换货单号（平台推送换货单时，会给一个新的oaid）；
                $params['tid'] = $sdf['platform_order_bn'];
            } else {
                $params['tid'] = explode('|', $sdf['order_bn']);
                $params['tid'] = $params['tid'][0];
            }
        }elseif(in_array($sdf['shop_type'], ['weimobr', 'weimobv'])){
            //@todo：必须传tid给美诺WMS侧;当微盟订单没有oaid时，必须传tid给美诺WMS侧;
            $tempOrders = explode('|', $sdf['order_bn']);
            $params['tid'] = $tempOrders[0];
        }

        // 唯品会,判断是否有优先发货的标签
        if ($sdf['shop_type'] == 'vop' && $sdf['extend_field']['action_list']) {
            $extendProps = $params['extendProps'] ? json_decode($params['extendProps'], 1) : [];
            // 优先发货
            if ($sdf['extend_field']['action_list']) {
                // $extendProps['action_list'] = $sdf['extend_field']['action_list'];
                $extendProps['priority_delivery'] = true;
            }
            $params['extendProps'] = json_encode($extendProps);
        }

        // 唯品会,判断是否有重点检查
        $quality_check_itemcode = [];
        if ($sdf['shop_type'] == 'vop' && isset($sdf['quality_check']) && $sdf['quality_check']) {
            $extendProps = $params['extendProps'] ? json_decode($params['extendProps'], 1) : [];
            $extendProps['quality_check'] = true;
            $params['extendProps'] = json_encode($extendProps);
            $quality_check_itemcode = array_column($sdf['quality_check'], 'bn');
        }

        # 电子面单相关信息
        if($sdf['waybill_extend']) {
            $waybillExtend = $this->_getWaybillExtend($sdf['waybill_extend']);
            if($waybillExtend) {
                $extendProps = $params['extendProps'] ? json_decode($params['extendProps'], 1) : [];
                $extendProps = array_merge($extendProps, $waybillExtend);
                $params['extendProps'] = json_encode($extendProps);
            }
        }

        // 支持WMS跨店铺取号传值方法
        $params = $this->_get_wms_cross_shop_waybill($params, $sdf);

        $params['seller_message'] = $sdf['memo'];

        //@todo：美诺WMS最多允许传255个字符(一个汉字为3个字符)，超出部分需要截取，否则推送报错；
        if(strlen($params['seller_message']) > 255){
            $params['seller_message'] = kernel::single('ome_func')->substrStringSafe($params['seller_message'], 255);
        }

        if(strlen($params['remark']) > 255){
            $params['remark'] = kernel::single('ome_func')->substrStringSafe($params['remark'], 255);
        }

        $params['buyer_message'] = $sdf['custom_mark'];

        $receiver_name = $params['receiver_name'];
        if ($receiver_name) $params['receiver_name'] = $receiver_name;

        $receiver_address = $params['receiver_address'];
        if ($receiver_address) $params['receiver_address'] = $receiver_address;

        //以product_id为键值组织货品明细(订单拆单后,回传奇门平台单号要唯一)
        $arrProductData    = array();
        if($sdf['order_objects'])
        {
            foreach ($sdf['order_objects'] as $objVal)
            {
                foreach ($objVal['order_items'] as $itemVal)
                {
                    $product_id    = $itemVal['product_id'];

                    //根据订单类型,使用关联订单号
                    if(in_array($objVal['order_type'], array('bufa'))){
                        $relate_order_bn = ($objVal['relate_order_bn'] ? $objVal['relate_order_bn'] : $objVal['order_bn']);

                        $arrProductData[$product_id]['order_bn'][$relate_order_bn] = $relate_order_bn;


                        $sdf['order_bn'] = $relate_order_bn;

                        // 补发单不推oaidOrderSourceCode
                        unset($params['tid']);
                    }else{
                        $arrProductData[$product_id]['order_bn'][$objVal['order_bn']] = $objVal['order_bn'];
                    }
                    
                    if($arrProductData[$product_id]){
                        $arrProductData[$product_id]['num'] += $itemVal['nums'];
                    }else {
                        $arrProductData[$product_id]['num']    = $itemVal['nums'];
                    }

                    $arrProductData[$product_id][$itemVal['item_id']]['oid'] = $itemVal['oid'] ? $itemVal['oid'] : uniqid();
                    $arrProductData[$product_id][$itemVal['item_id']]['shop_goods_id'] = $objVal['shop_goods_id'];
                }
            }
        }
        
        $params['operate_time']    = date('Y-m-d H:i:s');
        
        // 发货单明细详情
        $detailItems = [];
        $detailItemBns = [];
        $dlyDetailMdl = app::get('ome')->model('delivery_items_detail');
        $tempItems = $dlyDetailMdl->getList('item_detail_id,delivery_id,delivery_item_id,order_item_id,price,actually_amount,bn,number', array('delivery_id'=>$sdf['delivery_id']));
        foreach ($tempItems as $tempItemVal)
        {
            $product_bn = $tempItemVal['bn'];
            $detail_delivery_id = $tempItemVal['delivery_id'];
            $delivery_item_id = $tempItemVal['delivery_item_id'];
            $actually_amount = is_null($tempItemVal['actually_amount']) ? 0 : $tempItemVal['actually_amount'];

            // items
            $detailItems[$detail_delivery_id][$delivery_item_id] = $tempItemVal;

            // 按货号纬度累加：客户实付
            if(!isset($detailItemBns[$product_bn])){
                $detailItemBns[$product_bn] = [
                    'bn' => $product_bn,
                    'number' => 0,
                    'actually_amount' => 0,
                ];
            }

            $detailItemBns[$product_bn]['number'] += $tempItemVal['number'];
            $detailItemBns[$product_bn]['actually_amount'] += $actually_amount;
        }

        $items = array('item'=>array()); $delivery_items = $sdf['delivery_items'];

        if ($delivery_items){
            sort($delivery_items);
            foreach ($delivery_items as $k => $v)
            {
                $product_bn = $v['bn'];
                $qimen_delivery_id = $v['qimen_delivery_id'];
                $delivery_item_id = $v['delivery_items_id'];

                // 客户实付(合并发货单，按货号纬度累加后的金额)
                $actually_amount = $detailItemBns[$product_bn]['actually_amount'];

                // foreign_sku
                $foreignsku = app::get('console')->model('foreign_sku')->dump(array('wms_id'=>$this->__channelObj->wms['channel_id'],'inner_sku'=>$v['bn']));
                
                //订单拆单后,回传奇门平台单号要唯一
                $productInfo    = $arrProductData[$v['product_id']];

                //取明细order_item_id
                //$items_detail = $shopObj->db->selectrow("SELECT order_item_id FROM sdb_ome_delivery_items_detail WHERE delivery_id=".$v['qimen_delivery_id']." AND delivery_item_id=".$v['delivery_items_id']);
                $items_detail = $detailItems[$qimen_delivery_id][$delivery_item_id];

                //[兼容]合并发货单超过50个字符需要截取
                if($appkey && in_array($appkey,array('31417025'))){
                    $trade_code    = $sdf['order_bn'];
                }else{
                    $trade_code    = current($productInfo['order_bn']);
                }

                $sub_trade_code = $productInfo[$items_detail['order_item_id']]['oid'];
                if($productInfo && ($v['number'] != $productInfo['num']))
                {
                    //$sub_trade_code    .= '_'. $v['qimen_delivery_id'];
                }

                $_item = array(
                    'item_code'       => $foreignsku['oms_sku'] ? $foreignsku['oms_sku'] : $v['bn'],
                    'item_name'       => $v['product_name'],
                    'item_quantity'   => (int)$v['number'],
                    'item_price'      => (float)$v['price'],
                    'item_line_num'   => ($k + 1),// 订单商品列表中商品的行项目编号，即第n行或第n个商品
                    'trade_code'      => $trade_code,//可选(若是淘宝交易订单，并且不是赠品，必须要传订单来源编号)
                    'item_id'         => $foreignsku['outer_sku'] ? $foreignsku['outer_sku'] : $v['bn'],// 外部系统商品sku
                    'extCode'         => $productInfo[$items_detail['order_item_id']]['shop_goods_id'],// 平台商品编码
                    'is_gift'         => $v['is_gift'] == 'ture' ? '1' : '0',// 是否赠品
                    'item_remark'     => $v['memo'],// TODO: 商品备注
                    'inventory_type'  => '1',// TODO: 库存类型1可销售库存101类型用来定义残次品201冻结类型库存301在途库存
                    'item_sale_price' => (float)$v['sale_price'],//成交额
                    'sub_source_order_code' =>  $sub_trade_code,
                    'ownerCode'       => $sdf['owner_code'],
                    'item_actually_amount' => $actually_amount, // 客户实付
                );

                // 唯品会,判断是否有重点检查
                if ($quality_check_itemcode && in_array($_item['item_code'], $quality_check_itemcode)) {
                    $_item['extendProps'] = ['quality_check_itemcode'=>true];
                }
                $items['item'][] = $_item;
            }
        }

        //合并订单发货时，format格式化明细
        //@todo：items明细中第一个订单号必须与receiver_address加密订单一致，否则第三方WMS无法解密;
        $orderBnList = explode('|', $sdf['order_bn']);
        if(count($orderBnList) > 1){
            $items = $this->_formatMergeItems($sdf, $items);
        }

        //json
        $params['items'] = json_encode($items);
        
        // 出库单类型（矩阵字段名：orderType）
        //@todo: qimen平台类型（JYCK= 一般交易出库单，HHCK= 换货出库，BFCK= 补发出库 PTCK=普通出库单，DBCK=调拨出库，QTCK=其他出库）
        if ($sdf['relate_order_bn'] && $sdf['createway'] == 'after') {
            $params['order_type'] = 'HHCK';

            // 获取原始发货单号
            $order = app::get('ome')->model('orders')->dump(array('order_bn'=>$sdf['relate_order_bn']),'order_id');
            $delivery_orders = app::get('ome')->model('delivery_order')->getList('delivery_id',array('order_id'=>$order['order_id']));

            foreach ($delivery_orders as $delivery_order) {
                $delivery_ids[] = $delivery_order['delivery_id'];
            }

            $delivery = app::get('ome')->model('delivery')->dump(array('delivery_id'=>$delivery_ids,'parent_id'=>0,'process'=>'true','status'=>'succ'),'delivery_bn');

            $delivery_extend = app::get('console')->model('delivery_extension')->dump(array('delivery_bn'=>$delivery['delivery_bn']));
            $params['wms_order_code'] = $delivery_extend['original_delivery_bn'];

            $params['orig_order_code'] = $delivery['delivery_bn'];
        }elseif($sdf['order_type'] == 'bufa'){
            // 补发出库
            $params['order_type'] = 'BFCK';
        }
        
        $params['pay_time'] = $sdf['pay_time'];

        return $params;
    }

    /**
     * 判断当前发货单或关联订单是否已打上加急发货标签，用于透传 WMS。
     *
     * @param array $sdf
     * @return bool
     */
    protected function isUrgentDelivery($sdf)
    {
        $labelLib = kernel::single('ome_bill_label');
        if (!empty($sdf['delivery_id']) && $labelLib->existLabel($sdf['delivery_id'], 'SOMS_URGENT_SHIP', 'ome_delivery')) {
            return true;
        }
        if (!empty($sdf['order_id']) && $labelLib->existLabel($sdf['order_id'], 'SOMS_URGENT_SHIP')) {
            return true;
        }
        if (!empty($sdf['order_bn'])) {
            $order = app::get('ome')->model('orders')->dump(['order_bn' => $sdf['order_bn']], 'order_id');
            if (!empty($order['order_id']) && $labelLib->existLabel($order['order_id'], 'SOMS_URGENT_SHIP')) {
                return true;
            }
        }
        return false;
    }

    protected function _getNextObjType()
    {
        return '';
    }
    
    protected function _getWaybillExtend($waybill_extend) {
        $ret = [];
        if($waybill_extend['position_no']) {
            $ret['PrintData']['sortCode'] = $waybill_extend['position_no'];
        }
        if($waybill_extend['sort_code']) {
            $ret['PrintData']['sortCode'] = $waybill_extend['sort_code'];
        }
        if($waybill_extend['package_wdjc']) {
            $ret['PrintData']['packageCenterName'] = $waybill_extend['package_wdjc'];
        }
        if($waybill_extend['package_wd']) {
            $ret['PrintData']['packageCenterCode'] = $waybill_extend['package_wd'];
        }
        if($waybill_extend['json_packet']) {
            // $ret['PrintData']['extra'] = $waybill_extend['json_packet'];
            $jp = @json_decode($waybill_extend['json_packet'], 1);
            if(is_array($jp)) {
                if($jp['encryptedData']) {
                    $ret['PrintData']['encryptedData'] = $jp['encryptedData'];
                }
                if($jp['signature']) {
                    $ret['PrintData']['signature'] = $jp['signature'];
                }
                if($jp['templateURL']) {
                    $ret['PrintData']['templateURL'] = $jp['templateURL'];
                }
                if($jp['ver']) {
                    $ret['PrintData']['ver'] = $jp['ver'];
                }

                if ($jp['printData']) {
                    $jpp = @json_decode($jp['printData'], 1);
                    if (is_array($jpp)) {
                        if($jpp['encryptedData']) {
                            $ret['PrintData']['encryptedData'] = $jpp['encryptedData'];
                        }
                        if($jpp['templateURL']) {
                            $ret['PrintData']['templateURL'] = $jpp['templateURL'];
                        }
                        if($jpp['ver']) {
                            $ret['PrintData']['ver'] = $jpp['ver'];
                        }
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * 合并订单发货时，format格式化明细
     * @todo：items明细中第一个订单号必须与receiver_address加密订单一致，否则第三方WMS无法解密;
     *
     * @param $sdf
     * @param $items
     * @return void
     */
    public function _formatMergeItems($sdf, $items)
    {
        $first_order_bn = current(explode('|', $sdf['order_bn']));

        $tempList = array();
        $firstList = array();
        foreach ($items['item'] as $itemKey => $itemVal)
        {
            //type
            if($itemVal['trade_code'] == $first_order_bn){
                $firstList[] = $itemVal;
            }else{
                $tempList[] = $itemVal;
            }
        }

        $items['item'] = array_merge($firstList, $tempList);

        return $items;
    }

    /**
     * 预售付尾款通知wms接口
     *
     * @param $sdf
     * @return string
     */
    protected function get_delivery_notify_apiname($sdf)
    {
        return WMS_PRESALES_PACKAGE_CONSIGN;
    }

    /**
     * 预售付尾款通知wms参数
     *
     * @param $sdf
     * @return array
     */
    protected function _format_delivery_notify_params($sdf)
    {
        $params = array(
            'orderCode' => $sdf['delivery_bn'], //外部订单号
            'payTime' => date('Y-m-d H:i:s', $sdf['paytime']), //支付时间
            'totalAmount' => $sdf['total_amount'], //金额(单位为分)
            'remark' => '', //备注
            //'ownerCode' => $sdf['ownerCode'], //货主编码(此字段是矩阵取customerId字段的值)
        );

        return $params;
    }

    // 支持WMS跨店铺取号传值方法
    protected function _get_wms_cross_shop_waybill($params, $sdf)
    {
        $appkey = $this->__channelObj->wms['addon']['wms_appkey'];

        // 旺店通
        if (in_array($appkey, array('23381383'))) {
            // 视屏号
            if ($sdf['shop_type']=='wxshipin'){
                $extendProps = $params['extendProps'] ? json_decode($params['extendProps'], 1) : [];
                $extendProps['ewaybill_order_code'] = $params['receiver_oaid'];

                $params['extendProps'] = json_encode($extendProps);

                $params['buyer_nick'] = $sdf['platform_shop_unikey'];
            }
        }

        return $params;
    }
}
