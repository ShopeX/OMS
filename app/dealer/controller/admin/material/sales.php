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
 * 销售物料控制层
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

class dealer_ctl_admin_material_sales extends desktop_controller
{

    public $workground = 'dealer_manager';

    /**
     * 销售物料列表分栏菜单
     *
     * @param Null
     * @return Array
     */
    public function _views()
    {

        #不是销售列表时_隐藏Tab
        if ($_GET['act'] != 'index') {
            return array();
        }

        $base_filter = [];
        // 根据权限，只展示当前账号有权限的商品
        $cosList = kernel::single('organization_cos')->getCosList();
        if (!$cosList[0]) {
            $base_filter = ['sm_id' => 0];
        } elseif ($cosList[0] && $cosList[1] != '_ALL_') {
            $cosIds = array_column($cosList[1], 'cos_id');
            $base_filter['cos_id|in'] = $cosIds;
        }

        $smMdl = app::get('dealer')->model('sales_material');

        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false),
            1 => array('label' => app::get('base')->_('普通'), 'filter' => array_merge($base_filter, array('sales_material_type' => 1)), 'optional' => false),
            2 => array('label' => app::get('base')->_('组合'), 'filter' => array_merge($base_filter, array('sales_material_type' => 2)), 'optional' => false),
            3 => array('label' => app::get('base')->_('赠品'), 'filter' => array_merge($base_filter, array('sales_material_type' => 3)), 'optional' => false),
        );

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $smMdl->count($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=dealer&ctl=admin_material_sales&act=index&view=' . $k;
        }

        return $sub_menu;
    }

    /**
     * 销售物料列表项方法
     *
     * @param Post
     * @return String
     */
    public function index()
    {
        // 根据权限，只展示当前账号有权限的商品
        $cosList = kernel::single('organization_cos')->getCosList();
        if (!$cosList[0]) {
            $base_filter = ['sm_id' => 0];
        } elseif ($cosList[0] && $cosList[1] != '_ALL_') {
            $cosIds = array_column($cosList[1], 'cos_id');
            $base_filter['cos_id|in'] = $cosIds;
        }

        $params = array(
            'title'                 => '代发销售商品',
            'actions'               => array(),
            'base_filter'           => $base_filter,
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => false,
            // 'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_importxls' => false,
        );
        $params['actions'][] = array(
            'label' => '新建销售商品',
            'href'  => 'index.php?app=dealer&ctl=admin_material_sales&act=add&view=' . $_GET['view'],
            //'target' => '_blank',
        );
        // $params['actions'][] = array(
        //     'label'  => '导入',
        //     'href'   => $this->url.'&act=execlImportDailog&p[0]=sales_material',
        //     'target' => 'dialog::{width:500,height:300,title:\'导入\'}',
        // );

        $this->finder('dealer_mdl_sales_material', $params);
    }

    /**
     * 销售物料新增的展示页面方法
     *
     * @param void
     * @return void
     */
    public function add()
    {
        $filter = ['delivery_mode' => 'shopyjdf'];
        // 根据权限，只展示当前账号有权限的店铺
        $cosList    = kernel::single('organization_cos')->getCosList();
        $bbuCosList = kernel::single('organization_cos')->getBbuFromCosId();

        if (!$cosList[0]) {
            $filter['cos_id'] = 0;
            $this->pagedata['bm_filter'] = 'visibled=1&cos_id=0';
        } elseif ($cosList[0] && $cosList[1] != '_ALL_') {
            $cosIds = array_column($cosList[1], 'cos_id');
            $filter['cos_id|in'] = $cosIds;

            $bbu_cos_ids = array_column($bbuCosList[1], 'cos_id');

            $this->pagedata['bm_filter'] = 'visibled=1&cos_id=' . ($bbu_cos_ids ? implode(',', $bbu_cos_ids) : '0');
        } else {
            $this->pagedata['bm_filter'] = 'visibled=1';
        }

        // 检查是否有基础物料权限
        $bmMdl = app::get('material')->model('basic_material');
        $hasBm = $bmMdl->db_dump(explode('&', $this->pagedata['bm_filter']), 'bm_id');
        $this->pagedata['hasBm'] = $hasBm;

        #过滤o2o门店店铺
        $shopObj  = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', $filter);
        // array_unshift($shopList, array('shop_id' => '_ALL_', 'name' => '全部店铺'));
        $this->pagedata['shops']      = $shopList;
        $this->pagedata['finder_vid'] = $_GET['finder_vid'];
        $this->pagedata['view']       = isset($_GET['view']) ? $_GET['view'] : 0;
        $this->page('admin/material/sales/add.html');
    }

    /**
     * @description 显示关联的物料
     * @access public
     * @param void
     * @return void
     */
    public function showMaterials()
    {
        $bm_id = kernel::single('base_component_request')->get_post('bm_id');

        if ($bm_id) {
            $this->pagedata['_input'] = array(
                'name'     => 'bm_id',
                'idcol'    => 'bm_id',
                '_textcol' => 'material_name',
            );

            $bmMdl = app::get('material')->model('basic_material');
            $list  = $bmMdl->getList('bm_id,material_name, material_bn', array('bm_id' => $bm_id), 0, -1, 'bm_id asc');

            //显示基础物料编码
            foreach ($list as $key => $val) {
                $list[$key]['material_name'] = '基础商品编码：' . $val['material_bn'] . '&nbsp;&nbsp;&nbsp;基础商品名称：' . $val['material_name'];
            }

            $this->pagedata['_input']['items'] = $list;
        }

        $this->display('admin/material/sales/show_materials.html');
    }

    /**
     * 基础物料新增提交方法
     *
     * @param Post
     * @return Boolean
     */
    public function toAdd()
    {
        $this->begin('index.php?app=dealer&ctl=admin_material_sales&act=index&view=' . $_GET['view'] . '&finder_vid=' . $_GET['finder_vid']);

        #数据有效性检查
        $checkSalesLib = kernel::single('dealer_sales_check');
        $err_msg       = '';
        if (!$checkSalesLib->checkParams($_POST, $err_msg)) {
            $this->end(false, $err_msg);
        }

        $bmMdl   = app::get('material')->model('basic_material');
        $smMdl   = app::get('dealer')->model('sales_material');
        $sbmMdl  = app::get('dealer')->model('sales_basic_material');
        $shopMdl = app::get('ome')->model('shop');

        $shopInfo = $shopMdl->db_dump(['shop_id' => $_POST['shop_id']]);

        $opInfo = kernel::single('ome_func')->getDesktopUser();
        //保存物料主表信息
        $addData = array(
            'sales_material_bn'   => $_POST['sales_material_bn'],
            'sales_material_name' => $_POST['sales_material_name'],
            'sales_material_type' => $_POST['sales_material_type'],
            'shop_id'             => $_POST['shop_id'],
            'cos_id'              => $shopInfo['cos_id'],
            'op_name'             => $opInfo['op_name'],
        );
        $is_save = $smMdl->save($addData);

        if ($is_save) {
            $is_bind = false;
            //普通销售物料关联
            if ($_POST['sales_material_type'] == 1 && !empty($_POST['bm_id'])) {
                //基础物料信息
                $baseMaterialInfo = $bmMdl->dump(array('bm_id' => $_POST['bm_id']), '*');
                if (!$baseMaterialInfo) {
                    $this->end(false, '基础商品无效');
                }
                $addBindData = array(
                    'sm_id'  => $addData['sm_id'],
                    'bm_id'  => $_POST['bm_id'],
                    'number' => 1,
                );
                $sbmMdl->insert($addBindData);
                $is_bind = true;
            } elseif ($_POST['sales_material_type'] == 2 && !empty($_POST['at'])) {
                //基础物料信息
                $baseMaterialInfo = $bmMdl->getList('bm_id', ['bm_id|in' => array_keys($_POST['at'])]);
                if (!$baseMaterialInfo || count($baseMaterialInfo) != count(array_keys($_POST['at']))) {
                    $this->end(false, '基础商品无效');
                }

                //促销销售物料关联
                foreach ($_POST['at'] as $k => $v) {
                    $addBindData = array(
                        'sm_id'  => $addData['sm_id'],
                        'bm_id'  => $k,
                        'number' => $v,
                        'rate'   => $_POST['pr'][$k],
                    );
                    $sbmMdl->insert($addBindData);
                }
                $is_bind = true;
            } elseif ($_POST['sales_material_type'] == 3 && !empty($_POST['at'])) { // 赠品关联多基础物料处理
                // at=> [bm_id=>number]
                foreach ($_POST['at'] as $k => $v) {
                    $addBindData = array(
                        'sm_id' => $addData['sm_id'],
                        'bm_id' => $k,
                        'number' => $v,
                    );
                    $sbmMdl->insert($addBindData);
                    $addBindData = null;
                }
                $is_bind = true;
            }

            //如果有绑定物料数据，设定销售物料为绑定状态
            if ($is_bind) {
                $smMdl->update(array('is_bind' => 1), array('sm_id' => $addData['sm_id']));
            }

            //logs
            $operationLogObj = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('dealer_sm_add@dealer', $addData['sm_id'], '销售商品添加');
            $this->end(true, '操作成功');
        } else {
            $this->end(false, '保存失败');
        }

    }

    /**
     * 销售物料查询详情
     * @Author: xueding
     * @Vsersion: 2022/5/23 上午10:23
     * @param $sm_id
     */
    public function detail($sm_id)
    {
        $this->begin('index.php?app=dealer&ctl=admin_material_sales&act=index');
        if (empty($sm_id)) {
            $this->end(false, '操作出错，请重新操作');
        }

        $smMdl = app::get('dealer')->model('sales_material');

        $tmp_sm_id         = intval($sm_id);
        $salesMaterialInfo = $smMdl->dump($tmp_sm_id);
        if (!$salesMaterialInfo) {
            $this->end(false, '操作出错，请重新操作');
        }

        if (($salesMaterialInfo['sales_material_type'] == 1 || $salesMaterialInfo['sales_material_type'] == 3) && $salesMaterialInfo['is_bind'] == 1) {
            $sbmMdl                        = app::get('dealer')->model('sales_basic_material');
            $salesBasicMaterialInfo        = $sbmMdl->dump(array('sm_id' => $tmp_sm_id));
            $count                         = is_array($salesBasicMaterialInfo['bm_id']) ? count($salesBasicMaterialInfo['bm_id']) : 1;
            $this->pagedata['bind_bm_id']  = $salesBasicMaterialInfo['bm_id'];
            $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product'>已选择了{$count}个商品,<a href='javascript:void(0);' onclick='material_selected_show();'>查看关联的商品.</a></div>
EOF;
        }

        //店铺信息(过滤o2o门店店铺)
        $shopObj  = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', array('s_type' => 1), 0, -1);
        array_unshift($shopList, array('shop_id' => '_ALL_', 'name' => '全部店铺'));
        $this->pagedata['shops'] = $shopList;

        $this->pagedata['material_info'] = $salesMaterialInfo;
        $readonly                        = array('type' => true, 'shop' => true, 'bind_item' => true);
        $this->pagedata['readonly']      = $readonly;

        // //操作日志
        // $logObj = app::get('ome')->model('operation_log');

        // /* 本销售物料日志 */
        // $logList = $logObj->read_log(array('obj_id' => $sm_id, 'obj_type' => 'sales_material@material'), 0, -1);
        // foreach ($logList as $k => $v) {
        //     $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
        // }

        // $this->pagedata['data'] = $logList;
        $this->singlepage('admin/material/sales/detail.html');
    }
    /**
     * 销售物料编辑的展示页面方法
     *
     * @param Int $bm_id
     * @return Boolean
     */
    public function edit($sm_id)
    {
        $this->begin('index.php?app=dealer&ctl=admin_material_sales&act=index');
        if (empty($sm_id)) {
            $this->end(false, '操作出错，请重新操作');
        }

        $smMdl = app::get('dealer')->model('sales_material');

        $tmp_sm_id         = intval($sm_id);
        $salesMaterialInfo = $smMdl->dump($tmp_sm_id);
        if (!$salesMaterialInfo) {
            $this->end(false, '操作出错，请重新操作');
        }
        $filter = ['delivery_mode' => 'shopyjdf'];

        // 根据权限，只展示当前账号有权限的店铺
        $cosList = kernel::single('organization_cos')->getCosList();
        $bbuCosList = kernel::single('organization_cos')->getBbuFromCosId();

        if (!$cosList[0]) {
            $filter['cos_id'] = 0;
            $this->pagedata['bm_filter'] = 'visibled=1&cos_id=0';
        } elseif ($cosList[0] && $cosList[1] != '_ALL_') {
            $cosIds = array_column($cosList[1], 'cos_id');
            $filter['cos_id|in'] = $cosIds;

            $bbu_cos_ids = array_column($bbuCosList[1], 'cos_id');

            $this->pagedata['bm_filter'] = 'visibled=1&cos_id=' . ($bbu_cos_ids ? implode(',', $bbu_cos_ids) : '0');
        } else {
            $this->pagedata['bm_filter'] = 'visibled=1';
        }

        // 检查是否有基础物料权限
        $bmMdl = app::get('material')->model('basic_material');
        $hasBm = $bmMdl->db_dump(explode('&', $this->pagedata['bm_filter']), 'bm_id');
        $this->pagedata['hasBm'] = $hasBm;




        //检查物料是否有关联的订单,关联的基础物料不能改变
        //todo 需实现 readonly
        $checkSalesLib = kernel::single('material_sales_check');
        $readonly      = $checkSalesLib->checkEditReadOnly($salesMaterialInfo['shop_id'], $tmp_sm_id, $salesMaterialInfo['is_bind']);

        if (($salesMaterialInfo['sales_material_type'] == 1 || $salesMaterialInfo['sales_material_type'] == 3) && $salesMaterialInfo['is_bind'] == 1) {
            $sbmMdl                        = app::get('dealer')->model('sales_basic_material');
            $salesBasicMaterialInfo        = $sbmMdl->dump(array('sm_id' => $tmp_sm_id));
            $count                         = count([$salesBasicMaterialInfo['bm_id']]);
            $this->pagedata['bind_bm_id']  = $salesBasicMaterialInfo['bm_id'];
            $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product'>已选择了{$count}个商品,<a href='javascript:void(0);' onclick='material_selected_show();'>查看关联的商品.</a></div>
EOF;
        }

        //店铺信息(过滤o2o门店店铺)
        $shopObj  = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', $filter, 0, -1);
        // array_unshift($shopList, array('shop_id' => '_ALL_', 'name' => '全部店铺'));
        $this->pagedata['shops'] = $shopList;

        $this->pagedata['material_info'] = $salesMaterialInfo;
        $this->pagedata['readonly']      = $readonly;
        $this->singlepage('admin/material/sales/edit.html');
    }

    /**
     * 销售物料编辑提交方法
     *
     * @param Int $sm_id
     * @return Boolean
     */
    public function toEdit()
    {
        $this->begin('index.php?app=dealer&ctl=admin_material_sales&act=index');
        #数据有效性检查
        $checkSalesLib = kernel::single('dealer_sales_check');
        $err_msg       = '';
        $_POST['edit'] = true; //编辑标识 checkParams用
        if (!$checkSalesLib->checkParams($_POST, $err_msg)) {
            $this->end(false, $err_msg);
        }
        $bmMdl  = app::get('material')->model('basic_material');
        $smMdl  = app::get('dealer')->model('sales_material');
        $sbmMdl = app::get('dealer')->model('sales_basic_material');

        //更新基础物料基本信息
        $filter = [
            'sm_id'     => $_POST['sm_id'],
        ];

        $snapshoot = [
            'sdb_dealer_sales_material'       => $smMdl->db_dump($filter),
            'sdb_dealer_sales_basic_material' => $sbmMdl->getList('*', $filter),
        ];

        $shopInfo = app::get('ome')->model('shop')->db_dump(['shop_id'=>$_POST['shop_id']]);
        if (!$shopInfo || !$shopInfo['cos_id']) {
            $this->end(false, '店铺企业组织架构ID无效');
        }

        //更新销售物料基本信息
        $updateData = array(
            "sales_material_name" => $_POST['sales_material_name'],
            "sales_material_type" => $_POST['sales_material_type'],
            "shop_id"             => $_POST['shop_id'],
            "cos_id"              => $shopInfo['cos_id'],
            "last_modified"       => time(),
        );
        $is_update = $smMdl->update($updateData, $filter);
        if ($is_update) {
            $is_bind = false; //如果有关联物料就做绑定操作

            //删除原有关联基础物料信息  后续会新增的（重做关系）
            $sbmMdl->delete(array('sm_id' => $filter['sm_id'])); //普通、赠品、促销

            //普通销售物料关联
            if ($_POST['sales_material_type'] == 1 && !empty($_POST['bm_id'])) {
                //普通、赠品销售物料关联
                $addBindData = array(
                    'sm_id'  => $filter['sm_id'],
                    'bm_id'  => $_POST['bm_id'],
                    'number' => 1,
                );
                $sbmMdl->insert($addBindData);

                //基础物料信息
                $baseMaterialInfo = $bmMdl->dump(array('bm_id' => $_POST['bm_id']), '*');

                $is_bind = true;
            } elseif ($_POST['sales_material_type'] == 2 && !empty($_POST['at'])) {
                foreach ($_POST['at'] as $k => $v) {
                    $addBindData = array(
                        'sm_id'  => $filter['sm_id'],
                        'bm_id'  => $k,
                        'number' => $v,
                        'rate'   => $_POST['pr'][$k],
                    );
                    $sbmMdl->insert($addBindData);
                    $addBindData = null;
                }
                $is_bind = true;
            } elseif ($_POST['sales_material_type'] == 3 && !empty($_POST['at'])) {
                //赠品销售物料关联
                foreach ($_POST['at'] as $k => $v) {
                    $addBindData = array(
                        'sm_id' => $filter['sm_id'],
                        'bm_id' => $k,
                        'number' => $v,
//                        'rate' => $_POST['pr'][$k],
                    // todo rate是否需要
                    );
                    $sbmMdl->insert($addBindData);
                    $addBindData = null;
                }
                $is_bind = true;
            }

            //如果有绑定物料数据，设定销售物料为绑定状态
            if ($is_bind) {
                $smMdl->update(array('is_bind' => 1), array('sm_id' => $filter['sm_id']));
            } else {
                $smMdl->update(array('is_bind' => 2), array('sm_id' => $filter['sm_id']));
            }

            //保存日志
            $omeLogMdl = app::get('ome')->model('operation_log');
            $log_id    = $omeLogMdl->write_log('dealer_sm_edit@dealer', $filter['sm_id'], '更新销售商品');
            if ($log_id && $snapshoot) {
                $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
                $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
                $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
                $shootMdl->insert($tmp);
            }
            $this->end(true, '保存成功');
        } else {
            $this->end(false, '保存失败');
        }
    }

    /**
     * 促销类销售物料关联的基础物料信息异步加载方法
     * 还包括多选一类型的销售物料
     * @param Int $sm_id
     * @return String
     */
    public function getEditMaterial($sm_id, $log_id = '')
    {
        if ($sm_id == '') {
            $sm_id = $_POST['p[0]'];
        }

        $bmMdl               = app::get('material')->model('basic_material');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');

        $rows  = array();
        if ($log_id) {
            $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
            //日志
            $log = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
            $row = json_decode($log['snapshoot'], 1);
            $items = $row['sdb_dealer_sales_basic_material'];
        } else {
            $sbmMdl = app::get('dealer')->model('sales_basic_material');
            $items = $sbmMdl->getList('bm_id,sm_id,number,rate', array('sm_id' => $sm_id), 0, -1);
        }
        if (!empty($items)) {
            foreach ($items as $k => $item) {
                $basicMaterialInfo = $bmMdl->dump(array('bm_id' => $item['bm_id']), 'material_bn,material_name,cat_id,cat_path');

                //成本价
                $extInfo            = $basicMaterialExtObj->dump(array('bm_id' => $item['bm_id']), 'bm_id, cost,brand_id');
                $item['cost']       = $extInfo['cost'];
                $item['brand_id']   = $extInfo['brand_id'];
                $brand              = app::get('ome')->model('brand')->db_dump(['brand_id' => $item['brand_id']], 'brand_name');
                $item['brand_name'] = $brand['brand_name'];
                $item['cost']       = $extInfo['cost'];

                $items[$k] = array_merge($item, $basicMaterialInfo);
            }
            $rows["original"] = $items; //普通、赠品、促销
        }
        echo json_encode($rows);
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
        //日志
        $log = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
        $row = json_decode($log['snapshoot'], 1);

        $salesMaterialInfo = $row['sdb_dealer_sales_material'];
        if (($salesMaterialInfo['sales_material_type'] == 1 || $salesMaterialInfo['sales_material_type'] == 3) && $salesMaterialInfo['is_bind'] == 1) {
            $salesBasicMaterialInfo        = $row['sdb_dealer_sales_basic_material'][0];
            $count                         = is_array($salesBasicMaterialInfo['bm_id']) ? count($salesBasicMaterialInfo['bm_id']) : 1;
            $this->pagedata['bind_bm_id']  = $salesBasicMaterialInfo['bm_id'];
            $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product'>已选择了{$count}个商品,<a href='javascript:void(0);' onclick='material_selected_show();'>查看关联的商品.</a></div>
EOF;
        }

        //店铺信息(过滤o2o门店店铺)
        $shopObj  = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', array('s_type' => 1), 0, -1);
        array_unshift($shopList, array('shop_id' => '_ALL_', 'name' => '全部店铺'));
        $this->pagedata['shops'] = $shopList;

        $this->pagedata['material_info'] = $salesMaterialInfo;
        $readonly                        = array('type' => true, 'shop' => true, 'bind_item' => true);
        $this->pagedata['readonly']      = $readonly;
        $this->pagedata['history']       = true;
        $this->pagedata['log_id']        = $log_id;

        $this->singlepage('admin/material/sales/detail.html');
    }

}
