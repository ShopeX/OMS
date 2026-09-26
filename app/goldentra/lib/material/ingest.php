<?php

/**
 * SAP 主档：拆批入队后异步落库
 */
class goldentra_material_ingest
{
    /** 每批写入队列表的主档条数 */
    const SAVE_CHUNK = 200;

    /** 落库完成后，每批投递转换任务的 unique_code 数量 */
    const CONVERT_CHUNK = 100;

    const QUEUE_MODE = 'sap_material_sync';

    /**
     * OpenAPI 接收：拆批写入 goldentra_queue 并投递落库任务
     *
     * @return array(bool, array) 成功标志、统计
     */
    public function enqueueFromApi(array $materials, $sync_batch_no)
    {
        $materials = array_values($materials);
        $total = count($materials);
        if ($total <= 0) {
            return array(false, array('message' => '主档明细为空'));
        }

        $chunks = array_chunk($materials, self::SAVE_CHUNK);
        $chunkTotal = count($chunks);
        $queued = 0;
        $pushed = 0;
        $pushFail = 0;
        $queueIds = array();

        $queueMdl = app::get('goldentra')->model('queue');
        $now = time();

        foreach ($chunks as $i => $chunk) {
            $chunkNo = $i + 1;
            $queueRow = array(
                'queue_name' => sprintf('SAP主档落库[%s][%d/%d]', $sync_batch_no, $chunkNo, $chunkTotal),
                'queue_mode' => self::QUEUE_MODE,
                'status' => 'ready',
                'create_time' => $now,
                'modify_time' => $now,
                'queue_data' => json_encode(array(
                    'sync_batch_no' => $sync_batch_no,
                    'chunk_no' => $chunkNo,
                    'chunk_total' => $chunkTotal,
                    'materials' => $chunk,
                ), JSON_UNESCAPED_UNICODE),
                'error_msg' => '',
                'disabled' => 'false',
            );
            $queueId = $queueMdl->insert($queueRow);
            if (!$queueId) {
                continue;
            }
            $queued++;
            $queueIds[] = $queueId;
            if (goldentra_autotask::push('goldentramaterialsave', array(
                'queue_id' => $queueId,
                'sync_batch_no' => $sync_batch_no,
            ))) {
                $pushed++;
            } else {
                $pushFail++;
            }
        }

        $ok = $queued > 0;
        $message = sprintf(
            '已接收 %d 条，拆成 %d 批入队异步落库（每批最多 %d 条）',
            $total,
            $queued,
            self::SAVE_CHUNK
        );
        if ($pushFail > 0) {
            $message .= sprintf('，其中 %d 批即时投递失败将由定时补投', $pushFail);
        }
        return array($ok, array(
            'ok' => $total,
            'queued' => $queued,
            'pushed' => $pushed,
            'push_fail' => $pushFail,
            'chunk_size' => self::SAVE_CHUNK,
            'queue_ids' => $queueIds,
            'message' => $message,
        ));
    }

