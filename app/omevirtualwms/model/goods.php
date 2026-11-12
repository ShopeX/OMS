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

class omevirtualwms_mdl_goods extends dbeav_model{
    /**
     * count
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function count($filter=null){
     	 $sql = "SELECT count(*) as _count from sdb_omeapilog_api_log where status in ('running','fail') and log_type ='store.trade.goods'"; 
        $row = $this->db->select($sql);
        return intval($row[0]['_count']);
    }
    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
    	$sql= "SELECT *  from sdb_omeapilog_api_log where status in ('running','fail') and log_type ='store.trade.goods'"; 
        $rows = $this->db->selectLimit($sql,$limit,$offset);
         return $rows;
    }

    /**
     * 获取_schema
     * @return mixed 返回结果
     */
    public function get_schema(){
        $schema = array (
            'columns' =>array( 
              'log_id' => 
                array (
                  'type' => 'varchar(32)',
                  'required' => true,
                  'pkey' => true,
                  'editable' => false,
                  'searchtype' => 'has',
                  'label' => '日志编号',
                  'order' => '1',
                ),
                'original_bn' =>
                array (
                  'type' => 'varchar(50)',
                  'editable' => false,
                  'in_list' => true,
                  'default_in_list' => true,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'searchtype' => 'has',
                  'label' => '单据号',
                  'width' => '150',
                  'order' => '3',
                ),
                'task_name' =>
                array (
                  'type' => 'varchar(255)',
                  'editable' => false,
                  'in_list' => true,
                  'default_in_list' => true,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'searchtype' => 'has',
                  'label' => '日志名称',
                  'width' => '300',
                  'order' => '3',
                ),
                'api_type' => 
                array (
                  'type' => 
                    array (
                      'response' => '响应',
                      'request' => '请求',
                    ),
                  'editable' => false,
                  'default' => 'request',
                  'required' => true,
                  'in_list' => true,
                  'default_in_list' => true,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'label' => '同步类型',
                  'width' => '70',
                ),
                'log_type' => 
                array (
                  'type' => 'varchar(32)',
                  'editable' => false,
                  'label' => '日志类型',
                ),
                'unique' => 
                array (
                  'type' => 'varchar(32)',
                  'editable' => false,
                  'label' => '日志唯一性',
                ),
                'status' =>
                array (
                  'type' => 
                    array (
                      'running' => '运行中',
                      'success' => '成功',
                      'fail' => '失败',
                      'sending' => '发起中',
                    ),
                  'required' => true,
                  'default' => 'sending',
                  'editable' => false,
                  'in_list' => true,
                  'default_in_list' => true,
                  'editable' => false,
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'label' => '状态',
                  'width' => '60',
                  'order' => '4',
                ),
                'worker' =>
                array (
                  'type' => 'varchar(200)',
                  'editable' => false,
                  'required' => true,
                  'label' => 'api方法名',
                  'in_list' => false,
                ),
                'params' => 
                array (
                  'type' => 'longtext',
                  'editable' => false,
                  'label' => '日志参数',
                  'filtertype' => 'normal',
                ),
                'error_lv' =>
                array (
                  'type' => 
                  array (
                    'normal' => '正常',
                    'system' => '系统级',
                    'application' => '应用级',
                    'warning' => '警告',
                    'error' => '错误',
                  ),
                  'editable' => false,
                  'default' => 'normal',
                  'required' => true,
                  'label' => '错误级别',
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                ),
                'msg_id' =>
                array (
                  'type' => 'varchar(60)',
                  'filtertype' => 'normal',
                  'filterdefault' => true,
                  'label' => 'msg_id',
                  'width' => 60,
                  'edtiable' => false,
                ),
                'shop_id' => 
                array (
                  'type' => 'table:shop@ome',
                  'editable' => false,
                  'in_list' => false,
                  'label' => '来源店铺',
                  'width' => '100',
                  'order' => '2',
                ),
                'retry' =>
                array (
                  'type' => 'number',
                  'required' => true,
                  'default' => 0,
                  'width' => '60',
                  'edtiable' => false,
                  'in_list' => true,
                  'label' => '重试次数',
                  'default_in_list' => true,
                  'order' => '5',
                ),
                'msg' =>
                array (
                  'type' => 'text',
                  'editable' => false,
                  'label' => '同步消息',
                ),
                'addon' =>
                array (
                  'type' => 'text',
                  'editable' => false,
                  'label' => '附加参数',
                ),
                'createtime' =>
                array (
                  'type' => 'time',
                  'label' => '发起同步时间',
                  'width' => '130',
                  'editable' => false,
                  'in_list' => true,
                  'default_in_list' => true,
                  'filtertype' => 'time',
                  'filterdefault' => true,
                  'order' => '7',
                ),
                'last_modified' =>
                array (
                  'label' => '最后重试时间',
                  'type' => 'last_modify',
                  'width' => '130',
                  'editable' => false,
                  'in_list' => true,
                  'default_in_list' => true,
                  'order' => '8',
                ),
              ),
                'idColumn' => 'log_id',
                'in_list' => array (
                    'task_name', 'api_type','log_type','original_bn','status','shop_id','retry','createtime','last_modified'
                   ),
                'default_in_list' => array (
                'task_name', 'api_type','log_type','original_bn','status','shop_id','retry','createtime','last_modified'
                ),
        );
        return $schema;
    }
}
