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

class purchase_finder_statement{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    
    var $detail_basic = "结算明细";
    
    /*
    function detail_basic($supplier_id){
        
        $render = app::get('purchase')->render();
        
        $oStatement = app::get('purchase')->model('statement');
        $statementList = $oStatement->statementDetail($supplier_id, $wheresql);
        
        $render->pagedata['statement_list'] = $statementList;
        //类型类型
        $render->pagedata['statement_type'] = $oStatement->getStatementType();

        return $render->fetch('admin/purchase/statement/statement_list.html');
        
    }
    */
    
    
    var $addon_cols = "supplier_id,supplier_bn";
    var $column_show = "供应商名称";
    var $column_show_width = "110";
    //var $column_prints = "打印";
    //var $column_prints_width = "50";
    
    function column_show($row){
        $oSupplier = app::get('purchase')->model('supplier');
        $supplier = $oSupplier->dump($row[$this->col_prefix.'supplier_id'],'name,bn');
        return $supplier['name'];
    }
    //function column_prints($row){
        //return '<a href=\'index.php?app=purchase&ctl=admin_statement&act=statement_print&statement_id='.$row[$this->col_prefix.'supplier_id'].'\' target=\'_blank\'>打印</a>';
    //}
    
}
?>