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


class base_enterprise {
    static $enterp;
    static $version;
    static $token;

    /**
     * 设置版本号
     * @param string 版本号
     * @return null
     */
    static function set_version($version = '1.0') {
        self::$version = $version;
    }

    /**
     * 设置token
     * @param string token私钥
     * @return null
     */
    static function set_token($token = '') {
        self::$token = $token;
    }

    /**
     * 存储企业帐号和信息
     * @param mixed - 企业帐号信息
     * @return boolean true or false
     */
    static function set_enterprise_info($arr_enterprise) {
        if (!function_exists('set_enterprise')) {
            app::get('base')->setConf('ecos.enterprise_info', serialize($arr_enterprise));

            return true;
        } else {
            return set_enterprise($arr_enterprise);
        }
    }

    /**
     * 获取企业信息
     * @param string 获取的信息内容
     * @return string 相应的内容
     */
    static function get($code = 'ent_id') {
        if (!function_exists('get_ent_id')) {
            if (self::$enterp === null) {
                if ($serialize_enterp = app::get('base')->getConf('ecos.enterprise_info')) {
                    $enterprise = unserialize($serialize_enterp);
                    self::$enterp = $enterprise;
                }
            }
        } else {
            self::$enterp = get_ent_id();
        }

        return self::$enterp[$code];
    }

    /**
     * 返回企业号
     * @param null
     * @return string
     */
    static function ent_id() { return self::get('ent_id'); }

    /**
     * 返回企业密码
     * @param null
     * @return string
     */
    static function ent_ac() { return self::get('ent_ac'); }

    /**
     * 返回企业邮件
     * @param null
     * @return string
     */
    static function ent_email() { return self::get('ent_email'); }

    /**
     * 生成企业认证URL（供各处复用）
     * @return string
     */
    static function generate_auth_url()
    {
        $entId = self::ent_id();

        // 获取 handshake code
        $code = '';
        base_kvstore::instance('ecos')->fetch('net.handshake', $code);

        // 回调地址
        $callback_base = kernel::base_url(1) . kernel::url_prefix() . '/openapi/entermembercenter_callback/auth';
        $callback_url = $code ? $callback_base . '?code=' . urlencode($code) : $callback_base;

        // 从 deploy.xml 获取版本号，去掉最后一段
        $deploy_info = base_setup_config::deploy_info();
        $full_version = isset($deploy_info['ver']) ? $deploy_info['ver'] : '';
        if ($full_version) {
            $version_parts = explode('.', $full_version);
            array_pop($version_parts);
            $version = implode('.', $version_parts);
        } else {
            $version = '';
        }

        // 组装 signature
        $signature_data = array(
            'identifier'   => $entId ?: '',
            'product_key'  => 'ecos.ome',
            'url'          => kernel::base_url(1),
            'callback_url' => $callback_url,
            'result'       => $code ?: '',
            'version'      => $version,
        );

        $signature = base64_encode(json_encode($signature_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // 获取 node_id
        $node_id = base_shopnode::node_id('ome');
        
        $base = rtrim(ENTERPRISE_APPLY_URL, '/');
        
        // 构建URL参数
        $params = array('signature' => $signature);
        if (!empty($node_id)) {
            $params['node_id'] = $node_id;
        }
        $query_string = http_build_query($params);
        
        // 如果企业已经认证过了（ent_id存在），返回组织列表URL，否则返回认证URL
        // if (!empty($entId)) {
        //     return $base . '/organization/list?' . $query_string;
        // }
        
        return $base . '/system/apply?' . $query_string;
    }
}
