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
 * Class erpapi_shop_matrix_weimobv_request_product
 */
class erpapi_shop_matrix_weimobr_request_product extends erpapi_shop_request_product
{
    /**
     * 查询店铺缓存商品
     * @todo：微盟平台没有缓存商品接口，如果不添加此方法，定时任务请求会报错：没有绑定
     *
     * @param $sdf
     * @return array
     */
    public function queryCacheProduct($sdf = [])
    {
        $result = $this->succ('微盟没有店铺缓存商品接口，直接返回成功');
        
        return $result;
    }
}