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


class wms_delivery_template
{
    public $isJDMD      = false;
    public $jsKDNMD     = false;
    protected $elements = array(
        'ship_name'                     => '收货人-姓名',
        'ship_area_0'                   => '收货人-地区1级',
        'ship_area_1'                   => '收货人-地区2级',
        'ship_area_2'                   => '收货人-地区3级',
        'ship_addr'                     => '收货人-地址',
        'ship_addr_mark'                => '收货人-地址-备注',
        'ship_detailaddr'               => '收货人-详细地址',
        'ship_detailaddr_mark'          => '收货人-详细地址-备注',
        'delivery_bn'                   => '收货人-发货单号',
        'logi_no'                       => '快递单号',
        'ship_tel'                      => '收货人-电话',
        'ship_mobile'                   => '收货人-手机',
        'ship_zip'                      => '收货人-邮编',

        'dly_name'                      => '发货人-姓名',
        'dly_area_0'                    => '发货人-地区1级',
        'dly_area_1'                    => '发货人-地区2级',
        'dly_area_2'                    => '发货人-地区3级',
        'dly_address'                   => '发货人-地址',
        'dly_detailaddr'                => '发货人-详细地址',
        'dly_tel'                       => '发货人-电话',
        'dly_mobile'                    => '发货人-手机',
        'dly_zip'                       => '发货人-邮编',

        'date_y'                        => '当日日期-年',
        'date_m'                        => '当日日期-月',
        'date_d'                        => '当日日期-日',
        'date_h'                        => '当日日期-时',
        'date_i'                        => '当日日期-分',
        'date_s'                        => '当日日期-秒',
        'date_ymdhis'                   => '当日日期-年-月-日 时-分秒',
        'order_bn'                      => '订单-订单号',
        'order_count'                   => '快递单-物品数量',
        'delyvery_memo'                 => '快递单-备注',
        'delivery_order_amount'         => '快递单-总价',
        'delivery_order_amount_d'       => '快递单-总价(大写)',
        'delivery_receivable'           => '快递单-应收款',

        'delivery_receivable_d'         => '快递单-应收款(大写)',
        'order_memo'                    => '卖家备注',
        'order_custom'                  => '客户留言',
        'shop_name'                     => '网店名称',

        'bn_spec_num_n'                 => '商家编码+规格+数量(不换行)',
        'bn_spec_num_y'                 => '商家编码+规格+数量(换行)',

        'goodsbn_spec_num_n'            => '商品编码+规格+数量(不换行)',
        'goodsbn_spec_num_y'            => '商品编码+规格+数量(换行)',

        //12.15需求，TODO 暂时注释
        'member_uname'                  => '会员名',
        'bn_amount_n'                   => '货号+数量(不换行)',
        'name_amount_n'                 => '货品名+数量(不换行)',
        'bn_name_amount_n'              => '货号+货品名+数量(不换行)',

        'bn_amount'                     => '货号+数量',
        'name_amount'                   => '货品名+数量',
        'bn_name_amount'                => '货号+货品名+数量',

        'bn_amount_pos'                 => '货号+数量+货位',
        'name_amount_pos'               => '货品名+数量+货位',
        'bn_name_amount_pos'            => '货号+货品名+数量+货位',

        'print_no'                      => '打印批次号',

        'tick'                          => '对号 - √',
        'text'                          => '自定义内容',
        'name_spec_amount'              => '货品名+规格+数量',
        'bn_name_spec_amount'           => '货号+货品名+规格+数量(不换行)',
        'bn_name_spec_amount_y'         => '货号+货品名+规格+数量(换行)',
        'new_bn_name_amount'            => '{商品名称+数量}不换行',
        //货号+规格+数量
        'bn_spec_num'                   => '货号+规格+数量',

        'total_product_weight_g'        => '货品重量 单位：g',
        'total_product_weight_kg'       => '货品重量 单位：kg',
        'bn_productname_spec_num_pos_n' => '货号 货品名称 规格 数量 货位(换行)',
        'bn_productname_spec_num_pos'   => '货号 货品名称 规格 数量 货位(不换行)',
        'goods_bn'                      => '商家编码',
        'pkgbn_num_n'                   => '捆绑商品货号+数量(换行)',
        'sfcity_code'                   => '顺丰城市代码',
        'mailno_position'               => '面单大头笔',
        'mailno_position_no'            => '面单大头笔编码',
        'package_wdjc'                  => '集包地',
        'cloud_stack_position'          => '云栈大头笔',
        'virtual_number_memo'           => '虚拟号备注',

        'normal_productname_spec_num_n' => '普通商品-货品名称+规格+数量(换行)',
    );

