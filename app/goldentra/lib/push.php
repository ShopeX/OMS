<?php

/**
 * OMS→SAP 上传队列：落库、状态流转、推送调度（HTTP 通道后续接入）
 */
class goldentra_push
{
    const MODEL_SALES = 'sap_push_sales';
    const MODEL_RECEIPT = 'sap_push_receipt';
    const MODEL_VOUCHER = 'sap_push_voucher';

    /**
     * 手工重试：重置为 pending
     *
     * @param string $model_name
     * @param int[] $push_ids
     * @return array
     */
    public function retryByIds($model_name, array $push_ids)
    {
        $push_ids = array_values(array_filter(array_map('intval', $push_ids)));
        $ok = 0;
        $fail = 0;
        $fail_msgs = array();
        if (!$push_ids) {
            return array('ok' => 0, 'fail' => 0, 'fail_msgs' => array('未选择记录'));
        }

        $mdl = app::get('goldentra')->model($model_name);
        // 销售/回执用 zptdhyz，财务凭证用 zyzddh（不可混查，否则 Unknown column）
        $dumpCols = 'push_id,sync_status,retry_count';
        if ($model_name === self::MODEL_VOUCHER) {
            $dumpCols .= ',zyzddh';
        } else {
            $dumpCols .= ',zptdhyz';
        }
        foreach ($push_ids as $id) {
            $row = $mdl->dump($id, $dumpCols);
            if (!$row) {
                $fail++;
                $fail_msgs[] = 'ID ' . $id . ' 不存在';
                continue;
            }
            $bizLabel = $this->bizLabel($row, $id);
            if ($row['sync_status'] === 'running') {
                $fail++;
                $fail_msgs[] = $bizLabel . '：上传中不可重试';
                continue;
            }
            if ($row['sync_status'] === 'succ') {
                $fail++;
                $fail_msgs[] = $bizLabel . '：已成功，如需重推请联系开发';
                continue;
            }
            $mdl->update(
                array(
                    'sync_status' => 'pending',
                    'sync_msg' => '手工重试待上传',
                    'retry_count' => intval($row['retry_count']) + 1,
                    'up_time' => time(),
                ),
                array('push_id' => $id)
            );
            $this->dispatch($model_name, $id);
            $ok++;
        }

        return array('ok' => $ok, 'fail' => $fail, 'fail_msgs' => $fail_msgs);
    }

    /**
     * 保存 SD003 抬头+行，返回 push_id；已成功则跳过
     *
     * @param array $header
     * @param array $items
     * @return int|false push_id
     */
    public function saveSales(array $header, array $items)
    {
        $mdl = app::get('goldentra')->model(self::MODEL_SALES);
        $itemMdl = app::get('goldentra')->model('sap_push_sales_item');
        $now = time();

        $zptdhyz = isset($header['zptdhyz']) ? trim($header['zptdhyz']) : '';
        $zsalestype = isset($header['zsalestype']) ? $header['zsalestype'] : '01';
        if ($zptdhyz === '') {
            return false;
        }

        $exist = $mdl->db_dump(
            array('zptdhyz' => $zptdhyz, 'zsalestype' => $zsalestype),
            'push_id,sync_status'
        );
        if ($exist && in_array($exist['sync_status'], array('succ', 'running'), true)) {
            return intval($exist['push_id']);
        }

        $payload_json = $this->buildSalesPayload($header, $items, 'SD003');
        $row = $this->withoutContinfo($header);
        $row['payload_json'] = $payload_json;
        $row['sync_status'] = 'pending';
        $row['sync_msg'] = '待上传';
        if (!isset($row['sync_status2']) || $row['sync_status2'] === '') {
            // 退货无定时确认收货；销售默认 wait，签收后由定时任务置 pending
            $row['sync_status2'] = 'wait';
        }
        if (!isset($row['sync_msg2'])) {
            $row['sync_msg2'] = ($zsalestype === '02')
                ? '退货无确认收货推送'
                : '等待发货成功且签收后再确认收货';
        }
        $row['up_time'] = $now;

        if ($exist) {
            $push_id = intval($exist['push_id']);
            unset($row['at_time']);
            $row['push_id'] = $push_id;
            if (!$mdl->save($row)) {
                return false;
            }
            $itemMdl->delete(array('push_id' => $push_id));
        } else {
            $row['at_time'] = $now;
            $row['retry_count'] = 0;
            $push_id = $mdl->insert($row);
            if (!$push_id) {
                return false;
            }
            $push_id = intval($push_id);
        }

        foreach ($items as $item) {
            $item['push_id'] = $push_id;
            $item['at_time'] = $now;
            $item['up_time'] = $now;
            if (!isset($item['payload_json'])) {
                $item['payload_json'] = json_encode($item, JSON_UNESCAPED_UNICODE);
            }
            $itemMdl->insert($item);
        }

        return $push_id;
    }

