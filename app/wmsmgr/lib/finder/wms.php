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

class wmsmgr_finder_wms{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "channel_id,channel_name,node_id,channel_type,node_type,config";
    var $column_edit = "操作";
    var $column_edit_width = "260";
    var $column_edit_order = "1";
    function column_edit($row){
        // Vue Finder 契约：结构化行操作（不再下发 legacy <a> HTML 片段，SPA 不解析 legacy 链接）
        if ($this->vue_mode) {
            $channel_id = $row['_0_channel_id'] ?? $row['channel_id'] ?? 0;
            $node_id    = $row['_2_node_id'] ?? $row['node_id'] ?? '';
            $node_type  = $row['_4_node_type'] ?? $row['node_type'] ?? '';
            $config_raw = $row['_5_config'] ?? $row['config'] ?? '';
            $config     = is_array($config_raw) ? $config_raw : @unserialize((string) $config_raw);
            if (!is_array($config)) {
                $config = array();
            }

            $adapter = kernel::single('wmsmgr_func')->getAdapterByChannelId($channel_id);

            $actions = array(
                array(
                    'key'    => 'edit',
                    'label'  => '编辑',
                    'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=edit&p[0]=' . $channel_id,
                    'target' => array('type' => 'dialog', 'width' => 670, 'height' => 380, 'title' => '第三方仓储'),
                ),
            );

            $logi_added = false;
            if (in_array($adapter, array('matrixwms'))) {
                $config_node_type = isset($config['node_type']) ? $config['node_type'] : '';
                switch ($config_node_type) {
                    case 'bim':
                    case 'bms':
                    case 'yph':
                        $actions[] = $node_id
                            ? array('key' => 'unbind', 'label' => '取消绑定', 'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=unbind_shop&p[0]=' . $channel_id)
                            : array(
                                'key'    => 'bind',
                                'label'  => '申请绑定',
                                'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=bind_page&p[0]=' . $channel_id,
                                'target' => array('type' => 'dialog', 'width' => 650, 'height' => 300, 'title' => '绑定店铺'),
                            );
                        break;
                    case 'yjdf':
                        if ($node_id) {
                            $actions[] = array(
                                'key'    => 'unbind',
                                'label'  => '取消绑定',
                                'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=unbind_verify&p[0]=' . $channel_id,
                                'target' => array('type' => 'dialog', 'width' => 500, 'height' => 300, 'title' => '取消绑定'),
                            );

                            $relationObj   = app::get('wmsmgr')->model('express_relation');
                            $relationCount = $relationObj->count(array('wms_id' => $channel_id));
                            if ($relationCount < 20) {
                                $actions[]  = array(
                                    'key'    => 'init_logi',
                                    'label'  => '初始化物流公司',
                                    'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=initializeLogi&p[0]=' . $channel_id,
                                    'target' => array('type' => 'dialog', 'width' => 500, 'height' => 300, 'title' => '初始化京东物流公司映射关系'),
                                );
                                $logi_added = true;
                            }
                        } else {
                            $actions[] = array(
                                'key'    => 'bind',
                                'label'  => '申请绑定',
                                'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=bindNodeId&p[0]=' . $channel_id,
                                'target' => array('type' => 'dialog', 'width' => 300, 'height' => 200, 'title' => '输入node_id'),
                            );
                        }
                        break;
                    case 'other':
                    default:
                        $actions[] = empty($node_id)
                            ? array(
                                'key'    => 'bind',
                                'label'  => '申请绑定',
                                'url'    => 'index.php?app=wmsmgr&ctl=api_wms&act=relationUrl&mode=apply&channel_id=' . $channel_id,
                                'target' => '_blank',
                            )
                            : array('key' => 'bound', 'label' => '已绑定', 'disabled' => true);
                        break;
                }

                // 京东一件代发：已绑定且物流映射少于 20 条时给「初始化物流公司」
                if ($node_id && $node_type == 'yjdf' && !$logi_added) {
                    $relationObj   = app::get('wmsmgr')->model('express_relation');
                    $relationCount = $relationObj->count(array('wms_id' => $channel_id));
                    if ($relationCount < 20) {
                        $actions[] = array(
                            'key'    => 'init_logi',
                            'label'  => '初始化物流公司',
                            'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=initializeLogi&p[0]=' . $channel_id,
                            'target' => array('type' => 'dialog', 'width' => 500, 'height' => 300, 'title' => '初始化京东物流公司映射关系'),
                        );
                    }
                }
            }

            if ($node_id && $node_type == 'jd_wms_cloud') {
                $actions[] = array(
                    'key'    => 'syncBase',
                    'label'  => '初始化',
                    'action' => 'index.php?app=wmsmgr&ctl=admin_wms&act=syncBase&p[0]=' . $channel_id,
                    'target' => array('type' => 'dialog', 'width' => 650, 'height' => 300, 'title' => '同步基础数据'),
                );
            }

            return $actions;
        }

        $finder_id = $_GET['_finder']['finder_id'];
        $node_type = $row[$this->col_prefix.'node_type'];
        $channel_type  = $row[$this->col_prefix.'channel_type'];
        $node_id = $row[$this->col_prefix.'node_id'];
        $channel_id = $row[$this->col_prefix.'channel_id'];
        $channel_name = $row[$this->col_prefix.'channel_name'];
        $config     = unserialize($row[$this->col_prefix . 'config']);

        $adapter = kernel::single('wmsmgr_func')->getAdapterByChannelId($channel_id);

        $edit_btn = '<a href="index.php?app=wmsmgr&ctl=admin_wms&act=edit&p[0]='.$row[$this->col_prefix.'channel_id'].'&finder_id='.$finder_id.'" target="dialog::{width:670,height:380,title:\'第三方仓储\'}">编辑</a>';

        //初始化物流公司
        $logi_btn = '';
        
        $certi_id = base_certificate::get('certificate_id');
        $api_url = kernel::base_url(true).kernel::url_prefix().'/api';
        $sess_id = kernel::single('base_session')->sess_id();
        $callback_url = urlencode(kernel::openapi_url('openapi.wmsmgr','bindCallback',array('channel_id'=>$channel_id, 'sess_id'=>$sess_id)));
        $app_id = "ome";
        $api_url = urlencode($api_url);
        if (in_array($adapter,array('matrixwms'))) {

            switch ($config['node_type']) {
                case 'bim':
                case 'bms':
                case 'yph':
                    $bind_btn .= $node_id ? sprintf(' | <a href="index.php?app=wmsmgr&ctl=admin_wms&act=unbind_shop&p[0]=%s" style="color:#990000;">取消绑定</a>',$channel_id) : sprintf(' | <a href="index.php?app=wmsmgr&ctl=admin_wms&act=bind_page&p[0]=%s&finder_id=%s" target="dialog::{width:650,height:300,title:\'绑定店铺\'}">申请绑定</a>',$channel_id,$finder_id);
                    break;
                case 'yjdf':
                    if($node_id){
                        //$bind_btn .= sprintf(' | <a href="index.php?app=wmsmgr&ctl=admin_wms&act=unbind_shop&p[0]=%s" style="color:#990000;">取消绑定</a>',$channel_id);
                        
                        $bind_btn .= ' | <a target="dialog::{width:500,height:300,title:\'取消绑定\'}" href="index.php?app=wmsmgr&ctl=admin_wms&act=unbind_verify&p[0]='. $channel_id .'&finder_id='.$_GET['_finder']['finder_id'].'" style="color:#990000;">取消绑定</a>';
                        
                        //初始化物流公司
                        $relationObj = app::get('wmsmgr')->model('express_relation');
                        $relationCount = $relationObj->count(array('wms_id'=>$channel_id));
                        if($relationCount < 20){
                            $logi_btn = ' | <a href="index.php?app=wmsmgr&ctl=admin_wms&act=initializeLogi&p[0]='.$row[$this->col_prefix.'channel_id'].'&finder_id='.$finder_id.'" target="dialog::{width:500,height:300,title:\'初始化京东物流公司映射关系\'}">初始化物流公司</a>';
                        }
                        
                    }else{
                        $bind_btn .= sprintf(' | <a href="index.php?app=wmsmgr&ctl=admin_wms&act=bindNodeId&p[0]=%s&finder_id=%s" target="dialog::{width:300,height:200,title:\'输入node_id\'}">申请绑定</a>',$channel_id,$finder_id);
                    }
                    
                    break;
                case 'other':
                default:
                    $bind_btn .= empty($node_id) ?
                ' | <a href="index.php?app=wmsmgr&ctl=admin_wms&act=apply_bindrelation&p[0]='.$app_id.'&p[1]='.$callback_url.'&p[2]='.$api_url.'" target="_blank">申请绑定</a>' : ' | 已绑定';
                break;
            }
            
            //京东一件代发
            if($node_id && $node_type=='yjdf'){
                //初始化物流公司按钮
                $relationObj = app::get('wmsmgr')->model('express_relation');
                $relationCount = $relationObj->count(array('wms_id'=>$channel_id));
                if($relationCount < 20 && empty($logi_btn)){
                    $logi_btn = ' | <a href="index.php?app=wmsmgr&ctl=admin_wms&act=initializeLogi&p[0]='.$row[$this->col_prefix.'channel_id'].'&finder_id='.$finder_id.'" target="dialog::{width:500,height:300,title:\'初始化京东物流公司映射关系\'}">初始化物流公司</a>';
                }
            }
        }
        
        if ($extend_button){
            $extend_button = ' | '.implode(' | ',$extend_button);
        }else{
            $extend_button = '';
        }
        if($node_id && $row[$this->col_prefix.'node_type'] == 'jd_wms_cloud') {
            $bind_btn .= ' | <a href="index.php?app=wmsmgr&ctl=admin_wms&act=syncBase&p[0]='.$channel_id.'&finder_id='.$finder_id.'" target="dialog::{width:650,height:300,title:\'同步基础数据\'}">初始化</a>';
        }
        if(in_array($adapter,array('matrixwms','mixturewms'))){
            return $edit_btn.$bind_btn.$extend_button.$expressBtn. $logi_btn;
        }else{
            return $edit_btn.$extend_button.$expressBtn;
        }
    }

