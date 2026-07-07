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


class qimen_rpc_service
{
    private $path = array();

    /**
     * 处理
     * @param mixed $path path
     * @return mixed 返回值
     */
    public function process($path){
        if(!kernel::is_online()){
            die('error');
        }
        
        // 禁止请求此接口，直接返回
        $result = array(
            'rsp' => 'fail',
            'sub_code' => '403', // 无权限（Forbidden）
            'sub_message' => '禁止请求此接口!'
        );
        
        return json_encode($result);
        
//        $p = strpos($_REQUEST['method'], '.');
//        $method = substr($_REQUEST['method'], $p+1, strlen($_REQUEST['method']));
//        switch($method)
//        {
//            case 'onex.oms.custom.iostock.add':
//                $iostockLib = kernel::single('qimen_iostock_iostock');
//
//                //创建入库单
//                $res = $iostockLib->add($_REQUEST);
//
//                //write_log
//                $title = '接收转仓单通知';
//                $original_bn = $_REQUEST['trfoutno'];
//                $status = ($res['rsp'] == 'succ' ? 'success' : 'fail');
//
//                $params    = array();
//                $params[0]  = $method;
//                $params[1]  = $_POST;
//
//                $msg = json_encode($res);
//
//                $iostockLib->_write_log($title, $original_bn, $status, $params, $msg);
//
//                echo $msg;
//                exit;
//                break;
//            case 'onex.oms.custom.openapi.sync':
//                $q = json_decode(base64_decode($_REQUEST['q']), true);
//
//                $format_parmas = array(
//                    'flag' => $_REQUEST['flag'],
//                    'type' => $_REQUEST['type'],
//                    'charset' => $_REQUEST['charset'],
//                    'ver' => $_REQUEST['ver'],
//                );
//                unset($_POST, $_REQUEST);
//
//                $_POST = array_merge($format_parmas, $q);
//
//                $params = array();
//                kernel::single('openapi_entrance')->service($params);
//                break;
//            default:
//                break;
//        }
//
//        return json_encode(array('rsp'=>'fail', 'sub_code'=>'e0053', 'sub_message'=>'无效的请求通知'));
    }
}
