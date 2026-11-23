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
 * 仓库预占流水
 * 
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 */
class console_ctl_admin_stock_freeze extends desktop_controller{
    
    var $workground = "console_center";
    
    function index()
    {
        $this->title = '预占流水列表';
        $filter      = array();
        // $filter['num|than'] = 0;
        
        //店铺
        $shop_id    = trim($_POST['shop_id']);
        if($shop_id)
        {
            $filter['shop_id']    = $shop_id;
        }
        
        //仓库
        $branch_id    = intval($_POST['branch_id']);
        if($branch_id)
        {
            $filter['branch_id']    = $branch_id;
        }
        
        //对象类型
        $obj_type    = intval($_POST['obj_type']);
        if($obj_type)
        {
            $filter['obj_type']    = $obj_type;
        }
        
        //业务类型
        if(isset($_POST['bill_type']) && $_POST['bill_type']>=0)
        {
            $bill_type    = intval($_POST['bill_type']);
            $filter['bill_type']    = $bill_type;
        }
        
        //基础物料
        $material_bn    = trim($_POST['material_bn']);
        if($material_bn)
        {
            $mdlMaterialBasicMaterial    = app::get('material')->model('basic_material');
            $tempInfo    = $mdlMaterialBasicMaterial->dump(array('material_bn'=>$material_bn), 'bm_id');
            $filter['bm_id']    = intval($tempInfo['bm_id']);
        }

        $actions[] = array(
            'label'  => '批量释放',
            'submit' => $this->url.'&act=batch_unfreeze_dialog',
            'target' => 'dialog::{width:600,height:200,title:\'只支持京东店铺的售后预占释放，确定要释放预占吗？\'}"',
        );
        
        //params
        $params = array(
                'title'=>$this->title,
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>true,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false,
                'actions'=>$actions,
                'base_filter' => $filter,
        );
        
        //top filter
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('stock_freeze_finder_top');
            $panel->setTmpl('admin/finder/stock_freeze_top_filter.html');
            $panel->show('console_mdl_basic_material_stock_freeze', $params);
        }
        
