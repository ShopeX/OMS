<?php
/**
 * WAP 退货入库唯一码校验
 */
class wap_return_serial
{
    /** @var array */
    private $reshipCache = [];

    /** @var array */
    private $deliveryShippedCache = [];

    /** @var array */
    private $orderReturnedCache = [];

    /** @var array */
    private $deliveryIdCache = [];

    /** @var array */
    private $reshipReturnedCache = [];

    /**
     * 按 OME 发货单 ID 获取出库 SN（WAP 已发货列表展示用）
     *
     * @param int   $deliveryId
     * @param array $bnList 基础物料编码
     * @return array bn => 唯一码1、唯一码2
     */
    public function getShippedUniqueCodesByDeliveryId($deliveryId, array $bnList = array())
    {
        $deliveryId = intval($deliveryId);
        if ($deliveryId <= 0) {
            return array();
        }

        $buckets = $this->_buildShippedSnBucketsByBn(
            $this->_getDeliveryShippedSerialMap($deliveryId)
        );
        if (empty($buckets)) {
            return array();
        }

        $bnList = array_values(array_filter(array_unique(array_map('trim', $bnList))));
        $result = array();

        if (!empty($bnList)) {
            foreach ($bnList as $bn) {
                if (!empty($buckets[$bn])) {
                    $result[$bn] = implode('、', $buckets[$bn]);
                }
            }
        } else {
            foreach ($buckets as $bn => $sns) {
                $result[$bn] = implode('、', $sns);
            }
        }

        return $result;
    }

    /**
     * 按原始退换货单号解析发货出库 SN（WAP 退货列表展示用）
     *
     * @param array $return 至少含 original_reship_bn；可选 return_id、order_id 用于反查 delivery_id
     * @param array $bnList 基础物料编码
     * @return array bn => 唯一码1、唯一码2
     */
    public function getShippedUniqueCodesForDisplay(array $return, array $bnList = array())
    {
        $bnList = array_values(array_filter(array_unique(array_map('trim', $bnList))));
        if (empty($return['original_reship_bn'])) {
            return array();
        }

        $reship = $this->_getReshipByReturn($return);
        if (!empty($reship['delivery_id'])) {
            $result = $this->getShippedUniqueCodesByDeliveryId(intval($reship['delivery_id']), $bnList);
            if (!empty($result)) {
                return $result;
            }
        }

        $result         = array();
        $deliveryByBn   = array();
        $deliverySnPool = array();

        foreach ($bnList as $bn) {
            if ($bn === '') {
                continue;
            }
            if (!array_key_exists($bn, $deliveryByBn)) {
                $deliveryByBn[$bn] = $this->_resolveDeliveryId($return, $bn, $resolveErr);
            }
            $deliveryId = intval($deliveryByBn[$bn]);
            if ($deliveryId <= 0) {
                continue;
            }

            $cacheKey = '_delivery_' . $deliveryId;
            if (!isset($deliverySnPool[$cacheKey])) {
                $deliverySnPool[$cacheKey] = $this->_buildShippedSnBucketsByBn(
                    $this->_getDeliveryShippedSerialMap($deliveryId)
                );
            }

            if (!empty($deliverySnPool[$cacheKey][$bn])) {
                $result[$bn] = implode('、', $deliverySnPool[$cacheKey][$bn]);
            }
        }

        return $result;
    }

    /**
     * 按原始退换货单号解析退货入库 SN（WAP 已退货列表展示用）
     *
     * @param array $return 至少含 original_reship_bn
     * @param array $bnList 基础物料编码
     * @return array bn => 唯一码1、唯一码2
     */
    public function getReturnUniqueCodesForDisplay(array $return, array $bnList = array())
    {
        $bnList = array_values(array_filter(array_unique(array_map('trim', $bnList))));
        if (empty($return['original_reship_bn'])) {
            return array();
        }

        $reship = $this->_getReshipByReturn($return);
        if (empty($reship['reship_id'])) {
            return array();
        }

        $buckets = $this->_buildShippedSnBucketsByBn(
            $this->_getReshipReturnedSerialMap(intval($reship['reship_id']), trim((string) $reship['reship_bn']))
        );

        return $this->_formatSnBucketsResult($buckets, $bnList);
    }

