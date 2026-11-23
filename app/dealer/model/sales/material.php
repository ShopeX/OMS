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
 * 销售物料
 */

class dealer_mdl_sales_material extends dbeav_model
{

    public function searchOptions($value = '')
    {
        $arr = parent::searchOptions();
        return array_merge($arr, array(
            'betc_name' => __('所属贸易公司'),
        ));
    }

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = ' 1 ';
        if ($filter['shop_id']) {
            //$where .= " AND shop_id IN (SELECT shop_id FROM sdb_ome_shop WHERE delivery_mode='shopyjdf' AND shop_id='" . $filter['shop_id'] . "')";
            //unset($filter['shop_id']);
            
            //查询经销店铺(支持一次查询多个)
            $shopMdl = app::get('ome')->model('shop');
            $tempList = $shopMdl->getList('shop_id', array('shop_id'=>$filter['shop_id']), 0, -1);
            $shopIds = ($tempList ? array_column($tempList, 'shop_id') : '-1');
            
            //shop_id
            $filter['shop_id'] = $shopIds;
            
            //unset
            unset($tempList, $shopIds);
        }

        if ($filter['betc_name']) {
            $betcMdl  = app::get('dealer')->model('betc');
            $betcInfo = $betcMdl->db_dump(['betc_name' => $filter['betc_name']]);
            if ($betcInfo && $betcInfo['cos_id']) {
                $treeList = kernel::single('organization_cos')->getCosList($betcInfo['cos_id']);
                if ($treeList[0]) {
                    $cosIdArr = array_unique(array_column($treeList[1], 'cos_id'));
                    $where .= " AND cos_id IN ('" . implode("','", $cosIdArr) . "')";
                } else {
                    $where .= " AND cos_id=0";
                }
            } else {
                $where .= " AND cos_id=0";
            }
            unset($filter['betc_name']);
        }
        return parent::_filter($filter, $tableAlias, $baseWhere) . " AND " . $where;
    }

    public function modifier_shop_id($shop_id, $list, $row)
    {
        // if ($this->is_export_data) {
        //     return $tel;
        // }
        static $shopList;
        if (!isset($shopList[$shop_id])) {
            $shopMdl   = app::get('ome')->model('shop');
            $shopIdArr = array_column($list, 'shop_id');
            $shopList  = $shopMdl->getList('*', ['shop_id|in' => $shopIdArr]);
            $shopList  = array_column($shopList, null, 'shop_id');
        }
        return $shopList[$shop_id]['name'];
    }

    public function modifier_sales_material_type($sales_material_type)
    {
        switch ($sales_material_type) {
            case '1':
                $info = '普通';
                break;
            case '2':
                $info = '组合';
                break;
            case '3':
                $info = '赠品';
                break;
            default:
                $info = '';
                break;
        }
        return $info;
    }
}
