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
* 短信模板请求
*/
class taoexlib_request_sms{
    const SMS_CALLBACK_CODE_KEY = 'sms.callback.code';
    /** 回调 code 有效期（秒），24 小时 */
    const SMS_CALLBACK_CODE_TTL = 86400;

    public static $serverTimestamp = null;
    const VERSION = '1.0';
    const SERVICE_URL = '/sms-tpl/';
    const API_URL = 'http://webapi.sms.shopex.cn/';
    public static $localTimestamp = null;
    public static $writeLog = true;//开启日志
    /**
    * 根据类型返回请求URL
    *
    */
    public function _sms_templateUrl($type){
        $api_name = '';
        $api_url = '';
        switch ($type){
            case 'register':
                $api_name = 'template/register';
            break;
            case 'list':
                $api_name = 'template/list';
            break;
            case 'update':
                $api_name = 'template/update';
            break;
            case 'sendByTmpl':
                $api_name = 'sendByTmpl';
            break;
           
        }
        if ($api_name)
            $api_url = self::SERVICE_URL.$api_name;
        
        return $api_url;

    }

    public function _sms_templateParams($type,$sms_data){
        $params = array();
        base_kvstore::instance('taoexlib')->fetch('account', $account);
        if (!unserialize($account)) {
            return false;
        }
        $account = unserialize($account);
        $keys = $this->_keys();
        $keys = implode(',',$keys);
        switch($type){
            case 'register':#短信模板变量字段替换，需修改三个地方，分别为方法_keys,_keys_rp,_format_content
                $params = array(
                    'name'      =>  $sms_data['title'],
                    'entid'     =>  $account['entid'],
                    'product'   =>  APP_SOURCE,
                    'content'   =>  $this->_format_content($sms_data['content']),
                    'keys'      =>  $keys,
                    'tags'=>'',
                    'callback'=> kernel::openapi_url('openapi.taoexlib.sms','sms_callback', array('code' => self::get_sms_callback_code())),
                );
                
            break;
            case 'list':
                $params = array(
                    'entid'     =>  $account['entid'],
                    'product'   =>  APP_SOURCE,
                    'tag'  =>'',
                    'offset'=>'',
                    'limit'=>'100',
                );
            break;
            case 'update':
                $params = array(
                    'tplid'     =>  $sms_data['tplid'],
                    'entid'     =>  $account['entid'],
                    'product'   =>  APP_SOURCE,
                    'content'   =>  $this->_format_content($sms_data['content']),
                    'keys'      =>  $keys,
                );
            break;
            case 'sendByTmpl':
                $params = array(
                    'tplid'     =>  $sms_data['tplid'],
                    'product'   =>  APP_SOURCE,
                    'phones'    =>  $sms_data['phones'],
                    'replace'   =>  $sms_data['replace'],
                    'timestamp' =>  self::get_server_time(),
                    'license'   =>  base_certificate::get('certificate_id') ? base_certificate::get('certificate_id') : 1,
                    'entid'     =>  $account['entid'],
                    'entpwd'    =>  md5($account['password'] . 'ShopEXUser'),
                    'use_reply'=>'',
                    'use_backlist'=>'',
                );
            break;
        }
        
        return $params;
    }

    /**
    * 模板请求
    */
    public function sms_request($type,$method,$data){
        
        switch ($type){
            case 'register':
                $result = $this->register_request($type,$method,$data);
            break;
            case 'list':
                $result = $this->list_request($type,$method,$data);
            break;
            case 'update':
                $result = $this->update_request($type,$method,$data);
            break;
            case 'sendByTmpl':
                $result = $this->sendByTmpl_request($type,$method,$data);
            break;
           
        }
        return $result;
    }
    /**
    *注册模板
    */
    public function register_request($type,$method,$data){
        $params     = $this->_sms_templateParams($type,$data);
        $api_url    = $this->_sms_templateUrl($type);
        $result = $this->_request($api_url,$method,$params);
        
        $result = json_decode($result,1);
        return $result;
    }

    /**
    * 模板列表请求
    */
    public function list_request($type,$method,$data){
        $params     = $this->_sms_templateParams($type,$data);
        $api_url    = $this->_sms_templateUrl($type);
        $result = $this->_request($api_url,$method,$params);
        $result = json_decode($result,1);
        if ($result['res'] == 'succ') {
            $result = $result['data'];
            foreach ($result as $re ) {
                $this->_update_sms_approval_status($re, $data);
            }
        }
        return true;
    }

