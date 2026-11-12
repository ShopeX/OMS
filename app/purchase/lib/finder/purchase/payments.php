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

class purchase_finder_purchase_payments{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "基本信息";
    var $detail_statement = "结算信息";
    var $detail_poitem = "采购单明细";
    
    function __construct()
    {
        if ( in_array($_GET['p'][0],array('3','2') )){
            unset($this->column_edit);
        }
    }
    
    /*
     * 基本信息
    */
    function detail_basic($payment_id){
        $render = app::get('purchase')->render();
        $oPayment = app::get('purchase')->model('purchase_payments');
        $oSupplier = app::get('purchase')->model('supplier');

        //读取付款单详情
        $payment_detail = $oPayment->dump($payment_id,"*");
        //采购单编号
        $oPo = app::get('purchase')->model("po")->dump($payment_detail['po_id'],'po_bn');
        $payment_detail['po_bn'] = $oPo['po_bn'];
        //供应商名称
        $supplier_name = $oSupplier->supplier_detail($payment_detail['supplier_id']);
        $payment_detail['supplier_name'] = $supplier_name['name'];
        
        $render->pagedata['detail'] = $payment_detail;
        return $render->fetch('admin/purchase/payments/basic_detail.html');
    }
    
    /*
     * 结算信息
     */
    function detail_statement($payment_id){
        $render = app::get('purchase')->render();
        //加载refunds模块
        $oRefunds = app::get('purchase')->model('purchase_refunds');
        $oPayment = app::get('purchase')->model('purchase_payments');
        $oSupplier = app::get('purchase')->model('supplier');
        
        //备注追加
        if($_POST){
            $pay['payment_id'] = $_POST['payment_id'];
            //取出原备注信息
            $oldmemo = $oPayment->dump(array('payment_id'=>$pay['payment_id']), 'memo');
            $oldmemo= unserialize($oldmemo['memo']);
            //$byinfo = '  ('.date('Y-m-d H:i',time()).' by '.kernel::single('desktop_user')->get_name().')';
            if ($oldmemo)
            foreach($oldmemo as $k=>$v){
                $memo[] = $v;
            } 
            $op_name = kernel::single('desktop_user')->get_name();
	        $newmemo =  htmlspecialchars($_POST['memo']);
	        $memo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$newmemo);
	        
            $pay['memo'] = $memo;
            $pay['tax_no'] = htmlspecialchars($_POST['tax_no']);
            $pay['bank_no'] = htmlspecialchars($_POST['bank_no']);
            $pay['logi_no'] = htmlspecialchars($_POST['logi_no']);
            $pay['payment'] = $_POST['payment'];
            $oPayment->save($pay);
        }
        
        //读取付款单详情
        $payment_detail = $oPayment->dump($payment_id,"*");
        
        if ($payment_detail['statement_status']=='2'){
            //管理员姓名
            $oOpid = app::get('desktop')->model('users')->dump($payment_detail['op_id'],'name');
            //结算支付方式
            $render->pagedata['payment'] = $oRefunds->getPayment();
            $render->pagedata['username'] = $oOpid['name'];
        }
        if ($payment_detail['payment'])
        $payment_detail['payments'] = $oRefunds->getPayment($payment_detail['payment']);
        
        $payment_detail['memo'] = unserialize($payment_detail['memo']);
        $render->pagedata['detail'] = $payment_detail;
        return $render->fetch('admin/purchase/payments/statement_detail.html');
    }
    
    /*
     * 采购明细
     */
    function detail_poitem($payment_id)
    {
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $render = app::get('purchase')->render();
        $oPayment = app::get('purchase')->model('purchase_payments');
        $poObj  = app::get('purchase')->model('po');
        
        $payment_detail = $oPayment->dump($payment_id,"po_id");
        
        $po = $poObj->dump($payment_detail['po_id'],'po_id',array('po_items'=>array('*')));
        if ($po['po_items'])
        foreach ($po['po_items'] as $k => $i)
        {
            $p    = $basicMaterialLib->getBasicMaterialExt($i['product_id']);
            
            $po['po_items'][$k]['bn']   = $p['material_bn'];
            $po['po_items'][$k]['name'] = $p['material_name'];
            $po['po_items'][$k]['info'] = $p['specifications'];
        }
        $render->pagedata['po_items'] = $po['po_items'];
        return $render->fetch("admin/purchase/payments/purchase_item.html");
    }

    
    var $addon_cols = "payment_id,statement_status";
    var $column_edit = "操作";
    var $column_edit_width = "60";
    function column_edit($row){
        
        $payment_id = $row[$this->col_prefix.'payment_id'];
        $title = "付款单";
        $statement_status = $row[$this->col_prefix.'statement_status'];
        #未结算、部分结算，可以继续付款
        if ($statement_status ==1 || $statement_status == 4)
        return "<a class=\"lnk\" onclick=\"new Dialog('index.php?app=purchase&amp;ctl=admin_purchase_payments&act=statement&p[0]=$payment_id',{width:600,height:410,title:'$title'});\" name=\"merge\">付款</a>";
        else 
        return "-";

    }
}
?>