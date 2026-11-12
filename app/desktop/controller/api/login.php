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
 * Desktop Login Log API Controller
 * 
 * Handles login log list display via Vue
 * Uses desktop_mdl_login which delegates to pam model
 */
class desktop_ctl_api_login extends desktop_ctl_api_base
{
    protected static $objectName = 'desktop_mdl_login';

    /**
     * GET - List/filters/columns (finder dispatch)
     */
    public function index(): void
    {
        $action = $_GET['action'] ?? 'list';

        // finderDispatch only accepts universal actions
        $universalActions = ['list', 'filters', 'columns', 'views', 'full', 'cols'];
        if (in_array($action, $universalActions, true)) {
            $this->finderDispatch([
                'objectName' => 'desktop_mdl_login',
                'finderAlias' => 'vue',
            
            'actions' => [],
        ]);
            return;
        }

        $this->fail('Invalid action: ' . $action);
    }

    /**
     * GET - Get login log columns
     */
    public function columns(): void
    {
        $schema = $this->getSchema();
        $columns = $this->buildColumnsMeta($schema);

        $this->succ([
            'columns' => $columns,
        ]);
    }

    /**
     * GET - Get login log filters
     */
    public function filters(): void
    {
        $schema = $this->getSchema();
        $filters = $this->buildFilterDefinitions($schema);

        $this->succ([
            'filters' => $filters,
        ]);
    }

    /**
     * GET - Get views (tabs)
     */
    public function views(): void
    {
        $views = $this->buildViewDefinitions();

        $this->succ([
            'views' => $views,
        ]);
    }
}
