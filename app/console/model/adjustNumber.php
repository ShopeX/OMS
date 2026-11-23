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

class console_mdl_adjustNumber extends console_mdl_interface_iostocksearchs{
    public $kvdata = array();

    //定义导入文件模版字段
    public function import_title(){
        $title[1] = array(
            '*:货号',
            '*:货品名称',
            '*:仓库编号',
            '*:调整到的数量',
            '*:备注',
        );

        return $title;
    }

    function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

    function io_title($filter){     
        switch( $filter ){
            case 'adjust':
            default:
                $this->oSchema['csv'][$filter] = array(
                    '*:货号' =>'',
                    '*:货品名称' =>'',
                    '*:仓库编号' =>'',
                    '*:调整到的数量' =>'',
                    '*:备注' =>'',
                );
            break;
        }
        return  $this->ioTitle['csv'][$filter] = array_keys( $this->oSchema['csv'][$filter] );   
    }



    
     function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
     {
         $basicMaterialObj    = app::get('material')->model('basic_material');
         $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
         
        $this->aa++;
        $mark = false;
        $fileData = $this->kvdata;
        
        if( !$fileData ) $fileData = array();

        if( substr($row[0],0,2) == '*:' ){
            $titleRs =  array_flip($row);
            $mark = 'title';

            return $titleRs;
        }else{

            $bn = trim($row[0]);
            $branch_bn = $row[2];
            $nums = $row[3];
            $memo = $row[4];
            if ($bn && $branch_bn && ($nums>=0)) 
            {
                //判断货号是否存在
                $oBranch_product = app::get('ome')->model('branch_product');
                
                $products    = $basicMaterialObj->dump(array('material_bn'=>$bn), 'bm_id, material_bn');
                
                if(!$products){
                    $msg['error'] = '货号不存在 '.$bn;
                    return false;
                }

                //判断仓库编号是否存在
                $branchObj = app::get('ome')->model('branch');
                $branch = $branchObj->dump(array('branch_bn'=>$branch_bn),'branch_id,branch_bn');
                if( ! $branch){
                    $msg['error']= '编号 '.$branch_bn.' 仓库不存在 ';
                    return false;
                }

                //判断数量是否合法
                if($nums < 0){
                    $msg['error'] = '货号 '.$bn.' 数量应为正整数 ';
                    return false;
                }
                
                $branch_product = $oBranch_product->dump(array('branch_id'=>$branch['branch_id'],'product_id'=>$products['bm_id']),'store,store_freeze');
                
                //根据仓库ID、基础物料ID获取该物料仓库级的预占
                $branch_product['store_freeze']    = $basicMStockFreezeLib->getBranchFreeze($products['bm_id'], $branch['branch_id']);
                
                if($branch_product['store_freeze'] > $nums){
                    $msg['error'] = '货号 '.$bn.' 冻结大于调整数量 ';
                    return false;
                }
                 if(empty($branch_product)){
                    $branch_product['store'] = 0;
                    $branch_product['store_freeze'] = 0;
                    
                }
                $diff_nums = $branch_product['store']-$nums;
                if($diff_nums == 0){
                    $msg['error'] = '货号 '.$bn.' 库存数量无差异变化 ';
                    return false;
                }
               
                $type = $diff_nums < 0 ? "IN" : "OUT";
                $fileData[$type][] = array(
                    'product_id'=>$products['bm_id'],
                    'bn'   =>$bn,
                    'branch_id'=>$branch['branch_id'],
                    'nums'           =>abs($diff_nums),
                    'memo'=>$memo,
                );
               

                
            }
            $this->kvdata = $fileData;
            
        }
        return null;

     }

    function prepared_import_csv(){
        set_time_limit(0);

    }

    function finish_import_csv(){
        $oQueue = app::get('base')->model('queue');
        $data = $this->kvdata; unset($this->kvdata);
        
        $oBranch_product = app::get('ome')->model('branch_product');
        $adjustout = array();
        $adjustin = array();

        foreach ($data as $k=>$v ) {
            $iostockData = array();
            
            if ($k == 'IN') {
                $adjustLib = kernel::single('siso_receipt_iostock_stockin');
                $adjustLib->_typeId = 8;
            }else{
                $adjustLib = kernel::single('siso_receipt_iostock_stockout');
                $adjustLib->_typeId = 80;
            }
            $iostockData['items'] = $v;

            $adjustLib->create($iostockData, $createdata, $msg);
        }
        return null;
    }
}
