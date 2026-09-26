<?php
/**
 * 门店自提类
 * 20250114
 * @author: yzq@shopex.cn
 */
class wap_ziti{

    function __construct($app){
        $this->app = $app;
    }

    //获取并格式化自提订单的数据（包括主表和明细表）
    public function getList($filter, $offset=0, $limit=1, $orderby='', $menu_type = ''){
        $ordMdl = app::get("ome")->model("orders");

        //主表数据
        $dataList = $ordMdl->getList("*", $filter, $offset, $limit, $orderby);
        if (empty($dataList)){
            return array();
        }

        foreach ($dataList as $k => $v){
            $dataList[$k] = $this->formatOrdData($v);

        }
        unset($var_data);
        return $dataList;
    }

    public function formatOrdData($var_data){
        $shopMdl = app::get("ome")->model("shop");
        $bmeMdl = app::get("material")->model("basic_material_ext");
        $oiMdl = app::get("ome")->model('order_items');
        $objMdl = app::get("ome")->model("order_objects");
        $storeMdl = app::get("o2o")->model("store");

        $platformObj = $this->_getPlatformObj($var_data['shop_type']);
        //获取明细表数据
        $oiFilter = array(
            'delete' => 'false',
            'order_id' => $var_data['order_id'],
        );
        $ordItems = $oiMdl->getList("*", $oiFilter);
        foreach ($ordItems as $oik => $oiv){
            $addon = unserialize($oiv['addon']);
            $product_attr = [];
            if ($addon['product_attr']) {
                foreach ($addon['product_attr'] as $attr) {
                    $product_attr[] = $attr['label'] . ":" . $attr['value'];
                }
            }
            if ($product_attr) {
                $ordItems[$oik]['product_attr'] = implode("; ", $product_attr);
            } else {
                $ordItems[$oik]['product_attr'] = '';
            }
            $ordItems[$oik]['goods_img_url'] = $this->getImgUrl($var_data['order_id'], $oiv['item_id']);
            $objInfo = $objMdl->dump($oiv['obj_id'], "oid");
            $ordItems[$oik]['oid'] = $objInfo['oid'] ?: '';
            //规格
            $addon = unserialize($oiv['addon']);
            $product_attr = [];
            if ($addon['product_attr']) {
                foreach ($addon['product_attr'] as $attr) {
                    $product_attr[] = $attr['label'] . ":" . $attr['value'];
                }
            }

            if ($product_attr) {
                $ordItems[$oik]['product_attr'] = implode("; ", $product_attr);
            } else {
                $ordItems[$oik]['product_attr'] = '';
            }
            $bmeInfo = $bmeMdl->dump(array("bm_id" => $oiv['product_id']), "specifications");
            if(empty($product_attr)){
                $ordItems[$oik]['product_attr'] = $bmeInfo['specifications'];
            }

            $esb_oid = '';
            $esb_amount = 0;
            $esb_pmt_amount = 0;
            if ($platformObj) {
                $esb_oid = $platformObj->getOid($var_data['order_id'], $objInfo['oid']);
                $esb_order_item = $platformObj->getSourceOrderItems($var_data['order_id'], $esb_oid);
                $esb_amount = empty($esb_order_item) ? 0 : $esb_order_item['payAmount'];
                $esb_pmt_amount = empty($esb_order_item) ? 0 : $esb_order_item['discAmount'];
            }
            $ordItems[$oik]['esb_oid'] = $esb_oid ? $esb_oid : $ordItems[$oik]['oid'];
            $ordItems[$oik]['esb_amount'] = $esb_amount;
            $ordItems[$oik]['esb_pmt_amount'] = $esb_pmt_amount;
        }
        $var_data['order_items'] = $ordItems;
        $var_data['createtime_format'] = $var_data['createtime'] ? date("Y-m-d H:i:s", $var_data['createtime']) : '-';

        $shop_type_map = ome_shop_type::get_shop_type();
        $shopInfo = $shopMdl->dump($var_data['shop_id'], "name,shop_type");
        $var_data['shop_name'] = $shopInfo['name'];
        $var_data['shop_type_name'] = $shop_type_map[$shopInfo['shop_type']];

        //所属门店信息
        $storeInfo = $storeMdl->dump(array("store_id" => $var_data['selfpickup_store_id']), "store_id,name,performance_type,store_bn,branch_id,status");
        $var_data['store_name'] = $storeInfo['name'];

        $address = $this->decryptAddress($var_data['order_id'], false);
        $var_data['ship_name'] = $address['ship_name'];
        $var_data['ship_mobile'] = $address['ship_mobile'];
        $var_data['ship_tel'] = $address['ship_tel'];
        $var_data['ship_addr'] = $address['ship_area'].$address['ship_addr'];
        $var_data['decrypt_ship_name'] = $address['decrypt_ship_name'];
        $var_data['decrypt_ship_tel'] = $address['decrypt_ship_tel'];
        $var_data['decrypt_ship_mobile'] = $address['decrypt_ship_mobile'];
        $var_data['decrypt_ship_addr'] = $address['ship_area'].$address['decrypt_ship_addr'];

        //商城脱敏
        if($shopInfo['shop_type'] == 'ecos.ecshopx'){
            $var_data['ship_name'] = kernel::single('base_view_helper')->modifier_cut($var_data['ship_name'],-1,'*',false,true);
            $var_data['ship_mobile'] = kernel::single('base_view_helper')->modifier_cut($var_data['ship_mobile'],-1,'****',false,true);
            $var_data['ship_tel'] = kernel::single('base_view_helper')->modifier_cut($var_data['ship_tel'],-1,'****',false,true);
            $var_data['ship_addr'] = $address['ship_area']."******";
        }
        $var_data['ship_addr'] = '';//自提订单不展示收货地址

        //备注
        $order_remark_text = '';
        $custom_remark_text = '';
        if($var_data['mark_text']){
            $mark_text = unserialize($var_data['mark_text']);
            if ($mark_text) {
                foreach ($mark_text as $k => $v) {
                    $order_remark_text .= $v['op_content']." ".$v['op_time']." by ".$v['op_name']."<br>";
                }
            }
        }


        //客户备注
        if ($var_data['custom_mark']) {
            $custom_mark = unserialize($var_data['custom_mark']);
            if ($custom_mark) {
                foreach ($custom_mark as $k => $v) {
                    $custom_remark_text .= $v['op_content']." ".$v['op_time']." by ".$v['op_name']."<br>";
                }
            }
        }
        $var_data['order_remark_text'] = $order_remark_text;
        $var_data['custom_remark_text'] = $custom_remark_text;

        if($var_data['ziti_verify_status'] == '1' && $var_data['pay_status'] == '1' && $var_data['ship_status'] == '0'){
            $var_data['ziti_status_name'] = '待提货';
        }

        $raMdl = app::get("ome")->model("refund_apply");
        $raFilter = array(
            'order_id' => $var_data['order_id'],
            'status' => ['0','1','2','4','5','6'],
        );
        $raInfo = $raMdl->dump($raFilter, "apply_id");
        if(!empty($raInfo)){
            $var_data['ziti_status_name'] = '退款中';
        }

        if(in_array($var_data['pay_status'], [4,5])){
            $var_data['ziti_status_name'] = '已退款';
        }

        return $var_data;
    }

