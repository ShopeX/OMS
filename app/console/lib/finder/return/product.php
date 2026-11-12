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

class console_finder_return_product{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    

    function __construct($app)
    {
        $this->app = $app;
        
        if($_GET['app']!='console'){
            
            unset($this->column_edit);
            
        }
    }
    var $console_detail_basic = "售后服务详情";
    function console_detail_basic($return_id){
        $render         = app::get('console')->render();
        $oProduct       = app::get('ome')->model('return_product');
        $oProduct_items = app::get('ome')->model('return_product_items');
        $oReship_item   = app::get('ome')->model('reship_items');
        $oOrder         = app::get('ome')->model('orders');
        $oBranch        = app::get('ome')->model('branch');
        $oReship   = app::get('ome')->model('reship');
        $oDly_corp   = app::get('ome')->model('dly_corp');

        if ($_POST['delivery_id']){
            foreach($_POST['item_id'] as $key => $val){
                $item = array();
                $item['item_id'] = $val;
                $branch_id = $_POST['branch_id'.$val];
                $item['branch_id'] = $branch_id;
                $oProduct_items->save($item);
           }
           $return_product['return_id'] = $return_id;
           $return_product['delivery_id'] = $_POST['delivery_id'];
           $oProduct->save($return_product);
        }
        $product_detail = $oProduct->product_detail($return_id);

        $reshipinfo = $oReship->dump(array('return_id'=>$return_id),'return_logi_name,return_logi_no');
        if($reshipinfo){
            $corpinfo = $oDly_corp->dump($reshipinfo['return_logi_name'],'name');
            $product_detail['process_data']['shipcompany'] = $corpinfo['name'];
            $product_detail['process_data']['shiplogino'] = $reshipinfo['return_logi_no'];
        }

        $order_id = $product_detail['order_id'];
        if (!$product_detail['delivery_id']){
            $product_items = array();
            if ($product_detail['items'])
               foreach($product_detail['items'] as $k=>$v){
                $refund = $oReship_item->Get_refund_count($order_id,$v['bn']);
                $v['effective']=$refund;
                $v['branch']=$oReship_item->getBranchCodeByBnAndOd($v['bn'],$order_id);
                $product_items[] = $v;
            }
            //获取仓库模式
            $branch_mode = app::get('ome')->getConf('ome.branch.mode');
            $render->pagedata['branch_mode'] = $branch_mode;
            $product_detail['items'] = $product_items;
        }

        //增加售后服务详情显示前的扩展
        foreach(kernel::servicelist('ome.aftersale') as $o){
            if(method_exists($o,'pre_detail_display')){
                $o->pre_detail_display($product_detail);
            }
        }
        if (!is_numeric($product_detail['attachment'])){
            $render->pagedata['attachment_type'] = 'remote';
        }


        $pcount = $oProduct->count(array('order_id'=>$product_detail['order_id']));
        if($pcount > 1){
           $render->pagedata['is_return_order'] = true;
        }else{
           $render->pagedata['is_return_order'] = false;
        }

        $render->pagedata['product'] = $product_detail;
        $render->pagedata['order'] = $oOrder->dump($product_detail['order_id']);

        return $render->fetch('admin/return_product/detail/basic.html');
    }

    

    

    var $column_edit = "操作";
    var $column_edit_width = "200";
    function column_edit($row){

        if(!kernel::single('desktop_user')->is_super()){
            $returnLib = kernel::single('ome_return');

            $has_permission = $returnLib->chkground('aftersale_center','','aftersale_return_edit');
            if (!$has_permission) {
                return false;
            }

        }

        if($row['status'] == '1'||$row['status'] == '2'){
           return '<a target="dialog::{width:700,height:400,title:\'编辑售后服务单号:'.$row['return_bn'].'\'}" href="index.php?app=console&ctl=admin_return&act=edit&p[0]='.$row['return_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">编辑</a>  ';
        }
    }

}
?>