    var $detail_company = '物流公司编码配置';
    function detail_company($wms_id){
        $render = app::get('wmsmgr')->render();
        $express_relation_mdl = app::get('wmsmgr')->model('express_relation');
        if($this->vue_mode){
            $dly_corp_mdl = app::get('ome')->model('dly_corp');
            $sys_express_corp = $dly_corp_mdl->getlist('*');
            $wms = $express_relation_mdl->getlist('*',array('wms_id'=>$wms_id));
            $wmsBn = array();
            foreach($wms as $v){
                $wmsBn[$v['logi_id']] = $v['wms_express_bn'];
            }
            return array('detail'=>array(
                'wms_id' => $wms_id,
                'sys_express_corp' => $sys_express_corp,
                'wmsBn' => $wmsBn,
            ));
        }
        if($_POST){
            kernel::single('wmsmgr_capability_wms')->saveDetailCompany(array(
                'wms_id'=>$wms_id,
                'wms_express_bn'=>isset($_POST['wms_express_bn']) ? $_POST['wms_express_bn'] : array(),
                'sys_express_bn'=>isset($_POST['sys_express_bn']) ? $_POST['sys_express_bn'] : array(),
            ), false);
        }
        $dly_corp_mdl = app::get('ome')->model('dly_corp');
        $sys_express_corp = $dly_corp_mdl->getlist('*');
        $wms = $express_relation_mdl->getlist('*',array('wms_id'=>$wms_id));
        foreach($wms as $v){
            $wmsBn[$v['logi_id']] = $v['wms_express_bn'];
        }
        $render->pagedata['sys_express_corp'] = $sys_express_corp;
        $render->pagedata['wms_id'] = $wms_id;
        $render->pagedata['wmsBn'] = $wmsBn;
        return $render->fetch("exitExpress.html");
    }

