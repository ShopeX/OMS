<?php

/**
 * 淘宝加急发货订单状态构建类
 */
class ome_order_urgent
{
    /**
     * 构建加急订单状态信息
     *
     * 根据订单当前的流转状态和发货单信息，映射为平台要求的加急发货状态码，
     * 并附带物流单号、异常原因、订单明细等扩展信息返回给上游调用方。
     *
     * @param int|string $orderId 订单ID
     * @return array 加急状态信息，包含 orderStatus、status_update_time 等字段；订单不存在时返回空数组
     */
    public function buildUrgentOrderStatus($orderId)
    {
        // 查询订单主表信息
        $order = app::get('ome')->model('orders')->dump($orderId, 'pay_status,order_id,order_bn,process_status,status,ship_status,sync,sync_fail_type,abnormal,pause,logi_no,source,createway,shop_id,shop_type,last_modified,createtime');
        if (!$order) {
            return [];
        }

        // 获取订单关联发货单列表，并使用最早创建的主发货单作为订单级状态时间参考
        $deliveryRows = app::get('ome')->model('delivery')->getDeliversByOrderId($orderId);
        $delivery     = $this->getPrimaryUrgentDelivery($deliveryRows);

        $details = $this->buildUrgentOrderDetails($orderId, $deliveryRows, $order);
        $mainStatusData = $this->buildUrgentMainStatus($order, $details, $delivery);

        return [
            'orderStatus'        => $mainStatusData['orderStatus'],
            'status_update_time' => $mainStatusData['status_update_time'],
            'tracking_number'    => $mainStatusData['tracking_number'],
            'exception_reason'   => $mainStatusData['exception_reason'],
            'order_details'      => $details,
            'is_split'           => $this->isSplitOrder($order),
        ];
    }

    /**
     * 构建加急发货回告 DTO。
     *
     * @param int|string $orderId
     * @param string $orderBn
     * @return array
     */
    public function buildUrgentOrderReportDto($orderId, $orderBn = '')
    {
        $statusPayload = $this->buildUrgentOrderStatus($orderId);
        if (empty($statusPayload['orderStatus'])) {
            return [];
        }

        if (!$orderBn) {
            $order = app::get('ome')->model('orders')->dump($orderId, 'order_bn');
            $orderBn = $order['order_bn'] ?: '';
        }

        return $this->formatUrgentOrderReportDto($statusPayload, $orderBn);
    }

    /**
     * 构建矩阵出站参数 erp_synergy_callback_req_dto。
     *
     * @param int|string $orderId
     * @param string $orderBn
     * @return array
     */
    public function buildUrgentOrderReportParams($orderId, $orderBn = '')
    {
        return $this->wrapUrgentOrderDtoPayload(
            $this->buildUrgentOrderReportDto($orderId, $orderBn),
            'erp_synergy_callback_req_dto'
        );
    }

    /**
     * 构建加急发货入站响应参数 urgent_delivery_notify_response。
     *
     * @param int|string $orderId
     * @param string $orderBn
     * @return array
     */
    public function buildUrgentNotifyResponseParams($orderId, $orderBn = '')
    {
        return $this->wrapUrgentOrderDtoPayload(
            $this->buildUrgentOrderReportDto($orderId, $orderBn),
            'urgent_delivery_notify_response'
        );
    }

    /**
     * 将 DTO 内容包装为指定字段名的 JSON 字符串参数。
     *
     * @param array $payload
     * @param string $fieldName
     * @return array
     */
    protected function wrapUrgentOrderDtoPayload($payload, $fieldName)
    {
        if (!$payload) {
            return [];
        }

        $dtoJson = $this->encodeUrgentOrderDtoJson($payload);
        if ($dtoJson === '') {
            return [];
        }

        return [
            $fieldName => $dtoJson,
        ];
    }

    /**
     * 编码加急回告 DTO，入站响应与主动回告共用。
     *
     * @param array $payload
     * @return string
     */
    public function encodeUrgentOrderDtoJson($payload)
    {
        $dtoJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($dtoJson !== false) {
            return $dtoJson;
        }

        array_walk_recursive($payload, function (&$value) {
            if (is_string($value)) {
                $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
            }
        });

        $dtoJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return $dtoJson === false ? '' : $dtoJson;
    }

