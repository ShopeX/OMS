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
 * 核心方法及类
 */

function _eogo_auto_load($class_name)
{

    $class_name = strip_tags($class_name);
    $trait = false;
    if(strpos($class_name, 'trait') === 0) {
        $trait = true;
        $class_name = substr($class_name, 6);
    }
    $p          = strpos($class_name, '_');

    if ($p) {
        $owner      = substr($class_name, 0, $p);
        $class_name = substr($class_name, $p + 1);
        $tick       = substr($class_name, 0, 4);
        switch ($tick) {
            case 'ctl_':
                if($trait) {
                    $path = TRAIT_DIR . '/' . $owner . '/controller/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                } elseif (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/' . $owner . '/controller/' . str_replace('_', '/', substr($class_name, 4)) . '.php')) {
                    $path = CUSTOM_CORE_DIR . '/' . $owner . '/controller/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                } else {
                    $path = APP_DIR . '/' . $owner . '/controller/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                }
                if (file_exists($path)) {
                    return require_once $path;
                } else {
                    throw new exception('Don\'t find controller file');
                    exit;
                }
            case 'mdl_':
                if($trait) {
                    $path = TRAIT_DIR . '/' . $owner . '/model/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                } elseif (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/' . $owner . '/model/' . str_replace('_', '/', substr($class_name, 4)) . '.php')) {
                    $path = CUSTOM_CORE_DIR . '/' . $owner . '/model/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                } else {
                    $path = APP_DIR . '/' . $owner . '/model/' . str_replace('_', '/', substr($class_name, 4)) . '.php';
                }
                if (file_exists($path)) {
                    return require_once $path;
                } elseif (file_exists(APP_DIR . '/' . $owner . '/dbschema/' . substr($class_name, 4) . '.php') || file_exists(CUSTOM_CORE_DIR . '/' . $owner . '/dbschema/' . substr($class_name, 4) . '.php')) {
                    $parent_model_class = app::get($owner)->get_parent_model_class();
                    eval("class {$owner}_{$class_name} extends {$parent_model_class}{ }");
                    return true;
                } else {
                    throw new exception('Don\'t find model file "' . $class_name . '"');
                    exit;
                }
            default:
                if($trait) {
                    $path = TRAIT_DIR . '/' . $owner . '/lib/' . str_replace('_', '/', $class_name) . '.php';
                } elseif (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/' . $owner . '/lib/' . str_replace('_', '/', $class_name) . '.php')) {
                    $path = CUSTOM_CORE_DIR . '/' . $owner . '/lib/' . str_replace('_', '/', $class_name) . '.php';
                } else {
                    $path = APP_DIR . '/' . $owner . '/lib/' . str_replace('_', '/', $class_name) . '.php';
                }
                if (file_exists($path)) {
                    return require_once $path;
                } else {
                    throw new exception('Don\'t find lib file "' . $class_name . '"');
                    return false;
                }
        }
    } elseif (file_exists($path = APP_DIR . '/base/lib/static/' . $class_name . '.php')) {
        if($trait) {
            $path = TRAIT_DIR . '/base/lib/static/' . $class_name . '.php';
        } elseif (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/base/lib/static/' . $class_name . '.php')) {
            $path = CUSTOM_CORE_DIR . '/base/lib/static/' . $class_name . '.php';
        }
        return require_once $path;
    } else {
        throw new exception('Don\'t find static file "' . $class_name . '"');
        return false;
    }
}

class base_rpc_caller
{

    public $timeout = 10;
    //增加对实时接口方式的处理逻辑
    public $realtime = false;

    public function __construct(&$app, $node_id, $version)
    {
        $this->network_id          = $node_id;
        $this->app                 = $app;
        $this->api_request_version = $version;
    }

    private function begin_transaction($method, $params, $rpc_id = null)
    {
        $obj_rpc_poll = app::get('base')->model('rpcpoll');
        if (is_null($rpc_id)) {
            $time      = time();
            $microtime = utils::microtime();
            $rpc_id    = str_replace('.', '', strval($microtime));
            //mt_srand($microtime); //自PHP4.2.0起，不再需要随机数发生器播种。如果播种的话会导致同一时间生成的随机数相同，并发时插入数据失败
            $randval = mt_rand();
            $rpc_id .= strval($randval);
            //$rpc_id = rand(0,$microtime);

            $data = array(
                'id'              => $rpc_id,
                'network'         => $this->network_id,
                'calltime'        => $time,
                'method'          => $method,
                'params'          => serialize($params),
                'type'            => 'request',
                'callback'        => $this->callback_class . ':' . $this->callback_method,
                'callback_params' => serialize($this->callback_params),
            );
            $rpc_id = $rpc_id . '-' . $time;

            // $obj_rpc_poll->insert($data);
            $obj_rpc_poll->insertRpc($data, $rpc_id);
        } else {
            // $arr_pk = explode('-', $rpc_id);
            // $rpc_id = $arr_pk[0];
            // $rpc_calltime = $arr_pk[1];
            // $tmp = $obj_rpc_poll->getList('*', array('id'=>$rpc_id,'calltime'=>$rpc_calltime));
            $tmp = $obj_rpc_poll->getRpc($rpc_id);

            if ($tmp) {
                $data = array(
                    'fail_times' => $tmp[0]['fail_times'] + 1,
                );
                // $fiter = array(
                //     'id'=>$rpc_id,
                //     'calltime'=>$rpc_calltime,
                // );

                // $obj_rpc_poll->update($data,$fiter);
                $obj_rpc_poll->updateRpc($data, $rpc_id);
            }
            // $rpc_id = $rpc_id.'-'.$rpc_calltime;
        }
        return $rpc_id;
    }

    private function get_url($node)
    {
        $row = app::get('base')->model('network')->getlist('node_url,node_api', array('node_id' => $this->network_id));
        if ($row) {
            if (substr($row[0]['node_url'], -1, 1) != '/') {
                $row[0]['node_url'] = $row[0]['node_url'] . '/';
            }
            if ($row[0]['node_api'][0] == '/') {
                $row[0]['node_api'] = substr($row[0]['node_api'], 1);
            }
            $url = $row[0]['node_url'] . $row[0]['node_api'];
        }

        return $url;
    }

    public function call($method, $params, $rpc_id = null, $gzip = false)
    {

        if ($this->realtime) {

            return $this->realtime_call($method, $params);
        } else {

            return $this->async_call($method, $params, $rpc_id, $gzip);
        }
    }

    public function realtime_call($method, $params, $gzip = false)
    {

        $headers = array(
            'Connection' => $this->timeout,
        );
        if ($gzip) {
            $headers['Content-Encoding'] = 'gzip';
        }

        $query_params = array(
            'app_id'       => 'ecos.' . $this->app->app_id,
            'method'       => $method,
            'date'         => date('Y-m-d H:i:s'),
            //'callback_url' => kernel::openapi_url('openapi.rpc_callback', 'async_result_handler', array('id' => $rpc_id)),
            'format'       => 'json',
            'certi_id'     => base_certificate::certi_id(),
            'v'            => $this->api_version($method),
            'from_node_id' => base_shopnode::node_id($this->app->app_id),
        );

        $query_params         = array_merge((array) $params, $query_params);
        $query_params['sign'] = base_certificate::gen_sign($query_params);

        $url = $this->get_url($this->network_id);
        //实时接口，增加realtime后缀
        $url = $url . 'sync';

        if (app::get('commerce')->is_installed()) {
            $set = kernel::single('commerce_network')->set();
            if ($set) {
                $core_http = kernel::single('commerce_network')->conn();
                unset($query_params['sign']);
                $query_params['node_id'] = $query_params['from_node_id'] . '_' . $query_params['to_node_id'];

                $response = $core_http->post('/api/matrix/sync', $query_params);

            } else {
                $core_http = kernel::single('base_httpclient');
                $response  = $core_http->set_timeout($this->timeout)->post($url, $query_params, $headers);

            }

        } else {
            $core_http = kernel::single('base_httpclient');
            $response  = $core_http->set_timeout($this->timeout)->post($url, $query_params, $headers);

        }

        if ($response === HTTP_TIME_OUT) {
            $result->rsp       = 'fail';
            $result->err_msg   = '请求超时';
            $result->res_ltype = 1;
            return $result;
        } else {
            $result = json_decode($response);
            if ($result) {
                $result->res_ltype = 0;
                $this->error       = $response->error;
                return $result;
            } else {
                $result->rsp       = 'fail';
                $result->err_msg   = '返回信息异常:' . $response;
                $result->res_ltype = 2;
                return $result;
            }
        }
    }

    public function async_call($method, $params, $rpc_id = null, $gzip = false)
    {
        if (is_null($rpc_id)) {
            $rpc_id = $this->begin_transaction($method, $params);
        } else {
            $rpc_id = $this->begin_transaction($method, $params, $rpc_id);
        }
        $obj_rpc_poll = app::get('base')->model('rpcpoll');
        $headers      = array(
            'Connection' => $this->timeout,
        );
        if ($gzip) {
            $headers['Content-Encoding'] = 'gzip';
        }

        $query_params = array(
            'app_id'       => 'ecos.' . $this->app->app_id,
            'method'       => $method,
            'date'         => date('Y-m-d H:i:s'),
            'callback_url' => self::openapi_url('openapi.rpc_callback', 'async_result_handler', array('id' => $rpc_id, 'app_id' => $this->app->app_id)),
            'format'       => 'json',
            'certi_id'     => base_certificate::certi_id(),
            'v'            => $this->api_version($method),
            'from_node_id' => base_shopnode::node_id($this->app->app_id),
        );

        // rpc_id 分id 和 calltime
        $arr_rpc_key          = explode('-', $rpc_id);
        $rpc_id               = $arr_rpc_key[0];
        $rpc_calltime         = $arr_rpc_key[1];
        $query_params['task'] = $rpc_id;
        $query_params         = array_merge((array) $params, $query_params);
        if (!base_shopnode::token($this->app->app_id)) {
            $query_params['sign'] = base_certificate::gen_sign($query_params);
        } else {
            $query_params['sign'] = base_shopnode::gen_sign($query_params, $this->app->app_id);
        }

        $url = $this->get_url($this->network_id);

        if (app::get('commerce')->is_installed()) {
            $set = kernel::single('commerce_network')->set();
            if ($set) {
                $core_http = kernel::single('commerce_network')->conn();
                unset($query_params['sign']);
                $query_params['node_id'] = $query_params['from_node_id'] . '_' . $query_params['to_node_id'];

                $response = $core_http->post('/api/matrix/async', $query_params);
            } else {
                $core_http = kernel::single('base_httpclient');
                $response  = $core_http->set_timeout($this->timeout)->post($url, $query_params, $headers);
            }

        } else {

            $core_http = kernel::single('base_httpclient');
            $response  = $core_http->set_timeout($this->timeout)->post($url, $query_params, $headers);

        }

        if ($this->callback_class && method_exists(kernel::single($this->callback_class), 'response_log')) {
            $response_log_func = 'response_log';
            $callback_params   = $this->callback_params ? array_merge($this->callback_params, array('rpc_key' => $rpc_id . '-' . $rpc_calltime)) : array('rpc_key' => $rpc_id . '-' . $rpc_calltime);
            kernel::single($this->callback_class)->$response_log_func($response, $callback_params);

            //请求参数与msg_id信息记录到缓存
            /*
        $paramsCacheLib = kernel::single('taoexlib_params_cache');
        $tmp_result = json_decode($response,true);
        $paramsCacheLib->store($callback_params['log_id'], serialize(array($method, $params, array($this->callback_class,$this->callback_method,$this->callback_params), array('msg_id'=>$tmp_result['msg_id']))));
        $paramsCacheLib->connClose();*/
        }

        if ($response === HTTP_TIME_OUT) {
            $headers = $core_http->responseHeader;
            kernel::log('Request timeout, process-id is ' . $headers['process-id']);
            // $obj_rpc_poll->update(array('process_id'=>$headers['process-id']),array('id'=>$rpc_id,'calltime'=>$rpc_calltime,'type'=>'request'));
            $obj_rpc_poll->updateRpc(array('process_id' => $headers['process-id']), $rpc_id . '-' . $rpc_calltime);

            $this->status = RPC_RST_RUNNING;
            return false;
        } else {
            $result = json_decode($response);
            if ($result) {
                $this->error = $response->error;
                switch ($result->rsp) {
                    case 'running':
                        $this->status = RPC_RST_RUNNING;
                        // 存入中心给的process-id也就是msg-id
                        // $obj_rpc_poll->update(array('process_id'=>$result->msg_id),array('id'=>$rpc_id,'type'=>'request','calltime'=>$rpc_calltime));
                        $obj_rpc_poll->updateRpc(array('process_id' => $result->msg_id), $rpc_id . '-' . $rpc_calltime);

                        return true;

                    case 'succ':
                        // $obj_rpc_poll->delete(array('id'=>$rpc_id,'calltime'=>$rpc_calltime,'type'=>'request','fail_times'=>1));
                        $obj_rpc_poll->deleteRpc($rpc_id . '-' . $rpc_calltime);

                        $this->status = RPC_RST_FINISH;
                        $method       = $this->callback_method;
                        if ($method && $this->callback_class) {
                            kernel::single($this->callback_class)->$method($result->data);
                        }

                        $this->rpc_response = $response;
                        return $result->data;

                    case 'fail':
                        $this->error        = 'Bad response';
                        $this->status       = RPC_RST_ERROR;
                        $this->rpc_response = $response;
                        return false;
                }
            } else {
                //error 解码失败
            }
        }
    }

