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
 * 产品线授权到店model类
 * @author maxiaochen@shopex.cn
 * @version 2024.06.28
 */
class dealer_mdl_series_endorse extends dbeav_model
{

    var $has_export_cnf = false;
    var $export_name    = '产品线授权到店';

    //列表排序
    public $defaultOrder = array('series_id ASC');

    /**
     * 获取_schema
     * @return mixed 返回结果
     */

    public function get_schema()
    {
        $init   = parent::get_schema();
        $schema = [
            'columns'  => [
                'en_id' => array(
                    'type' => 'int unsigned',
                    'required' => true,
                    'pkey' => true,
                    'extra' => 'auto_increment',
                    'label' => '产品线授权到店ID',
                ),
                'series_id' => array(
                    'type' => 'int unsigned',
                    'label' => '产品线ID',
                ),
                'series_code'   => array(
                    'type'            => 'varchar(30)',
                    'label'           => '产品线编码',
                    'is_title'        => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'order'           => 10,
                    'width'           => 110,
                ),
                'series_name'   => array(
                    'type'            => 'varchar(50)',
                    'label'           => '产品线名称',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'order'           => 20,
                    'width'           => 100,
                ),
                'betc_name'     => array(
                    'type'            => 'varchar(50)',
                    'label'           => '所属贸易公司',
                    'editable'        => false,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 30,
                    'width'           => 100,
                ),
                'status'        => array(
                    'type'            => array(
                        'active' => '启用',
                        'close'  => '停用',
                    ),
                    'default'         => 'active',
                    'label'           => '状态',
                    'filtertype' => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 40,
                    'width'           => 60,
                ),
                'bs_id' => array(
                    'type' => 'int unsigned',
                    'label' => '经销商ID',
                    'comment' => '一个经销店铺只能选一个经销商',
                ),
                'bs_bn'           => array(
                    'type'            => 'varchar(32)',
                    'label'           => '经销商编号',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => false,
                    'order'           => 50,
                    'width'           => 100,
                ),
                'bs_name'            => array(
                    'type'            => 'varchar(255)',
                    'label'           => '经销商名称',
                    'editable'        => false,
                    'is_title'        => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 60,
                    'width'           => 100,
                ),
                'shop_id' => array(
                    'type' => 'varchar(32)',
                    'label' => '店铺ID',
                ),
                'sku_nums' => array(
                    'type' => 'int unsigned',
                    'label' => '产品数',
                    'default' => '0',
                ),
                'material_bn'   => array(
                    'type'            => 'varchar(200)',
                    'label'           => '物料编码',
                    'editable'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                    // 'searchtype'      => 'nequal',
                    // 'filtertype'      => 'textarea',
                    'filterdefault'   => false,
                ),
                'material_name' => array(
                    'type'            => 'varchar(200)',
                    'label'           => '物料名称',
                    'is_title'        => false,
                    'default_in_list' => false,
                    // 'searchtype'      => 'has',
                    'editable'        => false,
                    // 'filtertype'      => 'normal',
                    'filterdefault'   => false,
                    'in_list'         => false,
                ),
                'at_time'     => array(
                    'type'            => 'TIMESTAMP',
                    'label'           => '创建时间',
                    'default'         => 'CURRENT_TIMESTAMP',
                    'width'           => 150,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 70,
                ),
                'up_time'     => array(
                    'type'            => 'TIMESTAMP',
                    'label'           => '更新时间',
                    'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'width'           => 150,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 80,
                ),
                'op_name'       => array(
                    'type'            => 'varchar(32)',
                    'label'           => '创建人',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 90,
                    'width'           => 80,
                ),
            ],
            'idColumn' => 'en_id',
        ];
        $schema['columns'] = array_merge($init['columns'], $schema['columns']);

        foreach ($schema['columns'] as $key => $value) {
            if ($value['default_in_list']) {
                $schema['default_in_list'][] = $key;
            }
            if ($value['in_list']) {
                $schema['in_list'][] = $key;
            }
        }
        return $schema;
    }

    /**
     * 查找er_filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回结果
     */
    public function finder_filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $baseWhere = (array) $baseWhere;

        $where = array();

        if ($filter['series_id']) {
            $where[] = 'e.series_id="' . $filter['series_id'] . '"';
        }
        unset($filter['series_id']);

        if ($filter['series_code']) {
            $where[] = 'e.series_id in (SELECT series_id FROM sdb_dealer_series WHERE series_code="' . $filter['series_code'] . '")';
        }
        unset($filter['series_code']);
        
        if ($filter['series_name']) {
            $where[] = 'e.series_id in (SELECT series_id FROM sdb_dealer_series WHERE series_name="' . $filter['series_name'] . '")';
        }
        unset($filter['series_name']);
        
