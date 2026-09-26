<?php

/**
 * 定价 Mapping 基础物料编码（与现网 material_dj_mapping_items.m_bn 规则一致）
 */
class goldentra_mapping_code
{
    /**
     * D + 模号 + crc32(D+模号+克重8位+主石8位+辅石8位)
     */
    public static function genGoldMaterialBn($item)
    {
        return app::get('goldentra')->model('mapping')->gen_id($item);
    }

    public static function ensureItemBn($item)
    {
        if (!empty($item['bn'])) {
            return $item['bn'];
        }
        $bn = self::genGoldMaterialBn($item);
        if ($bn && !empty($item['item_id'])) {
            app::get('goldentra')->model('mapping_item')->update(
                array('bn' => $bn, 'up_time' => time()),
                array('item_id' => $item['item_id'])
            );
        }
        return $bn;
    }
}