    /**
    * 更新短信模板
    *
    */
    public function update_request($type,$method,$data){
        $oSms_sample = app::get('taoexlib')->model('sms_sample');
        $params     = $this->_sms_templateParams($type,$data);
        $api_url    = $this->_sms_templateUrl($type);
        $result = $this->_request($api_url,$method,$params);
        $result = json_decode($result,1);
        $id = $data['id'];
        $iid = $data['iid'];
        $sync_status = 'true';
        if ($result['res']=='fail') {
            $sync_status = 'fail';
        }
        $oSms_sample->db->exec("UPDATE sdb_taoexlib_sms_sample_items SET sync_status='".$sync_status."' WHERE id=".$id." AND iid=".$iid);

        return true;
    }

    /**
    * 发送短信模板提醒
    */
    public function sendByTmpl_request($type,$method,$data){
        $params     = $this->_sms_templateParams($type,$data);
        $params['replace'] = json_encode($params['replace']);
        $api_url    = $this->_sms_templateUrl($type);
        $result = $this->_request($api_url,$method,$params);
        return $result;
    }

    public function _request($api_url,$method,$params){
        $url = 'http://openapi.ishopex.cn:80/api';
        $key = defined('SMS_ISHOPEX_KEY') && SMS_ISHOPEX_KEY ? SMS_ISHOPEX_KEY : '';
        $secret = defined('SMS_ISHOPEX_SECRET') && SMS_ISHOPEX_SECRET ? SMS_ISHOPEX_SECRET : '';
        if (empty($key) || empty($secret)) {
            throw new Exception('短信服务密钥未配置，请检查 config/sms_secrets.php 或环境变量');
        }
        $http = new taoexlib_request_client($url, $key, $secret);
        if ($method == 'post'){
            $result = $http->post($api_url,$params);
        }else{
            $params = http_build_query($params);
            $result = $http->get($api_url.'?'.$params);
        }

       return $result ;
    }

    public static function get_server_time() {
        if (null === self::$serverTimestamp || null === self::$localTimestamp) {
            $param = array(
                'certi_app' => 'sms.servertime',
                'version' => self::VERSION,
                'format' => 'json',
            );
            $param['certi_ac'] = self::make_shopex_ac($param, 'SMS_TIME');
            $http = new base_httpclient;
            $result = $http->post(self::API_URL, $param);
            $result = json_decode($result);
            self::$serverTimestamp = ('succ' == $result->res) ? $result->info : 0;
            self::$localTimestamp = time();
            return self::$serverTimestamp;
        }else {
            return self::$serverTimestamp + time() - self::$localTimestamp;
        }
    }

    public static function make_shopex_ac($arr, $token) {
        $temp_arr = $arr;
        ksort($temp_arr);
        $str = '';
        foreach ($temp_arr as $key => $value) {
            if ($key != 'certi_ac') {
                $str .= $value;
            }
         }
        return md5($str . md5($token));
    }

    /**
     * 刷新短信回调 code 并写入 kv（带过期时间）
     * @return string
     */
    public static function refresh_sms_callback_code()
    {
        $code = md5(microtime());
        base_kvstore::instance('taoexlib')->store(
            self::SMS_CALLBACK_CODE_KEY,
            $code,
            self::SMS_CALLBACK_CODE_TTL
        );
        return $code;
    }

    /**
     * 获取短信回调 code，不存在或已过期则重新生成
     * @return string
     */
    public static function get_sms_callback_code()
    {
        if (base_kvstore::instance('taoexlib')->fetch(self::SMS_CALLBACK_CODE_KEY, $code) && $code) {
            return strval($code);
        }

        return self::refresh_sms_callback_code();
    }

    /**
     * 校验短信模板审核回调 code（URL 路径中的 code 与 kv 中未过期的值一致）
     * @param array $result
     * @return bool
     */
    public static function verify_sms_callback_code($result)
    {
        $code = isset($result['code']) ? trim(strval($result['code'])) : '';
        if ($code === '') {
            return false;
        }

        if (!base_kvstore::instance('taoexlib')->fetch(self::SMS_CALLBACK_CODE_KEY, $storedCode) || !$storedCode) {
            return false;
        }

        return $code === strval($storedCode);
    }