        $this->finder('console_mdl_basic_material_stock_freeze', $params);
    }
    
    public function show_store_freeze_list() {
        $filter = [];
        $filter['bm_id']    = intval($_GET['product_id']);
        $filter['num|than'] = 0;
        $params = array(
            'title'=>'预占流水列表',
            'use_buildin_set_tag'=>false,
            'use_buildin_filter'=>true,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_selectrow'=>false,
            'actions'=>array(),
            'base_filter' => $filter,
        );
        $this->finder('console_mdl_basic_material_stock_freeze', $params);
    }
    //加载业务类型
    function ajax_bmsq_type()
    {
        $obj_type    = $_POST['obj_type'];
        if(empty($obj_type))
        {
            echo json_encode(array('res'=>'error'));
            exit;
        }
        
        $stockFreezeObj    = app::get('console')->model('basic_material_stock_freeze');
        $typeList          = $stockFreezeObj->get_type();
        
        $html	= '<select type="select" name="bill_type" id="bill_type" class="x-input-select inputstyle"><option value=""></option>';
        if($typeList[$obj_type])
        {
            foreach ($typeList[$obj_type] as $key => $val)
            {
                $html    .= '<option value="'. $key .'">'. $val .'</option>';
            }
        }
        $html    .= '</select>';
        
        echo json_encode(array('res'=>'succ', 'html'=>$html));
        exit;
    }

    //单个释放
    function single_unfreeze($bmsf_id){
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');
        //开启事务
        // kernel::database()->beginTransaction();

        $stockFreezeMdl = app::get('material')->model('basic_material_stock_freeze');
        $info = $stockFreezeMdl->db_dump(['bmsf_id'=>$bmsf_id]);
        if(!$info){
            $this->end(false, '所选数据无效');
        }
        if ($info['num'] <= 0) {
            $this->end(false, '没有释放数量');
        }

        //库存管控 生成订单后释放库存
        $operateLog = app::get('ome')->model('operation_log');
        $branchMdl = app::get('ome')->model('branch');
        $materialMdl = app::get('material')->model('basic_material');

        $branchInfo = $branchMdl->db_dump(['branch_id' => $info['branch_id'], 'check_permission'=>'false']);
        $bmInfo = $materialMdl->db_dump(['bm_id' => $info['bm_id']]);

        if($info['bill_type'] == 2){
            $reship_id = $info['obj_id'];
            $res = kernel::single('console_reship')->releaseChangeFreeze($reship_id, $info['bm_id']);
            $operateLog->write_log('reship@ome', $reship_id, '人工释放预占，仓:'.$branchInfo['name'].'，物料编码:'.$bmInfo['material_bn']);

        } elseif ($info['bill_type'] == 12) {
            $return_id = $info['obj_id'];
            $res = kernel::single('ome_return_product')->releaseChangeFreeze($return_id, $info['bm_id']);
            $operateLog->write_log('return@ome', $return_id, '人工释放预占，仓:'.$branchInfo['name'].'，物料编码:'.$bmInfo['material_bn']);
        }
        if ($res[0] == true) {
            // kernel::database()->commit();
            $this->end(true, '释放成功');
        } else {
            // kernel::database()->rollBack();
            $this->end(false, '释放失败:'.$res[1]['msg']);
        }
    }


    /**
     * 批量推送发货单至WMS仓储
     */
    public function batch_unfreeze_dialog()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);

        $bmsfIds = $_POST['bmsf_id'];
        unset($_POST['bmsf_id']);
                
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择200条!');
        }
        
        if(empty($bmsfIds)){
            die('请选择需要释放的预占流水!');
        }
        
        if(count($bmsfIds) > 200){
            die('每次最多只能选择200条!');
        }

        //data
        $shopList = kernel::single('omeanalysts_shop')->getShopList();
        $shopIds = $shopList['360buy'];

        $filter = [
            'bmsf_id|in'    =>  $bmsfIds,
            'bill_type|in'  =>  [
                material_basic_material_stock_freeze::__RESHIP,
                material_basic_material_stock_freeze::__RETURN
            ],
            'num|than'      =>  0,
            'shop_id|in'    =>  $shopIds,
        ];
        $stockFreezeMdl = app::get('material')->model('basic_material_stock_freeze');
        $dataList = $stockFreezeMdl->getList('bmsf_id', $filter);
        if(empty($dataList)){
            die('没有可释放的预占流水!');
        }
        
        $bmsfIds = array_column($dataList, 'bmsf_id');
        $_POST['bmsf_id'] = $bmsfIds; // 更新$_POST['bmsf_id']
        
        $this->pagedata['GroupList'] = json_encode($bmsfIds);
        
        $this->pagedata['request_url'] = $this->url .'&act=batch_unfreeze';

        // 因为释放成功以后，会删除数据，数据在减少，所以第四个参数需要给zero，不能分页取，应该每次都取第一页
        // 但有个潜在问题，如果某次处理有失败的，以后每次再取数据，会再次把失败的取到，最终出现有的数据没被取到过
        parent::dialog_batch('console_mdl_basic_material_stock_freeze', false, 20, 'zero');
    }


    //整单释放
    public function single_order_unfreeze($order_id) {
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');

        if (!$order_id) {
            $this->end(false, '订单ID不能为空');
        }

        $orderObj = app::get('ome')->model('orders');
        $orderInfo = $orderObj->dump(['order_id' => $order_id], 'order_bn,process_status,status');
        
        if (!$orderInfo) {
            $this->end(false, '订单不存在');
        }

        if (!($orderInfo['process_status'] == 'cancel' || $orderInfo['status'] == 'dead')) {
            $this->end(false, '只能释放已取消或已作废的订单');
        }

        $orderObj->unfreez($order_id);
        $this->end(true, '整单释放成功');
    }

    public function batch_unfreeze() {
        $branchMdl      = app::get('ome')->model('branch');
        $operateLog     = app::get('ome')->model('operation_log');
        $materialMdl    = app::get('material')->model('basic_material');
        $stockFreezeMdl = app::get('material')->model('basic_material_stock_freeze');

        $retArr = ['itotal' => 0, 'isucc' => 0, 'ifail' => 0, 'err_msg' => []];

        //获取发货单号
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择需要释放的预占流水';
            exit;
        }

        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }

        //data
        $dataList = $stockFreezeMdl->getList('*', $filter, $offset, $limit);
        //check
        if (empty($dataList)) {
            echo 'Error: 没有获取到需要释放的预占流水';
            exit;
        }

        $branchIds  = array_column($dataList, 'branch_id');
        $branchList = $branchMdl->getList('branch_id,name', ['branch_id' => $branchIds, 'check_permission'=>'false']);
        $branchList = array_column($branchList, null, 'branch_id');

        $bmIds  = array_column($dataList, 'bm_id');
        $bmList = $materialMdl->getList('bm_id,material_bn', ['bm_id' => $bmIds]);
        $bmList = array_column($bmList, null, 'bm_id');

        //count
        $retArr['itotal'] = count($dataList);

        //list
        foreach ($dataList as $key => $info){
            $res = [];
            kernel::database()->beginTransaction();
            if($info['bill_type'] == 2){
                $reship_id = $info['obj_id'];
                $res = kernel::single('console_reship')->releaseChangeFreeze($reship_id, $info['bm_id']);
                $operateLog->write_log('reship@ome', $reship_id, '批量人工释放预占，仓:'.$branchList[$info['branch_id']]['name'].'，物料编码:'.$bmList[$info['bm_id']]['material_bn']);
            } elseif ($info['bill_type'] == 12) {
                $return_id = $info['obj_id'];
                $res = kernel::single('ome_return_product')->releaseChangeFreeze($return_id, $info['bm_id']);
                $operateLog->write_log('return@ome', $return_id, '批量人工释放预占，仓:'.$branchList[$info['branch_id']]['name'].'，物料编码:'.$bmList[$info['bm_id']]['material_bn']);
            }
            if ($res[0] == true) {
                kernel::database()->commit();
                $retArr['isucc'] += 1;
            } else {
                kernel::database()->rollBack();
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = '单据号'.$info['obj_bn'].'的物料编码'.$bmList[$info['bm_id']]['material_bn'].'释放失败:'.$res[1]['msg'];
            }
        }

        echo json_encode($retArr),'ok.';
        exit;
    }

}
?>
