<?php

/**
 * 投资金价业务：SAP → OMS 下发落库
 */
class goldentra_invest_goldprice
{
    /**
     * @param array $params
     * @return array|false
     */
    public function parsePayload(array $params)
    {
        foreach (array('items', 'BODY', 'body', 'invest_goldprices', 'prices') as $key) {
            if (!isset($params[$key]) || $params[$key] === '' || $params[$key] === null) {
                continue;
            }
            $batch = goldentra_api_normalize::decodeJsonBatch($params[$key], 'request_id');
            if ($batch === false) {
                $batch = goldentra_api_normalize::decodeJsonBatch($params[$key], 'requestId');
            }
            if ($batch === false) {
                $batch = goldentra_api_normalize::decodeJsonBatch($params[$key], 'ZIDNUM');
            }
            return $batch;
        }

        // 扁平单条
        $probe = goldentra_invest_goldprice_fields::pickField($params, 'request_id');
        if ($probe !== '') {
            return array($params);
        }
        return false;
    }

    /**
     * @param array $rows
     * @param string $dataSource
     * @param string $syncBatchNo
     * @return array
     */
    public function upsertBatchFromApi(array $rows, $dataSource = 'sap', $syncBatchNo = '')
    {
        $ok = 0;
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $fail = 0;
        $fail_msgs = array();
        $details = array();

        foreach ($rows as $idx => $raw) {
            if (!is_array($raw)) {
                $fail++;
                $fail_msgs[] = '第' . ($idx + 1) . '行非对象';
                continue;
            }
            try {
                $ret = $this->upsertOne($raw, $dataSource, $syncBatchNo);
                $ok++;
                if ($ret['action'] === 'insert') {
                    $inserted++;
                } elseif ($ret['action'] === 'update') {
                    $updated++;
                } else {
                    $skipped++;
                }
                $details[] = $ret;
            } catch (Exception $e) {
                $fail++;
                $norm = goldentra_invest_goldprice_fields::normalizeRow($raw);
                $fail_msgs[] = goldentra_api_normalize::formatRowError(
                    $e->getMessage(),
                    array(
                        'request_id' => isset($norm['request_id']) ? $norm['request_id'] : '',
                        'category_code' => isset($norm['category_code']) ? $norm['category_code'] : '',
                        'item_code' => isset($norm['item_code']) ? $norm['item_code'] : '',
                    )
                );
            }
        }

        return array(
            'ok' => $ok,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'fail' => $fail,
            'fail_msgs' => $fail_msgs,
            'details' => $details,
        );
    }

    /**
     * @param array $raw
     * @param string $dataSource
     * @param string $syncBatchNo
     * @return array
     * @throws Exception
     */
    public function upsertOne(array $raw, $dataSource = 'sap', $syncBatchNo = '')
    {
        $row = goldentra_invest_goldprice_fields::normalizeRow($raw);
        goldentra_invest_goldprice_fields::validate($row);

        $mdl = app::get('goldentra')->model('invest_goldprice');
        $filter = array(
            'sales_organize' => $row['sales_organize'],
            'brand_id' => $row['brand_id'],
            'category_code' => $row['category_code'],
            'item_code' => $row['item_code'],
        );
        $exist = $mdl->db_dump($filter, 'id,request_id,sync_type,price,add_price');

        $now = time();
        $save = array(
            'request_id' => mb_substr($row['request_id'], 0, 20),
            'sync_type' => $row['sync_type'],
            'sales_organize' => mb_substr($row['sales_organize'], 0, 4),
            'brand_id' => $row['brand_id'],
            'category_code' => mb_substr($row['category_code'], 0, 9),
            'price' => round(floatval($row['price']), 2),
            'item_code' => mb_substr($row['item_code'], 0, 18),
            'add_price' => round(floatval($row['add_price']), 2),
            'ext_field' => goldentra_invest_goldprice_fields::buildExtField($raw),
            'data_source' => $dataSource,
            'sync_batch_no' => mb_substr((string) $syncBatchNo, 0, 64),
            'payload_json' => json_encode($raw, JSON_UNESCAPED_UNICODE),
            'up_time' => $now,
        );

        if ($exist) {
            $samePrice = (abs(floatval($exist['price']) - floatval($save['price'])) < 0.00001)
                && (abs(floatval($exist['add_price']) - floatval($save['add_price'])) < 0.00001)
                && ((string) $exist['sync_type'] === (string) $save['sync_type']);
            if ($samePrice && (string) $exist['request_id'] === (string) $save['request_id']) {
                return array(
                    'action' => 'skip',
                    'id' => intval($exist['id']),
                    'request_id' => $save['request_id'],
                );
            }
            $save['id'] = intval($exist['id']);
            if (!$mdl->save($save)) {
                throw new Exception('更新失败');
            }
            $this->writeLog($save['id'], '更新投资金价 request_id=' . $save['request_id']
                . ' price=' . $save['price'] . ' add_price=' . $save['add_price']);
            return array(
                'action' => 'update',
                'id' => $save['id'],
                'request_id' => $save['request_id'],
            );
        }

        $save['at_time'] = $now;
        $id = $mdl->insert($save);
        if (!$id) {
            throw new Exception('新增失败');
        }
        $id = intval($id);
        $this->writeLog($id, '新增投资金价 request_id=' . $save['request_id']
            . ' price=' . $save['price'] . ' add_price=' . $save['add_price']);
        return array(
            'action' => 'insert',
            'id' => $id,
            'request_id' => $save['request_id'],
        );
    }

    protected function writeLog($id, $msg)
    {
        try {
            $opObj = app::get('ome')->model('operation_log');
            $opObj->write_log('invest_goldprice_sync@goldentra', $id, $msg);
        } catch (Exception $e) {
            // 日志失败不影响主流程
        }
    }
}
