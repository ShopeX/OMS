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
 * 产品线model类
 * @author wangjianjun@shopex.cn
 * @version 2024.04.12
 */
class dealer_mdl_series_endorse_products extends dbeav_model
{
    //列表排序
    public $defaultOrder = array('sep_id DESC');

    /**
     * 获取_schema
     * @return mixed 返回结果
     */

    public function get_schema()
    {
        $init   = parent::get_schema();
        $schema = [
            'columns'  => [
                'sep_id'           => [
                    'type'     => 'int unsigned',
                    'width'    => 110,
                    'hidden'   => true,
                    'editable' => false,
                    'pkey'     => true,
                ],
                'en_id'            => array(
                    'type'  => 'int unsigned',
                    'label' => '产品线授权到店ID',
                ),
                'material_bn'      => array(
                    'type'            => 'varchar(200)',
                    'label'           => '物料编码',
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'textarea',
                    'filterdefault'   => true,
                    'order'           => 10,
                    'width'           => 120,
                ),
                'material_name'    => array(
                    'type'            => 'varchar(200)',
                    'label'           => '物料名称',
                    'default_in_list' => true,
                    'searchtype'      => 'has',
                    'editable'        => false,
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => true,
                    'order'           => 20,
                    'width'           => 260,
                ),
                'series_code'      => array(
                    'type'            => 'varchar(30)',
                    'label'           => '产品线编码',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'order'           => 30,
                    'width'           => 110,
                ),
                'series_name'      => array(
                    'type'            => 'varchar(50)',
                    'label'           => '产品线名称',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'order'           => 40,
                    'width'           => 100,
                ),
                'betc_name'        => array(
                    'type'            => 'varchar(50)',
                    'label'           => '所属贸易公司',
                    'editable'        => false,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 50,
                    'width'           => 100,
                ),
                'shop_name'        => array(
                    'type'            => 'varchar(32)',
                    'label'           => '关联经销店铺',
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                ),
                'is_shopyjdf_type' => array(
                    'type'            => array(
                        1 => '自发货',
                        2 => '代发货',
                    ),
                    'default'         => '1',
                    'label'           => '发货方式',
                    'order'           => 110,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                ),
                'sale_status'    =>  array(
                    'type'           => array(
                        0 => '否',
                        1 => '是',
                    ),
                    'default'         => '1',
                    'label'           => '可售状态',
                    'order'           => 111,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'width'           => 80,
                ),
                'from_time'        => array(
                    'type'          => 'time',
                    'label'         => '开始时间',
                    'comment'       => '自发改成代发需要设置时间范围，代发变更自发设置开始时间',
                    'width'         => 130,
                    'editable'      => false,
                    'filtertype'    => 'time',
                    'filterdefault' => true,
                    'in_list'       => true,
                    'order'         => 120,
                ),
                'end_time'         => array(
                    'type'          => 'time',
                    'label'         => '结束时间',
                    'comment'       => '自发改成代发需要设置时间范围，代发变更自发设置开始时间',
                    'width'         => 130,
                    'editable'      => false,
                    'filtertype'    => 'time',
                    'filterdefault' => true,
                    'in_list'       => true,
                    'order'         => 130,
                ),
                'at_time'          => array(
                    'type'            => 'TIMESTAMP',
                    'label'           => '创建时间',
                    'default'         => 'CURRENT_TIMESTAMP',
                    'width'           => 150,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 140,
                ),
                'up_time'          => array(
                    'type'            => 'TIMESTAMP',
                    'label'           => '更新时间',
                    'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    'width'           => 150,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 150,
                ),
                'op_name'          => array(
                    'type'            => 'varchar(32)',
                    'label'           => '创建人',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 160,
                    'width'           => 80,
                ),
                'bm_id'            => array(
                    'type'  => 'int unsigned',
                    'label' => '物料ID',
                ),
                'shop_id'          => array(
                    'type'  => 'varchar(32)',
                    'label' => '店铺ID',
                ),
            ],
            'idColumn' => 'sep_id',
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

        if ($filter['series_code']) {
            $where[] = 's.series_code="' . $filter['series_code'] . '"';
        }
        unset($filter['series_code']);

        if ($filter['series_name']) {
            $where[] = 's.series_name="' . $filter['series_name'] . '"';
        }
        unset($filter['series_name']);

        if ($filter['betc_name']) {
            $where[] = 's.betc_id in (SELECT betc_id FROM sdb_dealer_betc WHERE betc_name="' . $filter['betc_name'] . '")';
        }
        unset($filter['betc_name']);

        if ($filter['material_bn']) {
            $filter['material_bn'] = explode("\n", $filter['material_bn']);
            $where[]               = 'p.bm_id in (SELECT bm_id FROM sdb_material_basic_material WHERE material_bn in ("' . implode('","', $filter['material_bn']) . '"))';
        }
        unset($filter['material_bn']);

        if ($filter['material_name']) {
            $where[] = 'p.bm_id in (SELECT bm_id FROM sdb_material_basic_material WHERE material_name="' . $filter['material_name'] . '")';
        }
        unset($filter['material_name']);

        if ($filter['status']) {
            $where[] = 's.status="' . $filter['status'] . '"';
        }
        unset($filter['status']);

        if (isset($filter['sale_status'])) {
            $where[] = 'p.sale_status="' . $filter['sale_status'] . '"';
            unset($filter['sale_status']);
        }

        if ($filter['is_shopyjdf_type']) {
            $where[] = 'p.is_shopyjdf_type=' . $filter['is_shopyjdf_type'];
        }
        unset($filter['is_shopyjdf_type']);

        if (isset($filter['shop_id'])) {
            $shopIds = (array) $filter['shop_id'];
            $where[] = 'p.shop_id in ("' . implode('","', $shopIds) . '")';
            unset($filter['shop_id']);
        }

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
        $row = $this->db->select("SELECT count(sep_id) as _count FROM sdb_dealer_series as s RIGHT JOIN sdb_dealer_series_endorse_products as p ON s.series_id=p.series_id WHERE " . $this->finder_filter($filter));
        return intval($row[0]['_count']);
    }

    public function finder_getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderType = null)
    {
        $cols = 's.series_id,s.series_code,s.series_name,s.status,s.betc_id,p.sep_id,p.shop_id,p.bm_id,p.is_shopyjdf_type,p.op_name,p.from_time,p.end_time,p.at_time,p.up_time,p.sale_status';
        $sql  = "SELECT $cols FROM sdb_dealer_series as s RIGHT JOIN sdb_dealer_series_endorse_products as p ON s.series_id=p.series_id WHERE " . $this->finder_filter($filter);

        $orderType = $orderType ? $orderType : $this->defaultOrder[0];
        if ($orderType) {
            $orderType = $orderType;
            $sql .= ' ORDER BY ' . (is_array($orderType) ? implode($orderType, ' ') : $orderType);
        }
        $data = $this->db->selectLimit($sql, $limit, $offset);

        if (!$data || !$data[0]['sep_id']) {
            return $data;
        }
        $basicMdl = app::get('material')->model('basic_material');
        $betcMdl  = app::get('dealer')->model('betc');
        $shopMdl  = app::get('ome')->model('shop');

        $bmIdArr = array_unique(array_column($data, 'bm_id'));
        $bmList  = $basicMdl->getList('bm_id,material_bn,material_name', ['bm_id|in' => $bmIdArr]);
        $bmList  = array_column($bmList, null, 'bm_id');

        $betcIdArr = array_unique(array_column($data, 'betc_id'));
        $betcList  = $betcMdl->getList('betc_id,betc_code,betc_name', ['betc_id|in' => $betcIdArr]);
        $betcList  = array_column($betcList, null, 'betc_id');

        $shopIdArr = array_unique(array_column($data, 'shop_id'));
        $shopList  = $shopMdl->getList('shop_id, name, shop_bn', ['shop_id|in' => $shopIdArr]);
        $shopList  = array_column($shopList, null, 'shop_id');

        foreach ($data as $k => $v) {
            if (isset($bmList[$v['bm_id']]) && $bmList[$v['bm_id']]) {
                $data[$k]['material_bn']   = $bmList[$v['bm_id']]['material_bn'];
                $data[$k]['material_name'] = $bmList[$v['bm_id']]['material_name'];
            }
            if (isset($betcList[$v['betc_id']]) && $betcList[$v['betc_id']]) {
                $data[$k]['betc_code'] = $betcList[$v['betc_id']]['betc_code'];
                $data[$k]['betc_name'] = $betcList[$v['betc_id']]['betc_name'];
            }
            if (isset($shopList[$v['shop_id']]) && $shopList[$v['shop_id']]) {
                $data[$k]['shop_bn']   = $shopList[$v['shop_id']]['shop_bn'];
                $data[$k]['shop_name'] = $shopList[$v['shop_id']]['name'];
            }
        }
        return $data;
    }

