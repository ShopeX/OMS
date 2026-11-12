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
 * 淘宝御城河
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class base_hchsafe
{

    /**
     * 登录日志
     *
     * @return void
     * @author 
     **/

    public function login_log($params)
    {
        if (defined('DEV_ENV')) {
            return;
        }

        kernel::single('erpapi_router_request')->set('hchsafe','taobao')->hchsafe_login($params);
        
        kernel::single('erpapi_router_request')->set('hchsafe','360buy')->hchsafe_login($params);
        kernel::single('erpapi_router_request')->set('hchsafe','luban')->hchsafe_login($params);
    }

    /**
     * 风控风险分析
     *
     * @return void
     * @author 
     **/
    public function compute_risk($params,&$msg,&$extra = null)
    {
        if (defined('DEV_ENV')) {
            return true;
        }
        
        // IDAAS
        $rs = ['rsp' => 'succ'];
        $idaas_enable = app::get('ome')->getConf('pam.passport.idaas.enable');
        if (defined('IDAAS_LOGIN') && true == constant("IDAAS_LOGIN") && $idaas_enable == 'true') {
            $rs = kernel::single('erpapi_router_request')->set('idaas', 'aliyun')->account_login(array (
                'login_name'     => $params['uname'],
                'login_password' => $params['password_string'],
                'member_id'      => $params['member_id'],
            ));
        }
        if($rs['rsp'] == 'succ'){
            $rs = kernel::single('erpapi_router_request')->set('hchsafe','360buy')->hchsafe_computerisk();
        }

        if ($rs['rsp'] == 'fail') $msg = $rs['msg'];
        if ($rs['rsp'] == 'fail' && isset($rs['data']) && is_array($rs['data'])) {
            $extra = $rs['data'];
        }

        return $rs['rsp'] == 'succ' ? true : false;
    }


    /**
     * 订单访问(打印)
     *
     * @return void
     * @author 
     **/
    public function order_log($params)
    {
       
        kernel::single('erpapi_router_request')->set('hchsafe','taobao')->hchsafe_orderdata($params);
        kernel::single('erpapi_router_request')->set('hchsafe','360buy')->hchsafe_orderdata($params);
        kernel::single('erpapi_router_request')->set('hchsafe','luban')->hchsafe_orderdata($params);
    }


    /**
     * 订单访问
     *
     * @return void
     * @author 
     **/
    public function sql_log($params)
    {
        kernel::single('erpapi_router_request')->set('hchsafe','taobao')->hchsafe_sql($params);
    }

    public function order_push_log($params)
    {
        kernel::single('erpapi_router_request')->set('hchsafe','taobao')->hchsafe_orderpush($params);
        kernel::single('erpapi_router_request')->set('hchsafe','360buy')->hchsafe_orderpush($params);
        kernel::single('erpapi_router_request')->set('hchsafe','luban')->hchsafe_orderpush($params);
    }

    public function isVerifyPassed($params) {
        return kernel::single('erpapi_router_request')->set('hchsafe','taobao')->hchsafe_isVerifyPassed($params);
    }
}
