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

class console_finder_cpfr
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'bill_status,adjust_type,cpfr_bn,cpfr_id';
    
    public $column_edit = "操作";
    public $column_edit_width = "80";
    public $column_edit_order = "-1";
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $adjust_type = $row[$this->col_prefix.'adjust_type'];
        $cpfr_bn = $row[$this->col_prefix.'cpfr_bn'];
        $cpfr_id = intval($row['cpfr_id']);
        $confirm= '<a class="lnk" href="javascript:if(confirm(\'确认单据'.$cpfr_bn.'?\')) {W.page(\'index.php?app=console&ctl=admin_cpfr&act=singleConfirm&p[0]='.$cpfr_id.'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'\');};">
                            确认</a>';
        if(in_array($row[$this->col_prefix.'bill_status'], ['1']) && $adjust_type == 'import') {
            return $confirm;
        }
        return '';
    }

    public $detail_items = '配货明细';
    /**
     * detail_items
     * @param mixed $cpfr_id ID
     * @return mixed 返回值
     */
    public function detail_items($cpfr_id)
    {
        $render = app::get('console')->render();
        
        /***
        $items = app::get('console')->model('cpfr_items')->getList('*', array('cpfr_id' => $cpfr_id));

        @ini_set('memory_limit','1024M');
        $render->pagedata['items'] = $items;
        return $render->fetch('admin/finder/cpfr/items.html');
        ***/
        
        $render->pagedata['cpfr_id'] = $cpfr_id;
        
        return $render->fetch('admin/cpfr/cpfr_item.html');
    }


}