    /**
     * 保存SalesMaterial
     * @param mixed $sepIdArr ID
     * @return mixed 返回操作结果
     */
    public function saveSalesMaterial($sepIdArr = [])
    {
        if (!$sepIdArr) {
            return [false, 'sepIdArr is false'];
        }
        $list = $this->getList('*', ['sep_id|in' => $sepIdArr]);
        if (!$list) {
            return [false, 'list is null'];
        }

        $basicMaterialObj      = app::get('material')->model('basic_material');
        $salesMaterialObj      = app::get('dealer')->model('sales_material');
        $salesBasicMaterialObj = app::get('dealer')->model('sales_basic_material');
        $cosMdl                = app::get('organization')->model('cos');
        $shopMdl               = app::get('ome')->model('shop');

        $bmIdArr = array_unique(array_column($list, 'bm_id'));
        $bmList  = $basicMaterialObj->getList('bm_id,material_bn,material_name', ['bm_id|in' => $bmIdArr]);
        $bmList  = array_column($bmList, null, 'bm_id');

        $shopIdArr = array_unique(array_column($list, 'shop_id'));
        $shopList  = $shopMdl->getList('shop_id,shop_bn', ['shop_id|in' => $shopIdArr]);
        $shopList  = array_column($shopList, null, 'shop_id');

        $bmBnList   = array_column($bmList, 'material_bn');
        $smTempList = $salesMaterialObj->getList('sales_material_bn,shop_id', ['sales_material_bn|in' => $bmBnList, 'shop_id|in' => $shopIdArr]);
        $smList     = [];
        foreach ($smTempList as $tk => $tv) {
            $smList[] = $tv['shop_id'] . '|' . $tv['sales_material_bn'];
        }

        $shopBnList = array_column($shopList, 'shop_bn');
        $cosList    = $cosMdl->getList('cos_id,cos_code', ['cos_type' => 'shop', 'cos_code|in' => $shopBnList]);
        $cosList    = array_column($cosList, null, 'cos_code');

        $opInfo = kernel::single('ome_func')->getDesktopUser();
        foreach ($list as $k => $v) {

            $salesMbn   = $bmList[$v['bm_id']]['material_bn'];
            $salesMname = $bmList[$v['bm_id']]['material_name'];
            if (in_array($v['shop_id'] . "|" . $salesMbn, $smList)) {
                continue;
            }
            $shopBn = $shopList[$v['shop_id']]['shop_bn'];
            $cosId  = $cosList[$shopBn]['cos_id'];

            //保存物料主表信息
            $addData = array(
                'sales_material_bn'   => $salesMbn,
                'sales_material_name' => $salesMname,
                'sales_material_type' => 1,
                'is_bind'             => 1,
                'shop_id'             => $v['shop_id'],
                'cos_id'              => $cosId,
                'op_name'             => $opInfo['op_name'],
            );
            $is_save = $salesMaterialObj->save($addData);
            if (!$is_save) {
                continue;
            }

            $addBindData = array(
                'sm_id'  => $addData['sm_id'],
                'bm_id'  => $v['bm_id'],
                'number' => 1,
            );
            $salesBasicMaterialObj->insert($addBindData);
        }
        return [true, ''];
    }

}
