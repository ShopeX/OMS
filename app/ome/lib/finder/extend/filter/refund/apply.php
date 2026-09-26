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

class ome_finder_extend_filter_refund_apply
{
    /**
     * 获取_extend_colums
     * @return mixed 返回结果
     */
    public function get_extend_colums()
    {
        $problem      = kernel::single('ome_mdl_return_product_problem');
        $problem_info = $problem->getList('problem_id,problem_name');
        $filter       = array();
        if (!empty($problem_info)) {
            foreach ($problem_info as $v) {
                $filter[$v['problem_id']] = $v['problem_name'];
            }
        }
        
        //异常类型
        $abnormal_status_options =kernel::single('ome_constants_refundapply_abnormal')->getOptions();
        
        //单据种类
        $boolTypeOptions =kernel::single('ome_refund_bool_type')->getSearchOptions();
        
        // 线上店铺列表（按是否常用店铺进行排序）
        $shopFilter = array(
            's_type' => '1',
        );
        $shopNames = array_column(app::get('ome')->model('shop')->getList('name,shop_id', $shopFilter),'name','shop_id');
        
        //平台售后状态
        $platformStatus = kernel::single('ome_refund_func')->get_source_status('all', 'all');
        
        //dbschema
        $db['refund_apply'] = array(
            'columns' => array(
                'order_bn'        => array(
                    'type'            => 'varchar(30)',
                    'label'           => '订单号',
                    'width'           => 130,
                    'filtertype'      => 'textarea',
                    'searchtype'      => 'nequal',
                    'filterdefault'   => true,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                ),
                'problem_id'      => array(
                    'type'            => $filter,
                    'label'           => '售后类型',
                    'width'           => 130,
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'editable'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
                'abnormal_status' => array(
                    'type'          => $abnormal_status_options,
                    'label'         => '异常标识',
                    'filtertype'    => 'yes',
                    'filterdefault' => true,
                    'editable'      => false,
                ),
                'bool_type' => array(
                    'type' => $boolTypeOptions,
                    'label' => '单据种类',
                    'editable' => false,
                    'default' => '0',
                    'filtertype' => 'yes',
                    'filterdefault' => true,
                ),
                'paytime'            => array(
                    'type'       => 'time',
                    'label'      => '支付时间',
                    'comment'    => '支付时间',
                    'editable'   => false,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'in_list'    => true,
                ),
                'shop_id' => array(
                    'type'          => $shopNames,
                    'label'         => '来源店铺',
                    'width'         => 100,
                    'editable'      => false,
                    'in_list'       => true,
                    'filtertype'    => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                ),
                'source_status' => array(
                    'type' => $platformStatus,
                    'editable' => false,
                    'label' => '平台退款状态',
                    'default' => '',
                    'in_list'  => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
            ),
        );
        return $db;
    }
}
