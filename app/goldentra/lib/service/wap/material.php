<?php

/**
 * WAP 端读取 SAP 主档展示字段（模号编码、款式编码、款式名称、克重范围、唯一码）及基础物料克重
 *
 * 注册 service：wap.service.delivery.items.display
 * 未注册或未安装 goldentra 时，WAP 侧 servicelist 为空，展示字段不扩展。
 *
 * 每条明细可附加 item_display_lines，结构示例：
 * array('label' => '模号编码', 'value' => '1040004773', 'text' => '模号编码：1040004773')
 *
 * 字段说明：
 */
class goldentra_service_wap_material
{
    const DISPLAY_FIELDS = array('mold_code', 'style_code', 'style_name', 'weight', 'weight_range', 'stock_unique_codes');

    /**
     * goldentra 是否可用
     */
    public function isEnabled()
    {
        return app::get('goldentra')->is_installed();
    }

    /**
     * 按 SAP 唯一码读取展示字段（非基础物料编码）
     *
     * @param string $unique_code goldentra_material.unique_code
     * @return array mold_code, style_code, style_name, weight, weight_range
     */
    public function getDisplayByUniqueCode($unique_code)
    {
        $unique_code = trim((string) $unique_code);
        if ($unique_code === '' || !$this->isEnabled()) {
            return $this->emptyDisplay();
        }

        $row = app::get('goldentra')->model('material')->db_dump(
            array('unique_code' => $unique_code),
            'bn,mold_code,style_code,style_name,weight'
        );
        if (!empty($row['bn'])) {
            $bn = trim((string) $row['bn']);
            $basicWeights = $this->loadBasicMaterialWeightByBns(array($bn));
            if (isset($basicWeights[$bn])) {
                $row['basic_weight'] = $basicWeights[$bn];
            }
        }

        return $this->formatDisplayRow($row);
    }

    /**
     * 按基础物料编码读取展示字段（WAP 主路径，与发货明细 bn 关联）
     *
     * @param string $bn goldentra_material.bn，转基础物料成功后必有值
     * @return array
     */
    public function getDisplayByBn($bn)
    {
        $bn = trim((string) $bn);
        if ($bn === '' || !$this->isEnabled()) {
            return $this->emptyDisplay();
        }

        $rows = app::get('goldentra')->model('material')->getList(
            'bn,mold_code,style_code,style_name,weight',
            array('bn' => $bn),
            0,
            1,
            'material_id DESC'
        );
        $row = !empty($rows[0]) ? $rows[0] : null;
        if (!empty($row)) {
            $basicWeights = $this->loadBasicMaterialWeightByBns(array($bn));
            if (isset($basicWeights[$bn])) {
                $row['basic_weight'] = $basicWeights[$bn];
            }
        }

        return $this->formatDisplayRow($row);
    }