    /**
     * 将状态聚合结果格式化为 erp_synergy_callback_req_dto 内容。
     *
     * @param array $statusPayload
     * @param string $orderBn
     * @return array
     */
    public function formatUrgentOrderReportDto($statusPayload, $orderBn)
    {
        $payload = [
            'trade_order_code'   => (string)$orderBn,
            'order_status'       => (string)$statusPayload['orderStatus'],
            'status_update_time' => $statusPayload['status_update_time'] ?: date('Y-m-d H:i:s'),
            'tracking_number'    => '',
            'exception_reason'   => '',
            'extend_fields'      => '{}',
            'order_details'      => array_values((array)($statusPayload['order_details'] ?: [])),
            'is_split'           => (bool)($statusPayload['is_split'] ?? false),
        ];

        if (!empty($statusPayload['tracking_number'])) {
            $payload['tracking_number'] = (string)$statusPayload['tracking_number'];
        }
        if (!empty($statusPayload['exception_reason'])) {
            $payload['exception_reason'] = (string)$statusPayload['exception_reason'];
        }

        return $payload;
    }

    /**
     * 判断订单是否拆单，逻辑与发货同步 _is_split_order 保持一致。
     *
     * @param array $order
     * @return bool
     */
    public function isSplitOrder($order)
    {
        if ($order['ship_status'] == '2'
            || $order['process_status'] == 'remain_cancel'
            || $order['process_status'] == 'splitting') {
            return true;
        }

        return count($this->getOrderDeliveryIds($order['order_id'])) > 1;
    }

    /**
     * 获取订单有效主发货单列表。
     *
     * @param int|string $orderId
     * @return array
     */
    protected function getOrderDeliveryIds($orderId)
    {
        $sql = "SELECT d.delivery_id,d.status,d.delivery_time
                FROM sdb_ome_delivery_order AS dord
                LEFT JOIN sdb_ome_delivery AS d
                ON(dord.delivery_id=d.delivery_id)
                WHERE dord.order_id='" . intval($orderId) . "'
                AND d.parent_id='0'
                AND d.disabled='false'
                AND d.status IN('succ','progress','ready')";
        $rows = kernel::database()->select($sql);

        $deliveryIds = [];
        foreach ((array)$rows as $row) {
            $deliveryIds[$row['delivery_id']] = $row;
        }

        return $deliveryIds;
    }

    /**
     * 解析加急状态的更新时间
     *
     * 按优先级从发货单和订单中取最合适的时间戳，格式化为 Y-m-d H:i:s 返回。
     * 优先级：发货时间 > 发货单修改时间 > 订单修改时间 > 订单创建时间
     *
     * @param array $order    订单信息
     * @param array $delivery 发货单信息
     * @return string 格式化后的时间字符串
     */
    protected function resolveUrgentStatusUpdateTime($order, $delivery)
    {
        // 按优先级取时间戳
        $timestamp = 0;
        if (!empty($delivery['delivery_time'])) {
            $timestamp = $delivery['delivery_time'];
        } elseif (!empty($delivery['last_modified'])) {
            $timestamp = $delivery['last_modified'];
        } elseif (!empty($order['last_modified'])) {
            $timestamp = $order['last_modified'];
        } elseif (!empty($order['createtime'])) {
            $timestamp = $order['createtime'];
        }

        // 无有效时间戳时返回当前时间
        if (!$timestamp) {
            return date('Y-m-d H:i:s');
        }
        // 数字型时间戳转格式化字符串
        if (is_numeric($timestamp)) {
            return date('Y-m-d H:i:s', intval($timestamp));
        }

        return (string)$timestamp;
    }

