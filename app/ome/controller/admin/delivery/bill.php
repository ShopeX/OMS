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

class ome_ctl_admin_delivery_bill extends desktop_controller
{
    public $name = "包裹中心";
    public $workground = "logistics_center";

    /**
     * 包裹列表页面
     */
    public function index()
    {
        $this->title = '包裹中心';

        $params = array(
            'title'                  => $this->title,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => true,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_view_tab'           => true,
            'orderBy'                => 'log_id desc',
            'base_filter'            => [],
            'actions'                => [
                [
                    'label'  => '异常查询下载',
                    'href'   => $this->url . '&act=exceptionQueryPage',
                    'target' => 'dialog::{width:600,height:500,title:\'异常查询下载\'}',
                ],
                [
                    'label'  => '预警配置查询',
                    'href'   => $this->url . '&act=exceptionConfigQueryPage',
                    'target' => 'dialog::{width:800,height:600,title:\'预警配置查询\'}',
                ],
            ],

        );

        $this->finder('ome_mdl_delivery_bill', $params);
    }



    /**
     * 包裹列表视图定义
     */
    function _views(){
        $base_filter = array();
        
        $sub_menu = array(
            0 => array('label'=>app::get('ome')->_('全部'),'filter'=>array(),'optional'=>false),
            1 => array('label'=>app::get('ome')->_('揽收异常'),'filter'=>array('exception_code'=>'GOT_EXCEPTION'),'optional'=>false),
            2 => array('label'=>app::get('ome')->_('揽收更新异常'),'filter'=>array('exception_code'=>'GOT_UPDATE_EXCEPTION'),'optional'=>false),
            3 => array('label'=>app::get('ome')->_('运输派送异常'),'filter'=>array('exception_code'=>'TRANSPORT_EXCEPTION'),'optional'=>false),
            4 => array('label'=>app::get('ome')->_('派送更新异常'),'filter'=>array('exception_code'=>'DELIVERY_UPDATE_EXCEPTION'),'optional'=>false),
        );

        $deliveryBillModel = app::get('ome')->model('delivery_bill');
        
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }
            
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            // 「全部」无条件 count 会全表扫 700万+，用 showtab 显示页签但不展示数字；异常页签仍按索引条件 count
            if (empty($v['filter'])) {
                $sub_menu[$k]['addon'] = 'showtab';
            } else {
                $sub_menu[$k]['addon'] = $deliveryBillModel->count($v['filter']);
            }
            $sub_menu[$k]['href'] = $this->url.'&act='.$_GET['act'].'&view='.$k;
        }

        return $sub_menu;
    }



    /**
     * 异常查询下载页面
     */
    public function exceptionQueryPage()
    {
        // 获取符合条件的店铺列表
        $shopModel = app::get('ome')->model('shop');
        $shops = $shopModel->getList('shop_id,shop_bn,name', [
            's_type' => 1,
            'node_type' => 'taobao',
            'filter_sql' => 'node_id is not null and node_id != ""',
        ]);

        $this->pagedata['shops'] = $shops;
        $this->pagedata['time_from'] = date('Y-m-d', strtotime('last month'));
        $this->pagedata['time_to'] = date('Y-m-d');

        $this->display('admin/delivery/bill/exception_query_page.html');
    }



    /**
     * 执行异常查询下载
     */
    public function doExceptionQuery()
    {
        $post = $_POST; 
        $pageNo = $_GET['pageNo'] ?? 1;

        $filter = array_filter($post);

        // 验证必填字段
        if (empty($filter['shop_id'])) {
            $this->splash('error', null, '请选择店铺');
        }
        
        if (empty($filter['exception_code'])) {
            $this->splash('error', null, '请选择一级异常类型');
        }

        // 构建请求参数
        $params = [
            'filter' => $filter,
            'page_no' => $pageNo,
            'page_size' => 50,
        ];

        // 调用库方法进行异常查询和更新
        $result = kernel::single('ome_delivery_bill')->queryExceptionAndUpdate($params, $filter['shop_id']);

        if ($result['rsp'] == 'fail') {
            $this->splash('error', null, $result['msg']);
        }

        $this->splash('success', null, $result['msg']);
    }

    /**
     * 异常查询下载Promise页面
     */
    public function exceptionQueryPromise()
    {
        $post = $_POST;  
        unset($post['baseApiUrl'], $post['_DTYPE_DATE']);

        $inputhtml = '';

        foreach ($post as $key => $value) {
            $params = array(
                'type' => 'hidden',
                'name' => $key,
                'value' => $value,
            );

            $inputhtml .= utils::buildTag($params, 'input');
        }

        $this->pagedata['inputhtml'] = $inputhtml;

        $filter = array_filter($post);

        // 验证必填字段
        if (empty($filter['shop_id'])) {
            $this->splash('error', null, '请选择店铺');
        }
        
        if (empty($filter['exception_code'])) {
            $this->splash('error', null, '请选择一级异常类型');
        }

        // 调用ERPAPI查询异常包裹
        $result = kernel::single('erpapi_router_request')->set('shop', $filter['shop_id'])->logistics_exception_query($filter);
        
        if ($result['rsp'] == 'fail') {
            $this->splash('error', null, $result['msg']);
        }

        $totalCount = $result['total_num'] ?? 0;
        $customLimit = $filter['page_size'] ?? 50; // 从filter中获取每页数量，默认50条

        parent::dialog_promise($totalCount, $customLimit);
    }

    /**
     * 预警配置查询页面
     */
    public function exceptionConfigQueryPage()
    {
        // 获取符合条件的店铺列表
        $shopModel = app::get('ome')->model('shop');
        $shops = $shopModel->getList('shop_id,shop_bn,name', [
            's_type' => 1,
            'node_type' => 'taobao',
            'filter_sql' => 'node_id is not null and node_id != ""',
        ]);

        $this->pagedata['shops'] = $shops;
        $this->display('admin/delivery/bill/exception_config_query_page.html');
    }

    /**
     * 执行预警配置查询
     */
    public function doExceptionConfigQuery()
    {
        try {
            $shop_id = $_POST['shop_id'] ?? '';
            
            if (empty($shop_id)) {
                $this->splash('error', null, '请选择店铺');
                return;
            }
            
            // 调用ERPAPI查询预警配置
            $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->logistics_exception_config_query([
                'shop_id' => $shop_id,
                'request_id' => $shop_id // 使用shop_id作为request_id，用于幂等性控制
            ]);
            
                       if ($result['rsp'] == 'succ') {
               $config_data = $result['data'] ?? [];
               
               // 定义配置项的中文名称和说明
               $configLabels = array(
                   'transporting_stop_warn_value' => array(
                       'name' => '运输停滞预警值',
                       'desc' => '被判断为"运输停滞"X前计入"即将超时"',
                       'unit' => '小时'
                   ),
                   'consign_fake_warn_value' => array(
                       'name' => '虚假发货预警值',
                       'desc' => '被判断为"虚假发货"X前计入"即将虚假发货"',
                       'unit' => '小时'
                   ),
                   'consign_delay_warn_value' => array(
                       'name' => '延迟发货预警值',
                       'desc' => '延迟发货前X小时计入"即将延迟"',
                       'unit' => '小时'
                   ),
                   'collected_stop_warn_value' => array(
                       'name' => '揽收后停滞预警值',
                       'desc' => '被判断为"揽收后停滞"X前计入"即将超时"',
                       'unit' => '小时'
                   ),
                   'deliverying_stop_warn_value' => array(
                       'name' => '派签停滞预警值',
                       'desc' => '被判断为"派签停滞"X前计入"即将超时"',
                       'unit' => '小时'
                   )
               );
               
               $this->pagedata['config_data'] = $config_data;
               $this->pagedata['config_labels'] = $configLabels;
               $this->display('admin/delivery/bill/exception_config_result.html');
           } else {
                $this->splash('error', null, '查询失败：' . ($result['msg'] ?? '未知错误'));
            }
            
        } catch (Exception $e) {
            $this->splash('error', null, '查询异常：' . $e->getMessage());
        }
    }

    /**
     * 补发建议单确认弹窗：展示包裹明细后再提交（与 finder 展开「包裹明细」数据源一致）
     */
    public function suggestReshippingDialog()
    {
        $log_id = (int) ($_GET['log_id'] ?? 0);
        $data = $this->_loadDeliveryBillPackagesForSuggestDialog($log_id);
        $this->pagedata['delivery_bill'] = $data['delivery_bill'];
        $this->pagedata['packages'] = $data['packages'];
        $this->pagedata['error_msg'] = $data['error_msg'];
        $this->pagedata['can_submit'] = $data['can_submit'];

        $submitUrl = 'index.php?app=ome&ctl=admin_delivery_bill&act=doSuggestReshipping&log_id=' . $log_id;
        if (!empty($_GET['finder_id'])) {
            $submitUrl .= '&finder_id=' . rawurlencode($_GET['finder_id']);
        }
        $this->pagedata['submit_url'] = $submitUrl;

        $this->display('admin/delivery/bill/suggest_reshipping_dialog.html');
    }

    /**
     * @param int $log_id
     * @return array delivery_bill, packages, error_msg, can_submit
     */
    private function _loadDeliveryBillPackagesForSuggestDialog($log_id)
    {
        $log_id = (int) $log_id;
        if ($log_id <= 0) {
            return array(
                'delivery_bill' => array(),
                'packages' => array(),
                'error_msg' => '无效的包裹记录',
                'can_submit' => false,
            );
        }

        kernel::single('ome_return_reshipping')->supplementOrderIdsForDeliveryBillSuggest($log_id);
        $bill = app::get('ome')->model('delivery_bill')->dump($log_id);
        if (empty($bill['log_id'])) {
            return array(
                'delivery_bill' => array(),
                'packages' => array(),
                'error_msg' => '包裹记录不存在',
                'can_submit' => false,
            );
        }
        $reshipLib = kernel::single('ome_return_reshipping');
        $blockReason = $reshipLib->getDeliveryBillSuggestBlockReason($bill);
        if ($blockReason !== null) {
            return array(
                'delivery_bill' => $bill,
                'packages' => array(),
                'error_msg' => $blockReason,
                'can_submit' => false,
            );
        }

        $delivery_package_model = app::get('ome')->model('delivery_package');
        $packages = $delivery_package_model->getList('*', array(
            'delivery_id' => $bill['delivery_id'],
            'logi_no' => $bill['logi_no'],
        ), 0, -1, 'package_id ASC');

        $reshipLib = kernel::single('ome_return_reshipping');
        $payCheck = $reshipLib->decorateDeliveryPackagesPayStatusFromOrderObjects($packages);
        if (!$payCheck['ok']) {
            $packages = kernel::single('console_delivery')->attachPackageStatusLabelsForView($packages);
            return array(
                'delivery_bill' => $bill,
                'packages' => $packages,
                'error_msg' => $payCheck['msg'],
                'can_submit' => false,
            );
        }

        $eligibleCount = $reshipLib->countPackagesEligibleForSuggestReshipping($packages);
        $error_msg = '';
        if ($eligibleCount <= 0) {
            $error_msg = '包裹明细关联子单均为全额退款，无法生成补发建议单';
        } elseif ($eligibleCount < count($packages)) {
            $error_msg = '提示：子订单支付状态为「全额退款」的明细不会写入补发建议单';
        }

        $orderBnMap = array();
        $collectIds = array();
        foreach ($packages as $pk) {
            if (empty($pk['order_id'])) {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', trim((string) $pk['order_id']), -1, PREG_SPLIT_NO_EMPTY) as $oid) {
                $oid = (int) $oid;
                if ($oid > 0) {
                    $collectIds[$oid] = true;
                }
            }
        }
        if ($collectIds) {
            $orderRows = app::get('ome')->model('orders')->getList('order_id,order_bn', array(
                'order_id|in' => array_keys($collectIds),
            ));
            foreach ($orderRows as $or) {
                $orderBnMap[(int) $or['order_id']] = $or['order_bn'];
            }
        }
        foreach ($packages as $k => $pk) {
            $packages[$k]['order_bn_display'] = $this->_orderIdsFieldToOrderBnDisplay(
                isset($pk['order_id']) ? $pk['order_id'] : '',
                $orderBnMap
            );
        }

        $packages = kernel::single('console_delivery')->attachPackageStatusLabelsForView($packages);

        return array(
            'delivery_bill' => $bill,
            'packages' => $packages,
            'error_msg' => $error_msg,
            'can_submit' => $eligibleCount > 0,
        );
    }

    /**
     * delivery_package.order_id 可能为逗号分隔，转为 order_bn 展示
     *
     * @param string $orderIdField
     * @param array  $orderBnMap
     * @return string
     */
    private function _orderIdsFieldToOrderBnDisplay($orderIdField, array $orderBnMap)
    {
        if ($orderIdField === '' || $orderIdField === null) {
            return '-';
        }
        $bns = array();
        foreach (preg_split('/\s*,\s*/', trim((string) $orderIdField), -1, PREG_SPLIT_NO_EMPTY) as $oid) {
            $oid = (int) $oid;
            if ($oid <= 0) {
                continue;
            }
            $bns[] = isset($orderBnMap[$oid]) ? $orderBnMap[$oid] : (string) $oid;
        }
        if (empty($bns)) {
            return '-';
        }
        return implode(',', $bns);
    }

    /**
     * 执行创建补发建议单（补寄申请）
     * 列表上通过 W.page(GET) 调用，需返回 splash(text/jcmd)，与 refund_apply、reship 等 finder 一致
     */
    public function doSuggestReshipping()
    {
        $log_id = (int) ($_POST['log_id'] ?? $_GET['log_id'] ?? 0);
        if ($log_id <= 0) {
            $this->splash('error', null, '参数错误：log_id 不能为空');
            return;
        }

        kernel::single('ome_return_reshipping')->supplementOrderIdsForDeliveryBillSuggest($log_id);
        $billPre = app::get('ome')->model('delivery_bill')->dump($log_id);
        $reshipLib = kernel::single('ome_return_reshipping');
        if (empty($billPre['log_id'])) {
            $this->splash('error', null, '包裹记录不存在');
            return;
        }
        $blockReason = $reshipLib->getDeliveryBillSuggestBlockReason($billPre);
        if ($blockReason !== null) {
            $this->splash('error', null, $blockReason);
            return;
        }

        // 创建补发建议单前校验：包裹明细 bn 必须能映射到 sales_material_type=1 的销售物料
        $bnCheck = $this->_validateSuggestReshippingBn($log_id);
        if (!empty($bnCheck['errors'])) {
            $this->splash('error', null, implode('；', $bnCheck['errors']));
            return;
        }

        $result = kernel::single('ome_return_reshipping')->runSuggestReshippingFromDeliveryBill($log_id);
        $msg = isset($result['msg']) ? $result['msg'] : '操作失败';
        if (isset($result['rsp']) && $result['rsp'] === 'succ') {
            $this->splash('success', null, $msg);
        } else {
            $this->splash('error', null, $msg);
        }
    }

    /**
     * @param int $log_id sdb_ome_delivery_bill.log_id
     * @return array{errors: array<int,string>}
     */
    private function _validateSuggestReshippingBn($log_id)
    {
        $log_id = (int) $log_id;
        $errors = array();

        $bill = app::get('ome')->model('delivery_bill')->dump($log_id);
        if (empty($bill) || empty($bill['delivery_id']) || empty($bill['logi_no'])) {
            return array('errors' => array('包裹记录不存在或缺少发货单/运单信息'));
        }

        $packages = app::get('ome')->model('delivery_package')->getList('*', array(
            'delivery_id' => (int) $bill['delivery_id'],
            'logi_no' => $bill['logi_no'],
        ), 0, -1, 'package_id ASC');
        if (empty($packages)) {
            return array('errors' => array('未找到包裹明细，无法创建补发建议单'));
        }

        $reshipLib = kernel::single('ome_return_reshipping');
        $payCheck = $reshipLib->decorateDeliveryPackagesPayStatusFromOrderObjects($packages);
        if (!$payCheck['ok']) {
            return array('errors' => array($payCheck['msg']));
        }
        $packages = $reshipLib->filterPackagesEligibleForSuggestReshipping($packages);
        if (empty($packages)) {
            return array('errors' => array('包裹明细均为全额退款子单，无法创建补发建议单'));
        }

        $bns = array();
        foreach ($packages as $pk) {
            $bn = isset($pk['bn']) ? trim((string) $pk['bn']) : '';
            if ($bn !== '') {
                $bns[$bn] = true;
            }
        }
        if (empty($bns)) {
            return array('errors' => array('包裹明细无有效 bn，无法创建补发建议单'));
        }

        $db = kernel::database();
        foreach (array_keys($bns) as $bn) {
            $bnQ = $db->quote($bn);
            // basic_material(bn) -> sales_basic_material(bm_id) -> sales_material(sm_id,type=1)
            $sql = 'SELECT sm.sm_id FROM sdb_material_basic_material AS bm'
                . ' INNER JOIN sdb_material_sales_basic_material AS sbm ON sbm.bm_id=bm.bm_id'
                . ' INNER JOIN sdb_material_sales_material AS sm ON sm.sm_id=sbm.sm_id AND sm.sales_material_type=1'
                . ' WHERE bm.material_bn=' . $bnQ;
            $row = $db->selectrow($sql);
            if (empty($row) || empty($row['sm_id'])) {
                $errors[] = 'bn物料不存在或未维护销售物料(type=1)：' . $bn;
            }
        }

        return array('errors' => $errors);
    }

} 