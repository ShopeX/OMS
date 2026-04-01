<?php

/**
 * 售后接口数据转换
 * Class erpapi_shop_matrix_b2b_response_aftersalev2
 */
class erpapi_shop_matrix_b2b_response_aftersalev2 extends erpapi_shop_response_aftersalev2
{
    var $status = array(
        'apply'  => array(
            'APPLY'  => '0',
            'VERIFY' => '1',
            'SUCC'   => '2',
            'REFUND' => '4',
            'FAIL'   => '3',
        ),
        'refund' => array(
            'SUCC'     => 'succ',
            'FAILED'   => 'failed',
            'CANCEL'   => 'cancel',
            'ERROR'    => 'error',
            'INVALID'  => 'invalid',
            'PROGRESS' => 'progress',
            'TIMEOUT'  => 'timeout',
            'READY'    => 'ready',
        )
    
    );
    
    static public $aftersale_refund = [
        '0'    => 'WAIT_SELLER_AGREE',
        '1'    => 'WAIT_SELLER_AGREE',
        '2'    => 'WAIT_BUYER_RETURN_GOODS',//卖家已经同意退款
        '3'    => 'CLOSED',//卖家拒绝
        '4'    => 'SUCCESS',//退款成功
        'succ' => 'WAIT_SELLER_AGREE',
    ];
    
    static public $aftersale_return = [
        '1' => 'WAIT_SELLER_AGREE',//待处理
        '2' => 'WAIT_SELLER_AGREE',//待处理
        '3' => 'WAIT_BUYER_RETURN_GOODS',//待退货
        '4' => 'SUCCESS',//退款成功
        '5' => 'CLOSED',//商家未收货拒绝退款或退货取消
        '9' => 'CLOSED',//拒绝退款
    ];
    
    /**
     * 售后退货 store.trade.aftersale.add
     * 退款 store.trade.refund.add
     * 外部直连是分别请求的，oms在mapping里转换成 shop.aftersalev2.add
     * 转接aftersale的请求至aftersalev2
     * 有aftersale_id为退货
     * 有refund_id为退款
     * @param $params
     * @return array
     * @author db
     * @date 2023-08-25 2:02 下午
     */
    protected function _formatAddParams($params)
    {
        if (isset($params['method']) && $params['method'] == 'store.trade.aftersale.add') {
            return $this->_formatAddParamsReturn($params);
        } else {
            return $this->_formatAddParamsRefund($params);
        }
    }
    
    public function _formatAddParamsReturn($params)
    {
        $sdf = parent::_formatAddParams($params);
        // 售后物流
        if (is_string($params['logistics_info'])) {
            $logistics_info = json_decode($params['logistics_info'], true);
            if ($logistics_info) {
                $params['logistics_company'] = $logistics_info['logistics_company'];
                $params['logistics_no']      = $logistics_info['logistics_no'];
            }
        }
        
        if($params['original_order_bn']){
            $sdf['change_order_flag'] = true;
         
            $params['oid'] = '';
            $sdf['memo'] = '换货订单转换生成,原订单号:'.$params['tid'];
            $this->_tranChangeItems($params);
            $refund_item_list= $params['refund_item_list'];
        }else{

            $refund_item_list = [];
            $return_item      = [];
            $aftersale_items  = is_string($params['aftersale_items']) ? json_decode($params['aftersale_items'], true) : $params['aftersale_items'];
            foreach ($aftersale_items as $key => $val) {
                $return_item[] = array(
                    'title'    => $val['sku_name'],
                    'price'    => $val['price'],
                    'oid'      => $val['oid'],
                    'modified' => time(),
                    'num'      => $val['number'],
                    'item_id'  => $val['sku_bn'],
                    'outer_id' => '',
                    'amount' => $val['amount'],
                    'sku_uuid'=>$val['sku_uuid'],
                );
            }

            if ($return_item) {
                $refund_item_list['return_item'] = $return_item;
            }

        }
        
        
        $d1mSdf = array(
            'order_bn'          => $params['tid'],
            'refund_bn'         => $params['aftersale_id'],
            'status'            => self::$aftersale_return[$params['status']],//需要确认
            'source_status'     => $params['status'],
            'refund_fee'        => $params['refund_money'] ? sprintf('%.2f', $params['refund_money']) : 0,//申请退款金额
            'reason'            => $params['memo'],//退货原因
            'modified'          => time(),
            'created'           => $params['created'] ? kernel::single('ome_func')->date2time($params['created']) : time(),//申请时间
            'buyer_nick'        => $params['buyer_name'],//会员名
            'desc'              => $params['messager'],//申请售后留言
            'logistics_company' => $params['logistics_company'] ? $params['logistics_company'] : '',
            'logistics_no'      => $params['logistics_no'] ? $params['logistics_no'] : '',
            'has_good_return'   => 'true',
            'refund_item_list'  => $refund_item_list ? $refund_item_list : '',
            'org_id'            => $this->__channelObj->channel['org_id'],
            'oid'               => isset($refund_item_list['return_item']) ? $refund_item_list['return_item'][0]['oid'] : '', //未发货退款明细删除用
            'original_order_bn' => $params['original_order_bn'],
            'change_order_id'   => isset($params['change_order_id']) ? $params['change_order_id'] : '',
        );

         // 申请退运费金额
        $sdf['refund_shipping_fee'] = $params['refund_shipping_fee'] ?:0;
        return array_merge($sdf, $d1mSdf);
    }
    
