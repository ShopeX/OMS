<?php

class goldentra_metadata
{
    /**
     * 主档编码字段 → 数据字典业务编码
     * 产品类别 Z03 / 品种分类 Z05 / 小系列 Z13 / 金属材质 Z04
     */
    public static $material_dict_codes = array(
        'cate_code' => 'Z03',
        'class_code' => 'Z05',
        'small_series_code' => 'Z13',
        'texture_code' => 'Z04',
    );

    /**
     * 扩展字段 JSON → 键值行（后台展示）
     */
    public static function decodeExtFieldRows($ext_field)
    {
        if ($ext_field === '' || $ext_field === null) {
            return array();
        }
        $data = is_array($ext_field) ? $ext_field : json_decode($ext_field, true);
        if (!is_array($data)) {
            return array(array('key' => 'raw', 'value' => (string) $ext_field));
        }
        $rows = array();
        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $rows[] = array('key' => $key, 'value' => is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
        }
        return $rows;
    }

    /**
     * 解析 OpenAPI 入参：metadatas JSON / 单条平铺
     *
     * @return array[]|false
     */
    public static function parseApiPayload($params)
    {
        if (!empty($params['metadatas'])) {
            $raw = $params['metadatas'];
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            if (!is_array($raw)) {
                return false;
            }
            if (self::isAssocRow($raw)) {
                return array(self::normalizeApiRow($raw));
            }
            $rows = array();
            foreach ($raw as $row) {
                if (is_array($row)) {
                    $rows[] = self::normalizeApiRow($row);
                }
            }
            return $rows ? $rows : false;
        }

        if (!empty($params['code']) || !empty($params['name'])) {
            return array(self::normalizeApiRow($params));
        }

        return false;
    }

    /**
     * 校验单条字典
     *
     * @return true|string 错误信息
     */
    public static function validateApiRow(array $row)
    {
        $code = trim(isset($row['code']) ? $row['code'] : '');
        $name = trim(isset($row['name']) ? $row['name'] : '');
        if ($code === '' || $name === '') {
            return 'code、name 不能为空';
        }

        $header = isset($row['header']) ? $row['header'] : array();
        if (is_string($header)) {
            $header = json_decode($header, true);
        }
        $uniqueKey = '';
        if (is_array($header) && !empty($header['unique_key'])) {
            $uniqueKey = trim($header['unique_key']);
        } elseif (!empty($row['unique_key'])) {
            $uniqueKey = trim($row['unique_key']);
        }
        if ($uniqueKey === '') {
            return 'header.unique_key 不能为空';
        }

        return true;
    }

    /**
     * SAP 字段兼容：item → items
     */
    public static function normalizeApiRow(array $row)
    {
        if (empty($row['items']) && !empty($row['item'])) {
            $row['items'] = $row['item'];
        }
        unset($row['item']);
        return $row;
    }

    private static function isAssocRow(array $row)
    {
        return array_key_exists('code', $row)
            || array_key_exists('name', $row)
            || array_key_exists('header', $row)
            || array_key_exists('items', $row)
            || array_key_exists('item', $row);
    }

    /**
     * 按主档编码字段查字典描述（自动映射 Z03/Z05/Z13/Z04）
     */
    public static function lookupMaterialDictLabel($codeField, $unique_key)
    {
        $dictCode = isset(self::$material_dict_codes[$codeField])
            ? self::$material_dict_codes[$codeField]
            : '';
        return self::lookupItemLabel($unique_key, $dictCode);
    }

    /**
     * 按字典值 unique_key 查展示名（优先 ext_2 描述，可限定字典业务编码）
     *
     * @param string $unique_key 业务值，如 0001
     * @param string $dict_code 字典头 code，如 Z13；空则不限定字典（兼容旧调用）
     */
    public static function lookupItemLabel($unique_key, $dict_code = '')
    {
        $labels = self::lookupItemLabels(array($unique_key), $dict_code);
        $unique_key = trim((string) $unique_key);
        return isset($labels[$unique_key]) ? $labels[$unique_key] : '';
    }

