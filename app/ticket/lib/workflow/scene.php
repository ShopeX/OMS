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
 * 订单场景
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_workflow_scene extends ticket_abstract
{
    public function getConditionFields($sceneType)
    {
        $sceneType = trim($sceneType);
        if ($sceneType === '') {
            return array(
                'field_source' => 'scene:unknown',
                'fields' => array(),
            );
        }

        $numberOperators = array('>', '>=', '<', '<=', '=', '!=');
        $scenes = array(
            'add_gift' => array(
                'field_source' => 'scene:add_gift',
                'fields' => array(
                    array(
                        'field' => 'order_amount',
                        'label' => '订单金额',
                        'type' => 'number',
                        'unit' => '元',
                        'precision' => 2,
                        'operators' => $numberOperators,
                        'input_hint' => '含税金额',
                    ),
                    array(
                        'field' => 'order_total_quantity',
                        'label' => '订单商品数量',
                        'type' => 'integer',
                        'operators' => array('>', '>=', '<', '<=', '='),
                        'min' => 0,
                    ),
                ),
            ),
            'approve_orders' => array(
                'field_source' => 'scene:approve_orders',
                'fields' => array(
                    array(
                        'field' => 'order_amount',
                        'label' => '订单金额',
                        'type' => 'number',
                        'unit' => '元',
                        'precision' => 2,
                        'operators' => $numberOperators,
                        'input_hint' => '含税金额',
                    ),
                ),
            ),
        );

        if (isset($scenes[$sceneType])) {
            return $scenes[$sceneType];
        }

        return array(
            'field_source' => 'scene:' . $sceneType,
            'fields' => array(),
        );
    }
}
