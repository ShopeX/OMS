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
 * 审批流节点模型类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_mdl_workflow_node extends dbeav_model {
    // 可根据需要扩展model方法
    
    /**
     * 获取节点类型列表
     *
     * @return array
     */
    public function getNodeTypes()
    {
        return array(
            'start' => '开始',
            'condition' => '条件',
            'approval' => '审批',
            'end' => '结束',
        );
    }
    
    /**
     * 获取审批人类型列表
     *
     * @return array
     */
    public function getAssigneeTypes()
    {
        return array(
            'user' => '用户',
            //'role' => '角色',
            //'dept' => '部门',
        );
    }
    
    /**
     * 获取模板的下一个节点顺序号
     *
     * @param int $template_id
     * @return int
     */
    public function getNextStepOrder($template_id)
    {
        $maxOrder = $this->getList('MAX(step_order) as max_order', array('template_id' => $template_id));
        return isset($maxOrder[0]['max_order']) ? $maxOrder[0]['max_order'] + 1 : 1;
    }
} 