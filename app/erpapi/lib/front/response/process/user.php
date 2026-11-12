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
 * 管理员处理类
 *
 * @author <chenping@shopex.cn>
 * @time 2020-11-18T19:56:04+08:00
 */
class erpapi_front_response_process_user
{
    /**
     * 管理员登陆
     *
     * @return void
     * @author
     **/

    public function login($data)
    {
        $_POST['uname']    = $data['uname'];
        $_POST['password'] = $data['password'];

        $account_type = pam_account::get_account_type('desktop');
        $auth         = pam_auth::instance($account_type);

        $auth->set_appid('desktop');
        $auth->set_enable_vcode(false);

        $passport_module = kernel::single('pam_passport_basic');
        $module_uid      = $passport_module->login($auth, $auth_data);

        if (!$module_uid) {
            return array('rsp' => 'fail', 'msg' => '登陆失败：' . $_SESSION['error']);
        }

        // SESSION auth writes removed - JWT should be implemented as primary auth for ERPAPI
        // $_SESSION['account'][$account_type] = $module_uid;
        // $_SESSION['type'] = $account_type;
        // $_SESSION['login_time'] = time();

        $params = array(
            'member_id' => $module_uid,
            'type'      => $account_type,
        );
        foreach (kernel::servicelist('pam_login_listener') as $service) {
            $service->listener_login($params);
        }

        $log = array(
            'event_time' => time(),
            'event_type' => $account_type,
            'event_data' => base_request::get_remote_addr() . ':' . $auth_data['log_data'],

        );
        app::get('pam')->model('log')->insert($log);

        $rData = array(
            'uname'   => $data['uname'],
            'node_id' => kernel::single('base_session')->sess_id(),
        );

        // 如果有门店权限
        if (app::get('o2o')->is_installed()) {
            $branch_ops = app::get('o2o')->model('branch_ops')->dump(array('op_id' => $module_uid));

            if ($branch_ops) {
                $branch = app::get('ome')->model('branch')->dump(array(
                    'branch_id'        => $branch_ops['branch_id'],
                    'check_permission' => 'false',
                ), 'branch_bn,name,branch_id');

                $rData['store_bn']   = $branch['branch_bn'];
                $rData['store_name'] = $branch['name'];

                // 获取门店对应的大区
                $orgMdl = app::get('organization')->model('organization');
                $parent = $orgMdl->get_first_parent($branch['branch_bn']);

                $rData['org_no']   = $parent['org_no'];
                $rData['org_name'] = $parent['org_name'];

                $_SESSION['branch_id'] = $branch['branch_id'];
                $_SESSION['org_no']    = $parent['org_no'];
            }
        }

        return array('rsp' => 'succ', 'data' => $rData, 'msg' => '登陆成功');
    }

    /**
     * 管理员登出
     *
     * @return void
     * @author
     **/
    public function logout($data)
    {
        kernel::single('base_session')->destory();

        return array('rsp' => 'succ', 'data' => array(), 'msg' => '登出成功');
    }
}
