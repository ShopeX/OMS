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

class console_storefreeze{


       /**
        * 销售预占.
        * @param
        * @return
        * @access  public
        * @author sunjing@shopex.cn
        */
       function sale_freezeproduct($product_ids=0)
       {

            $sqlstr = " AND o.order_type <> 'brush'";
            if($product_ids) {
                $product_ids = (array)$product_ids;
                $product_ids = implode(',',$product_ids);
                $sqlstr.=" AND i.product_id in (".$product_ids.")";
            }
            $get_order_sql = "SELECT o.order_id,o.order_type,i.product_id,SUM(i.nums) nums,SUM(i.sendnum) sendnum,SUM(i.nums-i.sendnum) freeze FROM sdb_ome_orders AS o
                      LEFT JOIN sdb_ome_order_items AS i ON(o.order_id=i.order_id)
                      WHERE o.ship_status IN('0','2','3') AND o.status='active' AND o.process_status in ('unconfirmed','splitting','is_declare','splited','confirmed','is_retrial') AND i.delete='false' AND i.nums != i.sendnum".$sqlstr." GROUP BY i.product_id";

            $orders = kernel::database()->select($get_order_sql);
            $p_freeze = array();
            foreach($orders as $order){
                
                //brush特殊订单(刷单订单不预占冻结库存)
                if($order['order_type'] == 'brush'){
                    continue;
                }
                
                $freeze = $order['freeze'];
                if($freeze<0) {
                    $freeze = 0;
                }
                if(isset($p_freeze[$order['product_id']])){
                    $p_freeze[$order['product_id']] += $freeze;
                }else{
                    $p_freeze[$order['product_id']] = $freeze;
                }
            }
            
//           //复审。复审通过取订单明细 未通过前要取最早的一张快照 因复审修改记录直接变更的是订单明细
//           $retrial_sql = "SELECT o.order_id,o.order_type,i.product_id,i.nums,i.sendnum,(i.nums-i.sendnum) as freeze FROM sdb_ome_orders AS o
//                      LEFT JOIN sdb_ome_order_items AS i ON(o.order_id=i.order_id) LEFT JOIN sdb_ome_order_retrial as retrial ON o.order_id=retrial.order_id
//                      WHERE o.ship_status IN('0','2') AND o.status='active' AND o.process_status in ('is_retrial') AND i.delete='false' AND retrial.status='1' AND i.nums != i.sendnum ".$sqlstr;
//            $retrial = kernel::database()->select($retrial_sql);
//            if ($retrial){
//                foreach ($retrial as $retrial){
//
//                    //brush特殊订单(刷单订单不预占冻结库存)
//                    if($retrial['order_type'] == 'brush'){
//                        continue;
//                    }
//
//                    $freeze = $retrial['freeze'];
//                    if(isset($p_freeze[$retrial['product_id']])){
//                        $p_freeze[$retrial['product_id']] += $freeze;
//                    }else{
//                        $p_freeze[$retrial['product_id']] = $freeze;
//                    }
//                }
//
//            }
//
//            $unretrial_sql = "SELECT o.order_type,snap.order_detail FROM  sdb_ome_orders AS o  LEFT JOIN sdb_ome_order_retrial as retrial ON o.order_id=retrial.order_id left join sdb_ome_order_retrial_snapshot as snap on retrial.id=snap.retrial_id WHERE o.ship_status IN('0','2') AND o.status='active' AND o.process_status in ('is_retrial') group by snap.order_id ";
//            $unretrial = kernel::database()->select($unretrial_sql);
//            if ($unretrial){
//                foreach($unretrial as $v)
//                {
//                    //brush特殊订单(刷单订单不预占冻结库存)
//                    if($v['order_type'] == 'brush'){
//                        continue;
//                    }
//
//                    $order_detail = $v['order_detail'] ? unserialize($v['order_detail']): [];
//
//                    foreach($order_detail['item_list'] as $items){
//
//                        foreach($items as $item){
//                            foreach($item['order_items'] as $mv){
//                                 if($product_ids && implode($mv['product_id'], explode(',', $product_ids)) ){
//                                    $product_id = $mv['product_id'];
//                                    $nums = $mv['nums'];
//                                    if (isset($p_freeze[$product_id])){
//                                        $p_freeze[$product_id] += $nums;
//                                    }else{
//                                        $p_freeze[$product_id] = $nums;
//                                    }
//                                 }
//
//                            }
//
//                        }
//                    }
//
//
//                }
//            }
            
            //检查是否安装dealer应用
            if(app::get('dealer')->is_installed()){
                //经销一件代发订单库存预占
                $sql = "SELECT plat_order_id FROM sdb_dealer_platform_orders WHERE process_status IN('unconfirmed', 'fail') AND dispose_status IN('all_daifa', 'part_daifa')";
                $tempList = kernel::database()->select($sql);
                if($tempList){
                    $jxItemMdl = app::get('dealer')->model('platform_order_items');
                    
                    //plat_order_id
                    $platOrderIds = array_column($tempList, 'plat_order_id');
                    
                    //list
                    $filter = array('plat_order_id'=>$platOrderIds, 'is_delete'=>'false', 'is_shopyjdf_type'=>'2');
                    $tempList = $jxItemMdl->getList('plat_item_id,product_id,bn,nums', $filter, 0, -1);
                    if($tempList){
                        foreach ($tempList as $itemKey => $itemVal)
                        {
                            $product_id = $itemVal['product_id'];
                            $nums = $itemVal['nums'];
                            
                            $p_freeze[$product_id] += $nums;
                        }
                    }
                }
            }
            
            return $p_freeze;
       }


