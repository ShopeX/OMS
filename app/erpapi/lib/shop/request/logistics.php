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

class erpapi_shop_request_logistics extends erpapi_shop_request_abstract
{
    //菜鸟流转订单处理规则同步
    /**
     * syncOrderRule
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function syncOrderRule($sdf){
        $params['shop_code'] = $sdf['shop_code'];
        $params['is_open_cnauto'] =$sdf['is_open_cnauto'];
        $params['is_auto_check'] =  $sdf['is_auto_check'];
        $params['check_rule_msg'] =  $sdf['check_rule_msg'];
        $params['is_sys_merge_order'] = $sdf['is_sys_merge_order'];
        $params['merge_order_cycle'] = $sdf['merge_order_cycle'];
        
        $title = '同步订单流转处理规则';
        $callback = array(
            'class' => get_class($this),
            'method' => 'callback',
            'params' => $params
        ); 
        $rs = $this->__caller->call(STORE_CN_RULE,$params,$callback,$title,10,$this->__channelObj->channel['shop_id']);
        return $rs;
    }

    /**
     * 搜索Address
     * @param mixed $search_type search_type
     * @param mixed $page page
     * @return mixed 返回值
     */
    public function searchAddress($search_type='', $page=0)
    {
        $shop_id = $this->__channelObj->channel['shop_id'];
        $data = array('shop_id' => $this->__channelObj->channel['shop_id'],'shop_type'=> $this->__channelObj->channel['shop_type'],'obj_bn'=>$this->__channelObj->channel['shop_id']);
        $params = array(
            'search_type'=>$search_type,    
        );
        
        $callback = array(
            'class' => get_class($this),
            'method' => 'searchAddress_callback',
            'params' => $data
        );
        $title = '店铺('.$this->__channelObj->channel['name'].')获取地址库列表';

        $this->__caller->call(SHOP_LOGISTICS_ADDRESS_SEARCH,$params,$callback,$title,10,$this->__channelObj->channel['shop_id']);
        
        return true;
    }
    
    /**
     * 搜索Address_callback
     * @param mixed $response response
     * @param mixed $callback_params 参数
     * @return mixed 返回值
     */
    public function searchAddress_callback($response, $callback_params){
        
        $oAddress = app::get('ome')->model('return_address');
        $shop_id = $callback_params['shop_id'];
        $rsp = $response;
        if ($rsp['rsp']=='succ' && $shop_id) {
            $address_list = json_decode($rsp['data'],true);
             $address_list= $address_list['address_result'];

            // 删除该店铺下的所有地址
            $oAddress->delete(array('shop_id'=>$shop_id));

            //保存至本地
            if ($address_list) {
                foreach ($address_list as $list ) {
                    $data = array(
                        'cancel_def'    =>$list['cancel_def'] ? 'true' : 'false',
                        'city'          =>$list['city'],
                        'area_id'       =>(int)$list['area_id'],
                        'phone'         =>$list['phone'],
                        'mobile_phone'  =>$list['mobile_phone'],
                        'province'      =>$list['province'],
                        'addr'          =>$list['addr'],
                        'country'       =>$list['country'],
                        'contact_id'    =>$list['contact_id'],
                        'get_def'       =>$list['get_def'] ? 'true' : 'false',
                        'contact_name'  =>$list['contact_name'],
                        'seller_company'=>$list['seller_company'],
                        'send_def'      =>$list['send_def'] ? 'true' : 'false',
                        'zip_code'      =>$list['zip_code'],
                        'shop_type'     =>$callback_params['shop_type'],
                        'shop_id'       =>$shop_id,
                        'modify_date' => time(),
                        'add_type' => 'shop', //创建类型为：店铺平台
                    );
                    
                    //平台创建时间
                    if($list['create_time']){
                        $data['platform_create_time'] = $list['create_time'];
                    }
                    
                    //平台更新时间
                    if($list['update_time']){
                        $data['platform_update_time'] = $list['update_time'];
                    }
                    
                    $rp = $oAddress->save($data);
                }
            }
            
        } 
        return $this->callback($response, $callback_params);    
    }

