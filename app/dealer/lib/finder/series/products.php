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

class dealer_finder_series_products
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "";
    public $endorseList = [];

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

    public $column_endorse       = '关联经销商店铺';
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
        if (!$this->endorseList) {
            $seMdl   = app::get('dealer')->model('series_endorse');
            $shopMdl = app::get('ome')->model('shop');

            $seriesIdArr       = array_column($list, 'series_id');
            $seriesEndorseList = $seMdl->getList('*', ['series_id|in' => $seriesIdArr]);
            $shopIdArr         = array_unique(array_column($seriesEndorseList, 'shop_id'));
            $shopNameList      = array_column($shopMdl->getList('shop_id,shop_bn,name'), null, 'shop_id');
            foreach ($seriesEndorseList as $k => $v) {
                $this->endorseList[$v['series_id']][] = $shopNameList[$v['shop_id']]['name'];
            }
        }

        $endorse = $detail = '';
        if (isset($this->endorseList[$row['series_id']]) && $this->endorseList[$row['series_id']]) {
            $count    = count($this->endorseList[$row['series_id']]);
            $endorse  = implode('、', $this->endorseList[$row['series_id']]);
            $detail   = implode('<br>', $this->endorseList[$row['series_id']]);
        }

        return '<span style="color:#0000ff"><div class="desc-tip" onmouseover="bindFinderColTip(event);">' . $endorse . '<textarea style="display:none;"><h4>关联经销商店铺('.$count.')</h4>' . $detail . '</textarea></div></span>';
    }
}