    public function set_callback($callback_class, $callback_method, $callback_params = null)
    {
        $this->callback_class  = $callback_class;
        $this->callback_method = $callback_method;
        $this->callback_params = $callback_params;
        return $this;
    }

    public function set_timeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function set_api_version($version)
    {
        $this->api_request_version = $version;
    }

    public function set_realtime($realtime)
    {

        $this->realtime = $realtime;
        return $this;
    }

    private function api_version($method)
    {return $this->api_request_version;}

    public static function openapi_url($openapi_service_name, $method = 'access', $params = null)
    {
        if (substr($openapi_service_name, 0, 8) != 'openapi.') {
            trigger_error('$openapi_service_name must start with: openapi.');
            return false;
        }
        $arg = array();
        foreach ((array) $params as $k => $v) {
            $arg[] = urlencode($k);
            $arg[] = urlencode(str_replace('/', '%2F', $v));
        }
        $callback_url = kernel::base_url(1) . kernel::url_prefix() . '/openapi/' . substr($openapi_service_name, 8) . '/' . $method . '/' . implode('/', $arg);
        if (app::get('commerce')->is_installed()) {
            $set = kernel::single('commerce_network')->set();
            if ($set) {
                $callback_url = $set['callback_url'] . kernel::url_prefix() . '/openapi/' . substr($openapi_service_name, 8) . '/' . $method . '/' . implode('/', $arg);
            }

        }
        return $callback_url;
    }
}

class base_rpc_service
{

    private $start_time;
    private $path   = array();
    private $finish = false;
    static $node_id;

    public function process($path)
    {

        if (!kernel::is_online()) {
            die('error');
        } 

        if ($path == '/api') {
            $this->process_rpc();
        } else {
            if (strpos($path, '/openapi') !== false) {
                $args         = explode('/', substr($path, 9));
                $service_name = 'openapi.' . array_shift($args);
                $method       = array_shift($args);
                foreach ($args as $i => $v) {
                    if ($i % 2) {
                        $params[$k] = str_replace('%2F', '/', $v);
                    } else {
                        $k = $v;
                    }
                }

                kernel::service($service_name)->$method($params);
            }
        }
    }

    private function begin()
    {
        register_shutdown_function(array(&$this, 'shutdown'));
        array_push($this->path, $key);
        @ob_start();
    } //End Function

    private function end($shutdown = false)
    {
        if ($this->path) {
            $this->finish = true;
            $content      = ob_get_contents();
            ob_end_clean();
            $name = array_pop($this->path);
            if (defined('SHOP_DEVELOPER')) {
                error_log("\n\n" . str_pad(@date(DATE_RFC822) . ' ', 60, '-') . "\n" . $content
                    , 3, ROOT_DIR . '/data/logs/trace.' . $name . '.log');
            }
            if ($shutdown) {
                echo json_encode(array(
                    'rsp'  => 'fail',
                    'res'  => $content,
                    'data' => null,
                ));
                exit;
            }
            return $content;
        }
    }

    public function shutdown()
    {
        $this->end(true);
    }

    //app_id     String     Y     分配的APP_KEY
    //method     String     Y     api接口名称
    //date     string     Y     时间戳，为datetime格式
    //format     string     Y     响应格式，xml[暂无],json
    //certi_id     int     Y     分配证书ID
    //v     string     Y     API接口版本号
    //sign     string     Y     签名，见生成sign
    private function parse_rpc_request($request)
    {

        $sign = $request['sign'];
        unset($request['sign']);

        //注销多余的垃圾参数（fails 为 taskmgr 重试计数，不能参与业务验签）
        unset($request['_FROM_MQ_QUEUE'], $request['task_id'], $request['rpc_id'], $request['task_type'], $request['taskmgr_sign'], $request['fails']);

        $app_id = $request['app_id'];

        if ($app_id) {
            $app_id = substr($app_id, strpos($app_id, '.') + 1, strlen($app_id));
        } else {
            //如果不存在app_id的话,取系统主应用的main_app.
            $app_exclusion = app::get('base')->getConf('system.main_app');
            $app_id        = $app_exclusion['app_id'];
        }

        if (!base_shopnode::token($app_id)) {
            $sign_check = base_certificate::gen_sign($request);
        } else {
            $sign_check = base_shopnode::gen_sign($request, $app_id);
        }

        if ($sign != $sign_check) {
            //trigger_error('sign error',E_USER_ERROR);
            $this->send_user_error('4003', 'sign error');
            return false;
        }

        $system_params = array('app_id', 'method', 'date', 'format', 'certi_id', 'v', 'sign', 'node_id');
        foreach ($system_params as $name) {
            $call[$name] = $request[$name];
            unset($request[$name]);
        }

        //if method request = 'aaa.bbb.ccc.ddd'
        //then: object_service = api.aaa.bbb.ccc, method=ddd
        if (isset($call['method'][2])) {
            if ($p = strrpos($call['method'], '.')) {
                $service = 'api.' . substr($call['method'], 0, $p);
                $method  = substr($call['method'], $p + 1);
            }
        } else {
            //trigger_error('error method',E_ERROR);
            $this->send_user_error('4001', 'error method');
            return false;
        }

        if ($call['node_id']) {
            self::$node_id = $call['node_id'];
        }

        return array($service, $method, $request);
    }

    private function gen_uniq_process_id()
    {
        return uniqid();
    }

    private function _process_rpc_mq()
    {
        $this->begin(__FUNCTION__);

        $push_data = array(
            'task_id'        => $this->rpc_id,
            '_FROM_MQ_QUEUE' => 'true',
            'task_type'      => 'orderrpc',
        );
        $push_data = array_merge($push_data, $_REQUEST);

        taskmgr_func::publishMsg($push_data, 'api', $_REQUEST['order_bn'], [
            'SAAS_MQ'           => defined('SAAS_RPCORDER_MQ') ? constant('SAAS_RPCORDER_MQ') : '',
            'queue_config'      => $GLOBALS['_MQ_RPCORDER_CONFIG'],
            'queue_exchange'    => 'TG_RPCORDER_EXCHANGE',
            'queue_name'        => 'TG_RPCORDER_QUEUE',
            'routerKey'         => 'tg.order.rpc.*',
        ]);

        //callback请求进队列
        // $push_params = array(
        //     'data' => $push_data,
        //     'url'  => kernel::openapi_url('openapi.autotask', 'service'),
        // );
        // kernel::single('taskmgr_interface_connecter')->push($push_params);

        $this->end();

        if (!$return) {
            $return = array(
                'rsp'  => 'succ',
                'data' => array('msg' => 'the order rpc is into mq now !!!'),
                'res'  => '',
            );
        }

        echo json_encode($return);
        exit;
    }

    private function process_rpc()
    {

        ignore_user_abort(true);
        set_time_limit(0);
        $this->process_id = $this->gen_uniq_process_id();
        header('Process-id: ' . $this->process_id);
        header('Connection: close');
        flush();

        if (strtolower($_SERVER['HTTP_CONTENT_ENCODING']) == 'gzip') {
            $_input = fopen('php://input', 'rb');
            while (!feof($_input)) {
                $_post .= fgets($_input);
            }
            fclose($_input);
            $_post = utils::gzdecode($_post);
            parse_str($_post, $post);
            if ($post) {
                $_REQUEST = array_merge($_GET, $post);
            }
        } //todo: uncompress post data

        $this->rpc_id = $_REQUEST['task'] ? $_REQUEST['task'] : md5($_REQUEST['task'] . $_REQUEST['sign'] . $_REQUEST['method'] . $_SERVER['HTTP_HOST']);

        //判断是否是订单请求入队列
        $order_rpc_mq = app::get('ome')->getConf('ome.orderrpc.mq');
        if ($order_rpc_mq == 'true' && !isset($_REQUEST['_FROM_MQ_QUEUE']) && $_REQUEST['_FROM_MQ_QUEUE'] != 'true' && $_REQUEST['method'] == 'ome.order.add') {
            $this->_process_rpc_mq();
        }

        $this->begin(__FUNCTION__);
        set_error_handler(array(&$this, 'error_handle'), E_ERROR);
        set_error_handler(array(&$this, 'user_error_handle'), E_USER_ERROR);

        $this->start_time                = $_SERVER['REQUEST_TIME'] ? $_SERVER['REQUEST_TIME'] : time();
        list($service, $method, $params) = $this->parse_rpc_request($_REQUEST);

        $data = array(
            'id'         => $this->rpc_id,
            'network'    => $this->network, //要读到来源，要加密
            'method'     => $service,
            'calltime'   => $this->start_time,
            'params'     => serialize($params),
            'type'       => 'response',
            'process_id' => $this->process_id,
            'callback'   => $_SERVER['HTTP_CALLBACK'],
        );
        $obj_rpc_poll = app::get('base')->model('rpcpoll');
        // 防止多次重刷.
        $rpcpoll = $obj_rpc_poll->getRpc($this->rpc_id, 'response');
        if (!$rpcpoll) {
            // $obj_rpc_poll->insert($data);
            $obj_rpc_poll->insertRpc($data, $this->rpc_id, 'response', 120);

            if ($erpapiMethod = erpapi_router_mapping::rspServiceMapping($service, $_REQUEST['method'], $_REQUEST['node_id'])) {
                $result = kernel::single('erpapi_router_response')
                    ->set_node_id($_REQUEST['node_id'])
                    ->set_api_name($erpapiMethod)
                    ->dispatch($_REQUEST);
                
                if ($result['rsp'] == 'fail') {
                    $this->send_user_error('4007', $result['msg']??'', $result['data']??new stdClass());
                }

            } else {
                $object = kernel::service($service);
                $result = $object->$method($params, $this);
            }
            $output = $this->end();
        } else {
            $output = $this->end();
            $output = app::get('base')->_('该请求已经处理，不能在处理了！');
        }
        $result_json = array(
            'rsp'  => 'succ',
            'data' => $result,
            'res'  => strip_tags($output),
        );

        $this->rpc_response_end($result, $this->rpc_id, $result_json);
        echo json_encode($result_json);
    }

