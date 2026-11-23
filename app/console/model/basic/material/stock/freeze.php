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
 * 仓库预占流水记录
 */
class console_mdl_basic_material_stock_freeze extends material_mdl_basic_material_stock_freeze{
    
    var $defaultOrder = array('last_modified DESC, bmsf_id DESC');
    
    public function table_name($real = false)
    {
        if($real){
            $table_name = 'sdb_material_basic_material_stock_freeze';
        }else{
            $table_name = 'basic_material_stock_freeze';
        }
        
        return $table_name;
    }
    
    public function get_schema()
    {
        return app::get('material')->model('basic_material_stock_freeze')->get_schema();
    }
    
    /**
     * 预占流水类型
     */
    public function get_type($type_id=0)
    {
        $typeList  = array();
        $typeList[1]  = array(0=>'订单冻结',13=>'订单缺货',14=>'仓库冻结');
        $typeList[2]  = array(1=>'发货单', 2=>'售后换货', 3=>'采购退换', 4=>'调拨出库', 5=>'库内转储', 6=>'唯品会出库', 7=>'人工库存预占', 8=>'库存调整单', 9=>'差异单',10=>'加工单', 11 => 'VOP拣货单', 12 => '售后申请单', 13 => '订单缺货', 14 => '仓库冻结');
        $typeList[3]  = array(1=>'发货单', 2=>'售后换货', 3=>'采购退换', 4=>'调拨出库', 5=>'库内转储', 6=>'唯品会出库', 7=>'人工库存预占', 8=>'库存调整单', 9=>'差异单',10=>'加工单', 11 => 'VOP拣货单', 12 => '售后申请单', 13 => '订单缺货', 14 => '仓库冻结');
        
        //唯品会销售订单
        $typeList[15]  = array(0=>'创建订单');
        
        if($type_id)
        {
            $typeList    = $typeList[$type_id];
        }
        
        return $typeList;
    }
}
