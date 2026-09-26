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

class erpapi_hqepay_response_logistics extends erpapi_hqepay_response_abstract {
    /**
     * 物流状态 -> ERP logi_status
     * Matrix 推送（含顺丰）的 State 已是系统值 1~6；快递鸟另有 301 等扩展码
     */
    static public $state_status = array(
        '1'=>'1',#已揽收
        '2'=>'2',#在途中
        '3'=>'3',#已签收
        '4'=>'4',#退件/问题件
        '5'=>'5',#待取件
        '6'=>'6',#待派件
        '301'=>'3',#正常签收
        '302'=>'3',#派件异常后最终签收
        '304'=>'3',#代收签收
        '311'=>'3',#快递柜或驿站签收
    );

    protected function _formatParams($params) {
        return $params;
    }

    public function push($params){
        $sdf = $this->_formatParams($params);
        $this->__apilog['title'] = '接受物流信息（物流单号：' . $sdf['LogisticCode']. '）';
        $this->__apilog['original_bn'] = $sdf['LogisticCode'];

        $rawState = (string) ($sdf['State'] ?? '');
        $logi_status = isset(self::$state_status[$rawState]) ? self::$state_status[$rawState] : '';
        if ($logi_status === '') {
            $this->__apilog['result'] = array(
                'msg' => '不支持的物流状态码：' . $rawState,
                'msg_code' => 'LOGI_STATUS_UNKNOWN',
            );
            return false;
        }

        $data = array(
            'logi_status' => $logi_status,
            'logi_no' => $sdf['LogisticCode'],
        );

        $Traces = array();
        if (!empty($params['Traces'])) {
            $Traces = is_array($params['Traces']) ? $params['Traces'] : json_decode($params['Traces'], true);
            if (!is_array($Traces)) {
                $Traces = array();
            }
        }

        // 已揽收/已签收/问题件：从轨迹取时间；Action 为空时用 State（顺丰 Matrix 常为空）
        if (in_array($data['logi_status'], array('1', '3', '4'), true) && $Traces) {
            foreach ($Traces as $trace) {
                $action = isset($trace['Action']) ? (string) $trace['Action'] : '';
                if ($action !== '' && isset(self::$state_status[$action])) {
                    $action = self::$state_status[$action];
                } elseif ($action !== '') {
                    $action = substr($action, 0, 1);
                } else {
                    $action = $data['logi_status'];
                }

                if (empty($trace['AcceptTime'])) {
                    continue;
                }
                if ($action == '3') {
                    $data['sign_time'] = strtotime($trace['AcceptTime']);
                }
                if ($action == '1' && !isset($data['embrace_time'])) {
                    $data['embrace_time'] = strtotime($trace['AcceptTime']);
                }
                if ($action == '4') {
                    $data['problem_time'] = strtotime($trace['AcceptTime']);
                }
            }
        }

        $filter = array(
            'process' => 'TRUE',
            'pause' => 'FALSE',
            'logi_no' => $data['logi_no'],
            'status' => 'succ',
            'logi_status|noequal' => $data['logi_status']
        );
        $deliveryData = app::get('ome')->model('delivery')->getList('delivery_id,is_cod', $filter, 0, 1);
        if ($deliveryData) {
            $data['delivery_id'] = $deliveryData[0]['delivery_id'];
            $data['is_cod'] = $deliveryData[0]['is_cod'];
        }
        return $data;
    }
}
