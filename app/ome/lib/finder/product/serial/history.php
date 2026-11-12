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

class ome_finder_product_serial_history{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $detail_basic = '基本信息';
    
    function detail_basic($item_id){
        $render = app::get('ome')->render();
        $mdl_serial_history = app::get('ome')->model('product_serial_history');
        $rs_serial_history = $mdl_serial_history->dump($item_id);
        //获取收货人信息
        if($rs_serial_history["bill_type"] == "1"){ //发货单
            $mdl_ome_delivery = app::get('ome')->model('delivery');
            $delivery = $mdl_ome_delivery->dump(array('delivery_bn'=>$rs_serial_history['bill_no']),'ship_name,ship_area,ship_addr,ship_tel,ship_mobile');
            if(!empty($delivery)){
                $ship_area = explode(":", $delivery["consignee"]["area"]);
                $render->pagedata["ship_info"] = array(
                    "ship_name" => $delivery["consignee"]["name"],
                    "ship_area" => str_replace("/", "", $ship_area[1]),
                    "ship_addr" => $delivery["consignee"]["addr"],
                    "ship_tel" => $delivery["consignee"]["telephone"],
                    "ship_mobile" => $delivery["consignee"]["mobile"],
                );
            }
        }elseif($rs_serial_history["bill_type"] == "2"){ //退换货单
            $mdl_ome_reship = app::get('ome')->model('reship');
            $rs_reship = $mdl_ome_reship->dump(array("reship_bn"=>$rs_serial_history['bill_no']),'ship_name,ship_area,ship_addr,ship_tel,ship_mobile');
            if(!empty($rs_reship)){
                $ship_area = explode(":", $rs_reship["ship_area"]);
                $render->pagedata["ship_info"] = array(
                    "ship_name" => $rs_reship["ship_name"],
                    "ship_area" => str_replace("/", "", $ship_area[1]),
                    "ship_addr" => $rs_reship["ship_addr"],
                    "ship_tel" => $rs_reship["ship_tel"],
                    "ship_mobile" => $rs_reship["ship_mobile"],
                );
            }
        }
        return $render->fetch('admin/product/serial/history/detail.html');
    }
    var $addon_cols = "bill_type,bill_no";
    var $column_order_bn='订单号';
    var $column_order_bn_width = "100";

    /**
     * column_order_bn
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_order_bn($row){
        $bill_type  = $row[$this->col_prefix . 'bill_type'];
        $bill_no    = $row['bill_no'];

        if ($bill_type=='1'){
            return app::get('ome')->model('product_serial_history')->get_ordersBydeliverybn($bill_no);
        }else if($bill_type=='2'){

        }

    }
}