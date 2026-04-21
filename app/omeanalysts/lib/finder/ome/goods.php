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

class omeanalysts_finder_ome_goods{
	var $detail_basic = '基础物料详情';

    /**
     * detail_basic
     * @param mixed $goods_id ID
     * @return mixed 返回值
     */
    public function detail_basic($goods_id) {
        $filter = array(
            'goods_id'=>$goods_id,
            'time_from' => $_GET['time_from'],
            'time_to' => $_GET['time_to'],
        );
		$render = app::get('omeanalysts')->render();
		$productObj = app::get('omeanalysts')->model('ome_products');
		$products = $productObj->getlist('*',$filter);

		$render->pagedata['products'] = $products;
		
		return $render->display('ome/detail_goods.html');
	}
}
