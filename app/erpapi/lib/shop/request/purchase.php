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
 * 唯品会JIT相关接口
 */
class erpapi_shop_request_purchase extends erpapi_shop_request_abstract {

    /**
     * 组织接口参数
     * 
     * @param string  $vop_method  接口名
     * @param int  $is_multiple_params 是否多个参数,默认1
     * @param array   data 查询条件
     * @return array
     */

    public function _format_api_params($vop_method, $is_multiple_params=1, $data)
    {
        $vop_param                        = array();
        $vop_param['vop_method']          = $vop_method;
        $vop_param['is_multiple_params']  = $is_multiple_params;
        $vop_param['data']                = ($data ? json_encode($data) : '{}');
        
        return $vop_param;
    }
    
    /**
     * 手工单拉PO单号
     */
    public function getPullPo($param)
    {
        if(empty($param['po_no']))
        {
            return false;
        }
        
        //组织参数getPoList
        $vop_param    = $this->_format_api_params('getPoList', 1, $param);
        
        $title      = '获取采购单号('. $param['po_no'] .')信息';
        $primary_bn = $param['po_no'];
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->getPoList_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 获取po列表
     */
    public function getPoList($param)
    {
        //组织参数
        $vop_param    = $this->_format_api_params('getPoList', 1, $param);
        
        $title        = '获取店铺('. $this->__channelObj->channel['name'] .')PO单列表';
        $primary_bn   = 'getPoList';
        
        $shop_info    = array('shop_id'=>$this->__channelObj->channel['shop_id']);
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->getPoList_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 回调
     */
    public function getPoList_callback($response, $callback_params=NULL)
    {
        $rsp        = $response['rsp'];
        $shop_id    = $this->__channelObj->channel['shop_id'];
        $is_empty   = false;
        
        if($rsp == 'succ')
        {
            $purchaseObj    = app::get('purchase')->model('order');
            $purchaseLib    = kernel::single('purchase_purchase_order');
            
            $data    = json_decode($response['data'], true);
            $data    = json_decode($data['msg'], true);
            
            $po_list = $data['result']['purchase_order_list'];//接口名getPoList
            
            $pick_pos    = array();//需要获取拣货单的PO单号
            
            //是否为空
            $is_empty    = (empty($po_list) ? true : false);
            
            if($po_list && is_array($po_list))
            {
                foreach ($po_list as $key => $val)
                {
                    $val['sell_st_time']    = $val['sell_st_time'] ? strtotime($val['sell_st_time']) : 0;
                    $val['sell_et_time']    = $val['sell_et_time'] ? strtotime($val['sell_et_time']) : 0;

                    if ($val['sell_st_time'] < 0) {
                        $val['sell_st_time'] = 0;
                    }

                    if ($val['sell_et_time'] < 0) {
                        $val['sell_et_time'] = 0;
                    }

                    
                    $poInfo    = $purchaseObj->dump(array('po_bn'=>$val['po_no']), 'po_id, sales_num, unpick_num');
                    if($poInfo)
                    {
                        //更新
                        $data    = array(
                                'po_id'=>$poInfo['po_id'],
                                'stock'=> intval($val['stock']),
                                'sales_num'=> intval($val['sales_volume']),
                                'unpick_num'=> intval($val['not_pick']),
                                'old_sales_num'=>$poInfo['sales_num'],
                                'old_unpick_num'=>$poInfo['unpick_num'],
                                'schedule_id'=>$val['schedule_id'],//档期号
                                'schedule_name'=>$val['schedule_name'],//档期名称
                                'sell_st_time'=>$val['sell_st_time'],//档期开始时间
                                'sell_et_time'=>$val['sell_et_time'],//档期结束时间
                        );
                        $result    = $purchaseLib->update_purchase($data);
                    }
                    else 
                    {
                        //新建
                        $data    = array(
                                'po_bn'=>$val['po_no'],//采购单号
                                'shop_id'=> $shop_id,//来源店铺
                                'co_mode'=> $val['co_mode'],
                                'sell_st_time'=>$val['sell_st_time'],//档期开始时间
                                'sell_et_time'=>$val['sell_et_time'],//档期结束时间
                                'stock'=> intval($val['stock']),//虚拟总库存
                                'sales_num'=> intval($val['sales_volume']),//销售数量
                                'unpick_num'=> intval($val['not_pick']),//未拣货数量
                                'trade_mode'=>$val['trade_mode'],
                                'schedule_id'=>$val['schedule_id'],//档期号
                                'schedule_name'=>$val['schedule_name'],//档期名称
                                'supplier_name'=>$val['vendor_name'],//供应商名称
                                'brand_name'=>$val['brand_name'],//品牌名称
                                'warehouse'=>$val['warehouse'],//仓库
                                'is_normal'=>intval($val['normality_flag']),//是否是常态档期
                        );
                        $result    = $purchaseLib->create_purchase($data);
                    }
                    
                    //[有未拣货数量]通过Po单号获取拣货单列表
                    if($result && $data['unpick_num'])
                    {
                        $pick_pos[]    = $val['po_no'];
                    }
                }
            }
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res'], 'is_empty'=>$is_empty);
        if($pick_pos)
        {
            $result['po_nos']    = $pick_pos;
        }
        
        return $result;
    }
    
    /**
     * 获取拣货单列表
     */
    public function getPickList($param)
    {
        if(empty($param))
        {
            return array('rsp'=>'fail', 'err_msg'=>'没有输入参数');
        }
        
        //组织参数
        $vop_param    = $this->_format_api_params('getPickList', 1, $param);
        
        if($param['pick_no'])
        {
            $title  = '获取拣货单号('. $param['pick_no'] .')信息';
            $primary_bn = $param['pick_no'];
        }
        else
        {
            $title        = '获取店铺('. $this->__channelObj->channel['name'] .')拣货单列表';
            $primary_bn   = 'getPickList';
        }
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->getPickList_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 拣货单回调
     */
    public function getPickList_callback($response, $callback_params=NULL)
    {
        $rsp = $response['rsp'];
        $total = 0;
        
        if($rsp == 'succ')
        {
            $data    = json_decode($response['data'], true);
            $data    = json_decode($data['msg'], true);
            $pick_list = $data['result']['picks'];
            
            $total = (int) $data['result']['total'];
            $pick_nos = array();
            
            //获取拣货单明细
            if($pick_list)
            {
                foreach ($pick_list as $key => $val)
                {
                    //组织拣货单参数
                    $pick_nos[$val['pick_no']]    = $val;
                }
            }
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res'], 'total'=>$total);
        if($pick_nos)
        {
            $result['po_list']    = $pick_nos;
        }
        
        return $result;
    }
    
    /**
     * 拣货单明细
     */
    public function getPickDetail($param)
    {
        if(empty($param))
        {
            return array('rsp'=>'fail', 'err_msg'=>'没有输入参数');
        }
        
        //组织参数
        $vop_param    = $this->_format_api_params('getPickDetail', 1, $param);
        
        $title      = '获取拣货单号('. $param['pick_no'] .')明细信息';
        $primary_bn = $param['pick_no'];
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn); 
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->getPickDetail_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 拣货单明细回调
     */
    public function getPickDetail_callback($response, $callback_params=NULL)
    {
        $rsp          = $response['rsp'];
        
        $result = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res']);
        if($rsp == 'succ'){
            $data    = json_decode($response['data'], true);
            $data    = json_decode($data['msg'], true);
            
            if ($data['returnCode'] == '0') {
                $result['pick_product_list'] = $data['result']['pick_product_list'];
                $result['total'] = $data['result']['total'];
            } else {
                $result['rsp'] = 'fail';
                $result['msg'] = $result['err_msg'] = $data['returnMessage'];
            }
        }
        
        return $result;
    }
    
    /**
     * 创建拣货单
     */
    public function createPick($param)
    {
        if(empty($param['po_no']))
        {
            return false;
        }
        
        //组织参数
        $vop_param    = $this->_format_api_params('createPick', 1, $param);
        
        $title      = '采购单号('. $param['po_no'] .')创建拣货单';
        $primary_bn = $param['po_no'];
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->createPick_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 创建拣货单回调
     */
    public function createPick_callback($response, $callback_params=NULL)
    {
        $rsp          = $response['rsp'];
        $pick_info    = array();
        
        if($rsp == 'succ')
        {
            $data    = json_decode($response['data'], true);
            $data    = json_decode($data['msg'], true);
            
            $pick_info    = $data['result'];
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res']);
        if($pick_info)
        {
            $result['pick_info']    = $pick_info;
        }
        
        return $result;
    }
    
    /**
     * 创建出仓单
     */
    public function createDelivery($param)
    {
        if(empty($param['po_no']) || empty($param['delivery_no']) || empty($param['warehouse'])) {
            $error_msg = '无效的请求出仓单参数!';
            return $this->error($error_msg);
        }

        if(empty($param['arrival_time']) || empty($param['carrier_name']) || empty($param['carrier_code'])){
            $error_msg = '无效的请求出仓单参数。';
            return $this->error($error_msg);
        }
        
        // check
        if(empty($param['delivery_warehouse'])){
            $error_msg = '供应商发货仓库必须传值!';
            return $this->error($error_msg);
        }
        
        // params
        $vop_param = array (
            'createPoDeliveryReq' => array (
                'po_nos'             => explode(',',$param['po_no']), // po单号列表
                'logistics_no'       => $param['logistics_no'] ?: $param['delivery_no'], // 运单号
                'warehouse'          => $param['warehouse'], // 送货仓库
                'delivery_time'      => $param['delivery_time'], // 送货时间，示例值：2026-08-01 10:00:00
                'arrival_time'       => $param['arrival_time'], // 要求到货时间(必须是当天或者明天)；
                'carrier_code'       => $param['carrier_code'], // 承运商编码
                'delivery_method'    => $param['delivery_method'],
                // 'store_sn'           => $param['store_sn'],
                // 'jit_type'           => $param['jit_type'],
                // 'is_air_embargo'     => $param['is_air_embargo'],
            ),
        );
        
        // 供应商发货仓库(如果是承运商=唯品会，则必填)
        $vop_param['createPoDeliveryReq']['delivery_warehouse'] = $param['delivery_warehouse'];
        
        // 是否航空禁运,0-不禁运，1-禁运，默认为0，对于承运商为唯品会时，必填
        $vop_param['createPoDeliveryReq']['is_air_embargo'] = ($param['is_air_embargo'] ? $param['is_air_embargo'] : '0');
        
        if (kernel::single('purchase_purchase_stockout')->is_vopcp($param['carrier_code'])) {
            $vop_param['createPoDeliveryReq']['arrival_time']       = '';
            $vop_param['createPoDeliveryReq']['delivery_method']    = '';
        }

        // 如果是预调拨件货单
        if (false !== strpos($param['pick_no'],'FPICK-')) {
            $vop_param['createPoDeliveryReq']['jit_type']    = 3;
        }

        //组织参数
        $vop_param    = $this->_format_api_params('createPoDeliveryV2', 0, $vop_param);
        
        $title        = '出库单号('. $param['delivery_no'] .')创建出库单';
        $primary_bn   = $param['delivery_no'];

        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);

        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->createDelivery_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 创建出仓单回调
     */
    public function createDelivery_callback($response, $callback_params=NULL)
    {
        $rsp          = $response['rsp'];
        $delivery     = array();
        
        if($rsp == 'succ')
        {
            $data    = json_decode($response['data'], true);
            $data    = json_decode($data['msg'], true);
            $data    = $data['result'];
            
            $delivery    = array(
                'delivery_id'     => $data['delivery_id'], 
                'storage_no'      => $data['storage_no'],
                'logistics_no'    => $data['logistics_no'],
                'delivery_method' => $data['delivery_method'],
                'arrival_time'    => $data['arrival_time'],
            );
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res']);
        if($delivery)
        {
            $result['delivery']    = $delivery;
        }
        
        return $result;
    }
    
    /**
     * 创建出仓单2.0
     */
    public function createMultiPoDelivery($param)
    {
        $chk_param    = $param['createMultiPoDeliveryRequest'];
        
        if(empty($chk_param['po_no']) || empty($chk_param['delivery_no']) || empty($chk_param['warehouse']))
        {
            return false;
        }
        if(empty($chk_param['arrival_time']) || empty($chk_param['carrier_code']))
        {
            return false;
        }
        
        //组织参数
        $vop_param    = $this->_format_api_params('createMultiPoDelivery', 0, $param);
        
        $title        = '出库单号('. $chk_param['delivery_no'] .')创建出库单';
        $primary_bn   = $chk_param['delivery_no'];
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->createMultiPoDelivery_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 创建出仓单回调2.0
     */
    public function createMultiPoDelivery_callback($response, $callback_params=NULL)
    {
        $rsp          = $response['rsp'];
        $delivery     = array();
        
        if($rsp == 'succ')
        {
            $data          = json_decode($response['data'], true);
            $data          = json_decode($data['msg'], true);
            $storage_no    = $data['result'];
            
            if($storage_no)
            {
                $delivery    = array('storage_no'=>$storage_no);
            }
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res']);
        if($delivery)
        {
            $result['delivery']    = $delivery;
        }
        
        return $result;
    }
    
    /**
     * 修改出仓单信息
     */
    public function editDelivery($param)
    {
        $stockout_no    = $param['stockout_no'];
        
        // unset
        unset($param['stockout_no']);
        
        // check
        if(empty($stockout_no)){
            $error_msg = '出库单号不能为空，请检查!';
            return $this->error($error_msg);
        }
        
        if(empty($param['storage_no']) || empty($param['warehouse'])){
            $error_msg = '入库单号、送货仓库，不能为空!';
            return $this->error($error_msg);
        }
        
        if(empty($param['delivery_warehouse'])){
            $error_msg = '供应商发货仓库不能为空，请检查!';
            return $this->error($error_msg);
        }
        
        // params
        $vop_param = array (
            'editDeliveryReq' => array (
                'storage_no'         => $param['storage_no'], // 入库单号
                'logistics_no'       => $param['delivery_no'], // 运单号
                'delivery_warehouse' => $param['delivery_warehouse'], // 供应商发货仓库(如果是承运商=唯品会，则必填)
                'warehouse'          => $param['warehouse'], // 送货仓库 (如果是承运商=唯品会，则必填)
                'delivery_time'      => $param['delivery_time'], // 送货时间
                // 'arrival_time'       => $param[''],
                'carrier_code'       => $param['carrier_code'], // 承运商编码
                // 'delivery_method'    => $param[''],
                // 'store_sn'           => $param[''],
                // 'is_air_embargo'     => $param[''],
            ),
        );
        
        // 是否航空禁运,0-不禁运，1-禁运，默认为0，对于承运商为唯品会时，必填
        $vop_param['editDeliveryReq']['is_air_embargo']     = ($param['is_air_embargo'] ? $param['is_air_embargo'] : '0');
        
        //组织参数
        $vop_param    = $this->_format_api_params('editPoDeliveryV2', 0, $vop_param);
        
        $title        = '出库单号('. $stockout_no .')修改出仓单信息';
        $primary_bn   = $stockout_no;
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->editDelivery_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 修改出仓单回调
     */
    public function editDelivery_callback($response, $callback_params=NULL)
    {
        $rsp          = $response['rsp'];
        $delivery     = array();
        
        if($rsp == 'succ')
        {
            $data    = json_decode($response['data'], true);
            $data    = json_decode($data['msg'], true);
            
            //失败原因
            if($data['returnCode'] != '0' && $data['returnMessage'])
            {
                $response['rsp']        = 'fail';
                $response['err_msg']    = json_encode(array('returnMessage'=>$data['returnMessage']));
            }
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res'], 'err_msg'=>$response['err_msg']);
        
        return $result;
    }
    
    /**
     * 将出仓明细信息导入到出仓单中（目前该接口明细信息最大导入量在500条SKU信息）
     */
    public function importDeliveryDetail($param)
    {
        $stockout_no    = $param['stockout_no'];
        unset($param['stockout_no']);
        
        if(empty($stockout_no) || empty($param['po_no']) || empty($param['storage_no']))
        {
            return false;
        }
        
        //组织参数
        $vop_param    = $this->_format_api_params('importMultiPoDeliveryDetail', 1, $param);
        
        $title        = '出库单号('. $stockout_no .')导入出仓明细信息';
        $primary_bn   = $stockout_no;
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->importDeliveryDetail_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 导入出仓明细信息回调
     */
    public function importDeliveryDetail_callback($response, $callback_params=NULL)
    {
        $rsp          = $response['rsp'];
        $delivery     = array();
        
        if($rsp == 'succ')
        {
            $data    = json_decode($response['data'], true);
            $data    = json_decode($data['msg'], true);
            
            //失败原因
            if($data['returnCode'] != '0' && $data['returnMessage'])
            {
                $response['rsp']        = 'fail';
                $response['err_msg']    = json_encode(array('returnMessage'=>$data['returnMessage']));
            }
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res'], 'err_msg'=>$response['err_msg']);
        
        return $result;
    }
    
    /**
     * 确认出仓单
     */
    public function confirmDelivery($param)
    {
        $stockout_no    = $param['stockout_no'];
        unset($param['stockout_no']);
        
        if(empty($stockout_no) || empty($param['storage_no']))
        {
            return false;
        }
        
        //组织参数
        $vop_param    = $this->_format_api_params('confirmDelivery', 1, $param);
        
        $title        = '出库单号('. $stockout_no .')确认出仓单';
        $primary_bn   = $stockout_no;
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->confirmDelivery_callback($rsp);
        }
        
        return $rsp;
    }
    
    /**
     * 确认出仓单回调
     */
    public function confirmDelivery_callback($response, $callback_params=NULL)
    {
        $rsp          = $response['rsp'];
        $delivery     = array();
        
        if($rsp == 'succ')
        {
            $data    = json_decode($response['data'], true);
            $data    = json_decode($data['msg'], true);
            
            //失败原因
            if($data['returnCode'] != '0' && $data['returnMessage'])
            {
                $response['rsp']        = 'fail';
                $response['err_msg']    = json_encode(array('returnMessage'=>$data['returnMessage']));
            }
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res'], 'err_msg'=>$response['err_msg']);
        
        return $result;
    }
    
    /**
     * 同步承运商
     */
    function getCarrierList($param)
    {
        $chk_param    = $param['carrierRequest'];
        $shop_id      = $this->__channelObj->channel['shop_id'];
        
        //组织参数
        $vop_param    = $this->_format_api_params('getCarrierList', 0, $param);
        
        $title        = '同步承运商';
        $primary_bn   = 'carrier';
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($rsp['rsp'] == 'succ')
        {
            $rsp    = $this->getCarrierList_callback($rsp, $shop_id);
        }
        
        return $rsp;
    }
    
    /**
     * 同步承运商回调
     */
    public function getCarrierList_callback($response, $shop_id)
    {
        $rsp          = $response['rsp'];
        $delivery     = array();
        
        if($rsp == 'succ' && isset($response['data'])){
            $data = array();
            if($response['data'] && is_string($response['data'])){
                $data = json_decode($response['data'], true);
            }elseif($response['data']){
                $data = $response['data'];
            }
            
            if(isset($data['msg']) && $data['msg']){
                if(is_string($data['msg'])){
                    $data = json_decode($data['msg'], true);
                }else{
                    $data = $data['msg'];
                }
            }
            
            $dataList = isset($data['result']['carriers']) ? $data['result']['carriers'] : array();
            if($dataList) {
                $stockLib        = kernel::single('purchase_purchase_stockout');
                
                foreach ($dataList as $key => $val)
                {
                    $save_data    = array(
                            'tms_carrier_id'=>$val['tms_carrier_id'],
                            'carrier_code'=>$val['carrier_code'],
                            'carrier_name'=>$val['carrier_name'],
                            'carrier_shortname'=>$val['carrier_shortname'],
                            'carrier_isvalid'=>$val['carrier_isvalid'],
                            'shop_id'=>$shop_id,
                    );
                    
                    $stockLib->saveCarrier($save_data);
                }
            }
        }
        
        $result    = array('rsp'=>$response['rsp'], 'msg_id'=>$response['msg_id'], 'res'=>$response['res']);
        
        return $result;
    }

    /**
     * 获取ReturnInfo
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getReturnInfo($sdf)
    {
        $shop_id      = $this->__channelObj->channel['shop_id'];
        $param = [
            'warehouse' => $sdf['warehouse'],
            'st_create_time' => $sdf['start_date'],
            'ed_create_time' => $sdf['end_date'],
            'page' => $sdf['page_no'],
            'limit' => $sdf['page_size'],
        ];
        //组织参数
        $vop_param    = $this->_format_api_params('getReturnInfo', 1, $param);
        $vop_param['vop_type'] = 'vreturn';
        
        $title        = '同步退供单';
        $primary_bn   = 'vopreturn';
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);

        //数据处理
        //数据处理
        if($rsp['data'])
        {
            $data    = json_decode($rsp['data'], 1);
            if ($data['msg']) {
                $data['msg']  = json_decode($data['msg'],1);
                $rsp['data']  = $data['msg']['result']['returnInfos'];
                $rsp['total'] = $data['msg']['result']['total'];
            }
        }
        
        return $rsp;
    }

    /**
     * 获取ReturnDetail
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getReturnDetail($sdf)
    {
        $shop_id      = $this->__channelObj->channel['shop_id'];
        $param = [
            'warehouse' => $sdf['warehouse'],
            'return_sn' => $sdf['return_sn'],
        ];
        //组织参数
        $vop_param    = $this->_format_api_params('getReturnDetail', 1, $param);
        $vop_param['vop_type'] = 'vreturn';
        
        $title        = '同步退供单详情';
        $primary_bn   = $sdf['return_sn'];
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        


        if($rsp['data'])
        {
            $data    = json_decode($rsp['data'], 1);
            if ($data['msg']) {
                $data['msg']  = json_decode($data['msg'],1);
                $rsp['data']  = $data['msg']['result']['returnDeliveryInfos'][0];
                $rsp['total'] = $data['msg']['result']['total'];
            }
        }
        
        return $rsp;
    }

    /**
     * returnConfirmBySn
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function returnConfirmBySn($sdf)
    {
        $shop_id      = $this->__channelObj->channel['shop_id'];
        $param = [
            'return_sn_list' => json_encode([$sdf['return_sn']]),
        ];
        //组织参数
        $vop_param    = $this->_format_api_params('returnConfirmBySn', 0, $param);
        
        $title        = '退供单供应商确认';
        $primary_bn   = $sdf['return_sn'];
        
        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        
        return $rsp;
    }

    /**
     * 获取SkuPriceInfo
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getSkuPriceInfo($sdf)
    {
        $shop_id      = $this->__channelObj->channel['shop_id'];
        $param = [
            'request' => [
                'po_no' => $sdf['po_no'],
                'barcodes' => $sdf['barcodes'],
            ]
        ];

        // 组织参数
        $vop_param    = $this->_format_api_params('getSkuPriceInfo', 0, $param);
        
        $title        = '查询JIT供货价信息';

        $primary_bn   = $sdf['po_no'];

        $rsp    = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $vop_param, array(), $title, 10, $primary_bn);
        if ($rsp['rsp'] == 'succ'){
            $data = @json_decode($rsp['data'], 1);
            $msg = @json_decode($data['msg'], 1);
            
            $rsp['rsp'] = $msg['returnCode'] == '0' ? 'succ' : 'fail';

            $rsp['msg'] = $rsp['err_msg'] =  $msg['returnMessage'];

            $rsp['data'] = $msg['result'];
        }

        
        return $rsp;
    }


    /**
     * 退供差明细列表
     * 
     * 
     * @param array $sdf
     * @return array
     * */
    public function getReturnDiffInterDetail($params)
    {
        $title = sprintf('[%s]退供明细列表', $this->__channelObj->channel['name']);



        $result = $this->__caller->call(SHOP_RETURNORDER_DIFF_DETAIL_GET, $params, array(), $title, 10);

        if ($result['rsp'] == 'succ' && $result['data']){
            $data = @json_decode($result['data'], true);

            if ($data && $data['msg']) {
                $msg = @json_decode($data['msg'], true);

                if ($msg && $msg['returnCode'] != 0) {
                    $result['rsp'] = 'fail';
                    $result['msg'] = $result['err_msg'] = $msg['returnMessage'];
                    return $result;
                }


                $result['data'] = $msg['result'];
            }
        }

        return $result;
    }


        /**
     * 获取ReturnDiffInterList
     * @param mixed $params 参数
     * @return mixed 返回结果
     */
    public function getReturnDiffInterList($params)
    {
        $title = sprintf('[%s]退供差主单列表', $this->__channelObj->channel['name']);

        $result =  $this->__caller->call(SHOP_RETURNORDER_DIFF_LIST_GET, $params, array(), $title, 10);

        if ($result['rsp'] == 'succ' && $result['data']){
            $data = @json_decode($result['data'], true);

            if ($data && $data['msg']) {
                $msg = @json_decode($data['msg'], true);

                if ($msg && $msg['returnCode'] != 0) {
                    $result['rsp'] = 'fail';
                    $result['msg'] = $result['err_msg'] = $msg['returnMessage'];
                    return $result;
                }


                $result['data'] = $msg['result'];
            }
        }
        return $result;
    }
    
    /**
     * JIT订单明细查询
     * API文档：https://vop.vip.com/home#/api/method/detail/com.vip.vis.order.jit.service.order.JitOrderVopService-1.0.0/getJitOrderDetail
     * 
     * @param $param array
     * @return array
     */
    public function getJitorderdetail($param)
    {
        if(empty($param['request']['po']) || empty($param['request']['pick_no'])){
            return array('rsp'=>'fail', 'error_msg'=>'po单号、拣货单号不能为空');
        }
        
        $title = '获取JIT订单明细查询';
        $primary_bn = $param['request']['pick_no'];
        
        //data
        $requestData = $param;
        
        //params
        $requestParams = array(
            'vop_type' => 'JitOrder',
            'vop_service' => 'com.vip.vis.order.jit.service.order.JitOrderVopService', //服务名
            'vop_method' => 'getJitOrderDetail', //方法名
            'is_multiple_params' => 0, //是否多个参数：默认1,必须填写0
            'data' => json_encode($requestData),
        );
        
        //request
        $result = $this->__caller->call(SHOP_COMMONS_VOP_JIT, $requestParams, array(), $title, 10, $primary_bn);
        
        //数据处理
        if($result['rsp'] == 'succ'){
            $result = $this->getJitorderdetail_callback($result);
        }
        
        return $result;
    }
    
    /**
     * JIT订单明细查询--数据格式化
     * 
     * @param $response
     * @param $callback_params
     * @return array
     */
    public function getJitorderdetail_callback($response, $callback_params=NULL)
    {
        $total = 0;
        
        //format
        $order_detail_list = array();
        if($response['data']){
            if($response['data'] && is_array($response['data'])){
                $data = $response['data'];
            }else{
                $data = json_decode($response['data'], true);
            }
            
            //content
            if($data['msg'] && is_array($data['msg'])){
                $data = $data['msg'];
            }else{
                $data = json_decode($data['msg'], true);
            }
            
            $total = (isset($data['result']['total']) ? $data['result']['total'] : 0);
            $total = intval($total);
            
            //JIT订单明细列表
            if(isset($data['result']['order_detail_list'])){
                $order_detail_list = $data['result']['order_detail_list'];
            }
        }
        
        //result
        $result = array(
            'rsp' => $response['rsp'],
            'res' => $response['res'],
            'msg_id' => $response['msg_id'],
            'total' => $total,
            'dataList' => $order_detail_list,
        );
        
        return $result;
    }
}
