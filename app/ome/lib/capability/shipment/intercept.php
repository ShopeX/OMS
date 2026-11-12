<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

/**
 * Read-only capability for shipment intercept list.
 *
 * 查询回写失败 + 子单全额退款（order_objects.pay_status=5）的发货拦截清单。
 * 直接关联 shipment_log -> delivery_items_detail -> order_objects -> order_items，
 * 一次返回结构化结果，避免 N+1 调用。
 */
class ome_capability_shipment_intercept
{
    private const SHIPMENT_LOG_BATCH_SIZE = 100;
    private const OBJECT_PAY_STATUS_FULL_REFUND = 5;

    private object $shipmentLogModel;
    private object $deliveryItemsDetailModel;
    private object $orderObjectsModel;
    private object $orderItemsModel;
    private object $deliveryModel;
    private ?object $modelProvider;

    public function __construct(?object $modelProvider = null)
    {
        $this->modelProvider = $modelProvider;
        $this->shipmentLogModel = $this->model('shipment_log');
        $this->deliveryItemsDetailModel = $this->model('delivery_items_detail');
        $this->orderObjectsModel = $this->model('order_objects');
        $this->orderItemsModel = $this->model('order_items');
        $this->deliveryModel = $this->model('delivery');
    }

    /**
     * @param array $request Supports page, pageSize, startTime, endTime, orderBn, shopId.
     * @return array{list: array<int, array<string, mixed>>, total: int, page: int, pageSize: int}
     */
    public function list(array $request): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $hasTimeRange = isset($request['startTime']) || isset($request['endTime']);
        $hasOrderBn = !empty($request['orderBn']);
        if (!$hasTimeRange && !$hasOrderBn) {
            throw new InvalidArgumentException('startTime/endTime 或 orderBn 至少传一个');
        }

        $logFilter = $this->buildLogFilter($request);
        $shipmentLogs = $this->shipmentLogModel->getList(
            '*',
            $logFilter,
            0,
            self::SHIPMENT_LOG_BATCH_SIZE,
            'receiveTime DESC',
        );

        if ($shipmentLogs === []) {
            return ['list' => [], 'total' => 0, 'page' => $page, 'pageSize' => $pageSize];
        }

        $rows = $this->correlate($shipmentLogs);
        $total = count($rows);
        $rows = array_slice($rows, $offset, $pageSize);

        return [
            'list' => $rows,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    private function model(string $name): object
    {
        if ($this->modelProvider !== null && method_exists($this->modelProvider, 'model')) {
            return $this->modelProvider->model($name);
        }
        return app::get('ome')->model($name);
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function buildLogFilter(array $request): array
    {
        $filter = ['status' => 'fail'];

        if (isset($request['startTime'])) {
            $filter['receiveTime|bthan'] = (int) $request['startTime'];
        }
        if (isset($request['endTime'])) {
            $filter['receiveTime|sthan'] = (int) $request['endTime'];
        }
        if (isset($request['shopId']) && $request['shopId'] !== '') {
            $filter['shopId'] = (string) $request['shopId'];
        }
        if (isset($request['orderBn']) && $request['orderBn'] !== '') {
            $filter['orderBn'] = (string) $request['orderBn'];
        }

        return $filter;
    }

    /**
     * @param array<int, array<string, mixed>> $shipmentLogs
     * @return array<int, array<string, mixed>>
     */
    private function correlate(array $shipmentLogs): array
    {
        $orderBns = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => (string) ($row['orderBn'] ?? $row['order_bn'] ?? ''),
            $shipmentLogs,
        ))));
        if ($orderBns === []) {
            return [];
        }

        $deliveryItemsDetailRows = $this->deliveryItemsDetailModel->getList(
            '*',
            ['oid' => $orderBns],
            0,
            -1,
            '',
        );

        if ($deliveryItemsDetailRows === []) {
            return [];
        }

        $deliveryIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) ($row['delivery_id'] ?? 0),
            $deliveryItemsDetailRows,
        )));
        $deliveries = $this->deliveryModel->getList('*', ['delivery_id' => $deliveryIds], 0, -1, '');
        $deliveriesById = [];
        foreach ($deliveries as $delivery) {
            $deliveriesById[(int) $delivery['delivery_id']] = $delivery;
        }

        $orderObjIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) ($row['order_obj_id'] ?? 0),
            $deliveryItemsDetailRows,
        )));
        $orderObjects = $this->orderObjectsModel->getList('*', ['obj_id' => $orderObjIds], 0, -1, '');
        $orderObjectsById = [];
        $fullRefundObjIds = [];
        foreach ($orderObjects as $obj) {
            $objId = (int) ($obj['obj_id'] ?? 0);
            $orderObjectsById[$objId] = $obj;
            if ((int) ($obj['pay_status'] ?? 0) === self::OBJECT_PAY_STATUS_FULL_REFUND) {
                $fullRefundObjIds[] = $objId;
            }
        }

        if ($fullRefundObjIds === []) {
            return [];
        }

        $orderItemIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) ($row['order_item_id'] ?? 0),
            $deliveryItemsDetailRows,
        )));
        $orderItems = $this->orderItemsModel->getList('*', ['item_id' => $orderItemIds], 0, -1, '');
        $orderItemsById = [];
        foreach ($orderItems as $item) {
            $orderItemsById[(int) ($item['item_id'] ?? 0)] = $item;
        }

        $logsByOrderBn = [];
        foreach ($shipmentLogs as $log) {
            $orderBn = (string) ($log['orderBn'] ?? $log['order_bn'] ?? '');
            if ($orderBn !== '') {
                $logsByOrderBn[$orderBn] = $log;
            }
        }

        $rows = [];
        foreach ($deliveryItemsDetailRows as $detail) {
            $orderObjId = (int) ($detail['order_obj_id'] ?? 0);
            if (!in_array($orderObjId, $fullRefundObjIds, true)) {
                continue;
            }

            $oid = (string) ($detail['oid'] ?? '');
            $log = $logsByOrderBn[$oid] ?? null;
            if ($log === null) {
                continue;
            }

            $deliveryId = (int) ($detail['delivery_id'] ?? 0);
            $delivery = $deliveriesById[$deliveryId] ?? null;
            if ($delivery === null) {
                continue;
            }

            $orderItemId = (int) ($detail['order_item_id'] ?? 0);
            $orderItem = $orderItemsById[$orderItemId] ?? null;

            $rows[] = [
                'order_bn' => (string) ($log['orderBn'] ?? $log['order_bn'] ?? ''),
                'delivery_bn' => (string) ($delivery['delivery_bn'] ?? ''),
                'logi_corp_code' => (int) ($delivery['logi_id'] ?? 0),
                'delivery_code' => (string) ($delivery['logi_no'] ?? ''),
                'oid' => $oid,
                'bn' => (string) ($orderItem['bn'] ?? $detail['bn'] ?? ''),
                'name' => (string) ($orderItem['name'] ?? ''),
                'quantity' => (float) ($orderItem['nums'] ?? $detail['number'] ?? 0),
                'status' => self::OBJECT_PAY_STATUS_FULL_REFUND,
                'receive_time' => (int) ($log['receiveTime'] ?? 0),
                'fail_message' => (string) ($log['message'] ?? ''),
            ];
        }

        return $rows;
    }
}
