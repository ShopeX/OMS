<?php

/**
 * 定价(D)↔计价(J) 价格类型变更：按唯一码调整单出库清空 → 重匹配 → 调整单入库恢复
 * 不把老基础物料整料停售（共用料号时仍可能有未迁唯一码在库）
 * Mapping 手工禁用：按基础物料查在库唯一码 → 调整单出库清空
 */
class goldentra_material_pricetype
{
    const SOURCE = '价格类型变更';
    const SOURCE_MAPPING = 'MAPPING禁用清库存';

    /**
     * SAP 主档 upsert：计价方式变更时登记事件（幂等）
     *
     * @param array $oldRow
     * @param array $newRow
     * @return array|null event row
     */
    public function enqueueFromUpsert(array $oldRow, array $newRow)
    {
        $oldPm = isset($oldRow['price_method']) ? (string) $oldRow['price_method'] : '';
        $newPm = isset($newRow['price_method']) ? (string) $newRow['price_method'] : '';
        if ($oldPm === '' || $newPm === '' || $oldPm === $newPm) {
            return null;
        }
        if (!in_array($oldPm, array('1', '2'), true) || !in_array($newPm, array('1', '2'), true)) {
            return null;
        }

        $unique_code = isset($newRow['unique_code']) ? $newRow['unique_code'] : $oldRow['unique_code'];
        $old_bn = !empty($oldRow['bn']) ? $oldRow['bn'] : (!empty($newRow['bn']) ? $newRow['bn'] : '');
        $material_id = intval(isset($oldRow['material_id']) ? $oldRow['material_id'] : (isset($newRow['material_id']) ? $newRow['material_id'] : 0));
        $sync_batch_no = isset($newRow['sync_batch_no']) ? $newRow['sync_batch_no'] : '';
        $labelLib = kernel::single('goldentra_material_pricetype_label');

        // 无旧料号：仍登记 pending 事件，避免「只打标、不入队」；处理时再尝试从在库 SN 反查
        if ($old_bn === '') {
            $labelLib->recordPriceMethodChange($material_id, '', $unique_code);
            $scene = ($oldPm === '1' && $newPm === '2') ? 'jj_to_dj' : 'dj_to_jj';
            $event_key = $this->buildEventKey($unique_code, $oldPm, $newPm, '_nobn_');
            $mdl = app::get('goldentra')->model('pricetype_event');
            $exist = $mdl->dump(array('event_key' => $event_key), '*');
            if ($exist) {
                if (in_array($exist['status'], array('succ', 'waiting_map', 'cleared', 'frozen', 'restocked', 'pending'), true)) {
                    return $exist;
                }
                $mdl->update(
                    array(
                        'status' => 'pending',
                        'process_msg' => '缺少变更前料号，待处理时从在库反查',
                        'sync_batch_no' => $sync_batch_no,
                        'up_time' => time(),
                    ),
                    array('event_id' => $exist['event_id'])
                );
                return $mdl->dump($exist['event_id'], '*');
            }
            $now = time();
            $eventRow = array(
                'event_key' => $event_key,
                'scene' => $scene,
                'unique_code' => $unique_code,
                'material_id' => $material_id,
                'mapping_id' => 0,
                'old_price_method' => $oldPm,
                'new_price_method' => $newPm,
                'old_bn' => '',
                'new_bn' => '',
                'status' => 'pending',
                'snapshot_json' => '',
                'clear_bill_bns' => '',
                'restock_bill_bns' => '',
                'sync_batch_no' => $sync_batch_no,
                'process_msg' => '缺少变更前料号，待处理时从在库反查',
                'at_time' => $now,
                'up_time' => $now,
            );
            $id = $mdl->insert($eventRow);
            return $id ? $mdl->dump($id, '*') : null;
        }

        $scene = ($oldPm === '1' && $newPm === '2') ? 'jj_to_dj' : 'dj_to_jj';
        $event_key = $this->buildEventKey($unique_code, $oldPm, $newPm, $old_bn);

        $mdl = app::get('goldentra')->model('pricetype_event');
        $exist = $mdl->dump(array('event_key' => $event_key), '*');
        if ($exist) {
            $labelLib->recordPriceMethodChange($material_id, $old_bn, $unique_code);
            // 已成功但库存仍挂在旧料号 / 主档仍挂旧料号 → 重开迁移
            if ($exist['status'] === 'succ' && $this->shouldReopenSuccEvent($exist, $unique_code)) {
                $mdl->update(
                    array(
                        'status' => 'pending',
                        'process_msg' => '库存仍在旧料号或主档未切完，重跑迁移',
                        'sync_batch_no' => $sync_batch_no,
                        'up_time' => time(),
                    ),
                    array('event_id' => $exist['event_id'])
                );
                return $mdl->dump($exist['event_id'], '*');
            }
            if (in_array($exist['status'], array('succ', 'waiting_map', 'cleared', 'frozen', 'restocked'), true)) {
                return $exist;
            }
            if ($exist['status'] === 'pending') {
                return $exist;
            }
            // fail → 允许重试，更新为 pending
            $mdl->update(
                array(
                    'status' => 'pending',
                    'process_msg' => '',
                    'sync_batch_no' => $sync_batch_no,
                    'up_time' => time(),
                ),
                array('event_id' => $exist['event_id'])
            );
            return $mdl->dump($exist['event_id'], '*');
        }

        $now = time();
        $eventRow = array(
            'event_key' => $event_key,
            'scene' => $scene,
            'unique_code' => $unique_code,
            'material_id' => $material_id,
            'mapping_id' => 0,
            'old_price_method' => $oldPm,
            'new_price_method' => $newPm,
            'old_bn' => $old_bn,
            'new_bn' => '',
            'status' => 'pending',
            'snapshot_json' => '',
            'clear_bill_bns' => '',
            'restock_bill_bns' => '',
            'sync_batch_no' => $sync_batch_no,
            'process_msg' => '',
            'at_time' => $now,
            'up_time' => $now,
        );
        $id = $mdl->insert($eventRow);

        if ($id) {
            $labelLib->recordPriceMethodChange($material_id, $old_bn, $unique_code);
            return $mdl->dump($id, '*');
        }
        // 入队失败仍打标，避免业务看不见
        $labelLib->recordPriceMethodChange($material_id, $old_bn, $unique_code);
        return null;
    }

