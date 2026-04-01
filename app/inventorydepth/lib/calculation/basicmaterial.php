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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2022/6/13 10:51:33
 * @describe: 库存回写基础物料计算类
 * 库存回写获取销售物料和基础物料应该用此类， 因为销售物料分一件代发(经销)和自发的
 * ============================
 */
class inventorydepth_calculation_basicmaterial {

    protected $salesmaterialBnId = [];

    protected $salesmaterial = [];

    protected $basicmaterial = [];

    protected $branch = [];

    protected $branch_product = [];

    protected $tmp_is_selfwms = [];
    protected $tmp_is_use_expire = [];
    protected $tmp_expire_store = [];
    
    //店铺发货类型
    protected $shop_delivery_mode = '';

    //店铺关联仓店铺
    protected $shopList =[];
    //是否初始化
    protected $isInit = false;
    //可售库存
    protected $actualStock = [];
    //可售库存计算公式
    protected $actualStockMake = [];
    //订单店铺预占 
    protected $shopFreeze = [];
    //订单指定仓预占
    protected $appointBranchFreeze = [];

    // 专用供货仓信息
    protected $applySupplyBranches = [];

    /**
     * 设置专用供货仓信息
     * @param array $applySupplyBranches 专用供货仓信息，格式：shop_id => [branch_bn, ...]
     */
    public function setApplySupplyBranches($applySupplyBranches) {
        $this->applySupplyBranches = $applySupplyBranches;
    }

    /**
     * 获取供货仓列表
     * 优先使用应用专用供货仓，如果不存在则使用店铺默认供货仓
     * @param string $shop_bn 店铺编码
     * @param string $shop_id 店铺ID
     * @return array 供货仓编码数组
     */
    protected function getSupplyBranches($shop_bn, $shop_id) {
        // 先判断是否存在应用专供仓
        if (!empty($this->applySupplyBranches) && is_array($this->applySupplyBranches)) {
            return $this->applySupplyBranches;
        }
        
        // 如果不存在，使用店铺默认供货仓
        return kernel::single('inventorydepth_shop')->getBranchByshop($shop_bn);
    }

    protected function initMaterial($salesmaterial) {//初始化物料
        //shop_id
        $shopIds = array_unique(array_column($salesmaterial, 'shop_id'));
        if(array_search('_ALL_', $shopIds) !== false) {
            $this->shop_delivery_mode = 'self';
            $shopIds[] = 0; //避免只有_ALL_的时候， 被dbeav_filter过滤掉
        }
        
        if($shopIds){
            $shopRows = app::get('ome')->model('shop')->getList('delivery_mode', ['shop_id'=>$shopIds]);
            if($shopRows) {
                $delivery_mode = array_unique(array_column($shopRows, 'delivery_mode'));
                if(count($delivery_mode) > 1) {
                    $this->throwException('店铺的发货方式不能混用：'.json_encode($shopIds));
                    return;
                }
                
                if($this->shop_delivery_mode) {
                    if($this->shop_delivery_mode != $delivery_mode[0]) {
                        $this->throwException('店铺的发货方式不能混用：'.json_encode($shopIds));
                        return;
                    }
                } else {
                    $this->shop_delivery_mode = $delivery_mode[0];
                }
            }
        }
        
        //shopyjdf
        if($this->shop_delivery_mode == 'shopyjdf') {
            $this->initYjdfMaterial($salesmaterial);
        } else {
            $this->initSelfMaterial($salesmaterial);
        }
    }
    
    protected function initYjdfMaterial($salesmaterial) { //yjdf店铺物料
        $smId = $sp = [];
        foreach ($salesmaterial as $key => $value) {
            $this->salesmaterialBnId[$value['sales_material_bn']][$value['shop_id']] = $value['sm_id'];
            $this->salesmaterial[$value['sm_id']] = $value;
            $smId[] = $value['sm_id'];
            $this->salesmaterial[$value['sm_id']]['products'] = &$sp[$value['sm_id']];
        }
        $salesMLib = kernel::single('material_sales_material');
        if($smId) {
            $smbc = app::get('dealer')->model('sales_basic_material');
            $smProduct = $salesMLib->getBasicMBySalesMIds($smId, $smbc);
            foreach ((array)$smProduct as $key=>$value)
            {
                foreach ($value as $bm) {
                    $this->basicmaterial[$bm['bm_id']] = $bm;
                }
                $sp[$key]    = $value;
            }
        }
    }
    
