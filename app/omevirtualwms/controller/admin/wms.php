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

define('WAIT_TIME', 5); //finder中的数据多长时间重复展示(队列失败的情况)，单位 分
define('TIP_INFO', '部分回传'); //再次展示时的提示信息

class omevirtualwms_ctl_admin_wms extends desktop_controller
{

    public function __construct($app)
    {
        parent::__construct($app);
        $this->app    = $app;
        $this->objBhc = kernel::single('base_httpclient');
        $this->objBcf  = kernel::single('base_certificate');
        base_kvstore::instance('wms_config_test')->fetch('config', $config);
        $api_url       = kernel::base_url(1) . kernel::url_prefix() . '/api';
        $certificate   = base_shopnode::node_id('ome');
        $token         = base_certificate::token('ome');
        $this->api_url = $api_url; //api地址
        $this->token   = $token;
        //证书token
    }

    //用于显示展示页面
    public function index()
    {
        $this->page('show.html');
    }
    //显示入库，出库 发货 退货
    public function show2($flag = '')
    {

        $titles["stockin"]     = "入库单回传";
        $titles["stockout"]    = "出库单回传";
        $titles["delivery"]    = "发货单回传";
        $titles["reship"]      = "退货单回传";
        $titles["stockdump"]   = "转储单回传";
        $titles["vopstockout"] = "唯品会出库单回传";
        $titles["storeprocess"] = "加工单回传";

        $flag                   = isset($_GET['flag']) ? $_GET['flag'] : $flag;
        $this->title            = $titles[$flag];
        $base_filter['io_type'] = $flag;
        $params                 = array(
            'title'                 => $this->title,
            'use_buildin_recycle'   => false,
            // 'use_buildin_import'=>fasle,
            //'base_filter' => array('otype'=>$otype),
            'use_buildin_filter'    => false,
            'use_buildin_selectrow' => false,
            'use_buildin_export'    => false,
            'use_buildin_selectrow' => false,
            'base_filter'           => $base_filter,
        );

        $this->finder("omevirtualwms_mdl_" . $flag, $params);

    }

    public function callback()
    {
        $this->title = 'callback回传';
        $params      = array(
            'title'                 => $this->title,
            'use_buildin_recycle'   => true,
            'use_buildin_filter'    => true,
            'use_buildin_selectrow' => false,
            'use_buildin_export'    => false,
            'use_buildin_selectrow' => true,
            'orderBy'               => ' createtime DESC ',
        );
        $this->finder("omevirtualwms_mdl_callback", $params);
    }

    // 商品返回模拟页面
    public function callback_goods_html($msg_id, $finder_id)
    {
        $apilogModel = app::get('ome')->model('api_log');
        $log_detail  = $apilogModel->dump(array('msg_id' => $msg_id));
        $params      = unserialize($log_detail['params']);
        $items       = json_decode($params[1]['item_lists'], true);

        $this->pagedata['items']      = $items;
        $this->pagedata['log_detail'] = $log_detail;
        $this->pagedata['finder_id']  = $finder_id;
        $this->display('callback_goods.html');
    }

    // 异步返回模拟页面
    public function callback_html($msg_id, $finder_id)
    {
        $this->pagedata['msg_id']    = $msg_id;
        $this->pagedata['finder_id'] = $finder_id;
        $this->display('callback.html');
    }

    // 异步返回模拟处理
    public function callback_call($msg_id, $rsp = 'succ')
    {
        $callbackModel = app::get('omevirtualwms')->model('callback');
        $callback      = $callbackModel->dump(array('msg_id' => $msg_id));
        $url           = $callback['callback_url'];
        $core_http     = kernel::single('base_httpclient');
        $res           = $_POST['res'][0] ? $_POST['res'][0] : $_POST['err_msg'][0];
        $err_msg       = $_POST['err_msg'][0];
        $data          = array('wms_order_code' => $_POST['wms_order_code'][0]);
        $query_params  = array(
            'rsp'     => $rsp,
            'res'     => $res ? $res : $err_msg,
            'err_msg' => $err_msg,
            'data'    => json_encode($data),
            'msg_id'  => $msg_id,
            'node_id' => $callback['params']['to_node_id'],
        );
        $query_params['sign'] = $this->_gen_sign($query_params, $this->token);
        $callbacl_rs          = $core_http->post($url, $query_params);
        $rs                   = json_decode($callbacl_rs, true);
        $response             = array();
        if ($rs['rsp'] == 'success') {
            $response['rsp'] = 'succ';
            //删除任务
            $callbackModel->delete(array('msg_id' => $msg_id));
        } else {
            $response['rsp'] = 'fail';
        }
        $response['html'] = $callbacl_rs;
        die(json_encode($response));
    }