    /**
     * @param array $buckets bn => sn[]
     * @param array $bnList
     * @return array bn => 唯一码1、唯一码2
     */
    private function _formatSnBucketsResult(array $buckets, array $bnList)
    {
        if (empty($buckets)) {
            return array();
        }

        $result = array();
        if (!empty($bnList)) {
            foreach ($bnList as $bn) {
                if (!empty($buckets[$bn])) {
                    $result[$bn] = implode('、', $buckets[$bn]);
                }
            }
        } else {
            foreach ($buckets as $bn => $sns) {
                $result[$bn] = implode('、', $sns);
            }
        }

        return $result;
    }

    /**
     * @param array $serialMap serial_number => bn
     * @return array bn => sn[]
     */
    private function _buildShippedSnBucketsByBn(array $serialMap)
    {
        $buckets = array();
        foreach ($serialMap as $sn => $bn) {
            $sn  = trim((string) $sn);
            $bn  = trim((string) $bn);
            if ($sn === '' || $bn === '') {
                continue;
            }
            if (!isset($buckets[$bn])) {
                $buckets[$bn] = array();
            }
            if (!in_array($sn, $buckets[$bn], true)) {
                $buckets[$bn][] = $sn;
            }
        }

        return $buckets;
    }

    /**
     * 获取退货单唯一码明细
     */
    public function getReturnSerialInfo($return_id, &$err_msg = '')
    {
        $return = $this->_getReturn($return_id, $err_msg);
        if (!$return) {
            return false;
        }

        $items = app::get('wap')->model('return_items')->getList(
            '*',
            ['return_id' => $return_id, 'return_type' => 'return']
        );
        if (empty($items)) {
            $err_msg = '退货明细不存在';
            return false;
        }

        $serialLib   = kernel::single('material_serial');
        $serialItems = [];
        $hasSerial   = false;

        foreach ($items as $item) {
            if (!$serialLib->checkSerialById($item['product_id'])) {
                continue;
            }
            $hasSerial = true;
            $serialItems[] = [
                'product_id'   => $item['product_id'],
                'bn'           => $item['bn'],
                'product_name' => $item['name'],
                'number'       => intval($item['num']),
                'scanned_count'=> 0,
            ];
        }

        return [
            'has_serial'   => $hasSerial,
            'return_id'    => $return['return_id'],
            'reship_bn'    => $return['original_reship_bn'],
            'serial_items' => $serialItems,
        ];
    }

