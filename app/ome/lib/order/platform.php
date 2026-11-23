<?php

class ome_order_platform
{
    /**
     * 平台自发类型的订单自动完成发货
     *
     * @param $orderId
     * @return array|string[]
     */
    public function deliveryConsign($orderId)
    {
        $orderMdl       = app::get('ome')->model('orders');
        $deliveryMdl    = app::get('ome')->model('delivery');
        $orderObjectMdl = app::get('ome')->model('order_objects');

        $order = $orderMdl->db_dump(array('order_id' => $orderId));

        if($order['is_fail'] == 'true') {
            return array('rsp' => 'fail', 'order_bn' => $order['order_bn'], 'msg' => '失败订单不能发货');
        }

        $shShipCount = $orderObjectMdl->count([
            'order_id'   => $orderId,
            'is_sh_ship' => 'true',
            'delete'     => 'false',
        ]);

        $rs = array('rsp' => 'fail', 'order_bn' => $order['order_bn']);

        if ($order['order_type'] != 'platform' && $shShipCount == 0) {
            $rs['msg'] = '不是平台自发订单';
            return $rs;
        }
        if (!in_array($order['source_status'], ['WAIT_BUYER_CONFIRM_GOODS', 'TRADE_FINISHED'])) {
            //$rs['msg'] = '平台订单状态不对'; return $rs;
        }
        
        $delivery_id_list = array();
        $delivery_list = $deliveryMdl->getDeliversByOrderId($order['order_id']);
        foreach ($delivery_list as $key => $value) {
            if (in_array($value['status'], array('ready', 'progress'))) {
                $delivery_id_list[] = $value['delivery_id'];
            }
        }

        if (!$delivery_id_list) {
            foreach ($this->_get_order_items($order['order_id']) as $store_code => $value) {

                $order['order_items'] = $value;

                list($rsp, $msg, $delivery_id) = $this->addDelivery($order, $store_code);

                if ($rsp === false){
                    $rs['msg'] = $msg;return $rs;
                }

                $delivery_id_list[] = $delivery_id;
            }
        }

        if (!$delivery_id_list) {
            $rs['msg'] = '订单已经发货或者没有符合自动发货的订单明细（is_sh_ship必须为true）!';
            return $rs;
        }

        /////////////////////////////////////////////////////////
        // 发货单发货                                           //
        ////////////////////////////////////////////////////////
        $delivery_list = $deliveryMdl->getList('delivery_bn,delivery_id', array('delivery_id' => $delivery_id_list));

        foreach ($delivery_list as $delivery) {

            $params = array(
                'status'      => 'delivery',
                'delivery_bn' => $delivery['delivery_bn'],

            );
            $consignRs = kernel::single('ome_event_receive_delivery')->update($params);
            if ($consignRs['rsp'] == 'fail') {
                app::get('ome')->model('operation_log')->write_log('delivery_process@ome', $delivery['delivery_id'], '发货单发货失败:'.$consignRs['msg']);
                $rs['msg'] = '发货单发货失败:'.$consignRs['msg'];
                return $rs;
            }
        }
        
        // Service扩展服务
        foreach ($delivery_list as $delivery)
        {
            $delivery_id = $delivery['delivery_id'];
            
            // 发货单发货完成后，service扩展触发其他服务
            foreach(kernel::servicelist('ome.service.delivery.after.consign') as $service) {
                if(method_exists($service,'after_consign')) {
                    $service->after_consign($delivery_id);
                }
            }
        }
        
        return array('rsp' => 'succ');
    }
    
