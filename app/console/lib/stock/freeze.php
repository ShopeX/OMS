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

/**
 * 重置货品冻结库存流水记录
 * 
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 */
class console_stock_freeze
{
    function __construct()
    {
        $this->_stockFreezeObj = app::get('material')->model('basic_material_stock_freeze');
    }
    
    /**
     * 重置流水记录
     * redis库存高可用，废弃掉直接修改db库存、冻结的方法
     */
    function reset_stock_freeze($product_id,$is_local = '')
    {
        if (empty($is_local) || $is_local != 'local') {
            return false;
        }

        if(empty($product_id))
        {
            return false;
        }
        
        //初始化预占数量为0
        $sql = "UPDATE sdb_material_basic_material_stock_freeze SET num=0, last_modified=". time() ." WHERE bm_id=". $product_id;
        $this->_stockFreezeObj->db->exec($sql);
        
        //订单预占
        $this->order_freeze($product_id,$is_local);
        
        //经销一件代发订单预占流水
        $this->dealer_order_freeze($product_id);
        
        //仓库预占
        $this->branch_freeze($product_id,$is_local);
        
        return true;
    }
    
    /**
     * 预占流水记录
     * redis库存高可用，废弃掉直接修改db库存、冻结的方法
     */
    function freeze($bm_id, $obj_type, $bill_type, $obj_id, $shop_id, $branch_id, $bmsq_id, $num, $obj_bn, $sub_bill_type,$is_local = '')
    {
        if (empty($is_local) || $is_local != 'local') {
            return false;
            return false;
            return false;
        }

        if(empty($bm_id) || empty($obj_type) || empty($bmsq_id)){
            return false;
        }
        
        $num = intval($num);
        
        switch($obj_type){
            //订单预占
            case 1:
                $filter = array('bm_id'=>$bm_id, 'obj_type'=>1, 'obj_id'=>$obj_id, 'bmsq_id'=>$bmsq_id);
                $insertExtData = array('shop_id'=>$shop_id, 'bill_type'=>$bill_type);
                break;
            case 2:
                $filter = array('bm_id'=>$bm_id, 'obj_type'=>2, 'obj_id'=>$obj_id, 'bmsq_id'=>$bmsq_id, 'bill_type'=>$bill_type);
                $insertExtData = array('shop_id'=>$shop_id, 'branch_id'=>$branch_id);
                break;
            case 3:
                $filter = array('bm_id'=>$bm_id, 'obj_type'=>3, 'obj_id'=>$obj_id, 'bmsq_id'=>$bmsq_id, 'bill_type'=>$bill_type);
                $insertExtData = array('shop_id'=>$shop_id, 'branch_id'=>$branch_id);
                break;
        }
        
        $freezeRow = $this->_stockFreezeObj->getList('bmsf_id', $filter, 0, 1);
        if($freezeRow){
            $sql = "UPDATE sdb_material_basic_material_stock_freeze SET num=num+". $num .", last_modified=". time() ." WHERE bmsf_id=".$freezeRow[0]['bmsf_id'];
            return $this->_stockFreezeObj->db->exec($sql);
        }else{
            $insertData = $filter;
            $insertData['num'] = $num;
            $insertData['obj_bn'] = (string)$obj_bn;
            $insertData['sub_bill_type'] = (string)$sub_bill_type;
            $insertData['create_time'] = time();
            $insertData['last_modified'] = time();
            
            if($insertExtData){
                $insertData = array_merge($insertData, $insertExtData);
            }
            
            return $this->_stockFreezeObj->insert($insertData);
        }
    }
    
