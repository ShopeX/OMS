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
 * @DateTime: 2022/11/21 15:16:56
 * @describe: 控制器
 * ============================
 */
class console_ctl_admin_difference extends desktop_controller {

    public function index($operate_type='') {

        $actions = array();
        $_GET['view'] = intval($_GET['view']);
        $base_filter    = array();
        if($operate_type) {
            //$base_filter['operate_type'] = $operate_type;
        }
        if(in_array(intval($_GET['view']),array('1'))){

            $actions[] =array(
                'label'=>'批量审核',
                'submit' => 'index.php?app=console&ctl=admin_difference&act=batchAudit',
                'target' => 'dialog::{width:600,height:250,title:\'批量审核\'}'
            ); 
        }
        

        $params = array(
                'title'=>'盘点差异单',
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>true,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false,
                'actions'=>$actions,
                'orderBy'=>'id desc',
                'base_filter' => $base_filter,
        );
        
        $this->finder('console_mdl_difference', $params);
    }

    function _views(){
        $differenceMdl = app::get('console')->model('difference');
        $operate_type = $_GET['p'][0];
        $base_filter = [];
        if($operate_type) {
            //$base_filter['operate_type'] = $operate_type;
        }
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>array(),'optional'=>false),
            1 => array('label'=>app::get('base')->_('待财务确认'),'filter'=>array('status'=>array('2')),'optional'=>false),
            2 => array('label'=>app::get('base')->_('已确认'),'filter'=>array('status'=>array('1')),'optional'=>false),
            3 => array('label'=>app::get('base')->_('待审核'),'filter'=>array('status'=>array('4')),'optional'=>false),
            4 => array('label'=>app::get('base')->_('取消'),'filter'=>array('status'=>array('3')),'optional'=>false),
           
        );
        foreach ($sub_menu as $k => $v) {
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }else{
                $v['filter'] = $base_filter;
            }

            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] =  $differenceMdl->count($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=console&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&p[0]=' . $operate_type . '&view=' . $k;
        }

        return $sub_menu;
    }



    public function singleConfirm($id) {
        list($rs, $rsData) = kernel::single('console_difference')->confirm($id);
        $differenceMdl = app::get('console')->model('difference');

        $diff = $differenceMdl->db_dump($id);
        if(!$rs) {
            $this->splash('error', $this->url.'&p[0]='.$diff['operate_type'], '确认失败:'.$rsData['msg']);
        }
        $this->splash('success', $this->url.'&p[0]='.$diff['operate_type'], '确认成功');
    }

    public function cancel($id) {
        $id = (int)$id;
        $differenceMdl = app::get('console')->model('difference');
        $diff = $differenceMdl->db_dump($id);
        if(empty($diff)) {
            $this->splash('success', $this->url.'&p[0]='.$diff['operate_type'], '操作完成');
        }
        $differenceItemsMdl = app::get('console')->model('difference_items_freeze');

        $freezeItems = $differenceItemsMdl->getList('*', ['diff_id'=>$id]);

        
        if(!$freezeItems) {
            $rs = $differenceMdl->update(['status'=>'3'], ['id'=>$id, 'status'=>['2']]);
            if(!is_bool($rs)) {
                app::get('ome')->model('operation_log')->write_log('difference@console',$id,"操作取消");
            }
            $this->splash('success', $this->url.'&p[0]='.$diff['operate_type'], '操作完成');
        }
        kernel::database()->beginTransaction();

        $rs = $differenceMdl->update(['status'=>'3'], ['id'=>$id, 'status'=>['2']]);
        if(!is_bool($rs)) {
            //释放冻结
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $diff['branch_id']));
            $params = $diff;
            $params['items'] = $freezeItems;
            $params = ['params'=>$params];
            $params['node_type'] = 'cancelDifference';
            $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
            if (!$processResult) {
                kernel::database()->rollBack();

                $this->splash('error', $this->url, $diff['diff_bn'].'取消失败：'.$err_msg);
            }
            app::get('ome')->model('operation_log')->write_log('difference@console',$id,"操作取消");
        }
        kernel::database()->commit();
        $this->splash('success', $this->url.'&p[0]='.$diff['operate_type'], '操作完成');
    }

    public function retry($id) {
        list($rs, $rsData) = kernel::single('console_difference')->retryInAndOut($id);
        app::get('ome')->model('operation_log')->write_log('difference@console',$id,"重试：".$rsData['msg']);
        $this->splash(($rs ? 'success' : 'error'), $this->url, $rsData['msg']);
    }

    public function doEdit($id) {
        $main = app::get('console')->model('difference')->db_dump(['id'=>$id]);
        $items = app::get('console')->model('difference_items')->getList('*', ['diff_id'=>$id]);
        $branch = app::get('ome')->model('branch')->db_dump(['branch_id'=>$main['branch_id'], 'check_permission'=>'false'], 'name');
        $main['branch_name'] = $branch['name'];
        $this->pagedata['main'] = $main;
        $this->pagedata['items'] = $items;
        $this->singlepage('admin/difference/main.html');
    }

    public function save() {
        $id = (int) $_POST['id'];
        $number = $_POST['number'];
        $this->begin($this->url);

        app::get('ome')->model('operation_log')->write_log('difference@console',$id,"操作编辑");
        $itemObj = app::get('console')->model('difference_items');
        $items = $itemObj->getList('*', ['diff_id'=>$id]);
        $retryFreeze = false;
        foreach ($items as $v) {
            $number[$v['id']] = (int) $number[$v['id']];
            if($number[$v['id']] == $v['number']) {
                continue;
            }
            if($v['diff_stores'] > 0) {
                if($number[$v['id']] < 0) {
                    $this->end(false, $v['material_bn'].':不能为负数');
                }
                if($number[$v['id']] > $v['diff_stores']) {
                    $this->end(false, $v['material_bn'].':不能超过'.$v['diff_stores']);
                }
                $itemObj->update(['number'=>$number[$v['id']]], ['id'=>$v['id']]);
            }
            if($v['diff_stores'] < 0) {
                $retryFreeze = true;
                if($number[$v['id']] > 0) {
                    $this->end(false, $v['material_bn'].':不能为正数');
                }
                if($number[$v['id']] < $v['diff_stores']) {
                    $this->end(false, $v['material_bn'].':不能小于'.$v['diff_stores']);
                }
                $itemObj->update(['number'=>$number[$v['id']]], ['id'=>$v['id']]);
            }
        }
        if($retryFreeze) {
            list($rs, $rsData) = kernel::single('console_difference')->retryFreeze($id);
            if(!$rs) {

                $this->end(false, $rsData['msg']);
            }
        }
        $this->end(true, '操作完成');
    }

    public function add() {
        $this->pagedata['operate_type'] = $_GET['operate_type'];
        $this->pagedata['operate_type_name'] = app::get('console')->model('difference')->schema['columns']['operate_type']['type'][$_GET['operate_type']];
        $this->singlepage('admin/difference/add.html');
    }

    public function doAdd() {
        $this->begin($this->url);
        
        $data = [];
        $data['operate_type'] = trim($_POST['operate_type']);
        $data['branch_id'] = trim($_POST['branch_id']);
        $data['negative_branch_id'] = [trim($_POST['branch_id'])];
        $data['memo'] = trim($_POST['memo']);
        $number = $_POST['number'];
        $arrBmId = [];
        foreach ($number as $bmId => $num) {
            if($num == 0) {
                $this->end(false, '数量不能为0');
            }
            $arrBmId[] = $bmId;
        }
        $bcRows = app::get('material')->model('basic_material')->getList('bm_id,material_bn', ['bm_id'=>$arrBmId]);
        $data['items'] = [];
        foreach ($bcRows as $v) {
            $data['items'][] = [
                'bm_id' => $v['bm_id'],
                'material_bn' => $v['material_bn'],
                'wms_stores' => 0,
                'oms_stores' => 0,
                'diff_stores' => $number[$v['bm_id']],
            ];
        }
        list($rs, $rsData) = kernel::single('console_difference')->insertBill($data);
        if(!$rs) {

            $this->end(false, $rsData['msg']);
        }
        $this->end(true, '操作成功');
    }

    public function batchAudit(){
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        $this->pagedata['GroupList']   = json_encode($_POST['id']);
        $this->pagedata['request_url'] = 'index.php?app=console&ctl=admin_difference&act=allAudit';
        parent::dialog_batch('console_mdl_difference');
    }

    public function allAudit()
    {
        $msg        = '审核成功';

        parse_str($_POST['primary_id'], $postdata);

        $ids = $postdata['f']['id'];
     
        $diffMdl   = app::get('console')->model('difference');

        $retArr = array(
            'itotal'    => count($ids),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );

        foreach ($ids as $id) {

            list($res, $rsData) = kernel::single('console_difference')->confirm($id);
            
            if ($res) {
                $retArr['isucc']++;
            }else{
                $retArr['ifail']++;
                $retArr['err_msg'][] = 'ERROR:'.$rsData['msg'];
            }
        }
        
        echo json_encode($retArr),'ok.';exit;
    }
}