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

class ome_mdl_operation_log extends dbeav_model{
    
    var $operations = array();
    
    function __construct($app){
        parent::__construct($app);
        if(base_kvstore::instance('service')->fetch('operation_log',$service_define)){
            foreach($service_define['list'] as $k => $v) {
                try {
                    $newObj = new $v();
                    if(method_exists($newObj,'get_operations')){
                        $this->operations += $newObj->get_operations();
                    }
                } catch (\Throwable $th) {
                    //throw $th;
                } 
            }
        }
    }

    /**
     * 批量写日志
     * 
     * @return void
     * @author 
     * */
    public function batch_write_log2($logs)
    {
        $opinfo = $this->_get_op_info();
        $ip = kernel::single("base_request")->get_remote_addr();
        foreach ($logs as $key => $value) {
            $_operations = $this->_get_operations($value['operation']);

            $logs[$key]['op_id']        = $opinfo['op_id'];
            $logs[$key]['op_name']      = $opinfo['op_name'];
            $logs[$key]['operate_time'] = time();
            $logs[$key]['obj_type']     = $_operations['obj_type'];
            $logs[$key]['ip']           = $ip;
        }

        $sql = ome_func::get_insert_sql($this,$logs);

        return $this->db->exec($sql);
    }

    /*
     * 写日志
     * 
     * @param int $operation 操作标识
     * @param int $obj_id 操作对象id（主键ID）
     * @param string $memo 操作内容备注
     * @param int $operate_time 操作时间
     * @param string $opinfo 操作额外信息
     * 
     * @return bool
     */
    function write_log($operation,$obj_id,$memo=NULL,$operate_time=NULL,$opinfo=NULL){
        
        //操作额外信息
        $opinfo = $this->_get_op_info($opinfo);
        $op_id = $opinfo['op_id'];#操作者ID
        $op_name = $opinfo['op_name'];#操作者姓名
        
        $_operations = $this->_get_operations($operation);
        $title_column = $_operations['title_column'];
        $obj_type = $_operations['obj_type'];
        $model = $_operations['model'];
        $title_value = "";
        if($title_column){
            $tmp = $model->dump($obj_id,$title_column);
            $title_value = $tmp[$title_column];
        }
        if ($_operations){
            $ip = kernel::single("base_request")->get_remote_addr();
            $data = array(
               'obj_id' => $obj_id,
               'obj_name' => $title_value,
               'obj_type' => $obj_type,
               'operation' => $operation,
               'op_id' => $op_id,
               'op_name' => $op_name,
               'operate_time' => $operate_time ? $operate_time : time(),
               'memo' => $memo,
               'ip' => $ip
            );
            
            $this->save($data);
            return $data['log_id'];
        }else{
            return false;
        }
    }

    /*
     * 批量写入相同类型的日志记录时使用
     * 比如全选订单分派给管理员时，如果记录过大循环write_log会超时，这里采用insert into select
     *
     * @param int $operation 操作id
     * @param string $memeo 操作备注
     * @param int $operate_time 操作时间
     * @param array $filter
     *
     * @return bool
     */
    function batch_write_log($operation,$filter='',$memo=NULL,$operate_time=NULL,$opinfo=NULL){
		
        $opinfo = $this->_get_op_info($opinfo);
        $op_id = $opinfo['op_id'];
        $op_name = $opinfo['op_name'];
        
        $_operations = $this->_get_operations($operation);
        if ($_operations){
            $model = $_operations['model'];
            $title_column = $_operations['title_column'];
            $obj_type = $_operations['obj_type'];
            
            $table = $model->table_name(1);
            $primary_key = $model->idColumn;
            $ip = kernel::single("base_request")->get_remote_addr();
            $operate_time = $operate_time ? $operate_time : time();

            if($title_column){
                $column_name = "obj_name,";
                $column_value = $title_column.",";
            }
            $sql = "INSERT INTO sdb_ome_operation_log (obj_id,{$column_name}obj_type,operation,op_id,op_name,operate_time,memo,ip)
                    SELECT ".$primary_key.",{$column_value}'".$obj_type."','".$operation."','".$op_id."','".$op_name."','".$operate_time."','".$memo."','".$ip."' FROM ".$table." WHERE ".$model->_filter($filter);
    
            return $this->db->exec($sql);
        }else{
            return false;
        }
    }
    
