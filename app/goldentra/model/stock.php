<?php

class goldentra_mdl_stock extends dbeav_model
{
    /**
     * 快捷搜索顺序：模号编码放第一位
     */
    public function searchOptions()
    {
        $columns = parent::searchOptions();
        if (isset($columns['mold_code'])) {
            $moldLabel = $columns['mold_code'];
            unset($columns['mold_code']);
            $columns = array_merge(array('mold_code' => $moldLabel), $columns);
        }
        return $columns;
    }

    /**
     * 历史未落库模号时，列表/导出按唯一码从主档回填展示
     */
    public function getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderType = null)
    {
        $list = parent::getList($cols, $filter, $offset, $limit, $orderType);
        if (!$list) {
            return $list;
        }

        $needCodes = array();
        foreach ($list as $row) {
            if (empty($row['mold_code']) && !empty($row['unique_code'])) {
                $needCodes[] = $row['unique_code'];
            }
        }
        if (!$needCodes) {
            return $list;
        }

        $map = array();
        $rows = app::get('goldentra')->model('material')->getList(
            'unique_code,mold_code',
            array('unique_code' => array_values(array_unique($needCodes)))
        );
        foreach ((array) $rows as $row) {
            $map[trim((string) $row['unique_code'])] = trim((string) $row['mold_code']);
        }

        foreach ($list as $k => $row) {
            if (empty($row['mold_code']) && !empty($row['unique_code'])) {
                $uc = trim((string) $row['unique_code']);
                if (isset($map[$uc]) && $map[$uc] !== '') {
                    $list[$k]['mold_code'] = $map[$uc];
                }
            }
        }

        return $list;
    }

    /**
     * 模号筛选：兼容 stock.mold_code 未回填的旧数据（按主档 unique_code 兜底）
     */
    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $filter = (array) $filter;
        $extra = '';
        if (isset($filter['mold_code']) && $filter['mold_code'] !== '' && $filter['mold_code'] !== null) {
            $mold = is_array($filter['mold_code'])
                ? $filter['mold_code']
                : trim((string) $filter['mold_code']);
            unset($filter['mold_code']);

            $prefix = $tableAlias ? $tableAlias . '.' : '';
            $materialMdl = app::get('goldentra')->model('material');
            $esc = kernel::single('goldentra_db');

            if (is_array($mold)) {
                $molds = array();
                foreach ($mold as $m) {
                    $m = trim((string) $m);
                    if ($m !== '') {
                        $molds[] = $m;
                    }
                }
                if (!$molds) {
                    $extra = ' AND 0';
                } else {
                    $materials = $materialMdl->getList('unique_code', array('mold_code' => $molds));
                    $codes = array();
                    foreach ((array) $materials as $row) {
                        $uc = trim((string) $row['unique_code']);
                        if ($uc !== '') {
                            $codes[] = "'" . $esc->escape($uc) . "'";
                        }
                    }
                    $moldIn = array();
                    foreach ($molds as $m) {
                        $moldIn[] = "'" . $esc->escape($m) . "'";
                    }
                    $parts = array($prefix . 'mold_code IN (' . implode(',', $moldIn) . ')');
                    if ($codes) {
                        $parts[] = $prefix . 'unique_code IN (' . implode(',', array_unique($codes)) . ')';
                    }
                    $extra = ' AND (' . implode(' OR ', $parts) . ')';
                }
            } else {
                $materials = $materialMdl->getList('unique_code', array('mold_code' => $mold));
                $codes = array();
                foreach ((array) $materials as $row) {
                    $uc = trim((string) $row['unique_code']);
                    if ($uc !== '') {
                        $codes[] = "'" . $esc->escape($uc) . "'";
                    }
                }
                $moldEsc = $esc->escape($mold);
                $parts = array($prefix . "mold_code = '{$moldEsc}'");
                if ($codes) {
                    $parts[] = $prefix . 'unique_code IN (' . implode(',', array_unique($codes)) . ')';
                }
                $extra = ' AND (' . implode(' OR ', $parts) . ')';
            }
        }

        return parent::_filter($filter, $tableAlias, $baseWhere) . $extra;
    }
}
