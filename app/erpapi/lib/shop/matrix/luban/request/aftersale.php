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
 * 抖音店铺退货业务请求Lib类
 */
class erpapi_shop_matrix_luban_request_aftersale extends erpapi_shop_request_aftersale
{
    /**
     * 售后退货、换货接口名
     * 
     * @param string $status
     * @param array $returnInfo
     */
    protected function __afterSaleApi($status, $returnInfo=null)
    {
        $api_method = '';
        
        //获取店铺配置(售后状态是否同步给平台)
        if($this->__channelObj->channel['config']){
            //禁止售后状态同步给平台
            if($this->__channelObj->channel['config']['return_sync_platform'] == 'forbid_sync'){
                return $api_method;
            }
        }
        
        //opinion
        switch($status)
        {
            case '3':
                if($returnInfo['return_type'] == 'change'){
                    $api_method = SHOP_AFTERSALE_EXCHANGE_AGREE; //同意换货
                }else{
                    $api_method = SHOP_AGREE_RETURN_GOOD; //同意退货
                }
                break;
            case '4':
                /***
                 * [换货]退款完成不用推送抖音平台
                 * @todo：只有等换出订单(换货产生的C开头新订单)发货完成后才推送给抖音平台; 
                 * 
                if($returnInfo['return_type'] == 'change'){
                    $api_method = SHOP_EXCHANGE_RETURNGOODS_AGREE; //[换货]卖家确认收货
                }
                ***/
                
                /***
                 * [退货]确认收货接口
                if($returnInfo['return_type'] == 'return'){
                    $api_method = SHOP_EXCHANGE_RETURNGOODS_AGREE; //[退货]商家收到退货,不需要走此接口,直接添加退款单
                }
                ***/
            break;
            case '5':
                if($returnInfo['kinds'] == 'change'){
                    $api_method = SHOP_AFTERSALE_EXCHANGE_REFUSE; //拒绝换货
                }elseif($returnInfo['kinds'] == 'reship'){
                    $api_method = SHOP_REFUSE_RETURN_GOOD;//拒绝退货退款
                }elseif($returnInfo['kinds'] == 'refund'){
                    $api_method = SHOP_REFUSE_REFUND;//拒绝退款
                }
                break;
        }
        
        return $api_method;
    }
    