    /**
     * 当前 bn 前缀与计价方式不一致时补登记迁移（定价 D / 计价 J）
     * 修复：price_method 已变但未入队，或静默 remap 导致未打标/未迁库存
     *
     * @param array $row material 当前行（含 bn、price_method）
     * @return array|null
     */
    public function enqueueIfBnPrefixMismatch(array $row)
    {
        $bn = isset($row['bn']) ? trim((string) $row['bn']) : '';
        $pm = isset($row['price_method']) ? (string) $row['price_method'] : '';
        if ($bn === '' || !in_array($pm, array('1', '2'), true)) {
            return null;
        }
        $implied = $this->priceMethodFromBn($bn);
        if ($implied === '' || $implied === $pm) {
            return null;
        }
        $material_id = intval(isset($row['material_id']) ? $row['material_id'] : 0);
        $oldRow = $row;
        $oldRow['price_method'] = $implied;
        $oldRow['bn'] = $bn;
        $oldRow['material_id'] = $material_id;
        $newRow = $row;
        $newRow['price_method'] = $pm;
        $newRow['bn'] = $bn;
        $newRow['material_id'] = $material_id;
        return $this->enqueueFromUpsert($oldRow, $newRow);
    }

    /**
     * 两料号是否跨定价(D)/计价(J)
     */
    public function isCrossPriceTypeBn($old_bn, $new_bn)
    {
        $a = $this->priceMethodFromBn($old_bn);
        $b = $this->priceMethodFromBn($new_bn);
        return $a !== '' && $b !== '' && $a !== $b;
    }

    /**
     * 料号首字母：J=计价(1)，D=定价(2)
     */
    public function priceMethodFromBn($bn)
    {
        $bn = strtoupper(trim((string) $bn));
        if ($bn === '') {
            return '';
        }
        $prefix = substr($bn, 0, 1);
        if ($prefix === 'J') {
            return '1';
        }
        if ($prefix === 'D') {
            return '2';
        }
        return '';
    }