    private function rpc_response_end($result, $rpc_id, $result_json)
    {
        if (isset($rpc_id) && $rpc_id) {
            $connection_aborted = $this->connection_aborted();
            $obj_rpc_poll       = app::get('base')->model('rpcpoll');

            if ($connection_aborted) {
                // $obj_rpc_poll->update(array('result'=>$result),array('process_id'=>$process_id,'type'=>'response'));
                $obj_rpc_poll->updateRpc(array('result' => $result), $rpc_id, 'response');
                if ($_SERVER['HTTP_CALLBACK']) {
                    $return = kernel::single('base_httpclient')->get($_SERVER['HTTP_CALLBACK'] . '?' . json_encode($result_json));
                    $return = json_decode($return);
                    if ($return->result == 'ok') {
                        // $obj_rpc_poll->delete(array('process_id'=>$process_id,'type'=>'response'));
                        $obj_rpc_poll->deleteRpc($rpc_id, 'response');
                    }
                } else {
                    // $obj_rpc_poll->delete(array('process_id'=>$process_id,'type'=>'response'));
                    $obj_rpc_poll->deleteRpc($rpc_id, 'response');
                }
            } else {
                // $obj_rpc_poll->delete(array('process_id'=>$process_id,'type'=>'response'));
                $obj_rpc_poll->deleteRpc($rpc_id, 'response');
            }
        }
    }

    private function connection_aborted()
    {
        $return = connection_aborted();
        if (!$return) {
            if (is_numeric($_SERVER['HTTP_CONNECTION']) && $_SERVER['HTTP_CONNECTION'] > 0) {
                if (time() - $this->start_time >= $_SERVER['HTTP_CONNECTION']) {
                    $return = true;
                }
            }
        }
        return $return;
    }

    public function async_result_handler($params)
    {
        $callback_mq = app::get('ome')->getConf('ome.callback.mq');
        if ($callback_mq == 'true') {
            if (!isset($_REQUEST['_FROM_MQ_QUEUE']) && $_REQUEST['_FROM_MQ_QUEUE'] != 'true') {
                $this->_mq_async_result_handler($params);
            } else {
                $this->_real_async_result_handler($params);
            }
        } else {
            $this->_real_async_result_handler($params);
        }
    }

    private function _mq_async_result_handler($params)
    {
        $this->begin(__FUNCTION__);
        $pathinfo = kernel::single('base_request', 1)->get_path_info();

        $push_data = array(
            'rpc_id'         => $params['id'],
            '_FROM_MQ_QUEUE' => 'true',
            'pathinfo'       => $pathinfo,
            'task_type'      => 'omecallback',
        );
        $push_data = array_merge($push_data, $_REQUEST);

        taskmgr_func::publishMsg($push_data, 'api', $_REQUEST['msg_id'], [
            'SAAS_MQ'           => constant('SAAS_CALLBACK_MQ'),
            'queue_config'      => $GLOBALS['_MQ_CALLBACK_CONFIG'],
            'queue_exchange'    => 'TG_CALLBACK_EXCHANGE',
            'queue_name'        => 'TG_CALLBACK_QUEUE',
            'routerKey'         => 'tg.sys.callback.'.$data['nodeId'],
        ]);

        //callback请求进队列
        // $push_params = array(
        //     'data' => $push_data,
        //     'url'  => kernel::openapi_url('openapi.autotask', 'service'),
        // );

        // kernel::single('taskmgr_interface_connecter')->push($push_params);

        $this->end();

        if (!$return) {
            $return = array(
                'rsp'    => 'succ',
                'res'    => 'the callback is into mq now !!!',
                'msg_id' => '',
            );
        }

        echo json_encode($return);
        exit;
    }

    private function _real_async_result_handler($params)
    {
        $this->begin(__FUNCTION__);

        $result       = new base_rpc_result($_POST, $params['app_id']);
        $obj_rpc_poll = app::get('base')->model('rpcpoll');
        $arr_rpc_id   = explode('-', $params['id']);
        $rpc_id       = $arr_rpc_id[0];
        $rpc_calltime = $arr_rpc_id[1];

        // $row = $obj_rpc_poll->getlist('fail_times,callback,callback_params,process_id,params',array('id'=>$rpc_id,'calltime'=>$rpc_calltime,'type'=>'request'),0,1);
        $row = $obj_rpc_poll->getRpc($params['id']);

        $fail_time = ($row[0]['fail_times'] - 1) ? ($row[0]['fail_times'] - 1) : 0;
        // $obj_rpc_poll->update(array('fail_times'=>($row[0]['fail_times']-1)), array('id'=>$rpc_id,'calltime'=>$rpc_calltime,'type'=>'request'));
        $obj_rpc_poll->updateRpc(array('fail_times' => $fail_time), $params['id']);

        if ($row) {
            list($class, $method) = explode(':', $row[0]['callback']);
            if ($class && $method) {
                $tmp_params = unserialize($row[0]['callback_params']);
                $result->set_callback_params($tmp_params);
                $result->set_request_params($row[0]['params']);
                $result->set_msg_id($row[0]['process_id']);

                $return = kernel::single($class)->$method($result);
                if ($return) {
                    $notify = array(
                        'callback'   => $row[0]['callback'],
                        'rsp'        => $return['rsp'],
                        'msg'        => $return['res'],
                        'notifytime' => time(),
                    );
                    app::get('base')->model('rpcnotify')->insert($notify);
                }
            }
        }

        //$obj_rpc_poll->delete(array('id'=>$rpc_id,'calltime'=>$rpc_calltime,'type'=>'request','fail_times'=>'1'));
        if ($return['rsp'] == 'succ') {
            // $obj_rpc_poll->delete(array('id'=>$rpc_id,'calltime'=>$rpc_calltime,'type'=>'request'));
            $obj_rpc_poll->deleteRpc($params['id']);
        }

        if (!$return) {
            $return = array(
                "rsp"    => "fail",
                "res"    => "",
                "msg_id" => "",
            );
        }

        $this->end();

        header('Content-type: text/plain');
        echo json_encode($return);
    }

    public function error_handle($error_code, $error_msg)
    {
        $this->send_user_error('4007', $error_msg);
    }

    public function user_error_handle($error_code, $error_msg)
    {
        $this->send_user_error('4007', $error_msg);
    }

    public function send_user_error($code, $error_msg, $data = [])
    {
        $this->end();
        $res = array(
            'rsp'  => 'fail',
            'res'  => $code,
            'data' => $data,
            'msg'  => $error_msg,
        );
        $this->rpc_response_end($data, $this->rpc_id, $res);
        echo json_encode($res);
        exit;
    } //End Function

    public function send_user_success($code, $data)
    {
        $output      = $this->end();
        $result_json = array(
            'rsp'  => 'succ',
            'data' => $data,
            'res'  => $code,
        );
        $this->rpc_response_end($data, $this->rpc_id, $result_json);
        echo json_encode($result_json);
        exit;
    } //End Function

}

class erpapi_rpc_service
{

    private $start_time;
    private $format = 'json';
    private $path   = array();
    private $finish = false;
    static $node_id;

    /**
     * 外部入口
     *
     * @param String $path URL路径
     * @param String $source_type matrix|openapi|prism
     **/
    public function process($path)
    {
        if (!kernel::is_online()) {
            die('error');
        } 

        $this->__source_type = 'matrix';

        $this->handle();
    }

    private function begin()
    {
        register_shutdown_function(array(&$this, 'shutdown'));
        array_push($this->path, $key);
        @ob_start();
    }

    private function end($shutdown = false)
    {
        if ($this->path) {
            $this->finish = true;
            $content      = ob_get_contents();
            ob_end_clean();
            $name = array_pop($this->path);

            if ($shutdown) {
                $result = array(
                    'rsp'  => 'fail',
                    'res'  => $content,
                    'data' => null,
                );

                echo $this->formatObj->data_encode($result);
                exit;
            }

            return $content;
        }
    }

    public function shutdown()
    {
        $this->end(true);
    }

    private function gen_uniq_process_id()
    {
        return uniqid();
    }

    /**
     * 放入MQ处理
     *
     * @return void
     * @author
     **/
    public function _mq_handle()
    {
        $this->begin(__FUNCTION__);
        $rpc_id = $_REQUEST['task'] ? md5($_REQUEST['task'] . $_REQUEST['method'] . $_SERVER['HTTP_HOST']) : md5($_REQUEST['task'] . $_REQUEST['sign'] . $_SERVER['HTTP_HOST']);

        // 关键字段放后面，避免被 $_REQUEST 覆盖（尤其是 task_type）
        $push_data = array_merge($_REQUEST, array(
            'rpc_id'         => $rpc_id,
            '_FROM_MQ_QUEUE' => 'true',
            'task_type'      => 'wmsrpc',
        ));

        $uniqid = $_REQUEST['msg_id'] ? $_REQUEST['msg_id'] : $_REQUEST['LogisticCode'];
        // SAAS 路由用 node_id；兼容 from_node_id，兜底本节点
        $nodeId = $_REQUEST['node_id'] ? $_REQUEST['node_id'] : ($_REQUEST['from_node_id'] ? $_REQUEST['from_node_id'] : base_shopnode::node_id('ome'));

        // SAAS_API_MQ=true → RabbitMQ TG_API_QUEUE；否则 → Redis wmsrpc（sw.php 消费）
        $error_msg = '';
        $pushed = taskmgr_func::publishMsg($push_data, 'api', $uniqid, array(
            'SAAS_MQ'        => defined('SAAS_API_MQ') ? constant('SAAS_API_MQ') : '',
            'queue_config'   => $GLOBALS['_MQ_API_CONFIG'],
            'queue_exchange' => 'TG_API_EXCHANGE',
            'queue_name'     => 'TG_API_QUEUE',
            'routerKey'      => 'tg.sys.api.' . $nodeId,
        ), $error_msg);

        $this->end();

        if ($pushed) {
            $return = array(
                'rsp'  => 'succ',
                'data' => array('msg' => 'the wms rpc is into mq now !!!'),
                'res'  => '',
            );
        } else {
            $return = array(
                'rsp'  => 'fail',
                'data' => array(
                    'msg'   => 'the wms rpc push to mq failed',
                    'error' => $error_msg ? $error_msg : 'unknown push error',
                ),
                'res'  => 'push_mq_failed',
            );
        }

        echo $this->formatObj->data_encode($return);
        exit;
    }

