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
 * @author ykm 2016-01-04
 * @describe 特殊订单状态回写
 */

class brush_ctl_admin_statusback extends desktop_controller {

    public function _views() {
        $base_filter = array(
            'status' => 'finish',
            'process_status' => 'splited',
            'createway' => 'matrix',
            'ship_status' => '1',
            'order_type' => 'brush'
        );
        $sub_menu[0] = array('label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false);
        $sub_menu[2] = array('label' => app::get('base')->_('回写未发起'),'filter' => array_merge($base_filter,array('createway' => 'matrix','sync' => 'none')),'optional' => false);
        $sub_menu[3] = array('label' => app::get('base')->_('发货中'), 'filter' => array_merge($base_filter, array('createway'=>'matrix','sync' => 'run')), 'optional' => false);
        $sub_menu[4] = array('label' => app::get('base')->_('发货失败'), 'filter' => array_merge($base_filter, array('createway'=>'matrix','sync' => 'fail','sync_fail_type'=>array('none','unbind'))), 'optional' => false);
        $sub_menu[8] = array('label' => app::get('base')->_('发货成功'), 'filter' => array_merge($base_filter, array('createway' => 'matrix','sync' => 'succ')), 'optional' => false);
        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = app::get('ome')->model('orders')->count($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=brush&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&flt=' . $_GET['flt'] . '&view=' . $k;
        }
        return $sub_menu;
    }

    public function index() {
        $this->app->app_id = 'ome';
        $_GET['view'] = intval($_GET['view']);
        $op_id = kernel::single('desktop_user')->get_id();
        switch ($_GET['view']) {
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
                $action = array(
                    array('label' => '批量发货', 'submit' => 'index.php?app=brush&ctl=admin_statusback&act=batch_sync', 'confirm' => '你确定要对勾选的订单进行发货操作吗？', 'target' => 'refresh'),
                    array('label' => '已回写成功', 'submit' => 'index.php?app=brush&ctl=admin_statusback&act=batch_sync_succ', 'confirm' => "这些订单系统认为都是在前台(淘宝、京东等)已经发货，请确认这些订单前端已经发货！！！\n\n警告：本操作不会再同步发货状态，并不可恢复，请谨慎使用！！！", 'target' => 'refresh'),
                );
                break;
            default:
                break;
        }

        $params = array(
            'title' => '特殊订单状态回写',
            'actions' => $action,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => false,
            'use_view_tab' => true,
            'finder_aliasname' => 'order_consign_fast'.$op_id,
            'finder_cols' => '_func_0,column_confirm,order_bn,column_sync_status,column_print_status,logi_id,logi_no,column_deff_time,member_id, ship_name,ship_area,total_amount',
        );
        $this->finder('ome_mdl_orders', $params);
    }

    public function batch_sync() {
        $orderIds = $this->_getSelectedId();
        kernel::single('brush_delivery_back')->backRequest($orderIds);
        $this->splash('success', null, '命令已经被成功发送！');
    }

    function batch_sync_succ() {
        $ids = $this->_getSelectedId();
        if (!empty($ids)) {
            $orderObj = app::get('ome')->model('orders');
            $data = array('sync'=>'succ','sync_fail_type'=>'none');
            $filter = array('order_id'=>$ids,'createway' => 'matrix');
            $orderObj->update($data,$filter);
            //记录日志
            $logObj = app::get('ome')->model('operation_log');
            $logObj->batch_write_log('order_modify@ome','手动设为同步成功',time(),$filter);
        }
        $this->splash('success', null, '命令已经被成功发送！');
    }

    private function _getSelectedId(){
        $objModel = app::get('ome')->model('orders');
        if($_POST['isSelectedAll'] == '_ALL_') {
            unset($_POST['isSelectedAll']);
            $view = intval($_POST['view']);
            $subMenu = $this->_views();
            $baseFilter = $subMenu[$view]['filter'];
            $param = array_merge($baseFilter, $_POST);
            $objModel->defaultOrder = '';
            $objModel->filter_use_like = true;
            $selData = $objModel->getList($objModel->idColumn, $param, 0, -1);
            $arrObjId = array();
            foreach($selData as $val) {
                $arrObjId[] = $val[$objModel->idColumn];
            }
        } else {
            $arrObjId = $_POST[$objModel->idColumn];
        }
        return $arrObjId;
    }
}