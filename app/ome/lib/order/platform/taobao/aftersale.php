<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * 天猫/淘宝售后平台识别业务 Lib
 *
 * - 商责免费退（平台承担退货运费）识别与判定
 * - 天猫极速换货（speed_exchange）识别 / 打标 / 17 提前建单 / 18 可恢复拦截
 *
 * @author wangbiao@shopex.cn
 * @version 2026.09.29
 */
class ome_order_platform_taobao_aftersale extends ome_abstract
{
    /** reason */
    const REASON_REFUND_POSTAGE = '退运费';
    
    /** dispute_type */
    const DISPUTE_RETURN_GOODS_POSTAGE = 'RETURN_GOODS_POSTAGE';
    
    /** attribute.trigger */
    const TRIGGER_CN_POSTAGE_REFUND = 'cnPostageRefund';
    
    /** 商责退运费 tag_type */
    const TAG_TYPE_SHANGZE = '9';

    /** 换出订单 bill_label：极速换货 */
    const LABEL_TOPSPEED = 'SOMS_TOPSPEED_EXCHANGE';

    /** 换出订单 bill_label：拒发新品可恢复禁发 */
    const LABEL_HOLD = 'SOMS_ISDELIVERY';
    
    /**
     * 识别是否商责免费退（平台承担退货运费）
     *
     * 条件：reason=退运费 + dispute_type=RETURN_GOODS_POSTAGE + trigger=cnPostageRefund
     *
     * @param array $params 平台推送原始参数
     * @return array
     */
    public function identifyShangzeFreeReturn($params)
    {
        $result = [
            'matched' => false,
            'tag_type' => '0',
            'is_shangze_free_return' => 'false',
            'platform_refunded' => false,
            'apply_init_refund_fee' => 0,
            'addon' => [],
        ];
        
        // check params
        if (!is_array($params)) {
            return $result;
        }
        
        $reason = isset($params['reason']) ? trim((string)$params['reason']) : '';
        $disputeType = isset($params['dispute_type']) ? trim((string)$params['dispute_type']) : '';
        $attributeCode = $this->parseAttribute(isset($params['attribute']) ? $params['attribute'] : '');

        $trigger = isset($attributeCode['trigger']) ? trim((string)$attributeCode['trigger']) : '';
        $postAR = isset($attributeCode['postAR']) ? (string)$attributeCode['postAR'] : '';
        $postFI = isset($attributeCode['postFI']) ? (string)$attributeCode['postFI'] : '';
        $postFIL = isset($attributeCode['postFIL']) ? (string)$attributeCode['postFIL'] : '';
        
        // 匹配条件
        if ($reason !== self::REASON_REFUND_POSTAGE
            || $disputeType !== self::DISPUTE_RETURN_GOODS_POSTAGE
            || $trigger !== self::TRIGGER_CN_POSTAGE_REFUND
        ) {
            return $result;
        }
        
        // 检查postAR、postFI、postFIL字段值
        if(empty($postAR) || empty($postFI) || empty($postFIL)){
            return $result;
        }
        
        // apply_init_refund_fee：平台单位为分，转为元
        $applyInitFeeFen = isset($attributeCode['apply_init_refund_fee']) ? floatval($attributeCode['apply_init_refund_fee']) : 0;
        if ($applyInitFeeFen > 0) {
            $result['apply_init_refund_fee'] = sprintf('%.2f', $applyInitFeeFen / 100);
        }
        
        // result
        $result['matched'] = true;
        $result['tag_type'] = self::TAG_TYPE_SHANGZE;
        $result['is_shangze_free_return'] = 'true';
        $result['platform_refunded'] = ($postFIL !== '');
        $result['addon'] = [
            'dispute_type' => $disputeType,
            'trigger' => $trigger,
            'postAR' => $postAR,
            'postFI' => $postFI,
            'postFIL' => $postFIL,
            'is_shangze_free_return' => 'true',
        ];
        
        return $result;
    }
    
