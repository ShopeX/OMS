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
 * 门店自提发货单处理Lib类
 * 
 * @author: wangbiao@shopex.cn
 */
class wap_delivery
{
    function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 门店自提物流（o2o_pickup）对应的 logi_id 列表
     *
     * @return int[]
     */
    public function getPickupLogiIds()
    {
        static $ids = null;
        if ($ids !== null) {
            return $ids;
        }
        $rows = kernel::database()->select(
            "SELECT corp_id FROM sdb_ome_dly_corp WHERE disabled='false' AND type='o2o_pickup'"
        );
        $ids = $rows ? array_column($rows, 'corp_id') : array();
        return $ids;
    }

    /**
     * 订单列表等场景：排除自提单
     *
     * @return array
     */
    public function getExcludePickupFilter()
    {
        $ids = $this->getPickupLogiIds();
        if (empty($ids)) {
            return array();
        }
        return array('logi_id|notin' => $ids);
    }

    /**
     * 自提订单列表：仅 o2o_pickup
     *
     * @return array
     */
    public function getPickupOnlyFilter()
    {
        $ids = $this->getPickupLogiIds();
        if (empty($ids)) {
            return array('logi_id' => 0);
        }
        return array('logi_id' => $ids);
    }

    /**
     * 原生 SQL 排除自提单（统计用）
     *
     * @param string $alias 表别名，如 wd
     * @return string
     */
    public function getExcludePickupSql($alias = '')
    {
        $ids = $this->getPickupLogiIds();
        if (empty($ids)) {
            return '';
        }
        $col = $alias ? $alias . '.logi_id' : 'logi_id';
        return ' AND ' . $col . ' NOT IN (' . implode(',', array_map('intval', $ids)) . ')';
    }

    /**
     * 通过外部发货单号找到关联的订单号
     *
     * @param $outer_delivery_bn    ome发货单号
     * @return Array
     */
    public function getList($filter, $offset=0, $limit=2, $orderby='', $current_page_type="")
    {
        $wapDeliveryObj    = app::get('wap')->model('delivery');
        
        //门店订单的配送超时时间
        if($filter['branch_id'])
        {
            $storeObj       = app::get('o2o')->model('store');
            $storeInfo      = $storeObj->dump(array('branch_id'=>$filter['branch_id']), 'store_id');
        }
        
        //发货单列表
        $fields            = 'delivery_id, delivery_bn, itemNum, status, print_status, process_status, wms_status, is_received, shop_id, 
                              outer_delivery_bn, ship_name, ship_area, ship_addr, ship_mobile, ship_tel, 
                              branch_id, confirm, is_cod, order_bn, total_amount, logi_name, create_time, order_createtime, logi_status, logi_id';
        $dataList          = $wapDeliveryObj->getList($fields, $filter, $offset, $limit, $orderby);
        $cloudprintCache   = array();
        $cloudprintLib     = kernel::single('wap_event_trigger_cloudprint_common');
        foreach ($dataList as $key => $val)
        {
            $dataList[$key]['delivery_bill'] = app::get('wap')->model('delivery_bill')->dump(array('delivery_id' => $val['delivery_id']), '*');
            if (empty($dataList[$key]['delivery_bill'])) {
                $dataList[$key]['delivery_bill'] = array('logi_no' => '');
            }
            $order_info = $this->get_order_info($val['order_bn'], $val['is_cod']);
            #订单信息
            $dataList[$key]['order_info']    = $order_info;
            $shop_type = $order_info['shop_type'];
            
            if (strpos($val['ship_name'], '>>')) {
                $dataList[$key]['ship_name'] = substr($val['ship_name'], 0, strpos($val['ship_name'], '>>'));
            }
            if (strpos($val['ship_mobile'], '>>')) {
                $dataList[$key]['ship_mobile'] = substr($val['ship_mobile'], 0, strpos($val['ship_mobile'], '>>'));
            }
            if (strpos($val['ship_addr'], '>>')) {
                $dataList[$key]['ship_addr'] = substr($val['ship_addr'], 0, strpos($val['ship_addr'], '>>'));
            }

            $ship_area_str = '';
            if ($val['ship_area']) {
                $ship_area = explode(":", $val['ship_area']);
                $ship_area_str = str_replace("/", "", $ship_area[1]);
               
                if(strpos($dataList[$key]['ship_addr'], $ship_area_str) === false ){
                    $dataList[$key]['ship_addr'] = $ship_area_str . $dataList[$key]['ship_addr'];
                }
                
            }
            // 店铺渠道名称
            $dataList[$key]['shop_type_name'] = ome_shop_type::shop_name($shop_type);
            #发货单明细
            if($val['outer_delivery_bn'])
            {
                $count_num    = 0;
                $dataList[$key]['delivery_items']    = $this->getDeliveryItemList($val['outer_delivery_bn'], 0, -1, array(
                    'wap_status' => (string) $val['status'],
                    'branch_id'  => (int) $val['branch_id'],
                ));
                $dataList[$key]['dlyItemCount']      = $count_num;
            }
            $dataList[$key]['dly_status']    = $this->formatDeliveryStatus('status', $val['status']);
            $dataList[$key]['dly_confirm']   = $this->formatDeliveryStatus('confirm', $val['confirm']);
            #已签收
            if($val['status']=='0' && $val['print_status']=='1')
            {
                $dataList[$key]['dly_status']    = '已打印';
            }
            if (strval($val['wms_status'] ?? '') === '30') {
                $dataList[$key]['dly_prepare_status'] = '已备货';
            }
            #已签收 / 自提已提货
            if($val['status']=='3' && $val['is_received']=='2')
            {
                $pickupIds = $this->getPickupLogiIds();
                if (!empty($pickupIds) && in_array($val['logi_id'], $pickupIds)) {
                    $dataList[$key]['dly_status'] = '已提货';
                } else {
                    $dataList[$key]['dly_status'] = '已签收';
                }
            }

            #门店自提仓库对应店铺信息
            $dataList[$key]['shop_info']     = $this->getBranchShopInfo($val['branch_id']);
            $dataList[$key]['store_name']    = $dataList[$key]['shop_info']['store_name'];
            $storeId = isset($dataList[$key]['shop_info']['store_id']) ? $dataList[$key]['shop_info']['store_id'] : 0;
            if (!isset($cloudprintCache[$storeId])) {
                $cloudprintCache[$storeId] = $cloudprintLib->isCloudprintConfigured($storeId);
            }
            $dataList[$key]['has_cloudprint'] = $cloudprintCache[$storeId];
            $pickupIds = $this->getPickupLogiIds();
            if (!empty($pickupIds) && in_array($val['logi_id'], $pickupIds)) {
                $dataList[$key]['pickup_status_name'] = $this->formatPickupStatusName($val, $order_info);
                $dataList[$key]['can_pickup_verify']  = $this->canPickupVerify($val, $order_info);
            }
            
            #付款方式
            $dataList[$key]['pay_name']    = ($val['is_cod']=='true' ? '货到付款' : '款到发货');
            
            #履约超时时间
            $dataList[$key]['dly_overtime']    = $this->getDeliveryOvertime($val['create_time']);
            
            //获取page_type用来控制操作按钮显示
            if($current_page_type){
                $dataList[$key]['current_page_type'] = $current_page_type;
            }

            // 处理签收照片
            $deliveryImages = kernel::single('wap_deliveryimg')->getwapdeliveryImages($val['delivery_id']);
            if($deliveryImages){
                $dataList[$key]['count_signimage'] = count($deliveryImages);
                $dataList[$key]['signimage_attachment_url'] = json_encode($deliveryImages);
            }
        }

        unset($filter, $fields, $count_num, $storeInfo, $mark_text);
        
        return $dataList;
    }
    