    /**
     * 扫描退货唯一码
     */
    public function scanReturnSerial($return_id, $sn, $scannedList = [], &$err_msg = '', $expected_bn = '')
    {
        $sn = trim($sn);
        $expected_bn = trim((string) $expected_bn);
        if ($sn === '') {
            $err_msg = '请输入唯一码';
            return false;
        }

        $return = $this->_getReturn($return_id, $err_msg);
        if (!$return) {
            return false;
        }

        if (!app::get('machinecenter')->is_installed()) {
            $err_msg = '机器中心未安装，无法校验唯一码';
            return false;
        }

        $info = $this->getReturnSerialInfo($return_id, $err_msg);
        if (!$info || !$info['has_serial']) {
            $err_msg = $err_msg ?: '该退货单无需扫描唯一码';
            return false;
        }

        $requiredMap = [];
        $nameMap     = [];
        foreach ($info['serial_items'] as $row) {
            $requiredMap[$row['bn']] = $row['number'];
            $nameMap[$row['bn']]      = $row['product_name'];
        }

        $scannedCount = [];
        foreach ((array)$scannedList as $row) {
            $scanSn = trim($row['sn'] ?? '');
            $scanBn = trim($row['bn'] ?? '');
            if ($scanSn === '' || $scanBn === '') {
                continue;
            }
            $scannedCount[$scanBn] = ($scannedCount[$scanBn] ?? 0) + 1;
        }

        foreach ($scannedList as $row) {
            if (trim($row['sn'] ?? '') === $sn) {
                $err_msg = '该唯一码已扫描，请勿重复扫描';
                return false;
            }
        }

        $snInfo = app::get('machinecenter')->model('material_sn')->db_dump(['sn' => $sn]);
        if (!$snInfo) {
            $err_msg = '唯一码不存在';
            return false;
        }

        $bn = $snInfo['material_bn'];
        if ($expected_bn !== '' && $bn !== $expected_bn) {
            $err_msg = '唯一码与当前商品不匹配，请在该商品行扫描';
            return false;
        }
        if (!isset($requiredMap[$bn])) {
            $err_msg = '唯一码与退货明细物料不匹配';
            return false;
        }
        if (intval($snInfo['branch_id']) !== intval($return['branch_id'])) {
            $err_msg = '唯一码不属于当前门店仓库';
            return false;
        }
        if (!$this->_isReturnableSn($snInfo, $return, $bn, $err_msg)) {
            return false;
        }
        if (($scannedCount[$bn] ?? 0) >= $requiredMap[$bn]) {
            $err_msg = '该物料唯一码已扫满';
            return false;
        }

        return [
            'sn'           => $sn,
            'bn'           => $bn,
            'product_name' => $nameMap[$bn],
        ];
    }

    /**
     * 退货入库前校验唯一码并返回各货号 sn_list
     */
    public function validateSerialItemsForReship($return_id, $items, $serialItems, &$err_msg = '')
    {
        $info = $this->getReturnSerialInfo($return_id, $err_msg);
        if (!$info) {
            return false;
        }
        if (!$info['has_serial']) {
            return [];
        }

        $itemNumMap = [];
        foreach ((array)$items as $item) {
            $bn = trim($item['bn'] ?? '');
            if ($bn === '') {
                continue;
            }
            $itemNumMap[$bn] = intval($item['num']);
        }

        $requiredMap = [];
        foreach ($info['serial_items'] as $row) {
            $requiredMap[$row['bn']] = $itemNumMap[$row['bn']] ?? $row['number'];
        }

        $result = [];
        foreach ($requiredMap as $bn => $needNum) {
            if ($needNum <= 0) {
                continue;
            }

            $snList = isset($serialItems[$bn]) ? (array)$serialItems[$bn] : [];
            $snList = array_values(array_filter(array_map('trim', $snList)));
            $snList = array_unique($snList);

            if (count($snList) !== $needNum) {
                $err_msg = '物料【' . $bn . '】唯一码数量不正确，需扫描 ' . $needNum . ' 个';
                return false;
            }

            foreach ($snList as $sn) {
                $scanRes = $this->scanReturnSerial(
                    $return_id,
                    $sn,
                    $this->_buildScannedListExcept($serialItems, $bn, $sn),
                    $scanErr
                );
                if (!$scanRes) {
                    $err_msg = $scanErr ?: '唯一码校验失败';
                    return false;
                }
            }

            $result[$bn] = $snList;
        }

        return $result;
    }

    /**
     * 唯一码是否可退货入库：本发货单出库 SN、未退过、当前出库态
     */
    private function _isReturnableSn($snInfo, $return, $bn, &$err_msg = '')
    {
        $sn = $snInfo['sn'];

        $deliveryId = $this->_resolveDeliveryId($return, $bn, $resolveErr);
        if ($deliveryId <= 0) {
            $err_msg = $resolveErr ?: '退货单未关联发货单，无法校验唯一码';
            return false;
        }

        if (isset($this->_getOrderReturnedSerialSet($return['order_id'])[$sn])) {
            $err_msg = '该唯一码已在本订单退过，不能重复退货';
            return false;
        }

        $shipped = $this->_getDeliveryShippedSerialMap($deliveryId);
        if (!isset($shipped[$sn])) {
            $err_msg = '该唯一码不是本发货单出库的唯一码';
            return false;
        }
        if ($shipped[$sn] !== $bn) {
            $err_msg = '唯一码与退货明细物料不匹配';
            return false;
        }

        if (strval($snInfo['status']) !== '0') {
            $err_msg = '唯一码不是在出库状态，无法退货入库';
            return false;
        }

        return true;
    }

