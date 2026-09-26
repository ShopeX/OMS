<?php

class ticket_workflow_logger extends ticket_abstract
{
    public function recordTemplateLog($templateId, array $data, $oldTemplateData = null, $isEdit = false)
    {
        if (!$templateId) {
            return;
        }

        $omeLogMdl = app::get('ome')->model('operation_log');
        $logKey = $isEdit ? 'ticket_workflow_edit@ticket' : 'ticket_workflow_add@ticket';
        $operation = $isEdit ? '编辑' : '新建';
        $nodeCount = isset($data['nodes']) && is_array($data['nodes']) ? count($data['nodes']) : 0;
        $memo = "审批流{$operation}：模板名称=" . ($data['template_name'] ?? '')
            . "，场景类型=" . ($data['scene_type'] ?? '')
            . "，节点数量=" . $nodeCount;

        $logId = $omeLogMdl->write_log($logKey, $templateId, $memo);
        if (!$logId) {
            return;
        }

        $snapshoot = ($isEdit && !empty($oldTemplateData))
            ? json_encode($oldTemplateData, JSON_UNESCAPED_UNICODE)
            : '';
        $updated = json_encode($data, JSON_UNESCAPED_UNICODE);

        $shootMdl = app::get('ome')->model('operation_log_snapshoot');
        $insertData = array(
            'log_id' => $logId,
            'snapshoot' => $snapshoot,
            'updated' => $updated,
        );
        $shootMdl->insert($insertData);
    }
}