    /**
     * 准备确认收货(SD004)字段并调度推送（发货成功后）
     *
     * @param int $push_id
     * @param array $phase2
     * @return bool
     */
    public function preparePhase2($push_id, array $phase2)
    {
        $push_id = intval($push_id);
        if ($push_id <= 0) {
            return false;
        }
        $mdl = app::get('goldentra')->model(self::MODEL_SALES);
        $row = $mdl->dump($push_id, 'push_id,sync_status,sync_status2,zptdhyz,bonnummer');
        if (!$row || $row['sync_status'] !== 'succ') {
            return false;
        }
        if ($row['sync_status2'] === 'succ' || $row['sync_status2'] === 'running') {
            return true;
        }

        $now = time();
        $zyzstatus = isset($phase2['zyzstatus']) ? $phase2['zyzstatus'] : '1';
        $zysfs = isset($phase2['zysfs']) ? $phase2['zysfs'] : '1';
        $confirmDatum = isset($phase2['confirm_datum']) ? $phase2['confirm_datum'] : '';
        $confirmZeit = isset($phase2['confirm_zeit']) ? $phase2['confirm_zeit'] : '';
        $bodyRow = array(
            'ZPTDHYZ' => $row['zptdhyz'],
            'BONNUMMER' => !empty($phase2['bonnummer']) ? $phase2['bonnummer'] : $row['bonnummer'],
            'VORGDATUM' => $confirmDatum,
            'VORGZEIT' => $confirmZeit,
            'ZYZSTATUS' => $zyzstatus,
            'ZYSFS' => $zysfs,
            'ZREMARKS1' => isset($phase2['zremarks1']) ? $phase2['zremarks1'] : '',
            'ZREMARKS2' => isset($phase2['zremarks2']) ? $phase2['zremarks2'] : '',
            'ZREMARKS3' => isset($phase2['zremarks3']) ? $phase2['zremarks3'] : '',
            'ZREMARKS4' => isset($phase2['zremarks4']) ? $phase2['zremarks4'] : '',
            'ZREMARKS5' => isset($phase2['zremarks5']) ? $phase2['zremarks5'] : '',
        );
        $payload2 = array(
            'CONTINFO' => $this->continfo('SD004'),
            'BODY' => array($bodyRow),
        );

        $upd = array(
            'zyzstatus' => $zyzstatus,
            'zysfs' => $zysfs,
            'confirm_datum' => $confirmDatum,
            'confirm_zeit' => $confirmZeit,
            'sign_time' => isset($phase2['sign_time']) ? intval($phase2['sign_time']) : $now,
            'payload2_json' => json_encode($payload2, JSON_UNESCAPED_UNICODE),
            'sync_status2' => 'pending',
            'sync_msg2' => '待确认收货(SD004)',
            'up_time' => $now,
        );
        $mdl->update($upd, array('push_id' => $push_id));
        return $this->dispatchPhase2($push_id);
    }

    /**
     * 确认收货调度 SD004
     *
     * @param int $push_id
     * @return bool
     */
    public function dispatchPhase2($push_id)
    {
        $push_id = intval($push_id);
        if ($push_id <= 0) {
            return false;
        }
        $mdl = app::get('goldentra')->model(self::MODEL_SALES);
        $row = $mdl->dump($push_id, '*');
        if (!$row || $row['sync_status'] !== 'succ' || $row['sync_status2'] !== 'pending') {
            return false;
        }

        $mdl->update(
            array(
                'sync_status2' => 'running',
                'sync_msg2' => '确认收货上传中',
                'up_time' => time(),
            ),
            array('push_id' => $push_id)
        );

        $result = $this->doHttpPush(self::MODEL_SALES . ':phase2', $row);
        $now = time();
        if (!empty($result['succ'])) {
            $upd = array(
                'sync_status2' => 'succ',
                'sync_msg2' => isset($result['msg']) ? $result['msg'] : '确认收货成功',
                'sync_time2' => $now,
                'up_time' => $now,
                'sap_status2' => isset($result['sap_status']) ? $result['sap_status'] : '',
                'sap_msgtxt2' => isset($result['sap_msgtxt']) ? $result['sap_msgtxt'] : '',
            );
            $mdl->update($upd, array('push_id' => $push_id));
            return true;
        }

        $keepPending = !empty($result['keep_pending']);
        $upd = array(
            'sync_status2' => $keepPending ? 'pending' : 'fail',
            'sync_msg2' => isset($result['msg']) ? $result['msg'] : '确认收货失败',
            'sync_time2' => $now,
            'up_time' => $now,
        );
        if (isset($result['sap_status'])) {
            $upd['sap_status2'] = $result['sap_status'];
        }
        if (isset($result['sap_msgtxt'])) {
            $upd['sap_msgtxt2'] = $result['sap_msgtxt'];
        }
        $mdl->update($upd, array('push_id' => $push_id));
        return false;
    }

