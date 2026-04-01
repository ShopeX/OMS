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
 * 基础物料数据验证Lib类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: check.php 2016-08-03 15:00
 */
class material_basic_check
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
        $basicMaterialObj = app::get('material')->model('basic_material');

        //新增标记
        $is_new_add    = $params['edit'] ? false : true;
        unset($params['edit']);
        
        // 去除基础物料编码前后空格
        if (isset($params['material_bn'])) {
            $params['material_bn'] = trim($params['material_bn']);
        }
        if (isset($params['material_spu'])) {
            $params['material_spu'] = trim($params['material_spu']);
        }
        if (isset($params['material_code'])) {
            $params['material_code'] = trim($params['material_code']);
        }

        //检查物料名称
        if(empty($params['material_name'])){
            $err_msg    ="物料名称不能为空";
            return false;
        }

        if(empty($params['material_bn'])){
            $err_msg    = "物料编号不能为空";
            return false;
        }
        
        if(trim($params["material_spu"])){ //物料款号有值
            $reg_bn_code_spu = "/^[0-9a-zA-Z\_\#\-\/ ]*$/";
            if(!preg_match($reg_bn_code_spu,$params["material_spu"])){
                $err_msg = "物料款号只支持(数字、英文、_下划线、-横线、#井号、/斜杠)组成";
                return false;
            }
            // 额外检查：不允许连续斜杠
            if (strpos($params["material_spu"], '//') !== false) {
                $err_msg = "物料款号中不允许出现连续斜杠（//）";
                return false;
            }
        }

        //检查有效性
        if($is_new_add)
        {
            //判断物料编码和物料条码只能是由数字英文下划线组成
            // 物料编号：支持数字、英文、下划线、横线、井号、斜杠、空格
            $reg_bn = "/^[0-9a-zA-Z\_\#\-\/ ]*$/";
            if(!preg_match($reg_bn,$params["material_bn"])){
                $err_msg = "物料编码只支持(数字、英文、_下划线、-横线、#井号、/斜杠、空格)";
                return false;
            }
            // 物料条码：支持数字、英文、下划线、横线、井号、斜杠（不支持空格）
            $reg_code = "/^[0-9a-zA-Z\_\#\-\/]*$/";
            if(!preg_match($reg_code,$params["material_code"])){
                $err_msg = "物料条码只支持(数字、英文、_下划线、-横线、#井号、/斜杠)组成";
                return false;
            }

            $basicMaterialInfo = $basicMaterialObj->getList('material_bn',array('material_bn'=>$params['material_bn']));
            if($basicMaterialInfo){
                $err_msg ="当前新增的物料编码已存在，不能重复创建";
                return false;
            }

            //检查物料条码
            $barcode = app::get('material')->model('barcode')->getList('bm_id',array('code'=>$params['material_code'], 'type' => material_codebase::getBarcodeType()));
            if($barcode){
                $err_msg ="物料条码:". $params['material_code'] ." 已被使用，不能重复使用";
                return false;
            }

            $params['material_bn_crc32'] = sprintf('%u',crc32($params['material_bn']));
        }
        else
        {
            if(empty($params['bm_id'])){
                $err_msg ="基础物料bm_id不能为空";
                return false;
            }

            $basicMaterialExistInfo = $basicMaterialObj->getList('bm_id',array('material_bn'=>$params['material_bn'], 'bm_id|noequal'=>$params['bm_id']));
            if($basicMaterialExistInfo){
                $err_msg ="提交的物料编码已存在,请检查";
                return false;
            }
            //条码是否重复检测
            $barcode = app::get('material')->model('barcode')->getList('bm_id',array('code'=>$params['material_code'],'bm_id|noequal'=>$params['bm_id']));
            if($barcode){
                $err_msg ="物料条码:". $params['material_code'] ." 已被使用，不能重复使用";
                return false;
            }
        }

        //如果是成品基础物料，识别关联的半成品数量必须大于0
        if($params['type'] == 1){
            if(isset($params['at'])){
                foreach ($params['at'] as $val){
                    if ($val < 1){
                        $err_msg ="数量必须大于0";
                        return false;
                    }
                }
            }
        }
        list($checkrs,$errmsg) = $this->validatePageConfigs($params);
        if(!$checkrs){
            $err_msg = $errmsg;
            return false;
        }
        #检验保质期监控配置
        $use_expire    = intval($params['use_expire']);
        $warn_day      = intval($params['warn_day']);
        $quit_day      = intval($params['quit_day']);
        if($use_expire == 1 && ($warn_day <= $quit_day))
        {
            $err_msg = "预警天数必须大于自动退出库存天数";
            return false;
        }

        #特殊扫码验证
        if($params['special_setting'] == '3')
        {
            if(!is_numeric($params['first_num']) || !is_numeric($params['last_num']))
            {
                $err_msg = "必须输入数字";
                return false;
            }

            #条码的后一个数必须大于前一个
            if($params['last_num'] - $params['first_num'] <= 0)
            {
                $err_msg = "因开启特殊扫码配置，特殊扫码结束位数 减 特殊扫码开始位数要大于0";
                return false;
            }

            #扫码的位数长度要等于输入的条码长度
            $code_len = strlen($params['material_code']);
            $scan_len = $params['last_num'] - $params['first_num'] + 1;
            if($code_len - $scan_len != 0)
            {
                $err_msg = "条码长度与扫码配置长度不相等";
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
    function checkEditReadOnly($bm_id){
        $readonly = array('type' => false);

        //如果基础物料有库存、冻结、采购、订单或者绑定过成品、半成品，那么物料属性不能编辑
        $basicMStockObj = app::get('material')->model('basic_material_stock');
        $storeInfo = $basicMStockObj->getList('store,store_freeze',array('bm_id'=>$bm_id));

        //根据基础物料ID获取对应的冻结库存
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $storeInfo[0]['store_freeze']    = $basicMStockFreezeLib->getMaterialStockFreeze($bm_id);

        if($storeInfo[0]['store'] > 0 || $storeInfo[0]['store_freeze'] > 0){
            $is_type_readonly = true;
        }

        $purchaseItemObj = app::get('purchase')->model('po_items');
        $purchaseInfo = $purchaseItemObj->getList('product_id',array('product_id'=>$bm_id));
        if($purchaseInfo){
            $is_type_readonly = true;
            // $is_use_expire_readonly = true;
        }

        //基础物料编辑页面打不开，直接报500，处理方式为停止检测订单、采购退货、出入库、转储等单据
//        $orderItemObj = app::get('ome')->model('order_items');
//        $orderInfo = $orderItemObj->getList('product_id',array('product_id'=>$bm_id));
//        if($orderInfo){
//            $is_type_readonly = true;
//        }

//出入库明细各种情况
        //采购退货
//        $returnItemObj = app::get('purchase')->model('returned_purchase_items');
//        $returnitemInfo = $returnItemObj->dump(array('product_id'=>$bm_id),'product_id');
//        if ($returnitemInfo) $is_type_readonly = true;
//其他出入库
//        $isoitemObj = app::get('taoguaniostockorder')->model('iso_items');
//        $iso_list = $isoitemObj->dump(array('product_id'=>$bm_id),'product_id');
//        if ($iso_list) $is_type_readonly = true;
//转储
//        $stockdumpObj = app::get('console')->model('stockdump_items');
//        $stockdump_list = $stockdumpObj->dump(array('product_id'=>$bm_id),'product_id');
//        if ($stockdump_list) $is_type_readonly = true;

        $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
        $basicMaterialCombinationInfo = $basicMaterialCombinationItemsObj->getList('bm_id',array('bm_id'=>$bm_id));
        if($basicMaterialCombinationInfo){
            $is_type_readonly = true;
        }

        $basicMaterialCombinationPInfo = $basicMaterialCombinationItemsObj->getList('pbm_id',array('pbm_id'=>$bm_id));
        if($basicMaterialCombinationPInfo){
            //$is_type_readonly = true;
        }

        //如果有批次明细就不能变更保质期的开关
        $usefulLifeObj = app::get('console')->model('useful_life');
        $expireItemsInfo = $usefulLifeObj->getList('product_id',array('product_id'=>$bm_id, 'num|than'=>0), 0, 1);
        if($expireItemsInfo){
            $is_use_expire_readonly = true;
        }

        //类目绑定后什么情况下可以解绑换成别的?需要判断么?

        if($is_type_readonly){
            $readonly['type'] = true;
        }

        if($is_use_expire_readonly){
            $readonly['use_expire'] = true;
        }

        return $readonly;
    }

    /**
     * 验证页面配置项
     */
    public function validatePageConfigs($post)
    {
        $pageConfigs = app::get('desktop')->model('pagecols_setting')->getTableConfigs('material_basic_material');
       
        // 字段名称映射，将技术字段名转换为用户友好的显示名称
        $fieldNameMap = [];
        $list         = kernel::servicelist('set_pagecols_setting');
        foreach ($list as $k => $obj) {
            if (method_exists($obj, 'get_pagecols_setting')) {
                $settingData = $obj->get_pagecols_setting('material_basic_material');
                if (isset($settingData['elements'])) {
                    $fieldNameMap = $settingData['elements'];
                }
            }
        }
      
        foreach ($pageConfigs as $config) {
            $colKey = $config['col_key'];
            $formFieldKey = isset($fieldKeyMap[$colKey]) ? $fieldKeyMap[$colKey] : $colKey;
          
            if ($config['is_required'] && empty($post[$formFieldKey])) {
                // 使用友好的字段名称
                $fieldName = isset($fieldNameMap[$colKey]) ? $fieldNameMap[$colKey]['name'] : $colKey;

                return [false,"{$fieldName} 必填"];
            }
        }
        return [true];
    }

}
