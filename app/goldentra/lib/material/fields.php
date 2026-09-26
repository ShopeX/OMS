<?php

/**
 * materialAdd / SAP 主档字段字典（OpenAPI V1.3 小写 snake_case）
 */
class goldentra_material_fields
{
    public static $ext_fields = array(
        'ext_1', 'ext_2', 'ext_3', 'ext_4', 'ext_5',
        'ext_6', 'ext_7', 'ext_8', 'ext_9', 'ext_10',
        'ext_11', 'ext_12', 'ext_13', 'ext_14', 'ext_15',
        'ext_16', 'ext_17', 'ext_18', 'ext_19', 'ext_20',
    );

    /** OMS 必填：模号编码、唯一码、克重、计价方式 */
    public static $oms_required = array(
        'unique_code',
        'mold_code',
        'weight',
        'price_method',
    );

    /** 钻石件额外必填（V1.5 已取消，颜色/净度/切工均非必传） */
    public static $diamond_required = array();

    /** API → 表字段 */
    public static $field_map = array(
        'unique_code' => 'unique_code',
        'goods_name' => 'goods_name',
        'material_no' => 'material_no',
        'material_desc' => 'material_desc',
        'store_bn' => 'store_bn',
        'mold_code' => 'mold_code',
        'mold_name' => 'mold_name',
        'cate_code' => 'cate_code',
        'cate_name' => 'cate_name',
        'class_code' => 'class_code',
        'class_name' => 'class_name',
        'style_code' => 'style_code',
        'style_name' => 'style_name',
        'texture_code' => 'texture_code',
        'texture_name' => 'texture_name',
        'small_series_name' => 'small_series_name',
        'small_series_code' => 'small_series_code',
        'pricedimension_code' => 'pricedimension_code',
        'sales_mode' => 'sales_mode',
        'price_method' => 'price_method',
        'price' => 'price',
        'weight' => 'weight',
        'unit' => 'unit',
        'main_weight' => 'main_weight',
        'secondary_weight' => 'secondary_weight',
        'process_cost' => 'process_cost',
        'spec' => 'spec',
        'fcolor' => 'fcolor',
        'fpurity' => 'fpurity',
        'fcut' => 'fcut',
        'fcertificate' => 'fcertificate',
        'material_code' => 'material_code',
        'pic_url' => 'pic_url',
    );

    /** 字段说明（OpenAPI 文档） */
    public static $descriptions = array(
        'unique_code' => '唯一码（必填，每件实物一条主档）',
        'goods_name' => '主档名称/商品名称（可选）',
        'material_no' => '物料号（可选）',
        'material_desc' => '物料描述（可选）',
        'store_bn' => '门店编码',
        'mold_code' => '模号编码（必填）',
        'mold_name' => '模号名称（可选）',
        'cate_code' => '大类编码（字典，可选）',
        'cate_name' => '大类名称（V1.5 已删除，OMS 由 cate_code 回填）',
        'class_code' => '品类编码（字典，可选）',
        'class_name' => '品类名称（V1.5 已删除，OMS 由 class_code 回填）',
        'style_code' => '款式编码（字典，可选）',
        'style_name' => '款式名称（可选）',
        'texture_code' => '材质编码（字典，可选）',
        'texture_name' => '材质名称（V1.5 已删除，OMS 由 texture_code 回填）',
        'small_series_name' => '小系列名称（V1.5 已删除，OMS 由 small_series_code 回填）',
        'small_series_code' => '小系列编码（字典，可选）',
        'pricedimension_code' => '金价定价维度编码（可选；品牌编码+商品类目5位，如110102=1+10102，1老庙/2亚一）',
        'sales_mode' => '商品类型：0/无=无，1/经销=经销，2/代销=代销（可选，未传默认无）',
        'price_method' => '计价方式（必填）：1/jj/计价/按克=计价（按克），2/dj/定价/按件=定价（按件）',
        'price' => '标签价，默认0',
        'weight' => '克重（必填）',
        'unit' => '单位，如克',
        'main_weight' => '主石重，默认0',
        'secondary_weight' => '辅石重，默认0',
        'process_cost' => '工费，默认0',
        'spec' => '主档规格（可选）',
        'fcolor' => '颜色（钻石属性，可选）',
        'fpurity' => '净度（钻石属性，可选）',
        'fcut' => '切工（钻石属性，可选）',
        'fcertificate' => '证书号（钻石属性，可选）',
        'material_code' => '物料编码（仅用于生成基础物料，兼容字段）',
        'pic_url' => '主档图片编号或链接；编号将转为 https://image.yuyuanjewelry.com/product/{编号}',
    );

    public static function normalizeRow(array $raw)
    {
        $row = goldentra_api_normalize::mapRow($raw, self::$field_map);
        foreach (self::$ext_fields as $field) {
            $val = goldentra_api_normalize::pick($raw, $field);
            if ($val !== null && $val !== '') {
                $row[$field] = $val;
            }
        }
        return $row;
    }

    public static function apiToColumn($api_field)
    {
        $api_field = strtolower($api_field);
        return isset(self::$field_map[$api_field]) ? self::$field_map[$api_field] : '';
    }

    public static function buildExtField($raw)
    {
        $known = array_keys(self::$field_map);
        return goldentra_api_normalize::buildExtField($raw, $known, self::$ext_fields);
    }
}
