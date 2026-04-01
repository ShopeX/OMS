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
 * 更新库存逻辑
 *
 * @author chenping<chenping@shopex.cn>
 * @version $2012-8-6 17:22Z
 */
class inventorydepth_logic_stock extends inventorydepth_logic_abstract
{
    /* 当前的执行时间 */
    public static $now;

    /* 更新商品的时间 */
    private $readStoreLastmodify = 0;

    /**
     * undocumented class variable
     *
     * @var string
     **/
    private $_errmsg = '';

    /* 执行的间隔时间 */
    const intervalTime = 40; //修改为40秒
    
    /**
     * 唯品会店铺购买车库存占用
     *
     * @var array
     */
    public static $_vopCartStocks = array();
    
    function __construct($app)
    {
        $this->app = $app;
        self::$now = time();
    }

    public function start()
    {
        @set_time_limit(0);
        @ini_set('memory_limit','1024M');
        ignore_user_abort(true);
        
        base_kvstore::instance('inventorydepth/apply/stock')->fetch('apply-lastexectime',$lastExecTime);
        if($lastExecTime && ($lastExecTime+self::intervalTime)>self::$now) {
            return false;
        }
        base_kvstore::instance('inventorydepth/apply/stock')->store('apply-lastexectime',self::$now);
        
        //获取货品
        list($products, $shopIds) = $this->getChgProducts();
        base_kvstore::instance('inventorydepth/apply')->store('read_store_lastmodify',self::$now);
        if(empty($products)){
            return false;
        }
        
        $this->do_sync_products_stock($products, $shopIds);
    }

    public function getStock($product,$shop_id,$shop_bn,$node_type='') 
    {
        // 数据检查product需要是数组、sales_material_type需要存在
        if (!is_array($product) || !isset($product['sales_material_bn'])) {
            return false;
        }

        $product_bn = $product['sales_material_bn'];

        // 读取商品要执行的规则
        try {
            $stock = $this->dealWithRegu($product,$shop_id,$shop_bn);
            if ($stock === false) { return false; }
        } catch (\Exception $e) {
            // 发送库存计算异常报警
            kernel::single('monitor_event_notify')->addNotify('inventory_calc_error', [
                'datetime' => date('Y-m-d H:i:s'),
                'product_bn' => $product['sales_material_bn'] ?? 'unknown',
                'shop_id' => $shop_id,
                'shop_name' => $shop_bn,
                'error_message' => $e->getMessage(),
                'error_location' => 'getStock method',
            ]);
            return false;
        }

        // 受1号店回写库存限制
        if($node_type == 'yihaodian' && $stock['quantity'] >= 3000){
            $stock['quantity'] = 2999;
        }

        // 查询增量库存
        if (kernel::single('inventorydepth_sync_set')->isModeSupportInc($node_type)) {        
            $stockLogMdl   = app::get('ome')->model('api_stock_log');
            $last_quantity = $stockLogMdl->getLastStockLog($shop_id, $product_bn);
            if ($last_quantity) {
                $stock['inc_quantity'] = $stock['quantity'] - $last_quantity['store'];
            }
        }

        return $stock;
    }

    /**
     * 计算库存
     * @param array $product 商品信息
     * @param int $shop_id 店铺ID
     * @param string $shop_bn 店铺编号
     * @return array|false 库存信息 ['bn' => '商品编码', 'quantity' => '库存数量', 'actual_stock' => '实际库存', 'memo' => '备注', 'regulation' => '规则']
     */
    private function dealWithRegu($product,$shop_id,$shop_bn) 
    {
        // 数据检查product需要是数组、sales_material_type需要存在
        if (!is_array($product) || !isset($product['sales_material_bn'])) {
            return false;
        }

        $stockCalLib = kernel::single('inventorydepth_calculation_salesmaterial');

        $pbn = $product['sales_material_bn'];

        $sales_material = kernel::single('inventorydepth_calculation_basicmaterial')->getSalesMaterial($pbn, $shop_id);
        if(empty($sales_material)) {
            return false;
        }

        $sm_id = $sales_material['sm_id'];
        
        $stock = [];
        $detail = [];

        $regu = $this->getRegu($shop_id); 

        foreach($regu as $r){

            if(empty($r['regulation'])) continue;
            
            // 应用范围是否满足 BEGIN
            if ($r['shop_id'][0] != '_ALL_' && !in_array($shop_id, $r['shop_id'])) {
                continue;
            }

            if ($r['apply_object_type'] == '3') {
                if (!$product['class_id'] || !is_array($r['apply_object_filter']) || !is_array($r['apply_object_filter']['customer_classify']) || !in_array($product['class_id'], $r['apply_object_filter']['customer_classify'])) {
                    continue;
                }
            } else {
                if ($r['apply_goods'][0] != '_ALL_' && !in_array($sm_id, $r['apply_goods'])) {
                    continue;
                }
            }

            if ($r['shop_sku_id'] && !in_array($product['shop_sku_id'], $r['shop_sku_id'])) {
                continue;
            }
            // 应用范围是否满足 END
            
            //判断是否满足规则
            $params = array(
                'shop_product_bn'   => $pbn,
                'shop_bn'           => $shop_bn,
                'shop_id'           => $shop_id,
                'regulation_code'   => $r['regulation']['bn'],
            );
            
            // 添加专用供货仓信息到参数中
            if (!empty($r['supply_branch_id'])) {
                $params['supply_branch_id'] = $r['supply_branch_id'];
            }
            if (!empty($r['supply_branch_bn'])) {
                $params['supply_branch_bn'] = $r['supply_branch_bn'];
            }

            foreach ($r['regulation']['content']['filters'] as $filter) {
                $allow_update = $this->check_condition($filter,$params);

                if(!$allow_update){ continue 2;}
            }

            if ($r['regulation']['content']['stockupdate'] != 1) { 
                return false;
            }

            $quantity = $stockCalLib->formulaRun($r['regulation']['content']['result'],$params,$detail);

            if ($quantity === false){ continue; }

            list($store_freeze, )   = $stockCalLib->get_shop_freeze($params);
            list($actual_stock,)    = $stockCalLib->get_actual_stock($params);
            
            $stock = [
                'bn'            => $pbn,
                'quantity'      => $quantity,
                'actual_stock'  => $actual_stock,
                'memo'          => json_encode([
                    'store_freeze' => $store_freeze,
                    'last_modified' => time(),
                ]),
                'regulation'    => [
                    '规则名称'      => $r['regulation']['heading'],
                    '规则内容'      => $r['regulation']['content'],
                    '规则ID'       => $r['regulation']['regulation_id'],
                    'detail'       => $detail,
                ],
            ];

            if ($product['shop_sku_id']) {
                $stock['shop_sku_id'] = $product['shop_sku_id'];
            }

            // 如果分仓回写
            if ($r['is_sync_subwarehouse'] == '1') {
                // 获取店铺对应供货仓
                $warehouseStocks = $this->processSubwarehouseSync($params, $r);
                $stock['warehouse_stock'] = $warehouseStocks;
            }

            // 如果门店回写
            if ($r['is_sync_store'] == '1') {
                $storeStocks = $this->processStoreSync($params, $r);
                $stock['store_stock'] = $storeStocks;
            }

            return $stock;
        }

        return false;
    }