    /**
     * 成功事件是否需重开：主档仍挂旧料号，或在库 SN 仍在旧料号上
     */
    protected function shouldReopenSuccEvent(array $event, $unique_code)
    {
        $old_bn = isset($event['old_bn']) ? trim((string) $event['old_bn']) : '';
        $new_bn = isset($event['new_bn']) ? trim((string) $event['new_bn']) : '';
        $material_id = intval(isset($event['material_id']) ? $event['material_id'] : 0);
        if ($material_id > 0 && $old_bn !== '') {
            $mat = app::get('goldentra')->model('material')->dump($material_id, 'bn');
            if (!empty($mat['bn']) && (string) $mat['bn'] === $old_bn) {
                return true;
            }
        }
        if ($old_bn === '' || $unique_code === '') {
            return false;
        }
        $stranded = $this->findInStockOnBn($unique_code, $old_bn);
        if ($stranded) {
            return true;
        }
        // 主档已切到新料号，但旧料号仍有该唯一码在库
        if ($new_bn !== '' && $new_bn !== $old_bn) {
            return (bool) $this->findInStockOnBn($unique_code, $old_bn);
        }
        return false;
    }

    /**
     * 主档转换时：若存在待处理/待匹配的价格类型事件则走迁移流程
     *
     * @return bool|null true/false 已处理；null 无事件走普通转换
     */
    public function processIfNeeded(array $row)
    {
        $this->enqueueIfBnPrefixMismatch($row);
        $this->enqueueIfStockStranded($row);
        $event = $this->findOpenEventForMaterial($row);
        if (!$event) {
            return null;
        }
        return $this->processMaterialEvent($event, $row);
    }

    /**
     * 主档已切到新料号，但在库 SN 仍挂在跨类型旧料号上 → 补迁移
     */
    public function enqueueIfStockStranded(array $row)
    {
        $bn = isset($row['bn']) ? trim((string) $row['bn']) : '';
        $code = isset($row['unique_code']) ? trim((string) $row['unique_code']) : '';
        $pm = isset($row['price_method']) ? (string) $row['price_method'] : '';
        if ($code === '' || $bn === '' || !in_array($pm, array('1', '2'), true)) {
            return null;
        }
        $sns = $this->findInStockByUniqueCode($code);
        if (!$sns) {
            return null;
        }
        foreach ($sns as $sn) {
            $snBn = isset($sn['bn']) ? trim((string) $sn['bn']) : '';
            if ($snBn === '' || $snBn === $bn) {
                continue;
            }
            if (!$this->isCrossPriceTypeBn($snBn, $bn)) {
                continue;
            }
            $impliedOld = $this->priceMethodFromBn($snBn);
            if ($impliedOld === '') {
                continue;
            }
            return $this->enqueueFromUpsert(
                array(
                    'material_id' => intval(isset($row['material_id']) ? $row['material_id'] : 0),
                    'unique_code' => $code,
                    'bn' => $snBn,
                    'price_method' => $impliedOld,
                ),
                array(
                    'material_id' => intval(isset($row['material_id']) ? $row['material_id'] : 0),
                    'unique_code' => $code,
                    'bn' => $snBn,
                    'price_method' => $pm,
                    'sync_batch_no' => isset($row['sync_batch_no']) ? $row['sync_batch_no'] : '',
                )
            );
        }
        return null;
    }

