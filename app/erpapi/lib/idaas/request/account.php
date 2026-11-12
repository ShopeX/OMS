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

class erpapi_idaas_request_account extends erpapi_idaas_request_abstract
{
    private $_passwordPre = 'S#';

    /**
     * 同步账号
     * @param  [type] $sdf [description]
     * @return [type]      [description]
     */
    public function create($sdf)
    {
        $res = kernel::single('erpapi_router_request')->set('idaas', 'aliyun')->organization_get($sdf);
        if ($res['rsp'] == 'fail' && $res['res'] == 'EntityNotFound') {
            $res = kernel::single('erpapi_router_request')->set('idaas', 'aliyun')->organization_create($sdf);
        }

        if ($res['rsp'] == 'fail') {
            return $res;
        }

        $title = 'IDAAS同步账号';

        $params = array(
            'login_name'     => base_shopnode::node_id('ome') . '.' . $sdf['login_name'],
            'login_password' => $sdf['login_password'],
            'user_name'      => $sdf['user_name'],
            'external_id'    => base_shopnode::node_id('ome') . '_' . $sdf['user_id'],
            'email'          => $sdf['email'],
            'mobile'         => $sdf['mobile'],
            'belongs'        => json_encode(array(base_shopnode::node_id('ome'))),
            'enabled'        => $sdf['enabled'],
            'source'         => $sdf['source'],
            'domain'         => base_request::get_host(),
        );

        if ($params['login_password']) {
            $params['login_password'] = $this->_passwordPre . $params['login_password'];
        }

        $rsp = $this->__caller->call(IDAAS_ACCOUNT_CREATE, $params, array(), $title, 10, $sdf['login_name']);

        return $rsp;
    }

    /**
     * 同步账号
     * @param  [type] $sdf [description]
     * @return [type]      [description]
     */
    public function update($sdf)
    {
        $title = 'IDAAS同步账号';

        $params = array(
            'login_name'     => base_shopnode::node_id('ome') . '.' . $sdf['login_name'],
            'login_password' => $sdf['login_password'],
            'user_name'      => $sdf['user_name'],
            'external_id'    => base_shopnode::node_id('ome') . '_' . $sdf['user_id'],
            'email'          => $sdf['email'],
            'mobile'         => $sdf['mobile'],
            'belongs'        => json_encode(array(base_shopnode::node_id('ome'))),
            'enabled'        => $sdf['enabled'],
        );

        if ($params['login_password']) {
            $params['login_password'] = $this->_passwordPre . $params['login_password'];
        }

        $rsp = $this->__caller->call(IDAAS_ACCOUNT_UPDATE, $params, array(), $title, 10, $sdf['login_name']);

        return $rsp;
    }

    /**
     * 同步账号
     * @param  [type] $sdf [description]
     * @return [type]      [description]
     */
    public function delete($sdf)
    {
        $title = 'IDAAS删除账号';

        $params = array(
            'external_id' => base_shopnode::node_id('ome') . '_' . $sdf['user_id'],
        );

        $rsp = $this->__caller->call(IDAAS_ACCOUNT_DELETE, $params, array(), $title, 10, $sdf['login_name']);

        return $rsp;
    }

    /**
     * 同步账号
     * @param  [type] $sdf [description]
     * @return [type]      [description]
     */
    public function get($sdf)
    {
        $title = 'IDAAS查询账号';

        $params = array(
            'external_id' => base_shopnode::node_id('ome') . '_' . $sdf['user_id'],
        );

        $rsp = $this->__caller->call(IDAAS_ACCOUNT_GET, $params, array(), $title, 10, $sdf['login_name']);

        return $rsp;
    }

