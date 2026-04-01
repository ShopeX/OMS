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

class erpapi_router_mapping
{

    private static $_rsp_method_mapping = array(
        'ome.remark.add'                 => 'shop.remark.add',
        'ome.logistics.push'             => 'hqepay.logistics.push',
        'ome.payment.add'                => 'shop.payment.add',
        'ome.payment.status_update'      => 'shop.payment.statusUpdate',
        'ome.aftersale.add'              => 'shop.aftersale.add',
        'ome.aftersale.status_update'    => 'shop.aftersale.statusUpdate',
        'ome.aftersale.logistics_update' => 'shop.aftersale.logisticsUpdate',
        'ome.refund.add'                 => 'shop.refund.add',
        'ome.refund.aftersale_add'       => 'shop.refund.aftersale_add',
        'ome.refund.status_update'       => 'shop.refund.statusUpdate',
        'ome.order.payment_update'       => 'shop.order.payment_update',
        'ome.order.memo_update'          => 'shop.order.memo_update',
        'ome.order.memo_add'             => 'shop.order.memo_add',
        'ome.order.custom_mark_update'   => 'shop.order.custom_mark_update',
        'ome.order.custom_mark_add'      => 'shop.order.custom_mark_add',
        'ome.order.ship_status_update'   => 'shop.order.ship_status_update',
        'ome.order.pay_status_update'    => 'shop.order.pay_status_update',
        'ome.order.status_update'        => 'shop.order.status_update',
        'ome.order.add'                  => 'shop.order.add',
        'ome.aftersalev2.add'            => 'shop.aftersalev2.add',
        'ome.unionpay.push'              => 'unionpay.logistics.push',
        'ome.invoice.message_push'       => 'shop.invoice.message_push',
        'ome.qianniu.address'            => 'shop.qianniu.address_modify',
        'ome.qianniu.address_notify'     => 'shop.qianniu.order_addr_modify',//地址更新
        'ome.shopbee.ordermsg'           => 'shop.bookingrefund.ordermsg', //预约退款
        'ome.order.delivergoods'         => 'shop.delivergoods.urgent', //催发货
        'ome.order.promise'              => 'shop.delivergoods.promise', //晚发赔时间更新
        'ome.order.invoice'              => 'shop.invoice.add', //自助开发票
        'ome.order.express'              => 'shop.express.assign', //指定快递
        'ome.order.getcorp'              => 'shop.express.getcorp', //指定快递可用物流公司
        'ome.exchange.add'               => 'shop.exchange.add',
        'ome.xc.order.add'               => 'shop.branch.wait',
        'system.msg.notify'              => 'system.msg.notify',
        'ome.goods.add'                 => 'shop.goods.add',
        'ome.goods.delete'              => 'shop.goods.delete',
        'ome.qianniu.modifysku'         => 'shop.qianniu.modifysku',
        'ome.stock.get'                 => 'shop.stock.get',
        'matrix_message.refund_created.notify' => 'shop.tmcnotify.refund',
        'ome.shop.aoxiang_signed' => 'shop.shop.aoxiang_signed', //翱象系统通知签约
        'ome.goods.aoxiang.update' => 'shop.goods.aoxiang_update', //货品新建&更新结果回传
        'ome.goods.aoxiang.combine_update' => 'shop.goods.aoxiang_combine_update', //组合货品新建&更新结果回传
        'ome.goods.aoxiang.combine_delete' => 'shop.goods.aoxiang_delete', //货品删除结果回传
        'ome.goods.aoxiang.mapping' => 'shop.goods.aoxiang_mapping', //商货品关联关系结果回传
        'ome.inventory.aoxiang.query' => 'shop.stock.aoxiang_query', //实仓库存查询接口
        'ome.order.deliverypriority'     => 'shop.deliverypriority.comeback',//订单挽回
        'store.trade.add'                        => 'shop.order.add', //d1m创建订单
        'store.trade.refund.add'                 => 'shop.aftersalev2.add', //d1m创建退款单
        'store.trade.aftersale.add'              => 'shop.aftersalev2.add', //d1m创建售后申请单
        'store.trade.aftersale.logistics.update' => 'shop.aftersalev2.logisticsUpdate', //d1m更新售后申请物流信息
        'store.trade.salesmaterial.listing'      => 'shop.salesmaterial.getList', //d1m主档查询
        'store.trade.invoice.add'                => 'shop.invoice.message_push', //d1m订单开票
        'store.trade.exchange.add'               => 'shop.exchange.add', //website换货
        'ome.bookingrefund.ordercancle'=>'shop.bookingrefund.ordercancle',
        'ome.stock.occupy'              =>  'shop.stock.occupy',
        'ome.goods.sku_delete'              =>  'shop.goods.sku_delete',
        'invoice.einvoice.status_update' => 'invoice.order.status_update',
        'store.item.skus.add'            =>  'shop.goods.add',
        'ome.reissue.query'              => 'shop.reissue.query',
        'ome.reissue.cancel'             => 'shop.reissue.cancel',
        'qimen.taobao.erp.order.add'     => 'qimen.order.add',
        'qimen.taobao.erp.order.update'  => 'qimen.order.update',
        'ome.shopbee.fxordermsg'         => 'shop.bookingrefund.fxordermsg', // 供销供应商订单信息同步
        'ome.reshipping.add'              => 'shop.reshipping.add',
    );

