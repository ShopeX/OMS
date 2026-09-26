<?php
class console_mdl_basic_material extends material_mdl_basic_material
{
    //导出的文件名
    var $export_name = '总库存列表';
    
    public function table_name($real = false)
    {
        if($real){
            $table_name = 'sdb_material_basic_material';
        }else{
            $table_name = 'basic_material';
        }
        return $table_name;
    }
    
    public function get_schema()
    {
        return app::get('material')->model('basic_material')->get_schema();
    }
    
    //额外导出字段（因finder里相关字段用了html显示 无法取到值）
    function export_extra_cols(){
        return array_merge(array(
            'column_store_freeze' => array('label'=>'总冻结库存','width'=>'100','func_suffix'=>'store_freeze'),
            'column_arrive_store' => array('label'=>'在途库存','width'=>'100','func_suffix'=>'arrive_store'),
        ), material_basic_material_master::extraCols());
    }
    function export_extra_store_freeze($rows){
        return kernel::single('ome_exportextracolumn_store_freeze')->process($rows);
    }
    function export_extra_arrive_store($rows){
        return kernel::single('ome_exportextracolumn_arrive_store')->process($rows);
    }
    
    public function getListStock($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        if(!$cols){
            $cols = $this->defaultCols;
        }
        if(!empty($this->appendCols)){
            $cols.=','.$this->appendCols;
        }
        if($this->use_meta){
            $meta_info = $this->prepare_select($cols);
        }
    
        $tableAlias = $this->table_name(true);

        $tmpCols = array();
        foreach(explode(',',$cols) as $col){
            if(strpos($col, 'as column')){
                $tmpCols[] = $col;
            }else{
                $tmpCols[] = $tableAlias.'.'.$col;
            }
        }
        $cols = implode(',',$tmpCols); unset($tmpCols);

        $orderType = $orderType?$orderType:$this->defaultOrder;

        $sql = 'SELECT '.$cols.' FROM `'.$this->table_name(true).'` ,sdb_material_basic_material_ext ext WHERE '.$tableAlias.'.bm_id=ext.bm_id AND '.$this->_filter($filter, $tableAlias);

        if($orderType) {
            $sql.=' ORDER BY ';
            if (is_array($orderType)){
                $sql .= $tableAlias.'.';
                $sql .= implode(' ' , $orderType);
            }else {
                $sql .= $tableAlias.'.'.$orderType;
            }
        }

        $data = $this->db->selectLimit($sql,$limit,$offset);
        $this->tidy_data($data, $cols);

        if($this->use_meta && count($meta_info['metacols']) && $data){
            foreach($meta_info['metacols'] as $col){
                $obj_meta = new dbeav_meta($this->table_name(true),$col,$meta_info['has_pk']);
                $obj_meta->select($data);
            }
        }
        return $data;
    }
    
    public function countStock($filter=null)
    {
        $sql = 'SELECT  COUNT(*) as _count FROM `'.$this->table_name(1).'` LEFT JOIN  sdb_material_basic_material_ext AS ext  ON '.$this->table_name(1).'.bm_id = ext.bm_id WHERE '.$this->_filter($filter, $this->table_name(1));

        $row = $this->db->selectrow($sql);
    
        return intval($row['_count']);
    }
    
