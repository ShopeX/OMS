<?php
/**
 * Copyright © ShopeX （http://www.shopex.cn）. All rights reserved.
 * See LICENSE file for license details.
 */


if (file_exists(dirname(__FILE__) . '/compat.php')) {

    require_once(dirname(__FILE__) . '/compat.php');
}

class base_db_connections extends base_db_abstract implements base_interface_db
{

    protected $_rw_lnk = null;
    protected $_ro_lnk = null;
    protected $_use_transaction = false;
    private $_hchsafe_sql = array();

    public function exec($sql , $skipModifiedMark=false, $db_lnk=null){

        if($this->prefix!='sdb_'){
            //$sql = preg_replace('/([`\s\(,])(sdb_)([a-z\_]+)([`\s\.]{0,1})/is',"\${1}".$this->prefix."\\3\\4",$sql);
            $sql = preg_replace_callback('/([`\s\(,])(sdb_)([0-9a-z\_]+)([`\s\.]{0,1})/is', array($this, 'fix_dbprefix'), $sql); //todo: 兼容有特殊符号的表名前缀
        }

        if(!is_resource($db_lnk)){
            if($this->_rw_lnk){
                $db_lnk = &$this->_rw_lnk;
                if (!mysql_ping($db_lnk)) {
                    //连接不成功
                    $db_lnk = $this->_rw_conn();
                }
            }else{
                $db_lnk = $this->_rw_conn();
            }
        } else {
            if (!mysql_ping($db_lnk)) { 
                //连接不成功
                $db_lnk = $this->_rw_conn();
            }
        }
	// 御城河
        if(defined('MQ_HCHSAFE') && true === constant("MQ_HCHSAFE") && strpos($sql, $this->prefix.'ome_orders')) {
            $this->_hchsafe_sql['sqls'][] = $sql;
        }

        if($rs = mysql_query($sql,$db_lnk)){
            self::$mysql_query_executions++;
            $db_result = array('rs'=>&$rs,'sql'=>$sql);
            return $db_result;
        }else{
            trigger_error($sql.':'.mysql_error($db_lnk),E_USER_WARNING);
            return false;
        }
    }

    protected function fix_dbprefix($matchs) 
    {
        return $matchs[1] . ((trim($matchs[1])=='`') ? $this->prefix.$matchs[3] : '`'.$this->prefix.$matchs[3].'`') . $matchs[4];
    }//End Function

    public function select($sql, $skipModifiedMark=false){
        if($this->_rw_lnk){
            $db_lnk = &$this->_rw_lnk;
        }else{
          if($this->_ro_lnk){
              $db_lnk = &$this->_ro_lnk;
          }else{
              $db_lnk = $this->_ro_conn();
          }
        }

        if($this->prefix!='sdb_'){
            //$sql = preg_replace('/([`\s\(,])(sdb_)([a-z\_]+)([`\s\.]{0,1})/is',"\${1}".$this->prefix."\\3\\4",$sql);
            $sql = preg_replace_callback('/([`\s\(,])(sdb_)([0-9a-z\_]+)([`\s\.]{0,1})/is', array($this, 'fix_dbprefix'), $sql); //todo: 兼容有特殊符号的表名前缀
        }//todo:为了配合check_expries判断表名，冗余执行

        $rs = $this->exec($sql, $skipModifiedMark, $db_lnk);
        if($rs['rs']){
            $data = array();
            while($row = mysql_fetch_assoc($rs['rs'])){
                $data[]=$row;
            }
            mysql_free_result($rs['rs']);
            return $data;
        }else{
            return false;
        }
    }

    public function selectrow($sql){
        $row = $this->selectlimit($sql,1,0);
        return $row[0];
    }

    public function selectlimit($sql,$limit=10,$offset=0){
        if ($offset >= 0 || $limit >= 0){
            $offset = ($offset >= 0) ? $offset . "," : '';
            $limit = ($limit >= 0) ? $limit : '18446744073709551615';
            $sql .= ' LIMIT ' . $offset . ' ' . $limit;
        }
        $data = $this->select($sql);
        return $data;
    }

    protected function _ro_conn(){
        if(defined('DB_SLAVE_HOST') && $this->_use_transaction !== true){
            //todo:如果设置过slave，且没有启用过事务
            $this->_ro_lnk = $this->_connect(DB_SLAVE_HOST,DB_SLAVE_USER,DB_SLAVE_PASSWORD,DB_SLAVE_NAME);
        }elseif($this->_rw_lnk){
            $this->_ro_lnk = &$this->_rw_lnk;
        }else{
            $this->_ro_lnk = $this->_rw_conn();
        }
        return $this->_ro_lnk;
    }

    public function getRows($rs,$row=10){
        $i=0;
        $data = array();
        while(($row = mysql_fetch_assoc($rs['rs'])) && $i++<$row){
            $data[]=$row;
        }
        return $data;
    }

