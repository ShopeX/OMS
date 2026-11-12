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
* 打印模板finder类
*
* @author chenping<chenping@shopex.cn>
* @version 2012-4-18 13:39
*/
class ome_finder_print_otmpl 
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    function __construct(&$app)
    {
    }

    var $column_control = '操作';
    public function column_control($row){
        return '<a href="index.php?app=ome&ctl=admin_print_otmpl&act=show&p[0]='.$row['id'].'&p[1]='.$row['type'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="_blank">编辑</a>';
    }

}
