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
 * @DateTime: 2022/11/23 14:49:10
 * @describe: 控制器
 * ============================
 */
class console_ctl_admin_material_package extends desktop_controller
{

    /**
     * _views
     * @return mixed 返回值
     */

    public function _views()
    {
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>array(),'optional'=>false),
            1 => array('label'=>app::get('base')->_('待处理'),'filter'=>array( 'status'=>'1'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('已确定'),'filter'=>array( 'status'=>'2'),'optional'=>false),
            3 => array('label'=>app::get('base')->_('处理中'),'filter'=>array( 'status'=>'5'),'optional'=>false),
        );
        
        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['addon'] = '_FILTER_POINT_';
            $sub_menu[$k]['href'] = 'index.php?app='.$_GET['app'].'&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&view=' . $k;
        }
        
        return $sub_menu;
    }

    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $actions   = array();
        $actions[] = array(
            'label'  => '新增',
            'href'   => $this->url . '&act=add',
            'target' => '_blank',
        );
        $actions[] = array(
            'label'  => '导入',
            'href'   => $this->url.'&act=execlImportDailog&p[0]=material_package',
            'target' => 'dialog::{width:500,height:300,title:\'导入\'}',
        );
        /*$actions[] = array(
        'label'  => '导出模板',
        'href'   => $this->url.'&act=exportTemplate',
        'target' => '_blank',
        );*/
        $params = array(
            'title'                 => '加工单',
            'use_buildin_set_tag'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_importxls' => false, //只能新建一个商品
            'use_buildin_recycle'   => false,
            'actions'               => $actions,
            'orderBy'               => 'id desc',
        );

        $this->finder('console_mdl_material_package', $params);
    }

    /**
     * exportTemplate
     * @return mixed 返回值
     */
    public function exportTemplate()
    {
        $row = app::get('console')->model('material_package')->getTemplateColumn();
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel(null, '加工单导入模板', 'xls', $row);
    }

    /**
     * 添加
     * @return mixed 返回值
     */
    public function add()
    {
        $this->singlepage('admin/material/package/add.html');
    }

    /**
     * edit
     * @return mixed 返回值
     */
    public function edit()
    {
        $id      = (int) $_GET['id'];
        $main    = app::get('console')->model('material_package')->db_dump(['id' => $id]);
        $items   = app::get('console')->model('material_package_items')->getList('id, bm_id, bm_bn as material_bn, bm_name as material_name, number', ['mp_id' => $id]);
        $mpItems = array_column($items, null, 'id');
        $items   = array_column($items, null, 'bm_id');
        $detail  = app::get('console')->model('material_package_items_detail')->getList('id, mpi_id, bm_id, bm_bn as material_bn, bm_name as material_name, number', ['mp_id' => $id]);
        foreach ($detail as $v) {
            $v['pbm_id']                    = $mpItems[$v['mpi_id']]['bm_id'];
            $v['material_num']              = bcdiv($v['number'], $mpItems[$v['mpi_id']]['number']);
            $items[$v['pbm_id']]['items'][] = $v;
        }
        $this->pagedata['main']  = $main;
        $this->pagedata['items'] = json_encode($items);
        $this->singlepage('admin/material/package/add.html');
    }

    /**
     * 获取ProductsByBn
     * @return mixed 返回结果
     */
    public function getProductsByBn() {
        $pro_bn= $_GET['material_bn'];
        $pro_name= $_GET['material_name'];
        $pro_barcode= $_GET['barcode'];
        if($pro_bn){
            $filter = array(
                'material_bn'=>$pro_bn
            );
        }
        if($pro_name){
            $filter = array(
                'material_name'=>$pro_name
            );
        }
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        if($pro_barcode) {
            $bm_ids    = $basicMaterialBarcode->getBmidListByBarcode($pro_barcode);
            $filter = array('bm_id'=>$bm_ids);
        }
        if(empty($filter)) {
            echo "window.autocompleter_json=".json_encode([]);exit;
        }
        $filter['type'] = '4';
        $bmRows       = app::get('material')->model('basic_material')->getList('*', $filter);
        $bmRows       = array_column($bmRows, null, 'bm_id');
        $bmci         = app::get('material')->model('basic_material_combination_items')->getList('*', ['pbm_id' => array_keys($bmRows)]);
        foreach ($bmci as $v) {
            $v['number']                     = $v['material_num'];
            $bmRows[$v['pbm_id']]['barcode'] = $basicMaterialBarcode->getBarcodeById($v['pbm_id']);
            $bmRows[$v['pbm_id']]['items'][] = $v;
        }
        echo "window.autocompleter_json=".json_encode(array_values($bmRows));exit;
    }

    /**
     * 获取Products
     * @return mixed 返回结果
     */
    public function getProducts()
    {
        $arrProductId = $_POST['bm_id'];
        $bmRows       = app::get('material')->model('basic_material')->getList('*', ['bm_id' => $arrProductId, 'type' => '4']);
        $bmRows       = array_column($bmRows, null, 'bm_id');
        $bmci         = app::get('material')->model('basic_material_combination_items')->getList('*', ['pbm_id' => $arrProductId]);
        foreach ($bmci as $v) {
            $v['number']                     = $v['material_num'];
            $bmRows[$v['pbm_id']]['items'][] = $v;
        }
        echo json_encode($bmRows);
    }

    /**
     * 保存
     * @return mixed 返回操作结果
     */
    public function save()
    {
        if (empty($_POST['mp_name'])) {
            $this->splash('error', $this->url, '缺少加工单名称');
        }
        if (empty($_POST['branch_id'])) {
            $this->splash('error', $this->url, '缺少仓库');
        }
        if (empty($_POST['number'])) {
            $this->splash('error', $this->url, '缺少明细');
        }
        $data = [
            'mp_name'       => trim($_POST['mp_name']),
            'branch_id'     => $_POST['branch_id'],
            'service_type'  => $_POST['service_type'],
            'movement_code' => trim($_POST['movement_code']),
            'memo'          => trim($_POST['memo']),
        ];
        $items  = [];
        $number = $_POST['number'];
        if (count($number) > 1) {
            $this->splash('error', $this->url, '只能有一个礼盒商品');
        }
        foreach (app::get('material')->model('basic_material')->getList('*', ['bm_id' => array_keys($number)]) as $v) {
            $items[$v['bm_id']] = [
                'bm_id'   => $v['bm_id'],
                'bm_bn'   => $v['material_bn'],
                'bm_name' => $v['material_name'],
                'number'  => $number[$v['bm_id']],
            ];
        }
        $mpObj = app::get('console')->model('material_package');
        if ($_POST['id']) {
            $data['id']        = (int) $_POST['id'];
            list($rs, $rsData) = $mpObj->updateDataItems($data, $items, '手工');
        } else {
            list($rs, $rsData) = $mpObj->insertDataItems($data, $items, '手工');
        }
        if ($rs) {
            $this->splash('success', $this->url, '操作成功');
        }
        $this->splash('error', $this->url, '操作失败：' . $rsData['msg']);
    }

    /**
     * singleConfirm
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function singleConfirm($id)
    {
        $this->begin($this->url);

        $mpObj = app::get('console')->model('material_package');
        $rs    = $mpObj->update(['status' => '2'], ['id' => $id, 'status' => '1']);
        if (is_bool($rs)) {
            $this->end(false, '更新失败');
        }
        $main           = $mpObj->db_dump(['id' => $id], '*');
        if($main['service_type'] == '2') {
            $itemsDetail    = app::get('console')->model('material_package_items')->getList('*', ['mp_id' => $id]);
        } else {
            $itemsDetail    = app::get('console')->model('material_package_items_detail')->getList('*', ['mp_id' => $id]);
        }
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id' => $main['branch_id']));

        // 校验物料同步状态
        // 同时获取主商品和子商品进行校验
        $mainItems = app::get('console')->model('material_package_items')->getList('*', ['mp_id' => $id]);
        $detailItems = app::get('console')->model('material_package_items_detail')->getList('*', ['mp_id' => $id]);
        
        // 调用封装的校验方法
        list($validateRs, $validateMsg) = $mpObj->_validateMaterialSyncStatus($main['branch_id'], $mainItems, $detailItems);
        if (!$validateRs) {
            $this->end(false, $validateMsg);
        }

        $params              = array();
        $params['main']      = $main;
        $params['items']     = $itemsDetail;
        $params              = ['params' => $params];
        $params['node_type'] = 'confirmMaterialPackage';
        $processResult       = $storeManageLib->processBranchStore($params, $err_msg);

        if (!$processResult) {

            $this->end(false, $err_msg);
        }
        app::get('ome')->model('operation_log')->write_log('material_package@console', $id, "操作确认");
        kernel::single('console_event_trigger_material_package')->create($id);
        $this->end(true, '操作成功');
    }

    /**
     * cancel
     * @param mixed $id ID
     * @param mixed $isLocal isLocal
     * @return mixed 返回值
     */
    public function cancel($id, $isLocal = false)
    {
        $this->begin($this->url);

        list($rs, $msg) = kernel::single('console_material_package')->cancel($id, $isLocal);

        $this->end($rs, $msg);
    }

    /**
     * sync
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function sync($id)
    {
        kernel::single('console_event_trigger_material_package')->create($id);
        $this->splash('success', $this->url, '操作成功');
    }
}
