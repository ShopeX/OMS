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


class ome_mdl_order_retrial extends dbeav_model
{
    /*------------------------------------------------------ */
    //-- 获取列表数据[自定义]
    /*------------------------------------------------------ */
    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $where        = $this->_filter($filter);
        if(!empty($where))
        {
            $where    = ' WHERE '.$where;
        }
        
        $sql      = "SELECT a.*, b.total_amount, b.process_status, b.is_cod, b.pay_status, b.ship_status, b.payment, b.createtime, paytime,b.shop_id FROM ". DB_PREFIX ."ome_order_retrial as a
                      LEFT JOIN ". DB_PREFIX ."ome_orders as b ON a.order_id=b.order_id 
                      ". $where ." ORDER BY a.id DESC";
        $rows     = $this->db->selectLimit($sql, $limit, $offset);
        $this->tidy_data($rows, $cols);

        return $rows;
    }
    /**
     * 规范化高级筛选中的单值或多行文本值
     *
     * 历史订单的物料筛选使用多行文本框，Finder会将多个值以换行符提交。
     * 这里统一兼容字符串和数组，去除空值并去重，供店铺和物料筛选复用。
     *
     * @param mixed $value
     * @return array
     */
    private function _normalize_filter_values($value)
    {
        if(is_string($value))
        {
            $value = preg_split('/\r\n|\r|\n/', $value);
        }

        $result = array();
        foreach((array) $value as $item)
        {
            if(!is_scalar($item))
            {
                continue;
            }

            $item = trim((string) $item);
            if($item !== '')
            {
                $result[$item] = $item;
            }
        }

        return array_values($result);
    }

    /**
     * 生成日期时间高级筛选条件
     *
     * 参数结构与Finder现有time控件保持一致，支持等于、大于、小于和时间范围。
     *
     * @param array  $filter
     * @param string $field
     * @param string $column
     * @return string
     */
    private function _build_time_filter($filter, $field, $column)
    {
        $search_key = '_'.$field.'_search';
        $search = isset($filter[$search_key]) ? $filter[$search_key] : '';
        if($search == 'between' && !empty($filter[$field.'_from']) && !empty($filter[$field.'_to']))
        {
            $from_hour = isset($filter['_DTIME_']['H'][$field.'_from']) ? intval($filter['_DTIME_']['H'][$field.'_from']) : 0;
            $from_minute = isset($filter['_DTIME_']['M'][$field.'_from']) ? intval($filter['_DTIME_']['M'][$field.'_from']) : 0;
            $to_hour = isset($filter['_DTIME_']['H'][$field.'_to']) ? intval($filter['_DTIME_']['H'][$field.'_to']) : 0;
            $to_minute = isset($filter['_DTIME_']['M'][$field.'_to']) ? intval($filter['_DTIME_']['M'][$field.'_to']) : 0;
            $from = strtotime($filter[$field.'_from'].' '.$from_hour.':'.$from_minute.':00');
            $to = strtotime($filter[$field.'_to'].' '.$to_hour.':'.$to_minute.':00');

            return "(".$column.">='".$from."' AND ".$column."<='".$to."')";
        }

        if(empty($filter[$field]))
        {
            return '';
        }

        $hour = isset($filter['_DTIME_']['H'][$field]) ? intval($filter['_DTIME_']['H'][$field]) : 0;
        $minute = isset($filter['_DTIME_']['M'][$field]) ? intval($filter['_DTIME_']['M'][$field]) : 0;
        $time = strtotime($filter[$field].' '.$hour.':'.$minute.':00');
        if($search == 'than')
        {
            return $column.">'".$time."'";
        }
        elseif($search == 'lthan')
        {
            return $column."<'".$time."'";
        }

        return $column."='".$time."'";
    }
    /*------------------------------------------------------ */
    //-- 过滤条件[自定义]
    /*------------------------------------------------------ */
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where[]  = 1;
        if(!empty($filter['id']))
        { 
            $where[]     = "a.id = '".$filter['id']."'";
        }
        if(!empty($filter['order_id']))
        { 
            $where[]     = "a.order_id = '".$filter['order_id']."'";
        }
        if(!empty($filter['order_bn']))
        {
            $where[]     = "a.order_bn = '".$filter['order_bn']."'";
        }
        //控制器传入不可修改的组织权限范围，必须独立于用户选择的组织筛选条件。
        //两个条件同时存在时会自然取交集，恶意提交权限外的org_id也无法越权。
        if(!empty($filter['permission_org_id']))
        {
            $org_ids = array();
            foreach((array) $filter['permission_org_id'] as $org_id)
            {
                $org_id = intval($org_id);
                if($org_id > 0)
                {
                    $org_ids[$org_id] = $org_id;
                }
            }

            $where[] = $org_ids ? "b.org_id IN (".implode(',', $org_ids).")" : '1=0';
        }
        //运营组织高级筛选，选项由Finder按当前账号权限裁剪。
        if(!empty($filter['org_id']))
        {
            $org_ids = array();
            foreach((array) $filter['org_id'] as $org_id)
            {
                $org_id = intval($org_id);
                if($org_id > 0)
                {
                    $org_ids[$org_id] = $org_id;
                }
            }

            $where[] = $org_ids ? "b.org_id IN (".implode(',', $org_ids).")" : '1=0';
        }
        //来源店铺沿用历史订单的可搜索多选控件。
        if(!empty($filter['shop_id']))
        {
            $shop_ids = $this->_normalize_filter_values($filter['shop_id']);
            $quoted_shop_ids = array();
            foreach($shop_ids as $shop_id)
            {
                $quoted_shop_ids[] = $this->db->quote($shop_id);
            }
            $where[] = $quoted_shop_ids ? "b.shop_id IN (".implode(',', $quoted_shop_ids).")" : '1=0';
        }
        //基础物料和销售物料使用EXISTS过滤，避免直接JOIN明细导致复审列表重复。
        if(!empty($filter['product_bn']))
        {
            $product_bns = $this->_normalize_filter_values($filter['product_bn']);
            $quoted_product_bns = array();
            foreach($product_bns as $product_bn)
            {
                $quoted_product_bns[] = $this->db->quote($product_bn);
            }
            $where[] = $quoted_product_bns
                ? "EXISTS (SELECT 1 FROM ". DB_PREFIX ."ome_order_items AS oi WHERE oi.order_id=a.order_id AND oi.`delete`='false' AND oi.bn IN (".implode(',', $quoted_product_bns)."))"
                : '1=0';
        }
        if(!empty($filter['sales_material_bn']))
        {
            $sales_material_bns = $this->_normalize_filter_values($filter['sales_material_bn']);
            $quoted_sales_material_bns = array();
            foreach($sales_material_bns as $sales_material_bn)
            {
                $quoted_sales_material_bns[] = $this->db->quote($sales_material_bn);
            }
            $where[] = $quoted_sales_material_bns
                ? "EXISTS (SELECT 1 FROM ". DB_PREFIX ."ome_order_objects AS oo WHERE oo.order_id=a.order_id AND oo.`delete`='false' AND oo.bn IN (".implode(',', $quoted_sales_material_bns)."))"
                : '1=0';
        }
        if(!empty($filter['sales_material_name']))
        {
            $sales_material_names = $this->_normalize_filter_values($filter['sales_material_name']);
            $quoted_sales_material_names = array();
            foreach($sales_material_names as $sales_material_name)
            {
                $quoted_sales_material_names[] = $this->db->quote($sales_material_name);
            }
            $where[] = $quoted_sales_material_names
                ? "EXISTS (SELECT 1 FROM ". DB_PREFIX ."ome_order_objects AS oo WHERE oo.order_id=a.order_id AND oo.`delete`='false' AND oo.name IN (".implode(',', $quoted_sales_material_names)."))"
                : '1=0';
        }
        $createtime_where = $this->_build_time_filter($filter, 'createtime', 'b.createtime');
        if($createtime_where)
        {
            $where[] = $createtime_where;
        }
        if(isset($filter['pay_status']) && $filter['pay_status'] !== '')
        {
            $pay_status = array();
            foreach((array) $filter['pay_status'] as $status)
            {
                $status = intval($status);
                if($status >= 0 && $status <= 8)
                {
                    $pay_status[$status] = $status;
                }
            }
            $where[] = $pay_status ? "b.pay_status IN (".implode(',', $pay_status).")" : '1=0';
        }
        if(isset($filter['ship_status']) && $filter['ship_status'] !== '')
        {
            $ship_status = array();
            foreach((array) $filter['ship_status'] as $status)
            {
                $status = intval($status);
                if($status >= 0 && $status <= 4)
                {
                    $ship_status[$status] = $status;
                }
            }
            $where[] = $ship_status ? "b.ship_status IN (".implode(',', $ship_status).")" : '1=0';
        }
        if(!empty($filter['process_status']))
        {
            $allowed_process_status = array(
                'unconfirmed' => true,
                'confirmed' => true,
                'splitting' => true,
                'splited' => true,
                'cancel' => true,
                'remain_cancel' => true,
                'is_retrial' => true,
                'is_declare' => true,
            );
            $process_status = array();
            foreach($this->_normalize_filter_values($filter['process_status']) as $status)
            {
                if(isset($allowed_process_status[$status]))
                {
                    $process_status[] = $this->db->quote($status);
                }
            }
            $where[] = $process_status ? "b.process_status IN (".implode(',', $process_status).")" : '1=0';
        }
        if(!empty($filter['status']) || $filter['status'] == '0')
        {
            if(is_array($filter['status']))
            {
                $_temp      = array();
                foreach ($filter['status'] as $key => $val)
                {
                    $_temp[]       = "'".intval($val)."'";
                }
                $_temp      = implode(',', $_temp);
                
                $where[]    = "a.status in (".$_temp.")";
            }
            else
            {
                $where[]     = "a.status = '".$filter['status']."'";
            }
        }
        
        //标记是否加group_id判断
        if($filter['assign'])
        {
            if($filter['group_id'])
            {
                $where[]     = "IF(b.op_id>0, b.op_id='".$filter['op_id']."', b.group_id='".$filter['group_id']."')";
            }
            unset($filter['assign']);
        }
        elseif(!empty($filter['op_id']))
        {
            $where[]     = "a.op_id = '".$filter['op_id']."'";
        }
        
        if(!empty($filter['retrial_type']))
        {
            $where[]     = "a.retrial_type = '".$filter['retrial_type']."'";
        }

        if(!empty($filter['dateline']))
            {
                $create_time_hour     = $filter['_DTIME_']['H']['dateline'];
                $create_time_minute   = $filter['_DTIME_']['M']['dateline'];             
                $create_time_start    = strtotime($filter['dateline'].' '.$create_time_hour.':'.$create_time_minute.':00');
                if($filter['_dateline_search']=='nequal')
                {
                    $where[]    = "a.dateline='".$create_time_start."'";
                }
                elseif($filter['_dateline_search']=='than')
                {
                    $where[]    = "a.dateline>'".$create_time_start."'";
                }
                elseif($filter['_dateline_search']=='lthan')
                {
                    $where[]    = "a.dateline<'".$create_time_start."'";
                }
                elseif($filter['_dateline_search']=='between' && $filter['dateline_from'] && $filter['dateline_to'])
                {
                    $from_hour            = $filter['_DTIME_']['H']['dateline_from'];
                    $from_minute          = $filter['_DTIME_']['H']['dateline_from'];
                    $create_time_from     = $filter['dateline_from'];
                    $create_time_from     = strtotime($create_time_from.' '.$from_hour.':'.$from_minute.':00');
                    
                    $to_hour            = $filter['_DTIME_']['H']['dateline_to'];
                    $to_minute          = $filter['_DTIME_']['H']['dateline_to'];
                    $create_time_to     = $filter['dateline_to'];
                    $create_time_to     = strtotime($create_time_to.' '.$to_hour.':'.$to_minute.':00');
                    
                    $where[]    = "(a.dateline>='".$create_time_from."' AND a.dateline<='".$create_time_to."')";
                }
        }
        if(!empty($filter['lastdate']))
            {
                $create_time_hour     = $filter['_DTIME_']['H']['lastdate'];
                $create_time_minute   = $filter['_DTIME_']['M']['lastdate'];             
                $create_time_start    = strtotime($filter['lastdate'].' '.$create_time_hour.':'.$create_time_minute.':00');
                if($filter['_lastdate_search']=='nequal')
                {
                    $where[]    = "a.lastdate='".$create_time_start."'";
                }
                elseif($filter['_lastdate_search']=='than')
                {
                    $where[]    = "a.lastdate>'".$create_time_start."'";
                }
                elseif($filter['_lastdate_search']=='lthan')
                {
                    $where[]    = "a.lastdate<'".$create_time_start."'";
                }
                elseif($filter['_lastdate_search']=='between' && $filter['lastdate_from'] && $filter['lastdate_to'])
                {
                    $from_hour            = $filter['_DTIME_']['H']['lastdate_from'];
                    $from_minute          = $filter['_DTIME_']['H']['lastdate_from'];
                    $create_time_from     = $filter['lastdate_from'];
                    $create_time_from     = strtotime($create_time_from.' '.$from_hour.':'.$from_minute.':00');
                    
                    $to_hour            = $filter['_DTIME_']['H']['lastdate_to'];
                    $to_minute          = $filter['_DTIME_']['H']['lastdate_to'];
                    $create_time_to     = $filter['lastdate_to'];
                    $create_time_to     = strtotime($create_time_to.' '.$to_hour.':'.$to_minute.':00');
                    
                    $where[]    = "(a.lastdate>='".$create_time_from."' AND a.lastdate<='".$create_time_to."')";
                }
        }
        
        return implode(' AND ', $where);
    }    
    /*------------------------------------------------------ */
    //-- 获取总数[自定义]
    /*------------------------------------------------------ */
    public function count($filter=null)
    {
        $where        = $this->_filter($filter);
        if(!empty($where))
        {
            $where    = ' WHERE '.$where;
        }

        $needJoin = (strpos($where, 'b.') !== false);
        if ($needJoin) {
            $sql  = "SELECT count(*) as num FROM ". DB_PREFIX ."ome_order_retrial as a 
                      LEFT JOIN ". DB_PREFIX ."ome_orders as b ON a.order_id=b.order_id 
                      ". $where;
        } else {
            $sql  = "SELECT count(*) as num FROM ". DB_PREFIX ."ome_order_retrial as a ". $where;
        }
        
        $row      = $this->db->select($sql);
        return $row[0]['num'];
    }
    /**
     +----------------------------------------------------------
     * 获取订单中的商品信息[order_items]
     +----------------------------------------------------------
     * @param   Array     $item_list
     * return   Array
     +----------------------------------------------------------
     */
    public function get_obj_product($item_list)
    {
        $product_list  = array();

        foreach ($item_list as $key => $obj_item)
        {
            foreach ($obj_item as $key_j => $items)
            {
                foreach ($items['order_items'] as $key_k => $item)
                {
                    $product_list[$key_k]   = $item;
                }
            }
        }
        
        return $product_list;
    }
    /**
     +----------------------------------------------------------
     * 获取订单中购买的所有商品id及购买量
     +----------------------------------------------------------
     * @param   Array     $item_list
     * @param   boolean   $show_del : true允许加入删除商品
     * return   Array
     +----------------------------------------------------------
     */
    public function get_product_list($item_list, $show_del=false)
    {
        $product_list  = array();
        $quantity_list = array();

        foreach ($item_list as $key => $obj_item)
        {
            foreach ($obj_item as $key_j => $items)
            {
                foreach ($items['order_items'] as $key_k => $item)
                {
                    if($show_del)
                    {
                        $product_list['ids'][$item['product_id']]  = $item['product_id'];
                        
                        $quantity_list[$item['product_id']][]        = intval($item['nums']);
                    }
                    else
                    {
                        if($item['delete'] == 'false')
                        {
                            $product_list['ids'][$item['product_id']]  = $item['product_id'];
                            
                            $quantity_list[$item['product_id']][]        = intval($item['nums']);
                        }
                    }
                }
            }
        }
        
        #订单明细中有重复货号则累加数量
        foreach ($product_list['ids'] as $product_id)
        {
            foreach ($quantity_list[$product_id] as $val_num)
            {
                $product_list['nums'][$product_id]    += $val_num;
            }
        }
        unset($quantity_list);
        
        return $product_list;
    }
    /**
     +----------------------------------------------------------
     * 获取未设置成本价的商品货号
     +----------------------------------------------------------
     * @param   Array     $product_arr
     * return   Array
     +----------------------------------------------------------
     */
    public function get_invalid_bn($product_arr)
    {
        $bn_arr   = array();
        foreach ($product_arr as $key => $val)
        {
            $bn_arr[]     = $val['bn'];
        }
        
        return $bn_arr;
    }
    /**
     +----------------------------------------------------------
     * 获取订单成本价&销售价 * 购买量
     +----------------------------------------------------------
     * @param   Array     $product_ids
     * @param   Array     $buy_product_nums
     * @param   boolean   $detail   true保存每件商品价格
     * return   Array
     +----------------------------------------------------------
     */
    public function get_price_monitor($product_ids, $buy_product_nums=array(), $detail=false)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        
        $price_monitor = array();
        
        $datalist    = $basicMaterialSelect->getlist_ext('bm_id, material_bn, retail_price, cost', array('bm_id'=>$product_ids));
        foreach ($datalist as $key => $val)
        {
            $product_id        = $val['product_id'];
            $val['cost']       = floatval($val['cost']);
            $val['price']      = floatval($val['price']);
            
            #保存未设置成本价格商品
            if($val['cost'] <= 0)
            {
                $price_monitor['invalid'][]   = array(
                                                  'product_id'=>$product_id,
                                                  'bn'=>$val['bn'],
                                                );
            }
            
            if(!empty($buy_product_nums[$product_id]))
            {
               $val['cost']       = $val['cost'] * $buy_product_nums[$product_id];
               $val['price']       = $val['price'] * $buy_product_nums[$product_id];
            }
            
            #保存每件商品价格
            if($detail)
            {
                $price_monitor['detail'][$product_id]['cost']     = $val['cost'];
                $price_monitor['detail'][$product_id]['price']    = $val['price'];
            }
            
            $price_monitor['cost']      += $val['cost'];//成本 价
            $price_monitor['price']     += $val['price'];//销售价
        }
        
        return $price_monitor;
    }
    /**
     +----------------------------------------------------------
     * 获取修改后订单和订单快照的对比
     +----------------------------------------------------------
     * @param   intval    $order_id
     * @param   intval    $retrial_id
     * return   Array
     +----------------------------------------------------------
     */
    public function contrast_order($order_id, $retrial_id=0)
    {
        #订单快照[原始订单]
        $order_old  = array();
        $oSnapshot  = app::get('ome')->model('order_retrial_snapshot');
        $order_old       = $oSnapshot->getList('*', array('retrial_id'=>$retrial_id, 'order_id'=>$order_id), 0, 1, 'tid ASC');
        $order_old       = $order_old[0];
        $order_old       = unserialize($order_old['order_detail']);
        
        //配送地区
        $areaArr        = explode(':', $order_old['consignee']['area']);
        $areaArr        = $areaArr[1];
        $order_old['consignee']['ship_area']  = str_replace('/', ' ', $areaArr);
        
        #修改后[新]订单
        $order      = array();
        $oOrder     = app::get('ome')->model('orders');
        $order      = $oOrder->dump($order_id);
        
        //配送地区
        $areaArr        = explode(':', $order['consignee']['area']);
        $areaArr        = $areaArr[1];
        $order['consignee']['ship_area']  = str_replace('/', ' ', $areaArr);
        
        $item_list      = $oOrder->getItemBranchStore($order_id);//商品列表信息
        
        #数据对比
        $old_item_list      = $order_old['item_list'];
        $new_item_list      = array();

        foreach ($item_list as $key => $obj_item)
        {
            if($key == 'pkg')
            {
                foreach ($obj_item as $key_j => $items)
                {
                    //是否新增商品
                    if(empty($old_item_list['pkg'][$key_j]))
                    {
                        $obj_item[$key_j]['flag_add']     = 'true';
                        
                        //剔除删除的捆绑商品
                        $delete_i    = 0;
                        foreach ($items['order_items'] as $key_k => $item)
                        {
                            if($item['delete'] == 'true'){ $delete_i++;}
                        }
                        if($delete_i)
                        {
                            $obj_item[$key_j]['delete'] = 'true';
                            unset($obj_item[$key_j]);
                        }
                    }
                    //是否修改商品
                    else
                    {
                        $new_item_md5    = md5($items['obj_id'].$items['order_id'].$items['obj_type'].$items['obj_alias'].
                                             $items['shop_goods_id'].$items['goods_id'].$items['bn'].$items['name'].$items['price'].
                                             $items['amount'].$items['quantity'].$items['weight'].$items['score'].$items['pmt_price'].
                                             $items['sale_price'].$items['oid'].$items['is_oversold'].count($items['order_items']));
                        
                        $temp_val        = $old_item_list[$key][$key_j];
                        $old_item_md5    = md5($temp_val['obj_id'].$temp_val['order_id'].$temp_val['obj_type'].$temp_val['obj_alias'].
                                             $temp_val['shop_goods_id'].$temp_val['goods_id'].$temp_val['bn'].$temp_val['name'].$temp_val['price'].
                                             $temp_val['amount'].$temp_val['quantity'].$temp_val['weight'].$temp_val['score'].$temp_val['pmt_price'].
                                             $temp_val['sale_price'].$temp_val['oid'].$temp_val['is_oversold'].count($temp_val['order_items']));
                                             
                        if($new_item_md5 != $old_item_md5)
                        {
                            $obj_item[$key_j]['flag_edit']     = 'true';
                        }
                        
                        //是否为删除商品
                        $delete_i    = 0;
                        foreach ($items['order_items'] as $key_k => $item)
                        {
                            if($item['delete'] == 'true'){ $delete_i++;}
                        }
                        if($delete_i)
                        {
                            $obj_item[$key_j]['delete'] = 'true';
                            $old_item_list[$key][$key_j]      = $obj_item[$key_j];//替换原始订单中删除商品                         
                            unset($obj_item[$key_j]);//剔除删除的捆绑商品
                        }
                    }
                }
            }
            else
            {
                foreach ($obj_item as $key_j => $items)
                {
                    foreach ($items['order_items'] as $key_k => $item)
                    {
                        //新增商品
                        if(empty($old_item_list[$key][$key_j]['order_items'][$key_k]))
                        {
                            $obj_item[$key_j]['order_items'][$key_k]['flag_add']     = 'true';
                            
                            //剔除删除商品
                            if($item['delete'] == 'true')
                            {
                                unset($obj_item[$key_j]);//剔除删除商品
                            }
                        }
                        //是否删除
                        elseif($item['delete'] == 'true')
                        {
                           $old_item_list[$key][$key_j]['order_items'][$key_k]     = $item;//替换原始订单中删除商品
                           unset($obj_item[$key_j]['order_items'][$key_k]);//剔除删除商品
                        }
                        //是否修改
                        else
                        {
                            $new_item_md5    = md5($item['item_id'].$item['order_id'].$item['obj_id'].$item['shop_goods_id'].
                                             $item['product_id'].$item['shop_product_id'].$item['bn'].$item['name'].$item['cost'].
                                             $item['price'].$item['pmt_price'].$item['sale_price'].$item['amount'].$item['weight'].
                                             $item['nums'].$item['sendnum'].$item['delete'].$item['quantity']);
                           
                            $temp_val        = $old_item_list[$key][$key_j]['order_items'][$key_k];
                            $old_item_md5    = md5($temp_val['item_id'].$temp_val['order_id'].$temp_val['obj_id'].$temp_val['shop_goods_id'].
                                             $temp_val['product_id'].$temp_val['shop_product_id'].$temp_val['bn'].$temp_val['name'].$temp_val['cost'].
                                             $temp_val['price'].$temp_val['pmt_price'].$temp_val['sale_price'].$temp_val['amount'].$temp_val['weight'].
                                             $temp_val['nums'].$temp_val['sendnum'].$temp_val['delete'].$temp_val['quantity']);
                                             
                            if($new_item_md5 != $old_item_md5)
                            {
                                $obj_item[$key_j]['order_items'][$key_k]['flag_edit']      = 'true';
                                $old_item_list[$key][$key_j]['order_items'][$key_k]['flag_edit']          = 'true';
                            }
                        }
                    }
                }
            }
            
            $new_item_list[$key]    = $obj_item;
        }
        
        #格式化[删除的商品最后显示]
        $temp   = array();
        foreach ($old_item_list as $key => $obj_item)
        {
            if($key == 'pkg')
            {
                foreach ($obj_item as $key_j => $items)
                {
                    if($items['delete'] == 'true')
                    {
                        $temp['delete'][$key][$key_j]   = $items;
                    }
                    else
                    {
                        $temp['normal'][$key][$key_j]   = $items;
                    }
                }
            }
            else
            {
                foreach ($obj_item as $key_j => $items)
                {
                    foreach ($items['order_items'] as $key_k => $item)
                    {
                        if($item['delete'] == 'true')
                        {
                            $temp['delete'][$key][$key_j]   = $items;
                        }
                        else
                        {
                            $temp['normal'][$key][$key_j]   = $items;
                        }
                    }
                }
            }
        }
        
        $old_item_list  = array();
        if($temp['delete']['pkg'] && $temp['normal']['pkg'])
        {
            $old_item_list['pkg']   = array_merge($temp['normal']['pkg'], $temp['delete']['pkg']);
        }
        elseif($temp['normal']['pkg'])
        {
            $old_item_list['pkg']   = $temp['normal']['pkg'];
        }
        elseif($temp['delete']['pkg'])
        {
            $old_item_list['pkg']   = $temp['delete']['pkg'];
        }
        
        if($temp['delete']['goods'] && $temp['normal']['goods'])
        {
            $old_item_list['goods'] = array_merge($temp['normal']['goods'], $temp['delete']['goods']);
        }
        elseif($temp['normal']['goods'])
        {
            $old_item_list['goods'] = $temp['normal']['goods'];
        }
        elseif($temp['delete']['goods'])
        {
            $old_item_list['goods'] = $temp['delete']['goods'];
        }
        
        $order['item_list']     = $new_item_list;
        $order_old['item_list'] = $old_item_list;
        
        #订单备注mark_text_客户备注custom_mark
        $order['custom_mark'] = unserialize($order['custom_mark']);
        if ($order['custom_mark'])
        foreach ($order['custom_mark'] as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order['custom_mark'][$k]['op_time'] = $v['op_time'];
            }
        }
        $order['mark_text'] = unserialize($order['mark_text']);
        if ($order['mark_text'])
        foreach ($order['mark_text'] as $k=>$v){
            if (!strstr($v['op_time'], '-')){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order['mark_text'][$k]['op_time'] = $v['op_time'];
            }
        }
        
        #价格监控[获取订单复审配置]
        $setting_is_monitor = app::get('ome')->getConf('ome.order.is_monitor');//是否开启价格监控
        $cost_multiple      = app::get('ome')->getConf('ome.order.cost_multiple');//成本价倍数
        $sales_multiple     = app::get('ome')->getConf('ome.order.sales_multiple');//销售价倍数

        $monitor_flag       = array();
        $monitor_flag['cost_multiple']  = ($cost_multiple['flag'] && floatval($cost_multiple['value']) ? true : false);
        $monitor_flag['sales_multiple'] = ($sales_multiple['flag'] && floatval($sales_multiple['value']) ? true : false);
        
        if($setting_is_monitor == 'true' && $monitor_flag['cost_multiple'])
        {
            //原始订单
            $old_product_ids   = $old_product_nums = array();
            $temp   = $this->get_product_list($old_item_list, true);
            $old_product_ids   = $temp['ids'];
            $old_product_nums  = $temp['nums'];
            
            //现始订单
            $new_product_ids    = $new_product_nums = array();
            $temp   = $this->get_product_list($item_list);
            $new_product_ids   = $temp['ids'];
            $new_product_nums  = $temp['nums'];

            //成本价_销售价监控 
            $old_price_monitor  = $this->get_price_monitor($old_product_ids, $old_product_nums);
            $new_price_monitor  = $this->get_price_monitor($new_product_ids, $new_product_nums);
            
            //成本价_销售价倍数
            $old_price_monitor['cost']  = floatval($old_price_monitor['cost']) * floatval($cost_multiple['value']);
            $old_price_monitor['cost']  = round($old_price_monitor['cost'], 2);
            $new_price_monitor['cost']  = floatval($new_price_monitor['cost']) * floatval($cost_multiple['value']);
            $new_price_monitor['cost']  = round($new_price_monitor['cost'], 2);
            
            if($monitor_flag['sales_multiple'])
            {
                $old_price_monitor['price']    = floatval($old_price_monitor['price']) * floatval($sales_multiple['value']);
                $old_price_monitor['price']    = round($old_price_monitor['price'], 2);
                $new_price_monitor['price']    = floatval($new_price_monitor['price']) * floatval($sales_multiple['value']);
                $new_price_monitor['price']    = round($new_price_monitor['price'], 2);
            }
            
            //未设置成本价的商品货号
            if(!empty($old_price_monitor['invalid']))
            {
                $old_price_monitor['bn']    = $this->get_invalid_bn($old_price_monitor['invalid']);
                $old_price_monitor['bn']    = implode(',', $old_price_monitor['bn']);
            }
            if(!empty($new_price_monitor['invalid']))
            {
                $new_price_monitor['bn']    = $this->get_invalid_bn($new_price_monitor['invalid']);
                $new_price_monitor['bn']    = implode(',', $new_price_monitor['bn']);
            }
            
            //计算预计盈亏
            $order_profit['old']['value']   = ($order_old['total_amount'] - $old_price_monitor['cost']);
            $order_profit['new']['value']   = ($order['total_amount'] - $new_price_monitor['cost']);
            $order_profit['new']['msg']     = ($order_profit['new']['value'] < 0 ? '不通过' : '通过');
        }
        
        $datalist    = array();
        $datalist['order_profit']         = $order_profit;
        $datalist['old_price_monitor']    = $old_price_monitor;
        $datalist['new_price_monitor']    = $new_price_monitor;
        $datalist['monitor_flag']         = $monitor_flag;
        $datalist['setting_is_monitor']   = $setting_is_monitor;

        $datalist['order_old']    = $order_old;
        $datalist['order_new']    = $order;
        
        return $datalist;
    }
    /**
     +----------------------------------------------------------
     * 订单回滚[恢复复审前订单信息]
     +----------------------------------------------------------
     * @param   intval    $retrial_id 复审ID
     * @param   intval    $order_id 订单ID
     * return   Array
     +----------------------------------------------------------
     */
    public function rollback_order($retrial_id, $order_id)
    {
        #订单信息[现在的]
        $oOrder        = app::get('ome')->model('orders');
        $now_order     = $oOrder->dump($order_id);
        $now_order['item_list']    = $oOrder->getItemBranchStore($order_id);
        
        #订单快照[原始的]
        $order_old  = array();
        $oSnapshot  = app::get('ome')->model('order_retrial_snapshot');
        $order_old       = $oSnapshot->getList('*', array('retrial_id'=>$retrial_id, 'order_id'=>$order_id), 0, 1);
        $order_old       = $order_old[0];
        
        if(empty($order_old))
        {
            return false;
        }        
        $order_old       = unserialize($order_old['order_detail']);

        #数据对比
        $is_address_change  = false;//地址是否变更
        $is_order_change    = false;//是否需要修改
        
        //收货人信息
        $consignee  = array_diff_assoc($order_old['consignee'], $now_order['consignee']);
        if (!empty($consignee)){
            $is_address_change = true;
        }
        
        //修改订单折扣金额[是否编辑过商品]
        if (strval($order_old['discount']) != strval($now_order['discount'])){
            $is_order_change = true;
        }
        
        #订单信息[更新]
        $new_order      = array();
        
        $new_order['order_id']      = $order_id;
        $new_order['cost_item']     = $order_old['cost_item'];
        $new_order['pmt_goods']     = $order_old['pmt_goods'];
        $new_order['total_amount']  = $order_old['total_amount'];
        $new_order['discount']      = $order_old['discount'];
        $new_order['cur_amount']    = $order_old['cur_amount'];
        $new_order['old_amount']    = $order_old['total_amount'];
        $new_order['confirm']       = $order_old['confirm'];
        
        $new_order['consignee']     = $order_old['consignee'];
        $new_order['is_modify']     = $order_old['is_modify'];
        $new_order['shipping']['cost_shipping'] = $order_old['shipping']['cost_shipping'];
        
        //复审自定义设置[更新]
        $new_order['process_status']    = 'unconfirmed';
        $new_order['abnormal']          = 'false';
        $new_order['pause']             = 'false';
        $new_order['confirm']           = 'N';
        
        //还原订单确认组、确认人属性
        $new_order['group_id']      = $order_old['group_id'];
        $new_order['op_id']         = $order_old['op_id'];
        
        //更新order
        $oOrder->save($new_order);
        
        /**
         +----------------------------------------------------------
         | [更新]订单对象表、订单商品表
         +----------------------------------------------------------
         */
        $del_ids    = array();
        
        $sql        = "select oi.item_id,o.obj_id,o.obj_type,o.quantity,oi.bn,oi.product_id,oi.nums,oi.delete from `". DB_PREFIX ."ome_order_objects` o 
                        left join `". DB_PREFIX ."ome_order_items` oi on o.obj_id = oi.obj_id where o.order_id = '".$order_id."'";
        $obj_items  = kernel::database()->select($sql);
        
        if($obj_items)
        {
            foreach ($obj_items as $key => $val)
            {
                $item_id       = $val['item_id'];
                $obj_id        = $val['obj_id'];            
                $del_ids['objects'][$obj_id]   = $obj_id;
                $del_ids['items'][$item_id]    = $item_id;
            }
        }
        
        # [删除现在的]订单对象表、订单商品表
        if(!empty($del_ids['objects']))
        {
            $sql       = "DELETE FROM ". DB_PREFIX ."ome_order_objects WHERE obj_id in(".implode(',', $del_ids['objects']).")";
            kernel::database()->exec($sql);
        }
        if(!empty($del_ids['items']))
        {
            $sql       = "DELETE FROM ". DB_PREFIX ."ome_order_items WHERE item_id in(".implode(',', $del_ids['items']).")";
            kernel::database()->exec($sql);
        }

        #回滚订单快照[新增订单对象表、订单商品表 && 重新加载库存]
        $oObjects   = app::get('ome')->model('order_objects');
        $oItems     = app::get('ome')->model('order_items');

        foreach ($order_old['item_list'] as $key => $obj_item)
        {
            foreach ($obj_item as $key_j => $items)
            {
                //新增订单对象表
                $obj_data  = array(
                               'order_id' => $items['order_id'],
                               'obj_type' => $items['obj_type'],
                               'obj_alias' => $items['obj_alias'],
                               'shop_goods_id' => $items['shop_goods_id'],
                               'goods_id' => $items['goods_id'],
                               'bn' => $items['bn'],
                               'name' => $items['name'],
                               'price' => $items['price'],
                               'amount' => $items['amount'],
                               'quantity' => $items['quantity'],
                               'weight' => $items['weight'],
                               'score' => $items['score'],
                               'pmt_price' => $items['pmt_price'],
                               'sale_price' => $items['sale_price'],
                               'oid' => $items['oid'],
                               'is_oversold' => $items['is_oversold'],
                           );
                $obj_id    = $oObjects->insert($obj_data);
                
                foreach ($items['order_items'] as $key_k => $item)
                {                    
                    //新增订单商品表
                    $item_data  = array(
                               'order_id' => $item['order_id'],
                               'obj_id' => $obj_id,//关联

                               'shop_goods_id' => $item['shop_goods_id'],
                               'product_id' => $item['product_id'],
                               'shop_product_id' => $item['shop_product_id'],                    
                               'bn' => $item['bn'],                    
                               'name' => $item['name'],
                               'cost' => $item['cost'],
                               'price' => $item['price'],
                               'pmt_price' => $item['pmt_price'],
                               'sale_price' => $item['sale_price'],
                               'amount' => $item['amount'],
                               'weight' => $item['weight'],
                               'nums' => $item['nums'],
                               'sendnum' => $item['sendnum'],
                               'addon' => $item['addon'],
                               'item_type' => $item['item_type'],
                               'delete' => $item['delete'],
                           );
                    $item_id    = $oItems->insert($item_data);
                }
            }
        }
        
        #订单关联信息[更新]
        //调用公共方法更改订单支付状态(货到付款订单不进行支付状态的变更)
        if ($new_order['shipping']['is_cod'] != 'true') {
            kernel::single('ome_order_func')->update_order_pay_status($order_id, true, __CLASS__.'::'.__FUNCTION__);
        }

        //修改交易收货人信息API
        if ($is_address_change == true){
            if ($service_order = kernel::servicelist('service.order')){
                foreach($service_order as $object=>$instance){
                    if(method_exists($instance, 'update_shippinginfo')){
                        $instance->update_shippinginfo($order_id);
                    }
                }
            }
        }
        
        //订单编辑API
        if ($is_order_change == true){
            if ($service_order = kernel::servicelist('service.order')){
                foreach($service_order as $object=>$instance){
                    if(method_exists($instance, 'update_order')){
                        $instance->update_order($order_id);
                    }
                }
            }
        }

        //订单恢复状态同步
        if ($service_order = kernel::servicelist('service.order')){
            foreach($service_order as $object=>$instance){
                if(method_exists($instance, 'update_order_pause_status')){
                    $instance->update_order_pause_status($order_id, 'false');
                }
            }
        }
        
        # [复审的]订单快照保存
        $now_order['mark_text']     = unserialize($now_order['mark_text']);
        $now_order['custom_mark']   = unserialize($now_order['custom_mark']);
        $now_order['dateline']      = time();
        
        $data   = array('new_order_detail' => $now_order);
        $filter = array('retrial_id' => $retrial_id, 'order_id' => $order_id);
        $oSnapshot->update($data, $filter);
        
        # [更新]复审订单状态[不能使用'save']
        $sql        = "UPDATE ". DB_PREFIX ."ome_order_retrial SET order_id='".$order_id."', status='3' WHERE id='".$retrial_id."'";
        kernel::database()->exec($sql);
        
        # [更新]订单异常表状态
        $this->update_abnormal($order_id);
        
        # [恢复]到原始订单的冻结库存
        $this->recover_stock_freeze($retrial_id);
        
        //写日志
        $oOperation_log = app::get('ome')->model('operation_log');
        $oOperation_log->write_log('order_retrial@ome', $order_id, '通过复审订单快照回滚到上一次订单信息。');
        
        return true;
    }
    /**
     +----------------------------------------------------------
     * 增加复审订单
     +----------------------------------------------------------
     * @param   Array    $old_order 原始订单
     * return   Array
     +----------------------------------------------------------
     */
    public function add_retrial($old_order)
    {
        #获取订单复审配置
        $setting_is_retrial = app::get('ome')->getConf('ome.order.is_retrial');//开启复审
        $setting_retrial    = app::get('ome')->getConf('ome.order.retrial');//复审规则

        if($setting_is_retrial != 'true')
        {
           return false;//跳出
        }
        $op_id     = kernel::single('desktop_user')->get_id();
        
        #修改
        $is_address_change  = $old_order['is_address_change'];//配置地址是否变更
        $is_order_change    = $old_order['is_order_change'];//订单金额是否变更
        $is_goods_modify    = $old_order['is_goods_modify'];//商品是否变更
        
        #进入复审
        $retrial_flag   = 0;
        $retrial_msg    = array();
        if($setting_retrial['product'] && $is_goods_modify)
        {
            $retrial_flag++;
            $retrial_msg[]  = '商品数量';
        }
        if($setting_retrial['order'] && $is_order_change)
        {
            $retrial_flag++;
            $retrial_msg[]  = '订单金额';
        }
        if($setting_retrial['delivery'] && $is_address_change)
        {
            $retrial_flag++;
            $retrial_msg[]  = '配送信息';
        }
        if($retrial_flag == 0)
        {
            return false;//跳出复审
        }
        
        #历史订单
        $order_id       = $old_order['order_id'];
        $kefu_remarks               = $old_order['kefu_remarks'];//客服修改备注
        $old_order['mark_text']     = unserialize($old_order['mark_text']);
        $old_order['custom_mark']   = unserialize($old_order['custom_mark']);

        //改变订单状态为"异常状态"&"复审"
        $retrial_msg    = implode('、', $retrial_msg);
        $retrial_msg    = $kefu_remarks.' [修改了'.$retrial_msg.'] 订单进入待复审。';

        #获取订单是否未复审通过
        $oRetrial       = app::get('ome')->model('order_retrial');
        $retrial_row    = $oRetrial->getList('id, retrial_type', array('order_id'=>$order_id, 'status'=>'2'));
        $retrial_row    = $retrial_row[0];
        
        if(!empty($retrial_row))
        {
            # [更新]复审数据
            $retrial_arr       = array();
            $retrial_arr['id']             = $retrial_row['id'];
            $retrial_arr['retrial_type']   = 'normal';//审核类型
            $retrial_arr['status']         = 0;//待审核
            $retrial_arr['kefu_remarks']   = $kefu_remarks;
            $retrial_arr['lastdate']       = time();
            
            $sql        = "UPDATE ". DB_PREFIX ."ome_order_retrial SET retrial_type='normal', status='0', kefu_remarks='".$kefu_remarks."', 
                    lastdate='".time()."' WHERE id='".$retrial_row['id']."'";
            kernel::database()->exec($sql);
            $retrial_id     = $retrial_row['id'];
        }
        else
        {
            # [设置]为订单异常
            $oAbnormal      = app::get('ome')->model('abnormal');
            $abnoram_row    = $oAbnormal->getList('abnormal_id, abnormal_type_id, is_done', array('order_id'=>$order_id), 0, 1);
            $abnoram_row    = $abnoram_row[0];
            if(!empty($abnoram_row))
            {
                $abnoram_row['abnormal_type_id']    = '9';
                $abnoram_row['is_done']             = 'false';
                $abnoram_row['abnormal_memo']       = htmlspecialchars($retrial_msg);
                
                $abnormal_type_name = app::get('ome')->model('abnormal_type')->dump(array('type_id'=>$abnoram_row['abnormal_type_id']),'type_name');
                $abnoram_row['abnormal_type_name']  = $abnormal_type_name['type_name'];
                
                $op_name                = kernel::single('desktop_user')->get_name();
                $abnormal_memo[]        = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$abnoram_row['abnormal_memo']);
                $abnoram_row['abnormal_memo']   = serialize($abnormal_memo);
                
                $oAbnormal->save($abnoram_row);
            }
            else 
            {
                $oOrder         = app::get('ome')->model('orders');
                $abnormal_data  = array();
                
                $abnormal_data['order_id']         = $order_id;
                $abnormal_data['op_id']            = $old_order['op_id'];
                $abnormal_data['group_id']         = $old_order['group_id'];
                $abnormal_data['abnormal_type_id'] = 9;//订单异常类型
                $abnormal_data['is_done']          = 'false';
                $abnormal_data['abnormal_memo']    = $retrial_msg;
                
                $oOrder->set_abnormal($abnormal_data);
            }
            
            # [增加]复审数据
            $retrial_arr       = array();
            $retrial_arr['order_id']       = $order_id;
            $retrial_arr['order_bn']       = $old_order['order_bn'];
            $retrial_arr['retrial_type']   = 'normal';//审核类型
            $retrial_arr['status']         = 0;//待审核
            $retrial_arr['kefu_remarks']   = $kefu_remarks;
            $retrial_arr['dateline']       = time();
            $retrial_arr['lastdate']       = time();
            
            $retrial_arr['op_id']           = $op_id;//操作员

            $retrial_id = $oRetrial->insert($retrial_arr);
            
            # [原订单]库存冻结保存
            $record = $this->record_stock_freeze($old_order['item_list'], $retrial_id, true);
        }
        
        # [快照]存储原始订单信息_log
        $oSnapshot  = app::get('ome')->model('order_retrial_snapshot');
        $data   = array(
            'retrial_id' => $retrial_id,
            'order_id' => $order_id,
            'order_detail' => $old_order,
            'dateline' => time(),
        );
        $oSnapshot->save($data);
        
        #如有退款申请单，设置为disabled='true'
        $oRefund    = app::get('ome')->model('refund_apply');
        $refunddata = $oRefund->getList('apply_id', array('order_id'=>$order_id, 'status'=>'0'), 0, 1);
        if(!empty($refunddata[0]['apply_id']))
        {
            $oRefund->update(array('disabled'=>'true'), array('apply_id'=>$refunddata[0]['apply_id']));
        }
        
        //写日志
        $oOperation_log     = app::get('ome')->model('operation_log');
        $oOperation_log->write_log('order_retrial@ome', $order_id, $retrial_msg);
        
        return $retrial_id;
    }
    /**
     +----------------------------------------------------------
     * 保存库存冻结记录[复审订单编辑]
     +----------------------------------------------------------
     * @param   Array    $productList
     * @param   intval   $retrial_id[复审id]
     * @param   boolean  $first[是否首次记录]
     * return   Array
     +----------------------------------------------------------
     */
    public function record_stock_freeze($item_list, $retrial_id, $first=false)
    {
        //订单信息
        $sql    = "SELECT b.order_id, b.shop_id, b.order_bn FROM sdb_ome_order_retrial AS a LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.id=". $retrial_id;
        $orderRow = $this->db->selectrow($sql);
        $order_id   = $orderRow['order_id'];
        $shop_id    = $orderRow['shop_id'];
        
        //订单明细
        $product_list  = array();
        foreach ($item_list as $key => $obj_item)
        {
            foreach ($obj_item as $key_j => $items)
            {
                foreach ($items['order_items'] as $key_k => $item)
                {
                    $product_id        = $item['product_id'];
                    $goods_id          = $items['goods_id'];
                    $product_list[$product_id]  = array(
                                                'retrial_id'=>$retrial_id,
                                                'order_id'=>$item['order_id'],
                                                'edit_num'=>0,
                                                'is_old'=>($first ? 'true' : 'false'),
                                                'status'=>($item['delete']=='true' ? 'delete' : 'normal'),
                                                'product_id'=>$product_id,
                                                'goods_id'=>$goods_id,
                                                'bn'=>$item['bn'],
                                                'buy_num'=>$item['quantity'],
                                                'up_num'=>0,
                                                'diff_num'=>0,
                                                'add_num'=>0,
                                                'dateline'=>time(),
                                                'delete'=>$item['delete'],
                                            );
                }
            }
        }
        if(empty($product_list))
        {
            return false;
        }        
        $oFreeze    = app::get('ome')->model('order_retrial_store_freeze');
        
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        
        #原始订单的冻结库存保存
        if($first)
        {
            foreach ($product_list as $key => $val)
            {
                $oFreeze->insert($val);
            }
            
            return true;
        }
        
        #上次修改的标记号
        $filter       = array('retrial_id'=>$retrial_id);
        $freeze_row   = $oFreeze->getList('sid, edit_num', $filter, 0, 1, 'edit_num DESC');
        $edit_num     = intval($freeze_row[0]['edit_num']);
        
        #获取上次订单详细
        $history_freeze = array();
        if($edit_num > 0)
        {
            $result     = $this->up_stock_freeze($retrial_id);
            
            $history_freeze = $result['buy_num'];
            $last_product   = $result['data'];
        }

        #原始订单购买商品详情
        $filter['edit_num'] = 0;
        $temp               = $oFreeze->getList('*', $filter, 0, -1);
        $freeze_list        = array();
        
        foreach ($temp as $key => $val)
        {
            $product_id                 = $val['product_id'];
            $freeze_list[$product_id]   = $val;
            
            if(empty($history_freeze))
            {
                $history_freeze[$product_id]   = $val['buy_num'];//购买数量
            }
        }
        
        #本次购买商品详情
        uasort($product_list, [kernel::single('console_iostockorder'), 'cmp_productid']);
        $branchBatchList = [];
        foreach($product_list as $key => $val)
        {
            $val['edit_num']    = 1;//修改的标记号
            $val['is_old']      = ($freeze_list[$key] ? 'true' : 'false');
            $val['is_del']      = ($val['delete'] == 'true' ? 'true' : 'false');
            $val['up_num']      = ($history_freeze[$key] ? $history_freeze[$key] : 0);//上一次购买数量
            
            if($val['delete'] == 'true' && $freeze_list[$key])
            {
                $val['diff_num']    = ($val['buy_num'] - $freeze_list[$key]['buy_num']);
                $val['status']      = 'delete';
            }
            elseif($freeze_list[$key] && ($freeze_list[$key]['buy_num'] != $val['buy_num']))
            {
                $val['diff_num']    = ($val['buy_num'] - $freeze_list[$key]['buy_num']);
                $val['status']      = 'edit';
            }
            elseif(empty($freeze_list[$key]))
            {
                $val['diff_num']    = $val['buy_num'];
                $val['status']      = 'add';                
            }
            
            //增加_删除的原订单库存
            if($val['is_old'] == 'true')
            {
                $product_id    = $val['product_id'];
                $goods_id      = $val['goods_id'];
                
                $old_buy_num   = intval($freeze_list[$key]['buy_num']);//原订单购买量
                $last_buy_num  = intval($val['up_num']);//上次购买量
                $now_buy_num   = intval($val['buy_num']);//本次购买量

                $diff_buy_num  = $old_buy_num - $now_buy_num;
                $diff_up_num   = $old_buy_num - $last_buy_num;

                
                if($val['status'] == 'delete')
                {
                    #上一次为删除状态
                    if($last_product[$key]['status'] == 'delete')
                    {
                        
                    }
                    elseif($last_buy_num < $old_buy_num)
                    {
                        $freezeData = [];
                        $freezeData['bm_id'] = $product_id;
                        $freezeData['sm_id'] = $goods_id;
                        $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                        $freezeData['bill_type'] = 0;
                        $freezeData['obj_id'] = $order_id;
                        $freezeData['shop_id'] = $shop_id;
                        $freezeData['branch_id'] = 0;
                        $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                        $freezeData['num'] = $last_buy_num;
                        $freezeData['obj_bn'] = $orderRow['order_bn'];
                        $branchBatchList['+'][] = $freezeData;
                    }
                    else
                    {
                        $freezeData = [];
                        $freezeData['bm_id'] = $product_id;
                        $freezeData['sm_id'] = $goods_id;
                        $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                        $freezeData['bill_type'] = 0;
                        $freezeData['obj_id'] = $order_id;
                        $freezeData['shop_id'] = $shop_id;
                        $freezeData['branch_id'] = 0;
                        $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                        $freezeData['num'] = $old_buy_num;
                        $freezeData['obj_bn'] = $orderRow['order_bn'];
                        $branchBatchList['+'][] = $freezeData;
                    }
                }
                elseif($val['status'] == 'edit')
                {
                    #上一次为删除状态
                    if($last_product[$key]['status'] == 'delete')
                    {
                        //减去上次删除时，强制增加的
                        $branchBatchList['-'][] = [
                            'bm_id'     =>  $product_id,
                            'sm_id'     =>  $goods_id,
                            'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                            'bill_type' =>  0,
                            'obj_id'    =>  $order_id,
                            'branch_id' =>  '',
                            'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                            'num'       =>  $old_buy_num,
                        ];                        
                        //
                        if($diff_buy_num > 0)
                        {                        
                            $freezeData = [];
                            $freezeData['bm_id'] = $product_id;
                            $freezeData['sm_id'] = $goods_id;
                            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                            $freezeData['bill_type'] = 0;
                            $freezeData['obj_id'] = $order_id;
                            $freezeData['shop_id'] = $shop_id;
                            $freezeData['branch_id'] = 0;
                            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                            $freezeData['num'] = $diff_buy_num;
                            $freezeData['obj_bn'] = $orderRow['order_bn'];
                            $branchBatchList['+'][] = $freezeData;
                        }
                    }
                    else
                    {
                        if($diff_buy_num > 0 && ($last_product[$key]['buy_num'] != $val['buy_num']))
                        {
                            $freezeData = [];
                            $freezeData['bm_id'] = $product_id;
                            $freezeData['sm_id'] = $goods_id;
                            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                            $freezeData['bill_type'] = 0;
                            $freezeData['obj_id'] = $order_id;
                            $freezeData['shop_id'] = $shop_id;
                            $freezeData['branch_id'] = 0;
                            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                            $freezeData['num'] = abs($diff_buy_num);
                            $freezeData['obj_bn'] = $orderRow['order_bn'];
                            $branchBatchList['+'][] = $freezeData;
                        }
                        
                        //减去上一次强制增加的库存
                        if($diff_up_num > 0)
                        {
                            $branchBatchList['-'][] = [
                                'bm_id'     =>  $product_id,
                                'sm_id'     =>  $goods_id,
                                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                                'bill_type' =>  0,
                                'obj_id'    =>  $order_id,
                                'branch_id' =>  '',
                                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                                'num'       =>  $diff_up_num,
                            ];
                        }
                    }
                }
            }
            
            $product_list[$key]     = $val;
        }

        $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
        $basicMStockFreezeLib->unfreezeBatch($branchBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        
        #保存冻结库存[复审订单商品]
        foreach ($product_list as $key => $val)
        {
            $oFreeze->insert($val);
        }
        
        return true;
    }
    /**
     +----------------------------------------------------------
     * 获取上次订单的冻结库存[并删除上次记录]
     +----------------------------------------------------------
     * @param   intval    $retrial_id
     * @param   boolean   $rollback 回滚订单
     * return   
     +----------------------------------------------------------
     */
    public function up_stock_freeze($retrial_id)
    {
        $oFreeze    = app::get('ome')->model('order_retrial_store_freeze');
        $filter     = array('retrial_id'=>$retrial_id, 'edit_num|than'=>'0');
        
        #计算[冻结库存]
        $freeze_list           = $oFreeze->getList('*', $filter, 0, -1);
        if(empty($freeze_list))
        {
            return false;
        }
        
        #记录上次购买量
        $result     = array();
        foreach ($freeze_list as $key => $val)
        {
            $product_id   = $val['product_id'];
            
            $result['buy_num'][$product_id] = $val['buy_num'];
            $result['data'][$product_id]    = $val;
        }
        
        #删除上一次库存记录
        $del_sql   = "DELETE FROM ". DB_PREFIX ."ome_order_retrial_store_freeze WHERE retrial_id ='".$retrial_id."' AND edit_num > 0";
        $this->db->exec($del_sql);
        
        return $result;
    }
    /**
     +----------------------------------------------------------
     * [恢复到原始订单]还原冻结库存
     +----------------------------------------------------------
     * @param   intval    $retrial_id
     * return   Array
     +----------------------------------------------------------
     */
    public function recover_stock_freeze($retrial_id)
    {
        $oFreeze    = app::get('ome')->model('order_retrial_store_freeze');
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        
        //订单信息
        $sql    = "SELECT b.order_id, b.shop_id FROM sdb_ome_order_retrial AS a LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.id=". $retrial_id;
        $orderRow = $this->db->selectrow($sql);
        $order_id   = $orderRow['order_id'];
        $shop_id    = $orderRow['shop_id'];
        
        //显示的字段
        $fields     = 'product_id, edit_num, status, is_old, is_del, buy_num, up_num, add_num';
        
        #编辑后的商品冻结库存
        $filter         = array('retrial_id'=>$retrial_id, 'edit_num|than'=>'0');
        $freeze_list    = $oFreeze->getList($fields, $filter, 0, -1);
        if(empty($freeze_list))
        {
            return false;
        }
        
        #原订单的商品冻结库存
        $filter         = array('retrial_id'=>$retrial_id, 'edit_num'=>'0');
        $temp           = $oFreeze->getList($fields, $filter, 0, -1);
        $old_product    = array();
        foreach($temp as $key => $val)
        {
            $product_id    = $val['product_id'];
            $old_product[$product_id]  = $val;
        }
        
        #计算[冻结库存]
        $num            = 0;
        uasort($freeze_list, [kernel::single('console_iostockorder'), 'cmp_productid']);
        $branchBatchList = [];
        foreach ($freeze_list as $key => $val)
        {
            $product_id    = $val['product_id'];
            $goods_id      = 0; // 复审订单没有存goods_id或者order_objects的信息
            $buy_num       = intval($val['buy_num']);
            $up_num        = intval($val['up_num']);
            $old_num       = intval($old_product[$product_id]['buy_num']);//原商品购买数量
            
            if($val['is_old'] == 'true')
            {
                $num      = $buy_num - $old_num;
                if($val['status'] == 'edit' && $num > 0)
                {
                    $branchBatchList[] = [
                        'bm_id'     =>  $product_id,
                        'sm_id'     =>  $goods_id,
                        'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                        'bill_type' =>  0,
                        'obj_id'    =>  $order_id,
                        'branch_id' =>  '',
                        'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                        'num'       =>  abs($num),
                    ];
                }
            }
            else
            {
                if($val['is_del'] == 'false' && $buy_num > 0)
                {
                    $branchBatchList[] = [
                        'bm_id'     =>  $product_id,
                        'sm_id'     =>  $goods_id,
                        'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                        'bill_type' =>  0,
                        'obj_id'    =>  $order_id,
                        'branch_id' =>  '',
                        'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                        'num'       =>  abs($buy_num),
                    ];
                }
            }
        }

        $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        $oFreeze->delete(array('retrial_id'=>$retrial_id));//清空库存记录
        
        return true;
    }
    /**
     +----------------------------------------------------------
     * [审核通过]使用现在的冻结库存
     +----------------------------------------------------------
     * @param   intval    $retrial_id
     * return   Array
     +----------------------------------------------------------
     */
    public function confirm_stock_freeze($retrial_id)
    {
        $oFreeze    = app::get('ome')->model('order_retrial_store_freeze');
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        
        //订单信息
        $sql    = "SELECT b.order_id, b.shop_id FROM sdb_ome_order_retrial AS a LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.id=". $retrial_id;
        $orderRow = $this->db->selectrow($sql);
        $order_id   = $orderRow['order_id'];
        $shop_id    = $orderRow['shop_id'];
        
        //显示的字段
        $fields     = 'product_id, edit_num, status, is_old, is_del, buy_num, up_num, add_num';
        
        #编辑后的商品冻结库存
        $filter         = array('retrial_id'=>$retrial_id, 'edit_num|than'=>'0');
        $freeze_list    = $oFreeze->getList($fields, $filter, 0, -1);
        if(empty($freeze_list))
        {
            return false;
        }
        
        #原订单的商品冻结库存
        $filter         = array('retrial_id'=>$retrial_id, 'edit_num'=>'0');
        $temp           = $oFreeze->getList($fields, $filter, 0, -1);
        $old_product    = array();
        foreach($temp as $key => $val)
        {
            $product_id    = $val['product_id'];
            $old_product[$product_id]  = $val;
        }
        
        #计算[冻结库存]
        $num            = 0;
        uasort($freeze_list, [kernel::single('console_iostockorder'), 'cmp_productid']);
        $branchBatchList = [];
        foreach ($freeze_list as $key => $val)
        {
            $product_id    = $val['product_id'];
            $goods_id      = 0; // 复审订单没有存goods_id或者order_objects的信息
            $buy_num       = intval($val['buy_num']);
            $up_num        = intval($val['up_num']);
            $old_num       = intval($old_product[$product_id]['buy_num']);//原商品购买数量
            
            if($val['is_old'] == 'true')
            {
                $num      = $buy_num - $old_num;
                if($val['status'] == 'edit' && $num < 0)
                {
                    $branchBatchList[] = [
                        'bm_id'     =>  $product_id,
                        'sm_id'     =>  $goods_id,
                        'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                        'bill_type' =>  0,
                        'obj_id'    =>  $order_id,
                        'branch_id' =>  '',
                        'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                        'num'       =>  abs($num),
                    ];
                }
                elseif($val['status'] == 'delete' && $old_num > 0)
                {
                    $branchBatchList[] = [
                        'bm_id'     =>  $product_id,
                        'sm_id'     =>  $goods_id,
                        'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                        'bill_type' =>  0,
                        'obj_id'    =>  $order_id,
                        'branch_id' =>  '',
                        'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                        'num'       =>  abs($old_num),
                    ];
                }
            }
        }

        $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        $oFreeze->delete(array('retrial_id'=>$retrial_id));//清空库存记录
        
        return true;
    }
    /**
     * 订单取消后自动结束待复审流程
     *
     * 全额退款、平台取消和后台人工取消最终都会进入订单统一取消流程。
     * 订单取消成功后，待复审记录不能继续保留为可操作状态，否则订单仍会出现在
     * 待复审列表中。这里只维护复审终态、异常状态和复审库存跟踪记录；订单取消
     * 主流程已经按当前订单明细释放实际冻结库存，因此不能再调用复审通过或回滚
     * 的库存处理方法，避免重复释放库存。
     *
     * @param int $order_id 订单ID
     * @return bool
     */
    public function finish_by_order_cancel($order_id)
    {
        $order_id = intval($order_id);
        if($order_id <= 0)
        {
            return false;
        }

        // 只有订单已经成功取消时才结束复审，避免取消失败时提前关闭复审流程。
        $order = $this->db->selectrow("SELECT order_id FROM ". DB_PREFIX ."ome_orders WHERE order_id='".$order_id."' AND (process_status='cancel' OR status='dead')");
        if(empty($order))
        {
            return false;
        }

        $retrial_list = $this->db->select("SELECT id, remarks FROM ". DB_PREFIX ."ome_order_retrial WHERE order_id='".$order_id."' AND status='0'");
        if(empty($retrial_list))
        {
            return true;
        }

        $lastdate = time();
        $system_remarks = '订单已取消，系统自动退出复审流程。';
        $retrial_ids = array();
        foreach($retrial_list as $retrial)
        {
            $retrial_id = intval($retrial['id']);
            $remarks = trim((string) $retrial['remarks']);
            if(strpos($remarks, $system_remarks) === false)
            {
                $remarks = $remarks ? $remarks.' '.$system_remarks : $system_remarks;
            }

            $sql = "UPDATE ". DB_PREFIX ."ome_order_retrial SET status='4', remarks=".$this->db->quote($remarks).", lastdate='".$lastdate."' WHERE id='".$retrial_id."' AND status='0'";
            $this->db->exec($sql);
            if($this->db->affect_row() > 0)
            {
                $retrial_ids[] = $retrial_id;
            }
        }

        // 并发情况下记录可能已被人工复审处理，不再重复执行取消收口。
        if(empty($retrial_ids))
        {
            return true;
        }

        // 订单已经是取消终态，同时清除复审产生的异常、暂停标记。
        $this->db->exec("UPDATE ". DB_PREFIX ."ome_orders SET abnormal='false', pause='false' WHERE order_id='".$order_id."' AND (process_status='cancel' OR status='dead')");
        $this->update_abnormal($order_id);

        // 这里只删除复审库存计算记录，不再调整实际库存冻结，避免与订单取消重复释放。
        if($retrial_ids)
        {
            $this->db->exec("DELETE FROM ". DB_PREFIX ."ome_order_retrial_store_freeze WHERE retrial_id IN (".implode(',', $retrial_ids).")");
        }

        $oOperation_log = app::get('ome')->model('operation_log');
        $oOperation_log->write_log('order_retrial@ome', $order_id, $system_remarks);

        return true;
    }
    /**
     +----------------------------------------------------------
     * 更新订单异常表[ome_abnormal]状态
     +----------------------------------------------------------
     * @param   intval    $order_id 订单ID
     * return   Array
     +----------------------------------------------------------
     */
    function update_abnormal($order_id)
    {
        $oAbnormal = app::get('ome')->model('abnormal');
        $result    = $oAbnormal->getList('abnormal_id, abnormal_type_id, is_done', array('order_id'=>$order_id), 0, 1);
        $result    = $result[0];
        
        if(!empty($result))
        {
            if($result['abnormal_type_id'] == '9')
            {
                $result['is_done']    = 'true';//已处理
            }
            else
            {
                $result['is_done']    = $result['is_done'];//还原复审前的异常状态
            }
            $oAbnormal->save($result);
            
            return $result['abnormal_id'];
        }
        
        return false;
    }
    /**
     +----------------------------------------------------------
     * [监控]商品列表的成本价格
     +----------------------------------------------------------
     * @param   Array    $item_list
     * @param   Array    $price_monitor
     * @param   Array    $monitor_flag
     * return   Array
     +----------------------------------------------------------
     */
    public function monitor_item_list($item_list, $price_monitor, $monitor_flag)
    {
        foreach ($item_list as $key => $obj_item)
        {
            foreach ($obj_item as $key_j => $items)
            {
                $count_cost       = 0;
                $count_price      = 0;
                foreach ($items['order_items'] as $key_k => $item)
                {
                    $item_id     = $item['item_id'];
                    $product_id  = $item['product_id'];
                    
                    #成本价[监控倍数]
                    if($monitor_flag['cost_multiple'])
                    {
                        $price_monitor[$product_id]['cost'] = floatval($price_monitor[$product_id]['cost']) * floatval($monitor_flag['cost_value']);
                        $price_monitor[$product_id]['cost'] = round($price_monitor[$product_id]['cost'], 2);
                    }
                    #销售价[监控倍数]
                    if($monitor_flag['sales_multiple'])
                    {
                        $price_monitor[$product_id]['price']   = floatval($price_monitor[$product_id]['price']) * floatval($monitor_flag['sales_value']);
                        $price_monitor[$product_id]['price']   = round($price_monitor[$product_id]['price'], 2);
                    }
                    
                    $item_list[$key][$key_j]['order_items'][$key_k]['product_cost']  = $price_monitor[$product_id]['cost'];
                    $item_list[$key][$key_j]['order_items'][$key_k]['product_price'] = $price_monitor[$product_id]['price'];
                    $item_list[$key][$key_j]['order_items'][$key_k]['flag_cost']     = 'true';
                    $item_list[$key][$key_j]['order_items'][$key_k]['flag_price']    = 'true';
                    
                    #成本价
                    if($price_monitor[$product_id]['cost'] <= 0)
                    {
                        $item_list[$key][$key_j]['order_items'][$key_k]['flag_cost']     = 'false';//未设置成本价
                    }
                    else
                    {
                        $count_cost += $price_monitor[$product_id]['cost'];
                    }
                    
                    #销售价
                    if($price_monitor[$product_id]['price'] <= 0)
                    {
                        $item_list[$key][$key_j]['order_items'][$key_k]['flag_price']   = 'false';//未设置销售价
                    }
                    else
                    {
                        $count_price += $price_monitor[$product_id]['price'];
                    }
                }

                $obj_id   = $item['obj_id'];
                $item_list[$key][$key_j]['product_cost']  = $count_cost;
                $item_list[$key][$key_j]['product_price'] = $count_price;
            }
        }
        
        return $item_list;
    }
    /**
     +----------------------------------------------------------
     * [获取]订单信息&&价格监控
     +----------------------------------------------------------
     * @param   intval    $order_id
     * return   Array
     +----------------------------------------------------------
     */
    public function get_order_monitor($order_id)
    {
        #订单详细信息
        $order      = array();
        $oOrder     = app::get('ome')->model('orders');
        $order      = $oOrder->dump($order_id);
        
        #配送地区
        $areaArr        = explode(':', $order['consignee']['area']);
        $areaArr        = $areaArr[1];
        $order['consignee']['ship_area']  = str_replace('/', ' ', $areaArr);

        #订单备注mark_text&&客户备注custom_mark
        $order['custom_mark'] = unserialize($order['custom_mark']);
        if ($order['custom_mark'])
        foreach ($order['custom_mark'] as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order['custom_mark'][$k]['op_time'] = $v['op_time'];
            }
        }
        $order['mark_text'] = unserialize($order['mark_text']);
        if ($order['mark_text'])
        foreach ($order['mark_text'] as $k=>$v){
            if (!strstr($v['op_time'], '-')){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $order['mark_text'][$k]['op_time'] = $v['op_time'];
            }
        }
        
        #商品列表信息
        $item_list      = $oOrder->getItemBranchStore($order_id);

        #价格监控[获取订单复审配置]
        $setting_is_monitor = app::get('ome')->getConf('ome.order.is_monitor');//是否开启价格监控
        $cost_multiple      = app::get('ome')->getConf('ome.order.cost_multiple');//成本价倍数
        $sales_multiple     = app::get('ome')->getConf('ome.order.sales_multiple');//销售价倍数

        $monitor_flag       = array();
        $monitor_flag['cost_multiple']  = ($cost_multiple['flag'] && floatval($cost_multiple['value']) ? true : false);
        $monitor_flag['sales_multiple'] = ($sales_multiple['flag'] && floatval($sales_multiple['value']) ? true : false);

        if($setting_is_monitor == 'true' && $monitor_flag['cost_multiple'])
        {
            $product_list   = $this->get_product_list($item_list, true);
            $product_ids    = $product_list['ids'];
            $product_nums   = $product_list['nums'];
            
            //成本价_销售价监控
            $price_monitor  = $this->get_price_monitor($product_ids, $product_nums, $item_list);
            $price_monitor['cost']  = floatval($price_monitor['cost']) * floatval($cost_multiple['value']);
            $price_monitor['cost']  = round($price_monitor['cost'], 2);
            
            //未设置成本价的商品货号
            if(!empty($price_monitor['invalid']))
            {
                $price_monitor['bn']    = $this->get_invalid_bn($price_monitor['invalid']);
                $price_monitor['bn']    = implode(',', $price_monitor['bn']);
            }
            
            //计算预计盈亏
            $order_profit['value']   = ($order['total_amount'] - $price_monitor['cost']);
            $order_profit['msg']     = ($order_profit['value'] < 0 ? '不通过' : '通过');
            
            //[监控]商品列表详细价格
            $monitor_flag['cost_value']     = floatval($cost_multiple['value']);
            $monitor_flag['sales_value']    = floatval($sales_multiple['value']);
            $item_list      = $this->monitor_item_list($item_list, $price_monitor['detail'], $monitor_flag);
        }
        $order['item_list'] = $item_list;

        $datalist    = array();
        $datalist['order_profit']       = $order_profit;
        $datalist['price_monitor']      = $price_monitor;
        $datalist['monitor_flag']       = $monitor_flag;
        $datalist['setting_is_monitor'] = $setting_is_monitor;
        $datalist['order']  = $order;

        return $datalist;
    }
    /**
     +----------------------------------------------------------
     * [订单编辑时]进行商品价格监控
     +----------------------------------------------------------
     * @param   intval    $product_ids 商品ID集
     * return   Array
     +----------------------------------------------------------
     */
    public function get_product_monitor($product_list, $total_amount=0)
    {
        #价格监控[获取订单复审配置]
        $setting_is_monitor = app::get('ome')->getConf('ome.order.is_monitor');//是否开启价格监控
        $cost_multiple      = app::get('ome')->getConf('ome.order.cost_multiple');//成本价倍数
        $sales_multiple     = app::get('ome')->getConf('ome.order.sales_multiple');//销售价倍数

        $monitor_flag       = array();
        $monitor_flag['cost_multiple']  = ($cost_multiple['flag'] && floatval($cost_multiple['value']) ? true : false);
        $monitor_flag['sales_multiple'] = ($sales_multiple['flag'] && floatval($sales_multiple['value']) ? true : false);
        
        if($setting_is_monitor == 'true' && $monitor_flag['cost_multiple'])
        {
            #成本价_销售价监控
            $price_monitor  = $this->get_price_monitor($product_list['ids'], $product_list['nums']);
            $price_monitor['cost']  = floatval($price_monitor['cost']) * floatval($cost_multiple['value']);
            $price_monitor['cost']  = round($price_monitor['cost'], 2);
            
            #计算预计盈亏
            $price_monitor['profit']    = ($total_amount - $price_monitor['cost']);
            $price_monitor['message']   = ($price_monitor['profit'] < 0 ? '不通过' : '通过');
            
            #未设置成本价的商品货号
            if(!empty($price_monitor['invalid']))
            {
                $price_monitor['bn']    = $this->get_invalid_bn($price_monitor['invalid']);
                $price_monitor['bn']    = implode('、', (array) $price_monitor['bn']);
            }
            
            return $price_monitor;
        }
        
        return false;
    }
    /**
     +----------------------------------------------------------
     * 复审订单关联的退款单操作sdb_ome_refund_apply
     +----------------------------------------------------------
     * @param   intval    $order_id 订单ID
     * return   Array
     +----------------------------------------------------------
     */
    function oper_ome_refund_apply($order_id, $verify='')
    {
        $oOrder     = app::get('ome')->model('orders');
        $detail     = $oOrder->getList('order_id, order_bn, pay_status', array('order_id'=>$order_id), 0, 1);
        $detail     = $detail[0];
        if($detail['pay_status'] != '6')
        {
            return false;
        }
        
        #查询是否有退款单
        $oRefund    = app::get('ome')->model('refund_apply');
        $oRow       = $oRefund->getList('apply_id, refund_apply_bn, status, disabled', array('order_id'=>$order_id), 0, 1);
        $oRow       = $oRow[0];
        if($oRow['status'] != '0' && $oRow['disabled'] != 'true')
        {
            return false;//不是复审订单产生的退款单，则跳出
        }
        
        #审核通过后更新退款单disabled状态，否则为删除退款单
        $filter     = array('apply_id' => $oRow['apply_id']);
        $msg        = '';
        if($verify == 'success')
        {            
            $oRefund->update(array('status'=>'0', 'disabled'=>'false'), $filter);//设为disabled有效状态
            $oOrder->update(array('pause'=>'true'), array('order_id'=>$order_id));//退款申请中 将订单置为暂停
            
            $msg    = '订单复审通过，更新退款单号'.$oRow['refund_apply_bn'].'为有效状态；';
        }
        else
        {
            $oRefund->delete($filter);//删除退款单
            
            $msg    = ($verify == 'rollback' ? '订单复审回滚' : '订单复审未通过');
            $msg    .= '，删除退款单号'.$oRow['refund_apply_bn'];
        }
        
        //写日志
        $oOperation_log     = app::get('ome')->model('operation_log');
        $oOperation_log->write_log('order_retrial@ome', $order_id, $msg);
        
        return true;
    }
}
?>