    /**
     * 批量查字典展示名（带请求内缓存，供主档列表/导出使用）
     *
     * @param string[] $unique_keys
     * @param string $dict_code 字典业务编码，如 Z03；建议传入避免跨字典业务值冲突
     * @return array unique_key => label
     */
    public static function lookupItemLabels(array $unique_keys, $dict_code = '')
    {
        static $cache = array();
        $dict_code = trim((string) $dict_code);
        $cachePrefix = $dict_code === '' ? '_' : $dict_code;

        $need = array();
        foreach ($unique_keys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $ck = $cachePrefix . "\0" . $key;
            if (!array_key_exists($ck, $cache)) {
                $need[$key] = $key;
            }
        }

        if ($need) {
            $rows = self::fetchItemRows(array_values($need), $dict_code);
            foreach ($need as $key) {
                $cache[$cachePrefix . "\0" . $key] = '';
            }
            foreach ($rows as $item) {
                $uk = trim((string) $item['unique_key']);
                $cache[$cachePrefix . "\0" . $uk] = self::labelFromItem($item);
            }
        }

        $result = array();
        foreach ($unique_keys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $ck = $cachePrefix . "\0" . $key;
            $result[$key] = isset($cache[$ck]) ? $cache[$ck] : '';
        }
        return $result;
    }

    /**
     * @param string[] $unique_keys
     * @return array
     */
    private static function fetchItemRows(array $unique_keys, $dict_code)
    {
        if (!$unique_keys) {
            return array();
        }

        $db = kernel::database();
        $esc = kernel::single('goldentra_db');
        $quoted = array();
        foreach ($unique_keys as $key) {
            $quoted[] = "'" . $esc->escape($key, $db) . "'";
        }
        $in = implode(',', $quoted);

        if ($dict_code !== '') {
            $codeEsc = $esc->escape($dict_code, $db);
            return (array) $db->select(
                "SELECT i.unique_key, i.ext_field
                FROM sdb_goldentra_metadata_item AS i
                INNER JOIN sdb_goldentra_metadata AS m ON m.metadata_id = i.metadata_id
                WHERE m.code = '{$codeEsc}'
                AND i.unique_key IN ({$in})"
            );
        }

        return (array) app::get('goldentra')->model('metadata_item')->getList(
            'unique_key,ext_field',
            array('unique_key|in' => $unique_keys)
        );
    }

    /**
     * SAP 字典描述：ext_2 为描述类字段（如小系列号描述），兼容 name/label/text/ext_1
     */
    private static function labelFromItem(array $item)
    {
        $ext = is_array($item['ext_field']) ? $item['ext_field'] : json_decode($item['ext_field'], true);
        if (is_array($ext)) {
            foreach (array('ext_2', 'name', 'label', 'text', 'ext_1') as $key) {
                if (!empty($ext[$key])) {
                    return trim((string) $ext[$key]);
                }
            }
        }
        return !empty($item['unique_key']) ? $item['unique_key'] : '';
    }

    /**
     * Z42 有赞门店库位：按门店编码(ext_5) 取 KUNNR/WERKS/LGORT（ext_2/3/4）
     *
     * @param string $store_bn OMS 门店编码
     * @return array{kunnr:string,werks:string,lgort:string}
     */
    public static function lookupStorePlantLocation($store_bn)
    {
        $empty = array('kunnr' => '', 'werks' => '', 'lgort' => '');
        $store_bn = trim((string) $store_bn);
        if ($store_bn === '') {
            return $empty;
        }

        $map = self::loadZ42StoreLocationMap();
        $norm = self::normalizeStoreBn($store_bn);
        if ($norm === '' || empty($map[$norm])) {
            return $empty;
        }

        $picked = self::pickZ42StoreLocation($map[$norm]);
        if (!$picked) {
            return $empty;
        }

        return array(
            'kunnr' => $picked['kunnr'],
            'werks' => $picked['werks'],
            'lgort' => $picked['lgort'],
        );
    }

