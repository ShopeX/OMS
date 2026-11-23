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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class base_db_model implements base_interface_model
{
    public $filter_use_like = false;
    /** @var app */
    public $app;
    public $db;
    public function __construct($app='')
    {
        $this->app = $app;
        $this->db  = kernel::database();

        //todo check 是否有意义?
        $this->schema           = $this->get_schema();
        $this->metaColumn       = $this->schema['metaColumn'];
        $this->idColumn         = $this->schema['idColumn'];
        $this->textColumn       = $this->schema['textColumn'];
        $this->skipModifiedMark = ($this->schema['ignore_cache'] === true) ? true : false;
        if (!is_array($this->idColumn) && is_array($this->schema['columns'][$this->idColumn]) && array_key_exists('extra', $this->schema['columns'][$this->idColumn])) {
            $this->idColumnExtra = $this->schema['columns'][$this->idColumn]['extra'];
        }
        //end check
    }

    public function table_name($real = false)
    {
        $class_name = get_class($this);
        $table_name = substr($class_name, 5 + strpos($class_name, '_mdl_'));
        if ($real) {
            return kernel::database()->prefix . $this->app->app_id . '_' . $table_name;
        } else {
            return $table_name;
        }
    }

    public function getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderby = null)
    {
        if ($orderby) {
            $sql_order = ' ORDER BY ' . (is_array($orderby) ? implode($orderby, ' ') : $orderby);
        }

        $rows = $this->db->selectLimit('select ' . $cols . ' from `' . $this->table_name(1)
            . '` where ' . $this->filter($filter) . $sql_order, $limit, $offset);
        $this->tidy_data($rows, $cols);
        return $rows;
    }

    public function tidy_data(&$rows, $cols = '*')
    {
        if ($rows) {
            $need_tidy   = false;
            $tidy_type   = array('serialize');
            $def_columns = $this->_columns();
            if (rtrim($cols) === '*') {
                $columns = $def_columns;
            } else {
                $tmp = explode(',', $cols);
                foreach ($tmp as $col) {
                    $col = trim($col);
                    if (preg_match('/\S+ as \S+/i', $col)) {
                        $array            = preg_split('/ as /i', $col);
                        $ex_key           = str_replace('`', '', trim($array[1]));
                        $ex_real          = str_replace('`', '', trim($array[0]));
                        $columns[$ex_key] = $def_columns[$ex_real];
                    } else {
                        $ex_key           = str_replace('`', '', $col);
                        $columns[$ex_key] = $def_columns[$ex_key];
                    }
                }
            }
            $curRow = current($rows);
            foreach ($columns as $k => $v) {
                if (in_array($v['type'], $tidy_type) && array_key_exists($k, $curRow)) {
                    $need_columns[] = $k;
                    $need_tidy      = true;
                }
            }
            if ($need_tidy) {
                foreach ($rows as $key => $row) {
                    foreach ($need_columns as $column) {
                        switch (trim($columns[$column]['type'])) {
                            case 'serialize':
                                $rows[$key][$column] = unserialize($row[$column]);
                            default:
                        }
                    }
                }
            }
        }
    } //End Function

    public function filter($filter)
    {
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if (!isset($this->schema['columns'][$k])) {
                    unset($filter[$k]);
                }
                //todo: 过滤不存在于dbschema里的filter
            }
        }
        return base_db_tools::filter2sql($filter);
    }

    public function count($filter = null)
    {
        $row = $this->db->select('SELECT count(*) as _count FROM `' . $this->table_name(1) . '` WHERE ' . $this->filter($filter));
        return intval($row[0]['_count']);
    }

    public function viewcount($filter = null)
    {
        $tab_style = app::get('ome')->getConf('desktop.finder.tab');

        if ($tab_style != '1') {
            $exprie_time = app::get('ome')->getConf('desktop.finder.tab.count.expire');
            $exprie_time = $exprie_time ? $exprie_time * 60 : 5 * 60;

            if (false && $exprie_time > 0 && function_exists('init_domain')) {
                // 再做一层数据缓存
                $cacheObj = kernel::single('taoexlib_params_cache');

                $key = base_certificate::assemble($filter);
                $key = sprintf("%u", crc32($key));
                if ($cacheObj->fetch($key, $count) === false) {
                    $count = $this->count($filter);

                    $cacheObj->store($key, $count, $exprie_time);
                }

            } else {
                $encode_filter = $filter ? urlencode(http_build_query($filter)) : '';
                $class_name = get_class($this);
                $vcid = sprintf('%u', crc32($class_name.$encode_filter));
                $viewaddon = '<viewconut id="'.$vcid.'">loading...';
                $viewaddon .= "<script>
                    new Request.JSON({
                        url: 'index.php?app=desktop&ctl=default&act=getviewcount',
                        data: {
                            mdl:'{$class_name}',
                            filter:'{$encode_filter}'
                        },
                        onSuccess: function(json) {
                            if(json.count) {
                                $('{$vcid}').setHTML(json.count);
                            } else {
                                $('{$vcid}').getParent('LI').dispose();
                            }
                        }
                    }).send();
                </script>";
                $viewaddon .= '</viewconut>';
                return $viewaddon;
            }

            return $count;
        } else {
            return 'showtab';
            /*
        $sql = 'SELECT ' . $this->idColumn . ' FROM `' . $this->table_name(1) . '` ';
        if (method_exists($this,'_filter')) {
        $sql .= ' WHERE ' . $this->_filter($filter);
        } else {
        $sql .= ' WHERE ' . $this->filter($filter);
        }
        $is_exist = $this->db->selectrow($sql);
        return $is_exist ? 'showtab' : 0;
         */
        }
    }

    public function get_schema()
    {
        $table = $this->table_name();
        if (!isset($this->__exists_schema[$this->app->app_id][$table])) {
            if (!isset($this->table_define)) {
                $this->table_define = new base_application_dbtable;
            }
            $this->__exists_schema[$this->app->app_id][$table] = $this->table_define->detect($this->app, $table)->load(false);
        }
        return $this->__exists_schema[$this->app->app_id][$table];
    }

    public function _columns()
    {
        $schema = new base_application_dbtable;
        $dbinfo = $schema->detect($this->app, $this->table_name())->load();
        return (array) $dbinfo['columns'];
    }
    public function searchOptions()
    {
        $columns = array();
        foreach ($this->_columns() as $k => $v) {
            if (isset($v['searchtype']) && $v['searchtype']) {
                $columns[$k] = $v['label'];
            }
        }
        return $columns;
    }

    public function replace($data, $filter)
    {
        $where = base_db_tools::filter2sql($filter);
        $rs    = $this->db->exec('select * from `' . $this->table_name(1) . '` where ' . $where);
        $sql   = base_db_tools::getupdatesql($rs, $data, 1);
        return !$sql || $this->db->exec($sql, $this->skipModifiedMark);
    }

    public function insert(&$data)
    {
        $cols         = $this->_columns();
        $insertValues = array();

        $rs        = $this->db->exec('select * from `' . $this->table_name(1) . '` where 0=1');
        $col_count = mysql_num_fields($rs['rs']);
        for ($i = 0; $i < $col_count; $i++) {
            $column = mysql_fetch_field($rs['rs'], $i);
            $k      = $column->name;
            $p      = $cols[$column->name];

            if (!isset($p['default']) && $p['required'] && $p['extra'] != 'auto_increment') {
                if (!isset($data[$k])) {
                    trigger_error(($p['label'] ? $p['label'] : $k) . app::get('base')->_('不能为空！'), E_USER_ERROR);
                }
            }
            if ($p['extra'] == 'auto_increment') {
                unset($data[$k]);
            }
            if ($data[$k] !== false) {
                if ($p['type'] == 'last_modify') {
                    $insertValues[$k] = time();
                } elseif ($p['depend_col']) {
                    $dependColVal = explode(':', $p['depend_col']);
                    if ($data[$dependColVal[0]] == $dependColVal[1]) {
                        switch ($dependColVal[2]) {
                            case 'now':
                                $insertValues[$k] = time();
                                break;
                        }
                    }
                }
            }

            if (isset($data[$k])) {
                if (!isset($data[$k]) && $p['required'] && isset($p['default'])) {
                    $data[$k] = $p['default'];
                }
                if ($p['type'] == 'money') {
                    if ($data[$k] === '') {
                        $data[$k] = null;
                    }
                }
                $column->type = $p['type'];
                if ($data[$k] == '' && isset($p['default'])) {
                    $data[$k] = $p['default'];
                }
                $needInsert = true;
                if(null === $data[$k] && strpos($column->type, 'char') !== false){
                    $needInsert = false;
                }
                if($needInsert) {
                    $insertValues[$k] = base_db_tools::quotevalue($this->db, $data[$k], $column->type);
                }
            }
        }

        $strValue  = implode(',', $insertValues);
        $strFields = implode('`,`', array_keys($insertValues));
        $sql       = 'INSERT INTO `' . $this->table_name(true) . '` ( `' . $strFields . '` ) VALUES ( ' . $strValue . ' )';
        try {
            if ($sql && $this->db->exec($sql, $this->skipModifiedMark)) {
                $insert_id = $this->db->lastinsertid();
                if ($this->idColumnExtra == 'auto_increment' && $insert_id) {
                    $data[$this->idColumn] = $insert_id;
                } else {
                    if (is_array($this->idColumn)) {
                        return true;
                    } else {
                        $insert_id = $data[$this->idColumn];
                    }
                }
                return $insert_id;
            } else {
                return false;
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) { // MySQL错误代码1062对应的就是主键或唯一键冲突
                return false;
            } else {
                // 其他错误处理
                throw $e;
            }
        }
    }

    public function update($data, $filter = array(), $mustUpdate = null)
    {
        if (count((array) $data) == 0) {
            return true;
        }

        $UpdateValues = array();
        $colKeys = '`'.implode('`|`', array_keys($this->_columns())).'`';
        foreach ($this->_columns() as $k => $v) {
            if (!empty($mustUpdate)) {
                if (!array_key_exists($k, $mustUpdate)) {
                    continue;
                } else {
                    unset($mustUpdate[$k]);
                }

            }
            if (array_key_exists($k, $data)) {
                $UpdateValues[] = '`' . $k . '`= ' . base_db_tools::quotevalue($this->db, $data[$k], $v['type']);
            }
            if (array_key_exists($k.'_upset_sql', (array)$data) && $data[$k.'_upset_sql'] && !preg_replace("/$colKeys|IF|IFNULL|IS|NULL|SUM|COUNT|AND|OR|\+|-|\d|\s|\(|\)|,|<|>|=|UNIX_TIMESTAMP/", "", $data[$k.'_upset_sql'])){
                $UpdateValues[] = '`' . $k . '`= ' . $data[$k.'_upset_sql'];
                continue;
            }
            if ($data[$k] !== false) {
                if ($v['type'] == 'last_modify') {
                    $UpdateValues[] = '`' . $k . '` = ' . time() . ' ';
                    //     $data[$k] = time();
                } elseif ($v['depend_col']) {
                    $dependColVal = explode(':', $v['depend_col']);
                    if ($data[$dependColVal[0]] == $dependColVal[1]) {
                        switch ($dependColVal[2]) {
                            case 'now':
                                $UpdateValues[] = '`' . $k . '` = ' . time() . ' ';
//                                $data[$k] = time();
                                break;
                        }
                    }
                }
            }

        }
        if (!empty($mustUpdate)) {
            foreach ($mustUpdate as $mpk => $mpv) {
                $UpdateValues[] = '`' . $mpk . '`= NULL';
            }
        }

        if (count($UpdateValues) > 0) {
            $sql = 'update `' . $this->table_name(1) . '` set ' . implode(',', $UpdateValues) . ' where ' . (is_array($filter) ? base_db_tools::filter2sql($filter) : $filter);
            if ($this->db->exec($sql, $this->skipModifiedMark)) {
                if ($rs = $this->db->affect_row()) {
                    return $rs;
                } else {
                    return true;
                }
            } else {
                return false;
            }
        }
    }

    /**
     * delete
     *
     * 根据条件删除条目
     * 不可以由pipe控制
     * 可以广播事件
     *
     * @param mixed $filter
     * @param mixed $named_action
     * @access public
     * @return void
     */
    public function delete($filter)
    {
        $sql = 'DELETE FROM `' . $this->table_name(1) . '` where ' . (is_array($filter) ? $this->filter($filter) : $filter);
        if ($this->db->exec($sql, $this->skipModifiedMark)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * fireEvent 触发事件
     *
     * @param mixed $event
     * @access public
     * @return void
     */
    public function fireEvent($action, &$object, $member_id = 0)
    {
//todo:somebody
    }

    /*
     *对数据库结构数据save
     *$dbData db结构
     *$mustUpdate db结构
     */
    final public function db_save(&$dbData, $mustUpdate = null)
    {
        $doMethod = 'update';
        $filter   = array();

        foreach ((array) $this->idColumn as $idv) {
            if (!$dbData[$idv]) {
                $doMethod = 'insert';
                break;
            }
            $filter[$idv] = $dbData[$idv];
        }

        $where = array();
        if ($filter) {
            foreach ($filter as $k => $v) {
                // 查询$v里是否带有"，如果存在抛出异常
                if (strpos($v, '"') !== false) {
                    throw new Exception('【'. $this->schema['comment'].'】存在主键异常数据【'.addslashes($v).'】，请重新提交');
                }

                $where[] = $k . ' = "' . $v . '"';
            }
        }

        if ($doMethod == 'update' && $this->db->selectrow('SELECT ' . implode(',', (array) $this->idColumn) . ' FROM `' . $this->table_name(true) . '` WHERE ' . implode(' AND ', $where))) {
            return $this->update($dbData, $filter, $mustUpdate);
        }

        return $this->insert($dbData);
    }

    public function save(&$dbData, $mustUpdate = null)
    {
        return $this->db_save($dbData, $mustUpdate);
    }

    final public function db_dump($filter, $field = '*')
    {
        if (!isset($filter)) {
            return null;
        }

        if (!is_array($filter)) {
            $filter = array($this->idColumn => $filter);
        }

        $tmp = $this->getList($field, $filter, 0, 1);
        reset($tmp);
        $data = current($tmp);
        return $data;
    }

    public function dump($filter, $field = '*')
    {
        return $this->db_dump($filter, $field);
    }

}
