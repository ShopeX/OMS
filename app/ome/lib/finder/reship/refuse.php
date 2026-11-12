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

class ome_finder_reship_refuse{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "退货单详情";
    var $addon_cols = 'need_sv,order_id';

    function detail_basic($reship_id)
    {
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');
        
        $oDesktop = app::get('desktop')->model('users');
        $render = app::get('ome')->render();
        $oReship = app::get('ome')->model('reship');
        $detail = $oReship->getCheckinfo($reship_id);
        $desktop_detail = $oDesktop->dump(array('user_id'=>$detail['op_id']), 'name');
        $detail['op_name'] = $desktop_detail['name'];
        $cols = $oReship->_columns();

        $detail['is_check'] = $cols['is_check']['type'][$detail['is_check']];
        //$Oreason = $oReship->dump(array('reship_id'=>$reship_id),'reason');
        $reason = unserialize($detail['reason']);

        $detail['check_memo'] = $reason['check'];

        $render->pagedata['detail'] = $detail;
        
        $reship_item = $oReship->getItemList($reship_id);
        foreach ($reship_item as $key => $value) {
            $pos_string ='';
            $posLists = $libBranchProductPos->get_pos($value['product_id'], $value['branch_id']);
            if(count($posLists) > 0){
                foreach($posLists as $pos){
                    $pos_string .= $pos['store_position'].",";
                }
                $reship_item[$key]['store_position'] = substr($pos_string,0,strlen($pos_string)-1);
            }
            $recover['return'][] = $reship_item[$key];
        }
        $render->pagedata['items'] = $recover;
        return $render->fetch('admin/reship/refuse.html');
    }

    /**
     * @description
     * @access public
     * @param void
     * @return void
     */
    public function row_style($row)
    {
        $s = kernel::single('ome_reship')->is_precheck_reship($row['is_check'],$row[$this->col_prefix.'need_sv']);
        return $s ? 'highlight-row' : '';
    }

    var $column_order_id='订单号';
    var $column_order_id_width='100';
    function column_order_id($row)
    {
        $archive = $row[$this->col_prefix . 'archive'];
        $source = $row[$this->col_prefix . 'source'];
        $order_id = $row[$this->col_prefix . 'order_id'];
        $filter = array('order_id'=>$order_id);
        if ($archive == '1' || in_array($source,array('archive'))) {
         
            $archive_ordObj = kernel::single('archive_interface_orders');
            $order = $archive_ordObj->getOrders($filter,'order_bn');
        }else{
            $orderObj = app::get('ome')->model('orders');
            $order = $orderObj->dump($filter,'order_bn');
        }
        return $order['order_bn'];
    }

}

?>