    public static $_rsp_nodetype_mapping = array(
        'weimeng', 'zhe800', 'youzan', 'yintai', 'yihaodian', 'wx', 'wdwd', 'vop', 'vjia', 'ecos.b2c', 'ecos.dzg', 'shopex_b2c', 'bbc', 'ecshop_b2c', 'public_b2c', 'shopex_b2b', 'suning', 'qqbuy', 'paipai', 'mogujie', 'mia', 'mengdian', 'meilishuo', 'juanpi', 'icbc', 'gome', 'feiniu', 'dangdang', 'ccb', 'beibei', 'amazon', 'alibaba', '360buy',
    );

    public static $_afterSaleV2 = array(
        'meilishuo', 'beibei', 'feiniu', 'yihaodian', 'zhe800',
    );
    
    private static $_tran_method = array(
        'ome.order.add',
        'ome.aftersalev2.add',
        'ome.exchange.add',
        'ome.refund.add',
        'ome.qianniu.address',
    );
    
    private static $_tran_method_mapping = array(
        'ome.order.add'         =>  'dealer.order.add',
        'ome.aftersalev2.add'   =>  'dealer.aftersalev2.add',
        'ome.exchange.add'      =>  'dealer.exchange.add',
        'ome.refund.add' => 'dealer.refund.add',
        'ome.qianniu.address' => 'dealer.qianniu.address_modify',
    );
    
    #是否映射到erpapi中response
    public static function rspServiceMapping($service, $method, &$node_id)
    {
        //转发方法
        if(in_array($method, self::$_tran_method)){
            $shops = self::get_shops($node_id);
        
            //发货模式
            if(in_array($shops['delivery_mode'], array('shopyjdf'))){
                if (self::$_tran_method_mapping[$method]) {
                    return self::$_tran_method_mapping[$method];
                }
            }
        }
        
        if (self::$_rsp_method_mapping[$method]) {
            // [开源ERP]获取绑定的qimen节点
            if(in_array($method, ['qimen.taobao.erp.order.add', 'qimen.taobao.erp.order.update'])){
                // app_key
                $app_key = $_REQUEST['target_appkey'];
                
                // 获取奇门聚石塔内外互通渠道信息
                $channelInfo = kernel::single('channel_channel')->getQimenJushitaErp($app_key);
                if(!empty($channelInfo)){
                    $node_id = $channelInfo['node_id'];
                }
            }
            
            return self::$_rsp_method_mapping[$method];
        }

        // if ($method == 'ome.order.add' && in_array(self::get_node_type($node_id), self::$_rsp_nodetype_mapping)) return 'ome.order.add';
        //if ($method == 'ome.aftersalev2.add' && in_array(self::get_node_type($node_id), self::$_afterSaleV2)) return 'shop.aftersalev2.add';

        return false;
    }

    private static function get_node_type($node_id)
    {
        $row = kernel::database()->selectrow('SELECT node_type FROM sdb_ome_shop WHERE node_id="' . addslashes($node_id) . '"');

        return $row['node_type'];
    }
    
    private static function get_shops($node_id)
    {
        $shops_detail = app::get('ome')->model('shop')->dump(array('node_id'=>$node_id), '*');
        if ($shops_detail['config']){
            $shops_detail['config'] = @unserialize($shops_detail['config']);
        }
        if (is_string($shops_detail['addon']) && $shops_detail['addon']){
            $shops_detail['addon'] = @unserialize($shops_detail['addon']);
        }
        
        return $shops_detail;
    }
}