    /**
     * 自提单状态展示文案
     *
     * @param array $delivery
     * @param array $orderInfo
     * @return string
     */
    public function formatPickupStatusName($delivery, $orderInfo = array())
    {
        $payStatus = isset($orderInfo['pay_status']) ? (string) $orderInfo['pay_status'] : '';
        if (in_array($payStatus, array('5'), true)) {
            return '已退款';
        }
        if (in_array($payStatus, array('4'), true)) {
            return '已退款';
        }
        if (in_array($payStatus, array('6', '7'), true)) {
            return '退款中';
        }
        if ($delivery['status'] == 1) {
            return '已取消';
        }
        if ($delivery['is_received'] == 2) {
            return '已提货';
        }
        if ($delivery['status'] == 3) {
            return '待提货';
        }
        if ($delivery['confirm'] == 3) {
            return '待接单';
        }
        if ($delivery['confirm'] == 1) {
            return '待发货';
        }
        if (!empty($delivery['platform_pickup_verified']) && (int) $delivery['is_received'] !== 2) {
            return '待本地提货';
        }
        return '待处理';
    }

    /**
     * 核销弹窗商品明细（优先用列表已加载的 delivery_items，避免重复查库/SAP）
     */
    public function formatPickupVerifyOrderItemsForModal(array $deliveryRow)
    {
        $result = array();
        foreach ((array) $deliveryRow['delivery_items'] as $dlyItem) {
            if (empty($dlyItem)) {
                continue;
            }
            $divide      = isset($dlyItem['divide_order_fee']) && $dlyItem['divide_order_fee'] !== ''
                ? $dlyItem['divide_order_fee']
                : '0.00';
            $esb_amount  = isset($dlyItem['esb_amount']) && $dlyItem['esb_amount'] !== ''
                ? $dlyItem['esb_amount']
                : $divide;
            $esb_pmt     = isset($dlyItem['esb_pmt_amount']) && $dlyItem['esb_pmt_amount'] !== ''
                ? $dlyItem['esb_pmt_amount']
                : '0.00';
            if ($esb_pmt == 0 && $esb_amount && $divide) {
                $esb_pmt = max(0, bcsub($esb_amount, $divide, 2));
            }

            $row = array(
                'name'             => $dlyItem['product_name'] ?: $dlyItem['bn'],
                'bn'               => $dlyItem['bn'],
                'esb_oid'          => $dlyItem['oid'] ?: '',
                'nums'             => $dlyItem['number'],
                'product_attr'     => $dlyItem['product_attr'] ?: '',
                'goods_img_url'    => $dlyItem['default_img_url'] ?: '',
                'divide_order_fee' => $divide,
                'esb_pmt_amount'   => $esb_pmt,
                'esb_amount'       => $esb_amount,
            );
            $result[] = $this->mergeItemDisplayFieldsByService($dlyItem, $row);
        }
        return $result;
    }

