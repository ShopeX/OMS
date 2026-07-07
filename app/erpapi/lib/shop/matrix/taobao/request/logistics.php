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
 * @desc
 * @author: jintao
 * @since: 2016/8/1
 */
class erpapi_shop_matrix_taobao_request_logistics extends erpapi_shop_request_logistics {

    /**
     * 回告淘宝加急发货订单在 OMS 当前节点下的物流状态。
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

        $title = sprintf('淘宝加急发货状态回告[%s:%s]', $sdf['tid'], $sdf['orderStatus']);
        return $this->__caller->call(SHOP_LOGISTICS_ORDER_REPORT, $params, array (), $title, 10, $sdf['tid']);
    }

    public function updateReturnLogistics($reshipinfo) {
        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->dump($reshipinfo['order_id'], 'order_bn');
        $confirm_result = '1';
        if ($reshipinfo['is_check'] == '9') {
            $confirm_result = '2';
        }
        $reship_bn = $reshipinfo['reship_bn'];
        #取退货单上的
        $oReturn_tmall = app::get('ome')->model('return_product_tmall');
        $return_tmall = $oReturn_tmall->dump(array('return_bn'=>$reship_bn));
        $params['refund_id']        = $reshipinfo['reship_bn'];
        $params['refund_phase '] = $return_tmall['refund_phase'];
        $params['confirm_result '] = $confirm_result;
        $params['company_code']=$reshipinfo['return_logi_name'];
        $params['sid'] = $reshipinfo['return_logi_no'];
        $params['operator']=kernel::single('desktop_user')->get_name();;
        $params['confirm_time']=date('Y-m-d H:i:s',$reshipinfo['t_end']);
        $callback = array(
            'class' => get_class($this),
            'method' => 'callback',
        );
        $title = '店铺('.$this->__channelObj->channel['name'].')回填退回物流单号物流公司'.'(订单号:'.$order['order_bn'].'退货单号:'.$reshipinfo['reship_bn'].')';;
        $rs = $this->__caller->call(SHOP_REFUND_GOOD_RETURN_CHECK,$params,$callback,$title,10,$order['order_bn']);
        return $rs;
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
