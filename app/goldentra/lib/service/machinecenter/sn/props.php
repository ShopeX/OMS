<?php

/**
 * 机器中心 SN props 扩展 Service（读 sdb_machinecenter_material_sn_props）
 *
 * 列表 / 导出：model extra_cols + fillListRows
 * 快捷搜索 / 高级筛选：getSearchOptions + getExtendFilterColumns + applyFilter
 */
class goldentra_service_machinecenter_sn_props
{
    const COLUMN_PREFIX = 'column_sn_prop_';
    const FILTER_PREFIX = 'sn_prop_filter_';
    const FUNC_SUFFIX = 'sn_props';

    const PROP_CHANGED_AT = 'price_method_changed_at';
    const PROP_OLD_BN = 'price_method_old_bn';

    /**
     * @return array field => def
     */
    protected function getFieldDefinitions()
    {
        return array(
            self::PROP_CHANGED_AT => array(
                'label' => '计价方式变更时间',
                'width' => '150',
                'format' => 'time',
                'filter' => array(
                    'type' => 'time',
                    'filtertype' => 'time',
                    'filterdefault' => true,
                ),
            ),
            self::PROP_OLD_BN => array(
                'label' => '变更前物料编码',
                'width' => '160',
                'filter' => array(
                    'type' => 'varchar(30)',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
            ),
        );
    }

    public function isEnabled()
    {
        return app::get('goldentra')->is_installed()
            && app::get('machinecenter')->is_installed();
    }

    public function getColumnKey($field)
    {
        return self::COLUMN_PREFIX . $field;
    }

    public function getFilterRequestKey($field)
    {
        return self::FILTER_PREFIX . $field;
    }

    protected function getFieldKeys()
    {
        return array_keys($this->getFieldDefinitions());
    }

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

    public function getExtraCols()
    {
        if (!$this->isEnabled()) {
            return array();
        }
        $cols = array();
        foreach ($this->getFieldDefinitions() as $field => $def) {
            $cols[$this->getColumnKey($field)] = array(
                'label' => $def['label'],
                'width' => isset($def['width']) ? $def['width'] : '120',
                'func_suffix' => self::FUNC_SUFFIX,
            );
        }
        return $cols;
    }

    /**
     * 高级筛选列
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
     * 顶部快捷搜索（仅文本类）
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
            if (!empty($def['format']) && $def['format'] === 'time') {
                continue;
            }
            if (isset($def['filter']['type']) && is_array($def['filter']['type'])) {
                continue;
            }
            $options[$this->getFilterRequestKey($field)] = $def['label'];
        }
        return $options;
    }

    /**
     * @param array $filter
     * @return bool
     */
    public function hasExtendFilter($filter)
    {
        if (!is_array($filter)) {
            return false;
        }
        $prefix = self::FILTER_PREFIX;
        $prefixLen = strlen($prefix);
        foreach ($filter as $key => $val) {
            $k = (string) $key;
            // _sn_prop_filter_xxx_search
            if (strpos($k, '_' . $prefix) === 0 && substr($k, -7) === '_search') {
                if ($val !== '' && $val !== null) {
                    return true;
                }
                continue;
            }
            // sn_prop_filter_xxx 或 sn_prop_filter_xxx|than / _from/_to
            $base = $k;
            if (($p = strpos($k, '|')) !== false) {
                $base = substr($k, 0, $p);
            }
            $base = preg_replace('/_(from|to)$/', '', $base);
            if (strncmp($base, $prefix, $prefixLen) !== 0) {
                continue;
            }
            if ($val !== '' && $val !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * 高级/快捷筛选：按 sn_id 子查询 material_sn_props
     *
     * @param array $filter
     * @param array $where
     * @param string|null $tableAlias
     * @param string $idColumn SN 主键列，默认 id
     */
    public function applyFilter(&$filter, array &$where, $tableAlias = null, $idColumn = 'id')
    {
        if (!$this->isEnabled() || !is_array($filter)) {
            return;
        }

        $active = $this->pickExtendFilter($filter);
        if (empty($active)) {
            return;
        }

        $prefix = $tableAlias ? $tableAlias . '.' : '';
        $idColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $idColumn);
        if ($idColumn === '') {
            $idColumn = 'id';
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
     * 抽出并移除 sn_prop_filter_* 条件
     *
     * @param array $filter
     * @return array field => value|array
     */
    protected function pickExtendFilter(array &$filter)
    {
        $active = array();
        $definitions = $this->getFieldDefinitions();
        foreach ($definitions as $field => $def) {
            if (empty($def['filter'])) {
                continue;
            }
            $requestKey = $this->getFilterRequestKey($field);
            $collected = null;

            if (!empty($def['format']) && $def['format'] === 'time') {
                $range = $this->pickTimeRange($filter, $requestKey);
                if ($range !== null) {
                    $collected = $range;
                }
            } elseif (isset($filter[$requestKey]) && $filter[$requestKey] !== '' && $filter[$requestKey] !== null) {
                $collected = is_string($filter[$requestKey])
                    ? trim($filter[$requestKey])
                    : $filter[$requestKey];
                unset($filter[$requestKey]);
            }

            if ($collected !== null && $collected !== '') {
                $active[$field] = $collected;
            }
        }
        return $active;
    }

    /**
     * 解析时间筛选（对齐 dbeav_filter）：
     * - _{key}_search = than|lthan|nequal|between|bthan|sthan
     * - {key} / {key}_from / {key}_to + _DTIME_[H|M]
     *
     * @param array $filter
     * @param string $requestKey
     * @return array|null array(from=>ts|null, to=>ts|null)  from 含等号用 bthan；to 用 lthan 语义在 SQL 侧处理
     *                    另含 op 便于精确比较
     */
    protected function pickTimeRange(array &$filter, $requestKey)
    {
        $searchKey = '_' . $requestKey . '_search';
        $op = isset($filter[$searchKey]) ? trim((string) $filter[$searchKey]) : '';
        if ($op !== '') {
            unset($filter[$searchKey]);
        }

        $from = null;
        $to = null;
        $hit = false;

        if ($op === 'between') {
            $fromStr = isset($filter[$requestKey . '_from']) ? $filter[$requestKey . '_from'] : '';
            $toStr = isset($filter[$requestKey . '_to']) ? $filter[$requestKey . '_to'] : '';
            unset($filter[$requestKey . '_from'], $filter[$requestKey . '_to'], $filter[$requestKey]);
            $fromTs = $this->combineDateTime($filter, $requestKey . '_from', $fromStr, false);
            $toTs = $this->combineDateTime($filter, $requestKey . '_to', $toStr, false);
            $this->clearDtimeKeys($filter, array($requestKey, $requestKey . '_from', $requestKey . '_to'));
            if ($fromTs === null && $toTs === null) {
                return null;
            }
            return array('from' => $fromTs, 'to' => $toTs, 'op' => 'between');
        }

        if (isset($filter[$requestKey]) && $filter[$requestKey] !== '' && $filter[$requestKey] !== null) {
            $dateStr = $filter[$requestKey];
            unset($filter[$requestKey]);
            $hit = true;
            $ts = $this->combineDateTime($filter, $requestKey, $dateStr, false);
            $this->clearDtimeKeys($filter, array($requestKey));

            if ($ts === null) {
                return null;
            }

            // 未指定运算符时：按当天
            if ($op === '' || $op === 'nequal' || $op === 'tequal') {
                return array('from' => $ts, 'to' => $ts + 86399, 'op' => 'between');
            }
            if ($op === 'than') {
                // 晚于 / 大于
                return array('from' => $ts, 'to' => null, 'op' => 'than');
            }
            if ($op === 'bthan') {
                return array('from' => $ts, 'to' => null, 'op' => 'bthan');
            }
            if ($op === 'lthan') {
                // 早于 / 小于
                return array('from' => null, 'to' => $ts, 'op' => 'lthan');
            }
            if ($op === 'sthan') {
                return array('from' => null, 'to' => $ts, 'op' => 'sthan');
            }
            // 未知运算符：当天
            return array('from' => $ts, 'to' => $ts + 86399, 'op' => 'between');
        }

        // 兼容 key|than / key|lthan
        $thanKey = $requestKey . '|than';
        $lthanKey = $requestKey . '|lthan';
        if (isset($filter[$thanKey]) && $filter[$thanKey] !== '' && $filter[$thanKey] !== null) {
            $from = $this->toTimestamp($filter[$thanKey], false);
            unset($filter[$thanKey]);
            $hit = true;
            $op = 'than';
        }
        if (isset($filter[$lthanKey]) && $filter[$lthanKey] !== '' && $filter[$lthanKey] !== null) {
            $to = $this->toTimestamp($filter[$lthanKey], false);
            unset($filter[$lthanKey]);
            $hit = true;
            $op = 'lthan';
        }
        if ($hit) {
            return array('from' => $from, 'to' => $to, 'op' => $op !== '' ? $op : 'between');
        }

        return null;
    }

    /**
     * 日期 + _DTIME_ 时分 → 时间戳
     *
     * @param array $filter
     * @param string $dtimeKey
     * @param mixed $dateStr
     * @param bool $endOfDay
     * @return int|null
     */
    protected function combineDateTime(array &$filter, $dtimeKey, $dateStr, $endOfDay = false)
    {
        $dateStr = is_string($dateStr) ? trim($dateStr) : $dateStr;
        if ($dateStr === '' || $dateStr === null) {
            return null;
        }
        $h = '00';
        $m = '00';
        if (isset($filter['_DTIME_']['H'][$dtimeKey]) && $filter['_DTIME_']['H'][$dtimeKey] !== '') {
            $h = str_pad((string) $filter['_DTIME_']['H'][$dtimeKey], 2, '0', STR_PAD_LEFT);
        }
        if (isset($filter['_DTIME_']['M'][$dtimeKey]) && $filter['_DTIME_']['M'][$dtimeKey] !== '') {
            $m = str_pad((string) $filter['_DTIME_']['M'][$dtimeKey], 2, '0', STR_PAD_LEFT);
        }
        if (is_numeric($dateStr) && intval($dateStr) > 1000000000) {
            return intval($dateStr);
        }
        $ts = strtotime(trim((string) $dateStr) . ' ' . $h . ':' . $m . ':00');
        if ($ts === false) {
            return $this->toTimestamp($dateStr, $endOfDay);
        }
        return $ts;
    }

    /**
     * @param array $filter
     * @param array $keys
     */
    protected function clearDtimeKeys(array &$filter, array $keys)
    {
        if (!isset($filter['_DTIME_']) || !is_array($filter['_DTIME_'])) {
            return;
        }
        foreach ($keys as $key) {
            if (isset($filter['_DTIME_']['H'][$key])) {
                unset($filter['_DTIME_']['H'][$key]);
            }
            if (isset($filter['_DTIME_']['M'][$key])) {
                unset($filter['_DTIME_']['M'][$key]);
            }
        }
        if (isset($filter['_DTIME_']['H']) && !$filter['_DTIME_']['H']) {
            unset($filter['_DTIME_']['H']);
        }
        if (isset($filter['_DTIME_']['M']) && !$filter['_DTIME_']['M']) {
            unset($filter['_DTIME_']['M']);
        }
        if (!$filter['_DTIME_']) {
            unset($filter['_DTIME_']);
        }
    }

    /**
     * @param mixed $val
     * @param bool $endOfDay
     * @return int|null
     */
    protected function toTimestamp($val, $endOfDay = false)
    {
        if ($val === '' || $val === null) {
            return null;
        }
        if (is_numeric($val)) {
            $ts = intval($val);
            if ($ts > 19000101 && $ts < 21001231 && strlen((string) $ts) === 8) {
                $y = intval(substr((string) $ts, 0, 4));
                $m = intval(substr((string) $ts, 4, 2));
                $d = intval(substr((string) $ts, 6, 2));
                return $endOfDay ? mktime(23, 59, 59, $m, $d, $y) : mktime(0, 0, 0, $m, $d, $y);
            }
            return $ts;
        }
        $str = trim((string) $val);
        $ts = strtotime($str);
        if ($ts === false) {
            return null;
        }
        if ($endOfDay && preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $str)) {
            return $ts + 86399;
        }
        return $ts;
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $definition
     * @return string
     */
    protected function buildFilterSubquery($field, $value, array $definition)
    {
        $db = kernel::database();
        $escCol = kernel::single('goldentra_db')->escape($field, $db);

        if (!empty($definition['format']) && $definition['format'] === 'time' && is_array($value)) {
            $conds = array("props_col = '{$escCol}'");
            $op = isset($value['op']) ? $value['op'] : 'between';
            $from = isset($value['from']) ? $value['from'] : null;
            $to = isset($value['to']) ? $value['to'] : null;

            if ($op === 'than' && $from !== null) {
                $conds[] = 'CAST(props_value AS UNSIGNED) > ' . intval($from);
            } elseif ($op === 'bthan' && $from !== null) {
                $conds[] = 'CAST(props_value AS UNSIGNED) >= ' . intval($from);
            } elseif ($op === 'lthan' && $to !== null) {
                $conds[] = 'CAST(props_value AS UNSIGNED) < ' . intval($to);
            } elseif ($op === 'sthan' && $to !== null) {
                $conds[] = 'CAST(props_value AS UNSIGNED) <= ' . intval($to);
            } else {
                if ($from !== null) {
                    $conds[] = 'CAST(props_value AS UNSIGNED) >= ' . intval($from);
                }
                if ($to !== null) {
                    $conds[] = 'CAST(props_value AS UNSIGNED) <= ' . intval($to);
                }
            }
            if (count($conds) <= 1) {
                return '';
            }
            return 'SELECT DISTINCT sn_id FROM sdb_machinecenter_material_sn_props WHERE '
                . implode(' AND ', $conds);
        }

        $valEsc = kernel::single('goldentra_db')->escape((string) $value, $db);
        return "SELECT DISTINCT sn_id FROM sdb_machinecenter_material_sn_props
            WHERE props_col = '{$escCol}' AND props_value LIKE '%{$valEsc}%'";
    }

    /**
     * 按 sn_id(=row.id) 批量灌 props
     *
     * @param array $rows
     * @return array
     */
    public function fillListRows(array $rows)
    {
        if (!$this->isEnabled() || empty($rows)) {
            return $rows;
        }

        $snIds = array();
        foreach ($rows as $row) {
            if (!empty($row['id'])) {
                $snIds[] = intval($row['id']);
            }
        }
        $snIds = array_values(array_unique(array_filter($snIds)));
        $propMap = $this->loadPropsBySnIds($snIds);

        $defs = $this->getFieldDefinitions();
        foreach ($rows as $k => $row) {
            $sid = !empty($row['id']) ? intval($row['id']) : 0;
            $props = ($sid > 0 && isset($propMap[$sid])) ? $propMap[$sid] : array();
            foreach ($defs as $field => $def) {
                $colKey = $this->getColumnKey($field);
                $raw = isset($props[$field]) ? $props[$field] : '';
                $rows[$k][$colKey] = $this->formatValue($raw, $def);
            }
        }
        return $rows;
    }

    /**
     * @param array $snIds
     * @return array sn_id => props_col => props_value
     */
    protected function loadPropsBySnIds(array $snIds)
    {
        $map = array();
        if (!$snIds) {
            return $map;
        }
        $cols = $this->getFieldKeys();
        $rows = app::get('machinecenter')->model('material_sn_props')->getList(
            'sn_id,props_col,props_value',
            array(
                'sn_id' => $snIds,
                'props_col' => $cols,
            ),
            0,
            -1
        );
        foreach ((array) $rows as $r) {
            $sid = intval($r['sn_id']);
            $col = isset($r['props_col']) ? (string) $r['props_col'] : '';
            if ($sid <= 0 || $col === '') {
                continue;
            }
            if (!isset($map[$sid])) {
                $map[$sid] = array();
            }
            $map[$sid][$col] = isset($r['props_value']) ? $r['props_value'] : '';
        }
        return $map;
    }

    /**
     * @param string $raw
     * @param array $def
     * @return string
     */
    protected function formatValue($raw, array $def)
    {
        $raw = (string) $raw;
        if ($raw === '') {
            return '';
        }
        if (!empty($def['format']) && $def['format'] === 'time') {
            $ts = intval($raw);
            return $ts > 0 ? date('Y-m-d H:i:s', $ts) : '';
        }
        return $raw;
    }
}
