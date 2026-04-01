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

class financebase_finder_bill_category_rules {

    var $addon_cols = 'account_id_plus,account_id_minus';
    var $col_prefix = ''; // 动态设置的前缀，用于addon_cols字段

    var $column_edit = "操作";
    var $column_edit_width = "150";

    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];

        // 检查编辑权限
        if (!kernel::single('desktop_user')->has_permission('shop_settlement_rules_edit')) {
            return '';
        }

        $ret = '<a href="index.php?app=financebase&ctl=admin_shop_settlement_rules&act=setCategory&p[0]='.$row['rule_id'].'&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '" target="dialog::{width:550,height:400,resizeable:false,title:\'编辑收支分类\'}">编辑</a>';

        return $ret;
    }

    // 添加贷会计科目列
    public $column_account_id_plus = "贷会计科目";
    public $column_account_id_plus_width = 110;
    public $column_account_id_plus_order = 15;

    // 添加借会计科目列
    public $column_account_id_minus = "借会计科目";
    public $column_account_id_minus_width = 110;
    public $column_account_id_minus_order = 16;

    private function _getAccountCache()
    {
        static $accountCache;
        
        if (!isset($accountCache)) {
            $accountObj = app::get('financebase')->model('account_chart');
            $accountList = $accountObj->getList('id,account,description,postingkey');
            $accountCache = array();
            foreach ($accountList as $account) {
                $accountCache[$account['id']] = '[' . $account['account'] . '-' . $account['postingkey'] . ']' . $account['description'];
            }
        }
        
        return $accountCache;
    }

    public function column_account_id_plus($row, $list)
    {
        if (empty($row[$this->col_prefix.'account_id_plus'])) {
            return '';
        }
        
        $accountCache = $this->_getAccountCache();
        
        // 格式化显示
        if (isset($accountCache[$row[$this->col_prefix.'account_id_plus']])) {
            return $accountCache[$row[$this->col_prefix.'account_id_plus']];
        }
        
        return $row[$this->col_prefix.'account_id_plus'];
    }

    public function column_account_id_minus($row, $list)
    {
        if (empty($row[$this->col_prefix.'account_id_minus'])) {
            return '';
        }
        
        $accountCache = $this->_getAccountCache();
        
        // 格式化显示
        if (isset($accountCache[$row[$this->col_prefix.'account_id_minus']])) {
            return $accountCache[$row[$this->col_prefix.'account_id_minus']];
        }
        
        return $row[$this->col_prefix.'account_id_minus'];
    }


    var $column_platform = '平台规则设置（蓝色：已设置、红色：未设置)';
	var $column_platform_width = "500";
	var $column_platform_order = 20;
	function column_platform($row) {

		$finder_id = $_GET['_finder']['finder_id'];
		$oFunc = kernel::single('financebase_func');

		$platform = $oFunc->getShopPlatform(true);

		$ret = "";

		// TODO 优化
		$tmp = app::get('financebase')->model('bill_category_rules')->getRow('rule_content',array('rule_id'=>$row['rule_id']));
		$row['rule_content'] = $tmp['rule_content'];

		$rule_content = $row['rule_content'] ? json_decode($row['rule_content'],1) : array();
		foreach ($platform as $key => $value) {
			$color = (isset($rule_content[$key]) && $rule_content[$key]) ? 'blue' : 'red';
			$ret .= '<a style="color:'.$color.';" href="index.php?app=financebase&ctl=admin_shop_settlement_rules&act=setRule&p[0]='.$row['rule_id'].'&p[1]='.$key.'&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '" target="_blank">'.$value.'</a>&nbsp;&nbsp;&nbsp;&nbsp;';

		}

		return $ret;
	}

	// 添加操作日志功能
	public $detail_show_log = '操作记录';
	public function detail_show_log($rule_id)
	{
		// 使用ome模块的read_log方法，与经销商品价格保持一致
		$omeLogMdl = app::get('ome')->model('operation_log');
		$logList = $omeLogMdl->read_log(array('obj_id' => $rule_id, 'obj_type' => 'bill_category_rules@financebase'), 0, -1);
		
		$finder_id = $_GET['_finder']['finder_id'];
		
		if ($logList) {
			foreach ($logList as $k => $v) {
				$logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

				// 检查操作类型，为编辑操作添加快照链接
				if (strpos($v['operation'], '编辑') !== false) {
					$logList[$k]['memo'] = "<a href='index.php?app=financebase&ctl=admin_shop_settlement_rules&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' onclick=\"window.open(this.href, '_blank', 'width=801,height=570'); return false;\">查看快照</a>";
				} else {
					// 其他操作（如新建）不显示快照链接，但保留原有的memo内容
					$logList[$k]['memo'] = $v['memo'] ?: '';
				}
			}
		}
		
		$render = app::get('financebase')->render();
		$render->pagedata['logs'] = $logList ?: array();
		return $render->fetch('finder/bill/category/rules/operation_log.html');
	}

}

