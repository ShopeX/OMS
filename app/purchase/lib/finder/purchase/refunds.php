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
class purchase_finder_purchase_refunds{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "详细信息";
    var $detail_statement = "结算信息";
    var $detail_ReturnItems = "";
    var $detail_ReturnItems_eo = "采购退货明细";
    var $detail_ReturnItems_po = "入库取消明细";
    var $detail_ReturnItems_iso = "出库明细";
    
    function __construct()
    {
        if ( in_array($_GET['p'][0],array('3','2') )){
            unset($this->column_edit);
        }
        //判断退款类型，显示相应的明细单
        $oRefunds = app::get('purchase')->model('purchase_refunds');
        $detail = $oRefunds->dump($_GET['id'], "type");
        if ($detail['type']=='eo'){
            $this->detail_ReturnItems = $this->detail_ReturnItems_eo;
        }elseif ($detail['type']=='iso'){
            $this->detail_ReturnItems = $this->detail_ReturnItems_iso;
        }else{
            $this->detail_ReturnItems = $this->detail_ReturnItems_po;
        }
    }
    
    function detail_basic($refund_id){
        $render = app::get('purchase')->render();
        $oRefunds = app::get('purchase')->model('purchase_refunds');
        $oSupplier = app::get('purchase')->model('supplier');
        $detail = $oRefunds->dump($refund_id,"*");
        
        //获取退货单编号
        $oRp = app::get('purchase')->model("returned_purchase")->dump($detail['rp_id'],'rp_bn,object_id,rp_type');
        
        
        $oPo = app::get('purchase')->model('po');
        $oEo = app::get('purchase')->model('eo');
        if ($oRp['rp_type']=='po'){
            $bndetail = $oPo->dump($oRp['object_id'], 'po_bn');
            $bn = $bndetail['po_bn'];
        }elseif ($oRp['rp_type']=='eo'){
            $bndetail = $oEo->dump($oRp['object_id'], 'eo_bn');
            $bn = $bndetail['eo_bn'];
        }else $bn = '-';
        
        $render->pagedata['bn'] = $bn;
        $render->pagedata['rp_type'] = $oRp['rp_type'];
        $supplier_name = $oSupplier->supplier_detail($detail['supplier_id']);
        $render->pagedata['supplier_name'] = $supplier_name['name'];
        
        $render->pagedata['detail'] = $detail;
        return $render->fetch('admin/purchase/refunds/basic_detail.html');
    }
    
    /*
     * 结算信息
     */
    function detail_statement($refund_id){
        $render = app::get('purchase')->render();
        $oRefunds = app::get('purchase')->model('purchase_refunds');
        $detail = $oRefunds->dump($refund_id,"*");
        
        //备注追加
        if($_POST){
            $refund['refund_id'] = $_POST['refund_id'];
            //取出原备注信息
            $oldmemo = $oRefunds->dump(array('refund_id'=>$refund['refund_id']), 'memo');
            $oldmemo= unserialize($oldmemo['memo']);
            if ($oldmemo)
            foreach($oldmemo as $k=>$v){
                $memo[] = $v;
            }
            $op_name = kernel::single('desktop_user')->get_name();
	        $newmemo =  htmlspecialchars($_POST['memo']);
	        $memo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$newmemo);
	        
            $refund['memo'] = $memo;
            $refund['bank_no'] = htmlspecialchars($_POST['bank_no']);
            $refund['payment'] = $_POST['payment'];
            $oRefunds->save($refund);
        }
        
        if ($detail['statement_status']=='2'){
            //管理员姓名
            $oOpid = app::get('desktop')->model('users')->dump($detail['op_id'],'name');
            //#结算支付方式
            $render->pagedata['payment'] = $oRefunds->getPayment();
            $render->pagedata['username'] = $oOpid['name'];
        }
        
        if ($detail['payment'])
        $detail['payments'] = $oRefunds->getPayment($detail['payment']);
        
        $detail['memo'] = unserialize($detail['memo']);
        $render->pagedata['detail'] = $detail;
        return $render->fetch('admin/purchase/refunds/statement_detail.html');
    }
    
    /*
     * 采购退货明细、入库取消明细
     */
    function detail_ReturnItems($refund_id=null){
        
        $render = app::get('purchase')->render();
        $oRefunds = app::get('purchase')->model('purchase_refunds');
        $oReturned = app::get('purchase')->model('returned_purchase');
        $refundDetail = $oRefunds->dump($refund_id, "*");
        //$object_id = $oReturned->dump($refundDetail['rp_id'], 'object_id');
        if ($refundDetail['type']=='eo'){
            $detail = $oReturned->returned_purchase_items($refundDetail['rp_id']);
        }elseif ($refundDetail['type']=='iso'){
            $oIso = app::get('taoguaniostockorder')->model('iso');
            $detail = $oIso->iso_items($refundDetail['rp_id']);
            $refundDetail['type']='eo';  
        }else{
            $detail = $oReturned->returned_purchase_items($refundDetail['rp_id']);
        }
        $render->pagedata['detail'] = $detail;
        if ($refundDetail['type']) {
            return $render->fetch('admin/purchase/refunds/'.$refundDetail['type'].'_items.html');
        }else{
            return '数据异常';
        }
    }

    var $addon_cols = "refund_id,statement_status,rp_id";
    var $column_edit = "操作";
    var $column_edit_width = "60";
    //var $column_bn = "关联单据编号";
    var $column_bn_width = "140";
    function column_edit($rows){
        $refund_id = $rows[$this->col_prefix.'refund_id'];
        $title = "退款单结算";
        
        if ($rows[$this->col_prefix.'statement_status']==1)
        return "<a class=\"lnk\" onclick=\"new Dialog('index.php?app=purchase&amp;ctl=admin_purchase_refunds&act=statement&p[0]=$refund_id',{width:700,height:380,title:'$title'});\" name=\"merge\">结算</a>";
        else 
        return "-";

    }
    
    function column_bn($rows){
        $rp_id = $rows[$this->col_prefix.'rp_id'];
        $oRefund = app::get('purchase')->model('returned_purchase');
        $oPo = app::get('purchase')->model('po');
        $oEo = app::get('purchase')->model('eo');
        $oRefuned_purchase = $oRefund->dump($rp_id, 'object_id,rp_type');
        if ($oRefuned_purchase['rp_type']=='po'){
            $bndetail = $oPo->dump($oRefuned_purchase['object_id'], 'po_bn');
            $bn = $bndetail['po_bn'];
        }elseif ($oRefuned_purchase['rp_type']=='eo'){
            $bndetail = $oEo->dump($oRefuned_purchase['object_id'], 'eo_bn');
            $bn = $bndetail['eo_bn'];
        }else $bn = '-';
        
        return $bn;
    }
}
?>