    /**
     * Mapping 手工禁用：按关联基础物料找在库唯一码，走调整单出库
     *
     * @param int $mapping_id
     * @param string $error_msg
     * @return bool
     */
    public function clearByMappingDisabled($mapping_id, &$error_msg = '')
    {
        $mapping_id = intval($mapping_id);
        if ($mapping_id <= 0) {
            $error_msg = '无效 Mapping ID';
            return false;
        }

        $event_key = 'mapping_disabled|' . $mapping_id . '|' . date('Ymd');
        $mdl = app::get('goldentra')->model('pricetype_event');
        $exist = $mdl->dump(array('event_key' => $event_key), '*');
        if ($exist && $exist['status'] === 'succ') {
            return true;
        }

        $items = app::get('goldentra')->model('mapping_item')->getList(
            'item_id,bn,bm_id',
            array('mapping_id' => $mapping_id)
        );
        if (empty($items)) {
            $error_msg = '无 Mapping 明细';
            return false;
        }

        list($bmIds, $materialBns) = $this->collectMaterialTargets($items);
        if (empty($materialBns) && empty($bmIds)) {
            $error_msg = '未找到关联基础物料';
            return false;
        }

        $snapshot = $this->findInStockByMaterials($bmIds, $materialBns);
        $now = time();
        if ($exist) {
            $event_id = intval($exist['event_id']);
            $mdl->update(
                array(
                    'status' => 'pending',
                    'snapshot_json' => json_encode($snapshot),
                    'process_msg' => '',
                    'up_time' => $now,
                ),
                array('event_id' => $event_id)
            );
        } else {
            $eventRow = array(
                'event_key' => $event_key,
                'scene' => 'mapping_disabled',
                'unique_code' => '',
                'material_id' => 0,
                'mapping_id' => $mapping_id,
                'old_price_method' => '',
                'new_price_method' => '',
                'old_bn' => implode(',', array_slice($materialBns, 0, 5)),
                'new_bn' => '',
                'status' => 'pending',
                'snapshot_json' => json_encode($snapshot),
                'clear_bill_bns' => '',
                'restock_bill_bns' => '',
                'sync_batch_no' => 'MAPDIS_' . $mapping_id . '_' . date('YmdHis'),
                'process_msg' => '',
                'at_time' => $now,
                'up_time' => $now,
            );
            $event_id = $mdl->insert($eventRow);
        }

        if (!$event_id) {
            $error_msg = '创建迁移事件失败';
            return false;
        }

        try {
            $this->freezeByBns($materialBns);
            $this->updateEvent($event_id, array('status' => 'frozen'));

            if (empty($snapshot)) {
                $this->updateEvent($event_id, array(
                    'status' => 'succ',
                    'process_msg' => '无在库唯一码，仅停售/打标',
                ));
                return true;
            }

            $batch = 'MAPDIS_' . $mapping_id . '_' . $event_id;
            $bills = $this->adjustOutFromSnapshot($snapshot, $batch, self::SOURCE_MAPPING);
            $this->updateEvent($event_id, array(
                'status' => 'succ',
                'clear_bill_bns' => implode(',', $bills),
                'process_msg' => sprintf('已调整出库 %d 条唯一码', count($snapshot)),
            ));
            return true;
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            $this->updateEvent($event_id, array(
                'status' => 'fail',
                'process_msg' => $error_msg,
            ));
            return false;
        }
    }

