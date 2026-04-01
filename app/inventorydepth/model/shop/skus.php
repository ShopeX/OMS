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
*
*/
class inventorydepth_mdl_shop_skus extends dbeav_model
{
    public $additional = array();

    public $filter_use_like = true;

    public $appendCols = 'shop_iid,shop_id';

    public static $taog_products = array();

    public static $taog_pkg = array();
    
    public static $taog_pko = array();

    function __construct($app)
    {
        parent::__construct($app);

        $this->app = $app;
    }

    public function initTaogBn($bnList) 
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        // check
        if(empty($bnList)){
            return false;
        }
        
        // 初始化
        self::$taog_products = self::$taog_pkg = self::$taog_pko = [];
        
        // list
        $filter = array('sales_material_bn'=>$bnList, 'is_bind'=>1);
        $materialList = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,shop_id,sales_material_type', $filter);
        
        // check
        if(empty($materialList)){
            return false;
        }
        
        // format
        foreach ($materialList as $key => $value)
        {
            $sales_material_bn = $value['sales_material_bn'];
            
            // type
            switch ($value['sales_material_type'])
            {
                case '2':
                case '7':
                    // pkg捆绑、lkb福袋组合
                    self::$taog_pkg[$sales_material_bn] = $value;
                break;
                case '5':
                    // 多选一
                    self::$taog_pko[$sales_material_bn] = $value;
                    break;
                default:
                    // 普通、赠品、礼盒
                    self::$taog_products[$sales_material_bn] = $value;
            }
        }
        
        // unset
        unset($materialList);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = array(1);
        if (isset($filter['shop_product_bn'])) {
            if(is_string($filter['shop_product_bn']) && strpos($filter['shop_product_bn'], "\n") !== false){
                $filter['shop_product_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['shop_product_bn']))));
            }
            if ($filter['shop_product_bn'] == 'nobn') {
                $where[] = ' (shop_product_bn is NULL OR shop_product_bn="") ';
                unset($filter['shop_product_bn']);
            }

            if ($filter['shop_product_bn'] == 'repeat') {
                unset($filter['shop_product_bn']);

                $sql = 'SELECT id,shop_product_bn,shop_id FROM '.$this->table_name(true).' WHERE shop_product_bn!="" AND shop_product_bn is not null GROUP BY shop_product_bn,shop_id  Having count(1)>1';
                
                $list = $this->db->select($sql);
                if ($list) {
                    foreach ($list as $value) {
                        $filter['shop_product_bn'][] = $value['shop_product_bn'];
                    }
                } else {
                    # 没有重复的，则结果为空
                    $filter['shop_product_bn'][] = 'norepeat';
                }
            }
        }

        return parent::_filter($filter,$tableAlias,$baseWhere).' AND '.implode(' AND ', $where);
    }