    public function handle()
    {
        // 设置客户端断开连接时是否中断脚本的执行 true:中断
        ignore_user_abort();
        set_time_limit(0);

        $this->process_id = $this->gen_uniq_process_id();
        header('Process-id: ' . $this->process_id);
        header('Connection: close');
        flush();

        if (strtolower($_SERVER['HTTP_CONTENT_ENCODING']) == 'gzip') {
            $_input = fopen('php://input', 'rb');
            while (!feof($_input)) {
                $_post .= fgets($_input);
            }
            fclose($_input);
            $_post = utils::gzdecode($_post);
            parse_str($_post, $post);
            if ($post) {
                $_REQUEST = array_merge($_GET, $post);
            }
        }

        
        // 如果content_type为application/json，则将body内容转换为json
        if (stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            // 需要判断php://input是否为空
            if ($input = file_get_contents('php://input')) {
                $input = json_decode($input, true);
                
                // merge
                $_REQUEST = array_merge($_REQUEST, $input?:[]);
            }
        }
        
        if ($_REQUEST['format']) {
            if (class_exists('erpapi_format_' . $_REQUEST['format'])) {
                $this->format = $_REQUEST['format'];
            }
        }
        $this->formatObj = kernel::single('erpapi_format_' . $this->format);
        $wms_rpc_mq      = app::get('ome')->getConf('ome.wmsrpc.mq');
        // 是否加入队列(只会返回succ|fail 如果返回数据是不支持的)
        if ($wms_rpc_mq && $wms_rpc_mq == 'true'
            && !isset($_REQUEST['_FROM_MQ_QUEUE'])
            && $_REQUEST['_FROM_MQ_QUEUE'] != 'true'
            && $_REQUEST['method'] == 'wms.delivery.status_update'
        ) {
            return $this->_mq_handle();
        }
        // fails 为 taskmgr worker 重试计数，不能参与业务验签
        unset($_REQUEST['_FROM_MQ_QUEUE'], $_REQUEST['rpc_id'], $_REQUEST['task_type'], $_REQUEST['taskmgr_sign'], $_REQUEST['fails']);
        unset($_POST['_FROM_MQ_QUEUE'], $_POST['rpc_id'], $_POST['task_type'], $_POST['taskmgr_sign'], $_POST['fails']);

        // 如果是前后单分离申请，后面有可能需要去掉
        if ('front.' == substr($_REQUEST['method'], 0, 6)) {
            $_REQUEST['task'] = uniqid();
        }

        //todo: uncompress post data
        $rpc_id       = $_REQUEST['task'] ? md5($_REQUEST['task'] . $_REQUEST['method'] . $_SERVER['HTTP_HOST']) : md5($_REQUEST['task'] . $_REQUEST['sign'] . $_REQUEST['method'] . $_SERVER['HTTP_HOST']);
        $this->rpc_id = $rpc_id;

        // 判断是否重复
        $apilogModel = app::get('ome')->model('api_log');
        if ($apilogModel->is_repeat($rpc_id)) {
            $this->send_user_success('4007', '不能重复');
        }

        $this->begin(__FUNCTION__);
        set_error_handler(array(&$this, 'error_handle'), E_ERROR);
        set_error_handler(array(&$this, 'user_error_handle'), E_USER_ERROR);

        $this->start_time = $_SERVER['REQUEST_TIME'] ? $_SERVER['REQUEST_TIME'] : time();

        // 兼容D1Mwestore 走第三方B2C直连对接, 需转换method
        $erpapiMethod = erpapi_router_mapping::rspServiceMapping('', $_REQUEST['method'], $_REQUEST['node_id']);
        if (!$erpapiMethod) {
            $erpapiMethod = $_REQUEST['method'];
        }

        // 验签
        $signRs = kernel::single('erpapi_router_response')
            ->set_node_id($_REQUEST['node_id'])
            ->set_api_name($erpapiMethod)
            ->dispatch($_REQUEST, true);

        if ($signRs['rsp'] == 'fail') {
            trigger_error($signRs['msg'], E_USER_ERROR);
        }

        // 解析
        $_REQUEST['task'] = $rpc_id;

        $data = array(
            'id'         => $rpc_id,
            // 'network'    => $this->network, //要读到来源，要加密
            'method'     => $_REQUEST['method'] ? $_REQUEST['method'] : '',
            'calltime'   => $this->start_time,
            'params'     => serialize($_REQUEST),
            'type'       => 'response',
            'process_id' => $this->process_id,
            'callback'   => $_SERVER['HTTP_CALLBACK'],
        );

        $obj_rpc_poll = app::get('base')->model('rpcpoll');
        // 防止多次重刷.
        $rpcpoll = $obj_rpc_poll->getRpc($rpc_id, 'response');
        if (!$rpcpoll) {
            // $obj_rpc_poll->insert($data);
            $obj_rpc_poll->insertRpc($data, $rpc_id, 'response', 120);

            $rs = kernel::single('erpapi_router_response')
                ->set_node_id($_REQUEST['node_id'])
                ->set_api_name($erpapiMethod)
                ->dispatch($_REQUEST);
            if ($rs['rsp'] == 'fail') {
                trigger_error($rs['msg'], E_USER_ERROR);
            }
            $result = $rs['data'];

            $output = $this->end();
        } else {
            $output = $this->end();
            $output = app::get('base')->_('该请求已经处理，不能在处理了！');
        }

        $result_json = array(
            'rsp'  => 'succ',
            'data' => $result,
            'res'  => strip_tags($output),
        );

        $this->rpc_response_end($result, $rpc_id, $result_json);

        echo $this->formatObj->data_encode($result_json);
    }

    private function rpc_response_end($result, $rpc_id, $result_json)
    {
        if (isset($rpc_id) && $rpc_id) {
            $connection_aborted = $this->connection_aborted();
            $obj_rpc_poll       = app::get('base')->model('rpcpoll');

            if ($connection_aborted) {

                // 异步回调
                // $obj_rpc_poll->update(array('result'=>$result),array('process_id'=>$process_id,'type'=>'response'));
                $obj_rpc_poll->updateRpc(array('result' => $result), $rpc_id, 'response');

                $callback = $_SERVER['HTTP_CALLBACK'] ? $_SERVER['HTTP_CALLBACK'] : $_REQUEST['callback'];
                if ($callback) {
                    $return = kernel::single('base_httpclient')->get($callback . '?' . json_encode($result_json));
                    $return = json_decode($return);

                    if ($return->result == 'ok') {
                        // $obj_rpc_poll->delete(array('process_id'=>$process_id,'type'=>'response'));
                        $obj_rpc_poll->deleteRpc($rpc_id, 'response');
                    }

                } else {
                    // $obj_rpc_poll->delete(array('process_id'=>$process_id,'type'=>'response'));
                    $obj_rpc_poll->deleteRpc($rpc_id, 'response');
                }
            } else {
                // $obj_rpc_poll->delete(array('process_id'=>$process_id,'type'=>'response'));
                $obj_rpc_poll->deleteRpc($rpc_id, 'response');
            }
        }
    }

    private function connection_aborted()
    {
        $return = connection_aborted();
        if (!$return) {
            if (is_numeric($_SERVER['HTTP_CONNECTION']) && $_SERVER['HTTP_CONNECTION'] > 0) {
                if (time() - $this->start_time >= $_SERVER['HTTP_CONNECTION']) {
                    $return = true;
                }
            }
        }
        return $return;
    }

    /**
     * 回调入口
     */
    public function async_result_handler($params)
    {
        list($usec, $sec) = explode(" ", microtime());
        $this->callback_start_time = $usec + $sec;

        $callback_mq = app::get('ome')->getConf('ome.callback.mq');
        if ($callback_mq == 'true') {
            if (!isset($_REQUEST['_FROM_MQ_QUEUE']) && $_REQUEST['_FROM_MQ_QUEUE'] != 'true') {
                $this->_mq_async_result_handler($params);
            } else {
                $this->_real_async_result_handler($params);
            }
        } else {
            $this->_real_async_result_handler($params);
        }
    }

    private function _mq_async_result_handler($params)
    {
        $this->begin(__FUNCTION__);
        $pathinfo = kernel::single('base_request', 1)->get_path_info();

        $push_data = array(
            'rpc_id'         => $_REQUEST['msg_id'],
            '_FROM_MQ_QUEUE' => 'true',
            'pathinfo'       => $pathinfo,
            'task_type'      => 'wmscallback',
        );
        $push_data = array_merge($push_data, $_REQUEST);

        taskmgr_func::publishMsg($push_data, 'api', $_REQUEST['msg_id'], [
            'SAAS_MQ'           => constant('SAAS_CALLBACK_MQ'),
            'queue_config'      => $GLOBALS['_MQ_CALLBACK_CONFIG'],
            'queue_exchange'    => 'TG_CALLBACK_EXCHANGE',
            'queue_name'        => 'TG_CALLBACK_QUEUE',
            'routerKey'         => 'tg.sys.callback.'.$data['nodeId'],
        ]);

        //callback请求进队列
        // $push_params = array(
        //     'data' => $push_data,
        //     'url'  => kernel::openapi_url('openapi.autotask', 'service'),
        // );
        // kernel::single('taskmgr_interface_connecter')->push($push_params);

        $this->end();
        if (!$return) {
            $return = array(
                "rsp"    => "succ",
                "res"    => "the callback is into mq now !!!",
                "msg_id" => "",
            );
        }

        echo json_encode($return);
        exit;
    }

    // 请求回调
    private function _real_async_result_handler($params)
    {

        // 修复PHP8.2报错
        @include_once(APP_DIR . '/erpapi/lib/apiname.php');

        $this->begin(__FUNCTION__);
        set_error_handler(array(&$this, 'user_error_handle'), E_USER_ERROR);

        $obj_rpc_poll                = app::get('base')->model('rpcpoll');
        list($rpc_id, $rpc_calltime) = explode('-', $params['id']);
        $row                         = $obj_rpc_poll->getRpc($params['id']);

        if ($row) {
            //$row[0]['params']          = @unserialize($row[0]['params']);
            //$row[0]['callback_params'] = @unserialize($row[0]['callback_params']);
            $row[0]['params']          = is_string($row[0]['params']) ? @unserialize($row[0]['params']) : $row[0]['params'];
            $row[0]['callback_params'] = is_string($row[0]['callback_params']) ? @unserialize($row[0]['callback_params']) : $row[0]['callback_params'];
            
            if (is_array($row[0]['callback_params'])) {
                $row[0]['callback_params']['method'] = $row[0]['method'];
            }

            if ($row[0]['format'] == 'xml') {
                $this->format = 'xml';
            }

            $this->formatObj = kernel::single('erpapi_format_' . $this->format);

            $configObj = unserialize($row[0]['callback_params']['config_class']);
            if(!is_object($configObj)) {
                trigger_error('config class is not object!', E_USER_ERROR);
            }
            //注销多余的垃圾参数（fails 为 taskmgr 重试计数，不能参与业务验签）
            unset($_POST['_FROM_MQ_QUEUE'], $_POST['rpc_id'], $_POST['task_type'], $_POST['taskmgr_sign'], $_POST['pathinfo'], $_POST['fails']);
            // 签名
            $sign       = $_POST['sign'];unset($_POST['sign']);
            $sign_check = $configObj->gen_sign($_POST);
            if ($sign != $sign_check) {
                trigger_error('sign error!', E_USER_ERROR);
            }
            $fail_time = ($row[0]['fail_times'] - 1) ? ($row[0]['fail_times'] - 1) : 0;
            
            //update
            $obj_rpc_poll->updateRpc(array('fail_times' => $fail_time), $params['id']);
            
            $return               = $this->_real_async_result_api_log($_POST, $row[0]['callback_params']);
            list($class, $method) = explode(':', $row[0]['callback']);
            if ($class && $method) {
                $return = kernel::single($class)->$method($_POST, $row[0]['callback_params']);
            }

        }

        //if ($return['rsp'] == 'succ' )
        //{
        $obj_rpc_poll->deleteRpc($params['id']);
        //}

        if (!$return) {
            $return = array(
                "rsp"    => "fail",
                "res"    => $params['id'],
                "msg_id" => $row[0]['id'],
            );
        }

        $this->end();

        echo json_encode($return);
        exit;
    }

