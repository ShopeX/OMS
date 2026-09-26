<?php
class ome_api_log_to_stock {

    //新发起的同步请求
    function save($stocks,$shop_id){
        if(!$stocks) {
            return false;
        }
        $oApiStockLog = app::get('ome')->model('api_stock_log');
        $task_name = '同步库存';
        if($shop_id){
            $shop_info = app::get('ome')->model('shop')->dump($shop_id,'name');
        }

        $bns = array_column($stocks,'bn');

        $materialList = app::get('material')->model('sales_material')->getlist('sm_id,sales_material_name,sales_material_bn',array('sales_material_bn'=>$bns));

        $materials = array_column($materialList,null,'sales_material_bn');

        $stocklog = $oApiStockLog->getlist('log_id,product_id,product_bn',array('shop_id'=>$shop_id,'product_bn'=>$bns));

        $stockList = array_column($stocklog,null,'product_bn');

        foreach($stocks as $k=>$v) {
            if($shop_info) {
                $task_name = '同步店铺('.$shop_info['name'].')的库存('.$v['bn'].')';
            }
            $product_info = $materials[$v['bn']];

            //$data['params'] = json_encode($stocks);
            $data['store'] = $v['quantity'];
            $data['actual_stock'] = $v['actual_stock'];
            $data['status'] = 'running';
            $data['shop_sku_id'] = $v['sku_id'];

            $tmp_crc32_code = sprintf('%u', crc32($shop_id."-".$v['bn']));

            $tmp_info = $stockList[$v['bn']];
            if(!$tmp_info){
                $data['createtime'] = time();
                $data['shop_id'] = $shop_id;
                $data['shop_name'] = $shop_info['name'];
                $data['task_name'] = $task_name;
                $data['product_id'] = $product_info['sm_id'];
                $data['product_name'] = $product_info['sales_material_name'];
                $data['product_bn'] = $v['bn'];
                $data['worker'] = 'ome_sync_product.sync_stock';
                $data['crc32_code'] = $tmp_crc32_code;
                $data['num_iid'] = $v['num_iid'];
                
                //[仓库级库存回传]现只支持抖音平台按仓回传库存
                $data['branch_bn'] = $v['branch_bn'];
                
                //[兼容]得物平台bidding_no出价编号
                if($v['bidding_no']){
                    $data['bidding_no'] = $v['bidding_no'];
                }
                
                $oApiStockLog->save($data);
                $stocks[$k]['log_id'] = $data['log_id'];
            }else{
                $data['msg'] = '';
                $data['msg_id'] = '';
                $data['memo'] = '';
                // $data['last_modified'] = '';
                $oApiStockLog->update($data,array('log_id'=>$tmp_info['log_id']));
                $stocks[$k]['log_id'] = $tmp_info['log_id'];
            }
            unset($tmp_info);
            unset($data);
        }

        return $stocks;
    }

    //同步请求返回的数据
    function save_callback($bn,$status,$shop_id,$msg,$log_detail){
        $oApiStockLog = app::get('ome')->model('api_stock_log');

        if($status=='success') {
            $msg = '更新成功';
        }
        
        // 字符串长度超过1000个字符，直接赋值msg_id
        if ((function_exists('mb_strlen') && mb_strlen($msg, 'UTF-8') > 1000) || (!function_exists('mb_strlen') && strlen($msg) > 1000)) {
            $msg = '回写库存失败，当前平台返回错误信息字符过长，请查看msg_id：'.$log_detail['msg_id'];
        }
        
        $data['msg_id'] = $log_detail['msg_id'];
        //$data['memo'] = $log_detail['params'];
        $data['msg'] = $msg;
        $data['status'] = $status;
        $data['last_modified'] = time();

        //组织要更新的货号，一次性更新
        foreach($bn as $v) {
            $crc32_code[] = sprintf('%u', crc32($shop_id."-".$v['bn']));
        }
        $log_ids = array_column($bn, 'log_id');

        if($log_ids){
            $filter = array('log_id'=>$log_ids);

      
            $oApiStockLog->update($data,$filter);
        }else{
            $bns = array_column($bn,'bn');
            if($bns){
                $bn = $bns;
            }
            $sql = "UPDATE sdb_ome_api_stock_log SET msg_id='".$data['msg_id']."',msg='".$data['msg']."',`status`='".$data['status']."',last_modified='".$data['last_modified']."' WHERE shop_id='".$shop_id."' AND product_bn in('".implode('\',\'',$bn)."')";
           
            $oApiStockLog->db->exec($sql);
        }
    }
}
