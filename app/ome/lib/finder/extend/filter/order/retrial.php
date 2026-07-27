<?php
/**
 * 复审订单高级筛选扩展
 *
 * 商品变化订单和已复审订单沿用历史订单列表的筛选控件样式，方便用户保持
 * 一致的操作习惯。店铺和运营组织选项同时按当前账号的组织权限裁剪，避免
 * 筛选选项暴露权限范围外的数据。
 */
class ome_finder_extend_filter_order_retrial
{
    public function get_extend_colums($finder_aliasname=null)
    {
        $finder_aliasname = (string) $finder_aliasname;
        if(strpos($finder_aliasname, 'order_retrial_normal') !== 0
            && strpos($finder_aliasname, 'order_retrial_success') !== 0)
        {
            return array();
        }

        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();

        $shop_filter = array();
        if($organization_permissions)
        {
            $shop_filter['user_org_id'] = $organization_permissions;
        }
        $shop_list = app::get('ome')->model('shop')->getList('shop_id,name', $shop_filter, 0, -1, 'name ASC');
        $shop_options = array_column((array) $shop_list, 'name', 'shop_id');

        $org_filter = array();
        if($organization_permissions)
        {
            $org_filter['org_id'] = $organization_permissions;
        }
        $org_list = app::get('ome')->model('operation_organization')->getList('org_id,name', $org_filter, 0, -1, 'name ASC');
        $org_options = array_column((array) $org_list, 'name', 'org_id');

        $db['order_retrial']['columns'] = array(
            'shop_id' => array(
                'type'          => $shop_options,
                'label'         => '来源店铺',
                'width'         => 100,
                'filtertype'    => 'fuzzy_search_multiple',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
            'org_id' => array(
                'type'          => $org_options,
                'label'         => '运营组织',
                'width'         => 60,
                'filtertype'    => 'normal',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
            'product_bn' => array(
                'type'          => 'varchar(30)',
                'label'         => '基础物料编码',
                'width'         => 85,
                'filtertype'    => 'textarea',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
            'sales_material_bn' => array(
                'type'          => 'varchar(30)',
                'label'         => '销售物料编码',
                'width'         => 85,
                'filtertype'    => 'textarea',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
            'sales_material_name' => array(
                'type'          => 'varchar(200)',
                'label'         => '销售物料名称',
                'width'         => 120,
                'filtertype'    => 'textarea',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
            'createtime' => array(
                'type'          => 'time',
                'label'         => '下单时间',
                'width'         => 160,
                'filtertype'    => 'time',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
            'pay_status' => array(
                'type' => array(
                    0 => '未支付',
                    1 => '已支付',
                    2 => '处理中',
                    3 => '部分付款',
                    4 => '部分退款',
                    5 => '全额退款',
                    6 => '退款申请中',
                    7 => '退款中',
                    8 => '支付中',
                ),
                'label'         => '付款状态',
                'width'         => 75,
                'filtertype'    => 'yes',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
            'ship_status' => array(
                'type' => array(
                    0 => '未发货',
                    1 => '已发货',
                    2 => '部分发货',
                    3 => '部分退货',
                    4 => '已退货',
                ),
                'label'         => '发货状态',
                'width'         => 75,
                'filtertype'    => 'yes',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
            'process_status' => array(
                'type' => array(
                    'unconfirmed'   => '未确认',
                    'confirmed'     => '已确认',
                    'splitting'     => '部分拆分',
                    'splited'       => '已拆分完',
                    'cancel'        => '取消',
                    'remain_cancel' => '余单撤销',
                    'is_retrial'    => '复审订单',
                    'is_declare'    => '跨境申报订单',
                ),
                'label'         => '确认状态',
                'width'         => 70,
                'filtertype'    => 'yes',
                'filterdefault' => true,
                'editable'      => false,
                'in_list'       => false,
            ),
        );

        return $db;
    }
}
