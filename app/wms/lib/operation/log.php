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

class wms_operation_log{

    /**
     * 定义当前APP下的操作日志的所有操作名称列表
     * type键值由表名@APP名称组成
     * @access public
     * @return Array
     */
    function get_operations(){
        $operations = array(
           //发货单
           'delivery_modify' => array('name'=> '发货单详情修改','type' => 'delivery@wms'),
           'delivery_position' => array('name'=> '发货单货位 录入','type' => 'delivery@wms'),
           'delivery_merge' => array('name'=> '发货单合并','type' => 'delivery@wms'),
           'delivery_split' => array('name'=> '发货单拆分','type' => 'delivery@wms'),
           'delivery_stock' => array('name'=> '发货单备货单打印','type' => 'delivery@wms'),
           'delivery_deliv' => array('name'=> '发货单商品信息打印','type' => 'delivery@wms'),
           'delivery_expre' => array('name'=> '发货单快递单打印','type' => 'delivery@wms'),
           'delivery_logi_no' => array('name'=> '发货单快递单 录入','type' => 'delivery@wms'),
           'delivery_check' => array('name'=> '发货单校验','type' => 'delivery@wms'),
           'delivery_process' => array('name'=> '发货单发货处理','type' => 'delivery@wms'),
           'delivery_back' => array('name'=> '发货单打回','type' => 'delivery@wms'),
           'delivery_logi' => array('name'=> '发货单物流公司修改','type' => 'delivery@wms'),
           'delivery_pick' => array('name'=> '发货单拣货','type' => 'delivery@wms'),
            //新增发货称重报警处理
            'delivery_weightwarn' => array('name'=> '发货称重报警处理','type' => 'delivery@wms'),
           //子物流单操作日志
           'delivery_bill_print' => array('name'=> '多包裹物流单 打印','type' => 'delivery@wms'),
           'delivery_bill_delete' => array('name'=> '多包裹物流单 删除','type' => 'delivery@wms'),
           'delivery_bill_add' => array('name'=> '多包裹物流单 录入','type' => 'delivery@wms'),
           'delivery_bill_modify' => array('name'=> '多包裹物流单 修改','type' => 'delivery@wms'),
           'delivery_bill_express' => array('name'=> '多包裹物流单 发货','type' => 'delivery@wms'),
           'delivery_checkdelivery'=>array('name'=>'发货单发货处理','type' => 'delivery@wms'),

           //基础物料_保质期批次操作日志
           'storage_life_edit' => array('name'=>'延保', 'type'=>'basic_material_storage_life@material'),
            'storage_life_add' => array('name'=>'新增', 'type'=>'basic_material_storage_life@material'),
            'storage_life_chg' => array('name'=>'更新', 'type'=>'basic_material_storage_life@material'),
            'storage_life_freeze' => array('name'=>'预占', 'type'=>'basic_material_storage_life@material'),
            'storage_life_unfreeze' => array('name'=>'释放', 'type'=>'basic_material_storage_life@material'),
            'storage_life_consign' => array('name'=>'出库', 'type'=>'basic_material_storage_life@material'),
            'storage_life_statusUpdate' => array('name'=>'状态', 'type'=>'basic_material_storage_life@material'),

            //唯一码操作日志
            'product_serial_add' => array('name'=>'新增', 'type'=>'product_serial@wms'),
            'product_serial_import' => array('name'=>'导入', 'type'=>'product_serial@wms'),
            'product_serial_freeze' => array('name'=>'预占', 'type'=>'product_serial@wms'),
            'product_serial_unfreeze' => array('name'=>'释放', 'type'=>'product_serial@wms'),
            'product_serial_outstorage' => array('name'=>'出库', 'type'=>'product_serial@wms'),
            'product_serial_cancel' => array('name'=>'作废', 'type'=>'product_serial@wms'),
            'product_serial_return' => array('name'=>'退入', 'type'=>'product_serial@wms'),

            //基础物料_保质期批次操作日志
            'basic_material_import' => array('name'=>'基础物料导入', 'type'=>'basic_material@material'),
            'sales_material_import' => array('name'=>'销售物料导入', 'type'=>'sales_material@material'),

            //门店_企业组织结构操作日志
            'organization_import' => array('name'=>'企业组织结构导入', 'type'=>'organization@organization'),
            'o2o_store_import' => array('name'=>'门店导入', 'type'=>'store@o2o'),

            //导入关联后端商品操作日志
            'shop_skus_import' => array('name'=>'关联后端商品导入', 'type'=>'shop_skus@tbo2o'),

            //物料操作日志
            'basic_material_add' => array('name'=>'基础物料添加', 'type'=>'basic_material@material'),
            'basic_material_edit' => array('name'=>'基础物料编辑', 'type'=>'basic_material@material'),
            'basic_material_property' => array('name'=>'基础物料属性', 'type'=>'basic_material@material'),
            'sales_material_property' => array('name'=>'销售物料属性', 'type'=>'sales_material@material'),
            'sales_material_add' => array('name'=>'销售物料添加', 'type'=>'sales_material@material'),
            'sales_material_edit' => array('name'=>'销售物料编辑', 'type'=>'sales_material@material'),
            'fukubukuro_combine_add' => array('name'=>'福袋组合添加', 'type'=>'fukubukuro_combine@material'),
            'fukubukuro_combine_edit' => array('name'=>'福袋组合编辑', 'type'=>'fukubukuro_combine@material'),
            'fukubukuro_combine_modify' => array('name'=>'编辑关联销售物料', 'type'=>'fukubukuro_combine@material'),
            
            // 赠品审批实例
            'order_workflow_case' => array('name'=>'赠品审批', 'type'=>'workflow_case@ticket'),
            
            // SET商品
            'set_materialprice_create' => array('name'=>'创建加价购', 'type'=>'basic_material_price@miele'),
            'set_materialprice_edit' => array('name'=>'编辑加价购', 'type'=>'basic_material_price@miele'),
        );
        
        return array('wms'=>$operations);
    }
}
?>