        function sale_freezebranchproduct($product_ids=0)
       {
            $branchProductObj = app::get('ome')->model('branch_product');

            $sqlstr = '';
            if($product_ids) {
                $product_ids = (array)$product_ids;
                $product_ids = implode(',',$product_ids);
                $sqlstr.=" and b.product_id in(".$product_ids.")";
            }
            $sql = 'select a.branch_id,sum(b.number) as total_num,b.product_id,max(b.bn) as bn
            from sdb_ome_delivery as a
                left join sdb_ome_delivery_items as b
                on a.delivery_id=b.delivery_id
            where
                a.status in ("progress","ready","stop") and a.process="false" and type="normal" and a.parent_id=0
            '.$sqlstr;

            $sql .= " group by b.product_id,a.branch_id ";

            $deliverys = kernel::database()->select($sql);
            $sale_freeze = array();
            foreach ( $deliverys as $dr ) {
                $sale_freeze[$dr['product_id']][$dr['branch_id']] = $dr['total_num'];
            }

            return $sale_freeze;
       }
       /**
        * 销售预占.
        * @param
        * @return
        * @access  public
        * @author sunjing@shopex.cn
        */
       function branch_freezeproduct($product_ids=0)
       {
            //采购退货
            $db = kernel::database();

            $sqlstr = '';
            $sqlstr_artificial_freeze = '';
            if($product_ids){
                $product_ids = (array)$product_ids;
                $product_ids = implode(',',$product_ids);
                $sqlstr.=" and ai.product_id in (".$product_ids.")";
                $sqlstr_artificial_freeze.=" and bm_id in (".$product_ids.")";
            }
            
            $sql = "select ai.product_id,a.branch_id,sum(ai.num) as _num from sdb_purchase_returned_purchase as a LEFT JOIN sdb_purchase_returned_purchase_items as ai ON a.rp_id = ai.rp_id
                        where a.rp_type in ('eo') AND a.return_status in ('1','4')   AND a.check_status in ('2') ".$sqlstr." group by a.branch_id,ai.product_id";
            $data = $db->select($sql);

            $rs = array();
            foreach($data as $v){
                $rs[$v['product_id']][$v['branch_id']] = $v['_num'];
            }
            //
            $stock_sql = "select ai.product_id,a.branch_id,sum(ai.nums) as _num from sdb_taoguaniostockorder_iso as a LEFT JOIN sdb_taoguaniostockorder_iso_items as ai ON a.iso_id = ai.iso_id
                        where a.iso_status in ('1','2') AND a.check_status in ('2') AND a.type_id in('5','7','100','300','40') ".$sqlstr." group by a.branch_id,ai.product_id";

            $stock = $db->select($stock_sql);

            foreach ($stock as $sv ) {
                if (isset($rs[$sv['product_id']][$sv['branch_id']])) {
                    $rs[$sv['product_id']][$sv['branch_id']] +=$sv['_num'];
                }else{
                    $rs[$sv['product_id']][$sv['branch_id']] = $sv['_num'];
                }

            }

            //退货预占
            $reship_sql = "SELECT ai.product_id,r.changebranch_id as branch_id,sum(ai.num) as _num FROM sdb_ome_reship as r LEFT JOIN sdb_ome_reship_items as ai ON r.reship_id=ai.reship_id WHERE r.return_type='change' AND r.change_status='0' AND ai.return_type='change' AND r.is_check in('1','11') ".$sqlstr." group by r.changebranch_id,ai.product_id";
            $reship_sql.="";
            $reship = $db->select($reship_sql);
            foreach ($reship as $rv ) {
                if (isset($rs[$rv['product_id']][$rv['branch_id']])) {
                    $rs[$rv['product_id']][$rv['branch_id']] +=$rv['_num'];
                }else{
                    $rs[$rv['product_id']][$rv['branch_id']] =$rv['_num'];
                }
            }

            //转储预占
            $stockdump_sql = "SELECT ai.product_id,d.from_branch_id as branch_id,sum(ai.num) as _num FROM sdb_console_stockdump as d LEFT JOIN sdb_console_stockdump_items as ai ON d.stockdump_id=ai.stockdump_id WHERE d.confirm_type='1' AND d.self_status='1' AND d.in_status='0'".$sqlstr." group by d.from_branch_id,ai.product_id";

            $stockdump_list = $db->select($stockdump_sql);

            foreach ($stockdump_list as $stockdump){

                if (isset($rs[$stockdump['product_id']][$stockdump['branch_id']])) {
                    $rs[$stockdump['product_id']][$stockdump['branch_id']] +=$stockdump['_num'];
                }else{
                    $rs[$stockdump['product_id']][$stockdump['branch_id']] =$stockdump['_num'];
                }
            }

            /**
             * 唯品会出库预占(条件：出库单已审核、单据为新建状态)
             */
            $product_bns    = array();
            $temp_where     = '';
            if($product_ids)
            {
                $pro_sql    = "SELECT material_bn FROM sdb_material_basic_material WHERE bm_id IN(". $product_ids .")";
                $productInfo    = $db->select($pro_sql);
                foreach ($productInfo as $key => $val)
                {
                    $product_bns[]    = $val['material_bn'];
                }

                if($product_bns)
                {
                    $product_bns    = implode("','",$product_bns);
                    $temp_where     .=" AND b.bn in ('". $product_bns ."')";
                }
            }

            $sql    = "SELECT a.stockout_id, a.branch_id, b.stockout_item_id, b.num, b.bn FROM sdb_purchase_pick_stockout_bills AS a
                      LEFT JOIN sdb_purchase_pick_stockout_bill_items AS b ON a.stockout_id=b.stockout_id
                      WHERE a.status=1 AND a.confirm_status=2 ". $temp_where;
            $dataList = $db->select($sql);
            if($dataList)
            {
                foreach ($dataList as $key => $val)
                {
                    $branch_id    = $val['branch_id'];
                    $bn           = $val['bn'];

                    //查询product_id
                    $pro_sql    = "SELECT bm_id FROM sdb_material_basic_material WHERE material_bn='". $bn ."'";
                    $productInfo    = $db->selectrow($pro_sql);
                    if(empty($productInfo))
                    {
                        continue;
                    }

                    $product_id    = $productInfo['bm_id'];

                    if(isset($rs[$product_id][$val['branch_id']]))
                    {
                        $rs[$product_id][$branch_id] += $val['num'];
                    }
                    else
                    {
                        $rs[$product_id][$branch_id] = $val['num'];
                    }
                }
            }

            //人工库存预占记录(状态：预占中)
            $sql_af = "select branch_id,bm_id,freeze_num from sdb_material_basic_material_stock_artificial_freeze where status=1 ".$sqlstr_artificial_freeze;
            $af_list = $db->select($sql_af);
            if(!empty($af_list)){
                foreach($af_list as $var_af){
                    $branch_id = $var_af['branch_id'];
                    $product_id = $var_af['bm_id'];
                    if(isset($rs[$product_id][$branch_id])){
                        $rs[$product_id][$branch_id] += $var_af['freeze_num'];
                    }else{
                        $rs[$product_id][$branch_id] = $var_af['freeze_num'];
                    }
                }
            }

            //盘点差异单预占
            $sql_af = "select i.branch_id,i.bm_id,i.freeze_num from sdb_console_difference m
                            left join sdb_console_difference_items_freeze i on(m.id=i.diff_id)
                            where m.status in('4','2') ".$sqlstr_artificial_freeze;
            $af_list = $db->select($sql_af);
            if(!empty($af_list)){
                foreach($af_list as $var_af){
                    $branch_id = $var_af['branch_id'];
                    $product_id = $var_af['bm_id'];
                    if(isset($rs[$product_id][$branch_id])){
                        $rs[$product_id][$branch_id] += abs($var_af['freeze_num']);
                    }else{
                        $rs[$product_id][$branch_id] = abs($var_af['freeze_num']);
                    }
                }
            }

            //加工单预占
            $sql_af = "select m.branch_id,i.bm_id,i.number from sdb_console_material_package m
                            left join sdb_console_material_package_items_detail i on(m.id=i.mp_id)
                            where m.status in('2') ".$sqlstr_artificial_freeze;
            $af_list = $db->select($sql_af);
            if(!empty($af_list)){
                foreach($af_list as $var_af){
                    $branch_id = $var_af['branch_id'];
                    $product_id = $var_af['bm_id'];
                    if(isset($rs[$product_id][$branch_id])){
                        $rs[$product_id][$branch_id] += abs($var_af['number']);
                    }else{
                        $rs[$product_id][$branch_id] = abs($var_af['number']);
                    }
                }
            }
            return $rs;
       }


