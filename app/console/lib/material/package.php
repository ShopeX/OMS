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
 * 加工单处理类
 *
 * @author chenping@shopex.cn
 * @version Thu May  4 11:11:33 2023
 */
class console_material_package
{
    /**
     * 加工单取消
     *
     * @return array [true, 'msg']
     * @author chenping@shopex.cn
     * @version Thu May  4 11:12:53 2023
     **/
    public function cancel($id, $isLocal)
    {
        $id = (int) $id;

        $mpObj = app::get('console')->model('material_package');

        // 如果已经审核需要通知WMS并释放冻结
        $main = $mpObj->db_dump(['id' => $id]);

        if (!$main) {
            return [false, '加工单不存在'];
        }

        if ($main['status'] == '2') {

            if($main['service_type'] == '2') {
                $itemsDetail    = app::get('console')->model('material_package_items')->getList('*', ['mp_id' => $id]);
            } else {
                $itemsDetail    = app::get('console')->model('material_package_items_detail')->getList('*', ['mp_id' => $id]);
            }
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $main['branch_id']));

            // 通知WMS撤单
            if(!$isLocal) {
                list($rs, $rsData) = kernel::single('console_event_trigger_material_package')->cancel($main);
                if(!$rs) {
                    return [false, 'WMS取消失败：'.$rsData['msg']];
                }
            }
            // 释放冻结
            $params              = [];
            $params['main']      = $main;
            $params['items']     = $itemsDetail;
            $params              = ['params' => $params];
            $params['node_type'] = 'cancelMaterialPackage';
            $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
            if (!$processResult) {
                return [false, $err_msg];
            }
        }

        $rs = $mpObj->update(['status' => '3'], ['id' => $id, 'status' => ['1','2']]);
        if (is_bool($rs)) {
            return [false, '状态异常加工单取消失败'];
        }
        
        app::get('ome')->model('operation_log')->write_log('material_package@console', $id, "操作取消");

        return [true, '取消成功'];
    }
}
