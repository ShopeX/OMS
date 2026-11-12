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

class desktop_finder_vue_context
{
    /**
     * 动作列：在 Vue 侧统一收口为单个 _actions 列，不参与排序（见 buildColumnsMeta()）。
     */
    public const ACTION_KEYS = ['column_edit', 'column_control', 'column_op', 'column_opt', 'column_confirm'];

    /**
     * 按 legacy all_columns() 的键序稳定排序待展示列键（只重排、不增删列）。
     *
     * 单一权威（legacy）：
     *   - app/desktop/lib/finder/builder/prototype.php:218-241 all_columns()
     *     （func_columns + extra_cols + dbschema in_list 合并后按 order 升序；未声明 order 视为 100）
     *   - 同文件 getColumns() 第 3 分支（用户无保存配置且 finder 未声明 _finder_cols）：
     *       $columns = array_merge(func_columns, extra_cols, dbschema['default_in_list']);
     *       foreach (all_columns() as $key => $value) { if (in_array($key, $columns)) $return[] = $key; }
     *     —— 即 legacy 表头顺序 = all_columns() 的键序，「哪些列可见」由可见集合决定。
     *
     * 规则：
     *   1) 动作列（ACTION_KEYS）剔除 —— 由 _actions 列承载；
     *   2) 去重，保持入参首次出现的相对顺序（作为同 order / 未登记 order 时的稳定依据）；
     *   3) 按 $allColumnKeys 的下标升序排列；未登记在 $allColumnKeys 中的键排到最后（PHP_INT_MAX），
     *      相互间保持 (2) 的相对顺序 —— 对应 legacy「order 未声明视为 100，array_multisort 稳定」。
     *
     * @param array $keys         可见列键（原始顺序：先 dbschema 可见列，后 finder 虚拟列）
     * @param array $allColumnKeys all_columns()/getAllColumns() 的键序（legacy 顺序权威）
     * @param array $actionKeys   动作列键
     * @return array
     */
    public static function sortByLegacyOrder(array $keys, array $allColumnKeys, array $actionKeys = self::ACTION_KEYS): array
    {
        $rank = array_flip(array_values($allColumnKeys));

        $seq = [];
        $out = [];
        foreach ($keys as $key) {
            if (in_array($key, $actionKeys, true) || isset($seq[$key])) {
                continue;
            }
            $seq[$key] = count($seq);
            $out[] = $key;
        }

        usort($out, function ($a, $b) use ($rank, $seq) {
            $ra = isset($rank[$a]) ? $rank[$a] : PHP_INT_MAX;
            $rb = isset($rank[$b]) ? $rank[$b] : PHP_INT_MAX;
            if ($ra === $rb) {
                return $seq[$a] <=> $seq[$b];
            }
            return $ra <=> $rb;
        });

        return $out;
    }

    /** 前端 FilterItem 认识的控制 token；不在此列一律落 'normal'（文本框） */
    private const FE_CONTROL_TOKENS = [
        'normal', 'yes', 'no', 'time', 'date', 'datetime', 'number', 'numrange',
        'bool', 'boolean', 'select', 'textarea',
    ];

    /**
     * 快捷筛选「控件类型」归一（Vue 契约层）。
     *
     * 单一权威：legacy app/desktop/lib/finder/builder/filter/render.php:40-120
     * （与 filter/top.php 的同类循环逐行一致）。
     *
     * legacy 语义：dbschema 的 filtertype 只是「可筛选」标记；控件类型由**列 type** 决定，
     * 仅 3 处特判 + 1 处归一：
     *   1) strpos(type,'decimal')!==false && filtertype==='number' → number
     *   2) filtertype==='textarea'                                → textarea
     *   3) substr(filtertype,0,6)==='custom'                      → substr(filtertype,7) ?: 'custom'
     *   4) type==='last_modify' || type==='TIMESTAMP'             → time（严格大写，镜像 legacy）
     * 其余（含 filtertype='yes' / true）一律保持列 type。
     *
     * ⚠️ 契约编码：只下发前端 FilterItem 认识的控制 token（normal/bool/time/select/textarea/date/…）；
     *    varchar(200) / longtext / int / money / region 等列类型一律折成 'normal'（=文本框，
     *    与 legacy input_default 同义）。直接下发原始列类型会让不经 normalizeFilterDefinitions 的
     *    页面（如 crm/gift，见 index.vue 自持 filterDefinitions）无分支可匹配 → 快捷筛选整块不渲染。
     * ⚠️ 禁止按 filtertype 判控件：filtertype='yes' 的列既有 varchar（文本框）也有 bool（是/否下拉）。
     */
    public static function normalizeFilterControlType($type, $filtertype): string
    {
        if (is_array($type)) {
            return 'select';
        }
        $type = (string) $type;
        if (strpos($type, 'table:') === 0) {
            return $type;
        }
        $filtertype = is_string($filtertype) ? $filtertype : '';
        if (strpos($type, 'decimal') !== false && $filtertype === 'number') {
            $type = 'number';
        } elseif ($filtertype === 'textarea') {
            $type = 'textarea';
        } elseif (substr($filtertype, 0, 6) === 'custom') {
            $type = substr($filtertype, 7) ?: 'custom';
        }
        if ($type === 'last_modify' || $type === 'TIMESTAMP') {
            $type = 'time';
        }
        $lower = strtolower($type);
        if ($lower !== '' && strpos($lower, 'table:') !== 0
            && !in_array($lower, self::FE_CONTROL_TOKENS, true)) {
            $type = 'normal';
        }
        return $type;
    }

    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private string $objectName;
    private array $sensitiveFields;
    private array $baseFilter;
    private $customFilter;
    private array $searchOptions;
    private array $allowedOrderColumns;
    private string $finderCols;
    private bool $useBuildinRecycle;
    private bool $useBuildinSelectrow;
    private bool $useBuildinExport;
    private bool $useBuildinFilter;
    private bool $useBuildinNewDialog;
    private bool $useBuildinSetTag;
    private bool $useBuildinImport;
    private bool $useImportTemplate;
    private bool $useBuildinCustomcols;
    private bool $useBuildinRefresh;
    private bool $useBuildinSetcol;
    private bool $useViewTab;
    private string $title;
    private string $orderBy;
    private string $orderType;
    private array $views;
    private array $filterOptions;
    private ?object $modelInstance = null;
    private ?array $schemaCache = null;

    private string $finderAlias = 'default';
    private array $addonColumns = [];
    private array $serviceObjects = [];   // [k => $object] preserve insertion order, mirrors legacy $service_object
    private array $detailPages = [];
    private array $rowStyleHandlers = [];
    private array $toolbarActions = [];
    private array $objectMethod = [
        'count'   => 'count',
        'getlist' => 'getlist',
    ];
    private array $extraColumns = [];

    private ?array $columnsCache = null;
    private ?array $allColumnsCache = null;
    private ?array $funcColumnsCache = null;