    // 商品异步返回模拟处理
    public function callback_goods_call($msg_id)
    {
        $this->begin('index.php?app=omevirtualwms&ctl=admin_wms&act=callback');
        $callbackModel = app::get('omevirtualwms')->model('callback');
        $callback      = $callbackModel->dump(array('msg_id' => $msg_id));
        $url           = $callback['callback_url'];
        $core_http     = kernel::single('base_httpclient');
        $res           = $_POST['res'] ? $_POST['res'] : $_POST['err_msg'];
        $rsp           = $_POST['rsp'];
        $err_msg       = $_POST['err_msg'];
        $wbn           = $_POST['wbn'];
        if ($_POST['bn']) {
            $data = array();
            foreach ($_POST['bn'] as $bn => $status) {
                if ($status == 'succ') {
                    $wms_item_code  = $wbn[$bn];
                    $data['succ'][] = array('item_code' => $bn, 'wms_item_code' => $wms_item_code);
                } else {
                    $data['error'][] = array('item_code' => $bn, 'error_code' => $status);
                }
            }
        }
        $query_params = array(
            'rsp'     => $rsp,
            'res'     => $res ? $res : $err_msg,
            'err_msg' => $err_msg,
            'data'    => json_encode($data),
            'msg_id'  => $msg_id,
        );
        $query_params['sign'] = $this->_gen_sign($query_params, $this->token);
        $callbacl_rs          = $core_http->post($url, $query_params);
        $rs                   = json_decode($callbacl_rs, true);
        if ($rs['rsp'] == 'succ') {
            $msg = '回传成功';
            //删除任务
            $callbackModel->delete(array('msg_id' => $msg_id));
        } else {
            $msg = serialize($callbacl_rs);
        }
        $this->end($rs['rsp'] == 'succ' ? true : false, $msg);
    }

    //显示所有回传菜单
    public function show($data = '')
    {
        $flag = in_array(trim($_GET['flag']), array('stockin', 'stockout', 'inventory', 'delivery', 'reship', 'account')) ? $_GET['flag'] : 'reship';
        $flag = empty($data) ? $flag : $data;
        if ($flag == 'inventory') {
            $wms = kernel::single('channel_func')->getWmsChannelList();
            if ($wms) {
                foreach ($wms as $k => $v) {
                    if ($v['adapter'] == 'selfwms') {
                        unset($wms[$k]);
                    }

                }
            }
            sort($wms);
            $mdl_wms_branch = app::get('ome')->model('branch');
            $branchs        = $mdl_wms_branch->getlist('branch_bn,name', array('wms_id' => $wms[0]['wms_id']));

            $this->pagedata['branchs']      = $branchs;
            $this->pagedata['wms']          = $wms;
            $this->pagedata['task']         = time();
            $this->pagedata['inventory_bn'] = 'P' . time();
            $this->pagedata['operate_time'] = date('Y-m-d H:i:s', time());
            $this->page('inventory.html');
        } elseif ($flag == 'account') {
            $wms = kernel::single('channel_func')->getWmsChannelList();
            if ($wms) {
                foreach ($wms as $k => $v) {
                    if ($v['adapter'] == 'selfwms') {
                        unset($wms[$k]);
                    }

                }
            }
            sort($wms);
            $mdl_wms_branch = app::get('ome')->model('branch');
            $branchs        = $mdl_wms_branch->getlist('branch_bn,name', array('wms_id' => $wms[0]['wms_id']));

            $this->pagedata['logistics']  = $logistics_arr;
            $this->pagedata['branchs']    = $branchs;
            $this->pagedata['wms']        = $wms;
            $this->pagedata['task']       = time();
            $this->pagedata['accoutn_bn'] = 'A' . time();
            $this->page('account.html');
        } else {
            $selflang = array(
                'stockin'   => array('title' => '入库单状态回传', 'input' => '入库编号', 'k' => 'stockin'),
                'stockout'  => array('title' => '出库单状态回传', 'input' => '出库编号', 'k' => 'stockout'),
                'stockdump' => array('title' => '转储单状态回传', 'input' => '转储编号', 'k' => 'stockdump'),
                'inventory' => array('title' => '盘点结果回传', 'input' => '申请盘点编号', 'k' => 'inventory'),
                'delivery'  => array('title' => '发货单状态回传', 'input' => '发货单号', 'k' => 'delivery'),
                'reship'    => array('title' => '退货单状态回传', 'input' => '退货单号', 'k' => 'reship'),
            );
            $this->pagedata['show'] = $selflang[$flag];
            $this->pagedata['t']    = isset($_GET['t']) && ($_GET['t'] == 1) ? '1' : '';
            $this->page('check.html');
        }
    }

    //处理提交过来的数据
    public function doSubmit($type_id = '')
    {
        ini_set('memory_limit','256M');

        $flag = $_POST['flag'];
        $show = ($flag == 'account' || $flag == 'inventory') ? 'show' : 'show2';
        if ($flag == 'iostock') {
            $url = "index.php?app=omevirtualwms&ctl=admin_wms&act=iostock";
        } else {
            $url = "index.php?app=omevirtualwms&ctl=admin_wms&act=$show&flag=$flag";
        }

        //取消出库单后返回的URL
        if ($flag == 'vopstockout') {
            $url = "index.php?app=omevirtualwms&ctl=admin_wms&act=show2&flag=vopstockout";
        }

        $this->begin($url);
        $method       = $this->_return_method($_POST['flag']);
        $params       = $this->_return_params($_POST, $type_id);
        $query_params = array(
            'method'  => $method,
            'date'    => '',
            'format'  => 'json',
            'node_id' => $_POST["node_id"],
            'app_id'  => 'ecos.ome',
            'task'    => time() . $_POST[$_POST['flag'] . '_bn'],
        );
        //
        $adapter_type = kernel::single('channel_func')->getAdapterByNodeId($_POST["node_id"]);
        $token        = '';
        if (in_array($adapter_type, array('matrixwms'))) {
            $token = $this->token;
        }
        if (in_array($adapter_type, array('openapiwms'))) {
            $channel_id      = kernel::single('channel_func')->getWmsIdByNodeId($_POST["node_id"]);
            $channel_adapter = app::get('channel')->model('adapter');

            $detail = $channel_adapter->getList('config', array('channel_id' => $channel_id), 0, 1);

            $config    = unserialize($detail[0]['config']);
            $node_type = $config['node_type'];
            if ($config['node_type'] != 'sku360') {
                $token = $config['private_key'];
            }
        }

        $query_params = array_merge((array) $params, $query_params);
        if ($node_type == 'sku360') {
            $timestamp                 = date('Y-m-d H:i:s');
            $query_params['timestamp'] = strtotime($timestamp);

            $query_params['sign'] = strtoupper(md5($config['owner'] . $config['appkey'] . $timestamp));
        } else {
            $query_params['sign'] = $this->_gen_sign($query_params, $token);
        }
        $rs = $this->objBhc->post($this->api_url, $query_params);

        $info = json_decode($rs, 1);

        //消息发送成功，插入数据状态表
        if ($info['rsp'] == 'succ') {
            // 操作员信息
            $op_id = kernel::single('desktop_user')->get_id();
            $op_name = kernel::single('desktop_user')->get_name();
            
            // data
            $data = array(
                'bn'          => $_POST[$_POST['flag'] . '_bn'],
                'type'        => $_POST['flag'],
                'status'      => $query_params['status'] ? $query_params['status'] : 'success',
                'op_id'       => $op_id,
                'op_name'     => $op_name,
                'create_time' => time(),
            );
            $this->app->model('dataStatus')->save($data);
        }
        //end
        $this->end($info['rsp'] == 'succ' ? true : false, $info['msg'] ? $info['msg'] : '回传成功');
    }

