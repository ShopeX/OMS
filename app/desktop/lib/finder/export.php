<?php
/**
 * 标准finder列表数据导出处理类
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */
define('COLUMN_IN_HEAD', 'HEAD');
define('COLUMN_IN_TAIL', 'TAIL');
class desktop_finder_export
{

    /**
     * 导出取数据入口方法
     *
     * @param string $full_object_name 导出对象model
     * @param array $params 导出所需的参数
     * @return array $data
     */
    public function work($full_object_name, $params)
    {
        //初始化当前对象属性
        $this->init($full_object_name);

        //当前导出要求的导出字段及过滤条件
        $this->columns     = explode(',', $params['fields']);
        $this->filter      = $params['filter'];
        $this->has_detail  = $params['has_detail'] == 1 ? true : false;
        $this->first_sheet = $params['curr_sheet'] == 1 ? true : false;
        $this->op_id       = $params['op_id'];

        return $this->main();
    }

    /**
     * 获取导出对象相应的导出字段
     *
     * @param string $full_object_name 导出对象model
     * @return array $return 导出的可使用字段
     */
    public function get_all_columns($full_object_name)
    {
        //初始化当前对象属性
        $this->init($full_object_name);

        return $this->all_columns();
    }

    /**
     * 根据引入的导出对象，初始化相关字段参数等
     *
     * @param string $full_object_name 导出对象model
     * @return null
     */
    private function init($full_object_name)
    {
        $this->object_name = $full_object_name;

        if ($p = strpos($full_object_name, '_mdl_')) {
            $object_app  = substr($full_object_name, 0, $p);
            $object_name = substr($full_object_name, $p + 5);
        } else {
            trigger_error('finder only accept full model name: ' . $full_object_name, E_USER_ERROR);
        }

        $service_list = array();
        foreach (kernel::servicelist('desktop_finder.' . $this->object_name) as $name => $object) {
            $service_list[$name] = $object;
        }

        foreach ($service_list as $name => $object) {
            $tmpobj = $object;
            foreach (get_class_methods($tmpobj) as $method) {
                switch (substr($method, 0, 7)) {
                    case 'column_':
                        $this->addon_columns[] = array(&$tmpobj, $method);
                        break;
                }
            }

            $this->service_object[] = &$tmpobj;
            unset($tmpobj);
        }

        $this->object   = app::get($object_app)->model($object_name);
        $this->object->is_export_data = true;
        $this->dbschema = $this->object->schema;

        if (method_exists($this->object, 'extra_cols')) {
            $this->extra_cols = $this->object->extra_cols();
        }

        //增加导出时候要额外扩展导出的字段
        if (method_exists($this->object, 'export_extra_cols')) {
            $this->export_extra_cols = $this->object->export_extra_cols();
        }

        $this->short_object_name = substr($this->object_name, strpos($this->object_name, '_mdl_') + 5);
    }

