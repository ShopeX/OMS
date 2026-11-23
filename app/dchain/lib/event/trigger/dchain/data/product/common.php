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
 * @Describe: 外部优仓商品请求公共类
 */
class dchain_event_trigger_dchain_data_product_common
{
    protected $__sdf = array();
    
    final public function init($data, $bnList, $shop)
    {
        $this->__sdf['data']   = $data;
        $this->__sdf['bnList'] = $bnList;
        $this->__sdf['shop']   = $shop;
        
        return $this;
    }
    
    /**
     * @param Int $delivery_id 如果是合并发货单，取的是子单
     *
     * @return void
     * @author
     **/
    public function get_sdf()
    {
        // 获取优仓信息
        $this->_get_dchain_branch();
        // 获取重组后的请求数据
        $this->_format_product_data();
        //获取捆绑子商品信息
        $this->_get_pkg_item_list();
        
        return $this->__sdf;
    }
    
    /**
     * 获取发货单
     *
     * @return void
     * @author
     **/
    final protected function _get_dchain_branch()
    {
        $dchainBranch = app::get('channel')->model('channel')->db_dump(array(
            'node_id'      => $this->__sdf['shop']['node_id'],
            'channel_type' => 'dchain'
        ));
        
        $this->__sdf['dchain_branch'] = $dchainBranch;
    }
    
    public function _format_product_data()
    {
        $foreignSkuMdl    = app::get('dchain')->model('foreign_sku');
        $salesMaterialMdl = app::get('material')->model('sales_material');
        //查询已创建的关联信息
        $foreignSkus   = $foreignSkuMdl->getList('inner_sku as shop_product_bn,shop_sku_id,shop_product_id,sync_status,mapping_status,outer_sku,outer_sku_id,inner_type',
            array('inner_sku' => $this->__sdf['bnList'], 'dchain_id' => $this->__sdf['dchain_branch']['channel_id'],'sync_status'=>'3'));
        $foreignSkus   = array_column($foreignSkus, null, 'shop_product_bn');
        $createData    = array();
        $createPkgData = array();
        
        foreach (
            $salesMaterialMdl->getList('sm_id as product_id,sales_material_bn as bn,sales_material_name as product_name',
                array('sales_material_bn'   => $this->__sdf['bnList'],
                      'sales_material_type' => 1,
                      'is_bind'             => 1
                )) as $value
        ) {
            $products[$value['bn']] = $value;
        }
        
        foreach (
            $salesMaterialMdl->getList('sm_id as product_id,sales_material_bn as bn,sales_material_name as product_name',
                array('sales_material_bn'   => $this->__sdf['bnList'],
                      'sales_material_type' => 2,
                      'is_bind'             => 1
                )) as $value
        ) {
            $pkgs[$value['bn']] = $value;
        }
        
        //创建或者编辑
        foreach ($this->__sdf['data'] as $key => $value) {
            unset($value['id']);
            if ($value['shop_product_bn'] == '0') {
                continue;
            }
            $foreignSkusItem = $foreignSkus[$value['shop_product_bn']];
 
            //普通商品与捆绑商品赋值处理
            $inner_type            = 0;
            $value['product_id']   = $products[$value['shop_product_bn']]['product_id'];
            $value['product_name'] = $products[$value['shop_product_bn']]['product_name'];
            if (isset($pkgs[$value['shop_product_bn']])) {
                $inner_type                     = 1;
                $value['product_id']            = $pkgs[$value['shop_product_bn']]['product_id'];
                $value['product_name']          = $pkgs[$value['shop_product_bn']]['product_name'];
                $this->__sdf['pkg_goods_ids'][] = $pkgs[$value['shop_product_bn']]['product_id'];
            }
            $value['inner_type'] = $inner_type;

            if (!$inner_type) {
                $createData[$value['shop_product_bn']] = $value;
            } else {
                $createPkgData[$value['shop_product_bn']] = $value;
            }
            //商品同步成功
            if ($foreignSkusItem['sync_status'] == '3') {
                if ($foreignSkusItem['inner_type']) {
                    $foreignSkusItem['combine_sc_item_code'] = $foreignSkusItem['outer_sku'];
                    $foreignSkusItem['combine_sc_item_id']   = $foreignSkusItem['outer_sku_id'];
                } else {
                    $foreignSkusItem['sc_item_code'] = $foreignSkusItem['outer_sku'];
                    $foreignSkusItem['sc_item_id']   = $foreignSkusItem['outer_sku_id'];
                }
                $foreignSkusItem['success']           = true;
                $this->__sdf['foreign_sku_detail'][]  = $foreignSkusItem;
                $this->__sdf['inventorydepth_data'][] = $value;
                continue;
            }
            $this->__sdf['product_detail_list'][] = $value;
        }
        
        $this->__sdf['create_data']     = $createData;
        $this->__sdf['create_pkg_data'] = $createPkgData;
    }
    
    final protected function _get_pkg_item_list()
    {
        $basicMaterialMdl      = app::get('material')->model('basic_material');
        $salesBasicMaterialMdl = app::get('material')->model('sales_basic_material');
        $pgkProductList        = $salesBasicMaterialMdl->getList('sm_id,number,bm_id', array('sm_id' => $this->__sdf['pkg_goods_ids']));
        $bmIds                 = array_column($pgkProductList, 'bm_id');
        $materialList          = $basicMaterialMdl->getList('bm_id,material_bn', array('bm_id' => $bmIds));
        $materialList          = array_column($materialList, null, 'bm_id');
        $pkgItems              = array();
        foreach ($pgkProductList as $key => $value) {
            $item                         = array();
            $item['sc_item_code']         = $materialList[$value['bm_id']]['material_bn'];
            $item['quantity']             = $value['number'];
            $pkgItems[$value['sm_id']][] = $item;
        }
        $this->__sdf['pkg_items_list'] = $pkgItems;
    }
    
}
