<?php
/**
 * WAP 发货唯一码校验
 */
class wap_delivery_serial
{
    const MODE_CONSIGN = 'consign';
    const MODE_PICKUP_VERIFY = 'pickup_verify';
    const MODE_PREPARE_STOCK = 'prepare_stock';

    const SYNC_STATUS_PENDING = 0;
    const SYNC_STATUS_SYNCED = 1;

    const WMS_STATUS_PACK = '30';

    /**
     * 获取发货单唯一码明细
     */
    public function getDeliverySerialInfo($delivery_id, &$err_msg = '', $mode = self::MODE_CONSIGN)
    {
        $delivery = $this->_getDelivery($delivery_id, $err_msg, $mode);
        if (!$delivery) {
            return false;
        }

        $items = kernel::single('wap_delivery')->getDeliveryItemList($delivery['outer_delivery_bn']);
        if (empty($items)) {
            $err_msg = '发货单明细不存在';
            return false;
        }

        $serialLib = kernel::single('material_serial');
        $serialItems = [];
        $hasSerial = false;

        foreach ($items as $item) {
            $needSerial = $serialLib->checkSerialById($item['product_id']);
            if (!$needSerial) {
                continue;
            }
            $hasSerial = true;
            $serialItems[] = [
                'item_id'          => $item['item_id'],
                'product_id'       => $item['product_id'],
                'bn'               => $item['bn'],
                'product_name'     => $item['product_name'],
                'number'           => intval($item['number']),
                'default_img_url'  => $item['default_img_url'],
                'product_attr'     => $item['product_attr'],
                'unit'             => $item['unit'],
                'scanned_count'    => 0,
            ];
        }

        return [
            'has_serial'   => $hasSerial,
            'delivery_id'  => $delivery['delivery_id'],
            'delivery_bn'  => $delivery['delivery_bn'],
            'order_bn'     => $delivery['order_bn'],
            'serial_items' => $serialItems,
        ];
    }

    /**
     * 备货：获取唯一码明细及已扫记录（WAP 本地）
     */
    public function getPrepareStockInfo($delivery_id, &$err_msg = '')
    {
        $info = $this->getDeliverySerialInfo($delivery_id, $err_msg, self::MODE_PREPARE_STOCK);
        if ($info === false) {
            return false;
        }

        $saved = $this->loadPrepareSerialRows($delivery_id);
        $info['scanned_list'] = $saved['list'];
        $info['op_no'] = $saved['op_no'];

        $countMap = [];
        foreach ($saved['list'] as $row) {
            $bn = trim($row['bn'] ?? '');
            if ($bn === '') {
                continue;
            }
            $countMap[$bn] = ($countMap[$bn] ?? 0) + 1;
        }
        foreach ($info['serial_items'] as $key => $item) {
            $info['serial_items'][$key]['scanned_count'] = $countMap[$item['bn']] ?? 0;
        }

        return $info;
    }

    /**
     * 确认发货：获取备货唯一码及发货所需信息
     */
    public function getConsignPrepareInfo($delivery_id, &$err_msg = '')
    {
        $info = $this->getPrepareStockInfo($delivery_id, $err_msg);
        if ($info === false) {
            return false;
        }

        $info['is_prepare_complete'] = $this->isPrepareSerialComplete($info);
        $info['serial_items_payload'] = $this->buildSerialItemsPayloadFromPrepare($delivery_id);

        return $info;
    }

