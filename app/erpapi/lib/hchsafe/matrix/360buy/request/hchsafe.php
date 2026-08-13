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

class erpapi_hchsafe_matrix_360buy_request_hchsafe extends erpapi_hchsafe_request_hchsafe
{
    private $__unikey   = '';
    private $__shopname = '';

    /**
     * __construct
     * @return mixed 返回值
     */
    public function __construct()
    {
        parent::__construct();

        $this->__device_id = kernel::single('base_component_request')->get_cookie('_device_id');

        // 判断是否有京东店铺
        $shop = app::get('ome')->model('shop')->dump(array('node_type' => '360buy', 'disabled' => 'false', 'filter_sql' => 'node_id is not null and node_id !=""'));
        if ($shop) {
            $this->__unikey   = $shop['addon']['unikey'];
            $this->__shopname = $shop['addon']['shop_title'] ? $shop['addon']['shop_title'] : $shop['name'];
            $this->__node_id  = $shop['node_id'];
        }
    }

    /**
     * 登录日志
     * 
     * @return void
     * @author
     * */
    public function login($sdf)
    {
        if (!$this->__is_config_mq()) {
            return $this->succ();
        }

        $jd_shop = $this->getShop();

        if (!$jd_shop['node_id']) {
            return $this->succ();
        }

        $title = '京东安全规范日志-登录';

        $pushData = array(
            'account'     => (string) $sdf['uname'], # 商家操作账号
            'clientIp'    => $this->__remote_addr, # 商家操作的客户端IP
            'op_time'     => time(), # 商家操作时间
            'op_content'  => '商家登录ERP系统' . ($_SESSION['last_error'] ? '失败' : '成功'), # 商家操作内容
            'req_jos_url' => $this->__url, # ISV的操作请求URL
            'touch_num'   => 'NULL', # 操作涉及的订单编号
            'touch_field' => 'NULL', # I操作涉及的订单字段
            'to_node_id'  => $jd_shop['node_id'],
        );

        $rs = $this->__caller->call(HCHSAFE_JD_ADDISVLOG, $pushData, array(), $title, 10);

        return $this->succ();
    }

        /**
     * orderdata
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function orderdata($sdf)
    {

        if (!$this->__is_config_mq()) {
            return $this->succ();
        }

        $jd_shop = $this->getShop();

        if (!$jd_shop['node_id']) {
            return $this->succ();
        }

        $title = '京东安全规范日志-订单访问';

        $pushData = array(
            'account'     => $this->__account, # 商家操作账号
            'clientIp'    => $this->__remote_addr, # 商家操作的客户端IP
            'op_time'     => time(), # 商家操作时间
            'op_content'  => $sdf['operation'],
            'req_jos_url' => $this->__url, # ISV的操作请求URL
            'touch_num'   => implode(',', (array) $sdf['tradeIds']), # 操作涉及的订单编号
            'touch_field' => 'order_bn', # I操作涉及的订单字段
            'to_node_id'  => $jd_shop['node_id'],
        );
        $rs = $this->__caller->call(HCHSAFE_JD_ADDISVLOG, $pushData, array(), $title, 10);

        return $this->succ();
    }

    /**
     * orderpush
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function orderpush($sdf)
    {
        if (!$this->__is_config_mq()) {
            return $this->succ();
        }

        if (!$jd_shop['node_id']) {
            return $this->succ();
        }

        $title = '京东安全规范日志-订单推送第三方';

        $pushData = array(
            'account'     => $this->__account, # 商家操作账号
            'clientIp'    => $this->__remote_addr, # 商家操作的客户端IP
            'op_time'     => time(), # 商家操作时间
            'op_content'  => '订单推送第三方',
            'req_jos_url' => $this->__url, # ISV的操作请求URL
            'touch_num'   => implode(',', (array) $sdf['tradeIds']), # 操作涉及的订单编号
            'touch_field' => 'order_bn', # I操作涉及的订单字段
            'to_node_id'  => $jd_shop['node_id'],
        );

        $rs = $this->__caller->call(HCHSAFE_JD_ADDISVLOG, $pushData, array(), $title, 10);

        return $this->succ();
    }

    /**
     * 获取Shop
     * @return mixed 返回结果
     */
    public function getShop()
    {

        $jd_shop = app::get('ome')->model('shop')->dump(array('node_type' => '360buy', 'filter_sql' => '(node_id!="" AND node_id is not null)'), 'node_id');
        return $jd_shop;
    }