    /**
     * 为发货单明细批量附加 goldentra 展示字段
     *
     * @param int   $delivery_id OME 发货单 ID
     * @param array $items       getDeliveryItemList 返回的明细
     * @param array $context       wap_status 等扩展上下文
     * @return array
     */
    public function enrichDeliveryItems($delivery_id, array $items, array $context = array())
    {
        if (!$this->isEnabled() || empty($items)) {
            return $items;
        }

        $delivery_id = intval($delivery_id);
        $wapStatus   = isset($context['wap_status']) ? (string) $context['wap_status'] : '';
        $showInStock = $this->shouldShowStockUniqueCodes($wapStatus);
        $showShipped = $this->shouldShowShippedUniqueCodes($wapStatus);
        $serialMap   = $this->buildSerialMap($delivery_id);
        $uniqueCodes = array();
        $bnList      = array();

        foreach ($items as $item) {
            $bn = trim((string) $item['bn']);
            if ($bn !== '') {
                $bnList[$bn] = $bn;
            }
            $key = $this->itemKey($item);
            if (!empty($serialMap[$key][0])) {
                $uniqueCodes[$serialMap[$key][0]] = $serialMap[$key][0];
            }
        }

        $branchId     = !empty($context['branch_id'])
            ? (int) $context['branch_id']
            : $this->resolveBranchIdByDeliveryId($delivery_id);
        $inStockSnMap = $showInStock
            ? $this->loadInStockUniqueCodesForItems($branchId, $items)
            : array();
        $shippedSnMap = $showShipped && $delivery_id > 0
            ? kernel::single('wap_return_serial')->getShippedUniqueCodesByDeliveryId(
                $delivery_id,
                array_values($bnList)
            )
            : array();
        $byUnique     = $this->loadDisplayByUniqueCodes(array_values($uniqueCodes));
        $byBn         = $this->loadDisplayByBns(array_values($bnList), $inStockSnMap);

        foreach ($items as $key => $item) {
            $display = $this->emptyDisplay();
            $itemKey = $this->itemKey($item);
            $itemBn  = trim((string) $item['bn']);
            $scanned = !empty($serialMap[$itemKey]) ? (array) $serialMap[$itemKey] : array();

            // 主路径：发货明细 bn = goldentra 基础物料编码（SAP 主档转物料成功后回填）
            if ($itemBn !== '' && !empty($byBn[$itemBn])) {
                $display = $byBn[$itemBn];
            } elseif (!empty($serialMap[$itemKey][0]) && !empty($byUnique[$serialMap[$itemKey][0]])) {
                // 补充：已扫描唯一码时按 unique_code 精确匹配（同 bn 多 SN 场景）
                $display = $byUnique[$serialMap[$itemKey][0]];
            }

            if ($showShipped) {
                $display['stock_unique_codes'] = $this->formatStockUniqueCodesForItem(
                    $item,
                    $scanned,
                    ($itemBn !== '' && !empty($shippedSnMap[$itemBn])) ? $shippedSnMap[$itemBn] : ''
                );
            } elseif ($showInStock) {
                $display['stock_unique_codes'] = $this->formatStockUniqueCodesForItem(
                    $item,
                    $scanned,
                    ($itemBn !== '' && !empty($inStockSnMap[$itemBn])) ? $inStockSnMap[$itemBn] : ''
                );
            } else {
                $display['stock_unique_codes'] = '';
            }

            $items[$key] = $this->appendItemDisplayLines($items[$key], $display);
        }

        return $items;
    }

    /**
     * 按货号批量附加 goldentra 展示字段（退货/换货等非发货单场景）
     *
     * @param array  $items
     * @param string $bnField 货号字段名，默认 bn
     * @param array  $context original_reship_bn 等；有值时唯一码取原发货单出库 SN
     * @return array
     */
    public function enrichItemsByBn(array $items, $bnField = 'bn', array $context = array())
    {
        if (!$this->isEnabled() || empty($items)) {
            return $items;
        }

        $bnField = trim((string) $bnField) ?: 'bn';
        $bnList  = array();

        foreach ($items as $item) {
            $bn = trim((string) $item[$bnField]);
            if ($bn !== '') {
                $bnList[$bn] = $bn;
            }
        }

        $originalReshipBn = trim((string) ($context['original_reship_bn'] ?? ''));
        $returnStatus     = (string) ($context['wap_return_status'] ?? '');
        $uniqueSnMap      = array();
        $inStockSnMap     = array();

        if ($originalReshipBn !== '') {
            $serialLib = kernel::single('wap_return_serial');
            if ($returnStatus === '3') {
                $uniqueSnMap = $serialLib->getReturnUniqueCodesForDisplay(
                    $context,
                    array_values($bnList)
                );
                if (empty($uniqueSnMap)) {
                    $uniqueSnMap = $serialLib->getShippedUniqueCodesForDisplay(
                        $context,
                        array_values($bnList)
                    );
                }
            } else {
                $uniqueSnMap = $serialLib->getShippedUniqueCodesForDisplay(
                    $context,
                    array_values($bnList)
                );
            }
        } else {
            $branchId     = $this->resolveCurrentBranchId();
            $inStockSnMap = $this->loadInStockUniqueCodesByBns($branchId, array_values($bnList));
        }

        $byBn = $this->loadDisplayByBns(array_values($bnList), $inStockSnMap);

        foreach ($items as $key => $item) {
            $bn      = trim((string) $item[$bnField]);
            $display = ($bn !== '' && !empty($byBn[$bn])) ? $byBn[$bn] : $this->emptyDisplay();

            if ($bn !== '' && empty($display['stock_unique_codes'])) {
                if (!empty($uniqueSnMap[$bn])) {
                    $display['stock_unique_codes'] = $this->formatStockUniqueCodesForItem(
                        $item,
                        array(),
                        $uniqueSnMap[$bn]
                    );
                } elseif (!empty($inStockSnMap[$bn])) {
                    $display['stock_unique_codes'] = $this->formatStockUniqueCodesForItem(
                        $item,
                        array(),
                        $inStockSnMap[$bn]
                    );
                }
            }

            $items[$key] = $this->appendItemDisplayLines($items[$key], $display);
        }

        return $items;
    }

