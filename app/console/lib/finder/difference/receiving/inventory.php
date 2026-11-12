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

class console_finder_difference_receiving_inventory
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $addon_cols = "";
    
    var $column_edit = "操作";
    var $column_edit_width = "50";
    var $column_edit_order = 1;
    
    function column_edit($row)
    {
        $page = 1;
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
        }
        $id   = $row['diff_id'];
        $view = $_GET['view'];
        $str  = "<a href='index.php?app=console&ctl=admin_difference_inventory&act=index&newAction=receiving_inventory_detail&id=" . $id . '&view=' . $view . '&page=' . $page . "'>" . '<span>' . '查看' . '</span>' . "</a>";
        return $str;
    }
    
    public $column_branch_bn = '收货仓编码';
    public $column_branch_bn_width = 100;
    public $column_branch_bn_order = 22;
    
    /**
     * column_branch_bn
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_branch_bn($row, $list)
    {
        $branch = $this->_getBranch($row['branch_id'], $list);
        return $branch['branch_bn'];
    }
    
    public $column_extrabranch_bn = '发货仓编码';
    public $column_extrabranch_bn_width = 100;
    public $column_extrabranch_bn_order = 23;
    
    /**
     * column_extrabranch_bn
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_extrabranch_bn($row, $list)
    {
        $branch = $this->_getExtrabranch($row['extrabranch_id'], $list);
        return $branch['branch_bn'];
    }
    
    private function _getBranch($branch_id, $list)
    {
        static $branch;
        if (isset($branch[$branch_id])) {
            return $branch[$branch_id];
        }
        $filter['branch_id'] = array_column($list, 'branch_id');
        $rows                = app::get('ome')->model('branch')->getList('branch_id,branch_bn,name', $filter);
        foreach ($rows as $row) {
            $branch[$row['branch_id']]['branch_bn']   = $row['branch_bn'];
            $branch[$row['branch_id']]['branch_name'] = $row['name'];
        }
        return $branch[$branch_id];
    }
    
    private function _getExtrabranch($extrabranch_id, $list)
    {
        static $branch;
        if (isset($branch[$extrabranch_id])) {
            return $branch[$extrabranch_id];
        }
        $filter['branch_id'] = array_column($list, 'extrabranch_id');
        $rows                = app::get('ome')->model('branch')->getList('branch_id,branch_bn,name', $filter);
        foreach ($rows as $row) {
            $branch[$row['branch_id']]['branch_bn']   = $row['branch_bn'];
            $branch[$row['branch_id']]['branch_name'] = $row['name'];
        }
        return $branch[$extrabranch_id];
    }
    
    public $column_diff_nums = '差异SKU';
    public $column_diff_nums_width = 80;
    public $column_diff_nums_order = 24;
    
    /**
     * column_diff_nums
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_diff_nums($row, $list)
    {
        $diff = $this->_getDiffItems($row['diff_id'], $list);
        return $diff['count_nums'];
    }
    
    private function _getDiffItems($diff_id, $list)
    {
        static $diff;
        if (isset($diff[$diff_id])) {
            return $diff[$diff_id];
        }
        $diff_ids    = array_column($list, 'diff_id');
        $str_diff_id = '"' . implode('","', $diff_ids) . '"';
        $sql         = "SELECT SUM(nums) sum_nums, count(product_id) as count_nums, diff_id FROM sdb_taoguaniostockorder_diff_items WHERE diff_id IN ($str_diff_id) GROUP BY diff_id ";
        $rows        = kernel::database()->select($sql);
        foreach ($rows as $row) {
            $diff[$row['diff_id']]['sum_nums']   = $row['sum_nums'];
            $diff[$row['diff_id']]['count_nums'] = $row['count_nums'];
        }
        return $diff[$diff_id];
    }
    
    public $column_abs_diff_nums = '差异数量';
    public $column_abs_diff_nums_width = 80;
    public $column_abs_diff_nums_order = 25;
    
    /**
     * column_abs_diff_nums
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_abs_diff_nums($row, $list)
    {
        $diff = $this->_getAbsDiffItems($row['diff_id'], $list);
        return (string)$diff['abs_sum_nums'];
    }
    
    private function _getAbsDiffItems($diff_id, $list)
    {
        ini_set('memory_limit', '128M');
        static $diff;
        if (isset($diff[$diff_id])) {
            return $diff[$diff_id];
        }
        $diff_ids    = array_column($list, 'diff_id');
        $str_diff_id = '"' . implode('","', $diff_ids) . '"';
        $sql         = "SELECT abs(nums) as nums,diff_id FROM sdb_taoguaniostockorder_diff_items WHERE diff_id IN ($str_diff_id)";
        $rows        = kernel::database()->select($sql);
        foreach ($rows as $row) {
            if (!isset($diff[$row['diff_id']]['abs_sum_nums'])) {
                $diff[$row['diff_id']]['abs_sum_nums'] = 0;
            }
            $diff[$row['diff_id']]['abs_sum_nums'] += $row['nums'];
        }
        return $diff[$diff_id];
    }
    
}
