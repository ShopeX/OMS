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
 * @author sunjing@shopex.cn
 * @describe 
 */

class erpapi_shop_matrix_xiaohongshu_response_aftersalev2 extends erpapi_shop_response_aftersalev2
{
    protected function _formatAddParams($params)
    {
        $sdf = parent::_formatAddParams($params);
        
        return $sdf;
    }
    
    protected function _getAddType($sdf)
    {
        //需要退货才更新为售后单
        if ($sdf['has_good_return'] == 'true') {
            if (in_array($sdf['order']['ship_status'],array('0'))) {
                //有退货，未发货的,做退款
                return 'refund';
            } else{
                //有退货，已发货的,做售后
                return 'returnProduct';
            }
        }else{
            //无退货的，直接退款
            return 'refund';
        }
    }

    protected function _formatAddItemList($sdf, $convert=array())
    {
        if ($sdf['order']['tran_type'] == 'archive') {
            return $this->formatArchiveitemlist($sdf, $convert);
        }
        $convert = array(
            'sdf_field'=>'oid',
            'order_field'=>'oid',
            
        );
        
        return parent::_formatAddItemList($sdf, $convert);
    }

    /**
     * 归档订单：按 oid 关联 archive.order_objects，返回以 object bn 为键的明细
     */
    public function formatArchiveitemlist($sdf, $convert)
    {
        $refund_item_list = $sdf['refund_item_list']['return_item'];
        if (empty($refund_item_list)) {
            return array();
        }

        $archiveOrderObjectMdl = app::get('archive')->model('order_objects');
        $oids = array_column($refund_item_list, 'oid');
        $filter = array('oid' => $oids);
        if ($sdf['order']['order_id']) {
            $filter['order_id'] = $sdf['order']['order_id'];
        }
        $object = $archiveOrderObjectMdl->getList('order_id,oid,bn,quantity,obj_id', $filter);
        $object = array_column($object, null, 'oid');

        $arrItem = array();
        foreach ($refund_item_list as $item) {
            if ($object[$item['oid']]['bn']) {
                $item['bn'] = $object[$item['oid']]['bn'];
            }
            if ($arrItem[$item['bn']]) {
                $arrItem[$item['bn']]['num'] += $item['num'];
            } else {
                $arrItem[$item['bn']] = $item;
            }
            if ($object[$item['oid']]['obj_id']) {
                $arrItem[$item['bn']]['obj_id'][] = $object[$item['oid']]['obj_id'];
            }
        }
        return $arrItem;
    }

    protected function _returnProductAddSdf($sdf)
    {
        if ($sdf['order']['tran_type'] == 'archive') {
            $sdf['archive'] = '1';
        }
        return parent::_returnProductAddSdf($sdf);
    }
}