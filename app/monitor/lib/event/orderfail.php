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
 * 失败订单批量通知：进入快照写入（SCRUM-7468）
 */
class monitor_event_orderfail
{
    const EVENT_TYPE = 'order_fail_notify';
    /** CSV 附件最多写入条数（按 entered_at 降序取最新） */
    const ORDER_CSV_LIMIT = 5000;

    /**
     * 订单进入失败时写入不可变快照（按 order_id 幂等）
     *
     * @param array $order 至少含 order_id / order_bn
     * @return bool
     */
    public function recordEnter(array $order)
    {
        $orderId = isset($order['order_id']) ? intval($order['order_id']) : 0;
        $orderBn = isset($order['order_bn']) ? trim((string) $order['order_bn']) : '';
        if ($orderId <= 0 || $orderBn === '') {
            return false;
        }

        $snapshotMdl = app::get('monitor')->model('order_fail_snapshot');
        if ($snapshotMdl->db_dump(array('order_id' => $orderId), 'id')) {
            return true;
        }

        // 缺字段时补读订单
        if (!isset($order['shop_id']) || !isset($order['org_id']) || !isset($order['total_amount']) || !isset($order['paytime'])) {
            $full = app::get('ome')->model('orders')->db_dump(
                array('order_id' => $orderId),
                'order_id,order_bn,shop_id,shop_name,org_id,total_amount,pay_status,createtime,paytime'
            );
            if ($full) {
                $order = array_merge($full, $order);
            }
        }

        $failReason = '';
        $abnormal = app::get('ome')->model('order_abnormal')->db_dump(
            array('order_id' => $orderId, 'abnormal_type' => 'object_fail'),
            'abnormal_msg'
        );
        if (!empty($abnormal['abnormal_msg'])) {
            $failReason = $abnormal['abnormal_msg'];
        }

        $row = array(
            'order_id'         => $orderId,
            'order_bn'         => $orderBn,
            'shop_id'          => isset($order['shop_id']) ? (string) $order['shop_id'] : '',
            'shop_name'        => isset($order['shop_name']) ? (string) $order['shop_name'] : '',
            'org_id'           => isset($order['org_id']) && $order['org_id'] !== '' ? (string) $order['org_id'] : '0',
            'total_amount'     => isset($order['total_amount']) ? $order['total_amount'] : 0,
            'pay_status'       => isset($order['pay_status']) ? (string) $order['pay_status'] : '0',
            'order_createtime' => isset($order['createtime']) ? intval($order['createtime']) : 0,
            'paytime'          => isset($order['paytime']) ? intval($order['paytime']) : 0,
            'fail_reason'      => $failReason,
            'entered_at'       => time(),
        );

        try {
            return (bool) $snapshotMdl->insert($row);
        } catch (Throwable $e) {
            // 并发下唯一键冲突视为已写入
            if ($snapshotMdl->db_dump(array('order_id' => $orderId), 'id')) {
                return true;
            }
            
            return false;
        }
    }

    /**
     * 按 order_id 补记（update is_fail 场景）
     *
     * @param int $orderId
     * @return bool
     */
    public function recordEnterByOrderId($orderId)
    {
        $orderId = intval($orderId);
        if ($orderId <= 0) {
            return false;
        }
        
        $order = app::get('ome')->model('orders')->db_dump(
            array('order_id' => $orderId),
            'order_id,order_bn,shop_id,shop_name,org_id,total_amount,pay_status,createtime,paytime,is_fail'
        );
        
        if (!$order || $order['is_fail'] !== 'true') {
            return false;
        }
        
        return $this->recordEnter($order);
    }

    /**
     * 按组织生成失败订单 CSV（参考 undelivery），返回 [绝对路径, 文件名]；失败返回 false
     *
     * @param string $orgId
     * @param array $rows 快照行，含 order_bn/order_createtime/paytime
     * @return array|false
     */
    public function buildDigestCsv($orgId, array $rows)
    {
        if (!$rows) {
            return false;
        }

        // 调用方宜按 entered_at 降序传入；此处再截断，保证附件最多 5000 条
        $rows = array_slice(array_values($rows), 0, self::ORDER_CSV_LIMIT);

        $dir = DATA_DIR . '/export/tmp_local';
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }

        $title = 'order_fail_notify_' . $orgId . '_' . date('YmdHis');
        $fileName = $title . '.csv';
        $filePath = $dir . '/' . $fileName;

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $header = implode(',', array('订单号', '下单时间', '付款时间')) . "\n";
        if (file_put_contents($filePath, $bom . $header, LOCK_EX) === false) {
            return false;
        }

        foreach ($rows as $row) {
            $orderBn = isset($row['order_bn']) ? $row['order_bn'] . "\t" : "\t";
            $createTime = !empty($row['order_createtime'])
                ? date('Y-m-d H:i:s', intval($row['order_createtime']))
                : '';
            $payTime = !empty($row['paytime'])
                ? date('Y-m-d H:i:s', intval($row['paytime']))
                : '';
            $line = implode(',', array($orderBn, $createTime, $payTime)) . "\n";
            if (file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX) === false) {
                return false;
            }
        }

        return array($filePath, $fileName);
    }

    /**
     * 按组织发送一封失败订单汇总邮件（同步，可带 CSV 附件），返回是否发送成功
     *
     * @param array $templateInfo
     * @param array $params org_id/start_time/end_time/order_count/csv_file/file_path
     * @return bool
     */
    public function dispatchDigestMail(array $templateInfo, array $params)
    {
        $notifyLib = kernel::single('monitor_event_notify');
        $eventNotifyMdl = app::get('monitor')->model('event_notify');

        $filePaths = array();
        if (!empty($params['file_path'])) {
            $filePaths = is_array($params['file_path']) ? $params['file_path'] : array($params['file_path']);
        }

        $notifyData = array(
            'template_id'      => $templateInfo['template_id'],
            'event_type'       => self::EVENT_TYPE,
            'original_content' => $templateInfo['content'],
            'send_content'     => $notifyLib->getNotifyParams($templateInfo, $params),
            'send_type'        => 'email',
            'params'           => json_encode($params),
            'file_path'        => json_encode(array_values($filePaths), JSON_UNESCAPED_SLASHES),
            'org_id'           => isset($params['org_id']) ? $params['org_id'] : '',
        );

        $notifyId = $eventNotifyMdl->insert($notifyData);
        if (!$notifyId) {
            return false;
        }

        $notifyData['notify_id'] = $notifyId;
        $res = $notifyLib->sendNotify($notifyId, $notifyData);

        return is_array($res) && isset($res['rsp']) && $res['rsp'] === 'succ';
    }
}
