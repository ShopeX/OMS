<?php

/**
 * 豫园珠宝业务字段 → sdb_material_basic_material_props（props_col / props_value）
 */
class goldentra_material_jewelry_props
{
    public static $prop_labels = array(
        'mold_code' => '模号',
        'mold_name' => '模号名称',
        'process_cost' => '加工费',
        'warn_price' => '预警价',
        'price_method' => '定价方式',
        'texture_name' => '材质名称',
        'texture_code' => '材质编码',
        'style_name' => '款式名称',
        'style_code' => '款式编码',
        'pricedimension_code' => '金价定价维度编码',
        'class_name' => '品类名称',
        'class_code' => '品类编码',
        'small_series_name' => '小系列名称',
        'weight' => '克重',
        'main_weight' => '主石重',
        'secondary_weight' => '辅石重',
        'color' => '颜色',
        'clarity' => '净度',
        'diamond_cut' => '切工',
        'certificate' => '证书',
        'sales_mode' => '商品类型',
        'is_weave' => '是否编织',
    );

    public static $prop_cols = array(
        'mold_code',
        'mold_name',
        'process_cost',
        'warn_price',
        'price_method',
        'texture_name',
        'texture_code',
        'style_name',
        'style_code',
        'pricedimension_code',
        'class_name',
        'class_code',
        'small_series_name',
        'weight',
        'main_weight',
        'secondary_weight',
        'color',
        'clarity',
        'diamond_cut',
        'certificate',
        'sales_mode',
        'is_weave',
        'mold_search_crc32',
        'mold_search_hash',
    );

    public static function saveProps($bm_id, array $data)
    {
        $bm_id = intval($bm_id);
        if ($bm_id <= 0) {
            return false;
        }

        $propsMdl = app::get('material')->model('basic_material_props');
        foreach (self::$prop_cols as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $value = $data[$col];
            if ($value === null || $value === '') {
                continue;
            }
            $row = $propsMdl->dump(array('bm_id' => $bm_id, 'props_col' => $col), 'id');
            $payload = array(
                'bm_id' => $bm_id,
                'props_col' => $col,
                'props_value' => (string) $value,
            );
            if ($row) {
                $propsMdl->update(array('props_value' => $payload['props_value']), array('id' => $row['id']));
            } else {
                $propsMdl->insert($payload);
            }
        }

        return true;
    }

    public static function getProps($bm_id, $cols = null)
    {
        $bm_id = intval($bm_id);
        if ($bm_id <= 0) {
            return array();
        }

        $filter = array('bm_id' => $bm_id);
        if ($cols) {
            $filter['props_col|in'] = $cols;
        } else {
            $filter['props_col|in'] = self::$prop_cols;
        }

        $rows = app::get('material')->model('basic_material_props')->getList('props_col,props_value', $filter);
        $props = array();
        foreach ($rows as $row) {
            $props[$row['props_col']] = $row['props_value'];
        }

        return $props;
    }

    public static function findByMoldSearchIndex($crc32, $hash)
    {
        $db = kernel::database();
        $esc = kernel::single('goldentra_db');
        $crc32 = $esc->escape((string) $crc32, $db);
        $hash = $esc->escape($hash, $db);
        $sql = "SELECT m.bm_id, m.material_bn, m.material_name
            FROM sdb_material_basic_material AS m
            INNER JOIN sdb_material_basic_material_props AS p_crc
                ON p_crc.bm_id = m.bm_id
                AND p_crc.props_col = 'mold_search_crc32'
                AND p_crc.props_value = '{$crc32}'
            INNER JOIN sdb_material_basic_material_props AS p_hash
                ON p_hash.bm_id = m.bm_id
                AND p_hash.props_col = 'mold_search_hash'
                AND p_hash.props_value = '{$hash}'";

        return $db->selectrow($sql);
    }

    public static function existsGoldMaterial($mold_code, $weight, $main_weight = 0, $secondary_weight = 0)
    {
        $db = kernel::database();
        $esc = kernel::single('goldentra_db');
        $mold_code = $esc->escape($mold_code, $db);
        $weight = $esc->escape((string) floatval($weight), $db);
        $main_weight = $esc->escape((string) floatval($main_weight), $db);
        $secondary_weight = $esc->escape((string) floatval($secondary_weight), $db);

        $sql = "SELECT m.bm_id FROM sdb_material_basic_material AS m
            INNER JOIN sdb_material_basic_material_props AS pm
                ON pm.bm_id = m.bm_id AND pm.props_col = 'price_method' AND pm.props_value = '2'
            INNER JOIN sdb_material_basic_material_props AS pcode
                ON pcode.bm_id = m.bm_id AND pcode.props_col = 'mold_code' AND pcode.props_value = '{$mold_code}'
            INNER JOIN sdb_material_basic_material_props AS pw
                ON pw.bm_id = m.bm_id AND pw.props_col = 'weight' AND pw.props_value = '{$weight}'
            INNER JOIN sdb_material_basic_material_props AS pmain
                ON pmain.bm_id = m.bm_id AND pmain.props_col = 'main_weight' AND pmain.props_value = '{$main_weight}'
            INNER JOIN sdb_material_basic_material_props AS psec
                ON psec.bm_id = m.bm_id AND psec.props_col = 'secondary_weight' AND psec.props_value = '{$secondary_weight}'";

        return (bool) $db->selectrow($sql);
    }
}
