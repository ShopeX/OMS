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
 * 公共方法
 */
class taskmgr_func
{
    /**
     * 分RDS多队列
     *
     * @return bool
     * @author
     **/
    public static function multiQueue($queue_config, $queue_exchange, $queue_name, $routerKey, $data, $type = 'task', $uniqid = '0', &$error_msg = '')
    {
        if (empty($queue_config) || !is_array($queue_config)) {
            $error_msg = 'SAAS MQ config(_MQ_API_CONFIG) empty or invalid';
            return false;
        }
        if (!$queue_exchange || !$queue_name || !$routerKey) {
            $error_msg = 'SAAS MQ exchange/queue/routerKey empty';
            return false;
        }

        //获取http或者https
        $http_prefix = kernel::request()->get_schema();
        $http_prefix = strtolower($http_prefix);
        $http_prefix = ($http_prefix ? $http_prefix : 'http');

        // type=api：回投原接口 URL（SAAS 蜘蛛消费）；否则走 autotask
        if ($type == 'api') {
            $url = sprintf("%s://%s%s", $http_prefix, $_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI']);

            // $data['_FROM_MQ_QUEUE'] = true;
        } else {
            $url = kernel::openapi_url('openapi.autotask', 'service');

            $data['taskmgr_sign'] = taskmgr_rpc_sign::gen_sign($data);
        }

        try {
            $mq = kernel::single('base_queue_mq');
            // connect 成功时无返回值，失败返回 false
            if ($mq->connect($queue_config, $queue_exchange, $queue_name) === false) {
                $error_msg = 'SAAS MQ connect failed, check _MQ_API_CONFIG';
                return false;
            }

            // http_build_query 可正确编码数组参数，避免 urlencode(array) 丢失结构
            $message                       = array();
            $message['spider_data']['url'] = $url;

            $message['spider_data']['params']    = http_build_query($data);
            $message['relation']['to_node_id']   = base_shopnode::node_id('ome');
            $message['relation']['from_node_id'] = '0';
            $message['relation']['tid']          = $uniqid ? $uniqid : $data['uniqid'];
            $message['relation']['to_url']       = $message['spider_data']['url'];
            $message['relation']['time']         = time();

            $result = $mq->publish(json_encode($message), $routerKey);

            $mq->disconnect();

            if (!$result) {
                $error_msg = 'SAAS MQ publish failed, routerKey=' . $routerKey;
                return false;
            }

            return true;
        } catch (Exception $e) {
            $error_msg = 'SAAS MQ exception: ' . $e->getMessage();
            return false;
        }
    }

    public static function singleQueue($data, &$error_msg = '')
    {
        $message = array(
            'data' => $data,
            'url'  => kernel::openapi_url('openapi.autotask', 'service'),
        );

        return kernel::single('taskmgr_interface_connecter')->push($message, $error_msg);
    }

    /**
     * 发布消息：SAAS_MQ=true 走 RabbitMQ，否则走 Redis/taskmgr
     *
     * @return bool
     * @author
     **/
    public static function publishMsg($data, $type = 'task', $uniqid = '0', $config_args = [], &$error_msg = '')
    {
        if ($config_args['SAAS_MQ'] == 'true') {
            return self::multiQueue($config_args['queue_config'],
                $config_args['queue_exchange'],
                $config_args['queue_name'],
                $config_args['routerKey'],
                $data,
                $type,
                $uniqid,
                $error_msg);
        } else {
            return self::singleQueue($data, $error_msg);
        }
    }
}