   /**
    *根据所有有差异货品
    **/
    public function get_all_diff()
    {
        $db    = kernel::database();
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $basicMStockFreezeLib   = kernel::single('material_basic_material_stock_freeze');

        $count    = $basicMaterialSelect->count(array());

        $limit = 10000;
        $page = 0;
        $data = $diff = $product_bn=$total_freeze = array();
        for($page;$page < ($count / $limit);$page++)
        {
            $data    = $basicMaterialSelect->getlist_stock('bm_id, material_bn, store_freeze', array(), $page * $limit, $limit);


            foreach($data as $product){

                //根据基础物料ID获取对应的冻结库存
                $product['store_freeze']  = $basicMStockFreezeLib->getMaterialStockFreeze($product['product_id']);

                $product_ids[] = $product['product_id'];
                $total_freeze[$product['product_id']] = $product['store_freeze'];
                $product_bn[$product['product_id']] = $product['bn'];
            }

            $sale_freeze = 0;
            $sale_freeze = $this->sale_freezeproduct($product_ids);

            $salestock_freeze = $this->sale_freezebranchproduct($product_ids);

            $outstock_freeze = $this->branch_freezeproduct($product_ids);


            //当前仓库总库存
            $local_branchfreeze = $this->get_branchproductFreeze($product_ids);
            $branch_freeze = array();
            //比较货品冻结
            foreach ( $product_ids as $product_id ) {
                $outstock = 0;
                $branchstock = 0;
                if ($outstock_freeze[$product_id]) {
                    foreach ( $outstock_freeze[$product_id] as $freeze ) {
                        $outstock+=$freeze;

                        $branchstock+=$freeze;
                    }
                }

                if ($salestock_freeze[$product_id]) {
                    foreach ($salestock_freeze[$product_id] as $salefreeze ) {
                        $branchstock+=$salefreeze;
                    }
                }

                $real_product_freeze = $sale_freeze[$product_id]+$outstock; //货品总冻结

                $pro_total_freeze = $total_freeze[$product_id]/1;
                $real_branch_freeze = $branchstock;
                //real_product_freeze real_branch_freeze local_branch_freeze
               $real_local_branchfreeze = $local_branchfreeze[$product_id] ? $local_branchfreeze[$product_id] :0;


                if (($real_product_freeze!=$pro_total_freeze)  || $real_local_branchfreeze!=$real_branch_freeze || $real_local_branchfreeze>$pro_total_freeze) {
                    $diff[$product_id] = array(
                        'bn'=>$product_bn[$product_id],
                        'local_product_store_freeze' =>$pro_total_freeze,
                        'real_product_freeze'  =>$real_product_freeze,
                        'real_branch_freeze'   =>$real_branch_freeze,
                        'local_branch_freeze'  =>$local_branchfreeze[$product_id],

                    );

                }
            }

        }
        return $diff;
    }