    /**
     * 合并已附加的展示行到目标明细
     */
    public function mergeDisplayFields(array $source, array $target)
    {
        if (!empty($source['item_display_lines'])) {
            $existing = isset($target['item_display_lines']) ? (array) $target['item_display_lines'] : array();
            $target['item_display_lines'] = array_merge($existing, $source['item_display_lines']);
        }

        return $target;
    }

    /**
     * 由 service 定义展示行（标题 + 值），无值则不输出
     *
     * @param array $display mold_code/style_code/style_name/weight/weight_range/stock_unique_codes
     * @return array 每项：label, value, text（如 模号编码：1040004773）
     */
    protected function buildItemDisplayLines(array $display)
    {
        $lines = array();
        $defs  = array(
            array('label' => '模号编码', 'key' => 'mold_code'),
            array('label' => '款式编码', 'key' => 'style_code'),
            array('label' => '款式名称', 'key' => 'style_name'),
            array('label' => '克重', 'key' => 'weight'),
            array('label' => '克重范围', 'key' => 'weight_range'),
            array('label' => '唯一码', 'key' => 'stock_unique_codes'),
        );

        foreach ($defs as $def) {
            $value = trim((string) $display[$def['key']]);
            if ($value === '') {
                continue;
            }
            $lines[] = array(
                'label' => $def['label'],
                'value' => $value,
                'text'  => $def['label'] . '：' . $value,
            );
        }

        return $lines;
    }

    /**
     * 将展示行追加到明细（支持多个 service 叠加）
     */
    protected function appendItemDisplayLines(array $item, array $display)
    {
        $lines = $this->buildItemDisplayLines($display);
        if (empty($lines)) {
            return $item;
        }

        $existing = isset($item['item_display_lines']) ? (array) $item['item_display_lines'] : array();
        $item['item_display_lines'] = array_merge($existing, $lines);

        return $item;
    }

    protected function buildSerialMap($delivery_id)
    {
        $map = array();
        if ($delivery_id <= 0) {
            return $map;
        }

        $rows = app::get('ome')->model('delivery_items_serial')->getList(
            'bn,product_id,serial_number',
            array('delivery_id' => $delivery_id),
            0,
            -1
        );
        foreach ((array) $rows as $row) {
            $serial = trim((string) $row['serial_number']);
            if ($serial === '') {
                continue;
            }
            $key = trim((string) $row['bn']) . '|' . intval($row['product_id']);
            if (!isset($map[$key])) {
                $map[$key] = array();
            }
            if (!in_array($serial, $map[$key], true)) {
                $map[$key][] = $serial;
            }
        }

        return $map;
    }

