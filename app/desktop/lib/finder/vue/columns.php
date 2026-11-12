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

class desktop_finder_vue_columns
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private desktop_finder_vue_context $context;

    public function __construct(desktop_finder_vue_context $context)
    {
        $this->context = $context;
        // vue_mode=true 使 buildColumnsMeta() 优先返回 controller FINDER_COLS（而非仅读 schema）
        $this->context->setVueMode(true);
    }

    public function handle(array $request): array
    {
        $detailPages = $this->context->getDetailPages();
        $tabs = [];
        foreach ($detailPages as $method => $def) {
            $labelMethod = $def['method'];
            $object = $def['object'];
            // $detail_item = 'label' 是 property; detail_item() 是 method
            // 先取 property，没有则用 method name
            $label = isset($object->$labelMethod) ? $object->$labelMethod : null;
            if (!$label) {
                $label = $labelMethod;
            }
            $tabs[] = [
                'key'   => substr($labelMethod, 7),
                'label' => $label,
                'url'   => '?finderview=' . substr($labelMethod, 7),
            ];
        }

        $toolbarActions = $this->context->getToolbarActions();

        // Auto-inject delete button when use_buildin_recycle is enabled
        if ($this->context->useBuildinRecycle()) {
            $hasDelete = false;
            foreach ($toolbarActions as $a) {
                if (($a['action'] ?? '') === 'delete') {
                    $hasDelete = true;
                    break;
                }
            }
            if (!$hasDelete) {
                $toolbarActions[] = [
                    'label'   => app::get('desktop')->_('删除'),
                    'id'      => 'delete',
                    // 确认文案按 finder 标题通用化(此前硬编码「角色」导致所有 finder 删除确认都误报角色)
                    'confirm' => app::get('desktop')->_('确定要删除所选') . ($this->context->getTitle() ?: app::get('desktop')->_('记录')) . app::get('desktop')->_('吗？'),
                    'type'    => 'danger',
                ];
            }
        }

        // Auto-inject list export when use_buildin_export is enabled (aligns with legacy desktop finder)
        if ($this->context->useBuildinExport()) {
            $hasExport = false;
            foreach ($toolbarActions as $a) {
                if (($a['id'] ?? '') === 'export' || ($a['action'] ?? '') === 'export') {
                    $hasExport = true;
                    break;
                }
            }
            if (!$hasExport) {
                $toolbarActions[] = [
                    'id'    => 'export',
                    'label' => app::get('desktop')->_('导出'),
                    'type'  => 'default',
                ];
            }
        }

        // Auto-inject import button when use_buildin_import is enabled
        if ($this->context->useBuildinImport()) {
            $hasImport = false;
            foreach ($toolbarActions as $a) {
                if (($a['id'] ?? '') === 'import' || ($a['action'] ?? '') === 'import') {
                    $hasImport = true;
                    break;
                }
            }
            if (!$hasImport) {
                $toolbarActions[] = [
                    'id'    => 'import',
                    'label' => app::get('desktop')->_('导入'),
                    'type'  => 'default',
                ];
            }
        }

        // Auto-inject export template button when use_import_template is enabled
        if ($this->context->useImportTemplate()) {
            $hasTemplate = false;
            foreach ($toolbarActions as $a) {
                if (($a['id'] ?? '') === 'exportTemplate' || ($a['action'] ?? '') === 'exportTemplate') {
                    $hasTemplate = true;
                    break;
                }
            }
            if (!$hasTemplate) {
                $toolbarActions[] = [
                    'id'    => 'exportTemplate',
                    'label' => app::get('desktop')->_('导出模板'),
                    'type'  => 'default',
                ];
            }
        }

        // Whether this finder has view tabs (告诉前端是否需要请求 action=tabs)
        $hasViewTab = $this->context->useViewTab() && !empty($this->context->buildViewDefinitions());

        // Quick search options (dropdown field selector + keyword input)
        $searchOptions = $this->context->getSearchOptions();
        // If context doesn't have explicit searchOptions, try model's searchOptions()
        if (empty($searchOptions)) {
            $model = $this->context->getModel();
            if (method_exists($model, 'searchOptions')) {
                $searchOptions = $model->searchOptions();
            }
        }
        // Normalize to [{field, label}] array for frontend
        $searchOptionsList = [];
        foreach ($searchOptions as $field => $label) {
            $searchOptionsList[] = ['field' => $field, 'label' => $label];
        }

        return [
            'columns'        => $this->context->buildColumnsMeta(),
            'detailTabs'     => $tabs,
            'toolbarActions' => $toolbarActions,
            'selectable'     => $this->context->useBuildinSelectrow(),
            'useBuildinFilter' => $this->context->useBuildinFilter(),
            'useViewTab'     => $hasViewTab,
            'useBuildinRefresh' => $this->context->useBuildinRefresh(),
            'useBuildinSetcol'  => $this->context->useBuildinSetcol(),
            'searchOptions'  => $searchOptionsList,
        ];
    }
}