    /**
     * 构建加急订单的商品明细列表
     *
     * 优先使用 order_items 表（含 SKU 信息）拼装明细；
     * 若 order_items 无有效数据则降级使用 order_objects 表。
     *
     * @param int|string $orderId 订单ID
     * @return array 订单明细数组，每项包含 sub_trade_order_code、item_id、sku_id、quantity
     */
    protected function buildUrgentOrderDetails($orderId, $deliveryRows = [], $order = [])
    {
        $details       = [];
        $deliveryRows  = (array)$deliveryRows;
        // 查询订单货品和订单商品行
        $objectRows    = app::get('ome')->model('order_objects')->getList('obj_id,oid,shop_goods_id,quantity', ['order_id' => $orderId, 'delete' => 'false']);
        $orderItemRows = app::get('ome')->model('order_items')->getList('item_id,obj_id,shop_goods_id,shop_product_id,nums,item_type', ['order_id' => $orderId, 'delete' => 'false']);
        $deliveryDetailRows = app::get('ome')->model('delivery_items_detail')->getList(
            'delivery_id,order_obj_id,order_item_id,oid,number',
            ['order_id' => $orderId]
        );

        // 构建 obj_id => object 映射，便于关联查找
        $objectMap = [];
        foreach ((array)$objectRows as $objectRow) {
            $objectMap[$objectRow['obj_id']] = $objectRow;
        }

        // 统计每个 obj_id 下的 order_items 数量，用于判断 obj 级映射是否会歧义。
        $objectItemCountMap = [];
        foreach ((array)$orderItemRows as $itemRow) {
            if (empty($itemRow['obj_id'])) {
                continue;
            }
            if (!isset($objectItemCountMap[$itemRow['obj_id']])) {
                $objectItemCountMap[$itemRow['obj_id']] = 0;
            }
            $objectItemCountMap[$itemRow['obj_id']]++;
        }

        // 构建发货单映射与订单货品 -> 发货单映射，便于按真实发货单状态生成明细状态
        $deliveryMap = [];
        foreach ($deliveryRows as $deliveryRow) {
            $deliveryMap[$deliveryRow['delivery_id']] = $deliveryRow;
        }

        $detailDeliveryMap = [];
        foreach ((array)$deliveryDetailRows as $deliveryDetailRow) {
            if (!empty($deliveryDetailRow['order_item_id'])) {
                $detailDeliveryMap['item_' . $deliveryDetailRow['order_item_id']] = $deliveryMap[$deliveryDetailRow['delivery_id']];
            }
            if (!empty($deliveryDetailRow['order_obj_id'])) {
                $detailDeliveryMap['obj_' . $deliveryDetailRow['order_obj_id']] = $deliveryMap[$deliveryDetailRow['delivery_id']];
            }
            if (!empty($deliveryDetailRow['oid'])) {
                $detailDeliveryMap['oid_' . $deliveryDetailRow['oid']] = $deliveryMap[$deliveryDetailRow['delivery_id']];
            }
        }

        // 优先从 order_items 构建明细（包含 SKU 维度信息）
        foreach ((array)$orderItemRows as $itemRow) {
            $objectRow = $objectMap[$itemRow['obj_id']] ?: [];
            if (!$objectRow) {
                continue;
            }
            // 过滤非商品类型且无商品编码的行
            if ($itemRow['item_type'] !== 'product' && !$itemRow['shop_product_id'] && !$itemRow['shop_goods_id']) {
                continue;
            }

            if (empty($objectRow['oid'])){
                continue;
            }

            $deliveryRow = [];
            if (!empty($detailDeliveryMap['item_' . $itemRow['item_id']])) {
                $deliveryRow = $detailDeliveryMap['item_' . $itemRow['item_id']];
            } elseif (!empty($objectRow['oid']) && !empty($detailDeliveryMap['oid_' . $objectRow['oid']])) {
                $deliveryRow = $detailDeliveryMap['oid_' . $objectRow['oid']];
            } elseif (($objectItemCountMap[$itemRow['obj_id']] ?: 0) === 1 && !empty($detailDeliveryMap['obj_' . $itemRow['obj_id']])) {
                // 仅当该 obj_id 下只有一个明细时，才允许退化到 obj 级发货单映射，避免把别的明细发货单借给当前明细。
                $deliveryRow = $detailDeliveryMap['obj_' . $itemRow['obj_id']];
            }
            $detailStatus = $this->buildUrgentDetailStatus($deliveryRow, $order);
            $trackingNumber = $this->resolveDetailTrackingNumber($deliveryRow, $detailStatus['order_status']);

            $details[] = [
                'sub_trade_order_code'   => (string)$objectRow['oid'],
                'item_id'                => (string)($itemRow['shop_goods_id'] ?: $objectRow['shop_goods_id']),
                'sku_id'                 => (string)$itemRow['shop_product_id'],
                'quantity'               => intval($itemRow['nums']),
                'sub_trade_order_status' => $detailStatus['order_status'],
                'status_update_time'     => $detailStatus['status_update_time'],
                'tracking_number'        => $trackingNumber,
            ];
        }

        if ($details) {
            return $details;
        }

        // 降级方案：直接使用 order_objects 构建明细（无 SKU 信息）
        foreach ((array)$objectRows as $objectRow) {
            $deliveryRow = [];
            if (!empty($objectRow['oid']) && !empty($detailDeliveryMap['oid_' . $objectRow['oid']])) {
                $deliveryRow = $detailDeliveryMap['oid_' . $objectRow['oid']];
            } elseif (!empty($detailDeliveryMap['obj_' . $objectRow['obj_id']])) {
                $deliveryRow = $detailDeliveryMap['obj_' . $objectRow['obj_id']];
            }
            $detailStatus = $this->buildUrgentDetailStatus($deliveryRow, $order);
            $trackingNumber = $this->resolveDetailTrackingNumber($deliveryRow, $detailStatus['order_status']);
            $details[] = [
                'sub_trade_order_code'   => (string)$objectRow['oid'],
                'item_id'                => (string)$objectRow['shop_goods_id'],
                'sku_id'                 => '',
                'quantity'               => intval($objectRow['quantity']),
                'sub_trade_order_status' => $detailStatus['order_status'],
                'status_update_time'     => $detailStatus['status_update_time'],
                'tracking_number'        => $trackingNumber,
            ];
        }

        return $details;
    }

