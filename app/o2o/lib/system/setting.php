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

class o2o_system_setting{

    public $tab_name = '门店配置';

    public $tab_key = 'o2o';

    private $_setting_tab = array(
        array('name' => '门店配置', 'file_name' => 'admin/system/setting/tab_o2o.html', 'app' => 'o2o', 'order' => 99),
    );

    /**
     * 获取_setting_tab
     * @return mixed 返回结果
     */
    public function get_setting_tab()
    {
        return $this->_setting_tab;
    }

    /**
     * 获取View
     * @return mixed 返回结果
     */
    public function getView(){
        $settings = $this->all_settings();
        foreach($settings as $set){
            $key = str_replace('.','_',$set);
            $setData[$key] = app::get('o2o')->getConf($set);
        }

        $render = kernel::single('base_render');


        $render->pagedata['tab_key'] = $this->tab_key;
        $render->pagedata['setData'] = $setData;

        $html = $render->fetch('admin/system/setting.html','o2o');
        return $html;
    }

    /**
     * all_settings
     * @return mixed 返回值
     */
    public function all_settings(){
        $all_settings =array(
            'o2o.autostore.type',
            'o2o.baidumap.show',
            'o2o.baidumap.ak',
            'o2o.baidumap.sk',
            'o2o.delivery.confirm.code',
            'o2o.delivery.dly_overtime',
            'o2o.ctrl.supply.relation',
            'o2o.order.router_num',
        );
        return $all_settings;
    }

    /**
     * 保存Conf
     * @param mixed $data 数据
     * @return mixed 返回操作结果
     */
    public function saveConf($data){
        if (isset($data['o2o.order.router_num'])) {
            $routerNum = (int) $data['o2o.order.router_num'];
            $data['o2o.order.router_num'] = $routerNum > 0 ? $routerNum : 3;
        }
        foreach($data as $set=>$value){
            $curSet = app::get('o2o')->getConf($set);
            if(in_array($set,$this->all_settings()) && $curSet!=$data[$set]){
                app::get('o2o')->setConf($set,$data[$set]);
            }
        }
        return true;
    }

    /**
     * 获取_setting_data
     * @return mixed 返回结果
     */
    public function get_setting_data()
    {
        $setData = array();

        $all_settings = $this->all_settings();

        foreach($all_settings as $set){
            $key = str_replace('.','_',$set);
            $setData[$key] = app::get('o2o')->getConf($set);
        }

        $modes = o2o_autostore::getAutoStoreModes();
        $setData['autostore_modes'] = $modes;

        if (empty($setData['o2o_order_router_num']) || (int) $setData['o2o_order_router_num'] <= 0) {
            $setData['o2o_order_router_num'] = 3;
        }

        return $setData;
    }

}