    /**
     * original_reship_bn → OME reship
     */
    private function _getReshipByReturn($return)
    {
        $reshipBn = $return['original_reship_bn'] ?? '';
        if ($reshipBn === '') {
            return null;
        }
        if (array_key_exists($reshipBn, $this->reshipCache)) {
            return $this->reshipCache[$reshipBn];
        }

        $reship = app::get('ome')->model('reship')->db_dump(
            ['reship_bn' => $reshipBn],
            'reship_id,order_id,delivery_id,reship_bn'
        );
        $this->reshipCache[$reshipBn] = $reship ?: null;

        return $this->reshipCache[$reshipBn];
    }

    /**
     * 解析发货单 ID：优先 reship.delivery_id，否则 order_item_id 反查
     */
    private function _resolveDeliveryId($return, $bn, &$err_msg = '')
    {
        $cacheKey = $return['return_id'] . ':' . $bn;
        if (isset($this->deliveryIdCache[$cacheKey])) {
            return $this->deliveryIdCache[$cacheKey];
        }

        $reship = $this->_getReshipByReturn($return);
        if (empty($reship)) {
            $err_msg = '原始退换货单不存在';
            $this->deliveryIdCache[$cacheKey] = 0;
            return 0;
        }

        if (intval($reship['delivery_id']) > 0) {
            $this->deliveryIdCache[$cacheKey] = intval($reship['delivery_id']);
            return $this->deliveryIdCache[$cacheKey];
        }

        $orderItemId = $this->_getReshipOrderItemId($reship['reship_id'], $bn);
        if ($orderItemId <= 0) {
            $err_msg = '退货单未关联发货单，无法校验唯一码';
            $this->deliveryIdCache[$cacheKey] = 0;
            return 0;
        }

        $row = kernel::database()->selectrow(
            'SELECT delivery_id FROM sdb_sales_delivery_order_item WHERE order_item_id='
            . intval($orderItemId)
        );
        if (empty($row['delivery_id'])) {
            $err_msg = '退货单未关联发货单，无法校验唯一码';
            $this->deliveryIdCache[$cacheKey] = 0;
            return 0;
        }

        $this->deliveryIdCache[$cacheKey] = intval($row['delivery_id']);
        return $this->deliveryIdCache[$cacheKey];
    }

    /**
     * 从 reship_items 取 order_item_id（优先匹配货号）
     */
    private function _getReshipOrderItemId($reshipId, $bn)
    {
        $items = app::get('ome')->model('reship_items')->getList(
            'order_item_id,bn',
            ['reship_id' => $reshipId, 'return_type' => ['return', 'refuse']],
            0,
            -1
        );
        if (empty($items)) {
            return 0;
        }

        if ($bn !== '') {
            foreach ($items as $item) {
                if ($item['bn'] === $bn && intval($item['order_item_id']) > 0) {
                    return intval($item['order_item_id']);
                }
            }
        }

        foreach ($items as $item) {
            if (intval($item['order_item_id']) > 0) {
                return intval($item['order_item_id']);
            }
        }

        return 0;
    }

