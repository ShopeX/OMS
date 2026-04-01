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
class material_ctl_admin_material_sales extends desktop_controller
{

    public $workground = 'goods_manager';
    public $view_source = 'normal';

    /**
     * 获取价格计算方式配置
     * @return string 价格计算方式：'cost' 或 'retail_price'
     */

    private function _getPriceRateConfig()
    {
        return kernel::single('material_sales_setting')->getConfig('price_rate', 'retail_price');
    }
    

    


    /**
     * 销售物料列表分栏菜单
     * 
     * @param Null
     * @return Array
     */
    public function _views()
    {

        #不是销售列表时_隐藏Tab
        if ($_GET['act'] != 'index' && $this->view_source == 'normal') {
            return array();
        }

        $salesMaterialObj = app::get('material')->model('sales_material');

        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'optional' => false),
            1 => array('label' => app::get('base')->_('普通'), 'filter' => array('sales_material_type' => 1), 'optional' => false),
            2 => array('label' => app::get('base')->_('组合'), 'filter' => array('sales_material_type' => 2), 'optional' => false),
            3 => array('label' => app::get('base')->_('赠品'), 'filter' => array('sales_material_type' => 3), 'optional' => false),
            5 => array('label' => app::get('base')->_('多选一'), 'filter' => array('sales_material_type' => 5), 'optional' => false),
            6 => array('label' => app::get('base')->_('礼盒'), 'filter' => array('sales_material_type' => 6), 'optional' => false),
            7 => array('label' => app::get('base')->_('福袋'), 'filter' => array('sales_material_type' => 7), 'optional' => false),
        );

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $salesMaterialObj->count($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=material&ctl=admin_material_sales&act=index&view=' . $k;
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
        $use_buildin_export = kernel::single('desktop_user')->has_permission('sales_material_export');
        $use_buildin_import = kernel::single('desktop_user')->has_permission('sales_material_import');
        $use_buildin_delete = kernel::single('desktop_user')->has_permission('sales_material_delete');
        
        $base_filter = array();
        $params = array(
            'title'               => '销售物料',
            'actions'             => array(),
            'base_filter'         => $base_filter,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter'  => true,
            'use_buildin_export'  => $use_buildin_export,
            'use_buildin_import'  => false,
            'use_buildin_importxls'  => false,
        );
        if($use_buildin_import) {
            $params['actions'][] = array(
                'label'  => '导入',
                'href'   => $this->url.'&act=execlImportDailog&p[0]=sales_material',
                'target' => 'dialog::{width:550,height:350,title:\'导入\'}',
            );
            /*
            $params['actions'][] = array(
                'label'  => sprintf('导入(新)'),
                'href'   => sprintf('%s&act=displayImportV2&p[0]=%s', $this->url, 'material_sales_add'),
                'target' => sprintf('dialog::{width:760,height:300,title:\'%s\'}','导入'),
            );
            */
        }
        if (kernel::single('desktop_user')->has_permission('sales_material_add')) {
            $params['actions'][] = array(
                'label'  => '新建',
                'href'   => 'index.php?app=material&ctl=admin_material_sales&act=add',
                //'target' => '_blank',
            );
        }
        /*if (kernel::single('desktop_user')->has_permission('sales_material_import')) {
            $params['actions'][] = array(
                'label'  => '导出模板',
                'href'   => 'index.php?app=material&ctl=admin_material_sales&act=exportTemplate',
                'target' => '_blank',
            );
        }*/
        if ($use_buildin_delete) {
            $params['actions'][] = array(
                'label'   => $this->app->_('删除'),
                'confirm' => '你确定要删除此条记录吗？',
                'submit'  => 'index.php?app=material&ctl=admin_material_sales&act=delete_material',
                'target'  => 'refresh'
            );
        }

        if (kernel::single('desktop_user')->has_permission('sales_material_invoice_import')) {
            $params['actions'][] = array(
                'label'  => '导入开票信息',
                'href'   => $this->url . '&act=execlImportDailog&p[]=sales',
                'target' => 'dialog::{width:760,height:400,title:\'导入开票信息\'}',
            );
        }
    
        $actions_group[] = array(
            'label'   => '批量停售',
            'submit'  => "index.php?app=material&ctl=admin_material_sales&act=batchInvisible&p[0]=" . $_GET['view'],
            'confirm' => '你确定要对勾选的销售物料统一设为停售？',
            'target'  => 'refresh',
        );
        $actions_group[] = array(
            'label'   => '批量在售',
            'submit'  => "index.php?app=material&ctl=admin_material_sales&act=batchVisible&p[0]=" . $_GET['view'],
            'confirm' => '你确定要对勾选的销售物料统一设为在售？',
            'target'  => 'refresh',
        );
        //压入批量操作
        if($actions_group) {
            $params['actions'][] = array(
                "label" => "批量操作",
                "group" => $actions_group,
            );
        }

        $this->finder('material_mdl_sales_material', $params);
    }

    /**
     * @description 显示可售基础物料列表查询方法
     * @access public
     * @param void
     * @return void
     */
    public function finder_common()
    {
        $params = array(
            'title'                  => app::get('desktop')->_('基础物料列表'),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_buildin_setcol'     => true,
            'use_buildin_refresh'    => true,
            'finder_aliasname'       => 'finder_common',
            'alertpage_finder'       => true,
            'use_buildin_tagedit'    => false,
        );

        $this->finder($_GET['app_id'] . '_mdl_' . $_GET['object'], $params);
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

            $basicMaterialObj = app::get('material')->model('basic_material');
            $list             = $basicMaterialObj->getList('bm_id,material_name, material_bn', array('bm_id' => $bm_id), 0, -1, 'bm_id asc');

            //显示基础物料编码
            foreach ($list as $key => $val) {
                $list[$key]['material_name'] = '基础物料编码：' . $val['material_bn'] . '&nbsp;&nbsp;&nbsp;基础物料名称：' . $val['material_name'];
            }

            $this->pagedata['_input']['items'] = $list;
        }

        $this->display('admin/material/sales/show_materials.html');
    }

    /**
     * 销售物料新增的展示页面方法
     * 
     * @param void
     * @return void
     */
    public function add()
    {
        $classifyMdl = app::get('material')->model('customer_classify');
        
        //过滤o2o门店店铺
        $shopObj  = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', array('s_type' => 1), 0, -1);
        array_unshift($shopList, array('shop_id' => '_ALL_', 'name' => '全部店铺'));
        
        //客户分类
        $classList = $classifyMdl->getList('class_id,class_name', array('disabled'=>'false'), 0, -1);
        if($classList){
            $classList = array_column($classList, null, 'class_id');
        }
        
        $this->pagedata['classList'] = $classList;
        $this->pagedata['shops'] = $shopList;
        
        // 获取价格计算方式配置
        $priceConfig = kernel::single('material_sales_setting')->getPriceConfig();
        $this->pagedata['price_rate'] = $priceConfig['price_rate'];
        $this->pagedata['price_field'] = $priceConfig['price_field'];
        $this->pagedata['price_label'] = $priceConfig['price_label'];
        
        $this->page('admin/material/sales/add.html');
    }

    /**
     * 基础物料新增提交方法
     * 
     * @param Post
     * @return Boolean
     */
    public function toAdd()
    {
        $this->begin('index.php?app=material&ctl=admin_material_sales&act=index');

        #数据有效性检查
        $checkSalesLib = kernel::single('material_sales_check');
        $err_msg       = '';
        
        if (!$checkSalesLib->checkParams($_POST, $err_msg)) {
            $this->end(false, $err_msg);
        }
        
        $salesMaterialObj           = app::get('material')->model('sales_material');
        $salesMaterialExtObj        = app::get('material')->model('sales_material_ext');
        $salesMaterialShopFreezeObj = app::get('material')->model('sales_material_shop_freeze');
        $basicMaterialObj = app::get('material')->model('basic_material');

        //保存物料主表信息
        $addData = array(
            'sales_material_name'     => $_POST['sales_material_name'],
            'sales_material_bn'       => $_POST['sales_material_bn'],
            'sales_material_bn_crc32' => $_POST['sales_material_bn_crc32'],
            'sales_material_type'     => $_POST['sales_material_type'],
            'shop_id'                 => $_POST['shop_id'],
            'create_time'             => time(),
            "visibled"                => $_POST['visibled'],
            'class_id'                => intval($_POST['class_id']),
        );
        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$addData['shop_id']], 'org_id');
        $addData['org_id'] = $shop['org_id'];

        if ($_POST['tax_rate']) {
            $addData['tax_rate'] = $_POST['tax_rate'];
        }
        if ($_POST['tax_name']) {
            $addData['tax_name'] = $_POST['tax_name'];
        }
        if ($_POST['tax_code']) {
            $addData['tax_code'] = $_POST['tax_code'];
        }
        $is_save = $salesMaterialObj->save($addData);
        if ($_POST['sales_material_type'] == 6){
            $_POST['bm_id'] = $_POST['box_id'];
        }
        if ($is_save) {
            $is_bind = false;
            $cat_id = 0;
            
            //如果有关联物料就做绑定操作
            $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
            //普通销售物料关联
            if (($_POST['sales_material_type'] == 1 || $_POST['sales_material_type'] == 6) && !empty($_POST['bm_id'])) {
                $addBindData = array(
                    'sm_id'  => $addData['sm_id'],
                    'bm_id'  => $_POST['bm_id'],
                    'number' => 1,
                );
                
                $brandInfo = app::get('material')->model('basic_material_ext')->db_dump(['bm_id'=>$_POST['bm_id']], 'brand_id');
                $brand_id = $brandInfo['brand_id'];
                $salesBasicMaterialObj->insert($addBindData);
                
                //基础物料信息
                $baseMaterialInfo = $basicMaterialObj->dump(array('bm_id'=>$_POST['bm_id']), '*');
                // 同步更新税收分类编码
                if ($baseMaterialInfo && $baseMaterialInfo['material_bn'] == $addData['sales_material_bn']){
                    $basicUpdateData = [];
                    if ($addData['tax_rate']) {
                        $basicUpdateData['tax_rate'] = $addData['tax_rate'];
                    }
                    if ($addData['tax_name']) {
                        $basicUpdateData['tax_name'] = $addData['tax_name'];
                    }
                    if ($addData['tax_code']) {
                        $basicUpdateData['tax_code'] = $addData['tax_code'];
                    }
                    if ($basicUpdateData) {
                        $basicMaterialObj->update($basicUpdateData,['bm_id' => $baseMaterialInfo['bm_id']]);
                    }
                }
                $cat_id = $baseMaterialInfo['cat_id'];
                
                $is_bind = true;
            } elseif ($_POST['sales_material_type'] == 2 && !empty($_POST['at'])) {
                //促销销售物料关联
                foreach ($_POST['at'] as $k => $v) {
                    $addBindData = array(
                        'sm_id'  => $addData['sm_id'],
                        'bm_id'  => $k,
                        'number' => $v,
                        'rate'   => $_POST['pr'][$k],
                    );
                    $salesBasicMaterialObj->insert($addBindData);
                    $addBindData = null;
                }
                $brand_id = $_POST['brand_id'];
                $cat_id = intval($_POST['cat_id']);
                $is_bind = true;
                
            } elseif ($_POST['sales_material_type'] == 3 && !empty($_POST['at'])) { // 赠品关联多基础物料处理
                // at=> [bm_id=>number]
                foreach ($_POST['at'] as $k => $v) {
                    $addBindData = array(
                        'sm_id' => $addData['sm_id'],
                        'bm_id' => $k,
                        'number' => $v,
                    );
                    $salesBasicMaterialObj->insert($addBindData);
                    $addBindData = null;
                }

                $brand_id = $_POST['brand_id'];
                $cat_id = intval($_POST['cat_id']);
                $is_bind = true;
            } elseif ($_POST['sales_material_type'] == 5 && !empty($_POST['sort'])) {
                //多选一
                $mdl_ma_pickone_ru = app::get('material')->model('pickone_rules');
                $select_type       = $_POST["pickone_select_type"] ? $_POST["pickone_select_type"] : 1; //默认"随机"
                foreach ($_POST['sort'] as $key_bm_id => $val_sort) {
                    $current_insert_arr = array(
                        "sm_id"       => $addData['sm_id'],
                        "bm_id"       => $key_bm_id,
                        "sort"        => $val_sort ? $val_sort : 0,
                        "select_type" => $select_type,
                    );
                    $mdl_ma_pickone_ru->insert($current_insert_arr);
                }
                $brandInfo = app::get('material')->model('basic_material_ext')->db_dump(['bm_id'=>$key_bm_id], 'brand_id');
                $brand_id = $brandInfo['brand_id'];
                $is_bind = true;
            }elseif ($_POST['sales_material_type'] == 7) {
                $saleFukuMdl = app::get('material')->model('sales_material_fukubukuro');
                $luckybagLib = kernel::single('material_luckybag');
                
                //check
                if(empty($_POST['fudai_rate'])){
                    $this->end(false, '组合价格贡献占比不能为空');
                }
                
                if(empty($_POST['rate_price'])){
                    $this->end(false, '组合贡献价不能为空');
                }
                
                //福袋组合规则
                foreach ($_POST['fudai_rate'] as $rateKey => $rateVal)
                {
                    $addBindData = array(
                        'sm_id' => $addData['sm_id'],
                        'combine_id' => $rateKey,
                        'rate_price' => $_POST['rate_price'][$rateKey], //组合贡献价
                        'rate' => $rateVal,
                    );
                    $saleFukuMdl->insert($addBindData);
                }
                
                //重新保存销售物料关联的福袋组合规中的基础物料
                $cursor_id = 1;
                $error_msg = '';
                $params = array('app'=>'material', 'mdl'=>'sales_basic_material');
                $params['sdfdata'] = array('sm_id'=>$addData['sm_id']);
                $isReSave = $luckybagLib->resaveLuckySalesBmids($cursor_id, $params, $error_msg);
                if(!$isReSave && $error_msg){
                    $this->end(false, '创建基础物料关联关系失败：'. $error_msg);
                }
                
                $brand_id = intval($_POST['brand_id']);
                $cat_id = intval($_POST['cat_id']);
                $is_bind = true;
            }
            
            //如果有绑定物料数据，设定销售物料为绑定状态
            if ($is_bind) {
                $salesMaterialObj->update(array('is_bind' => 1), array('sm_id' => $addData['sm_id']));
            }

            //保存销售物料扩展信息
            $addExtData = array(
                'sm_id'        => $addData['sm_id'],
                'cost'         => $_POST['cost'] ? $_POST['cost'] : 0.00,
                'lowest_price' => $_POST['lowest_price'] ? $_POST['lowest_price'] : 0.00,
                'retail_price' => $_POST['retail_price'] ? $_POST['retail_price'] : 0.00,
                'weight'       => $_POST['weight'] ? $_POST['weight'] : 0.00,
                'unit'         => $_POST['unit'],
                'brand_id'     => (int)$brand_id,
                'cat_id' => $cat_id, //分类
            );
            $salesMaterialExtObj->insert($addExtData);

            //保存销售物料店铺级冻结
            if ($_POST['shop_id'] != '_ALL_') {
                $addStockData = array(
                    'sm_id'       => $addData['sm_id'],
                    'shop_id'     => $_POST['shop_id'],
                    'shop_freeze' => 0,
                );
                $salesMaterialShopFreezeObj->insert($addStockData);
            }

            //logs
            //记录快照
            kernel::single('material_sales_material')->logSalesMaterialSnapshot($addData['sm_id'], '销售物料添加');

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
        $salesMaterialObj = app::get('material')->model('sales_material');
        $logObj = app::get('ome')->model('operation_log');
        
        $lib_ma_sa_ma = kernel::single('material_sales_material');
        $classifyMdl = app::get('material')->model('customer_classify');
        
        //check
        if (empty($sm_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        
        $tmp_sm_id         = intval($sm_id);
        $salesMaterialInfo = $salesMaterialObj->dump($tmp_sm_id);
        if (!$salesMaterialInfo) {
            $this->end(false, '操作出错，请重新操作');
        }
    
        //读取合并销售物料的扩展信息
        $salesMaterialExtObj  = app::get('material')->model('sales_material_ext');
        $salesMaterialExtInfo = $salesMaterialExtObj->dump($tmp_sm_id);
        if (is_array($salesMaterialExtInfo)) {
            $salesMaterialInfo = array_merge($salesMaterialInfo, $salesMaterialExtInfo);
        }
    
        if (($salesMaterialInfo['sales_material_type'] == 1 || $salesMaterialInfo['sales_material_type'] == 3 || $salesMaterialInfo['sales_material_type'] == 6) && $salesMaterialInfo['is_bind'] == 1) {
            $salesBasicMaterialObj         = app::get('material')->model('sales_basic_material');
            $bmList = $salesBasicMaterialObj->getList('sm_id,bm_id', array('sm_id'=>$tmp_sm_id));
            
            $count = is_array($bmList) ? count($bmList) : 0;
            $this->pagedata['bind_bm_id']  = ($bmList[0] ? $bmList[0]['bm_id'] : 0);
            
            $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product'>已选择了{$count}个物料,<a href='javascript:void(0);' onclick='material_selected_show();'>查看关联的物料.</a></div>
EOF;
        }
        
        //多选一类型获取数据
        $luckyBagList = array();
        if ($salesMaterialInfo['sales_material_type'] == 5 && $salesMaterialInfo['is_bind'] == 1) {
            $rs_pickone                            = $lib_ma_sa_ma->get_pickone_by_sm_id($tmp_sm_id);
            $this->pagedata["pickone_select_type"] = $rs_pickone[0]["select_type"];
        }elseif($salesMaterialInfo['sales_material_type'] == 7){
            $saleFukuMdl = app::get('material')->model('sales_material_fukubukuro');
            $combineLib = kernel::single('material_fukubukuro_combine');
            
            //福袋组合包含的基础物料列表
            //@todo：最终返回的是三层结构(销售物料-->福袋组合-->基础物料)
            $error_msg = '';
            $luckyBagList = $combineLib->getLuckyMaterialBySmid(array($sm_id), $error_msg);
            
            //指定获取单个销售物料ID的福袋信息
            $luckyBagList = $luckyBagList[$sm_id];
            
            //根据销售物料ID获取对应基础物料列表
            $bmMaterialList = $lib_ma_sa_ma->getBasicMBySalesMId($sm_id);
            $bmMaterialList = array_column($bmMaterialList, null, 'bm_id');
            
            //销售物料关联福袋信息
            $luckyItems = $saleFukuMdl->getList('*', array('sm_id'=>$sm_id), 0, -1, 'rate DESC');
            $luckyItems = array_column($luckyItems, null, 'combine_id');
            
            //format
            foreach ($luckyBagList as $combineKey => $combineInfo)
            {
                $combine_id = $combineInfo['combine_id'];
                
                //items
                foreach ($combineInfo['items'] as $bmKey => $bmInfo)
                {
                    $bm_id = $bmInfo['bm_id'];
                    
                    //merge
                    if(isset($bmMaterialList[$bm_id])){
                        $bmMaterialInfo = $bmMaterialList[$bm_id];
                        
                        $combineInfo['items'][$bmKey] = array_merge($bmInfo, $bmMaterialInfo);
                    }
                }
                
                //fukuInfo
                $fukuInfo = $luckyItems[$combine_id];
                
                //merge
                $combineInfo['rate_price'] = $fukuInfo['rate_price'];
                $combineInfo['rate'] = $fukuInfo['rate'];
                
                $luckyBagList[$combineKey] = $combineInfo;
            }
            
            //排序
            rsort($luckyBagList);
        }
        
        //店铺信息(过滤o2o门店店铺)
        $shopObj  = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', array('s_type' => 1), 0, -1);
        array_unshift($shopList, array('shop_id' => '_ALL_', 'name' => '全部店铺'));
        
        //客户分类
        $classList = $classifyMdl->getList('class_id,class_name', array('disabled'=>'false'), 0, -1);
        if($classList){
            $classList = array_column($classList, null, 'class_id');
        }
        
        $classRow = [];
        if($salesMaterialInfo['class_id'] && $classList){
            $classRow = $classList[$salesMaterialInfo['class_id']];
        }
        
        $this->pagedata['classRow'] = $classRow;
        $this->pagedata['classList'] = $classList;
        $this->pagedata['shops'] = $shopList;
        $this->pagedata['material_info'] = $salesMaterialInfo;
        $readonly = array('type' => true,'shop'=>true,'bind_item'=>true);
        $this->pagedata['readonly']      = $readonly;
        
        /* 本销售物料日志 */
        $logList    = $logObj->read_log(array('obj_id'=>$sm_id, 'obj_type'=>'sales_material@material'), 0, -1);
        foreach($logList as $k => $v)
        {
            $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
        }
        
        // 获取价格计算方式配置
        $priceConfig = kernel::single('material_sales_setting')->getPriceConfig();
        $this->pagedata['price_rate'] = $priceConfig['price_rate'];
        $this->pagedata['price_field'] = $priceConfig['price_field'];
        $this->pagedata['price_label'] = $priceConfig['price_label'];
        
        $this->pagedata['data'] = $logList;
        $this->pagedata['luckyBagList'] = $luckyBagList;
        
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
        $this->begin('index.php?app=material&ctl=admin_material_sales&act=index');
        if (empty($sm_id)) {
            $this->end(false, '操作出错，请重新操作');
        }
        
        $salesMaterialObj = app::get('material')->model('sales_material');
        $classifyMdl = app::get('material')->model('customer_classify');
        
        $tmp_sm_id         = intval($sm_id);
        $salesMaterialInfo = $salesMaterialObj->dump($tmp_sm_id);
        if (!$salesMaterialInfo) {
            $this->end(false, '操作出错，请重新操作');
        }

        //检查物料是否有关联的订单,关联的基础物料不能改变
        //todo 需实现 readonly
        $checkSalesLib = kernel::single('material_sales_check');
        $readonly      = $checkSalesLib->checkEditReadOnly($salesMaterialInfo);
        
        //读取合并销售物料的扩展信息
        $salesMaterialExtObj  = app::get('material')->model('sales_material_ext');
        $salesMaterialExtInfo = $salesMaterialExtObj->dump($tmp_sm_id);
        if ($salesMaterialExtInfo) {
            $salesMaterialInfo = array_merge($salesMaterialInfo, $salesMaterialExtInfo);
        }

        if (($salesMaterialInfo['sales_material_type'] == 1 || $salesMaterialInfo['sales_material_type'] == 3 || $salesMaterialInfo['sales_material_type'] == 6) && $salesMaterialInfo['is_bind'] == 1) {
            $salesBasicMaterialObj         = app::get('material')->model('sales_basic_material');
            $salesBasicMaterialInfo        = $salesBasicMaterialObj->dump(array('sm_id' => $tmp_sm_id));
            $count                         = count([$salesBasicMaterialInfo['bm_id']]);
            $this->pagedata['bind_bm_id']  = $salesBasicMaterialInfo['bm_id'];
            $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product'>已选择了{$count}个物料,<a href='javascript:void(0);' onclick='material_selected_show();'>查看关联的物料.</a></div>
EOF;
        }

        $lib_ma_sa_ma = kernel::single('material_sales_material');
        
        //多选一类型获取数据
        if ($salesMaterialInfo['sales_material_type'] == 5 && $salesMaterialInfo['is_bind'] == 1) {
            $rs_pickone                            = $lib_ma_sa_ma->get_pickone_by_sm_id($tmp_sm_id);
            $this->pagedata["pickone_select_type"] = $rs_pickone[0]["select_type"];
        }
        
        //福袋组合规则
        if ($salesMaterialInfo['sales_material_type'] == 7 && $salesMaterialInfo['is_bind'] == 1) {
            //==
        }
        
        //店铺信息(过滤o2o门店店铺)
        $shopObj  = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', array('s_type' => 1), 0, -1);
        array_unshift($shopList, array('shop_id' => '_ALL_', 'name' => '全部店铺'));
        
        //客户分类
        $classList = $classifyMdl->getList('class_id,class_name', array('disabled'=>'false'), 0, -1);
        if($classList){
            $classList = array_column($classList, null, 'class_id');
        }
        
        $this->pagedata['classList'] = $classList;
        $this->pagedata['shops'] = $shopList;

        // 获取价格计算方式配置
        $priceConfig = kernel::single('material_sales_setting')->getPriceConfig();
        $this->pagedata['price_rate'] = $priceConfig['price_rate'];
        $this->pagedata['price_field'] = $priceConfig['price_field'];
        $this->pagedata['price_label'] = $priceConfig['price_label'];

        $this->pagedata['material_info'] = $salesMaterialInfo;
        $this->pagedata['readonly']      = $readonly;
        $this->page('admin/material/sales/edit.html');
    }

    /**
     * 销售物料编辑提交方法
     * 
     * @param Int $sm_id
     * @return Boolean
     */
    public function toEdit()
    {
        $this->begin('index.php?app=material&ctl=admin_material_sales&act=index');
        #数据有效性检查
        $checkSalesLib = kernel::single('material_sales_check');
        $err_msg       = '';
        $_POST['edit'] = true; //编辑标识 checkParams用
        if (!$checkSalesLib->checkParams($_POST, $err_msg)) {
            $this->end(false, $err_msg);
        }
        $salesMaterialObj    = app::get('material')->model('sales_material');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        //更新基础物料基本信息
        $filter['sm_id'] = $_POST['sm_id'];
        //快照
        $salesMaterialInfo = $salesMaterialObj->dump(array('sm_id' => $filter['sm_id']), '*');
        $salesExtInfo      = $salesMaterialExtObj->dump(array('sm_id' => $filter['sm_id']), '*');
        if ($salesExtInfo) {
            $salesMaterialInfo = array_merge($salesMaterialInfo, $salesExtInfo); //原始销售物料基础和扩展数据
        }
        $lib_ma_sa_ma = kernel::single('material_sales_material');
        if ($salesMaterialInfo["sales_material_type"] == "5") {
            //原销售物料类型是多选一
            $salesMaterialInfo["pickone_rules_list"] = $lib_ma_sa_ma->get_pickone_by_sm_id($filter['sm_id']);
        } elseif ($salesMaterialInfo["sales_material_type"] == '7') {
            //福袋组合规则
            $salesMaterialInfo['fukubukuro_rules_list'] = $lib_ma_sa_ma->get_fukubukuro_by_sm_id($filter['sm_id']);
        } else {
            $bmInfoList = $lib_ma_sa_ma->getBasicMBySalesMId($filter['sm_id']);
            if ($bmInfoList) {
                $salesMaterialInfo['bm_list'] = $bmInfoList;
            }
        }
        
        //更新销售物料基本信息
        $updateData = array(
            "sales_material_name" => $_POST['sales_material_name'],
            "sales_material_type" => $_POST['sales_material_type'],
            "shop_id"             => $_POST['shop_id'],
            "last_modified"       => time(),
            "visibled"            => $_POST['visibled'],
            'class_id'            => intval($_POST['class_id']),
        );
        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$updateData['shop_id']], 'org_id');
        $updateData['org_id'] = $shop['org_id'];

        if ($_POST['tax_rate']) {
            $updateData['tax_rate'] = $_POST['tax_rate'];
        }
        if ($_POST['tax_name']) {
            $updateData['tax_name'] = $_POST['tax_name'];
        }
        if ($_POST['tax_code']) {
            $updateData['tax_code'] = $_POST['tax_code'];
        }
        //更新
        $is_update = $salesMaterialObj->update($updateData, $filter);
        if ($is_update) {
            $is_bind               = false; //如果有关联物料就做绑定操作
            $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
            $mdl_ma_lu_ru          = app::get('material')->model('luckybag_rules');
            $mdl_ma_pickone_ru     = app::get('material')->model('pickone_rules');
            
            $cat_id = 0;
            
            //删除原有关联基础物料信息  后续会新增的（重做关系）
            $salesBasicMaterialObj->delete(array('sm_id' => $filter['sm_id'])); //普通、赠品、促销、福袋组合
            $mdl_ma_pickone_ru->delete(array("sm_id" => $filter['sm_id'])); //多选一
            
            if ($_POST['sales_material_type'] != 4) {
                //目前如果编辑提交时sales_material_type不是4福袋 删除原有的福袋数据（后续也有update、insert、delete逻辑）
                $rs_old_luckybag = $mdl_ma_lu_ru->dump(array("sm_id" => $filter['sm_id']));
                if (!empty($rs_old_luckybag)) {
                    //有旧的福袋数据删除sm_id对应的所有lbr数据
                    $mdl_ma_lu_ru->delete(array("sm_id" => $filter['sm_id']));
                }
            }
            if ($_POST['sales_material_type'] == 6){
                $_POST['bm_id'] = $_POST['box_id'];
            }
            //普通销售物料关联
            if (($_POST['sales_material_type'] == 1 || $_POST['sales_material_type'] == 6) && !empty($_POST['bm_id'])) {
                //普通、赠品销售物料关联
                $addBindData = array(
                    'sm_id'  => $filter['sm_id'],
                    'bm_id'  => $_POST['bm_id'],
                    'number' => 1,
                );
                $salesBasicMaterialObj->insert($addBindData);
                $brandInfo = app::get('material')->model('basic_material_ext')->db_dump(['bm_id'=>$_POST['bm_id']], 'brand_id');
                $brand_id = $brandInfo['brand_id'];
                
                //基础物料信息
                $baseMaterialInfo = $basicMaterialObj->dump(array('bm_id'=>$_POST['bm_id']), '*');
                // 同步更新税收分类编码
                if ($baseMaterialInfo && $baseMaterialInfo['material_bn'] == $salesMaterialInfo['sales_material_bn']){
                    $basicUpdateData = [];
                    if ($updateData['tax_rate']) {
                        $basicUpdateData['tax_rate'] = $updateData['tax_rate'];
                    }
                    if ($updateData['tax_name']) {
                        $basicUpdateData['tax_name'] = $updateData['tax_name'];
                    }
                    if ($updateData['tax_code']) {
                        $basicUpdateData['tax_code'] = $updateData['tax_code'];
                    }
                    if ($basicUpdateData) {
                        $basicMaterialObj->update($basicUpdateData,['bm_id' => $baseMaterialInfo['bm_id']]);
                    }
                }
                $cat_id = $baseMaterialInfo['cat_id'];
                
                $is_bind = true;
            } elseif ($_POST['sales_material_type'] == 2 && !empty($_POST['at'])) {
                //促销销售物料关联
                //促销销售物料关联
                foreach ($_POST['at'] as $k => $v) {
                    $addBindData = array(
                        'sm_id'  => $filter['sm_id'],
                        'bm_id'  => $k,
                        'number' => $v,
                        'rate'   => $_POST['pr'][$k],
                    );
                    $salesBasicMaterialObj->insert($addBindData);
                    $addBindData = null;
                }
                $brand_id = $_POST['brand_id'];
                $cat_id = intval($_POST['cat_id']);

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
                    $salesBasicMaterialObj->insert($addBindData);
                    $addBindData = null;
                }
                $brand_id = $_POST['brand_id'];
                $cat_id = intval($_POST['cat_id']);
                $is_bind = true;
            }elseif ($_POST['sales_material_type'] == 5 && !empty($_POST['sort'])) {
                //多选一
                $select_type = $_POST["pickone_select_type"] ? $_POST["pickone_select_type"] : 1; //默认"随机"
                foreach ($_POST['sort'] as $key_bm_id => $val_sort) {
                    $current_insert_arr = array(
                        "sm_id"       => $filter['sm_id'],
                        "bm_id"       => $key_bm_id,
                        "sort"        => $val_sort ? $val_sort : 0,
                        "select_type" => $select_type,
                    );
                    $mdl_ma_pickone_ru->insert($current_insert_arr);
                }
                $brandInfo = app::get('material')->model('basic_material_ext')->db_dump(['bm_id'=>$key_bm_id], 'brand_id');
                $brand_id = $brandInfo['brand_id'];
                $is_bind = true;
            }elseif ($_POST['sales_material_type'] == 7) {
                $saleFukuMdl = app::get('material')->model('sales_material_fukubukuro');
                $luckybagLib = kernel::single('material_luckybag');
                
                //check
                if(empty($_POST['fudai_rate'])){
                    $this->end(false, '没有可保存的福袋组合规则');
                }
                
                //删除销售物料与福袋组合规则的关联关系
                $saleFukuMdl->delete(array('sm_id'=>$filter['sm_id']));
                
                //福袋组合规则
                foreach ($_POST['fudai_rate'] as $rateKey => $rateVal)
                {
                    $addBindData = array(
                        'sm_id' => $filter['sm_id'],
                        'combine_id' => $rateKey,
                        'rate_price' => $_POST['rate_price'][$rateKey], //组合贡献价
                        'rate' => $rateVal,
                    );
                    $saleFukuMdl->insert($addBindData);
                }
                
                //重新保存销售物料关联的福袋组合规中的基础物料
                $cursor_id = 1;
                $error_msg = '';
                $params = array('app'=>'material', 'mdl'=>'sales_basic_material');
                $params['sdfdata'] = array('sm_id'=>$filter['sm_id']);
                $isReSave = $luckybagLib->resaveLuckySalesBmids($cursor_id, $params, $error_msg);
                if(!$isReSave && $error_msg){
                    $this->end(false, '保存基础物料关联关系失败：'. $error_msg);
                }
                
                $brand_id = intval($_POST['brand_id']);
                $cat_id = intval($_POST['cat_id']);
                $is_bind = true;
            }
            
            //如果有绑定物料数据，设定销售物料为绑定状态
            if ($is_bind) {
                $salesMaterialObj->update(array('is_bind' => 1), array('sm_id' => $filter['sm_id']));
            } else {
                $salesMaterialObj->update(array('is_bind' => 2), array('sm_id' => $filter['sm_id']));
            }

            //更新销售物料扩展信息
            $updateExtData = array(
                'cost'         => $_POST['cost'] ? $_POST['cost'] : 0.00,
                'lowest_price' => $_POST['lowest_price'] ? $_POST['lowest_price'] : 0.00,
                'retail_price' => $_POST['retail_price'] ? $_POST['retail_price'] : 0.00,
                'weight'       => $_POST['weight'] ? $_POST['weight'] : 0.00,
                'unit'         => $_POST['unit'],
                'brand_id'     => $brand_id,
                'cat_id' => $cat_id, //分类
            );
            $salesextData = $salesMaterialExtObj->dump(array('sm_id' => $filter['sm_id']), 'sm_id');
            if ($salesextData) {
                $salesMaterialExtObj->update($updateExtData, $filter);
            } else {
                $updateExtData['sm_id'] = $filter['sm_id'];
                $salesMaterialExtObj->insert($updateExtData);
            }

            //记录快照
            kernel::single('material_sales_material')->logSalesMaterialSnapshot($filter['sm_id'], '销售物料编辑');

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
    public function getEditMaterial($sm_id)
    {
        if ($sm_id == '') {
            $sm_id = $_POST['p[0]'];
        }

        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj      = app::get('material')->model('basic_material');
        $basicMaterialExtObj   = app::get('material')->model('basic_material_ext');

        // 获取价格计算方式配置
        $priceConfig = kernel::single('material_sales_setting')->getPriceConfig();
        $priceField = $priceConfig['price_field'];

        $rows  = array();
        $items = $salesBasicMaterialObj->getList('bm_id,sm_id,number,rate,fixed_price', array('sm_id' => $sm_id), 0, -1);
        if (!empty($items)) {
            foreach ($items as $k => $item) {
                $basicMaterialInfo = $basicMaterialObj->dump(array('bm_id' => $item['bm_id']), 'material_bn,material_name,cat_id,cat_path');

                //成本价
                $extInfo      = $basicMaterialExtObj->dump(array('bm_id' => $item['bm_id']), '*');
                $item['cost'] = $extInfo['cost'];
                $item['brand_id'] = $extInfo['brand_id'];
                $brand = app::get('ome')->model('brand')->db_dump(['brand_id'=>$item['brand_id']], 'brand_name');
                $item['brand_name'] = $brand['brand_name'];
                $item['cost'] = $extInfo['cost'];
                $item['retail_price'] = $extInfo['retail_price'];
                
                // 根据配置设置显示的价格字段
                $item['display_price'] = $extInfo[$priceField];
                
                $items[$k] = array_merge($item, $basicMaterialInfo);
            }
            $rows["original"] = $items; //普通、赠品、促销
        }
        //多选一
        $mdl_ma_pickone_ru = app::get('material')->model('pickone_rules');
        $pickone_items     = $mdl_ma_pickone_ru->getList('bm_id,sm_id,sort,select_type', array('sm_id' => $sm_id), 0, -1);
        if (!empty($pickone_items)) {
            foreach ($pickone_items as $k => $pickone_item) {
                $basicMaterialInfo = $basicMaterialObj->dump(array('bm_id' => $pickone_item['bm_id']), 'material_bn,material_name');
                $pickone_items[$k] = array_merge($pickone_item, $basicMaterialInfo);
            }
            $rows["pickone"] = $pickone_items;
        }
        echo json_encode($rows);
    }

    /**
     * 销售物料列表弹窗数据获取方法
     * 
     * @param Void
     * @return String
     */
    public function findSalesMaterial()
    {
        //已绑定的销售物料才可选择
        $base_filter = array('is_bind' => 1);

        if ($_GET['shop_id']) {
            $shop                   = explode('*', $_GET['shop_id']);
            $base_filter['shop_id'] = array($shop[0], '_ALL_');
        }

        if ($_GET['type']) {
            $base_filter['sales_material_type'] = $_GET['type'];
        }elseif($_GET['forbid_type']){
            //指定禁止选择的销售物料类型
            $base_filter['sales_material_type|notin'] = intval($_GET['forbid_type']);
        }
        
        $params = array(
            'title'                  => '销售物料列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'base_filter'            => $base_filter,
        );
        $this->finder('material_mdl_sales_material', $params);
    }

    /**
     * 销售物料列表弹窗选中物料信息查询方法
     * 
     * @param Int $bm_id
     * @return String
     */
    public function getSalesMaterial()
    {
        $sm_id               = $_POST['sm_id'];
        $sales_material_bn   = $_GET['sales_material_bn'];
        $sales_material_name = $_GET['sales_material_name'];

        if (is_array($sm_id)) {
            if ($sm_id[0] == "_ALL_") {
                $filter = '';
            } else {
                $filter['sm_id'] = $sm_id;
            }
        }

        if ($sales_material_bn) {
            $filter = array(
                'sales_material_bn' => $sales_material_bn,
            );
        }

        if ($sales_material_name) {
            $filter = array(
                'sales_material_name' => $sales_material_name,
            );
        }

        $salesMaterialObj      = app::get('material')->model('sales_material');
        $salesMaterialExtObj   = app::get('material')->model('sales_material_ext');
        $salesMStockLib        = kernel::single('material_sales_material_stock');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMaterialObj      = app::get('material')->model('basic_material');

        $filter['use_like'] = 1;
        $data               = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn', $filter, 0, -1);

        if (!empty($data)) {
            foreach ($data as $k => $item) {
                $store      = $salesMStockLib->getSalesMStockById($item['sm_id']);
                $ExtInfo    = $salesMaterialExtObj->dump($item['sm_id'], 'retail_price');
                $promoItems = $salesBasicMaterialObj->getList('*', array('sm_id' => $item['sm_id']), 0, -1);
                if ($promoItems) {
                    foreach ($promoItems as $pk => &$promoItem) {
                        $tmp_item  = $basicMaterialObj->getList('material_name,material_bn', array('bm_id' => $promoItem['bm_id']), 0, 1);
                        $promoItem = array_merge($promoItem, $tmp_item[0]);
                    }
                    $item['items'] = $promoItems;
                }

                $item['store'] = $store;
                $item['num']   = 1;
                $item['price'] = $ExtInfo['retail_price'];
                $rows[]        = $item;
            }
        }
        echo json_encode($rows);
    }

    /**
     * 销售物料批量导入的模板
     * 
     * @param Null
     * @return String
     */
    public function exportTemplate()
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        $title            = $salesMaterialObj->exportTemplate('salesMaterial');
        $lib = kernel::single('omecsv_phpexcel');

        #模板案例
        $data[0] = array('导入普通销售物料一', 'material_001', '普通', '我的淘宝店铺','品牌', '100', '125', '200', '只', 'product_001');
        $data[1] = array('导入赠品销售物料二', 'material_002', '赠品', '全部店铺','品牌', '0', '0', '0', '个', 'product_002');
        $data[2] = array('导入组合销售物料三', 'material_003', '组合', '我的京东店铺','品牌', '150', '165', '500', '个', 'product_001:2:30|product_002:3:30|product_003:4:40');
        // $data[3] = array('导入福袋销售物料四', 'material_004', '福袋', '我的天猫店铺','品牌', '200', '280', '800', '包', '组合A:a1|a2|a3|a4-2|2|50.00#组合B:b1|b2-1|1|100.00');
        $data[4] = array('导入多选一销售物料五', 'material_005', '多选一', '全部店铺','品牌', '300', '380', '900', '件', '随机#sku01:0|sku02:0 或 排序#sku01:10|sku02:20');

        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, '销售物料导入模板-'.date('Ymd'), 'xls', $title);
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $logObj = app::get('ome')->model('operation_log');
        //日志
        $goodslog = $logObj->dump($log_id, 'memo');
        $memo     = unserialize($goodslog['memo']);
        //类型
        if ($memo['sales_material_type'] == 3) {
            $memo['type_name'] = '赠品';
        } elseif ($memo['sales_material_type'] == 2) {
            $memo['type_name'] = '组合';
        } elseif ($memo['sales_material_type'] == 4) {
            $memo['type_name'] = '福袋';
        } elseif ($memo['sales_material_type'] == 5) {
            $memo['type_name'] = '多选一';
        } elseif ($memo['sales_material_type'] == 6) {
            $memo['type_name'] = '礼盒';
        } elseif ($memo['sales_material_type'] == 7) {
            $memo['type_name'] = '福袋组合';
        } else {
            $memo['type_name'] = '普通';
        }
        
        //所属店铺
        if ($memo['shop_id'] && $memo['shop_id'] != '_ALL_') {
            $shopObj           = app::get('ome')->model('shop');
            $shopInfo          = $shopObj->dump(array('shop_id' => $memo['shop_id']), 'name');
            $memo['shop_name'] = $shopInfo['name'];
        } else {
            $memo['shop_name'] = '全部店铺';
        }

        //关联基础物料
        $memo['is_pkg']      = false;
        $memo['is_gift']      = false;
        $memo['is_luckybag'] = false;
        $memo['is_pickone']  = false;
        if ($memo['sales_material_type'] == 2 && $memo['bm_list']) {
            $memo['is_pkg'] = true;
        } elseif ($memo['sales_material_type'] == 5 && $memo['pickone_rules_list']) {
            $memo["pickone_select_type_text"] = ($memo['pickone_rules_list'][0]["sort"] == 2) ? "排序" : "随机";
            $memo['is_pickone'] = true;
        } else if ($memo['sales_material_type'] == 3 && $memo['bm_list']) {
            $memo['is_gift'] = true;
        } else if ($memo['sales_material_type'] == 7 && $memo['fukubukuro_rules_list']) {
            $memo['is_fukubukuro'] = true;
        } elseif ($memo['bm_list']) {
            foreach ($memo['bm_list'] as $key => $val) {
                $memo['basic_material_bn']   = $val['material_bn'];
                $memo['basic_material_name'] = $val['material_name'];
            }
        }
        
        $this->pagedata['data'] = $memo;
        $this->singlepage('admin/material/sales/history_log.html');
    }

    //删除销售物料
    /**
     * 删除_material
     * @return mixed 返回值
     */
    public function delete_material(){
        $this->begin('index.php?app=material&ctl=admin_material_sales&act=index');
        $mdl_ome_order_objects = app::get('ome')->model('order_objects');
        $mdl_ma_sa_ba_ma = app::get('material')->model('sales_basic_material');
        $mdl_ma_sa_ma = app::get('material')->model('sales_material');
        $mdl_ma_sa_ma_ext = app::get('material')->model('sales_material_ext');
        $mdl_ma_lu_ru = app::get('material')->model('luckybag_rules');
        $mdl_ma_pickone_ru = app::get('material')->model('pickone_rules');
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->end(false,'不支持全选');
        }else{
            if($_POST['sm_id']){
                foreach($_POST['sm_id'] as $sm_id){
                    if ($sm_id){
                        //判断是否已关联订单  
                        $order_object = $mdl_ome_order_objects->dump(array("goods_id" => $sm_id),"obj_id");
                        if (!empty($order_object)){
                            $this->end(false,'有订单记录不可以删除');
                        }
                        $rs_ma_sa_ma = $mdl_ma_sa_ma->dump(array("sm_id"=>$sm_id),"sales_material_type");
                        switch($rs_ma_sa_ma["sales_material_type"]){
                            //case "4": //福袋
                            //    $mdl_ma_lu_ru->delete(array("sm_id"=>$sm_id));
                            //    break;
                            case "5": //多选一
                                $mdl_ma_pickone_ru->delete(array("sm_id"=>$sm_id));
                                break;
                            default: //普通、促销、赠品
                                $mdl_ma_sa_ba_ma->delete(array("sm_id"=>$sm_id));
                        }
                        $mdl_ma_sa_ma->delete(array("sm_id"=>$sm_id));
                        $mdl_ma_sa_ma_ext->delete(array("sm_id"=>$sm_id));
                    }
                }
            }
        }
        $this->end(true, '命令已经被成功发送！！');
    }
    
    /**
     * 弹窗选择福袋后，返回格式化的数据
     * 
     * @return void
     */
    public function getEditFukubukuro($sm_id)
    {
        $saleFukuMdl = app::get('material')->model('sales_material_fukubukuro');
        $combineMdl = app::get('material')->model('fukubukuro_combine');
        
        //post
        if ($sm_id == '') {
            $sm_id = $_POST['p[0]'];
        }
        
        //items
        $itemList = $saleFukuMdl->getList('*', array('sm_id'=>$sm_id), 0, -1);
        if(empty($itemList)){
            return array();
        }
        
        $combineIds = array_column($itemList, 'combine_id');
        $combineList = $combineMdl->getList('*', array('combine_id'=>$combineIds), 0, -1);
        if(empty($combineList)){
            return array();
        }
        $combineList = array_column($combineList, null, 'combine_id');
        
        //format
        foreach ($itemList as $itemKey => $tempVal)
        {
            $combine_id = $tempVal['combine_id'];
            
            //check
            if(!isset($combineList[$combine_id])){
                continue;
            }
            
            $itemList[$itemKey]['combine_bn'] = $combineList[$combine_id]['combine_bn'];
            $itemList[$itemKey]['combine_name'] = $combineList[$combine_id]['combine_name'];
            
            //最低价~最高价
            if($combineList[$combine_id]['lowest_price'] == $combineList[$combine_id]['highest_price']){
                $itemList[$itemKey]['combine_price'] = $combineList[$combine_id]['lowest_price'];
            }else{
                $itemList[$itemKey]['combine_price'] = $combineList[$combine_id]['lowest_price'] .' ~ '. $combineList[$combine_id]['highest_price'];
            }
            
            //最低价
            $itemList[$itemKey]['lowest_price'] = $combineList[$combine_id]['lowest_price'];
        }
        
        echo json_encode($itemList);
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
        $this->view_source = 'batch_visibled';
        $sub_menu = $this->_views();
        $base_filter = [];
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }
        $filter_arr = array_merge(array("visibled" => "1"),$_POST);
        if($base_filter){
            $filter_arr = array_merge($filter_arr,$base_filter);
        }
    
        $mdl_ma_sa_ma = app::get('material')->model('sales_material');
        $offset       = 0;
        $limit        = 100;
        $sm_ids       = array();
        if (!$base_filter && isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //"全部"tab 全选的
            do {
                $rs = $mdl_ma_sa_ma->getList("sm_id", $filter_arr, $offset, $limit, "sm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_sm_ids = array();
                foreach ($rs as $var_r) {
                    $current_sm_ids[] = $var_r["sm_id"];
                }
                $sm_ids[] = $current_sm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } elseif (isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //其余的tab 全选的
            do {
                $rs = $mdl_ma_sa_ma->getList("sm_id", $filter_arr, $offset, $limit, "sm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_sm_ids = array();
                foreach ($rs as $var_r) {
                    $current_sm_ids[] = $var_r["sm_id"];
                }
                $sm_ids[] = $current_sm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } else {
            //勾选的
            $sm_ids = array_chunk($_POST["sm_id"], $limit);
        }
        if (empty($sm_ids)) {
            $this->end(false, '无有效数据');
        }
        $update_arr = array("visibled" => 0);
        foreach ($sm_ids as $var_sm_id) {
            $filter_arr = array("sm_id" => $var_sm_id);
            $mdl_ma_sa_ma->update($update_arr, $filter_arr);
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
        $this->view_source = 'batch_visibled';
        $view     = intval($view);
        $sub_menu = $this->_views();
        $base_filter = [];
        foreach ($sub_menu as $key => $value) {
            if ($key == $view) {
                $base_filter = $value['filter'];
            }
        }
    
        $filter_arr = array_merge(array("visibled" => "0"),$_POST);
        if($base_filter){
            $filter_arr = array_merge($filter_arr,$base_filter);
        }
    
        $mdl_ma_sa_ma = app::get('material')->model('sales_material');
        $offset       = 0;
        $limit        = 100;
        $sm_ids       = array();
        if (!$base_filter && isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //"全部"tab 全选的
            do {
                $rs = $mdl_ma_sa_ma->getList("sm_id", $filter_arr, $offset, $limit, "sm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_sm_ids = array();
                foreach ($rs as $var_r) {
                    $current_sm_ids[] = $var_r["sm_id"];
                }
                $sm_ids[] = $current_sm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } elseif (isset($_POST["isSelectedAll"]) && $_POST["isSelectedAll"] == "_ALL_") {
            //其余的tab 全选的
            do {
                $rs = $mdl_ma_sa_ma->getList("sm_id", $filter_arr, $offset, $limit, "sm_id asc");
                if (empty($rs)) {
                    break;
                }
                $current_sm_ids = array();
                foreach ($rs as $var_r) {
                    $current_sm_ids[] = $var_r["sm_id"];
                }
                $sm_ids[] = $current_sm_ids;
                $offset   = $offset + $limit;
            } while (!empty($rs));
        } else {
            //勾选的
            $sm_ids = array_chunk($_POST["sm_id"], $limit);
        }
        if (empty($sm_ids)) {
            $this->end(false, '无有效数据');
        }
        $update_arr = array("visibled" => 1);
        foreach ($sm_ids as $var_sm_id) {
            $filter_arr = array("sm_id" => $var_sm_id);
            $mdl_ma_sa_ma->update($update_arr, $filter_arr);
        }
        $this->end(true, '操作成功');
    }


}
