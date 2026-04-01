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
class purchase_finder_supplier{
    var $detail_basic = "详细信息";
    var $detail_current_account = "往来帐";
    var $detail_balance = "预付款";
    var $addon_cols = "supplier_id,bn,name";
    var $column_operator = "操作";
    var $column_operator_width = "150";
    
    function detail_basic($supplier_id){
        
        $render = app::get('purchase')->render();
        
        //加载supplier模块
        $oSupplier = app::get('purchase')->model('supplier');
        
        //读取供应商详情
        $supplier_detail = $oSupplier->dump($supplier_id,"*");
        
        //读取供应商提供的品牌列表，以brand_id存储
        $oBrand = app::get('purchase')->model('supplier_brand');
        $filter = array("supplier_id"=>$supplier_id);
        $brand_select_id = $oBrand->getlist('*',$filter);

        //关联到品牌数据表的品牌名称，将brand_id转换成brand_name
        $brandList = app::get('ome')->model('brand');
        if ($brand_select_id)
        foreach ($brand_select_id as $k=>$v){
            $brandname = $brandList->dump($v,'brand_name');
            $brand_select_name[$k] = $brandname['brand_name'];
        }
        
        //赋值给模板页面
        $contacter = unserialize($supplier_detail['contacter']);
        if ($contacter)
        foreach ($contacter as $k=>$v){
              $v['qqwangwang'] = $oSupplier->chat_code($v['qqwangwang']);
              $contacter[$k] = $v;
        }
        $supplier_detail['contacter'] = $contacter;
        $render->pagedata['supplier'] = $supplier_detail;
        $render->pagedata['brand'] = $brand_select_name;
        
        return $render->fetch('admin/supplier/basic_detail.html');
        
    }
    
    /*
     * 往来帐 detail_current_account
     */
    function detail_current_account($supplier_id){
        $render = app::get('purchase')->render();
        $oStatement = app::get('purchase')->model('statement');
        $result = $oStatement->statement_counter($supplier_id);

        $result['supplier_id'] = $supplier_id;
        $render->pagedata['results'] = $result;
        return $render->fetch('admin/purchase/statement/statement_counter.html');
    }
    
    /*
     * 预付款 detail_balance
     */
    function detail_balance($supplier_id){
        $render = app::get('purchase')->render();
        $oSupplier = app::get('purchase')->model('supplier');
        $result = $oSupplier->get_balance($supplier_id);
        //供应商名称
        $supplier = $oSupplier->supplier_detail($supplier_id, 'name');

        $render->pagedata['supplier_name'] = $supplier['name'];
        $render->pagedata['balance'] = $result;
        return $render->fetch('admin/supplier/balance.html');
    }
    
    function column_operator($row){
    	$finder_id = $_GET['_finder']['finder_id'];
        //编辑
        $supplier_id = $row[$this->col_prefix.'supplier_id'];
        $supplier_name = $row[$this->col_prefix.'name'];
        $button = "<a class=\"lnk\" href=\"index.php?app=purchase&amp;ctl=admin_supplier&amp;act=editsupplier&amp;p[0]=$supplier_id&finder_id=$finder_id\" target=\"_blank\">编辑</a>";
        //商品
        $supplier_bn = $row[$this->col_prefix.'bn'];
        //$button .= "&nbsp;&nbsp;&nbsp;<a class=\"lnk\" href=\"index.php#app=ome&amp;ctl=admin_goods&p[0]=$supplier_id\"  target=\"_blank\">查看商品</a>";
        $url='index.php?app=desktop&act=alertpages&goto='.urlencode('index.php?app=purchase&ctl=admin_purchase&act=findMaterial&p[0]='.$supplier_id);
        $button .= "&nbsp;&nbsp;&nbsp;<a class=\"lnk\" href=\"".$url."\" target='_blank'>查看关联基础物料</a>";
        //往来帐
        //$button .= "&nbsp;&nbsp;<span class=\"lnk\" onclick=\"new Dialog('index.php?app=purchase&amp;ctl=admin_statement&act=statement_counter&p[0]=$supplier_id',{height:300,width:750,title:'$supplier_name - 往来账(供应商编号：$supplier_bn)'});\">往来账</span>";
        //预付款
        //$button .= "&nbsp;&nbsp;<span class=\"lnk\" onclick=\"new Dialog('index.php?app=purchase&amp;ctl=admin_supplier&act=balance&p[0]=$supplier_id',{width:450,title:'$supplier_name - 预付款(供应商编号：$supplier_bn)'});\">预付款</span>";
        
        return $button;
    }
    /*
    var $column_view = "商品";
    var $column_view_width = "60";
    function column_view($row){

        $supplier_id = $row[$this->col_prefix.'supplier_id'];
        $supplier_bn = $row[$this->col_prefix.'bn'];
        //return "<input type=\"button\"  class=\"sysiconBtnNoIcon\" onclick=\"new Dialog('index.php?app=ome&amp;ctl=admin_goods&p[0]=$supplier_id',{width:800,title:'供应商 - $supplier_bn'});\" value=\"查看\">";
        return "<a class=\"lnk\" href=\"index.php?app=ome&amp;ctl=admin_goods&p[0]=$supplier_id\">商品</a>";
        
    }
    
    var $column_finance = "财务";
    var $column_finance_width = "70";
    function column_finance($row){
        $supplier_id = $row[$this->col_prefix.'supplier_id'];
        $supplier_name = $row[$this->col_prefix.'name'];
        $supplier_bn = $row[$this->col_prefix.'bn'];
        //return "<a class=\"sysiconBtnNoIcon\" href=\"index.php?app=purchase&amp;ctl=admin_statement&act=statement_counter&p[0]=$supplier_id\">结算单</a>";
        return "<span class=\"lnk\" onclick=\"new Dialog('index.php?app=purchase&amp;ctl=admin_statement&act=statement_counter&p[0]=$supplier_id',{height:300,width:750,title:'$supplier_name - 往来账(供应商编号：$supplier_bn)'});\">往来账</span>";
    }
    
    var $column_balance = "供应商";
    var $column_balance_width = "70";
    function column_balance($row){
        $supplier_id = $row[$this->col_prefix.'supplier_id'];
        $supplier_name = $row[$this->col_prefix.'name'];
        $supplier_bn = $row[$this->col_prefix.'bn'];
        return "<span class=\"lnk\" onclick=\"new Dialog('index.php?app=purchase&amp;ctl=admin_supplier&act=balance&p[0]=$supplier_id',{width:450,title:'$supplier_name - 预付款(供应商编号：$supplier_bn)'});\">预付款</span>";
    }
    */
}
?>