    /**
     * 自动创建发货单
     *
     * @param $order
     * @param $store_code
     * @return array
     */
    public function addDelivery($order, $store_code)
    {
        $brRelMdl    = app::get('wmsmgr')->model('branch_relation');
        $brMdl       = app::get('ome')->model('branch');
        $logMdl      = app::get('ome')->model('operation_log');
        $deliveryMdl = app::get('ome')->model('delivery');
        $orderMdl    = app::get('ome')->model('orders');

        // 判断是否指定平台仓
        $branch_id = null;
        if ($store_code) {
            $wms_branch = $brRelMdl->db_dump(array('wms_branch_bn' => $store_code), 'sys_branch_bn');

            if ($wms_branch['sys_branch_bn']) {
                $branch = $brMdl->db_dump(array('branch_bn' => $wms_branch['sys_branch_bn'], 'check_permission' => 'false'), 'branch_id');
            } else {
                $branch = $brMdl->db_dump(array('branch_bn' => $store_code, 'check_permission' => 'false'), 'branch_id');
            }

            $branch_id = $branch['branch_id'];
        }

        if (!$branch_id) {
            // 京东引凤对接：引凤订单优先匹配 type=yinfeng 仓
            // 引凤识别口径：sendPayMap[1410]=='1' → __yinfeng_order → SOMS_YINFENG 标签
            $err = null;
            if (kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'SOMS_YINFENG', $err)) {
                $branch = $brMdl->db_dump(array('type' => 'yinfeng', 'check_permission' => 'false'), 'branch_id');
                if ($branch) {
                    $branch_id = $branch['branch_id'];
                }
            }
            // 普通平台自发仍 owner=3 fallback
            if (!$branch_id) {
                $branch = $brMdl->db_dump(array('owner' => '3','platform' => $order['shop_type'], 'check_permission' => 'false'), 'branch_id');
                if (!$branch) {
                    $branch = $brMdl->db_dump(array('owner' => '3','type'=>'main', 'check_permission' => 'false' ,'filter_sql' => '(platform is null or platform="")'), 'branch_id');
                }
                $branch_id = $branch['branch_id'];
            }
        }

        if (!$branch_id) {
            $errmsg = '平台自发仓库未添加（store_code：'. $store_code .'）';

            $logMdl->write_log('order_confirm@ome', $order['order_id'], $errmsg);

            return array(false, $errmsg);
        }
        
        // 物流公司ID
        $logi_id = 0;
        if(isset($order['logi_id']) && $order['logi_id']){
            $logi_id = intval($order['logi_id']);
        }elseif(isset($order['corp_id']) && $order['corp_id']){
            $logi_id = intval($order['corp_id']);
        }
        
        // 自动获取一条物流公司（优先使用：虚拟物流公司）
        if(empty($logi_id)){
            $corpInfo = $this->getVirtualDlyCorpInfo($branch_id);
            if($corpInfo){
                $logi_id = $corpInfo['corp_id'];
            }
        }
        
        // data
        $delivery = array(
            'branch_id'            => $branch_id,
            'logi_id' => $logi_id,
            'delivery_waybillCode' => $order['logi_no'],
            'consignee'            => array(
                'name'      => $order['ship_name'],
                'r_time'    => $order['ship_time'],
                'mobile'    => $order['ship_mobile'],
                'zip'       => $order['ship_zip'],
                'area'      => $order['ship_area'],
                'telephone' => $order['ship_tel'],
                'email'     => $order['ship_email'],
                'addr'      => $order['ship_addr'],
            ),
            'delivery_items'       => array(),
        );

        $order_items = array();
        foreach ($order['order_items'] as $item) {
            if ($item['delete'] == 'true' || !$item['nums']) {
                continue;
            }

            if (!$item['product_id']) {
                $errmsg = '明细未修复，无法生成发货单';

                $logMdl->write_log('order_confirm@ome', $order['order_id'], $errmsg);

                return array(false, $errmsg);
            }
            if(!$delivery['delivery_items'][$item['product_id']]) {
                $delivery['delivery_items'][$item['product_id']] = array(
                    'product_id'      => $item['product_id'],
                    'bn'              => $item['bn'],
                    'product_name'    => $item['name'],
                    'shop_product_id' => $item['shop_product_id'],
                );
            }
            $delivery['delivery_items'][$item['product_id']]['number'] += $item['nums'];

            $order_items[] = array(
                'item_id'      => $item['item_id'],
                'product_id'   => $item['product_id'],
                'number'       => $item['nums'],
                'bn'           => $item['bn'],
                'product_name' => $item['name'],
                'obj_id'       => $item['obj_id'],
            );
        }

