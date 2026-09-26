<?php

/**
 * SAP OpenAPI 字段规范化（V1.3 小写 snake_case，兼容历史大写字段）
 */
class goldentra_api_normalize
{
    public static function pick(array $row, $key)
    {
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }
        $upper = strtoupper($key);
        if (array_key_exists($upper, $row)) {
            return $row[$upper];
        }
        return null;
    }

    public static function pickString(array $row, $key, $default = '')
    {
        $val = self::pick($row, $key);
        if ($val === null || $val === '') {
            return $default;
        }
        return is_string($val) ? trim($val) : $val;
    }

    public static function mapRow(array $raw, array $field_map)
    {
        $out = array();
        foreach ($field_map as $api => $col) {
            $val = self::pick($raw, $api);
            $out[$col] = ($val === null || $val === '') ? '' : $val;
        }
        return $out;
    }

    public static function buildExtField(array $raw, array $excludeKeys, array $extKeys = null)
    {
        $exclude = array();
        foreach ($excludeKeys as $key) {
            $exclude[strtolower($key)] = true;
            $exclude[strtoupper($key)] = true;
        }

        $ext = array();
        if ($extKeys !== null) {
            foreach ($extKeys as $key) {
                $val = self::pick($raw, $key);
                if ($val !== null && $val !== '') {
                    $ext[strtolower($key)] = $val;
                }
            }
            return $ext ? json_encode($ext, JSON_UNESCAPED_UNICODE) : '';
        }

        foreach ($raw as $key => $value) {
            if (isset($exclude[$key]) || $value === '' || $value === null) {
                continue;
            }
            $ext[$key] = $value;
        }

        return $ext ? json_encode($ext, JSON_UNESCAPED_UNICODE) : '';
    }

    /**
     * 解析批量 JSON 字段：兼容 JSON 字符串与 application/json 已解码的 array
     *
     * @param mixed $raw
     * @param string|null $singleRowKey 若顶层含该键则视为单条记录
     * @return array[]|false
     */
    public static function decodeJsonBatch($raw, $singleRowKey = null)
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return false;
        }
        if ($singleRowKey !== null && self::pick($raw, $singleRowKey) !== null) {
            return array($raw);
        }
        return $raw ? $raw : false;
    }

    /**
     * 从 OpenAPI 字段说明提取简短中文名
     */
    public static function fieldLabel($field, array $descriptions)
    {
        $field = strtolower(trim((string) $field));
        if ($field === '' || !isset($descriptions[$field])) {
            return $field;
        }

        $desc = trim((string) $descriptions[$field]);
        if ($desc === '') {
            return $field;
        }
        if (preg_match('/^(.+?)（/u', $desc, $match)) {
            return trim($match[1]);
        }
        if (preg_match('/^(.+?)：/u', $desc, $match)) {
            return trim($match[1]);
        }
        if (preg_match('/^(.+?)；/u', $desc, $match)) {
            return trim($match[1]);
        }
        return $desc;
    }

    /**
     * 缺少必填参数时的错误文案：field_name(中文描述)
     */
    public static function formatMissingFieldsMessage(array $fields, array $descriptions, $prefix = '缺少必要参数')
    {
        $fields = array_values(array_unique(array_filter(array_map('trim', $fields), 'strlen')));
        if (!$fields) {
            return $prefix;
        }

        $parts = array();
        foreach ($fields as $field) {
            $label = self::fieldLabel($field, $descriptions);
            $parts[] = $field . '(' . $label . ')';
        }
        return $prefix . ': ' . implode(',', $parts);
    }

    /**
     * 批量接口单行失败文案：主键=值，便于 SAP 定位错误行
     *
     * @param string $message
     * @param array $keys 主键字段 => 值，如 array('unique_code' => '6000099962')
     */
    public static function formatRowError($message, array $keys = array())
    {
        $message = trim((string) $message);
        $parts = array();
        foreach ($keys as $field => $value) {
            $field = trim((string) $field);
            $value = trim((string) $value);
            if ($field === '' || $value === '') {
                continue;
            }
            $parts[] = $field . '=' . $value;
        }
        if (!$parts) {
            return $message;
        }
        $prefix = implode(',', $parts);
        if ($message === '') {
            return $prefix;
        }
        return $prefix . '：' . $message;
    }
}
