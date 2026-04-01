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
 * 销售物料数据验证Lib类
 * 
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: check.php 2016-08-03 15:00
 */
class material_sales_check
{
    /**
     * 数据检验有效性
     * 
     * @param  Array   $params
     * @param  String  $err_msg
     * @return Boolean
     */
    public function checkParams(&$params, &$err_msg)
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        //新增标记
        $is_new_add    = $params['edit'] ? false : true;
        unset($params['edit']);
        
        // 去除销售物料编码前后空格
        if (isset($params['sales_material_bn'])) {
            $params['sales_material_bn'] = trim($params['sales_material_bn']);
        }
        
        if(empty($params['sales_material_name'])){
            $err_msg ="销售物料名称不能为空";
            return false;
        }
        
        if(empty($params['sales_material_bn'])){
            $err_msg ="销售物料编码不能为空";
            return false;
        }
        
        //检查有效性
        if($is_new_add)
        {
            //判断物料编码只能是由数字英文下划线组成
            // 销售物料编码：支持数字、英文、下划线、横线、井号、斜杠、空格
            $reg_bn_code = "/^[0-9a-zA-Z\_\#\-\/ ]*$/";
            if(!preg_match($reg_bn_code,$params["sales_material_bn"])){
                $err_msg = "物料编码只支持(数字、英文、_下划线、-横线、#井号、/斜杠、空格)";
                return false;
            }
            
            $salesMaterialInfo = $salesMaterialObj->getList('sales_material_bn',array('sales_material_bn'=>$params['sales_material_bn']));
            if($salesMaterialInfo){
                $err_msg ="当前新增的物料编码已被使用，不能重复";
                return false;
            }
            
            $params['sales_material_bn_crc32'] = sprintf('%u',crc32($params['sales_material_bn']));
        }
        else 
        {
            if(empty($params['sm_id'])){
                $err_msg ="销售物料sm_id不能为空";
                return false;
            }
            
            $salesMaterialExistInfo = $salesMaterialObj->getList('sm_id',array('sales_material_bn'=>$params['sales_material_bn']));
            if($salesMaterialExistInfo && $salesMaterialExistInfo[0]['sm_id'] != $params['sm_id']){
                $err_msg ="当前编辑的物料编码已被使用，不能重复";
                return false;
            }
            
            $salesMaterialInfo = $salesMaterialObj->dump($params['sm_id']);
            if(!$salesMaterialInfo){
                $err_msg ="当前物料不存在";
                return false;
            }
        }
        
        // 客户分类
        $params['class_id'] = $params['class_id'] ? $params['class_id'] : 0;
        //if(empty($params['class_id'])){
        //    $err_msg = '请选择客户分类';
        //    return false;
        //}
        
        //check
        if($params['sales_material_type'] == 1){
            if(!isset($params['bm_id']) || empty($params['bm_id'])){
                $err_msg = '请选择关联的基础物料';
                return false;
            }
        }elseif($params['sales_material_type'] == 6){
            if(!isset($params['box_id']) || empty($params['box_id'])){
                $err_msg = '请选择关联的礼盒';
                return false;
            }
        }
        
        //check
        if($params['sales_material_type'] == 2){
            if(!isset($params['at'])){
                $err_msg ="组合物料请至少设置一个物料明细内容";
                return false;
            }

            foreach ($params['at'] as $val){
                if (count($params['at']) == 1){
                    if ($val <2){
                        $err_msg ="只有一种物料时，数量必须大于1";
                        return false;
                    }
                }else {
                    if ($val < 1){
                        $err_msg ="数量必须大于0";
                        return false;
                    }
                }
            }

            $tmp_rate = 0;
            foreach ($params['pr'] as $val){
                $tmp_rate +=$val;
            }
            $tmp_rate = sprintf("%.2f",$tmp_rate);
            if($tmp_rate > 100){
                $err_msg ="分摊销售价合计百分比:".$tmp_rate.",已超100%";
                return false;
            }elseif($tmp_rate < 100){
                $err_msg ="分摊销售价合计百分比:".$tmp_rate.",不足100%";
                return false;
            }
        }
        
        // 赠品补充校验
        if ($params['sales_material_type'] == 3) {
            if (!isset($params['at'])) {
                $err_msg = "赠品物料请至少设置一个物料明细内容";
                return false;
            }

            foreach ($params['at'] as $val) {
                if ($val < 1) {
                    $err_msg = "数量必须大于0";
                    return false;
                }
            }
        }
        
