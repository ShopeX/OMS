<?php

/**
 * 店铺支付方式编码(ZAHLART) 配置读取
 */
class goldentra_zahlart
{
    const DEFAULT_ZAHLART = 'ZP39';

    /**
     * 按店铺编码取 ZAHLART；无配置或已禁用时返回默认 ZP39
     *
     * @param string $shop_bn
     * @return string
     */
    public function getByShopBn($shop_bn)
    {
        $shop_bn = trim((string) $shop_bn);
        if ($shop_bn === '') {
            return self::DEFAULT_ZAHLART;
        }

        $row = app::get('goldentra')->model('zahlart')->dump(
            array('shop_bn' => $shop_bn, 'disabled' => 'false'),
            'zahlart'
        );
        if (!empty($row['zahlart'])) {
            return strtoupper(trim($row['zahlart']));
        }

        return self::DEFAULT_ZAHLART;
    }

    /**
     * 按店铺 ID 取 ZAHLART
     *
     * @param string $shop_id
     * @return string
     */
    public function getByShopId($shop_id)
    {
        $shop_id = trim((string) $shop_id);
        if ($shop_id === '') {
            return self::DEFAULT_ZAHLART;
        }

        $row = app::get('goldentra')->model('zahlart')->dump(
            array('shop_id' => $shop_id, 'disabled' => 'false'),
            'zahlart'
        );
        if (!empty($row['zahlart'])) {
            return strtoupper(trim($row['zahlart']));
        }

        return self::DEFAULT_ZAHLART;
    }
}
