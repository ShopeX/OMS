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
 * 定时任务
 *
 * @author chenping@shopex.cn
 * @version 1.0 2017/7/3 11:00:30
 */
class erpapi_misc_task
{
    /**
     * 每分钟执行
     * 
     * @return void
     * @author
     * */

    public function minute()
    {
        $retryError = array(
            'timeout',
            'qimen inernal error',
            'timed out',
            'e00090', //请求超时
            'HTTP Error 502',
            'HTTP Error 403',
            '再试',
            'connect is error',
            '接单发货商品ID解析异常,没有后端商品时前端itemId解析错误',
            '查询后端商品失败',
            '超时',
            '重试',
            '\"retry\":true',
            'urlopen error',
            '拆单失败',
            '物流订单子交易ID匹配不上',
            'QUERY_REFUND_ERROR',
            'CD01#该物流公司揽收或派送范围不支持',
            '系统错误,获取物流订单数据失败',
            'Remote service error',
            '不能找到可以发货的订单',
            '通知交易异常',
            '当前操作状态为[等待出库]',
            'logistics_id参数类型',
            '如需修改物流单号',
            '系统内部错误',
        );

        $notRetryError = array(
            '请修改后重试'
        );
        // 查询近3天内的失败
        $objType = array('deliveryBack');
        $filter  = array(
            'last_modify|sthan' => time() - 300,
            'last_modify|than'  => strtotime('-5 days'),
            'obj_type'          => $objType,
            'fail_times|lthan'  => '15',
            'status'            => 'fail',
            'filter_sql'        => ' err_msg REGEXP "'.implode('|',$retryError).'" and not (err_msg REGEXP "'.implode('|',$notRetryError).'")',
        );

        $apiFailModel = app::get('erpapi')->model('api_fail');
        $apiFailModel->defaultOrder = 'last_modify DESC,fail_times ASC';
        $rows = $apiFailModel->getList('*', $filter, 0, 50);
        foreach ($rows as $key => $value) {
            $apiFailModel->retry($value);
        }

        // 订单退款回传平台
        $filter = array(
            'last_modify|sthan' => time() - 300,
            'last_modify|than'  => strtotime('-5 days'),
            'obj_type'          => ['bookingrefund_back'],
            'fail_times|lthan'  => '15',
            'status'            => 'fail',
        );
        $rows = $apiFailModel->getList('*', $filter, 0, 30);
        foreach ($rows as $key => $value) {
            $apiFailModel->retry($value);
        }


        // 仓储单据轮训查
        $retryType = array(
            'search_inpurchase',
            'search_delivery',
            'search_outpurchase_return',
            'search_inallcoate',
            'search_outallcoate',
            'search_indefective',
            'search_outdefective',
            'search_inother',
            'search_outother',
            'search_indirect',
            'search_outdirect',
        );
        $filter = array(
            'last_modify|sthan' => time()-1800,
            'obj_type'          => $retryType
        );
        $rows = $apiFailModel->getList('*',$filter,0,100);
        foreach($rows as $row) {
            $apiFailModel->retry($row);
        }
    
        // //重试推送请求WMS失败的发货单
        // $deliveryLib = kernel::single('console_delivery');
        // $result = $deliveryLib->auto_retry_wms_delivery();
    }

        /**
     * hour
     * @return mixed 返回值
     */
    public function hour()
    {
        $apiFailModel = app::get('erpapi')->model('api_fail');

        // 物流回写重试
        $filter = array (
            'last_modify|sthan' => time()-300,
            'last_modify|than'  => strtotime('-5 days'),
            'obj_type'          => array ('deliveryBack'),
            'err_msg|has'       => 'e00053',
        );
        $rows = $apiFailModel->getList('*',$filter,0,100,'last_modify DESC,fail_times ASC');
        foreach ($rows as $key => $value) {
            $apiFailModel->retry($value);
        }


        //[重试]发货单处理发货完成流程失败
        //@todo报错文字：订单状态更新失败!、仓库货品冻结释放失败;
        $apiFailObj = app::get('erpapi')->model('api_fail');
        $receiveLib = kernel::single('ome_event_receive_delivery');

        $sql = "SELECT * FROM sdb_erpapi_api_fail WHERE obj_type='deliveryship' AND create_time>" . strtotime('-1 days');
        $sql .= " ORDER BY id ASC LIMIT 0,500";
        $dataList = $apiFailObj->db->select($sql);
        if ($dataList) {
            foreach ($dataList as $key => $val) {
                $delivery_bn = $val['obj_bn'];

                //重试失败次数
                if ($val['fail_times'] > 10) {
                    continue;
                }

                $params = $val['params'] ? json_decode($val['params'], true) : array();
                if (empty($params)) {
                    continue;
                }

                //running
                $apiFailObj->update(array('status' => 'running'), array('id' => $val['id']));

                //处理发货逻辑
                $params['status'] = 'delivery';
                $result           = $receiveLib->update($params);
                if ($result['rsp'] == 'succ') {
                    $apiFailObj->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=" . $val['id']);
                } else {
                    $apiFailObj->update(array('status' => 'fail', 'fail_times' => $val['fail_times'] + 1), array('id' => $val['id']));
                }

                //@todo：一定要延迟1秒，防止同货号并发释放冻结和更新订单发货状态失败
//                sleep(1);
            }
        }
        
        // [每小时执行一次]重试推送请求WMS失败的发货单
        $deliveryLib = kernel::single('console_delivery');
        $deliveryLib->auto_retry_wms_delivery();
    }

    /**
     * day
     * @return mixed 返回值
     */
    public function day()
    {
        // 清除一个月之前的失败
        $apiFailModel = app::get('erpapi')->model('api_fail');
        $filter = array(
            'create_time|sthan' => strtotime('-2 month'),
        );
        $apiFailModel->delete($filter);
    }
}