    /**
     * 本发货单出库 SN 池：serial_number => bn
     */
    private function _getDeliveryShippedSerialMap($deliveryId)
    {
        $deliveryId = intval($deliveryId);
        if ($deliveryId <= 0) {
            return [];
        }
        if (isset($this->deliveryShippedCache[$deliveryId])) {
            return $this->deliveryShippedCache[$deliveryId];
        }

        $map = [];

        $serialRows = app::get('ome')->model('delivery_items_serial')->getList(
            'bn,serial_number',
            ['delivery_id' => $deliveryId, 'status' => '1'],
            0,
            -1
        );
        foreach ((array)$serialRows as $row) {
            if ($row['serial_number'] !== '') {
                $map[$row['serial_number']] = $row['bn'];
            }
        }

        $historyRows = app::get('ome')->model('product_serial_history')->getList(
            'bn,serial_number',
            ['bill_id' => $deliveryId, 'bill_type' => '1', 'act_type' => '1'],
            0,
            -1
        );
        foreach ((array)$historyRows as $row) {
            if ($row['serial_number'] !== '') {
                $map[$row['serial_number']] = $row['bn'];
            }
        }

        foreach ($this->_getMachineCenterDeliverySerials([$deliveryId]) as $row) {
            if (!empty($row['serial_number'])) {
                $map[$row['serial_number']] = $row['bn'];
            }
        }

        foreach ($this->_getMachineCenterMaterialSnByDeliveryId($deliveryId) as $row) {
            if (!empty($row['serial_number'])) {
                $map[$row['serial_number']] = $row['bn'];
            }
        }

        $this->deliveryShippedCache[$deliveryId] = $map;
        return $map;
    }

    /**
     * 退换货单退货入库 SN 池：serial_number => bn
     */
    private function _getReshipReturnedSerialMap($reshipId, $reshipBn = '')
    {
        $reshipId = intval($reshipId);
        if ($reshipId <= 0) {
            return [];
        }

        $cacheKey = $reshipId . ':' . trim((string) $reshipBn);
        if (isset($this->reshipReturnedCache[$cacheKey])) {
            return $this->reshipReturnedCache[$cacheKey];
        }

        $map = [];

        $serialRows = app::get('ome')->model('reship_items_serial')->getList(
            'bn,serial_number',
            ['reship_id' => $reshipId],
            0,
            -1
        );
        foreach ((array) $serialRows as $row) {
            $sn = trim((string) ($row['serial_number'] ?? ''));
            $bn = trim((string) ($row['bn'] ?? ''));
            if ($sn !== '') {
                $map[$sn] = $bn;
            }
        }

        foreach ($this->_getMachineCenterReshipSerials($reshipId, $reshipBn) as $row) {
            if (!empty($row['serial_number'])) {
                $map[$row['serial_number']] = $row['bn'];
            }
        }

        $this->reshipReturnedCache[$cacheKey] = $map;
        return $map;
    }

    /**
     * 本订单已成功退货的 SN 集合
     */
    private function _getOrderReturnedSerialSet($orderId)
    {
        $orderId = intval($orderId);
        if ($orderId <= 0) {
            return [];
        }
        if (isset($this->orderReturnedCache[$orderId])) {
            return $this->orderReturnedCache[$orderId];
        }

        $returned = [];
        $reshipIds = kernel::single('ome_order_reship')->getReshipIdsByOrdId($orderId, 'succ');
        if (!empty($reshipIds)) {
            $rows = app::get('ome')->model('reship_items_serial')->getList(
                'serial_number',
                ['reship_id|in' => $reshipIds],
                0,
                -1
            );
            foreach ((array)$rows as $row) {
                if ($row['serial_number'] !== '') {
                    $returned[$row['serial_number']] = true;
                }
            }

            foreach ($this->_getMachineCenterReshipSerialNumbers($reshipIds) as $sn) {
                $returned[$sn] = true;
            }
        }

        $this->orderReturnedCache[$orderId] = $returned;
        return $returned;
    }