    /**
     * 处理分仓回写逻辑
     * 
     * @param array $params 计算参数
     * @param array $r 规则信息
     * @return array 分仓库存数组
     */
    private function processSubwarehouseSync($params, $r)
    {
        $warehouseStocks = [];

        // 从参数中获取必要信息
        $pbn = $params['shop_product_bn'];
        $shop_bn = $params['shop_bn'];
        $shop_id = $params['shop_id'];
        
        // 获取店铺对应供货仓
        $shopBranches = kernel::single('inventorydepth_shop')->getBranchByshop($shop_bn);
        if (!$shopBranches) {
            return $warehouseStocks;
        }
        
        // 获取库存计算库
        $stockCalLib = kernel::single('inventorydepth_calculation_salesmaterial');
        
        // 获取店铺对应供货仓
        foreach ($shopBranches as $branch_id => $branch_bn) {
            $params['supply_branch_bn'] = [$branch_id => $branch_bn];
            $detail = [];

            $quantity = $stockCalLib->formulaRun($r['regulation']['content']['result'], $params, $detail);

            list($store_freeze, )   = $stockCalLib->get_shop_freeze($params);
            list($actual_stock,)    = $stockCalLib->get_actual_stock($params);

            $warehouseStocks[] = [
                'bn'            => $pbn,
                'quantity'      => $quantity,
                'actual_stock'  => $actual_stock,
                'branch_bn'     => $branch_bn,
                'memo'          => json_encode([
                    'store_freeze' => $store_freeze,
                    'last_modified' => time(),
                ]),
                'regulation'    => [
                    '规则名称'      => $r['regulation']['heading'],
                    '规则内容'      => $r['regulation']['content'],
                    'detail'       => $detail,
                ],
                'shop_sku_id'   => isset($params['shop_sku_id']) ? $params['shop_sku_id'] : '',
            ];
        }
        
        return $warehouseStocks;
    }

    /**
     * 处理门店库存回写逻辑
     * 
     * @param array $params 计算参数
     * @param array $r 规则信息
     * @return array 门店库存数组
     */
    private function processStoreSync($params, $r)
    {
        $storeStocks = [];
        
        // 从参数中获取必要信息
        $pbn = $params['shop_product_bn'];
        $shop_id = $params['shop_id'];
        
        // 获取店铺对应的门店关系
        $shopOnOfflineModel = app::get('ome')->model('shop_onoffline');
        $storeRelations = $shopOnOfflineModel->getList('off_id', array('on_id' => $shop_id));
        
        if (empty($storeRelations)) {
            return $storeStocks;
        }
        
        // 获取门店ID列表
        $storeIds = array_column($storeRelations, 'off_id');
        
        // 从o2o_store表获取门店信息
        $o2oStoreModel = app::get('o2o')->model('store');
        $stores = $o2oStoreModel->getList('store_id,branch_id,store_bn', array('store_id|in' => $storeIds,'status' => '1'));
        
        if (empty($stores)) {
            return $storeStocks;
        }
        
        // 获取库存计算库
        $stockCalLib = kernel::single('inventorydepth_calculation_salesmaterial');
        
        // 遍历每个门店计算库存
        foreach ($stores as $store) {
            $params['supply_branch_bn'] = [$store['branch_id'] => $store['store_bn']];

            $detail = [];

            $quantity = $stockCalLib->formulaRun($r['regulation']['content']['result'], $params, $detail);

            list($store_freeze, )   = $stockCalLib->get_shop_freeze($params);
            list($actual_stock,)    = $stockCalLib->get_actual_stock($params);

            $storeStocks[] = [
                'bn'            => $pbn,
                'quantity'      => $quantity,
                'actual_stock'  => $actual_stock,
                'store_code'    => $store['store_bn'],
                'memo'          => json_encode([
                    'store_freeze' => $store_freeze,
                    'last_modified' => time(),
                ]),
                'regulation'    => [
                    '规则名称'      => $r['regulation']['heading'],
                    '规则内容'      => $r['regulation']['content'],
                    'detail'       => $detail,
                ],
                'shop_sku_id'   => isset($params['shop_sku_id']) ? $params['shop_sku_id'] : '',
            ];
        }
        
        return $storeStocks;
    }

    public function set_stock_quantity($shop_id,$key,$data) {
        $this->stock_quantity[$shop_id][$data['product_id']] = $data['quantity'];
    }

