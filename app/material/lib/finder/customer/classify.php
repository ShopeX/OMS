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
 * 客户分类finder
 *
 * @author wangbiao@shopex.cn
 * @version 2025.06.12
 */
class material_finder_customer_classify
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $_appName = 'material';
    public $addon_cols = 'class_id';
    
    public $column_edit = '操作';
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row)
    {
        // legacy builder 注入 col_prefix；vue 模式未设置 ⇒ 优先读 addon_cols 顺序下发的原始值别名
        $id = $this->vue_mode
            ? ($row['_0_class_id'] ?? $row['class_id'] ?? 0)
            : $row[$this->col_prefix . 'class_id'];

        if ($this->vue_mode) {
            // Vue Finder 契约：结构化行操作（不再下发 legacy <a> HTML 片段，SPA 不解析 legacy 链接）
            return array(
                array(
                    'key'    => 'edit',
                    'label'  => '编辑',
                    'action' => 'index.php?app=' . $this->_appName . '&ctl=admin_customer_classify&act=edit&p[0]=' . $id,
                ),
            );
        }

        $finder_id = $_GET['_finder']['finder_id'];
        
        $button = '<a href="index.php?app='. $this->_appName . '&ctl=admin_customer_classify&act=edit&p[0]='. $id .'&finder_id='. $finder_id .'" target="dialog::{width:600,height:300,title:\'编辑客户分类\'}">编辑</a>';
        
        return $button;
    }
}