    /**
     * 选取订单关联的主发货单，优先取最早创建的有效发货单。
     *
     * @param array $deliveryRows
     * @return array
     */
    protected function getPrimaryUrgentDelivery($deliveryRows)
    {
        $primaryDelivery = [];
        foreach ((array)$deliveryRows as $deliveryRow) {
            if (!$primaryDelivery) {
                $primaryDelivery = $deliveryRow;
                continue;
            }

            $primaryCreateTime = intval($primaryDelivery['create_time']);
            $currentCreateTime = intval($deliveryRow['create_time']);
            if ($currentCreateTime && (!$primaryCreateTime || $currentCreateTime < $primaryCreateTime)) {
                $primaryDelivery = $deliveryRow;
            }
        }

        return $primaryDelivery;
    }

    /**
     * 按发货单和发货回写日志构建订单明细状态。
     *
     * 规则：
     * - 订单取消 → CANCELLED
     * - 无发货单 → ORDER_CREATED
     * - 明细状态只认当前明细自身映射到的发货单，不借用同订单其它明细的发货单
     * - 有发货单且未发货完成 → WAREHOUSE_PROCESSING
     * - 有发货单且已发货完成时，再用 shipment_log 区分：
     *   - shipment_log 失败 → WAREHOUSE_CONSIGN_FAIL
     *   - shipment_log 成功 → SHIPPED
     *   - 无明确回写结果 → WAREHOUSE_PROCESSING
     *
     * @param array $delivery
     * @param array $order
     * @return array
     */
    protected function buildUrgentDetailStatus($delivery, $order)
    {
        $detailStatus = 'ORDER_CREATED';
        $detailTime = $this->resolveUrgentDetailStatusUpdateTime($delivery, $order);

        if ($order['status'] == 'dead' || $order['process_status'] == 'cancel' || $order['pay_status'] == '5') {
            $detailStatus = 'CANCELLED';
        } elseif ($delivery) {
            if ($delivery['status'] != 'succ' && $delivery['process'] != 'true') {
                $detailStatus = 'WAREHOUSE_PROCESSING';
            } else {
                $shipmentLog = $this->getLatestShipmentLog($order['order_bn'], $delivery['logi_no']);
                if ($shipmentLog['status'] == 'fail') {
                    $detailStatus = 'WAREHOUSE_CONSIGN_FAIL';
                    $detailTime = $this->formatUrgentTimestamp($shipmentLog['updateTime']) ?: $detailTime;
                } elseif ($shipmentLog['status'] == 'succ') {
                    $detailStatus = 'SHIPPED';
                    $detailTime = $this->formatUrgentTimestamp($shipmentLog['updateTime']) ?: $detailTime;
                } else {
                    $detailStatus = 'WAREHOUSE_PROCESSING';
                }
            }
        }

        return [
            'order_status'       => $detailStatus,
            'status_update_time' => $detailTime,
        ];
    }