    /**
     * 保存 SD004
     *
     * @param array $header
     * @return int|false
     */
    public function saveReceipt(array $header)
    {
        $mdl = app::get('goldentra')->model(self::MODEL_RECEIPT);
        $now = time();
        $zptdhyz = isset($header['zptdhyz']) ? trim($header['zptdhyz']) : '';
        $zyzstatus = isset($header['zyzstatus']) ? $header['zyzstatus'] : '1';
        if ($zptdhyz === '') {
            return false;
        }

        $exist = $mdl->db_dump(
            array('zptdhyz' => $zptdhyz, 'zyzstatus' => $zyzstatus),
            'push_id,sync_status'
        );
        if ($exist && in_array($exist['sync_status'], array('succ', 'running'), true)) {
            return intval($exist['push_id']);
        }

        $payload_json = json_encode(
            array(
                'CONTINFO' => $this->continfo('SD004'),
                'BODY' => array($this->toUpperKeys($this->stripControl($header))),
            ),
            JSON_UNESCAPED_UNICODE
        );
        $row = $this->withoutContinfo($header);
        $row['payload_json'] = $payload_json;
        $row['sync_status'] = 'pending';
        $row['sync_msg'] = '待上传';
        $row['up_time'] = $now;

        if ($exist) {
            $push_id = intval($exist['push_id']);
            unset($row['at_time']);
            $row['push_id'] = $push_id;
            return $mdl->save($row) ? $push_id : false;
        }

        $row['at_time'] = $now;
        $row['retry_count'] = 0;
        $push_id = $mdl->insert($row);
        return $push_id ? intval($push_id) : false;
    }

    /**
     * 保存财务凭证
     *
     * @param array $header
     * @return int|false
     */
    public function saveVoucher(array $header)
    {
        $mdl = app::get('goldentra')->model(self::MODEL_VOUCHER);
        $now = time();
        $zyzddh = isset($header['zyzddh']) ? trim($header['zyzddh']) : '';
        $zlx = isset($header['zlx']) ? $header['zlx'] : '1';
        if ($zyzddh === '') {
            return false;
        }

        $exist = $mdl->db_dump(
            array('zyzddh' => $zyzddh, 'zlx' => $zlx),
            'push_id,sync_status'
        );
        if ($exist && in_array($exist['sync_status'], array('succ', 'running'), true)) {
            return intval($exist['push_id']);
        }

        $payload_json = json_encode(
            array(
                'CONTINFO' => $this->continfo('FI001'),
                'BODY' => array($this->toUpperKeys($this->stripControl($header))),
            ),
            JSON_UNESCAPED_UNICODE
        );
        $row = $this->withoutContinfo($header);
        $row['payload_json'] = $payload_json;
        $row['sync_status'] = 'pending';
        $row['sync_msg'] = '待上传';
        $row['up_time'] = $now;

        if ($exist) {
            $push_id = intval($exist['push_id']);
            unset($row['at_time']);
            $row['push_id'] = $push_id;
            return $mdl->save($row) ? $push_id : false;
        }

        $row['at_time'] = $now;
        $row['retry_count'] = 0;
        $push_id = $mdl->insert($row);
        return $push_id ? intval($push_id) : false;
    }