    /**
     * 获取操作者信息
     * @param mixed $opinfo 操作人信息
     * @access private
     * @return ArrayObject
     */
    private function _get_op_info($opinfo=NULL){
        if ($opinfo){
            $_opinfo = $opinfo;
        }else {
            $_opinfo = kernel::single('ome_func')->getDesktopUser();
        }
        return $_opinfo;
    }
    
    /**
     * 获取操作对象相关信息
     * @access private
     * @param string $operation 操作标识
     * @return ArrayObject
     */
    private function _get_operations($operation=NULL){
        
        if (empty($operation)) return NULL;
        //操作标识名称
        $_operations = explode("@",$operation);
        if (empty($_operations)) return NULL;
        $_operations[1] = $_operations[1] ? $_operations[1] : 'ome';
        $_operations = $this->operations[$_operations[1]][$_operations[0]];
        $obj_type = $_operations['type'];
        
        //对象model
        $type_model = explode("@",$obj_type);
        $model_name = $type_model[0];
        $app = $type_model[1];
        $model = app::get($app)->model($model_name);
        $schemas = $model->schema['columns'];
        $title_column = "";
        foreach($schemas as $k=>$v){
            if(isset($v['is_title']) && $v['is_title']){
                $title_column = $k;
                break;
            }
        }
        $_operations = array(
            'obj_type' => $obj_type,
            'title_column' => $title_column,
            'app' => $app,
            'model' => $model,
            'model_name' => $model_name
        );

        return $_operations;
    }

    /*
     * 读取日志
     *
     * @param mixed $filter
     * @param int $start
     * @param int $limit
     * @param string $orderType
     *
     * return array
     */
    function read_log($filter='',$start=0,$limit=20,$orderType=null){
        $logs = parent::getList("*",$filter,$start,$limit,$orderType);
        if($logs){
            foreach($logs as $k=>$v){
                if(isset($v['operation'])){
                    $_operations = explode("@",$v['operation']);
                   
                    $_operations = $this->operations[$_operations[1]][$_operations[0]];
                    $logs[$k]['operation'] = $_operations['name'];
                }

                if($_operations['type']!='orders@ome'){
                    $logs[$k]['memo'] = strip_tags($v['memo']);
                }                
            }
            return $logs;
        }else{
            return array();
        }
    }
    /**
     * 过滤器
     * @see dbeav_model::_filter()
     */
    public function _filter($filter, $tableAlias = null, $baseWhere=null) {
        if (isset($filter['user']) && $filter['user'] !== '') {
            $filter['op_id'] = $filter['user'];
            unset($filter['user']);
        }
        if (isset($filter['operation_type']) && $filter['operation_type'] !== '') {
            $operationLogLib = kernel::single('ome_operation_log');
            $map = $operationLogLib->getTypeMap($filter['operation_type']);
            $filter['obj_type'] = $map;
            unset($filter['operation_type']);
        }
        if (isset($filter['st_time']) && $filter['st_time'] && isset($filter['et_time']) && $filter['et_time']) {
            $stTime = strtotime($filter['st_time']);
            $time = strtotime($filter['et_time']);
            $edTime = mktime(0, 0, 0, date("m", $time), (date("d", $time) + 1), date("Y", $time));
            $filter['operate_time|bthan'] = $stTime;
            $filter['operate_time|lthan'] = $edTime;
            unset($filter['st_time']);
            unset($filter['et_time']);
        }
        elseif (isset($filter['st_time']) && $filter['st_time']) {
            $stTime = strtotime($filter['st_time']);
            $filter['operate_time|bthan'] = $stTime;
            unset($filter['st_time']);
        }
        elseif (isset($filter['et_time']) && $filter['et_time']) {
            $time = strtotime($filter['et_time']);
            $edTime = mktime(0, 0, 0, date("m", $time), (date("d", $time) + 1), date("Y", $time));
            $filter['operate_time|lthan'] = $edTime;
            unset($filter['et_time']);
        }
        return parent::_filter($filter,$tableAlias,$baseWhere);
    }
}
?>