    protected function initSelfMaterial($salesmaterial) { //自发店铺物料
        $pkoSmId = $smId = $pkop = $sp = $luckySmids = $luckyList = [];
        foreach ($salesmaterial as $key => $value) {
            $this->salesmaterialBnId[$value['sales_material_bn']][$value['shop_id']] = $value['sm_id'];
            $this->salesmaterial[$value['sm_id']] = $value;
            
            //按销售物料类型
            if($value['sales_material_type'] == 5){ //多选一
                $pkoSmId[] = $value['sm_id'];
                $this->salesmaterial[$value['sm_id']]['products'] = &$pkop[$value['sm_id']];
            }elseif($value['sales_material_type'] == 7){
                //福袋组合
                $luckySmids[] = $value['sm_id'];
                $this->salesmaterial[$value['sm_id']]['products'] = &$luckyList[$value['sm_id']];
            }else{
                $smId[] = $value['sm_id'];
                $this->salesmaterial[$value['sm_id']]['products'] = &$sp[$value['sm_id']];
            }
        }
        
        $salesMLib = kernel::single('material_sales_material');
        if($pkoSmId) {
            //获取多选一销售物料对应的基础物料明细
            $pkoProduct = $salesMLib->get_pickone_sm_bm($pkoSmId);
            foreach((array)$pkoProduct as $key => $value){ //key是sm_id
                foreach ($value as $bm) {
                    $this->basicmaterial[$bm['bm_id']] = $bm;
                }
                $pkop[$key] = $value;
            }
        }
        
        if($smId) {
            $smProduct = $salesMLib->getBasicMBySalesMIds($smId);
            foreach ((array)$smProduct as $key=>$value)
            {
                foreach ($value as $bm) {
                    $this->basicmaterial[$bm['bm_id']] = $bm;
                }
                $sp[$key]    = $value;
            }
        }
        
        //福袋组合
        if($luckySmids){
            //福袋组合
            //@todo：最终返回的是三层结构(销售物料-->福袋组合-->基础物料)
            $combineLib = kernel::single('material_fukubukuro_combine');
            $error_msg = '';
            
            //福袋组合包含的基础物料列表
            $luckyBagList = $combineLib->getLuckyMaterialBySmid($luckySmids, $error_msg);
            if(empty($luckyBagList)){
                $luckyBagList = array();
            }
            
            foreach ($luckyBagList as $sm_id => $luckyInfo)
            {
                //销售物料关联的福袋列表
                $luckyList[$sm_id] = $luckyInfo;
                
                //按福袋纬度
                foreach ((array)$luckyInfo as $combine_id => $combineInfo)
                {
                    //check
                    if(empty($combineInfo['items'])){
                        continue;
                    }
                    
                    //关联的基础物料列表
                    foreach ($combineInfo['items'] as $bm_id => $bmInfo)
                    {
                        $this->basicmaterial[$bm_id] = $bmInfo;
                    }
                }
            }
            
            //unset
            unset($luckyBagList);
        }
    }
    
    protected function initBranchProduct() {
        $bmIds = array_keys($this->basicmaterial);
        if(empty($bmIds)) {
            return;
        }

        $branches = app::get('ome')->model('branch')->getList('branch_id,branch_bn,b_type', ['check_permission'=>'false']);
        foreach ($branches as $key=>$branch) {
            $this->branch[$branch['branch_bn']] = $branch;
        }

        $bpModel = app::get('ome')->model('branch_product');
     
        $branch_product = $bpModel->getList('branch_id,product_id,store,store_freeze,last_modified,arrive_store,safe_store',
                            ['product_id'=>$bmIds]);
        
     

        foreach ($branch_product as $key=>$bp) {
            $bp = $this->resetBranchProductStore($bp);
            $this->branch_product[$bp['product_id']][$bp['branch_id']] = $bp;
        }
    }

