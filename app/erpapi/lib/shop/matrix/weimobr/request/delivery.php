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
 * Created by PhpStorm.
 * User: ghc
 * Date: 18/11/20
 * Time: 上午11:32
 */
class erpapi_shop_matrix_weimobr_request_delivery extends erpapi_shop_request_delivery
{
    /**
     * 发货请求参数
     * 平台接口名：weimob_shop/fulfill/logistics/update
     * 平台接口地址：https://doc.weimobcloud.com/detail?menuId=19&childMenuId=1&tag=2452&id=3310&isold=2
     *
     * @return void
     * @author
     **/

    public function get_confirm_params($sdf)
    {
        $param = parent::get_confirm_params($sdf);
        
        //订单需要拆单
        if($sdf['is_split']==1 && !empty($sdf['oid_list'])){
            $goods = array();
            foreach($sdf['delivery_items'] as $key => $object){
                $obj = array();
                $obj['item_id'] = $object['oid'];
                $obj['sku_id'] = $object['shop_goods_id'];
                $obj['sku_num'] = $object['number'];
                $goods[] = $obj;
            }
            $param['is_split'] = 1;
            $param['goods'] = json_encode($goods);
        }
        
        // 履约细分类型：fulfillMethod，此字段必传;如果OMS系统未传值，矩阵默认为：1
        //@todo：当订单配送方式为 1（商家配送）时，履约细分类型 fulfillMethod 支持：1-快递物流、2-无需物流
        if($sdf['logi_type'] == 'virtual_delivery'){
            // 虚拟物流为：2
            $param['fulfillMethod'] = 2; // 官方平台字段名
            $param['fulfill_method'] = 2; // 矩阵字段名
        }else{
            // 默认为：1
            $param['fulfillMethod'] = 1; // 官方平台字段名
            $param['fulfill_method'] = 1; // 矩阵字段名
        }
        
        return $param;
    }


}