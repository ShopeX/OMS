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
class ome_print_tmpl_express{
	
	public $smarty = null;

	static $singleton = null;

    static $logi_list = array('amazon','dangdang');//需要根据物流公司自定义快递单输入信息列表

    /**
     *
     * @param String $logi
     * @param Object $controller
     * @return ome_print_tmpl_express
     */
    static function instance($logi,$controller){
        $logi = strtolower($logi);

        if(self::$singleton[$logi] === null){
            if(in_array($logi,self::$logi_list)){
                self::$singleton[$logi] = kernel::single('ome_print_tmpl_logi_'.$logi,$controller);
            }else{
                self::$singleton[$logi] = kernel::single('ome_print_tmpl_express',$controller);
            }
        }

        return self::$singleton[$logi];
    }

    public function __construct($controller){
        $this->smarty = $controller;
    }

    public function setParams( $params = array() ){
        return $this;
    }

    public function getTmpl(){
        $this->smarty->pagedata['userAgent'] = $this->getUserAgent();
        //新版快递单打印

        $template_type = $this->smarty->pagedata['printTmpl']['template_type'];
           
        $control_type = $this->smarty->pagedata['printTmpl']['control_type'];

        if ($control_type == 'lodop') {
            $this->smarty->singlepage("admin/delivery/express_lodop.html", 'wms');
        } elseif($template_type=='normal') {

            $this->smarty->singlepage("admin/delivery/express_print_normal.html", 'wms');

        } elseif($template_type=='cainiao'){

            $this->smarty->singlepage("admin/delivery/express_print_cainiao.html", 'wms');

        }elseif(in_array($template_type, array('cainiao_standard', 'cainiao_user'))) {

            $this->smarty->singlepage("admin/delivery/express_cainiao_web.html", 'wms');

        }elseif(in_array($template_type, array('pdd_standard', 'pdd_user'))) {

            $this->smarty->singlepage("admin/delivery/express_pdd.html", 'wms');

        } elseif(in_array($template_type, array('jd_standard', 'jd_user'))) {

            $this->smarty->singlepage("admin/delivery/express_jd.html", 'wms');

        } elseif(in_array($template_type, array('douyin_standard', 'douyin_user'))) {

            $this->smarty->singlepage("admin/delivery/express_douyin.html", 'wms');

        }elseif(in_array($template_type, array('wphvip_standard', 'wphvip_user'))) {

            $this->smarty->singlepage("admin/delivery/express_wphvip.html", 'wms');

        }elseif(in_array($template_type, array('kuaishou_standard', 'kuaishou_user'))) {

            $this->smarty->singlepage("admin/delivery/express_kuaishou.html", 'wms');

        }elseif(in_array($template_type, array('sf'))) {

            $this->smarty->singlepage("admin/delivery/express_sf.html", 'wms');

        }elseif(in_array($template_type, array('youzan_standard'))) {

            $this->smarty->singlepage("admin/print/express_youzan.html", 'wms');

        }elseif(in_array($template_type, array('xhs_standard','xhs_user'))) {

            $this->smarty->singlepage("admin/delivery/express_xhs.html", 'wms');

        }elseif(in_array($template_type, array('aikucun_standard','aikucun_user'))) {

            $this->smarty->singlepage("admin/delivery/express_aikucun.html", 'wms');

        }elseif(in_array($template_type, array('wxshipin_standard','wxshipin_user'))) {

            $this->smarty->singlepage("admin/delivery/express_wxshipin.html", 'wms');
        }  elseif(in_array($template_type, array('dewu_ppzf'))) {

            $this->smarty->singlepage("admin/delivery/express_dewu.html", 'wms');
        }elseif(in_array($template_type, array('dewu_ppzf_zy'))) {

            $this->smarty->singlepage("admin/delivery/express_dewu_zy.html", 'wms');
        }elseif(in_array($template_type, array('meituan4bulkpurchasing_user'))) {

            $this->smarty->singlepage("admin/delivery/express_meituan4bulkpurchasing.html", 'wms');
        }else {
            $this->smarty->singlepage("admin/delivery/express_print_electron.html", 'wms');
        }
    }

