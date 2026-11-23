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
 * @license  http://ecos.shopex.com/license/gpl GPL License
 */
require_once(dirname(__FILE__) . '/lib/db/compat.php');
class base_task {

    function install_options() {

        $_https_default = ($_SERVER['HTTPS'] == 'on') ? "On" : "Off";
        if (ECAE_MODE) {

            return array(
                'db_host'            => array('type' => 'text', 'vtype' => 'required', 'required' => true, 'title' => app::get('base')->_('数据库主机'), 'value' => ECAE_MYSQL_HOST_M, 'readonly' => ''),
                'db_user'            => array('type' => 'text', 'vtype' => 'required', 'required' => true, 'title' => app::get('base')->_('数据库用户名'), 'value' => ECAE_MYSQL_USER, 'readonly' => ''),
                'db_password'        => array('type' => 'password', 'title' => app::get('base')->_('数据库密码'), 'value' => ECAE_MYSQL_PASS, 'readonly' => ''),
                'db_name'            => array('type' => 'text', 'vtype' => 'required', 'required' => true, 'title' => app::get('base')->_('数据库名'), 'value' => ECAE_MYSQL_DB, 'readonly' => ''),
                'db_prefix'          => array('type' => 'text', 'title' => app::get('base')->_('数据库表前缀'), 'default' => 'sdb_'),
                'default_timezone'   => array('type'  => 'select', 'options' => base_location::timezone_list(),
                                              'title' => app::get('base')->_('默认时区'), 'default' => '8', 'vtype' => 'required', 'required' => true),
                'with_https_support' => array('type'    => 'select', 'options' => array('Off' => '关闭', 'On' => '开启'), 'title' => app::get('base')->_('SSL安全支持'),
                                              'default' => $_https_default, 'vtype' => 'required', 'required' => true),
            );
        } else {

            return array(
                'db_host'            => array('type' => 'text', 'vtype' => 'required', 'required' => true, 'title' => app::get('base')->_('数据库主机'), 'default' => 'localhost'),
                'db_user'            => array('type' => 'text', 'vtype' => 'required', 'required' => true, 'title' => app::get('base')->_('数据库用户名'), 'default' => 'root'),
                'db_password'        => array('type' => 'password', 'title' => app::get('base')->_('数据库密码'), 'default' => ''),
                'db_name'            => array('type' => 'select', 'vtype' => 'required', 'required' => true, 'title' => app::get('base')->_('数据库名'), 'options_callback' => array('app' => 'base', 'method' => 'dbnames'), 'onfocus' => 'setuptools.getdata(\'base\', \'dbnames\', this);'),
                'db_prefix'          => array('type' => 'text', 'title' => app::get('base')->_('数据库表前缀'), 'default' => 'sdb_'),
                'default_timezone'   => array('type'  => 'select', 'options' => base_location::timezone_list(),
                                              'title' => app::get('base')->_('默认时区'), 'default' => '8', 'vtype' => 'required', 'required' => true),
                'with_https_support' => array('type'    => 'select', 'options' => array('Off' => '关闭', 'On' => '开启'), 'title' => app::get('base')->_('SSL安全支持'),
                                              'default' => $_https_default, 'vtype' => 'required', 'required' => true),//'ceti_identifier'=>array('type'=>'text','vtype'=>'required','required'=>true,'title'=>app::get('base')->_('电子邮箱或企业帐号'),'default'=>''),
            );
        }
    }

    function dbnames($options) {
        $options = $options['base'];
        $link = @mysql_connect($options['db_host'], $options['db_user'], $options['db_password']);
        if (!$link) {
            return array();
        } else {
            if (function_exists('mysql_list_dbs')) {
                $db_list = mysql_list_dbs($link);
            } else {
                $db_list = mysql_query('SHOW databases');
            }//todo: 加强兼容性
            $i = 0;
            $cnt = mysql_num_rows($db_list);
            $rows = array();
            while ($i < $cnt) {
                $dbname = trim(mysql_result($db_list, $i++));
                $rows[$dbname] = $dbname;
            }

            return $rows;
        }
    }//End Function

