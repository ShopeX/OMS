<?php

/**
 * 规格键指纹（与 material_basic_material 拼串规则一致）
 */
class goldentra_material_spec
{
    public static function isDiamond($row)
    {
        return !empty($row['fcolor']) && !empty($row['fpurity']);
    }

    public static function moldSearchIndex($price_method, $mold_code, $weight, $main_weight = 0, $secondary_weight = 0, $process_cost = 0)
    {
        $weight = str_pad((int) round($weight * 100), 6, '0', STR_PAD_LEFT);
        $main_weight = str_pad((int) round($main_weight * 100), 6, '0', STR_PAD_LEFT);
        $secondary_weight = str_pad((int) round($secondary_weight * 100), 6, '0', STR_PAD_LEFT);
        $process_cost = (int) round($process_cost * 100);

        if ($price_method == 1) {
            $key = 'J' . $mold_code . $weight . $process_cost;
        } else {
            $key = 'D' . $mold_code . $weight . $main_weight . $secondary_weight;
        }

        return array(crc32($key), md5($key));
    }

    public static function diamondSearchIndex($mold_code, $color, $purity, $cut, $main_weight)
    {
        $main_weight = str_pad((int) round($main_weight * 100), 6, '0', STR_PAD_LEFT);
        $key = 'A' . $mold_code . $color . $purity . $cut . $main_weight;

        return array(crc32($key), md5($key));
    }

    public static function findBasicMaterialByIndex($crc32, $hash)
    {
        return goldentra_material_jewelry_props::findByMoldSearchIndex($crc32, $hash);
    }
}