    /**
     * machinecenter 发货出库流水 SN
     *
     * @param int[] $deliveryIds
     * @return array<int, array{bn:string, serial_number:string}>
     */
    private function _getMachineCenterDeliverySerials(array $deliveryIds)
    {
        if (empty($deliveryIds) || !app::get('machinecenter')->is_installed()) {
            return [];
        }

        $snFlowMdl = app::get('machinecenter')->model('material_sn_flow');
        $filter    = ['original_type' => 'delivery', 'io_status' => '0'];
        if (count($deliveryIds) === 1) {
            $filter['original_id'] = reset($deliveryIds);
        } else {
            $filter['original_id|in'] = $deliveryIds;
        }

        $rows = $snFlowMdl->getList('sn_id,material_bn', $filter, 0, -1);
        if (empty($rows)) {
            return [];
        }

        $snIds = array_values(array_unique(array_filter(array_column($rows, 'sn_id'))));
        if (empty($snIds)) {
            return [];
        }

        $snMdl    = app::get('machinecenter')->model('material_sn');
        $snFilter = count($snIds) === 1 ? ['id' => reset($snIds)] : ['id|in' => $snIds];
        $snRows   = $snMdl->getList('id,sn', $snFilter, 0, -1);
        $idToSn   = [];
        foreach ($snRows as $r) {
            $idToSn[$r['id']] = $r['sn'];
        }

        $out = [];
        foreach ($rows as $row) {
            $sn = isset($idToSn[$row['sn_id']]) ? $idToSn[$row['sn_id']] : '';
            if ($sn === '') {
                continue;
            }
            $out[] = ['bn' => $row['material_bn'], 'serial_number' => $sn];
        }

        return $out;
    }

    /**
     * machinecenter material_sn：original_type=delivery + original_bn=发货单号
     *
     * @param int $deliveryId
     * @return array<int, array{bn:string, serial_number:string}>
     */
    private function _getMachineCenterMaterialSnByDeliveryId($deliveryId)
    {
        $deliveryId = intval($deliveryId);
        if ($deliveryId <= 0 || !app::get('machinecenter')->is_installed()) {
            return [];
        }

        $delivery = app::get('ome')->model('delivery')->db_dump(
            ['delivery_id' => $deliveryId],
            'delivery_bn'
        );
        $deliveryBn = trim((string) ($delivery['delivery_bn'] ?? ''));
        if ($deliveryBn === '') {
            return [];
        }

        $rows = app::get('machinecenter')->model('material_sn')->getList(
            'sn,material_bn',
            ['original_type' => 'delivery', 'original_bn' => $deliveryBn],
            0,
            -1
        );

        $out = [];
        foreach ((array) $rows as $row) {
            $sn = trim((string) ($row['sn'] ?? ''));
            $bn = trim((string) ($row['material_bn'] ?? ''));
            if ($sn === '') {
                continue;
            }
            $out[] = ['bn' => $bn, 'serial_number' => $sn];
        }

        return $out;
    }