    /**
     * 获取商品图片地址
     * @param $order_id
     * @param $order_item_id
     * @return string
     */
    public function getImgUrl($order_id, $order_item_id = '', $oid = '', $bn =''){
        // 显示图片
        $ordMdl = app::get("ome")->model("orders");
        $objMdl = app::get("ome")->model("order_objects");
        $oiMdl = app::get("ome")->model("order_items");
        if($oid){
            $oiInfo = $objMdl->dump(array("oid" => $oid), "shop_goods_id,bn,obj_id");
            $itemInfo = $oiMdl->dump(array("obj_id" => $oiInfo['obj_id']), "shop_goods_id,obj_id,img");
        } else {
            $itemInfo = $oiMdl->dump(array("item_id" => $order_item_id), "shop_goods_id,obj_id,img");
            $oiInfo = $objMdl->dump(array("obj_id" => $itemInfo['obj_id']), "shop_goods_id,bn");
        }

        if (empty($oiInfo['bn'])) {
            $oiInfo['bn'] = $bn;
        }

        $ordInfo = $ordMdl->dump($order_id, "shop_id,shop_type");

        if($ordInfo['shop_type'] == 'ecos.ecshopx'){
            $default_img_url = $itemInfo['img'];
        }elseif (app::get('inventorydepth') && app::get('inventorydepth')->is_installed()) {
            $inventorydepthSkuObj = app::get('inventorydepth')->model('shop_skus');
            $objectsItems = $inventorydepthSkuObj->dump(array('shop_product_bn' => $oiInfo['bn'], 'shop_id' => $ordInfo['shop_id']), 'default_img_url');
            if ($objectsItems['default_img_url']) {
                $default_img_url = $objectsItems['default_img_url'];
            } else {
                #$inventorydepthItemsObj = app::get('inventorydepth')->model('shop_items');
                #$inventorydepthItems = $inventorydepthItemsObj->dump(array('iid' => $objectsItems['shop_iid'], 'shop_id' => $ordInfo['shop_id']), 'default_img_url');
                #$default_img_url = '';
                #if ($inventorydepthItems) {
                #    $default_img_url = $inventorydepthItems['default_img_url'];
                #}
            }

            if ($default_img_url && $ordInfo['shop_type'] == '360buy') {
                $default_img_url = 'https://img13.360buyimg.com/n2' . $objectsItems['default_img_url'];
            }
        } else {
            $default_img_url = '';
        }

        if (empty($default_img_url)) {
            $defaultImgFile = ROOT_DIR . '/app/wap/statics/img/goods_default.png';
            $default_img_url = kernel::base_url() . '/app/wap/statics/img/goods_default.png?v=' . (file_exists($defaultImgFile) ? filemtime($defaultImgFile) : time());
        }

        return $default_img_url;
    }

