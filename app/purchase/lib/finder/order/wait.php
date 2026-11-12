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
/**
 * Created by PhpStorm.
 * User: yaokangming
 * Date: 2019/4/24
 * Time: 9:43
 */
class purchase_finder_order_wait
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = '';
    public $detail_detail = '详情';
    public $detail_detail_order = '10';
    /**
     * detail_detail
     * @param mixed $id ID
     * @return mixed 返回值
     */

    public function detail_detail($id){
        $render = app::get('purchase')->render();
        $render->pagedata['detail'] = app::get('purchase')->model('order_wait_items')->getList('*', array('ow_id'=>$id));;
        return $render->fetch("admin/order/wait_items.html");
    }

    public $detail_log = '日志';
    public $detail_log_order = '20';
    /**
     * detail_log
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_log($id){
        $render = app::get('purchase')->render();
        $logObj = app::get('ome')->model('operation_log');
        $logs = $logObj->read_log(array('obj_id'=>$id,'obj_type'=>'order_wait@purchase'),0,-1);

        $render->pagedata['logs'] = $logs;
        if ($this->vue_mode) { return []; }
        return $render->fetch("admin/order/log.html");
    }
}