    /**
     * @param array $event
     * @param array $row material row (new price_method already applied)
     * @return bool
     */
    public function processMaterialEvent(array $event, array $row)
    {
        $event_id = intval($event['event_id']);
        $materialLib = kernel::single('goldentra_material');
        $processor = kernel::single('goldentra_material_processor');

        try {
            $old_bn = !empty($event['old_bn']) ? $event['old_bn'] : (!empty($row['bn']) ? $row['bn'] : '');
            if ($old_bn === '') {
                // 事件入队时无旧料号：从在库 SN 反查
                $live = $this->findInStockByUniqueCode($row['unique_code']);
                if (!empty($live[0]['bn'])) {
                    $old_bn = trim((string) $live[0]['bn']);
                }
            }
            if ($old_bn === '') {
                throw new Exception('价格类型变更缺少原物料编码');
            }

            // 已成功：幂等跳过迁移，仅保证主档成功态
            if ($event['status'] === 'succ') {
                if (!empty($event['new_bn'])) {
                    $materialLib->markSuccess($row['material_id'], $event['new_bn']);
                }
                return true;
            }

            // 待匹配续跑：快照可能滞后；若旧料号上仍有在库 SN 则必须迁走，不能跳过回库
            $fromWaitingMap = ($event['status'] === 'waiting_map')
                || ($event['scene'] === 'jj_to_dj_nomap');
            $needClear = !in_array($event['status'], array('cleared', 'waiting_map', 'restocked'), true);

            $labelLib = kernel::single('goldentra_material_pricetype_label');
            $labelLib->recordPriceMethodChange(
                intval($row['material_id']),
                $old_bn,
                isset($row['unique_code']) ? $row['unique_code'] : ''
            );

            if ($needClear) {
                $snapshot = $this->findInStockByUniqueCode($row['unique_code']);
                // 兜底：唯一码在库查询为空时，按旧料号再捞一次（静默 remap 后 SN 仍挂旧 bn）
                if (empty($snapshot) && $old_bn !== '') {
                    $snapshot = $this->findInStockOnBn($row['unique_code'], $old_bn);
                }
                // 不整料停售老物料：同一 J/D 料号下可能还有未迁唯一码，停售会导致总库存列表「消失」
                $labelLib->markOldByBn($old_bn);
                $this->updateEvent($event_id, array(
                    'status' => 'frozen',
                    'old_bn' => $old_bn,
                    'snapshot_json' => json_encode($snapshot),
                ));

                $batch = $this->migrateBatchNo($event);
                $bills = array();
                if (!empty($snapshot)) {
                    $bills = $this->adjustOutFromSnapshot($snapshot, $batch . '_OUT', self::SOURCE);
                }
                $this->updateEvent($event_id, array(
                    'status' => 'cleared',
                    'clear_bill_bns' => implode(',', $bills),
                    'process_msg' => sprintf('已调整出库清空 %d 条', count($snapshot)),
                ));
                $event['snapshot_json'] = json_encode($snapshot);
                $event['status'] = 'cleared';
            }

            // 解析新物料编码
            $new_bn = '';
            try {
                $new_bn = $processor->processOne($row);
            } catch (Exception $e) {
                // 场景二：计价→定价无 Mapping
                if ($event['old_price_method'] === '1' && $event['new_price_method'] === '2') {
                    $materialLib->markFail(
                        $row['material_id'],
                        '价格类型变更待匹配：' . $e->getMessage()
                    );
                    app::get('goldentra')->model('material')->update(
                        array('match_status' => '0'),
                        array('material_id' => $row['material_id'])
                    );
                    $this->updateEvent($event_id, array(
                        'scene' => 'jj_to_dj_nomap',
                        'status' => 'waiting_map',
                        'process_msg' => $e->getMessage()
                            . '；后续匹配成功后将检查旧料号在库并迁移，否则等待SAP全量库存同步',
                    ));
                    return false;
                }
                throw $e;
            }

            if ($new_bn === '') {
                throw new Exception('转基础物料失败');
            }

            $materialLib->markSuccess($row['material_id'], $new_bn);
            if ($row['price_method'] == '2') {
                $materialLib->updatePriceByBn($new_bn);
            }
            $bm = app::get('material')->model('basic_material')->dump(array('material_bn' => $new_bn), 'bm_id');
            if (!empty($row['sales_mode']) && $bm) {
                goldentra_material_jewelry_props::saveProps($bm['bm_id'], array('sales_mode' => $row['sales_mode']));
            }
            if ($bm && !empty($row['pic_url'])) {
                goldentra_material_image::attachRemoteUrl($bm['bm_id'], $row['pic_url']);
            }
            if ($old_bn !== $new_bn) {
                goldentra_material_changelog::logRemap($row['material_id'], $old_bn, $new_bn);
            }

            $restockBills = array();
            $process_msg = '';
            // 续跑时优先看旧料号是否仍有在库；有则必须迁，不能跳过
            $liveOnOld = ($old_bn !== '')
                ? $this->findInStockOnBn($row['unique_code'], $old_bn)
                : array();
            if (empty($liveOnOld)) {
                $allLive = $this->findInStockByUniqueCode($row['unique_code']);
                foreach ((array) $allLive as $sn) {
                    if (isset($sn['bn']) && trim((string) $sn['bn']) === $old_bn) {
                        $liveOnOld[] = $sn;
                    }
                }
            }

            $skipSnapshotRestock = $fromWaitingMap && empty($liveOnOld);
            if ($skipSnapshotRestock) {
                $process_msg = sprintf(
                    '匹配完成 %s→%s，旧料号无在库，不按快照回库，等待SAP全量库存同步',
                    $old_bn,
                    $new_bn
                );
            } else {
                $snapshot = $liveOnOld;
                if (empty($snapshot)) {
                    $snapshot = $this->decodeSnapshot($event['snapshot_json']);
                }
                // 若快照为空但续跑前刚查出在库，用 live
                if (empty($snapshot) && !empty($liveOnOld)) {
                    $snapshot = $liveOnOld;
                }
                if (!empty($snapshot)) {
                    // 仍在旧料号上：先出再入；若已在清空阶段出过库，adjustOut 对已出库应安全失败或跳过
                    $stillOnOld = $this->findInStockOnBn($row['unique_code'], $old_bn);
                    $batch = $this->migrateBatchNo($event);
                    if (!empty($stillOnOld)) {
                        $outBills = $this->adjustOutFromSnapshot($stillOnOld, $batch . '_OUT2', self::SOURCE);
                        $restockBills = array_merge($restockBills, $outBills);
                        $snapshot = $stillOnOld;
                    }
                    $inBills = $this->adjustInFromSnapshot($snapshot, $new_bn, $batch . '_IN', self::SOURCE);
                    $restockBills = array_merge($restockBills, $inBills);
                    $this->assertRestockMatches($snapshot, $row['unique_code']);
                }
                $process_msg = sprintf(
                    '迁移完成 %s→%s，回库%d条',
                    $old_bn,
                    $new_bn,
                    count($snapshot)
                );
            }

            $this->setVisibled($new_bn, 1);
            $labelLib->markOldByBn($old_bn);
            $labelLib->markNewByBn($new_bn);
            // 成功态只补标，不刷新变更时间（时间以计价方式变更时为准）
            $labelLib->markMaster(intval($row['material_id']));
            $this->updateEvent($event_id, array(
                'status' => 'succ',
                'scene' => $skipSnapshotRestock
                    ? 'jj_to_dj_nomap'
                    : (($event['old_price_method'] === '1') ? 'jj_to_dj' : 'dj_to_jj'),
                'new_bn' => $new_bn,
                'restock_bill_bns' => implode(',', $restockBills),
                'process_msg' => $process_msg,
            ));
            return true;
        } catch (Exception $e) {
            $this->updateEvent($event_id, array(
                'status' => 'fail',
                'process_msg' => $e->getMessage(),
            ));
            $materialLib->markFail($row['material_id'], '价格类型变更失败：' . $e->getMessage());
            return false;
        }
    }