    //根据传来的参数获取相应的回传信息
    public function getinfo($bn, $method, $type_id='')
    {
        $_POST["bn"]   = $bn;
        $_POST["flag"] = $method;
        
        // 兼容 GET 请求：点击「开始模拟」链接时 i_type 在 URL 中（$_GET），需同步到 $_POST 供后续逻辑使用
        if (isset($_GET['i_type'])) {
            $_POST['i_type'] = $_GET['i_type'];
        }

        if (strtolower($method) == 'vopstockout') {
            $iostocktype = 'VOP';
        } else {
            $iostocktype = substr($_POST['bn'], 0, 1);
        }

        if ($method == 'stockin' && $iostocktype != 'I') {
            $iostocktype = 'E';
        }

        if ($method == 'stockout' && $iostocktype != 'H') {
            $iostocktype = 'A';
        }

        if ($type_id == '1') {
            $iostocktype = 'I';
        }
        
        if ($_POST['i_type'] == 'purchase_return') {
            $iostocktype = 'H';
        }

        switch ($iostocktype) {
            case 'VOP':
                $type = 'vopstockout';
                break;
            case 'I':
                $type = 'purchase';
                break;
            case 'H':
                $type = 'purchase_return';
                break;
            case 'T':
            case 'R':
            case 'A':
            case 'E':
            case 'F':
            case 'G':
            case 'J':
            case 'K':
            case 'D':
            case 'B':
            case 'X':
                $type = 'allocate';
                break;
            default:
                $type = $method;
        }
        $data = $this->_getinfo($_POST, $type);

        $data['task'] = time() . rand(0, 100);
        if (!empty($data)) {
            $this->pagedata['data'] = $data;
            $this->pagedata['type_id'] = $type_id;
            $this->pagedata['i_type'] = isset($_POST['i_type']) ? $_POST['i_type'] : '';
            $this->page($method . '.html');
        } else {
            $this->show($method);
            echo '没有找到相关信息';
        }

    }

