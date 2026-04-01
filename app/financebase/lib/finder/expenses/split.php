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
 * @DateTime: 2020/12/3 18:00:11
 * @describe: 费用均摊明细
 * ============================
 */
class financebase_finder_expenses_split {
    public $addon_cols = 'bm_id,order_bn,shop_id';

    public $column_skucode = "基础物料编码";
    public $column_skucode_width = "80";
    /**
     * column_skucode
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_skucode($row) {
        $bmId = $row[$this->col_prefix . 'bm_id'];
        $bm = app::get('material')->model('basic_material')->db_dump(array('bm_id'=>$bmId), 'material_bn');
        return (string)$bm['material_bn'];
    }

    public $column_verification_status = "核销状态";
    public $column_verification_status_width = "100";
    public function column_verification_status($row, $list) {
        $orderBn = $row[$this->col_prefix . 'order_bn'];
        if (empty($orderBn)) {
            return '-';
        }
        
        // 批量查询优化：使用静态缓存确保整个列表只查询一次数据库
        static $verificationStatusCache;
        
        if (!isset($verificationStatusCache)) {
            $verificationStatusCache = array();
            
            // 从传入的list参数获取当前页面的所有订单号
            $orderBns = array();
            foreach ($list as $listRow) {
                $bn = $listRow[$this->col_prefix . 'order_bn'];
                if (!empty($bn)) {
                    $orderBns[] = $bn;
                }
            }
            $orderBns = array_unique($orderBns);
            
            if (!empty($orderBns)) {
                // 批量查询所有订单号的核销状态
                $monthlyReportItemsModel = app::get('finance')->model('monthly_report_items');
                $items = $monthlyReportItemsModel->getList(
                    'order_bn,verification_status',
                    array('order_bn' => $orderBns)
                );
                
                // 构建缓存映射：order_bn => verification_status
                foreach ($items as $item) {
                    $verificationStatusCache[$item['order_bn']] = $item['verification_status'];
                }
            }
        }
        
        // 从缓存中获取核销状态
        $verificationStatus = $verificationStatusCache[$orderBn] ?? null;
        
        if (empty($verificationStatus)) {
            return '-';
        }
        
        $statusMap = array(
            '1' => '未核销',
            '2' => '已核销',
        );
        
        return $statusMap[$verificationStatus] ?? '-';
    }

    public $column_shop_name = "店铺名称";
    public $column_shop_name_width = "120";
    public $column_shop_name_order = 20;
    public function column_shop_name($row, $list) {
        $shopId = $row[$this->col_prefix . 'shop_id'];
        if (empty($shopId)) {
            return '-';
        }
        
        // 批量查询优化：使用静态缓存确保整个列表只查询一次数据库
        static $shopCache;
        
        if (!isset($shopCache)) {
            $shopCache = array();
            
            // 从传入的list参数获取当前页面的所有店铺ID
            $shopIds = array();
            foreach ($list as $listRow) {
                $sid = $listRow[$this->col_prefix . 'shop_id'];
                if (!empty($sid)) {
                    $shopIds[] = $sid;
                }
            }
            $shopIds = array_unique($shopIds);
            
            if (!empty($shopIds)) {
                // 批量查询所有店铺信息
                $shopModel = app::get('ome')->model('shop');
                $shops = $shopModel->getList(
                    'shop_id,name',
                    array('shop_id' => $shopIds)
                );
                
                // 构建缓存映射：shop_id => name
                foreach ($shops as $shop) {
                    $shopCache[$shop['shop_id']] = $shop['name'];
                }
            }
        }
        
        // 从缓存中获取店铺名称
        return $shopCache[$shopId] ?? '-';
    }

    public $column_platform = '平台规则设置（蓝色：已设置、红色：未设置)';
    public $column_platform_width = "500";
    public $column_platform_order = 21;
    public function column_platform($row) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }

        $finder_id = $_GET['_finder']['finder_id'];
        $oFunc = kernel::single('financebase_func');
        $platform = $oFunc->getShopPlatform();

        $ret = "";

        // 通过 bill_category 获取规则内容
        $ruleModel = app::get('financebase')->model('bill_category_rules');
        $ruleInfo = $ruleModel->getRow('rule_id,rule_content', array('bill_category' => $billCategory));
        
        if (empty($ruleInfo)) {
            return '-';
        }

        $ruleId = $ruleInfo['rule_id'];
        $rule_content = $ruleInfo['rule_content'] ? json_decode($ruleInfo['rule_content'], 1) : array();
        foreach ($platform as $key => $value) {
            $color = (isset($rule_content[$key]) && $rule_content[$key]) ? 'blue' : 'red';
            $ret .= '<a style="color:'.$color.';" href="index.php?app=financebase&ctl=admin_shop_settlement_rules&act=setRule&p[0]='.$ruleId.'&p[1]='.$key.'&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '" target="_blank">'.$value.'</a>&nbsp;&nbsp;&nbsp;&nbsp;';
        }

        return $ret;
    }
}