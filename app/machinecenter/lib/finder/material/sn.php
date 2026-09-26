<?php

/**
 * @Author  : xueding@shopex.cn
 * @Date    : 2025/4/17
 * @Describe: 机器中心
 */

class machinecenter_finder_material_sn
{
    var $addon_cols = 'user_province,user_city,user_district,user_town,user_village,ship_province,ship_city,ship_district,ship_town,ship_village';
    public $detail_basic = 'SN流水详情';

    public function detail_basic($sn_id)
    {
        $render      = app::get('machinecenter')->render();
        $snMdl       = app::get('machinecenter')->model('material_sn');
        $snFlowModel = app::get('machinecenter')->model('material_sn_flow');
        $flow_list   = $snFlowModel->getList('*', ['sn_id' => $sn_id], 0, -1, 'id desc');

        // 当前唯一码，用于明细页「编辑唯一码」
        $current_sn = '';
        if ($sn_id) {
            $snRow = $snMdl->db_dump($sn_id, 'sn');
            $current_sn = $snRow ? $snRow['sn'] : '';
        }
        $finder_id = isset($_GET['_finder']['finder_id']) ? $_GET['_finder']['finder_id'] : '';

        $columns            = $snFlowModel->_columns();
        $columns['ship_ad'] = [
            'label'   => '收货人地区',
            'in_list' => true,
            'order'   => 211,
        ];
        $columns['user_ad'] = [
            'label'   => '使用人地区',
            'in_list' => true,
            'order'   => 301,
        ];
        $detailHeader       = $this->formatHeaders($columns);

        // 获取仓库映射 branch_id => branch_bn
        $branchIds = array_unique(array_filter(array_column($flow_list, 'branch_id')));
        $branchMap = [];
        if (!empty($branchIds)) {
            $branchMdl = app::get('ome')->model('branch');
            $branchList = $branchMdl->getList('branch_id,branch_bn,name', ['branch_id' => $branchIds]);
            foreach ($branchList as $branch) {
                $branchMap[$branch['branch_id']] = $branch['branch_bn'] ?: $branch['name'];
            }
        }

        // 敏感字段列表
        $sensitiveFields = [
            'buyer_nickname', 'buyer_name', 'buyer_mobile', 'buyer_email', 'buyer_address',
            'ship_name', 'ship_mobile', 'ship_email', 'ship_address',
            'user_name', 'user_mobile', 'user_email', 'user_address'
        ];

        // io_status 映射：0=出库, 1=入库
        $ioStatusMap = ['0' => '出库', '1' => '入库'];
        
        foreach ($flow_list as $key => &$val) {
            // 组装地区信息
            $val['ship_ad'] = $val['ship_province'] . $val['ship_city'] . $val['ship_district'] . $val['ship_town'] . $val['ship_village'];
            $val['user_ad'] = $val['user_province'] . $val['user_city'] . $val['user_district'] . $val['user_town'] . $val['user_village'];
            
            // 转换 io_status 为文字描述
            if (isset($val['io_status']) && isset($ioStatusMap[$val['io_status']])) {
                $val['io_status'] = $ioStatusMap[$val['io_status']];
            }
            
            // 转换 branch_id 为 branch_bn
            if (!empty($val['branch_id']) && isset($branchMap[$val['branch_id']])) {
                $val['branch_id'] = $branchMap[$val['branch_id']];
            }

            // 处理敏感字段脱敏 - 保持加密状态，由前端解密按钮控制
            foreach ($sensitiveFields as $field) {
                if (isset($val[$field]) && $val[$field]) {
                    $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($val[$field]);
                    if ($is_encrypt) {
                        $encrypt     = kernel::single('ome_view_helper2')->modifier_ciphertext($val[$field], 'order', $field);
                        $val[$field] = '<span sensitive-field="' . $field . '">' . $encrypt . '</span>';
                    }
                }
            }
        }

        unset($detailHeader['buyer_email'], $detailHeader['buyer_address'], $detailHeader['ship_email'], $detailHeader['io_time'], $detailHeader['up_time'], $detailHeader['shop_id']);

        $render->pagedata['header']           = $detailHeader;
        $render->pagedata['flow_list']        = $flow_list;
        $render->pagedata['sn_id']            = $sn_id;
        $render->pagedata['current_sn']       = $current_sn;
        $render->pagedata['finder_id']        = $finder_id;
        $render->pagedata['sensitive_fields'] = $sensitiveFields;

        return $render->fetch('sn/finder_detail.html', 'machinecenter');
    }

