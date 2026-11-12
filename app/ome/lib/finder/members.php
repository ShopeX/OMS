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

class ome_finder_members{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $detail_address = '地址库';
    
    
    function detail_address($member_id){
        $render = app::get('ome')->render();
        $addressObj = app::get('ome')->model('member_address');
        $address_detail = $addressObj->getList('*',array('member_id'=>$member_id));
        foreach($address_detail as &$address){
            $ship_area = $address['ship_area'] ? explode(':',$address['ship_area']) : '';
            
            if($ship_area){
                $address['ship_area'] = $ship_area[1];
                
            }

        }
        $render->pagedata['address_detail'] = $address_detail;

        $finder_id = $_GET['_finder']['finder_id'];
        $render->pagedata['finder_id'] = $finder_id;
        unset($address_detail);
        return $render->fetch('admin/member/address.html');
    }

    var $column_edit = "操作";
    var $column_edit_width = "200";
    var $addon_cols = "member_id";
    function column_edit($row){
        $member_id = $row[$this->col_prefix.'member_id'];
        $finder_id = $_GET['_finder']['finder_id'];
        $button = <<<EOF
            <a href="index.php?app=ome&ctl=admin_customer&act=add&p[0]=$member_id&type=copy&finder_id=$finder_id" target="dialog::{width:800,height:500,title:'复制会员'}">复制</a>
EOF;
        $button1 = <<<EOF
            <a href="index.php?app=ome&ctl=admin_customer&act=add&p[0]=$member_id&type=edit&finder_id=$finder_id" target="dialog::{width:800,height:500,title:'编辑会员'}">编辑</a>
EOF;
        $button2 = <<<EOF
            <a href="index.php?app=ome&ctl=admin_customer&act=edit_address&member_id=$member_id&finder_id=$finder_id" target="_blank">地址库</a>
EOF;
        $string = $button.' | '.$button1.' | '.$button2;
        return $string;
    }
}
?>