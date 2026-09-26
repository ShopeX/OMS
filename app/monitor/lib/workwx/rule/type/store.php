<?php

/**
 * 企微通知规则 - 门店条件
 */
class monitor_workwx_rule_type_store extends monitor_workwx_rule_type_abstract implements monitor_workwx_rule_type_interface
{
    public function _prepareUI(&$tpl, $val)
    {
        $ruleId = !empty($val[0]) ? intval($val[0]) : 0;
        $init   = $tpl->pagedata['init'];
        $selectedIds = array();
        if (!empty($init['content']) && is_array($init['content'])) {
            $selectedIds = array_map('intval', $init['content']);
        }

        $occupied = kernel::single('monitor_workwx_config')->getOccupiedStoreMap($ruleId);
        $stores   = kernel::single('monitor_workwx_config')->getStoreOptions();
        foreach ($stores as $key => $store) {
            $storeId = intval($store['store_id']);
            $isOccupied = isset($occupied[$storeId]);
            $isSelected = in_array($storeId, $selectedIds, true);
            $stores[$key]['is_checked'] = $isSelected ? 'true' : 'false';
            $stores[$key]['is_disabled'] = ($isOccupied && !$isSelected) ? 'true' : 'false';
            $stores[$key]['occupied_rule_name'] = $isOccupied ? $occupied[$storeId]['rule_name'] : '';
        }

        $tpl->pagedata['stores']        = $stores;
        $tpl->pagedata['o2o_installed'] = app::get('o2o')->is_installed();
    }

    public function checkParams($params)
    {
        $storeIds = array_filter(array_map('intval', (array) $params['store_id']));
        if (empty($storeIds)) {
            return '请至少选择一个门店';
        }
        if (!app::get('o2o')->is_installed()) {
            return '未安装 O2O 应用，无法选择门店';
        }
        $occupied = kernel::single('monitor_workwx_config')->getOccupiedStoreMap(intval($params['rule_id']));
        foreach ($storeIds as $storeId) {
            if (isset($occupied[$storeId])) {
                return sprintf(
                    '门店 %s/%s 已属于通知规则「%s」，一个门店只能归属一条规则',
                    $occupied[$storeId]['store_bn'],
                    $occupied[$storeId]['store_name'],
                    $occupied[$storeId]['rule_name']
                );
            }
        }
        return true;
    }

    public function roleToString($params)
    {
        $storeIds = array_filter(array_map('intval', (array) $params['store_id']));
        $rows     = app::get('o2o')->model('store')->getList('store_id,store_bn,name', array('store_id' => $storeIds));
        $caption  = '';
        foreach ((array) $rows as $row) {
            $caption .= '，[' . $row['store_bn'] . ']' . $row['name'];
        }
        $caption = '门店 ' . preg_replace('/^，/u', '', $caption);
        $role    = array(
            'role'    => 'store',
            'caption' => $caption,
            'content' => $storeIds,
        );
        return json_encode($role);
    }
}
