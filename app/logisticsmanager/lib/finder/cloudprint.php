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

class logisticsmanager_finder_cloudprint
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "id,node_id";
    public $column_control = '操作';
    public $column_control_width = '100';
    public $column_control_order = COLUMN_IN_HEAD;
    
    /**
     * column_control
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_control($row)
    {
        $id = $row[$this->col_prefix . 'id'];
        $finder_id    = $_GET['_finder']['finder_id'];
        $node_id = $row[$this->col_prefix . 'node_id'];
        $button='';
        $button =$node_id ? '' :"<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=logisticsmanager&ctl=admin_cloudprint&act=add&p[0]={$id}&finder_id={$_GET['_finder']['finder_id']}',{width:800,height:600,title:'打印机编辑'}); \">编辑</a>";
        
        $button.= $node_id ? sprintf("  <a class='c-red' href='javascirpt:void(0);' url='index.php?app=logisticsmanager&ctl=admin_cloudprint&act=bindNodeId&&p[0]=%s&p[1]=%s&finder_id=%s' onclick='javascript:if(confirm(\"确认取消绑定？\")){W.page(this.get(\"url\"));}'>取消绑定</a>", $id,'unbind',$finder_id) : sprintf("  <a href='javascirpt:void(0);' url='index.php?app=logisticsmanager&ctl=admin_cloudprint&act=bindNodeId&&p[0]=%s&p[1]=%s&finder_id=%s' onclick='javascript:if(confirm(\"确认绑定？\")){W.page(this.get(\"url\"));}'>绑定</a>", $id,'bind',$finder_id);


        return $button;
    }
    
    
   
    
}
