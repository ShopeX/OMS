<?php
class ome_ctl_admin_return_reshipping extends desktop_controller
{
    var $name = "补寄申请";
    var $workground = "aftersale_center";

    /**
     * 补寄申请单列表页面
     */
    public function index()
    {
        $this->title = '补寄申请单';

        $params = array(
            'title'                  => $this->title,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => true,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_view_tab'           => true,
            'base_filter'            => array(),
            'orderBy'                => 'reshipping_id DESC',
            'actions'                => array(
                array(
                    'label'  => '单拉补寄申请单',
                    'href'   => $this->url . '&act=pullDialog',
                    'target' => 'dialog::{width:500,height:300,title:\'单拉补寄申请单\'}',
                ),
            ),
        );

        $this->finder('ome_mdl_return_reshipping', $params);
    }

    /**
     * 列表视图定义（按状态筛选）
     */
    function _views()
    {
        $reshippingModel = app::get('ome')->model('return_reshipping');
        
        $sub_menu = array(
            0 => array('label' => app::get('ome')->_('全部'), 'filter' => array(), 'optional' => false),
            1 => array('label' => app::get('ome')->_('补寄待处理'), 'filter' => array('status' => '0'), 'optional' => false),
            2 => array('label' => app::get('ome')->_('等待卖家发货'), 'filter' => array('status' => '1'), 'optional' => false),
            3 => array('label' => app::get('ome')->_('等待买家收货'), 'filter' => array('status' => '2'), 'optional' => false),
            4 => array('label' => app::get('ome')->_('补寄成功'), 'filter' => array('status' => '3'), 'optional' => false),
            5 => array('label' => app::get('ome')->_('卖家拒绝补寄'), 'filter' => array('status' => '4'), 'optional' => false),
            6 => array('label' => app::get('ome')->_('补寄关闭'), 'filter' => array('status' => '5'), 'optional' => false),
            7 => array('label' => app::get('ome')->_('转退款'), 'filter' => array('status' => '6'), 'optional' => false),
        );

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $reshippingModel->viewcount($v['filter']);
            $sub_menu[$k]['href'] = $this->url . '&act=' . $_GET['act'] . '&view=' . $k;
        }

