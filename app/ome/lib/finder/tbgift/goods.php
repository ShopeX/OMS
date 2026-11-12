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
class ome_finder_tbgift_goods{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "status";
    var $column_control = '操作';
    var $column_control_width = "150";
    function column_control($row){
        #获取赠品的商品类型
        $gift_goods = app::get('ome')->model('tbgift_goods');
        $type = $gift_goods->dump($row['goods_id'],'goods_type');
        $find_id = $_GET['_finder']['finder_id'];
        if($row[$this->col_prefix . 'status'] == 2){
            $btn = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要启用当前的赠品吗？\")) {href=\"index.php?app=ome&ctl=admin_preprocess_tbgift&act=setStatus&p[0]={$row['goods_id']}&p[1]=true&finder_id={$_GET['_finder']['finder_id']}\";}'>启用</a> | "; 
        }else{
            $btn = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要暂停当前的赠品吗？\")) {href=\"index.php?app=ome&ctl=admin_preprocess_tbgift&act=setStatus&p[0]={$row['goods_id']}&p[1]=false&finder_id={$_GET['_finder']['finder_id']}\";}'>暂停</a> | "; 
        }

        $btn .= '<a href="index.php?app=ome&ctl=admin_preprocess_tbgift&act=edit&p[0]='.$row['goods_id'].'&p[1]='.$type['goods_type'].'&finder_id='.$find_id.'&_finder[finder_id]='.$find_id.'" target="_blank">编辑</a>';
        return $btn;
    }

    function detail_basic($gift_id){
        $render = app::get('ome')->render();
        $gift_product = app::get('ome')->model('tbgift_product');
        $data = $gift_product->getAllProduct($gift_id);
        $render->pagedata['gift_data'] = $data;
        return $render->fetch('admin/preprocess/tbgift/detail_basic.html');
    }

    var $column_status = '当前状态';
    var $column_status_width = "100";
    var $column_status_order = "200";
    function column_status($row){
        if($row[$this->col_prefix . 'status'] == 2){
            return '关闭';
        }else{
            return '启用';
        }
    }
}
?>