    var $detail_shop = '店铺售达方编号';
    function detail_shop($wms_id){
        $render = app::get('wmsmgr')->render();
        if($this->vue_mode){
            $shop_config = app::get('finance')->getConf('shop_config_'.$wms_id);
            $shopMdl = app::get('ome')->model('shop');
            $shop_list = $shopMdl->getlist('*', array('s_type' => '1'));
            foreach($shop_list as $k=>&$v){
                $v['wms_code'] = isset($shop_config[$v['shop_bn']]) ? $shop_config[$v['shop_bn']] : $v['shop_bn'];
            }
            unset($v);
            return array('detail'=>array(
                'wms_id' => $wms_id,
                'shop_list' => $shop_list,
            ));
        }
        if($_POST){
            kernel::single('wmsmgr_capability_wms')->saveDetailShop(array(
                'wms_id'=>$wms_id,
                'shop_config'=>isset($_POST['shop_config']) ? $_POST['shop_config'] : array(),
            ), false);
        }
        $shop_config = app::get('finance')->getConf('shop_config_'.$wms_id);

        $shopMdl = app::get('ome')->model('shop');
        $shop_list = $shopMdl->getlist('*', array('s_type' => '1'));
        foreach($shop_list as $k=>&$v){
            $v['wms_code'] = isset($shop_config[$v['shop_bn']]) ? $shop_config[$v['shop_bn']] : $v['shop_bn'];
        }
        $render->pagedata['shop_list'] = $shop_list;
        return $render->fetch("shop_config.html");
    }

