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
class ome_mdl_return_iostock extends dbeav_model
{

    //是否有导出配置
    public $has_export_cnf = true;
    public $export_name    = '售后入库单';
    /**
     * 扩展搜索项
     */
    //function searchOptions(){

    //return parent::searchOptions();
    //}
    public function get_schema()
    {
        $schema = array(
            'columns'  => array(
                'item_id'          => array(
                    'type'     => 'number',
                    #'required' => true,
                    #'pkey' => true,
                    'editable' => false,
                    'extra'    => 'auto_increment',
                ),
                'reship_id'        => array(
                    'type'            => 'table:reship@ome',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '退换货单号',
                    'filterdefault'   => true,
                    'filtertype' => 'normal',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 1,
                    'width'           => 180,
                ),
                'member_id'        => array(
                    'type'            => 'table:members@ome',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '会员名',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 2,
                    'width'           => 130,
                ),
                'ship_area'        => array(
                    'type'            => 'region',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '收货人地区',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 3,
                    'width'           => 130,
                ),
                'bn'               => array(
                    'type'            => 'varchar(50)',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '货号',
                    'filterdefault'   => true,
                    'filtertype' => 'normal',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 4,
                    'width'           => 130,
                ),
                'return_type'      => array(
                    'type'            => array(
                        'return' => '退货',
                        'change' => '换货',
                        'refuse' => '拒收退货',
                    ),
                    'default'         => 'return',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '售后类型',
                    'filterdefault'   => true,
                    'filtertype'      => 'true',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 5,
                    'width'           => 130,
                ),
                'op_id'            => array(
                    'type'            => 'table:account@pam',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '质检人',
                    'in_list'         => true,
                    'filterdefault'   => true,
                    'filtertype' => 'normal',
                    'default_in_list' => true,
                    'order'           => 6,
                    'width'           => 130,
                ),
                'acttime'          => array(
                    'type'            => 'time',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '质检时间',
                    'filterdefault'   => true,
                    'filtertype'      => 'time',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 7,
                    'width'           => 130,
                ),
                'ship_mobile'      => array(
                    'type'            => 'table:reship@ome',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '手机号',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 8,
                    'width'           => 130,
                ),
                'return_logi_name' => array(
                    'type'            => 'table:reship@ome',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '退回物流公司',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 9,
                    'width'           => 130,
                ),
                'return_logi_no'   => array(
                    'type'            => 'table:reship@ome',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '退回物流单号',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 10,
                    'width'           => 130,
                ),
                'memo'             => array(
                    'type'            => 'table:reship@ome',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '售后理由',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 11,
                    'width'           => 130,
                ),
                'is_check'         => array(
                    'type'            => array(
                        0  => '未审核',
                        1  => '审核成功',
                        2  => '审核失败',
                        3  => '收货成功',
                        4  => '拒绝收货',
                        5  => '拒绝',
                        6  => '补差价',
                        7  => '完成',
                        8  => '质检通过',
                        9  => '拒绝质检',
                        10 => '质检异常',
                    ),
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '质检结果',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 12,
                    'width'           => 130,
                ),
                'order_id'         => array(
                    'type'            => 'table:orders@ome',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '订单号',
                    'searchtype'      => 'has',
                    'filterdefault'   => true,
                    'filtertype' => 'normal',
                    'in_list'         => true,
                    'default_in_list' => true,
                    'order'           => 13,
                    'width'           => 130,
                ),
                'return_id'        => array(
                    'type'            => 'table:return_product@ome',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '售后单号',
                    'filterdefault'   => true,
                    'filtertype' => 'normal',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 14,
                    'width'           => 130,
                ),
                'add_time'         => array(
                    'type'            => 'time',
                    'required'        => false,
                    'editable'        => false,
                    'label'           => '售后申请时间',
                    'filterdefault'   => true,
                    'filtertype'      => 'time',
                    'in_list'         => false,
                    'default_in_list' => false,
                    'order'           => 15,
                    'width'           => 130,
                ),
                'rpi_memo' =>
                    array (
                        'type' => 'text',
                        'editable' => false,
                        'label'           => '质检描述',
                    ),
            ),
            'idColumn' => 'item_id',
        );
        foreach ($schema['columns'] as $schema_k => $val) {
            if ($schema_k == 'item_id') {
                continue;
            }

            $schema['default_in_list'][] = $schema_k;
            $schema['in_list'][]         = $schema_k;
        }

        return $schema;
    }

