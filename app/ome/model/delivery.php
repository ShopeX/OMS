<?php

class ome_mdl_delivery extends dbeav_model
{
    const URGENT_LABEL_CODE = 'SOMS_URGENT_SHIP';
    //public $filter_use_like = true;
    public $has_many = array(
        'delivery_items' => 'delivery_items',
        'delivery_order' => 'delivery_order',
        //'dly_items_pos'  => 'dly_items_pos', TODO:和zhangxu确认，是否需要
    );
    public $defaultOrder       = array('delivery_id', ' ASC');
    public $deliveryOrderModel = null;

    /**
     * 须加密字段
     *
     * @var string
     **/
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_tel'    => 'phone',
        'ship_mobile' => 'phone',
        'ship_addr'     => 'simple',
    );

    public function __construct($app)
    {
        if ($_GET['status'] == '0' || $_GET['status'] == '') {
            $opInfo = kernel::single('ome_func')->getDesktopUser();
            if (app::get('ome')->getConf('delivery.bycreatetime' . $opInfo['op_id']) == 1) {
                $this->defaultOrder = array('order_createtime', ' ASC');
            } else {
                $this->defaultOrder = array('idx_split', ' ASC');
            }
        } else {
            $this->defaultOrder = array('delivery_id', ' DESC');
        }
        parent::__construct($app);
    }

    /**
     * 扩展发货单 Finder 过滤条件，支持加急标签相关筛选与自定义 SQL 条件。
     *
     * @param array $filter
     * @param string|null $tableAlias
     * @param array|null $baseWhere
     * @return string
     */
    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $tPre      = ($tableAlias ? $tableAlias : '`' . $this->table_name(true) . '`') . '.';
        $tmpBaseWhere = kernel::single('ome_filter_encrypt')->encrypt($filter, $this->__encrypt_cols, $tPre, 'delivery');
        $baseWhere = $baseWhere ? array_merge((array)$baseWhere, (array)$tmpBaseWhere) : (array)$tmpBaseWhere;

        //setting
        $deliveryIds = array();
        $where = '';

        //filter
        if (isset($filter['extend_delivery_id'])) {
            //delivery_id
            $tempDlyIds = array(0);
            foreach($filter['extend_delivery_id'] as $extend_delivery_id)
            {
                $tempDlyIds[$extend_delivery_id] = $extend_delivery_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            unset($filter['extend_delivery_id']);
        }

        if (isset($filter['member_uname'])) {
            $memberObj  = $this->app->model("members");
            $rows       = $memberObj->getList('member_id', array('uname|has' => $filter['member_uname']));
            $memberId[] = 0;
            foreach ($rows as $row) {
                $memberId[] = $row['member_id'];
            }
            $where .= '  AND member_id IN (' . implode(',', $memberId) . ')';
            unset($filter['member_uname']);
        }
        
        //按订单号搜索
        if (isset($filter['order_bn'])) {
            // 多订单号查询
            if(strpos($filter['order_bn'], "\n") !== false){
                $filter['order_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));
            }
            
            $orderObj  = $this->app->model("orders");
            $rows      = $orderObj->getList('order_id', array('order_bn' => $filter['order_bn']));
            $orderId[] = 0;
            foreach ($rows as $row) {
                $orderId[] = $row['order_id'];
            }

            $deliOrderObj = $this->app->model("delivery_order");
            $rows         = $deliOrderObj->getList('delivery_id', array('order_id' => $orderId));

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            unset($filter['order_bn']);
        }
        
        //[SKU基础物料号]多货号查询
        if (isset($filter['material_bn'])){
            if(strpos($filter['material_bn'], "\n") !== false){
                $material_bns = array_unique(array_map('trim', array_filter(explode("\n", $filter['material_bn']))));

                $filter['material_bn'] = $material_bns;
            }
            
            //search_delivery_items
            $itemsObj = app::get('ome')->model("delivery_items");
            $rows = $itemsObj->getDeliveryIdByPbn($filter);

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            unset($filter['material_bn']);
        }
        
        //[单个货号]按货号查询
        if (isset($filter['product_bn'])) {
            $where .= '  AND ' . $tPre . 'bnsContent like \'%' . $filter['product_bn'] . '%\'';
            unset($filter['product_bn']);
        }
        
        //按条形码搜索
        if (isset($filter['product_barcode'])) {
            //search_delivery_items
            $itemsObj = $this->app->model("delivery_items");
            $rows = $itemsObj->getDeliveryIdByPbarcode($filter);

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            //unset
            unset($filter['product_barcode']);
        }
        
        if (isset($filter['logi_no_ext'])) {
            $logObj       = $this->app->model("delivery_log");
            $rows         = $logObj->getDeliveryIdByLogiNO($filter['logi_no_ext']);

            //delivery_id
            $tempDlyIds = array(0);
            foreach($rows as $row)
            {
                $temp_dly_id = $row['delivery_id'];

                $tempDlyIds[$temp_dly_id] = $temp_dly_id;
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            unset($filter['logi_no_ext']);
        }

        if (isset($filter['addonSQL'])) {
            $where .= ' AND ' . $filter['addonSQL'];
            unset($filter['addonSQL']);
        }
        if (!empty($filter['delivery_label_code'])) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM sdb_ome_bill_label bl
                WHERE bl.bill_id = '.$tPre.'delivery_id
                AND bl.bill_type = "delivery"
                AND bl.label_code = "'.addslashes($filter['delivery_label_code']).'"
            )';
            unset($filter['delivery_label_code']);
        }
        if (isset($filter['urgent_delivery'])) {
            if ($filter['urgent_delivery'] === 'true' || $filter['urgent_delivery'] === true || $filter['urgent_delivery'] == 1) {
                $where .= ' AND EXISTS (
                    SELECT 1 FROM sdb_ome_bill_label bl
                    WHERE bl.bill_id = '.$tPre.'delivery_id
                    AND bl.bill_type = "delivery"
                    AND bl.label_code = "'.self::URGENT_LABEL_CODE.'"
                )';
            } else {
                $where .= ' AND NOT EXISTS (
                    SELECT 1 FROM sdb_ome_bill_label bl
                    WHERE bl.bill_id = '.$tPre.'delivery_id
                    AND bl.bill_type = "delivery"
                    AND bl.label_code = "'.self::URGENT_LABEL_CODE.'"
                )';
            }
            unset($filter['urgent_delivery']);
        }
        if (isset($filter['delivery_ident'])) {
            //delivery_id
            $tempDlyIds = array(0);

            $arr_delivery_ident = explode('_', $filter['delivery_ident']);
            $mdl_queue          = app::get('ome')->model("print_queue");
            if (count($arr_delivery_ident) == 2) {
                $ident_dly = array_pop($arr_delivery_ident);
                $ident     = implode('-', $arr_delivery_ident);
                $queueItem = $mdl_queue->findQueueItem($ident, $ident_dly);
                if ($queueItem) {
                    $temp_dly_id = $queueItem['delivery_id'];
                    $tempDlyIds[$temp_dly_id] = $temp_dly_id;
                }
            } else {
                if (1 == substr_count($filter['delivery_ident'], '-')) {
                    $queues = $mdl_queue->getList('dly_bns', array('ident|head' => $filter['delivery_ident']));
                    if ($queues) {
                        //$queue['dly_bns'] = implode(',', array_map('current', $queues));
                        $tempDlyIds = array_map('current', $queues);
                    }

                } else {
                    //获取实际的打印批次号
                    $delivery_id = $mdl_queue->findQueueDeliveryId($filter['delivery_ident'], 'delivery_id');
                    if ($delivery_id) {
                        //$queue['dly_bns'] = $delivery_id;
                        $tempDlyIds = explode(',', $delivery_id);
                    }
                }
            }

            //intersection
            if(empty($deliveryIds)){
                $deliveryIds = $tempDlyIds;
            }else{
                $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
            }

            unset($filter['delivery_ident']);
        }

        if (isset($filter['ship_tel_mobile'])) {
            $where .= ' AND (ship_tel=\'' . $filter['ship_tel_mobile'] . '\' or ship_mobile=\'' . $filter['ship_tel_mobile'] . '\')';
            unset($filter['ship_tel_mobile']);
        }

        if ($filter['todo'] == 1) {
            $where .= " AND ({$tPre}stock_status='false' or {$tPre}expre_status='false' or {$tPre}deliv_status='false')";
            unset($filter['todo']);
        }

        if ($filter['todo'] == 2) {
            $where .= " AND ({$tPre}stock_status='false' or {$tPre}expre_status='false')";
            unset($filter['todo']);
        }

        if ($filter['todo'] == 3) {
            $where .= " AND ({$tPre}expre_status='false' or {$tPre}deliv_status='false')";
            unset($filter['todo']);
        }

        if ($filter['todo'] == 4) {
            $where .= " AND {$tPre}expre_status='false'";
            unset($filter['todo']);
        }

        if (isset($filter['print_finish'])) {
            $where_or = array();
            foreach ((array) $filter['print_finish'] as $key => $value) {
                $or = "(deli_cfg='" . $key . "'";
                switch ($value) {
                    case '1_1':
                        $or .= " AND {$tPre}stock_status='true' AND {$tPre}deliv_status='true' ";
                        break;
                    case '1_0':
                        $or .= " AND {$tPre}stock_status='true' ";
                        break;
                    case '0_1':
                        $or .= " AND {$tPre}deliv_status='true' ";
                        break;
                    case '0_0':
                        break;
                }
                $or .= ')';
                $where_or[] = $or;
            }
            if ($where_or) {
                $where .= ' AND (' . implode(' OR ', $where_or) . ')';
            }
            unset($filter['print_finish']);
        }
        
        if (isset($filter['ext_branch_id'])) {
            if (isset($filter['branch_id'])) {
                $filter['branch_id'] = array_intersect((array) $filter['branch_id'], (array) $filter['ext_branch_id']);
                $filter['branch_id'] = $filter['branch_id'] ? $filter['branch_id'] : 'false';
            } else {
                $filter['branch_id'] = $filter['ext_branch_id'];
            }
            unset($filter['ext_branch_id']);
        }

        if (isset($filter['no_logi_no']) && $filter['no_logi_no'] == 'NULL') {
            $where .= "AND {$tPre}logi_no is null";
            unset($filter['no_logi_no']);
        }

        //客服备注
        if (isset($filter['mark_text'])) {
            $mark_text = $filter['mark_text'];
            $sql       = "SELECT do.delivery_id FROM sdb_ome_delivery_order do JOIN sdb_ome_orders o ON do.order_id=o.order_id  and o.process_status='splited' and  o.mark_text like " . "'%{$mark_text}%'";
            $_rows     = $this->db->select($sql);
            if (!empty($_rows)) {
                $tempDlyIds = array(0);
                foreach ($_rows as $_orders)
                {
                    $temp_dly_id = $_orders['delivery_id'];

                    $tempDlyIds[$temp_dly_id] = $temp_dly_id;
                }

                //intersection
                if(empty($deliveryIds)){
                    $deliveryIds = $tempDlyIds;
                }else{
                    $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
                }

                unset($filter['mark_text']);
            }
        }
        
        //买家留言
        if (isset($filter['custom_mark'])) {
            $custom_mark = $filter['custom_mark'];
            $sql         = "SELECT do.delivery_id FROM sdb_ome_delivery_order do JOIN sdb_ome_orders o ON do.order_id=o.order_id  and o.process_status='splited' and  o.custom_mark like " . "'%{$custom_mark}%'";
            $_rows       = $this->db->select($sql);
            if (!empty($_rows)) {
                $tempDlyIds = array(0);
                foreach ($_rows as $_orders)
                {
                    $temp_dly_id = $_orders['delivery_id'];

                    $tempDlyIds[$temp_dly_id] = $temp_dly_id;
                }

                //intersection
                if(empty($deliveryIds)){
                    $deliveryIds = $tempDlyIds;
                }else{
                    $deliveryIds = array_intersect($deliveryIds, $tempDlyIds);
                }

                unset($filter['custom_mark']);
            }
        }
        
        //delivery_ids
        if($deliveryIds){
            $where .= " AND delivery_id IN (". implode(',', $deliveryIds) .")";
        }

        return parent::_filter($filter, $tableAlias, $baseWhere) . $where;
    }

    public function getParentIdBybn($delivery_bn)
    {
        $sql  = 'SELECT parent_id from sdb_ome_delivery WHERE parent_id>0 and delivery_bn like \'%' . $delivery_bn . '%\' GROUP BY parent_id';
        $rows = $this->db->select($sql);
        return $rows;
    }

    /**
     * 取得物流公司名称
     */
    public function getLogi_name()
    {
        $sql = " SELECT logi_id,logi_name FROM sdb_ome_delivery GROUP BY logi_id ";
        $row = $this->db->select($sql);
        return $row;
    }

    /**
     * 判断是否已有此物流单号，检验前物流单号可以任意修改
     *
     * @param string $logi_no
     * @param int $dly_id
     * @return boolean
     */
    public function existExpressNo($logi_no, $dly_id = 0)
    {

        if ($logi_no) {
            $count = $this->db->selectRow('select delivery_id from sdb_ome_delivery where  logi_no="' . $logi_no . '" AND `status` in(\'progress\',\'succ\',\'progress\',\'ready\',\'stop\',\'failed\')');

            //检测delivery_bill是否存在快递单号 wujian@shopex.cn 2012年3月13日
            $billrow = $this->db->selectRow('select delivery_id from sdb_ome_delivery_bill where logi_no="' . $logi_no . '"');

            if (($count && $count['delivery_id'] != $dly_id) || $billrow) {
                unset($count);
                unset($billrow);
                return true;
            }
        }
    }

    /**
     * 判断是否已有此物流单号，检验前物流单号可以任意修改(反向检测)
     * wujian@shopex.cn
     * 2012年3月22日
     */
    public function existExpressNoBill($logi_no, $dly_id = 0, $billid = 0)
    {
        //更新，conut走架构
        $filter['logi_no']             = $logi_no;
        $filter['delivery_id|noequal'] = $dly_id; //不等于，见dbeav：filter
        $filter['verify']              = 'true';
        $filter['status']              = array('progress', 'succ');

        $count = $this->count($filter);
        //检测delivery_bill是否存在快递单号 wujian@shopex.cn 2012年3月13日
        $billrow = $this->db->selectRow('select * from sdb_ome_delivery_bill where log_id!=' . $billid . ' and logi_no="' . $logi_no . '"');
        if ($count > 0 || $billrow) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 判断发货单是否已合并过，
     *
     * @param array() $dly_ids
     * @return boolean
     */
    public function existIsMerge($dly_ids)
    {
        $ids = implode(',', $dly_ids);
        //更新，conut走架构
        $filter['delivery_id|in']    = $ids;
        $filter['parent_id|noequal'] = 0;

        $count = $this->count($filter);
        if ($count > 0) {
            return true;
        }

        return false;
    }

    /**
     * 判断发货单是否为合并后的发货单
     *
     * @param array() $dly_ids
     * @return boolean
     */
    public function existIsMerge_parent($dly_id)
    {
        $sql = "SELECT is_bind FROM sdb_ome_delivery where delivery_id = {$dly_id}";
        $row = $this->db->select($sql);
        if ($row[0]['is_bind'] == 'true') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 更新发货单详情
     *
     * @param array() $dly
     * @param array() $delivery_id
     * @return boolean
     */
    public function updateDelivery($dly, $delivery_id)
    {

        $result = $this->update($dly, $delivery_id);
        if ($result) {
            kernel::single('ome_event_trigger_shop_delivery')->delivery_logistics_update($delivery_id);}
        return $result;
    }

    /**
     * 记录商品当天仓库的发货数量
     *
     * @param int $branch_id
     * @param int $number
     * @param int $product_id
     *
     * @return boolean
     */
    public function createStockChangeLog($branch_id, $number, $product_id)
    {
        $basicMaterialLib = kernel::single('material_basic_material');

        $branchObj   = $this->app->model('branch');
        $stock_clObj = $this->app->model('stock_change_log');

        $branch = $branchObj->dump($branch_id);
        $day    = $branch['stock_safe_day'];
        $time   = $branch['stock_safe_time'];

        $log_bn   = date('Ymd');
        $now      = time();
        $todaylog = $stock_clObj->dump(array('log_bn' => $log_bn, 'product_id' => $product_id, 'branch_id' => $branch_id));
        if ($todaylog) {
            $log['log_id'] = $todaylog['log_id'];
            $log['store']  = $todaylog['store'] + $number;

            $stock_clObj->save($log);
        } else {
            $log['product_id']  = $product_id;
            $log['branch_id']   = $branch_id;
            $log['log_bn']      = $log_bn;
            $log['create_time'] = time();
            $log['store']       = $number;

            $bMaterialRow = $basicMaterialLib->getBasicMaterialExt($product_id);

            $log['bn']           = $bMaterialRow['material_bn'];
            $log['product_name'] = $bMaterialRow['material_name'] . ($bMaterialRow['spec_desc'] ? "(" . $bMaterialRow['spec_desc'] . ")" : "");

            $stock_clObj->save($log);
        }
        unset($branch, $todaylog, $bMaterialRow, $log);
        return true;
    }

    /**
     * 判断发货单对应的订单处理状态是否为取消或异常
     *
     * @param bigint $dly_id
     * @param string $is_bind
     *
     * @return boolean
     */
    public function existOrderStatus($dly_id, $is_bind)
    {
        if ($is_bind == 'true') {
            $ids = $this->getItemsByParentId($dly_id);
        } else {
            $ids = $dly_id;
        }
        //$sql = "SELECT COUNT(*) AS '_count'  FROM sdb_ome_delivery_order dord JOIN sdb_ome_orders o ON dord.order_id=o.order_id WHERE dord.delivery_id in ($ids) AND (o.process_status='cancel' OR o.abnormal='true' OR o.disabled='true') ";
        $sql = "SELECT COUNT(*) AS '_count'  FROM sdb_ome_delivery WHERE delivery_id in ($ids) AND (status='cancel' OR status='back' OR status='timeout' OR status='failed' OR disabled='true' OR pause='true' OR status='return_back') ";
        $row = $this->db->select($sql);
        if ($row[0]['_count'] > 0) {
            return false;
        } else {
            return true;
        }
    }

    public function existOrderPause($dly_id, $is_bind)
    {
        if ($is_bind == 'true') {
            $ids = $this->getItemsByParentId($dly_id);
        } else {
            $ids = $dly_id;
        }
        $sql = "SELECT COUNT(*) AS '_count'  FROM sdb_ome_delivery_order dord JOIN sdb_ome_orders o ON dord.order_id=o.order_id WHERE dord.delivery_id in ($ids) AND (o.process_status='cancel' OR o.abnormal='true' OR o.disabled='true' OR o.pause='true' OR pay_status='6' OR pay_status='7' OR pay_status='5') ";
        $row = $this->db->select($sql);
        if ($row[0]['_count'] > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取与本发货单配送信息(bind_key)相同的发货单列表(父id为0)
     *
     * @param bigint $dly_id
     *
     * @return array()
     */
    public function getSameKeyList($dly_id)
    {
        $dly                 = $this->dump($dly_id, 'bind_key');
        $filter['bind_key']  = $dly['bind_key'];
        $filter['process']   = 'false';
        $filter['status']    = array('ready', 'progress');
        $filter['type']      = 'normal';
        $filter['parent_id'] = '0';
        $data                = $this->getList('*', $filter, 0, -1);
        foreach ($data as $key => $item) {
            if ($this->existOrderStatus($item['delivery_id'], $item['is_bind']) && $this->existOrderPause($item['delivery_id'], $item['is_bind'])) {
                $data[$key]['order_status'] = 'OK';
            } else {
                $data[$key]['order_status'] = 'ERROR';
            }
            if ($item['is_bind'] == 'true') {
                $data[$key]['ids'] = $this->getItemsByParentId($item['delivery_id'], 'array', '*');
            }
        }
        return $data;
    }

    /**
     * 利用父ID获取子发货单的ID
     *
     * @param bigint $parent_id
     *
     * @return string/array         id字符串或id数组或者id对应所有数据
     */
    public function getItemsByParentId($parent_id, $return = 'string', $column = 'delivery_id')
    {
        $filter['parent_id'] = $parent_id;
        $rows                = $this->getList($column, $filter, 0, -1);
        if (empty($rows)) {
            if($return == 'string') {
                $ids = '0';
            }else{
                $ids = array($parent_id);
            }

            return $ids;
        }

        foreach ($rows as $item) {
            $data[] = $item['delivery_id'];
        }
        if ($return == 'string') {
            $ids = implode(',', $data);
        } elseif ($column == 'delivery_id') {
            $ids = $data;
        } else {
            foreach ($rows as $key => $item) {
                if ($this->existOrderStatus($item['delivery_id'], $item['is_bind']) && $this->existOrderPause($item['delivery_id'], $item['is_bind'])) {
                    $rows[$key]['order_status'] = 'OK';
                } else {
                    $rows[$key]['order_status'] = 'ERROR';
                }
            }
            $ids = $rows;
        }

        return $ids;
    }

    /**
     * 拆分发货单
     * 作用：先将大的发货单拆分掉，再将不需要拆分的子发货单合并
     *
     * @param bigint $parent_id
     * @param array  $items            需要拆分的items
     * @param boolean  $must_log            订单收订叫回失败记录日志
     *
     * @return boolean
     */
    public function splitDelivery($parent_id, $items = '', $must_log = false)
    {

        $opObj = $this->app->model('operation_log');
        //获取发货单信息
        $dly = $this->dump(array('delivery_id' => $parent_id));

        //发货通知单暂停推送仓库
        $notice_params = array(
            'delivery_id' => $dly['delivery_id'],
            'delivery_bn' => $dly['delivery_bn'],
            'branch_id'   => $dly['branch_id'],
        );

        $res = ome_delivery_notice::cancel($notice_params, true);
        if ($res['rsp'] == 'success' || $res['rsp'] == 'succ') {

            $data['logi_no'] = null;
            $data['status'] = 'back';
            $data['delivery_id'] = $parent_id;
            $affect_row = $this->update($data,array('delivery_id' => $parent_id,'status' => array('ready','progress')));

            if (!is_numeric($affect_row) || $affect_row <= 0) {
                
                return false;
            }
            $opObj->write_log('delivery_split@ome', $parent_id, '合并发货单叫回');
         
            $ids = $this->getItemsByParentId($parent_id, 'array');
           
            foreach($ids as $id){

                if (empty($items) || ($items && !in_array($id,$items))) {
                   
                    $this->rebackDelivery($id, '合并发货单叫回', false, false);

                }
            }
            // 发货单取消后的service扩展点
            foreach(kernel::servicelist('ome.service.delivery.cancel.after') as $object) {
                if(method_exists($object, 'cancel_delivery_after')) {
                    $object->cancel_delivery_after($parent_id);
                }
            }
            return true;
            
            
            
        } else {
            $opObj->write_log('delivery_back@ome', $parent_id, '发货单拆分,取消通知仓库:失败,原因:' . $res['msg']);
            //订单收订触发叫回失败的，需额外记录失败日志并展示
            if ($must_log) {
                kernel::single('console_delivery')->update_sync_status($parent_id, 'cancel_fail', $res['msg']);
            }
            return false;
        }
    }

    /**
     * 合并发货单处理
     *
     * @param array() $dly_ids
     * @param array() $delivery => array(
    'logi_id' => '',
    'logi_name' => '',

    'delivery_bn' => '',
    'logi_no' => '',
    'status' => '',
    'stock_status' => '',
    'deliv_status' => '',
    'expre_status' => '',
     *                             );
     * $delivery的键值可以只传前两个或者都传
     *
     * @return boolean
     */
    public function merge($dly_ids, $delivery = array())
    {
        if (!is_array($dly_ids)) {
            return false;
        }

        if (count($dly_ids) < 2) {
            return false;
        }

        if ($delivery) {
            if (!$delivery['logi_id']) {
                return false;
            }

            $tmp = $delivery;
            unset($delivery['logi_id'], $delivery['logi_name'], $delivery['memo'], $delivery['logi_no']);

            if ($delivery) {
                if (!$delivery['delivery_bn']) {
                    return false;
                }

                return $this->mergeDelivery($dly_ids, $tmp);
            } else {
                $dly['logi_id']      = $tmp['logi_id'];
                $dly['logi_name']    = $tmp['logi_name'];
                $dly['memo']         = $tmp['memo'];
                $dly['logi_no']      = $tmp['logi_no'] ? $tmp['logi_no'] : null; #菜鸟的智选物流会返回物流单号
                $dly['delivery_bn']  = $this->gen_id();
                $dly['status']       = 'ready';
                $dly['stock_status'] = 'false';
                $dly['deliv_status'] = 'false';
                $dly['expre_status'] = 'false';
                return $this->mergeDelivery($dly_ids, $dly);
            }
        } else {
            $tmp = $dly_ids;
            $id  = array_shift($tmp);
            unset($tmp);
            $delivery = $this->dump($id, 'logi_id,logi_name');

            $dly['logi_id']      = $delivery['logi_id'];
            $dly['logi_name']    = $delivery['logi_name'];
            $dly['logi_no']      = null;
            $dly['delivery_bn']  = $this->gen_id();
            $dly['status']       = 'ready';
            $dly['stock_status'] = 'false';
            $dly['deliv_status'] = 'false';
            $dly['expre_status'] = 'false';

            return $this->mergeDelivery($dly_ids, $dly);
        }
    }

    /**
     * 合并发货单
     *
     * @param array() $dly_ids
     *
     * @return boolean
     */
    public function mergeDelivery($dly_ids, $delivery)
    {
        if ($dly_ids && is_array($dly_ids)) {
            foreach ($dly_ids as $key => $_id) {
                $_dly = $this->db->selectrow("SELECT delivery_id,delivery_bn,parent_id FROM sdb_ome_delivery WHERE delivery_id=" . $_id);
                if ($_dly['parent_id'] != 0) {
                    trigger_error("发货单:" . $_dly['delivery_bn'] . "已合并过", E_USER_ERROR);
                    return false;
                    //unset($dly_ids[$key]);
                }
            }
        }
        if (count($dly_ids) < 2) {
            return false;
        }

        $dly_corpObj = app::get('ome')->model('dly_corp');
        $opObj       = $this->app->model('operation_log');
        if (!is_array($dly_ids)) {
            return false;
        }

        $new_ids    = array(); //单个小发货单ID数组
        $net_weight = 0;
        $weight     = 0;
        $bool_type = 0; $r_time = ''; $delivery_time = ''; $promised_collect_time = ''; $promised_sign_time = ''; $cpup_service = [];
        $promiseServiceList = [];
        foreach ($dly_ids as $item) {
            $dly = $this->dump($item);
            //$net_weight += $dly['net_weight'];
            #合并发货单计算净重
            $bool_type = $bool_type | $dly['bool_type'];
            $weight += $dly['weight'];
            if ($dly['is_bind'] == 'true') {
                $ids = $this->getItemsByParentId($item, 'array');
                if (is_array($ids)) {
                    $parents[] = $item; //大发货单ID数组
                    $new_ids   = array_merge($new_ids, $ids);
                }
            } else {
                $new_ids[] = $item;
            }
    
            //天猫物流升级
            if (kernel::single('ome_delivery_bool_type')->isCPUP($bool_type)) {
                if ($dly['consignee']['r_time']) {
                    $r_time = $r_time ? min($r_time, $dly['consignee']['r_time']) : $dly['consignee']['r_time'];
                }
        
                if ($dly['delivery_time']) {
                    $delivery_time = $delivery_time ? min($delivery_time, $dly['delivery_time']) : $dly['delivery_time'];
                }
                
                if ($dly['promised_collect_time']) {
                    $promised_collect_time = $promised_collect_time ? min($promised_collect_time, $dly['promised_collect_time']) : $dly['promised_collect_time'];
                }
                
                if ($dly['promised_sign_time']) {
                    $promised_sign_time = $promised_sign_time ? min($promised_sign_time, $dly['promised_sign_time']) : $dly['promised_sign_time'];
                }
        
                $cpup_service = array_merge($cpup_service, explode(',', $dly['cpup_service']));
            }elseif(kernel::single('ome_delivery_bool_type')->isAoxiang($dly['bool_type'])){
                //翱象业务
                //计划发货时间(取最早时间)
                if($dly['delivery_time']){
                    $delivery_time = $delivery_time ? min($delivery_time, $dly['delivery_time']) : $dly['delivery_time'];
                }

                //承诺最晚送达时间(取最早时间)
                if($dly['promised_sign_time']){
                    $promised_sign_time = $promised_sign_time ? min($promised_sign_time, $dly['promised_sign_time']) : $dly['promised_sign_time'];
                }

                //计划发货时间(取最早时间)
                if($dly['promised_collect_time']){
                    $promised_collect_time = $promised_collect_time ? min($promised_collect_time, $dly['promised_collect_time']) : $dly['promised_collect_time'];
                }

                //计划发货时间(取最早时间)
                if($dly['promise_outbound_time']){
                    $promise_outbound_time = $promise_outbound_time ? min($promise_outbound_time, $dly['promise_outbound_time']) : $dly['promise_outbound_time'];
                }

                //计划发货时间(取最早时间)
                if($dly['plan_sign_time']){
                    $plan_sign_time = $plan_sign_time ? min($plan_sign_time, $dly['plan_sign_time']) : $dly['plan_sign_time'];
                }

                //物流服务标签(取合集)
                if($dly['promise_service']){
                    $promiseServiceList = array_merge($promiseServiceList, explode(',', $dly['promise_service']));
                    $promiseServiceList = array_unique($promiseServiceList);
                }

                //物流升级服务
                if($dly['cpup_service']){
                    $cpup_service = array_merge($cpup_service, explode(',', $dly['cpup_service']));
                    $cpup_service = array_unique($cpup_service);
                }
            }

        }

        #获取发货单累计重量
        foreach ($dly_ids as $dk => $dv) {
            $dlys = $this->dump($dv, 'net_weight');

            if ($dlys['net_weight'] > 0) {
                $net_weight += $dlys['net_weight'];
            } else {
                $net_weight = 0;
                break;
            }
        }

        if (count($new_ids) < 2) {
            return false;
        }

        unset($dly['net_weight']);
        unset($dly['delivery_id']);
        unset($dly['verify']);
        unset($dly['cost_protect']);

        //计算合并后的大发货单的预计物流费用
        $area    = $dly['consignee']['area'];
        $arrArea = explode(':', $area);
        $area_id = $arrArea[2];
        $price   = $this->getDeliveryFreight($area_id, $delivery['logi_id'], $net_weight);

        if ($delivery['logi_id']) {
            $dly_corp  = $dly_corpObj->dump($delivery['logi_id']);
            $logi_name = $dly_corp['name'];
            //计算保价费用
            $protect = $dly_corp['protect'];
            if ($protect == 'true') {
                $is_protect    = 'true';
                $protect_rate  = $dly_corp['protect_rate']; //保价费率
                $protect_price = $protect_rate * $net_weight;
                $minprice      = $dly_corp['minprice']; //最低报价费用
                if ($protect_price < $minprice) {
                    $cost_protect = $minprice;
                } else {
                    $cost_protect = $protect_price;
                }
            }
        }

        $dly['cost_protect']             = $cost_protect;
        $dly['is_protect']               = $is_protect ? $is_protect : 'false';
        $new_dly                         = $dly; //新的大发货单sdf结构
        $new_dly['memo']                 = $delivery['memo'];
        $new_dly['delivery_bn']          = $delivery['delivery_bn'];
        $new_dly['net_weight']           = $net_weight;
        $new_dly['weight']               = $weight;
        $new_dly['is_bind']              = 'true';
        $new_dly['logi_no']              = $delivery['logi_no'];
        $new_dly['logi_id']              = $delivery['logi_id'];
        $new_dly['logi_name']            = $delivery['logi_name'];
        $new_dly['parent_id']            = 0;
        $new_dly['status']               = $delivery['status'];
        $new_dly['stock_status']         = $delivery['stock_status'];
        $new_dly['deliv_status']         = $delivery['deliv_status'];
        $new_dly['expre_status']         = $delivery['expre_status'];
        $new_dly['delivery_cost_expect'] = $price;
        $new_dly['bool_type']            = $bool_type;
        $new_dly['cpup_service']         = implode(',', $cpup_service);
    
        if ($r_time) {
            $new_dly['ship_time'] = $r_time;
        }

        //未发货之前,不能赋值,否则客户导出时有异议
//        if ($delivery_time){
//            $new_dly['delivery_time'] = $delivery_time;
//        }

        if ($promised_collect_time){
            $new_dly['promised_collect_time'] = $promised_collect_time;
        }
        if ($promised_sign_time){
            $new_dly['promised_sign_time'] = $promised_sign_time;
        }
        //获取大发货单的订单创建时间 取各个发货单最小的订单创建时间
        $order_createtime = $this->getDeliveryOrderCreateTime($dly_ids);
        if ($order_createtime) {
            $new_dly['order_createtime'] = $order_createtime;
        }

        //承诺最晚出库时间
        if($promise_outbound_time){
            $new_dly['promise_outbound_time'] = $promise_outbound_time;
        }

        //承诺计划送达时间
        if($plan_sign_time){
            $new_dly['plan_sign_time'] = $plan_sign_time;
        }

        //物流服务标签
        if($promiseServiceList){
            $new_dly['promise_service'] = implode(',', $promiseServiceList);
        }

        //save
        if ($this->save($new_dly)) {
            //创建大发货单
            if ($parents && is_array($parents)) {
                foreach ($parents as $p_id) {
                    $this->splitDelivery($p_id, '', false);
                }
            }

            foreach ($new_ids as $id) {
                $tmp_dly = array(
                    'delivery_id' => $id,
                    'logi_no'     => null,
                    'parent_id'   => $new_dly['delivery_id'],
                );
                $this->save($tmp_dly);
            }
            $this->insertParentItemByItems($new_dly['delivery_id'], $new_ids, $dly['branch_id']); //新增大发货单与小发货单的明细关联
            $this->insertParentOrderByItems($new_dly['delivery_id'], $new_ids);
            $this->insertParentItemDetailByItemsDetail($new_dly['delivery_id'], $new_ids); //2011.03.15新增（为发货单详情绑定订单商品关联）
            ////////////////////////////////为合并后的发货单生成统计字段///////////////////////////////////
            $bns      = array();
            $totalNum = 0;
            $diObj    = $this->app->model('delivery_items');
            $dis      = $diObj->getList('*', array('delivery_id' => $new_dly['delivery_id']), 0, -1);
            foreach ($dis as $v) {
                $totalNum += $v['number'];
                $bns[$v['product_id']] = $v['bn'];
            }
            ksort($bns);
            //11.25新增
            $data['skuNum']     = count($dis);
            $data['itemNum']    = $totalNum;
            $data['bnsContent'] = serialize($bns);
            $data['idx_split']  = $data['skuNum'] * 10000000000 + sprintf("%u", crc32($data['bnsContent']));

            $data['delivery_id'] = $new_dly['delivery_id'];
            $this->save($data);
            /////////////////////////////////////////////////////////////////////////////////////////////////////

            $merge_dly = $this->getList("delivery_bn", array('delivery_id' => $new_ids), 0, -1);
            foreach ($merge_dly as $v) {
                $tmp_idd[] = $v['delivery_bn'];
            }
            $idd = implode(",", $tmp_idd);

            $opObj->write_log('delivery_merge@ome', $new_dly['delivery_id'], '合并发货单(' . $idd . ')');
            return $new_dly['delivery_id'];
        }
        return false;
    }

    /**
     * 重置发货单
     * 使用：将发货单状态设置为初始状态 ，物流运单号设置为空
     *
     * @param array() $filter
     *
     * @return boolean
     */
    public function resumeDelivery($filter, $cancel_items)
    {
        $rows           = $this->getList('*', $filter, 0, -1);
        $oOperation_log = app::get('ome')->model('operation_log');
        if (empty($rows)) {
            return false;
        }

        $dly_itemObj = $this->app->model('delivery_items');

        foreach ($rows as $r) {
            $data['parent_id'] = 0;
            $data['logi_no']   = null;
            if ($r['status'] == 'cancel' || $r['status'] == 'back') {
                $data['status'] = $r['status'];
            } else {
                if (empty($cancel_items) || ($cancel_items && !in_array($r['delivery_id'], $cancel_items))) {
                    $data['status']       = 'ready';
                    $data['verify']       = 'false';
                    $data['stock_status'] = 'false';
                    $data['deliv_status'] = 'false';
                    $data['expre_status'] = 'false';
                    $dly_itemObj->resumeItemsByDeliveryId($r['delivery_id']); //重置每个发货单的校验
                    $filter2['delivery_id'] = $r['delivery_id'];
                }
            }

            if ($cancel_items && in_array($r['delivery_id'], $cancel_items)) {
                unset($data['parent_id']);
            }
            if ($r['delivery_id']) {
                $filter2['delivery_id'] = $r['delivery_id'];
                $this->update($data, $filter2);
            }

        }
        $data = null;
        return true;
    }

    /**
     * 调用model:delivery_items中的insertParentItemByItems方法
     * 作用：将子发货单的关联items复制给大发货单
     *
     * @param bigint $parent_id
     * @param array() $items
     *
     * @return boolean
     */
    public function insertParentItemByItems($parent_id, $items, $branch_id)
    {
        $dly_itemObj = $this->app->model('delivery_items');
        return $dly_itemObj->insertParentItemByItems($parent_id, $items, $branch_id);
    }

    /**
     * 调用model:delivery_order中的insertParentOrderByItems方法
     * 作用：将子发货单的关联order复制给大发货单
     *
     * @param bigint $parent_id
     * @param array() $items
     *
     * @return boolean
     */
    public function insertParentOrderByItems($parent_id, $items)
    {
        $dly_orderObj = $this->app->model('delivery_order');
        return $dly_orderObj->insertParentOrderByItems($parent_id, $items);
    }

    public function insertParentItemDetailByItemsDetail($parent_id, $items)
    {
        $didObj = $this->app->model('delivery_items_detail');
        return $didObj->insertParentItemDetailByItemsDetail($parent_id, $items);
    }

    /**
     * 通过一个发货单号或一个发货单号数组，获取这些发货单号对应的订单号
     *
     * @param string/array() $dly_ids
     *
     * @return array()                  订单ID的数组(自然下标)
     */
    public function getOrderIdByDeliveryId($dly_ids)
    {
        $dly_orderObj          = $this->app->model('delivery_order');
        $filter['delivery_id'] = $dly_ids;

        $data = $dly_orderObj->getList('order_id', $filter);
        foreach ($data as $item) {
            $ids[] = $item['order_id'];
        }
        return $ids;
    }
    /**
     * 根据发货单id获取订对应的订单号
     *
     * @param  void
     * @return void
     * @author
     **/
    public function getOrderBnbyDeliveryId($delivery_id)
    {
        $sql = 'SELECT order_bn,pay_status,o.order_id
                FROM sdb_ome_orders AS o
                LEFT JOIN  sdb_ome_delivery_order AS deli ON o.order_id = deli.order_id
                WHERE delivery_id=' . $delivery_id;
        $delivery = kernel::database()->select($sql);
        return $delivery[0];
    }
    /**
     * 通过发货单号获取发货单详情关联表的对应记录
     *
     * @param bigint $dly_id
     *
     * @return array()
     */
    public function getItemsByDeliveryId($dly_id)
    {
        $dly_itemObj = $this->app->model('delivery_items');
        $rows        = $dly_itemObj->getList('*', array('delivery_id' => $dly_id), 0, -1);

        return $rows;
    }

    /**
     * 通过仓库ID和货品ID，获取其所有货位的商品数量信息
     *
     * @param int $branch_id
     * @param int $product_id
     *
     * @return array()
     * ss备注：些方法已经没用，可以删除
     */
    public function getBranchProductPosNum($branch_id, $product_id)
    {
        //$branch_pposObj = $this->app->model('branch_product_pos');
        //$rows = $branch_pposObj->getList('*', array('branch_id' => $branch_id, 'product_id' => $product_id));
        $sql = "SELECT * FROM sdb_ome_branch_product_pos dpp
                               JOIN sdb_ome_branch_pos dp
                                   ON dpp.pos_id=dp.pos_id
                               WHERE dp.branch_id='$branch_id' AND dpp.product_id='$product_id'";

        return $this->db->select($sql);
    }

    /**
     * 通过发货单的itemId获取其相应的item货位商品数量信息
     *
     * @param int $item_id
     *
     * @return array()
     * ss备注：些方法已经没用，可以删除
     */
    public function getItemPosByItemId($item_id)
    {
        $dly_iposObj   = $this->app->model('dly_items_pos');
        $branch_posObj = $this->app->model('branch_pos');
        $rows          = $dly_iposObj->getList('*', array('item_id' => $item_id));
        foreach ($rows as $key => $item) {
//循环取出货位名称
            $pos                          = $branch_posObj->dump($item['pos_id']);
            $rows[$key]['store_position'] = $pos['store_position'];
        }
        return $rows;
    }

    /**
     * 通过delivery_id(可以为数组)删除发货单订单关联表中的记录
     *
     * @param bigint/array() $dly_id
     *
     * @return boolean
     */
    public function deleteDeliveryItemDetailByDeliveryId($dly_id)
    {
        if ($dly_id) {
            $didObj                = $this->app->model('delivery_items_detail');
            $filter['delivery_id'] = $dly_id;
            return $didObj->delete($filter);
        }
        return false;
    }

    /**
     * 通过delivery_id(可以为数组)删除发货单订单关联表中的记录
     *
     * @param bigint/array() $dly_id
     *
     * @return boolean
     */
    public function deleteDeliveryOrderByDeliveryId($dly_id)
    {
        if ($dly_id) {
            $dly_orderObj          = $this->app->model('delivery_order');
            $filter['delivery_id'] = $dly_id;
            return $dly_orderObj->delete($filter);
        }
        return false;
    }

    /**
     * 通过delivery_id(可以为数组)删除发货单明细关联表中的记录
     *
     * @param bigint/array() $dly_id
     *
     * @return boolean
     */
    public function deleteDeliveryItemsByDeliveryId($dly_id)
    {
        if ($dly_id) {
            $dly_itemObj           = $this->app->model('delivery_items');
            $filter['delivery_id'] = $dly_id;
            return $dly_itemObj->delete($filter);
        }
        return false;
    }

    /**
     * 删除发货单相关的货位信息（只有合并过的发货单才会触发此方法）
     *
     * @param int $dly_id
     *
     * @return boolean
     * ss备注：此方法可以被删除
     */
    public function deleteDeliveryItemsPosByDeliveryId($dly_id)
    {
        if ($dly_id) {
            $dly_itemObj    = $this->app->model('delivery_items');
            $dly_itemPosObj = $this->app->model('dly_items_pos');
            foreach ($dly_id as $id) {
                $rows = $dly_itemObj->getList('*', array('delivery_id' => $id), 0, -1);
                if (empty($rows)) {
                    return false;
                }

                foreach ($rows as $row) {
                    $filter['item_id'] = $row['item_id'];
                    $dly_itemPosObj->delete($filter);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 打回发货单操作
     *
     * @param array() $dly_ids
     * @param string $memo
     * @param boolean $reback_status 打回状态，默认为false:打回所有发货单;true：只打回未发货的发货单
     * @param boolean  $must_log 订单收订叫回失败记录日志
     * @return boolean
     */
    public function rebackDelivery($dly_ids, $memo, $dly_status = false, $must_log = false)
    {
        $flag = true; //发货单打回,成功标志
        $err_msg = '';

        if (is_array($dly_ids)) {
            $ids = $dly_ids;
        } else {
            $ids[] = $dly_ids;
        }

        $memo_log = '';
        if ($memo) {
            $memo_log .= ',备注:' . $memo;
        }

        $data['memo']      = $memo;
        $data['status']    = 'back';
        $data['logi_no']   = null;
        $orderObj          = app::get('ome')->model('orders');
        $delivery_itemsObj = app::get('ome')->model('delivery_items');

        $deliveryBillObj = app::get('ome')->model('delivery_bill');
        $combineObj      = new omeauto_auto_combine();
        $dispatchObj     = app::get('omeauto')->model('autodispatch');
        $opObj           = app::get('ome')->model('operation_log');

        foreach ($ids as $item) {
            $res = array('rsp' => 'false');

            $deliveryInfo = $this->dump($item, 'delivery_id, shop_id, process, status, delivery_bn, branch_id, is_bind, parent_id', array('delivery_items' => array('*'), 'delivery_order' => array('*')));

            //本地先检查是否可操作
            if ($deliveryInfo['process'] == 'true' || in_array($deliveryInfo['status'], array('failed', 'cancel', 'back', 'succ', 'return_back'))) {
                continue;
            }

            //如果是主发货单
            if ($deliveryInfo['parent_id'] == 0) {
                //发货通知单暂停推送仓库
                $notice_params = array(
                    'delivery_id' => $item,
                    'delivery_bn' => $deliveryInfo['delivery_bn'],
                    'branch_id'   => $deliveryInfo['branch_id'],
                );

                $res = ome_delivery_notice::cancel($notice_params, true);
            } else {
                $res['rsp'] = 'success';
            }

            if ($res['rsp'] == 'success' || $res['rsp'] == 'succ') {

                $data['delivery_id'] = $item;
                $rs = $this->update($data, ['delivery_id'=>$item, 'status|in' => ['stop', 'ready', 'progress', 'timeout']]);
                if (is_bool($rs)) {
                    continue;
                }

                $opObj->write_log('delivery_back@ome', $item, '发货单撤销成功' . $memo_log);

                // 发货单取消成功通知
                $this->sendDeliveryCancelSuccessNotify($deliveryInfo, $memo);

                if($deliveryInfo['parent_id'] == 0){
                    // 发货单取消后的service扩展点
                    foreach(kernel::servicelist('ome.service.delivery.cancel.after') as $object) {
                        if(method_exists($object, 'cancel_delivery_after')) {
                            $object->cancel_delivery_after($item);
                        }
                    }
                }
                //库存管控处理
                $storeManageLib = kernel::single('ome_store_manage');
                $storeManageLib->loadBranch(array('branch_id' => $deliveryInfo['branch_id']));

                if ($deliveryInfo['is_bind'] == 'true') {
                    $childIds = $this->getItemsByParentId($item, 'array');
                    foreach ($childIds as $i) {
                        $delivery = $this->dump($i, 'delivery_id,branch_id,shop_id', array('delivery_items' => array('*'), 'delivery_order' => array('*')));

                        $de     = $delivery['delivery_order'];
                        $or     = array_shift($de);
                        $ord_id = $or['order_id'];

                        //仓库库存处理
                        $params['params']    = array_merge($delivery, array('order_id' => $ord_id));
                        $params['node_type'] = 'cancelDly';
                        $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
                        kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($i);
                    }
                } else {
                    $de     = $deliveryInfo['delivery_order'];
                    $or     = array_shift($de);
                    $ord_id = $or['order_id'];

                    //仓库库存处理
                    $params['params']    = array_merge($deliveryInfo, array('order_id' => $ord_id));
                    $params['node_type'] = 'cancelDly';
                    $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
                    kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($item);
                }

                //默认叫回发货单后需处理订单相应的状态
                if (!$dly_status) {
                    $order     = $this->getOrderIdByDeliveryId($item);
                    $orderlist = $orderObj->getlist('order_id,order_combine_idx,order_combine_hash,pay_status,ship_status,payed,process_status', array('order_id' => $order));
                    foreach ($orderlist as $orderInfo) {
                        //发货单打回，更新订单状态
                        kernel::single('ome_order')->resumeOrdStatus($orderInfo);
                        $isStoreBranch = $storeManageLib->isStoreBranch();
                        if ($isStoreBranch) {
                            kernel::single('ome_o2o_performance_orders')->updateProcessStatus($orderInfo['order_id'], 'refuse');
                        }
                    }
                }

                //打回发货单状态同步
                kernel::single('ome_event_trigger_shop_delivery')->delivery_process_update($item);
            } else {

                $opObj->write_log('delivery_back@ome', $item, '发货单取消通知仓库:失败,原因:' . $res['msg'] . $memo_log);

                //如果是强制更新，但是通知仓储失败则标记发货单通知失败
                if ($must_log) {
                    kernel::single('console_delivery')->update_sync_status($item, 'cancel_fail', $res['msg']);
                }

                $flag = false; //打回发货单失败

                continue;
            }
        }

        return $flag;
    }

    /**
     * 判断订单确认状态是否为部分拆分[2014.10.31 拆单已使用]
     * @param int $order_id
     * @param void $return_num 是否返回发货单count数量
     */
    public function validDeiveryByOrderId($order_id, $return_num = false)
    {
        $order_id = intval($order_id);
        if ($order_id == 0) {
            return false;
        }

        $sql = "SELECT COUNT(*) AS _count FROM sdb_ome_delivery_order do
                                JOIN sdb_ome_delivery d
                                    ON do.delivery_id=d.delivery_id
                                WHERE do.order_id=$order_id
                                    AND d.parent_id=0
                                    AND d.status IN ('ready','progress','succ', 'stop')
                                    AND d.disabled='false'";
        $row = $this->db->selectrow($sql);

        #返回统计的发货单数量
        if ($return_num) {
            return $row['_count'];
        }

        return ($row['_count'] > 0 ? true : false);
    }

    public function getCorpsByBranchId()
    {

    }

    /**
     * 获取发货人信息
     *
     * @param int $dly_id
     *
     * @return array()
     */
    public function getShopInfo($shop_id)
    {
        static $shops;

        if ($shops[$shop_id]) {
            return $shops[$shop_id];
        }

        $shopObj         = $this->app->model("shop");
        $shops[$shop_id] = $shopObj->dump($shop_id);

        return $shops[$shop_id];
    }

    /**
     * 统计商品数量
     *
     * @param array() $dly_ids
     *
     * @return array()
     */
    public function countProduct($dly_ids = null)
    {
        if ($dly_ids) {
            $sql = "SELECT bn,product_name,SUM(number) AS 'count' FROM sdb_ome_delivery_items
                                                              WHERE delivery_id IN ($dly_ids) AND number!=0 GROUP BY bn";

            $data = $this->db->select($sql);
        }
        return $data;
    }

    /**
     * 统计商品在每个货位上的数量
     *
     * @param array() $dly_ids
     * @param string $bn
     *
     * @return array()
     * ss备注：此方法已经没用，可以删除
     */
    public function countProductPos($dly_ids = null, $bn = null)
    {
        if ($dly_ids) {
            $sql = "SELECT bp.store_position,SUM(dip.num) AS 'count' FROM sdb_ome_delivery_items di
                                JOIN sdb_ome_dly_items_pos dip
                                    ON di.item_id=dip.item_id
                                JOIN sdb_ome_branch_pos bp
                                    ON dip.pos_id=bp.pos_id
                                WHERE di.delivery_id IN ($dly_ids)
                                    AND di.number!=0
                                    AND dip.num!=0 AND di.bn='$bn'
                                GROUP BY di.bn,bp.store_position";

            $rows = $this->db->select($sql);
        }
        return $rows;
    }

    public function getProductPosByDeliveryId($dly_id = null)
    {
        if ($dly_id) {
            // 发货单对应的仓库id
            $branch_id = $this->dump($dly_id, 'branch_id');
            $sql       = "SELECT di.bn,di.product_name,di.product_id,
                        a.bm_id AS goods_id, a.material_name AS name, a.material_bn AS bn,
                        di.number,delivery_id, bp.store_position
                        FROM sdb_ome_delivery_items di
                        JOIN sdb_material_basic_material a ON a.bm_id=di.product_id
                                LEFT JOIN (
                                SELECT bpp.*
                                FROM (
                                SELECT ss.pos_id,ss.product_id
                                FROM sdb_ome_branch_product_pos as ss LEFT JOIN sdb_ome_branch_pos bss on ss.pos_id=bss.pos_id
                                        WHERE ss.branch_id=" . $branch_id['branch_id'] . " AND bss.pos_id!=''
                                )bpp
                                GROUP BY bpp.product_id
                                )bb
                                ON bb.product_id = di.product_id
                                LEFT JOIN sdb_ome_branch_pos bp ON bp.pos_id = bb.pos_id
                                WHERE di.delivery_id = $dly_id
                                    AND di.number != 0";

            $rows = $this->db->select($sql);
        }

        $basicMaterialLib = kernel::single('material_basic_material');

        foreach ($rows as $key => $val) {
            $get_product = $basicMaterialLib->getBasicMaterialExt($val['goods_id']);

            $val['barcode']   = $get_product['barcode'];
            $val['weight']    = $get_product['weight'];
            $val['unit']      = $get_product['unit'];
            $val['price']     = $get_product['retail_price'];
            $val['spec_info'] = $get_product['specifications'];

            $rows[$key] = $val;
        }

        return $rows;
    }

    public function getProductPosInfo($dly_id = '', $branch_id = '')
    {
        if ($dly_id && $branch_id) {
            $sql = "SELECT di.bn,di.product_name,di.product_id,
                    a.bm_id AS goods_id, a.material_name AS name, a.material_bn AS bn,
                    di.number,delivery_id, bp.store_position
                    FROM sdb_ome_delivery_items di
                    JOIN sdb_material_basic_material a ON a.bm_id=di.product_id
                                LEFT JOIN (
                                SELECT bpp.*
                                FROM (
                                SELECT ss.pos_id,ss.product_id
                                FROM sdb_ome_branch_product_pos as ss LEFT JOIN sdb_ome_branch_pos bss on ss.pos_id=bss.pos_id
                                        WHERE ss.branch_id=" . $branch_id . " AND bss.pos_id!=''
                                )bpp
                                GROUP BY bpp.product_id
                                )bb
                                ON bb.product_id = di.product_id
                                LEFT JOIN sdb_ome_branch_pos bp ON bp.pos_id = bb.pos_id
                                WHERE di.delivery_id = $dly_id
                                    AND di.number != 0";
            $rows = $this->db->select($sql);
        }

        $basicMaterialLib = kernel::single('material_basic_material');

        foreach ($rows as $key => $val) {
            $get_product = $basicMaterialLib->getBasicMaterialExt($val['goods_id']);

            $val['barcode']   = $get_product['barcode'];
            $val['weight']    = $get_product['weight'];
            $val['unit']      = $get_product['unit'];
            $val['price']     = $get_product['retail_price'];
            $val['spec_info'] = $get_product['specifications'];

            $rows[$key] = $val;
        }

        return $rows;
    }

    public function getOrderMemoByDeliveryId($dly_ids = null)
    {
        if ($dly_ids) {
            $sql = "SELECT o.custom_mark FROM sdb_ome_delivery_order do
                                    JOIN sdb_ome_orders o
                                        ON do.order_id=o.order_id
                                    WHERE do.delivery_id IN ($dly_ids)
                                        GROUP BY do.order_id ";
            $rows = $this->db->select($sql);
            $memo = array();
            if ($rows) {
                foreach ($rows as $v) {
                    $memo[] = unserialize($v['custom_mark']);
                }

            }
            return serialize($memo);
        }
    }

    /**
     * 统计订单商品的发货数量
     *
     * @param int $order_id
     *
     * @return int
     */
    public function countOrderSendNumber($order_id)
    {
        $sql      = "SELECT COUNT(*) AS 'total' FROM sdb_ome_order_items WHERE order_id = '$order_id' AND nums!=sendnum AND `delete`='false'";
        $item_num = $this->db->selectrow($sql);
        return $item_num['total'];
    }

    /**
     * 统计订单商品的拆分数量
     *
     * @param int $order_id
     *
     * @return int
     */
    public function countOrderSplitNumber($order_id)
    {
        $sql      = "SELECT COUNT(*) AS 'total' FROM sdb_ome_order_items WHERE order_id = '$order_id' AND nums!=split_num AND `delete`='false'";
        $item_num = $this->db->selectrow($sql);
        return $item_num['total'];
    }

    /**
     * 更新仓库商品表商品数量
     *
     * @param int $num
     * @param int $product_id
     * @param int $branch_id
     *
     * @return boolean
     *
    function updateBranchProduct($num, $product_id, $branch_id){
    $now = time();
    //$sql = "UPDATE sdb_ome_branch_product SET store=store-$num,store_freeze=store_freeze-$num,last_modified=$now WHERE branch_id='$branch_id' AND product_id='$product_id'";
    //暂时不在branch_product上使用冻结库存
    $sql = "UPDATE sdb_ome_branch_product SET store=store-$num,last_modified=$now WHERE branch_id='$branch_id' AND product_id='$product_id'";
    //echo $sql;
    return $this->db->exec($sql);//扣减branch_product表
    }

    /**
     * 更新仓库商品货位表商品数量
     *
     * @param int $num
     * @param int $product_id
     * @param int $pos_id
     *
     * @return boolean
     *
    function updateBranchProductPos($num, $product_id, $pos_id){
    $sql = "UPDATE sdb_ome_branch_product_pos SET store=store-$num WHERE pos_id='$pos_id' AND product_id='$product_id'";
    //echo $sql;
    return $this->db->exec($sql);//扣减branch_product_pos表
    }/*
    /*
     * 生成发货单号
     *
     *
     * @return bigint           发货单号
     */
    public function gen_id()
    {
        $prefix = date("ymd");
        $sign   = kernel::single('eccommon_guid')->incId('delivery', $prefix, 7, true);

        return $sign;

        /*        $cManage = $this->app->model("concurrent");
    $prefix = date("ymd").'11';
    $sqlString = "SELECT MAX(delivery_bn) AS maxno FROM sdb_ome_delivery WHERE delivery_bn LIKE '".$prefix."%'";
    $aRet = $this->db->selectrow($sqlString);
    if(is_null($aRet['maxno'])){
    $aRet['maxno'] = 0;
    $maxno = 0;
    }else
    $maxno = substr($aRet['maxno'], -5);

    do{
    $maxno += 1;
    if ($maxno==100000){
    break;
    }
    $maxno = str_pad($maxno,5,'0',STR_PAD_LEFT);

    $sign = $prefix.$maxno;

    if($cManage->is_pass($sign,'delivery')){
    break;
    }
    }while(true);

    return $sign;
     */
    }

    /*
     * 根据订单id获取发货单信息
     *
     * @param string $cols
     * @param bigint $order_id 订单id
     *
     * @return array $delivery 发货单数组
     */

    public function getDeliveryByOrder($cols = "*", $order_id)
    {
        $delivery_ids = $this->getDeliverIdByOrderId($order_id);
        if ($delivery_ids) {
            $f_status = array('ready', 'progress', 'succ', 'return_back', 'back');
            $delivery = $this->getList($cols, array('delivery_id' => $delivery_ids, 'status' => $f_status), 0, -1);
            if ($delivery) {
                foreach ($delivery as $k => $v) {
                    if (isset($v['logi_id'])) {
                        $dly_corp                    = $this->db->selectrow("SELECT * FROM sdb_ome_dly_corp WHERE disabled='false' AND corp_id=" . intval($v['logi_id']));
                        $delivery[$k]['request_url'] = $dly_corp['website']; //TODO: 等request_url完善后使用request_url
                        $delivery[$k]['logi_code']   = $dly_corp['type'];
                    }

                    if (isset($v['branch_id'])) {
                        $branch                      = $this->db->selectrow("SELECT * FROM sdb_ome_branch WHERE disabled='false' AND branch_id=" . intval($v['branch_id']));
                        $delivery[$k]['branch_name'] = $branch['name'];
                    }

                    if (isset($v['status'])) {
                        if (empty($status_text)) {
                            $status_text = array('succ' => '已发货', 'failed' => '发货失败', 'cancel' => '已取消', 'progress' => '等待配货', 'timeout' => '超时', 'ready' => '等待配货', 'stop' => '暂停', 'back' => '打回','return_back'=>'退回');
                        }

                        $delivery[$k]['status_text'] = $status_text[$v['status']];
                    }

                    if (isset($v['stock_status']) || isset($v['deliv_status']) || isset($v['expre_status'])) {
                        if ($v['stock_status'] == 'ture' && $v['deliv_status'] == 'true' && $v['expre_status'] == 'true') {
                            $delivery[$k]['print_statis'] = '已完成打印';
                        } else if ($v['stock_status'] == 'false' && $v['deliv_status'] == 'false' && $v['expre_status'] == 'false') {
                            $delivery[$k]['print_status'] = '未打印';
                        } else {

                            $print_status = array();

                            if ($v['stock_status'] == 'true') {
                                $print_status[] = '备货单';
                            }
                            if ($v['deliv_status'] == 'true') {
                                $print_status[] = '清单';
                            }
                            if ($v['expre_status'] == 'true') {
                                $print_status[] = '快递单';
                            }
                            $delivery[$k]['print_status'] = implode("/", $print_status) . "已打印";
                        }
                    }
                }
                return $delivery;
            } else {
                return array();
            }
        } else {
            return array();
        }
    }

    /**
     * 根据订单order_id获取关联的所有发货单delivery_id
     * todo：只获取父delivery_id是0的发货单
     *
     * @param int $order_id
     * @param bool $is_reback_dly 只获取能打回的发货单
     * @return array
     */
    public function getDeliverIdByOrderId($order_id, $is_reback_dly = false)
    {

        if (is_array($order_id)) {
            $order_id = implode(',', $order_id);
            $where    = 'order_id in (' . $order_id . ')';
        } else {
            $where = 'order_id = ' . $order_id;
        }

        if ($is_reback_dly) {
            //过滤掉已发货的
            $sql = "SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord
                    LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                    WHERE dord.{$where} AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' AND d.status NOT IN('failed','cancel','back','return_back', 'succ')";
        } else {
            $sql = "SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord
                    LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                    WHERE dord.{$where} AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' AND d.status NOT IN('failed','cancel','back','return_back')";
        }
        //delivery list
        $delivery_ids = $this->db->select($sql);
        $ids          = array();
        if ($delivery_ids) {
            foreach ($delivery_ids as $v) {
                $ids[] = $v['delivery_id'];
            }
        }

        return $ids;
    }

    /*
     * 根据订单id获取"失败"、"取消"、"打回"的发货单id
     * 只获取父id是0的发货单
     *
     * @param bigint $order_id
     *
     * @return array $ids
     */

    public function getHistoryIdByOrderId($order_id)
    {
        $delivery_ids = $this->db->select("SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord
                                            LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            WHERE dord.order_id='{$order_id}' AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' AND d.status IN('failed','cancel','back','return_back')");
        $ids = array();
        if ($delivery_ids) {
            foreach ($delivery_ids as $v) {
                $ids[] = $v['delivery_id'];
            }
        }
        return $ids;
    }

    /**
     * 根据发货单ID判断相关订单ID所有相关联的发货单是否都已打印，如果都打印，更新订单print_finish为TRUE
     * $type为0 是否更新，为1时，更新为FALSE
     *
     * @param bigint $dly_id
     * @param int $type
     *
     */
    public function updateOrderPrintFinish($dly_id, $type = 0)
    {
        $orderIds = $this->getOrderIdByDeliveryId($dly_id);
        foreach ($orderIds as $id) {
            $ordObj               = $this->app->model('orders');
            if(!$ordObj->db_dump(['order_id' => $id], 'order_id')) {
                continue;
            }
            if ($type == 0) {
                $flag   = 0;
                $dlyIds = $this->getDeliverIdByOrderId($id);
                foreach ($dlyIds as $i) {
                    $dly = $this->dump($i);
                    if ($dly['stock_status'] == 'false') {
                        break;
                    }

                    if ($dly['deliv_status'] == 'false') {
                        break;
                    }

                    if ($dly['expre_status'] == 'false') {
                        break;
                    }

                    $flag++;
                }
                if ($flag > 0) {
                    $data['order_id']     = $id;
                    $data['print_finish'] = 'true';
                    $ordObj->save($data);
                }
            } elseif ($type == 1) {
                $data['order_id']     = $id;
                $data['print_finish'] = 'false';
                $data['print_status'] = 0;
                $data['logi_no']      = null;
                $ordObj->save($data);
            }
        }
        return true;
    }

    /*
     * 设置发货单状态
     *
     * @param int $delivery_id
     * @param string $status 状态
     *
     * @return bool
     */

    public function set_status($delivery_id, $status)
    {
        $data = array(
            'delivery_id' => $delivery_id,
            'status'      => $status,
        );
        if ($status == 'cancel') {
            $data['logi_no'] = null;
        }
        return $this->save($data);
    }

    /*
     * 获取订单已生成发货单的货品数量
     *
     * @param bigint $order_id 订单id
     * @param int $product_id 货品id
     *
     * @return int
     */

    public function getDeliveryFreez($order_id, $product_id)
    {
        $sql = "SELECT SUM(di.number) AS nums FROM sdb_ome_delivery_order AS dord
                    LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                    LEFT JOIN sdb_ome_delivery_items AS di ON(di.delivery_id=d.delivery_id)
                    WHERE dord.order_id='{$order_id}' AND di.product_id='{$product_id}' AND d.parent_id=0 AND d.disabled='false' AND d.status IN('succ','failed','progress','timeout','ready','stop')";

        $ret = $this->db->selectrow($sql);

        return $ret['nums'];
    }

    /*
     * 获取发货单可以合并的依据
     *
     * @param int $shop_id 前端店铺id
     * @param int $branch_id 仓库id
     * @param string $ship_addr 收货地址
     * @param int $member_id 会员id
     * @param string $is_cod 是否活到付款 true/false
     * @param string $is_protect 是否保价 true/false
     *
     * @return string
     */

    public function getBindKey($data)
    {
        $bindkey = md5($data['shop_id'] . $data['branch_id'] . $data['consignee']['addr'] . $data['member_id'] . $data['is_cod'] . $data['is_protect']);
        if ($service = kernel::service('ome.service.delivery.bindkey')) {
            if (method_exists($service, 'get_bindkey')) {
                $bindkey = $service->get_bindkey($data);
            }
        }

        return $bindkey;
    }

    public function cmp_delivery_productid($arr1, $arr2)
    {
        if ($arr1['product_id'] == $arr2['product_id']) {
            return 0;
        }
        return ($arr1['product_id'] < $arr2['product_id']) ? -1 : 1;
    }

    /**
     * 新建发货单
     *
     * @param $order_id bigint
     * @param $delivery array
     * @param $ship_info array
     * @param $order_items
     * @param $split_status 拆单后订单状态
     * @param $is_diff_order 补差价订单生成已发货的发货单 不涉及库存
     * @param $extendInfo array 扩展信息
     *
     * @return array|string[]
     */
    public function addDelivery($order_id, $delivery, $ship_info = array(), $order_items = array(), &$split_status = '', $is_diff_order = false, $extendInfo=array())
    {
        $addRes = $this->_addDelivery($order_id, $delivery, $ship_info, $order_items, $split_status, $is_diff_order, $extendInfo);

        if ($addRes['rsp'] == 'fail') {

            return $addRes;
        }
        $data = $addRes['data'];

        if ($is_diff_order) { //调用方法的外层会更新当前订单为已发货并发起发货单回写请求
        } else {
            //发货单创建
            $deliveryEventStartTime = microtime(true);
            kernel::single('ome_event_trigger_shop_delivery')->delivery_add($data['delivery_id']);
            $deliveryEventTime = microtime(true) - $deliveryEventStartTime;

            // 记录发货单事件触发性能数据
            ome_api_log::add_message('event', 'delivery_add', $deliveryEventTime, array(
                'delivery_id' => $data['delivery_id']
            ));
        }

        return array('rsp' => 'succ', 'data' => $data['delivery_id']);
    }

    private function _addDelivery($order_id, $delivery, $ship_info = array(), $order_items = array(), &$split_status = '', $is_diff_order = false, $extendInfo=array())
    {
        $oOrder    = $this->app->model("orders");
        $oDly_corp = $this->app->model("dly_corp");
        $branchObj = $this->app->model("branch");

        // 验证明细
        if (!$delivery['delivery_items']) {
            return array('rsp' => 'fail', 'msg' => '没有明细');
        }

        // 减少死锁概率，以product_id排序
        usort($delivery['delivery_items'], array('ome_mdl_delivery', 'cmp_delivery_productid'));

        // 根据代码追踪，order_items获取order_objects的sm_id，不准确，临时方案
        if ($order_items) {
            $orderObjMdl = app::get('ome')->model('order_objects');
            $objIdArr = array_column($order_items, 'obj_id');
            $objectsList = $orderObjMdl->getList('obj_id,goods_id', ['order_id'=>$order_id, 'obj_id'=>$objIdArr]);
            $objectsList = array_column($objectsList, null, 'obj_id');

            foreach ($delivery['delivery_items'] as $di => $dv) {
                $sm_id = 0;
                foreach ($order_items as $ov) {
                    $sm_id = $objectsList[$ov['obj_id']]['goods_id'];
                    break;
                }
                $delivery['delivery_items'][$di]['goods_id'] = $sm_id;
            }
        }

        //开启添加发货单事务,锁定当前订单记录
        $this->db->beginTransaction();
        //防止订单编辑与生成发货单并发导致错误
        $oOrder->update(['last_modified'=>time()], ['order_id'=>$order_id]);
        $psRow = app::get('ome')->model('order_platformsplit')->db_dump(['order_id'=>$order_id], 'id');
        if($psRow) {
            $this->db->rollBack();
            return array('rsp' => 'fail', 'msg' => '已经进行京东平台拆，不能生成发货单');
        }
        $order = $oOrder->dump($order_id);

        if (kernel::single('ome_order_bool_type')->isJITX($order['order_bool_type'])) {
            kernel::single('purchase_branch_freeze')->deleteFromOrder($order['order_bn'], $order['shop_id']);
        }

        $ship_info           = $delivery['consignee'] ? $delivery['consignee'] : $order['consignee'];
        $delivery_bn         = $delivery['delivery_bn'] ? $delivery['delivery_bn'] : $this->gen_id();
        $data['delivery_bn'] = $delivery_bn;
        $is_protect          = $delivery['is_protect'] ? $delivery['is_protect'] : $order['shipping']['is_protect'];

        $is_cod = $delivery['is_cod'] ? $delivery['is_cod'] : $order['shipping']['is_cod'];
        if ($is_cod) {
            $data['is_cod'] = $is_cod;
        }
        if ($order['order_type'] == 'vopczc') {
            $delivery['type'] = 'vopczc';
        }
        $data['delivery']       = $delivery['delivery'] ? $delivery['delivery'] : $order['shipping']['shipping_name'];
        $data['logi_id']        = $delivery['logi_id'];
        $data['memo']           = $delivery['memo'];
        $data['delivery_group'] = $delivery['delivery_group'];
        $data['sms_group']      = $delivery['sms_group'];
        $data['branch_id']      = $delivery['branch_id'];
        $data['wms_channel_id'] = $delivery['wms_channel_id'] ? : kernel::single('console_delivery_yjdf')->getWMSChannelId($delivery['branch_id'], $delivery['delivery_items']); //WMS渠道ID
        
        //平台订单号
        if(kernel::single('ome_order_bool_type')->isJDLVMI($order['order_bool_type'])) {
            $data['platform_order_bn'] =   $order['platform_order_bn'];
        }elseif($order['platform_order_bn']){
            $data['platform_order_bn'] = $order['platform_order_bn'];
        }

        $logi_name = "";
        if ($is_diff_order) {
            //补差价订单直接生成已发货的发货单 不涉及库存
            $delivery['type']      = "reject";
            $data['memo']          = "售后补差价订单：" . $order["order_bn"] . "，自动生成已发货的发货单。";
            $logi_name             = "其他物流公司";
            $data['process']       = 'true';
            $data['status']        = 'succ';
            $data['delivery_time'] = time();
        }
        if ($delivery['type']) {
            $data['type'] = $delivery['type'];
        }

        //计算预计物流费用
        // 记录运费和重量计算开始时间
        $freightStartTime = microtime(true);

        $weight = 0;
        if (isset($delivery['weight'])) {
            $weight = $delivery['weight'];
        } else {
            //[拆单]根据发货单中货品详细读取重量
            $orderSplitLib = kernel::single('ome_order_split');
            $split_seting  = $orderSplitLib->get_delivery_seting();

            if ($split_seting) {
                $weight = $orderSplitLib->getDeliveryWeight($order_id, $order_items);
            } else {
                $weight = $this->app->model('orders')->getOrderWeight($order_id);
            }
        }

        list($area_prefix, $area_chs, $area_id) = explode(':', $ship_info['area']);

        $price = 0.00;
        if ($delivery['logi_id']) {
            $price     = $this->getDeliveryFreight($area_id, $delivery['logi_id'], $weight);
            $dly_corp  = $oDly_corp->dump($delivery['logi_id']);
            $logi_name = $dly_corp['name'];

            // 计算保价费用
            $protect = $dly_corp['protect'];
            if ($protect == 'true') {
                $is_protect    = 'true';
                $protect_price = $dly_corp['protect_rate'] * $weight;
                $cost_protect  = max($protect_price, $dly_corp['minprice']);
            }
        }

        // 计算运费和重量计算总耗时
        $freightTime = microtime(true) - $freightStartTime;

        // 记录运费和重量计算性能数据
        ome_api_log::add_message('freight', 'freight_calculation', $freightTime, array(
            'delivery_id' => $data['delivery_id'] ?? '',
            'order_id' => $order_id,
            'logi_id' => $delivery['logi_id'] ?? '',
            'weight' => $weight,
            'freight_price' => $price
        ));

        //[同城配]配送方式
        if($dly_corp['corp_model'] == 'instatnt'){
            //同城配送
            $data['delivery'] = 'instatnt';
        }elseif($dly_corp['corp_model'] == 'seller'){
            //商家配送
            $data['delivery'] = 'seller';
        }

        //order has logi_info：aikucun
        if ($delivery['delivery_waybillCode']) {
            $data['logi_no'] = $delivery['delivery_waybillCode'];
        }

        if ($delivery['delivery_sub_waybillCode']) {
            $data['logi_number'] = count($delivery['delivery_sub_waybillCode']) + 1;
        }

        $data['logi_name']            = $logi_name;
        $data['is_protect']           = $is_protect ? $is_protect : 'false';
        $data['create_time']          = time();
        $data['cost_protect']         = $cost_protect ? $cost_protect : '0';
        $data['net_weight']           = $weight;
        $data['delivery_cost_expect'] = $price;
        $data['member_id']            = $delivery['member_id'] ? $delivery['member_id'] : $order['member_id'];
        $data['shop_id']              = $order['shop_id'];
        $data['shop_type']            = $order['shop_type'];

        $data['delivery_items'] = $delivery['delivery_items'];
        $data['consignee']      = $ship_info;

        //权限相关
        $data['org_id'] = $order['org_id']; //运营组织
        $data['betc_id'] = $order['betc_id']; //贸易公司ID
        $data['cos_id'] = $order['cos_id']; //组织架构ID

        //支持四、五级地区
        $temp_area                     = explode('/', $area_chs);
        $data['consignee']['province'] = $temp_area[0];
        $data['consignee']['city']     = $temp_area[1];
        $data['consignee']['district'] = $temp_area[2];
        $data['consignee']['town']     = empty($temp_area[3]) ? '' : $temp_area[3];
        $data['consignee']['village']  = empty($temp_area[4]) ? '' : $temp_area[4];

        $data['order_createtime'] = ($order['paytime'] && $is_cod == 'false') ? $order['paytime'] : $order['createtime']; #付款时间为空时取创建时间

        $opInfo          = kernel::single('ome_func')->getDesktopUser();
        $data['op_id']   = $opInfo['op_id'];
        $data['op_name'] = $opInfo['op_name'];

        //得物急速现货
        if ($order['order_type'] == 'jisuxianhuo') {
            $data['bool_type'] = $data['bool_type'] | ome_delivery_bool_type::__JISU_CODE;
        }

        if ($order['order_type'] == 'platform') {
            $data['original_delivery_bn'] = $delivery_bn;
            $data['bool_type'] = ($data['bool_type'] ? $data['bool_type'] : 0) | ome_delivery_bool_type::__PLATFORM_CODE;
        }
        if($order['shop_type'] == '360buy') {
            if(kernel::single('ome_bill_label_shsm')->isTinyPieces($order['order_id'])) {
                $data['bool_type'] = ($data['bool_type'] ? $data['bool_type'] : 0) | ome_delivery_bool_type::__SHSM_CODE;
            }
        }

        // format
        $bns      = array();
        $totalNum = 0;
        foreach ($data['delivery_items'] as $dlyItemKey => $v) {
            $product_id = $v['product_id'];

            $totalNum += $v['number'];
            $bns[$v['product_id']] = $v['bn'];
        }
        ksort($bns);
        
        //11.25新增
        $data['skuNum']     = count($delivery['delivery_items']);
        $data['itemNum']    = $totalNum;
        $data['bnsContent'] = serialize($bns);
        $data['idx_split']  = $data['skuNum'] * 10000000000 + sprintf("%u", crc32($data['bnsContent']));

        $data['bind_key'] = $this->getBindKey($data);
        $data['bool_type']  = (int)$data['bool_type'];
    
        $orderExtend = app::get('ome')->model("order_extend")->db_dump(array('order_id' => $order_id), '*');
    
        if (kernel::single('ome_order_bool_type')->isCPUP($order['order_bool_type'])) {
            $data['bool_type'] = $data['bool_type'] | ome_delivery_bool_type::__CPUP_CODE;

            //未发货之前,不能赋值,否则客户导出时有异议
            //$data['delivery_time'] = $orderExtend['latest_delivery_time'];

            $data['cpup_service'] = $orderExtend['cpup_service'];
            $data['promise_service'] = $orderExtend['promise_service'];
            $data['promised_collect_time'] = $orderExtend['promised_collect_time'];
            $data['promised_sign_time'] = $orderExtend['promised_sign_time'];
            $data['cpup_addon'] = serialize($orderExtend);
        }

        //[翱象]相关信息
        if(in_array($order['shop_type'], array('taobao', 'tmall')) && $order['order_bool_type'] && $orderExtend){
            //是否翱象订单
            $orderTypeLib = kernel::single('ome_order_bool_type');
            $isAoxiang = $orderTypeLib->isAoxiang($order['order_bool_type']);
            if($isAoxiang){
                //翱象发货单标识
                $data['bool_type'] = $data['bool_type'] | ome_delivery_bool_type::__AOXIANG_CODE;

                //物流升级服务
                $data['cpup_service'] = $orderExtend['cpup_service'];

                //最晚发货时间
                //$data['delivery_time'] = $orderExtend['latest_delivery_time'];

                //承诺最晚送达时间
                $data['promised_sign_time'] = $orderExtend['promised_sign_time'];

                //承诺最晚揽收时间
                $data['promised_collect_time'] = $orderExtend['promised_collect_time'];

                //承诺最晚出库时间
                $data['promise_outbound_time'] = $orderExtend['latest_delivery_time'];

                //承诺计划送达时间
                $data['plan_sign_time'] = $orderExtend['plan_sign_time'];

                //物流服务标签(多个订单合并取消合集)
                $data['promise_service'] = $orderExtend['promise_service'];
            }
        }
        if(kernel::single('ome_order_bool_type')->isJDLVMI($order['order_bool_type'])) {
            $data['platform_order_bn'] =   $order['platform_order_bn'];
        }elseif($order['betc_id'] && $order['cos_id']){
            //分销订单
            $data['platform_order_bn'] = $order['platform_order_bn'];
        }

        //save
        $saveStartTime = microtime(true);
        $result           = $this->save($data);
        $saveTime = microtime(true) - $saveStartTime;

        // 记录发货单保存性能数据
        ome_api_log::add_message('database', 'delivery_save', $saveTime, array(
            'delivery_id' => $data['delivery_id'] ?? '',
            'order_id' => $order_id
        ));

        if (!$result || !$data['delivery_id']) {
            $this->db->rollBack();
            return array('rsp' => 'fail', 'msg' => '发货单生成失败');
        }

        if ($delivery['type'] != 'reject') {
            $err_msg = '';

            // product_id
            $productIds = array_column($delivery['delivery_items'], 'product_id');

            // 获取指定不需要管控库存的基础物料
            $notCtrlStoreBmList = kernel::single('material_basic_material')->getNotCtrlStoreProducts($productIds);
            if($notCtrlStoreBmList){
                // 标记是否管控库存
                foreach ($delivery['delivery_items'] as $dlyItemKey => $dlyItemVal)
                {
                    $product_id = $dlyItemVal['product_id'];

                    // 是否管控库存
                    if(isset($notCtrlStoreBmList[$product_id]) && $notCtrlStoreBmList[$product_id]){
                        $delivery['delivery_items'][$dlyItemKey]['is_product_ctrl_store'] = false;
                    }else{
                        $delivery['delivery_items'][$dlyItemKey]['is_product_ctrl_store'] = true;
                    }
                }
            }

            //库存管控
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $delivery['branch_id']));

            $params = array();
            $params['params']    = array_merge($delivery, array('order_id' => $order_id, 'shop_id' => $order['shop_id'], 'delivery_id' => $data['delivery_id'], 'order_type' => $order['order_type']));
            $params['node_type'] = 'addDly';
            $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$order['shop_id']], 'delivery_mode');

            // 记录库存冻结开始时间
            $inventoryFreezeStartTime = microtime(true);

            if($shop['delivery_mode'] == 'jingxiao') {
                $processResult = true; //经销发货单不产生冻结
            } else {
                $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
            }

            // 计算库存冻结耗时
            $inventoryFreezeTime = microtime(true) - $inventoryFreezeStartTime;

            // 记录库存冻结操作
            ome_api_log::add_message('inventory', 'inventory_freeze', $inventoryFreezeTime, array(
                'delivery_id' => $data['delivery_id'],
                'branch_id' => $delivery['branch_id'],
                'mode' => $shop['delivery_mode'] == 'jingxiao' ? 'jingxiao' : 'normal',
                'result' => $processResult ? 'success' : 'failed'
            ));
            if (!$processResult) {
                $this->db->rollBack();
                return array('rsp' => 'fail', 'msg' => $err_msg);
            }
            
            // 记录拆单处理开始时间
            $splitProcessStartTime = microtime(true);

            if (!kernel::single('ome_order_object_splitnum')->addDeliverySplitNum($order_items)) {
                $this->db->rollBack();
                return array('rsp' => 'fail', 'msg' => '明细已经生成发货单');
            }

            // 判断是否已经拆分完
            $is_splited   = app::get('ome')->model('order_items')->is_splited($order_id);
            $split_status = $is_splited ? 'splited' : 'splitting';
            
            //有优惠明细记录，实付进行重算
            $order_items = $this->_regroupDeliveryItemDetailData($order_id,$order_items);

            // 计算拆单处理耗时
            $splitProcessTime = microtime(true) - $splitProcessStartTime;

            // 记录优惠明细重算性能数据
            ome_api_log::add_message('discount', 'discount_recalculation', $splitProcessTime, array(
                'delivery_id' => $data['delivery_id'] ?? '',
                'order_id' => $order_id,
                'split_status' => $split_status,
                'is_splited' => $is_splited ? 'yes' : 'no'
            ));
        }

        if ($data['delivery_id'] && !empty($order_items) && is_array($order_items)) {
            $this->create_delivery_items_detail($data['delivery_id'], $order_items);
        }

        //插关联表
        if ($order_id) {
            $rs  = $this->db->exec('SELECT * FROM sdb_ome_delivery_order WHERE 0=1');
            $ins = array('order_id' => $order_id, 'delivery_id' => $data['delivery_id']);
            $sql = kernel::single("base_db_tools")->getinsertsql($rs, $ins);
            $this->db->exec($sql);
        }

        //更新订单相应状态
        $this->updateOrderLogi($data['delivery_id'], $data);
        //订单标签插入到发货单上
        if (in_array(strtolower($order['shop_type']),['vop','luban','taobao'])) {
            kernel::single('ome_bill_label')->transferLabel('omeorders_to_omedelivery', [
                'order_id'          => $order_id,
                'ome_delivery_id'   => $data['delivery_id'],
            ]);
        }

        // 记录标签处理开始时间
        $labelProcessStartTime = microtime(true);

        //  jitx 检测订单是否有标签
        if (in_array(strtolower($order['shop_type']),['vop','luban'])) {
                kernel::single('ome_bill_label')->transferLabel('omeorders_to_omedelivery', [
                'order_id'          => $order_id,
                'ome_delivery_id'   => $data['delivery_id'],
            ]);
        }

        // 标签扩展信息(Json格式)
        $bill_extend_info = '';
        if($extendInfo){
            $bill_extend_info = is_array($extendInfo) ? json_encode($extendInfo, JSON_UNESCAPED_UNICODE) : $extendInfo;
        }

        //标签写入发货单
        kernel::single('ome_bill_label')->orderToDeliveryLabel($order_id, $data['delivery_id'], 'ome_delivery', $bill_extend_info);

        //打上标
        kernel::single('ome_bill_label_delivery')->ToDeliveryPresaleLabel($order, $data['delivery_id']);

        // 计算标签处理耗时
        $labelProcessTime = microtime(true) - $labelProcessStartTime;

        // 记录标签处理性能数据
        ome_api_log::add_message('label', 'label_process', $labelProcessTime, array(
            'delivery_id' => $data['delivery_id'] ?? '',
            'order_id' => $order_id,
            'shop_type' => $order['shop_type'] ?? ''
        ));

        $this->db->commit();


        /*
        if ($is_diff_order) { //调用方法的外层会更新当前订单为已发货并发起发货单回写请求
        } else {
            //发货单创建
            kernel::single('ome_event_trigger_shop_delivery')->delivery_add($data['delivery_id']);
        }
        */

        return array('rsp' => 'succ', 'data' => $data);
    }
    
    /**
     * 根据优惠明细 - 重组delivery_items_detail 订单实付、用户实付、销售实付
     * @param $order_id
     * @param $order_items
     * @return mixed
     */
    public function _regroupDeliveryItemDetailData($order_id, $order_items)
    {
        $deliveryItemDetailMdl = app::get('ome')->model('delivery_items_detail');
        $orderCouponDetailObj = app::get('ome')->model('order_coupon');
        $itemsMdl = app::get('ome')->model('order_items');
        
        //平台金额明细
        $couponRow = $orderCouponDetailObj->getCouponAmountList($order_id);
        
        //查询当前订单下的所有发货单id
        $delivery_ids = $this->getDeliverIdByOrderId($order_id);
        $deliveryItemDetailList = $deliveryItemDetailMdl->getList('*', array('delivery_id'=>$delivery_ids));
        $devideFee = array();
        foreach ($deliveryItemDetailList as $key => $value) {
            $oid = $value['oid'];
            $item_type = $value['item_type'];
            $product_id = $value['product_id'];
            
            //键名
            if($value['item_type'] == 'pkg'){
                $key_name = $oid .'-'. $product_id;
            }else{
                $key_name = $oid;
            }
            
            $devideFee[$key_name]['divide_user_fee'] += $value['divide_user_fee'];
            $devideFee[$key_name]['divide_order_fee'] += $value['divide_order_fee'];
            
            $devideFee[$key_name]['origin_amount'] += $value['origin_amount'];
            $devideFee[$key_name]['total_promotion_amount'] += $value['total_promotion_amount'];
            
            //货品总价格
            $devideFee[$key_name]['total_price'] += $value['total_price'];
        }
        
        //order_items
        $itemIds = array_column($order_items,'item_id');
        $orderItemList = $itemsMdl->getList('*', array('item_id'=>$itemIds));
        $orderItemList = array_column($orderItemList,null,'item_id');
        
        //是否存在捆绑商品
        $isPkg = false;
        foreach($orderItemList as $key => $value)
        {
            if($value['item_type'] == 'pkg'){
                $isPkg = true;
                break;
            }
        }
        
        //均摊PKG捆绑商品的相关金额
        $pkgItemList = array();
        if($isPkg && $couponRow){
            $pkgItemList = $orderCouponDetailObj->avgCouponPkgAmount($order_id, $couponRow);
        }
        
        //list
        foreach ($order_items as $key => $item)
        {
            $item_id = $item['item_id'];
            $oid = $item['oid'];
            $product_id = $item['product_id'];
            $total_price = 0;
            
            $order_items[$key]['divide_order_fee'] = isset($orderItemList[$item_id]['divide_order_fee']) ? bcmul(bcdiv($orderItemList[$item_id]['divide_order_fee'], $orderItemList[$item_id]['nums'], 2), $item['number'], 2) : 0;
            $order_items[$key]['retail_price']     = $orderItemList[$item_id]['price'];
            
            //item_type
            $item['item_type'] = ($item['item_type'] ? $item['item_type'] : $orderItemList[$item_id]['item_type']);
            
            //键名
            if($value['item_type'] == 'pkg'){
                $key_name = $oid .'-'. $product_id;
            }else{
                $key_name = $oid;
            }
            
            //平台相关金额
            $devideUserFee = 0; //用户实付
            $origin_amount = 0; //商品单件价格
            $total_promotion_amount = 0; //SKU商品优惠
            
            // 客户实付
            $actually_amount = (isset($orderItemList[$item_id]['actually_amount']) ? $orderItemList[$item_id]['actually_amount'] : 0);

            //PKG捆绑商品
            if($item['item_type'] == 'pkg'){
                //平台支付明细
                if($pkgItemList){
                    $devideUserFee = $pkgItemList[$oid]['pkg_items'][$product_id]['avg_amount'];
                    $origin_amount = $pkgItemList[$oid]['pkg_items'][$product_id]['avg_origin_amount'];
                    $total_promotion_amount = $pkgItemList[$oid]['pkg_items'][$product_id]['avg_promotion_amount'];
                    
                    //sku货品总价格(单价*数量)
                    $buy_nums = intval($pkgItemList[$oid]['pkg_items'][$product_id]['buy_nums']);
                    if($item['number'] == $buy_nums){
                        //未拆分直接取SKU总价格
                        $total_price = $pkgItemList[$oid]['pkg_items'][$product_id]['sku_total_price'];
                    }else{
                        $total_price = bcmul($origin_amount, $item['number'], 2);
                        
                        //实付金额
                        $devideUserFee = $buy_nums != 0 ? bcdiv($devideUserFee, $buy_nums, 2) : 0;
                        $devideUserFee = bcmul($devideUserFee, $item['number'], 2);
                    }
                }else{
                    //用户实付
                    $devideUserFee = $order_items[$key]['divide_order_fee'];
                    
                    //商品单件价格
                    $origin_amount = $order_items[$key]['retail_price'];
                    
                    //SKU商品优惠
                    $total_promotion_amount = $orderItemList[$item_id]['part_mjz_discount'];
                    
                    //sku货品总价格(单价*数量)
                    $total_price = bcmul($origin_amount, $item['number'], 2);
                }
                
            }else{
                //普通商品
                //用户实付
                if(isset($couponRow[$oid]['user_payamount'])){
                    $devideUserFee = bcmul(bcdiv($couponRow[$oid]['user_payamount'], $orderItemList[$item_id]['nums'], 2), $item['number'], 2);
                }else{
                    $devideUserFee = $order_items[$key]['divide_order_fee'];
                }
                
                //商品单件价格
                $origin_amount = isset($couponRow[$oid]['origin_amount']) ? $couponRow[$oid]['origin_amount'] : $order_items[$key]['retail_price'];
                
                //SKU商品优惠
                $total_promotion_amount = isset($couponRow[$oid]['total_promotion_amount']) ? $couponRow[$oid]['total_promotion_amount'] : $orderItemList[$item_id]['part_mjz_discount'];
                
                //sku货品总价格(单价*数量)
                $total_price = bcmul($origin_amount, $item['number'], 2);
            }
            
            //单个优惠金额
            if($orderItemList[$item_id]['nums'] > 1){
                $total_promotion_amount = bcdiv($total_promotion_amount, $orderItemList[$item_id]['nums'], 2);
                $total_promotion_amount = bcmul($total_promotion_amount, $item['number'], 2);
            }
            
            $order_items[$key]['divide_user_fee'] = empty($devideUserFee) ? 0 : $devideUserFee;
            $order_items[$key]['total_promotion_amount'] = empty($total_promotion_amount) ? 0 : $total_promotion_amount;
            $order_items[$key]['origin_amount'] = empty($origin_amount) ? 0 : $origin_amount;
            $order_items[$key]['total_price'] = $total_price;
            
            //如果是最后一单实付金额重算，实付 - 累加小计 = 实付
            if ($orderItemList[$item_id]['split_num'] != $orderItemList[$item_id]['nums']) {
                continue;
            }
            
            //以下是拆分订单时，最后一次拆单场景；
            $devideUser = isset($devideFee[$key_name]['divide_user_fee']) ? $devideFee[$key_name]['divide_user_fee'] : 0 ;
            $devideOrder = isset($devideFee[$key_name]['divide_order_fee']) ? $devideFee[$key_name]['divide_order_fee'] : 0 ;
            
            $dly_origin_amount = isset($devideFee[$key_name]['origin_amount']) ? $devideFee[$key_name]['origin_amount'] : 0 ;
            $dly_total_promotion_amount = isset($devideFee[$key_name]['total_promotion_amount']) ? $devideFee[$key_name]['total_promotion_amount'] : 0 ;
            
            //coupon
            //PKG捆绑商品
            if($item['item_type'] == 'pkg'){
                //平台支付明细
                if($pkgItemList){
                    $residue = $pkgItemList[$oid]['pkg_items'][$product_id]['avg_amount'];
                    
                    $total_promotion_amount = $pkgItemList[$oid]['pkg_items'][$product_id]['avg_promotion_amount'];
                }else{
                    $residue = $order_items[$key]['divide_order_fee'];
                    $total_promotion_amount = $orderItemList[$item_id]['part_mjz_discount'];
                }
                
            }else{
                //普通商品
                //用户实付
                if(isset($couponRow[$oid]['user_payamount'])){
                    $residue = $couponRow[$oid]['user_payamount'];
                }else{
                    $residue = $order_items[$key]['divide_order_fee'];
                }
                
                //SKU货品总优惠
                if(isset($couponRow[$oid]['total_promotion_amount'])){
                    $total_promotion_amount = $couponRow[$oid]['total_promotion_amount'];
                }else{
                    $total_promotion_amount = $orderItemList[$item_id]['part_mjz_discount'];
                }
                
            }
            
            $residue = (empty($residue) ? 0 : $residue);
            $total_promotion_amount = (empty($total_promotion_amount) ? 0 : $total_promotion_amount);
            
            //diff
            $devideUserFee = $residue - $devideUser;
            if ($devideUser <= 0) {
                $devideUserFee = $residue;
            }
            
            if (!$residue) {
                $devideUserFee = 0;
            }
            
            $order_items[$key]['divide_user_fee'] = $devideUserFee;
            $devideOrderFee = $orderItemList[$item_id]['divide_order_fee'] - $devideOrder;
            if ($devideOrder <= 0) {
                $devideOrderFee = $orderItemList[$item_id]['divide_order_fee'];
            }
            if ($orderItemList[$item_id]['divide_order_fee'] <= 0) {
                $devideOrderFee = 0;
            }
            
            $order_items[$key]['divide_order_fee'] = $devideOrderFee;
            
            // 客户实付
            $order_items[$key]['actually_amount'] = $actually_amount;

            //SKU商品优惠
            $diff_promotion_amount = $total_promotion_amount - $dly_total_promotion_amount;
            $diff_promotion_amount = ($diff_promotion_amount <= 0 ? $total_promotion_amount : $diff_promotion_amount);
            $order_items[$key]['total_promotion_amount'] = $diff_promotion_amount;
            
            //[pkg拆单]最后一次拆分
            if($item['item_type'] == 'pkg' && $pkgItemList){
                //剩余sku货品总价格
                if($devideFee[$key_name]['total_price']){
                    $sku_total_price = $pkgItemList[$oid]['pkg_items'][$product_id]['sku_total_price'];
                    $order_items[$key]['total_price'] = bcsub($sku_total_price, $devideFee[$key_name]['total_price'], 2);
                }
                
                //[兼容]最后一次拆分数量为1个,并且没有优惠金额时,直接用剩余用户实付金额
                //@todo：当捆绑商品拆分多次后,单价均摊不均,导致报错：(商品数量*商品单价) != 用户实付金额;
                if($item['number'] == 1 && empty($order_items[$key]['total_promotion_amount'])){
                    $order_items[$key]['origin_amount'] = $devideOrderFee;
                }
            }
        }
        
        return $order_items;
    }
    
    public function create_delivery_items_detail($delivery_id, $order_items)
    {
        $didObj = $this->app->model('delivery_items_detail');
        $diObj  = $this->app->model('delivery_items');
        $oiObj  = $this->app->model('order_items');
        foreach ($order_items as $item)
        {
            $oi      = $di      = $di_item      = $did      = array();
            $oi      = $oiObj->dump($item['item_id']);
            $di      = $diObj->dump(array('delivery_id' => $delivery_id, 'product_id' => $item['product_id']));
            $item_id = $di['item_id'];
            
            //save
            $item_price = 0;
            $item_price = $oi['sale_price'] / $oi['quantity'];
            $did        = array(
                'delivery_id'      => $delivery_id,
                'delivery_item_id' => $item_id,
                'order_id'         => $oi['order_id'],
                'order_item_id'    => $oi['item_id'],
                'order_obj_id'     => $oi['obj_id'],
                'item_type'        => $oi['item_type'],
                'product_id'       => $oi['product_id'],
                'bn'               => $oi['bn'],
                'number'           => $item['number'],
                'price'            => $item_price,
                'amount'           => $item['number'] * $item_price,
                'oid'              => $item['oid'],
                's_type'           => $item['s_type'],
                'divide_order_fee' => $item['divide_order_fee'],
                'divide_user_fee'  => $item['divide_user_fee'],
                'retail_price'     => $item['retail_price'],
                'origin_amount' => $item['origin_amount'], //货品单件价格
                'total_price' => $item['total_price'], //货品总价格(单价*数量)
                'total_promotion_amount' => $item['total_promotion_amount'], //SKU商品优惠
                'actually_amount' => (isset($item['actually_amount']) ? $item['actually_amount'] : 0), // 客户实付
            );
            $didObj->save($did);
        }
    }

    public function call_delivery_api($delivery_id, $fastConsign = false)
    {

        kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_send($delivery_id);
    }

    public function array2xml2($data, $root = 'root')
    {
        $xml = '<' . $root . '>';
        $this->_array2xml($data, $xml);
        $xml .= '</' . $root . '>';
        return $xml;
    }

    public function _array2xml(&$data, &$xml)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (is_numeric($k)) {
                    $xml .= '<item>';
                    $xml .= $this->_array2xml($v, $xml);
                    $xml .= '</item>';
                } else {
                    $xml .= '<' . $k . '>';
                    $xml .= $this->_array2xml($v, $xml);
                    $xml .= '</' . $k . '>';
                }
            }
        } elseif (is_numeric($data)) {
            $xml .= $data;
        } elseif (is_string($data)) {
            $xml .= '<![CDATA[' . $data . ']]>';
        }
    }

    public function existStockIsPlus($product_id, $store, $item_id, $branch_id, &$err_msg, $bn)
    {
        $err_msg     = '';
        $branch_pObj = $this->app->model("branch_product");

        $bp = $branch_pObj->dump(array('branch_id' => $branch_id, 'product_id' => $product_id), 'store');
        if ($bp['store'] < $store) {
            $err_msg = $bn . "：此货号商品仓库商品数量不足";
            return false;
        }
        return true;
    }

    /*
     * 校验发货单
     */

    public function verifyDelivery($dly, $auto = false)
    {
        $dly_id      = $dly['delivery_id'];
        $dly_itemObj = $this->app->model('delivery_items');
        $opObj       = $this->app->model('operation_log');
        //对发货单详情进行校验完成处理
        if ($dly_itemObj->verifyItemsByDeliveryId($dly_id)) {
            $delivery['delivery_id'] = $dly_id;
            $delivery['verify']      = 'true';

            if (!$this->save($delivery)) {
                return false;
            }

            if ($dly['is_bind'] == 'true') {
                $ids = $this->getItemsByParentId($dly_id, 'array');
                foreach ($ids as $i) {
                    $dly_itemObj->verifyItemsByDeliveryId($i);
                }
            }

            //增加捡货绩效
            foreach (kernel::servicelist('tgkpi.pick') as $o) {
                if (method_exists($o, 'finish_pick')) {
                    $o->finish_pick($dly_id);
                }
            }

            if ($auto) {
                $msg = '发货单校验完成(免校验)';
            } else {
                $msg = '发货单校验完成';
            }

            if (kernel::single('desktop_user')->get_id()) {
                $opObj->write_log('delivery_check@ome', $dly_id, $msg);
            }

            #淘宝全链路 已捡货，已验货
            $this->sendMessageProduce($dly_id, 'picking');
            return true;
        } else {

            if (kernel::single('desktop_user')->get_id()) {
                $opObj->write_log('delivery_check@ome', $dly_id, '发货单校验未完成');
            }

            return false;
        }
    }

    /**
     * 发货单验货后触发淘宝全链路消息，并补投加急发货状态回告任务。
     *
     * @param int $delivery_id
     * @param array $statusArr
     * @return void
     */
    public function sendMessageProduce($delivery_id, $statusArr)
    {
        if (empty($this->deliveryOrderModel)) {
            $this->getDeliveryOrderModel();
        }
        $deliveryOrderInfo = $this->deliveryOrderModel->getList('*', array('delivery_id' => $delivery_id));
        $orderIds          = array();
        foreach ($deliveryOrderInfo as $delivery_order) {
            $orderIds[] = $delivery_order['order_id'];
        }

        kernel::single('ome_event_trigger_shop_order')->order_message_produce($orderIds, $statusArr);
        if ($orderIds) {
            foreach (array_values(array_unique(array_map('intval', $orderIds))) as $orderId) {
                app::get('ome')->model('misc_task')->saveMiscTask([
                    'obj_id' => $orderId,
                    'obj_type' => 'urgent_logistics_dly',
                    'exec_time' => time(),
                    'addon' => json_encode(['scene' => 'delivery_status'], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }
    /**
     * 发送CRM赠品日志
     * @param Int $delivery_id
     */
    public function crmSendGiftLog($delivery_id)
    {
        if (empty($this->deliveryOrderModel)) {
            $this->getDeliveryOrderModel();
        }
        $obj_crm_rpc       = kernel::single('crm_rpc_gift');
        $deliveryOrderInfo = $this->deliveryOrderModel->getList('*', array('delivery_id' => $delivery_id));
        foreach ($deliveryOrderInfo as $delivery_order) {
            $logData = $this->getCrmGiftLog($delivery_order['order_id']);
            if ($logData) {
                $obj_crm_rpc->getGiftRuleLog($logData);
            }
        }
    }
    /**
     * 验证CRM赠品是否发送
     * @param Int $delivery_id
     */
    public function getCrmGiftLog($order_id)
    {
        if (!$order_id) {
            return false;
        }
        $app_type    = channel_ctl_admin_channel::$appType;
        $obj_channel = app::get('channel')->model('channel');
        $node_info   = $obj_channel->getList('node_id', array('channel_type' => $app_type['crm']));
        if (empty($node_info) || strlen($node_info[0]['node_id']) <= 0) {
            return false;
        }
        $crm_cfg = app::get('crm')->getConf('crm.setting.cfg');
        if (empty($crm_cfg)) {
            return false;
        }
        if ($crm_cfg['gift'] != 'on') {
            return false;
        }
        $orderObj   = app::get('ome')->model('orders');
        $order_info = $obj_channel->getOrderInfo($order_id);
        if (empty($order_info)) {
            return false;
        }
        $shop_id = $order_info['shop_id']; #店铺节点
        if (empty($crm_cfg['name'][$shop_id])) {
            return false;
        }
        #赠品数据
        $order_item_info = $obj_channel->getOrderItemInfo($order_id, 'gift');
#        $order_item_info = $obj_channel->getOrderItemInfo($order_id);
        if (empty($order_item_info)) {
            return false;
        }
        $mobile        = $order_info['ship_mobile'] ? $order_info['ship_mobile'] : '';
        $tel           = $order_info['ship_tel'] ? $order_info['ship_tel'] : '';
        $ship_area     = $order_info['ship_area'];
        $ship_area_arr = '';
        if ($ship_area && is_string($ship_area)) {
            $firstPos      = strpos($ship_area, ':');
            $lastPos       = strrpos($ship_area, ':');
            $newShipArea   = substr($ship_area, $firstPos + 1, ($lastPos - $firstPos - 1));
            $ship_area_arr = explode('/', $newShipArea);
        }
        $payed   = $order_info['payed'] ? $order_info['payed'] : 0; #付款金额
        $isCod   = $order_info['is_cod'] == 'true' ? 1 : 0; #是否货到付款
        $logData = array(
            'buyer_nick'    => $order_info['uname'],
            'receiver_name' => $order_info['ship_name'],
            'mobile'        => $mobile,
            'tel'           => $tel,
            'shop_id'       => $shop_id,
            'order_bn'      => $order_info['order_bn'],
            'province'      => $ship_area_arr[0],
            'city'          => $ship_area_arr[1],
            'district'      => $ship_area_arr[2],
            'total_amount'  => $order_info['total_amount'],
            'payed'         => $payed,
            'is_cod'        => $isCod,
            'addon'         => $order_item_info,
        );
        return $logData;
    }

    /**
     * 获得发货单订单关联模式
     * Enter description here ...
     */
    public function getDeliveryOrderModel()
    {
        $this->deliveryOrderModel = $this->app->model('delivery_order');
    }

    public function queueConsign($delivery_id)
    {
        $oQueue    = app::get('base')->model('queue');
        $queueData = array(
            'queue_title' => '订单导入',
            'start_time'  => time(),
            'params'      => array(
                'sdfdata' => $delivery_id,
                'app'     => 'ome',
                'mdl'     => 'delivery',
            ),
            'worker'      => 'ome_delivery_consign.run',
        );
        $oQueue->save($queueData);
    }

    /*
     * 处理订单发货数量
     */

    public function consignOrderItem($delivery)
    {
        $ord_itemObj = $this->app->model('order_items');
        $didObj      = $this->app->model('delivery_items_detail');
        if (!empty($delivery['delivery_items'])) {
            foreach ($delivery['delivery_items'] as $r) {
                $filter = array(
                    'delivery_item_id' => $r['item_id'],
                    'product_id'       => $r['product_id'],
                );

                $rows = $didObj->getList('item_detail_id,order_item_id,number', $filter, 0, -1);
                if ($rows) {
                    foreach ($rows as $row) {
                        $num = (int) $row['number'];
                        $sql = "UPDATE sdb_ome_order_items SET sendnum = IFNULL(sendnum,0)+" . $num . " WHERE item_id=" . $row['order_item_id'];
                        $this->db->exec($sql);
                    }
                }
                unset($rows);
            }
        }
    }

    /**
     * 获取电子面单类型
     */
    public function getChannelType($logi_id)
    {
        $channel_type = '';
        if ($logi_id > 0) {
            $dlyCorpObj = app::get('ome')->model('dly_corp');
            $dlyCorp    = $dlyCorpObj->dump($logi_id, 'channel_id,tmpl_type,shop_id');
            if ($dlyCorp['channel_id'] > 0) {
                $channelObj   = app::get('logisticsmanager')->model('channel');
                $channel      = $channelObj->dump($dlyCorp['channel_id']);
                $channel_type = $channel['channel_type'];
            }
        }
        return $channel_type;
    }

    public function getShopType($delivery_id)
    {
        if (!$delivery_id) {
            return false;
        }
        $deliveryObj       = app::get('ome')->model('delivery');
        $delivery_orderObj = app::get('ome')->model('delivery_order');
        $orderObj          = app::get('ome')->model('orders');
        $shopObj           = app::get('ome')->model('shop');
        $delivery_detail   = $deliveryObj->dump($delivery_id, 'is_bind,parent_id');
        $delivery_order    = $delivery_orderObj->dump(array('delivery_id' => $delivery_id));
        $order_detail      = $orderObj->dump($delivery_order['order_id'], 'ship_status,shop_id');
        $shop_detail       = $shopObj->dump($order_detail['shop_id'], 'node_type');
        $shop_type         = $shop_detail['node_type'];
        return $shop_type;
    }

    public function getDeliveryCost($area_id = 0, $logi_id = 0, $weight = 0)
    {
        if ($logi_id && $logi_id > 0) {
            $dlyCorpObj = $this->app->model('dly_corp');
            $corp       = $dlyCorpObj->dump($logi_id); //物流公司信息
        }

        //物流预算费用计算
        if ($area_id && $area_id > 0) {
            $regionObj = kernel::single('eccommon_regions');
            $region    = $regionObj->getOneById($area_id);
            $regionIds = explode(',', $region['region_path']);
            foreach ($regionIds as $key => $val) {
                if ($regionIds[$key] == '' || empty($regionIds[$key])) {
                    unset($regionIds[$key]);
                }
            }
        }

        if ($corp['area_fee_conf'] && $regionIds) {
            $area_fee_conf = unserialize($corp['area_fee_conf']);
            foreach ($area_fee_conf as $k => $v) {
                $areaIds = array();
                $areaIds = explode(',', $v['areaGroupId']);

                if (array_intersect($areaIds, $regionIds)) {
                    //如果配送地区匹配，优先使用地区设置的配送费用，及公式
                    $corp['firstprice']     = $v['firstprice'];
                    $corp['continueprice']  = $v['continueprice'];
                    $corp['dt_expressions'] = $v['dt_expressions'];
                    break;
                }
            }
        }

        if ($corp['dt_expressions'] && $corp['dt_expressions'] != '') {
            $price = utils::cal_fee($corp['dt_expressions'], $weight, 0, $corp['firstprice'], $corp['continueprice']); //TODO 生成快递费用
        } else {
            $price = 0;
        }
        return $price;
    }

    public function searchOptions()
    {
        $parentOptions = parent::searchOptions();
        $childOptions  = array(
            'order_bn'        => app::get('base')->_('订单号'),
            'delivery_bn'     => app::get('base')->_('发货单号'),
            'member_uname'    => app::get('base')->_('用户名'),
            'ship_name'       => app::get('base')->_('收货人'),
            'ship_mobile'     =>app::get('ome')->_('联系手机'),
            'ship_tel'        =>app::get('ome')->_('联系电话'),
            'product_bn'      => app::get('base')->_('货号'),
            'product_barcode' => app::get('base')->_('条形码'),
            'delivery_ident'  => app::get('base')->_('打印批次号'),
        );
        return array_merge($childOptions, $parentOptions);
    }

    public function getDeliveryOrderCreateTime($dly_ids)
    {
        $str_dly_ids = implode(',', $dly_ids);
        $sql         = 'SELECT order_createtime FROM sdb_ome_delivery  WHERE delivery_id IN(' . $str_dly_ids . ')';
        $rows        = $this->db->select($sql);

        if ($rows) {
            $lenOrder         = count($rows);
            $order_createtime = $rows[0]['order_createtime'];
            for ($i = 1; $i < $lenOrder; $i++) {
                if (isset($rows[$i])) {
                    if ($order_createtime > $rows[$i]['order_createtime']) {
                        $order_createtime = $rows[$i]['order_createtime'];
                    }
                }
            }
            return $order_createtime;
        } else {
            return false;
        }
    }

    public function getAllTotalAmountByDelivery($delivery_order)
    {
        $order_total_amount = 0;
        if (count($delivery_order) > 1) {
//合并
            $is_vaild = true;
            foreach ($delivery_order as $deli_order) {
                $total_amount = $this->getTotalAmountByDelivery($deli_order['order_id'], $deli_order['delivery_id']);
                if ($total_amount) {
                    $order_total_amount += $total_amount;
                } else {
                    $is_vaild = false;
                    break;
                }
            }

            if (!$is_vaild) {
                $order_total_amount = 0;
            }
        } else {
//单张
            $delivery_order     = current($delivery_order);
            $order_total_amount = $this->getTotalAmountByDelivery($delivery_order['order_id'], $delivery_order['delivery_id']);
        }

        return $order_total_amount;
    }

    public function getTotalAmountByDelivery($order_id, $delivery_id)
    {
        $order_total_amount = 0;
        $objOrders          = $this->app->model('orders');
        $order              = $objOrders->order_detail($order_id);

        if ($order['process_status'] == 'splited') {
//已拆分
            $ids = $this->getDeliverIdByOrderId($order_id);
            if (count($ids) == 1) {
//发货单必须是一张
                $order_total_amount = $order['total_amount'];
            }
        }

        return $order_total_amount;
    }

    /**
     * 根据订单ID获取发货单的商品
     *
     * @param $order_id
     * @return array
     */
    public function getDeliverItemByOrderId($order_id)
    {
        $list = $this->db->select("SELECT dt.* FROM sdb_ome_delivery_order AS dord
                                            LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            LEFT JOIN sdb_ome_delivery_items AS dt ON d.delivery_id = dt.delivery_id
                                            WHERE dord.order_id='{$order_id}' AND d.is_bind='false' AND d.disabled='false' AND d.status IN ('ready','progress','succ') AND d.pause='false'");
        $new_list = array();
        foreach ($list as $item) {
            if (!isset($new_list[$item['delivery_id']])) {
                $new_list[$item['delivery_id']] = array();
            }

            $new_list[$item['delivery_id']][] = $item;
        }

        return $new_list;
    }

    /**
     * 根据订单ID获取发货单列表
     *
     * @param $order_id
     * @return array
     */
    public function getDeliversByOrderId($order_id)
    {
        return $this->db->select("SELECT d.* FROM sdb_ome_delivery_order AS dord
                                            LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            WHERE dord.order_id='{$order_id}' AND d.is_bind='false' AND d.disabled='false' AND d.status IN ('ready','progress','succ') AND d.pause='false'");
    }

    /**
     * 统计已打印完成待校验的发货单总数
     */
    public function countNoVerifyDelivery()
    {
        //$status_cfg = $this->app->getConf('ome.delivery.status.cfg');
        $deliCfgLib      = kernel::single('ome_delivery_cfg');
        $btncombi_single = $deliCfgLib->btnCombi('single');
        $btncombi_multi  = $deliCfgLib->btnCombi('multi');
        $btncombi_basic  = $deliCfgLib->btnCombi();
        $filter          = array(
            'parent_id'    => 0,
            'expre_status' => 'true',
            'verify'       => 'false',
            'disabled'     => 'false',
            'pause'        => 'false',
            'status'       => 'progress',
        );
        $filter['print_finish'] = array(
            ''       => $btncombi_basic,
            'single' => $btncombi_single,
            'multi'  => $btncombi_multi,
        );
        if ($deliCfgLib->deliveryCfg == '') {
            $filter['addonSQL'] = ' logi_no IS NOT NULL ';
        }

        $oBranch  = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $filter['branch_id'] = $branch_ids;
            }
        }

        $num = $this->count_logi_no($filter);
        return $num;
    }

    /**
     * 统计已校验待发货的发货单总数
     */
    public function countNoProcessDelivery()
    {
        $filter = array(
            'parent_id' => 0,
//            'stock_status' => 'true',
            //            'deliv_status' => 'true',
            //            'expre_status' => 'true',
            'verify'    => 'true',
            'disabled'  => 'false',
            'pause'     => 'false',
            'process'   => 'false',
            'status'    => 'progress',
        );

        $oBranch  = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $filter['branch_id'] = $branch_ids;
            }
        }

        $num = $this->count($filter);
        return $num;
    }

    /**
     * 统计子物流表待发货的发货单总数
     * wujian@shopex.cn
     * 2012年3月19日
     */
    public function countNoProcessDeliveryBill()
    {
        $filter = array(
            'parent_id' => 0,
//            'stock_status' => 'true',
            //            'deliv_status' => 'true',
            //            'expre_status' => 'true',
            'verify'    => 'true',
            'disabled'  => 'false',
            'pause'     => 'false',
            'process'   => 'false',
            'status'    => 'progress',
        );

        $oBranch  = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids) {
                $filter['branch_id'] = $branch_ids;
            }
        }
        /*$num =0;
        //$num = $this->count($filter);
        $numarr = $this->getList('logi_number, delivery_logi_number', $filter, 0, -1);
        for($i=0;$i<=count($numarr);$i++){
        $num += $numarr[$i]['logi_number']-$numarr[$i]['delivery_logi_number'];
        }*/
        $num = $this->count($filter);

        $dataDly = $this->getList('delivery_id', $filter, 0, -1);
        $billObj = app::get('ome')->model('delivery_bill');
        foreach ($dataDly as $v) {
            $billFilter = array(
                'status'      => 0,
                'delivery_id' => $v['delivery_id'],
            );
            $num += $billObj->count($billFilter);
        }
        return $num;
    }

    public function getOrderByDeliveryId($delivery_id)
    {
        if ($delivery_id) {
            $sql = "SELECT O.pay_status,O.order_bn FROM `sdb_ome_orders` as O LEFT JOIN
                `sdb_ome_delivery_order` as DO ON DO.order_id=O.order_id
                WHERE DO.delivery_id ='" . $delivery_id . "'";

            $rows = $this->db->selectrow($sql);
            return $rows;
        }
    }

    public function getOrderByDeliveryBn($delivery_bn)
    {
        if ($delivery_bn) {
            $sql = "SELECT O.* FROM `sdb_ome_orders` as O LEFT JOIN
                `sdb_ome_delivery_order` as DO ON DO.order_id=O.order_id 
                LEFT JOIN sdb_ome_delivery as D ON D.delivery_id=DO.delivery_id 
                WHERE D.delivery_bn ='" . $delivery_bn . "'";

            $rows = $this->db->selectrow($sql);
            return $rows;
        }
    }

    //从载方法 以解决 发货中未录入快递单号不能过滤的bug
    //    function getlist_logi_no($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
    //        if(!$cols){
    //            $cols = $this->defaultCols;
    //        }
    //        if(!empty($this->appendCols)){
    //            $cols.=','.$this->appendCols;
    //        }
    //        if($this->use_meta){
    //             $meta_info = $this->prepare_select($cols);
    //        }
    //        $orderType = $orderType?$orderType:$this->defaultOrder;
    //        if($filter['logi_no'] == 'NULL'){
    //            unset($filter['logi_no']);
    //            $sql = 'SELECT '.$cols.' FROM `'.$this->table_name(true).'` WHERE '.$this->_filter($filter).' AND `logi_no` IS NULL';
    //        }else{
    //            $sql = 'SELECT '.$cols.' FROM `'.$this->table_name(true).'` WHERE '.$this->_filter($filter);
    //        }
    //        if ($orderType)
    //            $sql.=' ORDER BY ' . (is_array($orderType) ? implode($orderType, ' ') : $orderType);
    //        $data = $this->db->selectLimit($sql,$limit,$offset);
    //        $this->tidy_data($data, $cols);
    //        if($this->use_meta && count($meta_info['metacols']) && $data){
    //            foreach($meta_info['metacols'] as $col){
    //                $obj_meta = new dbeav_meta($this->table_name(true),$col,$meta_info['has_pk']);
    //                $obj_meta->select($data);
    //            }
    //        }
    //        return $data;
    //    }

    /**
     * 重载带物流单号的列表查询，并在启用时让加急发货单优先排序。
     *
     * @param string $cols
     * @param array $filter
     * @param int $offset
     * @param int $limit
     * @param string|array|null $orderType
     * @return array
     */
    public function getlist_logi_no($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderType = null)
    {
        if (!$cols) {
            $cols = $this->defaultCols;
        }
        if (!empty($this->appendCols)) {
            $cols .= ',' . $this->appendCols;
        }
        if ($this->use_meta) {
            $meta_info = $this->prepare_select($cols);
        }
        $orderType = $orderType ? $orderType : $this->getUrgentPriorityOrder($this->defaultOrder, $filter);

        if ($filter['logi_no'] == 'NULL') {
            unset($filter['logi_no']);
            $where = $this->_filter($filter) . ' AND `logi_no` IS NULL';
        } else {
            $where = $this->_filter($filter);
        }

        //增加对 idx_split 的 order 排序的支持
        $orderType = (is_array($orderType) ? implode(' ', $orderType) : $orderType);
        $table     = $this->table_name(true);

        if (strpos($orderType, 'idx_split') !== false) {

            $table .= " LEFT JOIN (SELECT COUNT(idx_split) AS iNum, idx_split AS idx FROM {$table} WHERE {$where} GROUP BY idx_split ) AS i ON i.idx=idx_split";
            $orderType = str_replace('idx_split', 'skuNum, itemNum, iNum DESC, idx_split,delivery_id', $orderType);
        }

        $sql = 'SELECT ' . $cols . ' FROM ' . $table . ' WHERE ' . $where;

        if ($orderType
        ) {
            $sql .= ' ORDER BY ' . $orderType;
        }

        $data = $this->db->selectLimit($sql, $limit, $offset);
        // 数据解密
        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field], $type);
                }
            }
        }
        $this->tidy_data($data, $cols);
        if ($this->use_meta && count($meta_info['metacols']) && $data) {
            foreach ($meta_info['metacols'] as $col) {
                $obj_meta = new dbeav_meta($this->table_name(true), $col, $meta_info['has_pk']);
                $obj_meta->select($data);
            }
        }
        return $data;
    }

    public function count_logi_no($filter = null)
    {
        if ($filter['logi_no'] == 'NULL') {
            unset($filter['logi_no']);
            $row = $this->db->select('SELECT count(*) as _count FROM `' . $this->table_name(1) . '` WHERE ' . $this->_filter($filter) . ' AND `logi_no` IS NULL');
        } else {
            $row = $this->db->select('SELECT count(*) as _count FROM `' . $this->table_name(1) . '` WHERE ' . $this->_filter($filter));
        }
        return intval($row[0]['_count']);
    }

    public function getPrintStockPrice($ids)
    {
        $data = array();
        $sql  = 'SELECT did.bn,SUM(did.amount) AS _amount FROM sdb_ome_delivery_items_detail AS did WHERE did.delivery_id IN(' . implode(',', $ids) . ') GROUP BY did.bn';
        $rows = $this->db->select($sql);
        foreach ($rows as $row) {
            $data[strtoupper($row['bn'])] = $row['_amount'];
        }
        return $data;
    }

    /**
     * 根据物流ID获取站内对应的物流公司
     * @param array $logi_ids
     *
     */
    public function getOMELogiName($logi_ids)
    {
        if (!$logi_ids) {
            return false;
        }

        $logi_names     = $this->db->select('SELECT corp_id,name FROM sdb_ome_dly_corp WHERE corp_id IN(' . implode(',', $logi_ids) . ')');
        $new_logi_names = array();
        if ($logi_names) {
            foreach ($logi_names as $l) {
                $new_logi_names[$l['corp_id']] = $l['name'];
            }
            return $new_logi_names;
        } else {
            return false;
        }
    }

    public function getPrintProductName($ids)
    {
        $printProductNames = array();
        $sql               = 'SELECT distinct oi.order_id,oi.name,oi.bn,oi.addon,bp.store_position
                    FROM sdb_ome_delivery_order AS d2o
                LEFT JOIN sdb_ome_order_items AS oi
                    ON d2o.order_id = oi.order_id
                LEFT JOIN (
                    SELECT bpp.*
                        FROM (
                            SELECT pos_id,product_id
                            FROM sdb_ome_branch_product_pos
                            ORDER BY create_time DESC
                        )bpp
                    GROUP BY bpp.product_id
                 )bb
                    ON bb.product_id = oi.product_id
                 LEFT JOIN sdb_ome_branch_pos bp
                    ON bp.pos_id = bb.pos_id
                WHERE d2o.delivery_id IN(' . implode(',', $ids) . ') ORDER BY d2o.order_id';
        $rows = $this->db->select($sql);
        foreach ($rows as $row) {
            $row['bn'] = trim($row['bn']);

            if (isset($printProductNames[$row['bn']])) {
                continue;
            }

            $row['addon'] = ome_order_func::format_order_items_addon($row['addon']);

            $printProductNames[$row['bn']] = $row;
        }

        return $printProductNames;
    }

    public function getOrderIdsByDeliveryIds($delivery_ids)
    {
        $rows      = $this->db->select('select order_id from sdb_ome_delivery_order where delivery_id in(' . implode(',', $delivery_ids) . ')');
        $order_ids = array();
        foreach ($rows as $row) {
            $order_ids[] = $row['order_id'];
        }

        return $order_ids;
    }

    public function update($data, $filter = array(), $mustUpdate = null)
    {

        //取id和更新上下位置换一下解决由于发货单更新后，订单更新打印状态查询不到发货单id的问题，因为发货单发货状态变成已发货了
        //获取更新的列表
        $deliveryIds = $this->getList('delivery_id', $filter);

        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field], $type);
            }
        }
        //调用原有处理
        $result = parent::update($data, $filter, $mustUpdate);

        if (!empty($deliveryIds)) {
            foreach ($deliveryIds as $row) {
                $this->updateOrderLogi($row['delivery_id'], $data);
            }
        }

        return $result;
    }

    public function insert(&$data)
    {
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field], $type);
            }
        }

        return parent::insert($data);
    }

    public function getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderType = null)
    {
        $data = parent::getList($cols, $filter, $offset, $limit, $orderType);

        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field], $type);
                }
            }
        }

        return $data;
    }

    /**
     * 重载 save 方法：同步订单物流信息，并把订单加急标签继承到发货单。
     *
     * @param array $data
     * @param bool|null $mustUpdate
     * @return bool
     */
    public function save(&$data, $mustUpdate = null)
    {

        //调用原有处理
        $result = parent::save($data, $mustUpdate);

        if ($data['delivery_id'] > 0) {
            $this->updateOrderLogi($data['delivery_id'], $data);
            $this->inheritUrgentLabelFromOrders($data['delivery_id']);
        }

        return $result;
    }

    /**
     * 在加急优先配置开启时，构造发货单列表的加急优先排序 SQL。
     *
     * @param string|array $orderType
     * @param array $filter
     * @return string|array
     */
    protected function getUrgentPriorityOrder($orderType, $filter = [])
    {
        if (!$this->isUrgentPriorityEnabledByFilter($filter)) {
            return $orderType;
        }
        $orderSql = is_array($orderType) ? implode( ' ', $orderType) : $orderType;
        $prefix = "CASE WHEN EXISTS (
            SELECT 1 FROM sdb_ome_bill_label bl
            WHERE bl.bill_id = ".$this->table_name(true).".delivery_id
            AND bl.bill_type = 'delivery'
            AND bl.label_code = '".self::URGENT_LABEL_CODE."'
        ) THEN 0 ELSE 1 END ASC";
        return $prefix . ($orderSql ? ',' . $orderSql : '');
    }

    /**
     * 判断当前查询上下文是否开启加急单优先排序。
     *
     * @param array $filter
     * @return bool
     */
    protected function isUrgentPriorityEnabledByFilter($filter = [])
    {
        $shopId   = null;
        $branchId = null;
        if (!empty($filter['shop_id']) && !is_array($filter['shop_id'])) {
            $shopId = $filter['shop_id'];
        }
        if (!empty($filter['branch_id']) && !is_array($filter['branch_id'])) {
            $branchId = $filter['branch_id'];
        } elseif (!empty($filter['ext_branch_id']) && is_array($filter['ext_branch_id']) && count($filter['ext_branch_id']) == 1) {
            $branchId = current($filter['ext_branch_id']);
        }

        $shopConf = $shopId ? app::get('ome')->getConf('ome.urgent_delivery.prioritize_wave.shop.' . $shopId) : null;
        if ($shopConf !== null && $shopConf !== '') {
            return $shopConf !== 'off';
        }
        $branchConf = $branchId ? app::get('ome')->getConf('ome.urgent_delivery.prioritize_wave.branch.' . $branchId) : null;
        if ($branchConf !== null && $branchConf !== '') {
            return $branchConf !== 'off';
        }
        $globalConf = app::get('ome')->getConf('ome.urgent_delivery.prioritize_wave');
        return $globalConf === null || $globalConf === '' || $globalConf === 'on';
    }

    /**
     * 在发货单保存后，把关联订单上的加急标签补写到发货单标签表。
     *
     * @param int $deliveryId
     * @return bool
     */
    public function inheritUrgentLabelFromOrders($deliveryId)
    {
        if (!$deliveryId) {
            return false;
        }
        $labelLib = kernel::single('ome_bill_label');
        if ($labelLib->existLabel($deliveryId, self::URGENT_LABEL_CODE, 'delivery')) {
            return true;
        }
        $orderRows = app::get('ome')->model('delivery_order')->getList('order_id', ['delivery_id' => $deliveryId]);
        if (!$orderRows) {
            return false;
        }
        foreach ($orderRows as $row) {
            if ($labelLib->existLabel($row['order_id'], self::URGENT_LABEL_CODE)) {
                $err = '';
                return $labelLib->markBillLabel($deliveryId, '', self::URGENT_LABEL_CODE, 'delivery', $err);
            }
        }
        return false;
    }

    /**
     * 更新发货单对应的订单发货物流信息
     *
     * @param Integer $delivery_id
     * @param Array $data
     * @return void
     */
    public function updateOrderLogi($delivery_id, $data)
    {

        //执行订单更新过程
        $updatebody = array();
        //检查打印状态
        if (key_exists('status', $data) && in_array($data['status'], array('back', 'cancel', 'failed'))) {

            //发货单取消，对应订单数据中的物流信息也清除 （如有分折订单，需做更多检查）
            $updatebody = array("logi_no = ''", 'print_status = 0');
        } else {
            $chkField       = array('expre_status' => 0x01, 'stock_status' => 0x02, 'deliv_status' => 0x04);
            $addPrintStatus = 0;
            $removeStatus   = 0x7;
            if (key_exists('expre_status', $data) || key_exists('stock_status', $data) || key_exists('deliv_status', $data)) {
                foreach ($chkField as $fieldName => $state) {
                    if (isset($data[$fieldName])) {
                        if ($data[$fieldName] == 'true') {
                            $addPrintStatus = $addPrintStatus | $state;
                        } else {
                            $removeStatus = $removeStatus & (~$state);
                        }
                    }
                }
                $updatebody[] = "print_status = print_status | $addPrintStatus & $removeStatus";
            }

            //检查快递单号
            if (key_exists('logi_no', $data)) {
                if (empty($data['logi_no'])) {
                    $updatebody[] = "logi_no = ''";
                } else {
                    $updatebody[] = "logi_no = '".$data['logi_no']."'";
                }
            }
            //检查快递公司
            if (key_exists('logi_id', $data)) {
                if (!empty($data['logi_id'])) {
                    $updatebody[] = "logi_id = " . $data['logi_id'];
                }
            }
        }
        //有更新内容，则更新订单
        if (!empty($updatebody)) {
            $d2o = app::get('ome')->model('delivery_order')->getList('order_id', array('delivery_id' => $delivery_id));
            if (!empty($d2o)) {
                $ids = array();
                foreach ($d2o as $oId) {
                    $ids[] = $oId['order_id'];
                }

                kernel::database()->exec("UPDATE sdb_ome_orders SET " . join(',', $updatebody) . " WHERE order_id IN (" . join(',', $ids) . ")");
            }
        }
    }

    public function repairCheck($delivery_ids)
    {
        $rows = $this->db->select('select delivery_id from sdb_ome_delivery where process ="false" and verify="true" and logi_no in("' . implode('","', $delivery_ids) . '")');
        if ($rows) {
            foreach ($rows as $row) {
                $this->db->exec('update sdb_ome_delivery_items set verify="true",verify_num=number where delivery_id=' . $row['delivery_id']);
            }
        }
    }

    //根据发货单id调整打印排序
    public function printOrderByByIds($ids)
    {
        if (!$ids) {
            return false;
        }

        $table = $this->table_name(true);
        $where = 'delivery_id in(' . implode(',', $ids) . ')';
        $table .= " LEFT JOIN (SELECT COUNT(idx_split) AS iNum, idx_split AS idx FROM {$table} WHERE {$where} GROUP BY idx_split ) AS i ON i.idx=idx_split";
        $orderType    = 'skuNum, itemNum, iNum DESC, idx_split,delivery_id';
        $sql          = 'SELECT delivery_id FROM ' . $table . ' WHERE ' . $where . ' ORDER BY ' . $orderType;
        $list         = $this->db->select($sql);
        $delivery_ids = array();
        foreach ($list as $row) {
            $delivery_ids[] = $row['delivery_id'];
        }

        return $delivery_ids;
    }
    /* 检测快递单是主快递单 还是子表中的快递单
     * wujian@shopex.cn
     * 2012年3月20日
     */
    public function checkDeliveryOrBill($logi_no)
    {
        $dlyObj = $this->app->model('delivery');
        $dly    = $dlyObj->dump(array('logi_no|nequal' => $logi_no), '*');

        if (empty($dly)) {
            $dlyObjBill = $this->app->model('delivery_bill');
            $dlyBill    = $dlyObjBill->dump(array('logi_no|nequal' => $logi_no), '*');
            if ($dlyBill) {
                $dly = $dlyObj->dump(array('delivery_id|nequal' => $dlyBill["delivery_id"]), '*');
                return $dly["logi_no"];
            } else {
                return false;
            }
        } else {
            return $logi_no;
        }
    }

    /* 检测主快递单是否有子快递单
     * wujian@shopex.cn
     * 2012年3月22日
     */
    public function checkDeliveryHaveBill($delivery_id)
    {
        $dlyBillObj = $this->app->model('delivery_bill');
        $dlyBill    = $dlyBillObj->getList('*', array('delivery_id|nequal' => $delivery_id, 'status' => 0));
        if ($dlyBill) {
            return $dlyBill;
        } else {
            return false;
        }
    }

    /*
     *获取发货单优惠金额
     *
     *@date 2012-04-26
     */

    public function getPmt_price($data)
    {
        $orderObj  = $this->app->model('orders');
        $pmt_order = array();

        return $pmt_order;
    }

    /*
     *获取商品销售单价
     *@date 2012-04-26
     */

    public function getsale_price($data)
    {
        $orderObj   = $this->app->model('orders');
        $sale_order = array();
        foreach ($data as $key => $val) {
            $order = $orderObj->dump($val['order_id'], "order_id", array("order_objects" => array("*", array("order_items" => array('bn,pmt_price,sale_price,nums,price')))));
            foreach ($order['order_objects'] as $k => $v) {
                //基础物料线order_objects数据表上有delete字段
                if ($v['delete'] == 'false') {
                    foreach ($v['order_items'] as $k1 => $v1) {
                        if (isset($sale_order[$v1['bn']])) {
                            $sale_order[$v1['bn']]['quantity'] += $v1['quantity'];
                            $sale_order[$v1['bn']]['sale_price'] += $v1['sale_price'];
                        } else {
                            $sale_order[$v1['bn']]['quantity']   = $v1['quantity'];
                            $sale_order[$v1['bn']]['sale_price'] = $v1['sale_price'];
                        }
                    }
                }
            }
        }

        $sale_price = array();
        foreach ($sale_order as $k => $v) {
            $price          = $v['sale_price'];
            $quantity       = $v['quantity'];
            $sale_price[$k] = round($price / $quantity, 2);
        }

        return $sale_price;

    }
    /**
     * 根据订单bn获取发货单信息
     *
     * @param  void
     * @return void
     * @author
     **/
    public function getDeliveryByOrderBn($order_bn, $col = '*')
    {
        $order_info = app::get('ome')->model('orders')->select()->columns('order_id')->where('order_bn=?', $order_bn)->instance()->fetch_row();
        $sql        = "SELECT *
                FROM sdb_ome_delivery_order as deo
                LEFT JOIN sdb_ome_delivery AS d ON deo.delivery_id = d.delivery_id
                WHERE deo.order_id={$order_info['order_id']}
                AND (d.parent_id=0 OR d.is_bind='true')
                AND d.disabled='false'
                AND d.status NOT IN('failed','cancel','back','return_back')";
        $delivery = kernel::database()->select($sql);
        // 发货单解密
        foreach ((array) $delivery as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $delivery[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field], $type);
                }
            }
        }
        if (isset($delivery[0]) && $delivery) {
            return $delivery[0];
        } else {
            return array();
        }
    }
    /**
     * 检查发货单是否已经打印完成
     *
     * @author chenping<chenping@shopex.cn>
     * @version 2012-5-15 00:14
     * @param Array $dly 发货单信息 $dly
     * @param Array $msg 错误信息
     * @return TRUE:打印完成、FALSE:打印未完成
     **/
    public function checkPrintFinish($dly, &$msg)
    {
        $deliCfgLib = kernel::single('ome_delivery_cfg');
        if ($deliCfgLib->deliveryCfg != '') {
            $btncombi            = $deliCfgLib->btnCombi($dly['deli_cfg']);
            list($stock, $delie) = explode('_', $btncombi);
            if (1 == $stock) {
                if ($dly['stock_status'] == 'false') {
                    $msg[] = array('bn' => $dly['logi_no'], 'msg' => $this->app->_('备货单未打印'));
                    return false;
                }
            }
            if (1 == $delie) {
                if ($dly['deliv_status'] == 'false') {
                    $msg[] = array('bn' => $dly['logi_no'], 'msg' => $this->app->_('发货单未打印'));
                    return false;
                }
            }
        } else {
            # 默认情况全部开启
            if ($dly['stock_status'] == 'false') {
                // 备货单未打印
                $msg[] = array('bn' => $dly['logi_no'], 'msg' => $this->app->_('备货单未打印'));
                return false;
            }
            if ($dly['deliv_status'] == 'false') {
                // 发货单未打印
                $msg[] = array('bn' => $dly['logi_no'], 'msg' => $this->app->_('发货单未打印'));
                return false;
            }
        }
        if ($dly['expre_status'] == 'false') {
            // 快递单未打印
            $msg[] = array('bn' => $dly['logi_no'], 'msg' => $this->app->_('快递单未打印'));
            return false;
        }
        return true;
    }

    /**
     * 计算物流费用
     */
    public function getDeliveryFreight($area_id = 0, $logi_id = 0, $weight = 0)
    {

        if ($logi_id && $logi_id > 0) {
            $dlyCorpObj = $this->app->model('dly_corp');
            $corp       = $dlyCorpObj->dump($logi_id); //物流公司信息
        }
        if ($corp['setting'] == '1') {
            $firstunit      = $corp['firstunit'];
            $continueunit   = $corp['continueunit'];
            $firstprice     = $corp['firstprice'];
            $continueprice  = $corp['continueprice'];
            $dt_expressions = $corp['dt_expressions'];
        } else {
            //物流预算费用计算
            $regionIds = [];
            if ($area_id && $area_id > 0) {
                $regionObj = kernel::single('eccommon_regions');
                $region    = $regionObj->getOneById($area_id);
                $regionIds = explode(',', $region['region_path']);
                foreach ($regionIds as $key => $val) {
                    if ($regionIds[$key] == '' || empty($regionIds[$key])) {
                        unset($regionIds[$key]);
                    }
                }
            }
            $regionIds = implode('","', $regionIds);
            #物流公式设置明细表
            $sql = 'SELECT firstunit,continueunit,firstprice,continueprice,dt_expressions,dt_useexp FROM sdb_ome_dly_corp_items WHERE corp_id="' . $logi_id . '" AND region_id in ("' . $regionIds . '") ORDER BY region_id DESC';

            $corp_items     = $this->db->selectrow($sql);
            $firstunit      = $corp_items['firstunit'];
            $continueunit   = $corp_items['continueunit'];
            $firstprice     = $corp_items['firstprice'];
            $continueprice  = $corp_items['continueprice'];
            $dt_expressions = $corp_items['dt_expressions'];
        }

        if ($dt_expressions && $dt_expressions != '') {

            $price = utils::cal_fee($dt_expressions, $weight, 0, $firstprice, $continueprice); //TODO 生成快递费用
        } else {
            if ($continueunit > 0 && bccomp($weight, $firstunit, 3) == 1) {
                $continue_price = (($weight - $firstunit) / $continueunit) * $continueprice;
            } else {
                $continue_price = 0;
            }
            $price = $firstprice + $continue_price;
        }
        return $price;
    }

    /**
     * 根据物流单号获取商品和重量信息
     */
    public function getWeightbydelivery_id($logi_no)
    {
        $orderObj              = $this->app->model('orders');
        $dlyObj                = $this->app->model('delivery');
        $basicMaterialExtObj   = app::get('material')->model('basic_material_ext');
        $salesMaterialExtObj   = app::get('material')->model('sales_material_ext');
        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $dlyBillObj            = $this->app->model('delivery_bill');
        $dlyBill               = $dlyBillObj->dump(array('logi_no|nequal' => $logi_no), 'delivery_id');
        $dlyfather             = $dlyObj->dump(array('logi_no|nequal' => $logi_no), 'delivery_id');
        if ($dlyBill) {
            $delivery_id = $dlyBill['delivery_id'];
        } elseif ($dlyfather) {
            $delivery_id = $dlyfather['delivery_id'];

        }
        $dly = $dlyObj->dump($delivery_id, '*', array('delivery_items' => array('*'), 'delivery_order' => array('*')));

        //[拆单]根据发货单中货品详细读取重量
        $orderSplitLib = kernel::single('ome_order_split');
        $split_seting  = $orderSplitLib->get_delivery_seting();

        if ($split_seting && $dly['is_bind'] == 'false') {
            $orderItemObj  = app::get('ome')->model('order_items');
            $objectsObj    = app::get('ome')->model('order_objects');
            $dlyItemDetail = app::get('ome')->model('delivery_items_detail');

            $product_weight = array();
            $item_list      = $item_ids      = array();

            $temp_data = $dlyItemDetail->getList('*', array('delivery_id' => $delivery_id));
            foreach ($temp_data as $key => $val) {
                $item_id             = $val['order_item_id'];
                $item_list[$item_id] = array(
                    'item_id'    => $item_id,
                    'obj_id'     => $val['order_obj_id'],
                    'item_type'  => $val['item_type'],
                    'product_id' => $val['product_id'],
                    'bn'         => $val['bn'],
                    'number'     => $val['number'],
                );

                $item_ids[] = $item_id;
            }

            #获取本次发货单关联的订单明细
            $obj_list = array();
            $flag     = true; //重量累加标记

            $filter    = array('item_id' => $item_ids, '`delete`' => 'false');
            $item_data = $orderItemObj->getList('item_id, obj_id, product_id, bn, item_type, nums, name', $filter);
            foreach ($item_data as $key => $val) {
                $item_type  = $val['item_type'];
                $item_id    = $val['item_id'];
                $obj_id     = $val['obj_id'];
                $product_id = $val['product_id'];
                $bn         = $val['bn'];

                $val['send_num'] = $item_list[$item_id]['number']; //发货数量

                if ($item_type == 'pkg') {
                    $obj_list[$obj_id]['items'][$item_id] = $val;

                    //[捆绑商品]货号bn
                    if (empty($obj_list[$obj_id]['bn'])) {
                        $obj_item                = $objectsObj->getList('obj_id, goods_id,bn', array('obj_id' => $obj_id), 0, 1);
                        $obj_list[$obj_id]['bn'] = $obj_item[0]['bn'];

                        //单个[捆绑商品]重量
                        $pkg_goods                       = $salesMaterialExtObj->dump(array('sm_id' => $obj_item[0]['goods_id']), 'sm_id, weight');
                        $obj_list[$obj_id]['net_weight'] = floatval($pkg_goods['weight']);

                        //[捆绑商品]发货数量
                        $pkg_product                   = $salesBasicMaterialObj->dump(array('sm_id' => $pkg_goods['sm_id'], 'bm_id' => $product_id), 'number');
                        $obj_list[$obj_id]['send_num'] = intval($val['send_num'] / $pkg_product['number']);

                        $obj_list[$obj_id]['weight'] = 0;
                        if ($obj_list[$obj_id]['net_weight'] > 0) {
                            $obj_list[$obj_id]['weight'] = ($obj_list[$obj_id]['net_weight'] * $obj_list[$obj_id]['send_num']);
                        }
                    }

                    //items_list
                    $products                                   = $basicMaterialExtObj->dump(array('bm_id' => $product_id), 'weight');
                    $product_weight[$obj_id]['items'][$item_id] = array(
                        'weight'       => $products['weight'],
                        'number'       => $val['send_num'],
                        'total'        => ($products['weight'] * $val['send_num']),
                        'bn'           => $bn,
                        'product_name' => $val['name'],
                    );
                } else {
                    //普通商品直接计算重量
                    $weight   = 0;
                    $products = $basicMaterialExtObj->dump(array('bm_id' => $product_id), 'weight');
                    if ($products['weight'] > 0) {
                        $weight = ($products['weight'] * $val['send_num']);
                    }

                    //items_list
                    $product_weight[$obj_id]['obj_type']        = $item_type;
                    $product_weight[$obj_id]['weight']          = $weight;
                    $product_weight[$obj_id]['items'][$item_id] = array(
                        'weight'       => $products['weight'],
                        'number'       => $val['send_num'],
                        'total'        => ($products['weight'] * $val['send_num']),
                        'bn'           => $bn,
                        'product_name' => $val['name'],
                    );
                }
            }

            #捆绑商品无重量的重新计算
            if (!empty($obj_list)) {
                foreach ($obj_list as $obj_id => $obj_item) {
                    $weight = 0;
                    if ($obj_item['weight'] > 0 && $flag == true) {
                        $weight += $obj_item['weight'];
                    } else {
                        foreach ($product_weight[$obj_id] as $item_id => $item) {
                            if ($item['total'] == 0) {
                                $weight = 0;
                                break;
                            }
                            $weight += $item['total'];
                        }
                    }

                    $product_weight[$obj_id]['obj_type'] = 'pkg';
                    $product_weight[$obj_id]['weight']   = $weight;
                }
            }

            sort($product_weight);
            return $product_weight;
        } elseif ($dly) {
            $delivery_order = $dly['delivery_order'];
            $product_weight = array();
            foreach ($delivery_order as $items) {
                $order = $orderObj->dump($items['order_id'], "order_id", array("order_objects" => array("*", array("order_items" => array('product_id,nums,bn,name,`delete`')))));
                foreach ($order['order_objects'] as $k => $v) {
                    $bn                = $v['bn'];
                    $item_weight_total = 0;
                    $items_list        = array();
                    foreach ($v['order_items'] as $k1 => $v1) {
                        if ($v1['delete'] == 'true') {
                            continue;
                        }

                        $products     = $basicMaterialExtObj->dump(array('bm_id' => $v1['product_id']), 'weight');
                        $items_list[] = array(
                            'weight'       => $products['weight'],
                            'number'       => $v1['quantity'],
                            'total'        => $products['weight'] * $v1['quantity'],
                            'bn'           => $v1['bn'],
                            'product_name' => $v1['name'],

                        );
                        $item_weight_total += $products['weight'] * $v1['quantity'];
                    }

                    if (empty($items_list)) {
                        continue;
                    }

                    foreach ($items_list as $list) {
                        if ($list['total'] == 0) {
                            $item_weight_total = 0;
                            break;
                        }
                    }
                    if ($v['obj_type'] == 'pkg') {

                        $pkg    = $salesMaterialExtObj->dump(array('sm_id' => $v['goods_id']), 'weight');
                        $weight = $pkg['weight'] * $v['quantity'];
                        if ($weight == 0) {
                            $weight = $item_weight_total;
                        }

                    } else {
                        $weight = $item_weight_total;
                    }
                    $product_weight[$k]['items']    = $items_list;
                    $product_weight[$k]['weight']   = $weight;
                    $product_weight[$k]['obj_type'] = $v['obj_type'];
                }

            }
            sort($product_weight);
            return $product_weight;
        }
    }

    public function modifier_is_cod($row)
    {
        if ($row == 'true') {
            return "<div style='width:48px;padding:2px;height:16px;background-color:green;float:left;'><span style='color:#eeeeee;'>货到付款</span></div>";
        } else {
            return '款到发货';
        }
    }

    public function modifier_org_id($org_id,$list,$row){
        static $orgList;

        if (isset($orgList)) {
            return $orgList[$org_id];
        }

        $orgIds  = array_unique(array_column($list, 'org_id'));
        $orgList = app::get('ome')->model('operation_organization')->getList('org_id,name', ['org_id'=>$orgIds]);
        $orgList = array_column($orgList, 'name', 'org_id');

        return $orgList[$org_id];
    }

    /**
     * 获取订单备注
     */
    public function getOrderMarktextByDeliveryId($dly_ids = null)
    {
        if ($dly_ids) {
            $sql = "SELECT o.mark_text FROM sdb_ome_delivery_order do
                                    JOIN sdb_ome_orders o
                                        ON do.order_id=o.order_id
                                    WHERE do.delivery_id IN ($dly_ids)
                                        GROUP BY do.order_id ";
            $rows = $this->db->select($sql);

            $memo = array();
            if ($rows) {
                foreach ($rows as $v) {
                    $memo[] = unserialize($v['mark_text']);
                }

            }
            return serialize($memo);
        }
    }

    /**
     * 获取可合并发货单
     */
    public function fetchCombineDelivery($order_id)
    {
        $combine_member_id = app::get('ome')->getConf('ome.combine.member_id');
        $combine_shop_id   = app::get('ome')->getConf('ome.combine.shop_id');
        $combine_member_id = !isset($combine_member_id) ? 1 : $combine_member_id;
        $combine_shop_id   = !isset($combine_shop_id) ? 1 : $combine_shop_id;
        $memberidconf      = intval(app::get('ome')->getConf('ome.combine.memberidconf'));
        $memberidconf      = $memberidconf == '1' ? '1' : '0';
        $orders            = $this->app->model('orders')->getlist('order_id, shop_type,member_id,shop_id,ship_name,ship_mobile,ship_area,ship_addr,is_cod', array('order_id' => $order_id), 0, 1);

        $orders = $orders[0];

        $filter = array('process' => 'false', 'status' => array('ready', 'progress'), 'parent_id' => '0', 'is_cod' => $orders['is_cod']);
        if ($orders['shop_type'] == 'shopex_b2b') {

            if (empty($orders['member_id'])) {
                return false;
            } else {
                $filter['member_id'] = $orders['member_id'];

            }
            $filter['shop_id'] = $orders['shop_id'];
        } else if ($orders['shop_type'] == 'dangdang' && $orders['is_cod'] == 'true') {
            return false;
        } else if ($orders['shop_type'] == 'amazon' && $orders['self_delivery'] == 'false') {
            return false;} else if ($orders['shop_type'] == 'taobao' && $orders['order_source'] == 'tbdx') {
            return false;
        } else {
            //直销单
            if ($combine_member_id) {
                if (empty($orders['member_id'])) {
                    if ($memberidconf == '0') {
                        return false;
                    }

                } else {
                    $filter['member_id'] = $orders['member_id'];
                }
            }
            if ($combine_shop_id) {
                $filter['shop_id'] = $orders['shop_id'];
            }
        }

        $filter = array_merge($filter, kernel::single('omeauto_auto_combine')->_getAddrFilter($orders));
        $filter['no_encrypt'] = true;

        $delivery = $this->getlist('delivery_bn', $filter);

        $combine_delivery = array();
        foreach ((array) $delivery as $deli) {
            $combine_delivery[] = $deli['delivery_bn'];
        }

        return $combine_delivery;

    }
    /**
     * 找印时获取前端名称
     *
     */
    public function getPrintFrontProductName($ids)
    {
        $ordersObj         = $this->app->model('orders');
        $printProductNames = array();
        $sql               = 'SELECT distinct oi.order_id,oi.name,oi.bn,oi.addon,bp.store_position
                    FROM sdb_ome_delivery_order AS d2o
                LEFT JOIN sdb_ome_order_items AS oi
                    ON d2o.order_id = oi.order_id
                LEFT JOIN (
                    SELECT bpp.*
                        FROM (
                            SELECT pos_id,product_id
                            FROM sdb_ome_branch_product_pos
                            ORDER BY create_time DESC
                        )bpp
                    GROUP BY bpp.product_id
                 )bb
                    ON bb.product_id = oi.product_id
                 LEFT JOIN sdb_ome_branch_pos bp
                    ON bp.pos_id = bb.pos_id
                WHERE d2o.delivery_id IN(' . implode(',', $ids) . ') ORDER BY d2o.order_id';
        $rows = $this->db->select($sql);
        foreach ($rows as $row) {
            $orders = $ordersObj->dump($row['order_id'], 'shop_id');

            $bncode    = md5($orders['shop_id'] . trim($row['bn']));
            $row['bn'] = $bncode;

            if (isset($printProductNames[$row['bn']])) {
                continue;
            }

            $row['addon'] = ome_order_func::format_order_items_addon($row['addon']);

            $printProductNames[$row['bn']] = $row;
        }

        return $printProductNames;
    }
    #逐单发货时，根据物流单号，获取货号、货品名称
    public function getProcutInfo($logi_no = null)
    {
        $sql = 'select
                    items.bn,items.product_id,items.product_name,items.number,delivery.delivery_id
                from sdb_ome_delivery as delivery
                left join sdb_ome_delivery_items items on items.delivery_id=delivery.delivery_id
                where delivery.logi_no=' . "'$logi_no'";
        $rows = $this->db->select($sql);
        return $rows;
    }

    /**
     * 获取打印前端商品名
     *
     * @param Array $deliverys 发货单集合
     * @return Array
     * @author
     **/
    public function getPrintOrderName($deliverys)
    {
        $data = array();

        $order_ids = array();
        foreach ((array) $deliverys as $delivery) {
            foreach ($delivery['delivery_order'] as $delivery_order) {
                $order_ids[] = $delivery_order['order_id'];
            }
        }

        $orderItemModel = app::get('ome')->model('order_items');
        $orderItemList  = $orderItemModel->getList('order_id,name,bn,addon', array('order_id' => $order_ids, 'delete' => 'false'));

        $re_orderItemList = array();
        foreach ((array) $orderItemList as $order_item) {
            $order_item['addon']                                          = ome_order_func::format_order_items_addon($order_item['addon']);
            $re_orderItemList[$order_item['order_id']][$order_item['bn']] = $order_item;
        }
        unset($orderItemList);

        foreach ((array) $deliverys as $delivery) {
            $arr = array();
            foreach ($delivery['delivery_order'] as $delivery_order) {
                //$arr = array_merge((array) $arr,(array) $re_orderItemList[$delivery_order['order_id']]);
                $arr = $arr + (array) $re_orderItemList[$delivery_order['order_id']];
            }

            $data[$delivery['delivery_id']] = $arr;
        }
        unset($re_orderItemList);

        return $data;
    }

    /**
     * 获取打印货品位
     *
     * @param Array $deliverys 发货单集合
     * @return void
     * @author
     **/
    public function getPrintProductPos($deliverys)
    {
        $data = array();

        $product_ids = array();
        foreach ($deliverys as $delivery) {
            foreach ($delivery['delivery_items'] as $delivery_item) {
                $product_ids[] = $delivery_item['product_id'];

                $bpro_key                           = $delivery['branch_id'] . $delivery_item['product_id'];
                $data[$delivery_item['product_id']] = &$bpro[$bpro_key];
            }
        }

        // 货品货位有关系
        $bppModel = app::get('ome')->model('branch_product_pos');
        $bppList  = $bppModel->getList('product_id,pos_id,branch_id', array('product_id' => $product_ids));

        // 如果货位存在
        if ($bppList) {
            // 货位信息
            $pos_ids = array();
            foreach ($bppList as $key => $value) {
                $pos_ids[] = $value['pos_id'];
            }

            $posModel = app::get('ome')->model('branch_pos');
            $posList  = $posModel->getList('pos_id,branch_id,store_position', array('pos_id' => $pos_ids));

            foreach ($posList as $key => $value) {
                $bpos_key = $value['branch_id'] . $value['pos_id'];

                $bpos[$bpos_key] = $value['store_position'];
            }
            unset($posList);

            foreach ($bppList as $key => $value) {
                $bpro_key        = $value['branch_id'] . $value['product_id'];
                $bpos_key        = $value['branch_id'] . $value['pos_id'];
                $bpro[$bpro_key] = $bpos[$bpos_key];
            }
            unset($bppList);
        }

        return $data;
    }

    /**
     * 获取发货商品序列号
     * @param
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function getProductserial($dly_id)
    {
        $order_objectsObj  = app::get('ome')->model('order_objects');
        $order_itemsObj    = app::get('ome')->model('order_items');
        $product_serialObj = app::get('ome')->model('product_serial');
        $deliveryObj       = $this->app->model('delivery');
        $orderIds          = $deliveryObj->getOrderIdByDeliveryId($dly_id);

        $order_objects  = $order_objectsObj->getlist('oid,order_id,obj_id', array('order_id' => $orderIds, 'oid|than' => '0'));
        $product        = array();
        $product_serial = $product_serialObj->getSerialByproduct_id($dly_id);
        if ($product_serial) {
            foreach ($order_objects as $objects) {
                $obj_id      = $objects['obj_id'];
                $order_id    = $objects['order_id'];
                $oid         = $objects['oid'];
                $order_items = $order_itemsObj->getlist('product_id,nums', array('obj_id' => $obj_id, 'order_id' => $order_id, 'delete' => 'false'));
                $serial_list = array();
                foreach ($order_items as $items) {
                    $nums       = $items['nums'];
                    $product_id = $items['product_id'];
                    if ($product_serial[$product_id]) {
                        $serial = array_slice($product_serial[$product_id], 0, $nums); //取数组
                        if ($serial) {
                            $serial_list[] = implode(',', $serial);
                            array_splice($product_serial[$product_id], 0, $nums); //删除
                        }
                    }}
                if ($serial_list) {
                    $product[] = $oid . ':' . implode('|', $serial_list);
                }

            }
        }

        if ($product) {
            $product = "identCode=" . implode('|', $product);
        }

        return $product;

    }

    /**
     * 发货单列表项扩展字段
     */
    public function extra_cols()
    {
        return array(
            'column_delivery_label' => array('label' => '发货单标记', 'width' => '220', 'func_suffix' => 'delivery_label'),
            'column_custom_mark' => array('label' => '买家留言', 'width' => '180', 'func_suffix' => 'custom_mark'),
            'column_mark_text'   => array('label' => '客服备注', 'width' => '180', 'func_suffix' => 'mark_text'),
            'column_tax_no'      => array('label' => '发票号', 'width' => '180', 'func_suffix' => 'tax_no'),
            'column_ident'       => array('label' => '批次号', 'width' => '160', 'func_suffix' => 'ident', 'order_field' => 'idx_split'),
        );
    }

    /**
     * 买家备注扩展字段格式化
     */
    public function extra_custom_mark($rows)
    {
        return kernel::single('ome_extracolumn_delivery_custommark')->process($rows);
    }

    /**
     * 客服备注扩展字段格式化
     */
    public function extra_mark_text($rows)
    {
        return kernel::single('ome_extracolumn_delivery_marktext')->process($rows);
    }

    /**
     * 发票号扩展字段格式化
     */
    public function extra_tax_no($rows)
    {
        return kernel::single('ome_extracolumn_delivery_taxno')->process($rows);
    }

    /**
     * 批次号扩展字段格式化
     */
    public function extra_ident($rows)
    {
        return kernel::single('ome_extracolumn_delivery_ident')->process($rows);
    }

    /**
     * 渲染发货单列表的标签列，包含加急发货标签展示。
     *
     * @param array $rows
     * @return array
     */
    public function extra_delivery_label($rows)
    {
        $deliveryIds = array_column((array)$rows, 'delivery_id');
        if (!$deliveryIds) {
            return array();
        }
        $labelRows = app::get('ome')->model('bill_label')->getBIllLabelList($deliveryIds, 'delivery');
        $result = array();
        foreach ((array)$labelRows as $label) {
            $result[$label['bill_id']] .= sprintf(
                "<span class='tag-label' title='%s' style='background-color:%s;color:#000000;'>%s</span>",
                $label['label_name'],
                $label['label_color'],
                $label['label_name']
            );
        }
        return $result;
    }

    public function getDlyId($logi_nos = false)
    {
        $all_logi_no = array();
        foreach ($logi_nos as $logi_no) {
            $all_logi_no[] = "'" . $logi_no . "'";
        }
        $str_logi_no = implode(',', $all_logi_no);
        $sql         = "select delivery_id from sdb_ome_delivery where logi_no in ( " . $str_logi_no . " )
                union
                select delivery_id from sdb_ome_delivery_bill where logi_no in(" . $str_logi_no . " )";
        $rows = $this->db->select($sql);
        $data = array();
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $data[] = $row['delivery_id'];
            }
        }
        return $data;
    }

    #订阅华强宝物流信息
    public function get_hqepay_logistics($delivery_id)
    {
        #检测是否开启华强宝物流
        $is_hqepay_on = app::get('ome')->getConf('ome.delivery.hqepay');
        if ($is_hqepay_on == 'false') {
            return true;
        }
        #订阅物流信息
        kernel::single('ome_service_delivery')->get_hqepay_logistics($delivery_id);
        return true;
    }

    public function getFinishDeliveryByorderId($order_id)
    {
        $SQL           = "SELECT d.* FROM sdb_ome_delivery as d LEFT JOIN sdb_ome_delivery_order as od ON od.delivery_id=d.delivery_id LEFT JOIN sdb_ome_orders as o ON o.order_id=od.order_id WHERE o.order_id='" . $order_id . "' AND (d.parent_id=0 OR d.is_bind='true') AND d.status='succ' AND d.process='true'";
        $delivery_list = $this->db->select($SQL);
        if ($delivery_list) {
            return $delivery_list;
        } else {
            return array();
        }
    }

    /**
     * 获取已发货发货单
     *
     * @param Int $delivery_id 主单发货单ID
     * @return void
     * @author
     **/
    public function getFinishDelivery($delivery_id)
    {
        static $deliverys;

        if (isset($deliverys[$delivery_id])) {
            return $deliverys[$delivery_id];
        }

        $deliveryinfo = $this->db->selectrow('SELECT * FROM ' . $this->table_name(true) . ' WHERE delivery_id=' . $delivery_id . ' AND process="true" AND parent_id="0"');

        if (!$deliveryinfo) {
            return array();
        }

        // 发货单对应的订单
        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliveryOrderList  = $deliveryOrderModel->getList('order_id', array('delivery_id' => $delivery_id));

        $order_ids = array();
        foreach ($deliveryOrderList as $key => $value) {
            $order_ids[] = $value['order_id'];
        }

        $orderModel = app::get('ome')->model('orders');
        $orderList  = $orderModel->getList('*', array('order_id' => $order_ids));

        $deliveryinfo['orders']         = $orderList;
        $deliveryinfo['delivery_order'] = $deliveryOrderList;

        $deliverys[$delivery_id] = $deliveryinfo;

        return $deliverys[$delivery_id];
    }

    #根据订单,检测已生成发货单数量
    public function checkDeliverNumsById($order_ids)
    {
        $sql           = " SELECT  count(dord.delivery_id)  nums  FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id) WHERE dord.order_id in( " . implode(',', $order_ids) . " ) AND (d.parent_id=0 OR d.is_bind='true')  AND d.disabled='false' AND d.status NOT IN('cancel','back','return_back')"; #过滤已撤销发货单
        $delivery_nums = $this->db->selectRow($sql);
        if (empty($delivery_nums)) {
            return 0;
        }

        return $delivery_nums['nums'];
    }

    /**
     * 获取订单下所有发货单
     * @param $order_id
     * @return array
     * @date 2024-04-11 10:26 上午
     */
    public function getAllDeliversOrderId($order_id)
    {
        return $this->db->select("SELECT d.delivery_id,d.delivery_bn,d.logi_id,d.logi_no,d.logi_name FROM sdb_ome_delivery_order AS dord
                                            LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                                            WHERE dord.order_id='{$order_id}' AND d.disabled='false' AND d.status IN ('succ')");
    }

    /**
     * 发送发货单取消成功通知
     * @param array $deliveryInfo 发货单信息
     * @param string $memo 备注
     */
    private function sendDeliveryCancelSuccessNotify($deliveryInfo, $memo = '')
    {
        try {
            // 获取仓库信息
            $branchObj = app::get('ome')->model('branch');
            $branchInfo = $branchObj->dump(['branch_id' => $deliveryInfo['branch_id'], 'check_permission' => 'false'], 'name');
            $branchName = $branchInfo ? $branchInfo['name'] : '未知仓库';

            // 发送通知
            kernel::single('monitor_event_notify')->addNotify('delivery_cancel_success', [
                'delivery_bn' => $deliveryInfo['delivery_bn'],
                'branch_name' => $branchName,
                'cancel_time' => date('Y-m-d H:i:s'),
                'memo' => $memo ?: '无',
            ]);
        } catch (Exception $e) {
            // 静默处理异常，不影响主流程
        }
    }
}
