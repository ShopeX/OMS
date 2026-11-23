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

class console_mdl_stockdump extends dbeav_model{

    // var $defaultOrder = array('create_time',' DESC');

    //是否有导出配置
    var $has_export_cnf = true;
    var $export_name = '转储单';

    public function modifier_type($row){
        $info = kernel::single('siso_receipt_iostock')->get_iostock_types();
        return $info[$row]['info'];
    }

    function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

    function io_title( $filter, $ioType='csv' ){

        switch( $ioType ){
             case 'csv':
                 $this->oSchema['csv']['title'] = array(
                                                    '*:调出仓库' => 'from_branch_name',
                                                    '*:调入仓库' => 'to_branch_name',
                                                    '*:备注' => 'memo',
                 );
                $this->oSchema['csv']['items'] = array(
                                                    '*:货号' =>'bn',
                                                    '*:名称' =>'product_name',
                                                    '*:数量' => 'num',
                                                    '*:价格' => 'appro_price',
                 );

             break;
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
     }

   //出入库单保存
    function to_savestore($adata,$options=array(),&$appro_data=array(), &$errmsg = '')
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $libBranchProduct    = kernel::single('ome_branch_product');
        
        $tran = kernel::database()->beginTransaction();

        $result = array();
        $oStockdump_items = $this->app->model("stockdump_items");
        
        $appro_data = array(
            'stockdump_bn'=> empty($options['use_third_party'])?$this->get_appro_bn($options['type']):$options['stockdump_bn'],
            'type'=>$options['type'],
            #'in_status' => $options['in_status'],
            #'confirm_type' => $options['confirm_type'] != '' ? $options['confirm_type'] : '1',
            'create_time'=>time(),
            #'otype'=>$options['otype'],
            'operator_name'=>$options['op_name'],
            'from_branch_name'=>$options['from_branch_name'],
            'to_branch_id'=>$options['to_branch_id']=='' ? 0:$options['to_branch_id'],
            'from_branch_id'=>$options['from_branch_id']=='' ? 0:$options['from_branch_id'],
            'to_branch_name'=>$options['to_branch_name'],
            'memo'=>$options['memo'],

        );
        
        if ($options['source_from']) $appro_data['source_from'] = $options['source_from'];
        if ($options['transfer_channel']) $appro_data['transfer_channel'] = $options['transfer_channel'];
        if(!$this->app->model('stockdump')->save($appro_data)){
            kernel::database()->rollBack();

            $errmsg = $this->db->errorinfo();
            return false;
        }
        
        $break = false;
        $branch_id = $appro_data['from_branch_id'];
        
        //保存明细
        foreach($adata as $k=>$v){
            $product_id = $v['product_id'];
            $num = $v['num'];
            
            $product     = $basicMaterialObj->dump(array('bm_id'=>$v['product_id']), 'bm_id, material_bn, material_name');
            
            $items_data = array(
                'stockdump_id'=>$appro_data['stockdump_id'],
                'stockdump_bn'=>$appro_data['stockdump_bn'],
                'bn'=>$product['material_bn'],
                'product_name'=>$product['material_name'],
                'product_id'=>$v['product_id'],
                'product_size'=>$v['product_id'],
                'num'=>$v['num'],
                'appro_price'=>$v['appro_price'],
                'in_nums' => '0',
            );
            
            //保存items
            if(!$oStockdump_items->save($items_data)){
                kernel::database()->rollBack();

                $errmsg = $this->db->errorinfo();
                return false;
            }

            if($break == true) break;
            
        }
                
        //库存管控处理
        $storeManageLib    = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id'=>$branch_id));
        
        $params    = array();
        $params['node_type'] = 'saveStockdump';
        $params['params']    = array('stockdump_id'=>$appro_data['stockdump_id'], 'branch_id'=>$branch_id);
        $params['params']['items'] = $adata;

        $processResult    = $storeManageLib->processBranchStore($params, $errmsg);
        if(!$processResult)
        {
            kernel::database()->rollBack();
            return false;
        }
        kernel::database()->commit($tran);
        return  $appro_data;
  }
    
