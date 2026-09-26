<?php

class goldentra_mapping_validate
{
    public function check($data, &$msg)
    {
        $min_weight = $data['min_weight'];
        $max_weight = $data['max_weight'];
        $range_list = array();
        foreach ($min_weight as $k => $v) {
            $v = sprintf('%.2f', $v);
            $imax = sprintf('%.2f', $max_weight[$k]);
            $range_list[] = array($v, $imax);
        }

        $r = array_unique(call_user_func_array('array_merge', $range_list));
        sort($r);

        foreach ($range_list as $v) {
            if (array_search($v[0], $r) != array_search($v[1], $r) - 1) {
                $msg = $v[1] . '重复区间存在';
                return false;
            }
        }
        return true;
    }
}