    /**
     * 扩展搜索项
     */
    public function searchOptions()
    {
        return array(
            'order_bn'  => '订单号',
            'reship_bn' => '退换货单号',
        );
    }

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = '';

        //搜索条件
        if (isset($filter['order_id'])) {
            $where .= " AND rpi.order_id=" . intval($filter['order_id']);
            unset($filter['order_id']);
        }

        if (isset($filter['bn'])) {
            $where .= " AND rpi.bn='" . $filter['bn'] . "'";
            unset($filter['bn']);
        }

        if (isset($filter['reship_id'])) {
            $where .= " AND rpi.reship_id=" . intval($filter['reship_id']);
            unset($filter['reship_id']);
        }

        if (isset($filter['return_id'])) {
            $where .= " AND rpi.return_id=" . intval($filter['return_id']);
            unset($filter['return_id']);
        }

        if (isset($filter['return_type'])) {
            $where .= " AND rs.return_type='" . $filter['return_type'] . "'";
            unset($filter['return_type']);
        }

        if (isset($filter['org_id']) && $filter['org_id']) {
            $where .= " AND rs.org_id IN(" . implode(',', $filter['org_id']) . ")";

            unset($filter['org_id']);
        }

        if (isset($filter['add_time'])) {
            if ($filter['_add_time_search'] == 'nequal') {
//等于
                $add_time = $filter['add_time'] . ' ' . $filter['_DTIME_']['H']['add_time'] . $filter['_DTIME_']['M']['add_time'];
                if ($add_time) {
                    $add_time = strtotime($add_time);
                    $where .= " AND srp.add_time=" . $add_time;
                }
            } else if ($filter['_add_time_search'] == 'than') {
//晚于
                $add_time = $filter['add_time'] . ' ' . $filter['_DTIME_']['H']['add_time'] . ':' . $filter['_DTIME_']['M']['add_time'];
                if ($add_time) {
                    $add_time = strtotime($add_time);
                    $where .= " AND srp.add_time<" . $add_time;
                }
            } else if ($filter['_add_time_search'] == 'between') {
//介于
                $add_time_from = $filter['add_time_from'] . ' ' . $filter['_DTIME_']['H']['add_time_from'] . ':' . $filter['_DTIME_']['M']['add_time_from'];
                $add_time_to   = $filter['add_time_to'] . ' ' . $filter['_DTIME_']['H']['add_time_to'] . ':' . $filter['_DTIME_']['M']['add_time_to'];

                if ($add_time_from) {
                    $add_time_from = strtotime($add_time_from);
                    $where .= " AND srp.add_time>" . $add_time_from;
                }
                if ($add_time_to) {
                    $add_time_to = strtotime($add_time_to);
                    $where .= " AND srp.add_time<" . $add_time_to;
                }
            } else if ($filter['_add_time_search'] == 'lthan') {
//早于
                $add_time = $filter['add_time'] . ' ' . $filter['_DTIME_']['H']['add_time'] . ':' . $filter['_DTIME_']['M']['add_time'];
                if ($add_time) {
                    $add_time = strtotime($add_time);
                    $where .= " AND srp.add_time>" . $add_time;
                }
            }
            unset($filter['add_time']);
        }

        if (isset($filter['order_bn'])) {
            $oOrders       = $this->app->model('orders');
            $oItems        = $this->app->model('return_process_items');
            $order_ids = [];
            $order_ids_arr = $oOrders->getList('order_id', array('order_bn|head' => $filter['order_bn']));
            foreach ($order_ids_arr as $row) {
                $order_ids[] = $row['order_id'];
            }
            $archObj       = app::get('archive')->model('orders');
            $archive_order = $archObj->getList('order_id', array('order_bn' => $filter['order_bn']));

            foreach ($archive_order as $row) {
                $order_ids[] = $row['order_id'];
            }
            $where .= " AND rpi.order_id in ('" . implode("','", $order_ids) . "')";
            unset($filter['order_bn']);
        }

