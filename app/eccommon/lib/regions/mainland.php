<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


class eccommon_regions_mainland
{
    var $name = '中国地区';
    var $key = 'mainland';
    var $setting = array('desc' => '系统默认为中国地区设置，包括港、澳、台地区。',
                       'maxdepth' => 3,
                       'source' => 'region-mainland.txt');

    function __construct($app){
        $this->app = $app;
        $this->db = kernel::database();
    }

    function insert_area_arr($area_arr){
        $area_str = ""; $max_region_id = NULL;
        foreach($area_arr as $k => $v){
            $v[1] = "'".$v[1]."'";
            $v[3] = "'".$v[3]."'";
            $v[5] = "'".$v[5]."'";
            $area_str .= "(".implode(",", $v)."),";

            $max_region_id = max($max_region_id, $v[0]);
        }
        $area_str_sql = substr($area_str, 0, -1);
        $sql = "REPLACE INTO `{$this->db->prefix}eccommon_regions` (`region_id`, `package`, `p_region_id`,`region_path`,`region_grade`, `local_name`, `haschild`) VALUES $area_str_sql";
        $this->db->exec($sql);

        // 清空非默认的
        if (intval($max_region_id)) {
            $this->db->exec("DELETE FROM `{$this->db->prefix}eccommon_regions` WHERE region_id > ".$max_region_id);
            $affect_rows = $this->db->affect_row();
        }

    }

    function install(){
        $file = $this->app->app_dir.'/'.$this->setting['source'];
        // 检查是否有实现地区安装扩展点的 service，并调用
        foreach(kernel::servicelist('eccommon.service.regions.mainland.install') as $service){
            if(method_exists($service, 'before_install')){
                $new_file = $service->before_install($file);
                // 如果返回了新路径，则使用新路径
                if ($new_file && $new_file != $file) {
                    $file = $new_file;
                }
            }
        }

        $content = file_get_contents($file);
        preg_match("/---.*?---/", $content, $ret);
        $json_string = str_replace($ret[0], "", $content);
        $data = json_decode($json_string, true);
        if($data){
            $area_arr = array();
            foreach($data as $p => $cities){
                $province = explode(",", $p);
                $province_id = $province[0];
                $province_pid = "NULL";
                $province_path = ','.$province[0].',';
                $province_grade = 1;
                $province_name = $province[1];
                $province_haschild = 1;
                $area_arr[] = array($province_id, 'mainland', $province_pid, $province_path, $province_grade, $province_name, $province_haschild);
                foreach ($cities as $c => $qus){
                    $city = explode(",", $c);
                    $city_id = $city[0];
                    $city_pid = $province_id;
                    $city_path = ','.$province_id.','.$city[0].',';
                    $city_grade = 2;
                    $city_name = $city[1];
                    if($qus){
                        $city_haschild = 1;
                        $area_arr[] = array($city_id, 'mainland', $city_pid, $city_path, $city_grade, $city_name, $city_haschild);
                        foreach ($qus as $q => $v){
                            $qu = explode(",", $q);
                            $qu_id = $qu[0];
                            $qu_pid = $city_id;
                            $qu_path = ','.$province_id.','.$city[0].','.$qu[0].',';
                            $qu_grade = 3;
                            $qu_name = $qu[1];
                            $qu_haschild = 0;
                            $area_arr[] = array($qu_id, 'mainland', $qu_pid, $qu_path, $qu_grade, $qu_name, $qu_haschild);
                        }
                    }else{
                        $city_haschild = 0;
                        $area_arr[] = array($city_id, 'mainland', $city_pid, $city_path, $city_grade, $city_name, $city_haschild);
                    }
                }
            }
            $this->insert_area_arr($area_arr);
            return true;
        }else{
            return false;
        }
    }
}
