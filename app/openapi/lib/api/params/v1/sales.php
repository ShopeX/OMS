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
class openapi_api_params_v1_sales extends openapi_api_params_abstract implements openapi_api_params_interface
{

    public function checkParams($method, $params, &$sub_msg)
    {
        if (parent::checkParams($method, $params, $sub_msg)) {
            return true;
        } else {
            return false;
        }
    }

    public function getAppParams($method)
    {

        $params = array(
            'getList'         => array(
                'start_time' => array('type' => 'date', 'required' => 'false', 'name' => '开始时间(销售单创建时间),例如2012-12-08 18:50:30'),
                'end_time'   => array('type' => 'date', 'required' => 'false', 'name' => '结束时间(同上)'),
                'modified_start' => array('type' => 'date', 'required' => 'false', 'name' => '开始时间(销售单更新时间),例如2012-12-08 18:50:30'),
                'modified_end'   => array('type' => 'date', 'required' => 'false', 'name' => '结束时间(同上)'),
                'page_no'    => array('type' => 'number', 'required' => 'false', 'name' => '页码，默认1 第一页'),
                'page_size'  => array('type' => 'number', 'required' => 'false', 'name' => '每页数量，最大1000'),
                'shop_bn'    => array('type' => 'string', 'name' => '店铺编码', 'desc' => '多个店铺编码之间，用#分隔'),
                'order_bn' => array('type'=>'string', 'required'=>'false', 'name'=>'订单号', 'desc'=>'平台订单号'),
            ),
            'getSalesAmount'  => array(
                'start_time' => array('type' => 'date', 'required' => 'true', 'name' => '开始时间', 'desc' => '(销售单创建时间),例如2012-12-08 18:50:30'),
                'end_time'   => array('type' => 'date', 'required' => 'true', 'name' => '结束时间', 'desc' => '(销售单创建时间),例如2012-12-08 18:50:30'),
                'page_no'    => array('type' => 'number', 'required' => 'false', 'name' => '页码', 'desc' => '默认1,第一页'),
                'page_size'  => array('type' => 'number', 'required' => 'false', 'name' => '每页数量', 'desc' => '最大100'),
                'shop_bn'    => array('type' => 'string', 'name' => '店铺编码', 'desc' => '多个店铺编码之间，用#分隔'),
            ),
            'getDeliveryList' => array(
                'start_time'  => array('type' => 'date', 'required' => 'false', 'name' => '发货时间开始', 'desc' => '(发货时间),例如2012-12-08 18:50:30'),
                'end_time'    => array('type' => 'date', 'required' => 'false', 'name' => '发货时间结束', 'desc' => '(发货时间),例如2012-12-08 18:50:30'),
                'modified_start' => array('type' => 'date', 'required' => 'false', 'name' => '修改时间开始', 'desc' => '(发货销售明细更新时间),例如2012-12-08 18:50:30，时间跨度不超过24小时'),
                'modified_end'   => array('type' => 'date', 'required' => 'false', 'name' => '修改时间结束', 'desc' => '(发货销售明细更新时间),例如2012-12-08 18:50:30，时间跨度不超过24小时'),
                'page_no'     => array('type' => 'number', 'required' => 'false', 'name' => '页码', 'desc' => '默认1,第一页'),
                'page_size'   => array('type' => 'number', 'required' => 'false', 'name' => '每页数量', 'desc' => '最大1000'),
                'shop_bn'     => array('type' => 'string', 'name' => '店铺编码', 'desc' => ''),
                'delivery_bn' => array('type' => 'string', 'name' => '发货单号', 'desc' => ''),
                'order_bn'    => array('type' => 'string', 'name' => '订单号', 'desc' => '多个用英文逗号分隔'),
                'branch_bn'   => array('type' => 'string', 'name' => '仓库编码', 'desc' => ''),
            ),
            'getGxList' => array(
                'start_time'  => array('type' => 'date', 'required' => 'true', 'name' => '查询修改开始时间', 'desc' => '格式：2012-12-08 18:50:30'),
                'end_time'    => array('type' => 'date', 'required' => 'true', 'name' => '查询修改结束时间', 'desc' => '格式：2012-12-08 18:50:30'),
                'page_no'     => array('type' => 'number', 'required' => 'false', 'name' => '页码', 'desc' => '默认1,第一页'),
                'page_size'   => array('type' => 'number', 'required' => 'false', 'name' => '每页数量', 'desc' => '最大1000'),
            ),
        );

        return $params[$method];
    }

    public function description($method)
    {
        $desccription = array(
            'getList'        => array('name' => '查询销售单信息(根据销售单创建时间)', 'description' => '批量获取一个时间段内的销售单信息数据'),
            'getSalesAmount' => array('name' => '查询销售单信息(根据销售单创建时间)', 'description' => '获取一段时间段内的销售单总额数据'),
        );
        return $desccription[$method];
    }
}