    protected function resetBranchProductStore($bp) {
        $branchLib = kernel::single('ome_branch');
        $channelLib = kernel::single('channel_func');
        $basicMStorageLifeLib = kernel::single('material_storagelife');
        $branch_id    = $bp['branch_id'];
        $product_id   = $bp['product_id'];
        if(empty($branch_id) || empty($product_id)) {
            return $bp;
        }
        //是否自有仓储
        if(isset($this->tmp_is_selfwms[$branch_id])){
            $is_selfWms = $this->tmp_is_selfwms[$branch_id];
        }else{
            $wms_id = $branchLib->getWmsIdById($branch_id);
            if($wms_id){
                $is_selfWms = $channelLib->isSelfWms($wms_id);
            }else{
                $is_selfWms = false;
            }
            $this->tmp_is_selfwms[$branch_id] = $is_selfWms;
        }

        //是否保质期物料
        if(isset($this->tmp_is_use_expire[$product_id])){
            $is_use_expire = $this->tmp_is_use_expire[$product_id];
        }else{
            $is_use_expire = $basicMStorageLifeLib->checkStorageLifeById($product_id);
            $this->tmp_is_use_expire[$product_id] = $is_use_expire;
        }

        //扣减失效的保质期库存
        if($is_selfWms && $is_use_expire){
            //仓库货品下失效的保质期库存数
            if(isset($this->tmp_expire_store[$product_id][$branch_id])){
                $expire_store = $this->tmp_expire_store[$product_id][$branch_id];
            }else{
                $expire_store = $basicMStorageLifeLib->getExpireStorageLifeStore($branch_id, $product_id);
                $this->tmp_expire_store[$product_id][$branch_id] = $expire_store;
            }

            if($expire_store){
                $bp['store'] = $bp['store'] - $expire_store;
            }
        }
        return $bp;
    }

    public function initOrderFreeze() {
        $bmIds = array_keys($this->basicmaterial);
        if(empty($bmIds)) {
            return;
        }
        $shop = app::get('ome')->model('shop')->getList('shop_id', ['delivery_mode'=>'jingxiao']);
        $notInShop = '';
        if($shop) {
            $notInShop = "and shop_id not in ('".implode("','", array_column($shop, 'shop_id'))."')";
        }
        $bmIdStr = "('".implode("','", $bmIds)."')";
        $sql = "SELECT sum(num) as total,shop_id,bm_id FROM sdb_material_basic_material_stock_freeze 
                    WHERE bm_id in {$bmIdStr} AND obj_type=1 AND branch_id=0 {$notInShop}
                    GROUP BY bm_id, shop_id";
        $list = kernel::database()->select($sql);
        foreach ($list as $key => $value) {
            $sha1Str = sha1($value['shop_id'].'-'.$value['bm_id']);
            $this->shopFreeze[$sha1Str] = (int) $value['total'];
        }
        $you = material_basic_material_stock_freeze::__ORDER_YOU;
        $sql = "SELECT sum(num) as total,branch_id,bm_id FROM sdb_material_basic_material_stock_freeze 
                    WHERE bm_id in {$bmIdStr} AND obj_type=1 AND branch_id<>0 AND bill_type<>{$you} {$notInShop}
                    GROUP BY bm_id, branch_id";
        $list = kernel::database()->select($sql);
        foreach ($list as $key => $value) {
            $sha1Str = sha1($value['branch_id'].'-'.$value['bm_id']);
            $this->appointBranchFreeze[$sha1Str] = (int) $value['total'];
        }

    }

    public function init($salesmaterial) { //自动回写库存初始化数据
        $this->isInit = true;

        $this->salesmaterialBnId = [];
        $this->salesmaterial = [];
        $this->basicmaterial = [];
        $this->shop_delivery_mode = '';
        
        $this->initMaterial($salesmaterial);
        
        $this->branch = [];
        $this->branch_product = [];
        $this->tmp_is_selfwms = [];
        $this->tmp_is_use_expire = [];
        $this->tmp_expire_store = [];
        $this->initBranchProduct();

        $this->shopFreeze = [];
        $this->appointBranchFreeze = [];
        $this->initOrderFreeze();
    }
    
    public function initBasicMaterial($bmIds) {
        $list = kernel::single('material_basic_material')->getBasicMaterialByBmids($bmIds);
        foreach ($list as $bm) {
            $this->basicmaterial[$bm['bm_id']] = $bm;
        }
    }
    
