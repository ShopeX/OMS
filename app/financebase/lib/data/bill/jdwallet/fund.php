<?php
/**
 * 处理京东钱包导入
 * 支持多工作表Excel文件（结算表和资金表）
 *
 * @author AI Assistant
 * @version 1.0
 */

class financebase_data_bill_jdwallet_fund extends financebase_data_bill_jdwallet
{
    /**
     * 获取资金表标题定义（与京东日账单基本一致，但缺少结算状态字段）
     * @return array
     */
    public function getTitle()
    {
        return parent::getFundTitle();
    }

}