    public function error_handle($error_code, $error_msg)
    {
        $this->send_user_error('4007', $error_msg);
    }

    public function user_error_handle($error_code, $error_msg)
    {
        $this->send_user_error('4007', $error_msg);
    }

    public function send_user_error($code, $err_msg)
    {
        $this->end();
        $res = array(
            'rsp'      => 'fail',
            'res'      => $err_msg,
            'data'     => '',
            'msg'      => $err_msg,
            'msg_code' => $code,
            //'flag' => 'failure', // qimen奇门字段
            //'code' => $code, // qimen奇门字段
            //'message' => $err_msg, // qimen奇门字段
        );
        
        // qimen接口返回数据
        if(in_array($_REQUEST['method'], ['qimen.taobao.erp.order.add', 'qimen.taobao.erp.order.update'])){
            $qimenResult = [
                'flag' => 'failure',
                'code' => '0',
                'message' => '',
            ];
            
            // succ
            if($res['rsp'] == 'succ' || $res['rsp'] == 'success'){
                $qimenResult['flag'] = 'success';
            }
            
            // msg_code
            if(isset($res['msg_code'])){
                $qimenResult['code'] = $res['msg_code'];
            }
            
            // message
            if(isset($res['msg']) || isset($res['mmessagesg'])){
                $qimenResult['message'] = ($res['msg'] ? $res['msg'] : $res['mmessagesg']);
            }
            
            // data
            if(isset($data['data'])){
                $qimenResult['data'] = $data['data'];
            }
            
            // 重置
            $res = $qimenResult;
        }
        
        $this->rpc_response_end($err_msg, $this->rpc_id, $res);

        echo json_encode($res);
        exit;
    } //End Function

    public function send_user_success($code, $data)
    {
        $output      = $this->end();
        $result_json = array(
            'rsp'  => 'succ',
            'data' => $data,
            'res'  => $code,
        );
        $this->rpc_response_end($data, $this->rpc_id, $result_json);

        echo $this->formatObj->data_encode($result_json);
        exit;
    } //End Function

    private function _real_async_result_api_log($response, $callback_params)
    {

        $rsp     = $response['rsp'];
        $err_msg = $response['err_msg'];
        $data    = $response['data'];
        $msg_id  = $response['msg_id'];
        $res     = $response['res'];
        $status  = 'fail';
        $msg     = $err_msg . '(' . $res . ')';
        if ($rsp == 'succ') {
            $msg    = '成功';
            $status = 'success';
        } elseif (isset($msg[2048])) {
            $msg = '失败';
        }

        // 记录失败
        $obj_type   = $callback_params['obj_type'];
        $obj_bn     = $callback_params['obj_bn'];
        $method     = $callback_params['method'];
        $log_id     = $callback_params['log_id'];
        $apiFailId  = $callback_params['api_fail_id'];

        if($apiFailId) {
            app::get('erpapi')->model('api_fail')->dealCallback($status,$apiFailId, $msg, $msg_id);
        }

        if ($log_id) {
            $callback_end_time = microtime(true);

            $kafkaMsg = var_export($response, true);
            if(isset($kafkaMsg[512000])) {
                $kafkaMsg = '数据太大，不存储返回结果';
            } else {
                $kafkaMsg = $response;
            }

            $kaf = [
                'obj_bn'        => $obj_bn,
                'msg_id'        => $msg_id,
                'createtime'    => $this->callback_start_time,
                'spendtime'     => ($callback_end_time-$this->callback_start_time),
                'response'      => json_encode($response),
                'transfer'      => json_encode($callback_params),
                // 'data' => [
                //     'response' => $kafkaMsg,
                //     'callback_params' => $callback_params,
                // ],
            ];


            $logModel = app::get('ome')->model('api_log');
            $logModel->update_log($log_id, $msg, $status, null, null, $kaf);
        }

        if ($callback_params['config_class']) {
            unset($callback_params['config_class']);
        }
        if ($callback_params['result_class']) {
            unset($callback_params['result_class']);
        }

        return array('rsp' => $rsp, 'res' => '', 'msg' => $msg, 'msg_code' => $msg_code, 'data' => $data);
    }

}

class base_render
{

    public $pagedata      = array();
    public $force_compile = 0;
    public $_tag_stack    = array();
    private $_compiler;
    static $_extra_vars         = array();
    public $_vars               = array();
    public $_files              = array();
    public $_tpl_key_prefix     = array();
    public $_ignore_pre_display = false;
    /** @var app */
    public $app;

    public function __construct(&$app)
    {
        $this->app      = $app;
        $this->params   = &kernel::request()->request_params;
        $this->pagedata = &base_render::$_extra_vars;
    }

    public function display($tmpl_file, $app_id = null, $fetch = false)
    {
        array_unshift($this->_files, $tmpl_file);
        $this->_vars = $this->pagedata;

        if ($p = strpos($tmpl_file, ':')) {
            $object = kernel::service('tpl_source.' . substr($tmpl_file, 0, $p));
            if ($object) {
                $tmpl_file_path = substr($tmpl_file, $p + 1);
                $last_modified  = $object->last_modified($tmpl_file_path);
            }
        } else {
            if (defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR . '/' . ($app_id ? $app_id : $this->app->app_id) . '/view/' . $tmpl_file)) {
                $tmpl_file = CUSTOM_CORE_DIR . '/' . ($app_id ? $app_id : $this->app->app_id) . '/view/' . $tmpl_file;
            } else {
                $tmpl_file = realpath(APP_DIR . '/' . ($app_id ? $app_id : $this->app->app_id) . '/view/' . $tmpl_file);
            }
            $last_modified = filemtime($tmpl_file);
        }
        $min = strtotime('2024-03-14 11:00');
        if ($last_modified < $min) {
            $last_modified = $min;
        }
        $path = pathinfo($tmpl_file);
        if(!in_array($path['extension'], ['html', 'tpl', 'vue'])
            && strpos($tmpl_file, 'print_otmpl') === false
        ) {
            if ($fetch !== true) {
                echo 'file unvaild: '.$tmpl_file;
            }
            return '';
        }
        $this->tmpl_cachekey('__temp_lang', kernel::get_lang()); //设置模版所属语言包
        $this->tmpl_cachekey('__temp_app_id', $app_id ? $app_id : $this->app->app_id);
        $compile_id = $this->compile_id($tmpl_file);

        if ($this->force_compile || base_kvstore::instance('cache/template')->fetch($compile_id, $compile_code, $last_modified) === false) {
            if ($object) {
                $compile_code = $this->_compiler()->compile($object->get_file_contents($tmpl_file_path));
            } else {
                $compile_code = $this->_compiler()->compile_file($tmpl_file);
            }
            if ($compile_code !== false) {
                base_kvstore::instance('cache/template')->store($compile_id, $compile_code);
            }
        }

        ob_start();
        eval('?>' . $compile_code);
        $content = ob_get_contents();
        ob_end_clean();
        array_shift($this->_files);

        $this->pre_display($content);

        if ($fetch === true) {
            return $content;
        } else {
            echo $content;
        }
    }

    public function pre_display(&$content)
    {
        if ($this->_ignore_pre_display === false) {
            foreach (kernel::serviceList('base_render_pre_display') as $service) {
                if (method_exists($service, 'pre_display')) {
                    $service->pre_display($content);
                }
            }
        }
    } //End Function

    public function _compiler()
    {
        return $this->single('base_component_compiler');
    }

    private function single($classname)
    {
        if (!isset($this->_object[$classname])) {
            $this->_object[$classname] = new $classname($this);
        }
        return $this->_object[$classname];
    }

    public function fetch($tmpl_file, $app_id = null)
    {

        return $this->display($tmpl_file, $app_id, true);
    }

    public function tmpl_cachekey($key, $value)
    {
        $this->_tpl_key_prefix[$key] = $value;
    }

    public function &ui()
    {
        return $this->single('base_component_ui');
    }

    public function _fetch_compile_include($app_id, $tmpl_file, $vars = null)
    {
        $_tmp_pagedata = $this->pagedata;
        $_tmp_vars     = $this->_vars;
        if (is_null($vars) || empty($vars)) {
            $this->pagedata = $this->_vars;
        } else {
            $this->pagedata = (array) $vars;
        }
        $this->_ignore_pre_display = true;
        $include                   = $this->fetch($tmpl_file, $app_id);
        $this->_ignore_pre_display = false; //todo: fetch include的模板时不需要执行pre_display过滤，主模板会最终执行一次
        $this->pagedata            = $_tmp_pagedata;
        $this->_vars               = $_tmp_vars;
        return $include;
    }

    public function compile_id($path)
    {
        ksort($this->_tpl_key_prefix);
        return md5($path . serialize($this->_tpl_key_prefix));
    }

}

class base_shopnode
{
    static $snode = null;


    public static function get($code = 'node_id', $app_id = 'b2c')
    {

        if (!function_exists('get_node_id')) {
            if (empty(self::$snode)) {
                if ($shopnode = app::get($app_id)->getConf('shop_site_node_id')) {
                    self::$snode = unserialize($shopnode);
                } else {
                    self::$snode = array();
                }
            }
        } else {
            self::$snode = get_node_id();
        }

        return self::$snode[$code];
    }

    public static function active($app_id = 'b2c')
    {
        if (self::get('node_id', $app_id)) {
            kernel::log('Using exists shopnode: kvstore shop_site_node_id');
        } else {
            kernel::log('Shopnode not found: node_id now obtained from callback interface');
        }
    }

    public static function set_node_id($node_id, $app_id = 'b2c')
    {
        if (!function_exists('set_node_id')) {
            // 存储kvstore.
            return app::get($app_id)->setConf('shop_site_node_id', serialize($node_id));
        } else {
            return set_node_id($node_id, $app_id);
        }
    }

    public static function delete_node_id($app_id = 'b2c')
    {
        if (!function_exists('delete_node_id')) {
            return app::get($app_id)->setConf('shop_site_node_id', '');
        } else {
            return delete_node_id($app_id);
        }
    }

    /**
     * 转给接口ac验证用
     * @param array 需要验证的参数
     * @param string app_id
     * @return string 结构sign
     */
    public static function gen_sign($params, $app_id)
    {
        return strtoupper(md5(strtoupper(md5(self::assemble($params))) . self::token($app_id)));
    }

    public static function assemble($params)
    {
        if (!is_array($params)) {
            return null;
        }

        ksort($params, SORT_STRING);
        $sign = '';
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }

            if (is_bool($val)) {
                $val = ($val) ? 1 : 0;
            }

