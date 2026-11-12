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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class pam_passport_idaas implements pam_interface_passport
{
    public $loginType = 'IDAAS';

    public function get_name()
    {
        return 'Idaas Account Sign In';
    }

    public function get_login_form($auth, $appid, $view, $ext_pagedata = array())
    {
        return;
    }

    public function login($auth, &$usrdata)
    {
    }

    /** Legacy openapi 入口：二次认证统一走 Vue /login */
    public function callback()
    {
        header('Location:' . kernel::base_url(1) . '/login');
        exit;
    }

    public function loginout($auth, $backurl = "index.php")
    {
    }

    public function get_data()
    {
    }

    public function get_id()
    {
    }

    public function get_expired()
    {
    }

    public function get_config()
    {
    }
}