    public function _formatAddParamsRefund($params)
    {
        $sdf = parent::_formatAddParams($params);
        // 兼容套娃换货退款, 获取实际退款订单号
        $params['tid'] = $this->_getActualTid($params);
        
        $refund_item_list = [];
        if ($params['items']) {
            $params['items'] = is_string($params['items']) ? json_decode($params['items'], true) : $params['items'];
            $order           = app::get('ome')->model('orders')->db_dump(['order_bn' => $params['tid']], 'order_id');
            $objList         = app::get('ome')->model('order_objects')->getList('*', ['order_id' => $order['order_id']]);
            $order_items     = app::get('ome')->model('order_items')->getList('*', ['order_id' => $order['order_id']]);
            
            if ($order_items) {
                $tmp_items = array();
                foreach ($order_items as $i_key => $i_val) {
                    $tmp_items[$i_val['obj_id']][] = $i_val;
                }
                $order_items = NULL;
            }
            
            if ($objList) {
                foreach ($objList as $o_key => &$o_val) {
                    $o_val['order_items'] = $tmp_items[$o_val['obj_id']];
                }
            }
            $objList = array_column($objList, null, 'oid');
            
            $return_item = [];
            foreach ($params['items'] as $key => $val) {
                if ($objList[$val['oid']]) {
                    $obj           = $objList[$val['oid']];
                    $item          = [
                        'title'    => $val['sku_name'],
                        'price'    => $val['price'] ?? $obj['sale_price'],
                        'oid'      => $val['oid'],
                        'modified' => time(),
                        'num'      => $val['number'] ?? $obj['quantity'],
                        'item_id'  => $val['sku_bn'],
                        'outer_id' => $val['oid'],
                        'amount'   => $val['amount'],
                        'sku_uuid' =>$val['sku_uuid'],
                    ];
                    $return_item[] = $item;
                }
            }
            $refund_item_list['return_item'] = $return_item;
        }
        
        if (!$refund_item_list) {
            $this->__apilog['result']['msg'] = '缺少退款商品数据，不接受';
            return [];
        }
        
        $status  = $this->status[$params['refund_type']][$params['status']];
        $d1mSdf  = array(
            'order_bn'         => $params['tid'],
            'refund_bn'        => $params['refund_id'],//退款单ID
            'status'           => self::$aftersale_refund[$status],//退款不同
            'source_status'    => self::$aftersale_refund[$status],
            'refund_fee'       => $params['refund_fee'] ? sprintf('%.2f', $params['refund_fee']) : 0,
            'reason'           => $params['memo'],//退款原因
            'modified'         => time(),
            'created'          => $params['created'] ? kernel::single('ome_func')->date2time($params['created']) : time(),//申请时间
            'buyer_nick'       => $params['buyer_name'],//会员名
            'desc'             => $params['messager'],//申请售后留言
            'refund_item_list' => $refund_item_list ? $refund_item_list : '',
            'org_id'           => $this->__channelObj->channel['org_id'],
        );
        
        return array_merge($sdf, $d1mSdf);
    }
    
    protected function _formatAddItemList($sdf, $convert = array())
    {
        return parent::_formatAddItemList($sdf, $convert);
    }
    
    protected function _getAddType($sdf)
    {
        return parent::_getAddType($sdf);
    }
    
    protected function _getActualTid($params)
    {
        $refundApplyMdl = app::get('ome')->model('refund_apply');
        // 查询退款申请表是否有此单
        $filter = [
            'refund_apply_bn' => $params['refund_id'],
            'shop_id' => $this->__channelObj->channel['shop_id']
        ];

        $refundApply = $refundApplyMdl->dump($filter,'apply_id,refund_apply_bn,order_id,archive');
        
        // 没有则原样返回
        if(!$refundApply){
            return $params['tid'];
        }
        
        // 查订单号
        if($refundApply['archive']){
            $orderMdl = app::get('archive')->model('orders');
        }else{
            $orderMdl = app::get('ome')->model('orders');
        }
       
        $order = $orderMdl->dump($refundApply['order_id'],'order_bn');
        

        // 没有则原样返回
        if(!$order){
            return $params['tid'];
        }
        
        return $order['order_bn'];
    }
    
    protected function _formatLogisticsUpdate($params)
    {
        if (is_string($params['logistics_info'])) {
            $logistics_info = json_decode($params['logistics_info'], true);
            $process_data = array();
            $process_data['shipcompany'] = $logistics_info['logistics_company'];
            $process_data['logino'] = $logistics_info['logistics_no'];
        }
        $sdf = array(
            'order_bn'     => $params['tid'],
            'return_bn'    => $params['aftersale_id'],
            'process_data' => $process_data
        );
        return $sdf;
    }
    
    protected function _tranChangeItems(&$sdf){
        // 换货商品处理逻辑
        if ($sdf['exchange_bn']) {
            $change_items[] = array(
                'bn'    => $sdf['exchange_bn'],
                'num'   => $sdf['num'],
                'price' => floatval($sdf['price']),
            );
            $sdf['change_items'] = $change_items;
        }
    }
} 