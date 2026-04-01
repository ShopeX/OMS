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


class finance_cronjob_tradeScript
{
    protected $proofTime = 1750;
    /**
     * 定时获取销售数量
     * 
     * @return array
     * @author
     * */
    public function get_sales()
    {
        @ini_set('memory_limit', '512M');@set_time_limit(0);
        $tmp_shop_list = financebase_func::getShopList(financebase_func::getShopType());
        if(empty($tmp_shop_list)) {
            return [false, 'shop_list_empty'];
        }
        $shopId = array_column($tmp_shop_list, 'shop_id');
        $proofIndex = 'finance-trade-sale';
        $proof = cachecore::fetch($proofIndex);
        if($proof == 'running') {
            return [false, 'script_running'];
        }
        cachecore::store($proofIndex, 'running', 3000);
        $financeObj = base_kvstore::instance('setting/finance');

        $now_time = time() - 10; //每次获取时间提前10秒
        $financeObj->fetch("sales_run_time", $sales_run_time);

        $sales_run_time = $sales_run_time ? $sales_run_time : 0;
        $financeObj->fetch('sales_last_run_time', $sales_last_run_time);
        if($sales_run_time) {
            $sales_last_run_time = $sales_last_run_time ? : 0;
            $sql = "select min(s.sale_id) sale_id from sdb_ome_sales s 
                        where s.sale_time >= {$sales_last_run_time} and s.sale_time < {$sales_run_time} and s.shop_id in('".implode("','", $shopId)."')
                            and s.sale_bn not in (select serial_number from sdb_finance_ar)";
            $rs = kernel::database()->selectrow($sql);
            if($rs['sale_id']) {
                $now_time = $sales_run_time;
                $sales_run_time = $sales_last_run_time;
                $last_sale_id = $rs['sale_id'];
            }
        }
        $financeObj->store('sales_last_run_time', $sales_run_time);
        $financeObj->store('sales_run_time', $now_time);

        $saleModel     = app::get('ome')->model('sales');
        $mdlBillAr     = app::get('finance')->model('ar');

        $offset       = 0;
        $limit        = 9000;
        $last_sale_id = $last_sale_id ? ($last_sale_id - 1) : 0;
        while (true) {
            $sales        = array();

            $filter = array(
                'sale_id|than'      => $last_sale_id,
                'sale_time|between' => array(
                    0 => $sales_run_time,
                    1 => $now_time,
                ),
                'shop_id' => $shopId,
            );
            $list = $saleModel->getList('*', $filter, 0, $limit, ' sale_id ');

            if (!$list) {
                break;
            }
            $last = end($list);
            $last_sale_id = $last['sale_id'];

            $list = array_column($list, null, 'sale_bn');
            // 判断是否重复
            $ar_exist_list = $mdlBillAr->getList('serial_number', array('serial_number|in' => array_keys($list)));
            if ($ar_exist_list) {
                foreach ($ar_exist_list as $v) {
                    unset($list[$v['serial_number']]);
                }
            }
            if (!$list) {
                continue;
            }
            $this->dealSales($list);
            if(time() - $now_time > $this->proofTime) {
                break;
            }
        }
        cachecore::store($proofIndex, 'finish', 1);

        return [true, 'success',['sale_time_start'=>$sales_run_time, 'sale_time_end'=>$now_time]];
    }