    /**
     * 核销弹窗商品明细（按发货单号查 OME 明细，含 ESB 平台金额）
     */
    public function formatPickupVerifyOrderItems($outerDeliveryBn, $orderBn)
    {
        $items = $this->getDeliveryItemList($outerDeliveryBn);
        if (empty($items)) {
            return array();
        }

        $orderRow = app::get('ome')->model('orders')->dump(array('order_bn' => $orderBn), 'order_id,shop_type');
        $platformObj = $this->_getSapPlatformObj(!empty($orderRow['shop_type']) ? $orderRow['shop_type'] : '');

        $result = array();
        foreach ($items as $dlyItem) {
            $esb_oid     = $dlyItem['oid'];
            $esb_amount  = 0;
            $esb_pmt_amount = 0;
            if ($platformObj && !empty($orderRow['order_id'])) {
                $platformOid = $platformObj->getOid($orderRow['order_id'], $dlyItem['oid']);
                if ($platformOid) {
                    $esb_oid = $platformOid;
                }
                $esbOrderItem = $platformObj->getSourceOrderItems($orderRow['order_id'], $esb_oid);
                if ($esbOrderItem) {
                    $esb_amount     = $esbOrderItem['payAmount'];
                    $esb_pmt_amount = $esbOrderItem['discAmount'];
                }
            }

            $divide = isset($dlyItem['divide_order_fee']) ? $dlyItem['divide_order_fee'] : '0.00';
            if (!$esb_amount) {
                $esb_amount = $divide;
            }
            if (!$esb_pmt_amount && $esb_amount && $divide) {
                $esb_pmt_amount = max(0, bcsub($esb_amount, $divide, 2));
            }

            $row = array(
                'name'             => $dlyItem['product_name'],
                'bn'               => $dlyItem['bn'],
                'esb_oid'          => $esb_oid,
                'nums'                => $dlyItem['number'],
                'product_attr'        => $dlyItem['product_attr'],
                'goods_img_url'       => $dlyItem['default_img_url'],
                'divide_order_fee'    => $divide,
                'esb_pmt_amount'      => $esb_pmt_amount,
                'esb_amount'          => $esb_amount,
            );
            $result[] = $this->mergeItemDisplayFieldsByService($dlyItem, $row);
        }

        return $result;
    }

    /**
     * 通过 service 扩展发货单明细展示字段（模号编码、款式名称、克重等）
     */
    public function enrichDeliveryItemsByService($delivery_id, array $items, array $context = array())
    {
        if (empty($items)) {
            return $items;
        }

        if ($service = kernel::servicelist('wap.service.delivery.items.display')) {
            foreach ($service as $object => $instance) {
                if (method_exists($instance, 'enrichDeliveryItems')) {
                    $items = $instance->enrichDeliveryItems($delivery_id, $items, $context);
                }
            }
        }

        return $items;
    }

    /**
     * 通过 service 按货号扩展明细展示字段（退货/换货等场景）
     *
     * @param array  $items
     * @param string $bnField
     * @param array  $context original_reship_bn/return_id/order_id 等扩展上下文
     */
    public function enrichItemsByBnByService(array $items, $bnField = 'bn', array $context = array())
    {
        if (empty($items)) {
            return $items;
        }

        if ($service = kernel::servicelist('wap.service.delivery.items.display')) {
            foreach ($service as $object => $instance) {
                if (method_exists($instance, 'enrichItemsByBn')) {
                    $items = $instance->enrichItemsByBn($items, $bnField, $context);
                }
            }
        }

        return $items;
    }

    /**
     * 通过 service 合并已扩展的展示字段到目标行
     */
    public function mergeItemDisplayFieldsByService(array $source, array $target)
    {
        if ($service = kernel::servicelist('wap.service.delivery.items.display')) {
            foreach ($service as $object => $instance) {
                if (method_exists($instance, 'mergeDisplayFields')) {
                    $target = $instance->mergeDisplayFields($source, $target);
                }
            }
        }

        return $target;
    }

    /**
     * SAP/ESB 平台对象（类不存在时返回 null）
     */
    private function _getSapPlatformObj($shop_type)
    {
        if (empty($shop_type)) {
            return null;
        }
        if (in_array($shop_type, array('shopex_fy', 'ecos.ecshopx'), true)) {
            $class = 'ome_sap_data_platform_shopex_ecshopx';
        } else {
            $class = sprintf('ome_sap_data_platform_%s', $shop_type);
        }
        if (!class_exists($class)) {
            return null;
        }
        return kernel::single($class);
    }

    /**
     * 是否展示「确认核销」按钮
     * 自提列表无接单/发货操作：未取消、未拒绝、未核销、未退款即可核销
     */
    public function canPickupVerify($delivery, $orderInfo = array())
    {
        $payStatus = isset($orderInfo['pay_status']) ? (string) $orderInfo['pay_status'] : '';
        if (in_array($payStatus, array('4', '5', '6', '7'), true)) {
            return false;
        }
        if ((int) $delivery['status'] === 1) {
            return false;
        }
        if ((int) $delivery['confirm'] === 2) {
            return false;
        }
        if ((int) $delivery['is_received'] === 2) {
            return false;
        }
        return true;
    }

    /**
     * 核销前自动补齐接单/发货（自提单无单独接单发货入口时）
     *
     * @param array $deliveryInfo
     * @param string $err_msg
     * @return array|false 更新后的发货单，失败返回 false
     */
    public function preparePickupForVerify(array $deliveryInfo, &$err_msg = '', array $serialItems = array())
    {
        $wapDeliveryObj = app::get('wap')->model('delivery');
        $process        = kernel::single('wap_delivery_process');
        $filter         = array('delivery_id' => $deliveryInfo['delivery_id']);

        if ((int) $deliveryInfo['status'] === 1) {
            $err_msg = '订单已取消';
            return false;
        }
        if ((int) $deliveryInfo['confirm'] === 2) {
            $err_msg = '订单已拒绝';
            return false;
        }
        if ((int) $deliveryInfo['is_received'] === 2) {
            $err_msg = '订单已提货';
            return false;
        }

        if ((int) $deliveryInfo['confirm'] === 3) {
            if (!$process->accept($deliveryInfo)) {
                $err_msg = '自动接单失败';
                return false;
            }
            $deliveryInfo = $wapDeliveryObj->dump($filter, '*');
        }

        if ((int) $deliveryInfo['status'] === 0 && (int) $deliveryInfo['confirm'] === 1) {
            if (!empty($serialItems)) {
                $deliveryInfo['serial_items'] = $serialItems;
            }
            // 自提核销无工号扫描，不写门店发货唯一码记录（见 saveFromWapConsign scanned_op_no 判断）
            $deliveryInfo['scanned_op_no'] = '';
            if (!$process->consign($deliveryInfo)) {
                $err_msg = '自动发货失败';
                return false;
            }
            $deliveryInfo = $wapDeliveryObj->dump($filter, '*');
        }

        if ((int) $deliveryInfo['status'] !== 3 || (int) $deliveryInfo['confirm'] !== 1) {
            $err_msg = '订单状态异常，无法核销';
            return false;
        }

        return $deliveryInfo;
    }

