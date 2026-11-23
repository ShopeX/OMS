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

class crm_mdl_gift_rule_base extends dbeav_model{

    function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        if(!isset($filter['disabled'])){
            $filter['disabled'] = 'false';
        }
        
        if($filter['sales_material_bn']){
            $giftLogMdl = app::get('crm')->model('gift_rule_logs');
            
            //filter
            $logFilter = array('gift_bn'=>$filter['sales_material_bn']);
            
            //list
            $rule_ids = $giftLogMdl->getList('rule_id', $logFilter);
            $rule_ids = array_unique(array_column($rule_ids, 'rule_id'));
            
            if($rule_ids){
                $filter['id'] = $rule_ids;
            }else{
                $filter['id'] = -1;
            }
            
            unset($filter['sales_material_bn']);
        }
        
        return parent::_filter($filter, $tableAlias, $baseWhere);
    }

    public function modifier_gift_list($col, $list) {
        $col = unserialize($col);
        static $giftBn = array();
        if(!$giftBn) {
            $giftId = array();
            foreach ($list as $val) {
                $giftList = unserialize($val['gift_list']);
                foreach ($giftList as $k => $v) {
                    $giftId[$k] = $k;
                }
            }
            $gift = app::get('crm')->model('gift')->getList('gift_id, gift_bn',array('gift_id'=>$giftId));
            foreach ($gift as $v) {
                $giftBn[$v['gift_id']] = $v['gift_bn'];
            }
        }
        $str = '';
        foreach ($col as $k => $v) {
            $str .= $giftBn[$k] . ':' . $v . ';';
        }
        return $str;
    }

}