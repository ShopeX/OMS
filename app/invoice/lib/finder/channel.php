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

class invoice_finder_channel{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "channel_type,shop_id,node_id";
    
    var $column_control = '操作';
    var $column_control_width = '150';
    var $column_control_order = COLUMN_IN_HEAD;

    function column_control($row){
       $channel_id = $row['channel_id'];
       $channel_type = $row[$this->col_prefix.'channel_type'];
       $button = "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=invoice&ctl=admin_channel&act=edit&p[0]={$channel_id}&finder_id={$_GET['_finder']['finder_id']}',{width:620,height:460,title:'来源添加/编辑'}); \">编辑</a>";
       $status = $row['status'];
       if ($status == 'true') {
            $todo = 'false';
            $button_2 = sprintf('<a  style="color: green" href="javascript:if (confirm(\'如果关闭，则电子发票渠道不可用！\')){W.page(\'index.php?app=invoice&ctl=admin_channel&act=status&p[0]=%s&p[1]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">开启</a>', $todo,$row['channel_id'],$_GET['_finder']['finder_id']);
        }else{
            $todo = 'true';
            $button_2 = sprintf('<a  style="color: red" href="javascript:if (confirm(\'如果开启，则继续使用电子发票渠道！\')){W.page(\'index.php?app=invoice&ctl=admin_channel&act=status&p[0]=%s&p[1]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">关闭</a>', $todo,$row['channel_id'],$_GET['_finder']['finder_id']);
        }
        $btns = [
            $button_2,
            $button,
        ];
        // 判断是否已经绑定
        if ($row[$this->col_prefix.'node_id']){
            $btns[] = sprintf('<a  style="color: darkred" href="javascript:if (confirm(\'如果解绑，则渠道将无法开票！\')){W.page(\'index.php?app=invoice&ctl=admin_channel&act=unbind&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">解绑</a>', $row['channel_id'],$_GET['_finder']['finder_id']);
        }

        return implode(' | ', $btns);
    }
    var $column_channel_type = '来源类型';
    var $column_channel_type_width = '80';
    var $column_channel_type_order = COLUMN_IN_TAIL;
    function column_channel_type($row){
        $funcObj = kernel::single('invoice_func');
        $channel_type = $row[$this->col_prefix.'channel_type'];
        $channels = $funcObj->channels($channel_type);
        if($channels) {
            return $channels['name'];
        } else {
            return '未知';
        }
    }
/*     var $column_shop = '店铺';
    var $column_shop_width = '150';
    var $column_shop_order = COLUMN_IN_TAIL;
    function column_shop($row){
        if($row[$this->col_prefix.'channel_type'] == 'taobao') {
            $shopObj = app::get('ome')->model('shop');
            $shop = $shopObj->dump($row[$this->col_prefix.'shop_id'],'name');
            return $shop['name'];
        }else {
            return '全部';
        }

    } */
}