        if ($filter['betc_name']) {
            $bsCodeArr = [];
            $betcMdl   = app::get('dealer')->model('betc');
            $betcInfo  = $betcMdl->db_dump(['betc_name' => $filter['betc_name']]);
            if ($betcInfo) {
                $cosList = kernel::single('organization_cos')->getCosList($betcInfo['cos_id']);
                if ($cosList[0] && is_array($cosList[1])) {
                    foreach ($cosList[1] as $k => $v) {
                        if ($v['cos_type'] == 'bs') {
                            $bsCodeArr[] = $v['cos_code'];
                        }
                    }
                }
            }
            $where[] = 'e.bs_id in (SELECT bs_id FROM sdb_dealer_business WHERE bs_bn in ("' . implode('","', $bsCodeArr) . '"))';
        }
        unset($filter['betc_name']);

        // if ($filter['material_bn']) {
        //     $filter['material_bn'] = explode("\n", $filter['material_bn']);
        //     $where[]               = 'p.bm_id in (SELECT bm_id FROM sdb_material_basic_material WHERE material_bn in ("' . implode('","', $filter['material_bn']) . '"))';
        // }
        // unset($filter['material_bn']);

        // if ($filter['material_name']) {
        //     $where[] = 'p.bm_id in (SELECT bm_id FROM sdb_material_basic_material WHERE material_name="' . $filter['material_name'] . '")';
        // }
        // unset($filter['material_name']);

        if ($filter['status']) {
            $where[] = 'e.series_id in (SELECT series_id FROM sdb_dealer_series WHERE status="' . $filter['status'] . '")';
        }
        unset($filter['status']);

        if ($filter['bs_bn']) {
            $where[] = 'e.bs_id in (SELECT bs_id FROM sdb_dealer_business WHERE bs_bn="' . $filter['bs_bn'] . '")';
        }
        unset($filter['bs_bn']);

        $sqlstr = '';

        if ($where) {
            $sqlstr .= implode(' AND ', $where) . " AND ";
        }

        return $sqlstr . parent::_filter($filter, $tableAlias, $baseWhere);
    }

    /**
     * 查找er_count
     * @param mixed $filter filter
     * @return mixed 返回结果
     */
    public function finder_count($filter = null)
    {
        $sql = "SELECT series_id FROM sdb_dealer_series_endorse as e WHERE " . $this->finder_filter($filter) . " GROUP BY series_id, bs_id ";
        $row = $this->db->select($sql);
        return intval(count($row));
    }
    
    public function finder_getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderType = null)
    {
        $cols      = 'e.series_id, e.bs_id, b.bs_bn, b.name as bs_name';
        $sql       = "SELECT $cols FROM `sdb_dealer_series_endorse` AS e LEFT JOIN sdb_dealer_business AS b ON e.bs_id=b.bs_id WHERE " . $this->finder_filter($filter) . " GROUP BY e.series_id, e.bs_id ";
        $orderType = $orderType ? $orderType : $this->defaultOrder[0];
        if ($orderType) {
            $orderType = $orderType;
            $sql .= ' ORDER BY ' . (is_array($orderType) ? implode(' ', $orderType) : $orderType);
        }
        $data = $this->db->selectLimit($sql, $limit, $offset);

        if (!$data) {
            return $data;
        }
        $seriesMdl = app::get('dealer')->model('series');
        $betcMdl   = app::get('dealer')->model('betc');

        $seriesIdArr = array_column($data, 'series_id');
        $seriesList  = $seriesMdl->getList('*', ['series_id' => $seriesIdArr]);
        $seriesList  = array_column($seriesList, null, 'series_id');

        $betcIdArr = array_unique(array_column($seriesList, 'betc_id'));
        $betcList  = $betcMdl->getList('betc_id,betc_code,betc_name', ['betc_id|in' => $betcIdArr]);
        $betcList  = array_column($betcList, null, 'betc_id');

        foreach ($data as $k => $v) {
            $data[$k]['en_id'] = $v['series_id'].'-'.$v['bs_id']; // 列表展示、关联基础物料展示会用到
            $betc_id = '';
            if (isset($seriesList[$v['series_id']]) && $seriesList[$v['series_id']]) {
                $data[$k]['series_code'] = $seriesList[$v['series_id']]['series_code'];
                $data[$k]['series_name'] = $seriesList[$v['series_id']]['series_name'];
                $data[$k]['status']      = $seriesList[$v['series_id']]['status'];
                $data[$k]['at_time']     = $seriesList[$v['series_id']]['at_time'];
                $data[$k]['up_time']     = $seriesList[$v['series_id']]['up_time'];
                $data[$k]['op_name']     = $seriesList[$v['series_id']]['op_name'];
                $betc_id = $seriesList[$v['series_id']]['betc_id'];
            }

            if ($betc_id && isset($betcList[$betc_id]) && $betcList[$betc_id]) {
                $data[$k]['betc_code'] = $betcList[$betc_id]['betc_code'];
                $data[$k]['betc_name'] = $betcList[$betc_id]['betc_name'];
            }
        }
        return $data;
    }

}
