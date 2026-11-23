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

class ome_aftersale_request_bbc extends ome_aftersale_abstract{
    /**
     * __construct
     * @return mixed 返回值
     */
    public function __construct()
    {
        $this->_render = app::get('ome')->render();
    }
    static private $cs_status = array(
        1 => '不需客服介入',
        2 => '需要客服介入',
        3 => '客服已经介入',
        4 => '客服初审完成',
        5 => '客服主管复审失败',
        6 => '客服处理完成',    
    );
    static private $advance_status = array(
        0=>'未申请状态',
        1 => '退款先行垫付申请中 ',
        2 => '退款先行垫付，垫付完成 ',
        3 => '退款先行垫付，卖家拒绝收货',
        4 => ' 退款先行垫付，垫付关闭',
        5 => '退款先行垫付，垫付分账成功',    
    );
    static private $good_status = array(
        'BUYER_NOT_RECEIVED'=>'买家未收到货',
        'BUYER_RECEIVED'=>'买家已收到货',
    );
    
    /**
     * 退款申请详情扩展.
     * 
     *   
     * 
     * @author 
     */
    function refund_detail($refundinfo)
    {
        $apply_id = $refundinfo['apply_id'];
        $shop_id = $refundinfo['shop_id'];
        $apply_bn = $refundinfo['shop_id'];
        $oRefund_taobao = app::get('ome')->model ( 'refund_apply_taobao' );
        $refund_taobao = $oRefund_taobao->dump(array('apply_id'=>$apply_id,'shop_id'=>$shop_id));
        $refund_taobao['cs_status'] = self::$cs_status[$refund_taobao['cs_status']];
        $refund_taobao['advance_status'] = self::$advance_status[$refund_taobao['advance_status']];
        $refund_taobao['good_status'] = self::$good_status[$refund_taobao['good_status']];
        if ($refund_taobao) {
            $refundinfo = array_merge($refundinfo,$refund_taobao);
        }
       
        if ($refundinfo['message_text']) {
            $refundinfo['message_text'] = unserialize($refundinfo['message_text']);
        }
        if ($refundinfo['refuse_memo']) {
            $refundinfo['refuse_memo'] = unserialize($refundinfo['refuse_memo']);
        }
        if ($refundinfo['online_memo']) {
            $refundinfo['online_memo'] = unserialize($refundinfo['online_memo']);
        }
        $product_data = $refundinfo['product_data'];
        if ($product_data) {
            $product_data = unserialize($product_data);
        }
        $refundinfo['product_data'] = $product_data;
        $this->_render->pagedata['refundinfo'] = $refundinfo;
        unset($refundinfo);
        return '';//后续有需要再调整这里样式，目前用不到
    }

    function pre_save_refund($apply_id,$data)
    {
        $rs = array('rsp'=>'succ','msg'=>'成功','data'=>'');
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $refunddata = $oRefund_apply->refund_apply_detail($apply_id);
        if ($data['status'] == '3') {
            $result = kernel::single('ome_service_refund_apply')->update_status($refunddata,3,'sync');
            
            return $result;
            
        }
        
        
    }

    
    /**
     * 保存退款时按钮直接跳转还是dialog
     * @param   
     * @return  
     * @access  public
     * @author 
     */
    function refund_button($apply_id,$status)
    {
        $rs = array('rsp'=>'default','msg'=>'成功','data'=>'');
        if ($status == '3') {
            $rs = array('rsp'=>'show','msg'=>'','data'=>'index.php?app=ome&ctl=admin_refund_apply&act=upload_refuse_message&p[0]='.$apply_id.'&p[1]=bbc');
        }
        return $rs;
    }
}
?>