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


class base_db_tools{

    static function getinsertsql(&$rs, &$data,$autoup=false) {
        $db = kernel::database();
        if(!$rs['rs']){
            trigger_error('GetInsertSQL: '.$rs['sql'].' error ',E_USER_WARNING);
            return false;
        }
        mysql_field_seek($rs['rs'],0);
        if(is_object($data)){
            $data = get_object_vars($data);
        }

        foreach((array)$data as $key=>$value){
            $data[strtolower($key)]=$value;
        }
        if (preg_match('/FROM\s+([]0-9a-z_:"`.@[-]*)/is', $rs['sql'], $tableName)){
            $tableName = $tableName[1];
        }

        if($autoup){
            $keyColumn = mysql_fetch_field($rs['rs']);
            if(!$data[strtolower($keyColumn->name)]){
                $rs = $db->exec('SELECT MAX('.$keyColumn->name.') AS keyid FROM '.$tableName);
                $result = $db->selectrow('SELECT MAX('.$keyColumn->name.') AS keyid FROM '.$tableName);
                $data[$keyColumn->name]= $result['keyid'] + 1;
            }
        }
        $insertValues = array();
        $col_count = mysql_num_fields($rs['rs']);
        for($i=0;$i<$col_count;$i++) {
            $column = mysql_fetch_field($rs['rs'],$i);
            if(isset($data[$column->name])){
                $insertValues[$column->name] = self::quotevalue($db,$data[$column->name],$column->type);
            }
        }
        $strValue = implode(',',$insertValues);
        $strFields = implode('`,`',array_keys($insertValues));
        mysql_field_seek($rs['rs'],0);
        return 'INSERT INTO '.$tableName.' ( `'.$strFields.'` ) VALUES ( '.$strValue.' )';  //todo: 能通过connection的表名肯定是正确的，所以这里不需要再添加'`'，避免重复添加
    }

    static function getupdatesql(&$rs, $data, $InsertIfNoResult = false,$insertData=null,$ignore=false){
        $db = kernel::database();
        if(!$rs['rs']){
            trigger_error('GetUpdateSQL: '.$rs['sql'].' error ',E_USER_ERROR);
        }
        @mysql_data_seek($rs['rs'],0);
        $row = mysql_fetch_assoc($rs['rs']);
        if($InsertIfNoResult && !$row){
            return self::getinsertsql($rs,$data);
        }

        if (preg_match('/FROM\s+([]0-9a-z_:"`.@[-]*)/is', $rs['sql'], $tableName)){
            $tableName = $tableName[1];
        }

        if(is_object($data)){
            $data = get_object_vars($data);
        }

        foreach($data as $key=>$value){
            $data[strtolower($key)]=$value;
        }

        $UpdateValues = array();
        $col_count = mysql_num_fields($rs['rs']);
        for($i=0;$i<$col_count;$i++) {
            $column = mysql_fetch_field($rs['rs'],$i);
            if(array_key_exists($column->name, $data) && ($ignore || $data[$column->name]!==$row[$column->name] || $column->type == 'bool')){
                if(is_array($data[$column->name]) || is_object($data[$column->name])){
                    if(serialize($data[$column->name])==$row[$column->name]){
                        continue;
                    }
                }
                $UpdateValues[] ='`'.$column->name.'`='.self::quotevalue($db,$data[$column->name],$column->type);
            }
        }
        mysql_field_seek($rs['rs'],0);
        if (count($UpdateValues)>0) {
            $whereClause = base_db_tools::db_whereClause($rs['sql']);
            $UpdateValues=implode(',',$UpdateValues);
            $sql = 'UPDATE '.$tableName.' SET '.$UpdateValues;
            if (strlen($whereClause) > 0)
                $sql .= ' WHERE '.$whereClause;
            return $sql;
        } else {
            return '';
        }
    }

    static function db_whereClause($queryString){

        preg_match('/\sWHERE\s(.*)/is', $queryString, $whereClause);

        $discard = false;
        if ($whereClause) {
            if (preg_match('/\s(ORDER\s.*)/is', $whereClause[1], $discard));
            else if (preg_match('/\s(LIMIT\s.*)/is', $whereClause[1], $discard));
            else preg_match('/\s(FOR UPDATE.*)/is', $whereClause[1], $discard);
        } else
            $whereClause = array(false,false);

        if ($discard)
            $whereClause[1] = substr($whereClause[1], 0, strlen($whereClause[1]) - strlen($discard[1]));
        return $whereClause[1];
    }

    static function filter2sql($filter){
        $db = kernel::database();
        $where = array('1');
        
        // format filter to array
        if ($filter) {
            foreach ($filter as $k => $v) {
                // Column name hardening: only allow simple identifiers
                $k = (string)$k;
                if ($k === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $k)) {
                    continue;
                }
                $col = '`' . $k . '`';
                
                if (is_array($v)) {
                    $ac = array();
                    foreach ($v as $m) {
                        if ($m !== '_ANY_' && $m !== '' && $m != '_ALL_') {
                            if ($m === null) {
                                $m = '';
                            }
                            $ac[] = $col . ' = ' . $db->quote($m);
                        } else {
                            $ac = array();
                            break;
                        }
                    }
                    if (count($ac) > 0) {
                        $where[] = '(' . implode(' or ', $ac) . ')';
                    }
                } else {
                    if ($v === null) {
                        $v = '';
                    }
                    $where[] = $col . ' = ' . $db->quote($v);
                }
            }
        }
        
        return implode(' AND ', $where);
    }

    /**
     * quotevalue
     * @param mixed $db db
     * @param mixed $value value
     * @param mixed $valuedef valuedef
     * @return mixed 返回值
     */
    static public function quotevalue(&$db,$value,$valuedef){
        if(null===$value){
            return 'null';
        }

        switch($valuedef){
        case 'bool':
            return '\''.((strtolower($value)!='false' && $value || (is_int($value) && $value>0))?'true':'false').'\'';
            break;

        case 'real':
        case 'int':
            $value = trim($value);
            if($value===''){
                return 'null';
            }else{
                return $value;
            }
            break;
        case 'serialize':
            return $db->quote(serialize($value));
            break;
        default:
            if(is_array($value) || is_object($value)){
                return $db->quote(serialize($value));
            }else{
                return $db->quote($value);
            }
            break;
        }
    }

}