    /**
     * 解析平台 attribute 字符串为键值数组
     *
     * @param mixed $attribute
     * @return array
     */
    public function parseAttribute($attribute)
    {
        $attributeCode = [];
        if (empty($attribute) || !is_string($attribute)) {
            return $attributeCode;
        }
        
        $attributeArr = explode(';', $attribute);
        foreach ($attributeArr as $item) {
            if ($item === '' || strpos($item, ':') === false) {
                continue;
            }
            list($key, $value) = explode(':', $item, 2);
            $attributeCode[$key] = $value;
        }
        
        return $attributeCode;
    }
    
    /**
     * 是否商责免费退 SDF
     *
     * @param array $sdf
     * @return bool
     */
    public function isShangzeFreeReturnSdf($sdf)
    {
        if (!is_array($sdf)) {
            return false;
        }
        
        if (!empty($sdf['is_shangze_free_return']) && $sdf['is_shangze_free_return'] === 'true') {
            return true;
        }
        
        return $this->isShangzeByTagType(isset($sdf['tag_type']) ? $sdf['tag_type'] : '');
    }
    
    /**
     * 是否商责退运费（按 tag_type）
     *
     * @param mixed $tagType
     * @return bool
     */
    public function isShangzeByTagType($tagType)
    {
        return (string)$tagType === self::TAG_TYPE_SHANGZE;
    }

    // -------------------------------------------------------------------------
    // 天猫极速换货（本期仅 shop_type=tmall）
    // -------------------------------------------------------------------------

    /**
     * 本期极速换货渠道门闩：仅天猫
     *
     * @param mixed $shopType
     * @return bool
     */
    public function isTopspeedShopType($shopType)
    {
        return (string)$shopType === 'tmall';
    }

    /**
     * 从换货报文提取 speed_exchange（顶层优先，否则 extend_field）
     *
     * @param array $params
     * @return string
     */
    public function extractSpeedExchange($params)
    {
        if (!is_array($params)) {
            return '';
        }
        if (isset($params['speed_exchange']) && (string)$params['speed_exchange'] !== '') {
            return (string)$params['speed_exchange'];
        }
        $ext = isset($params['extend_field']) ? $params['extend_field'] : null;
        if (is_string($ext) && $ext !== '') {
            $ext = json_decode($ext, true);
        }
        if (is_array($ext) && isset($ext['speed_exchange'])) {
            return (string)$ext['speed_exchange'];
        }
        return '';
    }

    /**
     * 报文是否声明极速换货（需同时满足 tmall）
     *
     * @param array $params
     * @return bool
     */
    public function isTopspeedExchangeParams($params)
    {
        if (!is_array($params)) {
            return false;
        }
        $shopType = isset($params['shop_type']) ? $params['shop_type'] : '';
        if (!$this->isTopspeedShopType($shopType)) {
            return false;
        }
        return $this->extractSpeedExchange($params) === '1';
    }

    /**
     * 是否极速换货 flag_type
     *
     * @param mixed $flagType
     * @return bool
     */
    public function isTopspeedByFlagType($flagType)
    {
        return ((int)$flagType & ome_reship_const::__TOPSPEED_EXCHANGE) ? true : false;
    }

    /**
     * 售后申请 / 退换货单是否极速
     *
     * @param array $row
     * @return bool
     */
    public function isTopspeedReturnOrReship($row)
    {
        if (!is_array($row) || empty($row)) {
            return false;
        }
        $shopType = isset($row['shop_type']) ? $row['shop_type'] : '';
        if ($shopType !== '' && !$this->isTopspeedShopType($shopType)) {
            return false;
        }
        return $this->isTopspeedByFlagType(isset($row['flag_type']) ? $row['flag_type'] : 0);
    }

    /**
     * flag_type 位或极速标
     *
     * @param mixed $flagType
     * @return int
     */
    public function applyTopspeedFlagType($flagType)
    {
        return ((int)$flagType) | ome_reship_const::__TOPSPEED_EXCHANGE;
    }