    /**
     * 落库后立即调度推送
     *
     * @param string $model_name
     * @param int $push_id
     * @return bool
     */
    public function dispatch($model_name, $push_id)
    {
        $push_id = intval($push_id);
        if ($push_id <= 0) {
            return false;
        }
        $mdl = app::get('goldentra')->model($model_name);
        $row = $mdl->dump($push_id, '*');
        if (!$row || $row['sync_status'] !== 'pending') {
            return false;
        }

        $mdl->update(
            array(
                'sync_status' => 'running',
                'sync_msg' => '上传中',
                'up_time' => time(),
            ),
            array('push_id' => $push_id)
        );

        $result = $this->doHttpPush($model_name, $row);
        $now = time();
        if (!empty($result['succ'])) {
            $upd = array(
                'sync_status' => 'succ',
                'sync_msg' => isset($result['msg']) ? $result['msg'] : '上传成功',
                'sync_time' => $now,
                'up_time' => $now,
                'sap_status' => isset($result['sap_status']) ? $result['sap_status'] : '',
                'sap_msgtxt' => isset($result['sap_msgtxt']) ? $result['sap_msgtxt'] : '',
            );
            $this->mergeSapRemarks($upd, $result);
            $mdl->update($upd, array('push_id' => $push_id));
            return true;
        }

        // 通道未就绪：保持待上传，便于后台手工重试；真正失败再记 fail
        $keepPending = !empty($result['keep_pending']);
        $upd = array(
            'sync_status' => $keepPending ? 'pending' : 'fail',
            'sync_msg' => isset($result['msg']) ? $result['msg'] : '上传失败',
            'sync_time' => $now,
            'up_time' => $now,
        );
        if (isset($result['sap_status'])) {
            $upd['sap_status'] = $result['sap_status'];
        }
        if (isset($result['sap_msgtxt'])) {
            $upd['sap_msgtxt'] = $result['sap_msgtxt'];
        }
        $this->mergeSapRemarks($upd, $result);
        $mdl->update($upd, array('push_id' => $push_id));
        return false;
    }

    /**
     * HTTP 推送通道（Basic Auth + CONTINFO/BODY）
     *
     * @param string $model_name
     * @param array $row
     * @return array succ, msg, keep_pending?, sap_status?, sap_msgtxt?
     */
    protected function doHttpPush($model_name, array $row)
    {
        $setting = kernel::single('goldentra_push_setting');
        $cfg = $setting->getEffective();
        if ($cfg['enabled'] !== '1') {
            return array(
                'succ' => false,
                'keep_pending' => true,
                'msg' => 'SAP推送未启用，数据已落库待上传',
            );
        }

        $intfid = 'SD003';
        $payload = null;
        if ($model_name === self::MODEL_SALES . ':phase2') {
            $intfid = 'SD004';
            $payload = $this->resolveWirePayload($row, 'SD004', 'payload2_json');
        } elseif ($model_name === self::MODEL_SALES) {
            $intfid = 'SD003';
            $payload = $this->resolveWirePayload($row, 'SD003', 'payload_json');
        } elseif ($model_name === self::MODEL_RECEIPT) {
            $intfid = 'SD004';
            $payload = $this->resolveWirePayload($row, 'SD004', 'payload_json');
        } elseif ($model_name === self::MODEL_VOUCHER) {
            $intfid = 'FI001';
            $payload = $this->resolveWirePayload($row, 'FI001', 'payload_json');
        } else {
            return array(
                'succ' => false,
                'msg' => '未知推送类型: ' . $model_name,
            );
        }

        if (!is_array($payload) || empty($payload['BODY'])) {
            return array(
                'succ' => false,
                'msg' => '推送报文无效或为空',
            );
        }

        // 每次实推刷新时间戳
        if (!isset($payload['CONTINFO']) || !is_array($payload['CONTINFO'])) {
            $payload['CONTINFO'] = $this->continfo($intfid);
        } else {
            $payload['CONTINFO']['TIMSTAMP'] = date('YmdHis');
            if (empty($payload['CONTINFO']['INTFID'])) {
                $payload['CONTINFO']['INTFID'] = $intfid;
            }
            if (empty($payload['CONTINFO']['ZLY'])) {
                $cfgZly = isset($cfg['zly']) ? (string) $cfg['zly'] : '7';
                $payload['CONTINFO']['ZLY'] = $cfgZly;
            }
            if (empty($payload['CONTINFO']['DESTSYSTEM'])) {
                $payload['CONTINFO']['DESTSYSTEM'] = isset($cfg['destsystem']) ? $cfg['destsystem'] : 'SAP';
            }
        }

        $url = $setting->resolveUrl($intfid);
        if ($url === '') {
            return array(
                'succ' => false,
                'keep_pending' => true,
                'msg' => 'SAP ' . $intfid . ' 推送地址未配置，数据已落库待上传',
            );
        }

        $http = kernel::single('goldentra_push_http');
        return $http->postJson($url, $payload, $cfg);
    }

