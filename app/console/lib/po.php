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

class console_po
{
    /**
     * 采购单审核
     *
     * @return void
     * @author
     **/
    public function do_check($po_id)
    {
        $err_msg='';
        if (!$po_id) {
            return array(false, '采购单ID不能为空');
        }

        $mdl_po = app::get('purchase')->model('po');
        $aRow   = $mdl_po->dump($po_id, '*', array('po_items' => array('product_id,num')));

        if (!$aRow) {
            return array(false, '采购单不存在');
        }

        if ($aRow['check_status'] == '2') {
            return array(false, '采购单已审核');
        }

        // 生成在途
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id' => $aRow['branch_id']));
        $params                    = array();
        $params['node_type']       = 'changeArriveStore';
        $params['params']          = array(
            'obj_id' => $aRow['po_id'],
            'branch_id' => $aRow['branch_id'],
            'obj_type' => 'purchase',
            'operator' => '+'
        );
        $params['params']['items'] = $aRow['po_items'];
        $storeManageLib->processBranchStore($params, $err_msg);

        $payObj = app::get('purchase')->model('purchase_payments');
        $pay_bn = $payObj->gen_id();

        $row                = array();
        $row['payment_bn']  = $pay_bn;
        $row['po_id']       = $aRow['po_id'];
        $row['po_type']     = $aRow['po_type'];
        $row['add_time']    = time();
        $row['supplier_id'] = $aRow['supplier_id'];

        $oper = kernel::single('ome_func')->getDesktopUser();

        $row['operator'] = $oper['op_name'];

        if ($aRow['po_type'] == 'cash') {
            //现购,生成付款单
            $row['payable']       = $aRow['product_cost'] + $aRow['delivery_cost'];
            $row['deposit']       = 0;
            $row['product_cost']  = $aRow['product_cost'];
            $row['delivery_cost'] = $aRow['delivery_cost'];
            $payObj->save($row);
        } elseif ($aRow['po_type'] == 'credit' && $aRow['deposit'] > 0) {

            //赊购,预付款不为0时生成付款单
            $row['payable']       = $aRow['deposit'];
            $row['deposit']       = $aRow['deposit'];
            $row['product_cost']  = 0;
            $row['delivery_cost'] = 0;
            $payObj->save($row);
        }

        $rs = app::get('purchase')->model('po')->update(array(
            'check_status'   => 2,
            'eo_status'      => 1,
            'check_time'     => time(),
            'check_operator' => $oper['op_name'],
        ), array('po_id' => $po_id, 'check_status' => 1));
        if(is_bool($rs)) {
            return array(false, '采购单状态已改变，不能再审核');
        }
        kernel::single('console_event_trigger_purchase')->create(array('po_id' => $po_id), false);
        $log_msg = '审核完成';
        $opObj   = app::get('ome')->model('operation_log');
        $opObj->write_log('purchase_modify@purchase', $po_id, $log_msg);
        return array(true, '审核完成');
    }

