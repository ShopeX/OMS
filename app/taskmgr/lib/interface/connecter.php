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
 * 任务存储介质外部调用接口类
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

//加载配置信息（Web 场景可能由业务方先加载，此处兜底）
if (!defined('__CONNECTER_MODE')) {
    $path = dirname(__FILE__) . '/../../config/config.php';
    if (is_readable($path)) {
        include_once $path;
    }
}

class taskmgr_interface_connecter
{

    private static $_curr_connecter = array();

    public function push($params, &$error_msg = '')
    {
        if (!defined('__CONNECTER_MODE') || !__CONNECTER_MODE) {
            $error_msg = 'taskmgr config missing: __CONNECTER_MODE undefined, check app/taskmgr/config/config.php';
            return false;
        }

        $_fix          = sprintf('__%s_CONFIG', strtoupper(__CONNECTER_MODE));
        $this->_config = $GLOBALS[$_fix];

        if (empty($this->_config) || !is_array($this->_config)) {
            $error_msg = 'taskmgr config missing: $GLOBALS[' . $_fix . '] empty';
            return false;
        }

        if (!$params['data'] || !$params['url'] || !($task_type = $params['data']['task_type'])) {
            $error_msg = 'push params invalid: data/url/task_type required';
            return false;
        }

        $task_list = taskmgr_whitelist::get_all_task_list();
        if (!isset($task_list[$task_type])) {
            $error_msg = 'task_type not in whitelist: ' . $task_type;
            return false;
        }

        try {
            $connecterClass = sprintf('taskmgr_connecter_%s', __CONNECTER_MODE);

            // class_exists 会触发 autoload，可能抛异常，需放在 try 内
            if (!class_exists($connecterClass)) {
                $error_msg = 'connecter class not found: ' . $connecterClass;
                return false;
            }

            if (!isset(self::$_curr_connecter[$task_type])) {
                $connecter = new $connecterClass();
                // load 失败时不能缓存，否则后续 push 都会假成功
                if (!$connecter->load($task_type, $this->_config)) {
                    $error_msg = sprintf('%s load/connect failed, host=%s port=%s',
                        __CONNECTER_MODE,
                        isset($this->_config['HOST']) ? $this->_config['HOST'] : '',
                        isset($this->_config['PORT']) ? $this->_config['PORT'] : ''
                    );
                    return false;
                }
                self::$_curr_connecter[$task_type] = $connecter;
            } else {
                $connecter = self::$_curr_connecter[$task_type];
            }

            //验签生成，数据压缩
            $params['data']['taskmgr_sign'] = taskmgr_rpc_sign::gen_sign($params['data']);
            $msg                            = json_encode($params);

            $routerKey = sprintf('erp.task.%s.*', $task_type);
            //$connecter->disconnect();

            if (!$connecter->publish($msg, $routerKey)) {
                $error_msg = sprintf('%s publish failed, task_type=%s routerKey=%s', __CONNECTER_MODE, $task_type, $routerKey);
                return false;
            }

            return true;
        } catch (Exception $e) {
            $error_msg = __CONNECTER_MODE . ' exception: ' . $e->getMessage();
            return false;
        }
    }
}
