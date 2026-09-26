<?php

class goldentra_mdl_material extends dbeav_model
{
    var $export_name = 'SAP主档信息';

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
     * 高级筛选：模号编码 / 基础物料编码支持换行批量精准匹配；主档标记
     */
    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $filter = (array) $filter;
        foreach (array('mold_code', 'bn') as $field) {
            if (empty($filter[$field]) || !is_string($filter[$field])) {
                continue;
            }
            $raw = str_replace(array("\r\n", "\r"), "\n", $filter[$field]);
            if (strpos($raw, "\n") === false && strpos($raw, ',') === false && strpos($raw, '，') === false) {
                $filter[$field] = trim($raw);
                continue;
            }
            $raw = str_replace(array('，', ','), "\n", $raw);
            $values = array_unique(array_map('trim', array_filter(explode("\n", $raw))));
            $filter[$field] = $values ? $values : array('__empty__');
        }

        $where = array();
        if (!empty($filter['material_label'])) {
            $billLabelObj = app::get('ome')->model('bill_label');
            $labelFilter = array(
                'label_id' => $filter['material_label'],
                'bill_type' => 'goldentra_material',
            );
            $remainFilter = $filter;
            unset($remainFilter['material_label']);
            $alias = $tableAlias ? $tableAlias . '.' : '';
            $sql = "SELECT bill_id FROM sdb_ome_bill_label bl
                LEFT JOIN sdb_goldentra_material m ON bl.bill_id=m.material_id
                WHERE " . $billLabelObj->_filter($labelFilter, 'bl') . " AND " . parent::_filter($remainFilter, 'm');
            $where[] = ' ' . $alias . "material_id IN (" . $sql . ") ";
            unset($filter['material_label']);
        }

        $sqlstr = '';
        if ($where) {
            $sqlstr .= implode(' AND ', $where) . ' AND ';
        }

        return $sqlstr . parent::_filter($filter, $tableAlias, $baseWhere);
    }

    public function modifier_cate_name($col, $list, $row)
    {
        return $this->modifierDictName($row, 'cate_code', $col);
    }

    public function modifier_class_name($col, $list, $row)
    {
        return $this->modifierDictName($row, 'class_code', $col);
    }

    public function modifier_texture_name($col, $list, $row)
    {
        return $this->modifierDictName($row, 'texture_code', $col);
    }

    public function modifier_small_series_name($col, $list, $row)
    {
        return $this->modifierDictName($row, 'small_series_code', $col);
    }

    public function modifier_pic_url($col, $list, $row)
    {
        $url = isset($row['pic_url']) ? goldentra_material_image::resolveProductUrl($row['pic_url']) : '';
        if ($url === '') {
            return '';
        }
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        if (preg_match('#^https?://#i', $url)) {
            return '<a href="' . $safeUrl . '" target="_blank" rel="noopener">'
                . '<img src="' . $safeUrl . '" alt="" style="max-height:40px;max-width:60px;" />'
                . '</a>';
        }
        return $safeUrl;
    }

    private function modifierDictName(array $row, $codeKey, $fallback)
    {
        $code = isset($row[$codeKey]) ? trim((string) $row[$codeKey]) : '';
        if ($code === '') {
            return '';
        }
        $name = goldentra_metadata::lookupMaterialDictLabel($codeKey, $code);
        if ($name !== '') {
            return $name;
        }
        $fallback = ($fallback !== '' && $fallback !== null) ? (string) $fallback : '';
        return $fallback !== '' ? $fallback : $code;
    }
}
