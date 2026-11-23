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
 * 请求同步翱象商品关系Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2023.03.16
 */
class dchain_autotask_mappingproduct
{
    /**
     * 请求同步翱象商品关系
     * @param $params
     * @param $error_msg
     * @return bool
     */
    public function process($params, &$error_msg='')
    {
        $axInventoryLib = kernel::single('dchain_inventorydepth');
        
        //params
        $shop_id = $params['shop_id'];
        $shop_bn = $params['shop_bn'];
        $task_page = $params['task_page'];
        $product_type = $params['product_type'];
        $product_bns = json_decode($params['product_bns'], true);
        
        //check
        if(empty($shop_id) || empty($product_bns)){
            return true;
        }
        
        if(!is_array($product_bns)){
            return true;
        }
        
        //sdfdata
        $sdfdata = array(
                'shop_id' => $shop_id,
                'product_bns' => $product_bns,
        );
        
        //推送平台商品关系
        $result = $axInventoryLib->reqeustMappingProduct($sdfdata);
        
        return true;
    }
}