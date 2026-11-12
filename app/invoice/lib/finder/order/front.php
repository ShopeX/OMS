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

/**
 * ============================
 * @Author:   yaokangming
 * @describe: 预发票订单
 * ============================
 */
class invoice_finder_order_front {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "";
    /* public $column_edit = "操作";
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    public function column_edit($row){
        $btn = [];
        $btn[] = '<a class="lnk" target="_blank" href="index.php?app=invoice&ctl=admin_order_front&act=downloadRow&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'">下载</a>';
        $btn[] = '<a class="lnk" href="javascript:if(confirm(\'确定标记上传该单据?\')) {W.page(\'index.php?app=invoice&ctl=admin_order_front&act=flag&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'\');};">
                标记</a>';
        $btn[] = '<a class="lnk" target="_blank" href="index.php?app=desktop&act=alertpages&goto='.urlencode('index.php?app=invoice&ctl=admin_order_front&act=item&p[0]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id']).'">查看</a>';
        return implode('|', $btn);
    } */

    public $detail_item = "详情";
    public function detail_item($id){
        $render = app::get('invoice')->render();
        $itemsObj = app::get('invoice')->model('order_front_items');
        $items = $itemsObj->getList('*', array('of_id'=>$id), 0, -1);
        $render->pagedata['items'] = $items;
        return $render->fetch("admin/order/front/item.html");
    }

    /* public $detail_oplog = "操作记录";
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>id,'obj_type'=>'order_front@invoice'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;
        if ($this->vue_mode) { return []; }
        return $render->fetch('admin/oplog.html');
    } */
}