    /**
     * 获得浏览器版本
     * Enter description here ...
     */
    public function getUserAgent() {
        $agent = $_SERVER["HTTP_USER_AGENT"];
        $brower = array('brower' => 'Other', 'ver' => '0', 'type' => 2);
    
        if (strpos($agent, "MSIE 10.0")) {
            $brower = array('brower' => 'Ie', 'ver' => '10.0', 'type' => 1);
        }
        elseif (strpos($agent, "MSIE 9.0")) {
            $brower = array('brower' => 'Ie', 'ver' => '9.0', 'type' => 1);
        }
        elseif (strpos($agent, "MSIE 8.0")) {
            $brower = array('brower' => 'Ie', 'ver' => '8.0', 'type' => 1);
        }
        elseif (strpos($agent, "MSIE 7.0")) {
            $brower = array('brower' => 'Ie', 'ver' => '7.0', 'type' => 1);
        }
        elseif (strpos($agent, "MSIE 6.0")) {
            $brower = array('brower' => 'Ie', 'ver' => '6.0', 'type' => 1);
        }
        elseif (strpos($agent, "Trident")) {
            //IE11以后的版本
            $str = substr($agent, strpos($agent, 'rv:') + strlen('rv:'));
            $ver = substr($str, 0, strpos($str, ')'));
            $brower = array('brower' => 'Ie', 'ver' => $ver, 'type' => 1);
        }
        elseif (strpos($agent, "Chrome")) {
            $str = substr($agent, strpos($agent, 'Chrome/') + strlen('Chrome/'));
            $verInfo = explode(" ", $str);
            $brower = array('brower' => 'Chrome', 'ver' => $verInfo[0], 'type' => 2);
        }
        elseif (strpos($agent, "Firefox")) {
            $str = substr($agent, strpos($agent, 'Firefox/') + strlen('Firefox/'));
            $brower = array('brower' => 'Firefox', 'ver' => $str, 'type' => 2);
        }
        return $brower;
    }

    public function getExpressTpl($corp) {
        $prtTmplId = $corp['prt_tmpl_id'];
        $templateObj = app::get("logisticsmanager")->model('express_template');
        $printTmpl = $templateObj->dump($prtTmplId);
        if(empty($printTmpl)) {
            $this->msg = '没有设定快递单模板';
            return false;
        }
        if(empty($printTmpl['template_select'])){
            $printTmpl['template_select'] = json_encode(array());
        }else{
            $printTmpl['template_select'] = json_encode(unserialize($printTmpl['template_select']));
        }
        $this->printTpl = $printTmpl;
        if(in_array($printTmpl['template_type'], array('cainiao', 'cainiao_standard', 'cainiao_user','pdd_standard','pdd_user','jd_standard','jd_user', 'douyin_standard', 'douyin_user','kuaishou_standard','kuaishou_user','sf','xhs_standard','xhs_user','aikucun_standard','aikucun_user','meituan4bulkpurchasing_user','youzan_standard'))) {
            $rs = $this->_dealUnShopexWidgetField($corp['channel_id']);
            if(!$rs) {
                return false;
            }
        } else {
            if($printTmpl['control_type']=='lodop'){
                $plugins = unserialize($this->printTpl['template_data']);
                $this->_dataToFieldByLodop($plugins);
                $this->printTpl['template_data'] = kernel::base_url(1) . '/index.php/openapi/template/getToCNTpl?tpl_id=' . $prtTmplId;
            }else{
                $this->_dataToField($printTmpl['template_data']);
            }
        }

        return true;
    }

