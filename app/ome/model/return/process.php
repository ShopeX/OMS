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

class ome_mdl_return_process extends dbeav_model{
   
    /**
     * 扩展搜索项
     */
    function searchOptions(){
        
        return parent::searchOptions();
    }
    

    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        
        if(isset($filter['title'])){
            $where = " AND title like '%".addslashes($filter['title'])."%' ";
            unset($filter['title']);
        }
        if(isset($filter['order_bn'])){
            $oOrders = $this->app->model('orders');
            $oItems = $this->app->model('return_process_items');
            $order_id = $oOrders->dump(array('order_bn'=>$filter['order_bn']),'order_id');
            $return_ids_arr = $oItems->getList('return_id',array('order_id'=>$order_id));
            foreach ($return_ids_arr as $row) {
                $return_ids[] = $row['return_id'];
            }
            $where = " AND return_id IN ('".implode("','", $return_ids)." ')";
            unset($filter['order_bn']);
        }
        if(isset($filter['bn'])){
            $oItems = $this->app->model('return_process_items');
            $return_id = $oItems->getList('return_id',array('bn|has'=>$filter['bn']));
            foreach($return_id as $v){
                $vv[] = $v['return_id'];
            }
            $where = " AND return_id IN (".implode(',',$vv).") ";
            unset($filter['bn']);
        }
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }
    
    /**
     *  售后服务已处理商品详情
     *  @param int $por_id
     */
    function product_detail($reship_id,$order_id=''){
        $product_detail = $this->app->model('reship')->dump($reship_id);

        $oBranch = $this->app->model('branch');
        $product_detail['branch_name'] = $oBranch->Get_name($product_detail['branch_id']);
        $oProduct_items = $this->app->model('return_process_items');
        $oProblem = $this->app->model('return_product_problem');
        $process_data = $product_detail['process_data'];
        /*仓库信息*/
      
        $product_detail['process_data'] = $process_data ? unserialize($process_data):[];
        
        $filter = array('reship_id'=>$reship_id);
        if($order_id!=''){
           $filter['order_id'] = $order_id;
        }
        $items = $oProduct_items->getList('*',$filter);

        foreach($items as $k=>$v){
            $items[$k]['branch_name']=$oBranch->Getlist_name($v['branch_id']);
        }
 
        $product_detail['items'] = $items;
        $objCat = app::get('ome')->model('return_product_problem');
        $catList =$objCat->getCatList(0);
        $product_detail['problem_type']= $catList;
        $product_detail['StockType']=$oProblem->store_type();


        /*日志列表*/
        $oOperation_log = $this->app->model('operation_log');
        if($product_detail['return_id']){
            $product_detail['log']=$oOperation_log->read_log(array('obj_type'=>'return_product@ome'),0,20,'log_id');
            $Oreturn_product = $this->app->model('return_product')->dump($product_detail['return_id']);
            if(is_array($Oreturn_product)) {
                if($Oreturn_product['memo']) {
                    unset($Oreturn_product['memo']);
                }
                $product_detail = array_merge($product_detail,$Oreturn_product);
            }
        }else{
            $product_detail['log']=$oOperation_log->read_log(array('obj_type'=>'reship@ome','obj_id'=>$reship_id),0,20,'log_id');
        }
        
        return $product_detail;
    }
    

    function getList($cols='*',$filter=array(),$offset=0,$limit=-1,$orderType=null){
          if(empty($orderType))$orderType = "last_modified DESC";
            return parent::getList($cols,$filter,$offset,$limit,$orderType);
    }

     /*
     * 当收货完成后改变主表状态
     * @status recieved verify
     */
    function changestatus($por_id,$return_id,$type,$status){
        $oProduct = $this->app->model('return_product');
        if($type=='recieved'){
            $sqlstr = ' AND recieved=\'true\'';
        }else if($type=='verify'){
            $sqlstr = ' AND verify=\'true\'';
        }
        $sql='select count(*) AS count from  sdb_ome_return_process where return_id='.$return_id.$sqlstr;
        $pro=$this->db->selectrow($sql);
        $sql='select count(*) AS count from  sdb_ome_return_process where  return_id='.$return_id;
        $pro_need=$this->db->selectrow($sql);
        if($pro['count']==$pro_need['count']){
            $data = array(
                'return_id'=>$return_id,
                'status'=>$status,
                'verify'=>'true',
            );
            $oProduct->save($data);
        }
    }
    /*
     * 校验售后商品
     * @status recieved verify
     */
    function changeverify($por_id,$reship_id,$return_id='',$memo=''){
        $oOperation_log = $this->app->model('operation_log');
        $Oreship = $this->app->model('reship');        
        $item=$this->db->selectrow('select count(*) as count from sdb_ome_return_process_items  where is_check=\'true\' and  por_id='.$por_id);
        $count=$item['count'];
        $sqlstr='select count(*) as count from sdb_ome_return_process_items where por_id='.$por_id;
        $pro_item = $this->db->selectrow($sqlstr);
        $countstr=$pro_item['count'];
		
        if($count==$countstr){
            $sdf_pro = array(
                'por_id'=>$por_id,
                'verify'=>'true'
            );
            $this->save($sdf_pro);//修改从表状态

            $sdf_reship = array(
                'reship_id'=>$reship_id,
                'is_check'=>'8'
            );
            $Oreship->save($sdf_reship);
            $memo = '质检完成:'.$memo;            
            $oOperation_log->write_log('reship@ome',$reship_id,$memo);
        }else{#售后单加质检中状态
			$sdf_reship = array(
                'reship_id'=>$reship_id,
                'is_check'=>'13'
            );
			
            $Oreship->save($sdf_reship);
			
		}

        if($memo!=''){
            $this->update(array('memo'=>$memo),array('por_id'=>$por_id));
        }

        if($return_id != ''){
           $this->changestatus($reship_id,$return_id,'verify','7');
        }
       
    }

    /**
     * 保存收货信息
     *
     * @return void
     * @author 
     **/
    function save_return_process($data)
    {
        $Oprocess_items = $this->app->model('return_process_items');
        $Oreship_items = $this->app->model('reship_items');
        $Oreship = $this->app->model('reship');
        $reship_items = $Oreship_items->getList('*',array('reship_id' => $data['reship_id'],'return_type'=>'return'));

        $reship_info = $Oreship->dump(array('reship_id'=>$data['reship_id']), '*');
        $reship_id = $reship_info['reship_id'];
        
        //[京东一件代发]更新京东服务单信息
        //@todo：推送ACCEPT状态时,服务单结果时已保存,现在只需要更新状态
        if($data['wms_type'] == 'yjdf' && $data['service_bn']){
            $filter = array('reship_id'=>$reship_id, 'service_bn'=>$data['service_bn']);
            $processInfo = $this->dump($filter, 'por_id');
            if($processInfo){
                $saveData = array(
                        'service_status' => 'finish',
                        'last_modified' => time(),
                );
                
                //平台售后状态
                if($data['afsResultType'] && $data['service_bn']){
                    $saveData['afsResultType'] = $data['afsResultType'];
                }
                
                //平台处理环节
                if($data['stepType'] && $data['service_bn']){
                    $saveData['step_type'] = $data['stepType'];
                }
                
                $this->update($saveData, $filter);
                
                return true;
            }
        }
        
        //branch
        $ret=array();
        foreach($reship_items as $k=>$v){
            $reship_items[$k]['branch_id'] = $v['branch_id'] = (int)$v['branch_id'];
            if(!isset($ret[$v['branch_id']])){
                $ret[$v['branch_id']] = $v;
            }
        }
        
        $code_pro_array = array();
        foreach($ret as $k => $v)
        {
            $sdf_data = array(
                'reship_id'=>$data['reship_id'],
                'order_id'=>$reship_info['order_id'],
                'return_id'=>$reship_info['return_id'],
                'member_id'=>$reship_info['member_id'],
                'title'=>$data['title'],
                'content'=>$data['content'],
                'add_time'=>$reship_info['t_begin'],
                'shop_id'=>$reship_info['shop_id'],
                'last_modified'=>$data['last_modified'],
                'memo'=>$data['memo'],
                'branch_id'=>$k,
                'attachment'=>$data['attachment'],   
                'comment'=>$data['comment'],
                'process_data'=>$data['process_data'],
                'recieved'=>'true',
                'verify'=>'false',
                'wms_type' => $data['wms_type'], //WMS仓储类型[yjdf京东一件代发]
                'service_bn' => $data['service_bn'], //服务单号
                'service_type' => $data['service_type'], //售后服务类型
                'package_bn' => $data['package_bn'], //包裹号
                'wms_order_code' => $data['wms_order_code'], //售后申请单号
                'logi_code' => $data['logi_code'], //物流公司编码
                'logi_no' => $data['logi_no'], //物流单号
            );
            
            if($reship_info['return_id']){
                $Oreturn_product = $this->app->model('return_product');
                $product_info = $Oreturn_product->dump(array('return_id'=>$reship_info['return_id']),'title,content,add_time,shop_id,memo,attachment,comment');
                $sdf_data['title'] = $product_info['title'];
                $sdf_data['content'] = $product_info['content'];
                $sdf_data['add_time'] = $product_info['add_time'];
                $sdf_data['shop_id'] = $product_info['shop_id'];
                $sdf_data['memo'] = $product_info['memo'];
                $sdf_data['attachment'] = $product_info['attachment'];
                $sdf_data['comment'] = $product_info['comment'];
            }
            
            //save
            $this->save($sdf_data);
            
            $code_pro_array[$k] = $sdf_data['por_id'];//构造仓库为键，最后插入id为值的一维数组
        }
        
        //items
        foreach ($reship_items as $k => $v) {   
            $process_items = [];
            $process_items['reship_item_id'] = $v['item_id'];
            $process_items['reship_id'] = $v['reship_id'];
            $process_items['order_id'] = $sdf_data['order_id'];
            $process_items['return_id'] = $sdf_data['return_id']?:0;
            $process_items['product_id'] = $v['product_id'];
            $process_items['bn'] = $v['bn'];
            $process_items['name'] = $v['product_name'];
            $process_items['branch_id'] = (int)$v['branch_id'];
            $process_items['op_id'] = (int)$v['op_id'];
            $process_items['acttime'] = time();
            $process_items['por_id'] = $code_pro_array[(int)$v['branch_id']];
            $process_items['num'] = $v['num'];
            $Oprocess_items->insert($process_items);
        }

        return true;
    }

    /**
     * 拒绝发货后，将reship_id对应的return_process,return_process_items表中的删除
     *
     * @return void
     * @author 
     **/
    function cancel_process($refuse_memo)
    {
       $Oreship = $this->app->model('reship');
       $Oreship->update(array('reason'=>$refuse_memo['reason']),array('reship_id'=>$refuse_memo['reship_id']));
       $this->delete(array('reship_id'=>$refuse_memo['reship_id']));
       $Oprocess_items = $this->app->model('return_process_items');
       $Oprocess_items->delete(array('reship_id'=>$refuse_memo['reship_id']));
       return true;
    }
}
?>
