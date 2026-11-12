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

class ome_finder_return_fail{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_fail = '货品修正';

    function detail_fail($return_id){
        $render = app::get('ome')->render();
        $oOrder_items = app::get('ome')->model('order_items');
        $oReturn = app::get('ome')->model('return_product');
        $oReturn_items = app::get('ome')->model('return_product_items');
        $returninfo = $oReturn->dump($return_id,'order_id,return_id');
        $order_id = $returninfo['order_id'];
        
        $return_item = $oReturn_items->getlist('*',array('return_id'=>$return_id));
        foreach ($return_item as &$item ) {
            $bn = $item['bn'];
            $order_item = $oOrder_items->dump(array('bn'=>$bn,'delete'=>'false','order_id'=>$order_id),'item_id');
            $item['status'] = 0;
            if ($order_item && $item['product_id']>0) {
                $item['status'] = 1;
            }
        }

        $render->pagedata['returninfo'] = $returninfo;
        $render->pagedata['item_list'] = $return_item;
        
        return $render->fetch('admin/return_product/detail_fail.html');
    }

    var $column_edit = "操作";
    var $column_edit_width = "80";
    public $column_edit_order = 1;
    function column_edit($row){
        $return_id = $row['return_id'];
        $confirm_notice = '确定要拒绝此售后申请吗?拒绝后将不可以操作!';
        $finder_id = $_GET['_finder']['finder_id'];
        $word = '拒绝';
        $html = <<<EOF
            <a style="float:left;text-decoration:none;" href="javascript:void(0);" title="{$t}" onclick="if(confirm('{$confirm_notice}')){new Event(event).stop();new Request({url:'index.php?app=ome&ctl=admin_return_fail&act=refuse&p[0]={$return_id}',method : 'post',onComplete : function(rs){var resp = JSON.decode(rs);alert(resp.msg);window.finderGroup['{$finder_id}'].refresh(true);}}).send();}"><span style="padding:2px;">&nbsp;{$word}&nbsp;</span></a>
EOF;
        return $html;
     
    }
}