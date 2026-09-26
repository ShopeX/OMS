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

class material_customcols{

    /**
     * 获取cols
     * @return mixed 返回结果
     */
    public function getcols(){

        $customcolsMdl = app::get('desktop')->model('customcols');

        $customcolslist = $customcolsMdl->getList('col_name,col_key,col_type,filter_data', array(
            'tbl_name' => 'sdb_material_basic_material',
            'disabled' => 'false',
        ), 0, -1, 'p_order ASC, id ASC');

        if (!is_array($customcolslist)) {
            return array();
        }

        foreach ($customcolslist as &$col) {
            if (empty($col['col_type'])) {
                $col['col_type'] = 'input';
            }
            $col['options'] = array();
            if (!empty($col['filter_data'])) {
                $decoded = json_decode($col['filter_data'], true);
                if (is_array($decoded) && !empty($decoded['options']) && is_array($decoded['options'])) {
                    $col['options'] = $decoded['options'];
                }
            }
            unset($col['filter_data']);
        }
        unset($col);

        return $customcolslist;
    }

    /**
     * 合并 basic_material_props 值到自定义列（编辑/详情回显）
     *
     * @param array $customcols
     * @param array $arr_props props_col => props_value
     * @return array
     */
    public function attachPropsValues(array $customcols, array $arr_props)
    {
        foreach ($customcols as $k => $v) {
            if (!empty($v['col_type']) && $v['col_type'] === 'radiobox') {
                if (array_key_exists($v['col_key'], $arr_props)
                    && $arr_props[$v['col_key']] !== ''
                    && $arr_props[$v['col_key']] !== null) {
                    $val = $this->normalizeRadioboxValue($arr_props[$v['col_key']]);
                } else {
                    $val = '0';
                }
                $customcols[$k]['col_value']         = $val;
                $customcols[$k]['col_display_value'] = ($val === '1') ? '是' : '否';
                $customcols[$k]['is_radiobox_yes']   = ($val === '1') ? '1' : '';
                $customcols[$k]['is_radiobox_no']    = ($val === '0') ? '1' : '';
                continue;
            }
            if (array_key_exists($v['col_key'], $arr_props)) {
                $raw = $arr_props[$v['col_key']];
                $customcols[$k]['col_value'] = $raw;
                $customcols[$k]['col_display_value'] = $this->formatDisplayValue($v, $raw);
            } else {
                $customcols[$k]['col_display_value'] = '';
            }
        }
        return $customcols;
    }

    /**
     * 自定义列 props 是否应写入库（radiobox 选「否」时为 "0"，不能用 empty/if($pv) 判断）
     *
     * @param mixed $pv
     * @return bool
     */
    public function isPropValueSavable($pv)
    {
        return $pv !== '' && $pv !== null;
    }

    /**
     * radiobox 导入/回显：Excel「是/否」或 1/0 统一为 props 存储值 "1"/"0"
     *
     * @param mixed $raw
     * @return string
     */
    public function normalizeRadioboxValue($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '是' || $raw === '1') {
            return '1';
        }
        if ($raw === '否' || $raw === '0') {
            return '0';
        }
        if (is_numeric($raw)) {
            return (string) (int) $raw;
        }
        return '0';
    }

    /**
     * select 导入：允许填 value 或 label，统一为 options 中的 value
     *
     * @param mixed $raw
     * @param array $options
     * @return string|null 匹配失败返回 null
     */
    public function normalizeSelectValue($raw, array $options)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || empty($options)) {
            return null;
        }
        foreach ($options as $opt) {
            $value = isset($opt['value']) ? (string) $opt['value'] : '';
            $label = isset($opt['label']) ? (string) $opt['label'] : $value;
            if ($raw === $value || $raw === $label) {
                return $value;
            }
        }
        return null;
    }

    /**
     * 列表/详情展示：按类型把存储值转为可读文案
     *
     * @param array $col
     * @param mixed $raw
     * @return string
     */
    public function formatDisplayValue(array $col, $raw)
    {
        $type = isset($col['col_type']) ? $col['col_type'] : 'input';
        if ($type === 'radiobox') {
            $normalized = $this->normalizeRadioboxValue($raw);
            return ($normalized === '1') ? '是' : '否';
        }
        if ($type === 'select') {
            $options = isset($col['options']) && is_array($col['options']) ? $col['options'] : array();
            $raw = (string) $raw;
            foreach ($options as $opt) {
                $value = isset($opt['value']) ? (string) $opt['value'] : '';
                if ($raw === $value) {
                    return isset($opt['label']) ? (string) $opt['label'] : $value;
                }
            }
            return $raw;
        }
        return (string) $raw;
    }

    /**
     * 导入自定义列 props：radiobox 转 1/0，select 映射为 value，空值跳过
     *
     * @param array $custom_props col_key => raw value
     * @return array
     */
    public function normalizeImportCustomProps(array $custom_props)
    {
        $colMetaMap = array();
        foreach ($this->getcols() as $col) {
            $colMetaMap[$col['col_key']] = $col;
        }
        $normalized = array();
        foreach ($custom_props as $colKey => $rawValue) {
            if (!$this->isPropValueSavable($rawValue)) {
                continue;
            }
            $meta = isset($colMetaMap[$colKey]) ? $colMetaMap[$colKey] : array();
            $colType = isset($meta['col_type']) ? $meta['col_type'] : '';
            if ($colType === 'radiobox') {
                $normalized[$colKey] = $this->normalizeRadioboxValue($rawValue);
            } elseif ($colType === 'select') {
                $options = isset($meta['options']) && is_array($meta['options']) ? $meta['options'] : array();
                $mapped = $this->normalizeSelectValue($rawValue, $options);
                if ($mapped === null) {
                    continue;
                }
                $normalized[$colKey] = $mapped;
            } else {
                $normalized[$colKey] = $rawValue;
            }
        }
        return $normalized;
    }

    /**
     * 获取colstemplate
     * @return mixed 返回结果
     */
    public function getcolstemplate(){

        $cols = $this->getcols();
        $coltemp = array();
        foreach($cols as $v){

            $key = '*:'.$v['col_name'];
            $name = 'custom/'.$v['col_key'];

            $coltemp[$key] = $name;
        }

        return $coltemp;
    }

    /**
     * 销售物料导出用：读取 sdb_desktop_customcols 中 tbl_name = sdb_material_basic_material_props 的扩展列
     * @return array  [ ['col_name'=>xx, 'col_key'=>yy], ... ] 按 p_order, id 排序
     */
    public function getcolsForBmProps()
    {
        $customcolsMdl = app::get('desktop')->model('customcols');
        $list = $customcolsMdl->getList('col_name,col_key', array(
            'tbl_name' => 'sdb_material_basic_material_props',
            'disabled'  => 'false',
        ), 0, -1, 'p_order ASC, id ASC');
        return is_array($list) ? $list : array();
    }

    /**
     * 参照 getcolstemplate（sdb_material_basic_material），为 sdb_material_basic_material_props 提供统一模板格式
     * @return array  ['*:col_name' => 'sales_bm_props/col_key', ...]
     */
    public function getcolstemplateForBmProps()
    {
        $cols = $this->getcolsForBmProps();
        $coltemp = array();
        foreach ($cols as $v) {
            $key = '*:' . $v['col_name'];
            $name = 'sales_bm_props/' . $v['col_key'];
            $coltemp[$key] = $name;
        }
        return $coltemp;
    }

}
