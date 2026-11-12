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

class dealer_finder_reship {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $addon_cols = 'reship_id,is_check,source,order_id,changebranch_id,return_id,return_type,change_order_id,shop_type,shop_id,reship_bn';

    
    var $column_edit = "操作";
    var $column_edit_width = "200";
    function column_edit($row){
        
        $is_check = $row[$this->col_prefix.'is_check'];
        $reship_bn = $row[$this->col_prefix.'reship_bn'];
        if($is_check==0){
            $cols = '<a class="lnk" href="javascript:if(confirm(\'确认审核退货单:'.$reship_bn.'?\')) {W.page(\'index.php?app=dealer&ctl=admin_reship&act=confirm&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'&finder_vid='.$_GET['finder_vid'].'\');};">
                            审核</a>';
        }
        
        return $cols ? $cols : '';
    }

    var $detail_basic = "退货单详情";

     function detail_basic($reship_id)
    {
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $render = app::get('ome')->render();
        $oReship = app::get('ome')->model('reship');
        $corpObj = app::get('ome')->model('dly_corp');

        $branchLib = kernel::single('ome_branch');
        $reshipLib = kernel::single('ome_reship');

        $keplerLib = kernel::single('ome_reship_kepler');
        
        //退货单信息
        $detail = $oReship->getCheckinfo($reship_id);

        $oDesktop          = app::get('desktop')->model('users');
        $desktop_detail    = $oDesktop->dump(array('user_id'=>$detail['op_id']), 'name');
        $detail['op_name'] = $desktop_detail['name'];

        if($detail['is_check'] == '3') $detail['is_check'] = '1';

        $cols = $oReship->_columns();
        $detail['is_check'] = $cols['is_check']['type'][$detail['is_check']];

        $reason = unserialize($detail['reason']);
        $detail['check_memo'] = $reason['check'];

        # 售后问题类型
        if ($detail['problem_id']) {
            $detail['problem_name'] = app::get('ome')->model('return_product_problem')->getCatName($detail['problem_id']);
        }

        //退回物流信息
        $return_id    = $detail['return_id'];
        if($return_id && empty($detail['return_logi_id']))
        {
            $reProductObj    = app::get('ome')->model('return_product');
            $product_detail  = $reProductObj->dump(array('return_id'=>$return_id), 'process_data');
            $process_data    = ($product_detail['process_data'] ? unserialize($product_detail['process_data']) : '');
            if($process_data)
            {
                $corpinfo = $corpObj->dump(array('name'=>$process_data['shipcompany']), 'corp_id');

                $detail['return_logi_no']    = $process_data['logino'];
            }
        }
        if($return_id) {
            $render->pagedata['return_freight_data'] = app::get('ome')->model('return_freight')->db_dump(['return_id'=>$return_id]);
        }
        if(is_numeric($detail['return_logi_name'])){
            $corpinfo = $corpObj->dump($detail['return_logi_name'], 'name');
            $detail['return_logi_name']=$corpinfo['name'];
        }

        $render->pagedata['detail'] = $detail;
        //[换货]关联的销售物料的类型
        if($detail['return_type'] == 'change'){
            $reshipObjects    = app::get('ome')->model('reship_objects');
            $temp_data    = $reshipObjects->getList('obj_id, obj_type', array('reship_id'=>$reship_id));
            $reship_objects    = array();
            if($temp_data){
                foreach ($temp_data as $key => $val){
                    $reship_objects[$val['obj_id']]    = $val['obj_type'];
                }
            }
            unset($temp_data);
        }
        $branchlib = kernel::single('ome_branch');

        //退换货明细
        $reship_item = $oReship->getItemList($reship_id);

        foreach ($reship_item as $key => $value){
            $spec_info    = $basicMaterialExtObj->dump(array('bm_id'=>$value['product_id']), '*');
            $reship_item[$key]['spec_info'] = $spec_info['specifications'];
            $reship_item[$key]['specifications'] = $spec_info['specifications'];
            #换货的销售物料类型
            if($value['obj_id']){
                $reship_item[$key]['item_type']    = $reship_objects[$value['obj_id']];
            }

            if ($value['return_type'] == 'change' && $value['changebranch_id']){

                $branch_detail = $branchlib->getBranchInfo($value['changebranch_id'],'name');

                $reship_item[$key]['branch_name'] = $branch_detail['name'];

            }
            $recover[$value['return_type']][] = $reship_item[$key];
        }

        $is_hqepay_on =  app::get('ome')->getConf('ome.delivery.hqepay');
        if($is_hqepay_on == 'false'){
            $is_hqepay_on = false;
        }else{
            $is_hqepay_on = true;
        }

        //[京东一件代发]
        $wms_type = $branchLib->getNodetypBybranchId($detail['branch_id']);
        if($wms_type == 'yjdf'){
            //物流公司列表
            $corpList = $corpObj->getList('corp_id,type,name', array('disabled'=>'false'));
            $render->pagedata['corpList'] = $corpList;

            //获取退货包裹明细
            $delivery_ids = array();
            $error_msg = '';
            $packageList = $reshipLib->get_reship_package($reship_id, $error_msg);
            if($packageList){
                foreach ($packageList as $key => $val)
                {
                    $delivery_id = $val['delivery_id'];

                    $delivery_ids[$delivery_id] = $delivery_id;
                }

                //京东包裹配送状态
                $deliveryLib = kernel::single('console_delivery');
                $shipStatusList = $deliveryLib->getShippingStatus();

                //发货包裹
                $packageObj = app::get('ome')->model('delivery_package');
                $tempList = $packageObj->getList('*', array('delivery_id'=>$delivery_ids));
                $dlyPackageList = array();
                foreach ($tempList as $key => $val)
                {
                    $package_bn = $val['package_bn'];

                    $dlyPackageList[$package_bn] = $val;
                }

                //京东包裹配送状态
                foreach ($packageList as $key => $val)
                {
                    $package_bn = $val['package_bn'];
                    $shipping_status = $dlyPackageList[$package_bn]['shipping_status'];

                    $packageList[$key]['shipping_status'] = $shipping_status;
                    $packageList[$key]['ship_status_name'] = $shipStatusList[$shipping_status];
                }
            }
            $render->pagedata['packageList'] = $packageList;

            //获取售后服务单
            $error_msg = '';
            $serviceList = $keplerLib->get_reship_services($reship_id, true, $error_msg);
            $render->pagedata['serviceList'] = $serviceList;
            
            //寄件地址列表
            $addressList = $keplerLib->getReturnAddressList($reship_id);
            $render->pagedata['addressList'] = $addressList;
        }
        
        if($detail['return_id']) {
            $render->pagedata['exchangeAddress'] = app::get('ome')->model('return_exchange_receiver')->db_dump(['return_id'=>$detail['return_id']]);
        }
        $render->pagedata['finder_id'] = ($_GET['_finder']['finder_id'] ? $_GET['_finder']['finder_id'] : $_GET['finder_id']);
        $render->pagedata['is_hqepay_on'] = $is_hqepay_on;
        $render->pagedata['items'] = $recover;
        return $render->fetch('admin/reship/detail.html');
    }

     var $detail_log = "操作日志";
    function detail_log($reship_id){
        $render = app::get('ome')->render();
        $oOperation_log = app::get('ome')->model('operation_log');
        $render->pagedata['log'] = $oOperation_log->read_log(array('obj_type'=>'reship@ome','obj_id'=>$reship_id),0,20,'log_id desc');
        return $render->fetch('admin/reship/detail_log.html');
    }
}