    function checkenv($options) {

        if (!$options['db_host']) {
            echo app::get('base')->_("Error: 需要填写数据库主机") . "\n";

            return false;
        }
        if (!$options['db_user']) {
            echo app::get('base')->_("Error: 需要填写数据库用户名") . "\n";

            return false;
        }
        if (!$options['db_name']) {
            echo app::get('base')->_("Error: 请选择数据库") . "\n";

            return false;
        }
        if ($options['db_prefix'] && $options['db_prefix'] != 'sdb_' && strpos($options['db_prefix'], 'sdb_') === 0) {
            echo app::get('base')->_("Error: 数据库前缀不支持以'sdb_'开头的两级划分，可改用例如") . "'tbl_" . substr($options['db_prefix'], 4) . "'" . app::get('base')->_("为数据库前缀") . "\n";

            return false;
        }

        $link = @mysql_connect($options['db_host'], $options['db_user'], $options['db_password']);
        if (!$link) {
            echo app::get('base')->_("Error: 数据库连接错误") . "\n";

            return false;
        }

        $mysql_ver = mysql_get_server_info($link);
        if (!version_compare($mysql_ver, '4.1', '>=')) {
            echo app::get('base')->_("Error: 数据库需高于4.1的版本") . "\n";

            return false;
        }

        if (!mysql_select_db($options['db_name'], $link)) {
            echo app::get('base')->_("Error: 数据库") . "\"" . $options['db_name'] . "\"" . app::get('base')->_("不存在") . "\n";

            return false;
        }

        if (ECAE_MODE) {
            if (!file_exists(ROOT_DIR . '/config/config.php')) {
                echo app::get('base')->_("Error: 没有找到config文件，ECAE环境下请将base/example下的ecae.config.php改名为config.php放入config文件夹") . "\n";

                return false;
            }
        } else {
            if (!kernel::single('base_setup_config')->write($options)) {
                echo app::get('base')->_("Error: Config文件写入错误") . "\n";

                return false;
            }
        }//todo 

        if (file_exists(ROOT_DIR . '/config/config.php')) {
            require(ROOT_DIR . '/config/config.php');
        }

        date_default_timezone_set(
            defined('DEFAULT_TIMEZONE') ? ('Etc/GMT' . (DEFAULT_TIMEZONE >= 0 ? (DEFAULT_TIMEZONE * -1) : '+' . (DEFAULT_TIMEZONE * -1))) : 'UTC'
        );

        return true;
    }

    function pre_install() {
        kernel::set_online(false);
        // base_certificate::active();
    }

    function post_install() {
        kernel::single('base_application_manage')->sync();
        kernel::set_online(true);
        $rpc_server = array(
            'node_id'     => '1',
            'node_url'    => MATRIX_URL, //todo 测试
            'node_name'   => 'Global Matrix',
            'node_api'    => '',
            'link_status' => 'active',
        );
        app::get('base')->model('network')->replace($rpc_server, array('node_id' => 1));
    }

    function post_update($dbinfo) {
        $dbver = $dbinfo['dbver'];
        if (empty($dbver) || $dbver == '0.1') {
            app::get('base')->model('cache_expires')->delete(array());
            $rows = app::get('base')->model('apps')->getList('app_id', array('installed' => 1));
            $content_detectors['list'] = array('base_application_cache_expires');
            $service = new service($content_detectors);
            foreach ($rows as $row) {
                foreach ($service as $detector) {
                    foreach ($detector->detect(app::get($row['app_id'])) as $name => $item) {
                        $item->install();
                    }
                }
            }
            cachemgr::clean($msg);  //清空缓存
            kernel::log('cache expiers update');
        }//变更cache_expires结构及数据，0.1版本前存在的问题包括0.1

        if ($dbver && $dbver == '0.12') {
            // 升级版本，清理原来rpcpoll表里面的大量冗余数据。
            app::get('base')->model('rpcpoll')->delete(array('type' => 'response'));
        } elseif ($dbver && $dbver == '0.13') {
            // 0.13-0.14版本历史性的解决下openapi的bug-修改数据互联callback_url的地址.
            // 将原来的api->openapi.
            $params = array(
                'app'     => 'app.updateRelCallbackUrl',
                'cert_id' => base_certificate::get('certificate_id'),
            );
            $token = base_certificate::get('token');
            $str = '';
            ksort($params);
            foreach ($params as $key => $value) {
                $str .= $value;
            }
            $params['certi_ac'] = md5($str . $token);
            $http = kernel::single('base_httpclient');
            $http->timeout = 6;
            $result = $http->post(
                MATRIX_RELATION_URL . '/api.php',
                $params
            );
        }

    }//End Function

}
