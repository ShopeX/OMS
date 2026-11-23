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
 * 人工库存预占流水记录
 */
class console_mdl_basic_material_stock_artificial_freeze extends material_mdl_basic_material_stock_artificial_freeze{
    
    //是否有导出配置
    var $has_export_cnf = true;
    var $export_name = '人工预占列表';
    
    //确定表名获取表数据
    public function table_name($real = false){
        if($real){
            $table_name = 'sdb_material_basic_material_stock_artificial_freeze';
        }else{
            $table_name = 'basic_material_stock_artificial_freeze';
        }
        return $table_name;
    }
    
    //确定表结构
    public function get_schema(){
        return app::get('material')->model('basic_material_stock_artificial_freeze')->get_schema();
    }
    
    function _filter($filter,$tableAlias=null,$baseWhere=null){
        $where = array();
        //基础物料编码
        if(isset($filter['basic_material_bn']) && $filter['basic_material_bn']){
            $mdl_basic_material = app::get('material')->model('basic_material');
            $rs_basic_material = $mdl_basic_material->dump(array("material_bn"=>trim($filter['basic_material_bn'])));
            if(!empty($rs_basic_material)){
                $where[] = "bm_id=".$rs_basic_material["bm_id"];
            }else{
                $where[] = "bm_id=-1";
            }
            unset($filter['basic_material_bn']);
        }
        if(isset($filter["basic_material_bn"]) && !$filter["basic_material_bn"]){
            unset($filter["basic_material_bn"]);
        }
        if(isset($filter["group_id"]) && !$filter["group_id"]){
            unset($filter["group_id"]);
        }
        if(isset($filter["branch_id"]) && !$filter["branch_id"]){
            unset($filter["branch_id"]);
        }
        $sWhere = parent::_filter($filter,$tableAlias,$baseWhere);
        if(!empty($where)){
            $sWhere .= " AND ".implode(" and ",$where);
        }
        return $sWhere;
    }
    
    //导出 字段配置 移除不需要的字段
    public function disabled_export_cols(&$cols){
        unset($cols['column_edit']);
    }
    
}