            $sign .= $key . (is_array($val) ? self::assemble($val) : $val);
        }
        return $sign;
    } //End Function

    public static function node_id($app_id = NULL)
    {
        if (!$app_id) {
            $config = base_setup_config::deploy_info();
            foreach ($config['package']['app'] as $k => $app) {
                $app_xml = kernel::single('base_xml')->xml2array(file_get_contents(app::get($app['id'])->app_dir . '/app.xml'), 'base_app');
                if (isset($app_xml['node_id']) && $app_xml['node_id'] == "true" && !self::node_id($app['id'])) {
                    // 获取节点.
                    if ($node_id = self::node_id($app['id'])) {
                        return $node_id;
                    }
                }
            }
            return false;
        } else {
            return self::get('node_id', $app_id);
        }

    }

    public static function node_type($app_id = 'b2c')
    {return self::get('node_type', $app_id);}

    public static function token($app_id = 'b2c')
    {return self::get('token', $app_id);}
}

class base_certificate
{

    static $certi = null;


    //设置对外发布版本
    public static function set_release_version($release_version = null)
    {
        if (empty($release_version)) {
            app::get('ome')->setConf('tg_release_version', 'tg');
        } else {
            app::get('ome')->setConf('tg_release_version', $release_version);
        }
    }

    //获取对外发布版本
    public static function get_release_version()
    {
        return (app::get('ome')->getConf('tg_release_version')) ? app::get('ome')->getConf('tg_release_version') : 'tg';
    }

    public static function get($code = 'certificate_id')
    {
        if (self::$certi === null) {
            base_kvstore::instance('certificate')->fetch('cert', $certificate);
            if (!empty($certificate)) {
                self::$certi = $certificate;
            } elseif (file_exists(ROOT_DIR . '/config/certi.php')) {
                $certificate = array();
                require ROOT_DIR . '/config/certi.php';
                self::$certi = is_array($certificate) ? $certificate : array();
            } else {
                self::$certi = array();
            }
        }

        return isset(self::$certi[$code]) ? self::$certi[$code] : null;
    }

    public static function active()
    {
        if (self::get()) {
            kernel::log('Using exists certificate: kvstore certificate');
        } else {
            kernel::log('Certificate not found: kvstore certificate');
        }
    }

    public static function set_certificate($certificate)
    {
        $stored = base_kvstore::instance('certificate')->store('cert', $certificate);
        $configFile = ROOT_DIR . '/config/certi.php';

        // kv 保存失败时，回退写入配置文件
        if ($stored === false) {
            $content = "<?php\n\$certificate = " . var_export($certificate, true) . ";\n";
            if (file_put_contents($configFile, $content, LOCK_EX) === false) {
                return false;
            }
            self::$certi = null;
            return true;
        }

        // kv 保存成功：仅在清空证书时删除文件，正常情况下依赖 kv
        if (empty($certificate) && file_exists($configFile)) {
            @unlink($configFile);
        }

        // 清除缓存，下次获取时重新读取
        self::$certi = null;
        return true;
    }
    public static function del_certificate()
    {
        base_kvstore::instance('certificate')->delete('cert');
        // 删除配置文件，避免残留
        $configFile = ROOT_DIR . '/config/certi.php';
        if (file_exists($configFile)) {
            @unlink($configFile);
        }
        // 清除缓存
        self::$certi = null;
    }
    public static function gen_sign($params)
    {
        return strtoupper(md5(strtoupper(md5(self::assemble($params))) . self::token()));
    }

    public static function assemble($params)
    {
        if (!is_array($params)) {
            return null;
        }

        ksort($params, SORT_STRING);
        $sign = '';
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }

            if (is_bool($val)) {
                $val = ($val) ? 1 : 0;
            }

            $sign .= $key . (is_array($val) ? self::assemble($val) : $val);
        }
        return $sign;
    } //End Function

    public static function certi_id()
    {return self::get('certificate_id');}

    public static function token()
    {return self::get('token');}

    /**
     * 生成 certi_ac 签名
     * 
     * @param array $params 需要签名的参数数组
     * @param array $options 选项数组
     *   - exclude_keys: 排除的字段列表，默认 ['certi_ac']
     *   - token: 自定义 token，默认使用 base_certificate::token()。仅在调用企业 API 等特殊场景时需要传入
     *   - uppercase: 是否返回大写，默认 false
     *   - stripslashes: 是否对值使用 stripslashes，默认 false
     * @return string 签名字符串
     */
    public static function getCertiAC($params, $options = array())
    {
        // 默认选项：默认使用证书 token，仅在特殊场景（如企业 API）需要传入自定义 token
        $exclude_keys = isset($options['exclude_keys']) ? $options['exclude_keys'] : array('certi_ac');
        $token = isset($options['token']) ? $options['token'] : self::token();
        $uppercase = isset($options['uppercase']) ? $options['uppercase'] : false;
        $stripslashes = isset($options['stripslashes']) ? $options['stripslashes'] : false;
        
        ksort($params);
        $str = '';
        foreach ($params as $key => $value) {
            if (!in_array($key, $exclude_keys)) {
                $value = $stripslashes ? stripslashes($value) : $value;
                $str .= $value;
            }
        }
        $signString = md5($str . $token);
        return $uppercase ? strtoupper($signString) : $signString;
    }
}



class ome_cert_certcheck
{
    public function certcheck()
    {
        return true;
    }
}

class ome_rpc_request
{

    /**
     * RPC应用层发起（业务过滤）
     * 此方法控制发起前的过滤（禁止向未绑定的店铺发起），写入日志记录，还可以决定是否队列发起
     * @access public
     * @param string $method RPC远程服务接口名称
     * @param array $params 业务参数
     * @param array $callback 异步返回参数
     * @param string $title 发起的标题
     * @param string $shop_id 前端店铺
     * @param int $time_out 发起超时时间（秒）
     * @param array $write_log 日志记录
     * @param boolean $queue 是否放入队列方式稍后发起，默认为false:非队列 true:队列
     * @param array $addon 附加参数
     * @param Bool $center 请求平台：false矩阵   true:licence中心
     * @param String $http_method HTTP请求方式,POST或GET
     * @return boolean
     */
    public function request($method, $params, $callback = array(), $title = '', $shop_id = null, $time_out = 10, $queue = false, $addon = '', $write_log = array(), $center = false, $http_method = 'POST')
    {
        $return_value = array('rsp' => 'fail', 'res' => '', 'data' => '');
        //过滤此次同步前端店铺
        if ($node = $this->_check_node($shop_id, $method)) {
            $Ofunc                = kernel::single('ome_rpc_func');
            $params['to_node_id'] = $node[0]['node_id'];
            $params['node_type']  = $node[0]['node_type'];

            $app_xml              = $Ofunc->app_xml();
            $params['from_api_v'] = $app_xml['api_ver'];
            $params['to_api_v']   = $Ofunc->fetch_shop_api_v($node[0]['node_id']);
        } else {
            $return_value['res'] = '店铺节点不存在';
            return $return_value;
        }

        //检查是否过滤指定回写操作
        if ($this->_check_request_config($shop_id, $method)) {

            return false;
        }

        //生成日志ID号
        $oApi_log = app::get('ome')->model('api_log');
        $log_id   = $oApi_log->gen_id();

        //设置callback异常返回参数为空时的默认值
        if ($callback && $callback['class'] && $callback['method']) {
            $rpc_callback = array($callback['class'], $callback['method'], array('log_id' => $log_id, 'shop_id' => $shop_id));
        } else {
            $rpc_callback = array('ome_rpc_request', 'callback', array('log_id' => $log_id, 'shop_id' => $shop_id));
        }

        if ($queue == true) {
            //队列发起（此时不记录同步日志，队列后台执行时再记录）
            $param                 = array();
            $param['api_title']    = $title;
            $param['params']       = $params;
            $param['method']       = $method;
            $param['rpc_callback'] = $rpc_callback;
            $this->api_queue($method, $param, $addon);
        } else {
            //非队列发起（记录同步日志），并立即发起RPC
            if (!empty($addon) && is_array($addon)) {
                $api_params = array_merge($params, $addon);
            } else {
                $api_params = $params;
            }
            if (isset($write_log['log_type'])) {
                $log_type = $write_log['log_type'];
            } else {
                $log_type = ome_rpc_func::method2type($method);
            }
            $log_type = $log_type ? $log_type : 'other';

            $oApi_log->write_log($log_id, $title, 'ome_rpc_request', 'rpc_request', array($method, $api_params, $rpc_callback), '', 'request', 'running', '', $addon, $log_type, $addon['bn']);
            $this->rpc_request($method, $params, $rpc_callback, $time_out, $write_log, $center, $http_method);
        }
        return $log_id;
    }

    /**
     * RPC开始请求
     * 业务层数据过滤后，开始向上级框架层发起
     * @access public
     * @param string $method RPC远程服务接口名称
     * @param array $params 业务参数
     * @param int $time_out 发起超时时间（秒）
     * @return RPC响应结果
     */
    public function call($method, $params, $shop_id, $time_out = 2)
    {

        //过滤此次同步前端店铺
        if ($node = $this->_check_node($shop_id, $method)) {
            $params['to_node_id'] = $node[0]['node_id'];
            $params['node_type']  = $node[0]['node_type'];

            if (in_array($node[0]['node_type'], ome_shop_type::shopex_shop_type())) {
                $Ofunc                = kernel::single('ome_rpc_func');
                $app_xml              = $Ofunc->app_xml();
                $params['from_api_v'] = $app_xml['api_ver'];
                $params['to_api_v']   = $Ofunc->fetch_shop_api_v($node[0]['node_id']);
            }
        } else {
            return false;
        }

        //检查是否过滤指定回写操作
        if ($this->_check_request_config($shop_id, $method)) {

            return false;
        }

        return $this->rpc_request($method, $params, null, $time_out);
    }

    /**
     * RPC开始请求
     * 业务层数据过滤后，开始向上级框架层发起
     * @access public
     * @param string $method RPC远程服务接口名称
     * @param array $params 业务参数
     * @param array $callback 异步返回
     * @param int $time_out 发起超时时间（秒）
     * @param array $write_log 日志记录
     * @param Bool $center 请求平台：false矩阵   true:licence中心
     * @param String $http_method HTTP请求方式,POST或GET
     * @return RPC响应结果
     */
    public function rpc_request($method, $params, $callback, $time_out = 5, $write_log = array(), $center = false, $http_method = 'POST')
    {

        if ($center === false) {
            if (empty($callback)) {
                //实时请求
                $rst = app::get('ome')->matrix()->set_realtime(true)
                    ->set_timeout($time_out)
                    ->call($method, $params);
                return $rst;
            } else {
                if (isset($params['gzip'])) {
                    $gzip = $params['gzip'];
                } else {
                    $gzip = false;
                }
                $callback_class  = $callback[0];
                $callback_method = $callback[1];
                $callback_params = (isset($callback[2]) && $callback[2]) ? $callback[2] : array();
                if (isset($params[1]['task'])) {
                    $rpc_id = $params[1]['task'];
                }
                $rst = app::get('ome')->matrix()->set_callback($callback_class, $callback_method, $callback_params)
                    ->set_timeout($time_out)
                    ->call($method, $params, $rpc_id, $gzip);
            }
        } else {

            return $this->center_request($method, $params, $write_log, $http_method, $time_out);
        }

    }

