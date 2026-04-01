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
 * @DateTime: 2020/12/4 11:49:52
 * @describe: 费用均摊
 * ============================
 */
class financebase_finder_expenses_unsplit {
    public $addon_cols = 'bill_category,order_bn';

    public $column_edit = "操作";
    public $column_edit_width = "80";
    public $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $ret = '<a href="index.php?app=financebase&ctl=admin_expenses_splititem&act=split&p[0]='.$row['id'].'&finder_id=' . $finder_id . '&view='.intval($_GET['view']).'" target="dialog::{width:350,height:200,title:\'再次拆分\'}">再次拆分</a>';

        return $ret;
    }

    /**
     * 获取会计科目信息缓存
     * @param array $list 当前列表数据
     * @return array 返回 bill_category => account_info 的映射
     */
    private function _getAccountInfoCache($list) {
        static $accountInfoCache;
        
        if (!isset($accountInfoCache)) {
            $accountInfoCache = array();
            
            // 收集所有 bill_category
            $billCategories = array();
            foreach ($list as $listRow) {
                $category = $listRow[$this->col_prefix . 'bill_category'];
                if (!empty($category)) {
                    $billCategories[] = $category;
                }
            }
            $billCategories = array_unique($billCategories);
            
            if (!empty($billCategories)) {
                // 批量查询 bill_category_rules 获取会计科目ID
                $ruleModel = app::get('financebase')->model('expenses_rule');
                $rules = $ruleModel->getList(
                    'bill_category,account_id_plus,account_id_minus',
                    array('bill_category' => $billCategories)
                );
                
                // 收集所有会计科目ID
                $accountIds = array();
                foreach ($rules as $rule) {
                    if (!empty($rule['account_id_plus'])) {
                        $accountIds[] = $rule['account_id_plus'];
                    }
                    if (!empty($rule['account_id_minus'])) {
                        $accountIds[] = $rule['account_id_minus'];
                    }
                }
                $accountIds = array_unique($accountIds);
                
                // 批量查询 account_chart 获取详细信息
                $accountChartModel = app::get('financebase')->model('account_chart');
                $accounts = array();
                if (!empty($accountIds)) {
                    $accounts = $accountChartModel->getList(
                        'id,account,postingkey,costcenter,tax_rate,customer',
                        array('id' => $accountIds)
                    );
                }
                
                // 构建 account_id => account_info 映射
                $accountMap = array();
                foreach ($accounts as $account) {
                    $accountMap[$account['id']] = $account;
                }
                
                // 构建 bill_category => account_info 映射
                foreach ($rules as $rule) {
                    $accountInfoCache[$rule['bill_category']] = array(
                        'plus' => isset($accountMap[$rule['account_id_plus']]) ? $accountMap[$rule['account_id_plus']] : null,
                        'minus' => isset($accountMap[$rule['account_id_minus']]) ? $accountMap[$rule['account_id_minus']] : null,
                    );
                }
            }
        }
        
        return $accountInfoCache;
    }

    /**
     * 获取核销状态缓存
     * @param array $list 当前列表数据
     * @return array 返回 order_bn => verification_status 的映射
     */
    private function _getVerificationStatusCache($list) {
        static $verificationStatusCache;
        
        if (!isset($verificationStatusCache)) {
            $verificationStatusCache = array();
            
            // 收集所有订单号
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
        
        return $verificationStatusCache;
    }

    // 贷方会计科目
    public $column_account_plus = "贷方会计科目";
    public $column_account_plus_width = "120";
    public $column_account_plus_order = 100;
    public function column_account_plus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['plus'])) {
            return '-';
        }
        
        return $accountInfo['plus']['account'] ?: '-';
    }

    // 借方会计科目
    public $column_account_minus = "借方会计科目";
    public $column_account_minus_width = "120";
    public $column_account_minus_order = 101;
    public function column_account_minus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['minus'])) {
            return '-';
        }
        
        return $accountInfo['minus']['account'] ?: '-';
    }

    // 贷方记账码
    public $column_postingkey_plus = "贷方记账码";
    public $column_postingkey_plus_width = "100";
    public $column_postingkey_plus_order = 102;
    public function column_postingkey_plus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['plus'])) {
            return '-';
        }
        
        return $accountInfo['plus']['postingkey'] ?: '-';
    }

    // 借方记账码
    public $column_postingkey_minus = "借方记账码";
    public $column_postingkey_minus_width = "100";
    public $column_postingkey_minus_order = 103;
    public function column_postingkey_minus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['minus'])) {
            return '-';
        }
        
        return $accountInfo['minus']['postingkey'] ?: '-';
    }

    // 贷方成本中心
    public $column_costcenter_plus = "贷方成本中心";
    public $column_costcenter_plus_width = "100";
    public $column_costcenter_plus_order = 104;
    public function column_costcenter_plus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['plus'])) {
            return '-';
        }
        
        return $accountInfo['plus']['costcenter'] ?: '-';
    }

    // 借方成本中心
    public $column_costcenter_minus = "借方成本中心";
    public $column_costcenter_minus_width = "100";
    public $column_costcenter_minus_order = 105;
    public function column_costcenter_minus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['minus'])) {
            return '-';
        }
        
        return $accountInfo['minus']['costcenter'] ?: '-';
    }

    // 贷方税率
    public $column_tax_rate_plus = "贷方税率";
    public $column_tax_rate_plus_width = "100";
    public $column_tax_rate_plus_order = 106;
    public function column_tax_rate_plus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['plus'])) {
            return '-';
        }
        
        $taxRate = $accountInfo['plus']['tax_rate'];
        return $taxRate !== null && $taxRate !== '' ? $taxRate : '-';
    }

    // 借方税率
    public $column_tax_rate_minus = "借方税率";
    public $column_tax_rate_minus_width = "100";
    public $column_tax_rate_minus_order = 107;
    public function column_tax_rate_minus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['minus'])) {
            return '-';
        }
        
        $taxRate = $accountInfo['minus']['tax_rate'];
        return $taxRate !== null && $taxRate !== '' ? $taxRate : '-';
    }

    // 贷方客户
    public $column_customer_plus = "贷方客户";
    public $column_customer_plus_width = "100";
    public $column_customer_plus_order = 108;
    public function column_customer_plus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['plus'])) {
            return '-';
        }
        
        return $accountInfo['plus']['customer'] ?: '-';
    }

    // 借方客户
    public $column_customer_minus = "借方客户";
    public $column_customer_minus_width = "100";
    public $column_customer_minus_order = 109;
    public function column_customer_minus($row, $list) {
        $billCategory = $row[$this->col_prefix . 'bill_category'];
        if (empty($billCategory)) {
            return '-';
        }
        
        $accountInfoCache = $this->_getAccountInfoCache($list);
        $accountInfo = isset($accountInfoCache[$billCategory]) ? $accountInfoCache[$billCategory] : null;
        
        if (empty($accountInfo) || empty($accountInfo['minus'])) {
            return '-';
        }
        
        return $accountInfo['minus']['customer'] ?: '-';
    }

    // 核销状态
    public $column_verification_status = "核销状态";
    public $column_verification_status_width = "100";
    public $column_verification_status_order = 110;
    public function column_verification_status($row, $list) {
        $orderBn = $row[$this->col_prefix . 'order_bn'];
        if (empty($orderBn)) {
            return '-';
        }
        
        $verificationStatusCache = $this->_getVerificationStatusCache($list);
        $verificationStatus = isset($verificationStatusCache[$orderBn]) ? $verificationStatusCache[$orderBn] : null;
        
        if (empty($verificationStatus)) {
            return '-';
        }
        
        $statusMap = array(
            '1' => '未核销',
            '2' => '已核销',
        );
        
        return isset($statusMap[$verificationStatus]) ? $statusMap[$verificationStatus] : '-';
    }
}