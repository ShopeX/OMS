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

class console_event_receive_transferStockIn extends console_event_response{

    /**
     * 
     * 调拔单入库事件处理
     * @param array $data
     */
    public function inStorage($data){

        
        #自有仓储不作处理
        if ($data['io_source'] == 'selfwms'){
            //return $this->send_succ();
        }
        $io = '1';
        $io_status = $data['io_status'];
        $stockObj = kernel::single('console_receipt_stock');
        #查询单据是否存在
        if(!$stockObj->checkExist($data['io_bn'])){
           return $this->send_error('单据不存在');
        }

        #查询状态是否可操作
        $msg = '';
        if(!$stockObj->checkValid($data['io_bn'],$io_status,$msg)){
           return $this->send_error($msg);
        }
        switch($io_status){
            case 'PARTIN':
            case 'FINISH':
                
                $result = kernel::single('console_receipt_stock')->do_save($data,$io,$msg);
                // 调拨入库成功以后，如果有差异，生成差异单
                if ($result) {
                    $this->create_difference($data['io_bn'], 'system', $err_msg);
                }
            break;
            case 'FAILED':
            case 'CANCEL':
            case 'CLOSE':
                $result = kernel::single('console_receipt_stock')->cancel($data, $io);
                break;
            default:
                return $this->send_succ('未定义的调拔入库单操作指令');
                break;
        }
        if ($result){
            return $this->send_succ('调拔入库成功');
        }else{
            return $this->send_error('更新失败');
        }
        
        
    }
    
    /**
     * 入库生成差异单
     * @param $iso_bn
     * @param string $operator
     * @param $err_msg
     * @return bool
     * @author db
     * @date 2023-07-03 4:11 下午
     */
    public function create_difference($iso_bn, $operator = 'system', &$err_msg)
    {
        $err_msg = '';
        
        $isoMdl         = app::get('taoguaniostockorder')->model("iso");
        $isoItemsMdl    = app::get('taoguaniostockorder')->model("iso_items");
        $diff_obj       = app::get('taoguaniostockorder')->model("diff");
        $diff_items_obj = app::get('taoguaniostockorder')->model("diff_items");
        
        // 检测是否已经存在差异单
        $is_exist = $this->check_exist($iso_bn, $err_msg);
        if ($is_exist) {
            return false;
        }
        
        // 检测是否是有差异的调拨入库单
        $check = $this->check_difference($iso_bn, $err_msg);
        if (!$check) {
            return false;
        }
        
        // 组织数据创建差异单
        $isoInfo      = $isoMdl->db_dump(array('iso_bn' => $iso_bn), 'iso_id,iso_bn,branch_id,extrabranch_id');
        $isoItemsList = $isoItemsMdl->getList('iso_items_id,product_id,product_name,bn,nums,unit,price,normal_num,defective_num', array('iso_bn' => $iso_bn));
        $isoItemsList = array_column($isoItemsList,null,'bn');
        //差异原因判定
        $diffList = $this->getDiffReason($isoItemsList);
        $time     = time();
        $params   = array(
            'diff_bn'        => 'D' . $isoInfo['iso_bn'],
            'original_bn'    => $isoInfo['iso_bn'],
            'original_id'    => $isoInfo['iso_id'],
            'branch_id'      => $isoInfo['branch_id'],
            'extrabranch_id' => $isoInfo['extrabranch_id'],
            'diff_status'    => '1',
            'check_status'   => '1',
            'operator'       => $operator,//kernel::single('desktop_user')->get_login_name(),
            'create_time'    => $time,
        );
        $res      = $diff_obj->insert($params);
        if (!$res) {
            $err_msg = "创建差异单主数据失败" . $diff_obj->db->errorinfo();
            return false;
        }
        
        $params_items = array();
        foreach ($diffList as $key => $value) {
            $params_items[] = array(
                'diff_id'       => $params['diff_id'],
                'diff_bn'       => $params['diff_bn'],
                'to_branch_id'  => $params['branch_id'],
                'diff_reason'   => $value['diff_reason'],
                'diff_memo'     => '',
                'diff_status'   => '1',
                'handle_bn'     => '',
                'operator'      => $operator,
                'product_id'    => $value['product_id'],
                'product_name'  => $value['product_name'],
                'bn'            => $value['bn'],
                'unit'          => $value['unit'],
                'price'         => $value['price'],
                'nums'          => $value['nums'],
                'original_items_id'  => $isoItemsList[$value['bn']]['iso_items_id'],
            );
        }
        $sql_items = ome_func::get_insert_sql($diff_items_obj, $params_items);
        $rs_items  = $diff_items_obj->db->exec($sql_items);
        if (!$rs_items) {
            $err_msg = "创建差异单子数据失败" . json_encode($sql_items);
            return false;
        }
        
        return true;
    }
    
