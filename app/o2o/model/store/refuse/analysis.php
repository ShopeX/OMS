<?php
class o2o_mdl_store_refuse_analysis extends dbeav_model
{
    var $has_export_cnf = true;
    var $export_name    = '门店拒单列表';

    public function modifier_reason_id($reasonId)
    {
        if ($reasonId === '' || $reasonId === null) {
            return '';
        }
        $row = app::get('o2o')->model('refuse_reason')->db_dump($reasonId, 'reason_name');
        return $row['reason_name'] ?? $reasonId;
    }

    public function get_reason_analysis($filter = null)
    {
        $sql = 'SELECT count(sra.reason_id) as num , rr.reason_name as name FROM sdb_o2o_store_refuse_analysis as sra right join sdb_o2o_refuse_reason as rr on sra.reason_id = rr.reason_id and ' . $this->_get_filter($filter) . ' GROUP BY rr.reason_id,rr.reason_name ORDER BY rr.reason_name';

        $row = $this->db->select($sql);
        return $row;
    }

    public function _get_filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = array(1);
        if (isset($filter['time_from']) && $filter['time_from']) {
            $where[] = ' sra.createtime >=' . strtotime($filter['time_from']);
        }

        if (isset($filter['time_to']) && $filter['time_to']) {
            $where[] = ' sra.createtime <' . (strtotime($filter['time_to']) + 86400);
        }

        if (isset($filter['type_id']) && $filter['type_id']) {
            $where[] = ' sra.store_bn = \'' . $filter['type_id'] . '\'';
        }

        return parent::_filter($filter, 'sra', $baseWhere) . " AND " . implode(' AND ', $where);
    }

    /**
     * 获取订单拒绝门店
     *
     * @return void
     * @author
     **/
    public function get_refuse_stores($order_id)
    {
        $stores = array();

        $delivery_order = app::get('ome')->model('delivery_order')->getList('*', array('order_id' => $order_id));

        if (!$delivery_order) {
            return $stores;
        }

        $delivery_id = array_column($delivery_order, 'delivery_id');

        $refuse_list = $this->getList('store_bn', array('delivery_id' => $delivery_id));

        $stores = array_column($refuse_list, 'store_bn');

        return $stores;
    }
}