        //多选一
        if($params['sales_material_type'] == 5){
            if(!$params['pickone_select_type']){
                $err_msg = "缺少多选一的选择方式";
                return false;
            }
            if(!isset($params['sort']) || count($params['sort']) < 2){
                $err_msg ="多选一物料请至少设置二个基础物料明细内容";
                return false;
            }
            $reg_number = "/^[1-9][0-9]*$/"; //正整数
            foreach($params['sort'] as $key_bm_id => $var_w){
                if($var_w){
                    if(!preg_match("/^[1-9][0-9]*$/",$var_w)){
                        $err_msg = "权重必须是数值";
                        return false;
                    }
                }else{ //0和空 数据库默认给0
                }
            }
        }
        
        //福袋组合规则
        if($params['sales_material_type'] == 7){
            //rate_price
            if(!isset($params['rate_price'])){
                $err_msg = "请添加组合贡献价";
                return false;
            }
            
            foreach ($params['rate_price'] as $priceKey => $val)
            {
                $params['rate_price'][$priceKey] = floatval($val);
            }
            
            //fudai_rate
            if(!isset($params['fudai_rate'])){
                $err_msg = "请添加福袋组合";
                return false;
            }
            
            $tmp_rate = 0;
            foreach ($params['fudai_rate'] as $val){
                $tmp_rate += $val;
            }
            
            if($tmp_rate > 100){
                $err_msg ="组合价格贡献百分比:". $tmp_rate .",已超100%";
                return false;
            }elseif($tmp_rate < 100){
                $err_msg ="分组合价格贡献百分比:". $tmp_rate .",不足100%";
                return false;
            }
        }
        
        return true;
    }

    /**
     * 检查基础物料个别参数是否可编辑
     * 
     * @param Int $bm_id
     * @return Array
     */
    function checkEditReadOnly($salesMaterialInfo){
        $orderObjObj = app::get('ome')->model('order_objects');
        
        //setting
        $readonly = array('type' => false,'shop'=>false,'bind_item'=>false);
        
        $shop_id = $salesMaterialInfo['shop_id'];
        $sm_id = $salesMaterialInfo['sm_id'];
        $is_bind = $salesMaterialInfo['is_bind'];
        $sales_material_type = $salesMaterialInfo['sales_material_type'];
        
        //如果销售物料有冻结、订单，那么物料类型不能变
        if($shop_id != '_ALL_'){
            $shopFreezeObj = app::get('material')->model('sales_material_shop_freeze');
            $storeInfo = $shopFreezeObj->getList('shop_freeze',array('sm_id'=>$sm_id,'shop_id'=>$shop_id), 0, 1);
            if($storeInfo[0]['shop_freeze'] > 0){
                $is_type_readonly = true;
            }
        }
        
        //objects
        $orderInfo = $orderObjObj->getList('obj_id,goods_id',array('goods_id'=>$sm_id), 0, 1);
        if($orderInfo){
            $has_object = true;
            $is_type_readonly = true;
            $is_shop_readonly = true;
        }

        if($is_type_readonly){
            $readonly['type'] = true;
        }

        if($is_shop_readonly){
            $readonly['shop'] = true;
        }
        
        //已绑定有订单的不能变
        $is_bind_item_readonly = false;
        if($is_bind == 1 && $has_object == true){
            //是否允许编辑销售物料(TB任务号：CROCS-234)
            //@todo：开会确认,使用订单冻结流水判断没有活动订单,允许编辑关联基础物料;
            $sql = "SELECT * FROM sdb_material_basic_material_stock_freeze WHERE sm_id=". $sm_id ." AND obj_type=1 AND num>0 LIMIT 0, 1";
            $freezeInfo = $orderObjObj->db->select($sql);
            if($freezeInfo){
                $is_bind_item_readonly = true;
            }
            
            //@todo：组合类型销售物料如果有未完成的订单,则不允许编辑
            if($sales_material_type == 2 && !$is_bind_item_readonly){
                $orderItemObj = app::get('ome')->model('order_items');
                $orderItemInfo = $orderItemObj->db->select("select item_id from sdb_ome_order_items as oi left join sdb_ome_order_objects as oo on oi.obj_id = oo.obj_id left join sdb_ome_orders o on oi.order_id = o.order_id where goods_id = ".$sm_id." and o.status='active' LIMIT 0,1");
                if($orderItemInfo){
                    $is_bind_item_readonly = true;
                }
            }
        }
        
        //是否允许编辑销售物料
        if($is_bind_item_readonly){
            $readonly['bind_item'] = true;
        }
        
        return $readonly;
    }
}
