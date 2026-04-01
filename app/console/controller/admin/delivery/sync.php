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
class console_ctl_admin_delivery_sync extends desktop_controller {

    var $name = "通知仓库取消列表";
    var $workground = "console_center";

    /**
     *
     * 发货单列表
     */
    function index(){
        $actions = array();
        $base_filter = array(
            'parent_id' => 0,
            'status' => array('ready','progress'),
            'type' => 'normal',
            'disabled' => 'false',
            'pause' => 'false',

        );
        $base_filter = array_merge($base_filter,$_GET);
        $base_filter['sync_filter'] =  ['in'=>[console_delivery_bool_sync::__CANCEL_FAIL]];

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }

        $actions[] =  array('label' => '重试取消发货单', 'submit' => 'index.php?app=console&ctl=admin_delivery_sync&act=batch_sync', 'confirm' => '你确定要对勾选的发货单进行发货取消吗？', 'target' => 'refresh');
        $actions[] = array('label' => 'OMS强制取消', 'submit' => 'index.php?app=console&ctl=admin_delivery_sync&act=batch_cancel', 'confirm' => "本操作只对非自有仓储,这些发货单认为都是在仓储已经取消发货，请确认这些发货单WMS已经取消！！！\n\n警告：本操作将会直接取消oms发货单并释放库存，并不可恢复，请谨慎使用！！！", 'target' => 'refresh');

        $params = array(
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_import'=>false,
            'use_buildin_export'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab'=>false,
            'actions' => $actions,
            'title'=>'通知仓库取消列表',
            'base_filter' => $base_filter,
        );

        $this->finder('console_mdl_delivery', $params);
    }

    /**
     * 批量同步.
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function batch_sync()
    {
        $oOperation_log = app::get('ome')->model('operation_log');
        
        $ids = $_REQUEST['delivery_id'];
        if (!empty($ids)) {
            
            $delivery_list = app::get('console')->model('delivery')->getList('delivery_id,branch_id,delivery_bn', ['delivery_id'=>$ids, 'sync_filter'=>['in'=>[console_delivery_bool_sync::__CANCEL_FAIL]]]);
            
            foreach ((array) $delivery_list as $delivery)
            {
                $delivery_id = $delivery['delivery_id'];
                
                $res = ome_delivery_notice::cancel($delivery, true);
                if ($res['rsp'] == 'fail') {
                    $oOperation_log->write_log('delivery_back@ome',$delivery_id,'发货单取消通知仓库:失败,原因'.$res['msg']);
                }else{
                    $consoleDlyLib = kernel::single('console_delivery');
                    $consoleDlyLib->update_sync_status($delivery_id, 'cancel_succ');
                    $oOperation_log->write_log('delivery_back@ome',$delivery_id,'发货单取消通知仓库:成功');
                    $data = array(
                        'status'=>'cancel',
                        'memo'=>'发货单取消通知仓库成功',
                        'delivery_bn'=>$delivery['delivery_bn'],
                    );
                    kernel::single('ome_event_receive_delivery')->update($data);
                }
            }
        }

        $this->splash('success', $this->url, '命令已经被成功发送！！');
    }


    /**
     * 批量取消.
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function batch_cancel()
    {
        
        $oOperation_log = app::get('ome')->model('operation_log');
        
        $ids = $_REQUEST['delivery_id'];
        if (!empty($ids)) {
            $sync = kernel::single('console_delivery_bool_sync')->getBoolSync([
                'in'=>[console_delivery_bool_sync::__CANCEL_FAIL]
            ]);
            $delivery_list = app::get('console')->model('delivery')->getList('delivery_id,branch_id,delivery_bn', [
                'delivery_id'=>$ids, 
                'filter_sql'=>'sync in ('.(implode(',', $sync)).') AND sync > 9',
                'status' => ['ready','progress']
            ]);
            
            foreach ((array) $delivery_list as $delivery)
            {
                $delivery_id = $delivery['delivery_id'];
                
                // 开启事务
                $transaction = kernel::database()->beginTransaction();
                try {
                    $data = array(
                        'status'=>'cancel',
                        'memo'=>'发货单请求第三方仓储取消失败,强制取消!',
                        'delivery_bn'=>$delivery['delivery_bn'],
                    );
                    $rs = kernel::single('ome_event_receive_delivery')->update($data);
                    if ($rs['rsp'] != 'succ') {
                        throw new Exception('发货单状态更新失败');
                    }
                    
                    // 完成订单明细处理：如果拆分数量为0且支付状态为全额退款，则更新为已删除并更新主表金额
                    list($result, $msg) = kernel::single('console_delivery_cancel')->afterForce($delivery_id);
                    if (!$result) {
                        throw new Exception('订单明细处理失败：' . $msg['msg']);
                    }
                    $oOperation_log->write_log('delivery_back@ome', $delivery_id, '手工强制取消发货单成功');
                    
                    // 提交事务
                    kernel::database()->commit($transaction);
                } catch (Exception $e) {
                    // 回滚事务
                    kernel::database()->rollBack();
                    $oOperation_log->write_log('delivery_back@ome', $delivery_id, '发货单强制取消失败：' . $e->getMessage());
                }
                
            }
        }
        $this->splash('success', $this->url, '命令已经被成功发送！！');
    }

}
