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
 * 重试推送wms失败的拒绝退货单
 *
 *
 * @author
 * @version 0.1
 */

class ome_autotask_timer_retryreshipcancel extends ome_autotask_timer_common
{
    private $ttl = 60*6; // kv过期时间

    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);

        base_kvstore::instance('ome/retryreshipcancel')->fetch('process_status', $process_status);
        if (!$process_status) {
            $process_status = 'finish';
        }
        if ($process_status == 'running') {
            $error_msg = 'is running';
            return true; // 时间未到
        }

        // $db = kernel::database();

        // ==================================================================
        // ==================================================================

        base_kvstore::instance('ome/retryreshipcancel')->store('process_status', 'running', $this->ttl);

        $db = kernel::database();
        $operateLogMdl = app::get('ome')->model('operation_log');
        $reshipMdl = app::get('ome')->model('reship');
        $returnProductMdl = app::get('ome')->model('return_product');

        $sql = "SELECT r.reship_id, reship_bn, is_check, r.order_id, branch_id, reship_bn, rp.return_id FROM sdb_ome_reship AS r LEFT JOIN sdb_ome_return_product AS rp ON r.return_id = rp.return_id WHERE rp.status = '5' AND r.status<>'5' AND rp.last_modified>unix_timestamp('2025-06-25 00:00:00') ORDER BY rp.last_modified ASC LIMIT 30";

        $list = $db->select($sql);
        if (!$list) {
            $error_msg = 'no info to work';
            return $this->_finish();
        }

        $reshipIds = array_column($list, 'reship_id');

        $retryError = [ 
            // '前端拒绝',
            '请求WMS取消失败，返回:数据异常,请联系IT',
            '请求WMS取消失败，返回:EXEC QM_SP_IF_DL_ORDER_CANCEL',
            '请求WMS取消失败，返回:请求超时',
        ];

        $logFilter = [
            'obj_type'      =>  'reship@ome',
            'obj_id'        =>  $reshipIds,
            'filter_sql'    =>  ' memo REGEXP "'.implode('|',$retryError).'"',
        ];
        $logList = $operateLogMdl->getList('*', $logFilter, 0, -1, 'log_id ASC');
        if (!$logList) {
            $error_msg = 'no info to work';
            return $this->_finish();
        }
        $logList = array_column($logList, null, 'obj_id'); // 根据obj_id分组,obj_id重复的只保留最新的一条
        $logReshipIds = array_column($logList, 'obj_id');

        foreach ($list as $lv) {
            $up = [
                'last_modified' => time(),
            ];
            $returnProductMdl->update($up, ['return_id'=>$lv['return_id']]);

            if (!in_array($lv['reship_id'], $logReshipIds)) {
                continue;
            }
            if (strpos($logList[$lv['reship_id']]['memo'], '自动重试请求WMS') === false) {

                $msg = '';
                $reship = [
                    'is_check'  =>  $lv['is_check'],
                    'branch_id' =>  $lv['branch_id'],
                    'reship_id' =>  $lv['reship_id'],
                    'reship_bn' =>  $lv['reship_bn'],
                    'order_id'  =>  $lv['order_id'],
                ];
                $rs = console_reship::notice($reship);
                if ($rs['rsp'] == 'succ'){
                    $updateSdf = array(
                        'is_check' => '5',
                        't_end' => time(),
                    );
                    $reshipMdl->update($updateSdf, array('reship_id'=>$reship['reship_id']));
                    kernel::single('console_reship')->releaseChangeFreeze($reship['reship_id']);
                    // 退货单取消后的service扩展点
                    foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
                        if(method_exists($object, 'cancel_reship_after')) {
                            $object->cancel_reship_after($reship['reship_id']);
                        }
                    }
                    $msg = "取消成功";
                } elseif ($rs['rsp'] == 'fail'){
                    $msg = "取消失败，返回:".$rs['msg'];
                }

                $operateLogMdl->write_log('reship@ome',$reship['reship_id'],'前端拒绝 自动重试请求WMS'.$msg);
            }
        }

        return $this->_finish();
    }


    private function _finish($status = true, $process_status = 'finish')
    {
        base_kvstore::instance('ome/retryreshipcancel')->store('process_status', $process_status, $this->ttl);
        return $status;
    }

}
