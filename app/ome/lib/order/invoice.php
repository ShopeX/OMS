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

    public function insertInvoice($sdf, $memo = '订单保存发票信息', &$error_msg=null) {
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
            
            // 来源标记（只有当source_marking='message_push'时,才执行）
            if($sdf['source_marking'] == 'message_push'){
                $sdf['is_auto'] = '1'; // 是否自动开票(1:自动开票, 2:手工开票)
            }
            
            // 创建订单发票信息
            $rs = app::get('ome')->model('order_invoice')->insert($insertData);
            if($rs) {
                // 注册service，判断是否执行发票业务
                foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
                {
                    // 客户自助申请开发票,例如：淘宝店小蜜
                    //@todo：1、客户下单时未申请开发票，等发货完成后之，客户申请开发票；2、客户通过淘宝店小密申请开发票；
                    if(method_exists($object, 'directCreateInvoice')){
                        // analysis
                        $analysisResult = $object->directCreateInvoice($sdf);
                        if($analysisResult['data']['is_direct_invoice']){
                            // 已经创建发票，直接返回false
                            $error_msg = ($analysisResult['error_msg'] ? $analysisResult['error_msg'] : $analysisResult['msg']);
                            return false;
                        }elseif($analysisResult['rsp'] == 'fail'){
                            $error_msg = ($analysisResult['error_msg'] ? $analysisResult['error_msg'] : $analysisResult['msg']);
                            return false;
                        }
                    }
                    
                    // 是否允许创建预开发票信息
                    if(method_exists($object, 'check_allowed_invoice')){
                        // analysis
                        $analysisResult = $object->check_allowed_invoice($sdf, 'create');
                        if($analysisResult['rsp'] == 'fail'){
                            $memo = '创建订单发票信息' .($memo ? ':'.$memo : '');
                            $error_msg = ($analysisResult['error_msg'] ? $analysisResult['error_msg'] : $analysisResult['msg']);
                            
                            // logs
                            if($memo){
                                app::get('ome')->model('operation_log')->write_log('order_modify@ome', $sdf['order_id'], $memo);
                            }
                            
                            // 不允许创建发票，直接返回false
                            return false;
                        }
                    }
                }
                
                // 创建订单发票
                list($rs, $rsData) = kernel::single('invoice_order_front')->insertOrUpdateByOrder($sdf);
                app::get('ome')->model('operation_log')->write_log('order_modify@ome', $sdf['order_id'], $memo.':'.$rsData['msg']);
            }
            
            return $rs;
        }

        return true;
    }

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
        
        // 是否归档发票
        if(isset($sdf['old_invoice']['is_archive_invoice']) && $sdf['old_invoice']['is_archive_invoice']){
            app::get('archive')->model('order_invoice')->update($upData, array('id'=>$sdf['old_invoice']['id']));
        }else{
            app::get('ome')->model('order_invoice')->update($upData, array('id'=>$sdf['old_invoice']['id']));
        }
        
        // logs
        app::get('ome')->model('operation_log')->write_log('order_modify@ome', $sdf['order_id'], $memo);
        
        // 注册service，判断是否执行发票业务
        foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
        {
            // 客户自助申请开发票,例如：淘宝店小蜜
            //@todo：1、客户下单时未申请开发票，等发货完成后之，客户申请开发票；2、客户通过淘宝店小密申请开发票；
            if(method_exists($object, 'directUpdateInvoice')){
                // analysis
                $analysisResult = $object->directUpdateInvoice($sdf);
                if($analysisResult['data']['is_direct_invoice']){
                    // 已经更新发票，直接返回false
                    return false;
                }
            }
            
            // 分析是否允许更新预开发票信息
            if(method_exists($object, 'check_allowed_invoice')){
                // analysis
                $analysisResult = $object->check_allowed_invoice($sdf, 'update');
                if($analysisResult['rsp'] == 'fail'){
                    // 不允许更新发票，直接返回false
                    return false;
                }
            }
        }
        
        return kernel::single('invoice_order_front')->insertOrUpdateByOrder($sdf);
    }
}