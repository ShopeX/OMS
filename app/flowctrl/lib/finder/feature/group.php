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
 * 类目列表扩展Lib类
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

class flowctrl_finder_feature_group{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $column_edit = '操作';
    function column_edit($row){
        if($_GET['ctl'] == 'admin_feature_group' && $_GET['act'] == 'index'){
            return "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=flowctrl&ctl=admin_feature_group&act=edit&p[0]={$row[ftgp_id]}&finder_id={$_GET[_finder][finder_id]}',{width:600,height:400,title:'编辑特性'});\">编辑</a>";
        }else{
            return '-';
        }
    }

}
