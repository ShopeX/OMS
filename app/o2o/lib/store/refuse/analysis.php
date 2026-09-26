<?php
/**
 * 门店拒单分析记录
 */
class o2o_store_refuse_analysis
{
    /**
     * 保存门店拒单分析记录（同一发货单+门店去重）
     *
     * @param array  $params delivery_bn|delivery_id|store_bn|store_name|reason_id|memo|branch_id|order_id|order_bn|op_name
     * @param string $errMsg
     * @return bool
     */
    public function saveRecord(array $params, &$errMsg = '')
    {
        if (!app::get('o2o')->is_installed()) {
            return false;
        }

        $deliveryBn = trim($params['delivery_bn'] ?? '');
        $deliveryId = intval($params['delivery_id'] ?? 0);

        if ($deliveryId <= 0 && $deliveryBn !== '') {
            $dlyRow = app::get('ome')->model('delivery')->db_dump(['delivery_bn' => $deliveryBn], 'delivery_id,delivery_bn');
            if (!empty($dlyRow['delivery_id'])) {
                $deliveryId = intval($dlyRow['delivery_id']);
                $deliveryBn = $dlyRow['delivery_bn'];
            }
        } elseif ($deliveryId > 0 && $deliveryBn === '') {
            $dlyRow = app::get('ome')->model('delivery')->db_dump($deliveryId, 'delivery_bn');
            $deliveryBn = $dlyRow['delivery_bn'] ?? '';
        }

        if ($deliveryId <= 0 || $deliveryBn === '') {
            $errMsg = '发货单不存在';
            return false;
        }

        $storeBn   = trim($params['store_bn'] ?? '');
        $storeName = trim($params['store_name'] ?? '');
        if ($storeBn === '' && !empty($params['branch_id'])) {
            $storeInfo = kernel::single('wap_delivery')->getBranchShopInfo($params['branch_id']);
            $storeBn   = $storeInfo['store_bn'] ?? '';
            $storeName = $storeInfo['store_name'] ?? ($storeInfo['name'] ?? '');
        }

        if ($storeBn === '') {
            $errMsg = '门店信息不存在';
            return false;
        }

        $mdl    = app::get('o2o')->model('store_refuse_analysis');
        $exists = $mdl->db_dump(['delivery_id' => $deliveryId, 'store_bn' => $storeBn], 'sra_id');
        if (!empty($exists)) {
            return true;
        }

        list($orderId, $orderBn) = $this->_resolveOrderInfo($deliveryId, $params);
        $branchId = $this->_resolveBranchId($deliveryId, $storeBn, $params);

        $opName = trim($params['op_name'] ?? '');
        if ($opName === '') {
            $consignOp = kernel::single('wap_delivery_process')->getConsignOp();
            $opName    = $consignOp['op_name'] ?? '';
        }

        $reasonId = $params['reason_id'] ?? '';
        if ($reasonId === '' || $reasonId === null) {
            $errMsg = '拒单原因不能为空';
            return false;
        }

        $row = [
            'delivery_bn' => $deliveryBn,
            'delivery_id' => $deliveryId,
            'branch_id'   => $branchId,
            'order_id'    => $orderId,
            'order_bn'    => $orderBn,
            'store_bn'    => $storeBn,
            'store_name'  => $storeName,
            'reason_id'   => $reasonId,
            'memo'        => trim($params['memo'] ?? ''),
            'op_name'     => $opName,
            'createtime'  => time(),
        ];

        return (bool) $mdl->save($row);
    }

    /**
     * 解析门店仓 branch_id
     */
    private function _resolveBranchId($deliveryId, $storeBn, array $params)
    {
        $branchId = intval($params['branch_id'] ?? 0);
        if ($branchId > 0) {
            return $branchId;
        }

        $dlyRow = app::get('ome')->model('delivery')->db_dump($deliveryId, 'branch_id');
        if (!empty($dlyRow['branch_id'])) {
            return intval($dlyRow['branch_id']);
        }

        if ($storeBn !== '') {
            $storeRow = app::get('o2o')->model('store')->db_dump(
                array('store_bn' => $storeBn, 'status' => 1),
                'branch_id'
            );
            if (!empty($storeRow['branch_id'])) {
                return intval($storeRow['branch_id']);
            }
        }

        return 0;
    }

    /**
     * @param int   $deliveryId
     * @param array $params
     * @return array [order_id, order_bn]
     */
    private function _resolveOrderInfo($deliveryId, array $params)
    {
        $orderId = intval($params['order_id'] ?? 0);
        $orderBn = trim($params['order_bn'] ?? '');

        if ($orderBn === '' && !empty($params['delivery_bn'])) {
            $wapDly = app::get('wap')->model('delivery')->db_dump(
                ['outer_delivery_bn' => $params['delivery_bn']],
                'order_bn'
            );
            $orderBn = $wapDly['order_bn'] ?? '';
        }

        if ($orderId <= 0) {
            $dlyOrder = app::get('ome')->model('delivery_order')->db_dump(['delivery_id' => $deliveryId], 'order_id');
            $orderId  = intval($dlyOrder['order_id'] ?? 0);
        }

        if ($orderBn === '' && $orderId > 0) {
            $orderRow = app::get('ome')->model('orders')->db_dump($orderId, 'order_bn');
            $orderBn  = $orderRow['order_bn'] ?? '';
        } elseif ($orderBn !== '' && $orderId <= 0) {
            $orderRow = app::get('ome')->model('orders')->db_dump(['order_bn' => $orderBn], 'order_id');
            $orderId  = intval($orderRow['order_id'] ?? 0);
        }

        return [$orderId, $orderBn];
    }
}
