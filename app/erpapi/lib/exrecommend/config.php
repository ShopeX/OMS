<?php
/**
 * Copyright © ShopeX （http://www.shopex.cn）. All rights reserved.
 * See LICENSE file for license details.
 */

class erpapi_exrecommend_config extends erpapi_config
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
        $query_params = array(
            'app_id'       => 'ecos.ome',
            'method'       => $method,
            'date'         => date('Y-m-d H:i:s'),
            'format'       => 'json',
            'certi_id'     => base_certificate::certi_id(),
            'v'            => '1',
            'from_node_id' => base_shopnode::node_id('ome'),
            'to_node_id'=>$this->__channelObj->exrecommend["to_node_id"]
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
}