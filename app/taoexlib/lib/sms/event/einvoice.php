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
 * @description 电子发票短信触发事件
 * @access public
 * @param void
 * @return void
 */
class  taoexlib_sms_event_einvoice extends taoexlib_sms_event_abstract implements taoexlib_sms_event_interface
{

    /**
     * 
     * 短信模板标题
     * @var string
     */
    protected $_tmplTitle = '电子发票提醒';

    /**
     * 
     * 短信模板内容
     * @var string
     */
    protected $_tmplContent = '尊敬的用户，您的订单{订单号}的电子发票已在{开票时间}开票成功，发票号码是{发票号码}，开票方是{开票方名称}，您可在发票列表点击预览来查看。{短信签名}';

    /**
     * 
     * 短信模板变量
     * @var array
     */

    protected function getTmplVariables(){
        return array(
            array('id' => 'dingdanhao', 'name' => '订&nbsp;&nbsp;单&nbsp;&nbsp;号', 'value' => '{订单号}','br'=>''),
            array('id' => 'kaipiaoshijian', 'name' => '开票时间', 'value' => '{开票时间}','br'=>'<br>'),
            array('id' => 'fapiaohaoma', 'name' => '发票号码', 'value' => '{发票号码}','br'=>''),
            array('id' => 'fapiaodaima', 'name' => '发票代码', 'value' => '{发票代码}','br'=>''),
            array('id' => 'jiashuiheji', 'name' => '价税合计', 'value' => '{价税合计}','br'=>'<br>'),
            array('id' => 'kaipiaofangmingcheng', 'name' => '开票方名称', 'value' => '{开票方名称}','br'=>'<br>'),
            array('id' => 'pdfurl', 'name' => 'PDF链接', 'value' => '{PDF链接}','br'=>''),
            array('id' => 'ofdurl', 'name' => 'OFD链接', 'value' => '{OFD链接}','br'=>''),
            array('id' => 'xmlurl', 'name' => 'XML链接', 'value' => '{XML链接}','br'=>'<br>'),
            array('id'=>'msgsign','name'=>'短信签名','value'=>'{短信签名}','br'=>''),
            array('id' => 'recovery', 'name' => '<font style="color:#f00">恢复默认</font>', 'value' => $this->_tmplContent,'br'=>'<br>','img'=>kernel::base_url(1).'/app/desktop/statics/bundle/afresh.gif'),
        );
    }

    /**
     * 检查Params
     * @param mixed $params 参数
     * @param mixed $error_msg error_msg
     * @return mixed 返回验证结果
     */
    public function checkParams(&$params, &$error_msg){

        if(!$params["order_bn"] || !$params["billing_time"] || !$params["invoice_no"] || !$params["payee_name"]){
            $error_msg = '订单号/开票时间/发票号码/开票方名称不能为空';
            return false;
        }

        return true;
    }

    /**
     * formatContent
     * @param mixed $params 参数
     * @param mixed $sendParams 参数
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */
    public function formatContent($params, &$sendParams, &$error_msg){
        //获取短信模板内容
        $rule_sample_mdl = app::get('taoexlib')->model('sms_bind');
        if(!$content){
            $contentinfo = $rule_sample_mdl->getOtherSmsContent('einvoice');
            if (!$contentinfo['content']) {
                $error_msg = '短信模板未设置或未审核通过';
                return false;
            }else{
                $content = $contentinfo['content'];
                $sendParams["tplid"] = $contentinfo['tplid'];
            }
        }

        //临时变量
        $order_bn = $params['order_bn'];
        $billing_time = $params['billing_time'];
        $invoice_no = $params['invoice_no'];
        $invoice_code = isset($params['invoice_code']) ? $params['invoice_code'] : '';
        $amount = isset($params['amount']) ? $params['amount'] : '';
        $payee_name = $params['payee_name'];
        $store_name = $params['store_name'];
        $invoice_pdf_path = isset($params['invoice_pdf_path']) ? $params['invoice_pdf_path'] : '';
        $invoice_ofd_path = isset($params['invoice_ofd_path']) ? $params['invoice_ofd_path'] : '';
        $invoice_xml_url = isset($params['invoice_xml_url']) ? $params['invoice_xml_url'] : '';
        $helper = kernel::single('base_view_helper');
        $sendParams['phones'] = $params["telephone"];
        $sendParams['order_bn'] = $order_bn;
        $sendParams['replace'] = array(
            'orderstr' => $helper->str_mosaic($order_bn, '*', true, 2, 4),
            'billing_time' => $billing_time,
            'invoice_no' => $invoice_no,
            'invoice_code' => $invoice_code,
            'amount' => $amount,
            'payee_name' => $payee_name,
            'invoice_pdf_path' => $invoice_pdf_path,
            'invoice_ofd_path' => $invoice_ofd_path,
            'invoice_xml_url' => $invoice_xml_url,
            'msgsign' => "【".$store_name."】",
        );

        foreach(kernel::servicelist('taoexlib.service.sms.einvoice.replace.format') as $object)
        {
            if(method_exists($object, 'format_replace')){
                $object->format_replace($params, $sendParams['replace']);
            }
        }

        //替换签名 获取完整短信日志content
        $find = array('{订单号}','{开票时间}','{发票号码}','{发票代码}','{价税合计}','{开票方名称}','{PDF链接}','{OFD链接}','{XML链接}','{短信签名}');
        $replace = array($sendParams['replace']['orderstr'],$sendParams['replace']['billing_time'],$sendParams['replace']['invoice_no'],$sendParams['replace']['invoice_code'],$sendParams['replace']['amount'],$sendParams['replace']['payee_name'],$sendParams['replace']['invoice_pdf_path'],$sendParams['replace']['invoice_ofd_path'],$sendParams['replace']['invoice_xml_url'],$sendParams['replace']['msgsign']);

        //获取短信日志content
        $sendParams['content'] = str_replace($find,$replace,$content);

        return true;
    }
}
