<?php
/**
 * 财务基础模块操作日志服务类
 * 
 * @author Assistant
 * @version 1.0
 * @describe 处理财务基础模块的操作日志记录
 */
class financebase_operation_log
{
    /**
     * 获取支持的操作类型
     * 
     * @return array 操作类型配置
     */
    public function get_operations()
    {
        $operations = array(
            'bill_category_rules_add' => array('name' => '新增收支分类', 'type' => 'bill_category_rules@financebase'),
            'bill_category_rules_edit' => array('name' => '编辑收支分类', 'type' => 'bill_category_rules@financebase'),
            'bill_category_rules_delete' => array('name' => '收支分类置为无效', 'type' => 'bill_category_rules@financebase'),
            'bill_category_rules_restore' => array('name' => '收支分类恢复', 'type' => 'bill_category_rules@financebase'),
            'account_chart_add' => array('name' => '会计科目新增', 'type' => 'account_chart@financebase'),
            'account_chart_edit' => array('name' => '会计科目编辑', 'type' => 'account_chart@financebase'),
            'account_chart_delete' => array('name' => '会计科目置为无效', 'type' => 'account_chart@financebase'),
            'account_chart_restore' => array('name' => '会计科目恢复', 'type' => 'account_chart@financebase'),
            'gap_add' => array('name' => '差异类型新增', 'type' => 'gap@financebase'),
            'gap_edit' => array('name' => '差异类型编辑', 'type' => 'gap@financebase'),
            'gap_delete' => array('name' => '差异类型置为无效', 'type' => 'gap@financebase'),
            'gap_restore' => array('name' => '差异类型恢复', 'type' => 'gap@financebase'),
        );

        return array('financebase' => $operations);
    }
}