    /**
     * 更新ReturnLogistics
     * @param mixed $reshipinfo reshipinfo
     * @return mixed 返回值
     */
    public function updateReturnLogistics($reshipinfo) {}

    /**
     * 获取CorpServiceCode
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getCorpServiceCode($sdf) {}

    /**
     * 批量获取云栈大头笔信息
     * @param   array  $datas    发货单信息
     * @param   string $cp_code  物流公司编号
     * @return  object $res      返回信息结果
     * @access  public
     * @author  liuzecheng@shopex.cn
     */
    public function getCloudStackPrintTags($datas,$cp_code) {
        $params = array(
            'cp_code' => $cp_code,
        );
        foreach ($datas as $data) {
            $address_pairs = array(
                'shipping_address'=>array(
                    'area'=>$data['dly_area_2'],
                    'province'=>$data['dly_area_0'],
                    'town'=>'',
                    'city'=>$data['dly_area_1'],
                    'address_detail'=>$data['dly_address'],
                ),
                //订单号非必须参数
                'trade_order_code'=> $data['delivery_bn'],
                //收货人地址
                'consignee_address'=> array(
                    'area'=>$data['ship_area_2'],
                    'province'=>$data['ship_area_0'],
                    'town'=> '',
                    'city'=>$data['ship_area_1'],
                    'address_detail'=> $data['ship_addr'],
                ),
                //物流单号非必须参数（暂时屏蔽）
                // 'waybill_code'=> ''
            );
            $address_pairss[] = $address_pairs;
        }
        $params['address_pairs'] = json_encode($address_pairss);
        $title = '获取（' . $cp_code . '）的' . '云栈大头笔';
        // 记录获取云栈大头笔日志
        $result = $this->__caller->call(SHOP_GET_CLOUD_STACK_PRINT_TAG, $params, array(), $title, 20,$data['delivery_bn']);
        return $result;
    }

    /**
     * 获取物流可不可达
     * 
     * @return void
     * @author 
     * */
    public function getAddressReachable($sdf)
    {
        $params = array();

        $result = $this->__caller->call(LOGISTICS_SERVICE_AREAS_ALL_GET, $params, array(), '物流可不可达', 6);

        return $result;
    }

    //获取跨境物流
    public function crossbordercorp($sdf){
        $param['type'] = 'OFFLINE';# ONLINE或者
        $param['from_id'] = $sdf['region_id'];# 发货地区域id
        $param['to_address'] =  json_encode($sdf['to_address']);  # 收件人地址
        $param['shop_id'] =  $this->__channelObj->channel['shop_id'];

        $title = '店铺('.$this->__channelObj->channel['name'].')直邮获取跨境资源列表'; 
        $result = $this->__caller->call(SHOP_WLB_THREEPL_RESOUCE_GET, $param, '', $title, 10);   
        return $this->back_crossbordercorp($result,$param);
    }
        /**
     * back_crossbordercorp
     * @param mixed $response response
     * @param mixed $callback_params 参数
     * @return mixed 返回值
     */
    public function back_crossbordercorp($response, $callback_params){
        $obj_dly_corp = app::get('ome')->model('dly_corp');
        $shop_id = $callback_params['shop_id'];
        $rsp = $response;
        if ($rsp['rsp']== 'succ' && $shop_id) {
             $data = json_decode($rsp['data'],true);
             $corp_list = $data['result']['resources']['three_pl_consign_resource_dto'];
            $all_crossborder_res_id = $obj_dly_corp->getList('crossborder_res_id',array('crossborder_souce'=>$shop_id,'is_crossborder'=>'true','crossborder_region_id'=>$callback_params['from_id']));
            $all_crossborder_res_id = array_map('current', $all_crossborder_res_id);
            //保存至本地
            if ($corp_list) {
                foreach ($corp_list as $list ) {
                    $res_id = number_format($list['res_id'],0,'','');
                    #已经存在的res_id，过滤掉
                    if(in_array($res_id, $all_crossborder_res_id))continue;
                    $data = array (
                        'all_branch' => 'true',#默认是适用所有仓库
                        'tmpl_type' => 'normal',
                        'name' => $list['res_name'],
                        'type' => $list['res_code'],
                        'setting' => '1',#统一地区
                        'firstunit' =>  $list['basic_weight']?$list['basic_weight']:0,#首重
                        'firstprice' => $list['basic_weight_price']?$list['basic_weight_price']:0,#首重价格
                        'continueunit' => $list['step_weight']?$list['step_weight']:0,#续重
                        'continueprice' => $list['step_weight_price']?$list['step_weight_price']:0,#续重价格
                        'corp_type' => 1,#1是跨境
                        'crossborder_res_id'=>$res_id,
                        'crossborder_souce'=>$shop_id,
                        'crossborder_region_id'=>$callback_params['from_id']
                    );
                    $obj_dly_corp->save($data);
                    $corp_lastInsertId= $obj_dly_corp->db->lastInsertId();
                    if ($corp_lastInsertId){
                        //获取仓库list 默认是适用所有电商主仓
                        $mdl_ome_branch = app::get('ome')->model('branch');
                        $rs_branch_ids = $mdl_ome_branch->getList("branch_id",array("b_type"=>"1","type"=>"main"));
                        //新建仓库与物流的关系
                        $branch_corp_lib = kernel::single("ome_branch_corp");
                        $arr_corp = array("corp_id"=>$corp_lastInsertId);
                        $branch_corp_lib->createBranchCorpRelationship($arr_corp,$rs_branch_ids);
                    }
                }
            }
        }else{
            $err_msg = json_decode($rsp['msg'],true);
            if(!empty($err_msg)){
                $rsp['msg'] = $err_msg['result']['error_msg'];
            }else{
                $rsp['msg'] = $rsp['msg'];
            }
        }
        return $rsp;
    } 

