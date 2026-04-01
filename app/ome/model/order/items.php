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

class ome_mdl_order_items extends dbeav_model
{
    public $_obj_alias = array(
        'goods'       => '商品',
        'pkg'         => '捆绑商品',
        'gift'        => '赠品',
        'giftpackage' => '礼包',
        'lkb'         => '福袋',
        'pko'         => '多选一',
    );
    
    public function getItemDetail($bn, $order_id)
    {
        $aGoods = $this->db->select('SELECT i.*,nums-sendnum AS send,sendnum AS resend,p.store FROM sdb_ome_order_items i
            LEFT JOIN sdb_material_basic_material_stock p ON i.product_id = p.bm_id
            WHERE order_id = \'' . $order_id . '\' AND i.bn = \'' . $bn . '\'');
        return $aGoods[0];
    }

    public function getOrderIdByPbn($product_bn)
    {
        $sql   = 'SELECT count(1) as _c FROM sdb_ome_order_items WHERE bn like \'' . addslashes($product_bn) . '%\'';
        $count = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT order_id FROM sdb_ome_order_items WHERE bn like \'' . addslashes($product_bn) . '%\'';
            $total  = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }

        $sql  = 'SELECT order_id FROM sdb_ome_order_items WHERE bn like \'' . addslashes($product_bn) . '%\'';
        $rows = $this->db->select($sql);
        return $rows;
    }

    public function getOrderIdByPbarcode($product_barcode)
    {
        $sql = 'SELECT count(1) as _c FROM sdb_ome_order_items as I LEFT JOIN ' .
        'sdb_material_codebase as c ON I.product_id=c.bm_id WHERE c.code like \'' . addslashes($product_barcode) . '%\'';
        $count = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT order_id FROM sdb_ome_order_items as I LEFT JOIN ' .
            'sdb_material_codebase as c ON I.product_id=c.bm_id WHERE c.code like \'' . addslashes($product_barcode) . '%\'';
            $total = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }

        $sql = 'SELECT order_id FROM sdb_ome_order_items as I LEFT JOIN ' .
        'sdb_material_codebase as c ON I.product_id=c.bm_id WHERE c.code like \'' . addslashes($product_barcode) . '%\'';
        $rows = $this->db->select($sql);
        return $rows;
    }
    /**
     * 通过product_id获得符合条件的冻结库存值
     * @param unknown_type $product_id
     */
    public function getStoreByProductId($product_id, $offset = '0', $limit = '10')
    {
        //brush特殊订单(刷单订单不预占冻结库存)
        $sql = "SELECT o.order_bn,o.pay_status,o.ship_status,o.createtime,o.order_limit_time,o.paytime,o.shop_id,oi.sendnum,oi.nums,o.is_assign_store 
                FROM sdb_ome_order_items as oi,sdb_ome_orders o
                where o.order_id = oi.order_id
                and o.status='active'
                and oi.product_id = $product_id
                and oi.`delete`='false'
                and o.ship_status in ('0','2','3')
                and oi.sendnum != oi.nums 
                AND o.order_type <> 'brush' 
                LIMIT {$offset},{$limit}
                ";
        $rows = $this->db->select($sql);
        return $rows;
    }
    /**
     * 获取符合条件的冻结库存的 总数
     */
    public function count_order_id($product_id)
    {
        //brush特殊订单
        $sql = "SELECT count(*) AS count
                FROM sdb_ome_order_items as oi,sdb_ome_orders o
                where o.order_id = oi.order_id
                and o.status='active'
                and oi.product_id = $product_id
                and oi.`delete`='false'
                and o.ship_status in ('0','2')
                AND o.order_type <> 'brush' 
                and oi.sendnum != oi.nums";
        $rows = $this->db->selectrow($sql);
        return $rows['count'];
    }

    public function getFailOrderByBn($bn = array())
    {
        //brush特殊订单
        $sql = 'SELECT I.order_id FROM sdb_ome_order_items as I LEFT JOIN ' .
        'sdb_ome_orders as O ON I.order_id=O.order_id WHERE O.is_fail=\'true\' and O.edit_status=\'true\' and O.archive=\'1\' and O.status=\'active\' AND O.order_type <> \'brush\' and I.bn in (\'' . implode('\',\'', $bn) . '\') GROUP BY order_id';
        $rows = $this->db->select($sql);
        return $rows;
    }

    /**
     *
     */
    public function getOrderIdByPkgbn($product_bn)
    {
        $sql   = 'SELECT count(1) as _c FROM sdb_ome_order_objects WHERE bn like \'' . addslashes($product_bn) . '%\'';
        $count = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT order_id FROM sdb_ome_order_objects WHERE bn like \'' . addslashes($product_bn) . '%\'';
            $total  = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }

        $sql  = 'SELECT order_id FROM sdb_ome_order_objects WHERE bn like \'' . addslashes($product_bn) . '%\'';
        $rows = $this->db->select($sql);
        return $rows;
    }

    /**
     * 按销售物料(bn)查询相关订单，关联主表并应用其他筛选条件，避免数据量过大导致内存溢出
     */
    public function getOrderIdByPkgbnEq($filter)
    {
        $orderObj     = app::get('ome')->model('orders');
        $searchfilter = $filter;
        $product_bn   = $filter['product_bn'];

        $where = 1;
        if (is_array($product_bn)) {
            $where = 'in (\'' . implode('\',\'', $product_bn) . '\')';
        } else {
            $where = '= \'' . $product_bn . '\'';
        }
        unset($searchfilter['product_bn'], $searchfilter['sales_material_bn']);
        $order_filter = $orderObj->_filter($searchfilter);
        $order_filter = str_replace('`sdb_ome_orders`', 'o', $order_filter);
        $order_filter = str_replace('order_id', 'o.order_id', $order_filter);

        $sql   = 'SELECT count(1) as _c FROM sdb_ome_order_objects as obj LEFT JOIN sdb_ome_orders as o ON obj.order_id=o.order_id WHERE obj.bn ' . $where . ' AND' . $order_filter;
        $count = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT obj.order_id FROM sdb_ome_order_objects as obj LEFT JOIN sdb_ome_orders as o ON obj.order_id=o.order_id WHERE obj.bn ' . $where . ' AND ' . $order_filter;
            $total  = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }

        $sql  = 'SELECT obj.order_id FROM sdb_ome_order_objects as obj LEFT JOIN sdb_ome_orders as o ON obj.order_id=o.order_id WHERE obj.bn ' . $where . ' AND ' . $order_filter;
        $rows = $this->db->select($sql);

        return $rows;
    }

    /**
     * 查询货号相关订单
     * @param array filter
     * @return array
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function getOrderIdByFilterbn($filter)
    {
        $orderObj     = app::get('ome')->model('orders');
        $searchfilter = $filter;
        $product_bn   = $searchfilter['product_bn'];
        unset($searchfilter['product_bn']);
        $order_filter = $orderObj->_filter($searchfilter);
        $order_filter = str_replace('`sdb_ome_orders`', 'o', $order_filter);
        $sql          = 'SELECT count(1) as _c FROM sdb_ome_order_items as i LEFT JOIN sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.bn like \'' . addslashes($product_bn) . '%\' AND' . $order_filter;
        $count        = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT i.order_id FROM sdb_ome_order_items as i LEFT JOIN sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.bn like \'' . addslashes($product_bn) . '%\' AND ' . $order_filter;
            $total  = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }

        $sql  = 'SELECT i.order_id FROM sdb_ome_order_items as i LEFT JOIN  sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.bn like \'' . addslashes($product_bn) . '%\' AND ' . $order_filter;
        $rows = $this->db->select($sql);
        return $rows;
    }

    public function getOrderIdByFilterbnEq($filter)
    {
        $orderObj = app::get('ome')->model('orders');

        $searchfilter = $filter;
        $product_bn   = $filter['product_bn'];

        $where = 1;
        if (is_array($product_bn)) {
            $where = 'in (\'' . implode('\',\'', $product_bn) . '\')';
        } else {
            $where = '= \'' . $product_bn . '\'';
        }
        unset($searchfilter['product_bn']);
        $order_filter = $orderObj->_filter($searchfilter);
        $order_filter = str_replace('`sdb_ome_orders`', 'o', $order_filter);
        $order_filter = str_replace('order_id', 'o.order_id', $order_filter);
        $sql          = 'SELECT count(1) as _c FROM sdb_ome_order_items as i LEFT JOIN sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.bn ' . $where . ' AND' . $order_filter;
        $count        = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT i.order_id FROM sdb_ome_order_items as i LEFT JOIN sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.bn ' . $where . ' AND ' . $order_filter;
            $total  = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }

        $sql  = 'SELECT i.order_id FROM sdb_ome_order_items as i LEFT JOIN  sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.bn ' . $where . ' AND ' . $order_filter;
        $rows = $this->db->select($sql);

        return $rows;
    }

    public function updateSplitNum($itemId, $num, $op)
    {
        $updateSql = 'update sdb_ome_order_items set split_num = ';
        if ($op == '+') {
            $updateSql .= "(split_num+{$num})";
            $filter = "split_num+{$num}<=nums";
        } elseif ($op == '-') {
            $updateSql .= "(split_num-{$num})";
            $filter = "split_num>={$num}";
        } else {
            return 0;
        }
        $updateSql .= 'where item_id = "' . $itemId . '" and `delete`="false" and ' . $filter;
        $this->db->exec($updateSql);
        return $this->db->affect_row();
    }

    /**
     * 判断订单是否已经拆分（已弃用，可以使用getProcessStatus方法）
     *
     * @return void
     * @author
     **/
    public function is_splited($order_id)
    {
        $row = $this->db->selectrow('SELECT item_id FROM sdb_ome_order_items WHERE order_id=' . $order_id . ' AND nums > split_num AND `delete`="false"');

        return $row ? false : true;
    }

    /**
     * 批量编辑替换订单明细
     * 
     * @param int $order_id
     * @param array $params
     * @param string $err_msg
     * @return bool
     */
    public function changeOrderItem($order_id, $params, &$err_msg=null)
    {
        $orderMdl = app::get('ome')->model('orders');
        $objectObj = app::get('ome')->model('order_objects');
        $itemObj = app::get('ome')->model('order_items');
        $smModel = app::get('material')->model('sales_material');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $salesBasicMaterialLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        $replace = $params['replace']; //删除
        $change = $params['change']; //新增
        $change_number = $params['change_number']; //新增
        $delAll = $params['delAll']; //全选删除
        
        //防止订单编辑与生成发货单并发导致错误  更新订单修改标识
        $orderMdl->update(array('is_modify'=>'true'), array('order_id'=>$order_id));
        
        //订单信息
        $order = $orderMdl->dump($order_id, "*", array("order_objects" => array("*", array("order_items" => array('*')))));
        $object = array_column($order['order_objects'], null, 'bn');
        
        //check
        if(empty($order)){
            $err_msg = sprintf('订单ID[%s]没有找到订单信息', $order_id);
            return false;
        }
        list($rs, $rsData) = kernel::single('material_basic_material_stock_freeze')->deleteOrderBranchFreeze([$order_id]);
        if(!$rs) {
            $err_msg = $order['order_bn'].'错误：'.$rsData['msg'];
            return false;
        }
        
        if (!in_array($order['shop_type'], ome_shop_type::shop_list()) && $order['createway'] == 'matrix') {
            $err_msg = sprintf('订单[%s]失败：SHOPEX体系内订单不能编辑', $order['order_bn']);
            return false;
        }
        
        //如果是全删除，重置replace
        if ($delAll == 'true'){
            $replace = array();
            foreach ($order['order_objects'] as $obj) {
                if ($obj['delete'] == 'false') {
                    $replace[] = $obj['bn'];
                }
            }
            
            if (empty($replace)) {
                $err_msg = sprintf('订单[%s]全选状态下无可替换商品', $order['order_bn']);
                return false;
            }
        }
        $updateOrder = array('order_id'=>$order_id);
        $needUpdateFreezeItem = [];
        if(count($change) < count($replace)) {
            for($i=count($change), $num = count($replace);$i<$num; $i++) {
                $objectInfo = $object[$replace[$i]];
                if(!$objectInfo) {
                    continue;
                }
                if($objectInfo['price'] > 0 || $objectInfo['sale_price'] > 0) {
                    $err_msg = sprintf('订单[%s]商品[%s]有金额不能被删除', $order['order_bn'], $replace[$i]);
                    return false;
                }
                if($objectInfo['split_num'] > 0) {
                    $err_msg = sprintf('订单[%s]商品[%s]已有发货单不能被删除', $order['order_bn'], $replace[$i]);
                    return false;
                }
                //删除订单明细
                $delete_order_items = array();
                foreach($objectInfo['order_items'] as $item)
                {
                    $delete_order_items[] = array(
                            'item_id' => $item['item_id'],
                            'bn' => $item['bn'],
                            'delete'  => 'true',
                    );
                    $item['operate_type'] = 'delete';
                    $item['goods_id'] = $objectInfo['goods_id'];
                    $needUpdateFreezeItem[] = $item;
                }
                
                $updateOrder['order_objects'][] = array(
                        'order_id' => $order_id,
                        'obj_id' => $objectInfo['obj_id'],
                        'bn' => $objectInfo['bn'],
                        'pay_status' => '5',
                        'delete' => 'true',
                        'order_items' => $delete_order_items,
                );
            }
        }
        //获取替换的销售物料信息
        $tempList = [];
        if($change) {
            $tempList = $smModel->getList('sm_id,sales_material_bn,sales_material_name,shop_id,sales_material_type', array('sales_material_bn'=>$change));
            if(empty($tempList)){
                $err_msg = sprintf('订单[%s]替换商品没有找到', $order['order_bn']);
                return false;
            }
        }
        
        $smList = array();
        foreach ($tempList as $key => $val)
        {
            $sm_id = $val['sm_id'];
            $sales_material_bn = $val['sales_material_bn'];
            
            //绑定的基础物料
            $bindInfo = $salesBasicMaterialLib->getBasicMBySalesMIds($sm_id);
            $bindInfo = $bindInfo[$sm_id];
            $bindInfo = $bindInfo ? array_column($bindInfo, null, 'bm_id') : null;
            
            if(empty($bindInfo)){
                $err_msg = sprintf('订单[%s]替换商品[%s]没有绑定基础物料', $order['order_bn'], $sales_material_bn);
                return false;
            }
            
            $val['basicMaterial'] = $bindInfo;
            $smList[$sales_material_bn] = $val;
        }
        
        //check检查
        foreach ($change as $key => $new_bn)
        {
            //验证货号是否在订单明细中存在
            if($object[$new_bn]){
                $err_msg = sprintf('订单[%s]替换商品[%s]已经在订单明细中存在', $order['order_bn'], $new_bn);
                return false;
            }
            
            //检查替换的销售物料是否存在
            if(empty($smList[$new_bn])){
                $err_msg = sprintf('订单[%s]替换商品[%s]不存在', $order['order_bn'], $new_bn);
                return false;
            }
            
            //检查被替换的商品是否存在
            $old_bn = $replace[$key];
            if($old_bn){
                if(empty($object[$old_bn])){
                    $err_msg = sprintf('订单[%s]被替换商品[%s]不在订单明细中', $order['order_bn'], $old_bn);
                    return false;
                }
                
                if($object[$old_bn]['delete'] == 'true') {
                    $err_msg = sprintf('订单[%s]被替换商品[%s]已经删除', $order['order_bn'], $old_bn);
                    return false;
                }

                foreach ($object[$old_bn]['order_items'] as $v) {
                    if($v['split_num'] > 0) {
                        $err_msg = sprintf('订单[%s]被替换商品[%s]已经生成发货单', $order['order_bn'], $old_bn);
                        return false;
                    }
                }
            }
        }
        
        //订单明细类型
        $obj_types = array(
                1 => 'goods',
                2 => 'pkg',
                3 => 'gift',
                4 => 'lkb',
                5 => 'pko',
                6 => 'giftpackage',
        );
        foreach ($change as $key => $new_bn)
        {
            //新增的商品的销售物料
            $salesMaterial = $smList[$new_bn];
            $sales_material_type = $salesMaterial['sales_material_type'];
            
            //新增的商品绑定的基础物料
            $salesBasicMaterial = $salesMaterial['basicMaterial'];
            
            //商品类型
            $obj_type = $obj_types[$sales_material_type] ? $obj_types[$sales_material_type] : 'goods';
            
            //[兼容]新增的商品(不包括:pkg、lkb)默认为gift赠品类型
            $add_obj_type = 'gift';
            if(in_array($obj_type, array('pkg', 'lkb'))){
                $add_obj_type = $obj_type;
            }
            
            //订单object层明细
            $new_order_object   = array(
                'order_id'          => $order_id,
                'obj_type'          => $add_obj_type,
                'obj_alias'         => $add_obj_type,
                'shop_goods_id'     => $add_obj_type == 'gift' ? '-1' : '0',
                'bn'                => $salesMaterial['sales_material_bn'],
                'name'              => $salesMaterial['sales_material_name'],
                'goods_id'          => $salesMaterial['sm_id'],
                'price'             => 0,
                'quantity'          => $change_number[$new_bn] > 0 ? (int)$change_number[$new_bn] : 1,
                'amount'            => 0,
                'pmt_price'         => 0,
                'sale_price'        => 0,
                'divide_order_fee'  => 0,
                'part_mjz_discount' => 0,
            );
            
            //订单item层明细
            $new_order_item = array();
            foreach ($salesBasicMaterial as $item)
            {
                $item_type = ($obj_type=='goods' ? 'product' : $obj_type);
                
                $new_order_item[] = array(
                    'order_id'          => $order_id,
                    'bn'                => $item['material_bn'],
                    'name'              => $item['material_name'],
                    'product_id'        => $item['bm_id'],
                    'price'             => 0,
                    'nums'              => intval($new_order_object['quantity'] * $item['number']),
                    'original_unit_num' => $item['number'],
                    'amount'            => 0,
                    'pmt_price'         => 0,
                    'sale_price'        => 0,
                    'item_type'         => $add_obj_type,
                    'divide_order_fee'  => 0,
                    'part_mjz_discount' => 0,
                );
            }
            
            //被替换的商品
            $old_bn = $replace[$key];
            $objectInfo = $object[$old_bn]; //原订单object明细
            if ($old_bn && $objectInfo){
                
                //[兼容]商品有金额只能是普通商品、捆绑商品类型
                if($objectInfo['sale_price']>0 && $obj_type == 'gift'){
                    $obj_type = 'goods';
                }
                
                //object
                $new_order_object = array(
                        'order_id'          => $order_id,
                        'obj_type'          => $obj_type,
                        'obj_alias'         => $obj_type,
                        'shop_goods_id'     => $obj_type == 'gift' ? '-1' : '0',
                        'bn'                => $salesMaterial['sales_material_bn'],
                        'name'              => $salesMaterial['sales_material_name'],
                        'goods_id'          => $salesMaterial['sm_id'],
                        'price'             => $objectInfo['price'],
                        'quantity'          => $objectInfo['quantity'],
                        'amount'            => $objectInfo['amount'],
                        'pmt_price'         => $objectInfo['pmt_price'],
                        'sale_price'        => $objectInfo['sale_price'],
                        'divide_order_fee'  => $objectInfo['divide_order_fee'],
                        'part_mjz_discount' => $objectInfo['part_mjz_discount'],
                        'settlement_amount' => $objectInfo['settlement_amount'],
                        'actually_amount' => $objectInfo['actually_amount'],
                        'platform_pay_amount' => $objectInfo['platform_pay_amount'],
                        'platform_amount' => $objectInfo['platform_cost_amount'],
                        'oid' => $objectInfo['oid'],
                        'main_oid' => $objectInfo['main_oid'],
                        'estimate_con_time' => $objectInfo['estimate_con_time'],
                        'author_id' => $objectInfo['author_id'],
                        'author_name' => $objectInfo['author_name'],
                        'store_code' => $objectInfo['store_code'],
                );
                $num = count($new_order_item);
                
                //items
                $price = $amount = $pmt_price = $sale_price = $divide_order_fee = $part_mjz_discount = 0;
                $order_item = array();
                for($i = 0; $i <= $num - 1; $i++)
                {
                    $item_type = ($obj_type=='goods' ? 'product' : $obj_type);
                    
                    if ($i == $num - 1) {
                        $order_item[$i] = array(
                                'price'             => $objectInfo['price'] - $price,
                                'amount'            => $objectInfo['amount'] - $amount,
                                'pmt_price'         => $objectInfo['pmt_price'] - $pmt_price,
                                'sale_price'        => $objectInfo['sale_price'] - $sale_price,
                                'divide_order_fee'  => $objectInfo['divide_order_fee'] - $divide_order_fee,
                                'part_mjz_discount' => $objectInfo['part_mjz_discount'] - $part_mjz_discount,
                                'quantity' => intval($objectInfo['quantity'] * $new_order_item[$i]['original_unit_num']),
                                'nums' => intval($objectInfo['quantity'] * $new_order_item[$i]['original_unit_num']),
                                'item_type' => $item_type,
                        );
                    } else {
                        $order_item[$i] = array(
                                'price'             => bcdiv($objectInfo['price'], $num, 2),
                                'amount'            => bcdiv($objectInfo['amount'], $num, 2),
                                'pmt_price'         => bcdiv($objectInfo['pmt_price'], $num, 2),
                                'sale_price'        => bcdiv($objectInfo['sale_price'], $num, 2),
                                'divide_order_fee'  => bcdiv($objectInfo['divide_order_fee'], $num, 2),
                                'part_mjz_discount' => bcdiv($objectInfo['part_mjz_discount'], $num, 2),
                                'quantity' => intval($objectInfo['quantity'] * $new_order_item[$i]['original_unit_num']),
                                'nums' => intval($objectInfo['quantity'] * $new_order_item[$i]['original_unit_num']),
                                'item_type' => $item_type,
                        );
                        
                        $price += $new_order_item[$i]['price'];
                        $amount += $new_order_item[$i]['amount'];
                        $pmt_price += $new_order_item[$i]['pmt_price'];
                        $sale_price += $new_order_item[$i]['sale_price'];
                        $divide_order_fee += $new_order_item[$i]['divide_order_fee'];
                        $part_mjz_discount += $new_order_item[$i]['part_mjz_discount'];
                    }
                    
                    $new_order_item[$i] = array_merge($new_order_item[$i], $order_item[$i]);
                }
                
                //删除被替换的订单明细
                $delete_order_items = array();
                foreach($objectInfo['order_items'] as $item)
                {
                    $delete_order_items[] = array(
                            'item_id' => $item['item_id'],
                            'bn' => $item['bn'],
                            'delete'  => 'true',
                    );
                    $item['operate_type'] = 'delete';
                    $item['goods_id'] = $objectInfo['goods_id'];
                    $needUpdateFreezeItem[] = $item;
                }
                
                $updateOrder['order_objects'][] = array(
                        'order_id' => $order_id,
                        'obj_id' => $objectInfo['obj_id'],
                        'bn' => $objectInfo['bn'],
                        'pay_status' => '5',
                        'delete' => 'true',
                        'order_items' => $delete_order_items,
                );
            }
            
            //新增商品,增加冻结
            foreach($new_order_item as $item)
            {
                $item['operate_type'] = 'add';
                $item['goods_id'] = $new_order_object['goods_id'];
                $needUpdateFreezeItem[] = $item;
            }
            
            $new_order_object['order_items'] = $new_order_item;
            $divideOrder = kernel::single('ome_order')->divide_objects_to_items(['order_objects'=>[$new_order_object]]);
            $updateOrder['order_objects'][]  = $divideOrder['order_objects'][0];
        }
        if($needUpdateFreezeItem) {
            uasort($needUpdateFreezeItem, [kernel::single('console_iostockorder'), 'cmp_productid']);
            $branchBatchList = [];
            foreach($needUpdateFreezeItem as $item) {
                if($item['operate_type'] == 'add') {

                    $freezeData = [];
                    $freezeData['bm_id'] = $item['product_id'];
                    $freezeData['sm_id'] = $item['goods_id'];
                    $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                    $freezeData['bill_type'] = 0;
                    $freezeData['obj_id'] = $order_id;
                    $freezeData['shop_id'] = $order['shop_id'];
                    $freezeData['branch_id'] = 0;
                    $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                    $freezeData['num'] = abs($item['nums']);
                    $freezeData['obj_bn'] = $order['order_bn'];
                    $branchBatchList['+'][] = $freezeData;
                } else {
                    //删除商品,减少预占
                    $branchBatchList['-'][] = [
                        'bm_id'     =>  $item['product_id'],
                        'sm_id'     =>  $item['goods_id'],
                        'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                        'bill_type' =>  0,
                        'obj_id'    =>  $order_id,
                        'branch_id' =>  '',
                        'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                        'num'       =>  abs($item['quantity']),
                    ];
                }
            }

            $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
            $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        }
        //check
        if(empty($updateOrder['order_objects'])){
            $err_msg = sprintf('订单[%s]没有需要更新object层记录', $order['order_bn']);
            return false;
        }
        
        //更新订单明细
        foreach ($updateOrder['order_objects'] as $objKey => $objVal)
        {
            $order_items = $objVal['order_items'];
            if(empty($order_items)){
                $err_msg = sprintf('订单[%s]没有需要更新item层记录', $order['order_bn']);
                return false;
            }
            unset($objVal['order_items']);
            
            if($objVal['delete'] == 'true'){
                //删除操作
                $obj_bn = $objVal['bn'];
                unset($objVal['bn'], $objVal['order_id']);
                
                $isSave = $objectObj->save($objVal);
                if(!$isSave){
                    $err_msg = sprintf('订单[%s]删除销售物料[%s]失败', $order['order_bn'], $obj_bn);
                    return false;
                }
                
                foreach ($order_items as $itemKey => $itemVal)
                {
                    $item_bn = $itemVal['bn'];
                    unset($itemVal['bn']);
                    
                    $isSave = $itemObj->save($itemVal);
                    if(!$isSave){
                        $err_msg = sprintf('订单[%s]删除基础物料[%s]失败', $order['order_bn'], $item_bn);
                        return false;
                    }
                }
            }else{
                //新增操作
                $isSave = $objectObj->insert($objVal);
                if(!$isSave){
                    $err_msg = sprintf('订单[%s]添加销售物料[%s]失败', $order['order_bn'], $objVal['bn']);
                    return false;
                }
                
                foreach ($order_items as $itemKey => $itemVal)
                {
                    $itemVal['obj_id']= $objVal['obj_id'];
                    
                    $isSave = $itemObj->insert($itemVal);
                    if(!$isSave){
                        $err_msg = sprintf('订单[%s]添加基础物料[%s]失败', $order['order_bn'], $itemVal['bn']);
                        return false;
                    }
                }
            }
        }
        
        //日志
        $log_id = $operLogObj->write_log('order_modify@ome', $order_id, '批量替换编辑订单成功。');
        
        //订单快照
        $orderMdl->write_log_detail($log_id, $order);
        
        //修改明细打标
        $boolExtendStatus = ome_order_bool_extendstatus::__GOODS_PRICE;
        app::get('ome')->model('order_extend')->updateBoolExtendStatus($order_id, $boolExtendStatus);
        
        return true;
    }
    
    public function conver($salesMaterial, $object, $order_id, $old_bn) {
        $salesMLib             = kernel::single('material_sales_material');
        $basicMStockLib       = kernel::single('material_basic_material_stock');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        $quantity   = $object['quantity'] ? $object['quantity'] : 1;
        $obj_amount = $object['amount'] ? $object['amount'] : bcmul($quantity, $object['price'], 3);
        $shop_id = $object['shop_id'] ? $object['shop_id'] : '_ALL_';
        $obj_sale_price = (isset($object['sale_price']) && is_numeric($object['sale_price']) && -1 != bccomp($object['sale_price'], 0, 3)) ? $object['sale_price'] : bcsub($obj_amount, $object['pmt_price'], 3);
        $obj_type       = 'goods';
        if ($salesMaterial['sales_material_type'] == 4) {
            //福袋
            $basicMInfos = $salesMLib->get_order_luckybag_bminfo($salesMaterial['sm_id']);
        } elseif ($salesMaterial['sales_material_type'] == 5) {
            //多选一
            $basicMInfos = $salesMLib->get_order_pickone_bminfo($salesMaterial['sm_id'], $quantity, $shop_id);
        } else {
            $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMaterial['sm_id']);
        }
        $order = app::get('ome')->model('orders')->db_dump(['order_id'=>$order_id], 'order_bn');
        //组织item数据
        switch ($salesMaterial['sales_material_type']) {
            case "2":
                $salesMLib->calProSaleMPriceByRate($obj_sale_price, $basicMInfos);
                $price_rate     = $salesMLib->calProPriceByRate($object['price'], $basicMInfos);
                $pmt_price_rate = $salesMLib->calpmtpriceByRate($object['pmt_price'], $basicMInfos);
                $obj_type       = 'pkg';
                break;
            case "3":
                $obj_type = 'gift';
                break;
            case "7":
                $obj_type = 'lkb';
                break;
            case "5":
                $obj_type = 'pko';
                break;
            case '6':
                $obj_type = 'giftpackage';
                break;
        }
        $branchBatchList = [];
        foreach ($basicMInfos as $k => $basicMInfo) {
            if ($obj_type == 'pkg') {
                $cost = $basicMInfo['cost'];
                //$price      = $price_rate[$basicMInfo['material_bn']] ? bcdiv($price_rate[$basicMInfo['material_bn']]['rate_price'], $price_rate[$basicMInfo['material_bn']]['number'], 2) : 0.00;

                $pmt_price = $pmt_price_rate[$basicMInfo['material_bn']] ? $pmt_price_rate[$basicMInfo['material_bn']]['rate_price'] : 0.00;

                $sale_price        = $basicMInfo['rate_price'];
                $amount            = bcadd($pmt_price, $sale_price, 2);
                $price             = bcdiv($amount, $basicMInfo['number'] * $quantity, 2);
                $weight            = $basicMInfo['weight'];
                $shop_product_id   = 0;
                $divide_order_fee  = 0;
                $part_mjz_discount = 0;
                $item_type         = 'pkg';
                $quantity          = $basicMInfo['number'] * $quantity;
            } else if ($obj_type == 'gift') {
                //如果是赠品重置相关的金额字段
                $cost              = 0.00;
                $price             = 0.00;
                $pmt_price         = 0.00;
                $sale_price        = 0.00;
                $amount            = 0.00;
                $obj_amount        = 0.00;
                $obj_sale_price    = 0.00;
                $item_type         = 'gift';
                $shop_product_id   = $object['shop_product_id'] ? $object['shop_product_id'] : 0;
                $weight            = (float) $object['weight'] ? $object['weight'] : ($basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00);
                $divide_order_fee  = 0;
                $part_mjz_discount = 0;
                $quantity          = $basicMInfo['number'] * $quantity;
            } elseif ($obj_type == 'lkb') {
                //福袋
                $cost              = $basicMInfo['cost'];
                $price             = $basicMInfo['price'];
                $pmt_price         = 0;
                $sale_price        = $basicMInfo['price'] * $quantity * $basicMInfo['number'];
                $amount            = $basicMInfo['price'] * $quantity * $basicMInfo['number'];
                $weight            = $basicMInfo['weight'] * $quantity * $basicMInfo['number'];
                $shop_product_id   = 0;
                $divide_order_fee  = 0;
                $part_mjz_discount = 0;
                $item_type         = 'lkb';
                $lbr_id            = $basicMInfo["lbr_id"];
                $quantity          = $basicMInfo['number'] * $quantity;
            } elseif ($obj_type == 'pko') {
                //多选一
                $cost              = (float) $object['cost'] ? $object['cost'] : $basicMInfo['cost'];
                $price             = (float) $object['price'];
                $pmt_price         = 0;
                $sale_price        = bcmul($obj_sale_price / $quantity, $basicMInfo['number'], 3);
                $amount            = $sale_price;
                $weight            = $basicMInfo['weight'] ? $basicMInfo['weight'] * $basicMInfo['number'] : 0.00;
                $shop_product_id   = 0;
                $divide_order_fee  = 0;
                $part_mjz_discount = 0;
                $item_type         = 'pko';
                $quantity          = $basicMInfo['number'];
            } else {
                $cost              = (float) $object['cost'] ? $object['cost'] : $basicMInfo['cost'];
                $price             = (float) $object['price'];
                $pmt_price         = (float) $object['pmt_price'];
                $sale_price        = (isset($object['sale_price']) && is_numeric($object['sale_price']) && -1 != bccomp($object['sale_price'], 0, 3)) ? $object['sale_price'] : bcsub($obj_amount, (float) $object['pmt_price'], 3);
                $amount            = $obj_amount;
                $weight            = (float) $object['weight'] ? $object['weight'] : ($basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00);
                $shop_product_id   = $object['shop_product_id'] ? $object['shop_product_id'] : 0;
                $item_type         = $obj_type == 'giftpackage' ? 'giftpackage' : 'product';
                $divide_order_fee  = $object['divide_order_fee'];
                $part_mjz_discount = $object['part_mjz_discount'];
                $quantity          = $basicMInfo['number'] * $quantity;
            }
            $order_items[] = array(
                'shop_goods_id'     => $object['shop_goods_id'] ? $object['shop_goods_id'] : 0,
                'product_id'        => $basicMInfo['bm_id'] ? $basicMInfo['bm_id'] : 0,
                'shop_product_id'   => $shop_product_id,
                'bn'                => $basicMInfo['material_bn'],
                'name'              => $basicMInfo['material_name'],
                'cost'              => $cost ? $cost : 0.00,
                'price'             => $price ? $price : 0.00,
                'pmt_price'         => $pmt_price,
                'sale_price'        => $sale_price ? $sale_price : 0.00,
                'amount'            => $amount ? $amount : 0.00,
                'weight'            => $weight ? $weight : 0.00,
                'quantity'          => $quantity,
                'addon'             => '',
                'item_type'         => $item_type,
                'delete'            => ($object['status'] == 'close') ? 'true' : 'false',
                'divide_order_fee'  => $divide_order_fee,
                'part_mjz_discount' => $part_mjz_discount,
                'lbr_id'            => $lbr_id ? $lbr_id : "",
            );

            $freezeData = [];
            $freezeData['bm_id'] = $basicMInfo['bm_id'];
            $freezeData['sm_id'] = $salesMaterial['sm_id'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
            $freezeData['bill_type'] = 0;
            $freezeData['obj_id'] = $order_id;
            $freezeData['shop_id'] = $salesMaterial['shop_id'];
            $freezeData['branch_id'] = 0;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = abs($basicMInfo['quantity']);
            $freezeData['obj_bn'] = $order['order_bn'];
            $branchBatchList[] = $freezeData;
        }

        $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);

        return array(
            'order_id'          => $order_id,
            'obj_type'          => $obj_type,
            'obj_alias'         => $object['obj_alias'] ? $object['obj_alias'] : $this->_obj_alias[$obj_type],
            'bn'                => $salesMaterial['sales_material_bn'],
            'name'              => $salesMaterial['sales_material_name'],
            'goods_id'          => $salesMaterial['sm_id'],
            'price'             => $object[$old_bn]['price'],
            'quantity'          => $object[$old_bn]['quantity'],
            'amount'            => $object[$old_bn]['amount'],
            'pmt_price'         => $object[$old_bn]['pmt_price'],
            'sale_price'        => $object[$old_bn]['sale_price'],
            'divide_order_fee'  => $object[$old_bn]['divide_order_fee'],
            'part_mjz_discount' => $object[$old_bn]['part_mjz_discount'],
            'order_items'       => $order_items
        );
    }
    
    /**
     * 获取订单拆分状态
     * @param $order_id
     * @return string
     * @date 2024-07-24 10:51 上午
     */
    public function getProcessStatus($order_id)
    {
        $row = $this->db->selectrow('SELECT SUM(nums) sum_nums,SUM(split_num) sum_split_num FROM sdb_ome_order_items WHERE order_id=' . $order_id . ' AND `delete`="false"');
        if (!$row) {
            return 'unconfirmed';
        }
        $sum_nums      = $row['sum_nums'];
        $sum_split_num = $row['sum_split_num'];
        if ($sum_split_num == 0) {
            $process_status = 'unconfirmed';
        } elseif ($sum_split_num < $sum_nums) {
            $process_status = 'splitting';
        } else {
            $process_status = 'splited';
        }
        return $process_status;
    }
    
    public function getOrderIdByFilterNameEq($filter)
    {
    
        $orderObj = app::get('ome')->model('orders');
        
        $searchfilter = $filter;
        $product_name   = $filter['sales_material_name'];
        
        $where = 1;
        if (is_array($product_name)) {
            $where = 'in (\'' . implode('\',\'', $product_name) . '\')';
        } else {
            $where = '= \'' . $product_name . '\'';
        }
        unset($searchfilter['sales_material_name']);
        $order_filter = $orderObj->_filter($searchfilter);
        $order_filter = str_replace('`sdb_ome_orders`', 'o', $order_filter);
        $sql          = 'SELECT count(1) as _c FROM sdb_ome_order_objects as i LEFT JOIN sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.name ' . $where . ' AND' . $order_filter;
        $count        = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT i.order_id FROM sdb_ome_order_objects as i LEFT JOIN sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.name ' . $where . ' AND ' . $order_filter;
            $total  = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }
        
        $sql  = 'SELECT i.order_id FROM sdb_ome_order_objects as i LEFT JOIN  sdb_ome_orders as o ON i.order_id=o.order_id WHERE i.name ' . $where . ' AND ' . $order_filter;
        $rows = $this->db->select($sql);
        
        return $rows;
    }
}