    /**
     * 解析订单明细状态更新时间。
     *
     * 若存在发货单则取发货单创建时间，否则取订单创建时间。
     *
     * @param array $delivery
     * @param array $order
     * @return string
     */
    protected function resolveUrgentDetailStatusUpdateTime($delivery, $order)
    {
        $timestamp = $delivery['create_time'] ?: $order['createtime'];
        return $this->formatUrgentTimestamp($timestamp);
    }

    /**
     * 按子交易单号聚合明细，同一子单取优先级最高的状态。
     *
     * @param array $details
     * @return array
     */
    protected function aggregateDetailsBySubTradeOrder($details)
    {
        $priorityMap = [
            'WAREHOUSE_CONSIGN_FAIL' => 5,
            'SHIPPED'                => 4,
            'CANCELLED'              => 3,
            'WAREHOUSE_PROCESSING'   => 2,
            'ORDER_CREATED'          => 1,
        ];

        $grouped = [];
        foreach ((array)$details as $detail) {
            $oid = (string)$detail['sub_trade_order_code'];
            if ($oid === '') {
                continue;
            }
            if (!isset($grouped[$oid])) {
                $grouped[$oid] = $detail;
                continue;
            }

            $currentPriority = $priorityMap[$grouped[$oid]['sub_trade_order_status']] ?? 0;
            $newPriority     = $priorityMap[$detail['sub_trade_order_status']] ?? 0;
            if ($newPriority > $currentPriority) {
                $grouped[$oid] = $detail;
            }
        }

        return array_values($grouped);
    }

    /**
     * 解析子单运单号：SHIPPED 时取映射发货单 delivery.logi_no，主推与入站响应共用。
     *
     * @param array $deliveryRow
     * @param string $detailStatus
     * @return string
     */
    protected function resolveDetailTrackingNumber($deliveryRow, $detailStatus)
    {
        if ($detailStatus !== 'SHIPPED' || empty($deliveryRow['logi_no'])) {
            return '';
        }

        return (string)$deliveryRow['logi_no'];
    }

    /**
     * 从已发货子单中选取主单运单号：优先最早 status_update_time，时间相同按子单号升序。
     * 主单 tracking_number 与 order_details[].tracking_number 均来自 delivery.logi_no。
     *
     * @param array $subOrders
     * @return string
     */
    protected function resolveMainTrackingFromShippedSubOrders($subOrders)
    {
        $shippedSubs = [];
        foreach ((array)$subOrders as $subOrder) {
            if ($subOrder['sub_trade_order_status'] !== 'SHIPPED') {
                continue;
            }
            if (empty($subOrder['tracking_number'])) {
                continue;
            }
            $shippedSubs[] = $subOrder;
        }

        if (!$shippedSubs) {
            return '';
        }

        usort($shippedSubs, function ($left, $right) {
            $timeCompare = strcmp((string)$left['status_update_time'], (string)$right['status_update_time']);
            if ($timeCompare !== 0) {
                return $timeCompare;
            }

            return strcmp((string)$left['sub_trade_order_code'], (string)$right['sub_trade_order_code']);
        });

        return (string)$shippedSubs[0]['tracking_number'];
    }

