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

class invoice_autotask_timer_redapply_sync
{
    // 脚本执行时间
    private $spendTime = 1700;

    // 脚本开始时间
    private $startTime = 0;

    // 失败重试错误信息
    private $retryError = [
        '请稍后重试',
        '请稍后再试',
        'REVERSING',
        '请求超时',
    ];

    public function __construct()
    {
        $this->startTime = time();
    }

    public function process($params, &$error_msg = '')
    {
        // 注册service，分析是否允许开蓝票
        foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
        {
            if(method_exists($object, 'check_blue_invoice')){
                // check
                $analysisResult = $object->check_blue_invoice($params);
                if(isset($analysisResult['rsp']) && $analysisResult['rsp'] == 'fail'){
                    $error_msg = ($analysisResult['error_msg'] ? $analysisResult['error_msg'] : '禁止自动重试开蓝票；');
                    return true;
                }
            }
        }
        
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '512M');

        // 心跳检查
        $this->heartbeat();

        // 开蓝失败重试
        $this->retryBill_B2C();

        // 冲红失败重试
        $this->retryCancel_B2C();

        // 同步开票结果
        $this->getInvoiceResult_B2C();

        // 同步红冲确认单结果
        $this->getRedApplyResult_B2C();

        // 红冲确认单失败重试
        $this->retryRedApply_B2C();
    }

    /**
     * 心跳检查
     *
     * @return void
     *
     * @author chenping <chenping@shopex.cn>
     */
    public function heartbeat() {}

    /**
     * 开票失败重试
     *
     * @return void
     *
     * @author chenping <chenping@shopex.cn>
     */
    public function retryBill_B2C()
    {
        // 只允许运行一次
        if (cachecore::fetch(__FUNCTION__)) {
            return;
        }

        $filter = [
            'sync'               => '2',
            'is_status'          => '0',
            'is_make_invoice'    => '1',
            'filter_sql'         => ' sync_msg REGEXP "' . implode('|', $this->retryError) . '"',
        ];

        $model = app::get('invoice')->model('order');

        $list = $model->getList('id, order_id', $filter, 0, 100, 'last_modify asc');

        if (empty($list)) {
            return;
        }

        cachecore::store(__FUNCTION__, 1, 500);

        foreach ($list as $value) {
            // 只运行5分钟
            if (time() - $this->startTime > $this->spendTime) {
                break;
            }

            kernel::single('invoice_process')->billing(['id' => $value['id'], 'order_id' => $value['order_id'], 'msg' => '系统重试开蓝失败']);
        }

        cachecore::delete(__FUNCTION__);
    }

    /**
     * 红冲失败重试
     *
     * @return void
     *
     * @author chenping <chenping@shopex.cn>
     */
    public function retryCancel_B2C()
    {
        // 只允许运行一次
        if (cachecore::fetch(__FUNCTION__)) {
            return;
        }

        $filter = [
            'sync'               => '5',
            'is_status'          => '1',
            'filter_sql'         => ' sync_msg REGEXP "' . implode('|', $this->retryError) . '"',
        ];

        $model = app::get('invoice')->model('order');

        $list = $model->getList('id, order_id', $filter, 0, 100, 'last_modify asc');

        if (empty($list)) {
            return;
        }

        cachecore::store(__FUNCTION__, 1, 500);

        foreach ($list as $value) {
            // 只运行5分钟
            if (time() - $this->startTime > $this->spendTime) {
                break;
            }

            kernel::single('invoice_process')->cancel(['id' => $value['id'], 'order_id' => $value['order_id'], 'msg' => '系统重试红冲失败'],
                'invoice_list');
        }

        cachecore::delete(__FUNCTION__);
    }

    /**
     * 同步开票结果
     *
     * @return void
     *
     * @author chenping <chenping@shopex.cn>
     */
    public function getInvoiceResult_B2C()
    {
        // 只运行一次
        if (cachecore::fetch(__FUNCTION__)) {
            return;
        }

        $filter = [
            'sync'               => ['1', '4', '7', '9'],
        ];

        $model = app::get('invoice')->model('order');

        $list = $model->getList('id, order_id', $filter, 0, 100, 'last_modify asc');

        if (empty($list)) {
            return;
        }

        cachecore::store(__FUNCTION__, 1, 500);

        // 循环list
        foreach ($list as $invoice) {

            // 执行5分钟后退出
            if (time() - $this->startTime > $this->spendTime) {
                break;
            }

            // 查询ITEM表
            $items = app::get('invoice')->model('order_electronic_items')->getList('item_id', [
                'id'             => $invoice['id'],
                'invoice_status' => ['10', '20'],
            ]);

            // 循环items
            foreach ($items as $item) {
                kernel::single('invoice_event_trigger_einvoice')->getEinvoiceCreateResult($item['item_id']);
            }
        }

        cachecore::delete(__FUNCTION__);
    }

    /**
     * 同步红冲确认单结果
     *
     * @return void
     *
     * @author chenping <chenping@shopex.cn>
     */
    public function getRedApplyResult_B2C()
    {
        // 只允许运行一次
        if (cachecore::fetch(__FUNCTION__)) {
            return;
        }

        $filter = [
            'sync' => '7',
        ];

        $model = app::get('invoice')->model('order');

        $list = $model->getList('id', $filter, 0, 100, 'last_modify asc');

        if (empty($list)) {
            return;
        }

        cachecore::store(__FUNCTION__, 1, 500);

        $triggerLib = kernel::single('invoice_event_trigger_redapply');
        foreach ($list as $invoice) {

            // 执行5分钟后退出
            if (time() - $this->startTime > $this->spendTime) {
                break;
            }

            $triggerLib->sync($invoice['id']);
        }

        cachecore::delete(__FUNCTION__);
    }

    /**
     * 红冲确认单失败重试
     *
     * @return void
     *
     * @author chenping <chenping@shopex.cn>
     */
    public function retryRedApply_B2C() {}
}
