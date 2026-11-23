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
 * @Author: xueding@shopex.cn
 * @Datetime: 2022/4/24
 * @Describe: 外部优仓商品请求路由
 */
class dchain_event_trigger_dchain_data_product_router
{
    private $__shop_id;

    public function set_shop_id($shop_id)
    {
        $this->__shop_id = $shop_id;

        return $this;
    }

    public function __call($method,$args)
    { 
        $platform = kernel::single('dchain_event_trigger_dchain_data_product_common');

        if ($this->__shop_id) {
            $shop = $this->get_shop();

            try {
                $node_type = $shop['node_type'];

                $platform = kernel::single('ome_event_trigger_dchain_data_product_'.$node_type);
            } catch (Exception $e) {}    
        }

        return call_user_func_array(array($platform,$method), $args);
    }

    private function get_shop()
    {
        static $shops;

        if ($shops[$this->__shop_id]) return $shops[$this->__shop_id];

        $shops[$this->__shop_id] = app::get('ome')->model('shop')->dump($this->__shop_id);

        return $shops[$this->__shop_id];
    }
}