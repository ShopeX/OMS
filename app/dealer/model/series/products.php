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
class dealer_mdl_series_products extends dbeav_model
{

    var $has_export_cnf = false;
    var $export_name    = '产品线物料';

    //列表排序
    public $defaultOrder = array('sp_id DESC');

    /**
     * 获取_schema
     * @return mixed 返回结果
     */

    public function get_schema()
    {
        $init   = parent::get_schema();
        $schema = [
            'columns'  => [
                'sp_id'         => [
                    'type'     => 'int unsigned',
                    'width'    => 110,
                    'hidden'   => true,
                    'editable' => false,
                    'extra'    => 'auto_increment',
                    'required' => true,
                    'pkey'     => true,
                ],
                'series_id'     => array(
                    'type'  => 'int unsigned',
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
                'material_bn'   => array(
                    'type'            => 'varchar(200)',
                    'label'           => '物料编码',
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'nequal',
                    'filtertype'      => 'textarea',
                    'filterdefault'   => true,
                    'order'           => 50,
                    'width'           => 120,
                ),
                'material_name' => array(
                    'type'            => 'varchar(200)',
                    'label'           => '物料名称',
                    'is_title'        => true,
                    'default_in_list' => true,
                    'searchtype'      => 'has',
                    'editable'        => false,
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'in_list'         => true,
                    'order'           => 60,
                    'width'           => 260,
                ),
                'at_time'       => array(
                    'type'            => 'TIMESTAMP',
                    'label'           => '创建时间',
                    'default'         => 'CURRENT_TIMESTAMP',
                    'width'           => 150,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 70,
                ),
                'up_time'       => array(
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
            'idColumn' => 'sp_id',
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
            $where[] = 's.series_id="' . $filter['series_id'] . '"';

            if ($filter['bs_id']) {
                $enList = app::get('dealer')->model('series_endorse')->getList('en_id', ['series_id' => $filter['series_id'], 'bs_id' => $filter['bs_id']]);
                $enList = array_column($enList, 'en_id');
                $where[] = 'p.bm_id in (SELECT bm_id FROM sdb_dealer_series_endorse_products WHERE en_id in (' . implode(',', $enList) . '))';
            }
            unset($filter['bs_id']);
        }
        unset($filter['series_id']);

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
        $row = $this->db->select("SELECT count(sp_id) as _count FROM sdb_dealer_series as s RIGHT JOIN sdb_dealer_series_products as p ON s.series_id=p.series_id WHERE " . $this->finder_filter($filter));
        return intval($row[0]['_count']);
    }

    public function finder_getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderType = null)
    {
        $cols = 's.series_id,s.series_code,s.series_name,s.status,s.betc_id,p.sp_id,p.bm_id,p.op_name,p.at_time,p.up_time';
        $sql  = "SELECT $cols FROM sdb_dealer_series as s RIGHT JOIN sdb_dealer_series_products as p ON s.series_id=p.series_id WHERE " . $this->finder_filter($filter);

        $orderType = $orderType ? $orderType : $this->defaultOrder[0];
        if ($orderType) {
            $orderType = $orderType;
            $sql .= ' ORDER BY ' . (is_array($orderType) ? implode(' ', $orderType) : $orderType);
        }
        $data = $this->db->selectLimit($sql, $limit, $offset);

        if (!$data || !$data[0]['sp_id']) {
            return $data;
        }
        $basicMdl = app::get('material')->model('basic_material');
        $betcMdl  = app::get('dealer')->model('betc');

        $bmIdArr = array_unique(array_column($data, 'bm_id'));
        $bmList  = $basicMdl->getList('bm_id,material_bn,material_name', ['bm_id|in' => $bmIdArr]);
        $bmList  = array_column($bmList, null, 'bm_id');

        $betcIdArr = array_unique(array_column($data, 'betc_id'));
        $betcList  = $betcMdl->getList('betc_id,betc_code,betc_name', ['betc_id|in' => $betcIdArr]);
        $betcList  = array_column($betcList, null, 'betc_id');

        foreach ($data as $k => $v) {
            if (isset($bmList[$v['bm_id']]) && $bmList[$v['bm_id']]) {
                $data[$k]['material_bn']   = $bmList[$v['bm_id']]['material_bn'];
                $data[$k]['material_name'] = $bmList[$v['bm_id']]['material_name'];
            }
            if (isset($betcList[$v['betc_id']]) && $betcList[$v['betc_id']]) {
                $data[$k]['betc_code'] = $betcList[$v['betc_id']]['betc_code'];
                $data[$k]['betc_name'] = $betcList[$v['betc_id']]['betc_name'];
            }
        }
        return $data;
    }

    /**
     * exportTemplate
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function exportTemplate($filter = 'series_products')
    {
        foreach ($this->io_title($filter) as $v) {
            $title[] = $v;
        }
        return $title;
    }

    /**
     * io_title
     * @param mixed $filter filter
     * @param mixed $ioType ioType
     * @return mixed 返回值
     */
    public function io_title($filter = 'series_products', $ioType = 'csv')
    {
        switch ($filter) {
            case 'series_products':
                $this->oSchema['csv'][$filter] = [
                    '*:基础物料编码'    => 'material_bn',
                    '*:基础物料名称'    => 'material_name',
                ];
                break;
        }

        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema[$ioType][$filter]);
        return $this->ioTitle[$ioType][$filter];
    }

    /**
     * finish_import_csv
     * @return mixed 返回值
     */
    public function finish_import_csv()
    {
        set_time_limit(0);
        @ini_set('memory_limit','1024M');

        header("Content-type: text/html; charset=utf-8");
        
        $data = $this->import_data;
        if (!$data['contents']) {
            return true;
        }
        unset($this->import_data);
        
        $seriesMdl                = app::get('dealer')->model('series');
        $seriesProductsMdl        = app::get('dealer')->model('series_products');
        $seriesEndorseMdl         = app::get('dealer')->model('series_endorse');
        $seriesEndorseProductsMdl = app::get('dealer')->model('series_endorse_products');

        $filter = ['series_id'=>$data['contents'][0][1]];

        $seriesInfo   = $seriesMdl->db_dump($filter);
        $productsList = $seriesProductsMdl->getList('*', $filter);
        $endorseList  = $seriesEndorseMdl->getList('*', $filter);
        $sepList      = $seriesEndorseProductsMdl->getList('*', $filter);
        
        //日志快照
        $snapshoot = [
            'sdb_dealer_series'                   =>  $seriesInfo,
            'sdb_dealer_series_endorse'           =>  $endorseList,
            'sdb_dealer_series_products'          =>  $productsList,
            'sdb_dealer_series_endorse_products'  =>  $sepList,
        ];
        unset($productsList, $sepList);

        kernel::database()->beginTransaction();

        $opInfo   = kernel::single('ome_func')->getDesktopUser();
        foreach ($data['contents'] as $key => $row) {
            $insert = [
                'series_id' =>  $row[1], // 导入的是material_name, 不需要处理，在prepared_import_csv_row中处理成series_id
                'bm_id'     =>  $row[0], // 导入的是material_bn，在prepared_import_csv_row中处理成了bm_id
                'op_name'   =>  $opInfo['op_name'],
            ];
            if (!$this->insert($insert)) {
                $a = kernel::database()->errorinfo();
                kernel::database()->rollBack();
                return false;
            };

            // 插入产品授权到店表
            $sep_insert = [];
            foreach ($endorseList as $ek => $ev) {
                $sep_insert[] = [
                    'en_id'             =>  $ev['en_id'],
                    'series_id'         =>  $row[1],
                    'shop_id'           =>  $ev['shop_id'],
                    'bm_id'             =>  $row[0],
                    'is_shopyjdf_type'  =>  '1',
                    'status'            =>  '1',
                    'op_name'           =>  $opInfo['op_name'],
                ];
            }
            if ($sep_insert) {
                $sep_insert_sql = ome_func::get_insert_sql($seriesEndorseProductsMdl, $sep_insert);
                $res = $seriesEndorseProductsMdl->db->exec($sep_insert_sql);
                if (!$res) {
                    kernel::database()->rollBack();
                    return false;
                }
            }
        }
        
        $count = $seriesProductsMdl->count(['series_id' => $insert['series_id']]);
        $seriesMdl->update(['sku_nums' => $count], ['series_id' => $insert['series_id']]);

        $opLogMdl = app::get('ome')->model('operation_log');
        $log_id   = $opLogMdl->write_log('dealer_series_add@dealer',$insert['series_id'],"导入物料。");
        if ($log_id && $snapshoot) {
            $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
            $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
            $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
            $shootMdl->insert($tmp);
        }
        kernel::database()->commit();

        return true;
    }

    /**
     * prepared_import_csv_row
     * @param mixed $row row
     * @param mixed $title title
     * @param mixed $tmpl tmpl
     * @param mixed $mark mark
     * @param mixed $newObjFlag newObjFlag
     * @param mixed $msg msg
     * @return mixed 返回值
     */
    public function prepared_import_csv_row($row, $title, &$tmpl, &$mark, &$newObjFlag, &$msg)
    {
        $mark = 'contents';

        $fileData = $this->import_data;
        if (!$fileData) {
            $fileData = [];
        }
        // $series_id = $this->import_filter['sid'];
        $series_code = $this->import_filter['series_code'];

        if (substr($row[0], 0, 1) == '*') {
            $mark    = 'title';
            $titleRs = array_flip($row);
            return $titleRs;
        } else {
            if ($row) {

                $row[0] = trim($row[0]);
                if (empty($row[0])) {
                    unset($this->import_data);
                    $msg['error'] = "物料编码不能为空";
                    return false;
                }

                if (isset($this->import_data) && is_array($this->import_data['contents'])) {
                    $tmp_series_code_arr = array_column($this->import_data['contents'], 0);
                    if (in_array($row[0], $tmp_series_code_arr)) {
                        unset($this->import_data, $tmp_series_code_arr);
                        $msg['error'] = "物料编码【" . $row[0] . "】不能重复";
                        return false;
                    }
                }

                $seriesMdl  = app::get('dealer')->model('series');
                $seriesInfo = $seriesMdl->db_dump(['series_code' => $series_code]);
                if (!$seriesInfo) {
                    unset($this->import_data);
                    $msg['error'] =  "产品线编码【" . $series_code . "】不已存在";
                    return false;
                } else {
                    $row[1] = $seriesInfo['series_id'];
                }
                
                $basicMdl = app::get('material')->model('basic_material');
                $bmInfo = $basicMdl->db_dump(['material_bn'=>trim($row[0])]);
                if (!$bmInfo) {
                    unset($this->import_data);
                    $msg['error'] = "产品编码【" . $row[0] . "】不存在";
                    return false;
                } else {
                    $row[0] = $bmInfo['bm_id']; // 产品编码转成bm_id
                }
                
                $has = $this->db_dump(['series_id' => $row[1], 'bm_id' => $row[0]]);
                if ($has) {
                    unset($this->import_data);
                    $msg['error'] = "产品线【" . $series_code . "】的物料【" . $bmInfo['material_bn'] . "】已存在";
                    return false;
                }

                $fileData['contents'][] = $row;
            }
            $this->import_data = $fileData;
        }

        return true;
    }

    /**
     * prepared_import_csv_obj
     * @param mixed $data 数据
     * @param mixed $mark mark
     * @param mixed $tmpl tmpl
     * @param mixed $msg msg
     * @return mixed 返回值
     */
    public function prepared_import_csv_obj($data, $mark, $tmpl, &$msg = '')
    {
        return true;
    }

    /**
     * import_input
     * @return mixed 返回值
     */
    public function import_input()
    {
        $seriesMdl = app::get('dealer')->model('series');
        $info      = $seriesMdl->db_dump(['series_id' => $_GET['sid']]);
        
        $input[0]['label'] = '产品线编码';
        $input[0]['params'] = [
            'type'     =>[
                $info['series_code'] => $info['series_code'],
            ],
            'name'     =>'filter[series_code]',
            'required' => true,
            // 'onchange' => "javascript:if(this.value=='ar'){this.form.action='index.php?app=omecsv&ctl=admin_to_import&act=treat&ctler=finance_mdl_ar&add=finance'}else{this.form.action='index.php?app=omecsv&ctl=admin_to_import&act=treat&ctler=finance_mdl_bill&add=finance'}",
        ];
        $ui = kernel::single('base_component_ui');
        $input[0]['input'] = $ui->input($input[0]['params']);

        return $input;
    }

}
