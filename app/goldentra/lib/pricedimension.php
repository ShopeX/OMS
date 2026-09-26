<?php

/**
 * 金价定价维度编码：品牌 + 商品类目（类目固定 5 位，前缀为品牌编码）
 *
 * 例：110102 = 品牌 1(老庙) + 商品类目 10102
 *     210202 = 品牌 2(亚一) + 商品类目 10202
 */
class goldentra_pricedimension
{
    const CATEGORY_LENGTH = 5;

    public static $brand_labels = array(
        '1' => '老庙',
        '2' => '亚一',
    );

    /**
     * @return array|null brand_code, category_code, brand_label
     */
    public static function parse($code)
    {
        $code = trim((string) $code);
        if ($code === '' || !preg_match('/^\d+$/', $code)) {
            return null;
        }

        $categoryLength = self::CATEGORY_LENGTH;
        if (strlen($code) <= $categoryLength) {
            return null;
        }

        $brandCode = substr($code, 0, -$categoryLength);
        $categoryCode = substr($code, -$categoryLength);
        if ($brandCode === '' || $categoryCode === '') {
            return null;
        }

        return array(
            'brand_code' => $brandCode,
            'category_code' => $categoryCode,
            'brand_label' => self::brandLabel($brandCode),
        );
    }

    public static function brandLabel($brand_code)
    {
        $brand_code = trim((string) $brand_code);
        if ($brand_code === '') {
            return '';
        }
        if (isset(self::$brand_labels[$brand_code])) {
            return self::$brand_labels[$brand_code];
        }
        return '';
    }

    /**
     * 从定价维度编码解析品牌，在 OMS 品牌表关联 brand_id
     *
     * 匹配顺序：brand_name 精确 → brand_code 精确 → brand_name 模糊包含
     */
    public static function resolveBrandId($pricedimension_code)
    {
        $parsed = self::parse($pricedimension_code);
        if ($parsed === null || $parsed['brand_label'] === '') {
            return 0;
        }

        return self::lookupBrandId($parsed['brand_code'], $parsed['brand_label']);
    }

    private static function lookupBrandId($brand_code, $brand_name)
    {
        static $cache = array();
        $cacheKey = $brand_code . '|' . $brand_name;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $brandMdl = app::get('ome')->model('brand');

        $row = $brandMdl->dump(array('brand_name' => $brand_name), 'brand_id');
        if (!empty($row['brand_id'])) {
            return $cache[$cacheKey] = (int) $row['brand_id'];
        }

        if ($brand_code !== '') {
            $row = $brandMdl->dump(array('brand_code' => $brand_code), 'brand_id');
            if (!empty($row['brand_id'])) {
                return $cache[$cacheKey] = (int) $row['brand_id'];
            }
        }

        $db = kernel::database();
        $nameEsc = kernel::single('goldentra_db')->escape($brand_name, $db);
        $fuzzy = $db->selectrow(
            "SELECT brand_id FROM sdb_ome_brand
            WHERE disabled='false' AND (brand_name LIKE '%{$nameEsc}%' OR brand_keywords LIKE '%{$nameEsc}%')
            ORDER BY brand_id ASC"
        );
        if (!empty($fuzzy['brand_id'])) {
            return $cache[$cacheKey] = (int) $fuzzy['brand_id'];
        }

        return $cache[$cacheKey] = 0;
    }

    /**
     * 将主档定价维度解析出的品牌写到基础物料扩展表
     *
     * @param int $bm_id
     * @param string $pricedimension_code
     * @param bool $force true=强制覆盖已有 brand_id；false=仅空品牌时补写
     */
    public static function syncBrandToMaterial($bm_id, $pricedimension_code, $force = true)
    {
        $bm_id = intval($bm_id);
        if ($bm_id <= 0) {
            return false;
        }
        $brand_id = self::resolveBrandId($pricedimension_code);
        if ($brand_id <= 0) {
            return false;
        }

        $extMdl = app::get('material')->model('basic_material_ext');
        $ext = $extMdl->dump(array('bm_id' => $bm_id), 'bm_id,brand_id');
        if (empty($ext['bm_id'])) {
            return false;
        }
        if (!$force && intval($ext['brand_id']) > 0) {
            return true;
        }
        if (intval($ext['brand_id']) === $brand_id) {
            return true;
        }

        return (bool) $extMdl->update(
            array('brand_id' => $brand_id),
            array('bm_id' => $bm_id)
        );
    }
}
