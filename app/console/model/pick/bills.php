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
 * 唯品会JIT拣货单mdl类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: vopurchase.php 2017-03-06 13:00
 */
class console_mdl_pick_bills extends dbeav_model{
    var $defaultOrder = array('bill_id',' DESC');
    
    public function table_name($real = false)
    {
        if($real){
           $table_name = 'sdb_purchase_pick_bills';
        }else{
           $table_name = 'pick_bills';
        }
        
        return $table_name;
    }
    
    public function get_schema(){
        return app::get('purchase')->model('pick_bills')->get_schema();
    }

    public function modifier_order_cate($row){
        if($row == 'normal'){
            return '普通';
        }elseif($row == '3pl'){
            return '3PL';
        }else{
            return $row ? $row : '-';
        }
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = " 1 ";
        //订单标记
        if($filter['order_label']){
            $ordLabelObj = app::get('ome')->model('bill_label');
            $tempData = $ordLabelObj->getList('bill_id', array('label_id'=>$filter['order_label'], 'bill_type'=>'pick_bill'));
            if($tempData){
                $orderId = array();
                foreach ($tempData as $tempKey => $tempVal)
                {
                    $temp_order_id = $tempVal['bill_id'];
                    
                    $orderId[$temp_order_id] = $temp_order_id;
                }
                
                $where .= ' AND bill_id IN ('. implode(',', $orderId) .')';
            }else{
                $where .= ' AND bill_id=-1';
            }
            
            unset($filter['order_label'], $tempData);
        }
        
        return $where ." AND ".parent::_filter($filter,$tableAlias,$baseWhere);
    }
}