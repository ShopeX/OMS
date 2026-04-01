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

/***
 * 导入供应商基础物料数据
 */
class purchase_supplier_goods_to_import
{
    function run(&$cursor_id, $params)
    {
        $supGoodsObj         = app::get('purchase')->model('supplier_goods');
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $supplierObj         = app::get('purchase')->model('supplier');
        
        $sdfdata    = $params['sdfdata'];
        foreach($sdfdata as $val)
        {
            //组织数据
            $data    = array(
                    'supplier_id'=>$val['supplier_id'],
                    'bm_id'=>$val['bm_id'],
            );
            
            //检查供应商
            $tempData   = $supplierObj->dump(array('supplier_id'=>$data['supplier_id']), 'supplier_id');
            if(empty($tempData))
            {
                return false;//供应商不存在
            }
            
            //检查基础物料
            $tempData   = $basicMaterialObj->dump(array('bm_id'=>$data['bm_id']), 'bm_id');
            if(empty($tempData))
            {
                return false;//基础物料不存在
            }
            
            //检查数据是否已存在
            $tempData    = $supGoodsObj->dump(array('supplier_id'=>$data['supplier_id'], 'bm_id'=>$data['bm_id']), '*');
            if($tempData)
            {
                continue;//跳过，数据已存在
            }
            
            //插入
            $supGoodsObj->insert($data);
        }
        
        return false;
    }
}