    /**
     * 同步账号
     * @param  [type] $sdf [description]
     * @return [type]      [description]
     */
    public function login($sdf)
    {
        if (defined('DEV_ENV')) {
            return array('rsp' => 'succ');
        }
        
        $shopMdl = app::get('ome')->model('shop');
    
        $seller_nick = [];
        foreach ($shopMdl->getList('addon', ['node_type'=>'taobao']) as $v) {
            if ($v['addon']['nickname']) {
                $seller_nick[] = (string)$v['addon']['nickname'];
            }
        }

        $user = app::get('desktop')->model('users')->db_dump(['user_id' => $sdf['member_id']], 'mobile');

        $title = 'IDAAS登陆账号';

        $params = array(
            'login_name'     => base_shopnode::node_id('ome') . '.' . $sdf['login_name'],
            'login_password' => $sdf['login_password'],
            'ati'            => kernel::single('base_component_request')->get_cookie('_ati'),
            'X-Client-IP'    => $_SERVER['HTTP_X_FORWARDED_FOR'] ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'],
            'merchantName'   => implode(',', $seller_nick),

            'loginFrom'         => kernel::this_url(1) . '?' . $_SERVER['QUERY_STRING'],
            'REMOTE_ADDR'       => $_SERVER['HTTP_X_FORWARDED_FOR'] ?: $_SERVER['REMOTE_ADDR'],
            'HTTP_USER_AGENT'   => $_SERVER['HTTP_USER_AGENT'],
            'mobile'            => $user['mobile'],
        );

        if ($params['login_password']) {
            $params['login_password'] = $this->_passwordPre . $params['login_password'];
        }

        if (!empty($sdf['captcha_code'])) {
            $params['captcha_code'] = $sdf['captcha_code'];
        }
        if (isset($sdf['captcha_value']) && $sdf['captcha_value'] !== '') {
            $params['captcha_value'] = $sdf['captcha_value'];
        }

        $rsp = $this->__caller->call(IDAAS_ACCOUNT_LOGIN, $params, array(), $title, 10, $sdf['login_name']);

        return $rsp;
    }

    /**
     * 获取 IDAAS 图形验证码
     *
     * @param array $sdf
     * @return array
     */
    public function captcha($sdf = array())
    {
        $title = 'IDAAS获取图形验证码';

        $params = array();
        if (!empty($sdf['login_name'])) {
            $params['login_name'] = base_shopnode::node_id('ome') . '.' . $sdf['login_name'];
        }

        $rsp = $this->__caller->call(
            IDAAS_ACCOUNT_CAPTCHA,
            $params,
            array(),
            $title,
            10,
            $sdf['login_name'] ?? ''
        );

        return $rsp;
    }

    /**
     * 获取MobileVerifyCode
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getMobileVerifyCode($sdf)
    {
        $title = 'IDAAS获取短信验证码';

        $params = array(
            'username'     => $sdf['login_name'],
            'fid'          => $sdf['fid'],
            'secondFactor' => 'SMS',
        );

        $rsp = $this->__caller->call(IDAAS_ACCOUNT_VERIFY_CODE, $params, array(), $title, 10, $sdf['login_name']);

        return $rsp;
    }

    /**
     * 检查MobileVerifyCode
     * @param mixed $sdf sdf
     * @return mixed 返回验证结果
     */
    public function checkMobileVerifyCode($sdf)
    {
        $title = 'IDAAS验证短信验证码';

        $params = array(
            'username'     => $sdf['login_name'],
            'code'         => $sdf['mobileverifycode'],
            'fid'          => $sdf['fid'],
            'secondFactor' => 'SMS',
            'ip'           => $_SERVER['HTTP_X_FORWARDED_FOR'] ?: $_SERVER["REMOTE_ADDR"],

            'loginFrom'         => kernel::this_url(1) . '?' . $_SERVER['QUERY_STRING'],
            'REMOTE_ADDR'       => $_SERVER['HTTP_X_FORWARDED_FOR'] ?: $_SERVER["REMOTE_ADDR"],
            'HTTP_USER_AGENT'   => $_SERVER['HTTP_USER_AGENT'],
            'mobile'            => $sdf['mobile'] ?? '',
            'merchantName'      => $sdf['merchantName'] ?? '',
        );
        $rsp = $this->__caller->call(IDAAS_ACCOUNT_CODE_VERIFY, $params, array(), $title, 10, $sdf['login_name']);
        return $rsp;
    }
}