    public function __construct(array $params)
    {
        $this->objectName = $params['objectName'] ?? '';
        if (empty($this->objectName)) {
            throw new InvalidArgumentException('objectName is required');
        }
        $this->sensitiveFields = $params['sensitiveFields'] ?? [];
        $this->useBuildinRecycle   = (bool) ($params['use_buildin_recycle'] ?? false);
        // 与 desktop_finder_builder_view::$use_buildin_selectrow 默认一致（行首多选列）
        $this->useBuildinSelectrow = (bool) ($params['use_buildin_selectrow'] ?? true);
        $this->useBuildinExport    = (bool) ($params['use_buildin_export'] ?? false);
        $this->useBuildinFilter    = (bool) ($params['use_buildin_filter'] ?? true);
        $this->useBuildinNewDialog = (bool) ($params['use_buildin_new_dialog'] ?? false);
        $this->useBuildinSetTag    = (bool) ($params['use_buildin_set_tag'] ?? false);
        $this->useBuildinImport    = (bool) ($params['use_buildin_import'] ?? false);
        $this->useImportTemplate   = (bool) ($params['use_import_template'] ?? false);
        $this->useBuildinCustomcols = (bool) ($params['use_buildin_customcols'] ?? false);
        $this->useBuildinRefresh   = (bool) ($params['use_buildin_refresh'] ?? true);
        $this->useBuildinSetcol    = (bool) ($params['use_buildin_setcol'] ?? true);
        $this->useViewTab         = (bool) ($params['use_view_tab'] ?? true);
        $this->baseFilter         = $params['base_filter'] ?? [];
        $this->customFilter        = $params['customFilter'] ?? null;
        $this->searchOptions       = $params['searchOptions'] ?? [];
        $this->allowedOrderColumns = $params['allowedOrderColumns'] ?? [];
        $this->finderCols         = $params['finderCols'] ?? '';
        $this->title              = $params['title'] ?? '';
        $this->orderBy            = $params['orderBy'] ?? '';
        $this->orderType          = strtolower($params['orderType'] ?? 'desc');
        $this->views              = $params['views'] ?? [];
        $this->filterOptions      = $params['filterOptions'] ?? [];
        $this->finderAlias        = $params['finderAlias'] ?? 'default';
        if (isset($params['objectMethod']) && is_array($params['objectMethod'])) {
            $this->objectMethod = array_merge($this->objectMethod, $params['objectMethod']);
        }
        $this->toolbarActions = $params['actions'] ?? [];
        $this->discoverServices();
    }

    public function getObjectName(): string { return $this->objectName; }
    public function getSensitiveFields(): array { return $this->sensitiveFields; }
    public function getBaseFilter(): array { return $this->baseFilter; }
    public function getSearchOptions(): array { return $this->searchOptions; }
    public function getAllowedOrderColumns(): array { return $this->allowedOrderColumns; }
    public function getFinderCols(): string { return $this->finderCols; }
    public function getTitle(): string { return $this->title; }
    public function getOrderBy(): string { return $this->orderBy; }
    public function getOrderType(): string { return $this->orderType; }
    public function getFinderAlias(): string { return $this->finderAlias; }
    public function getFilterOptions(): array { return $this->filterOptions; }
    public function useBuildinRecycle(): bool   { return $this->useBuildinRecycle; }
    public function useBuildinSelectrow(): bool { return $this->useBuildinSelectrow; }
    public function useBuildinExport(): bool     { return $this->useBuildinExport; }
    public function useBuildinFilter(): bool     { return $this->useBuildinFilter; }
    public function useBuildinNewDialog(): bool  { return $this->useBuildinNewDialog; }
    public function useBuildinSetTag(): bool     { return $this->useBuildinSetTag; }
    public function useBuildinImport(): bool     { return $this->useBuildinImport; }
    public function useImportTemplate(): bool    { return $this->useImportTemplate; }
    public function useBuildinCustomcols(): bool { return $this->useBuildinCustomcols; }
    public function useBuildinRefresh(): bool    { return $this->useBuildinRefresh; }
    public function useBuildinSetcol(): bool     { return $this->useBuildinSetcol; }
    public function useViewTab(): bool           { return $this->useViewTab; }
    public function getAddonColumns(): array { return $this->addonColumns; }
    public function getDetailPages(): array { return $this->detailPages; }
    public function getRowStyleHandlers(): array { return $this->rowStyleHandlers; }
    public function getObjectMethod(): array { return $this->objectMethod; }
    public function getExtraColumns(): array { return $this->extraColumns; }
    public function getToolbarActions(): array { return $this->toolbarActions; }
    public function getModel(): object
    {
        if ($this->modelInstance === null) {
            [$appId, $modelName] = $this->parseObjectName();
            $this->modelInstance = app::get($appId)->model($modelName);
        }
        return $this->modelInstance;
    }

    public function getSchema(): array
    {
        if ($this->schemaCache === null) {
            $this->schemaCache = $this->getModel()->get_schema();
        }
        return $this->schemaCache;
    }

    public function buildFilterConditions(array $rawFilters): array
    {
        $filters = [];
        foreach ($rawFilters as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $filters[$key] = $value;
        }

        // Handle view_id: look up the view and apply its filter
        if (isset($filters['view_id']) && !empty($filters['view_id'])) {
            $viewId = $filters['view_id'];
            unset($filters['view_id']);
            $viewFilter = $this->getViewFilterById($viewId);
            if (!empty($viewFilter)) {
                $filters = array_merge($viewFilter, $filters);
            }
        }

        $filters = array_merge($this->baseFilter, $filters);
        $filters = $this->applyCustomFilter($filters);

        return $this->normalizeTimeFilterKeys($filters);
    }

    /**
     * 时间筛选键归一：Vue 端用 {col} 或 {col}|{op} + 'Y-m-d[ H:i[:s]]' 表达时间筛选，
     * legacy 的 int 时间戳列（dbschema type=time / last_modify）必须还原成 legacy 筛选包结构：
     *   {col}='Y-m-d' + _{col}_search={op} + _DTIME_[H|M][{col}]
     *   between 走 {col}_from / {col}_to + _DTIME_[H|M][{col}_from|_to] + _{col}_search='between'
     * 否则 dbeav_filter_parser 会把 'Y-m-d H:i' 串直接与 int 列比较（MySQL 强转 0）⇒ 时间筛选静默失效
     * （bthan 退化为恒真、lthan 恒假、nequal 退化为裸列）。
     * 精度：H/M 取用户所选时分，秒固定 00 —— 与 legacy 一致（legacy 时间筛选精度即到分）。
     *
     * 范围：只归一 type=time / last_modify（int 时间戳列）；datetime / TIMESTAMP 等字符串时间列保持原串
     * 比较路径，避免把用户选的秒截断成 00 造成回归。
     */
    public function normalizeTimeFilterKeys(array $filters): array
    {
        $columns = (array) ($this->getSchema()['columns'] ?? []);

        return self::normalizeTimeFilters($filters, $columns);
    }

    public static function normalizeTimeFilters(array $filters, array $columnSchema): array
    {
        $timeColumns = self::timeColumnKeys($columnSchema);
        if (!$timeColumns) {
            return $filters;
        }

        $out = [];
        $pending = [];
        foreach ($filters as $key => $value) {
            $key = (string) $key;
            if (strpos($key, '_') === 0) {
                // legacy 原生键（_DTIME_ / _{col}_search）与 base_filter 内部键原样透传
                $out[$key] = $value;
                continue;
            }

            $column = $key;
            $operator = null;
            if (strpos($key, '|') !== false) {
                [$column, $operator] = explode('|', $key, 2);
            }
            if (empty($timeColumns[$column])) {
                $out[$key] = $value;
                continue;
            }
            if (isset($out['_' . $column . '_search']) || isset($filters['_' . $column . '_search'])) {
                // legacy 原生筛选包（{col} + _{col}_search + _DTIME_）原样透传：
                // 否则裸值会被误当成 Vue 的 nequal 形态，把调用方给的 bthan/sthan/between 覆写掉（实测会把 >= 退化成 =）
                $out[$key] = $value;
                continue;
            }

            // 表头弹层（FinderHeaderFilterPopover::toPatch）下发的是符号操作符 >= / <=，
            // 而 legacy 只认 bthan/sthan/nequal —— 先归一为 legacy 词表。
            // 认不出的操作符（has/head/between 单值等）原样透传回 legacy 路径，绝不静默丢条件。
            $operator = self::normalizeTimeOperatorToken($operator);
            if ($operator === null) {
                $out[$key] = $value;
                continue;
            }

            $parts = self::splitTimeFilterValue($value);
            if ($parts === null) {
                // 非 'Y-m-d[ H:i[:s]]' 形态：交回 legacy 原路径处理
                $out[$key] = $value;
                continue;
            }
            $pending[$column][$operator === '' ? 'plain' : $operator] = $parts;
        }

        foreach ($pending as $column => $operators) {
            $added = self::buildLegacyTimeFilterKeys($column, $operators);
            if (!empty($added['_DTIME_']) && !empty($out['_DTIME_'])) {
                foreach (['H', 'M'] as $unit) {
                    $added['_DTIME_'][$unit] = array_merge((array) ($out['_DTIME_'][$unit] ?? []), $added['_DTIME_'][$unit]);
                }
            }
            $out = array_merge($out, $added);
        }

        return $out;
    }

