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
 * 审批流日志
 *
 * @author wangbiao@shopex.cn
 * @version 2025.07.23
 */
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
