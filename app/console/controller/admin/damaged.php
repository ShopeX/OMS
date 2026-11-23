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

/*
**@author Kris wang 406048119@qq.com
**报残控制器
*/
class console_ctl_admin_damaged extends desktop_controller{
    public function index(){
        $branchMdl = app::get('ome')->model('branch');
        $branch_list = $branchMdl->getList('branch_id,name',array('type'=>array('main','aftersale'),'b_type'=>1));
        $this->pagedata['branch_list'] = $branch_list;
        $this->page('admin/damaged.html');
    }

    public function add_products(){
        $res = array('res'=>'succ');
        $product_bn = trim($_POST['product_bn']);
        $branch_id = intval($_POST['branch_id']);
        
        $bpMdl = app::get('ome')->model('branch_product');
        
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $product_data_tmp    = $basicMaterialObj->getList('bm_id, material_name, material_bn', array('material_bn'=>$product_bn), 0, 1);
        
        $product_id = $product_data_tmp[0]['bm_id'];
        $product_data = $bpMdl->getList('store as num',array('product_id'=>$product_id,'branch_id'=>$branch_id));

        if(empty($product_data)){
            $res['res'] = 'false';
            $res['msg'] = '货号不存在';
            echo json_encode($res);exit;
        }
        $res['data']['bn'] = $product_bn;
        $res['data']['product_id'] = $product_data_tmp[0]['bm_id'];
        $res['data']['name'] = $product_data_tmp[0]['material_name'];
        $res['data']['num'] = $product_data[0]['num'];
        $res['data']['price'] = 0;
        echo json_encode($res);exit;
    }

    public function do_action(){
        $url = 'index.php?app=console&ctl=admin_damaged&act=index';
        $this->begin($url);

        $bpMdl = app::get('ome')->model('branch_product');
        
        
        $branch_id = $_POST['branch_id'];
        if(empty($branch_id)){
            $this->end(false,'请选择仓库');
        }
        #判断仓库有没有相关的残仓
        $damaged_branch = kernel::single('console_iostockdata')->getDamagedbranch($branch_id);
        if(!$damaged_branch){
            $this->end(false,'所选仓库没有对应残仓，无法进行报残操作');
        }
        $damaged_branch_id = $damaged_branch['branch_id'];
        #判断库存数 与 报残数的 大小
        $in = $out =array();
        
        $type_out = 5;
        $type_in = 50;
        $operator = kernel::single('desktop_user')->get_name();
        foreach($_POST['num'] as $product_id=>$num){
            if(!is_int(intval($num)) || $num <= 0)  $this->end(false,'请输入大于零的正整数');
            //todo 只判断实际库存
            $data = $bpMdl->getList('store',array('branch_id'=>$branch_id,'product_id'=>$product_id));
            if($data[0]['store'] < $num){
                $this->end(false,$_POST['name'][$product_id].' 库存数小于报残数量，请重新确认');
            }
            #入库数据
            $in[$product_id]['bn'] = $_POST['bn'][$product_id];
            $in[$product_id]['branch_id'] = $damaged_branch_id;
            $in[$product_id]['nums'] = $_POST['num'][$product_id];
           
            $in[$product_id]['iostock_price'] = $_POST['price'][$product_id];
            $in[$product_id]['type_id'] = $type_in;
            #出库数据
            $out[$product_id]['bn'] = $_POST['bn'][$product_id];
            $out[$product_id]['branch_id'] = $branch_id;
            $out[$product_id]['nums'] = $_POST['num'][$product_id];
            
            $out[$product_id]['iostock_price'] = $_POST['price'][$product_id];
            $out[$product_id]['type_id'] = $type_out;
        }

        #生成 残损出入库单
        #1出库  0 入库
        if ($in){
            $instockLib = kernel::single('siso_receipt_iostock_damagedin');
            $instockLib->_typeId = 50;
            if(!$instockLib->create(array('items'=>$in,'operator'=>$operator), $data, $msg)){

                $this->end(false,'残损入库操作失败');
            }
        }
        if ($out){
            $outstockLib = kernel::single('siso_receipt_iostock_damagedout');
            $outstockLib->_typeId = 5;
            if(!$outstockLib->create(array('items'=>$out,'operator'=>$operator), $data, $msg)){

                $this->end(false,'残损出库操作失败');
            }
        }
        $this->end(true,$url);
    }
}