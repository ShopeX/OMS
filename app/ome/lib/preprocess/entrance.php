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

class ome_preprocess_entrance {

    static private $__instance = array();
    
    //订单打标记:label,已经提前到创建订单时调用;
    static private $__methods_list = array('tbgift','invoice','crm','outstorage');

    private $__use_method = null;

    public function setMethod($method){
        $method = strtolower($method);
        if(in_array($method,self::$__methods_list)){
            $this->__use_method = $method;
        }else{
            $this->__use_method = -1;
        }
        return $this;
    }

    public function process($params,&$msg){
        $orderIds = $this->_mergeGroup($params);
        $process_status = true;
        if($this->__use_method == -1){
            $msg = 'use error method';
            return false;
        }elseif($this->__use_method && $this->__use_method != -1){
            $obj = self::_instanceObj($this->__use_method);
            foreach ($orderIds as $orderId){
                if(!$obj->process($orderId,$flag_msg)){
                    $process_status = false;
                    $msg[$orderId][] = $flag_msg;
                }
            }
        }else{
            #应用类型暂时只有crm类型应用
            $app_type = channel_ctl_admin_channel::$appType;
            #检测crm节点有没有绑定
            $obj_channel = app::get('channel')->model('channel');
            $node_info = $obj_channel->getList('node_id',array('channel_type'=>$app_type['crm']));

            #未安装crm应用，则不需要执行crm相关流程[第三方仓储版没有Crm目录App]
            if(!app::get('crm')->is_installed())
            {
                unset(self::$__methods_list['2']);
            }
            
            foreach (self::$__methods_list as $method){
                $obj = self::_instanceObj($method);
                foreach ($orderIds as $orderId){
                    if(!$obj->process($orderId,$flag_msg)){
                        $process_status = false;
                        $msg[$orderId][] = $flag_msg;
                    }
                }
            }
        }

        if($process_status){
            return true;
        }else{
            return false;
        }
    }

    static private function _instanceObj($key){
        if(!isset(self::$__instance[$key])){
            self::$__instance[$key] = kernel::single(sprintf('ome_preprocess_%s',$key));
        }
        return self::$__instance[$key];
    }

    private function _mergeGroup($params) {
        $ids = array();
        if(is_array($params)){
            foreach ($params as $item) {
                $ids = array_merge($ids, $item['orders']);
            }
        }else{
            $ids = array($params);
        }
        return $ids;
    }

}
