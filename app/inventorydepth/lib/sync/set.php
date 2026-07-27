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

class inventorydepth_sync_set {
    //skuid 同步
    private $support = [
        'taobao' => '淘宝/天猫',
        'luban'     => '抖音',
        'pinduoduo' => '拼多多',
        '360buy'    => '京东',
        'vop'       => '唯品会',
        'meituan4bulkpurchasing' => '美团电商',
        'website' => '官网',
    ];
    //增量 全量
    private $mode_support = [
        'taobao' => '淘宝/天猫'
    ];

    public function getSupportName() {
        return implode(',', $this->support);
    }

    public function getModeSupport() {
        return array_keys($this->mode_support);
    }

    public function getModeSupportName() {
        return implode(',', $this->mode_support);
    }

    /**
     * 获取库存回写触发依据。
     *
     * 历史版本固定使用可售库存判断是否需要回写。为保证未初始化配置的站点
     * 升级后行为不变，仅当配置明确选择 store 时才改用回写库存比较。
     *
     * @return string actual_stock：可售库存；store：回写库存
     */
    public function getStockTriggerBasis()
    {
        $triggerBasis = app::get('inventorydepth')->getConf('stock.sync.trigger_basis');

        if ($triggerBasis === 'store') {
            return 'store';
        }

        return 'actual_stock';
    }

    // 是否开启并支持增量库存回写
    public function isModeSupportInc($shop_type = '')
    {
        if (!$this->mode_support[$shop_type]) {
            return false;
        }
        $modeConf = app::get('inventorydepth')->getConf('stock.sync.mode');
        if ($modeConf == 'increment') {
            return true;
        }
        return false;
    }

    public function getUnRequestBn($shop, $products) {
        $skuModel = app::get('inventorydepth')->model('shop_skus');
        $arrBn = array_column($products, 'sales_material_bn');
        if($this->isUseSkuid($shop)) {
            $filter = [
                'mapping' => '1',
                'request' => 'true',
                'shop_id' => $shop['shop_id'],
                'shop_product_bn' => $arrBn
            ];
            $skuList = $skuModel->getList('shop_product_bn', $filter);
            $unRequest = array_diff($arrBn, array_map('current',$skuList));
            return $unRequest;
        }
        $filter = [
            'mapping' => '1',
            'request' => 'false',
            'shop_id' => $shop['shop_id'],
            'shop_product_bn' => $arrBn
        ];
        $skuList = $skuModel->getList('shop_product_bn', $filter);
        $unRequest = array();
        if ($skuList) { $unRequest = array_map('current',$skuList); }
        return $unRequest;
    }

    public function isUseSkuid($shop) {
        return (app::get('inventorydepth')->getConf('stock.sync.set') == 'skuid'
        && in_array($shop['node_type'], array_keys($this->support)));
    }
}
