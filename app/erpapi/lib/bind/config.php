<?php
/**
 * Copyright © ShopeX （http://www.shopex.cn）. All rights reserved.
 * See LICENSE file for license details.
 */
/**
 * @author ykm 2018/3/20
 * @describe 绑定相关
 */
class erpapi_bind_config extends erpapi_config
{

    /**
     * 获取_query_params
     * @param mixed $method method
     * @param mixed $params 参数
     * @return mixed 返回结果
     */

    public function get_query_params($method, $params){
        return array();
    }

    /**
     * 获取_url
     * @param mixed $method method
     * @param mixed $params 参数
     * @param mixed $realtime realtime
     * @return mixed 返回结果
     */
    public function get_url($method, $params, $realtime){
        $url = rtrim(MATRIX_RELATION_URL, '/').'/api.php'; #矩阵
        return $url;
    }

    /**
     * gen_sign
     * @param mixed $params 参数
     * @param mixed $method method
     * @return mixed 返回值
     */
    public function gen_sign($params,$method=''){
        return null;
    }
}