    public function initFromBasic($bmIds) { //基础物料可售库存初始化数据
        $this->isInit = true;
        
        $this->salesmaterialBnId = [];
        $this->salesmaterial = [];
        $this->basicmaterial = [];
        $this->shop_delivery_mode = '';
        $this->initBasicMaterial($bmIds);
        
        $this->branch = [];
        $this->branch_product = [];
        $this->tmp_is_selfwms = [];
        $this->tmp_is_use_expire = [];
        $this->tmp_expire_store = [];
        $this->initBranchProduct();
        
        $this->shopFreeze = [];
        $this->appointBranchFreeze = [];
        $this->initOrderFreeze();
    }

    public function getSalesMaterialType($salesMaterialBn, $shopId) {
        $salesmaterial = $this->getSalesMaterial($salesMaterialBn, $shopId);
        if($salesmaterial['sales_material_type'] == 2){ //促销
            return 'pkg';
        }elseif($salesmaterial['sales_material_type'] == 7){
            //福袋类型
            return 'fukubukuro';
        }
        
        if($salesmaterial['sales_material_type'] == 5){ //多选一
            return 'pko';
        }
        if ($salesmaterial['sales_material_type'] == 3) { //多选一
            return 'gift';
        }
        return 'product';
    }

    public function getSalesMaterial($salesMaterialBn, $shopId) {
        if($this->salesmaterialBnId[$salesMaterialBn] && $this->salesmaterialBnId[$salesMaterialBn][$shopId]) {
            return $this->salesmaterial[$this->salesmaterialBnId[$salesMaterialBn][$shopId]];
        }
        if($this->shop_delivery_mode == 'self') {
            if($this->salesmaterialBnId[$salesMaterialBn] && $this->salesmaterialBnId[$salesMaterialBn]['_ALL_']) {
                return $this->salesmaterial[$this->salesmaterialBnId[$salesMaterialBn]['_ALL_']];
            }
        }
        if($this->isInit) {
            return [];
        }
        
        $salesMaterialObj = app::get('material')->model('sales_material');
        $goods    = $salesMaterialObj->db_dump(array('sales_material_bn'=>$salesMaterialBn), 'sm_id,sales_material_name,sales_material_bn,sales_material_type,shop_id');
        if(empty($goods)){
            return [];
        }
        
        //sm_id
        $sm_id = $goods['sm_id'];
        
        //根据类型组合销售物料数据
        if($goods['sales_material_type'] == 7) {
            //福袋组合
            //@todo：最终返回的是三层结构(销售物料-->福袋组合-->基础物料)
            $combineLib = kernel::single('material_fukubukuro_combine');
            $error_msg = '';
            
            //福袋组合包含的基础物料列表
            $luckyBagList = $combineLib->getLuckyMaterialBySmid(array($sm_id), $error_msg);
            if(!$luckyBagList){
                $luckyBagList = array();
            }
            $goods['products'] = $luckyBagList[$sm_id];
            
            //销售物料关联的福袋列表
            foreach((array)$goods['products'] as $luckyKey => $luckyVal)
            {
                //check
                if(empty($luckyVal['items'])){
                    continue;
                }
                
                //福袋关联的基础物料列表
                foreach ($luckyVal['items'] as $bmKey => $bmVal)
                {
                    $bm_id = $bmVal['bm_id'];
                    
                    $this->basicmaterial[$bm_id] = $bmVal;
                }
            }
        }else{
            $salesMLib = kernel::single('material_sales_material');
            
            if($goods['sales_material_type'] == 5) {
                //多选一
                $pkoProduct = $salesMLib->get_pickone_sm_bm(array($goods['sm_id']));
                $goods['products'] = $pkoProduct[$goods['sm_id']];
            } else {
                $products    = $salesMLib->getBasicMBySalesMId($goods['sm_id']);
                $goods['products'] = $products;
            }
            foreach((array)$goods['products'] as $key => $value){
                $this->basicmaterial[$value['bm_id']] = $value;
            }
        }
        
        $this->salesmaterialBnId[$salesMaterialBn] = $goods['sm_id'];
        $this->salesmaterial[$goods['sm_id']] = $goods;
        
        return $this->salesmaterial[$goods['sm_id']] ;
    }
    
