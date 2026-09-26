<?php
/**
 * 补寄相关：包裹中心「补发建议单」等入口的数据准备与落库（return_reshipping / return_reshipping_items / return_reshipping_items_detail）
 * 主表字段组织参照 {@see erpapi_shop_response_process_reshipping::_formatReshippingData}（矩阵补寄同步）
 */
class ome_return_reshipping
{
    /**
     * 包裹中心「补发建议单」主单允许的 orders.pay_status（已支付、部分退款）
     *
     * @return string[]
     */
    public function allowedOrderPayStatusesForDeliveryBillSuggest()
    {
        return array('1', '4');
    }

    /**
     * 主单 pay_status 是否允许展示/创建补发建议单
     *
     * @param string|int $payStatus sdb_ome_orders.pay_status
     * @return bool
     */
    public function isAllowedOrderPayStatusForDeliveryBillSuggest($payStatus)
    {
        return in_array((string) $payStatus, $this->allowedOrderPayStatusesForDeliveryBillSuggest(), true);
    }

    /**
     * 包裹中心「补发建议单」入口：拦截状态为 intercept_succ（与 status 追回状态无关）
     *
     * @param array $bill
     * @return bool
     */
    public function isDeliveryBillInterceptSuccessForSuggest(array $bill)
    {
        return (string) ($bill['intercept_status'] ?? '') === 'intercept_succ';
    }

    /**
     * 包裹中心「补发建议单」不允许时的统一提示
     *
     * @return string
     */
    public function deliveryBillSuggestConditionErrorMsg()
    {
        return '仅当包裹拦截状态为「拦截成功」且关联订单支付状态为「已支付」或「部分退款」时可创建补发建议单';
    }

    /**
     * 包裹中心是否已生成补发建议单（读 bill 字段，兼容历史 status=8）
     *
     * @param array $bill
     * @return bool
     */
    public function isDeliveryBillSuggestReshippingCreated(array $bill)
    {
        if (!empty($bill['suggest_reshipping_id']) && (int) $bill['suggest_reshipping_id'] > 0) {
            return true;
        }
        // 历史数据：生成时尚未回写 suggest_reshipping_id，仅 status=8
        return (string) ($bill['status'] ?? '') === '8';
    }

    /**
     * 从 delivery_bill 行解析已关联的补发建议单（仅读 bill 字段，不查库）
     *
     * @param array $bill
     * @return array|null reshipping_id、reshipping_bn
     */
    public function getDeliveryBillLinkedSuggestReshipping(array $bill)
    {
        if (!empty($bill['suggest_reshipping_id']) && (int) $bill['suggest_reshipping_id'] > 0) {
            return array(
                'reshipping_id' => (int) $bill['suggest_reshipping_id'],
                'reshipping_bn' => isset($bill['suggest_reshipping_bn']) ? (string) $bill['suggest_reshipping_bn'] : '',
            );
        }
        return null;
    }

    /**
     * 包裹中心是否已生成补发建议单（sdb_ome_return_reshipping）
     *
     * @param int        $log_id
     * @param array|null $bill 可选，含 logi_no
     * @return array|null 已存在则返回 reshipping_id、reshipping_bn
     */
    public function findExistingDeliveryBillSuggestReshipping($log_id, $bill = null)
    {
        $log_id = (int) $log_id;
        if ($log_id <= 0) {
            return null;
        }

        if (is_array($bill)) {
            $linked = $this->getDeliveryBillLinkedSuggestReshipping($bill);
            if ($linked) {
                return $linked;
            }
        }

        $reshippingModel = app::get('ome')->model('return_reshipping');
        $filter = array(
            'source'      => 'local',
            'sub_source'  => 'delivery_bill',
            'original_id' => $log_id,
        );

        $original_bn = '';
        if (is_array($bill) && isset($bill['logi_no'])) {
            $original_bn = trim((string) $bill['logi_no']);
        }
        if ($original_bn !== '') {
            $row = $reshippingModel->db_dump(
                array_merge($filter, array('original_bn' => $original_bn)),
                'reshipping_id,reshipping_bn'
            );
            if ($row) {
                return $row;
            }
        }

        $row = $reshippingModel->db_dump($filter, 'reshipping_id,reshipping_bn');
        if ($row) {
            return $row;
        }

        $reshipping_bn = $this->buildDeliveryBillSuggestReshippingBn($log_id);
        return $reshippingModel->db_dump(
            array('reshipping_bn' => $reshipping_bn),
            'reshipping_id,reshipping_bn'
        );
    }

    /**
     * @param int $log_id
     * @return string
     */
    public function buildDeliveryBillSuggestReshippingBn($log_id)
    {
        $reshipping_bn = 'SYSBJ' . (int) $log_id;
        if (strlen($reshipping_bn) > 50) {
            $reshipping_bn = substr($reshipping_bn, 0, 50);
        }
        return $reshipping_bn;
    }

    /**
     * @param int        $log_id
     * @param array|null $bill
     * @return bool
     */
    public function hasExistingDeliveryBillSuggestReshipping($log_id, $bill = null)
    {
        return !empty($this->findExistingDeliveryBillSuggestReshipping($log_id, $bill));
    }

    /**
     * 已生成补发建议单时的提示文案
     *
     * @param array $existing findExistingDeliveryBillSuggestReshipping 返回值
     * @return string
     */
    public function deliveryBillSuggestDuplicateErrorMsg(array $existing)
    {
        $bnHint = !empty($existing['reshipping_bn'])
            ? $existing['reshipping_bn']
            : ('ID:' . $existing['reshipping_id']);
        return '该包裹已生成过补发建议单（' . $bnHint . '），请勿重复创建';
    }

    /**
     * 包裹中心不可创建/展示补发建议单的原因；null 表示允许
     *
     * @param array $bill
     * @return string|null
     */
    public function getDeliveryBillSuggestBlockReason(array $bill)
    {
        if (empty($bill['log_id'])) {
            return '包裹记录无效';
        }

        if ($this->isDeliveryBillSuggestReshippingCreated($bill)) {
            $existing = $this->getDeliveryBillLinkedSuggestReshipping($bill);
            if (!$existing) {
                $existing = $this->findExistingDeliveryBillSuggestReshipping($bill['log_id'], $bill);
            }
            if ($existing) {
                return $this->deliveryBillSuggestDuplicateErrorMsg($existing);
            }
            return '该包裹已生成过补发建议单，请勿重复创建';
        }

        if (!$this->isDeliveryBillInterceptSuccessForSuggest($bill)) {
            return $this->deliveryBillSuggestConditionErrorMsg();
        }

        if (!$this->_deliveryBillLinkedOrdersPayStatusAllowedFromDb($bill, $this->allowedOrderPayStatusesForDeliveryBillSuggest())) {
            return $this->deliveryBillSuggestConditionErrorMsg();
        }

        return null;
    }

    /**
     * 包裹中心「补发建议单」是否允许：intercept_status=intercept_succ 且关联订单 pay_status 均为允许值（1 已支付 / 4 部分退款）
     * 不依赖 delivery_bill.status（追回中/追回成功等）；pay_status 须根据 bill.order_id 查 sdb_ome_orders。
     * 已生成补发建议单时不允许。
     *
     * @param array $bill sdb_ome_delivery_bill 一行（可先经 {@see supplementOrderIdsForDeliveryBillSuggest} 补全 order_id）
     * @return bool
     */
    public function validateDeliveryBillSuggestConditions(array $bill)
    {
        return $this->getDeliveryBillSuggestBlockReason($bill) === null;
    }