    private function _getinfo($data, $type)
    {
        ini_set('memory_limit','256M');

        $db   = kernel::database();
        $info = array();
        switch ($type) {
            case 'vopstockout':
                //出库单
                $stockoutObj  = app::get('purchase')->model('pick_stockout_bills');
                $stockoutInfo = $stockoutObj->dump(array('stockout_no' => $data['bn'], 'status' => 1, 'confirm_status' => array(1, 2), 'o_status' => 1), '*');

                //仓库
                $objOmeBranch = app::get('ome')->model('branch');
                $branchInfo   = $objOmeBranch->dump(array('branch_id' => $stockoutInfo['branch_id']), 'branch_bn,name');

                //wms_id
                $wms_info = $this->getWmsInfo($stockoutInfo['branch_id']);

                $info       = array_merge($stockoutInfo, $branchInfo);
                $info['bn'] = $info['stockout_no'];

                $info['wms_name'] = $wms_info['wms_name'];
                $info['node_id']  = $wms_info['node_id'];

                //商品明细
                $stockoutItemsObj = app::get('purchase')->model('pick_stockout_bill_items');
                $tempData         = $stockoutItemsObj->getList('stockout_item_id,bn,product_name,barcode,num', array('stockout_id' => $stockoutInfo['stockout_id'], 'is_del' => 'false'));

                $productList = array();
                foreach ($tempData as $key => $val) {
                    $productList[$key] = $val;
                }

                $info['product']     = $productList;
                $info["time"]        = date("Y-m-d H:i:s");
                $info["demo"]        = '唯品会出库单回传';
                $info['items_count'] = count($productList);

                break;
            case 'purchase':
                $objStock = app::get('purchase')->model('po');
                $tmp      = $objStock->dump(array('po_bn' => $data['bn']), '*');
                if (!empty($tmp)) {
                    $objStockItem = app::get('purchase')->model('po_items');
                    $objOmeBranch = app::get('ome')->model('branch');
                    $tt           = $objOmeBranch->dump(array('branch_id' => $tmp['branch_id']), 'branch_bn,name');
                    $info         = array_merge($tmp, $tt);
                    $info['bn']   = $info['po_bn'];

                    $sql                 = "SELECT COUNT(*) AS _count FROM `sdb_purchase_po_items` WHERE po_id = '" . $tmp['po_id'] . "'";
                    $arr                 = $db->select($sql);
                    $info['items_count'] = $arr[0]['_count'];
                    if ($info['items_count'] < 3000) {
                        $sub = $objStockItem->getList('bn,name AS product_name,num,in_num AS normal_num,defective_num', array('po_id' => $tmp['po_id']));
                    }

                    $wms_info         = $this->getWmsInfo($tmp['branch_id']);
                    $info['wms_name'] = $wms_info['wms_name'];
                    $info['node_id']  = $wms_info['node_id'];

                    $info['product'] = $sub;
                    $info["time"]    = date("Y-m-d H:i:s");
                    $info["demo"]    = '采购单回传';
                    //echo "<pre>";print_r($info);exit;
                }
                break;
            case 'purchase_return':
                $objStock = app::get('purchase')->model('returned_purchase');
                $tmp      = $objStock->dump(array('rp_bn' => $data['bn']), '*');
                if (!empty($tmp)) {
                    $objStockItem = app::get('purchase')->model('returned_purchase_items');
                    $objOmeBranch = app::get('ome')->model('branch');
                    $tt           = $objOmeBranch->dump(array('branch_id' => $tmp['branch_id']), 'branch_bn,name');
                    $info         = array_merge($tmp, $tt);
                    $info['bn']   = $info['rp_bn'];

                    $counter             = $objStockItem->count(array('rp_id' => $tmp['rp_id']));
                    $info['items_count'] = $counter;
                    if ($info['items_count'] < 3000) {
                        $sub = $objStockItem->getList('bn,name AS product_name,num,out_num', array('rp_id' => $tmp['rp_id']));
                    }

                    $wms_info         = $this->getWmsInfo($tmp['branch_id']);
                    $info['wms_name'] = $wms_info['wms_name'];
                    $info['node_id']  = $wms_info['node_id'];

                    $info['product'] = $sub;
                    $info["time"]    = date("Y-m-d H:i:s");
                    $info["demo"]    = '采购退货单回传';
                    //echo "<pre>";print_r($info);exit;
                }
                break;
            case 'allocate':
                $objStock = app::get('taoguaniostockorder')->model('iso');
                $tmp      = $objStock->dump(array('iso_bn' => $data['bn']), '*');
                if (!empty($tmp)) {
                    $objStockItem = app::get('taoguaniostockorder')->model('iso_items');
                    $objOmeBranch = app::get('ome')->model('branch');
                    $tt           = $objOmeBranch->dump(array('branch_id' => $tmp['branch_id']), 'branch_bn,name');
                    $info         = array_merge((array)$tmp, (array)$tt);
                    $info['bn']   = $info['iso_bn'];

                    $counter             = $objStockItem->count(array('iso_id' => $tmp['iso_id']));
                    $info['items_count'] = $counter;
                    if ($info['items_count'] < 3000) {
                        $sub = $objStockItem->getList('bn,product_name,nums as num,normal_num,defective_num,normal_num AS out_num', array('iso_id' => $tmp['iso_id']));
                    }

                    $wms_info         = $this->getWmsInfo($tmp['branch_id']);
                    $info['wms_name'] = $wms_info['wms_name'];
                    $info['node_id']  = $wms_info['node_id'];

                    $info['product'] = $sub;
                    $info["time"]    = date("Y-m-d H:i:s");
                    $info["demo"]    = '调拨出入库单回传';
                }
                break;
            case 'stockdump':
                $objStock = app::get('console')->model('stockdump');
                $tmp      = $objStock->dump(array('stockdump_bn' => $data['bn']), '*');
                if (!empty($tmp)) {
                    $objStockItem = app::get('console')->model('stockdump_items');
                    $objOmeBranch = app::get('ome')->model('branch');
                    $tt           = $objOmeBranch->dump(array('branch_id' => $tmp['from_branch_id']), 'branch_bn,name');
                    $info         = array_merge((array)$tmp, (array)$tt);
                    $info['bn']   = $info['stockdump_bn'];

                    $counter             = $objStockItem->count(array('stockdump_id' => $tmp['stockdump_id']));
                    $info['items_count'] = $counter;
                    if ($info['items_count'] < 4000) {
                        $sub = $objStockItem->getList('*', array('stockdump_id' => $tmp['stockdump_id']));
                    }

                    $wms_info         = $this->getWmsInfo($tmp['from_branch_id']);
                    $info['wms_name'] = $wms_info['wms_name'];
                    $info['node_id']  = $wms_info['node_id'];

                    $info['product'] = $sub;
                    $info["time"]    = date("Y-m-d H:i:s");
                    $info["demo"]    = '转储单回传';
                }
                break;
            case 'inventory':
                //$info = $this->show_inventory($data['num']);
                $info = array('t' => 't');
                break;
            case 'delivery':
                $obj              = app::get('ome')->model('delivery');
                $delivery_bn      = $data['bn'];
                $data             = $obj->dump(array('delivery_bn' => $delivery_bn), '*');
                $tmp              = $this->_getinfo_delivery($data['delivery_id']);
                $info['product']  = $tmp['product'];
                $info['wuliu']    = $tmp['wuliu'];
                $info['branch']   = $tmp['branch'];
                $wms_info         = $this->getWmsInfo($tmp['branch']['branch_id']);
                $info['wms_name'] = $wms_info['wms_name'];
                $info['node_id']  = $wms_info['node_id'];

                foreach ($info['wuliu'] as &$v) {
                    $v['wms_code'] = kernel::single('wmsmgr_func')->getWmslogiCode($wms_info['wms_id'], $v['type']);
                    $v['wms_code'] = $v['wms_code'] ? $v['wms_code'] : $v['type'];
                }

                $info['delivery_bn'] = $delivery_bn;
                $info["time"]        = date("Y-m-d H:i:s");
                $info["demo"]        = "发货单回传";
                $info["logi"]        = '';
                break;
            case 'reship':
                $obj_Wuliu = app::get('ome')->model('dly_corp');
                $wuliu     = $obj_Wuliu->getlist('*', array());
                $obj       = app::get('ome')->model('reship');

                $data = $obj->dump(array('reship_bn' => $data['bn']), '*');

                $reship_id           = $data["reship_id"];
                $objReship           = app::get('ome')->model('reship_items');
                $sql                 = "SELECT COUNT(*) AS _count FROM `sdb_ome_reship_items` WHERE reship_id = '" . $reship_id . "'";
                $arr                 = $db->select($sql);
                $info['items_count'] = $arr[0]['_count'];
                if ($info['items_count'] < 3000) {
                    $sub = $objReship->getList('*', array('reship_id' => $reship_id, 'return_type' => array('return', 'refuse')));
                }
                $objBranch = app::get('ome')->model('branch');
                $branchs   = $objBranch->getList('*');
                $sql       = "SELECT  a.channel_name as wms_name,a.node_id,c.name,c.branch_bn from sdb_channel_channel a
                  left JOIN  sdb_ome_branch c on c.wms_id=a.channel_id LEFT
                  JOIN sdb_ome_reship d on d.branch_id=c.branch_id WHERE d.branch_id=" . $data['branch_id'];

                $arr = $db->selectrow($sql);

                $info             = $data;
                $info['wms_name'] = $arr['wms_name'];
                $info['node_id']  = $arr['node_id'];
                $info['product']  = $sub;
                $info['branchs']  = $branchs;
                $info["time"]     = date("Y-m-d H:i:s");
                $info["demo"]     = "退货单回传";
                $info['wuliu']    = $wuliu;
                $info["logi"]     = time() . rand(1000, 9999);
                $info['branch']   = $arr;
                break;
            case 'storeprocess':
                $obj              = app::get('console')->model('material_package');
                $mp_bn      = $data['bn'];
                $data             = $obj->dump(array('mp_bn' => $mp_bn), '*');
                $items = app::get('console')->model('material_package_items')->getList('*', ['mp_id'=>$data['id']]);
                $wms_info         = $this->getWmsInfo($data['branch_id']);
                $info = ['mp_bn'=>$mp_bn];
                $info['wms_name'] = $wms_info['wms_name'];
                $info['node_id']  = $wms_info['node_id'];
                $info['items'] = $items;
                $info["time"]        = date("Y-m-d H:i:s");
                $info["demo"]        = "加工单回传";
                break;
        }
        return $info;
    }