    public function io_title($filter=null,$ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:店铺编号' => 'shop_bn',
                    '*:店铺名称' => 'shop_name',
                    '*:店铺货号' => 'shop_product_bn',
                    '*:销售价'  => 'shop_price',
                    '*:条形码'  => 'shop_barcode',
                    //'*:规格'   => 'shop_properties',
                    '*:商品名称' => 'title',
                    '*:上架状态' => 'approve_status', 
                );
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType]['main'] );
        return $this->ioTitle[$ioType][$filter];
    }

    public function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        @ini_set('memory_limit','64M');

        if( !$data['title'] ){
            $title = array();
            foreach( $this->io_title('main') as $k => $v ){
                //$title[] = $this->charset->local2utf($v);
                $title[] = $v;
            }
            $data['title'] = '"'.implode('","',$title).'"';
        }

        $limit = 100;

        if( !$list=$this->getList('*',$filter,$offset*$limit,$limit) )return false;

        $itemModel = $this->app->model('shop_items');
        foreach( $list as $l ){
            $item = $itemModel->select()->columns('*')->where('iid=?',$l['shop_iid'])->instance()->fetch_row();
            $l['approve_status'] = $item['approve_status'];
            $l['title'] = $item['title'];

            foreach( $this->oSchema['csv']['main'] as $k => $v ){
                //$row[$k] = $this->charset->local2utf($l[$v]);
                $row[$k] = $l[$v];
            }
            $data['contents'][] = '"'.implode('","',$row).'"';
        }

        return true;
    }

    public function get_schema()
    {
        $schema = parent::get_schema();

        # 过滤掉不显示的字段
        $none = array('release_status','release_stock','shop_stock');
        foreach ($none as $value) {
            $key = array_search($value, $schema['in_list']);

            if($key !== false) unset($schema['in_list'][$key]);

            $key = array_search($value, $schema['default_in_list']);

            if($key !== false) unset($schema['default_in_list'][$key]);
        }

        return $schema;
    }

    /**
     * 删除货品
     *
     * @return void
     * @author 
     **/
    public function deleteSkus($filter)
    {
        $sql = 'DELETE FROM `'.$this->table_name(1).'` where '.$this->_filter($filter);

        return $this->db->exec($sql);
    }

    /**
     * 保存SKU
     *
     * @return void
     * @author 
     **/
    public function updateSku($sku,$id)
    {
        # 
        $bnList[] = $sku['outer_id'];
        $this->initTaogBn($bnList);

        $data = array(
            'shop_product_bn'       => $sku['outer_id'],
            'shop_product_bn_crc32' => sprintf('%u',crc32($sku['outer_id'])),
            'shop_properties'       => $sku['properties'],
            'shop_price'            => $sku['price'],
            'download_time'         => time(),
            'shop_stock'            => $sku['quantity'],
            'sales_material_type'   => '1',
        );
        
        $skuInfo = $this->getList('shop_id',array('id'=>$id));
        
        //sales_material_type
        $sales_material_type = '1';
        
        # 映射到本地商品
        $data['mapping'] = $this->getMapping($sku['outer_id'], $skuInfo[0]['shop_id'], $data['bind'], $sales_material_type);
        
        //sales_material_type
        if($sales_material_type){
            $data['sales_material_type'] = $sales_material_type;
        }
        
        //商品同步到优仓
        $shop = kernel::single('ome_shop')->getRowByShopId($skuInfo[0]['shop_id']);
        kernel::single('dchain_event_trigger_dchain_product')->addProduct([$data],$bnList,$shop);
    
        $this->update($data,array('id'=>$id));
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function isave($skus,$shop,$item=array())
    {
        $bnList = array();
        foreach ($skus['sku'] as $sku) {
            $bnList[] = $sku['outer_id'];
        }
        
        $this->initTaogBn($bnList);
        
        $shop_id = $shop['shop_id']; $shop_bn = $shop['shop_bn'];
        unset($bnList);

       $spbn = array();
        # 保存SKU
        foreach ($skus['sku'] as $sku) {
            $iid = $sku['iid'] ? $sku['iid'] : $item['iid']; $spbn[] = $sku['outer_id'];

            if ($sku['sku_id']) {
                $sku_id = md5($shop['shop_id'].$iid.$sku['sku_id']);
            } else {
                $sku_id = md5($shop['shop_id'].$iid);
            }

            # 映射到本地商品
            $product_bn = $sku['outer_id'];
            $stock_model = $sku['stock_model'] ?: '';
            $bidding_no = $sku['bidding_no'] ?: '';
            $bidding_type = $sku['bidding_type'] ?: '';
    
            $sku = array(
                'id'                    => $sku_id,
                'shop_id'               => $shop['shop_id'],
                'shop_bn'               => $shop['shop_bn'],
                'cos_id'                => $shop['cos_id'],
                'shop_bn_crc32'         => sprintf('%u', crc32($shop['shop_bn'])),
                'shop_name'             => $shop['name'],
                'shop_type'             => $shop['shop_type'],
                'shop_sku_id'           => $sku['sku_id'],
                'shop_iid'              => $iid,
                'shop_product_bn'       => $sku['outer_id'],
                'shop_product_bn_crc32' => sprintf('%u', crc32($sku['outer_id'])),
                'shop_properties'       => $sku['properties'],
                'shop_price'            => $sku['price'],
                'shop_barcode'          => $sku['barcode'],
                'simple'                => ($item['simple'] == 'true') ? 'true' : 'false',
                'download_time'         => time(),
                'shop_stock'            => $sku['quantity'],
                'operator'              => isset($sku['op_id']) ? $sku['op_id'] : '',
                'op_name'               => isset($sku['op_name']) ? $sku['op_name'] : '',
                'sales_material_type'   => '1',
            );

            if ($item['title']) {
                $sku['shop_title'] = $item['title'];
            }
            
            $sku['mapping'] = $this->getMapping($product_bn,$shop['shop_id'],$sku['bind'], $sku['sales_material_type']);
            
            //促销or福袋组合
            if ($sku['bind'] == 1) {
                $pkgFlag[] = $product_bn;
            }elseif($sku['bind'] == 2){ //多选一
                $pkoFlag[] = $product_bn;
            }else{
                $productFlag[] = $product_bn;
            }
            
            //[兼容]得物平台出价编号
            if($bidding_no){
                $sku['bidding_type'] = $bidding_type;
                $sku['bidding_no']   = $bidding_no;
            }

            // 分区库存
            if ($stock_model){
                $sku['stock_model'] = $stock_model;
            }

            //商品同步到优仓
            kernel::single('dchain_event_trigger_dchain_product')->addProduct([$sku],[$sku['shop_product_bn']],$shop);
            if(!$this->db_dump(['id'=>$sku['id'],'shop_product_bn'=>$sku['shop_product_bn']], 'id') && $sku['shop_product_bn'] && $sku['mapping']) {
                $mdlStock = app::get('ome')->model('api_stock_log');
                $slist = $mdlStock->getList('log_id', ['product_bn'=>$sku['shop_product_bn'],'shop_id'=>$shop['shop_id']]);
                if($slist) {
                    $mdlStock->delete(['log_id'=>array_column($slist, 'log_id')]);
                }
            }
            $this->save($sku);
            $delete_filter['shop_sku_id|notin'][] = $sku['shop_sku_id'];
        }

        # 删除多余的
        $delete_filter['shop_iid'] = $item['iid'];
        $delete_filter['shop_id'] = $shop['shop_id'];

        $this->deleteSkus($delete_filter);

        return true;
    }

    public function isave_back($skus,$shop,$item=array())
    {
        $data = array(); $bnList = array();
        foreach ($skus['sku'] as $sku) {
            $bnList[] = $sku['outer_id'];
        }
        $this->initTaogBn($bnList); $shop_id = $shop['shop_id']; $shop_bn = $shop['shop_bn'];
        unset($bnList);
    
        foreach ((array) $skus['sku'] as $sku) {
            $iid = $item['iid']; 

            $id = $sku['sku_id'] ? md5($shop['shop_id'].$iid.$sku['sku_id']) : md5($shop['shop_id'].$iid);

            $data[$id] = array(
                'id'                    => $id,
                'shop_id'               => $shop['shop_id'],
                'shop_bn'               => $shop['shop_bn'],
                'shop_bn_crc32'         => inventorydepth_func::crc32($shop['shop_bn']),
                'shop_name'             => $shop['name'],
                'shop_type'             => $shop['shop_type'],
                'shop_product_bn_crc32' => inventorydepth_func::crc32($sku['outer_id']),
                'shop_sku_id'           => $sku['sku_id'],
                'shop_iid'              => $iid,
                'shop_product_bn'       => $sku['outer_id'],
                'shop_properties_name'  => $sku['properties_name'],
                'shop_price'            => $sku['price'],
                'shop_barcode'          => $sku['barcode'],
                'simple'                => ($item['simple'] == 'true') ? 'true' : 'false',
                'download_time'         => time(),
                'shop_stock'            => $sku['quantity'],
                'shop_title'            => $item['title'],
                'outer_createtime'      => $sku['created'] ? strtotime($sku['created']) : null,
                'outer_lastmodify'      => $sku['modified'] ? strtotime($sku['modified']) : null,
                'request'               => 'true',
                'sync_map'              => '0',
                'bind'                  => '0',
                'mapping'               => '0',
                'sales_material_type'   => '1',
            );
            
            //mapping
            $sku['mapping'] = $this->getMapping($sku['outer_id'],$shop['shop_id'],$sku['bind'], $sku['sales_material_type']);
            
            if(!$this->db_dump(['id'=>$sku['id'],'shop_product_bn'=>$sku['shop_product_bn']], 'id') && $sku['shop_product_bn'] && $sku['mapping']) {
                $mdlStock = app::get('ome')->model('api_stock_log');
                $slist = $mdlStock->getList('log_id', ['product_bn'=>$sku['shop_product_bn'],'shop_id'=>$shop['shop_id']]);
                if($slist) {
                    $mdlStock->delete(['log_id'=>array_column($slist, 'log_id')]);
                }
            }
            $data[$id]['mapping'] = $sku['mapping'];
            $data[$id]['bind'] = $sku['bind'];
            $data[$id]['sales_material_type'] = $sku['sales_material_type'];
            
            $bnList[] = $sku['outer_id'];
        }

        foreach ($this->getList('request,sync_map,id',array('id' => array_keys($data))) as $value) {
            $data[$value['id']]['request']  = $value['request'];
            $data[$value['id']]['sync_map'] = $value['sync_map'];
        }
        //商品同步到优仓
        kernel::single('dchain_event_trigger_dchain_product')->addProduct($data,$bnList,$shop);
    
        if (!$data) return false;

        return $this->db->exec(inventorydepth_func::get_replace_sql($this,$data));
    }

    /**
     * 清空表数据
     *
     * @return void
     * @author 
     **/
    public function truncate()
    {
        $sql = 'TRUNCATE TABLE '.$this->table_name(true);

        $this->db->exec($sql);
    }

    /**
     * 批量加入货品
     *
     * @return void
     * @author 
     **/
    public function batchInsert($items,$shop,&$stores)
    {
        if (empty($items)) return false;

        $bnList = $taog_id = array();
        foreach ($items as $key => $item) {
            $iid = $item['iid'] ? $item['iid'] : $item['num_iid'];

            if (isset($item['skus']['sku'])) {
                foreach ($item['skus']['sku'] as $k => $sku) {
                    $bnList[] = $sku['outer_id'];

                    $id = md5($shop['shop_id'].$iid.$sku['sku_id']);
                    $items[$key]['skus']['sku'][$k]['taog_id'] = $id;
                    $taog_id[] = $id;
                }
            } else {
                $bnList[] = $item['outer_id'];

                $id = md5($shop['shop_id'].$iid);
                $items[$key]['taog_id'] = $id;
                $taog_id[] = $id;
            }
        }
        
        $this->initTaogBn($bnList); $shop_id = $shop['shop_id']; $shop_bn = $shop['shop_bn'];
        
        unset($bnList);

        $shopSkuLib = kernel::single('inventorydepth_shop_skus');

        $shop_bn_crc32         = $shopSkuLib->crc32($shop['shop_bn']);

        $request = array();
        $rows = $this->getList('request,id',array('id' => $taog_id));
        foreach ($rows as $key=>$row) {
            $request[$row['id']] = $row['request'];
        }
        unset($rows,$taog_id);
        
        $data = array();
        
        $line_i = 0;
        foreach ($items as $key => $item) {
            $spbn = array();
            $iid = $item['iid'] ? $item['iid'] : $item['num_iid'];

            if (isset($item['skus']['sku'])) {
             # 多规格   
                foreach ($item['skus']['sku'] as $sku) {
                    $shop_product_bn = $sku['outer_id'];
                    $spbn[] = $shop_product_bn;
                    $shop_product_bn_crc32 = $shopSkuLib->crc32($shop_product_bn);
                    
                    //sales_material_type
                    $sales_material_type = '1';
                    
                    //mapping
                    $mapping = $this->getMapping($shop_product_bn,$shop['shop_id'],$bind, $sales_material_type);
                    $download_time = time();
                    
                    if ($bind == 1) {
                        $pkgFlag[] = $shop_product_bn;
                    }elseif($bind == 2){
                        $pkoFlag[] = $shop_product_bn;
                    }else{
                        $productFlag[] = $shop_product_bn;
                    }
                    
                    #  判断是否存在发布库存
                    $release_stock = 0;
                    
                    $data[$line_i] = array(
                        'shop_id' => $shop['shop_id'],
                        'shop_bn' => $shop['shop_bn'],
                        'shop_bn_crc32' => $shop_bn_crc32,
                        'shop_name' => $shop['name'],
                        'shop_type' => $shop['shop_type'],
                        'shop_sku_id' => $sku['sku_id'],
                        'shop_barcode' => $sku['barcode'],
                        'shop_iid' => $iid,
                        'shop_product_bn' => $shop_product_bn,
                        'shop_product_bn_crc32' => $shop_product_bn_crc32,
                        'shop_properties' => $sku['properties'],
                        'shop_price' => $sku['price'],
                        'simple' => $item['simple'],
                        'download_time' => $download_time,
                        'shop_title' => $item['title'],
                        'mapping' => $mapping,
                        'shop_stock' => isset($sku['quantity']) ? $sku['quantity'] : $sku['num'],
                        'shop_properties_name' => $sku['properties_name'] ? $sku['properties_name'] : '',
                        'release_stock' => $release_stock,
                        'bind' => $bind,
                        'sales_material_type' => $sales_material_type,
                        'id' => $sku['taog_id'],
                        'request' => $request[$sku['taog_id']] == 'false' ? 'false' : 'true',
                        'update_time'=>time()
                    );
                    
                    //[兼容]得物平台出价编号
                    if($sku['bidding_no']){
                        $data[$line_i]['bidding_type'] = $sku['bidding_type'];
                        $data[$line_i]['bidding_no'] = $sku['bidding_no'];
                    }

                    // 分区库存
                    if ($sku['stock_model']){
                        $data[$line_i]['stock_model'] = $sku['stock_model'];
                    }

                    $bnList[] = $shop_product_bn;
                    $line_i++;
                }
            }else{
                // 单商品
                $shop_product_bn       = $item['outer_id']; $spbn[] = $shop_product_bn;
                $shop_product_bn_crc32 = $shopSkuLib->crc32($shop_product_bn);
                
                //sales_material_type
                $sales_material_type = '1';
                
                //mapping
                $mapping = $this->getMapping($shop_product_bn,$shop['shop_id'],$bind, $sales_material_type);
                $download_time = time();
                
                if ($bind == 1) {
                    $pkgFlag[] = $shop_product_bn;
                }elseif($bind == 2){
                    $pkoFlag[] = $shop_product_bn;
                }else{
                    $productFlag[] = $shop_product_bn;
                }

                $release_stock = 0;
                
                $data[$line_i] = array(
                    'shop_id' => $shop['shop_id'],
                    'shop_bn' => $shop['shop_bn'],
                    'shop_bn_crc32' => $shop_bn_crc32,
                    'shop_name' => $shop['name'],
                    'shop_type' => $shop['shop_type'],
                    'shop_sku_id' => '',
                    'shop_barcode' => $sku['barcode'],
                    'shop_iid' => $iid,
                    'shop_product_bn' => $shop_product_bn,
                    'shop_product_bn_crc32' => $shop_product_bn_crc32,
                    'shop_properties' => $item['props'],
                    'shop_price' => $item['price'],
                    'simple' => $item['simple'],
                    'download_time' => $download_time,
                    'shop_title' => $item['title'],
                    'mapping' => $mapping,
                    'shop_stock' => $item['num'],
                    'shop_properties_name' => '',
                    'release_stock' => $release_stock,
                    'bind' => $bind,
                    'sales_material_type' => $sales_material_type,
                    'id' => $item['taog_id'],
                    'request' => $request[$item['taog_id']]=='false' ? 'false' : 'true',
                );
                
                //[兼容]得物平台出价编号
                if($item['bidding_no']){
                    $data[$line_i]['bidding_type'] = $item['bidding_type'];
                    $data[$line_i]['bidding_no'] = $item['bidding_no'];
                }
                $bnList[] = $shop_product_bn;
                $line_i++;
            }

            // 商品的实际库存
            $item_actual_stock = 0;

            $stores[strval($iid)]['taog_store'] = $item_actual_stock;
        }
        
        //商品同步到优仓
        kernel::single('dchain_event_trigger_dchain_product')->addProduct($data,$bnList,$shop);
        if($data) {
            $sql = inventorydepth_func::get_replace_sql($this,$data);
            $this->db->exec($sql); 
        }
    }

    /**
     * 获取商品映射关系信息
     *
     * @param $bn
     * @param $shop_id
     * @param $bind 商品类型
     * @param $sales_material_type 销售物料类型
     * @return string
     */
    public function getMapping($bn,$shop_id,&$bind, &$sales_material_type='1')
    {  
        $bind = '0';

        if(isset(self::$taog_products[$bn]) && (self::$taog_products[$bn]['shop_id'] == $shop_id || self::$taog_products[$bn]['shop_id'] == '_ALL_')){
            //sales_material_type
            if(self::$taog_products[$bn]['sales_material_type']){
                $sales_material_type = self::$taog_products[$bn]['sales_material_type'];
            }
            
            //format
            $sales_material_type = $this->_formatSalesMaterialType($sales_material_type);
            
            return '1';
        }
        elseif(isset(self::$taog_products[$bn]))
        {
            /* 销售物料存在,但不属于此店铺(强制修改销售物料的所属店铺等于_ALL_,并且进行关联) */
            $salesMaterialObj    = app::get('material')->model('sales_material');
            $salesMaterialObj->update(array('shop_id'=>'_ALL_'), array('sales_material_bn'=>$bn, 'sales_material_type'=>1, 'is_bind'=>1));
            
            //sales_material_type
            if(self::$taog_products[$bn]['sales_material_type']){
                $sales_material_type = self::$taog_products[$bn]['sales_material_type'];
            }
            
            //format
            $sales_material_type = $this->_formatSalesMaterialType($sales_material_type);
            
            return '1';
        }
        
        if (isset(self::$taog_pkg[$bn]) && (self::$taog_pkg[$bn]['shop_id'] == $shop_id || self::$taog_pkg[$bn]['shop_id'] == '_ALL_')) {
            $bind = '1';
            
            //sales_material_type
            if(self::$taog_pkg[$bn]['sales_material_type']){
                $sales_material_type = self::$taog_pkg[$bn]['sales_material_type'];
            }
            
            //format
            $sales_material_type = $this->_formatSalesMaterialType($sales_material_type);
            
            return '1';
        }
        elseif(isset(self::$taog_pkg[$bn]))
        {
            $filter = array('sales_material_bn'=>$bn, 'sales_material_type'=>array(2, 7), 'is_bind'=>1);
            
            /* 促销销售物料存在,但不属于此店铺(强制修改销售物料的所属店铺等于_ALL_,并且进行关联) */
            $salesMaterialObj    = app::get('material')->model('sales_material');
            $salesMaterialObj->update(array('shop_id'=>'_ALL_'), $filter);
            
            //sales_material_type
            if(self::$taog_pkg[$bn]['sales_material_type']){
                $sales_material_type = self::$taog_pkg[$bn]['sales_material_type'];
            }
            
            //format
            $sales_material_type = $this->_formatSalesMaterialType($sales_material_type);
            
            return '1';
        }

        //多选一
        if (isset(self::$taog_pko[$bn]) && (self::$taog_pko[$bn]['shop_id'] == $shop_id || self::$taog_pko[$bn]['shop_id'] == '_ALL_')) {
            $bind = '2';
            
            //sales_material_type
            if(self::$taog_pko[$bn]['sales_material_type']){
                $sales_material_type = self::$taog_pko[$bn]['sales_material_type'];
            }
            
            //format
            $sales_material_type = $this->_formatSalesMaterialType($sales_material_type);
            
            return '2';
        }elseif(isset(self::$taog_pko[$bn])){
            /* 多选一销售物料存在,但不属于此店铺(强制修改销售物料的所属店铺等于_ALL_,并且进行关联) */
            $salesMaterialObj = app::get('material')->model('sales_material');
            $salesMaterialObj->update(array('shop_id'=>'_ALL_'), array('sales_material_bn'=>$bn, 'sales_material_type'=>5, 'is_bind'=>1));
            $bind = '2';
            
            //sales_material_type
            if(self::$taog_pko[$bn]['sales_material_type']){
                $sales_material_type = self::$taog_pko[$bn]['sales_material_type'];
            }
            
            //format
            $sales_material_type = $this->_formatSalesMaterialType($sales_material_type);
            
            return '2';
        }
        
        return '0';
    }

    /**
     * @description 删除过时数据
     * @access public
     * @param void
     * @return void
     */
    public function deletePassData($shop_id,$time) 
    {
        $sql = ' DELETE FROM `'.$this->table_name(1).'` WHERE shop_id = "'.$shop_id.'" AND download_time < '.$time;
        $this->db->exec($sql);
    }

    /**
     * 通过CRC32查询
     *
     * @return void
     * @author 
     **/
    public function getListByCrc32($shop_product_bn,$shop_id)
    {
        $shop_product_bn = (array)$shop_product_bn;
        foreach ($shop_product_bn as &$value) {
            $value = kernel::single('inventorydepth_shop_skus')->crc32($value);
        }

        $filter = array(
            'shop_product_bn_crc32' => $shop_product_bn,
            'shop_id' => $shop_id,
        );

        $skus = $this->getList('*',$filter);

        return $skus;
    }
    
    /**
     * 格式化销售物料类型字段值
     * @todo：销售物料表中类型是int类型,sdb_inventorydepth_shop_skus表是用枚举型,因为finder中无法用extend扩展加搜索项。
     *
     * @param $sales_material_type
     * @return void
     */
    public function _formatSalesMaterialType($sales_material_type)
    {
        $sales_material_type = intval($sales_material_type);
        
        //format
        if($sales_material_type > 7 || $sales_material_type == 4){
            $sales_material_type = '0';
        }
        
        return $sales_material_type;
    }
}