    /**
     * default elements
     * 默认配置列表
     * @return array();
     */
    public function defaultElements()
    {
        $printTagObj = app::get('wms')->model('print_tag');
        $rows        = $printTagObj->getList('*');
        foreach ($rows as $row) {
            if ($row['tag_id'] > 0) {
                $key                  = 'print_tag_' . $row['tag_id'];
                $this->elements[$key] = '大头笔-' . $row['name'];
            }
        }
        return $this->elements;
    }

    /**
     * process default print content
     * 处理快递单打印项的对应内容
     * @param array $value_list
     * @return string
     */
    public function processElementContent($value_list)
    {
        $basicMaterialLib = kernel::single('material_basic_material');

        $data['delivery_id'] = $value_list['delivery_id'];

        // 如果用的平台组件打印不需要解密
        if (!in_array($value_list['printTpl']['template_type'], ['normal', 'electron', 'delivery', 'stock', 'sf'])) {
            $consigneeCols = array('ship_name', 'ship_addr', 'ship_tel', 'ship_mobile');
            foreach ($consigneeCols as $col) {
                $csIndex = strpos($value_list[$col], '>>');
                if ($csIndex !== false) {
                    $value_list[$col] = substr($value_list[$col], 0, $csIndex);
                }
            }
            foreach ($value_list['consignee'] as $dkk => $dkv) {
                $csIndex = strpos($dkv, '>>');
                if ($csIndex !== false) {
                    $value_list['consignee'][$dkk] = substr($dkv, 0, $csIndex);
                }
            }
        }

        // 判断是否加密
        if (kernel::single('ome_security_router', $value_list['shop_type'])->is_encrypt($value_list, 'delivery')) {
            $data['delivery_id'] = $value_list['delivery_id'];
            $data['is_encrypt']  = true;
            $data['app']         = 'wms';
        }

        // [拆单]订单是否拆单
        $oDelivery = app::get('ome')->model('delivery');
        $is_split  = false;

        //12.15需求 ，TODO 暂时注释
        $order_Objects = app::get('ome')->model('order_objects');
        $orderObj      = app::get('ome')->model('orders');
        $orderItemsObj = app::get('ome')->model('order_items');
        if ($value_list['delivery_order']) {
            $orderIds = array_keys($value_list['delivery_order']);

            $tbfxitemObj = app::get('ome')->model('tbfx_order_items');

            $orders = $value_list['orders'];

            // $orderObj->getList('cost_freight,order_id, process_status, ship_status, total_amount,shop_type,order_source', array('order_id' => $orderIds));

            $delivery_amount = $delivery_receivable = 0;

            foreach ($orders as $order) {
                if ($order['order_source'] == 'tbdx' && $order['shop_type'] == 'taobao') {
                    $itemdata = $tbfxitemObj->dump(array('order_id' => $order['order_id']), 'SUM(buyer_payment) AS total_buyer_payment');
                    $delivery_amount += ($order['cost_freight'] + $itemdata['total_buyer_payment']);
                } else {
                    $delivery_amount += $order['total_amount'];
                }

                // [拆单]订单是否为部分拆分OR部分发货
                if ($order['process_status'] == 'splitting' || $order['ship_status'] == '2') {
                    $is_split = true;
                }

                // [拆单]订单是否有多个发货单
                if ($is_split == false) {
                    $is_split = $oDelivery->validDeiveryByOrderId($order['order_id']);
                }

                $delivery_receivable += $order['receivable'];
            }

            // $orderExtendObj = app::get('ome')->model('order_extend');
            // $orderExtends   = $orderExtendObj->getList('receivable', array('order_id' => $orderIds));
            // foreach ($orderExtends as $extend) {
            //     $delivery_receivable += $extend['receivable'];
            // }

            $data['delivery_order_amount']   = number_format($delivery_amount, 2, '.', ' ');
            $data['delivery_order_amount_d'] = $this->NumToFinanceNum(number_format($delivery_amount, 2, '.', ''), true, false);
            $data['delivery_receivable']     = number_format($delivery_receivable, 2, '.', ' ');
            $data['delivery_receivable_d']   = $this->NumToFinanceNum(number_format($delivery_receivable, 2, '.', ''), true, false);
        }

        $data['bn_spec_num_y'] = $data['bn_spec_num_n'] = '';

        $delivery_cfg = app::get('wms')->getConf('wms.delivery.status.cfg');
        #开启打印捆绑商品按钮
        if ($delivery_cfg['set']['print_pkg_goods']) {
            #根据订单，获取捆绑商品信息

            $pkgbn_num_n = [];
            foreach ($value_list['delivery_items'] as $di) {
                if ($di['item_type'] != 'pkg') {
                    continue;
                }
                $object = $value_list['orders'][$di['order_id']]['order_objects'][$di['order_obj_id']];
                $item = $object['order_items'][$di['order_item_id']];
                $quantity = $di['number'] * ($object['quantity'] / $item['nums']);

                $data['pkgbn_num_n'] .= $object['bn'] . '  x  ' . $quantity . "\n";
                $data['pkg_productname_bn_num_n'] .= $object['name'] . '（' . $object['bn'] . '）x ' . $quantity . "\n";
            }
        }

        #普通商品(货号+数据)
        foreach ($value_list['delivery_items'] as $di) {
            if ($di['item_type'] != 'pkg') {
                continue;
            }

            $object = $value_list['orders'][$di['order_id']]['order_objects'][$di['order_obj_id']];

            $data['normal_good_n'] .= $object['bn'] . '  x  ' . $di['number'] . "\n";
            $data['normal_good'] .= $object['bn'] . '  x  ' . $di['number'] . ' , ';
            $data['normal_productname_bn_num_n'] .= $object['name'] . ' : ' . $object['bn'] . '  x  ' . $di['number'] . "\n";
        }

        $data['normal_good'] = trim($data['normal_good'], ' , ');

        $noFirst = false;
        $data['wxshipin_product_info'] = array();
        if ($value_list['delivery_items']) {
            $totalNum             = 0;
            $total_product_weight = 0;
            $i                    = 0;
            foreach ($value_list['delivery_items'] as $item) {
                $data['wxshipin_product_info'][] = array(
                    'name' => isset($item['product_name']) ? $item['product_name'] : '',
                    'count' => isset($item['number']) ? intval($item['number']) : 0,
                    'code' => isset($item['bn']) ? $item['bn'] : '',
                    'description' => isset($item['addon']) ? $item['addon'] : '',
                );
                if ($item['addon']) {
                    $addon = sprintf(' %s', $item['addon']);
                } else {
                    $addon = '';
                }
                $totalNum = $totalNum + $item['number'];

                //商家编码+规格+数量+换行
                $bn = $item['bn'];
                if (substr($item['bn_dbvalue'], 0, 3) === ':::') {
                    $bn = '';
                }

                $noFirst && $data['bn_spec_num_n'] .= ' , ';
                $noFirst && $data['bn_spec_num_y'] .= "\r\n";

                $noFirst && $data['bn_name_spec_amount'] .= ' , ';
                $noFirst && $data['bn_name_spec_amount_y'] .= "\r\n";
                $goods_bn = $bn;//$this->get_goods_bn($bn);
                if (empty($bn) && empty($item['addon'])) {
                    $data['bn_spec_num_n'] .= '';
                    $data['bn_spec_num_y'] .= '';
                } else {
                    $data['bn_spec_num_n'] .= $bn . "  " . $item['addon'] . " x " . $item['number'];
                    $data['bn_spec_num_y'] .= $bn . "  " . $item['addon'] . " x " . $item['number'];
                }

                //基础物料的重量
                // $product = $basicMaterialLib->getBasicMaterialBybn($item['bn']);

                $total_product_weight += ($item['weight'] * $item['number']);
                //
                //货号+数量+货位
                $data['bn_amount_pos'] .= $item['bn'] . " x " . $item['number'] . ' - ' . $item['store_position'] . "\n";
                //货品名+数量+货位
                $data['name_amount_pos'] .= $item['product_name'] . $addon . " x " . $item['number'] . ' - ' . $item['store_position'] . "\n";
                //货号+货品名+数量+货位
                $data['bn_name_amount_pos'] .= $item['bn'] . " ：" . $item['product_name'] . $addon . " x " . $item['number'] . ' - ' . $item['store_position'] . "\n";

                //货号+数量
                $data['bn_amount_n'] .= $item['bn'] . " x " . $item['number'] . " , ";
                //货品名+数量
                $data['name_amount_n'] .= $item['product_name'] . $addon . " x " . $item['number'] . " , ";
                //货号+货品名+数量
                $data['bn_name_amount_n'] .= $item['bn'] . " ：" . $item['product_name'] . $addon . " x " . $item['number'] . " , ";

                $data['bn_amount'] .= "货号：" . $item['bn'] . " 数量：" . $item['number'] . "\n";
                //货品名+数量
                $data['name_amount'] .= "货品名：" . $item['product_name'] . $addon . " 数量：" . $item['number'] . "\n";
                //货号+货品名+数量
                $data['bn_name_amount'] .= "货号：" . $item['bn'] . " 货品名：" . $item['product_name'] . $addon . " 数量：" . $item['number'] . "\n";
                //货品名+规格+数量
                $data['name_spec_amount'] .= $item['product_name'] . "  " . $item['addon'] . " x " . $item['number'];
                //货号+货品名+规格+数量
                $data['bn_name_spec_amount'] .= $item['bn'] . "：" . $item['product_name'] . "  " . $item['addon'] . " x " . $item['number'];
                $data['bn_name_spec_amount_y'] .= $item['bn'] . "：" . $item['product_name'] . "  " . $item['addon'] . " x " . $item['number'];

                //抖音电子面单[货品名称+规格+数量(换行)]
                $data['productname_spec_num_n'] .= $item['product_name'] . "  " . $item['addon'] . " x " . $item['number'] . "\n";
                $data['new_bn_name_amount_n'] .= $item['product_name'] . " x " . $item['number'] . "\n";

                $data['new_bn_name_amount'] .= "【" . $item['product_name'] . " x " . $item['number'] . " 】 ";
                $data['bn_spec_num'] .= $item['bn'] . "  " . $item['addon'] . " x " . $item['number'];

                $data['goodsbn_spec_num_n'] .= $goods_bn . "  " . $item['addon'] . " x " . $item['number'];
                $data['goodsbn_spec_num_y'] .= $goods_bn . "  " . $item['addon'] . " x " . $item['number'] . "\n";

                $data['goods_bn'] .= $goods_bn . "\n";
                $data['bn_productname_spec_num_pos_n'] .= $item['bn'] . "：" . $item['product_name'] . "  " . $item['addon'] . " x " . $item['number'] . '-' . $item['store_position'] . "\n";
                $data['bn_productname_spec_num_pos'] .= $item['bn'] . "：" . $item['product_name'] . "  " . $item['addon'] . " x " . $item['number'] . '-' . $item['store_position'];

                // 有效规整
                $data['normal_productname_spec_num_n'] .= $item['product_name'] . "  " . $item['addon'] . " x " . $item['number'] . "\n";

                $noFirst                      = true;
                $self_data[$i]['bn']          = $item['bn'] . ' ';
                $self_data[$i]['pos']         = $item['store_position'] . ' ';
                $self_data[$i]['name']        = $item['product_name'] . ' ';
                $self_data[$i]['spec']        = $item['addon'] . ' ';
                $self_data[$i]['amount']      = $item['number'] . ' ';
                $self_data[$i]['new_bn_name'] = $item['product_name'] . ' ';
                $self_data[$i]['goods_bn']    = $goods_bn . ' ';
                $self_data[$i]['goods_bn2']   = $goods_bn . ' '; //历史遗留问题，商家编码就是商品编号
                $self_data[$i]['n']           = "\n";
                $i++;
            }

            $data['bn_amount_n']             = preg_replace('/, $/is', '', $data['bn_amount_n']) . sprintf(' 共 %d 件', $totalNum);
            $data['name_amount_n']           = preg_replace('/, $/is', '', $data['name_amount_n']) . sprintf(' 共 %d 件', $totalNum);
            $data['bn_name_amount_n']        = preg_replace('/, $/is', '', $data['bn_name_amount_n']) . sprintf(' 共 %d 件', $totalNum);
            $data['total_product_weight_g']  = $total_product_weight . 'g'; //商品重量累加
            $data['total_product_weight_kg'] = ($total_product_weight / 1000) . 'kg'; //商品重量累加
        }

        //会员名
        // if ($value_list['member_id']) {
        //     $member               = app::get('ome')->model('members')->dump($value_list['member_id']);
        //     $data['member_uname'] = $member['account']['uname'];
        // }
        $data['member_uname'] = $value_list['member_uname'];
        $data['ship_name']       = $value_list['consignee']['name'];
        $data['ship_addr']       = $value_list['consignee']['addr'];
        $data['ship_tel']        = $value_list['consignee']['telephone'];
        $data['ship_mobile']     = $value_list['consignee']['mobile'];
        $data['ship_zip']        = (string) $value_list['consignee']['zip'];
        $data['ship_area_0']     = trim($value_list['consignee']['province']);
        $data['ship_area_1']     = trim($value_list['consignee']['city']);
        $data['ship_area_2']     = trim($value_list['consignee']['district']);
        $data['ship_detailaddr'] = $value_list['consignee']['province'] . $value_list['consignee']['city'] . $value_list['consignee']['district'] . $value_list['consignee']['addr'];
        $data['order_bn']        = (string) $value_list['order_bn'];
        $data['order_count']     = (string) $value_list['order_count'];
        $data['order_memo']      = (string) $value_list['order_memo'];
        $data['order_custom']    = (string) $value_list['order_custom'];
        $data['delivery_bn']     = (string) $value_list['delivery_bn'];
        $data['logi_no']         = (string) $value_list['logi_no'];
        $data['delyvery_memo']   = $value_list['memo'];

        list($virtual_number, $ext_number) = explode('-', $value_list['consignee']['mobile']);
        $data['virtual_number_memo']       = $ext_number ? sprintf('[配送请拨打%s转%s]', $virtual_number, $ext_number) : '';

        //获取顺丰城市代码
        // if (app::get('logisticsmanager')->is_installed()) {
        //     $sfcityCodeObj       = app::get('logisticsmanager')->model('sfcity_code');
        //     $area_crc32          = sprintf('%u', crc32($data['ship_area_1']));
        //     $sfcity_code         = $sfcityCodeObj->dump(array('city_crc32' => $area_crc32, 'province|head' => $data['ship_area_0']), 'city_code');
        //     $data['sfcity_code'] = $sfcity_code['city_code'];
        // }

        if (isset($GLOBALS['user_timezone'])) {
            $t = time() + ($GLOBALS['user_timezone'] - SERVER_TIMEZONE) * 3600;
        } else {
            $t = time();
        }
        //$t = time()+($GLOBALS['user_timezone']-SERVER_TIMEZONE)*3600;
        $data['date_y']   = (string) date('Y', $t);
        $data['date_m']   = (string) date('m', $t);
        $data['date_d']   = (string) date('d', $t);
        $data['date_ymd'] = date('Y-m-d', $t);
        $data['date_h']   = date('H', $t);
        $data['date_i']   = date('i', $t);
        $data['date_s']   = date('s', $t);
        $data['date_ymdhis'] = date('Y-m-d H:i:s', $t);
        // 发货人信息
        if ($value_list['shopinfo']) {
            $area                   = kernel::single('base_view_helper')->modifier_region($value_list['shopinfo']['area']);
            $area                   = explode('-', $area);
            $data['dly_area_0']     = $area[0];
            $data['dly_area_1']     = $area[1];
            $data['dly_area_2']     = $area[2];
            $data['dly_address']    = $value_list['shopinfo']['addr'];
            $data['dly_detailaddr'] = $area[0] . $area[1] . $area[2] . $value_list['shopinfo']['addr'];
            $data['dly_tel']        = (string) $value_list['shopinfo']['tel'];
            $data['dly_mobile']     = (string) $value_list['shopinfo']['mobile'];
            $data['dly_zip']        = (string) $value_list['shopinfo']['zip'];
            $data['dly_name']       = $value_list['shopinfo']['default_sender'];
            $data['shop_name']      = $value_list['shopinfo']['name'];
        }

        //根据自定义获取大头笔信息
        // $this->getPrintTag($data);

        // $corpType         = app::get('ome')->model('dly_corp')->dump(array('corp_id' => $value_list['logi_id']), 'tmpl_type,channel_id,type');
        // $dly_corp_channel = app::get('ome')->model('dly_corp_channel')->db_dump(['corp_id' => $value_list['logi_id'], 'shop_type' => $value_list['shop_type']]);
        // if ($dly_corp_channel) {
        //     $data['channel_id'] = $dly_corp_channel['channel_id'];
        // } else {
        //     $data['channel_id'] = $corpType['channel_id'];
        // }
        $data['channel_id'] = $value_list['channel_id'];


        //面单扩展数据
        $data['mailno_position']  = ''; //面单大头笔
        $data['mailno_position_'] = ''; //面单大头笔编码
        $data['mailno_barcode']   = ''; //面单条形码
        $data['mailno_qrcode']    = ''; //面单二维码
        $data['package_wdjc']     = ''; //集包地
        $data['package_wd']       = ''; //集包地编码
        $data['batch_logi_no']    = '';
        $mainoInfo                = $value_list['mainoInfo'];

        if (!$mainoInfo) {
            $mainoInfo = $this->getMainnoInfo(array('logi_no' => $value_list['logi_no'], 'logi_id' => $value_list['logi_id'], 'channel_id' => $data['channel_id']));
        }

        if ($mainoInfo) {
            $data['mailno_position']    = $mainoInfo['position'];
            $data['mailno_position_no'] = $mainoInfo['position_no'];
            $data['mailno_barcode']     = $mainoInfo['mailno_barcode'];
            $data['mailno_qrcode']      = $mainoInfo['qrcode'];
            $data['package_wdjc']       = $mainoInfo['package_wdjc'];
            $data['package_wd']         = $mainoInfo['package_wd'];
            $data['print_config']       = $mainoInfo['print_config'];
            // $channelExtendInfo          = app::get('logisticsmanager')->model('channel_extend')->dump(array('channel_id' => $corpType['channel_id']), 'seller_id');
            $data['seller_id']          = $value_list['seller_id'];
            $data['waybill_no_check']   = $value_list['channel_mobile'] ? substr($value_list['channel_mobile'], -6) : '';//寄方电话后6位 运单号合法校验
            $data['cp_code']            = strtoupper($value_list['logi_type']);
        }

        $data['batch_logi_no']  = $value_list['batch_logi_no'];
        $data['package_number'] = '1/1';

        if ($data['logi_no']) {
            $pack_number = explode('-', $data['batch_logi_no']);

            if (count($pack_number) >= 3) {
                $data['package_number'] = $pack_number[1] . '/' . $pack_number[2];
            }
        }
        $memo = '';
        if (!empty($value_list['memo'])) {
            $memo = '   (' . $value_list['memo'] . ')';
        }

        $data['ship_addr_mark']       = $data['ship_addr'] . $memo;
        $data['ship_detailaddr_mark'] = $data['ship_detailaddr'] . $memo;
        //业务类型logistics_code
        // $channelObj = app::get('logisticsmanager')->model('channel');
        // $channel    = $channelObj->dump(array('channel_id' => $corpType['channel_id']), 'logistics_code,channel_type');

        switch ($value_list['channel_type']) {
            case '360buy':$this->isJDMD = true;
                break;
            case 'hqepay':$this->jsKDNMD = true;
                break;
        }
        $logistics_code         = kernel::single('logisticsmanager_waybill_sf')->logistics($value_list['logistics_code']);
        $data['logistics_code'] = $logistics_code['name'];
        $data['print_no']       = $value_list['print_no'];
        //app::get('ome')->model('print_queue')->findFullIdent($value_list['delivery_id']);

        $_self_elments = app::get('wms')->getConf('wms.delivery.print.selfElments');
        #获取快递单对应的自定义打印项
        $self_elments = $_self_elments['element_' . $value_list['prt_tmpl_id']];
        if (isset($self_elments['element'])) {
            $_key            = array_keys($self_elments['element']);
            $key             = explode('+', $_key[0]);
            $str_self_elment = '';
            foreach ($self_data as $_k => $v) {
                foreach ($key as $val) {
                    $str_self_elment .= $v[$val] . ' ';
                }
            }
            #把原来键中的+号替换掉
            $new_key = str_replace('+', '_', $_key[0]);
            #自定义的打印项的值
            $data[$new_key] = $str_self_elment;
        }
        foreach ($data as $k => $v) {
            $data[$k] = addslashes($v);
            unset($k, $v);
        }
        if ($mainoInfo['json_packet']) {
            $json_packet = json_decode($mainoInfo['json_packet'], true);

            if ($json_packet['rls_detail']) {
                $data['sf_twoDimensionCode_qrcode'] = (string) $json_packet['rls_detail']['@twoDimensionCode'];
            }

            if ($this->isJDMD) {
                $data['jdsourcet_sort_center_name']            = $json_packet['resultInfo']['sourcetSortCenterName'];
                $data['jdoriginal_cross_tabletrolley_code']    = $json_packet['resultInfo']['originalCrossCode'] . '-' . $json_packet['resultInfo']['originalTabletrolleyCode'];
                $data['jdtarget_sort_center_name']             = $json_packet['resultInfo']['targetSortCenterName'];
                $data['jddestination_cross_tabletrolley_code'] = $json_packet['resultInfo']['destinationCrossCode'] . '-' . $json_packet['resultInfo']['destinationTabletrolleyCode'];
                $data['jdsite_name']                           = $json_packet['resultInfo']['siteName'];
                $data['jdroad']                                = $json_packet['resultInfo']['road'];
                $data['jdaging_name']                          = $json_packet['resultInfo']['agingName'];
            }
            $this->covertAddslashes($json_packet);
            if ($json_packet['DialPage']) {
                $data['phonecall_qrcode'] = $json_packet['DialPage'];
            }

            if ($json_packet['MarkDestination']) {
                $data['markdest'] = $json_packet['MarkDestination'];
            }

            if ($json_packet['SortingCode']) {
                $data['SortingCode'] = $json_packet['SortingCode'];
            }

            if ($this->jsKDNMD) {
                if ($json_packet['ReceiverSafePhone']) {
                    $data['ship_mobile'] = $json_packet['ReceiverSafePhone'];
                }

            }

            $data['json_packet'] = json_encode($json_packet);

        }

        //仓库信息
        $branch = app::get('ome')->model('branch')->db_dump(array('branch_id'=>$value_list['branch_id'],'check_permission'=>'false'),'*') ;
        if($branch){
            $province = '';
            $city = '';
            $area = '';
            if($branch['area']){
                list(, $mainland)             = explode(':', $branch['area']);
                list($province, $city, $area) = explode('/', $mainland);

            }
            

            $data['shopstore_address'] = $province.$city.$area.$branch['address'];
            $data['shopstore_shipname'] = $branch['uname'];
            $data['shopstore_mobile'] = $branch['mobile'];
            
        }
        
        // 判断是否加密
        $hashCode     = kernel::single('ome_security_hash')->get_code();
        $encryptField = ['member_uname', 'ship_detailaddr', 'ship_addr', 'ship_name', 'ship_addr_mark', 'ship_detailaddr_mark', 'ship_mobile', 'dly_mobile', 'dly_tel', 'ship_tel'];
        foreach ($encryptField as $cf) {
            if (false !== strpos($data[$cf], $hashCode)) {
                $data['delivery_id'] = $value_list['delivery_id'];
                $data['is_encrypt']  = true;
                $data['app']         = 'wms';

                break;
            }
        }

        if ($value_list['channel_type'] == 'hqepay' && $value_list['printTpl']['control_type'] == 'lodop' && !$data['is_encrypt']) {
            // 隐私面单
            $account = explode('|||', $value_list['channel_shop_id']);
            if (isset($account[5]) && $account[5] == 1) {
                foreach (['ship_name','ship_mobile','ship_tel','ship_addr','ship_detailaddr','dly_mobile','dly_address','dly_detailaddr'] as $_v) {
                    $data[$_v] = kernel::single('base_view_helper')->modifier_cut($data[$_v],-1,strlen($data[$_v]) > 11 ?'****':'*',false,true);
                }
            }
        }

        return $data;
    }