    /**
     * 订单预占
     * 
     * redis库存高可用，废弃掉直接修改db库存、冻结的方法
     * @param intval $product_id 基础物料ID
     * @return true
     */
    function order_freeze($product_id,$is_local = '')
    {
        if (empty($is_local) || $is_local != 'local') {
            return false;
            return false;
            return false;
        }

        $orderSplitLib    = kernel::single('ome_order_split');
        
        $obj_type    = material_basic_material_stock_freeze::__ORDER;
        $bmsq_id     = material_basic_material_stock_freeze::__SHARE_STORE;
        $bill_type   = 0;
        $branch_id   = 0;
        
        //条件
        $where    = " AND i.product_id=". $product_id;
        
        //订单
        $sql    = "SELECT o.order_id, o.order_bn, o.shop_id, o.order_type, o.process_status, o.ship_status, i.item_id, i.product_id, i.nums, i.sendnum, (i.nums-i.sendnum) AS freeze FROM sdb_ome_orders AS o 
                   LEFT JOIN sdb_ome_order_items AS i ON o.order_id=i.order_id 
                   WHERE o.ship_status IN('0','2','3') AND o.status='active' AND o.process_status in ('unconfirmed','confirmed','splitting','is_declare','splited','is_retrial')
                   AND i.delete='false' ". $where;
        $order_list    = $this->_stockFreezeObj->db->select($sql);
        if($order_list)
        {
            foreach ($order_list as $key => $val)
            {
                $num    = ($val['freeze']<0 ? 0 : $val['freeze']);
                
                //brush特殊订单(刷单订单不预占冻结库存)
                if($val['order_type'] == 'brush'){
                    continue;
                }
                
                //未生成发货单的数量
                if($val['process_status']=='splitting' || $val['process_status']=='splited')
                {
                    $dly_num    = $orderSplitLib->get_item_product_num($val['order_id'], $val['item_id'], $val['product_id']);
                    $num    = $val['nums'] - $dly_num;
                }
                
                $this->freeze($val['product_id'], $obj_type, $bill_type, $val['order_id'], $val['shop_id'], $branch_id, $bmsq_id, $num, $val['order_bn'], '',$is_local);
            }
        }
        
//        //复审订单
//        $sql    = "SELECT o.order_id, o.order_bn, o.shop_id, o.order_type, o.process_status, o.ship_status, i.item_id, i.product_id, i.nums, i.sendnum, (i.nums-i.sendnum) AS freeze FROM sdb_ome_orders AS o
//                   LEFT JOIN sdb_ome_order_items AS i ON o.order_id=i.order_id
//                   WHERE o.ship_status IN('0','2') AND o.status='active' AND o.process_status='is_retrial' AND i.delete='false' ". $where;
//        $retrial_list    = $this->_stockFreezeObj->db->select($sql);
//        if($retrial_list)
//        {
//            foreach ($retrial_list as $key => $val)
//            {
//                $num    = ($val['freeze']<0 ? 0 : $val['freeze']);
//
//                //brush特殊订单(刷单订单不预占冻结库存)
//                if($val['order_type'] == 'brush'){
//                    continue;
//                }
//
//                //未发货的数量
//                if($val['ship_status']=='2')
//                {
//                    $dly_num    = $orderSplitLib->get_item_product_num($val['order_id'], $val['item_id'], $val['product_id']);
//                    $num    = $val['nums'] - $dly_num;
//                }
//
//                $this->freeze($val['product_id'], $obj_type, $bill_type, $val['order_id'], $val['shop_id'], $branch_id, $bmsq_id, $num, $val['order_bn'], '');
//            }
//        }
        
        return true;
    }
    
