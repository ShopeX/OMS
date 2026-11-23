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
 * @Describe: 外部优仓商品请求类
 */

class dchain_event_trigger_dchain_product
{
    
    /**
     * @param array $data sku数据
     * @param array $bnList skuList中所有bn
     * @param array $shop 店铺信息
     * @return bool
     */
    public function addProduct($data, $bnList, $shop)
    {
        if (empty($shop) || empty($data)) {
            return false;
        }
        //判断优仓是否金额创建回写
        $dchainBranch = app::get('channel')->model('channel')->db_dump(array(
            'node_id'      => $shop['node_id'],
            'channel_type' => 'dchain'
        ));
        if (!$dchainBranch) {
            return false;
        }
        $this->_process_retry($data, $bnList, $shop);
    }
    
    private function _process_retry($data, $bnList, $shop)
    {
        $sdf = kernel::single('dchain_event_trigger_dchain_data_product_router')
            ->set_shop_id($shop['shop_id'])
            ->init($data, $bnList, $shop)
            ->get_sdf();
        if (!$sdf) {
            return;
        }
        
        $itemMappings = array();
        //商品创建或者更新
        if ($sdf['create_data']) {
            $params = array_chunk($sdf['create_data'], 30);
            foreach ($params as $v) {
                $param          = array();
                $param['items'] = $v;
                $result         = kernel::single('erpapi_router_request')->set('dchain', $shop['shop_id'])->product_create($param);
                if ($result['rsp'] == 'succ' && !empty($result['data'])) {
                    kernel::single('dchain_branch_product')->saveForeignSku($sdf['product_detail_list'], $result['data'], $sdf['dchain_branch']);
                }
            }
        }
        
        if ($sdf['create_pkg_data']) {
            $params = array_chunk($sdf['create_pkg_data'], 30);
            foreach ($params as $v) {
                $param                  = array();
                $param['items']         = $v;
                $param['pkg_items_list'] = $sdf['pkg_items_list'];
                $result = kernel::single('erpapi_router_request')->set('dchain', $shop['shop_id'])->product_create_pkg($param);
                if ($result['rsp'] == 'succ' && !empty($result['data'])) {
                    kernel::single('dchain_branch_product')->saveForeignSku($sdf['product_detail_list'], $result['data'], $sdf['dchain_branch']);
                }
            }
        }
    
        //创建同货号的平台商品到优仓商品列表
        $foreign_sku_detail['detail']['detail_item'] = $sdf['foreign_sku_detail'];
        $inventorydepth_data = $sdf['inventorydepth_data'];
        if ($foreign_sku_detail && $inventorydepth_data) {
            kernel::single('dchain_branch_product')->saveForeignSku($inventorydepth_data, $foreign_sku_detail, $sdf['dchain_branch']);
        }
    }
}