    /**
     * 根据审核结果更新短信模板状态
     * @param array $item
     * @param array $data
     * @return void
     */
    protected function _update_sms_approval_status($item, $data)
    {
        if (!in_array($item['approved'], array('0', '1'))) {
            return;
        }

        $tplid = isset($item['tplid']) ? trim(strval($item['tplid'])) : '';
        if ($tplid === '' || strlen($tplid) > 25 || !preg_match('/^[a-zA-Z0-9_-]+$/', $tplid)) {
            return;
        }

        if ($item['approved'] == '0') {
            $approved = '2';
        } else {
            $approved = '1';
        }

        $approved_at = time();
        if (isset($item['approved_at']) && preg_match('/^\d+$/', strval($item['approved_at']))) {
            $approved_at = intval($item['approved_at']);
        }

        $itemsModel = app::get('taoexlib')->model('sms_sample_items');
        $sampleModel = app::get('taoexlib')->model('sms_sample');
        $filter = array('tplid' => $tplid);

        $itemsData = array(
            'approved' => $approved,
            'approvedtime' => $approved_at,
        );
        if ($item['approved'] == '0' && isset($item['reason'])) {
            $itemsData['sync_reason'] = substr(trim(strval($item['reason'])), 0, 200);
        }
        if (isset($data['isapproved']) && $data['isapproved'] == 'true') {
            $itemsData['status'] = '1';
        }

        $itemsModel->update($itemsData, $filter);
        $sampleModel->update(array('approved' => $approved), $filter);
    }
    /**
     *格式化内容
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function _format_content($content)
    {
        $find = array('{会员名}','{收货人}','{店铺名称}','{物流公司}','{物流单号}','{发货时间}','{配送费用}','{订单号}','{订单金额}','{付款金额}','{订单优惠}','{发货单号}','{收货人手机号}','{订单时间}','{短信签名}','{提货单}','{校验码}','{门店名称}','{门店地址}','{门店联系电话}','{开票方名称}','{发票号码}','{开票时间}','{验证码}','{分机号}');
        $replace = $this->_keys_rp();
        
        $messcontent = str_replace($find,$replace,$content);
        return $messcontent;
    }

    /**
    * 替换键值
    *
    */
    public function _keys_rp(){
        $keys = array('{uname}','{ship_name}','{shopname}','{logi_name}','{logi_no}','{delivery_time}','{logi_actual}','{orderstr}','{total_amount}','{payed}','{cheap}','{delivery_bn}','{ship_mobile}','{create_time}','{msgsign}','{pickup_bn}','{pickup_code}','{store_name}','{store_addr}','{store_contact_tel}','{payee_name}','{invoice_no}','{billing_time}','{check_code}','{fenjihao}');
        return $keys;
    }

    /**
    * 原始替换键值
    *
    */
    public function _keys(){
        $keys = array('uname','ship_name','shopname','logi_name','logi_no','delivery_time','logi_actual','orderstr','total_amount','payed','cheap','delivery_bn','ship_mobile','create_time','msgsign','pickup_bn','pickup_code','store_name','store_addr','store_contact_tel','payee_name','invoice_no','billing_time','check_code','fenjihao');
        return $keys;
    }

    /**
     * 验签注册
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function newoauth_request($data)
    {
        $name = $data['sms_sign'];
        $sms_signObj = app::get('taoexlib')->model('sms_sign');
        $sms_sign = $sms_signObj->dump(array('name'=>$name),'*');

        if (!$sms_sign || $sms_sign['extend_no']=='') {
            base_kvstore::instance('taoexlib')->fetch('account', $account);
            if (!unserialize($account)) {
                return false;
            }
            $account = unserialize($account);
            $url = 'https://openapi.shopex.cn:80/';
            $key = defined('SMS_SHOPEX_KEY') && SMS_SHOPEX_KEY ? SMS_SHOPEX_KEY : '';
            $secret = defined('SMS_SHOPEX_SECRET') && SMS_SHOPEX_SECRET ? SMS_SHOPEX_SECRET : '';
            if (empty($key) || empty($secret)) {
                throw new Exception('短信服务密钥未配置，请检查 config/sms_secrets.php 或环境变量');
            }
            $http = new taoexlib_request_client($url, $key, $secret);
            $http->sign_params_in_url=false;
            $params = array(
                'shopexid'     =>  $account['entid'],
                'passwd'    =>  md5($account['password'] . 'ShopEXUser'),
                'content'=>$name,
            );
            $api_url = '/api/addcontent/new';
            $result = $http->post($api_url,$params);
            $result = json_decode($result,1);
            
            if ($result['res']) {
                
                $extend_no = isset($result['data']['extend_no']) ? $result['data']['extend_no'] : '';
                $sign_data = array(
                    'name'  =>$name,
                    'extend_no'=>$extend_no,
                );
                if ($sms_sign && $extend_no) {
                    
                    $sms_signObj->db->exec("UPDATE sdb_taoexlib_sms_sign SET extend_no='".$extend_no."' WHERE s_id=".$sms_sign['s_id']);
                }else{
                    $sms_signObj->save($sign_data);
                }
            }
            return $result;  
        }
        

    }
}
?>