    /**
     * 采购单取消（先取消WMS，成功后再处理退货退款）
     *
     * @param int $po_id 采购单ID
     * @param string $operator 操作员
     * @param string $memo 备注
     * @return array [bool, string] 是否成功和消息
     * @author
     **/
    public function do_cancel($po_id, $operator, $memo = '')
    {
        if (empty($po_id)) {
            return array(false, '采购单ID不能为空');
        }

        if (empty($operator)) {
            return array(false, '操作员不能为空');
        }

        $poObj = app::get('purchase')->model('po');
        $po    = $poObj->dump($po_id, '*', array('po_items' => array('*')));

        if (!$po) {
            return array(false, '采购单不存在');
        }

        if ($po['eo_status'] >= 3) {
            return array(false, '此采购单已完成入库，请走采购退货流程');
        }

        // 检查是否需要取消WMS
        if ($po['check_status'] == '2') {
            // 已审核，需要请求第三方取消
            if ($po['eo_status'] == '2' || $po['eo_status'] == '3' || $po['eo_status'] == '4') {
                return array(false, '单据所在状态不允许此次操作');
            }

            // 调用第三方取消
            $po_bn       = $po['po_bn'];
            $purchaseObj = kernel::single('console_event_trigger_purchase');
            $branch_id   = $po['branch_id'];
            $data        = array(
                'io_type'    => 'PURCHASE',
                'io_bn'      => $po_bn,
                'branch_id'  => $branch_id,
                'out_iso_bn' => $po['out_iso_bn'],
            );

            $result = $purchaseObj->cancel($data, true);
            
            // 如果WMS取消失败，直接返回
            if (!empty($result['rsp']) && $result['rsp'] == 'fail') {
                return array(false, $result['err_msg'] ? $result['err_msg'] : 'WMS取消失败');
            }
        }

        // WMS取消成功或无需取消WMS，继续处理退货退款逻辑
        return $this->processRefund($po_id, $po, $operator, $memo);
    }

    /**
     * 处理采购单退货退款逻辑
     *
     * @param int $po_id 采购单ID
     * @param array $po 采购单数据
     * @param string $operator 操作员
     * @param string $memo 备注
     * @return array [bool, string]
     * @author
     **/
    private function processRefund($po_id, $po, $operator, $memo)
    {
        // 开启事务
        kernel::database()->beginTransaction();
        
        try {
            $poObj      = app::get('purchase')->model('po');
            $po_itemObj = app::get('purchase')->model('po_items');
            $returnObj  = app::get('purchase')->model('returned_purchase');
            $paymentObj = app::get('purchase')->model('purchase_payments');
            $refundObj  = app::get('purchase')->model('purchase_refunds');
            $rp_itemObj = app::get('purchase')->model('returned_purchase_items');

            $return_flag = false; // 无任何操作时，不生成退款单标志
            $pay         = $paymentObj->dump(array('po_id' => $po_id), '*');
            
            if ($po['eo_status'] == '1' && $pay['statement_status'] != '2') {
                // 没有入库并且没有结算付款单
                $return_flag = true;
                if ($pay['payment_id']) {
                    $paym['payment_id']       = $pay['payment_id'];
                    $paym['statement_status'] = '3';
                    $paymentObj->save($paym);
                }
            }

            // 生成退货单
            $return['supplier_id']   = $po['supplier_id'];
            $return['operator']      = $operator;
            $return['po_type']       = $po['po_type'];
            $return['purchase_time'] = $po['purchase_time'];
            $return['returned_time'] = time();
            $return['branch_id']     = $po['branch_id'];
            $return['arrive_time']   = $po['arrive_time'];
            $return['amount']        = 0;
            $return['rp_type']       = 'po';
            $return['object_id']     = $po_id;

            $rp_id = $returnObj->createReturnPurchase($return);
            
            if (!$rp_id) {
                throw new Exception('生成退货单失败');
            }

            // 处理退货明细
            $po_items = $po['po_items'];
            $money    = 0;
            
            if ($po_items) {
                foreach ($po_items as $item) {
                    $num = $item['num'] - $item['in_num'] - $item['out_num'];
                    $num = $num < 0 ? 0 : $num;
                    
                    if (($item['status'] == '1' || $item['status'] == '2') && $num != 0) {
                        // 判断此商品是否可以取消入库
                        $row['rp_id']      = $rp_id;
                        $row['product_id'] = $item['product_id'];
                        $row['num']        = $num;
                        $row['price']      = $item['price'];
                        $money += $item['price'] * $num;
                        $row['bn']        = $item['bn'];
                        $row['name']      = $item['name'];
                        $row['spec_info'] = $item['spec_info'];

                        $rp_itemObj->save($row);
                        $row = null;

                        $r['item_id'] = $item['item_id'];
                        $r['out_num'] = $item['out_num'] + $num;
                        $r['status']  = ($r['out_num'] + $item['in_num']) >= $item['num'] ? '3' : $item['status'];

                        $po_itemObj->save($r);
                        $r = null;
                    }
                }
            }

            // 取消在途
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $po['branch_id']));
            $params                    = array();
            $params['node_type']       = 'deleteArriveStore';
            $params['params']          = array(
                'obj_id'    => $po['po_id'],
                'branch_id' => $po['branch_id'],
                'obj_type'  => 'purchase',
            );
            $err_msg = '';
            $result = $storeManageLib->processBranchStore($params, $err_msg);
            
