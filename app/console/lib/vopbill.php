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
 * sunjing@shopex.cn
 */
class console_vopbill {

    public function getDetail($bill_id){

        $detailMdl = app::get('console')->model('vopbill_detail');
        $sql = "SELECT sum(if(amount=0,targetamount,amount))  as detail_amount FROM `sdb_console_vopbill_detail` WHERE bill_id=".$bill_id;

        $details = $detailMdl->db->selectrow($sql);

        return $details;
    }

   

    public function getBills($bill_id){
        $amountMdl = app::get('console')->model('vopbill_amount');
        $amounts = $amountMdl->db->select("SELECT detail_line_type,sum(qty) as qty,sum(amount) as amount,sum(total_amount) as total_amount FROM sdb_console_vopbill_amount WHERE bill_id=".$bill_id." group by detail_line_type");

        $amounts = array_column($amounts, null,'detail_line_type');
        return $amounts;

    }


    

    public function getBillsAmount($bill_id){
        $amountMdl = app::get('console')->model('vopbill_amount');
        $amounts = $amountMdl->db->selectrow("SELECT sum(amount),sum(discount_amount) as sum_discount_amount,sum(adjust_amount) as sum_adjust_amount,sum(total_amount) as sum_total_amount FROM sdb_console_vopbill_amount WHERE bill_id=".$bill_id."");
        $amounts['sum_total_amount'] = sprintf('%.2f',$amounts['sum_total_amount']);
        return $amounts;
    }


    

    
}