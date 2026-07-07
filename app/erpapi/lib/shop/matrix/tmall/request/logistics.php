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
 * @author ykm 2017/2/16
 * @describe 物流相关 请求接口类
 */

class erpapi_shop_matrix_tmall_request_logistics extends erpapi_shop_request_logistics {

    /**
     * 回告天猫加急发货订单在 OMS 当前节点下的物流状态。
     *
     * @param array $sdf
     * @param bool $queue
     * @return array
     */
    public function order_report($sdf, $queue = false)
    {
        $args = func_get_args();
        array_pop($args);
        $_in_mq = $this->__caller->caller_into_mq('logistics_order_report', 'shop', $this->__channelObj->channel['shop_id'], $args, $queue);
        if ($_in_mq) {
            return $this->succ('成功放入队列');
        }

        $params = $this->buildUrgentOrderReportRequestParams($sdf);

        $title = sprintf('天猫加急发货状态回告[%s:%s]', $sdf['tid'], $sdf['orderStatus']);
        return $this->__caller->call(SHOP_LOGISTICS_ORDER_REPORT, $params, array (), $title, 10, $sdf['tid']);
    }

    public function getCorpServiceCode($sdf) {
        $params = array(
            'cp_code' => $sdf['cp_code']
        );
        $title = '获取物流商服务类型';
        $result = $this->__caller->call(STORE_CN_WAYBILL_II_SEARCH,$params,array(),$title, 10, $params['cp_code']);

        return $result;
    }

    public function timerule($sdf)
    {
        $params = [
            'api' => 'taobao.open.seller.biz.logistic.time.rule',
            'data' => json_encode([
                'last_pay_time' => date('H:i', $sdf['cutoff_time']),
                'last_delivery_time' => date('H:i', $sdf['latest_delivery_time']),
            ]),
        ];

        $title = '商家自定义发货时效';
        $result = $this->__caller->call(TAOBAO_COMMON_TOP_SEND,$params,array(),$title, 10, $this->__channelObj->channel['shop_bn']);
        return $result;
    }
}
