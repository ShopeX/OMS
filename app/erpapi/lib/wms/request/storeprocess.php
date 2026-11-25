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
 * @DateTime: 2022/11/28 15:10:09
 * @describe: 加工单
 * ============================
 */
class erpapi_wms_request_storeprocess extends erpapi_wms_request_abstract
{

    /**
     * storeprocess_create
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function storeprocess_create($sdf){
        $params = $this->_format_storeprocess_create_params($sdf);
        if (!$params) {
            return $this->error('参数为空,终止同步,主档有问题');
        }
        
        $callback = array(
            'class'  => get_class($this),
            'method' => 'storeprocess_create_callback',
            'params' => ['mp_id'=>$sdf['main']['id']]
        );

        $title = '推送加工单';
        return $this->__caller->call(WMS_STOREPROCESS_CREATE, $params, $callback, $title, 10, $sdf['main']['mp_bn']);
    } 

    /**
     * storeprocess_create_callback
     * @param mixed $response response
     * @param mixed $callback_params 参数
     * @return mixed 返回值
     */
    public function storeprocess_create_callback($response, $callback_params)
    {
        $rsp     = $response['rsp'];
        $err_msg = $response['err_msg'];
        $data    = @json_decode($response['data'], true);
        $msg_id  = $response['msg_id'];
        $res     = $response['res'];
        $mp_id = $callback_params['mp_id'];
        $mpObj = app::get('console')->model('material_package');
        $upData = [];
        if($rsp == 'succ') {
            $upData['sync_status'] = '2';
            $upData['sync_msg'] = '';
        } else {
            $upData['sync_status'] = '3';
            $upData['sync_msg'] = '失败：'.$err_msg;
        }
        if ($data['processOrderId']) {
            $upData['out_mp_bn'] = $data['processOrderId'];
        }
        if($upData) {
            $mpObj->update($upData, ['id'=>$mp_id]);
        }
        return $this->callback($response,$callback_params);
    }

    protected function _format_storeprocess_create_params($sdf)
    {
        $planQty = 0;
        $materialitems = [];$productitems = [];
        $iidBn = [];
        foreach ($sdf['items'] as $v) {
            $productitems[] = [
                'itemCode' => $v['bm_bn'],
                'quantity' => $v['number']
            ];
            $planQty += $v['number'];
            $iidBn[$v['id']] = $v['bm_bn'];
        }
        
        foreach ($sdf['detail'] as $v) {
            $materialitems[] = [
                'itemCode' => $v['bm_bn'],
                'quantity' => $v['number'],
                'remark' => $iidBn[$v['mpi_id']]
            ];
        }
        
        $params = array(
            'processOrderCode' => $sdf['main']['mp_bn'],
            'warehouseCode' => $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['main']['branch_bn']),
            'orderType' => 'CNJG',
            'orderCreateTime' => $sdf['main']['at_time'],
            'planTime' => date('Y-m-d H:i:s'),
            'serviceType' => $sdf['main']['service_type'],
            'planQty' => $planQty,
            'remark' => $sdf['main']['memo'],
            'materialitems' => json_encode($materialitems),
            'productitems' => json_encode($productitems),
        );
        
        // 货主编码
        if (isset($sdf['owner_code'])) {
            $params['ownerCode'] = $sdf['owner_code'];
        }elseif(isset($sdf['main']['owner_code'])) {
            $params['ownerCode'] = $sdf['main']['owner_code'];
        }
        
        return $params;   
    }
    
    /**
     * storeprocess_cancel
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function storeprocess_cancel($sdf){
        $mp_bn = $sdf['mp_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '出库单取消';

        $params = $this->_format_storeprocess_cancel_params($sdf);

        return $this->__caller->call(WMS_ORDER_CANCEL, $params, null, $title, 10, $mp_bn);

    }

    protected function _format_storeprocess_cancel_params($sdf)
    {
        $params = array(
            'out_order_code' => $sdf['mp_bn'],
            'warehouse_code' => $this->get_warehouse_code($this->__channelObj->wms['channel_id'],$sdf['branch_bn']),
            'order_type'     => 'CNJG',
        );

        return $params;
    }

}