    private function _getinfo_delivery($delivery_id)
    {
        $data                = app::get('ome')->model('delivery')->dump($delivery_id);
        $branch_id           = $data["branch_id"];
        $objBranch           = app::get('ome')->model('branch');
        $return['branch']    = $objBranch->dump(array('branch_id' => $branch_id), '*');
        $objdelivery         = app::get('ome')->model('delivery_items');
        $sql                 = "SELECT COUNT(*) AS _count FROM `sdb_ome_delivery_items` WHERE delivery_id = '" . $delivery_id . "'";
        $arr                 = kernel::database()->select($sql);
        $info['items_count'] = $arr[0]['_count'];
        if ($info['items_count'] < 3000) {
            $return['product'] = $objdelivery->getList('*', array('delivery_id' => $delivery_id));
        }
        $obj_Wuliu       = app::get('ome')->model('dly_corp');
        $return['wuliu'] = $obj_Wuliu->getlist('*', array());
        return $return;
    }

    //重组并获取各个接口传递的参数
    private function _return_params($data, $type_id = '')
    {
        if ($data['flag'] == 'iostock') {
            $url = "index.php?app=omevirtualwms&ctl=admin_wms&act=iostock";
            if (empty($data['product'])) {
                $this->end(false, '数据不能为空');
            }
        }
        switch ($data['flag']) {
            //模拟出入库
            case 'iostock':
                $items = array();
                foreach ($data['product'] as $k => $value) {
                    $items[$k]['bn']    = $value['0'];
                    $items[$k]['nums']  = $value['1'];
                    $items[$k]['price'] = $value['2'];
                }
                $iostock_data = array(
                    'original_bn' => $data['original_bn'],
                    'type'        => $data['type'],
                    'warehouse'   => $data['warehouse'],
                    'supplier'    => $data['supplier'],
                    'iotime'      => $data['iotime'],
                    'oper'        => $data['oper'],
                    'memo'        => $data['memo'],
                    'items'       => $items,
                );
                return $iostock_data;
                break;
            case 'stockin':
                $params = array(
                    'stockin_bn'   => $data['stockin_bn'],
                    'warehouse'    => $data['warehouse'],
                    'status'       => $data['status'],
                    'remark'       => $data['remark'],
                    'operate_time' => $data['operate_time'] ?: time(),
                    'type'         => 'QTRK', // 其他入库
                );
                if ('I' == $params['stockin_bn'][0]) {
                    $params['type'] = 'CGRK';
                }

                if ($type_id == '1') {
                    $params['type'] = 'CGRK';
                }

                $item = array();
                if ($data['product']) {
                    foreach ($data['product'] as $k => $v) {
                        if (empty($v['0'])) {
                            continue;
                        }

                        $item[] = array(
                            'product_bn'    => $v['0'],
                            'normal_num'    => $v['1'],
                            'defective_num' => $v['2'],
                            'sn_list'    => $v[3] ? ['sn'=>explode(',', $v[3])] : []
                        );
                    }
                } else {
                    $this->err();
                }
                $params['item'] = json_encode($item);
                return $params;
                break;
            case 'stockout':
                $params = array(
                    'stockout_bn'  => $data['stockout_bn'],
                    'warehouse'    => $data['warehouse'],
                    'status'       => $data['status'],
                    'remark'       => $data['remark'],
                    'operate_time' => $data['operate_time'] ?: time(),
                    'type'         => 'QTCK',
                );
                if ('H' == $params['stockout_bn'][0] || $_POST['i_type'] == 'purchase_return') {
                    $params['type'] = 'CGTH';
                }
                $item = array();
                if ($data['product']) {
                    foreach ($data['product'] as $k => $v) {
                        if (empty($v['0'])) {
                            continue;
                        }

                        $item[] = array(
                            'product_bn' => $v['0'],
                            'num'        => $v['1'],
                            'sn_list'    => $v[2] ? ['sn'=>explode(',', $v[2])] : []
                        );
                    }
                } else {
                    $this->err();
                }
                $params['item'] = json_encode($item);
                return $params;
                break;
            case 'stockdump':
                $params = array(
                    'stockdump_bn' => $data['stockdump_bn'],
                    'warehouse'    => $data['warehouse'],
                    'status'       => $data['status'],
                    'remark'       => $data['remark'],
                    'operate_time' => $data['operate_time'] ? $data['operate_time'] : time(),
                );
                $item = array();
                if ($data['product']) {
                    foreach ($data['product'] as $k => $v) {
                        if (empty($v['0'])) {
                            continue;
                        }

                        $item[] = array(
                            'product_bn' => $v['0'],
                            'num'        => $v['1'],
                        );
                    }
                } else {
                    $this->err();
                }
                $params['items'] = json_encode($item);
                return $params;
                break;
            case 'delivery':
                $params = array(
                    'delivery_bn'  => $data['delivery_bn'],
                    'logistics'    => $data['logistics'],
                    'logi_no'      => $data['logi_no'],
                    'warehouse'    => '001', //$data['warehouse'],
                    'status'       => $data['status'],
                    'volume'       => $data['volume'],
                    'weight'       => $data['weight'],
                    'remark'       => $data['remark'],
                    'operate_time' => $data['operate_time'] ? $data['operate_time'] : time(),
                );
                $item = array();
                if ($data['product']) {
                    foreach ($data['product'] as $k => $v) {
                        if (empty($v['0'])) {
                            continue;
                        }

                        $item[] = array(
                            'product_bn' => $v['0'],
                            'num'        => $v['1'],
                            'sn_list'    => $v[2] ? ['sn'=>explode(',', $v[2])] : []
                        );
                    }
                } else {
                    //$this->err();
                }
                $params['item'] = json_encode($item);
                return $params;
                break;
            case 'reship':
                $params = array(
                    'reship_bn'    => $data['reship_bn'],
                    'logistics'    => $data['logistics'],
                    'logi_no'      => $data['logi_no'],
                    'warehouse'    => $data['warehouse'],
                    'status'       => $data['status'],
                    'remark'       => $data['remark'],
                    'operate_time' => $data['operate_time'] ? $data['operate_time'] : time(),
                );
                $item = array();
                if ($data['product']) {
                    foreach ($data['product'] as $k => $v) {
                        if (empty($v['0'])) {
                            continue;
                        }

                        $item[] = array(
                            'product_bn'    => $v['0'],
                            'normal_num'    => $v['1'],
                            'defective_num' => $v['2'],
                            'sn_list'    => $v[3] ? ['sn'=>explode(',', $v[3])] : []
                        );
                    }
                } else {
                    $this->err();
                }
                $params['item'] = json_encode($item);
                return $params;
                break;
            case 'inventory':
                $params = array(
                    'inventory_bn' => $data['inventory_bn'],
                    'warehouse'    => $data['warehouse'],
                    'node_id'      => $data['node_id'],
                    'remark'       => $data['remark'],
                    'operate_time' => $data['operate_time'] ? $data['operate_time'] : time(),
                );
                $item = array();
                if ($data['product']) {
                    foreach ($data['product'] as $k => $v) {
                        if (empty($v['0'])) {
                            continue;
                        }

                        $item[] = array(
                            'product_bn'    => $v['0'],
                            'normal_num'    => $v['1'],
                            'defective_num' => $v['2'],
                            'totalQty'      => $v['3'],
                        );
                    }
                } else {
                    $this->err();
                }
                $params['item'] = json_encode($item);
                return $params;
                break;
            case 'account':
                $items = array();
                if ($data['product']) {
                    foreach ($data['product'] as $k => $pdu) {
                        if ($pdu[0] && $data['num'][$k][0]) {
                            $_item = array('batch' => $data['batch'], 'warehouse' => $data['warehouse'],
                                'order_code'            => $data['order_code'],
                                'product_bn'           => $pdu[0]);

                            if ($data['type'] == 'true') {
                                $_item['normal_num']    = $data['num'][$k][0];
                                $_item['defective_num'] = 0;
                            } elseif ($data['type'] == 'false') {
                                $_item['normal_num']    = 0;
                                $_item['defective_num'] = $data['num'][$k][0];
                            }
                            $items[] = $_item;
                        }
                    }
                } else {
                    $this->err();
                }
                //if(!$items) die('请选择商品');
                $params['item'] = json_encode($items);
                return $params;
                break;
            case 'vopstockout':
                $params = array(
                    'stockout_bn'  => $data['stockout_bn'],
                    'warehouse'    => $data['warehouse'],
                    'status'       => $data['status'],
                    'remark'       => $data['remark'],
                    'operate_time' => $data['operate_time'] ? $data['operate_time'] : time(),
                );
                $params['item'] = '';

                return $params;
                break;
            case 'storeprocess':
                $mpRow = app::get('console')->model('material_package')->db_dump(['mp_bn'=>$data['storeprocess_bn']], 'id,service_type');
                $mpid = $mpRow['id'];
                $service_type = $mpRow['service_type'];
                $materialitems = [];
                foreach(app::get('console')->model('material_package_items')->getList('*', ['mp_id'=>$mpid]) as $v) {
                    $materialitems[] = [
                        'itemCode' => $v['bm_bn'],
                        'quantity' => $v['number'],
                    ];
                }
                $productitems = [];
                foreach(app::get('console')->model('material_package_items_detail')->getList('*', ['mp_id'=>$mpid]) as $v) {
                    $productitems[] = [
                        'itemCode' => $v['bm_bn'],
                        'quantity' => $v['number'],
                    ];
                }
                $params = array(
                    'processOrderCode'  => $data['storeprocess_bn'],
                    'materialitems'     => json_encode(['item'=>$materialitems]),
                    'productitems'      => json_encode(['item'=>$productitems]),
                );
                // 如果是service_type=2（仓内组合拆分），调换materialitems和productitems
                if($service_type == '2') {
                    $params['materialitems'] = json_encode(['item'=>$productitems]);
                    $params['productitems'] = json_encode(['item'=>$materialitems]);
                }
                return $params;
                break;
        }

    }

