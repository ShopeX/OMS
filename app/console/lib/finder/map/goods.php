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

class console_finder_map_goods {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = 'shop_id,bind,shop_type';

    public $column_edit = "操作";
    public $column_edit_width = "170";
    public $column_edit_order = 10;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row){

    }

    public $column_bind = '销售物料类型';
    public $column_bind_order = 70;
    public $column_bind_width = "70";
    /**
     * column_bind
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_bind($row){
        if($row[$this->col_prefix.'bind'] == '1'){
            return '组合';
        }elseif ($row[$this->col_prefix.'bind'] == '2'){
            return '多选一';
        }else{
            return '普通';
        }
    }

    public $column_shop_name ='店铺名';
    public $column_shop_name_order = 20;
    public $column_shop_name_width = "150";
    /**
     * column_shop_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_shop_name($row, $list){
        $shop = $this->__getShop($row, $list);
        return $shop['name'];
    }

    public $column_shop_type ='店铺类型';
    public $column_shop_type_order = 30;
    public $column_shop_type_width = "100";
    /**
     * column_shop_type
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_shop_type($row, $list){
        $shop = $this->__getShop($row, $list);
        return ome_shop_type::shop_name($shop['shop_type']);
    }

    private function __getShop($row, $list) {
        static $shop = array();
        if(empty($shop)) {
            $shopId = array();
            foreach($list as $val) {
                $shopId[$val[$this->col_prefix . 'shop_id']] = $val[$this->col_prefix . 'shop_id'];
            }
            $shopData = app::get('ome')->model('shop')->getList('*', array('shop_id' => $shopId));
            foreach($shopData as $value) {
                $shop[$value['shop_id']] = $value;
            }
        }
        return $shop[$row[$this->col_prefix . 'shop_id']] ? $shop[$row[$this->col_prefix . 'shop_id']] : array();
    }
}