    public function center_request($method, $params, $write_log = array(), $http_method = 'POST', $time_out = 10)
    {

        $url        = MATRIX_RELATION_URL;
        $sys_params = array(
            'app'          => $method,
            'certi_id'     => base_certificate::get('certificate_id'),
            'from_node_id' => base_shopnode::node_id('ome'),
            'from_api_v'   => $params['from_api_v'],
            'to_node_id'   => $params['to_node_id'],
            'to_api_v'     => $params['to_api_v'],
            'v'            => $params['from_api_v'],
            'timestamp'    => date('Y-m-d H:i:s', time()),
            'format'       => 'json',
        );
        $query_params             = array_merge($sys_params, $params);
        $query_params['certi_ac'] = self::licence_sign($query_params, 'ome');

        $log_title   = $write_log['log_title'];
        $original_bn = $write_log['original_bn'];

        if ($http_method == 'POST') {
            $http = kernel::single('base_httpclient');

            $response = $http->set_timeout($time_out)->post($url, $query_params, $headers);
            $response = json_decode($response, true);

            $rsp = array('rsp' => 'fail', 'msg' => '', 'data' => '');
            if (!isset($response['res'])) {
                $rsp['msg'] = '请求超时';
            } else {
                $rsp['rsp']  = $response['res'];
                $rsp['msg']  = $response['msg'];
                $rsp['data'] = $response['info'];
            }

            //$logObj = kernel::single('omeapilog_log');
            $log_type = $write_log['log_type'];
            $log_type = $log_type ? $log_type : 'other';
            //$status = $rsp['rsp'] == 'success' ? 'success' : 'fail';
            //$addon['msg_id'] = $rsp['msg_id'];
            //$msg = $rsp['msg'];
            //$logObj->write_action_log($log_title,$method,$query_params,$log_type,$status,$original_bn,$msg='',$addon);

            return $rsp;
        } else {
            $query_str = array();
            foreach ($query_params as $key => $value) {
                $query_str[] = $key . '=' . $value;
            }

            $query_str = implode('&', $query_str);
            $src       = $url . '?' . $query_str;
            header('Location:' . $src);
            exit;
            //echo '<title>'.$log_title.':'.$original_bn.'</title><iframe width="100%" height="95%" frameborder="0" src="'.$src.'" ></iframe>';
        }
    }

    /**
     * licence生成加密串
     * @access public
     * @param $params
     * @return String
     */
    public static function licence_sign($params)
    {
        $str = '';
        ksort($params);
        foreach ($params as $key => $value) {
            $str .= $value;
        }
        $token = base_certificate::token();

        return md5($str . $token);
    }
    /**
     * RPC异步返回数据接收
     * @access public
     * @param object $result 经由框架层处理后的同步结果数据
     * @return 返回业务处理结果
     */
    public function callback($result)
    {
        if (is_object($result)) {
            $callback_params = $result->get_callback_params();
            $status          = $result->get_status();
            $msg             = $result->get_result();
            $err_msg         = $result->get_err_msg();
            $data            = $result->get_data();
            $request_params  = $result->get_request_params();
            $msg_id          = $result->get_msg_id();
        } else {
            return true;
        }

        if ($status == 'succ') {
            $api_status = 'success';
        } else {
            $api_status = 'fail';
        }

        if ($msg != '') {
            $msg = '(' . $msg . ')' . $err_msg;
        }

        $rsp = 'succ';
        if ($status != 'succ' && $status != 'fail') {
            $msg = 'rsp:' . $status . 'res:' . $msg . 'data:' . $data;
            $rsp = 'fail';
        }
        //错误等级
        if (isset($data['error_level']) && !empty($data['error_level'])) {
            $addon['error_lv'] = $data['error_level'];
        }
        $log_id   = $callback_params['log_id'];
        $oApi_log = app::get('ome')->model('api_log');
        $oApi_log->update_log($log_id, $msg, $api_status, null, $addon);
        //$log_detail = $oApi_log->dump($log_id, 'msg_id,params');

        //只有接口类型为库存更新时，才调用库存callback函数

        return array('rsp' => $rsp, 'res' => $msg, 'msg_id' => $msg_id);
    }

    /**
     * RPC同步返回数据接收
     * @access public
     * @param json array $res RPC响应结果
     * @param array $params 同步日志ID
     */
    public function response_log($res, $params)
    {
        $response = json_decode($res, true);
        if (!is_array($response)) {
            $response = array(
                'rsp' => 'running',
                'res' => $res,
            );
        }
        $status = $response['rsp'];
        $result = $response['res'];

        if ($status == 'running') {
            $api_status = 'running';
        } elseif ($result == 'rx002') {
            //将解除绑定的重试设置为成功
            $api_status = 'success';
        } else {
            $api_status = 'fail';
        }

        $log_id   = $params['log_id'];
        $oApi_log = app::get('ome')->model('api_log');

        //更新日志数据
        $oApi_log->update_log($log_id, $result, $api_status);

        if ($response['msg_id']) {
            //更新日志msg_id及在应用级参数中记录task
            /*
            $log_info = $oApi_log->dump($log_id);
            $log_params = unserialize($log_info['params']);
            $rpc_key = $params['rpc_key'];
            $log_params[1]['task'] = $rpc_key;
            $update_data = array(
            'msg_id' => $response['msg_id'],
            'params' => serialize($log_params),
            );*/
            $update_data = array(
                'msg_id' => $response['msg_id'],
            );
            $update_filter = array('log_id' => $log_id);
            $oApi_log->update($update_data, $update_filter);
        }

        //只有接口类型为库存更新时，才调用库存callback函数
    }

    /**
     * 更新库存回写状态
     */
    public function save_stock_callback($log_id, $oApi_log)
    {
        $log_info   = $oApi_log->dump($log_id, 'msg_id,params');
        $log_params = unserialize($log_info['params']);
        if ($log_params[2][1] == 'stock_update_callback') {
            $list_quantity     = json_decode($log_params[1]['list_quantity'], true);
            $all_list_quantity = json_decode($log_params[1]['all_list_quantity'], true);
            $oApiLogToStock    = kernel::single('ome_api_log_to_stock');
            $oApiLogToStock->save_callback(
                $all_list_quantity, 'success',
                $params['shop_id'], $response['res'], $log_info
            );
            $oApiLogToStock->save_callback(
                $list_quantity, $api_status,
                $params['shop_id'], $response['res'], $log_info
            );
        }
    }