    protected function findOpenEventForMaterial(array $row)
    {
        $mdl = app::get('goldentra')->model('pricetype_event');
        $material_id = intval($row['material_id']);
        $list = $mdl->getList(
            '*',
            array(
                'material_id' => $material_id,
                'status|in' => array('pending', 'frozen', 'cleared', 'waiting_map', 'restocked', 'fail'),
            ),
            0,
            5,
            'event_id DESC'
        );
        if (empty($list)) {
            // 兼容 fail 后同日重推：按 unique_code + 当前价格方式找 pending
            return null;
        }
        foreach ($list as $ev) {
            if ($ev['status'] === 'fail') {
                continue;
            }
            if ($ev['new_price_method'] !== '' && (string) $ev['new_price_method'] !== (string) $row['price_method']) {
                continue;
            }
            return $ev;
        }
        // fail 且价格方式仍匹配 → 允许重试
        foreach ($list as $ev) {
            if ($ev['status'] === 'fail'
                && (string) $ev['new_price_method'] === (string) $row['price_method']
            ) {
                $mdl->update(
                    array('status' => 'pending', 'process_msg' => '', 'up_time' => time()),
                    array('event_id' => $ev['event_id'])
                );
                $ev['status'] = 'pending';
                return $ev;
            }
        }
        return null;
    }

    protected function buildEventKey($unique_code, $oldPm, $newPm, $old_bn)
    {
        return substr(md5($unique_code . '|' . $oldPm . '|' . $newPm . '|' . $old_bn), 0, 32);
    }

    protected function migrateBatchNo(array $event)
    {
        $batch = !empty($event['sync_batch_no']) ? $event['sync_batch_no'] : ('PT' . $event['event_id']);
        return 'PT_' . $event['event_id'] . '_' . $batch;
    }

    protected function updateEvent($event_id, array $data)
    {
        $data['up_time'] = time();
        app::get('goldentra')->model('pricetype_event')->update($data, array('event_id' => $event_id));
    }

    protected function decodeSnapshot($json)
    {
        if ($json === '' || $json === null) {
            return array();
        }
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : array();
    }

