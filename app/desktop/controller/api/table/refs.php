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

class desktop_ctl_api_table_refs extends desktop_ctl_api_base
{
    public function index(): void
    {
        $this->actionRefs();
    }

    private function buildObjectName(): string
    {
        $model = $_GET['model'] ?? '';
        $appId = $_GET['app_id'] ?? '';
        
        if (!$model) {
            throw new Exception('model is required');
        }
        
        return $appId . '_mdl_' . $model;
    }

    protected function actionRefs(): void
    {
        $model = $_GET['model'] ?? '';
        $appId = $_GET['app_id'] ?? '';
        
        // Set type so that refs handler can parse it correctly
        $_GET['type'] = 'table:' . $model . '@' . $appId;
        
        $handler = new desktop_finder_vue_refs(
            new desktop_finder_vue_context([
                'objectName' => $this->buildObjectName(),
                'sensitiveFields' => [],
            ])
        );
        $result = $handler->handle($_GET);
        $this->succ($result);
    }
}
