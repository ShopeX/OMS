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
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/27
 * @Describe: 自动开票定时任务
 */

class invoice_autotask_timer_makeoutinvoice
{
    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '512M');
        $key   = 'invoice_autotask_timer_makeoutinvoice';
        $isRun = cachecore::fetch($key);
        
        if ($isRun) {
            $error_msg = 'is running';
            return false;
        }
        $db = kernel::database();
        //订单是完成状态、发票表状态是空的时候
        cachecore::store($key, 'running', 595);

        $time = time();
        base_kvstore::instance('invoice/make_out_invoice')->fetch('make-out-invoice-lastexectime',$lastExecTime);
        base_kvstore::instance('invoice/make_out_invoice')->store('make-out-invoice-lastexectime',$time);

        $where = '';
        if ($lastExecTime) {
            $where = " AND b.last_modified >= ".$lastExecTime .' AND b.last_modified < ' . $time;
        }
        $this->updateInvoiceInfo($where);
        //系统自动开票
        $this->invoiceBill();
    }

    public function updateInvoiceInfo($where)
    {
        $offset = 0;
        $limit  = 500;
        $db = kernel::database();

        $field = 'a.*,
            b.source_status as order_source_status,
            b.ship_status,
            b.payed,
            b.pay_status';
        do {
            //查询完结的单据
            $sql = "SELECT
                        $field
                    FROM
                        sdb_invoice_order AS a,
                        sdb_ome_orders AS b
                    WHERE
                        a.order_bn = b.order_bn
                        AND a.is_status = '0'
                        AND a.is_make_invoice = '0'
                        ". $where ."
                    LIMIT " . $offset . ", " . $limit;

            $invoiceList = $db->select($sql);

            if (empty($invoiceList)) {
                break;
            }

            if ($invoiceList) {
                foreach ($invoiceList as $val) {
                    //过滤pos，门店类型是BTQ、Trade
                    if ($val['shop_type'] == 'pekon') {
                        $store = app::get('o2o')->model('store')->db_dump(['shop_id' => $val['shop_id']], 'store_sort');
                        if ($store && in_array($store['store_sort'], ['BTQ', 'Trade'])) {
                            continue;
                        }
                    }
                    //更新状态与明细
                    $this->updateInvoiceOrder($val);
                }
            }

            $offset += $limit;
        } while (true);
    }

    public function updateInvoiceOrder($params)
    {
        $db = kernel::database();
        $opObj  = app::get('ome')->model('operation_log');
        $updateContent = "update_time=" . time();

        $shopInfo = $this->getShop($params['shop_id']);
        $isMakeInvoice = 0;
        if ($shopInfo['einvoice_operating_conditions'] == '1') {//发货完成
            if ($params['ship_status'] == '1' && $params['is_make_invoice'] == '0' && $params['is_status'] == '0' && $params['sync'] == '0') {
                $updateContent .= ",is_make_invoice='1'";
                $isMakeInvoice = '1';
                $opObj->write_log('invoice_edit@invoice', $params['id'], '订单已发货发票可进行开票');
            }
        }else if ($shopInfo['einvoice_operating_conditions'] == '3') {//订单完成
            //更新invoiceOrder::source_status状态
            if ($params['order_source_status'] == 'TRADE_FINISHED' && $params['source_status'] != 'TRADE_FINISHED') {
                $updateContent .= ",source_status='TRADE_FINISHED'";
                if ($params['is_status'] == '0' && $params['sync'] == '0') {
                    $isMakeInvoice = '1';
                    $updateContent .= ",is_make_invoice='1'";
                }
                $opObj->write_log('invoice_edit@invoice', $params['id'], '订单签收完成发票可进行开票');
            }
        }else if (empty($shopInfo)){
            $opObj->write_log('invoice_edit@invoice', $params['id'], '订单开票渠道信息未配置');
        }

        //全额退款未开票 作废发票
        if ($params['pay_status'] == '5' && $params['is_status'] == '0') {
            $updateContent .= ",is_status='2'";
            $opObj->write_log('invoice_cancel@invoice', $params['id'], '全额退款发票作废');
            // 作废ITEM数据
            $eInvoidItemMdl = app::get('invoice')->model('order_electronic_items');
            $eInvoidItemMdl->update(['invoice_status' => '2'],[
                'id' => $params['id'],
                'invoice_status' => ['99','10']
            ]);
        }
        //部分退款未开票 更新发票金额和税金
        if (($params['pay_status'] == '4' && $params['is_status'] == '0') ) {
            $amount = $params['payed'];
            list($rs, $rsData) = kernel::single('invoice_order')->getInvoiceMoney($params);
            if ($rs) {
                $amount = $rsData['amount'];
            }
            $cost_tax = kernel::single('invoice_func')->get_invoice_cost_tax($amount, $params["tax_rate"]);
            if ($params['amount'] != $amount && $amount > 0) {
                $updateContent .= ",amount=$amount,cost_tax=$cost_tax";
                $logMsg        = '部分退款更新发票金额，原发票金额：' . $params['amount'] . '，修改后金额：' . $amount;
                //专票部分退货进行自动进入待开
                if ($params['mode'] == '0' && $params['is_make_invoice'] == '0' && $isMakeInvoice == '1') {
                    $updateContent .= ",is_make_invoice='1'";
                }
                $opObj->write_log('invoice_edit@invoice', $params['id'], $logMsg);
            }
        }
        //纸票
        if ($params['mode'] == '0') {
            if ($params['is_status'] == '1') {
                $updateContent .= ",is_make_invoice='2'";
            }
        }
        $db->exec("UPDATE sdb_invoice_order SET $updateContent WHERE id=" . $params['id']);
        //纸票
        if ($params['mode'] == '0') {
            $this->getInvoiceContent($params);
        }
    }

    /**
     * 获取开票明细
     * @Author: xueding
     * @Vsersion: 2023/1/11 下午3:49
     * @param $rs_invoice
     */
    public function getInvoiceContent($rs_invoice)
    {
        $opObj  = app::get('ome')->model('operation_log');
        $opObj->write_log('invoice_edit@invoice', $rs_invoice['id'], '更新发票明细');
        $mdlInOrder = app::get('invoice')->model('order');
        $info = $mdlInOrder->db_dump( $rs_invoice['id']);
        kernel::single('invoice_sales_data')->generate($info);
    }

    protected function getShop($shop_id)
    {
        static $shop_list;

        if (isset($shop_list[$shop_id])) return $shop_list[$shop_id];

        $shop_list[$shop_id] = app::get('invoice')->model('channel')->get_channel_info($shop_id);

        return $shop_list[$shop_id];
    }
    
    /**
     * 系统自动开蓝票
     * @Author: xueding
     * @Vsersion: 2023/3/12 下午12:48
     */
    public function invoiceBill()
    {
        if (app::get('ome')->getConf('ome.order.autoinvoice') != 'on') {
            return false;
        }

        $db = kernel::database();
        $offset = 0;
        $limit  = 500;

        //配置为空不进行处理
        if (!app::get('invoice')->model('order_setting')->db_dump([])) {
            return false;
        }

        //自发店铺
        $selfShop = app::get('ome')->model('shop')->getList('shop_id',['delivery_mode'=>'self']);
        if (!$selfShop) {
            return false;
        }

        $channelMdl = app::get('invoice')->model('channel');
        $shop_id = [];
        foreach ($selfShop as $shop) {
            $channelInfo = $channelMdl->get_channel_info($shop['shop_id']);
            if (!empty($channelInfo)) {
                $shop_id[] = $shop['shop_id'];
            }
        }
        if (!$shop_id) {
            return false;
        }

        do {
            //查询完结的单据
            $sql = "SELECT
                        id,order_id,shop_type,shop_id
                    FROM
                        sdb_invoice_order
                    WHERE
                        mode = '1'
                        AND is_status = '0'
                        AND sync = '0'
                        AND is_make_invoice = '1'
                        AND shop_id IN ('".implode("','", $shop_id)."')
                    LIMIT " . $offset . ", " . $limit;
    
            $invoiceList = $db->select($sql);
        
            if (empty($invoiceList)) {
                break;
            }
        
            if ($invoiceList) {
                foreach ($invoiceList as $val) {
                    //过滤pos，门店类型是BTQ、Trade
                    if ($val['shop_type'] == 'pekon') {
                        $store = app::get('o2o')->model('store')->db_dump(['shop_id'=>$val['shop_id']],'store_sort');
                        if ($store && in_array($store['store_sort'],['BTQ','Trade'])) {
                            continue;
                        }
                    }
                    
                    //蓝票自动开票
                    kernel::single('invoice_process')->billing(['id' => $val['id'], 'order_id' => $val['order_id'], 'msg' => '系统自动开票']);
                    /**@used-by sales_aftersale::generate_aftersale  50行--售后自动冲红 * */
                }
            }
        
            $offset += $limit;
        } while (true);
    }
    
}