    /**
     * @description 获取指定店铺的所有规则
     * @access public
     * @param string $shop_id 店铺ID
     * @return array 规则数组
     */
    public function getRegu($shop_id) {
        if(!$this->regu) {
            $filter = array(
                'start_time|sthan' =>self::$now,
                'end_time|bthan' =>self::$now,
                'using' =>'true',
                'al_exec' => 'false',
                'condition' => 'stock',
                'type'             => ['0','1','2'],
                'filter_sql' => "(shop_id='_ALL_' || FIND_IN_SET('{$shop_id}',shop_id) )",
            );
            $this->regu = $this->app->model('regulation_apply')->getList('*',$filter,0,-1,'type desc,priority desc');

            foreach($this->regu as $key=>$value){
                $this->regu[$key]['shop_id'] = explode(',',$value['shop_id']);
                
                //所有销售物料sm_id
                $this->regu[$key]['apply_goods'] = explode(',',$value['apply_goods']);

                $this->regu[$key]['shop_sku_id'] = $value['shop_sku_id'] ? explode(',',$value['shop_sku_id']) : [];
                
                // 处理专用供货仓字段
                if (!empty($value['supply_branch_id'])) {
                    $this->regu[$key]['supply_branch_id'] = explode(',', $value['supply_branch_id']);
                    $this->regu[$key]['supply_branch_id'] = array_filter(array_map('trim', $this->regu[$key]['supply_branch_id']));
                    // 转成仓库编码
                    $branchModel = app::get('ome')->model('branch');
                    $branches = $branchModel->getList('branch_bn', ['branch_id|in' => $this->regu[$key]['supply_branch_id'], 'check_permission' => 'false']);
                    $this->regu[$key]['supply_branch_bn'] = array_column($branches, 'branch_bn');
                } else {
                    $this->regu[$key]['supply_branch_id'] = [];
                    $this->regu[$key]['supply_branch_bn'] = [];
                }


                $apply_object_filter = $value['apply_object_filter'] ? @json_decode($value['apply_object_filter'], true) : [];
                $this->regu[$key]['apply_object_filter'] = $apply_object_filter ?: [];
                
                $this->regu[$key]['regulation'] = &$regulation[$value['regulation_id']];
                
                //回写方式
                $this->regu[$key]['sync_mode'] = $value['sync_mode'];
            }

            if($regulation){
                $rr = $this->app->model('regulation')->getList('*',array('regulation_id'=>array_keys($regulation),'using'=>'true'));
                foreach($rr as $r){
                    $regulation[$r['regulation_id']] = $r;
                }
            }
        }

        return $this->regu;
    }

    /**
     * @description 获取5分钟内库存变更的货品
     * @access public
     * @param void
     * @return void
     */
    public function getChgProducts(){
        base_kvstore::instance('inventorydepth/apply')->fetch('read_store_lastmodify',$read_store_lastmodify);
        if (!$read_store_lastmodify || $read_store_lastmodify>self::$now) {
            $read_store_lastmodify = self::$now-self::intervalTime;
            base_kvstore::instance('inventorydepth/apply')->store('read_store_lastmodify',$read_store_lastmodify);
        }
        $filter = array(
            'max_store_lastmodify|between' => array(
                0 => $read_store_lastmodify,
                1 => self::$now,
            )
        );

        $this->_errmsg .= sprintf('s:%s,e:%s,',date('Y-m-d H:i:s', $read_store_lastmodify),date('Y-m-d H:i:s',self::$now));

        $this->readStoreLastmodify = $read_store_lastmodify;
        
        $shopFilter = array(
            'filter_sql' =>'{table}node_id is not null and {table}node_id !=""',
        );
        $shops = $this->app->model('shop')->getList('shop_id,delivery_mode',$shopFilter);
        $yjdfShop = [];
        $selfShop = [];
        foreach($shops as $val) {
            if($val['delivery_mode'] == 'self') {
                $selfShop[] = $val['shop_id'];
            }
            if($val['delivery_mode'] == 'shopyjdf') {
                $yjdfShop[] = $val['shop_id'];
            }
        }
        
        $products = array();
        $queue_limit = 200;
        $salesMaterialObj = app::get('material')->model('sales_material');
        $basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        $luckybagLib = kernel::single('material_luckybag');
        
        //根据变化的基础物料找到变化的销售物料
        $bm_ids = $basicMaterialStockObj->getList('bm_id', $filter);
        $bm_ids = array_map('current', $bm_ids);
        if($bm_ids){
            #获取绑定的销售物料sm_id
            $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
            $sm_ids = $salesBasicMaterialObj->getList('sm_id,bm_id', array('bm_id'=>$bm_ids));
            
            // 只取在售状态的销售物料
            $filter_sm_ids = [];
            
            if(!empty($sm_ids)){
                foreach($sm_ids as $var_si){
                    if(!in_array($var_si["sm_id"],$filter_sm_ids)){
                        $filter_sm_ids[] = $var_si["sm_id"];
                    }
                }
            }
            //多选一类型的sm_ids获取
            $mdl_ma_pickone_ru = app::get('material')->model('pickone_rules');
            $rs_pickone = $mdl_ma_pickone_ru->getlist("sm_id",array("bm_id"=>$bm_ids));
            if(!empty($rs_pickone)){
                foreach($rs_pickone as $var_rp){
                    if(!in_array($var_rp["sm_id"],$filter_sm_ids)){
                        $filter_sm_ids[] = $var_rp["sm_id"];
                    }
                }
            }
            
            //批量通过基础物料bm_id获取关联的销售物料sm_id
            $error_msg = '';
            $luckySmIds = $luckybagLib->batchGetSmidByBmid($bm_ids, $error_msg);
            if($luckySmIds){
                foreach ($luckySmIds as $luckyKey => $lucky_sm_id)
                {
                    if(!in_array($lucky_sm_id, $filter_sm_ids)){
                        $filter_sm_ids[] = $lucky_sm_id;
                    }
                }
            }
            if(!empty($filter_sm_ids)){
                $filter = [
                    'sm_id' => $filter_sm_ids,
                    'visibled' => 1,//在售
                ];
                $products = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn, sales_material_type,shop_id,class_id', $filter);
            }

            if(count($products) > $queue_limit){ //走队列
                $filter_sm_ids = $this->getNeedWriteSmIds($products);
                $products = [];
                $queue_title = "定时变化库存自动回写";
                $queue_sm_ids = array_chunk($filter_sm_ids, $queue_limit);
                foreach($queue_sm_ids as $var_qsi){
                    $params = array(
                        "sm_ids"                => $var_qsi,
                        'shop_ids'              => $selfShop,
                        'delivery_mode'         => 'self',
                        'read_store_lastmodify' => $read_store_lastmodify,
                        'visibled' => 1,//在售
                    );
                    kernel::single('inventorydepth_queue')->timed_stock_sync_queue($queue_title,$params);
                }
            }
        }
        
        //shopyjdf
        if($yjdfShop && app::get('dealer')->is_installed()) {
            $yjdfSmIds = app::get('dealer')->model('sales_basic_material')->getList('sm_id',  array('bm_id'=>$bm_ids));
            $yjdfSm = app::get('dealer')->model('sales_material')->getList('sm_id,shop_id', ['sm_id'=>array_filter(array_column($yjdfSmIds, 'sm_id'))]);
            $queue_title = "定时变化库存自动回写";
            $queue_sm = array_chunk($yjdfSm, $queue_limit);
            foreach($queue_sm as $var_qsi){
                $params = array(
                    "sm_ids"                => array_column($var_qsi, 'sm_id'),
                    'shop_ids'              => array_filter(array_column($var_qsi, 'shop_id')),
                    'delivery_mode'         => 'shopyjdf',
                    'read_store_lastmodify' => $read_store_lastmodify,
                );
                kernel::single('inventorydepth_queue')->timed_stock_sync_queue($queue_title,$params);
            }
        }
        
        $this->_errmsg .= sprintf('apply_sm:%s,',implode('、', array_column($products, 'sales_material_bn')));
        return [$products, $selfShop];
    }