        /**
     * dealSales
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function dealSales($list) {
        $saleItemModel = app::get('ome')->model('sales_items');
        $orderModel    = app::get('ome')->model('orders');
        $sale_id = [];
        $order_id = [];
        $arrShop = app::get('ome')->model('shop')->getList('shop_id, shop_type, name');
        $arrShop = array_column($arrShop, null, 'shop_id');
        $init_time = app::get('finance')->getConf('finance_setting_init_time');
        $orders = array();
        $sales_items = array();
        if ($list) {
            foreach ($list as $k => $v) {
                if($init_time['ying_shou'] == 'settlement') {
                    $sale_amount = $v['settlement_amount'];
                } elseif($init_time['ying_shou'] == 'actually') {
                    $sale_amount = $v['actually_amount'];
                } else {
                    $sale_amount = $v['sale_amount'];
                }
                // 计算平台承担金额 = 结算金额 - 客户实付金额
                $platform_amount = bcsub($v['settlement_amount'], $v['actually_amount'], 3);
                
                $sales[$v['sale_id']] = array(
                    'sale_time'     => $v['sale_time'],
                    'delivery_time' => $v['ship_time'],
                    'member_id'     => $v['member_id'],
                    'sale_amount'   => bcsub($sale_amount, $v['cost_freight'], 3), //$v['sale_amount'],
                    'delivery_cost' => $v['cost_freight'],
                    'actually_amount' => $v['actually_amount'],
                    'platform_amount' => $platform_amount,
                    'sale_bn'       => $v['sale_bn'],
                    'iostock_type'  => &$orders[$v['order_id']]['iostock_type'],
                    'sales_items'   => &$sales_items[$v['sale_id']],
                    'order_id'      => &$orders[$v['order_id']]['order_id'],
                    'order_bn'      => &$orders[$v['order_id']]['order_bn'],
                    'relate_order_bn' => &$orders[$v['order_id']]['relate_order_bn'],
                    'shop_id'       => &$orders[$v['order_id']]['shop_id'],
                    'shop_name'     => &$orders[$v['order_id']]['shop_name'],
                );
                if($v['platform_order_bn']) {
                    $orders[$v['order_id']]['order_bn'] = $v['platform_order_bn'];
                }
                $sale_id[]  = $v['sale_id'];
                $order_id[] = $v['order_id'];

            }

            $list = $orderModel->getList('order_id,order_bn,relate_order_bn,shop_id,createway,order_type', array('order_id' => $order_id));
            foreach ($list as $k => $v) {
                $orders[$v['order_id']]['order_id']  = $v['order_id'];

                if($v['order_type'] == 'custom') {
                    // 定制类订单
                    $orders[$v['order_id']]['order_bn']         = $v['relate_order_bn'];
                    $orders[$v['order_id']]['relate_order_bn']  = $v['order_bn'];
                    $orders[$v['order_id']]['iostock_type']     = 'SALE_STORAGE';

                } elseif($v['createway'] == 'after') {
                    $orders[$v['order_id']]['relate_order_bn']  = $v['order_bn'];
                    if(empty($orders[$v['order_id']]['order_bn'])) {
                        $orders[$v['order_id']]['order_bn']  = $this->_getOrderBn(['order_bn'=>$v['relate_order_bn']])['order_bn'];
                    }
                    $orders[$v['order_id']]['iostock_type'] = 'RE_STORAGE';
                } else {
                    $orders[$v['order_id']]['order_bn']  = $v['order_bn'];
                    $orders[$v['order_id']]['relate_order_bn']  = $v['relate_order_bn'];
                    $orders[$v['order_id']]['iostock_type'] = 'SALE_STORAGE';
                }
                $orders[$v['order_id']]['shop_id']   = $v['shop_id'];
                $orders[$v['order_id']]['shop_name'] = $arrShop[$v['shop_id']]['name'];
            }

            $iOffset = 0;
            $iLimit  = 9000;
            do {
                $list = $saleItemModel->getList('*', array('sale_id' => $sale_id), $iOffset, $iLimit);
                if (empty($list)) {
                    break;
                }
                foreach ($list as $k => $v) {
                    if($init_time['ying_shou'] == 'settlement') {
                        $v['sales_amount'] = $v['settlement_amount'];
                    } elseif($init_time['ying_shou'] == 'actually') {
                        $v['sales_amount'] = $v['actually_amount'];
                    }
                    // 计算平台承担金额 = 结算金额 - 客户实付金额
                    $item_platform_amount = bcsub($v['settlement_amount'], $v['actually_amount'], 3);
                    
                    $sales_items[$v['sale_id']][] = array(
                        'bn'           => $v['bn'],
                        'name'         => $v['name'],
                        'nums'         => $v['nums'],
                        'sales_amount' => $v['sales_amount'],
                        'actually_amount' => $v['actually_amount'],
                        'platform_amount' => $item_platform_amount,
                        'shop_name'    => $sales[$v['sale_id']]['shop_name'],
                        'shop_id'      => $sales[$v['sale_id']]['shop_id'],
                        'order_id'     => $sales[$v['sale_id']]['order_id'],
                        'order_bn'     => $sales[$v['sale_id']]['order_bn'],
                    );
                }

                $iOffset += $iLimit;
            } while (true);
        }

        foreach ($sales as $sale_id => $value) {
            $data = array('sales' => $value);
            kernel::single('finance_iostocksales')->do_iostock_sales_data($data);
        }
    }

    /**
     * _getOrderBn
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function _getOrderBn($filter) {
        $field = 'order_id, order_bn, createway, relate_order_bn, order_type';
        $order = app::get('ome')->model('orders')->db_dump($filter, $field);
        if(empty($order)) {
            $order = app::get('archive')->model('orders')->db_dump($filter, $field);
        }

        // 如果是定制订单的退款不进AR
        if ($order['order_type'] == 'custom') {
            return ['order_bn' => $order['relate_order_bn']];
        }

        if($order['createway'] == 'after' && $order['relate_order_bn']) {
            return $this->_getOrderBn(['order_bn'=>$order['relate_order_bn']]);
        }
        return $order;
    }

    /**
     * 获取费用
     * 
     * @return void
     * @author
     * */
    public function get_bills()
    {
        // 以下时间段不请求接口
        $denytime = array(
            0 => array(mktime(9, 30, 0, date('m'), date('d'), date('Y')), mktime(11, 0, 0, date('m'), date('d'), date('Y'))),
            1 => array(mktime(14, 0, 0, date('m'), date('d'), date('Y')), mktime(17, 0, 0, date('m'), date('d'), date('Y'))),
            2 => array(mktime(20, 0, 0, date('m'), date('d'), date('Y')), mktime(22, 30, 0, date('m'), date('d'), date('Y'))),
            2 => array(mktime(1, 0, 0, date('m'), date('d'), date('Y')), mktime(3, 0, 0, date('m'), date('d'), date('Y'))),
        );

        $now = time();
        foreach ($denytime as $value) {
            if ($value[0] <= $now && $now <= $value[1]) {
                return false;
            }
        }

        $run_time   = 120; //每次执行脚本的时间间隔 单位 分钟
        $time       = time();
        $financeObj = base_kvstore::instance('setting/finance');
        $financeObj->fetch("bills_get", $last_time);

        #获取账单日
        $billObj     = kernel::single('finance_monthly_report');
        $bill_status = $billObj->get_init_time();

        $last_time = $last_time ? $last_time : 0;
        if ($time - $run_time * 60 - $last_time > $run_time * 60) {
            #设置脚本最后执行时间
            $financeObj->store("bills_get", $time);

            #店铺信息:淘宝已授权
            $funcObj   = kernel::single('finance_func');
            $shop_list = $funcObj->shop_list(array('tbbusiness_type' => 'B', 'node_type' => 'taobao'));
            if ($shop_list) {
                $worker = 'finance_cronjob_execQueue.bills_get';
                foreach ($shop_list as $shop) {
                    if (!$shop['node_id']) {
                        continue;
                    }

                    unset($last_end_time);

                    //店铺上一次同步时间,为空,则取当前脚本的最后执行时间
                    $financeObj->fetch("shop_bills_get_" . $shop['node_id'], $last_end_time);
                    if (empty($last_end_time)) {
                        // 取期当前时间之前一个月
                        $last_end_time = time() - 30 * 24 * 60 * 60;
                    }

                    #时间范围超过6天的处理逻辑:将此时间范围分成每隔5天进行获取
                    $day2second = 23 * 60 * 60;
                    // $last_end_time = $shop_start_time;
                    if (($last_end_time + $day2second) > $time) {
                        continue;
                    }

                    $max = 1;
                    do {
                        if ($max > 99) {
                            break;
                        }

                        $next_start_time = $last_end_time;
                        $next_end_time   = $last_end_time + $day2second;

                        if ($next_end_time > $time) {
                            break;
                        }

                        #添加队列
                        $params = array(
                            'start_time' => date('Y-m-d H:i:s', $next_start_time - 5 * 60), #交易开始时间提前5分钟,确保不遗漏
                            'end_time'   => date('Y-m-d H:i:s', $next_end_time),
                            'node_name'  => $shop['name'],
                            'node_id'    => $shop['node_id'],
                            'shop_id'    => $shop['shop_id'],
                        );
                        $log_title = '请求账单数据:' . $params['start_time'] . '至' . $params['end_time'] . '';
                        if (!$funcObj->addTask($log_title, $worker, $params, $type = 'slow')) {
                            #添加日志:失败
                            $log_type = 'bills_get';
                            $logObj   = kernel::single('finance_tasklog');
                            $log_id   = $logObj->write_log($log_title, $log_type, $params, $status = 'fail', $msg = '添加队列失败');
                        }

                        $max++;
                        $last_end_time = $next_end_time;
                    } while (1);

                    //更新当前店铺的最后执行时间
                    $financeObj->store("shop_bills_get_" . $shop['node_id'], $last_end_time);
                }
            }
        }
    }

