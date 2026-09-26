<?php

/**
 * 操作日志操作名称定义
 * @author ome
 * @copyright www.shopex.cn 2011.2.21
 *
 */
class goldentra_operation_log
{

    /**
     * 定义当前APP下的操作日志的所有操作名称列表
     * type键值由表名@APP名称组成
     * @access public
     * @return Array
     */
    function get_operations()
    {
        $operations = array(
            'pos_material_add' => array('name' => '主档信息添加', 'type' => 'pos_material@goldentra'),
            'pos_material_update' => array('name' => '主档信息更新', 'type' => 'pos_material@goldentra'),
            'sap_material_add' => array('name' => 'SAP主档新增', 'type' => 'material@goldentra'),
            'sap_material_update' => array('name' => 'SAP主档更新', 'type' => 'material@goldentra'),
            'sap_material_remap' => array('name' => 'SAP主档重映射', 'type' => 'material@goldentra'),
            'daily_goldprice_edit' => array('name' => '每日金价修改', 'type' => 'daily_goldprice@goldentra'),
            'invest_goldprice_sync' => array('name' => '投资金价同步', 'type' => 'invest_goldprice@goldentra'),
            'sap_stock_process_succ' => array('name' => '库存比对成功', 'type' => 'stock@goldentra'),
            'sap_stock_process_fail' => array('name' => '库存比对失败', 'type' => 'stock@goldentra'),
            'sap_stock_process_skip' => array('name' => '库存比对跳过', 'type' => 'stock@goldentra'),
        );
        return array('goldentra' => $operations);
    }

}