    public static function timeColumnKeys(array $columnSchema): array
    {
        $keys = [];
        foreach ($columnSchema as $column => $columnData) {
            if (!is_array($columnData)) {
                continue;
            }
            $type = $columnData['type'] ?? '';
            // time / last_modify ⇒ filter.php 的 int 时间戳分支（strtotime，L160-175）
            // TIMESTAMP（严格大写，legacy 只认这种写法）⇒ 同文件字符串分支（引号 + 'Y-m-d H:i:00'，L176-190）
            // 两者 legacy 筛选包结构一致（{col} + _{col}_search + _DTIME_），故共用同一归一；
            // 其它列型（datetime / 小写 timestamp / int / money…）legacy 无时间分支，保持原路径。
            if (!is_array($type) && ($type === 'time' || $type === 'last_modify' || $type === 'TIMESTAMP')) {
                $keys[$column] = true;
            }
        }

        return $keys;
    }

    /**
     * 操作符词表归一：前端（表头弹层 / filterAdapter）可能下发符号操作符，
     * legacy 的 _{col}_search 只认 bthan/sthan/than/lthan/nequal。
     *
     * @return string|null 归一后的 legacy 操作符；'' = 无操作符（裸列键）；null = 不认识，调用方须原样透传
     */
    public static function normalizeTimeOperatorToken(?string $operator): ?string
    {
        $op = trim((string) $operator);
        if ($op === '') {
            return '';
        }
        $aliases = [
            '>=' => 'bthan',
            '>' => 'bthan',
            '<=' => 'sthan',
            '<' => 'sthan',
            '=' => 'nequal',
            '==' => 'nequal',
        ];
        if (isset($aliases[$op])) {
            return $aliases[$op];
        }

        return in_array($op, ['bthan', 'sthan', 'than', 'lthan', 'nequal'], true) ? $op : null;
    }