    /**
     * @return array normalized_store_bn => list of location rows
     */
    private static function loadZ42StoreLocationMap()
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = array();
        $db = kernel::database();
        $rows = (array) $db->select(
            "SELECT i.unique_key, i.ext_field
            FROM sdb_goldentra_metadata_item AS i
            INNER JOIN sdb_goldentra_metadata AS m ON m.metadata_id = i.metadata_id
            WHERE m.code = 'Z42'"
        );
        foreach ($rows as $item) {
            $ext = is_array($item['ext_field'])
                ? $item['ext_field']
                : json_decode($item['ext_field'], true);
            if (!is_array($ext)) {
                continue;
            }
            $storeBn = isset($ext['ext_5']) ? trim((string) $ext['ext_5']) : '';
            $norm = self::normalizeStoreBn($storeBn);
            if ($norm === '') {
                continue;
            }
            $map[$norm][] = array(
                'unique_key' => isset($item['unique_key']) ? trim((string) $item['unique_key']) : '',
                'ext_1' => isset($ext['ext_1']) ? trim((string) $ext['ext_1']) : '',
                'kunnr' => isset($ext['ext_2']) ? trim((string) $ext['ext_2']) : '',
                'werks' => isset($ext['ext_3']) ? trim((string) $ext['ext_3']) : '',
                'lgort' => isset($ext['ext_4']) ? trim((string) $ext['ext_4']) : '',
            );
        }
        return $map;
    }

    /**
     * 同门店多行：优先无来源(ext_1 空)的基础映射，否则取第一条
     *
     * @param array $candidates
     * @return array|null
     */
    private static function pickZ42StoreLocation(array $candidates)
    {
        if (!$candidates) {
            return null;
        }
        foreach ($candidates as $row) {
            if ($row['ext_1'] === '') {
                return $row;
            }
        }
        return $candidates[0];
    }

    /**
     * 门店编码归一：去左右空白，去掉前导 0（全 0 保留 "0"）
     *
     * @param string $store_bn
     * @return string
     */
    private static function normalizeStoreBn($store_bn)
    {
        $store_bn = trim((string) $store_bn);
        if ($store_bn === '') {
            return '';
        }
        $stripped = ltrim($store_bn, '0');
        return $stripped === '' ? '0' : $stripped;
    }

    public function upsertFromApi($params)
    {
        $now = time();
        $code = trim(isset($params['code']) ? $params['code'] : '');
        $name = trim(isset($params['name']) ? $params['name'] : '');

        $header = isset($params['header']) ? $params['header'] : array();
        if (is_string($header)) {
            $header = json_decode($header, true);
        }
        if (!is_array($header)) {
            $header = array();
        }

        $uniqueKey = trim(goldentra_api_normalize::pickString($header, 'unique_key'));
        if ($uniqueKey === '') {
            $uniqueKey = trim(goldentra_api_normalize::pickString($params, 'unique_key'));
        }

        $items = isset($params['items']) ? $params['items'] : array();
        if (is_string($items)) {
            $items = json_decode($items, true);
        }
        if (empty($items) && !empty($params['item'])) {
            $items = $params['item'];
            if (is_string($items)) {
                $items = json_decode($items, true);
            }
        }

        if ($code === '' || $name === '' || $uniqueKey === '') {
            throw new Exception('code、name、header.unique_key 不能为空');
        }
        if (!is_array($items)) {
            throw new Exception('items 必须为数组');
        }

        $mdl = app::get('goldentra')->model('metadata');
        $itemMdl = app::get('goldentra')->model('metadata_item');
        $headerExt = $this->buildExtField(array_merge($params, $header), array('code', 'name', 'header', 'items', 'item', 'unique_key'));
        $exist = $mdl->dump(array('code' => $code, 'unique_key' => $uniqueKey), 'metadata_id');
        $row = array(
            'code' => $code,
            'name' => $name,
            'unique_key' => $uniqueKey,
            'ext_field' => $headerExt,
            'up_time' => $now,
        );
        if ($exist) {
            $row['metadata_id'] = $exist['metadata_id'];
            $mdl->save($row);
            $metadataId = $exist['metadata_id'];
            $itemMdl->delete(array('metadata_id' => $metadataId));
        } else {
            $row['at_time'] = $now;
            $metadataId = $mdl->insert($row);
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemKey = trim(goldentra_api_normalize::pickString($item, 'unique_key'));
            if ($itemKey === '') {
                continue;
            }
            $itemRow = array(
                'metadata_id' => $metadataId,
                'unique_key' => $itemKey,
                'ext_field' => $this->buildExtField($item, array('unique_key')),
                'at_time' => $now,
                'up_time' => $now,
            );
            $itemMdl->insert($itemRow);
        }
        return array('metadata_id' => $metadataId);
    }

    private function buildExtField($row, $exclude)
    {
        $extKeys = array();
        for ($i = 1; $i <= 20; $i++) {
            $extKeys[] = 'ext_' . $i;
        }
        return goldentra_api_normalize::buildExtField($row, $exclude, $extKeys);
    }
}
