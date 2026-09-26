<?php

class goldentra_mapping_diamond
{
    private static $color_rows = array(
        'D',
        'E',
        'F',
        'G',
        'H',
        'I',
        'J',
        'K',
        'L',
        'D-E',
        'F-G',
        'G-H',
        'I-J',
        'J-K',
    );

    private static $clarity_rows = array(
        'SI',
        'SI1',
        'SI2',
        'VS',
        'VS1',
        'VS2',
        'VVS',
        'VVS1',
        'VVS2',
    );

    private static $diamond_cut_rows = array(
        'F',
        'G',
        'VG',
        'EX',
        '',
    );
    
    public static function checkColor($color)
    {
        return in_array($color, self::$color_rows);
    }

    public static function checkClarity($color)
    {
        return in_array($color, self::$clarity_rows);
    }

    public static function checkDiamondCut($color)
    {
        return in_array($color, self::$diamond_cut_rows);
    }

    public static function getColorRows()
    {
        return self::$color_rows;
    }

    public static function getClarityRows()
    {
        return self::$clarity_rows;
    }

    public static function getDiamondCutRows()
    {
        return self::$diamond_cut_rows;
    }

    private function checkWeightRangeByItems($weight_range_items, &$msg)
    {
        $weight_list = array();
        $weight_range_keys = array();

        foreach ($weight_range_items as $k => $weight_range_item) {

            $min_main = sprintf('%.3f', $weight_range_item[0]);
            $max_main = sprintf('%.3f', $weight_range_item[1]);
            $main_weight = $weight_range_item[2];
            $k1 = "$k:0:$min_main";
            $k2 = "$k:1:$max_main";

            $weight_range_key = "$min_main:$max_main";

            if (in_array($weight_range_key, $weight_range_keys)) {
                $msg = '主石克重区间不能重复';
                return false;
            }

            array_push($weight_range_keys, $weight_range_key);

            if ($min_main >= $max_main) {
                $msg = '最小主石重大于等于最大主石重';
                return false;
            }

            if ($main_weight >= $max_main || $main_weight < $min_main) {
                $msg = '主石重大于等于最大主石重或小于最小主石重';
                return false;
            }

            array_push($weight_list, $k1);
            array_push($weight_list, $k2);
        }

        usort($weight_list, function ($v1, $v2) {

            $v1 = explode(':', $v1);
            $v2 = explode(':', $v2);

            if ($v1[2] > $v2[2]) {
                return 1;
            }

            if ($v1[2] < $v2[2]) {
                return -1;
            }

            if ($v1[0] > $v2[0]) {
                return 1;
            }

            if ($v1[0] < $v2[0]) {
                return -1;
            }

            return 0;
        });

        for ($length = count($weight_list), $i = 0; $i < $length - 1;$i++) {

            $v1 = explode(':', $weight_list[$i]);
            $v2 = explode(':', $weight_list[++$i]);

            if ($v1[0] != $v2[0]) {
                $msg = '主石克重区间不能重复';
                return false;
            }
        }

        return true;
    }

    public function checkWeightRange($weight_range_multi, &$msg)
    {
        foreach ($weight_range_multi as $weight_range_items) {

            if (count($weight_range_items) == 1) {
                continue;
            }

            $flag = $this->checkWeightRangeByItems($weight_range_items, $msg);

            if ($flag === false) {
                return false;
            }
        }

        return true;
    }

    public static function getWeightRange($post_data)
    {
        $weight_range_multi = array();
        foreach ($post_data['main_weight'] as $k=> $data) {

            $key = sprintf('%s-%s-%s', $post_data['color'][$k], $post_data['clarity'][$k], $post_data['diamond_cut'][$k]);

            if (!isset($weight_range_multi[$key])) {
                $weight_range_multi[$key] = array();
            }
            array_push($weight_range_multi[$key], array($post_data['min_main'][$k], $post_data['max_main'][$k], $post_data['main_weight'][$k]));
        }

        return $weight_range_multi;
    }

    public static function isDiamond($color, $purity, $cut)
    {
        if (empty($color)) {
            return false;
        }

        if (empty($purity)) {
            return false;
        }

        /*if (empty($cut)) {
            return false;
        }*/

        return true;
    }

