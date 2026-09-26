<?php
/**
 * 销售表异常状态位常量
 * 
 * 使用二进制位运算来标识不同字段的异常情况
 * 每个字段对应一个位位置，从0开始
 * 
 * @package tongyioil
 * @author system
 * @version 1.0
 */

/**
 * 销售表异常状态位常量
 */
class tongyioil_constants_abnormal_bill
{
    // 异常状态位定义 (从0开始)  1 2 4 8  16 32
    const ORDER_BN_MISSING = 1;           // 位0: 订单号不可以为空
    const ACCOUNT_MISSING = 2;           // 位1: 会计科目不可为空
    const TAX_ACCOUNT_MISSING = 4;           // 位2: 税率会计科目不可为空
    const POSTINGKEY_MISSING = 8;           // 位3: 记账码不可为空
    const COSTCENTER_MISSING = 16;           // 位4: 成本中心不可为空
    const CUSTOMER_MISSING = 32;           // 位5: 客户不可为空
    const SAP_SALE_BN_MISSING = 64;           // 位6: SAP销售单号不可为空
    const DRCR_MISSING = 128;      // 贷会计科目
    const GAPDRCR_MISSING = 256;      // 贷会计科目
    const ACTUALLYAMOUNT_MISSING = 512;      // actually_amount
    const PLATFORMAMOUNT_MISSING = 1024;      // platform_amount
    const ITEMS_MISSING = 2048;//ITEM
    const DETAIL_MISSING = 4096;
     const BILL_MONEY_UNBANANCE = 8192; // 位13: 平台承担明细税码为空
    // 所有字段正常
    const ALL_NORMAL = 0;
    
    // 所有字段异常
    const ALL_ABNORMAL = 2047; // 2^11 - 1
    
    /**
     * 获取异常状态位描述数组
     * 
     * @return array 位位置 => 字段描述
     */
    private static function getDescriptionsArray()
    {
        return [
            0 => '订单号不可以为空',
            1=>'会计科目或客户有一个不可为空',
            2=>'税率会计科目不可为空',
            3=>'记账码不可为空',
            4=>'成本中心不可为空',
            5=>'客户不可为空',
            6=>'SAP销售单号不可为空',
            7=>'借/贷会计科目至少一个不可为空',
            8=>'GAP借/贷会计科目至少一个不可为空',
            9=>'actually_amount会计科目不可为空',
            10=>'platform_amount会计科目不可为空',
            11=>'items 缺失',
            12=>'detail 缺失',
            13=>'借/贷金额不平',
        ];
    }
    
    /**
     * 获取异常状态位描述
     * 
     * @param int $bitPosition 位位置 (0-10)
     * @return string 字段描述
     */
    public static function getBitDescription($bitPosition)
    {
        $descriptions = self::getDescriptionsArray();
        return isset($descriptions[$bitPosition]) ? $descriptions[$bitPosition] : '未知字段';
    }
    
    /**
     * 获取所有异常状态位描述
     * 
     * @return array 位位置 => 字段描述
     */
    public static function getAllBitDescriptions()
    {
        $descriptions = [];
        for ($i = 0; $i < 15; $i++) {
            $descriptions[$i] = self::getBitDescription($i);
        }
        return $descriptions;
    }
    
    /**
     * 根据位位置计算对应的值
     * 
     * @param int $bitPosition 位位置 (0-10)
     * @return int 对应的值 (2^bitPosition)
     */
    public static function getBitValue($bitPosition)
    {
        if ($bitPosition < 0 || $bitPosition > 15) {
            return 0;
        }
        
        // 使用位移操作计算 2^bitPosition
        return 1 << $bitPosition;
    }
    
    /**
     * 获取所有异常状态位描述数组
     * 
     * @return array 位位置 => 字段描述
     */
    public static function getAllDescriptions()
    {
        return self::getDescriptionsArray();
    }
    
    /**
     * 分析异常状态值包含哪些异常类型
     * 
     * @param int $abnormalStatus 异常状态值
     * @return array 包含的异常类型信息
     *               [
     *                   'total_count' => int,      // 异常总数
     *                   'abnormal_types' => array, // 异常类型列表
     *                   'bit_positions' => array,  // 异常位位置列表
     *                   'descriptions' => array    // 异常描述列表
     *               ]
     */
    public static function analyzeAbnormalStatus($abnormalStatus)
    {
        $abnormalStatus = (int)$abnormalStatus;
        
        if ($abnormalStatus == 0) {
            return [
                'total_count' => 0,
                'abnormal_types' => [],
                'bit_positions' => [],
                'descriptions' => []
            ];
        }
        
        $abnormalTypes = [];
        $bitPositions = [];
        $descriptions = [];
        
        // 遍历所有可能的位位置 (0-10)
        for ($i = 0; $i < 15; $i++) {
            $bitValue = self::getBitValue($i);
            
            // 使用位与运算检查该位是否被设置
            if (($abnormalStatus & $bitValue) > 0) {
                $bitPositions[] = $i;
                $description = self::getBitDescription($i);
                $descriptions[] = $description;
                
                // 构建异常类型信息
                $abnormalTypes[] = [
                    'bit_position' => $i,
                    'bit_value' => $bitValue,
                    'description' => $description,
                    'constant_name' => self::getConstantNameByBitPosition($i)
                ];
            }
        }
        
        return [
            'total_count' => count($abnormalTypes),
            'abnormal_types' => $abnormalTypes,
            'bit_positions' => $bitPositions,
            'descriptions' => $descriptions
        ];
    }
    
    /**
     * 根据位位置获取对应的常量名称
     * 
     * @param int $bitPosition 位位置 (0-10)
     * @return string 常量名称
     */
    private static function getConstantNameByBitPosition($bitPosition)
    {
        $constantNames = [
            0 => 'ORDER_BN_MISSING',
            1 => 'ACCOUNT_MISSING',
            2 => 'TAX_ACCOUNT_MISSING',
            3 => 'POSTINGKEY_MISSING',
            4 => 'COSTCENTER_MISSING',
            5 => 'CUSTOMER_MISSING',
            6 => 'SAP_SALE_BN_MISSING',
            7=>'DRCR_MISSING',
            8=>'GAPDRCR_MISSING',
            9=>'ACTUALLYAMOUNT_MISSING',
            10=>'PLATFORMAMOUNT_MISSING',
            11=>'ITEMS_MISSING',
            12=>'DETAIL_MISSING',
            13=>'BILL_MONEY_UNBANANCE',
        ];
        
        return isset($constantNames[$bitPosition]) ? $constantNames[$bitPosition] : 'UNKNOWN';
    }
    
    /**
     * 获取异常状态的简要描述
     * 
     * @param int $abnormalStatus 异常状态值
     * @return string 简要描述
     */
    public static function getAbnormalStatusSummary($abnormalStatus)
    {
        $analysis = self::analyzeAbnormalStatus($abnormalStatus);
        
        if ($analysis['total_count'] == 0) {
            return '正常';
        }
        
        if ($analysis['total_count'] == 1) {
            return $analysis['descriptions'][0];
        }
        
        return "包含{$analysis['total_count']}种异常：" . implode('、', $analysis['descriptions']);
    }
    
}