    /**
     * 综合判定是否极速：报文 / SDF 标记 / 已落库单据
     *
     * @param array $sdf
     * @return bool
     */
    public function resolveIsTopspeedExchange($sdf)
    {
        if (!is_array($sdf)) {
            return false;
        }
        $shopType = isset($sdf['shop_type']) ? $sdf['shop_type'] : '';
        if (!$this->isTopspeedShopType($shopType)) {
            return false;
        }
        if (!empty($sdf['is_topspeed_exchange'])) {
            return true;
        }
        if ($this->isTopspeedExchangeParams($sdf)) {
            return true;
        }
        if (!empty($sdf['return_product']) && $this->isTopspeedReturnOrReship($sdf['return_product'])) {
            return true;
        }
        if (!empty($sdf['reship']) && $this->isTopspeedReturnOrReship($sdf['reship'])) {
            return true;
        }
        return false;
    }

    /**
     * 平台状态 → OMS 内部 status（二维：是否极速）
     *
     * @param string $platformStatus
     * @param bool   $isTopspeed
     * @param array  $defaultMap    现网 $return_status
     * @return string|null
     */
    public function resolvePlatformStatusMap($platformStatus, $isTopspeed, $defaultMap)
    {
        $platformStatus = strtoupper((string)$platformStatus);
        if ($isTopspeed) {
            if ($platformStatus === 'WAIT_SELLER_SEND_GOODS') {
                // 禁止映射内部完成(4)，改为待收货语义，允许后续更新与提前建单
                return '6';
            }
            if ($platformStatus === 'SELLER_REFUSED_SEND_NEW_GOODS') {
                return '18';
            }
        }
        if (isset($defaultMap[$platformStatus])) {
            return (string)$defaultMap[$platformStatus];
        }
        return null;
    }

    /**
     * 售后申请 + 退换货单打极速 flag_type
     *
     * @param int $returnId
     * @param int $reshipId
     * @return void
     */
    public function markTopspeedOnReturnAndReship($returnId, $reshipId = 0)
    {
        $returnId = (int)$returnId;
        $reshipId = (int)$reshipId;
        if ($returnId <= 0 && $reshipId <= 0) {
            return;
        }

        $db = kernel::database();
        if ($returnId > 0) {
            $row = $db->selectrow("SELECT flag_type FROM sdb_ome_return_product WHERE return_id=" . $returnId);
            if ($row) {
                $newFlag = $this->applyTopspeedFlagType($row['flag_type']);
                if ((int)$row['flag_type'] !== $newFlag) {
                    app::get('ome')->model('return_product')->update(
                        array('flag_type' => $newFlag),
                        array('return_id' => $returnId)
                    );
                }
            }
        }

        if ($reshipId <= 0 && $returnId > 0) {
            $reship = $db->selectrow(
                "SELECT reship_id,flag_type FROM sdb_ome_reship WHERE return_id=" . $returnId
                . " AND return_type='change' AND is_check NOT IN('5','9') ORDER BY reship_id DESC"
            );
            if ($reship) {
                $reshipId = (int)$reship['reship_id'];
            }
        }

        if ($reshipId > 0) {
            $row = $db->selectrow("SELECT flag_type FROM sdb_ome_reship WHERE reship_id=" . $reshipId);
            if ($row) {
                $newFlag = $this->applyTopspeedFlagType($row['flag_type']);
                if ((int)$row['flag_type'] !== $newFlag) {
                    app::get('ome')->model('reship')->update(
                        array('flag_type' => $newFlag),
                        array('reship_id' => $reshipId)
                    );
                }
            }
        }
    }