    /**
     * 通过外部发货单号找到关联的订单号
     * 
     * @param $outer_delivery_bn    ome发货单号
     * @return Array
     */
    public function getDeliverBnByOrderId($outer_delivery_bn)
    {
        $deliveryObj    = app::get('ome')->model('delivery');
        
        $sql            = "SELECT dord.order_id FROM sdb_ome_delivery_order AS dord 
                           LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                           WHERE d.delivery_bn='". $outer_delivery_bn ."' AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' 
                           AND d.status NOT IN('failed','cancel','back','return_back')";
        $order_ids      = $deliveryObj->db->selectrow($sql);
        
        return $order_ids['order_id'];
    }
    
    /**
     * 通过外部发货单号找到ome发货单号详细信息
     *
     * @param $outer_delivery_bn    ome发货单号
     * @return $delivery_bn
     */
    public function getOmeDeliveryInfo($outer_delivery_bn)
    {
        $deliveryObj    = app::get('ome')->model('delivery');
        
        $sql            = "SELECT delivery_id, delivery_bn FROM sdb_ome_delivery WHERE delivery_bn='". $outer_delivery_bn ."' 
                           AND (parent_id=0 OR is_bind='true') AND disabled='false' AND status NOT IN('failed','cancel','back','return_back')";
        $delivery_info  = $deliveryObj->db->selectrow($sql);
        
        return $delivery_info;
    }
    
    /**
     * 通过外部发货单号找到订单详细信息
     *
     * @param $outer_delivery_bn    ome发货单号
     * @return $orderInfo
     */
    public function getDeliverBnByOrderInfo($outer_delivery_bn)
    {
        $ordersObj         = app::get('ome')->model('orders');
        
        $order_id    = $this->getDeliverBnByOrderId($outer_delivery_bn);
        if(empty($order_id))
        {
            return '';
        }
        
        $orerInfo    = $ordersObj->dump(array('order_id'=>$order_id), 'order_id, order_bn, process_status, pay_status, ship_status');
        return $orerInfo;
    }
    
    /**
     * Wap发货单状态
     *
     * @param $outer_delivery_bn    ome发货单号
     * @return $orderInfo
     */
    public function formatDeliveryStatus($type='', $suffix='')
    {
        $data    = array();
        
        $data['status']          = array('处理中', '已取消', '暂停', '已发货');
        $data['print_status']    = array('未打印', '已打印');
        $data['process_status']  = array('未打印', '已打印', '已校验', 4=>'已称重打包', 8=>'已物流交接');
        $data['confirm']         = array(1=>'已确认', 2=>'已拒绝', 3=>'未确认');
        $data['is_cod']         = array("true"=>'货到付款', "false"=>'款到发货');
        
        if($type)
        {
            return $suffix || $suffix === '0' ? $data[$type][$suffix] : $data[$type];
        }
        
        return $data;
    }

    /**
     * 订单付款状态文案（sdb_ome_orders.pay_status）
     */
    public function formatPayStatus($pay_status)
    {
        static $map = null;
        if ($map === null) {
            $columns = app::get('ome')->model('orders')->schema['columns'];
            $map = !empty($columns['pay_status']['type']) ? $columns['pay_status']['type'] : array();
        }

        $key = strval($pay_status);
        return isset($map[$key]) ? $map[$key] : '';
    }
    
    /**
     * 通过门店自提仓库获取店铺详细信息
     *
     * @param $outer_delivery_bn    ome发货单号
     * @return $orderInfo
     */
    public function getBranchShopInfo($branch_id)
    {
        if(empty($branch_id)) {
            return [];
        }
        //$oBranchObj     = app::get('ome')->model('branch');

        $storeObj    = app::get('o2o')->model('store');

        $branch_info    = $storeObj->db->selectrow("SELECT branch_id, wms_id, branch_bn, name,store_id FROM sdb_ome_branch WHERE branch_id=" . $branch_id);
        
        $storeInfo    = array();
        if($branch_info)
        {
            $branch_info['branch_name']    = $branch_info['name'];
            unset($branch_info['name']);

            $storeInfo = $storeObj->dump(array('store_id'=>$branch_info['store_id']),'store_id, store_bn, name, area, shop_id, addr, contacter, mobile, tel');


            $storeInfo['store_name']    = $storeInfo['name'];
            $area    = explode(':', $storeInfo['area']);
            $storeInfo['store_addr']    = str_replace('/', '-', $area[1]) . $storeInfo['addr'];
            unset($storeInfo['name']);
        }
        
        return array_merge($branch_info, $storeInfo);
    }
    
    /**
     * 获取订单明细列表
     * 
     * @param int $order_id 订单id
     * @param bool $sort 是否要排序，默认不要。排序后的结果会按照普通商品、捆绑商品、赠品、配件等排列
     */
    function getOrderItemList($order_id, $sort=false)
    {
        $oOrder        = app::get('ome')->model('orders');
        $order_items   = array();
        
        if($sort)
        {
            $items    = $oOrder->dump($order_id, 'order_id', array("order_objects"=>array("*")));
            foreach($items['order_objects'] as $k=>$v)
            {
                $order_items[$v['obj_type']][$k] = $v;
                
                $sql    = "SELECT *,nums AS quantity FROM sdb_ome_order_items WHERE obj_id=".$v['obj_id']." AND item_type='product' ORDER BY item_type";
                foreach($oOrder->db->select($sql) as $it)
                {
                    $order_items[$v['obj_type']][$k]['order_items'][$it['item_id']] = $it;
                }
                
                $sql    = "SELECT *,nums AS quantity FROM sdb_ome_order_items WHERE obj_id=".$v['obj_id']." AND item_type<>'product' ORDER BY item_type";
                foreach($oOrder->db->select($sql) as $it)
                {
                    $order_items[$v['obj_type']][$k]['order_items'][$it['item_id']] = $it;
                }
            }
        }
        else 
        {
            $items    = $oOrder->dump($order_id, 'order_id', array("order_objects"=>array("*",array("order_items"=>array("*")))));
            foreach($items['order_objects'] as $oneobj)
            {
                foreach ($oneobj['order_items'] as $objitems)
                    $order_items[] = $objitems;
            }
        }
        
        return $order_items;
    }
    
