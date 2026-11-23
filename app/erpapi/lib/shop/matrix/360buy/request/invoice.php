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
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/25
 * @Describe: 发票 相关请求接口类
 */
class erpapi_shop_matrix_360buy_request_invoice extends erpapi_shop_request_invoice
{
    /**
     * 发票上传组织数据
     * 
     * @return void
     * @author
     */

    protected function getUploadParams($sdf)
    {
        $params = parent::getUploadParams($sdf);
        
        // unset
        unset($params['invoice_code'], $params['blue_invoice_code']);
        
        $params['invoice_no'] = $params['invoice_no'] ?: 0;
        $params['blue_invoice_no'] = $params['blue_invoice_no'] ?: 0;
        
        $plat = array(
            '360buy' => '10001',
            'taobao' => '10002',
            'suning' => '10003',
            'amazon' => '10004',
            'other'  => '30001',
        );
        
        $order_type = $plat[$this->__channelObj->channel['node_type']];
        $params['order_type'] = $order_type ? $order_type : $plat['other'];
        
        // 版本号
        $params['invoice_dto_version'] = 'v2';
        
        return $params;
    }
    
    /**
     * 获取发票信息
     * @Author: xueding
     * @Vsersion: 2023/3/8 上午11:50
     * @param $order_sdf
     * @return mixed
     */
    public function getApplyInfo($order_sdf)
    {
        $data = [
            'orderId' => $order_sdf['order_bn'],
        ];
        
        $params = [
            'jos_method' => 'jingdong.pop.invoice.self.amount',
            'data'       => json_encode($data),
            'to_node_id' => $this->__node_id,
        ];
        
        $title  = '获取发票信息';
        $result = $this->__caller->call(JD_COMMON_TOP_SEND, $params, array(), $title, 10, $order_sdf['order_bn']);
        if ($result['data']) {
            $tmpData        = json_decode($result['data'], true);
            $result['data'] = $tmpData['jingdong_pop_invoice_self_amount_responce']['queryamountforown_result']['data'];
        }
        return $result;
    }
}