    private function _dealUnShopexWidgetField($channelId) {
        $printTmpl = $this->printTpl;
        switch($printTmpl['template_type']) {
            case 'cainiao':
                $this->printField = array(//菜鸟打印固定部分
                    'seller_id', 'ship_mobile', 'ship_tel', 'cp_code', 'print_config', 'mailno_position', 'package_wdjc', 'package_wd', 'logi_no', 'ship_name', 'ship_detailaddr', 'dly_name', 'dly_tel', 'dly_mobile', 'dly_detailaddr', 'dly_area_1'
                );
                $this->printField = array_merge(json_decode($printTmpl['template_select'], true), $this->printField);
                break;
            case 'cainiao_standard':
                $this->printField = array('batch_logi_no','logi_no','print_config', 'json_packet');
                break;
            case 'cainiao_user':
                $this->printField = array('batch_logi_no','logi_no','print_config', 'json_packet');
                if($this->printTpl['out_template_id'] > 0) {
                    $sdf = array(
                        'template_id' => $this->printTpl['out_template_id']
                    );
                    $rs = kernel::single('erpapi_router_request')->set('logistics', $channelId)->template_getUserDefinedTpl($sdf);
                    if ($rs['rsp'] == 'succ') {
                        $this->printTpl['custom_area_url'] = $rs['data']['custom_area_url'];
                        $this->printTpl['template_select'] = json_encode($rs['data']['template_select']);
                        $this->printField = array_merge((array)$rs['data']['template_select'], $this->printField);
                    } else {
                        $this->msg = '请求自定义模板失败,网络异常，请重试';
                        return false;
                    }
                }
                break;
            case 'pdd_standard':
                //$this->printField = array('batch_logi_no','logi_no','print_config', 'json_packet','user_id');
                //break;
            case 'pdd_user':
                $this->printField = array('batch_logi_no','logi_no','print_config', 'json_packet','user_id');
                $sdf = array(
                    'template_name' => $this->printTpl['template_name'],
                    'template_id' => $this->printTpl['out_template_id']
                );
                if($this->printTpl['template_select']) {
                    $ts = json_decode($this->printTpl['template_select'], 1);
                    if($ts['standard_template_id']) {
                        $sdf['template_id'] = $ts['standard_template_id'];
                        $sdf['custom_area_id'] = $this->printTpl['out_template_id'];
                    }
                }
                $rs = kernel::single('erpapi_router_request')
                    ->set('logistics', $channelId)->template_getUserDefinedTpl($sdf);
                if ($rs['rsp'] == 'succ') {
                    if($rs['data']) {
                        $this->printTpl['custom_area_url'] = $rs['data']['custom_area_url'];
                        $this->printTpl['template_select'] = json_encode($rs['data']['template_select']);
                        $this->printField = array_merge((array)$rs['data']['template_select'], $this->printField);
                    }
                } else {
                    $this->msg = '请求自定义模板失败,网络异常，请重试';
                    return false;
                }
                break;
            case 'jd_standard':
            case 'jd_user':
                $this->printField = array('batch_logi_no','logi_no','print_config', 'json_packet');

                $template_select = @json_decode($this->printTpl['template_select'], 1);
                $printItems      = $template_select['print_items'];
                $printItems && $this->printField = array_merge($this->printField, (array)$printItems);

                if ($template_select['user_url']){
                    $this->printTpl['custom_area_url'] = $template_select['user_url'];
                    $this->printTpl['template_select'] = json_encode((array)$printItems);
                }

                break;
            case 'douyin_standard':
                $this->printField = array('batch_logi_no','logi_no','print_config','channel_id');

                $template_data = @json_decode($this->printTpl['template_data'], 1);
                $this->printTpl['template_url'] = $template_data['template_url'];
                break;
            case 'douyin_user':
                $this->printField = array('batch_logi_no','logi_no','print_config','channel_id', 'bn_spec_num_n', 'shop_name', 'order_bn', 'order_memo', 'order_count', 'bn_name_spec_amount_y');

                $template_data = @json_decode($this->printTpl['template_data'], 1);
                $this->printTpl['template_url'] = $template_data['template_url'];
                $this->printTpl['custom_area_url'] = $template_data['custom_template_url'];

                break;
            case 'kuaishou_standard':
            case 'kuaishou_user':
                $this->printField = array('batch_logi_no','logi_no','print_config', 'json_packet');

                $template_data = @json_decode($this->printTpl['template_data'], 1);
                if($template_data['template_url']) {
                    $this->printTpl['template_url'] = $template_data['template_url'];
                } else {
                    $standard = app::get('logisticsmanager')->model('express_template')->db_dump(['out_template_id'=>$template_data['template_code']], 'template_data');
                    $standard_data =  @json_decode($standard['template_data'], 1);
                    $this->printTpl['template_url'] = $standard_data['template_url'];
                }
                $this->printTpl['custom_area_url'] = $template_data['custom_template_url'];

                break;
            case 'youzan_standard':

                $this->printField = array('batch_logi_no','logi_no','print_config','channel_id','delivery_id','json_packet','user_id');
                $this->printTpl['template_url'] = $this->printTpl['template_data'];

                // 获取自定义模板url和自定义模板内容
                $this->_xhs_custom();

                break;    
            case 'sf':
                $this->printField = array('logi_no', 'json_packet');
                $rs = kernel::single('erpapi_router_request')
                    ->set('logistics', $channelId)->electron_getAccessToken([]);
                if ($rs['rsp'] == 'succ') {
                    if($rs['data']) {
                        $this->printTpl['access_token'] = $rs['data']['accessToken'];
                    }
                } else {
                    $this->msg = '请求access_token失败,网络异常，请重试';
                    return false;
                }
                $template_data = @json_decode($this->printTpl['template_data'], 1);
                $this->printTpl['template_code'] = $template_data['templateCode'];
                $this->printTpl['custom_template_code'] = $template_data['customTemplateCode'];
                $this->printTpl['partner_id'] = SF_EXPRESS_PARTNER_ID;

                break;
            case 'meituan4bulkpurchasing_user':
                $this->printField = array('logi_no', 'json_packet', 'channel_id', 'batch_logi_no');
                $rs = kernel::single('erpapi_router_request')
                    ->set('logistics', $channelId)->electron_getAccessToken([]);
                if ($rs['rsp'] == 'succ') {
                    if($rs['data']) {
                        $this->printTpl['access_token'] = http_build_query($rs['data']);
                    }
                } else {
                    $this->msg = '请求access_token失败,网络异常，请重试';
                    return false;
                }
                $template_data = @json_decode($this->printTpl['template_data'], 1);
                $this->printTpl['template_url'] = $template_data['template_url'];
                $this->printTpl['custom_area_url'] = $template_data['custom_url'];

                break;
            case 'xhs_standard':
            case 'xhs_user':

                $this->printField = array('batch_logi_no','logi_no','print_config','channel_id','delivery_id','json_packet','user_id');
                // 获取自定义模板url和自定义模板内容
                $this->_xhs_custom();
                break;
            case 'aikucun_standard':
            case 'aikucun_user':
                $this->printField = array('batch_logi_no','logi_no','print_config','channel_id','delivery_id','json_packet','user_id');
                $this->_xhs_custom();
                break;
            default : break;
        }
        return true;
    }

