<?php

/**
 * 机器中心主档扩展 Service（仅模号编码）
 *
 * 挂到 machinecenter.service.material.master；未注册 / 未启用则列表无扩展列。
 */
class goldentra_service_machinecenter_material extends goldentra_service_basic_material
{
    protected function getFieldDefinitions()
    {
        $all = parent::getFieldDefinitions();
        if (empty($all['mold_code'])) {
            return array();
        }

        return array(
            'mold_code' => $all['mold_code'],
        );
    }
}