        if (!$order_items) {
            $errmsg = '明细已删除';

            $logMdl->write_log('order_confirm@ome', $order['order_id'], $errmsg);

            return array(false, $errmsg);
        }
        
        $split_status = '';
        $result      = $deliveryMdl->addDelivery($order['order_id'], $delivery, array(), $order_items, $split_status);
        $delivery_id = $result['data'];

        if (!$delivery_id) {
            $logMdl->write_log('order_confirm@ome', $order['order_id'], $result['msg']);

            return array(false, $result['msg']);
        }

        //更新订单信息
        $orderMdl->update(array(
            'process_status' => $split_status,
            'confirm'        => 'Y',
            'dispatch_time'  => time(),
            'refund_status'  => 0,
            'splited_num_upset_sql' => 'IF(`splited_num` IS NULL, 1, `splited_num` + 1)',
        ), array('order_id' => $order['order_id']));

        $d = $deliveryMdl->db_dump($delivery_id, 'delivery_id,delivery_bn');

        $log_msg = sprintf('订单确认(发货单号：<a href="index.php?app=ome&ctl=admin_receipts_print&act=show_delivery_items&id=%s" target="_blank">%s</a>)', $d['delivery_id'], $d['delivery_bn']);
        $logMdl->write_log('order_confirm@ome', $order['order_id'], $log_msg);

        return array(true, '生成发货单成功', $delivery_id);
    }

    private function _get_order_items($order_id)
    {
        $objectMdl = app::get('ome')->model('order_objects');
        $itemMdl   = app::get('ome')->model('order_items');

        $object_list = $item_list = array();
        foreach ($objectMdl->getList('*', array('order_id' => $order_id, 'is_sh_ship' => 'true', 'delete' => 'false')) as $value) {
            $object_list[$value['obj_id']] = $value;
        }

        if (!$object_list) {
            return [];
        }

        foreach ($itemMdl->getList('*', array('order_id' => $order_id, 'obj_id' => array_column($object_list, 'obj_id'), 'delete' => 'false', 'filter_sql' => ' nums > split_num')) as $value) {
            $store_code = (string) $object_list[$value['obj_id']]['store_code'];

            if ($left_nums = $value['nums'] - $value['split_num']) {
                $value['nums'] = $left_nums;

                $item_list[$store_code][$value['item_id']] = $value;
            }
        }

        return $item_list;
    }
    
    /**
     * 获取虚拟物流公司信息
     *
     * @return void
     */
    public function getVirtualDlyCorpInfo($branch_id=0)
    {
        $corpMdl = app::get('ome')->model('dly_corp');
        
        // 优先获取虚拟物流公司
        $corpInfo = $corpMdl->db_dump(array('type'=>'virtual_delivery', 'disabled'=>'false'), '*');
        
        // 默认读取仓库关联的第一个物流公司
        if(empty($corpInfo) && $branch_id){
            $sql = "SELECT a.*, b.name FROM sdb_ome_branch_corp AS a LEFT JOIN sdb_ome_dly_corp AS b ON a.corp_id=b.corp_id WHERE a.branch_id=". $branch_id;
            $corpInfo = $corpMdl->db->selectrow($sql);
        }
        
        // 获取第一个物流公司（不包含门店类型）
        if(empty($corpInfo)){
            $sql = "SELECT * FROM `sdb_ome_dly_corp` WHERE `type` NOT IN('o2o_pickup', 'o2o_ship')";
            $corpInfo = $corpMdl->db->selectrow($sql);
        }
        
        //物流公司ID
        $logi_id = ($corpInfo['corp_id'] ? $corpInfo['corp_id'] : 1);
        
        // 获取物流公司信息
        $corpInfo = $corpMdl->db_dump(array('corp_id'=>$logi_id), '*');
        
        return $corpInfo;
    }
}
