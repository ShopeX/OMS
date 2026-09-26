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

class wap_event_trigger_cloudprint {
    

    /**
     * print
     * @param mixed $delivery_id ID
     * @return mixed 返回值
     */
    public function print($delivery_id)
    {
        $orderMdl = app::get('ome')->model('orders');
        $wapDlyMdl = app::get('wap')->model('delivery');
        $omsDlyMdl = app::get('ome')->model('delivery');

        # 检查发货单状态
        $result = ['rsp' => 'succ'];
        # 检查发货单状态
        $msg = '';
        $omsDlyInfo = kernel::single("wap_delivery")->checkDeliveryStatus($delivery_id, $msg);

       
        if (!$omsDlyInfo) {
            $result['res'] = 'fail';
            $result['err_msg'] = $msg;
            return $result;
        }

        $printRes = $this->printText($delivery_id);

        if ($printRes['rsp'] != 'succ') {
            $result['rsp'] = 'fail';
            $result['err_msg'] = $printRes['msg'] ? $printRes['msg'] : $printRes['err_msg'];
            return $result;
        }
        $this->updatePrintStatus($delivery_id);
        
        return $result;
    }

    /**
     * 更新 WAP 发货单打印状态（与 doPrint 成功后一致）
     */
    public function updatePrintStatus($delivery_id)
    {
        $wapDlyMdl = app::get('wap')->model('delivery');
        $wapdelivery = $wapDlyMdl->dump(array('delivery_id' => $delivery_id), 'print_count');
        if (!$wapdelivery) {
            return false;
        }
        $print_count = intval($wapdelivery['print_count']) + 1;
        $updateDly = array(
            'print_status' => 1,
            'confirm'      => 1,
            'print_count'  => $print_count,
        );
        return $wapDlyMdl->update($updateDly, array('delivery_id' => $delivery_id));
    }


    /**
     * printText
     * @param mixed $deliveryId ID
     * @return mixed 返回值
     */
    public function printText($deliveryId) {
        
        $_err = 'false';

        /* 单品、多品标识 */
        $sku = '';

        $now_print_type = 'ship';

        //获取当前待打印的发货单过滤条件
        $filter_condition = ['delivery_id'=>$deliveryId];

        $PrintLib = kernel::single('o2o_delivery_print');
        $print_data = $PrintLib->getPrintDatas($filter_condition,$now_print_type,$sku,false,$msg);

        foreach($print_data['deliverys'] as $val) {
            empty($logiId) && $logiId = $val['logi_id'];
           
        }

        if(!$print_data && $msg){

            $result['rsp'] = 'fail';
            $result['msg'] = $msg['warn_msg'];
            return $result;
        }
        $corp = app::get('ome')->model('dly_corp')->dump($logiId);
        $PrintShipLib = kernel::single('o2o_delivery_print_ship');
        $format_data = $PrintShipLib->format($print_data, $sku,$_err);

        $commonLib = kernel::single('wap_event_trigger_cloudprint_common');
        if($format_data) {
            
            $deliverys = $print_data['deliverys'][$deliveryId];
            $printResult = $this->processImage($format_data);

         
            if($printResult['rsp']=='fail'){
                return $printResult;
            }
            $branch_id = $deliverys['branch_id'];
            $wap_deliveryLib = kernel::single('wap_delivery');

            $stores = $wap_deliveryLib->getBranchShopInfo($branch_id);
            $store_id = $stores['store_id'];

            $cloudprint = $commonLib->getCloudprint($store_id);
            if(!$cloudprint || empty($cloudprint['app_key']) || empty($cloudprint['secret_key'])){
                $result['rsp'] = 'fail';
                $result['msg'] = '请给门店配置云打印机';
                return $result;
            }
            # 打印电子面单
            $printParams = [
                'order_bn'          => $deliverys['order_bn'],
                'outer_delivery_bn' => $deliverys['outer_delivery_bn'],
                'outer_delivery_id' => $sdf['wap_delivery_id'],
                'branch_id'         => $deliverys['branch_id'],
                'img_url'           => $printResult['img_url'],
                'stores'            => $stores,
                'cloudprint'        => $cloudprint,
            ];

            $result = kernel::single('erpapi_router_request')->set('yilianyun', true)->logistics_printText($printParams);

          
            if ($result['rsp'] != 'succ') {
                $result['rsp'] = 'fail';
                $result['msg'] = $printRes['msg'];
                return $result;
            
            }
            return $result;
        }else{
            $result['rsp'] = 'fail';
            $result['msg'] = '打印数据异常';
            return $result;
        }
       
        
    }

    
    /**
     * 获取omedelivery
     * @param mixed $wap_delivery_id ID
     * @return mixed 返回结果
     */
    public function getomedelivery($wap_delivery_id){

        $wapdeliveryMdl = app::get('wap')->model('delivery');
        $wapdelivery = $wapdeliveryMdl->dump(array('delivery_id'=>$wap_delivery_id),'outer_delivery_bn');
        $outer_delivery_bn = $wapdelivery['outer_delivery_bn'];
        $wamdeliveryLib = kernel::single('wap_delivery');
        $omedeliverys = $wamdeliveryLib->getOmeDeliveryInfo($outer_delivery_bn);

        return $omedeliverys;

    }