    /**
     * 备货唯一码是否已扫满
     */
    public function isPrepareSerialComplete(array $info)
    {
        if (empty($info['has_serial']) || empty($info['scanned_list'])) {
            return false;
        }
        foreach ((array) $info['serial_items'] as $item) {
            if (intval($item['scanned_count'] ?? 0) < intval($item['number'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 将备货本地唯一码转为发货 serial_items 参数
     */
    public function buildSerialItemsPayloadFromPrepare($delivery_id)
    {
        $saved = $this->loadPrepareSerialRows($delivery_id);
        $payload = [];
        foreach ($saved['list'] as $row) {
            $bn = trim($row['bn'] ?? '');
            $sn = trim($row['sn'] ?? '');
            if ($bn === '' || $sn === '') {
                continue;
            }
            if (!isset($payload[$bn])) {
                $payload[$bn] = [];
            }
            $payload[$bn][] = $sn;
        }

        return $payload;
    }

    /**
     * 备货：扫描并保存唯一码到 WAP 本地
     */
    public function scanPrepareSerial($delivery_id, $sn, $op_no, &$err_msg = '')
    {
        $sn = trim($sn);
        $op_no = trim($op_no);
        if ($sn === '') {
            $err_msg = '请输入唯一码';
            return false;
        }
        if ($op_no === '') {
            $err_msg = '请先录入工号';
            return false;
        }

        $delivery = $this->_getDelivery($delivery_id, $err_msg, self::MODE_PREPARE_STOCK);
        if (!$delivery) {
            return false;
        }

        $validate = $this->_validateSerialScan($delivery, $sn, [], $err_msg, self::MODE_PREPARE_STOCK);
        if ($validate === false) {
            return false;
        }

        $savedRows = $this->loadPrepareSerialRows($delivery_id);
        foreach ($savedRows['list'] as $row) {
            if (trim($row['sn'] ?? '') === $sn) {
                $err_msg = '该唯一码已扫描，请勿重复扫描';
                return false;
            }
        }

        $serialMdl = app::get('wap')->model('delivery_items_serial');
        $saveData = [
            'delivery_id'   => $delivery['delivery_id'],
            'product_id'    => $validate['product_id'],
            'bn'            => $validate['bn'],
            'product_name'  => $validate['product_name'],
            'serial_id'     => $validate['serial_id'],
            'serial_number' => $sn,
            'op_no'         => $op_no,
            'status'        => 0,
            'sync_status'   => self::SYNC_STATUS_PENDING,
            'create_time'   => time(),
        ];
        if (!$serialMdl->save($saveData)) {
            $err_msg = '唯一码保存失败，请重试';
            return false;
        }

        return [
            'item_serial_id' => $saveData['item_serial_id'],
            'sn'             => $sn,
            'bn'             => $validate['bn'],
            'product_name'   => $validate['product_name'],
            'op_no'          => $op_no,
        ];
    }

    /**
     * 备货：删除已扫唯一码
     */
    public function removePrepareSerial($delivery_id, $item_serial_id, &$err_msg = '')
    {
        $delivery_id = intval($delivery_id);
        $item_serial_id = intval($item_serial_id);
        if ($delivery_id <= 0 || $item_serial_id <= 0) {
            $err_msg = '无效操作';
            return false;
        }

        $delivery = $this->_getDelivery($delivery_id, $err_msg, self::MODE_PREPARE_STOCK);
        if (!$delivery) {
            return false;
        }

        $serialMdl = app::get('wap')->model('delivery_items_serial');
        $row = $serialMdl->dump([
            'item_serial_id' => $item_serial_id,
            'delivery_id'    => $delivery_id,
            'sync_status'    => self::SYNC_STATUS_PENDING,
        ], 'item_serial_id,serial_number');
        if (empty($row)) {
            $err_msg = '扫描记录不存在';
            return false;
        }

        if (!$serialMdl->delete(['item_serial_id' => $item_serial_id])) {
            $err_msg = '删除失败';
            return false;
        }

        return ['serial_number' => $row['serial_number']];
    }

    /**
     * 备货完成：校验唯一码并更新 WAP 发货单（已接单、已打印、已备货，不回写 OMS）
     */
    public function finishPrepareStock($delivery_id, &$err_msg = '')
    {
        $delivery_id = intval($delivery_id);
        $delivery = $this->_getDelivery($delivery_id, $err_msg, self::MODE_PREPARE_STOCK);
        if (!$delivery) {
            return false;
        }

        if (intval($delivery['print_status']) === 1) {
            $err_msg = '该发货单已备货';
            return false;
        }

        $info = $this->getPrepareStockInfo($delivery_id, $err_msg);
        if ($info === false) {
            return false;
        }
        if (empty($info['has_serial'])) {
            $err_msg = '该发货单无需备货扫描';
            return false;
        }

        foreach ($info['serial_items'] as $item) {
            $needNum = intval($item['number']);
            $scanned = intval($item['scanned_count'] ?? 0);
            if ($scanned < $needNum) {
                $err_msg = '物料【' . $item['bn'] . '】唯一码未扫满，需扫描 ' . $needNum . ' 个';
                return false;
            }
        }

        $updateData = [
            'print_status'  => 1,
            'wms_status'    => self::WMS_STATUS_PACK,
            'last_modified' => time(),
        ];
        if (intval($delivery['confirm']) === 3) {
            $updateData['confirm'] = 1;
        }

        $wapDeliveryObj = app::get('wap')->model('delivery');
        $result = $wapDeliveryObj->update(
            $updateData,
            [
                'delivery_id'  => $delivery_id,
                'status'       => 0,
                'print_status' => 0,
            ]
        );

        if (is_bool($result) && $result === false) {
            $err_msg = '备货状态更新失败，请重试';
            return false;
        }

        return [
            'delivery_id'  => $delivery_id,
            'confirm'      => intval($delivery['confirm']) === 3 ? 1 : intval($delivery['confirm']),
            'print_status' => 1,
            'wms_status'   => self::WMS_STATUS_PACK,
        ];
    }

    /**
     * 校验唯一码是否已被 OMS 或其他未回写 WAP 单据占用
     */
    public function assertSerialAvailable($sn, $delivery_id, &$err_msg = '')
    {
        $sn = trim($sn);
        $delivery_id = intval($delivery_id);
        if ($sn === '') {
            $err_msg = '唯一码不能为空';
            return false;
        }

        $omeRow = app::get('ome')->model('delivery_items_serial')->db_dump(['serial_number' => $sn], 'item_serial_id');
        if (!empty($omeRow)) {
            $err_msg = '唯一码已被其他发货单使用';
            return false;
        }

        $wapSerialMdl = app::get('wap')->model('delivery_items_serial');
        $usedRows = $wapSerialMdl->getList(
            'item_serial_id,delivery_id',
            [
                'serial_number'     => $sn,
                'sync_status'       => self::SYNC_STATUS_PENDING,
                'delivery_id|noequal' => $delivery_id,
            ],
            0,
            1
        );
        if (!empty($usedRows[0]['delivery_id'])) {
            $usedDelivery = app::get('wap')->model('delivery')->dump(
                ['delivery_id' => $usedRows[0]['delivery_id'], 'status|noequal' => 1],
                'delivery_bn,order_bn'
            );
            if (!empty($usedDelivery)) {
                $billBn = trim($usedDelivery['order_bn']) ?: trim($usedDelivery['delivery_bn']);
                $err_msg = $billBn !== ''
                    ? '唯一码已被订单【' . $billBn . '】备货占用，尚未回写OMS'
                    : '唯一码已被其他备货单占用，尚未回写OMS';
                return false;
            }
        }

        return true;
    }

    /**
     * 读取 WAP 本地备货扫描记录
     */
    public function loadPrepareSerialRows($delivery_id)
    {
        $result = ['list' => [], 'op_no' => ''];
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            return $result;
        }

        $rows = app::get('wap')->model('delivery_items_serial')->getList(
            'item_serial_id,bn,product_name,serial_number,op_no,create_time',
            [
                'delivery_id' => $delivery_id,
                'sync_status' => self::SYNC_STATUS_PENDING,
            ],
            0,
            -1,
            'item_serial_id ASC'
        );

        foreach ((array) $rows as $row) {
            if ($result['op_no'] === '' && trim($row['op_no']) !== '') {
                $result['op_no'] = trim($row['op_no']);
            }
            $result['list'][] = [
                'item_serial_id' => intval($row['item_serial_id']),
                'sn'             => trim($row['serial_number']),
                'bn'             => trim($row['bn']),
                'product_name'   => trim($row['product_name']),
                'op_no'          => trim($row['op_no']),
            ];
        }

        return $result;
    }

    /**
     * 取消发货单时释放备货唯一码占用（删除 WAP 本地未回写记录，并释放 SN 预占）
     *
     * @param int    $delivery_id
     * @param string $outer_delivery_bn
     * @return int 删除条数
     */
    public function releasePrepareSerialByDelivery($delivery_id = 0, $outer_delivery_bn = '')
    {
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            $outer_delivery_bn = trim((string) $outer_delivery_bn);
            if ($outer_delivery_bn === '') {
                return 0;
            }
            $deliveryRow = app::get('wap')->model('delivery')->dump(
                array('outer_delivery_bn' => $outer_delivery_bn),
                'delivery_id'
            );
            $delivery_id = intval($deliveryRow['delivery_id'] ?? 0);
        }
        if ($delivery_id <= 0) {
            return 0;
        }

        if (app::get('machinecenter')->is_installed()) {
            kernel::single('machinecenter_sn_hold')->releaseByBill('delivery', $delivery_id);
        }

        $serialMdl = app::get('wap')->model('delivery_items_serial');
        $rows      = $serialMdl->getList(
            'item_serial_id',
            array(
                'delivery_id' => $delivery_id,
                'sync_status' => self::SYNC_STATUS_PENDING,
            ),
            0,
            -1
        );
        if (empty($rows)) {
            return 0;
        }

        $deleted = 0;
        foreach ($rows as $row) {
            $itemSerialId = intval($row['item_serial_id'] ?? 0);
            if ($itemSerialId <= 0) {
                continue;
            }
            if ($serialMdl->delete(array('item_serial_id' => $itemSerialId))) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * 扫描唯一码校验
     */
    public function scanSerial($delivery_id, $sn, $scannedList = [], &$err_msg = '', $mode = self::MODE_CONSIGN)
    {
        $sn = trim($sn);
        if ($sn === '') {
            $err_msg = '请输入唯一码';
            return false;
        }

        $delivery = $this->_getDelivery($delivery_id, $err_msg, $mode);
        if (!$delivery) {
            return false;
        }

        $validate = $this->_validateSerialScan($delivery, $sn, $scannedList, $err_msg, $mode);
        if ($validate === false) {
            return false;
        }

        $holder = [
            'type'        => 'delivery',
            'bill_id'     => $delivery['delivery_id'],
            'bill_bn'     => $delivery['delivery_bn'],
            'branch_id'   => $delivery['branch_id'],
            'operator_id' => kernel::single('desktop_user')->get_id(),
        ];
        $holdLib = kernel::single('machinecenter_sn_hold');
        if ($holdLib->isHeldByOther($sn, $holder)) {
            $err_msg = '唯一码已被其他单据占用，请稍后再试';
            return false;
        }
        if (!$holdLib->acquire($sn, $holder, $holdErr)) {
            $err_msg = $holdErr ?: '唯一码预占失败，请稍后再试';
            return false;
        }

        return [
            'sn'           => $sn,
            'bn'           => $validate['bn'],
            'product_name' => $validate['product_name'],
            'material_bn'  => $validate['bn'],
        ];
    }

    /**
     * 扫描唯一码通用校验（不含预占/落库）
     */
    private function _validateSerialScan(array $delivery, $sn, $scannedList = [], &$err_msg = '', $mode = self::MODE_CONSIGN)
    {
        $sn = trim($sn);
        if ($sn === '') {
            $err_msg = '请输入唯一码';
            return false;
        }

        if (!app::get('machinecenter')->is_installed()) {
            $err_msg = '机器中心未安装，无法校验唯一码';
            return false;
        }

        $info = $this->getDeliverySerialInfo($delivery['delivery_id'], $err_msg, $mode);
        if (!$info || !$info['has_serial']) {
            $err_msg = $err_msg ?: '该发货单无需扫描唯一码';
            return false;
        }

        $serialBnMap = [];
        $requiredMap = [];
        foreach ($info['serial_items'] as $row) {
            $serialBnMap[$row['bn']] = $row;
            $requiredMap[$row['bn']] = $row['number'];
        }

        $scannedCount = [];
        if ($mode === self::MODE_PREPARE_STOCK) {
            $savedRows = $this->loadPrepareSerialRows($delivery['delivery_id']);
            foreach ($savedRows['list'] as $row) {
                $scanBn = trim($row['bn'] ?? '');
                if ($scanBn !== '' && isset($requiredMap[$scanBn])) {
                    $scannedCount[$scanBn] = ($scannedCount[$scanBn] ?? 0) + 1;
                }
            }
        } else {
            foreach ((array) $scannedList as $row) {
                $scanSn = trim($row['sn'] ?? '');
                $scanBn = trim($row['bn'] ?? '');
                if ($scanSn === '' || $scanBn === '') {
                    continue;
                }
                if (!isset($requiredMap[$scanBn])) {
                    $err_msg = '已扫描唯一码数据异常';
                    return false;
                }
                $scannedCount[$scanBn] = ($scannedCount[$scanBn] ?? 0) + 1;
            }

            foreach ($scannedList as $row) {
                if (trim($row['sn'] ?? '') === $sn) {
                    $err_msg = '该唯一码已扫描，请勿重复扫描';
                    return false;
                }
            }
        }

        if (!$this->assertSerialAvailable($sn, $delivery['delivery_id'], $err_msg)) {
            return false;
        }

        $snInfo = app::get('machinecenter')->model('material_sn')->db_dump(['sn' => $sn]);
        if (!$snInfo) {
            $err_msg = '唯一码不存在';
            return false;
        }
        if (intval($snInfo['branch_id']) !== intval($delivery['branch_id'])) {
            $err_msg = '唯一码不属于当前门店仓库';
            return false;
        }
        if (strval($snInfo['status']) !== '1') {
            $err_msg = '唯一码不是在库状态，无法发货';
            return false;
        }
        if (!isset($serialBnMap[$snInfo['material_bn']])) {
            $matchedByBm = false;
            if (!empty($snInfo['bm_id'])) {
                foreach ($info['serial_items'] as $itemRow) {
                    if (intval($itemRow['product_id']) === intval($snInfo['bm_id'])) {
                        $matchedByBm = true;
                        $snInfo['material_bn'] = $itemRow['bn'];
                        break;
                    }
                }
            }
            if (!$matchedByBm) {
                $err_msg = '唯一码与发货单明细物料不匹配';
                return false;
            }
        }

        $bn = $snInfo['material_bn'];
        if (($scannedCount[$bn] ?? 0) >= $requiredMap[$bn]) {
            $err_msg = '该物料唯一码已扫满';
            return false;
        }

        return [
            'bn'           => $bn,
            'product_id'   => intval($serialBnMap[$bn]['product_id']),
            'product_name' => $serialBnMap[$bn]['product_name'],
            'serial_id'    => intval($snInfo['id'] ?? 0),
        ];
    }

    /**
     * 发货前校验唯一码数量并格式化 items 参数
     */
    public function validateSerialItemsForConsign($delivery_id, $serialItems, &$err_msg = '')
    {
        return $this->_validateSerialItems($delivery_id, $serialItems, $err_msg, self::MODE_CONSIGN);
    }

    /**
     * 自提核销前校验唯一码
     */
    public function validateSerialItemsForPickupVerify($delivery_id, $serialItems, &$err_msg = '')
    {
        return $this->_validateSerialItems($delivery_id, $serialItems, $err_msg, self::MODE_PICKUP_VERIFY);
    }

    private function _validateSerialItems($delivery_id, $serialItems, &$err_msg, $mode)
    {
        $info = $this->getDeliverySerialInfo($delivery_id, $err_msg, $mode);
        if (!$info) {
            return false;
        }
        if (!$info['has_serial']) {
            return [];
        }

        if (!is_array($serialItems) || empty($serialItems)) {
            $err_msg = '请先扫描全部唯一码';
            return false;
        }

        $requiredMap = [];
        foreach ($info['serial_items'] as $row) {
            $requiredMap[$row['bn']] = $row['number'];
        }

        $items = [];
        foreach ($requiredMap as $bn => $needNum) {
            $snList = isset($serialItems[$bn]) ? (array)$serialItems[$bn] : [];
            $snList = array_values(array_filter(array_map('trim', $snList)));
            $snList = array_unique($snList);

            if (count($snList) !== intval($needNum)) {
                $err_msg = '物料【' . $bn . '】唯一码数量不正确，需扫描 ' . $needNum . ' 个';
                return false;
            }

            foreach ($snList as $sn) {
                $scanRes = $this->scanSerial(
                    $delivery_id,
                    $sn,
                    $this->_buildScannedListExcept($serialItems, $bn, $sn),
                    $scanErr,
                    $mode
                );
                if (!$scanRes) {
                    $err_msg = $scanErr ?: '唯一码校验失败';
                    return false;
                }
            }

            $items[] = [
                'bn'      => $bn,
                'sn_list' => $snList,
            ];
        }

        return $items;
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

    private function _getDelivery($delivery_id, &$err_msg = '', $mode = self::MODE_CONSIGN)
    {
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            $err_msg = '无效操作';
            return false;
        }

        $filter = ['delivery_id' => $delivery_id];
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = kernel::single('o2o_store_branch')->getO2OBranchByUser(true);
            if (empty($branch_ids)) {
                $err_msg = '操作员没有管辖的仓库';
                return false;
            }
            $filter['branch_id'] = $branch_ids;
        }

        $delivery = app::get('wap')->model('delivery')->dump($filter, '*');
        if (empty($delivery)) {
            $err_msg = '没有此发货单或没仓库权限,请检查';
            return false;
        }

        if ($mode === self::MODE_PICKUP_VERIFY) {
            if (intval($delivery['confirm']) === 2) {
                $err_msg = '该发货单已被拒绝,不能进行操作';
                return false;
            }
            if (intval($delivery['is_received']) === 2) {
                $err_msg = '订单已提货';
                return false;
            }
            if (intval($delivery['status']) === 1) {
                $err_msg = '订单已取消';
                return false;
            }
            if (!in_array(intval($delivery['confirm']), array(1, 3), true)) {
                $err_msg = '该发货单状态不正确,不能进行操作';
                return false;
            }
            if (!in_array(intval($delivery['status']), array(0, 3), true)) {
                $err_msg = '该发货单状态不正确,不能进行操作';
                return false;
            }
            return $delivery;
        }

        if ($mode === self::MODE_PREPARE_STOCK) {
            if (intval($delivery['status']) === 1) {
                $err_msg = '订单已取消';
                return false;
            }
            if (intval($delivery['status']) !== 0) {
                $err_msg = '该发货单状态不正确,不能进行备货';
                return false;
            }
            if (intval($delivery['process_status']) !== 0) {
                $err_msg = '该发货单状态不正确,不能进行备货';
                return false;
            }
            return $delivery;
        }

        if (intval($delivery['confirm']) !== 1) {
            $err_msg = intval($delivery['confirm']) === 2 ? '该发货单已被拒绝,不能进行操作' : '该发货单还未确认,不能进行操作';
            return false;
        }
        if (intval($delivery['status']) !== 0) {
            $err_msg = '该发货单状态不正确,不能进行操作';
            return false;
        }

        return $delivery;
    }
}
