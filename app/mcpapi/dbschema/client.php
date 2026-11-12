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

$db['client'] = array(
    'columns' => array(
        'client_id' => array('type' => 'varchar(128)', 'required' => true, 'pkey' => true, 'is_title' => true),
        'client_secret_hash' => array('type' => 'varchar(255)', 'required' => false, 'default' => null),
        'owner_user_id' => array('type' => 'number', 'required' => true),
        'name' => array('type' => 'varchar(255)', 'required' => true),
        'tenant_id' => array('type' => 'varchar(128)', 'required' => true),
        'oms_base_url' => array('type' => 'varchar(255)', 'required' => true),
        'oms_subject_id' => array('type' => 'number', 'required' => true),
        'oms_role_ids' => array('type' => 'serialize', 'required' => true),
        'oms_permissions' => array('type' => 'serialize', 'required' => true),
        'scopes' => array('type' => 'serialize', 'required' => true),
        'client_type' => array('type' => array('confidential' => '机密客户端', 'public' => '公共客户端'), 'default' => 'confidential', 'required' => true),
        'client_kind' => array('type' => array('operator' => '操作员型', 'agent' => 'Agent型'), 'default' => 'operator', 'required' => true),
        'redirect_uris' => array('type' => 'serialize', 'required' => false, 'default' => null),
        'registration_origin' => array('type' => array('static' => '静态登记', 'dynamic' => '动态注册'), 'default' => 'static', 'required' => true),
        'token_endpoint_auth' => array('type' => array('client_secret_basic' => 'Basic 认证', 'client_secret_post' => 'POST 认证', 'none' => '无密认证'), 'default' => 'client_secret_basic', 'required' => true),
        'registered_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
        'applied_by' => array('type' => 'varchar(64)', 'required' => false, 'default' => null),
        'last_seen_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
        'enabled' => array('type' => 'bool', 'required' => true, 'default' => 'true'),
        'revoked_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
        'expires_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
        'created_at' => array('type' => 'time', 'required' => true),
        'updated_at' => array('type' => 'time', 'required' => true),
        'created_by' => array('type' => 'varchar(128)', 'required' => true),
        'updated_by' => array('type' => 'varchar(128)', 'required' => true),
        'revoked_reason' => array('type' => 'varchar(255)', 'required' => false, 'default' => null),
        'last_used_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
    ),
    'index' => array(
        'uni_client_id' => array('columns' => array('client_id'), 'prefix' => 'UNIQUE'),
        'ind_tenant_id' => array('columns' => array('tenant_id')),
        'ind_owner_user_id' => array('columns' => array('owner_user_id')),
        'ind_lifecycle' => array('columns' => array('enabled', 'revoked_at', 'expires_at')),
        'ind_registration_origin' => array('columns' => array('registration_origin')),
        'ind_kind_owner' => array('columns' => array('client_kind', 'owner_user_id')),
    ),
    'engine' => 'innodb',
    'comment' => 'MCPAPI client authorization registry',
);
