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

class ome_aftersale_service{
    
    const _APP_NAME = 'ome';
    
    public function __construct(){
        $this->_router = kernel::single('ome_aftersale_request');
    }
    
    //matrix售后编辑前
    function pre_return_product_edit($returninfo){
        $shop_id = $returninfo['shop_id'];
        $return_id = $returninfo['return_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($returninfo['source'] == 'matrix' && $shop && $shop['node_id']) {
           $plugin_html = $this->_router->setShopId($shop_id)->pre_return_product_edit($returninfo);
            if ($plugin_html) {
                return $plugin_html;
            }
        }        
    }
    
    //matrix售后新增或者编辑后
    function return_product_edit_after($data){
        $return_id = $data['return_id'];
        $oReturn_product = app::get('ome')->model ( 'return_product' );
        $return_product = $oReturn_product->dump($return_id,'shop_id,source');
        $shop_id = $return_product['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        $source = $return_product['source'];
        $data['shop_id'] = $shop_id;
        if ($source == 'matrix' && $shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->return_product_edit_after($data);
        }
     }

     //matrix获取售后服务详情
    function return_product_detail($data){
        $return_id = $data['return_id'];
        $oReturn_product = app::get('ome')->model ( 'return_product' );
        $return_product = $oReturn_product->dump($return_id,'shop_id,source');
        $shop_id = $return_product['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($return_product['source'] == 'matrix' && $shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->return_product_detail($data);
            if ($result) {
                return $result;
            }
        }  
    }

    //matrix更新售后申请状态前
    function pre_save_return($data){
        $return_id = $data['return_id'];
        $oProduct = app::get('ome')->model ( 'return_product' );
        $oPro_detail  = $oProduct->dump ( $return_id, 'shop_id,source,return_type' );
        $shop_id = $oPro_detail['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($oPro_detail['source'] == 'matrix' && $shop && $shop['node_id']) {
            $data['return_type'] = $oPro_detail['return_type'];
            $result = $this->_router->setShopId($shop_id)->pre_save_return($data);
            return $result;
        }
    }
    
    //matrix更新售后申请状态后
    function after_save_return($data){
        $apply_id = $data['apply_id'];
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $refunddata = $oRefund_apply->refund_apply_detail($apply_id);
        $shop_id = $refunddata['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($refunddata['source'] == 'matrix' && $shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->after_save_return($data);
        }
    }

    //matrix退款申请单详情
    function refund_detail($data){
        $shop_id = $data['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        $result = $this->_router->setShopId($shop_id)->refund_detail($data);
        if (is_array($result) && $shop && $shop['node_id'] && $result['rsp']!='fail') {
            return $result;
        }
    }

    //matrix退款申请单详情保存前
    function pre_save_refund($apply_id,$data){
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $refunddata = $oRefund_apply->refund_apply_detail($apply_id);
        $shop_id = $refunddata['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($refunddata['source'] == 'matrix' && $shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->pre_save_refund($apply_id,$data);
            return $result;
        }
    }

    //matrix退款申请单详情保存后
    function after_save_refund($data){
        $apply_id = $data['apply_id'];
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $refunddata = $oRefund_apply->refund_apply_detail($apply_id);
        $shop_id = $refunddata['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($refunddata['source'] == 'matrix' && $shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->after_save_refund($data);
        }
    }

    //matrix售后申请保存
    function save_return($status,$data){
        $return_id = $data['return_id'];
        $oProduct = app::get('ome')->model ( 'return_product' );
        $oPro_detail  = $oProduct->dump ( $return_id, 'shop_id,source' );
        $shop_id = $oPro_detail['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($oPro_detail['source']== 'matrix' && $shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->save_return($status,$data);
            return $result;
        }
    }

    //matrix售后api
    function return_api($data){
        $shop_id = $data['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->return_api();
            return $result;
        }
    }

    //matrix退款按钮信息
    function refund_button($apply_id,$status){
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $refund_apply = $oRefund_apply->dump($apply_id,'shop_id,source');
        $shop_id = $refund_apply['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($refund_apply['source']=='matrix' && $shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->refund_button($apply_id,$status);
            return $result;
        }
    }
    
    //matrix售后按钮信息
    function return_button($return_id,$status){
        $oReturn = app::get('ome')->model('return_product');
        $return = $oReturn->dump($return_id,'shop_id,source');
        $shop_id = $return['shop_id'];
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        if ($return['source']=='matrix' && $shop && $shop['node_id']) {
            $result = $this->_router->setShopId($shop_id)->return_button($return_id,$status);
            return $result;
        }
    }

    //matrix质检编辑
    function reship_edit($returninfo){
        $shop_id = $returninfo['shop_id'];
        $result = $this->_router->setShopId($shop_id)->reship_edit($returninfo);
        $shopModel = app::get('ome')->model('shop');
        $shop = $shopModel->dump($shop_id);
        $result_ok = $result && (is_array($result) ? $result['rsp'] != 'fail' : true);
        if ($result_ok && $shop && $shop['node_id']) {
            return $result;
        }
    }
    
} 

?>
