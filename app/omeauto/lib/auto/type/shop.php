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
 * 来源店铺
 */
class omeauto_auto_type_shop extends omeauto_auto_type_abstract implements omeauto_auto_type_interface {

    /**
     * 在显示前为模板做一些数据准备工作
     * 
     * @param object $tpl
     * @return void
     */
    public function _prepareUI(& $tpl, $val) {
        $filter = array('s_type'=>1);

        if(isset($val[1]) && $val[1]){
            $filter['org_id'] = $val[1];
        }

        #过滤o2o门店店铺
        $shop = array();
        $rows = app::get('ome')->model('shop')->getList("shop_id,name", $filter, 0, -1);
        if ($rows) {
            foreach ($rows as $v) {
                $shop[] = array('id' => $v['shop_id'], 'caption' => $v['name']);
            }
        }
        $tpl->pagedata['shops'] = $shop;
    }

    /**
     * 检查输入的参数
     * 
     * @param Array $params
     * @returm mixed
     */
    public function checkParams($params) {

        if (empty($params['shop']) && !is_array($params['shop'])) {

            return "你还没有选择相应的前端店铺\n\n请勾选以后再试！！";
        }

        return true;
    }

    /**
     * 生成规则字串
     * 
     * @param Array $params
     * @return String
     */
    public function roleToString($params) {

        $rows = app::get('ome')->model('shop')->getList('name', array('shop_id' => $params['shop']));

        $caption = '';
        foreach ($rows as $row) {

            $caption .= ", " . $row['name'];
        }
        $caption = sprintf('来自店铺 %s', preg_replace('/^,/is', '', $caption));

        $role = array('role' => 'shop', 'caption' => $caption, 'content' => $params['shop']);

        return json_encode($role);
    }

    /**
     * 检查订单数据是否符合要求
     * 
     * @param omeauto_auto_group_item $item
     * @return boolean
     */
    public function vaild($item) {

        if (!empty($this->content)) {

            foreach ($item->getOrders() as $order) {
                //检查店铺
                if (!in_array($order['shop_id'], $this->content)) {
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }

}