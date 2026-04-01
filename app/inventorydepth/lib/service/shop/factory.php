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
* 店铺商品处理工厂类
* 
* chenping<chenping@shopex.cn>
*/
class inventorydepth_service_shop_factory
{
    
    function __construct(&$app)
    {
        $this->app = $app;
    }

    /**
     * 工厂方法
     *
     * @return inventorydepth_service_shop_taobao
     * @author 
     **/
    public static function createFactory($shop_type,$business_type='zx')
    {
        switch ($shop_type) {
            case 'taobao':
                if($business_type == 'maochao') {
                    return false;
                }
                if ($business_type == 'fx') {
                    return kernel::single('inventorydepth_service_shop_tbfx');
                } else {
                    return kernel::single('inventorydepth_service_shop_taobao');
                }
                break;
            case '360buy':
                return kernel::single('inventorydepth_service_shop_360buy');
                break;
            case 'paipai':
                return kernel::single('inventorydepth_service_shop_paipai');
                break;
            case 'yihaodian':
                return kernel::single('inventorydepth_service_shop_yihaodian');
                break;
            case 'ecos.b2c':
                return kernel::single('inventorydepth_service_shop_ecstore');
                break;
           case 'bbc':
               return kernel::single('inventorydepth_service_shop_bbc');
               break;
               case 'ecos.b2b2c.stdsrc':
               return kernel::single('inventorydepth_service_shop_b2b2c');
               break;
            case 'luban':
                return kernel::single('inventorydepth_service_shop_luban');
                break;
            case 'alibaba':
                return kernel::single('inventorydepth_service_shop_alibaba');
                break;
            case 'dewu':
                return kernel::single('inventorydepth_service_shop_dewu');
                break;
            case 'huawei':
                return kernel::single('inventorydepth_service_shop_huawei');
                break;
            case 'kuaishou':
                return kernel::single('inventorydepth_service_shop_kuaishou');
            case 'aikucun':
                return kernel::single('inventorydepth_service_shop_aikucun');
            case 'vop':
                return kernel::single('inventorydepth_service_shop_vop');
            case 'pinduoduo':
                return kernel::single('inventorydepth_service_shop_pinduoduo');
               break;
            case 'zkh':
                return kernel::single('inventorydepth_service_shop_zkh');
            case 'meituan4bulkpurchasing':
                return kernel::single('inventorydepth_service_shop_meituan4bulkpurchasing');
                break;
            case 'xhs':
                return kernel::single('inventorydepth_service_shop_xhs');
                break;    
            case 'wxshipin':
                return kernel::single('inventorydepth_service_shop_wxshipin');
                break;
            case 'weimobr':
                return kernel::single('inventorydepth_service_shop_weimobr');
                break;
            default:
                return false;
                break;
        }
    }
}
