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
 * @DateTime: 2022/6/6 10:17:55
 * @describe: 控制器
 * ============================
 */
class o2o_ctl_admin_delivery_processed extends desktop_controller {
    private $base_filter = ['status|notin'=>['0']];

    /**
     * _views
     * @return mixed 返回值
     */

    public function _views()
    {
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>array(),'optional'=>false),
            1 => array('label'=>app::get('base')->_('已拒绝'),'filter'=>array( 'status'=>'1'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('已完成'),'filter'=>array( 'status'=>'3'),'optional'=>false),
            3 => array('label'=>app::get('base')->_('待取件'),'filter'=>array( 'status'=>'3', 'is_received'=>1),'optional'=>false),
            4 => array('label'=>app::get('base')->_('已取件'),'filter'=>array( 'status'=>'3', 'is_received'=>2),'optional'=>false),
        );
        
        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = array_merge($this->base_filter, $v['filter']);
            $sub_menu[$k]['addon'] = '_FILTER_POINT_';
            $sub_menu[$k]['href'] = 'index.php?app=o2o&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&view=' . $k;
        }
        
        return $sub_menu;
    }

    /**
     * index
     * @return mixed 返回值
     */
    public function index() {
        $base_filter = $this->base_filter;
        $is_super    = kernel::single('desktop_user')->is_super();
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids))
            {
                header("Content-type: text/html; charset=utf-8");
                echo '操作员没有管辖的仓库';
                exit;
            }
            $base_filter['branch_id']    = $branch_ids;
        }
        $actions = array();
        if($_GET['view'] == '2') {
            $actions['batchsync'] = array('label' => '发货状态回写至OMS',
                            'submit' => $this->url.'&act=batch_sync',
                            'confirm' => '你确定要对勾选的发货单状态回写吗？',
                            'target' => 'refresh');
        }

        if($_GET['view'] == '2'){
            $actions['batchsync'] = array('label' => '取件',
                            'submit' => $this->url.'&act=batch_received',
                            'confirm' => '你确定要对勾选的发货单取件吗？',
                            'target' => 'refresh');
        }
        $params = array(
                'title'=>'已处理单据',
                'base_filter'=>$base_filter,
                'finder_aliasname'=>'o2o_processed',
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false,
                'actions'=>$actions,
                'orderBy'=>'delivery_id desc',
        );
        
        $this->finder('o2o_mdl_delivery', $params);
    }

    function batch_sync()
    {
        $ids = array_map('intval', (array)$_POST['delivery_id']);
        $db = kernel::database();
        if (!empty($ids)) {
            $sql = "SELECT w.* FROM sdb_wap_delivery as w left join sdb_ome_delivery as o on w.outer_delivery_bn=o.delivery_bn WHERE w.status='3' AND o.process='false' AND w.delivery_id in(".implode(',', $ids).")";
            $deliverys = $db->select($sql);
            foreach ($deliverys as $delivery){
                $bill = app::get('wap')->model('delivery_bill')->db_dump(['delivery_id'=>$delivery['delivery_id'], 'type'=>'1'], 'logi_no');
                $delivery['logi_no'] = $bill['logi_no'];
                $delivery['order_number']  = 1;
                $delivery['retry_sync']  = 1;
                
                //执行发货
                $dlyProcessLib  = kernel::single('wap_delivery_process');
                $res            = $dlyProcessLib->consign($delivery);
            }

        }
        $this->splash('success', $this->url.'&view=2', '命令已经被成功发送！！');
    }


    /**
     * batch_received
     * @return mixed 返回值
     */
    public function batch_received()
    {
        $ids = array_map('intval', (array)$_POST['delivery_id']);
        $db = kernel::database();
        if (!empty($ids)) {
            $sql = "SELECT w.* FROM sdb_wap_delivery as w left join sdb_ome_delivery as o on w.outer_delivery_bn=o.delivery_bn WHERE w.status='3'  AND w.delivery_id in(".implode(',', $ids).")";
            $deliverys = $db->select($sql);
            foreach ($deliverys as $delivery){
               $params = [

                'delivery_id'   =>  $delivery['delivery_id'],
                'delivery_bn'   =>  $delivery['delivery_bn'],
               ];
                //执行发货
                $dlyProcessLib  = kernel::single('wap_delivery_process');
                $dlyProcessLib->sign($params);
            }

        }
        $this->splash('success', $this->url.'&view=2', '命令已经被成功发送！！');
    }
}