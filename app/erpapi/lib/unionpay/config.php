<?php
/**
 * Copyright © ShopeX （http://www.shopex.cn）. All rights reserved.
 * See LICENSE file for license details.
 */

/**
 * CONFIG
 *
 * @category 
 * @package 
 * @author sunjing<sunjing@shopex.cn>
 * @version $Id: Z
 */
class erpapi_unionpay_config extends erpapi_config
{
    /**
     * 应用级参数
     *
     * @param String $method 请求方法
     * @param Array $params 业务级请求参数
     * @return void
     * @author 
     **/
    public function get_query_params($method, $params){
        if ($method == SHOP_LOGISTICS_BIND) return array();

        $query_params = array(
            'app_id'       => 'ecos.ome',
            'method'       => $method,
            'date'         => date('Y-m-d H:i:s'),
            'format'       => 'json',
            'certi_id'     => base_certificate::certi_id(),
            'v'            => '1',
            'from_node_id' => base_shopnode::node_id('ome'),
        );

        $app_xml = app::get('ome')->define();
        $query_params['from_api_v'] = $app_xml['api_ver'];

        return $query_params;
    }

    /**
     * 获取请求地址
     *
     * @param String $method 请求方法
     * @param Array $params 业务级请求参数
     * @param Boolean $realtime 同步|异步
     * @return void
     * @author 
     **/
    public function get_url($method, $params, $realtime){
        $row = app::get('base')->model('network')->getlist('node_url,node_api', array('node_id'=>1));
        if($row){
            if(substr($row[0]['node_url'],-1,1)!='/'){
                $row[0]['node_url'] = $row[0]['node_url'].'/';
            }
            if($row[0]['node_api'][0]=='/'){
                $row[0]['node_api'] = substr($row[0]['node_api'],1);
            }
            $url = $row[0]['node_url'].$row[0]['node_api'];

            if ($method == SHOP_LOGISTICS_BIND){
                $url = rtrim(MATRIX_RELATION_URL, '/').'/api.php';
            } elseif ($realtime==true) {
                $url .= 'sync';
            }
        }

        return $url;
    }

    /**
     * 签名
     *
     * @param Array $params 参数
     * @return void
     * @author 
     **/
    public function gen_sign($params,$method=''){
        if ($method == SHOP_LOGISTICS_BIND) {return '';}

        if (!base_shopnode::token('ome'))
            $sign = base_certificate::gen_sign($params);
        else
            $sign = base_shopnode::gen_sign($params,'ome');

        return $sign;
    }
}