    public $detail_operate_logs = '操作日志';

    public function detail_operate_logs($id)
    {
        $render  = app::get('machinecenter')->render();
        $logObj  = app::get('ome')->model('operation_log');
        $logData = $logObj->read_log(array ('obj_id' => $id, 'obj_type' => 'material_sn@machinecenter'), 0, -1, 'log_id desc');
        foreach ($logData as $k => $v) {
            $logData[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
        }
        $render->pagedata['datalist'] = $logData;
        return $render->fetch('sn/detail_log.html');
    }


    /**
     * SN标记列（复用订单标记 bill_label，bill_type=material_sn）
     */
    public $column_sn_label = '标记';
    public $column_sn_label_width = 260;
    public $column_sn_label_order = 32;

    public function column_sn_label($row, $list)
    {
        $sn_id = $row['id'];
        $labelList = $this->__getSnLabel($list);
        $dataList = !empty($labelList[$sn_id]) ? $labelList[$sn_id] : array();
        if (empty($dataList)) {
            return '';
        }

        $str = array();
        foreach ($dataList as $val) {
            $label_desc = $val['label_desc'];
            if ($label_desc) {
                $str[] = sprintf(
                    "<span onmouseover='bindFinderColTip(event)' rel='%s' style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;'>%s</span>",
                    $label_desc,
                    $val['label_color'],
                    $val['label_color'],
                    $val['label_name']
                );
            } else {
                $str[] = sprintf(
                    "<span style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;'>%s</span>",
                    $val['label_color'],
                    $val['label_color'],
                    $val['label_name']
                );
            }
        }

        return '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">' . implode('', $str) . '</div>';
    }

    private function __getSnLabel($list)
    {
        static $arrSnLabel;

        if (isset($arrSnLabel)) {
            return $arrSnLabel;
        }

        $arrSnLabel = array();
        $snIds = array();
        foreach ($list as $val) {
            if (!empty($val['id'])) {
                $snIds[] = $val['id'];
            }
        }
        if (empty($snIds)) {
            return $arrSnLabel;
        }

        $billLabelObj = app::get('ome')->model('bill_label');
        $labelData = $billLabelObj->getBIllLabelList($snIds, 'material_sn');
        foreach ((array) $labelData as $val) {
            $sn_id = $val['bill_id'];
            $arrSnLabel[$sn_id][] = array(
                'label_id' => $val['label_id'],
                'label_name' => $val['label_name'],
                'label_color' => $val['label_color'],
                'label_desc' => $val['label_desc'],
            );
        }

        return $arrSnLabel;
    }

    public $column_confirm = '操作';
    public $column_confirm_width = "150";
    public $column_confirm_order = "1";

    public function column_confirm($row)
    {
        $find_id = isset($_GET['_finder']['finder_id']) ? $_GET['_finder']['finder_id'] : '';
        $id      = $row['id'];
        
        $btns = [];
        
        // 编辑按钮
        $editUrl = 'index.php?app=machinecenter&ctl=admin_sn&act=edit&p[0]=' . $id;
        if ($find_id) {
            $editUrl .= '&finder_id=' . $find_id;
        }
        $btns[] = '<a href="' . $editUrl . '" target="_blank">编辑</a>';
        
        return implode(' ', $btns);
    }

    public $column_ship_ad = '收货人地区';
    public $column_ship_ad_width = 260;
    public $column_ship_ad_order = 30;

    public function column_ship_ad($row, $list)
    {
        return $row['ship_province'] . $row['ship_city'] . $row['ship_district'] . $row['ship_town'] . $row['ship_village'];
    }

    public $column_user_ad = '使用人地区';
    public $column_user_ad_width = 260;
    public $column_user_ad_order = 30;

    public function column_user_ad($row, $list)
    {
        return $row['user_province'] . $row['user_city'] . $row['user_district'] . $row['user_town'] . $row['user_village'];
    }

    public function formatHeaders($columns)
    {
        $displayColumns = array_filter($columns, function ($column) {
            return (isset($column['in_list']) && $column['in_list']);
        });

        // 为没有 order 字段的列设置默认值 999
        $order = [];
        foreach ($displayColumns as $key => $column) {
            $order[$key] = isset($column['order']) ? $column['order'] : 999;
        }

        // 按 order 排序
        asort($order);
        
        // 按排序后的顺序重组 headColumns
        $headColumns = [];
        foreach ($order as $key => $ord) {
            $headColumns[$key] = $displayColumns[$key]['label'] ?? $key;
        }
        
        return $headColumns;
    }

}
