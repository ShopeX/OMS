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
 * 企业组织管理finder
 */
class organization_finder_organization{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


	var $addon_cols = "del_mark";
	var $column_edit = '操作';
	var $column_edit_width = "100";
	function column_edit($row){
		//删除状态的组织层级目前不做操作
		if(intval($row[$this->col_prefix."del_mark"]) == 1){
			return '';
		}
		$org_id = $row["org_id"];
		$find_id = $_GET['_finder']['finder_id'];
		$view = $_GET["view"]?$_GET["view"]:0;
		$button = <<<EOF
			<a href="index.php?app=organization&ctl=admin_management&act=editGropItem&finder_id=$find_id&org_id=$org_id&view=$view" target="dialog::{width:550,height:400,title:'编辑组织'}">编辑</a>&nbsp;
EOF;
		$button.= <<<EOF
		<a href="javascript:if(confirm('确认删除该组织？')){W.page('index.php?app=organization&ctl=admin_management&act=doDelGropItem&finder_id=$find_id&org_id=$org_id&view=$view', $extend({method: 'get'}, JSON.decode({})), this);}void(0);" target="">删除</a>
EOF;
		if(intval($row["status"]) == 2){
			$button.= <<<EOF
			<a href="index.php?app=organization&ctl=admin_management&act=doActiveGropItem&finder_id=$find_id&org_id=$org_id&view=$view" target="">启用</a>
EOF;
		}
		if(intval($row["status"]) == 1){
			$button.= <<<EOF
			<a href="javascript:if(confirm('确认停用该组织？')){W.page('index.php?app=organization&ctl=admin_management&act=doUnactiveGropItem&finder_id=$find_id&org_id=$org_id&view=$view', $extend({method: 'get'}, JSON.decode({})), this);}void(0);" target="">停用</a>
EOF;
		}
		return $button;
	}
	
}