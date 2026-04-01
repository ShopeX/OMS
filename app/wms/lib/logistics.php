<?php
/**
 * WMS 物流相关封装
 * 统一通过 erpapi shop request 调用各平台物流接口
 */
class wms_logistics
{
    /**
     * 官方提货（提货物流）
     * 入参为 wms delivery_id，通过 outer_delivery_bn 对应 ome_delivery.delivery_bn 取 ome 发货单，
     * 用 ome_event_trigger_shop_data_delivery_alibaba->get_sdf 取 sdf，再调 erpapi 对应平台 officialPickup
     * @param int|array $delivery_id wms 发货单主键；或数组且含 key delivery_id
     * @return array 统一返回 ['rsp' => 'succ'|'fail', 'msg' => '', 'data' => ...]，便于后续根据成功/失败处理
     */
    public function officialPickup($delivery_id)
    {
        $fail = function ($msg, $data = null) {
            return ['rsp' => 'fail', 'msg' => $msg, 'data' => $data];
        };
        $ok = function ($result) {
            if (is_array($result) && isset($result['rsp'])) {
                return $result;
            }
            return ['rsp' => 'succ', 'msg' => '', 'data' => $result];
        };

        $wms_delivery_id = is_array($delivery_id) ? (isset($delivery_id['delivery_id']) ? (int)$delivery_id['delivery_id'] : 0) : (int)$delivery_id;
        if ($wms_delivery_id <= 0) {
            return $fail('无效的发货单ID');
        }

        $wmsDeliveryMdl = app::get('wms')->model('delivery');
        $wmsDelivery = $wmsDeliveryMdl->dump($wms_delivery_id, 'delivery_id,outer_delivery_bn,shop_id');
        if (empty($wmsDelivery) || empty($wmsDelivery['outer_delivery_bn']) || empty($wmsDelivery['shop_id'])) {
            return $fail('未找到WMS发货单或缺少 outer_delivery_bn/shop_id');
        }

        $omeDeliveryMdl = app::get('ome')->model('delivery');
        $omeDelivery = $omeDeliveryMdl->dump(['delivery_bn' => $wmsDelivery['outer_delivery_bn']]);
        if (empty($omeDelivery) || empty($omeDelivery['delivery_id'])) {
            return $fail('未找到对应的OMS发货单');
        }

        $ome_delivery_id = $omeDelivery['delivery_id'];
        $deliveryList = $omeDeliveryMdl->getList('*', ['delivery_id' => $ome_delivery_id]);
        if (empty($deliveryList)) {
            return $fail('OMS发货单列表为空');
        }

        $deliveryOrderMdl = app::get('ome')->model('delivery_order');
        $deliveryOrderList = $deliveryOrderMdl->getList('delivery_id,order_id', ['delivery_id' => $ome_delivery_id]);
        $order_ids = [];
        $delivery_orders = [];
        $orderList = [];
        foreach ($deliveryOrderList as $row) {
            $order_ids[] = $row['order_id'];
            $delivery_orders[$row['delivery_id']] = &$orderList[$row['order_id']];
        }
        $orderModel = app::get('ome')->model('orders');
        $orders = $orderModel->getList('*', ['order_id' => $order_ids]);
        foreach ($orders as $o) {
            $orderList[$o['order_id']] = $o;
        }

        $delivery = $deliveryList[0];
        $order = isset($delivery_orders[$ome_delivery_id]) ? $delivery_orders[$ome_delivery_id] : null;
        $sdf = $this->_buildOfficialPickupSdf($ome_delivery_id, $delivery, $order);
        if (empty($sdf)) {
            return $fail('获取平台发货数据(sdf)为空');
        }

        $result = kernel::single('erpapi_router_request')->set('shop', $wmsDelivery['shop_id'])->logistics_officialPickup($sdf);
        $ret = $ok($result);

        // 仅成功时：从平台返回结果中提取运单号，回写 OMS / WMS（原 erpapi_shop_matrix_alibaba_request_delivery confirm_callback 逻辑）
        if ($ret['rsp'] === 'succ' || $ret['rsp'] === 'success') {
            $this->_officialPickupSyncLogiNo($ret, $sdf['delivery_bn'], $wmsDelivery['delivery_id']);
        }

        return $ret;
    }

