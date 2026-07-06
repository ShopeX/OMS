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

class ome_ctl_admin_branch extends desktop_controller
{
    public $name       = "仓库列表";
    public $workground = "goods_manager";

    public function index()
    {
        
        $actions = array(
            array('label' => '添加仓库', 'href' => 'index.php?app=ome&ctl=admin_branch&act=addbranch&singlepage=false&finder_id=' . $_GET['finder_id']),
            //array('label'=>'添加货位','href'=>'index.php?app=ome&ctl=admin_branch&act=addpos'),
        );
       
        //只显示原有仓库，不包含门店虚拟仓
        $filter['b_type'] = 1;

        $params = array(
            'title'                  => '仓库列表',
            'actions'                => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'base_filter'            => $filter,
            'use_buildin_customcols' => true,
            'orderBy'                => 'wms_id asc',
        );
        $this->finder('ome_mdl_branch', $params);
    }

    public function save(){

        $this->begin($this->url);
        $oBranch = $this->app->model("branch");
        $_POST['stock_threshold'] = $_POST['stock_threshold'] ? $_POST['stock_threshold'] : 0;
            $data                     = $_POST;
            unset($data['area_conf']);
            if (!$data['branch_id'] && $_POST['wms_id'] == '') {
                $this->end(false, '请选择仓库对应WMS!');
            }
            kernel::single("ome_stock_do")->save_stock_safe_info($data);
            $areaObj = $this->app->model('branch_area');

            if ($data['attr'] == 'false' && $data['shop_config']) {
                $this->end(false, '线下仓库不能设置对应店铺！');
            }

            //原仓库信息
            $oldBranchInfo = $oBranch->db_dump(array('branch_id'=>$data['branch_id']), '*');
            
            //一个主仓只有一个残仓
            if ($data['branch_type'] == 'damaged') {
                $main_branch = intval($data['main_branch']);
                $branch_id   = intval($data['branch_id']);
                $damaged_num = 0;

                if ($main_branch > 0) {
                    $filter = array(
                        'type'      => 'damaged',
                        'parent_id' => $main_branch,
                    );
                    if ($branch_id > 0) {
                        $filter['branch_id|noequal'] = $branch_id;
                    }
                    $damaged_num = $oBranch->count($filter);
                }

                if ($damaged_num >= 1) {
                    $this->end(false, app::get('base')->_('该主仓下已存在一个残仓，请重新选择主仓！'));
                }
                $data['attr'] = 'false';
            }

            if ($_POST['branch_type']) {
                if ($_POST['branch_type'] == 'main') {
                    $data['type']      = $_POST['branch_type'];
                    $data['parent_id'] = 0;
                } else {
                    $data['type']            = $_POST['branch_type'];
                    $data['attr']            = 'false';
                    $data['is_deliv_branch'] = 'false';
                    $data['parent_id']       = intval($_POST['main_branch']);
                    #残仓售后仓wms_id复用主仓
                }
            }
            //平台自发仓没选平台只能创建一个。
            if ($data['owner'] == '3' && empty($data['platform'])) {
                $ownerBrancn = $oBranch->dump(array('owner' => $data['owner'],'platform'=>'','type'=>'main'), 'branch_id');
                if ($ownerBrancn && $ownerBrancn['branch_id'] != $data['branch_id']) {
                    $this->end(false, app::get('base')->_('没有所属平台的平台自发仓数量达到上限'));
                }
            }
            //一个平台的平台自发仓只能创建一个
            if ($data['owner'] == '3' && !empty($data['platform'])) {
                $ownerBrancn = $oBranch->dump(array('owner' => $data['owner'],'platform'=>$data['platform'],'type'=>'main'), 'branch_id');
                if ($ownerBrancn && $ownerBrancn['branch_id'] != $data['branch_id']) {
                    $this->end(false, app::get('base')->_('所属平台已有平台自发仓'));
                }
            }
            if ($data['owner'] != 3) {
                $data['platform'] = '';
            }
            
            // 验证仓库编号格式
            if (!empty($data['branch_bn'])) {
                if (!preg_match('/^[a-zA-Z0-9_:-]+$/', $data['branch_bn'])) {
                    $this->end(false, app::get('base')->_('仓库编号只允许输入英文字母、数字、下划线、横线和冒号'));
                }
            }
            
            // 验证库内存放点编号格式
            if (!empty($data['storage_code'])) {
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['storage_code'])) {
                    $this->end(false, app::get('base')->_('库内存放点编号只允许输入英文字母、数字、下划线和横线'));
                }
            }
            
            $oldBrancn = $oBranch->dump(array('branch_bn' => $data['branch_bn']), 'branch_id,branch_bn');
            if (($oldBrancn['branch_bn'] && !$data['branch_id']) || ($oldBrancn['branch_id'] && $oldBrancn['branch_id'] != $data['branch_id'])) {
                $this->end(false, app::get('base')->_('仓库编号重复'));
            }

            $checkname_id = $oBranch->namecheck(trim($_POST['name']));
            if (!$data['branch_id'] && $checkname_id) {
                $this->end(false, app::get('base')->_('仓库名称已经存在'));
            }
            $data['storage_code'] = $_POST['storage_code']; //库内存放点编号
            $cutoff_time_hour = intval($data['cutoff_time_hour'])<10 ? '0'.intval($data['cutoff_time_hour']) : $data['cutoff_time_hour'];
            $cutoff_time_minute = intval($data['cutoff_time_minute'])<10 ? '0'.intval($data['cutoff_time_minute']) : $data['cutoff_time_minute'];
            $data['cutoff_time'] = $cutoff_time_hour.$cutoff_time_minute;
            $data['latest_delivery_time'] = str_pad($data['latest_delivery_time_hour'], 2, '0', STR_PAD_LEFT).str_pad($data['latest_delivery_time_minute'], 2, '0', STR_PAD_LEFT); 
            $oBranch->save($data);
            # 存储仓库与店铺的关联关系
            ome_shop_branch::update_relation($data['branch_bn'], $data['shop_config'], $data['branch_id']);

            //处理指定物流公司 corp_config
            $mdl_ome_branch_corp = $this->app->model("branch_corp");
            //获取原有的关系数据
            $rs_branch_corp = $mdl_ome_branch_corp->getList("*", array("branch_id" => $data['branch_id']));
            if (!empty($_POST["corp_config"])) {
                $arr_del_branch_corp    = array();
                $arr_insert_branch_corp = array();
                if (empty($rs_branch_corp)) {
                    $arr_insert_branch_corp = $_POST["corp_config"];
                } else {
                    $has_corp_ids = array();
                    foreach ($rs_branch_corp as $var_b_c) {
                        $has_corp_ids[] = $var_b_c["corp_id"];
                    }
                    $arr_del_branch_corp    = array_diff($has_corp_ids, $_POST["corp_config"]);
                    $arr_insert_branch_corp = array_diff($_POST["corp_config"], $has_corp_ids);
                }
                if (!empty($arr_insert_branch_corp)) {
                    //新增
                    foreach ($arr_insert_branch_corp as $var_i) {
                        $insert_branch_corp = array(
                            "branch_id" => $data['branch_id'],
                            "corp_id"   => $var_i,
                        );
                        $mdl_ome_branch_corp->insert($insert_branch_corp);
                    }
                }
                if (!empty($arr_del_branch_corp)) {
                    //删除
                    $delete_branch_corp = array(
                        "branch_id" => $data['branch_id'],
                        "corp_id"   => $arr_del_branch_corp,
                    );
                    $mdl_ome_branch_corp->delete($delete_branch_corp);
                }
            } else {
//没有选指定物流公司
                if (!empty($rs_branch_corp)) {
                    $mdl_ome_branch_corp->delete(array("branch_id" => $data['branch_id']));
                }
            }

            //增加仓库保存后的扩展
            foreach (kernel::servicelist('ome.branch') as $o) {
                if (method_exists($o, 'after_branch_save')) {
                    $o->after_branch_save($data);
                }
            }
            kernel::single('console_map_branch')->getLocation($data['branch_id']);
            kernel::single('desktop_roles')->syncPermissionQueue();
            
            //[翱象系统]同步仓库作业信息
            if($data['attr']=='true' && $data['is_deliv_branch']=='true'){
                //是否安装了dchain应用
                if(app::get('dchain')->is_installed()){
                    $aoxiangLib = kernel::single('dchain_aoxiang');
                    
                    //仓库编码被修改
                    if($oldBranchInfo['branch_bn'] != $data['branch_bn']){
                        $aoxiangLib->triggerDeleteBranch($data['branch_id'], $oldBranchInfo['branch_bn']);
                    }
                    
                    //sync
                    $aoxiangLib->triggerBranch($data['branch_id']);
                }
            }
            
            //保存扩展字段
            if($_POST['props']){
                $propsMdl = app::get('ome')->model('branch_props');
                $propsMdl->saveProps($data['branch_id'], $_POST['props']);
            }
        $this->end(true, app::get('base')->_('保存成功'));
    }
    /*
     * 仓库添加
     *
     * @param int $branch_id
     *
     */
    public function addbranch()
    {
        $oBranch = $this->app->model("branch");

       
        //添加仓库类型

        $type                   = kernel::single('ome_branch')->getBranchTypes();

        $this->pagedata['type'] = $type;

        #第三方仓储列表
        $wms_list = kernel::single('ome_branch')->getWmsChannelList();
      
        $this->pagedata['wms_list'] = $wms_list;
        //
        # 绑定店铺(过滤o2o门店店铺)
        $shopModel              = app::get('ome')->model('shop');
        $shop                   = $shopModel->getList('*', array('s_type' => 1, 'delivery_mode' => 'self'), 0, -1);
        $this->pagedata['shop'] = $shop;

        $this->pagedata['conf_hours'] = array_map(fn($v) => str_pad($v, 2, '0', STR_PAD_LEFT), range(0, 23));

        $this->pagedata['conf_minitue'] = array_map(fn($v) => str_pad($v, 2, '0', STR_PAD_LEFT), range(0, 59));
        $this->pagedata['branch'] = array(
            'cutoff_time_hour'=>0, 
            'cutoff_time_minute'=>0, 
            'latest_delivery_time_hour'=>0, 
            'latest_delivery_time_minute'=>0,
            'latitude' => '',
            'longitude' => ''
        );
       
        $this->pagedata['title']     = '添加仓库';

        $options['owner']          = array('1' => '自建仓库', '2' => '第三方仓库(自有仓导入方式进行发货)', '3' => '平台自发仓库');
        $this->pagedata['options'] = $options;

        //物料公司列表
        $mdl_ome_dly_corp        = app::get('ome')->model('dly_corp');
        $this->pagedata['corps'] = $mdl_ome_dly_corp->getList("*", array("d_type" => "1")); //排除线下业务的物流
        //所属平台
        $shoptype = ome_shop_type::get_shop_type();
        $this->pagedata['platform_list'] = $shoptype;
        
        //扩展字段配置
        $customcols = kernel::single('ome_branch')->getcols();
        $this->pagedata['customcols'] = $customcols;
    
        $this->page("admin/system/branch.html");
    }

    /*
     * 仓库编辑
     *
     * @param int $branch_id
     *
     */
    public function editbranch($branch_id = null, $singlepage = false)
    {
        $oBranch      = $this->app->model("branch");
        $oBranch_area = $this->app->model("branch_area");
        $oRegions     = app::get('eccommon')->model("regions");

        $branch     = $oBranch->dump(array('branch_id' => $branch_id), '*');
        $area_conf  = unserialize($branch['area_conf']);
        $areas      = $area_conf['areaGroupId'];
        $areas_name = $area_conf['areaGroupName'];

        //仓库设置
      
        $type                   = kernel::single('ome_branch')->getBranchTypes();
        $this->pagedata['type'] = $type;

        #第三方仓储列表
        $wms_list = kernel::single('ome_branch')->getWmsChannelList();
        foreach ($wms_list as $k => $val) {
            if (empty($val['node_id'])) {
                unset($wms_list[$k]);
            }
        }
        $this->pagedata['wms_list'] = $wms_list;
        $wms_disabled               = false;
        //主仓
        if (!empty($branch_id)) {
            $p_id       = $oBranch->dump(array('branch_id' => $branch_id), 'parent_id');
            $parentItem = $oBranch->dump(array('branch_id' => $p_id['parent_id']), 'branch_id,name');
            #判断是否仓库是否已有单据，如果有不可以切换wms
            #采购单发货单采购退货单调拔单出入库单
            $oPurchase        = app::get('purchase')->model('po');
            $oReturn_purchase = app::get('purchase')->model('returned_purchase');
            $oDelivery        = app::get('ome')->model('delivery');
            $oIso             = app::get('taoguaniostockorder')->model('iso');
            $oAppropriation   = app::get('taoguanallocate')->model('appropriation');
            $purchase         = $oPurchase->dump(array('branch_id' => $branch_id), 'branch_id');
            $return_purchase  = $oReturn_purchase->dump(array('branch_id' => $branch_id), 'branch_id');
            $delivery         = $oDelivery->dump(array('branch_id' => $branch_id), 'branch_id');
            $iso              = $oIso->dump(array('branch_id' => $branch_id), 'branch_id');
            $appropriation    = $oAppropriation->dump(array('to_branch_id' => $branch_id), 'to_branch_id');
            if ($purchase || $return_purchase || $delivery || $iso || $appropriation) {
                $wms_disabled = true;
            }
            #若是主仓，查看是否作为父仓，如果是父仓不允许切换为售后仓或残仓
            if ($branch['type'] == 'main') {
                $branch_main = $oBranch->getlist('branch_id', array('parent_id' => $branch_id), 0, -1);
                if (count($branch_main) > 0) {
                    $branch_main_disabled = true;
                }
            }
            //物料公司列表
            $mdl_ome_dly_corp    = app::get('ome')->model('dly_corp');
            $rs_corps            = $mdl_ome_dly_corp->getList("*", array("d_type" => "1"));
            $mdl_ome_branch_corp = app::get('ome')->model('branch_corp');
            $rs_branch_corps     = $mdl_ome_branch_corp->getList("*", array("branch_id" => $branch_id));
            if (!empty($rs_branch_corps)) {
                $select_corps = array();
                foreach ($rs_branch_corps as $var_b_c) {
                    $select_corps[] = $var_b_c["corp_id"];
                }
                foreach ($rs_corps as &$var_c) {
                    if (in_array($var_c["corp_id"], $select_corps)) {
                        $var_c["selected"] = true;
                    }
                }
                unset($var_c);
            }
            $this->pagedata['corps'] = $rs_corps;
        }
        $this->pagedata['branch_main_disabled'] = $branch_main_disabled;
        $this->pagedata['wms_disabled']         = $wms_disabled;
        $sql                                    = "SELECT * FROM `sdb_ome_branch` as s WHERE  type='main' and attr='true' and ( select count(*) from sdb_ome_branch where `type` = 'damaged' and parent_id=s.branch_id) = 0";
        $main_branchs                           = $oBranch->db->select($sql);
        if (!empty($parentItem)) {
            array_push($main_branchs, $parentItem);
        }
        if (!empty($main_branchs)) {
            foreach ($main_branchs as $v) {
                $main_branch[$v['branch_id']] = $v['name'];
            }
        }
        $this->pagedata['main_branch'] = $main_branch;

        # 绑定的店铺
        $shopModel              = app::get('ome')->model('shop');
        $shop                   = $shopModel->getList('*', array('s_type' => '1', 'delivery_mode' => 'self'));
        $this->pagedata['shop'] = $shop;

        # 仓库关联的店铺
        $shop_branchs = app::get('ome')->getConf('shop.branch.relationship');
        $shop_bns     = array();
        if ($shop_branchs) {
            foreach ($shop_branchs as $shop => $branchs) {
                if (in_array($branch['branch_bn'], $branchs)) {
                    $shop_bns[] = strval($shop);
                }
            }
        }
        $this->pagedata['shop_bns'] = $shop_bns;

        $safe_time = array(array(0, '0:00'), array(1, '1:00'), array(2, '2:00'), array(3, '3:00'), array(4, '4:00'),
            array(5, '5:00'), array(6, '6:00'), array(7, '7:00'), array(8, '8:00'), array(9, '9:00'),
            array(10, '10:00'), array(11, '11:00'), array(12, '12:00'), array(13, '13:00'), array(14, '14:00'),
            array(15, '15:00'), array(16, '16:00'), array(17, '17:00'), array(18, '18:00'), array(19, '19:00'),
            array(20, '20:00'), array(21, '21:00'), array(22, '22:00'), array(23, '23:00'));
        $this->pagedata['conf_hours'] = array_map(fn($v) => str_pad($v, 2, '0', STR_PAD_LEFT), range(0, 23));

        $this->pagedata['conf_minitue'] = array_map(fn($v) => str_pad($v, 2, '0', STR_PAD_LEFT), range(0, 59));
        $branch['cutoff_time_hour'] = $branch['cutoff_time'] ? intval(substr($branch['cutoff_time'], 0, 2)) : 0;
        $branch['cutoff_time_minute'] = $branch['cutoff_time'] ? intval(substr($branch['cutoff_time'], 2, 2)) : 0;
        $branch['latest_delivery_time_hour'] = $branch['latest_delivery_time'] ? intval(substr($branch['latest_delivery_time'], 0, 2)) : 0;
        $branch['latest_delivery_time_minute'] = $branch['latest_delivery_time'] ? intval(substr($branch['latest_delivery_time'], 2, 2)) : 0;


        $this->pagedata['branch']    = $branch;
        $this->pagedata['title']     = '编辑仓库';

        $options['owner']          = array('1' => '自建仓库', '2' => '第三方仓库', '3' => '平台自发仓库');
        $this->pagedata['options'] = $options;
        //所属平台
        $shoptype = ome_shop_type::get_shop_type();
        $this->pagedata['platform_list'] = $shoptype;
        
        //扩展字段配置和值
        $customcols = kernel::single('ome_branch')->getcols();
        $propsMdl = app::get('ome')->model('branch_props');
        $arr_props = $propsMdl->getPropsByBranchId($branch_id);
        
        foreach($customcols as $k=>$v){
            if(isset($arr_props[$v['col_key']])){
                $customcols[$k]['col_value'] = $arr_props[$v['col_key']];
            } else {
                $customcols[$k]['col_value'] = '';
            }
        }
        
        $this->pagedata['customcols'] = $customcols;
    
        $this->page("admin/system/branch.html");

    }

    public function addpos($branch_id = 0)
    {
        $oBranch_pos = $this->app->model("branch_pos");
        $oBranch     = $this->app->model("branch");
        $branch_list = $oBranch->Get_branchlist();
        if ($_POST) {
            $this->begin('index.php?app=ome&ctl=admin_branch&act=addpos&p[0]=' . $_POST['branch_id']);
            $_POST['store_position'] = strtoupper($_POST['store_position']);
            $branch_pos              = $oBranch_pos->dump(array('store_position' => $_POST['store_position'], 'branch_id' => $_POST['branch_id']), 'pos_id');
            if ($branch_pos['pos_id']) {
                $this->end(false, app::get('base')->_('货位已存在'));
            }
            $_POST['stock_threshold'] = !$_POST['stock_threshold'] ? 0 : intval($_POST['stock_threshold']);

            $oBranch_pos->save($_POST);

            $this->end(true, app::get('base')->_('保存成功'), 'index.php?app=ome&ctl=admin_branch_pos&act=index');
        }
        $this->pagedata['branch_id']   = $branch_id;
        $this->pagedata['branch_list'] = $branch_list;

        //获取仓库模式
        //$branch_mode = app::get('ome')->getConf('ome.branch.mode');
        //$this->pagedata['branch_mode'] = $branch_mode;

        /*
         * 获取操作员管辖仓库
         */
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_list_byuser = $oBranch->getBranchByUser();
        }
        $this->pagedata['branch_list_byuser'] = $branch_list_byuser;
        $this->pagedata['is_super']           = $is_super;

        $this->pagedata['title'] = '添加货位';
        $this->singlepage("admin/system/branch_pos.html");
    }

    /*
     * 没有残仓的主仓
     *
     */
    public function get_branch_type($branch_type, $branch_bn, $wms_id)
    {
        $oBranch = $this->app->model("branch");
        $wms_id  = intval($wms_id);
        if ($wms_id <= 0) {
            echo 'false';
            return;
        }

        $p_id = array();
        if (!empty($branch_bn)) {
            $p_id = $oBranch->dump(array('branch_bn' => $branch_bn), 'branch_id,parent_id');
        }
        
        if ($branch_type == 'damaged') {
            //残次仓
            $main_branchs = $oBranch->getList('*', array('type' => 'main', 'wms_id' => $wms_id));
            
            $html = "<select id='main_branch' name='main_branch' vtype='required'>";
            foreach ($main_branchs as $v)
            {
                $branch_id = $v['branch_id'];
                $branch_name = $v['name'];
                
                $html .= '<option value="' . $branch_id . '"';
                if ($p_id['parent_id'] == $branch_id) {
                    $html .= ' selected="selected" ';
                }
                
                $html .= '>' . $branch_name . '</option>';
            }
            
            $html .= "</select>";
        } else {
            //主仓
            $filter = array('type' => 'main', 'wms_id' => $wms_id);
            if (!empty($p_id['branch_id'])) {
                $filter['branch_id|noequal'] = intval($p_id['branch_id']);
            }
            $main_branchs = $oBranch->getList('branch_id,name', $filter);
            if (!empty($main_branchs)) {
                $html = "<select id='main_branch' name='main_branch' vtype='required'>";
                
                foreach ($main_branchs as $v) {
                    $branch_id   = $v['branch_id'];
                    $branch_name = $v['name'];
    
                    $html .= '<option value="' . $branch_id . '"';
                    if ($p_id['parent_id'] == $branch_id) {
                        $html .= ' selected="selected" ';
                    }
                    
                    $html .= '>' . $branch_name . '</option>';
                }
                
                $html .= "</select>";
            }
        }
        if (empty($main_branchs)) {
            echo 'false';
        } else {
            echo $html;
        }
    }

    /**
     * 选择前端物流公司
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function ajax_select_branch($wms_id)
    {

        $this->page("admin/system/wms_branch.html");

    }

    /**
     * 获取WMS仓库
     * @param
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function ajax_wms_branch($wms_id)
    {

        // $result = kernel::single('middleware_wms_request', $wms_id)->get_warehouse_list($sdf,true);
        $result = kernel::single('erpapi_router_request')->set('wms', $wms_id)->branch_getlist(null);

        $wms_list = $result['data'];
        echo json_encode($wms_list);
    }

    /**
     * Short description.
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function ajax_wms_corp()
    {
        $wms_id = 16;
        //$result = kernel::single('middleware_wms_request', $wms_id)->get_logistics_list($sdf,true);
        //$result = kernel::single('erpapi_router_request')->set('wms', $wms_id)->logistics_getlist($sdf);

    }

    public function showBranchs()
    {
        $branch_id = kernel::single('base_component_request')->get_post('o2o_branch');

        if ($branch_id) {
            $this->pagedata['_input'] = array(
                'name'     => 'name',
                'idcol'    => 'branch_id',
                '_textcol' => 'name',
            );

            $basicMaterialObj = app::get('ome')->model('branch');
            $list             = $basicMaterialObj->getList('branch_id,name', array('branch_id' => $branch_id), 0, -1, 'branch_id asc');

            $this->pagedata['_input']['items'] = $list;
        }

        $this->display('admin/branch/show_branch.html');
    }

    public function finder_common()
    {
        $params = array(
            'title'                  => app::get('desktop')->_('门店列表'),
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

}
