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

class financebase_finder_bill_fee_type {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


	var $column_edit = "设置";
    var $column_edit_width = "80";

    /** 店铺缓存（legacy 动态属性，显式声明以避免 PHP8 动态属性告警） */
    var $shop_list = array();

    function column_edit($row) {
        if ($this->vue_mode) {
            // Vue Finder 契约：结构化行操作（不下发 legacy HTML 片段）。
            // 与 legacy 一致 —— 仅 taobao + business_type=fx 的店铺行才有「设置白名单」。
            if (!$this->_isWhiteListShop($row)) {
                return array();
            }

            return array(
                array(
                    'key'         => 'setWhitelist',
                    'label'       => '设置白名单',
                    'action'      => 'setWhitelist',
                    'fee_type_id' => (int) $row['fee_type_id'],
                ),
            );
        }

        $finder_id = $_GET['_finder']['finder_id'];

        $ret = '';
        if($this->_isWhiteListShop($row))
        {
        	$ret .= '<a href="index.php?app=financebase&ctl=admin_shop_settlement_fee_type&act=setWhiteList&p[0]='.$row['fee_type_id'].'&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '" target="_blank">设置白名单</a>';
        }


        return $ret;
    }

    /**
     * 是否渲染「设置白名单」（legacy column_edit 原判据：taobao + business_type=fx）。
     */
    private function _isWhiteListShop($row) {
        if(!$this->shop_list)
        {
        	$this->shop_list = array_column(financebase_func::getShopList(financebase_func::getShopType()),null,'shop_id');
        }

        $shop = $this->resolveShop($row);

        return $shop && $shop['shop_type'] == 'taobao' && $shop['business_type'] == 'fx';
    }

    /**
     * 按行取店铺：先按 shop_id 命中（legacy 行 = 原始外键）；
     * 未命中时按**店铺名**反查 —— Vue 契约下列表回包把 `table:shop@ome` 关联列渲染成了展示名，
     * 原始 32 位 id 已不在行里（不反查 ⇒ 行操作「设置白名单」在 Vue 页永远不出现）。
     */
    private function resolveShop($row) {
        $key = isset($row['shop_id']) ? (string) $row['shop_id'] : '';
        if ($key === '') {
            return null;
        }

        if (isset($this->shop_list[$key])) {
            return $this->shop_list[$key];
        }

        foreach ($this->shop_list as $item) {
            if (isset($item['name']) && (string) $item['name'] === $key) {
                return $item;
            }
        }

        return null;
    }


}
