<?php
/**
 * 审批流模板模型类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_mdl_workflow_template extends dbeav_model
{
    /**
     * 审批场景类型列表
     *
     * @return array
     */
    public function getSceneTypes()
    {
        return array(
            'add_gift' => '加赠',
        );
    }
} 