<?php

/**
 * SAP→OMS 销售/退货处理结果回调
 */
class goldentra_push_callback
{
    /**
     * @param array $params OpenAPI 参数
     * @return array stats + items detail
     * @throws Exception
     */
    public function applyFromApi(array $params)
    {
        $rows = $this->parseBody($params);
        if ($rows === false) {
            throw new Exception('参数结构体错误：请提供 BODY / body JSON 数组');
        }
        if (!$rows) {
            throw new Exception('BODY 不能为空');
        }

        $mdl = app::get('goldentra')->model('sap_push_sales');
        $http = kernel::single('goldentra_push_http');
        $ok = 0;
        $fail = 0;
        $fail_msgs = array();
        $details = array();

        foreach ($rows as $idx => $raw) {
            if (!is_array($raw)) {
                $fail++;
                $fail_msgs[] = '第' . ($idx + 1) . '行非对象';
                continue;
            }
            $row = $this->normalizeRow($raw);
            $zptdhyz = isset($row['ZPTDHYZ']) ? trim((string) $row['ZPTDHYZ']) : '';
            if ($zptdhyz === '') {
                $fail++;
                $fail_msgs[] = '第' . ($idx + 1) . '行缺少 ZPTDHYZ';
                continue;
            }

            $zsalestype = isset($row['ZSALESTYPE']) ? trim((string) $row['ZSALESTYPE']) : '';
            $filter = array('zptdhyz' => $zptdhyz);
            if ($zsalestype !== '') {
                $filter['zsalestype'] = $zsalestype;
            }

            $exist = $mdl->db_dump($filter, 'push_id,zptdhyz,zsalestype,sync_status,sap_zremarks5');
            if (!$exist && $zsalestype !== '') {
                // 类型不匹配时再按单号兜底
                $exist = $mdl->db_dump(array('zptdhyz' => $zptdhyz), 'push_id,zptdhyz,zsalestype,sync_status,sap_zremarks5');
            }
            if (!$exist) {
                $fail++;
                $fail_msgs[] = $zptdhyz . '：未找到对应上传记录';
                continue;
            }

            $status = isset($row['STATUS']) ? trim((string) $row['STATUS']) : '';
            $msgtxt = isset($row['MSGTXT']) ? trim((string) $row['MSGTXT']) : '';
            $succ = $status === '' ? true : $http->isSuccStatus($status);

            $upd = array(
                'sap_status' => $status,
                'sap_msgtxt' => mb_substr($msgtxt, 0, 1024),
                'sap_callback_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'up_time' => time(),
            );
            for ($i = 1; $i <= 5; $i++) {
                $k = 'ZREMARKS' . $i;
                if (isset($row[$k]) && $row[$k] !== '') {
                    $upd['sap_zremarks' . $i] = mb_substr((string) $row[$k], 0, 50);
                }
            }

            // 异步回调：成功则纠正为 succ；失败则记 fail（不覆盖已成功除非明确失败）
            if ($succ) {
                if ($exist['sync_status'] !== 'succ') {
                    $upd['sync_status'] = 'succ';
                    $upd['sync_msg'] = $msgtxt !== '' ? $msgtxt : 'SAP回调成功';
                    $upd['sync_time'] = time();
                } elseif ($msgtxt !== '') {
                    $upd['sync_msg'] = $msgtxt;
                }
            } else {
                $upd['sync_status'] = 'fail';
                $upd['sync_msg'] = $msgtxt !== '' ? $msgtxt : ('SAP回调失败 STATUS=' . $status);
                $upd['sync_time'] = time();
            }

            // 关键凭证号摘要进备注空位（不覆盖已有）
            $docBits = array();
            foreach (array('VBELN_VA', 'VBELN_VL', 'MBLNR', 'VBELN', 'BELNR') as $dk) {
                if (!empty($row[$dk])) {
                    $docBits[] = $dk . '=' . $row[$dk];
                }
            }
            if ($docBits) {
                $summary = implode(';', $docBits);
                if (empty($upd['sap_zremarks5']) && empty($exist['sap_zremarks5'])) {
                    $upd['sap_zremarks5'] = mb_substr($summary, 0, 50);
                }
                if (empty($upd['sync_msg']) || $upd['sync_msg'] === $msgtxt) {
                    $upd['sync_msg'] = mb_substr(
                        ($msgtxt !== '' ? $msgtxt . ' | ' : '') . $summary,
                        0,
                        500
                    );
                }
            }

            if (!$mdl->update($upd, array('push_id' => intval($exist['push_id'])))) {
                $fail++;
                $fail_msgs[] = $zptdhyz . '：更新失败';
                continue;
            }

            $ok++;
            $details[] = array(
                'zptdhyz' => $zptdhyz,
                'push_id' => intval($exist['push_id']),
                'status' => $status,
                'succ' => $succ,
            );
        }

        return array(
            'ok' => $ok,
            'fail' => $fail,
            'fail_msgs' => $fail_msgs,
            'details' => $details,
        );
    }

    /**
     * @param array $params
     * @return array|false
     */
    public function parseBody(array $params)
    {
        foreach (array('BODY', 'body', 'items', 'callbacks') as $key) {
            if (!isset($params[$key]) || $params[$key] === '' || $params[$key] === null) {
                continue;
            }
            $val = $params[$key];
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    return false;
                }
                $val = $decoded;
            }
            if (!is_array($val)) {
                return false;
            }
            // 单对象
            if ($val && !isset($val[0]) && (isset($val['ZPTDHYZ']) || isset($val['zptdhyz']))) {
                return array($val);
            }
            return array_values($val);
        }

        // 扁平单条
        if (!empty($params['ZPTDHYZ']) || !empty($params['zptdhyz'])) {
            return array($params);
        }

        return false;
    }

    protected function normalizeRow(array $raw)
    {
        $out = array();
        foreach ($raw as $k => $v) {
            $out[strtoupper((string) $k)] = $v;
        }
        return $out;
    }
}