    /**
     * timerule
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function timerule($sdf)
    {
    }

    /**
     * 获取Recommend
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getRecommend($sdf) {return $this->succ('没有推荐物流');}
    
    /**
     * 同步OMS物流公司给到翱象系统
     * 
     * @param array $params
     * @return array
     */
    public function createAoxiangLogistics($params)
    {
        $title = '同步OMS物流公司给到翱象系统';
        
        $original_bn = $params[0]['shop_bn'];
        $original_bn = ($original_bn ? $original_bn : date('Ymd', time()));
        
        //warehouse
        $delivery_infos = array();
        foreach ($params as $key => $val)
        {
            $status = ($val['disabled'] == 'true' ? '0' : '1');
            
            $logiInfo = array (
                'erp_code' => $val['erp_code'], //erp配资源唯一编码,卖家唯一
                'platform_code' => $val['logi_code'], //平台资源编码(物流公司编码)
                'name' => $val['logi_name'], //资源名称(物流公司名称)
                'erp_delivery_biz_code' => $val['erp_code'], //商家编码,商家在erp维护的编码
                'con_name' => ($val['contact_name'] ? $val['contact_name'] : ''), //联系人姓名
                'con_phone' => ($val['contact_phone'] ? $val['contact_phone'] : ''), //联系人电话
                'status' => $status, //状态：0=停用,1=启用
            );
            
            $delivery_infos[] = $logiInfo;
        }
        
        //params
        $requestParams = array(
            //'request_id' => uniqid(),
            //'request_time' => time(),
            'delivery_infos' => json_encode($delivery_infos), //物流公司数组,最多50条
        );
        
        //callback
        $callback = array();
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_LOGISTICS_CREATE, $requestParams, $callback, $title, 10, $original_bn);
        
        //format
        if($result['rsp'] == 'succ'){
            $dataList = $result['data'];
            
            //unset
            unset($result['data']);
            
            //string
            if(is_string($dataList)){
                $dataList = json_decode($dataList, true);
            }
            
            $detailItems = $dataList['data']['detail']['detail_item'];
            
            $resData = array();
            foreach ((array)$detailItems as $key => $val)
            {
                $erp_code = $val['erp_code'];
                
                //check
                if($val['success'] == 1 || $val['success'] == 'true'){
                    $resData['succ'][$erp_code] = array('erp_code'=>$erp_code);
                }else{
                    $resData['fail'][$erp_code] = array('erp_code'=>$erp_code, 'message'=>$val['message']);
                }
            }
            
            $result['datalist'] = $resData;
        }
        