    /**
     * 从落库 JSON 还原 CONTINFO+BODY；兼容旧扁平报文
     *
     * @param array $row
     * @param string $intfid
     * @param string $jsonField
     * @return array|null
     */
    protected function resolveWirePayload(array $row, $intfid, $jsonField)
    {
        $raw = isset($row[$jsonField]) ? $row[$jsonField] : '';
        if ($raw === '' || $raw === null) {
            return null;
        }
        $data = is_array($raw) ? $raw : json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        if (isset($data['CONTINFO']) || isset($data['BODY']) || isset($data['body'])) {
            $body = isset($data['BODY']) ? $data['BODY'] : (isset($data['body']) ? $data['body'] : array());
            if (!is_array($body)) {
                $body = array();
            }
            // 单对象 BODY 归一为数组
            if ($body && !isset($body[0]) && (isset($body['ZPTDHYZ']) || isset($body['zptdhyz']) || isset($body['ZYZDDH']) || isset($body['zyzddh']))) {
                $body = array($this->toUpperKeys($body));
            } else {
                $norm = array();
                foreach ($body as $one) {
                    if (is_array($one)) {
                        $norm[] = $this->normalizeBodyRow($one);
                    }
                }
                $body = $norm;
            }
            return array(
                'CONTINFO' => isset($data['CONTINFO']) && is_array($data['CONTINFO'])
                    ? $this->toUpperKeys($data['CONTINFO'])
                    : $this->continfo($intfid),
                'BODY' => $body,
            );
        }

        // 旧格式：扁平 + ITEMS
        $items = array();
        if (isset($data['ITEMS']) && is_array($data['ITEMS'])) {
            foreach ($data['ITEMS'] as $it) {
                if (is_array($it)) {
                    $items[] = $this->toUpperKeys($it);
                }
            }
            unset($data['ITEMS']);
        } elseif (isset($data['ITEM']) && is_array($data['ITEM'])) {
            foreach ($data['ITEM'] as $it) {
                if (is_array($it)) {
                    $items[] = $this->toUpperKeys($it);
                }
            }
            unset($data['ITEM']);
        }
        unset(
            $data['intfid'], $data['INTFID'],
            $data['zly'], $data['ZLY'],
            $data['destsystem'], $data['DESTSYSTEM'],
            $data['timstamp'], $data['TIMSTAMP'],
            $data['backup1'], $data['BACKUP1'],
            $data['backup2'], $data['BACKUP2']
        );
        $bodyRow = $this->toUpperKeys($data);
        if ($items) {
            $bodyRow['ITEM'] = $items;
        }
        return array(
            'CONTINFO' => $this->continfo($intfid),
            'BODY' => array($bodyRow),
        );
    }

    protected function normalizeBodyRow(array $row)
    {
        $row = $this->toUpperKeys($row);
        if (isset($row['ITEMS']) && is_array($row['ITEMS']) && empty($row['ITEM'])) {
            $items = array();
            foreach ($row['ITEMS'] as $it) {
                if (is_array($it)) {
                    $items[] = $this->toUpperKeys($it);
                }
            }
            $row['ITEM'] = $items;
            unset($row['ITEMS']);
        } elseif (isset($row['ITEM']) && is_array($row['ITEM'])) {
            // 单行 ITEM 对象
            if ($row['ITEM'] && !isset($row['ITEM'][0]) && (isset($row['ITEM']['ITEMID']) || isset($row['ITEM']['ARTNR']))) {
                $row['ITEM'] = array($this->toUpperKeys($row['ITEM']));
            } else {
                $norm = array();
                foreach ($row['ITEM'] as $it) {
                    if (is_array($it)) {
                        $norm[] = $this->toUpperKeys($it);
                    }
                }
                $row['ITEM'] = $norm;
            }
        }
        return $row;
    }

