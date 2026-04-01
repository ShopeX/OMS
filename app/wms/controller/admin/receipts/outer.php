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

class wms_ctl_admin_receipts_outer extends wms_ctl_admin_receipts_print {

    var $name        = "发货中心";
    var $workground  = "wms_center";
    var $dlyCorp_tab = 'show';


    public function _views($base_filter = [], $source = '') {
        if($this->dlyCorp_tab == 'hidden'){
           return array();
        }

        $status = kernel::single('base_component_request')->get_get('status');
        $sku = kernel::single('base_component_request')->get_get('sku');

        $query = array(
            'app'    => 'wms',
            'ctl'    => 'admin_receipts_outer',
            'act'    => 'index',
            'status' => $status,
            'sku'    => $sku,
        );

        # 所有第三方仓
        $outerBranch = array();
        $oBranch = app::get('ome')->model('branch');
        $tmpBranchList = $oBranch->getList('branch_id',array('owner'=>'2'));
        foreach ($tmpBranchList as $key => $value) {
            $outerBranch[] = $value['branch_id'];
        }
        unset($tmpBranchList);

        $sub_menu = $this->getView($status);
        $i = 0;
        $mdl_order = app::get('wms')->model('delivery');
        foreach ($sub_menu as $k => $v) {
            //$v['filter']['branch_id'] = $outerBranch;
            //非管理员取管辖仓与自建仓的交集
            $v['filter']['ext_branch_id'] = $v['filter']['ext_branch_id'] ? $v['filter']['ext_branch_id'] : $outerBranch;
            if (!is_array($v['filter']['ext_branch_id'])) {
                $v['filter']['ext_branch_id'] = array($v['filter']['ext_branch_id']);
            }
            $v['filter']['ext_branch_id'] = array_intersect($v['filter']['ext_branch_id'], $outerBranch);
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $mdl_order->count($v['filter']);
            $query['view'] = $i++;
            $query['logi_id'] = urlencode($v['filter']['logi_id']);
            $sub_menu[$k]['href'] = 'index.php?' . http_build_query($query);
        }
        return $sub_menu;
    }

    /**
     * 第三方发货单列表
     *
     * @author chenping<chenping@shopex.cn>
     **/
    public function index()
    {
        if (isset($_POST['delivery_bn']) && $_POST['delivery_bn']) {
            $deliveryObj = app::get('ome')->model('delivery');
            $rows = $deliveryObj->getParentIdBybn($_POST['delivery_bn']);
            if ($rows) {
                foreach ($rows as $val) {
                    $deliveryId[] = $val['parent_id'];
                }
                $filter['extend_delivery_id'] = $deliveryId;
            }
        }
        $filter['type'] = array('normal');

        //分析status的filter条件
        //$tmp_filter = $this->analyseStatus($_GET['status']);
        $tmp_filter=array();
        $filter = array_merge($filter, $tmp_filter);
        
        $oBranch = app::get('ome')->model('branch');
        //所有第三方仓
        $outerBranch = array();
        $tmpBranchList = $oBranch->getList('branch_id',array('owner'=>'2'));
        foreach ($tmpBranchList as $key => $value) {
            $outerBranch[] = $value['branch_id'];
        }
        unset($tmpBranchList);
         # 发货配置
        $deliCfgLib = kernel::single('wms_delivery_cfg');
        //获取操作员管辖仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $filter['ext_branch_id'] = $_POST['branch_id'] ? $_POST['branch_id'] : $branch_ids;
                if (!is_array($filter['ext_branch_id'])) {
                    $filter['ext_branch_id'] = array($filter['ext_branch_id']);
                }
                $filter['ext_branch_id'] = array_intersect($filter['ext_branch_id'], $outerBranch);
            } else {
                $filter['ext_branch_id'] = 'false';
            }
        } else {
            $filter['ext_branch_id'] = $outerBranch;
        }

        $params = array(
            'title'                  => '第三方发货单列表',
            'actions'                => array(
                array(
                    'label'  => '快速发货模板',
                    'submit' => 'index.php?app=wms&ctl=admin_receipts_outer&act=exportTemplate',
                    'target' => '_blank',
                ),
                array(
                    'label'  => '快速发货导入',
                    'href'   => 'index.php?app=wms&ctl=admin_receipts_outer&act=index&action=import',
                    'target' => 'dialog::{title:\'快速发货导入\',width:400,height:200}',
                ),
                
                array(
                    'label'  => '导出',
                    'submit' => 'index.php?app=wms&ctl=admin_receipts_outer&act=index&action=export&btype=2',
                    'target' => 'dialog::{width:400,height:170,title:\'导出\'}',
                ),
                
                 'stock' => array(
                    'label' => '打印备货单',
                    'submit' => 'index.php?app=wms&ctl=admin_receipts_print&act=toPrintStock' . $attach,
                    'target' => "_blank",
                ),
                'delie' => array(
                    'label' => '打印发货单',
                    'submit' => 'index.php?app=wms&ctl=admin_receipts_print&act=toPrintMerge' . $attach,
                    'target' => '_blank',
                ),
                'merge' => array(
                    'label' => '联合打印',
                    'submit' => 'index.php?app=wms&ctl=admin_receipts_print&act=toMergePrint' . $attach,
                    'target' => '_blank',
                ),
            ),
            'base_filter'            => $filter,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_view_tab'           => true,
            //'object_method'          => array('count' => 'count_logi_no', 'getlist' => 'getlist_logi_no'),
        );
        //选择显示打印的按钮
        $showStockBtn = $deliCfgLib->analyse_btn_status('stock',$sku);
        if ($showStockBtn == false) {
            unset($params['actions']['stock']);
        }
        $showDelieBtn = $deliCfgLib->analyse_btn_status('delie',$sku);
        if ($showDelieBtn == false) {
            unset($params['actions']['delie']);
        }
        $showMergeBtn = $deliCfgLib->analyse_btn_status('merge',$sku);
        if ($showMergeBtn == false) {
            unset($params['actions']['merge']);
        }
        $is_export = kernel::single('desktop_user')->has_permission('process_receipts_print_export');
        if(!$is_export){
            unset($params['actions']['export']);
        }
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('delivery_finder_top');
            $panel->setTmpl('admin/finder/finder_panel_filter.html');
            $panel->show('wms_mdl_delivery_outerlogi', $params);
        }

        $this->finder('wms_mdl_delivery_outerlogi',$params);
    }
   
    #自定义发货模板导出
    public function exportTemplate(){
        if(!empty($_POST['delivery_id'])){
           $filter['delivery_id'] = $_POST['delivery_id'];
        }
        if(isset($_POST['isSelectedAll']) &&  ($_POST['isSelectedAll'] ==  '_ALL_')){
            $filter['isSelectedAll'] = '_ALL_';
        }
        if(empty($filter)){
            echo '请选择导出发货单';exit;
        }
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=outer".date('Ymd').".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');   
        $obj_outerlogi = $this->app->model('delivery_outerlogi');
        $title1 = $obj_outerlogi->exportTemplate();
        echo '"'.implode('","',$title1).'"'; 
        echo "\n";
        $obj_outerlogi = $this->app->model('delivery_outerlogi');
        $data =  $obj_outerlogi->getOuterData($filter);
        foreach( $data['content'] as $val){
            echo $val;
            echo "\n";
        }
    }  
}