    /**
     * 按唯一码查在库 SN（跨仓）
     */
    public function findInStockByUniqueCode($unique_code)
    {
        if (!kernel::single('goldentra_stock_machinecenter')->isInstalled()) {
            return array();
        }
        $in = kernel::single('goldentra_stock_machinecenter')->sqlInStockStatuses();
        $db = kernel::database();
        $code = kernel::single('goldentra_db')->escape($unique_code, $db);
        $rows = $db->select(
            "SELECT mc.sn AS unique_code, mc.branch_id, mc.branch_bn, mc.material_bn AS bn, mc.bm_id
            FROM sdb_machinecenter_material_sn mc
            WHERE mc.sn = '{$code}' AND mc.status IN ({$in})"
        );
        return $this->normalizeSnapshotRows($rows);
    }

    /**
     * 指定料号上、指定唯一码的在库 SN
     *
     * @param string $unique_code
     * @param string $material_bn
     * @return array
     */
    public function findInStockOnBn($unique_code, $material_bn)
    {
        $unique_code = trim((string) $unique_code);
        $material_bn = trim((string) $material_bn);
        if ($unique_code === '' || $material_bn === '') {
            return array();
        }
        if (!kernel::single('goldentra_stock_machinecenter')->isInstalled()) {
            return array();
        }
        $in = kernel::single('goldentra_stock_machinecenter')->sqlInStockStatuses();
        $db = kernel::database();
        $code = kernel::single('goldentra_db')->escape($unique_code, $db);
        $bn = kernel::single('goldentra_db')->escape($material_bn, $db);
        $rows = $db->select(
            "SELECT mc.sn AS unique_code, mc.branch_id, mc.branch_bn, mc.material_bn AS bn, mc.bm_id
            FROM sdb_machinecenter_material_sn mc
            WHERE mc.sn = '{$code}' AND mc.material_bn = '{$bn}' AND mc.status IN ({$in})"
        );
        return $this->normalizeSnapshotRows($rows);
    }

    /**
     * 按基础物料查在库唯一码
     */
    public function findInStockByMaterials(array $bmIds, array $materialBns)
    {
        if (!kernel::single('goldentra_stock_machinecenter')->isInstalled()) {
            return array();
        }
        $in = kernel::single('goldentra_stock_machinecenter')->sqlInStockStatuses();
        $db = kernel::database();
        $conds = array();
        if (!empty($materialBns)) {
            $bns = array();
            foreach ($materialBns as $bn) {
                $bns[] = "'" . kernel::single('goldentra_db')->escape($bn, $db) . "'";
            }
            $conds[] = 'mc.material_bn IN (' . implode(',', $bns) . ')';
        }
        if (!empty($bmIds)) {
            $ids = array();
            foreach ($bmIds as $id) {
                $ids[] = intval($id);
            }
            $conds[] = 'mc.bm_id IN (' . implode(',', $ids) . ')';
        }
        if (empty($conds)) {
            return array();
        }
        $rows = $db->select(
            "SELECT mc.sn AS unique_code, mc.branch_id, mc.branch_bn, mc.material_bn AS bn, mc.bm_id
            FROM sdb_machinecenter_material_sn mc
            WHERE mc.status IN ({$in}) AND (" . implode(' OR ', $conds) . ")"
        );
        return $this->normalizeSnapshotRows($rows);
    }

    protected function normalizeSnapshotRows($rows)
    {
        $out = array();
        $seen = array();
        foreach ((array) $rows as $r) {
            $branch_id = intval($r['branch_id']);
            $branch_bn = trim((string) $r['branch_bn']);
            if ($branch_bn === '' && $branch_id > 0) {
                $b = app::get('ome')->model('branch')->dump($branch_id, 'branch_bn');
                $branch_bn = !empty($b['branch_bn']) ? $b['branch_bn'] : '';
            }
            $key = $r['unique_code'] . '|' . $branch_id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = 1;
            $out[] = array(
                'unique_code' => $r['unique_code'],
                'branch_id' => $branch_id,
                'branch_bn' => $branch_bn,
                'bn' => $r['bn'],
                'bm_id' => intval($r['bm_id']),
            );
        }
        return $out;
    }

