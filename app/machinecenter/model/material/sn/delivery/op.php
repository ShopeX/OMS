<?php

class machinecenter_mdl_material_sn_delivery_op extends dbeav_model
{
    var $has_export_cnf = true;
    var $export_name    = '门店发货唯一码记录';

    private $_list_extra_gt_master_done = false;
    private $_export_extra_gt_master_done = false;

    /**
     * 主档扩展列（machinecenter.service.material.master，无 service 则无扩展）
     */
    public function extra_cols()
    {
        return machinecenter_material_master::extraCols();
    }

    public function extra_gt_master($rows)
    {
        if ($this->_list_extra_gt_master_done || empty($rows)) {
            return $rows;
        }
        $rows = $this->_prepareMasterRows($rows);
        $rows = machinecenter_material_master::fillListRows($rows);
        $this->_list_extra_gt_master_done = true;
        return $rows;
    }

    public function export_extra_cols()
    {
        return machinecenter_material_master::extraCols();
    }

    public function export_extra_gt_master($list)
    {
        if ($this->_export_extra_gt_master_done || empty($list)) {
            return $list;
        }
        $list = $this->_prepareMasterRows($list);
        $list = machinecenter_material_master::fillListRows($list);
        $this->_export_extra_gt_master_done = true;
        return $list;
    }

    /**
     * 行数据兼容主档填充：material_bn → bn
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

    public function searchOptions()
    {
        return array_merge(array(
            'delivery_bn' => '发货单号',
            'order_bn'    => '订单号',
            'sn'          => '唯一码',
            'material_bn' => '货号',
            'op_name'     => '操作员',
            'op_no'       => '工号',
            'store_bn'    => '门店编码',
        ), machinecenter_material_master::searchOptions());
    }

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        foreach (array('delivery_bn', 'order_bn', 'sn', 'material_bn') as $field) {
            if (!empty($filter[$field]) && is_string($filter[$field]) && strpos($filter[$field], "\n") !== false) {
                $filter[$field] = array_unique(array_map('trim', array_filter(explode("\n", $filter[$field]))));
            }
        }

        // 本表无 bm_id，主档筛选按 material_bn 关联
        $where = '';
        $masterWhere = array();
        machinecenter_material_master::applyFilter($filter, $masterWhere, null, 'bm_id');
        if (!empty($masterWhere)) {
            $prefix = $tableAlias ? $tableAlias . '.' : '';
            foreach ($masterWhere as $item) {
                $item = trim($item);
                if (preg_match('/^bm_id\s*=\s*-1$/i', $item)) {
                    $where .= ' AND ' . $prefix . "material_bn = '__NO_MATCH__' ";
                } elseif (preg_match('/^bm_id\s+IN\s+\((.+)\)$/is', $item, $m)) {
                    $where .= ' AND ' . $prefix . 'material_bn IN ('
                        . 'SELECT material_bn FROM sdb_material_basic_material WHERE bm_id IN (' . $m[1] . ')'
                        . ') ';
                }
            }
        }

        return $where . parent::_filter($filter, $tableAlias, $baseWhere);
    }
}