    /**
     * 按 delivery_bill 解析出的 order_id，逐单读 orders.pay_status（实时库表，非 finder 行上的虚拟字段）
     *
     * @param array    $bill
     * @param string[] $allowedPayStatuses 允许的 sdb_ome_orders.pay_status
     * @return bool
     */
    private function _deliveryBillLinkedOrdersPayStatusAllowedFromDb(array $bill, array $allowedPayStatuses)
    {
        $allowed = array();
        foreach ($allowedPayStatuses as $ps) {
            $allowed[(string) $ps] = true;
        }
        if (empty($allowed)) {
            return false;
        }

        $orderIds = $this->_collectOrderIdsForDeliveryBill($bill);
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));
        $orderIds = array_filter($orderIds, function ($id) {
            return $id > 0;
        });
        if (empty($orderIds)) {
            return false;
        }

        $orderModel = app::get('ome')->model('orders');
        foreach ($orderIds as $orderId) {
            $row = $orderModel->db_dump(array('order_id' => $orderId), 'pay_status');
            if (empty($row) || !isset($allowed[(string) $row['pay_status']])) {
                return false;
            }
        }

        return true;
    }

    /**
     * bill.order_id（可多值）+ delivery_order 兜底
     *
     * @param array $bill
     * @return int[]
     */
    private function _collectOrderIdsForDeliveryBill(array $bill)
    {
        $ids = array();
        $orderField = isset($bill['order_id']) ? trim((string) $bill['order_id']) : '';
        if ($orderField !== '') {
            foreach (preg_split('/\s*,\s*/', $orderField, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $oid = (int) $part;
                if ($oid > 0) {
                    $ids[$oid] = true;
                }
            }
        }
        if (empty($ids) && !empty($bill['delivery_id'])) {
            $dos = app::get('ome')->model('delivery_order')->getList('order_id', array('delivery_id' => (int) $bill['delivery_id']));
            foreach ($dos as $d) {
                if (!empty($d['order_id'])) {
                    $ids[(int) $d['order_id']] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * 包裹中心「补发建议单」：补全 order_id 后写入三张表（与 erpapi 补寄单创建时的主表+明细结构一致，并预写 items_detail 便于后续补发订单）
     *
     * @param int $log_id sdb_ome_delivery_bill.log_id
     * @return array rsp/msg/data
     */
    public function runSuggestReshippingFromDeliveryBill($log_id)
    {
        $log_id = (int) $log_id;
        if ($log_id <= 0) {
            return array('rsp' => 'fail', 'msg' => '无效的包裹记录');
        }

        $this->supplementOrderIdsForDeliveryBillSuggest($log_id);

        $billModel = app::get('ome')->model('delivery_bill');
        $bill = $billModel->dump($log_id);
        if (empty($bill)) {
            return array('rsp' => 'fail', 'msg' => '包裹中心记录不存在');
        }
        $blockReason = $this->getDeliveryBillSuggestBlockReason($bill);
        if ($blockReason !== null) {
            return array('rsp' => 'fail', 'msg' => $blockReason);
        }

        $deliveryId = (int) $bill['delivery_id'];
        if ($deliveryId <= 0) {
            return array('rsp' => 'fail', 'msg' => '发货单ID无效');
        }

        $deliveryModel = app::get('ome')->model('delivery');
        $delivery = $deliveryModel->dump(array('delivery_id' => $deliveryId), '*');
        if (empty($delivery)) {
            return array('rsp' => 'fail', 'msg' => '发货单不存在');
        }

        $packageModel = app::get('ome')->model('delivery_package');
        $packages = $packageModel->getList('*', array(
            'delivery_id' => $deliveryId,
            'logi_no' => $bill['logi_no'],
        ), 0, -1, 'package_id ASC');
        if (empty($packages)) {
            return array('rsp' => 'fail', 'msg' => '未找到包裹明细，无法生成建议');
        }

        // 行级：子单 pay_status=5（全额退款）的包裹行不参与补发；至少保留一行可补发
        $payCheck = $this->decorateDeliveryPackagesPayStatusFromOrderObjects($packages);
        if (!$payCheck['ok']) {
            return array('rsp' => 'fail', 'msg' => $payCheck['msg']);
        }
        $packages = $this->filterPackagesEligibleForSuggestReshipping($packages);
        if (empty($packages)) {
            return array('rsp' => 'fail', 'msg' => '包裹明细均为全额退款子单，无法生成补发建议单');
        }

        $orderId = $this->_resolveOrderIdFromBillAndPackages($bill, $packages);
        if ($orderId <= 0) {
            return array('rsp' => 'fail', 'msg' => '无法解析关联订单，请检查包裹明细 order_id');
        }

        $orderModel = app::get('ome')->model('orders');
        // 使用 getRow：扁平字段且解密；勿用 dump()，否则 ship_* 会被 sdfpath 挪到 consignee/* 导致顶层为空
        $order = $orderModel->getRow(
            array('order_id' => $orderId),
            'order_id,order_bn,platform_order_bn,shop_id,shop_type,ship_addr,ship_area,ship_name,ship_mobile,ship_tel'
        );
        if (empty($order['shop_id'])) {
            return array('rsp' => 'fail', 'msg' => '订单未关联店铺');
        }

        $buyer = $this->buildBuyerFieldsFromOrder($order);
        $receiverRow = app::get('ome')->model('order_receiver')->db_dump(array('order_id' => $orderId), 'encrypt_source_data');
        $oaid = '';
        if (!empty($receiverRow['encrypt_source_data'])) {
            $encryptSource = json_decode($receiverRow['encrypt_source_data'], true);
            if (!empty($encryptSource['oaid'])) {
                $oaid = $encryptSource['oaid'];
            }
        }
        $original_bn = isset($bill['logi_no']) ? trim((string) $bill['logi_no']) : '';

        $reshipping_bn = $this->buildDeliveryBillSuggestReshippingBn($log_id);

        $reshippingModel = app::get('ome')->model('return_reshipping');
        $dupByOriginal = $this->findExistingDeliveryBillSuggestReshipping($log_id, $bill);
        if ($dupByOriginal) {
            return array('rsp' => 'fail', 'msg' => $this->deliveryBillSuggestDuplicateErrorMsg($dupByOriginal));
        }

        $agg = $this->_aggregatePackageBnNums($packages);
        if (empty($agg)) {
            return array('rsp' => 'fail', 'msg' => '包裹明细无有效基础物料号(bn)');
        }
        // 写入商品行以 delivery_package 行为准（不合并），便于按 order_item_id 解析销售物料

        $desc = $this->_buildDescForDeliveryBillSuggest($bill, $delivery, $packages, $agg);
        $extendPayload = array(
            'source' => 'delivery_bill',
            'log_id' => $log_id,
            'delivery_id' => $deliveryId,
            'delivery_bn' => isset($bill['delivery_bn']) ? $bill['delivery_bn'] : '',
            'logi_no' => isset($bill['logi_no']) ? $bill['logi_no'] : '',
        );

        $now = date('Y-m-d H:i:s');
        $sdf = array(
            'shop_id' => $order['shop_id'],
            'shop_type' => $order['shop_type'] ? $order['shop_type'] : '',
            'dispute_id' => $reshipping_bn,
            'alipay_no' => $order['order_bn'],
            'biz_order_id' => '',
            'reason' => '包裹追回补发建议（包裹中心）',
            'desc' => $desc,
            'logi_name' => isset($delivery['logi_name']) ? $delivery['logi_name'] : '',
            'logi_no' => isset($bill['logi_no']) ? $bill['logi_no'] : '',
            'created' => $now,
            'modified' => $now,
            'extend_field' => json_encode($extendPayload, JSON_UNESCAPED_UNICODE),
            'buyer_address' => $buyer['buyer_address'],
            'buyer_province' => $buyer['buyer_province'],
            'buyer_city' => $buyer['buyer_city'],
            'buyer_district' => $buyer['buyer_district'],
            'buyer_town' => $buyer['buyer_town'],
            'buyer_phone' => $buyer['buyer_phone'],
            'buyer_name' => $buyer['buyer_name'],
            'oaid' => $oaid,
        );

        $reshippingRow = $this->_formatReshippingInsertRow($sdf, $orderId);
        $reshippingRow['status'] = '0';
        $reshippingRow['source'] = 'local';
        $reshippingRow['sub_source'] = 'delivery_bill';
        $reshippingRow['original_id'] = $log_id;
        $reshippingRow['original_bn'] = $original_bn;

        $rsTran = kernel::database()->beginTransaction();
        try {
            $reshipping_id = $reshippingModel->insert($reshippingRow);
            if (!$reshipping_id) {
                kernel::database()->rollBack();
                return array('rsp' => 'fail', 'msg' => '保存补寄申请单失败');
            }

            $insItems = $this->insertReturnReshippingItemsFromDeliveryPackages($reshipping_id, $packages);
            if (empty($insItems['succ'])) {
                kernel::database()->rollBack();
                return array('rsp' => 'fail', 'msg' => isset($insItems['msg']) ? $insItems['msg'] : '保存补寄商品明细失败');
            }

            $billUp = $billModel->update(
                array(
                    'status'                => '8',
                    'suggest_reshipping_id' => (int) $reshipping_id,
                    'suggest_reshipping_bn' => $reshipping_bn,
                ),
                array('log_id' => $log_id)
            );
            if ($billUp === false) {
                kernel::database()->rollBack();
                return array('rsp' => 'fail', 'msg' => '更新包裹中心状态失败');
            }

            $pkgFilter = array(
                'delivery_id' => $deliveryId,
                'logi_no' => isset($bill['logi_no']) ? $bill['logi_no'] : '',
            );
            $pkgUp = $packageModel->update(array('status' => 'reshipping'), $pkgFilter);
            if ($pkgUp === false) {
                kernel::database()->rollBack();
                return array('rsp' => 'fail', 'msg' => '更新发货包裹状态失败');
            }

            kernel::database()->commit($rsTran);
        } catch (Exception $e) {
            kernel::database()->rollBack();
            return array('rsp' => 'fail', 'msg' => '保存失败：' . $e->getMessage());
        }

        $operationLog = app::get('ome')->model('operation_log');
        $operationLog->write_log('return_reshipping@ome', $reshipping_id, '创建补寄申请单');

        return array(
            'rsp' => 'succ',
            'msg' => '补发建议单已创建',
            'data' => array(
                'reshipping_id' => $reshipping_id,
                'reshipping_bn' => $reshipping_bn,
            ),
        );
    }

    /**
     * 用于「补发建议单」入口：按 order_objects.pay_status 补充包裹行 pay_status、suggest_eligible。
     * 规则：子单 pay_status=5（全额退款）的行不参与补发；只要并非全部包裹行均为 5 即可展示入口。
     *
     * @param array<int,array> &$packages delivery_package 行列表（原地补充 pay_status、suggest_eligible）
     * @return array{ok:bool,msg:string}
     */
    public function decorateDeliveryPackagesPayStatusFromOrderObjects(array &$packages)
    {
        if (empty($packages)) {
            return array('ok' => true, 'msg' => '');
        }

        $itemIdToPayStatus = $this->_loadOrderObjectPayStatusByPackageItemIds($packages);
        $eligibleCount = 0;
        $hasItemLink = !empty($itemIdToPayStatus);

        foreach ($packages as $k => $pk) {
            $statuses = $this->_collectObjPayStatusesForPackageRow($pk, $itemIdToPayStatus);
            if (empty($statuses) && !$hasItemLink) {
                $packages[$k]['suggest_eligible'] = true;
                $eligibleCount++;
                continue;
            }
            if (empty($statuses)) {
                $packages[$k]['suggest_eligible'] = true;
                $eligibleCount++;
                continue;
            }
            $packages[$k]['pay_status'] = $this->_packageDisplayPayStatusFromObjPayStatuses($statuses);
            $packages[$k]['suggest_eligible'] = $this->_packageSuggestEligibleByObjPayStatuses($statuses);
            if (!empty($packages[$k]['suggest_eligible'])) {
                $eligibleCount++;
            }
        }

        if ($hasItemLink && $eligibleCount <= 0) {
            return array(
                'ok' => false,
                'msg' => '包裹明细关联子单均为全额退款(pay_status=5)，无法生成补发建议单',
            );
        }
        return array('ok' => true, 'msg' => '');
    }

    /**
     * 仅保留可参与补发建议单的包裹行（排除子单全额退款）
     *
     * @param array $packages
     * @return array
     */
    public function filterPackagesEligibleForSuggestReshipping(array $packages)
    {
        $out = array();
        foreach ($packages as $pk) {
            if (!empty($pk['suggest_eligible'])) {
                $out[] = $pk;
            }
        }
        return $out;
    }

    /**
     * @param array $packages
     * @return int
     */
    public function countPackagesEligibleForSuggestReshipping(array $packages)
    {
        $n = 0;
        foreach ($packages as $pk) {
            if (!empty($pk['suggest_eligible'])) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @param array $packages
     * @return array<int,string> item_id => pay_status
     */
    private function _loadOrderObjectPayStatusByPackageItemIds(array $packages)
    {
        $packageItemIds = array();
        foreach ($packages as $pk) {
            $oidStr = isset($pk['order_item_id']) ? trim((string) $pk['order_item_id']) : '';
            if ($oidStr === '') {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', $oidStr, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $packageItemIds[$id] = true;
                }
            }
        }
        if (empty($packageItemIds)) {
            return array();
        }

        $idList = array();
        foreach (array_keys($packageItemIds) as $id) {
            $idList[] = (string) ((int) $id);
        }
        $inSql = implode(',', $idList);
        $db = kernel::database();
        $sql = 'SELECT oi.item_id, oo.pay_status'
            . ' FROM sdb_ome_order_items AS oi'
            . ' INNER JOIN sdb_ome_order_objects AS oo ON oo.obj_id=oi.obj_id'
            . ' WHERE oi.item_id IN (' . $inSql . ')';
        $rows = $db->select($sql);

        $itemIdToPayStatus = array();
        foreach ((array) $rows as $r) {
            if (!isset($r['item_id'])) {
                continue;
            }
            $itemIdToPayStatus[(int) $r['item_id']] = isset($r['pay_status']) ? (string) $r['pay_status'] : '';
        }
        return $itemIdToPayStatus;
    }

    /**
     * @param array $pk
     * @param array<int,string> $itemIdToPayStatus
     * @return string[]
     */
    private function _collectObjPayStatusesForPackageRow(array $pk, array $itemIdToPayStatus)
    {
        $oidStr = isset($pk['order_item_id']) ? trim((string) $pk['order_item_id']) : '';
        if ($oidStr === '') {
            return array();
        }
        $statuses = array();
        foreach (preg_split('/\s*,\s*/', $oidStr, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $id = (int) $part;
            if ($id <= 0) {
                continue;
            }
            if (isset($itemIdToPayStatus[$id]) && $itemIdToPayStatus[$id] !== '') {
                $statuses[] = (string) $itemIdToPayStatus[$id];
            }
        }
        return $statuses;
    }

    /**
     * 包裹行是否可补发：关联子单并非全部为 pay_status=5
     *
     * @param string[] $statuses
     * @return bool
     */
    private function _packageSuggestEligibleByObjPayStatuses(array $statuses)
    {
        if (empty($statuses)) {
            return true;
        }
        foreach ($statuses as $st) {
            if ((string) $st !== '5') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string[] $statuses
     * @return string
     */
    private function _packageDisplayPayStatusFromObjPayStatuses(array $statuses)
    {
        if (empty($statuses)) {
            return '';
        }
        if (!$this->_packageSuggestEligibleByObjPayStatuses($statuses)) {
            return '5';
        }
        foreach ($statuses as $st) {
            if ((string) $st === '1') {
                return '1';
            }
        }
        foreach ($statuses as $st) {
            if ((string) $st === '0') {
                return '0';
            }
        }
        return (string) $statuses[0];
    }

    /**
     * 是否物流中心「包裹中心」入口生成的补寄单（source=local, sub_source=delivery_bill）
     *
     * @param array $reshipping return_reshipping 行或含 source/sub_source 的数组
     * @return bool
     */
    public function isDeliveryBillLocalReshipping($reshipping)
    {
        return !empty($reshipping)
            && isset($reshipping['source']) && $reshipping['source'] === 'local'
            && isset($reshipping['sub_source']) && $reshipping['sub_source'] === 'delivery_bill';
    }

    /**
     * 补发订单是否来自物流中心包裹中心补寄单
     *
     * @param int $reissueOrderId 补发订单 order_id
     * @return bool
     */
    public function isDeliveryBillLocalBufaOrder($reissueOrderId)
    {
        $reissueOrderId = (int) $reissueOrderId;
        if ($reissueOrderId <= 0) {
            return false;
        }
        $reshipping = app::get('ome')->model('return_reshipping')->db_dump(
            array('reissue_order_id' => $reissueOrderId),
            'source,sub_source'
        );

        return $this->isDeliveryBillLocalReshipping($reshipping);
    }

    /**
     * 从订单主表收货人信息组装补寄买家字段（与订单一致，不取发货单 ship_* 快照）
     *
     * 注意：{@see ome_mdl_orders::dump()} 会把带 sdfpath 的列挪到 consignee/* 并从结果中移除顶层 ship_*，
     * 若传入 dump 结果请依赖 consignee；推荐调用方使用 {@see ome_mdl_orders::getRow()} 取扁平已解密字段。
     *
     * @param array $order orders 行
     * @return array buyer_address, buyer_province, buyer_city, buyer_district, buyer_town, buyer_phone, buyer_name
     */
    public function buildBuyerFieldsFromOrder(array $order)
    {
        $buyer_address = $this->_orderBuyerScalar($order, 'ship_addr', 'addr');
        $buyer_name = $this->_orderBuyerScalar($order, 'ship_name', 'name');
        $mobile = $this->_orderBuyerScalar($order, 'ship_mobile', 'mobile');
        $tel = $this->_orderBuyerScalar($order, 'ship_tel', 'telephone');
        $buyer_phone = $mobile !== '' ? $mobile : $tel;

        $areaParts = $this->_orderBuyerAreaParts($order);

        return array(
            'buyer_address' => $buyer_address,
            'buyer_province' => $areaParts['province'],
            'buyer_city' => $areaParts['city'],
            'buyer_district' => $areaParts['district'],
            'buyer_town' => $areaParts['town'],
            'buyer_phone' => $buyer_phone,
            'buyer_name' => $buyer_name,
        );
    }

    /**
     * @param string $consigneeKey consignee 下键名：addr/name/mobile/telephone
     */
    private function _orderBuyerScalar(array $order, $flatKey, $consigneeKey)
    {
        if (isset($order[$flatKey]) && $order[$flatKey] !== '') {
            return trim((string) $order[$flatKey]);
        }
        if (!empty($order['consignee'][$consigneeKey])) {
            return trim((string) $order['consignee'][$consigneeKey]);
        }

        return '';
    }

    /**
     * @return array{province:string,city:string,district:string,town:string}
     */
    private function _orderBuyerAreaParts(array $order)
    {
        $empty = array(
            'province' => '',
            'city' => '',
            'district' => '',
            'town' => '',
        );

        if (!empty($order['consignee']['area']) && is_array($order['consignee']['area'])) {
            $a = $order['consignee']['area'];
            $p = isset($a['province']) ? trim((string) $a['province']) : '';
            $c = isset($a['city']) ? trim((string) $a['city']) : '';
            $d = isset($a['county']) ? trim((string) $a['county']) : (isset($a['district']) ? trim((string) $a['district']) : '');
            $t = isset($a['town']) ? trim((string) $a['town']) : '';
            if ($p !== '' || $c !== '' || $d !== '' || $t !== '') {
                $empty['province'] = $p;
                $empty['city'] = $c;
                $empty['district'] = $d;
                $empty['town'] = $t;

                return $empty;
            }
        }

        $ship_area = '';
        if (isset($order['ship_area']) && $order['ship_area'] !== '') {
            $ship_area = (string) $order['ship_area'];
        } elseif (!empty($order['consignee']['area']) && is_string($order['consignee']['area'])) {
            $ship_area = $order['consignee']['area'];
        }

        $ship_area = trim($ship_area);
        if ($ship_area === '') {
            return $empty;
        }

        $areaParts = $ship_area;
        kernel::single('eccommon_regions')->split_area($areaParts);
        if (is_array($areaParts) && !empty($areaParts)) {
            $province = isset($areaParts[0]) ? trim((string) $areaParts[0]) : '';
            $city = isset($areaParts[1]) ? trim((string) $areaParts[1]) : '';
            $rawDistrict = isset($areaParts[2]) ? (string) $areaParts[2] : '';
            if ($rawDistrict !== '' && strpos($rawDistrict, ':') !== false) {
                $tmpCounty = explode(':', $rawDistrict);
                $district = trim($tmpCounty[0]);
            } else {
                $district = trim($rawDistrict);
            }
            $town = isset($areaParts[3]) ? trim((string) $areaParts[3]) : '';

            return array(
                'province' => $province,
                'city' => $city,
                'district' => $district,
                'town' => $town,
            );
        }

        return $this->_parseShipAreaStringFallback($ship_area);
    }

    /**
     * split_area 无法识别时的省市区镇解析（对齐订单导出等对 consignee.area 的处理）
     *
     * @return array{province:string,city:string,district:string,town:string}
     */
    private function _parseShipAreaStringFallback($ship_area)
    {
        $s = trim((string) $ship_area);
        if (preg_match('/^\d+\s*:\s*(.+)$/', $s, $m)) {
            $s = trim($m[1]);
        }
        $s = trim($s, ': ');
        $parts = array_values(array_filter(explode('/', $s), function ($x) {
            return $x !== '';
        }));
        $province = '';
        $city = '';
        $district = '';
        $town = '';
        if (!empty($parts)) {
            $first = trim($parts[0]);
            if (strpos($first, ':') !== false) {
                $tmpP = explode(':', $first);
                $province = trim(end($tmpP));
            } else {
                $province = $first;
            }
            $city = isset($parts[1]) ? trim($parts[1]) : '';
            $rawD = isset($parts[2]) ? trim($parts[2]) : '';
            if ($rawD !== '' && strpos($rawD, ':') !== false) {
                $tmpCounty = explode(':', $rawD);
                $district = trim($tmpCounty[0]);
            } else {
                $district = $rawD;
            }
            $town = isset($parts[3]) ? trim($parts[3]) : '';
        }

        return array(
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'town' => $town,
        );
    }

    /**
     * 按 delivery_package 行写入 return_reshipping_items / return_reshipping_items_detail。
     * 商品与价格：delivery_package.order_item_id 对应 sdb_ome_order_items.item_id；价格按该行 order_items 与 nums 计算。
     * 商品ID：order_item_id → order_items.obj_id → order_objects（obj_type、quantity 等）；
     * 销售物料：由 order_items.bn 解析 sales_material（sales_material_type=1）得 sm_id、sales_material_bn；未命中则留空，不按包裹 bn 回退，仍写入明细。
     * oid：平台子订单号 order_objects.oid；order_item_id：sdb_ome_order_items.item_id。
     *
     * @param int   $reshipping_id
     * @param array $packages      sdb_ome_delivery_package 行列表
     * @return array succ(bool), msg(string)
     */
    public function insertReturnReshippingItemsFromDeliveryPackages($reshipping_id, array $packages)
    {
        $reshipping_id = (int) $reshipping_id;
        if ($reshipping_id <= 0) {
            return array('succ' => false, 'msg' => '无效的补寄申请单ID');
        }

        $itemsModel = app::get('ome')->model('return_reshipping_items');
        $reshippingModel = app::get('ome')->model('return_reshipping');

        $rr = $reshippingModel->db_dump($reshipping_id, 'order_id');
        $orderId = isset($rr['order_id']) ? (int) $rr['order_id'] : 0;
        $shopId = '';
        if ($orderId > 0) {
            $ord = app::get('ome')->model('orders')->db_dump(array('order_id' => $orderId), 'shop_id');
            if (!empty($ord['shop_id'])) {
                $shopId = trim((string) $ord['shop_id']);
            }
        }

        $inserted = 0;
        foreach ($packages as $pkg) {
            $bn = isset($pkg['bn']) ? trim((string) $pkg['bn']) : '';
            if ($bn === '') {
                continue;
            }
            $num = isset($pkg['number']) ? (int) $pkg['number'] : 0;
            if ($num <= 0) {
                continue;
            }

            $orderItemId = $this->_firstOrderItemIdFromPackage($pkg);
            $ctx = $this->_loadOrderReshippingContext($orderItemId, $orderId);
            if ($ctx !== null) {
                $smId = (int) $ctx['sm_id'];
                $salesBn = isset($ctx['sales_material_bn']) ? trim((string) $ctx['sales_material_bn']) : '';
            } else {
                $smId = 0;
                $salesBn = '';
            }
            // 部分包裹行没有 order_item_id / 或 order_items.bn 异常：按包裹基础物料号(bn)回退解析 sales_material_type=1
            if ($salesBn === '' && $bn !== '') {
                $resolvedSm = $this->_salesMaterialType1ByOrderItemBn($bn, $shopId);
                if (!empty($resolvedSm['sales_material_bn'])) {
                    $salesBn = trim((string) $resolvedSm['sales_material_bn']);
                    $smId = (int) $resolvedSm['sm_id'];
                }
            }

            $numsConverted = $this->_convertedNumsForReshippingItemsDetail($num, $ctx);

            $platformOid = '';
            if ($ctx !== null && !empty($ctx['platform_oid'])) {
                $platformOid = $this->_normalizePlatformOid($ctx['platform_oid']);
            } elseif ($orderItemId > 0) {
                $platformOid = $this->getPlatformOidForOrderItem($orderItemId, $orderId);
            }

            $unitPrice = $this->_reshippingItemUnitPriceFromOrder(
                $orderId,
                $orderItemId,
                $salesBn,
                $bn,
                $ctx
            );

            $moneyCols = $this->_returnReshippingItemsMoneyFromOrderLine(
                $ctx,
                $orderId,
                $orderItemId,
                $numsConverted
            );
            // 无 order_item_id 时，按 order_id + 包裹基础货号(bn) 回退取订单行金额
            if (($ctx === null || $orderItemId <= 0) && $orderId > 0 && $bn !== '') {
                $fallback = $this->_returnReshippingItemsMoneyFromOrderLineByBn($orderId, $bn, $numsConverted);
                // 仅当回退有值时覆盖，避免误覆盖 ctx 已解析出的金额
                if ((float) $fallback['amount'] > 0 || (float) $fallback['sale_price'] > 0 || (float) $fallback['divide_order_fee'] > 0) {
                    $moneyCols = $fallback;
                }
            }

            $itemData = array(
                'reshipping_id' => $reshipping_id,
                'oid' => $platformOid,
                'order_item_id' => $orderItemId > 0 ? $orderItemId : 0,
                'sku_uuid' => '',
                'goods_id' => $smId,
                'bn' => $salesBn,
                'nums' => $numsConverted,
                'price' => $unitPrice,
                'pmt_price' => $moneyCols['pmt_price'],
                'sale_price' => $moneyCols['sale_price'],
                'amount' => $moneyCols['amount'],
                'divide_order_fee' => $moneyCols['divide_order_fee'],
                'part_mjz_discount' => $moneyCols['part_mjz_discount'],
            );
            $item_id = $itemsModel->insert($itemData);
            if (!$item_id) {
                return array('succ' => false, 'msg' => '保存补寄商品行失败');
            }
            $inserted++;
        }

        if ($inserted <= 0) {
            return array('succ' => false, 'msg' => '没有可写入的包裹明细');
        }

        return array('succ' => true, 'msg' => '');
    }

    /**
     * 按 order_items.item_id 取平台子单号 order_objects.oid
     *
     * @param int $orderItemId sdb_ome_order_items.item_id
     * @param int $orderId     可选，限定订单
     * @return string
     */
    public function getPlatformOidForOrderItem($orderItemId, $orderId = 0)
    {
        $orderItemId = (int) $orderItemId;
        if ($orderItemId <= 0) {
            return '';
        }
        $filter = array('item_id' => $orderItemId);
        $orderId = (int) $orderId;
        if ($orderId > 0) {
            $filter['order_id'] = $orderId;
        }
        $oi = app::get('ome')->model('order_items')->db_dump($filter, 'obj_id');
        if (empty($oi['obj_id'])) {
            return '';
        }
        $oo = app::get('ome')->model('order_objects')->db_dump(
            array('obj_id' => (int) $oi['obj_id']),
            'oid'
        );

        return $this->_normalizePlatformOid(isset($oo['oid']) ? $oo['oid'] : '');
    }

    /**
     * 补寄申请行 → 平台子单号（优先 order_item_id 查库；兼容历史 oid 误存 item_id）
     *
     * @param array $applyItem return_reshipping_items 一行
     * @param int   $orderId   原订单 order_id
     * @return string
     */
    public function resolvePlatformOidForApplyItem(array $applyItem, $orderId = 0)
    {
        $orderId = (int) $orderId;
        $orderItemId = isset($applyItem['order_item_id']) ? (int) $applyItem['order_item_id'] : 0;
        $storedOid = isset($applyItem['oid']) ? trim((string) $applyItem['oid']) : '';

        if ($orderItemId <= 0 && $storedOid !== '' && $orderId > 0) {
            $legacyItem = app::get('ome')->model('order_items')->db_dump(
                array('item_id' => (int) $storedOid, 'order_id' => $orderId),
                'item_id'
            );
            if (!empty($legacyItem['item_id'])) {
                $orderItemId = (int) $legacyItem['item_id'];
            }
        }

        if ($orderItemId > 0) {
            $fromDb = $this->getPlatformOidForOrderItem($orderItemId, $orderId);
            if ($fromDb !== '') {
                return $fromDb;
            }
        }

        return $this->_normalizePlatformOid($storedOid);
    }

    /**
     * 列表/详情展示：补全 oid_display（包裹中心走 resolve；矩阵补寄直接用 oid 字段）
     *
     * @param array $items
     * @param array $reshipping return_reshipping 主单
     * @return array
     */
    public function decorateReshippingItemsForView(array $items, array $reshipping)
    {
        $orderId = isset($reshipping['order_id']) ? (int) $reshipping['order_id'] : 0;
        $isDeliveryBill = $this->isDeliveryBillLocalReshipping($reshipping);
        foreach ($items as $k => $item) {
            if ($isDeliveryBill) {
                $oid = $this->resolvePlatformOidForApplyItem($item, $orderId);
            } else {
                $oid = $this->_normalizePlatformOid(isset($item['oid']) ? $item['oid'] : '');
            }
            $items[$k]['oid_display'] = $oid !== '' ? $oid : '-';
        }

        return $items;
    }

    /**
     * 按平台子单号反查 order_items.item_id（取该 object 下首个未删明细）
     *
     * @param int    $orderId
     * @param string $platformOid order_objects.oid
     * @return int
     */
    public function resolveOrderItemIdByPlatformOid($orderId, $platformOid)
    {
        $orderId = (int) $orderId;
        $platformOid = $this->_normalizePlatformOid($platformOid);
        if ($orderId <= 0 || $platformOid === '') {
            return 0;
        }

        $oo = app::get('ome')->model('order_objects')->db_dump(
            array('order_id' => $orderId, 'oid' => $platformOid),
            'obj_id'
        );
        if (empty($oo['obj_id'])) {
            return 0;
        }

        $items = app::get('ome')->model('order_items')->getList(
            'item_id',
            array('order_id' => $orderId, 'obj_id' => (int) $oo['obj_id'], 'delete' => 'false'),
            0,
            1,
            'item_id ASC'
        );
        if (empty($items[0]['item_id'])) {
            return 0;
        }

        return (int) $items[0]['item_id'];
    }

    /**
     * @param mixed $oid
     * @return string
     */
    private function _normalizePlatformOid($oid)
    {
        $oid = trim((string) $oid);
        if ($oid === '' || $oid === '0' || $oid === '-1') {
            return '';
        }

        return $oid;
    }

    /**
     * 无 order_item_id 时，按订单号 + 基础货号(bn) 读取订单行金额并按 nums 比例缩放
     *
     * @param int    $orderId
     * @param string $basicBn   delivery_package.bn（基础物料号）
     * @param int    $numsConverted
     * @return array pmt_price, sale_price, amount, divide_order_fee, part_mjz_discount
     */
    private function _returnReshippingItemsMoneyFromOrderLineByBn($orderId, $basicBn, $numsConverted)
    {
        $cols = array(
            'pmt_price' => 0.0,
            'sale_price' => 0.0,
            'amount' => 0.0,
            'divide_order_fee' => 0.0,
            'part_mjz_discount' => 0.0,
        );
        $orderId = (int) $orderId;
        $basicBn = trim((string) $basicBn);
        $numsConverted = (int) $numsConverted;
        if ($orderId <= 0 || $basicBn === '' || $numsConverted <= 0) {
            return $cols;
        }

        $db = kernel::database();
        $bnQ = $db->quote($basicBn);
        $sql = 'SELECT nums,pmt_price,sale_price,amount,divide_order_fee,part_mjz_discount FROM sdb_ome_order_items'
            . ' WHERE order_id=' . $orderId . ' AND bn=' . $bnQ;
        $row = $db->selectrow($sql);
        if (empty($row) || !is_array($row)) {
            return $cols;
        }

        $lineNums = isset($row['nums']) ? (float) $row['nums'] : 0.0;
        if ($lineNums <= 0) {
            return $cols;
        }
        foreach (array_keys($cols) as $k) {
            $cols[$k] = isset($row[$k]) ? (float) $row[$k] : 0.0;
        }
        $ratio = $numsConverted / $lineNums;
        foreach (array_keys($cols) as $k) {
            $cols[$k] = round($cols[$k] * $ratio, 3);
        }

        return $cols;
    }

    /**
     * @param array $pkg delivery_package 一行
     * @return int 首个 order_item_id
     */
    private function _firstOrderItemIdFromPackage(array $pkg)
    {
        $s = isset($pkg['order_item_id']) ? trim((string) $pkg['order_item_id']) : '';
        if ($s === '') {
            return 0;
        }
        if (strpos($s, ',') !== false) {
            $parts = preg_split('/\s*,\s*/', $s, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $p) {
                $id = (int) $p;
                if ($id > 0) {
                    return $id;
                }
            }
            return 0;
        }
        return (int) $s;
    }

    /**
     * 用订单行基础货号 order_items.bn 解析「普通」销售物料（sales_material_type=1）：先按 sales_material_bn 命中，再按 basic_material + sales_basic_material 关联命中
     *
     * @param string $basicBn order_items.bn
     * @param string $shopId  订单店铺，用于限定 sales_material.shop_id（含 _ALL_）
     * @return array          sm_id(int)、sales_material_bn(string)，未找到均为 0 / 空串
     */
    private function _salesMaterialType1ByOrderItemBn($basicBn, $shopId = '')
    {
        $basicBn = trim((string) $basicBn);
        if ($basicBn === '') {
            return array('sm_id' => 0, 'sales_material_bn' => '');
        }

        $db = kernel::database();
        $shopClause = '';
        if ($shopId !== '') {
            $shopClause = ' AND (shop_id=' . $db->quote($shopId) . ' OR shop_id=' . $db->quote('_ALL_') . ')';
        }

        // 不用 sales_material::getList：部分环境下 _filter 可能拼出空 WHERE
        // 注意：selectrow() 会再追加 LIMIT 0,1，SQL 内勿写 LIMIT，否则出现 “LIMIT 1 LIMIT 0,1” 语法错误
        $bnQ = $db->quote($basicBn);
        $sql = 'SELECT sm_id, sales_material_bn FROM sdb_material_sales_material'
            . ' WHERE sales_material_bn=' . $bnQ . ' AND sales_material_type=1' . $shopClause;
        $row = $db->selectrow($sql);
        if (!empty($row['sm_id']) && isset($row['sales_material_bn']) && trim((string) $row['sales_material_bn']) !== '') {
            return array(
                'sm_id' => (int) $row['sm_id'],
                'sales_material_bn' => trim((string) $row['sales_material_bn']),
            );
        }

        $bm = app::get('material')->model('basic_material')->db_dump(array('material_bn' => $basicBn), 'bm_id');
        if (empty($bm['bm_id'])) {
            return array('sm_id' => 0, 'sales_material_bn' => '');
        }
        $bmId = (int) $bm['bm_id'];
        $sbmRows = $db->select('SELECT sm_id FROM sdb_material_sales_basic_material WHERE bm_id=' . $bmId);
        if (empty($sbmRows)) {
            return array('sm_id' => 0, 'sales_material_bn' => '');
        }

        $seen = array();
        foreach ($sbmRows as $sbm) {
            $smIdTry = isset($sbm['sm_id']) ? (int) $sbm['sm_id'] : 0;
            if ($smIdTry <= 0 || isset($seen[$smIdTry])) {
                continue;
            }
            $seen[$smIdTry] = true;

            $sqlSm = 'SELECT sm_id, sales_material_bn FROM sdb_material_sales_material'
                . ' WHERE sm_id=' . $smIdTry . ' AND sales_material_type=1' . $shopClause;
            $rowSm = $db->selectrow($sqlSm);
            if (!empty($rowSm['sm_id']) && isset($rowSm['sales_material_bn']) && trim((string) $rowSm['sales_material_bn']) !== '') {
                return array(
                    'sm_id' => (int) $rowSm['sm_id'],
                    'sales_material_bn' => trim((string) $rowSm['sales_material_bn']),
                );
            }
        }

        return array('sm_id' => 0, 'sales_material_bn' => '');
    }

    /**
     * order_item_id → order_items + order_objects，供销售物料与数量换算（捆绑 pkg 与 {@see erpapi_shop_matrix_tmall_response_aftersalev2::_tranChangeItems} 一致）
     *
     * @param int $orderItemId order_items.item_id（与 delivery_package.order_item_id 一致）
     * @param int $orderId     补寄单关联订单，用于校验 order_items.order_id，防止串单
     * @return array|null sm_id, order_item_id, platform_oid, sales_material_bn（由 order_items.bn 解析 material.sales_material type=1）, order_item_bn, obj_id, obj_type, order_object_quantity, order_item_nums, item_type, divide_order_fee, sale_price, amount, price, pmt_price, part_mjz_discount
     */
    private function _loadOrderReshippingContext($orderItemId, $orderId = 0)
    {
        $orderItemId = (int) $orderItemId;
        if ($orderItemId <= 0) {
            return null;
        }

        $filter = array('item_id' => $orderItemId);
        $orderId = (int) $orderId;
        if ($orderId > 0) {
            $filter['order_id'] = $orderId;
        }

        $oiModel = app::get('ome')->model('order_items');
        $oi = $oiModel->db_dump(
            $filter,
            'obj_id,nums,bn,item_type,divide_order_fee,sale_price,amount,price,pmt_price,part_mjz_discount'
        );
        if (empty($oi['obj_id'])) {
            return null;
        }

        $objId = (int) $oi['obj_id'];
        $ooModel = app::get('ome')->model('order_objects');
        $oo = $ooModel->db_dump(array('obj_id' => $objId), 'goods_id,bn,obj_type,quantity,oid');
        if (empty($oo)) {
            return null;
        }

        $shopId = '';
        if ($orderId > 0) {
            $ord = app::get('ome')->model('orders')->db_dump(array('order_id' => $orderId), 'shop_id');
            if (!empty($ord['shop_id'])) {
                $shopId = trim((string) $ord['shop_id']);
            }
        }

        $orderItemBn = isset($oi['bn']) ? trim((string) $oi['bn']) : '';
        $resolvedSm = $this->_salesMaterialType1ByOrderItemBn($orderItemBn, $shopId);
        $smId = (int) $resolvedSm['sm_id'];
        $salesMaterialBn = isset($resolvedSm['sales_material_bn']) ? trim((string) $resolvedSm['sales_material_bn']) : '';

        return array(
            'order_item_id' => $orderItemId,
            'platform_oid' => $this->_normalizePlatformOid(isset($oo['oid']) ? $oo['oid'] : ''),
            'sm_id' => $smId,
            'sales_material_bn' => $salesMaterialBn,
            'order_item_bn' => $orderItemBn,
            'obj_id' => $objId,
            'obj_type' => isset($oo['obj_type']) ? trim((string) $oo['obj_type']) : '',
            'order_object_quantity' => isset($oo['quantity']) ? (float) $oo['quantity'] : 0.0,
            'order_item_nums' => isset($oi['nums']) ? (float) $oi['nums'] : 0.0,
            'item_type' => isset($oi['item_type']) ? trim((string) $oi['item_type']) : '',
            'divide_order_fee' => isset($oi['divide_order_fee']) ? (float) $oi['divide_order_fee'] : 0.0,
            'sale_price' => isset($oi['sale_price']) ? (float) $oi['sale_price'] : 0.0,
            'amount' => isset($oi['amount']) ? (float) $oi['amount'] : 0.0,
            'price' => isset($oi['price']) ? (float) $oi['price'] : 0.0,
            'pmt_price' => isset($oi['pmt_price']) ? (float) $oi['pmt_price'] : 0.0,
            'part_mjz_discount' => isset($oi['part_mjz_discount']) ? (float) $oi['part_mjz_discount'] : 0.0,
        );
    }

    /**
     * 订单行金额写入 return_reshipping_items：与 order_items 字段对齐，按补寄 nums 占订单行 nums 比例缩放
     *
     * @param array|null $ctx            {@see _loadOrderReshippingContext}
     * @param int        $orderId
     * @param int        $orderItemId    order_items.item_id
     * @param int        $numsConverted  补寄明细数量
     * @return array     pmt_price, sale_price, amount, divide_order_fee, part_mjz_discount
     */
    private function _returnReshippingItemsMoneyFromOrderLine($ctx, $orderId, $orderItemId, $numsConverted)
    {
        $cols = array(
            'pmt_price' => 0.0,
            'sale_price' => 0.0,
            'amount' => 0.0,
            'divide_order_fee' => 0.0,
            'part_mjz_discount' => 0.0,
        );
        $numsConverted = (int) $numsConverted;
        if ($numsConverted <= 0) {
            return $cols;
        }

        $lineNums = 0.0;
        $src = null;

        if ($ctx !== null && isset($ctx['order_item_nums']) && (float) $ctx['order_item_nums'] > 0) {
            $lineNums = (float) $ctx['order_item_nums'];
            $src = $ctx;
        } elseif ($orderId > 0 && $orderItemId > 0) {
            $row = $this->_orderItemMoneyRowByItemId($orderId, $orderItemId);
            if (!empty($row) && is_array($row)) {
                $src = $row;
                $lineNums = isset($row['nums']) ? (float) $row['nums'] : 0.0;
            }
        }

        if ($src === null || $lineNums <= 0) {
            return $cols;
        }

        foreach (array_keys($cols) as $k) {
            $cols[$k] = isset($src[$k]) ? (float) $src[$k] : 0.0;
        }

        $ratio = $numsConverted / $lineNums;
        foreach (array_keys($cols) as $k) {
            $cols[$k] = round($cols[$k] * $ratio, 3);
        }

        return $cols;
    }

    /**
     * @param int $order_id
     * @param int $item_id
     * @return array|null
     */
    private function _orderItemMoneyRowByItemId($order_id, $item_id)
    {
        $order_id = (int) $order_id;
        $item_id = (int) $item_id;
        if ($order_id <= 0 || $item_id <= 0) {
            return null;
        }

        $db = kernel::database();
        $sql = 'SELECT nums,pmt_price,sale_price,amount,divide_order_fee,part_mjz_discount FROM sdb_ome_order_items'
            . ' WHERE item_id=' . $item_id . ' AND order_id=' . $order_id;
        $row = $db->selectrow($sql);

        return (!empty($row) && is_array($row)) ? $row : null;
    }

    /**
     * 补寄明细单价：优先用 delivery_package.order_item_id = order_items.item_id（且 order_id 一致）取价，按 nums 折算
     *
     * @param int         $order_id
     * @param int         $orderItemId     delivery_package.order_item_id → order_items.item_id
     * @param string      $salesMaterialBn 销售物料货号（order_objects.bn）
     * @param string      $packageBasicBn  包裹行基础物料 bn（delivery_package.bn）
     * @param array|null  $ctx             {@see _loadOrderReshippingContext}
     * @return float
     */
    private function _reshippingItemUnitPriceFromOrder($order_id, $orderItemId, $salesMaterialBn, $packageBasicBn, $ctx)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return 0.0;
        }

        $orderItemId = (int) $orderItemId;
        if ($orderItemId > 0) {
            $row = $this->_orderItemRowForPriceByItemId($order_id, $orderItemId);
            if (!empty($row)) {
                $u = $this->_unitPriceFromOrderItemRow($row);
                if ($u > 0) {
                    return $u;
                }
            }
        }

        $basicBn = '';
        if ($ctx !== null && isset($ctx['order_item_bn']) && $ctx['order_item_bn'] !== '') {
            $basicBn = $ctx['order_item_bn'];
        } elseif ($packageBasicBn !== '') {
            $basicBn = trim((string) $packageBasicBn);
        }

        // 无有效 order_item_id 或该行金额为 0：用上下文或货号回退
        if ($ctx !== null && isset($ctx['item_type']) && $ctx['item_type'] === 'lkb') {
            $fee = isset($ctx['divide_order_fee']) ? (float) $ctx['divide_order_fee'] : 0.0;
            $nums = isset($ctx['order_item_nums']) ? (float) $ctx['order_item_nums'] : 0.0;
            if ($fee > 0 && $nums > 0) {
                return round($fee / $nums, 4);
            }

            return $this->_orderItemUnitPriceFallback($order_id, $salesMaterialBn, $basicBn);
        }

        if ($ctx !== null) {
            $nums = isset($ctx['order_item_nums']) ? (float) $ctx['order_item_nums'] : 0.0;
            if ($nums > 0) {
                $salePrice = isset($ctx['sale_price']) ? (float) $ctx['sale_price'] : 0.0;
                if ($salePrice > 0) {
                    return round($salePrice / $nums, 4);
                }
                $amount = isset($ctx['amount']) ? (float) $ctx['amount'] : 0.0;
                if ($amount > 0) {
                    return round($amount / $nums, 4);
                }
                $price = isset($ctx['price']) ? (float) $ctx['price'] : 0.0;
                if ($price > 0) {
                    return round($price, 4);
                }
            }
        }

        return $this->_orderItemUnitPriceFallback($order_id, $salesMaterialBn, $basicBn);
    }

    /**
     * 按 item_id + order_id 读取订单行（与 delivery_package.order_item_id 对齐）
     *
     * @param int $order_id
     * @param int $item_id  order_items.item_id
     * @return array|null
     */
    private function _orderItemRowForPriceByItemId($order_id, $item_id)
    {
        $order_id = (int) $order_id;
        $item_id = (int) $item_id;
        if ($order_id <= 0 || $item_id <= 0) {
            return null;
        }

        $db = kernel::database();
        $sql = 'SELECT sale_price,amount,price,nums,divide_order_fee,item_type FROM sdb_ome_order_items'
            . ' WHERE item_id=' . $item_id . ' AND order_id=' . $order_id;

        $row = $db->selectrow($sql);

        return (!empty($row) && is_array($row)) ? $row : null;
    }

    /**
     * 无 order_item_id 或行金额为 0 时，按订单号 + 基础货号 / 销售物料货号查 order_items
     *
     * @param int    $order_id
     * @param string $salesMaterialBn
     * @param string $basicBn
     * @return float
     */
    private function _orderItemUnitPriceFallback($order_id, $salesMaterialBn, $basicBn)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return 0.0;
        }

        $db = kernel::database();
        $oid = $order_id;

        if ($basicBn !== '') {
            $bnQ = $db->quote($basicBn);
            $sql = 'SELECT sale_price,amount,price,nums,divide_order_fee,item_type FROM sdb_ome_order_items'
                . ' WHERE order_id=' . $oid . ' AND bn=' . $bnQ;
            $row = $db->selectrow($sql);
            $u = $this->_unitPriceFromOrderItemRow($row);
            if ($u > 0) {
                return $u;
            }
        }

        $smBn = trim((string) $salesMaterialBn);
        if ($smBn !== '') {
            $smQ = $db->quote($smBn);
            $sql = 'SELECT OI.sale_price,OI.amount,OI.price,OI.nums,OI.divide_order_fee,OI.item_type'
                . ' FROM sdb_ome_order_items AS OI INNER JOIN sdb_ome_order_objects AS OO ON OI.obj_id=OO.obj_id'
                . ' WHERE OI.order_id=' . $oid . ' AND OO.bn=' . $smQ;
            $row = $db->selectrow($sql);

            return $this->_unitPriceFromOrderItemRow($row);
        }

        return 0.0;
    }

    /**
     * @param array|null $row sdb_ome_order_items 一行
     * @return float
     */
    private function _unitPriceFromOrderItemRow($row)
    {
        if (empty($row) || !is_array($row)) {
            return 0.0;
        }

        $nums = isset($row['nums']) ? (float) $row['nums'] : 0.0;
        if ($nums <= 0) {
            return 0.0;
        }

        if (isset($row['item_type']) && $row['item_type'] === 'lkb') {
            $fee = isset($row['divide_order_fee']) ? (float) $row['divide_order_fee'] : 0.0;
            if ($fee > 0) {
                return round($fee / $nums, 4);
            }

            return 0.0;
        }

        $salePrice = isset($row['sale_price']) ? (float) $row['sale_price'] : 0.0;
        if ($salePrice > 0) {
            return round($salePrice / $nums, 4);
        }

        $amount = isset($row['amount']) ? (float) $row['amount'] : 0.0;
        if ($amount > 0) {
            return round($amount / $nums, 4);
        }

        $price = isset($row['price']) ? (float) $row['price'] : 0.0;

        return $price > 0 ? round($price, 4) : 0.0;
    }

    /**
     * return_reshipping_items 的 nums：直接采用 delivery_package.number。
     *
     * delivery_package.bn 为基础物料号，number 已是基础物料数量（与 order_items.nums 同粒度）。
     * 矩阵售后换货里 pkg 的 radio 换算适用于「退货数量在捆绑销售对象层」的场景
     *（{@see erpapi_shop_matrix_tmall_response_exchange}：radio = 退货套数 / order_objects.quantity），
     * 不能套用在包裹中心行上，否则会把 2 算成 (2/1)*2=4。
     *
     * @param int        $packageNum delivery_package.number
     * @param array|null $ctx        保留参数，便于后续扩展
     * @return int
     */
    private function _convertedNumsForReshippingItemsDetail($packageNum, $ctx)
    {
        $packageNum = (int) $packageNum;
        if ($packageNum <= 0) {
            return 0;
        }
        return $packageNum;
    }

    /**
     * 与 erpapi {@see erpapi_shop_response_process_reshipping::_formatReshippingData} 一致的主表字段组织
     *
     * @param array $sdf
     * @param int   $order_id
     * @return array
     */
    private function _formatReshippingInsertRow(array $sdf, $order_id)
    {
        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->dump(array('order_id' => $order_id), 'order_bn,platform_order_bn');

        $data = array(
            'reshipping_bn' => $sdf['dispute_id'],
            'shop_id' => $sdf['shop_id'],
            'shop_type' => $sdf['shop_type'],
            'order_id' => $order_id,
            'order_bn' => $order['order_bn'] ?: '',
            'platform_order_bn' => $order['platform_order_bn'] ?: $sdf['alipay_no'],
            'source' => 'matrix',
            'buyer_address' => $sdf['buyer_address'] ?: '',
            'buyer_province' => $sdf['buyer_province'] ?: '',
            'buyer_city' => $sdf['buyer_city'] ?: '',
            'buyer_district' => $sdf['buyer_district'] ?: '',
            'buyer_town' => $sdf['buyer_town'] ?: '',
            'buyer_phone' => $sdf['buyer_phone'] ?: '',
            'buyer_name' => $sdf['buyer_name'] ?: '',
            'buyer_open_uid' => isset($sdf['buyer_open_uid']) ? $sdf['buyer_open_uid'] : '',
            'oaid' => isset($sdf['oaid']) ? $sdf['oaid'] : '',
            'real_receiver_open_id' => isset($sdf['real_receiver_open_id']) ? $sdf['real_receiver_open_id'] : '',
            'real_receiver_display_nick' => isset($sdf['real_receiver_display_nick']) ? $sdf['real_receiver_display_nick'] : '',
            'reason' => $sdf['reason'] ?: '',
            'desc' => $sdf['desc'] ?: '',
            'refund_phase' => isset($sdf['refund_phase']) ? $sdf['refund_phase'] : '',
            'cs_status' => isset($sdf['cs_status']) ? $sdf['cs_status'] : '',
            'advance_status' => isset($sdf['advance_status']) ? $sdf['advance_status'] : '',
            'operation_contraint' => isset($sdf['operation_contraint']) ? $sdf['operation_contraint'] : '',
            'logi_name' => $sdf['logi_name'] ?: '',
            'logi_no' => $sdf['logi_no'] ?: '',
            'refuse_reason' => isset($sdf['refuse_reason']) ? $sdf['refuse_reason'] : '',
            'refuse_reason_id' => isset($sdf['refuse_reason_id']) ? $sdf['refuse_reason_id'] : '',
            'extend_field' => isset($sdf['extend_field']) ? $sdf['extend_field'] : '',
        );

        if (!empty($sdf['created'])) {
            $data['outer_create_time'] = kernel::single('ome_func')->date2time($sdf['created']);
        }
        if (!empty($sdf['modified'])) {
            $data['outer_modified_time'] = kernel::single('ome_func')->date2time($sdf['modified']);
        }
        if (!empty($sdf['time_out'])) {
            $data['time_out'] = kernel::single('ome_func')->date2time($sdf['time_out']);
        }

        return $data;
    }

    /**
     * 补发建议单入口前：若包裹中心/包裹行未写 order_id，则按订单号、发货单-订单关联、明细表补写 DB
     *
     * @param int $log_id sdb_ome_delivery_bill.log_id
     */
    public function supplementOrderIdsForDeliveryBillSuggest($log_id)
    {
        $log_id = (int) $log_id;
        if ($log_id <= 0) {
            return;
        }

        $billModel = app::get('ome')->model('delivery_bill');
        $bill = $billModel->dump($log_id);
        if (empty($bill)) {
            return;
        }

        $deliveryId = (int) $bill['delivery_id'];
        if ($deliveryId <= 0) {
            return;
        }

        $packageModel = app::get('ome')->model('delivery_package');
        $packages = $packageModel->getList('*', array(
            'delivery_id' => $deliveryId,
            'logi_no' => $bill['logi_no'],
        ), 0, -1, 'package_id ASC');
        if (empty($packages)) {
            return;
        }

        $oid = isset($bill['order_id']) ? (int) $bill['order_id'] : 0;
        if ($oid > 0) {
            return;
        }
        foreach ($packages as $p) {
            if (empty($p['order_id'])) {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', trim((string) $p['order_id']), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                if ((int) $part > 0) {
                    return;
                }
            }
        }

        $orderId = $this->_resolveOrderIdForDeliveryBillFallback($bill, $deliveryId);
        if ($orderId <= 0) {
            return;
        }

        $billModel->update(array('order_id' => $orderId), array('log_id' => $log_id));
        $orderIdStr = (string) $orderId;
        foreach ($packages as $p) {
            if (empty($p['package_id'])) {
                continue;
            }
            $packageModel->update(
                array('order_id' => $orderIdStr),
                array('package_id' => $p['package_id'])
            );
        }
    }

    /**
     * @param array $bill
     * @param array $packages
     * @return int
     */
    private function _resolveOrderIdFromBillAndPackages($bill, array $packages)
    {
        $oid = isset($bill['order_id']) ? (int) $bill['order_id'] : 0;
        if ($oid > 0) {
            return $oid;
        }
        foreach ($packages as $p) {
            if (empty($p['order_id'])) {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', trim((string) $p['order_id']), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    return $id;
                }
            }
        }
        return 0;
    }

    /**
     * @param array $bill delivery_bill 行
     * @param int   $deliveryId
     * @return int
     */
    private function _resolveOrderIdForDeliveryBillFallback(array $bill, $deliveryId)
    {
        $orderId = 0;
        if (!empty($bill['order_bn'])) {
            $orderRow = app::get('ome')->model('orders')->db_dump(
                array('order_bn' => trim((string) $bill['order_bn'])),
                'order_id'
            );
            if (!empty($orderRow['order_id'])) {
                $orderId = (int) $orderRow['order_id'];
            }
        }
        if ($orderId <= 0) {
            $doRows = app::get('ome')->model('delivery_order')->getList(
                'order_id',
                array('delivery_id' => $deliveryId),
                0,
                1
            );
            if (!empty($doRows[0]['order_id'])) {
                $orderId = (int) $doRows[0]['order_id'];
            }
        }
        if ($orderId <= 0) {
            $detailRows = app::get('ome')->model('delivery_items_detail')->getList(
                'order_id',
                array('delivery_id' => $deliveryId),
                0,
                1
            );
            if (!empty($detailRows[0]['order_id'])) {
                $orderId = (int) $detailRows[0]['order_id'];
            }
        }
        return $orderId;
    }

    /**
     * @param array $packages
     * @return array<string,int> bn => 数量合计
     */
    private function _aggregatePackageBnNums(array $packages)
    {
        $agg = array();
        foreach ($packages as $p) {
            $bn = isset($p['bn']) ? trim((string) $p['bn']) : '';
            if ($bn === '') {
                continue;
            }
            if (!isset($agg[$bn])) {
                $agg[$bn] = 0;
            }
            $agg[$bn] += isset($p['number']) ? (int) $p['number'] : 0;
        }
        return $agg;
    }

    /**
     * @param array $bill
     * @param array $delivery
     * @param array $packages
     * @param array $agg
     * @return string
     */
    private function _buildDescForDeliveryBillSuggest($bill, $delivery, array $packages, array $agg)
    {
        $lines = array();
        $lines[] = '[包裹中心] 发货单：' . (isset($bill['delivery_bn']) ? $bill['delivery_bn'] : '');
        $lines[] = '运单号：' . (isset($bill['logi_no']) ? $bill['logi_no'] : '');
        $lines[] = '京东订单号：' . (isset($bill['package_bn']) ? $bill['package_bn'] : '');
        $lines[] = '物流公司：' . (isset($delivery['logi_name']) ? $delivery['logi_name'] : '');
        $lines[] = '明细行数：' . count($packages);
        $skuParts = array();
        foreach ($agg as $bn => $num) {
            $skuParts[] = $bn . '×' . $num;
        }
        $lines[] = '建议补发SKU：' . implode('；', $skuParts);
        return implode("\n", $lines);
    }

    /**
     * 拦截入库（__LANJIE_RUKU）退货单生成后，按退换货明细「申请数量」分摊到各包裹行，回写 apply_num（多包裹同 SKU 按 order_item_id / 本行 number 比例）
     * 同步：该发货单下 delivery_bill.status=6（已追回/拦截中）、有分摊数量的 delivery_package.status=1
     * 由 {@see ome_event_receive_delivery::_after_consign}、{@see console_reship::oneOrderRefundToLJRK} 在拦截单生成后调用。
     *
     * @param int    $delivery_id sdb_ome_delivery.delivery_id
     * @param string $logi_no     运单号；非空时仅更新该运单下的 delivery_package / delivery_bill
     */
    public function updateDeliveryPackageApplyNumFromLanJieReship($delivery_id, $logi_no = '')
    {
        $qtyMap = $this->_buildLanJieReshipQtyMap($delivery_id, 'apply');
        if (empty($qtyMap)) {
            return;
        }
        $logi_no = trim((string) $logi_no);
        $byPkg = $this->_lanjieAllocateQtyToDeliveryPackages($delivery_id, $qtyMap, $logi_no);
        $this->_persistLanJiePackageQtyColumn($byPkg, 'apply_num');
        $this->_markLanJieInterceptBillAndPackageStatus($delivery_id, $byPkg, $logi_no);
    }

    /**
     * WMS/PDA 退货入库完成（FINISH）且为拦截入库单时，按实收（良品+不良品）分摊到各包裹行，回写 return_num
     * 由 {@see console_receipt_reship::updateStatus} 在 status=FINISH 时调用。
     *
     * 实收数量来自 sdb_ome_reship_items 的 normal_num、defective_num（入库回写后才有值）。
     * 若未走 wms.reship.status_update / PDA 入库，二者多为 0，此时实收合计为 0：默认不写库，避免把 return_num 误更新为 0。
     * 本地联调可传 $useApplyWhenReceiptEmpty=true，在实收为 0 时用申请数量 num 分摊（仅测试，与真实入库一致时应为 false）。
     *
     * @param int  $delivery_id
     * @param bool $useApplyWhenReceiptEmpty 实收合计为 0 时是否改用申请数量 num 分摊（默认 false）
     */
    public function updateDeliveryPackageReturnNumFromLanJieReshipReceipt($delivery_id, $useApplyWhenReceiptEmpty = false)
    {
        $delivery_id = (int) $delivery_id;
        if ($delivery_id <= 0) {
            return;
        }
        $reshipRows = app::get('ome')->model('reship')->getList('reship_id,flag_type', array('delivery_id' => $delivery_id));
        foreach ($reshipRows as $r) {
            if (empty($r['flag_type']) || !((int) $r['flag_type'] & ome_reship_const::__LANJIE_RUKU)) {
                continue;
            }
            $this->updateDeliveryPackageReturnNumFromLanJieReshipReceiptByReshipId((int) $r['reship_id'], $useApplyWhenReceiptEmpty);
        }
    }

    /**
     * 拦截入库退货单：按本单 reship_items 与 delivery_package 匹配（delivery_id + reship.order_id 对应 package.order_id + order_item_id），
     * 回写 return_num、包裹 status（已补寄 reshipping 时仅写 return_num）；对应运单 delivery_bill status=7（已补寄 status=8 时不回退）
     *
     * @param int  $reship_id
     * @param bool $useApplyWhenReceiptEmpty 实收为 0 时是否用申请数量 num（仅测试/联调）
     */
    public function updateDeliveryPackageReturnNumFromLanJieReshipReceiptByReshipId($reship_id, $useApplyWhenReceiptEmpty = false)
    {
        $reship_id = (int) $reship_id;
        if ($reship_id <= 0) {
            return;
        }

        $reshipModel = app::get('ome')->model('reship');
        $reship = $reshipModel->dump(array('reship_id' => $reship_id), '*');
        if (empty($reship['delivery_id'])) {
            return;
        }
        if (empty($reship['flag_type']) || !((int) $reship['flag_type'] & ome_reship_const::__LANJIE_RUKU)) {
            return;
        }

        $delivery_id = (int) $reship['delivery_id'];
        $reship_order_id = isset($reship['order_id']) ? (int) $reship['order_id'] : 0;

        $itemsModel = app::get('ome')->model('reship_items');
        $items = $itemsModel->getList(
            'bn,order_item_id,normal_num,defective_num,num,return_type',
            array('reship_id' => $reship_id, 'return_type' => 'return')
        );
        if (empty($items)) {
            return;
        }

        $packageModel = app::get('ome')->model('delivery_package');
        $packages = $packageModel->getList('*', array('delivery_id' => $delivery_id), 0, -1, 'package_id ASC');
        if (empty($packages)) {
            return;
        }

        $agg = array();
        foreach ($items as $it) {
            $bn = isset($it['bn']) ? trim((string) $it['bn']) : '';
            if ($bn === '') {
                continue;
            }
            $oid = isset($it['order_item_id']) ? (int) $it['order_item_id'] : 0;
            if ($oid <= 0) {
                continue;
            }
            $received = (int) $it['normal_num'] + (int) $it['defective_num'];
            if ($received <= 0 && $useApplyWhenReceiptEmpty) {
                $received = isset($it['num']) ? (int) $it['num'] : 0;
            }
            if ($received <= 0) {
                continue;
            }
            $k = $bn . '|' . $oid;
            if (!isset($agg[$k])) {
                $agg[$k] = 0;
            }
            $agg[$k] += $received;
        }
        if (empty($agg)) {
            return;
        }

        $pkgById = array();
        foreach ($packages as $p) {
            if (!empty($p['package_id'])) {
                $pkgById[(int) $p['package_id']] = $p;
            }
        }

        $packageIdToQty = array();
        foreach ($agg as $k => $totalQty) {
            $pos = strpos($k, '|');
            if ($pos === false) {
                continue;
            }
            $bn = substr($k, 0, $pos);
            $order_item_id = (int) substr($k, $pos + 1);
            $matched = $this->_lanjieFindPackagesForReceiptItem($packages, $reship_order_id, $bn, $order_item_id);
            if (empty($matched)) {
                continue;
            }
            $alloc = $this->_lanjieAllocateReceiptQtyAcrossPackages($matched, (int) $totalQty);
            foreach ($alloc as $package_id => $qty) {
                if ($qty <= 0) {
                    continue;
                }
                $pid = (int) $package_id;
                if (!isset($packageIdToQty[$pid])) {
                    $packageIdToQty[$pid] = 0;
                }
                $packageIdToQty[$pid] += (int) $qty;
            }
        }

        if (empty($packageIdToQty)) {
            return;
        }

        $logiNos = array();
        foreach ($packageIdToQty as $pid => $qty) {
            if ($qty <= 0) {
                continue;
            }
            $this->_updateLanJiePackageReturnNumAfterReceipt($packageModel, (int) $pid, (int) $qty, $pkgById);
            if (!empty($pkgById[$pid]['logi_no'])) {
                $logiNos[trim((string) $pkgById[$pid]['logi_no'])] = true;
            }
        }

        $this->_updateLanJieDeliveryBillStatusAfterReceipt($delivery_id, $logiNos);
    }

    /**
     * 拦截入库回写包裹行：始终更新 return_num；已补寄(reshipping) 时不覆盖包裹 status
     *
     * @param ome_mdl_delivery_package $packageModel
     * @param int                      $packageId
     * @param int                      $qty
     * @param array<int,array>         $pkgById
     */
    private function _updateLanJiePackageReturnNumAfterReceipt($packageModel, $packageId, $qty, array $pkgById)
    {
        if ($packageId <= 0 || $qty <= 0) {
            return;
        }
        $updateData = array('return_num' => (int) $qty);
        $currentStatus = isset($pkgById[$packageId]['status']) ? trim((string) $pkgById[$packageId]['status']) : '';
        if ($currentStatus !== 'reshipping') {
            $updateData['status'] = 'intercept_success';
        }
        $packageModel->update($updateData, array('package_id' => $packageId));
    }

    /**
     * 拦截入库回写运单：status=7（追回成功）；已生成补发建议单的不回退
     *
     * @param int            $delivery_id
     * @param array<string,bool> $logiNos logi_no => true
     */
    private function _updateLanJieDeliveryBillStatusAfterReceipt($delivery_id, array $logiNos)
    {
        $delivery_id = (int) $delivery_id;
        if ($delivery_id <= 0) {
            return;
        }

        $billModel = app::get('ome')->model('delivery_bill');
        $filter = array('delivery_id' => $delivery_id);
        if (!empty($logiNos)) {
            $filter['logi_no|in'] = array_keys($logiNos);
        }
        $bills = $billModel->getList('log_id,status,suggest_reshipping_id', $filter, 0, -1);
        foreach ((array) $bills as $bill) {
            if (empty($bill['log_id'])) {
                continue;
            }
            if ($this->isDeliveryBillSuggestReshippingCreated($bill)) {
                continue;
            }
            $billModel->update(array('status' => '7'), array('log_id' => (int) $bill['log_id']));
        }
    }

    /**
     * delivery_package 行是否包含 reship 订单号及明细 order_item_id（order_id / order_item_id 可为逗号分隔）
     *
     * @param array $pkg
     * @param int   $reshipOrderId sdb_ome_reship.order_id
     * @param int   $orderItemId   sdb_ome_reship_items.order_item_id
     * @return bool
     */
    private function _deliveryPackageMatchesReshipOrderItem(array $pkg, $reshipOrderId, $orderItemId)
    {
        $reshipOrderId = (int) $reshipOrderId;
        $orderItemId = (int) $orderItemId;
        if ($orderItemId <= 0) {
            return false;
        }

        $orderField = isset($pkg['order_id']) ? trim((string) $pkg['order_id']) : '';
        if ($orderField !== '' && $reshipOrderId > 0) {
            $hitOrder = false;
            foreach (preg_split('/\s*,\s*/', $orderField, -1, PREG_SPLIT_NO_EMPTY) as $p) {
                if ((int) $p === $reshipOrderId) {
                    $hitOrder = true;
                    break;
                }
            }
            if (!$hitOrder) {
                return false;
            }
        }

        $oidStr = isset($pkg['order_item_id']) ? trim((string) $pkg['order_item_id']) : '';
        if ($oidStr === '') {
            return false;
        }
        foreach (preg_split('/\s*,\s*/', $oidStr, -1, PREG_SPLIT_NO_EMPTY) as $p) {
            if ((int) $p === $orderItemId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $packages      delivery_package 全量列表（同一 delivery_id）
     * @param int   $reshipOrderId
     * @param string $bn
     * @param int   $orderItemId
     * @return array 匹配的 package 行（含 package_id、number、logi_no）
     */
    private function _lanjieFindPackagesForReceiptItem(array $packages, $reshipOrderId, $bn, $orderItemId)
    {
        $bn = trim((string) $bn);
        $out = array();
        foreach ($packages as $pkg) {
            if (!isset($pkg['bn']) || trim((string) $pkg['bn']) !== $bn) {
                continue;
            }
            if (!$this->_deliveryPackageMatchesReshipOrderItem($pkg, $reshipOrderId, $orderItemId)) {
                continue;
            }
            $out[] = $pkg;
        }

        return $out;
    }

    /**
     * 多行同一 bn+order_item_id 时按 number 比例分摊实收数量
     *
     * @param array $matchedPackages
     * @param int   $totalQty
     * @return array<int,int> package_id => qty
     */
    private function _lanjieAllocateReceiptQtyAcrossPackages(array $matchedPackages, $totalQty)
    {
        $totalQty = (int) $totalQty;
        if ($totalQty <= 0 || empty($matchedPackages)) {
            return array();
        }
        $n = count($matchedPackages);
        if ($n === 1) {
            $pkg = $matchedPackages[0];
            $pid = (int) $pkg['package_id'];
            $cap = isset($pkg['number']) ? (int) $pkg['number'] : 0;
            $q = $cap > 0 ? min($totalQty, $cap) : $totalQty;

            return array($pid => $q);
        }

        $sumNum = 0;
        foreach ($matchedPackages as $pkg) {
            $sumNum += isset($pkg['number']) ? (int) $pkg['number'] : 0;
        }
        $out = array();
        $used = 0;
        $last = $n - 1;
        foreach ($matchedPackages as $idx => $pkg) {
            $pid = (int) $pkg['package_id'];
            $pn = isset($pkg['number']) ? (int) $pkg['number'] : 0;
            if ($idx === $last) {
                $alloc = max(0, $totalQty - $used);
            } else {
                $alloc = $sumNum > 0 ? (int) round($totalQty * $pn / $sumNum) : 0;
                $used += $alloc;
            }
            if ($pn > 0 && $alloc > $pn) {
                $alloc = $pn;
            }
            $out[$pid] = max(0, (int) $alloc);
        }

        return $out;
    }

    /**
     * @param array $qtyMap
     * @return int
     */
    private function _lanjieQtyMapTotalQty(array $qtyMap)
    {
        $s = 0;
        foreach ($qtyMap as $v) {
            $s += (int) $v;
        }
        return $s;
    }

    /**
     * @param int    $delivery_id
     * @param string $mode apply：reship_items.num；received：normal_num+defective_num
     * @return array<string,int> key 为 bn|order_item_id
     */
    private function _buildLanJieReshipQtyMap($delivery_id, $mode)
    {
        $delivery_id = (int) $delivery_id;
        if ($delivery_id <= 0) {
            return array();
        }

        $reshipModel = app::get('ome')->model('reship');
        $reshipRows = $reshipModel->getList('reship_id,flag_type', array('delivery_id' => $delivery_id));
        $ljReshipIds = array();
        foreach ($reshipRows as $row) {
            if (!empty($row['flag_type']) && ((int) $row['flag_type'] & ome_reship_const::__LANJIE_RUKU)) {
                $ljReshipIds[] = (int) $row['reship_id'];
            }
        }
        if (empty($ljReshipIds)) {
            return array();
        }

        $itemsModel = app::get('ome')->model('reship_items');
        if ($mode === 'received') {
            $items = $itemsModel->getList('bn,normal_num,defective_num,order_item_id,return_type', array('reship_id' => $ljReshipIds));
        } else {
            $items = $itemsModel->getList('bn,num,order_item_id,return_type', array('reship_id' => $ljReshipIds));
        }

        $qtyMap = array();
        foreach ($items as $it) {
            if (isset($it['return_type']) && $it['return_type'] !== 'return') {
                continue;
            }
            $bn = isset($it['bn']) ? trim((string) $it['bn']) : '';
            if ($bn === '') {
                continue;
            }
            $oid = isset($it['order_item_id']) ? (int) $it['order_item_id'] : 0;
            $k = $bn . '|' . $oid;
            if (!isset($qtyMap[$k])) {
                $qtyMap[$k] = 0;
            }
            if ($mode === 'received') {
                $qtyMap[$k] += (int) $it['normal_num'] + (int) $it['defective_num'];
            } else {
                $qtyMap[$k] += isset($it['num']) ? (int) $it['num'] : 0;
            }
        }

        return $qtyMap;
    }

    /**
     * 将 bn|order_item_id 数量分摊到 delivery_package 各行（与历史 return_num 逻辑一致）
     *
     * @param int    $delivery_id
     * @param array  $qtyMap
     * @param string $logi_no 非空时仅匹配该运单的包裹行
     * @return array<int,int> package_id => 数量
     */
    private function _lanjieAllocateQtyToDeliveryPackages($delivery_id, array $qtyMap, $logi_no = '')
    {
        $delivery_id = (int) $delivery_id;
        $packageModel = app::get('ome')->model('delivery_package');
        $pkgFilter = array('delivery_id' => $delivery_id);
        $logi_no = trim((string) $logi_no);
        if ($logi_no !== '') {
            $pkgFilter['logi_no'] = $logi_no;
        }
        $packages = $packageModel->getList('*', $pkgFilter, 0, -1, 'package_id ASC');
        if (empty($packages)) {
            return array();
        }

        $sumQtyForBn = array();
        foreach ($qtyMap as $k => $v) {
            $pos = strpos($k, '|');
            if ($pos === false) {
                continue;
            }
            $bnKey = substr($k, 0, $pos);
            if (!isset($sumQtyForBn[$bnKey])) {
                $sumQtyForBn[$bnKey] = 0;
            }
            $sumQtyForBn[$bnKey] += $v;
        }

        $emptyOidGroups = array();
        foreach ($packages as $idx => $pkg) {
            $bn = isset($pkg['bn']) ? trim((string) $pkg['bn']) : '';
            if ($bn === '') {
                continue;
            }
            $oidStr = isset($pkg['order_item_id']) ? trim((string) $pkg['order_item_id']) : '';
            if ($oidStr === '') {
                if (!isset($emptyOidGroups[$bn])) {
                    $emptyOidGroups[$bn] = array();
                }
                $emptyOidGroups[$bn][] = $idx;
            }
        }

        $propByPkgId = array();
        foreach ($emptyOidGroups as $bn => $idxList) {
            $sumBn = isset($sumQtyForBn[$bn]) ? (int) $sumQtyForBn[$bn] : 0;
            if ($sumBn <= 0) {
                continue;
            }
            $cnt = count($idxList);
            if ($cnt === 1) {
                $i = $idxList[0];
                $pid = $packages[$i]['package_id'];
                $pn = isset($packages[$i]['number']) ? (int) $packages[$i]['number'] : 0;
                $propByPkgId[$pid] = $pn > 0 ? min($pn, $sumBn) : 0;
                continue;
            }
            $totalNumbers = 0;
            foreach ($idxList as $i) {
                $totalNumbers += isset($packages[$i]['number']) ? (int) $packages[$i]['number'] : 0;
            }
            if ($totalNumbers <= 0) {
                continue;
            }
            $used = 0;
            $last = $cnt - 1;
            foreach ($idxList as $j => $i) {
                $pid = $packages[$i]['package_id'];
                $pn = isset($packages[$i]['number']) ? (int) $packages[$i]['number'] : 0;
                if ($j === $last) {
                    $alloc = $sumBn - $used;
                } else {
                    $alloc = (int) round($sumBn * $pn / $totalNumbers);
                    $used += $alloc;
                }
                $propByPkgId[$pid] = $pn > 0 ? min($pn, max(0, $alloc)) : 0;
            }
        }

        $out = array();
        foreach ($packages as $pkg) {
            $bn = isset($pkg['bn']) ? trim((string) $pkg['bn']) : '';
            if ($bn === '') {
                continue;
            }
            $maxNum = isset($pkg['number']) ? (int) $pkg['number'] : 0;
            $oidStr = isset($pkg['order_item_id']) ? trim((string) $pkg['order_item_id']) : '';
            $matched = 0;

            if ($oidStr !== '') {
                foreach (preg_split('/\s*,\s*/', $oidStr, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    $oid = (int) $part;
                    $k = $bn . '|' . $oid;
                    if (isset($qtyMap[$k])) {
                        $matched += $qtyMap[$k];
                    }
                }
            } else {
                $pid = $pkg['package_id'];
                if (isset($propByPkgId[$pid])) {
                    $matched = (int) $propByPkgId[$pid];
                }
            }

            $qty = $matched;
            if ($maxNum > 0 && $qty > $maxNum) {
                $qty = $maxNum;
            }
            if ($qty < 0) {
                $qty = 0;
            }
            $out[(int) $pkg['package_id']] = $qty;
        }

        return $out;
    }

    /**
     * @param array<int,int> $packageIdToQty
     * @param string         $column apply_num|return_num
     */
    private function _persistLanJiePackageQtyColumn(array $packageIdToQty, $column)
    {
        if ($column !== 'apply_num' && $column !== 'return_num') {
            return;
        }
        $packageModel = app::get('ome')->model('delivery_package');
        foreach ($packageIdToQty as $package_id => $qty) {
            $packageModel->update(
                array($column => (int) $qty),
                array('package_id' => (int) $package_id)
            );
        }
    }

    /**
     * 拦截分配 apply_num 后：运单包裹单进入拦截中（bill status=6），包裹行 status=1（仅分摊数量大于 0 的 package_id）
     *
     * @param int              $delivery_id
     * @param array<int,int>   $packageIdToQty package_id => 分摊数量
     * @param string           $logi_no 非空时仅更新该运单对应的 delivery_bill
     */
    private function _markLanJieInterceptBillAndPackageStatus($delivery_id, array $packageIdToQty, $logi_no = '')
    {
        $delivery_id = (int) $delivery_id;
        if ($delivery_id <= 0 || empty($packageIdToQty)) {
            return;
        }
        $hasPositive = false;
        foreach ($packageIdToQty as $qty) {
            if ((int) $qty > 0) {
                $hasPositive = true;
                break;
            }
        }
        if (!$hasPositive) {
            return;
        }

        $billModel = app::get('ome')->model('delivery_bill');
        $billFilter = array('delivery_id' => $delivery_id);
        $logi_no = trim((string) $logi_no);
        if ($logi_no !== '') {
            $billFilter['logi_no'] = $logi_no;
        }
        $billModel->update(array('status' => '6'), $billFilter);

        $packageModel = app::get('ome')->model('delivery_package');
        foreach ($packageIdToQty as $package_id => $qty) {
            if ((int) $qty <= 0) {
                continue;
            }
            $packageModel->update(
                array('status' => 'intercepting'),
                array('package_id' => (int) $package_id)
            );
        }
    }

    /**
     * 包裹中心补发订单：按子单 oid 从原订单复制 order_coupon 优惠明细
     *
     * @param int   $originalOrderId  原订单 order_id
     * @param int   $newOrderId       补发订单 order_id
     * @param array $oidReshipNums    oid => 补发数量（同一 oid 多行时累加）
     * @return int 成功写入条数
     */
    public function copyOriginalOrderPromotionsByOids($originalOrderId, $newOrderId, array $oidReshipNums)
    {
        $originalOrderId = (int) $originalOrderId;
        $newOrderId = (int) $newOrderId;
        if ($originalOrderId <= 0 || $newOrderId <= 0 || empty($oidReshipNums)) {
            return 0;
        }

        $oids = array();
        foreach ($oidReshipNums as $oid => $nums) {
            $oid = $this->_normalizePlatformOid($oid);
            $nums = (int) $nums;
            if ($oid === '' || $nums <= 0) {
                continue;
            }
            $oids[$oid] = $nums;
        }
        if (empty($oids)) {
            return 0;
        }

        $origQtyByOid = $this->_loadOriginalObjectQtyByOid($originalOrderId, array_keys($oids));
        $ratioByOid = array();
        foreach ($oids as $oid => $reshipNums) {
            $origQty = isset($origQtyByOid[$oid]) ? (float) $origQtyByOid[$oid] : 0.0;
            $ratioByOid[$oid] = ($origQty > 0) ? ($reshipNums / $origQty) : 1.0;
        }

        return $this->_copyOrderCouponRowsByOids($originalOrderId, $newOrderId, array_keys($oids), $ratioByOid);
    }

    /**
     * @param int   $originalOrderId
     * @param array $oids
     * @return array<string,float> oid => quantity
     */
    private function _loadOriginalObjectQtyByOid($originalOrderId, array $oids)
    {
        $out = array();
        $originalOrderId = (int) $originalOrderId;
        if ($originalOrderId <= 0 || empty($oids)) {
            return $out;
        }

        $ooModel = app::get('ome')->model('order_objects');
        foreach ($oids as $oid) {
            $oid = $this->_normalizePlatformOid($oid);
            if ($oid === '') {
                continue;
            }
            $row = $ooModel->db_dump(
                array('order_id' => $originalOrderId, 'oid' => $oid),
                'quantity'
            );
            if (!empty($row) && isset($row['quantity'])) {
                $out[$oid] = (float) $row['quantity'];
            }
        }

        return $out;
    }

    /**
     * @param int          $originalOrderId
     * @param int          $newOrderId
     * @param string[]     $oids
     * @param array<string,float> $ratioByOid
     * @return int
     */
    private function _copyOrderCouponRowsByOids($originalOrderId, $newOrderId, array $oids, array $ratioByOid)
    {
        $couponModel = app::get('ome')->model('order_coupon');
        $rows = $couponModel->getList('*', array(
            'order_id' => (int) $originalOrderId,
            'oid|in'   => $oids,
        ));
        if (empty($rows)) {
            return 0;
        }

        $inserted = 0;
        foreach ($rows as $row) {
            $oid = $this->_normalizePlatformOid(isset($row['oid']) ? $row['oid'] : '');
            if ($oid === '' || !isset($ratioByOid[$oid])) {
                continue;
            }
            $newRow = $row;
            unset($newRow['id']);
            $newRow['order_id'] = (int) $newOrderId;
            $newRow['source'] = 'local';
            $this->_scaleCouponQuantityAmountRow($newRow, (float) $ratioByOid[$oid], array('num', 'amount', 'total_amount'));
            if ($couponModel->insert($newRow)) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * @param array  $row     引用传递，原地修改
     * @param float  $ratio
     * @param string[] $moneyFields
     */
    private function _scaleCouponQuantityAmountRow(array &$row, $ratio, array $moneyFields)
    {
        if ($ratio >= 0.9999 && $ratio <= 1.0001) {
            return;
        }
        if (isset($row['num']) && is_numeric($row['num'])) {
            $row['num'] = max(1, (int) round((float) $row['num'] * $ratio));
        }
        foreach ($moneyFields as $field) {
            if (!isset($row[$field]) || !is_numeric($row[$field])) {
                continue;
            }
            $row[$field] = round((float) $row[$field] * $ratio, 3);
        }
        if (isset($row['num'], $row['total_amount']) && (int) $row['num'] > 0 && isset($row['amount'])) {
            $row['amount'] = round((float) $row['total_amount'] / (int) $row['num'], 3);
        }
    }
}