    protected function throwException($msg) {
        throw new exception($msg);
    }



    public function getBranch($branch_bn) {
        if($this->branch[$branch_bn]) {
            return $this->branch[$branch_bn];
        }
        if($this->isInit) {
            return [];
        }
        $this->throwException('未进行初始化');
        return [];
    }

    public function getBranchProduct($bm_id, $branch_id) {
        if($this->branch_product[$bm_id][$branch_id]) {
            return $this->branch_product[$bm_id][$branch_id];
        }
        if($this->isInit) {
            return [];
        }
        $this->throwException('未进行初始化');
        return [];
    }

    //获取可售库存
    public function get_actual_stock($bm_id, $shop_bn, $shop_id) {
        return $this->getActualStock($bm_id, $shop_bn, $shop_id);
    }

    //获取仓库可售库存
    public function get_branch_actual_stock($bm_id, $shop_bn, $shop_id) {
        return $this->getActualStock($bm_id, $shop_bn, $shop_id, 'branch');
    }
    //获取门店可售库存
    public function get_md_actual_stock($bm_id, $shop_bn, $shop_id) {
        return $this->getActualStock($bm_id, $shop_bn, $shop_id, 'md');
    }
    //获取可售安全库存
    public function get_actual_safe_stock($bm_id, $shop_bn, $shop_id) {
        return $this->getActualStock($bm_id, $shop_bn, $shop_id, 'all', true);
    }

    //获取仓库可售安全库存
    public function get_branch_actual_safe_stock($bm_id, $shop_bn, $shop_id) {
        return $this->getActualStock($bm_id, $shop_bn, $shop_id, 'branch', true);
    }
    //获取门店可售安全库存
    public function get_md_actual_safe_stock($bm_id, $shop_bn, $shop_id) {
        return $this->getActualStock($bm_id, $shop_bn, $shop_id, 'md', true);
    }
    