    var $detail_branch = '仓库编码配置';
    function detail_branch($wms_id){
        $render = app::get('wmsmgr')->render();
        $oBranch_relation = app::get('wmsmgr')->model('branch_relation');
        $oBranch = app::get('ome')->model('branch');
        if($this->vue_mode){
            $branch_list = $oBranch->getlist('*',array('wms_id'=>$wms_id));
            $branch_relation_list = $oBranch_relation->getlist('*',array('wms_id'=>$wms_id));
            $wms_branch = array();
            foreach ($branch_relation_list as $branch ) {
                $wms_branch[$branch['sys_branch_bn']] = $branch;
            }
            return array('detail'=>array(
                'wms_id' => $wms_id,
                'branch_list' => $branch_list,
                'wms_branch' => $wms_branch,
            ));
        }
        if($_POST){
            kernel::single('wmsmgr_capability_wms')->saveDetailBranch(array(
                'wms_id'=>$wms_id,
                'wms_branch_bn'=>isset($_POST['wms_branch_bn']) ? $_POST['wms_branch_bn'] : array(),
                'positive'=>isset($_POST['positive']) ? $_POST['positive'] : array(),
                'negative'=>isset($_POST['negative']) ? $_POST['negative'] : array(),
            ), false);
        }

        $branch_list = $oBranch->getlist('*',array('wms_id'=>$wms_id));
        $render->pagedata['branch_list'] = $branch_list;
        unset($branch_list);

        $branch_relation_list = $oBranch_relation->getlist('*',array('wms_id'=>$wms_id));
        $wms_branch = array();
        foreach ($branch_relation_list as $branch ) {
            $wms_branch[$branch['sys_branch_bn']] = $branch;
        }

        $render->pagedata['wms_branch'] = $wms_branch;
        unset($wms_branch,$branch_relation_list);
        return $render->fetch("branch_config.html");
    }

    var $detail_monthaccount = '月结号';