    /**
     * 店铺绑定关系过滤
     * 检查店铺（shop_id为空时标识所有店铺）是否可访问远端API接口服务，并返回可用的node_id
     * @access private
     * @param string $shop_id 店铺标识ID
     * @param string $method RPC远程调用接口名称
     * @return boolean
     */
    private function _check_node($shop_id, $method)
    {

        $node = $this->_get_node($shop_id);

        if ($node) {
            $request_whitelist = kernel::single('ome_rpc_request_whitelist');
            $t_node            = $node;
            foreach ($t_node as $k => $v) {
                $res = $request_whitelist->check_node($v['node_type'], $method);
                if (!$res) {
                    unset($node[$k]);
                }
            }
            if ($node) {
                return $node;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 检查指定店铺及回调方法是否被禁止
     *
     * @param String $shop_id 店铺ID
     * @param $method 调用方法
     * @return boolean true 禁止 false 允许
     */
    private function _check_request_config($shop_id, $method)
    {

        $method = strtolower($method);
        if ($method == 'store.items.quantity.list.update') {

            $request_auto_stock = app::get('ome')->getConf('request_auto_stock_' . $shop_id);

            //如无设置,缺省置为 true
            if (empty($request_auto_stock)) {
                $request_auto_stock = 'true';
                app::get('ome')->setConf('request_auto_stock_' . $shop_id, 'true');
            }

            //如已经关闭了库存回写功能，返回 true
            if ($request_auto_stock == 'false') {

                return true;
            }
        }

        return false;
    }

    /**
     * 通过shop_id获取结点信息
     * @access private
     * @param $shop_id
     * @return array 店铺绑定的节点数据
     */
    private function _get_node($shop_id)
    {

        $shopObj = app::get('ome')->model('shop');
        $node    = array();
        if (empty($shop_id)) {

            $shop_info = $shopObj->getList('node_id,node_type', '', 0, -1);
            if ($shop_info) {
                foreach ($shop_info as $v) {
                    if ($v['node_id']) {
                        $node[] = array(
                            'node_id'   => $v['node_id'],
                            'node_type' => $v['node_type'],
                        );
                    }
                }
            }
        } else {

            $shop_info = $shopObj->dump($shop_id, 'node_id,node_type');
            if ($shop_info['node_id']) {
                $node[] = array(
                    'node_id'   => $shop_info['node_id'],
                    'node_type' => $shop_info['node_type'],
                );
            }
        }

        return $node;
    }

    /**
     * RPC同步日志队列
     * @access public
     * @param string $queue_title 队列标题
     * @param array $queue_params 队列参数
     * @param array $addon 附加参数
     *
     */
    public function api_queue($queue_title, $queue_params, $addon = '')
    {

        $oQueue    = app::get('base')->model('queue');
        $queueData = array(
            'queue_title' => $queue_title,
            'start_time'  => time(),
            'params'      => array(
                'sdfdata' => $queue_params,
                'addon'   => $addon,
            ),
            'status'      => 'hibernate',
            'worker'      => __CLASS__ . '.run',
        );
        $oQueue->save($queueData);
    }

    /**
     * 执行API同步日志队列
     * @param $cursor_id
     * @param $params
     */
    public function run(&$cursor_id, $params)
    {

        $oApi_log = app::get('ome')->model('api_log');

        if (!is_array($params)) {
            $params = unserialize($params);
        }
        $Sdf          = $params['sdfdata'];
        $addon        = $params['addon'];
        $title        = $Sdf['api_title'];
        $method       = $Sdf['method'];
        $params       = $Sdf['params'];
        $rpc_callback = $Sdf['rpc_callback'];
        //附加参数
        if (!empty($addon) && is_array($addon)) {
            $api_params = array_merge($params, $addon);
        } else {
            $api_params = $params;
        }
        $log_type = ome_rpc_func::method2type($method);
        $log_id   = $rpc_callback[2]['log_id'];
        $oApi_log->write_log($log_id, $title, 'ome_rpc_request', 'rpc_request', array($method, $api_params, $rpc_callback), '', 'request', 'running', '', $addon, $log_type, $addon['bn']);
        kernel::single('ome_rpc_request')->rpc_request($method, $params, $rpc_callback);

    }

    /**
     *
     * @param $url
     * @param $params
     * @param $time_out
     *
     * @return String
     */
    public function direct_request($url, $params, $time_out = 5)
    {
        $headers = array(
            'Connection' => $time_out,
        );
        $core_http = kernel::single('base_httpclient');
        $res       = $core_http->post($url, $params, $headers);
        $res       = 'direct_request:' . $res;
        kernel::log($res);
        $res2 = 'direct_request content:' . $url . "\n" . json_encode($params);
        kernel::log($res2);

        return $res;
    }

    /**
     * 返回验证字符串
     *
     * @param $params
     *
     * @return String
     */
    public function make_sign($post_params)
    {
        ksort($post_params);
        $str = '';
        foreach ($post_params as $key => $value) {
            $str .= $value;
        }

        return md5($str . base_certificate::get('token'));
    }
}

class erpapi_rpc_caller
{

    /**
     * 超时时间
     *
     * @var int
     **/
    private $timeout = 10;

    /**
     * 请求方式 同步|异步
     *
     * @var boolean
     **/
    private $realtime = false;

    /**
     * 返回格式 json | xml
     *
     * @var string
     **/
    private $format = 'json';

    /**
     * 请求网关
     *
     * @var string
     **/
    private $gateway      = 'matrix';

    /**
     * 平台对象
     *
     * @var erpapi_config
     **/
    private $config = null;

    /**
     * 异常请求处理对象
     *
     * @var erpapi_result
     **/
    private $result = null;

    public function set_timeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function set_realtime($realtime)
    {
        $this->realtime = $realtime;
        return $this;
    }

    public function set_format($format)
    {
        $this->format = $format;
        return $this;
    }

    public function set_config(erpapi_config $config)
    {
        $this->config = $config;
        return $this;
    }

    public function set_result(erpapi_result $result)
    {
        $this->result = $result;
        return $this;
    }

    public function set_gateway($gateway)
    {
        $this->gateway = $gateway;

        return $this;
    }

    public function set_callback($callback_class, $callback_method, $callback_params = null)
    {
        $this->callback_class  = $callback_class;
        $this->callback_method = $callback_method;

        // CONFIG
        $callback_params['config_class'] = serialize($this->config);
        $this->callback_params           = $callback_params;
        return $this;
    }

    /**
     * 记录请求
     *
     * @return void
     * @author
     **/
    private function begin_transaction($method, $params, $rpc_id = null)
    {
        $obj_rpc_poll = app::get('base')->model('rpcpoll');
        if (is_null($rpc_id)) {
            $time      = time();
            $microtime = utils::microtime();
            $rpc_id    = str_replace('.', '', strval($microtime));
            $randval   = mt_rand();
            $rpc_id .= strval($randval);

            $data = array(
                'id'              => $rpc_id,
                'network'         => null,
                'calltime'        => $time,
                'method'          => $method,
                'params'          => serialize($params),
                'type'            => 'request',
                'callback'        => $this->callback_class . ':' . $this->callback_method,
                'callback_params' => serialize($this->callback_params),
            );
            $rpc_id = $rpc_id . '-' . $time;

            // $obj_rpc_poll->insert($data);
            $obj_rpc_poll->insertRpc($data, $rpc_id);
        } else {

            // $arr_pk       = explode('-', $rpc_id);
            // $rpc_id       = $arr_pk[0];
            // $rpc_calltime = $arr_pk[1];
            // $tmp          = $obj_rpc_poll->getList('*', array('id'=>$rpc_id,'calltime'=>$rpc_calltime));
            $tmp = $obj_rpc_poll->getRpc($rpc_id);

            if ($tmp) {
                $data = array(
                    'fail_times' => $tmp[0]['fail_times'] + 1,
                );
                // $fiter = array(
                //     'id'=>$rpc_id,
                //     'calltime'=>$rpc_calltime,
                // );

                // $obj_rpc_poll->update($data,$fiter);
                $obj_rpc_poll->updateRpc($data, $rpc_id);
            }
            // $rpc_id = $rpc_id.'-'.$rpc_calltime;
        }
        return $rpc_id;
    }

    public function call($method, $params, $rpc_id = null, $gzip = false)
    {

        if ($this->realtime) {

            return $this->realtime_call($method, $params);
        } else {

            return $this->async_call($method, $params, $rpc_id, $gzip);
        }
    }

    public function realtime_call($method, $params, $gzip = false)
    {

        $headers = array(
            'Connection' => $this->timeout,
        );
        if ($gzip) {
            $headers['Content-Encoding'] = 'gzip';
        }

        // 应用级参数
        $query_params = $this->config->get_query_params($method, $params);

        if ($query_params['headers']) {
            $headers = array_merge($headers, (array) $query_params['headers']);
            unset($query_params['headers']);
        }

        $query_params = array_merge((array) $params, (array) $query_params);

        $core_http = kernel::single('base_httpclient');
        $query_params['sign'] = $this->config->gen_sign($query_params,$method);

        $url = $this->config->get_url($method,$query_params,$this->realtime);

        // 请求参数格式化
        $query_params = $this->config->format($query_params);

        $response = $core_http->set_timeout($this->timeout)->post($url, $query_params, $headers);
        
        // 如存在httpCode,则进行处理
        $responseCode = '';
        if(property_exists($core_http,'responseCode')){
            $this->result->set_response_http_code($core_http->responseCode);
            $responseCode = $core_http->responseCode;
        }
        
        $responseHeader = '';
        if (property_exists($core_http,'responseHeader')){
            $responseHeader = is_array($core_http->responseHeader) ? json_encode($core_http->responseHeader) : $core_http->responseHeader;
        }

        if ($response === HTTP_TIME_OUT) {
            $result = array(
                'rsp'       => 'fail',
                'err_msg'   => '请求超时',
                'res_ltype' => 1,
            );
        } else {

            
            $this->result->set_response($response, $this->format);

            $format_response = $this->result->get_response();
            if ($format_response) {
                $result = array(
                    'rsp'      => $this->result->get_status(),
                    'msg_id'   => $this->result->get_msg_id(),
                    'data'     => $format_response['data'],
                    'err_msg'  => htmlspecialchars($this->result->get_err_msg()),
                    'res'      => $this->result->get_result(),
                    'response' => $response,
                );
            } else {
                $result = array(
                    'rsp'       => 'fail',
                    'err_msg'   => '返回信息异常:' . $response . ($responseCode?'(httpCode: '.$responseCode.')':''),
                    'res_ltype' => 2,
                );
            }
        }
        
        $result['url'] = $url;
        $result['responseCode'] = $responseCode;
        $result['responseHeader'] = $responseHeader;
        $result['requestBody'] = $query_params;

        return $result;
    }

    public function async_call($method, $params, $rpc_id = null, $gzip = false)
    {
        if (is_null($rpc_id)) {
            $rpc_id = $this->begin_transaction($method, $params);
        } else {
            $rpc_id = $this->begin_transaction($method, $params, $rpc_id);
        }
        $obj_rpc_poll = app::get('base')->model('rpcpoll');
        $headers      = array(
            'Connection' => $this->timeout,
        );
        if ($gzip) {
            $headers['Content-Encoding'] = 'gzip';
        }

        // 应用级参数
        $params['callback_url'] = kernel::openapi_url('openapi.asynccallback', 'async_result_handler', array('id' => $rpc_id));

        // rpc_id 分id 和 calltime
        $arr_rpc_key    = explode('-', $rpc_id);
        $rpc_id         = $arr_rpc_key[0];
        $rpc_calltime   = $arr_rpc_key[1];
        $params['task'] = $rpc_id;

        $query_params = $this->config->get_query_params($method, $params);
        if ($query_params['headers']) {
            $headers = array_merge($headers, (array) $query_params['headers']);
            unset($query_params['headers']);
        }

        $query_params = array_merge((array) $params, (array) $query_params);
        
        $core_http = kernel::single('base_httpclient');

        $query_params['sign'] = $this->config->gen_sign($query_params);

        $url = $this->config->get_url($method,$query_params,$this->realtime);

        // 请求参数格式化
        $query_params = $this->config->format($query_params);

        $response = $core_http->set_timeout($this->timeout)->post($url, $query_params, $headers);
        
        // 如存在httpCode,则进行处理
        $responseCode = '';
        if(property_exists($core_http,'responseCode')){
            $responseCode = $core_http->responseCode;
        }
        
        $responseHeader = '';
        if (property_exists($core_http,'responseHeader')){
            $responseHeader = is_array($core_http->responseHeader) ? json_encode($core_http->responseHeader) : $core_http->responseHeader;
        }

        if ($this->callback_class && method_exists(kernel::single($this->callback_class), 'response_log')) {
            $response_log_func = 'response_log';
            $callback_params   = $this->callback_params ? array_merge($this->callback_params, array('rpc_key' => $rpc_id . '-' . $rpc_calltime)) : array('rpc_key' => $rpc_id . '-' . $rpc_calltime);
            kernel::single($this->callback_class)->$response_log_func($response, $callback_params);
        }

        if ($response === HTTP_TIME_OUT) {
            $headers = $core_http->responseHeader;
            // $obj_rpc_poll->update(array('process_id'=>$headers['process-id']),array('id'=>$rpc_id,'calltime'=>$rpc_calltime,'type'=>'request'));
            $obj_rpc_poll->updateRpc(array('process_id' => $headers['process-id']), $rpc_id . '-' . $rpc_calltime);

            $result = array(
                'rsp'       => 'fail',
                'err_msg'   => '请求超时',
                'res_ltype' => 1,
                'res'       => 'e00090',
                'url'       => $url,
                'responseCode' => $responseCode,
                'responseHeader' => $responseHeader,
                'requestBody'      => $query_params,
            );

            return $result;
        } else {

            $this->result->set_response($response, $this->format);

            $format_response = $this->result->get_response();
            if ($format_response) {
                switch ($this->result->get_status()) {
                    case 'running':
                        // 存入中心给的process-id也就是msg-id
                        // $obj_rpc_poll->update(array('process_id'=>$this->result->get_msg_id()),array('id'=>$rpc_id,'type'=>'request','calltime'=>$rpc_calltime));
                        $obj_rpc_poll->updateRpc(array('process_id' => $this->result->get_msg_id()), $rpc_id . '-' . $rpc_calltime);

                        return array(
                            'rsp' => 'running',
                            'err_msg' => '',
                            'res_ltype' => 0,
                            'msg_id' => $this->result->get_msg_id(),
                            'url' => $url,
                            'responseCode' => $responseCode,
                            'responseHeader' => $responseHeader,
                            'requestBody'      => $query_params,
                        );
                    case 'succ':
                        // $obj_rpc_poll->delete(array('id'=>$rpc_id,'calltime'=>$rpc_calltime,'type'=>'request','fail_times'=>1));
                        $obj_rpc_poll->deleteRpc($rpc_id . '-' . $rpc_calltime);

                        $method = $this->callback_method;
                        if ($method && $this->callback_class) {
                            kernel::single($this->callback_class)->$method($format_response, $this->callback_params);
                        }

                        return array(
                            'rsp' => 'succ',
                            'err_msg' => '',
                            'res_ltype' => 0,
                            'msg_id' => $this->result->get_msg_id(),
                            'data' => array(),
                            'url' => $url,
                            'responseCode' => $responseCode,
                            'responseHeader' => $responseHeader,
                            'requestBody'      => $query_params,
                        );

                    case 'fail':

                        return array(
                            'rsp' => 'fail',
                            'res_ltype' => 2,
                            'err_msg' => htmlspecialchars($this->result->get_err_msg()),
                            'url' => $url,
                            'responseCode'      => $responseCode,
                            'responseHeader'    => $responseHeader,
                            'requestBody'      => $query_params,

                        );
                }

            } else {
                //error 解码失败

                return array(
                    'rsp' => 'fail',
                    'err_msg' => '返回信息异常:' . $response,
                    'res_ltype' => 2,
                    'res' => 'ERP00090',
                    'url' => $url,
                    'responseCode' => $responseCode,
                    'responseHeader' => $responseHeader,
                    'requestBody'      => $query_params,
                );
            }
        }
    }

}
