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
 
class base_application_dbtable extends base_application_prototype_filepath{

    var $path = 'dbschema';
    var $use_db_cache = true;
    var $_define = null;
    static $force_update = false;

    static $__type_define = array();

    function __construct($app=null) 
    {
        parent::__construct($app);
        $db = kernel::database();
        //$rs = $db->select('show variables like "have_innodb"');
        //$this->_enable_innodb = ($rs[0]['Value']=='YES' ? true : false);
        $this->_enable_innodb = true;
    }//End Function

    function get_sql($tablename=null){
        if(!$tablename){
            $tablename = $this->real_table_name();
        }
        
        $define = &$this->load();
        $rows = array();
        foreach($define['columns'] as $k=>$v){
            $rows[] = '`'.$k.'` '.$this->get_column_define($v);
        }

        $this->get_index_sql('PRIMARY');

        if($define['pkeys']){
            $rows[] = $this->get_index_sql('PRIMARY');
        }
        if(is_array($define['index'])){
            foreach($define['index'] as $key=>$value){
                $rows[] = $this->get_index_sql($key);
            }
        }

        $sql = 'CREATE TABLE `'.$tablename.'`'." (\n\t".implode(",\n\t",$rows)."\n)";
        $engine = isset($define['engine'])?$define['engine']:'InnoDB';
        if(!$this->_enable_innodb && strtolower($engine)=='innodb'){
            $engine = 'MyISAM';
        }
        if($this->dbver == 3){
            $sql.= 'type = '.$engine;
        }else{
            $sql.= 'ENGINE = '.$engine;
        }
    
        if (!isset($define['charset']) || !in_array($define['charset'],array('utf8','utf8mb4'))) {
            $sql .= ' DEFAULT CHARACTER SET utf8';
        }else{
            $sql .= ' DEFAULT CHARACTER SET ' . $define['charset'];
        }
        
        if (isset($define['comment']) && $define['comment']) {
	        $sql.= ' COMMENT = "'.$define['comment'].'"';
        }

        // 分区
        if (isset($define['partition']) && $define['partition']) {
	        $sql.= ' PARTITION BY '.$define['partition']['type'].' (`'.$define['partition']['columns'].'`) (';

            $partitions = [];
            foreach ($define['partition']['partitions'] as $key => $value) {
                $partitions[] = 'PARTITION '.$key.' VALUES LESS THAN ('.$value.')';
            }
            $sql .= implode(',', $partitions) . ')';
        }
	    
        $sql .= ';';
        return $sql;
    }

    function real_table_name(){
        return kernel::database()->prefix.$this->target_app->app_id.'_'.$this->key();
    }

