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

class console_mdl_difference_receiving_inventory extends dbeav_model
{
    public $defaultOrder = array('di.diff_status ASC, di.diff_id DESC');
    //是否有导出配置
    var $has_export_cnf = true;
    
    public $export_name = '差异单列表';
    
    public function exportName(&$data, $filter = array())
    {
        $data['name'] = $this->export_name . '-' . date('Y-m-d H:i:s', time());
    }
    
    public function get_schema()
    {
        $schema['idColumn']   = $schema['pkeys'] = 'diff_id';
        $schema['textColumn'] = 'diff_id';
        
        $schema['columns'] = array(
            'diff_id'          => array(
                'label' => 'diff_id',
            ),
            'creat_time'       => array(
                'label'           => '创建时间',
                'default_in_list' => true,
                'in_list'         => true,
                'width'           => 130,
                'order'           => 10,
            ),
            'diff_bn'          => array(
                'label'           => '差异单号',
                'default_in_list' => true,
                'in_list'         => true,
                'filtertype'      => 'normal',
                'filterdefault'   => true,
                'panel_id'        => 'console_difference_receiving_inventory_filter_top',
                'width'           => 130,
                'order'           => 15,
            ),
            'relevant_bn'      => array(
                'label'           => '业务单号',
                'default_in_list' => true,
                'in_list'         => true,
                'filtertype'      => 'normal',
                'filterdefault'   => true,
                'panel_id'        => 'console_difference_receiving_inventory_filter_top',
                'width'           => 125,
                'order'           => 20,
            ),
            'packaging_status' => array(
                'label'           => '外箱状态',
                'default_in_list' => true,
                'in_list'         => true,
                'width'           => 80,
                'order'           => 24,
            ),
            'diff_nums'        => array(
                'label'           => '差异SKU',
                'default_in_list' => false,
                'in_list'         => false,
                'width'           => 70,
                'order'           => 25,
            ),
            'abs_diff_nums'    => array(
                'label'           => '差异数量', // 统计绝对值
                'default_in_list' => false,
                'in_list'         => false,
                'width'           => 70,
                'order'           => 26,
            ),
            'status'           => array(
                'label'           => '单据状态',
                'default_in_list' => true,
                'in_list'         => true,
                'width'           => 70,
                'order'           => 30,
            ),
            'oper'             => array(
                'label'           => '处理人',
                'default_in_list' => true,
                'in_list'         => true,
                'width'           => 70,
                'order'           => 35,
            ),
            'confirm_time'     => array(
                'label'           => '处理时间',
                'default_in_list' => true,
                'in_list'         => true,
                'width'           => 140,
                'order'           => 40,
            ),
        
        );
        foreach ($schema['columns'] as $key => $val) {
            $val['in_list'] == true and $schema['in_list'][] = $key;
            $val['default_in_list'] == true and $schema['default_in_list'][] = $key;
        }
        
        return $schema;
    }
    
    
    public function finder_getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderType = null)
    {
        $cols = 'di.branch_id,di.extrabranch_id,di.packaging_status,di.diff_id,di.create_time as creat_time,di.diff_bn,di.original_bn as relevant_bn,di.diff_status as status,di.operator as oper,di.up_time as confirm_time';
        $sql  = "SELECT $cols FROM sdb_taoguaniostockorder_diff di
                LEFT JOIN sdb_taoguaniostockorder_iso AS i ON di.original_id = i.iso_id
                WHERE " . $this->finder_filter($filter);
        
        $orderType = $orderType ? $orderType : $this->defaultOrder[0];
        if ($orderType) {
            $sql .= ' ORDER BY ' . (is_array($orderType) ? implode($orderType, ' ') : $orderType);
        }
        $data = $this->db->selectLimit($sql, $limit, $offset);
        if (!$data) {
            return $data;
        }
        return $data;
    }
    
    
    public function finder_count($filter = null)
    {
        $row = $this->db->select("SELECT count(*) as _count FROM sdb_taoguaniostockorder_diff di
            LEFT JOIN sdb_taoguaniostockorder_iso AS i ON di.original_id = i.iso_id
            WHERE " . $this->finder_filter($filter));
        return intval($row[0]['_count']);
    }
    
    public function finder_filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = [];
        // 快照时间
        if ($filter['diff_id']) {
            if (is_array($filter['diff_id'])) {
                $str_ids = '"' . implode('","', $filter['diff_id']) . '"';
                $where[] = " di.diff_id IN ($str_ids)";
            } else {
                $where[] = " di.diff_id = " . $filter['diff_id'];
            }
            
        }
        unset($filter['diff_id']);
        
        if ($filter['bill_type']) {
            if (is_array($filter['bill_type'])) {
                $str_ids = '"' . implode('","', $filter['bill_type']) . '"';
                $where[] = " i.bill_type IN ($str_ids)";
            } else {
                $where[] = " i.bill_type = '" . $filter['bill_type'] . '\'';
            }
            
        }
        unset($filter['bill_type']);
        
        //只获取店铺差异
        if ($filter['branch_id']) {
            if (is_array($filter['branch_id'])) {
                $str_branch_id = '"' . implode('","', $filter['branch_id']) . '"';
                $where[]       = " di.branch_id IN ($str_branch_id)";
            } else {
                $where[] = " di.branch_id = '" . $filter['branch_id'] . '\'';
            }
        }
        unset($filter['branch_id']);
        
        if ($filter['extrabranch_id']) {
            if (is_array($filter['extrabranch_id'])) {
                $str_branch_id = '"' . implode('","', $filter['extrabranch_id']) . '"';
                $where[]       = " di.extrabranch_id IN ($str_branch_id)";
            } else {
                $where[] = " di.extrabranch_id = '" . $filter['extrabranch_id'] . '\'';
            }
        }
        unset($filter['extrabranch_id']);
        
        if (isset($filter['relevant_bn'])) {
            $filter['relevant_bn'] && $where[] = " di.original_bn = '" . $filter['relevant_bn'] . "'";
            unset($filter['relevant_bn']);
        }
        
        if (isset($filter['diff_bn'])) {
            $filter['diff_bn'] && $where[] = " di.diff_bn = '" . $filter['diff_bn'] . "'";
            unset($filter['diff_bn']);
        }
        
        $sqlstr = '';
        
        if ($where) {
            $sqlstr .= implode(' AND ', $where) . " AND ";
        }
        return $sqlstr . parent::_filter($filter, $tableAlias, $baseWhere);
    }
    
    /**
     * 处理状态
     * @param null $status
     * @return string|string[]
     */
    public function diff_status($status = NULL)
    {
        $diff_status = array(
            1 => '未处理',
            2 => '部分处理',
            3 => '全部处理',
            4 => '取消',
        );
        if ($status == NULL) {
            return $diff_status;
        } else {
            return $diff_status[$status];
        }
    }
    
    public function modifier_status($status, $list, $row)
    {
        return $this->diff_status($status);
    }
    
    public function modifier_oper($oper, $list, $row)
    {
        if (in_array($row['status'], ['2', '3'])) {
            return $oper;
        }
        return '';
    }
    
    public function modifier_confirm_time($confirm_time, $list, $row)
    {
        if ($confirm_time && in_array($row['status'], ['2', '3'])) {
            return $confirm_time;
        }
        return '';
    }
    
    public function modifier_creat_time($creat_time, $list, $row)
    {
        if ($creat_time) {
            return date('Y-m-d H:i:s', $creat_time);
        }
        return '';
    }
    
    /**
     * 获取大仓
     * @return array
     */
    public static function getShopBranches()
    {
        static $branches;
        if ($branches) {
            return $branches;
        }
        
        // 大仓库存
        $branchMdl = app::get('ome')->model('branch');
        
        $brancheList  = $branchMdl->getList('branch_id,branch_bn', [
            'b_type|in'        => ['2', '3'],
            'check_permission' => 'false',
            'disabled'         => 'false',
        ]);
        $branchIdList = array_column($brancheList, null, 'branch_id');
        return $branchIdList;
    }
    
    public function modifier_packaging_status($packaging_status, $list, $row)
    {
        return $packaging_status == 'intact' ? '完好' : '有破损';
    }
    
    /**
     * 根据查询条件获取导出数据
     * @param $fields
     * @param $filter
     * @param $has_detail
     * @param $curr_sheet
     * @param $start
     * @param $end
     * @param $op_id
     * @return bool
     * @author db
     * @date 2023-07-10 4:13 下午
     */
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
    {
        //根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            $title         = array();
            $main_columns = array_flip($this->io_title());
            $filde_columns = [];
            foreach (explode(',', $fields) as $k => $col) {
                //处理column_标识
                if ('column_' == substr($col, 0, 7)) {
                    $col = substr($col, 7);
                }
                if (isset($main_columns[$col])) {
                    $title[]         = $main_columns[$col];
                    $filde_columns[] = $col;
                }
            }
            
            $data['content']['main'][] = $this->getCustomExportTitle($title);
        }
        if (!$omedelivery = $this->finder_getList('*', $filter)) {
            return false;
        }
        //获取lib里的扩展字段值
        $export_fields = 'column_branch_bn,column_extrabranch_bn,column_diff_nums,column_abs_diff_nums';
        $finderObj = kernel::single('console_finder_difference_receiving_inventory');
        foreach ($omedelivery as $key=>$aFilter) {
            foreach (explode(',', $export_fields) as $v) {
                if ('column_' == substr($v, 0, 7) && method_exists($finderObj, $v)) {
                    $cv          = $finderObj->{$v}($aFilter, $omedelivery);
                    $omedelivery[$key][substr($v,7)] = $cv;
                }
            }
        }
        //明细标题
        $basicMaterialLib = kernel::single('material_basic_material');
        
        $diffItemsMdl        = app::get('taoguaniostockorder')->model('diff_items');
        $order_items_columns = array_values($this->orderItemsExportTitle());
        $items_fields        = implode(',', $order_items_columns);
    
        $diff_status = [
            1 => '未处理',
            2 => '部分处理',
            3 => '全部处理',
            4 => '取消',
        ];
        foreach ($omedelivery as $k => $aFilter) {
            $aFilter['creat_time'] = $aFilter['creat_time'] ? date('Y-m-d H:i:s', $aFilter['creat_time']) : '';
            $aFilter['packaging_status'] = $aFilter['packaging_status']== 'intact' ? '完好' : '有破损';
            $aFilter['status'] = $diff_status[$aFilter['status']];
            //查明细
            $diff_items = $diffItemsMdl->getList('*', ['diff_id' => $aFilter['diff_id']]);
            
            foreach ($diff_items as $itemk => $itemv) {
                $product = $basicMaterialLib->getBasicMaterialExt($itemv['product_id']);
                
                $diffItemObjRow                 = array();
                $diffItemObjRow['product_name'] = str_replace("\n", " ", $itemv['product_name']);
                $diffItemObjRow['bn']           = $itemv['bn'];
                $diffItemObjRow['barcode']      = $itemv['barcode'];
                $diffItemObjRow['barcode']      = $product['barcode'];
                
                $index = $aFilter['relevant_bn'] . '_' . $itemv['bn'];
                
                //获取出库和入库数量
                $info                            = $this->getIsoData($index, $diff_items);
                $diffItemObjRow['from_item_num'] = $info['from_item_num'];
                $diffItemObjRow['to_item_num']   = $info['to_item_num'];
                $diffItemObjRow['diff_item_num'] = $info['diff_item_num'];
                $diffItemObjRow['adjustment_bn'] = $info['adjustment_bn'];
                
                $diff_reason                         = $diffItemsMdl->diff_reason;//结果
                $diffItemObjRow['diff_memo'] = $diff_reason[$itemv['diff_reason']];//差异备注
                
                $diffItemObjRow['responsible_value'] = $diffItemsMdl->responsible[$itemv['responsible']];//责任方
                
                $diff_status                         = $diffItemsMdl->diff_status;
                $diffItemObjRow['diff_status_value'] = $diff_status[$itemv['diff_status']];
                $diffItemObjRow['operator']          = $itemv['operator'];
                
                $orderdataRow = array_merge($aFilter, $diffItemObjRow);
                $all_fields   = implode(',', $filde_columns) . ',' . $items_fields;//主表 拼接 明细表字段
                
                $exptmp_data = [];
                foreach (explode(',', $all_fields) as $key => $col) {
                    if (isset($orderdataRow[$col])) {
                        $orderdataRow[$col] = mb_convert_encoding($orderdataRow[$col], 'GBK', 'UTF-8');
                        $exptmp_data[]      = $orderdataRow[$col];
                    } else {
                        $exptmp_data[] = '';
                    }
                }
                $data['content']['main'][] = implode(',', $exptmp_data);
            }
        }
        
        return $data;
    }
    
    /**
     * 组装导出的数据
     * @param $bn_iso_bn
     * @param $list
     * @return mixed
     * @author db
     * @date 2023-07-10 4:10 下午
     */
    public function getIsoData($bn_iso_bn, $list)
    {
        static $iso;
        if (isset($iso[$bn_iso_bn])) {
            return $iso[$bn_iso_bn];
        }
        $diff_obj       = app::get('taoguaniostockorder')->model("diff");
        $diff_items_obj = app::get('taoguaniostockorder')->model("diff_items");
        $isoItemsMdl    = app::get('taoguaniostockorder')->model("iso_items");
        
        $diff_bns = array_column($list, 'diff_bn');
        $diffList = $diff_obj->getList('*', ['diff_bn' => $diff_bns]);
        $diffList = array_column($diffList, null, 'diff_id');
        
        $diff_ids      = array_column($list, 'diff_id');
        $diffItemsList = $diff_items_obj->getList('*', ['diff_id' => $diff_ids]);
        
        $original_bns = array_column($diffList, 'original_bn');
        //收发货明细
        $isoItemsList    = $isoItemsMdl->getList('product_id,bn,nums,normal_num,defective_num,iso_bn', array('iso_bn' => $original_bns));
        $newIsoItemsList = [];
        foreach ($isoItemsList as $k => $item) {
            $index                   = $item['iso_bn'] . '_' . $item['bn'];
            $newIsoItemsList[$index] = $item;
        }
        
        $adjustMdl      = app::get('console')->model('adjust');
        $adjustItemsMdl = app::get('console')->model('adjust_items');
        $adjust         = $adjustMdl->getList('id, adjust_bn,origin_bn', ['origin_bn' => $diff_bns]);
        $adjust         = array_column($adjust, null, 'id');
        
        $adjustId   = array_column($adjust, 'id');
        $ajustItems = $adjustItemsMdl->getList('bm_id, adjust_id,bm_bn', ['adjust_id|in' => $adjustId]);
        $ajustItems = array_column($ajustItems, null, 'bm_id');
        
        $newAdjustItems = [];
        foreach ($ajustItems as $key => $adjust_item) {
            $adjustInfo = $adjust[$adjust_item['adjust_id']];
            $index      = $adjustInfo['origin_bn'] . '_' . $adjust_item['bm_bn'];
            
            $newAdjustItems[$index]['adjust_bn'] = $adjustInfo['adjust_bn'];
        }
        
        
        foreach ($diffItemsList as $key => $diff_item) {
            $diffInfo = $diffList[$diff_item['diff_id']];
            $index    = $diffInfo['original_bn'] . '_' . $diff_item['bn'];
            //发货数量
            $from_item_num                = $newIsoItemsList[$index]['nums'];
            $iso[$index]['from_item_num'] = $from_item_num ? $from_item_num : 0;
            
            //收货数量
            $to_item_nums               = $newIsoItemsList[$index]['normal_num'] + $newIsoItemsList[$index]['defective_num'];
            $iso[$index]['to_item_num'] = $to_item_nums;
            
            //差异数量
            $diff_item_num                = $from_item_num - $to_item_nums;
            $iso[$index]['diff_item_num'] = $diff_item_num;
            
            //调整单号
            $index2 = $diffInfo['diff_bn'] . '_' . $diff_item['bn'];
            $iso[$index]['adjustment_bn'] = $newAdjustItems[$index2] ? $newAdjustItems[$index2]['adjust_bn'] : '';
        }
        
        return $iso[$bn_iso_bn];
    }
    
    public function getCustomExportTitle($main_title)
    {
        $main_title        = $main_title;
        $order_items_title = array_keys($this->orderItemsExportTitle());
        $title             = array_merge($main_title, $order_items_title);
        return mb_convert_encoding(implode(',', $title), 'GBK', 'UTF-8');
    }
    
    public function orderItemsExportTitle()
    {
        $items_title = array(
            '基础物料名称' => 'product_name',
            '基础物料编码' => 'bn',
            '条形码'    => 'barcode',
            '发货数量'   => 'from_item_num',
            '收货数量'   => 'to_item_num',
            '差异数量'   => 'diff_item_num',
            '责任方'    => 'responsible_value',
            '差异备注'   => 'diff_memo',
            '处理状态'   => 'diff_status_value',
            '调整单号'   => 'adjustment_bn',
            '操作人员'   => 'operator',
        );
        return $items_title;
    }
    
    //根据过滤条件获取导出发货单的主键数据数组
    public function getPrimaryIdsByCustom($filter, $op_id)
    {
        $cols = 'di.diff_id';
        $sql  = "SELECT $cols FROM sdb_taoguaniostockorder_diff di LEFT JOIN sdb_taoguaniostockorder_iso AS i ON di.original_id = i.iso_id WHERE " . $this->finder_filter($filter);
        $data = $this->db->select($sql);
        $ids  = [];
        if ($data) {
            $ids = array_column($data, 'diff_id');
        }
        return $ids;
    }
    
    public function io_title()
    {
        $import_title = [
            '创建时间'  => 'creat_time',
            '差异单号'  => 'diff_bn',
            '业务单号'  => 'relevant_bn',
            '收货仓编码' => 'branch_bn',
            '发货仓编码' => 'extrabranch_bn',
            '差异SKU' => 'diff_nums',
            '差异数量'  => 'abs_diff_nums',
            '外箱状态'  => 'packaging_status',
            '单据状态'  => 'status',
            '处理人'   => 'oper',
            '处理时间'  => 'confirm_time',
        ];
        return $import_title;
    }
}