    /**
     * 获取发货单明细
     */
    function getDeliveryItemList($delivery_bn, $offset=0, $limit=-1, $context = array())
    {
       $basicMaterialObj = app::get('material')->model('basic_material');
        $bmeMdl = app::get("material")->model("basic_material_ext");
        $deliveryObj = app::get('ome')->model('delivery');
        $dlyItemObj = app::get('ome')->model('delivery_items');
        $dlyItemsDetailObj = app::get('ome')->model('delivery_items_detail');
        $orderItemObj = app::get('ome')->model('order_items');
        $orderObjectsObj = app::get('ome')->model('order_objects');
        $materialLib = kernel::single('material_basic_material');
        $deliveryInfo    = $deliveryObj->dump(array('delivery_bn'=>$delivery_bn), 'delivery_id');
        if(empty($deliveryInfo))
        {
            return '';
        }
        
        $delivery_id   = $deliveryInfo['delivery_id'];
        $items         = $dlyItemObj->getList('*', array('delivery_id' => $delivery_id), 0, -1);
        
        if ($items)
        {
            foreach ($items as $key => $item) {
                // delivery_items_detail
                $orderItemRel = $dlyItemsDetailObj->dump(array('delivery_item_id' => $item['item_id']), 'order_item_id,oid');
                if (empty($orderItemRel['order_item_id'])) {
                    $items[$key]['oid'] = '';
                    $items[$key]['product_attr'] = '';
                    if (empty($items[$key]['default_img_url'])) {
                        $defaultImgFile = ROOT_DIR . '/app/wap/statics/img/goods_default.png';
                        $items[$key]['default_img_url'] = kernel::base_url() . '/app/wap/statics/img/goods_default.png?v=' . (file_exists($defaultImgFile) ? filemtime($defaultImgFile) : time());
                    }
                    continue;
                }
                $orderItem = $orderItemObj->dump(array('item_id' => $orderItemRel['order_item_id']), '*');
                if (empty($orderItem)) {
                    continue;
                }
                $items[$key]['order_item_id'] = $orderItemRel['order_item_id'];

                $items[$key]['order_item_del'] = $orderItem['delete'];
                $items[$key]['oid'] = $orderItemRel['oid'];

                $orderObjects = $orderObjectsObj->dump(array('obj_id' => $orderItem['obj_id']), 'name');
                //$items[$key]['product_name'] = $orderObjects['name'];

                # 商城直接取订单明细里的实付金额和单价
                if($deliveryInfo['shop_type'] == 'ecos.ecshopx'){
                    // 单价
                    $items[$key]['divide_order_price'] = $orderItem['price'];
                    // 总价
                    $items[$key]['divide_order_fee'] = $orderItem['divide_order_fee'];
                }else{
                    // 单价
                    $items[$key]['divide_order_price'] = bcdiv($orderItem['divide_order_fee'], $orderItem['quantity'], 2);
                    // 总价
                    $items[$key]['divide_order_fee'] = bcmul(bcdiv($orderItem['divide_order_fee'], $orderItem['quantity'], 3), $item['number'], 2);
                }

                $addon = unserialize($orderItem['addon']);
                $product_attr = [];
                if ($addon['product_attr']) {
                    foreach ($addon['product_attr'] as $attr) {
                        $product_attr[] = $attr['label'] . ":" . $attr['value'];
                    }
                }

                if ($product_attr) {
                    $items[$key]['product_attr'] = implode("; ", $product_attr);
                } else {
                    $items[$key]['product_attr'] = '';
                }
                $bmeInfo = $bmeMdl->dump(array("bm_id" => $item['product_id']), "specifications,unit");
                if(empty($product_attr)){
                    $items[$key]['product_attr'] = $bmeInfo['specifications'];
                }
                $items[$key]['unit'] = $bmeInfo['unit'];
                $mainImage = $materialLib->getBasicMaterialMainImage($item['product_id']);
                if ($mainImage) {
                    $items[$key]['default_img_url'] = $mainImage['full_url'];
                }

                // 显示图片
               
                if (empty($items[$key]['default_img_url'])) {
                    $defaultImgFile = ROOT_DIR . '/app/wap/statics/img/goods_default.png';
                    $items[$key]['default_img_url'] = kernel::base_url() . '/app/wap/statics/img/goods_default.png?v=' . (file_exists($defaultImgFile) ? filemtime($defaultImgFile) : time());
                }

                //将商品的显示名称改为后台的显示名称
                $productInfo = $basicMaterialObj->dump(array('material_bn' => $items[$key]['bn']), 'material_name');
                if (!empty($productInfo['material_name'])) {
                    $items[$key]['product_name'] = $productInfo['material_name'];
                }
            }
        }

        if (!empty($items)) {
            if (empty($context['wap_status']) || empty($context['branch_id'])) {
                $wapDelivery = app::get('wap')->model('delivery')->dump(
                    array('outer_delivery_bn' => $delivery_bn),
                    'status,branch_id'
                );
                if (empty($context['wap_status']) && !empty($wapDelivery['status'])) {
                    $context['wap_status'] = (string) $wapDelivery['status'];
                }
                if (empty($context['branch_id']) && !empty($wapDelivery['branch_id'])) {
                    $context['branch_id'] = (int) $wapDelivery['branch_id'];
                }
            }
            $items = $this->enrichDeliveryItemsByService($delivery_id, $items, $context);
        }

        return $items;
    }
    
