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
 * WMS仓储异常错误码
 *
 * @author wangbiao@shopex.cn
 * @version $Id: Z
 */
class wmsmgr_finder_abnormal_code
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = '';
    
    //操作
    var $column_confirm = '操作';
    var $column_confirm_width = '120';
    var $column_confirm_order = 1;
    function column_confirm($row)
    {
        $abnormal_id = $row['abnormal_id'];
        $url = "index.php?app=wmsmgr&ctl=admin_abnormal_code&act=edit&p[0]={$abnormal_id}&finder_id={$_GET['_finder']['finder_id']}";
        
        $str = "<a href='javascript:void(0);' target='download' onclick=\"new Dialog('%s', {width:500,height:300,title:'修改错误码'}); \">修改</a>";
        
        return sprintf($str, $url);
    }
}