    public function getNeedWriteSmIds($products)
    {
        $skus = app::get('inventorydepth')->model('shop_skus')->getList('distinct shop_product_bn',array('shop_product_bn'=>array_column($products, 'sales_material_bn'), 'request'=>'true'));
        $skus = array_column($skus, null, 'shop_product_bn');
        $filter_sm_ids = [];
        foreach($products as $key => $val){
            if(isset($skus[$val['sales_material_bn']])){
                unset($products[$key]);
                $filter_sm_ids[] = $val['sm_id'];
            }
        }
        $filter = array('product_bn'=>array_column($products, 'sales_material_bn'),'msg|noequal'=>'货号未找到');
        $shop = app::get('ome')->model('shop')->getList('shop_id',array('node_type'=>'website','tbbusiness_type'=>'BAOZUN'));
        if($shop){
            $filter['shop_id|notin'] = array_column($shop, 'shop_id');
        }
        $skus = app::get('ome')->model('api_stock_log')->getlist('distinct product_bn', $filter);
        $skus = array_column($skus, null, 'product_bn');
        foreach($products as $key => $val){
            if(isset($skus[$val['sales_material_bn']])){
                unset($products[$key]);
                $filter_sm_ids[] = $val['sm_id'];
            }
        }
        $skus = app::get('ome')->model('api_stock_log')->getlist('distinct product_bn',array('product_bn'=>array_column($products, 'sales_material_bn')));
        $skus = array_column($skus, null, 'product_bn');
        foreach($products as $key => $val){
            if(!isset($skus[$val['sales_material_bn']])){
                unset($products[$key]);
                $filter_sm_ids[] = $val['sm_id'];
            }
        }
        return $filter_sm_ids;
    }
    
    public function do_sync_products_stock($products, $shopIds)
    {
        if(empty($products) || empty($shopIds)){
            return;
        }
        
        $products_normal = [];
        foreach ($products as $key => $val){
            if (!in_array($val['sales_material_type'], ['2','5','7'])) {
                $products_normal[] = $val;
            }
        }

        // 初始化物料
        kernel::single('inventorydepth_calculation_basicmaterial')->init($products);
        kernel::single('inventorydepth_calculation_salesmaterial')->init($products);

        // 获取已经连接的店铺
        $filter = array(
            'filter_sql' =>'{table}node_id is not null and {table}node_id !=""',
        );
        $filter['shop_id'] = $shopIds;
        $shops = $this->app->model('shop')->getList('shop_id,shop_bn,node_type,business_type,delivery_mode,config',$filter);
        foreach($shops as $k => $shop) {
            $request = kernel::single('inventorydepth_shop')->getStockConf($shop['shop_id']);
            if($request != 'true') {
                unset($shops[$k]);
            }
        }
        if(empty($shops)) {
            return;
        }

        foreach($shops as $shop)
        {
            // 是否安装drm模块
            if (app::get('drm')->is_installed()) {
                //获取淘管店铺信息
                $channelShopObj = app::get('drm')->model('channel_shop');
                $binds = array();
                $binds = $channelShopObj->getList('channel_id',array('shop_id'=>$shop['shop_id']),0,1);
                if(is_array($binds) && !empty($binds)) {
                    continue;
                }
            }
            
            // 店铺未开启回写
            $request = kernel::single('inventorydepth_shop')->getStockConf($shop['shop_id']);
            if($request != 'true') { continue; }

            // kernel::single('inventorydepth_offline_queue')->store_update($products_normal, $shop);
            
            // 仓库为该店铺供货
            $bra = kernel::single('inventorydepth_shop')->getBranchByshop($shop['shop_bn']);
            if (!$bra) { continue; }
            
            // 读取已经匹配，但不需要回写的货品
            $unRequest = kernel::single('inventorydepth_sync_set')->getUnRequestBn($shop, $products);

            //默认方式
            $this->syncProductStocks($shop, $products, $unRequest);

        }
    }
    
