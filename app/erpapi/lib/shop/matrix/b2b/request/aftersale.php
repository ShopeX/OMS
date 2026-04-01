<?php

/**
 * @desc
 * @author:
 * @since:
 */
class erpapi_shop_matrix_b2b_request_aftersale extends erpapi_shop_request_aftersale
{

    public $status = array(
        'succ' => 'SUCC',
        'failed' => 'FAILED',
        'cancel' => 'CANCEL',
        'lost' => 'LOST',
        'progress' => 'PROGRESS',
        'timeout' => 'TIMEOUT',
        'ready' => 'READY',
    );

    /**
     * 对应第三方B2B接口文档, b2b.reship.create 退货入库完成 接口
     * @param $reship 退货单请求参数
     * @return string[]|void
     * 因对接方不关心退货入库状态,不再实现该接口
     * @used-by ome_service_reship::reship()
     */
    public function addReship($reship)
    {

    }


    /**
     * 对应第三方B2B接口文档, b2b.aftersale.update 更新售后申请的状态 接口
     * @param $reship 退货单请求参数
     * @return string[]|void
     */
    public function updateAfterSaleStatus($aftersale, $status = '', $mod = 'async')
    {
        // 如果缺失status,则直接返回(说明由ome_service_aftersale::update_status调用)
        if (!$status) {
            return true;
        }
      
        // 如果来源是本地,且状态是接受申请,则转发至售后单添加
        if ($aftersale['source'] == 'local' && $status == '3') {
            return $this->addAfterSale($aftersale, 'audit');
        }
        
        $rs = parent::updateAfterSaleStatus($aftersale, $status, $mod);
        return $rs;
    }

    protected function __formatAfterSaleParams($aftersale, $status)
    {
        
        if (in_array($status,array('13','15','16'))){
            return $this->_formatChangeParams($aftersale,$status);
        }

        $params = array(
            'return_bn' => $aftersale['return_bn'],
            'order_bn' => $aftersale['order']['order_bn'], //todo 接口文档能否调整为tid,与refund接口一致, 父类也直接赋值tid
            'status' => $status
        );

        if ($params['status'] == '5') {
            $params['addon'] = isset($aftersale['content']) ? $aftersale['content'] : '中台拒绝';
        }

        return $params;
    }


    /**
     * 获取售后请求api
     * @param $status
     * @param null $returnInfo
     * @return string
     */
    protected function __afterSaleApi($status, $returnInfo = null)
    {
        switch ($status) {
            case '3':       // 同意申请
                $api_method = SHOP_UPDATE_RESHIP_STATUS_RPC;
                break;
            case '5':       // 拒绝申请
                $api_method = SHOP_UPDATE_RESHIP_STATUS_RPC;
                break;
            case '10':      // 入库失败
                $api_method = SHOP_UPDATE_RESHIP_STATUS_RPC;
                break;
            case '13':
                
            case '15':
            case '16':
                $api_method= SHOP_EXCHANGE_NOTIFY;
            break;
            default :
                $api_method = SHOP_UPDATE_RESHIP_STATUS_RPC;
                break;
        }

        return $api_method;
    }