    /**
     * 格式化售后请求数据
     * 
     * @param array $aftersale
     * @param string $status
     * @return array
     */
    protected function __formatAfterSaleParams($aftersale, $status)
    {
        $reshipObj = app::get('ome')->model('reship');
        $addressObj = app::get('ome')->model('return_address');
        
        $lubanLib = kernel::single('ome_reship_luban');
        
        $shop_id = $this->__channelObj->channel['shop_id'];
        if(empty($shop_id)){
            $shop_id = $shop_id ? $shop_id : $aftersale['shop_id'];
        }
        
        //获取退货单信息
        $reshipInfo = $reshipObj->dump(array('return_id'=>$aftersale['return_id']), '*');
        $aftersale['reship_id'] = intval($reshipInfo['reship_id']);
        $aftersale['return_logi_name'] = $reshipInfo['return_logi_name'];
        $aftersale['return_logi_no'] = $reshipInfo['return_logi_no'];
        $aftersale['branch_id'] = $reshipInfo['branch_id'];
        
        //获取默认平台退货地址ID
        $addressInfo = array();
        if($aftersale['address_id']){
            $addressInfo = $addressObj->dump(array('contact_id'=>$aftersale['address_id']), '*');
        }
        
        $aftersale['receiver_address_id'] = ($addressInfo['contact_id'] ? $addressInfo['contact_id'] : 0);
        $aftersale['addressInfo'] = $addressInfo;
        
        //获取子订单号
        $returnLubanObj = app::get('ome')->model('return_product_luban');
        $return_luban = $returnLubanObj->dump(array('shop_id'=>$shop_id, 'return_id'=>$aftersale['return_id']), '*');
        $aftersale['oid'] = $return_luban['oid'];
        
        //获取京东云交易返回的退货回寄地址
        $jdAddressInfo = array();
        $tempInfo = $addressObj->dump(array('reship_id'=>$aftersale['reship_id']), '*');
        if($tempInfo){
            $jdAddressInfo = array(
                    'province_name' => $tempInfo['province'], //省
                    'city_name' => $tempInfo['city'], //市
                    'town_name' => ($tempInfo['country'] ? $tempInfo['country'] : ''), //区
                    'detail' => $tempInfo['addr'], //地址详情
                    'user_name' => $tempInfo['contact_name'], //收件人
                    'mobile' => ($tempInfo['mobile_phone'] ? $tempInfo['mobile_phone'] : $tempInfo['phone']), //联系电话
            );
            
            //街道名称
            if($tempInfo['street']){
                $jdAddressInfo['street_name'] = $tempInfo['street'];
            }
            
            //[重置系统]退货寄件地址(此字段抖音不用,只为OMS同步日志显示)
            $aftersale['addressInfo'] = array(
                'contact_name' => $jdAddressInfo['user_name'],
                'mobile_phone' => $jdAddressInfo['mobile'],
                'province' => $jdAddressInfo['province_name'],
                'city' => $jdAddressInfo['city_name'],
                'country' => $jdAddressInfo['town_name'],
                'addr' => $jdAddressInfo['detail'],
            );
            
            //获取商家退货地址库ID(字段名：receiver_address_id)
            //@todo：抖音after_sale_address_detail字段已经下线；
            if(empty($aftersale['receiver_address_id'])){
                $jdAddressInfo = $lubanLib->matchingReturnContactId($tempInfo);
                
                $aftersale['receiver_address_id'] = intval($jdAddressInfo['contact_id']);
            }
        }elseif(empty($aftersale['receiver_address_id'])){
            //使用默认退货地址
            $addressInfo = $addressObj->dump(array('shop_id'=>$shop_id, 'cancel_def'=>'true'), '*');
            
            $aftersale['receiver_address_id'] = ($addressInfo['contact_id'] ? $addressInfo['contact_id'] : 0);
        }
        
        //unset
        unset($tempInfo, $jdAddressInfo);
        
        //business
        if($aftersale['kinds'] == 'change'){
            $params = $this->_formatExchangeParams($aftersale, $status);
        }else{
            $params = $this->_formatReturnParams($aftersale, $status);
        }
        
        return $params;
    }
    
