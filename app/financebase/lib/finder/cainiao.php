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

class financebase_finder_cainiao
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_edit = "操作";
    var $column_edit_width = "110";
    var $column_edit_order = 4;
    
    public $addon_cols = 'error_data';//调用字段

    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $view = isset($_GET['view']) ? $_GET['view'] : 0;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        
        //文件类型
        $catType = 'cainiao';
        switch ($row['type'])
        {
            case 'jzt':
                $catType = 'jingzhuntong';
                break;
            case 'jdbill':
                $catType = 'jdbill';
                break;
            default:
        }
        
        $url = '<a href="index.php?app=financebase&ctl=admin_shop_settlement_'. $catType .'&act=detailed';
        $seeBtn = $url .'&id=' . $row['id'] . '&finder_id=' . $finder_id . '&view='.$view.'&page='.$page.'" >查看明细</a>';

        return $seeBtn;
    }
    
    var $column_download = "文件下载";
    var $column_download_width = "100";
    var $column_download_order = 5;

    function column_download($row)
    {
        $data = $row[$this->col_prefix . 'error_data'];
        if (!$data[10]) {
            return '';
        }
        
        //文件类型
        $catType = 'cainiao';
        switch ($row['type'])
        {
            case 'jzt':
                $catType = 'jingzhuntong';
                break;
            case 'jdbill':
                $catType = 'jdbill';
                break;
            default:
        }
        
        $text = '错误文件';
        $url = '<a target="_blank" href="index.php?app=financebase&ctl=admin_shop_settlement_'. $catType .'&act=downloaderr';
        
        return $url .'&id=' . $row['id'] . '" >'.$text.'</a>';
    }
}