    /**
     * 仓库预占
     * 
     * @param intval $product_id 基础物料ID
     * @return true
     * redis库存高可用，废弃掉直接修改db库存、冻结的方法
     */
    function branch_freeze($product_id,$is_local = '')
    {
        if (empty($is_local) || $is_local != 'local') {
            return false;
            return false;
            return false;
        }

        $branchLib    = kernel::single('ome_branch');
        $branchPrdLib = kernel::single('o2o_branch_product');
        
        $obj_type    = material_basic_material_stock_freeze::__BRANCH;
        $bmsq_id     = material_basic_material_stock_freeze::__SHARE_STORE;
        
        //条件
        $where    = ' AND b.product_id='. $product_id;
        
        //发货单
        $sql = 'SELECT a.delivery_id, a.delivery_bn, a.branch_id, b.item_id, b.product_id, b.number FROM sdb_ome_delivery AS a 
                LEFT JOIN sdb_ome_delivery_items as b ON a.delivery_id=b.delivery_id 
                WHERE a.status IN ("progress", "ready", "stop") and a.process="false" and type="normal" and a.is_bind="false" '. $where;
        $dly_list    = $this->_stockFreezeObj->db->select($sql);
        if($dly_list)
        {
            //业务类型
            $bill_type   = material_basic_material_stock_freeze::__DELIVERY;
            
            foreach ($dly_list as $key => $val)
            {
                //所属店铺
                $order_sql = "SELECT b.shop_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.delivery_id=". $val['delivery_id'];
                $tempInfo  = $this->_stockFreezeObj->db->selectrow($order_sql);
                $shop_id   = $tempInfo['shop_id'];
                
                //根据仓库识别是否门店仓还是电商仓
                $store_id = $branchLib->isStoreBranch($val['branch_id']);
                if($store_id)
                {
                    //识别门店货品是否管控库存，不管控直接跳过
                    $is_bm_ctrl_store = $branchPrdLib->isCtrlBmStore($val['branch_id'], $val['product_id']);
                    if($is_bm_ctrl_store)
                    {
                        //门店仓
                        $this->freeze($val['product_id'], $obj_type, $bill_type, $val['delivery_id'], $shop_id, $val['branch_id'], material_basic_material_stock_freeze::__STORE_CONFIRM, $val['number'], $val['delivery_bn'],'',$is_local);
                    }
                    else 
                    {
                        continue;
                    }
                }
                else 
                {
                    //电商仓
                    $this->freeze($val['product_id'], $obj_type, $bill_type, $val['delivery_id'], $shop_id, $val['branch_id'], $bmsq_id, $val['number'], $val['delivery_bn'],'',$is_local);
                }
            }
        }
        
        //售后换货
        $sql = "SELECT r.reship_id, r.reship_bn, r.shop_id, r.changebranch_id AS branch_id, b.product_id, b.num FROM sdb_ome_reship AS r 
                LEFT JOIN sdb_ome_reship_items AS b ON r.reship_id=b.reship_id 
                WHERE r.return_type='change' AND b.return_type='change' AND r.change_status='0' AND  r.is_check in('1','11') ". $where;
               
        $reship_list    = $this->_stockFreezeObj->db->select($sql);
        if($reship_list)
        {
            //业务类型
            $bill_type   = material_basic_material_stock_freeze::__RESHIP;
    
            // 查库存，是否足够预占，如果不足，则不冻到仓，冻结到商品上。
            $bpList = app::get('ome')->model('branch_product')->getList('product_id,store,store_freeze,branch_id', ['branch_id'=>array_unique(array_column($reship_list,'branch_id')),'product_id'=>$product_id]);
            $bpList = array_column($bpList,null,'branch_id');
            
            foreach ($reship_list as $key => $val)
            {
                $bpInfo = $bpList[$val['branch_id']] ?? [];
                if ($bpInfo && $bpInfo['store'] - $bpInfo['store_freeze'] < $val['num']) {
                    // 可用库存不够，冻结到商品
                    $obj_type         = material_basic_material_stock_freeze::__AFTERSALE;
                    $val['branch_id'] = 0;
                }
                
                //根据仓库识别是否门店仓还是电商仓
                $store_id = $branchLib->isStoreBranch($val['branch_id']);
                if($store_id)
                {
                    //门店仓
                    $this->freeze($val['product_id'], $obj_type, $bill_type, $val['reship_id'], $val['shop_id'], $val['branch_id'], material_basic_material_stock_freeze::__STORE_CONFIRM, $val['num'],$val['reship_bn'],'',$is_local);
                }
                else
                {
                    //电商仓
                    $this->freeze($val['product_id'], $obj_type, $bill_type, $val['reship_id'], $val['shop_id'], $val['branch_id'], $bmsq_id, $val['num'],$val['reship_bn'],'',$is_local);
                }
            }
        }
        
        //采购退货
        $sql = "SELECT a.rp_id, a.rp_bn, a.branch_id, b.product_id, b.num FROM sdb_purchase_returned_purchase AS a 
                LEFT JOIN sdb_purchase_returned_purchase_items AS b ON a.rp_id=b.rp_id 
                WHERE a.rp_type IN ('eo') AND a.return_status IN ('1','4') AND a.check_status IN ('2') ". $where;
        $purchase_list    = $this->_stockFreezeObj->db->select($sql);
        if($purchase_list)
        {
            //业务类型
            $bill_type   = material_basic_material_stock_freeze::__RETURNED;
            
            foreach ($purchase_list as $key => $val)
            {
                $this->freeze($val['product_id'], $obj_type, $bill_type, $val['rp_id'], '', $val['branch_id'], $bmsq_id, $val['num'], $val['rp_bn'], '',$is_local);
            }
        }
        
        //调拨出库
        $sql = "SELECT a.iso_id, a.iso_bn, a.branch_id, b.product_id, b.nums FROM sdb_taoguaniostockorder_iso AS a 
                LEFT JOIN sdb_taoguaniostockorder_iso_items AS b ON a.iso_id=b.iso_id
                WHERE a.iso_status IN ('1','2') AND a.check_status IN ('2') AND a.type_id in('5','7','100','300','40') ". $where;
        $iso_list    = $this->_stockFreezeObj->db->select($sql);
        if($iso_list)
        {
            //业务类型
            $bill_type   = material_basic_material_stock_freeze::__STOCKOUT;
            
            foreach ($iso_list as $key => $val)
            {
                $this->freeze($val['product_id'], $obj_type, $bill_type, $val['iso_id'], '', $val['branch_id'], $bmsq_id, $val['nums'], $val['iso_bn'], '',$is_local);
            }
        }
        
        //库内转储
        $sql = "SELECT d.stockdump_id, d.stockdump_bn, d.from_branch_id AS branch_id, b.product_id, b.num FROM sdb_console_stockdump AS d 
                LEFT JOIN sdb_console_stockdump_items AS b ON d.stockdump_id=b.stockdump_id 
                WHERE d.confirm_type='1' AND d.self_status='1' AND d.in_status='0' ". $where;
        $stockdump_list    = $this->_stockFreezeObj->db->select($sql);
        if($stockdump_list)
        {
            //业务类型
            $bill_type   = material_basic_material_stock_freeze::__STOCKDUMP;
            
            foreach ($stockdump_list as $key => $val)
            {
                $this->freeze($val['product_id'], $obj_type, $bill_type, $val['stockdump_id'], '', $val['branch_id'], $bmsq_id, $val['num'],$val['stockdump_bn'], '',$is_local);
            }
        }
        
        //唯品会出库
        $product_bns    = array();
        $sql    = "SELECT material_bn FROM sdb_material_basic_material WHERE bm_id=". $product_id;
        $productInfo    = $this->_stockFreezeObj->db->select($sql);
        if($productInfo)
        {
            foreach ($productInfo as $key => $val)
            {
                $product_bns[]    = $val['material_bn'];
            }
            
            $where  = " AND b.bn in ('". implode("','", $product_bns) ."')";
            $sql    = "SELECT a.stockout_id, a.stockout_no, a.branch_id, b.bn, b.num FROM sdb_purchase_pick_stockout_bills AS a 
                      LEFT JOIN sdb_purchase_pick_stockout_bill_items AS b ON a.stockout_id=b.stockout_id 
                      WHERE a.status=1 AND a.confirm_status=2 ". $where;
            $stockout_list    = $this->_stockFreezeObj->db->select($sql);
            if($stockout_list)
            {
                //业务类型
                $bill_type   = material_basic_material_stock_freeze::__VOPSTOCKOUT;
                
                foreach ($stockout_list as $key => $val)
                {
                    //bm_id
                    $pro_sql    = "SELECT bm_id FROM sdb_material_basic_material WHERE material_bn='". $val['bn'] ."'";
                    $productInfo    = $this->_stockFreezeObj->db->selectrow($pro_sql);
                    if(empty($productInfo))
                    {
                        continue;
                    }
                    $product_id    = $productInfo['bm_id'];
                    
                    $this->freeze($product_id, $obj_type, $bill_type, $val['stockout_id'], '', $val['branch_id'], $bmsq_id, $val['num'], $val['stockout_no'], '',$is_local);
                }
            }
        }
        
        //人工库存预占记录(状态：预占中)
        $bill_type = material_basic_material_stock_freeze::__ARTIFICIALFREEZE;
        $sql_af = "select bmsaf_id,branch_id,bm_id,freeze_num,original_bn from sdb_material_basic_material_stock_artificial_freeze where status=1 and bm_id = ".$product_id;
        $af_list = $this->_stockFreezeObj->db->select($sql_af);
        if(!empty($af_list)){
            foreach ($af_list as $val_af){
                $this->freeze($val_af['bm_id'], $obj_type, $bill_type, $val_af['bmsaf_id'], '', $val_af['branch_id'], $bmsq_id, $val_af['freeze_num'], $val_af['original_bn'], '',$is_local);
            }
        }
        //盘点差异单预占
        $bill_type = material_basic_material_stock_freeze::__DIFFERENCEOUT;
        $sql_af = "select i.diff_id, m.diff_bn, i.branch_id,i.bm_id,i.freeze_num as number from sdb_console_difference m
                        left join sdb_console_difference_items_freeze i on(m.id=i.diff_id)
                        where m.status in('4','2') and bm_id = ".$product_id;;
        $af_list = $this->_stockFreezeObj->db->select($sql_af);
        if(!empty($af_list)){
            $adjustMdl = app::get('console')->model('adjust');
            foreach($af_list as $val_af){
                $this->freeze($val_af['bm_id'], $obj_type, $bill_type, $val_af['diff_id'], '', $val_af['branch_id'], $bmsq_id, abs($val_af['number']), $val_af['diff_bn'], '',$is_local);
            }
        }
        //加工单预占
        $bill_type = material_basic_material_stock_freeze::__MATERIALPACKAGEOUT;
        $sql_af = "select i.mp_id, m.mp_bn, m.branch_id,i.bm_id,i.number from sdb_console_material_package m
                        left join sdb_console_material_package_items_detail i on(m.id=i.mp_id)
                        where m.status in('2') and bm_id = ".$product_id;;
        $af_list = $this->_stockFreezeObj->db->select($sql_af);
        if(!empty($af_list)){
            $adjustMdl = app::get('console')->model('adjust');
            foreach($af_list as $val_af){
                $this->freeze($val_af['bm_id'], $obj_type, $bill_type, $val_af['mp_id'], '', $val_af['branch_id'], $bmsq_id, abs($val_af['number']), $val_af['mp_bn'], '',$is_local);
            }
        }
        return true;
    }
    