    /**
     * [格式化]抖音退货数据
     * 
     * @param array $aftersale
     * @param string $status
     * @return array
     */
    public function _formatReturnParams($aftersale, $status)
    {
        //params
        $params = array(
            'aftersale_id' => $aftersale['return_bn'], //退货单号
            'oid' => $aftersale['oid'], //子订单号(抖音是按子订单号回传)
            'parse' => 'first', //同意退货申请(一次审核)
            'evidence_type' => '1', //凭证类型(1:图片，2:视频，3:音频，4:文字)
        );
        
        //平台退货地址ID
        if(isset($aftersale['receiver_address_id'])){
            $params['receiver_address_id'] = $aftersale['receiver_address_id'];
        }
        
        //京东云交易返回的退货回寄地址
        if($aftersale['after_sale_address_detail']){
            $params['after_sale_address_detail'] = $aftersale['after_sale_address_detail'];
        }
        
        //business
        switch ($status)
        {
            case '3':
                //获取退回寄件地址
                $return_address = $aftersale['addressInfo'];
                
                //params
                $params['sms_id'] = '0'; //是否使用模版短信发送短信：1：是 0：否
                $params['memo'] = ($aftersale['memo'] ? $aftersale['memo'] : $aftersale['content']); //退货原因
                $params['url'] = ''; //图片地址
                $params['receiver_name'] = $return_address['contact_name']; //退回商品的收货人姓名
                $params['receiver_phone'] = ($return_address['mobile_phone'] ? $return_address['mobile_phone'] : $return_address['phone']); //退回商品的收货手机号
                $params['receiver_province'] = $return_address['province']; //退货地址的省（直辖市也必须填，比如北京市）
                $params['receiver_city'] = $return_address['city']; //退货地址的市
                $params['receiver_district'] = $return_address['country']; //退货地址的区
                $params['receiver_address'] = $return_address['addr']; //退货详细地址
                
            break;
            //case '4':
            //    商家确认收货
            case '5':
                //拒绝退货参数
                $params['sms_id'] = '0'; //是否使用模版短信发送短信：1：是 0：否
                $params['comment'] = '5'; //抖音平台枚举型(5:商品退回后才能退款)
                $params['reason'] = ($aftersale['memo'] ? $aftersale['memo'] : $aftersale['content']); //拒绝原因
                $params['desc'] = '拒绝退货'; //凭证描述
                
                //自动拒绝平台售后申请单
                if($params['reason'] == '商品不支持售后'){
                    $params['desc'] = '请与客服协商确认';
                    $params['evidence_type'] = '4'; //凭证类型(1:图片，2:视频，3:音频，4:文字)
                }
                
                //获取拒绝退货图片(抖单平台需要拒绝图片)
                $file_url = '';
                if($aftersale['attachment']){
                    if(is_numeric($aftersale['attachment'])){
                        $fileLib = kernel::single('base_storager');
                        $file_url = $fileLib->getUrl($aftersale['attachment']);
                    }else{
                        $tempData = explode('|', $aftersale['attachment']);
                        $file_url = $tempData[0];
                    }
                }else{
                    $file_url = is_array($params['reason']) ?  $params['reason']['refuse_proof'] : '';
                }
                
                $refuse_reason = $this->getRefuseReason();
                $refuse_reason = ome_func::cast_index_to_key($refuse_reason,'reason_id');
                
                $arr_params = is_array($params['reason']) ? $params['reason'] : array();
                
                //兼容
                if(isset($arr_params['reject_reason_code']) && $arr_params['reject_reason_code']){
                    $arr_params['reject_reason_code'] = trim($arr_params['reject_reason_code']);
                }else{
                    $arr_params['reject_reason_code'] = '1';
                }
                
                //reason
                if(isset($refuse_reason[$arr_params['reject_reason_code']])){
                    $params['reason'] = $refuse_reason[$arr_params['reject_reason_code']]['reason_text'];
                }else{
                    $params['reason'] = (is_string($params['reason']) ? $params['reason'] : '');
                }
                
                $params['reject_reason_code'] = $arr_params['reject_reason_code'];
                $params['remark'] = $arr_params['remark'];
                $params['url'] = $file_url;
                $params['parse'] = empty($arr_params['parse']) ? 'first' : $arr_params['parse'];
                $params['version'] = '2.0';
                //[兼容]先同意退货再进行拒绝
                if(in_array($aftersale['status'], array('2','3','4'))){
                    $parseVal = $this->_getParseConfirm($aftersale);
                    if($parseVal){
                        //[二次审核标识]退款审单
                        $params['parse'] = $parseVal;
                    }
                }
            break;
        }
        
        return $params;
    }
    
