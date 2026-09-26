<?php

/**
 * @author liyu
 * POS 主档信息
 */


class goldentra_pos_material
{
    static $column_keys = array(
        'cate_code',
        'cate_name',
        'class_code',
        'class_name',
        'goods_name',
        'main_weight',
        'mold_code',
        'mold_name',
        'price',
        'pricedimension_code',
        'price_method',
        'process_cost',
        'sales_mode',
        'secondary_weight',
        'small_series_name',
        'style_code',
        'style_name',
        'texture_code',
        'texture_name',
        'unique_code',
        'weight',
        'create_time',
        'update_time',
        'fcolor',
        'fpurity',
        'fcut',
        'fcertificate',
    );

    static $price_method_maps = array(
        'jj' => 1,
        'dj' => 2
    );

    public static function parseMaterial($material)
    {
        $result = array();

        foreach (self::$column_keys as $key) {
            $value = $material[strtoupper($key)];
            $result[$key] = $value === NULL ? '' : $value;
        }
        $result['main_weight'] = $result['main_weight'] == '' ? 0 : $result['main_weight'];
        $result['secondary_weight'] = $result['secondary_weight'] == '' ? 0 : $result['secondary_weight'];
        $result['price'] = $result['price'] == '' ? 0 : $result['price'];
        $result['process_cost'] = $result['process_cost'] == '' ? 0 : $result['process_cost'];
        $result['price_method'] = self::$price_method_maps[$result['price_method']];
        return $result;
    }


    public function insertOnDuplicateKey($material)
    {
        $material = self::parseMaterial($material);

        $unique_code = $material['unique_code'];

        $data = app::get('goldentra')->model('pos_material')->dump(array('unique_code' => $unique_code), 'id,price');

        if (empty($data)) {

            $material['create_time'] = time();
            $material['update_time'] = time();
            $flag = app::get('goldentra')->model('pos_material')->insert($material);
            if (is_numeric($flag) && $flag > 0) {
                app::get('ome')->model('operation_log')->write_log('pos_material_add@goldentra', $unique_code, '导入');
            }
        } else {

            unset($material['create_time']);
            $material['update_time'] = time();
            $flag = app::get('goldentra')->model('pos_material')->update($material, array('id' => $data['id']));

            if (is_numeric($flag) && $flag > 0 && $data['price'] != $material['price']) {
                $msg = sprintf('价格更新%s->%s', $data['price'], $material['price']);
                app::get('ome')->model('operation_log')->write_log('pos_material_update@goldentra', $unique_code, $msg);
            }
        }

        return $flag;
    }

    /**
     * 更新状态已匹配
     * @param $unique_code
     * @param $bn
     * @return boolean
     */
    public function matchedBn($unique_code, $bn)
    {
        $where = array(
            'bn'=> $bn,
            'match_status'=> 1
        );

        $flag = app::get('goldentra')->model('pos_material')->update($where, array('unique_code'=> $unique_code));

        if ($flag === false) {
            return false;
        }

        return true;
    }


    /**
     * 更新价格
     * @param $unique_code
     * @return bool
     * @throws Exception
     */
    public function updatePrice($unique_code)
    {
        $pos_material_data = app::get('goldentra')->model('pos_material')->dump(array('unique_code'=> $unique_code), 'price_method,bn');

        //如果是计价，不作处理
        if ($pos_material_data['price_method'] == 1) {
            return true;
        }

        //货号为空，不作处理
        if (empty($pos_material_data['bn'])) {
            app::get('ome')->model('operation_log')->write_log('pos_material_update@goldentra', $unique_code, '更新价格失败[没有货号]');
            return false;
        }

        $bn = $pos_material_data['bn'];
        $get_min_price_sql = <<<EOF
SELECT
	mpm.id,
	min( price ) AS min_price 
FROM
	sdb_goldentra_pos_material AS mpm
	LEFT JOIN sdb_wms_product_serial AS wps ON wps.serial_number = mpm.unique_code 
WHERE
	mpm.bn = '$bn' 
    AND wps.STATUS IN ('0', '4', '5')
EOF;


        $data = kernel::database()->selectrow($get_min_price_sql);
        $min_price = $data['min_price'];

        if (empty($min_price)) {
            app::get('ome')->model('operation_log')->write_log('pos_material_update@goldentra', $unique_code, '更新价格失败[没有在库库存]');
            return false;
        }

        try {
            $flag = kernel::single('material_mold')->updateTagPrice($bn, $min_price);

            if ($flag === true) {
                app::get('ome')->model('operation_log')->write_log('pos_material_update@goldentra', $unique_code, '更新价格成功');
            }

        } catch (Exception $e) {
            $msg = sprintf('更新价格失败[%s]', $e->getMessage());
            app::get('ome')->model('operation_log')->write_log('pos_material_update@goldentra', $unique_code, $msg);
        }
    }
}
