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
 * 经销商物料价格管理
 */

class dealer_mdl_goods_price extends dbeav_model
{
    //是否有导出配置
    var $has_export_cnf = true;

    
    public function searchOptions()
    {
        return array(
            'bs_bn' => '经销商编码',
            'bs_name' => '经销商名称',
            'material_bn' => '基础物料编码',
        );
    }
    
    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = ' 1 ';
        
        // 经销商编码搜索
        if (isset($filter['bs_bn'])) {
            $dealerObj = app::get('dealer')->model('business');
            $tempData = $dealerObj->dump(array('bs_bn' => $filter['bs_bn']), 'bs_id');
            if ($tempData) {
                $bs_id = $tempData['bs_id'];
                $where .= " AND bs_id = " . $bs_id;
            } else {
                // 如果经销商编码不存在，返回空结果
                $where .= " AND 1 = 0";
            }
            unset($filter['bs_bn']);
        }
        
        // 经销商名称搜索
        if (isset($filter['bs_name']) && !empty($filter['bs_name'])) {
            $dealerObj = app::get('dealer')->model('business');
            $dealerList = $dealerObj->getList('bs_id', array('name|has' => $filter['bs_name']));
            if (!empty($dealerList)) {
                $bsIds = array_column($dealerList, 'bs_id');
                $bsIdsStr = implode(',', $bsIds);
                $where .= " AND bs_id IN ({$bsIdsStr})";
            } else {
                // 如果经销商名称不存在，返回空结果
                $where .= " AND 1 = 0";
            }
            unset($filter['bs_name']);
        }
        
        // 基础物料编码搜索
        if (isset($filter['material_bn'])) {
            $basicMaterialObj = app::get('material')->model('basic_material');
            $tempData = $basicMaterialObj->dump(array('material_bn' => $filter['material_bn']), 'bm_id');
            if ($tempData) {
                $bm_id = $tempData['bm_id'];
                $where .= " AND bm_id = " . $bm_id;
            } else {
                // 如果基础物料编码不存在，返回空结果
                $where .= " AND 1 = 0";
            }
            unset($filter['material_bn']);
        }
        return parent::_filter($filter, $tableAlias, $baseWhere) . " AND " . $where;
    }
    
    /**
     * 经销商字段显示编码而非名称
     */
    public function modifier_bs_id($data)
    {
        if (!$data) return '';
        $dealerObj = app::get('dealer')->model('business');
        $dealer = $dealerObj->dump(array('bs_id' => $data), 'bs_bn');
        return $dealer ? $dealer['bs_bn'] : $data;
    }
    
    /**
     * 基础物料字段显示编码而非名称
     */
    public function modifier_bm_id($data)
    {
        if (!$data) return '';
        $materialObj = app::get('material')->model('basic_material');
        $material = $materialObj->dump(array('bm_id' => $data), 'material_bn');
        return $material ? $material['material_bn'] : $data;
    }
    
    /**
     * 生效时间字段格式化
     */
    public function modifier_start_time($data)
    {
        return $data ? date('Y-m-d H:i:s', $data) : '';
    }
    
    /**
     * 过期时间字段格式化
     */
    public function modifier_end_time($data)
    {
        return $data ? date('Y-m-d H:i:s', $data) : '';
    }
    
    /**
     * 验证时间数据
     * @param int|null $start_time 生效时间
     * @param int|null $end_time 过期时间
     * @param int $bs_id 经销商ID
     * @param int $bm_id 物料ID
     * @param int|null $exclude_id 排除的记录ID（编辑时使用）
     * @return true|string 验证通过返回true，失败返回错误信息
     */
    public function validateTimeData($start_time, $end_time, $bs_id, $bm_id, $exclude_id = null)
    {
        /*
        // 验证过期时间必须大于当前时间
        if ($end_time && $end_time <= $current_time) {
            return '过期时间必须大于当前时间';
        }
        */
        
        // 验证过期时间必须大于生效时间
        if ($start_time && $end_time && $end_time <= $start_time) {
            return '过期时间必须大于生效时间';
        }
        
        // 检查时间重叠
        $filter = array(
            'bs_id' => $bs_id,
            'bm_id' => $bm_id
        );
        
        if ($exclude_id) {
            $filter['id|noequal'] = $exclude_id;
        }
        
        $existing_records = $this->getList('id,start_time,end_time', $filter);
        
        foreach ($existing_records as $record) {
            $record_start = $record['start_time'];
            $record_end = $record['end_time'];
            
            // 检查时间重叠
            if ($this->isTimeOverlap($start_time, $end_time, $record_start, $record_end)) {
                return '该时间段内已存在价格记录，请检查生效时间和过期时间';
            }
        }
        
        return true;
    }
    
    /**
     * 检查两个时间段是否重叠
     * @param int|null $start1 时间段1开始时间
     * @param int|null $end1 时间段1结束时间
     * @param int|null $start2 时间段2开始时间
     * @param int|null $end2 时间段2结束时间
     * @return bool 是否重叠
     */
    public function isTimeOverlap($start1, $end1, $start2, $end2)
    {
        // 如果任一时间为空，认为不重叠
        if (!$start1 || !$end1 || !$start2 || !$end2) {
            return false;
        }
        
        // 时间段重叠的条件：一个时间段的开始时间小于另一个时间段的结束时间，且结束时间大于另一个时间段的开始时间
        // 注意：这里使用 <= 和 >= 来处理边界重叠的情况
        return ($start1 < $end2) && ($end1 > $start2);
    }
    
    /**
     * 清理和验证价格数据
     * @param string $price 价格字符串
     * @return array [是否有效, 清理后的价格, 错误信息]
     */
    public function validatePrice($price)
    {
        $price = trim($price);
        if (empty($price)) {
            return [false, '', '价格不能为空'];
        }
        
        if (!is_numeric($price)) {
            return [false, '', '价格必须是数字'];
        }
        
        if ($price < 0) {
            return [false, '', '价格不能小于0'];
        }
        
        return [true, $price, ''];
    }

}