    /**
     * 调整单出库（diff=-1），按仓合并
     *
     * @return string[] adjust_bn list
     */
    public function adjustOutFromSnapshot(array $snapshot, $sync_batch_no, $source)
    {
        $rows = array();
        $now = date('Y-m-d H:i:s');
        foreach ($snapshot as $line) {
            if (empty($line['branch_id']) || empty($line['bn']) || empty($line['unique_code'])) {
                continue;
            }
            $rows[] = array(
                'stock_id' => 0,
                'branch_id' => $line['branch_id'],
                'branch_bn' => $line['branch_bn'],
                'unique_code' => $line['unique_code'],
                'diff_qty' => -1,
                'snapshot_date' => $now,
                'sync_batch_no' => $sync_batch_no,
                'bn' => $line['bn'],
                'adjust_source' => $source,
            );
        }
        if (empty($rows)) {
            return array();
        }
        $results = kernel::single('goldentra_stock_adjustment')->createFromDiffBatch($rows);
        return $this->collectBillBns($results);
    }

    /**
     * 调整单入库（diff=+1），挂到新物料编码
     *
     * @return string[]
     */
    public function adjustInFromSnapshot(array $snapshot, $new_bn, $sync_batch_no, $source)
    {
        $rows = array();
        $now = date('Y-m-d H:i:s');
        foreach ($snapshot as $line) {
            if (empty($line['branch_id']) || empty($line['unique_code'])) {
                continue;
            }
            $rows[] = array(
                'stock_id' => 0,
                'branch_id' => $line['branch_id'],
                'branch_bn' => $line['branch_bn'],
                'unique_code' => $line['unique_code'],
                'diff_qty' => 1,
                'snapshot_date' => $now,
                'sync_batch_no' => $sync_batch_no,
                'bn' => $new_bn,
                'adjust_source' => $source,
            );
        }
        if (empty($rows)) {
            return array();
        }
        $results = kernel::single('goldentra_stock_adjustment')->createFromDiffBatch($rows);
        return $this->collectBillBns($results);
    }

    protected function collectBillBns(array $results)
    {
        $bns = array();
        foreach ($results as $r) {
            if (!empty($r['bill_bn'])) {
                $bns[] = $r['bill_bn'];
            }
        }
        return array_values(array_unique($bns));
    }

    protected function assertRestockMatches(array $snapshot, $unique_code)
    {
        $expect = count($snapshot);
        if ($expect <= 0) {
            return;
        }
        $after = $this->findInStockByUniqueCode($unique_code);
        if (count($after) !== $expect) {
            throw new Exception(sprintf(
                '回库校验失败：快照%d条，当前在库%d条',
                $expect,
                count($after)
            ));
        }
    }

    /**
     * Mapping 禁用等场景：将基础物料设为停售（visibled=2）
     * 价格类型迁移（计转定）不再调用，避免共用料号时总库存列表消失
     */
    public function freezeByBns(array $bns)
    {
        foreach (array_unique(array_filter($bns)) as $bn) {
            $this->setVisibled($bn, 2);
        }
    }

    protected function setVisibled($material_bn, $visibled)
    {
        $material_bn = trim((string) $material_bn);
        if ($material_bn === '') {
            return;
        }
        $bm = app::get('material')->model('basic_material')->dump(
            array('material_bn' => $material_bn),
            'bm_id'
        );
        if (empty($bm['bm_id'])) {
            return;
        }
        app::get('material')->model('basic_material')->update(
            array('visibled' => intval($visibled)),
            array('bm_id' => $bm['bm_id'])
        );
    }

    /**
     * @return array [bmIds, materialBns]
     */
    protected function collectMaterialTargets(array $items)
    {
        // 直接复现 collectMaterialTargets，避免反射
        $bmIds = array();
        $materialBns = array();
        $bmMdl = app::get('material')->model('basic_material');

        foreach ($items as $item) {
            $bn = trim(isset($item['bn']) ? $item['bn'] : '');
            if ($bn === '' && !empty($item['item_id'])) {
                $bn = trim((string) goldentra_mapping_code::ensureItemBn($item));
            }
            if ($bn !== '') {
                $materialBns[] = $bn;
            }
            $bm_id = intval(isset($item['bm_id']) ? $item['bm_id'] : 0);
            if ($bm_id > 0) {
                $bmIds[] = $bm_id;
                continue;
            }
            if ($bn === '') {
                continue;
            }
            $bm = $bmMdl->dump(array('material_bn' => $bn), 'bm_id');
            if (!empty($bm['bm_id'])) {
                $bmIds[] = intval($bm['bm_id']);
            }
        }

        return array(
            array_values(array_unique(array_filter($bmIds))),
            array_values(array_unique(array_filter($materialBns))),
        );
    }
}
