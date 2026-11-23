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

//退货差异

class console_reship_diff {

    public function doCheck($reship) {
        $reship_id = $reship['reship_id'];
        $rsTran = kernel::database()->beginTransaction();
        $Oreship = app::get('ome')->model('reship');
        if (!$Oreship->finish_aftersale($reship_id)) {
            kernel::database()->rollBack();
            return [false, ['msg'=>'入库单完成失败']];
        }
        $result = kernel::single('console_reship')->siso_iostockReship($reship_id);
        if (!$result) {
            kernel::database()->rollBack();
            return [false, ['msg'=>'入库单入库失败']];
        }
        list($rs, $rsData) = $this->reshipStockout($reship_id);
        if(!$rs) {
            kernel::database()->rollBack();
            return [false, $rsData];
        }
        kernel::database()->commit($rsTran);
        return [true, ['msg'=>'操作成功']];
    }

    public function reshipStockout($reship_id) {
        $oReship = app::get('ome')->model('reship');
        $reship = $oReship->dump(array('reship_id'=>$reship_id),'reship_id,reship_bn,branch_id,return_type');
        $oReship_item = app::get('ome')->model('reship_items');
        $normal_reship_item = $oReship_item->getList('product_id,bn,product_name,normal_num as number',array('reship_id'=>$reship_id,'normal_num|than'=>0));
        $defective_reship_item = $oReship_item->getList('product_id,bn,product_name,defective_num as number',array('reship_id'=>$reship_id,'defective_num|than'=>0));
        if (count($normal_reship_item)>0){
            $main = $reship;
            $main['memo'] = '差异退货入库，产生出库单';
            $main['items'] = $normal_reship_item;
            list($rs, $rsData) = $this->_dealOutItems($main);
            if(!$rs) {
                return [$rs, $rsData];
            }
        }

        if (count($defective_reship_item)>0) {
            $damaged = kernel::single('console_iostockdata')->getDamagedbranch($reship['branch_id']);
            $main = $reship;
            $main['branch_id'] = $damaged['branch_id'];
            $main['memo'] = '差异退货入库，产生出库单';
            $main['items'] = $defective_reship_item;
            list($rs, $rsData) = $this->_dealOutItems($main);
            if(!$rs) {
                return [$rs, $rsData];
            }
        }
        return [true, ['msg'=>'操作完成']];
    }

    private function _dealOutItems($main) {
        $products = [];
        foreach ($main['items'] as $v) {
                $products[$v['product_id']] = [
                    'bn' => $v['bn'],
                    'name' => $v['product_name'],
                    'nums' => $v['number'],
                    'unit' => '',
                    'price' => 0,
                ];
        }
        $op = kernel::single('ome_func')->getDesktopUser();
        $data = array(
            'iostockorder_name' => date('Ymd') . '出库单',
            'supplier'          => '',
            'supplier_id'       => 0,
            'branch'            => $main['branch_id'],
            'extrabranch_id'    => 0,
            'type_id'           => ome_iostock::DIRECT_LIBRARAY,
            'iso_price'         => 0,
            'memo'              => $main['memo'],
            'operator'          => $op['op_name'],
            'original_bn'       => $main['reship_bn'],
            'original_id'       => $main['reship_id'],
            'products'          => $products,
            'appropriation_no'  => '',
            'bill_type'         => 'oms_reshipdiffout',
            'confirm'           => 'Y',
        );

        $iostockorder_instance = kernel::single('console_iostockorder');
        $rs = $iostockorder_instance->save_iostockorder($data, $msg);
        return [$rs, ['msg'=>$msg]];
    }
}