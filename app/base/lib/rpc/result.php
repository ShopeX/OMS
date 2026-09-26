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


class base_rpc_result{

    function __construct($response,$app_id){
        $sign = $response['sign'];
        unset($response['sign']);

        //注销多余的垃圾参数（fails 为 taskmgr 重试计数，不能参与业务验签）
        unset($response['_FROM_MQ_QUEUE'], $response['rpc_id'], $response['task_type'], $response['taskmgr_sign'], $response['pathinfo'], $response['fails']);

        $this->response = $response;
		if (!$app_id || !base_shopnode::token($app_id))
			$sign_check = base_certificate::gen_sign($response);
		else
			$sign_check = base_shopnode::gen_sign($response,$app_id);
        if($sign != $sign_check){
            trigger_error('sign error!',E_USER_ERROR);
        }
    }

    function set_callback_params($params){
        $this->callback_params = $params;
    }

    function get_callback_params(){
        return $this->callback_params;
    }

    function get_pid(){
        return $this->response['msg_id'];
    }

    function get_status(){
        return $this->response['rsp'];
    }

    function get_data(){
        return json_decode($this->response['data'],1);
    }

    function get_result(){
        return $this->response['res'];
    }

    function get_err_msg(){
        return $this->response['err_msg'];
    }
    
    /*
    function set_request_params($log_id){
        $paramsCacheLib = kernel::single('taoexlib_params_cache');
        $paramsCacheLib->fetch($log_id, $request_params);
        $paramsCacheLib->connClose();
        $this->request_params = unserialize($request_params);
    }*/
    
    function set_request_params($request_params){
        $this->request_params = $request_params;
    }

    function get_request_params(){
        return $this->request_params;
    }

    function set_msg_id($msg_id){
        $this->msg_id = $msg_id;
    }

    function get_msg_id(){
        return $this->msg_id;
    }

}