    /**
     * 获取履约超时时间
     * 
     * @param $delivery_time 发货时间
     */
    function getDeliveryOvertime($delivery_time)
    {
        //履约超时时间设置(分钟)
        $minute    = app::get('o2o')->getConf('o2o.delivery.dly_overtime');
        $minute    = intval($minute);
        
        if(empty($minute) || empty($delivery_time))
        {
            return false;
        }
        
        $second    = $minute * 60;
        
        //履约超时时间
        $diff_time    = time() - $delivery_time;
        
        return ($diff_time > $second ? $diff_time : 0);
    }
    
    /**
     * 读取缓存数据
     * 
     */
    function fetchDataFromCache()
    {
        $branchObj     = kernel::single('o2o_store_branch');
        $branch_ids    = $branchObj->getO2OBranchByUser(true);
        if(empty($branch_ids))
        {
            return false;
        }
        $branch_id    = $branch_ids[0];
        
        $statistic    = cachecore::fetch('wap_statistic_'. $branch_id);
        
        //更新今日订单总数(15分钟更新一次)
        if($statistic)
        {
            $today_order    = cachecore::fetch('wap_today_order_'. $branch_id);
            if(empty($today_order))
            {
                $this->taskmgr_statistic('today');
                
                cachecore::store('wap_today_order_'. $branch_id, '1', 900);
                
                //重新读取数据
                $statistic    = cachecore::fetch('wap_statistic_'. $branch_id);
            }
        }
        
        return $statistic;
    }
    
    /**
     * 任务列表执行统计订单数据
     */
    function taskmgr_statistic($type)
    {
       return true;
        $branchObj     = kernel::single('o2o_store_branch');
        $branch_ids    = $branchObj->getO2OBranchByUser(true);
        $branch_id     = $branch_ids[0];
        
        if(empty($branch_id) || empty($type))
        {
            return false;
        }
        
        //任务队列
        $push_params = array(
                'data' => array(
                        'branch_id' => $branch_id,
                        'type' => $type,
                        'task_type' => 'autowapdly',
                ),
                'url' => kernel::openapi_url('openapi.autotask','service')
        );
        kernel::single('taskmgr_interface_connecter')->push($push_params);
        
        return true;
    }
    
    /**
     * 获取订单信息(合单发货单有多个订单号order以|分隔)
     */
    function get_order_info($order_bns, $is_cod='')
    {
        if(empty($order_bns))
        {
            return false;
        }
        
        $ordersObj   = app::get('ome')->model('orders');
        $order_bn    = explode('|', $order_bns);
        
        $order_info        = array();
        $order_remark      = array();
        $receivable        = 0;
        
        //订单列表
        $dataList    = $ordersObj->getList('order_id, createtime, mark_text, is_tax, tax_company, paytime, shop_type, source_status, pay_status, ship_status, member_id', array('order_bn'=>$order_bn));
        foreach ($dataList as $key => $val)
        {
            //只取第一条订单信息
            if(empty($order_info))
            {
                $order_info    = $val;
            }
            
            //备注
            if($val['mark_text'])
            {
                $mark_text    = unserialize($val['mark_text']);
                if ($mark_text) {
                    foreach ($mark_text as $k => $v) {
                        if (!strstr($v['op_time'], "-"))
                        {
                            $v['op_time']    = date('Y-m-d H:i:s',$v['op_time']);
                            $mark_text[$k]['op_time']    = $v['op_time'];
                        }
                    }
                }
                $order_remark[$val['order_id']]    = $mark_text;
            }
            
            //货到付款订单的应收费用
            if($is_cod == 'true')
            {
                $temp_price    = app::get('ome')->model('order_extend')->dump(array('order_id'=>$val['order_id']), '*');
                $receivable    += $temp_price['receivable'];
            }
        }
        
        $order_info['mark_text']    = $order_remark;
        $order_info['receivable']   = $receivable;
        $order_info['pay_status_name'] = $this->formatPayStatus($order_info['pay_status'] ?? '');

        // 购买人信息（会员姓名/手机，对应 OMS 订单详情「购买人信息」）
        $order_info['buyer_name']   = '';
        $order_info['buyer_mobile'] = '';
        $order_info['buyer_display'] = '';
        if (!empty($order_info['member_id'])) {
            $member = app::get('ome')->model('members')->dump(
                array('member_id' => $order_info['member_id']),
                'name,mobile'
            );
            if ($member) {
                $buyerName = isset($member['contact']['name']) ? trim((string) $member['contact']['name']) : trim((string) ($member['name'] ?? ''));
                $buyerMobile = '';
                if (isset($member['contact']['phone']['mobile'])) {
                    $buyerMobile = trim((string) $member['contact']['phone']['mobile']);
                } elseif (isset($member['mobile'])) {
                    $buyerMobile = trim((string) $member['mobile']);
                }
                // 加密字段截断展示前缀
                if ($buyerName !== '' && strpos($buyerName, '>>') !== false) {
                    $buyerName = substr($buyerName, 0, strpos($buyerName, '>>'));
                }
                if ($buyerMobile !== '' && strpos($buyerMobile, '>>') !== false) {
                    $buyerMobile = substr($buyerMobile, 0, strpos($buyerMobile, '>>'));
                }
                $order_info['buyer_name']   = $buyerName;
                $order_info['buyer_mobile'] = $buyerMobile;
                if ($buyerName !== '' && $buyerMobile !== '') {
                    $order_info['buyer_display'] = $buyerName . '（' . $buyerMobile . '）';
                } elseif ($buyerMobile !== '') {
                    $order_info['buyer_display'] = $buyerMobile;
                } elseif ($buyerName !== '') {
                    $order_info['buyer_display'] = $buyerName;
                }
            }
        }
        
        return $order_info;
    }

