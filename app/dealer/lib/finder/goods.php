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
 * @Version:  1.0
 * @DateTime: 2020/9/7 15:49:19
 * @describe: 经销商货品
 * ============================
 */
class dealer_finder_goods {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $business;
    private $material;

    public $addon_cols = "bs_id,bm_id";
    public $column_bs_bn = "经销商编码";
    public $column_bs_bn_width = 120;
    public $column_bs_bn_order = 1;
    /**
     * column_bs_bn
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_bs_bn($row){
        $bs_id = $row[$this->col_prefix.'bs_id'];
        if(!isset($this->business[$bs_id])) {
            $this->business[$bs_id] = $this->_getBusiness($bs_id);
        }
        return $this->business[$bs_id]['bs_bn'];
    }
    public $column_name = "经销商名称";
    public $column_name_width = 120;
    public $column_name_order = 2;
    /**
     * column_name
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_name($row){
        $bs_id = $row[$this->col_prefix.'bs_id'];
        if(!isset($this->business[$bs_id])) {
            $this->business[$bs_id] = $this->_getBusiness($bs_id);
        }
        return $this->business[$bs_id]['name'];
    }

    private function _getBusiness($bs_id) {
        return app::get('dealer')->model('business')->db_dump(array('bs_id'=>$bs_id), 'bs_bn,name');
    }

    public $column_material_bn = "基础物料编码";
    public $column_material_bn_order = 3;
    /**
     * column_material_bn
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_material_bn($row) {
        $bm_id = $row[$this->col_prefix . 'bm_id'];
        if(!isset($this->material[$bm_id])) {
            $this->material[$bm_id] = $this->_getMaterial($bm_id);
        }
        return $this->material[$bm_id]['material_bn'];
    }

    public $column_material_name = "基础物料名称";
    public $column_material_name_order = 3;
    /**
     * column_material_name
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_material_name($row) {
        $bm_id = $row[$this->col_prefix . 'bm_id'];
        if(!isset($this->material[$bm_id])) {
            $this->material[$bm_id] = $this->_getMaterial($bm_id);
        }
        return $this->material[$bm_id]['material_name'];
    }

    private function _getMaterial($bm_id) {
        return app::get('material')->model('basic_material')->db_dump(array('bm_id'=>$bm_id), 'material_bn, material_name');
    }
}