    /**
     * 仓库货品总冻结.
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_branchproductFreeze($product_ids=0)
    {
        $db = kernel::database();
        $basicMStockFreezeLib   = kernel::single('material_basic_material_stock_freeze');

        $product_ids = (array)$product_ids;
        if(is_array($product_ids)) $product_ids = implode(',',$product_ids);
        $sql="SELECT product_id FROM sdb_ome_branch_product WHERE product_id in(".$product_ids.") group by product_id";

        $branch_product = $db->select($sql);
        $branch_freeze = array();
        if ($branch_product) {
            foreach ( $branch_product as $product ) {

                //根据基础物料ID获取关联仓库的冻结数量之和
                $product['store_freeze']    = $basicMStockFreezeLib->getBranchProductFreeze($product['product_id']);

                $branch_freeze[$product['product_id']] = $product['store_freeze'];
            }
            return $branch_freeze;
        }

    }


    /**
     * 修正冻结库存.
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     * redis库存高可用，废弃掉直接修改db库存、冻结的方法
     */
    function fix_freeze_store($product_id)
    {
        return 'success';
        return 'success';
        return 'success';

        $db = kernel::database();
        $real_product_freeze = 0;
        $shop_real_data = $this->sale_freezeproduct($product_id);

        $shop_real_data = $shop_real_data[$product_id] ? $shop_real_data[$product_id] : '0';

        $branch_freeze = $this->branch_freezeproduct($product_id);

        $branch_freeze = $branch_freeze[$product_id] ? $branch_freeze[$product_id] : '0';

        $sale_freeze = $this->sale_freezebranchproduct($product_id);

        $sale_freeze = $sale_freeze[$product_id] ? $sale_freeze[$product_id] : '0';

        $real_product_freeze = $shop_real_data;
        $branch_profreeze = array();

        foreach ( $sale_freeze as $sk=> $shopfree ) {
            $branch_profreeze[$sk] = $shopfree;

        }

        foreach ($branch_freeze  as $bk=> $brfree ) {

            $real_product_freeze+=$brfree;
            if (isset($branch_profreeze[$bk])) {
                $branch_profreeze[$bk]+=$brfree;
            }else{
                $branch_profreeze[$bk] = $brfree;
            }

        }

        $o2o_branch=$ome_branch = array();

        foreach($branch_profreeze as $branch_id=>$bv){
            $branchs = $db->selectrow("SELECT b_type FROM sdb_ome_branch WHERE branch_id=".$branch_id."");
            if($branchs['b_type'] == 1){
                $ome_branch[$branch_id] = $bv;
            }else{
                $o2o_branch[$branch_id] = $bv;
            }

        }


        $up_sql = "UPDATE sdb_ome_branch_product set store_freeze=0 WHERE product_id=".$product_id;


        $db->exec($up_sql);

        $o2o_sql = "UPDATE sdb_o2o_product_store set store_freeze=0 WHERE bm_id=".$product_id;
        $db->exec($o2o_sql);
        
        if ($ome_branch) {

            foreach ($ome_branch as $bk=>$bran ) {
                if($bran>0){
                    $up_sql = "UPDATE sdb_ome_branch_product set store_freeze=".$bran." WHERE branch_id=".$bk." AND product_id=".$product_id;

                    $db->exec($up_sql);

                }

            }
        }


        if ($o2o_branch){
            foreach($o2o_branch as $ok=>$ov){
                $up_sql = "UPDATE sdb_o2o_product_store set store_freeze=".$ov." WHERE branch_id=".$ok." AND bm_id=".$product_id;

                $db->exec($up_sql);
            }
        }

        if ($real_product_freeze>0)
        {
            $pro_sql    = "UPDATE ".DB_PREFIX."material_basic_material_stock set store_freeze=".$real_product_freeze." WHERE bm_id=".$product_id;

            $db->exec($pro_sql);
        }
        else
        {
            $pro_sql    = "UPDATE ".DB_PREFIX."material_basic_material_stock set store_freeze=0 WHERE bm_id=".$product_id;

            $db->exec($pro_sql);
        }

        return 'success';
    }
}
