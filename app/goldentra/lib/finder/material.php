<?php

class goldentra_finder_material
{
    var $detail_process = '处理信息';
    var $detail_changelog = '变更日志';

    /**
     * SAP主档标记列（bill_label，bill_type=goldentra_material）
     */
    public $column_material_label = '标记';
    public $column_material_label_width = 200;
    public $column_material_label_order = 25;

    public function column_material_label($row, $list)
    {
        $material_id = $row['material_id'];
        $labelList = $this->__getMaterialLabel($list);
        $dataList = !empty($labelList[$material_id]) ? $labelList[$material_id] : array();
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

    private function __getMaterialLabel($list)
    {
        static $arrMaterialLabel;

        if (isset($arrMaterialLabel)) {
            return $arrMaterialLabel;
        }

        $arrMaterialLabel = array();
        $ids = array();
        foreach ($list as $val) {
            if (!empty($val['material_id'])) {
                $ids[] = $val['material_id'];
            }
        }
        if (empty($ids)) {
            return $arrMaterialLabel;
        }

        $billLabelObj = app::get('ome')->model('bill_label');
        $labelData = $billLabelObj->getBIllLabelList($ids, 'goldentra_material');
        foreach ((array) $labelData as $val) {
            $mid = $val['bill_id'];
            $arrMaterialLabel[$mid][] = array(
                'label_id' => $val['label_id'],
                'label_name' => $val['label_name'],
                'label_color' => $val['label_color'],
                'label_desc' => $val['label_desc'],
            );
        }

        return $arrMaterialLabel;
    }

    public function detail_changelog($material_id)
    {
        $render = app::get('goldentra')->render();
        $logs = goldentra_material_changelog::readLogs($material_id);
        foreach ($logs as $k => $log) {
            $ts = isset($log['operate_time']) ? (int) $log['operate_time'] : 0;
            $logs[$k]['operate_time'] = $ts > 0 ? date('Y-m-d H:i:s', $ts) : '';
            if (!isset($logs[$k]['op_name']) || $logs[$k]['op_name'] === '') {
                $logs[$k]['op_name'] = 'system';
            }
        }
        $render->pagedata['logs'] = $logs;
        return $render->fetch('admin/material/detail_changelog.html');
    }

    public function detail_process($material_id)
    {
        $row = app::get('goldentra')->model('material')->dump($material_id, 'process_status,process_msg,process_time');
        $render = app::get('goldentra')->render();
        $render->pagedata['row'] = $row;
        return $render->fetch('admin/material/detail_process.html');
    }
}
