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

class financebase_crontab_billapidownload extends financebase_abstract_crontab
{

    /**
     * __construct
     * @return mixed 返回值
     */
    public function __construct()
    {
        $this->_time_key = sprintf("%s_time", __CLASS__);
        parent::__construct();
    }

    /**
     * 处理
     * @return mixed 返回值
     */
    public function process()
    {

        $oFunc = kernel::single('financebase_func');
        $list  = app::get('channel')->model('channel')->getList('*', array('channel_type' => 'ipay'));

        if (!$list) {
            return;
        }
        $tmp_shop_list = financebase_func::getShopList(financebase_func::getShopType());
        if(empty($tmp_shop_list)) {
            return;
        }

        $tmp_shop_list = array_column($tmp_shop_list,null, 'shop_id');

        $class_pool    = array();
        $node_type_ref = $oFunc->getConfig('node_type');

        foreach ($list as $v) {
            if(!$tmp_shop_list[$v['channel_bn']]) {
                continue;
            }
            if (!isset($node_type_ref[$v['node_type']])) {
                $oFunc->writelog('对账单-同步任务', 'settlement', "不支持类型" . $v['node_type']);
                continue;
            }

            // 判断是否开启自动下载
            $config = $tmp_shop_list[$v['channel_bn']]['config'];
            $config = is_string($config) ? @unserialize($config) : $config;

            if (!$config || $config['download_settle_bill'] != 'yes') {
                $oFunc->writelog('对账单-同步任务', 'settlement', "未开启自动下载");
                continue;
            }

            // 商家账单明细走 billapidownloadhourly；本任务仅钱包等对账渠道（同店可有第二条 channel.biz_type=360buy_bills）
            if (!empty($v['biz_type']) && $v['biz_type'] === '360buy_bills') {
                continue;
            }

            $class_name = 'financebase_data_bill_' . $node_type_ref[$v['node_type']];
            if (!isset($class_pool[$class_name])) {
                if (ome_func::class_exists($class_name)) {
                    $class_pool[$class_name] = kernel::single($class_name);
                } else {
                    $oFunc->writelog('对账单-同步任务', 'settlement', "没有类" . $class_name);
                    continue;
                }
            }

            $v['bill_date'] = date("Y-m-d", strtotime("-1 day"));
            $v['shop_name'] = $v['channel_name'];
            $v['shop_type'] = $node_type_ref[$v['node_type']];
            $v['shop_id']   = $v['channel_bn'];

            $queueData                = array();
            $queueData['queue_mode']  = 'billApiDownload';
            $queueData['create_time'] = time();
            $queueData['queue_name']  = sprintf("%s_%s_下载任务", $v['shop_name'], $v['bill_date']);
            $queueData['queue_data']  = $v;
            $queueData['queue_no']    = $v['bill_date'];
            $queueData['shop_id']     = $v['shop_id'];

            $queue_id = $this->oQueue->insert($queueData);
            $queue_id and financebase_func::addTaskQueue(array('queue_id' => $queue_id), 'billapidownload');

        }
    }

    /**
     * 设置Time
     * @return mixed 返回操作结果
     */
    public function setTime()
    {
        $next_run_time = strtotime(date("Y-m-d", strtotime("+1 day")) . " 10:30:00");

        $this->financeObj->store($this->_time_key, $next_run_time);
        return true;
    }
}