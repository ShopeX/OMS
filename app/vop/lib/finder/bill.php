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

class vop_finder_bill {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = 'id,status,sync_status,discount_sync_status,detail_sync_status,bill_number,sku_count,get_count,get_detail_count,get_discount_count';
    

    public $column_action = "操作";
    public $column_action_width = 200;
    public $column_action_order = 1;
    /**
     * column_action
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_action($row)
    {
       
       
        $bill_number = $row[$this->col_prefix.'bill_number'];
        $status = $row[$this->col_prefix.'status'];
        $sync_status = $row[$this->col_prefix.'sync_status'];
        $discount_sync_status = $row[$this->col_prefix.'discount_sync_status'];
        $detail_sync_status = $row[$this->col_prefix.'detail_sync_status'];
        $id = $row['id'];
        $confirmBtn = "<a href='index.php?app=vop&ctl=admin_bill&act=confirm&p[0]={$id}'  target='_blank'>确认</a>";

        $btn = [];
        if($status == '0' && $sync_status == '2' && $discount_sync_status == '2' && $detail_sync_status=='2') {
           
            $btn[] = $confirmBtn; 
        }
        $itemBtn = <<<HTML
        <a href='index.php?app=vop&ctl=admin_bill&act=getItem&p[0]={$row['id']}&finder_id={$_GET["_finder"]["finder_id"]}'>获取明细</a>
HTML;


        if($status == '0') {
            $btn[] = $itemBtn;
       
        }
      

        return implode(' | ', $btn);
    }

    public $detail_basic = '账单总览';

    /**
     * detail_basic
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_basic($id){
        $render = app::get('vop')->render();
        $discountMdl = app::get('vop')->model('source_discount');

        $discounts = $discountMdl->db->selectrow("SELECT sum(datasign*bill_amount) as dis_total_amount, sum(final_total_amount) as dis_amount,sum(datasign*total_bill_amount*tax_rate) as rate_amount FROM sdb_vop_source_discount WHERE bill_id=".$id." AND detail_line_type like '%DISCOUNT%'");


        $discounts['dis_total_amount'] = sprintf('%.3f',$discounts['dis_total_amount']);

        $discounts['dis_amount'] = sprintf('%.2f',$discounts['dis_amount']);

        $discounts['rate_amount'] = sprintf('%.2f',$discounts['rate_amount']);
        
        $render->pagedata['discounts'] = $discounts;

        $insures = $discountMdl->db->selectrow("SELECT sum(datasign*bill_amount) as insure_total_amount,sum(final_total_amount) as insure_amount,sum(datasign*total_bill_amount*tax_rate) as rate_amount FROM sdb_vop_source_discount WHERE bill_id=".$id." AND detail_line_type like '%INSURE%'");


        $insures['insure_total_amount'] = sprintf('%.3f',$insures['insure_total_amount']);

        $insures['insure_amount'] = sprintf('%.2f',$insures['insure_amount']);

        $insures['rate_amount'] = sprintf('%.2f',$insures['rate_amount']);
        $render->pagedata['insures'] = $insures;


        $vopbillMdl = app::get('vop')->model('bill');
        $vopbills = $vopbillMdl->dump($id,'*');

        $render->pagedata['vopbills'] = $vopbills;

        $goods = $discountMdl->db->select("SELECT sum(datasign*bill_amount) as sum_bill_amount,sum(final_total_amount) as total_bill_amount,sum(datasign*payable_quantity) as total_quantity,detail_line_type,sum(datasign*total_bill_amount*tax_rate) as rate_amount FROM sdb_vop_source_billgoods WHERE bill_id=".$id." group by detail_line_type");
        $objMath    = kernel::single('eccommon_math');


        $goods = array_column($goods,null,'detail_line_type');
        foreach($goods as &$v){
            $v['sum_bill_amount'] = sprintf('%.2f',$v['sum_bill_amount']);
            $v['total_bill_amount'] = sprintf('%.3f',$v['total_bill_amount']);
            $v['rate_amount'] = sprintf('%.2f',$v['rate_amount']);
        }
        $render->pagedata['goods'] = $goods;   
        $total_qty = $objMath->number_minus(array($vopbills['cr_cust_quantity'], $vopbills['dr_cust_quantity']));

        $total_qty = $objMath->number_plus( array($total_qty, $vopbills['other_quantity']) );
        $render->pagedata['total_qty'] = $total_qty;
        $details = kernel::single('vop_bill')->getDetail($id);


        $total_amount = $objMath->number_plus(array($goods['CR_CUST']['total_bill_amount'], $goods['DR_CUST']['total_bill_amount'],$goods['OTHER']['total_bill_amount']));
       
        $total_amount = $objMath->number_plus(array($total_amount,$discounts['dis_amount'],$insures['insure_amount']));
     
        $total_amount = $objMath->number_plus(array($total_amount,$details['reship_amount']));
       
        $total_amount = $objMath->number_plus(array($total_amount,$details['refund_amount']));
       
     
        $render->pagedata['total_amount'] = $total_amount;
        

        $render->pagedata['details'] = $details;  
  
        return $render->fetch('admin/vop/bill_basic.html');
        
    }

    public $detail_amount = 'PO账单';
    /**
     * detail_amount
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_amount($id)
    {
       
        $render = app::get('vop')->render();
        $poObj = app::get('vop')->model('po');

        
        $items = $poObj->getList('*', ['bill_id'  => $id]);
        $render->pagedata['lines'] = [
            'header' => $poObj->_columns(),
            'body' => $items,
        ];
        
        return $render->fetch('finder/detail.html', 'desktop');

    }


    public $column_get_count       = '获取货款行数';
    public $column_get_count_width = '80';
    /**
     * column_get_count
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_get_count($row)
    {
        $count = $row[$this->col_prefix . 'get_count'];
        $bill_id = $row[$this->col_prefix . 'id'];
        return "<a href='index.php?app=vop&ctl=admin_bill_goods&act=index&bill_id={$bill_id}' target='_blank'>" . $count . "</a>";
    }

    public $column_get_detail_count       = '获取费用项行数';
    public $column_get_detail_count_width = '80';
    /**
     * column_get_detail_count
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_get_detail_count($row)
    {
        $count = $row[$this->col_prefix . 'get_detail_count'];
        $bill_id = $row[$this->col_prefix . 'id'];
        return "<a href='index.php?app=vop&ctl=admin_bill_detail&act=index&bill_id={$bill_id}' target='_blank'>" . $count . "</a></span>";
    }

    public $column_get_discount_count       = '获取折扣行数';
    public $column_get_discount_count_width = '80';
    /**
     * column_get_discount_count
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_get_discount_count($row)
    {
        $count = $row[$this->col_prefix . 'get_discount_count'];
        $bill_id = $row[$this->col_prefix . 'id'];
        return "<a href='index.php?app=vop&ctl=admin_bill_discount&act=index&bill_id={$bill_id}' target='_blank'>" . $count . "</a></span>";
    }
    

    public $detail_oplog = "操作记录";
    /**
     * detail_oplog
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_oplog($id){

        $render = app::get('vop')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'bill@vop'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['logs'] = $logdata;
        return $render->fetch('admin/vop/logs.html');
    }
}