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

class finance_finder_ar{

    private $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "addon,monthly_id";
    function detail_edit($ar_id){
        $aritemObj = &app::get('finance')->model('ar_items');
        $items = $aritemObj->getList('*',array('ar_id'=>$ar_id));
        $render = app::get('finance')->render();
        $render->pagedata['items'] = $items;
        $render->pagedata['finder_id'] = $_GET['_finder']['finder_id'];
        return $render->fetch('ar/detail.html');
    }

    var $column_sale_money = '商品成交金额';
    var $column_sale_money_width = "65";
    var $column_sale_money_order = 8;
    /**
     * column_sale_money
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_sale_money($row){
        $addon = unserialize($row[$this->col_prefix.'addon']);
        return "￥".number_format($addon['sale_money'],2);
    }

    var $column_fee_money = '运费收入';
    var $column_fee_money_width = "65";
    var $column_fee_money_order = 9;
    /**
     * column_fee_money
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_fee_money($row){
        $addon = unserialize($row[$this->col_prefix.'addon']);
        return "￥".number_format($addon['fee_money'],2);
    }

    // var $column_delete = "删除";
    // var $column_delete_width = "65";
    // var $column_delete_order = 17;
    // function column_delete($row){
    //     $ar_id = $row['ar_id'];
    //     $render = app::get('finance')->render();
    //     $render->pagedata['ar_id'] = $ar_id;
    //     $render->pagedata['finder_id'] = $_GET['_finder']['finder_id'];
    //     if($row['charge_status'] == 0){
    //         return $render->fetch('ar/do_cancel.html');
    //     }
    // }

    public $column_monthly_id = '账期名称';
    public $column_monthly_id_order = 1;
    public $column_monthly_id_width = 180;
    /**
     * column_monthly_id
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_monthly_id($row, $list) {
        if(!$this->monthly_id) {
            $miRows = app::get('finance')->model('monthly_report')->getList('monthly_id, monthly_date', ['monthly_id'=> array_column($list, $this->col_prefix.'monthly_id')]);
            $this->monthly_id = array_column($miRows, null, 'monthly_id');
        }
        return $this->monthly_id[$row[$this->col_prefix.'monthly_id']]['monthly_date'] ? : '';
    }
}
?>