    //根据收货地区得到大头笔内容
    /**
     * 获取PrintTag
     * @param mixed $data 数据
     * @return mixed 返回结果
     */
    public function getPrintTag(&$data)
    {
        $zhixiashi   = array('北京', '上海', '天津', '重庆');
        $areaGAT     = array('香港', '澳门', '台湾');
        $area2Str    = substr($data['ship_area_2'], -3);
        $printTagObj = app::get('wms')->model('print_tag');
        $rows        = $printTagObj->getList('*');
        foreach ($rows as $row) {
            if ($row['tag_id'] > 0) {
                $key       = 'print_tag_' . $row['tag_id'];
                $tagConfig = unserialize($row['config']);
                if ($data['ship_area_0'] && in_array($data['ship_area_0'], $zhixiashi)) {
                    if ($tagConfig['zhixiashi'] == '1') {
                        $data[$key] = $data['ship_area_2'];
                    } else {
                        $data[$key] = $data['ship_area_1'] . $data['ship_area_2'];
                    }
                } elseif ($data['ship_area_0'] && in_array($data['ship_area_0'], $areaGAT)) {
                    if ($tagConfig['areaGAT'] == '1') {
                        $data[$key] = $data['ship_area_2'];
                    } else {
                        $data[$key] = $data['ship_area_1'] . $data['ship_area_2'];
                    }
                } else {
                    $data[$key] = '';
                    if ($tagConfig['province'] == '1') {
                        $data[$key] .= $data['ship_area_0'];
                    }

                    if ($area2Str == '区') {
                        if ($tagConfig['district'] == '1') {
                            $data[$key] .= $data['ship_area_1'];
                        } else {
                            $data[$key] .= $data['ship_area_1'] . $data['ship_area_2'];
                        }
                    } elseif ($area2Str == '市') {
                        if ($tagConfig['city'] == '1') {
                            $data[$key] .= $data['ship_area_1'] . $data['ship_area_2'];
                        } else {
                            $data[$key] .= $data['ship_area_2'] ? $data['ship_area_2'] : $data['ship_area_1'];
                        }
                    } else {
                        if ($tagConfig['county'] == '1') {
                            $data[$key] .= $data['ship_area_2'] ? $data['ship_area_2'] : $data['ship_area_1'];
                        } else {
                            $data[$key] .= $data['ship_area_1'] . $data['ship_area_2'];
                        }
                    }
                }
            }
        }
    }

