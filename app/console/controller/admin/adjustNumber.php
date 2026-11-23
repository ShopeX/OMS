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

class console_ctl_admin_adjustNumber extends desktop_controller{

    public function exportTemplate(){
        die('此接口已经废弃');
        $filename = "调账模板".date('Y-m-d').".csv";
        $encoded_filename = urlencode($filename);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        $ua = $_SERVER["HTTP_USER_AGENT"];
        header("Content-Type: text/csv");
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox$/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        
        $adjustObj = $this->app->model('adjustNumber');
        $title = $adjustObj->exportTemplate('adjust');
        echo '"'.implode('","',$title).'"';
    }
    
    public function import(){
        die('此接口已经废弃');
        $this->finder('console_mdl_adjustNumber',array(
            'title'=>'批量导入',
            'base_filter' => $base_filter,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>true,
            'use_buildin_filter'=>true,
            'use_buildin_export'=>true,
            'orderBy' =>'goods_id DESC'
        ));
    }
    

    function adjust(){
        die('此接口已经废弃');
        $oBranch  = app::get('ome')->model('branch');
        $oBranchPro = app::get('ome')->model('branch_product');
        
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $basicMStockFreezeLib   = kernel::single('material_basic_material_stock_freeze');
        
        if($_POST){
            $data = $_POST;
            $this->begin('index.php?app=console&ctl=admin_adjustNumber&act=adjust');
//            $data['branch'] == '' ? $this->end(false,'仓库名称不能为空') : '';
            if($data['searchtype'] == 'bn'){ //按货号查
                $data['product_bn'] == '' ? $this->end(false,'货号必填') : '';
                
                //判断货品名称是否存在
                $products    = $basicMaterialSelect->getlist('*', array('material_bn'=>$data['product_bn']));
                
                if(empty($products[0]['product_id'])){
                    $this->end(false,'该货品不存在');
                }
            }else if($data['searchtype'] == 'name'){ //按货品名称查
                $data['product_name'] == '' ? $this->end(false,'货品名称必填') : '';
                
                //判断货品名称是否存在
                $products    = $basicMaterialSelect->getlist('*', array('material_name'=>$data['product_name']));
                
                if(empty($products[0]['product_id'])){
                    $this->end(false,'该货品不存在');
                }
            }
            $this->endonly(true);
            
            $branchPro = array();
            foreach ($products as $key => $value) {
                if ($data['branch']) {
                    $branchPro[] = $oBranchPro->dump(
                        array(
                            'branch_id'  => $data['branch'],
                            'product_id' => $value['product_id']
                        ), 'branch_id,store,store_freeze');
                } else {
                    $branchPro = $oBranchPro->getList('branch_id,store,store_freeze',
                        array('product_id' => $value['product_id']));
                }
                if ($branchPro) {
                    if (empty($branchPro[$key])) {
                        $branchPro[$key]['branch_id']    = $data['branch'];
                        $branchPro[$key]['store']        = 0;
                        $branchPro[$key]['store_freeze'] = 0;
                    } else {
                        //根据仓库ID、基础物料ID获取该物料仓库级的预占
                        if ($data['branch']) {
                            $branchPro[$key]['store_freeze'] = $basicMStockFreezeLib->getBranchFreeze($value['product_id'], $data['branch']);
                        } else {
                            foreach ($branchPro as $k => $v) {
                                $branchPro[$k]['branch_id']    = $v['branch_id'];
                                $branchPro[$k]['product_name'] = $value['name'];
                                $branchPro[$k]['product_id']   = $value['product_id'];
                                $branchPro[$k]['product_bn']   = $value['bn'];
                                $branch_name                   = $oBranch->dump($v['branch_id'], 'name');
                                $branchPro[$k]['branch_name']  = $branch_name['name'];
                                $branchPro[$k]['store_freeze'] = $basicMStockFreezeLib->getBranchFreeze($value['product_id'], $v['branch_id']);
                            }
                        }
                    }
                }
                if ($data['branch']) {
                    $branchPro[$key]['product_name'] = $value['name'];
                    $branchPro[$key]['product_id']   = $value['product_id'];
                    $branchPro[$key]['product_bn']   = $value['bn'];
                    $branch_name                     = $oBranch->dump($data['branch'], 'name');
                    $branchPro[$key]['branch_name']  = $branch_name['name'];
                }
            }

            $this->pagedata['pickList'] = $branchPro;
            $this->pagedata['products'] = $products[0];
        }
        // 获取操作员管辖仓库
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids){
                $branch_id = $branch_ids;
            }else{
                $branch_id = 'false';
            }
        }
        //$user_branch = kernel::single("ome_userbranch");
        //$branch_id= $user_branch->get_user_branch_id();
        if($branch_id) $branch  = $oBranch->getList('branch_id, name',array('branch_id'=>$branch_id),0,-1); //获取改管理员权限下仓库
        else $branch  = $oBranch->getList('branch_id, name',array(),0,-1); //获取所有仓库仓库
        $this->pagedata['branch'] = $branch ;
        $this->pagedata['searchtype'] = array(array('type_value'=>'bn','type_name'=>'货号'),array('type_value'=>'name','type_name'=>'货品名称'));
        
        $filetype = ['csv' => '.csv'];
        $this->pagedata['filetype'] = $filetype;
        $this->page('admin/stock/adjust.html');
    }
    
    function do_adjust()
    {
        die('此接口已经废弃');
        $product_id = $_POST['product_id'][0];
        $branch_id  = $_POST['branch_id'];
        $to_nums    = $_POST['to_nums'];
        //$update_type = isset($_POST['update_type']) ? $_POST['update_type'] : 'inc';
        
        $operator = kernel::single('desktop_user')->get_name();
        
        $this->begin('index.php?app=console&ctl=admin_adjustNumber&act=adjust');
        
        $basicMaterialObj = app::get('material')->model('basic_material');
        $product          = $basicMaterialObj->dump(array('bm_id' => $product_id), 'material_bn');
        
        //调账方式
        $stockLib = kernel::single('console_stock');
        $update_type = $stockLib->getAdjustType();
        
        //items
        $items = array();
        foreach ($branch_id as $key => $value) {
            $material_bn = $product['material_bn'];
            $num         = $to_nums[$key];
    
            if (!is_numeric($num) || $num < 0) {
                continue;
            }
            
            $items[$value][$material_bn]['num']        = $num;
            $items[$value][$material_bn]['branch_id']  = $value;
            $items[$value][$material_bn]['product_id'] = $product_id;
        }
        
        foreach ($items as $branch_id => $item) {
            $sku_total = $num_total = $original_total = 0;
            // 插入到配货表
            $cpfr_data = array(
                'cpfr_bn'     => uniqid(date('YmdHi')),
                'cpfr_name'   => '手动调整库存',
                'store_total' => count($item),
                
                'create_time' => time(),
                'operator'    => $operator,
                'branch_id'   => $branch_id,
                'adjust_type' => 'manual',
                'update_type' => $update_type,
            );
            $cpfrMdl   = app::get('console')->model('cpfr');
            
            $cpfr_data_items = array();
            
            foreach ($item as $bn => $r) {
                $cpfr_data_items[] = array(
                    'bn'         => $bn,
                    'product_id' => $r['product_id'],
                    'num'        => $r['num'],
                
                );
                
                if ($r['num'] < 0) {
                    unset($items[$branch_id][$bn]);
                    continue;
                }
                $num_total += $r['num'];
                $sku_total++;
            }
            
            $cpfr_data['sku_total'] = $sku_total;
            $cpfr_data['num_total'] = $num_total;
            $cpfrMdl->insert($cpfr_data);
            $cpfrItemMdl = app::get('console')->model('cpfr_items');
            foreach ($cpfr_data_items as &$v) {
                $v['cpfr_id'] = $cpfr_data['cpfr_id'];
            }
            $sql = ome_func::get_insert_sql($cpfrItemMdl, $cpfr_data_items);
            
            kernel::database()->exec($sql);
            
            $cpfrMdl->finishIostock($cpfr_data['cpfr_id'],$update_type);
        }
        
        $this->end(true, '调整库存成功');
    }
    
    
    function index(){
        die('此接口已经废弃');
        $this->finder('console_mdl_adjustNumber');
    }
    
}