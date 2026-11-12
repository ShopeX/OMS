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

class inventorydepth_finder_regulation_apply{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = 'supply_branch_id,regulation_id';
    
    public $column_operator = '操作';
    public $column_operator_order = 1;
    public $column_operator_width = 300;
    public function column_operator($row)
    {
        
        $finder_id = $_GET['_finder']['finder_id'];

        $button = <<<EOF
        <a target='_blank' href='index.php?app=inventorydepth&ctl=regulation_apply&act=edit&p[0]={$row['id']}&_finder[finder_id]={$finder_id}'>编辑</a>
EOF;
        $button1 = <<<EOF
        <a target='dialog::{width:500,height:300,title:"导入商品",resizeable:false}' href='index.php?app=omecsv&ctl=admin_import&act=main&ctler=inventorydepth_mdl_regulation_apply&add=inventorydepth&id={$row['id']}&_finder[finder_id]={$finder_id}'>导入货品</a>
EOF;
        $export_button = <<<EOF
        <a target='_blank' href='index.php?app=inventorydepth&ctl=regulation_apply&act=export_goods&p[0]={$row['id']}&_finder[finder_id]={$finder_id}'>导出货品</a>
EOF;
        $skuid_button = <<<EOF
        <a target='dialog::{width:500,height:300,title:"导入SKU ID",resizeable:false}' href='index.php?app=omecsv&ctl=admin_import&act=main&ctler=inventorydepth_mdl_regulation_apply&add=inventorydepth&id={$row['id']}&_finder[finder_id]={$finder_id}'>导入SKU ID</a>
EOF;
        $export_skuid = <<<EOF
        <a target='_blank' href='index.php?app=inventorydepth&ctl=regulation_apply&act=export_skuid&p[0]={$row['id']}&_finder[finder_id]={$finder_id}'>导出SKU ID</a>
EOF;
        return $button.' | '.$button1.' | '.$export_button . ' | ' . $skuid_button . ' | ' . $export_skuid;

    }

    public $column_regulation = '库存规则';
    public $column_regulation_order = 2;
    public $column_regulation_width = 200;
    public function column_regulation($row)
    {
        $field_name = $this->col_prefix . 'regulation_id';
        $regulation_id = $row[$field_name];
        if (empty($regulation_id)) {
            return '<span style="color:#999;">未设置规则</span>';
        }
        
        $regulationModel = app::get('inventorydepth')->model('regulation');
        $regulation = $regulationModel->db_dump($regulation_id);
        
        if (!$regulation) {
            return '<span style="color:#ff0000;">规则不存在</span>';
        }
        
        $status_color = $regulation['using'] == 'true' ? '#0066cc' : '#ff0000';
        
        return <<<EOF
        <div>
            <span style="color:{$status_color};font-weight:bold;">{$regulation['heading']}</span>
            <span style="color:{$status_color};font-size:12px;">({$regulation['bn']})</span>
        </div>
EOF;
    }

    public $column_supply_branch_id = '专用供货仓';
    public $column_supply_branch_id_order = 90;
    public $column_supply_branch_id_width = 200;
    public function column_supply_branch_id($row)
    {
        $field_name = $this->col_prefix . 'supply_branch_id';
        if (empty($row[$field_name])) {
            return '<span style="color:#999;">使用店铺默认供货仓</span>';
        }
        
        $branch_ids = explode(',', $row[$field_name]);
        $branch_ids = array_map('trim', $branch_ids);
        $branch_ids = array_filter($branch_ids);
        
        if (empty($branch_ids)) {
            return '<span style="color:#999;">使用店铺默认供货仓</span>';
        }
        
        // 获取仓库名称
        $branchModel = app::get('ome')->model('branch');
        $branches = $branchModel->getList('name,branch_bn,branch_id', array('branch_id|in' => $branch_ids));
        $branch_names = array();
        foreach ($branches as $branch) {
            $branch_names[] = $branch['name'] . ' (' . $branch['branch_bn'] . ')';
        }
        
        if (empty($branch_names)) {
            return '<span style="color:#ff0000;">供货仓不存在: ' . $row[$field_name] . '</span>';
        }
        
        return '<div style="width:200px;overflow:hidden;word-break:break-all;white-space:normal;line-height:1.4;">'
            . implode('<br/>', array_map(function($name){
                return '<span style="color:#0066cc;font-weight:bold;">' . $name . '</span>';
            }, $branch_names))
            . '</div>';
    }

}
