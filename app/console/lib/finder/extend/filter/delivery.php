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

class console_finder_extend_filter_delivery{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums()
    {
        //WMS异常错误码
        $abnormalObj = app::get('wmsmgr')->model('abnormal_code');
        $tempList = $abnormalObj->getList('abnormal_id,abnormal_code,abnormal_name', array('abnormal_type'=>'delivery'), 0, 100);
        $error_codes = array();
        if($tempList){
            foreach ($tempList as $key => $val)
            {
                $abnormal_code = $val['abnormal_code'];
                $error_codes[$abnormal_code] = $abnormal_code.'('.$val['abnormal_name'].')';
            }
        }
        unset($tempList);
        
        $db['delivery']=array (
            'columns' => array(
                'delivery_bn' => array (
                    'type' => 'varchar(32)',
                    'label' => '发货单号',
                    'editable' => false,
                    'width' =>140,
                    'searchtype' => 'nequal',
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'order_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '订单号',
                    'width' => 130,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'outer_dlybn' => array (
                    'type' => 'varchar(30)',
                    'label' => '第三方单号',
                    'width' => 130,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'sync_code' => array (
                    'type' => $error_codes,
                    'label' => '同步WMS错误码',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => false,
                    'default_in_list' => false,
                ),
                'material_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '基础物料编码',
                    'width' => 130,
                    'filtertype' => 'textarea',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'order_label' => array (
                    'type' => 'table:order_labels@omeauto',
                    'label' => '标记',
                    'width' => 120,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
            )
        );
        
        return $db;
    }
}