    /**
     * 队列 worker：按 queue_id 落库，再拆批投递转基础物料
     *
     * @return array
     */
    public function processQueueId($queue_id)
    {
        $queue_id = intval($queue_id);
        if ($queue_id <= 0) {
            return array('ok' => 0, 'fail' => 0, 'fail_msgs' => array('queue_id 无效'));
        }

        $queueMdl = app::get('goldentra')->model('queue');
        $row = $queueMdl->dump($queue_id, '*');
        if (!$row || $row['queue_mode'] !== self::QUEUE_MODE) {
            return array('ok' => 0, 'fail' => 0, 'fail_msgs' => array('队列记录不存在'));
        }
        if ($row['status'] === 'succ') {
            return array('ok' => 0, 'fail' => 0, 'fail_msgs' => array());
        }

        if (!$this->claimQueue($queue_id)) {
            return array('ok' => 0, 'fail' => 0, 'fail_msgs' => array());
        }

        $payload = json_decode($row['queue_data'], true);
        $materials = isset($payload['materials']) && is_array($payload['materials'])
            ? $payload['materials']
            : array();
        $sync_batch_no = isset($payload['sync_batch_no']) ? trim($payload['sync_batch_no']) : '';
        if (!$materials) {
            $queueMdl->update(
                array('status' => 'error', 'modify_time' => time(), 'error_msg' => '队列数据为空'),
                array('queue_id' => $queue_id)
            );
            return array('ok' => 0, 'fail' => 0, 'fail_msgs' => array('队列数据为空'));
        }

        $lib = kernel::single('goldentra_material');
        $ok = 0;
        $fail = 0;
        $fail_msgs = array();
        $pending_codes = array();

        foreach ($materials as $material) {
            $rowKey = array(
                'unique_code' => goldentra_api_normalize::pickString($material, 'unique_code'),
            );
            try {
                $ret = $lib->upsertFromApi($material, 'sap', $sync_batch_no);
                $flag = isset($ret[0]) ? $ret[0] : false;
                $msg = isset($ret[1]) ? $ret[1] : '';
                $status = isset($ret[2]) ? $ret[2] : '';
            } catch (Exception $e) {
                $fail++;
                $fail_msgs[] = goldentra_api_normalize::formatRowError($e->getMessage(), $rowKey);
                continue;
            }
            if (!$flag) {
                $fail++;
                $fail_msgs[] = $msg;
                continue;
            }
            $ok++;
            if ($status === 'pending' && $msg !== '') {
                $pending_codes[] = $msg;
            }
        }

        $error_msg = '';
        if ($fail_msgs) {
            $error_msg = implode('；', array_slice($fail_msgs, 0, 10));
        }
        $queueMdl->update(
            array(
                'status' => ($ok > 0 || $fail === 0) ? 'succ' : 'error',
                'modify_time' => time(),
                'error_msg' => $error_msg,
            ),
            array('queue_id' => $queue_id)
        );

        $this->pushConvert($pending_codes, $sync_batch_no);

        return array(
            'ok' => $ok,
            'fail' => $fail,
            'fail_msgs' => $fail_msgs,
        );
    }

    /**
     * 补投未消费的落库批次：即时 push 失败、或 worker 中途挂掉
     *
     * @param int $limit
     * @return array
     */
    public function recoverStale($limit = 20)
    {
        $limit = intval($limit);
        if ($limit <= 0) {
            $limit = 20;
        }
        $now = time();
        $db = kernel::database();
        $mode = kernel::single('goldentra_db')->escape(self::QUEUE_MODE, $db);
        $stuckBefore = $now - 600;

        $db->exec(
            "UPDATE sdb_goldentra_queue
            SET status='ready', modify_time={$now}
            WHERE queue_mode='{$mode}' AND disabled='false'
            AND status='process' AND modify_time < {$stuckBefore}"
        );
        $resetStuck = intval($db->affect_row());

        $readyBefore = $now - 60;
        $rows = app::get('goldentra')->model('queue')->getList(
            'queue_id',
            array(
                'queue_mode' => self::QUEUE_MODE,
                'status' => 'ready',
                'disabled' => 'false',
                'create_time|sthan' => $readyBefore,
            ),
            0,
            $limit,
            'queue_id ASC'
        );

        $pushed = 0;
        foreach ((array) $rows as $row) {
            $qid = intval($row['queue_id']);
            if ($qid <= 0) {
                continue;
            }
            if (goldentra_autotask::push('goldentramaterialsave', array('queue_id' => $qid))) {
                $pushed++;
            }
        }

        return array(
            'reset_stuck' => $resetStuck,
            'pushed' => $pushed,
        );
    }

    /**
     * @param int $queue_id
     * @return bool
     */
    private function claimQueue($queue_id)
    {
        $queue_id = intval($queue_id);
        $now = time();
        $db = kernel::database();
        $mode = kernel::single('goldentra_db')->escape(self::QUEUE_MODE, $db);
        $db->exec(
            "UPDATE sdb_goldentra_queue
            SET status='process', modify_time={$now}
            WHERE queue_id={$queue_id} AND queue_mode='{$mode}' AND disabled='false'
            AND status IN ('ready','error')"
        );
        return intval($db->affect_row()) > 0;
    }

    /**
     * @param array $unique_codes
     * @param string $sync_batch_no
     */
    public function pushConvert(array $unique_codes, $sync_batch_no = '')
    {
        $unique_codes = array_values(array_filter(array_unique(array_map('trim', $unique_codes))));
        if (!$unique_codes) {
            return;
        }
        $chunks = array_chunk($unique_codes, self::CONVERT_CHUNK);
        foreach ($chunks as $chunk) {
            goldentra_autotask::push('goldentramaterial', array(
                'unique_codes' => $chunk,
                'sync_batch_no' => $sync_batch_no,
            ));
        }
    }
}
