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
 * 基础物料库存Lib类
 * @author xiayuanjun@shopex.cn
 * @version 1.0
 */
class material_basic_material_stock extends ome_redis{

    // redis需要回滚的《商品冻结》
    static private $_redisFreezeRollbackList = [];
    // redis需要回滚的《商品库存》
    static private $_redisStoreRollbackList = [];

    // redis的《冻结/库存流水》
    static private $_redisFlow = [];

    // 初始化redis需要回滚的《商品冻结》和《商品库存》
    static public function initRedisMaterialStock()
    {
        self::_initRedisFreezeRollbackList();
        self::_initRedisStoreRollbackList();
    }

    // 初始化redis需要回滚的《商品冻结》
    static private function _initRedisFreezeRollbackList()
    {
        self::$_redisFreezeRollbackList = [];
        return true;
    }

    // 初始化redis需要回滚的《商品库存》
    static private function _initRedisStoreRollbackList()
    {
        self::$_redisStoreRollbackList = [];
        return true;
    }

    // 追加redis需要回滚的《商品冻结》
    static private function _setRedisFreezeRollbackList($freezeRollbackReq=[])
    {
        if ($freezeRollbackReq) {
            self::$_redisFreezeRollbackList[] = $freezeRollbackReq;
        }
        return true;
    }

    // 追加redis需要回滚的《商品库存》
    static private function _setRedisStoreRollbackList($storeRollbackReq=[])
    {
        if ($storeRollbackReq) {
            self::$_redisStoreRollbackList[] = $storeRollbackReq;
        }
        return true;
    }

    // 获取redis需要回滚的《商品冻结》
    static private function _getRedisFreezeRollbackList()
    {
        return self::$_redisFreezeRollbackList;
    }

    // 获取redis需要回滚的《商品库存》
    static private function _getRedisStoreRollbackList()
    {
        return self::$_redisStoreRollbackList;
    }

    // 删除《冻结/库存流水》并且 回滚redis的《商品冻结》和《商品库存》
    static public function rollbackRedisMaterialStock()
    {
        self::delRedisMaterialFlow();
        self::freezeRollbackInRedis();
        self::storeRollbackInRedis();
    }

    // 获取redis《冻结/库存流水》的hash表名
    public function getFlowHash($type = '')
    {
        if ($type == 'freeze') {
            return sprintf('%s#materialFreezeFlow', base_shopnode::node_id('ome'));

        } elseif ($type == 'store') {
            return sprintf('%s#materialStoreFlow', base_shopnode::node_id('ome'));

        } else {
            return '';
        }
    }

    // 临时保存redis《冻结流水》或者《库存流水》
    static private function _setRedisFlow($_key, $_field, $_value)
    {
        return true;
        return true;
        return true;
        $isRedis = parent::_connectRedis();
        if (!$isRedis) {
            return true;
        }
        // 只有在事务里才去保存临时流水，否则静态变量里的数据不会删除
        if (!kernel::database()->isInTransaction()) {
            return true;
        }
        $info = parent::$stockRedis->hget($_key, $_field);

        if ($info) {
            $_value = $info . ';' . $_value;
        } else {   
            $key = $_key . '-' . $_field;
            self::$_redisFlow[$key] = [
                'flowHash'  =>  $_key,
                'flowKey'   =>  $_field,
            ];
        }
        parent::$stockRedis->hset($_key, $_field, $_value);
    }

    // 删除redis《冻结流水》和redis《库存流水》
    static public function delRedisMaterialFlow()
    {
        $isRedis = parent::_connectRedis();
        if (!$isRedis) {
            return [true,'isRedis is false'];
        }
        if (!self::$_redisFlow) {
            return [true, 'materialStock redisFlow is []'];
        }

        foreach (self::$_redisFlow as $ro_k => $ro_v) {
            if ($ro_v['flowHash'] && $ro_v['flowKey']) {
                parent::$stockRedis->hdel($ro_v['flowHash'], $ro_v['flowKey']);
                unset(self::$_redisFlow[$ro_k]);
            }
        }
        return [true, 'succ'];
    }

    function __construct(){
        $this->_basicMaterialStockObj = app::get('material')->model('basic_material_stock');
    }

