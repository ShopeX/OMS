<?php

/**
 * 投资金价字段字典（对外主字段：小写下划线；兼容 SAP 大写 / camelCase）
 * 备注：ext_1~ext_20 落库 ext_field JSON（同每日金价）；兼容 ZREMARKS1~10
 */
class goldentra_invest_goldprice_fields
{
    public static $ext_fields = array(
        'ext_1', 'ext_2', 'ext_3', 'ext_4', 'ext_5',
        'ext_6', 'ext_7', 'ext_8', 'ext_9', 'ext_10',
        'ext_11', 'ext_12', 'ext_13', 'ext_14', 'ext_15',
        'ext_16', 'ext_17', 'ext_18', 'ext_19', 'ext_20',
    );

    /**
     * 规范化字段名 => 别名列表（含 SAP / 有赞目标字段）
     */
    public static $aliases = array(
        'request_id' => array('request_id', 'requestId', 'ZIDNUM', 'zidnum'),
        'sync_type' => array('sync_type', 'syncType', 'ZJGBG', 'zjgb'),
        'sales_organize' => array('sales_organize', 'salesOrganize', 'VKORG', 'vkorg'),
        'brand_id' => array('brand_id', 'brandId', 'ZPP', 'zpp'),
        'category_code' => array('category_code', 'categoryCode', 'MATKL', 'matkl'),
        'price' => array('price', 'ZJG_ZTJJ', 'zjg_ztjj', 'base_price', 'basePrice'),
        'item_code' => array('item_code', 'itemCode', 'MATNR', 'matnr'),
        'add_price' => array('add_price', 'addPrice', 'ZJG_ZTJ1', 'zjg_ztj1'),
    );

    public static $required = array(
        'request_id',
        'sync_type',
        'sales_organize',
    );

    public static $descriptions = array(
        'request_id' => '请求ID号(ZIDNUM)：1位变更类型+8位日期+6位时间+5位类目编码',
        'sync_type' => '价格变更类型(ZJGBG)：1类目基准价 / 2商品加价 / 3基准价+加价',
        'sales_organize' => '销售组织(VKORG)，固定值多为1200',
        'brand_id' => '品牌编码(ZPP)：1老庙 / 2亚一；基准价时常双品牌各一条',
        'category_code' => '商品类目编码(MATKL)，条件类型ZTJJ时必填',
        'price' => '投资金条基准价(ZJG_ZTJJ)，元/g',
        'item_code' => 'SAP商品编码(MATNR)，条件类型ZTJ1时必填',
        'add_price' => '投资金条加价零售(ZJG_ZTJ1)，元/g',
        'ext_1' => '备注1（兼容 ZREMARKS1）',
        'ext_2' => '备注2（兼容 ZREMARKS2）',
        'ext_3' => '备注3',
        'ext_4' => '备注4',
        'ext_5' => '备注5',
        'ext_6' => '备注6',
        'ext_7' => '备注7',
        'ext_8' => '备注8',
        'ext_9' => '备注9',
        'ext_10' => '备注10',
        'ext_11' => '备注11',
        'ext_12' => '备注12',
        'ext_13' => '备注13',
        'ext_14' => '备注14',
        'ext_15' => '备注15',
        'ext_16' => '备注16',
        'ext_17' => '备注17',
        'ext_18' => '备注18',
        'ext_19' => '备注19',
        'ext_20' => '备注20',
    );

    public static function pickField(array $raw, $field)
    {
        $aliases = isset(self::$aliases[$field]) ? self::$aliases[$field] : array($field);
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $raw) && $raw[$alias] !== null && $raw[$alias] !== '') {
                return is_string($raw[$alias]) ? trim($raw[$alias]) : $raw[$alias];
            }
        }
        $map = array();
        foreach ($raw as $k => $v) {
            $map[strtolower((string) $k)] = $v;
        }
        foreach ($aliases as $alias) {
            $lk = strtolower((string) $alias);
            if (array_key_exists($lk, $map) && $map[$lk] !== null && $map[$lk] !== '') {
                $v = $map[$lk];
                return is_string($v) ? trim($v) : $v;
            }
        }
        return '';
    }

    public static function normalizeRow(array $raw)
    {
        $row = array();
        foreach (array_keys(self::$aliases) as $field) {
            $row[$field] = self::pickField($raw, $field);
        }
        $row['sync_type'] = (string) $row['sync_type'];
        $row['brand_id'] = (string) $row['brand_id'];
        if ($row['price'] === '') {
            $row['price'] = '0';
        }
        if ($row['add_price'] === '') {
            $row['add_price'] = '0';
        }
        return $row;
    }

    /**
     * 将 ZREMARKS1~20 / remarks1 等归一到 ext_1~ext_20 后写入 ext_field JSON
     *
     * @param array $raw
     * @return string
     */
    public static function buildExtField(array $raw)
    {
        $normalized = $raw;
        for ($i = 1; $i <= 20; $i++) {
            $extKey = 'ext_' . $i;
            if (isset($normalized[$extKey]) && $normalized[$extKey] !== '' && $normalized[$extKey] !== null) {
                continue;
            }
            $candidates = array(
                'ZREMARKS' . $i,
                'zremarks' . $i,
                'remarks' . $i,
                'remark' . $i,
                'REMARKS' . $i,
            );
            foreach ($candidates as $alias) {
                if (array_key_exists($alias, $raw) && $raw[$alias] !== null && $raw[$alias] !== '') {
                    $normalized[$extKey] = is_string($raw[$alias]) ? trim($raw[$alias]) : $raw[$alias];
                    break;
                }
            }
        }
        $known = array_keys(self::$aliases);
        return goldentra_api_normalize::buildExtField($normalized, $known, self::$ext_fields);
    }

    /**
     * @param array $row normalize 后
     * @throws Exception
     */
    public static function validate(array $row)
    {
        $missing = array();
        foreach (self::$required as $field) {
            if (!isset($row[$field]) || $row[$field] === '') {
                $missing[] = $field;
            }
        }
        if ($missing) {
            throw new Exception(goldentra_api_normalize::formatMissingFieldsMessage($missing, self::$descriptions));
        }

        $syncType = (string) $row['sync_type'];
        if (!in_array($syncType, array('1', '2', '3'), true)) {
            throw new Exception('sync_type(ZJGBG) 仅支持 1/2/3');
        }

        if ($row['brand_id'] !== '' && !in_array((string) $row['brand_id'], array('1', '2'), true)) {
            throw new Exception('brand_id(ZPP) 仅支持 1老庙 / 2亚一');
        }

        if ($syncType === '1' || $syncType === '3') {
            if ($row['category_code'] === '') {
                throw new Exception('类目基准价场景缺少 category_code(MATKL)');
            }
            if (!is_numeric($row['price'])) {
                throw new Exception('price(ZJG_ZTJJ) 必须为数字');
            }
        }
        if ($syncType === '2' || $syncType === '3') {
            if ($row['item_code'] === '') {
                throw new Exception('商品加价场景缺少 item_code(MATNR)');
            }
            if (!is_numeric($row['add_price'])) {
                throw new Exception('add_price(ZJG_ZTJ1) 必须为数字');
            }
        }
    }
}
