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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: alt+t
 * @describe: 类
 * ============================
 */
class console_finder_vopbill {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = 'status,sync_status,discount_sync_status,bill_number,sku_count';
    

    public $detail_basic = '账单总览';

    /**
     * detail_basic
     * @param mixed $id ID
     * @return mixed 返回值
     */

    public function detail_basic($id){
        $render = app::get('console')->render();
       
        $discountMdl = app::get('console')->model('vopbill_discount');

        $discounts = $discountMdl->db->selectrow("SELECT sum(datasign*totalbillamount) as dis_amount FROM sdb_console_vopbill_discount WHERE bill_id=".$id."");

        $render->pagedata['discounts'] = $discounts;

        $vopbillMdl = app::get('console')->model('vopbill');
        $vopbills = $vopbillMdl->dump($id,'*');
      
        $render->pagedata['vopbills'] = $vopbills;
        $objMath    = kernel::single('eccommon_math');
        

        $total_qty = $objMath->number_minus(array($vopbills['cr_cust_quantity'], $vopbills['dr_cust_quantity']));

        $total_qty = $objMath->number_plus( array($total_qty, $vopbills['other_quantity']) );
        $render->pagedata['total_qty'] = $total_qty;


        $total_amount = $objMath->number_plus(array($vopbills['cr_cust_amount'], $vopbills['dr_cust_amount'],$vopbills['other_amount'],$vopbills['discount_amount']));

        $render->pagedata['total_amount'] = $total_amount;
        $details = kernel::single('console_vopbill')->getDetail($id);

        $render->pagedata['details'] = $details;


        $summary = kernel::single('console_vopbill')->getBillsAmount($id);

        $render->pagedata['summary'] = $summary;
        return $render->fetch('admin/vop/bill_basic.html');
    }
    public $detail_amount = '销售/客退';
    /**
     * detail_amount
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_amount($id)
    {
        @ini_set('memory_limit','1024M');
        $render = app::get('console')->render();
        $items  = app::get('console')->model('vopbill_amount')->getList('*', array('bill_id' => $id));
        foreach ($items as $key => $value) {
            
            $items[$key]['price_retail'] = $sku['price_retail'];
            $items[$key]['price'] = $value['amount']/$value['qty'];
        }


        $render->pagedata['items'] = $items;
        if ($this->vue_mode) { return []; }
        return $render->fetch('admin/vop/bill_amount.html');
    }


    public $detail_items = '明细列表';
    /**
     * detail_items
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_items($id)
    {
        @ini_set('memory_limit','1024M');
        $render = app::get('console')->render();
        $items  = app::get('console')->model('vopbill_items')->getList('*', array('bill_id' => $id));

        $render->pagedata['items'] = $items;
        return $render->fetch('admin/vop/bill_items.html');
    }

    

    public $detail_oplog = "操作记录";
    /**
     * detail_oplog
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'vopbill@console'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['logs'] = $logdata;
        if ($this->vue_mode) { return []; }
        return $render->fetch('admin/vop/logs.html');
    }
}