    /**
     * 获取虚拟账户明细数据
     * 
     * @return void
     * @author
     * */
    public function get_book_bills()
    {
        $run_time = 360; //每次执行脚本的时间间隔 单位 分钟
        $time     = time();

        // 上次脚本执行的时间
        $financeObj = base_kvstore::instance('setting/finance');
        $financeObj->fetch("book_bills_get", $last_time);

        if (($time - $last_time) <= $run_time * 60) {
            return false;
        }

        // 记录本次脚本执行的时间
        $financeObj->store("book_bills_get", $time);

        $feeItemModel = app::get('finance')->model('bill_fee_item');
        $feeItemList  = $feeItemModel->getList('outer_account_id');

        $funcObj  = kernel::single('finance_func');
        $worker   = 'finance_cronjob_execQueue.sync_book_bills_get';
        $shopList = $funcObj->shop_list(array('tbbusiness_type' => 'B', 'node_type' => 'taobao'));
        foreach ($shopList as $shop) {
            if (!$shop['node_id']) {
                continue;
            }

            unset($shop_last_endtime);

            //店铺上一次同步时间,为空,则取当前脚本的最后执行时间
            $financeObj->fetch("shop_book_bills_get_" . $shop['node_id'], $shop_last_endtime);
            if (!$shop_last_endtime) {
                // 取一个月之前的数量
                $shop_last_endtime = $time - 30 * 24 * 60 * 60;
            }

            $day2second = 24 * 60 * 60;

            if (($shop_last_endtime + $day2second) > $time) {
                continue;
            }

            $max = 1;
            do {
                if ($max >= 2) {
                    break;
                }

                $shop_next_starttime = $shop_last_endtime;
                $shop_last_endtime   = $shop_next_starttime + $day2second;

                $params = array(
                    'start_time' => date('Y-m-d H:i:s', $shop_next_starttime - 5 * 60), #交易开始时间提前5分钟,确保不遗漏
                    'end_time'   => date('Y-m-d H:i:s', $shop_last_endtime),
                    'node_name'  => $shop['name'],
                    'node_id'    => $shop['node_id'],
                    'shop_id'    => $shop['shop_id'],
                    'page_no'    => 1,
                    'page_size'  => 50,
                    // 'account_id' => $fee_item['outer_account_id'],
                );

                foreach ($feeItemList as $fee_item) {
                    if (!$fee_item['outer_account_id']) {
                        continue;
                    }
                    $params['account_id'] = $fee_item['outer_account_id'];

                    $log_title = '请求获取虚拟账户明细:' . $params['start_time'] . '至' . $params['end_time'] . '';
                    if (!$funcObj->addTask($log_title, $worker, $params, $type = 'slow')) {
                        #添加日志:失败
                        $log_type = 'book_bills_get';
                        $logObj   = kernel::single('finance_tasklog');
                        $log_id   = $logObj->write_log($log_title, $log_type, $params, $status = 'fail', $msg = '添加队列失败');
                    }
                }

                $max++;
            } while (1);

            $financeObj->store("shop_book_bills_get_" . $shop['node_id'], $shop_last_endtime);
        }
    }

