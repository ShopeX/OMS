<?php

class machinecenter_mdl_material_sn extends dbeav_model
{
    // 导出配置 - 启用队列导出
    var $has_export_cnf = true;
    var $export_name = '机器中心SN列表';

    private $_list_extra_gt_master_done = false;
    private $_export_extra_gt_master_done = false;
    private $_list_extra_sn_props_done = false;
    private $_export_extra_sn_props_done = false;

    /**
     * 扩展列：主档 service + SN props service
     */
    public function extra_cols()
    {
        return array_merge(
            machinecenter_material_master::extraCols(),
            machinecenter_material_sn_props::extraCols()
        );
    }

    public function extra_gt_master($rows)
    {
        if ($this->_list_extra_gt_master_done || empty($rows)) {
            return $rows;
        }
        $rows = $this->_prepareMasterRows($rows);
        $rows = machinecenter_material_master::fillListRowsWithIdField($rows, 'bm_id');
        $this->_list_extra_gt_master_done = true;
        return $rows;
    }

    public function extra_sn_props($rows)
    {
        if ($this->_list_extra_sn_props_done || empty($rows)) {
            return $rows;
        }
        $rows = machinecenter_material_sn_props::fillListRows($rows);
        $this->_list_extra_sn_props_done = true;
        return $rows;
    }

    public function export_extra_cols()
    {
        return array_merge(
            machinecenter_material_master::extraCols(),
            machinecenter_material_sn_props::extraCols()
        );
    }

    public function export_extra_gt_master($list)
    {
        if ($this->_export_extra_gt_master_done || empty($list)) {
            return $list;
        }
        $list = $this->_prepareMasterRows($list);
        $list = machinecenter_material_master::fillListRowsWithIdField($list, 'bm_id');
        $this->_export_extra_gt_master_done = true;
        return $list;
    }

    public function export_extra_sn_props($list)
    {
        if ($this->_export_extra_sn_props_done || empty($list)) {
            return $list;
        }
        $list = machinecenter_material_sn_props::fillListRows($list);
        $this->_export_extra_sn_props_done = true;
        return $list;
    }

    /**
     * 顶部快捷搜索：schema 字段 + service 扩展（模号编码等）
     */
    public function searchOptions()
    {
        return array_merge(
            parent::searchOptions(),
            machinecenter_material_master::searchOptions(),
            machinecenter_material_sn_props::searchOptions()
        );
    }

    /**
     * SN 行兼容主档填充：material_bn → bn
     */
    private function _prepareMasterRows(array $rows)
    {
        foreach ($rows as $k => $row) {
            if (empty($row['bn']) && !empty($row['material_bn'])) {
                $rows[$k]['bn'] = $row['material_bn'];
            }
        }
        return $rows;
    }

