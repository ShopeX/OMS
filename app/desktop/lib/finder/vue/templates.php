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

class desktop_finder_vue_templates
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $context;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function handle(array $request): array
    {
        $action = $request['do'] ?? 'list';

        switch ($action) {
            case 'list':
                return $this->listTemplates($request);
            case 'save':
                return $this->saveTemplate($request);
            case 'delete':
                return $this->deleteTemplate($request);
            default:
                return ['success' => false, 'message' => 'Unknown action: ' . $action];
        }
    }

    /**
     * List all export templates for the current object type
     */
    private function listTemplates(array $request): array
    {
        $objectName = $this->context->getObjectName();
        $userId = $this->context->getCurrentUserId();

        $model = app::get('desktop')->model('export_template');
        $templates = $model->getList('et_id,et_name,et_filter', [
            'et_type' => $objectName
        ], 0, -1, 'et_id DESC');

        $result = [];
        foreach ($templates as $tpl) {
            $filter = unserialize($tpl['et_filter']);
            $result[] = [
                'id' => $tpl['et_id'],
                'name' => $tpl['et_name'],
                'exportType' => $filter['export_type'] ?? 'csv',
                'fields' => $filter['fields'] ?? [],
            ];
        }

        return ['success' => true, 'templates' => $result];
    }

    /**
     * Save a new export template
     */
    private function saveTemplate(array $request): array
    {
        $objectName = $this->context->getObjectName();
        $userId = $this->context->getCurrentUserId();

        $name = trim($request['name'] ?? '');
        if (empty($name)) {
            return ['success' => false, 'message' => 'Template name is required'];
        }

        $exportType = $request['export_type'] ?? 'csv';
        $fields = $request['fields'] ?? [];

        if (empty($fields) || !is_array($fields)) {
            return ['success' => false, 'message' => 'At least one field must be selected'];
        }

        $filterData = [
            'export_type' => $exportType,
            'fields' => $fields,
        ];

        $data = [
            'et_name' => $name,
            'et_type' => $objectName,
            'et_filter' => serialize($filterData),
        ];

        $model = app::get('desktop')->model('export_template');
        $model->save($data);

        return [
            'success' => true,
            'template' => [
                'id' => $data['et_id'],
                'name' => $name,
                'exportType' => $exportType,
                'fields' => $fields,
            ],
        ];
    }

    /**
     * Delete an export template
     */
    private function deleteTemplate(array $request): array
    {
        $id = intval($request['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid template ID'];
        }

        $model = app::get('desktop')->model('export_template');
        $result = $model->delete(['et_id' => $id]);

        if ($result) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Failed to delete template'];
        }
    }
}