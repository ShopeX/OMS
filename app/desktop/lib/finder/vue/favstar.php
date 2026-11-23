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
 * Vue Finder favstar handler
 * Toggle / get favorite star state per user per finder
 */

class desktop_finder_vue_favstar
{
    private desktop_finder_vue_context $context;

    public function __construct(desktop_finder_vue_context $context)
    {
        $this->context = $context;
    }

    public function handle(array $request): array
    {
        $action = $request['favAction'] ?? 'get';

        switch ($action) {
            case 'toggle':
                return $this->toggle($request);
            case 'get':
            default:
                return $this->get();
        }
    }

    /**
     * Get favstar IDs for current user + finder
     */
    private function get(): array
    {
        $favRows = $this->getFavRows();
        $ids = [];
        foreach ($favRows as $key => $val) {
            if (intval($val) === 1 && strpos($key, 'id-') === 0) {
                $ids[] = substr($key, 3);
            }
        }
        return ['favstarIds' => $ids];
    }

    /**
     * Toggle a single row's favstar state
     */
    private function toggle(array $request): array
    {
        $id = $request['id'] ?? null;
        if (!$id) {
            return ['error' => 'id is required'];
        }

        $confKey = $this->getConfKey();
        $userId = $this->context->getCurrentUserId();
        if (!$userId) {
            return ['error' => 'not logged in'];
        }

        $favRows = (array) app::get('desktop')->getConf($confKey);
        $key = 'id-' . $id;

        if (intval($favRows[$key] ?? 0) === 1) {
            $favRows[$key] = 0;
            $starred = false;
        } else {
            $favRows[$key] = 1;
            $starred = true;
        }

        // Clean up zeros
        $favRows = array_filter($favRows);
        app::get('desktop')->setConf($confKey, $favRows);

        return ['id' => $id, 'starred' => $starred];
    }

    private function getFavRows(): array
    {
        $confKey = $this->getConfKey();
        return (array) app::get('desktop')->getConf($confKey);
    }

    private function getConfKey(): string
    {
        $objectName = $this->context->getObjectName();
        $finderAlias = $this->context->getFinderAlias();
        $userId = $this->context->getCurrentUserId();
        return 'favstar.' . $objectName . '_' . $finderAlias . '.' . $userId;
    }
}