    /**
     * 获取PrintData
     * @param mixed $deliveryId ID
     * @return mixed 返回结果
     */
    public function getPrintData($deliveryId){
       
        $_err = 'false';

        /* 单品、多品标识 */
        $sku = '';

        $now_print_type = 'ship';

        //获取当前待打印的发货单过滤条件
        $filter_condition = ['delivery_id'=>$deliveryId];

        $PrintLib = kernel::single('o2o_delivery_print');
        $print_data = $PrintLib->getPrintDatas($filter_condition,$now_print_type,$sku,false,$msg);
     
        if(!$print_data && $msg){

            $result['rsp'] = 'fail';
            $result['msg'] = $msg['warn_msg'];
            return $result;
        }
        $PrintShipLib = kernel::single('o2o_delivery_print_ship');
        $format_data = $PrintShipLib->format($print_data, $sku,$_err);
       
        return $format_data;
    }
    

    /**
     * _print
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function _print($data){
        $prolib = kernel::single('wap_cloudprint_processor');
        $templateLib = kernel::single('wap_cloudprint_template');
        $templates = $templateLib->getTemplate();
        $template_content = $templates['template_data'];

        $content= $prolib->processTemplate($template_content, $data);


        return $content;
    }

    /**
     * 处理Image
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function processImage($data){
        require_once APP_DIR . '/wap/lib/generate_template_image.php';
        $generator = new TemplateImageGenerator(array('paper_width' => 100));
       
        $filename = DATA_DIR.'/waybill_' . date('YmdHis') . '.jpg';
       

        $mydata = $this->processprintdata($data);
    
        if($mydata['rsp']=='fail'){
            return $mydata;
        }

        if(strpos($mydata['ship_mobile'] , '***') || strpos($mydata['ship_detailaddr'] , '***')) {
           // return array('rsp' => 'fail', 'msg' => '收货人信息解密失败');
        }
        $dly_tmpl_id = $data['dly_tmpl_id'];
        $rs = $generator->generateImage($dly_tmpl_id, $filename,$mydata);

        $file_id = ome_func::uuid();
        $rs = $generator->uploadFile($file_id, $filename);

        return $rs;
        
    }

    /**
     * 处理printdata
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function processprintdata($data){

        foreach ($data['delivery'] as $k=>$val) {
            $val['delivery_bn'] = $val['outer_delivery_bn'];

            $order_bn = $val['order_bn'];
            $decryptorders = $this->_getEncryptOriginData($order_bn);

            if($decryptorders['rsp']=='succ'){
                $data['delivery'][$k]['consignee']['name'] = $decryptorders['data']['ship_name'];
                $data['delivery'][$k]['consignee']['addr'] = $decryptorders['data']['ship_addr'];
                $data['delivery'][$k]['consignee']['telephone'] = $decryptorders['data']['ship_tel'];
                $data['delivery'][$k]['consignee']['mobile'] = $decryptorders['data']['ship_mobile'];
                $val['consignee']['name'] = $decryptorders['data']['ship_name'];
                $val['consignee']['addr'] = $decryptorders['data']['ship_addr'];
                $val['consignee']['telephone'] = $decryptorders['data']['ship_tel'];
                $val['consignee']['mobile'] = $decryptorders['data']['ship_mobile'];


            }

            //获取快递单打印模板的servivce定义
            $data = array();
            foreach (kernel::servicelist('wms.service.template') as $object => $instance) {
            if (method_exists($instance, 'getElementContent')) {
                $tmp = $instance->getElementContent($val);
                }
                $data = array_merge($data, $tmp);
            }

            //输出所有打印项
            $mydata = $data;

        }
        
        return $mydata;
    }


    /**
     * _getEncryptOriginData
     * @param mixed $order_bn order_bn
     * @return mixed 返回值
     */
    public function _getEncryptOriginData($order_bn) {
       
        
        $field = 'order_bn,shop_id,shop_type,ship_tel,ship_mobile,ship_addr,ship_name,ship_area';
        $data = app::get('ome')->model('orders')->db_dump(array('order_bn' => $order_bn), $field);
        if (!$data) {
            return array('rsp' => 'fail', 'msg' => '订单号不存在');
        }

        $ship_area_str = '';
        if ($data['ship_area']) {
            $ship_area = explode(":", $data['ship_area']);
            $ship_area_str = str_replace("/", "", $ship_area[1]);
        }

        // 解密
        $decrypt_data = kernel::single('ome_security_router', $data['shop_type'])->decrypt(array(
            'ship_tel'    => $data['ship_tel'],
            'ship_mobile' => $data['ship_mobile'],
            'ship_addr'   => $data['ship_addr'],
            'shop_id'     => $data['shop_id'],
            'order_bn'    => $data['order_bn'],
            'ship_name' => $data['ship_name'],
        ), 'order', true);

        if ($decrypt_data['rsp'] && $decrypt_data['rsp'] == 'fail') {
            $errArr = json_decode($decrypt_data['err_msg'], true);
            $msg = $errArr['data']['decrypt_infos'][0]['err_msg'] ? $errArr['data']['decrypt_infos'][0]['err_msg'] : '解密失败,订单已关闭或者解密额度不足';
            $result = [
                'rsp' => 'fail',
                'err_data' => $decrypt_data,
                'msg' => $msg
            ];
            return $res;
        }

        $res = [
            'rsp' => 'succ',
            'data' => [
                'ship_name' => $decrypt_data['ship_name'],
                'ship_tel' => $decrypt_data['ship_tel'],
                'ship_mobile' => $decrypt_data['ship_mobile'],
                'ship_addr' => $decrypt_data['ship_addr'],
            ]
        ];
        return $res;
        
    }


}