    /**
     * 获取首页统计数据
     */
    public function getStoreStatistic($storeInfo) {
        //今日订单总数
        //待发货
        //待退货
        //今日发货取消
        //今日退货取消
        //今日派单量（KV 净派单：下发 +1，撤销/拒单 -1）
        $branch_id = $storeInfo['branch_id'];
        $db = kernel::database();
        $today  = strtotime(date('Y-m-d', time()) .'00:00');
        $excludePickupSql = $this->getExcludePickupSql();

        // 交易笔数（不含自提）
        $sql = "SELECT count(*) as _count FROM sdb_wap_delivery WHERE branch_id=". intval($branch_id)
            ." AND create_time>=". $today . $excludePickupSql;
        $num = $db->selectrow($sql);
        $today_num = $num['_count'] ? $num['_count'] : 0;

        // 订单列表待发货（不含自提）
        $sql = "SELECT count(*) as _count FROM sdb_wap_delivery WHERE branch_id=". intval($branch_id)
            . " AND status='0'" . $excludePickupSql;
        $orderList = $db->selectrow($sql);
        $today_delivery_num = $orderList['_count'] ? $orderList['_count'] : 0;

        $sql = "SELECT count(*) as _count FROM sdb_wap_delivery WHERE branch_id=". intval($branch_id)
            . " AND status='1' AND last_modified>=".$today . $excludePickupSql;
        $orderList = $db->selectrow($sql);
        $today_delivery_cancel_num = $orderList['_count'] ? $orderList['_count'] : 0;

        //售后
        $rpMdl = app::get("wap")->model("return");
        $filter = array('branch_id'=>$branch_id, 'status'=>['1']);
        $aftersale_return = $rpMdl->count($filter);
       
        $sql = "select count(*) as _count from sdb_wap_return WHERE branch_id=".$branch_id." AND status in('2') and last_modified>=".$today."";
        $returns = $db->selectrow($sql);
        $aftersale_return_cancel = $returns['_count'] ? $returns['_count'] : 0;

        $wap_pickup_orders = 0;
        $pickupLogiIds = $this->getPickupLogiIds();
        if (!empty($branch_id) && $pickupLogiIds) {
            $sql = 'SELECT count(*) as _count FROM sdb_wap_delivery WHERE branch_id=' . intval($branch_id)
                . " AND status <> '1' AND is_received <> '2' AND logi_id IN ("
                . implode(',', array_map('intval', $pickupLogiIds)) . ')';
            $pickupStat = $db->selectrow($sql);
            $wap_pickup_orders = $pickupStat['_count'] ? $pickupStat['_count'] : 0;
        }

        $today_dispatch_num = kernel::single('wap_branch_daily_counter')->getDispatchCount($branch_id);

       return array(
            'today_num' => $today_num,
            'wap_order_index' => $today_delivery_num,
            'wap_aftersale_returnproduct' => $aftersale_return,
            'wap_store_selfpick' => $wap_pickup_orders,
            'delivery_cancel_num'=>$today_delivery_cancel_num,
            'aftersale_cancel_num' => $aftersale_return_cancel,
            'today_dispatch_num' => $today_dispatch_num,
        );
    }

    /**
     * OMS 已发货而门店 WAP 仍为待发货时，对齐 WAP 与 delivery_bill
     */
    public function syncWapFromOmeIfOmsShippedButH5Pending($wap_delivery_id)
    {
        $wap_delivery_id = (int) $wap_delivery_id;
        if ($wap_delivery_id <= 0) {
            return false;
        }
        $deliveryObj = app::get('wap')->model('delivery');
        $wap = $deliveryObj->dump($wap_delivery_id, 'delivery_id,delivery_bn,outer_delivery_bn,status,logi_status,branch_id,order_bn');
        if (empty($wap['outer_delivery_bn'])) {
            return false;
        }
        if ((string) $wap['status'] !== '0' || (string) $wap['logi_status'] !== '0') {
            return false;
        }
        $ome = app::get('ome')->model('delivery')->dump(
            array('delivery_bn' => $wap['outer_delivery_bn'], 'disabled' => 'false'),
            'delivery_id,status,logi_no,logi_id,logi_name,delivery_time'
        );
        if (empty($ome) || $ome['status'] !== 'succ') {
            return false;
        }
        $logiNo = isset($ome['logi_no']) ? trim((string) $ome['logi_no']) : '';
        if ($logiNo === '') {
            return false;
        }
        $delivery_time = !empty($ome['delivery_time']) ? (int) $ome['delivery_time'] : time();
        $branchObj = kernel::single('ome_branch');
        $o2oStoreInfo = $branchObj->getStoreSenderInfoByBranchId($wap['branch_id']);
        $consigner_mobile = '';
        if (!empty($o2oStoreInfo['consignee']['custom_login_mobile'])) {
            $consigner_mobile = $o2oStoreInfo['consignee']['custom_login_mobile'];
        } elseif (!empty($o2oStoreInfo['consignee']['mobile'])) {
            $consigner_mobile = $o2oStoreInfo['consignee']['mobile'];
        }
        $deliveryObj->save(array(
            'delivery_id' => $wap['delivery_id'],
            'status' => 3,
            'process_status' => 7,
            'logi_status' => '7',
            'logi_id' => $ome['logi_id'],
            'logi_name' => isset($ome['logi_name']) ? $ome['logi_name'] : '',
            'last_modified' => time(),
            'delivery_time' => $delivery_time,
        ));
        $deliveryBillObj = app::get('wap')->model('delivery_bill');
        $bill = $deliveryBillObj->dump(array('delivery_id' => $wap['delivery_id']), 'delivery_id');
        if (empty($bill)) {
            $deliveryBillObj->insert(array(
                'delivery_id' => $wap['delivery_id'],
                'logi_no' => $logiNo,
                'logi_no_time' => time(),
                'print_status' => 3,
                'consigner_mobile' => $consigner_mobile,
            ));
        } else {
            $deliveryBillObj->update(
                array('logi_no' => $logiNo, 'logi_no_time' => time(), 'print_status' => 3, 'consigner_mobile' => $consigner_mobile),
                array('delivery_id' => $wap['delivery_id'])
            );
        }
        if (!empty($ome['delivery_id'])) {
            app::get('ome')->model('operation_log')->write_log(
                'delivery_modify@ome',
                $ome['delivery_id'],
                'OMS已发货，已自动对齐门店H5发货单状态（运单号：' . $logiNo . '）'
            );
        }
        return true;
    }

