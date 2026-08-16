<?php
/**
 * 天猫/淘宝售后平台识别业务 Lib
 *
 * 商责免费退（平台承担退货运费）识别与判定
 *
 * @author wangbiao@shopex.cn
 * @version 2026.08.13
 */
class ome_order_platform_taobao_aftersale
{
    /** reason */
    const REASON_REFUND_POSTAGE = '退运费';
    
    /** dispute_type */
    const DISPUTE_RETURN_GOODS_POSTAGE = 'RETURN_GOODS_POSTAGE';
    
    /** attribute.trigger */
    const TRIGGER_CN_POSTAGE_REFUND = 'cnPostageRefund';
    
    /** 商责退运费 tag_type */
    const TAG_TYPE_SHANGZE = '9';
    
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
}
