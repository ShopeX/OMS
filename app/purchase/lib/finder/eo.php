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

class purchase_finder_eo{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "库存单详情";

    function detail_basic($eo_id){
        $render = app::get('purchase')->render();
        $oEo = app::get('purchase')->model("eo");
        $eo = $oEo->eo_detail($eo_id);
        $eo['memo'] = kernel::single('ome_func')->format_memo($eo['detail']['memo']);
        $render->pagedata['eo'] = $eo;
        return $render->fetch('admin/eo/eo_detail.html');
    }

    var $addon_cols = "eo_id";
    var $column_edit = "操作";
    var $column_edit_width = "100";
    function column_edit($row){
        $find_id = $_GET['_finder']['finder_id'];
        $id = $row[$this->col_prefix.'eo_id'];
        $poObj = app::get('purchase')->model('eo');
        $data = $poObj->dump($id);

        $button = <<<EOF
         <a href="index.php?app=purchase&ctl=admin_eo&act=eo_cancel&p[0]=$id&find_id=$find_id" class="lnk" target="_blank">采购退货</a>

EOF;
       $button22 = <<<EOF
        &nbsp;&nbsp;<a href="index.php?app=purchase&ctl=admin_eo&act=printeo&p[0]=$id" target="_bank" class="lnk">打印</a>
EOF;
        $string = '';
        if ($data['status'] != '3'){

            $string .= $button;
        }
        $string.=$button22 ;
        return $string;
    }

}