    /**
     * machinecenter 退货入库 SN（流水 + material_sn）
     *
     * @param int    $reshipId
     * @param string $reshipBn
     * @return array<int, array{bn:string, serial_number:string}>
     */
    private function _getMachineCenterReshipSerials($reshipId, $reshipBn = '')
    {
        $reshipId  = intval($reshipId);
        $reshipBn  = trim((string) $reshipBn);
        if ($reshipId <= 0 || !app::get('machinecenter')->is_installed()) {
            return [];
        }

        $out = [];

        $snFlowMdl = app::get('machinecenter')->model('material_sn_flow');
        $filter    = ['original_type' => 'reship'];
        $filter['original_id'] = $reshipId;
        $rows = $snFlowMdl->getList('sn_id,material_bn', $filter, 0, -1);
        if (!empty($rows)) {
            $snIds = array_values(array_unique(array_filter(array_column($rows, 'sn_id'))));
            if (!empty($snIds)) {
                $snMdl    = app::get('machinecenter')->model('material_sn');
                $snFilter = count($snIds) === 1 ? ['id' => reset($snIds)] : ['id|in' => $snIds];
                $snRows   = $snMdl->getList('id,sn', $snFilter, 0, -1);
                $idToSn   = [];
                foreach ($snRows as $r) {
                    $idToSn[$r['id']] = $r['sn'];
                }
                foreach ($rows as $row) {
                    $sn = isset($idToSn[$row['sn_id']]) ? trim((string) $idToSn[$row['sn_id']]) : '';
                    if ($sn === '') {
                        continue;
                    }
                    $out[] = [
                        'bn'            => trim((string) ($row['material_bn'] ?? '')),
                        'serial_number' => $sn,
                    ];
                }
            }
        }

        if ($reshipBn !== '') {
            $snRows = app::get('machinecenter')->model('material_sn')->getList(
                'sn,material_bn',
                ['original_type' => 'reship', 'original_bn' => $reshipBn],
                0,
                -1
            );
            foreach ((array) $snRows as $row) {
                $sn = trim((string) ($row['sn'] ?? ''));
                if ($sn === '') {
                    continue;
                }
                $out[] = [
                    'bn'            => trim((string) ($row['material_bn'] ?? '')),
                    'serial_number' => $sn,
                ];
            }
        }

        return $out;
    }

    /**
     * machinecenter 已成功退货入库流水 SN
     *
     * @param int[] $reshipIds
     * @return string[]
     */
    private function _getMachineCenterReshipSerialNumbers(array $reshipIds)
    {
        if (empty($reshipIds) || !app::get('machinecenter')->is_installed()) {
            return [];
        }

        $snFlowMdl = app::get('machinecenter')->model('material_sn_flow');
        $filter    = ['original_type' => 'reship'];
        if (count($reshipIds) === 1) {
            $filter['original_id'] = reset($reshipIds);
        } else {
            $filter['original_id|in'] = $reshipIds;
        }

        $rows = $snFlowMdl->getList('sn_id', $filter, 0, -1);
        if (empty($rows)) {
            return [];
        }

        $snIds = array_values(array_unique(array_filter(array_column($rows, 'sn_id'))));
        if (empty($snIds)) {
            return [];
        }

        $snMdl    = app::get('machinecenter')->model('material_sn');
        $snFilter = count($snIds) === 1 ? ['id' => reset($snIds)] : ['id|in' => $snIds];
        $snRows   = $snMdl->getList('id,sn', $snFilter, 0, -1);

        $sns = [];
        foreach ($snRows as $r) {
            if ($r['sn'] !== '' && $r['sn'] !== null) {
                $sns[] = $r['sn'];
            }
        }

        return $sns;
    }

    private function _buildScannedListExcept($serialItems, $excludeBn, $excludeSn)
    {
        $list = [];
        foreach ((array)$serialItems as $bn => $sns) {
            foreach ((array)$sns as $sn) {
                $sn = trim($sn);
                if ($sn === '') {
                    continue;
                }
                if ($bn === $excludeBn && $sn === $excludeSn) {
                    continue;
                }
                $list[] = ['bn' => $bn, 'sn' => $sn];
            }
        }
        return $list;
    }

    private function _getReturn($return_id, &$err_msg = '')
    {
        $return_id = intval($return_id);
        if ($return_id <= 0) {
            $err_msg = '无效操作';
            return false;
        }

        $filter = ['return_id' => $return_id, 'return_type' => 'return'];
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = kernel::single('o2o_store_branch')->getO2OBranchByUser(true);
            if (empty($branch_ids)) {
                $err_msg = '操作员没有管辖的仓库';
                return false;
            }
            $filter['branch_id'] = $branch_ids;
        }

        $return = app::get('wap')->model('return')->dump($filter, '*');
        if (empty($return)) {
            $err_msg = '没有此退货单或没仓库权限';
            return false;
        }
        if (intval($return['status']) !== 1) {
            $err_msg = '该退货单状态不正确，不能操作';
            return false;
        }

        return $return;
    }
}