    function _filter($filter, $tableAlias = null, $baseWhere = array ())
    {
        $where       = '1';
        $mcSnFlowMdl = app::get('machinecenter')->model('material_sn_flow');
        // 多订单号查询
        if ($filter['order_bn'] && is_string($filter['order_bn']) && strpos($filter['order_bn'], "\n") !== false) {
            $order_bn   = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));
            $snFLowList = $mcSnFlowMdl->getList('sn_id', [
                'order_bn' => $order_bn,
            ]);
            if ($snFLowList) {
                $sn_id = array_unique(array_column($snFLowList, 'sn_id'));
                $where .= '  AND id IN (' . implode(',', $sn_id) . ')';
            } else {
                $where .= ' AND id=-1';
            }
            unset($filter['order_bn']);
        }

        if ($filter['sn'] && is_string($filter['sn']) && strpos($filter['sn'], "\n") !== false) {
            $filter['sn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['sn']))));
        }

        if ($filter['bill_bn'] && is_string($filter['bill_bn']) && strpos($filter['bill_bn'], "\n") !== false) {
            $bill_bn    = array_unique(array_map('trim', array_filter(explode("\n", $filter['bill_bn']))));
            $snFLowList = $mcSnFlowMdl->getList('sn_id', [
                'bill_bn' => $bill_bn,
            ]);
            if ($snFLowList) {
                $sn_id = array_unique(array_column($snFLowList, 'sn_id'));
                $where .= '  AND id IN (' . implode(',', $sn_id) . ')';
            } else {
                $where .= ' AND id=-1';
            }
            unset($filter['bill_bn']);
        }

        if ($filter['original_bn'] && is_string($filter['original_bn']) && strpos($filter['original_bn'], "\n") !== false) {
            $original_bn = array_unique(array_map('trim', array_filter(explode("\n", $filter['original_bn']))));
            $snFLowList  = $mcSnFlowMdl->getList('sn_id', [
                'original_bn' => $original_bn,
            ]);
            if ($snFLowList) {
                $sn_id = array_unique(array_column($snFLowList, 'sn_id'));
                $where .= '  AND id IN (' . implode(',', $sn_id) . ')';
            } else {
                $where .= ' AND id=-1';
            }
            unset($filter['original_bn']);
        }

        if ($filter['material_bn'] && is_string($filter['material_bn']) && strpos($filter['material_bn'], "\n") !== false) {
            $filter['material_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['material_bn']))));
        }
        $otherFilter = [];
        if ($filter['order_bn']) {
            $otherFilter['order_bn'] = $filter['order_bn'];
            unset($filter['order_bn']);
        }
        if ($filter['bill_bn']) {
            $otherFilter['bill_bn'] = $filter['bill_bn'];
            unset($filter['bill_bn']);
        }
        if ($filter['original_bn']) {
            $otherFilter['original_bn'] = $filter['original_bn'];
            unset($filter['original_bn']);
        }

        if ($otherFilter) {
            $snFLowList = $mcSnFlowMdl->getList('sn_id', $otherFilter);
            if ($snFLowList) {
                $sn_id = array_unique(array_column($snFLowList, 'sn_id'));
                $where .= '  AND id IN (' . implode(',', $sn_id) . ')';
            } else {
                $where .= ' AND id=-1';
            }
        }

        if (!empty($filter['sn_label'])) {
            $billLabelObj = app::get('ome')->model('bill_label');
            $labelFilter = array(
                'label_id' => $filter['sn_label'],
                'bill_type' => 'material_sn',
            );
            $remainFilter = $filter;
            unset($remainFilter['sn_label']);
            $sql = "SELECT bill_id FROM sdb_ome_bill_label bl
                LEFT JOIN sdb_machinecenter_material_sn sn ON bl.bill_id=sn.id
                WHERE " . $billLabelObj->_filter($labelFilter, 'bl') . " AND " . parent::_filter($remainFilter, 'sn');
            $where .= ' AND id IN (' . $sql . ')';
            unset($filter['sn_label']);
        }

        // 主档扩展筛选（machinecenter.service.material.master）
        machinecenter_material_master::appendMasterWhere($filter, $where, $tableAlias, 'bm_id');
        // SN props 扩展筛选（machinecenter.service.material.sn.props）
        machinecenter_material_sn_props::appendSnPropsWhere($filter, $where, $tableAlias, 'id');

        return $where . " AND " . parent::_filter($filter, $tableAlias, $baseWhere);
    }

    function modifier_status($status)
    {
        $statusMap = [
            '0' => ['text' => '出库', 'bg_color' => '#dc3545', 'text_color' => '#ffffff'], // 红色 - 出库状态
            '1' => ['text' => '在库', 'bg_color' => '#28a745', 'text_color' => '#ffffff'], // 绿色 - 在库状态  
            '3' => ['text' => '未使用', 'bg_color' => '#ffc107', 'text_color' => '#212529'], // 黄色 - 未使用状态
        ];

        if (isset($statusMap[$status])) {
            $config = $statusMap[$status];
            return sprintf(
                "<span class='tag-label' title='%s' style='background-color:%s;color:%s;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;'>%s</span>",
                $config['text'],
                $config['bg_color'],
                $config['text_color'],
                $config['text']
            );
        } else {
            // 默认样式，用于未知状态
            return sprintf(
                "<span class='tag-label' title='%s' style='background-color:#6c757d;color:#ffffff;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;'>%s</span>",
                $status,
                $status
            );
        }
    }

    public function modifier_buyer_nickname($buyer_nickname, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($buyer_nickname);
        if (!$is_encrypt) {
            return $buyer_nickname;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($buyer_nickname,'order','buyer_nickname');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="buyer_nickname">{$encrypt}</span></span>
HTML;
        return $buyer_nickname ? $return : $buyer_nickname;
    }

    public function modifier_buyer_name($buyer_name, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($buyer_name);
        if (!$is_encrypt) {
            return $buyer_name;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($buyer_name,'order','buyer_name');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="buyer_name">{$encrypt}</span></span>
HTML;
        return $buyer_name ? $return : $buyer_name;
    }

    public function modifier_buyer_mobile($buyer_mobile, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($buyer_mobile);
        if (!$is_encrypt) {
            return $buyer_mobile;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($buyer_mobile,'order','buyer_mobile');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="buyer_mobile">{$encrypt}</span></span>
HTML;
        return $buyer_mobile ? $return : $buyer_mobile;
    }

    public function modifier_buyer_email($buyer_email, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($buyer_email);
        if (!$is_encrypt) {
            return $buyer_email;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($buyer_email,'order','buyer_email');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="buyer_email">{$encrypt}</span></span>
HTML;
        return $buyer_email ? $return : $buyer_email;
    }

    public function modifier_buyer_address($buyer_address, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($buyer_address);
        if (!$is_encrypt) {
            return $buyer_address;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($buyer_address,'order','buyer_address');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="buyer_address">{$encrypt}</span></span>
HTML;
        return $buyer_address ? $return : $buyer_address;
    }

    public function modifier_ship_name($ship_name, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_name);
        if (!$is_encrypt) {
            return $ship_name;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_name,'order','ship_name');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_name">{$encrypt}</span></span>
HTML;
        return $ship_name ? $return : $ship_name;
    }

    public function modifier_ship_mobile($ship_mobile, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_mobile);
        if (!$is_encrypt) {
            return $ship_mobile;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_mobile,'order','ship_mobile');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_mobile">{$encrypt}</span></span>
HTML;
        return $ship_mobile ? $return : $ship_mobile;
    }

    public function modifier_ship_email($ship_email, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_email);
        if (!$is_encrypt) {
            return $ship_email;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_email,'order','ship_email');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_email">{$encrypt}</span></span>
HTML;
        return $ship_email ? $return : $ship_email;
    }

    public function modifier_ship_address($ship_address, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_address);
        if (!$is_encrypt) {
            return $ship_address;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_address,'order','ship_address');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_address">{$encrypt}</span></span>
HTML;
        return $ship_address ? $return : $ship_address;
    }

    public function modifier_user_name($user_name, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($user_name);
        if (!$is_encrypt) {
            return $user_name;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($user_name,'order','user_name');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="user_name">{$encrypt}</span></span>
HTML;
        return $user_name ? $return : $user_name;
    }

    public function modifier_user_mobile($user_mobile, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($user_mobile);
        if (!$is_encrypt) {
            return $user_mobile;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($user_mobile,'order','user_mobile');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="user_mobile">{$encrypt}</span></span>
HTML;
        return $user_mobile ? $return : $user_mobile;
    }

    public function modifier_user_email($user_email, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($user_email);
        if (!$is_encrypt) {
            return $user_email;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($user_email,'order','user_email');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="user_email">{$encrypt}</span></span>
HTML;
        return $user_email ? $return : $user_email;
    }

    public function modifier_user_address($user_address, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($user_address);
        if (!$is_encrypt) {
            return $user_address;
        }

        $id       = $row['id'];
        $encrypt  = kernel::single('ome_view_helper2')->modifier_ciphertext($user_address,'order','user_address');
        $return   = <<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=machinecenter&ctl=admin_sn&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="user_address">{$encrypt}</span></span>
HTML;
        return $user_address ? $return : $user_address;
    }


    public function modifier_qa_expire_item($qa_expire_item, $list, $row)
    {
        return $qa_expire_item > 0 ? date('Y-m-d H:i:s',$qa_expire_item) : '';
    }

}