    /**
    * 生成调拨单号
    *
    **/
    function get_appro_bn($type){
        $iostcok = kernel::single("ome_iostock");
        return $iostcok->get_iostock_bn($type);
    }

    /**
    * 获取出入库主表数据
    * @access public
    * @param Number $stock_id 出入库单ID
    * @return 主表数据 
    */
    function detail($stock_id){
        if (empty($stock_id)) return NULL;

        $sql = sprintf('SELECT * FROM `sdb_console_stockdump` WHERE appropriation_id=\'%s\'',$stock_id);
        $detail = $this->db->selectrow($sql);
        return $detail;
    }

    /**
    * 获取出入库商品总金额
    * @access public
    * @param Number $stock_id 出入库单ID
    * @return 总金额 
    */
    function total_money($stock_id){
        if (empty($stock_id)) return NULL;

        $sql = sprintf('SELECT sum(appro_price) AS total_amount FROM `sdb_console_stockdump`_items` WHERE appropriation_id=\'%s\'',$stock_id);
        $tmp = $this->db->selectrow($sql);
        $total_amount = $tmp['total_amount'];
        return $total_amount;
    }
	
    /*快速搜素*/
    function searchOptions(){
        $arr = parent::searchOptions();
        return array_merge($arr,array(
                'finder_bn'=>__('货号'),
            ));
    }
	/*
	*搜素条件
	*/
	   function _filter($filter,$tableAlias=null,$baseWhere=null){
        if(isset($filter['finder_bn'])){
            $where .= " AND `stockdump_id` in (SELECT `stockdump_id` FROM `sdb_console_stockdump_items` WHERE `bn` ='".$filter['finder_bn']."') ";
            unset($filter['finder_bn']);
        }

        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    function cancel($stockdump_id){
        
        $data = array(
            'stockdump_id'=> $stockdump_id,
            'self_status' =>'0',
        );
        return $this->save($data);

    }
    
    function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }

    //导入
    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        
        if (empty($row)){

            if ($this -> item_exist == false) {
                $msg['error'] = "采购单中没有货品";
                return false;
            }

            if ($this->flag){
                if ($this->not_exist_product_bn){
                    $temp = $this->not_exist_product_bn;
                    $tmp = array_unique($temp);
                    sort($tmp);
                    $msg['error'] .= '\n数据库中不存在的商品货号：';
                    $ms = '';
                    foreach ($tmp as $k => $v){
                        if ($k >= 10){
                            $ms = '...\n';
                            break;
                        }
                        if ($k < 5){
                            $tmp1[] = $v;
                            continue;
                        }
                        $tmp2[] = $v;
                    }
                    $msg['error'] .= '\n'.implode(',', $tmp1);
                    if (!empty($tmp2)) $msg['error'] .= '\n'.implode(',', $tmp2);
                    $msg['error'] .= $ms;
                    $tmp1 = null;
                    $tmp2 = null;
                }
                if ($this->not_exist_branch_product) {
                    $temp = $this->not_exist_branch_product;
                    $tmp = array_unique($temp);
                    sort($tmp);
                    $msg['error'] .= '\n数据库中不存在的货号与出库仓关系：';
                    $ms = '';
                    foreach ($tmp as $k => $v){
                        if ($k >= 10){
                            $ms = '...\n';
                            break;
                        }
                        if ($k < 5){
                            $tmp1[] = $v;
                            continue;
                        }
                        $tmp2[] = $v;
                    }
                    $msg['error'] .= '\n'.implode(',', $tmp1);
                    if (!empty($tmp2)) $msg['error'] .= '\n'.implode(',', $tmp2);
                    $msg['error'] .= $ms;
                    $tmp1 = null;
                    $tmp2 = null;
                }
                if ($this->not_exist_unable_store) {
                    $temp = $this->not_exist_unable_store;
                    $tmp = array_unique($temp);
                    sort($tmp);
                    $msg['error'] .= '\n库存不足此次调出：';
                    $ms = '';
                    foreach ($tmp as $k => $v){
                        if ($k >= 10){
                            $ms = '...\n';
                            break;
                        }
                        if ($k < 5){
                            $tmp1[] = $v;
                            continue;
                        }
                        $tmp2[] = $v;
                    }
                    $msg['error'] .= '\n'.implode(',', $tmp1);
                    if (!empty($tmp2)) $msg['error'] .= '\n'.implode(',', $tmp2);
                    $msg['error'] .= $ms;
                    $tmp1 = null;
                    $tmp2 = null;
                }
                if ($this->same_product_bn){
                    $temp = $this->same_product_bn;
                    $tmp = array_unique($temp);
                    sort($tmp);
                    $msg['error'] .= '\n文件中重复的商品货号：';
                    $ms = '';
                    foreach ($tmp as $k => $v){
                        if ($k >= 10){
                            $ms = '...\n';
                            break;
                        }
                        if ($k < 5){
                            $tmp1[] = $v;
                            continue;
                        }
                        $tmp2[] = $v;
                    }
                    $msg['error'] .= '\n'.implode(',', $tmp1);
                    if (!empty($tmp2)) $msg['error'] .= '\n'.implode(',', $tmp2);
                    $msg['error'] .= $ms;
                    $tmp1 = null;
                    $tmp2 = null;
                }
                base_kvstore::instance('console_stockdump')->store('stockdump-'.$this->ioObj->cacheTime,'');
                return false;
            }
            return true;
        }
        
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        $branchObj = app::get('ome')->model('branch');
        $branch_prObj = app::get('ome')->model('branch_product');
        