    /**
     * 回写商品库存
     * 
     * @param array $shop 单个店铺信息
     * @param array $products_normal 基础物料数组
     * @param array $unRequest 不用回写的商品数组
     * @return bool
     */
    public function syncProductStocks($shop, $products, $unRequest)
    {

        // 清除上一个店铺的规则
        unset($this->regu);
        
        $skusObj = app::get('inventorydepth')->model('shop_skus');

        // 获取当前店铺的所有规则应用和应用里所有的shop_sku_id
        $applyAllShopSkuIdArr = $this->getApplyAllShopSkuId($shop['shop_id']);
        
        $sales_bn_list = array();

        $shop_sku_id_list = [];
        $shop_sku_id = []; // 初始化 shop_sku_id 变量
        
        $stocks = [];

        // 获取销售物料对应的shop_sku_id
        $smBnArr = array_column($products, 'sales_material_bn');
        $_shopSkuList = $skusObj->getList('shop_product_bn,shop_sku_id,shop_iid', array('shop_id'=>$shop['shop_id'], 'shop_product_bn'=>$smBnArr, 'request'=>'true'));
        $shopSkuList = [];
        foreach ($_shopSkuList as $_v) {
            $_v['shop_sku_id'] = $_v['shop_sku_id'] ? $_v['shop_sku_id'] : $_v['shop_iid'];
            $shopSkuList[$_v['shop_product_bn']][] = $_v['shop_sku_id'];
        }
        
        foreach($products as $product)
        {            
            if ($unRequest && in_array($product['sales_material_bn'],$unRequest)) { continue; }

            // 销售物料有对应的shop_sku_id,且在规则应用中，根据shop_sku_id去匹配规则应用，然后去计算库存
            if ($shopSkuList[$product['sales_material_bn']] 
                && count(array_intersect($shopSkuList[$product['sales_material_bn']], array_keys($applyAllShopSkuIdArr)))>0) 
            {
                foreach ($shopSkuList[$product['sales_material_bn']] as $shopSkuId) {

                    // 未配置指定SKU的商品，跳过
                    if (!isset($applyAllShopSkuIdArr[$shopSkuId])) {
                        continue;
                    }

                    $product['shop_sku_id'] = $shopSkuId;

                    //获取销售物料的可用库存数量
                    $st = $this->getStock($product,$shop['shop_id'],$shop['shop_bn'],$shop['node_type']);
                    if ($st === false) { continue; }
                    
                    // 如果是分仓回写
                    if ($st['warehouse_stock']) {
                        $stocks = array_merge($stocks, $st['warehouse_stock']);

                        unset($st['warehouse_stock']);
                    }
                    
                    // 如果是门店回写
                    if ($st['store_stock']) {
                        $stocks = array_merge($stocks, $st['store_stock']);

                        unset($st['store_stock']);
                    }
                    
                    // 如果既不是分仓回写也不是门店回写，使用默认库存
                    $stocks[] = $st;
                }

            } else {

                //获取销售物料的可用库存数量
                $st = $this->getStock($product,$shop['shop_id'],$shop['shop_bn'],$shop['node_type']);
                if ($st === false) { continue; }

                
                // 如果是分仓回写
                if ($st['warehouse_stock']) {
                    $stocks = array_merge($stocks, $st['warehouse_stock']);

                    unset($st['warehouse_stock']);
                }
                
                // 如果是门店回写
                if ($st['store_stock']) {
                    $stocks = array_merge($stocks, $st['store_stock']);

                    unset($st['store_stock']);
                }
                
                // 如果既不是分仓回写也不是门店回写，使用默认库存
                $stocks[] = $st;
            }
            
            //普通商品列表
            $sales_bn_list[] = $product['sales_material_bn'];

            // 初始化 shop_sku_id_list 数组
            if (!isset($shop_sku_id_list[$product['sales_material_bn']])) {
                $shop_sku_id_list[$product['sales_material_bn']] = [];
            }
        }
        
        $stocks = $this->resetChangeStocks($stocks, $shop, $sales_bn_list, $shop_sku_id_list);
        
        //check shop
        if(in_array($shop['node_type'], array('vop'))){
            //检查唯品会(库存占用+熔断值),防止提示超卖风险报警
            $stocks = $this->checkVopCircuitStock($stocks, $shop);
        }else{
            //减掉唯品会平台购物车库存占用
            $stocks = $this->subtractVopCartStock($stocks, $shop);
        }
        
        //回写个数
        $stock_nums = 50;
        if($shop['node_type'] == 'dewu'){
            $stock_nums = 10;
        }elseif($shop['node_type'] == 'aikucun'){
            //爱库存限制每次最多30个
            $stock_nums = 30;
        }
        
        //往前端回写库存
        if ($stocks) {
            $new_stocks = array_chunk($stocks, $stock_nums);
            foreach ($new_stocks as $stock) {
                kernel::single('inventorydepth_shop')->doStockRequest($stock,$shop['shop_id']);
            }
        }
        
        return $stocks;
    }
    
    /**
     * [库存级]根据库存规则读取商品库存
     * 
     * @param string $pbn
     * @param string $shop_id
     * @param string $shop_bn
     * @param array $apply_regulation
     * @return array
     */
    public function dealWarehouseWithRegu($pbn, $shop_id, $shop_bn, &$apply_regulation=array(), &$shop_sku_id=[], $regu = [], $shopSkuId = '')
    {
        $product = kernel::single('inventorydepth_stock_products')->fetch_products($pbn);
        $product_id = $product['sm_id'];
        $msg = '';
        
        if (!$regu) {
            $regu = $this->getRegu($shop_id);
        }
        // 如果传了shop_sku_id，检查是否可以命中配置的shop_sku_id
        $is_hit_shop_sku_id = false;
        if ($shopSkuId) {
            foreach ($regu as $_v) {
                if ($_v['shop_sku_id'] && in_array($shopSkuId, $_v['shop_sku_id'])) {
                    $is_hit_shop_sku_id = true;
                    break;
                }
            }
        }
        
        //stocks
        $storeList = array();
        foreach($regu as $r)
        {
            //不是按仓库回写,则跳过
            if($r['sync_mode'] != 'warehouse'){
                continue;
            }
            
            if(empty($r['regulation'])) continue;
            
            //check店铺和商品是否满足规则
            $shop_flag = false;
            if($r['shop_id'][0] == '_ALL_'){
                $shop_flag = true;
            }elseif(in_array($shop_id, $r['shop_id'])){
                $shop_flag = true;
            }
            
            $goods_flag = false;
            if($r['apply_goods'][0] == '_ALL_'){
                $goods_flag = true;
            }elseif(in_array($product_id, $r['apply_goods'])){
                $goods_flag = true;
            }
            
            if($shop_flag && $goods_flag){
                //定时回写
                if ($r['style'] == 'fix') {
                    $this->reguUpdateFilter['id'][] = $r['id'];
                }
                
                //判断是否满足规则
                $params = array(
                        'shop_product_bn' => $pbn,
                        'shop_bn' => $shop_bn,
                        'shop_id' => $shop_id,
                );
                
                foreach ($r['regulation']['content']['filters'] as $filter)
                {
                    $allow_update = $this->check_condition($filter,$params);
                
                    if(!$allow_update){ continue 2;}
                }
                
                if ($r['regulation']['content']['stockupdate'] != 1) { return false;}

                if ($is_hit_shop_sku_id) {
                    if (!in_array($shopSkuId, $r['shop_sku_id'])) {
                        continue;
                    }
                } elseif (!$is_hit_shop_sku_id && $shopSkuId && array_filter($r['shop_sku_id'])) {
                    continue;
                }
                
                //按仓库纬度回写
                $params['sync_mode'] = 'warehouse'; //按仓库纬度回写标识
                $type = 'warehouse_'; //todo:要带入_下划线
                
                $branchQuantity = kernel::single('inventorydepth_stock')->formulaRun($r['regulation']['content']['result'], $params, $msg, $type);
                if ($branchQuantity === false){ continue; }
                
                if (!empty($this->product_list)) {
                    $new_product= array_column($this->product_list,null,'sm_id');
                    //如果是规则等于订单的并且 stock_status == false continue
                    if (isset($new_product[$product_id]['exit_io']) && !$new_product[$product_id]['exit_io'] && $r['style'] == 'order_change') {
                        return false;
                    }
                }
                
                $storeList = $branchQuantity; //一维数组,以仓库编码为下标
                
                $apply_regulation = $r['regulation'];

                $shop_sku_id = $r['shop_sku_id']; // 回写哪些skuid
                
                break;
            }
        }
        
        return empty($storeList) ? false : $storeList;
    }