    protected function loadDisplayByUniqueCodes(array $uniqueCodes)
    {
        $result = array();
        $uniqueCodes = array_values(array_filter(array_unique(array_map('trim', $uniqueCodes))));
        if (empty($uniqueCodes)) {
            return $result;
        }

        $rows = app::get('goldentra')->model('material')->getList(
            'unique_code,bn,mold_code,style_code,style_name,weight',
            array('unique_code' => $uniqueCodes),
            0,
            -1
        );
        $bnList = array();
        foreach ((array) $rows as $row) {
            $bn = trim((string) $row['bn']);
            if ($bn !== '') {
                $bnList[$bn] = $bn;
            }
        }
        $weightRanges  = $this->loadWeightRangeByBns(array_values($bnList));
        $basicWeights  = $this->loadBasicMaterialWeightByBns(array_values($bnList));
        foreach ((array) $rows as $row) {
            $bn = trim((string) $row['bn']);
            if ($bn !== '' && !empty($weightRanges[$bn])) {
                $row['weight_range'] = $weightRanges[$bn];
            }
            if ($bn !== '' && isset($basicWeights[$bn])) {
                $row['basic_weight'] = $basicWeights[$bn];
            }
            $result[$row['unique_code']] = $this->formatDisplayRow($row);
        }

        return $result;
    }

    protected function loadDisplayByBns(array $bnList, array $inStockSnMap = array())
    {
        $result = array();
        $bnList = array_values(array_filter(array_unique(array_map('trim', $bnList))));
        if (empty($bnList)) {
            return $result;
        }

        $rows = app::get('goldentra')->model('material')->getList(
            'bn,mold_code,style_code,style_name,weight,material_id',
            array('bn' => $bnList),
            0,
            -1,
            'material_id DESC'
        );
        $weightRanges = $this->loadWeightRangeByBns($bnList);
        $basicWeights = $this->loadBasicMaterialWeightByBns($bnList);
        foreach ((array) $rows as $row) {
            $bn = trim((string) $row['bn']);
            if ($bn === '' || isset($result[$bn])) {
                continue;
            }
            if (!empty($weightRanges[$bn])) {
                $row['weight_range'] = $weightRanges[$bn];
            }
            if (isset($basicWeights[$bn])) {
                $row['basic_weight'] = $basicWeights[$bn];
            }
            $result[$bn] = $this->formatDisplayRow($row);
        }

        return $result;
    }

    /**
     * 仅已取消、已发货不展示在库唯一码
     */
    protected function shouldShowStockUniqueCodes($wapStatus)
    {
        return !in_array((string) $wapStatus, array('1', '3'), true);
    }

    /**
     * 已发货（待签收/已签收）展示该发货单出库唯一码
     */
    protected function shouldShowShippedUniqueCodes($wapStatus)
    {
        return (string) $wapStatus === '3';
    }

    /**
     * 按明细数量展示唯一码：优先已扫描，其次在库 SN，最多 number 条
     */
    protected function formatStockUniqueCodesForItem(array $item, array $scannedSerials, $inStockSnString)
    {
        $limit = 0;
        if (isset($item['number'])) {
            $limit = intval($item['number']);
        } elseif (isset($item['num'])) {
            $limit = intval($item['num']);
        }
        if ($limit <= 0) {
            return '';
        }

        $codes = array();
        foreach ((array) $scannedSerials as $sn) {
            $sn = trim((string) $sn);
            if ($sn === '' || in_array($sn, $codes, true)) {
                continue;
            }
            $codes[] = $sn;
            if (count($codes) >= $limit) {
                return implode('、', $codes);
            }
        }

        if ($inStockSnString !== '') {
            foreach (explode('、', $inStockSnString) as $sn) {
                $sn = trim($sn);
                if ($sn === '' || in_array($sn, $codes, true)) {
                    continue;
                }
                $codes[] = $sn;
                if (count($codes) >= $limit) {
                    break;
                }
            }
        }

        return empty($codes) ? '' : implode('、', $codes);
    }