        return $sub_menu;
    }
    
    /**
     * 补寄申请单详情页面
     */
    public function detail()
    {
        $reshipping_id = $_GET['id'];
        if (empty($reshipping_id)) {
            $this->splash('error', '', '补寄申请单ID不能为空');
            return;
        }
        
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $reshippingItemsModel = app::get('ome')->model('return_reshipping_items');
        $orderModel = app::get('ome')->model('orders');
        
        // 获取补寄申请单基本信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            $this->splash('error', '', '补寄申请单不存在');
            return;
        }
        
        $reshipping['buyer_address'] = $reshipping['buyer_province'] . $reshipping['buyer_city'] . $reshipping['buyer_district'] . $reshipping['buyer_town'] . $reshipping['buyer_address'];
        $reshipping['shop_name'] = app::get('ome')->model('shop')->db_dump(array('shop_id' => $reshipping['shop_id']), 'name')['name'];
        // 获取关联订单信息
        if ($reshipping['order_id']) {
            $order = $orderModel->db_dump(array('order_id' => $reshipping['order_id']), 'order_bn,platform_order_bn,ship_name,ship_mobile,ship_addr,shop_type');
            $reshipping['order_info'] = $order;
        }
        
        // 获取补发订单信息
        if ($reshipping['reissue_order_id']) {
            $reissueOrder = $orderModel->db_dump(array('order_id' => $reshipping['reissue_order_id']), 'order_bn,order_type,status');
            $reshipping['reissue_order_info'] = $reissueOrder;
        }
        
        // 获取补寄申请商品明细
        $items = $reshippingItemsModel->getList('*', array('reshipping_id' => $reshipping_id));
        $this->pagedata['items'] = $items;
        
        // 获取补寄确认商品明细
        $reshippingItemsDetailModel = app::get('ome')->model('return_reshipping_items_detail');
        $itemsDetail = $reshippingItemsDetailModel->getList('*', array('reshipping_id' => $reshipping_id));
        $this->pagedata['items_detail'] = $itemsDetail;
        
        // 状态映射
        $reshipping['status_name'] = isset($reshippingModel->status[$reshipping['status']]) ? $reshippingModel->status[$reshipping['status']] : $reshipping['status'];
        
        $this->pagedata['reshipping'] = $reshipping;
        $this->pagedata['finder_id'] = ($_GET['_finder']['finder_id'] ? $_GET['_finder']['finder_id'] : $_GET['finder_id']);
        
        $this->singlepage('admin/return/reshipping/detail_page.html');
    }
    
    /**
     * 同意补寄申请对话框
     */
    public function agreeDialog()
    {
        $reshipping_id = $_GET['reshipping_id'];
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $reshippingItemsModel = app::get('ome')->model('return_reshipping_items');
        $salesMaterialModel = app::get('material')->model('sales_material');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            $this->splash('error', '', '补寄申请单不存在');
            return;
        }
        
        // 获取商品明细
        $items = $reshippingItemsModel->getList('*', array('reshipping_id' => $reshipping_id));
        
        // 获取销售物料信息
        foreach ($items as $key => $item) {
            if ($item['goods_id']) {
                $salesMaterial = $salesMaterialModel->db_dump($item['goods_id'], 'sm_id,sales_material_bn,sales_material_name');
                $items[$key]['sales_material'] = $salesMaterial;
            }
        }
        
        $this->pagedata['reshipping'] = $reshipping;
        $this->pagedata['items'] = $items;
        
        $this->display('admin/return/reshipping/agree_dialog.html');
    }
    
    /**
     * 执行同意补寄申请
     */
    public function doAgree()
    {
        $reshipping_id = $_POST['reshipping_id'];
        $items_detail = isset($_POST['items_detail']) ? $_POST['items_detail'] : array();
        
        $reshippingLib = kernel::single('ome_reshipping');
        $result = $reshippingLib->agree($reshipping_id, $items_detail);
        
        if ($result['rsp'] == 'succ') {
            $this->splash('success', $this->url, $result['msg']);
        } else {
            $this->splash('error', '', $result['msg']);
        }
    }
    
    /**
     * 拒绝补寄申请对话框
     */
    public function refuseDialog()
    {
        $reshipping_id = $_GET['reshipping_id'];
        $reshippingModel = app::get('ome')->model('return_reshipping');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            $this->splash('error', '', '补寄申请单不存在');
            return;
        }
        
        // 获取拒绝原因列表
        $apiResult = kernel::single('erpapi_router_request')->set('shop', $reshipping['shop_id'])->reshipping_refusereason_get(array(
            'dispute_id' => $reshipping['reshipping_bn'],
        ));
        $refuseReasons = ($apiResult['rsp'] == 'succ' && isset($apiResult['data'])) ? $apiResult['data'] : array();
        
        $this->pagedata['reshipping'] = $reshipping;
        $this->pagedata['refuse_reasons'] = $refuseReasons;
        
        $this->display('admin/return/reshipping/refuse_dialog.html');
    }
    
    /**
     * 执行拒绝补寄申请
     */
    public function doRefuse()
    {
        $reshipping_id = $_POST['reshipping_id'];
        $refuse_reason_id = $_POST['refuse_reason_id'];
        $refuse_reason = $_POST['refuse_reason'];
        $leave_message = trim($_POST['leave_message']);
        
        if (empty($refuse_reason_id)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '请选择拒绝原因'));
            exit;
        }
        
        if (empty($leave_message)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '拒绝留言不能为空'));
            exit;
        }
        
        // 处理图片上传（base64 编码，仅支持单张图片）
        $leave_message_pics = array();
        if (isset($_FILES['leave_message_pics']) && !empty($_FILES['leave_message_pics']['name'])) {
            $uploadedFiles = $_FILES['leave_message_pics'];
            
            // 检查是否为多文件上传
            if (is_array($uploadedFiles['name'])) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '只能上传一张图片'));
                exit;
            }
            
            // 检查文件是否上传成功
            if ($uploadedFiles['error'] != UPLOAD_ERR_OK || empty($uploadedFiles['name'])) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '文件上传失败'));
                exit;
            }
            
            // 检查文件大小（500K限制）
            if ($uploadedFiles['size'] > 512000) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '上传文件不能超过500K！'));
                exit;
            }
            
            // 检查文件类型
            $allowedTypes = array('gif', 'jpg', 'png', 'jpeg');
            $imgext = strtolower(pathinfo($uploadedFiles['name'], PATHINFO_EXTENSION));
            if (!in_array($imgext, $allowedTypes)) {
                $text = implode(",", $allowedTypes);
                echo json_encode(array('rsp' => 'fail', 'msg' => "您只能上传以下类型文件{$text}！"));
                exit;
            }
            
            // 读取文件二进制并 base64 编码
            $rh = fopen($uploadedFiles['tmp_name'], 'rb');
            if ($rh === false) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '读取文件失败'));
                exit;
            }
            $imagebinary = fread($rh, filesize($uploadedFiles['tmp_name']));
            fclose($rh);
            $imagebinary = base64_encode($imagebinary);
            
            if ($imagebinary) {
                $leave_message_pics[] = $imagebinary;
            }
        }
        
        if (empty($leave_message_pics)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '请上传凭证图片'));
            exit;
        }
        
        $refuseData = array(
            'refuse_reason_id' => $refuse_reason_id,
            'refuse_reason' => $refuse_reason,
            'leave_message' => $leave_message,
            'leave_message_pics' => $leave_message_pics[0], // 只传递单个值
        );
        
        $reshippingLib = kernel::single('ome_reshipping');
        $result = $reshippingLib->refuse($reshipping_id, $refuseData);
        
        if ($result['rsp'] == 'succ') {
            echo json_encode(array('rsp' => 'succ', 'msg' => $result['msg']));
        } else {
            echo json_encode(array('rsp' => 'fail', 'msg' => $result['msg']));
        }
        exit;
    }
    
    /**
     * 商品信息确认对话框（淘宝平台同意场景）
     */
    public function confirmGoodsDialog()
    {
        $reshipping_id = $_GET['reshipping_id'];
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $reshippingItemsModel = app::get('ome')->model('return_reshipping_items');
        $salesMaterialModel = app::get('material')->model('sales_material');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            $this->splash('error', '', '补寄申请单不存在');
            return;
        }
        
        // 检查状态
        if ($reshipping['is_confirm_goods'] == '1') {
            $this->splash('error', '', '商品信息已确认');
            return;
        }
        
        // 获取商品明细
        $items = $reshippingItemsModel->getList('*', array('reshipping_id' => $reshipping_id));
        
        // 获取销售物料信息
        foreach ($items as $key => $item) {
            if ($item['goods_id']) {
                $salesMaterial = $salesMaterialModel->db_dump($item['goods_id'], 'sm_id,sales_material_bn,sales_material_name');
                $items[$key]['sales_material'] = $salesMaterial;
            }
        }
        
        $this->pagedata['reshipping'] = $reshipping;
        $this->pagedata['items'] = $items;
        
        $this->display('admin/return/reshipping/confirm_goods_dialog.html');
    }
    
    /**
     * 执行商品信息确认
     */
    public function doConfirmGoods()
    {
        $reshipping_id = $_POST['reshipping_id'];
        $items_detail = isset($_POST['items_detail']) ? $_POST['items_detail'] : array();
        
        $reshippingLib = kernel::single('ome_reshipping');
        $result = $reshippingLib->confirmGoods($reshipping_id, $items_detail);
        
        if ($result['rsp'] == 'succ') {
            $this->splash('success', $this->url, $result['msg']);
        } else {
            $this->splash('error', '', $result['msg']);
        }
    }
    
    /**
     * 获取留言列表（AJAX）
     */
    public function getMessages()
    {
        $reshipping_id = $_GET['reshipping_id'];
        $page_no = isset($_GET['page_no']) ? intval($_GET['page_no']) : 1;
        $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 20;
        
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        
        if (empty($reshipping)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '补寄申请单不存在'));
            return;
        }
        
        // 调用矩阵接口获取留言列表
        $apiResult = kernel::single('erpapi_router_request')->set('shop', $reshipping['shop_id'])->reshipping_messages_get(array(
            'dispute_id' => $reshipping['reshipping_bn'],
            'page_no' => $page_no,
            'page_size' => $page_size,
        ));
        
        if ($apiResult['rsp'] == 'succ' && isset($apiResult['data'])) {
            $messages = is_array($apiResult['data']) ? $apiResult['data'] : array();
            echo json_encode(array('rsp' => 'succ', 'data' => $messages));
        } else {
            echo json_encode(array('rsp' => 'fail', 'msg' => isset($apiResult['msg']) ? $apiResult['msg'] : '获取留言列表失败'));
        }
    }
    
    /**
     * 追加留言对话框
     */
    public function addMessageDialog()
    {
        $reshipping_id = $_GET['reshipping_id'];
        $reshippingModel = app::get('ome')->model('return_reshipping');
        
        // 获取补寄申请单信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        if (empty($reshipping)) {
            $this->splash('error', '', '补寄申请单不存在');
            return;
        }
        
        $this->pagedata['reshipping'] = $reshipping;
        
        $this->display('admin/return/reshipping/add_message_dialog.html');
    }
    
    /**
     * 执行追加留言
     */
    public function doAddMessage()
    {
        
        $reshipping_id = $_POST['reshipping_id'];
        $content = trim($_POST['content']);
        
        if (empty($content)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '留言内容不能为空'));
            exit;
        }
        
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        
        if (empty($reshipping)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '补寄申请单不存在'));
            exit;
        }
        
        // 处理图片上传（base64 编码，仅支持单张图片）
        $message_pics = array();
        if (isset($_FILES['message_pics']) && !empty($_FILES['message_pics']['name'])) {
            $uploadedFiles = $_FILES['message_pics'];
            
            // 检查是否为多文件上传
            if (is_array($uploadedFiles['name'])) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '只能上传一张图片'));
                exit;
            }
            
            // 检查文件是否上传成功
            if ($uploadedFiles['error'] != UPLOAD_ERR_OK || empty($uploadedFiles['name'])) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '文件上传失败'));
                exit;
            }
            
            // 检查文件大小（500K限制）
            if ($uploadedFiles['size'] > 512000) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '上传文件不能超过500K！'));
                exit;
            }
            
            // 检查文件类型
            $allowedTypes = array('gif', 'jpg', 'png', 'jpeg');
            $imgext = strtolower(pathinfo($uploadedFiles['name'], PATHINFO_EXTENSION));
            if (!in_array($imgext, $allowedTypes)) {
                $text = implode(",", $allowedTypes);
                echo json_encode(array('rsp' => 'fail', 'msg' => "您只能上传以下类型文件{$text}！"));
                exit;
            }
            
            // 读取文件二进制并 base64 编码
            $rh = fopen($uploadedFiles['tmp_name'], 'rb');
            if ($rh === false) {
                echo json_encode(array('rsp' => 'fail', 'msg' => '读取文件失败'));
                exit;
            }
            $imagebinary = fread($rh, filesize($uploadedFiles['tmp_name']));
            fclose($rh);
            $imagebinary = base64_encode($imagebinary);
            
            if ($imagebinary) {
                $message_pics[] = $imagebinary;
            }
        }
        
        // 调用矩阵接口创建留言
        $apiParams = array(
            'dispute_id' => $reshipping['reshipping_bn'],
            'content' => $content,
        );
        if (!empty($message_pics)) {
            $apiParams['message_pics'] = $message_pics[0]; // 只传递单个值
        }
        
        $apiResult = kernel::single('erpapi_router_request')->set('shop', $reshipping['shop_id'])->reshipping_message_add($apiParams);
        
        if ($apiResult['rsp'] == 'succ') {
            // 保存留言到数据库
            $messagesModel = app::get('ome')->model('return_reshipping_messages');
            $opinfo = kernel::single('ome_func')->getDesktopUser();
            
            $messageData = array(
                'reshipping_id' => $reshipping_id,
                'message_type' => '商家', // 商家留言
                'content' => $content,
                //'attachment' => !empty($message_pics) ? implode(',', $message_pics) : '',
                'op_id' => $opinfo['op_id'],
            );
            $messagesModel->insert($messageData);
            
            // 记录操作日志
            $operationLogModel = app::get('ome')->model('operation_log');
            $memo = '追加补寄留言：' . $content;
            if (!empty($message_pics)) {
                $memo .= '（包含1张图片）';
            }
            $operationLogModel->write_log('return_reshipping@ome', $reshipping_id, $memo, time(), $opinfo);
            
            echo json_encode(array('rsp' => 'succ', 'msg' => '追加留言成功'));
            exit;
        } else {
            echo json_encode(array('rsp' => 'fail', 'msg' => '追加留言失败：' . (isset($apiResult['msg']) ? $apiResult['msg'] : '未知错误')));
            exit;
        }
    }
    
    /**
     * 单拉补寄申请单对话框
     */
    public function pullDialog()
    {
        // 获取店铺列表
        $shopModel = app::get('ome')->model('shop');
        $shops = $shopModel->getList('shop_id,name,node_type', array('s_type' => '1', 'disabled' => 'false', 'node_type' => 'taobao'), 0, -1);
        
        $this->pagedata['shops'] = $shops;
        
        $this->display('admin/return/reshipping/pull_dialog.html');
    }
    
    /**
     * 执行单拉补寄申请单
     */
    public function doPull()
    {
        $shop_id = trim($_POST['shop_id']);
        $dispute_id = trim($_POST['dispute_id']);
        
        // 参数验证
        if (empty($shop_id)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '请选择店铺'));
            exit;
        }
        
        if (empty($dispute_id)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '请输入补寄单号'));
            exit;
        }
        
        // 调用事件触发器处理业务逻辑
        $trigger = kernel::single('ome_event_trigger_reshipping_order');
        $result = $trigger->pullReshipping($shop_id, $dispute_id);
        
        echo json_encode($result);
        exit;
    }
    
}