    /**
     * 定时获取应退单
     * 
     * @return array
     * @author
     * */
    public function get_aftersales()
    {
        $tmp_shop_list = financebase_func::getShopList(financebase_func::getShopType());
        if(empty($tmp_shop_list)) {
            return [false, 'shop_list_empty'];
        }
        $shopId = array_column($tmp_shop_list, 'shop_id');
        $proofIndex = 'finance-trade-aftersale';
        $proof = cachecore::fetch($proofIndex);
        if($proof == 'running') {
            return [false, 'script_running'];
        }
        cachecore::store($proofIndex, 'running', 3000);
        $financeObj             = base_kvstore::instance('setting/finance');
        $mdlSalesAftersale      = app::get('sales')->model('aftersale');
        $mdlBillAr              = app::get('finance')->model('ar');

        $now_time = time() - 10; //每次获取时间提前10秒
        $financeObj->fetch("aftersales_run_time", $aftersales_run_time);
        $aftersales_run_time = $aftersales_run_time ? $aftersales_run_time : 0;
        $financeObj->fetch('aftersales_last_run_time', $aftersales_last_run_time);
        if($aftersales_run_time) {
            $aftersales_last_run_time = $aftersales_last_run_time ? : 0;

            $sql = "select min(s.aftersale_id) aftersale_id from sdb_sales_aftersale s 
                        where s.aftersale_time >= {$aftersales_last_run_time} and s.aftersale_time < {$aftersales_run_time} and s.shop_id in('".implode("','", $shopId)."') 
                            and s.aftersale_bn not in (select serial_number from sdb_finance_ar a where a.trade_time >= {$aftersales_last_run_time} and a.trade_time < {$aftersales_run_time})";
            $rs = kernel::database()->selectrow($sql);
            if($rs['aftersale_id']) {
                $now_time = $aftersales_run_time;
                $aftersales_run_time = $aftersales_last_run_time;
                $last_aftersale_id = $rs['aftersale_id'];
            }
        }
        $financeObj->store("aftersales_run_time", $now_time);
        $financeObj->store("aftersales_last_run_time", $aftersales_run_time);
        $aftersale_id = $last_aftersale_id ? ($last_aftersale_id - 1) :0;
        $page_size    = 200;

        while (true) {
            $filter = array('aftersale_id|than' => $aftersale_id, 'aftersale_time|between' => [$aftersales_run_time, $now_time], 'shop_id'=>$shopId);
            $list   = $mdlSalesAftersale->getList('*', $filter, 0, $page_size, 'aftersale_id');
            if (!$list) {
                break;
            }
            $last = end($list);
            $aftersale_id = $last['aftersale_id'];
            $list = array_column($list, null, 'aftersale_bn');

            // 判断是否有重复数据
            $ar_list = $mdlBillAr->getList('serial_number', array('serial_number|in' => array_keys($list)));

            if ($ar_list) {
                foreach ($ar_list as $v) {
                    unset($list[$v['serial_number']]);
                }

            }
            if (!$list) {
                continue;
            }
            $this->dealAftersale($list);
            if(time() - $now_time > $this->proofTime) {
                break;
            }
        }
        cachecore::store($proofIndex, 'finish', 1);

        return [true, 'success',['aftersales_time_start'=>$aftersales_run_time, 'aftersales_time_end'=>$now_time]];
    }

