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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2022/6/27 13:38:19
 * @describe: 猫超获取物料
 * ============================
 */
class inventorydepth_shop_maochao_skus {
    protected $intervalTime = 3600;

    public function syncMaterial() {
        $shops = app::get('ome')->model('shop')->getList('*', ['node_type'=>'taobao', 'business_type'=>'maochao']);
        if(empty($shops)) {
            return false;
        }
        foreach ($shops as $v) {
            $config = (array)@unserialize($v['config']);
            if($config['supplier_id']) {
                $supplier_id = explode('|', $config['supplier_id']);
                foreach ($supplier_id as $sid) {
                    $this->dealShopSupplier($v, trim($sid));
                }
            }
        }
    }

    public function dealShopSupplier($shop, $supplier_id) {
        $shop_id = $shop['shop_id'];
        $intervalTime = $this->intervalTime;
        $now = time();
        $key = 'apply-lastexectime-'.$shop_id.'-'.$supplier_id;
        base_kvstore::instance('inventorydepth/shop/maochao/skus')->fetch($key,$lastExecTime);
        if($lastExecTime && ($lastExecTime+$intervalTime)>$now) {
            return false;
        }
        base_kvstore::instance('inventorydepth/shop/maochao/skus')->store($key, $now);
        $nearDay = strtotime('-1 day');
        //获取近一天的， 避免某次请求失败
        $lastExecTime = $lastExecTime < $nearDay ? ($lastExecTime ? : strtotime('-1 year')) : $nearDay;
        $sdf = [
            'start_time' => date('Y-m-d H:i:s', $lastExecTime),
            'end_time' => date('Y-m-d H:i:s', $now),
            'supplier_id' => $supplier_id,
        ];
        $offset=1;$limit=100;
        do {
            $sdf['page'] = $offset;
            $sdf['page_size'] = $limit;
            $rs = kernel::single('erpapi_router_request')->set('shop',$shop_id)->product_skuAllGet($sdf);
            if(empty($rs['data']) && $sdf['page'] != 1) {
                break;
            }

            if ($rs['scroll_id']) {
                $sdf['scroll_id'] = $rs['scroll_id'];
            }


            $data = [];
            foreach ($rs['data'] as $item) {
                $tmp = array(
                    'outer_id' => $item['outer_id'],
                    'iid' => $item['iid'],
                    'title' => $item['title'],
                    'approve_status' => '',
                    'price' => '',
                    'num' => '',
                    'detail_url' => '',
                    'default_img_url' => '',
                    'props' => '',
                    'simple' => $item['sku'] ? 'false' : 'true',
                );
                if($item['sku']) {
                    $tmp['skus']['sku'][] = [
                            'outer_id' => $item['sku']['outer_id'],
                            'sku_id' => $item['sku']['sku_id'],
                            'barcode' => $item['sku']['barcode'],
                    ];
                }
                $data[] = $tmp;
            }
            if($data){
                $itemModel = app::get('inventorydepth')->model('shop_items');
                $skuModel = app::get('inventorydepth')->model('shop_skus');
                $skuModel->batchInsert($data,$shop,$stores);
                $itemModel->batchInsert($data,$shop,$stores);
            }
            $offset++;
        } while(true);
        return true;
    }
}