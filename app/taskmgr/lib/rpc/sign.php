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
//加载配置信息
require_once(dirname(__FILE__) . '/../../config/config.php');
class taskmgr_rpc_sign{

    /**
     *
     * 生成签名算法函数
     * @param array $params
     * @param string $token 可选，指定使用的token，默认使用REQ_TOKEN
     */
    static public function gen_sign($params, $token = null){
        $useToken = $token !== null ? $token : REQ_TOKEN;
        return strtoupper(md5(strtoupper(md5(self::assemble($params))).$useToken));
    }

    /**
     * 验证签名（支持新旧token同时验证，用于平滑切换）
     * @param array $params 参数数组（不包含taskmgr_sign）
     * @param string $sign 待验证的签名
     * @return bool 验证是否通过
     */
    static public function validate_sign($params, $sign){
        // 先尝试用新token验证
        $newSign = self::gen_sign($params);
        if($sign === $newSign){
            return true;
        }
        
        // 如果配置了旧token，尝试用旧token验证（用于平滑切换）
        if(defined('REQ_TOKEN_OLD') && REQ_TOKEN_OLD){
            $oldSign = self::gen_sign($params, REQ_TOKEN_OLD);
            if($sign === $oldSign){
                return true;
            }
        }
        
        return false;
    }

	/**
     *
     * 签名参数组合函数
     * @param array $params
     */
    static private function assemble($params)
    {
        if(!is_array($params))  return null;
        ksort($params, SORT_STRING);
        $sign = '';
        foreach($params AS $key=>$val){
            if(is_null($val))   continue;
            if(is_bool($val))   $val = ($val) ? 1 : 0;
            $sign .= $key . (is_array($val) ? self::assemble($val) : $val);
        }
        return $sign;
    }
}