    /**
     * [格式化]抖音换货数据
     *
     * @param array $aftersale
     * @param string $status
     * @return array
     */
    public function _formatExchangeParams($aftersale, $status)
    {
        $params = array(
            'aftersale_id' => $aftersale['return_bn'], //退货单号
            'oid' => $aftersale['oid'], //子订单号
            'parse' => 'first', //同意退货申请(一次审核)
            'evidence_type' => '1', //凭证类型(1:图片，2:视频，3:音频，4:文字)
            'version' => '2.0', //矩阵用此字段判断调用最新接口
        );
        
        //平台退货地址ID
        if(isset($aftersale['receiver_address_id'])){
            $params['receiver_address_id'] = $aftersale['receiver_address_id'];
        }
        
        //京东云交易返回的退货回寄地址
        if($aftersale['after_sale_address_detail']){
            $params['after_sale_address_detail'] = $aftersale['after_sale_address_detail'];
        }
        
        switch ($status)
        {
            case '3':
                //同意换货
                $params['is_reject'] = 'false'; //处理方式true:拒绝用户换货申请,false:同意用户换货申请
                $params['sms_id'] = '0'; //是否使用模版短信发送短信：1：是 0：否
                $params['remark'] = ($aftersale['memo'] ? $aftersale['memo'] : $aftersale['content']);
            break;
            case '4':
                //[换货]卖家确认收货
                $params['is_reject'] = 'false'; //处理方式true:拒绝用户换货申请,false:同意用户换货申请
                $params['sms_id'] = '0'; //是否使用模版短信发送短信：1：是 0：否
                $params['action'] = 'exchange_agree'; //必填,换货动作(refund_agree：同意换货转退款,exchange_agree：同意换货并发货)
                $params['logistics_no'] = ''; //必填,需要上传物流单号
                $params['company_code'] = ''; //必填,上传物流公司编号
                
                //[二次审核标识]退款审单
                $params['parse'] = 'second';
                
                //查询物流公司编码
                if($aftersale['return_logi_name']){
                    $reshipObj = app::get('ome')->model('reship');
                    $sql = "SELECT corp_id,type FROM sdb_ome_dly_corp WHERE name='". $aftersale['return_logi_name'] ."' OR type='". $aftersale['return_logi_name'] ."'";
                    $corpInfo = $reshipObj->db->selectrow($sql);
                    
                    $params['company_code'] = $corpInfo['type'];
                }
                
                if($aftersale['return_logi_no']){
                    $params['logistics_no'] = $aftersale['return_logi_no'];
                }
                
            break;
            case '5':
                //拒绝退货参数
                $params['sms_id'] = '0'; //是否使用模版短信发送短信：1：是 0：否
                $params['comment'] = '5'; //抖音平台枚举型(5:商品退回后才能退款)
                $params['reason'] = ($aftersale['memo'] ? $aftersale['memo'] : $aftersale['content']); //拒绝原因
                $params['desc'] = '拒绝退货'; //凭证描述

                //自动拒绝平台售后申请单
                if($params['reason'] == '商品不支持售后'){
                    $params['desc'] = '请与客服协商确认';
                    $params['evidence_type'] = '4'; //凭证类型(1:图片，2:视频，3:音频，4:文字)
                }

                //获取拒绝退货图片(抖单平台需要拒绝图片)
                $file_url = '';
                if($aftersale['attachment']){
                    if(is_numeric($aftersale['attachment'])){
                        $fileLib = kernel::single('base_storager');
                        $file_url = $fileLib->getUrl($aftersale['attachment']);
                    }else{
                        $tempData = explode('|', $aftersale['attachment']);
                        $file_url = $tempData[0];
                    }
                }else{
                    $file_url = is_array($params['reason']) ? $params['reason']['refuse_proof'] : '';
                }
                
                $refuse_reason = $this->getRefuseReason();
                $refuse_reason = ome_func::cast_index_to_key($refuse_reason,'reason_id');
                
                // reason
                $arr_params = is_array($params['reason']) ? $params['reason'] : array();
                
                //兼容
                if(isset($arr_params['reject_reason_code']) && $arr_params['reject_reason_code']){
                    $arr_params['reject_reason_code'] = trim($arr_params['reject_reason_code']);
                }else{
                    $arr_params['reject_reason_code'] = '1';
                }
                
                if(isset($refuse_reason[$arr_params['reject_reason_code']]['reason_text'])){
                    $params['reason'] = $refuse_reason[$arr_params['reject_reason_code']]['reason_text'];
                }else{
                    $params['reason'] = (is_string($params['reason']) ? $params['reason'] : '');
                }
                
                $params['reject_reason_code'] = $arr_params['reject_reason_code'];
                $params['remark'] = $arr_params['remark'];
                $params['url'] = $file_url;
                $params['parse'] = empty($arr_params['parse']) ? 'first' : $arr_params['parse'];
                $params['version'] = '2.0';
                
                //[兼容]先同意退货再进行拒绝
                if(in_array($aftersale['status'], array('2','3','4'))){
                    $parseVal = $this->_getParseConfirm($aftersale);
                    if($parseVal){
                        //[二次审核标识]退款审单
                        $params['parse'] = $parseVal;
                    }
                }
            break;
        }
        
        return $params;
    }
    
