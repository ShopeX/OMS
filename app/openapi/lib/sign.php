<?php

/**
 * OpenAPI 签名拼接：JSON 字符串参数统一规范化，避免中文与 \uXXXX 验签不一致
 */
class openapi_sign
{
    /**
     * JSON 业务参数 decode 后按 UTF-8 中文重新 encode，非 JSON 原样返回
     */
    public static function normalizeValue($val)
    {
        if (!is_string($val)) {
            return $val;
        }

        $trimmed = ltrim($val);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return $val;
        }

        $decoded = json_decode($val, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $val;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function assemble($params)
    {
        if (!is_array($params)) {
            return null;
        }

        ksort($params, SORT_STRING);
        $sign = '';
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }
            if (is_bool($val)) {
                $val = $val ? 1 : 0;
            }
            if (is_array($val)) {
                $sign .= $key . self::assemble($val);
            } else {
                $sign .= $key . self::normalizeValue($val);
            }
        }

        return $sign;
    }

    /**
     * 验签/加签前统一参数形态（与 OpenAPI $_POST 一致）
     */
    public static function prepareForSign($params)
    {
        if (!is_array($params)) {
            return array();
        }

        unset($params['sign']);
        $prepared = array();
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }
            if (is_bool($val)) {
                $val = $val ? '1' : '0';
            } elseif (is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $val = (string) $val;
            }
            $prepared[$key] = self::normalizeValue($val);
        }

        return $prepared;
    }

    /**
     * 参与签名的拼接串（与 openapi_entrance::gen_sign 一致）
     */
    public static function assembleForSign($params)
    {
        return self::assemble(self::prepareForSign($params));
    }

    public static function genSign($params, $token)
    {
        if (!$token) {
            return false;
        }

        $assemble = self::assembleForSign($params);

        return strtoupper(md5(strtoupper(md5($assemble)) . $token));
    }
}