    protected function getActualStock($bm_id, $shop_bn, $shop_id, $type = 'all', $safe = false) {
        $branches = $this->getSupplyBranches($shop_bn, $shop_id);

        if (!$branches) {
            return [false, ['error'=>'店铺'.$shop_bn.'缺少供货仓']];
        }

        $sha1Str = $bm_id.'-'.$shop_id.'-'.$type.($safe ? '-safe' : '').implode('|', $branches);

        $sha1 = sha1($sha1Str);
        if(isset($this->actualStock[$sha1])) return [$this->actualStock[$sha1], $this->actualStockMake[$sha1]];

        $store_sum = $store_freeze_sum = $appoint_freeze = $safeActual = 0;
        $detail = [];
        foreach ($branches as $branch_bn) {
            $branch = $this->getBranch($branch_bn);
            if($type == 'branch' && $branch['b_type'] == 2) {
                continue;
            }
            if($type == 'md' && $branch['b_type'] != 2) {
                continue;
            }
            $branch_product = $this->getBranchProduct($bm_id, $branch['branch_id']);
            if ($branch_product) {
                $store_sum += $branch_product['store'];
                $store_freeze_sum += $branch_product['store_freeze'];
            }
            list($tmpAF,) = $this->get_appoint_branch_freeze($bm_id, $branch['branch_id']);
            $appoint_freeze += $tmpAF;
            $tmpSA = $branch_product['store'] - $branch_product['store_freeze'] - $tmpAF - $branch_product['safe_store'];
            $tmpSA = $tmpSA > 0 ? $tmpSA : 0;
            $safeActual += $tmpSA;
            $detail[$branch_bn]['库存'] = $branch_product['store'];
            $detail[$branch_bn]['仓库预占'] = $branch_product['store_freeze'];
            $detail[$branch_bn]['安全库存'] = $branch_product['safe_store'];
            $detail[$branch_bn]['指定仓预占'] = $tmpAF;
            $detail[$branch_bn]['安全可售'] = [
                'quantity' => $tmpSA,
                'info' => '库存-仓库预占-指定仓预占-安全库存=安全可售(小于0时为0)'
            ];
        }
        if($type == 'md') {
            if($safe) {
                $actual_stock = $safeActual;
                $this->actualStockMake[$sha1]['公式'] = '安全可售';
            } else {
                $actual_stock = $store_sum  - $store_freeze_sum - $appoint_freeze;
                $this->actualStockMake[$sha1]['公式'] = '库存-仓库预占-指定仓预占';
            }
        } else {
            list($globals_freeze, $globals_freeze_detail) = $this->get_globals_freeze($bm_id, $shop_bn, $shop_id);
            $globals_freeze = (int) $globals_freeze;
            if($safe) {
                $actual_stock = $safeActual - $globals_freeze;
                $this->actualStockMake[$sha1]['公式'] = '安全可售-全局预占';
            } else {
                $actual_stock = $store_sum - $globals_freeze - $store_freeze_sum - $appoint_freeze;
                $this->actualStockMake[$sha1]['公式'] = '库存-全局预占-仓库预占-指定仓预占';
            }
            $this->actualStockMake[$sha1]['全局预占'] = $globals_freeze;
            $this->actualStockMake[$sha1]['全局预占明细'] = $globals_freeze_detail;
        }
        $this->actualStockMake[$sha1]['库存'] = $store_sum;
        $this->actualStockMake[$sha1]['仓库预占'] = $store_freeze_sum;
        $this->actualStockMake[$sha1]['指定仓预占'] = $appoint_freeze;
        $this->actualStockMake[$sha1]['安全可售'] = $safeActual;
        $this->actualStockMake[$sha1]['detail'] = $detail;
        $this->actualStock[$sha1] = (int)$actual_stock > 0 ? (int)$actual_stock : 0;
        return [$this->actualStock[$sha1], $this->actualStockMake[$sha1]];
    }
    //获取全局预占
    public function get_globals_freeze($bm_id,$shop_bn,$shop_id)
    {
        $shop_branches = kernel::single('inventorydepth_shop')->getBranchByshop();
        $branches      = $this->getSupplyBranches($shop_bn, $shop_id);
        if (empty($branches) || empty($shop_branches)) {
            return [false, ['error'=>'仓库未绑定店铺']];
        }

        # 获取这些仓所对应的所有店铺
        $shopListKey = sha1($shop_bn . '-' . json_encode($branches));
        if (!isset($this->shopList[$shopListKey])) {
            $shopes = array();
            foreach ($branches as $branch_bn) {
                foreach ($shop_branches as $shop => $branch) {
                    if (in_array($branch_bn, $branch)) {
                        $shopes[] = $shop;
                    }
                }
            }
            $s = app::get('ome')->model('shop')->getList('shop_id,shop_bn',array('shop_bn'=>$shopes));
            $this->shopList[$shopListKey] = $s;
        }
        # 根据订单计算店铺预占(未发货订单) 该店铺下的商品ID
        $globals_freeze = 0;
        $shop_freeze_detail = [];
        foreach ($this->shopList[$shopListKey] as $key=>$value) {
            list($tmpNum, )= $this->get_shop_freeze($bm_id, $value['shop_bn'], $value['shop_id']);
            $globals_freeze += $tmpNum;
            $shop_freeze_detail[$value['shop_bn']] = [
                'shop_id' => $value['shop_id'],
                'freeze' => $tmpNum
            ];
        }
        $globals_freeze = $globals_freeze > 0 ? (int)$globals_freeze : 0;

        $detail = [
            'shop_branches' => $shop_branches,
            'branches' => $branches,
            'shop_freeze_detail' => $shop_freeze_detail
        ];

        return [$globals_freeze, $detail];
    }
    //获取订单店铺预占
    public function get_shop_freeze($bm_id, $shop_bn, $shop_id) {
        $sha1Str = sha1($shop_id.'-'.$bm_id);
        if(isset($this->shopFreeze[$sha1Str])) {
            return [$this->shopFreeze[$sha1Str], []];
        }
        if($this->isInit) {
            return [0, []];
        }
        $this->throwException('未进行初始化');
        return [];
    }
    //获取订单指定仓预占
    public function get_appoint_branch_freeze($product_id, $branch_id) {
        $sha1Str = sha1($branch_id.'-'.$product_id);
        if(isset($this->appointBranchFreeze[$sha1Str])) {
            return [$this->appointBranchFreeze[$sha1Str], []];
        }
        if($this->isInit) {
            return [0, []];
        }
        $this->throwException('未进行初始化');
        return [];
    }
}