        return $result;
    }
    
    /**
     * 删除翱象系统里OMS同步的物流公司
     * 
     * @param array $params
     * @return array
     */
    public function deleteAoxiangLogistics($params)
    {
        $title = '删除翱象系统里OMS同步的物流公司';
        
        $original_bn = $params[0]['shop_bn'];
        $original_bn = ($original_bn ? $original_bn : date('Ymd', time()));
        
        //warehouse
        $delivery_infos = array();
        foreach ($params as $key => $val)
        {
            $logiInfo = array (
                'erp_code' => $val['erp_code'], //erp配资源唯一编码,卖家唯一
                'platform_code' => $val['logi_code'], //平台资源编码(物流公司编码)
                'name' => $val['logi_name'], //资源名称(物流公司名称)
                'erp_delivery_biz_code' => $val['erp_code'], //商家编码,商家在erp维护的编码
                'con_name' => ($val['contact_name'] ? $val['contact_name'] : ''), //联系人姓名
                'con_phone' => ($val['contact_phone'] ? $val['contact_phone'] : ''), //联系人电话
                'status' => '0', //状态：0=停用,1=启用
            );
            
            $delivery_infos[] = $logiInfo;
        }
        
        //params
        $requestParams = array(
            //'request_id' => uniqid(),
            //'request_time' => time(),
            'delivery_infos' => json_encode($delivery_infos), //物流公司数组,最多50条
        );
        
        //callback
        $callback = array();
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_LOGISTICS_CREATE, $requestParams, $callback, $title, 10, $original_bn);
        
        //format
        if($result['rsp'] == 'succ'){
            $dataList = $result['data'];
            
            //unset
            unset($result['data']);
            
            //string
            if(is_string($dataList)){
                $dataList = json_decode($dataList, true);
            }
            
            $detailItems = $dataList['data']['detail']['detail_item'];
            
            $resData = array();
            foreach ((array)$detailItems as $key => $val)
            {
                $erp_code = $val['erp_code'];
                
                //check
                if($val['success'] == 1 || $val['success'] == 'true'){
                    $resData['succ'][$erp_code] = array('erp_code'=>$erp_code);
                }else{
                    $resData['fail'][$erp_code] = array('erp_code'=>$erp_code, 'message'=>$val['message']);
                }
            }
            
            $result['datalist'] = $resData;
        }
        
        return $result;
    }
    
    /**
     * 获取CarrierPlatform
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getCarrierPlatform($sdf){}

    /**
     * 查询开通的网点账号信息, 先按有赞的格式返回
     * 
     * @author chenping@shopex.cn
     * @since 2024-09-19 16:57:21
     */
    public function getWaybillNetSite($sdf = [])
    {
        $title = '查询开通的网点账号信息';

        $result = $this->__caller->call(STORE_WAYBILL_SEARCH, [], [], $title, 6);
        if ($result['rsp'] == 'succ' && $result['data']){
            $data = @json_decode($result['data'], 1);

            $result['data'] = [];
            foreach ((array)$data['data'] as $key => $val) {
                
                foreach ($val['lattice_point_detail_model'] as $k => $v) {
                    foreach ($v['delivery_address_models'] as $k1 => $v1) {
                        $netsite = [
                            'consignor_name' => $v1['consignor_name'], // 发货人姓名
                            'consignor_phone' => $v1['consignor_phone'], // 发货人联系电话-手机
                            'address' => $v1['address'], // 详细地址
                            'county_name' => $v1['county_name'], // 县区
                            'city_name' => $v1['city_name'], // 市
                            'consignor_tel' => $v1['consignor_tel'], // 发货人联系电话-固话
                            'province_name' => $v1['province_name'], // 省
                            'brand_code' => $v['brand_code'], // 品牌编码
                            'brand_name' => $v['brand_name'], // 品牌名称
                            'lattice_point_name' => $v['lattice_point_name'], // 网点名称
                            'lattice_point_no' => $v['lattice_point_no'], // 网点编号
                            'customer_code' => $v1['customer_code'],
                            'code' => $val['express_id'], // 物流公司编码
                            'name' => $val['express_name'], // 物流公司名称 
                            'logo' => $val['logo'], // 物流公司logo
                            'is_pay' => $val['is_pay'], // 是否需要支付结算true需要，false不需要
                            'payment_type' => $val['payment_type'], // 结算方式：0-统一结算；1-自结算
                            'express_biz_type' => $val['express_biz_type'], // 快递公司⽀持业务类型，1为直营，2为加盟，3为落地配，4为直营⽹点
                        ];

                        if ($sdf['cp_code'] && $sdf['cp_code'] != $val['express_id']){
                            continue;
                        }

                        $result['data'][] = $netsite;
                    }
                }
            }
        }

        if ($result['data']){
            $result['data'][0]['is_default'] = true;
        }

        return $result;
    }
    
    /**
     * 查询物流公司, 先按有赞的格式返回
     * 
     * @author chenping@shopex.cn
     * @since 2024-09-19 18:03:01
     */
    public function getCompanies($sdf = [])
    {
        $title = '查询物流公司';

        $result = $this->__caller->call(STORE_LOGISTICS_COMPANIES_GET, [], [], $title, 6);
        
        if ($result['rsp'] == 'succ' && $result['data']){
            $data = @json_decode($result['data'], 1);
            $result['data'] = [];
            foreach ($data as $key => $val) {
                $result['data'][] = [
                    'code' => $val['id'],
                    'name' => $val['name'],
                ];
            }
        }

        return $result;
    }

    /**
     * 查询包裹异常状态
     * @param array $params 请求参数
     * @return array [rsp, msg, data]
     */
    public function exception_query($sdf)
    {
        $title = '查询包裹异常状态';

        $params = [];

        $params['exception_code'] = $sdf['exception_code'];
        // 转换为毫秒级时间戳
        $params['create_start_time'] = is_numeric($sdf['start_time']) ? $sdf['start_time'] : (strtotime($sdf['start_time']) * 1000);
        $params['create_end_time'] = is_numeric($sdf['end_time']) ? $sdf['end_time'] : (strtotime($sdf['end_time']) * 1000);

        if ($sdf['sub_exception_code']) {
            $params['sub_exception_code'] = $sdf['sub_exception_code'];
        }

        $params['page_index'] = $sdf['page_no'];
        $params['page_size'] = $sdf['page_size'];

        $result = $this->__caller->call(STORE_LOGISTICS_PACKAGE_EXCEPTION_QUERY, $params, [], $title, 10);
        

        // 如果data是JSON字符串，需要解码
        if ($result['rsp'] == 'succ' && is_string($result['data'])) {
            $result['data'] = json_decode($result['data'], true);
        }

        // 添加total_num字段到返回结果中
        if ($result['rsp'] == 'succ' && isset($result['data']['total_num'])) {
            $result['total_num'] = $result['data']['total_num'];
        }
        
        // 处理嵌套的data结构，只返回一层
        if ($result['rsp'] == 'succ' && isset($result['data']['data'])) {
            $result['data'] = $result['data']['data'];
        }

        return $result;
    }

    /**
     * 查询物流包裹异常配置
     * @param array $params 请求参数
     * @return array [rsp, msg, data]
     */
    public function exception_config_query($params)
    {
        // 添加request_id参数，用于幂等性控制
        if (!isset($params['request_id'])) {
            $params['request_id'] = $params['shop_id'] ?? '100000001'; // 使用shop_id作为request_id
        }
        
        $title = '查询物流包裹异常配置';
        $result = $this->__caller->call(STORE_LOGISTICS_PACKAGE_EXCEPTION_CONFIG_QUERY, $params, [], $title, 10);
        
        // 如果data是JSON字符串，需要解码
        if ($result['rsp'] == 'succ' && is_string($result['data'])) {
            $result['data'] = json_decode($result['data'], true);
        }
        
        // 处理嵌套的data结构，只返回一层
        if ($result['rsp'] == 'succ' && isset($result['data']['data'])) {
            $result['data'] = $result['data']['data'];
        }
        
        return $result;
    }

    /**
     * 官方提货（提货物流）
     * 子类可重写实现具体平台逻辑
     * @param array $sdf 参数
     * @return mixed
     */
    public function officialPickup($sdf = [])
    {
        return null;
    }
}