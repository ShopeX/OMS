<?php

/**
 * 主档图片链接 → 基础物料 image 关联
 */
class goldentra_material_image
{
    const PRODUCT_IMAGE_BASE = 'https://image.yuyuanjewelry.com/product/';

    /**
     * SAP 同步图片编号 → 完整 URL；已是 http(s) 则原样返回
     */
    public static function resolveProductUrl($pic)
    {
        $pic = trim((string) $pic);
        if ($pic === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $pic)) {
            return $pic;
        }
        if (preg_match('/^\d+$/', $pic)) {
            return self::PRODUCT_IMAGE_BASE . $pic;
        }
        return $pic;
    }

    /**
     * 将主档图片关联到基础物料（storage=network，不下载落盘）
     *
     * 注意：不能走 image::store($url)，其内部会先 getimagesize，远程编号链接常失败。
     */
    public static function attachRemoteUrl($bm_id, $pic_url)
    {
        $bm_id = intval($bm_id);
        $pic_url = self::resolveProductUrl($pic_url);
        if ($bm_id <= 0 || $pic_url === '') {
            return false;
        }
        if (!preg_match('#^https?://#i', $pic_url)) {
            return false;
        }

        $imageModel = app::get('image')->model('image');
        $existing = $imageModel->getAttachedImages('material', $bm_id);
        foreach ((array) $existing as $img) {
            if (!empty($img['url']) && $img['url'] === $pic_url) {
                return true;
            }
        }

        $image_id = self::findOrCreateNetworkImage($pic_url);
        if (!$image_id) {
            return false;
        }

        return (bool) $imageModel->attach($image_id, 'material', $bm_id);
    }

    /**
     * 查找或创建 remote 图片记录
     */
    private static function findOrCreateNetworkImage($pic_url)
    {
        $imageModel = app::get('image')->model('image');
        $exist = $imageModel->dump(array('url' => $pic_url, 'storage' => 'network'), 'image_id');
        if (!empty($exist['image_id'])) {
            return $exist['image_id'];
        }

        $image_id = $imageModel->gen_id();
        $params = array(
            'image_id' => $image_id,
            'image_name' => basename(parse_url($pic_url, PHP_URL_PATH)),
            'storage' => 'network',
            'url' => $pic_url,
            'ident' => $pic_url,
            'width' => 0,
            'height' => 0,
            'last_modified' => time(),
        );
        if (!$imageModel->save($params)) {
            return false;
        }

        return $image_id;
    }
}