    /**
     * 获取订单加解密收货地址信息
     * @param $orderId
     * @return array|string[]
     */
    public function decryptAddress($orderId, $decrypt = true)
    {
        $field = 'order_bn,shop_id,shop_type,ship_area,ship_tel,ship_mobile,ship_addr,ship_name';
        $data = app::get('ome')->model('orders')->db_dump(array('order_id' => $orderId), $field);
        if (!$data) {
            return [
                'ship_name' => '',
                'ship_tel' => '',
                'ship_mobile' => '',
                'ship_addr' => '',
                'ship_area' => '',
                'decrypt_ship_name' => '',
                'decrypt_ship_tel' => '',
                'decrypt_ship_mobile' => '',
                'decrypt_ship_addr' => '',
            ];
        }
        $decrypt_data = [];
        if($decrypt){
            $decrypt_data = kernel::single('ome_security_router', $data['shop_type'])->decrypt(array(
                'ship_tel'    => $data['ship_tel'],
                'ship_mobile' => $data['ship_mobile'],
                'ship_addr'   => $data['ship_addr'],
                'shop_id'     => $data['shop_id'],
                'order_bn'    => $data['order_bn'],
                'ship_name' => $data['ship_name'],
            ), 'order');
        }
        $ship_area_arr = explode(":", $data['ship_area']);
        $ship_area = str_replace("/", "", $ship_area_arr[1]);
        if(strpos($data['ship_addr'], ">>") !== false){
            $data['ship_addr'] = explode(">>", $data['ship_addr'])[0];
        }
        if(strpos($data['ship_mobile'], ">>") !== false){
            $data['ship_mobile'] = explode(">>", $data['ship_mobile'])[0];
        }
        if(strpos($data['ship_name'], ">>") !== false){
            $data['ship_name'] = explode(">>", $data['ship_name'])[0];
        }

        $res = [
            'ship_name' => $data['ship_name'],
            'ship_tel' => $data['ship_tel'],
            'ship_mobile' => $data['ship_mobile'],
            'ship_addr' => $data['ship_addr'],
            'ship_area' => $ship_area,
            'decrypt_ship_name' => isset($decrypt_data['ship_name']) ? $decrypt_data['ship_name'] : '',
            'decrypt_ship_tel' => isset($decrypt_data['ship_tel']) ? $decrypt_data['ship_tel'] : '',
            'decrypt_ship_mobile' => isset($decrypt_data['ship_mobile']) ? $decrypt_data['ship_mobile'] : '',
            'decrypt_ship_addr' => isset($decrypt_data['ship_addr']) ? $decrypt_data['ship_addr'] : '',
        ];
        return $res;
    }

