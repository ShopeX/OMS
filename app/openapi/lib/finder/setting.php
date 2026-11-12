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

class openapi_finder_setting{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    static private $_statistics = null;

    function __construct(){
        $this->getStatistics();
    }


    var $addon_cols = "s_id,code,status";
    var $column_edit = "操作";
    var $column_edit_width = "100";

    function column_edit($row) {
        $finder_id = $_GET['_finder']['finder_id'];
        if($row[$this->col_prefix . 'status'] == 1){
            $ret = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要暂停该设置吗？？？\\n\\n注意：你还可以随时启用该设置。\")) {href=\"index.php?app=openapi&ctl=admin_setting&act=setStatus&p[0]={$row[$this->col_prefix . 's_id']}&p[1]=0&finder_id={$finder_id}\";}'>暂停</a>";
        }else{
            $ret = "<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要启用该设置吗？？？\\n\\n注意：你还可以随时暂停该设置。\")) {href=\"index.php?app=openapi&ctl=admin_setting&act=setStatus&p[0]={$row[$this->col_prefix . 's_id']}&p[1]=1&finder_id={$finder_id}\";}'>启用</a>";
        }
        $ret .= "&nbsp;<a href='index.php?app=openapi&ctl=admin_setting&act=edit&p[0]={$row[$this->col_prefix . 's_id']}&finder_id={$finder_id}' target=\"_blank\">编辑</a>";
        return $ret;
    }

    var $column_total = "总调用次数";
    var $column_total_width = "120";
    function column_total($row){
        $total = (isset(self::$_statistics[$row[$this->col_prefix . 'code']]) && isset(self::$_statistics[$row[$this->col_prefix . 'code']]['total'])) ? self::$_statistics[$row[$this->col_prefix . 'code']]['total'] : 0;
        return $total;
    }

    var $column_yesterday_count = "昨日调用次数";
    var $column_yesterday_count_width = "120";
    function column_yesterday_count($row){
        $total = (isset(self::$_statistics[$row[$this->col_prefix . 'code']]) && isset(self::$_statistics[$row[$this->col_prefix . 'code']]['yesterday'])) ? array_shift(self::$_statistics[$row[$this->col_prefix . 'code']]['yesterday']) : 0;
        return $total;
    }

    var $column_today_count = "今日调用次数";
    var $column_today_count_width = "120";
    function column_today_count($row){
        $total = (isset(self::$_statistics[$row[$this->col_prefix . 'code']]) && isset(self::$_statistics[$row[$this->col_prefix . 'code']]['today'])) ? array_shift(self::$_statistics[$row[$this->col_prefix . 'code']]['today']) : 0;
        return $total;
    }

    private function getStatistics(){
        if(isset(self::$_statistics)){
            return self::$_statistics;
        }else{
            $tmpdata = app::get('openapi')->getConf('datainterface.statistics');
            self::$_statistics = unserialize($tmpdata);
        }

    }

    var $detail_basic = "基础设置详情";
    function detail_basic($sid){
        $render = app::get('openapi')->render();
        $settingObj = app::get('openapi')->model('setting');
        $settingInfo = $settingObj->dump(array('s_id'=>$sid));
        $flag = $settingInfo['code'];

        $tmp_statisticsInfos = self::$_statistics;
        $methodLists = openapi_conf::getMethods();
        if(isset($tmp_statisticsInfos[$flag]) && $flag){
            $data['total'] = isset($tmp_statisticsInfos[$flag]['total']) ? $tmp_statisticsInfos[$flag]['total'] : 0;
            $data['yesterday'] = isset($tmp_statisticsInfos[$flag]['yesterday']) ? array_shift($tmp_statisticsInfos[$flag]['yesterday']) : 0;
            $data['today'] = isset($tmp_statisticsInfos[$flag]['today']) ? array_shift($tmp_statisticsInfos[$flag]['today']) : 0;

            unset($tmp_statisticsInfos[$flag]['total']);
            unset($tmp_statisticsInfos[$flag]['yesterday']);
            unset($tmp_statisticsInfos[$flag]['today']);

            foreach($methodLists as $key => $obj){
                if(isset($tmp_statisticsInfos[$flag][$key])){
                    foreach ($obj['methods'] as $k => $name){
                        if(isset($tmp_statisticsInfos[$flag][$key][$k])){
                            $data['details'][$key]['label'] = $obj['label'];
                            $data['details'][$key]['info'][$k]['label'] = $name;
                            $data['details'][$key]['info'][$k]['total'] = isset($tmp_statisticsInfos[$flag][$key][$k]['total']) ? $tmp_statisticsInfos[$flag][$key][$k]['total'] : 0;
                            $data['details'][$key]['info'][$k]['yesterday'] = isset($tmp_statisticsInfos[$flag][$key][$k]['yesterday']) ? array_shift($tmp_statisticsInfos[$flag][$key][$k]['yesterday']) : 0;
                            $data['details'][$key]['info'][$k]['today'] = isset($tmp_statisticsInfos[$flag][$key][$k]['today']) ? array_shift($tmp_statisticsInfos[$flag][$key][$k]['today']) : 0;
                        }
                    }
                }
            }
        }else{
            $data = null;
        }

        // Vue 模式：返回结构化数据（与 legacy statistics.html 等价）——
        // 总计（总/今日/昨日调用次数）+ 按服务/方法拆分的明细（接口方法/总/今日/昨日）。
        if ($this->vue_mode) {
            if (empty($data)) {
                return array('available' => false);
            }

            $services = array();
            foreach ((array) $data['details'] as $serviceKey => $service) {
                $methods = array();
                foreach ((array) $service['info'] as $methodKey => $method) {
                    $methods[] = array(
                        'key'       => $methodKey,
                        'label'     => $method['label'],
                        'total'     => (int) $method['total'],
                        'today'     => (int) $method['today'],
                        'yesterday' => (int) $method['yesterday'],
                    );
                }
                $services[] = array(
                    'key'     => $serviceKey,
                    'label'   => $service['label'],
                    'methods' => $methods,
                );
            }

            return array(
                'available' => true,
                'total'     => (int) $data['total'],
                'today'     => (int) $data['today'],
                'yesterday' => (int) $data['yesterday'],
                'services'  => $services,
            );
        }

        $render->pagedata['data'] = $data;

        return $render->fetch('admin/setting/statistics.html');
    }
}
