<?php
/**
 * 基础物料增加库存时,每小时定时运行,自动审核库存不足的订单
 *
 * 逻辑：
 * 1) 读取60分钟内“库存增加”的基础物料；
 * 2) 获取库存不足的未审核订单；
 * 3) 每小时匹配有库存的基础物料，进行自动审核订单。
 *
 * 注意：定时任务本身由外部调度高频触发，本类内部通过kvstore控制60分钟频率。
 */
class ome_autotask_timer_autoconfirmorderstockinc
{
    /**
     * 60分钟执行一次
     */
    const FREQUENCY_SECONDS = 3600;

    /**
     * 并发锁（缓存锁）
     */
    const LOCK_KEY = 'inc_store_autoconfirmorder_lock';

    /**
     * 最后执行时间（kvstore）
     */
    const LAST_EXEC_TIME_KEY = 'inc_store_autoconfirmorder';

    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '512M');
        
        $now = time();
        
        // 60分钟频控（即使本次无数据也会更新lasttime，避免高频空跑）
        base_kvstore::instance('ome')->fetch(self::LAST_EXEC_TIME_KEY, $lastExecTime);
        $lastExecTime = $lastExecTime ? (int)$lastExecTime : 0;
        if ($lastExecTime && ($now - $lastExecTime) < self::FREQUENCY_SECONDS) {
            $error_msg = 'its not time yet，lastExecTime：'. $lastExecTime;
            return true;
        }
        
        // 防并发
        $isRun = cachecore::fetch(self::LOCK_KEY);
        if ($isRun) {
            $error_msg = 'running';
            return true;
        }
        cachecore::store(self::LOCK_KEY, 'running', self::FREQUENCY_SECONDS);
        
        // 指定时间前的时间戳
        $lastmodify = $now - self::FREQUENCY_SECONDS;
        
        // 查询订单时间范围
        $start_time = strtotime('-7 days', strtotime('today')); // 7天前零点的时间戳
        $end_time = $now - 1800; // 30分钟前的时间戳
        
        // 库存不足的二进制
        $storeCode = omeauto_auto_const::__STORE_CODE;
        
        try {
            $db = kernel::database();
            $orderItemMdl = app::get('ome')->model('order_items');
            
            // 第一步：查询60分钟内有“库存增加”的基础物料行数。
            $sql = "SELECT count(*) AS nums FROM sdb_material_basic_material_stock WHERE inc_store_lastmodify >= {$lastmodify} AND store>store_freeze";
            $row = $db->selectrow($sql);
            if($row['nums'] <= 0){
                // kvstore
                base_kvstore::instance('ome')->store(self::LAST_EXEC_TIME_KEY, $now);
                
                $error_msg = '没有获取到“库存增加”的基础物料';
                return true;
            }
            
            // 第二步：根据库存不足状态码（omeauto_auto_const::__STORE_CODE）查询订单行数，并且订单是未审核的。
            $sql = "SELECT count(*) AS nums FROM sdb_ome_orders WHERE process_status IN('unconfirmed', 'splitting') ";
            $sql .= " AND status='active' AND createtime>={$start_time} AND createtime<{$end_time} ";
            $sql .= "AND (auto_status & {$storeCode} = {$storeCode}) ";
            $row = $db->selectrow($sql);
            if($row['nums'] <= 0){
                // kvstore
                base_kvstore::instance('ome')->store(self::LAST_EXEC_TIME_KEY, $now);
                
                $error_msg = '没有获取到“库存不足”的订单';
                return true;
            }
            
            $orderCountNums = $row['nums'];
            
            // 第三步：获取60分钟内有“库存增加”的基础物料列表
            $sql = "SELECT bm_id,store,store_freeze FROM sdb_material_basic_material_stock WHERE inc_store_lastmodify >= {$lastmodify} AND store>store_freeze LIMIT 0, 5000";
            $tempList = $db->select($sql);
            if (empty($tempList)) {
                // kvstore
                base_kvstore::instance('ome')->store(self::LAST_EXEC_TIME_KEY, $now);
                
                $error_msg = '没有可执行的基础物料';
                return true;
            }
            
            // format
            $bmList = [];
            foreach ($tempList as $bmKey => $bmVal)
            {
                $bm_id = $bmVal['bm_id'];
                
                // 可用库存数量
                $avail_qty = $bmVal['store'] - $bmVal['store_freeze'];
                
                $bmList[$bm_id] = ['bm_id'=>$bm_id, 'avail_qty'=>$avail_qty];
            }
            
            // bm_id
            $bmIds = array_keys($bmList);
            
            // 第四步：获取符合条件的订单
            $execOrderIds = [];
            
            // exec
            $limit = 100;
            $pageCount = ceil($orderCountNums / $limit);
            for($page_i=1; $page_i <= $pageCount; $page_i++)
            {
                // offset
                $offset = ($page_i - 1) * $limit;
                
                // select
                $order_sql = "SELECT order_id,order_bn,archive,is_fail,abnormal,pause,pay_status,ship_status,is_not_combine FROM sdb_ome_orders WHERE ";
                $order_sql .= " process_status IN('unconfirmed', 'splitting') AND status='active' AND createtime>={$start_time} AND createtime<{$end_time} ";
                $order_sql .= "AND (auto_status & {$storeCode} = {$storeCode}) ";
                $order_sql .= " LIMIT ". $offset .", ". $limit;
                $orderList = $db->select($order_sql);
                if(empty($orderList)){
                    continue;
                }
                
                // order_id
                $orderIds = array_column($orderList, 'order_id');
                
                // order_items
                $itemList = $orderItemMdl->getList('item_id,order_id,product_id,bn,nums,split_num', ['order_id'=>$orderIds, 'delete'=>'false']);
                if(empty($itemList)){
                    continue;
                }
                
                // format
                $orderItems = [];
                foreach ($itemList as $itemKey => $itemVal)
                {
                    $order_id = $itemVal['order_id'];
                    $product_id = $itemVal['product_id'];
                    
                    // check
                    if($itemVal['split_num'] >= $itemVal['nums']){
                        continue;
                    }
                    
                    // 检查基础物料ID是否在有效列表中
                    if(!in_array($product_id, $bmIds)){
                        continue;
                    }
                    
                    // 需要审核的基础物料数量
                    $itemVal['true_num'] = $itemVal['nums'] - $itemVal['split_num'];
                    
                    $orderItems[$order_id][$product_id] = $itemVal;
                }
                
                // 订单行循环
                foreach ($orderList as $orderKey => $orderVal)
                {
                    $order_id = $orderVal['order_id'];
                    
                    // 检查订单状态
                    if($orderVal['archive'] != '0' || $orderVal['pause'] != 'false'){
                        continue;
                    }
                    
                    if($orderVal['is_fail'] != 'false' || $orderVal['abnormal'] != 'false'){
                        continue;
                    }
                    
                    if($orderVal['pay_status'] != '1'){
                        continue;
                    }
                    
                    if($orderVal['is_not_combine'] != 0){
                        continue;
                    }
                    
                    // 仅处理未发货/部分发货的订单
                    if(!in_array((int)$orderVal['ship_status'], [0, 2])){
                        continue;
                    }
                    
                    // 检查订单明细是否存在
                    if(!isset($orderItems[$order_id])){
                        continue;
                    }
                    
                    $execOrderIds[$order_id] = $order_id;
                }
            }
            
            // 检查是否有可执行的订单
            if(empty($execOrderIds)){
                // kvstore
                base_kvstore::instance('ome')->store(self::LAST_EXEC_TIME_KEY, $now);
                
                $error_msg = '没有可执行的订单';
                return true;
            }
            
            // 第五步：检验可用库存数量，是否符合审核订单的基础物料购买数量，如果订单不符合，则注销掉：unset($execOrderIds[$order_id]);
            foreach ($execOrderIds as $order_id => $tmp)
            {
                // check
                if(!isset($orderItems[$order_id]) || empty($orderItems[$order_id])){
                    // unset
                    unset($execOrderIds[$order_id]);
                    
                    continue;
                }
                
                // 汇总该订单在“库存增加物料”上的需求数量（按bm_id汇总）
                $needMap = [];
                foreach ($orderItems[$order_id] as $bm_id => $itemVal)
                {
                    // check
                    if(!isset($itemVal['true_num'])){
                        continue;
                    }
                    
                    if($itemVal['true_num'] <= 0){
                        continue;
                    }
                    
                    if (!isset($needMap[$bm_id])) {
                        $needMap[$bm_id] = 0;
                    }
                    
                    $needMap[$bm_id] += $itemVal['true_num'];
                }
                
                // check
                if (empty($needMap)) {
                    // unset
                    unset($execOrderIds[$order_id]);
                    
                    continue;
                }
                
                // 校验可用库存是否满足该订单需求
                foreach ($needMap as $bm_id => $needQty)
                {
                    $availQty = isset($bmList[$bm_id]) ? (int)$bmList[$bm_id]['avail_qty'] : 0;
                    if ($availQty < $needQty) {
                        // unset
                        unset($execOrderIds[$order_id]);
                        
                        break;
                    }
                }
            }
            
            // check
            if (empty($execOrderIds)) {
                // kvstore
                base_kvstore::instance('ome')->store(self::LAST_EXEC_TIME_KEY, $now);
                
                $error_msg = '符合基础物料库存的订单为0条';
                return true;
            }
            
            // 延迟自动审核订单
            $line_i = 0;
            foreach ($execOrderIds as $order_id)
            {
                $line_i++;
                
                // 递增延迟的秒数
                $second = 300 + ($line_i * 3);
                
                // params
                $params = [
                    'op_type' => 'timing_confirm',
                    'timing_time' => time() + $second, // 延迟5分钟
                    'memo' => '基础物料增加库存，自动审核库存不足的订单',
                    'cnAuto' => 'true', // 防止系统设置里未开启[是否开启系统自动审核]
                ];
                $result = kernel::single('ome_order')->auto_order_combine($order_id, $params);
            }
            
            // kvstore
            base_kvstore::instance('ome')->store(self::LAST_EXEC_TIME_KEY, $now);
            
            $error_msg = '符合条件的订单：'. count($execOrderIds) .'条，系统会触发延迟自动审核订单';
            
            return true;
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            return false;
        } finally {
            cachecore::delete(self::LOCK_KEY);
        }
    }
}

