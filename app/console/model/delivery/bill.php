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
 * 物流包裹单Model类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_mdl_delivery_bill extends dbeav_model
{
    var $defaultOrder = array('log_id',' DESC');
    
    //是否支持导出字段定义
    var $has_export_cnf = true;
    
    //导出的文件名
    var $export_name = '物流包裹单';
    
    var $ioTitle = array();
    var $export_flag = false;
    
    /**
     * table_name
     * @param mixed $real real
     * @return mixed 返回值
     */

    public function table_name($real = false)
    {
        if($real){
            $table_name = 'sdb_ome_delivery_bill';
        }else{
            $table_name = 'delivery_bill';
        }
        
        return $table_name;
    }
    
    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema(){
        return app::get('ome')->model('delivery_bill')->get_schema();
    }
    
    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = '';
        
        //多包裹号/订单号/发货单号/运单号：换行拆成 IN（走本表字段索引）
        foreach (array('order_bn', 'package_bn', 'delivery_bn', 'logi_no') as $field) {
            if (!empty($filter[$field]) && is_string($filter[$field])
                && (strpos($filter[$field], "\n") !== false || strpos($filter[$field], "\r") !== false)
            ) {
                $filter[$field] = array_values(array_unique(array_map(
                    'trim',
                    array_filter(preg_split('/[\r\n]+/', $filter[$field]))
                )));
            }
        }
        
        //[兼容]已发货的状态
        if (isset($filter['status']) && $filter['status'] == '1') {
            $filter['status'] = array('1', '4'); //签收状态也是已发货
        }
        
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }
    
}