    protected function buildSalesPayload(array $header, array $items, $intfid = 'SD003')
    {
        $bodyRow = $this->toUpperKeys($this->stripControl($header));
        $bodyRow['ITEM'] = array();
        foreach ($items as $it) {
            $bodyRow['ITEM'][] = $this->toUpperKeys($this->stripControl($it));
        }
        unset($bodyRow['ITEMS']);
        return json_encode(
            array(
                'CONTINFO' => $this->continfo($intfid),
                'BODY' => array($bodyRow),
            ),
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * CONTINFO 固定段：仅进入推送报文，不落库
     *
     * @param string $intfid SD003/SD004/FI001
     * @return array
     */
    protected function continfo($intfid)
    {
        $cfg = kernel::single('goldentra_push_setting')->getEffective();
        return array(
            'DESTSYSTEM' => !empty($cfg['destsystem']) ? (string) $cfg['destsystem'] : 'SAP',
            'INTFID' => (string) $intfid,
            'ZLY' => isset($cfg['zly']) ? (string) $cfg['zly'] : '7',
            'TIMSTAMP' => date('YmdHis'),
            'BACKUP1' => '',
            'BACKUP2' => '',
        );
    }

    /**
     * 小写/混合键 → SAP 大写键（嵌套数组保留结构，值原样）
     *
     * @param array $row
     * @return array
     */
    protected function toUpperKeys(array $row)
    {
        $out = array();
        foreach ($row as $k => $v) {
            $uk = strtoupper((string) $k);
            if ($uk === 'ITEMS') {
                $uk = 'ITEM';
            }
            if (is_array($v)) {
                // 列表 or 关联
                if ($v && array_keys($v) === range(0, count($v) - 1)) {
                    $list = array();
                    foreach ($v as $one) {
                        $list[] = is_array($one) ? $this->toUpperKeys($one) : $one;
                    }
                    $out[$uk] = $list;
                } else {
                    $out[$uk] = $this->toUpperKeys($v);
                }
            } else {
                $out[$uk] = $v === null ? '' : $v;
            }
        }
        return $out;
    }

    /**
     * 落库前去掉 CONTINFO，避免写入已删除的表列
     *
     * @param array $row
     * @return array
     */
    protected function withoutContinfo(array $row)
    {
        unset(
            $row['intfid'],
            $row['zly'],
            $row['destsystem'],
            $row['timstamp'],
            $row['backup1'],
            $row['backup2']
        );
        return $row;
    }

    protected function stripControl(array $row)
    {
        unset(
            $row['push_id'],
            $row['item_pk'],
            $row['payload_json'],
            $row['payload2_json'],
            $row['sync_status'],
            $row['sync_msg'],
            $row['sync_time'],
            $row['retry_count'],
            $row['sync_status2'],
            $row['sync_msg2'],
            $row['sync_time2'],
            $row['retry_count2'],
            $row['sap_status'],
            $row['sap_msgtxt'],
            $row['sap_status2'],
            $row['sap_msgtxt2'],
            $row['sap_zremarks1'],
            $row['sap_zremarks2'],
            $row['sap_zremarks3'],
            $row['sap_zremarks4'],
            $row['sap_zremarks5'],
            $row['sap_callback_json'],
            $row['sale_id'],
            $row['sale_bn'],
            $row['aftersale_id'],
            $row['aftersale_bn'],
            $row['delivery_id'],
            $row['delivery_bn'],
            $row['order_id'],
            $row['order_bn'],
            $row['reship_id'],
            $row['reship_bn'],
            $row['shop_id'],
            $row['sign_time'],
            $row['confirm_datum'],
            $row['confirm_zeit'],
            $row['at_time'],
            $row['up_time'],
            // CONTINFO 由 continfo() 统一注入
            $row['intfid'],
            $row['zly'],
            $row['destsystem'],
            $row['timstamp'],
            $row['backup1'],
            $row['backup2']
        );
        return $row;
    }

    protected function bizLabel(array $row, $id)
    {
        if (!empty($row['zptdhyz'])) {
            return $row['zptdhyz'];
        }
        if (!empty($row['zyzddh'])) {
            return $row['zyzddh'];
        }
        return (string) $id;
    }

    /**
     * 同步响应中的 ZREMARKS1~5 写入表列
     *
     * @param array $upd
     * @param array $result
     */
    protected function mergeSapRemarks(array &$upd, array $result)
    {
        $src = array();
        if (!empty($result['sap_row']) && is_array($result['sap_row'])) {
            $src = $result['sap_row'];
        }
        for ($i = 1; $i <= 5; $i++) {
            $k = 'ZREMARKS' . $i;
            $lk = 'zremarks' . $i;
            if (isset($src[$k]) && $src[$k] !== '') {
                $upd['sap_zremarks' . $i] = mb_substr((string) $src[$k], 0, 50);
            } elseif (isset($src[$lk]) && $src[$lk] !== '') {
                $upd['sap_zremarks' . $i] = mb_substr((string) $src[$lk], 0, 50);
            }
        }
    }
}
