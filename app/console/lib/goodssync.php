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

class console_goodssync{

    /**
    * 编辑货品前的操作
    * @param Array $sdf 货品编辑前的数据
    * @return void
    */
    public function pre_update($sdf=array()){
        if (empty($sdf)) return false;

        if($sdf['type'] == 'normal'){
            $pre_update_md5 = md5($sdf['barcode'].$sdf['name'].$sdf['spec_info']);
            $this->pre_update_md5 = $pre_update_md5;
        }
    }

    /**
    * 编辑货品成功后的操作
    * @param Array $sdf 货品编辑后的数据
    * @param String $msg 引用返回消息
    * @return void
    */
    public function after_update($sdf=array(),&$msg=''){
        if (empty($sdf)) return false;

        if($sdf['type'] == 'normal'){
            $after_update_md5 = md5($sdf['barcode'].$sdf['name'].$sdf['spec_info']);
            if($this->pre_update_md5 != $after_update_md5){
                $skuObj = kernel::single('console_foreign_sku');
                //将编辑过后的商品 状态更改为编辑后同步
                $rs = $skuObj->update_sync_status($sdf['bn']);
                $msg = $rs ? '货品信息已被编辑，需重新同步至第三方仓库' : '';
            }
        }
        return $rs;
    }

    /**
    * 删除货品时的操作
    * @param Int $product_id 货品ID
    * @return void
    */
    public function delete_product($product_id=''){
        if (empty($product_id)) return false;

        $skuObj = kernel::single('console_foreignsku');
        if($skuObj->delete_sku($product_id)){
            return true;
        }else return false;
    }

    //同步全部商品
    public function sync_all($filter)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');

        @ini_set('memory_limit','128M');
        $wfsObj = app::get('console')->model('foreign_sku');
        $db = kernel::database();
        $data = kernel::single('channel_func')->getWmsChannelList();
        $view = $filter['view'];
        if($view != ''){
            $desktop_filter_model = app::get('desktop')->model('filter');
            $_desktop_filter = $desktop_filter_model->getList('*',array('model'=>'console_mdl_foreign_sku'));

            $_count = count($data);
            $_filter = $_desktop_filter[(int)($view-$_count[0]['_count']-1)];
            $_filter_query = array();
            parse_str($_filter['filter_query'],$_filter_query);
            $filter = array_merge($filter,$_filter_query);
        }

        //选择全部wms时单独处理
        if($filter['wms_id'] == '0'){

            $wms_id = array();
            foreach($data as $v){
                $wms_id[] = $v['wms_id'];
            }
            $filter['wms_id'] = (array)$wms_id;
        }
        #error_log('filter:'.var_export($filter,1),3,__FILE__.'.log');
        $sql_counter = " SELECT count(*) ";
        $sql_list = " SELECT * ";
        $wfsObj->filter_use_like = true;
        $sql_base = ' FROM `sdb_console_foreign_sku` WHERE '.$wfsObj->_filter($filter);
        $sql = $sql_counter . $sql_base;
        #error_log('sql:'.$sql."\n",3,__FILE__.'.log');
        $count = $db->count($sql);
        $limit = 500;
        if ($count){
            $pagecount = ceil($count/$limit);
            for ($page=1;$page<=$pagecount;$page++){
                $offset = ($page-1) * $limit;
                $sql = $sql_list.$sql_base." ORDER BY `fsid` LIMIT ".$offset.",".$limit;
                #error_log('sql1:'.$sql."\n",3,__FILE__.'.log');
                $products = $db->select($sql);
                if ($products){
                    $product_ids = array();
                    foreach ($products as $p){
                        $product_ids[] = $p['inner_product_id'];
                    }
                }

                $products_sdf = $this->getProductSdf($product_ids);


                $this->syncProduct_notifydata($filter['wms_id'],$products_sdf,$filter['branch_bn']);
                $products_sdf = $product_ids = $products = NULL;
            }
        }

