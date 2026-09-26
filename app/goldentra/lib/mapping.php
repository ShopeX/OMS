<?php

class goldentra_mapping
{
    public function getGoldMappingItem($mold_code, $weight, $main_weight = 0, $secondary_weight = 0)
    {
        $db = kernel::database();
        $mold_code = kernel::single('goldentra_db')->escape($mold_code, $db);
        $weight = floatval($weight);
        $main_weight = floatval($main_weight);
        $secondary_weight = floatval($secondary_weight);

        $sql = "SELECT i.* FROM sdb_goldentra_mapping_item AS i
            LEFT JOIN sdb_goldentra_mapping AS m ON m.mapping_id = i.mapping_id
            WHERE m.disabled = '0' AND m.type = 'gold'
            AND i.mold_code = '{$mold_code}'
            AND i.min_weight <= '{$weight}' AND i.max_weight > '{$weight}'";

        if ($main_weight > 0) {
            $sql .= " AND i.min_main <= {$main_weight} AND i.max_main > {$main_weight}";
        } else {
            $sql .= ' AND i.min_main = 0';
        }

        if ($secondary_weight > 0) {
            $sql .= " AND i.min_extra <= {$secondary_weight} AND i.max_extra > {$secondary_weight}";
        } else {
            $sql .= ' AND i.min_extra = 0';
        }

        return $db->selectrow($sql);
    }

    public function getDiamondMappingItem($mold_code, $color, $purity, $cut, $main_weight)
    {
        $db = kernel::database();
        $mold_code = kernel::single('goldentra_db')->escape($mold_code, $db);
        $color = kernel::single('goldentra_db')->escape($color, $db);
        $purity = kernel::single('goldentra_db')->escape($purity, $db);
        $cut = kernel::single('goldentra_db')->escape($cut, $db);
        $main_weight = floatval($main_weight);

        $sql = "SELECT i.* FROM sdb_goldentra_mapping_item AS i
            LEFT JOIN sdb_goldentra_mapping AS m ON m.mapping_id = i.mapping_id
            WHERE m.disabled = '0' AND m.type = 'diamond'
            AND i.mold_code = '{$mold_code}'
            AND i.color = '{$color}' AND i.clarity = '{$purity}' AND i.diamond_cut = '{$cut}'";

        if ($main_weight > 0) {
            $sql .= " AND i.min_main <= '{$main_weight}' AND i.max_main > '{$main_weight}'";
        } else {
            $sql .= ' AND i.min_main = 0';
        }

        return $db->selectrow($sql);
    }

    public function getMappingByBn($bn)
    {
        $bn = kernel::single('goldentra_db')->escape($bn);
        $sql = "SELECT i.* FROM sdb_goldentra_mapping_item AS i
            LEFT JOIN sdb_goldentra_mapping AS m ON m.mapping_id = i.mapping_id
            WHERE i.bn = '{$bn}' AND m.disabled = '0'";

        return kernel::database()->selectrow($sql);
    }

    /**
     * 定价主档：按 mapping 明细 bn 关联/创建基础物料
     *
     * @return string material_bn
     */
    public function resolveBasicMaterialByMappingItem($row, $norm, $mapping_item)
    {
        $bn = goldentra_mapping_code::ensureItemBn($mapping_item);
        if (empty($bn)) {
            throw new Exception('生成基础物料编码失败');
        }

        $self = $this;
        return kernel::single('goldentra_db')->withLock('bm_map_' . $bn, 15, function () use ($self, $row, $norm, $mapping_item, $bn) {
            $bm = app::get('material')->model('basic_material')->dump(
                array('material_bn' => $bn),
                'bm_id,material_bn'
            );
            if ($bm) {
                $self->updateMappingItemBmId($mapping_item['item_id'], $bm['bm_id']);
                if (!empty($row['pricedimension_code'])) {
                    goldentra_pricedimension::syncBrandToMaterial($bm['bm_id'], $row['pricedimension_code'], true);
                }
                return $bm['material_bn'];
            }

            $created = kernel::single('goldentra_material_bootstrap')->createFromMaterialRow(
                $row,
                $norm,
                $mapping_item['item_id'],
                $bn
            );
            if (empty($created['material_bn'])) {
                throw new Exception('创建基础物料失败');
            }
            return $created['material_bn'];
        });
    }

    public function updateMappingItemBmId($item_id, $bm_id)
    {
        return app::get('goldentra')->model('mapping_item')->update(
            array('bm_id' => $bm_id, 'up_time' => time()),
            array('item_id' => $item_id)
        );
    }

    public function updateMappingItemBn($item_id, $bn, $bm_id)
    {
        return app::get('goldentra')->model('mapping_item')->update(
            array('bn' => $bn, 'bm_id' => $bm_id, 'up_time' => time()),
            array('item_id' => $item_id)
        );
    }

    public function checkGoldMaterialInfo($mold_code, $weight, $main_weight = 0, $secondary_weight = 0)
    {
        return goldentra_material_jewelry_props::existsGoldMaterial(
            $mold_code,
            $weight,
            $main_weight,
            $secondary_weight
        );
    }

    public function refresh($mapping_ids)
    {
        $mapping_ids = array_map(function ($id) {
            return "'" . intval($id) . "'";
        }, (array) $mapping_ids);
        $ids = implode(',', $mapping_ids);

        $sql = "SELECT item_id FROM sdb_goldentra_mapping_item AS i
            LEFT JOIN sdb_goldentra_mapping AS m ON m.mapping_id = i.mapping_id
            WHERE (i.bn IS NULL OR i.bn = '')
            AND m.mapping_id IN ($ids) AND m.disabled = '0'";
        $items = kernel::database()->select($sql);

        foreach ($items as $item) {
            $this->_fixMappingItem($item['item_id']);
        }
    }

    private function _fixMappingItem($item_id)
    {
        $item = app::get('goldentra')->model('mapping_item')->dump(array('item_id' => $item_id), '*');
        if (empty($item)) {
            return;
        }

        $bn = goldentra_mapping_code::ensureItemBn($item);
        if (empty($bn)) {
            return;
        }

        $bm = app::get('material')->model('basic_material')->dump(
            array('material_bn' => $bn),
            'bm_id,material_bn'
        );
        if ($bm) {
            $this->updateMappingItemBmId($item_id, $bm['bm_id']);
        }
    }
}