    /**
     * @return array{date:string,hour:string,minute:string}|null
     */
    public static function splitTimeFilterValue($value): ?array
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || !preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/', $value, $matched)) {
            return null;
        }

        return [
            'date' => $matched[1],
            'hour' => ($matched[2] ?? '') !== '' ? $matched[2] : '00',
            'minute' => ($matched[3] ?? '') !== '' ? $matched[3] : '00',
        ];
    }

    public static function buildLegacyTimeFilterKeys(string $column, array $operators): array
    {
        $out = [];
        $dtime = [];
        if (isset($operators['bthan'], $operators['sthan'])) {
            // 裸列键必须一并给出：filter.php 的 foreach 守卫是 isset($cols[$k])，
            // 只给 {col}_from/_to 时该列根本不进时间分支（实测：条件全丢、返回全量）。
            $out[$column] = $operators['bthan']['date'];
            $out[$column . '_from'] = $operators['bthan']['date'];
            $out[$column . '_to'] = $operators['sthan']['date'];
            $dtime['H'][$column . '_from'] = $operators['bthan']['hour'];
            $dtime['M'][$column . '_from'] = $operators['bthan']['minute'];
            $dtime['H'][$column . '_to'] = $operators['sthan']['hour'];
            $dtime['M'][$column . '_to'] = $operators['sthan']['minute'];
            $out['_' . $column . '_search'] = 'between';
        } else {
            $operator = null;
            $parts = null;
            foreach (['bthan', 'sthan', 'than', 'lthan', 'nequal'] as $candidate) {
                if (isset($operators[$candidate])) {
                    $operator = $candidate;
                    $parts = $operators[$candidate];
                    break;
                }
            }
            if ($operator === null && isset($operators['plain'])) {
                $operator = 'nequal';
                $parts = $operators['plain'];
            }
            if ($operator === null) {
                return [];
            }
            $out[$column] = $parts['date'];
            $dtime['H'][$column] = $parts['hour'];
            $dtime['M'][$column] = $parts['minute'];
            $out['_' . $column . '_search'] = $operator;
        }
        $out['_DTIME_'] = $dtime;

        return $out;
    }

    private function getViewFilterById(string $viewId): array
    {
        foreach ($this->views as $view) {
            if (isset($view['id']) && (string) $view['id'] === $viewId) {
                return $view['filter'] ?? [];
            }
        }
        return [];
    }

    public function applyCustomFilter(array $filter): array
    {
        if ($this->customFilter !== null) {
            return ($this->customFilter)($filter);
        }
        return $filter;
    }

    public function buildOrderClause(?string $column, string $direction): ?string
    {
        if (!$column) {
            return null;
        }
        if (!$this->isOrderColumnAllowed($column)) {
            return null;
        }
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }
        return $column . ' ' . $direction;
    }

    /**
     * 排序列白名单校验：显式 allowedOrderColumns 优先，未配置时回退到 dbschema 真实列。
     * 防止非法列名直接拼进 ORDER BY（SQL 报错 500 / 注入面）。
     */
    private function isOrderColumnAllowed(string $column): bool
    {
        if (!empty($this->allowedOrderColumns)) {
            return in_array($column, $this->allowedOrderColumns, true);
        }
        $schema = $this->getSchema();
        $columns = isset($schema['columns']) && is_array($schema['columns']) ? $schema['columns'] : [];
        return array_key_exists($column, $columns);
    }

    public function normalizePage($page): int
    {
        $page = (int) $page;
        return $page > 0 ? $page : 1;
    }

    public function normalizePageSize($pageSize): int
    {
        $pageSize = (int) $pageSize;
        if ($pageSize < 1) {
            $pageSize = 20;
        }
        // 与 legacy finder 分页口径统一：desktop/lib/finder/builder/prototype.php::$plimit_in_sel = (500,200,100,50,20,10)，max_plimit 默认 500
        return min(500, $pageSize);
    }

    public function detectUiType(array $column): string
    {
        $type = $column['type'] ?? null;
        if (is_array($type)) {
            return 'enum';
        }
        if ($type === 'bool') {
            return 'bool';
        }
        return 'normal';
    }

    public function buildHighlightConfig(array $column, string $key): ?array
    {
        $uiType = $this->detectUiType($column);
        
        if ($uiType === 'bool') {
            return [
                'trueLabel' => '是',
                'falseLabel' => '否',
                'trueType' => 'success',
                'falseType' => 'danger',
            ];
        }
        
        if ($uiType === 'enum') {
            $type = $column['type'] ?? [];
            $options = [];
            foreach ($type as $value => $label) {
                $optionValue = (string) $value;
                $optionLabel = (string) $label;
                $options[$value] = [
                    'label' => $optionLabel,
                    'type'  => $this->inferEnumTagType($optionValue, $optionLabel),
                ];
            }
            return ['options' => $options];
        }
        
        return null;
    }

    /**
     * Vue 表头筛选控件类型：enum=下拉 options；datetime=日期时间；text=文本输入。
     */
    private function inferColumnFilterValueType(array $column): string
    {
        $type = $column['type'] ?? null;
        if (is_array($type)) {
            return 'enum';
        }
        if (is_string($type)) {
            $t = strtolower($type);
            $dateTypes = array('time', 'last_modify', 'cdate', 'timestamp', 'datetime', 'intl_datetime');
            if (in_array($t, $dateTypes, true)) {
                return 'datetime';
            }
        }

        return 'text';
    }

    /**
     * Read the current user's saved column config for this finder.
     * Returns null when no config has been saved (user is using defaults).
     */
    public function getSavedColConfig(): ?array
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return null;
        }
        $key = 'view.' . $this->objectName . '.' . $this->finderAlias . '.' . $userId;
        $saved = app::get('desktop')->getConf($key);
        if (!$saved || !is_string($saved)) {
            return null;
        }
        $parts = array_filter(array_map('trim', explode(',', $saved)));
        return $parts ?: null;
    }

    public function buildColumnsMeta(): array
    {
        $schema = $this->getSchema();
        $columns = $schema['columns'] ?? [];
        $rootInList = (isset($schema['in_list']) && is_array($schema['in_list'])) ? $schema['in_list'] : [];
        $rootDefaultInList = (isset($schema['default_in_list']) && is_array($schema['default_in_list']))
            ? $schema['default_in_list'] : [];
        $rootDefaultSet = array_flip($rootDefaultInList);

        // Load current user's saved column config (if any) to override defaultInList
        $savedConfig = $this->getSavedColConfig();
        $savedSet = $savedConfig !== null ? array_flip($savedConfig) : null;

        $orderedKeys = [];

        // 当 schema['columns'] 为空（无 dbschema 的 model）但 controller 声明了 FINDER_COLS 时，
        // 根据 finderCols 字段名列表构造虚拟 schema 列（label 取字段名，default_in_list=true）
        if (empty($columns) && $this->finderCols) {
            $orderedKeys = array_map('trim', explode(',', $this->finderCols));
            // 同步构建虚拟 columns 映射，使后续 $columns[$key] 读取不返回 null
            foreach ($orderedKeys as $field) {
                $columns[$field] = ['label' => $field, 'default_in_list' => true];
            }
        } elseif (!empty($rootInList)) {
            foreach ($rootInList as $key) {
                if (!isset($columns[$key])) {
                    continue;
                }
                $orderedKeys[] = $key;
            }
        } else {
            foreach ($columns as $key => $column) {
                if (empty($column['in_list'])) {
                    continue;
                }
                $orderedKeys[] = $key;
            }
        }

        // 列顺序与 legacy 对齐（框架统一修；可见列集合沿用上面的原判定，不增不减）。
        // legacy 列表页表头顺序取自 desktop_finder_builder_prototype::all_columns()，即
        // 「finder 虚拟列(column_xxx) + 扩展列 + dbschema in_list 列」各自按 order 升序
        //（未声明 order 视为 100；PHP8 排序稳定 ⇒ 同 order 保持声明顺序）；
        // 用户未保存列配置时，legacy 正是用 all_columns() 的键序把可见列挑出来
        // （prototype.php::getColumns() 第 3 分支）。此前 Vue 版本按「dbschema 声明顺序展开 +
        // finder 虚拟列一律追加到尾部」，导致状态列/创建者列错位。
        // 这里只改顺序不改集合：可见列与 finder 虚拟列并成一个集合，再按 all_columns() 键序稳定排序。
        // 排序规则见本类 sortByLegacyOrder()（同一实现有单测覆盖）。
        $funcColumns = $this->getFuncColumns();
        // Action columns (column_edit/control/op/opt/confirm) are surfaced via _actions, not as individual columns
        $actionColumnKeys = self::ACTION_KEYS;
        $orderedKeys = self::sortByLegacyOrder(
            array_merge($orderedKeys, array_keys($funcColumns)),
            array_keys($this->getAllColumns()),
            $actionColumnKeys
        );

        $result = [];
        foreach ($orderedKeys as $key) {
            // Skip action columns — they appear in columns metadata as _actions only (merged below)
            if (in_array($key, $actionColumnKeys, true)) {
                continue;
            }
            // finder 虚拟列（column_xxx）没有 dbschema 定义：按 order 就地落位，
            // 元数据构造复用 buildFuncColumnMeta()，与下方「未登记虚拟列」兜底分支同一实现
            if (!isset($columns[$key])) {
                if (isset($funcColumns[$key])) {
                    $result[] = $this->buildFuncColumnMeta($key, $funcColumns[$key], $savedSet);
                }
                continue;
            }
            $column = $columns[$key];
            $width = $column['width'] ?? null;
            // Convert 'auto' string to null so frontend uses default minWidth
            if ($width === 'auto' || $width === 'false') {
                $width = null;
            }
            $uiType = $this->detectUiType($column);
            $result[] = [
                'field'      => $key,
                'label'      => $column['label'] ?? $key,
                'defaultInList' => $savedSet !== null
                    ? isset($savedSet[$key])
                    : (!empty($column['default_in_list']) || isset($rootDefaultSet[$key])),
                'width'      => $width,
                'sensitive'  => in_array($key, $this->sensitiveFields, true),
                'uiType'     => $uiType,
                'options'    => $this->buildEnumOptions($column),
                'highlight'  => $this->buildHighlightConfig($column, $key),
                'searchtype' => isset($column['searchtype']) ? (string) $column['searchtype'] : '',
                'valueType'  => $this->inferColumnFilterValueType($column),
            ];
        }

        // finder 虚拟列已随 $orderedKeys 按 all_columns() 的 order 落位（见 self::sortByLegacyOrder()）；
        // 这里只把所有动作列收口成一个 Vue 操作列 _actions。
        $actionColumn = null;
        foreach ($funcColumns as $key => $column) {
            if (!in_array($key, $actionColumnKeys, true)) {
                continue;
            }
            $actionColumn = [
                'field'         => '_actions',
                'label'         => $column['label'] ?? '操作',
                'defaultInList' => true,
                'width'         => $column['width'] ?? 110,
                'fixed'         => 'right',
                'sensitive'     => false,
                'uiType'        => 'normal',
                'options'       => [],
                'highlight'     => null,
                'searchtype'    => '',
                'valueType'     => 'text',
            ];
            break;
        }

        if ($actionColumn !== null) {
            $result[] = $actionColumn;
        }

        return $result;
    }

    /**
     * 构造 finder 虚拟列（column_xxx）的列元数据。
     *
     * 元数据驱动状态列：finder 通过 var $column_xxx_type / $column_xxx_highlight 声明，
     * 框架把 column_xxx 翻译成原始字段名让前端按 uiType 标准渲染。
     *
     * 顺序无关：调用方（buildColumnsMeta）按 all_columns() 的 order 决定其落位，
     * 这里只负责单个列的元数据结构，避免多处重复实现。
     */
    private function buildFuncColumnMeta(string $key, array $column, ?array $savedSet): array
    {
        $ref = $column['ref'] ?? null;
        $typeProp = $key . '_type';
        $highlightProp = $key . '_highlight';
        $type = ($ref && isset($ref[0]) && isset($ref[0]->$typeProp))
            ? $ref[0]->$typeProp : null;
        $highlight = ($ref && isset($ref[0]) && isset($ref[0]->$highlightProp))
            ? $ref[0]->$highlightProp : null;
        if ($type) {
            $rawField = (strpos($key, 'column_') === 0) ? substr($key, 7) : $key;
            return [
                'field'         => $rawField,
                'label'         => $column['label'] ?? $rawField,
                'defaultInList' => $savedSet !== null ? isset($savedSet[$rawField]) : true,
                'width'         => $column['width'] ?? 110,
                'sensitive'     => false,
                'uiType'        => $type,
                'options'       => [],
                'highlight'     => $highlight,
                'searchtype'    => '',
                'valueType'     => 'text',
            ];
        }
        return [
            'field'         => $key,
            'label'         => $column['label'] ?? $key,
            'defaultInList' => $savedSet !== null ? isset($savedSet[$key]) : true,
            'width'         => $column['width'] ?? 110,
            'sensitive'     => false,
            'uiType'        => 'normal',
            'options'       => [],
            'highlight'     => null,
            'searchtype'    => '',
            'valueType'     => 'text',
        ];
    }

    private function buildEnumOptions(array $column): array
    {
        $type = $column['type'] ?? null;
        if (!is_array($type)) {
            return [];
        }

        $options = [];
        foreach ($type as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return $options;
    }

    private function inferEnumTagType(string $value, string $label): string
    {
        $valueLower = strtolower($value);
        $labelLower = strtolower($label);

        if ($valueLower === 'success' || strpos($labelLower, '成功') !== false) {
            return 'success';
        }

        if (
            $valueLower === 'fail' ||
            $valueLower === 'failed' ||
            $valueLower === 'error' ||
            strpos($labelLower, '失败') !== false
        ) {
            return 'danger';
        }

        // 中文状态模式自动推断
        // 已完成：已审、已确认、已完成、已通过、已处理、已发货、已支付 等
        if (preg_match('/^(已|\d+完成)/u', $label) && strpos($label, '未') === false) {
            return 'success';
        }

        // 待处理/未完成：未审、未确认、未处理、未通过、待审核、待处理、待发货 等
        if (strpos($label, '未') !== false || strpos($label, '待') !== false) {
            return 'warning';
        }

        return 'info';
    }

    public function buildFilterDefinitions(): array
    {
        if (!$this->useBuildinFilter) {
            return [];
        }
        $schema = $this->getSchema();
        $columns = (array) ($schema['columns'] ?? []);

        // Merge extend_filter_<modelClass> service columns (legacy panel/filter/top.php pattern).
        // Lets services like extend_filter_ome_mdl_operation_log inject virtual filter columns
        // (user, operation_type, st_time, et_time) without modifying dbschema.
        // model::_filter() consumes these virtual fields and rewrites to real columns.
        $modelClass = get_class($this->getModel());
        foreach (kernel::servicelist('extend_filter_' . $modelClass) as $extendFilter) {
            if (method_exists($extendFilter, 'setVueMode')) {
                $extendFilter->setVueMode(true);
            }
            if (!method_exists($extendFilter, 'get_extend_colums')) {
                continue;
            }
            $extendCols = $extendFilter->get_extend_colums($this->finderAlias);
            foreach ((array) $extendCols as $entry) {
                if (!empty($entry['columns']) && is_array($entry['columns'])) {
                    $columns = array_merge($columns, $entry['columns']);
                }
            }
        }

        $result = [];
        foreach ($columns as $key => $column) {
            $filtertype = $column['filtertype'] ?? null;
            if (!$filtertype) {
                continue;
            }
            $type = $column['type'] ?? '';
            $isEnumType = is_array($type);
            $enumOptions = $isEnumType ? $this->buildEnumOptions($column) : [];
            // 控件类型口径与 legacy 完全一致：由列 type 决定（filtertype 仅为「可筛选」标记）。
            // 权威实现：app/desktop/lib/finder/builder/filter/render.php:40-120
            $filterType = self::normalizeFilterControlType($type, $filtertype);
            $filter = [
                'field'   => $key,
                'label'   => $column['label'] ?? $key,
                'type'    => $filterType,
                'default' => !empty($column['filterdefault']),
                // legacy TOP 面板归属依据：dbschema / extend filter 的 panel_id（如 apifail_finder_top）。
                // 非空 ⇒ 该筛选项属于 legacy view/finder/finder_panel_filter.html 面板，
                // Vue 侧由 FinderTopFilter 组件按 panelId 渲染到列表上方（不硬编码字段名）。
                'panelId' => (string) ($column['panel_id'] ?? ''),
            ];
            if (!empty($enumOptions)) {
                $filter['options'] = $enumOptions;
            }
            $result[] = $filter;
        }
        return $result;
    }

    public function buildViewDefinitions(): array
    {
        if (!empty($this->views)) {
            return $this->views;
        }
        return [];
    }

    public function annotateSensitiveFields(array $rows): array
    {
        if (empty($this->sensitiveFields)) {
            return $rows;
        }
        $security = class_exists('ome_security_hash') ? kernel::single('ome_security_hash') : null;
        foreach ($rows as &$row) {
            foreach ($this->sensitiveFields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }
                $row[$field . '_encrypted'] = $security
                    ? $security->check_encrypt($row[$field])
                    : false;
            }
        }
        unset($row);
        return $rows;
    }

    // ===== Step 2: Column Aggregation =====

    public function getColumns(): array
    {
        if ($this->columnsCache !== null) {
            return $this->columnsCache;
        }
        $schema = $this->getSchema();
        $userId = $this->getCurrentUserId();
        $cols = null;
        if ($userId) {
            $cols = app::get('desktop')->getConf(
                'view.' . $this->objectName . '.' . $this->finderAlias . '.' . $userId
            );
        }
        if ($cols) {
            $this->columnsCache = explode(',', $cols);
            return $this->columnsCache;
        }
        if ($this->finderCols) {
            $this->columnsCache = explode(',', $this->finderCols);
            return $this->columnsCache;
        }
        $colKeys = [];
        $funcColumns = $this->getFuncColumns();
        if ($funcColumns) {
            foreach (array_keys($funcColumns) as $key) {
                $colKeys[] = $key;
            }
        }
        if (!empty($this->extraColumns)) {
            foreach (array_keys($this->extraColumns) as $key) {
                $colKeys[] = $key;
            }
        }
        $defaultInList = (array) ($schema['default_in_list'] ?? []);
        $columns = array_merge((array) $colKeys, $defaultInList);
        $allCols = $this->getAllColumns();
        $return = [];
        foreach ($allCols as $key => $value) {
            if (in_array($key, $columns)) {
                $return[] = $key;
            }
        }
        $this->columnsCache = $return;
        return $this->columnsCache;
    }

    public function getAllColumns(): array
    {
        if ($this->allColumnsCache !== null) {
            return $this->allColumnsCache;
        }
        $funcColumns = $this->getFuncColumns();
        $extraColumns = !empty($this->extraColumns) ? $this->extraColumns : [];
        $schema = $this->getSchema();
        $columns = [];
        foreach ((array) ($schema['in_list'] ?? []) as $key) {
            if (isset($schema['columns'][$key])) {
                $columns[$key] = &$schema['columns'][$key];
            }
        }
        $return = array_merge((array) $funcColumns, (array) $extraColumns, (array) $columns);
        foreach ($return as $k => $r) {
            if (!isset($r['order'])) {
                $return[$k]['order'] = 100;
            }
            $orders[] = $return[$k]['order'];
        }
        array_multisort($orders, SORT_ASC, $return);
        $this->allColumnsCache = $return;
        return $this->allColumnsCache;
    }

    public function getFuncColumns(): array
    {
        if ($this->funcColumnsCache !== null) {
            return $this->funcColumnsCache;
        }
        $defaultWidth = (int) app::get('desktop')->getConf('finder.thead.default.width') ?: 110;
        $return = [];
        $model = $this->getModel();
        foreach ($this->addonColumns as $item) {
            [$object, $method] = $item;
            $width = $object->{$method . '_width'} ?: $defaultWidth;
            $label = $object->{$method} ?: '';
            $order = $object->{$method . '_order'} ?: 100;
            $orderField = $object->{$method . '_order_field'} ?: '';
            $return[$method] = [
                'type'       => 'func',
                'width'      => $width,
                'label'      => $label,
                'order'      => $order,
                'ref'        => $item,
                'sql'        => '1',
                'order_field' => $orderField,
                'alias_name' => $method,
            ];
        }
        $this->funcColumnsCache = $return;
        return $this->funcColumnsCache;
    }

    // ===== Step 4: Column Formatting Pipeline =====

    public function buildColumnFormatters(): array
    {
        $model = $this->getModel();
        $allCols = $this->getAllColumns();
        $columns = $this->getColumns();
        $result = [];
        foreach ($columns as $col) {
            if (!isset($allCols[$col])) {
                continue;
            }
            $colData = $allCols[$col];
            $result[$col] = $this->detectFormatter($col, $colData, $model);
        }
        return $result;
    }

    private function detectFormatter(string $col, array $colData, object $model): array
    {
        if (($colData['type'] ?? '') === 'func') {
            return [
                'formatter' => 'func',
                'ref'       => $colData['ref'] ?? null,
                'label'     => $colData['label'] ?? $col,
                'width'     => $colData['width'] ?? null,
                'order'     => $colData['order'] ?? 100,
            ];
        }
        if (method_exists($model, 'modifier_' . $col)) {
            return [
                'formatter' => 'key_modifier',
                'method'    => 'modifier_' . $col,
                'label'     => $colData['label'] ?? $col,
                'width'     => $colData['width'] ?? null,
            ];
        }
        $type = $colData['type'] ?? '';
        if (is_string($type) && strpos($type, 'table:') === 0) {
            [$null, $objRef] = explode(':', $type, 2);
            return [
                'formatter' => 'object_modifier',
                'type'      => $type,
                'obj_ref'   => $objRef,
                'label'     => $colData['label'] ?? $col,
                'width'     => $colData['width'] ?? null,
            ];
        }
        if (is_array($colData['type'] ?? null)) {
            return [
                'formatter' => 'key_map',
                'type_map'  => $colData['type'],
                'label'     => $colData['label'] ?? $col,
                'width'     => $colData['width'] ?? null,
            ];
        }
        if (is_string($type) && $this->isBuiltInModifierType($type)) {
            return [
                'formatter' => 'type_modifier',
                'type'      => $type,
                'label'     => $colData['label'] ?? $col,
                'width'     => $colData['width'] ?? null,
            ];
        }
        return [
            'formatter' => 'text',
            'label'     => $colData['label'] ?? $col,
            'width'     => $colData['width'] ?? null,
        ];
    }

    private function isBuiltInModifierType(string $type): bool
    {
        static $types = [
            'gender', 'region', 'date', 'time', 'last_modify',
            'money', 'intbool', 'tinybool', 'bool', 'enum',
        ];
        return in_array($type, $types, true);
    }

    public function resolveObjectModifiers(array $rows): array
    {
        $formatters = $this->buildColumnFormatters();
        $objectModCols = [];
        foreach ($formatters as $col => $formatter) {
            if (($formatter['formatter'] ?? '') === 'object_modifier') {
                $objectModCols[$col] = $formatter;
            }
        }
        if (empty($objectModCols)) {
            return $rows;
        }
        $lookupMap = [];
        foreach ($objectModCols as $col => $formatter) {
            $objRef = $formatter['obj_ref'] ?? '';
            $values = array_filter(array_unique(array_column($rows, $col)));
            if (empty($values)) {
                continue;
            }
            $lookupMap[$col] = $this->lookupObjectValues($objRef, $values);
        }
        foreach ($rows as &$row) {
            foreach ($lookupMap as $col => $map) {
                $row[$col] = $map[$row[$col]] ?? $row[$col];
            }
        }
        unset($row);
        return $rows;
    }

    private function lookupObjectValues(string $objRef, array $values): array
    {
        if (strpos($objRef, '@') !== false) {
            [$objName, $appId] = explode('@', $objRef);
            $o = app::get($appId)->model($objName);
        } else {
            $o = $this->getModel()->app->model($objRef);
        }
        $idCol = $o->idColumn;
        $textCol = $o->textColumn ?: $idCol;
        $rows = $o->getList($idCol . ',' . $textCol, [$idCol => $values]);
        $map = [];
        foreach ($rows as $r) {
            $map[$r[$idCol]] = $r[$textCol];
        }
        return $map;
    }

    public function resolveTypeModifiers(array $rows): array
    {
        $formatters = $this->buildColumnFormatters();
        $typeModCols = [];
        foreach ($formatters as $col => $formatter) {
            if (($formatter['formatter'] ?? '') === 'type_modifier') {
                $typeModCols[$col] = $formatter['type'] ?? '';
            }
        }
        if (empty($typeModCols)) {
            return $rows;
        }
        $modifierObject = new modifiers;
        foreach ($typeModCols as $col => $type) {
            // Skip bool type - let Vue frontend handle bool-to-label conversion via highlight config
            if ($type === 'bool') {
                continue;
            }
            $rawValues = [];
            foreach ($rows as $row) {
                $v = $row[$col];
                if (is_float($v)) {
                    $rawValues[md5((string) $v)] = $v;
                } else {
                    $rawValues[$v] = $v;
                }
            }
            if (!empty($rawValues)) {
                $modifierObject->$type($rawValues);
                foreach ($rows as &$row) {
                    $v = $row[$col];
                    $key = is_float($v) ? md5((string) $v) : $v;
                    $row[$col] = $rawValues[$key] ?? $v;
                }
                unset($row);
            }
        }
        return $rows;
    }

    /**
     * Apply time modifier to ALL columns with type='time' in schema,
     * regardless of whether they are in getColumns()/finderCols.
     * This ensures fields like check_time are properly formatted.
     */
    public function applyTimeModifiersToAllTimeColumns(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $schema = $this->getSchema();
        $allCols = $this->getAllColumns();

        // Find all columns with type='time' or 'last_modify' in schema
        $timeCols = [];
        foreach ($allCols as $col => $colData) {
            $type = $colData['type'] ?? '';
            if ($type === 'time' || $type === 'last_modify') {
                $timeCols[] = $col;
            }
        }

        if (empty($timeCols)) {
            return $rows;
        }

        $modifierObject = new modifiers;
        foreach ($timeCols as $col) {
            // Collect all unique values for this column
            $rawValues = [];
            foreach ($rows as $row) {
                if (!isset($row[$col])) {
                    continue;
                }
                $v = $row[$col];
                if ($v !== null && $v !== '') {
                    if (is_float($v)) {
                        $rawValues[md5((string) $v)] = $v;
                    } else {
                        $rawValues[$v] = $v;
                    }
                }
            }

            if (!empty($rawValues)) {
                // Apply time modifier (converts UNIX timestamp to Y-m-d H:i:s)
                $modifierObject->time($rawValues);

                // Put formatted values back
                foreach ($rows as &$row) {
                    if (!isset($row[$col])) {
                        continue;
                    }
                    $v = $row[$col];
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $key = is_float($v) ? md5((string) $v) : $v;
                    $row[$col] = $rawValues[$key] ?? $v;
                }
                unset($row);
            }
        }

        return $rows;
    }

    /**
     * Apply money modifier (currency display) to ALL columns with type='money' in schema,
     * regardless of whether they are in getColumns()/finderCols.
     *
     * 判据 = dbschema `type: 'money'`（与 legacy builder/view.php 的 type_modifier 同规）；
     * 多币种前瞻：金额列一律带币种符号，避免 Vue 通道出现裸数字。
     * 幂等保护：仅格式化 int/float/数字字符串；已带币种符号的串（如 '￥123.45'）原样保留
     * —— modifiers::money 内部走 floatval()，二次格式化会把金额抹成 ￥0.00。
     */
    public function applyMoneyModifiersToAllMoneyColumns(array $rows): array
    {
        return $this->applyMoneyFormatter($rows, $this->getAllColumns());
    }

    /**
     * 按给定列 schema 格式化行内金额列（列定义 type==='money' 才处理）。
     *
     * detail 通道复用本方法：其 payload 的 header 就是模型 _columns()，天然带 dbschema type。
     * 同时支持「行列表」（列表页）与「单行 assoc」（detail body）两种入参。
     */
    public function applyMoneyFormatter(array $rows, array $columnSchema): array
    {
        if (empty($rows) || empty($columnSchema)) {
            return $rows;
        }

        $model = $this->getModel();
        $moneyCols = [];
        foreach (self::moneyColumnKeys($columnSchema) as $col) {
            // 与 applyTimeModifiersToAllTimeColumns 同规：model 自带 modifier_<col> 的列交给业务渲染，
            // 否则会把业务已格式化的展示串再格式化一次。
            if ($model && method_exists($model, 'modifier_' . $col)) {
                continue;
            }
            $moneyCols[] = $col;
        }

        if (empty($moneyCols)) {
            return $rows;
        }

        $single = !is_array(reset($rows));
        $work = $single ? [$rows] : $rows;

        $modifierObject = new modifiers;
        foreach ($moneyCols as $col) {
            $rawValues = [];
            foreach ($work as $row) {
                if (!is_array($row) || !isset($row[$col])) {
                    continue;
                }
                $v = $row[$col];
                if (self::isFormattableMoney($v)) {
                    $rawValues[is_float($v) ? md5((string) $v) : $v] = $v;
                }
            }

            if (empty($rawValues)) {
                continue;
            }

            // Apply money modifier (adds currency symbol per current currency config)
            $modifierObject->money($rawValues);

            foreach ($work as &$row) {
                if (!is_array($row) || !isset($row[$col])) {
                    continue;
                }
                $v = $row[$col];
                if (!self::isFormattableMoney($v)) {
                    continue;
                }
                $key = is_float($v) ? md5((string) $v) : $v;
                $row[$col] = $rawValues[$key] ?? $v;
            }
            unset($row);
        }

        return $single ? $work[0] : $work;
    }

    /**
     * 从列 schema（dbschema / 模型 _columns() 结构）中挑出 type==='money' 的列键。
     *
     * 判据单一来源 = 列定义里的 dbschema `type`（与 legacy type_modifier 同规），
     * 不依赖列名猜测 ⇒ 新增金额字段无需改框架。
     *
     * @param  array $columnSchema 形如 ['total_amount' => ['type' => 'money', ...], ...]
     * @return string[]            命中列键（保持 schema 声明顺序）
     */
    public static function moneyColumnKeys(array $columnSchema): array
    {
        $keys = [];
        foreach ($columnSchema as $col => $colData) {
            if (is_array($colData) && ($colData['type'] ?? '') === 'money') {
                $keys[] = $col;
            }
        }

        return $keys;
    }

    /**
     * 仅数值型值可被 money modifier 安全格式化（幂等保护，见 applyMoneyFormatter）。
     *
     * 判据：int/float/数字字符串 ⇒ 可格式化；其余（''、'--'、'￥123.45'、null、数组…）原样保留。
     * 必要性：modifiers::money() 内部走 floatval()，把已达展示态的串（'￥123.45'）再格式化会得到 ￥0.00
     * ⇒ 列表通道（resolveTypeModifiers 已格式化）二次进入本流程时必须幂等。
     */
    public static function isFormattableMoney($value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        $trimmed = trim($value);

        return $trimmed !== '' && is_numeric($trimmed);
    }

    /**
     * Apply object_modifier (table: lookups) to ALL columns with table: type in schema,
     * regardless of whether they are in getColumns()/finderCols.
     * This ensures fields like branch_id are properly resolved to names.
     */
    public function applyObjectModifiersToAllTableColumns(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $allCols = $this->getAllColumns();

        // Find all columns with table: type in schema (skip those with custom modifier_)
        $model = $this->getModel();
        $tableCols = [];
        foreach ($allCols as $col => $colData) {
            $type = $colData['type'] ?? '';
            if (is_string($type) && strpos($type, 'table:') === 0) {
                // Skip if model has a custom modifier for this column (it handles the lookup itself)
                if (method_exists($model, 'modifier_' . $col)) {
                    continue;
                }
                [, $objRef] = explode(':', $type, 2);
                $tableCols[$col] = $objRef;
            }
        }

        if (empty($tableCols)) {
            return $rows;
        }

        // Build lookup maps for each table: column
        $lookupMaps = [];
        foreach ($tableCols as $col => $objRef) {
            $values = array_filter(array_unique(array_column($rows, $col)));
            if (!empty($values)) {
                $lookupMaps[$col] = $this->lookupObjectValues($objRef, $values);
            }
        }

        if (empty($lookupMaps)) {
            return $rows;
        }

        // Apply lookups to rows
        foreach ($rows as &$row) {
            foreach ($lookupMaps as $col => $map) {
                if (isset($row[$col])) {
                    $row[$col] = $map[$row[$col]] ?? $row[$col];
                }
            }
        }
        unset($row);

        return $rows;
    }

    public function resolveKeyMapModifiers(array $rows): array
    {
        $formatters = $this->buildColumnFormatters();
        $keyMapCols = [];
        foreach ($formatters as $col => $formatter) {
            if (($formatter['formatter'] ?? '') === 'key_map') {
                $keyMapCols[$col] = $formatter['type_map'] ?? [];
            }
        }
        if (empty($keyMapCols)) {
            return $rows;
        }
        foreach ($rows as &$row) {
            foreach ($keyMapCols as $col => $map) {
                if (array_key_exists($col, $row)) {
                    $originalValue = $row[$col];
                    if ($originalValue !== null && array_key_exists($originalValue, $map)) {
                        $row[$col] = $map[$originalValue];
                    }
                }
            }
        }
        unset($row);
        return $rows;
    }

    public function applyAddonColAliases(array $rows): array
    {
        if (empty($rows) || empty($this->serviceObjects)) {
            return $rows;
        }
        foreach ($this->serviceObjects as $k => $object) {
            if (empty($object->addon_cols)) {
                continue;
            }
            $prefix = '_' . $k . '_';
            $cols = array_map('trim', explode(',', (string) $object->addon_cols));
            foreach ($rows as &$row) {
                foreach ($cols as $col) {
                    if ($col === '') continue;
                    if (array_key_exists($col, $row) && !array_key_exists($prefix . $col, $row)) {
                        $row[$prefix . $col] = $row[$col];
                    }
                }
            }
            unset($row);
        }
        return $rows;
    }

    public function applyFuncColumns(array $rows): array
    {
        $funcColumns = $this->getFuncColumns();
        $allCols = $this->getAllColumns();
        if (empty($rows)) {
            return $rows;
        }

        $model = $this->getModel();
        $schema = $this->getSchema();
        $idColRaw = $schema['idColumn'] ?? 'id';
        // dbtable.php stores idColumn as ['colname' => 'colname'], extract the string key
        $idCol = is_array($idColRaw) ? (string) key($idColRaw) : (string) $idColRaw;

        foreach ($rows as $idx => &$row) {
            $row['idColumn'] = $idCol;
            $row['app_id'] = $row['app_id'] ?? $this->getModel()->app->app_id;
            $row['tag_type'] = $row['tag_type'] ?? '';

            // Save primary key BEFORE modifiers may overwrite it (e.g. modifier_user_id replaces user_id with array)
            $row['_pkvalue_raw'] = $row[$idCol];

            // key_modifier: model->modifier_{col}($value, $list, $row) → directly replaces value
            foreach ($allCols as $col => $colData) {
                if (!isset($row[$col])) {
                    continue;
                }
                $modifierMethod = 'modifier_' . $col;
                if (method_exists($model, $modifierMethod)) {
                    $model->pkvalue = $row['_pkvalue_raw'];
                    $row[$col] = $model->$modifierMethod($row[$col], $rows, $row);
                }
            }

            // func columns (column_*): stored with full method name to match columns API field name
            foreach ($funcColumns as $method => $colDef) {
                $ref = $colDef['ref'] ?? null;
                if (!$ref || !isset($ref[0]) || !isset($ref[1])) {
                    continue;
                }
                // 元数据驱动状态列：finder 声明了 $column_xxx_type → 跳过函数调用，
                // row 里保留原始字段值，前端按裸字段名 uiType 渲染（与 buildColumnsMeta 对应）
                $typeProp = $method . '_type';
                if (isset($ref[0]->$typeProp) && $ref[0]->$typeProp) {
                    continue;
                }
                $value = $ref[0]->{$ref[1]}($row, $rows);
                // Map column_edit / column_control / column_op / column_opt / column_confirm to _actions for Vue FinderTable compatibility
                if (in_array($method, ['column_edit', 'column_control', 'column_op', 'column_opt', 'column_confirm'], true)) {
                    // vue_mode=true finder 直接返回 array，原样使用；空 string 则跳过
                    if (is_string($value) && strlen(trim($value)) === 0) {
                        continue;
                    }
                    $row['_actions'] = $value;
                } else {
                    $row[$method] = $value;
                }
            }
        }
        unset($row);
        return $rows;
    }

    // ===== Step 5: Row Selection & Actions =====

    public function applyRowStyles(array $rows): array
    {
        if (empty($this->rowStyleHandlers)) {
            return $rows;
        }
        foreach ($rows as $idx => &$row) {
            $styles = [];
            foreach ($this->rowStyleHandlers as $handler) {
                // $handler is [$object, 'method_name']
                $style = $handler[0]->{$handler[1]}($row, $rows);
                if ($style) {
                    $styles[] = $style;
                }
            }
            $row['_style'] = $styles;
        }
        unset($row);
        return $rows;
    }

    public function annotateRowActions(array $rows, ?string $detailUrl = null): array
    {
        $schema = $this->getSchema();
        $idCol = $schema['idColumn'] ?? 'id';
        foreach ($rows as $idx => &$row) {
            $row['_selectable'] = true;
            if ($detailUrl && isset($row[$idCol])) {
                $row['_detailUrl'] = rtrim($detailUrl, '/') . '/' . $row[$idCol];
            }
        }
        unset($row);
        return $rows;
    }

    // ===== Service Discovery =====

    private function discoverServices(): void
    {
        $serviceList = [];
        $srvKey1 = 'desktop_finder.' . $this->objectName;
        $srvKey2 = $srvKey1 . '.' . $this->finderAlias;
        error_log('[DEBUG] discoverServices START: objectName=' . $this->objectName . ', finderAlias=' . $this->finderAlias);
        foreach (kernel::servicelist($srvKey1) as $name => $object) {
            $serviceList[$name] = $object;
            error_log('[DEBUG] servicelist ' . $srvKey1 . ': ' . $name . ' => ' . get_class($object));
        }
        foreach (kernel::servicelist($srvKey2) as $name => $object) {
            $serviceList[$name] = $object;
            error_log('[DEBUG] servicelist ' . $srvKey2 . ': ' . $name . ' => ' . get_class($object));
        }
        error_log('[DEBUG] discoverServices: serviceList count=' . count($serviceList));

        // If no services registered, try to load the finder object directly
        // This handles finders like ome_finder_orders that aren't registered as services
        if (empty($serviceList)) {
            $finderClass = $this->objectName;
            $finderClass = str_replace('_mdl_', '_finder_', $finderClass);
            try {
                if (class_exists($finderClass)) {
                    $serviceList['default'] = new $finderClass();
                    error_log('[DEBUG] discoverServices: loaded finder directly: ' . $finderClass);
                }
            } catch (\Throwable $e) {
                // Finder class does not exist — proceed without it (columns from dbschema)
                error_log('[DEBUG] discoverServices: finder class not found: ' . $finderClass);
            }
        }

        foreach ($serviceList as $name => $object) {
            $this->serviceObjects[] = $object;
            if (method_exists($object, 'setVueMode')) {
                $object->setVueMode(true);
                error_log('[DEBUG] setVueMode(true) called on: ' . get_class($object) . ', vue_mode=' . ($object->vue_mode ?? 'null'));
            }
            $tmpobj = $object;
            foreach (get_class_methods($tmpobj) as $method) {
                $prefix = substr($method, 0, 7);
                switch ($prefix) {
                    case 'column_':
                        $this->addonColumns[] = [$object, $method];
                        break;
                    case 'detail_':
                        $this->detailPages[$method] = ['object' => $object, 'method' => $method];
                        break;
                }
            }
            // Discover detail_* as public properties (not just methods)
            // Only register when a same-named method is also implemented, matching legacy prototype.php behavior.
            $classProps = get_class_vars(get_class($object));
            foreach ($classProps as $propName => $defaultValue) {
                if (strpos($propName, 'detail_') === 0
                    && !isset($this->detailPages[$propName])
                    && method_exists($object, $propName)) {
                    $this->detailPages[$propName] = ['object' => $object, 'method' => $propName];
                }
            }
            if (method_exists($tmpobj, 'row_style')) {
                $this->rowStyleHandlers[] = [$object, 'row_style'];
            }
            unset($tmpobj);
        }
        $colModifiers = kernel::servicelist(
            'desktop_finder_column_modifier.' . $this->objectName . '.' . $this->finderAlias
        );
        if ($colModifiers) {
            foreach ($colModifiers as $modifier) {
                if (method_exists($modifier, 'columns_modifier')) {
                    $modifier->columns_modifier($this->addonColumns);
                }
            }
        }
        $detailModifiers = kernel::servicelist(
            'desktop_finder_detail_modifier.' . $this->objectName . '.' . $this->finderAlias
        );
        if ($detailModifiers) {
            foreach ($detailModifiers as $modifier) {
                if (method_exists($modifier, 'detail_columns_modifier')) {
                    $modifier->detail_columns_modifier($this->detailPages);
                }
            }
        }
        $model = $this->getModel();
        if (method_exists($model, 'extra_cols')) {
            $this->extraColumns = (array) $model->extra_cols();
        }
    }

    private function parseObjectName(): array
    {
        $parts = explode('_mdl_', $this->objectName, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid object name: ' . $this->objectName);
        }
        return [$parts[0], $parts[1]];
    }

    public function getCurrentUserId()
    {
        // Prefer JWT-based auth (API mode), fallback to session
        $userId = desktop_auth_user::currentUserId();
        if ($userId > 0) {
            return $userId;
        }
        return $_SESSION['user']['user_id'] ?? null;
    }

}
