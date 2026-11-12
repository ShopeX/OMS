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

class dealer_finder_series_endorse
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "";
    public $bsMaterialList = [];

    public $column_status       = '状态';
    public $column_status_width = 60;
    public $column_status_order = 40;
    /**
     * column_status
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_status($row, $list)
    {
        switch ($row['status']) {
            case 'active':
                $status = '<span style="color:#109010">启用</span>';
                break;
            case 'close':
                $status = '<span style="color:#e60000">停用</span>';
                break;
            default:
                $status = $row['status'];
                break;
        }
        return $status;
    }

    public $column_endorse       = '关联基础物料';
    public $column_endorse_width = 110;
    public $column_endorse_order = 65;
    /**
     * column_endorse
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_endorse($row, $list)
    {
        if (!$this->bsMaterialList) {
            $bsIdArr     = array_column($list, 'bs_id');
            $seriesIdArr = array_column($list, 'series_id');

            // 获取产品线id+贸易公司id下的所有店铺
            $seMdl  = app::get('dealer')->model('series_endorse');
            $seList = $seMdl->getList('bs_id,series_id,shop_id,en_id', ['series_id|in'=>$seriesIdArr, 'bs_id|in' => $bsIdArr]);

            // 获取上述条件下店铺的bm_id
            $enIdArr = array_unique(array_column($seList, 'en_id'));
            $sepMdl  = app::get('dealer')->model('series_endorse_products');
            $_sepList = $sepMdl->getList('series_id,shop_id,bm_id', ['en_id|in' => $enIdArr]);
            
            // 获取商品信息
            $bmIdList = array_unique(array_column($_sepList, 'bm_id'));
            $bmMdl  = app::get('material')->model('basic_material');
            $bmList = $bmMdl->getList('bm_id,material_bn,material_name', ['bm_id|in' => $bmIdList]);
            $bmList = array_column($bmList, null, 'bm_id');
            
            // 根据bm_id关联商品信息（以产品线下的店铺为维度整合商品信息）
            $sepList = [];
            foreach ($_sepList as $sep_k => $sep_v) {
                if (!isset($sepList[$sep_v['series_id']])) {
                    $sepList[$sep_v['series_id']] = [];
                }
                if (!isset($sepList[$sep_v['series_id']][$sep_v['shop_id']])) {
                    $sepList[$sep_v['series_id']][$sep_v['shop_id']] = [];
                }
                $sepList[$sep_v['series_id']][$sep_v['shop_id']][$sep_v['bm_id']] = $bmList[$sep_v['bm_id']];
            }

            // 将店铺下的商品信息整合到产品线下的经销公司下
            foreach ($seList as $k => $v) {
                if (isset($sepList[$v['series_id']]) && $sepList[$v['series_id']][$v['shop_id']]) {
                    if (!isset($this->bsMaterialList[$v['series_id'].'-'.$v['bs_id']])) {
                        $this->bsMaterialList[$v['series_id'].'-'.$v['bs_id']] = [];
                    }
                    foreach ($sepList[$v['series_id']][$v['shop_id']] as $bm_id => $ss_v) {
                        $this->bsMaterialList[$v['series_id'].'-'.$v['bs_id']][$bm_id] = $ss_v;
                    }
                }
            }
        }
        $endorse = $detail = '';
        // 在model的finder_getList中，已经重新定义了en_id的值，en_id = series_id.-.bs_id
        if (isset($this->bsMaterialList[$row['en_id']]) && $this->bsMaterialList[$row['en_id']]) {

            $materialList = array_column($this->bsMaterialList[$row['en_id']], 'material_name');
            $count    = count($materialList);
            $endorse  = implode('、', $materialList);
            $detail   = implode('<br>', $materialList);
        }

        return "<a><span style=\"color:#0000ff\" class='show_list' series_id=" . $row['series_id'] . " bs_id=" . $row['bs_id'] . "><div class=\"desc-tip\" onmouseover=\"bindFinderColTip(event);\">" . $endorse . "<textarea style=\"display:none;\"><h4>关联基础物料($count)</h4>" . $detail . "</span></textarea></div></span>";
    }
}
