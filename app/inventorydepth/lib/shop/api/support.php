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
* 接口所支持的店铺
*/
class inventorydepth_shop_api_support
{
    
    function __construct($app)
    {
        $this->app = $app;
    }
    
    //支持单个拉取商品的店铺类型
    public static $item_sku_get_shops = array('taobao','paipai','360buy', 'ecos.b2c','bbc','ecos.b2b2c.stdsrc');
    
    /**
     * 接口store.item.sku.get
     *
     * @param String $shop_type 店铺类型
     * @return void
     * @author 
     **/
    public static function item_sku_get_support($shop_type)
    {
        return in_array($shop_type,self::$item_sku_get_shops);
    }
    
    //支持拉取商品的店铺类型
    public static $items_all_get_shops = array('taobao','paipai','360buy', 'ecos.b2c','bbc','ecos.b2b2c.stdsrc','luban','alibaba','dewu','huawei','kuaishou','vop','pinduoduo','zkh','meituan4bulkpurchasing','weimobr');
    
    //不显示前端购物小车的店铺类型
    public static $stock_shop_type_not_support = array('alibaba','suning','amazon', 'kaola','juanpi','cmb','haoshiqi','vop','eyee','yunji','congminggou','gegejia');
    
    //不支持库存回写的店铺类型
    public static $no_write_back_stock = array('haoshiqi', 'yunji','congminggou','gegejia','yunji4pop');
    
    /**
     * 接口store.item.all.get
     *
     * @return void
     * @author 
     **/
    public static function items_all_get_support($shop_type,$business_type='zx')
    {
        return in_array($shop_type,self::$items_all_get_shops);
    }

    public static function stock_get_not_support($shop_type)
    {
        return !in_array($shop_type, self::$stock_shop_type_not_support);
    }
    
    //支持批量拉取商品的店铺类型
    public static $items_get_shops = array('taobao','paipai','360buy', 'ecos.b2c','bbc','ecos.b2b2c.stdsrc','alibaba','weimobr');
    
    /**
     * 接口store.item.get 
     *
     * @return void
     * @author 
     **/
    public static function items_get_support($shop_type)
    {
        return in_array($shop_type,self::$items_get_shops);
    }
    
    /**
     * 禁止上下架店铺类型
     *
     * @param String $shop_type 店铺类型
     * @return void
     * @author wangbiao@shopex.cn
     **/
    public static $prohibit_onsale_shops = array('ecos.b2c');
    public static function prohibit_onsale_shops($shop_type)
    {
        return in_array($shop_type, self::$prohibit_onsale_shops);
    }
}