    /**
     * 获取_goods_bn
     * @param mixed $bn bn
     * @return mixed 返回结果
     */
    public function get_goods_bn($bn)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        $product          = $basicMaterialObj->dump(array('material_bn' => $bn), 'material_bn');

        return $product['material_bn'];
    }

    /**
     * NumToFinanceNum
     * @param mixed $num num
     * @param mixed $mode mode
     * @param mixed $sim sim
     * @return mixed 返回值
     */
    public function NumToFinanceNum($num, $mode = true, $sim = true)
    {
        if (!is_numeric($num)) {
            return '含有非数字非小数点字符！';
        }

        $char = $sim ? array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九')
        : array('零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖');
        $unit = $sim ? array('', '十', '百', '千', '', '万', '亿', '兆')
        : array('', '拾', '佰', '仟', '', '萬', '億', '兆');
        $retval = $mode ? '元' : '点';
        if (strpos($num, '.')) {
            list($num, $dec) = explode('.', $num);
            $dec             = strval(round($dec, 2));
            if ($mode) {
                $retval .= $dec['0'] ? "{$char[$dec['0']]}角" : '';
                $retval .= $dec['1'] ? "{$char[$dec['1']]}分" : '';
            } else {
                for ($i = 0, $c = strlen($dec); $i < $c; $i++) {
                    $retval .= $char[$dec[$i]];
                }
            }
        }

        $prev_num = '';
        $str      = $mode ? strrev(intval($num)) : strrev($num);
        for ($i = 0, $c = strlen($str); $i < $c; $i++) {
            if (($str[$i] == 0 && $i == 0) || ($prev_num == 0 && $str[$i] == 0 && $i > 0) || ($i == 4 && $str[$i] == 0)) {
                $out[$i] = '';
            } else {
                $out[$i] = $char[$str[$i]];
            }

            $prev_num = $str[$i];

            if ($mode) {
                $out[$i] .= $str[$i] != '0' ? $unit[$i % 4] : '';

                if ($i % 4 == 0) {
                    $out[$i] .= $unit[4 + floor($i / 4)];
                }
            }
        }

        $retval = join('', array_reverse($out)) . $retval;
        return $retval;
    }

    /**
     * 获取面单信息
     * @param Array 参数信息
     */
    public function getMainnoInfo($params)
    {
        $mailnoExtend = array();

        $filter       = array('corp_id' => $params['logi_id']);
        $dlyData      = app::get('ome')->model('dly_corp')->dump($filter, 'channel_id,tmpl_type');
        if ($dlyData['tmpl_type'] == 'electron') {
            $sql = "select e.mailno_barcode,e.qrcode,e.`position`,e.position_no,e.package_wdjc,e.package_wd,e.print_config,e.json_packet from sdb_logisticsmanager_waybill w left join sdb_logisticsmanager_waybill_extend e on(w.id = e.waybill_id) where w.waybill_number='" . $params['logi_no'] . "' AND w.channel_id=" . $params['channel_id'] . "";

            $mailnoExtend = kernel::database()->selectrow($sql);
        }
        return $mailnoExtend;
    }

    protected function covertAddslashes(&$items)
    {
        foreach ($items as $k => &$v) {
            if (is_array($v)) {
                $this->covertAddslashes($v);
            } elseif (is_string($v)) {
                $v = str_replace(array('“', '”'), '', $v);

            }

        }

    }
}
