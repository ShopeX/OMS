<?php

/**
 * priceAdd / 每日金价字段字典（OpenAPI V1.3 小写 snake_case）
 */
class goldentra_daily_goldprice_fields
{
    public static $ext_fields = array(
        'ext_1', 'ext_2', 'ext_3', 'ext_4', 'ext_5',
        'ext_6', 'ext_7', 'ext_8', 'ext_9', 'ext_10',
        'ext_11', 'ext_12', 'ext_13', 'ext_14', 'ext_15',
        'ext_16', 'ext_17', 'ext_18', 'ext_19', 'ext_20',
    );

    /** POS 推送必填 */
    public static $pos_required = array(
        'date',
        'gold_price',
        'pricedimension_code',
        'level_flag',
    );

    /** OMS 新增时必填（更新已有记录时不校验、不更新） */
    public static $oms_insert_required = array(
        'pricedimension_name',
    );

    public static $field_map = array(
        'shop_bn' => 'shop_bn',
        'date' => 'price_date',
        'gold_price' => 'gold_price',
        'pricedimension_code' => 'pricedimension_code',
        'pricedimension_name' => 'pricedimension_name',
        'fcode' => 'fcode',
        'level_flag' => 'level_flag',
        'sales_area' => 'sales_area',
    );

    public static $descriptions = array(
        'shop_bn' => '店铺/门店编码（可选；可经映射转换，如 9898→9801200）',
        'date' => '金价日期，经 date2time 转时间戳；不参与唯一键，命中已有记录时会回写',
        'gold_price' => '金价；记录已存在且变价时更新并写操作日志',
        'pricedimension_code' => '定价维度编码，与主档 pricedimension_code 对应，参与唯一键',
        'pricedimension_name' => '定价维度名称；参与唯一键；新增写入，变价更新时不改',
        'fcode' => '金价单历史/编码（可选）；新增写入，变价更新时不改',
        'level_flag' => '标识',
        'sales_area' => '销售地区；参与唯一键',
    );

    public static function normalizeRow(array $raw)
    {
        $row = array();
        foreach (self::$field_map as $api => $col) {
            $row[$api] = goldentra_api_normalize::pickString($raw, $api);
        }
        foreach (self::$ext_fields as $field) {
            $val = goldentra_api_normalize::pick($raw, $field);
            if ($val !== null && $val !== '') {
                $row[$field] = $val;
            }
        }
        return $row;
    }

    public static function buildExtField($raw)
    {
        $known = array_keys(self::$field_map);
        return goldentra_api_normalize::buildExtField($raw, $known, self::$ext_fields);
    }
}