    public function get_errmsg()
    {
        return $this->_errmsg;
    }

    public function set_readStoreLastmodify($readStoreLastmodify)
    {
        $this->readStoreLastmodify = $readStoreLastmodify;

        return $this;
    }

    public function resetChangeStocks($stocks, $shop, $sales_bn_list, $shop_sku_id_list=[]) {
        $skusObj = app::get('inventorydepth')->model('shop_skus');
        #剔除请求成功得相同数据物料
        $stocks = $this->eliminateSameNumber($stocks, $shop, $sales_bn_list);
        if(empty($stocks)) {
            return [];
        }

        foreach ($stocks as $_k => $_v) {
            if ($_v['shop_sku_id']) {
                $stocks[$_k]['sku_id'] = $_v['shop_sku_id'];
            }
        }

        //[抖音平台]加入sku_id字段
        if(in_array($shop['node_type'], array('luban'))
            || kernel::single('inventorydepth_sync_set')->isUseSkuid($shop)
        ){
            $tempList = $skusObj->getList('shop_product_bn,shop_sku_id,shop_iid,id,request', array('shop_id'=>$shop['shop_id'], 'shop_product_bn'=>$sales_bn_list));
            
            $skusList = array();
            foreach ((array)$tempList as $key => $val)
            {
                if($val['request'] != 'true') {
                    continue;
                }

                // 如果配置了skuid，判断是否在skuid list里，不在则不回写
                // if (isset($shop_sku_id_list[$val['shop_product_bn']]) && array_filter($shop_sku_id_list[$val['shop_product_bn']]) && !in_array($val['shop_sku_id'], $shop_sku_id_list[$val['shop_product_bn']])) {
                //     continue;
                // }
                $shop_product_bn = $val['shop_product_bn'];
                
                //一个货号对应多个sku_id的场景
                $skusList[$shop_product_bn][] = $val;
            }
            
            $stockList = array();
            if($stocks && $skusList){
                foreach ($stocks as $key => $val)
                {
                    $shop_product_bn = $val['bn'];
                    
                    if($skusList[$shop_product_bn]){
                        foreach ($skusList[$shop_product_bn] as $skuKey => $skuVal)
                        {
                            if ($val['sku_id'] && $skuVal['shop_sku_id'] != $val['sku_id'] && $skuVal['shop_iid'] != $val['sku_id']) {
                                continue;
                            }

                            $tmp = $val;
                            $tmp['sku_id'] = $val['sku_id'] == $skuVal['shop_iid'] ? '' : $skuVal['shop_sku_id'];

                            if (in_array($shop['node_type'], array('vop'))) {
                                $tmp['barcode'] = $skuVal['shop_sku_id'];
                            }
                            $tmp['num_iid'] = $skuVal['shop_iid'];
                            if($val['branch_bn']) {
                                $plateSet = app::get('inventorydepth')->model('shop_skustockset')->db_dump(['branch_bn'=>$val['branch_bn'], 'skus_id'=>$skuVal['id']], 'stock_only');
                                if($plateSet) {
                                    $tmp['stock_only'] = $plateSet['stock_only'];
                                }
                            }

                            // 京东分区库存回写
                            if ($skuVal['stock_model'] == 'PARTITION') {
                                $tmp['stockModel'] = 'POP_PARTITION';
                                $tmp['storeId'] = '0';
                            }

                            $stockList[] = $tmp;
                        }
                    }else{
                        $stockList[] = $val;
                    }
                }
                
                //重新赋值
                $stocks = $stockList;
            }
            
            //去除下标
            $stocks = array_values($stocks);
            
            unset($tempList, $skusList, $stockList);
        } elseif (in_array($shop['node_type'], array('vop'))) {
            // 判断是否为唯品会省仓，如果是省仓，找到省仓对应关系

            // $shop = [shop_id,shop_bn,node_type,business_type]

            // 获取商品条码
            $materialCodeLib = kernel::single('material_codebase');
            foreach ($stocks as $_k => $_v) {
                $barcode = $materialCodeLib->getBarcodeBySmbn($_v['bn'], $shop['shop_id']);
                $stocks[$_k]['barcode']= $barcode; // 回传匹配店铺资源的商品用barcode去匹配
            }

            $tmp_stocks = [];
            list($stocks, $tmp_stocks) = [$tmp_stocks, $stocks];

            $branchBnArr = array_column($tmp_stocks, 'branch_bn');
            if (!$branchBnArr) {
                return $tmp_stocks;
            }
            $branchList  = app::get('ome')->model('branch')->getList('*', ['branch_bn|in' => $branchBnArr, 'check_permission' => 'false']);
            $branchList  = array_column($branchList, null, 'branch_bn');

            // 仓库关联的店铺
            $branchRelationList = app::get('ome')->getConf('shop.branch.relationship');
            $branchRelationList = $branchRelationList[$shop['shop_bn']];

            $branchIdArr = array_keys($branchRelationList);
            $relation    = app::get('ome')->model('branch_relation')->getList('*', ['branch_id|in' => $branchIdArr, 'type' => 'vopjitx']);
            if ($relation) {
                $relation = array_column($relation, 'relation_branch_bn', 'branch_id');

                // 获取唯品会省仓列表
                $warehouseMdl  = app::get('console')->model('warehouse');
                $warehouseList = $warehouseMdl->getList('*', ['branch_bn|in' => $relation, 'warehouse_type' => '2']);
                $warehouseList = array_column($warehouseList, null, 'branch_bn');

                /*
                // 获取商品条码
                $materialCodeLib = kernel::single('material_codebase');
                $materCodeList   = $materialCodeLib->getBarcodeBybn(array_column($tmp_stocks, 'bn'));
                */

                foreach ($tmp_stocks as $k => $v) {
                    $branch_id      = $branchList[$v['branch_bn']]['branch_id'];
                    $vop_branch_bn  = $relation[$branch_id];
                    $warehouse_type = $warehouseList[$vop_branch_bn]['warehouse_type'];
                    $cooperation_no = $warehouseList[$vop_branch_bn]['cooperation_no'];
                    if ($vop_branch_bn && $warehouse_type == '2' && $cooperation_no) {
                        $stocks[$k]                     = $v;
                        $stocks[$k]['warehouse_code']   = $vop_branch_bn;
                        $stocks[$k]['warehouse_type']   = $warehouse_type;
                        $stocks[$k]['cooperation_no']   = $cooperation_no;
                        // $stocks[$k]['barcode']          = $materCodeList[$v['bn']] ? $materCodeList[$v['bn']] : ''; // 回传匹配店铺资源的商品用barcode去匹配
                        $stocks[$k]['warehouse_flag']   = '1'; // 仓库标识 0：全国逻辑仓或7大仓 1：省仓 不填默认0
                    }
                }
                $stocks = array_values($stocks);
            }

            // 如果stocks是空，说明没有有效的省仓，继续返回原始数据
            if (!$stocks) {
                list($stocks, $tmp_stocks) = [$tmp_stocks, $stocks];
            }
        }
        return $stocks;
    }

