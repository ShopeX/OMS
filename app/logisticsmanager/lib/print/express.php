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
 * @author ykm 2015-12-22
 * @describe 快递单打印模板
 */
class logisticsmanager_print_express{
    public $smarty = null;
    public $printTpl; //打印模板
    public $printField; //打印需要的数据字段
    public $msg;
    static $singleton = null;
    static $logi_list = array('amazon','dangdang');//需要根据物流公司自定义快递单输入信息列表

    static function instance($logi,$controller){
        $logi = strtolower($logi);
        if(self::$singleton[$logi] === null){
            if(in_array($logi,self::$logi_list)){
                self::$singleton[$logi] = kernel::single('logisticsmanager_print_express_'.$logi,$controller);
            }else{
                self::$singleton[$logi] = kernel::single('logisticsmanager_print_express',$controller);
            }
        }
        return self::$singleton[$logi];
    }

    /**
     * __construct
     * @param mixed $controller controller
     * @return mixed 返回值
     */

    public function __construct($controller){
        $this->smarty = $controller;
    }
    public function setParams( $params = array() ){
        return $this;
    }

    /**
     * 获取ExpressTpl
     * @param mixed $prtTmplId ID
     * @return mixed 返回结果
     */
    public function getExpressTpl($prtTmplId) {
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
        if ($printTmpl['template_type'] == 'wxshipin_standard') {
            $this->printField = array('batch_logi_no','logi_no','print_config','channel_id','delivery_id','json_packet','user_id');
            if (!$this->_wxshipin_custom()) {
                return false;
            }
            return true;
        }
        $this->_dataToField($printTmpl['template_data']);
        if(empty($this->printField)) {
            $this->printField = array(//菜鸟打印固定部分
                'seller_id','ship_mobile','ship_tel','cp_code','print_config','mailno_position','package_wdjc','logi_no','ship_name','ship_detailaddr','dly_name','dly_mobile','dly_detailaddr','dly_area_1'
            );
            $this->printField = array_merge(json_decode($printTmpl['template_select'], true), $this->printField);
        }
        return true;
    }

    private function _wxshipin_custom()
    {
        $templateSelect = @json_decode($this->printTpl['template_select'], true);
        $customTplId = isset($templateSelect['wxshipin_custom_tpl_id']) ? intval($templateSelect['wxshipin_custom_tpl_id']) : 0;
        if (!$customTplId) {
            return true;
        }
        $templateObj = app::get('logisticsmanager')->model('express_template');
        $customConfig = $templateObj->getWxshipinCustomConfig($this->printTpl['template_data']);
        if (!$customConfig) {
            $this->msg = '微信视频号标准模板缺少 custom_config，请重新同步模板后再打印';
            return false;
        }
        $customTpl = $templateObj->db_dump(array('template_id' => $customTplId), 'template_id,template_type,control_type,template_data,status');
        if (!$customTpl || $customTpl['template_type'] != 'electron' || $customTpl['control_type'] != 'lodop' || $customTpl['status'] != 'true') {
            $this->msg = '关联的微信视频号 Lodop 自定义区模板已失效，请重新关联';
            return false;
        }
        $unsupported = $templateObj->getWxshipinUnsupportedPlugins($customTpl['template_data']);
        if ($unsupported) {
            $this->msg = '关联的 Lodop 模板包含暂不支持的控件：' . implode('、', $unsupported);
            return false;
        }
        $plugins = @unserialize($customTpl['template_data']);
        $customData = array('fields' => array(), 'barcode_field' => '', 'qrcode_field' => '', 'has_product_info' => false);
        foreach ((array)$plugins as $plugin) {
            $field = isset($plugin['type']['value']) ? $plugin['type']['value'] : '';
            if ($plugin['plugin'] == 'sp-input' && $field && $field != 'empty' && $field != 'custom') {
                $customData['fields'][] = $field; $this->printField[] = $field;
            } elseif ($plugin['plugin'] == 'sp-barcode' && $field && $field != 'empty' && $field != 'custom') {
                $customData['barcode_field'] = $field; $this->printField[] = $field;
            } elseif ($plugin['plugin'] == 'sp-qrcode' && $field && $field != 'empty' && $field != 'custom') {
                $customData['qrcode_field'] = $field; $this->printField[] = $field;
            } elseif ($plugin['plugin'] == 'sp-table') {
                $customData['has_product_info'] = true; $this->printField[] = 'wxshipin_product_info';
            }
        }
        $this->printField[] = 'shop_name';
        $customData['fields'] = array_values(array_unique($customData['fields']));
        $this->printField = array_values(array_unique($this->printField));
        $templateVersion = substr(md5(
            'wxshipin-renderer-v2|' . $customTpl['template_data']
            . '|' . $customConfig['width'] . 'x' . $customConfig['height']
        ), 0, 12);
        $this->printTpl['custom_area_url'] = kernel::base_url(1) . '/index.php/openapi/template/getToWxshipinTpl?tpl_id=' . $customTplId
            . '&width=' . rawurlencode($customConfig['width'])
            . '&height=' . rawurlencode($customConfig['height'])
            . '&v=' . $templateVersion;
        $this->printTpl['template_select'] = json_encode($customData);
        return true;
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

    /**
     * 获取Tmpl
     * @return mixed 返回结果
     */
    public function getTmpl(){
        $this->smarty->pagedata['userAgent'] = $this->getUserAgent();
        if($this->smarty->pagedata['printTmpl']['template_type']=='normal') {
            $this->smarty->singlepage("admin/print/express_normal.html", 'logisticsmanager');
        } elseif($this->smarty->pagedata['printTmpl']['template_type']=='cainiao'){
            $this->smarty->singlepage("admin/print/express_cainiao.html", 'logisticsmanager');
        }else {
            $this->smarty->singlepage("admin/print/express_electron.html", 'logisticsmanager');
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
}
