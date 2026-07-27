<?php
/**
 * 拼多多消费者付费送货上门审单物流校验
 *
 * 仅处理带 SOMS_ZFSHSM 标签的拼多多订单。所有仓库校验承运商白名单，
 * OMS 自有仓额外校验物流公司使用启用中的拼多多电子面单；第三方 WMS
 * 不校验电子面单来源，避免依赖第三方在 OMS 中不存在的面单配置。
 */
class ome_order_platform_pinduoduo_chargehomedelivery
{
    const LABEL_CODE = 'SOMS_ZFSHSM';

    private $allowedCorpTypes = ['ZTO', 'YTO', 'STO', 'jitu', 'YUNDA'];

    private $pddLogisticsCodeMap = [
        'ZTO'   => 'ZTO',
        'YTO'   => 'YTO',
        'STO'   => 'STO',
        'jitu'  => 'JTSD',
        'YUNDA' => 'YUNDA',
    ];

    /**
     * 校验审单所选物流公司
     *
     * @param array $order
     * @param array|int $branch
     * @param array $corp
     * @param string $errorMsg
     * @return bool
     */
    public function validate($order, $branch, $corp, &$errorMsg = '')
    {
        if (strtolower($order['shop_type']) != 'pinduoduo' || empty($order['order_id'])) {
            return true;
        }

        $labelInfo = kernel::single('ome_bill_label')->getBillLabelInfo(
            $order['order_id'],
            'order',
            self::LABEL_CODE
        );
        if (!$labelInfo) {
            return true;
        }

        // “自动匹配物流”需要等自动审单插件选出实际物流后再校验。
        if (isset($corp['corp_id']) && $corp['corp_id'] === 'auto') {
            return true;
        }

        $corp = $this->completeCorpInfo($corp);
        if (empty($corp['corp_id']) || empty($corp['type'])) {
            $errorMsg = '拼多多消费者付费送货上门订单未选择有效物流公司';
            return false;
        }

        $whiteDeliveryCps = $this->getWhiteDeliveryCps($order['order_id']);
        if (!$whiteDeliveryCps) {
            $errorMsg = '订单物流服务要求存在冲突，没有同时满足条件的物流公司，请人工处理';
            return false;
        }

        if (!in_array($corp['type'], $whiteDeliveryCps, true)
            || !in_array($corp['type'], $this->allowedCorpTypes, true)) {
            $errorMsg = '拼多多消费者付费送货上门订单只能使用中通、圆通、申通、极兔或韵达发货';
            return false;
        }

        if (!$this->isSelfWms($branch)) {
            return true;
        }

        if ($corp['tmpl_type'] != 'electron' || empty($corp['channel_id'])) {
            $errorMsg = '拼多多消费者付费送货上门订单，自有仓发货必须使用拼多多电子面单';
            return false;
        }

        $channel = app::get('logisticsmanager')->model('channel')->db_dump([
            'channel_id' => $corp['channel_id'],
            'status'     => 'true',
        ], 'channel_id,channel_type,logistics_code,status');
        $expectedLogisticsCode = $this->pddLogisticsCodeMap[$corp['type']] ?? '';
        if (!$channel
            || $channel['channel_type'] != 'pdd'
            || strtoupper($channel['logistics_code']) != strtoupper($expectedLogisticsCode)) {
            $errorMsg = '拼多多消费者付费送货上门订单，自有仓发货必须使用拼多多电子面单';
            return false;
        }

        return true;
    }

    /**
     * 从仓库可用物流中选择第一个满足整个订单组要求的物流公司
     *
     * 仓库物流列表已经按 weight 倒序排列。自动审单原先选中的物流不合规时，
     * 仅在同一仓库的可达物流中寻找替代项，避免有合规物流却直接挂起订单。
     *
     * @param array $orders
     * @param array|int $branch
     * @param string $shipArea
     * @return array
     */
    public function findAvailableCorp($orders, $branch, $shipArea = '')
    {
        $branchId = is_array($branch) ? $branch['branch_id'] : $branch;
        if (empty($branchId)) {
            return [];
        }

        $corpList = app::get('ome')->model('branch')->get_corp($branchId, $shipArea);
        foreach ((array)$corpList as $corp) {
            $isAvailable = true;
            foreach ((array)$orders as $order) {
                $errorMsg = '';
                if (!$this->validate($order, $branch, $corp, $errorMsg)) {
                    $isAvailable = false;
                    break;
                }
            }
            if ($isAvailable) {
                return $corp;
            }
        }

        return [];
    }

    /**
     * 补齐审单入口可能未查询的电子面单字段
     */
    private function completeCorpInfo($corp)
    {
        if (empty($corp['corp_id']) || (!empty($corp['type']) && isset($corp['tmpl_type']) && isset($corp['channel_id']))) {
            return $corp;
        }

        $corpInfo = app::get('ome')->model('dly_corp')->db_dump(
            ['corp_id' => $corp['corp_id']],
            'corp_id,name,type,tmpl_type,channel_id,disabled'
        );
        return $corpInfo ? array_merge($corp, $corpInfo) : $corp;
    }

    /**
     * 获取订单当前生效的承运商白名单
     */
    private function getWhiteDeliveryCps($orderId)
    {
        $orderExtend = app::get('ome')->model('order_extend')->db_dump(
            ['order_id' => $orderId],
            'white_delivery_cps'
        );
        $whiteDeliveryCps = $orderExtend['white_delivery_cps']
            ? json_decode($orderExtend['white_delivery_cps'], true)
            : [];
        return array_values(array_unique(array_filter((array)$whiteDeliveryCps)));
    }

    /**
     * 判断审单仓库是否由 OMS 自有仓履约
     */
    private function isSelfWms($branch)
    {
        if (!is_array($branch)) {
            $branch = app::get('ome')->model('branch')->db_dump(
                ['branch_id' => $branch, 'check_permission' => false],
                'branch_id,wms_id'
            );
        } elseif (empty($branch['wms_id']) && $branch['branch_id']) {
            $branch = app::get('ome')->model('branch')->db_dump(
                ['branch_id' => $branch['branch_id'], 'check_permission' => false],
                'branch_id,wms_id'
            );
        }

        if (empty($branch['wms_id'])) {
            return false;
        }

        $adapter = app::get('channel')->model('adapter')->db_dump(
            ['channel_id' => $branch['wms_id']],
            'adapter'
        );
        return $adapter['adapter'] == 'selfwms';
    }
}
