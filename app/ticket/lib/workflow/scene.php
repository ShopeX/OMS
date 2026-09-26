<?php

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
