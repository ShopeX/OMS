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
 * 产品线通用Lib方法类
 * @author wangjianjun@shopex.cn
 * @version 2024.04.12
 */
class dealer_series{
    
    /**
     * 新增、编辑数据校验
     * @param $post 提交array
     * @param $error_msg 报错信息（引用方式）
     * @return boolean
     */
    public function check_series_data($post,&$error_msg){
        if(!$post["series_code"] || !$post["series_name"] || !$post["description"] || !$post["cat_name"]){
            $error_msg = "产品线编码、名称、描述、分类不能为空。";
            return false;
        }
        if(!$post["betc_id"]){
            $error_msg = "产品线所属贸易公司不能为空。";
            return false;
        }
        // if(empty($post["bm_id"]) || !is_array($post["bm_id"])){
        //     $error_msg = "产品线绑定基础物料不能为空。";
        //     return false;
        // }
        // if(empty($post["shop_id"]) || !is_array($post["shop_id"])){
        //     $error_msg = "产品线绑定经销店铺不能为空。";
        //     return false;
        // }
        return true;
    }
    
}