    /**
     *
     * 增加基础物料冻结数
     * @param Int $bm_id
     * @param Int $num
     * @return Boolean
     */
    public function freeze($bm_id, $num)
    {
        // 改用批量freezeBatch方法
        return false;
        return false;
        return false;

        $dateline   = time();
        $storeFreeze = "store_freeze=IFNULL(store_freeze,0)+".$num;

        $sql = 'UPDATE sdb_material_basic_material_stock SET '.$storeFreeze.', last_modified='. $dateline .',max_store_lastmodify='. $dateline .' 
                WHERE bm_id='.$bm_id;
        if($this->_basicMaterialStockObj->db->exec($sql)){
            $rs = $this->_basicMaterialStockObj->db->affect_row();
            if(is_numeric($rs) && $rs > 0){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
        
    }

    /**
     *
     * 增加基础物料冻结数
     * @param items=[
     *                  ['bm_id'=>1,'num'=>1,'branch_id'=>1,'obj_type'=>'对象类型',
     *      'bill_type'=>'业务类型','obj_id'=>'对象ID','obj_bn'=>'对象单号'],
     *                  ['bm_id'=>2,'num'=>1,'branch_id'=>1,'obj_type'=>'对象类型',
     *      'bill_type'=>'业务类型','obj_id'=>'对象ID','obj_bn'=>'对象单号'],
     *              ]
     * @param string $source 方法调用来源，一般入参__CLASS__.'::'.__FUNCTION__
     * @return [true/false, '']
     */
    public function freezeBatch($items, $source='')
    {
        // 改成调用redis高并发的方法，迭代掉本类的freeze方法
        $itemArr = [];
        foreach ($items as $key => $item) {
            if ($item['num']==0) {
                continue;
            }
            $itemArr[] = [
                'bm_id'     => $item['bm_id'],
                'sm_id'     => $item['sm_id'],
                'quantity'  => $item['num'], 

                'branch_id'     =>  $item['branch_id'] ? $item['branch_id'] : 0,
                'obj_type'      =>  $item['obj_type'],
                'bill_type'     =>  $item['bill_type'],
                'obj_id'        =>  $item['obj_id'],
                'obj_bn'        =>  $item['obj_bn'],
                // 'obj_item_id'   =>  $item['obj_item_id'],
            ];
        }

        $rs = [true, '增加基础物料冻结成功'];
        if ($itemArr) {
            $rs = self::freezeInRedis($itemArr, '+', $source);
        }
        return $rs;
    }


    /**
     *
     * 释放基础物料冻结数
     * @param Int $bm_id
     * @param int $num
     * @return Boolean
     */
    public function unfreeze($bm_id, $num)
    {
        // 改用批量unfreezeBatch方法
        return false;
        return false;
        return false;

        $dateline   = time();
        $storeFreeze = " store_freeze=IF((CAST(store_freeze AS SIGNED)-$num)>0,store_freeze-$num,0)";

        $sql = 'UPDATE sdb_material_basic_material_stock SET '.$storeFreeze.', last_modified='. $dateline .',max_store_lastmodify='. $dateline .' 
                WHERE bm_id='.$bm_id;
        if($this->_basicMaterialStockObj->db->exec($sql)){
            $rs = $this->_basicMaterialStockObj->db->affect_row();
            if(is_numeric($rs) && $rs > 0){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
        
    }


    /**
     *
     * 释放基础物料冻结数
     * @param items=[
     *                  ['bm_id'=>1,'num'=>1,'branch_id'=>1,'obj_type'=>'对象类型',
     *      'bill_type'=>'业务类型','obj_id'=>'对象ID','obj_bn'=>'对象单号'],
     *                  ['bm_id'=>2,'num'=>1,'branch_id'=>1,'obj_type'=>'对象类型',
     *      'bill_type'=>'业务类型','obj_id'=>'对象ID','obj_bn'=>'对象单号'],
     *              ]
     * @param string $source 方法调用来源，一般入参__CLASS__.'::'.__FUNCTION__
     * @return [true/false, '']
     */
    public function unfreezeBatch($items, $source='')
    {
        // 改成调用redis高并发的方法，迭代掉本类的unfreeze方法
        $itemArr = [];
        foreach ($items as $key => $item) {
            if ($item['num'] == 0) {
                continue;
            }
            $itemArr[] = [
                'bm_id'     => $item['bm_id'],
                'quantity'  => $item['num'], 

                'branch_id'     =>  $item['branch_id'] ? $item['branch_id'] : 0,
                'obj_type'      =>  $item['obj_type'],
                'bill_type'     =>  $item['bill_type'],
                'obj_id'        =>  $item['obj_id'],
                'obj_bn'        =>  $item['obj_bn'] ? $item['obj_bn'] : '',
                // 'obj_item_id'   =>  $obj_item_id,
            ];
        }

        $rs = [true, '释放基础物料冻结成功'];
        if ($itemArr) {
            $rs = self::freezeInRedis($itemArr, '-', $source);
        }
        return $rs;
    }
    
    /**
     *
     * 更新基础物料[库存]
     * @param intval $bm_id
     * @param intval $num
     * @param string $operator
     * @param type   $log_type
     * @return Boolean
     */
    function change_store($bm_id, $num, $operator='=')
    {
        // 改成调用change_store_batch的方法
        return false;
        return false;
        return false;

        $dateline   = time();
        $store      = '';
        switch($operator)
        {
            case '+':
                $store    = "store=IFNULL(store, 0)+". $num . ',';
                break;
            case '-':
                $store    = " store=IF((CAST(store AS SIGNED)-". $num .")>0, store-". $num .",0), ";
                break;
            case '=':
            default:
                $store    = "store=".$num . ',';
                break;
        }
        
        $sql    = "UPDATE ". DB_PREFIX ."material_basic_material_stock SET ". $store .'last_modified='. $dateline .',max_store_lastmodify='. $dateline .' 
                   WHERE bm_id='.$bm_id;
        if($this->_basicMaterialStockObj->db->exec($sql)){
            $rs = $this->_basicMaterialStockObj->db->affect_row();
            if(is_numeric($rs) && $rs > 0){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
        
    }

    /**
     *
     * 更新基础物料[库存]
     * @param items=[
     *                  ['bm_id'=>1,'num'=>1,'real_store_lastmodify'=>false/true,
     *      'branch_id'=>1,'iostock_bn'=>'出入库单号','negative_stock'=>false/true],
     *                  ['bm_id'=>2,'num'=>1,'real_store_lastmodify'=>false/true,
     *      'branch_id'=>1,'iostock_bn'=>'出入库单号','negative_stock'=>false/true],
     *              ]
     * @param string $operator + 或 - 
     * @param string $source 方法调用来源，一般入参__CLASS__.'::'.__FUNCTION__
     * @return [true/false, '']
     */
    function change_store_batch($items, $operator='', $source=[])
    {
        // 改成调用redis高并发的方法,迭代掉本类的change_store方法 (实际没有operator='='的情况)
        $itemArr = [];
        foreach ($items as $key => $item) {
            if ($item['num'] == 0) {
                continue;
            }
            $itemArr[] = [
                'bm_id'                 => $item['bm_id'],
                'quantity'              => $item['num'], 
                'real_store_lastmodify' => $item['real_store_lastmodify'] ? $item['real_store_lastmodify'] : false,
                'branch_id'             => $item['branch_id'],
                'iostock_bn'            => $item['iostock_bn'],
                'negative_stock'        => $item['negative_stock'] === true ? true : false,
            ];
        }

        $rs = [true, '更新基础物料库存成功'];
        if ($itemArr) {
            $rs = self::storeInRedis($itemArr, $operator, $source);
        }
        return $rs;
    }
    
    /**
     *
     * 修改基础物料[冻结库存]
     * @param intval $bm_id
     * @param intval $num
     * @param string $operator
     * @param type   $log_type
     * @return Boolean
     */
    function chg_product_store_freeze($bm_id, $num, $operator='=', $log_type='order')
    {
        // 改成调用chg_product_store_freeze_batch方法
        return false;
        return false;
        return false;

        $basicMaterialObj        = app::get('material')->model('basic_material');
        
        $dateline    = time();
        $store_freeze = '';
        $mark_no = uniqid();
        
        switch($operator)
        {
            case "+":
                $store_freeze = "store_freeze=IFNULL(store_freeze,0)+". $num .",";
                $action = '增加';
                break;
            case "-":
                $store_freeze = "store_freeze=IF((CAST(store_freeze AS SIGNED)-". $num .")>0,store_freeze-". $num .",0),";
                $action = '扣减';
                break;
            case "=":
            default:
                $store_freeze = "store_freeze=". $num .",";
                $action = '覆盖';
                break;
        }
        
        #修改库存
        $sql    = 'UPDATE '. DB_PREFIX .'material_basic_material_stock SET '.$store_freeze.'last_modified='.$dateline.',max_store_lastmodify='.$dateline.' 
                   WHERE bm_id='.$bm_id;
        if($this->_basicMaterialStockObj->db->exec($sql)){
            $rs = $this->_basicMaterialStockObj->db->affect_row();
            if(is_numeric($rs) && $rs > 0){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
        
    }

    /**
     *
     * 修改基础物料[冻结库存]
     * @param items=[['bm_id'=>1,'num'=>1],['bm_id'=>2,'num'=>3]]
     * @param string $operator + 或 - 
     * @param string $source 方法调用来源，一般入参__CLASS__.'::'.__FUNCTION__
     * @return [true/false, '']
     */
    function chg_product_store_freeze_batch($items, $operator='', $source='')
    {
        // 只有已经废弃的app/ome/lib/rpc在调用，如有需要，
        // 请调用本类 freezeBatch 和 unfreezeBatch 方法
        return false;
        return false;
        return false;
        // (实际没有operator='='的情况)
        // 改成调用redis高并发的方法,迭代掉本类的chg_product_store_freeze方法 
        // 改成调用redis高并发的方法,迭代掉本类的chg_product_store_freeze方法
        // 改成调用redis高并发的方法,迭代掉本类的chg_product_store_freeze方法
        $itemArr = [];
        foreach ($items as $key => $item) {
            $itemArr[] = [
                'bm_id'     => $item['bm_id'],
                'quantity'  => $item['num'], 
            ];
        }

        $rs = [true, '修改基础物料冻结成功'];
        if ($itemArr) {
            $rs = self::freezeInRedis($itemArr, $operator, $source);
        }
        return $rs;
    }
    
    /**
     * 初始化基础物料[库存数量]
     */
    public function initNullStore($bm_id)
    {
        $dateline    = 'UNIX_TIMESTAMP()';
        if($bm_id)
        {
            $sql = "UPDATE ". DB_PREFIX ."material_basic_material_stock SET store=0, last_modified='.$dateline.',max_store_lastmodify='.$dateline.' 
                    WHERE bm_id=" . $bm_id ." AND ISNULL(store) LIMIT 1";
            
            return $this->_basicMaterialStockObj->db->exec($sql);
        }
        else
        {
            return false;
        }
    }


    /**
     * 扣减冻结
     *
     * @params array $items,
     *         示例：[
     *                  ['bm_id'=>1,'quantity'=>1,'branch_id'=>1,'obj_type'=>'对象类型',
     *      'bill_type'=>'业务类型','obj_id'=>'对象ID','obj_bn'=>'对象单号'],
     *                  ['bm_id'=>2,'quantity'=>1,'branch_id'=>1,'obj_type'=>'对象类型',
     *      'bill_type'=>'业务类型','obj_id'=>'对象ID','obj_bn'=>'对象单号']
     *              ]
     * @params string $opt，可选值: +、-
     * @return void
     */
    static public function freezeInRedis($items, $opt, $source='')
    {
        $db      = kernel::database();
        $node_id = base_shopnode::node_id('ome');
     
        if (!in_array($opt, ['+', '-'])){
            return [false, '物料总冻结操作符错误：'.$opt];
        }
       
        if ($opt == '+') {
            $ratio = 1;
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_freeze_incr.lua');
            $title = '增加物料总冻结';
        } else {
            $ratio = -1;
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_freeze_decr.lua');
            $title = '扣减物料总冻结';
        }

        $flowHash = kernel::single('material_basic_material_stock')->getFlowHash('freeze');
        
        // todo 需要加参数验证
        $fItems = [];
        foreach ($items as $item) {
            if (!$item['bm_id']) {
                return [false, '参数错误-bm_id空：'.json_encode($item)];
            } elseif (!$item['quantity']) {
                $bm_bn = self::getbmBn($item['bm_id']);
                return [false, '参数错误-quantity空[bm_bn=>'.$bm_bn.']：'.json_encode($item)];
            }
            
            $key = sprintf('%s#stock:material:%s', $node_id, $item['bm_id']);

            $flowKey = sprintf('%s#%s#%s#%s#%s', $item['obj_type'], $item['bill_type'], $item['obj_id'], $opt, time());
            
            // 扣减冻结为负数
            $item['quantity'] = abs($item['quantity']) * $ratio;
            
            if (isset($fItems[$key])) {
                $fItems[$key]['quantity'] += $item['quantity'];
            } else {
                $fItems[$key] = $item;
            }
        }

        if (!$fItems) {
            return [true, '数据为空'];
        }

        // 根据product_id对数组进行升序排序
        uasort($fItems, function($a, $b) { 
            return $a['bm_id'] - $b['bm_id'];
        });

        $flowValue = [];
        foreach ($fItems as $_k => $_v) {
            $tmp = [
                0  =>  $_v['branch_id'],
                1  =>  $_v['bm_id'],
                2  =>  abs($_v['quantity']),
                // 3  =>  time(),
            ];
            $flowValue[] = implode(':', $tmp);
        }
        $flowValue = implode(';', $flowValue);
        
        $isRedis = parent::_connectRedis();
        $code = null;

        // 1. 更新Redis
        if ($isRedis) {

            $args = array_merge(array_keys($fItems), array_column($fItems, 'quantity'));

            list($code, $msg, $sku_id) = $rr = parent::$stockRedis->eval($lua, $args, count($fItems));

            if ($code === false) {
                return [false, $title.'失败：脚本异常'];
            }
            
            if ($code > 100) {
                $bm_bn = self::getbmBn($fItems[$sku_id]['bm_id']);
                return [false, '【'.$bm_bn.'】'.$title.'失败：'.$msg];
            }

            // 存redis流水
            self::_setRedisFlow($flowHash, $flowKey, $flowValue);
        }
        
        // 2. 如果Redis不存在，更新数据库
        if (!$isRedis || $code == 100){
            foreach ($fItems as $key => $item) {

                $dateline = 'UNIX_TIMESTAMP()';
                if ($opt == '+') {
                    $store_freeze = "store_freeze=IFNULL(store_freeze,0)+" . $item['quantity'] . ",";
                } else {
                    // opt='-'的时候，$item['quantity']为负，所以用+
                    /*
                    $store_freeze = "store_freeze=IF((CAST(store_freeze AS SIGNED)+" . $item['quantity'] . ")>0,store_freeze+". $item['quantity'] . ",0),";
                    */

                    // 修改成允许store_freeze减成负数
                    $store_freeze = "store_freeze=store_freeze+". $item['quantity'] . ",";
                }

                $sql = 'UPDATE `' . DB_PREFIX . 'material_basic_material_stock` SET ' . $store_freeze . 'last_modified=' . $dateline . ',max_store_lastmodify=' . $dateline . ' WHERE bm_id='.$item['bm_id'];

                if (!$db->exec($sql)){
                    $bm_bn = self::getbmBn($item['bm_id']);
                    return [false, '【'.$bm_bn.'】'.$title.'失败：'.$db->errorinfo()];
                }

                if (1 !== $db->affect_row()){
                    $bm_bn = self::getbmBn($item['bm_id']);
                    return [false, '【'.$bm_bn.'】'.$title.'失败：物料总冻结不足'];
                }
            }
        }

        // 3. 数据库更新成功，做SET
        if ($isRedis && $code == 100) {
            $args = [];

            $fBmIdArr = array_column($fItems, 'bm_id');
            $stockSql = "SELECT `bm_id`,`store`,`store_freeze` FROM `" . DB_PREFIX . "material_basic_material_stock` WHERE `bm_id` in ('".implode("','", $fBmIdArr)."')";
            $stockList = $db->select($stockSql);
            $stockList = array_column($stockList, null, 'bm_id');

            foreach ($fItems as $key => $item) {
                $row = $stockList[$item['bm_id']];
                if (!$row) {
                    continue;
                }
                $args[] = $row['store'].','.$row['store_freeze'];
            }

            if ($args) {
                $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_set.lua');

                $args = array_merge(array_keys($fItems), $args);

                parent::$stockRedis->eval($lua, $args, count($fItems));
            }
        }

        // 4. 如果走队列并没有更新数据库生成冻结流水，方便后台任务读取并更新
        if ($isRedis && $code != 100){
            $freezeQueueMdl = app::get('ome')->model('material_freeze_queue');
            foreach ($fItems as $key => $item) {
                $queue = [
                    'bm_id'      => $item['bm_id'],
                    'quantity'   => $item['quantity'],
                    'branch_id'  => $item['branch_id'],
                    'obj_type'   => $item['obj_type'],
                    'bill_type'  => $item['bill_type'],
                    'obj_id'     => $item['obj_id'],
                    'obj_bn'     => $item['obj_bn'],
                    'source'     => $source,
                    // 'obj_item_id'     => $item['obj_item_id'],
                ];
                
                $freezeQueueMdl->insert($queue);
            }
        }

        // 业务roback以后，需要对redis回滚，freezeRollbackReq是需要回滚的参数。
        if ($isRedis) {
            $freezeRollbackReq = [
                'items'     =>  $items,
                'opt'       =>  $opt == '+' ? '-' : '+', // 回滚需要逆向
                'source'    =>  $source, // __CLASS__.'::'.__FUNCTION__
            ];
            self::_setRedisFreezeRollbackList($freezeRollbackReq);
        }

        return [true, $title.'成功'];
    }

    static private function getbmBn($bm_id='')
    {
        if (!$bm_id) {
            return '未知商品';
        }
        $mdl  = app::get('material')->model('basic_material');
        $info = $mdl->db_dump(['bm_id'=>$bm_id], 'material_bn');

        !$info && $info = ['material_bn'=>$bm_id];
        return $info['material_bn'];
    }


    /**
     * 扣减库存
     *
     * @params array $items,
     *         示例：[
     *                  ['bm_id'=>1,'quantity'=>1],
     *                  ['bm_id'=>2,'quantity'=>1,'real_store_lastmodify'=>true,'negative_stock'=>false/true]
     *              ]
     * @params string $opt，可选值: +、-
     * @return void
     */
    static public function storeInRedis($items, $opt, $source='')
    {
        $node_id = base_shopnode::node_id('ome');
     
        if (!in_array($opt, ['+', '-'])){
            return [false, '物料总库存操作符错误：'.$opt];
        }
       
        if ($opt == '+') {
            $ratio = 1;
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_incr.lua');
            $title = '增加物料总库存';
        } else {
            $ratio = -1;
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_decr.lua');
            $title = '扣减物料总库存';
        }

        $flowHash = kernel::single('material_basic_material_stock')->getFlowHash('store');
        
        // todo 需要加参数验证
        $fItems = [];
        foreach ($items as $item) {
            if (!$item['bm_id']) {
                return [false, '参数错误-bm_id空：'.json_encode($item)];
            } elseif (!$item['quantity']) {
                $bm_bn = self::getbmBn($item['bm_id']);
                return [false, '参数错误-quantity空[bm_bn=>'.$bm_bn.']：'.json_encode($item)];
            }
            
            $key = sprintf('%s#stock:material:%s', $node_id, $item['bm_id']);

            $flowKey = sprintf('%s#%s#%s', $item['iostock_bn'], $opt, time());
            
            // 扣减冻结为负数
            $item['quantity'] = abs($item['quantity']) * $ratio;
            
            if (isset($fItems[$key])) {
                $fItems[$key]['quantity'] += $item['quantity'];
                // if ($item['negative_stock'] === true){
                //     $fItems[$key]['negative_stock'] = 'true';
                // }
            } else {
                $fItems[$key] = $item;
                // $fItems[$key]['negative_stock'] = ($item['negative_stock'] && $item['negative_stock'] === true) ? 'true' : 'false';
                $fItems[$key]['negative_stock'] = 'true';
            }

            // 是否需要更新real_store_lastmodify字段
            if ($item['real_store_lastmodify']) {
                $fItems[$key]['real_store_lastmodify'] = true;
            }
        }

        if (!$fItems) {
            return [true, '数据为空'];
        }

        // 根据product_id对数组进行升序排序
        uasort($fItems, function($a, $b) { 
            return $a['bm_id'] - $b['bm_id'];
        });

        $flowValue = [];
        foreach ($fItems as $_k => $_v) {
            $tmp = [
                0  =>  $_v['branch_id'],
                1  =>  $_v['bm_id'],
                2  =>  abs($_v['quantity']),
                // 3  =>  time(),
            ];
            $flowValue[] = implode(':', $tmp);
        }
        $flowValue = implode(';', $flowValue);

        // 1. 更新数据库        
        $db = kernel::database();
        foreach ($fItems as $key => $item) {

            $dateline = 'UNIX_TIMESTAMP()';
            if ($opt == '+') {
                $store = "store=IFNULL(store, 0)+" . $item['quantity'] . ',';
                
                // 库存增加时，记录“库存最后添加时间”
                $store .= " inc_store_lastmodify=" . $dateline . ",";
            } else {
                // opt='-'的时候，$item['quantity']为负，所以用+
                if ($item['negative_stock'] == 'true'){
                    $store = "store=store+" . $item['quantity'] . ",";
                } else {
                    $store = "store=IF((CAST(store AS SIGNED)+" . $item['quantity'] . ")>0, store+" . $item['quantity'] . ",0),";
                }
            }

            if ($item['real_store_lastmodify']) {
                $store .= " real_store_lastmodify=" . $dateline . ",";
            }

            $sql = "UPDATE `". DB_PREFIX ."material_basic_material_stock` SET ". $store .'last_modified='. $dateline .',max_store_lastmodify='. $dateline .' WHERE bm_id='.$item['bm_id'];

            if (!$db->exec($sql)){
                $bm_bn = self::getbmBn($item['bm_id']);
                return [false, '【'.$bm_bn.'】'.$title.'失败：'.$db->errorinfo()];
            }

            if (1 !== $db->affect_row()){
                $bm_bn = self::getbmBn($item['bm_id']);
                return [false, '【'.$bm_bn.'】'.$title.'失败：物料总库存不足'];
            }
        }
        
        $isRedis = parent::_connectRedis();
        $code = null;

        // 2. 更新Redis
        if ($isRedis) {

            $args = array_merge(array_keys($fItems), array_column($fItems, 'quantity'), array_column($fItems, 'negative_stock'));

            list($code, $msg, $sku_id) = $rr = parent::$stockRedis->eval($lua, $args, count($fItems));

            if ($code === false) {
                return [false, $title.'失败：脚本异常'];
            }
            
            if ($code > 100) {
                $bm_bn = self::getbmBn($fItems[$sku_id]['bm_id']);
                return [false, '【'.$bm_bn.'】'.$title.'失败：'.$msg];
            }

            // 存redis流水
            self::_setRedisFlow($flowHash, $flowKey, $flowValue);
        }

        // 3. Redis数据不存在，做初始化SET
        if ($isRedis && $code == 100) {
            $args = [];
            foreach ($fItems as $key => $item) {
                $row = $db->selectrow("SELECT `store`,`store_freeze` FROM `" . DB_PREFIX . "material_basic_material_stock` WHERE `bm_id`={$item['bm_id']}");
                
                if ($row) {
                    !$row['store'] && $row['store'] = 0;
                    !$row['store_freeze'] && $row['store_freeze'] = 0;
                } else {
                    continue;
                }
                $args[] = $row['store'].','.$row['store_freeze'];
            }

            if ($args) {
                $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_set.lua');

                $args = array_merge(array_keys($fItems), $args);

                parent::$stockRedis->eval($lua, $args, count($fItems));
            }
        }

        // 业务rollback以后，需要对redis回滚，storeRollbackReq是需要回滚的参数。
        if ($isRedis) {
            $storeRollbackReq = [
                'items'     =>  $items,
                'opt'       =>  $opt == '+' ? '-' : '+', // 回滚需要逆向
                'source'    =>  $source, // __CLASS__.'::'.__FUNCTION__
            ];
            self::_setRedisStoreRollbackList($storeRollbackReq);
        }

        return [true, $title.'成功'];
    }


    /**
     * redis冻结回滚
     */
    static public function freezeRollbackInRedis()
    {
        if (!self::_getRedisFreezeRollbackList()) {
            return [true, 'materialStock redisFreezeRollbackList is []'];
        }

        foreach (self::_getRedisFreezeRollbackList() as $ro_value) {
            $items  = $ro_value['items'];
            $opt    = $ro_value['opt'];
            self::_freezeRollbackInRedis($items, $opt);
        }

        self::_initRedisFreezeRollbackList();

        return [true, 'redis商品冻结回滚成功'];
    }


    /**
     * redis冻结回滚
     *
     * @params array $items,
     *         示例：[['bm_id'=>1,'quantity'=>1],['bm_id'=>2,'quantity'=>1]]
     * @params string $opt，可选值: +、-
     * @return void
     */
    static private function _freezeRollbackInRedis($items, $opt)
    {
        $node_id = base_shopnode::node_id('ome');

        if (!in_array($opt, ['+', '-'])){
            return [false, '(回滚)物料总冻结操作符错误：'.$opt];
        }

        if ($opt == '+') {
            $ratio = 1;
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_freeze_incr.lua');
            $title = '(回滚)增加物料总冻结';
        } else {
            $ratio = -1;
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_freeze_decr.lua');
            $title = '(回滚)扣减物料总冻结';
        }

        // todo 需要加参数验证
        $fItems = [];
        foreach ($items as $item) {
            if (!$item['bm_id']) {
                return [false, '(回滚)参数错误-bm_id空：'.json_encode($item)];
            } elseif (!$item['quantity']) {
                $bm_bn = self::getbmBn($item['bm_id']);
                return [false, '(回滚)参数错误-quantity空[bm_bn=>'.$bm_bn.']：'.json_encode($item)];
            }
            
            $key = sprintf('%s#stock:material:%s', $node_id, $item['bm_id']);

            // 扣减冻结为负数
            $item['quantity'] = abs($item['quantity']) * $ratio;
            
            if (isset($fItems[$key])) {
                $fItems[$key]['quantity'] += $item['quantity'];
            } else {
                $fItems[$key] = $item;
            }
        }

        $isRedis = parent::_connectRedis();
        $code = null;

        if (!$isRedis) {
            return [true, '冻结回滚，连接redis失败'];
        }

        // 1. 更新Redis
        if ($isRedis) {
            $args = array_merge(array_keys($fItems), array_column($fItems, 'quantity'));

            list($code, $msg, $sku_id) = $rr = parent::$stockRedis->eval($lua, $args, count($fItems));

            if ($code === false) {
                return [false, $title.'失败：脚本异常'];
            }
            
            if ($code > 100) {
                return [false, '(回滚)'.$fItems[$sku_id]['bm_id'].'】'.$title.'失败：'.$msg];
            }
        }

        /* // 不能做set处理，因为调用redis的rollback的地方有的在db->rollback之前有的在之后
        // 3. 数据库更新成功，做SET
        if ($isRedis && $code == 100) {
            $args = [];
            foreach ($fItems as $key => $item) {
                $row = $db->selectrow("SELECT `store`,`store_freeze` FROM `" . DB_PREFIX . "material_basic_material_stock` WHERE `bm_id`={$item['bm_id']}");
                
                $args[] = $row['store'].','.$row['store_freeze'];
            }
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_set.lua');

            $args = array_merge(array_keys($fItems), $args);

            parent::$stockRedis->eval($lua, $args, count($fItems));
        }
        */

        return [true, $title.'成功'];
    }

    /**
     * redis库存回滚
     */
    static public function storeRollbackInRedis()
    {
        if (!self::_getRedisStoreRollbackList()) {
            return [true, 'materialStock redisStoreRollbackList is []'];
        }

        foreach (self::_getRedisStoreRollbackList() as $ro_value) {
            $items  = $ro_value['items'];
            $opt    = $ro_value['opt'];
            self::_storeRollbackInRedis($items, $opt);
        }

        self::_initRedisStoreRollbackList();
        return [true, 'redis商品库存回滚成功'];
    }

    /**
     * redis库存回滚
     *
     * @params array $items,
     *         示例：[['bm_id'=>1,'quantity'=>1],['bm_id'=>2,'quantity'=>1]]
     * @params string $opt，可选值: +、-
     * @return void
     */
    static private function _storeRollbackInRedis($items, $opt)
    {
        $node_id = base_shopnode::node_id('ome');
     
        if (!in_array($opt, ['+', '-'])){
            return [false, '(回滚)物料总库存操作符错误：'.$opt];
        }
       
        if ($opt == '+') {
            $ratio = 1;
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_incr.lua');
            $title = '(回滚)增加物料总库存';
        } else {
            $ratio = -1;
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_decr.lua');
            $title = '(回滚)扣减物料总库存';
        }

        
        // todo 需要加参数验证
        $fItems = [];
        foreach ($items as $item) {
            if (!$item['bm_id']) {
                return [false, '(回滚)参数错误-bm_id空：'.json_encode($item)];
            } elseif (!$item['quantity']) {
                $bm_bn = self::getbmBn($item['bm_id']);
                return [false, '(回滚)参数错误-quantity空[bm_bn=>'.$bm_bn.']：'.json_encode($item)];
            }

            $key = sprintf('%s#stock:material:%s', $node_id, $item['bm_id']);

            // 扣减冻结为负数
            $item['quantity'] = abs($item['quantity']) * $ratio;

            if (isset($fItems[$key])) {
                $fItems[$key]['quantity'] += $item['quantity'];
            } else {
                $fItems[$key] = $item;
            }
        }
        
        $isRedis = parent::_connectRedis();
        $code = null;

        if (!$isRedis) {
            return [true, '库存回滚，连接redis失败'];
        }

        // 1. 更新Redis
        if ($isRedis) {
            $args = array_merge(array_keys($fItems), array_column($fItems, 'quantity'));

            list($code, $msg, $sku_id) = $rr = parent::$stockRedis->eval($lua, $args, count($fItems));

            if ($code === false) {
                return [false, $title.'失败：脚本异常'];
            }
            
            if ($code > 100) {
                return [false, '(回滚)【'.$fItems[$sku_id]['bm_id'].'】'.$title.'失败：'.$msg];
            }
        }

        /*// 不能做set处理，因为调用redis的rollback的地方有的在db->rollback之前有的在之后
        // 3. 数据库更新成功，做SET
        if ($isRedis && $code == 100) {
            $args = [];
            foreach ($fItems as $key => $item) {
                $row = $db->selectrow("SELECT `store`,`store_freeze` FROM `" . DB_PREFIX . "material_basic_material_stock` WHERE `bm_id`={$item['bm_id']}");
                
                $args[] = $row['store'].','.$row['store_freeze'];
            }
            $lua = file_get_contents(app::get('ome')->app_dir.'/lua/material_store_set.lua');

            $args = array_merge(array_keys($fItems), $args);

            parent::$stockRedis->eval($lua, $args, count($fItems));
        }
        */

        return [true, $title.'成功'];
    }


    /**
     * 在redis中获取冻库存和结数
     *
     * @params array $product_info,
     *         示例：['bm_id' => 666]
     * 
     * @return void
     */
    static public function storeFromRedis($product_info = [])
    {
        if (!$product_info['bm_id']) {
            return [false, 'bm_id is null', []];
        }

        $isRedis = parent::_connectRedis();
        $code = null;

        // 1. 从Redis获取
        if ($isRedis) {

            $node_id = base_shopnode::node_id('ome');
            $key     = sprintf('%s#stock:material:%s', $node_id, $product_info['bm_id']);

            $redis_quantity = parent::$stockRedis->hgetall($key);

            if ($redis_quantity) {
                $return = [
                    'store'         =>  $redis_quantity['store'],
                    'store_freeze'  =>  $redis_quantity['store_freeze'],
                ];
                return [true, 'succ', $return];

            } else {

                $isRedis = false;
            }
        }

        // 2. 如果Redis不存在，或者redis中数据不存在，查mysql
        if (!$isRedis){
            $db  = kernel::database();
            $row = $db->selectrow("SELECT `store`,`store_freeze` FROM `" . DB_PREFIX . "material_basic_material_stock` WHERE `bm_id`={$product_info['bm_id']}");
            if ($row) {

                return [true, 'succ', $row];

            } else {

                return [false, 'get dbstore is fail', []];
            }
        }

        return [false, 'storeFromRedis unknown error', []];
    }

}