    public function err()
    {
        $err = <<<EOF
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<h3>请添加商品！</h3>
<a href="javascript:history.go(-1);">返回</a>
<style>
</style>
EOF;
        echo $err;
        exit;
    }

    private function getWmsInfo($branch_id)
    {
        $wms_id   = kernel::single('ome_branch')->getWmsIdById($branch_id);
        $node_id  = kernel::single('channel_func')->getNodeIdByChannelId($wms_id);
        $wms_name = kernel::single('channel_func')->getChannelNameById($wms_id);
        $wms_bn   = kernel::single('channel_func')->getWmsBnByWmsId($wms_id);
        if(!$node_id){
            $channel_adapter = app::get('channel')->model('channel');
            $detail = $channel_adapter->db->selectrow("SELECT node_id,channel_id FROM sdb_channel_channel  where channel_type='wms' and node_id!='' and node_id!='selfwms'");
            $node_id = $detail['node_id'];
            $wms_id = $detail['channel_id'];
  
        }
        return array('wms_id' => $wms_id, 'node_id' => $node_id, 'wms_name' => $wms_name, 'wms_bn' => $wms_bn);
    }

    //获取各个接口的方法名称
    private function _return_method($flag)
    {
        switch ($flag) {
            case 'stockin':
                return 'wms.stockin.status_update';
                break;
            case 'stockout':
                return 'wms.stockout.status_update';
                break;
            case 'stockdump':
                return 'wms.stockdump.status_update';
                break;
            case 'inventory':
                return 'wms.inventory.add';
                break;
            case 'delivery':
                return 'wms.delivery.status_update';
                break;
            case 'reship':
                return 'wms.reship.status_update';
                break;
            case 'account':
                return 'wms.stock.quantity';
                break;
            //模拟出入库
            case 'iostock':
                return 'wms.iostock.add';
                break;
            case 'vopstockout':
                return 'wms.stockout.status_update'; //唯品会JIT出库
                break;
            case 'storeprocess':
                return 'wms.storeprocess.status_update';
                break;
        }
    }