        /**
     * dealAftersale
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function dealAftersale($list) {
        $mdlSalesAftersaleItems = app::get('sales')->model('aftersale_items');
        $mdlBillAr              = app::get('finance')->model('ar');
        $mdlBillArItem          = app::get('finance')->model('ar_items');
        $oBillAr                = kernel::single('finance_ar');
        $oFunc                  = kernel::single('financebase_func');
        $arrOrderId = array_column($list, 'order_id');
        $arrSale = app::get('ome')->model('sales')->getList('order_id, ship_time, sale_id,order_type', ['order_id'=>$arrOrderId]);
        $arrSale = array_column($arrSale, null, 'order_id');
        $arrShop   = app::get('ome')->model('shop')->getList('shop_id, shop_type');
        $arrShop = array_column($arrShop, null, 'shop_id');
        foreach ($list as $sdf) {
            $aftersale_id = $sdf['aftersale_id'];

            $init_time = app::get('finance')->getConf('finance_setting_init_time');
            if($init_time['ying_tui'] == 'settlement' && $sdf['return_type'] != 'refund') {
                $sdf['refundmoney'] = $sdf['settlement_amount'];
            }
            if($init_time['ying_tui'] == 'apply') {
                $sdf['refundmoney'] = $sdf['refund_apply_money'];
            }
            if($init_time['ying_tui'] == 'real_refund_amount') {
                $sdf['refundmoney'] = $sdf['real_refund_amount'];
            }

            // 如果是定制订单的退款不进AR
            if ($arrSale[$sdf['order_id']]['order_type'] == 'custom' && $sdf['return_type'] == 'refund') {
                continue;
            }

            //0元不转
            // 开始处理
            $db = kernel::database();
            $db->beginTransaction();

            try {
                $money = sprintf('%.2f', $sdf['refundmoney'] * -1);
                $relate_order_bn                       = $sdf['reship_bn'];
                !$relate_order_bn and $relate_order_bn = $sdf['return_apply_bn'];
                !$relate_order_bn and $relate_order_bn = $sdf['diff_order_bn'];
                !$relate_order_bn and $relate_order_bn = $sdf['order_bn'];

                // 计算平台承担金额 = 结算金额 - 客户实付金额（退款为负数）
                $platform_amount = sprintf('%.3f', ($sdf['settlement_amount'] - $sdf['actually_amount']) * -1);

                $ar_data = array(
                    'ar_bn'                 => $oBillAr->gen_ar_bn(),
                    'trade_time'            => $sdf['aftersale_time'],
                    'delivery_time'         => $arrSale[$sdf['order_id']]['ship_time'],
                    'create_time'           => time(), #单据进入系统的时间
                    'member'                => $sdf['member_uname'],
                    'type'                  => $oBillAr->get_name_by_return_type($sdf['return_type'], $sdf['reship_bn']),
                    'order_bn'              => $sdf['platform_order_bn'] ? : $this->_getOrderBn(['order_bn'=>$sdf['order_bn']])['order_bn'],
                    'crc32_order_bn'        => sprintf('%u', crc32($sdf['order_bn'])),
                    'relate_order_bn'       => $relate_order_bn,
                    'crc32_relate_order_bn' => $relate_order_bn ? sprintf('%u', crc32($relate_order_bn)) : null,
                    'channel_id'            => $sdf['shop_id'],
                    'channel_name'          => $sdf['shop_name'],
                    'unconfirm_money'       => $money,
                    'charge_status'         => 1,
                    'charge_time'           => time(),
                    'money'                 => $money,
                    'actually_money'        => sprintf('%.2f', $sdf['actually_amount'] * -1),
                    'platform_amount'       => $platform_amount,
                    'serial_number'         => $sdf['aftersale_bn'],
                    'unique_id'             => md5($sdf['aftersale_bn']),
                    'ar_type'               => 1,
                    'verification_flag'     => 0,
                    'addon'                 => '',
                    'memo'                  => '',
                );

                $ar_data['monthly_id']     = 0;
                $ar_data['monthly_item_id']     = 0;
                $ar_data['monthly_status'] = 0;

                $ar_id = $mdlBillAr->insert($ar_data);

                if (!$ar_id) {
                    throw new Exception('插入ar单据表失败');
                }

                $aftersale_items = $mdlSalesAftersaleItems->getList('bn,num,refunded,settlement_amount,actually_amount,platform_amount,product_name', array(
                    'aftersale_id' => $sdf['aftersale_id'],
                    'return_type' => ['return','refunded','refuse'],
                ));

                if ($aftersale_items) {
                    $m = array_sum(array_column($aftersale_items, 'refunded'));
                    if($m>0.01) {
                        $aftersale_items = kernel::single('ome_order')->calculate_part_porth($aftersale_items,array (
                            'part_total'  => $sdf['real_refund_amount'],
                            'part_field'  => 'real_refund_amount',
                            'porth_field' => 'refunded',
                        ));
                    }
                    foreach ($aftersale_items as $v) {
                        // 计算平台承担金额 = 结算金额 - 客户实付金额（退款为负数）
                        $item_platform_amount = sprintf('%.3f', ($v['settlement_amount'] - $v['actually_amount']) * -1);
                        
                        $ar_item = array(
                            'ar_id' => $ar_id,
                            'bn'    => $v['bn'],
                            'name'  => $v['product_name'],
                            'num'   => $v['num'],
                            'money' => sprintf('%.2f', $v['refunded'] * -1),
                            'actually_money' => sprintf('%.2f', $v['actually_amount'] * -1),
                            'platform_amount' => $item_platform_amount,
                        );
                        if($init_time['ying_tui'] == 'settlement' && $sdf['return_type'] != 'refund') {
                            $ar_item['money'] = sprintf('%.2f', $v['settlement_amount'] * -1);
                        }
                        if($init_time['ying_tui'] == 'real_refund_amount') {
                            $ar_item['money'] = (float) $v['real_refund_amount'];
                        }
                        if (!$mdlBillArItem->insert($ar_item)) {
                            throw new Exception('插入ar_items表失败');
                        }
                    }
                }
                $db->commit();
                kernel::single('finance_monthly_report_items')->dealArMatchReport($ar_id);
            } catch (Exception $e) {
                $db->rollBack();
                $msg = $e->getMessage();
                $oFunc->writelog('导入应退单' . $sdf['aftersale_bn'] . '出错：', 'aftersales', $msg);
                /**kernel::single('monitor_event_notify')->addNotify('finance_refunds', [
                    'bn' => $ar_data['serial_number'],
                    'errmsg'      => $msg,
                ]);**/
            }

        }
    }
    /**
     * 定时获取赔付单
     * 
     * @return array
     * @author
     * */
    public function get_compensate()
    {
        $tmp_shop_list = financebase_func::getShopList(financebase_func::getShopType());
        if(empty($tmp_shop_list)) {
            return [false, 'shop_list_empty'];
        }
        $shopId = array_column($tmp_shop_list, 'shop_id');
        $proofIndex = 'finance-trade-compensate';
        $proof = cachecore::fetch($proofIndex);
        if($proof == 'running') {
            return [false, 'script_running'];
        }
        cachecore::store($proofIndex, 'running', 3000);
        $financeObj             = base_kvstore::instance('setting/finance');
        $mdlCompensate          = app::get('ome')->model('compensate_record');
        $mdlBillAr              = app::get('finance')->model('ar');

        $now_time = time() - 10; //每次获取时间提前10秒
        $financeObj->fetch("compensate_run_time", $compensate_run_time);
        $compensate_run_time = $compensate_run_time ? $compensate_run_time : 0;
        $financeObj->fetch('compensate_last_run_time', $compensate_last_run_time);
        if($compensate_run_time) {
            $compensate_last_run_time = $compensate_last_run_time ? : 0;
            $format_compensate_last_run_time = date('Y-m-d H:i:s', $compensate_last_run_time);
            $format_compensate_run_time = date('Y-m-d H:i:s', $compensate_run_time);
            $sql = "select min(s.id) compensate_id from sdb_ome_compensate_record s 
                        where s.up_time >= '{$format_compensate_last_run_time}' and s.up_time < '{$format_compensate_run_time}' and s.shop_id in('".implode("','", $shopId)."') 
                            and s.compensate_bn not in (select serial_number from sdb_finance_ar a where a.trade_time >= {$compensate_last_run_time} and a.trade_time < {$compensate_run_time})";
            $rs = kernel::database()->selectrow($sql);
            if($rs['compensate_id']) {
                $now_time = $compensate_run_time;
                $compensate_run_time = $compensate_last_run_time;
                $last_compensate_id = $rs['compensate_id'];
            }
        }
        $financeObj->store("compensate_run_time", $now_time);
        $financeObj->store("compensate_last_run_time", $compensate_run_time);
        $compensate_id = $last_compensate_id ? ($last_compensate_id - 1) :0;
        $page_size    = 200;

        while (true) {
            $filter = array('id|than' => $compensate_id, 'up_time|betweenstr' => [date('Y-m-d H:i:s',$compensate_run_time), date('Y-m-d H:i:s',$now_time)], 'shop_id'=>$shopId);
            $list   = $mdlCompensate->getList('*', $filter, 0, $page_size, 'id');
            if (!$list) {
                break;
            }
            $last = end($list);
            $compensate_id = $last['id'];
            $list = array_column($list, null, 'compensate_bn');

            // 判断是否有重复数据
            $ar_list = $mdlBillAr->getList('serial_number', array('serial_number|in' => array_keys($list)));

            if ($ar_list) {
                foreach ($ar_list as $v) {
                    unset($list[$v['serial_number']]);
                }

            }
            if (!$list) {
                continue;
            }
            $this->dealCompensate($list);
            if(time() - $now_time > $this->proofTime) {
                break;
            }
        }
        cachecore::store($proofIndex, 'finish', 1);

        return [true, 'success',['compensate_time_start'=>$compensate_run_time, 'compensate_time_end'=>$now_time]];
    }

        /**
     * dealCompensate
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function dealCompensate($list) {
        $mdlBillAr              = app::get('finance')->model('ar');
        $oBillAr                = kernel::single('finance_ar');
        $oFunc                  = kernel::single('financebase_func');
        $arrOrderId = array_column($list, 'order_id');
        $arrSale = app::get('ome')->model('sales')->getList('order_id, ship_time, sale_id', ['order_id'=>$arrOrderId]);
        $arrSale = array_column($arrSale, null, 'order_id');
        $arrShop = app::get('ome')->model('shop')->getList('shop_id, shop_type, name');
        $arrShop = array_column($arrShop, null, 'shop_id');
        foreach ($list as $sdf) {

            //0元不转
            // 开始处理
            $db = kernel::database();
            $db->beginTransaction();

            try {
                $money = sprintf('%.2f', $sdf['compensateamount'] * -1);

                $ar_data = array(
                    'ar_bn'                 => $oBillAr->gen_ar_bn(),
                    'trade_time'            => strtotime($sdf['outer_created']),
                    'delivery_time'         => $arrSale[$sdf['order_id']]['ship_time'],
                    'create_time'           => time(), #单据进入系统的时间
                    'member'                => '',
                    'type'                  => '12',
                    'order_bn'              => $sdf['order_bn'],
                    'crc32_order_bn'        => sprintf('%u', crc32($sdf['order_bn'])),
                    'relate_order_bn'       => $sdf['compensate_bn'],
                    'crc32_relate_order_bn' => $sdf['compensate_bn'] ? sprintf('%u', crc32($sdf['compensate_bn'])) : null,
                    'channel_id'            => $sdf['shop_id'],
                    'channel_name'          => $arrShop[$sdf['shop_id']]['name'],
                    'unconfirm_money'       => $money,
                    'charge_status'         => 1,
                    'charge_time'           => time(),
                    'money'                 => $money,
                    'serial_number'         => $sdf['compensate_bn'],
                    'unique_id'             => md5($sdf['compensate_bn']),
                    'ar_type'               => 1,
                    'verification_flag'     => 0,
                    'addon'                 => '',
                    'memo'                  => '',
                );
                $ar_data['monthly_id']     = 0;
                $ar_data['monthly_item_id']     = 0;
                $ar_data['monthly_status'] = 0;

                $ar_id = $mdlBillAr->insert($ar_data);

                if (!$ar_id) {
                    throw new Exception('插入ar单据表失败');
                }

                $db->commit();
                kernel::single('finance_monthly_report_items')->dealArMatchReport($ar_id);
            } catch (Exception $e) {
                $db->rollBack();
                $msg = $e->getMessage();
                $oFunc->writelog('导入应退单' . $sdf['compensate_bn'] . '出错：', 'compensate', $msg);
                /**kernel::single('monitor_event_notify')->addNotify('finance_refunds', [
                    'bn' => $ar_data['serial_number'],
                    'errmsg'      => $msg,
                ]);**/
            }

        }
    }
}