    /**
     * 风控
     * 
     * @return void
     * @author
     * */
    public function computerisk()
    {
        if (!$this->__is_config_mq() || !$this->__device_id || !$this->__unikey) {
            return $this->succ();
        }

        if (!$this->__node_id) {
            return $this->succ();
        }

        $uname        = kernel::single('desktop_user')->get_login_name();
        $mobile       = kernel::single('desktop_user')->get_mobile();
        $deviceOsType = $this->getDeviceType();
        $objSession   = kernel::single('base_session');
        $sessionId    = $objSession->sess_id();

        $title = '京东风控';

        $returnUrl = 'http://erp-redirect.shopex.cn/index.php?url=' . base64_encode(kernel::base_url(true) . '?ctl=passport&act=login_verify&sess_id=' . $sessionId . '&to_node_id=' . $this->__node_id);
        $data      = [
            'returnUrl'       => urlencode($returnUrl),
            'deviceOSType'    => $deviceOsType,
            'appId'           => '001',
            'businessType'    => '1',
            'eid'             => $this->__device_id,
            'openUDID'        => '',
            'source'          => 'source-1e20dc51ee48e5c96642664fc09df7e7',
            'deviceName'      => '',
            'email'           => '',
            'deviceOSVersion' => '',
            'pin'             => $this->__unikey,
            'appVersion'      => '',
            'loginChannel'    => '1',
            'authType'        => '1',
            'clientIp'        => $this->__remote_addr,
            'uuid'            => $this->__prefixUname . $uname,
            'mobile'          => $mobile,
        ];

        $params = [
            'jos_method' => 'jingdong.mfa.userUnifiedAuthentication',
            'data'       => json_encode($data),
            'to_node_id' => $this->__node_id,
        ];

        $rs = $this->__caller->call(JD_COMMON_TOP_SEND, $params, array(), $title, 10);
        if ($rs['rsp'] == 'fail') {

        }

        return $this->succ();
    }

        /**
     * 获取VerifyUrl
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getVerifyUrl($sdf)
    {
        if (!$this->__ati) {
            return $this->error('缺少孔明锁');
        }

        $objDesktopUser = kernel::single('desktop_user');
        $uname          = $objDesktopUser->get_login_name();
        $mobile         = $objDesktopUser->get_mobile();
        if (empty($mobile)) {
            return $this->error('缺少手机号,请联系主账号添加手机号');
        }
        $objSession = kernel::single('base_session');
        $sessionId  = $objSession->sess_id();
        $url        = 'http://erp-redirect.shopex.cn/index.php?url=' . base64_encode(kernel::base_url(true) . '?ctl=passport&act=login_verify&sess_id=' . $sessionId . '&to_node_id=' . $sdf['to_node_id']);
        $title      = '京东获取二次验证url';
        $params     = array(
            "sessionId"   => $sessionId,
            "mobile"      => $mobile,
            "redirectURL" => urlencode($url),
            "userId"      => $this->__prefixUname . $uname,
            "userIp"      => $this->__remote_addr,
            "ati"         => $this->__ati,
            "appId"       => TOP_APP_KEY,
            "appName"     => $this->__host,
            "time"        => time(),
            "to_node_id"  => $sdf['to_node_id'],
        );

        $rs = $this->__caller->call(HCHSAFE_VERIFY_URL, $params, array(), $title, 10);

        if ($rs['rsp'] == 'succ') {
            $data = json_decode($rs['data'], true);
            if ($data['verifyUrl']) {
                $objSession->set_cookie_expires(0);
                header('Location:' . $data['verifyUrl']);
                exit();
            }
        }
        return $rs;
    }

    /*
    获取设备型号
     */

    public function getDeviceType()
    {
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $type  = 'pc';
        if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
            $type = 'ios';
        }
        if (strpos($agent, 'android')) {
            $type = 'android';
        }
        return $type;
    }
}