    /**
     * finder数据处理的核心方法
     *
     * @param null
     * @return array $export_data 导出的格式化数据
     */
    private function main()
    {
        $allCols = $this->all_columns();

        $modifiers       = array();
        $type_modifier   = array();
        $key_modifier    = array();
        $object_modifier = array();
        $modifier_object = new modifiers;

        //根据当前字段格式化查询语句
        foreach ($this->columns as $col) {
            if (isset($allCols[$col])) {
                $colArray[$col] = &$allCols[$col];
                if (method_exists($this->object, 'modifier_' . $col)) {
                    $key_modifier[$col] = 'modifier_' . $col;
                } elseif (is_string($colArray[$col]['type'])) {
                    if (substr($colArray[$col]['type'], 0, 6) == 'table:') {
                        $object_modifier[$colArray[$col]['type']] = array();
                    } elseif (method_exists($modifier_object, $colArray[$col]['type'])) {
                        $type_modifier[$colArray[$col]['type']] = array();
                    }
                }
                if (isset($col_width_set[$col])) {
                    $colArray[$col]['width'] = $col_width_set[$col];
                }

                if (isset($allCols[$col]['sql'])) {
                    $sql[] = $allCols[$col]['sql'] . ' as ' . $col;
                } elseif ($col == '_tag_') {
                    $sql[] = $dbschema['idColumn'] . ' as _tag_';
                } elseif (isset($this->extra_cols[$col])) {
                    $sql[] = '1 as ' . $col;
                } elseif (isset($this->export_extra_cols[$col])) {
                    $sql[] = '1 as ' . $col;
                } else {
                    $sql[] = '`' . $col . '`';
                }
            }
        }

        foreach ((array) $this->service_object as $k => $object) {
            if ($object->addon_cols) {
                $object->col_prefix = '_' . $k . '_';
                foreach (explode(',', $object->addon_cols) as $col) {
                    $sql[] = $col . ' as ' . $object->col_prefix . $col;
                }
            }
        }
        $sql = (array) $sql;
        if (!isset($colArray[$this->dbschema['idColumn']])) {
            array_unshift($sql, $this->dbschema['idColumn']);
        }

        $list = $this->object->getlist(implode(',', $sql), $this->filter, 0, -1);

        if (is_array($this->extra_cols) && count($this->extra_cols) > 0) {
            foreach ($this->extra_cols as $ek => $extra_col) {
                $extra_col_method = '';
                if (method_exists($this->object, 'extra_' . $extra_col['func_suffix'])) {
                    $extra_col_method = 'extra_' . $extra_col['func_suffix'];
                    $list             = $this->object->$extra_col_method($list);
                }
            }
        }

        //导出时候特定额外要导出的字段
        if (is_array($this->export_extra_cols) && count($this->export_extra_cols) > 0) {
            foreach ($this->export_extra_cols as $ek => $export_extra_col) {
                $export_extra_col_method = '';
                if (method_exists($this->object, 'export_extra_' . $export_extra_col['func_suffix'])) {
                    $export_extra_col_method = 'export_extra_' . $export_extra_col['func_suffix'];
                    $list                    = $this->object->$export_extra_col_method($list);
                }
            }
        }

        //导出数据客户敏感信息处理
        $securityLib = kernel::single('ome_security_customer');
        $securityLib->check_sensitive_info($list, $this->object_name, $this->op_id);

        // 导出明细
        $isV2 = false;
        if ($this->has_detail && method_exists($this->object, 'getExportDetailV2')) {
            list($listV2, $colArrayV2) = $this->object->getExportDetailV2($list, $colArray);

            if ($listV2) {
                $list = $listV2;
            }

            if ($colArrayV2) {
                $colArray = $colArrayV2;
            }
            $isV2 = true;
        }

        //导出主明细数据
        $export_data['content']['main'] = $this->item_list_body($list, $colArray, $key_modifier, $object_modifier, $type_modifier, $isV2);

        //取明细数据内容
        if (method_exists($this->object, 'getexportdetail') && $this->has_detail) {
            $export_data['content']['pair'] = $this->object->getexportdetail('*', $this->filter, 0, -1, $this->first_sheet);
        }

        //取唯一码平铺明细（如调整单 snlists）
        if (method_exists($this->object, 'getexportsnlists') && $this->has_detail) {
            $export_data['content']['snlists'] = $this->object->getexportsnlists('*', $this->filter, 0, -1, $this->first_sheet);
        }

        //返回数据
        return $export_data;
    }

