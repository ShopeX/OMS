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
 * 基础物料控制层
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */
class material_ctl_admin_material_basic extends desktop_controller
{

    public $workground  = 'goods_manager';
    public $view_source = 'normal';

    /**
     * 基础物料列表分栏菜单
     * 
     * @param Null
     * @return Array
     */

    public function _views()
    {
        $sub_menu = array();

        //默认列表查看的时候才显示TAB，关联物料时弹窗列表页不加载TAB by xiayuanjun
        if ($this->view_source == 'normal') {
            $sub_menu = $this->_viewsNormal();
        }

        return $sub_menu;
    }

    public function _viewsNormal()
    {
        $basicMaterialObj = app::get('material')->model('basic_material');

        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'filter' => array(), 'optional' => false),
            1 => array('label' => app::get('base')->_('在售'), 'filter' => array("visibled" => 1), 'optional' => false),
            2 => array('label' => app::get('base')->_('停售'), 'filter' => array("visibled" => 2), 'optional' => false),
            3 => array('label' => app::get('base')->_('全渠道'), 'filter' => array('omnichannel' => 1), 'optional' => false),
            4 => array('label' => app::get('base')->_('非全渠道'), 'filter' => array('omnichannel' => 2), 'optional' => false),
        );

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $basicMaterialObj->count($v['filter']);
            $sub_menu[$k]['href']   = "index.php?app=material&ctl=admin_material_basic&act=" . $_GET['act'] . "&view=" . $k;
        }

        return $sub_menu;
    }

    /**
     * 基础物料列表项方法
     * 
     * @param Post
     * @return String
     */
    public function index()
    {
        $use_buildin_export = false;
        $use_buildin_import = false;
        $actions       = array();
        $actions_group = array();
        if($_GET['view'] != "2" && kernel::single('desktop_user')->has_permission('basic_material_unsale')) {
            $actions_group[] = array(
                'label'   => '批量停售',
                'submit'  => "index.php?app=material&ctl=admin_material_basic&act=batchInvisible&p[0]=" . $_GET['view'],
                'confirm' => '你确定要对勾选的基础物料统一设为停售？',
                'target'  => 'refresh',
            );
        }
        if($_GET['view'] != "1" && kernel::single('desktop_user')->has_permission('basic_material_sale')) {
            $actions_group[] = array(
                'label'   => '批量在售',
                'submit'  => "index.php?app=material&ctl=admin_material_basic&act=batchVisible&p[0]=" . $_GET['view'],
                'confirm' => '你确定要对勾选的基础物料统一设为在售？',
                'target'  => 'refresh',
            );
        }
    
        $actions_group[] = array(
            'label'   => '批量启用唯一码',
            'submit'  => "index.php?app=material&ctl=admin_material_basic&act=batchSerialNumber&p[0]=1&p[1]=". $_GET['view'],
            'confirm' => '你确定要对勾选的基础物料统一设为启用唯一码？',
            'target'  => 'refresh',
        );
    
        $actions_group[] = array(
            'label'   => '批量停用唯一码',
            'submit'  => "index.php?app=material&ctl=admin_material_basic&act=batchSerialNumber&p[0]=2&p[1]=". $_GET['view'],
            'confirm' => '你确定要对勾选的基础物料统一设为停用唯一码？',
            'target'  => 'refresh',
        );

        switch ($_GET['view']) {
            case '3':
            case '4':
                if(kernel::single('desktop_user')->has_permission('basic_material_omnichannel')) {
                    $actions_group[] = array(
                        'label'  => '批量设置全渠道',
                        'submit' => 'index.php?app=material&ctl=admin_material_basic&act=batchUpOmnichannel&p[0]=' . $_GET['view'],
                        'target' => 'dialog::{width:600,height:300,title:\'批量设置全渠道\'}"',
                    );
                }
                break;
            default:
                $use_buildin_export = kernel::single('desktop_user')->has_permission('basic_material_export');
                $use_buildin_import = kernel::single('desktop_user')->has_permission('basic_material_import');
                
                if(kernel::single('desktop_user')->has_permission('basic_material_add')) {
                    $actions[] = array(
                            'label'  => '新增',
                            'href'   => 'index.php?app=material&ctl=admin_material_basic&act=add',
                        );
                }
                if(kernel::single('desktop_user')->has_permission('basic_material_import')) {
                    $actions[] = array(
                        'label'  => '导出模板',
                        'href'   => 'index.php?app=material&ctl=admin_material_basic&act=getExportTemplate',
                        'target' => 'dialog::{width:450,height:210,title:\'' . app::get('desktop')->_('导出模板') . '\'}',
                    );
                    $actions[] = array('label' => app::get('desktop')->_('更新导入'), 'icon' => 'upload.gif', 'href' => 'index.php?app=omecsv&ctl=admin_import&act=main&ctler=material_mdl_basic_material_ext&add=material', 'target' => 'dialog::{width:550,height:310,title:\'' . app::get('desktop')->_('更新导入') . '\'}');


                   
                    $actions[] = array(
                        'label' => app::get('desktop')->_('物料属性更新导入'),
                        'icon' => 'upload.gif', 
                        'href' => 'index.php?app=omecsv&ctl=admin_import&act=main&ctler=material_mdl_basic_material_props&add=material', 
                        'target' => 'dialog::{width:450,height:210,title:\'' . app::get('desktop')->_('物料属性更新导入') . '\'}',
                    );
                }
                if(kernel::single('desktop_user')->has_permission('basic_material_uselife')) {
                    $actions_group[] = array(
                        'label'  => '批量设置保质期',
                        'submit' => "index.php?app=material&ctl=admin_material_basic&act=BatchUpExpire&p[0]=" . $_GET['view'],
                        'target' => 'dialog::{width:600,height:300,title:\'批量设置保质期\'}"',
                    );
                }
                if(kernel::single('desktop_user')->has_permission('basic_material_property')) {
                    $actions_group[] = array(
                        'label'  => '批量设置属性',
                        'submit' => "index.php?app=material&ctl=admin_material_basic&act=batchUpProperty&p[0]=" . $_GET['view'],
                        'target' => 'dialog::{width:600,height:300,title:\'批量设置属性\'}"',
                    );
                }
                
                if(app::get('dealer')->is_installed()){
                    if (app::get('dealer')->model('bbu')->count(['status'=>'active']) > 0){
                        $actions_group[] = array(
                            'label'  => '批量设置销售团队',
                            'submit' => "index.php?app=material&ctl=admin_material_basic&act=batchSetBbu&p[0]=" . $_GET['view'],
                            'target' => 'dialog::{width:520,height:200,title:\'批量设置销售团队\'}"',
                        );
                    }
                }
                
                if(kernel::single('desktop_user')->has_permission('basic_material_delete')) {
                    $actions[] = array(
                        'label'=>$this->app->_('删除'),
                        'confirm' => '你确定要删除此条记录吗？',
                        'submit'=>'index.php?app=material&ctl=admin_material_basic&act=delete_material',
                        'target'=>'refresh'
                    );
                }

                break;
        }
        //压入批量操作
        if($actions_group) {
            $actions[] = array(
                "label" => "批量操作",
                "group" => $actions_group,
            );
        }
        $base_filter = [];
        $params = array(
            'title'               => '基础物料',
            'actions'             => $actions,
            'base_filter'         => $base_filter,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter'  => true,
            'use_buildin_customcols' => true,
            'use_buildin_export'  => $use_buildin_export,
            'use_buildin_import'  => $use_buildin_import,
            'use_buildin_importxls'  => $use_buildin_import,
            'finder_cols'         => 'column_edit,material_name,material_bn,column_barcode,column_cost,column_weight,column_unit,column_retail_price,visibled,column_specifications,column_brand,column_cat_level_1,column_cat_level_2,column_cat_level_3,column_cat_level_4,column_cat_level_5',
        );



        $this->finder('material_mdl_basic_material', $params);
    }

    /**
     * 获取ExportTemplate
     * @return mixed 返回结果
     */
    public function getExportTemplate() {
        $this->display('admin/material/basic/get_export_template.html');
    }

    /**
     * 基础物料批量导入的模板
     * 
     * @param Null
     * @return String
     */
    public function exportTemplate()
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        $title            = $basicMaterialObj->getTemplateColumn();

        #模板案例
        $data = [];
        $data[0] = array('导入成品基础物料一', 'material_001', '成品', 'barcode_001', '在售', '件', '150', '120', '500',
            'semi_material_001:1|semi_material_002:1|semi_material_003:1', '关闭', '', '', '', '', '', '灯具', '200w灯泡|1米6灯架|黑色灯罩', '商派OMS', '开启', '1', '11', '是', '开启', '否', '', '', '', '', '', '', '', '', '是');
        $data[1] = array('导入半成品基础物料二', 'material_002', '半成品', 'barcode_002', '在售', '件', '180', '135', '750',
            '', '开启', '18', '12', '', '', '', '男装', '白色|170M|棉', 'ONex OMS', '关闭', '', '', '否', '关闭', '是', '', '', '', '', '', '', '', '', '否');

        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, '基础物料导入模板-' . date('Ymd'), 'xls', $title);
    }
    /**
     * 基础物料批量更新导入的模板
     * 
     * @param Null
     * @return String
     */
    public function exportUpdateTemplate()
    {
        $row = app::get('material')->model('basic_material_ext')->getTemplateColumn();
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel(null, "基础物料更新导入模板-" . date('Ymd'), 'xls', $row);
    }

    /**
     * 基础物料新增的展示页面方法
     * 
     * @param void
     * @return void
     */
    public function add()
    {
        $materialLib = kernel::single('material_basic_material');
        
        //获取物料品牌
        $brand_obj = app::get('ome')->model('brand');
        $brands    = $brand_obj->getList('brand_id,brand_name', array());

        //获取物料分类
        $goods_type_obj = app::get('ome')->model('goods_type');
        $goods_types    = $goods_type_obj->getList('type_id,name', array());

        //第三方仓储版_隐藏保质期管理(未安装invoice并且已安装omevirtualwms)
        $show_storage_life = true;
        if (app::get('invoice')->is_installed() == false) {
            if (app::get('omevirtualwms')->is_installed()) {
                $show_storage_life = false;
            }
        }

        $this->pagedata['show_storage_life'] = $show_storage_life;
        $this->pagedata['material_brand']    = $brands;

        $this->pagedata['goods_types'] = $goods_types;

        $envSpecConf                   = app::get('ome')->getConf('ome.product.serial.merge');
        $this->pagedata['envSpecConf'] = $envSpecConf;
        
        //是否有O2O门店
        $exist_store                   = $this->getStoreRs();
        $this->pagedata['exist_store'] = $exist_store;
        
        //物料属性
        $materialTypes = $materialLib->get_material_types();
        $this->pagedata['materialTypes'] = $materialTypes;

        // 销售团队
        $this->pagedata['bbu_list'] = [];
        if(app::get('dealer')->is_installed()){
            //检查是否安装dealer应用
            $bbuMdl = app::get('dealer')->model('bbu');
            $this->pagedata['bbu_list'] = $bbuMdl->getList('*', ['status' => 'active']);
        }
        $this->pagedata['finder_vid'] = $_GET['finder_vid'];

        //自定义
        
        $customcols = kernel::single('material_customcols')->getcols();

        $this->pagedata['customcols'] = $customcols;
        $this->page('admin/material/basic/add.html');
    }

    /**
     * 基础物料新增提交方法
     * 
     * @param Post
     * @return Boolean
     */
    public function toAdd()
    {
        $this->begin('index.php?app=material&ctl=admin_material_basic&act=index');

        #检查数据有效性
        $checkBasicLib = kernel::single('material_basic_check');
        $err_msg       = '';


        if (!$checkBasicLib->checkParams($_POST, $err_msg)) {
            $this->end(false, $err_msg);
        }

        $basicMaterialObj           = app::get('material')->model('basic_material');
        $basicMaterialExtObj        = app::get('material')->model('basic_material_ext');
        $basicMaterialFeatureGrpObj = app::get('material')->model('basic_material_feature_group');
        $basicMaterialStockObj      = app::get('material')->model('basic_material_stock');
        $basicMaterialConfObj       = app::get('material')->model('basic_material_conf');
        $basicMaterialConfObjSpe    = app::get('material')->model('basic_material_conf_special');
        $barocdeObj                 = app::get('material')->model('barcode');
        $basicMaterialCatObj        = app::get('material')->model('basic_material_cat');
        $salesMaterialObj           = app::get('material')->model('sales_material');

        //获取cat_path
        $cat_id = intval($_POST['cat_id']);
        if (!$cat_id) {
            //$this->end(false, '请选择分类');
            $cat_id = array('cat_path' => ','); //填充默认分类
        }
        $rs = $basicMaterialCatObj->dump($cat_id, 'cat_id,cat_path,min_price');
        if ($rs) {
            $cat_id    = $rs['cat_id'];
            $cat_path  = substr($rs['cat_path'] . $cat_id, 1);
            $min_price = floatval($rs['min_price']); //当前分类的最低限价
        } else {
            $cat_id    = 0;
            $cat_path  = '';
            $min_price = 0;
        }

        //是否全渠道
        $omnichannel = ($_POST['omnichannel'] == 1 ? 1 : 2);
        
        // 是否管控库存
        $is_ctrl_store = isset($_POST['is_ctrl_store']) ? intval($_POST['is_ctrl_store']) : 1;
        
        //保存物料主表信息
        $addData = array(
            'material_name'     => $_POST['material_name'],
            'material_bn'       => trim($_POST['material_bn']),
            'material_spu'      => trim($_POST['material_spu']),
            'material_bn_crc32' => $_POST['material_bn_crc32'],
            'type'              => $_POST['type'],
            'serial_number'     => $_POST['serial_number'],
            'visibled'          => $_POST['visibled'],
            'cat_id'            => $cat_id,
            'cat_path'          => $cat_path,
            'create_time'       => time(),
            'omnichannel'       => $omnichannel,
            'tax_rate'          => intval($_POST['tax_rate']),
            'tax_name'          => $_POST['tax_name'],
            'tax_code'          => $_POST['tax_code'],
            'color'             => $_POST['color'], 
            'size'              => $_POST['size'],
            'is_o2o_sales'      => $_POST['is_o2o_sales'],
            'is_ctrl_store'     => ($is_ctrl_store === 2 ? 2 : 1),
        );
        
        if (isset($_POST['bbu_info']) && $_POST['bbu_info']) {
            [$addData['bbu_id'], $addData['cos_id']] = explode('|', $_POST['bbu_info']);
        }
        
        $is_save = $basicMaterialObj->save($addData);

        if ($is_save) {

            //保存条码信息
            $sdf = array(
                'bm_id' => $addData['bm_id'],
                'type'  => material_codebase::getBarcodeType(),
                'code'  => $_POST['material_code'],
            );
            $barocdeObj->insert($sdf);


            if($_POST['props']){
                $propsMdl = app::get('material')->model('basic_material_props');

                $propsdata = array();

                foreach($_POST['props'] as $pk=>$pv){

                    if($pv){
                        $propsdata = array(
                            'bm_id'         =>  $addData['bm_id'],
                            'props_col'     =>  $pk,
                            'props_value'   =>  $pv,
                        );
                        $propsMdl->save($propsdata);
                    }
                }

                

            }
            //保存保质期配置
            $useExpireConfData = array(
                'bm_id'       => $addData['bm_id'],
                'use_expire'  => $_POST['use_expire'] == 1 ? 1 : 2,
                'warn_day'    => $_POST['warn_day'] ? $_POST['warn_day'] : 0,
                'quit_day'    => $_POST['quit_day'] ? $_POST['quit_day'] : 0,
                'use_expire_wms' => $_POST['use_expire'] == 1 ? 1 : 2,
                'shelf_life' => (int) $_POST['shelf_life'],
                'reject_life_cycle' => (int) $_POST['reject_life_cycle'],
                'lockup_life_cycle' => (int) $_POST['lockup_life_cycle'],
                'advent_life_cycle' => (int) $_POST['advent_life_cycle'],
                'create_time' => time(),
            );
            $basicMaterialConfObj->save($useExpireConfData);

            //如果关联半成品数据
            if (in_array($addData['type'],array('1','4'))) {
                $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
                if (isset($_POST['at'])) {
                    foreach ($_POST['at'] as $k => $v) {
                        $tmpChildMaterialInfo = $basicMaterialObj->dump($k, 'material_name,material_bn');

                        $addCombinationData = array(
                            'pbm_id'            => $addData['bm_id'],
                            'bm_id'             => $k,
                            'material_num'      => $v,
                            'material_name'     => $tmpChildMaterialInfo['material_name'],
                            'material_bn'       => $tmpChildMaterialInfo['material_bn'],
                            'material_bn_crc32' => sprintf('%u', crc32($tmpChildMaterialInfo['material_bn'])),
                        );
                        $basicMaterialCombinationItemsObj->insert($addCombinationData);
                        $addCombinationData = null;
                    }
                } else {
                    if(in_array($addData['type'],array('4'))) {
                        $this->end(false, '礼盒未绑定普通商品');
                    }
                }
            }

            //保存基础物料的关联的特性
            if ($_POST['ftgp_id']) {
                $addBindFeatureData = array(
                    'bm_id'            => $addData['bm_id'],
                    'feature_group_id' => $_POST['ftgp_id'],
                );
                $basicMaterialFeatureGrpObj->insert($addBindFeatureData);
                $addBindFeatureData = null;
            }

            //保存物料扩展信息
            $addExtData = array(
                'bm_id'            => $addData['bm_id'],
                'cost'             => $_POST['cost'] ? $_POST['cost'] : 0.00,
                'retail_price'     => $_POST['retail_price'] ? $_POST['retail_price'] : 0.00,
                'weight'           => $_POST['weight'] ? $_POST['weight'] : 0.00,
                'length'           => $_POST['length'] ? $_POST['length'] : 0.00,
                'width'            => $_POST['width'] ? $_POST['width'] : 0.00,
                'high'             => $_POST['high'] ? $_POST['high'] : 0.00,
                'unit'             => $_POST['unit'],
                'specifications'   => $_POST['material_specification'],
                'brand_id'         => empty($_POST['brand']) ? 0 : $_POST['brand'],
                'cat_id'           => empty($_POST['goods_type']) ? 0 : $_POST['goods_type'],
                'purchasing_price' => $_POST['purchasing_price'] ? $_POST['purchasing_price'] : 0.00,
                'volume'         => $_POST['volume'] ? $_POST['volume'] : 0.00,
                'purchasing_price' => $_POST['purchasing_price'] ? $_POST['purchasing_price'] : 0.00,
                'box_spec'         => $_POST['box_spec'],
                'net_weight'       => $_POST['net_weight'], 
            );
            $basicMaterialExtObj->insert($addExtData);

            //保存物料库存信息
            // redis库存高可用，废弃掉直接修改db库存、冻结的方法
            $addStockData = array(
                'bm_id'        => $addData['bm_id'],
                // 'store'        => $_POST['store'] ? $_POST['store'] : 0,
                // 'store_freeze' => $_POST['store_freeze'] ? $_POST['store_freeze'] : 0,
                'store'        => 0,
                'store_freeze' => 0,
            );
            $basicMaterialStockObj->insert($addStockData);

            //保存特殊扫码配置信息
            $addScanConfInfo = array(
                'bm_id'        => $addData['bm_id'],
                'openscan'     => $_POST['special_setting'] ? $_POST['special_setting'] : "",
                'fromposition' => $_POST['first_num'] ? $_POST['first_num'] : "",
                'toposition'   => $_POST['last_num'] ? $_POST['last_num'] : "",
            );
            $basicMaterialConfObjSpe->insert($addScanConfInfo);

            //logs
            $operationLogObj = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('basic_material_add@wms', $addData['bm_id'], '基础物料添加');

            $saleMaterialInfo = $salesMaterialObj->dump(array('sales_material_bn'=>$addData['material_bn'],'sales_material_type'=>['1','6']), '*');
            // 同步更新税收分类编码
            if ($saleMaterialInfo && $saleMaterialInfo['sales_material_bn'] == $addData['material_bn']){
                $saleUpdateData = [];
                if ($addData['tax_rate']) {
                    $saleUpdateData['tax_rate'] = $addData['tax_rate'];
                }
                if ($addData['tax_name']) {
                    $saleUpdateData['tax_name'] = $addData['tax_name'];
                }
                if ($addData['tax_code']) {
                    $saleUpdateData['tax_code'] = $addData['tax_code'];
                }
                if ($saleUpdateData) {
                    $salesMaterialObj->update($saleUpdateData,['sm_id' => $saleMaterialInfo['sm_id']]);
                }
            }

            // =============== 处理物料图片上传 ===============
            // 新增物料时，如果有图片上传就直接上传
            if (isset($_FILES['material_image']) && !empty($_FILES['material_image']['name']) && 
                !empty($_FILES['material_image']['tmp_name']) && 
                $_FILES['material_image']['size'] > 0 && 
                $_FILES['material_image']['error'] === UPLOAD_ERR_OK) {
                
                try {
                    // 获取图片模型
                    $imageModel = app::get('image')->model('image');
                    
                    // 获取上传的文件信息
                    $uploadFile = $_FILES['material_image'];
                    $imageName = $uploadFile['name'];
                    $imageTmpPath = $uploadFile['tmp_name'];
                    
                    // 使用封装好的一站式上传方法（包含完整验证）
                    $imageResult = $imageModel->uploadAndAttach(
                        $imageTmpPath,           // 图片文件路径
                        'material',              // 目标类型
                        $addData['bm_id'],       // 物料ID
                        $imageName,              // 图片名称
                        null,                    // 不生成不同尺寸
                        false                    // 不添加水印
                    );

                    if (isset($imageResult['error'])) {
                        $this->end(false, '物料图片上传失败：' . $imageResult['error']);
                    }
                    
                    if (!$imageResult) {
                        $this->end(false, '物料图片上传失败，请重试');
                    }
                } catch (Exception $e) {
                    $this->end(false, '物料图片上传失败：' . $e->getMessage());
                }
            }
            
            $this->end(true, '操作成功');
        } else {
            $this->end(false, '保存失败');
        }

    }

    /**
     * 基础物料编辑的展示页面方法
     * 
     * @param Int $bm_id
     * @return Boolean
     */
    public function edit($bm_id)
    {
        $this->begin('index.php?app=material&ctl=admin_material_basic&act=index');
        if (empty($bm_id)) {
            $this->end(false, '操作出错，请重新操作');
        }

        $basicMaterialObj        = app::get('material')->model('basic_material');
        $basicMaterialExtObj     = app::get('material')->model('basic_material_ext');
        $basicMaterialCodeObj    = app::get('material')->model('codebase');
        $basicMaterialConfObj    = app::get('material')->model('basic_material_conf');
        $basicMaterialConfObjSpe = app::get('material')->model('basic_material_conf_special');
        $basicMaterialBrand      = app::get('ome')->model('brand');
        
        $materialLib = kernel::single('material_basic_material');
        
        $tmp_bm_id = $bm_id = intval($bm_id);

        $basicMaterialInfo = $basicMaterialObj->dump($tmp_bm_id);
        if (!$basicMaterialInfo) {
            $this->end(false, '操作出错，请重新操作');
        }

        //检查部分按钮是否只读不可能修改
        $checkBasicLib = kernel::single('material_basic_check');
        $readonly      = $checkBasicLib->checkEditReadOnly($bm_id);

        $basicMaterialExtInfo = $basicMaterialExtObj->dump($tmp_bm_id);
        //这里的cat_id实际上是类型 type_id
        $basicMaterialExtInfo['type_id'] = $basicMaterialExtInfo['cat_id'];
        unset($basicMaterialExtInfo['cat_id'],$basicMaterialExtInfo['color'],$basicMaterialExtInfo['size']);
        $basicMaterialConfInfo  = $basicMaterialConfObj->dump($tmp_bm_id);
        $basicMaterialBrandInfo = $basicMaterialBrand->getList('brand_id,brand_name', array());
        unset($basicMaterialExtInfo['size'],$basicMaterialExtInfo['color']);
        $material_info = array_merge($basicMaterialInfo, (array) $basicMaterialExtInfo, (array) $basicMaterialConfInfo);

        $basicBarCode                   = $basicMaterialCodeObj->dump(array('bm_id' => $tmp_bm_id, 'type' => material_codebase::getBarcodeType()), 'code');
        $material_info['material_cdoe'] = empty($basicBarCode) ? '' : $basicBarCode['code'];

        $basicMaterialFeatureGrpObj  = app::get('material')->model('basic_material_feature_group');
        $basicMaterialFeatureGrpInfo = $basicMaterialFeatureGrpObj->getList('*', array('bm_id' => $tmp_bm_id), 0, 1);
        if ($basicMaterialFeatureGrpInfo) {
            $this->pagedata['bind_ftgp_id'] = $basicMaterialFeatureGrpInfo[0]['feature_group_id'];
            $this->pagedata['replacehtml']  = <<<EOF
<div id='hand-selected-product'>已选择了1个类目,<a href='javascript:void(0);' onclick='feature_group_selected_show();'>查看选中的类目.</a></div>
EOF;
        }

      
        $this->pagedata['material_info'] = $material_info;
        $this->pagedata['readonly']      = $readonly;

        #第三方仓储版_隐藏保质期管理(未安装invoice并且已安装omevirtualwms)
        $show_storage_life = true;
        if (app::get('invoice')->is_installed() == false) {
            if (app::get('omevirtualwms')->is_installed()) {
                $show_storage_life = false;
            }
        }
        $this->pagedata['show_storage_life'] = $show_storage_life;
        $this->pagedata['brand_info']        = $basicMaterialBrandInfo;

        $specialConf                   = $basicMaterialConfObjSpe->dump($tmp_bm_id);
        $this->pagedata['specialConf'] = $specialConf;

        #获取物料分类
        $goods_type_obj               = app::get('ome')->model('goods_type');
        $goods_types                  = $goods_type_obj->getList('type_id,name', array());
        $this->pagedata['goods_type'] = $goods_types;

        //是否有O2O门店
        $exist_store                   = $this->getStoreRs();
        $this->pagedata['exist_store'] = $exist_store;
        
        //物料属性
        $materialTypes = $materialLib->get_material_types();
        $this->pagedata['materialTypes'] = $materialTypes;

        //
        $propsMdl = app::get('material')->model('basic_material_props');

        $propsList = $propsMdl->getlist('*', ['bm_id' => $tmp_bm_id]);

        $arr_props = array();
        foreach($propsList as $v){

            $arr_props[$v['props_col']] = $v['props_value'];

        }
        
        // 销售团队
        $this->pagedata['bbu_list'] = [];
        if(app::get('dealer')->is_installed()){
            //检查是否安装dealer应用
            $bbuMdl = app::get('dealer')->model('bbu');
            $this->pagedata['bbu_list'] = $bbuMdl->getList('*', ['status' => 'active']);
        }

        $customcols = kernel::single('material_customcols')->getcols();

        $customcol_keys = $customcols ? array_column($customcols,'col_key') : [];

        $this->pagedata['customcol_keys'] = $customcol_keys;
        $this->pagedata['arr_props'] = $arr_props;

         //自定义
       
        $customcols = kernel::single('material_customcols')->getcols();
       
        foreach($customcols as $k=>$v){
            if($arr_props[$v['col_key']]){
                $customcols[$k]['col_value'] = $arr_props[$v['col_key']];
            }
        }
        $this->pagedata['customcols'] = $customcols;

        // =============== 获取物料图片信息 ===============
        $materialImages = $materialLib->getBasicMaterialImages($bm_id);
        $mainImage = $materialLib->getBasicMaterialMainImage($bm_id);
        
        $this->pagedata['material_images'] = $materialImages;
        $this->pagedata['main_image'] = $mainImage;

        $this->page('admin/material/basic/edit.html');
    }

    /**
     * 基础物料编辑提交方法
     * 
     * @param Int $bm_id
     * @return Boolean
     */
    public function toEdit()
    {
        $this->begin('index.php?app=material&ctl=admin_material_basic&act=index');

        #检查数据有效性
        $checkBasicLib = kernel::single('material_basic_check');
        $err_msg       = '';
        $_POST['edit'] = true; //编辑标识

        if (!$checkBasicLib->checkParams($_POST, $err_msg)) {
            $this->end(false, $err_msg);
        }

        $basicMaterialObj           = app::get('material')->model('basic_material');
        $basicMaterialExtObj        = app::get('material')->model('basic_material_ext');
        $basicMaterialFeatureGrpObj = app::get('material')->model('basic_material_feature_group');
        $basicMaterialStockObj      = app::get('material')->model('basic_material_stock');
        $basicMaterialConfObjSpe    = app::get('material')->model('basic_material_conf_special');
        $basicMaterialConfObj       = app::get('material')->model('basic_material_conf');
        $barocdeObj                 = app::get('material')->model('barcode');
        $basicMaterialCatObj        = app::get('material')->model('basic_material_cat');
        $salesMaterialObj           = app::get('material')->model('sales_material');

        //获取cat_path
        $cat_id = intval($_POST['cat_id']);
        if (!$cat_id) {
            //$this->end(false, '请选择分类');
            $cat_id = array('cat_path' => ','); //填充默认分类
        }
        $rs = $basicMaterialCatObj->dump($cat_id, 'cat_id,cat_path,min_price');
        if ($rs) {
            $cat_id    = $rs['cat_id'];
            $cat_path  = substr($rs['cat_path'] . $cat_id, 1);
            $min_price = floatval($rs['min_price']); //当前分类的最低限价
        } else {
            $cat_id    = 0;
            $cat_path  = '';
            $min_price = 0;
        }
        $updateData['cat_id']   = $cat_id;
        $updateData['cat_path'] = $cat_path;

        //更新基础物料基本信息
        $updateData['material_bn'] = $_POST['material_bn'];

        $updateData['material_name'] = $_POST['material_name'];
        
        if($_POST['type']){
            $updateData['type'] = $_POST['type'];
        }
        
        $updateData['bbu_id'] = $updateData['cos_id'] = NULL;
        if (isset($_POST['bbu_info']) && $_POST['bbu_info']) {
            [$updateData['bbu_id'], $updateData['cos_id']] = explode('|', $_POST['bbu_info']);
        }
        
        //编辑时唯一码选择否的话 做判断
        if ($_POST['serial_number'] == "false") {
            $mdl_p_s          = app::get('wms')->model('product_serial');
            $rs_serial_number = $mdl_p_s->dump(array("product_id" => $_POST['bm_id']));
            if (!empty($rs_serial_number)) {
                $this->end(false, "物料已存在唯一码，唯一码项不能选否。");
            }
        }
        $updateData['serial_number'] = $_POST['serial_number'];
        $updateData['visibled']      = $_POST['visibled'];
        $updateData['omnichannel']   = ($_POST['omnichannel'] == 1 ? 1 : 2);
        $updateData['material_spu']  = $_POST['material_spu'];
        $updateData['tax_rate']      = intval($_POST['tax_rate']);
        $updateData['tax_name']      = $_POST['tax_name'];
        $updateData['tax_code']      = $_POST['tax_code'];
        $updateData['color']      = $_POST['color'];
        $updateData['size']      = $_POST['size'];
        $updateData['is_o2o_sales']      = $_POST['is_o2o_sales'];
        $filter['bm_id']             = $_POST['bm_id'];
        
        // 是否管控库存
        $is_ctrl_store = isset($_POST['is_ctrl_store']) ? intval($_POST['is_ctrl_store']) : 1;
        $updateData['is_ctrl_store'] = ($is_ctrl_store === 2 ? 2 : 1);
        
        //快照
        $basicMaterialLib  = kernel::single('material_basic_material');
        $basicMaterialInfo = $basicMaterialLib->getBasicMaterialExt($filter['bm_id']);
        if(!$updateData['type']){
            $updateData['type'] = $basicMaterialInfo['type'];
        }
        $specialInfo = $basicMaterialConfObjSpe->dump(array('bm_id' => $filter['bm_id']), '*');
        if ($specialInfo) {
            $basicMaterialInfo = array_merge($basicMaterialInfo, $specialInfo);
        }

        $confInfo = $basicMaterialConfObj->dump(array('bm_id' => $filter['bm_id']), '*');
        if ($confInfo) {
            unset($confInfo['create_time']);
            $basicMaterialInfo = array_merge($basicMaterialInfo, $confInfo);
        }

        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
        // 快照,预先保存数据
        $combinationItemList = $basicMaterialCombinationItemsObj->getList('*', array('pbm_id' => $filter['bm_id']));
        if(!empty($combinationItemList)){
            $basicMaterialInfo['combination_items'] = $combinationItemList;
        }
        
        //更新
        $is_update = $basicMaterialObj->update($updateData, $filter);
        if ($is_update) {
            //保存保质期配置
            $useExpireConfData = array(
                'bm_id'      => $filter['bm_id'],
                'use_expire' => $_POST['use_expire'] == 1 ? 1 : 2,
                'warn_day'   => $_POST['warn_day'] ? $_POST['warn_day'] : 0,
                'quit_day'   => $_POST['quit_day'] ? $_POST['quit_day'] : 0,
                'use_expire_wms' => $_POST['use_expire'] == 1 ? 1 : 2,
                'shelf_life' => (int) $_POST['shelf_life'],
                'reject_life_cycle' => (int) $_POST['reject_life_cycle'],
                'lockup_life_cycle' => (int) $_POST['lockup_life_cycle'],
                'advent_life_cycle' => (int) $_POST['advent_life_cycle'],
            );
            $basicMaterialConfObj->save($useExpireConfData);

            $barcodes = $barocdeObj->dump($filter,'bm_id');
            if(!$barcodes){
                //保存条码信息
                $sdf = array(
                    'bm_id' => $filter['bm_id'],
                    'type'  => material_codebase::getBarcodeType(),
                    'code'  => $_POST['material_code'],
                );
                $barocdeObj->insert($sdf);

            }else{
                $codeData = array(
                    'code' => $_POST['material_code'],
                );
                $barocdeObj->update($codeData, $filter);
            }
            
            //如果关联半成品数据
            if ($updateData['type'] == 1 || $updateData['type'] == 4) {
              
                //删除原有半成品数据
                $basicMaterialCombinationItemsObj->delete(array('pbm_id' => $filter['bm_id']));
                //新增半成品数据
                if (isset($_POST['at'])) {
                    foreach ($_POST['at'] as $k => $v) {
                        $tmpChildMaterialInfo = $basicMaterialObj->dump($k, 'material_name,material_bn');

                        $addCombinationData = array(
                            'pbm_id'            => $filter['bm_id'],
                            'bm_id'             => $k,
                            'material_num'      => $v,
                            'material_name'     => $tmpChildMaterialInfo['material_name'],
                            'material_bn'       => $tmpChildMaterialInfo['material_bn'],
                            'material_bn_crc32' => sprintf('%u', crc32($tmpChildMaterialInfo['material_bn'])),
                        );
                        $basicMaterialCombinationItemsObj->insert($addCombinationData);
                        $addCombinationData = null;
                    }
                } else {
                    if(in_array($updateData['type'],array('4'))) {
                        $this->end(false, '礼盒未绑定普通商品');
                    }
                }
            } else {
                //如果是半成品的，更新下绑定的名称信息
                $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
                $basicMaterialCombinationItemsObj->update(array('material_name' => $updateData['material_name']), $filter);
            }

            //删除原有的关联特性
            $basicMaterialFeatureGrpObj->delete(array('bm_id' => $filter['bm_id']));
            //保存基础物料的关联的特性
            if ($_POST['ftgp_id']) {
                $addBindFeatureData = array(
                    'bm_id'            => $filter['bm_id'],
                    'feature_group_id' => $_POST['ftgp_id'],
                );
                $basicMaterialFeatureGrpObj->insert($addBindFeatureData);
                $addBindFeatureData = null;
            }

            //保存物料扩展信息
            $updateExtData = array(
                'cost'           => $_POST['cost'] ? $_POST['cost'] : 0.00,
                'retail_price'   => $_POST['retail_price'] ? $_POST['retail_price'] : 0.00,
                'weight'         => $_POST['weight'] ? $_POST['weight'] : 0.00,
                'length'         => $_POST['length'] ? $_POST['length'] : 0.00,
                'width'          => $_POST['width'] ? $_POST['width'] : 0.00,
                'high'           => $_POST['high'] ? $_POST['high'] : 0.00,
                'unit'           => $_POST['unit'],
                'specifications' => $_POST['material_specification'],
                'brand_id'       => empty($_POST['brand']) ? 0 : $_POST['brand'],
                'cat_id'         => empty($_POST['goods_type']) ? 0 : $_POST['goods_type'],
                'purchasing_price' => $_POST['purchasing_price'] ? $_POST['purchasing_price'] : 0.00,
                'volume'         => $_POST['volume'] ? $_POST['volume'] : 0.00,
                'purchasing_price' => $_POST['purchasing_price'] ? $_POST['purchasing_price'] : 0.00,
                'box_spec'       => $_POST['box_spec'],
                'net_weight'     => $_POST['net_weight'], 
            );
            //判断扩展表是否存在
            $basicextData = $basicMaterialExtObj->dump(array('bm_id' => $filter['bm_id']), 'bm_id');
            if ($basicextData) {
                $basicMaterialExtObj->update($updateExtData, $filter);
            } else {
                $updateExtData['bm_id'] = $filter['bm_id'];
                $basicMaterialExtObj->insert($updateExtData);
            }

            if($_POST['props']){
                $propsMdl = app::get('material')->model('basic_material_props');

                $propsdata = array();

                foreach($_POST['props'] as $pk=>$pv){

                    if($pv){
                        $propsdata = array(
                            'bm_id'         =>  $filter['bm_id'],
                            'props_col'     =>  $pk,
                            'props_value'   =>  $pv,
                        );

                        $props = $propsMdl->db_dump(array('bm_id'=>$filter['bm_id'],'props_col'=>$pk),'id');
                        if($props){
                            $propsdata['id'] = $props['id'];
                        }
                        $propsMdl->save($propsdata);
                    }
                }

                

            }
            //保存扫码配置信息
            $addScanConfInfo = array(
                'openscan'     => $_POST['special_setting'] ? $_POST['special_setting'] : '',
                'fromposition' => $_POST['first_num'] ? $_POST['first_num'] : '',
                'toposition'   => intval($_POST['last_num']) ? intval($_POST['last_num']) : 0,
            );
            $basicMaterialConfObjSpe->update($addScanConfInfo, $filter);
            //更新物料同步信息

            app::get('console')->model('foreign_sku')->update_status($filter['bm_id']);

            // =============== 同步更新销售物料信息 ===============
            // 查找对应的销售物料（普通商品和礼盒类型）
            $saleMaterialInfo = $salesMaterialObj->dump(array('sales_material_bn'=>$updateData['material_bn'],'sales_material_type'=>['1','6']), '*');
            
            // 如果找到对应的销售物料，同步更新相关字段
            if ($saleMaterialInfo && $saleMaterialInfo['sales_material_bn'] == $updateData['material_bn']){
                // 提取允许更新的字段：税率、开票名称、开票编码、销售状态
                $saleUpdateData = array_intersect_key($updateData, array_flip(['tax_rate', 'tax_name', 'tax_code', 'visibled']));
                
                // 转换visibled字段值：基础物料(1=销售,2=停售) -> 销售物料(1=销售,0=停售)
                if (isset($saleUpdateData['visibled'])) {
                    if ($saleUpdateData['visibled'] == 1) {
                        $saleUpdateData['visibled'] = 1; // 销售状态保持一致
                    } elseif ($saleUpdateData['visibled'] == 2) {
                        $saleUpdateData['visibled'] = 0; // 停售状态：基础物料2 -> 销售物料0
                    }
                }

                // 过滤掉空值，只保留有值的字段进行更新
                $saleUpdateData = array_filter($saleUpdateData, function($value) {
                    return $value !== '' && $value !== null;
                });
                
                // 如果有需要更新的字段，则执行更新操作
                if ($saleUpdateData) {
                    $salesMaterialLib = kernel::single('material_sales_material');
                    $salesMaterialLib->updateSalesMaterial($saleMaterialInfo['sm_id'], $saleUpdateData, '基础物料变化');
                }
            }
            
            // =============== 处理物料图片上传 ===============
            // 获取当前提交的图片ID（可能为空，表示用户删除了图片）
            $submittedImageId = !empty($_POST['current_image_id']) ? $_POST['current_image_id'] : null;
            
            // 获取数据库里原有的图片ID
            $originalImageId = null;
            if ($filter['bm_id']) {
                $imageModel = app::get('image')->model('image');
                $attachedImages = $imageModel->getAttachedImages('material', $filter['bm_id']);
                if ($attachedImages) {
                    $originalImageId = $attachedImages[0]['image_id'];
                }
            }
            
            // 比较图片ID是否发生变化
            if ($submittedImageId !== $originalImageId) {
                // 图片ID发生变化，需要处理
                
                // 如果有原图片，先删除
                if ($originalImageId) {
                    $imageModel->detach($originalImageId, 'material', $filter['bm_id'], true);
                }
                
                // 如果有新图片上传，则上传新图片
                if (isset($_FILES['material_image']) && !empty($_FILES['material_image']['name']) && 
                    !empty($_FILES['material_image']['tmp_name']) && 
                    $_FILES['material_image']['size'] > 0 && 
                    $_FILES['material_image']['error'] === UPLOAD_ERR_OK) {
                    
                    try {
                        $uploadFile = $_FILES['material_image'];
                        $imageName = $uploadFile['name'];
                        $imageTmpPath = $uploadFile['tmp_name'];
                        
                        $imageResult = $imageModel->uploadAndAttach(
                            $imageTmpPath,           // 图片文件路径
                            'material',              // 目标类型
                            $filter['bm_id'],        // 物料ID
                            $imageName,              // 图片名称
                            null,                    // 不生成不同尺寸
                            false                    // 不添加水印
                        );
                        
                        if (isset($imageResult['error'])) {
                            $this->end(false, '物料图片上传失败：' . $imageResult['error']);
                        }
                        
                        if (!$imageResult) {
                            $this->end(false, '物料图片上传失败，请重试');
                        }
                    } catch (Exception $e) {
                        $this->end(false, '物料图片上传失败：' . $e->getMessage());
                    }
                }
                // 如果没有新图片上传且submittedImageId为空，说明用户删除了图片
            }
            // 如果图片ID没有变化，什么都不做，保持原图片

            //logs
            $log_memo        = serialize($basicMaterialInfo);
            $operationLogObj = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('basic_material_edit@wms', $filter['bm_id'], $log_memo);

            $this->end(true, '保存成功');
        } else {
            $this->end(false, '保存失败');
        }

    }
    
    /**
     * 基础物料查看详情的展示页面方法
     * @Author: xueding
     * @Vsersion: 2022/5/26 上午10:27
     * @param $bm_id
     */
    
    public function detail($bm_id)
    {
        $this->begin('index.php?app=material&ctl=admin_material_basic&act=index');
        if (empty($bm_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        
        $basicMaterialObj        = app::get('material')->model('basic_material');
        $basicMaterialExtObj     = app::get('material')->model('basic_material_ext');
        $basicMaterialCodeObj    = app::get('material')->model('codebase');
        $basicMaterialConfObj    = app::get('material')->model('basic_material_conf');
        $basicMaterialConfObjSpe = app::get('material')->model('basic_material_conf_special');
        $basicMaterialBrand      = app::get('ome')->model('brand');
        
        $materialLib = kernel::single('material_basic_material');
        
        $tmp_bm_id = $bm_id = intval($bm_id);
        
        $basicMaterialInfo = $basicMaterialObj->dump($tmp_bm_id);
        if (!$basicMaterialInfo) {
            $this->end(false, '操作出错，请重新操作');
        }
        
        //检查部分按钮是否只读不可能修改
        $readonly      = array('use_expire'=>true,'type'=>true,);
        
        $basicMaterialExtInfo = $basicMaterialExtObj->dump($tmp_bm_id);
        //这里的cat_id实际上是类型 type_id
        $basicMaterialExtInfo['type_id'] = $basicMaterialExtInfo['cat_id'];
        unset($basicMaterialExtInfo['cat_id']);
        $basicMaterialConfInfo  = $basicMaterialConfObj->dump($tmp_bm_id);
        $basicMaterialBrandInfo = $basicMaterialBrand->getList('brand_id,brand_name', array());
        $showCostPrice = true;
        if (!kernel::single('desktop_user')->has_permission('cost_price')) {
            $showCostPrice = false;
        }
        $this->pagedata['show_cost_price'] = $showCostPrice;
        $material_info = array_merge($basicMaterialInfo, (array) $basicMaterialExtInfo, (array) $basicMaterialConfInfo);
        
        $basicBarCode                   = $basicMaterialCodeObj->dump(array('bm_id' => $tmp_bm_id, 'type' => material_codebase::getBarcodeType()), 'code');
        $material_info['material_cdoe'] = empty($basicBarCode) ? '' : $basicBarCode['code'];
        
        $basicMaterialFeatureGrpObj  = app::get('material')->model('basic_material_feature_group');
        $basicMaterialFeatureGrpInfo = $basicMaterialFeatureGrpObj->getList('*', array('bm_id' => $tmp_bm_id), 0, 1);
        if ($basicMaterialFeatureGrpInfo) {
            $this->pagedata['bind_ftgp_id'] = $basicMaterialFeatureGrpInfo[0]['feature_group_id'];
            $this->pagedata['replacehtml']  = <<<EOF
<div id='hand-selected-product'>已选择了1个类目,<a href='javascript:void(0);' onclick='feature_group_selected_show();'>查看选中的类目.</a></div>
EOF;
        }
        
        $this->pagedata['material_info'] = $material_info;
        $this->pagedata['readonly']      = $readonly;
        
        #第三方仓储版_隐藏保质期管理(未安装invoice并且已安装omevirtualwms)
        $show_storage_life = true;
        if (app::get('invoice')->is_installed() == false) {
            if (app::get('omevirtualwms')->is_installed()) {
                $show_storage_life = false;
            }
        }
        $this->pagedata['show_storage_life'] = $show_storage_life;
        $this->pagedata['brandList'] = $basicMaterialBrandInfo;
        
        $specialConf                   = $basicMaterialConfObjSpe->dump($tmp_bm_id);
        $this->pagedata['specialConf'] = $specialConf;
        
        #获取物料分类
        $goods_type_obj               = app::get('ome')->model('goods_type');
        $goods_types                  = $goods_type_obj->getList('type_id,name', array());
        $this->pagedata['goods_types'] = $goods_types;
        
        //是否有O2O门店
        $exist_store                   = $this->getStoreRs();
        $this->pagedata['exist_store'] = $exist_store;
        
        //物料属性
        $materialTypes = $materialLib->get_material_types();
        $this->pagedata['materialTypes'] = $materialTypes;
        
        $logObj   = app::get('ome')->model('operation_log');
    
        /* 本订单日志 */
        $logList    = $logObj->read_log(array('obj_id'=>$bm_id, 'obj_type'=>'basic_material@material'), 0, -1);
        foreach($logList as $k => $v)
        {
            $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
        }
    
        $this->pagedata['data'] = $logList;
        
        // 销售团队
        $this->pagedata['bbu_list'] = [];
        if(app::get('dealer')->is_installed()){
            //检查是否安装dealer应用
            $bbuMdl = app::get('dealer')->model('bbu');
            $this->pagedata['bbu_list'] = $bbuMdl->getList('*', ['status' => 'active']);
        }

        // =============== 获取物料属性信息 ===============
        $propsMdl = app::get('material')->model('basic_material_props');
        $propsList = $propsMdl->getlist('*', ['bm_id' => $tmp_bm_id]);
        
        $arr_props = array();
        foreach($propsList as $v){
            $arr_props[$v['props_col']] = $v['props_value'];
        }
        $this->pagedata['arr_props'] = $arr_props;
        
        $customcols = kernel::single('material_customcols')->getcols();
        foreach($customcols as $k=>$v){
            if($arr_props[$v['col_key']]){
                $customcols[$k]['col_value'] = $arr_props[$v['col_key']];
            }
        }
        $this->pagedata['customcols'] = $customcols;

        // =============== 获取物料图片信息 ===============
        $materialImages = $materialLib->getBasicMaterialImages($bm_id);
        $mainImage = $materialLib->getBasicMaterialMainImage($bm_id);
        
        $this->pagedata['material_images'] = $materialImages;
        $this->pagedata['main_image'] = $mainImage;

        $this->singlepage('admin/material/basic/detail.html');
    }
    /**
     * 基础物料列表弹窗数据获取方法
     * 
     * @param Void
     * @return String
     */
    public function findMaterial()
    {
        $this->view_source = 'dialog';

        //只能选择可售的物料
        $base_filter['visibled'] = 1;

        if (in_array($_GET['type'],array('2','3'))) {
            $base_filter['type'] = $_GET['type'];
        }
        if ($_GET['type'] == 'giftpackage'){
            $base_filter['type'] = array('1','3');
        }
        
        if (isset($_GET['cos_id'])) {
            $base_filter['cos_id'] = $_GET['cos_id'];
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

    /**
     * 基础物料列表弹窗选中物料信息查询方法
     * 
     * @param Int $bm_id
     * @return String
     */
    public function getMaterial()
    {
        $bm_id         = $_POST['bm_id'];
        $material_bn   = $_GET['material_bn'];
        $material_name = $_GET['material_name'];
        $material_type = $_GET['material_type'];
        $mode = $_GET['mode']; //获取方式
        if (is_array($bm_id)) {
            if ($bm_id[0] == "_ALL_") {
                $filter = '';
            } else {
                $filter['bm_id'] = $bm_id;
            }
        }

        if ($material_bn) {
            $filter = array(
                'material_bn' => $material_bn,
            );
        }

        if ($material_name) {
            $filter = array(
                'material_name' => $material_name,
            );
        }
        if (in_array($material_type,array('6'))){
            $filter = array(
                'material_type' => 4,
            );
        }
        $basicMaterialObj   = app::get('material')->model('basic_material');
        $filter['use_like'] = 1;
        $data = $basicMaterialObj->getList('bm_id,material_name,material_bn,cat_id,cat_path', $filter, 0, -1);

        if (!empty($data)) {
            $bm_ids = array();
            foreach ($data as $k => $item) {
                $bm_id = $item['bm_id'];

                $item['num']  = null;
                $item['rate'] = 0;
                
                //福袋组合选中比例(%)
                if($mode){
                    $item['ratio'] = '随机';
                }
                
                $rows[] = $item;

                $bm_ids[] = $item['bm_id'];
            }

            //扩展信息
            $costList            = $retail_prices = array();
            $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
            
            $extList = $basicMaterialExtObj->getList('bm_id,cost,brand_id,cost,retail_price,specifications', array('bm_id'=>$bm_ids));
            
            $extList = array_column($extList, null, 'bm_id');
            $brandIds = array_column($extList, 'brand_id');
            $brand = app::get('ome')->model('brand')->getList('brand_id, brand_name', ['brand_id'=>$brandIds]);
            $brandInfo = array_column($brand, null, 'brand_id');
            foreach ($extList as $key => $val) {
                $bm_id = $val['bm_id'];

                //成本价
                $costList[$bm_id]['cost'] = $val['cost'];
                $retail_prices[$bm_id]['retail_price'] = $val['retail_price'];
            }

            //显示成本价
            foreach ($rows as $key => $val)
            {
                $bm_id = $val['bm_id'];
                $rows[$key]['brand_id'] = $extList[$bm_id]['brand_id'];
                $rows[$key]['brand_name'] = $brandInfo[$extList[$bm_id]['brand_id']]['brand_name'];
                $rows[$key]['cost'] = ($costList[$bm_id]['cost'] ? $costList[$bm_id]['cost'] : 0.00);
                $rows[$key]['retail_price'] = ($retail_prices[$bm_id]['retail_price'] ? $retail_prices[$bm_id]['retail_price'] : 0.00);
                $rows[$key]['specifications'] = $extList[$bm_id]['specifications'];
            }

            unset($bm_ids, $data, $costList);
        }

        echo json_encode($rows);
    }

    /**
     * 基础物料组合信息异步加载方法
     * 
     * @param Int $bm_id
     * @return String
     */
    public function getEditMaterial($bm_id)
    {
        if ($bm_id == '') {
            $bm_id = $_POST['p[0]'];
        }

        $basicMaterialObj                 = app::get('material')->model('basic_material');
        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');

        $rows  = array();
        $items = $basicMaterialCombinationItemsObj->getList('bm_id,material_bn,material_name,material_num', array('pbm_id' => $bm_id), 0, -1);

        echo json_encode($items);
    }

    /**
     * 基础物料组合信息异步加载方法
     * 
     * @param Int $bm_id
     * @return String
     */
    public function batchGenSalesMaterial()
    {
        $this->_request = kernel::single('base_component_request');
        $material_info  = $this->_request->get_post();

        if (!isset($material_info['isSelectedAll']) && empty($material_info['bm_id'])) {
            $error_msg = '请选择要生成销售物料的基础物料';
        } else {
            $lastBatchGenSalesMaterial = app::get('material')->getConf('lastBatchGenSalesMaterial'); //上次执行批量生成销售物料的时间
            $getMaterialIntervalTime   = 5;

            if (($lastBatchGenSalesMaterial['execTime'] + 60 * $getMaterialIntervalTime) < time()) {
                $this->pagedata['is_allow'] = true;
            } else {
                $this->pagedata['is_allow'] = false;
            }
        }

        $basicMaterialObj = app::get('material')->model('basic_material');
        if (isset($material_info['isSelectedAll'])) {
            unset($material_info['isSelectedAll']);
        }

        $materialList = $basicMaterialObj->getList('bm_id', $material_info, 0, -1);
        /*
        $count = 0;
        $i = 0;
        $materialGroup = array();
        foreach($materialList as $k => $material){
        if($count == 10){
        $count = 0;
        $i++;
        }

        $materialGroup[$i]['materials'] .= $material['bm_id'].",";

        $count++;
        }
         */

        $this->pagedata['materialCnt']   = count($materialList);
        $this->pagedata['materialGroup'] = json_encode($materialList);

        $this->pagedata['bm_id'] = serialize($material_info['bm_id']);

        $this->pagedata['lastBatchGenSalesMaterial'] = !empty($lastBatchGenSalesMaterial['execTime']) ? date('Y-m-d H:i:s', $lastBatchGenSalesMaterial['execTime']) : '';
        $this->pagedata['getMaterialIntervalTime']   = $getMaterialIntervalTime;
        $this->pagedata['currentTime']               = time();

        $this->display('admin/material/basic/gensalesmaterial.html');
    }

    /**
     * dobatchGen
     * @return mixed 返回值
     */
    public function dobatchGen()
    {
        //传入的物料格式 id1,id2,id3,一次10个,页面js变量控制
        $params = $_POST['ajaxParams'];
        if (empty($params)) {
            echo $this->_ajaxRespone();
            exit;
        }

        /* 执行时间判断 start */
        $pageBn                    = intval($_POST['pageBn']);
        $lastBatchGenSalesMaterial = app::get('material')->getConf('lastBatchGenSalesMaterial'); //上次获取订单信息(key为execTime表示执行时间，key为pageBn表示页面编号),
        $getMaterialIntervalTime   = 5;

        if ($pageBn != $lastBatchGenSalesMaterial['pageBn'] && ($lastBatchGenSalesMaterial['execTime'] + 60 * $getMaterialIntervalTime) > time()) {
            echo $this->_ajaxRespone();
            exit;
        }
        if ($pageBn != $lastBatchGenSalesMaterial['pageBn'] && $pageBn < $lastBatchGenSalesMaterial['execTime']) {
            echo $this->_ajaxRespone();
            exit;
        }

        //记录本次获取订单时间
        $currentGetOrder = array(
            'execTime' => time(),
            'pageBn'   => $pageBn,
        );
        app::get('material')->setConf('lastBatchGenSalesMaterial', $currentGetOrder);
        /* 执行时间判断 end */

        //基础物料转成销售处理
        $result = kernel::single('material_basic_exchange')->process($params);
        echo $this->_ajaxRespone($result);
    }

    /**
     *  对输入的内容进行格式化输出至AJAX
     * 
     * @author hzjsq (2011/3/24)
     * @param Mixed $param 要转换的内容
     * @return String
     */
    private function _ajaxRespone($param='')
    {

        if (empty($param)) {

            return json_encode(array('total' => 0, 'succ' => 0, 'fail' => 0));
        } else {

            return json_encode($param);
        }
    }

    /**
     * @description 显示关联的物料
     * @access public
     * @param void
     * @return void
     */
    public function showMaterialBase()
    {
        $bm_id = kernel::single('base_component_request')->get_post('bm_id');

        if ($bm_id) {
            #前端店铺_规则应用传值
            if (!is_array($bm_id)) {
                $bm_id = explode(',', $bm_id);
            }

            $this->pagedata['_input'] = array(
                'name'     => 'bm_id',
                'idcol'    => 'bm_id',
                '_textcol' => 'material_name',
            );

            $basicMaterialObj                  = app::get('material')->model('basic_material');
            $list                              = $basicMaterialObj->getList('bm_id,material_name', array('bm_id' => $bm_id, 'visibled' => 1), 0, -1, 'bm_id asc');
            $this->pagedata['_input']['items'] = $list;
        }

        $this->display('admin/material/basic/show_material_base.html');
    }

    /**
     * @description 显示绑定的特性
     * @access public
     * @param void
     * @return void
     */
    public function showFeatureGrp()
    {
        $ftgp_id = kernel::single('base_component_request')->get_post('ftgp_id');

        if ($ftgp_id) {
            $this->pagedata['_input'] = array(
                'name'     => 'ftgp_id',
                'idcol'    => 'ftgp_id',
                '_textcol' => 'ftgp_name',
            );


        }

        $this->display('admin/material/basic/show_feature_group.html');
    }

    /**
     * @description 批量设置保质期配置的页面
     * @access public
     * @param void
     * @return void
     */
    public function BatchUpExpire($view)
    {

        $sub_menu = $this->_views();
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }

        $filter                            = array_merge((array) $_POST, (array) $base_filter);
        $basicMaterialObj                  = app::get('material')->model('basic_material');
        $basicMaterialObj->filter_use_like = true;
        $count                             = $basicMaterialObj->count($filter);

        $this->pagedata['total']  = $count;
        $this->pagedata['filter'] = http_build_query($filter);
        $this->display('admin/material/basic/batch_update_expire.html');
    }

    /**
     * @description 批量设置属性的页面
     * @access public
     * @param void
     * @return void
     */
    public function batchUpProperty($view)
    {

        $sub_menu = $this->_views();
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }

        $filter                            = array_merge((array) $_POST, (array) $base_filter);
        $basicMaterialObj                  = app::get('material')->model('basic_material');
        $basicMaterialObj->filter_use_like = true;
        $count                             = $basicMaterialObj->count($filter);
        $basicMaterialBrand      = app::get('ome')->model('brand');
        $basicMaterialBrandInfo = $basicMaterialBrand->getList('brand_id,brand_name', array());
        $this->pagedata['brand_info']        = $basicMaterialBrandInfo;
        #获取物料分类
        $goods_type_obj               = app::get('ome')->model('goods_type');
        $goods_types                  = $goods_type_obj->getList('type_id,name', array());
        $this->pagedata['goods_type'] = $goods_types;
        $this->pagedata['total']  = $count;
        $this->pagedata['filter'] = http_build_query($filter);
        $this->display('admin/material/basic/batch_update_property.html');
    }
    
    /**
     * @description 批量设置销售团队
     * @access public
     * @param void
     * @return void
     */
    public function batchSetBbu($view)
    {
        $sub_menu = $this->_views();
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }
        $basicMaterialObj                  = app::get('material')->model('basic_material');
        $basicMaterialObj->filter_use_like = true;
        $filter                            = array_merge((array) $_POST, (array) $base_filter);
        $count                             = $basicMaterialObj->count($filter);
        $this->pagedata['total']           = $count;
        $this->pagedata['filter']          = http_build_query($filter);
        $this->pagedata['bbuList']         = [];
        if(app::get('dealer')->is_installed()){
            //检查是否安装dealer应用
            $bbuMdl                     = app::get('dealer')->model('bbu');
            $this->pagedata['bbuList']  = $bbuMdl->getList('*', ['status'=>'active']);
        }
        $this->display('admin/material/basic/batch_set_bbu.html');
    }
    
    /**
     * @description 设置销售团队
     * @access public
     * @param array $_POST
     * @return void
     */
    public function doBatchSetBbu()
    {
        
        $page_no   = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
        $page_size = 10;
        $offset    = ($page_no - 1) * $page_size;
        $total     = intval($_GET['total']);
        parse_str($_POST['filter'], $filter);
        
        $bmMdl                  = app::get('material')->model('basic_material');
        $bmMdl->filter_use_like = true;
        $materialList           = $bmMdl->getList('*', $filter, $offset, $page_size);
        $succ_num               = $fail_num = 0;
        
        $updateData = [];
        [$updateData['bbu_id'], $updateData['cos_id']] = explode('|', $_POST['bbu_info']);
        
        if ($materialList) {
            $bmIds = array_column($materialList, 'bm_id');
            $bmMdl->update($updateData, ['bm_id|in' => $bmIds]);
            $succ_num = count($materialList) - $fail_num;
        }
        
        $result = array('status' => 'running', 'data' => array('succ_num' => $succ_num, 'fail_num' => $fail_num));
        
        if (($page_size * $page_no) >= $total) {
            $result['status']       = 'complete';
            $result['data']['rate'] = '100';
        } else {
            $result['data']['rate'] = $page_no * $page_size / $total * 100;
        }
        
        echo json_encode($result);exit;
    }
    
    /**
     * @description 设置保质期
     * @access public
     * @param array $_POST
     * @return void
     */
    public function doBatchUpExpire()
    {
        $checkBasicLib = kernel::single('material_basic_check');

        $page_no   = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
        $page_size = 10;
        $offset    = ($page_no - 1) * $page_size;
        $total     = intval($_GET['total']);
        parse_str($_POST['filter'], $filter);
        $basicMaterialObj                  = app::get('material')->model('basic_material');
        $basicMaterialObj->filter_use_like = true;
        $materialList                      = $basicMaterialObj->getList('*', $filter, $offset, $page_size);
        $succ_num                          = $fail_num                          = 0;
        if ($materialList) {
            foreach ($materialList as $material_info) {

                #如果有批次明细 或者 采购明细 就不能变更保质期的开关
                $readonly = $checkBasicLib->checkEditReadOnly($material_info['bm_id']);
                if ($readonly['use_expire']) {
                    $fail_num++;
                } else {
                    $arr_bm_id[] = $material_info['bm_id'];
                }
            }
            $succ_num = count($materialList) - $fail_num;

            if ($arr_bm_id) {

                $basicMaterialConfObj = app::get('material')->model('basic_material_conf');

                $updateData           = array(
                    'use_expire' => $_POST['use_expire'] == 1 ? 1 : 2,
                    'warn_day'   => $_POST['warn_day'] ? $_POST['warn_day'] : 0,
                    'quit_day'   => $_POST['quit_day'] ? $_POST['quit_day'] : 0,
                );
                if ($basicMaterialConfObj->count(array('bm_id'=>$arr_bm_id)) == 0) {
                    foreach ($arr_bm_id as $bm_id) {
                        $updateDatap['bm_id'] = $bm_id;
                        $basicMaterialConfObj->insert($updateData);
                    }
                } else {
                    $confList = $basicMaterialConfObj->getList('bm_id',array('bm_id' => $arr_bm_id));
                    foreach ($confList as $conf) {
                        if (in_array($conf['bm_id'],$arr_bm_id)) {
                            $updateList[] = $conf['bm_id'];
                        }
                    }
                    $insertList = array_diff($updateList,$arr_bm_id);
                    foreach ($insertList as $bm_id) {
                        $updateDatap['bm_id'] = $bm_id;
                        $basicMaterialConfObj->insert($updateData);
                    }
                    $basicMaterialConfObj->update($updateData, array('bm_id' => $updateList));
                }
            }
        }

        $result = array('status' => 'running', 'data' => array('succ_num' => $succ_num, 'fail_num' => $fail_num));

        if (($page_size * $page_no) >= $total) {
            $result['status']       = 'complete';
            $result['data']['rate'] = '100';
        } else {
            $result['data']['rate'] = $page_no * $page_size / $total * 100;
        }

        echo json_encode($result);exit;
    }
    /**
     * @description 设置属性
     * @access public
     * @param array $_POST
     * @return void
     */
    public function doBatchUpProperty()
    {
        $checkBasicLib = kernel::single('material_basic_check');

        $page_no   = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
        $page_size = 10;
        $offset    = ($page_no - 1) * $page_size;
        $total     = intval($_GET['total']);
        parse_str($_POST['filter'], $filter);
        $basicMaterialObj                  = app::get('material')->model('basic_material');
        $basicMaterialObj->filter_use_like = true;
        $materialList                      = $basicMaterialObj->getList('*', $filter, $offset, $page_size);
        $succ_num                          = $fail_num                          = 0;
        $goods_type_obj = app::get('ome')->model('goods_type');
        $goods_type_obj->getList('type_id,name', array());
        if($_POST['goods_type'] > 0) {
            $goodsType = $goods_type_obj->db_dump(['type_id'=>$_POST['goods_type']], 'type_id,name');
        }
        $basicMaterialBrand      = app::get('ome')->model('brand');
        if($_POST['brand'] > 0) {
            $brandInfo = $basicMaterialBrand->db_dump(['brand_id'=>$_POST['brand']],'brand_id,brand_name');
        }
        $basicMaterialCatObj = app::get('material')->model('basic_material_cat');
        if($_POST['cat_id'] > 0) {
            $catInfo = $basicMaterialCatObj->db_dump(['cat_id'=>$_POST['cat_id']],'cat_id,cat_path,cat_name');
        }
        if ($materialList) {
            foreach ($materialList as $v) {
                $succ_num += 1;
                $str = '';
                if($goodsType) {
                    app::get('material')->model('basic_material_ext')->update(['cat_id'=>$goodsType['type_id']], ['bm_id'=>$v['bm_id']]);
                    $str .= '类型-'.$goodsType['name'].';';
                }
                if($brandInfo) {
                    app::get('material')->model('basic_material_ext')->update(['brand_id'=>$brandInfo['brand_id']], ['bm_id'=>$v['bm_id']]);
                    $str .= '品牌-'.$brandInfo['brand_name'].';';
                }
                if($catInfo) {
                    $affect_row = $basicMaterialObj->update(['cat_id'=>$catInfo['cat_id'],'cat_path'=>substr($catInfo['cat_path'] . $catInfo['cat_id'], 1)], ['bm_id'=>$v['bm_id']]);
                    $str .= '分类-'.$catInfo['cat_name'].';';
                    if(!is_bool($affect_row)){
                        app::get('console')->model('foreign_sku')->update_status($v['bm_id']);
                    }
                }
                if($str) {
                    app::get('ome')->model('operation_log')->write_log('basic_material_property@wms',$v['bm_id'],"批量设置属性：".$str);
                }
            }
        }

        $result = array('status' => 'running', 'data' => array('succ_num' => $succ_num, 'fail_num' => $fail_num));

        if (($page_size * $page_no) >= $total || empty($materialList)) {
            $result['status']       = 'complete';
            $result['data']['rate'] = '100';
        } else {
            $result['data']['rate'] = $page_no * $page_size / $total * 100;
        }

        echo json_encode($result);exit;
    }

    /**
     * 批量设置全渠道页面
     */
    public function batchUpOmnichannel($view)
    {
        $sub_menu = $this->_views();
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }

        $base_filter = array('omnichannel' => $base_filter['omnichannel']);
        $filter      = array_merge((array) $_POST, (array) $base_filter);

        $basicMaterialObj                  = app::get('material')->model('basic_material');
        $basicMaterialObj->filter_use_like = true;

        $count = $basicMaterialObj->count($filter);

        $this->pagedata['total']  = $count;
        $this->pagedata['filter'] = http_build_query($filter);

        $this->display('admin/material/basic/batch_set_omnichannel.html');
    }

    /**
     * 批量设置全渠道
     */
    public function doBatchUpOmnichannel()
    {
        $page_no   = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
        $page_size = 10;
        $offset    = ($page_no - 1) * $page_size;
        $total     = intval($_GET['total']);

        parse_str($_POST['filter'], $filter);

        $omnichannel = ($_POST['omnichannel'] == 1 ? 1 : 2);

        $basicMaterialObj                  = app::get('material')->model('basic_material');
        $basicMaterialObj->filter_use_like = true;
        $materialList                      = $basicMaterialObj->getList('bm_id, omnichannel', $filter, $offset, $page_size);
        $succ_num                          = $fail_num                          = 0;

        if ($materialList) {
            foreach ($materialList as $material_info) {
                $arr_bm_id[] = $material_info['bm_id'];

                $succ_num++;
            }

            if ($arr_bm_id) {
                $basicMaterialObj->update(array('omnichannel' => $omnichannel), array('bm_id' => $arr_bm_id));
            }
        }

        $fail_num = count($materialList) - $succ_num;
        $result   = array('status' => 'running', 'data' => array('succ_num' => $succ_num, 'fail_num' => $fail_num));

        if (($page_size * $page_no) >= $total) {
            $result['status']       = 'complete';
            $result['data']['rate'] = '100';
        } else {
            $result['data']['rate'] = $page_no * $page_size / $total * 100;
        }

        echo json_encode($result);
        exit;
    }
    
    //判断O2O门店是否存在
    /**
     * 获取StoreRs
     * @return mixed 返回结果
     */
    public function getStoreRs()
    {
        //是否有O2O门店
        $storeObj  = app::get('o2o')->model('store');
        $storeInfo = $storeObj->getList('store_id', array(), 0, 1);
        if ($storeInfo) {
            return true;
        }

        return false;
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $goodsTypeObj = app::get('ome')->model('goods_type');
        $brandObj     = app::get('ome')->model('brand');
        $logObj       = app::get('ome')->model('operation_log');

        //日志
        $goodslog = $logObj->dump($log_id, 'memo');
        $memo     = unserialize($goodslog['memo']);

        //类型
        if ($memo['cat_id']) {
            $typeInfo          = $goodsTypeObj->dump(array('type_id' => $memo['cat_id']), 'type_id,name');
            $memo['type_name'] = $typeInfo['name'];
        }

        //品牌
        if ($memo['brand_id']) {
            $brandInfo          = $brandObj->dump(array('brand_id' => $memo['brand_id']), 'brand_id,brand_name');
            $memo['brand_name'] = $brandInfo['brand_name'];
        }

        //是否在售
        $memo['serial_number'] = ($memo['serial_number'] == 'true' ? '是' : '否');

        //是否在售
        $memo['is_sales'] = ($memo['visibled'] == 1 ? '在售' : '停售');

        //物料属性
        $materialLib = kernel::single('material_basic_material');
        $memo['is_type'] = $materialLib->get_material_types($memo['type']);

        //是否全渠道
        $memo['is_channel'] = ($memo['omnichannel'] == 1 ? '是' : '否');

        //特殊扫码配置
        if ($memo['openscan'] == 3) {
            $memo['openscan'] = '扫码中识别整个条码中第 ' . $memo['fromposition'] . ' 位到第 ' . $memo['toposition'] . '位';
        } else {
            $memo['openscan'] = '';
        }

        //保质期
        if ($memo['use_expire'] == 1) {
            $memo['show_storage_life'] = true;
            $memo['warn_day']          = $memo['warn_day'];
            $memo['quit_day']          = $memo['quit_day'];
        }

        $this->pagedata['data'] = $memo;

        $this->singlepage('admin/material/basic/history_log.html');
    }

    //批量停售
    /**
     * batchInvisible
     * @param mixed $view view
     * @return mixed 返回值
     */
    public function batchInvisible($view)
    {
        $this->begin('');
        $view     = intval($view);
        $sub_menu = $this->_views();
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }
        $mdl_ma_ba_ma = app::get('material')->model('basic_material');
        $offset       = 0;
        $limit        = 100;
        $bm_ids       = array();
        if (!$base_filter && isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //"全部"tab 全选的
            do {
                $rs = $mdl_ma_ba_ma->getList("bm_id", array("visibled" => "1"), $offset, $limit, "bm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_bm_ids = array();
                foreach ($rs as $var_r) {
                    $current_bm_ids[] = $var_r["bm_id"];
                }
                $bm_ids[] = $current_bm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } elseif (isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //其余的tab 全选的
            $filter_arr = $base_filter;
            unset($filter_arr["bm_id"]);
            $filter_arr = array_merge(array("visibled" => "1"), $filter_arr);
            do {
                $rs = $mdl_ma_ba_ma->getList("bm_id", $filter_arr, $offset, $limit, "bm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_bm_ids = array();
                foreach ($rs as $var_r) {
                    $current_bm_ids[] = $var_r["bm_id"];
                }
                $bm_ids[] = $current_bm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } else {
            //勾选的
            $bm_ids = array_chunk($_POST["bm_id"], $limit);
        }
        if (empty($bm_ids)) {
            $this->end(false, '无有效数据');
        }
        $update_arr = array("visibled" => 2);
        foreach ($bm_ids as $var_bm_id) {
            $filter_arr = array("bm_id" => $var_bm_id);
            $mdl_ma_ba_ma->update($update_arr, $filter_arr);
        }
        $this->end(true, '操作成功');
    }

    //批量在售
    /**
     * batchVisible
     * @param mixed $view view
     * @return mixed 返回值
     */
    public function batchVisible($view)
    {
        $this->begin('');
        $view     = intval($view);
        $sub_menu = $this->_views();
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }
        $mdl_ma_ba_ma = app::get('material')->model('basic_material');
        $offset       = 0;
        $limit        = 100;
        $bm_ids       = array();
        if (!$base_filter && isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //"全部"tab 全选的
            do {
                $rs = $mdl_ma_ba_ma->getList("bm_id", array("visibled" => "2"), $offset, $limit, "bm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_bm_ids = array();
                foreach ($rs as $var_r) {
                    $current_bm_ids[] = $var_r["bm_id"];
                }
                $bm_ids[] = $current_bm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } elseif (isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //其余的tab 全选的
            $filter_arr = $base_filter;
            unset($filter_arr["bm_id"]);
            $filter_arr = array_merge(array("visibled" => "2"), $filter_arr);
            do {
                $rs = $mdl_ma_ba_ma->getList("bm_id", $filter_arr, $offset, $limit, "bm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_bm_ids = array();
                foreach ($rs as $var_r) {
                    $current_bm_ids[] = $var_r["bm_id"];
                }
                $bm_ids[] = $current_bm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } else {
            //勾选的
            $bm_ids = array_chunk($_POST["bm_id"], $limit);
        }
        if (empty($bm_ids)) {
            $this->end(false, '无有效数据');
        }
        $update_arr = array("visibled" => 1);
        foreach ($bm_ids as $var_bm_id) {
            $filter_arr = array("bm_id" => $var_bm_id);
            $mdl_ma_ba_ma->update($update_arr, $filter_arr);
        }
        $this->end(true, '操作成功');
    }


    /**
     * 删除_material
     * @return mixed 返回值
     */
    public function delete_material(){

        $this->begin('index.php?app=material&ctl=admin_material_basic&act=index');
        $checkObj = kernel::single('material_basic_check');
        $salesObj = app::get('material')->model('sales_basic_material');
        $db = kernel::database();

        if($_POST['isSelectedAll'] == '_ALL_')
        {
            $this->end(false,'不支持全选');
        }else{


            if($_POST['bm_id']){
                foreach($_POST['bm_id'] as $bm_id){
                    $check_rs = $checkObj->checkEditReadOnly($bm_id);
                    if ($check_rs['type']){
                        $this->end(false,'有出入库记录不可以删除');
                    }
                    //判断是否有销售物料绑定
                    $sales_material = $salesObj->dump(array('bm_id'=>$bm_id),'bm_id');
                    if ($sales_material){
                        $this->end(false,'已关联销售物料不可以删除');
                    }
                    //删除相关联表

                    $db->exec("DELETE FROM sdb_material_basic_material WHERE bm_id=".$bm_id);
                    $db->exec("DELETE FROM sdb_material_basic_material_combination_items WHERE bm_id=".$bm_id);
                    $db->exec("DELETE FROM sdb_material_basic_material_combination_items WHERE pbm_id=".$bm_id);
                    $db->exec("DELETE FROM sdb_material_basic_material_conf WHERE bm_id=".$bm_id);
                    $db->exec("DELETE FROM sdb_material_basic_material_conf_special WHERE bm_id=".$bm_id);
                    $db->exec("DELETE FROM sdb_material_basic_material_ext WHERE bm_id=".$bm_id);
                    $db->exec("DELETE FROM sdb_material_basic_material_feature_group WHERE bm_id=".$bm_id);

                    $db->exec("DELETE FROM sdb_material_basic_material_storage_life WHERE bm_id=".$bm_id);
                    $db->exec("DELETE FROM sdb_material_basic_material_storage_life_bills WHERE bm_id=".$bm_id);
                    $db->exec("DELETE FROM sdb_material_codebase WHERE bm_id=".$bm_id);

                }
            }
        }
        $this->end(true, '命令已经被成功发送！！');
    }
    
    /**
     * 批量编辑唯一码
     * @param $type
     * @param $view
     * @date 2025-05-13 下午7:17
     */
    public function batchSerialNumber($type, $view)
    {
        $this->begin('');
        $view        = intval($view);
        $sub_menu    = $this->_views();
        $base_filter = [];
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }
        
        $getWhere = ['1' => 'false', '2' => 'true'];
        $upWhere  = ['1' => 'true', '2' => 'false'];
        
        $filter_arr = array_merge(array("serial_number" => $getWhere[$type]), $_POST);
        if ($base_filter) {
            $filter_arr = array_merge($filter_arr, $base_filter);
        }
        
        $mdl_ma_ba_ma = app::get('material')->model('basic_material');
        $offset       = 0;
        $limit        = 500;
        $bm_ids       = array();
        if (!$base_filter && isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //"全部"tab 全选的
            do {
                $rs = $mdl_ma_ba_ma->getList("bm_id", $filter_arr, $offset, $limit, "bm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_bm_ids = array();
                foreach ($rs as $var_r) {
                    $current_bm_ids[] = $var_r["bm_id"];
                }
                $bm_ids[] = $current_bm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } elseif (isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //其余的tab 全选的
            do {
                $rs = $mdl_ma_ba_ma->getList("bm_id", $filter_arr, $offset, $limit, "bm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_bm_ids = array();
                foreach ($rs as $var_r) {
                    $current_bm_ids[] = $var_r["bm_id"];
                }
                $bm_ids[] = $current_bm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } else {
            //勾选的
            $bm_ids = array_chunk($_POST["bm_id"], $limit);
        }
        if (empty($bm_ids)) {
            $this->end(false, '无有效数据');
        }
        $update_arr = array("serial_number" => $upWhere[$type]);
        foreach ($bm_ids as $var_bm_id) {
            $filter_arr = array("bm_id" => $var_bm_id);
            $mdl_ma_ba_ma->update($update_arr, $filter_arr);
        }
        $this->end(true, '操作成功');
    }
}
