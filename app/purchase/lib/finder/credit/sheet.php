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

class purchase_finder_credit_sheet{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "基本信息";
    var $detail_statement = "结算信息";
    var $detail_eoitem = "入库单明细";

    function __construct()
    {
        if ($_GET['p'][0]=='2') unset($this->column_edit);
    }

    /*
     * 基本信息
    */
    function detail_basic($cs_id){

        $render = app::get('purchase')->render();
        $oCredit = app::get('purchase')->model('credit_sheet');
        $oSupplier = app::get('purchase')->model('supplier');
        $detail = $oCredit->dump($cs_id,"*");

        //获取采购单编号
        $oEo =  app::get('taoguaniostockorder')->model('iso')->dump($detail['eo_id'],'iso_bn');
        $render->pagedata['eo_bn'] = $oEo['iso_bn'];

        //供应商名称
        $supplier_name = $oSupplier->supplier_detail($detail['supplier_id']);
        $render->pagedata['supplier_name'] = $supplier_name['name'];

        $render->pagedata['detail'] = $detail;
        return $render->fetch('admin/purchase/credit_sheet/basic_detail.html');
    }

    /*
     * 结算信息
     */
    function detail_statement($cs_id){

        $render = app::get('purchase')->render();
        $oCredit = app::get('purchase')->model('credit_sheet');
        $oSupplier = app::get('purchase')->model('supplier');

        //备注追加
        if($_POST){
            $credit['cs_id'] = $_POST['cs_id'];
            //取出原备注信息
            $oldmemo = $oCredit->dump(array('cs_id'=>$credit['cs_id']), 'memo');
            $oldmemo= unserialize($oldmemo['memo']);
            $byinfo = '  ('.date('Y-m-d H:i',time()).' by '.kernel::single('desktop_user')->get_name().')';
            if ($oldmemo)
            foreach($oldmemo as $k=>$v){
                $memo[] = $v;
            }
            $op_name = kernel::single('desktop_user')->get_name();
	        $newmemo =  htmlspecialchars($_POST['memo']);
	        $memo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$newmemo);
            //$memo[] = htmlspecialchars($_POST['memo'].$byinfo);
            $credit['memo'] = $memo;
            $credit['bank_no'] = htmlspecialchars($_POST['bank_no']);
            $oCredit->save($credit);
        }

        //读取赊购单详情
        $detail = $oCredit->dump($cs_id,"*");
        //管理员姓名
        $oOpid = app::get('desktop')->model('users')->dump($detail['op_id'],'name');

        $detail['memo'] = unserialize($detail['memo']);
        $render->pagedata['detail'] = $detail;
        $render->pagedata['username'] = $oOpid['name'];
        return $render->fetch('admin/purchase/credit_sheet/statement_detail.html');

    }

    /*
     * 入 库单明细
     */
    function detail_eoitem($cs_id){
        $render = app::get('purchase')->render();
        $oEo = app::get('purchase')->model("eo");
        $oCredit = app::get('purchase')->model('credit_sheet');
        $detail = $oCredit->dump($cs_id,"eo_id");

        $eo = $oEo->eo_detail_iso($detail['eo_id']);
        $render->pagedata['eo'] = $eo;
        return $render->fetch('admin/purchase/credit_sheet/eo_item.html');
    }

    var $addon_cols = "cs_id,statement_status";
    var $column_edit = "操作";
    var $column_edit_width = "60";
    function column_edit($row){

        $title = "赊账单结算";
        $cs_id = $row[$this->col_prefix.'cs_id'];

        if ($row[$this->col_prefix.'statement_status']=='1' ||$row[$this->col_prefix.'statement_status'] =='4')
        return "<a class=\"lnk\" onclick=\"new Dialog('index.php?app=purchase&amp;ctl=admin_credit_sheet&act=statement&p[0]=$cs_id',{width:600,height:380,title:'$title'});\" name=\"merge\">结算</a>";
        else
        return "-";

    }
}
?>