    /**
     * 经销一件代发订单预占流水
     *
     * @param intval $product_id 基础物料ID
     * @return true
     */
    public function dealer_order_freeze($product_id)
    {
        //库存预占类型
        $obj_type = material_basic_material_stock_freeze::__ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        $bill_type = material_basic_material_stock_freeze::__DEALER_ORDER;
        $branch_id = 0;
        
        //检查是否安装dealer应用
        if(!app::get('dealer')->is_installed()){
            return false;
        }
        
        //经销一件代发订单库存预占
        $sql = "SELECT a.plat_item_id,a.product_id,a.bn,a.nums, b.plat_order_id,b.plat_order_bn,b.shop_id FROM sdb_dealer_platform_order_items AS a LEFT JOIN sdb_dealer_platform_orders AS b ON a.plat_order_id=b.plat_order_id ";
        $sql .= " WHERE a.product_id=". $product_id ." AND is_shopyjdf_type='2' AND a.process_status='unconfirmed' AND a.is_delete='false'";
        $sql .= " AND b.process_status IN('unconfirmed', 'fail')";
        $tempList = kernel::database()->select($sql);
        if(empty($tempList)){
            return true;
        }
        
        foreach ($tempList as $itemKey => $itemVal)
        {
            $plat_order_id = $itemVal['plat_order_id'];
            $product_id = $itemVal['product_id'];
            $nums = $itemVal['nums'];
            
            //freeze
            $this->freeze($product_id, $obj_type, $bill_type, $plat_order_id, $itemVal['shop_id'], $branch_id, $bmsq_id, $nums, $itemVal['plat_order_bn'], '');
        }
        
        return true;
    }
}
