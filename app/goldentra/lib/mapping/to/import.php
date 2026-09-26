<?php

class goldentra_mapping_to_import
{
    public function run(&$cursor_id, $params, &$errmsg)
    {
        $importObj = app::get($params['app'])->model($params['mdl']);
        $itemsObj = app::get('goldentra')->model('mapping_item');
        $oOperation_log = app::get('ome')->model('operation_log');
        $validate = kernel::single('goldentra_mapping_validate');

        foreach ($params['sdfdata'] as $v) {
            $i = 1;
            $min_weight_list = $max_weight_list = $weight_list = array();
            $min_main_list = $max_main_list = $main_weight_list = array();
            $min_extra_list = $max_extra_list = $extra_weight_list = array();
            $main = array();

            foreach ($v as $mk => $mv) {
                if ($i == 1) {
                    $mapping_filter = array('mold_code' => $mv['mold_code'], 'disabled' => '0', 'type' => 'gold');
                    $mapping_info = $importObj->dump($mapping_filter, 'mapping_id,type');
                    if ($mapping_info) {
                        $main = $mapping_info;
                    } else {
                        $main = array(
                            'mold_code' => $mv['mold_code'],
                            'type' => 'gold',
                            'disabled' => '0',
                            'at_time' => time(),
                            'up_time' => time(),
                        );
                        $mapping_id = $importObj->insert($main);
                        if (!$mapping_id) {
                            continue;
                        }
                        $main['mapping_id'] = $mapping_id;
                    }
                }
                $v[$mk]['mapping_id'] = $main['mapping_id'];
                $v[$mk]['mold_code'] = $mv['mold_code'];
                $v[$mk]['at_time'] = time();
                $v[$mk]['up_time'] = time();
                $v[$mk]['bn'] = goldentra_mapping_code::genGoldMaterialBn($v[$mk]);

                $min_weight_list[$mk] = $mv['min_weight'];
                $max_weight_list[$mk] = $mv['max_weight'];
                $weight_list[$mk] = $mv['weight'];
                $min_main_list[$mk] = $mv['min_main'];
                $max_main_list[$mk] = $mv['max_main'];
                $main_weight_list[$mk] = $mv['main_weight'];
                $min_extra_list[$mk] = $mv['min_extra'];
                $max_extra_list[$mk] = $mv['max_extra'];
                $extra_weight_list[$mk] = $mv['extra_weight'];
                $i++;

                $filter_is_exist = array(
                    'mapping_id' => $main['mapping_id'],
                    'min_weight' => $mv['min_weight'],
                    'max_weight' => $mv['max_weight'],
                    'weight' => $mv['weight'],
                    'min_main' => $mv['min_main'],
                    'max_main' => $mv['max_main'],
                    'main_weight' => $mv['main_weight'],
                    'min_extra' => $mv['min_extra'],
                    'max_extra' => $mv['max_extra'],
                    'extra_weight' => $mv['extra_weight'],
                    'min_tag_price' => $mv['min_tag_price'],
                    'max_tag_price' => $mv['max_tag_price'],
                );
                $_info = $itemsObj->dump($filter_is_exist, 'item_id,tag_price');
                if ($_info || $mv['tag_price'] <= 0) {
                    unset($v[$mk]);
                }
            }

            $msg = '';
            $data = array(
                'min_weight' => $min_weight_list,
                'max_weight' => $max_weight_list,
                'weight' => $weight_list,
                'min_main' => $min_main_list,
                'max_main' => $max_main_list,
                'main_weight' => $main_weight_list,
                'min_extra' => $min_extra_list,
                'max_extra' => $max_extra_list,
                'extra_weight' => $extra_weight_list,
            );
            if ($validate->check($data, $msg) && $v) {
                $item_sql = ome_func::get_insert_sql($itemsObj, $v);
                kernel::database()->exec($item_sql);
                $oOperation_log->write_log('mapping_item_import@goldentra', $main['mapping_id'], '导入模号' . $mv['mold_code']);
            }
        }
        return false;
    }
}
