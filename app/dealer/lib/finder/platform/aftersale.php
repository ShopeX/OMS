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

class dealer_finder_platform_aftersale {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = "plat_aftersale_id";

    private static $sync_status_values = array(
        '0' => '未转换',
        '1' => '转换成功',
        '2' => '转换失败',
        '3' => '无需转换',
    );

    function detail_basic($plat_aftersale_id){

        $render = app::get('dealer')->render();


        $plat_aftersaleMdl = app::get('dealer')->model('platform_aftersale');
        $plat_aftersale_itemsMdl = app::get('dealer')->model('platform_aftersale_items');


        $platform_aftersale = $plat_aftersaleMdl->db_dump(array('plat_aftersale_id'=>$plat_aftersale_id),'*');

        $platform_aftersale_objectsMdl = app::get('dealer')->model('platform_aftersale_objects');

        $objects = $platform_aftersale_objectsMdl->getlist('*',array('plat_aftersale_id'=>$plat_aftersale_id));


        foreach($objects as $ok=>$ov){

            $items = $plat_aftersale_itemsMdl->getlist('*',array('plat_aftersale_id'=>$plat_aftersale_id,'plat_aftersale_obj_id'=>$ov['plat_aftersale_obj_id']));

            
            $sync_status_values = self::$sync_status_values;

            foreach($items as $k=>$v){
                $items[$k]['sync_status_value'] = $sync_status_values[$v['sync_status']];
            }

            $objects[$ok]['items'] = $items;
        }

        $render->pagedata['platform_aftersale'] = $platform_aftersale;

        $render->pagedata['objects'] = $objects;

        $logMdl = app::get('ome')->model('operation_log');
        $logList = $logMdl->read_log(array('obj_id'=>$plat_aftersale_id,'obj_type'=>'platform_aftersale@dealer'), 0, -1);

        foreach($logList as $k=>$v){
            $logList[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }


        $render->pagedata['logList'] = $logList;


        return $render->fetch('admin/platform/aftersale_items.html');

    }
}