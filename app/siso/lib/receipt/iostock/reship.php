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

class siso_receipt_iostock_reship extends siso_receipt_iostock_abstract implements siso_receipt_iostock_interface{
    /**
     *
     * 出入库类型id
     * @var int
     */
    public $_typeId = 30;

    /**
     *
     * 出库/入库动作
     * @var int
     */
    protected $_io_type = 1;
    
    public function create($params, &$data, &$msg = null)
    {
        $reship_id = $params['reship_id'];
        $reship = app::get('ome')->model('reship')->db_dump(['reship_id'=>$reship_id], 'shop_id');
        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$reship['shop_id']], 'delivery_mode,shop_bn,name');

        $params['shop_bn'] = $shop['shop_bn'];
        $params['shop_name'] = $shop['name'];

        if($shop['delivery_mode'] == 'jingxiao') {
            return true;
        }
        return parent::create($params, $data, $msg);
    }

    /**
     *
     * 退货入库
     * @param array $data
     */
    function get_io_data($data){
        $oReship = app::get('ome')->model('reship');
        $oReship_items = app::get('ome')->model('reship_items');
        $reship_id = $data['reship_id'];
        $reship = $oReship->dump($reship_id,'reship_bn,t_end,order_id');
        $reship_items = $data['items'];

        if (!$reship_items){
            $reship_items = $oReship_items->getlist('*',array('reship_id'=>$reship_id,'return_type'=>array('return','refuse'),'normal_num|than'=>0),0,-1);
        }
        $operator       = kernel::single('desktop_user')->get_name();
        $operator = $operator=='' ? 'system' : $operator;
        $iostock_types = kernel::single('siso_receipt_iostock')->get_iostock_types();
        $bill_type = $iostock_types[$this->_typeId]['info'];
        $iostock_data = array();
        if ($reship_items) {
            foreach($reship_items as $k=>$v){

                $nums = $this->_typeId==50 ? $v['defective_num'] : $v['normal_num'];
                $iostock_data[] = array(
                    'branch_id' => $data['branch_id'],
                    'original_bn' => $reship['reship_bn'],
                    'original_id' => $reship_id,
                    'original_item_id' => $v['item_id'],
                    'supplier_id' => 0,
                    'supplier_name' => '',
                    'bn' => $v['bn'],
                    'iostock_price' => $v['price']!='' ? $v['price']: '0',
                    'nums' => $nums,
                    'cost_tax' => 0,
                    'oper' => $operator,
                    'create_time' => $reship['t_end'],
                    'operator' => $operator,
                   'order_id'=>$reship['order_id'],
                    'memo'=>$data['memo'],
                    'bill_type'=>$bill_type,
                    'business_bn'=>$reship['reship_bn'],
                    'shop_bn'   =>$data['shop_bn'],
                    'shop_name'=>$data['shop_name'],
                );
            }
            $this->dealBatch($iostock_data, ['reship'], '+', true);
        }
        
        
        return $iostock_data;
    }
}
