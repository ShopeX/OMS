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
 * 请求同步翱象发货单Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2023.03.16
 */
class dchain_autotask_syncdelivery
{
    /**
     * 请求同步翱象发货单
     * @param $params
     * @param $error_msg
     * @return bool
     */
    public function process($params, &$error_msg='')
    {
        $aoDeliveryObj = app::get('dchain')->model('aoxiang_delivery');
        $deliveryObj = app::get('ome')->model('delivery');

        $axDeliveryLib = kernel::single('dchain_delivery');

        //params
        $shop_id = $params['shop_id'];
        $delivery_id = $params['delivery_id'];
        $delivery_bn = $params['delivery_bn'];
        $process_status = $params['process_status']; //accept仓库接单,confirm确认出库

        //check
        if(empty($delivery_id)){
            return true;
        }

        //delivery
        $deliveryList = $deliveryObj->getList('*', array('delivery_id'=>$delivery_id));
        $delivery = $deliveryList[0];
        if(empty($delivery)){
            //$error_msg = '发货单信息不存在!';
            return true;
        }

        //推送状态
        $delivery['process_status'] = $process_status;

        //创建翱象发货单
        if($process_status != 'confirm'){
            $aoDeliveryInfo = $aoDeliveryObj->dump(array('delivery_id'=>$delivery_id), 'did');
            if(empty($aoDeliveryInfo)){
                $sdf = array(
                    'shop_id' => $delivery['shop_id'],
                    'shop_type' => $delivery['shop_type'],
                    'delivery_id' => $delivery['delivery_id'],
                    'delivery_bn' => $delivery['delivery_bn'],
                    'create_time' => time(),
                    'last_modified' => time(),
                );
                $aoDeliveryObj->insert($sdf);
            }
        }

        //sync
        $sync_error_msg = '';
        $result = $axDeliveryLib->syncDelivery($delivery, 'auto', $sync_error_msg);

        return true;
    }
}