    private function _assemble($params)
    {
        if (!is_array($params)) {
            return null;
        }

        ksort($params, SORT_STRING);
        $sign = '';
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }

            if (is_bool($val)) {
                $val = ($val) ? 1 : 0;
            }

            $sign .= $key . (is_array($val) ? self::_assemble($val) : $val);
        }
        return $sign;
    }
    private function _gen_sign($params, $token)
    {
        return strtoupper(md5(strtoupper(md5($this->_assemble($params))) . $token));
    }

    public function config()
    {

        base_kvstore::instance('wms_config_test')->fetch('config', $config);
        if ($_POST) {
            if ($_POST['api_url'] && $_POST['certificate'] && $_POST['token']) {
                $api_url     = $_POST['api_url'];
                $certificate = $_POST['certificate'];
                $token       = $_POST['token'];

            } else {
                if ($config && $config = unserialize($config)) {
                    $api_url     = $config['api_url'];
                    $certificate = $config['certificate'];
                    $token       = $config['token'];
                } else {
                    $api_url     = kernel::base_url(1) . kernel::url_prefix() . '/api';
                    $certificate = base_shopnode::node_id('ome');
                    $token       = base_shopnode::token('ome');
                }
            }
        } else {
            if ($config && $config = unserialize($config)) {
                $api_url     = $config['api_url'];
                $certificate = $config['certificate'];
                $token       = $config['token'];
            } else {
                $api_url     = kernel::base_url(1) . kernel::url_prefix() . '/api';
                $certificate = base_shopnode::node_id('ome');
                $token       = base_shopnode::token('ome');
            }
        }

        $config = array('api_url' => $api_url, 'certificate' => $certificate, 'token' => $token);

        base_kvstore::instance('wms_config_test')->store('config', serialize($config));
        $this->pagedata['api_url']     = $api_url;
        $this->pagedata['certificate'] = $certificate;
        $this->pagedata['token']       = $token;
        $this->page('config.html');
    }

    public function callback_config()
    {
        if ($_POST) {
            $callback = $_POST['callback'];
            base_kvstore::instance('omevirtualwms')->store('callback', $callback);
            #矩阵请求地址变更
            $networkModel = app::get('base')->model('network');
            if ($callback['status'] == '0') {
#内网
                #异步
                $update_sdf = array('node_url' => 'http://rpc.ex-sandbox.com');
                $filter     = array('node_id' => '1');
                $networkModel->update($update_sdf, $filter);
            } elseif ($callback['status'] == '2') {
#外网
                #异步
                $update_sdf = array('node_url' => MATRIX_URL); //'http://matrix.ecos.shopex.cn');
                $filter     = array('node_id' => '1');
                $networkModel->update($update_sdf, $filter);
            } else {
#模拟
                $update_sdf = array('node_url' => kernel::base_url(1) . '/index.php/openapi/virtualwms/call');
                $filter     = array('link_status' => 'active');
                $networkModel->update($update_sdf, $filter);
            }
        }
        base_kvstore::instance('omevirtualwms')->fetch('callback', $callback);
        $this->pagedata['callback'] = $callback;
        $this->page('callback_config.html');
    }

    public function show_inventory($inventory_num)
    {

        $mdl_apply      = app::get('omestorage')->model('inventory_apply');
        $mdl_apply_item = app::get('omestorage')->model('inventory_apply_items');

        //申请盘点信息
        $apply_info = $mdl_apply->dump(array('inventory_apply_bn' => $inventory_num), '*');
        if ($apply_info) {
            //申请盘点商品信息
            $apply_items           = $mdl_apply_item->getList('*', array('inventory_apply_id' => $apply_info['inventory_apply_id']));
            $apply_info['product'] = $apply_items;
        }
        return $apply_info;

    }

    public function back_branch()
    {
        $tt = '<select name="warehouse">';
        if (trim($_POST['node_id'])) {
            //$objWms =  app::get('omewms')->model('wms');
            //$wms =$objWms->getlist('wms_id',array('node_id'=>$_POST['node_id']));
            $wms_id = kernel::single('channel_func')->getWmsIdByNodeId($_POST['node_id']);
            //print_r($wms);

            $branch  = app::get('ome')->model('branch');
            $branchs = $branch->getlist('branch_bn,name', array('wms_id' => $wms_id));
            if ($branchs) {
                foreach ($branchs as $branch) {
                    $tt .= '<option value=\'' . $branch['branch_bn'] . '\'>' . $branch['name'] . '</option>';
                }
            }
        }
        $tt .= '</select>';
        echo $tt;
        exit;
    }
    public function get_stock()
    {
        $basicMaterialLib = kernel::single('material_basic_material');
        $product          = $basicMaterialLib->getMaterialStockByBn($_POST['product_bn']);

        if ($product) {
            echo $product['store'];
        } else {
            echo 'null';
        }
        exit;
    }

    //显示所有同步中的商品列表
    public function show_goods()
    {
        $titles["goods"] = "商品同步状态模拟回传";
        $this->title     = $titles["goods"];
        $params          = array(
            'title'                 => $this->title,
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_selectrow' => false,
            'use_buildin_export'    => false,
            'use_buildin_selectrow' => true,
            'use_buildin_filter'    => true,
            'use_view_tab'          => true,
        );

        $this->finder("omevirtualwms_mdl_goods", $params);

    }

    public function goods($log_id)
    {
        $this->begin('javascript:finderGroup["finderGroup["' . $_GET['finder_id'] . '"].refresh();');
        $callback_params['log_id'] = $log_id;
        $response                  = array('rsp' => 'succ', 'res' => '');
        $resultObj                 = kernel::single('ome_rpc_result', $response);
        $resultObj->set_callback_params($callback_params);

        $goodsObj = new omewmsmatrix_rpc_request_goods();
        $goodsObj->add_callback($resultObj);
        $this->end(true, '模拟成功', 'index.php?app=omevirtualwms&ctl=admin_wms&act=show_goods');
    }

    //模拟直连WMS 推送交易流水单据
    public function iostock()
    {
        $iotypeObj                      = app::get('ome')->model('iostock_type');
        $branchObj                      = app::get('ome')->model('branch');
        $type                           = $iotypeObj->getList();
        $branch                         = $branchObj->getList();
        $operate_time                   = date('Y/m/d H:i:s', time());
        $this->pagedata['types']        = $type;
        $this->pagedata['operate_time'] = $operate_time;
        $this->pagedata['branchs']      = $branch;
        $this->page('iostock.html');
    }
}