    //售后原因
    public function getReturnResaon($params)
    {
        $problemObj = app::get('ome')->model('return_product_problem');
        
        $node_type = ($this->__channelObj->wms['node_type'] ? $this->__channelObj->wms['node_type'] : 'luban');
        
        //返回json售后原因
        $str = '[
            {
             "applyReasonId": 5,
             "applyReasonName": "收到商品少件 / 错件 / 空包裹"
            },
            {
             "applyReasonId": 6,
             "applyReasonName": "不喜欢 / 效果不好"
            },
            {
             "applyReasonId": 8,
             "applyReasonName": "功能故障"
            },
            {
             "applyReasonId": 10,
             "applyReasonName": "商品材质 / 品牌 / 外观等描述不符"
            },
            {
             "applyReasonId": 11,
             "applyReasonName": "生产日期 / 保质期 / 规格等描述不符"
            },
            {
             "applyReasonId": 15,
             "applyReasonName": "其他"
            },
            {
             "applyReasonId": 16,
             "applyReasonName": "大小／尺寸／重量与商品描述不符"
            },
            {
             "applyReasonId": 18,
             "applyReasonName": "品种／规格／成分等描述不符"
            },
            {
             "applyReasonId": 20,
             "applyReasonName": "少件／漏发"
            },
            {
             "applyReasonId": 22,
             "applyReasonName": "商家发错货"
            },
            {
             "applyReasonId": 25,
             "applyReasonName": "品种／产品／规格／成分等描述不符"
            },
            {
             "applyReasonId": 28,
             "applyReasonName": "规格等描述不符"
            },
            {
             "applyReasonId": 31,
             "applyReasonName": "做工粗糙 / 有瑕疵 / 有污渍"
            }
        ]';
        
        //list
        $reasonList = json_decode($str, true);
        if(empty($reasonList)){
            $msgcode = '';
            $error_msg = '没有获取到售后原因';
            return $this->error($error_msg, $msgcode);
        }
        
        //已有WMS售后原因
        $problemList = array();
        $tempList = $problemObj->getList('problem_id,problem_name,reason_id', array('problem_type'=>'shop', 'platform_type'=>$node_type));
        if($tempList){
            foreach ($tempList as $key => $val)
            {
                $reason_id = $val['reason_id'];
                $problemList[$reason_id] = $val;
            }
        }
        
        //save
        foreach ($reasonList as $key => $val)
        {
            $reason_id = trim($val['applyReasonId']);
            $problem_id = $problemList[$reason_id]['problem_id'];
            
            //是否默认
            $is_default = 'false';
            if($reason_id=='15' || $val['applyReasonName']=='其他'){
                $is_default = 'true';
            }
            
            //sdf
            $sdf = array(
                    'problem_name' => trim($val['applyReasonName']),
                    'reason_id' => $reason_id,
                    'problem_type' => 'shop',
                    'platform_type' => $node_type,
                    'last_sync_time' => time(),
                    'defaulted' => $is_default, //是否默认
            );
            
            if($problem_id){
                $sdf['problem_id'] = $problem_id;
            }else{
                $sdf['createtime'] = time();
            }
            
            $problemObj->save($sdf);
        }
        