        if (isset($filter['reship_bn'])) {
            $oOreship       = $this->app->model('reship');
            $reship_ids_arr = $oOreship->getList('reship_id', array('reship_bn|head' => $filter['reship_bn']));
            
            //reship_id
            $reship_ids = [];
            foreach ($reship_ids_arr as $row) {
                $reship_ids[] = $row['reship_id'];
            }
            
            //reship_id
            if(empty($reship_ids)){
                $where .= " AND rpi.reship_id=-1";
            }else{
                $where .= " AND rpi.reship_id IN ('" . implode("','", $reship_ids) . "')";
            }
            
            unset($filter['reship_bn']);
        }

        if (isset($filter['acttime'])) {
            if ($filter['_acttime_search'] == 'nequal') {
//等于
                $acttime = $filter['acttime'] . $filter['_DTIME_']['H']['acttime'] . $filter['_DTIME_']['M']['acttime'];
                if ($acttime) {
                    $acttime = strtotime($acttime);
                    $where .= " AND rpi.acttime=" . $acttime;

                }

            } else if ($filter['_acttime_search'] == 'than') {
//晚于

                $acttime = $filter['acttime'] . ' ' . $filter['_DTIME_']['H']['acttime'] . ':' . $filter['_DTIME_']['M']['acttime'];

                if ($acttime) {

                    $acttime = strtotime($acttime);

                    $where .= " AND rpi.acttime<" . $acttime;

                }

            } else if ($filter['_acttime_search'] == 'between') {
//介于
                $acttime_from = $filter['acttime_from'] . ' ' . $filter['_DTIME_']['H']['acttime_from'] . ':' . $filter['_DTIME_']['M']['acttime_from'];
                $acttime_to   = $filter['acttime_to'] . ' ' . $filter['_DTIME_']['H']['acttime_to'] . ':' . $filter['_DTIME_']['M']['acttime_to'];

                if ($acttime_from) {
                    $acttime_from = strtotime($acttime_from);
                    $where .= " AND rpi.acttime>" . $acttime_from;
                }
                if ($acttime_to) {
                    $acttime_to = strtotime($acttime_to);
                    $where .= " AND rpi.acttime<" . $acttime_to;
                }

            } else if ($filter['_acttime_search'] == 'lthan') {
//早于

                $acttime = $filter['acttime'] . ' ' . $filter['_DTIME_']['H']['acttime'] . ':' . $filter['_DTIME_']['M']['acttime'];
                if ($acttime) {
                    $acttime = strtotime($acttime);
                    $where .= " AND rpi.acttime>" . $acttime;
                }

            }
            unset($filter['acttime']);
        }
        if (isset($filter['op_id'])) {
            $where .= " AND rpi.op_id=" . $filter['op_id'];
            unset($filter['op_id']);
        }
    
        if (isset($filter['item_id'])) {
            if (is_array($filter['item_id'])) {
                $where .= " AND rpi.item_id IN ('" . implode("','", $filter['item_id']) . "')";
                unset($filter['item_id']);
            }
        }

