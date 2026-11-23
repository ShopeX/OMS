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

class console_mdl_inventory_apply extends dbeav_model{
    var $has_export_cnf = true;
    var $export_name = '盘点表';
    var $export_flag = false;
    var $has_many = array(
        'inventory_apply_items' => 'inventory_apply_items',
    );
    
    function gen_id(){
        return 'S'.date("mdHis").rand(0,9).rand(0,9);
    }
    
    public function modifier_negative_branch_id($c,$list,$row){
        $bid = json_decode($c, 1);
        $branchList = app::get('ome')->model('branch')->getList('branch_bn', ['branch_id'=>$bid,'check_permission'=>'false']);
        return implode(' | ', array_column($branchList, 'branch_bn'));
    }

    function get_branch_by_wms($wms_id){
        $sql = "SELECT wb.* FROM sdb_ome_branch as wb WHERE wb.type in('main','damaged') AND wb.wms_id = '".$wms_id."'";

        $branch = kernel::database()->select($sql);

        return $branch;
    }
    
    function exist_product($product_id)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        $product     = $basicMaterialObj->dump(array('bm_id'=>$product_id), 'bm_id, material_bn');
        
        if ($product)
        {
            return true;
        }
        return false;
    }
    
    function exist_branch($product_id, $branch_bn){
        $sql = "SELECT branch_id FROM sdb_ome_branch WHERE branch_bn = '".addslashes($branch_bn)."'";
        $branch = kernel::database()->selectrow($sql);
        $sql = "SELECT store FROM sdb_ome_branch_product WHERE product_id = ".intval($product_id)." AND branch_id = ".intval($branch['branch_id']);
        
        $product = kernel::database()->selectrow($sql);
        
        if ($product){
            return true;
        }
        return false;
    }
    
    function exist_num($product_id, $num)
    {
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $product     = $basicMaterialLib->getBasicMaterialStock($product_id);
        
        if ($product && ($product['store'] + $num) >= 0)
        {
            return true;
        }
        return false;
    }
    
    function exist_branch_num($product_id, $branch_bn, $num){
        $sql = "SELECT branch_id FROM sdb_ome_branch WHERE branch_bn = '".addslashes($branch_bn)."'";
        $branch = kernel::database()->selectrow($sql);
        $sql = "SELECT store FROM sdb_ome_branch_product WHERE product_id = ".intval($product_id)." AND branch_id = ".intval($branch['branch_id']);
        $product = kernel::database()->selectrow($sql);
        
        if ($product && ($product['store'] + $num) >= 0){
            return true;
        }
        return false;
    }
    
    function io_title( $filter=null,$ioType='csv' ){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['apply'] = array(
                    '*:盘点流水单号' => 'inventory_apply_bn',
                    '*:盘点日期' => 'date',
                    '*:备注' => 'memo',
                );
                $this->oSchema['csv']['item'] = array(
                    '*:货号' => 'bn',
                    '*:商品名称' => 'name',
                    //'*:盘点结果' => 'quantity',
                    '*:良品' => 'normal_num',
                    '*:不良品' => 'defective_num',
                );
                break;
        }
        $this->ioTitle[$ioType]['apply'] = array_keys( $this->oSchema[$ioType]['apply'] );
        $this->ioTitle[$ioType]['item'] = array_keys( $this->oSchema[$ioType]['item'] );
        return $this->ioTitle[$ioType][$filter];
     }
     
    //csv导出
    function fgetlist_csv( &$data, $filter, $offset, $exportType = 1 ){
        @ini_set('memory_limit','128M');//设置当前PHP的处理内存
        //set_time_limit(60);//设置超时时间
        //error_log(print_r($filter,1),3,'e:/eeee.log');
        if ($offset == 1) return null;
        if (!$filter) $filter['inventory_apply_id'] = $_GET['inventory_apply_id'];
        $applySdf = $this->dump($filter, '*', array('inventory_apply_items'=>'*'));
        if (!$applySdf) return false;
        
        $data['contents'] = array();
        $branch = $this->get_branch_by_wms($applySdf['wms_id']);

        $title_item = array();
        //$content_item = array();
        $i = 1;
        foreach ($branch as $bi){
            $title_item[] = $bi['branch_bn'];
            $title_item[] = '盈亏'.$i++;
            //$content_item[] = $bi['branch_bn'];
            //$content_item[] = '盈亏';
            $branchs[] = $bi['branch_bn'];
        }
        //$publi_content = ',"'.implode('","',$content_item).'"';
        
        $title = array();
        foreach( $this->io_title('apply') as $k => $v ){
            $title[] = $v;
        }
        $data['title']['apply'] = '"'.implode('","',$title).'"';
        $title = array();
        foreach( $this->io_title('item') as $k => $v ){
            $title[] = $v;
        }
        $title = array_merge($title, $title_item);
        $data['title']['item'] = '"'.implode('","',$title).'"';
        
        $aApply = $applySdf;
        $aApply['date'] = date("Y年m月d日", $aApply['inventory_date']);

        foreach( $this->oSchema['csv']['apply'] as $k => $v ){
            $orderRow[$k] = utils::apath( $aApply,explode('/',$v) );
        }
        $data['content']['apply'][] = '"'.implode('","',$orderRow).'"';
        //处理子数据
        $branchlist = app::get('ome')->model('branch')->getList('branch_id',array('branch_bn'=>$branchs),0,-1);
        foreach ($branchlist as $b){
            $branch_ids[] = $b['branch_id'];
        }
        $ids = implode(',',$branch_ids);
        $error = '';
        foreach($applySdf['inventory_apply_items'] as $itemv){
            $sql = "SELECT bp.store,b.branch_bn FROM sdb_ome_branch_product bp 
                                JOIN sdb_ome_branch b 
                                    ON b.branch_id=bp.branch_id 
                                WHERE bp.product_id=".$itemv['product_id']." AND 
                                b.branch_id IN ($ids)";
            $rows = $this->db->select($sql);
            $branchdd = array();
            $num = 0;
            foreach ($rows as $row){
                $num += $row['store'];
                $branchdd[$row['branch_bn']] = ',"'.$row['store'].'"';
            }
            if ($itemv['quantity'] < 0 && (abs($itemv['quantity']) - $num) > 0){
                $error .= $itemv['bn'].":库存不足 <br/>";
            }
            
            $_tmp_ = '';
            foreach ($branchs as $branch_bn){
                if (isset($branchdd[$branch_bn])){
                    $_tmp_ .= $branchdd[$branch_bn].',"0"';
                }else {
                    $_tmp_ .= ',"-","0"';
                }
            }
            foreach( $this->oSchema['csv']['item'] as $k => $v ){
                $itemRow[$k] = utils::apath( $itemv,explode('/',$v) );
            }
            $data['content']['item'][] = '"'.implode('","',$itemRow).'"'.$_tmp_;
        }
        if ($error){
            exit($error);
        }
        return true;
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
      //  if( $exportType == 2 ){
            foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
      //  }
        return implode("\n",$output);
    }
    
    function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }

    function finish_import_csv(){
        $data = $this->import_data;
        unset($this->import_data);
        $objs = $data['apply']['contents'];
        $items = $data['item']['contents'];
        
        $apply = array_shift($objs);
        $applysdf = $this->dump(array('inventory_apply_bn'=>$apply[0]));
        if (!$applysdf) return false;
        
        $branch = $this->get_branch_by_wms($applysdf['wms_id']);
        
        foreach ($branch as $bi){
            $branchs[] = $bi['branch_bn'];
        }
        $branchlist = app::get('ome')->model('branch')->getList('branch_id',array('branch_bn'=>$branchs),0,-1);
        foreach ($branchlist as $b){
            $branch_ids[] = $b['branch_id'];
        }
        $inventoryObj = kernel::single('console_receipt_inventory');
        $ids = implode(',',$branch_ids);
        if ($items){
            $data_branch = array();
            foreach ($items as $item){
                $bn = $item[0];
                $name = $item[1];
                $nums = intval($item[2])+intval($item[3]);
                $start = 4;//仓库数据开始key
                $product = $this->app->model('inventory_apply_items')->dump(array('bn'=>$bn,'inventory_apply_id'=>$applysdf['inventory_apply_id']));
                $branch_s = array();
                while (true){
                    if (isset($item[$start]) && intval($item[$start+1]) != 0){
                        $branch_s[$item[$start]] = intval($item[$start+1]);
                        $start += 2;
                        continue;
                    }elseif (isset($item[$start])){
                        $start += 2;
                        continue;
                    }
                    break;
                }
                
                if ($branch_s)
                foreach ($branch_s as $branch_bn => $num){
                    $data_branch[$branch_bn][$product['product_id']] = array(
                        'bn'         => $product['bn'],
                        'name'       => $product['name'],
                        'memo'       => $product['memo'],
                        'normal_num'   => $num,
                        'product_id' => $product['product_id'],
                    );
                }
            }
            $tmp_sdf = $applysdf;
            kernel::database()->beginTransaction();

            if ($data_branch){
                foreach ($data_branch as $branch_bn => $_data_){
                    $tmp_sdf['inventory_apply_items'] = $_data_;
                    if(!$inventoryObj->finish_inventory($applysdf['inventory_apply_bn'],$branch_bn,1,$tmp_sdf['inventory_apply_items'])){

                        kernel::database()->rollBack();

                        return false;
                    }
                    
                }
            }else {
                kernel::database()->rollBack();
                return false;
            }
            if ($this->update(array('status'=>'confirmed','process_date'=>time()), array('inventory_apply_id'=>$applysdf['inventory_apply_id']))){
                kernel::database()->commit();
                return true;
            }else {
                kernel::database()->rollBack();

                return false;
            }
        }
        return false;
    }

    //导入
    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        if(empty($row)){
            $error_msg = array();
            if(isset($this->not_exist_product_bn)){
                if(count($this->not_exist_product_bn) > 10){
                    for($i=0;$i<10;$i++){
                        $not_exist_product_bn[] = current($this->not_exist_product_bn);
                        next($this->not_exist_product_bn);
                    }
                    $more = "...";
                }else{
                    $not_exist_product_bn = $this->not_exist_product_bn;
                    $more = "";
                }
                $error_msg[] = "不存在的货号：".implode(",",$not_exist_product_bn).$more;
                unset($this->not_exist_product_bn);
            }
            if(isset($this->branch_product_has_not)){//error_log(print_r($this->not_exist_product_bn,1),3,'E:/aaalll.log');
                if(count($this->branch_product_has_not) > 10){
                    for($i=0;$i<10;$i++){
                        $branch_product_has_not[] = current($this->branch_product_has_not);
                        next($this->branch_product_has_not);
                    }
                    $more = "...";
                }else{
                    $branch_product_has_not = $this->branch_product_has_not;
                    $more = "";
                }
                $error_msg[] = "仓库未关联的货品：".implode(",",$branch_product_has_not).$more;
                unset($this->branch_product_has_not);
            }
            if(isset($this->duplicate_order_bn_in_file)){
                if(count($this->duplicate_order_bn_in_file) > 10){
                    for($i=0;$i<10;$i++){
                        $duplicate_order_bn_in_file[] = current($this->duplicate_order_bn_in_file);
                        next($this->duplicate_order_bn_in_file);
                    }
                    $more = "...";
                }else{
                    $duplicate_order_bn_in_file = $this->duplicate_order_bn_in_file;
                    $more = "";
                }
                $error_msg[] = "文件中以下货号重复：".implode(",",$duplicate_order_bn_in_file).$more;
                unset($this->duplicate_order_bn_in_file);
            }
            if(isset($this->bn_nums_is_diff)){
                if(count($this->bn_nums_is_diff) > 10){
                    for($i=0;$i<10;$i++){
                        $bn_nums_is_diff[] = current($this->bn_nums_is_diff);
                        next($this->bn_nums_is_diff);
                    }
                    $more = "...";
                }else{
                    $bn_nums_is_diff = $this->bn_nums_is_diff;
                    $more = "";
                }
                $error_msg[] = "货品总数量与申请数量不符：".implode(",",$bn_nums_is_diff).$more;
                unset($this->bn_nums_is_diff);
            }
            if(isset($this->branch_nums_has_not)){
                if(count($this->branch_nums_has_not) > 10){
                    for($i=0;$i<10;$i++){
                        $branch_nums_has_not[] = current($this->branch_nums_has_not);
                        next($this->branch_nums_has_not);
                    }
                    $more = "...";
                }else{
                    $branch_nums_has_not = $this->branch_nums_has_not;
                    $more = "";
                }
                $error_msg[] = "货品在仓库中的库存不足：".implode(",",$branch_nums_has_not).$more;
                unset($this->branch_nums_has_not);
            }
            if(isset($this->bn_in_nums_is_diff)){
                if(count($this->bn_in_nums_is_diff) > 10){
                    for($i=0;$i<10;$i++){
                        $bn_in_nums_is_diff[] = current($this->bn_in_nums_is_diff);
                        next($this->bn_in_nums_is_diff);
                    }
                    $more = "...";
                }else{
                    $bn_in_nums_is_diff = $this->bn_in_nums_is_diff;
                    $more = "";
                }
                $error_msg[] = "盘点结果与填写的总数量不同：".implode(",",$bn_in_nums_is_diff).$more;
                unset($this->bn_in_nums_is_diff);
            }
            if(!empty($error_msg)){
                unset($this->import_data);
                $msg['error'] = implode("    ",$error_msg);
                return false;
            }
        }


        $mark = false;
        $fileData = $this->import_data;
        if( !$fileData )
            $fileData = array();

        if( substr($row[0],0,1) == '*' ){
            if ( $row[0] == '*:货号' && $this->branch_num ){
                $tmp = 4;//仓库数据开始key
                for ($i = 0;$i < $this->branch_num;$i++){
                    if (isset($row[$tmp]) && !in_array($row[$tmp], $this->branch_bns)){
                        $msg['error'] = $row[$tmp].":此仓库不在本次盘点申请中";
                        return false;
                    }
                }
            }
            $titleRs =  array_flip($row);
            $mark = 'title';
            return $titleRs;
        }else{
            if( $row[0] ){
                if( array_key_exists( '*:盘点流水单号',$title )  ) {
                    $applysdf = $this->dump(array('inventory_apply_bn'=>$row[0]));
                    if ($applysdf['status'] == 'confirmed' || $applysdf['status'] == 'closed'){
                        $msg['error'] = "盘点流水单已确认或已取消";
                        return false;
                    }
                    $branch = $this->get_branch_by_wms($applysdf['wms_id']);
                    
                    foreach ($branch as $bi){
                        $branchs[] = $bi['branch_bn'];
                    }
                    $branchlist = app::get('ome')->model('branch')->getList('branch_id',array('branch_bn'=>$branchs),0,-1);
                    foreach ($branchlist as $b){
                        $branch_ids[] = $b['branch_id'];
                    }
                    $ids = implode(',',$branch_ids);
                    
                    if (!$applysdf){
                        $msg['error'] = "无此盘点流水单号!";
                        return false;
                    }
                    if (empty($this->apply_id)){
                        $this->apply_id = $applysdf['inventory_apply_id'];
                    }
                    if (empty($this->branch_num)){
                        $this->branch_bns = $branchs;
                        $this->branch_num = count($branch);
                        $this->branchs = $ids;
                    }
                    $fileData['apply']['contents'][$row[0]] = $row;
                }else{
                    //计数判断，是否超过5000条记录，超过就提示数据过多
                    if(isset($this->order_nums)){
                        kernel::log($this->order_nums);
                        $this->order_nums ++;
                        if($this->order_nums > 5000){
                            unset($this->import_data);
                            $msg['error'] = "导入的数据量过大!";
                            return false;
                        }
                    }else{
                        $this->order_nums = 0;
                    }
                    #error_log('row2'.$row[2]."\r\n".'row3'.$row[3]."\r\n",3,__FILE__.'row.log');
                    $all_num = $row[2]+$row[3];
                    $item = $this->app->model('inventory_apply_items')->dump(array('bn'=>$row[0],'inventory_apply_id'=>$this->apply_id));
                    #error_log('item:'.var_export($item,1),3,__FILE__.'row.log');
                    if (!$item){
                        //申请表中没有此货品
                        $this->not_exist_product_bn = isset($this->not_exist_product_bn)?array_merge($this->not_exist_product_bn,array($row[0])):array($row[0]);
                    }else{
                        $branch_n = array();
                        $sql = "SELECT bp.store,b.branch_bn FROM sdb_ome_branch_product bp 
                                JOIN sdb_ome_branch b 
                                    ON b.branch_id=bp.branch_id 
                                WHERE bp.product_id=".$item['product_id']." AND 
                                b.branch_id IN ($this->branchs)";
                        $rows = $this->db->select($sql);
                        if ($rows){
                            //获取每个仓库的此货品现有数量
                            foreach ($rows as $rw){
                                $branch_n[$rw['branch_bn']] = $rw['store'];
                            }
                            if ($item['quantity'] != intval($all_num)){
                                //上传的此货品盘点总数与申请数量不符 (货品总数量与申请数量不符)
                                $this->bn_nums_is_diff = isset($this->bn_nums_is_diff)?array_merge($this->bn_nums_is_diff,array($row[0])):array($row[0]);
                            }
                        }
                    }
                    //判断货品在OME中是否存在
                    $get_material_row    = $basicMaterialObj->dump(array('material_bn'=>$row[0]), 'bm_id, material_bn');
                    if(empty($get_material_row))
                    {
                        $this->not_exist_product_bn = isset($this->not_exist_product_bn)?array_merge($this->not_exist_product_bn,array($row[0])):array($row[0]);
                    }
                    //判断货品是否已经读取
                    if (isset($fileData['item']['contents'][$row[0]])){
                        $this->duplicate_order_bn_in_file = isset($this->duplicate_order_bn_in_file)?array_merge($this->duplicate_order_bn_in_file,array($row[0])):array($row[0]);
                    }
                    $title_data = array_keys($title);
                    $tmp = 4;//仓库数据开始key
                    $branch = array();
                    $num = array();
                    for ($i = 0;$i < $this->branch_num;$i++){
                        $branch[] = intval($row[$tmp]);
                        if (!isset($branch_n[$title_data[$tmp]]) && intval($row[$tmp+1]) != 0){
                            $this->branch_product_has_not = isset($this->branch_product_has_not)?array_merge($this->branch_product_has_not,array($row[0]."|".$title_data[$tmp])):array($row[0]."|".$title_data[$tmp]);
                        }elseif (intval($row[$tmp+1]) < 0 && $branch_n[$title_data[$tmp]] == 0 ){
                            $this->branch_nums_has_not = isset($this->branch_nums_has_not)?array_merge($this->branch_nums_has_not,array($row[0]."|".$title_data[$tmp])):array($row[0]."|".$title_data[$tmp]);
                        }elseif ( intval($row[$tmp+1]) < 0 && $branch_n[$title_data[$tmp]] < abs(intval($row[$tmp+1])) ){
                            //仓库的此货品数量不足
                            $this->branch_nums_has_not = isset($this->branch_nums_has_not)?array_merge($this->branch_nums_has_not,array($row[0]."|".$title_data[$tmp])):array($row[0]."|".$title_data[$tmp]);
                        }
                        $row[$tmp] = $title_data[$tmp];
                        $num[] = intval($row[$tmp+1]);
                        $tmp += 2;
                    }
                    $branch_nums = array_sum($branch);
                    $import_nums = array_sum($num);
                    if ($import_nums != intval($all_num)){
                        //盘点数据与填写总数量不同
                        $this->bn_in_nums_is_diff = isset($this->bn_in_nums_is_diff)?array_merge($this->bn_in_nums_is_diff,array($row[0])):array($row[0]);
                    }
                    $fileData['item']['contents'][$row[0]] = $row;
                }
                $this->import_data = $fileData;
            }
        }
        return null;
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }

    public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false){
        $applyData = $this->getlist('inventory_apply_id,inventory_apply_bn', array('inventory_apply_id|in' => $filter['inventory_apply_id']));
        $data = array();
        foreach($applyData as $val) {
            $items = $this->app->model('inventory_apply_items')->getList('*', array('inventory_apply_id'=>$val['inventory_apply_id']));
            foreach($items as $item) {
                $tmp['*:盘点申请单号'] = kernel::single('ome_func')->csv_filter($val['inventory_apply_bn']);
                $tmp['*:基础物料编码'] = kernel::single('ome_func')->csv_filter($item['material_bn']);
                $tmp['*:wms库存'] = $item['wms_stores'];
                $tmp['*:oms库存'] = $item['oms_stores'];
                $tmp['*:库存差异'] = $item['diff_stores'];
                $tmp['*:良/残品'] = kernel::single('ome_func')->csv_filter($item['m_type'] == 'zp' ? '良品' : '残品');
                $tmp['*:备注'] = kernel::single('ome_func')->csv_filter($item['memo']);
                $data[] = implode(',', $tmp);
            }
        }
        if($data && $has_title) {
            $title = array(
                '*:盘点申请单号',
                '*:基础物料编码',
                '*:wms库存',
                '*:oms库存',
                '*:库存差异',
                '*:良/残品',
                '*:备注'
            );
            $firstData = mb_convert_encoding(implode(',', $title), 'GBK', 'UTF-8');
            array_unshift($data, $firstData);
        }
        return $data;
    }
}
