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

class console_ctl_admin_purchase extends desktop_controller
{
    public $name       = "采购管理";
    public $workground = "console_purchasecenter";

    public function index()
    {
        $singleselect = $_GET['singleselect'];
        if (!$singleselect){
            $action = array(
                array(
                    'label'  => '新建',
                    'href'   => 'index.php?app=console&ctl=admin_purchase&act=add',
                    'target' => '_blank',
                ),
                array(
                    'label'  => '导出模板',
                    'href'   => 'index.php?app=console&ctl=admin_purchase&act=exportTemplate',
                    'target' => '_blank',
                ),
                array('label' => '推送单据至WMS',
                      'submit'      => 'index.php?app=console&ctl=admin_purchase&act=batch_sync',
                      'confirm'     => '你确定要对勾选的采购单发送至第三方吗？',
                      'target'      => 'refresh'),
            );
            $export = true;
            $import = true;
            $filter = true;
        }
        $params = array(
            'title'                  => '采购列表',
            'actions'                => $action,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => $export,
            'use_buildin_import'     => $import,
            'use_buildin_importxls'  => $import,
            'use_buildin_filter'     => $filter,
            'finder_cols'            => 'column_edit,supplier_id,emergency,name,arrive_time,operator,deposit,purchase_time,check_status,check_time,po_status,po_type,statement,eo_status,branch_id,check_operator',
            'orderBy'                => 'emergency asc,purchase_time desc',
        );

        $oBranch  = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                if (isset($_POST['branch_id']) && $_POST['branch_id']) {
                    $params['base_filter']['branch_id'] = $_POST['branch_id'];
                } else {
                    $params['base_filter']['branch_id'] = $branch_ids;
                }

            } else {
                $params['base_filter']['branch_id'] = 'false';
            }
        }

        $this->finder('purchase_mdl_po', $params);
    }

    /**
     * 打开已完成的采购单列表信息（添加采购）
     */
    public function getSuccessPurchase(){
        $params = array(
            'title'                  => '采购单列表',
            'base_filter'            => array('eo_status' => array(3),'po_species'=>'1'),
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'finder_cols'            => 'column_edit,supplier_id,emergency,name,arrive_time,operator,deposit,purchase_time,check_status,check_time,po_status,po_type,statement,eo_status,branch_id,check_operator',
            'orderBy'                => 'emergency asc,purchase_time desc',
        );
        $this->finder('purchase_mdl_po', $params);
    }

    public function checklist()
    {
        $params = array(
            'title'                  => '待审核',
            'base_filter'            => array('check_status' => array(1)),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'orderBy'                => 'emergency asc,purchase_time desc',
        );
        $this->finder('purchase_mdl_po', $params);
    }

    public function check_auto()
    {
        $this->begin('index.php?app=console&ctl=admin_purchase&act=index');
        foreach ($_POST['po_id'] as $v) {
            app::get('purchase')->model('po')->update(array('check_status' => 2, 'eo_status' => 1), array('po_id' => $v, 'check_status' => 1));
            kernel::single('console_event_trigger_purchase')->create(array('po_id' => $v), false);
        }
        $this->end(true, '批量审核成功');
    }

    public function check($po_id, $uncheck = false)
    {
        $this->begin('index.php?app=console&ctl=admin_purchase&act=index');
        if (empty($po_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        if ($uncheck) {
            $checkInfo = array(
                'title'  => '反审核',
                'action' => 'do_uncheck',
            );
        } else {
            $checkInfo = array(
                'title'  => '审核',
                'action' => 'do_check',
            );
        }
        $this->pagedata['checkInfo'] = $checkInfo;
        $poObj                       = app::get('purchase')->model('po');
        $suObj                       = app::get('purchase')->model('supplier');
        $brObj                       = app::get('ome')->model('branch');

        $data = $poObj->dump($po_id, '*', array('po_items' => array('*')));

        //当前供应商
        $supplier_detail                   = $suObj->dump($data['supplier_id'], 'supplier_id,name');
        $this->pagedata['supplier_detail'] = $supplier_detail;
        
        $this->pagedata['branch_mode'] = 'single';

        $su                    = $suObj->dump($data['supplier_id'], 'name');
        $br                    = $brObj->dump($data['branch_id'], 'name');
        $data['branch_name']   = $br['name'];
        $data['supplier_name'] = $su['name'];
        //到货天数
        if($data['arrive_time']>0){
            $diff_time                  = $data['arrive_time'] - $data['purchase_time'];
            $data['diff_days']          = floor($diff_time / (24 * 60 * 60));
        }
        
        $this->pagedata['po_items'] = $data['po_items'];
        if ($data['memo']) {
            $data['memo'] = unserialize($data['memo']);
            if ($data['memo']) {
                foreach ($data['memo'] as $key => $v) {
                    $str = unserialize($v['op_content']);
                    if ($str) {
                        $data['memo'][$key]['op_content'] = $str[0]['op_content'];
                    } else {
                        $data['memo'][$key]['op_content'] = $v['op_content'];
                    }
                }
            }

        }
        $this->pagedata['po'] = $data;
        $this->singlepage("admin/purchase/purchase_check.html");
    }

    public function do_check()
    {
        $this->begin('index.php?app=console&ctl=admin_purchase&act=index');
        $po_id = $_POST['po_id'];

        list($rs, $msg) = kernel::single('console_po')->do_check($po_id);

        $this->end($rs, $msg);
    }

    public function uncheck($po_id)
    {
        $this->check($po_id, true);
    }

    public function do_uncheck()
    {

    }

    public function eoList($p = null)
    {
        switch ($p) {
            case 'i':
                $sub_title        = '采购入库';
                $this->workground = 'console_center';
                break;
            default:
                $sub_title = '待入库';
                break;
        }

        $filter['eo_status'] = array('1', '2');
        $params              = array(
            'title'                  => $sub_title,
            'base_filter'            => $filter,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'orderBy'                => 'purchase_time desc',
            'finder_cols'            => 'column_edit,supplier_id,emergency,name,arrive_time,operator,deposit,purchase_time,check_status,check_time,po_status,po_type,statement,eo_status,branch_id,check_operator',
        );

        $oBranch  = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $params['base_filter']['branch_id'] = $branch_ids;
            } else {
                $params['base_filter']['branch_id'] = 'false';
            }
        }
        $this->finder('purchase_mdl_po', $params);
    }
    
    public function exportTemplate()
    {
        $pObj   = app::get('purchase')->model('po');
        $row = $pObj->io_title('purchase');
        $data[] = array();
        $data[] = $pObj->io_title('item');
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, '采购单模板', 'xls', $row);
    }
    /**
     * 新建采购单
     *
     */
    public function add()
    {
        $suObj = app::get('purchase')->model('supplier');
        $data  = $suObj->getList('supplier_id, name', '', 0, -1);
        /*
         * 获取操作员管辖仓库
         */
        $is_super                      = kernel::single('desktop_user')->is_super();
        $iostockObj                    = kernel::single('console_iostockdata');
        $branch_list                   = $iostockObj->getBranchByUser();

        $branch_list = kernel::single('ome_branch_type')->getBranchByIOType(ome_iostock::PURCH_STORAGE);
        $this->pagedata['branch_list'] = $branch_list;
        $is_super                      = 1;
        $this->pagedata['is_super']    = $is_super;

        //获取设置的采购方式
        $po_type = app::get('ome')->getConf('purchase.po_type');

        if (!$po_type) {
            $po_type = 'credit';
        }

        $this->pagedata['po_type'] = $po_type;

        $this->pagedata['supplier'] = $data;
        $operator                   = kernel::single('desktop_user')->get_name();
        $this->pagedata['operator'] = $operator;

        $this->pagedata['branchid'] = 0;
        $this->pagedata['cur_date'] = date('Ymd', time()) . '采购单';
        $this->singlepage("admin/purchase/purchase_add.html");
    }

    public function add_replenishment(){
        $oPo  = app::get('purchase')->model('po');
        $branchMdl  = app::get('ome')->model('branch');
        $suObj = app::get('purchase')->model('supplier');

        $po_id = $_GET['po_id'];
        $po_info = $oPo->dump($po_id,'*');
        if(empty($po_info) || $po_info['eo_status'] != 3){
            echo "
<script>alert('单据不存在或状态不是已入库');
window.location.href = \"index.php?app=console&ctl=admin_purchase&act=add\";
</script>";exit();
        }

        $supplier                 = $suObj->dump($po_info['supplier_id'], 'supplier_id,name,arrive_days');
        $this->pagedata['supplier'] = $supplier;

        $po_info['memo']               = unserialize($po_info['memo']);
        if ($po_info['memo']) {
            foreach ($po_info['memo'] as $key => $v) {
                $str = unserialize($v['op_content']);
                if ($str) {
                    $po_info['memo'][$key]['op_content'] = $str[0]['op_content'];
                } else {
                    $po_info['memo'][$key]['op_content'] = $v['op_content'];
                }
            }
        }

        $this->pagedata['po_info'] = $po_info;
        $po_items = $oPo->getPoItemByPoId($po_id);
       # pr($po_info,1);
        $this->pagedata['po_items'] = json_encode($po_items);
        $branch_info                   = $branchMdl->dump($po_info['branch_id'],'name,branch_id');
        $this->pagedata['branch_info'] = $branch_info;
       # pr($branch_info,1);

        $this->pagedata['cur_date'] = date('Ymd', time()) . '采购补货单';
        $this->singlepage("admin/purchase/purchase_replenishment_add.html");



    }

    /**
     * 从仓库搜索商品
     */
    public function findInBranch()
    {
        $where = ' 1 ';
        $poObj = app::get('purchase')->model('po');
        if ($_POST['name'] != '') {
            $where .= " AND p.name LIKE " . $poObj->db->quote('%' . $_POST['name'] . '%');
        }
        if ($_POST['branch'] != '') {
            $where .= " AND bp.branch_id=" . intval($_POST['branch']);
        }
        $branchObj = app::get('ome')->model('branch');
        $branch    = $branchObj->getList('branch_id,name', '', 0, -1);
        $data      = $poObj->findProductsByBranch($where);

        $this->pagedata['goods_name'] = $_POST['name'];
        $this->pagedata['branch_id']  = $_POST['branch_id'];
        $this->pagedata['branch']     = $branch;
        $this->pagedata['data']       = $data;
        $this->display("admin/purchase/purchase_find_in_branch.html");
    }

    //获取基础物料
    public function getProducts()
    {
        $basicMaterialSelect  = kernel::single('material_basic_select');
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');

        $pro_id      = $_POST['product_id'];
        $supplier_id = $_POST['supplier_id'];
        $pro_bn      = $_GET['bn'];
        $pro_name    = $_GET['name'];
        $pro_barcode = $_GET['barcode'];

        if (is_array($pro_id)) {
            $filter['bm_id'] = $pro_id;
        }

        //选定全部
        if (is_array($filter['bm_id'][0]) && $filter['bm_id'][0]['_ALL_']) {
            if (isset($_POST['filter']['advance']) && $_POST['filter']['advance']) {
                $arr_filters = explode(',', $_POST['filter']['advance']);
                foreach ($arr_filters as $obj_filter) {
                    $arr             = explode('=', $obj_filter);
                    $filter[$arr[0]] = $arr[1];
                }
                unset($_POST['filter']['advance']);
            }
        }

        if ($pro_bn) {
            $filter = array(
                'material_bn' => $pro_bn,
            );
        }
        if ($pro_name) {
            $filter = array(
                'material_name' => $pro_name,
            );
        }

        if ($pro_barcode) {
            //查询条形码对应的bm_id
            $bm_ids = $basicMaterialBarcode->getBmidListByBarcode($pro_barcode);
            $filter = array('bm_id' => $bm_ids);
        }

        $data = $basicMaterialSelect->getlist_ext('bm_id, material_bn, material_name, visibled, retail_price, cost, specifications, purchasing_price', $filter);

        if (!empty($data)) {
            foreach ($data as $k => $item) {
                #查询关联的条形码
                $item['barcode'] = $basicMaterialBarcode->getBarcodeById($item['product_id']);

                $item['num'] = 0;
                if ($supplier_id > 0) {
                    $item['price'] = app::get('purchase')->model('po')->getPurchsePriceBySupplierId($supplier_id, $item['product_id'], 'desc');

                    if (!$item['price']) {
                        $item['price'] = 0;
                    }
                } else {
                    $item['price'] = $item['cost']; #成本价
                }

                $item['price'] = sprintf('%.2f', $item['price']);

                #基础物料规格
                $item['spec_info'] = $item['specifications'];
                $item['purchasing_price'] = $item['purchasing_price'];

                $rows[] = $item;
            }
        }

        echo "window.autocompleter_json=" . json_encode($rows);
    }

    public function getOriginalPurchaseItems(){
        $oPo  = app::get('purchase')->model('po');
        $po_items = $oPo->getPoItemByPoId($_REQUEST['po_id']);
        $arr_return = [];
        $po_items = array_column($po_items,null,'product_id');
        foreach($_REQUEST['product_id'] as $bm_id){
            if(!empty($po_items[$bm_id])){
                $po_items[$bm_id]['max_nums'] = $po_items[$bm_id]['num'];
                $arr_return[] = $po_items[$bm_id];
            }
        }
        echo "window.autocompleter_json=" . json_encode($arr_return);
    }

    public function createPurchase($supplier_id, $branch_id, $bn)
    {

        // 商品查询参数
        if ($_POST['isSelectedAll'] == '_ALL_') {
            $product_ids = app::get('ome')->model('supply_product')->getList('*', $_POST, 0, -1);
            for ($i = 0; $i < sizeof($product_ids); $i++) {
                $product_id[] = $product_ids[$i]['product_id'];
            }
        } else {
            $product_id = $_POST['product_id'];
        }
        $product_id = array_map('intval', (array)$product_id);
        $this->pagedata['product_ids'] = implode(',', $product_id);

        // 获取供应商id
        $sql = "SELECT a.supplier_id FROM sdb_purchase_supplier_goods AS a
                LEFT JOIN sdb_material_basic_material AS b ON a.bm_id=b.bm_id
                WHERE b.bm_id IN (" . implode(',', $product_id) . ")";
        $rs = kernel::database()->selectrow($sql);
        if ($rs) {
            $supplier_id = $rs['supplier_id'];
        }

        $filter = array('supplier_id' => $supplier_id, 'branch_id' => $branch_id, 'bn' => $bn);

        $suObj = app::get('purchase')->model('supplier');
        $data  = $suObj->getList('supplier_id, name', '', 0, -1);

        $brObj = app::get('ome')->model('branch');
        $row   = $brObj->getList('branch_id, name', '', 0, -1);

        /*
         * 获取操作员管辖仓库
         */
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_list = $brObj->getBranchByUser();
        }
        $this->pagedata['branch_list'] = $branch_list;
        $is_super                      = 1;
        $this->pagedata['is_super']    = $is_super;

        //获取设置的采购方式
        $po_type = app::get('ome')->getConf('purchase.po_type');
        if (!$po_type) {
            $po_type = 'credit';
        }

        $this->pagedata['po_type'] = $po_type;

        $supplier                 = $suObj->dump($supplier_id, 'supplier_id,name,arrive_days');
        $filter                   = array('supplier_id' => $supplier_id, 'branch_id' => $branch_id, 'bn' => $bn);
        $this->pagedata['filter'] = $filter;

        $operator                   = kernel::single('desktop_user')->get_name();
        $this->pagedata['operator'] = $operator;
        $this->pagedata['supplier'] = $supplier;
        $this->pagedata['branchid'] = $branch_id;
        $this->pagedata['branch']   = $row;
        $this->pagedata['cur_date'] = date('Ymd', time()) . '采购单';
        $this->singlepage("admin/purchase/purchase_create.html");
    }

    public function need()
    {
        $supplierObj = app::get('purchase')->model('supplier');
        $data        = $supplierObj->getList('supplier_id,name', '', 0, -1);
        $data[]      = array('supplier_id' => '0', 'name' => '全部');

        $brObj = app::get('ome')->model('branch');
        $row   = $brObj->getList('branch_id, name', '', 0, -1);

        /*
         * 获取操作员管辖仓库
         */
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_list = $brObj->getBranchByUser();
        }
        $this->pagedata['branch_list'] = $branch_list;
        $is_super                      = 1;
        $this->pagedata['is_super']    = $is_super;

        $this->pagedata['supplier'] = $data;
        $this->pagedata['branch']   = $row;
        $this->pagedata['branchid'] = $row[0]['branch_id'];
        $this->page("admin/purchase/requirement.html");
    }

    public function getSafeStock($supplier_id, $branch_id, $bn, $product_ids)
    {
        $data['supplier_id'] = $supplier_id;
        $data['bn']          = $bn;
        $data['branch_id']   = $branch_id;
        $data['product_ids'] = $product_ids;

        $oPo  = app::get('purchase')->model('po');
        $data = $oPo->getSafeList($data);
        echo json_encode($data);
    }

    public function safeStockPreview($page = 1)
    {
        $data = utils::addslashes_array($_POST);
        //print_r($data);die;
        $page      = $page ? $page : 1;
        $pagelimit = 12;

        //读取仓库的货品信息
        $oPo = app::get('purchase')->model('po');

        $safe_data = $oPo->getSafeStock($data, $pagelimit * ($page - 1), $pagelimit);

        $count      = $safe_data['count'];
        $total_page = ceil($count / $pagelimit);
        $pager      = $this->ui()->pager(array(
            'current' => $page,
            'total'   => $total_page,
            'link'    => '?page=%d',
        ));

        $this->pagedata['pager'] = $pager;
        unset($safe_data['count']);
        $this->pagedata['data']       = $safe_data;
        $this->pagedata['total_page'] = $total_page;
        $this->pagedata['pagelimit']  = $pagelimit;
        $this->pagedata['count']      = $count;
        $this->pagedata['cur_page']   = $page;
        return $this->display("admin/inventory/safe_stock_div.html");
    }

    public function getEditProducts($po_id)
    {
        if ($po_id == '') {
            $po_id = $_POST['p[0]'];
        }

        $piObj = app::get('purchase')->model('po_items');

        $basicMaterialSelect = kernel::single('material_basic_select');

        $oPo = app::get('purchase')->model('po');
        $po_info = $oPo->dump($po_id,'accos_po_id,po_species');
        if($po_info['po_species'] == 2){
            $origin_items = $piObj->getList('product_id,num,price,barcode,bn,name,spec_info,status,out_num,in_num,purchasing_price', array('po_id' => $po_info['accos_po_id']), 0, -1);
            $origin_items = array_column($origin_items,null,'product_id');
        }


        $rows  = array();
        $items = $piObj->getList('product_id,num,price,barcode,bn,name,spec_info,status,out_num,in_num,purchasing_price', array('po_id' => $po_id), 0, -1);
        if ($items) {
            $product_ids = array();
            foreach ($items as $k => $v) {
                if ($v['status'] == '1' || ($v['in_num'] + $v['out_num']) <= 0) {
                    $items[$k]['delete'] = 1;
                } else {
                    $items[$k]['delete'] = 0;
                }
                $product_ids[]           = $v['product_id'];
                $items[$k]['visibility'] = &$product[$v['product_id']]['visibility'];
                if($po_info['po_species'] == 2){
                    $items[$k]['max_nums'] = $origin_items[$v['product_id']]['num'];
                }
                unset($items[$k]['status']);
                unset($items[$k]['out_num']);
                unset($items[$k]['in_num']);
            }
            if ($product_ids) {
                #基础物料
                $plist = $basicMaterialSelect->getlist('bm_id, visibled', array('bm_id' => $product_ids));

                foreach ($plist as $value) {
                    $product_id = $value['product_id'];
                    $visibility = ($value['visibility'] == 1 ? 'true' : 'false');

                    $product[$product_id]['visibility'] = $visibility;
                }
            }
        }
        $rows = $items;
        echo json_encode($rows);
    }

    /**
     * 保存采购单
     *
     */
    public function doSave()
    {
        $this->begin();
        $at        = $_POST['at'];
        $pr        = $_POST['pr'];
        $type      = $_POST['type'];
        $name      = $_POST['purchase_name'];
        $emergency = $_POST['emergency'];
        $supplier  = $_POST['supplier'];
        $branch    = $_POST['branch'];
        $price     = $_POST['price'];
        $memo      = $_POST['memo'];
        $arrive    = $_POST['arrive_days'];
        $operator  = $_POST['operator'];
        $d_cost    = $_POST['d_cost'];

        $total = 0;

        if ($at) {
            foreach ($at as $k => $a) {
                $ids[]  = $k;
                $pr[$k] = number_format($pr[$k], 3, '.', '');

                $pt = bcmul(number_format($a, 3, '.', ''), $pr[$k], 3);
            }
        }

        //判断供应商是否存在
        $oSupplier = app::get('purchase')->model('supplier');
        $supplier_ = $oSupplier->dump(array('name' => $supplier), 'supplier_id');

        $poObj = app::get('purchase')->model('po');

        $data['supplier_id'] = $supplier_['supplier_id'];
        $data['operator']    = $operator; //kernel::single('desktop_user')->get_name();

        #采购单创建人
        $data['op_name']         = kernel::single('desktop_user')->get_name();
        $data['po_type']         = $type;
        $data['name']            = $name;
        $data['emergency']       = $emergency;
        $data['purchase_time']   = time();
        $data['branch_id']       = $branch;
        $data['arrive_time']     = $arrive;
        $data['deposit']         = $type == 'cash' ? 0 : $price;
        $data['deposit_balance'] = $type == 'cash' ? 0 : $price; #预付款
        $data['amount']          = $type == 'cash' ? bcadd($total, $d_cost, 3) : $total;
        $data['product_cost']    = $total;
        $data['delivery_cost']   = $d_cost;
        $data['po_species']      = 1;

        if ($memo) {
            $op_name = kernel::single('desktop_user')->get_login_name();
            $newmemo = array();
            $newmemo = array('op_name' => $op_name, 'op_time' => date('Y-m-d H:i', time()), 'op_content' => $memo);
        }
        $data['memo'] = $newmemo;

        $po_itemObj       = app::get('purchase')->model('po_items');
        $basicMaterialObj = app::get('material')->model('basic_material');

        if ($ids) {
            foreach ($ids as $i) {
//插入采购单详情
                $p = $basicMaterialObj->dump($i, 'bm_id, material_name, material_bn');

                $row['nums']     = $at[$i];
                $row['price']    = $pr[$i];
                $row['bn']       = $p['material_bn'];
                $row['name']     = $p['material_name'];
                $data['items'][] = $row;
                $row             = null;
            }
        }

        $rs = $poObj->savePo($data);

        if ($rs['status'] == 'success') {
            //--生成采购单日志记录
            $log_msg = '生成了编号为:' . $data['po_bn'] . '的采购单';
            $opObj   = app::get('ome')->model('operation_log');
            $opObj->write_log('purchase_create@purchase', $data['po_id'], $log_msg);
            $this->end(true, '已完成');
        } else {
            $this->end(false, $rs['msg'] != '' ? $rs['msg'] : '未完成', 'index.php?app=console&ctl=admin_purchase&act=add');
        }
    }

    /**
     * 采购补货单入库操作
     *
     * author : Joe
     * Date : 2022-01-26 15:32
     */
    public function doSaveReplenishment()
    {
        $this->begin();
        $oPo             = app::get('purchase')->model('po');
        $po_origin       = $oPo->dump($_REQUEST['origin_po_id'], '*');
        $po_origin_items = $oPo->getPoItemByPoId($_REQUEST['origin_po_id']);
        $po_origin_items = array_column($po_origin_items, null, 'product_id');
        $arr_items       = [];
        $product_price   = 0;
        if ($_POST['at']) {
            foreach ($_POST['at'] as $k => $a) {
                if ($po_origin_items[$k]) {
                    unset($po_origin_items[$k]['item_id']);
                    unset($po_origin_items[$k]['po_id']);
                    unset($po_origin_items[$k]['status']);
                    unset($po_origin_items[$k]['in_num']);
                    unset($po_origin_items[$k]['out_num']);
                    if ($a <= $po_origin_items[$k]['num']) {
                        $po_origin_items[$k]['nums'] = $a;
                    } else {
                        $po_origin_items[$k]['nums'] = $po_origin_items[$k]['num'];
                    }
                    unset($po_origin_items[$k]['num']);
                    $product_price += bcmul(number_format($po_origin_items[$k]['nums'], 3, '.', ''), $po_origin_items[$k]['price'], 3);
                    $arr_items[]   = $po_origin_items[$k];
                }
            }
        }

        //采购补货单
        $data = [
            'name'            => $_REQUEST['purchase_name'],
            'supplier_id'     => $po_origin['supplier_id'],
            'branch_id'       => $po_origin['branch_id'],
            'deposit_balance' => $po_origin['deposit_balance'],
            'deposit'         => $po_origin['deposit'],
            'po_type'         => $po_origin['po_type'],
            'arrive_time'     => $_REQUEST['arrive_days'],
            'delivery_cost'   => $_REQUEST['d_cost'],
            'operator'        => $_REQUEST['operator'],
            'accos_po_id'     => $po_origin['po_id'],
            'accos_po_bn'     => $po_origin['po_bn'],
            'memo'            => empty($_REQUEST['memo']) ? '' : ['op_content' => $_REQUEST['memo']],
            'product_cost'    => $product_price,
            'purchase_time'   => time(),
            'po_species'      => 2,
            'op_name'         => kernel::single('desktop_user')->get_name(),
            'emergency'       => empty($_REQUEST['emergency']) ? 'false' : $_REQUEST['emergency'],
            'items'           => $arr_items,
        ];

        $rs = $oPo->savePo($data);

        if ($rs['status'] == 'success') {
            //--生成采购单日志记录
            $log_msg = '通过原采购订单【' . $po_origin['po_bn'] . '】生成了编号为:' . $data['po_bn'] . '的采购补货单';
            $opObj   = app::get('ome')->model('operation_log');
            $opObj->write_log('purchase_create@purchase', $data['po_id'], $log_msg);
            $this->end(true, '已完成');
        } else {
            $this->end(false, $rs['msg'] != '' ? $rs['msg'] : '未完成', 'index.php?app=console&ctl=admin_purchase&act=add');
        }
    }

    /**
     * 修改采购单
     *
     */
    public function editPo($po_id)
    {
        $this->begin('index.php?app=console&ctl=admin_purchase&act=index');
        if (empty($po_id)) {
            $this->end(false, '操作出错，请重新操作');
        }

        $poObj = app::get('purchase')->model('po');
        $suObj = app::get('purchase')->model('supplier');
        $brObj = app::get('ome')->model('branch');

        $data = $poObj->dump($po_id, '*', array('po_items' => array('*')));

        //当前供应商
        $supplier_detail                   = $suObj->dump($data['supplier_id'], 'supplier_id,name');
        $this->pagedata['supplier_detail'] = $supplier_detail;
        
        $this->pagedata['branch_mode'] = 'single';

        $su                    = $suObj->dump($data['supplier_id'], 'name');
        $br                    = $brObj->dump($data['branch_id'], 'name');
        $data['branch_name']   = $br['name'];
        $data['supplier_name'] = $su['name'];
        //到货天数
        $diff_time                  = $data['arrive_time'] - $data['purchase_time'];
        $data['diff_days']          = floor($diff_time / (24 * 60 * 60));
        $this->pagedata['po_items'] = $data['po_items'];
        $data['memo']               = unserialize($data['memo']);
        if ($data['memo']) {
            foreach ($data['memo'] as $key => $v) {
                $str = unserialize($v['op_content']);
                if ($str) {
                    $data['memo'][$key]['op_content'] = $str[0]['op_content'];
                } else {
                    $data['memo'][$key]['op_content'] = $v['op_content'];
                }
            }
        }

        $this->pagedata['po'] = $data;
        $this->singlepage("admin/purchase/purchase_edit.html");
    }

    /**
     * 修改采购补货单页面
     *
     * @param $po_id
     * author : Joe
     * Date : 2022-01-27 10:42
     */
    public function editPoReplenishment($po_id)
    {
        $this->begin('index.php?app=console&ctl=admin_purchase&act=index');
        if (empty($po_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        $poObj        = app::get('purchase')->model('po');
        $suObj        = app::get('purchase')->model('supplier');
        $brObj        = app::get('ome')->model('branch');
        $data         = $poObj->dump($po_id, '*', array('po_items' => array('*')));
        $origin_data  = $poObj->dump($data['accos_po_id'], '*', array('po_items' => array('*')));
        $data['memo'] = unserialize($data['memo']);
        if ($data['memo']) {
            foreach ($data['memo'] as $key => $v) {
                $str = unserialize($v['op_content']);
                if ($str) {
                    $data['memo'][$key]['op_content'] = $str[0]['op_content'];
                } else {
                    $data['memo'][$key]['op_content'] = $v['op_content'];
                }
            }
        }

        //当前供应商
        $supplier_detail = $suObj->dump($data['supplier_id'], 'supplier_id,name');
        $branch_info     = $brObj->dump($data['branch_id'], 'name,branch_id');

        //到货天数
        $diff_time             = $data['arrive_time'] - $data['purchase_time'];
        $data['diff_days']     = floor($diff_time / (24 * 60 * 60));
        $data['branch_name']   = $branch_info['name'];
        $data['supplier_name'] = $supplier_detail['name'];

        $this->pagedata['po']              = $data;
        $this->pagedata['origin_po']       = $origin_data;
        $this->pagedata['branch_mode']     = 'single';
        $this->pagedata['po_items']        = $data['po_items'];
        $this->pagedata['supplier_detail'] = $supplier_detail;
        $this->singlepage("admin/purchase/purchase_replenishment_edit.html");
    }

    public function doEdit()
    {
        $this->begin();
        $po_id      = $_POST['po_id'];
        $poObj      = app::get('purchase')->model('po');
        $po_itemObj = app::get('purchase')->model('po_items');
        $payObj     = app::get('purchase')->model('purchase_payments');
        $data       = $poObj->dump($po_id, '*', array('po_items' => array('*')));

        if ($data['eo_status'] == '3') {
            $this->end(false, '此采购单已完成入库，不允许修改', 'index.php?app=console&ctl=admin_purchase&act=editPo');
        }
        if ($data['eo_status'] == '4') {
            $this->end(false, '此采购单已取消入库，不允许修改', 'index.php?app=console&ctl=admin_purchase&act=editPo');
        }
        if ($data['statement'] == '3') {
            $this->end(false, '此采购单已结算，不允许修改', 'index.php?app=console&ctl=admin_purchase&act=editPo');
        }
        if ($data['check_status'] == 2) {
            $this->end(false, '此采购单已审核，不允许修改', 'index.php?app=console&ctl=admin_purchase&act=editPo');
        }
        $at      = $_POST['at'];
        $pr      = $_POST['pr'];
        $d_cost  = $_POST['d_cost'];
        $deposit = $_POST['price'];
        $total   = 0;
        if (empty($at) || empty($pr)) {
            $this->end(false, '采购单中必须有商品', 'index.php?app=console&ctl=admin_purchase&act=editPo');
        }

        foreach ($data['po_items'] as $v) {
            $p_id = $v['product_id'];
            if (empty($at[$p_id])) {
                if ($v['status'] != 1) {
                    $this->end(false, $v['bn'] . ':已入库，不能删除', 'index.php?app=console&ctl=admin_purchase&act=editPo');
                }
                $del_item_id[] = $v;
            }
        }

        if ($at) {
            foreach ($at as $k => $a) {
                if (!is_numeric($a) || $a < 1) {
                    $this->end(false, '采购数量必须为数字且大于0', 'index.php?app=console&ctl=admin_purchase&act=editPo');
                }
                if (!is_numeric($pr[$k]) || $pr[$k] < 0) {
                    $this->end(false, '单价必须为数字且大于0', 'index.php?app=console&ctl=admin_purchase&act=editPo');
                }
                $pi = $po_itemObj->dump(array('po_id' => $po_id, 'product_id' => $k));
                if ($pi) {
                    if ($a < ($pi['out_num'] + $pi['in_num'])) {
                        $this->end(false, $pi['bn'] . ':数量不能小于已入库数量', 'index.php?app=console&ctl=admin_purchase&act=editPo');
                    }
                    $edit_pi[$k]['item_id'] = $pi['item_id'];
                }
                $edit_pi[$k]['num']   = $a;
                $edit_pi[$k]['price'] = $pr[$k];
                $ids[]                = $k;
                $total += $a * $pr[$k];
            }
        }

        if ($data['po_type'] == 'credit') {
            if ($deposit > ($total + $d_cost)) {
                $this->end(false, '预付款不能大于总金额', 'index.php?app=console&ctl=admin_purchase&act=editPo');
            }
        }

        if ($data['check_status'] == 2) {
            $filter['po_id']   = $po_id;
            $filter['po_type'] = $data['po_type'];
            $pay               = $payObj->dump($filter);
        }

        $memo    = array();
        $oldmemo = unserialize($data['memo']);
        if ($oldmemo) {
            foreach ($oldmemo as $k => $v) {
                $memo[] = $v;
            }
        }
        $newmemo = htmlspecialchars($_POST['memo']);
        if ($newmemo) {
            $op_name = kernel::single('desktop_user')->get_name();
            $memo[]  = array('op_name' => $op_name, 'op_time' => date('Y-m-d H:i', time()), 'op_content' => $newmemo);
        }
        $edit_memo = serialize($memo);

        $poo                = array();
        $poo['po_id']       = $po_id;
        $poo['name']        = $_POST['purchase_name'];
        $poo['emergency']   = $_POST['emergency'];
        $poo['operator']    = $_POST['operator'];
        $poo['memo']        = $edit_memo;
        
        //预计到货天数
        $_POST['arrive_days'] = intval($_POST['arrive_days']);
        $poo['arrive_time'] = ($_POST['arrive_days'] * 24 * 60 * 60) + time();

        if ($data['check_status'] == 2) {
            foreach ($data['po_items'] as $v) {
                if (in_array($v['product_id'], $ids)) {
                    //$poObj->updateBranchProductArriveStore($data['branch_id'], $v['product_id'], $v['num'], '-');
                }
            }
        }

        if ($del_item_id) {
            foreach ($del_item_id as $item) {
                $po_itemObj->delete(array('item_id' => $item['item_id']));
            }
        }

        if ($ids) {
            $basicMaterialLib     = kernel::single('material_basic_material');
            $basicMaterialBarcode = kernel::single('material_basic_material_barcode');

            foreach ($ids as $i) {
                $p = $basicMaterialLib->getBasicMaterialExt($i);

                $row               = $edit_pi[$i];
                $row['barcode']    = $p['barcode'];
                $row['po_id']      = $po_id;
                $row['product_id'] = $i;
                $row['price']      = $pr[$i];
                $row['status']     = '1';
                $row['bn']         = $p['material_bn'];
                $row['name']       = $p['material_name'];
                $row['spec_info']  = $p['spec_info'];
                $row['purchasing_price'] = $p['purchasing_price'];

                $po_itemObj->save($row);
                $row = null;

                if ($data['check_status'] == 2) {
                    //$poObj->updateBranchProductArriveStore($data['branch_id'], $i, $edit_pi[$i]['num'], '+');
                }
            }
        }

        if ($data['po_type'] == 'cash') {
            if ($data['check_status'] == 2) {
                $row['payment_id']    = $pay['payment_id'];
                $row['payable']       = $total + $d_cost;
                $row['deposit']       = $total;
                $row['product_cost']  = $total;
                $row['delivery_cost'] = $d_cost;
            }
            $poo['amount']  = $total + $d_cost;
            $poo['deposit'] = 0;
        } elseif ($data['po_type'] == 'credit') {
            $credit_sObj = app::get('purchase')->model('credit_sheet');
            if ($data['check_status'] == 2) {
                $row['payment_id']      = $pay['payment_id'];
                $row['payable']         = $deposit;
                $row['deposit']         = $deposit;
                $row['deposit_balance'] = $deposit; #预付款初始化
                $row['product_cost']    = 0;
                $row['delivery_cost']   = 0;
            }
            $poo['amount']  = $total;
            $poo['deposit'] = empty($deposit) ? $data['deposit'] : $deposit;
        }

        $poo['delivery_cost'] = $d_cost;
        $poo['product_cost']  = $total;
        $re                   = $poObj->ExistFinishPurchase($po_id);
        if ($re) {
            $poo['eo_status'] = '3';
            if ($data['po_status'] == '1') {
                $poo['po_status'] = '4';
            }
        }

        if ($data['check_status'] == 2) {
            $payObj->save($row);
        }
        $poObj->save($poo);

        $eoObj   = app::get('purchase')->model('eo');
        $eo_iObj = app::get('purchase')->model('eo_items');
        $eos     = $eoObj->getList('eo_id', array('po_id' => $po_id), 0, -1);
        if ($eos) {
            foreach ($eos as $it) {
                $tmp_num = 0;
                if ($data['po_type'] == 'credit') {
                    $cs = $credit_sObj->dump(array('eo_id' => $it['eo_id']));
                    if ($cs['statement_status'] == 2) {
                        continue;
                    }

                }
                $eoi = $eo_iObj->getList('*', array('eo_id' => $it['eo_id']), 0, -1);
                if ($eoi) {
                    foreach ($eoi as $ei) {
                        $num   = $ei['entry_num'] - $ei['out_num'];
                        $price = $pr[$ei['product_id']];
                        $tmp_num += $num * $price;

                        $eoii['item_id']      = $ei['item_id'];
                        $eoii['purchase_num'] = $at[$ei['product_id']];
                        $eo_iObj->save($eoii);
                        $eoii = null;
                    }
                }
                $eoo['eo_id']  = $it['eo_id'];
                $eoo['amount'] = $tmp_num;

                if ($data['po_type'] == 'credit') {
                    $eoo['amount']  = $tmp_num + $d_cost;
                    $css['cs_id']   = $cs['cs_id'];
                    $css['payable'] = $tmp_num + $d_cost;
                    $credit_sObj->save($css);
                }
                $eoObj->save($eoo);
            }
        }

        $log_msg = '修改了编号为:' . $data['po_bn'] . '的采购单';
        $opObj   = app::get('ome')->model('operation_log');
        $opObj->write_log('purchase_modify@purchase', $po_id, $log_msg);
        $this->end(true, '已完成');
    }

    /**
     * 保存详情
     *
     */
    public function doDetail()
    {
        $this->begin('index.php?app=console&ctl=admin_purchase');
        if (empty($_POST['id'])) {
            $this->end(false, '操作出错，请重新操作');
        }
        if ($_POST['memo'] == '') {
            $this->end(true, '操作完成');
        }

        $poObj       = app::get('purchase')->model('po');
        $po['po_id'] = $_POST['id'];
        $po['memo']  = $_POST['oldmemo'] . '<br/>' . $_POST['memo'] . '  &nbsp;&nbsp;(' . date('Y-m-d H:i', time()) . ' by ' . kernel::single('desktop_user')->get_name() . ')';

        $poObj->save($po);
        $this->end(true, '操作成功');
    }

    /*
     * 追加备注 append_memo
     */
    public function append_memo()
    {

        $poObj       = app::get('purchase')->model('po');
        $po['po_id'] = $_POST['id'];
        if ($_POST['oldmemo']) {
            $oldmemo = $_POST['oldmemo'] . '<br/>';
        }
        $memo       = $oldmemo . $_POST['memo'] . '  &nbsp;&nbsp;(' . date('Y-m-d H:i', time()) . ' by ' . kernel::single('desktop_user')->get_name() . ')';
        $po['memo'] = $memo;
        $poObj->save($po);
        echo $memo;
    }

    /**
     * 打印采购单
     *
     * @param int $po_id
     */
    public function printItem($po_id, $type = 'po')
    {
        $poObj = app::get('purchase')->model('po');
        $suObj = app::get('purchase')->model('supplier');
        $brObj = app::get('ome')->model('branch');

        $basicMaterialLib = kernel::single('material_basic_material');
        $goodsObj         = app::get('ome')->model('goods');
        $poo              = $poObj->dump($po_id, '*', array('po_items' => array('*')));

        #检测货号、规格、商品名是否变化
        foreach ($poo['po_items'] as $key => $product_items) {
            $goodsInfo = array();

            $last_product_info = $basicMaterialLib->getBasicMaterialExt($product_items['product_id']);

            if ($goodsInfo) {
                $product_items['goods_bn'] = $goodsInfo[0]['bn'];
                $product_items['unit']     = $goodsInfo[0]['unit'];
            } else {
                $product_items['goods_bn'] = '';
                $product_items['unit']     = '';
            }

            #检测货号是否变化
            if (strcasecmp($product_items['bn'], $last_product_info['material_bn']) != 0) {
                $product_items['bn'] = $product_items['bn'] . '(' . $last_product_info['bn'] . ')';
            }
            #检测规格是否变化
            if (strcasecmp($product_items['spec_info'], $last_product_info['spec_info']) != 0) {
                if (empty($product_items['spec_info'])) {
                    #如果原来没有规格值，则直接显示该商品最新的规格值
                    $product_items['spec_info'] = $last_product_info['spec_info'];
                } else {
                    $product_items['spec_info'] = $product_items['spec_info'] . '(' . $last_product_info['spec_info'] . ')';
                }
            }
            #检测商品名称是否变化
            if (strcasecmp($product_items['name'], $last_product_info['material_name']) != 0) {
                $product_items['name'] = $product_items['name'] . '(' . $last_product_info['material_name'] . ')';
            }
            $poo['po_items'][$key] = $product_items;
        }

        $su   = $suObj->dump($poo['supplier_id'], 'name');
        $bran = $brObj->dump($poo['branch_id'], 'name');

        $this->pagedata['type']     = $type;
        $poo['supplier']            = $su['name'];
        $poo['branch']              = $bran['name'];
        $poo['memo']                = unserialize($poo['memo']);
        $this->pagedata['po']       = $poo;
        $this->pagedata['time']     = time();
        $this->pagedata['base_dir'] = kernel::base_url();

        # 改用新打印模板机制 chenping
        kernel::single('ome_print_otmpl')->printOTmpl($_GET['otmplId'], 'purchase', $this);
    }

    public function cancel($po_id, $type = 'confirm')
    {

        //获取采购单供应商经办人/负责人
        $oPo       = app::get('purchase')->model('po');
        $po        = $oPo->dump($po_id, 'supplier_id');
        $oSupplier = app::get('purchase')->model('supplier');
        $supplier  = $oSupplier->dump($po['supplier_id'], 'operator');
        //if (!$supplier['operator']) $supplier['operator'] = '未知';

        $this->pagedata['operator'] = kernel::single('desktop_user')->get_name();

        $this->pagedata['type'] = $type;
        $this->pagedata['id']   = $po_id;
        $this->display("admin/purchase/purchase_cancel.html");
    }

    public function addSame($po_id)
    {
        $this->begin('index.php?app=console&ctl=admin_purchase&act=index');
        if (empty($po_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        $suObj = app::get('purchase')->model('supplier');
        $supp  = $suObj->getList('supplier_id, name', array(), 0, -1);

        $brObj = app::get('ome')->model('branch');
        $row   = $brObj->getList('branch_id, name', array('type' => 'main', 'b_type' => 1), 0, -1);

        $poObj = app::get('purchase')->model('po');

        $data = $poObj->dump($po_id, '*', array('po_items' => array('*')));

        //当前供应商
        $supplier_detail = $suObj->dump($data['supplier_id'], 'supplier_id,name,arrive_days');

        //获取设置的采购方式
        $po_type = app::get('ome')->getConf('purchase.po_type');
        if (!$po_type) {
            $po_type = 'credit';
        }

        $this->pagedata['po_type'] = $po_type;

        //获取操作员管辖仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $iostockObj  = kernel::single('console_iostockdata');
            $branch_list = $iostockObj->getBranchByUser();
        }
        $this->pagedata['branch_list'] = $branch_list;
        $is_super                      = 1;
        $this->pagedata['is_super']    = $is_super;

        $diff_time         = $data['arrive_time'] - $data['purchase_time'];
        $data['diff_days'] = floor($diff_time / (24 * 60 * 60)); //print_r($data);die;

        $this->pagedata['branch_mode']     = 'single';
        $this->pagedata['supplier']        = $supp;
        $this->pagedata['supplier_detail'] = $supplier_detail;
        $this->pagedata['branch']          = $row;
        $this->pagedata['po_items']        = $data['po_items'];


        if($data['po_species'] == '1'){
            $this->pagedata['po']              = $data;
            $this->pagedata['cur_date']        = date('Ymd', time()) . '采购单';
            $this->singlepage("admin/purchase/purchase_addsame.html");
        }else{

            $origin_data  = $poObj->dump($data['accos_po_id'], '*', array('po_items' => array('*')));
            $this->pagedata['origin_po']       = $origin_data;
            $branch_info     = $brObj->dump($data['branch_id'], 'name,branch_id');
            $data['branch_name']   = $branch_info['name'];
            $this->pagedata['cur_date']        = date('Ymd', time()) . '采购补货单';
            $this->pagedata['po']              = $data;

            $this->singlepage("admin/purchase/purchase_replenishment_addsame.html");
        }

    }

    public function doSame()
    {
        $this->begin('index.php?app=console&ctl=admin_purchase&act=index');
        $at        = $_POST['at'];
        $pr        = $_POST['pr'];
        $type      = $_POST['type'];
        $name      = $_POST['purchase_name'];
        $emergency = $_POST['emergency'];
        $supplier  = $_POST['supplier'];
        $branch    = $_POST['branch'];
        $price     = $_POST['price'];
        $memo      = $_POST['memo'];
        
        $_POST['arrive_days'] = intval($_POST['arrive_days']);
        $arrive    = ($_POST['arrive_days'] * 24 * 60 * 60) + time();
        $operator  = $_POST['operator'];
        $d_cost    = $_POST['d_cost'];
        $total     = 0;

        if (empty($at) || empty($pr)) {
            $this->end(false, '采购单中必须有商品', 'index.php?app=console&ctl=admin_purchase&act=add');
        }
        if ($at) {
            foreach ($at as $k => $a) {
                if (!is_numeric($a) || $a < 1) {
                    $this->end(false, '采购数量必须为数字且大于0', 'index.php?app=console&ctl=admin_purchase&act=add');
                }
                if (!is_numeric($pr[$k]) || $pr[$k] < 0) {
                    $this->end(false, '单价必须为数字且大于0', 'index.php?app=console&ctl=admin_purchase&act=add');
                }
                $ids[] = $k;
                $total += $a * $pr[$k];
            }
        }

        if ($type == 'credit') {
            if ($_POST['price'] == '') {
                $price = 0;
            }
        }

        if ($branch == '') {
            $this->end(false, '请选择仓库', 'index.php?app=console&ctl=admin_purchase&act=add');
        }
        if ($price != '' && !is_numeric($price)) {
            $this->end(false, '预付款必须为数字', 'index.php?app=console&ctl=admin_purchase&act=add');
        }
        if ($price > $total) {
            $this->end(false, '预付款金额不得大于商品总额', 'index.php?app=console&ctl=admin_purchase&act=add');
        }
        //判断供应商是否存在
        $oSupplier = app::get('purchase')->model('supplier');
        $supplier  = $oSupplier->dump(array('name' => $supplier), 'supplier_id');
        if (!$supplier['supplier_id']) {
            $this->end(false, '输入的供应商不存在！', 'index.php?app=console&ctl=admin_purchase&act=add');
        }
        $supplier = $_POST['supplier_id'];
        if ($arrive == '') {
            $this->end(false, '请输入预计到货天数', 'index.php?app=console&ctl=admin_purchase&act=add');
        }
        $poObj                   = app::get('purchase')->model('po');
        $po_bn                   = $poObj->gen_id();
        $data['po_bn']           = $po_bn;
        $data['supplier_id']     = $supplier;
        $data['operator']        = $operator; //kernel::single('desktop_user')->get_name();
        $data['po_type']         = $type;
        $data['name']            = $name;
        $data['emergency']       = $emergency;
        $data['purchase_time']   = time();
        $data['branch_id']       = $branch;
        $data['arrive_time']     = $arrive;
        $data['deposit']         = $type == 'cash' ? 0 : $price;
        $data['deposit_balance'] = $type == 'cash' ? 0 : $price;

        $data['amount']        = $type == 'cash' ? bcadd($total, $d_cost, 3) : $total;
        $data['delivery_cost'] = $d_cost;
        $data['product_cost']  = $total;
        if ($memo) {
            $op_name   = kernel::single('desktop_user')->get_login_name();
            $newmemo   = array();
            $newmemo[] = array('op_name' => $op_name, 'op_time' => date('Y-m-d H:i', time()), 'op_content' => $memo);
        }
        $data['memo'] = serialize($newmemo);

        $rs = $poObj->save($data);
        if ($rs) {
            $po_id      = $data['po_id'];
            $po_itemObj = app::get('purchase')->model('po_items');

            $basicMaterialLib = kernel::single('material_basic_material');

            if ($ids) {
                foreach ($ids as $i) {
//插入采购单详情

                    $p = $basicMaterialLib->getBasicMaterialExt($i);

                    $row['barcode']    = $p['barcode'];
                    $row['po_id']      = $po_id;
                    $row['product_id'] = $i;
                    $row['num']        = $at[$i];
                    $row['in_num']     = 0;
                    $row['out_num']    = 0;
                    $row['price']      = $pr[$i];
                    $row['status']     = '1';
                    $row['bn']         = $p['material_bn'];
                    $row['name']       = $p['material_name'];
                    $row['spec_info']  = $p['spec_info'];
                    $row['purchasing_price']  = $p['purchasing_price'];

                    $po_itemObj->save($row);
                    $row = null;
                }
            }
            //--生成采购单日志记录
            $payment_log = $type == 'cash' ? '现款' : '预付款';
            $log_msg     = '生成了编号为:' . $po_bn . '的采购单';
            $opObj       = app::get('ome')->model('operation_log');
            $opObj->write_log('purchase_create@purchase', $po_id, $log_msg);

            $this->end(true, '已完成');
        }
        $this->end(true, '未完成', 'index.php?app=console&ctl=admin_purchase&act=add');
    }

    /**
     *根据条码查询商品详情
     */
    public function getProduct()
    {
        $basicMaterialSelect  = kernel::single('material_basic_select');
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');

        $pro_barcode = trim($_POST['barcode']);
        $supplier_id = $_POST['supplier_id'];

        //基础物料关联条码
        if ($pro_barcode) {
            //查询条形码对应的bm_id
            $bm_ids = $basicMaterialBarcode->getBmidListByBarcode($pro_barcode);
            if (empty($bm_ids)) {
                return '';
            }
            $filter = array('bm_id' => $bm_ids);

            $data = $basicMaterialSelect->getlist_ext('bm_id, material_bn, material_name, visibled, retail_price, cost, specifications', $filter);

            if (!empty($data)) {
                foreach ($data as $k => $item) {
                    $item['num'] = 1;
                    if ($supplier_id > 0) {
                        $item['price'] = app::get('purchase')->model('po')->getPurchsePriceBySupplierId($supplier_id, $item['product_id'], 'desc');
                        if (!$item['price']) {
                            $item['price'] = 0;
                        }
                    } else {
                        $item['price'] = $item['cost'];
                    }

                    #查询关联的条形码
                    $item['barcode'] = $basicMaterialBarcode->getBarcodeById($item['product_id']);

                    $rows[] = $item;
                }
                echo json_encode($rows);
            }
        }
    }

    /**
     * 入库确认
     *
     */
    public function purchaseConfirm($po_id)
    {
        $poObj       = app::get('purchase')->model('po');
        $eoObj       = app::get('purchase')->model('eo');
        $suObj       = app::get('purchase')->model('supplier');
        $brObj       = app::get('ome')->model('branch');
        $po_itemsObj = app::get('purchase')->model('po_items');
        $po_items    = $po_itemsObj->getList('*', array('po_id' => $po_id, 'defective_num|than' => '0'), 0, -1);

        $po                   = array();
        $po['po_id']          = $po_id;
        $po['po_items']       = $po_items;
        $this->pagedata['po'] = $po;
        $this->singlepage('admin/purchase/purchase_confirm.html');

    }

    /**
     * 执行采购入库确认
     */
    public function doPurchaseconfirm()
    {
        $this->begin();

        $po_id      = $_POST['po_id'];
        $Opo        = app::get('purchase')->model('po');
        $iostockObj = kernel::single('console_iostockdata');

        $aRow = $Opo->dump($po_id, '*', array('po_items' => array('defective_num,num,bn,product_id,price,name')));

        if ($aRow['defective_status'] != '1') {
            $this->end(false, '此单据已确认或无需确认!');
        }
        $damagedbranch = $iostockObj->getDamagedbranch($aRow['branch_id']);
        if (empty($damagedbranch)) {

            $this->end(false, $aRow['bn'] . '有不良品，但未设置主仓对应的残仓');
        }

        $iostock_data = array();
        foreach ($aRow['po_items'] as $items) {

            if ($items['defective_num'] > 0) {
                $items_data[] = array(
                    'nums'         => $items['defective_num'],
                    'bn'           => $items['bn'],
                    'price'        => $items['price'],
                    'product_id'   => $items['product_id'],
                    'purchase_num' => $items['num'],
                    'product_name' => $items['name'],
                );
            }
        }
        $iostock_data['items'] = $items_data;
        if (count($items_data) > 0) {
            $result = kernel::single('console_receipt_purchase')->save_eo($po_id, 'defective', $iostock_data);
            if ($result) {
                #更新确认状态
                $io_update_data = array(
                    'defective_status' => '2',
                );
                $Opo->update($io_update_data, array('po_id' => $po_id));
                $this->end(true, '确认成功');
            } else {

                $this->end(false, '确认失败');
            }
        } else {
            $this->end(false, '没有需要确认的货品!');
        }

    }

    /**
     * 差异查看
     */
    public function difference($po_id)
    {
        $poObj     = app::get('purchase')->model('po');
        $eoObj     = app::get('purchase')->model('eo');
        $suObj     = app::get('purchase')->model('supplier');
        $brObj     = app::get('ome')->model('branch');
        $po        = $poObj->dump($po_id, '*', array('po_items' => array('*')));
        $eos       = $eoObj->getList('eo_id,eo_bn,entry_time', array('po_id' => $po_id), 0, -1);
        $total_num = 0;
        if ($po['po_items']) {
            foreach ($po['po_items'] as $k => $v) {
                $total_num += $v['num'];
            }
        }

        $su                        = $suObj->dump($po['supplier_id'], 'name');
        $br                        = $brObj->dump($po['branch_id'], 'name');
        $po['branch_name']         = $br['name'];
        $po['supplier_name']       = $su['name'];
        $po['total_num']           = $total_num;
        $po['memo']                = unserialize($po['memo']);
        $this->pagedata['po']      = $po;
        $this->pagedata['eo']      = count($eos);
        $this->pagedata['emslist'] = $eos;
        $this->pagedata['count']   = count($eos);
        //物流费用计算
        $this->pagedata['delivery_cost'] = count($eos) * $po['delivery_cost'];
        $this->singlepage('admin/purchase/purchase_difference.html');
    }

    /*
     * 供应商查找
     */
    public function getSupplier()
    {

        $name = $_GET['name'];
        if ($name) {
            $supplier = app::get('purchase')->model('supplier');
            $data     = $supplier->getSupplier($name);

            echo "window.autocompleter_json=" . json_encode($data);
        }
    }

    /*
     * 供应商查找 by id
     */
    public function getSupplierById()
    {

        $supplier_id = $_POST['id'];
        if ($supplier_id) {
            $supplier = app::get('purchase')->model('supplier');
            $data     = $supplier->dump(array('supplier_id' => $supplier_id), 'supplier_id,name');

            //echo json_encode($data);
            echo "{id:'" . $data['supplier_id'] . "',name:'" . $data['name'] . "'}";
        }
    }

    public function doRefund()
    {
        $po_id = $_POST['po_id'];
        $memo  = $_POST['memo'];
        if (!$_POST['memo_flag']) {
            $memo = '';
        }

        $operator = $_POST['operator'];
        $this->begin('index.php?app=console&ctl=admin_purchase&act=index');
        if (empty($po_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        if ($operator == '') {
            $this->end(false, '操作出错，请重新操作');
        }
        $poObj = app::get('purchase')->model('po');
        $po    = $poObj->dump($po_id, '*', array('po_items' => array('*')));
        #查看状态是否已审核
        $check_status = $po['check_status'];
        if ($po['eo_status'] < 3) {
            //TODO 一期为取消所有未入库的商品，以后会通过POST数据进行入库取消
            //生成退货单与退货明细
            $po_itemObj = app::get('purchase')->model('po_items');
            $returnObj  = app::get('purchase')->model('returned_purchase');
            $paymentObj = app::get('purchase')->model('purchase_payments');
            $refundObj  = app::get('purchase')->model('purchase_refunds');
            $rp_itemObj = app::get('purchase')->model('returned_purchase_items');

            $return_flag = false; //无任何操作时，不生成退款单标志
            $pay         = $paymentObj->dump(array('po_id' => $po_id), '*');
            if ($po['eo_status'] == '1' && $pay['statement_status'] != '2') {
//没有入库并且没有结算付款单

                $return_flag = true;
                if ($pay['payment_id']) {
                    $paym['payment_id']       = $pay['payment_id'];
                    $paym['statement_status'] = '3';
                    $paymentObj->save($paym);
                }
            }

            //如果采购单已入库或者付款单已结算，生成退货单与退款单
            $return['supplier_id']   = $po['supplier_id'];
            $return['operator']      = $operator; //kernel::single('desktop_user')->get_name();
            $return['po_type']       = $po['po_type'];
            $return['purchase_time'] = $po['purchase_time'];
            $return['returned_time'] = time();
            $return['branch_id']     = $po['branch_id'];
            $return['arrive_time']   = $po['arrive_time'];
            $return['amount']        = 0;
            $return['rp_type']       = 'po';
            $return['object_id']     = $po_id;

            $rp_id = $returnObj->createReturnPurchase($return); //生成退货单

            $po_items = $po['po_items']; //$poObj->getPoItemsByPoId($po_id);
            $money    = 0;
            if ($po_items) {
                foreach ($po_items as $item) {
                    $num = $item['num'] - $item['in_num'] - $item['out_num'];
                    $num = $num < 0 ? 0 : $num;
                    if (($item['status'] == '1' || $item['status'] == '2') && $num != 0) {
//判断此商品是否可以取消入库
                        $row['rp_id']      = $rp_id;
                        $row['product_id'] = $item['product_id'];
                        $row['num']        = $num;
                        $row['price']      = $item['price'];
                        $money += $item['price'] * $num;
                        $row['bn']        = $item['bn'];
                        $row['name']      = $item['name'];
                        $row['spec_info'] = $item['spec_info'];

                        $rp_itemObj->save($row);
                        $row = null;

                        $r['item_id'] = $item['item_id'];
                        $r['out_num'] = $item['out_num'] + $num;
                        $r['status']  = ($r['out_num'] + $item['in_num']) >= $item['num'] ? '3' : $item['status'];

                        $po_itemObj->save($r);
                        $r = null;
                    }
                }
            }

            //取消在途
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $po['branch_id']));
            $params                    = array();
            $params['node_type']       = 'deleteArriveStore';
            $params['params']          = array(
                'obj_id' => $po['po_id'], 
                'branch_id' => $po['branch_id'], 
                'obj_type' => 'purchase',
            );
            $err_msg = '';
            $storeManageLib->processBranchStore($params, $err_msg);
            $data['rp_id']        = $rp_id;
            $data['amount']       = $money;
            $data['product_cost'] = $money;

            $returnObj->save($data); //更新退货单
            
            //日志备注
            $log_msg = '';
            $log_msg .= '<br/>生成了一张编号为：' . $return['rp_bn'] . '的退货单';
            
            if ($return_flag == false) {
                //生成退款单
                $refund['add_time']      = time();
                $refund['po_type']       = $po['po_type'];
                $refund['delivery_cost'] = 0;
                $refund['type']          = 'po';
                $refund['rp_id']         = $rp_id;
                $refund['supplier_id']   = $po['supplier_id'];
                if ($po['po_type'] == 'cash') {
                    $refund['refund']       = $money;
                    $refund['product_cost'] = $money;

                } elseif ($po['po_type'] == 'credit' && $po['deposit_balance'] != 0) {
                    $refund['refund']       = $po['deposit_balance'];
                    $refund['product_cost'] = 0;
                }
                $refund_id = $refundObj->createRefund($refund);

                $poo['amount']          = $po['amount'] - $money;
                $poo['product_cost']    = $po['product_cost'] - $money;
                $poo['deposit_balance'] = 0;
            } else {
                $poo['amount']       = 0;
                $poo['product_cost'] = 0;
            }
            $poo['po_id'] = $po_id;
            if ($_POST['memo']) {
                $op_name = $operator;
                $oldmemo = unserialize($po['memo']);
                $memo    = array();
                if ($oldmemo) {
                    foreach ($oldmemo as $k => $v) {
                        $memo[] = $v;
                    }
                }
                $memo[]      = array('op_name' => $op_name, 'op_time' => date('Y-m-d H:i', time()), 'op_content' => htmlspecialchars($_POST['memo']));
                $poo['memo'] = serialize($memo);
            }

            if ($po['po_status'] == '1') {
                $poo['po_status'] = '2'; //入库取消
            }

            if ($po['eo_status'] == '2') {
                $poo['eo_status'] = '3'; //已入库
            } elseif ($po['eo_status'] == '1' || $po['eo_status'] == '0') {
                $poo['eo_status'] = '4'; //未入库
            }

            $poObj->save($poo);

            //--采购单入库取消日志记录
            if ($refund_id) {
                $refund_bn = $refundObj->dump($refund_id, 'refund_bn');
                $log_msg   = '<br/>生成了一张编号为：' . $refund_bn['refund_bn'] . '的退款单';
            }
            $log_msg2 = '对采购单编号为:' . $po['po_bn'] . '进行了入库取消<br/>';
            $opObj    = app::get('ome')->model('operation_log');
            $opObj->write_log('purchase_cancel@purchase', $po_id, $log_msg2 . $log_msg);

            $this->end(true, '入库取消已完成');
        } else {
            $this->end(false, '此采购单已完成入库，请走采购退货流程');
        }
    }

    /**
     * 检测是否可以取消
     *
     */
    public function checkCancel($po_id)
    {
        $poObj = app::get('purchase')->model('po');
        $po    = $poObj->dump($po_id, '*', array('po_items' => array('*')));
        if ($po['check_status'] == '2') {
#已审核，需要请求第三方

            if ($po['eo_status'] == '2' || $po['eo_status'] == '3' || $po['eo_status'] == '4') {
                $result = array('rsp' => 'fail', 'err_msg' => '单据所在状态不允许此次操作');
            } else {
                $po_bn       = $po['po_bn'];
                $purchaseObj = kernel::single('console_event_trigger_purchase');
                $branch_id   = $po['branch_id'];
                $data        = array(
                    'io_type'    => 'PURCHASE',
                    'io_bn'      => $po_bn,
                    'branch_id'  => $branch_id,
                    'out_iso_bn' => $po['out_iso_bn'],
                );

                $result = $purchaseObj->cancel($data, true);

            }

        } else {
            $result = array('rsp' => 'succ');
        }

        echo json_encode($result);
    }

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
        $ids   = $_POST['po_id'];
        
        //check
        if(empty($ids)){
            $this->splash('error', null, '请选择需要操作的记录!');
        }
        
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->splash('error', null, '不能使用全选功能,请选择需要操作的记录!');
        }
        
        $poObj = app::get('purchase')->model('po');

        $po_list = $poObj->getList('po_id', array('po_id'=>$ids, 'check_status' => array('2'), 'po_status' => array('1'), 'eo_status' => array('0', '1')));

        //已审核单据
        if (!empty($po_list)) {
            foreach ($po_list as $po_id) {
                kernel::single('console_event_trigger_purchase')->create(array('po_id' => $po_id), false);

            }
        }
        $this->splash('success', null, '命令已经被成功发送！！');
    }

    /**
     * 基础物料列表弹窗数据获取方法
     *
     * @param Void
     * @return String
     */
    public function findMaterial($supplier_id = null)
    {

        #供应商频道
        if ($supplier_id) {
            //根据供应商商品
            $oSupplierGoods       = app::get('purchase')->model('supplier_goods');
            $supplier_goods       = $oSupplierGoods->getSupplierGoods($supplier_id);
            $base_filter['bm_id'] = $supplier_goods['bm_id'];
        }

        //添加补货采购单，默认带入原采购单商品
        if(!empty($_GET['po_id'])){
            $oPo  = app::get('purchase')->model('po');
            $po_items = $oPo->getPoItemByPoId($_GET['po_id']);
            $product_ids = array_column($po_items,'product_id');
            $base_filter['bm_id'] = $product_ids;
//            pr($product_ids,1);
        }

        //只能选择可见的物料作为组合的明细内容
        $base_filter['visibled'] = 1;

        if ($_GET['type'] == 1) {
            $base_filter['type'] = 1;
        }elseif ($_GET['type'] == 4) {
            $base_filter['type'] = 4;
        }

        $params = array(
            'title'                  => '基础物料列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'base_filter'            => $base_filter,
        );
        $this->finder('material_mdl_basic_material', $params);
    }

    public function iostockConfirm($po_id)
    {
        $poObj       = app::get('purchase')->model('po');
        $eoObj       = app::get('purchase')->model('eo');
        $suObj       = app::get('purchase')->model('supplier');
        $brObj       = app::get('ome')->model('branch');
        $po_itemsObj = app::get('purchase')->model('po_items');
        $po_items    = $po_itemsObj->getList('*', array('po_id' => $po_id), 0, -1);

        $po                   = array();
        $po['po_id']          = $po_id;
        $po['po_items']       = $po_items;
        $this->pagedata['po'] = $po;
        $this->singlepage('admin/purchase/iostock_confirm.html');
    }

    public function doIostockconfirm()
    {
        $this->begin();

        $po_id      = $_POST['po_id'];
        $poObj      = app::get('purchase')->model('po');
        $iostockObj = kernel::single('console_iostockdata');
        $oProducts  = app::get('ome')->model("products");
        $aRow       = $poObj->dump($po_id, '*', array('po_items' => array('in_num,defective_num,num,bn,product_id,price,name')));
        if ($aRow['po_status'] == '5') {
            $this->end(false, '单据已经完成无须确认!');
        }
        $_inner_key = sprintf("purchase_%s", $po_id);
        $aData      = cachecore::fetch($_inner_key);
        if ($aData === false) {
            cachecore::store($_inner_key, 'purchase', 5);
        } else {

            $this->end(false, '此单据已在确认中!');
        }
        $iostock_data = array();
        foreach ($aRow['po_items'] as $items) {

            if ($items['in_num'] > 0) {
                $items_data[] = array(
                    'nums'          => $items['in_num'],
                    'bn'            => $items['bn'],
                    'price'         => $items['price'],
                    'product_id'    => $items['product_id'],
                    'purchase_num'  => $items['num'],
                    'product_name'  => $items['name'],
                    'effective_num' => $items['in_num'],
                );
            }
        }
        $iostock_data['items'] = $items_data;
        if (count($items_data) > 0) {
            $result = kernel::single('console_receipt_purchase')->save_eo($po_id, 'normal', $iostock_data);
            if ($result) {
                $oldmemo = $aRow['memo'];
                if (!$oldmemo) {
                    $oldmemo = unserialize($oldmemo);
                }
                $memo = serialize($oldmemo . '入库确认');
                $poObj->update(array('eo_status' => '3', 'po_status' => '4'), array('po_id' => $po_id));
                $purchaseObj = kernel::single('console_receipt_purchase');
                if ($purchaseObj->checkExist($aRow['po_bn'])) {
                    $purchaseObj->cleanArriveStore($items_data, 'FINISH');
                }
                $this->end(true, '确认成功');
            } else {

                $this->end(false, '确认失败');
            }
        } else {
            $this->end(false, '没有需要确认的货品!');
        }
    }
    /*
  * 采购单查询 by id
  */
    function getPoBnById(){
        
        $po_id = $_POST['id'];
        if ($po_id){
            $poObj                       = app::get('purchase')->model('po');
            $data = $poObj->dump(array('po_id'=>$po_id), 'po_bn');
            
            //echo json_encode($data);
            echo "{id:'".$po_id."',name:'".$data['po_bn']."'}";
        }
    }

    public function getPOById(){
        $po_id = $_POST['id'];
        if ($po_id){
            $poObj                       = app::get('purchase')->model('po');
            $data = $poObj->dump($po_id,'po_id',array('po_items'=>array('*')));
            $this->splash('success',null,'成功','',$data);
        }
    }
}
