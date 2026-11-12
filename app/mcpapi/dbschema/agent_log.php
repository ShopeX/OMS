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

// MCP Agent 调用审计日志：agent/模型/工具/操作员/参数（脱敏）/结果。
// 列元数据（label/in_list/searchtype/filtertype）供 desktop finder 列表页使用。
$db['agent_log'] = array(
    'columns' => array(
        'id' => array(
            'type' => 'bigint unsigned', 'required' => true, 'pkey' => true, 'extra' => 'auto_increment',
            'label' => '日志ID', 'width' => 110, 'editable' => false,
            'in_list' => false, 'default_in_list' => false,
        ),
        'request_id' => array(
            'type' => 'varchar(64)', 'required' => true,
            'label' => '请求ID', 'width' => 130, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'normal', 'searchtype' => 'nequal',
        ),
        'session_id' => array(
            'type' => 'varchar(64)', 'required' => false, 'default' => null,
            'label' => '会话ID', 'width' => 130, 'editable' => false,
            'in_list' => false, 'default_in_list' => false,
            'filtertype' => 'normal', 'searchtype' => 'nequal',
        ),
        'trace_id' => array(
            'type' => 'varchar(64)', 'required' => false, 'default' => null,
            'label' => '任务/对话ID', 'width' => 130, 'editable' => false,
            'in_list' => true, 'default_in_list' => false,
            'filtertype' => 'normal', 'searchtype' => 'nequal',
        ),
        'client_id' => array(
            'type' => 'varchar(128)', 'required' => true,
            'label' => '应用', 'width' => 150, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'normal', 'searchtype' => 'nequal',
        ),
        'client_mode' => array(
            'type' => 'varchar(16)', 'required' => false, 'default' => null,
            'label' => '通道', 'width' => 80, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
        ),
        'agent_name' => array(
            'type' => 'varchar(128)', 'required' => false, 'default' => null,
            'label' => 'Agent', 'width' => 120, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'normal', 'searchtype' => 'has',
        ),
        'agent_version' => array(
            'type' => 'varchar(64)', 'required' => false, 'default' => null,
            'label' => 'Agent版本', 'width' => 90, 'editable' => false,
            'in_list' => false, 'default_in_list' => false,
        ),
        'model' => array(
            'type' => 'varchar(128)', 'required' => false, 'default' => null,
            'label' => '模型', 'width' => 120, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'normal', 'searchtype' => 'has',
        ),
        'user_id' => array(
            'type' => 'int unsigned', 'required' => false, 'default' => null,
            'label' => '操作员ID', 'width' => 90, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'number',
        ),
        'tenant_id' => array(
            'type' => 'varchar(32)', 'required' => false, 'default' => null,
            'label' => '租户', 'width' => 90, 'editable' => false,
            'in_list' => false, 'default_in_list' => false,
        ),
        'tool_name' => array(
            'type' => 'varchar(128)', 'required' => false, 'default' => null,
            'label' => '工具', 'width' => 180, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'normal', 'searchtype' => 'has',
        ),
        'action_type' => array(
            'type' => array('query' => '查询', 'create' => '创建', 'update' => '更新', 'delete' => '删除'),
            'default' => 'query', 'required' => false,
            'label' => '动作', 'width' => 70, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'enum',
        ),
        'biz_keys' => array(
            'type' => 'varchar(512)', 'required' => false, 'default' => null,
            'label' => '业务对象', 'width' => 200, 'editable' => false,
            'in_list' => true, 'default_in_list' => false,
            'filtertype' => 'normal', 'searchtype' => 'has',
        ),
        'write_flag' => array(
            'type' => 'bool', 'required' => true, 'default' => 'false',
            'label' => '写操作', 'width' => 70, 'editable' => false,
            'in_list' => true, 'default_in_list' => false,
        ),
        'success' => array(
            'type' => 'bool', 'required' => true, 'default' => 'true',
            'label' => '成功', 'width' => 70, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'enum',
        ),
        'error_code' => array(
            'type' => 'varchar(64)', 'required' => false, 'default' => null,
            'label' => '错误码', 'width' => 130, 'editable' => false,
            'in_list' => true, 'default_in_list' => false,
            'filtertype' => 'normal', 'searchtype' => 'nequal',
        ),
        'http_status' => array(
            'type' => 'smallint unsigned', 'required' => false, 'default' => null,
            'label' => 'HTTP状态', 'width' => 80, 'editable' => false,
            'in_list' => true, 'default_in_list' => false,
        ),
        'params' => array(
            'type' => 'text', 'required' => false, 'default' => null,
            'label' => '请求参数', 'width' => 200, 'editable' => false,
            'in_list' => false, 'default_in_list' => false,
        ),
        'response_digest' => array(
            'type' => 'text', 'required' => false, 'default' => null,
            'label' => '响应摘要', 'width' => 200, 'editable' => false,
            'in_list' => false, 'default_in_list' => false,
        ),
        'spendtime_ms' => array(
            'type' => 'int unsigned', 'required' => false, 'default' => null,
            'label' => '耗时(ms)', 'width' => 90, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
        ),
        'ip' => array(
            'type' => 'varchar(45)', 'required' => false, 'default' => null,
            'label' => '来源IP', 'width' => 120, 'editable' => false,
            'in_list' => true, 'default_in_list' => false,
            'filtertype' => 'normal', 'searchtype' => 'nequal',
        ),
        'user_agent' => array(
            'type' => 'varchar(255)', 'required' => false, 'default' => null,
            'label' => 'UA', 'width' => 180, 'editable' => false,
            'in_list' => false, 'default_in_list' => false,
        ),
        'created_at' => array(
            'type' => 'time', 'required' => true,
            'label' => '调用时间', 'width' => 160, 'editable' => false,
            'in_list' => true, 'default_in_list' => true,
            'filtertype' => 'time', 'filterdefault' => true,
        ),
    ),
    'index' => array(
        'idx_user_time' => array('columns' => array('user_id', 'created_at')),
        'idx_client_time' => array('columns' => array('client_id', 'created_at')),
        'idx_tool' => array('columns' => array('tool_name')),
        'idx_created' => array('columns' => array('created_at')),
        'idx_request' => array('columns' => array('request_id')),
        'idx_action' => array('columns' => array('action_type', 'created_at')),
        'idx_trace' => array('columns' => array('trace_id')),
    ),
    'engine' => 'innodb',
    'comment' => 'MCP agent call audit log (agent/model/tool/user, redacted params)',
);