    /**
     * 对应第三方B2B接口文档, b2b.aftersale.create 售后申请 接口
     * @param $returninfo退货单请求参数
     * @param $source 来源, create 新建or编辑,audit 审核
     * @return string[]|void
     * @used-by ome_service_aftersale::add_aftersale
     */
    public function addAfterSale($returninfo, $source = 'create')
    {
        // 仅审核时触发,才发起推送
        if ($source != 'audit') {
            return true;
        }
        // 订单号处理,兼容套娃换货退款情况
        $orderBn = $this->_getOriginalOrderBn($returninfo['order'], $returninfo);

        $params = [
            'aftersale_id' => $returninfo['return_bn'],   // 售后申请单ID
            'tid' => $orderBn,   // 交易ID
            'refund_money' => $returninfo['money'],   // 申请退款总金额,手动单仅money字段存在金额
            'title' => $returninfo['title'],   // 售后申请标题
            'content' => $returninfo['content'],   // 申请售后内容
            'messager' => $returninfo['title'],   // 申请售后留言
            'created' => date("Y-m-d H:i:s", $returninfo['add_time']),   // 申请时间 格式:YYYY - MM - dd HH:mm:ss
            'memo' => $returninfo['memo'],   // 售后备注
            'status' => '3',   // 状态 可选值:1(申请中),2(审核中),3(接受申请) 仅接受申请会进入该函数
            'attachment' => $returninfo['attachment'],   // 附件（通常为url）
        ];

        // 售后明细
        // todo amount 字段为0
        $returnItemModel = app::get('ome')->model('return_product_items');
        $returnItems = $returnItemModel->getList('name as sku_name,bn as sku_bn,num as number,price,amount,order_item_id', array('return_id' => $returninfo['return_id']));
    
        if ($returnItems && !empty($returnItems)) {
            $orderitemList = app::get('ome')->model('order_items')->getList('item_id,obj_id,order_id', ['item_id' => array_column($returnItems, 'order_item_id')]);
            $orderitemList = array_column($orderitemList, null, 'item_id');
            $orderObjList  = app::get('ome')->model('order_objects')->getList('obj_id,oid', ['obj_id' => array_column($orderitemList, 'obj_id')]);
            $orderObjList  = array_column($orderObjList, null, 'obj_id');
        
            foreach ($returnItems as $k => $val) {
                $itemInfo               = $orderitemList[$val['order_item_id']] ?? [];
                $returnItems[$k]['oid'] = $orderObjList[$itemInfo['obj_id']]['oid'] ?? 0;
            }
            $params['aftersale_items'] = json_encode($returnItems);
        }

        $title = '店铺(' . $this->__channelObj->channel['name'] . ')售后申请(订单号:' . $params['tid'] . ',申请单号:' . $returninfo['return_bn'] . ')';

        $rs = $this->__caller->call(SHOP_ADD_AFTERSALE_RPC, $params, [], $title, 10, $params['tid']);

        // 调试mock
        /*if (is_array($rs) && $rs['rsp'] == 'fail') {
            $rs['rsp'] = 'succ';
            $rs['data'] = [
                'aftersaleNo' => "TPA-". date("YmdHis") . rand(1000, 9000)
            ];
        }*/
        // 直连情况下,执行callback函数 callback保存返回单号至special表
        $callbackParams = array(
            'order_id' => $returninfo['order_id'],
            'return_id' => $returninfo['return_id'],
            'return_bn' => $returninfo['return_bn']
        );
        $this->addAfterSaleCallback($rs, $callbackParams);
        return $rs;
    }
    
    /**
     * 卖家确认收货
     * 对应第三方B2B接口文档, b2b.aftersale.update 更新售后申请的状态 接口
     * 对应D1M文档, oms/aftersaleUpdate 推送售后单审核结果 接口
     * @param $aftersale 售后申请请求参数
     * @return string[]|void
     */
    public function returnGoodsConfirm($sdf)
    {
        $title = '售后确认收货[' . $sdf['return_bn'] . ']';

        $returnModel = app::get('ome')->model('return_product');
        $returninfo = $returnModel->db_dump(array('return_id' => $sdf['return_id']), 'order_id,return_bn');
        $sdf['return_bn'] = $returninfo['return_bn'];
        $orderInfo = app::get('ome')->model('orders')->db_dump(array('order_id' => $returninfo['order_id']), 'order_bn');
     
        $data = array(
            'order_bn' => $orderInfo['order_bn'], //订单号
            'reship_bn' => $sdf['return_bn'], //订单号
            'status' => '4', // 状态，可选值:1(申请中),2(审核中),3(接受申请),4(完成),5(拒绝)
            'addon' =>'',
        );

      
        $method = 'b2b.reship.create';
        $data['status'] = 'SUCC';

        $items = array();
        list($res,$newItems) = $this->getReshipItems($sdf);
        if($res){
            $items = $newItems;
        }
        $items = json_encode($items);

        $data['items'] = $items;

        $rs = $this->__caller->call($method, $data, array(), $title, 10, $sdf['return_bn']);

        return $rs;
    }

    /**
     * 售后单添加回调
     * @param $response
     * @param $callback_params
     * @return array
     */
    public function addAfterSaleCallback($response, $callback_params)
    {
        // 保存westore返回的售后单号
        $status = $response['rsp'];
        /*if ($status == 'succ' && isset($response['data']['aftersaleNo'])) {

            $model = app::get('ome')->model('return_apply_special');
            $data = array(
                'return_id' => $callback_params['return_id'],
                'return_bn' => $callback_params['return_bn'],
                'special' => json_encode([
                    'aftersale_no' => $response['data']['aftersaleNo'],
                ], JSON_UNESCAPED_UNICODE)
            );
            $model->db_save($data);
        }*/
        return $this->callback($response, $callback_params);
    }


