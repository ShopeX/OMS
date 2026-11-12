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
 * 门店响应发货单处理类
 *
 * @author xiayuanjun@shopex.cn
 * @version 0.1
 *
 */
class erpapi_store_response_delivery extends erpapi_store_response_abstract
{

    /**
     * status_update
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function status_update($params){
        // 参数校验
        $this->__apilog['title']       = $this->__channelObj->store['name'] . '发货单' . $params['delivery_bn'];
        $this->__apilog['original_bn'] = $params['delivery_bn'];

        $batch_logi_no = preg_replace('/\s/', '', trim($params['logi_no']));
        $batch_logi_no = array_filter(explode(';', $batch_logi_no));
        $logi_no = array_pop($batch_logi_no);

        $data = array(
            'delivery_bn'  => trim($params['delivery_bn']), 
            'logi_no'      => $logi_no,
            'logi_id'      => $params['logistics'],
            'weight'       => $params['weight'],
            'branch_bn'    => $params['warehouse'],
            'volume'       => $params['volume'],
            'memo'         => $params['remark'],
            'operate_time' => $params['operate_time'] ? $params['operate_time'] : date('Y-m-d H:i:s'),
            'wms_id'       => $this->__channelObj->wms['channel_id'],
            'bill_logi_no' => $batch_logi_no,
        );

        if ($data['logi_id']) {
            $wmsFunc = kernel::single('wmsmgr_func');
            
            // shop_type
            $shop_type = $wmsFunc->getShopTypeByDeliveryBn($data['delivery_bn']);
            
            // 根据wms_id、wms物流公司编号获取系统物流公司编号
            $mapped = $wmsFunc->getlogiCode($this->__channelObj->wms['channel_id'], $data['logi_id'], $shop_type);
            
            // 物流公司编码
            $data['logi_id'] = $mapped ? $mapped : $data['logi_id'];
        }

        if ($params['out_delivery_bn']) $data['out_delivery_bn'] = $params['out_delivery_bn'];

        switch ($params['status']) {
            case 'CLOSE':
            case 'FAILED':
            case 'refuse':
                $data['status'] = 'cancel';break;
            case 'ACCEPT':
                $data['status'] = 'accept';break;
                break;
            case 'PRINT':
                $data['status'] = 'print';break;
                break;
            case 'PICK':
                $data['status'] = 'pick';break;
                break;
            case 'CHECK':
                $data['status'] = 'check';break;
                break;
            case 'PACKAGE':
                $data['status'] = 'package';break;
                break;
            case 'DELIVERY':
                $data['status'] = 'delivery';break;
                break;
            case 'UPDATE':
                $data['status'] = 'update';break;
                break;
            default:
                $data['status'] = strtolower($params['status']);
                break;
        }

        $delivery_items = array();
        $items = $params['item'] ? json_decode($params['item'],true) : array();
        if ($items) {
            foreach ($items as $key => $val) {
                if (!$val['product_bn']) continue;
                $sn_list = $val['sn_list'] ? json_decode($val['sn_list'],true) : '';
                $delivery_items[] = array(
                    'bn' => $val['product_bn'],
                    'num' => $val['num'],
                    'sn_list'=>$sn_list,
                );
            }
        }

        $data['items'] = $delivery_items;
        return $data;
    }
}
