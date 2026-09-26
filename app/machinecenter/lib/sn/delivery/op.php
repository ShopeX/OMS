<?php
/**
 * 门店 WAP 发货唯一码操作记录（consign 成功写入，每 SN 一行）
 */
class machinecenter_sn_delivery_op
{
    /**
     * @param array $deliveryInfo  WAP delivery dump
     * @param array $serialItems   [['bn'=>'', 'sn_list'=>[]], ...]
     * @return int 成功写入条数
     */
    public function saveFromWapConsign(array $deliveryInfo, array $serialItems)
    {
        if (empty($serialItems) || !app::get('machinecenter')->is_installed()) {
            return 0;
        }

        // 仅「确认发货」扫描工号后写入；自提核销等无工号环节不写门店发货唯一码记录
        $scannedOpNo = trim($deliveryInfo['scanned_op_no'] ?? '');
        if ($scannedOpNo === '') {
            return 0;
        }

        $deliveryBn = $deliveryInfo['outer_delivery_bn'] ?: '';
        if ($deliveryBn === '') {
            return 0;
        }

        $omeDly = app::get('ome')->model('delivery')->db_dump(
            ['delivery_bn' => $deliveryBn],
            'delivery_id'
        );
        if (!$omeDly) {
            return 0;
        }

        $orderBn = $deliveryInfo['order_bn'] ?: '';
        if ($orderBn === '' && !empty($omeDly['delivery_id'])) {
            $orderInfo = app::get('ome')->model('delivery')->getOrderBnbyDeliveryId($omeDly['delivery_id']);
            $orderBn   = $orderInfo['order_bn'] ?: '';
        }

        $branchId = intval($deliveryInfo['branch_id']);
        $branchBn = '';
        if ($branchId > 0) {
            $branchRow = app::get('ome')->model('branch')->db_dump($branchId, 'branch_bn');
            $branchBn  = $branchRow['branch_bn'] ?: '';
        }

        $storeBn   = '';
        $storeName = '';
        if ($branchId > 0) {
            $storeInfo = kernel::single('wap_delivery')->getBranchShopInfo($branchId);
            if (!empty($storeInfo)) {
                $storeBn   = $storeInfo['store_bn'] ?: '';
                $storeName = $storeInfo['store_name'] ?: ($storeInfo['name'] ?: '');
            }
        }

        $consignOp = kernel::single('wap_delivery_process')->getConsignOp();
        $opId      = !empty($deliveryInfo['op_id']) ? $deliveryInfo['op_id'] : $consignOp['op_id'];
        $opName    = !empty($deliveryInfo['op_name']) ? $deliveryInfo['op_name'] : $consignOp['op_name'];
        $opNo      = $scannedOpNo;

        $consignTime = !empty($deliveryInfo['delivery_time'])
            ? intval($deliveryInfo['delivery_time'])
            : time();

        $mdl     = app::get('machinecenter')->model('material_sn_delivery_op');
        $saved   = 0;

        foreach ($serialItems as $item) {
            $bn     = trim($item['bn'] ?? '');
            $snList = isset($item['sn_list']) ? (array) $item['sn_list'] : [];
            if ($bn === '' || empty($snList)) {
                continue;
            }
            foreach ($snList as $sn) {
                $sn = trim($sn);
                if ($sn === '') {
                    continue;
                }
                if ($mdl->db_dump(['delivery_bn' => $deliveryBn, 'sn' => $sn], 'id')) {
                    continue;
                }
                $row = [
                    'delivery_id'  => intval($omeDly['delivery_id']),
                    'delivery_bn'  => $deliveryBn,
                    'order_bn'     => $orderBn,
                    'branch_id'    => $branchId,
                    'branch_bn'    => $branchBn,
                    'store_bn'     => $storeBn,
                    'store_name'   => $storeName,
                    'material_bn'  => $bn,
                    'sn'           => $sn,
                    'op_id'        => $opId,
                    'op_name'      => $opName,
                    'op_no'        => $opNo,
                    'consign_time' => $consignTime,
                    'source'       => 'wap',
                ];
                if ($mdl->insert($row)) {
                    $saved++;
                }
            }
        }

        return $saved;
    }
}
