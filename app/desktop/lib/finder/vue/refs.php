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

class desktop_finder_vue_refs
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
    }
    
    public function handle(array $request): array
    {
        $type = $request['type'] ?? '';
        
        if (!is_string($type) || strpos($type, 'table:') !== 0) {
            return ['options' => [], 'total' => 0, 'needRemote' => false];
        }
        
        $rest = substr($type, 6); // remove 'table:'
        
        // Parse model and app: 'table:model@app' or 'table:model'
        if (strpos($rest, '@') !== false) {
            [$modelName, $appId] = explode('@', $rest, 2);
        } else {
            // Same-app reference: default to context's app
            $modelName = $rest;
            $appId = $this->context->getObjectName();
            $appId = explode('_mdl_', $appId)[0];
        }
        
        $model = app::get($appId)->model($modelName);
        $schema = $model->get_schema();
        
        $idColumn = $schema['idColumn'] ?? 'id';
        $textColumn = $model->textColumn ?? $idColumn;
        
        $options = [];
        $rows = $model->getList($idColumn . ',' . $textColumn, [], 0, 100);
        foreach ($rows as $row) {
            $options[] = [
                'value' => $row[$idColumn],
                'label' => $row[$textColumn],
            ];
        }
        
        return [
            'options' => $options,
            'total' => count($options),
            'needRemote' => false
        ];
    }
}