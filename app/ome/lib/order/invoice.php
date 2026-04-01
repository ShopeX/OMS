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
 * @author ykm 2017/7/6
 * @describe 发票相关 保存
 */
class ome_order_invoice {

    /**
     * insertInvoice
     * @param mixed $sdf sdf
     * @param mixed $memo memo
     * @return mixed 返回值
     */

    public function insertInvoice($sdf, $memo = '订单保存发票信息') {
        if($sdf['tax_no'] || $sdf['tax_title']) {
            $insertData = array(
                'order_id'             => $sdf['order_id'],
                'tax_title'            => $sdf['tax_title'],
                'tax_no'               => $sdf['tax_no'],
                'register_no'          => $sdf['register_no'],
                'invoice_kind'         => $sdf['invoice_kind'] == '1' ? '1' : ($sdf['invoice_kind'] == '3' ? '2' : "0") ,
                'title_type'           => $sdf['title_type'],
                'create_time'          => time(),
            );

            if ($sdf['invoice_amount']) {
                $insertData['invoice_amount'] = $sdf['invoice_amount'];
            }

            $rs = app::get('ome')->model('order_invoice')->insert($insertData);
            if($rs) {
                list($rs, $rsData) = kernel::single('invoice_order_front')->insertOrUpdateByOrder($sdf);
                app::get('ome')->model('operation_log')->write_log('order_modify@ome', $sdf['order_id'], $memo.':'.$rsData['msg']);
            }
            return $rs;
        }

        return true;
    }

    /**
     * 更新Invoice
     * @param mixed $sdf sdf
     * @param mixed $memo memo
     * @return mixed 返回值
     */
    public function updateInvoice($sdf, $memo = '订单更新发票信息'){
        $upData = array(
            'order_id'=>$sdf['old_invoice']['order_id'],
            'register_no' => $sdf['register_no'],
            'invoice_kind' => $sdf['invoice_kind'] == '1' ? '1' : ($sdf['invoice_kind'] == '3' ? '2' : "0") ,
        );
        if ($sdf['invoice_amount']) {
            $upData['invoice_amount'] = $sdf['invoice_amount'];
        }
        // 更新发票抬头
        if (isset($sdf['tax_title'])) {
            $upData['tax_title'] = $sdf['tax_title'];
        }
        app::get('ome')->model('order_invoice')->update($upData, array('id'=>$sdf['old_invoice']['id']));
        app::get('ome')->model('operation_log')->write_log('order_modify@ome', $sdf['order_id'], $memo);
        return kernel::single('invoice_order_front')->insertOrUpdateByOrder($sdf);
    }


    

}