    /**
     * 发货单对应门店仓库 ID
     */
    protected function resolveBranchIdByDeliveryId($delivery_id)
    {
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            return 0;
        }

        $row = app::get('ome')->model('delivery')->dump(
            array('delivery_id' => $delivery_id),
            'branch_id'
        );

        return !empty($row['branch_id']) ? intval($row['branch_id']) : 0;
    }

    /**
     * WAP 当前操作员门店对应仓库 ID
     */
    protected function resolveCurrentBranchId()
    {
        $store = kernel::single('wap_store_user')->getCurrentStore();
        return !empty($store['branch_id']) ? intval($store['branch_id']) : 0;
    }

    /**
     * 当前门店仓库、明细货号/bm_id 在库唯一码（展示用）
     *
     * @param int   $branch_id
     * @param array $items 发货明细（bn、product_id）
     * @return array bn => 唯一码1、唯一码2
     */
    protected function loadInStockUniqueCodesForItems($branch_id, array $items)
    {
        $result = array();
        $branch_id = intval($branch_id);
        if ($branch_id <= 0 || empty($items)) {
            return $result;
        }
        if (!app::get('machinecenter')->is_installed()) {
            return $result;
        }

        $bnList  = array();
        $bmIds   = array();
        $bmToBn  = array();
        foreach ($items as $item) {
            $bn = trim((string) $item['bn']);
            $bmId = intval($item['product_id']);
            if ($bn !== '') {
                $bnList[$bn] = $bn;
            }
            if ($bmId > 0 && $bn !== '') {
                $bmIds[$bmId] = $bmId;
                if (!isset($bmToBn[$bmId])) {
                    $bmToBn[$bmId] = $bn;
                }
            }
        }

        if (empty($bnList) && empty($bmIds)) {
            return $result;
        }

        $db = kernel::database();
        $inStockSql = kernel::single('goldentra_stock_machinecenter')->sqlInStockStatuses();
        $snBuckets  = array();

        if (!empty($bnList)) {
            $escaped = array();
            foreach ($bnList as $bn) {
                $escaped[] = "'" . kernel::single('goldentra_db')->escape($bn, $db) . "'";
            }
            $sql = "SELECT material_bn, bm_id, sn FROM sdb_machinecenter_material_sn
                WHERE branch_id = {$branch_id}
                AND status IN ({$inStockSql})
                AND material_bn IN (" . implode(',', $escaped) . ")
                ORDER BY material_bn ASC, sn ASC";
            $this->appendInStockSnRows($snBuckets, $db->select($sql), $bnList, $bmToBn);
        }

        if (!empty($bmIds)) {
            $bmIn = implode(',', array_map('intval', array_values($bmIds)));
            $sql = "SELECT material_bn, bm_id, sn FROM sdb_machinecenter_material_sn
                WHERE branch_id = {$branch_id}
                AND status IN ({$inStockSql})
                AND bm_id IN ({$bmIn})
                ORDER BY bm_id ASC, sn ASC";
            $this->appendInStockSnRows($snBuckets, $db->select($sql), $bnList, $bmToBn);
        }

        foreach ($snBuckets as $bn => $sns) {
            $result[$bn] = implode('、', $sns);
        }

        return $result;
    }

    /**
     * @param array $buckets   bn => sn[]
     * @param array $rows
     * @param array $bnList    合法货号白名单
     * @param array $bmToBn    bm_id => 发货明细货号
     */
    protected function appendInStockSnRows(array &$buckets, $rows, array $bnList, array $bmToBn)
    {
        foreach ((array) $rows as $row) {
            $sn = trim((string) $row['sn']);
            if ($sn === '') {
                continue;
            }

            $targetBn = '';
            $materialBn = trim((string) $row['material_bn']);
            if ($materialBn !== '' && !empty($bnList[$materialBn])) {
                $targetBn = $materialBn;
            } elseif (!empty($row['bm_id']) && !empty($bmToBn[(int) $row['bm_id']])) {
                $targetBn = $bmToBn[(int) $row['bm_id']];
            }
            if ($targetBn === '') {
                continue;
            }

            if (!isset($buckets[$targetBn])) {
                $buckets[$targetBn] = array();
            }
            if (!in_array($sn, $buckets[$targetBn], true)) {
                $buckets[$targetBn][] = $sn;
            }
        }
    }

    /**
     * @param int   $branch_id
     * @param array $bnList 基础物料编码
     * @return array bn => 唯一码1、唯一码2
     */
    protected function loadInStockUniqueCodesByBns($branch_id, array $bnList)
    {
        $items = array();
        foreach ($bnList as $bn) {
            $items[] = array('bn' => $bn, 'product_id' => 0);
        }

        return $this->loadInStockUniqueCodesForItems($branch_id, $items);
    }

    /**
     * 按基础物料编码批量读取「重量」(sdb_material_basic_material_ext.weight)
     *
     * @param array $bnList material_bn
     * @return array bn => weight
     */
    protected function loadBasicMaterialWeightByBns(array $bnList)
    {
        $result = array();
        $bnList = array_values(array_filter(array_unique(array_map('trim', $bnList))));
        if (empty($bnList) || !app::get('material')->is_installed()) {
            return $result;
        }

        $rows = app::get('material')->model('basic_material')->getList(
            'material_bn,bm_id',
            array('material_bn' => $bnList, 'disabled' => 'false'),
            0,
            -1
        );
        if (empty($rows)) {
            return $result;
        }

        $bmIds    = array();
        $bnToBmId = array();
        foreach ((array) $rows as $row) {
            $bn = trim((string) $row['material_bn']);
            $bmId = intval($row['bm_id']);
            if ($bn === '' || $bmId <= 0) {
                continue;
            }
            $bmIds[$bmId] = $bmId;
            $bnToBmId[$bn] = $bmId;
        }
        if (empty($bmIds)) {
            return $result;
        }

        $extRows = app::get('material')->model('basic_material_ext')->getList(
            'bm_id,weight',
            array('bm_id' => array_values($bmIds)),
            0,
            -1
        );
        $weightByBmId = array();
        foreach ((array) $extRows as $extRow) {
            $weightByBmId[intval($extRow['bm_id'])] = $extRow['weight'];
        }

        foreach ($bnToBmId as $bn => $bmId) {
            if (array_key_exists($bmId, $weightByBmId)) {
                $result[$bn] = $weightByBmId[$bmId];
            }
        }

        return $result;
    }

    /**
     * 按基础物料编码批量读取 mapping_item 克重范围
     *
     * @param array $bnList
     * @return array bn => min_weight~max_weight
     */
    protected function loadWeightRangeByBns(array $bnList)
    {
        $result = array();
        $bnList = array_values(array_filter(array_unique(array_map('trim', $bnList))));
        if (empty($bnList)) {
            return $result;
        }

        $db = kernel::database();
        $escaped = array();
        foreach ($bnList as $bn) {
            $escaped[] = "'" . kernel::single('goldentra_db')->escape($bn, $db) . "'";
        }

        $sql = "SELECT i.bn, i.min_weight, i.max_weight FROM sdb_goldentra_mapping_item AS i
            LEFT JOIN sdb_goldentra_mapping AS m ON m.mapping_id = i.mapping_id
            WHERE m.disabled = '0' AND i.bn IN (" . implode(',', $escaped) . ")";
        $rows = $db->select($sql);
        foreach ((array) $rows as $row) {
            $bn = trim((string) $row['bn']);
            if ($bn === '' || isset($result[$bn])) {
                continue;
            }
            $range = $this->formatWeightRange($row['min_weight'], $row['max_weight']);
            if ($range !== '') {
                $result[$bn] = $range;
            }
        }

        return $result;
    }

    /**
     * 按 mapping_item 解析克重范围（与克重同源主档，不做额外业务判断）
     */
    protected function resolveWeightRangeFromMaterialRow(array $materialRow)
    {
        $bn = trim((string) $materialRow['bn']);
        if ($bn !== '') {
            $item = kernel::single('goldentra_mapping')->getMappingByBn($bn);
            if (!empty($item)) {
                return $this->formatWeightRange($item['min_weight'], $item['max_weight']);
            }
        }

        $moldCode = trim((string) $materialRow['mold_code']);
        if ($moldCode === '' || !isset($materialRow['weight'])) {
            return '';
        }

        $weight = floatval($materialRow['weight']);
        $db = kernel::database();
        $moldEsc = kernel::single('goldentra_db')->escape($moldCode, $db);

        // selectrow 会自动追加 LIMIT 0,1，SQL 中不可再写 LIMIT
        $sql = "SELECT i.min_weight, i.max_weight FROM sdb_goldentra_mapping_item AS i
            LEFT JOIN sdb_goldentra_mapping AS m ON m.mapping_id = i.mapping_id
            WHERE m.disabled = '0'
            AND i.mold_code = '{$moldEsc}'
            AND i.min_weight <= '{$weight}' AND i.max_weight > '{$weight}'
            ORDER BY i.item_id DESC";
        $item = $db->selectrow($sql);
        if (empty($item)) {
            return '';
        }

        return $this->formatWeightRange($item['min_weight'], $item['max_weight']);
    }

    protected function itemKey(array $item)
    {
        return trim((string) $item['bn']) . '|' . intval($item['product_id']);
    }

    protected function formatDisplayRow($row)
    {
        if (empty($row)) {
            return $this->emptyDisplay();
        }

        $rowForRange = $row;
        if (array_key_exists('basic_weight', $row)) {
            $display = array(
                'mold_code'          => trim((string) $row['mold_code']),
                'style_code'         => trim((string) $row['style_code']),
                'style_name'         => trim((string) $row['style_name']),
                'weight'             => $this->formatWeight($row['basic_weight']),
                'weight_range'       => '',
                'stock_unique_codes' => '',
            );
            $rowForRange['weight'] = $row['basic_weight'];
        } else {
            $display = array(
                'mold_code'          => trim((string) $row['mold_code']),
                'style_code'         => trim((string) $row['style_code']),
                'style_name'         => trim((string) $row['style_name']),
                'weight'             => '',
                'weight_range'       => '',
                'stock_unique_codes' => '',
            );
        }

        // 克重范围与克重同源：有主档即解析 mapping_item，不做额外业务判断
        if (!empty($row['weight_range'])) {
            $display['weight_range'] = trim((string) $row['weight_range']);
        } else {
            $display['weight_range'] = $this->resolveWeightRangeFromMaterialRow($rowForRange);
        }

        if (!empty($row['stock_unique_codes'])) {
            $display['stock_unique_codes'] = trim((string) $row['stock_unique_codes']);
        }

        return $display;
    }

    protected function formatWeightRange($minWeight, $maxWeight)
    {
        $min = $this->formatWeight($minWeight);
        $max = $this->formatWeight($maxWeight);
        if ($min === '' || $max === '') {
            return '';
        }

        return $min . '~' . $max;
    }

    protected function formatWeight($weight)
    {
        if ($weight === '' || $weight === null) {
            return '';
        }
        $formatted = number_format((float) $weight, 3, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    protected function emptyDisplay()
    {
        return array(
            'mold_code'          => '',
            'style_code'         => '',
            'style_name'         => '',
            'weight'             => '',
            'weight_range'       => '',
            'stock_unique_codes' => '',
        );
    }
}
