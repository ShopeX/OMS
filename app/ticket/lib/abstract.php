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
 * 抽象类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.07.23
 */
abstract class ticket_abstract
{
    public $page_size = 100;
    
    public function __construct()
    {
        //--
    }
    
    /**
     * 成功输出
     * 
     * @param string $msg
     * @param string $data
     * @return array
     */
    final public function succ($msg='', $data=null)
    {
        return array('rsp'=>'succ', 'msg'=>$msg, 'data'=>$data);
    }
    
    /**
     * 失败输出
     * 
     * @param string $msg
     * @param string $data
     * @return array
     */
    final public function error($error_msg, $data=null)
    {
        return array('rsp'=>'fail', 'msg'=>$error_msg, 'error_msg'=>$error_msg, 'data'=>$data);
    }
    
    /**
     * 过滤特殊字符
     *
     * @param $str
     * @return string
     */
    public function charFilter($str)
    {
        return str_replace(array("\t","\r","\n",'"',"\\"), array(''), $str);
    }
}