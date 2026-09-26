<?php

class machinecenter_ctl_admin_sn extends desktop_controller
{

    public $name       = "SN列表";
    public $workground = "machine_center";
    public function index()
    {
        //ini_set('display_errors', 'on');
        $params = [
            'title'                 => 'SN列表',
            'use_buildin_set_tag'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => true,  // 启用队列导出
            'use_buildin_importxls' => false,
            'use_buildin_recycle'   => false,
            'actions'               => [],
            'finder_cols'           => machinecenter_material_master::mergeFinderCols(
                machinecenter_material_sn_props::mergeFinderCols(
                    'column_confirm,column_sn_label,material_bn,sn,branch_id,bill_bn,status',
                    'column_sn_label'
                ),
                'material_bn'
            ),
            'orderBy'               => 'id desc',
        ];

        $this->finder('machinecenter_mdl_material_sn', $params);
    }

    /**
     * 编辑sn使用人信息
     * @param $id
     * @return void
     */
    public function edit($id)
    {
        $snMdl  = app::get('machinecenter')->model('material_sn');
        $snInfo = $snMdl->db_dump($id);

        if ($snInfo['qa_expire_item']) {
            $time                         = ($snInfo['qa_expire_item']);
            $snInfo['start_time']         = date('Y-m-d', $time);
        }
        $user_province = str_replace('省','',$snInfo['user_province']);
        $user_province = str_replace('市','',$user_province);
        $regions                = app::get('eccommon')->model('regions')->db_dump(array ('region_grade' => 1, 'local_name' => $user_province), 'region_id');
        $regions                = app::get('eccommon')->model('regions')->db_dump(array ('region_grade' => 2, 'local_name' => $snInfo['user_city'], 'p_region_id' => $regions['region_id']), 'region_id');
        $regions                = app::get('eccommon')->model('regions')->db_dump(array ('region_grade' => 3, 'local_name' => $snInfo['user_district'], 'p_region_id' => $regions['region_id']), 'region_id');
        $snInfo['area']         = 'mainland:' . $snInfo['user_province'] . '/' . $snInfo['user_city'] . '/' . $snInfo['user_district'] . ':' . $regions['region_id'];
        $this->pagedata['data'] = $snInfo;

        $this->singlepage('sn/edit.html');
    }

    public function save()
    {
        $this->begin();

        $post = kernel::single('base_component_request')->get_params(true);
        if ($post['area']) {
            $address               = explode('/', explode(':', $post['area'])['1']);
            $post['user_province'] = $address[0];
            $post['user_city']     = $address[1];
            $post['user_district'] = $address[2];
        }

        $post['qa_expire_item'] = strtotime($post['start_time']);

        list($res, $msg) = kernel::single('machinecenter_event_receive_sn')->updateUserInfo($post);
        if (!$res) {

        }
        $this->end(true, '编辑成功');
    }

    /**
     * 编辑唯一码表单（弹窗）
     * @param int $id material_sn_flow.id 流水表主键
     */
    public function editSn($id = 0)
    {
        $id = (int) $id;
        if (!$id) {
            echo '<div class="content-container"><p>参数错误：缺少流水ID</p></div>';
            return;
        }
        $snFlowMdl = app::get('machinecenter')->model('material_sn_flow');
        $snMdl     = app::get('machinecenter')->model('material_sn');
        $flow      = $snFlowMdl->db_dump($id, 'id,sn_id');
        if (!$flow || empty($flow['sn_id'])) {
            echo '<div class="content-container"><p>流水记录不存在或未关联SN</p></div>';
            return;
        }
        $snRow = $snMdl->db_dump($flow['sn_id'], 'id,sn');
        $this->pagedata['data'] = [
            'id' => $id,
            'sn' => $snRow ? $snRow['sn'] : '',
        ];
        $this->pagedata['finder_id'] = isset($_GET['finder_id']) ? $_GET['finder_id'] : '';
        $this->page('sn/edit_sn.html', 'machinecenter');
    }

    /**
     * 保存唯一码（通过 updateSnInfo 按单据方式更新并写操作日志）
     * 表单提交的 id 为 material_sn_flow.id（流水表主键）
     * params: bill_bn(必填), bill_type(必填 delivery/reship/iso), items(必填 [{bn, old_sn, new_sn}])
     */
    public function updateSn()
    {
        $this->begin();
        $post = kernel::single('base_component_request')->get_params(true);
        $flow_id = isset($post['id']) ? (int) $post['id'] : 0;  // material_sn_flow.id
        $new_sn  = isset($post['sn']) ? trim($post['sn']) : '';  // 页面提交的是用户修改后的新唯一码
        if (!$flow_id) {
            $this->end(false, '参数错误');
        }
        if ($new_sn === '') {
            $this->end(false, '唯一码不能为空');
        }
        $snFlowMdl = app::get('machinecenter')->model('material_sn_flow');
        $snMdl     = app::get('machinecenter')->model('material_sn');
        $flow = $snFlowMdl->db_dump($flow_id, 'id,sn_id,original_bn,original_type,material_bn');
        if (!$flow || empty($flow['sn_id'])) {
            $this->end(false, '流水记录不存在或未关联SN');
        }
        if (empty($flow['original_bn'])) {
            $this->end(false, '无关联单据，无法通过单据方式更新');
        }
        $snRow  = $snMdl->db_dump($flow['sn_id'], 'sn');
        $old_sn = $snRow ? $snRow['sn'] : '';  // 数据库里存的是旧唯一码
        if ($new_sn === $old_sn) {
            $this->end(false, '修改的唯一码与当前一致，无需保存');
        }
        $bn     = !empty($flow['material_bn']) ? $flow['material_bn'] : '';
        $params = [
            'bill_bn'   => $flow['original_bn'],
            'bill_type' => $flow['original_type'] ?: 'iso',
            'items'     => [
                ['bn' => $bn, 'old_sn' => $old_sn, 'new_sn' => $new_sn],
            ],
        ];
        list($res, $msg) = kernel::single('machinecenter_event_receive_sn')->updateSnInfo($params);
        $this->end($res, $msg);
    }

    public function showSensitiveData($id, $type = 'order', $rowId = null)
    {
        $data  = array ();
        $snMdl = app::get('machinecenter')->model('material_sn');

        if ($id) {
            $data = $snMdl->db_dump($id);
            $shopInfo = app::get('ome')->model('shop')->db_dump($data['shop_id'],'shop_type');
            
            // 如果是处理单行数据的敏感数据
            if ($type == 'row' && $rowId) {
                // 获取SN流水数据
                $snFlowModel = app::get('machinecenter')->model('material_sn_flow');
                $flowInfo = $snFlowModel->db_dump(['id' => $rowId]);
                $data = $flowInfo;
                if ($flowInfo) {
                    $data['encrypt_body'] = kernel::single('ome_security_router', $shopInfo['shop_type'])->get_encrypt_body($data, $type);
                }
            } else {
                // 处理加密
                $data['encrypt_body'] = kernel::single('ome_security_router', $shopInfo['shop_type'])->get_encrypt_body($data, $type);
            }
        }

        $this->splash('success', null, null, 'redirect', $data);
    }

}
