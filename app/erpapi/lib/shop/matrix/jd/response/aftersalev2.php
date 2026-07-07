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
 * @author ykm 2018/12/7
 * @describe 京东供应商平台
 */

class erpapi_shop_matrix_jd_response_aftersalev2 extends erpapi_shop_response_aftersalev2 {

    public function add($params) {
        if ($params['refund_type'] == 'reship' && $params['refund_id']) {
            $returnModel = app::get('ome')->model('return_product');
            $count = $returnModel->count(array(
                'shop_id'   => $this->__channelObj->channel['shop_id'],
                'return_bn' => $params['refund_id'],
                'source'    => 'matrix',
            ));

            if ($count == 0) {
                $returnRsp                = $params;
                $returnRsp['status']      = 'WAIT_BUYER_RETURN_GOODS';
                $returnRsp['refund_type'] = 'return';
                kernel::single('ome_return')->get_return_log($returnRsp, $this->__channelObj->channel['shop_id'], $msg);
            }
        }

        return parent::add($params);
    }

    protected function _formatAddParams($params) {
        $sdf = parent::_formatAddParams($params);
        // 原逻辑：所有售后单统一为退款申请(apply)，会覆盖平台 refund_type=return，导致无法走退货售后
        // $sdf['refund_type'] = 'apply';
        $refundType = isset($sdf['refund_type']) ? (string) $sdf['refund_type'] : '';
        if ($refundType === '' || $refundType === 'refund') {
            $sdf['refund_type'] = 'apply';
        }
        return $sdf;
    }

    protected function _getAddType($sdf) {
        // return 'refund';#只有售前退款单

        // 参考360buy
        if(in_array($sdf['refund_type'],array('refund','apply'))) { #退款
            return 'refund';
        } elseif ($sdf['refund_type'] == 'return') {
            return 'returnProduct';
        } elseif ($sdf['refund_type'] == 'reship') {
            if ($sdf['status'] == 'confirm_failed'){
                return 'returnProduct';
            }else{
               return 'reship'; 
            }
        }
    }

    protected function _formatAddItemList($sdf, $convert=array()) {
        $convert = array(
            'sdf_field'=>'oid',
            'order_field'=>'oid',
            'default_field'=>'outer_id'
        );
        return parent::_formatAddItemList($sdf, $convert);
    }

}