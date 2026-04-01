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

class ome_mdl_reship_items extends dbeav_model{
    /*
     * 统计某订单货号生成退款单数
     *
     * @param int $order_id ,varchar $bn
     *
     * @return int
     */
    function Get_refund_count($order_id,$bn,$reship_id='',$item_id='')
    {
       
        $sql = "SELECT sum(nums) as count FROM sdb_ome_order_items WHERE  order_id='".$order_id."' AND bn='".$bn."' AND `delete`='false' ";
        if ($item_id){
            $sql.=" AND item_id=".$item_id;
        }
        $order=$this->db->selectrow($sql);

        $sql = "SELECT sum(i.normal_num) as normal_count,sum(i.defective_num) as defective_count FROM sdb_ome_reship as r left join sdb_ome_reship_items as i on r.reship_id=i.reship_id WHERE i.return_type='return' AND r.is_check in ('11','7') AND r.order_id='".$order_id."' AND i.bn='".$bn."'";
        if ($item_id){
            $sql.=" AND order_item_id=".$item_id;
        }
        if($reship_id != ''){
            $sql .= ' AND r.reship_id!='.$reship_id;
        }//已收获的取入库数量
        $refund = $this->db->selectrow($sql);

        $sql1 = "SELECT sum(i.num) as nums FROM sdb_ome_reship as r left join sdb_ome_reship_items as i on r.reship_id=i.reship_id WHERE i.return_type='return' AND r.is_check not in ('5','7','11') AND r.order_id='".$order_id."' AND i.bn='".$bn."' ";//未入仓库的取申请数量
        if($item_id){
            $sql1.=" AND order_item_id=".$item_id;
        }
        if($reship_id != ''){
            $sql1 .= ' AND r.reship_id!='.$reship_id;
        }
        $refund1 = $this->db->selectrow($sql1);

        return $order['count']-$refund['normal_count']-$refund['defective_count']-$refund1['nums'];
    }

    /*
    *  根据货号获取对应仓库和ID
    *
    * @param int $order_id ,varchar $bn
    *
    * * return array
    */
     function getBranchCodeByBnAndOd($bn,$orderid)
     {
         $oBranch=$this->app->model('branch');
         $sqlstr = "SELECT s.branch_id,s.delivery_id FROM sdb_ome_delivery as s left join sdb_ome_delivery_items sdi on sdi.delivery_id = s.delivery_id left join sdb_ome_delivery_order as o on o.delivery_id=s.delivery_id WHERE o.order_id='$orderid' AND sdi.bn='$bn' AND s.type='normal' AND s.status='succ'";

        $branch=$this->db->select($sqlstr);
        
        $branch_ids = array();
        $t_branch = $branch;
        foreach($t_branch as $k=>$v){
            if(!in_array($v['branch_id'],$branch_ids)){
                $branchs = $oBranch->dump($v['branch_id'],'name,branch_id');
                $branch[$k]['branch_name']=$branchs['name'];
                $branch_ids[] = $v['branch_id'];
            }else{
                unset($branch[$k]);
            }
        }
        
        return $branch;
     }

    /*
    *  统计退换货明细中，退入商品和换出商品的个数
    *
    * @param int $reship_id
    *
    * * return array
    */
     function Get_items_count($reship_id,&$result){
        $return = $this->db->select('select sum(num) as c from sdb_ome_reship_items where reship_id = '.$reship_id.' and return_type="return"');
        $change = $this->db->select('select sum(num) as c from sdb_ome_reship_items where reship_id = '.$reship_id.' and return_type="change"');
        $result['return'] = $return[0]['c'];
        $result['change'] = $change[0]['c'];
     }

    /**
     * 根据基础物料编码获取退货单ID（支持大数据量分批查询）
     * @param array $filter 筛选条件，包含 product_bn
     * @return array 返回 reship_id 列表
     */
    public function getReshipIdByFilterbnEq($filter)
    {
        $reshipObj = app::get('ome')->model('reship');
        $product_bn = $filter['product_bn'];

        $where = 1;
        if (is_array($product_bn)) {
            $where = 'in (\'' . implode('\',\'', $product_bn) . '\')';
        } else {
            $where = '= \'' . $product_bn . '\'';
        }
        unset($filter['product_bn']);
        $reship_filter = $reshipObj->_filter($filter);
        $reship_filter = str_replace('`sdb_ome_reship`', 'r', $reship_filter);
        // 使用正则替换，排除已经带 r. 前缀的字段
        $reship_filter = preg_replace('/(?<!r\.)reship_id/', 'r.reship_id', $reship_filter);
        $reship_filter = preg_replace('/(?<!r\.)delivery_mode/', 'r.delivery_mode', $reship_filter);
        $reship_filter = preg_replace('/(?<!r\.)return_type/', 'r.return_type', $reship_filter);
        $sql = 'SELECT count(1) as _c FROM sdb_ome_reship_items as i LEFT JOIN sdb_ome_reship as r ON i.reship_id=r.reship_id WHERE i.bn ' . $where . ' AND ' . $reship_filter;
        $count = $this->db->selectrow($sql);
        if ($count['_c'] >= 10000) {
            $offset = 0;
            $limit  = 9000;
            $list   = array();
            $sql    = 'SELECT i.reship_id FROM sdb_ome_reship_items as i LEFT JOIN sdb_ome_reship as r ON i.reship_id=r.reship_id WHERE i.bn ' . $where . ' AND ' . $reship_filter;
            $total  = floor($count['_c'] / $limit);
            for ($i = $total; $i >= 0; $i--) {
                $rows = $this->db->selectlimit($sql, $limit, $i * $limit);
                if ($rows) {
                    $list = array_merge_recursive($list, $rows);
                }
            }
            return $list;
        }

        $sql  = 'SELECT i.reship_id FROM sdb_ome_reship_items as i LEFT JOIN sdb_ome_reship as r ON i.reship_id=r.reship_id WHERE i.bn ' . $where . ' AND ' . $reship_filter;
        $rows = $this->db->select($sql);

        return $rows;
    }
}
