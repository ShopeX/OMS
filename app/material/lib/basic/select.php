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
 * 基础物料_查询数据Lib类
 *
 * @version 1.0
 */

class material_basic_select
{
    public $schema    = array();

    function __construct()
    {
        $this->_basicMaterialObj = app::get('material')->model('basic_material');
        $this->schema    = $this->_basicMaterialObj->get_schema();

        $this->db    = kernel::database();
    }

    /**
     *
     * 获取主表数据[step 1]
     * @param String $cols
     * @param Array $filter
     * @param intval $offset
     * @param intval $limit
     * @param Array $orderby
     * @return Array
     */
    public function getlist($cols='*', $filter, $offset=0, $limit=-1, $orderby=null)
    {
        $sql_order    = '';
        if($orderby)  $sql_order = ' ORDER BY ' . (is_array($orderby) ? implode($orderby,' ') : $orderby);

        $cols    = $this->get_cols($cols);#格式化字段

        #sql
        $sql    = "SELECT ".$cols." FROM ".DB_PREFIX."material_basic_material WHERE ". $this->filter($filter). $sql_order;
        $data    = kernel::database()->selectlimit($sql, $limit, $offset);

        return $data;
    }

    public function dump($cols='*', $filter)
    {
        $cols    = $this->get_cols($cols);#格式化字段

        #sql
        $sql    = "SELECT ".$cols." FROM ".DB_PREFIX."material_basic_material WHERE ". $this->filter($filter);
        $data    = kernel::database()->selectrow($sql);

        return $data;
    }

    public function count($filter)
    {
        #sql
        $sql    = "SELECT count(*) AS num FROM ".DB_PREFIX."material_basic_material WHERE ". $this->filter($filter);
        $data   = kernel::database()->selectrow($sql);

        return $data['num'];
    }

    /*------------------------------------------------------ */
    //-- 格式化字段
    /*------------------------------------------------------ */
    public function get_cols($cols='*')
    {
        if(trim($cols) == '*')
        {
            $cols    = "bm_id, material_bn, material_name, visibled, type";
        }
        else
        {
            $field       = explode(',', $cols);
            $cols_tmp    = array();

            foreach ($field as $key => $k_val)
            {
                $k_val    = trim($k_val);

                if($this->schema['columns'][$k_val])
                {
                    $cols_tmp[]    = $k_val;
                }
            }
            $cols    = implode(',', $cols_tmp);
        }

        $cols    = str_replace('bm_id', 'bm_id AS product_id', $cols);
        $cols    = str_replace('material_bn', 'material_bn AS bn', $cols);
        $cols    = str_replace('material_name', 'material_name AS name', $cols);
        $cols    = str_replace('visibled', 'visibled AS visibility', $cols);

        return $cols;
    }

    /*------------------------------------------------------ */
    //-- 格式化查询条件
    /*------------------------------------------------------ */
    public function filter($filter, $tableAlias=null, $baseWhere=null)
    {
        $dbeav_filter_ret    = '1';

        if(is_array($filter) && !empty($filter))
        {
            foreach($filter AS $k => $v)
            {
                list($k_field, $k_type)    = explode('|', $k);
                if(!isset($this->schema['columns'][$k_field]))
                {
                    unset($filter[$k]); //todo: 过滤不存在于dbschema里的filter
                }
            }

            $dbeav_filter = kernel::single('dbeav_filter');
            $dbeav_filter_ret    = $dbeav_filter->dbeav_filter_parser($filter, $tableAlias, $baseWhere, $this->_basicMaterialObj);
        }

        return $dbeav_filter_ret;
    }

    /**
     *
     * 获取主表与扩展表数据[step 2]
     * @param String $cols
     * @param Array $filter
     * @param intval $offset
     * @param intval $limit
     * @param Array $orderby
     * @return Array $filter
     */
    public function getlist_ext($cols='*', $filter, $offset=0, $limit=-1, $orderby=null)
    {
        $mExtObj       = app::get('material')->model('basic_material_ext');
        $ext_schema    = $mExtObj->get_schema();

        $sql_order     = '';

        $cols    = $this->get_cols_ext($cols, $ext_schema);#格式化字段

        $where_sql    = $this->get_filter_ext($filter, $ext_schema);#查询条件

        #sql
        $sql    = "SELECT ".$cols." FROM ".DB_PREFIX."material_basic_material AS a
                   LEFT JOIN ".DB_PREFIX."material_basic_material_ext AS b ON a.bm_id=b.bm_id 
                   WHERE ".$where_sql . $sql_order;

        $data    = kernel::database()->selectlimit($sql, $limit, $offset);

        return $data;
    }