    protected function _rw_conn(){
        $this->_rw_lnk = $this->_connect(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);
        return $this->_rw_lnk;
    }

    protected function _connect($host,$user,$passwd,$dbname){
        if(defined('DB_PCONNECT') && constant('DB_PCONNECT')){
            $lnk = mysql_pconnect($host,$user,$passwd);
        }else{
            $lnk = mysql_connect($host,$user,$passwd);
        }
        if(!$lnk){
            trigger_error(app::get('base')->_('无法连接数据库:').mysql_error(),E_USER_ERROR);
        }
        mysql_select_db( $dbname, $lnk );
        if(preg_match('/[0-9\.]+/is',mysql_get_server_info($lnk),$match)){
            $dbver = $match[0];
            if(version_compare($dbver,'4.1.1','<')){
                define('DB_OLDVERSION',1);
                $this->dbver = 3;
            }else{
                if(defined('DB_CHARSET') && constant('DB_CHARSET')){
                    mysql_query('SET NAMES \''.DB_CHARSET.'\'',$lnk);
                }
                if(!version_compare($dbver,'6','<')){
                    $this->dbver = 6;
                }
            }
        }
        return $lnk;
    }

    public function count($sql) {
        $sql = preg_replace(array(
            '/(.*\s)LIMIT .*/i',
            '/^select\s+(.+?)\bfrom\b/is'
        ),array(
            '\\1',
            'select count(*) as c from'
        ),trim($sql));
        $row = $this->select($sql);
        return intval($row[0]['c']);
    }

    /**
     * _whereClause
     *
     * @param mixed $queryString
     * @access protected
     * @return void
     */
    protected function _whereClause($queryString){

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

    public function quote($string){
        if($this->_rw_lnk){
            $db_lnk = &$this->_rw_lnk;
        }else{
          if($this->_ro_lnk){
              $db_lnk = &$this->_ro_lnk;
          }else{
              $db_lnk = $this->_ro_conn();
          }
        }
        if(!($result=mysql_real_escape_string($string, $db_lnk))){
            //$result=$string;
            $result = addslashes($string);
        }
        //$string=addslashes($string);
        //return "'" . $string . "'";
        return "'" . $result . "'";
    }

    public function lastinsertid(){
        $sql = '/*FORCE_MASTER*/ SELECT LAST_INSERT_ID() AS lastinsertid';
        $rs = $this->exec($sql,true,$this->_rw_lnk);
        $row = mysql_fetch_assoc($rs['rs']);
        mysql_free_result($rs['rs']);
        return $row['lastinsertid'];
    }

    public function affect_row(){
        if($this->_rw_lnk){
            $db_lnk = &$this->_rw_lnk;
        }elseif($this->_ro_lnk){
            $db_lnk = &$this->_ro_lnk;
        }
        return mysql_affected_rows($db_lnk);
    }

    public function errorinfo(){
        if($this->_rw_lnk){
            $db_lnk = &$this->_rw_lnk;
        }elseif($this->_ro_lnk){
            $db_lnk = &$this->_ro_lnk;
        }
        return mysql_error($db_lnk);
    }

    public function errorcode() 
    {
        if($this->_rw_lnk){
            $db_lnk = &$this->_rw_lnk;
        }elseif($this->_ro_lnk){
            $db_lnk = &$this->_ro_lnk;
        }
        return mysql_errno($db_lnk);
    }//End Function

    public function beginTransaction(){
        if(!$this->_in_transaction){
            $this->_in_transaction = true;
            if(!$this->_use_transaction){
                $this->_use_transaction = true;
                if(isset($this->_ro_lnk)){
                    $this->_ro_conn();
                }//todo:如果已经连上slave，变更ro_lnk至主库，保持连线统一
            }//todo:第一次使用事务后即通知程序当前进程ro_conn至主库

            ome_branch_product::initRedisBranch();
            material_basic_material_stock::initRedisMaterialStock();

            return $this->exec('start transaction');
        }else{
            return false;
        }
    }

    public function isInTransaction() {
        return $this->_in_transaction;
    }

    public function commit($status=true){
        if($status){
            $this->exec('commit');
            $this->_in_transaction = false;

            ome_branch_product::delRedisBranchFlow();
            material_basic_material_stock::delRedisMaterialFlow();

            ome_branch_product::initRedisBranch();
            material_basic_material_stock::initRedisMaterialStock();
            return true;
        }else{
            return false;
        }
    }

    public function rollBack(){
        $this->exec('rollback');

        ome_branch_product::rollbackRedisBranch();
        material_basic_material_stock::rollbackRedisMaterialStock();

        $this->_in_transaction = false;
    }
    
	public function dbclose(){
        $flag =  mysql_close($this->_rw_lnk);
        $this->_rw_lnk = null;
        $this->_ro_lnk = null;
        
        return $flag;
    }

    function __destruct(){
        if ($this->_hchsafe_sql) {
            kernel::single('base_hchsafe')->sql_log($this->_hchsafe_sql);    
        }
    }
}