            if (!$result) {
                $error_info = $err_msg ? ': ' . $err_msg : '';
                throw new Exception('取消在途库存失败' . $error_info);
            }
            
            // 更新退货单金额
            $data['rp_id']        = $rp_id;
            $data['amount']       = $money;
            $data['product_cost'] = $money;
            $returnObj->save($data);

            // 日志备注
            $log_msg = '';
            $return_bn = $returnObj->dump($rp_id, 'rp_bn');
            $log_msg .= '<br/>生成了一张编号为：' . $return_bn['rp_bn'] . '的退货单';

            $refund_id = null;
            if ($return_flag == false) {
                // 生成退款单
                $refund['add_time']      = time();
                $refund['po_type']       = $po['po_type'];
                $refund['delivery_cost'] = 0;
                $refund['type']          = 'po';
                $refund['rp_id']         = $rp_id;
                $refund['supplier_id']   = $po['supplier_id'];
                
                if ($po['po_type'] == 'cash') {
                    $refund['refund']       = $money;
                    $refund['product_cost'] = $money;
                } elseif ($po['po_type'] == 'credit' && $po['deposit_balance'] != 0) {
                    $refund['refund']       = $po['deposit_balance'];
                    $refund['product_cost'] = 0;
                }
                
                $refund_id = $refundObj->createRefund($refund);
                
                if (!$refund_id) {
                    throw new Exception('生成退款单失败');
                }

                $poo['amount']          = $po['amount'] - $money;
                $poo['product_cost']    = $po['product_cost'] - $money;
                $poo['deposit_balance'] = 0;
            } else {
                $poo['amount']       = 0;
                $poo['product_cost'] = 0;
            }

            // 更新采购单
            $poo['po_id'] = $po_id;
            
            if ($memo) {
                $oldmemo = unserialize($po['memo']);
                $memo_arr = array();
                if ($oldmemo) {
                    foreach ($oldmemo as $k => $v) {
                        $memo_arr[] = $v;
                    }
                }
                $memo_arr[]  = array('op_name' => $operator, 'op_time' => date('Y-m-d H:i', time()), 'op_content' => htmlspecialchars($memo));
                $poo['memo'] = serialize($memo_arr);
            }

            if ($po['po_status'] == '1') {
                $poo['po_status'] = '2'; // 入库取消
            }

            if ($po['eo_status'] == '2') {
                $poo['eo_status'] = '3'; // 已入库
            } elseif ($po['eo_status'] == '1' || $po['eo_status'] == '0') {
                $poo['eo_status'] = '4'; // 未入库
            }

            $poObj->save($poo);

            // 记录日志
            if ($refund_id) {
                $refund_bn = $refundObj->dump($refund_id, 'refund_bn');
                $log_msg  .= '<br/>生成了一张编号为：' . $refund_bn['refund_bn'] . '的退款单';
            }
            
            $log_msg2 = '对采购单编号为:' . $po['po_bn'] . '进行了入库取消<br/>';
            $opObj    = app::get('ome')->model('operation_log');
            $opObj->write_log('purchase_cancel@purchase', $po_id, $log_msg2 . $log_msg);

            // 提交事务
            kernel::database()->commit();
            
            return array(true, '入库取消已完成');
            
        } catch (Exception $e) {
            // 事务回滚
            kernel::database()->rollBack();
            
            return array(false, '入库取消失败: ' . $e->getMessage());
        }
    }
}