    public function eliminateSameNumber($stocks, $shop, $sales_bn_list) {
        $stockApi = [];
        $stockApiModel = app::get('ome')->model('api_stock_log');
        foreach ($stockApiModel->getList('shop_id,product_bn,store,actual_stock,msg,last_modified,status,shop_sku_id',array('product_bn'=>$sales_bn_list, 'shop_id'=>$shop['shop_id'])) as $value) {
            // $index = $value['shop_id'] . "-" .$value['product_bn'] . "-" . $value['shop_sku_id'];
            $index = $value['shop_id'] . "-" .$value['product_bn'];
            $stockApi[$index] = $value;
        }
        foreach($stocks as $k => $st) {
            // 临时判断，如果有shop_sku_id，且shop_sku_id有值，则不过滤
            if ($st['shop_sku_id']) {
                continue;
            }
            $stockapi_code = $shop['shop_id'] . "-" . $st['bn'];
            if(isset($stockApi[$stockapi_code]['actual_stock'])
                && $stockApi[$stockapi_code]['actual_stock'] == $st['actual_stock']
                && $stockApi[$stockapi_code]['status'] == 'success'
                && (
                    #如果规则为常量,则没有规则说明，需发起请求
                    $st['regulation']['detail']
                )
                && !kernel::single('inventorydepth_stock')->getNeedUpdateSku($shop['shop_id'], $st['bn'])
            ) {
                unset($stocks[$k]);
            }
        }
        return $stocks;
    }
    // 获取当前店铺的所有规则应用和应用里所有的shop_sku_id
    public function getApplyAllShopSkuId($shop_id)
    {
        unset($this->regu); // 先清除上一个店铺的规则

        $regu = $this->getRegu($shop_id);

        $applyAllShopSkuIdArr = [];
        foreach ($regu as $_v) {
            if ($_v['shop_sku_id'] && array_filter($_v['shop_sku_id'])) {
                foreach ($_v['shop_sku_id'] as $skuId) {
                    $applyAllShopSkuIdArr[$skuId][] = $_v['id'];
                }
            }
        }

        return $applyAllShopSkuIdArr;
    }
    