        return parent::_filter($filter, $tableAlias, $baseWhere) . $where;
    }

    public function count($filter = array())
    {
        //return count($this->getList('*',$filter));

        $sql = 'select count(*) as c from sdb_ome_return_process_items rpi left join sdb_ome_return_process srp on rpi.por_id = srp.por_id left join sdb_ome_reship rs on rpi.reship_id = rs.reship_id left join sdb_ome_return_product orp on rpi.return_id = orp.return_id where rpi.is_check="true" and ' . $this->_filter($filter);

        $row = $this->db->select($sql);

        return intval($row[0]['c']);
    }

    public function getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderby = null)
    {

        $sql = 'select rpi.item_id,rpi.reship_id,rpi.bn,rpi.op_id,rpi.acttime,rpi.order_id,rpi.return_id,rs.return_type,rs.ship_area,rs.ship_mobile,rs.return_logi_name,rs.return_logi_no,rs.is_check,rs.memo,srp.add_time,orp.member_id,rpi.memo as rpi_memo from sdb_ome_return_process_items rpi left join sdb_ome_return_process srp on rpi.por_id = srp.por_id left join sdb_ome_reship rs on rpi.reship_id = rs.reship_id left join sdb_ome_return_product orp on rpi.return_id = orp.return_id where rpi.is_check="true" and ' . $this->_filter($filter);

        if ($orderType) {
            $sql = $sql . " order by " . $orderType;
        }

        $sql = str_replace('`sdb_ome_return_iostock`', 'rpi', $sql);

        $aData = $this->db->selectLimit($sql, $limit, $offset);

        return $aData;
    }

    public function fgetlist_csv(&$data, $filter, $offset, $exportType = 1, $pass_data = false)
    {

        @ini_set('memory_limit', '64M');
        $limit = 100;
        $list  = $this->getreturnIostock($filter, $offset * $limit, $limit);

        if (!$list) {
            return false;
        }

        $csv_title = $this->io_title();

        if (!$data['title']) {
            $title = array();
            foreach ($csv_title as $k => $v) {
                $title[] = $this->charset->utf2local($v);
            }
            $data['title'] = '"' . implode('","', $title) . '"';
        }

        foreach ($list['main'] as $k => $aFilter) {
            foreach ($this->oSchema['csv']['main'] as $kk => $v) {
                $iostockRow[$kk] = $this->charset->utf2local($aFilter[$v]);

            }

            $data['contents'][] = '"' . implode('","', $iostockRow) . '"';
        }

        return true;
    }

    public function export_csv($data, $exportType = 1)
    {

        $output   = array();
        $output[] = $data['title'] . "\n" . implode("\n", (array) $data['contents']);

        echo implode("\n", $output);
    }

    public function exportName(&$data)
    {

        $data['name'] = '售后入库单';
    }

    public function io_title($filter = null, $ioType = 'csv')
    {
        switch ($ioType) {
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:退换货单号'  => 'reship_id',
                    '*:会员名'    => 'member_id',
                    '*:收货人地区'  => 'ship_area',
                    '*:货号'     => 'bn',
                    '*:售后类型'   => 'return_type',
                    '*:质检人'    => 'op_id',
                    '*:质检时间'   => 'acttime',
                    '*:质检描述'   => 'rpi_memo',
                    '*:手机号'    => 'ship_mobile',
                    '*:退回物流公司' => 'return_logi_name',
                    '*:退回物流单号' => 'return_logi_no',
                    '*:售后理由'   => 'memo',
                    '*:质检结果'   => 'is_check',
                    '*:订单号'    => 'order_id',
                    '*:售后单号'   => 'return_id',
                    '*:售后申请时间' => 'add_time',
                );
        }

        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema[$ioType]['main']);
        return $this->ioTitle[$ioType][$filter];
    }

    public function getreturnIostock($filter, $offset, $limit)
    {

        $list = $this->getList('*', $filter, $offset, $limit);

        if (!$list) {
            return false;
        }

        $oOrder  = $this->app->model('orders');
        $oReship = $this->app->model('reship');
        $oReturn = $this->app->model('return_product');
        $oPam    = app::get('pam')->model('account');
        $oMember = $this->app->model('members');

        $archiveOrder = app::get('archive')->model('orders');
        $oShop        = $this->app->model('shop');
        foreach ($list as $key => $value) {
            $order = $oOrder->getList('order_bn', array('order_id' => $value['order_id']), 0, 1);
            if (!$order) {
                $order = $archiveOrder->getList('order_bn,shop_id', array('order_id' => $value['order_id']), 0, 1);
            }
            $reship  = $oReship->getList('reship_bn', array('reship_id' => $value['reship_id']), 0, 1);
            $return  = $oReturn->getList('return_bn', array('return_id' => $value['return_id']), 0, 1);
            $members = $oMember->getList('name', array('member_id' => $value['member_id']), 0, 1);
            $account = $oPam->getList('login_name', array('account_id' => $value['op_id']), 0, 1);
            $corp    = $this->app->model('dly_corp')->getList('name', array('corp_id' => $value['return_logi_name']), 0, 1);
            $columns = $this->get_schema();

            $is_check = $columns['columns']['is_check']['type'][$value['is_check']];

            $list['main'][] = array(
                'reship_id'        => "\t" . $reship[0]['reship_bn'],
                'ship_area'        => $value['ship_area'],
                'member_id'        => $members[0]['name'],
                'ship_name'        => $value['ship_name'],
                'bn'               => $value['bn'],
                'return_type'      => $value['return_type'],
                'op_id'            => $account[0]['login_name'],
                'acttime'          => $value['acttime'] ? date('Y-m-d H:i:s', $value['acttime']) : '-',
                'ship_mobile'      => $value['ship_mobile'],
                'return_logi_name' => $corp[0]['name'],
                'return_logi_no'   => $value['return_logi_no'],
                'memo'             => $value['memo'],
                'is_check'         => $is_check,
                'order_id'         => "\t" . $order[0]['order_bn'],
                'return_id'        => "\t" . $return[0]['return_bn'],
                'add_time'         => $value['add_time'] ? date('Y-m-d H:i:s', $value['add_time']) : '-',
                'rpi_memo'         => $value['rpi_memo'],
            );
        }

        return $list;
    }

    /**
     * 获得日志类型(non-PHPdoc)
     * @see dbeav_model::getLogType()
     */
    public function getLogType($logParams)
    {
        $type    = $logParams['type'];
        $logType = 'none';
        if ($type == 'export') {
            $logType = $this->exportLogType($logParams);
        } elseif ($type == 'import') {
            $logType = $this->importLogType($logParams);
        }
        return $logType;
    }
    /**
     * 导出日志类型
     * @param Array $logParams 日志参数
     */
    public function exportLogType($logParams)
    {
        $params = $logParams['params'];
        $type   = 'bill';
        if ($logParams['app'] == 'ome' && $logParams['ctl'] == 'admin_return') {
            $type .= '_delivery_afterSales';
        }
        $type .= '_export';
        return $type;
    }
    /**
     * 导入操作日志类型
     * @param Array $logParams 日志参数
     */
    public function importLogType($logParams)
    {
        $params = $logParams['params'];
        $type   = 'bill';
        if ($logParams['app'] == 'ome' && $logParams['ctl'] == 'admin_return') {
            $type .= '_delivery_afterSales';
        }
        $type .= '_import';
        return $type;
    }

    //根据过滤条件获取导出主键数据数组
    public function getPrimaryIdsByCustom($filter)
    {
        //过滤掉空的查询条件
        foreach ($filter as $fk => $fv) {
            if ($fv == '') {
                unset($filter[$fk]);
            }
        }

        $where = $this->_filter($filter);
        $where = str_replace('`sdb_ome_return_iostock`', 'rpi', $where);

        $sql = 'select rpi.item_id from sdb_ome_return_process_items rpi
                left join sdb_ome_return_process srp on rpi.por_id = srp.por_id
                left join sdb_ome_reship rs on rpi.reship_id = rs.reship_id
                left join sdb_ome_return_product orp on rpi.return_id = orp.return_id
                where rpi.is_check="true" AND ' . $where;

        $list = $this->db->select($sql, 0, -1);
        if (empty($list)) {
            return false;
        }

        $ids = array();
        foreach ($list as $k => $row) {
            $ids[] = $row['item_id'];
        }

        return $ids;
    }

    //根据主键id获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end)
    {
        $oOrder         = $this->app->model('orders');
        $oReship        = $this->app->model('reship');
        $oReturn        = $this->app->model('return_product');
        $oPam           = app::get('pam')->model('account');
        $oMember        = $this->app->model('members');
        $archive_ordObj = kernel::single('archive_interface_orders');

        $oShop = $this->app->model('shop');

        $ids = $filter['item_id'];
        if (empty($ids)) {
            return false;
        }

        $sql = 'select rpi.item_id,rpi.reship_id,rpi.bn,rpi.op_id,rpi.acttime,rpi.order_id,rpi.return_id,rs.return_type,rs.ship_area,
                rs.ship_mobile,rs.return_logi_name,rs.return_logi_no,rs.is_check,rs.memo,srp.add_time,orp.member_id,rpi.memo as rpi_memo
                from sdb_ome_return_process_items rpi
                left join sdb_ome_return_process srp on rpi.por_id = srp.por_id
                left join sdb_ome_reship rs on rpi.reship_id = rs.reship_id
                left join sdb_ome_return_product orp on rpi.return_id = orp.return_id
                where rpi.item_id in (' . implode(',', $ids) . ') ORDER BY rpi.item_id DESC';
        $dataList = $this->db->select($sql);
        if (empty($dataList)) {
            return false;
        }

        $list = array();

        //如果是第一分片那么加上标题
        if ($curr_sheet == 1) {

            $title       = array();
            $main_schema = $this->get_schema();

            foreach (explode(',', $fields) as $key => $col) {
                if (isset($main_schema['columns'][$col])) {
                    $title[] = "*:" . $main_schema['columns'][$col]['label'];
                }
            }

            foreach ((array) $title as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }

            $list['content']['main'][0] = implode(',', $title);
            unset($main_schema, $detail_schema);
        }

        //组织数据
        $sdf = array();
        foreach ($dataList as $key => $value) {
            $order = $oOrder->getList('order_bn', array('order_id' => $value['order_id']), 0, 1);
            if (!$order) {
                $order = $archive_ordObj->getOrder_list(array('order_id' => $value['order_id']), 'order_bn,shop_id');
            }
            $reship  = $oReship->getList('reship_bn', array('reship_id' => $value['reship_id']), 0, 1);
            $return  = $oReturn->getList('return_bn', array('return_id' => $value['return_id']), 0, 1);
            $members = $oMember->getList('name', array('member_id' => $value['member_id']), 0, 1);
            $account = $oPam->getList('login_name', array('account_id' => $value['op_id']), 0, 1);
            $corp    = $this->app->model('dly_corp')->getList('name', array('corp_id' => $value['return_logi_name']), 0, 1);
            $columns = $this->get_schema();

            $is_check = $columns['columns']['is_check']['type'][$value['is_check']];

            //格式化收货地址
            if ($value['ship_area']) {
                $temp_ship_area     = explode(':', $value['ship_area']);
                $value['ship_area'] = str_replace('/', '-', $temp_ship_area[1]);
            }

            //售后类型
            if ($value['return_type'] == 'return') {
                $value['return_type'] = '退货';
            } elseif ($value['return_type'] == 'change') {
                $value['return_type'] = '换货';
            } elseif ($value['return_type'] == 'refuse') {
                $value['return_type'] = '拒收退货';
            }

            //售后理由过滤,逗号
            $value['memo'] = str_replace(',', ' ', $value['memo']);

            $sdf[] = array(
                'reship_id'        => $reship[0]['reship_bn'],
                'ship_area'        => $value['ship_area'],
                'member_id'        => $members[0]['name'],
                'ship_name'        => $value['ship_name'],
                'bn'               => $value['bn'],
                'return_type'      => $value['return_type'],
                'op_id'            => $account[0]['login_name'],
                'acttime'          => $value['acttime'] ? date('Y-m-d H:i:s', $value['acttime']) : '-',
                'ship_mobile'      => $value['ship_mobile'],
                'return_logi_name' => $corp[0]['name'],
                'return_logi_no'   => $value['return_logi_no'],
                'memo'             => $value['memo'],
                'is_check'         => $is_check,
                'order_id'         => "\t" . $order[0]['order_bn'],
                'return_id'        => "\t" . $return[0]['return_bn'],
                'add_time'         => $value['add_time'] ? date('Y-m-d H:i:s', $value['add_time']) : '-',
                'rpi_memo'         => $value['rpi_memo'],
            );
        }

        //格式化输出的内容字段
        $dataList = $this->convert($sdf, $fields, $has_detail);

        $new_line = 1;
        foreach ($dataList as $key => $item) {

            foreach ($item as $filed_key => $value) {
                $item[$filed_key] = $value ? mb_convert_encoding($value, 'GBK', 'UTF-8') : '-';
            }

            $list['content']['main'][$new_line] = implode(',', $item);

            $new_line++;
        }

        return $list;
    }

    //格式化输出的内容字段
    public function convert($rows, &$fields = '', $has_detail = 1)
    {

        $tmp_rows = array();

        //先处理主数据的排序
        foreach (explode(',', $fields) as $k => $col) {
            foreach ($rows as $key => $row) {
                foreach ($row as $cl => $value) {
                    //只保留配置的主字段
                    if ($col == $cl) {
                        $tmp_rows[$key][$col] = $row[$col] ? $row[$col] : '-';
                    }
                }
            }
        }

        return $tmp_rows;
    }
}