    public function dump_ext($cols='*', $filter)
    {
        $mExtObj       = app::get('material')->model('basic_material_ext');
        $ext_schema    = $mExtObj->get_schema();

        $cols    = $this->get_cols_ext($cols, $ext_schema);#格式化字段

        $where_sql    = $this->get_filter_ext($filter, $ext_schema);#查询条件

        #sql
        $sql    = "SELECT ".$cols." FROM ".DB_PREFIX."material_basic_material AS a
                   LEFT JOIN ".DB_PREFIX."material_basic_material_ext AS b ON a.bm_id=b.bm_id 
                   WHERE ".$where_sql;
        $data    = kernel::database()->selectrow($sql);
        return $data;
    }

    /*------------------------------------------------------ */
    //-- 格式化扩展字段
    /*------------------------------------------------------ */
    public function get_cols_ext($cols='*', $ext_schema)
    {
        if(trim($cols) == '*')
        {
            $cols    = "bm_id, material_bn, material_name, serial_number, visibled, type, retail_price, cost, weight, unit, specifications, brand_id, cat_id";
        }

        $field       = explode(',', $cols);
        $cols_tmp    = array();

        foreach ($field as $key => $k_val)
        {
            $k_val    = trim($k_val);

            if($this->schema['columns'][$k_val] && $k_val != 'cat_id')
            {
                $cols_tmp[]    = 'a.'.$k_val;
            }
            elseif($ext_schema['columns'][$k_val])
            {
                $cols_tmp[]    = 'b.'.$k_val;
            }
        }
        $cols    = implode(',', $cols_tmp);

        $cols    = str_replace('bm_id', 'bm_id AS product_id', $cols);
        $cols    = str_replace('material_bn', 'material_bn AS bn', $cols);
        $cols    = str_replace('material_name', 'material_name AS name', $cols);
        $cols    = str_replace('visibled', 'visibled AS visibility', $cols);
        $cols    = str_replace('retail_price', 'retail_price AS price', $cols);#销售价格

        return $cols;
    }

    /*------------------------------------------------------ */
    //-- 格式化查询扩展条件
    /*------------------------------------------------------ */
    public function get_filter_ext($filter, $ext_schema)
    {
        $where   = array('1');
        if(!empty($filter))
        {
            foreach ($filter as $key => $val)
            {
                list($k_field, $k_type)    = explode('|', $key);

                if($this->schema['columns'][$k_field])
                {
                    $where[]    = $this->getMultiFilter('a.' . $k_field, $k_type, $val);
                }
                elseif($ext_schema['columns'][$k_field])
                {
                    $where[]    = $this->getMultiFilter('b.' . $k_field, $k_type, $val);
                }
            }
        }
        $where_sql    = implode(" AND ", $where);

        return $where_sql;
    }

    /**
     *
     * 获取主表与库存表数据[step 3]
     * @param String $cols
     * @param Array $filter
     * @param intval $offset
     * @param intval $limit
     * @param Array $orderby
     * @return Array $filter
     */
    public function getlist_stock($cols='*', $filter, $offset=0, $limit=-1, $orderby=null)
    {
        $basicMaterialStockObj       = app::get('material')->model('basic_material_stock');
        $stock_schema    = $basicMaterialStockObj->get_schema();

        $sql_order     = '';#默认不支持排序

        $cols    = $this->get_cols_stock($cols, $stock_schema);#格式化字段

        $where_sql    = $this->get_filter_ext($filter, $stock_schema);#查询条件

        #sql
        $sql    = "SELECT ".$cols." FROM ".DB_PREFIX."material_basic_material AS a
                   LEFT JOIN ".DB_PREFIX."material_basic_material_stock AS b ON a.bm_id=b.bm_id 
                   WHERE ".$where_sql . $sql_order;
        $data    = kernel::database()->selectlimit($sql, $limit, $offset);

        return $data;
    }

    public function dump_stock($cols='*', $filter)
    {
        $mExtObj       = app::get('material')->model('basic_material_stock');
        $ext_schema    = $mExtObj->get_schema();

        $cols    = $this->get_cols_stock($cols, $ext_schema);#格式化字段

        $where_sql    = $this->get_filter_ext($filter, $ext_schema);#查询条件

        #sql
        $sql    = "SELECT ".$cols." FROM ".DB_PREFIX."material_basic_material AS a
                   LEFT JOIN ".DB_PREFIX."material_basic_material_stock AS b ON a.bm_id=b.bm_id 
                   WHERE ".$where_sql;
        $data    = kernel::database()->selectrow($sql);

        return $data;
    }

