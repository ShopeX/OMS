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

class openapi_api_params_v1_orders extends openapi_api_params_abstract implements openapi_api_params_interface{

    /**
     * 检查Params
     * @param mixed $method method
     * @param mixed $params 参数
     * @param mixed $sub_msg sub_msg
     * @return mixed 返回验证结果
     */
    public function checkParams($method,$params,&$sub_msg){
        if(parent::checkParams($method,$params,$sub_msg)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取AppParams
     * @param mixed $method method
     * @return mixed 返回结果
     */
    public function getAppParams($method){

        $params = array(
            'getList'=>array(
            	'start_time'=>array('type'=>'date','required'=>'true','name'=>'开始时间,例如2012-12-08 18:50:30'),
            	'end_time'=>array('type'=>'date','required'=>'true','name'=>'结束时间'),
                'page_no'=>array('type'=>'number','required'=>'false','name'=>'页码，默认1 第一页'),
                'page_size'=>array('type'=>'number','required'=>'false','name'=>'每页数量，最大1000'),
                'shop_bn'=>array('type'=>'string','name'=>'店铺编码','desc'=>'多个店铺编码之间，用#分隔'),
                'order_bn' => array('type'=>'string', 'required'=>'false', 'name'=>'订单号', 'desc'=>'平台订单号,多个订单号用逗号分隔'),
                'close_item_req' => array('type'=>'string', 'required'=>'false', 'name'=>'close_item_req', 'desc'=>'是否需要CLOSE明细'),
                'time_select' => array('type'=>'string', 'required'=>'false', 'name'=>'time_select', 'desc'=>'查询时间字段，默认订单更新时间，可选值：createtime（订单创建时间）'),
                'cursor_id' => array('type'=>'number', 'required'=>'false', 'name'=>'游标ID', 'desc'=>'前一页最后一行数据的唯一标识，首次默认值为0'),
            ),
            // 'decrypt'=>array(
            //     'order_bn'=>array('type'=>'string','name'=>'订单编号','desc'=>''),
            //     'shop_bn'=>array('type'=>'string','name'=>'店铺编码','desc'=>''),
            // ),
            'getCouponList' => array(
                'start_time' => array('type' => 'date', 'required' => 'true', 'name' => '开始时间(完成状态)', 'desc' => '例如2012-12-08 00:00:00'),
                'end_time'   => array('type' => 'date', 'required' => 'true', 'name' => '结束时间(完成状态)', 'desc' => '例如2012-12-08 23:59:59'),
                'order_bn' => array('type'=>'string', 'required'=>'false', 'name'=>'订单号', 'desc'=>'平台订单号,多个订单号用逗号分隔'),
                'page_no'    => array('type' => 'number', 'required' => 'false', 'name' => '页码', 'desc' => '默认1,第一页'),
                'page_size'  => array('type' => 'number', 'required' => 'false', 'name' => '每页数量', 'desc' => '最大100'),
            ),
            'getPmtList' => array(
                'start_time' => array('type' => 'date', 'required' => 'true', 'name' => '开始时间(完成状态)', 'desc' => '例如2012-12-08 00:00:00'),
                'end_time'   => array('type' => 'date', 'required' => 'true', 'name' => '结束时间(完成状态)', 'desc' => '例如2012-12-08 23:59:59'),
                'order_bn' => array('type'=>'string', 'required'=>'false', 'name'=>'订单号', 'desc'=>'平台订单号,多个订单号用逗号分隔'),
                'page_no'    => array('type' => 'number', 'required' => 'false', 'name' => '页码', 'desc' => '默认1,第一页'),
                'page_size'  => array('type' => 'number', 'required' => 'false', 'name' => '每页数量', 'desc' => '最大100'),
            ),
        );

        return $params[$method];
    }
    
    /**
     * description
     * @param mixed $method method
     * @return mixed 返回值
     */
    public function description($method){
        $desccription = array(
            'getList'       => array('name' => '查询订单信息', 'description' => '批量获取一个时间段内的订单信息数据'),
            'getCouponList' => array('name' => '订单优惠券明细', 'description' => '按完成状态时间查询订单优惠券明细'),
            'getPmtList'    => array('name' => '订单促销明细', 'description' => '按完成状态时间查询订单促销明细'),
        );
        return isset($desccription[$method]) ? $desccription[$method] : null;
    }
}