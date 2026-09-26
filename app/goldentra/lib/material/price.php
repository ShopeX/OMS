<?php

class goldentra_material_price
{
    public function updateTagPrice($bn, $tag_price)
    {
        $mapping = kernel::single('goldentra_mapping')->getMappingByBn($bn);
        if (empty($mapping)) {
            throw new Exception('没有找到定价Mapping信息');
        }
        if (empty($tag_price)) {
            throw new Exception('更新价格无效');
        }
        if ($mapping['tag_price'] == $tag_price) {
            return false;
        }

        $flag = app::get('goldentra')->model('mapping_item')->update(
            array('tag_price' => $tag_price, 'up_time' => time()),
            array('item_id' => $mapping['item_id'])
        );
        if ($flag === false) {
            throw new Exception('更新Mapping价格失败');
        }

        $bm = app::get('material')->model('basic_material')->dump(array('material_bn' => $bn), 'bm_id');
        if ($bm) {
            app::get('material')->model('basic_material_ext')->update(
                array('retail_price' => $tag_price),
                array('bm_id' => $bm['bm_id'])
            );
        }

        return true;
    }
}
