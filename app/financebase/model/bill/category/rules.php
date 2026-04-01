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
* 对账规则模型类
* @author 334395174@qq.com
* @version 0.1
*/
class financebase_mdl_bill_category_rules extends dbeav_model
{

	public function getRow($cols='*',$filter=array())
	{
		$sql = "SELECT $cols FROM ".$this->table_name(true)." WHERE ".$this->filter($filter);
        return $this->db->selectrow($sql);
	}

	public function isExist($filter,$rule_id = 0)
	{
		$sql = "SELECT rule_id FROM ".$this->table_name(true)." WHERE ".$this->filter($filter);
		$rule_id and $sql.=" and rule_id <> ".$rule_id;
		return $this->db->selectrow($sql) ? true : false;
	}

    public function modifier_business_type($col) {
        return $col == 'cainiao' ? '菜鸟' : '';
    }

    public function modifier_need_ar_verify($col) {
        return $col == 1 ? '是' : '否';
    }
	public function delete($filter,$subSdf = 'delete'){
        if(parent::delete($filter)){
            foreach(kernel::servicelist('bill_category_rules_set') as $name=>$object){
                if(method_exists($object,'setRules')){
                    $object->setRules();
                }
            }
            return true;
        }else{
            return false;
        }
    }

}