    /*------------------------------------------------------ */
    //-- 格式化库存字段
    /*------------------------------------------------------ */
    public function get_cols_stock($cols='*', $stock_schema)
    {
        if(trim($cols) == '*')
        {
            $cols    = "bm_id, material_bn, material_name, visibled, type, store, store_freeze,
                        alert_store, last_modified, real_store_lastmodify, max_store_lastmodify";
        }

        $field       = explode(',', $cols);
        $cols_tmp    = array();

        foreach ($field as $key => $k_val)
        {
            $k_val    = trim($k_val);

            if($this->schema['columns'][$k_val])
            {
                $cols_tmp[]    = 'a.'.$k_val;
            }
            elseif($stock_schema['columns'][$k_val])
            {
                $cols_tmp[]    = 'b.'.$k_val;
            }
        }
        $cols    = implode(',', $cols_tmp);

        $cols    = str_replace('bm_id', 'bm_id AS product_id', $cols);
        $cols    = str_replace('material_bn', 'material_bn AS bn', $cols);
        $cols    = str_replace('material_name', 'material_name AS name', $cols);
        $cols    = str_replace('visibled', 'visibled AS visibility', $cols);

        return $cols;
    }

    /**
     *
     * 统计
     * @param Array $filter
     * @return Array
     */
    public function countAnother($filter=null)
    {
        $strWhere    = '';

        $count    = ' count(*) ';
        if(isset($filter['product_group']))
        {
            $count    = ' COUNT(DISTINCT a.bm_id) ';
        }

        if(isset($filter['branch_id']))
        {
            if (is_array($filter['branch_id']))
            {
                $strWhere = ' AND b.branch_id IN ('.implode(',', $filter['branch_id']).') ';
            }
            else
            {
                $strWhere = ' AND b.branch_id = '.$filter['branch_id'];
            }
        }
        else
        {
            if ($filter['branch_ids'])
            {
                if (is_array($filter['branch_ids']))
                {
                    $strWhere = ' AND b.branch_id IN ('.implode(',', $filter['branch_ids']).') ';
                }
                else
                {
                    $strWhere = ' AND b.branch_id = '.$filter['branch_ids'];
                }
            }
        }

        $sql = 'SELECT count(*) AS num FROM '.DB_PREFIX.'material_basic_material AS a
                LEFT JOIN '.DB_PREFIX.'ome_branch_product AS b ON a.bm_id = b.product_id 
                WHERE 1 ' . $strWhere;

        $row = $this->db->selectrow($sql);

        return intval($row['num']);
    }

    /**
     *
     * 获取库存详情
     * @param intval $product_id
     * @return Array
     */
    function products_detail($product_id)
    {
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');
        $basicMStockFreezeLib   = kernel::single('material_basic_material_stock_freeze');

        #基础物料详情
        $pro    = $this->dump_stock('*', array('bm_id'=>$product_id));

        #基础物料对应库存详情
        $sql    = 'SELECT p.product_id,p.branch_id,p.arrive_store,p.store,p.store_freeze,p.safe_store,p.is_locked, bc.name as branch_name
                   FROM '.DB_PREFIX.'ome_branch_product as p 
                   LEFT JOIN '.DB_PREFIX.'ome_branch as bc ON bc.branch_id=p.branch_id 
                   WHERE p.product_id='.$product_id;
        $branch_product = $this->db->select($sql);

        foreach($branch_product as $key=>$val)
        {
            $pos_string ='';
            $posLists = $libBranchProductPos->get_pos($val['product_id'], $val['branch_id']);
            if(count($posLists) > 0){
                foreach($posLists as $pos){
                    $pos_string .= $pos['store_position'].",";
                }
                $branch_product[$key]['store_position'] = substr($pos_string,0,strlen($pos_string)-1);
            }
            
            //根据仓库ID、基础物料ID获取该物料仓库级的预占
            $branch_product[$key]['store_freeze']  = $basicMStockFreezeLib->getBranchFreeze($val['product_id'], $val['branch_id']);
            $branch_product[$key]['available_store'] = max(0, $val['store'] - $branch_product[$key]['store_freeze']);
        }

        $pro['branch_product'] = $branch_product;

        return $pro;
    }

