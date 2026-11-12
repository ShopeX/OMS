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
class logistics_finder_rule {

    private $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = "rule_id,branch_id";
    var $column_edit = "操作";
    var $column_edit_width = "100";
    function __construct(){
        if($_GET['branch_id']){
            $branch_rule = app::get('logistics')->model('branch_rule')->dump(array('branch_id'=>$_GET['branch_id']),'type');
            if($branch_rule['type']=='other'){
                unset($this->column_edit);
            }
        }
    }
    
    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        $ret= "&nbsp;<a href='index.php?app=logistics&ctl=admin_area_rule&act=area_rule_list&rule_id={$row["rule_id"]}&finder_id={$finder_id}' target=\"_blank\">编辑</a>";
        return $ret;
    }

    var $detail_basic='默认规则';
    function detail_basic($rule_id){
        $render = app::get('logistics')->render();
        #规则
        $rule = app::get('logistics')->model('rule')->detailRule($rule_id,1);
        $render->pagedata['rule'] = $rule;
        
        if($rule["set_type"] == "shop"){//按店铺
            //获取选中的指定店铺
            $mdl_rule_shop= app::get('logistics')->model('rule_shop');
            $mdl_ome_shop = app::get('ome')->model('shop');
            $rs_rule_shop = $mdl_rule_shop->getList("*",array("rule_id"=>$rule_id));
            $arr_shop_ids = array();
            foreach ($rs_rule_shop as $var_rule_shop){
                $arr_shop_ids[] = $var_rule_shop["shop_id"];
            }
            $rs_shops = $mdl_ome_shop->getList("name",array("shop_id|in"=>$arr_shop_ids));
            $shop_names = array();
            foreach ($rs_shops as $var_shop){
                $shop_names[] = $var_shop["name"];
            }
            $render->pagedata["str_selected_shop"] = implode("，", $shop_names);
            //获取指定物流公司
            $mdl_rule_obj= app::get('logistics')->model('rule_obj');
            $rs_rule_obj = $mdl_rule_obj->dump(array("rule_id"=>$rule_id));
            $mdl_rule_items = app::get('logistics')->model('rule_items');
            $rs_rule_items = $mdl_rule_items->dump(array("obj_id"=>$rs_rule_obj["obj_id"]));
            if ($rs_rule_items["corp_id"] == "-1"){//人工审单 -1
                $render->pagedata["corp_name"] = "人工审单";
            }else{
                $mdl_dly_corp = app::get('ome')->model('dly_corp');
                $rs_dly_corp = $mdl_dly_corp->dump(array("corp_id"=>$rs_rule_items["corp_id"]));
                $render->pagedata["corp_name"] = $rs_dly_corp["name"];
            }
        }
        
        // 复用子仓
        $rule_branches = array();
        $ruleBranchMdl = app::get('logistics')->model('branch_rule');
        foreach ($ruleBranchMdl->getList('*',array('parent_id'=>$rule['branch_id'],'type'=>'other')) as $value) {

            $rule_branches[$value['branch_id']]['name']      = &$branches[$value['branch_id']]['name'];
            $rule_branches[$value['branch_id']]['branch_id'] = &$branches[$value['branch_id']]['branch_id'];
            $rule_branches[$value['branch_id']]['branch_bn'] = &$branches[$value['branch_id']]['branch_bn'];
        }
        if ($branches) {
            $branchMdl = app::get('ome')->model('branch');
            foreach ($branchMdl->getList('branch_id,name,branch_bn',array('branch_id'=>array_keys($branches),'skip_permission'=>true)) as $value) {
                $branches[$value['branch_id']]['branch_id'] = $value['branch_id'];
                $branches[$value['branch_id']]['name']      = $value['name'];
                $branches[$value['branch_id']]['branch_bn'] = $value['branch_bn'];
            }
        }

        $render->pagedata['rule_branches'] = $rule_branches;
        
        return $render->fetch('admin/detail_rule_list.html');
    }

    var $detail_basics='下属特殊地区规则';
    function detail_basics($rule_id){
        $render = app::get('logistics')->render();
        //判断是否是按店铺规则类型
        $mdl_rule_obj = app::get('logistics')->model("rule_obj");
        $rs_rule_obj = $mdl_rule_obj->dump(array("rule_id"=>$rule_id));
        if($rs_rule_obj["set_type"] == "shop"){
            $render->pagedata['shop_rule'] = true;
        }else{
            $render->pagedata['rule_id'] = $rule_id;
        }
        return $render->fetch('admin/detail_area_rule_list.html');
    }
    
    var $column_set_type = "规则类型";
    var $column_set_type_width = "100";
    function column_set_type($row) {
        $mdl_rule_obj = app::get('logistics')->model('rule_obj');
        $rs_set_type = $mdl_rule_obj->dump(array("rule_id"=>$row["rule_id"]),"set_type");
        switch ($rs_set_type["set_type"]){
            case "shop":
                $ret = "指定店铺";
                break;
            case "noweight":
                $ret = "任意重量";
                break;
            case "weight":
                $ret = "重量区间";
                break;
        }
        return $ret;
    }

    public $column_child_branch = '复用子仓';
    public $column_child_branch_width = "280";
    /**
     * 复用子仓
     *
     * @return void
     * @author 
     */
    public function column_child_branch($row, $list)
    {
        static $rule_branches;
        if (!isset($rule_branches)) {
            $rule_branches = array();

            $branch_id = array(0);
            foreach ($list as $value) {
                $branch_id[$value['branch_id']] = $value['branch_id'];
            }

            $ruleBranchMdl = app::get('logistics')->model('branch_rule');
            foreach ($ruleBranchMdl->getList('*',array('parent_id'=>$branch_id,'type'=>'other')) as $value) {

                $rule_branches[$value['parent_id']][$value['branch_id']]['name'] = &$branches[$value['branch_id']]['name'];
                $rule_branches[$value['parent_id']][$value['branch_id']]['branch_id'] = &$branches[$value['branch_id']]['branch_id'];
            }

            if ($branches) {
                $branchMdl = app::get('ome')->model('branch');
                foreach ($branchMdl->getList('branch_id,name',array('branch_id'=>array_keys($branches),'skip_permission'=>true)) as $value) {
                    $branches[$value['branch_id']]['branch_id'] = $value['branch_id'];
                    $branches[$value['branch_id']]['name'] = $value['name'];
                }
            }
        }


        $html='';
        foreach ($rule_branches[$row['branch_id']] as $value) {
            $html .= $value['name'].'、';
        }


         return sprintf('<div style="overflow: auto;word-break: break-word;white-space: normal;%s;flex-wrap: wrap;" class="desc-tip" onmouseover="bindFinderColTip(event);">%s<textarea style="display:none;">%s</textarea></div>', 'width: 100%',$html,$html);
    }
    
    var $column_shop_names = "指定店铺";
    var $column_shop_names_width = "200";
    
    function column_shop_names($row, $list) {
        static $shop_names_cache;
        
        if (!isset($shop_names_cache)) {
            $shop_names_cache = array();
            
            // 获取当前页面所有规则的ID
            $rule_ids = array();
            foreach ($list as $rule) {
                $rule_ids[] = $rule['rule_id'];
            }
            
            if (!empty($rule_ids)) {
                // 一次性查询所有规则关联的店铺
                $mdl_rule_shop = app::get('logistics')->model('rule_shop');
                $all_shop_list = $mdl_rule_shop->getList('rule_id,shop_id', array('rule_id|in' => $rule_ids));
                
                // 按规则ID分组
                $rule_shops = array();
                foreach ($all_shop_list as $shop) {
                    $rule_shops[$shop['rule_id']][] = $shop['shop_id'];
                }
                
                // 获取所有店铺ID
                $all_shop_ids = array();
                foreach ($all_shop_list as $shop) {
                    $all_shop_ids[] = $shop['shop_id'];
                }
                
                if (!empty($all_shop_ids)) {
                    // 一次性查询所有店铺名称
                    $mdl_shop = app::get('ome')->model('shop');
                    $all_shops = $mdl_shop->getList('shop_id,name', array('shop_id|in' => $all_shop_ids));
                    
                    // 建立店铺ID到名称的映射
                    $shop_id_to_name = array();
                    foreach ($all_shops as $shop) {
                        $shop_id_to_name[$shop['shop_id']] = $shop['name'];
                    }
                    
                    // 为每个规则生成店铺名称字符串
                    foreach ($rule_shops as $rule_id => $shop_ids) {
                        $shop_names = array();
                        foreach ($shop_ids as $shop_id) {
                            if (isset($shop_id_to_name[$shop_id])) {
                                $shop_names[] = $shop_id_to_name[$shop_id];
                            }
                        }
                        $shop_names_cache[$rule_id] = implode(', ', $shop_names);
                    }
                }
            }
        }
        
        $shop_names_str = isset($shop_names_cache[$row['rule_id']]) ? $shop_names_cache[$row['rule_id']] : '';
        
        if (!empty($shop_names_str)) {
            return sprintf('<div style="overflow: auto;word-break: break-word;white-space: normal;%s;flex-wrap: wrap;" class="desc-tip" onmouseover="bindFinderColTip(event);">%s<textarea style="display:none;">%s</textarea></div>', 'width: 100%', $shop_names_str, $shop_names_str);
        }
        
        return '';
    }
    
    var $column_corp = "物流公司";
    var $column_corp_width = "100";
    
    function column_corp($row, $list)
    {
        $corp_info = $this->getShopCrop($row['rule_id'], $list);
        return isset($corp_info['corp_name']) ? $corp_info['corp_name'] : '';
    }
    
    private function getShopCrop($ruleId, $list)
    {
        static $corpList;
        if (isset($corpList[$ruleId])) {
            return $corpList[$ruleId];
        }
        
        $ruleObjMdl    = app::get('logistics')->model('rule_obj');
        $ruleItemsMdl  = app::get('logistics')->model('rule_items');
        $regionRuleMdl = app::get('logistics')->model('region_rule');
        $dlyCorpMdl    = app::get('ome')->model('dly_corp');
        
        
        $rule_ids = array_column($list, 'rule_id');
        $ruleList = $ruleObjMdl->getList('rule_id,obj_id,set_type,branch_id', ['rule_id' => $rule_ids]);
        $obj_ids  = array_column($ruleList, 'obj_id');
        $ruleList = array_column($ruleList, null, 'rule_id');
        
        //重量区间取首选物流公司
        $regionRuleList = $regionRuleMdl->getList('item_id,obj_id', ['obj_id' => $obj_ids]);
        $item_ids       = array_column($regionRuleList, 'item_id');
        $regionRuleList = array_column($regionRuleList, null, 'obj_id');
        
        $ruleItems = $ruleItemsMdl->getList('item_id,obj_id,corp_id,second_corp_id', ['item_id' => $item_ids]);
        $ruleItems = array_column($ruleItems, null, 'item_id');
        
        //店铺维度去物流公司
        $shopRuleItems = $ruleItemsMdl->getList('item_id,obj_id,corp_id,second_corp_id', ['obj_ids' => $obj_ids]);
        $shopRuleItems = array_column($shopRuleItems, null, 'obj_id');
        
        $corpAll = $dlyCorpMdl->getList('name,corp_id');
        $corpAll = array_column($corpAll, null, 'corp_id');
        
        $corpList = [];
        foreach ($list as $info) {
            $rule_id  = $info['rule_id'];
            $set_type = $ruleList[$rule_id]['set_type'] ?? '';
            if (!$set_type) {
                continue;
            }
            $obj_id = $ruleList[$rule_id]['obj_id'] ?? 0;
            
            if ($set_type == 'shop') {
                $corp_id = $shopRuleItems[$obj_id]['corp_id'] ?? 0;
            } else {
                $item_id = $regionRuleList[$obj_id]['item_id'] ?? 0;
                $corp_id = $ruleItems[$item_id]['corp_id'] ?? 0;
                if ($corp_id == 0) {
                    $corp_id = $ruleItems[$obj_id]['second_corp_id'] ?? 0;
                }
            }
            
            if ($corp_id && $corp_id == '-1') {
                $corpList[$rule_id]['corp_name'] = "人工审单";
            } else {
                $corpList[$rule_id]['corp_name'] = $corpAll[$corp_id]['name'] ?? '';
            }
        }
        
        return $corpList[$ruleId] ?? [];
    }
    
}

?>
