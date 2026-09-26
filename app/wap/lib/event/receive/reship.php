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
 * 退换货事件处理类
 * 20170516
 * wangjianjun@shopex.cn
 */

class wap_event_receive_reship extends wap_event_response{

    /**
     * 退换货单创建事件
     * @param array $data
     */
    public function create($data){
        //创建
        $res = kernel::single('wap_receipt_reship')->create($data, $msg);
        if($res){
            $this->sendReshipCreateNotify($data);
            return $this->send_succ();
        }else{
            return $this->send_error($msg);
        }
    }

    public function cancel($data){
       
        $res = kernel::single('wap_receipt_reship')->cancel($data, $msg);
        if($res){
            return $this->send_succ();
        }else{
            return $this->send_error($msg);
        }
    }

    /**
     * O2O 退货单创建成功预警
     */
    protected function sendReshipCreateNotify(array $data)
    {
        if (empty($data['reship_bn'])) {
            return;
        }
        try {
            $returnRow = app::get('wap')->model('return')->dump(
                array('original_reship_bn' => $data['reship_bn'], 'status' => 1),
                'return_id,return_bn,original_reship_bn,order_id,branch_id,return_type'
            );
            if (empty($returnRow)) {
                return;
            }

            $orderBn = '';
            if (!empty($returnRow['order_id'])) {
                $orderRow = app::get('ome')->model('orders')->dump(
                    array('order_id' => $returnRow['order_id']),
                    'order_bn'
                );
                $orderBn = $orderRow['order_bn'] ?? '';
            }

            $itemRows = app::get('wap')->model('return_items')->getList(
                'num',
                array('return_id' => $returnRow['return_id'])
            );
            $skuNum = count($itemRows);
            $itemNum = 0;
            foreach ((array) $itemRows as $itemRow) {
                $itemNum += intval($itemRow['num']);
            }

            $returnTypeMap = array(
                'return' => '退货',
                'change' => '换货',
            );

            $notifyParams = array(
                'return_bn'         => $returnRow['return_bn'],
                'reship_bn'         => $returnRow['original_reship_bn'],
                'order_bn'          => $orderBn,
                'branch_id'         => $returnRow['branch_id'],
                'return_type'       => $returnRow['return_type'],
                'return_type_name'  => $returnTypeMap[$returnRow['return_type']] ?? $returnRow['return_type'],
                'sku_num'           => $skuNum,
                'item_num'          => $itemNum,
            );

            if (app::get('o2o')->is_installed() && !empty($returnRow['branch_id'])) {
                $storeRow = app::get('o2o')->model('store')->dump(
                    array('branch_id' => $returnRow['branch_id'], 'status' => 1),
                    'store_id,store_bn,name'
                );
                if ($storeRow) {
                    $notifyParams['store_id']   = $storeRow['store_id'];
                    $notifyParams['store_bn']   = $storeRow['store_bn'];
                    $notifyParams['store_name'] = $storeRow['name'];
                }
            }

            kernel::single('monitor_event_notify')->addNotify('wap_store_reship_create', $notifyParams, true);
        } catch (Exception $e) {
            // 预警失败不影响退换货单创建
        }
    }
}

?>
