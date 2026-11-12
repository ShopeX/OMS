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
 * @author sunjing@shopex.cn
 * @describe 平台自发订单
 */
class ome_ctl_admin_order_platform extends desktop_controller {
    private $base_filter = array(
        'order_type' => 'platform',
        'is_fail' => 'false'
    );

    /**
     * _views
     * @return mixed 返回值
     */

    public function _views(){
        static $sub_menu;
        if($sub_menu) {
            return $sub_menu;
        }
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('未发货'),'filter'=>array( 'ship_status'=>'0', 'status|noequal'=>'dead'),'optional'=>false),
            1 => array('label'=>app::get('base')->_('已完成'),'filter'=>array( 'ship_status'=>'1'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('全部'),'filter'=>array(),'optional'=>false),
        );
       
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $this->base_filter['org_id'] = $organization_permissions;
        }

        $mdl_order = app::get('ome')->model('orders');
        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = array_merge($this->base_filter, $v['filter']);
            $sub_menu[$k]['addon'] = '_FILTER_POINT_';
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&view=' . $k;
        }
        return $sub_menu;
    }

    /**
     * index
     * @return mixed 返回值
     */
    public function index(){
        $_GET['view'] = (int) $_GET['view'];

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $this->base_filter['org_id'] = $organization_permissions;
        }

        $params = array(
            'title'=>'平台自发订单',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>true,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab' => true,
            'base_filter' => $this->base_filter,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ]
        );
       
        if($_GET['view'] == 0) {
            $params['actions'] = array(
                array('label' => '批量发货', 'submit' => 'index.php?app=ome&ctl=admin_order_platform&act=delivery', 'target' => 'dialog::{width:600,height:250,title:\'平台自发订单发货\'}'),
            );
        }
        
        $params['actions']['import'] = [
            'label'  => '自发订单导入',
            'href'   => sprintf('%s&act=displayImportV2&p[0]=%s', $this->url, 'platform'),
            'target' => 'dialog::{width:760,height:300,title:\'导入自发订单任务\'}',
        ];
        
        $this->finder('ome_mdl_orders',$params);
    }

    /**
     * delivery
     * @return mixed 返回值
     */
    public function delivery() {
        $model = app::get('ome')->model('orders');
        $pageData = array(
            'billName' => '平台自发订单',
            'request_url' => 'index.php?app=ome&ctl=admin_order_platform&act=doDelivery',
            'maxProcessNum' => 1,
            'close' => true
        );
        $this->selectToPageRequest($model, $pageData);
    }

    /**
     * doDelivery
     * @return mixed 返回值
     */
    public function doDelivery() {
        $orderIds = explode(';', $_POST['ajaxParams']);
        $retArr = array(
            'total' => count($orderIds),
            'succ' => 0,
            'fail' => 0,
            'fail_msg' => array()
        );
        foreach($orderIds as $orderId) {
            $rs = kernel::single('ome_order_platform')->deliveryConsign($orderId);

            if($rs['rsp'] == 'succ') {
                $retArr['succ'] ++;
            } else {
                $retArr['fail'] ++;
                $retArr['fail_msg'][] = array(
                    'obj_bn' => $rs['order_bn'],
                    'msg' => $rs['msg']
                );
            }
        }
        echo json_encode($retArr);
    }
    
    public function exportTemplateV2()
    {
        $fileName = "平台自发订单导入模板-" . date('Ymd') . ".xlsx";
        $title = app::get('ome')->model('orders')->exportTemplateV2('ome_order_importV2');
        
        // 获取下载模板事例
        $demoRows = kernel::single('ome_order_importV2')->getTemplateDemoRows($title);
        kernel::single('omecsv_phpoffice')->export($fileName, array_merge([$title], $demoRows));
    }
}