    /**
     * 获取原始订单号
     * @param $order
     */
    protected function _getOriginalOrderBn($order, $returninfo)
    {
        // 原样返回
        if(!isset($order['createway']) || $order['createway'] =='matrix' || !isset($order['relate_order_bn']) || !$order['relate_order_bn']){
            return $order['order_bn'];
        }
        
        $filter = array(
            'order_bn' => $order['relate_order_bn'],
        );
     
        if($returninfo['archive']){
            $archive_ordObj = kernel::single('archive_interface_orders');
            $originalOrder = $archive_ordObj->getOrders($filter, 'order_bn,createway,relate_order_bn');
        }else{
            $orderMdl = app::get('ome')->model('orders');
            $originalOrder = $orderMdl->dump($filter, 'order_bn,createway,relate_order_bn');
        }
        
        // 没有原始订单则返回
        if(!$originalOrder){
            return $order['order_bn'];
        }elseif($originalOrder['createway'] == 'matrix'){
            return $originalOrder['order_bn'];
        }
        // 仍非平台订单,则再次调用查询
        elseif($originalOrder['relate_order_bn']){
            return $this->_getOriginalOrderBn($originalOrder);
        }
        // 兜底返回原样
        else{
            return $order['order_bn'];
        }
    }

    /**
     * 换货参数格式化
     * @param $returninfo
     * @param $status
     * @return array
     */
    protected function _formatChangeParams($returninfo,$status){
        $params = array(
            'return_bn' => $returninfo['return_bn'],
            'order_bn' => $returninfo['order']['order_bn'],
            'status' => $status
        );

        if ($params['status'] == '13') {
            $params['addon'] = '换货商品已发出';
        } elseif ($params['status'] == '15') {
            $params['addon'] = '换货商品已收到';
        } elseif ($params['status'] == '16') {
            $params['addon'] = '换货完成';
        }

        return $params;
    }

    /**
     * 换货商品发货
     * 对应第三方B2B接口文档, b2b.exchange.consigngoods 换货商品发货 接口
     * @param $data
     * @return array
     */
    public function consignGoods($data){
        $title = '换货商品发货[' . $data['return_bn'] . ']';

        $returnModel = app::get('ome')->model('return_product');
        $returninfo = $returnModel->db_dump(array('return_id' => $data['return_id']), 'order_id,return_bn');
        $data['return_bn'] = $returninfo['return_bn'];
        $orderInfo = app::get('ome')->model('orders')->db_dump(array('order_id' => $returninfo['order_id']), 'order_bn');
     
        $params = array(
            'return_bn' => $data['return_bn'], //退货单号
            'order_bn' => $orderInfo['order_bn'], //订单号
            'logi_no' => $data['logi_no'], //物流单号
            'logi_code' => $data['logi_code'], //物流公司代码
            'logi_name' => $data['logi_name'], //物流公司名称
            'status' => '13', //状态
            'addon' => '换货商品已发出', //备注
        );

        $rs = $this->__caller->call(SHOP_EXCHANGE_CONSIGNGOODS, $params, array(), $title, 10, $data['return_bn']);

        return $rs;
    }

    /**
     * 获取退货商品明细
     * @param $sdf
     * @return array
     */
    public function getReshipItems($sdf)
    {
        $returnItemModel = app::get('ome')->model('return_product_items');
        $returnItems = $returnItemModel->getList('name as sku_name,bn as sku_bn,num as number,price,amount,order_item_id', array('return_id' => $sdf['return_id']));
    
        if ($returnItems && !empty($returnItems)) {
            $orderitemList = app::get('ome')->model('order_items')->getList('item_id,obj_id,order_id', ['item_id' => array_column($returnItems, 'order_item_id')]);
            $orderitemList = array_column($orderitemList, null, 'item_id');
            $orderObjList  = app::get('ome')->model('order_objects')->getList('obj_id,oid', ['obj_id' => array_column($orderitemList, 'obj_id')]);
            $orderObjList  = array_column($orderObjList, null, 'obj_id');
        
            foreach ($returnItems as $k => $val) {
                $itemInfo               = $orderitemList[$val['order_item_id']] ?? [];
                $returnItems[$k]['oid'] = $orderObjList[$itemInfo['obj_id']]['oid'] ?? 0;
            }
            return array(true, $returnItems);
        }
        return array(false, array());
    }
} 