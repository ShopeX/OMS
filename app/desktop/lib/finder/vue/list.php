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

class desktop_finder_vue_list
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
        $page = $this->context->normalizePage($request['page'] ?? 1);
        $pageSize = $this->context->normalizePageSize($request['page_size'] ?? 20);
        $orderBy = $request['orderBy'] ?? null;
        $orderType = $request['orderType'] ?? 'desc';
        $detailUrl = $request['detailUrl'] ?? null;

        $filters = $this->context->buildFilterConditions($request['filters'] ?? []);
        $orderClause = $this->context->buildOrderClause($orderBy, $orderType);

        $model = $this->context->getModel();
        if (method_exists($model, 'setVueMode')) {
            $model->setVueMode(true);
        }
        $objectMethod = $this->context->getObjectMethod();
        $extraColumns = $this->context->getExtraColumns();

        try {
            $model->filter_use_like = true;

            $countMethod = $objectMethod['count'] ?? 'count';
            $total = (int) $model->$countMethod($filters);

            // 取数列：控制器声明 finderCols 时按展示列裁剪（对齐 legacy builder/view.php:490-512 + 607-609），
            // 未声明时保持 '*'——其他所有 finder 页取数行为零变化。
            $fetchColumns = $this->buildFetchColumns(
                $this->context->getFinderCols(),
                $this->context->getColumns(),
                $this->context->getSchema()
            );

            $getlistMethod = $objectMethod['getlist'] ?? 'getlist';
            $list = $model->$getlistMethod($fetchColumns, $filters, ($page - 1) * $pageSize, $pageSize, $orderClause);
            if (!is_array($list)) {
                $list = [];
            }

            if (!empty($extraColumns) && is_array($extraColumns)) {
                foreach ($extraColumns as $extraCol) {
                    $funcSuffix = $extraCol['func_suffix'] ?? null;
                    if ($funcSuffix && method_exists($model, 'extra_' . $funcSuffix)) {
                        $extraMethod = 'extra_' . $funcSuffix;
                        $list = $model->$extraMethod($list);
                    }
                }
            }
        } finally {
            $model->filter_use_like = false;
        }

        // Row metadata: styles + selection + detail URL
        $list = $this->context->applyRowStyles($list);
        $list = $this->context->annotateRowActions($list, $detailUrl);

        // Backend formatting: object_modifier resolves table: lookups to text
        $list = $this->context->resolveObjectModifiers($list);

        // Ensure ALL table:-type columns are resolved (not just those in getColumns()/finderCols)
        // This handles columns like branch_id that may not be in finderCols but exist in schema
        $list = $this->context->applyObjectModifiersToAllTableColumns($list);

        // Backend formatting: type_modifier runs built-in formatters (money, region, bool, etc.)
        $list = $this->context->resolveTypeModifiers($list);

        // Ensure ALL time-type columns are formatted (not just those in getColumns()/finderCols)
        // This handles columns like check_time that may not be in finderCols but exist in schema
        $list = $this->context->applyTimeModifiersToAllTimeColumns($list);

        // Backend formatting: add legacy `_<idx>_<col>` aliases for addon_cols (mirrors builder/view.php:585-592)
        // Required for column_*($row) finder methods that read $row['_0_xxx']
        $list = $this->context->applyAddonColAliases($list);

        // Backend formatting: func columns (column_*) return HTML, placed as {col}_html
        $list = $this->context->applyFuncColumns($list);

        // 金额列币种展示（判据 = dbschema type='money'，多币种前瞻）。
        // 刻意放在 applyFuncColumns 之后：column_*/modifier_* 仍拿到原始数值，
        // 避免格式化后的展示串（'￥123.45'）破坏业务侧计算。
        $list = $this->context->applyMoneyModifiersToAllMoneyColumns($list);

        // Sensitive fields annotation (last)
        $list = $this->context->annotateSensitiveFields($list);

        // Favstar: get starred IDs for current user
        $favstarIds = [];
        $userId = $this->context->getCurrentUserId();
        if ($userId) {
            $objectName = $this->context->getObjectName();
            $finderAlias = $this->context->getFinderAlias();
            $confKey = 'favstar.' . $objectName . '_' . $finderAlias . '.' . $userId;
            $favRows = (array) app::get('desktop')->getConf($confKey);
            foreach ($favRows as $key => $val) {
                if (intval($val) === 1 && strpos($key, 'id-') === 0) {
                    $favstarIds[] = substr($key, 3);
                }
            }
        }

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'pageSize'   => $pageSize,
            'favstarIds' => $favstarIds,
        ];
    }

    /**
     * 构造 getlist 取数列（对齐 legacy builder/view.php:490-512 + 607-609）。
     *
     * finderCols 为空（控制器未显式声明）⇒ 返回 '*'，其他 finder 页零改动。
     * 声明后：
     *   1) 取 getColumns()（含用户保存的列配置）∩ dbschema columns 键集，
     *      func 列（column_*）/extra 虚拟列不在表里，被交集自然滤除；
     *   2) 命中列带 'sql' 键者按 legacy 用其表达式（view.php:505-506）；
     *   3) schema idColumn 不在列清单时兜底插首（view.php:607-609）。
     *
     * @param string $finderCols context 声明的展示列字串（空 = 未裁剪）
     * @param array  $columns    context->getColumns() 展开后的列键
     * @param array  $schema     model->get_schema()
     */
    protected function buildFetchColumns(string $finderCols, array $columns, array $schema): string
    {
        if (trim($finderCols) === '') {
            return '*';
        }

        $schemaColumns = (isset($schema['columns']) && is_array($schema['columns'])) ? $schema['columns'] : [];
        $idColumn = $this->extractIdColumn($schema);

        $sql = [];
        $seen = [];
        foreach ($columns as $col) {
            $col = trim((string) $col);
            if ($col === '' || isset($seen[$col]) || !array_key_exists($col, $schemaColumns)) {
                continue;
            }
            $seen[$col] = true;
            $colDef = $schemaColumns[$col];
            if (is_array($colDef) && isset($colDef['sql'])) {
                $sql[] = $colDef['sql'] . ' as ' . $col;
            } else {
                $sql[] = '`' . $col . '`';
            }
        }

        if ($idColumn !== '' && !isset($seen[$idColumn])) {
            array_unshift($sql, '`' . $idColumn . '`');
        }

        return $sql ? implode(',', $sql) : '*';
    }

    /**
     * dbschema idColumn 可能是字符串，也可能是 ['colname' => 'colname']（dbtable.php 结构）。
     */
    private function extractIdColumn(array $schema): string
    {
        $idColumn = $schema['idColumn'] ?? '';
        if (is_array($idColumn)) {
            $idColumn = (string) key($idColumn);
        }
        return trim((string) $idColumn);
    }
}