        return true;
    }

    public function doadd ($sdf){
        if (!is_array($sdf)) return $this->msg->get_flag('success');

        $basicMaterialLib    = kernel::single('material_basic_material');

        $product_sdf = array();
        foreach($sdf as $key=>$product_id)
        {
            $tmp    = $basicMaterialLib->getBasicMaterialExt($product_id);

            if ($tmp)
            {
                $product_sdf[]    = $tmp;
            }
        }

        // 商品同步
        $this->syncProduct_notifydata($sdf['wms_id'], $product_sdf);
        return $this->msg->get_flag('success');
    }

    /**
    *通过wms_id获取未分派的商品
    *@params $wms_id wms_id
    *return array 未分派的商品
    **/
    public function get_goods_by_wms($wms_id,$offset='0',$limit='999999')
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $codebaseObj = app::get('material')->model('codebase');
        $data           = array();
        $product_ids    = array();

        $wfsObj    = app::get('console')->model('foreign_sku');
        $temp_data    = $wfsObj->getList('inner_product_id', array('wms_id'=>$wms_id));

        if($temp_data)
        {
            foreach ($temp_data as $key => $val)
            {
                $product_ids[]    = $val['inner_product_id'];
            }
        }

        $data    = $basicMaterialSelect->getlist('bm_id, material_bn, material_name', array('bm_id|notin'=>$product_ids));

        foreach ($data as &$v){
            $codebase = $codebaseObj->dump(array('bm_id'=>$v['product_id']),'code');

            $v['barcode'] = $codebase['code'];
        }
        return $data;
    }

    /**
    *通过wms_id获取未分派的商品
    *@params $wms_id wms_id
    *@params $search_key 搜索的键
    *@params $search_value 搜索的值
    *return array 未分派的商品
    **/
    public function get_data_by_search($search_key,$search_value,$wms_id)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');

        $data['search_key'] = $search_key;
        $data['search_value'] = $search_value;
        $data['wms_id'] = $wms_id;
        $product_ids = $this->get_filter($data);
        $limt = 10;
        $product_ids_tmp = array_chunk($product_ids,$limt);
        $db = kernel::database();
        for($i=0;$i<(count($product_ids) / $limt);$i++)
        {
            $res[]    = $basicMaterialSelect->getlist('bm_id, material_bn, material_name', array('bm_id'=>$product_ids_tmp[$i]));
        }
        $result = array();
        foreach($res as $key=>$value){
            foreach($value as $v){
                $result[] = $v;
            }
        }
        return $result;
    }


    /**
    *通过wms_id获取未分派的商品的数量
    *@params $wms_id wms_id
    *return array 未分派的商品
    **/
    public function get_goods_count_by_wms($wms_id)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $data           = array();
        $product_ids    = array();

        $wfsObj    = app::get('console')->model('foreign_sku');
        $temp_data    = $wfsObj->getList('inner_product_id', array('wms_id'=>$wms_id));

        if($temp_data)
        {
            foreach ($temp_data as $key => $val)
            {
                $product_ids[]    = $val['inner_product_id'];
            }
        }

        $data    = $basicMaterialSelect->getlist('bm_id, material_bn, material_name', array('bm_id|notin'=>$product_ids));

        $tmp[0]['count'] = count($data);
        return $tmp;
    }

    /**
    *通过product_id获取未分派的商品的数量
    *@params array $product_id
    *return array 未分派的商品
    **/
    public function get_wms_goods($product_id)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');

        $data    = $basicMaterialSelect->getlist('bm_id, material_bn, material_name', array('bm_id'=>$product_id));

        return $data;
    }

    /**
    *获取自定义搜素选项
    *return array
    **/
    public function get_search_options(){
        $options = array(
            'bn'=>'货品编码',
            'name'=>'货品名称',
            'brand'=>'商品品牌',
            'barcode'=>'条码',
        );
        return $options;
    }

    /**
    *获取自定义搜素选项
    *return array
    **/
    public function get_search_list(){
        $brandObj = app::get('ome')->model('brand');
        //$packObj = app::get('ome')->model('pack');//
        $brand_tmp =$brandObj->getList('brand_name,brand_id');
        $brand = array();
        foreach($brand_tmp as $branddata){
            $brand[$branddata['brand_id']] = $branddata['brand_name'];
        }
        //$pack_tmp = $packObj->getList('pack_name');//
        $pack = array();
        //foreach($pack_tmp as $packdata){
           // $pack[] = $packdata['pack_name'];
        //}
        $list = array(
            'brand'=>$brand,
            //'pack'=>$pack,
        );
        return $list;
    }

    /**
    *组织filter条件
    *return array
    **/
    public function get_filter($data,$offset='0',$limit='999999'){
        if(empty($data['search_key']) || empty($data['search_value'])){
            return false;
        }
        $db = kernel::database();

        $wfsObj    = app::get('console')->model('foreign_sku');
        $temp_data    = $wfsObj->getList('inner_product_id', array('wms_id'=>$data['wms_id'], 'inner_type'=>'0'));

        $bm_ids    = 0;
        if($temp_data)
        {
            foreach ($temp_data as $key => $val)
            {
                $product_ids[]    = $val['inner_product_id'];
            }
            $bm_ids    = implode(',', $product_ids);
        }

        switch ($data['search_key']) {
            case 'bn':
                $sql    = "SELECT bm_id AS product_id, material_name AS name, material_bn AS bn
                           FROM sdb_material_basic_material WHERE material_bn like '".$data['search_value']."%'
                           AND bm_id not in(". $bm_ids .") AND visibled=1 limit ". $offset .",". $limit;
                $row = $db->select($sql);
                break;

            case 'name':
                $sql    = "SELECT bm_id AS product_id, material_name AS name, material_bn AS bn
                           FROM sdb_material_basic_material WHERE material_name like '".$data['search_value']."%'
                           AND bm_id not in(". $bm_ids .") AND visibled=1 limit ". $offset .",". $limit;
                $row = $db->select($sql);
                break;

            case 'brand':
                $sql    = "SELECT bm_id AS product_id, material_name AS name, material_bn AS bn
                           FROM sdb_material_basic_material WHERE bm_id not in(". $bm_ids .")
                           AND visibled=1 limit ". $offset .",". $limit;
                $row = $db->select($sql);
                break;

            case 'pack':

                break;
            case 'barcode':
                $sql    = "SELECT m.bm_id AS product_id, m.material_name AS name, m.material_bn AS bn
                           FROM sdb_material_basic_material as m LEFT JOIN sdb_material_codebase as b ON m.bm_id=b.bm_id WHERE b.code like '".$data['search_value']."%'
                           AND m.bm_id not in(". $bm_ids .") AND m.visibled=1 limit ". $offset .",". $limit;

                $row = $db->select($sql);
                break;
        }

        $data = array();
        foreach($row as $v){
            $data[] = $v['product_id'];
        }
        return $data;
    }

    /*通过搜索条件获取未分派的商品
    *@params $filter 搜索条件
    *return array 未分派的商品
    **/
    public function get_goods_by_product_ids($product_ids)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $db      = kernel::database();
        $data    = array();
        $codebaseObj = app::get('material')->model('codebase');
        if($product_ids)
        {
            $data    = $basicMaterialSelect->getlist('bm_id, material_bn, material_name', array('bm_id'=>$product_ids));
            foreach ($data as &$v){
                $codebase = $codebaseObj->dump(array('bm_id'=>$v['product_id']),'code');

                $v['barcode'] = $codebase['code'];
            }
        }

        return $data;
    }

    /*通过搜索条件获取未分派的商品数量
    *@params $data 搜索条件
    *return array 未分派的商品
    **/
    public function get_goods_count_by_search($data)
    {
        $db = kernel::database();

        $wfsObj    = app::get('console')->model('foreign_sku');
        $temp_data    = $wfsObj->getList('inner_product_id', array('wms_id'=>$data['wms_id']));

        $bm_ids    = 0;
        if($temp_data)
        {
            foreach ($temp_data as $key => $val)
            {
                $product_ids[]    = $val['inner_product_id'];
            }
            $bm_ids    = implode(',', $product_ids);
        }

        switch ($data['search_key']) {
            case 'bn':
                $sql    = "SELECT count(*) AS count
                           FROM sdb_material_basic_material WHERE material_bn like '".$data['search_value']."%'
                           AND bm_id not in(". $bm_ids .") AND visibled=1";
                $row = $db->select($sql);
                break;

            case 'name':
                $sql    = "SELECT count(*) AS count
                           FROM sdb_material_basic_material WHERE material_name like '".$data['search_value']."%'
                           AND bm_id not in(". $bm_ids .") AND visibled=1";
                $row = $db->select($sql);
                break;

            case 'brand':
                $sql    = "SELECT count(*) AS count
                           FROM sdb_material_basic_material WHERE bm_id not in(". $bm_ids .")
                           AND visibled=1";
                $row = $db->select($sql);
                break;

            case 'pack':

                break;
            case 'barcode':
                $sql    = "SELECT count(m.bm_id)  AS count
                           FROM sdb_material_basic_material as m LEFT JOIN sdb_material_codebase as b ON m.bm_id=b.bm_id WHERE b.code like '".$data['search_value']."%'
                           AND m.bm_id not in(". $bm_ids .") AND m.visibled=1";

                $row = $db->select($sql);
                break;
        }

        return $row[0]['count'];
    }

    /**
    * 获得非selfwms wms_id数组
    * flag notequal
    */
    function get_wms_list($type,$flag='') {
        $wms_list = kernel::single('channel_func')->getWmsChannelList();

        $wms = array();
        if ($flag=='notequal') {
            foreach ($wms_list as $v) {
                if ($v['adapter']!=$type) {
                    $wms[] = $v['wms_id'];
                }
            }

        }else{
            foreach ($wms_list as $v) {
                if ($v['adapter']==$type) {
                    $wms[] = $v['wms_id'];
                }
            }
        }

        return $wms;


    }

    /**
    * 商品同步发起通知数据
    */
    function syncProduct_notifydata($wms_id,$product_sdf,$branch_bn='') {
        if (is_array($product_sdf) && $wms_id) {
            foreach($product_sdf as $key=>$item ){
                $inner_type = 0;
                if ($item['type'] == 'pkg'){
                    $inner_type = '1';
                }
                $sku_info = kernel::single('console_foreignsku')->sku_info($item['bn'],$wms_id,$inner_type);
                // 组织sku数据
                $method = $sku_info['sync_status'] == 4 ? 'update' : 'create';
                $item['branch_bn'] = $branch_bn;

                if ($branch_bn) {
                    $branch = app::get('ome')->model('branch')->dump([
                        'branch_bn' => $branch_bn,
                        'check_permission' => 'false',
                    ],'owner_code');

                    $item['owner_code'] = $branch['owner_code'];
                }


                $params = [$item];
                kernel::single('console_event_trigger_goodssync')->$method($wms_id, $params, false);
            }
        }
        return true;
    }

    public function getProductSdf($product_ids) {
        if(empty($product_ids) || !is_array($product_ids)) {
            return [];
        }
        $sql = 'SELECT p.type as material_type,
                    p.material_bn as bn,
                    p.material_name as name,
                    p.bm_id as product_id,
                    p.serial_number,
                    c.code as barcode,
                    gext.retail_price as price,
                    gext.weight,
                    gext.unit,
                    gext.box_spec,
                    gext.length,
                    gext.width,
                    gext.high,
                    gext.specifications as property,
                    t.addon,
                    gconf.use_expire_wms,
                    gconf.shelf_life,
                    gconf.reject_life_cycle,
                    gconf.lockup_life_cycle,
                    gconf.advent_life_cycle,p.cat_id as good_cat_id
                    FROM sdb_material_basic_material as p 
                    LEFT JOIN sdb_material_basic_material_ext as gext on p.bm_id=gext.bm_id 
                    LEFT JOIN sdb_material_basic_material_conf as gconf on p.bm_id=gconf.bm_id 
                    LEFT JOIN sdb_material_codebase as c on p.bm_id=c.bm_id 
                    LEFT JOIN sdb_ome_goods_type as t ON gext.cat_id =t.type_id 
                    WHERE p.bm_id IN ('.implode(',',$product_ids).')';
        $product_sdf = kernel::database()->select($sql);
        $cat_ids = array_column($product_sdf, 'good_cat_id');
        if($cat_ids){
            $cats = $this->getcats($cat_ids);
        }
        //props
        $propsMdl = app::get('material')->model('basic_material_props');

        $propslist = $propsMdl->getlist('props_col,props_value,bm_id',array('bm_id'=>$product_ids));

        $arr_props = array();
        if($propslist){
            foreach($propslist as $v){

                $arr_props[$v['bm_id']][$v['props_col']] = $v['props_value'];

            }
        }
        

        foreach($product_sdf as $pk=>$pv){
            $product_id = $pv['product_id'];
            $props = $arr_props[$product_id] ? $arr_props[$product_id] : array();
            $product_sdf[$pk]['props'] = $props;

            $good_cat_id = $pv['good_cat_id'];

            if($good_cat_id){
                
                $cat_name = $cats[$good_cat_id] ? $cats[$good_cat_id]['cat_name'] : '';

                $product_sdf[$pk]['cat_name'] = $cat_name;
            }
        }

        return $product_sdf;
    }
    
    /**
     * 商品同步所有已绑定的wms
     * @param $bmIds array
     * @return array
     * @date 2025-01-02 6:18 下午
     */
    public function addProductSyncWms($bmIds)
    {
        if (empty($bmIds)) {
            return [false, '缺少基础物料ID'];
        }
        
        if (!is_array($bmIds)) {
            return [false, '基础物料ID需传数组'];
        }
        
        $channelList = app::get('channel')->model('channel')->getList('channel_id,channel_type,node_type,node_id', [
            'channel_type' => 'wms',
            'node_type'    => 'qimen',
            'filter_sql'   => 'node_id IS NOT NULL AND node_id!=""',
        ]);
        if (!$channelList) {
            return [false, '未检测到第三方仓储'];
        }
    
        

        $branchMdl = app::get('ome')->model('branch');
        $foreignObj = app::get('console')->model('foreign_sku');
        foreach($channelList as $channe){
            $products_sdf = $this->getProductSdf($bmIds);
            if (!$products_sdf) {
                continue;
            }
    
            foreach ($products_sdf as $product) {
                $inner_type = $product['material_type']=='4' ? '2' : '0';
                $upData = array(
                    'inner_sku'        => $product['bn'],
                    'inner_product_id' => $product['product_id'],
                    'wms_id'           => $channe['channel_id'],
                    'inner_type'       => $inner_type, 
                );
                $oldRow = $foreignObj->db_dump(array('inner_sku' => $upData['inner_sku'], 'wms_id' => $upData['wms_id']), 'fsid');
                if (!$oldRow) {
                    $foreignObj->insert($upData);
                }
            }
            $this->syncProduct_notifydata($channe['channel_id'], $products_sdf);

            $branchList = $branchMdl->getList('branch_id,branch_bn,name,wms_id',array(
                'wms_id'=>$channe['channel_id'], 
                'b_type'=>'1',
                'type' => 'main',
            ));

            $branchs = current($branchList);
          
            foreach($products_sdf as $products){
                if($products['material_type']!='4') continue;
                kernel::single('console_event_trigger_goodssync')->checkSynCombine($products['product_id'],$branchs);
                
                kernel::single('console_event_trigger_goodssync')->syncCombination($products['product_id'], $branchs['branch_id']);
            }
            
        }
        
        return [true, '完成'];
    }


    public function getcats($cat_ids){


        $catMdl = app::get('material')->model('basic_material_cat');
        $cats = $catMdl->getlist('cat_id,cat_name',array('cat_id'=>$cat_ids));
        if($cats){
            $cats = array_column($cats,null,'cat_id');
            return $cats;
        }
        
    }
}