    // 获取小红书自定义模板url和自定义模板内容
    private function _xhs_custom(){
        if (in_array($this->printTpl['control_type'], ['youzan'])) {
            $template_select = @json_decode($this->printTpl['template_select'] ,1) ?: [];

            $custom_area_url = $template_select['user_url'];
        } else {
            $template_data = @json_decode($this->printTpl['template_data'], 1);
            $custom_area_url = $template_data['customerTemplateUrl'];
        }

        if ($custom_area_url) {
            $this->printTpl['custom_area_url'] = $custom_area_url;

            $xml       = file_get_contents($custom_area_url);
            $simpleXml = @simplexml_load_string($xml,'SimpleXMLElement', LIBXML_NOCDATA);
            if ($simpleXml) {
                $simpleArr = json_decode(json_encode($simpleXml),1);

                // 如果自定义模板只定义了一个字段，数据层级会少一层，下面循环统一处理，顾加了一层array
                if (!isset($simpleArr['layout'][0])) {
                    $simpleArr['layout'] = [
                        $simpleArr['layout']
                    ];
                }

                $template_select = [];
                foreach ($simpleArr['layout'] as $k => $v) {
                    $text = trim($v['text']);
                    if (strpos($text, '<%=_data.') === false) {
                        continue;
                    }
                    $text = str_replace(['<%=_data.','%>'], '', $text);
                    $template_select[] = $text;
                }
                if ($template_select) {
                    $this->printTpl['template_select'] = json_encode($template_select);
                    $this->printField = array_merge($template_select, $this->printField);
                }
            }
        }
    }

    //获取快递单打印模板中需要的字段
    private function _dataToField($data) {
        $arrData = explode(';', $data);
        $field = array();
        foreach($arrData as $val) {
            if(strpos($val, 'report_field:') !== false || strpos($val, 'report_barcode:') !== false) {
                $tmpData = explode(',', $val);
                $field[] = $tmpData[5];
            }
        }
        $this->printField = $field;
    }

    //获取快递单打印模板中需要的字段(lodop){
    private function _dataToFieldByLodop($data){
        $field = array('json_packet'); //json_packet必须放在ship_mobile前，用于隐私用
        foreach ($data as $val){
            if( in_array($val['plugin'], array('sp-barcode','sp-input','sp-qrcode')) && $val['type']['value'] != 'empty'){
                $field[] = $val['type']['value'];
            }
        }

        $this->printField = $field;
    }
}