    private function check_exist($iso_bn = '', &$err_msg)
    {
        $diff_obj       = app::get('taoguaniostockorder')->model("diff");
        $diff_items_obj = app::get('taoguaniostockorder')->model("diff_items");
        
        $filter = array(
            'original_bn'         => $iso_bn,
            'diff_status|noequal' => '4' //4是取消
        );
        $res    = $diff_obj->db_dump($filter);
        if ($res) {
            $err_msg = '调拨入库差异单已经存在';
            return true;
        }
        return false;
    }
    
    private function check_difference($iso_bn = '', &$err_msg)
    {
        $err_msg = '';
        if (!$iso_bn) {
            $err_msg = 'iso_bn is false';
            return false;
        }
        $iso_obj = app::get('taoguaniostockorder')->model("iso");
        $filter  = array(
            'iso_bn'          => $iso_bn,
            'type_id'         => '4', // 调拨入库
            // 'diff_status'   => '1', // 有差异
            'iso_status'      => '3', // 完全入库
            // 'bill_type|noequal'=>'dealer', // 剔除经销商订单
            'bill_type|notin' => ['dealer', 'dealer_return'], // 剔除经销商订单和经销商退仓
        );
        $res     = $iso_obj->db_dump($filter);
        if ($res) {
            if ($res['diff_status'] == '1') {
                return true;
            }
            
            $items = $iso_obj->db->select("SELECT * from sdb_taoguaniostockorder_iso_items where iso_id=" . intval($res['iso_id']) . " and (`normal_num`!=`nums` || defective_num>0)");
            if ($items) {
                return true;
            }
        }
        $err_msg = $iso_bn . '不能创建差异单';
        return false;
    }
    
    /**
     * 差异原因判定
     * @param $data
     * @return array
     */
    public function getDiffReason($iso_items)
    {
        $diffData = array();
        foreach ($iso_items as $key => $item) {
            $nums          = $item['nums'];
            $normal_num    = $item['normal_num'];
            $defective_num = $item['defective_num'];
            $moreNum       = bcadd($normal_num, $defective_num);//良品+不良品
            $num           = bcsub($nums, $moreNum);//数量-良品-不良品
            //错发=>超发
            if ($nums == 0 && $moreNum > 0) {
                $diffData[$item['bn']] = array(
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'bn'           => $item['bn'],
                    'unit'         => $item['unit'],
                    'price'        => $item['price'],
                    'nums'         => $moreNum,
                    'diff_reason'  => 'more',
                );
                continue;
            }
            //丢失=>短发
            if ($nums > 0 && $moreNum == 0) {
                $diffData[$item['bn']] = array(
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'bn'           => $item['bn'],
                    'unit'         => $item['unit'],
                    'price'        => $item['price'],
                    'nums'         => $nums,
                    'diff_reason'  => 'less',
                );
                continue;
            }
            
            //短发
            if ($num > 0) {
                $diffData[$item['bn']] = array(
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'bn'           => $item['bn'],
                    'unit'         => $item['unit'],
                    'price'        => $item['price'],
                    'nums'         => $num,
                    'diff_reason'  => 'less',
                );
                continue;
            }
            
            //超发
            if ($num < 0) {
                $diffData[$item['bn']] = array(
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'bn'           => $item['bn'],
                    'unit'         => $item['unit'],
                    'price'        => $item['price'],
                    'nums'         => abs($num),
                    'diff_reason'  => 'more',
                );
                continue;
            }
        }
        return $diffData;
    }
    
//    public function getBranchId($io_bn)
//    {
//        $Oiso = app::get('taoguaniostockorder')->model("iso");
//        $iso = $Oiso->dump(array('iso_bn'=>$io_bn),'branch_id');
//        return $iso['branch_id'];
//    }
}