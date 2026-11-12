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

class wms_finder_print_tmpl{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "prt_tmpl_id";
    var $column_print = "快递单模板操作";
    var $column_print_width = "125";

    function column_print($row){
        $id = $row['prt_tmpl_id'];
        $printObj = app::get('wms')->model('print_tmpl');

        $data = $printObj->dump($id);


        $width = 0;
        $finder_id = $_GET['_finder']['finder_id'];
        $button = <<<EOF
        <a href="index.php?app=wms&ctl=admin_delivery_print&act=editTmpl&p[0]=$id&finder_id=$finder_id" class="lnk" target="_blank">编辑</a> |
EOF;
        $button2 = <<<EOF
        <span onclick="window.open('index.php?app=wms&ctl=admin_delivery_print&act=downloadTmpl&p[0]=$id')" class="lnk">下载</span> |
EOF;
        $button3 = <<<EOF
        <a href="index.php?app=wms&ctl=admin_delivery_print&act=addSameTmpl&p[0]=$id&finder_id=$finder_id" class="lnk" target="_blank">添加相似</a>
EOF;
        $string = '';
        $string .= $button;
        $width += 50;

        $string .= $button2;
        $width += 50;

        $string .= $button3;
        $width += 80;

        return $string;
    }

}
?>