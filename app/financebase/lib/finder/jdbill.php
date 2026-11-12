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
 * 京东钱包流水Finder类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class financebase_finder_jdbill
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    //调用扩展字段
    public $addon_cols = 'error_data';
    
    var $column_edit = '操作';
    var $column_edit_width = 120;
    var $column_edit_order = 2;
    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $view = isset($_GET['view']) ? $_GET['view'] : 0;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        
        //查看明细
        $url = 'index.php?app=financebase&ctl=admin_shop_settlement_jdbill&act=detailed';
        $seeBtn = '<a href="'. $url .'&id='. $row['id'] .'&finder_id='. $finder_id .'&view='.$view.'&page='.$page.'" >查看明细</a>';
        
        return $seeBtn;
    }
    
    var $column_download = '文件下载';
    var $column_download_width = "100";
    var $column_download_order = 5;
    function column_download($row)
    {
        $data = $row[$this->col_prefix .'error_data'];
        if (!$data[10]) {
            return '';
        }
        
        $text = '错误文件';
        return '<a target="_blank" href="index.php?app=financebase&ctl=admin_shop_settlement_jdbill&act=downloaderr&id='. $row['id'] .'" >'.$text.'</a>';
    }
}