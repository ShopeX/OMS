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

class console_ctl_admin_iostockorder extends desktop_controller
{
    public $name       = "出入库计划";
    public $workground = "console_purchasecenter";

    /**
     * index
     * @return mixed 返回值
     */
    public function index() {}
    /**
     * 
     * 其他入库列表
     */
    public function other_iostock()
    {
        $io = $_GET['io'];
        if ($io) {
            $title = '入库单';
        } else {
            $title = '出库单';
        }

        $params = array(
            'actions'                => array(
                array(
                    'label'  => '新建',
                    'href'   => 'index.php?app=console&ctl=admin_iostockorder&act=iostock_add&p[0]=other&p[1]=' . $io,
                    'target' => '_blank',
                ),
                array('label' => app::get('taoguaniostockorder')->_('导出模板'), 'href' => 'index.php?app=console&ctl=admin_iostockorder&act=exportTemplate&p[1]=' . $io, 'target' => '_blank'),
                array('label' => '推送单据至WMS',
                    'submit'      => 'index.php?app=console&ctl=admin_iostockorder&act=batch_sync&io=' . $io,
                    'confirm'     => '你确定要对勾选的单子发送至仓储吗？',
                    'target'      => 'refresh'),
                array('label' => '批量审核',
                    'submit'      => 'index.php?app=console&ctl=admin_iostockorder&act=batch_check&io=' . $io,
                    'confirm'     => '你确定要对勾选的单子批量审核吗?',
                    'target'      => 'refresh'),
                array(
                    'label'  => sprintf('%s导入',$title),
                    'href'   => sprintf('%s&act=displayImportIso&io=%s', $this->url,$io),
                    'target' => sprintf('dialog::{width:760,height:300,title:\'%s导入\'}',$title),
                ),
            ),
            'title'                  => $title,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => true,
            'use_buildin_export'     => true,
            'use_buildin_import'     => false,
            'use_buildin_importxls'  => true,
            'use_buildin_filter'     => true,
            'finder_cols'            => 'column_edit,column_confirm,name,iso_bn,oper,operator,original_bn,create_time,type_id',
        );
        /* 获取操作员管辖仓库 */
        $oBranch  = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $params['base_filter']['branch_id|in'] = $branch_ids;
            } else {
                $params['base_filter']['iso_id'] = 'false';
            }
        }

        $iostock_type = kernel::single('taoguaniostockorder_iostockorder')->get_create_iso_type($io, true);

        if ($_POST['type_id'] && in_array($_POST['type_id'], $iostock_type)) {
            $params['base_filter']['type_id'] = $_POST['type_id'];

        } else {
            $params['base_filter']['type_id'] = $iostock_type;
        }

        #$params['base_filter']['confirm'] = 'N';
        //$this->workground = "console_purchasecenter";
        $this->finder('taoguaniostockorder_mdl_iso', $params);
    }

    /**
     * allocate_iostock
     * @return mixed 返回值
     */
    public function allocate_iostock()
    {
        $io               = $_GET['io'];
        $iostock_instance = kernel::single('siso_receipt_iostock');

        if ($io) {
            $title = '调拨入库';
            eval('$type=' . get_class($iostock_instance) . '::ALLOC_STORAGE;');
        } else {
            $title = '调拨出库';
            eval('$type=' . get_class($iostock_instance) . '::ALLOC_LIBRARY;');
        }
        $actions   = array();
        $actions[] = array('label' => '推送单据至WMS',
            'submit'                   => 'index.php?app=console&ctl=admin_iostockorder&act=batch_sync&io=' . $io,
            'confirm'                  => '你确定要对勾选的单子发送至仓储吗？',
            'target'                   => 'refresh');
        $params = array(
            'title'                  => $title,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => true,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'actions'                => $actions,
            'finder_cols'            => 'column_edit,column_confirm,name,iso_bn,oper,operator,original_bn,create_time,type_id',
        );
        /* 获取操作员管辖仓库 */
        $oBranch  = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            $params['base_filter']['branch_id|in'] = $branch_ids ?: [0];
        }

        $params['base_filter']['type_id'] = $type;
        //$params['base_filter']['iso_status'] = array('1','2');
        #$params['base_filter']['confirm'] = 'N';
        $this->workground = "console_purchasecenter";
        $this->finder('taoguaniostockorder_mdl_iso', $params);
    }

    /**
     * iostock_add
     * @param mixed $type type
     * @param mixed $io io
     * @return mixed 返回值
     */
    public function iostock_add($type, $io)
    {
        if ($io) {
            $order_label = '入库单';
        } else {
            $order_label = '出库单';

            #过滤o2o门店虚拟物流公司
            $oDly_corp                  = app::get('ome')->model('dly_corp');
            $dly_corp                   = $oDly_corp->getlist('*', array('disabled' => 'false', 'd_type' => '1'));
            $this->pagedata['dly_corp'] = $dly_corp;
        }

        $suObj = app::get('purchase')->model('supplier');
        $data  = $suObj->getList('supplier_id, name', '', 0, -1);

        $brObj = app::get('ome')->model('branch');
        $row   = $brObj->getList('branch_id, name',['is_ctrl_store'=>'1']);

        /* 获取操作员管辖仓库 */
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_list = $brObj->getBranchByUser();
        }
        $this->pagedata['branch_list'] = $branch_list;
        $is_super                      = 1;
        $this->pagedata['is_super']    = $is_super;

        $this->pagedata['supplier'] = $data;
        $operator                   = kernel::single('desktop_user')->get_name();
        $this->pagedata['operator'] = $operator;

        $this->pagedata['branch']        = $row;
        $this->pagedata['branchid']      = $branch_id;
        $this->pagedata['cur_date']      = date('Ymd', time()) . $order_label;
        $this->pagedata['io']            = $io;
        $this->pagedata['iostock_types'] = kernel::single('taoguaniostockorder_iostockorder')->get_create_iso_type($io);
        //出入库计划-入库单新建：移除type_id 11 作为选调拨入库取消 择项 此出入库类型为取消调拨入库时系统生成
        unset($this->pagedata['iostock_types'][11]);

        #外部仓库列表
        $oExtrabranch                  = app::get('ome')->model('extrabranch');
        $extrabranch                   = $oExtrabranch->getlist('branch_id,name', '', 0, -1);
        $this->pagedata['extrabranch'] = $extrabranch;
        if ($io) {
            $this->singlepage("admin/iostock/instock_add.html");
        } else {
            $this->singlepage("admin/iostock/outstock_add.html");
        }
    }

    /**
     * iostock_edit
     * @param mixed $iso_id ID
     * @param mixed $io io
     * @param mixed $act act
     * @return mixed 返回值
     */
    public function iostock_edit($iso_id, $io, $act)
    {
        $order_label = $io ? '入库单' : '出库单';

        //获取出入库单信息
        $isoObj     = app::get('taoguaniostockorder')->model('iso');
        $data       = $isoObj->dump($iso_id, '*', array('iso_items' => array('*')));
        $productIds = array();
        //总和
        $product_cost = 0;
        foreach ($data['iso_items'] as $k => $v) {
            $productIds[] = $v['product_id'];
            $total_num += $v['nums'];
            $product_cost += sprintf('%.3f', $v['nums'] * $v['price']);
        }
        $data['total_num']    = $total_num;
        $data['items']        = implode('-', $productIds);
        $data['product_cost'] = $product_cost;
        //获取仓库信息
        $branchObj           = app::get('ome')->model('branch');
        $branch              = $branchObj->dump(array('branch_id' => $data['branch_id']), 'branch_id, name');
        $data['branch_name'] = $branch['name'];
        $data['bill_type_name'] = $isoObj->modifier_bill_type($data['bill_type'], [0=>$data], $data);

        //获取出入库类型信息
        $iostockTypeObj    = app::get('ome')->model('iostock_type');
        $iotype            = $iostockTypeObj->dump(array('type_id' => $data['type_id']), 'type_name');
        $data['type_name'] = $iotype['type_name'];

        $operator     = kernel::single('desktop_user')->get_name();
        $data['oper'] = $data['oper'] ? $data['oper'] : $operator;
        #外部仓库列表
        $oExtrabranch                  = app::get('ome')->model('extrabranch');
        $extrabranch                   = $oExtrabranch->getlist('branch_id,name', '', 0, -1);
        $this->pagedata['extrabranch'] = $extrabranch;
        #
        $oDly_corp                     = app::get('ome')->model('dly_corp');
        $dly_corp                      = $oDly_corp->getlist('*', array('disabled' => 'false'));
        $this->pagedata['dly_corp']    = $dly_corp;
        $this->pagedata['io']          = $io;
        $this->pagedata['act']         = $act;
        $this->pagedata['iso']         = $data;
        $this->pagedata['order_label'] = $order_label;
        $this->pagedata['act_status']  = trim($_GET['act_status']);
        $this->singlepage("admin/iostock/instock_edit.html");
    }

    /**
     * 获取EditProducts
     * @param mixed $iso_id ID
     * @return mixed 返回结果
     */
    public function getEditProducts($iso_id)
    {
        if ($iso_id == '') {
            $iso_id = $_POST['p[0]'];
        }

        $basicMaterialSelect  = kernel::single('material_basic_select');
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');

        $isoItemObj = app::get('taoguaniostockorder')->model('iso_items');
        $rows       = array();
        $items      = $isoItemObj->getList('*', array('iso_id' => $iso_id), 0, -1);
        if ($items) {
            $product_ids = array();
            foreach ($items as $k => $v) {
                $product_ids[]           = $v['product_id'];
                $items[$k]['name']       = $v['product_name'];
                $items[$k]['num']        = $v['nums'];
                $items[$k]['barcode']    = &$product[$v['product_id']]['barcode'];
                $items[$k]['visibility'] = &$product[$v['product_id']]['visibility'];
                $items[$k]['spec_info']  = &$product[$v['product_id']]['spec_info'];
                #新建调拨单时,如果开启固定成本，price就是商品价格；如果没有开启，则是0
                $items[$k]['price'] = $v['price'];
            }
            if ($product_ids) {
                $plist = $basicMaterialSelect->getlist_ext('bm_id,visibled,specifications', array('bm_id' => $product_ids));
                foreach ($plist as $value) {
                    $product[$value['product_id']]['visibility'] = ($value['visibility'] == 1 ? 'true' : 'false');
                    $product[$value['product_id']]['spec_info']  = $value['specifications'];

                    #查询关联的条形码
                    $product[$value['product_id']]['barcode'] = $basicMaterialBarcode->getBarcodeById($value['product_id']);
                }
            }
        }
        $rows = $items;
        echo json_encode($rows);
    }

    /**
     * do_edit_iostock
     * @return mixed 返回值
     */
    public function do_edit_iostock()
    {

        $this->begin('index.php?app=console&ctl=admin_iostockorder&act=' . $_POST['io_act'] . '&io=' . $_POST['io']);
        $data              = $_POST;
        $data['old_items'] = explode('-', $data['old_items']);

        //出入库明细信息
        $branchProductObj    = app::get('ome')->model('branch_product');
        $isoItemObj          = app::get('taoguaniostockorder')->model('iso_items');
        $product_cost        = 0;
        $iso_items           = array();
        $productIds          = array();
        $appropriation_items = array();
        $itemsList = $isoItemObj->getList('*',['iso_id'=>$data['iso_id']]);

        $branchLib = kernel::single('ome_store_manage');
        $branchLib->loadBranch(array('branch_id' => $data['branch']));

        foreach ($data['bn'] as $product_id => $bn) {
            if ($data['at'][$product_id] == 0) {
                $this->end(false, '库存数量不能为0.');
            }

            if ($data['io'] == '0') {
                //获取单仓库-单个基础物料中的可用库存
                $params = array(
                    'node_type' => 'getAvailableStore',
                    'params'    => array(
                        'branch_id'  => $data['branch'],
                        'product_id' => $product_id,
                    ),
                );
                $usable_store = $branchLib->processBranchStore($params, $err_msg);

                if ($data['at'][$product_id] > $usable_store) {
                    $this->end(false, '货号：' . $bn . '出库数不可大于库存数' . $usable_store);
                }
            }

            $iso_items[$product_id] = array(
                'iso_id'       => $data['iso_id'],
                'iso_bn'       => $data['iso_bn'],
                'product_id'   => $product_id,
                'bn'           => $bn,
                'product_name' => $data['product_name'][$product_id],
                'unit'         => $data['unit'][$product_id],
                'nums'         => $data['at'][$product_id],
                'price'        => $data['pr'][$product_id],
            );

            $item = array();
            $item = $isoItemObj->dump(array('product_id' => $product_id, 'iso_id' => $data['iso_id']), 'iso_items_id');
            if ($item['iso_items_id'] > 0) {
                $iso_items[$product_id]['iso_items_id'] = $item['iso_items_id'];
            }

            $product_cost += $data['at'][$product_id] * $data['pr'][$product_id];
            $productIds[] = $product_id;

            $appropriation_items[$product_id] = array(
                'product_id'   => $product_id,
                'bn'           => $bn,
                'product_name' => $data['product_name'][$product_id],
                'num'          => $data['at'][$product_id],
            );
        }

        //出入库主单信息
        $operator          = kernel::single('desktop_user')->get_name();
        $operator          = $operator ? $operator : 'system';
        $iostockorder_data = array(
            'iso_id'         => $data['iso_id'],
            'name'           => $data['iostockorder_name'],
            'iso_price'      => $data['iso_price'],
            'oper'           => $data['operator'],
            'operator'       => $operator,
            'product_cost'   => $product_cost,
            'memo'           => $data['memo'],
            'iso_items'      => $iso_items,
            'extrabranch_id' => $data['extrabranch_id'],
            'extra_ship_name' => $data['extra_ship_name'],
            'extra_ship_area' => $data['extra_ship_area'],
            'extra_ship_addr' => $data['extra_ship_addr'],
            'extra_ship_zip' => $data['extra_ship_zip'],
            'extra_ship_tel' => $data['extra_ship_tel'],
            'extra_ship_mobile' => $data['extra_ship_mobile'],
            'extra_ship_email' => $data['extra_ship_email'],
            'cost_type' => $data['cost_type'],
            'cost_department' => $data['cost_department'],
            'business_bn' => $data['business_bn'],
        );
        if (is_numeric($data['corp_id'])) {
            $iostockorder_data['corp_id'] = $data['corp_id'];
        }
        $isoObj = app::get('taoguaniostockorder')->model('iso');
    
        $type = kernel::single('ome_iostock')->get_iostock_types();
        $log_msg = '编辑'.$type[$_POST['type_id']]['info'];
        $opObj = app::get('ome')->model('operation_log');
        
        if ($isoObj->save($iostockorder_data)) {
            $delFilter = $delIds = array();
            $delIds    = array_diff($data['old_items'], $productIds);
            $delIds    = array_values($delIds);
            foreach ($delIds as $key => $val) {
                if (!$val) {
                    unset($delIds[$key]);
                }
            }
            if (is_array($delIds) && count($delIds) > 0) {
                $delFilter['iso_id']     = $data['iso_id'];
                $delFilter['product_id'] = $delIds;
                $isoItemObj->delete($delFilter);
            }

            #更新调拨单明细
            if ($data['act_status'] == 'allocate_iostock') {
//当是调拔出库时才更新调拔单
                $isodata = $isoObj->dump(array('iso_id' => $data['iso_id']), 'original_id');
                $filter  = array('appropriation_id' => $isodata['original_id']);

                $apprItemObj = app::get('taoguanallocate')->model('appropriation_items');
                $apprItems   = $apprItemObj->dump($filter, 'from_branch_id,to_branch_id,from_pos_id,to_pos_id');
                $apprItemObj->delete($filter);

                foreach ($appropriation_items as $k => $v) {
                    $appropriation_items[$k]['appropriation_id'] = $isodata['original_id'];
                    $appropriation_items[$k]['from_branch_id']   = $apprItems['from_branch_id'];
                    $appropriation_items[$k]['from_pos_id']      = $apprItems['from_pos_id'];
                    $appropriation_items[$k]['to_pos_id']        = $apprItems['to_pos_id'];
                    $appropriation_items[$k]['to_branch_id']     = $apprItems['to_branch_id'];
                    $apprItemObj->save($appropriation_items[$k]);
                }
            }
    
            
            $memo = ['title' => $log_msg, 'memo' =>$itemsList];
            $opObj->write_log('edit_iostock@taoguaniostockorder', $data['iso_id'], json_encode($memo));
            $this->end(true, '保存完成');
        } else {
            $opObj->write_log('edit_iostock@taoguaniostockorder', $data['iso_id'], $log_msg.'：失败');
            $this->end(false, '保存失败');
        }
    }

    /**
     * do_save_iostockorder
     * @return mixed 返回值
     */
    public function do_save_iostockorder()
    {
        $libBranchProduct = kernel::single('ome_branch_product');

        $this->begin("index.php?app=console&ctl=admin_iostockorder");

        $_POST['iso_price'] = $_POST['iso_price'] ? $_POST['iso_price'] : 0;
        $oBranchProduct     = app::get('ome')->model('branch_product');

        $basicMaterialObj = app::get('material')->model('basic_material');

        if (!$_POST['bn']) {
            $this->end(false, '请先选择入库商品！.');
        }
        //判断类型是否是残损
        $branch_id     = $_POST['branch'];
        $branch_detail = kernel::single('console_iostockdata')->getBranchByid($branch_id);
        if ($_POST['type_id'] == '50' || $_POST['type_id'] == '5') {

            if ($branch_detail['type'] != 'damaged') {
                $this->end(false, '出入库类型为残损出入库，仓库必须为残仓!');
            }
        } else {
            if ($branch_detail['type'] == 'damaged') {
                $this->end(false, '出入库类型不为残损出入库,不可以选择残仓!');
            }
        }
        $products = array();
        foreach ($_POST['bn'] as $product_id => $bn)
        {
            //过滤空格和全角空格
            $bn = str_replace(array("\r\n", "\r", "\n", ' ', '　', "\t"), '',  $bn);
            
            if ($_POST['at'][$product_id] == 0) {
                $this->end(false, '库存数量不能为0.');
            }

            if ($_POST['io'] == '0') {
                #获取单仓库-单个基础物料中的可用库存
                $usable_store = $libBranchProduct->get_available_store($_POST['branch'], $product_id);

                if ($_POST['at'][$product_id] > $usable_store) {
                    $pInfo = array();

                    #基础物料
                    $pInfo = $basicMaterialObj->dump(array('bm_id' => $product_id), 'material_bn');

                    $this->end(false, '货号：' . $pInfo['material_bn'] . '出库数不可大于库存数' . $usable_store);
                }
            }

            $products[$product_id] = array('bn' => $bn,
                'nums'                              => $_POST['at'][$product_id],
                'unit'                              => $_POST['unit'][$product_id],
                'name'                              => $_POST['product_name'][$product_id],
                'price'                             => $_POST['pr'][$product_id],
            );
        }
        $_POST['products'] = $products;
        $iso_id            = kernel::single('console_iostockorder')->save_iostockorder($_POST, $msg);

    
        $type    = kernel::single('ome_iostock')->get_iostock_types();
        $log_msg = sprintf('新建%s：%s', $type[$_POST['type_id']]['info'], $iso_id ? '成功' : $msg);
        app::get('ome')->model('operation_log')->write_log('create_iostock@taoguaniostockorder', $iso_id, $log_msg);
        if ($iso_id) {

            $this->end(true, '保存完成');
        } else {

            $this->end(false, '保存失败', '', array('msg' => $msg));
        }
    }

    /**
     * 获取ProductStore
     * @return mixed 返回结果
     */
    public function getProductStore()
    {
        $libBranchProduct = kernel::single('ome_branch_product');

        $product_id = $_POST['pid'];
        $branch_id  = $_POST['bid'];

        if ($product_id > 0 && $branch_id > 0) {
            //获取单仓库-单个基础物料中的可用库存
            $usable_store = $libBranchProduct->get_available_store($branch_id, $product_id);

            echo json_encode(array('result' => 'true', 'store' => $usable_store));
        }
    }
    /**
     * 出入库单残损确认
     */
    public function doDefective($iso_id, $io)
    {

        $iso_itemsObj          = app::get('taoguaniostockorder')->model('iso_items');
        $iso_items             = $iso_itemsObj->getlist('*', array('iso_id' => $iso_id, 'defective_num|than' => '0'), 0, -1);
        $iso                   = array();
        $iso['iso_id']         = $iso_id;
        $iso['iso_items']      = $iso_items;
        $this->pagedata['iso'] = $iso;
        $this->singlepage('admin/iostock/stock_defective.html');
    }

    /**
     * 残损确认
     */
    public function doDefectiveconfirm()
    {
        $this->begin("index.php?app=console&ctl=admin_iostockorder");

        $iso_id     = intval($_POST['iso_id']);
        $oIso       = app::get('taoguaniostockorder')->model("iso");
        $iostockObj = kernel::single('console_iostockdata');
        $iso        = $oIso->dump(array('iso_id' => $iso_id), 'branch_id,iso_bn,type_id,iso_id,supplier_id,supplier_name,cost_tax,oper,create_time,operator,defective_status');
        if ($iso['defective_status'] != '1') {
            $this->end(false, '此单据已确认或无需确认!');
        }
        if (!in_array($iso['type_id'], array('5', '50'))) {
            $damagedbranch = $iostockObj->getDamagedbranch($iso['branch_id']);
            if (empty($damagedbranch)) {
                $this->end(false, $item['bn'] . '有不良品，但未设置主仓对应的残仓');
            }
            $branch_id = $damagedbranch['branch_id'];
        } else {
            $branch_id = $iso['branch_id'];
        }

        $io = $_POST['io'];
        #查询是否有不良品
        #
        $iostock_data = array(
            'type_id'       => '50',
            'branch_id'     => $branch_id,
            'iso_bn'        => $iso['iso_bn'],
            'iso_id'        => $iso['iso_id'],
            'supplier_id'   => $iso['supplier_id'],
            'supplier_name' => $iso['supplier_name'],
            'cost_tax'      => $iso['cost_tax'],
            'oper'          => $iso['oper'],
            'create_time'   => $iso['create_time'],
            'original_bn'   => $iso['iso_bn'],
            'original_id'   => $iso['iso_id'],
            'orig_type_id'  => $iso['type_id'],
        );
        $iso_data = $iostockObj->get_iostockData($iso_id);
        
        $oIsoItems = app::get('taoguaniostockorder')->model("iso_items");
        $iso_data['items'] = $oIsoItems->getList('product_id,bn,nums as num,product_name as name,price,defective_num,normal_num,iso_items_id,partcode', array('iso_id' => $iso_id));
        
        $items = array();
        foreach ($iso_data['items'] as $item) {
            if ($item['defective_num'] > 0) {
                $items[] = array(
                    'bn'           => $item['bn'],
                    'nums'         => $item['defective_num'],
                    'price'        => $item['price'],
                    'iso_items_id' => $item['iso_items_id'],
                );
            }

        }
        if (count($items) > 0) {
            $iostock_data['items'] = $items;
            $result                = kernel::single('console_iostockorder')->confirm_iostockorder($iostock_data, '50', $msg, null);
            if ($result) {
                #更新确认状态
                $io_update_data = array(
                    'defective_status' => '2',
                );
                $oIso->update($io_update_data, array('iso_id' => $iso_id));
                app::get('ome')->model('operation_log')->write_log('check_defective@taoguaniostockorder', $iso_id, '残存确认入库完成');
                $this->end(true, '成功');
            } else {

                $this->end(false, '残损确认失败!');
            }

        } else {
            $this->end(false, '没有可确认的货品');
        }

    }

    /**
     * 差异查看确认
     */
    public function difference($iso_id, $io)
    {

        $isoObj           = app::get('taoguaniostockorder')->model('iso');
        $suObj            = app::get('purchase')->model('supplier');
        $brObj            = app::get('ome')->model('branch');
        $iso              = $isoObj->dump($iso_id, '*');
        $stockObj         = kernel::single('console_receipt_stock');
        $iso['iso_items'] = $stockObj->difference_stock($iso['iso_bn']);
        $total_num        = 0;
        if ($iso['iso_items']) {
            foreach ($iso['iso_items'] as $k => $v) {
                $total_num += $v['nums'];
            }
        }

        $su                    = $suObj->dump($iso['supplier_id'], 'name');
        $br                    = $brObj->dump($iso['branch_id'], 'name');
        $iso['iso_id']         = $iso_id;
        $iso['branch_name']    = $br['name'];
        $iso['supplier_name']  = $su['name'];
        $iso['create_time']    = date("Y-m-d", $iso['create_time']);
        $iso['total_num']      = $total_num;
        $iso['memo']           = $iso['memo'];
        $this->pagedata['iso'] = $iso;
        $this->pagedata['io']  = $io;

        $this->singlepage('admin/iostock/stock_difference.html');
    }

    #导出模板
    /**
     * exportTemplate
     * @param mixed $p p
     * @return mixed 返回值
     */
    public function exportTemplate($p)
    {
        if ($p) {
            #入库
            $name = 'RK';
        } else {
            #出库
            $name = 'CK';
        }

        $obj_iso = app::get('taoguaniostockorder')->model('iso');

        $row = $obj_iso->exportTemplate($p);
        $data[] = array();
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, $name . date('Ymd'), 'xls', $row);
    }

    /**
     * 审核出入库单据
     * 
     */
    public function check($iso_id, $io, $act)
    {
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');

        $isoObj       = app::get('taoguaniostockorder')->model('iso');
        $suObj        = app::get('purchase')->model('supplier');
        $oExtrabranch = app::get('ome')->model('extrabranch');

        $brObj          = app::get('ome')->model('branch');
        $iso            = $isoObj->dump($iso_id, '*', array('iso_items' => array('*')));
        $iso['bill_type_name'] = $isoObj->modifier_bill_type($iso['bill_type'], [0=>$iso], $iso);
        $extrabranch_id = $iso['extrabranch_id'];
        $extrabranch    = $oExtrabranch->dump($extrabranch_id, 'name');

        $total_num = 0;
        if ($iso['iso_items']) {
            foreach ($iso['iso_items'] as $k => $v) {
                $total_num += $v['nums'];

                #查询关联的条形码
                $iso['iso_items'][$k]['barcode'] = $basicMaterialBarcode->getBarcodeById($v['product_id']);
            }
        }

        $su                       = $suObj->dump($iso['supplier_id'], 'name');
        $br                       = $brObj->dump($iso['branch_id'], 'name');
        $iso['iso_id']            = $iso_id;
        $iso['branch_name']       = $br['name'];
        $iso['supplier_name']     = $su['name'];
        $iso['create_time']       = date("Y-m-d", $iso['create_time']);
        $iso['total_num']         = $total_num;
        $iso['memo']              = $iso['memo'];
        $iso['extrabranch_name']  = $extrabranch['name'];
        list(,$iso['extra_ship_area']) = explode(':', $iso['extra_ship_area']);
        $this->pagedata['iso']    = $iso;
        $this->pagedata['io']     = $io;
        $this->pagedata['act']    = $act;
        $this->pagedata['amount'] = $iso['product_cost'] + $iso['iso_price'];
        $this->singlepage('admin/iostock/stock_check.html');

    }

    /**
     * 保存出入库审核单据
     * 
     */
    public function doCheck()
    {

        //$basicMaterialStock    = kernel::single('material_basic_material_stock');

        $this->begin('index.php?app=console&ctl=admin_iostockorder&act=' . $_POST['io_act'] . '&io=' . $_POST['io']);

        #更新单据审核状态
        $iso_id = intval($_POST['iso_id']);
        $io     = $_POST['io'];
        //检查不能重复操作
        // $cacheKeyName = sprintf("docheck_iso_id_%s", $iso_id);
        // $cacheData    = cachecore::fetch($cacheKeyName);
        // if ($cacheData === false) {
        //     cachecore::store($cacheKeyName, $iso_id, 60);
        // } else {
        //     $this->end(false, '单据号：' . $iso['iso_bn'] . ',一分钟内请不要重复操作!');
        // }

        list($rs, $rsData) = kernel::single('console_iostockorder')->doCkeck($iso_id, $io);
        $type    = kernel::single('ome_iostock')->get_iostock_types();
        $log_msg = sprintf('审核%s：%s', $type[$_POST['type_id']]['info'], $rs ? '成功' : $rsData['msg']);
        app::get('ome')->model('operation_log')->write_log('docheck_iostock@taoguaniostockorder', $iso_id, $log_msg);
        if(!$rs) {

            $this->end(false, $rsData['msg']);
        }

        $this->end(true, '审核成功');
    }

    /**
     * 取消单据
     */
    public function cancel($iso_id, $io, $type)
    {
        $isoObj = app::get('taoguaniostockorder')->model('iso');

        #库存状态判断
        $iso   = $isoObj->dump($iso_id, 'iso_bn,iso_id,type_id');
        $title = '';
        switch ($iso['type_id']) {
            case '4':
            case '40':
                $io_type = 'ALLCOATE';
                $title .= '调拔单';
                break;
            case '5':
            case '50':
                $io_type = 'DEFECTIVE';
                $title .= '残损';
                break;
            case '7':
            case '70':
                $io_type = 'DIRECT';
                $title .= '直接';
                break;
            default:
                $io_type = 'OTHER';
                $title .= '其它';
                break;

        }
        if ($io) {
            $method = 'otherstockin';
            $title .= '入库';
        } else {
            $method = 'otherstockout';
            $title .= '出库';
        }

        $this->pagedata['iso']   = $iso;
        $this->pagedata['io']    = $io;
        $this->pagedata['type']  = $type;
        $this->pagedata['title'] = $title;
        $iso_type_id             = $iso['type_id'];
        unset($iso);
        $this->display("admin/iostock/stock_cancel.html");
    }

    /**
     * 执行取消出入库
     */
    public function doCancel()
    {
        $this->begin('index.php?app=console&ctl=admin_iostockorder&act=' . $_POST['type'] . '&io=' . $_POST['io']);
        $type     = $_POST['type'];
        $iso_id   = $_POST['iso_id'];
        $isoObj   = app::get('taoguaniostockorder')->model('iso');
        $stockObj = kernel::single('console_receipt_stock');
        $iso      = $isoObj->dump($iso_id, 'iso_bn,iso_id,type_id,branch_id,iso_status,check_status,bill_type');
        if ($iso['iso_status'] > 1) {
            $this->end(false, '取消失败!');
        } else {
            $isoObj->update(array('iso_status' => 4), array('iso_id' => $iso_id));
            #如果是已审核，取消冻结库存
            if ($iso['check_status'] == '2') {
                if($_POST['io'] == '0') {
                    if ($stockObj->checkExist($iso['iso_bn'])) {
                        $stockObj->clear_stockout_store_freeze($iso, 'FINISH');
                    }
                }
                if($_POST['io'] == '1') {
                    //取消在途
                    $storeManageLib = kernel::single('ome_store_manage');
                    $storeManageLib->loadBranch(array('branch_id' => $iso['branch_id']));
                    $params                    = array();
                    $params['node_type']       = 'deleteArriveStore';
                    $params['params']          = array(
                        'obj_id' => $iso['iso_id'],
                        'branch_id' => $iso['branch_id'],
                        'obj_type' => 'iostockorder',
                    );
                    $storeManageLib->processBranchStore($params, $err_msg);
                    if ($iso['bill_type'] == 'vopjitrk') {
                        list($res, $msg) = kernel::single('console_vopreturn')->cancelStockin($iso_id);
                        if (!$res) {
                            $this->end(false, $msg);
                        }
                    }
                }
            }
    
            $msg = '取消单据';
            if ($_POST['forced_cancel_value'] == '1') {
                $msg = '强制'.$msg;
            }
            app::get('ome')->model('operation_log')->write_log('other_iostock_cancel@console',$iso_id,$msg);
    
            $this->end(true, '成功');
        }

    }

    /**
     * 确认是否可以取消
     */
    public function checkCancel($iso_id)
    {

        $isoObj = app::get('taoguaniostockorder')->model('iso');
        //$iso = $isoObj->dump($iso_id,'iso_bn,iso_id,type_id,branch_id,check_status,iso_status,out_iso_bn');
        $iso        = $isoObj->dump($iso_id, 'iso_bn,iso_id,type_id,branch_id,check_status,iso_status,out_iso_bn');
        $iso_status = $iso['iso_status'];
        $io         = $_GET['io'];
        $forced_cancel = $_GET['forced_cancel'];
        if ($iso['check_status'] == '2') {
            if ($iso_status > 1) {
                $result = array('rsp' => 'fail', 'err_msg' => '单据所在状态不允许此次操作');
            } else {
                switch ($iso['type_id']) {
                    case '4':
                    case '40':
                        $io_type = 'ALLCOATE';
                        break;
                    case '5':
                    case '50':
                        $io_type = 'DEFECTIVE';
                        break;
                    case '7':
                    case '70':
                        $io_type = 'DIRECT';
                        break;
                    default:
                        $io_type = 'OTHER';
                        break;
                }
                if ($io) {
                    $method = 'otherstockin';
                } else {
                    $method = 'otherstockout';
                }
                $branch_id = $iso['branch_id'];
                $data      = array(
                    'io_type'    => $io_type,
                    'io_bn'      => $iso['iso_bn'],
                    'out_iso_bn' => $iso['out_iso_bn'],
                    'branch_id'  => $branch_id,
                );

                if ($forced_cancel != '1') {
                    $result = kernel::single('console_event_trigger_' . $method)->cancel($data, true);
                }else{
                    $result = array('rsp' => 'succ');
                }
            }

        } else {
            $result = array('rsp' => 'succ');
        }

        echo json_encode($result);

    }

    //调拨入库 取消操作 生成入库单 打回原始仓库
    /**
     * cancelIostockin
     * @return mixed 返回值
     */
    public function cancelIostockin()
    {
        $iso_id                     = intval($_GET['iso_id']);
        $this->pagedata['cur_date'] = date('Ymd', time()) . "调拨入库取消单";
        $this->pagedata['title']    = "调拔单入库"; //checkCancel时用来撤销原调拨入库单撤销
        $operator                   = kernel::single('desktop_user')->get_name();
        $this->pagedata['operator'] = $operator;
        $this->pagedata["iso_id"]   = $iso_id;

        //get from_branch_id to_branch_id
        $taoguaniostockorder_iso_obj = app::get('taoguaniostockorder')->model('iso');
        $taoguaniostockorder_isoinfo = $taoguaniostockorder_iso_obj->dump(array('iso_id' => $iso_id), 'iso_id,branch_id,extrabranch_id');
        $from_branch_id              = intval($taoguaniostockorder_isoinfo["extrabranch_id"]);
        $to_branch_id                = intval($taoguaniostockorder_isoinfo["branch_id"]);
        $branch_ids                  = array($from_branch_id, $to_branch_id);
        //获取仓库信息
        $branch_obj  = app::get('ome')->model('branch');
        $branch_info = $branch_obj->getList("branch_id,name", array('branch_id|in' => $branch_ids));
        foreach ($branch_info as $var_branch_info) {
            if (intval($var_branch_info["branch_id"]) == $from_branch_id) {
                $this->pagedata["from_branch_name"] = $var_branch_info["name"];
            }
            if (intval($var_branch_info["branch_id"]) == $to_branch_id) {
                $this->pagedata["to_branch_name"] = $var_branch_info["name"];
            }
        }

        //get iso_items
        $taoguaniostockorder_iso_items_obj = app::get('taoguaniostockorder')->model('iso_items');
        $this->pagedata["iso_items"]       = $taoguaniostockorder_iso_items_obj->getList("product_id,product_name,bn,nums", array("iso_id" => $iso_id));
        $arr_products_ids                  = array();
        foreach ($this->pagedata["iso_items"] as $var_iso_item) {
            if (!in_array($var_iso_item["product_id"], $arr_products_ids)) {
                $arr_products_ids[] = $var_iso_item["product_id"];
            }
        }

        #基础物料
        $basicMaterialLib = kernel::single('material_basic_material');
        $rs_products      = $basicMaterialLib->getBasicMaterialByBmids($arr_products_ids);

        $rl_product_id_infos = array();
        foreach ($rs_products as $var_product) {
            $temp_product_arr = array(
                "spec_info" => $var_product["spec_info"],
                "barcode"   => $var_product["barcode"],
                "price"     => $var_product["price"],
            );
            $rl_product_id_infos[$var_product["product_id"]] = $temp_product_arr;
        }
        $this->pagedata["total_stockin_number"] = 0;
        $this->pagedata["total_stockin_price"]  = 0;
        foreach ($this->pagedata["iso_items"] as &$final_iso_item) {
            $final_iso_item["spec_info"]   = $rl_product_id_infos[$final_iso_item["product_id"]]["spec_info"];
            $final_iso_item["barcode"]     = $rl_product_id_infos[$final_iso_item["product_id"]]["barcode"];
            $final_iso_item["price"]       = $rl_product_id_infos[$final_iso_item["product_id"]]["price"];
            $final_iso_item["total_price"] = sprintf("%.3f", $final_iso_item["price"] * $final_iso_item["nums"]);
            $this->pagedata["total_stockin_number"] += $final_iso_item["nums"];
            $this->pagedata["total_stockin_price"] += $final_iso_item["total_price"];
        }
        unset($final_iso_item);
        $this->pagedata["total_stockin_price"] = sprintf("%.3f", $this->pagedata["total_stockin_price"]);
        $this->singlepage("admin/iostock/instock_appropriation_cancel.html");
    }

    //生成入库单 打回原始仓库
    /**
     * doCancelIostockin
     * @return mixed 返回值
     */
    public function doCancelIostockin()
    {
        $this->begin('');

        if (!$_POST["iostockorder_name"]) {
            $this->end(false, "单据名称不能为空");
        }
        //第三方仓或者自由仓 出库确认后的调拨入库单id
        $iso_id = intval($_POST["iso_id"]);

        $taoguaniostockorder_iso_obj       = app::get('taoguaniostockorder')->model('iso');
        $taoguaniostockorder_iso_items_obj = app::get('taoguaniostockorder')->model('iso_items');
        $info_iso                          = $taoguaniostockorder_iso_obj->dump(array('iso_id' => $iso_id), "*");
        $info_iso_items                    = $taoguaniostockorder_iso_items_obj->getList("*,nums as num", array('iso_id' => $iso_id));

        //更新原入库单为取消状态 iso_status 4
        $iso_status_update = array('iso_status' => '4');
        $taoguaniostockorder_iso_obj->update($iso_status_update, array('iso_id' => $iso_id));

        foreach ($info_iso_items as $ik => $iv) {
            if (app::get('taoguaninventory')->is_installed()) {
                //检查是否在盘点：传入商品id和目标仓库id
                $check_inventory = kernel::single('taoguaninventory_inventorylist')->checkproductoper($iv['product_id'], $info_iso["extrabranch_id"]);
                if (!$check_inventory) {
                    $this->end(false, '此商品正在盘点中，不可以出入库操作!', 'index.php?app=console&ctl=admin_iostockorder&act=cancelIostockin&iso_id=' . $iso_id);
                }
            }
        }

        //组调拨入库取消类型的单据 插入到出入库计划中的入库单页
        $products_arr = array();
        foreach ($info_iso_items as $var_iso_item) {
            $temp_p = array(
                "bn"    => $var_iso_item["bn"],
                "nums"  => $var_iso_item["nums"],
                "unit"  => $var_iso_item["unit"],
                "name"  => $var_iso_item["product_name"],
                "price" => $var_iso_item["price"],
            );
            $products_arr[$var_iso_item["product_id"]] = $temp_p;
        }
        //取消在途
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id' => $info_iso['branch_id']));
        $params                    = array();
        $params['node_type']       = 'deleteArriveStore';
        $params['params']          = array(
            'obj_id' => $info_iso['iso_id'],
            'branch_id' => $info_iso['branch_id'],
            'obj_type' => 'iostockorder',
        );
        $storeManageLib->processBranchStore($params, $err_msg);
        $operator             = kernel::single('desktop_user')->get_name();
        $operator             = $operator ? $operator : 'system';
        $cancel_appropriation = array(
            "io"                => 1,
            "iostockorder_name" => $_POST["iostockorder_name"],
            "branch"            => $info_iso["extrabranch_id"],
            "type_id"           => 11,
            "operator"          => $operator,
            "appropriation_no"  => $info_iso["appropriation_no"],
            "products"          => $products_arr,
        );

        //新增调拨入库取消类型的单据 可在出入库计划中的入库单页操作
        $cancel_iso_id = kernel::single('console_iostockorder')->save_iostockorder($cancel_appropriation, $msg);
        if ($cancel_iso_id) {
            $this->end(true, '调拨入库取消单据已生成');
        } else {

            $this->end(false, '调拨入库取消单据未生成', '', array('msg' => $msg));
        }

    }

    /**
     * 调拔入库列表
     * 
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function allocate_iostocklist()
    {
        $io               = $_GET['io'];
        $iostock_instance = kernel::single('siso_receipt_iostock');

        if ($io) {
            $title = '调拨入库列表';
            eval('$type=' . get_class($iostock_instance) . '::ALLOC_STORAGE;');
        } else {
            $title = '调拨出库列表';
            eval('$type=' . get_class($iostock_instance) . '::ALLOC_LIBRARY;');
        }

        $params = array(
            'title'                  => $title,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'finder_cols'            => 'name,iso_bn,oper,operator,original_bn,create_time,type_id',
        );
        /* 获取操作员管辖仓库 */
        $oBranch  = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $params['base_filter']['branch_id|in'] = $branch_ids;

                // $oIso     = app::get('taoguaniostockorder')->model('iso');
                // $iso_list = $oIso->getList('iso_id', array('branch_id' => $branch_ids), 0, -1);
                // if ($iso_list) {
                //     foreach ($iso_list as $p) {
                //         $isolist[] = $p['iso_id'];
                //     }
                // }
                // if ($isolist) {
                //     $isolist                         = array_unique($isolist);
                //     $params['base_filter']['iso_id'] = $isolist;
                // } else {
                //     $params['base_filter']['iso_id'] = 'false';
                // }
            } else {
                $params['base_filter']['iso_id'] = 'false';
            }
        }

        $params['base_filter']['type_id'] = $type;

        $this->workground = "console_center";
        $this->finder('taoguaniostockorder_mdl_iso', $params);
    } // end func

    /**
     * 单据发送至第三方.
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function batch_sync()
    {
        // $this->begin('');
        $isoObj  = app::get('taoguaniostockorder')->model('iso');
        $ids     = $_POST['iso_id'];
        $isoList = $isoObj->getList('iso_id', array('iso_id' => $ids, 'check_status' => '2', 'iso_status' => array('1')));

        $io = $_GET['io'];
        foreach ($isoList as $iso) {
            $iso_id = $iso['iso_id'];

            if ($io == '0') {

                kernel::single('console_event_trigger_otherstockout')->create(array('iso_id' => $iso_id), false);
            } else {
                #入库
                kernel::single('console_event_trigger_otherstockin')->create(array('iso_id' => $iso_id), false);
            }
        }

        $this->splash('success', null, '命令已经被成功发送！！');
    }

    /**
     * batch_check
     * @return mixed 返回值
     */
    public function batch_check()
    {
        // $this->begin('');
        // kernel::database()->exec('commit');
        $isoObj  = app::get('taoguaniostockorder')->model('iso');
        $ids     = $_POST['iso_id'];
        $isoList = $isoObj->getList('iso_id', array('iso_id' => $ids, 'check_status' => '1', 'confirm' => 'N', 'iso_status' => '1'));

        $io      = $_GET['io'];

        if ($io == '1' || $io == '0') {// 等于1是入库单  等于0是出库单
            foreach ($isoList as $iso) {
                $iso_id   = $iso['iso_id'];
                $iso_data = array('check_status' => '2');
                $result   = $isoObj->update($iso_data, array('iso_id' => $iso_id));

                if ($io == '1') {
                    kernel::single('console_event_trigger_otherstockin')->create(array('iso_id' => $iso_id), false);
                }else{
                    kernel::single('console_event_trigger_otherstockout')->create(array('iso_id' => $iso_id), false);
                }
            }
        }

        $this->splash('success', null, '命令已经被成功发送！');

    }

    /**
     * iostockConfirm
     * @param mixed $iso_id ID
     * @param mixed $io io
     * @return mixed 返回值
     */
    public function iostockConfirm($iso_id, $io)
    {
        $iso_itemsObj          = app::get('taoguaniostockorder')->model('iso_items');
        $iso_items             = $iso_itemsObj->getlist('*', array('iso_id' => $iso_id), 0, -1);
        $iso                   = array();
        $iso['iso_id']         = $iso_id;
        $iso['iso_items']      = $iso_items;
        $this->pagedata['iso'] = $iso;
        $this->pagedata['io']  = $io;
        $this->singlepage('admin/iostock/iostock_confirm.html');
    }

    /**
     * doIostockconfirm
     * @return mixed 返回值
     */
    public function doIostockconfirm()
    {
        $this->begin("index.php?app=console&ctl=admin_iostockorder");

        $iso_id     = intval($_POST['iso_id']);
        $oIso       = app::get('taoguaniostockorder')->model("iso");
        $iostockObj = kernel::single('console_iostockdata');
        $iso        = $oIso->dump(array('iso_id' => $iso_id), 'branch_id,iso_bn,type_id,iso_id,supplier_id,supplier_name,cost_tax,oper,create_time,operator,defective_status,iso_status');
        if ($iso['iso_status'] == '3') {
            $this->end(false, '此单据已确认或无需确认!');
        }
        //加缓存时间
        $_inner_key = sprintf("iostock_%s", $iso_id);
        $aData      = cachecore::fetch($_inner_key);
        if ($aData === false) {
            cachecore::store($_inner_key, 'iostock', 5);
        } else {

            $this->end(false, '此单据已在确认中!');
        }
        $branch_id    = $iso['branch_id'];
        $io           = $_POST['io'];
        $iostock_data = array(
            'type_id'       => $iso['type_id'],
            'branch_id'     => $branch_id,
            'iso_bn'        => $iso['iso_bn'],
            'original_bn'   => $iso['iso_bn'],
            'iso_id'        => $iso['iso_id'],
            'original_id'   => $iso['iso_id'],
            'supplier_id'   => $iso['supplier_id'],
            'supplier_name' => $iso['supplier_name'],
            'cost_tax'      => $iso['cost_tax'],
            'oper'          => $iso['oper'],
            'create_time'   => $iso['create_time'],
        );
        $iso_data   = $iostockObj->get_iostockData($iso_id);
        $items = array();
        foreach ($iso_data['items'] as $item) {
            if ($item['normal_num'] > 0) {
                $items[] = array(
                    'bn'            => $item['bn'],
                    'nums'          => $item['normal_num'],
                    'price'         => $item['price'],
                    'iso_items_id'  => $item['iso_items_id'],
                    'effective_num' => $item['normal_num'],
                    'product_id'    => $item['product_id'],
                );
            }
        }
        if (count($items) > 0) {
            $iostock_data['items'] = $items;
            $result                = kernel::single('console_iostockorder')->confirm_iostockorder($iostock_data, $iso['type_id'], $msg, null);
            if ($result) {
                #更新确认状态
                $io_update_data = array(
                    'iso_status' => '3',
                );
                $oIso->update($io_update_data, array('iso_id' => $iso_id));
                //出库释放冻结
                if ($io == '0') {
                    $stockObj = kernel::single('console_receipt_stock');
                    if ($stockObj->checkExist($iso['iso_bn'])) {
                        $iso['items'] = $items;
                        $stockObj->clear_stockout_store_freeze($iso, 'FINISH');
                    }
                }
                if ($iso['type_id'] == '40') {

                    kernel::single('console_iostockdata')->allocate_out($iso_id);
                }
                $this->end(true, '成功');
            } else {

                $this->end(false, '确认失败!');
            }

        } else {
            $this->end(false, '没有可确认的货品');
        }

    }

    /**
     * 添加Same
     * @param mixed $iso_id ID
     * @param mixed $io io
     * @return mixed 返回值
     */
    public function addSame($iso_id,$io)
    {
        $this->begin('index.php?app=console&ctl=admin_iostockorder&act=other_iostock&io='.$io);
        if (empty($iso_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        if ($io) {
            $order_label = '入库单';
        } else {
            $order_label = '出库单';
        }

        //仓库列表
        $brObj = app::get('ome')->model('branch'); 
        $branchList   = $brObj->getList('branch_id, name', array('b_type' => 1), 0, -1);
        //获取操作员管辖仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $iostockObj  = kernel::single('console_iostockdata');
            $branch_list = $iostockObj->getBranchByUser();
        }

        //入库类型列表
        $this->pagedata['io']            = $io;
        $this->pagedata['iostock_types'] = kernel::single('taoguaniostockorder_iostockorder')->get_create_iso_type($io);
        unset($this->pagedata['iostock_types'][11]);
        //来源地列表
        $oExtrabranch                  = app::get('ome')->model('extrabranch');
        $extrabranch                   = $oExtrabranch->getlist('branch_id,name', '', 0, -1);
        $this->pagedata['extrabranch'] = $extrabranch;
        //仓库等信息
        $isoObj = app::get('taoguaniostockorder')->model('iso');
        $data = $isoObj->dump(array('iso_id' => $iso_id), '*', array('iso_items' => array('*')));
        $data['bill_type_name'] = $isoObj->modifier_bill_type($data['bill_type'], [0=>$data], $data);

        $this->pagedata['branch_list'] = $branch_list;
        $is_super                      = 1;
        $this->pagedata['is_super']    = $is_super;
        $this->pagedata['branch']           = $branchList;
        $this->pagedata['iso']              = $data;
        $this->pagedata['cur_date']         = date('Ymd', time()) . $order_label;
        if ($io) {
            //出库单需要物流信息
            $oDly_corp                     = app::get('ome')->model('dly_corp');
            $this->pagedata['dly_corp']    = $oDly_corp->getlist('*', array('disabled' => 'false'));
        }
        $this->singlepage("admin/iostock/instock_addSame.html");

    }

    /**
     * doSame
     * @return mixed 返回值
     */
    public function doSame()
    {
        $libBranchProduct = kernel::single('ome_branch_product');

        $this->begin("index.php?app=console&ctl=admin_iostockorder");

        $_POST['iso_price'] = $_POST['iso_price'] ? $_POST['iso_price'] : 0;
        $oBranchProduct     = app::get('ome')->model('branch_product');
        $io = $_POST['io'];
        $basicMaterialObj = app::get('material')->model('basic_material');

        if (!$_POST['bn']) {
            $this->end(false, '请先选择入库商品！.');
        }
        //判断类型是否是残损
        $branch_id     = $_POST['branch'];
        $branch_detail = kernel::single('console_iostockdata')->getBranchByid($branch_id);
        if ($_POST['type_id'] == '50' || $_POST['type_id'] == '5') {

            if ($branch_detail['type'] != 'damaged') {
                $this->end(false, '出入库类型为残损出入库，仓库必须为残仓!');
            }
        } else {
            if ($branch_detail['type'] == 'damaged') {
                $this->end(false, '出入库类型不为残损出入库,不可以选择残仓!');
            }
        }
        $products = array();
        foreach ($_POST['bn'] as $product_id => $bn) {
            if ($_POST['at'][$product_id] == 0) {
                $this->end(false, '库存数量不能为0.');
            }

            if ($io == '0') {
                #获取单仓库-单个基础物料中的可用库存
                $usable_store = $libBranchProduct->get_available_store($_POST['branch'], $product_id);

                if ($_POST['at'][$product_id] > $usable_store) {
                    $pInfo = array();

                    #基础物料
                    $pInfo = $basicMaterialObj->dump(array('bm_id' => $product_id), 'material_bn');

                    $this->end(false, '货号：' . $pInfo['material_bn'] . '出库数不可大于库存数' . $usable_store);
                }
            }

            $products[$product_id] = array('bn' => $bn,
                'nums'                              => $_POST['at'][$product_id],
                'unit'                              => $_POST['unit'][$product_id],
                'name'                              => $_POST['product_name'][$product_id],
                'price'                             => $_POST['pr'][$product_id],
            );
        }
        $_POST['products'] = $products;
        $iso_id            = kernel::single('console_iostockorder')->save_iostockorder($_POST, $msg);

        if ($iso_id) {

            $this->end(true, '保存完成');
        } else {

            $this->end(false, '未完成', "index.php?app=console&ctl=admin_iostockorder&act=iostock_add&p[0]=other&p[1]=$io");
        }
    }
    
    /**
     * 出入库单明细更新记录展示
     * @param $log_id
     * @date 2024-09-09 2:43 下午
     */
    public function detailHistory($log_id)
    {
        $basicMaterialSelect  = kernel::single('material_basic_select');
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');

        $logObj       = app::get('ome')->model('operation_log');
        $history      = $logObj->db_dump(['log_id' => $log_id]);
        $historyItems   = json_decode($history['memo'], true);
        $items     = $historyItems['memo'] ?: [];
        if ($items) {
            $product_ids = array();
            foreach ($items as $k => $v) {
                $product_ids[]           = $v['product_id'];
                $items[$k]['name']       = $v['product_name'];
                $items[$k]['num']        = $v['nums'];
                $items[$k]['barcode']    = &$product[$v['product_id']]['barcode'];
                $items[$k]['visibility'] = &$product[$v['product_id']]['visibility'];
                $items[$k]['spec_info']  = &$product[$v['product_id']]['spec_info'];
                #新建调拨单时,如果开启固定成本，price就是商品价格；如果没有开启，则是0
                $items[$k]['price'] = $v['price'];
                $items[$k]['count'] = bcmul($v['price'],$v['nums'],3);
            }
            if ($product_ids) {
                $plist = $basicMaterialSelect->getlist_ext('bm_id,visibled,specifications', array('bm_id' => $product_ids));
                foreach ($plist as $value) {
                    $product[$value['product_id']]['visibility'] = ($value['visibility'] == 1 ? 'true' : 'false');
                    $product[$value['product_id']]['spec_info']  = $value['specifications'];
                    
                    #查询关联的条形码
                    $product[$value['product_id']]['barcode'] = $basicMaterialBarcode->getBarcodeById($value['product_id']);
                }
            }
        }
        $this->pagedata['items'] = $items;
        $this->singlepage("admin/iostock/iostock_detail_history.html");
    }
    
    /**
     * 出入库单切片导入页面
     * @date 2024-10-11 4:05 下午
     */
    public function displayImportIso()
    {
        $this->pagedata['type'] = 'iostock';
        $this->pagedata['io']   = $_GET['io'];
        $this->display('admin/iostock/outstock_import.html');
    }
}