    /**
     * 换出订单打极速 bill_label
     *
     * @param int $orderId
     * @return bool
     */
    public function markTopspeedOnChangeOrder($orderId)
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) {
            return false;
        }
        $err = '';
        return kernel::single('ome_bill_label')->markBillLabel(
            $orderId,
            '',
            self::LABEL_TOPSPEED,
            'order',
            $err,
            0
        );
    }

    /**
     * 极速 create_order 是否跳过平台 returngoods_agree
     *
     * @param array $reshipinfo
     * @return bool
     */
    public function shouldSkipReturngoodsAgreeOnCreate($reshipinfo)
    {
        if (!is_array($reshipinfo)) {
            return false;
        }
        if (!$this->isTopspeedShopType(isset($reshipinfo['shop_type']) ? $reshipinfo['shop_type'] : '')) {
            // shop_type 为空时仍看 flag
            if (!$this->isTopspeedByFlagType(isset($reshipinfo['flag_type']) ? $reshipinfo['flag_type'] : 0)) {
                return false;
            }
            return true;
        }
        return $this->isTopspeedByFlagType(isset($reshipinfo['flag_type']) ? $reshipinfo['flag_type'] : 0);
    }

    /**
     * 极速发货强制 consigngoods（优先于新换货升级 confirm.consign）
     *
     * @param mixed $flagType
     * @return bool
     */
    public function shouldForceConsigngoods($flagType)
    {
        return $this->isTopspeedByFlagType($flagType);
    }

    /**
     * 平台 17：极速提前生成换出订单（幂等）
     *
     * @param int    $reshipId
     * @param string $msg
     * @return bool
     */
    public function tryCreateChangeOrderForTopspeed($reshipId, &$msg = null)
    {
        $reshipId = (int)$reshipId;
        $msg = '';
        if ($reshipId <= 0) {
            $msg = 'reship_id 无效';
            return false;
        }

        $reshipObj = app::get('ome')->model('reship');
        $reship = $reshipObj->dump(array('reship_id' => $reshipId), '*');
        if (!$reship || $reship['return_type'] !== 'change') {
            $msg = '换货单不存在';
            return false;
        }
        if (!$this->isTopspeedShopType($reship['shop_type'])) {
            $msg = '非天猫渠道不处理极速建单';
            return false;
        }
        if (!$this->isTopspeedByFlagType($reship['flag_type'])) {
            $msg = '非极速换货单';
            return false;
        }

        // 幂等
        if ((int)$reship['change_order_id'] > 0 || (string)$reship['change_status'] === '1') {
            $msg = '换出订单已存在';
            if ((int)$reship['change_order_id'] > 0) {
                $this->releaseRefuseSendIntercept((int)$reship['change_order_id']);
            }
            return true;
        }

        // yjdf / yunmall 现网不本地建单
        $branchLib = kernel::single('ome_branch');
        $wmsType = $branchLib->getNodetypBybranchId($reship['branch_id']);
        if (in_array($reship['shop_type'], array('yunmall')) || $wmsType == 'yjdf') {
            $msg = '代发仓不生成换出单';
            $logMdl = app::get('ome')->model('operation_log');
            $logMdl->write_log('reship@ome', $reshipId, '极速换货17：' . $msg);
            return false;
        }

        $orderSdf = $reshipObj->create_order($reship);
        if (!$orderSdf || empty($orderSdf['order_id'])) {
            $msg = 'create_order 失败';
            return false;
        }

        kernel::single('console_reship')->releaseChangeFreeze($reshipId);

        $orderObj = app::get('ome')->model('orders');
        $origin = $orderObj->dump(array('order_id' => $reship['order_id']), 'order_bn');
        $payOrder = array(
            'order_id'        => $orderSdf['order_id'],
            'shop_id'         => $reship['shop_id'],
            'pay_status'      => '1',
            'pay_money'       => $orderSdf['total_amount'],
            'currency'        => 'CNY',
            'reship_order_bn' => $origin['order_bn'],
        );
        kernel::single('ome_reship')->payChangeOrder($payOrder);

        $this->markTopspeedOnChangeOrder($orderSdf['order_id']);
        $this->releaseRefuseSendIntercept((int)$orderSdf['order_id']);

        $logMdl = app::get('ome')->model('operation_log');
        $logMdl->write_log(
            'reship@ome',
            $reshipId,
            '极速换货17：提前生成换出订单[' . $orderSdf['order_bn'] . ']'
        );

        $msg = 'ok';
        return true;
    }

    /**
     * 平台 18：拒发新品 — 可恢复拦截（保留换出单）
     *
     * @param int    $changeOrderId
     * @param string $msg
     * @return bool
     */
    public function interceptRefuseSendNewGoods($changeOrderId, &$msg = null)
    {
        $changeOrderId = (int)$changeOrderId;
        $msg = '';
        if ($changeOrderId <= 0) {
            $msg = '无换出订单可拦截';
            return false;
        }

        $orderObj = app::get('ome')->model('orders');
        $order = $orderObj->dump(array('order_id' => $changeOrderId), 'order_id,ship_status,status,process_status');
        if (!$order) {
            $msg = '换出订单不存在';
            return false;
        }

        // 已出库：只告警
        $shipped = $orderObj->db->selectrow(
            "SELECT d.delivery_id FROM sdb_ome_delivery_order AS dord"
            . " LEFT JOIN sdb_ome_delivery AS d ON dord.delivery_id=d.delivery_id"
            . " WHERE dord.order_id=" . $changeOrderId
            . " AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' AND d.status='succ' LIMIT 1"
        );
        if ($shipped || in_array((string)$order['ship_status'], array('1', '2'), true)) {
            $msg = '换出订单已出库，无法拦截';
            app::get('ome')->model('operation_log')->write_log(
                'order_modify@ome',
                $changeOrderId,
                '极速换货18：拒发新品，订单已出库，仅告警不拦截'
            );
            return false;
        }

        $cancelRs = $orderObj->cancel_delivery($changeOrderId, true);
        $cancelOk = (!empty($cancelRs['rsp']) && $cancelRs['rsp'] === 'succ')
            || (isset($cancelRs['succ_num']) && (int)$cancelRs['succ_num'] > 0)
            || empty($cancelRs['msg']); // 无发货单时也可继续禁发

        // 无待撤销发货单也视为可继续打禁发
        $pending = $orderObj->db->selectrow(
            "SELECT d.delivery_id FROM sdb_ome_delivery_order AS dord"
            . " LEFT JOIN sdb_ome_delivery AS d ON dord.delivery_id=d.delivery_id"
            . " WHERE dord.order_id=" . $changeOrderId
            . " AND d.disabled='false' AND d.parent_id=0"
            . " AND d.status NOT IN('cancel','back','succ','return_back') LIMIT 1"
        );
        if (!$pending) {
            $cancelOk = true;
        }

        $err = '';
        kernel::single('ome_bill_label')->markBillLabel(
            $changeOrderId,
            '',
            self::LABEL_HOLD,
            'order',
            $err,
            0
        );
        $orderObj->update(array('is_delivery' => 'N'), array('order_id' => $changeOrderId));

        $logMsg = '极速换货18：拒发新品，可恢复拦截（禁发+撤销未出库发货单）';
        if (!$cancelOk && !empty($cancelRs['msg'])) {
            $logMsg .= '；撤销发货单部分失败:' . $cancelRs['msg'];
        }
        app::get('ome')->model('operation_log')->write_log('order_modify@ome', $changeOrderId, $logMsg);

        $msg = $cancelOk ? 'ok' : ('拦截已打标，撤销发货单失败:' . (isset($cancelRs['msg']) ? $cancelRs['msg'] : ''));
        return true;
    }

    /**
     * 解除 18 拒发拦截（改口再发）
     *
     * @param int $changeOrderId
     * @return bool
     */
    public function releaseRefuseSendIntercept($changeOrderId)
    {
        $changeOrderId = (int)$changeOrderId;
        if ($changeOrderId <= 0) {
            return false;
        }

        $billLabelMdl = app::get('ome')->model('bill_label');
        $exists = $billLabelMdl->dump(
            array(
                'bill_type'  => 'order',
                'bill_id'    => $changeOrderId,
                'label_code' => self::LABEL_HOLD,
            ),
            'id'
        );
        if ($exists) {
            $billLabelMdl->delete(array('id' => $exists['id']));
        }

        app::get('ome')->model('orders')->update(
            array('is_delivery' => 'Y'),
            array('order_id' => $changeOrderId, 'is_delivery' => 'N')
        );

        if ($exists) {
            app::get('ome')->model('operation_log')->write_log(
                'order_modify@ome',
                $changeOrderId,
                '极速换货：解除拒发新品禁发拦截'
            );
        }
        return true;
    }

    /**
     * 平台 ome.exchange.consigngoods.refuse：取消换出新订单
     * 有未发货发货单时先撤销；撤销失败打 SOMS_CANCEL_DELIVERY_FAIL 且不取消订单
     *
     * @param array $sdf return_bn / shop_id / seller_refuse_reason_id / leave_message
     * @return array rsp, msg
     */
    public function cancelChangeOrderOnConsigngoodsRefuse($sdf)
    {
        $returnBn = isset($sdf['return_bn']) ? trim((string)$sdf['return_bn']) : '';
        $shopId = isset($sdf['shop_id']) ? $sdf['shop_id'] : '';
        $reasonId = isset($sdf['seller_refuse_reason_id']) ? trim((string)$sdf['seller_refuse_reason_id']) : '';
        $leave = isset($sdf['leave_message']) ? trim((string)$sdf['leave_message']) : '';
        
        $memo = '换货拒绝发出新品';
        if ($reasonId !== '') {
            $memo .= '，原因ID:' . $reasonId;
        }
        
        if ($leave !== '') {
            $memo .= '，说明:' . $leave;
        }
        
        // check
        if ($returnBn === '') {
            return $this->error('换货单号为空，请检查');
        }
        
        // 获取换货申请单信息
        $filter = array('return_bn' => $returnBn);
        if ($shopId !== '' && $shopId !== null) {
            $filter['shop_id'] = $shopId;
        }
        $returnInfo = app::get('ome')->model('return_product')->dump($filter, 'return_id,return_bn');
        if (!$returnInfo || empty($returnInfo['return_id'])) {
            return $this->succ('未找到售后换货申请单，跳过');
        }
        
        $orderMdl = app::get('ome')->model('orders');
        $reshipMdl = app::get('ome')->model('reship');
        $logMdl = app::get('ome')->model('operation_log');
        
        // 获取换货单信息
        $reshipFilter = array(
            'return_id' => $returnInfo['return_id'],
            'return_type' => 'change',
            'status|notin' => array('cancel', 'back'),
        );
        $reshipList = $reshipMdl->getList('reship_id,change_order_id', $reshipFilter, 0, 1, 'reship_id DESC');
        $reshipInfo = $reshipList ? $reshipList[0] : array();
        
        // 获取换出订单ID
        $changeOrderId = $reshipInfo ? (int)$reshipInfo['change_order_id'] : 0;
        if ($changeOrderId <= 0) {
            $logMdl->write_log('return@ome', $returnInfo['return_id'], $memo . '：尚无换出订单，跳过取消');
            return $this->succ('尚无换出订单');
        }
        
        // 获取换出订单信息
        $orderInfo = $orderMdl->dump(array('order_id' => $changeOrderId), 'order_id,ship_status,status');
        if (!$orderInfo) {
            return $this->error('换出订单不存在');
        }
        
        if ((string)$orderInfo['status'] === 'dead') {
            return $this->succ('换出订单已取消');
        }
        
        // 订单可能拆成多张发货单，逐张判断是否允许撤消
        $dlyOrderFilter = array('order_id' => $changeOrderId);
        $dlyOrderList = app::get('ome')->model('delivery_order')->getList('delivery_id', $dlyOrderFilter);
        $deliveryIds = array();
        foreach ($dlyOrderList as $dlyOrderInfo) {
            if (!empty($dlyOrderInfo['delivery_id'])) {
                $deliveryIds[] = $dlyOrderInfo['delivery_id'];
            }
        }
        
        $shippedMsg = '';
        if ($deliveryIds) {
            $deliveryFilter = array('delivery_id' => $deliveryIds, 'parent_id' => 0, 'disabled' => 'false');
            $deliveryList = app::get('ome')->model('delivery')->getList('delivery_id,delivery_bn,status', $deliveryFilter);
            $closedStatus = array('cancel', 'back', 'return_back');
            foreach ($deliveryList as $deliveryInfo)
            {
                $dlyStatus = (string)$deliveryInfo['status'];
                
                if ($dlyStatus === 'succ') {
                    $shippedMsg = '发货单[' . $deliveryInfo['delivery_bn'] . ']已发货，无法撤消';
                    break;
                }
                
                if (in_array($dlyStatus, $closedStatus, true)) {
                    continue;
                }
            }
        }
        
        if ($shippedMsg !== '' || in_array((string)$orderInfo['ship_status'], array('1', '2'), true)) {
            if ($shippedMsg === '') {
                $shippedMsg = '换出订单已出库，无法取消';
            }
            
            $logMdl->write_log('order_modify@ome', $changeOrderId, $memo . '：' . $shippedMsg);
            
            return $this->error($shippedMsg);
        }
        
        // 撤消发货单
        $cancelDly = $orderMdl->cancel_delivery($changeOrderId, true);
        if (empty($cancelDly['rsp']) || $cancelDly['rsp'] !== 'succ') {
            $err = '';
            kernel::single('ome_bill_label')->markBillLabel(
                $changeOrderId,
                '',
                'SOMS_CANCEL_DELIVERY_FAIL',
                'order',
                $err,
                0
            );
            
            $failMsg = !empty($cancelDly['msg']) ? $cancelDly['msg'] : '撤消发货单失败';
            
            $logMdl->write_log('order_modify@ome', $changeOrderId, $memo . '：' . $failMsg);
            
            return $this->error($failMsg);
        }
        
        // 取消换出订单
        $cancelRs = $orderMdl->cancel($changeOrderId, $memo, true, 'sync', true);
        $ok = (!empty($cancelRs['rsp']) && ($cancelRs['rsp'] === 'succ' || $cancelRs['rsp'] === 'success'));
        if (!$ok) {
            $failMsg = !empty($cancelRs['msg']) ? $cancelRs['msg'] : '取消换出订单失败';
            
            $logMdl->write_log('order_modify@ome', $changeOrderId, $memo . '：' . $failMsg);
            
            return $this->error($failMsg);
        }
        
        if (!empty($reshipInfo['reship_id'])) {
            $logMdl->write_log('reship@ome', $reshipInfo['reship_id'], $memo . '：已取消换出订单');
        }
        
        return $this->succ('已取消换出订单');
    }

    /**
     * OMS 取消极速换货：取消换出新订单，并请求平台拒绝发出新品
     * 无极速标、非天猫、非换货单不请求平台
     *
     * @param int    $reshipId
     * @param string $memo
     * @return array
     */
    public function notifyConsigngoodsRefuseOnCancel($reshipId, $memo = '')
    {
        $reshipId = (int)$reshipId;
        $reshipInfo = app::get('ome')->model('reship')->dump($reshipId, 'reship_id,return_id,return_type,shop_id,shop_type,flag_type,change_order_id');
        if (!$reshipInfo) {
            return $this->succ('换货单不存在，跳过');
        }
        
        if ($reshipInfo['return_type'] !== 'change' || !$this->isTopspeedShopType($reshipInfo['shop_type']) || !$this->isTopspeedByFlagType($reshipInfo['flag_type'])) {
            return $this->succ('非极速换货，不请求平台');
        }

        $returnInfo = app::get('ome')->model('return_product')->dump(array('return_id' => $reshipInfo['return_id']), 'return_id,return_bn,shop_id');
        $returnBn = $returnInfo ? trim((string)$returnInfo['return_bn']) : '';
        if ($returnBn === '') {
            return $this->error('换货单号为空，不能请求平台');
        }

        $cancelRs = $this->cancelChangeOrderOnConsigngoodsRefuse(array(
            'return_bn' => $returnBn,
            'shop_id' => $reshipInfo['shop_id'],
            'leave_message' => $memo,
        ));
        
        // check
        if (empty($cancelRs['rsp']) || $cancelRs['rsp'] !== 'succ') {
            return $cancelRs;
        }
        
        // 拒绝原因ID，需属于当前换货单允许的原因；
        //@todo：可通过接口，获取拒绝换货原因列表：tmall.exchange.refusereason.get，获取拒绝原因列表；
        $seller_refuse_reason_id = 0;
        
        // 请求平台换货拒绝发出新品
        $requestParams = array(
            'dispute_id' => $returnBn,
            'seller_refuse_reason_id' => $seller_refuse_reason_id,
            'leave_message' => $memo,
        );
        $result = kernel::single('erpapi_router_request')->set('shop', $reshipInfo['shop_id'])->aftersale_consigngoodsRefuse($requestParams);
        $ok = (!empty($result['rsp']) && ($result['rsp'] === 'succ' || $result['rsp'] === 'success'));
        $logMdl = app::get('ome')->model('operation_log');
        if (!$ok) {
            $failMsg = !empty($result['msg']) ? $result['msg'] : '请求平台拒绝发出新品失败';
            $logMdl->write_log('reship@ome', $reshipId, '极速换货取消：' . $failMsg);
            return $this->error($failMsg);
        }
        $logMdl->write_log('reship@ome', $reshipId, '极速换货取消：已请求平台拒绝发出新品');
        return $this->succ('已请求平台拒绝发出新品');
    }

    /**
     * 手工或批量取消换出订单：订单带极速换货标签时回告平台拒绝发出新品
     * 订单已由取消流程处理，这里不再重复取消
     *
     * @param int    $orderId
     * @param string $memo
     * @return array
     */
    public function notifyConsigngoodsRefuseOnOrderCancel($orderId, $memo = '')
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) {
            return $this->succ('订单无效，跳过');
        }
        
        $orderInfo = app::get('ome')->model('orders')->dump(array('order_id' => $orderId), 'order_id,order_type,order_source');
        $isExchangeOrder = $orderInfo && ($orderInfo['order_type'] === 'exchange' || $orderInfo['order_source'] === 'platformexchange');
        if (!$isExchangeOrder) {
            return $this->succ('非换货订单，跳过');
        }
        
        if (!kernel::single('ome_bill_label')->existLabel($orderId, self::LABEL_TOPSPEED, 'order')) {
            return $this->succ('非极速换货订单，跳过');
        }

        $reshipFilter = array(
            'change_order_id' => $orderId,
            'return_type' => 'change',
            'status|notin' => array('cancel', 'back'),
        );
        $reshipList = app::get('ome')->model('reship')->getList('reship_id', $reshipFilter, 0, 1, 'reship_id DESC');
        if (!$reshipList) {
            return $this->error('未找到极速换货单，不能请求平台');
        }

        return $this->notifyConsigngoodsRefuseOnCancel($reshipList[0]['reship_id'], $memo);
    }

    /**
     * 售后申请单拒绝：若关联极速换货单则取消换出订单并回告平台
     *
     * @param int    $returnId
     * @param string $memo
     * @return array
     */
    public function notifyConsigngoodsRefuseOnReturnRefuse($returnId, $memo = '')
    {
        $returnId = (int)$returnId;
        if ($returnId <= 0) {
            return $this->succ('售后申请单无效，跳过');
        }

        $returnInfo = app::get('ome')->model('return_product')->dump(
            array('return_id' => $returnId),
            'return_id,return_bn,shop_id,shop_type,flag_type,return_type'
        );
        if (!$returnInfo) {
            return $this->succ('售后申请单不存在，跳过');
        }

        $reshipFilter = array(
            'return_id' => $returnId,
            'return_type' => 'change',
            'status|notin' => array('cancel', 'back'),
            'is_check|noequal' => '5',
        );
        $reshipList = app::get('ome')->model('reship')->getList(
            'reship_id,shop_type,flag_type,change_order_id,is_check',
            $reshipFilter,
            0,
            -1,
            'reship_id DESC'
        );
        if (!$reshipList) {
            return $this->succ('无关联换货单，跳过');
        }

        $reshipMdl = app::get('ome')->model('reship');
        $logMdl = app::get('ome')->model('operation_log');
        $lastRs = $this->succ('无极速换货需处理');
        foreach ($reshipList as $reshipInfo) {
            $shopType = !empty($reshipInfo['shop_type']) ? $reshipInfo['shop_type'] : $returnInfo['shop_type'];
            $flagType = !empty($reshipInfo['flag_type']) ? $reshipInfo['flag_type'] : $returnInfo['flag_type'];
            if (!$this->isTopspeedShopType($shopType) || !$this->isTopspeedByFlagType($flagType)) {
                continue;
            }

            kernel::single('console_reship')->releaseChangeFreeze($reshipInfo['reship_id']);
            $reshipMdl->update(array('is_check' => '5', 't_end' => time()), array('reship_id' => $reshipInfo['reship_id']));
            $logMdl->write_log('reship@ome', $reshipInfo['reship_id'], $memo !== '' ? $memo : '售后申请拒绝：同步拒绝极速换货单');

            $lastRs = $this->notifyConsigngoodsRefuseOnCancel($reshipInfo['reship_id'], $memo);
        }
        return $lastRs;
    }
}