    /**
     * 减掉唯品会店铺购物车的库存占用(目前为购物车+未支付订单占用的库存值)
     *
     * @param $stocks
     * @param $shop
     * @return void
     */
    public function subtractVopCartStock($stocks, $shopInfo)
    {
        $vopLib = kernel::single('inventorydepth_shop_vop');
        
        //shop_id
        $shop_bn = $shopInfo['shop_bn'];
        $error_msg = '';
        
        //检查是否需要减掉唯品会店铺购物车的库存占用
        $vopShops = $vopLib->isSubtractVopCartStock($shopInfo, $error_msg);
        if(!$vopShops){
            return $stocks;
        }
        
        //按唯品会店铺纬度获取购物车的库存占用
        foreach ($vopShops as $vopKey => $vopInfo)
        {
            $vop_shop_bn = $vopInfo['shop_bn'];
            
            //cache
            $cartStocks = array();
            if(isset(self::$_vopCartStocks[$vop_shop_bn])){
                $cartStocks = self::$_vopCartStocks[$vop_shop_bn];
            }
            
            //stock
            $barcodes = array();
            foreach ($stocks as $stockKey => $stockInfo)
            {
                $barcode = $stockInfo['barcode'];
                
                //check
                if(empty($barcode)){
                    continue;
                }
                
                //已经获取过库存,跳过
                if(isset($cartStocks[$barcode])){
                    continue;
                }
                
                $barcodes[$barcode] = $barcode;
            }
            
            //check
            if(empty($barcodes)){
                continue;
            }
            
            //批量获取唯品会商品库存
            $skuStocks = $vopLib->getSkuCartFreezeStocks($vopInfo, $barcodes);
            if($skuStocks['rsp'] == 'succ' && $skuStocks['data']){
                foreach ($skuStocks['data'] as $stockKey => $skuStockInfo)
                {
                    $barcode = $skuStockInfo['barcode'];
                    
                    //check
                    if(empty($barcode)){
                        continue;
                    }
                    
                    //current_hold
                    if(isset($skuStockInfo['current_hold'])){
                        self::$_vopCartStocks[$vop_shop_bn][$barcode] = $skuStockInfo;
                    }
                }
            }
        }
        
        //check
        if(empty(self::$_vopCartStocks)){
            return $stocks;
        }
        
        //format
        foreach ($stocks as $stockKey => $stockInfo)
        {
            $barcode = $stockInfo['barcode'];
            
            //memo
            if(isset($stockInfo['memo']) && $stockInfo['memo']){
                $memoInfo = json_decode($stockInfo['memo'], true);
            }else{
                $memoInfo = array();
            }
            
            //check
            if(!isset(self::$_vopCartStocks[$shop_bn])){
                $memoInfo['current_hold'] = '';
                $stocks[$stockKey]['memo'] = json_encode($memoInfo);
                
                continue;
            }
            
            if(!isset(self::$_vopCartStocks[$shop_bn][$barcode])){
                $memoInfo['current_hold'] = '';
                $stocks[$stockKey]['memo'] = json_encode($memoInfo);
                
                continue;
            }
            
            //current_hold
            $current_hold = intval(self::$_vopCartStocks[$shop_bn][$barcode]['current_hold']);
            
            //quantity
            if($current_hold > 0){
                if($stockInfo['quantity'] >= $current_hold){
                    $stocks[$stockKey]['quantity'] = $stockInfo['quantity'] - $current_hold;
                }else{
                    $stocks[$stockKey]['quantity'] = 0;
                }
            }
            
            //memo
            $memoInfo['current_hold'] = 0;
            $stocks[$stockKey]['memo'] = json_encode($memoInfo);
        }
        
        return $stocks;
    }
    
    /**
     * 检查唯品会库存数量(熔断值+库存占用数量)
     *
     * @param $stocks
     * @param $shop
     * @return void
     */
    public function checkVopCircuitStock($stocks, $vopInfo)
    {
        $vopLib = kernel::single('inventorydepth_shop_vop');
        
        //check
        if($vopInfo['node_type'] != 'vop'){
            return $stocks;
        }
        
        //shop_id
        $vop_shop_bn = $vopInfo['shop_bn'];
        $error_msg = '';
        
        //cache shop
        $cartStocks = array();
        if(isset(self::$_vopCartStocks[$vop_shop_bn])){
            $cartStocks = self::$_vopCartStocks[$vop_shop_bn];
        }
        
        //cache stock
        $barcodes = array();
        foreach ($stocks as $stockKey => $stockInfo)
        {
            $barcode = $stockInfo['barcode'];
            
            //check
            if(empty($barcode)){
                continue;
            }
            
            //已经获取过库存,跳过
            if(isset($cartStocks[$barcode])){
                continue;
            }
            
            $barcodes[$barcode] = $barcode;
        }
        
        //get vop batchGetSkuStock
        if($barcodes){
            //批量获取唯品会商品库存
            $skuStocks = $vopLib->getSkuCartFreezeStocks($vopInfo, $barcodes);
            if($skuStocks['rsp'] == 'succ' && $skuStocks['data']){
                foreach ($skuStocks['data'] as $stockKey => $skuStockInfo)
                {
                    $barcode = $skuStockInfo['barcode'];
                    
                    //check
                    if(empty($barcode)){
                        continue;
                    }
                    
                    self::$_vopCartStocks[$vop_shop_bn][$barcode] = $skuStockInfo;
                }
            }
        }
        
        //check
        if(empty(self::$_vopCartStocks)){
            return $stocks;
        }
        
        //format
        foreach ($stocks as $stockKey => $stockInfo)
        {
            $barcode = $stockInfo['barcode'];
            
            //memo
            if(isset($stockInfo['memo']) && $stockInfo['memo']){
                $memoInfo = json_decode($stockInfo['memo'], true);
            }else{
                $memoInfo = array();
            }
            
            //check
            if(!isset(self::$_vopCartStocks[$vop_shop_bn])){
                $memoInfo['current_hold'] = '';
                $memoInfo['circuit_break_value'] = '';
                $stocks[$stockKey]['memo'] = json_encode($memoInfo);
                
                continue;
            }
            
            if(!isset(self::$_vopCartStocks[$vop_shop_bn][$barcode])){
                $memoInfo['current_hold'] = '';
                $memoInfo['circuit_break_value'] = '';
                $stocks[$stockKey]['memo'] = json_encode($memoInfo);
                
                continue;
            }
            
            //leaving_stock
            $leaving_stock = intval(self::$_vopCartStocks[$vop_shop_bn][$barcode]['leaving_stock']);
            
            //current_hold
            $current_hold = intval(self::$_vopCartStocks[$vop_shop_bn][$barcode]['current_hold']);
            
            //circuit_break_value
            $circuit_break_value = intval(self::$_vopCartStocks[$vop_shop_bn][$barcode]['circuit_break_value']);
            
            //freeze = 库存占用数量 + 熔断值
            $vop_freeze_nums = $current_hold + $circuit_break_value;
            
            //check
            if($stockInfo['quantity'] <= $vop_freeze_nums){
                $stocks[$stockKey]['quantity'] = 0;
            }
            
            //memo
            $memoInfo['leaving_stock'] = $leaving_stock;
            $memoInfo['current_hold'] = $current_hold;
            $memoInfo['circuit_break_value'] = $circuit_break_value;
            $stocks[$stockKey]['memo'] = json_encode($memoInfo);
        }
        
        return $stocks;
    }
}