    /**
     * 在本类内直接组装 officialPickup 所需 sdf，与 erpapi alibaba request logistics 入参一致，避免 router 取数不全
     * 结构：orderinfo.order_bn、delivery_items(oid/nums/number/weight)、memo、delivery_time、delivery_bn
     * @param int $ome_delivery_id OMS 发货单 id
     * @param array $delivery OMS 发货单行（含 delivery_bn, delivery_time, memo 等）
     * @param array|null $order OMS 订单行（含 order_bn/platform_order_bn）
     * @return array 空表示组装失败，否则为 alibaba officialPickup 所需 sdf
     */
    protected function _buildOfficialPickupSdf($ome_delivery_id, $delivery, $order)
    {
        if (empty($delivery) || empty($order)) {
            return [];
        }
        $order_bn = isset($order['platform_order_bn']) && $order['platform_order_bn'] !== ''
            ? $order['platform_order_bn']
            : (isset($order['order_bn']) ? $order['order_bn'] : '');
        if ($order_bn === '') {
            return [];
        }

        $detailMdl = app::get('ome')->model('delivery_items_detail');
        $details = $detailMdl->getList('*', ['delivery_id' => $ome_delivery_id]);
        if (empty($details)) {
            return [];
        }

        $objIds = array_unique(array_column($details, 'order_obj_id'));
        $objMdl = app::get('ome')->model('order_objects');
        $objRows = $objMdl->getList('obj_id,oid', ['obj_id' => $objIds]);
        $objId2Oid = [];
        foreach ($objRows as $r) {
            $objId2Oid[$r['obj_id']] = isset($r['oid']) ? trim((string)$r['oid']) : '';
        }

        $delivery_items = [];
        foreach ($details as $d) {
            $oid = isset($objId2Oid[$d['order_obj_id']]) ? $objId2Oid[$d['order_obj_id']] : '';
            if ($oid === '') {
                continue;
            }
            $num = isset($d['number']) ? (int)$d['number'] : 0;
            if ($num < 1) {
                continue;
            }
            $delivery_items[] = [
                'oid'    => $oid,
                'nums'   => $num,
                'number' => $num,
                'weight' => isset($d['weight']) ? (float)$d['weight'] : 0,
            ];
        }
        if (empty($delivery_items)) {
            return [];
        }

        return [
            'orderinfo'     => ['order_bn' => $order_bn],
            'delivery_items'=> $delivery_items,
            'memo'          => isset($delivery['memo']) ? $delivery['memo'] : '',
            'delivery_time' => isset($delivery['delivery_time']) ? $delivery['delivery_time'] : time(),
            'delivery_bn'   => isset($delivery['delivery_bn']) ? $delivery['delivery_bn'] : '',
        ];
    }

    /**
     * 从 officialPickup 返回的 result 中解析出 result.pickUpInfo（与 yunqi saveOfficialPickupResult 一致：data 可能为 JSON 字符串，且含 msg 内嵌 JSON）
     * @param array $result ['rsp'=>'succ','data'=>'...'] 其中 data 可能为 '{"msg":"{\"result\":{\"pickUpInfo\":{...}}}","success":true}'
     * @return array|null pickUpInfo 或 null
     */
    protected function _parseOfficialPickupResult($result)
    {
        $data = isset($result['data']) ? $result['data'] : $result;
        if (is_string($data)) {
            $data = json_decode($data, true);
            if (is_array($data) && isset($data['msg']) && is_string($data['msg'])) {
                $inner = json_decode($data['msg'], true);
                if (is_array($inner)) {
                    $data = array_merge($data, $inner);
                }
            }
        }
        if (!is_array($data)) {
            $data = array();
        }
        if (!empty($data['result']['pickUpInfo'])) {
            return $data['result']['pickUpInfo'];
        }
        if (!empty($result['result']['pickUpInfo'])) {
            return $result['result']['pickUpInfo'];
        }
        return null;
    }

    /**
     * 官方提货成功：从接口返回中解析 pickUpInfo.mailNo，回写 OMS 与 WMS 运单号
     * @param array $result officialPickup 返回的 ['rsp'=>'succ','data'=>...]
     * @param string $delivery_bn OMS 发货单号（delivery_bn）
     * @param int $wms_delivery_id WMS 发货单主键
     */
    protected function _officialPickupSyncLogiNo($result, $delivery_bn, $wms_delivery_id)
    {
        $pickUpInfo = $this->_parseOfficialPickupResult($result);
        if (empty($pickUpInfo) || empty($pickUpInfo['mailNo'])) {
            return;
        }
        $mailNo = trim($pickUpInfo['mailNo']);

        // OMS: 通过 addLogiNo 统一链路更新 ome_delivery.logi_no
        kernel::single('ome_event_receive_delivery')->update([
            'delivery_bn' => $delivery_bn,
            'logi_no'     => $mailNo,
            'action'      => 'addLogiNo',
            'status'      => 'update',
        ]);

        // WMS: 同步更新 wms_delivery_bill.logi_no（type=1 主单）
        app::get('wms')->model('delivery_bill')->update(
            ['logi_no' => $mailNo],
            ['delivery_id' => $wms_delivery_id, 'type' => 1]
        );
    }
}