    /**
     * 自提订单 自动发货签收
     * @param $order_bn
     * @param $err_msg
     */
    public function autoConsignZiti($order_bn, &$err_msg){
        $orderMdl       = app::get('ome')->model('orders');
        $deliveryMdl    = app::get('ome')->model('delivery');
        $logMdl         = app::get('ome')->model('operation_log');
        $orderObjectMdl = app::get('ome')->model('order_objects');

        $order = $orderMdl->db_dump(array('order_bn' => $order_bn));

        if($order['is_fail'] == 'true') {
            $err_msg = '失败订单不能发货';
            return false;
        }

        $delivery_id_list = array();

        $delivery_list = $deliveryMdl->getDeliversByOrderId($order['order_id']);
        foreach ($delivery_list as $key => $value) {
            if (in_array($value['status'], array('ready', 'progress'))) {
                $delivery_id_list[] = $value['delivery_id'];
            }
        }

        if (!$delivery_id_list) {
            foreach ($this->_get_order_items($order['order_id'], $order['selfpickup_store_id']) as $store_code => $value) {

                $order['order_items'] = $value;

                list($rsp, $msg, $delivery_id) = $this->addDelivery($order, $store_code);

                if ($rsp === false){
                    $err_msg = $msg;return false;
                }

                $delivery_id_list[] = $delivery_id;
            }
        }

        if (!$delivery_id_list) {
            $err_msg = '订单已经发货';return false;
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
                $err_msg = '发货单发货失败:'.$consignRs['msg'];
                return false;
            }
        }

        //更新订单买家确认收货时间
        $oData = array(
            'end_time' => time(),
            'ziti_verify_status' => '2',
        );
        $orderMdl->update($oData, array("order_bn" => $order_bn));

        return true;
    }

    public function addDelivery($order, $store_code)
    {
        $brMdl       = app::get('ome')->model('branch');
        $logMdl      = app::get('ome')->model('operation_log');
        $deliveryMdl = app::get('ome')->model('delivery');
        $orderMdl    = app::get('ome')->model('orders');

        $branch = $brMdl->db_dump(array('branch_bn' => $store_code, 'check_permission' => 'false'), 'branch_id');
        $branch_id = $branch['branch_id'];

        if (!$branch_id) {
            $errmsg = '门店仓库不存在：'.$store_code;

            $logMdl->write_log('order_confirm@ome', $order['order_id'], $errmsg);

            return array(false, $errmsg);
        }

        $delivery = array(
            'branch_id'            => $branch_id,
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
            );
        }

        if (!$order_items) {
            $errmsg = '明细已删除';

            $logMdl->write_log('order_confirm@ome', $order['order_id'], $errmsg);

            return array(false, $errmsg);
        }

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

    private function _get_order_items($order_id, $store_id)
    {
        $objectMdl = app::get('ome')->model('order_objects');
        $itemMdl   = app::get('ome')->model('order_items');
        $storeMdl = app::get("o2o")->model("store");
        $storeInfo = $storeMdl->dump($store_id, "store_bn");
        $store_code = $storeInfo['store_bn'];
        $object_list = $item_list = array();
        foreach ($objectMdl->getList('*', array('order_id' => $order_id, 'delete' => 'false')) as $value) {
            $object_list[$value['obj_id']] = $value;
        }

        if (!$object_list) {
            return [];
        }

        foreach ($itemMdl->getList('*', array('order_id' => $order_id, 'obj_id' => array_column($object_list, 'obj_id'), 'delete' => 'false', 'filter_sql' => ' nums > split_num')) as $value) {
            if ($left_nums = $value['nums'] - $value['split_num']) {
                $value['nums'] = $left_nums;

                $item_list[$store_code][$value['item_id']] = $value;
            }
        }

        return $item_list;
    }

    /**
     * SAP/ESB 平台对象（未安装 SAP 模块时返回 null）
     */
    private function _getPlatformObj($shop_type)
    {
        if (in_array($shop_type, ['shopex_fy', 'ecos.ecshopx'])) {
            $class = 'ome_sap_data_platform_shopex_ecshopx';
        } else {
            $class = sprintf('ome_sap_data_platform_%s', $shop_type);
        }
        if (!class_exists($class)) {
            return null;
        }
        return kernel::single($class);
    }

}
