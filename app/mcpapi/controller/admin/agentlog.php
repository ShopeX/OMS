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
 * MCP Agent 调用日志 legacy 入口（desktop 框架菜单路由）。
 *
 * 主界面为 iframeui SPA 页（/m-desktop/mcp-agent-log/index，数据走 api_agentlog），
 * legacy 路由直接 302 到 SPA 页，保持单一实现。
 */
class mcpapi_ctl_admin_agentlog extends desktop_controller
{
    var $workground = 'adminpanel';

    public function index()
    {
        $params = array(
            'title' => 'MCP Agent 调用日志，<font class="c-red">默认显示7天内记录</font>',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => ' id desc ',
            'base_filter' => array('created_at|than' => strtotime('-7 days')),
        );
        $this->finder('mcpapi_mdl_agent_log', $params);
    }
}
