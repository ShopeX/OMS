<?php

class goldentra_openapi_conf
{
    public static function getMethods()
    {
        return array(
            'goldentra.material' => array(
                'label' => '唯一码主档',
                'group' => 'goldentra',
                'methods' => array(
                    'add' => 'SAP主档同步',
                ),
            ),
            'goldentra.goldprice' => array(
                'label' => '每日金价',
                'group' => 'goldentra',
                'methods' => array(
                    'add' => '每日金价同步',
                ),
            ),
            'goldentra.investgoldprice' => array(
                'label' => '投资金价',
                'group' => 'goldentra',
                'methods' => array(
                    'add' => '投资金价下发',
                ),
            ),
            'goldentra.stock' => array(
                'label' => '库存增量',
                'group' => 'goldentra',
                'methods' => array(
                    'add' => 'SAP库存增量同步',
                ),
            ),
            'goldentra.metadata' => array(
                'label' => '数据字典',
                'group' => 'goldentra',
                'methods' => array(
                    'add' => '数据字典同步',
                ),
            ),
            'goldentra.sales' => array(
                'label' => '销售退货回调',
                'group' => 'goldentra',
                'methods' => array(
                    'callback' => '销售/退货处理结果回调',
                ),
            ),
        );
    }
}