    public function getDiamondMappingItem($mold_code, $color, $purity, $cut, $main_weight)
    {

        $sql = <<<EOF
SELECT
	mdmi.* 
FROM
	sdb_goldentra_mapping_item AS mdmi
	LEFT JOIN sdb_goldentra_mapping AS mdm ON mdm.mapping_id = mdmi.mapping_id 
WHERE
	mdm.disabled = '0' 
	AND mdmi.mold_code = '$mold_code' 
	AND mdmi.color = '$color' 
	AND mdmi.clarity = '$purity' 
	AND mdmi.diamond_cut = '$cut' 
EOF;

        if ($main_weight > 0) {
            $sql .= " AND mdmi.min_main <= '$main_weight' AND mdmi.max_main > '$main_weight' ";
        } else {
            $sql .= " AND mdmi.min_main = '0' ";
        }

        return kernel::database()->selectrow($sql);
    }

    public function import($data)
    {
        $mold_code = $data['mold_code'];
        $color = $data['color'];
        $clarity = $data['clarity'];
        $diamond_cut = $data['diamond_cut'];
        $min_main = $data['min_main'];
        $max_main = $data['max_main'];
        $main_weight = $data['main_weight'];
        $tag_price = $data['tag_price'];

        if (empty($mold_code)){
            return array(false, '模号为空');
        }

        if (!goldentra_mapping_diamond::checkColor($color)) {
            return array(false, '颜色错误');
        }

        if (!goldentra_mapping_diamond::checkClarity($clarity)) {
            return array(false, '净度错误');
        }

        if (!goldentra_mapping_diamond::checkDiamondCut($diamond_cut)) {
            return array(false, '切工错误');
        }

        //判断模号mapping是否存在

        //如果不存在直接添加
        $mapping = app::get('goldentra')->model('mapping')->dump(array('mold_code'=> $mold_code, 'disabled'=> '0'), 'mapping_id,type');

        if (empty($mapping)) {
            return $this->addDjMapping($data)
                ? array(true, 'success')
                : array(false, '新增失败');
        }

        if ($mapping['type'] != 'diamond') {
            return array(false, '不是钻石mapping');
        }

        //验证
        $mapping_id = $mapping['mapping_id'];
        $mapping_item = app::get('goldentra')->model('mapping_item')->getList('*', array('mapping_id'=> $mapping_id));

        if (empty($mapping_item)) {
            return $this->addDjMappingItem($mapping_id, $data)
                ? array(true, 'success')
                : array(false, '新增失败');
        }

        array_push($mapping_item, $data);
        if ($this->checkWeightRange($this->formatMappingItemsByCheckWeightRange($mapping_item), $err_msg) == false) {
            return array(false, $err_msg);
        }

        return $this->addDjMappingItem($mapping_id, $data)
            ? array(true, 'success')
            : array(false, '新增失败');
    }

    private function formatMappingItemsByCheckWeightRange($mapping_item)
    {
        $weight_range_multi = array();

        foreach ($mapping_item as $mapping_item) {

            $key = sprintf('%s-%s-%s', $mapping_item['color'], $mapping_item['clarity'], $mapping_item['diamond_cut']);

            if (!isset($weight_range_multi[$key])) {
                $weight_range_multi[$key] = array();
            }
            array_push($weight_range_multi[$key], array($mapping_item['min_main'], $mapping_item['max_main'], $mapping_item['main_weight']));
        }

        return $weight_range_multi;
    }

    private function addDjMapping($data)
    {
        $mapping_data = array(
            'mold_code'=> $data['mold_code'],
            'type'=> 'diamond',
            'at_time'=> time(),
            'up_time'=> time(),
        );

        $mapping_id = app::get('goldentra')->model('mapping')->insert($mapping_data);

        if ($mapping_id == false) {
            return false;
        }

        return $this->addDjMappingItem($mapping_id, $data);
    }

    private function addDjMappingItem($mapping_id, $data)
    {
        $mapping_item_data = array(
            'mapping_id'    => $mapping_id,
            'min_main'    => $data['min_main'],
            'max_main'    => $data['max_main'],
            'main_weight' => $data['main_weight'],
            'tag_price'   => $data['tag_price'],
            'mold_code'   => $data['mold_code'],
            'color'       => $data['color'],
            'clarity'     => $data['clarity'],
            'diamond_cut' => $data['diamond_cut'],
            'max_weight'  => 0,
            'weight'      => 0,
            'min_extra'   => 0,
            'max_extra'   => 0,
            'extra_weight'=> 0,
            'at_time'     => time(),
            'up_time'     => time(),
        );

        return app::get('goldentra')->model('mapping_item')->insert($mapping_item_data);
    }
}