    /**
     * finder列表原始数据格式化方法
     *
     * @param array $list 查询出来的原始数据
     * @param array $colArray 导出数据的字段名数组
     * @param array $key_modifier 字段在model中有定义modifier_字段的处理方法的数据
     * @param array $object_modifier 对象字段数据，比如id字段是其它表主键
     * @param array $type_modifier 指定字段类型数据，比如money等
     * @return null
     */
    public function &item_list_body(&$list, &$colArray, &$key_modifier, &$object_modifier, &$type_modifier, $isV2 = false)
    {
        
        // 把list中有value是_ISNULL_的行过滤掉
        $_list = $list;
        if($isV2){
            foreach($_list as $k => $v){
                if(in_array('_ISNULL_',$v)){
                    unset($_list[$k]);
                }
            }
        }
        $body     = array();
        $curr_row = 1;
        foreach ($list as $i => $row) {
            foreach ((array) $colArray as $k => $col) {
                //如果是第一分片在第一行之前导出标题
                if ($this->first_sheet && $curr_row == 1) {
                    $body[0][] = $col['label'];
                }

                if ($isV2 && $row[$k] == '_ISNULL_') {
                    $body[$curr_row][] = '';
                } elseif ($col['type'] == 'func') {
                    $row['idColumn']   = $this->dbschema['idColumn'];
                    $row['app_id']     = $row['app_id'] ? $row['app_id'] : $this->app->app_id;
                    $row['tag_type']   = $row['tag_type'] ? $row['tag_type'] : $this->short_object_name;
                    $body[$curr_row][] = $a = $col['ref'][0]->{$col['ref'][1]}($row, $_list);
                } elseif (isset($key_modifier[$k])) {
                    $this->object->pkvalue = $row[$this->dbschema['idColumn']];
                    $body[$curr_row][]     = $this->object->{$key_modifier[$k]}($row[$k], $_list, $row);
                } elseif (is_array($col['type']) && !is_null($row[$k])) {
                    $body[$curr_row][] = &$col['type'][$row[$k]];
                } elseif (!is_array($col['type']) && isset($object_modifier[$col['type']])) {
                    $object_modifier[$col['type']][$row[$k]] = $row[$k];
                    $body[$curr_row][]                       = &$object_modifier[$col['type']][$row[$k]];
                } elseif (!is_array($col['type']) && isset($type_modifier[$col['type']])) {
                    if (is_float($row[$k])) {
                        $number = md5($row[$k]);
                    } else {
                        $number = $row[$k];
                    }
                    $type_modifier[$col['type']][$number] = $row[$k];
                    $body[$curr_row][]                    = &$type_modifier[$col['type']][$number];
                } else {
                    $body[$curr_row][] = $row[$k];
                }
            }
            $curr_row++;
        }

        if ($type_modifier) {
            $type_modifier_object = new modifiers;
            foreach ($type_modifier as $type => $val) {
                if ($val) {
                    $type_modifier_object->$type($val);

                    if ($type == 'money') {
                        foreach ($val as $i => $money) {
                            $val[$i] = str_replace(',', '', $money);
                        }
                    }
                }
            }
        }

        foreach ($object_modifier as $target => $val) {
            if ($val) {
                list(, $obj_name, $fkey) = explode(':', $target);
                if ($p = strpos($obj_name, '@')) {
                    $app_id   = substr($obj_name, $p + 1);
                    $obj_name = substr($obj_name, 0, $p);
                    $o        = app::get($app_id)->model($obj_name);
                } else {
                    $o = $this->object->app->model($obj_name);
                }
                if (!$fkey) {
                    $fkey = $o->textColumn;
                }

                $rows = $o->getList($o->idColumn . ',' . $fkey, array($o->idColumn => $val));
                foreach ($rows as $r) {
                    $object_modifier[$target][$r[$o->idColumn]] = $r[$fkey];
                }
                $app_id = null;
            }
        }

        //error_log(var_export($body,true),3,'/www/be.log');

        $export_arr = array();
        foreach ($body as $row => $content) {
            //error_log(var_export($content,true),3,'/www/be.log');
            $tmp_arr = array();
            foreach ($content as $value) {
                //过滤html编码转换
                $value     = str_replace('&nbsp;', '', $value);
                $value     = str_replace(array("\r\n", "\r", "\n"), '', $value);
                $value     = str_replace(',', '，', $value);
                $value     = strip_tags(html_entity_decode($value, ENT_COMPAT | ENT_QUOTES, 'UTF-8'));
                $value     = trim($value);
                $tmp_arr[] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }
            //error_log(var_export($tmp_arr,true)."\n\t",3,'/www/af.log');
            //去html代码
            $export_arr[] = implode(',', $tmp_arr);
        } //echo"<pre>";var_dump($export_arr);exit;
        return $export_arr;
    }

    //获取所有字段
    private function &all_columns()
    {
        //finder扩展字段
        $func_columns = $this->func_columns();

        //新方式扩展字段
        $extra_columns = array();
        if (is_array($this->extra_cols) && count($this->extra_cols) > 0) {
            $extra_columns = $this->extra_cols;
        }

        //额外导出扩展字段
        $export_extra_columns = array();
        if (is_array($this->export_extra_cols) && count($this->export_extra_cols) > 0) {
            $export_extra_columns = $this->export_extra_cols;
        }

        //表结构原声字段
        $columns = array();
        foreach ((array) $this->dbschema['in_list'] as $key) {
            $columns[$key] = &$this->dbschema['columns'][$key];
        }

        //合并所有字段
        $return = array_merge((array) $func_columns, (array) $extra_columns, (array) $export_extra_columns, (array) $columns);
        foreach ($return as $k => $r) {
            if (!$r['order']) {
                $return[$k]['order'] = 100;

            }
            $orders[] = $return[$k]['order'];
        }
        array_multisort($orders, SORT_ASC, $return);
        return $return;
    }

    //取finder里定义的扩展字段
    private function &func_columns()
    {
        if (!isset($this->func_list)) {
            $default_with    = app::get('desktop')->getConf('finder.thead.default.width');
            $return          = array();
            $this->func_list = &$return;

            foreach ($this->addon_columns as $k => $function) {
                $func['type']  = 'func';
                $func['width'] = $function[0]->{$function[1] . '_width'} ? $function[0]->{$function[1] . '_width'} : $default_with;
                $func['label'] = $function[0]->{$function[1]};
                if ($function[0]->{$function[1] . '_order'} == COLUMN_IN_TAIL) {
                    $func['order'] = 100;
                } elseif ($function[0]->{$function[1] . '_order'} == COLUMN_IN_HEAD) {
                    $func['order'] = 1;
                } else {
                    $func['order'] = $function[0]->{$function[1] . '_order'};
                }

                $func['ref']         = $function;
                $func['sql']         = '1';
                $func['order_field'] = '';
                if ($function[0]->{$function[1] . '_order_field'}) {
                    $func['order_field'] = $function[0]->{$function[1] . '_order_field'};
                }
                $func['alias_name'] = $function[1];
                if ($func['label']) {
                    //只有有名称，才能被显示
                    $return[$function[1]] = $func;
                }
            }
        }
        return $this->func_list;
    }
}