    /**
     * Short description.
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    function detail_monthaccount ($wms_id)
    {
        $render = app::get('wmsmgr')->render();
        if($this->vue_mode){
            $monthaccount = app::get('wmsmgr')->getConf('monthaccount_'.$wms_id);
            $department_no = app::get('wmsmgr')->getConf('department_no_'.$wms_id);
            $isv_source = app::get('wmsmgr')->getConf('isv_source_'.$wms_id);
            $owner_user_id = app::get('wmsmgr')->getConf('owner_user_id_'.$wms_id);
            return array('detail'=>array(
                'wms_id' => $wms_id,
                'monthaccount' => $monthaccount,
                'department_no' => $department_no,
                'isv_source' => $isv_source,
                'owner_user_id' => $owner_user_id,
            ));
        }
        if($_POST){
            kernel::single('wmsmgr_capability_wms')->saveDetailMonthaccount(array(
                'wms_id'=>$wms_id,
                'monthaccount'=>isset($_POST['monthaccount']) ? $_POST['monthaccount'] : '',
                'department_no'=>isset($_POST['department_no']) ? $_POST['department_no'] : '',
                'isv_source'=>isset($_POST['isv_source']) ? $_POST['isv_source'] : '',
                'owner_user_id'=>isset($_POST['owner_user_id']) ? $_POST['owner_user_id'] : '',
            ), false);
        }
        $monthaccount = app::get('wmsmgr')->getConf('monthaccount_'.$wms_id);
        
        $department_no = app::get('wmsmgr')->getConf('department_no_'.$wms_id);
        $isv_source = app::get('wmsmgr')->getConf('isv_source_'.$wms_id);
        $owner_user_id = app::get('wmsmgr')->getConf('owner_user_id_'.$wms_id);
        $render->pagedata['monthaccount'] = $monthaccount;
        $render->pagedata['department_no'] = $department_no;
        $render->pagedata['isv_source'] = $isv_source;
        $render->pagedata['owner_user_id'] = $owner_user_id;
        $render->pagedata['wms_id'] = $wms_id;
        return $render->fetch("monthaccount.html");
    }

    public $detail_basic = '基础配置';
    /**
     * detail_basic
     * @param mixed $wms_id ID
     * @return mixed 返回值
     */
    public function detail_basic($wms_id)
    {
        $render = app::get('wmsmgr')->render();

        $wmsMdl = app::get('wmsmgr')->model('wms');
        $dlyObj = app::get('ome')->model('dly_corp');

        if($this->vue_mode){
            $corpList = $dlyObj->getlist('corp_id,type,name', array());
            $wms = $wmsMdl->dump($wms_id, 'channel_id,crop_config,node_type');
            $wms['crop_config'] = is_array($wms['crop_config'] ?? null) ? $wms['crop_config'] : array();
            // 配送拦截，默认为“是”
            if (!isset($wms['crop_config']['saleorder_callback'])) {
                $wms['crop_config']['saleorder_callback'] = '1';
            }
            $invoice = isset($wms['crop_config']['invoice']) ? $wms['crop_config']['invoice'] : null;
            unset($wms['crop_config']['invoice']);
            return array('detail'=>array(
                'wms_id' => $wms_id,
                'corpList' => $corpList,
                'crop_config' => $wms['crop_config'],
                'invoice' => $invoice,
                'node_type' => isset($wms['node_type']) ? $wms['node_type'] : '',
            ));
        }
        
        //物流公司
        $corpList = $dlyObj->getlist('corp_id,type,name', array());
        $render->pagedata['corpList'] = $corpList;
        
        //WMS仓储信息
        $wms = $wmsMdl->dump($wms_id, 'channel_id,crop_config,node_type');
        if ($_POST && $wms_id) {
            kernel::single('wmsmgr_capability_wms')->saveDetailBasic(array_merge(array('wms_id'=>$wms_id), $_POST), false);
            $wms = $wmsMdl->dump($wms_id, 'channel_id,crop_config,node_type');
        }

        // 配送拦截，默认为“是”
        if (!isset($wms['crop_config']['saleorder_callback'])) {
            $wms['crop_config']['saleorder_callback'] = '1';
        }

        $invoice = $wms['crop_config']['invoice']; unset($wms['crop_config']['invoice']);
        $render->pagedata['node_type'] = $wms['node_type'];
        $render->pagedata['invoice'] = $invoice;
        $render->pagedata['crop_config'] = $wms['crop_config'];
        $render->pagedata['node_type'] = $wms['node_type'];
        return $render->fetch("basic.html");
    }
    public $detail_supplier = '供应商编码配置';
    /**
     * detail_supplier
     * @param mixed $wms_id ID
     * @return mixed 返回值
     */
    public function detail_supplier($wms_id) {
        $render = app::get('wmsmgr')->render();
        $supplierRelationModel = app::get('wmsmgr')->model('supplier_relation');
        $oSupplier = app::get('purchase')->model('supplier');
        if($this->vue_mode){
            $supplierList = array();
            $rows = $oSupplier->getlist('supplier_id,name',array());
            foreach ($rows as $key => $value) {
                $supplierList[$value['supplier_id']] = $value;
            }
            $rows = $supplierRelationModel->getlist('*',array('wms_id'=>$wms_id));
            foreach ($rows as $key => $value) {
                $supplierList[$value['supplier_id']]['wms'] = $value;
            }
            return array('detail'=>array(
                'wms_id' => $wms_id,
                'supplier_list' => $supplierList,
            ));
        }
        if($_POST['wms']){
            kernel::single('wmsmgr_capability_wms')->saveDetailSupplier(array(
                'wms_id'=>$wms_id,
                'wms'=>$_POST['wms'],
            ), false);
        }
        $supplierList = array();
        $rows = $oSupplier->getlist('supplier_id,name',array());
        foreach ($rows as $key => $value) {
            $supplierList[$value['supplier_id']] = $value;
        }
        $rows = $supplierRelationModel->getlist('*',array('wms_id'=>$wms_id));
        foreach ($rows as $key => $value) {
            $supplierList[$value['supplier_id']]['wms'] = $value;
        }
        $render->pagedata['supplier_list'] = $supplierList;
        $render->pagedata['wms_id']      = $wms_id;
        return $render->fetch("supplier_config.html");
    }
}
