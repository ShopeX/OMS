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

class logisticsmanager_mdl_warehouse extends dbeav_model {

    /**
     * 获取_Schema
     * @return mixed 返回结果
     */
    public function get_Schema()
    {
        $data = parent::get_Schema();
        $regionlist = kernel::single('eccommon_platform_regions')->getRegionList();

        $regions = array();
        foreach($regionlist as $v){
            $regions[$v['region_id']] = $v['local_name'];
        }
        $data['columns']['regions'] = array (
            'type' => $regions,
            'label' => '覆盖范围',
            'comment' => '覆盖范围',
            'editable' => false,
            'width' =>75,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            
        );
        

        return $data;
        
    }

    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
    public function _filter($filter, $tableAlias=null, $baseWhere=null) {

        $where = '';
        if ($filter['regions']) {
            $regions = $filter['regions'];
            $where.=" AND FIND_IN_SET ($regions,region_ids)";
            unset($filter['regions']);
        }

        return parent::_filter($filter, $tableAlias, $baseWhere) . $where;
    }
    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        if (!strpos($cols,'region_names')) {
            $cols .= ',region_names';
        }
        if (!strpos($cols,'one_level_region_names')) {
            $cols .= ',one_level_region_names';
        }
        $data = parent::getList($cols, $filter, $offset, $limit, $orderType);

        return $data;
    }
    
    /**
     * modifier_b_type
     * @param mixed $value value
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_b_type($value, $list, $row)
    {
        $type_map = array(
            1 => '仓库',
            2 => '门店'
        );
        return isset($type_map[$value]) ? $type_map[$value] : '未知';
    }
}
?>