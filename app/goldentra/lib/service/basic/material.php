<?php

/**
 * 基础物料主档字段 Service（列表展示 / 筛选 / 导出）
 *
 * 字段定义、列键、筛选项、数据读取均在本类实现；
 * material 仅通过 servicelist(material.service.basic.master) 动态获取，不写死任何主档字段。
 */
class goldentra_service_basic_material
{
    const COLUMN_PREFIX = 'column_gt_';

    /** 高级筛选请求参数前缀，与列表列键区分，限定 service 扩展 filter 作用范围 */
    const FILTER_PREFIX = 'gt_filter_';

    const SALES_MODE_LABELS = array(
        '0' => '无',
        '1' => '经销',
        '2' => '代销',
    );

    const PRICE_METHOD_LABELS = array(
        '1' => '计价（按克）',
        '2' => '定价（按件）',
    );

    /**
     * 主档字段定义（唯一配置源）
     *
     * source: prop=物料属性表, goldentra=SAP主档表
     */
    protected function getFieldDefinitions()
    {
        return array(
            'mold_name' => array(
                'label' => '模号名称',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(255)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'mold_code' => array(
                'label' => '模号编码',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(30)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'style_name' => array(
                'label' => '款式名称',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(255)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'style_code' => array(
                'label' => '款式编码',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(30)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'texture_code' => array(
                'label' => '材质编码',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(30)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'texture_name' => array(
                'label' => '材质名称',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(255)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'cate_code' => array(
                'label' => '大类编码',
                'source' => 'goldentra',
                'filter' => array('type' => 'varchar(30)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'cate_name' => array(
                'label' => '大类名称',
                'source' => 'goldentra',
                'filter' => array('type' => 'varchar(30)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'class_code' => array(
                'label' => '品类编码',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(30)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'class_name' => array(
                'label' => '品类名称',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(255)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'pricedimension_code' => array(
                'label' => '金价定价维度编码',
                'source' => 'prop',
                'filter' => array('type' => 'varchar(30)', 'filtertype' => 'normal', 'filterdefault' => true),
            ),
            'sales_mode' => array(
                'label' => '商品类型',
                'source' => 'prop',
                'options' => self::SALES_MODE_LABELS,
                'filter' => array(
                    'type' => self::SALES_MODE_LABELS,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
            ),
            'price_method' => array(
                'label' => '计价方式',
                'source' => 'prop',
                'options' => self::PRICE_METHOD_LABELS,
                'filter' => array(
                    'type' => self::PRICE_METHOD_LABELS,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
            ),
        );
    }

    protected function getFieldKeys()
    {
        return array_keys($this->getFieldDefinitions());
    }

    protected function getPropFieldKeys()
    {
        $keys = array();
        foreach ($this->getFieldDefinitions() as $field => $def) {
            if ($def['source'] === 'prop') {
                $keys[] = $field;
            }
        }

        return $keys;
    }

    protected function getGoldentraFieldKeys()
    {
        $keys = array();
        foreach ($this->getFieldDefinitions() as $field => $def) {
            if ($def['source'] === 'goldentra') {
                $keys[] = $field;
            }
        }

        return $keys;
    }

    public function getColumnKey($field)
    {
        return self::COLUMN_PREFIX . $field;
    }

    /**
     * 高级筛选请求参数键（带前缀，与 material 原生筛选项隔离）
     */
    public function getFilterRequestKey($field)
    {
        return self::FILTER_PREFIX . $field;
    }

    /**
     * 可参与高级筛选的字段键（不含前缀）
     */
    public function getFilterFieldKeys()
    {
        $keys = array();
        foreach ($this->getFieldDefinitions() as $field => $def) {
            if (!empty($def['filter'])) {
                $keys[] = $field;
            }
        }

        return $keys;
    }

    /**
     * 高级筛选请求参数键列表（含前缀）
     */
    public function getExtendFilterRequestKeys()
    {
        $keys = array();
        foreach ($this->getFilterFieldKeys() as $field) {
            $keys[] = $this->getFilterRequestKey($field);
        }

        return $keys;
    }

    /**
     * 列表顶部快捷搜索下拉项（key 为 gt_filter_*，与高级筛选同一套参数）
     * 无 service / 未启用时返回空
     */
    public function getSearchOptions()
    {
        if (!$this->isEnabled()) {
            return array();
        }

        $options = array();
        foreach ($this->getFieldDefinitions() as $field => $def) {
            if (empty($def['filter'])) {
                continue;
            }
            // 枚举下拉不适合顶部文本快捷搜
            if (!empty($def['options']) || (isset($def['filter']['type']) && is_array($def['filter']['type']))) {
                continue;
            }
            $options[$this->getFilterRequestKey($field)] = $def['label'];
        }

        return $options;
    }

    /**
     * 当前 filter 是否包含 service 扩展筛选项
     */
    public function hasExtendFilter($filter)
    {
        if (!is_array($filter)) {
            return false;
        }

        foreach ($this->getExtendFilterRequestKeys() as $requestKey) {
            if (isset($filter[$requestKey]) && $filter[$requestKey] !== '' && $filter[$requestKey] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * 从请求 filter 中提取 service 扩展筛选项（并移除对应请求键）
     *
     * @return array field => value
     */
    public function pickExtendFilter(&$filter)
    {
        if (!is_array($filter)) {
            return array();
        }

        $active = array();
        foreach ($this->getFilterFieldKeys() as $field) {
            $requestKey = $this->getFilterRequestKey($field);
            if (!isset($filter[$requestKey]) || $filter[$requestKey] === '' || $filter[$requestKey] === null) {
                continue;
            }
            $active[$field] = trim((string) $filter[$requestKey]);
            unset($filter[$requestKey]);
        }

        return $active;
    }

    public function isEnabled()
    {
        return app::get('goldentra')->is_installed();
    }

    /**
     * Finder 默认展示列键（material 动态合并，不写死字段名）
     */
    public function getFinderColKeys()
    {
        if (!$this->isEnabled()) {
            return array();
        }

        $keys = array();
        foreach ($this->getFieldKeys() as $field) {
            $keys[] = $this->getColumnKey($field);
        }

        return $keys;
    }

    /**
     * Finder / 导出列定义
     */
    public function getExtraCols()
    {
        if (!$this->isEnabled()) {
            return array();
        }

        $cols = array();
        foreach ($this->getFieldDefinitions() as $field => $def) {
            $cols[$this->getColumnKey($field)] = array(
                'label' => $def['label'],
                'width' => '120',
                'func_suffix' => 'gt_master',
            );
        }

        return $cols;
    }

    /**
     * 高级筛选列定义
     */
    public function getExtendFilterColumns()
    {
        if (!$this->isEnabled()) {
            return array();
        }

        $columns = array();
        foreach ($this->getFieldDefinitions() as $field => $def) {
            if (empty($def['filter'])) {
                continue;
            }
            $requestKey = $this->getFilterRequestKey($field);
            $columns[$requestKey] = array_merge(
                array('label' => $def['label']),
                $def['filter']
            );
        }

        return $columns;
    }

    /**
     * 列表 / 导出行填充主档字段
     */
    public function fillListRows(array $rows)
    {
        if (!$this->isEnabled() || empty($rows)) {
            return $rows;
        }

        $bmIds = array();
        $bnFallback = array();
        foreach ($rows as $row) {
            if (!empty($row['bm_id'])) {
                $bmIds[] = intval($row['bm_id']);
            } elseif (!empty($row['product_id'])) {
                $bmIds[] = intval($row['product_id']);
            } elseif (!empty($row['bn'])) {
                $bn = trim((string) $row['bn']);
                if ($bn !== '') {
                    $bnFallback[] = $bn;
                }
            } elseif (!empty($row['material_bn'])) {
                $bn = trim((string) $row['material_bn']);
                if ($bn !== '') {
                    $bnFallback[] = $bn;
                }
            }
        }
        $masterMap = $this->getMasterByBmIds($bmIds);
        // 仅对无 bm_id 的行按货号兜底，避免整页再查一遍 goldentra（bn 一对多易撑爆内存）
        $masterByBn = $bnFallback ? $this->getMasterByBns($bnFallback) : array();

        foreach ($rows as $k => $row) {
            $bmId = !empty($row['bm_id']) ? intval($row['bm_id']) : intval(isset($row['product_id']) ? $row['product_id'] : 0);
            $master = array();
            if ($bmId > 0 && !empty($masterMap[$bmId])) {
                $master = $masterMap[$bmId];
            } else {
                $bn = '';
                if (!empty($row['bn'])) {
                    $bn = trim((string) $row['bn']);
                } elseif (!empty($row['material_bn'])) {
                    $bn = trim((string) $row['material_bn']);
                }
                if ($bn !== '' && !empty($masterByBn[$bn])) {
                    $master = $masterByBn[$bn];
                }
            }
            foreach ($this->getFieldKeys() as $field) {
                $colKey = $this->getColumnKey($field);
                $rows[$k][$colKey] = isset($master[$field]) ? $master[$field] : '';
            }
        }

        return $rows;
    }

    /**
     * 批量读取主档展示字段
     *
     * @param array $bmIds
     * @return array bm_id => field => value
     */
    public function getMasterByBmIds(array $bmIds)
    {
        $bmIds = array_values(array_filter(array_unique(array_map('intval', $bmIds))));
        if (empty($bmIds) || !$this->isEnabled()) {
            return array();
        }

        $materialRows = app::get('material')->model('basic_material')->getList(
            'bm_id,material_bn',
            array('bm_id|in' => $bmIds)
        );
        if (empty($materialRows)) {
            return array();
        }

        $bnByBmId = array();
        foreach ($materialRows as $row) {
            $bnByBmId[intval($row['bm_id'])] = trim((string) $row['material_bn']);
        }

        $propsByBmId = $this->loadPropsByBmIds($bmIds);
        $goldentraByBn = $this->loadGoldentraFieldsByBns(
            array_values(array_filter(array_unique($bnByBmId))),
            $this->getGoldentraFieldKeys()
        );

        $result = array();
        foreach ($bmIds as $bmId) {
            $bn = !empty($bnByBmId[$bmId]) ? $bnByBmId[$bmId] : '';
            $merged = !empty($propsByBmId[$bmId]) ? $propsByBmId[$bmId] : array();
            if ($bn !== '' && !empty($goldentraByBn[$bn])) {
                $merged = array_merge($merged, $goldentraByBn[$bn]);
            }
            $result[$bmId] = $this->formatMasterRow($merged);
        }

        return $result;
    }

    /**
     * 按货号批量读取主档展示字段（门店/仓库库存兜底）
     *
     * @param array $bnList
     * @return array bn => field => value
     */
    public function getMasterByBns(array $bnList)
    {
        $bnList = array_values(array_filter(array_unique(array_map('trim', $bnList))));
        if (empty($bnList) || !$this->isEnabled()) {
            return array();
        }

        $goldentraByBn = $this->loadGoldentraFieldsByBns($bnList, $this->getGoldentraFieldKeys());
        $result = array();
        foreach ($bnList as $bn) {
            $merged = !empty($goldentraByBn[$bn]) ? $goldentraByBn[$bn] : array();
            $result[$bn] = $this->formatMasterRow($merged);
        }

        return $result;
    }

    /**
     * 高级筛选：通过 SQL 子查询过滤，避免 PHP 侧加载超大 bm_id 数组
     *
     * @param string $idColumn 主键列名，总库存为 bm_id，仓库库存为 product_id
     */
    public function applyFilter(&$filter, array &$where, $tableAlias = null, $idColumn = 'bm_id')
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (!is_array($filter)) {
            return;
        }

        $active = $this->pickExtendFilter($filter);
        if (empty($active)) {
            return;
        }

        $prefix = $tableAlias ? $tableAlias . '.' : '';
        $idColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $idColumn);
        if ($idColumn === '') {
            $idColumn = 'bm_id';
        }

        $definitions = $this->getFieldDefinitions();
        foreach ($active as $field => $value) {
            if ($value === '' || empty($definitions[$field])) {
                continue;
            }

            $subquery = $this->buildFilterSubquery($field, $value, $definitions[$field]);
            if ($subquery === '') {
                $where[] = ' ' . $prefix . $idColumn . ' = -1 ';
                continue;
            }

            $where[] = ' ' . $prefix . $idColumn . ' IN (' . $subquery . ') ';
        }
    }

    /**
     * 构建单个主档筛选条件的 bm_id 子查询 SQL
     */
    protected function buildFilterSubquery($field, $value, array $definition)
    {
        if ($definition['source'] === 'goldentra') {
            return $this->buildGoldentraFilterSubquery($field, $value);
        }

        return $this->buildPropFilterSubquery(
            $field,
            $value,
            !empty($definition['options'])
        );
    }

    /**
     * props 表子查询
     */
    protected function buildPropFilterSubquery($col, $value, $exact = false)
    {
        $definitions = $this->getFieldDefinitions();
        if (empty($definitions[$col]) || $definitions[$col]['source'] !== 'prop') {
            return '';
        }

        $db = kernel::database();
        $esc = kernel::single('goldentra_db')->escape($col, $db);
        $valEsc = kernel::single('goldentra_db')->escape($value, $db);

        if ($exact) {
            return "SELECT DISTINCT bm_id FROM sdb_material_basic_material_props
                WHERE props_col = '{$esc}' AND props_value = '{$valEsc}'";
        }

        return "SELECT DISTINCT bm_id FROM sdb_material_basic_material_props
            WHERE props_col = '{$esc}' AND props_value LIKE '%{$valEsc}%'";
    }

    /**
     * goldentra 主档表子查询（大类编码/名称等）
     */
    protected function buildGoldentraFilterSubquery($field, $value)
    {
        $definitions = $this->getFieldDefinitions();
        if (empty($definitions[$field]) || $definitions[$field]['source'] !== 'goldentra') {
            return '';
        }

        if (!in_array($field, $this->getGoldentraFieldKeys(), true)) {
            return '';
        }

        $db = kernel::database();
        $fieldEsc = kernel::single('goldentra_db')->escape($field, $db);
        $valEsc = kernel::single('goldentra_db')->escape($value, $db);

        return "SELECT DISTINCT m.bm_id
            FROM sdb_material_basic_material AS m
            INNER JOIN sdb_goldentra_material AS g ON g.bn = m.material_bn
            WHERE g.{$fieldEsc} LIKE '%{$valEsc}%'";
    }

    protected function loadPropsByBmIds(array $bmIds)
    {
        $propCols = $this->getPropFieldKeys();
        if (empty($propCols)) {
            return array();
        }

        $rows = app::get('material')->model('basic_material_props')->getList(
            'bm_id,props_col,props_value',
            array(
                'bm_id|in' => $bmIds,
                'props_col|in' => $propCols,
            )
        );

        $result = array();
        foreach ((array) $rows as $row) {
            $bmId = intval($row['bm_id']);
            $result[$bmId][$row['props_col']] = $row['props_value'];
        }

        return $result;
    }

    protected function loadGoldentraFieldsByBns(array $bnList, array $fields)
    {
        $bnList = array_values(array_filter(array_unique(array_map('trim', $bnList))));
        $fields = array_values(array_filter(array_unique($fields)));
        if (empty($bnList) || empty($fields)) {
            return array();
        }

        // 只查 goldentra 来源字段，避免把 props 字段拼进主档 SQL
        $allowed = array_flip($this->getGoldentraFieldKeys());
        $fields = array_values(array_filter($fields, function ($f) use ($allowed) {
            return isset($allowed[$f]);
        }));
        if (empty($fields)) {
            return array();
        }

        $db = kernel::database();
        $escaped = array();
        foreach ($bnList as $bn) {
            $escaped[] = "'" . kernel::single('goldentra_db')->escape($bn, $db) . "'";
        }
        $bnIn = implode(',', $escaped);

        $selectFields = array('g.bn');
        foreach ($fields as $field) {
            $col = kernel::single('goldentra_db')->escape($field, $db);
            $selectFields[] = 'g.' . $col;
        }

        // 一个基础物料 bn 对应大量唯一码主档行；只取每个 bn 最新一条，避免 select 撑爆内存
        $sql = 'SELECT ' . implode(', ', $selectFields) . '
            FROM sdb_goldentra_material AS g
            INNER JOIN (
                SELECT bn, MAX(material_id) AS material_id
                FROM sdb_goldentra_material
                WHERE bn IN (' . $bnIn . ')
                GROUP BY bn
            ) AS latest ON latest.material_id = g.material_id';
        $rows = $db->select($sql);

        $result = array();
        foreach ((array) $rows as $row) {
            $bn = trim((string) $row['bn']);
            if ($bn === '') {
                continue;
            }
            $item = array();
            foreach ($fields as $field) {
                $item[$field] = isset($row[$field]) ? trim((string) $row[$field]) : '';
            }
            $result[$bn] = $item;
        }

        return $result;
    }

    protected function formatMasterRow(array $props)
    {
        $row = array();
        foreach ($this->getFieldDefinitions() as $field => $def) {
            $value = isset($props[$field]) ? trim((string) $props[$field]) : '';
            if (!empty($def['options'])) {
                $row[$field] = isset($def['options'][$value]) ? $def['options'][$value] : $value;
            } else {
                $row[$field] = $value;
            }
        }

        return $row;
    }
}