    function _filter($filter,$tableAlias=null,$baseWhere=null){
        if (!is_array($filter)) {
            $filter = array();
        }
        if($filter['material_bn'] && is_string($filter['material_bn']) && strpos($filter['material_bn'], "\n") !== false){
            $filter['material_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['material_bn']))));
        }
        $sqlstr = '';

        if ($filter['brand_id']) {
            $sqlstr .= ' ext.brand_id in( ' . implode(',',$filter['brand_id']) . ') AND ';
            unset($filter['brand_id']);
        }
        
        return $sqlstr.parent::_filter($filter,$tableAlias,$baseWhere);
    }
    
    public function fcount_csv($filter = null)
    {
        return $this->countStock($filter);
    }
    
    public function getExportTitle($fields)
    {
        $newColumn = array();
        foreach ((array)$this->get_schema()['in_list'] as $key) {
            $newColumn[$key] = $this->get_schema()['columns'][$key];
        }
        
        $allColumn      = array_merge($this->all_columns(), $newColumn);
        $export_columns = array_combine(array_keys($allColumn), array_column($allColumn, 'label'));
        
        $title = array();
        foreach (explode(',', $fields) as $k => $col) {
            if (isset($export_columns[$col])) {
                $title[] = $export_columns[$col];
            }
        }
        
        return mb_convert_encoding(implode(',', $title), 'GBK', 'UTF-8');
    }
    
    //根据查询条件获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
    {
        
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        //根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            $data['content']['main'][] = $this->getExportTitle($fields);
        }
        
        if (!$list = $this->getListStock('*', $filter, $start, $end)) {
            return false;
        }
        
        if (count($this->extra_cols()) > 0) {
            foreach ($this->extra_cols() as $ek => $extra_col) {
                if (method_exists($this, 'extra_' . $extra_col['func_suffix'])) {
                    $extra_col_method = 'extra_' . $extra_col['func_suffix'];
                    $list             = $this->$extra_col_method($list);
                }
            }
        }
        
        //导出时候特定额外要导出的字段
        if (count($this->export_extra_cols()) > 0) {
            foreach ($this->export_extra_cols() as $ek => $export_extra_col) {
                if (method_exists($this, 'export_extra_' . $export_extra_col['func_suffix'])) {
                    $export_extra_col_method = 'export_extra_' . $export_extra_col['func_suffix'];
                    $list                    = $this->$export_extra_col_method($list);
                }
            }
        }
 
        foreach ($list as $aFilter) {
            $aFilter = array_map(function ($val) {
                return str_replace(',', '，', $val);
            }, $aFilter);
            $listRow = $aFilter;
            
            $listRow['create_time'] = $aFilter['create_time'] ? date('Y-m-d H:i:s',$aFilter['create_time']) : '';
            $listRow['last_modified'] = $aFilter['last_modified'] ? date('Y-m-d H:i:s',$aFilter['last_modified']) : '';
            $listRow['serial_number'] = $aFilter['serial_number'] == 'true' ? '是' : '否';
            $listRow['cat_path'] = str_replace(',','',$aFilter['cat_path']);
            $listRow['type'] = $this->modifier_type($aFilter['type']);
            $listRow['visibled'] = $this->modifier_visibled($aFilter['visibled']);
            $listRow['omnichannel'] = $this->modifier_omnichannel($aFilter['omnichannel']);
            $listRow['column_store'] = kernel::single('console_finder_basic_material')->column_store($aFilter,$list);
            $listRow['column_good_store'] = $basicMStockFreezeLib->getMaterialWarehouseStore($aFilter['bm_id']);
            $listRow['column_valid_store'] = kernel::single('console_finder_basic_material')->column_valid_store($aFilter,$list);
            $listRow['column_order_store_freeze'] = kernel::single('console_finder_basic_material')->column_order_store_freeze($aFilter,$list);
            $listRow['column_branch_store_freeze'] = kernel::single('console_finder_basic_material')->column_branch_store_freeze($aFilter,$list);

            $listRow['column_o2o_store'] = $basicMStockFreezeLib->getMaterialO2oStore($aFilter['bm_id']);
            $listRow['column_o2o_store_freeze'] = $basicMStockFreezeLib->getStoreBranchFreezeByBmid($aFilter['bm_id']);
            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {
                if (isset($listRow[$col])) {
                    $listRow[$col] = mb_convert_encoding($listRow[$col], 'GBK', 'UTF-8');
                    $exptmp_data[] = $listRow[$col];
                } else {
                    $exptmp_data[] = '';
                }
            }
            
            $data['content']['main'][] = implode(',', $exptmp_data);
        }
        
        return $data;
    }
    
    //获取所有字段
    private function all_columns()
    {
        //finder扩展字段
        $func_columns = $this->func_columns();
        
        //新方式扩展字段
        $extra_columns = array();
        if (count($this->extra_cols()) > 0) {
            $extra_columns = $this->extra_cols();
        }
        
        //额外导出扩展字段
        $export_extra_columns = array();
        if (count($this->export_extra_cols()) > 0) {
            $export_extra_columns = $this->export_extra_cols();
        }
        
        //表结构原声字段
        $columns = array();
        foreach ((array)$this->dbschema['in_list'] as $key) {
            $columns[$key] = &$this->dbschema['columns'][$key];
        }
        
        //合并所有字段
        $return = array_merge((array)$func_columns, (array)$extra_columns, (array)$export_extra_columns,
            (array)$columns);
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
    private function func_columns()
    {
        $service_list = array();
        foreach (kernel::servicelist('desktop_finder.console_mdl_basic_material') as $name => $object) {
            $service_list[$name] = $object;
        }
        $addon_columns = array();
        foreach ($service_list as $name => $object) {
            $tmpobj = $object;
            foreach (get_class_methods($tmpobj) as $method) {
                switch (substr($method, 0, 7)) {
                    case 'column_':
                        $addon_columns[] = array(&$tmpobj, $method);
                        break;
                }
            }
        }
        foreach ($addon_columns as $k => $function) {
            $func['type']  = 'func';
            $func['width'] = $function[0]->{$function[1] . '_width'} ? $function[0]->{$function[1] . '_width'} : $default_with;
            $func['label'] = $function[0]->{$function[1]};
            $func['order'] = $function[0]->{$function[1] . '_order'};
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
        return $return;
    }
    
}