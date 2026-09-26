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
    var $addon_cols = "channel_id,channel_name,node_id,channel_type,node_type,config";
    var $column_edit = "操作";
    var $column_edit_width = "260";
    var $column_edit_order = "1";
    function column_edit($row){
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
        if($_POST){
            $express_relation_mdl->delete(['wms_id'=>$wms_id]);
            $wms_express_bn = $_POST['wms_express_bn'];
            foreach($wms_express_bn as $k=>$v){
                if ($k && $v) {
                    $sdata = array('wms_id'=>$wms_id,'logi_id'=>$k,'sys_express_bn'=>trim($_POST['sys_express_bn'][$k]),'wms_express_bn'=>trim($v));
                    $rs = $express_relation_mdl->save($sdata);
                }

            }
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
        if($_POST){
            $shop_config = $_POST['shop_config'];
            app::get('finance')->setConf('shop_config_'.$wms_id,$shop_config);
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
        if($_POST){
            $wms_branch_bn = $_POST['wms_branch_bn'];
            $oBranch_relation->delete(['wms_id'=>$wms_id]);
            foreach($wms_branch_bn as $k=>$v){
                $sdata = array(
                    'wms_id'=>$wms_id,
                    'sys_branch_bn'=>$k,
                    'wms_branch_bn'=>$v,
                    'positive'=>$_POST['positive'][$k] ? '1' : '2',
                    'negative'=>(int)$_POST['negative'][$k],
                );
                $rs = $oBranch_relation->insert($sdata);
            }
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
        if($_POST){
            $monthaccount = $_POST['monthaccount'];
            app::get('wmsmgr')->setConf('monthaccount_'.$wms_id,$monthaccount);
            $department_no = trim($_POST['department_no']);
            $isv_source = trim($_POST['isv_source']);
            $owner_user_id = trim($_POST['owner_user_id']);
            app::get('wmsmgr')->setConf('department_no_'.$wms_id,$department_no);
            app::get('wmsmgr')->setConf('isv_source_'.$wms_id,$isv_source);
            app::get('wmsmgr')->setConf('owner_user_id_'.$wms_id,$owner_user_id);

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
        
        //物流公司
        $corpList = $dlyObj->getlist('corp_id,type,name', array());
        $render->pagedata['corpList'] = $corpList;
        
        //WMS仓储信息
        $wms = $wmsMdl->dump($wms_id, 'channel_id,crop_config,node_type');
        if ($_POST && $wms_id) {
            $wms['channel_id'] = $wms_id;
            $wms['crop_config'] = array_merge((array)$wms['crop_config'],$_POST);

            $wmsMdl->save($wms);
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
        if($_POST['wms']){
            foreach($_POST['wms'] as $k=>$v){
                $sdata = array('wms_id'=>$wms_id,'supplier_id'=>$k,'wms_supplier_bn'=>trim($v['supplier_bn']));
                $supplierRelationModel->db_save($sdata);
            }
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