        $mark = false;
        $re = base_kvstore::instance('console_stockdump')->fetch('stockdump-'.$this->ioObj->cacheTime,$fileData);
        if( !$re )
            $fileData = array();

        if( substr($row[0],0,1) == '*' ){
            $titleRs =  array_flip($row);
            $mark = 'title';
            $this -> item_exist = false;
            return $titleRs;
        }else{
            if( $row[0] ){
                $row[0] = trim($row[0]);
                if( array_key_exists( '*:货号',$title )  ) {
                    $this -> item_exist = true;
                    // 过滤掉0的数据
                    $num = trim($row[2]);
                    if (!is_numeric($num) || !$num) {
                        return null;
                    }
                    $stockdump = $fileData['stockdump'];
                    $from_branch_id = $stockdump['from_branch_id'];
                    
                    $p    = $basicMaterialObj->dump(array('material_bn'=>$row[0]), 'bm_id, material_bn');
                    
                    $product_id = $p['bm_id'];
                    if(!$p){
                        $this->flag = true;
                        $this->not_exist_product_bn = isset($this->not_exist_product_bn)?array_merge($this->not_exist_product_bn,array($row[0])):array($row[0]);
                    }
                    
                    $branch_product = $branch_prObj->dump(array('branch_id'=>$from_branch_id,'product_id'=>$p['bm_id']));
                    if (!$branch_product) {
                        $this->flag = true;
                        $this->not_exist_branch_product= isset($this->not_exist_branch_product)?array_merge($this->not_exist_branch_product,array($row[0])):array($row[0]);
                    }
                    
                    //获取单仓库-单个基础物料中的可用库存
                    $usable_store    = $libBranchProduct->get_available_store($from_branch_id, $product_id);
                    
                    if ($usable_store<$num ) {
                        // $this->flag = true;
                        // $this->not_exist_unable_store= isset($this->not_exist_unable_store)?array_merge($this->not_exist_unable_store,array($row[0])):array($row[0]);

                        $msg['error'] = sprintf('【%s】库存不足，调出数量：%s，可用库存：%s', $row[0], $num, $usable_store);
                        return false;
                    }

                    if ($fileData['item']){
                        foreach ($fileData['item'] as $v){

                            if (trim($row[0]) == trim($v['bn'])){
                                $this->flag = true;
                                $this->same_product_bn = isset($this->same_product_bn)?array_merge($this->same_product_bn,array($row[0])):array($row[0]);
                            }
                        }
                    }
                    $items = array(
                        'num'=>$num,
                        'product_id'=>$product_id,
                        'bn'=>$row[0],
                        'product_name'=>$row[1],
                        'appro_price'=>$row[3],
                    );
                    $fileData['item'][] = $items;
                }else {
                    
                    $from_branch_name = $row[0];
                    $to_branch_name = $row[1];
                    $memo = $row[2];
                    $from_branch = $branchObj->dump(array('name'=>trim($from_branch_name)),'branch_id');
                    if (!$from_branch) {
                        $msg['error'] .= '\n调出仓不存在';
                        return false;
                    }
                    
                    $to_branch = $branchObj->dump(array('name'=>trim($to_branch_name)),'branch_id');
                    if (!$to_branch) {
                        $msg['error'] .= '\n调入仓不存在';
                        return false;
                    }
                    $main = array(
                        'from_branch_id'=>  $from_branch['branch_id'],
                        'to_branch_id'=>$to_branch['branch_id'],
                        'memo'=>$memo,
                    );
                    unset($from_branch,$to_branch);
                    $fileData['stockdump']= $main;
                }
                
                base_kvstore::instance('console_stockdump')->store('stockdump-'.$this->ioObj->cacheTime,$fileData, 10800);
            }

        }
        return null;
    }

    function finish_import_csv(){
        set_time_limit(0);
        base_kvstore::instance('console_stockdump')->fetch('stockdump-'.$this->ioObj->cacheTime,$data);
        base_kvstore::instance('console_stockdump')->store('stockdump-'.$this->ioObj->cacheTime,'');
        $oQueue = app::get('base')->model('queue');
        $op_name = kernel::single('desktop_user')->get_name();
        $op_name = $op_name ? $op_name : 'system';
        $sto_sdf = array(
            'op_name' => $op_name,
            'from_branch_id' => $data['stockdump']['from_branch_id'],
            'to_branch_id' => $data['stockdump']['to_branch_id'],
            'memo' => $data['stockdump']['memo'],
        );
        $items = array();
        foreach ($data['item'] as $item ) {
            $items[] = array(
                'num'=>$item['num'],
                'product_id'=>$item['product_id'],
                'appro_price'=>$item['appro_price'],
            );
        }

        if (!$items) {
            return null;
        }

        $sto_sdf['items'] = $items;
        $queueData = array(
            'queue_title'=>'转储单导入',
            'start_time'=>time(),
            'params'=>array(
                'sdfdata'=>$sto_sdf,
                'app' => 'stockdump',
                'mdl' => 'console'
            ),
            'worker'=>'console_stockdump_to_import.run',
        );
        
        $oQueue->save($queueData);

        return null;
    }

    public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false)
    {
        
       
        $items = $this->db->select("SELECT bn,product_name,num,in_nums,defective_num FROM sdb_console_stockdump_items WHERE stockdump_id in(".implode(',', $filter['stockdump_id']).")");
        

        $row_num = 1;
        foreach($items as $item){//bn,product_name,num,in_nums,defective_num
            $itemRow = array();
            $itemRow['*:货号']   = mb_convert_encoding($item['bn'], 'GBK', 'UTF-8');
            $itemRow['*:商品名称'] = mb_convert_encoding($item['product_name'], 'GBK', 'UTF-8');
            $itemRow['*:申请数量'] = $item['num'];
            $itemRow['*:良品'] = $item['in_nums'];
            $itemRow['*:不良品'] = $item['defective_num'];
            

            $data[$row_num] = implode(',', $itemRow );
            $row_num++;
        }
                
        

        //明细标题处理
        if($data && $has_title){
            $title = array(
                '*:货号' => '',
                '*:商品名称' => '',
                '*:申请数量' => '',
                '*:良品' => '',
                '*:不良品' => '',
                
            );

            foreach ((array)$title as $key => $value) {
                $title[$key] = mb_convert_encoding($key, 'GBK', 'UTF-8');
            }

            $data[0] = implode(',', $title);
        }

        ksort($data);
        return $data;
    }
}