        return $this->succ('同步售后原因成功');
    }
    
    /**
     * [换货]卖家确认收货
     */
    public function consignGoods($data)
    {
        $title = '店铺('.$this->__channelObj->channel['name'].')换货订单卖家发货,(售后单号:'.$data['dispute_id'].')';
        $shop_id = $this->__channelObj->channel['shop_id'];
        
        //获取子订单号
        $returnLubanObj = app::get('ome')->model('return_product_luban');
        $return_luban = $returnLubanObj->dump(array('shop_id'=>$shop_id, 'return_id'=>$data['return_id']), '*');
        
        //获取默认平台退货地址ID
        $addressObj = app::get('ome')->model('return_address');
        if($data['address_id']){
            $addressInfo = $addressObj->dump(array('contact_id'=>$data['address_id']), '*');
        }
        
        /***
         * @todo：千万不能读取默认退货地址;
         * 
        if(empty($addressInfo)){
            $addressInfo = $addressObj->dump(array('shop_id'=>$shop_id, 'cancel_def'=>'true'), '*');
        }
        ***/
        
        $receiver_address_id = ($addressInfo['contact_id'] ? $addressInfo['contact_id'] : 0);
        
        //params
        $params = array(
                'aftersale_id' => $data['dispute_id'], //退货单号
                'oid' => $return_luban['oid'], //子订单号
                'receiver_address_id' => $receiver_address_id, //平台退货地址ID
                'parse' => 'second', // 同意换货(二次审核)
                'version' => '2.0', //版本号
        );
        
        //[换货]卖家确认收货
        $params['is_reject'] = 'false'; //处理方式true:拒绝用户换货申请,false:同意用户换货申请
        $params['sms_id'] = '0'; //是否使用模版短信发送短信：1：是 0：否
        $params['action'] = 'exchange_agree'; //必填,换货动作(refund_agree：同意换货转退款,exchange_agree：同意换货并发货)
        $params['logistics_no'] = $data['logistics_no']; //必填,需要上传物流单号
        $params['company_code'] = $data['corp_type']; //必填,上传物流公司编号
        $params['logistics_company_name'] = $data['logistics_company_name']; //物流公司名称
        
        //request
        $result = $this->__caller->call(SHOP_EXCHANGE_RETURNGOODS_AGREE, $params, array(), $title, 10, $data['order_bn']);
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }elseif(isset($result['res']) && $result['res']){
            $rs['msg'] = $result['res'];
        }
        $rs['rsp'] = $result['rsp'];
        
        $rs['data'] = $result['data'] ? $result['data'] : array();
        
        //发货记录
        $log_id = uniqid($_SERVER['HOSTNAME']);
        $rsp = ($rs['rsp'] == 'success' ? 'succ' : $rs['rsp']);
        $status = ($rsp == 'succ') ? 'succ' : 'fail';
        $log = array(
                'shopId'           => $this->__channelObj->channel['shop_id'],
                'ownerId'          => '16777215',
                'orderBn'          => $data['order_bn'],
                'deliveryCode'     => $params['logistics_no'],
                'deliveryCropCode' => $params['company_code'],
                'deliveryCropName' => $params['logistics_company_name'],
                'receiveTime'      => time(),
                'status'           => $status,
                'updateTime'       => time(),
                'message'          => $rs['msg'] ? $rs['msg'] : '成功',
                'log_id'           => $log_id,
        );
        
        $shipmentLogModel = app::get('ome')->model('shipment_log');
        $shipmentLogModel->insert($log);
        if ($data['order_id']){
            $orderModel = app::get('ome')->model('orders');
            
            $updateOrderData = array(
                    'sync' => $status,
                    'up_time' => time(),
            );
            $orderModel->update($updateOrderData,array('order_id'=>$data['order_id'], 'sync|noequal'=>'succ'));
        }
        
        return $rs;
    }
    
    /**
     * 同步售后单备注内容
     */
    public function syncReturnRemark($reshipInfo)
    {
        $operLogObj = app::get('ome')->model('operation_log');
        
        $original_bn = $reshipInfo['reship_bn'];
        $title = '店铺('.$this->__channelObj->channel['name'].')同步售后单备注内容(售后单号:'. $original_bn .')';
        
        $shop_id = $this->__channelObj->channel['shop_id'];
        if(empty($shop_id)){
            $shop_id = $shop_id ? $shop_id : $reshipInfo['shop_id'];
        }
        
        //params
        $params = array(
                'aftersale_id' => $reshipInfo['return_bn'], //退货单号
                'remark' => substr($reshipInfo['remark'], 0, 280), //京东审核意见
                'version' => '2.0', //版本号
        );
        
        //抖音订单号
        if($reshipInfo['order_id']){
            $orderObj = app::get('ome')->model('orders');
            $orderInfo = $orderObj->dump(array('order_id'=>$reshipInfo['order_id']), 'order_bn');
            $params['tid'] = str_replace('A', '', $orderInfo['order_bn']); //抖音订单号(去除A字母)
            
            $original_bn = $orderInfo['order_bn'];
            $title = '店铺('.$this->__channelObj->channel['name'].')同步售后单备注内容(售后单号:'. $original_bn .')';
        }
        
        /***
        //抖音子订单号
        $returnLubanObj = app::get('ome')->model('return_product_luban');
        $return_luban = $returnLubanObj->dump(array('shop_id'=>$shop_id, 'return_id'=>$reshipInfo['return_id']), '*');
        if($return_luban){
            $params['oid'] = $return_luban['oid']; //抖音子订单号
        }
        ***/
        
        //request
        $result = $this->__caller->call(SHOP_AFTERSALE_RETURN_REMARK, $params, array(), $title, 10, $original_bn);
        $logMsg = '';
        if($result['rsp'] == 'succ'){
            $logMsg = '同步售后单备注内容成功';
        }else{
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['msg']);
            $logMsg = '同步售后单备注内容失败：'. $error_msg;
        }
        
        //log
        if($reshipInfo['reship_id']){
            $operLogObj->write_log('reship@ome', $reshipInfo['reship_id'], $logMsg);
        }
        
        if($reshipInfo['return_id']){
            $operLogObj->write_log('return@ome', $reshipInfo['return_id'], $logMsg);
        }
        
        return $result;
    }
    
    
    public function _getParseConfirm($aftersale)
    {
        $parse = '';
        
        //售后申请单自动审批(系统-->退换货自动审核设置-->是否启用售后申请单自动审批)
        $is_auto_approve = app::get('ome')->getConf('aftersale.auto_approve');
        if ($is_auto_approve != 'on') {
            if(empty($aftersale['reship_id'])){
                $parse = 'first'; //一次审核
            }else{
                $parse = 'second'; //二次审核
            }
            
            return $parse;
        }
        
        if(empty($aftersale['reship_id']) || empty($aftersale['branch_id'])){
            return $parse;
        }
        
        $branchLib = kernel::single('ome_branch');
        $wms_type = $branchLib->getNodetypBybranchId($aftersale['branch_id']);
        if($wms_type == 'yjdf'){
            //获取退回寄件地址
            $addressInfo = app::get('ome')->model('return_address')->dump(array('reship_id'=>$aftersale['reship_id']), 'address_id');
            if($addressInfo){
                $parse = 'second'; //二次审核(获取到京东云交易回寄地址,会回传同意状态给抖音)
            }
        }else{
            $parse = 'second'; //二次审核(不是京东云交易WMS,则生成退货单时已经传同意状态给抖音)
        }
        
        return $parse;
    }
    
    /**
     * 售后申请单同步平台状态回调
     * 
     * @param $response Array
     * @param $callback_params Array
     * @return Array
     **/
    public function callback($response, $callback_params)
    {
        $failApiModel = app::get('erpapi')->model('api_fail');
        $returnProductObj = app::get('ome')->model('return_product');
        
        //异步返回的数据
        $response['rsp'] = ($response['rsp'] == 'succ' || $response['rsp'] == 'success') ? 'succ' : 'fail';
        
        //异步返回结果
        if($response['rsp'] == 'succ'){
            $response = $this->_formatResultStatus($response);
        }else{
            //失败处理
        }
        
        //[兼容]售后请求的单据号(默认使用的订单号)
        if($callback_params['return_bn']){
            $callback_params['obj_bn'] = $callback_params['return_bn'];
        }
        
        //更新失败重试记录(@todo：当返回成功会自动删除此记录)
        $failApiModel->publish_api_fail($callback_params['method'], $callback_params, $response);
        
        //获取推送平台状态
        $lubanLib = kernel::single('ome_reship_luban');
        $sync_status = $lubanLib->getReturnSyncStatus($callback_params['return_bn'], $response['rsp']);
        if(empty($sync_status)){
            if($response['rsp'] == 'fail'){
                $sync_status = '2';
            }else{
                $sync_status = '1';
            }
        }
        
        //更新退货单状态
        $saveData = array('sync_status'=>$sync_status, 'sync_msg'=>'');
        if($response['rsp'] == 'fail'){
            $response['err_msg'] = substr($response['err_msg'], 0, 150);
            $saveData = array('sync_status'=>$sync_status, 'sync_msg'=>$response['err_msg']);
        }
        $returnProductObj->update($saveData, array('return_bn'=>$callback_params['return_bn']));
        
        return $response;
    }

    public function getRefuseReason($return_id=null){
        $reason = array(
            array(
                'reason_id' => '1',
                'reason_text' => '商品已发出，如买家不再需要请拒收后申请仅退款或收到后申请退货退款'
            ),
            array(
                'reason_id' => '2',
                'reason_text' => '商品已经签收，如买家不再需要可以申请退货退款'
            ),
            array(
                'reason_id' => '3',
                'reason_text' => '买家误操作/取消申请'
            ),
            array(
                'reason_id' => '4',
                'reason_text' => '问题已解决，待用户收货'
            ),
            array(
                'reason_id' => '5',
                'reason_text' => '商品已发出，如买家不再需要请拒收后申请仅退款或收到后申请退货退款'
            ),
            array(
                'reason_id' => '6',
                'reason_text' => '买家误操作/取消申请'
            ),
            array(
                'reason_id' => '7',
                'reason_text' => '协商一致，用户取消退款'
            ),
            array(
                'reason_id' => '8',
                'reason_text' => '已与买家协商补偿，包括差价、赠品、额外补偿'
            ),
            array(
                'reason_id' => '9',
                'reason_text' => '已与买家协商补发商品'
            ),
            array(
                'reason_id' => '10',
                'reason_text' => '已与买家协商换货'
            ),
            array(
                'reason_id' => '11',
                'reason_text' => '买家上传的单号有误，商家尚未收到货，请核实正确物流单号后重新上传'
            ),
            array(
                'reason_id' => '12',
                'reason_text' => '退货与原订单不符（商品不符、退货地址不符）'
            ),
            array(
                'reason_id' => '13',
                'reason_text' => '退回商品影响二次销售'
            ),
            array(
                'reason_id' => '15',
                'reason_text' => '协商一致，用户取消退款请'
            ),
            array(
                'reason_id' => '16',
                'reason_text' => '买家误操作/取消申请'
            ),
            array(
                'reason_id' => '17',
                'reason_text' => '协商一致，用户取消退款'
            ),
            array(
                'reason_id' => '18',
                'reason_text' => '商品影响二次销售'
            ),
            array(
                'reason_id' => '19',
                'reason_text' => '定制商品不支持七天无理由退货，定制商品不接受质量问题以外的退货'
            ),
            array(
                'reason_id' => '20',
                'reason_text' => '定制商品不支持七天无理由退货，定制商品不接受质量问题以外的退货'
            ),
            array(
                'reason_id' => '21',
                'reason_text' => '买家申请的金额有误'
            ),
            array(
                'reason_id' => '22',
                'reason_text' => '运费未协商一致'
            ),
            array(
                'reason_id' => '23',
                'reason_text' => '商品没问题，买家未举证或凭证无效'
            ),
            array(
                'reason_id' => '24',
                'reason_text' => '已在约定时间发货'
            ),
            array(
                'reason_id' => '25',
                'reason_text' => '运费未协商一致'
            ),
            array(
                'reason_id' => '26',
                'reason_text' => '商品已经签收，如买家不再需要可以申请退货退款'
            ),
            array(
                'reason_id' => '27',
                'reason_text' => '商品没问题，买家未举证或举证无效'
            ),
            array(
                'reason_id' => '28',
                'reason_text' => '已在约定时间发货'
            ),
            array(
                'reason_id' => '29',
                'reason_text' => '买家申请的金额有误'
            ),
            array(
                'reason_id' => '30',
                'reason_text' => '发票没问题，买家未举证'
            ),
            array(
                'reason_id' => '31',
                'reason_text' => '发票已补寄'
            ),
            array(
                'reason_id' => '32',
                'reason_text' => '买家发票信息不完整'
            ),
            array(
                'reason_id' => '33',
                'reason_text' => '运费未协商一致'
            ),
            array(
                'reason_id' => '34',
                'reason_text' => '申请时间已超7天无理由退换货时间'
            ),
            array(
                'reason_id' => '35',
                'reason_text' => '不支持买家主观原因退换货'
            ),
            array(
                'reason_id' => '36',
                'reason_text' => '买家填错号码'
            ),
            array(
                'reason_id' => '37',
                'reason_text' => '已完成服务，买家未提供凭证或凭证无效'
            ),
            array(
                'reason_id' => '38',
                'reason_text' => '买家填错号码'
            ),
            array(
                'reason_id' => '39',
                'reason_text' => '已完成服务，买家未提供凭证或凭证无效'
            ),
        );

        return $reason;
    }
    
    /**
     * 格式化抖音平台请求退款返回的状态
     *
     * @param array $response
     * @return array
     */
    public function _formatResultStatus($response)
    {
        //data
        $rspData = ($response['data'] ? $response['data'] : '');
        if(is_string($rspData)){
            $rspData = json_decode($response['data'], true);
        }
        
        //[兼容]聚合接口按list列表返回结果
        $items = $rspData['results']['data']['items'];
        if(empty($items)){
            return $response;
        }
        
        foreach ($items as $key => $val)
        {
            if($val['status_code'] != '0' && $val['status_msg'] != '成功'){
                $response['rsp'] = 'fail';
                $response['res'] = $val['status_code'];
                $response['err_msg'] .= $val['status_msg'];
                $response['msg'] .= $val['status_msg'];
            }
        }
        
        return $response;
    }
}