    function &load($check_lastmodified=true){
    
        $real_table_name = $this->real_table_name();
        if($this->_define[$real_table_name]){
            return $this->_define[$real_table_name];
        }

        if(kernel::is_online() && !($this->target_app->app_id=='base' && $this->key()=='kvstore')){
            if(defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/dbschema/'.$this->key.'.php')){
                 $define_lastmodified = ($check_lastmodified) ? filemtime(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/dbschema/'.$this->key.'.php') : null;
            }else{
                 $define_lastmodified = ($check_lastmodified) ? filemtime($this->target_app->app_dir.'/dbschema/'.$this->key.'.php') : null;
            }
            $define_flag = base_kvstore::instance('tbdefine')->fetch($this->target_app->app_id.$this->key, $define, $define_lastmodified);
        }else{
            $define_flag = false;
        }
        if($define_flag === false || !$define){
            if(defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/dbschema/'.$this->key.'.php')){
                 require(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/dbschema/'.$this->key.'.php');
            }else{
                 require($this->target_app->app_dir.'/dbschema/'.$this->key.'.php');
            }
            $define = &$db[$this->key()];
            $this->_define[$real_table_name] = &$define;

            foreach($define['columns'] as $k=>$v){

                if($v['pkey'])
                    $define['idColumn'][$k] = $k;

                if($v['is_title'])
                    $define['textColumn'][$k] = $k;

                if($v['in_list']){
                    $define['in_list'][] = $k;
                    if($v['default_in_list']){
                        $define['default_in_list'][] = $k;
                    }
                }

                $define['columns'][$k] = $this->_prepare_column($k, $v);
                if(isset($v['pkey']) && $v['pkey']){
                    $define['pkeys'][$k] = $k;
                }

            }

            if(!$define['idColumn']){
                $define['idColumn'] = key($define['columns']);
            }elseif(count($define['idColumn'])==1){
                $define['idColumn'] = current($define['idColumn']);
            }

            if(!$define['textColumn']){
                $keys = array_keys($define['columns']);
                $define['textColumn'] = $keys[1];
            }elseif(is_string($define['idColumn'])){
                $define['textColumn'] = current($define['textColumn']);
            }

            if(kernel::is_online() && !($this->target_app->app_id=='base' && $this->key()=='kvstore')){
                base_kvstore::instance('tbdefine')->store($this->target_app->app_id.$this->key,$define);
            }
        }
        
        return $define;
    }

    function get_column_define($v){
        $r = $v['realtype'];
        if(isset($v['required']) && $v['required']){
            $r.=' not null';
        }
        if(isset($v['default'])){
            if($v['default']===null){
                $r.=' default null';
            }elseif(is_string($v['default']) && !in_array($v['default'], array ('CURRENT_TIMESTAMP','CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))){
                $r.=' default \''.$v['default'].'\'';
            }else{
                $r.=' default '.$v['default'];
            }
        }
        if(isset($v['extra'])){
            $r.=' '.$v['extra'];
        }

        $comment = $v['comment'] ?: $v['label'];

	    if(isset($comment) && $comment){
		    $r.=' comment \''.$comment.'\'';
	    }

        return $r;
    }

    function _prepare_column($col_name, $col_set){
        $col_set['realtype'] = $col_set['type'];
        if(is_array($col_set['type'])){
            $col_set['realtype'] = 'enum(\''.implode('\',\'',array_keys($col_set['type'])).'\')';
        }elseif(substr($col_set['type'],0,6)=='table:'){
            list(,$tablename,$column) = explode(':',$col_set['type']);
            if($p=strpos($tablename,'@')){
                $app = substr($tablename,$p+1);
                $tablename = substr($tablename,0,$p);
            }else{
                $app = $this->target_app;
            }

            $table = new base_application_dbtable;
            $def = $table->detect($app,$tablename)->load(false);

            if(!$column){
                $pkeyfounded = false;
                foreach($def['columns'] as $cn=>$ci){
                    if($ci['pkey']){
                        $column = $cn;
                        $pkeyfounded = true;
                        break;
                    }
                }
                if(!$pkeyfounded){
                    $column = key($def['columns']);
                }
            }
            if($col_set['pkey'] !== true){
                $define = &$this->load();
                $define['index']['idx_c_'.$col_name] = array('columns'=>array($col_name));
            }
            $col_set['realtype'] = $def['columns'][$column]['realtype'];
        }elseif($this->type_define($col_set['type'])){
            $col_set['realtype'] = $this->type_define($col_set['type']);
        }

        if(substr(trim($col_set['realtype']),-4,4)=='text'){
            unset($col_set['default']);
        }else{
            //int
            $col_set['realtype'] = str_replace('integer','int',$col_set['realtype']);
            if(false===strpos($col_set['realtype'],'(')){
                $int_length = 0;
                if(false!==strpos($col_set['realtype'],'tinyint')){
                    $int_length = 4;
                }elseif(false!==strpos($col_set['realtype'],'smallint')){
                    $int_length = 6;
                }elseif(false!==strpos($col_set['realtype'],'mediumint')){
                    $int_length = 9;
                }elseif(false!==strpos($col_set['realtype'],'bigint')){
                    $int_length = 20;
                }elseif(false!==strpos($col_set['realtype'],'int')){
                    $int_length = 11;
                }
                if($int_length){
                    if($int_length<20 && false!==strpos($col_set['realtype'],'unsigned')){
                        $int_length--;
                    }
                    $col_set['realtype'] = str_replace('int','int('.$int_length.')',$col_set['realtype']);
                }
            }
        }
        return $col_set;
    }

    function type_define($type){
        if(!self::$__type_define){
            if(defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR.'/base/datatypes.php')){
                 require(CUSTOM_CORE_DIR.'/base/datatypes.php');
            }else{
                 require(APP_DIR.'/base/datatypes.php');
            }

            $types = array();
            foreach($datatypes as $k=>$v){
                if($v['sql']){
                    $types[$k] = $v['sql'];
                }
            }
            self::$__type_define = &$types;
        }
        return isset(self::$__type_define[$type])?self::$__type_define[$type]:false;
    }

    function current(){
        $this->key = substr($this->iterator()->getFilename(),0,-4);
        return $this;
    }

    function filter(){
        return substr($this->iterator()->getFilename(),-4,4)=='.php' && is_file($this->getPathname());
    }

    function install(){
        $db = kernel::database();
        $sql = $this->get_sql();
        $real_table_name = $this->real_table_name();
        $db->exec('drop table if exists `'.$real_table_name.'`');
        $rs = $db->exec($sql);
        
        kernel::log('Creating table '.$real_table_name.' ...... '.($rs?'ok':'error'));
    }

    private function get_current_define($tbname){
        $define = kernel::database()->select("/*FORCE_MASTER*/ show tables like '".$tbname."'");
        if($define){
            $rows = @kernel::database()->select('/*FORCE_MASTER*/ SHOW COLUMNS FROM '.$tbname);
            $columns = array();
            if($rows){
                foreach($rows as $c){
                    $columns[$c['Field']] = array(
                        'type'=>$c['Type'],
                        'default'=>$c['Default'],
                        'required'=>!($c['Null']=='YES'),
                    );
                }
            }

            $rows = @kernel::database()->select('/*FORCE_MASTER*/ SHOW INDEX FROM '.$tbname);
            $index = array();
            if($rows){
                foreach($rows as $row){
                    $index[$row['Key_name']] = array(
                                    'Column_name'=>$row['Column_name'],
                                    'Non_unique'=>$row['Non_unique'],
                                    'Collation'=>$row['Collation'],
                                    'Sub_part'=>$row['Sub_part'],
                                    'Index_type'=>$row['Index_type'],
                                );
                }
            }
            return array('columns'=>$columns, 'index'=>$index);
        }else{
            return false;
        }
    }


    function get_index_sql($name){
        $define = $this->load();
        if($name=='PRIMARY'){
            if($define['pkeys']){
                return 'primary key ('.implode(',',$define['pkeys']).')';
            }
        }else{
            $value = $define['index'][$name];

            foreach ($value['columns'] as $k => $c) {
                if (preg_match('/(\w+)\((\d+)\)$/',$c,$m)) {
                    $value['columns'][$k] = '`'.$m[1].'`('.$m[2].')';
                } else {
                    $value['columns'][$k] = '`'.$c.'`';
                }
            }

            return $value['prefix'].' INDEX '.$name.($value['type']?(' USING '.$value['type']):'').'('.implode(',',$value['columns']).')';
        }
    }

    function diff_sql($be_careful=true){
        
        $diff = array();
        $real_table_name = $this->real_table_name();
        $old_define = $this->get_current_define($real_table_name);
        
        if($old_define){
            $tb_define = $this->load();
            $db = kernel::database();
            $tmp_table = 'tmp_'.uniqid();
            if(@!$db->exec($this->get_sql($tmp_table))){
                return false;
            }
            $new_define = $this->get_current_define($tmp_table);
            $db->exec('drop table if exists '.$tmp_table);
            
            if($new_define==$old_define){
                return array();
            }else{
                foreach($new_define['columns'] as $key=>$define){
                    if(isset($old_define['columns'][$key])){
                        if($old_define['columns'][$key] != $new_define['columns'][$key]){
                            if(!$old_define['columns'][$key]['required'] && $new_define['columns'][$key]['required']){
                                $default=$new_define['default']?$new_define['default']:"''";
                                $diff[] = "update {$real_table_name} set `{$key}`={$default} where `{$key}`=null;\n";
                            }
                            $alter[]='MODIFY COLUMN `'.$key.'` '.$this->get_column_define($tb_define['columns'][$key]);
                        }
                    }else{
                        $alter[]='ADD COLUMN `'.$key.'` '.$this->get_column_define($tb_define['columns'][$key]).' '.($last?('AFTER `'.$last.'`'):'FIRST');
                    }
                    unset($old_define['columns'][$key]);
                    $last = $key;
                }
                
                if(is_array($old_define['columns'])){
                    if($be_careful){
                        foreach($old_define['columns'] as $c=>$def){
                            $alter[]='DROP COLUMN `'.$c.'`'; //设置默认值或者允许空值
                        }
                    }
                }
                
                if($alter){
                    $diff[]='ALTER TABLE `'.$real_table_name."` \n\t".implode(",\n\t",$alter).';';
                }

                //todo: 索引和主键

                $old_define_index = $old_define['index'];
                
                foreach($new_define['index'] as $key=>$define){
                    if(isset($old_define['index'][$key])){
                        if($old_define['index'][$key] != $new_define['index'][$key]){
                            print_r($old_define['index'][$key]);
                            print_r($new_define['index'][$key]);
                            echo "=====================\n";
                            $diff[] = 'ALTER TABLE `'.$real_table_name.'` DROP '.($key==='PRIMARY'?'PRIMARY KEY':'INDEX '.$key).' , ADD '.$this->get_index_sql($key);
                        }
                        unset($old_define_index[$key]);
                    }else{
                        $diff[] = 'ALTER TABLE `'.$real_table_name.'` ADD '.$this->get_index_sql($key);
                    }
                }

                if(is_array($old_define_index)){
                    foreach($old_define_index AS $key=>$define){
                        if($key === 'PRIMARY'){
                            $diff[] = 'ALTER TABLE `'.$real_table_name.'` DROP PRIMARY KEY';
                        }else{
                            $diff[] = 'ALTER TABLE `'.$real_table_name.'` DROP KEY `' . $key . '`';
                        }
                    }
                }
            }
        }else{
            $diff[]= $this->get_sql();
        }

        return $diff;
    }
    
    function update($app_id){
        $update_info = $this->diff($app_id);
        if($update_info){
            $this->merge($update_info);
        }
    }
    
    function diff($app_id){
        $diff = array();
        foreach($this->detect($app_id) as $k=>$item){
            //$diff = array_merge($diff,$item->diff_sql());
            $item_sql_arr = $item->diff_sql();
            if(is_array($item_sql_arr)){
                $diff = array_merge($diff, $item_sql_arr);
            }
        }
        return $diff;
    }
    
    function merge($diff){
       if($diff){
            foreach($diff as $sql ){
                kernel::log($sql);
                kernel::database()->exec($sql);
            }
        }
    }
    
    function last_modified($app_id){
        if(self::$force_update){
            return time()+999999;
        }else{
            return parent::last_modified($app_id);    
        }
    }
    
    function clear_by_app($app_id){
        $db = kernel::database();
        $rows = $db->select('/*FORCE_MASTER*/ show tables like '.$db->quote(kernel::database()->prefix.$app_id.'\_%'));
        foreach($rows as $row){
            $tables[] = current($row);
        }
        if($tables){
            $db->exec('drop tables IF EXISTS '.implode(',',$tables));
        }
    }

    function pause_by_app($app_id) 
    {
        $db = kernel::database();
        $tables = array();
        $fix = substr(md5('dbtable_'.$app_id), 0, 16);
        $rows = $db->select('/*FORCE_MASTER*/ show tables like '.$db->quote(kernel::database()->prefix.$app_id.'\_%\_'.$fix));
        foreach($rows as $row){
            $tables[] = current($row);
        }
        if($tables){
            $db->exec('drop tables IF EXISTS '.implode(',',$tables));
        }
        $tables = array();
        $rows = $db->select('/*FORCE_MASTER*/ show tables like '.$db->quote(kernel::database()->prefix.$app_id.'\_%'));
        foreach($rows as $row){
            $tables[] = current($row);
        }
        foreach($tables AS $table){
            $db->exec('ALTER TABLE `' . $table . '` RENAME `' . $table . '_' . $fix . '`');
            kernel::log(sprintf('%s backup to %s', $table, $table . '_' . $fix));
        }
    }//End Function

    function active_by_app($app_id) 
    {
        $db = kernel::database();
        $tables = array();
        $fix = substr(md5('dbtable_'.$app_id), 0, 16);
        $rows = $db->select('/*FORCE_MASTER*/ show tables like '.$db->quote(kernel::database()->prefix.$app_id.'\_%\_'.$fix));
        foreach($rows as $row){
            $tables[] = current($row);
        }
        foreach($tables AS $table){
            $pos = strpos($table, '_' . $fix);
            if($pos){
                $db->exec('ALTER TABLE `' . $table . '` RENAME `' . substr($table, 0, $pos) . '`');
                kernel::log(sprintf('%s restore', substr($table, 0, $pos)));
            }
        }
    }//End Function
    
}