    /**
     *
     * 模糊搜索
     * @param string $keywords
     * @return Array
     */
    function search_stockinfo($keywords, $branch_type, $limit = -1)
    {
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');

        ini_set('memory_limit','128M');

        if(empty($keywords))
        {
            return false;
        }

        $limit_sql    = '';
        if($limit > 0)
        {
            $limit_sql    = " limit ".$limit;
        }

        //增加是否是自有仓的库存查询，只显示自有仓储类型数据
        $whereStr = '';
        if($branch_type == 'selfwms'){
            $is_super = kernel::single('desktop_user')->is_super();
            $branch_ids = kernel::single('wms_branch')->getBranchwmsByUser($is_super);
            $branch_ids = $branch_ids ?: [0];
            $whereStr .= " AND b.branch_id in (".implode(',',$branch_ids).")";
        }

        $bm_ids = array();
        //按物料名称，编码查询
        $sql    = "SELECT a.bm_id FROM ".DB_PREFIX."material_basic_material AS a
                   WHERE a.visibled=1 AND (a.material_bn like '%". $keywords ."%' OR a.material_name like '%". $keywords ."%')";
        $result = $this->db->select($sql);
        foreach ($result as $key => $val)
        {
            $bm_ids[]    = $val['bm_id'];
        }

        //按条码查询
        $tmp_bm_id = $basicMaterialBarcode->getIdByBarcode($keywords);
        if($tmp_bm_id){
            $bm_ids[] = $tmp_bm_id;
        }

        if(empty($bm_ids))
        {
            return false;
        }

        $basicMStorageLifeLib    = kernel::single('material_storagelife');

        #库存
        $ids = implode(',', $bm_ids);
        $sql = "SELECT a.bm_id AS product_id, a.material_name AS name, a.material_bn AS bn, a.visibled AS visibility,
                bpt.store, b.name AS branch, b.branch_id 
                FROM ".DB_PREFIX."material_basic_material AS a
                LEFT JOIN ".DB_PREFIX."ome_branch_product AS bpt ON(a.bm_id=bpt.product_id) 
                LEFT JOIN ".DB_PREFIX."ome_branch AS b ON(bpt.branch_id=b.branch_id) 
                WHERE a.bm_id IN (".$ids.") " . $whereStr. $limit_sql;
        $product_info    = $this->db->select($sql);

        #货位
        $branch_product_posObj    = kernel::single('ome_branch_product_pos');
        foreach($product_info as $key => $val)
        {
            $pos_string  ='';
            $posLists    = $branch_product_posObj->get_pos($val['product_id'], $val['branch_id']);
            if(count($posLists) > 0)
            {
                foreach($posLists as $pos)
                {
                    $pos_string    .= $pos['store_position'].",";
                }

                $product_info[$key]['store_position'] = substr($pos_string, 0, strlen($pos_string)-1);
            }

            #查询关联的条形码
            $product_info[$key]['barcode']    = $basicMaterialBarcode->getBarcodeById($val['product_id']);

            #检查基础物料是否是保质期类型
            $get_material_conf       = $basicMStorageLifeLib->checkStorageLifeById($val['product_id']);
            if($get_material_conf)
            {
                $get_storage_life    = $basicMStorageLifeLib->getStorageLifeBatchList($val['product_id'], $val['branch_id']);
                if($get_storage_life)
                {
                    foreach ($get_storage_life as $key_j => $val)
                    {
                        $product_info[$key]['in_num']         += $val['in_num'];
                        $product_info[$key]['balance_num']    += $val['balance_num'];

                        #预警库存
                        if($val['warn_date'] <= time())
                        {
                            $product_info[$key]['warn_num']    += $val['balance_num'];
                        }
                    }
                }
            }
        }

        return $product_info;
    }

    /**
     *解析搜索类型
     *
     **/
    function getMultiFilter($col, $type, $var)
    {
        if(is_array($var) && ($type == '' || $type == 'nequal'))
        {
            $type    = 'in';
        }
        elseif($type == '')
        {
            $type    = 'nequal';
        }

        $FilterArray= array('than'=>' > '.$var,
            'lthan'=>' < '.$var,
            'nequal'=>' = \''.$var.'\'',
            'noequal'=>' <> \''.$var.'\'',
            'tequal'=>' = \''.$var.'\'',
            'sthan'=>' <= '.$var,
            'bthan'=>' >= '.$var,
            'has'=>' like \'%'.$var.'%\'',
            'head'=>' like \''.$var.'%\'',
            'foot'=>' like \'%'.$var.'\'',
            'nohas'=>' not like \'%'.$var.'%\'',
            'between'=>" {$col}>=".$var[0].' and '." {$col}<".$var[1],
            'in' =>" in ('".implode("','",(array)$var)."') ",
            'notin' =>" not in ('".implode("','",(array)$var)."') ",
        );

        return $type == 'between' ? $FilterArray[$type] : $col . $FilterArray[$type];
    }
}