    /**
     * 根据子单状态聚合主结构状态与时间。
     *
     * 规则：
     * - 任一子单 WAREHOUSE_CONSIGN_FAIL → 主单异常态，不返回运单号
     * - 否则按优先级 SHIPPED > CANCELLED > WAREHOUSE_PROCESSING > ORDER_CREATED
     * - 主单 SHIPPED 时，运单号取已发货子单对应发货单 logi_no；多个 SHIPPED 时取最早时间，时间相同按子单号升序
     * - 主结构 status_update_time 取命中状态对应子单时间
     *
     * @param array $order
     * @param array $details
     * @param array $delivery
     * @return array
     */
    protected function buildUrgentMainStatus($order, $details, $delivery = [])
    {
        $defaultStatus = 'ORDER_CREATED';
        $defaultTime   = $this->resolveUrgentStatusUpdateTime($order, $delivery);

        if (!$details) {
            return [
                'orderStatus'        => $defaultStatus,
                'status_update_time' => $defaultTime,
                'tracking_number'    => '',
                'exception_reason'   => '',
            ];
        }

        $subOrders = $this->aggregateDetailsBySubTradeOrder($details);

        $failSubOrder = null;
        foreach ($subOrders as $subOrder) {
            if ($subOrder['sub_trade_order_status'] !== 'WAREHOUSE_CONSIGN_FAIL') {
                continue;
            }
            if (!$failSubOrder || strcmp((string)$subOrder['status_update_time'], (string)$failSubOrder['status_update_time']) < 0) {
                $failSubOrder = $subOrder;
            }
        }
        if ($failSubOrder) {
            return [
                'orderStatus'        => 'WAREHOUSE_CONSIGN_FAIL',
                'status_update_time' => $failSubOrder['status_update_time'],
                'tracking_number'    => '',
                'exception_reason'   => '发货状态回写平台失败',
            ];
        }

        $priorityMap = [
            'SHIPPED'              => 4,
            'CANCELLED'            => 3,
            'WAREHOUSE_PROCESSING' => 2,
            'ORDER_CREATED'        => 1,
        ];

        $bestDetail = [
            'sub_trade_order_status' => $defaultStatus,
            'status_update_time'     => $defaultTime,
        ];

        foreach ($subOrders as $subOrder) {
            $detailStatus   = $subOrder['sub_trade_order_status'];
            $detailPriority = $priorityMap[$detailStatus] ?? 0;
            $bestPriority   = $priorityMap[$bestDetail['sub_trade_order_status']] ?? 0;
            if ($detailPriority > $bestPriority) {
                $bestDetail = $subOrder;
            }
        }

        $trackingNo = '';
        if ($bestDetail['sub_trade_order_status'] === 'SHIPPED') {
            $trackingNo = $this->resolveMainTrackingFromShippedSubOrders($subOrders);
        }

        return [
            'orderStatus'        => $bestDetail['sub_trade_order_status'],
            'status_update_time' => $bestDetail['status_update_time'],
            'tracking_number'    => $trackingNo,
            'exception_reason'   => '',
        ];
    }

    /**
     * 读取当前订单+运单号对应的最新发货回写日志。
     *
     * @param string $orderBn
     * @param string $logiNo
     * @return array
     */
    protected function getLatestShipmentLog($orderBn, $logiNo)
    {
        if (!$orderBn || !$logiNo) {
            return [];
        }

        $rows = app::get('ome')->model('shipment_log')->getList(
            'status,updateTime,deliveryCode',
            ['orderBn' => $orderBn, 'deliveryCode' => $logiNo],
            0,
            1,
            'updateTime DESC, log_id DESC'
        );

        return $rows ? $rows[0] : [];
    }

    /**
     * 统一格式化时间戳。
     *
     * @param mixed $timestamp
     * @return string
     */
    protected function formatUrgentTimestamp($timestamp)
    {
        if (!$timestamp) {
            return date('Y-m-d H:i:s');
        }
        if (is_numeric($timestamp)) {
            return date('Y-m-d H:i:s', intval($timestamp));
        }

        return (string)$timestamp;
    }
}