    public function syncStaleWapWaitDeliveryFromOme(array $filter, $limit = 100)
    {
        $limit = max(1, min(500, (int) $limit));
        $branchSql = '';
        if (!empty($filter['branch_id'])) {
            $b = is_array($filter['branch_id']) ? $filter['branch_id'] : array($filter['branch_id']);
            $b = array_filter(array_map('intval', $b));
            if ($b) {
                $branchSql = ' AND wd.branch_id IN (' . implode(',', $b) . ')';
            }
        }
        $sql = 'SELECT wd.delivery_id FROM sdb_wap_delivery wd '
            . 'INNER JOIN sdb_ome_delivery od ON od.delivery_bn = wd.outer_delivery_bn AND od.disabled = \'false\' '
            . "WHERE wd.status = 0 AND wd.logi_status = '0' "
            . "AND od.status = 'succ' AND od.logi_no IS NOT NULL AND od.logi_no != ''"
            . $branchSql
            . ' LIMIT ' . $limit;
        $rows = kernel::database()->select($sql);
        foreach ((array) $rows as $row) {
            if (!empty($row['delivery_id'])) {
                $this->syncWapFromOmeIfOmsShippedButH5Pending((int) $row['delivery_id']);
            }
        }
    }

     public function checkDeliveryPrint($delivery_id, $action = 'electron', &$msg)
    {
        $deliveryObj = app::get('wap')->model('delivery');
        $orderMdl = app::get('ome')->model('orders');
        $delivery = $deliveryObj->dump(array('delivery_id' => $delivery_id), 'delivery_id,delivery_bn,branch_id,outer_delivery_bn,order_bn,status,print_status,logi_status');

        $omeDeliveryObj = app::get('ome')->model('delivery');
        $omeDeliveryInfo = $omeDeliveryObj->dump(array('delivery_bn' => $delivery['outer_delivery_bn']), '*');
        if (in_array($omeDeliveryInfo['status'], ['back', 'return_back', 'cancel'])) {
            $msg = '发货单:' . $delivery['delivery_bn'] . '已撤销';
            return false;
        }

        if ($this->syncWapFromOmeIfOmsShippedButH5Pending($delivery_id)) {
            $msg = 'OMS已发货且已有运单号，门店端状态已自动同步，请返回列表刷新查看，无需再录入运单号。';
            return false;
        }

        # 发货仓库
        $omeDeliveryInfo['wap_branch_id'] = $delivery['branch_id'];
        $omeDeliveryInfo['wap_delivery_id'] = $delivery['delivery_id'];
        $omeDeliveryInfo['wap_delivery_bn'] = $delivery['delivery_bn'];
        # 检查是否为手工单
        if (!empty($delivery['order_bn'])) {
            $orderInfo = $orderMdl->dump(array('order_bn' => $delivery['order_bn']), 'order_id,pay_status,source,source_status,createway');
            if (empty($orderInfo)) {
                $msg = '该发货单未找到相应的订单信息';
                return false;
            } elseif (in_array($orderInfo['pay_status'], ['5'])) {
                $msg = '该订单已全额退款，不能发货';
                return false;
            }  elseif ($orderInfo['source_status'] == 'TRADE_CLOSED') {
                $msg = '订单交易取消,不能发货';
                return false;
            } elseif ($orderInfo['source_status'] == 'PAID_FORBID_CONSIGN') {
                $msg = '拼团中订单、POP暂停或者发货强管控的订单，已付款但禁止发货';
                return false;
            }

            
        }
        if ($action != 'reprint' && $delivery['print_status'] == '1') {
            $bill = app::get('wap')->model('delivery_bill')->dump(
                array('delivery_id' => $delivery_id),
                'logi_no'
            );
            // 备货后 print_status=1 但尚未录入运单号，仍允许补录
            if (!empty($bill['logi_no'])) {
                $msg = '发货单:' . $delivery['outer_delivery_bn'] . '已打印';
                return false;
            }
        }
       
        if ($action!='reprint' && $omeDeliveryInfo['status'] == 'succ') {
            $msg = '发货单:' . $delivery['outer_delivery_bn'] . '已发货';
            return false;
        }

        
        return $omeDeliveryInfo;
    }

    public function checkDeliveryStatus($delivery_id, &$msg)
    {
        if (empty($delivery_id)) {
            return false;
        }

        $deliveryObj = app::get('wap')->model('delivery');
        $omeDeliveryObj = app::get('ome')->model('delivery');

        $delivery = $deliveryObj->dump(array('delivery_id' => $delivery_id), 'delivery_id,delivery_bn,outer_delivery_bn,status');
        if ($delivery['status'] == '1') {
            $msg = '当前发货单已取消';
            return false;
        }

        $omeDeliveryInfo = $omeDeliveryObj->dump(array('delivery_bn' => $delivery['outer_delivery_bn']), '*', array('delivery_items' => array('*'), 'delivery_order' => array('*')));
        if (in_array($omeDeliveryInfo['status'], ['back', 'return_back', 'cancel'])) {
            $msg = '发货单:' . $delivery['delivery_bn'] . '已撤销';
            return false;
        }

        # 设置wap发货单信息
        $omeDeliveryInfo['wap_delivery_id'] = $delivery_id;
        $omeDeliveryInfo['wap_delivery_bn'] = $delivery['delivery_bn'];
        return $omeDeliveryInfo;
    }

}
